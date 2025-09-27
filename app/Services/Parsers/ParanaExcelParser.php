<?php

namespace App\Services\Parsers;

use App\Contracts\ManifestParserInterface;
use App\ValueObjects\ManifestParseResult;
use App\Models\Voyage;
use App\Models\Shipment;
use App\Models\ShipmentItem;
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
use App\Models\ManifestImport;
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
        // Información de la empresa
        'A' => 'LOCATION_NAME',           // MAERSK LINE ARGENTINA S.A
        'B' => 'ADDRESS_LINE1',           
        'C' => 'ADDRESS_LINE2',           
        'D' => 'ADDRESS_LINE3',           
        'E' => 'CITY',                    
        'F' => 'ZIP',                     
        'G' => 'COUNTRY_NAME',            
        'H' => 'TELEPHONE_NO',            
        'I' => 'FAX_NO',                  
        'J' => 'EMAIL_ID',                
        'K' => 'MANIFEST_TYPE',           // CM = Consolidado Marítimo
        'L' => 'BARGE_ID',                
        'M' => 'BARGE_NAME',              // PAR13001
        'N' => 'VOYAGE_NO',               // V022NB
        'O' => 'BL_NUMBER',               // Número BL
        'P' => 'BL_DATE',                 
        'Q' => 'POL',                     // Puerto carga: ARBUE
        'R' => 'POL_TERMINAL',            
        'S' => 'POD',                     // Puerto descarga: PYTVT
        'T' => 'POD_TERMINAL',            
        'U' => 'FREIGHT_TERMS',           
        'V' => 'SHIPPER_NAME',            
        'W' => 'SHIPPER_ADDRESS1',        // ← AQUÍ ESTÁ EL CUIT: "CUIT: 30688415531"
        'X' => 'SHIPPER_ADDRESS2',        
        'Y' => 'SHIPPER_ADDRESS3',        
        'Z' => 'SHIPPER_CITY',            
        'AA' => 'SHIPPER_ZIP',            
        'AB' => 'SHIPPER_COUNTRY',        
        'AC' => 'SHIPPER_PHONE',          
        'AD' => 'SHIPPER_FAX',            
        'AE' => 'CONSIGNEE_NAME',         // ← Nombre consignatario
        'AF' => 'CONSIGNEE_ADDRESS1',     // ← Solo dirección, NO hay CUIT/RUC
        'AG' => 'CONSIGNEE_ADDRESS2',     
        'AH' => 'CONSIGNEE_ADDRESS3',     
        'AI' => 'CONSIGNEE_CITY',         
        'AJ' => 'CONSIGNEE_ZIP',          
        'AK' => 'CONSIGNEE_COUNTRY',      
        'AL' => 'CONSIGNEE_PHONE',        
        'AM' => 'CONSIGNEE_FAX',          
        'AN' => 'NOTIFY_PARTY_NAME',      // ← Nombre notificatario
        'AO' => 'NOTIFY_PARTY_ADDRESS1',  // ← Solo dirección, NO hay RUC
        'AP' => 'NOTIFY_PARTY_ADDRESS2',  
        'AQ' => 'NOTIFY_PARTY_ADDRESS3',  
        'AR' => 'NOTIFY_PARTY_CITY',      
        'AS' => 'NOTIFY_PARTY_ZIP',       
        'AT' => 'NOTIFY_PARTY_COUNTRY',   
        'AU' => 'NOTIFY_PARTY_PHONE',     
        'AV' => 'NOTIFY_PARTY_FAX',       
        'AW' => 'PFD',                    
        'AX' => 'CONTAINER_NUMBER',       
        'AY' => 'CONTAINER_TYPE',         // 40HC, 20DV, etc.
        'AZ' => 'CONTAINER_STATUS',       
        'BA' => 'SEAL_NO',                
        'BB' => 'PACK_TYPE',              // ← Tipo empaque real
        'BC' => 'NUMBER_OF_PACKAGES',     
        'BD' => 'GROSS_WEIGHT',           
        'BE' => 'NET_WEIGHT',             
        'BF' => 'TARE_WEIGHT',            
        'BG' => 'VOLUME',                 // ← Volumen
        'BH' => 'REMARKS',                
        'BI' => 'MARKS_DESCRIPTION',      
        'BJ' => 'DESCRIPTION',            
        'BK' => 'IMO_NUMBER',             
        'BL' => 'UN_NUMBER',              
        'BM' => 'FLASH_POINT',            
        'BN' => 'TEMP_MAX',               
        'BO' => 'TEMP_MIN',               
        'BP' => 'NCM',                    
        'BQ' => 'REMARKS1',               
        'BR' => 'REMARKS2',               
        'BS' => 'REMARKS3',               
        'BT' => 'MLO_BL_NR',              // ← MBL (Madre)
        'BU' => 'PERMISO'                 // ← Permiso de Embarque
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

    public function parse(string $filePath, array $options = []): ManifestParseResult
    {
        $startTime = microtime(true);
        Log::info('Starting PARANA Excel parsing', ['file' => $filePath]);

        try {
            $importRecord = $this->createImportRecord($filePath, $options);
            $reader = IOFactory::createReaderForFile($filePath);
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($filePath);
            $worksheet = $spreadsheet->getActiveSheet();

            $highestRow = $worksheet->getHighestRow();
            Log::info('PARANA file loaded', ['rows' => $highestRow]);

            // Extraer información del Viaje de la primera fila
            $voyageData = $this->extractVoyageData($worksheet);
            $vesselData = $this->extractVesselDataFromExcel($worksheet);
            $voyageData = array_merge($voyageData, $vesselData);
                        
            // Crear Viaje
            $voyage = $this->createVoyage($voyageData, $options);
            
            // Crear shipment principal
            $vessel = $this->findOrCreateVessel($voyageData['barge_name'] ?? 'PAR13001', $voyage->company_id);
            $shipment = $this->createShipment($voyage, $vessel, $voyageData);       

            // Procesar filas de datos (ignorar header si existe)
            $containers = [];
            $bills = [];
            $items = [];
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
                    
                   // VALIDACIÓN: Verificar si ya existe BL duplicado
                    $existingBL = BillOfLading::where('bill_number', $blNumber)->first();

                    if ($existingBL) {
                        throw new \Exception("Ya existe un conocimiento de embarque con número: {$blNumber}.");
                    }

                    $bill = $this->createBillOfLading($shipment, $rowData);
                    $bills[] = $bill;
                    $processedBLs[$blNumber] = $bill;
                } else {
                    $bill = $processedBLs[$blNumber];
                }

                // Crear contenedor para esta fila
                if (!empty($rowData['CONTAINER_NUMBER'])) {
                    $container = $this->createContainer($bill, $rowData);
                    $containers[] = $container;
                }

                // AGREGAR: Crear ShipmentItem para cada fila
                $shipmentItem = $this->createShipmentItem($bill, $rowData);
                if ($shipmentItem) {
                    $items[] = $shipmentItem;
                    Log::info('ShipmentItem created', ['item_id' => $shipmentItem->id]);
                }
            }

            Log::info('PARANA parsing completed', [
                'voyage_id' => $voyage->id,
                'bills_count' => count($bills),
                'containers_count' => count($containers),
                'items_count' => count($items)
            ]);

            // NUEVO: Registrar objetos creados y completar importación
            $this->completeImportRecord($importRecord, $voyage, $bills, $containers, $items, $startTime);

            return ManifestParseResult::success(
                voyage: $voyage,
                shipments: [$shipment],
                containers: $containers,
                billsOfLading: $bills,
                statistics: [
                    'processed_rows' => $highestRow - 1,
                    'unique_bills' => count($bills),
                    'total_containers' => count($containers),
                    'import_id' => $importRecord->id // Agregar ID del registro
                ]
            );

        } catch (Exception $e) {
            Log::error('PARANA parsing failed', [
                'file' => $filePath,
                'error' => $e->getMessage()
            ]);

            // NUEVO: Marcar importación como fallida
            if (isset($importRecord)) {
                $processingTime = microtime(true) - $startTime;
                $importRecord->markAsFailed([$e->getMessage()], [
                    'processing_time_seconds' => round($processingTime, 2),
                    'errors_count' => 1
                ]);
            }

            return ManifestParseResult::failure([
                'Error al procesar archivo PARANA: ' . $e->getMessage()
            ]);
        }
    }

    protected function extractVoyageData($worksheet): array
    {
        // Detectar si la fila 1 es encabezado (por el contenido de A1)
        $a1 = trim((string)$worksheet->getCell('A1')->getCalculatedValue());
        $isHeaderRow = in_array(mb_strtoupper($a1), ['LOCATION NAME','COMPANY','LOCATION'], true);

        // Si la fila 1 es encabezado, los datos reales empiezan en la 2
        $row = $isHeaderRow ? 2 : 1;

        return [
            'company_name'   => $worksheet->getCell('A' . $row)->getCalculatedValue() ?: 'MAERSK LINE ARGENTINA S.A',
            'barge_name'     => $worksheet->getCell('M' . $row)->getCalculatedValue() ?: 'PAR13001',
            'voyage_number'  => $worksheet->getCell('N' . $row)->getCalculatedValue() ?: 'V022NB',
            'POL'            => $worksheet->getCell('Q' . $row)->getCalculatedValue() ?: 'ARBUE',
            'POD'            => $worksheet->getCell('S' . $row)->getCalculatedValue() ?: 'PYTVT',
            'POL_terminal'   => $worksheet->getCell('R' . $row)->getCalculatedValue(),
            'POD_terminal'   => $worksheet->getCell('T' . $row)->getCalculatedValue() ?: 'TERPORT VILLETA',
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

    protected function createVoyage(array $data, array $options = []): Voyage
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

        $originPort = $this->findOrCreatePort($data['POL'], 'Buenos Aires');
        $destPort = $this->findOrCreatePort($data['POD'], 'Terminal Villeta');
        
        // CORREGIDO: Buscar o crear vessel con campos obligatorios
        // USAR vessel seleccionado en lugar de crear fake
        $vesselId = $options['vessel_id'] ?? null;
        if ($vesselId) {
            $vessel = Vessel::find($vesselId);
            if (!$vessel) {
                throw new \Exception("Vessel con ID {$vesselId} no encontrado");
            }
        } else {
            // Fallback: buscar o crear vessel (para compatibilidad)
            $vessel = $this->findOrCreateVessel($data['barge_name'] ?? 'PAR13001', $companyId);
        }

        $voyageNumber = 'PARANA-' . now()->format('YmdHis') . '-' . uniqid();

        // VALIDACIÓN: Verificar si ya existe Viaje con este número
        $existingVoyage = Voyage::where('voyage_number', $voyageNumber)->first();
        if ($existingVoyage) {
            throw new \Exception("Ya existe un viaje con número: {$voyageNumber}.");
        }

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

        // MEJORADO: Parsear datos mezclados del consignatario
        $consigneeData = [
            'name' => $data['CONSIGNEE_NAME'] ?? 'Consignee Unknown', 
            'address' => $data['CONSIGNEE_ADDRESS1'] ?? null,
            'phone' => $data['CONSIGNEE_PHONE'] ?? null
        ];

        // Si la dirección es muy larga, probablemente tiene datos mezclados
        if (isset($consigneeData['address']) && strlen($consigneeData['address']) > 100) {
            $parsed = $this->parseConsigneeMixedData($consigneeData['address']);
            $consigneeData = array_merge($consigneeData, $parsed);
        }

        $consignee = $this->findOrCreateClient($consigneeData, $shipment->voyage->company_id);

        // Obtener puertos
        $loadingPort = $this->findOrCreatePort($data['POL'] ?? 'ARBUE', 'Buenos Aires');
        $dischargePort = $this->findOrCreatePort($data['POD'] ?? 'PYTVT', 'Villeta');

        // VALIDACIÓN: Verificar si ya existe bill of lading con este número
        $billNumber = $data['BL_NUMBER'];
        $existingBill = BillOfLading::where('bill_number', $billNumber)->first();
        if ($existingBill) {
            throw new \Exception("Ya existe un conocimiento de embarque con número: {$billNumber}.");
        }

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
            'primary_cargo_type_id' => $this->determinateCargoTypeId($data),
            'primary_packaging_type_id' => $this->determinatePackagingTypeId($data),
            
            // AGREGADO: Campos adicionales con valores por defecto
            'gross_weight_kg' => $this->parseWeight($data['GROSS_WEIGHT']),
            'net_weight_kg' => $this->parseWeight($data['NET_WEIGHT']),
            'total_packages' => intval($data['NUMBER_OF_PACKAGES'] ?? 1),  // CORREGIDO: era PACKAGE_COUNT
            'volume_m3' => $this->parseVolume($data['VOLUME']),
            'master_bill_number' => $data['MLO_BL_NR'] ?? null, // MLO BL Number agregado
            'permiso_embarque' => $data['PERMISO'] ?? null, // Permiso de embarque agregado
            'commodity_code' => $data['NCM'] ?? null, // Código NCM agregado
            'cargo_marks' => !empty($data['MARKS_DESCRIPTION']) && $data['MARKS_DESCRIPTION'] !== 'N/A' 
                ? $data['MARKS_DESCRIPTION'] 
                : 'S/M', // S/M si no hay marcas 
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
       // VALIDACIÓN: Verificar si ya existe contenedor con este número
        $existing = Container::where('container_number', $data['CONTAINER_NUMBER'])->first();
        if ($existing) {
            throw new \Exception("Ya existe un contenedor con número: {$data['CONTAINER_NUMBER']}.");
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
            'condition' => 'L', // Loaded - valor fijo válido
            'shipper_seal' => $data['SEAL_NO'] ?? null,
            'operational_status' => 'loaded',
            'current_port_id' => $bill->loading_port_id,
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
            'ARBUE' => 'Puerto de Buenos Aires',           // ✅ Más descriptivo
            'ARROS' => 'Puerto de Rosario',
            'ARCAM' => 'Puerto de Campana',
            'ARCON' => 'Puerto de Concepción del Uruguay',
            'ARSFE' => 'Puerto de Santa Fe',
            'ARPAR' => 'Puerto de Paraná',
            'PYASU' => 'Puerto de Asunción',
            'PYCON' => 'Puerto de Concepción',
            'PYTVT' => 'Terminal Villeta',                 // ✅ Mantener nombre real
            'PYVIL' => 'Puerto de Villeta',
            'PYPIL' => 'Puerto de Pilar',
            'BRRIG' => 'Puerto de Rio Grande',
            'BRPOA' => 'Puerto de Porto Alegre',
            'BRSFS' => 'Puerto de Santos',
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
            'ARPAR' => 'Paraná',
            'PYASU' => 'Asunción',
            'PYTVT' => 'Villeta',        // ✅ Consistente con Terminal Villeta
            'PYCON' => 'Concepción',
            'PYVIL' => 'Villeta',
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

        // Determinar país del cliente ANTES de buscar
        $clientCountryId = $this->determineClientCountry($clientData, $companyId);

        $client = Client::where('tax_id', $taxId)
                        ->where('country_id', $clientCountryId)  // ✅ País dinámico
                        ->first();

        // Si no existe por tax_id, buscar por nombre como fallback
        if (!$client) {
            $client = Client::where('legal_name', $clientData['name'])
                            ->where('country_id', $clientCountryId)  // ✅ País dinámico
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
            'country_id' => $clientCountryId,
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

    protected function createShipmentItem(BillOfLading $bill, array $data): ?\App\Models\ShipmentItem
    {
        // Generar line_number único para este bill_of_lading
        $nextLineNumber = \App\Models\ShipmentItem::where('bill_of_lading_id', $bill->id)
            ->max('line_number') + 1;
        
        if ($nextLineNumber < 1) {
            $nextLineNumber = 1;
        }

        try {
            $shipmentItem = \App\Models\ShipmentItem::create([
                'bill_of_lading_id' => $bill->id,
                'line_number' => $nextLineNumber,
                'item_description' => $data['DESCRIPTION'] ?? 'Mercadería general',
                'package_quantity' => intval($data['NUMBER_OF_PACKAGES'] ?? 1),
                'gross_weight_kg' => $this->parseWeight($data['GROSS_WEIGHT']),
                'net_weight_kg' => $this->parseWeight($data['NET_WEIGHT']),
                'cargo_type_id' => $this->determinateCargoTypeId($data),
                'packaging_type_id' => $this->determinatePackagingTypeId($data),                
                'volume_m3' => $this->parseVolume($data['VOLUME']),
                'commodity_code' => $data['NCM'] ?? null,                             // BP
                'cargo_marks' => $data['MARKS_DESCRIPTION'] ?? null,                  // BI
                'created_by_user_id' => auth()->id()
            ]);

            Log::info('ShipmentItem created successfully', [
                'item_id' => $shipmentItem->id,
                'line_number' => $shipmentItem->line_number,
                'bill_id' => $bill->id
            ]);

            // Crear relación con contenedor si existe
            if (!empty($data['CONTAINER_NUMBER'])) {
                $container = Container::where('container_number', $data['CONTAINER_NUMBER'])->first();
                
                if ($container) {
                    $shipmentItem->containers()->attach($container->id, [
                        'package_quantity' => intval($data['NUMBER_OF_PACKAGES'] ?? 1),
                        'gross_weight_kg' => $this->parseWeight($data['GROSS_WEIGHT']),
                        'net_weight_kg' => $this->parseWeight($data['NET_WEIGHT']),
                        'volume_m3' => $this->parseVolume($data['VOLUME']),
                    ]);
                }
            }

            return $shipmentItem;
        } catch (\Exception $e) {
            Log::error('Error creating ShipmentItem', [
                'bill_id' => $bill->id,
                'data' => $data,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Crear registro de importación - NUEVO
     */
    protected function createImportRecord(string $filePath, array $options): ManifestImport
    {
        $fileName = basename($filePath);
        $fileSize = filesize($filePath);
        $fileHash = ManifestImport::generateFileHash($filePath);
        
        $user = auth()->user();
        $companyId = $user->company_id ?: ($user->userable_type === 'App\Models\Company' ? $user->userable_id : null);
        
        return ManifestImport::createForImport([
            'company_id' => $companyId,
            'user_id' => $user->id,
            'file_name' => $fileName,
            'file_format' => 'parana',
            'file_size_bytes' => $fileSize,
            'file_hash' => $fileHash,
            'parser_config' => [
                'parser_class' => self::class,
                'options' => $options,
                'vessel_id' => $options['vessel_id'] ?? null
            ]
        ]);
    }

    /**
     * Completar registro de importación - NUEVO
     */
    protected function completeImportRecord(
        ManifestImport $importRecord, 
        Voyage $voyage, 
        array $bills, 
        array $containers,
        array $items,
        float $startTime
    ): void {
        $processingTime = microtime(true) - $startTime;
        
        // Registrar IDs de objetos creados
        $createdObjects = [
            'voyages' => [$voyage->id],
            'shipments' => [$voyage->shipments()->first()->id ?? null],
            'bills' => array_map(fn($bill) => $bill->id, $bills),
            'containers' => array_map(fn($container) => $container->id, $containers),
            'items' => array_map(fn($item) => $item->id, $items)                                                                                    
        ];
        
        // Filtrar nulls
        $createdObjects = array_map(fn($ids) => array_filter($ids), $createdObjects);
        
        $importRecord->recordCreatedObjects($createdObjects);
        $importRecord->markAsCompleted([
            'voyage_id' => $voyage->id,
            'processing_time_seconds' => round($processingTime, 2),
            'notes' => 'Importación PARANA Excel completada exitosamente'
        ]);
        
        Log::info('PARANA import record completed', [
            'import_id' => $importRecord->id,
            'processing_time' => round($processingTime, 2) . 's'
        ]);
    }

    protected function parseConsigneeMixedData(string $mixedData): array
    {
        // Parsear RUC/ID fiscal
        $ruc = null;
        if (preg_match('/RUC[:\s]*([0-9\-\s]+)/i', $mixedData, $matches)) {
            $ruc = trim(str_replace(['-', ' '], '', $matches[1]));
        }

        // Parsear dirección (primera parte antes de TEL/RFC/EMAIL)
        $address = preg_replace('/\s*(TEL|RFC|EMAIL|RUC).*$/i', '', $mixedData);
        $address = trim(str_replace($ruc ?? '', '', $address));
        
        // Limpiar direcciones muy largas
        if (strlen($address) > 200) {
            $address = substr($address, 0, 200);
        }

        // Parsear teléfono
        $phone = null;
        if (preg_match('/TEL[:\s]*([+0-9\-\s]+)/i', $mixedData, $matches)) {
            $phone = trim($matches[1]);
        }

        // Parsear email
        $email = null;
        if (preg_match('/([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})/i', $mixedData, $matches)) {
            $email = trim($matches[1]);
        }

        return [
            'tax_id' => $ruc,
            'address' => $address,
            'phone' => $phone,
            'email' => $email
        ];
    }

    // determinar el tipo de carga 
    protected function determinateCargoTypeId(array $data): int
    {
        // Si tiene número de contenedor, es carga contenerizada
        if (!empty($data['CONTAINER_NUMBER'])) {
            // Buscar tipo "Contenedores" exacto
            $containerCargoType = \App\Models\CargoType::where('name', 'Contenedores')
                                                    ->where('active', 1)
                                                    ->first();
            
            // También intentar con otros nombres posibles
            if (!$containerCargoType) {
                $containerCargoType = \App\Models\CargoType::where('name', 'LIKE', '%Container%')
                                                        ->orWhere('code', 'CON001')
                                                        ->where('active', 1)
                                                        ->first();
            }
            
            if ($containerCargoType) {
                return $containerCargoType->id;
            }
        }
        
        // Fallback: Carga General
        return 1;
    }

    //determinar el tipo de embalaje
    protected function determinatePackagingTypeId(array $data): int
    {
        $packType = $data['PACK_TYPE'] ?? null;
        
        if ($packType) {
            // Mapear tipos del Excel a tipos de la BD
            $packTypeMap = [
                'PACKAGE' => 'Paquete',
                'ROLLS' => 'Rollo', 
                'BOX' => 'Caja',
                'BAGS' => 'Bolsa'
            ];
            
            $mappedType = $packTypeMap[$packType] ?? $packType;
            
            $packagingType = \App\Models\PackagingType::where('name', 'LIKE', '%' . $mappedType . '%')
                                                    ->where('active', 1)
                                                    ->first();
            
            if ($packagingType) {
                return $packagingType->id;
            }
        }
        
        // Fallback
        return 1;
    }

    protected function findContainerTypeId(string $containerType): ?\App\Models\ContainerType
    {
        // Mapear tipos del Excel a códigos de la BD
        $typeMapping = [
            '20GP' => '20GP',
            '20DV' => '20GP', 
            '40GP' => '40GP',
            '40DV' => '40GP',
            '40HC' => '40HC',
            '20RF' => '20RF',
            '20OT' => '20OT',
        ];
        
        $mappedCode = $typeMapping[$containerType] ?? $containerType;
        
        return \App\Models\ContainerType::where('code', $mappedCode)
                                    ->where('active', true)
                                    ->first();
    }

    /**
     * Determinar país del cliente basado en contexto
     */
    protected function determineClientCountry(array $clientData, int $companyId): int
    {
        // Analizar dirección del cliente
        $address = strtoupper($clientData['address'] ?? '');
        
        // Si tiene RUC o menciona Paraguay -> Paraguay (2)
        if (str_contains($address, 'RUC') || 
            str_contains($address, 'PARAGUAY') || 
            str_contains($address, 'ASUNCION') ||
            str_contains($address, 'VILLETA')) {
            return 2; // Paraguay
        }
        
        // Si tiene CUIT o menciona Argentina -> Argentina (1)  
        if (str_contains($address, 'CUIT') || 
            str_contains($address, 'ARGENTINA') ||
            str_contains($address, 'BUENOS AIRES')) {
            return 1; // Argentina
        }
        
        // Si menciona Brasil -> Brasil (3)
        if (str_contains($address, 'BRASIL') || 
            str_contains($address, 'BRAZIL')) {
            return 3; // Brasil
        }
        
        // Default: Argentina (origen típico del shipper PARANA)
        return 1;
    }

    protected function scanValueLikeColumns(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet): array
{
    $rows = $sheet->toArray(null, true, true, true);
    $headersRow = $rows[1] ?? [];
    $candidates = [];

    // patrones típicos de valor declarado / monto
    $needles = [
        'VALUE', 'DECLARED', 'INVOICE', 'CIF', 'FOB', 'AMOUNT', 'USD', 'U$S', 'U$D', 'TOTAL'
    ];

    // 1) detectar por encabezado
    foreach ($headersRow as $col => $title) {
        $t = mb_strtoupper(trim((string)$title));
        foreach ($needles as $n) {
            if ($t !== '' && str_contains($t, $n)) {
                $candidates[$col] = $t;
                break;
            }
        }
    }

    // 2) si no hay headers claros, buscar por “formas” en las primeras 30 filas
    if (empty($candidates)) {
        for ($r = 1; $r <= min(30, count($rows)); $r++) {
            foreach (($rows[$r] ?? []) as $col => $val) {
                $v = (string)$val;
                // heurística: $/USD/ números grandes con separadores
                if (preg_match('/(\$|USD|U\$S|U\$D)/i', $v) || preg_match('/\d{1,3}([.,]\d{3})+([.,]\d{2})?/', $v)) {
                    $candidates[$col] = $headersRow[$col] ?? '(sin header)';
                }
            }
        }
    }

    // 3) sample: devolvemos hasta 10 valores no vacíos por columna candidata
    $samples = [];
    foreach ($candidates as $col => $title) {
        $vals = [];
        $limit = 10;
        for ($r = 2; $r <= min(count($rows), 200) && count($vals) < $limit; $r++) {
            $cell = trim((string)($rows[$r][$col] ?? ''));
            if ($cell !== '') $vals[] = $cell;
        }
        $samples[] = [
            'column' => $col,
            'header' => $title,
            'examples' => $vals,
        ];
    }

    return $samples;
}

}