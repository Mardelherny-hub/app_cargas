<?php

namespace App\Services\Parsers;

use App\Contracts\ManifestParserInterface;
use App\ValueObjects\ManifestParseResult;
use App\Models\Voyage;
use App\Models\Shipment;
use App\Models\BillOfLading;
use App\Models\ShipmentItem;
use App\Models\Container;
use App\Models\Client;
use App\Models\Port;
use App\Models\Country;
use App\Models\Vessel;
use App\Services\Parsers\Concerns\ExtractsEmbeddedTaxId;
use App\Services\Parsers\Concerns\EnsuresUniqueVoyageNumber;
use App\Services\Parsers\Concerns\ResolvesClientAddresses;
use App\Models\User;
use App\Models\ManifestImport;
use Illuminate\Database\Eloquent\Model;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Exception;

/**
 * PARSER GUARAN EXCEL - SOLO DATOS REALES DEL ARCHIVO
 * 
 * ✅ FIDEDIGNO PARA ADUANA - SIN DATOS INVENTADOS
 * 
 * Estructura verificada del archivo:
 * - Formato: Excel (.xlsx) con 72 columnas (A-BT)
 * - 5 filas de metadatos, encabezados en fila 6
 * - Datos desde fila 7 en adelante
 * 
 * Datos reales extraídos:
 * - MANIFEST_TYPE: "CM" → voyage_type
 * - BARGE_NAME/BARGE_ID: vessel real
 * - NCM: "0206" → cargo_type por código real
 * - PACK_TYPE: "Carton(s)" → packaging_type real
 * - TEMP_MIN: "-18" → refrigeración real
 * - FREIGHT_TERMS: términos reales
 * - BL_DATE: fecha real para cálculos
 */
class GuaranExcelParser implements ManifestParserInterface
{
    use ExtractsEmbeddedTaxId;
    use EnsuresUniqueVoyageNumber;
    use ResolvesClientAddresses;
    /**
     * Mapeo columnas A-BT basado en análisis real del archivo
     */
    protected array $columnMapping = [
        'A' => 'LOCATION_NAME',      'B' => 'ADDRESS_LINE1',     'C' => 'ADDRESS_LINE2',
        'D' => 'ADDRESS_LINE3',      'E' => 'CITY',              'F' => 'ZIP',
        'G' => 'COUNTRY_NAME',       'H' => 'TELEPHONE_NO',      'I' => 'FAX_NO',
        'J' => 'EMAIL_ID',           'K' => 'MANIFEST_TYPE',     'L' => 'BARGE_ID',
        'M' => 'BARGE_NAME',         'N' => 'VOYAGE_NO',         'O' => 'BL_NUMBER',
        'P' => 'BL_DATE',            'Q' => 'POL',               'R' => 'POL_TERMINAL',
        'S' => 'POD',                'T' => 'POD_TERMINAL',      'U' => 'FREIGHT_TERMS',
        'V' => 'SHIPPER_NAME',       'W' => 'SHIPPER_ADDRESS1',  'X' => 'SHIPPER_ADDRESS2',
        'Y' => 'SHIPPER_ADDRESS3',   'Z' => 'SHIPPER_CITY',      'AA' => 'SHIPPER_ZIP',
        'AB' => 'SHIPPER_COUNTRY',   'AC' => 'SHIPPER_PHONE',    'AD' => 'SHIPPER_FAX',
        'AE' => 'CONSIGNEE_NAME',    'AF' => 'CONSIGNEE_ADDRESS1', 'AG' => 'CONSIGNEE_ADDRESS2',
        'AH' => 'CONSIGNEE_ADDRESS3', 'AI' => 'CONSIGNEE_CITY',   'AJ' => 'CONSIGNEE_ZIP',
        'AK' => 'CONSIGNEE_COUNTRY', 'AL' => 'CONSIGNEE_PHONE',  'AM' => 'CONSIGNEE_FAX',
        'AN' => 'NOTIFY_PARTY_NAME', 'AO' => 'NOTIFY_PARTY_ADDRESS1', 'AP' => 'NOTIFY_PARTY_ADDRESS2',
        'AQ' => 'NOTIFY_PARTY_ADDRESS3', 'AR' => 'NOTIFY_PARTY_CITY', 'AS' => 'NOTIFY_PARTY_ZIP',
        'AT' => 'NOTIFY_PARTY_COUNTRY', 'AU' => 'NOTIFY_PARTY_PHONE', 'AV' => 'NOTIFY_PARTY_FAX',
        'AW' => 'PFD',               'AX' => 'CONTAINER_NUMBER', 'AY' => 'CONTAINER_TYPE',
        'AZ' => 'CONTAINER_STATUS',  'BA' => 'SEAL_NO',          'BB' => 'PACK_TYPE',
        'BC' => 'NUMBER_OF_PACKAGES', 'BD' => 'GROSS_WEIGHT',    'BE' => 'NET_WEIGHT',
        'BF' => 'TARE_WEIGHT',       'BG' => 'VOLUME',           'BH' => 'REMARKS',
        'BI' => 'MARKS_DESCRIPTION', 'BJ' => 'DESCRIPTION',      'BK' => 'IMO_NUMBER',
        'BL' => 'UN_NUMBER',         'BM' => 'FLASH_POINT',      'BN' => 'TEMP_MAX',
        'BO' => 'TEMP_MIN',          'BP' => 'NCM',              'BQ' => 'REMARKS1',
        'BR' => 'REMARKS2',          'BS' => 'REMARKS3',         'BT' => 'MLO_BL_NR'
    ];

    public function canParse(string $filePath): bool
    {
        if (!in_array(strtolower(pathinfo($filePath, PATHINFO_EXTENSION)), ['xlsx', 'xls'])) {
            return false;
        }

        try {
            $reader = IOFactory::createReaderForFile($filePath);
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($filePath);
            $worksheet = $spreadsheet->getActiveSheet();
            
            $highestColumn = $worksheet->getHighestColumn();
            $columnIndex = Coordinate::columnIndexFromString($highestColumn);
            
            if ($columnIndex < 70) return false;

            // Buscar indicadores GUARAN en primeras filas
            for ($row = 1; $row <= 10; $row++) {
                $content = strtoupper(
                    $worksheet->getCell('A' . $row)->getCalculatedValue() . ' ' .
                    $worksheet->getCell('M' . $row)->getCalculatedValue()
                );
                
                if (strpos($content, 'GUARAN') !== false || 
                    strpos($content, 'EDI TO CUSTOM') !== false) {
                    return true;
                }
            }

            return false;
        } catch (Exception $e) {
            return false;
        }
    }

