<?php

namespace App\Services\Parsers;

use App\Contracts\ManifestParserInterface;
use App\ValueObjects\ManifestParseResult;
use App\Models\Voyage;
use App\Models\Shipment;
use App\Models\BillOfLading;
use App\Models\Container;
use App\Models\ShipmentItem;
use App\Models\Client;
use App\Models\Port;
use App\Models\Vessel;
use App\Models\ContainerType;
use App\Models\ManifestImport;
use App\Services\Parsers\Concerns\EnsuresUniqueVoyageNumber;
use App\Services\Parsers\Concerns\ExtractsEmbeddedTaxId;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Exception;

/**
 * PARSER PARA NAVSUR.TXT - FORMATO TEXTO JERÁRQUICO
 * 
 * Procesa archivos TXT con estructura:
 * - Marcadores de sección: **BL**, **CONTENEDORES**, **MERCADERIAS**
 * - Campos con formato: CAMPO: valor*
 * - Múltiples BLs, contenedores y mercaderías por archivo
 * - MSC como línea naviera principal
 */
class NavsurTextParser implements ManifestParserInterface
{
    use EnsuresUniqueVoyageNumber;
    use ExtractsEmbeddedTaxId;

    protected array $stats = [
        'processed_bls' => 0,
        'processed_containers' => 0,
        'processed_items' => 0,
        'errors' => 0,
        'warnings' => []
    ];

