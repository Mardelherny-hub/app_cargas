<?php

namespace App\Services\Parsers;

use App\Contracts\ManifestParserInterface;
use App\ValueObjects\ManifestParseResult;
use App\Models\Voyage;
use App\Models\Shipment;
use App\Models\BillOfLading;
use App\Models\ShipmentItem;
use App\Models\Client;
use App\Models\Port;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Exception;

/**
 * PARSER INTEGRADO PARA KLINE.DAT
 * 
 * Integra el KlineParserService existente con la nueva arquitectura de manifiestos.
 * Mantiene toda la lógica funcional pero adapta al nuevo flujo unificado.
 * 
 * CARACTERÍSTICAS:
 * - Parsea archivos .DAT con formato KLine
 * - Crea Voyages, Shipments, BillOfLading automáticamente
 * - Maneja múltiples B/L por archivo
 * - Detección automática de puertos
 * - Logging detallado para debugging
 */
class KlineDataParser implements ManifestParserInterface
{
    protected array $lines;
    protected array $stats = [
        'processed' => 0,
        'errors' => 0,
        'warnings' => [],
        'created_voyages' => 0,
        'created_shipments' => 0,
        'created_bills' => 0
    ];

    /**
     * Verificar si puede parsear el archivo
     */
    public function canParse(string $filePath): bool
    {
        // Verificar extensión
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        if (!in_array($extension, ['dat', 'txt'])) {
            return false;
        }

        // Verificar contenido (primeras líneas deben tener formato KLine)
        if (!file_exists($filePath)) {
            return false;
        }

        $handle = fopen($filePath, 'r');
        if (!$handle) {
            return false;
        }

        $sampleLines = [];
        for ($i = 0; $i < 10 && !feof($handle); $i++) {
            $line = fgets($handle);
            if ($line !== false) {
                $sampleLines[] = trim($line);
            }
        }
        fclose($handle);

        // Buscar patrones KLine típicos
        foreach ($sampleLines as $line) {
            if (preg_match('/^(BLNOREC|SHPREC|CNEEREC|DESCREC|MARKREC)/', $line)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Parsear archivo KLine.DAT
     */
    public function parse(string $filePath): ManifestParseResult
    {
        try {
            $this->lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            
            Log::info('Starting KLine parse process', [
                'file_path' => $filePath,
                'total_lines' => count($this->lines),
                'sample_lines' => array_slice($this->lines, 0, 5)
            ]);

            $bills = $this->groupByBillOfLading();
            $results = ['voyages' => [], 'shipments' => [], 'bills' => []];
            
            foreach ($bills as $bl) {
                try {
                    DB::transaction(function () use ($bl, &$results) {
                        $result = $this->storeFromParsedData($bl);
                        
                        if ($result['voyage']) $results['voyages'][] = $result['voyage'];
                        if ($result['shipment']) $results['shipments'][] = $result['shipment'];
                        if ($result['bill']) $results['bills'][] = $result['bill'];
                    });
                    $this->stats['processed']++;
                } catch (Exception $e) {
                    $this->stats['errors']++;
                    $this->stats['warnings'][] = "Error procesando B/L {$bl['bl']}: " . $e->getMessage();
                    Log::error('Error processing B/L', [
                        'bl' => $bl['bl'],
                        'error' => $e->getMessage(),
                        'data_keys' => array_keys($bl['data'] ?? [])
                    ]);
                }
            }

            Log::info('KLine parse completed', $this->stats);

            return new ManifestParseResult(
                success: true,
                voyage: $results['voyages'][0] ?? null, // Voyage principal
                shipments: $results['shipments'],
                containers: [], // KLine no maneja contenedores directamente
                billsOfLading: $results['bills'],
                errors: array_filter($this->stats['warnings'], fn($w) => str_contains($w, 'Error')),
                warnings: array_filter($this->stats['warnings'], fn($w) => !str_contains($w, 'Error')),
                statistics: $this->stats
            );

        } catch (Exception $e) {
            Log::error('Critical error in KLine parser', [
                'file_path' => $filePath,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return new ManifestParseResult(
                success: false,
                voyage: null,
                shipments: [],
                containers: [],
                billsOfLading: [],
                errors: [$e->getMessage()],
                warnings: [],
                statistics: $this->stats
            );
        }
    }

    /**
     * Validar datos parseados
     */
    public function validate(array $data): array
    {
        $errors = [];

        // Validaciones básicas para formato KLine
        if (empty($data)) {
            $errors[] = 'Archivo vacío o no se pudo leer';
            return $errors;
        }

        // Verificar que hay al menos un B/L
        if (!isset($data['bills']) || empty($data['bills'])) {
            $errors[] = 'No se encontraron Bills of Lading válidos';
        }

        return $errors;
    }

    /**
     * Transformar datos a formato estándar
     */
    public function transform(array $data): array
    {
        // KLine ya genera los datos en formato de modelo
        // No necesita transformación adicional
        return $data;
    }

    /**
     * Obtener información del formato soportado
     */
    public function getFormatInfo(): array
    {
        return [
            'name' => 'K-Line DAT',
            'description' => 'Formato de datos K-Line con registros estructurados por tipo',
            'extensions' => ['dat', 'txt'],
            'mime_types' => ['text/plain', 'application/octet-stream'],
            'version' => '1.0',
            'parser_class' => self::class,
            'capabilities' => [
                'multiple_bills_of_lading' => true,
                'auto_port_detection' => true,
                'client_creation' => true,
                'voyage_creation' => true,
                'shipment_items' => true,
                'container_support' => false,
                'dangerous_goods' => false
            ],
            'record_types' => [
                'BLNOREC' => 'Bill of Lading Number',
                'SHPREC' => 'Shipper Information', 
                'CNEEREC' => 'Consignee Information',
                'DESCREC' => 'Description of Goods',
                'MARKREC' => 'Marks and Numbers',
                'VOYGREC' => 'Voyage Information',
                'POLREC' => 'Port of Loading',
                'PODREC' => 'Port of Discharge'
            ],
            'data_quality' => [
                'port_detection_accuracy' => 'high',
                'client_matching' => 'medium',
                'weight_precision' => 'low',
                'date_parsing' => 'none'
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
            'line_separator' => "\n",
            'record_length_minimum' => 8,
            'skip_empty_lines' => true,
            'skip_invalid_records' => true,
            'auto_create_ports' => true,
            'auto_create_clients' => true,
            'default_port_country' => 'AR',
            'default_currency' => 'USD',
            'default_weight_unit' => 'kg',
            'default_measurement_unit' => 'm3',
            'port_detection' => [
                'use_known_ports_list' => true,
                'create_unknown_ports' => true,
                'fallback_origin' => 'UNKNOWN-ORIG',
                'fallback_destination' => 'UNKNOWN-DEST'
            ],
            'client_creation' => [
                'merge_duplicates' => true,
                'required_fields' => ['name'],
                'auto_generate_tax_id' => true,
                'default_client_type' => 'business'
            ],
            'voyage_creation' => [
                'auto_generate_number' => true,
                'number_prefix' => 'KLINE',
                'include_timestamp' => true,
                'default_status' => 'planning',
                'default_cargo_type' => 'general'
            ],
            'item_processing' => [
                'extract_hs_codes' => true,
                'extract_values' => true,
                'default_package_type' => 'general',
                'minimum_weight' => 100,
                'default_quantity' => 1
            ],
            'logging' => [
                'log_level' => 'info',
                'log_parsing_details' => true,
                'log_port_detection' => true,
                'log_client_creation' => true,
                'log_data_extraction' => false
            ],
            'validation' => [
                'strict_port_validation' => false,
                'require_shipper_consignee' => true,
                'require_description' => false,
                'validate_weight_ranges' => false
            ],
            'error_handling' => [
                'continue_on_error' => true,
                'max_errors_per_file' => 50,
                'stop_on_critical_error' => true,
                'critical_errors' => [
                    'no_bills_found',
                    'invalid_file_format',
                    'database_connection_error'
                ]
            ]
        ];
    }

    /**
     * Agrupar líneas por Bill of Lading
     */
    protected function groupByBillOfLading(): array
    {
        $records = [];
        $currentBl = null;
        $currentData = [];

        foreach ($this->lines as $lineNumber => $line) {
            if (strlen($line) < 8) {
                continue; // Skip lines that are too short
            }

            $type = trim(substr($line, 0, 8));
            $content = trim(substr($line, 8));

            Log::debug("Processing line {$lineNumber}", [
                'type' => $type,
                'content' => substr($content, 0, 50) . (strlen($content) > 50 ? '...' : '')
            ]);

            if (Str::startsWith($type, 'BLNOREC')) {
                if ($currentBl) {
                    $records[] = ['bl' => $currentBl, 'data' => $currentData];
                    $currentData = [];
                }
                $currentBl = $content;
            }

            if ($currentBl) {
                $currentData[$type][] = $content;
            }
        }

        if ($currentBl) {
            $records[] = ['bl' => $currentBl, 'data' => $currentData];
        }

        Log::info('Grouped records', ['total_bills' => count($records)]);
        return $records;
    }

    /**
     * Procesar y almacenar datos de un B/L
     */
    protected function storeFromParsedData(array $record): array
    {
        $blNumber = $record['bl'];
        $data = $record['data'];

        Log::info("Processing B/L: {$blNumber}", [
            'data_keys' => array_keys($data)
        ]);

        // Extraer información de puertos del archivo KLine
        $portInfo = $this->extractPortInfo($data);
        $voyageInfo = $this->extractVoyageInfo($data);

        Log::info("Extracted info for B/L {$blNumber}", [
            'voyage_info' => $voyageInfo,
            'port_info' => $portInfo
        ]);

        // Buscar o crear puertos - CRÍTICO: deben existir para el Voyage
        $originPort = $this->findOrCreatePort($portInfo['origin'], 'Origen');
        $destinationPort = $this->findOrCreatePort($portInfo['destination'], 'Destino');

        if (!$originPort || !$destinationPort) {
            throw new Exception("No se pudieron determinar los puertos válidos para B/L: {$blNumber}. " .
                "Origen: {$portInfo['origin']}, Destino: {$portInfo['destination']}");
        }

        // Crear voyage con puertos válidos
        $voyage = $this->findOrCreateVoyage($voyageInfo, $originPort, $destinationPort);

        // Crear shipment
        $shipment = $this->createShipment($voyage, $blNumber);

        // Crear bill of lading
        $bill = $this->createBillOfLading($shipment, $blNumber, $data);

        // Procesar items de carga
        $this->processShipmentItems($bill, $data);

        Log::info("Successfully imported KLine B/L: {$blNumber}", [
            'voyage_id' => $voyage->id,
            'shipment_id' => $shipment->id,
            'bill_id' => $bill->id
        ]);

        return [
            'voyage' => $voyage,
            'shipment' => $shipment,
            'bill' => $bill
        ];
    }

    /**
     * Extraer información de puertos del archivo
     */
    protected function extractPortInfo(array $data): array
    {
        $portInfo = [
            'origin' => null,
            'destination' => null,
            'loading_port' => null,
            'discharge_port' => null
        ];

        // Mapeo de tipos de registro KLine a información de puertos
        $portMappings = [
            // Registros de puertos de carga
            'LOADPORT' => 'origin',
            'PLDREC0' => 'origin',
            'POLREC0' => 'origin',
            'LOADING' => 'origin',
            
            // Registros de puertos de descarga  
            'DISCPORT' => 'destination',
            'PODREC0' => 'destination',
            'DISCREC0' => 'destination',
            'DISCHARGE' => 'destination',
            
            // Registros genéricos de puerto
            'PORTREC0' => 'generic',
            'PORTREC1' => 'generic',
        ];

        // Buscar información de puertos en registros específicos
        foreach ($portMappings as $recordType => $portType) {
            if (!empty($data[$recordType])) {
                foreach ($data[$recordType] as $line) {
                    $portCode = $this->extractPortCodeFromLine($line);
                    if ($portCode) {
                        if ($portType === 'origin' && !$portInfo['origin']) {
                            $portInfo['origin'] = $portCode;
                            Log::info("Found origin port: {$portCode} from {$recordType}");
                        } elseif ($portType === 'destination' && !$portInfo['destination']) {
                            $portInfo['destination'] = $portCode;
                            Log::info("Found destination port: {$portCode} from {$recordType}");
                        }
                    }
                }
            }
        }

        // Si no se encontraron puertos específicos, buscar en todos los registros
        if (!$portInfo['origin'] || !$portInfo['destination']) {
            $this->searchPortsInAllRecords($data, $portInfo);
        }
        
        // Fallback a valores por defecto si no se encuentran
        if (!$portInfo['origin']) {
            $portInfo['origin'] = 'UNKNOWN-ORIG';
            Log::warning('No origin port found, using default');
        }
        
        if (!$portInfo['destination']) {
            $portInfo['destination'] = 'UNKNOWN-DEST';
            Log::warning('No destination port found, using default');
        }

        return $portInfo;
    }

    /**
     * Extraer código de puerto de una línea
     */
    protected function extractPortCodeFromLine(string $line): ?string
    {
        // Patrones comunes para extraer códigos de puerto
        $patterns = [
            '/^([A-Z]{2,6})\s/',           // Código al inicio: "ARBUE Buenos Aires"
            '/\s([A-Z]{2,6})\s/',          // Código en el medio
            '/^([A-Z]{2,6})$/',            // Solo código
            '/PORT\s*:?\s*([A-Z]{2,6})/',  // "PORT: ARBUE"
            '/([A-Z]{2}[A-Z0-9]{3,4})\s/', // Patrón específico de puertos
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, strtoupper($line), $matches)) {
                $code = $matches[1];
                
                // Validar que parece un código de puerto válido
                if (strlen($code) >= 3 && strlen($code) <= 6 && ctype_alnum($code)) {
                    return $code;
                }
            }
        }

        return null;
    }

    /**
     * Buscar puertos en todos los registros
     */
    protected function searchPortsInAllRecords(array $data, array &$portInfo): void
    {
        // Códigos de puerto conocidos (puedes expandir esta lista)
        $knownPorts = [
            'ARBUE', 'ARROS', 'ARCAM', 'ARCON', 'ARSFE', 'ARFOR', 'ARBAR',
            'PYASU', 'PYCON', 'PYTVT', 'PYVIL', 'PYITA', 'PYOLD',
            'BRMNG', 'BRRIG', 'BRPOR', 'BRSDR', 'BRSSZ',
            'UYMON', 'UYMVD', 'UYNPA', 'UYCOL'
        ];

        foreach ($data as $recordType => $lines) {
            if (is_array($lines)) {
                foreach ($lines as $line) {
                    foreach ($knownPorts as $portCode) {
                        if (stripos($line, $portCode) !== false) {
                            if (!$portInfo['origin']) {
                                $portInfo['origin'] = $portCode;
                                Log::info("Found origin port {$portCode} in {$recordType}: {$line}");
                            } elseif (!$portInfo['destination'] && $portCode !== $portInfo['origin']) {
                                $portInfo['destination'] = $portCode;
                                Log::info("Found destination port {$portCode} in {$recordType}: {$line}");
                                return; // Tenemos ambos puertos
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * Extraer información del viaje
     */
    protected function extractVoyageInfo(array $data): array
    {
        $voyageInfo = [
            'voyage_number' => null,
            'vessel_name' => null,
            'voyage_ref' => null
        ];

        // Buscar información de voyage en diferentes tipos de registro
        $voyageRecords = ['VOYGREC0', 'VESSELREC', 'VOYREC0', 'SHIPREC0', 'VSLREC0'];
        
        foreach ($voyageRecords as $recordType) {
            if (!empty($data[$recordType])) {
                foreach ($data[$recordType] as $line) {
                    // Intentar extraer información de voyage y vessel
                    if (preg_match('/^([A-Z0-9\-\/]+)\s*(.*)$/i', trim($line), $matches)) {
                        if (!$voyageInfo['voyage_ref']) {
                            $voyageInfo['voyage_ref'] = $matches[1];
                        }
                        if (!$voyageInfo['vessel_name'] && !empty(trim($matches[2]))) {
                            $voyageInfo['vessel_name'] = trim($matches[2]);
                        }
                    }
                }
            }
        }

        // Generar número de voyage
        if ($voyageInfo['voyage_ref']) {
            $voyageInfo['voyage_number'] = "KLINE-{$voyageInfo['voyage_ref']}-" . now()->format('Ymd');
        } else {
            $voyageInfo['voyage_number'] = 'KLINE-' . uniqid() . '-' . now()->format('Ymd');
        }

        Log::info('Generated voyage info', $voyageInfo);
        return $voyageInfo;
    }

    /**
     * Buscar o crear puerto
     */
    protected function findOrCreatePort(string $portCode, string $type): ?Port
    {
        if (!$portCode || $portCode === 'UNKNOWN-ORIG' || $portCode === 'UNKNOWN-DEST') {
            Log::warning("Attempting to create port with invalid code: {$portCode}");
            return null;
        }

        $port = Port::where('code', $portCode)->first();
        
        if (!$port) {
            // Crear puerto si no existe
            $port = Port::where('code', $code)->first();
    
    if (!$port) {
        $port = Port::create([
            'code' => $code,
            'name' => $defaultName,
            'city' => $defaultName,  // ← AGREGAR ESTE CAMPO REQUERIDO
            'country_id' => $code === 'ARBUE' ? 1 : 2, // AR=1, PY=2
            'port_type' => 'river',  // ← AGREGAR TIPO DE PUERTO
            'active' => true,        // ← CAMBIAR is_active por active
        ]);
    }
            
            Log::info("Created new port: {$portCode} as {$type}");
        }

        return $port;
    }

    /**
     * Obtener nombre del puerto desde código
     */
    protected function getPortNameFromCode(string $portCode): string
    {
        $portNames = [
            'ARBUE' => 'Buenos Aires',
            'ARROS' => 'Rosario',
            'ARCAM' => 'Campana',
            'PYASU' => 'Asunción',
            'PYCON' => 'Concepción',
            'PYTVT' => 'Villeta Terminal',
        ];

        return $portNames[$portCode] ?? ucfirst(strtolower($portCode));
    }

    /**
     * Obtener país desde código de puerto
     */
    protected function getCountryFromPortCode(string $portCode): int
    {
        $countryMappings = [
            'AR' => 1, // Argentina
            'PY' => 2, // Paraguay
            'BR' => 3, // Brasil
            'UY' => 4, // Uruguay
        ];

        $countryCode = substr($portCode, 0, 2);
        return $countryMappings[$countryCode] ?? 1; // Default Argentina
    }

    /**
     * Buscar o crear voyage
     */
    protected function findOrCreateVoyage(array $voyageInfo, Port $originPort, Port $destinationPort): Voyage
    {
        $voyage = Voyage::where('voyage_number', $voyageInfo['voyage_number'])
            ->where('company_id', auth()->user()->company_id)
            ->first();

        if (!$voyage) {
            $voyage = Voyage::create([
                'company_id' => auth()->user()->company_id,
                'voyage_number' => $voyageInfo['voyage_number'],
                'origin_port_id' => $originPort->id,
                'destination_port_id' => $destinationPort->id,
                'vessel_name' => $voyageInfo['vessel_name'] ?? 'KLine Vessel',
                'status' => 'planning',
                'cargo_type' => 'general',
                'created_by_user_id' => auth()->id(),
                'manifest_format' => 'KLINE_DAT',
                'import_source' => 'kline_parser',
                'import_timestamp' => now()
            ]);

            $this->stats['created_voyages']++;
            Log::info("Created new voyage: {$voyageInfo['voyage_number']}");
        }

        return $voyage;
    }

    /**
     * Crear shipment
     */
    protected function createShipment(Voyage $voyage, string $blNumber): Shipment
    {
        $shipment = Shipment::create([
            'voyage_id' => $voyage->id,
            'shipment_number' => "KL-{$blNumber}-" . now()->format('Ymd'),
            'vessel_role' => 'primary',
            'status' => 'planning',
            'cargo_capacity_tons' => 0, // Se actualizará con items
            'container_capacity' => 0,
            'cargo_weight_loaded' => 0,
            'containers_loaded' => 0,
            'utilization_percentage' => 0,
            'active' => true,
            'created_by_user_id' => auth()->id()
        ]);

        $this->stats['created_shipments']++;
        return $shipment;
    }

    /**
     * Crear bill of lading
     */
    protected function createBillOfLading(Shipment $shipment, string $blNumber, array $data): BillOfLading
    {
        // Extraer información del shipper y consignee
        $shipperInfo = $this->extractClientInfo($data, 'SHPREC');
        $consigneeInfo = $this->extractClientInfo($data, 'CNEEREC');

        $bill = BillOfLading::create([
            'shipment_id' => $shipment->id,
            'bl_number' => $blNumber,
            'bl_type' => 'original',
            'shipper_id' => $this->findOrCreateClient($shipperInfo),
            'consignee_id' => $this->findOrCreateClient($consigneeInfo),
            'status' => 'active',
            'freight_terms' => 'prepaid', // Default
            'total_packages' => 0, // Se actualizará
            'gross_weight' => 0, // Se actualizará
            'measurement' => 0,
            'created_by_user_id' => auth()->id(),
            'kline_data' => $data // Guardar datos originales para referencia
        ]);

        $this->stats['created_bills']++;
        return $bill;
    }

    /**
     * Extraer información del cliente
     */
    protected function extractClientInfo(array $data, string $recordType): array
    {
        $clientInfo = [
            'name' => null,
            'address' => null,
            'tax_id' => null
        ];

        if (!empty($data[$recordType])) {
            // El primer registro suele ser el nombre
            if (!empty($data[$recordType][0])) {
                $clientInfo['name'] = trim($data[$recordType][0]);
            }
            
            // Registros siguientes pueden ser dirección
            if (!empty($data[$recordType][1])) {
                $clientInfo['address'] = trim($data[$recordType][1]);
            }
        }

        return $clientInfo;
    }

    /**
     * Buscar o crear cliente
     */
    protected function findOrCreateClient(array $clientInfo): ?int
    {
        if (!$clientInfo['name']) {
            return null;
        }

        $client = Client::where('legal_name', $clientInfo['name'])
            ->where('company_id', auth()->user()->company_id)
            ->first();

        if (!$client) {
            $client = Client::create([
                'company_id' => auth()->user()->company_id,
                'legal_name' => $clientData['name'],
                'commercial_name' => $clientData['name'],
                'tax_id' => 'PARANA-' . uniqid(),
                'client_type' => 'business',
                'status' => 'active',
                'address' => $clientData['address'],
                'phone' => $clientData['phone'],
                'created_by_user_id' => auth()->id()
            ]);
        }


        return $client->id;
    }

    /**
     * Procesar items de carga
     */
    protected function processShipmentItems(BillOfLading $bill, array $data): void
    {
        // Procesar registros de descripción de mercadería
        if (!empty($data['DESCREC0'])) {
            foreach ($data['DESCREC0'] as $index => $description) {
                // Extraer información básica
                $itemData = $this->parseItemDescription($description);
                
                ShipmentItem::create([
                    'bill_of_lading_id' => $bill->id,
                    'line_number' => $index + 1,
                    'description' => $description,
                    'quantity' => $itemData['quantity'] ?? 1,
                    'unit_type' => $itemData['unit'] ?? 'units',
                    'gross_weight' => $itemData['weight'] ?? 0,
                    'net_weight' => $itemData['weight'] ?? 0,
                    'measurement' => $itemData['measurement'] ?? 0,
                    'commodity_code' => $itemData['hs_code'] ?? null,
                    'package_type' => $itemData['package_type'] ?? 'general',
                    'marks_numbers' => $data['MARKREC0'][$index] ?? null,
                    'created_by_user_id' => auth()->id()
                ]);
            }
        }

        // Actualizar totales del bill of lading
        $this->updateBillTotals($bill);
    }

    /**
     * Parsear descripción del item
     */
    protected function parseItemDescription(string $description): array
    {
        $itemData = [];

        // Buscar código HS
        if (preg_match('/HS CODE:\s*([0-9.]+)/', $description, $matches)) {
            $itemData['hs_code'] = $matches[1];
        }

        // Buscar montos en USD
        if (preg_match('/U\$S\s*([\d,]+\.?\d*)/', $description, $matches)) {
            $itemData['value'] = floatval(str_replace(',', '', $matches[1]));
        }

        // Peso por defecto mínimo
        $itemData['weight'] = 100; // kg
        $itemData['quantity'] = 1;
        $itemData['unit'] = 'units';

        return $itemData;
    }

    /**
     * Actualizar totales del bill of lading
     */
    protected function updateBillTotals(BillOfLading $bill): void
    {
        $items = $bill->shipmentItems;
        
        $bill->update([
            'total_packages' => $items->sum('quantity'),
            'gross_weight' => $items->sum('gross_weight'),
            'measurement' => $items->sum('measurement')
        ]);

        // Actualizar totales del shipment
        $shipment = $bill->shipment;
        $shipment->update([
            'cargo_weight_loaded' => $shipment->billsOfLading->sum('gross_weight'),
            'utilization_percentage' => min(100, ($shipment->cargo_weight_loaded / max(1, $shipment->cargo_capacity_tons)) * 100)
        ]);
    }
}