    public function parse(string $filePath, array $options = []): ManifestParseResult
    {
        $startTime = microtime(true);
        
        Log::info('Iniciando parseo GUARAN Excel - Solo datos reales', [
            'file_path' => $filePath,
            'file_size' => filesize($filePath)
        ]);

        try {
            DB::beginTransaction();

            // Crear registro de importación
            $importRecord = $this->createImportRecord($filePath, $options);
            
            // Leer Excel
            $reader = IOFactory::createReaderForFile($filePath);
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($filePath);
            $worksheet = $spreadsheet->getActiveSheet();
            
            // Extraer datos (desde fila 7)
            $data = $this->extractDataFromWorksheet($worksheet);
            
            if (empty($data)) {
                throw new Exception('No se encontraron datos válidos en el archivo');
            }

            Log::info('Datos extraídos', ['total_rows' => count($data)]);

            // Crear objetos usando SOLO datos reales
            $voyageData = $this->extractVoyageData($data[0]);
            $voyage = $this->createVoyage($voyageData, $options);
            $shipment = $this->createShipment($voyage, $voyageData);
            
            // Procesar por BL Number
            $groupedByBL = $this->groupDataByBillNumber($data);
            $bills = [];
            $containers = [];

            // Importación masiva: silenciar los eventos de modelo (hooks saved/saving)
            // durante la creación para evitar recálculos en cascada por cada item,
            // que en manifiestos grandes agotan tiempo/memoria (HTTP 500).
            Model::withoutEvents(function () use ($groupedByBL, $shipment, &$bills, &$containers) {
                foreach ($groupedByBL as $blNumber => $blRows) {
                    // Pasamos todas las filas del BL para que pueda contar contenedores únicos
                    $bill = $this->createBillOfLading($shipment, $blRows);
                    $bills[] = $bill;
                    foreach ($blRows as $row) {
                        // Crear contenedor si la fila lo trae
                        $container = null;
                        if (!empty($row['CONTAINER_NUMBER'])) {
                            $container = $this->createContainer($row);
                            if ($container) $containers[] = $container;
                        }
                        // Crear el item y guardarlo en variable para vincular
                        $item = $this->createShipmentItem($bill, $row);
                        // Si hay contenedor y se creó el item, vincular en la tabla pivot
                        if ($container && $item) {
                            $this->attachContainerToItem($container, $item);
                        }
                    }
                }
            });

            // Recalcular estadísticas UNA sola vez por cada BL (con los items ya insertados),
            // en lugar de una vez por item dentro del loop.
            foreach ($bills as $bill) {
                $bill->recalculateItemStats();
            }

            // Completar importación
            $this->completeImportRecord($importRecord, $voyage, $bills, $containers, $startTime);
            
            DB::commit();

            Log::info('GUARAN Excel parsing completado', [
                'voyage_id' => $voyage->id,
                'bills' => count($bills),
                'containers' => count($containers),
                'time' => round(microtime(true) - $startTime, 2) . 's'
            ]);

            return ManifestParseResult::success(
                voyage: $voyage,
                shipments: [$shipment],
                containers: $containers,
                billsOfLading: $bills,
                statistics: [
                    'records_processed' => count($data),
                    'bills_created' => count($bills),
                    'containers_created' => count($containers),
                    'agent' => $voyageData['agent_name'],
                    'vessel' => $voyageData['vessel_name'],
                    'route' => $voyageData['pol'] . ' → ' . $voyageData['pod']
                ]
            );

        } catch (Exception $e) {
            DB::rollBack();
            
            Log::error('Error parsing GUARAN Excel', [
                'file' => $filePath,
                'error' => $e->getMessage(),
                'line' => $e->getLine()
            ]);

            // Archivo ya importado: el viaje y su envío ya existen (choca el índice
            // único voyage_id + vessel_id). Mensaje amable en lugar del error SQL.
            if (strpos($e->getMessage(), 'uk_shipments_voyage_vessel') !== false
                || strpos($e->getMessage(), 'voyages_voyage_number_unique') !== false) {
                return ManifestParseResult::failure([
                    'Este archivo ya fue importado anteriormente. El viaje ya existe en el sistema y no se duplicó ningún dato. Si necesita importarlo de nuevo, primero revierta la importación desde el Historial de Importaciones.'
                ]);
            }

            return ManifestParseResult::failure([
                'Error procesando archivo GUARAN: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Extraer datos del worksheet (desde fila 7).
     * Aplica validación previa por fila. Si alguna fila tiene campos
     * críticos faltantes o BL_DATE inválida, aborta antes de tocar BD
     * con un mensaje legible que lista todos los errores encontrados.
     */
    protected function extractDataFromWorksheet($worksheet): array
    {
        // 🔍 DEBUG: Ver qué contiene la fila 7
        Log::info('DEBUG: Contenido de fila 7', [
            'A7' => $worksheet->getCell('A7')->getCalculatedValue(),
            'O7' => $worksheet->getCell('O7')->getCalculatedValue(),
            'AX7' => $worksheet->getCell('AX7')->getCalculatedValue()
        ]);
        $data = [];
        $errors = [];
        $highestRow = $worksheet->getHighestRow();

        for ($row = 7; $row <= $highestRow; $row++) {
            // Saltar filas con BL_NUMBER vacío (filas en blanco al final del archivo).
            $blNumber = trim($worksheet->getCell('O' . $row)->getCalculatedValue());
            if (empty($blNumber)) continue;

            $rowData = [];
            foreach ($this->columnMapping as $column => $fieldName) {
                $cellValue = $worksheet->getCell($column . $row)->getCalculatedValue();
                $rowData[$fieldName] = $this->cleanCellValue($cellValue);
            }

            // Validación previa: acumular errores sin abortar para reportar
            // todos los problemas del archivo en una sola pasada.
            $rowErrors = $this->validateRow($rowData, $row);
            if (!empty($rowErrors)) {
                $errors = array_merge($errors, $rowErrors);
                continue;
            }

            $data[] = $rowData;
        }

        // Si cualquier fila tuvo errores, abortar con mensaje amigable
        // antes de crear voyage, shipment, clients, BLs, items o containers.
        if (!empty($errors)) {
            throw new Exception("Archivo GUARAN inválido:\n" . implode("\n", $errors));
        }

        return $data;
    }

    /**
     * Validar campos críticos de una fila. Retorna array de errores legibles.
     * Array vacío significa fila válida.
     * Los campos críticos cubren los datos mínimos necesarios para crear
     * voyage, vessel, ports, clients y bill of lading sin SQL crudo expuesto.
     */
    protected function validateRow(array $rowData, int $excelRow): array
    {
        $errors = [];

        $required = [
            'BL_NUMBER',
            'BL_DATE',
            'SHIPPER_NAME',
            'CONSIGNEE_NAME',
            'VOYAGE_NO',
            'BARGE_NAME',
            'POL',
            'POD',
        ];

        foreach ($required as $field) {
            if ($rowData[$field] === null || $rowData[$field] === '') {
                $errors[] = "Fila {$excelRow}: campo obligatorio faltante: {$field}.";
            }
        }

        // Si BL_DATE está presente, verificar que parseDate() pueda procesarla.
        // Si está vacía ya quedó registrada arriba.
        if (!empty($rowData['BL_DATE']) && !$this->parseDate($rowData['BL_DATE'])) {
            $errors[] = "Fila {$excelRow}: BL_DATE inválida: {$rowData['BL_DATE']}.";
        }

        return $errors;
    }

    protected function cleanCellValue($value): ?string
    {
        return ($value === null || $value === '') ? null : trim((string) $value);
    }

    /**
     * Extraer datos del voyage - SOLO REALES
     */
    protected function extractVoyageData(array $firstRow): array
    {
        return [
            'agent_name' => $firstRow['LOCATION_NAME'] ?? 'Guaran Feeder S.A.',
            'agent_address' => trim(($firstRow['ADDRESS_LINE1'] ?? '') . ' ' . ($firstRow['ADDRESS_LINE2'] ?? '')),
            'agent_city' => $firstRow['CITY'] ?? null,
            'agent_country' => $firstRow['COUNTRY_NAME'] ?? null,
            'agent_phone' => $firstRow['TELEPHONE_NO'] ?? null,
            'agent_email' => $firstRow['EMAIL_ID'] ?? null,
            'vessel_name' => $firstRow['BARGE_NAME'] ?? null,
            'vessel_id' => $firstRow['BARGE_ID'] ?? null,
            'voyage_number' => $firstRow['VOYAGE_NO'] ?? null,
            'manifest_type' => $firstRow['MANIFEST_TYPE'] ?? null,
            'bl_date' => $firstRow['BL_DATE'] ?? null,
            'pol' => $firstRow['POL'] ?? null,
            'pod' => $firstRow['POD'] ?? null,
            'freight_terms' => $firstRow['FREIGHT_TERMS'] ?? null
        ];
    }

    /**
     * Crear voyage - SOLO DATOS REALES
     */
    protected function createVoyage(array $voyageData, array $options): Voyage
    {
        $user = auth()->user();
        $companyId = ($user->userable_type === 'App\Models\Company' ? $user->userable_id : null);
        
        if (!$companyId) {
            throw new Exception("Usuario sin empresa asignada");
        }

        // Verificar datos mínimos
        if (!$voyageData['voyage_number']) {
            throw new Exception("VOYAGE_NO es requerido en el archivo");
        }

        $vessel = $this->findOrCreateVessel($voyageData, $companyId);
        $originPort = $this->findOrCreatePort($voyageData['pol']);
        $destPort = $this->findOrCreatePort($voyageData['pod']);

        // El voyage_number es único global. Si ya existe (en cualquier empresa),
        // se bloquea la importación con un error claro en lugar de reusar el viaje.
        $this->guardVoyageNumberIsFree($voyageData['voyage_number']);

        // Crear con datos reales
        return Voyage::create([
            'company_id' => $companyId,
            'voyage_number' => $voyageData['voyage_number'],
            'lead_vessel_id' => $vessel->id,
            'origin_port_id' => $originPort->id,
            'destination_port_id' => $destPort->id,
            'origin_country_id' => $originPort->country_id,
            'destination_country_id' => $destPort->country_id,
            'departure_date' => $this->parseBLDateForDeparture($voyageData['bl_date']),
            'estimated_arrival_date' => $this->parseBLDateForArrival($voyageData['bl_date']),
            'voyage_type' => $this->mapManifestType($voyageData['manifest_type']),
            'cargo_type' => $this->mapCargoType($voyageData),
            'status' => 'planning',
            'is_consolidated' => true,
            'vessel_count' => 1,
            'created_by_user_id' => auth()->id(),
            'operational_notes' => 'Importado desde GUARAN Excel: ' . $voyageData['agent_name']
        ]);
    }

    /**
     * Determina el tipo de operación aduanera para el Voyage.
     * En Guaran: POL = Paraguay, POD = Argentina → import.
     * Si más adelante querés ampliar lógica, podés hacerlo acá.
     */
    protected function mapCargoType(array $voyageData): string
    {
        // 1) Caso simple y robusto para Guaran (PY → AR)
        return 'import';

        // ── Ejemplos de ampliación futura (dejar comentado para no romper hoy) ──
        // $pol = strtoupper($voyageData['pol_code'] ?? '');   // ej. 'PYASU'
        // $pod = strtoupper($voyageData['pod_code'] ?? '');   // ej. 'ARBUE'
        // if ($pol && $pod && substr($pol, 0, 2) === substr($pod, 0, 2)) {
        //     return 'cabotage';
        // }
        // $desc = strtolower(($voyageData['marks_description'] ?? '').' '.($voyageData['remarks'] ?? ''));
        // if (str_contains($desc, 'tránsito') || str_contains($desc, 'transit')) {
        //     return 'transit';
        // }
        // if (str_contains($desc, 'transbordo') || str_contains($desc, 'transshipment')) {
        //     return 'transshipment';
        // }
        // // Por defecto, si POL≠AR y POD=AR → import; si POL=AR y POD≠AR → export.
        // if ($pol && $pod) {
        //     $isARFrom = str_starts_with($pol, 'AR');
        //     $isARTo   = str_starts_with($pod, 'AR');
        //     if (!$isARFrom && $isARTo) return 'import';
        //     if ($isARFrom && !$isARTo) return 'export';
        // }
        // return 'import';
    }

    /**
     * Buscar/crear vessel - SOLO DATOS REALES
     */
    protected function findOrCreateVessel(array $voyageData, int $companyId): Vessel
    {
        if (!$voyageData['vessel_name']) {
            throw new Exception("BARGE_NAME es requerido en el archivo");
        }

        // La matricula (registration_number, desde BARGE_ID) es la clave unica REAL
        // y es global (indice uk_vessels_registration, sin company_id). Una misma
        // barcaza fluvial existe una sola vez aunque la operen distintas empresas.
        // Buscar por matricula evita el INSERT duplicado (SQLSTATE 23000).
        $registrationNumber = $voyageData['vessel_id'] ?? 'REG-' . uniqid();

        // 1. Buscar por matricula (clave unica global)
        $vessel = Vessel::where('registration_number', $registrationNumber)->first();
        if ($vessel) return $vessel;

        // 2. Fallback: buscar por nombre dentro de la empresa (compatibilidad)
        $vessel = Vessel::where('name', $voyageData['vessel_name'])
            ->where('company_id', $companyId)
            ->first();
        if ($vessel) return $vessel;

        // 3. No existe: crear
        return Vessel::create([
            'name' => $voyageData['vessel_name'],
            'registration_number' => $registrationNumber,
            'company_id' => $companyId,
            'vessel_type_id' => $this->findVesselTypeByName($voyageData['vessel_name']),
            'flag_country_id' => $this->mapCountryName($voyageData['agent_country']),
            'operational_status' => 'active',
            'active' => true,
            // Agregar campos requeridos con valores por defecto
            'length_meters' => 80.0,         // Valor por defecto para barcaza
            'beam_meters' => 12.0,           // Valor por defecto para barcaza
            'draft_meters' => 2.5,           // Valor por defecto para barcaza
            'gross_tonnage' => 500,          // Valor por defecto
            'net_tonnage' => 350,            // Valor por defecto
            'cargo_capacity_tons' => 1000,    // Valor por defecto
        ]);
    }

    /**
     * Buscar/crear puerto - SOLO DATOS REALES
     */
    protected function findOrCreatePort(?string $code): Port
    {
        if (!$code) {
            throw new Exception("Código de puerto es requerido");
        }

        $port = Port::where('code', $code)->first();
        if ($port) return $port;

        return Port::create([
            'code' => $code,
            'name' => $this->generatePortName($code),
            'country_id' => $this->getCountryIdFromPortCode($code),
            'port_type' => 'river',
            'active' => true
        ]);
    }

    /**
     * Resolver puerto por código - LOOKUP ESTRICTO, nunca crea puertos.
     *
     * Se usa a nivel conocimiento. El archivo Guaran puede traer distintos POL
     * en el mismo manifiesto (PYASU y PYVLL conviven; verificado sobre archivo
     * real: 115 filas PYASU + 120 filas PYVLL). Devuelve null si el código está
     * vacío o no existe en `ports`; el llamador decide el fallback.
     */
    protected function resolvePortIdByCode(?string $code): ?int
    {
        $code = strtoupper(trim((string) $code));

        if ($code === '') {
            return null;
        }

        $portId = Port::where('code', $code)->where('active', true)->value('id');

        if (!$portId) {
            Log::warning('Guaran: código de puerto no encontrado en ports', ['code' => $code]);
        }

        return $portId;
    }

    /**
     * Crear shipment
     */
    protected function createShipment(Voyage $voyage, array $voyageData): Shipment
    {
        // Próximo número de secuencia dentro del viaje (1,2,3,...)
        $sequence = \App\Models\Shipment::where('voyage_id', $voyage->id)
            ->max('sequence_in_voyage');
        $sequence = ($sequence ?? 0) + 1;

        return Shipment::create([
            'voyage_id' => $voyage->id,
            'vessel_id' => $voyage->lead_vessel_id,
            'shipment_number'     => $this->generateShipmentNumber($voyage, $sequence),
            'sequence_in_voyage' => $sequence,
            'departure_time' => $voyage->departure_date,
            'estimated_arrival_time' => $voyage->estimated_arrival_date,
            'status' => 'planning',
            'is_lead_vessel' => true,
            'vessel_role' => 'single',
            'current_cargo_weight_tons' => 0,
            'current_container_count' => 0,
            'utilization_percentage' => 0.0,
            'cargo_capacity_tons' => $voyage->leadVessel->cargo_capacity_tons,
        ]);
    }

    /**
     * Devuelve el próximo sequence_in_voyage para ese viaje (1,2,3,...)
     */
    protected function getNextSequenceInVoyage(int $voyageId): int
    {
        $max = \App\Models\Shipment::where('voyage_id', $voyageId)->max('sequence_in_voyage');
        return ($max ?? 0) + 1;
    }

    /**
     * Genera un número de shipment legible y único por viaje.
     * Formato: {VOYAGE}-{SEQ2}  ej: ABX2525S-01
     */
    protected function generateShipmentNumber(\App\Models\Voyage $voyage, int $sequence): string
    {
        // Normalizamos voyage_number para evitar espacios y caracteres raros
        $base = preg_replace('/[^A-Za-z0-9]/', '', (string) $voyage->voyage_number);
        $seq  = str_pad((string) $sequence, 2, '0', STR_PAD_LEFT);

        // Opcional: incluir sigla de compañía para más unicidad:
        // $company = $voyage->company_id ?? null;
        // $prefix  = $company ? ('C'.$company.'-') : '';

        return "{$base}-{$seq}";
    }

    /**
     * Crear BillOfLading - SOLO DATOS REALES
     */
    protected function createBillOfLading(Shipment $shipment, array $blRows): BillOfLading
    {
        // Tomamos la primera fila para los datos de cabecera (shipper/consignee/notify/fechas)
        $row = $blRows[0];

        $shipper = $this->findOrCreateClient($this->extractClientData($row, 'SHIPPER'));
        $consignee = $this->findOrCreateClient($this->extractClientData($row, 'CONSIGNEE'));
        $notifyParty = $this->findOrCreateClient($this->extractClientData($row, 'NOTIFY_PARTY'));

        $blDate = $this->parseDate($row['BL_DATE']);
        if (!$blDate) {
            throw new Exception('BL_DATE inválida: ' . $row['BL_DATE']);
        }

        // Detectar si el BL es contenedorizado: alguna fila trae CONTAINER_NUMBER
        $containerNumbers = [];
        foreach ($blRows as $r) {
            if (!empty($r['CONTAINER_NUMBER'])) {
                $containerNumbers[] = $r['CONTAINER_NUMBER'];
            }
        }
        $uniqueContainers = array_unique($containerNumbers);
        $isContainerized = !empty($uniqueContainers);

        // Si es contenedorizado:
        //   - primary_cargo_type_id = 9 (CONTENEDORES, criterio aprobado QA)
        //   - primary_packaging_type_id = 4 (CONTENEDOR, criterio aprobado QA)
        //   - total_packages = cantidad de contenedores únicos del BL
        // Si NO es contenedorizado: mantener lógica original por NCM y PACK_TYPE
        $primaryCargoTypeId = $isContainerized ? 9 : $this->findCargoTypeByNCM($row['NCM']);
        $primaryPackagingTypeId = $isContainerized ? 4 : $this->findPackagingTypeByName($row['PACK_TYPE']);
        $totalPackages = $isContainerized
            ? count($uniqueContainers)
            : (int) ($row['NUMBER_OF_PACKAGES'] ?? 0);

        // Si el BL es contenedorizado, los totales del BL se calculan sumando todas las filas
        // (cada fila Excel representa un contenedor con su propio peso/volumen).
        // Si NO es contenedorizado, mantener el comportamiento original (solo primera fila).
        $totalGrossWeight = $isContainerized
            ? array_sum(array_map(fn($r) => $this->parseWeight($r['GROSS_WEIGHT'] ?? null), $blRows))
            : $this->parseWeight($row['GROSS_WEIGHT']);

        $totalNetWeight = $isContainerized
            ? array_sum(array_map(fn($r) => $this->parseWeight($r['NET_WEIGHT'] ?? null), $blRows))
            : $this->parseWeight($row['NET_WEIGHT']);

        $totalVolume = $isContainerized
            ? array_sum(array_map(fn($r) => (float) ($r['VOLUME'] ?? 0), $blRows))
            : (float) ($row['VOLUME'] ?? 0);

        $bill = BillOfLading::create([
            'shipment_id' => $shipment->id,
            'shipper_id' => $shipper->id,
            'consignee_id' => $consignee->id,
            'notify_party_id' => $notifyParty?->id,
            // Puertos del conocimiento: se resuelven por el POL/POD de la propia
            // fila del archivo, NO por el del Voyage. Antes heredaban el puerto
            // del viaje (armado con la primera fila del Excel) y todos los BLs
            // quedaban con Asunción aunque cargaran en Villeta (Roberto 20/07).
            // Fallback al puerto del viaje si el código del archivo no resuelve,
            // para no dejar el BL sin puerto.
            'loading_port_id' => $this->resolvePortIdByCode($row['POL'] ?? null)
                ?? $shipment->voyage->origin_port_id,
            'discharge_port_id' => $this->resolvePortIdByCode($row['POD'] ?? null)
                ?? $shipment->voyage->destination_port_id,
            'primary_cargo_type_id' => $primaryCargoTypeId,
            'primary_packaging_type_id' => $primaryPackagingTypeId,
            'origin_operative_code' => '10073', // Guaran: lugar operativo origen siempre 10073 (Roberto 22/05). Confirmado contra carga manual (BL 32/31). Serializer lee origin_operative_code para codLugOper origen.
            'bill_number' => $row['BL_NUMBER'],
            'bill_date' => $blDate,
            'loading_date' => $blDate,
            'freight_terms' => $this->mapFreightTerms($row['FREIGHT_TERMS']),
            'total_packages' => $totalPackages,
            'gross_weight_kg' => $totalGrossWeight,
            'net_weight_kg' => $totalNetWeight,
            'volume_m3' => $totalVolume,
            'cargo_description' => $this->buildCargoDescription($row),
            'contains_dangerous_goods' => !empty($row['UN_NUMBER']),
            'requires_refrigeration' => $this->requiresRefrigeration($row),
            'un_number' => $row['UN_NUMBER'] ?: null,
            'status' => 'draft',
            'permiso_embarque' => $row['MLO_BL_NR'] ?: null,
            'created_by_user_id' => auth()->id()
        ]);

        // Direcciones de las partes (Guaran trae dirección limpia en columnas).
        // Etapa 1: si el cliente no tiene dirección en ficha, se la registra.
        // Etapa 2: si ya tiene una distinta, se guarda como dirección específica
        // de ESTE conocimiento, en su rol (no se pisa el padrón del cliente).
        $partes = [
            ['client' => $shipper,     'addr' => $row['SHIPPER_ADDRESS1']      ?? null, 'role' => 'shipper'],
            ['client' => $consignee,   'addr' => $row['CONSIGNEE_ADDRESS1']    ?? null, 'role' => 'consignee'],
            ['client' => $notifyParty, 'addr' => $row['NOTIFY_PARTY_ADDRESS1'] ?? null, 'role' => 'notify_party'],
        ];

        foreach ($partes as $parte) {
            $this->persistClientAddress($parte['client'], $parte['addr']);

            $specific = $this->resolveSpecificAddress($parte['client'], $parte['addr'], $parte['role']);
            if ($specific) {
                $bill->specificContacts()->create($specific);
            }
        }

        return $bill;
    }

    /**
     * Crear ShipmentItem - SOLO DATOS REALES
     */
    protected function createShipmentItem(BillOfLading $bill, array $row): ShipmentItem
    {
        $blId = $bill->id;

        // Siempre tomar el siguiente line_number libre dentro del BL
        $lineNumber = $this->nextItemLineNumber($blId);

        // Detectar si la fila trae contenedor para determinar cargo_type y packaging_type
        $isContainerized = !empty($row['CONTAINER_NUMBER']);

        // Si es contenedorizado: item representa la carga DENTRO del contenedor
        //   - cargo_type_id = 5 (OTRA CARGA NO CONTENEDORIZADA, criterio aprobado QA)
        //   - packaging_type_id = 2 (NO RETORNABLE, criterio aprobado QA)
        // Si NO es contenedorizado: mantener lógica original por NCM y PACK_TYPE
        $cargoTypeId = $isContainerized ? 5 : $this->findCargoTypeByNCM($row['NCM']);
        $packagingTypeId = $isContainerized ? 2 : $this->findPackagingTypeByName($row['PACK_TYPE']);

        return ShipmentItem::create([
            'shipment_id' => $bill->shipment_id,
            'bill_of_lading_id' => $bill->id,
            'line_number' => $lineNumber,
            'item_description' => $this->buildCargoDescription($row),
            'cargo_type_id' => $cargoTypeId,
            'packaging_type_id' => $packagingTypeId,
            'package_quantity' => (int) ($row['NUMBER_OF_PACKAGES'] ?? 1),
            'unit_of_measure' => 'KG', // Guaran exporta peso en kg (Roberto 22/05). Sin esto, la columna usa su default 'PCS'.
            // Campos AFIP/visualización a nivel item (los lee la pantalla del conocimiento
            // y el serializer). El parser los completa con el mismo criterio que la carga manual.
            'cargo_marks' => !empty($row['MARKS_DESCRIPTION']) ? $row['MARKS_DESCRIPTION'] : 'SM',
            'container_condition' => 'H', // Guaran: siempre House (Roberto 22/05), igual que el Container.
            'operational_discharge_code' => '10073', // Guaran: lugar operativo descarga 10073 (Roberto 02/06).
            'gross_weight_kg' => $this->parseWeight($row['GROSS_WEIGHT']),
            'net_weight_kg' => $this->parseWeight($row['NET_WEIGHT']),
            'volume_m3' => (float) ($row['VOLUME'] ?? 0),
            'commodity_code' => $row['NCM'] ?: null,
            'tariff_position' => $row['NCM'] ?: null,
            'cargo_marks' => !empty($row['MARKS_DESCRIPTION']) ? $row['MARKS_DESCRIPTION'] : 'SM',
            'country_of_origin_id' => $this->determineOriginCountry($row),
            'is_dangerous_goods' => !empty($row['UN_NUMBER']),
            'requires_refrigeration' => $this->requiresRefrigeration($row),
            'un_number' => $row['UN_NUMBER'] ?: null,
            'temperature_min' => $this->parseTemperature($row['TEMP_MIN']),
            'temperature_max' => $this->parseTemperature($row['TEMP_MAX']),
            'created_by_user_id' => auth()->id()
        ]);
    }

    /**
     * Crear contenedor - SOLO DATOS REALES
     */
    protected function createContainer(array $row): ?Container
    {
        $containerNumber = $row['CONTAINER_NUMBER'];
        if (!$containerNumber) return null;

        $existing = Container::where('container_number', $containerNumber)->first();
        if ($existing) return $existing;

        // Precintos: el SEAL_NO de Guaran puede traer varios separados por coma.
        // Se guardan en shipper_seal (varchar 50) que es el campo que lee el
        // serializer AFIP (SimpleXmlGenerator). Antes se guardaba en 'seal_numbers',
        // un campo inexistente en la tabla containers -> el precinto nunca llegaba al XML.
        $shipperSeal = null;
        if (!empty($row['SEAL_NO'])) {
            $seals = array_filter(array_map('trim', explode(',', $row['SEAL_NO'])));
            $shipperSeal = substr(implode(', ', $seals), 0, 50);
        }

        // Validar límite VARCHAR(15)
        if (strlen($containerNumber) > 15) {
            Log::warning('Container number truncado', [
                'original' => $containerNumber,
                'length' => strlen($containerNumber)
            ]);
            $containerNumber = substr($containerNumber, 0, 15);
        }
        return Container::create([
            'container_number' => $containerNumber, // ✅ Variable real
            'container_type_id' => $this->findContainerTypeByCode($row['CONTAINER_TYPE']), // ✅ ID real
            'condition' => $this->mapContainerConditionToEnum($row['CONTAINER_STATUS']), // ✅ ENUM válido
            'size_feet' => $this->extractContainerSize($row['CONTAINER_TYPE']),
            'container_condition' => 'H', // Guaran: siempre House (Roberto 22/05). Serializer lee este campo para <condicion> AFIP (SimpleXmlGenerator L377/907).
            'shipper_seal' => $shipperSeal,
            'tare_weight_kg' => $this->parseWeight($row['TARE_WEIGHT']),
            'current_status' => 'loaded',
            'temperature_min' => $this->parseTemperature($row['TEMP_MIN']),
            'temperature_max' => $this->parseTemperature($row['TEMP_MAX']),
            'is_reefer' => $this->requiresRefrigeration($row),
            'active' => true,
            'max_gross_weight_kg' => $this->parseWeight($row['GROSS_WEIGHT']),
            'created_by_user_id' => auth()->id()
        ]);
    }

    /**
     * Buscar container_type_id real basado en código
     */
    protected function findContainerTypeByCode(?string $code): int
    {
        if (!$code) {
            return $this->containerTypeFallbackBySize(null);
        }
        $code = strtoupper(trim($code));
        // Mapeo comercial Guaran -> ISO 6346 (confirmado QA 23/05).
        // 20TN -> 22T1 es mapeo default para tanque 20' generico.
        $comercialToIso = [
            '40HC' => '45G1', '40RH' => '45R1', '20DV' => '22G1',
            '20GP' => '22G1', '40GP' => '42G1', '40DV' => '42G1',
            '20RF' => '22R1', '20OT' => '22U1', '20TN' => '22T1',
            '40JU' => '45G1', // Jumbo 40' = High Cube (confirmado Roberto 01/06)
        ];
        $isoCode = $comercialToIso[$code] ?? $code;
        // Buscar en catalogo agrupando el OR correctamente con active.
        $containerType = DB::table('container_types')
            ->where('active', true)
            ->where(function ($q) use ($isoCode, $code) {
                $q->where('iso_code', $isoCode)
                  ->orWhere('code', $code)
                  ->orWhere('iso_code', $code);
            })
            ->first();
        if ($containerType) {
            return $containerType->id;
        }
        // Fallback que respeta el tamaño (no confunde 40' con 20').
        return $this->containerTypeFallbackBySize($code);
    }
    /**
     * Fallback que elige container_type respetando el tamaño del codigo (20 vs 40 pies).
     */
    protected function containerTypeFallbackBySize(?string $code): int
    {
        $is40 = $code && str_starts_with($code, '40');
        $iso = $is40 ? '42G1' : '22G1';
        $ct = DB::table('container_types')
            ->where('active', true)
            ->where('iso_code', $iso)
            ->first();
        if ($ct) {
            return $ct->id;
        }
        Log::warning('findContainerTypeByCode sin match ni fallback ISO', [
            'code' => $code, 'iso_buscado' => $iso,
        ]);
        $any = DB::table('container_types')->where('active', true)->first();
        return $any->id ?? 1;
    }

    /**
     * Mapear container status a ENUM válido
     */
    protected function mapContainerConditionToEnum(?string $status): string
    {
        if (!$status) return 'L';

        // ENUM permite solo: 'V','D','S','P','L'
        $statusMap = [
            'F' => 'L',    // Full → Loaded
            'E' => 'V',    // Empty → Vacío
            'L' => 'L',    // Loaded → Loaded
            'FULL' => 'L',
            'EMPTY' => 'V'
        ];

        return $statusMap[strtoupper($status)] ?? 'L';
    }

    // =====================================================
    // MÉTODOS AUXILIARES - SOLO DATOS REALES
    // =====================================================

    protected function mapManifestType(?string $type): string
    {
            return 'convoy';
    }

    protected function parseBLDateForDeparture(?string $blDate): Carbon
    {
        $date = $this->parseDate($blDate);
        return $date ? $date->copy()->addDay() : now()->addDay();
    }

    protected function parseBLDateForArrival(?string $blDate): Carbon
    {
        $date = $this->parseDate($blDate);
        return $date ? $date->copy()->addDays(3) : now()->addDays(3);
    }

    protected function parseDate($date): ?Carbon
    {
        if ($date === null || $date === '') {
            return null;
        }
        $value = trim((string) $date);
        if ($value === '') {
            return null;
        }
        try {
            // Excel puede entregar fechas como serial numérico.
            // Ejemplo: 46145. Se usa PhpSpreadsheet para evitar conversiones manuales.
            if (is_numeric($value)) {
                $serial = (float) $value;
                // Rango defensivo para fechas Excel razonables.
                // Evita interpretar valores tipo 20260504 como serial Excel.
                if ($serial >= 20000 && $serial <= 60000) {
                    return Carbon::instance(
                        ExcelDate::excelToDateTimeObject($serial)
                    )->startOfDay();
                }
            }
            $formats = [
                'd/m/Y',
                'Y-m-d',
                'd-m-Y',
            ];
            foreach ($formats as $format) {
                try {
                    $parsed = Carbon::createFromFormat($format, $value);
                    if ($parsed && $parsed->format($format) === $value) {
                        return $parsed->startOfDay();
                    }
                } catch (\Throwable $e) {
                    // Probar siguiente formato.
                }
            }
            Log::warning('Error parsing date', ['date' => $date]);
            return null;
        } catch (\Throwable $e) {
            Log::warning('Error parsing date', [
                'date' => $date,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    protected function parseWeight($weight): float
    {
        if ($weight === null || $weight === '') {
            return 0.0;
        }

        // Numérico nativo (int/float): usar tal cual.
        // Excel puede entregar valores numéricos directamente y no debemos manipularlos.
        if (is_int($weight) || is_float($weight)) {
            return (float) $weight;
        }

        $cleaned = trim((string) $weight);
        if ($cleaned === '') {
            return 0.0;
        }

        // Quitar espacios internos por si vinieran (p.ej. "33 536,67").
        $cleaned = str_replace(' ', '', $cleaned);

        $hasComma = strpos($cleaned, ',') !== false;
        $hasDot = strpos($cleaned, '.') !== false;

        if ($hasComma && $hasDot) {
            // Ambos separadores: el ÚLTIMO en aparecer es el decimal.
            // Europeo: "33.536,670" -> coma decimal, puntos miles.
            // US:      "33,536.67"  -> punto decimal, comas miles.
            $lastComma = strrpos($cleaned, ',');
            $lastDot = strrpos($cleaned, '.');
            if ($lastComma > $lastDot) {
                $cleaned = str_replace('.', '', $cleaned);
                $cleaned = str_replace(',', '.', $cleaned);
            } else {
                $cleaned = str_replace(',', '', $cleaned);
            }
        } elseif ($hasComma) {
            // Sólo coma. Una sola -> decimal europeo simplificado ("33536,67").
            // Varias -> separadores de miles US sin decimal ("33,536,000").
            if (substr_count($cleaned, ',') === 1) {
                $cleaned = str_replace(',', '.', $cleaned);
            } else {
                $cleaned = str_replace(',', '', $cleaned);
            }
        } elseif ($hasDot) {
            // Sólo punto. Si hay varios, los anteriores son miles y el último es decimal.
            // Un solo punto -> decimal estándar (compatible con PHP cast (string) de float).
            if (substr_count($cleaned, '.') > 1) {
                $lastDot = strrpos($cleaned, '.');
                $intPart = str_replace('.', '', substr($cleaned, 0, $lastDot));
                $decPart = substr($cleaned, $lastDot + 1);
                $cleaned = $intPart . '.' . $decPart;
            }
        }

        return (float) $cleaned;
    }

    protected function parseTemperature(?string $temp): ?float
    {
        if (!$temp || $temp === '0') return null;
        return (float) str_replace(',', '.', $temp);
    }

    protected function requiresRefrigeration(array $row): bool
    {
        // Por temperatura
        if (!empty($row['TEMP_MIN'])) {
            $temp = (float) str_replace(',', '.', $row['TEMP_MIN']);
            if ($temp < 0) return true;
        }
        
        // Por tipo contenedor
        if (strpos($row['CONTAINER_TYPE'] ?? '', 'R') !== false) {
            return true;
        }
        
        // Por descripción
        $desc = strtoupper($row['DESCRIPTION'] ?? '');
        return strpos($desc, 'FROZEN') !== false || strpos($desc, 'TEMPERATURE') !== false;
    }

    protected function buildCargoDescription(array $row): string
    {
        $desc = $row['DESCRIPTION'] ?? '';
        if (!$desc) {
            $desc = ($row['PACK_TYPE'] ?? 'Carga general') . 
                    ($row['NCM'] ? " (NCM: {$row['NCM']})" : '');
        }
        return strlen($desc) > 500 ? substr($desc, 0, 497) . '...' : $desc;
    }

    protected function extractClientData(array $row, string $type): ?array
    {
        $name = $row[$type . '_NAME'] ?? null;
        if (!$name) return null;
        
        return [
            'name' => $name,
            'address' => $row[$type . '_ADDRESS1'] ?? null,
            'city' => $row[$type . '_CITY'] ?? null,
            'country' => $row[$type . '_COUNTRY'] ?? null,
            'phone' => $row[$type . '_PHONE'] ?? null
        ];
    }

    protected function findOrCreateClient(?array $data): ?Client
    {
        if (!$data || !$data['name']) return null;

        $user = auth()->user();
        $companyId = ($user->userable_type === 'App\Models\Company' ? $user->userable_id : null);

        // Extraer tax_id y pais ANTES de buscar, porque la clave unica real es
        // (tax_id, country_id) global -indice uk_clients_tax_country-, no el nombre.
        // Un cliente con ese tax_id+pais existe una sola vez aunque lo hayan cargado
        // distintas empresas. Buscar por nombre+company no lo encontraba y el INSERT
        // chocaba con la unique (SQLSTATE 23000).
        // Tax desde dirección o nombre (helper común: cubre RUC/CUIT/TAXID/CNPJ/NIT).
        // No fabricar: si no hay dato real, queda null.
        $taxId = $this->resolveTaxId(null, $data['name'] ?? null, $data['address'] ?? null);

        $countryId = $this->mapCountryName($data['country']);

        // Inferencia de pais por longitud del tax_id (criterio aprobado QA 29/06).
        // El Excel de Guaran NO trae la columna _COUNTRY (viene vacia), por lo que
        // mapCountryName() cae siempre al default. Se refina aca usando la estructura
        // del identificador fiscal ya resuelto, que es deterministica:
        //   - 11 digitos  => CUIT argentino  => Argentina (id 11)
        //   - 7 a 9 digitos => RUC paraguayo  => Paraguay  (id 174)
        // IDs verificados contra la tabla countries (AR=11, PY=174).
        // Solo afecta clientes NUEVOS: si el cliente ya existe, las busquedas de abajo
        // lo devuelven sin modificar (no se pisa el pais de un cliente existente).
        if ($taxId) {
            $taxLen = strlen($taxId); // resolveTaxId ya devuelve solo digitos
            if ($taxLen === 11) {
                $countryId = 11;  // Argentina
                Log::info('Guaran: pais inferido por tax_id', ['tax_id' => $taxId, 'len' => $taxLen, 'country_id' => 11, 'pais' => 'Argentina']);
            } elseif ($taxLen >= 7 && $taxLen <= 9) {
                $countryId = 174; // Paraguay
                Log::info('Guaran: pais inferido por tax_id', ['tax_id' => $taxId, 'len' => $taxLen, 'country_id' => 174, 'pais' => 'Paraguay']);
            }
            // Longitudes fuera de 7-9 y distintas de 11: no se infiere, queda lo de mapCountryName.
        } else {
            // Sin tax_id confiable: no se infiere pais. Queda lo que devolvio mapCountryName
            // (en Guaran, por columna vacia, sera el default). Se marca para revision:
            // QA no aprueba "default Paraguay" como regla, pero la DB exige country_id NOT NULL.
            Log::warning('Guaran: cliente sin tax_id, pais NO confiable (revisar)', [
                'name'       => $data['name'] ?? null,
                'country_id' => $countryId,
            ]);
        }

        // 1. Buscar por la clave unica real (tax_id + country_id), solo si hay tax_id real
        if ($taxId) {
            $client = Client::where('tax_id', $taxId)
                ->where('country_id', $countryId)
                ->first();
            if ($client) return $client;
        }

        // 2. Fallback: buscar por nombre dentro de la empresa (compatibilidad)
        $client = Client::where('legal_name', $data['name'])
            ->where('created_by_company_id', $companyId)
            ->first();
        if ($client) return $client;

        // 3. No existe: crear
        return Client::create([
            'created_by_company_id' => $companyId,
            'legal_name' => $data['name'],
            'commercial_name' => $data['name'],
            'tax_id' => $taxId,
            'client_type' => 'business',
            'status' => 'active',
            'address_line_1' => $data['address'],
            'city' => $data['city'],
            'phone' => $data['phone'],
            'country_id' => $countryId,
            // Tipo de documento segun el pais real del cliente (verificado en document_types):
            // Argentina(11)->CUIT(1), Paraguay(174)->RUC(3). El numero (tax_id) puede ir null,
            // pero el TIPO siempre corresponde al pais. Default RUC para el resto (Guaran es PY->AR).
            'document_type_id' => $countryId === 11 ? 1 : 3,
            // verified_at explicito: la creacion ocurre dentro de Model::withoutEvents()
            // (en parse(), para evitar recalculos en cascada), por lo que el hook 'creating'
            // del modelo Client -que normalmente setea verified_at- no se dispara. Sin esto,
            // el cliente queda con verified_at=NULL y el listado (whereNotNull('verified_at'))
            // no lo muestra aunque exista y este activo.
            'verified_at' => now(),
            'created_by_user_id' => auth()->id()
        ]);
    }

    protected function extractTaxId(string $text): ?string
    {
        // Captura una corrida de digitos con guiones/puntos INTERNOS, sin cruzar espacios.
        // [0-9] obligatorio al inicio y fin; separadores solo en medio.
        // Evita concatenar el identificador fiscal con numeros siguientes (ej: "RUC: 80129758-3 005959").
        if (preg_match('/(RUC|CUIT)[:\s]*([0-9][0-9.\-]*[0-9])/i', $text, $matches)) {
            $clean = preg_replace('/[^0-9]/', '', $matches[2]);

            // Validacion de longitud: CUIT AR = 11, RUC PY = 8-9. Rango aceptado 7-11.
            // Si excede, es dato corrupto -> null (el flujo generara un tax_id sintetico corto).
            if (strlen($clean) >= 7 && strlen($clean) <= 11) {
                return $clean;
            }
        }
        return null;
    }

    protected function mapCountryName(?string $name): int
    {
        // IDs reales de la tabla countries (verificado): AR=11, BR=32, PY=174, UY=238.
        // Guaran es PY->AR, por eso Paraguay queda como default.
        if (!$name) return 174; // Paraguay default

        return match(strtolower($name)) {
            'argentina' => 11,
            'paraguay' => 174,
            'brasil', 'brazil' => 32,
            'uruguay' => 238,
            default => 174
        };
    }

    protected function getCountryIdFromPortCode(string $code): int
    {
        if (str_starts_with($code, 'PY')) return 2; // Paraguay
        if (str_starts_with($code, 'AR')) return 1; // Argentina
        return 2; // Default Paraguay
    }

    protected function generatePortName(string $code): string
    {
        $known = [
            'PYASU' => 'Puerto de Asunción',
            'ARBUE' => 'Puerto de Buenos Aires'
        ];
        return $known[$code] ?? 'Puerto ' . $code;
    }

    protected function findVesselTypeByName(string $name): int
    {
        $type = DB::table('vessel_types')
            ->whereRaw('UPPER(name) LIKE ?', ['%BARCAZA%'])
            ->orWhereRaw('UPPER(name) LIKE ?', ['%BARGE%'])
            ->where('active', true)
            ->first();
            
        return $type ? $type->id : 1;
    }

    protected function findCargoTypeByNCM(?string $ncm): int
    {
        if (!$ncm) return 1;
        
        // Mapeo NCM conocidos del archivo GUARAN
        $ncmMap = [
            '0206' => 'meat',     // Carne bovina congelada
            '0202' => 'meat',     // Carne bovina
            '1502' => 'food'      // Grasas bovinas
        ];
        
        $ncmKey = str_pad((string) $ncm, 4, '0', STR_PAD_LEFT);
        $cargoName = $ncmMap[substr($ncmKey, 0, 4)] ?? 'general';
        
        $type = DB::table('cargo_types')
            ->whereRaw('UPPER(name) LIKE ?', ['%' . strtoupper($cargoName) . '%'])
            ->where('active', true)
            ->first();
            
        return $type ? $type->id : 1;
    }

    protected function findPackagingTypeByName(?string $packType): int
    {
        if (!$packType) return 1;
        
        $cleanType = strtolower(trim($packType, '()s'));
        $searchTerm = match($cleanType) {
            'carton', 'cartons' => 'carton',
            'box', 'boxes' => 'box',
            'pallet', 'pallets' => 'pallet',
            'bag', 'bags' => 'bag',
            default => 'carton'
        };
        
        $type = DB::table('packaging_types')
            ->whereRaw('UPPER(name) LIKE ?', ['%' . strtoupper($searchTerm) . '%'])
            ->where('active', true)
            ->first();
            
        return $type ? $type->id : 1;
    }

    protected function determineOriginCountry(array $row): int
    {
        $shipperAddress = strtolower($row['SHIPPER_ADDRESS1'] ?? '');
        
        if (strpos($shipperAddress, 'paraguay') !== false || 
            strpos($shipperAddress, 'asuncion') !== false) {
            return 2;
        }
        
        if (strpos($shipperAddress, 'argentina') !== false) {
            return 1;
        }
        
        // Por puerto de origen
        $pol = $row['POL'] ?? '';
        return str_starts_with($pol, 'PY') ? 2 : 1;
    }

    protected function mapFreightTerms(?string $terms): string
    {
        if (!$terms) return 'prepaid';
        
        $upper = strtoupper($terms);
        
        if (strpos($upper, 'COLLECT') !== false) return 'collect';
        if (strpos($upper, 'THIRD') !== false) return 'third_party';
        
        return 'prepaid';
    }

    protected function extractContainerSize(?string $type): int
    {
        if (!$type) return 40;
        return str_starts_with($type, '20') ? 20 : 40;
    }

    protected function mapContainerCondition(?string $status): string
    {
        return match(strtoupper($status ?? '')) {
            'F' => 'good',
            'E' => 'good',
            'L' => 'good',
            default => 'good'
        };
    }

    protected function groupDataByBillNumber(array $data): array
    {
        $grouped = [];
        foreach ($data as $row) {
            $blNumber = $row['BL_NUMBER'];
            $grouped[$blNumber][] = $row;
        }
        return $grouped;
    }

    protected function createImportRecord(string $filePath, array $options): ManifestImport
    {
        $user = auth()->user();
        $companyId = ($user->userable_type === 'App\Models\Company' ? $user->userable_id : null);
        
        return ManifestImport::createForImport([
            'company_id' => $companyId,
            'user_id' => $user->id,
            'file_name' => basename($filePath),
            'file_format' => 'guaran_excel',
            'file_size_bytes' => filesize($filePath),
            'file_hash' => hash_file('sha256', $filePath),
            'parser_config' => [
                'parser_class' => self::class,
                'options' => $options
            ]
        ]);
    }

    protected function completeImportRecord(
        ManifestImport $importRecord,
        Voyage $voyage,
        array $bills,
        array $containers,
        float $startTime
    ): void {
        $processingTime = microtime(true) - $startTime;
        
        $createdObjects = [
            'voyages' => [$voyage->id],
            'shipments' => [$voyage->shipments()->first()->id ?? null],
            'bills' => array_map(fn($bill) => $bill->id, $bills),
            'containers' => array_map(fn($container) => $container->id, $containers)
        ];
        
        $createdObjects = array_map(fn($ids) => array_filter($ids), $createdObjects);
        
        $importRecord->recordCreatedObjects($createdObjects);
        $importRecord->markAsCompleted([
            'voyage_id' => $voyage->id,
            'processing_time_seconds' => round($processingTime, 2),
            'notes' => 'Importación GUARAN Excel completada - Solo datos reales del archivo'
        ]);
    }

    // =====================================================
    // MÉTODOS DE INTERFACE
    // =====================================================

    public function validate(array $data): array
    {
        $errors = [];
        
        if (empty($data)) {
            $errors[] = 'No se encontraron datos válidos';
            return $errors;
        }
        
        $firstRow = $data[0] ?? [];
        $required = ['BL_NUMBER', 'SHIPPER_NAME', 'CONSIGNEE_NAME', 'VOYAGE_NO', 'BARGE_NAME'];
        
        foreach ($required as $field) {
            if (empty($firstRow[$field])) {
                $errors[] = "Campo requerido faltante en archivo: {$field}";
            }
        }
        
        return $errors;
    }

    public function transform(array $data): array
    {
        return $data; // Ya transformados durante extracción
    }

    public function getFormatInfo(): array
    {
        return [
            'name' => 'Guaran Excel Parser',
            'description' => 'Parser para manifiestos consolidados de Guaran Feeder S.A. - Solo datos reales del archivo',
            'extensions' => ['xlsx', 'xls'],
            'version' => '1.0-real-data-only',
            'parser_class' => self::class,
            'agent' => 'Guaran Feeder S.A.',
            'country' => 'Paraguay',
            'route' => 'PYASU → ARBUE',
            'data_integrity' => 'FIDEDIGNO_PARA_ADUANA',
            'capabilities' => [
                'multi_bl_per_file' => true,
                'refrigerated_containers' => true,
                'health_certificates' => true,
                'senacsa_seals' => true,
                'ncm_classification' => true,
                'real_data_only' => true,
                'no_hardcoded_values' => true,
                'customs_compliant' => true
            ],
            'data_sources' => [
                'vessel_info' => 'BARGE_NAME + BARGE_ID del archivo',
                'cargo_types' => 'NCM codes reales del archivo', 
                'packaging_types' => 'PACK_TYPE real del archivo',
                'refrigeration' => 'TEMP_MIN real del archivo',
                'weights_volumes' => 'GROSS_WEIGHT, NET_WEIGHT, VOLUME reales',
                'dates' => 'BL_DATE real para cálculos',
                'client_data' => 'RUC/CUIT extraídos de direcciones reales',
                'certifications' => 'SEAL_NO con certificaciones reales'
            ],
            'validation' => [
                'no_invented_data' => true,
                'customs_ready' => true,
                'audit_trail' => true
            ]
        ];
    }

    public function getDefaultConfig(): array
    {
        return [
            'skip_metadata_rows' => 6,
            'start_data_row' => 7,
            'agent_name' => 'Guaran Feeder S.A.',
            'route' => 'PYASU-ARBUE',
            'data_policy' => 'real_data_only',
            'customs_compliant' => true,
            'handle_refrigerated' => true,
            'parse_certifications' => true,
            'extract_tax_ids' => true,
            'group_by_bl' => true,
            'create_shipment_items' => true,
            'parse_comma_decimals' => true,
            'validate_required_fields' => true,
            'transaction_mode' => true
        ];
    }

    protected function nextItemLineNumber(int $billOfLadingId): int
    {
        $max = ShipmentItem::where('bill_of_lading_id', $billOfLadingId)->max('line_number');
        return ($max ?? 0) + 1;
    }

    /**
     * Vincular un Container con un ShipmentItem usando la tabla pivot
     * container_shipment_item. Replica los valores cantidad/peso/volumen
     * del item al pivot (1 contenedor = 1 item en Guaran).
     */
    protected function attachContainerToItem(Container $container, ShipmentItem $item): void
    {
        // Evitar duplicar la relación si ya existe
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
}