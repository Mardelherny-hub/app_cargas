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
                
                // Buscar marcadores específicos de Navsur
                if (strpos($line, '**BL**') !== false ||
                    strpos($line, 'NUMEROBL:') !== false ||
                    strpos($line, '/*') !== false && strpos($line, '*/') !== false) {
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
        try {
            Log::info('Starting Navsur TXT parse', ['file' => $filePath]);

            $content = file_get_contents($filePath);
            if (!$content) {
                throw new Exception('No se pudo leer el archivo');
            }

            // Parsear estructura del archivo
            $bls = $this->parseAllBLs($content);
            
            if (empty($bls)) {
                return ManifestParseResult::failure(['No se encontraron BLs en el archivo']);
            }

            // Procesar en transacción
            $result = DB::transaction(function () use ($bls) {
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
                                    }
                                }
                            }
                        }
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
     * Extraer secciones de contenedores
     */
    protected function extractContainerSections(string $section): array
    {
        $containers = [];
        
        // Buscar patrones de contenedores
        $pattern = '/\*\*CONTENEDORES\*\*(.*?)\*\*FIN CONTENEDORES\*\*/s';
        preg_match_all($pattern, $section, $matches);
        
        if (!empty($matches[1])) {
            foreach ($matches[1] as $containerBlock) {
                // Encontrar las mercaderías asociadas
                $nextContainerPos = strpos($section, '**CONTENEDORES**', strpos($section, $containerBlock));
                $mercaderiasPattern = '/\*\*MERCADERIAS\*\*(.*?)\*\*FIN MERCADERIAS\*\*/s';
                
                // Buscar mercaderías después de este contenedor
                $searchStart = strpos($section, $containerBlock) + strlen($containerBlock);
                $searchEnd = $nextContainerPos !== false ? $nextContainerPos : strlen($section);
                $searchArea = substr($section, $searchStart, $searchEnd - $searchStart);
                
                preg_match($mercaderiasPattern, $searchArea, $mercaderiasMatch);
                
                $containers[] = [
                    'container' => $containerBlock,
                    'items' => isset($mercaderiasMatch[1]) ? $mercaderiasMatch[1] : ''
                ];
            }
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
        
        // Dividir por ITEM:
        $lines = explode("\n", $section);
        $currentItem = null;
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            if (strpos($line, 'ITEM:') !== false) {
                if ($currentItem !== null && !empty($currentItem['mercaderia'])) {
                    $items[] = $currentItem;
                }
                $currentItem = [
                    'item' => $this->extractValue($line, 'ITEM:'),
                    'titulo' => '',
                    'embalaje' => '',
                    'mercaderia' => '',
                    'cantidad' => 0,
                    'peso_neto' => 0,
                    'peso_bruto' => 0,
                    'cubitaje' => 0,
                    'imo' => '',
                    'partida_arancelaria' => ''
                ];
            } elseif ($currentItem !== null) {
                if (strpos($line, 'TITULO:') !== false) {
                    $currentItem['titulo'] = $this->extractValue($line, 'TITULO:');
                } elseif (strpos($line, 'EMBALAJE:') !== false) {
                    $currentItem['embalaje'] = $this->extractValue($line, 'EMBALAJE:');
                } elseif (strpos($line, 'MERCADERIA:') !== false) {
                    $currentItem['mercaderia'] = $this->extractValue($line, 'MERCADERIA:');
                } elseif (strpos($line, 'CANTIDAD:') !== false) {
                    $currentItem['cantidad'] = intval($this->extractValue($line, 'CANTIDAD:'));
                } elseif (strpos($line, 'PESONETO:') !== false) {
                    $currentItem['peso_neto'] = floatval($this->extractValue($line, 'PESONETO:'));
                } elseif (strpos($line, 'PESOBRUTO:') !== false) {
                    $currentItem['peso_bruto'] = floatval($this->extractValue($line, 'PESOBRUTO:'));
                } elseif (strpos($line, 'CUBITAJE:') !== false) {
                    $currentItem['cubitaje'] = floatval($this->extractValue($line, 'CUBITAJE:'));
                } elseif (strpos($line, 'IMO:') !== false) {
                    $currentItem['imo'] = $this->extractValue($line, 'IMO:');
                } elseif (strpos($line, 'PARTIDAARANCELARIA:') !== false) {
                    $currentItem['partida_arancelaria'] = $this->extractValue($line, 'PARTIDAARANCELARIA:');
                }
            }
        }
        
        // Agregar último item
        if ($currentItem !== null && !empty($currentItem['mercaderia'])) {
            $items[] = $currentItem;
        }
        
        return $items;
    }

    /**
     * Extraer valor entre marcadores de comentario
     */
    protected function extractValue(string $text, string $field): ?string
    {
        $pattern = '/' . preg_quote($field, '/') . '\s*\/\*(.*?)\*\//';
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
     * Buscar o crear voyage
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

        $voyage = Voyage::where('voyage_number', $data['voyage_number'])
            ->where('company_id', $companyId)
            ->first();

        if ($voyage) {
            return $voyage;
        }

        // Buscar o crear vessel
        $vessel = Vessel::firstOrCreate(
            ['name' => $data['vessel_name']],
            [
                'vessel_type' => 'barge',
                'flag' => $this->mapFlag($data['flag']),
                'company_id' => $companyId
            ]
        );

        // Buscar puertos
        $originPort = $this->findOrCreatePort($data['pol']);
        $destPort = $this->findOrCreatePort($data['pod']);

        // Crear voyage
        $voyage = Voyage::create([
            'voyage_number' => $data['voyage_number'],
            'company_id' => $companyId, 
            'lead_vessel_id' => $vessel->id,
            'origin_port_id' => $originPort->id,
            'destination_port_id' => $destPort->id,
            'origin_country_id' => $originPort->country_id ?? 2, // PY
            'destination_country_id' => $destPort->country_id ?? 1, // AR
            'departure_date' => now(),
            'estimated_arrival_date' => now()->addDays(3),
            'status' => 'planning',
            'voyage_type' => 'standard',
            'cargo_type' => 'general',
            'is_convoy' => false,
            'vessel_count' => 1
        ]);

        Log::info('Created voyage from Navsur', ['voyage_id' => $voyage->id]);
        return $voyage;
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

        // Crear BL
        $bill = BillOfLading::create([
            'shipment_id' => $shipment->id,
            'bill_number' => $data['numero_bl'],
            'master_bill_number' => $data['cod_booking'] ?? null,
            'internal_reference' => $data['cod_programacion'] ?? null,
            'shipper_id' => $shipper->id,
            'consignee_id' => $consignee->id,
            'notify_party_id' => $notify?->id,
            'loading_port_id' => $loadingPort->id,
            'discharge_port_id' => $dischargePort->id,
            'final_destination_port_id' => $finalPort->id,
            'freight_terms' => $data['condicion_flete'] ?? 'PREPAID',
            'bill_type' => 'straight',
            'status' => 'draft',
            'issue_date' => now(),
            'primary_cargo_type_id' => 1,
            'primary_packaging_type_id' => 1
        ]);

        Log::info('Created BL from Navsur', [
            'bill_id' => $bill->id,
            'bill_number' => $bill->bill_number
        ]);

        return $bill;
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
            'tare_weight_kg' => floatval($data['tara'] ?? 0) ?: 2200,
            'max_gross_weight_kg' => 30000,
            'condition' => 'L', // Loaded
            'operational_status' => 'in_use',
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

        $item = ShipmentItem::create([
            'bill_of_lading_id' => $bill->id,
            'line_number' => intval($data['item'] ?? 1),
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

        // Buscar existente
        $client = Client::where('legal_name', $name)
            ->orWhere('commercial_name', $name)
            ->first();

        if ($client) {
            return $client;
        }

        // Determinar país basado en el nombre
        $countryId = 1; // Argentina por defecto
        if (str_contains(strtoupper($name), 'PARAGUAY')) {
            $countryId = 2; // Paraguay
        }

        // Crear cliente
        $client = Client::create([
            'tax_id' => 'PENDING-' . strtoupper(substr(md5($name), 0, 6)),
            'country_id' => $countryId,
            'document_type_id' => $countryId == 1 ? 1 : 2, // CUIT o RUC
            'legal_name' => $name,
            'commercial_name' => $name,
            'status' => 'active',
            'created_by_company_id' => $companyId
        ]);

        $this->stats['warnings'][] = "Cliente '{$name}' creado con identificación temporal";

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

        // Determinar país basado en código
        $countryId = 1; // Argentina por defecto
        if (str_starts_with($code, 'PY')) {
            $countryId = 2; // Paraguay
        } elseif (str_starts_with($code, 'BR')) {
            $countryId = 3; // Brasil
        }

        $port = Port::create([
            'code' => $code,
            'name' => 'Puerto ' . $code,
            'country_id' => $countryId,
            'port_type' => 'river',
            'active' => true
        ]);

        $this->stats['warnings'][] = "Puerto '{$code}' creado automáticamente";

        return $port;
    }

    /**
     * Buscar o crear tipo de contenedor
     */
    protected function findOrCreateContainerType(string $code, string $size): ContainerType
    {
        $code = strtoupper(trim(str_replace(['/*', '*/'], '', $code)));
        
        $type = ContainerType::where('code', $code)->first();
        
        if ($type) {
            return $type;
        }

        $type = ContainerType::create([
            'code' => $code,
            'name' => "Container {$code}",
            'size_feet' => intval($size),
            'type_category' => $this->detectContainerCategory($code),
            'active' => true
        ]);

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
}