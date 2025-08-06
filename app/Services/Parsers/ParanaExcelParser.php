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
            
            // Crear voyage
            $voyage = $this->createVoyage($voyageData);
            
            // Crear shipment principal
            $shipment = $this->createShipment($voyage, $voyageData);

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
        // Crear/buscar puertos
        $originPort = $this->findOrCreatePort($data['pol'], 'Buenos Aires');
        $destPort = $this->findOrCreatePort($data['pod'], 'Terminal Villeta');

        $voyageNumber = 'PARANA-' . ($data['voyage_number'] ?: uniqid()) . '-' . now()->format('Ymd');

        return Voyage::create([
            'company_id' => auth()->user()->company_id,
            'voyage_number' => $voyageNumber,
            'origin_port_id' => $originPort->id,
            'destination_port_id' => $destPort->id,
            'vessel_name' => $data['barge_name'] ?: 'PAR13001',
            'status' => 'planning',
            'cargo_type' => 'container',
            'created_by_user_id' => auth()->id(),
            'manifest_format' => 'PARANA_EXCEL',
            'import_source' => 'parana_parser'
        ]);
    }

    protected function createShipment(Voyage $voyage, array $data): Shipment
    {
        return Shipment::create([
            'voyage_id' => $voyage->id,
            'shipment_number' => 'PARANA-' . now()->format('YmdHis'),
            'vessel_role' => 'primary',
            'status' => 'planning',
            'active' => true,
            'created_by_user_id' => auth()->id()
        ]);
    }

    protected function createBillOfLading(Shipment $shipment, array $data): BillOfLading
    {
        $shipper = $this->findOrCreateClient([
            'name' => $data['SHIPPER_NAME'],
            'address' => $data['SHIPPER_ADDRESS1'],
            'phone' => $data['SHIPPER_PHONE']
        ]);

        $consignee = $this->findOrCreateClient([
            'name' => $data['CONSIGNEE_NAME'], 
            'address' => $data['CONSIGNEE_ADDRESS1'],
            'phone' => $data['CONSIGNEE_PHONE']
        ]);

        return BillOfLading::create([
            'shipment_id' => $shipment->id,
            'bl_number' => $data['BL_NUMBER'],
            'bl_type' => 'original',
            'shipper_id' => $shipper?->id,
            'consignee_id' => $consignee?->id,
            'freight_terms' => $data['FREIGHT_TERMS'] ?: 'prepaid',
            'status' => 'active',
            'created_by_user_id' => auth()->id(),
            'parana_data' => [
                'mlo_bl_nr' => $data['MLO_BL_NR'],
                'permiso' => $data['PERMISO'],
                'temp_max' => $data['TEMP_MAX'],
                'temp_min' => $data['TEMP_MIN']
            ]
        ]);
    }

    protected function createContainer(BillOfLading $bill, array $data): Container
    {
        return Container::create([
            'bill_of_lading_id' => $bill->id,
            'container_number' => $data['CONTAINER_NUMBER'],
            'container_type' => $data['CONTAINER_TYPE'] ?: '40HC',
            'container_status' => $data['CONTAINER_STATUS'] ?: 'active',
            'seal_number' => $data['SEAL_NO'],
            'gross_weight' => $this->parseWeight($data['GROSS_WEIGHT']),
            'net_weight' => $this->parseWeight($data['NET_WEIGHT']),
            'tare_weight' => $this->parseWeight($data['TARE_WEIGHT']),
            'volume' => $this->parseVolume($data['VOLUME']),
            'package_count' => (int)($data['NUMBER_OF_PACKAGES'] ?: 0),
            'package_type' => $data['PACK_TYPE'] ?: 'general',
            'cargo_description' => $data['DESCRIPTION'],
            'hazmat_info' => [
                'imo_number' => $data['IMO_NUMBER'],
                'un_number' => $data['UN_NUMBER'],
                'flash_point' => $data['FLASH_POINT']
            ],
            'created_by_user_id' => auth()->id()
        ]);
    }

    protected function findOrCreatePort(string $code, string $defaultName): Port
    {
        $port = Port::where('port_code', $code)->first();
        
        if (!$port) {
            $port = Port::create([
                'port_code' => $code,
                'name' => $defaultName,
                'country_id' => $code === 'ARBUE' ? 1 : 2, // AR=1, PY=2
                'is_active' => true,
                'created_by_import' => true
            ]);
        }
        
        return $port;
    }

    protected function findOrCreateClient(array $clientData): ?Client
    {
        if (empty($clientData['name'])) {
            return null;
        }

        $client = Client::where('legal_name', $clientData['name'])
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
                'created_by_import' => true,
                'created_by_user_id' => auth()->id()
            ]);
        }

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
}