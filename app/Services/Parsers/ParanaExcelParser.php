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
use App\Models\Country;
use App\Models\DocumentType;
use App\Models\Vessel;
use App\Services\Parsers\Concerns\ExtractsEmbeddedTaxId;
use App\Services\Parsers\Concerns\EnsuresUniqueVoyageNumber;
use App\Services\Parsers\Concerns\ResolvesClientAddresses;
use App\Models\ContainerType;
use App\Services\Parsers\Concerns\ResolvesPorts;
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
    use ExtractsEmbeddedTaxId;
    use EnsuresUniqueVoyageNumber;
    use ResolvesClientAddresses;
    use ResolvesPorts;

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

            \Illuminate\Support\Facades\DB::beginTransaction();

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
            $vessel = Vessel::findOrFail($voyage->lead_vessel_id);
            $shipment = $this->createShipment(
                $voyage,
                $vessel,
                $voyageData
            );

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

            \Illuminate\Support\Facades\DB::commit();

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
            if (
                \Illuminate\Support\Facades\DB::transactionLevel() > 0
            ) {
                \Illuminate\Support\Facades\DB::rollBack();
            }

            // Viaje ya existente (bloqueo global de duplicado): mensaje amable, no SQL crudo.
            if (strpos($e->getMessage(), 'voyages_voyage_number_unique') !== false) {
                if (isset($importRecord)) {
                    $importRecord->markAsFailed([
                        'Este archivo ya fue importado anteriormente. El viaje ya existe en el sistema y no se duplicó ningún dato.'
                    ], ['errors_count' => 1]);
                }
                return ManifestParseResult::failure([
                    'Este archivo ya fue importado anteriormente. El viaje ya existe en el sistema y no se duplicó ningún dato. Si necesita importarlo de nuevo, primero revierta la importación desde el Historial de Importaciones.'
                ]);
            }

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
        $a1 = trim(
            (string) $worksheet->getCell('A1')->getCalculatedValue()
        );

        $isHeaderRow = in_array(
            mb_strtoupper($a1),
            ['LOCATION NAME', 'COMPANY', 'LOCATION'],
            true
        );

        $row = $isHeaderRow ? 2 : 1;

        return [
            'company_name' => $this->nullableSourceText(
                $worksheet->getCell('A' . $row)->getCalculatedValue()
            ),
            'barge_name' => $this->nullableSourceText(
                $worksheet->getCell('M' . $row)->getCalculatedValue()
            ),
            'voyage_number' => $this->requireParanaSourceText(
                $worksheet->getCell('N' . $row)->getCalculatedValue(),
                'VOYAGE_NO'
            ),
            'POL' => $this->requireParanaSourceText(
                $worksheet->getCell('Q' . $row)->getCalculatedValue(),
                'POL'
            ),
            'POD' => $this->requireParanaSourceText(
                $worksheet->getCell('S' . $row)->getCalculatedValue(),
                'POD'
            ),
            'POL_terminal' => $this->nullableSourceText(
                $worksheet->getCell('R' . $row)->getCalculatedValue()
            ),
            'POD_terminal' => $this->nullableSourceText(
                $worksheet->getCell('T' . $row)->getCalculatedValue()
            ),
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


    protected function createVoyage(
        array $data,
        array $options = []
    ): Voyage {
        $user = auth()->user();

        if (!$user) {
            throw new \Exception(
                'PARANA requiere un usuario autenticado.'
            );
        }

        $companyId = $user->company_id
            ?: (
                $user->userable_type === 'App\Models\Company'
                    ? $user->userable_id
                    : null
            );

        if (!$companyId) {
            throw new \Exception(
                "Usuario {$user->id} no tiene empresa asignada."
            );
        }

        $originPort = $this->resolvePortStrict(
            $this->requireParanaSourceText(
                $data['POL'] ?? null,
                'POL'
            )
        );

        $destPort = $this->resolvePortStrict(
            $this->requireParanaSourceText(
                $data['POD'] ?? null,
                'POD'
            )
        );

        $vesselId = $options['vessel_id'] ?? null;

        if (!$vesselId) {
            throw new \Exception(
                'PARANA requiere vessel_id seleccionado.'
            );
        }

        $vessel = Vessel::find($vesselId);

        if (!$vessel) {
            throw new \Exception(
                "Vessel con ID {$vesselId} no encontrado."
            );
        }

        if ((int) $vessel->company_id !== (int) $companyId) {
            throw new \Exception(
                'El vessel seleccionado no pertenece '
                . 'a la empresa importadora.'
            );
        }

        $voyageNumber = $this->requireParanaSourceText(
            $data['voyage_number'] ?? null,
            'VOYAGE_NO'
        );

        $this->guardVoyageNumberIsFree($voyageNumber);

        $timing = $this->buildParanaVoyageTiming();

        return Voyage::create([
            'company_id' => $companyId,
            'voyage_number' => $voyageNumber,
            'origin_port_id' => $originPort->id,
            'destination_port_id' => $destPort->id,
            'lead_vessel_id' => $vessel->id,
            'origin_country_id' => $originPort->country_id,
            'destination_country_id' => $destPort->country_id,
            'departure_date' => $timing['departure_date'],
            'estimated_arrival_date' =>
                $timing['estimated_arrival_date'],
            'status' => 'planning',
            'voyage_type' => $this->determineVoyageType($data),
            'cargo_type' => $this->determineCargoType(
                $data,
                $originPort,
                $destPort
            ),
            'created_by_user_id' => auth()->id(),
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


    protected function determineCargoType(
        array $data,
        Port $originPort,
        Port $destPort
    ): string {
        if (
            (int) $originPort->country_id
            === (int) $destPort->country_id
        ) {
            return 'cabotage';
        }

        if (
            !empty($data['transshipment_port'])
            || str_contains(
                strtoupper(
                    (string) ($data['MANIFEST_TYPE'] ?? '')
                ),
                'TRANSIT'
            )
        ) {
            return 'transit';
        }

        return 'export';
    }



    protected function createShipment(
        Voyage $voyage,
        Vessel $vessel,
        array $data
    ): Shipment {
        return Shipment::create([
            'voyage_id' => $voyage->id,
            'vessel_id' => $vessel->id,
            'shipment_number' =>
                'PARANA-' . $voyage->voyage_number,
            'sequence_in_voyage' => 1,
            'vessel_role' => 'single',
            'cargo_capacity_tons' =>
                $vessel->cargo_capacity_tons,
            'container_capacity' =>
                $vessel->container_capacity ?? 0,
            'status' => 'planning',
            'active' => true,
            'created_by_user_id' => auth()->id(),
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

        // Resolver puertos antes de tocar clientes.
        $loadingPort = $this->resolvePortStrict(
            $this->requireParanaSourceText(
                $data['POL'] ?? null,
                'POL'
            )
        );

        $dischargePort = $this->resolvePortStrict(
            $this->requireParanaSourceText(
                $data['POD'] ?? null,
                'POD'
            )
        );

        $shipper = $this->findOrCreateClient([
            'name' => $this->requireParanaSourceText(
                $data['SHIPPER_NAME'] ?? null,
                'SHIPPER_NAME'
            ),
            'address' => $this->buildPartyAddress($data, 'SHIPPER'),
            'country' => $data['SHIPPER_COUNTRY'] ?? null,
            'phone' => $data['SHIPPER_PHONE'] ?? null,
        ], $shipment->voyage->company_id, (int) $loadingPort->country_id);

        $consignee = $this->findOrCreateClient([
            'name' => $this->requireParanaSourceText(
                $data['CONSIGNEE_NAME'] ?? null,
                'CONSIGNEE_NAME'
            ),
            'address' => $this->buildPartyAddress($data, 'CONSIGNEE'),
            'country' => $data['CONSIGNEE_COUNTRY'] ?? null,
            'phone' => $data['CONSIGNEE_PHONE'] ?? null,
        ], $shipment->voyage->company_id, (int) $dischargePort->country_id);

        // VALIDACIÓN: Verificar si ya existe bill of lading con este número
        $billNumber = $data['BL_NUMBER'];
        $existingBill = BillOfLading::where('bill_number', $billNumber)->first();
        if ($existingBill) {
            throw new \Exception("Ya existe un conocimiento de embarque con número: {$billNumber}.");
        }

        $billDates = $this->buildParanaBillDates(
            $data['BL_DATE'] ?? null
        );

        $bill = BillOfLading::create([
            'shipment_id' => $shipment->id,
            'bill_number' => $data['BL_NUMBER'],
            
            // AGREGADO: Campos de fecha obligatorios
            'bill_date' => $billDates['bill_date'],
            'loading_date' => $billDates['loading_date'],
            
            // AGREGADO: Descripción de carga obligatoria
            'cargo_description' =>
                $this->requireParanaSourceText(
                    $data['DESCRIPTION'] ?? null,
                    'DESCRIPTION'
                ),
            
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
            'total_packages' =>
                $this->parseParanaPackageQuantity(
                    $data['NUMBER_OF_PACKAGES'] ?? null
                ),
            'volume_m3' => $this->parseVolume($data['VOLUME']),
            'master_bill_number' => $data['MLO_BL_NR'] ?? null, // MLO BL Number agregado
            'permiso_embarque' => $data['PERMISO'] ?? null, // Permiso de embarque agregado
            'commodity_code' => $data['NCM'] ?? null, // Código NCM agregado
            'cargo_marks' => $this->normalizeParanaCargoMarks(
                $data['MARKS_DESCRIPTION'] ?? null
            ),
]);

        Log::info('BillOfLading creado', [
            'bill_id' => $bill->id,
            'bill_number' => $bill->bill_number,
            'bill_date' => $bill->bill_date?->toDateString(),
            'loading_date' => $bill->loading_date?->toDateString()
        ]);

        // Dirección del cliente: persistir en ficha (cliente nuevo/sin dirección)
        // o guardar dirección específica del conocimiento (cliente existente con dirección distinta).
        $shipperAddr = $data['SHIPPER_ADDRESS1'] ?? null;
        $this->persistClientAddress($shipper, $shipperAddr);
        if ($c = $this->resolveSpecificAddress($shipper, $shipperAddr, 'shipper')) {
            $bill->specificContacts()->create($c);
        }

        $consigneeAddr = $data['CONSIGNEE_ADDRESS1'] ?? null;
        $this->persistClientAddress($consignee, $consigneeAddr);
        if ($c = $this->resolveSpecificAddress($consignee, $consigneeAddr, 'consignee')) {
            $bill->specificContacts()->create($c);
        }

        return $bill;
    }


    protected function nullableSourceText($value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    protected function requireParanaSourceText(
        $value,
        string $field
    ): string {
        $value = $this->nullableSourceText($value);

        if ($value === null) {
            throw new \Exception(
                "PARANA: falta {$field} en el archivo."
            );
        }

        return $value;
    }

    protected function buildParanaVoyageTiming(): array
    {
        return [
            'departure_date' => null,
            'estimated_arrival_date' => null,
        ];
    }

    protected function buildParanaBillDates($value): array
    {
        $value = $this->nullableSourceText($value);

        if ($value === null) {
            return [
                'bill_date' => null,
                'loading_date' => null,
            ];
        }

        $billDate = $this->parseDateFromData($value);

        if (!$billDate) {
            throw new \Exception(
                "PARANA: BL_DATE inválida: {$value}"
            );
        }

        return [
            'bill_date' => $billDate,
            'loading_date' => null,
        ];
    }

    protected function parseParanaPackageQuantity($value): int
    {
        $value = trim((string) $value);

        if ($value === '' || !ctype_digit($value)) {
            throw new \Exception(
                'PARANA: NUMBER_OF_PACKAGES inválido o ausente.'
            );
        }

        $quantity = (int) $value;

        if ($quantity <= 0) {
            throw new \Exception(
                'PARANA: NUMBER_OF_PACKAGES debe ser mayor que cero.'
            );
        }

        return $quantity;
    }

    protected function normalizeParanaCargoMarks($value): ?string
    {
        $value = trim((string) $value);

        if (
            $value === ''
            || strtoupper($value) === 'N/A'
        ) {
            return null;
        }

        return $value;
    }

    protected function mapParanaContainerState($status): array
    {
        $status = strtoupper(
            trim((string) $status)
        );

        return match ($status) {
            'F', 'FULL', 'L' => [
                'condition' => 'L',
                'operational_status' => 'loaded',
                'is_empty' => false,
            ],
            'E', 'EMPTY' => [
                'condition' => 'V',
                'operational_status' => 'empty',
                'is_empty' => true,
            ],
            default => throw new \Exception(
                "PARANA: CONTAINER_STATUS inválido: {$status}"
            ),
        };
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


    protected function createContainer(
        BillOfLading $bill,
        array $data
    ): ?Container {
        $number = trim(
            (string) ($data['CONTAINER_NUMBER'] ?? '')
        );

        if ($number === '') {
            return null;
        }

        if (
            Container::where(
                'container_number',
                $number
            )->exists()
        ) {
            throw new \Exception(
                "Ya existe un contenedor con número: {$number}."
            );
        }

        $containerType = $this->findExistingContainerType(
            $this->requireParanaSourceText(
                $data['CONTAINER_TYPE'] ?? null,
                'CONTAINER_TYPE'
            )
        );

        $state = $this->mapParanaContainerState(
            $data['CONTAINER_STATUS'] ?? null
        );

        return Container::create([
            'container_number' => $number,
            'container_type_id' => $containerType->id,
            'tare_weight_kg' => $this->parseWeight(
                $data['TARE_WEIGHT'] ?? null
            ),
            'current_gross_weight_kg' => $this->parseWeight(
                $data['GROSS_WEIGHT'] ?? null
            ),
            'cargo_weight_kg' => $state['is_empty']
                ? 0
                : $this->parseWeight(
                    $data['NET_WEIGHT'] ?? null
                ),
            'max_gross_weight_kg' =>
                $containerType->max_gross_weight_kg,
            'condition' => $state['condition'],
            'shipper_seal' => $data['SEAL_NO'] ?? null,
            'operational_status' =>
                $state['operational_status'],
            'current_port_id' => $bill->loading_port_id,
            'webservice_data' => json_encode([
                'parana_data' => [
                    'description' =>
                        $data['DESCRIPTION'] ?? null,
                    'imo_number' =>
                        $data['IMO_NUMBER'] ?? null,
                    'un_number' =>
                        $data['UN_NUMBER'] ?? null,
                    'temp_max' =>
                        $data['TEMP_MAX'] ?? null,
                    'temp_min' =>
                        $data['TEMP_MIN'] ?? null,
                    'packages' =>
                        $data['NUMBER_OF_PACKAGES'] ?? null,
                    'volume' =>
                        $data['VOLUME'] ?? null,
                ],
            ]),
            'active' => true,
            'created_by_user_id' => auth()->id(),
        ]);
    }



    protected function findExistingContainerType(
        string $typeCode
    ): ContainerType {
        $source = strtoupper(trim($typeCode));

        $mapping = [
            '20DV' => '20GP',
            '20GP' => '20GP',
            '40DV' => '40GP',
            '40GP' => '40GP',
            '40HC' => '40HC',
            '20RF' => '20RF',
            '40RH' => '40RH',
            '20TN' => '20TN',
            '20OT' => '20OT',
        ];

        if (!isset($mapping[$source])) {
            throw new \Exception(
                "Tipo de contenedor PARANA {$source} "
                . 'sin equivalencia comprobada en catálogo.'
            );
        }

        $type = ContainerType::where(
                'code',
                $mapping[$source]
            )
            ->where('active', true)
            ->first();

        if (!$type) {
            throw new \Exception(
                "Falta {$mapping[$source]} en catálogo "
                . "para PARANA {$source}."
            );
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

    protected function buildPartyAddress(
        array $data,
        string $prefix
    ): ?string {
        $parts = [];

        foreach ([
            "{$prefix}_ADDRESS1",
            "{$prefix}_ADDRESS2",
            "{$prefix}_ADDRESS3",
            "{$prefix}_CITY",
            "{$prefix}_ZIP",
        ] as $field) {
            $value = trim((string) ($data[$field] ?? ''));

            if ($value !== '') {
                $parts[] = $value;
            }
        }

        $parts = array_values(array_unique($parts));

        return $parts === []
            ? null
            : implode(', ', $parts);
    }

    /**
     * @return array{tax_id:string,tax_type:?string}|null
     */
    protected function extractFiscalIdentityFromText(?string $text): ?array
    {
        $text = trim((string) $text);

        if ($text === '') {
            return null;
        }

        $typedPatterns = [
            'CUIT' => '/\bCUIT\b\s*(?:(?:NBR|NRO|Nº|N°)\.?\s*)?[:#.-]?\s*([0-9][0-9.\/-]{5,20})/iu',
            'CNPJ' => '/\bCNPJ\b\s*[:#.-]?\s*([0-9][0-9.\/-]{5,20})/iu',
            'RUC' => '/\bR\.?\s*U\.?\s*C\.?\b\s*[:#.-]?\s*([0-9][0-9.\/-]{5,20})/iu',
            'NIT' => '/\bNIT\b\s*[:#.-]?\s*([0-9][0-9.\/-]{5,20})/iu',
        ];

        foreach ($typedPatterns as $taxType => $pattern) {
            if (!preg_match($pattern, $text, $matches)) {
                continue;
            }

            $taxId = preg_replace('/\D/', '', $matches[1]);

            if (
                $taxId === ''
                || preg_match('/^0+$/', $taxId)
                || !$this->isTaxIdCompatibleWithType($taxId, $taxType)
            ) {
                throw new \DomainException(
                    "Parana: identificador {$taxType} con formato incompatible."
                );
            }

            return [
                'tax_id' => $taxId,
                'tax_type' => $taxType,
            ];
        }

        // TAXID/TAX ID y RUT son marcadores fiscales reales del archivo,
        // pero no alcanzan para adjudicar un DocumentType de nuestro catálogo.
        //
        // RUT se trata expresamente como genérico: el archivo real contiene
        // RUT tanto para Paraguay como para Uruguay.
        if (preg_match(
            '/\b(?:TAX\s*ID|TAXID|RUT)\b\s*[:#.-]?\s*([0-9][0-9.\/-]{5,20})/iu',
            $text,
            $matches
        )) {
            $taxId = preg_replace('/\D/', '', $matches[1]);

            // Al haber marcador fiscal explícito permitimos 7..14 dígitos.
            // No usamos esa longitud para inferir ningún tipo documental.
            if (
                $taxId !== ''
                && !preg_match('/^0+$/', $taxId)
                && strlen($taxId) >= 7
                && strlen($taxId) <= 14
            ) {
                return [
                    'tax_id' => $taxId,
                    'tax_type' => null,
                ];
            }
        }

        return null;
    }

    protected function isTaxIdCompatibleWithType(
        string $taxId,
        string $taxType
    ): bool {
        $length = strlen($taxId);

        return match ($taxType) {
            'CUIT' => $length === 11,
            'CNPJ' => $length === 14,
            'RUC' => $length >= 7 && $length <= 9,
            'NIT' => $length >= 9 && $length <= 10,
            default => false,
        };
    }

    /**
     * @return array{tax_id:?string,tax_type:?string}
     */
    protected function resolveClientTaxIdentity(
        ?string $name,
        ?string $address
    ): array {
        $nameIdentity = $this->extractFiscalIdentityFromText($name);
        $addressIdentity = $this->extractFiscalIdentityFromText($address);

        if ($nameIdentity !== null && $addressIdentity !== null) {
            if ($nameIdentity['tax_id'] !== $addressIdentity['tax_id']) {
                throw new \DomainException(
                    'Parana: nombre y domicilio declaran identificadores fiscales distintos.'
                );
            }

            if (
                $nameIdentity['tax_type'] !== null
                && $addressIdentity['tax_type'] !== null
                && $nameIdentity['tax_type'] !== $addressIdentity['tax_type']
            ) {
                throw new \DomainException(
                    'Parana: nombre y domicilio declaran tipos fiscales distintos.'
                );
            }

            return [
                'tax_id' => $nameIdentity['tax_id'],
                'tax_type' => $nameIdentity['tax_type']
                    ?? $addressIdentity['tax_type'],
            ];
        }

        $identity = $nameIdentity ?? $addressIdentity;

        return $identity ?? [
            'tax_id' => null,
            'tax_type' => null,
        ];
    }

    protected function countryAlpha2ForTaxType(?string $taxType): ?string
    {
        return match ($taxType) {
            'CUIT' => 'AR',
            'RUC' => 'PY',
            'CNPJ' => 'BR',
            'NIT' => 'CO',
            default => null,
        };
    }

    protected function countryAlpha2FromPartyText(?string $text): ?string
    {
        $text = mb_strtoupper(trim((string) $text));

        if ($text === '') {
            return null;
        }

        $patterns = [
            'AR' => '/\bARGENTINA\b/u',
            'PY' => '/\bPARAGUAY\b/u',
            'UY' => '/\bURUGUAY\b/u',
            'BR' => '/\b(?:BRASIL|BRAZIL)\b/u',
            'CO' => '/\bCOLOMBIA\b/u',
        ];

        foreach ($patterns as $alpha2 => $pattern) {
            if (preg_match($pattern, $text)) {
                return $alpha2;
            }
        }

        return null;
    }

    protected function countryAlpha2FromDeclaredValue(
        ?string $country
    ): ?string {
        $country = mb_strtoupper(trim((string) $country));

        if ($country === '') {
            return null;
        }

        if (preg_match('/^[A-Z]{2}$/', $country)) {
            return $country;
        }

        $alpha3 = [
            'ARG' => 'AR',
            'PRY' => 'PY',
            'URY' => 'UY',
            'BRA' => 'BR',
            'COL' => 'CO',
        ];

        if (isset($alpha3[$country])) {
            return $alpha3[$country];
        }

        return $this->countryAlpha2FromPartyText($country);
    }

    protected function countryIdForAlpha2(string $alpha2): int
    {
        $countryId = Country::query()
            ->where('alpha2_code', strtoupper($alpha2))
            ->value('id');

        if (!$countryId) {
            throw new \DomainException(
                "Parana: no existe el país {$alpha2} en el catálogo."
            );
        }

        return (int) $countryId;
    }

    protected function resolveClientCountryId(
        array $clientData,
        ?string $taxType,
        int $contextCountryId
    ): int {
        $declaredValue = trim(
            (string) ($clientData['country'] ?? '')
        );

        $declaredAlpha2 = $this->countryAlpha2FromDeclaredValue(
            $declaredValue
        );

        if ($declaredValue !== '' && $declaredAlpha2 === null) {
            throw new \DomainException(
                "Parana: país declarado no reconocido: {$declaredValue}."
            );
        }

        $taxAlpha2 = $this->countryAlpha2ForTaxType($taxType);

        if (
            $declaredAlpha2 !== null
            && $taxAlpha2 !== null
            && $declaredAlpha2 !== $taxAlpha2
        ) {
            throw new \DomainException(
                "Parana: el país declarado {$declaredAlpha2} "
                . "es incompatible con el tipo fiscal {$taxType}."
            );
        }

        if ($declaredAlpha2 !== null) {
            return $this->countryIdForAlpha2($declaredAlpha2);
        }

        if ($taxAlpha2 !== null) {
            return $this->countryIdForAlpha2($taxAlpha2);
        }

        $textAlpha2 = $this->countryAlpha2FromPartyText(
            ($clientData['name'] ?? '')
            . ' '
            . ($clientData['address'] ?? '')
        );

        if ($textAlpha2 !== null) {
            return $this->countryIdForAlpha2($textAlpha2);
        }

        if (
            $contextCountryId <= 0
            || !Country::query()->whereKey($contextCountryId)->exists()
        ) {
            throw new \DomainException(
                'Parana: no existe un país contextual válido para el cliente.'
            );
        }

        return $contextCountryId;
    }

    protected function findOrCreateClient(
        array $clientData,
        int $companyId,
        int $contextCountryId
    ): ?Client {
        $name = trim(
            (string) ($clientData['name'] ?? '')
        );

        if ($name === '') {
            return null;
        }

        $identity = $this->resolveClientTaxIdentity(
            $name,
            $clientData['address'] ?? null
        );

        $taxId = $identity['tax_id'];
        $taxType = $identity['tax_type'];

        $clientCountryId = $this->resolveClientCountryId(
            $clientData,
            $taxType,
            $contextCountryId
        );

        Log::info('findOrCreateClient Debug', [
            'client_name' => $name,
            'client_address' => $clientData['address'] ?? 'N/A',
            'country_id' => $clientCountryId,
            'tax_id' => $taxId,
            'tax_type' => $taxType,
            'company_id' => $companyId,
        ]);

        // Con identificador fiscal, la identidad es tax_id + country_id.
        // Nunca degradar luego a una coincidencia solamente por nombre.
        if ($taxId !== null) {
            $client = Client::query()
                ->where('tax_id', $taxId)
                ->where('country_id', $clientCountryId)
                ->first();

            if ($client) {
                return $client;
            }
        } else {
            // Sin identificador fiscal sólo se reutiliza otro cliente
            // igualmente sin tax_id y del mismo país.
            $client = Client::query()
                ->whereNull('tax_id')
                ->where('country_id', $clientCountryId)
                ->where(function ($query) use ($name) {
                    $normalizedName = mb_strtoupper($name);

                    $query
                        ->whereRaw(
                            'UPPER(TRIM(legal_name)) = ?',
                            [$normalizedName]
                        )
                        ->orWhereRaw(
                            'UPPER(TRIM(commercial_name)) = ?',
                            [$normalizedName]
                        );
                })
                ->first();

            if ($client) {
                return $client;
            }
        }

        $documentTypeId = null;

        if ($taxId !== null && $taxType !== null) {
            $documentTypeId = DocumentType::query()
                ->where('code', $taxType)
                ->where('country_id', $clientCountryId)
                ->where('active', true)
                ->value('id');

            if (!$documentTypeId) {
                throw new \DomainException(
                    "Parana: no existe un tipo documental {$taxType} "
                    . 'activo y compatible con el país resuelto.'
                );
            }
        }

        $client = Client::create([
            'legal_name' => $name,
            'commercial_name' => $name,
            'tax_id' => $taxId,
            'country_id' => $clientCountryId,
            'document_type_id' => $documentTypeId,
            'status' => 'active',
            'address' => $clientData['address'] ?? null,
            'created_by_company_id' => $companyId,
            'verified_at' => now(),
        ]);

        Log::info('Cliente creado', [
            'client_id' => $client->id,
            'tax_id' => $client->tax_id,
            'country_id' => $client->country_id,
            'document_type_id' => $client->document_type_id,
            'legal_name' => $client->legal_name,
        ]);

        return $client;
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


    protected function findOrCreateVessel(
        string $bargeName,
        int $companyId
    ): Vessel {
        throw new \Exception(
            'PARANA requiere el vessel seleccionado explícitamente '
            . 'durante la importación.'
        );
    }



    protected function extractVesselDataFromExcel($worksheet): array
    {
        $a1 = trim(
            (string) $worksheet->getCell('A1')->getCalculatedValue()
        );

        $row = in_array(
            mb_strtoupper($a1),
            ['LOCATION NAME', 'COMPANY', 'LOCATION'],
            true
        ) ? 2 : 1;

        $bargeName = $this->nullableSourceText(
            $worksheet->getCell('M' . $row)->getCalculatedValue()
        );

        $bargeId = $this->nullableSourceText(
            $worksheet->getCell('L' . $row)->getCalculatedValue()
        );

        return [
            'barge_id' => $bargeId,
            'barge_name' => $bargeName,
            'registration_number' => $bargeId,
        ];
    }



    protected function createShipmentItem(
        BillOfLading $bill,
        array $data
    ): ?\App\Models\ShipmentItem {
        $nextLineNumber =
            \App\Models\ShipmentItem::where(
                'bill_of_lading_id',
                $bill->id
            )->max('line_number') + 1;

        if ($nextLineNumber < 1) {
            $nextLineNumber = 1;
        }

        $quantity = $this->parseParanaPackageQuantity(
            $data['NUMBER_OF_PACKAGES'] ?? null
        );

        $shipmentItem =
            \App\Models\ShipmentItem::create([
                'bill_of_lading_id' => $bill->id,
                'line_number' => $nextLineNumber,
                'item_description' =>
                    $this->requireParanaSourceText(
                        $data['DESCRIPTION'] ?? null,
                        'DESCRIPTION'
                    ),
                'package_quantity' => $quantity,
                'gross_weight_kg' => $this->parseWeight(
                    $data['GROSS_WEIGHT'] ?? null
                ),
                'net_weight_kg' => $this->parseWeight(
                    $data['NET_WEIGHT'] ?? null
                ),
                'cargo_type_id' =>
                    $this->determinateCargoTypeId($data),
                'packaging_type_id' =>
                    $this->determinatePackagingTypeId($data),
                'volume_m3' => $this->parseVolume(
                    $data['VOLUME'] ?? null
                ),
                'commodity_code' =>
                    $data['NCM'] ?? null,
                'cargo_marks' =>
                    $this->normalizeParanaCargoMarks(
                        $data['MARKS_DESCRIPTION'] ?? null
                    ),
                'created_by_user_id' => auth()->id(),
            ]);

        if (!empty($data['CONTAINER_NUMBER'])) {
            $container = Container::where(
                'container_number',
                $data['CONTAINER_NUMBER']
            )->first();

            if (!$container) {
                throw new \Exception(
                    'No se encontró el contenedor procesado: '
                    . $data['CONTAINER_NUMBER']
                );
            }

            $shipmentItem->containers()->attach(
                $container->id,
                [
                    'package_quantity' => $quantity,
                    'gross_weight_kg' => $this->parseWeight(
                        $data['GROSS_WEIGHT'] ?? null
                    ),
                    'net_weight_kg' => $this->parseWeight(
                        $data['NET_WEIGHT'] ?? null
                    ),
                    'volume_m3' => $this->parseVolume(
                        $data['VOLUME'] ?? null
                    ),
                    'status' => 'planned',
                ]
            );
        }

        return $shipmentItem;
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

        $shipmentId = $voyage->shipments()->value('id');

        $importRecord->recordExplicitlyCreatedObjects([
            'voyage' => [$voyage->id],
            'shipment' => array_filter([$shipmentId]),
            'bill' => array_map(
                fn ($bill) => $bill->id,
                $bills
            ),
            'container' => array_map(
                fn ($container) => $container->id,
                $containers
            ),
            'item' => array_map(
                fn ($item) => $item->id,
                $items
            ),
        ]);

        $importRecord->markAsCompleted([
            'voyage_id' => $voyage->id,
            'processing_time_seconds' =>
                round($processingTime, 2),
            'notes' => 'Importación PARANA Excel completada',
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