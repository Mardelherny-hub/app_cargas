<?php

namespace App\Services\Parsers;

use App\Contracts\ManifestParserInterface;
use App\ValueObjects\ManifestParseResult;
use App\Models\Voyage;
use App\Models\Shipment;
use App\Models\BillOfLading;
use App\Models\Container;
use App\Models\Client;
use App\Models\Port;
use App\Models\Vessel;
use App\Models\ContainerType;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Exception; 

/**
 * PARSER PARA PARANA.xlsx - FORMATO MAERSK ESTÁNDAR
 * 
 * Datos reales confirmados:
 * - 253 filas × 73 columnas
 * - MAERSK LINE ARGENTINA S.A
 * - Barcaza: PAR13001, Viaje: V022NB  
 * - Ruta: ARBUE → PYTVT
 * - 111 BL únicos, 6 tipos de contenedores
 */
class ParanaExcelParser implements ManifestParserInterface
{
    // Mapeo exacto de columnas según análisis real
    protected array $columnMap = [
        'A' => 'LOCATION_NAME',           // MAERSK LINE ARGENTINA S.A
        'B' => 'ADDRESS_LINE1',           // Dirección empresa
        'C' => 'ADDRESS_LINE2',           
        'D' => 'ADDRESS_LINE3',           
        'E' => 'CITY',                    // Ciudad empresa
        'F' => 'ZIP',                     // Código postal
        'G' => 'COUNTRY_NAME',            // País empresa
        'H' => 'TELEPHONE_NO',            // Teléfono
        'I' => 'FAX_NO',                  // Fax
        'J' => 'EMAIL_ID',                // Email
        'K' => 'MANIFEST_TYPE',           // CM = Consolidado Marítimo
        'L' => 'BARGE_ID',                // ID barcaza
        'M' => 'BARGE_NAME',              // PAR13001
        'N' => 'VOYAGE_NO',               // V022NB
        'O' => 'BL_NUMBER',               // Número BL
        'P' => 'BL_DATE',                 // Fecha BL
        'Q' => 'POL',                     // Puerto carga: ARBUE
        'R' => 'POL_TERMINAL',            // Terminal carga
        'S' => 'POD',                     // Puerto descarga: PYTVT
        'T' => 'POD_TERMINAL',            // TERPORT VILLETA
        'U' => 'FREIGHT_TERMS',           // Términos flete
        'V' => 'SHIPPER_NAME',            // Embarcador
        'W' => 'SHIPPER_ADDRESS1',        // Dirección embarcador
        'X' => 'SHIPPER_ADDRESS2',        
        'Y' => 'SHIPPER_ADDRESS3',        
        'Z' => 'SHIPPER_CITY',            
        'AA' => 'SHIPPER_ZIP',            
        'AB' => 'SHIPPER_COUNTRY',        
        'AC' => 'SHIPPER_PHONE',          
        'AD' => 'SHIPPER_FAX',            
        'AE' => 'CONSIGNEE_NAME',         // Consignatario
        'AF' => 'CONSIGNEE_ADDRESS1',     
        'AG' => 'CONSIGNEE_ADDRESS2',     
        'AH' => 'CONSIGNEE_ADDRESS3',     
        'AI' => 'CONSIGNEE_CITY',         
        'AJ' => 'CONSIGNEE_ZIP',          
        'AK' => 'CONSIGNEE_COUNTRY',      
        'AL' => 'CONSIGNEE_PHONE',        
        'AM' => 'CONSIGNEE_FAX',          
        'AN' => 'NOTIFY_PARTY_NAME',      // Notificado
        'AO' => 'NOTIFY_PARTY_ADDRESS1',  
        'AP' => 'NOTIFY_PARTY_ADDRESS2',  
        'AQ' => 'NOTIFY_PARTY_ADDRESS3',  
        'AR' => 'NOTIFY_PARTY_CITY',      
        'AS' => 'NOTIFY_PARTY_ZIP',       
        'AT' => 'NOTIFY_PARTY_COUNTRY',   
        'AU' => 'NOTIFY_PARTY_PHONE',     
        'AV' => 'NOTIFY_PARTY_FAX',       
        'AW' => 'PFD',                    
        'AX' => 'CONTAINER_NUMBER',       // Número contenedor
        'AY' => 'CONTAINER_TYPE',         // 40HC, 20DV, 40DV, 40FR, 20TN, 40RH
        'AZ' => 'CONTAINER_STATUS',       
        'BA' => 'SEAL_NO',                // Número sello
        'BB' => 'PACK_TYPE',              // Tipo empaque
        'BC' => 'NUMBER_OF_PACKAGES',     // Cantidad bultos
        'BD' => 'GROSS_WEIGHT',           // Peso bruto
        'BE' => 'NET_WEIGHT',             // Peso neto
        'BF' => 'TARE_WEIGHT',            // Peso tara
        'BG' => 'VOLUME',                 // Volumen
        'BH' => 'REMARKS',                
        'BI' => 'MARKS_DESCRIPTION',      
        'BJ' => 'DESCRIPTION',            // Descripción mercadería
        'BK' => 'IMO_NUMBER',             // Número IMO (peligrosas)
        'BL' => 'UN_NUMBER',              // Número UN
        'BM' => 'FLASH_POINT',            // Punto inflamación
        'BN' => 'TEMP_MAX',               // Temperatura máxima
        'BO' => 'TEMP_MIN',               // Temperatura mínima
        'BP' => 'NCM',                    // Código NCM
        'BQ' => 'REMARKS1',               
        'BR' => 'REMARKS2',               
        'BS' => 'REMARKS3',               
        'BT' => 'MLO_BL_NR',              // MLO BL Number
        'BU' => 'PERMISO'                 // Número permiso
    ];

