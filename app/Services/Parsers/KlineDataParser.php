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
 * PARSER INTEGRADO PARA KLINE.DAT - VERSIÓN FINAL CORREGIDA
 * 
 * Integra el KlineParserService existente con la nueva arquitectura de manifiestos.
 * Mantiene toda la lógica funcional pero adapta al nuevo flujo unificado.
 * 
 * CORRECCIONES APLICADAS:
 * ✅ Client: tax_id, country_id, document_type_id obligatorios
 * ✅ BillOfLading: campos mínimos requeridos según migración
 * ✅ Status y enums corregidos según modelos reales
 * ✅ Manejo de company_id corregido para auth user
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
            if (preg_match('/^(BLNOREC|GNRLREC|BLRFREC0|BOOKREC0|PTYIREC0|CMMDREC0|DESCREC0|MARKREC0|CARCREC0|FRTCREC0)/', $line)) {
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
                voyage: $results['voyages'][0] ?? null,
                shipments: $results['shipments'],
                containers: [],
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

        if (empty($data)) {
            $errors[] = 'Archivo vacío o no se pudo leer';
            return $errors;
        }

        if (!isset($data['bills']) || empty($data['bills'])) {
            $errors[] = 'No se encontraron Bills of Lading válidos';
        }

        return $errors;
    }

    /**
     * Transformar datos a formato estándar del sistema
     */
    public function transform(array $data): array
    {
        return $data;
    }

    /**
     * Obtener información del formato
     */
    public function getFormatInfo(): array
    {
        return [
            'name' => 'KLine Data Format',
            'description' => 'Formato de archivo de datos .DAT de K-Line',
            'extensions' => ['dat', 'txt'],
            'mime_types' => ['text/plain', 'application/octet-stream'],
            'features' => [
                'multiple_bills_per_file',
                'automatic_voyage_creation',
                'port_detection',
                'client_creation'
            ]
        ];
    }

    /**
     * Obtener configuración por defecto del parser
     */
    public function getDefaultConfig(): array
    {
        return [
            'parsing' => [
                'line_encoding' => 'UTF-8',
                'skip_empty_lines' => true,
                'min_line_length' => 8,
                'record_type_length' => 8
            ],
            'ports' => [
                'auto_create_missing' => true,
                'default_origin' => 'ARBUE',
                'default_destination' => 'PYTVT',
                'known_ports' => [
                    'ARBUE', 'ARROS', 'ARCAM', 'PYASU', 'PYCON', 'PYTVT',
                    'BRBEL', 'BRSSZ', 'UYMON', 'UYNDE'
                ]
            ],
            'voyage' => [
                'auto_create' => true,
                'default_vessel_name' => 'KLINE VESSEL',
                'voyage_prefix' => 'KLINE',
                'default_status' => 'planning'
            ],
            'clients' => [
                'auto_create_missing' => true,
                'default_type' => 'business',
                'code_prefix' => 'KLINE'
            ]
        ];
    }

    /**
     * Agrupar líneas por Bill of Lading - CORREGIDO: limpiar bill_number
     */
    protected function groupByBillOfLading(): array
    {
        $records = [];
        $currentBl = null;
        $currentData = [];

        foreach ($this->lines as $lineNumber => $line) {
            if (strlen($line) < 8) {
                continue;
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
                // ✅ CORREGIDO: Limpiar y truncar bill_number a 50 caracteres máximo
                $currentBl = $this->cleanBillNumber($content);
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
     * Limpiar y truncar bill_number para cumplir límite BD - NUEVO
     */
    protected function cleanBillNumber(string $rawBillNumber): string
    {
        // 1. Limpiar espacios extras y caracteres de control
        $cleaned = trim(preg_replace('/\s+/', ' ', $rawBillNumber));
        
        // 2. Extraer solo la parte del número del B/L (antes del nombre del buque si existe)
        // Patrón típico: "KLUCTG001009                                  CAPRI RIO"
        // Solo tomar la primera parte hasta el primer espacio grande
        if (preg_match('/^([A-Z0-9\-\/]+)/', $cleaned, $matches)) {
            $billNumber = $matches[1];
        } else {
            // Fallback: tomar primeros 20 caracteres alfanuméricos
            $billNumber = preg_replace('/[^A-Z0-9\-\/]/', '', substr($cleaned, 0, 20));
        }
        
        // 3. Asegurar que no esté vacío
        if (empty($billNumber)) {
            $billNumber = 'KLINE_' . uniqid();
        }
        
        // 4. Truncar a máximo 50 caracteres (límite de BD)
        $billNumber = substr($billNumber, 0, 50);
        
        Log::info('Cleaned bill number', [
            'original' => $rawBillNumber,
            'cleaned' => $billNumber,
            'length' => strlen($billNumber)
        ]);
        
        return $billNumber;
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

        // Buscar o crear puertos
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
        $bill = $this->createBillOfLading($shipment, $blNumber, $data, $originPort, $destinationPort);

        // Procesar items de carga
        $this->processShipmentItems($bill, $data);

        Log::info("Successfully imported KLine B/L: {$blNumber}", [
            'voyage_id' => $voyage->id,
            'shipment_id' => $shipment->id,
            'bill_id' => $bill->id
        ]);

        // Actualizar stats
        $this->stats['created_bills']++;
        if ($voyage->wasRecentlyCreated) $this->stats['created_voyages']++;
        if ($shipment->wasRecentlyCreated) $this->stats['created_shipments']++;

        return [
            'voyage' => $voyage,
            'shipment' => $shipment,
            'bill' => $bill
        ];
    }

    /**
     * Extraer información de puertos del KlineParserService funcional
     */
    protected function extractPortInfo(array $data): array
    {
        $portInfo = [
            'origin' => null,
            'destination' => null,
            'loading_port' => null,
            'discharge_port' => null
        ];

        $knownPorts = [
            'ARBUE', 'ARROS', 'ARCAM', 'PYASU', 'PYCON', 'PYTVT',
            'BRBEL', 'BRSSZ', 'UYMON', 'UYNDE'
        ];

        Log::info("Looking for ports in data", [
            'available_record_types' => array_keys($data),
            'known_ports' => $knownPorts
        ]);

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
                                return $portInfo;
                            }
                        }
                    }
                }
            }
        }

        // Si no encontramos puertos, usar defaults
        if (!$portInfo['origin']) {
            $portInfo['origin'] = 'ARBUE';
            Log::warning("No origin port found, using default: ARBUE");
        }

        if (!$portInfo['destination']) {
            $portInfo['destination'] = 'PYTVT';
            Log::warning("No destination port found, using default: PYTVT");
        }

        return $portInfo;
    }

    /**
     * Extraer información del viaje del KlineParserService funcional
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
            $port = Port::create([
                'code' => $portCode,
                'name' => $this->getPortNameFromCode($portCode),
                'city' => $this->getPortNameFromCode($portCode),
                'country_id' => $this->getCountryFromPortCode($portCode),
                'port_type' => 'river',
                'active' => true,
            ]);
            
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
        // Obtener company_id correctamente
        $user = auth()->user();
        $companyId = null;

        if ($user->company_id) {
            $companyId = $user->company_id;
        } elseif ($user->userable_type === 'App\Models\Company' && $user->userable_id) {
            $companyId = (int) $user->userable_id;
        } else {
            $company = $user->company ?? null;
            $companyId = $company?->id;
        }
        
        if (!$companyId) {
            throw new Exception("Usuario no tiene empresa asignada. User ID: {$user->id}");
        }

        $voyage = Voyage::where('voyage_number', $voyageInfo['voyage_number'])
            ->where('company_id', $companyId)
            ->first();

        if (!$voyage) {
            $voyage = Voyage::create([
                'company_id' => $companyId,
                'voyage_number' => $voyageInfo['voyage_number'],
                'origin_port_id' => $originPort->id,
                'destination_port_id' => $destinationPort->id,
                'lead_vessel_id' => 1, // TODO: Usar vessel real
                'origin_country_id' => $this->getCountryFromPortCode(substr($originPort->code, 0, 2)),
                'destination_country_id' => $this->getCountryFromPortCode(substr($destinationPort->code, 0, 2)),
                'vessel_name' => $voyageInfo['vessel_name'] ?? 'KLINE VESSEL',
                'departure_date' => now()->addDays(7),
                'estimated_arrival_date' => now()->addDays(14),
                'voyage_type' => 'single_vessel',
                'cargo_type' => 'export',
                'status' => 'planning',
                'created_by_user_id' => auth()->id()
            ]);
        }

        return $voyage;
    }

    /**
     * Crear shipment
     */
    protected function createShipment(Voyage $voyage, string $blNumber): Shipment
    {
        return Shipment::create([
            'voyage_id' => $voyage->id,
            'vessel_id' => 1, // TODO: Usar vessel real
            'shipment_number' => "KLINE-SHIP-{$blNumber}",
            'sequence_in_voyage' => 1,
            'vessel_role' => 'single',
            'cargo_capacity_tons' => 1000.0,
            'container_capacity' => 0,
            'cargo_weight_loaded' => 0,
            'containers_loaded' => 0,
            'utilization_percentage' => 0.0,
            'status' => 'planning',
            'created_by_user_id' => auth()->id()
        ]);
    }

    /**
     * Crear bill of lading - CORREGIDO: campos mínimos según migración
     */
    protected function createBillOfLading(Shipment $shipment, string $blNumber, array $data, Port $originPort, Port $destinationPort): BillOfLading
    {
        // Extraer información de clientes
        $clientInfo = $this->extractClientInfo($data);
        
        return BillOfLading::create([
            // CAMPOS OBLIGATORIOS según migración y validaciones
            'shipment_id' => $shipment->id,
            'shipper_id' => $this->findOrCreateClient($clientInfo['shipper']),
            'consignee_id' => $this->findOrCreateClient($clientInfo['consignee']),
            'notify_party_id' => $clientInfo['notify'] ? $this->findOrCreateClient($clientInfo['notify']) : null,
            
            // Puertos obligatorios según validaciones
            'loading_port_id' => $originPort->id,
            'discharge_port_id' => $destinationPort->id,
            
            // Tipos obligatorios (usando IDs por defecto del sistema)
            'primary_cargo_type_id' => 1, // General cargo
            'primary_packaging_type_id' => 1, // General packaging
            
            // Identificación del conocimiento
            'bill_number' => $blNumber,
            'bill_date' => now(),
            'loading_date' => now()->addDays(1), // ✅ AGREGADO: Campo obligatorio
            
            // Pesos mínimos requeridos
            'total_packages' => 1,
            'gross_weight_kg' => 100.0,
            'net_weight_kg' => 100.0,
            
            // Términos comerciales obligatorios
            'freight_terms' => 'collect', // ✅ CORREGIDO: 'collect' en lugar de 'COLLECT'
            
            // Descripción de carga obligatoria
            'cargo_description' => 'MERCADERIA GENERAL KLINE', // ✅ AGREGADO: Campo obligatorio
            
            // Estado y auditoría
            'status' => 'draft', // ✅ CORREGIDO: usar 'draft' en lugar de 'issued'
            'created_by_user_id' => auth()->id()
        ]);
    }

    /**
     * Extraer información de clientes
     */
    protected function extractClientInfo(array $data): array
    {
        $clientInfo = [
            'shipper' => null,
            'consignee' => null,
            'notify' => null
        ];

        if (!empty($data['PTYIREC0'])) {
            foreach ($data['PTYIREC0'] as $line) {
                if (stripos($line, 'SHIPPER') !== false || stripos($line, 'EXPORT') !== false) {
                    $clientInfo['shipper'] = $this->parseClientFromLine($line);
                } elseif (stripos($line, 'CONSIGN') !== false || stripos($line, 'IMPORT') !== false) {
                    $clientInfo['consignee'] = $this->parseClientFromLine($line);
                } elseif (stripos($line, 'NOTIFY') !== false) {
                    $clientInfo['notify'] = $this->parseClientFromLine($line);
                }
            }
        }

        // Usar defaults si no se encuentran
        $clientInfo['shipper'] = $clientInfo['shipper'] ?? [
            'legal_name' => 'KLINE SHIPPER',
            'tax_id' => 'KLINE001',
            'country_id' => 1,
            'document_type_id' => 1,
            'address' => 'ADDRESS NOT SPECIFIED'
        ];

        $clientInfo['consignee'] = $clientInfo['consignee'] ?? [
            'legal_name' => 'KLINE CONSIGNEE',
            'tax_id' => 'KLINE002',
            'country_id' => 2,
            'document_type_id' => 1,
            'address' => 'ADDRESS NOT SPECIFIED'
        ];

        return $clientInfo;
    }

    /**
     * Parsear cliente desde línea
     */
    protected function parseClientFromLine(string $line): array
    {
        $parts = explode(' ', trim($line));
        $name = implode(' ', array_slice($parts, 0, 3));
        
        return [
            'legal_name' => $name ?: 'UNKNOWN CLIENT',
            'tax_id' => 'KLINE' . substr(md5($name), 0, 8),
            'country_id' => 1, // Default Argentina
            'document_type_id' => 1, // Default document type
            'address' => implode(' ', array_slice($parts, 3)) ?: 'ADDRESS NOT SPECIFIED'
        ];
    }

    /**
     * Buscar o crear cliente - CORREGIDO: campos obligatorios
     */
    protected function findOrCreateClient(array $clientData): int
    {
        if (!$clientData || !$clientData['legal_name']) {
            throw new Exception('Datos de cliente inválidos');
        }

        // Obtener company_id correctamente
        $user = auth()->user();
        $companyId = null;

        if ($user->company_id) {
            $companyId = $user->company_id;
        } elseif ($user->userable_type === 'App\Models\Company' && $user->userable_id) {
            $companyId = (int) $user->userable_id;
        } else {
            $company = $user->company ?? null;
            $companyId = $company?->id;
        }
        
        if (!$companyId) {
            throw new Exception("Usuario no tiene empresa asignada para crear cliente. User ID: {$user->id}");
        }

        // Buscar cliente existente por tax_id y country_id
        $client = Client::where('tax_id', $clientData['tax_id'])
            ->where('country_id', $clientData['country_id'])
            ->first();

        if (!$client) {
            $client = Client::create([
                // CAMPOS OBLIGATORIOS según modelo Client
                'tax_id' => $clientData['tax_id'],
                'country_id' => $clientData['country_id'],
                'document_type_id' => $clientData['document_type_id'],
                'legal_name' => $clientData['legal_name'],
                
                // CAMPOS OPCIONALES
                'commercial_name' => $clientData['legal_name'],
                'address' => $clientData['address'] ?? null,
                
                // CAMPOS DE SISTEMA
                'status' => 'active',
                'created_by_company_id' => $companyId // Para auditoría
            ]);
        }

        return $client->id;
    }

    /**
     * Procesar items de carga - CORREGIDO: usar shipment_id en lugar de bill_of_lading_id
     */
    protected function processShipmentItems(BillOfLading $bill, array $data): void
    {
        if (!empty($data['DESCREC0'])) {
            foreach ($data['DESCREC0'] as $index => $description) {
                $itemData = $this->parseItemDescription($description);
                
                ShipmentItem::create([
                    // ✅ CORREGIDO: usar shipment_id (tabla pertenece a shipment, no a bill)
                    'shipment_id' => $bill->shipment_id,
                    
                    // Campos obligatorios según migración
                    'line_number' => $index + 1,
                    'cargo_type_id' => 1, // ✅ AGREGADO: campo obligatorio
                    'packaging_type_id' => 1, // ✅ AGREGADO: campo obligatorio
                    'package_quantity' => $itemData['quantity'] ?? 1, // ✅ CORREGIDO: package_quantity
                    'gross_weight_kg' => $itemData['weight'] ?? 100,
                    'item_description' => $description, // ✅ CORREGIDO: item_description
                    
                    // Campos opcionales
                    'net_weight_kg' => $itemData['weight'] ?? 100,
                    'volume_m3' => $itemData['measurement'] ?? 1,
                    'commodity_code' => $itemData['hs_code'] ?? null,
                    'cargo_marks' => $data['MARKREC0'][$index] ?? null,
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

        if (preg_match('/HS CODE:\s*([0-9.]+)/', $description, $matches)) {
            $itemData['hs_code'] = $matches[1];
        }

        if (preg_match('/(\d+\.?\d*)\s*KG/i', $description, $matches)) {
            $itemData['weight'] = (float) $matches[1];
        }

        if (preg_match('/(\d+)\s*(UNITS|PCS|BOXES)/i', $description, $matches)) {
            $itemData['quantity'] = (int) $matches[1];
            $itemData['unit'] = strtolower($matches[2]);
        }

        return $itemData;
    }

    /**
     * Actualizar totales del B/L - CORREGIDO: usar shipmentItems() en lugar de items()
     */
    protected function updateBillTotals(BillOfLading $bill): void
    {
        // ✅ CORREGIDO: usar la relación correcta shipmentItems()
        $totals = $bill->shipment->shipmentItems()
            ->selectRaw('
                SUM(package_quantity) as total_quantity,
                SUM(gross_weight_kg) as total_weight,
                SUM(volume_m3) as total_volume
            ')
            ->first();

        $bill->update([
            'total_packages' => $totals->total_quantity ?? 1,
            'gross_weight_kg' => $totals->total_weight ?? 100,
            'net_weight_kg' => $totals->total_weight ?? 100,
            'volume_m3' => $totals->total_volume ?? 1
        ]);

        // Actualizar también el shipment si tiene los campos
        if ($bill->shipment) {
            $bill->shipment->update([
                'cargo_weight_loaded' => $totals->total_weight ?? 100,
                'utilization_percentage' => min(100, (($totals->total_weight ?? 100) / $bill->shipment->cargo_capacity_tons) * 100)
            ]);
        }
    }
}