    /**
     * Verificar si puede parsear el archivo
     */
    public function canParse(string $filePath): bool
    {
        // Verificar extensión TXT
        if (strtolower(pathinfo($filePath, PATHINFO_EXTENSION)) !== 'txt') {
            return false;
        }

        try {
            $handle = fopen($filePath, 'r');
            if (!$handle) {
                return false;
            }

            // Buscar patrones característicos de Navsur
            $foundNavsurPattern = false;
            $lineCount = 0;
            
            while (!feof($handle) && $lineCount < 50) {
                $line = fgets($handle);
                if ($line === false) break;
                
                $line = trim($line);
                
                // Buscar marcador distintivo de Navsur (NUMEROBL es el campo propio del formato)
                if (strpos($line, 'NUMEROBL:') !== false) {
                    $foundNavsurPattern = true;
                    break;
                }
                
                $lineCount++;
            }
            
            fclose($handle);
            return $foundNavsurPattern;
            
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Parsear archivo Navsur TXT
     */
    public function parse(string $filePath): ManifestParseResult
    {
        $startTime = microtime(true);

        try {
            Log::info('Starting Navsur TXT parse', ['file' => $filePath]);

            // Registrar la importación (con dup-check por hash)
            $importRecord = $this->createImportRecord($filePath);

            $content = file_get_contents($filePath);
            if (!$content) {
                throw new Exception('No se pudo leer el archivo');
            }

            // Navsur viene en ISO-8859-1 (latin1). Convertir a UTF-8 para que las
            // descripciones con acentos se guarden y serialicen correctamente.
            if (!mb_check_encoding($content, 'UTF-8')) {
                $content = mb_convert_encoding($content, 'UTF-8', 'ISO-8859-1');
            }

            // Parsear estructura del archivo
            $bls = $this->parseAllBLs($content);
            
            if (empty($bls)) {
                return ManifestParseResult::failure(['No se encontraron BLs en el archivo']);
            }

            // Procesar en transacción
            $result = DB::transaction(function () use ($bls, $importRecord, $startTime) {
                // Crear voyage único para todos los BLs
                $voyageData = $this->extractVoyageData($bls[0]);
                $voyage = $this->findOrCreateVoyage($voyageData);
                
                // Crear shipment
                $shipment = $this->findOrCreateShipment($voyage, $voyageData);
                
                $allBills = [];
                $allContainers = [];
                $allItems = [];

                // Procesar cada BL
                foreach ($bls as $blData) {
                    // Crear BillOfLading
                    $bill = $this->createBillOfLading($shipment, $blData);
                    $allBills[] = $bill;
                    $this->stats['processed_bls']++;
                    
                    // Procesar contenedores
                    if (!empty($blData['containers'])) {
                        foreach ($blData['containers'] as $containerData) {
                            $container = $this->createContainer($bill, $containerData);
                            if ($container) {
                                $allContainers[] = $container;
                                $this->stats['processed_containers']++;
                            }
                            
                            // Procesar items de mercadería
                            if (!empty($containerData['items'])) {
                                foreach ($containerData['items'] as $itemData) {
                                    $item = $this->createShipmentItem($bill, $itemData);
                                    if ($item) {
                                        $allItems[] = $item;
                                        $this->stats['processed_items']++;
                                        // Vincular este contenedor con su ítem (pivote container_shipment_item)
                                        if ($container) {
                                            $this->attachContainerToItem($container, $item);
                                        }
                                    }
                                }
                            }
                        }
                    }
                }

                // Registrar objetos creados y completar el registro de importación.
                // El revert reconstruye items/containers (incluido el pivote) desde el voyage_id.
                if ($importRecord) {
                    $importRecord->recordCreatedObjects([
                        'voyage'   => [$voyage->id],
                        'shipment' => [$shipment->id],
                        'bill'     => array_map(fn($b) => $b->id, $allBills),
                        'item'     => array_map(fn($i) => $i->id, $allItems),
                    ]);
                    $importRecord->markAsCompleted([
                        'voyage_id'               => $voyage->id,
                        'created_bills'           => count($allBills),
                        'created_items'           => count($allItems),
                        'processing_time_seconds' => round(microtime(true) - $startTime, 2),
                        'import_statistics'       => $this->stats,
                        'notes'                   => 'Importación Navsur completada',
                    ]);
                }

                return [
                    'voyage' => $voyage,
                    'shipment' => $shipment,
                    'bills' => $allBills,
                    'containers' => $allContainers,
                    'items' => $allItems
                ];
            });

            Log::info('Navsur parsing completed', $this->stats);

            return ManifestParseResult::success(
                voyage: $result['voyage'],
                shipments: [$result['shipment']],
                containers: $result['containers'],
                billsOfLading: $result['bills'],
                warnings: $this->stats['warnings'],
                statistics: $this->stats
            );

        } catch (Exception $e) {
            // Viaje ya existente (bloqueo global de duplicado): mensaje amable, no SQL crudo.
            if (strpos($e->getMessage(), 'voyages_voyage_number_unique') !== false) {
                if (isset($importRecord)) {
                    $importRecord->markAsFailed([
                        'Este archivo ya fue importado anteriormente. El viaje ya existe en el sistema y no se duplicó ningún dato.'
                    ], ['import_statistics' => $this->stats]);
                }
                return ManifestParseResult::failure([
                    'Este archivo ya fue importado anteriormente. El viaje ya existe en el sistema y no se duplicó ningún dato. Si necesita importarlo de nuevo, primero revierta la importación desde el Historial de Importaciones.'
                ], $this->stats['warnings'], $this->stats);
            }

            if (isset($importRecord)) {
                $importRecord->markAsFailed([$e->getMessage()], [
                    'import_statistics' => $this->stats,
                ]);
            }
            Log::error('Navsur parsing failed', [
                'file' => $filePath,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return ManifestParseResult::failure(
                [$e->getMessage()],
                $this->stats['warnings'],
                $this->stats
            );
        }
    }

    /**
     * Parsear todos los BLs del archivo
     */
    protected function parseAllBLs(string $content): array
    {
        $bls = [];
        
        // Dividir por marcador de BL
        $blSections = preg_split('/\*\*BL\*\*/', $content);
        
        foreach ($blSections as $blSection) {
            if (trim($blSection) === '' || strpos($blSection, 'NUMEROBL:') === false) {
                continue;
            }
            
            // Extraer fin del BL
            $endPos = strpos($blSection, '**FIN BL**');
            if ($endPos !== false) {
                $blSection = substr($blSection, 0, $endPos);
            }
            
            $bl = $this->parseBLSection($blSection);
            if (!empty($bl['numero_bl'])) {
                $bls[] = $bl;
            }
        }
        
        return $bls;
    }

    /**
     * Parsear una sección de BL
     */
    protected function parseBLSection(string $section): array
    {
        $bl = [
            'numero_bl' => $this->extractValue($section, 'NUMEROBL:'),
            'cod_booking' => $this->extractValue($section, 'CODBOOKING:'),
            'cod_programacion' => $this->extractValue($section, 'CODPROGRAMACION:'),
            'buque' => $this->extractValue($section, 'BUQUE:'),
            'viaje' => $this->extractValue($section, 'VIAJE:'),
            'bandera' => $this->extractValue($section, 'BANDERA:'),
            'condicion_flete' => $this->extractValue($section, 'CONDICIONFLETE:'),
            'puerto_carga' => $this->extractValue($section, 'CODPUERTODECARGA:'),
            'puerto_descarga' => $this->extractValue($section, 'CODPUERTODEDESCARGA:'),
            'destino_final' => $this->extractValue($section, 'DESTINOFINAL:'),
            'cargador_nombre' => $this->extractValue($section, 'CARGADORNOMBRE:'),
            'consignatario_nombre' => $this->extractValue($section, 'CONSIGNATARIONOMBRE:'),
            'notificatario1_nombre' => $this->extractValue($section, 'NOTIFICATARIO1NOMBRE:'),
            'containers' => []
        ];
        
        // Parsear contenedores
        $containerSections = $this->extractContainerSections($section);
        foreach ($containerSections as $containerSection) {
            $container = $this->parseContainerSection($containerSection);
            if (!empty($container['cod_contenedor'])) {
                $bl['containers'][] = $container;
            }
        }
        
        return $bl;
    }

    /**
     * Extraer secciones de contenedores.
     *
     * Nota de formato Navsur: dentro de un BL, solo el primer contenedor abre con
     * **CONTENEDORES**; los siguientes vienen sin apertura pero todos cierran con
     * **FIN CONTENEDORES**. Por eso NO se usa el par apertura/cierre. En su lugar:
     * cada contenedor es el bloque que TERMINA en **FIN CONTENEDORES**, y sus
     * mercaderías son el bloque **MERCADERIAS**...**FIN MERCADERIAS** que lo sigue.
     */
    protected function extractContainerSections(string $section): array
    {
        $containers = [];

        // 1) Cada bloque de contenedor: todo lo que precede a un **FIN CONTENEDORES**.
        //    Se parte por ese cierre y se descarta lo que no tenga CODCONTENEDOR.
        $contParts = preg_split('/\*\*FIN CONTENEDORES\*\*/', $section);

        // 2) Cada bloque de mercaderías, en orden de aparición.
        preg_match_all('/\*\*MERCADERIAS\*\*(.*?)\*\*FIN MERCADERIAS\*\*/s', $section, $mercMatches);
        $mercaderiasBloques = $mercMatches[1] ?? [];

        $idx = 0;
        foreach ($contParts as $part) {
            if (strpos($part, 'CODCONTENEDOR:') === false) {
                continue;
            }

            // Quitar cualquier resto de un **MERCADERIAS** previo que haya quedado al inicio.
            $cleanPos = strrpos($part, '**FIN MERCADERIAS**');
            if ($cleanPos !== false) {
                $part = substr($part, $cleanPos + strlen('**FIN MERCADERIAS**'));
            }
            // Quitar la apertura **CONTENEDORES** si está presente (solo en el primero).
            $part = str_replace('**CONTENEDORES**', '', $part);

            $containers[] = [
                'container' => $part,
                'items'     => $mercaderiasBloques[$idx] ?? '',
            ];
            $idx++;
        }

        return $containers;
    }

    /**
     * Parsear sección de contenedor
     */
    protected function parseContainerSection(array $containerData): array
    {
        $containerText = $containerData['container'];
        $itemsText = $containerData['items'];
        
        $container = [
            'cod_contenedor' => $this->extractValue($containerText, 'CODCONTENEDOR:'),
            'tipo_contenedor' => $this->extractValue($containerText, 'CODTIPOCONTENEDOR:'),
            'medida' => $this->extractValue($containerText, 'CODMEDIDA:'),
            'tara' => $this->extractValue($containerText, 'TARA:'),
            'temperatura' => $this->extractValue($containerText, 'TEMPERATURA:'),
            'precintos_linea' => $this->extractValue($containerText, 'PRECINTOSLINEA:'),
            'precintos_aduana' => $this->extractValue($containerText, 'PRECINTOSADUANA:'),
            'precintos_senacsa' => $this->extractValue($containerText, 'PRECINTOSENACSA:'),
            'items' => []
        ];
        
        // Parsear items
        if (!empty($itemsText)) {
            $items = $this->parseItemsSection($itemsText);
            $container['items'] = $items;
        }
        
        return $container;
    }

    /**
     * Parsear sección de items de mercadería
     */
    protected function parseItemsSection(string $section): array
    {
        $items = [];

        // Separar cada ítem: cada uno arranca en "ITEM:" y termina donde empieza el siguiente.
        // Se usa lookahead para no consumir el "ITEM:" del próximo bloque.
        $chunks = preg_split('/(?=ITEM:\s*\/\*)/', $section);

        foreach ($chunks as $chunk) {
            if (strpos($chunk, 'ITEM:') === false) {
                continue;
            }

            $mercaderia = $this->extractValue($chunk, 'MERCADERIA:');

            // Sin descripción de mercadería no hay ítem válido.
            if (empty($mercaderia)) {
                continue;
            }

            $items[] = [
                'item'                => $this->extractValue($chunk, 'ITEM:'),
                'titulo'              => $this->extractValue($chunk, 'TITULO:') ?? '',
                'embalaje'            => $this->extractValue($chunk, 'EMBALAJE:') ?? '',
                'mercaderia'          => $mercaderia,
                'cantidad'            => intval($this->extractValue($chunk, 'CANTIDAD:') ?? 0),
                'peso_neto'           => floatval($this->extractValue($chunk, 'PESONETO:') ?? 0),
                'peso_bruto'          => floatval($this->extractValue($chunk, 'PESOBRUTO:') ?? 0),
                'cubitaje'            => floatval($this->extractValue($chunk, 'CUBITAJE:') ?? 0),
                'imo'                 => $this->extractValue($chunk, 'IMO:') ?? '',
                'partida_arancelaria' => $this->extractValue($chunk, 'PARTIDAARANCELARIA:') ?? '',
            ];
        }

        return $items;
    }

    /**
     * Extraer valor entre marcadores de comentario
     */
    protected function extractValue(string $text, string $field): ?string
    {
        $pattern = '/' . preg_quote($field, '/') . '\s*\/\*(.*?)\*\//s';
        if (preg_match($pattern, $text, $matches)) {
            return trim($matches[1]);
        }
        return null;
    }

    /**
     * Extraer datos del voyage del primer BL
     */
    protected function extractVoyageData(array $bl): array
    {
        return [
            'voyage_number' => $bl['viaje'] ?? 'NAV-' . date('Ymd'),
            'vessel_name' => $bl['buque'] ?? 'NAVSUR VESSEL',
            'flag' => $bl['bandera'] ?? 'PARAGUAYA',
            'pol' => $bl['puerto_carga'] ?? 'PYCAP',
            'pod' => $bl['puerto_descarga'] ?? 'ARBUE'
        ];
    }

    /**
     * Buscar o crear voyage - VALORES ENUM CORREGIDOS
     */
    protected function findOrCreateVoyage(array $data): Voyage
    {
        // Obtener company_id correctamente
        $user = auth()->user();
        $companyId = null;

        if ($user->userable_type === 'App\Models\Company' && $user->userable_id) {
            $companyId = (int) $user->userable_id;
        } elseif ($user->userable_type === 'App\Models\Operator' && $user->userable) {
            $companyId = $user->userable->company_id;
        }

        if (!$companyId) {
            throw new \Exception("Usuario no tiene empresa asignada. User ID: {$user->id}");
        }

        // El voyage_number es único global. Si ya existe (en cualquier empresa),
        // se bloquea la importación con un error claro en lugar de reusar el viaje.
        $this->guardVoyageNumberIsFree($data['voyage_number']);

        // Buscar o crear vessel con campos obligatorios correctos
        $vesselName = $data['vessel_name'] ?? 'NAVSUR VESSEL';
        $registrationNumber = $data['vessel_name'] ?? 'NAV-' . date('Ymd-His');
        
        $vessel = Vessel::firstOrCreate(
            ['registration_number' => $registrationNumber],
            [
                'name' => $vesselName,
                'company_id' => $companyId,
                'vessel_type_id' => 1, // Usar vessel_type_id en lugar de vessel_type
                'flag_country_id' => $this->mapFlagToCountryId($data['flag'] ?? 'PARAGUAYA'),
                'length_meters' => 50.0,
                'beam_meters' => 12.0,
                'draft_meters' => 3.0,
                'cargo_capacity_tons' => 1000.0,
                'operational_status' => 'active',
                'active' => true
            ]
        );

        // Buscar puertos
        $originPort = $this->findOrCreatePort($data['pol']);
        $destPort = $this->findOrCreatePort($data['pod']);

        // Crear voyage con valores enum CORREGIDOS
        $voyage = Voyage::create([
            'voyage_number' => $data['voyage_number'],
            'company_id' => $companyId, 
            'lead_vessel_id' => $vessel->id,
            'origin_port_id' => $originPort->id,
            'destination_port_id' => $destPort->id,
            'origin_country_id' => $originPort->country_id ?? 2, // PY
            'destination_country_id' => $destPort->country_id ?? 1, // AR
            'status' => 'planning',
            
            // CORREGIDO: usar valores válidos del enum voyage_type
            'voyage_type' => 'single_vessel', // En lugar de 'commercial'
            
            // CORREGIDO: usar valores válidos del enum cargo_type  
            'cargo_type' => 'export', // En lugar de 'containers'
            
            'departure_date' => now()->addDays(7),
            'estimated_arrival_date' => now()->addDays(10),
            'total_cargo_capacity_tons' => $vessel->cargo_capacity_tons ?? 1000.0,
            'total_container_capacity' => $vessel->container_capacity ?? 40,
            'total_cargo_weight_loaded' => 0,
            'total_containers_loaded' => 0,
            'capacity_utilization_percentage' => 0
        ]);

        return $voyage;
    }

    /**
     * NUEVO MÉTODO: Mapear bandera a country_id
     */
    protected function mapFlagToCountryId(string $flag): int
    {
        $flag = strtoupper(trim(str_replace(['/*', '*/'], '', $flag)));
        
        if (str_contains($flag, 'PARAGUAY')) return 2; // Paraguay
        if (str_contains($flag, 'ARGENTIN')) return 1; // Argentina  
        if (str_contains($flag, 'BRASIL')) return 3; // Brasil
        
        return 2; // Default: Paraguay
    }

    
    /**
     * Buscar o crear shipment
     */
    protected function findOrCreateShipment(Voyage $voyage, array $data): Shipment
    {
        $shipment = Shipment::where('voyage_id', $voyage->id)
            ->where('sequence_in_voyage', 1)
            ->first();

        if ($shipment) {
            return $shipment;
        }

        $vessel = Vessel::where('name', $data['vessel_name'])->first();

        $shipment = Shipment::create([
            'voyage_id' => $voyage->id,
            'vessel_id' => $vessel->id ?? $voyage->lead_vessel_id,
            'shipment_number' => 'SHP-' . $voyage->voyage_number . '-001',
            'sequence_in_voyage' => 1,
            'vessel_role' => 'single',
            'is_lead_vessel' => true,
            'cargo_capacity_tons' => 5000,
            'container_capacity' => 200,
            'status' => 'planning'
        ]);

        return $shipment;
    }

    /**
     * Registrar la importación en ManifestImport (con dup-check por hash).
     */
    protected function createImportRecord(string $filePath): ManifestImport
    {
        $user = auth()->user();
        if (!$user) {
            throw new Exception('Usuario no autenticado para crear registro de importación');
        }

        $fileName = basename($filePath);
        $fileSize = file_exists($filePath) ? filesize($filePath) : null;
        $fileHash = file_exists($filePath) ? ManifestImport::generateFileHash($filePath) : null;

        // Obtener company_id (mismo criterio que el resto del parser)
        $companyId = null;
        if ($user->userable_type === 'App\Models\Company' && $user->userable_id) {
            $companyId = (int) $user->userable_id;
        } elseif ($user->userable_type === 'App\Models\Operator' && $user->userable) {
            $companyId = $user->userable->company_id;
        }

        if ($fileHash && $companyId) {
            $existing = ManifestImport::isFileAlreadyImported($fileHash, $companyId);
            if ($existing) {
                throw new Exception("Este archivo ya fue importado anteriormente (ID: {$existing->id})");
            }
        }

        return ManifestImport::createForImport([
            'company_id'      => $companyId,
            'user_id'         => $user->id,
            'file_name'       => $fileName,
            'file_format'     => 'navsur',
            'file_size_bytes' => $fileSize,
            'file_hash'       => $fileHash,
            'parser_config'   => [
                'parser_class' => self::class,
            ],
        ]);
    }

    /**
     * Crear BillOfLading
     */
    protected function createBillOfLading(Shipment $shipment, array $data): BillOfLading
    {
        // Obtener o crear clientes
        $shipper = $this->findOrCreateClient($data['cargador_nombre'], 'shipper');
        $consignee = $this->findOrCreateClient($data['consignatario_nombre'], 'consignee');
        $notify = $this->findOrCreateClient($data['notificatario1_nombre'], 'notify');

        // Obtener puertos
        $loadingPort = $this->findOrCreatePort($data['puerto_carga']);
        $dischargePort = $this->findOrCreatePort($data['puerto_descarga']);
        $finalPort = !empty($data['destino_final']) 
            ? $this->findOrCreatePort($data['destino_final'])
            : $dischargePort;

        // AGREGAR: Fechas obligatorias con valores por defecto
        //$billDate = null; // Fecha actual como fallback
        //$loadingDate = null->addDays(1); // Un día después para loading

        // ✅ NAVSUR no incluye fechas - usar fecha actual como última opción
        Log::warning('⚠️ NAVSUR.TXT no contiene fechas específicas - usando fecha actual');
        $billDate = now();
        $loadingDate = now()->addDays(1);

        // Crear BL con campos obligatorios CORREGIDOS
        $bill = BillOfLading::create([
            'shipment_id' => $shipment->id,
            'bill_number' => $data['numero_bl'],
            'master_bill_number' => $data['cod_booking'] ?? null,
            'internal_reference' => $data['cod_programacion'] ?? null,
            
            // AGREGADO: Campos de fecha obligatorios
            'bill_date' => $billDate,
            'loading_date' => $loadingDate,
            
            // Clientes
            'shipper_id' => $shipper->id,
            'consignee_id' => $consignee->id,
            'notify_party_id' => $notify?->id,
            
            // Puertos
            'loading_port_id' => $loadingPort->id,
            'discharge_port_id' => $dischargePort->id,
            'final_destination_port_id' => $finalPort->id,
            
            // Términos y estado
            'freight_terms' => $this->mapFreightTerms($data['condicion_flete'] ?? 'PREPAID'),
            'status' => 'draft',
            
            // Tipos obligatorios con valores por defecto
            'primary_cargo_type_id' => $this->validateCargoTypeId($data),
            'primary_packaging_type_id' => $this->validatePackagingTypeId($data), // Bags/Bultos
            
            // AGREGADO: Campos de peso obligatorios con valores por defecto
            'gross_weight_kg' => floatval($data['peso_bruto'] ?? 0),
            'net_weight_kg' => floatval($data['peso_neto'] ?? 0),
            'total_packages' => intval($data['cantidad_bultos'] ?? 1),
            'volume_m3' => floatval($data['cubitaje'] ?? 0),
            
            // CORREGIDO: campo cargo_description es obligatorio
            'cargo_description' => $this->validateCargoDescription($data),
            'special_instructions' => !empty($data['instrucciones']) ? [$data['instrucciones']] : null,
            'internal_notes' => 'Importado desde archivo Navsur'
        ]);

        Log::info('BillOfLading creado desde Navsur', [
            'bill_id' => $bill->id,
            'bill_number' => $bill->bill_number,
            'bill_date' => $bill->bill_date->toDateString(),
            'loading_date' => $bill->loading_date->toDateString()
        ]);

        return $bill;
    }

    protected function validateCargoTypeId(array $data): int
    {
        // ✅ NAVSUR no tiene cargo_type_id - usar tipo general por defecto
        Log::warning('⚠️ NAVSUR.TXT no contiene cargo_type_id - usando tipo general');
        return 1; // General cargo como último recurso
    }

    /**
     * NUEVO MÉTODO: Mapear términos de flete
     */
    protected function mapFreightTerms(string $terms): string
    {
        $terms = strtoupper(trim(str_replace(['/*', '*/'], '', $terms)));
        
        if (str_contains($terms, 'PREPAID') || str_contains($terms, 'PREPAGADO')) {
            return 'prepaid';
        }
        
        if (str_contains($terms, 'COLLECT') || str_contains($terms, 'COBRAR')) {
            return 'collect';
        }
        
        if (str_contains($terms, 'THIRD') || str_contains($terms, 'TERCERO')) {
            return 'third_party';
        }
        
        return 'prepaid'; // Default
    }
 
    /**
     * Vincular un Container con un ShipmentItem en el pivote container_shipment_item.
     */
    protected function attachContainerToItem(Container $container, ShipmentItem $item): void
    {
        $exists = DB::table('container_shipment_item')
            ->where('container_id', $container->id)
            ->where('shipment_item_id', $item->id)
            ->exists();

        if ($exists) {
            return;
        }

        DB::table('container_shipment_item')->insert([
            'container_id' => $container->id,
            'shipment_item_id' => $item->id,
            'package_quantity' => $item->package_quantity,
            'gross_weight_kg' => $item->gross_weight_kg,
            'net_weight_kg' => $item->net_weight_kg,
            'volume_m3' => $item->volume_m3,
            'status' => 'loaded',
            'created_date' => now(),
            'created_by_user_id' => auth()->id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

/**
 * Crear Container
 */
protected function createContainer(BillOfLading $bill, array $data): ?Container
{
    if (empty($data['cod_contenedor'])) {
        return null;
    }

    // Verificar si ya existe
    $existing = Container::where('container_number', $data['cod_contenedor'])->first();
    if ($existing) {
        Log::info('Container already exists', ['number' => $data['cod_contenedor']]);
        return $existing;
    }

    $containerType = $this->findOrCreateContainerType(
        $data['tipo_contenedor'] ?? '20DV',
        $data['medida'] ?? '20'
    );

    $container = Container::create([
        'container_number' => $data['cod_contenedor'],
        'container_type_id' => $containerType->id,
        'tare_weight_kg' => $this->validateTareWeight($data),
        'max_gross_weight_kg' => $this->validateMaxGrossWeight($data),
        'condition' => 'L', // Loaded
        
        // CORREGIDO: usar valor válido del enum operational_status
        'operational_status' => 'loaded', // En lugar de 'in_use'
        
        'current_port_id' => $bill->loading_port_id,
        'active' => true
    ]);

    // Guardar sellos en el BL si existen
    if (!empty($data['precintos_linea'])) {
        $bill->update([
            'bl_seals_numbers' => $this->extractSeals($data)
        ]);
    }

    return $container;
}

    /**
     * Crear ShipmentItem
     */
    protected function createShipmentItem(BillOfLading $bill, array $data): ?ShipmentItem
    {
        if (empty($data['mercaderia'])) {
            return null;
        }

        // Extraer HS Code de la descripción
        $hsCode = null;
        if (preg_match('/(?:HS CODE|NCM)[:\s]*([0-9\.]+)/i', $data['mercaderia'], $matches)) {
            $hsCode = $matches[1];
        }

        // line_number correlativo dentro del BL: el "ITEM:" del archivo reinicia en cada
        // contenedor (1,2,3...), pero la BD exige line_number único por bill_of_lading.
        $lineNumber = ShipmentItem::where('bill_of_lading_id', $bill->id)->count() + 1;

        $item = ShipmentItem::create([
            'bill_of_lading_id' => $bill->id,
            'line_number' => $lineNumber,
            'cargo_type_id' => 1,
            'packaging_type_id' => $this->mapPackagingType($data['embalaje'] ?? 'BAGS'),
            'package_quantity' => intval($data['cantidad'] ?? 1),
            'gross_weight_kg' => floatval($data['peso_bruto'] ?? 0),
            'net_weight_kg' => floatval($data['peso_neto'] ?? 0),
            'volume_m3' => floatval($data['cubitaje'] ?? 0),
            'item_description' => substr($data['mercaderia'], 0, 1000),
            'commodity_code' => $hsCode ?? $data['partida_arancelaria'],
            'dangerous_cargo' => !empty($data['imo']),
            'imo_code' => $data['imo'] ?? null
        ]);

        return $item;
    }

    /**
     * Buscar o crear cliente
     */
    protected function findOrCreateClient(?string $name, string $type): ?Client
    {
        if (empty($name)) {
            return null;
        }

        // Limpiar nombre
        $name = trim(str_replace(['/*', '*/'], '', $name));
        
        if (empty($name)) {
            return null;
        }

        // Resolver tax embebido en el nombre (CUIT/RUC). Sin dato real -> null (no fabrica).
        $taxId = $this->resolveTaxId(null, $name);

        // 1. Buscar por tax_id de forma GLOBAL (mismo CUIT = mismo cliente, sin importar empresa)
        if ($taxId) {
            $client = Client::where('tax_id', $taxId)->first();
            if ($client) {
                Log::info('Cliente encontrado por tax_id', [
                    'tax_id' => $taxId,
                    'client_id' => $client->id
                ]);
                return $client;
            }
        }

        // 2. Si no se encontró por tax_id, buscar por nombre (global)
        $client = Client::where('legal_name', $name)
            ->orWhere('commercial_name', $name)
            ->first();

        if ($client) {
            return $client;
        }

        // Obtener company_id correctamente
        $user = auth()->user();
        $companyId = null;

        if ($user->userable_type === 'App\Models\Company' && $user->userable_id) {
            $companyId = (int) $user->userable_id;
        } elseif ($user->userable_type === 'App\Models\Operator' && $user->userable) {
            $companyId = $user->userable->company_id;
        }

        if (!$companyId) {
            Log::warning('No se pudo obtener company_id para crear cliente', [
                'user_id' => $user->id,
                'userable_type' => $user->userable_type,
                'client_name' => $name
            ]);
            
            $companyId = 1;
        }

        // Determinar país basado en el nombre
        $countryId = 1; // Argentina por defecto
        if (str_contains(strtoupper($name), 'PARAGUAY')) {
            $countryId = 2; // Paraguay
        }

        // Crear cliente (tax_id real si vino embebido; null si no, sin fabricar)
        $client = Client::create([
            'tax_id' => $taxId,
            'country_id' => $countryId,
            'document_type_id' => $countryId == 1 ? 1 : 2, // CUIT o RUC
            'legal_name' => $name,
            'commercial_name' => $name,
            'status' => 'active',
            'created_by_company_id' => $companyId,
            'verified_at' => now() // Agregar timestamp de verificación
        ]);

        if (!$taxId) {
            $this->stats['warnings'][] = "Cliente '{$name}' creado sin tax_id declarado.";
        }

        return $client;
    }
    /**
     * Buscar o crear puerto
     */
    protected function findOrCreatePort(string $code): Port
    {
        if (empty($code)) {
            $code = 'UNKNOWN';
        }

        $code = strtoupper(trim(str_replace(['/*', '*/'], '', $code)));

        $port = Port::where('code', $code)->first();
        
        if ($port) {
            return $port;
        }

        // Determinar país y ciudad basado en código
        $countryId = 1; // Argentina por defecto
        $cityName = 'Ciudad Desconocida'; // Valor por defecto para city (obligatorio)
        
        if (str_starts_with($code, 'PY')) {
            $countryId = 2; // Paraguay
            $cityName = $this->mapParaguayanPortCity($code);
        } elseif (str_starts_with($code, 'BR')) {
            $countryId = 3; // Brasil
            $cityName = $this->mapBrazilianPortCity($code);
        } else {
            // Argentina o códigos genéricos
            $cityName = $this->mapArgentinianPortCity($code);
        }

        $port = Port::create([
            'code' => $code,
            'name' => 'Puerto ' . $code,
            'city' => $cityName, // CORREGIDO: Campo obligatorio agregado
            'country_id' => $countryId,
            'port_type' => 'river',
            'active' => true,
            'handles_containers' => true,
            'handles_bulk_cargo' => true,
            'handles_general_cargo' => true,
            'has_customs_office' => true,
            'accepts_new_vessels' => true
        ]);

        $this->stats['warnings'][] = "Puerto '{$code}' creado automáticamente en {$cityName}";

        return $port;
    }

    /**
     * NUEVO MÉTODO: Mapear códigos paraguayos a ciudades
     */
    protected function mapParaguayanPortCity(string $code): string
    {
        $cityMap = [
            'PYCAP' => 'Capitán Carmelo Peralta',
            'PYASU' => 'Asunción',
            'PYVIL' => 'Villeta',
            'PYCON' => 'Concepción',
            'PYPIL' => 'Pilar',
            'PYALB' => 'Puerto Casado'
        ];
        
        return $cityMap[$code] ?? 'Asunción'; // Default Paraguay
    }

    /**
     * NUEVO MÉTODO: Mapear códigos argentinos a ciudades
     */
    protected function mapArgentinianPortCity(string $code): string
    {
        $cityMap = [
            'ARBUE' => 'Buenos Aires',
            'ARROS' => 'Rosario',
            'ARSFE' => 'Santa Fe',
            'ARPAR' => 'Paraná',
            'ARCOR' => 'Corrientes',
            'ARFOR' => 'Formosa',
            'ARBAH' => 'Bahía Blanca'
        ];
        
        return $cityMap[$code] ?? 'Buenos Aires'; // Default Argentina
    }

    /**
     * NUEVO MÉTODO: Mapear códigos brasileños a ciudades
     */
    protected function mapBrazilianPortCity(string $code): string
    {
        $cityMap = [
            'BRRIG' => 'Rio Grande',
            'BRPOA' => 'Porto Alegre',
            'BRSFS' => 'Santos',
            'BRSSZ' => 'Santos'
        ];
        
        return $cityMap[$code] ?? 'Porto Alegre'; // Default Brasil
    }

/**
 * Buscar o crear tipo de contenedor - VERSIÓN SIMPLIFICADA
 */
protected function findOrCreateContainerType(string $code, string $size): ContainerType
{
    $code = strtoupper(trim(str_replace(['/*', '*/'], '', $code)));
    
    // Mapear códigos Navsur a códigos estándar existentes en la tabla
    $codeMapping = [
        '20DV' => '20GP',  // Dry Van -> General Purpose
        '40DV' => '40GP',  // Dry Van -> General Purpose  
        '20GP' => '20GP',  // Ya correcto
        '40GP' => '40GP',  // Ya correcto
        '40HC' => '40HC',  // Ya correcto
        '20RF' => '20RF',  // Refrigerado
        '40RF' => '40HC',  // No hay 40RF, usar 40HC
        '20TN' => '20GP',  // Tank -> GP como fallback
        '40TN' => '40GP',  // Tank -> GP como fallback
        '20OT' => '20GP',  // Open Top -> GP como fallback
        '40OT' => '40GP',  // Open Top -> GP como fallback
        '20FR' => '20GP',  // Flat Rack -> GP como fallback
        '40FR' => '40GP',  // Flat Rack -> GP como fallback
    ];
    
    // Intentar mapear el código
    $mappedCode = $codeMapping[$code] ?? null;
    
    // Si no se puede mapear, intentar detectar por tamaño
    if (!$mappedCode) {
        if (str_contains($code, '20')) {
            $mappedCode = '20GP';
        } elseif (str_contains($code, '40')) {
            $mappedCode = str_contains($code, 'HC') ? '40HC' : '40GP';
        } else {
            $mappedCode = '20GP'; // Fallback por defecto
        }
    }
    
    // Buscar el tipo de contenedor existente
    $type = ContainerType::where('code', $mappedCode)->where('active', true)->first();
    
    if ($type) {
        // Si el código original es diferente, registrar warning
        if ($code !== $mappedCode) {
            $this->stats['warnings'][] = "Tipo de contenedor '{$code}' mapeado a '{$mappedCode}'";
        }
        return $type;
    }
    
    // Si aún no se encuentra, usar el primer tipo activo disponible
    $type = ContainerType::where('active', true)->first();
    
    if ($type) {
        $this->stats['warnings'][] = "Tipo de contenedor '{$code}' no encontrado, usando '{$type->code}' por defecto";
        return $type;
    }
    
    // Si no hay ningún tipo en la tabla, throw exception
    throw new \Exception("No hay tipos de contenedor disponibles en la tabla container_types. Ejecute ContainerTypesSeeder.");
}

    /**
     * Detectar categoría de contenedor
     */
    protected function detectContainerCategory(string $code): string
    {
        if (str_contains($code, 'RH') || str_contains($code, 'RF')) return 'reefer';
        if (str_contains($code, 'HC')) return 'high_cube';
        if (str_contains($code, 'TN') || str_contains($code, 'TK')) return 'tank';
        if (str_contains($code, 'OT')) return 'open_top';
        if (str_contains($code, 'FR')) return 'flat_rack';
        return 'dry';
    }

    /**
     * Mapear tipo de embalaje
     */
    protected function mapPackagingType(string $packaging): int
    {
        $packaging = strtoupper(trim(str_replace(['/*', '*/'], '', $packaging)));
        
        // Mapeo básico - se debe ajustar según tabla packaging_types real
        $map = [
            'BAGS' => 1,
            'CARTONS' => 2,
            'PALLETS' => 3,
            'BARRELS' => 4,
            'BOXES' => 5,
            'PALLET(S)' => 3,
            'BARREL(S)' => 4
        ];

        return $map[$packaging] ?? 1;
    }

    /**
     * Mapear bandera a código ISO
     */
    protected function mapFlag(string $flag): string
    {
        $flag = strtoupper(trim(str_replace(['/*', '*/'], '', $flag)));
        
        if (str_contains($flag, 'PARAGUAY')) return 'PY';
        if (str_contains($flag, 'ARGENTIN')) return 'AR';
        if (str_contains($flag, 'BRASIL')) return 'BR';
        
        return 'PY'; // Default
    }

    /**
     * Extraer números de sellos
     */
    protected function extractSeals(array $data): ?string
    {
        $seals = [];
        
        $sealFields = [
            'precintos_linea',
            'precintos_aduana',
            'precintos_senacsa',
            'precintos_cliente'
        ];

        foreach ($sealFields as $field) {
            if (!empty($data[$field])) {
                $sealValue = trim(str_replace(['/*', '*/'], '', $data[$field]));
                if (!empty($sealValue)) {
                    $seals[] = $sealValue;
                }
            }
        }

        return !empty($seals) ? implode(', ', $seals) : null;
    }

    /**
     * Validar datos parseados antes de procesamiento
     */
    public function validate(array $data): array
    {
        $errors = [];

        // Validar que existe al menos un BL
        if (empty($data) || !is_array($data)) {
            $errors[] = 'No se encontraron datos válidos en el archivo';
            return $errors;
        }

        foreach ($data as $index => $bl) {
            $blIndex = $index + 1;

            // Validar campos obligatorios del BL
            if (empty($bl['numero_bl'])) {
                $errors[] = "BL #{$blIndex}: Número de BL es obligatorio";
            }

            if (empty($bl['buque'])) {
                $errors[] = "BL #{$blIndex}: Nombre del buque es obligatorio";
            }

            if (empty($bl['viaje'])) {
                $errors[] = "BL #{$blIndex}: Número de viaje es obligatorio";
            }

            // Validar puertos
            if (empty($bl['puerto_carga'])) {
                $errors[] = "BL #{$blIndex}: Puerto de carga es obligatorio";
            }

            if (empty($bl['puerto_descarga'])) {
                $errors[] = "BL #{$blIndex}: Puerto de descarga es obligatorio";
            }

            // Validar partes involucradas
            if (empty($bl['cargador_nombre'])) {
                $errors[] = "BL #{$blIndex}: Nombre del cargador es obligatorio";
            }

            if (empty($bl['consignatario_nombre'])) {
                $errors[] = "BL #{$blIndex}: Nombre del consignatario es obligatorio";
            }

            // Validar contenedores
            if (empty($bl['containers']) || !is_array($bl['containers'])) {
                $errors[] = "BL #{$blIndex}: Debe tener al menos un contenedor";
            } else {
                foreach ($bl['containers'] as $containerIndex => $container) {
                    $contIndex = $containerIndex + 1;

                    if (empty($container['cod_contenedor'])) {
                        $errors[] = "BL #{$blIndex}, Contenedor #{$contIndex}: Código de contenedor es obligatorio";
                    }

                    if (empty($container['tipo_contenedor'])) {
                        $errors[] = "BL #{$blIndex}, Contenedor #{$contIndex}: Tipo de contenedor es obligatorio";
                    }

                    // Validar items del contenedor
                    if (empty($container['items']) || !is_array($container['items'])) {
                        $errors[] = "BL #{$blIndex}, Contenedor #{$contIndex}: Debe tener al menos un item de mercadería";
                    } else {
                        foreach ($container['items'] as $itemIndex => $item) {
                            $itemNum = $itemIndex + 1;

                            if (empty($item['mercaderia'])) {
                                $errors[] = "BL #{$blIndex}, Contenedor #{$contIndex}, Item #{$itemNum}: Descripción de mercadería es obligatoria";
                            }

                            if (empty($item['cantidad']) || $item['cantidad'] <= 0) {
                                $errors[] = "BL #{$blIndex}, Contenedor #{$contIndex}, Item #{$itemNum}: Cantidad debe ser mayor a 0";
                            }
                        }
                    }
                }
            }
        }

        return $errors;
    }

    /**
     * Transformar datos parseados a formato estándar del sistema
     */
    public function transform(array $data): array
    {
        $transformed = [];

        foreach ($data as $bl) {
            $transformedBl = [
                'bill_number' => $bl['numero_bl'],
                'booking_reference' => $bl['cod_booking'],
                'internal_reference' => $bl['cod_programacion'],
                'vessel_name' => $bl['buque'],
                'voyage_number' => $bl['viaje'],
                'vessel_flag' => $this->mapFlag($bl['bandera'] ?? ''),
                'freight_terms' => $bl['condicion_flete'] ?? 'PREPAID',
                'loading_port' => $bl['puerto_carga'],
                'discharge_port' => $bl['puerto_descarga'],
                'final_destination' => $bl['destino_final'],
                'shipper' => [
                    'name' => $bl['cargador_nombre'],
                    'type' => 'shipper'
                ],
                'consignee' => [
                    'name' => $bl['consignatario_nombre'],
                    'type' => 'consignee'
                ],
                'notify_party' => [
                    'name' => $bl['notificatario1_nombre'],
                    'type' => 'notify'
                ],
                'containers' => []
            ];

            // Transformar contenedores
            foreach ($bl['containers'] as $container) {
                $transformedContainer = [
                    'container_number' => $container['cod_contenedor'],
                    'container_type' => $container['tipo_contenedor'],
                    'size_feet' => intval($container['medida'] ?? 20),
                    'tare_weight' => floatval($container['tara'] ?? 0),
                    'temperature' => $container['temperatura'],
                    'seals' => [
                        'line_seals' => $container['precintos_linea'],
                        'customs_seals' => $container['precintos_aduana'],
                        'senacsa_seals' => $container['precintos_senacsa']
                    ],
                    'cargo_items' => []
                ];

                // Transformar items de carga
                foreach ($container['items'] as $item) {
                    $transformedItem = [
                        'line_number' => intval($item['item'] ?? 1),
                        'description' => $item['mercaderia'],
                        'packaging_type' => $item['embalaje'],
                        'package_quantity' => intval($item['cantidad'] ?? 0),
                        'gross_weight_kg' => floatval($item['peso_bruto'] ?? 0),
                        'net_weight_kg' => floatval($item['peso_neto'] ?? 0),
                        'volume_m3' => floatval($item['cubitaje'] ?? 0),
                        'commodity_code' => $item['partida_arancelaria'],
                        'imo_code' => $item['imo'],
                        'dangerous_cargo' => !empty($item['imo'])
                    ];

                    $transformedContainer['cargo_items'][] = $transformedItem;
                }

                $transformedBl['containers'][] = $transformedContainer;
            }

            $transformed[] = $transformedBl;
        }

        return $transformed;
    }

    /**
     * Obtener información del formato soportado
     */
    public function getFormatInfo(): array
    {
        return [
            'name' => 'Navsur Text Format',
            'description' => 'Formato de texto jerárquico utilizado por Navsur con marcadores **BL**, **CONTENEDORES**, **MERCADERIAS**',
            'extensions' => ['txt'],
            'mime_types' => ['text/plain'],
            'characteristics' => [
                'Marcadores de sección con doble asterisco',
                'Campos con formato CAMPO: /*valor*/',
                'Estructura jerárquica BL > Contenedores > Mercaderías',
                'Múltiples BLs por archivo',
                'Línea naviera principal: MSC'
            ],
            'sample_patterns' => [
                '**BL**',
                'NUMEROBL: /*valor*/',
                '**CONTENEDORES**',
                '**MERCADERIAS**',
                '**FIN BL**'
            ],
            'required_fields' => [
                'NUMEROBL',
                'BUQUE',
                'VIAJE',
                'CODPUERTODECARGA',
                'CODPUERTODEDESCARGA',
                'CARGADORNOMBRE',
                'CONSIGNATARIONOMBRE'
            ],
            'optional_fields' => [
                'CODBOOKING',
                'CODPROGRAMACION',
                'DESTINOFINAL',
                'NOTIFICATARIO1NOMBRE',
                'TEMPERATURA',
                'PRECINTOSLINEA',
                'PRECINTOSADUANA',
                'PRECINTOSENACSA'
            ]
        ];
    }


    /**
     * Obtener configuración por defecto del parser
     */
    public function getDefaultConfig(): array
    {
        return [
            'encoding' => 'UTF-8',
            'line_ending' => 'auto',
            'skip_empty_lines' => true,
            'trim_whitespace' => true,
            'case_sensitive' => false,
            'validate_containers' => true,
            'validate_cargo_items' => true,
            'create_missing_clients' => true,
            'create_missing_ports' => true,
            'create_missing_container_types' => true,
            'default_tare_weight' => 2200,
            'default_max_gross_weight' => 30000,
            'default_country_mapping' => [
                'PARAGUAYA' => 'PY',
                'ARGENTINA' => 'AR',
                'BRASIL' => 'BR'
            ],
            'packaging_type_mapping' => [
                'BAGS' => 1,
                'CARTONS' => 2,
                'PALLETS' => 3,
                'PALLET(S)' => 3,
                'BARRELS' => 4,
                'BARREL(S)' => 4,
                'BOXES' => 5
            ]
        ];
    }

    /**
 * ✅ VALIDAR descripción real del archivo
 */
protected function validateCargoDescription(array $data): string
{
    // ✅ Buscar descripción en múltiples ubicaciones posibles
    
    // 1. En items de contenedores
    if (!empty($data['containers'])) {
        foreach ($data['containers'] as $container) {
            if (!empty($container['items'])) {
                foreach ($container['items'] as $item) {
                    if (!empty($item['mercaderia'])) {
                        return trim($item['mercaderia']);
                    }
                }
            }
        }
    }
    
    // 2. En el título del BL
    if (!empty($data['titulo'])) {
        return trim($data['titulo']);
    }
    
    // 3. En buque + viaje como descripción básica
    if (!empty($data['buque']) && !empty($data['viaje'])) {
        return "Mercadería transportada en {$data['buque']} viaje {$data['viaje']}";
    }
    
    // 4. Última opción: descripción básica
    Log::warning('⚠️ NAVSUR.TXT sin descripción específica - usando descripción básica');
    return 'Mercadería general según manifiesto NAVSUR';
}

protected function validatePackagingTypeId(array $data): int
{
    // ✅ NAVSUR tiene EMBALAJE en items - mapear a packaging_type_id
    if (!empty($data['containers']) && !empty($data['containers'][0]['items'])) {
        $firstItem = $data['containers'][0]['items'][0];
        if (!empty($firstItem['embalaje'])) {
            return $this->mapEmbalajeToPackagingType($firstItem['embalaje']);
        }
    }
    
    Log::warning('⚠️ NAVSUR.TXT no contiene embalaje específico - usando tipo general');
    return 1; // Bags/Bultos como último recurso
}

/**
 * ✅ Mapear embalaje NAVSUR a packaging_type_id
 */
protected function mapEmbalajeToPackagingType(string $embalaje): int
{
    $embalaje = strtoupper(trim($embalaje));
    
    // Mapear tipos de embalaje comunes
    if (str_contains($embalaje, 'BAGS') || str_contains($embalaje, 'BOLSAS')) {
        return 1; // Bags
    }
    if (str_contains($embalaje, 'CARTONS') || str_contains($embalaje, 'CAJAS')) {
        return 2; // Cartons
    }
    if (str_contains($embalaje, 'PALLETS') || str_contains($embalaje, 'PALETAS')) {
        return 3; // Pallets
    }
    if (str_contains($embalaje, 'BARRELS') || str_contains($embalaje, 'BARRILES')) {
        return 4; // Barrels
    }
    if (str_contains($embalaje, 'BOXES') || str_contains($embalaje, 'CONTENEDORES')) {
        return 5; // Boxes
    }
    
    Log::warning("Tipo de embalaje no reconocido: {$embalaje} - usando Bags por defecto");
    return 1; // Default: Bags
}

protected function validateTareWeight(array $data): float
{
    if (!empty($data['tara']) && $data['tara'] > 0) {
        return floatval($data['tara']);
    }
    
    Log::warning('⚠️ NAVSUR.TXT no contiene tara válida - usando peso por defecto');
    return 2200.0; // Peso tara estándar contenedor 20'
}

protected function validateMaxGrossWeight(array $data): float
{
    if (!empty($data['peso_maximo']) && $data['peso_maximo'] > 0) {
        return floatval($data['peso_maximo']);
    }
    
    Log::warning('⚠️ NAVSUR.TXT no contiene peso_maximo - usando peso por defecto');
    return 30000.0; // Peso máximo estándar contenedor
}

}