    public function canParse(string $filePath): bool
    {
        // Verificar extensión
        if (!in_array(strtolower(pathinfo($filePath, PATHINFO_EXTENSION)), ['xlsx', 'xls'])) {
            return false;
        }

        try {
            $reader = IOFactory::createReaderForFile($filePath);
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($filePath);
            $worksheet = $spreadsheet->getActiveSheet();
            
            // Verificar estructura PARANA: debe tener ~73 columnas y datos MAERSK
            $highestColumn = $worksheet->getHighestColumn();
            $columnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);
            
            if ($columnIndex < 70) { // Debe tener ~73 columnas
                return false;
            }

            // Buscar indicadores PARANA/MAERSK en primeras filas
            for ($row = 1; $row <= 10; $row++) {
                $locationName = $worksheet->getCell('A' . $row)->getCalculatedValue();
                if (str_contains(strtoupper($locationName), 'MAERSK') || 
                    str_contains(strtoupper($locationName), 'PARANA')) {
                    return true;
                }
            }

            return false;
        } catch (Exception $e) {
            Log::debug('PARANA parser canParse failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    public function parse(string $filePath): ManifestParseResult
    {
        Log::info('Starting PARANA Excel parsing', ['file' => $filePath]);

        try {
            $reader = IOFactory::createReaderForFile($filePath);
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($filePath);
            $worksheet = $spreadsheet->getActiveSheet();

            $highestRow = $worksheet->getHighestRow();
            Log::info('PARANA file loaded', ['rows' => $highestRow]);

            // Extraer información del voyage de la primera fila
            $voyageData = $this->extractVoyageData($worksheet);
            $vesselData = $this->extractVesselDataFromExcel($worksheet);
            $voyageData = array_merge($voyageData, $vesselData);
                        
            // Crear voyage
            $voyage = $this->createVoyage($voyageData);
            
            // Crear shipment principal
            $vessel = $this->findOrCreateVessel($voyageData['barge_name'] ?? 'PAR13001', $voyage->company_id);
            $shipment = $this->createShipment($voyage, $vessel, $voyageData);       

            // Procesar filas de datos (ignorar header si existe)
            $containers = [];
            $bills = [];
            $processedBLs = [];
            
            for ($row = 2; $row <= $highestRow; $row++) {
                $rowData = $this->extractRowData($worksheet, $row);
                
                if (empty($rowData['BL_NUMBER'])) {
                    continue;
                }

                // Crear BL solo si no existe
                $blNumber = $rowData['BL_NUMBER'];
                if (!isset($processedBLs[$blNumber])) {
                    // AGREGAR: Verificar si ya existe en base de datos
                    $existingBL = BillOfLading::where('bill_number', $blNumber)->first();
                    
                    if ($existingBL) {
                        Log::info('BL ya existe en BD', ['bill_number' => $blNumber, 'id' => $existingBL->id]);
                        $bill = $existingBL;
                        $processedBLs[$blNumber] = $bill;
                    } else {
                        $bill = $this->createBillOfLading($shipment, $rowData);
                        $bills[] = $bill;
                        $processedBLs[$blNumber] = $bill;
                    }
                } else {
                    $bill = $processedBLs[$blNumber];
                }

                // Crear contenedor para esta fila
                if (!empty($rowData['CONTAINER_NUMBER'])) {
                    $container = $this->createContainer($bill, $rowData);
                    $containers[] = $container;
                }
            }

            Log::info('PARANA parsing completed', [
                'voyage_id' => $voyage->id,
                'bills_count' => count($bills),
                'containers_count' => count($containers)
            ]);

            return ManifestParseResult::success(
                voyage: $voyage,
                shipments: [$shipment],
                containers: $containers,
                billsOfLading: $bills,
                statistics: [
                    'processed_rows' => $highestRow - 1,
                    'unique_bills' => count($bills),
                    'total_containers' => count($containers)
                ]
            );

        } catch (Exception $e) {
            Log::error('PARANA parsing failed', [
                'file' => $filePath,
                'error' => $e->getMessage()
            ]);

            return ManifestParseResult::failure([
                'Error al procesar archivo PARANA: ' . $e->getMessage()
            ]);
        }
    }

    protected function extractVoyageData($worksheet): array
    {
        // Datos del voyage desde primera fila
        return [
            'company_name' => $worksheet->getCell('A1')->getCalculatedValue() ?: 'MAERSK LINE ARGENTINA S.A',
            'barge_name' => $worksheet->getCell('M1')->getCalculatedValue() ?: 'PAR13001',
            'voyage_number' => $worksheet->getCell('N1')->getCalculatedValue() ?: 'V022NB',
            'pol' => $worksheet->getCell('Q1')->getCalculatedValue() ?: 'ARBUE',
            'pod' => $worksheet->getCell('S1')->getCalculatedValue() ?: 'PYTVT',
            'pol_terminal' => $worksheet->getCell('R1')->getCalculatedValue(),
            'pod_terminal' => $worksheet->getCell('T1')->getCalculatedValue() ?: 'TERPORT VILLETA'
        ];
    }

    protected function extractRowData($worksheet, int $row): array
    {
        $data = [];
        foreach ($this->columnMap as $col => $field) {
            $value = $worksheet->getCell($col . $row)->getCalculatedValue();
            $data[$field] = $value;
        }
        return $data;
    }

    protected function createVoyage(array $data): Voyage
    {
        // DEBUG: Verificar estado del usuario y company
        $user = auth()->user();
        Log::info('createVoyage Debug', [
            'user_id' => $user?->id,
            'user_email' => $user?->email,
            'user_company_id' => $user?->company_id,
            'user_userable_type' => $user?->userable_type,
            'user_userable_id' => $user?->userable_id,
            'user_company_relation' => $user?->company?->id ?? 'NO COMPANY RELATION',
        ]);

        // CORREGIDO: Obtener company_id correctamente
        if ($user->company_id) {
            $companyId = $user->company_id;
        } elseif ($user->userable_type === 'App\Models\Company' && $user->userable_id) {
            $companyId = (int) $user->userable_id;
        } else {
            $companyId = null;
        }

        if (!$companyId) {
            throw new \Exception("Usuario no tiene empresa asignada. User ID: {$user->id}");
        }

        // Crear/buscar puertos PRIMERO para obtener country_ids
        $originPort = $this->findOrCreatePort($data['pol'], 'Buenos Aires');
        $destPort = $this->findOrCreatePort($data['pod'], 'Terminal Villeta');
        
        // CORREGIDO: Buscar o crear vessel con campos obligatorios
        $vessel = $this->findOrCreateVessel($data['barge_name'] ?? 'PAR13001', $companyId);

        $voyageNumber = 'PARANA-' . now()->format('YmdHis') . '-' . uniqid();

        return Voyage::create([
            'company_id' => $companyId,
            'voyage_number' => $voyageNumber,
            'origin_port_id' => $originPort->id,
            'destination_port_id' => $destPort->id,
            'lead_vessel_id' => $vessel->id, // CORREGIDO: vessel real con registration_number
            
            // CORREGIDO: country_ids dinámicos desde los puertos
            'origin_country_id' => $originPort->country_id,
            'destination_country_id' => $destPort->country_id,
            
            'departure_date' => now(),
            'estimated_arrival_date' => now()->addDays(3),
            'status' => 'planning',
            
            // CORREGIDO: valores enum dinámicos según datos
            'voyage_type' => $this->determineVoyageType($data),
            'cargo_type' => $this->determineCargoType($data, $originPort, $destPort),
            
            'created_by_user_id' => auth()->id()
        ]);
    }

    protected function determineVoyageType(array $data): string
    {
        // Analizar si es convoy basado en datos del Excel
        $vesselCount = intval($data['vessel_count'] ?? 1);
        $voyageRef = strtoupper($data['voyage_number'] ?? '');
        
        if ($vesselCount > 1 || str_contains($voyageRef, 'CONVOY')) {
            return 'convoy';
        }
        
        if (str_contains($voyageRef, 'FLEET')) {
            return 'fleet';
        }
        
        return 'single_vessel'; // Default
    }

    protected function determineCargoType(array $data, Port $originPort, Port $destPort): string
    {
        // DEBUG: Log para analizar determinación de cargo type
        Log::info('determineCargoType Debug', [
            'origin_country' => $originPort->country_id,
            'dest_country' => $destPort->country_id,
            'manifest_type' => $data['MANIFEST_TYPE'] ?? 'N/A'
        ]);
        
        // Determinar basado en países de origen y destino
        $originCountry = $originPort->country_id;
        $destCountry = $destPort->country_id;
        
        // Argentina (1) -> Paraguay (2) = Export
        if ($originCountry == 1 && $destCountry == 2) {
            return 'export';
        }
        
        // Paraguay (2) -> Argentina (1) = Import  
        if ($originCountry == 2 && $destCountry == 1) {
            return 'import';
        }
        
        // Mismo país = Cabotage
        if ($originCountry == $destCountry) {
            return 'cabotage';
        }
        
        // Países diferentes con transbordo = Transit
        if (isset($data['transshipment_port']) || str_contains($data['MANIFEST_TYPE'] ?? '', 'TRANSIT')) {
            return 'transit';
        }
        
        return 'export'; // Default para PARANA (generalmente AR->PY)
    }

    protected function createShipment(Voyage $voyage, Vessel $vessel, array $data): Shipment
    {
        return Shipment::create([
            'voyage_id' => $voyage->id,
            'vessel_id' => $vessel->id,  // CORREGIDO: usar vessel real
            'shipment_number' => 'PARANA-' . now()->format('YmdHis'),
            'sequence_in_voyage' => 1,
            'vessel_role' => 'single',
            'cargo_capacity_tons' => $vessel->cargo_capacity_tons ?? 1500.00,  // CORREGIDO: usar capacidad real
            'container_capacity' => $vessel->container_capacity ?? 64,  // CORREGIDO: usar capacidad real
            'status' => 'planning',
            'active' => true,
            'created_by_user_id' => auth()->id()
        ]);
    }

    protected function createBillOfLading(Shipment $shipment, array $data): BillOfLading
    {
        // DEBUG: Log del BL que se está procesando
        Log::info('createBillOfLading Debug', [
            'shipment_id' => $shipment->id,
            'bl_number' => $data['BL_NUMBER'] ?? 'N/A',
            'bl_date' => $data['BL_DATE'] ?? 'N/A',
            'description' => $data['DESCRIPTION'] ?? 'N/A'
        ]);

        // Obtener o crear clientes
        $shipper = $this->findOrCreateClient([
            'name' => $data['SHIPPER_NAME'] ?? 'Shipper Unknown',
            'address' => $data['SHIPPER_ADDRESS1'] ?? null,  // CORREGIDO: era SHIPPER_ADDRESS
            'phone' => $data['SHIPPER_PHONE'] ?? null
        ], $shipment->voyage->company_id);

        $consignee = $this->findOrCreateClient([
            'name' => $data['CONSIGNEE_NAME'] ?? 'Consignee Unknown', 
            'address' => $data['CONSIGNEE_ADDRESS1'] ?? null,  // CORREGIDO: era CONSIGNEE_ADDRESS
            'phone' => $data['CONSIGNEE_PHONE'] ?? null
        ], $shipment->voyage->company_id);

        // Obtener puertos
        $loadingPort = $this->findOrCreatePort($data['POL'] ?? 'ARBUE', 'Buenos Aires');
        $dischargePort = $this->findOrCreatePort($data['POD'] ?? 'PYTVT', 'Villeta');

        // CORREGIDO: Generar fechas obligatorias
        $billDate = $this->parseDateFromData($data['BL_DATE']) ?? now();
        $loadingDate = $this->parseDateFromData($data['BL_DATE']) ?? now()->addDays(1);

        $bill = BillOfLading::create([
            'shipment_id' => $shipment->id,
            'bill_number' => $data['BL_NUMBER'],
            
            // AGREGADO: Campos de fecha obligatorios
            'bill_date' => $billDate,
            'loading_date' => $loadingDate,
            
            // AGREGADO: Descripción de carga obligatoria
            'cargo_description' => $data['DESCRIPTION'] ?? 'Mercadería general importada desde PARANA Excel',
            
            'shipper_id' => $shipper->id,
            'consignee_id' => $consignee->id,
            'loading_port_id' => $loadingPort->id,
            'discharge_port_id' => $dischargePort->id,
            'freight_terms' => 'prepaid',
            'status' => 'draft',
            'primary_cargo_type_id' => 1,
            'primary_packaging_type_id' => 1,
            
            // AGREGADO: Campos adicionales con valores por defecto
            'gross_weight_kg' => $this->parseWeight($data['GROSS_WEIGHT']),
            'net_weight_kg' => $this->parseWeight($data['NET_WEIGHT']),
            'total_packages' => intval($data['NUMBER_OF_PACKAGES'] ?? 1),  // CORREGIDO: era PACKAGE_COUNT
            'volume_m3' => $this->parseVolume($data['VOLUME']),
        ]);

        Log::info('BillOfLading creado', [
            'bill_id' => $bill->id,
            'bill_number' => $bill->bill_number,
            'bill_date' => $bill->bill_date->toDateString(),
            'loading_date' => $bill->loading_date->toDateString()
        ]);

        return $bill;
    }

    protected function parseDateFromData(?string $dateValue): ?\Carbon\Carbon
    {
        if (!$dateValue) {
            return null;
        }

        try {
            return \Carbon\Carbon::parse($dateValue);
        } catch (\Exception $e) {
            Log::warning('No se pudo parsear fecha', [
                'date_value' => $dateValue,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    protected function createContainer(BillOfLading $bill, array $data): ?Container
    {
        if (empty($data['CONTAINER_NUMBER'])) {
            return null;
        }

        // DEBUG: Log del container que se está procesando
        Log::info('createContainer Debug', [
            'container_number' => $data['CONTAINER_NUMBER'],
            'container_type' => $data['CONTAINER_TYPE'] ?? 'N/A',
            'bill_id' => $bill->id
        ]);

        // Verificar si ya existe
        $existing = Container::where('container_number', $data['CONTAINER_NUMBER'])->first();
        if ($existing) {
            Log::info('Container already exists', [
                'container_id' => $existing->id,
                'number' => $data['CONTAINER_NUMBER']
            ]);
            return $existing;
        }

        // CORREGIDO: Usar container types existentes en lugar de crear nuevos
        $containerType = $this->findExistingContainerType(
            $data['CONTAINER_TYPE'] ?? '40HC'
        );

        $container = Container::create([
            'container_number' => $data['CONTAINER_NUMBER'],
            'container_type_id' => $containerType->id,
            'tare_weight_kg' => $this->parseWeight($data['TARE_WEIGHT']),
            'current_gross_weight_kg' => $this->parseWeight($data['GROSS_WEIGHT']),
            'cargo_weight_kg' => $this->parseWeight($data['NET_WEIGHT']),
            'max_gross_weight_kg' => 30000,
            'condition' => 'L', // Loaded
            'operational_status' => 'loaded',
            'current_port_id' => $bill->loading_port_id,
            'shipper_seal' => $data['SEAL_NO'] ?? null,
            'webservice_data' => json_encode([
                'parana_data' => [
                    'description' => $data['DESCRIPTION'] ?? null,
                    'imo_number' => $data['IMO_NUMBER'] ?? null,
                    'un_number' => $data['UN_NUMBER'] ?? null,
                    'temp_max' => $data['TEMP_MAX'] ?? null,
                    'temp_min' => $data['TEMP_MIN'] ?? null,
                    'packages' => $data['NUMBER_OF_PACKAGES'] ?? null,
                    'volume' => $data['VOLUME'] ?? null
                ]
            ]),
            'active' => true,
            'created_by_user_id' => auth()->id()
        ]);

        Log::info('Container creado', [
            'container_id' => $container->id,
            'number' => $container->container_number,
            'type' => $containerType->code,
            'operational_status' => $container->operational_status
        ]);

        return $container;

    }

    protected function findExistingContainerType(string $typeCode): ContainerType
    {
        // Mapear códigos PARANA a códigos estándar existentes
        $typeMapping = [
            '20DV' => '20GP',  // Dry Van -> General Purpose
            '40DV' => '40GP',  // Dry Van -> General Purpose  
            '20GP' => '20GP',  // Ya correcto
            '40GP' => '40GP',  // Ya correcto
            '40HC' => '40HC',  // Ya correcto
            '20RF' => '20RF',  // Refrigerado
            '40RF' => '40HC',  // No hay 40RF, usar 40HC
            '45HC' => '40HC',  // 45HC -> 40HC como fallback
        ];
        
        $mappedCode = $typeMapping[$typeCode] ?? '40HC'; // Default 40HC
        
        $type = ContainerType::where('code', $mappedCode)
                            ->where('active', true)
                            ->first();
        
        if (!$type) {
            // Si no existe el mapeado, usar el primer tipo activo disponible
            $type = ContainerType::where('active', true)->first();
            
            if (!$type) {
                throw new \Exception("No hay tipos de contenedor disponibles. Ejecute ContainerTypesSeeder.");
            }
            
            Log::warning('Tipo de contenedor no encontrado, usando fallback', [
                'requested' => $typeCode,
                'mapped' => $mappedCode,
                'fallback_used' => $type->code
            ]);
        }
        
        return $type;
    }

    protected function findOrCreatePort(?string $code, string $defaultCity = 'Puerto'): ?Port
    {
        if (!$code) {
            return null;
        }

        // DEBUG: Log del puerto que se está procesando
        Log::info('findOrCreatePort Debug', [
            'code' => $code,
            'defaultCity' => $defaultCity
        ]);

        $port = Port::where('code', $code)->first();
        
        if (!$port) {
            $port = Port::create([
                'code' => $code,
                'name' => $this->getPortNameFromCode($code),
                'country_id' => $this->getCountryIdFromCode($code),
                'city' => $this->getCityFromCode($code, $defaultCity), // AGREGADO: campo obligatorio
                'port_type' => 'river', // AGREGADO: campo obligatorio
                'port_category' => 'major', // AGREGADO: categoría
                'active' => true, // CORREGIDO: era is_active
            ]);
            
            Log::info('Puerto creado', [
                'port_id' => $port->id,
                'code' => $port->code,
                'city' => $port->city,
                'country_id' => $port->country_id
            ]);
        }
            
        return $port;
    }

    protected function getPortNameFromCode(string $code): string
    {
        // Mapeo de códigos conocidos a nombres
        $portNames = [
            'ARBUE' => 'Buenos Aires',
            'ARROS' => 'Rosario',
            'ARCAM' => 'Campana',
            'ARCON' => 'Concepción del Uruguay',
            'ARSFE' => 'Santa Fe',
            'ARPAR' => 'Paraná',
            'PYASU' => 'Asunción',
            'PYCON' => 'Concepción',
            'PYTVT' => 'Terminal Villeta',
            'PYVIL' => 'Villeta',
            'PYPIL' => 'Pilar',
            'BRRIG' => 'Rio Grande',
            'BRPOA' => 'Porto Alegre',
            'BRSFS' => 'Santos',
        ];

        return $portNames[$code] ?? "Puerto {$code}";
    }

    protected function getCountryIdFromCode(string $code): int
    {
        // Obtener ID del país basado en el prefijo del código
        $countryPrefix = substr($code, 0, 2);
        
        $countryMappings = [
            'AR' => 1, // Argentina
            'PY' => 2, // Paraguay
            'BR' => 3, // Brasil
            'UY' => 4, // Uruguay
        ];

        return $countryMappings[$countryPrefix] ?? 1; // Default Argentina
    }

    protected function getCityFromCode(string $code, string $defaultCity): string
    {
        $cityMap = [
            'ARBUE' => 'Buenos Aires',
            'ARROS' => 'Rosario', 
            'ARSFE' => 'Santa Fe',
            'PYASU' => 'Asunción',
            'PYTVT' => 'Villeta',
            'PYCON' => 'Concepción',
        ];
        
        return $cityMap[$code] ?? $defaultCity;
    }

    protected function findOrCreateClient(array $clientData, int $companyId): ?Client
    {
        if (empty($clientData['name'])) {
            return null;
        }

        // DEBUG: Log del cliente que se está procesando
        Log::info('findOrCreateClient Debug', [
            'client_name' => $clientData['name'],
            'client_address' => $clientData['address'] ?? 'N/A',
            'company_id' => $companyId
        ]);

        // Buscar cliente existente por nombre
        $taxId = $this->generateValidTaxId($clientData['name']);

        $client = Client::where('tax_id', $taxId)
                        ->where('country_id', 1)
                        ->first();

        // Si no existe por tax_id, buscar por nombre como fallback
        if (!$client) {
            $client = Client::where('legal_name', $clientData['name'])
                            ->where('country_id', 1)
                            ->first();
        }
        if ($client) {
            Log::info('Cliente existente encontrado', ['client_id' => $client->id]);
            return $client;
        }

        // Si encontramos cliente existente, usarlo
        if ($client) {
            Log::info('Cliente existente encontrado', [
                'client_id' => $client->id,
                'tax_id' => $client->tax_id
            ]);
            return $client;
        }

        // CORREGIDO: Generar tax_id de máximo 11 caracteres
        $taxId = $this->generateValidTaxId($clientData['name']);

        $client = Client::create([
            'legal_name' => $clientData['name'],
            'commercial_name' => $clientData['name'],
            'tax_id' => $taxId, // CORREGIDO: máximo 11 caracteres
            'country_id' => 1, // Argentina por defecto
            'document_type_id' => 1, // Tipo por defecto
            'status' => 'active',
            'address' => $clientData['address'] ?? null,
            'created_by_company_id' => $companyId,
            'verified_at' => now()
        ]);

        Log::info('Cliente creado', [
            'client_id' => $client->id,
            'tax_id' => $client->tax_id,
            'legal_name' => $client->legal_name
        ]);

        return $client;
    }

    protected function generateValidTaxId(string $clientName): string
    {
        // Generar tax_id único de máximo 11 caracteres
        $base = preg_replace('/[^0-9]/', '', $clientName); // Solo números del nombre
        if (strlen($base) < 5) {
            $base = str_pad($base, 5, '0'); // Rellenar con ceros
        }
        
        $timestamp = substr(time(), -6); // Últimos 6 dígitos del timestamp
        $taxId = $base . $timestamp;
        
        // Asegurar máximo 11 caracteres
        return substr($taxId, 0, 11);
    }

    protected function parseWeight(?string $weight): float
    {
        if (!$weight) return 0.0;
        return (float)preg_replace('/[^\d.]/', '', $weight);
    }

    protected function parseVolume(?string $volume): float
    {
        if (!$volume) return 0.0;
        return (float)preg_replace('/[^\d.]/', '', $volume);
    }

    public function validate(array $data): array
    {
        $errors = [];
        
        if (empty($data['BL_NUMBER'])) {
            $errors[] = 'Número de BL requerido';
        }
        
        if (empty($data['CONTAINER_NUMBER'])) {
            $errors[] = 'Número de contenedor requerido';
        }
        
        return $errors;
    }

    public function transform(array $data): array
    {
        return $data; // Ya está en formato correcto
    }

    public function getFormatInfo(): array
    {
        return [
            'name' => 'PARANA Excel',
            'description' => 'Formato tabular MAERSK con 73 columnas estándar',
            'extensions' => ['xlsx', 'xls'],
            'version' => '1.0',
            'parser_class' => self::class,
            'capabilities' => [
                'multiple_containers' => true,
                'hazmat_support' => true,
                'temperature_control' => true,
                'mlo_references' => true,
                'permit_numbers' => true
            ]
        ];
    }

    public function getDefaultConfig(): array
    {
        return [
            'skip_empty_rows' => true,
            'auto_create_ports' => true,
            'auto_create_clients' => true,
            'default_container_type' => '40HC',
            'default_freight_terms' => 'prepaid'
        ];
    }

    protected function findOrCreateVessel(string $bargeName, int $companyId): Vessel
    {
        $vessel = Vessel::where('registration_number', $bargeName)->first();
        
        if (!$vessel) {
            $vessel = Vessel::create([
            'name' => $bargeName,
            'registration_number' => $bargeName, // Campo obligatorio
            'vessel_type_id' => 1,
            'company_id' => $companyId,
            'flag_country_id' => 1,
            'length_meters' => 120.00, // Campo obligatorio
            'beam_meters' => 18.00,    // Campo obligatorio
            'draft_meters' => 3.50,    // Campo obligatorio
            'depth_meters' => 8.00,    // Campo obligatorio agregado
            'cargo_capacity_tons' => 1500.00,  // Campo obligatorio agregado
            'operational_status' => 'active',   // Campo obligatorio agregado
            'active' => true,
            'created_by_user_id' => auth()->id()  // Campo obligatorio agregado
        ]);
        }
        
        return $vessel;
    }

    protected function extractVesselDataFromExcel($worksheet): array
    {
        // Extraer datos REALES de la barcaza desde las primeras filas del Excel
        $bargeId = $worksheet->getCell('L1')->getCalculatedValue(); // BARGE_ID
        $bargeName = $worksheet->getCell('M1')->getCalculatedValue(); // BARGE_NAME como PAR13001
        
        // Si no hay nombre en M1, buscar en otras filas
        if (empty($bargeName)) {
            for ($row = 2; $row <= 10; $row++) {
                $testName = $worksheet->getCell('M' . $row)->getCalculatedValue();
                if (!empty($testName) && preg_match('/^PAR\d+/', $testName)) {
                    $bargeName = $testName;
                    break;
                }
            }
        }
        
        // Si aún no encontramos el nombre, usar valor por defecto pero loggearlo
        if (empty($bargeName)) {
            Log::warning('PARANA: No se encontró nombre de barcaza en Excel, usando valor por defecto');
            $bargeName = 'PAR13001';
        }
        
        return [
            'barge_id' => $bargeId,
            'barge_name' => $bargeName,
            'registration_number' => $bargeName, // Usar el nombre como registration_number
            'cargo_capacity_tons' => 1500.00, // Capacidad típica barcaza Paraná
            'container_capacity' => 64, // Capacidad típica contenedores
        ];
    }

}