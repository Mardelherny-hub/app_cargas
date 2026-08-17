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
use App\Models\Country;
use App\Models\DocumentType;
use App\Models\Vessel;
use App\Services\Parsers\Concerns\ExtractsEmbeddedTaxId;
use App\Services\Parsers\Concerns\EnsuresUniqueVoyageNumber;
use App\Services\Parsers\Concerns\ResolvesClientAddresses;
use App\Models\ManifestImport;
use App\Models\CargoType;
use App\Models\PackagingType;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Exception;
use SimpleXMLElement;
use Carbon\Carbon;

/**
 * PARSER PARA G2OCEAN.XML - FORMATO STARPORT ENVELOPE
 * 
 * Estructura verificada:
 * - <Envelope> con múltiples <bill_of_lading>
 * - Puertos: UNLOCODE en <portOfLoading>/<UNLOCODE> y <portOfDischarge>
 * - Partes: bl_shipper, bl_consignee, bl_notify con direcciones completas
 * - Carga: bl_detail con peso, volumen, NCM, marcas
 * - Buque: vesselName, voyageNo, dateOfLoading
 * 
 * Ejemplo real: RAVEN ARROW viaje 2501, China → Argentina
 */
class G2OceanXmlParser implements ManifestParserInterface
{
    use ExtractsEmbeddedTaxId;
    use EnsuresUniqueVoyageNumber;
    use ResolvesClientAddresses;

    protected array $stats = [
        'processed' => 0,
        'errors' => 0,
        'warnings' => [],
        'created_voyages' => 0,
        'created_shipments' => 0,
        'created_bills' => 0
    ];

