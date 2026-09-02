<?php

namespace App\Services\Parsers;

use App\Contracts\ManifestParserInterface;
use App\Models\BillOfLading;
use App\Models\CargoType;
use App\Models\Client;
use App\Models\Container;
use App\Models\ContainerType;
use App\Models\Country;
use App\Models\DocumentType;
use App\Models\ManifestImport;
use App\Models\PackagingType;
use App\Models\Shipment;
use App\Models\ShipmentItem;
use App\Models\Vessel;
use App\Models\Voyage;
use App\Services\Parsers\Concerns\EnsuresUniqueVoyageNumber;
use App\Services\Parsers\Concerns\ExtractsEmbeddedTaxId;
use App\Services\Parsers\Concerns\ResolvesClientAddresses;
use App\Services\Parsers\Concerns\ResolvesPorts;
use App\ValueObjects\ManifestParseResult;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Throwable;

/**
 * Parser del Excel "EDI To Custom" de Guaran Feeder.
 *
 * Principio del importador: conservar los datos declarados por la fuente y
 * no completar con fechas, capacidades, identificadores ni catálogos
 * inventados. Los catálogos internos se resuelven por código y, cuando el
 * esquema permite desconocido, se conserva NULL junto al dato crudo.
 */
class GuaranExcelParser implements ManifestParserInterface
{
    use ExtractsEmbeddedTaxId;
    use EnsuresUniqueVoyageNumber;
    use ResolvesClientAddresses;
    use ResolvesPorts;

    private const HEADER_ROW = 6;
    private const DATA_START_ROW = 7;
    private const SOURCE_FORMAT = 'guaran_excel';

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
        'BR' => 'REMARKS2',          'BS' => 'REMARKS3',         'BT' => 'MLO_BL_NR',
    ];

    protected array $createdIds = [];
    protected array $containerTypeCache = [];
    protected array $catalogIdCache = [];
    protected array $countryCache = [];

    public function canParse(string $filePath): bool
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        if (!in_array($extension, ['xlsx', 'xls'], true) || !is_file($filePath)) {
            return false;
        }

        try {
            $reader = IOFactory::createReaderForFile($filePath);
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($filePath);
            $worksheet = $spreadsheet->getActiveSheet();

            if (Coordinate::columnIndexFromString($worksheet->getHighestColumn()) !== 72) {
                return false;
            }

            $sheetName = strtoupper(trim($worksheet->getTitle()));
            $a1 = strtoupper(trim((string) $worksheet->getCell('A1')->getCalculatedValue()));
            if ($sheetName !== 'EDI TO CUSTOM' && $a1 !== 'EDI TO CUSTOM') {
                return false;
            }

            $signature = [
                'A6' => 'LOCATION NAME',
                'K6' => 'MANIFEST TYPE',
                'N6' => 'VOYAGE NO',
                'O6' => 'BL NUMBER',
                'AX6' => 'CONTAINER NUMBER',
                'BT6' => 'MLO BL NR',
            ];

            foreach ($signature as $cell => $expected) {
                $actual = strtoupper(trim((string) $worksheet->getCell($cell)->getCalculatedValue()));
                if ($actual !== $expected) {
                    return false;
                }
            }

            $sourceName = strtoupper(trim((string) $worksheet->getCell('A7')->getCalculatedValue()));
            return str_contains($sourceName, 'GUARAN');
        } catch (Throwable $e) {
            return false;
        }
    }

    public function parse(string $filePath, array $options = []): ManifestParseResult
    {
        $startTime = microtime(true);
        $this->resetCreatedIds();

        $transactionStarted = false;

        try {
            if (!$this->canParse($filePath)) {
                throw new \RuntimeException('El archivo no coincide con el formato Excel de Guaran Feeder.');
            }

            $data = $this->readAndValidateSource($filePath);
            $voyageData = $this->extractVoyageData($data[0]);

            DB::beginTransaction();
            $transactionStarted = true;

            $importRecord = $this->createImportRecord($filePath, $options);
            $voyage = $this->createVoyage($voyageData, $options);
            $this->rememberCreated('voyage', $voyage->id);

            $shipment = $this->createShipment($voyage);
            $this->rememberCreated('shipment', $shipment->id);

            $bills = [];
            $containers = [];
            $groupedByBL = $this->groupDataByBillNumber($data);

            Model::withoutEvents(function () use ($groupedByBL, $shipment, &$bills, &$containers): void {
                foreach ($groupedByBL as $blRows) {
                    $bill = $this->createBillOfLading($shipment, $blRows);
                    $bills[] = $bill;
                    $this->rememberCreated('bill', $bill->id);

                    foreach ($blRows as $rowIndex => $row) {
                        $container = $this->resolveContainer($row);
                        if ($container) {
                            $containers[$container->id] = $container;
                        }

                        $item = $this->createShipmentItem($bill, $row, $rowIndex + 1);
                        $this->rememberCreated('item', $item->id);

                        if ($container) {
                            $this->attachContainerToItem($container, $item, $row);
                        }
                    }
                }
            });

            foreach ($bills as $bill) {
                $bill->recalculateItemStats();
            }

            $this->completeImportRecord($importRecord, $voyage, $startTime);
            DB::commit();
            $transactionStarted = false;

            return ManifestParseResult::success(
                voyage: $voyage,
                shipments: [$shipment],
                containers: array_values($containers),
                billsOfLading: $bills,
                statistics: [
                    'records_processed' => count($data),
                    'bills_created' => count($bills),
                    'containers_seen' => count($containers),
                    'containers_created' => count($this->createdIds['container']),
                    'clients_created' => count($this->createdIds['client']),
                    'agent' => $voyageData['agent_name'],
                    'vessel_source' => $voyageData['vessel_name'],
                    'route' => $voyageData['pol'] . ' → ' . $voyageData['pod'],
                ]
            );
        } catch (Throwable $e) {
            if ($transactionStarted) {
                DB::rollBack();
            }

            Log::error('Error parsing GUARAN Excel', [
                'file' => $filePath,
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
            ]);

            return ManifestParseResult::failure([
                'Error procesando archivo GUARAN: ' . $e->getMessage(),
            ]);
        }
    }

    protected function readAndValidateSource(string $filePath): array
    {
        $reader = IOFactory::createReaderForFile($filePath);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($filePath);
        $worksheet = $spreadsheet->getActiveSheet();

        $data = [];
        $errors = [];
        $highestRow = $worksheet->getHighestRow();

        for ($excelRow = self::DATA_START_ROW; $excelRow <= $highestRow; $excelRow++) {
            $blNumber = $this->cleanCellValue($worksheet->getCell('O' . $excelRow)->getCalculatedValue());
            if ($blNumber === null) {
                continue;
            }

            $row = [];
            foreach ($this->columnMapping as $column => $fieldName) {
                $row[$fieldName] = $this->cleanCellValue(
                    $worksheet->getCell($column . $excelRow)->getCalculatedValue()
                );
            }

            foreach ($this->validateRow($row, $excelRow) as $error) {
                $errors[] = $error;
            }

            $data[] = $row;
        }

        if ($data === []) {
            throw new \RuntimeException('No se encontraron filas de datos en el archivo GUARAN.');
        }

        foreach ($this->validateFileConsistency($data) as $error) {
            $errors[] = $error;
        }

        if ($errors !== []) {
            throw new 