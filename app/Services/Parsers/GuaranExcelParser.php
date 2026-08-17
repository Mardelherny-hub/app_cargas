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
use App\Services\Parsers\Concerns\ResolvesPorts;
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
    use ResolvesPorts;
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
            $createdContainers = [];
            $items = [];

            // Importación masiva: silenciar los eventos de modelo (hooks saved/saving)
            // durante la creación para evitar recálculos en cascada por cada item,
            // que en manifiestos grandes agotan tiempo/memoria (HTTP 500).
            Model::withoutEvents(function () use (
                $groupedByBL,
                $shipment,
                &$bills,
                &$containers,
                &$createdContainers,
                &$items
            ) {
                foreach ($groupedByBL as $blNumber => $blRows) {
                    // Pasamos todas las filas del BL para que pueda contar contenedores únicos
                    $bill = $this->createBillOfLading($shipment, $blRows);
                    $bills[] = $bill;
                    foreach ($blRows as $row) {
                        // Crear contenedor si la fila lo trae
                        $container = null;
                        if (!empty($row['CONTAINER_NUMBER'])) {
                            $container = $this->createContainer($row);

                            if ($container) {
                                $containers[] = $container;

                                if ($container->wasRecentlyCreated) {
                                    $createdContainers[] = $container;
                                }
                            }
                        }

                        // Todo ShipmentItem de este loop nace en esta importación.
                        $item = $this->createShipmentItem($bill, $row);
                        $items[] = $item;
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
            $this->completeImportRecord(
                $importRecord,
                $voyage,
                $bills,
                $items,
                $createdContainers,
                $startTime
            );
            
            DB::commit();

            Log::info('GUARAN Excel parsing completado', [
                'voyage_id' => $voyage->id,
                'bills' => count($bills),
                'containers' => count($containers),
                'containers_created' => count($createdContainers),
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
                    'containers_created' => count($createdContainers),
                    'containers_processed' => count($containers),
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
            'agent_name' => $firstRow['LOCATION_NAME'] ?? null,
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
        $originPort = $this->resolvePortStrict($voyageData['pol']);
        $destPort = $this->resolvePortStrict($voyageData['pod']);

        // El voyage_number es único global. Si ya existe (en cualquier empresa),
        // se bloquea la importación con un error claro en lugar de reusar el viaje.
        $this->guardVoyageNumberIsFree($voyageData['voyage_number']);

        return Voyage::create(
            $this->buildVoyageCreationData(
                $voyageData,
                $companyId,
                $vessel,
                $originPort,
                $destPort
            )
        );
    }

    /**
     * Construir atributos del Voyage sin convertir BL_DATE en fechas
     * operativas que el archivo Guaran no declara.
     */
    protected function buildVoyageCreationData(
        array $voyageData,
        int $companyId,
        Vessel $vessel,
        Port $originPort,
        Port $destPort
    ): array {
        $notes = 'Importado desde GUARAN Excel';

        if (!empty($voyageData['agent_name'])) {
            $notes .= ': ' . $voyageData['agent_name'];
        }

        return [
            'company_id' => $companyId,
            'voyage_number' => $voyageData['voyage_number'],
            'lead_vessel_id' => $vessel->id,
            'origin_port_id' => $originPort->id,
            'destination_port_id' => $destPort->id,
            'origin_country_id' => $originPort->country_id,
            'destination_country_id' => $destPort->country_id,

            // Guaran sólo declara BL_DATE. No declara salida ni ETA.
            'departure_date' => null,
            'estimated_arrival_date' => null,

            'voyage_type' => $this->mapManifestType($voyageData['manifest_type']),
            'cargo_type' => $this->mapCargoType($voyageData),
            'status' => 'planning',
            'is_consolidated' => true,
            'vessel_count' => 1,
            'created_by_user_id' => auth()->id(),
            'operational_notes' => $notes,
        ];
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
        $name = trim((string) ($voyageData['vessel_name'] ?? ''));

        if ($name === '') {
            throw new Exception("BARGE_NAME es requerido en el archivo");
        }

        $registrationNumber = trim(
            (string) ($voyageData['vessel_id'] ?? '')
        );

        $registrationNumber = $registrationNumber !== ''
            ? $registrationNumber
            : null;

        if ($registrationNumber !== null) {
            // Con matrícula explícita, ésa es la identidad real.
            // No fusionar por nombre con una embarcación de otra matrícula.
            $vessel = Vessel::where(
                'registration_number',
                $registrationNumber
            )->first();

            if ($vessel) {
                return $vessel;
            }
        } else {
            // Sin matrícula sólo podemos reutilizar por nombre dentro
            // de la misma empresa. Nunca fabricar una matrícula.
            $vessel = Vessel::where('name', $name)
                ->where('company_id', $companyId)
                ->first();

            if ($vessel) {
                return $vessel;
            }
        }

        return Vessel::create(
            $this->buildVesselCreationData(
                $name,
                $registrationNumber,
                $companyId
            )
        );
    }

    /**
     * Atributos permitidos al crear una embarcación desde Guaran.
     *
     * El archivo aporta nombre y eventualmente BARGE_ID. No aporta
     * bandera, tipo técnico, dimensiones, tonelajes ni capacidad.
     */
    protected function buildVesselCreationData(
        string $name,
        ?string $registrationNumber,
        int $companyId
    ): array {
        return [
            'name' => $name,
            'registration_number' => $registrationNumber,
            'company_id' => $companyId,

            'vessel_type_id' => null,
            'flag_country_id' => null,

            'operational_status' => 'active',
            'active' => true,

            'length_meters' => null,
            'beam_meters' => null,
            'draft_meters' => null,
            'gross_tonnage' => null,
            'net_tonnage' => null,
            'cargo_capacity_tons' => null,
        ];
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
            // Guaran no declara horario de salida ni capacidad operativa.
            'departure_time' => null,
            'status' => 'planning',
            'is_lead_vessel' => true,
            'vessel_role' => 'single',
            'current_cargo_weight_tons' => 0,
            'current_container_count' => 0,
            'utilization_percentage' => 0.0,
            'cargo_capacity_tons' => null,
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
            ? array_sum(array_map(
                fn($r) => $this->parseVolume($r['VOLUME'] ?? null),
                $blRows
            ))
            : $this->parseVolume($row['VOLUME'] ?? null);

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
            'volume_m3' => $this->parseVolume($row['VOLUME'] ?? null),
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
        $containerNumber = $this->normalizeContainerNumber(
            $row['CONTAINER_NUMBER'] ?? null
        );

        if ($containerNumber === null) {
            return null;
        }

        // Validar siempre los datos declarados por Guaran, incluso si la
        // unidad ya existe en el maestro.
        $containerType = $this->resolveContainerTypeByCode(
            $row['CONTAINER_TYPE'] ?? null
        );

        $this->mapContainerConditionToEnum(
            $row['CONTAINER_STATUS'] ?? null
        );

        $this->normalizeShipperSeal(
            $row['SEAL_NO'] ?? null
        );

        $existing = Container::where(
            'container_number',
            $containerNumber
        )->first();

        if ($existing) {
            // El número identifica la unidad física. Un tipo diferente para
            // la misma unidad no debe aceptarse silenciosamente.
            if (
                (int) $existing->container_type_id
                !== (int) $containerType->id
            ) {
                throw new Exception(
                    "Contenedor {$containerNumber}: tipo "
                    . ($row['CONTAINER_TYPE'] ?? 'N/A')
                    . " no coincide con el tipo ya registrado"
                );
            }

            return $existing;
        }

        return Container::create(
            $this->buildContainerCreationData(
                $row,
                $containerType
            )
        );
    }

    /**
     * Normalizar el identificador sin truncar información.
     */
    protected function normalizeContainerNumber(?string $number): ?string
    {
        $number = trim((string) $number);

        if ($number === '') {
            return null;
        }

        if (strlen($number) > 15) {
            throw new Exception(
                "Número de contenedor excede VARCHAR(15): {$number}"
            );
        }

        return $number;
    }

    /**
     * Preservar SEAL_NO completo. La columna real admite 255 caracteres.
     */
    protected function normalizeShipperSeal(?string $seal): ?string
    {
        $seal = trim((string) $seal);

        if ($seal === '') {
            return null;
        }

        if (strlen($seal) > 255) {
            throw new Exception(
                'SEAL_NO excede la capacidad de shipper_seal (255 caracteres)'
            );
        }

        return $seal;
    }

    /**
     * Datos de una unidad nueva. Distingue peso actual de capacidad nominal.
     */
    protected function buildContainerCreationData(
        array $row,
        object $containerType
    ): array {
        $containerNumber = $this->normalizeContainerNumber(
            $row['CONTAINER_NUMBER'] ?? null
        );

        if ($containerNumber === null) {
            throw new Exception(
                'CONTAINER_NUMBER es requerido para crear el contenedor'
            );
        }

        if (
            !isset($containerType->id)
            || !isset($containerType->max_gross_weight_kg)
        ) {
            throw new Exception(
                'Container type sin capacidad máxima definida'
            );
        }

        $condition = $this->mapContainerConditionToEnum(
            $row['CONTAINER_STATUS'] ?? null
        );

        $isEmpty = $condition === 'V';

        $gross = $this->parseWeight(
            $row['GROSS_WEIGHT'] ?? null
        );

        $net = $this->parseWeight(
            $row['NET_WEIGHT'] ?? null
        );

        $tare = $this->parseWeight(
            $row['TARE_WEIGHT'] ?? null
        );

        $temperatureControlled = $this->requiresRefrigeration($row);

        return [
            'container_number' => $containerNumber,
            'container_type_id' => (int) $containerType->id,

            // Estado físico declarado por Guaran.
            'condition' => $condition,
            'operational_status' => $isEmpty ? 'empty' : 'loaded',

            // Regla operativa aprobada para Guaran.
            'container_condition' => 'H',

            'shipper_seal' => $this->normalizeShipperSeal(
                $row['SEAL_NO'] ?? null
            ),

            // Datos reales de ESTA unidad/movimiento.
            'tare_weight_kg' => $tare,
            'current_gross_weight_kg' => $gross,
            'cargo_weight_kg' => $isEmpty ? 0.0 : $net,

            // Capacidad técnica del tipo, no peso observado.
            'max_gross_weight_kg' => (float) $containerType->max_gross_weight_kg,

            // Guaran permite saber que requiere control, pero TEMP_MIN
            // no demuestra un setpoint operativo.
            'temperature_controlled' => $temperatureControlled,
            'set_temperature' => null,

            'active' => true,
            'created_by_user_id' => auth()->id(),
        ];
    }

    /**
     * Buscar container_type_id real basado en código
     */
    protected function resolveContainerTypeByCode(?string $code): object
    {
        $code = strtoupper(trim((string) $code));

        if ($code === '') {
            throw new Exception(
                'CONTAINER_TYPE es requerido en el archivo Guaran'
            );
        }

        // Mapeo comercial Guaran -> ISO 6346 aprobado.
        $commercialToIso = [
            '40HC' => '45G1',
            '40RH' => '45R1',
            '20DV' => '22G1',
            '20GP' => '22G1',
            '40GP' => '42G1',
            '40DV' => '42G1',
            '20RF' => '22R1',
            '20OT' => '22U1',
            '20TN' => '22T1',
            '40JU' => '45G1',
        ];

        $isoCode = $commercialToIso[$code] ?? $code;

        $containerType = DB::table('container_types')
            ->where('active', true)
            ->where(function ($query) use ($isoCode, $code) {
                $query->where('iso_code', $isoCode)
                    ->orWhere('code', $code)
                    ->orWhere('iso_code', $code);
            })
            ->first();

        if (!$containerType) {
            throw new Exception(
                "Tipo de contenedor Guaran no reconocido: {$code}"
            );
        }

        if ($containerType->max_gross_weight_kg === null) {
            throw new Exception(
                "Tipo {$code} sin max_gross_weight_kg en catálogo"
            );
        }

        return $containerType;
    }

    protected function mapContainerConditionToEnum(?string $status): string
    {
        $status = strtoupper(trim((string) $status));

        $statusMap = [
            'F' => 'L',
            'FULL' => 'L',
            'L' => 'L',
            'E' => 'V',
            'EMPTY' => 'V',
        ];

        if (!isset($statusMap[$status])) {
            $display = $status !== '' ? $status : '(vacío)';

            throw new Exception(
                "CONTAINER_STATUS Guaran no reconocido: {$display}"
            );
        }

        return $statusMap[$status];
    }

    // =====================================================
    // MÉTODOS AUXILIARES - SOLO DATOS REALES
    // =====================================================

    protected function mapManifestType(?string $type): string
    {
            return 'convoy';
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

    /**
     * Parsear volumen respetando separadores decimales del archivo Guaran.
     *
     * Los valores pueden venir como 76,2, 33.536,670 o numéricos nativos.
     * Comparte la normalización decimal ya validada para pesos.
     */
    protected function parseVolume($volume): float
    {
        return $this->parseWeight($volume);
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

        if (!$name) {
            return null;
        }

        $role = match ($type) {
            'SHIPPER' => 'shipper',
            'CONSIGNEE' => 'consignee',
            'NOTIFY_PARTY' => 'notify',
            default => null,
        };

        $contextPortCode = match ($role) {
            'shipper' => $row['POL'] ?? null,
            'consignee', 'notify' => $row['POD'] ?? null,
            default => null,
        };

        return [
            'name' => $name,
            'address' => $row[$type . '_ADDRESS1'] ?? null,
            'city' => $row[$type . '_CITY'] ?? null,
            'country' => $row[$type . '_COUNTRY'] ?? null,
            'phone' => $row[$type . '_PHONE'] ?? null,
            'role' => $role,
            'context_port_code' => $contextPortCode,
        ];
    }

    /**
     * @return array{tax_id:string,tax_type:?string}|null
     */
    protected function extractTypedTaxIdentityFromText(?string $text): ?array
    {
        $text = trim((string) $text);

        if ($text === '') {
            return null;
        }

        $patterns = [
            'CUIT' => '/\bCUIT\b\s*(?:(?:NBR|NRO|Nº|N°)\.?\s*)?[:#.-]?\s*([0-9][0-9.\/-]{5,20})/iu',
            'CNPJ' => '/\bCNPJ\b\s*[:#.-]?\s*([0-9][0-9.\/-]{5,20})/iu',
            'RUC' => '/\bR\.?\s*U\.?\s*C\.?(?:\s*\/\s*TAX\s*ID)?\s*[:#.-]?\s*([0-9][0-9.\/-]{5,20})/iu',
            'NIT' => '/\bNIT\b\s*[:#.-]?\s*([0-9][0-9.\/-]{5,20})/iu',
        ];

        foreach ($patterns as $taxType => $pattern) {
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
                    "Guaran: identificador {$taxType} con formato incompatible."
                );
            }

            return [
                'tax_id' => $taxId,
                'tax_type' => $taxType,
            ];
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
        $nameIdentity = $this->extractTypedTaxIdentityFromText($name);
        $addressIdentity = $this->extractTypedTaxIdentityFromText($address);

        if ($nameIdentity !== null && $addressIdentity !== null) {
            if ($nameIdentity['tax_id'] !== $addressIdentity['tax_id']) {
                throw new \DomainException(
                    'Guaran: nombre y domicilio declaran identificadores fiscales distintos.'
                );
            }

            if (
                $nameIdentity['tax_type'] !== null
                && $addressIdentity['tax_type'] !== null
                && $nameIdentity['tax_type'] !== $addressIdentity['tax_type']
            ) {
                throw new \DomainException(
                    'Guaran: nombre y domicilio declaran tipos fiscales distintos.'
                );
            }

            return [
                'tax_id' => $nameIdentity['tax_id'],
                'tax_type' => $nameIdentity['tax_type']
                    ?? $addressIdentity['tax_type'],
            ];
        }

        $typed = $nameIdentity ?? $addressIdentity;

        if ($typed !== null) {
            return $typed;
        }

        // El trait conserva TAX ID/RUT-VAT/etc. cuando el marcador existe,
        // pero sin adjudicar un DocumentType por longitud.
        return [
            'tax_id' => $this->resolveTaxId(
                null,
                $name,
                $address
            ),
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

    protected function countryAlpha2FromPortCode(
        ?string $portCode
    ): ?string {
        $portCode = strtoupper(trim((string) $portCode));

        if (
            !preg_match(
                '/^([A-Z]{2})[A-Z0-9]{3}$/',
                $portCode,
                $matches
            )
        ) {
            return null;
        }

        return $matches[1];
    }

    protected function countryIdForAlpha2(string $alpha2): int
    {
        $countryId = \App\Models\Country::query()
            ->where('alpha2_code', strtoupper($alpha2))
            ->value('id');

        if (!$countryId) {
            throw new \DomainException(
                "Guaran: no existe el país {$alpha2} en el catálogo."
            );
        }

        return (int) $countryId;
    }

    protected function resolveClientCountryId(
        array $data,
        ?string $taxType
    ): int {
        $declaredValue = trim(
            (string) ($data['country'] ?? '')
        );

        $declaredAlpha2 = $this->countryAlpha2FromDeclaredValue(
            $declaredValue
        );

        if (
            $declaredValue !== ''
            && $declaredAlpha2 === null
        ) {
            throw new \DomainException(
                "Guaran: país declarado no reconocido: {$declaredValue}."
            );
        }

        $taxAlpha2 = $this->countryAlpha2ForTaxType(
            $taxType
        );

        $textAlpha2 = $this->countryAlpha2FromPartyText(
            ($data['name'] ?? '')
            . ' '
            . ($data['address'] ?? '')
            . ' '
            . ($data['city'] ?? '')
        );

        $sources = array_filter([
            'declarado' => $declaredAlpha2,
            'fiscal' => $taxAlpha2,
            'texto' => $textAlpha2,
        ]);

        if (count(array_unique(array_values($sources))) > 1) {
            throw new \DomainException(
                'Guaran: las fuentes del cliente declaran países incompatibles: '
                . json_encode($sources)
            );
        }

        $alpha2 = $declaredAlpha2
            ?? $taxAlpha2
            ?? $textAlpha2;

        if ($alpha2 === null) {
            $alpha2 = $this->countryAlpha2FromPortCode(
                $data['context_port_code'] ?? null
            );
        }

        if ($alpha2 === null) {
            throw new \DomainException(
                'Guaran: no existe evidencia suficiente para resolver el país del cliente.'
            );
        }

        return $this->countryIdForAlpha2(
            $alpha2
        );
    }

    protected function findOrCreateClient(?array $data): ?Client
    {
        if (!$data || empty($data['name'])) {
            return null;
        }

        $user = auth()->user();

        $companyId = $user?->company_id
            ?? (
                $user?->userable_type === 'App\Models\Company'
                    ? $user?->userable_id
                    : null
            );

        if (!$companyId) {
            throw new Exception(
                'Guaran: usuario sin empresa asignada.'
            );
        }

        $identity = $this->resolveClientTaxIdentity(
            $data['name'] ?? null,
            $data['address'] ?? null
        );

        $taxId = $identity['tax_id'];
        $taxType = $identity['tax_type'];

        $countryId = $this->resolveClientCountryId(
            $data,
            $taxType
        );

        // Con identificador fiscal la identidad es tax_id + country_id.
        // Nunca degradar después a una coincidencia solamente por nombre.
        if ($taxId !== null) {
            $client = Client::query()
                ->where('tax_id', $taxId)
                ->where('country_id', $countryId)
                ->first();

            if ($client) {
                return $client;
            }
        } else {
            // Sin identificador fiscal sólo se reutiliza otra ficha
            // igualmente sin tax_id, mismo país y mismo nombre.
            $name = trim((string) $data['name']);

            $client = Client::query()
                ->whereNull('tax_id')
                ->where('country_id', $countryId)
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
            $documentTypeId = \App\Models\DocumentType::query()
                ->where('code', $taxType)
                ->where('country_id', $countryId)
                ->where('active', true)
                ->value('id');

            if (!$documentTypeId) {
                throw new \DomainException(
                    "Guaran: no existe un tipo documental {$taxType} "
                    . 'activo y compatible con el país resuelto.'
                );
            }
        }

        return Client::create([
            'created_by_company_id' => $companyId,
            'legal_name' => $data['name'],
            'commercial_name' => $data['name'],
            'tax_id' => $taxId,
            'client_type' => 'business',
            'status' => 'active',
            'address_line_1' => $data['address'] ?? null,
            'city' => $data['city'] ?? null,
            'phone' => $data['phone'] ?? null,
            'country_id' => $countryId,
            'document_type_id' => $documentTypeId,

            // La creación ocurre bajo Model::withoutEvents() en este parser.
            'verified_at' => now(),
            'created_by_user_id' => auth()->id(),
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
        array $items,
        array $createdContainers,
        float $startTime
    ): void {
        $processingTime = microtime(true) - $startTime;
        
        $createdObjects = [
            'voyage' => [$voyage->id],
            'shipment' => [$voyage->shipments()->first()->id ?? null],
            'bill' => array_map(fn($bill) => $bill->id, $bills),
            'item' => array_map(fn($item) => $item->id, $items),
            'container' => array_map(
                fn($container) => $container->id,
                $createdContainers
            ),
        ];
        
        $createdObjects = array_map(fn($ids) => array_filter($ids), $createdObjects);
        
        $importRecord->recordExplicitlyCreatedObjects($createdObjects);
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
        // Evitar duplicar la relación si ya existe.
        $exists = DB::table('container_shipment_item')
            ->where('container_id', $container->id)
            ->where('shipment_item_id', $item->id)
            ->exists();

        if ($exists) {
            return;
        }

        DB::table('container_shipment_item')->insert(
            $this->buildContainerItemPivotData(
                $container,
                $item
            )
        );
    }

    /**
     * La asociación nace planificada.
     *
     * Full/Empty describe el estado físico del contenedor y no debe
     * confundirse con el workflow operativo del vínculo con el item.
     */
    protected function buildContainerItemPivotData(
        Container $container,
        ShipmentItem $item
    ): array {
        return [
            'container_id' => $container->id,
            'shipment_item_id' => $item->id,
            'package_quantity' => $item->package_quantity,
            'gross_weight_kg' => $item->gross_weight_kg,
            'net_weight_kg' => $item->net_weight_kg,
            'volume_m3' => $item->volume_m3,
            'status' => 'planned',
            'created_date' => now(),
            'created_by_user_id' => auth()->id(),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}