    /**
     * Verificar si puede parsear el archivo XML
     */
    public function canParse(string $filePath): bool
    {
        if (strtolower(pathinfo($filePath, PATHINFO_EXTENSION)) !== 'xml') {
            return false;
        }

        if (!file_exists($filePath)) {
            return false;
        }

        try {
            $xmlContent = file_get_contents($filePath);
            
            // Verificar indicadores específicos de G2Ocean XML
            $indicators = [
                '<Envelope>',
                '<bill_of_lading>',
                '<bl_header>',
                '<vesselName>',
                '<UNLOCODE>',
                'StarPort'
            ];

            foreach ($indicators as $indicator) {
                if (strpos($xmlContent, $indicator) !== false) {
                    Log::debug('G2Ocean indicator found: ' . $indicator);
                    return true;
                }
            }

            return false;
        } catch (Exception $e) {
            Log::warning('Error checking G2Ocean XML: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Parsear archivo G2Ocean XML
     */

    public function parse(
        string $filePath,
        array $options = []
    ): ManifestParseResult {
        $startTime = microtime(true);

        try {
            if (empty($options['vessel_id'])) {
                return ManifestParseResult::failure([
                    'vessel_id es obligatorio para procesar archivo G2Ocean'
                ]);
            }

            $importRecord = $this->createImportRecord(
                $filePath,
                $options
            );

            $xml = simplexml_load_file($filePath);

            if (!$xml) {
                throw new Exception(
                    'No se pudo parsear el archivo XML de G2Ocean'
                );
            }

            $billsData = $this->extractBillsOfLading($xml);

            if (empty($billsData)) {
                throw new Exception(
                    'No se encontraron conocimientos de embarque '
                    . 'en el archivo G2Ocean'
                );
            }

            $duplicateCheck =
                $this->checkForDuplicateBills($billsData);

            if ($duplicateCheck['all_duplicates']) {
                throw new Exception(
                    'Todos los conocimientos de embarque '
                    . 'del archivo ya existen.'
                );
            }

            return DB::transaction(
                function () use (
                    $billsData,
                    $options,
                    $importRecord,
                    $startTime
                ) {
                    $firstBL = reset($billsData);

                    $voyage = $this->createVoyage(
                        $firstBL,
                        $options
                    );

                    $shipment = $this->createShipment(
                        $voyage,
                        $options
                    );

                    $createdBills = [];
                    $createdItems = [];

                    foreach ($billsData as $blData) {
                        $blNumber =
                            $this->requireG2OceanText(
                                $blData['bl_number'] ?? null,
                                'blNo'
                            );

                        if (
                            BillOfLading::where(
                                'bill_number',
                                $blNumber
                            )->exists()
                        ) {
                            $this->stats['warnings'][] =
                                "BL {$blNumber} ya existe, omitiendo";

                            continue;
                        }

                        /*
                         * No capturar excepciones acá:
                         * cualquier BL inválido revierte todo.
                         */
                        $bill = $this->createBillOfLading(
                            $shipment,
                            $blData
                        );

                        $createdBills[] = $bill;

                        $items = $this->createShipmentItems(
                            $bill,
                            $blData
                        );

                        $createdItems = array_merge(
                            $createdItems,
                            $items
                        );

                        $this->stats['created_bills']++;
                    }

                    if (empty($createdBills)) {
                        throw new Exception(
                            'No se creó ningún Bill of Lading.'
                        );
                    }

                    $this->completeImportRecord(
                        $importRecord,
                        $voyage,
                        $createdBills,
                        $createdItems,
                        [],
                        $startTime
                    );

                    return ManifestParseResult::success(
                        voyage: $voyage,
                        shipments: [$shipment],
                        containers: [],
                        billsOfLading: $createdBills,
                        statistics: array_merge(
                            $this->stats,
                            [
                                'processed_items' =>
                                    count($createdItems),
                                'total_bills' =>
                                    count($createdBills),
                                'import_id' =>
                                    $importRecord->id,
                            ]
                        )
                    );
                }
            );
        } catch (Exception $e) {
            Log::error('Critical error in G2Ocean parser', [
                'file_path' => $filePath,
                'error' => $e->getMessage(),
            ]);

            if (isset($importRecord)) {
                $importRecord->markAsFailed(
                    [$e->getMessage()],
                    [
                        'processing_time_seconds' =>
                            round(
                                microtime(true) - $startTime,
                                2
                            ),
                        'errors_count' => 1,
                    ]
                );
            }

            return ManifestParseResult::failure([
                'Error al procesar archivo G2Ocean: '
                . $e->getMessage(),
            ], [], $this->stats);
        }
    }


    /**
     * Extraer datos del envelope
     */
    protected function extractEnvelopeData(SimpleXMLElement $xml): array
    {
        $header = $xml->header ?? null;
        
        return [
            'message_id' => (string)($header->messageID ?? ''),
            'sender_id' => (string)($header->senderID ?? ''),
            'sender_name' => (string)($header->senderName ?? ''),
            'sender_email' => (string)($header->sender_email ?? ''),
        ];
    }

    /**
     * Extraer todos los bills of lading del XML
     */
    protected function extractBillsOfLading(SimpleXMLElement $xml): array
    {
        $billsData = [];
        
        foreach ($xml->bill_of_lading as $blXml) {
            $header = $blXml->bl_header;
            $party = $blXml->bl_party;
            $details = $blXml->bl_detail_list->bl_detail ?? [];

            // Extraer puertos UNLOCODE
            $loadingPort = (string)($header->portOfLoading->UNLocation->UNLOCODE ?? '');
            $dischargePort = (string)($header->portOfDischarge->UNLocation->UNLOCODE ?? '');

            // Extraer datos de partes
            $shipper = $this->extractPartyData($party->bl_shipper->partyInfo ?? null);
            $consignee = $this->extractPartyData($party->bl_consignee->partyInfo ?? null);
            $notify = $this->extractPartyData($party->bl_notify->partyInfo ?? null);

            // Extraer items de carga
            $items = [];
            foreach ($details as $detail) {
                $items[] = $this->extractCargoDetail($detail);
            }

            $billsData[] = [
                'bl_number' => (string)($header->blNo ?? ''),
                'vessel_name' => (string)($header->vesselName ?? ''),
                'voyage_number' => (string)($header->voyageNo ?? ''),
                'loading_date' => $this->parseDate((string)($header->dateOfLoading ?? '')),
                'issue_date' => $this->parseDate((string)($header->dateOfIssue ?? '')),
                'loading_port_code' => $loadingPort,
                'discharge_port_code' => $dischargePort,
                'shipper' => $shipper,
                'consignee' => $consignee,
                'notify' => $notify,
                'cargo_items' => $items
            ];
        }

        return $billsData;
    }

    /**
     * Extraer datos de una parte (shipper/consignee/notify)
     */
    protected function extractPartyData(?SimpleXMLElement $partyInfo): array
    {
        if (!$partyInfo) {
            return [
                'name' => 'Desconocido',
                'address' => '',
                'tax_id' => null,
                'tax_type' => null,
            ];
        }

        $name = (string) ($partyInfo->organizationName1 ?? '');

        // Quitar prefijo de rol del XML G2Ocean: "(CO) " consignee, "(NF) " notify.
        // Solo al inicio, para no tocar paréntesis legítimos del nombre.
        $name = preg_replace(
            '/^\((?:CO|NF)\)\s*/i',
            '',
            trim($name)
        );

        $address = $this->buildAddress(
            $partyInfo->addressInfo ?? null
        );

        $identity = $this->resolvePartyTaxIdentity(
            $name,
            $address
        );

        return [
            'name' => $name ?: 'Desconocido',
            'address' => $address,
            'tax_id' => $identity['tax_id'],
            'tax_type' => $identity['tax_type'],
        ];
    }

    /**
     * Extrae una identidad fiscal únicamente cuando el texto declara
     * expresamente el tipo. Nunca infiere el tipo por longitud.
     *
     * @return array{tax_id:string,tax_type:?string}|null
     */
    protected function extractTypedTaxIdentityFromText(?string $text): ?array
    {
        $text = trim((string) $text);

        if ($text === '') {
            return null;
        }

        $patterns = [
            'CUIT' => '/\bCUIT\b\s*(?:(?:NBR|NRO|Nº|N°)\.?\s*)?[:#-]?\s*([0-9][0-9.\-\/ ]{5,20}[0-9])/iu',
            'CNPJ' => '/\bCNPJ\b\s*(?:N(?:RO|º|°)?\.?\s*)?[:#-]?\s*([0-9][0-9.\-\/ ]{5,20}[0-9])/iu',
            'RUC'  => '/\bR\.?\s*U\.?\s*C\.?\b\s*(?:N(?:RO|º|°)?\.?\s*)?[:#-]?\s*([0-9][0-9.\-\/ ]{5,20}[0-9])/iu',
            'NIT'  => '/\bNIT\b\s*(?:N(?:RO|º|°)?\.?\s*)?[:#-]?\s*([0-9][0-9.\-\/ ]{5,20}[0-9])/iu',
        ];

        foreach ($patterns as $taxType => $pattern) {
            if (!preg_match($pattern, $text, $matches)) {
                continue;
            }

            $taxId = $this->resolveTaxId(
                $matches[1],
                null,
                null
            );

            if ($taxId === null) {
                continue;
            }

            if (!$this->isTaxIdCompatibleWithType($taxId, $taxType)) {
                throw new \DomainException(
                    "G2Ocean: identificador {$taxType} con formato incompatible."
                );
            }

            return [
                'tax_id' => $taxId,
                'tax_type' => $taxType,
            ];
        }

        // Identificadores genéricos: se conserva el número pero no se
        // adjudica CUIT/RUC/CNPJ/NIT sin evidencia explícita.
        if (preg_match(
            '/\bTAX\s*(?:ID|NUMBER)\b\s*[:#.-]?\s*([0-9][0-9.\-\/ ]{5,20}[0-9])/iu',
            $text,
            $matches
        )) {
            $taxId = $this->resolveTaxId(
                $matches[1],
                null,
                null
            );

            if ($taxId !== null) {
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
    protected function resolvePartyTaxIdentity(
        ?string $name,
        ?string $address
    ): array {
        $nameIdentity = $this->extractTypedTaxIdentityFromText($name);
        $addressIdentity = $this->extractTypedTaxIdentityFromText($address);

        if ($nameIdentity !== null && $addressIdentity !== null) {
            if ($nameIdentity['tax_id'] !== $addressIdentity['tax_id']) {
                throw new \DomainException(
                    'G2Ocean: nombre y domicilio declaran identificadores fiscales distintos.'
                );
            }

            if (
                $nameIdentity['tax_type'] !== null
                && $addressIdentity['tax_type'] !== null
                && $nameIdentity['tax_type'] !== $addressIdentity['tax_type']
            ) {
                throw new \DomainException(
                    'G2Ocean: nombre y domicilio declaran tipos fiscales distintos.'
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

        // El trait mantiene cobertura de otros marcadores genéricos
        // (por ejemplo RUT/VAT) sin inventar su tipo documental.
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

    protected function resolveClientCountryId(
        ?string $taxType,
        Port $defaultPort
    ): int {
        $alpha2 = $this->countryAlpha2ForTaxType($taxType);

        if ($alpha2 === null) {
            $countryId = (int) $defaultPort->country_id;

            if ($countryId <= 0) {
                throw new \DomainException(
                    'G2Ocean: el puerto contextual no tiene un país válido.'
                );
            }

            return $countryId;
        }

        $countryId = Country::query()
            ->where('alpha2_code', $alpha2)
            ->value('id');

        if (!$countryId) {
            throw new \DomainException(
                "G2Ocean: no existe el país {$alpha2} en el catálogo."
            );
        }

        return (int) $countryId;
    }

    /**
     * Construir dirección completa
     */
    protected function buildAddress(?SimpleXMLElement $addressInfo): string
    {
        if (!$addressInfo) {
            return '';
        }

        $parts = [];
        for ($i = 1; $i <= 4; $i++) {
            $line = trim((string)($addressInfo->{"addressLine{$i}"} ?? ''));
            if ($line) {
                $parts[] = $line;
            }
        }

        return implode(', ', $parts);
    }

    /**
     * Extraer tax_id (CUIT/RUC/CNPJ) del texto
     */
    protected function extractTaxId(string $text): ?string
    {
        // Patrones para diferentes documentos fiscales
        $patterns = [
            '/CUIT[:\s]+([0-9\-]+)/',           // CUIT 30-12345678-9
            '/RUC[:\s]+([0-9\-\.]+)/',          // RUC
            '/CNPJ[:\s]+([0-9\.\-\/]+)/',       // CNPJ
            '/TAX[:\s]+NUMBER[:\s]*([0-9A-Z\-]+)/i'  // Tax number genérico
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                return preg_replace('/[^0-9]/', '', $matches[1]);
            }
        }

        return null;
    }

    /**
     * Extraer detalle de carga
     */
    protected function extractCargoDetail(SimpleXMLElement $detail): array
    {
        $description = $this->extractCargoDescription($detail);
        // Una sola extracción del NCM/HS (cobertura completa de variantes).
        // tariff_position conserva el formato con puntos; commodity_code = mismo valor despuntado.
        $tariffPosition = $this->extractTariffPosition($description);
        $commodityCode = $tariffPosition
            ? (substr(preg_replace('/[^0-9]/', '', $tariffPosition), 0, 8) ?: null)
            : null;

        return [
            'item_number' => (int)($detail->itemSNo ?? 1),
            'description' => $description,
            'packages' => (int)($detail->noOfPkgs ?? 1),
            'package_type' => (string)($detail->pkgType ?? 'PACKAGES'),
            'weight_mt' => (float)($detail->weight ?? 0),
            'weight_unit' => (string)($detail->weightUOM ?? 'MT'),
            'volume' => (float)($detail->measure ?? 0),
            'volume_unit' => (string)($detail->measureUOM ?? 'M³'),
            'marks' => trim((string)($detail->marks ?? '')),
            // NCM/HS despuntado (informativo)
            'commodity_code' => $commodityCode,
            // Posición arancelaria AFIP (con formato de puntos)
            'tariff_position' => $tariffPosition,
        ];
    }

    /**
     * Extraer descripción de carga (múltiples líneas)
     */
    protected function extractCargoDescription(SimpleXMLElement $detail): string
    {
        $descriptions = [];
        
        if (isset($detail->cargoDesc->line)) {
            foreach ($detail->cargoDesc->line as $line) {
                $lineText = trim((string)$line);
                if ($lineText && !empty($lineText)) {
                    $descriptions[] = $lineText;
                }
            }
        }

        return implode(' ', $descriptions) ?: 'Mercadería general';
    }

    /**
     * Extraer código NCM/HS de la descripción de carga
     */
    protected function extractCommodityCode(string $description): ?string
    {
        // Patrones para extraer NCM/HS Code del texto
        $patterns = [
            '/NCM[:\s]+([0-9]{4}\.[0-9]{2}\.[0-9]{2})/i',           // NCM: 8705.10.30
            '/NCM[:\s]+([0-9]{4}\.[0-9]{2})/i',                      // NCM: 7213.91
            '/NCM\s+([0-9]{4}\.[0-9]{2}\.[0-9]{2})/i',               // NCM 8705.10.30
            '/TARIFF\s+(?:NUMBER|CODE)[:\s]+([0-9]{4}\.[0-9]{2})/i', // TARIFF NUMBER: 7208.51
            '/HARMONIZED\s+TARIFF\s+CODE[:\s]+([0-9]{8})/i',         // HARMONIZED TARIFF CODE: 84213990
            '/HS\s+CODE[:\s]+([0-9]{4}\.[0-9]{2})/i',                // HS CODE: 7213.91
            '/([0-9]{4}\.[0-9]{2}\.[0-9]{2}\.[0-9]{3}[A-Z]?)/i',     // 8419.90.20.900D (código directo)
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $description, $matches)) {
                // Limpiar y normalizar: quitar puntos y letras finales
                $code = preg_replace('/[^0-9]/', '', $matches[1]);
                // Retornar máximo 8 dígitos (formato NCM estándar)
            return substr($code, 0, 8) ?: null;
            }
        }
        return null;
    }

    /**
     * Extraer posición arancelaria (NCM/HS) PRESERVANDO el formato original.
     * A diferencia de extractCommodityCode() (que despunta y deja solo dígitos),
     * aquí se conserva el valor tal como aparece (ej: 8705.10.30), porque
     * tariff_position alimenta el campo AFIP que admite puntos (min 7, max 15).
     */
    protected function extractTariffPosition(string $description): ?string
    {
        $labels = '(?:NCM(?:\s+NO\.?)?|HS[\s\-]?CODE|TARIFF\s+(?:NUMBER|CODE)|HARMONIZED\s+TARIFF\s+CODE)';
        $code   = '([0-9]{4}\.[0-9]{2}(?:\.[0-9]{2})?(?:\.[0-9]{3}[A-Z]?)?|[0-9]{8,10})';
        $pattern = '/' . $labels . '[:\s]*' . $code . '/i';

        if (preg_match($pattern, $description, $matches)) {
            $value = trim($matches[1]);
            // Respetar el límite del campo AFIP (max 15, incluye puntos)
            return substr($value, 0, 15) ?: null;
        }

        return null;
    }

    /**
     * Parsear fecha desde formato YYYYMMDD
     */

    protected function parseDate(string $dateStr): ?Carbon
    {
        $value = trim($dateStr);

        if ($value === '') {
            return null;
        }

        foreach (
            ['d/m/Y', 'Y-m-d', 'd-m-Y', 'd.m.Y', 'Ymd']
            as $format
        ) {
            try {
                $date = Carbon::createFromFormat(
                    '!' . $format,
                    $value,
                    'UTC'
                );

                $errors = Carbon::getLastErrors();

                if (
                    $date
                    && (
                        $errors === false
                        || (
                            $errors['warning_count'] === 0
                            && $errors['error_count'] === 0
                        )
                    )
                ) {
                    return $date;
                }
            } catch (\Throwable $e) {
                // probar siguiente formato
            }
        }

        try {
            return Carbon::parse($value)->utc();
        } catch (\Throwable $e) {
            throw new \InvalidArgumentException(
                "Fecha G2Ocean inválida: {$value}"
            );
        }
    }


    /**
     * Verificar duplicados en lote
     */
    protected function checkForDuplicateBills(array $billsData): array
    {
        $billNumbers = array_column($billsData, 'bl_number');
        $billNumbers = array_filter($billNumbers); // Remover vacíos
        
        if (empty($billNumbers)) {
            return ['all_duplicates' => false, 'has_duplicates' => false];
        }

        $existing = BillOfLading::whereIn('bill_number', $billNumbers)
                               ->pluck('bill_number')
                               ->toArray();

        $totalBills = count($billNumbers);
        $existingCount = count($existing);
        $allDuplicates = ($totalBills > 0 && $existingCount === $totalBills);

        return [
            'all_duplicates' => $allDuplicates,
            'has_duplicates' => $existingCount > 0,
            'existing_count' => $existingCount,
            'total_count' => $totalBills,
            'existing_numbers' => $existing
        ];
    }

    /**
     * Crear voyage desde datos G2Ocean
     */


    protected function requireG2OceanText(
        $value,
        string $field
    ): string {
        $value = trim((string) $value);

        if ($value === '') {
            throw new Exception(
                "G2Ocean: falta {$field} en el XML."
            );
        }

        return $value;
    }

    protected function normalizeG2OceanCargoMarks(
        $value
    ): ?string {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    protected function parseG2OceanPackageQuantity(
        $value
    ): int {
        $value = trim((string) $value);

        if (
            $value === ''
            || !ctype_digit($value)
            || (int) $value <= 0
        ) {
            throw new Exception(
                'G2Ocean: cantidad de bultos inválida.'
            );
        }

        return (int) $value;
    }

    protected function g2OceanGrossKg($weightMt): float
    {
        if (
            !is_numeric($weightMt)
            || (float) $weightMt <= 0
        ) {
            throw new Exception(
                'G2Ocean: peso MT inválido.'
            );
        }

        return round(
            (float) $weightMt * 1000,
            2
        );
    }

    protected function assertG2OceanVesselMatchesSource(
        Vessel $vessel,
        $sourceName
    ): void {
        $normalize = static function ($value): string {
            $value = mb_strtoupper(
                trim((string) $value)
            );

            $value = preg_replace(
                '/[^A-Z0-9]+/u',
                ' ',
                $value
            );

            return trim(
                preg_replace('/\s+/', ' ', $value)
            );
        };

        $source = $normalize($sourceName);

        if ($source === '') {
            return;
        }

        $candidates = array_filter([
            $normalize($vessel->name),
            $normalize($vessel->registration_number),
        ]);

        if (!in_array($source, $candidates, true)) {
            throw new Exception(
                "G2Ocean declara vessel '{$sourceName}', "
                . "pero se seleccionó '{$vessel->name}'."
            );
        }
    }

    protected function resolveG2OceanFreightTerms(
        array $cargoItems
    ): ?string {
        $text = strtoupper(
            implode(
                ' ',
                array_map(
                    fn ($item) =>
                        (string) (
                            $item['description'] ?? ''
                        ),
                    $cargoItems
                )
            )
        );

        $prepaid = preg_match(
            '/\bFREIGHT\s+(?:IS\s+)?PREPAID\b'
            . '|\bPREPAID\s+ABROAD\b/',
            $text
        ) === 1;

        $collect = preg_match(
            '/\bFREIGHT\s+(?:IS\s+)?COLLECT\b/',
            $text
        ) === 1;

        if ($prepaid && $collect) {
            throw new Exception(
                'G2Ocean: términos de flete contradictorios.'
            );
        }

        if ($prepaid) {
            return 'prepaid';
        }

        if ($collect) {
            return 'collect';
        }

        /*
         * Puede existir importe de flete sin modalidad de pago
         * ("FREIGHT : USD18600"). Eso no autoriza inferir
         * prepaid ni collect.
         */
        return null;
    }


    protected function createVoyage(
        array $blData,
        array $options
    ): Voyage {
        $user = auth()->user();

        if (!$user) {
            throw new Exception(
                'G2Ocean requiere usuario autenticado.'
            );
        }

        $companyId = $user->company_id
            ?: (
                $user->userable_type === 'App\Models\Company'
                    ? $user->userable_id
                    : null
            );

        if (!$companyId) {
            throw new Exception(
                'Usuario no tiene empresa asignada.'
            );
        }

        $vessel = Vessel::find(
            $options['vessel_id'] ?? null
        );

        if (!$vessel) {
            throw new Exception(
                'Vessel seleccionado no encontrado.'
            );
        }

        $this->assertG2OceanVesselMatchesSource(
            $vessel,
            $blData['vessel_name'] ?? null
        );

        $originPort = $this->findOrCreatePort(
            $this->requireG2OceanText(
                $blData['loading_port_code'] ?? null,
                'portOfLoading'
            )
        );

        $destinationPort = $this->findOrCreatePort(
            $this->requireG2OceanText(
                $blData['discharge_port_code'] ?? null,
                'portOfDischarge'
            )
        );

        $voyageNumber = $this->requireG2OceanText(
            $blData['voyage_number'] ?? null,
            'voyageNo'
        );

        $this->guardVoyageNumberIsFree($voyageNumber);

        $voyage = Voyage::create([
            'company_id' => $companyId,
            'voyage_number' => $voyageNumber,
            'origin_port_id' => $originPort->id,
            'destination_port_id' =>
                $destinationPort->id,
            'lead_vessel_id' => $vessel->id,
            'origin_country_id' =>
                $originPort->country_id,
            'destination_country_id' =>
                $destinationPort->country_id,

            /*
             * dateOfLoading es fecha documental del BL.
             * El XML no aporta salida de viaje ni ETA.
             */
            'departure_date' => null,
            'estimated_arrival_date' => null,

            'voyage_type' => 'single_vessel',
            'cargo_type' => 'import',
            'status' => 'planning',
            'created_by_user_id' => $user->id,
        ]);

        $this->stats['created_voyages']++;

        return $voyage;
    }


    /**
     * Crear shipment
     */

    protected function createShipment(
        Voyage $voyage,
        array $options
    ): Shipment {
        $vessel = Vessel::findOrFail(
            $voyage->lead_vessel_id
        );

        return Shipment::create([
            'voyage_id' => $voyage->id,
            'vessel_id' => $vessel->id,

            /*
             * Identificador técnico interno.
             * No pretende ser dato documental G2Ocean.
             */
            'shipment_number' =>
                'G2O-SHIP-' . $voyage->id,

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


    /**
     * Crear BillOfLading
     */

    protected function createBillOfLading(
        Shipment $shipment,
        array $blData
    ): BillOfLading {
        $loadingPort = $this->findOrCreatePort(
            $this->requireG2OceanText(
                $blData['loading_port_code'] ?? null,
                'portOfLoading'
            )
        );

        $dischargePort = $this->findOrCreatePort(
            $this->requireG2OceanText(
                $blData['discharge_port_code'] ?? null,
                'portOfDischarge'
            )
        );

        $companyId =
            $shipment->voyage->company_id;

        $shipper = $this->findOrCreateClient(
            $blData['shipper'],
            $companyId,
            $loadingPort
        );

        $consignee = $this->findOrCreateClient(
            $blData['consignee'],
            $companyId,
            $dischargePort
        );

        $notify = null;
        $notifyText = null;

        $notifyName = strtoupper(
            trim(
                (string) (
                    $blData['notify']['name'] ?? ''
                )
            )
        );

        if ($notifyName === 'SAME AS CONSIGNEE') {
            $notifyText = 'SAME AS CONSIGNEE';
        } elseif ($notifyName !== '') {
            $notify = $this->findOrCreateClient(
                $blData['notify'],
                $companyId,
                $dischargePort
            );
        }

        $totalPackages = 0;
        $totalGrossKg = 0.0;
        $totalVolume = 0.0;

        foreach ($blData['cargo_items'] as $cargoItem) {
            $totalPackages +=
                $this->parseG2OceanPackageQuantity(
                    $cargoItem['packages'] ?? null
                );

            $totalGrossKg +=
                $this->g2OceanGrossKg(
                    $cargoItem['weight_mt'] ?? null
                );

            $totalVolume +=
                (float) ($cargoItem['volume'] ?? 0);
        }

        $bill = BillOfLading::create([
            'shipment_id' => $shipment->id,
            'bill_number' =>
                $this->requireG2OceanText(
                    $blData['bl_number'] ?? null,
                    'blNo'
                ),
            'bill_date' =>
                $blData['issue_date'] ?? null,
            'loading_date' =>
                $blData['loading_date'] ?? null,
            'shipper_id' => $shipper->id,
            'consignee_id' => $consignee->id,
            'notify_party_id' => $notify?->id,
            'notify_party_text' => $notifyText,
            'loading_port_id' => $loadingPort->id,
            'discharge_port_id' =>
                $dischargePort->id,
            'primary_cargo_type_id' =>
                $this->resolveCargoTypeByPkgType(
                    $blData['cargo_items'][0]
                        ['package_type'] ?? ''
                ),
            'primary_packaging_type_id' =>
                $this->resolvePackagingTypeByPkgType(
                    $blData['cargo_items'][0]
                        ['package_type'] ?? ''
                ),
            'total_packages' => $totalPackages,
            'gross_weight_kg' => $totalGrossKg,

            // La fuente no declara peso neto.
            'net_weight_kg' => null,

            'volume_m3' => $totalVolume,
            'cargo_description' =>
                $this->buildCargoDescription(
                    $blData['cargo_items']
                ),
            'freight_terms' =>
                $this->resolveG2OceanFreightTerms(
                    $blData['cargo_items']
                ),
            'status' => 'draft',
            'created_by_user_id' => auth()->id(),
        ]);

        $this->persistClientAddress(
            $shipper,
            $blData['shipper']['address'] ?? null
        );

        if (
            $c = $this->resolveSpecificAddress(
                $shipper,
                $blData['shipper']['address'] ?? null,
                'shipper'
            )
        ) {
            $bill->specificContacts()->create($c);
        }

        $this->persistClientAddress(
            $consignee,
            $blData['consignee']['address'] ?? null
        );

        if (
            $c = $this->resolveSpecificAddress(
                $consignee,
                $blData['consignee']['address'] ?? null,
                'consignee'
            )
        ) {
            $bill->specificContacts()->create($c);
        }

        if ($notify) {
            $this->persistClientAddress(
                $notify,
                $blData['notify']['address'] ?? null
            );

            if (
                $c = $this->resolveSpecificAddress(
                    $notify,
                    $blData['notify']['address'] ?? null,
                    'notify_party'
                )
            ) {
                $bill->specificContacts()->create($c);
            }
        }

        return $bill;
    }


    /**
     * Crear ShipmentItems
     */

    protected function createShipmentItems(
        BillOfLading $bill,
        array $blData
    ): array {
        $items = [];

        foreach ($blData['cargo_items'] as $cargoItem) {
            $commodity = trim(
                (string) (
                    $cargoItem['commodity_code'] ?? ''
                )
            );

            if (mb_strlen($commodity) > 20) {
                throw new Exception(
                    'Código G2Ocean de mercadería demasiado largo.'
                );
            }

            $item = ShipmentItem::create([
                'bill_of_lading_id' => $bill->id,
                'line_number' =>
                    $cargoItem['item_number'],
                'item_description' =>
                    $this->requireG2OceanText(
                        $cargoItem['description'] ?? null,
                        'cargo description'
                    ),
                'cargo_type_id' =>
                    $this->resolveCargoTypeByPkgType(
                        $cargoItem['package_type'] ?? ''
                    ),
                'packaging_type_id' =>
                    $this->resolvePackagingTypeByPkgType(
                        $cargoItem['package_type'] ?? ''
                    ),
                'package_quantity' =>
                    $this->parseG2OceanPackageQuantity(
                        $cargoItem['packages'] ?? null
                    ),
                'gross_weight_kg' =>
                    $this->g2OceanGrossKg(
                        $cargoItem['weight_mt'] ?? null
                    ),

                // No estimar neto como 90%.
                'net_weight_kg' => null,

                'volume_m3' =>
                    (float) ($cargoItem['volume'] ?? 0),

                'cargo_marks' =>
                    $this->normalizeG2OceanCargoMarks(
                        $cargoItem['marks'] ?? null
                    ),

                'commodity_code' =>
                    $commodity !== ''
                        ? $commodity
                        : null,

                /*
                 * Harmonized Tariff Code de origen no se hace pasar
                 * automáticamente por posición AFIP argentina.
                 */
                'tariff_position' => null,

                'created_by_user_id' => auth()->id(),
            ]);

            $items[] = $item;
        }

        return $items;
    }


    /**
     * Buscar/crear puerto
     */
    protected function findOrCreatePort(string $portCode): Port
    {
        if (empty($portCode)) {
            throw new \InvalidArgumentException("Código de puerto no puede estar vacío");
        }

        $code = strtoupper(trim($portCode));

        // Buscar existente
        $port = Port::where('code', $code)->first();
        if ($port) {
            return $port;
        }

        // Verificar que el país existe (validación defensiva, igualmente NO crearemos)
        $alpha2 = substr($code, 0, 2);
        $countryExists = Country::whereRaw('UPPER(alpha2_code)=?', [$alpha2])->exists();
        if (!$countryExists) {
            throw new \DomainException("Código de puerto {$code} rechazado: país {$alpha2} no existe en base de datos.");
        }

        // No existe en catálogo → no crear, abortar con mensaje descriptivo.
        // Política del proyecto: los parsers NUNCA crean puertos automáticamente.
        // El catálogo (~17.500 puertos) está alineado con UN/LOCODE + códigos AFIP/DNA.
        // Crear un puerto sintético invalidaría las transmisiones a aduana.
        throw new \DomainException(
            "Código de puerto '{$code}' no existe en el catálogo. " .
            "Si es un puerto válido que falta cargar, contactar al administrador. " .
            "Si es un error en el archivo origen, corregirlo antes de reintentar."
        );
    }

    /**
     * Buscar/crear cliente
     */
    protected function findOrCreateClient(array $clientData, int $companyId, Port $defaultPort): Client
    {
        $name = $clientData['name'] ?? 'Cliente Desconocido';
        $taxId = $clientData['tax_id'] ?: null;
        $taxType = $clientData['tax_type'] ?? null;

        // Con tipo fiscal explícito manda su jurisdicción.
        // Sin tipo explícito se conserva el país contextual del puerto.
        $countryId = $this->resolveClientCountryId(
            $taxType,
            $defaultPort
        );

        // Buscar existente
        if ($taxId) {
            // Con identificador: por (tax_id, country_id), coherente con el índice único
            $client = Client::where('tax_id', $taxId)
                ->where('country_id', $countryId)
                ->first();
            if ($client) {
                return $client;
            }
        } else {
            // Sin identificador: deduplicar por legal_name normalizado + country_id.
            // NO se mezcla con clientes que sí tienen tax_id real aunque compartan nombre.
            $client = Client::whereNull('tax_id')
                ->where('country_id', $countryId)
                ->whereRaw('UPPER(TRIM(legal_name)) = ?', [mb_strtoupper(trim($name))])
                ->first();
            if ($client) {
                return $client;
            }
        }

        $documentTypeId = null;

        if ($taxId !== null && $taxType !== null) {
            $documentTypeId = DocumentType::query()
                ->where('code', $taxType)
                ->where('country_id', $countryId)
                ->where('active', true)
                ->value('id');

            if (!$documentTypeId) {
                throw new \DomainException(
                    "G2Ocean: no existe un tipo documental {$taxType} "
                    . 'activo y compatible con el país resuelto.'
                );
            }
        }

        // Crear nuevo sin fabricar documento fiscal.
        return Client::create([
            'tax_id' => $taxId,
            'country_id' => $countryId,
            'document_type_id' => $documentTypeId,
            'legal_name' => $name,
            'commercial_name' => $name,
            'address' => $clientData['address'] ?: null,
            'status' => 'active',
            'created_by_company_id' => $companyId,
            'verified_at' => now(),
            'notes' => 'Cliente creado desde archivo G2Ocean XML'
        ]);
    }

    /**
     * Métodos auxiliares
     */
    protected function getPortNameFromCode(string $code): string
    {
        $portNames = [
            'CNCGU' => 'Changshu',
            'CNTXG' => 'Taicang',
            'ARZAE' => 'Zárate',
            'ARBUE' => 'Buenos Aires',
            'ARROS' => 'Rosario'
        ];
        
        return $portNames[$code] ?? "Puerto {$code}";
    }

    protected function getCityFromCode(string $code): string
    {
        return $this->getPortNameFromCode($code);
    }

    protected function getDefaultCargoTypeId(): int
    {
        return CargoType::where('active', true)->first()?->id ?? 1;
    }

    /**
     * Resolver CargoType a partir del pkgType del XML G2Ocean.
     * Mapeo basado en valores reales observados en archivos de muestra.
     */
    protected function resolveCargoTypeByPkgType(string $pkgType): int
    {
        $key = strtoupper(trim($pkgType));

        $map = [
            'PACKAGES'   => 10,  // BREAKBULK
            'BUNDLES'    => 10,  // BREAKBULK
            'COILS'      => 10,  // BREAKBULK
            'PIECES'     => 10,  // BREAKBULK
            'CARTONS'    => 10,  // BREAKBULK
            'BOXES'      => 10,  // BREAKBULK
            'CONTAINER'  => 9,   // CONTENEDORES
            'CONTAINERS' => 9,   // CONTENEDORES
            'PALLETS'    => 8,   // PALETIZADAS
            'PALETS'     => 8,   // PALETIZADAS
            'BULK'       => 5,   // OTRA CARGA NO CONTENEDORIZADA
        ];

        if (isset($map[$key])) {
            return $map[$key];
        }

        // Fallback: OTRA CARGA NO CONTENEDORIZADA (no "ENVIOS DE BAJO VALOR")
        return CargoType::where('id', 5)->where('active', true)->value('id') ?? $this->getDefaultCargoTypeId();
    }

    protected function getDefaultPackagingTypeId(): int
    {
        return PackagingType::where('active', true)->first()?->id ?? 1;
    }

    protected function getPackagingTypeByName(string $name): int
    {
        $type = PackagingType::where('name', 'LIKE', '%' . $name . '%')
                            ->where('active', true)
                            ->first();
        
        return $type?->id ?? $this->getDefaultPackagingTypeId();
    }

    /**
     * Resolver PackagingType a partir del pkgType del XML G2Ocean.
     * Mapeo basado en valores reales observados en archivos de muestra.
     */
    protected function resolvePackagingTypeByPkgType(string $pkgType): int
    {
        $key = strtoupper(trim($pkgType));

        $map = [
            'CONTAINER'  => 4,  // CONTENEDOR
            'CONTAINERS' => 4,  // CONTENEDOR
            'BULK'       => 1,  // A GRANEL
        ];

        if (isset($map[$key])) {
            return $map[$key];
        }

        // Fallback: NO RETORNABLE (envases de uso unico, no "A GRANEL")
        return PackagingType::where('id', 2)->where('active', true)->value('id') ?? $this->getDefaultPackagingTypeId();
    }

    protected function buildCargoDescription(array $cargoItems): string
    {
        $descriptions = array_column($cargoItems, 'description');
        return implode('; ', array_unique($descriptions)) ?: 'Mercadería general según manifiesto G2Ocean';
    }


    protected function createImportRecord(string $filePath, array $options): ManifestImport
    {
        $user = auth()->user();
        $fileName = basename($filePath);
        $fileSize = file_exists($filePath) ? filesize($filePath) : null;
        $fileHash = file_exists($filePath) ? ManifestImport::generateFileHash($filePath) : null;
        
        $companyId = $user->userable_type === 'App\Models\Company' ? $user->userable_id : null;
        
        return ManifestImport::createForImport([
            'company_id' => $companyId,
            'user_id' => $user->id,
            'file_name' => $fileName,
            'file_format' => 'g2ocean_xml',
            'file_size_bytes' => $fileSize,
            'file_hash' => $fileHash,
            'parser_config' => [
                'parser_class' => self::class,
                'options' => $options,
                'vessel_id' => $options['vessel_id'] ?? null
            ]
        ]);
    }


    protected function completeImportRecord(
        ManifestImport $importRecord,
        Voyage $voyage,
        array $bills,
        array $items,
        array $containers,
        float $startTime
    ): void {
        $processingTime =
            microtime(true) - $startTime;

        $shipmentId =
            $voyage->shipments()->value('id');

        $importRecord->recordExplicitlyCreatedObjects([
            'voyage' => [$voyage->id],
            'shipment' => array_filter([$shipmentId]),
            'bill' => array_map(
                fn ($bill) => $bill->id,
                $bills
            ),
            'item' => array_map(
                fn ($item) => $item->id,
                $items
            ),
            'container' => array_map(
                fn ($container) => $container->id,
                $containers
            ),
        ]);

        $importRecord->markAsCompleted([
            'voyage_id' => $voyage->id,
            'processing_time_seconds' =>
                round($processingTime, 2),
            'notes' => 'Importación G2Ocean XML completada',
        ]);
    }


    // Interface methods
    public function validate(array $data): array
    {
        $errors = [];
        if (empty($data)) {
            $errors[] = 'Archivo vacío o no se pudo leer';
        }
        return $errors;
    }

    public function transform(array $data): array
    {
        return $data;
    }

    public function getFormatInfo(): array
    {
        return [
            'name' => 'G2Ocean XML Format',
            'description' => 'Formato XML de StarPort con estructura Envelope',
            'extensions' => ['xml'],
            'features' => ['multiple_bills_per_file', 'international_shipping', 'detailed_cargo_info']
        ];
    }

    public function getDefaultConfig(): array
    {
        return [
            'parsing' => [
                'xml_encoding' => 'UTF-8',
                'validate_xml' => true,
                'extract_nested_cargo' => true
            ],
            'clients' => [
                'auto_create_missing' => true,
                'extract_tax_ids' => true,
                'default_document_type_id' => null
            ],
            'cargo' => [
                'default_cargo_type_id' => 1,
                'default_packaging_type_id' => 1,
                'convert_mt_to_kg' => true
            ]
        ];
    }
}