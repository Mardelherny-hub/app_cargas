<?php

namespace App\Services\Parsers;

use App\Contracts\ManifestParserInterface;
use App\ValueObjects\ManifestParseResult;
use App\Models\Voyage;
use App\Models\Shipment;
use App\Models\BillOfLading;
use App\Models\Container;
use App\Models\ShipmentItem;
use App\Models\Client;
use App\Models\Country;
use App\Models\DocumentType;
use App\Models\Port;
use App\Models\Vessel;
use App\Models\ContainerType;
use App\Models\ManifestImport;
use App\Services\Parsers\Concerns\EnsuresUniqueVoyageNumber;
use App\Services\Parsers\Concerns\ExtractsEmbeddedTaxId;
use App\Services\Parsers\Concerns\ResolvesClientAddresses;
use App\Services\Parsers\Concerns\ResolvesPorts;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Exception;

/**
 * PARSER PARA NAVSUR.TXT - FORMATO TEXTO JERÁRQUICO
 * 
 * Procesa archivos TXT con estructura:
 * - Marcadores de sección: **BL**, **CONTENEDORES**, **MERCADERIAS**
 * - Campos con formato: CAMPO: valor*
 * - Múltiples BLs, contenedores y mercaderías por archivo
 * - MSC como línea naviera principal
 */
class NavsurTextParser implements ManifestParserInterface
{
    use EnsuresUniqueVoyageNumber;
    use ExtractsEmbeddedTaxId;
    use ResolvesClientAddresses;
    use ResolvesPorts;

    protected array $stats = [
        'processed_bls' => 0,
        'processed_containers' => 0,
        'processed_items' => 0,
        'errors' => 0,
        'warnings' => []
    ];

