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
use App\Services\Parsers\Concerns\ResolvesClientAddresses;
use App\Services\Parsers\Concerns\ResolvesPorts;
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
    use ResolvesClientAddresses;
    use ResolvesPorts;

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

            $validationErrors = $this->validate($bls);

            if ($validationErrors !== []) {
                throw new \DomainException(
                    implode(' | ', $validationErrors)
                );
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
                    $completionData = [
                        'voyage_id'               => $voyage->id,
                        'created_bills'           => count($allBills),
                        'created_containers'      => count($allContainers),
                        'created_items'           => count($allItems),
                        'processing_time_seconds' => round(microtime(true) - $startTime, 2),
                        'import_statistics'       => $this->stats,
                        'warnings'                => $this->stats['warnings'],
                        'warnings_count'          => count($this->stats['warnings']),
                        'notes'                   => 'Importación Navsur completada',
                    ];

                    if ($this->stats['warnings'] !== []) {
                        $importRecord->markAsCompletedWithWarnings(
                            $completionData
                        );
                    } else {
                        $importRecord->markAsCompleted(
                            $completionData
                        );
                    }
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
            'cargador_domicilio' => $this->extractValue($section, 'CARGADORDOMICILIO:'),
            'consignatario_domicilio' => $this->extractValue($section, 'CONSIGNATARIODOMICILIO:'),
            'notificatario1_domicilio' => $this->extractValue($section, 'NOTIFICATARIO1DOMICILIO:'),
            'containers' => [],
        ];

        foreach ($this->extractContainerSections($section) as $containerSection) {
            $container = $this->parseContainerSection($containerSection);

            if (!empty($container['cod_contenedor'])) {
                $bl['containers'][] = $container;
            }
        }

        $bl['containers'] = $this->normalizeContainerItems(
            $bl['containers'],
            (string) $bl['numero_bl']
        );

        return $bl;
    }


    /**
     * Navsur repite, en BL multikontenedor, el bloque completo de
     * MERCADERIAS después de cada contenedor.
     *
     * Cuando todos los bloques son idénticos y la cantidad de líneas del
     * bloque coincide con la cantidad de contenedores, la relación real es
     * posicional: contenedor 1 -> item 1, contenedor 2 -> item 2, etc.
     */
    protected function normalizeContainerItems(
        array $containers,
        string $blNumber
    ): array {
        $containerCount = count($containers);

        if ($containerCount <= 1) {
            return $containers;
        }

        $sets = array_map(
            fn (array $container) => $container['items'] ?? [],
            $containers
        );

        $first = $sets[0] ?? [];

        $allIdentical = true;

        foreach ($sets as $set) {
            if ($set !== $first) {
                $allIdentical = false;
                break;
            }
        }

        if (!$allIdentical) {
            return $containers;
        }

        if (count($first) !== $containerCount) {
            throw new \DomainException(
                "Navsur BL {$blNumber}: el bloque de mercaderías se repite "
                . "para {$containerCount} contenedores, pero contiene "
                . count($first)
                . " líneas. No se puede determinar una relación inequívoca."
            );
        }

        foreach ($containers as $index => &$container) {
            $container['items'] = [$first[$index]];
        }
        unset($container);

        $this->stats['warnings'][] =
            "BL {$blNumber}: Navsur repite el bloque completo de mercaderías "
            . "por contenedor. Se normalizó a relación 1 contenedor = 1 ítem.";

        return $containers;
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

        $chunks = preg_split('/(?=ITEM:\s*\/\*)/', $section);

        foreach ($chunks as $chunk) {
            if (strpos($chunk, 'ITEM:') === false) {
                continue;
            }

            $mercaderia = $this->extractValue($chunk, 'MERCADERIA:');

            if (empty($mercaderia)) {
                continue;
            }

            $cantidad = $this->extractValue($chunk, 'CANTIDAD:');
            $pesoNeto = $this->extractValue($chunk, 'PESONETO:');
            $pesoBruto = $this->extractValue($chunk, 'PESOBRUTO:');
            $cubitaje = $this->extractValue($chunk, 'CUBITAJE:');

            $items[] = [
                'item' => $this->extractValue($chunk, 'ITEM:'),
                'titulo' => $this->extractValue($chunk, 'TITULO:') ?? '',
                'embalaje' => $this->extractValue($chunk, 'EMBALAJE:') ?? '',
                'mercaderia' => $mercaderia,

                'cantidad' =>
                    $cantidad === null || trim($cantidad) === ''
                        ? null
                        : (int) $cantidad,

                'peso_neto' =>
                    $pesoNeto === null || trim($pesoNeto) === ''
                        ? null
                        : (float) $pesoNeto,

                'peso_bruto' =>
                    $pesoBruto === null || trim($pesoBruto) === ''
                        ? null
                        : (float) $pesoBruto,

                'cubitaje' =>
                    $cubitaje === null || trim($cubitaje) === ''
                        ? null
                        : (float) $cubitaje,

                'imo' => $this->extractValue($chunk, 'IMO:') ?? '',
                'partida_arancelaria' =>
                    $this->extractValue($chunk, 'PARTIDAARANCELARIA:') ?? '',
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
        foreach ([
            'viaje' => 'número de viaje',
            'buque' => 'buque',
            'puerto_carga' => 'puerto de carga',
            'puerto_descarga' => 'puerto de descarga',
        ] as $field => $label) {
            if (empty($bl[$field])) {
                throw new \DomainException(
                    "Navsur no informa {$label}."
                );
            }
        }

        return [
            'voyage_number' => trim((string) $bl['viaje']),
            'vessel_name' => trim((string) $bl['buque']),
            'flag' => !empty($bl['bandera'])
                ? trim((string) $bl['bandera'])
                : null,
            'pol' => trim((string) $bl['puerto_carga']),
            'pod' => trim((string) $bl['puerto_descarga']),
        ];
    }

    /**
     * Buscar o crear voyage - VALORES ENUM CORREGIDOS
     */

    protected function findOrCreateVoyage(array $data): Voyage
    {
        $user = auth()->user();

        if (!$user) {
            throw new \DomainException(
                'Usuario no autenticado para importar Navsur.'
            );
        }

        $companyId = null;

        if (
            $user->userable_type === 'App\Models\Company'
            && $user->userable_id
        ) {
            $companyId = (int) $user->userable_id;
        } elseif (
            $user->userable_type === 'App\Models\Operator'
            && $user->userable
        ) {
            $companyId = (int) $user->userable->company_id;
        }

        if (!$companyId) {
            throw new \DomainException(
                "Usuario {$user->id} no tiene empresa asignada."
            );
        }

        $this->guardVoyageNumberIsFree($data['voyage_number']);

        $originPort = $this->resolvePortStrict($data['pol']);
        $destinationPort = $this->resolvePortStrict($data['pod']);

        if (!$originPort->country_id) {
            throw new \DomainException(
                "El puerto {$data['pol']} no tiene país configurado."
            );
        }

        $flagCountryId = !empty($data['flag'])
            ? $this->mapFlagToCountryId($data['flag'])
            : null;

        $vessel = Vessel::where('company_id', $companyId)
            ->where('name', $data['vessel_name'])
            ->first();

        if (!$vessel) {
            $vessel = Vessel::create([
                'name' => $data['vessel_name'],
                'registration_number' => null,
                'company_id' => $companyId,
                'vessel_type_id' => null,
                'flag_country_id' => $flagCountryId,
                'length_meters' => null,
                'beam_meters' => null,
                'draft_meters' => null,
                'cargo_capacity_tons' => null,
                'container_capacity' => null,
                'operational_status' => 'active',
                'active' => true,
            ]);
        } elseif (
            !$vessel->flag_country_id
            && $flagCountryId
        ) {
            $vessel->update([
                'flag_country_id' => $flagCountryId,
            ]);
        }

        return Voyage::create([
            'voyage_number' => $data['voyage_number'],
            'company_id' => $companyId,
            'lead_vessel_id' => $vessel->id,
            'origin_port_id' => $originPort->id,
            'destination_port_id' => $destinationPort->id,
            'origin_country_id' => $originPort->country_id,
            'destination_country_id' =>
                $destinationPort->country_id ?: null,

            'voyage_type' => 'single_vessel',
            'cargo_type' => 'export',
            'status' => 'planning',

            // Navsur no informa fechas operativas del viaje.
            'departure_date' => null,
            'estimated_arrival_date' => null,

            // Navsur tampoco informa capacidades del buque.
            'total_cargo_capacity_tons' => 0,
            'total_container_capacity' => 0,
            'total_cargo_weight_loaded' => 0,
            'total_containers_loaded' => 0,
            'capacity_utilization_percentage' => 0,
        ]);
    }

    /**
     * NUEVO MÉTODO: Mapear bandera a country_id
     */

    protected function mapFlagToCountryId(string $flag): int
    {
        $alpha2 = $this->mapFlag($flag);

        $id = DB::table('countries')
            ->where('alpha2_code', $alpha2)
            ->value('id');

        if (!$id) {
            throw new \DomainException(
                "No existe el país {$alpha2} en el catálogo."
            );
        }

        return (int) $id;
    }

    
    /**
     * Buscar o crear shipment
     */

    protected function findOrCreateShipment(
        Voyage $voyage,
        array $data
    ): Shipment {
        $existing = Shipment::where('voyage_id', $voyage->id)
            ->where('sequence_in_voyage', 1)
            ->first();

        if ($existing) {
            return $existing;
        }

        return Shipment::create([
            'voyage_id' => $voyage->id,
            'vessel_id' => $voyage->lead_vessel_id,
            'shipment_number' =>
                'SHP-' . $voyage->voyage_number . '-001',
            'sequence_in_voyage' => 1,
            'vessel_role' => 'single',
            'is_lead_vessel' => true,

            // No informadas por Navsur.
            'cargo_capacity_tons' => null,
            'container_capacity' => 0,

            'status' => 'planning',
        ]);
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


    protected function requireActiveCatalogId(
        string $table,
        string $code
    ): int {
        $id = DB::table($table)
            ->where('code', $code)
            ->where('active', true)
            ->value('id');

        if (!$id) {
            throw new \DomainException(
                "No existe {$table}.code={$code} activo."
            );
        }

        return (int) $id;
    }

    protected function flattenBillItems(array $data): array
    {
        $items = [];

        foreach (($data['containers'] ?? []) as $container) {
            foreach (($container['items'] ?? []) as $item) {
                $items[] = $item;
            }
        }

        return $items;
    }

    protected function normalizeCommodityCode(
        ?string $value
    ): ?string {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $value);

        if (strlen($digits) === 6) {
            return $digits . '00';
        }

        if (strlen($digits) >= 8) {
            return substr($digits, 0, 8);
        }

        return null;
    }

    protected function resolveItemCommodityCode(
        array $data
    ): ?string {
        $structured = $this->normalizeCommodityCode(
            $data['partida_arancelaria'] ?? null
        );

        if ($structured) {
            return $structured;
        }

        $description = (string) ($data['mercaderia'] ?? '');

        if (
            preg_match(
                '/(?:HS\s*CODE(?:\/NCM)?|NCM)\s*[:\-]?\s*([0-9.]+)/i',
                $description,
                $matches
            )
        ) {
            return $this->normalizeCommodityCode($matches[1]);
        }

        return null;
    }

    protected function optionalFloat(mixed $value): ?float
    {
        if (
            $value === null
            || (is_string($value) && trim($value) === '')
        ) {
            return null;
        }

        return (float) $value;
    }

    protected function inferClientCountryId(
        string $name,
        ?int $fallbackCountryId
    ): int {
        $upper = strtoupper(
            iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name) ?: $name
        );

        $alpha2 = null;

        if (str_contains($upper, 'PARAGUAY')) {
            $alpha2 = 'PY';
        } elseif (
            str_contains($upper, 'ARGENTIN')
            || str_contains($upper, 'BUENOS AIRES')
        ) {
            $alpha2 = 'AR';
        } elseif (
            str_contains($upper, 'BRASIL')
            || str_contains($upper, 'BRAZIL')
        ) {
            $alpha2 = 'BR';
        }

        if ($alpha2) {
            $id = DB::table('countries')
                ->where('alpha2_code', $alpha2)
                ->value('id');

            if ($id) {
                return (int) $id;
            }
        }

        if ($fallbackCountryId) {
            return $fallbackCountryId;
        }

        throw new \DomainException(
            "No se pudo determinar el país del cliente '{$name}'."
        );
    }

    protected function resolveClientDocumentTypeId(
        ?string $taxId,
        int $countryId
    ): ?int {
        if (!$taxId) {
            return null;
        }

        $alpha2 = DB::table('countries')
            ->where('id', $countryId)
            ->value('alpha2_code');

        $code = match ($alpha2) {
            'AR' => 'CUIT',
            'PY' => 'RUC',
            'BR' => 'CNPJ',
            default => null,
        };

        if (!$code) {
            return null;
        }

        $id = DB::table('document_types')
            ->where('country_id', $countryId)
            ->where('code', $code)
            ->where('active', true)
            ->value('id');

        return $id ? (int) $id : null;
    }

    protected function appendBillSeals(
        BillOfLading $bill,
        array $data
    ): void {
        $new = $this->extractSeals($data);

        if (!$new) {
            return;
        }

        $parts = [];

        foreach ([
            (string) ($bill->bl_seals_numbers ?? ''),
            $new,
        ] as $value) {
            foreach (explode(',', $value) as $seal) {
                $seal = trim($seal);

                if ($seal !== '') {
                    $parts[] = $seal;
                }
            }
        }

        $parts = array_values(array_unique($parts));

        $bill->update([
            'bl_seals_numbers' => implode(', ', $parts),
        ]);
    }

    /**
     * Crear BillOfLading
     */

    protected function createBillOfLading(
        Shipment $shipment,
        array $data
    ): BillOfLading {
        $loadingPort = $this->resolvePortStrict(
            $data['puerto_carga']
        );

        $dischargePort = $this->resolvePortStrict(
            $data['puerto_descarga']
        );

        $finalPort = !empty($data['destino_final'])
            ? (
                $this->resolvePortOrNull($data['destino_final'])
                ?? $dischargePort
            )
            : $dischargePort;

        $shipperCountryId = $this->inferClientCountryId(
            (string) $data['cargador_nombre'],
            $loadingPort->country_id
        );

        $consigneeCountryId = $this->inferClientCountryId(
            (string) $data['consignatario_nombre'],
            $dischargePort->country_id
        );

        $shipper = $this->findOrCreateClient(
            $data['cargador_nombre'],
            'shipper',
            $shipperCountryId
        );

        $consignee = $this->findOrCreateClient(
            $data['consignatario_nombre'],
            'consignee',
            $consigneeCountryId
        );

        $notify = !empty($data['notificatario1_nombre'])
            ? $this->findOrCreateClient(
                $data['notificatario1_nombre'],
                'notify',
                $this->inferClientCountryId(
                    (string) $data['notificatario1_nombre'],
                    $dischargePort->country_id
                )
            )
            : null;

        $items = $this->flattenBillItems($data);

        if ($items === []) {
            throw new \DomainException(
                "Navsur BL {$data['numero_bl']}: no contiene mercadería."
            );
        }

        $totalPackages = 0;
        $grossWeight = 0.0;
        $netWeight = 0.0;
        $volume = 0.0;

        $hasNet = false;
        $hasVolume = false;

        $descriptions = [];
        $commodityCodes = [];

        foreach ($items as $item) {
            if (($item['cantidad'] ?? null) === null) {
                throw new \DomainException(
                    "Navsur BL {$data['numero_bl']}: "
                    . "hay un ítem sin cantidad informada."
                );
            }

            if (($item['peso_bruto'] ?? null) === null) {
                throw new \DomainException(
                    "Navsur BL {$data['numero_bl']}: "
                    . "hay un ítem sin peso bruto informado."
                );
            }

            $totalPackages += (int) $item['cantidad'];
            $grossWeight += (float) $item['peso_bruto'];

            if (($item['peso_neto'] ?? null) !== null) {
                $hasNet = true;
                $netWeight += (float) $item['peso_neto'];
            }

            if (($item['cubitaje'] ?? null) !== null) {
                $hasVolume = true;
                $volume += (float) $item['cubitaje'];
            }

            if (!empty($item['mercaderia'])) {
                $descriptions[] = trim(
                    (string) $item['mercaderia']
                );
            }

            $commodityCode =
                $this->resolveItemCommodityCode($item);

            if ($commodityCode) {
                $commodityCodes[] = $commodityCode;
            }
        }

        $descriptions = array_values(
            array_unique($descriptions)
        );

        $commodityCodes = array_values(
            array_unique($commodityCodes)
        );

        $importDate = today();

        $bill = BillOfLading::create([
            'shipment_id' => $shipment->id,
            'bill_number' => $data['numero_bl'],
            'master_bill_number' =>
                $data['cod_booking'] ?? null,
            'internal_reference' =>
                $data['cod_programacion'] ?? null,

            // Regla operativa: Navsur no trae fechas.
            'bill_date' => $importDate,
            'loading_date' => $importDate,

            'shipper_id' => $shipper->id,
            'consignee_id' => $consignee->id,
            'notify_party_id' => $notify?->id,

            'loading_port_id' => $loadingPort->id,
            'discharge_port_id' => $dischargePort->id,
            'final_destination_port_id' => $finalPort->id,

            'freight_terms' => $this->mapFreightTerms(
                $data['condicion_flete'] ?? ''
            ),

            'status' => 'draft',

            // El manifiesto es contenedorizado.
            'primary_cargo_type_id' =>
                $this->requireActiveCatalogId(
                    'cargo_types',
                    'CON001'
                ),

            'primary_packaging_type_id' =>
                $this->requireActiveCatalogId(
                    'packaging_types',
                    'T'
                ),

            'gross_weight_kg' => $grossWeight,
            'net_weight_kg' => $hasNet ? $netWeight : null,
            'total_packages' => $totalPackages,
            'volume_m3' => $hasVolume ? $volume : null,

            'cargo_description' => implode(
                "\n",
                $descriptions
            ),

            'commodity_code' =>
                $commodityCodes[0] ?? null,

            'commodity_codes' =>
                $commodityCodes ?: null,

            'special_instructions' => null,
            'internal_notes' =>
                'Importado desde archivo Navsur',
        ]);

        foreach ([
            [
                'client' => $shipper,
                'addr' => $data['cargador_domicilio'] ?? null,
                'role' => 'shipper',
            ],
            [
                'client' => $consignee,
                'addr' => $data['consignatario_domicilio'] ?? null,
                'role' => 'consignee',
            ],
            [
                'client' => $notify,
                'addr' => $data['notificatario1_domicilio'] ?? null,
                'role' => 'notify_party',
            ],
        ] as $party) {
            if (!$party['client']) {
                continue;
            }

            $this->persistClientAddress(
                $party['client'],
                $party['addr']
            );

            if (
                $contact = $this->resolveSpecificAddress(
                    $party['client'],
                    $party['addr'],
                    $party['role']
                )
            ) {
                $bill->specificContacts()->create($contact);
            }
        }

        return $bill;
    }


    protected function validateCargoTypeId(array $data): int
    {
        return $this->requireActiveCatalogId(
            'cargo_types',
            'CON001'
        );
    }

    /**
     * NUEVO MÉTODO: Mapear términos de flete
     */

    protected function mapFreightTerms(string $terms): string
    {
        $terms = strtoupper(
            trim(str_replace(['/*', '*/'], '', $terms))
        );

        if (
            str_contains($terms, 'PREPAID')
            || str_contains($terms, 'PREPAGADO')
        ) {
            return 'prepaid';
        }

        if (
            str_contains($terms, 'COLLECT')
            || str_contains($terms, 'COBRAR')
        ) {
            return 'collect';
        }

        if (
            str_contains($terms, 'THIRD')
            || str_contains($terms, 'TERCERO')
        ) {
            return 'third_party';
        }

        throw new \DomainException(
            "Condición de flete Navsur no reconocida: '{$terms}'."
        );
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

protected function createContainer(
    BillOfLading $bill,
    array $data
): ?Container {
    if (empty($data['cod_contenedor'])) {
        return null;
    }

    $containerType = $this->findOrCreateContainerType(
        (string) ($data['tipo_contenedor'] ?? ''),
        (string) ($data['medida'] ?? '')
    );

    $tare = $this->validateTareWeight($data);
    $maxGross = $this->validateMaxGrossWeight($data);

    if ($tare !== null && $tare == 0.0) {
        $this->stats['warnings'][] =
            "Contenedor {$data['cod_contenedor']}: Navsur informa "
            . "tara en 0. Se importó en 0 y debe completarse "
            . "manualmente si corresponde.";
    }

    $lineSeal = trim(
        (string) ($data['precintos_linea'] ?? '')
    );

    $customsSeal = trim(
        (string) ($data['precintos_aduana'] ?? '')
    );

    $senacsaSeal = trim(
        (string) ($data['precintos_senacsa'] ?? '')
    );

    $additionalSeals = [];

    if ($senacsaSeal !== '') {
        $additionalSeals[] = [
            'issuer' => 'SENACSA',
            'seal_number' => $senacsaSeal,
        ];
    }

    $sourceType = strtoupper(
        trim((string) ($data['tipo_contenedor'] ?? ''))
    );

    $isReefer =
        str_contains($sourceType, 'RH')
        || str_contains($sourceType, 'RF');

    $attributes = [
        'container_type_id' => $containerType->id,
        'tare_weight_kg' => $tare,
        'max_gross_weight_kg' => $maxGross,

        // Navsur no informa estos pesos a nivel contenedor.
        'current_gross_weight_kg' => null,
        'cargo_weight_kg' => null,

        'condition' => 'L',
        'operational_status' => 'loaded',
        'current_port_id' => $bill->loading_port_id,

        // PRECINTOSLINEA = precinto de la línea/carrier.
        'carrier_seal' =>
            $lineSeal !== '' ? $lineSeal : null,

        'customs_seal' =>
            $customsSeal !== '' ? $customsSeal : null,

        'additional_seals' =>
            $additionalSeals !== []
                ? json_encode(
                    $additionalSeals,
                    JSON_UNESCAPED_UNICODE
                )
                : null,

        'temperature_controlled' => $isReefer,
        'requires_power' => $isReefer,

        'set_temperature' =>
            $this->optionalFloat(
                $data['temperatura'] ?? null
            ),

        'active' => true,
    ];

    $container = Container::where(
        'container_number',
        $data['cod_contenedor']
    )->first();

    if ($container) {
        $container->update($attributes);
    } else {
        $container = Container::create(
            array_merge(
                [
                    'container_number' =>
                        $data['cod_contenedor'],
                    'full_container_number' =>
                        $data['cod_contenedor'],
                ],
                $attributes
            )
        );
    }

    $this->appendBillSeals($bill, $data);

    return $container;
}

    /**
     * Crear ShipmentItem
     */

    protected function createShipmentItem(
        BillOfLading $bill,
        array $data
    ): ?ShipmentItem {
        if (empty($data['mercaderia'])) {
            return null;
        }

        if (($data['cantidad'] ?? null) === null) {
            throw new \DomainException(
                "Navsur BL {$bill->bill_number}: "
                . "ítem sin cantidad informada."
            );
        }

        if (($data['peso_bruto'] ?? null) === null) {
            throw new \DomainException(
                "Navsur BL {$bill->bill_number}: "
                . "ítem sin peso bruto informado."
            );
        }

        $lineNumber = ShipmentItem::where(
            'bill_of_lading_id',
            $bill->id
        )->count() + 1;

        foreach ([
            'cantidad' => 'cantidad de bultos',
            'peso_bruto' => 'peso bruto',
            'peso_neto' => 'peso neto',
        ] as $field => $label) {
            if (
                array_key_exists($field, $data)
                && $data[$field] !== null
                && (float) $data[$field] == 0.0
            ) {
                $this->stats['warnings'][] =
                    "BL {$bill->bill_number}, ítem {$lineNumber}: "
                    . "Navsur informa {$label} en 0. "
                    . "Se importó en 0 y debe completarse "
                    . "manualmente si corresponde.";
            }
        }

        $commodityCode =
            $this->resolveItemCommodityCode($data);

        $packagingDescription = trim(
            (string) ($data['embalaje'] ?? '')
        );

        return ShipmentItem::create([
            'bill_of_lading_id' => $bill->id,
            'line_number' => $lineNumber,

            'cargo_type_id' =>
                $this->requireActiveCatalogId(
                    'cargo_types',
                    'CON001'
                ),

            'packaging_type_id' =>
                $this->requireActiveCatalogId(
                    'packaging_types',
                    'T'
                ),

            'package_quantity' =>
                (int) $data['cantidad'],

            'gross_weight_kg' =>
                (float) $data['peso_bruto'],

            'net_weight_kg' =>
                $data['peso_neto'] !== null
                    ? (float) $data['peso_neto']
                    : null,

            'volume_m3' =>
                $data['cubitaje'] !== null
                    ? (float) $data['cubitaje']
                    : null,

            'item_description' =>
                (string) $data['mercaderia'],

            // El catálogo sólo clasifica retorno/contenedor.
            // El embalaje Navsur exacto se conserva aparte.
            'package_type_description' =>
                $packagingDescription !== ''
                    ? $packagingDescription
                    : null,

            'packaging_code' =>
                $packagingDescription !== ''
                    ? $packagingDescription
                    : null,

            // NCM / Posición Arancelaria: mismo dato de negocio.
            // Se conservan ambos campos internos por compatibilidad del modelo.
            'commodity_code' => $commodityCode,
            'tariff_position' => $commodityCode,

            'is_dangerous_goods' =>
                !empty($data['imo']),

            'imdg_class' =>
                !empty($data['imo'])
                    ? trim((string) $data['imo'])
                    : null,

            'unit_of_measure' => 'PCS',
            'status' => 'draft',
            'created_by_user_id' => auth()->id(),
        ]);
    }

    /**
     * Buscar o crear cliente
     */

    protected function findOrCreateClient(
        ?string $name,
        string $type,
        ?int $countryId = null
    ): ?Client {
        if ($name === null || trim($name) === '') {
            return null;
        }

        $name = trim(
            str_replace(['/*', '*/'], '', $name)
        );

        if ($name === '') {
            return null;
        }

        $countryId = $this->inferClientCountryId(
            $name,
            $countryId
        );

        $taxId = $this->resolveTaxId(null, $name);

        if ($taxId) {
            $existing = Client::where(
                'tax_id',
                $taxId
            )->first();

            if ($existing) {
                return $existing;
            }
        }

        $existing = Client::where(
                'country_id',
                $countryId
            )
            ->where(function ($query) use ($name) {
                $query
                    ->where('legal_name', $name)
                    ->orWhere('commercial_name', $name);
            })
            ->first();

        if ($existing) {
            return $existing;
        }

        $user = auth()->user();

        if (!$user) {
            throw new \DomainException(
                'Usuario no autenticado.'
            );
        }

        $companyId = null;

        if (
            $user->userable_type === 'App\Models\Company'
            && $user->userable_id
        ) {
            $companyId = (int) $user->userable_id;
        } elseif (
            $user->userable_type === 'App\Models\Operator'
            && $user->userable
        ) {
            $companyId =
                (int) $user->userable->company_id;
        }

        if (!$companyId) {
            throw new \DomainException(
                "No se puede crear cliente '{$name}': "
                . "el usuario no tiene empresa asignada."
            );
        }

        $client = Client::create([
            'tax_id' => $taxId,
            'country_id' => $countryId,
            'document_type_id' =>
                $this->resolveClientDocumentTypeId(
                    $taxId,
                    $countryId
                ),
            'legal_name' => $name,
            'commercial_name' => $name,
            'status' => 'active',
            'created_by_company_id' => $companyId,

            // Navsur no declara que el cliente esté verificado.
            'verified_at' => null,
        ]);

        if (!$taxId) {
            $this->stats['warnings'][] =
                "Cliente '{$name}' creado sin identificación "
                . "tributaria informada por Navsur.";
        }

        return $client;
    }
    /**
     * Buscar o crear puerto
     */

    protected function findOrCreatePort(string $code): Port
    {
        return $this->resolvePortStrict($code);
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

protected function findOrCreateContainerType(
    string $code,
    string $size
): ContainerType {
    $code = strtoupper(
        trim(str_replace(['/*', '*/'], '', $code))
    );

    if ($code === '') {
        throw new \DomainException(
            'Navsur no informa tipo de contenedor.'
        );
    }

    // Únicos aliases semánticos inequívocos.
    $aliases = [
        '20DV' => '20GP',
        '40DV' => '40GP',
    ];

    $resolvedCode = $aliases[$code] ?? $code;

    $type = ContainerType::where(
            'code',
            $resolvedCode
        )
        ->where('active', true)
        ->first();

    if (!$type) {
        throw new \DomainException(
            "Tipo de contenedor Navsur '{$code}' "
            . "no existe en el catálogo. "
            . "No se aplicó ningún tipo por defecto."
        );
    }

    return $type;
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

    protected function mapPackagingType(
        string $packaging
    ): int {
        return $this->requireActiveCatalogId(
            'packaging_types',
            'T'
        );
    }

    /**
     * Mapear bandera a código ISO
     */

    protected function mapFlag(string $flag): string
    {
        $flag = strtoupper(
            trim(str_replace(['/*', '*/'], '', $flag))
        );

        if (
            $flag === 'PY'
            || str_contains($flag, 'PARAGUAY')
        ) {
            return 'PY';
        }

        if (
            $flag === 'AR'
            || str_contains($flag, 'ARGENTIN')
        ) {
            return 'AR';
        }

        if (
            $flag === 'BR'
            || str_contains($flag, 'BRASIL')
            || str_contains($flag, 'BRAZIL')
        ) {
            return 'BR';
        }

        throw new \DomainException(
            "Bandera Navsur no reconocida: '{$flag}'."
        );
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

        if ($data === []) {
            return [
                'No se encontraron datos válidos en el archivo',
            ];
        }

        foreach ($data as $index => $bl) {
            $blIndex = $index + 1;

            foreach ([
                'numero_bl' => 'Número de BL',
                'buque' => 'Nombre del buque',
                'viaje' => 'Número de viaje',
                'puerto_carga' => 'Puerto de carga',
                'puerto_descarga' => 'Puerto de descarga',
                'cargador_nombre' => 'Nombre del cargador',
                'consignatario_nombre' =>
                    'Nombre del consignatario',
            ] as $field => $label) {
                if (empty($bl[$field])) {
                    $errors[] =
                        "BL #{$blIndex}: {$label} es obligatorio";
                }
            }

            if (
                empty($bl['containers'])
                || !is_array($bl['containers'])
            ) {
                $errors[] =
                    "BL #{$blIndex}: debe tener al menos "
                    . "un contenedor";
                continue;
            }

            foreach (
                $bl['containers']
                as $containerIndex => $container
            ) {
                $contIndex = $containerIndex + 1;

                if (empty($container['cod_contenedor'])) {
                    $errors[] =
                        "BL #{$blIndex}, contenedor "
                        . "#{$contIndex}: código obligatorio";
                }

                if (empty($container['tipo_contenedor'])) {
                    $errors[] =
                        "BL #{$blIndex}, contenedor "
                        . "#{$contIndex}: tipo obligatorio";
                }

                if (
                    empty($container['items'])
                    || !is_array($container['items'])
                ) {
                    $errors[] =
                        "BL #{$blIndex}, contenedor "
                        . "#{$contIndex}: sin mercadería";
                    continue;
                }

                foreach (
                    $container['items']
                    as $itemIndex => $item
                ) {
                    $itemNum = $itemIndex + 1;

                    if (empty($item['mercaderia'])) {
                        $errors[] =
                            "BL #{$blIndex}, contenedor "
                            . "#{$contIndex}, ítem #{$itemNum}: "
                            . "descripción obligatoria";
                    }

                    // Cero explícito se acepta.
                    if (
                        ($item['cantidad'] ?? null) === null
                    ) {
                        $errors[] =
                            "BL #{$blIndex}, contenedor "
                            . "#{$contIndex}, ítem #{$itemNum}: "
                            . "cantidad no informada";
                    }

                    if (
                        ($item['peso_bruto'] ?? null) === null
                    ) {
                        $errors[] =
                            "BL #{$blIndex}, contenedor "
                            . "#{$contIndex}, ítem #{$itemNum}: "
                            . "peso bruto no informado";
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
            'create_missing_ports' => false,
            'create_missing_container_types' => false,
            'default_tare_weight' => null,
            'default_max_gross_weight' => null,
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

protected function validateCargoDescription(
    array $data
): string {
    $descriptions = [];

    foreach ($this->flattenBillItems($data) as $item) {
        if (!empty($item['mercaderia'])) {
            $descriptions[] = trim(
                (string) $item['mercaderia']
            );
        }
    }

    $descriptions = array_values(
        array_unique($descriptions)
    );

    if ($descriptions === []) {
        throw new \DomainException(
            "Navsur BL {$data['numero_bl']}: "
            . "sin descripción de mercadería."
        );
    }

    return implode("\n", $descriptions);
}


protected function validatePackagingTypeId(
    array $data
): int {
    return $this->requireActiveCatalogId(
        'packaging_types',
        'T'
    );
}

/**
 * ✅ Mapear embalaje NAVSUR a packaging_type_id
 */

protected function mapEmbalajeToPackagingType(
    string $embalaje
): int {
    return $this->requireActiveCatalogId(
        'packaging_types',
        'T'
    );
}


protected function validateTareWeight(
    array $data
): ?float {
    return $this->optionalFloat(
        $data['tara'] ?? null
    );
}


protected function validateMaxGrossWeight(
    array $data
): ?float {
    return $this->optionalFloat(
        $data['peso_maximo'] ?? null
    );
}

}