    /**
     * Verificar si puede parsear el archivo
     */
    public function canParse(string $filePath): bool
    {
        // Verificar extensión TXT
        if (strtolower(pathinfo($filePath, PATHINFO_EXTENSION)) !== 'txt') {
            return false;
        }

        try {
            $handle = fopen($filePath, 'r');
            if (!$handle) {
                return false;
            }

            // Buscar patrones característicos de Navsur
            $foundNavsurPattern = false;
            $lineCount = 0;
            
            while (!feof($handle) && $lineCount < 50) {
                $line = fgets($handle);
                if ($line === false) break;
                
                $line = trim($line);
                
                // Buscar marcador distintivo de Navsur (NUMEROBL es el campo propio del formato)
                if (strpos($line, 'NUMEROBL:') !== false) {
                    $foundNavsurPattern = true;
                    break;
                }
                
                $lineCount++;
            }
            
            fclose($handle);
            return $foundNavsurPattern;
            
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Parsear archivo Navsur TXT
     */

    public function parse(
        string $filePath,
        array $options = []
    ): ManifestParseResult {
        $startTime = microtime(true);

        try {
            if (empty($options['vessel_id'])) {
                return ManifestParseResult::failure([
                    'vessel_id es obligatorio para procesar Navsur.'
                ]);
            }

            Log::info(
                'Starting Navsur TXT parse',
                ['file' => $filePath]
            );

            $importRecord = $this->createImportRecord(
                $filePath,
                $options
            );

            $content = file_get_contents($filePath);

            if ($content === false || $content === '') {
                throw new Exception(
                    'No se pudo leer el archivo Navsur.'
                );
            }

            if (!mb_check_encoding($content, 'UTF-8')) {
                $content = mb_convert_encoding(
                    $content,
                    'UTF-8',
                    'ISO-8859-1'
                );
            }

            $bls = $this->parseAllBLs($content);

            if (empty($bls)) {
                throw new Exception(
                    'No se encontraron BLs en el archivo Navsur.'
                );
            }

            $result = DB::transaction(
                function () use (
                    $bls,
                    $options,
                    $importRecord,
                    $startTime
                ) {
                    $voyageData =
                        $this->extractVoyageData($bls[0]);

                    $voyage = $this->findOrCreateVoyage(
                        $voyageData,
                        $options
                    );

                    $shipment =
                        $this->findOrCreateShipment(
                            $voyage,
                            $voyageData
                        );

                    $allBills = [];
                    $allContainers = [];
                    $createdContainers = [];
                    $allItems = [];

                    foreach ($bls as $blData) {
                        $bill = $this->createBillOfLading(
                            $shipment,
                            $blData
                        );

                        $allBills[] = $bill;
                        $this->stats['processed_bls']++;

                        foreach (
                            $blData['containers'] ?? []
                            as $containerData
                        ) {
                            $container = $this->createContainer(
                                $bill,
                                $containerData
                            );

                            if ($container) {
                                $allContainers[] = $container;

                                if ($container->wasRecentlyCreated) {
                                    $createdContainers[] =
                                        $container;
                                }

                                $this->stats[
                                    'processed_containers'
                                ]++;
                            }

                            foreach (
                                $containerData['items'] ?? []
                                as $itemData
                            ) {
                                $item =
                                    $this->createShipmentItem(
                                        $bill,
                                        $itemData
                                    );

                                if (!$item) {
                                    continue;
                                }

                                $allItems[] = $item;
                                $this->stats[
                                    'processed_items'
                                ]++;

                                if ($container) {
                                    $this->attachContainerToItem(
                                        $container,
                                        $item
                                    );
                                }
                            }
                        }
                    }

                    $importRecord
                        ->recordExplicitlyCreatedObjects([
                            'voyage' => [$voyage->id],
                            'shipment' => [$shipment->id],
                            'bill' => array_map(
                                fn ($b) => $b->id,
                                $allBills
                            ),
                            'item' => array_map(
                                fn ($i) => $i->id,
                                $allItems
                            ),
                            'container' => array_map(
                                fn ($c) => $c->id,
                                $createdContainers
                            ),
                        ]);

                    $importRecord->markAsCompleted([
                        'voyage_id' => $voyage->id,
                        'created_bills' =>
                            count($allBills),
                        'created_items' =>
                            count($allItems),
                        'processing_time_seconds' =>
                            round(
                                microtime(true) - $startTime,
                                2
                            ),
                        'import_statistics' =>
                            $this->stats,
                        'notes' =>
                            'Importación Navsur completada',
                    ]);

                    return [
                        'voyage' => $voyage,
                        'shipment' => $shipment,
                        'bills' => $allBills,
                        'containers' => $allContainers,
                        'items' => $allItems,
                    ];
                }
            );

            return ManifestParseResult::success(
                voyage: $result['voyage'],
                shipments: [$result['shipment']],
                containers: $result['containers'],
                billsOfLading: $result['bills'],
                warnings: $this->stats['warnings'],
                statistics: $this->stats
            );
        } catch (Exception $e) {
            if (isset($importRecord)) {
                $importRecord->markAsFailed(
                    [$e->getMessage()],
                    [
                        'import_statistics' =>
                            $this->stats,
                    ]
                );
            }

            Log::error('Navsur parsing failed', [
                'file' => $filePath,
                'error' => $e->getMessage(),
            ]);

            return ManifestParseResult::failure(
                [$e->getMessage()],
                $this->stats['warnings'],
                $this->stats
            );
        }
    }


    /**
     * Parsear todos los BLs del archivo
     */
    protected function parseAllBLs(string $content): array
    {
        $bls = [];
        
        // Dividir por marcador de BL
        $blSections = preg_split('/\*\*BL\*\*/', $content);
        
        foreach ($blSections as $blSection) {
            if (trim($blSection) === '' || strpos($blSection, 'NUMEROBL:') === false) {
                continue;
            }
            
            // Extraer fin del BL
            $endPos = strpos($blSection, '**FIN BL**');
            if ($endPos !== false) {
                $blSection = substr($blSection, 0, $endPos);
            }
            
            $bl = $this->parseBLSection($blSection);
            if (!empty($bl['numero_bl'])) {
                $bls[] = $bl;
            }
        }
        
        return $bls;
    }

    /**
     * Parsear una sección de BL
     */
    protected function parseBLSection(string $section): array
    {
        $bl = [
            'numero_bl' => $this->extractValue($section, 'NUMEROBL:'),
            'cod_booking' => $this->extractValue($section, 'CODBOOKING:'),
            'cod_programacion' => $this->extractValue($section, 'CODPROGRAMACION:'),
            'buque' => $this->extractValue($section, 'BUQUE:'),
            'viaje' => $this->extractValue($section, 'VIAJE:'),
            'bandera' => $this->extractValue($section, 'BANDERA:'),
            'condicion_flete' => $this->extractValue($section, 'CONDICIONFLETE:'),
            'puerto_carga' => $this->extractValue($section, 'CODPUERTODECARGA:'),
            'puerto_descarga' => $this->extractValue($section, 'CODPUERTODEDESCARGA:'),
            'destino_final' => $this->extractValue($section, 'DESTINOFINAL:'),
            'cargador_nombre' => $this->extractValue($section, 'CARGADORNOMBRE:'),
            'consignatario_nombre' => $this->extractValue($section, 'CONSIGNATARIONOMBRE:'),
            'notificatario1_nombre' => $this->extractValue($section, 'NOTIFICATARIO1NOMBRE:'),
            'cargador_domicilio' => $this->extractValue($section, 'CARGADORDOMICILIO:'),
            'consignatario_domicilio' => $this->extractValue($section, 'CONSIGNATARIODOMICILIO:'),
            'notificatario1_domicilio' => $this->extractValue($section, 'NOTIFICATARIO1DOMICILIO:'),
            'containers' => []
        ];
        
        // Parsear contenedores
        $containerSections = $this->extractContainerSections($section);
        foreach ($containerSections as $containerSection) {
            $container = $this->parseContainerSection($containerSection);
            if (!empty($container['cod_contenedor'])) {
                $bl['containers'][] = $container;
            }
        }
        
        return $bl;
    }

    /**
     * Extraer secciones de contenedores.
     *
     * Nota de formato Navsur: dentro de un BL, solo el primer contenedor abre con
     * **CONTENEDORES**; los siguientes vienen sin apertura pero todos cierran con
     * **FIN CONTENEDORES**. Por eso NO se usa el par apertura/cierre. En su lugar:
     * cada contenedor es el bloque que TERMINA en **FIN CONTENEDORES**, y sus
     * mercaderías son el bloque **MERCADERIAS**...**FIN MERCADERIAS** que lo sigue.
     */
    protected function extractContainerSections(string $section): array
    {
        $containers = [];

        // 1) Cada bloque de contenedor: todo lo que precede a un **FIN CONTENEDORES**.
        //    Se parte por ese cierre y se descarta lo que no tenga CODCONTENEDOR.
        $contParts = preg_split('/\*\*FIN CONTENEDORES\*\*/', $section);

        // 2) Cada bloque de mercaderías, en orden de aparición.
        preg_match_all('/\*\*MERCADERIAS\*\*(.*?)\*\*FIN MERCADERIAS\*\*/s', $section, $mercMatches);
        $mercaderiasBloques = $mercMatches[1] ?? [];

        $idx = 0;
        foreach ($contParts as $part) {
            if (strpos($part, 'CODCONTENEDOR:') === false) {
                continue;
            }

            // Quitar cualquier resto de un **MERCADERIAS** previo que haya quedado al inicio.
            $cleanPos = strrpos($part, '**FIN MERCADERIAS**');
            if ($cleanPos !== false) {
                $part = substr($part, $cleanPos + strlen('**FIN MERCADERIAS**'));
            }
            // Quitar la apertura **CONTENEDORES** si está presente (solo en el primero).
            $part = str_replace('**CONTENEDORES**', '', $part);

            $containers[] = [
                'container' => $part,
                'items'     => $mercaderiasBloques[$idx] ?? '',
            ];
            $idx++;
        }

        return $containers;
    }

    /**
     * Parsear sección de contenedor
     */
    protected function parseContainerSection(array $containerData): array
    {
        $containerText = $containerData['container'];
        $itemsText = $containerData['items'];
        
        $container = [
            'cod_contenedor' => $this->extractValue($containerText, 'CODCONTENEDOR:'),
            'tipo_contenedor' => $this->extractValue($containerText, 'CODTIPOCONTENEDOR:'),
            'medida' => $this->extractValue($containerText, 'CODMEDIDA:'),
            'tara' => $this->extractValue($containerText, 'TARA:'),
            'temperatura' => $this->extractValue($containerText, 'TEMPERATURA:'),
            'precintos_linea' => $this->extractValue($containerText, 'PRECINTOSLINEA:'),
            'precintos_aduana' => $this->extractValue($containerText, 'PRECINTOSADUANA:'),
            'precintos_senacsa' => $this->extractValue($containerText, 'PRECINTOSENACSA:'),
            'items' => []
        ];
        
        // Parsear items
        if (!empty($itemsText)) {
            $items = $this->parseItemsSection($itemsText);
            $container['items'] = $items;
        }
        
        return $container;
    }

    /**
     * Parsear sección de items de mercadería
     */

    protected function parseItemsSection(
        string $section
    ): array {
        $items = [];

        $chunks = preg_split(
            '/(?=ITEM:\s*\/\*)/',
            $section
        );

        foreach ($chunks as $chunk) {
            if (strpos($chunk, 'ITEM:') === false) {
                continue;
            }

            $mercaderia = $this->extractValue(
                $chunk,
                'MERCADERIA:'
            );

            if (
                $mercaderia === null
                || trim($mercaderia) === ''
            ) {
                continue;
            }

            $items[] = [
                'item' =>
                    $this->extractValue(
                        $chunk,
                        'ITEM:'
                    ),
                'titulo' =>
                    $this->extractValue(
                        $chunk,
                        'TITULO:'
                    ),
                'embalaje' =>
                    $this->extractValue(
                        $chunk,
                        'EMBALAJE:'
                    ),
                'mercaderia' => $mercaderia,
                'cantidad' =>
                    $this->extractValue(
                        $chunk,
                        'CANTIDAD:'
                    ),
                'peso_neto' =>
                    $this->extractValue(
                        $chunk,
                        'PESONETO:'
                    ),
                'peso_bruto' =>
                    $this->extractValue(
                        $chunk,
                        'PESOBRUTO:'
                    ),
                'cubitaje' =>
                    $this->extractValue(
                        $chunk,
                        'CUBITAJE:'
                    ),
                'imo' =>
                    $this->extractValue(
                        $chunk,
                        'IMO:'
                    ),
                'partida_arancelaria' =>
                    $this->extractValue(
                        $chunk,
                        'PARTIDAARANCELARIA:'
                    ),
            ];
        }

        return $items;
    }


    /**
     * Extraer valor entre marcadores de comentario
     */
    protected function extractValue(string $text, string $field): ?string
    {
        $pattern = '/' . preg_quote($field, '/') . '\s*\/\*(.*?)\*\//s';
        if (preg_match($pattern, $text, $matches)) {
            return trim($matches[1]);
        }
        return null;
    }

    /**
     * Extraer datos del voyage del primer BL
     */


    protected function nullableNavsurText($value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    protected function requireNavsurText(
        $value,
        string $field
    ): string {
        $value = $this->nullableNavsurText($value);

        if ($value === null) {
            throw new Exception(
                "Navsur: falta {$field} en la fuente."
            );
        }

        return $value;
    }

    protected function resolveNavsurCompanyId(
        $user
    ): int {
        if (
            $user->userable_type ===
                'App\Models\Company'
            && $user->userable_id
        ) {
            return (int) $user->userable_id;
        }

        if (
            $user->userable_type ===
                'App\Models\Operator'
            && $user->userable
        ) {
            return (int)
                $user->userable->company_id;
        }

        throw new Exception(
            "Usuario {$user->id} no tiene empresa."
        );
    }

    protected function assertNavsurVesselMatchesSource(
        Vessel $vessel,
        $sourceName
    ): void {
        $normalize = static function ($value): string {
            return trim(
                preg_replace(
                    '/\s+/',
                    ' ',
                    mb_strtoupper(
                        trim((string) $value)
                    )
                )
            );
        };

        $source = $normalize($sourceName);

        if ($source === '') {
            return;
        }

        $candidates = array_filter([
            $normalize($vessel->name),
            $normalize(
                $vessel->registration_number
            ),
        ]);

        if (!in_array($source, $candidates, true)) {
            throw new Exception(
                "Navsur declara buque '{$sourceName}', "
                . "pero se seleccionó '{$vessel->name}'."
            );
        }
    }

    protected function resolveNavsurVoyageCargoType(
        Port $origin,
        Port $destination
    ): string {
        if (
            (int) $origin->country_id
            === (int) $destination->country_id
        ) {
            return 'cabotage';
        }

        $originCountry = Country::find(
            $origin->country_id
        );

        $destinationCountry = Country::find(
            $destination->country_id
        );

        $originIso = strtoupper(
            (string) $originCountry?->alpha2_code
        );

        $destinationIso = strtoupper(
            (string) $destinationCountry?->alpha2_code
        );

        if ($destinationIso === 'AR') {
            return 'import';
        }

        if ($originIso === 'AR') {
            return 'export';
        }

        return 'transit';
    }

    protected function parseNavsurNumber(
        $value
    ): ?float {
        $raw = trim((string) $value);

        if ($raw === '') {
            return null;
        }

        $raw = preg_replace(
            '/\s+/u',
            '',
            $raw
        );

        $lastComma = strrpos($raw, ',');
        $lastDot = strrpos($raw, '.');

        if (
            $lastComma !== false
            && $lastDot !== false
        ) {
            if ($lastComma > $lastDot) {
                $raw = str_replace('.', '', $raw);
                $raw = str_replace(',', '.', $raw);
            } else {
                $raw = str_replace(',', '', $raw);
            }
        } elseif ($lastComma !== false) {
            $raw = str_replace(',', '.', $raw);
        }

        if (!is_numeric($raw)) {
            return null;
        }

        return (float) $raw;
    }

    protected function parseNavsurPackageQuantity(
        $value
    ): int {
        $number = $this->parseNavsurNumber($value);

        if (
            $number === null
            || $number <= 0
            || floor($number) != $number
        ) {
            throw new Exception(
                'Navsur: CANTIDAD debe ser entero '
                . 'positivo.'
            );
        }

        return (int) $number;
    }

    protected function parseNavsurRequiredPositiveNumber(
        $value,
        string $field
    ): float {
        $number = $this->parseNavsurNumber($value);

        if ($number === null || $number <= 0) {
            throw new Exception(
                "Navsur: {$field} inválido."
            );
        }

        return $number;
    }

    protected function parseNavsurRequiredNonNegativeNumber(
        $value,
        string $field
    ): float {
        $number = $this->parseNavsurNumber($value);

        if ($number === null || $number < 0) {
            throw new Exception(
                "Navsur: {$field} inválido."
            );
        }

        return $number;
    }

    protected function resolveNavsurNetWeight(
        array $data
    ): ?float {
        $direct = $this->parseNavsurNumber(
            $data['peso_neto'] ?? null
        );

        if ($direct !== null && $direct > 0) {
            return $direct;
        }

        $description =
            (string) ($data['mercaderia'] ?? '');

        if (
            preg_match(
                '/TOTAL\s+NET\s+WEIGHT\s*:\s*'
                . '([0-9.,]+)\s*KGS?/iu',
                $description,
                $matches
            )
        ) {
            $parsed =
                $this->parseNavsurNumber(
                    $matches[1]
                );

            if ($parsed !== null && $parsed > 0) {
                return $parsed;
            }
        }

        return null;
    }

    protected function extractNavsurCommodityCode(
        array $data
    ): ?string {
        $explicit = $this->nullableNavsurText(
            $data['partida_arancelaria'] ?? null
        );

        if ($explicit !== null) {
            return mb_substr($explicit, 0, 20);
        }

        $description =
            (string) ($data['mercaderia'] ?? '');

        if (
            preg_match(
                '/(?:HS\s*CODE|NCM)\s*:\s*'
                . '([0-9.]+)/iu',
                $description,
                $matches
            )
        ) {
            return mb_substr(
                trim($matches[1]),
                0,
                20
            );
        }

        return null;
    }

    protected function resolveNavsurPackagingTypeId(
        $source
    ): ?int {
        $source = $this->nullableNavsurText(
            $source
        );

        if ($source === null) {
            return null;
        }

        $normalized = mb_strtoupper($source);

        $type = \App\Models\PackagingType::query()
            ->where('active', true)
            ->where(function ($query) use ($normalized) {
                $query
                    ->whereRaw(
                        'UPPER(code) = ?',
                        [$normalized]
                    )
                    ->orWhereRaw(
                        'UPPER(name) = ?',
                        [$normalized]
                    )
                    ->orWhereRaw(
                        'UPPER(short_name) = ?',
                        [$normalized]
                    );
            })
            ->first();

        return $type?->id;
    }

    protected function resolveNavsurCargoTypeId(
        bool $dangerous
    ): int {
        $code = $dangerous
            ? 'PEL001'
            : 'CON001';

        $id = \App\Models\CargoType::query()
            ->where('active', true)
            ->where('code', $code)
            ->value('id');

        if (!$id) {
            throw new Exception(
                "Navsur requiere cargo type {$code}."
            );
        }

        return (int) $id;
    }

    protected function resolveNavsurItemCargoTypeId(
        array $data
    ): int {
        return $this->resolveNavsurCargoTypeId(
            $this->nullableNavsurText(
                $data['imo'] ?? null
            ) !== null
        );
    }

    protected function resolveNavsurPrimaryCargoTypeId(
        array $data
    ): int {
        $dangerous = false;

        foreach (
            $data['containers'] ?? []
            as $container
        ) {
            foreach (
                $container['items'] ?? []
                as $item
            ) {
                if (
                    $this->nullableNavsurText(
                        $item['imo'] ?? null
                    ) !== null
                ) {
                    $dangerous = true;
                    break 2;
                }
            }
        }

        return $this->resolveNavsurCargoTypeId(
            $dangerous
        );
    }

    protected function resolveNavsurPrimaryPackagingTypeId(
        array $data
    ): ?int {
        $ids = [];

        foreach (
            $data['containers'] ?? []
            as $container
        ) {
            foreach (
                $container['items'] ?? []
                as $item
            ) {
                $id =
                    $this->resolveNavsurPackagingTypeId(
                        $item['embalaje'] ?? null
                    );

                if ($id !== null) {
                    $ids[$id] = true;
                }
            }
        }

        if (count($ids) !== 1) {
            return null;
        }

        return (int) array_key_first($ids);
    }

    protected function resolveNavsurContainerState(
        array $data
    ): array {
        if (empty($data['items'])) {
            throw new Exception(
                'Navsur: contenedor sin estado explícito '
                . 'y sin mercadería asociada.'
            );
        }

        return [
            'condition' => 'L',
            'operational_status' => 'loaded',
        ];
    }

    protected function aggregateNavsurBillCargo(
        array $data
    ): array {
        $packages = 0;
        $gross = 0.0;
        $volume = 0.0;
        $net = 0.0;
        $allNetKnown = true;
        $descriptions = [];

        foreach (
            $data['containers'] ?? []
            as $container
        ) {
            foreach (
                $container['items'] ?? []
                as $item
            ) {
                $packages +=
                    $this->parseNavsurPackageQuantity(
                        $item['cantidad'] ?? null
                    );

                $gross +=
                    $this->parseNavsurRequiredPositiveNumber(
                        $item['peso_bruto'] ?? null,
                        'PESOBRUTO'
                    );

                $volume +=
                    $this
                        ->parseNavsurRequiredNonNegativeNumber(
                            $item['cubitaje'] ?? null,
                            'CUBITAJE'
                        );

                $itemNet =
                    $this->resolveNavsurNetWeight(
                        $item
                    );

                if ($itemNet === null) {
                    $allNetKnown = false;
                } else {
                    $net += $itemNet;
                }

                $description =
                    $this->nullableNavsurText(
                        $item['mercaderia'] ?? null
                    );

                if ($description !== null) {
                    $descriptions[$description] = true;
                }
            }
        }

        if ($packages <= 0 || $gross <= 0) {
            throw new Exception(
                'Navsur: BL sin carga cuantificable.'
            );
        }

        return [
            'total_packages' => $packages,
            'gross_weight_kg' =>
                round($gross, 2),
            'net_weight_kg' =>
                $allNetKnown
                    ? round($net, 2)
                    : null,
            'volume_m3' =>
                round($volume, 3),
            'description' =>
                implode(
                    "\n\n",
                    array_keys($descriptions)
                ),
        ];
    }


    protected function extractVoyageData(array $bl): array
    {
        return [
            'voyage_number' =>
                $this->requireNavsurText(
                    $bl['viaje'] ?? null,
                    'VIAJE'
                ),
            'vessel_name' =>
                $this->requireNavsurText(
                    $bl['buque'] ?? null,
                    'BUQUE'
                ),
            'flag' =>
                $this->nullableNavsurText(
                    $bl['bandera'] ?? null
                ),
            'pol' =>
                $this->requireNavsurText(
                    $bl['puerto_carga'] ?? null,
                    'CODPUERTODECARGA'
                ),
            'pod' =>
                $this->requireNavsurText(
                    $bl['puerto_descarga'] ?? null,
                    'CODPUERTODEDESCARGA'
                ),
        ];
    }


    /**
     * Buscar o crear voyage - VALORES ENUM CORREGIDOS
     */

    protected function findOrCreateVoyage(
        array $data,
        array $options = []
    ): Voyage {
        $user = auth()->user();

        if (!$user) {
            throw new Exception(
                'Navsur requiere usuario autenticado.'
            );
        }

        $companyId =
            $this->resolveNavsurCompanyId($user);

        $vessel = Vessel::find(
            $options['vessel_id'] ?? null
        );

        if (!$vessel) {
            throw new Exception(
                'Vessel seleccionado no encontrado.'
            );
        }

        if (
            (int) $vessel->company_id
            !== (int) $companyId
        ) {
            throw new Exception(
                'El vessel seleccionado no pertenece '
                . 'a la empresa importadora.'
            );
        }

        $this->assertNavsurVesselMatchesSource(
            $vessel,
            $data['vessel_name'] ?? null
        );

        $voyageNumber =
            $this->requireNavsurText(
                $data['voyage_number'] ?? null,
                'VIAJE'
            );

        $this->guardVoyageNumberIsFree(
            $voyageNumber
        );

        $originPort = $this->resolvePortStrict(
            $data['pol']
        );

        $destPort = $this->resolvePortStrict(
            $data['pod']
        );

        return Voyage::create([
            'voyage_number' => $voyageNumber,
            'company_id' => $companyId,
            'lead_vessel_id' => $vessel->id,
            'origin_port_id' => $originPort->id,
            'destination_port_id' =>
                $destPort->id,
            'origin_country_id' =>
                $originPort->country_id,
            'destination_country_id' =>
                $destPort->country_id,
            'status' => 'planning',
            'voyage_type' => 'single_vessel',
            'cargo_type' =>
                $this->resolveNavsurVoyageCargoType(
                    $originPort,
                    $destPort
                ),

            /*
             * Navsur no declara fechas del viaje.
             */
            'departure_date' => null,
            'estimated_arrival_date' => null,

            'total_cargo_capacity_tons' =>
                $vessel->cargo_capacity_tons,
            'total_container_capacity' =>
                $vessel->container_capacity,
            'total_cargo_weight_loaded' => 0,
            'total_containers_loaded' => 0,
            'capacity_utilization_percentage' => 0,
        ]);
    }


    /**
     * NUEVO MÉTODO: Mapear bandera a country_id
     */

    protected function mapFlagToCountryId(
        string $flag
    ): ?int {
        $alpha2 = $this->mapFlag($flag);

        if ($alpha2 === null) {
            return null;
        }

        return Country::whereRaw(
                'UPPER(alpha2_code) = ?',
                [$alpha2]
            )
            ->value('id');
    }


    
    /**
     * Buscar o crear shipment
     */

    protected function findOrCreateShipment(
        Voyage $voyage,
        array $data
    ): Shipment {
        $shipment = Shipment::where(
                'voyage_id',
                $voyage->id
            )
            ->where('sequence_in_voyage', 1)
            ->first();

        if ($shipment) {
            return $shipment;
        }

        $vessel = Vessel::findOrFail(
            $voyage->lead_vessel_id
        );

        return Shipment::create([
            'voyage_id' => $voyage->id,
            'vessel_id' => $vessel->id,
            'shipment_number' =>
                'NAVSUR-' . $voyage->id,
            'sequence_in_voyage' => 1,
            'vessel_role' => 'single',
            'is_lead_vessel' => true,
            'cargo_capacity_tons' =>
                $vessel->cargo_capacity_tons,
            'container_capacity' =>
                $vessel->container_capacity ?? 0,
            'status' => 'planning',
        ]);
    }


    /**
     * Registrar la importación en ManifestImport (con dup-check por hash).
     */

    protected function createImportRecord(
        string $filePath,
        array $options = []
    ): ManifestImport {
        $user = auth()->user();

        if (!$user) {
            throw new Exception(
                'Usuario no autenticado para crear importación.'
            );
        }

        $companyId =
            $this->resolveNavsurCompanyId($user);

        $fileName = basename($filePath);
        $fileSize = file_exists($filePath)
            ? filesize($filePath)
            : null;
        $fileHash = file_exists($filePath)
            ? ManifestImport::generateFileHash(
                $filePath
            )
            : null;

        if ($fileHash) {
            $existing =
                ManifestImport::isFileAlreadyImported(
                    $fileHash,
                    $companyId
                );

            if ($existing) {
                throw new Exception(
                    'Este archivo ya fue importado '
                    . "anteriormente (ID: {$existing->id})"
                );
            }
        }

        return ManifestImport::createForImport([
            'company_id' => $companyId,
            'user_id' => $user->id,
            'file_name' => $fileName,
            'file_format' => 'navsur',
            'file_size_bytes' => $fileSize,
            'file_hash' => $fileHash,
            'parser_config' => [
                'parser_class' => self::class,
                'vessel_id' =>
                    $options['vessel_id'] ?? null,
            ],
        ]);
    }


    /**
     * Crear BillOfLading
     */

    protected function createBillOfLading(
        Shipment $shipment,
        array $data
    ): BillOfLading {
        $shipper = $this->findOrCreateClient(
            $this->requireNavsurText(
                $data['cargador_nombre'] ?? null,
                'CARGADORNOMBRE'
            ),
            'shipper',
            $data['cargador_domicilio'] ?? null,
            $data['puerto_carga'] ?? null
        );

        $consignee = $this->findOrCreateClient(
            $this->requireNavsurText(
                $data['consignatario_nombre'] ?? null,
                'CONSIGNATARIONOMBRE'
            ),
            'consignee',
            $data['consignatario_domicilio'] ?? null,
            $data['puerto_descarga'] ?? null
        );

        $notify = null;

        if (
            $this->nullableNavsurText(
                $data['notificatario1_nombre'] ?? null
            ) !== null
        ) {
            $notify = $this->findOrCreateClient(
                $data['notificatario1_nombre'],
                'notify',
                $data['notificatario1_domicilio']
                    ?? null,
                $data['puerto_descarga'] ?? null
            );
        }

        $loadingPort = $this->resolvePortStrict(
            $this->requireNavsurText(
                $data['puerto_carga'] ?? null,
                'CODPUERTODECARGA'
            )
        );

        $dischargePort = $this->resolvePortStrict(
            $this->requireNavsurText(
                $data['puerto_descarga'] ?? null,
                'CODPUERTODEDESCARGA'
            )
        );

        $finalPort = null;

        if (
            $this->nullableNavsurText(
                $data['destino_final'] ?? null
            ) !== null
        ) {
            $finalPort = $this->resolvePortOrNull(
                $data['destino_final']
            );

            if (!$finalPort) {
                $this->stats['warnings'][] =
                    'Destino final Navsur no resoluble: '
                    . $data['destino_final'];
            }
        }

        $cargo =
            $this->aggregateNavsurBillCargo($data);

        $notes = array_values(
            array_filter([
                'Importado desde archivo Navsur',
                !empty($data['cod_booking'])
                    ? 'CODBOOKING='
                        . $data['cod_booking']
                    : null,
                !empty($data['cod_programacion'])
                    ? 'CODPROGRAMACION='
                        . $data['cod_programacion']
                    : null,
            ])
        );

        $bill = BillOfLading::create([
            'shipment_id' => $shipment->id,
            'bill_number' =>
                $this->requireNavsurText(
                    $data['numero_bl'] ?? null,
                    'NUMEROBL'
                ),

            /*
             * Booking no es un Master BL.
             */
            'master_bill_number' => null,

            'internal_reference' =>
                $data['cod_programacion'] ?? null,

            /*
             * Navsur no declara fechas documentales.
             */
            'bill_date' => null,
            'loading_date' => null,

            'shipper_id' => $shipper->id,
            'consignee_id' => $consignee->id,
            'notify_party_id' => $notify?->id,

            'loading_port_id' => $loadingPort->id,
            'discharge_port_id' =>
                $dischargePort->id,
            'final_destination_port_id' =>
                $finalPort?->id,

            'freight_terms' =>
                $this->mapFreightTerms(
                    $data['condicion_flete'] ?? ''
                ),

            'status' => 'draft',

            'primary_cargo_type_id' =>
                $this->resolveNavsurPrimaryCargoTypeId(
                    $data
                ),

            /*
             * NULL cuando BAGS/PALLETS/etc. no tienen
             * equivalencia real en packaging_types.
             */
            'primary_packaging_type_id' =>
                $this->resolveNavsurPrimaryPackagingTypeId(
                    $data
                ),

            'gross_weight_kg' =>
                $cargo['gross_weight_kg'],
            'net_weight_kg' =>
                $cargo['net_weight_kg'],
            'total_packages' =>
                $cargo['total_packages'],
            'volume_m3' =>
                $cargo['volume_m3'],
            'cargo_description' =>
                $cargo['description'],

            'internal_notes' =>
                implode(' | ', $notes),
        ]);

        foreach ([
            [
                'client' => $shipper,
                'addr' =>
                    $data['cargador_domicilio'] ?? null,
                'role' => 'shipper',
            ],
            [
                'client' => $consignee,
                'addr' =>
                    $data['consignatario_domicilio']
                        ?? null,
                'role' => 'consignee',
            ],
            [
                'client' => $notify,
                'addr' =>
                    $data['notificatario1_domicilio']
                        ?? null,
                'role' => 'notify_party',
            ],
        ] as $party) {
            if (!$party['client']) {
                continue;
            }

            $this->persistClientAddress(
                $party['client'],
                $party['addr']
            );

            $specific =
                $this->resolveSpecificAddress(
                    $party['client'],
                    $party['addr'],
                    $party['role']
                );

            if ($specific) {
                $bill->specificContacts()
                    ->create($specific);
            }
        }

        return $bill;
    }



    protected function validateCargoTypeId(
        array $data
    ): int {
        return $this->resolveNavsurPrimaryCargoTypeId(
            $data
        );
    }


    /**
     * NUEVO MÉTODO: Mapear términos de flete
     */

    protected function mapFreightTerms(
        string $terms
    ): ?string {
        $terms = strtoupper(
            trim(
                str_replace(
                    ['/*', '*/'],
                    '',
                    $terms
                )
            )
        );

        if ($terms === '') {
            return null;
        }

        if (
            str_contains($terms, 'PREPAID')
            || str_contains($terms, 'PREPAGADO')
        ) {
            return 'prepaid';
        }

        if (
            str_contains($terms, 'COLLECT')
            || str_contains($terms, 'COBRAR')
        ) {
            return 'collect';
        }

        if (
            str_contains($terms, 'THIRD')
            || str_contains($terms, 'TERCERO')
        ) {
            return 'third_party';
        }

        return null;
    }

 
    /**
     * Vincular un Container con un ShipmentItem en el pivote container_shipment_item.
     */
    protected function attachContainerToItem(Container $container, ShipmentItem $item): void
    {
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

/**
 * Crear Container
 */

    protected function createContainer(
        BillOfLading $bill,
        array $data
    ): ?Container {
        $number = $this->nullableNavsurText(
            $data['cod_contenedor'] ?? null
        );

        if ($number === null) {
            return null;
        }

        $existing = Container::where(
            'container_number',
            $number
        )->first();

        if ($existing) {
            return $existing;
        }

        $containerType =
            $this->findOrCreateContainerType(
                $this->requireNavsurText(
                    $data['tipo_contenedor'] ?? null,
                    'CODTIPOCONTENEDOR'
                ),
                (string) ($data['medida'] ?? '')
            );

        $state = $this->resolveNavsurContainerState(
            $data
        );

        $container = Container::create([
            'container_number' => $number,
            'container_type_id' =>
                $containerType->id,

            /*
             * El fixture trae TARA=0.
             * Se usa la especificación técnica del tipo,
             * no un 2200 genérico inventado.
             */
            'tare_weight_kg' =>
                $this->validateTareWeight(
                    $data,
                    $containerType
                ),

            'max_gross_weight_kg' =>
                $this->validateMaxGrossWeight(
                    $data,
                    $containerType
                ),

            'condition' => $state['condition'],
            'operational_status' =>
                $state['operational_status'],

            'current_port_id' =>
                $bill->loading_port_id,
            'active' => true,
        ]);

        $seals = $this->extractSeals($data);

        if ($seals) {
            $bill->update([
                'bl_seals_numbers' => $seals,
            ]);
        }

        return $container;
    }


    /**
     * Crear ShipmentItem
     */

    protected function createShipmentItem(
        BillOfLading $bill,
        array $data
    ): ?ShipmentItem {
        $description = $this->nullableNavsurText(
            $data['mercaderia'] ?? null
        );

        if ($description === null) {
            return null;
        }

        $quantity =
            $this->parseNavsurPackageQuantity(
                $data['cantidad'] ?? null
            );

        $gross =
            $this->parseNavsurRequiredPositiveNumber(
                $data['peso_bruto'] ?? null,
                'PESOBRUTO'
            );

        $net =
            $this->resolveNavsurNetWeight(
                $data
            );

        $volume =
            $this->parseNavsurRequiredNonNegativeNumber(
                $data['cubitaje'] ?? null,
                'CUBITAJE'
            );

        $lineNumber = ShipmentItem::where(
                'bill_of_lading_id',
                $bill->id
            )->count() + 1;

        return ShipmentItem::create([
            'bill_of_lading_id' => $bill->id,
            'line_number' => $lineNumber,

            'cargo_type_id' =>
                $this->resolveNavsurItemCargoTypeId(
                    $data
                ),

            'packaging_type_id' =>
                $this->resolveNavsurPackagingTypeId(
                    $data['embalaje'] ?? null
                ),

            'package_quantity' => $quantity,
            'gross_weight_kg' => $gross,
            'net_weight_kg' => $net,
            'volume_m3' => $volume,

            'item_description' =>
                mb_substr($description, 0, 1000),

            /*
             * HS/NCM de la propia descripción se conserva
             * como commodity_code. No se afirma posición AFIP.
             */
            'commodity_code' =>
                $this->extractNavsurCommodityCode(
                    $data
                ),

            'tariff_position' => null,

            'dangerous_cargo' =>
                $this->nullableNavsurText(
                    $data['imo'] ?? null
                ) !== null,

            'imo_code' =>
                $this->nullableNavsurText(
                    $data['imo'] ?? null
                ),
        ]);
    }


    /**
     * Buscar o crear cliente
     */
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
            'CUIT' => '/\bCUIT\b\s*(?:N(?:RO|º|°)?\.?\s*)?[:#-]?\s*([0-9][0-9.\-\/ ]{5,20}[0-9])/iu',
            'CNPJ' => '/\bCNPJ\b\s*(?:N(?:RO|º|°)?\.?\s*)?[:#-]?\s*([0-9][0-9.\-\/ ]{5,20}[0-9])/iu',
            'RUC'  => '/\bR\.?\s*U\.?\s*C\.?\b\s*(?:N(?:RO|º|°)?\.?\s*)?[:#-]?\s*([0-9][0-9.\-\/ ]{5,20}[0-9])/iu',
            'NIT'  => '/\bNIT\b\s*(?:N(?:RO|º|°)?\.?\s*)?[:#-]?\s*([0-9][0-9.\-\/ ]{5,20}[0-9])/iu',
        ];

        foreach ($patterns as $taxType => $pattern) {
            if (!preg_match($pattern, $text, $matches)) {
                continue;
            }

            $taxId = $this->resolveTaxId($matches[1], null, null);

            if ($taxId === null) {
                continue;
            }

            if (!$this->isTaxIdCompatibleWithType($taxId, $taxType)) {
                throw new \DomainException(
                    "Navsur: identificador {$taxType} con formato incompatible."
                );
            }

            return [
                'tax_id' => $taxId,
                'tax_type' => $taxType,
            ];
        }

        if (preg_match(
            '/\bTAX\s*ID\b\s*(?:N(?:RO|º|°)?\.?\s*)?[:#-]?\s*([0-9][0-9.\-\/ ]{5,20}[0-9])/iu',
            $text,
            $matches
        )) {
            $taxId = $this->resolveTaxId($matches[1], null, null);

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
    protected function resolveClientTaxIdentity(
        ?string $name,
        ?string $address
    ): array {
        $nameIdentity = $this->extractTypedTaxIdentityFromText($name);
        $addressIdentity = $this->extractTypedTaxIdentityFromText($address);

        if ($nameIdentity !== null && $addressIdentity !== null) {
            if ($nameIdentity['tax_id'] !== $addressIdentity['tax_id']) {
                throw new \DomainException(
                    'Navsur: nombre y domicilio declaran identificadores fiscales distintos.'
                );
            }

            if (
                $nameIdentity['tax_type'] !== null
                && $addressIdentity['tax_type'] !== null
                && $nameIdentity['tax_type'] !== $addressIdentity['tax_type']
            ) {
                throw new \DomainException(
                    'Navsur: nombre y domicilio declaran tipos fiscales distintos.'
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

        // Sólo analiza los campos propios de la parte. Nunca busca TAX/RUC
        // dentro de MERCADERIA u otros textos del conocimiento.
        return [
            'tax_id' => $this->resolveTaxId(null, $name, $address),
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

    protected function countryAlpha2FromPortCode(?string $portCode): ?string
    {
        $portCode = strtoupper(
            trim(str_replace(['/*', '*/'], '', (string) $portCode))
        );

        // Código portuario con forma UN/LOCODE: ISO alpha-2 + 3 caracteres.
        // No se aceptan placeholders como UNKNOWN como fuente de país.
        if (!preg_match('/^([A-Z]{2})[A-Z0-9]{3}$/', $portCode, $matches)) {
            return null;
        }

        return $matches[1];
    }

    protected function resolveClientCountryId(
        ?string $taxType,
        ?string $contextPortCode
    ): int {
        // Una identificación fiscal explícita es más fuerte que el contexto
        // logístico del puerto.
        $alpha2 = $this->countryAlpha2ForTaxType($taxType)
            ?? $this->countryAlpha2FromPortCode($contextPortCode);

        if ($alpha2 === null) {
            throw new \DomainException(
                'Navsur: no existe un país confiable para la parte.'
            );
        }

        $countryId = Country::query()
            ->where('alpha2_code', $alpha2)
            ->value('id');

        if (!$countryId) {
            throw new \DomainException(
                "Navsur: el país {$alpha2} no existe en el catálogo."
            );
        }

        return (int) $countryId;
    }

    /**
     * Buscar o crear cliente sin fabricar país ni documento fiscal.
     */
    protected function findOrCreateClient(
        ?string $name,
        string $type,
        ?string $address = null,
        ?string $contextPortCode = null
    ): ?Client {
        if (empty($name)) {
            return null;
        }

        $name = trim(str_replace(['/*', '*/'], '', $name));

        if ($name === '') {
            return null;
        }

        $identity = $this->resolveClientTaxIdentity(
            $name,
            $address
        );

        $taxId = $identity['tax_id'];
        $taxType = $identity['tax_type'];

        $countryId = $this->resolveClientCountryId(
            $taxType,
            $contextPortCode
        );

        // Con tax, la identidad es tax_id + país.
        // Nunca se degrada después a una coincidencia por nombre.
        if ($taxId !== null) {
            $client = Client::query()
                ->where('tax_id', $taxId)
                ->where('country_id', $countryId)
                ->first();

            if ($client) {
                return $client;
            }
        } else {
            // Sin tax sólo se reutiliza otro cliente sin tax, del mismo país
            // y con coincidencia exacta de nombre legal o comercial.
            $client = Client::query()
                ->whereNull('tax_id')
                ->where('country_id', $countryId)
                ->where(function ($query) use ($name) {
                    $query->where('legal_name', $name)
                        ->orWhere('commercial_name', $name);
                })
                ->first();

            if ($client) {
                return $client;
            }
        }

        $user = auth()->user();
        $companyId = null;

        if (
            $user->userable_type === 'App\Models\Company'
            && $user->userable_id
        ) {
            $companyId = (int) $user->userable_id;
        } elseif (
            $user->userable_type === 'App\Models\Operator'
            && $user->userable
        ) {
            $companyId = $user->userable->company_id;
        }

        // Se conserva por ahora la conducta previa de company_id.
        // No forma parte del contrato fiscal de este cambio.
        if (!$companyId) {
            Log::warning('No se pudo obtener company_id para crear cliente', [
                'user_id' => $user->id,
                'userable_type' => $user->userable_type,
                'client_name' => $name,
            ]);

            $companyId = 1;
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
                    "Navsur: no existe un tipo documental {$taxType} "
                    . 'activo y compatible con el país resuelto.'
                );
            }
        }

        $client = Client::create([
            'tax_id' => $taxId,
            'country_id' => $countryId,
            'document_type_id' => $documentTypeId,
            'legal_name' => $name,
            'commercial_name' => $name,
            'status' => 'active',
            'created_by_company_id' => $companyId,
            'verified_at' => now(),
        ]);

        if ($taxId === null) {
            $this->stats['warnings'][] =
                "Cliente '{$name}' creado sin tax_id declarado.";
        }

        Log::info('Navsur: alta de cliente con identidad preservada', [
            'role' => $type,
            'name' => $name,
            'tax_id' => $taxId,
            'tax_type' => $taxType,
            'country_id' => $countryId,
            'document_type_id' => $documentTypeId,
        ]);

        return $client;
    }

    /**
     * Buscar o crear puerto
     */
    protected function findOrCreatePort(string $code): Port
    {
        if (empty($code)) {
            $code = 'UNKNOWN';
        }

        $code = strtoupper(trim(str_replace(['/*', '*/'], '', $code)));

        $port = Port::where('code', $code)->first();
        
        if ($port) {
            return $port;
        }

        // Determinar país y ciudad basado en código
        $countryId = 1; // Argentina por defecto
        $cityName = 'Ciudad Desconocida'; // Valor por defecto para city (obligatorio)
        
        if (str_starts_with($code, 'PY')) {
            $countryId = 2; // Paraguay
            $cityName = $this->mapParaguayanPortCity($code);
        } elseif (str_starts_with($code, 'BR')) {
            $countryId = 3; // Brasil
            $cityName = $this->mapBrazilianPortCity($code);
        } else {
            // Argentina o códigos genéricos
            $cityName = $this->mapArgentinianPortCity($code);
        }

        $port = Port::create([
            'code' => $code,
            'name' => 'Puerto ' . $code,
            'city' => $cityName, // CORREGIDO: Campo obligatorio agregado
            'country_id' => $countryId,
            'port_type' => 'river',
            'active' => true,
            'handles_containers' => true,
            'handles_bulk_cargo' => true,
            'handles_general_cargo' => true,
            'has_customs_office' => true,
            'accepts_new_vessels' => true
        ]);

        $this->stats['warnings'][] = "Puerto '{$code}' creado automáticamente en {$cityName}";

        return $port;
    }

    /**
     * NUEVO MÉTODO: Mapear códigos paraguayos a ciudades
     */
    protected function mapParaguayanPortCity(string $code): string
    {
        $cityMap = [
            'PYCAP' => 'Capitán Carmelo Peralta',
            'PYASU' => 'Asunción',
            'PYVIL' => 'Villeta',
            'PYCON' => 'Concepción',
            'PYPIL' => 'Pilar',
            'PYALB' => 'Puerto Casado'
        ];
        
        return $cityMap[$code] ?? 'Asunción'; // Default Paraguay
    }

    /**
     * NUEVO MÉTODO: Mapear códigos argentinos a ciudades
     */
    protected function mapArgentinianPortCity(string $code): string
    {
        $cityMap = [
            'ARBUE' => 'Buenos Aires',
            'ARROS' => 'Rosario',
            'ARSFE' => 'Santa Fe',
            'ARPAR' => 'Paraná',
            'ARCOR' => 'Corrientes',
            'ARFOR' => 'Formosa',
            'ARBAH' => 'Bahía Blanca'
        ];
        
        return $cityMap[$code] ?? 'Buenos Aires'; // Default Argentina
    }

    /**
     * NUEVO MÉTODO: Mapear códigos brasileños a ciudades
     */
    protected function mapBrazilianPortCity(string $code): string
    {
        $cityMap = [
            'BRRIG' => 'Rio Grande',
            'BRPOA' => 'Porto Alegre',
            'BRSFS' => 'Santos',
            'BRSSZ' => 'Santos'
        ];
        
        return $cityMap[$code] ?? 'Porto Alegre'; // Default Brasil
    }

/**
 * Buscar o crear tipo de contenedor - VERSIÓN SIMPLIFICADA
 */

    protected function findOrCreateContainerType(
        string $code,
        string $size
    ): ContainerType {
        $source = strtoupper(
            trim(
                str_replace(
                    ['/*', '*/'],
                    '',
                    $code
                )
            )
        );

        /*
         * Sólo equivalencias ISO/industriales comprobadas.
         */
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
            throw new Exception(
                "Navsur: tipo de contenedor {$source} "
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
            throw new Exception(
                "Navsur requiere {$mapping[$source]} "
                . "para interpretar {$source}."
            );
        }

        return $type;
    }


    /**
     * Detectar categoría de contenedor
     */
    protected function detectContainerCategory(string $code): string
    {
        if (str_contains($code, 'RH') || str_contains($code, 'RF')) return 'reefer';
        if (str_contains($code, 'HC')) return 'high_cube';
        if (str_contains($code, 'TN') || str_contains($code, 'TK')) return 'tank';
        if (str_contains($code, 'OT')) return 'open_top';
        if (str_contains($code, 'FR')) return 'flat_rack';
        return 'dry';
    }

    /**
     * Mapear tipo de embalaje
     */

    protected function mapPackagingType(
        string $packaging
    ): ?int {
        return $this->resolveNavsurPackagingTypeId(
            $packaging
        );
    }


    /**
     * Mapear bandera a código ISO
     */

    protected function mapFlag(
        string $flag
    ): ?string {
        $flag = strtoupper(
            trim(
                str_replace(
                    ['/*', '*/'],
                    '',
                    $flag
                )
            )
        );

        if (str_contains($flag, 'PARAGUAY')) {
            return 'PY';
        }

        if (str_contains($flag, 'ARGENTIN')) {
            return 'AR';
        }

        if (
            str_contains($flag, 'BRASIL')
            || str_contains($flag, 'BRAZIL')
        ) {
            return 'BR';
        }

        return null;
    }


    /**
     * Extraer números de sellos
     */
    protected function extractSeals(array $data): ?string
    {
        $seals = [];
        
        $sealFields = [
            'precintos_linea',
            'precintos_aduana',
            'precintos_senacsa',
            'precintos_cliente'
        ];

        foreach ($sealFields as $field) {
            if (!empty($data[$field])) {
                $sealValue = trim(str_replace(['/*', '*/'], '', $data[$field]));
                if (!empty($sealValue)) {
                    $seals[] = $sealValue;
                }
            }
        }

        return !empty($seals) ? implode(', ', $seals) : null;
    }

    /**
     * Validar datos parseados antes de procesamiento
     */
    public function validate(array $data): array
    {
        $errors = [];

        // Validar que existe al menos un BL
        if (empty($data) || !is_array($data)) {
            $errors[] = 'No se encontraron datos válidos en el archivo';
            return $errors;
        }

        foreach ($data as $index => $bl) {
            $blIndex = $index + 1;

            // Validar campos obligatorios del BL
            if (empty($bl['numero_bl'])) {
                $errors[] = "BL #{$blIndex}: Número de BL es obligatorio";
            }

            if (empty($bl['buque'])) {
                $errors[] = "BL #{$blIndex}: Nombre del buque es obligatorio";
            }

            if (empty($bl['viaje'])) {
                $errors[] = "BL #{$blIndex}: Número de viaje es obligatorio";
            }

            // Validar puertos
            if (empty($bl['puerto_carga'])) {
                $errors[] = "BL #{$blIndex}: Puerto de carga es obligatorio";
            }

            if (empty($bl['puerto_descarga'])) {
                $errors[] = "BL #{$blIndex}: Puerto de descarga es obligatorio";
            }

            // Validar partes involucradas
            if (empty($bl['cargador_nombre'])) {
                $errors[] = "BL #{$blIndex}: Nombre del cargador es obligatorio";
            }

            if (empty($bl['consignatario_nombre'])) {
                $errors[] = "BL #{$blIndex}: Nombre del consignatario es obligatorio";
            }

            // Validar contenedores
            if (empty($bl['containers']) || !is_array($bl['containers'])) {
                $errors[] = "BL #{$blIndex}: Debe tener al menos un contenedor";
            } else {
                foreach ($bl['containers'] as $containerIndex => $container) {
                    $contIndex = $containerIndex + 1;

                    if (empty($container['cod_contenedor'])) {
                        $errors[] = "BL #{$blIndex}, Contenedor #{$contIndex}: Código de contenedor es obligatorio";
                    }

                    if (empty($container['tipo_contenedor'])) {
                        $errors[] = "BL #{$blIndex}, Contenedor #{$contIndex}: Tipo de contenedor es obligatorio";
                    }

                    // Validar items del contenedor
                    if (empty($container['items']) || !is_array($container['items'])) {
                        $errors[] = "BL #{$blIndex}, Contenedor #{$contIndex}: Debe tener al menos un item de mercadería";
                    } else {
                        foreach ($container['items'] as $itemIndex => $item) {
                            $itemNum = $itemIndex + 1;

                            if (empty($item['mercaderia'])) {
                                $errors[] = "BL #{$blIndex}, Contenedor #{$contIndex}, Item #{$itemNum}: Descripción de mercadería es obligatoria";
                            }

                            if (empty($item['cantidad']) || $item['cantidad'] <= 0) {
                                $errors[] = "BL #{$blIndex}, Contenedor #{$contIndex}, Item #{$itemNum}: Cantidad debe ser mayor a 0";
                            }
                        }
                    }
                }
            }
        }

        return $errors;
    }

    /**
     * Transformar datos parseados a formato estándar del sistema
     */
    public function transform(array $data): array
    {
        $transformed = [];

        foreach ($data as $bl) {
            $transformedBl = [
                'bill_number' => $bl['numero_bl'],
                'booking_reference' => $bl['cod_booking'],
                'internal_reference' => $bl['cod_programacion'],
                'vessel_name' => $bl['buque'],
                'voyage_number' => $bl['viaje'],
                'vessel_flag' => $this->mapFlag($bl['bandera'] ?? ''),
                'freight_terms' => $bl['condicion_flete'] ?? 'PREPAID',
                'loading_port' => $bl['puerto_carga'],
                'discharge_port' => $bl['puerto_descarga'],
                'final_destination' => $bl['destino_final'],
                'shipper' => [
                    'name' => $bl['cargador_nombre'],
                    'type' => 'shipper'
                ],
                'consignee' => [
                    'name' => $bl['consignatario_nombre'],
                    'type' => 'consignee'
                ],
                'notify_party' => [
                    'name' => $bl['notificatario1_nombre'],
                    'type' => 'notify'
                ],
                'containers' => []
            ];

            // Transformar contenedores
            foreach ($bl['containers'] as $container) {
                $transformedContainer = [
                    'container_number' => $container['cod_contenedor'],
                    'container_type' => $container['tipo_contenedor'],
                    'size_feet' => intval($container['medida'] ?? 20),
                    'tare_weight' => floatval($container['tara'] ?? 0),
                    'temperature' => $container['temperatura'],
                    'seals' => [
                        'line_seals' => $container['precintos_linea'],
                        'customs_seals' => $container['precintos_aduana'],
                        'senacsa_seals' => $container['precintos_senacsa']
                    ],
                    'cargo_items' => []
                ];

                // Transformar items de carga
                foreach ($container['items'] as $item) {
                    $transformedItem = [
                        'line_number' => intval($item['item'] ?? 1),
                        'description' => $item['mercaderia'],
                        'packaging_type' => $item['embalaje'],
                        'package_quantity' => intval($item['cantidad'] ?? 0),
                        'gross_weight_kg' => floatval($item['peso_bruto'] ?? 0),
                        'net_weight_kg' => floatval($item['peso_neto'] ?? 0),
                        'volume_m3' => floatval($item['cubitaje'] ?? 0),
                        'commodity_code' => $item['partida_arancelaria'],
                        'imo_code' => $item['imo'],
                        'dangerous_cargo' => !empty($item['imo'])
                    ];

                    $transformedContainer['cargo_items'][] = $transformedItem;
                }

                $transformedBl['containers'][] = $transformedContainer;
            }

            $transformed[] = $transformedBl;
        }

        return $transformed;
    }

    /**
     * Obtener información del formato soportado
     */
    public function getFormatInfo(): array
    {
        return [
            'name' => 'Navsur Text Format',
            'description' => 'Formato de texto jerárquico utilizado por Navsur con marcadores **BL**, **CONTENEDORES**, **MERCADERIAS**',
            'extensions' => ['txt'],
            'mime_types' => ['text/plain'],
            'characteristics' => [
                'Marcadores de sección con doble asterisco',
                'Campos con formato CAMPO: /*valor*/',
                'Estructura jerárquica BL > Contenedores > Mercaderías',
                'Múltiples BLs por archivo',
                'Línea naviera principal: MSC'
            ],
            'sample_patterns' => [
                '**BL**',
                'NUMEROBL: /*valor*/',
                '**CONTENEDORES**',
                '**MERCADERIAS**',
                '**FIN BL**'
            ],
            'required_fields' => [
                'NUMEROBL',
                'BUQUE',
                'VIAJE',
                'CODPUERTODECARGA',
                'CODPUERTODEDESCARGA',
                'CARGADORNOMBRE',
                'CONSIGNATARIONOMBRE'
            ],
            'optional_fields' => [
                'CODBOOKING',
                'CODPROGRAMACION',
                'DESTINOFINAL',
                'NOTIFICATARIO1NOMBRE',
                'TEMPERATURA',
                'PRECINTOSLINEA',
                'PRECINTOSADUANA',
                'PRECINTOSENACSA'
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
            'line_ending' => 'auto',
            'skip_empty_lines' => true,
            'trim_whitespace' => true,
            'case_sensitive' => false,
            'validate_containers' => true,
            'validate_cargo_items' => true,
            'create_missing_clients' => true,
            'create_missing_ports' => true,
            'create_missing_container_types' => true,
            'default_tare_weight' => 2200,
            'default_max_gross_weight' => 30000,
            'default_country_mapping' => [
                'PARAGUAYA' => 'PY',
                'ARGENTINA' => 'AR',
                'BRASIL' => 'BR'
            ],
            'packaging_type_mapping' => [
                'BAGS' => 1,
                'CARTONS' => 2,
                'PALLETS' => 3,
                'PALLET(S)' => 3,
                'BARRELS' => 4,
                'BARREL(S)' => 4,
                'BOXES' => 5
            ]
        ];
    }

    /**
 * ✅ VALIDAR descripción real del archivo
 */
protected function validateCargoDescription(array $data): string
{
    // ✅ Buscar descripción en múltiples ubicaciones posibles
    
    // 1. En items de contenedores
    if (!empty($data['containers'])) {
        foreach ($data['containers'] as $container) {
            if (!empty($container['items'])) {
                foreach ($container['items'] as $item) {
                    if (!empty($item['mercaderia'])) {
                        return trim($item['mercaderia']);
                    }
                }
            }
        }
    }
    
    // 2. En el título del BL
    if (!empty($data['titulo'])) {
        return trim($data['titulo']);
    }
    
    // 3. En buque + viaje como descripción básica
    if (!empty($data['buque']) && !empty($data['viaje'])) {
        return "Mercadería transportada en {$data['buque']} viaje {$data['viaje']}";
    }
    
    // 4. Última opción: descripción básica
    Log::warning('⚠️ NAVSUR.TXT sin descripción específica - usando descripción básica');
    return 'Mercadería general según manifiesto NAVSUR';
}


    protected function validatePackagingTypeId(
        array $data
    ): ?int {
        return $this->resolveNavsurPrimaryPackagingTypeId(
            $data
        );
    }


/**
 * ✅ Mapear embalaje NAVSUR a packaging_type_id
 */
protected function mapEmbalajeToPackagingType(string $embalaje): int
{
    $embalaje = strtoupper(trim($embalaje));
    
    // Mapear tipos de embalaje comunes
    if (str_contains($embalaje, 'BAGS') || str_contains($embalaje, 'BOLSAS')) {
        return 1; // Bags
    }
    if (str_contains($embalaje, 'CARTONS') || str_contains($embalaje, 'CAJAS')) {
        return 2; // Cartons
    }
    if (str_contains($embalaje, 'PALLETS') || str_contains($embalaje, 'PALETAS')) {
        return 3; // Pallets
    }
    if (str_contains($embalaje, 'BARRELS') || str_contains($embalaje, 'BARRILES')) {
        return 4; // Barrels
    }
    if (str_contains($embalaje, 'BOXES') || str_contains($embalaje, 'CONTENEDORES')) {
        return 5; // Boxes
    }
    
    Log::warning("Tipo de embalaje no reconocido: {$embalaje} - usando Bags por defecto");
    return 1; // Default: Bags
}


    protected function validateTareWeight(
        array $data,
        ContainerType $containerType
    ): float {
        $raw = $this->parseNavsurNumber(
            $data['tara'] ?? null
        );

        if ($raw !== null && $raw > 0) {
            return $raw;
        }

        if (
            $containerType->tare_weight_kg === null
        ) {
            throw new Exception(
                'Navsur: no existe tara de fuente '
                . "ni de catálogo para {$containerType->code}."
            );
        }

        return (float)
            $containerType->tare_weight_kg;
    }



    protected function validateMaxGrossWeight(
        array $data,
        ContainerType $containerType
    ): float {
        $raw = $this->parseNavsurNumber(
            $data['peso_maximo'] ?? null
        );

        if ($raw !== null && $raw > 0) {
            return $raw;
        }

        if (
            $containerType->max_gross_weight_kg === null
        ) {
            throw new Exception(
                'Navsur: no existe peso máximo '
                . "de catálogo para {$containerType->code}."
            );
        }

        return (float)
            $containerType->max_gross_weight_kg;
    }


}