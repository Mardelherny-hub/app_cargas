<?php

namespace App\Services\Parsers;

use App\Contracts\ManifestParserInterface;
use App\ValueObjects\ManifestParseResult;
use App\Models\ManifestImport;
use App\Models\Voyage;
use App\Models\Shipment;
use App\Models\BillOfLading;
use App\Models\ShipmentItem;
use App\Models\Container;
use App\Models\Client;
use App\Models\Port;
use App\Models\Country;
use App\Models\CargoType;
use App\Models\PackagingType;
use App\Models\ContainerType;
use App\Models\Vessel;
use App\Models\VesselType;
use App\Models\Company;
use App\Models\User;
use App\Services\Parsers\Concerns\ExtractsEmbeddedTaxId;
use App\Services\Parsers\Concerns\EnsuresUniqueVoyageNumber;
use App\Services\Parsers\Concerns\ResolvesClientAddresses;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Exception;
use SimpleXMLElement;

/**
 * PARSER PARA LOGIN.XML - MANIFIESTO XML ANIDADO COMPLETO
 * 
 * Estructura XML verificada:
 * - BillOfLadingRoot
 *   └── BillOfLading
 *       ├── BillOfLadingHeader (shipper, consignee, voyage)
 *       └── BillOfLadingLineDetail
 *           └── BillOfLadingLine[] (contenedores individuales)
 * 
 * Características identificadas:
 * - Múltiples contenedores por B/L
 * - Tipos: 40RH (Reefer High Cube), 40HC (High Cube)
 * - Pesos: Tare, NetWeight, GrossWeight
 * - Sellos múltiples por contenedor
 * - Códigos NCM por línea
 * - VGM (Verified Gross Mass) opcional
 */
class LoginXmlParser implements ManifestParserInterface
{
    use ExtractsEmbeddedTaxId;
    use EnsuresUniqueVoyageNumber;
    use ResolvesClientAddresses;
    // Mapeo de tipos de contenedor del XML a tipos del sistema
    protected array $containerTypeMapping = [
        '40RH' => 'Reefer High Cube 40ft',
        '40HC' => 'High Cube 40ft',
        '20DV' => 'Dry Van 20ft',
        '20RH' => 'Reefer High Cube 20ft',
        '40DV' => 'Dry Van 40ft',
        '20HC' => 'High Cube 20ft',
        '40FR' => 'Flat Rack 40ft',
        '20FR' => 'Flat Rack 20ft',
        '40OT' => 'Open Top 40ft',
        '20OT' => 'Open Top 20ft'
    ];

    /**
     * Alias de nomenclatura propios del formato Login hacia el `code` real
     * del catálogo container_types. Login usa "DC" (Dry Container) donde el
     * catálogo usa "GP" (General Purpose), y "TK" (tanque genérico) donde el
     * catálogo usa "TN". Equivalencias verificadas contra las filas reales
     * 20GP/40GP/20TN. Los 20TK del archivo son tanques vacíos; la peligrosidad,
     * si existiera, viaja por Un/Classe a nivel de línea, no por el tipo.
     */
    protected array $loginContainerAliases = [
        '20DC' => '20GP',
        '40DC' => '40GP',
        '20TK' => '20TN',
    ];

    // Contenedores creados durante la importación actual (evita duplicados)
    protected array $createdContainersInImport = [];

    // Cache de catálogos para evitar queries repetidas en el loop de BLs/contenedores.
    protected ?CargoType $cachedCargoType = null;
    protected ?PackagingType $cachedPackagingType = null;
    protected array $cachedContainerTypes = [];

    // Advertencias no bloqueantes originadas en datos explícitos del archivo.
    protected array $warnings = [];

    /**
     * Verificar si el parser puede procesar el archivo XML
     */
    public function canParse(string $filePath): bool
    {
        if (strtolower(pathinfo($filePath, PATHINFO_EXTENSION)) !== 'xml') {
            return false;
        }

        try {
            $xmlContent = file_get_contents($filePath);
            
            // Verificar indicadores específicos de Login XML
            $loginIndicators = [
                'BillOfLadingRoot',
                'BillOfLadingHeader',
                'BillOfLadingLineDetail',
                'BillOfLadingLine',
                'Container',
                'Tare',
                'NetWeight',
                'GrossWeight'
            ];

            $indicatorCount = 0;
            foreach ($loginIndicators as $indicator) {
                if (strpos($xmlContent, $indicator) !== false) {
                    $indicatorCount++;
                }
            }

            // Debe tener al menos 6 de 8 indicadores para ser Login XML
            return $indicatorCount >= 6;

        } catch (Exception $e) {
            Log::warning('Error verificando Login XML', [
                'file_path' => $filePath,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Parsear el archivo Login XML (Interface compatible)
     */
    public function parse(string $filePath): ManifestParseResult
    {
        Log::debug('=== INICIO LOGIN XML PARSER ===', [
            'file_path' => $filePath,
            'file_exists' => file_exists($filePath),
            'file_size' => file_exists($filePath) ? filesize($filePath) : 'N/A'
        ]);

        try {
            // Obtener contexto desde la sesión/auth actual
            $context = $this->getParsingContext();
            
            Log::debug('Contexto obtenido', $context);
            
            return $this->parseWithContext($filePath, $context);
        } catch (Exception $e) {
            Log::error('Error en parse() principal', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            throw $e;
        }
    }

    /**
     * Obtener contexto de parsing desde la sesión actual
     */
    protected function getParsingContext(): array
    {
        Log::debug('=== OBTENIENDO CONTEXTO ===');
    
        $user = auth()->user();
        
        Log::debug('Usuario autenticado', [
            'user_exists' => $user ? 'SI' : 'NO',
            'user_id' => $user?->id,
            'company_id' => $user?->company_id
        ]);
        
        $user = auth()->user();
        
        if (!$user) {
            throw new Exception('Usuario no autenticado para importación');
        }

        // Obtener company_id según el tipo de usuario
        $companyId = null;

        if ($user->userable_type === 'App\\Models\\Company') {
            // Company admin: la empresa está directamente en userable_id
            $companyId = $user->userable_id;
        } elseif ($user->userable_type === 'App\\Models\\Operator' && $user->userable) {
            // User operador: la empresa está en userable->company_id
            $companyId = $user->userable->company_id;
        }

        if (!$companyId) {
            throw new Exception('Usuario sin empresa asignada para importación');
        }

        return [
            'company_id' => $companyId,
            'user_id' => $user->id
        ];
    }

    /**
     * Parsear el archivo Login XML con contexto específico - SOPORTA MÚLTIPLES BLs
     */
    protected function parseWithContext(string $filePath, array $context): ManifestParseResult
    {
        Log::info('Iniciando parsing de Login XML', [
            'file_path' => $filePath,
            'company_id' => $context['company_id'],
            'user_id' => $context['user_id']
        ]);

        try {
            DB::beginTransaction();

            // Red de seguridad: archivos Login grandes (100+ BLs) requieren mas memoria.
            @ini_set('memory_limit', '1024M');

            $startTime = microtime(true);

             // Reset container tracking for this import
            $this->createdContainersInImport = [];
            $this->cachedCargoType = null;
            $this->cachedPackagingType = null;
            $this->cachedContainerTypes = [];
            
            // Crear registro de importación
            $importRecord = $this->createImportRecord($filePath, $context);

            // 1. Leer y parsear XML
            $xmlContent = file_get_contents($filePath);
            $xml = new SimpleXMLElement($xmlContent);
            
            // 2. Extraer datos del XML (ahora extrae TODOS los BLs)
            $rawData = $this->extractDataFromXml($xml);
            
            // 3. Validar datos extraídos
            $validationErrors = $this->validate($rawData);
            if (!empty($validationErrors)) {
                throw new Exception('Datos XML no válidos: ' . implode(', ', $validationErrors));
            }
            
            // 4. Transformar a formato estándar
            $transformedData = $this->transform($rawData);
            
            // 5. Crear objetos del modelo
            $voyage = $this->createVoyage($transformedData, $context);
            $shipment = $this->createShipment($transformedData, $voyage, $context);
            
            // 6. Crear MÚLTIPLES BillOfLading con sus items
            // Acumulamos solo IDs (no objetos Eloquent) para minimizar memoria
            // en archivos grandes (100+ BLs, cientos de contenedores).
            $allBillIds = [];
            $allItemIds = [];
            
            foreach ($transformedData['bills_of_lading'] as $blData) {
                $billOfLading = $this->createBillOfLadingFromData($blData, $shipment, $context);
                $allBillIds[] = $billOfLading->id;
                
                $itemIds = $this->createShipmentItemsFromData(
                    $blData['containers'],
                    $billOfLading,
                    $context
                );

                foreach ($itemIds as $iid) {
                    $allItemIds[] = $iid;
                }

                /*
                 * Los eventos de ShipmentItem recalculan estadísticas.
                 * Para Login, la cabecera XML vuelve a ser la fuente
                 * autoritativa al terminar de crear los items.
                 */
                /*
                 * Los eventos de ShipmentItem actualizan el mismo BL usando
                 * otras instancias de Eloquent. Refrescar primero evita que
                 * el dirty-check compare contra atributos obsoletos en memoria.
                 */
                $billOfLading->refresh();

                $billOfLading->updateQuietly([
                    'gross_weight_kg' => $blData['total_weight_kg'],
                    'volume_m3' => $blData['volume_m3'],
                    'cargo_marks' => $blData['cargo_marks'] ?? null,

                    // Login no informa cantidad de bultos.
                    // 0 representa desconocido por exigencia del esquema.
                    'total_packages' => 0,

                    'container_count' => $blData['total_containers'],
                ]);

                // Liberar el objeto BL de memoria tras usar su id
                unset($billOfLading);
            }
            
            // Completar el tracking dentro de la misma transacción.
            // Si esto falla, también deben revertirse voyage, shipment,
            // BLs, items, contenedores y clientes creados por la importación.
            $this->completeImportRecord(
                $importRecord,
                $voyage,
                [$shipment->id],
                $allBillIds,
                $allItemIds,
                $startTime,
                $this->warnings
            );

            DB::commit();

            Log::info('Login XML parseado exitosamente', [
                'voyage_id' => $voyage->id,
                'shipment_id' => $shipment->id,
                'bills_of_lading_count' => count($allBillIds),
                'items_created' => count($allItemIds)
            ]);

            return ManifestParseResult::success(
                voyage: $voyage,
                shipments: [$shipment],
                billsOfLading: [],
                containers: [],
                warnings: $this->warnings,
                statistics: [
                    'format' => 'Login XML',
                    'bills_of_lading' => count($allBillIds),
                    'containers' => count($transformedData['containers']),
                    'total_weight_kg' => array_sum(array_column($transformedData['containers'], 'gross_weight_kg')),
                    'shipper' => 'Multiple shippers',
                    'consignee' => 'Multiple consignees',
                    'warnings_count' => count($this->warnings)
                ]
            );

        } catch (\Throwable $e) {
            DB::rollBack();
            
            // Detectar voyage duplicado
            if (strpos($e->getMessage(), 'voyages_voyage_number_unique') !== false) {
                $voyageNumber = $transformedData['voyage']['voyage_number'] ?? 'desconocido';
                Log::warning('Intento de importar voyage duplicado', [
                    'voyage_number' => $voyageNumber,
                    'file_path' => $filePath,
                    'user_id' => $context['user_id']
                ]);
                
                return ManifestParseResult::failure([
                    "Este archivo ya fue importado anteriormente."
                ]);
            }
            
            // Otros errores
            Log::error('Error parseando Login XML', [
                'file_path' => $filePath,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return ManifestParseResult::failure([
                $e->getMessage()
            ]);
        }
    }

    /**
     * Extraer datos del XML parseado - SOPORTA MÚLTIPLES BillOfLading
     */
    protected function extractDataFromXml(SimpleXMLElement $xml): array
    {
        Log::debug('=== EXTRAYENDO DATOS XML ===');

        $data = [
            'header' => [],           // Header del primer BL (para voyage/shipment)
            'bills_of_lading' => [],  // Array con TODOS los BLs
            'containers' => []        // Todos los containers (para compatibilidad)
        ];

        $blCount = 0;
        
        // Iterar sobre TODOS los BillOfLading del XML
        foreach ($xml->BillOfLading as $billOfLading) {
            $blCount++;
            Log::debug("Procesando BillOfLading #{$blCount}");
            
            if (!isset($billOfLading->BillOfLadingHeader)) {
                Log::warning("BillOfLading #{$blCount} sin header, saltando");
                continue;
            }
            
            $header = $billOfLading->BillOfLadingHeader;

            [$vesselName, $voyageNumber] = $this->parseVesselVoyageFlag(
                (string) $header->InitialVesselVoyFlag
            );
            
            // Extraer datos del header de este BL
            $blData = [
                'bill_number' => (string)$header->BillOfLadingNumber ?? null,
                'shipper_name' => (string)$header->ShipperExporter ?? null,
                'shipper_cuit' => (string)$header->ShipperExporterCUIT ?? null,
                'consignee_name' => (string)$header->Consignee ?? null,
                'notify_party_name' => (string)$header->NotifyParty ?? null,
                'booking_number' => (string)$header->BookingNumber ?? null,
                'vessel_name' => $vesselName,
                'loading_port' => (string)$header->InitalPortOfLoading ?? (string)$header->FinalPortOfLoading ?? null,
                'discharge_port' => (string)$header->PortOfDischarge ?? null,
                'gross_weight' => (string)$header->GrossWeight ?? null,
                'measurement' => (string)$header->Measurement ?? null,
                'cargo_description' => (string)$header->DescriptionOfPackagesAndGoods ?? null,
                'cargo_marks' => trim((string)$header->MksAndNos) ?: null,
                'loading_date' => null,
                'bill_date' => null,
                'voyage_number' => $voyageNumber,
                'containers' => []  // Containers de este BL específico
            ];
            
            // Extraer contenedores de este BL
            if (isset($billOfLading->BillOfLadingLineDetail->BillOfLadingLine)) {
                foreach ($billOfLading->BillOfLadingLineDetail->BillOfLadingLine as $line) {
                    $container = [
                        'line_number' => (int)$line->BillOfLadingLineNumber ?? 0,
                        'container_number' => (string)$line->Container ?? null,
                        'container_type' => (string)$line->Type ?? null,
                        'tare_weight_kg' => $this->parseOptionalWeight(
                            isset($line->Tare) ? (string) $line->Tare : null
                        ),
                        'net_weight_kg' => $this->parseOptionalWeight(
                            isset($line->NetWeight) ? (string) $line->NetWeight : null
                        ),
                        'gross_weight_kg' => $this->parseOptionalWeight(
                            isset($line->GrossWeight) ? (string) $line->GrossWeight : null
                        ),
                        'vgm' => isset($line->Vgm)
                            ? $this->parseOptionalWeight((string) $line->Vgm)
                            : null,
                        'seals' => [],
                        'ncm_codes' => []
                    ];

                    // Extraer sellos
                    if (isset($line->Seal->Nseal)) {
                        foreach ($line->Seal->Nseal as $seal) {
                            $container['seals'][] = (string)$seal;
                        }
                    }

                    // Extraer códigos NCM
                    if (isset($line->Ncm->Nncm)) {
                        foreach ($line->Ncm->Nncm as $ncm) {
                            $container['ncm_codes'][] = (string)$ncm;
                        }
                    }

                    $blData['containers'][] = $container;
                }
            }
            
            $blData['containers'] = $this->consolidateLoginContainers(
                $blData['containers'],
                $blData['bill_number']
            );

            foreach ($blData['containers'] as $container) {
                $data['containers'][] = $container;
            }

            $data['bills_of_lading'][] = $blData;
            
            // El primer BL se usa como header principal (para voyage/shipment)
            if (empty($data['header'])) {
                $data['header'] = $blData;
            }
            
            Log::debug("BL #{$blCount}: {$blData['bill_number']} con " . count($blData['containers']) . " contenedores");
        }
        
        Log::info("Total BLs extraídos: " . count($data['bills_of_lading']) . ", Total contenedores: " . count($data['containers']));

        return $data;
    }

    protected function parseVesselVoyageFlag(string $raw): array
    {
        $raw = trim($raw);

        if ($raw === '' || !str_contains($raw, '/')) {
            throw new Exception(
                "Login no informa buque/viaje en formato válido: {$raw}"
            );
        }

        [$vesselName, $voyageNumber] = array_map(
            'trim',
            explode('/', $raw, 2)
        );

        if ($vesselName === '' || $voyageNumber === '') {
            throw new Exception(
                "Login informa buque/viaje incompleto: {$raw}"
            );
        }

        return [$vesselName, $voyageNumber];
    }

    protected function consolidateLoginContainers(
        array $containers,
        string $billNumber
    ): array {
        $result = [];

        foreach ($containers as $container) {
            $number = strtoupper(trim((string) $container['container_number']));

            if (!isset($result[$number])) {
                $container['container_number'] = $number;
                $container['seals'] = array_values(array_unique($container['seals']));
                $container['ncm_codes'] = array_values(array_unique($container['ncm_codes']));
                $result[$number] = $container;
                continue;
            }

            $current = $result[$number];

            foreach ([
                'container_type',
                'tare_weight_kg',
                'net_weight_kg',
                'gross_weight_kg',
            ] as $field) {
                if ($current[$field] != $container[$field]) {
                    throw new Exception(
                        "Contenedor {$number} repetido en BL {$billNumber} "
                        . "con distinto {$field}"
                    );
                }
            }

            if (
                $current['vgm'] !== null
                && $container['vgm'] !== null
                && $current['vgm'] != $container['vgm']
            ) {
                throw new Exception(
                    "Contenedor {$number} repetido en BL {$billNumber} "
                    . "con distinto VGM"
                );
            }

            if ($current['vgm'] === null && $container['vgm'] !== null) {
                $current['vgm'] = $container['vgm'];
            }

            $current['seals'] = array_values(array_unique(array_merge(
                $current['seals'],
                $container['seals']
            )));

            $current['ncm_codes'] = array_values(array_unique(array_merge(
                $current['ncm_codes'],
                $container['ncm_codes']
            )));

            $result[$number] = $current;
        }

        return array_values($result);
    }

    /**
     * Parsear una medida sin confundir ausencia con cero explícito.
     */
    protected function parseOptionalWeight(?string $weightStr): ?float
    {
        if ($weightStr === null || trim($weightStr) === '') {
            return null;
        }

        $normalized = str_replace(',', '.', trim($weightStr));
        $cleaned = preg_replace('/[^0-9.-]/', '', $normalized);

        if ($cleaned === '' || $cleaned === '-' || $cleaned === '.') {
            return null;
        }

        return (float) $cleaned;
    }

    /**
     * Compatibilidad para campos donde el contrato histórico requiere float.
     */
    protected function parseWeight(string $weightStr): float
    {
        return $this->parseOptionalWeight($weightStr) ?? 0.0;
    }

    /**
     * Extraer tax_id (CNPJ/CUIT) del texto
     */
    protected function extractTaxIdFromText(string $text): ?string
    {
        // Buscar CNPJ pattern: XX.XXX.XXX/XXXX-XX
        if (preg_match('/CNPJ:\s*([0-9]{2}\.?[0-9]{3}\.?[0-9]{3}\/[0-9]{4}-[0-9]{2})/', $text, $matches)) {
            return preg_replace('/[^0-9]/', '', $matches[1]);
        }
        
        // Buscar CUIT pattern: XX-XXXXXXXX-X
        if (preg_match('/CUIT:\s*([0-9]{2}-?[0-9]{8}-?[0-9])/', $text, $matches)) {
            return preg_replace('/[^0-9]/', '', $matches[1]);
        }
        
        return null;
    }

    /**
     * Validar datos extraídos.
     *
     * Un cero informado por Login se conserva y genera advertencia.
     * Un dato obligatorio ausente o un valor negativo bloquea la importación.
     */
    public function validate(array $data): array
    {
        $errors = [];
        $this->warnings = [];

        if (empty($data['bills_of_lading'])) {
            $errors[] = 'Al menos un Bill of Lading es requerido en el XML';
            return $errors;
        }

        if (empty($data['header']['loading_port'])) {
            $errors[] = 'Puerto de carga requerido en el XML';
        }

        if (empty($data['header']['discharge_port'])) {
            $errors[] = 'Puerto de descarga requerido en el XML';
        }

        if (empty($data['header']['voyage_number'])) {
            $errors[] = 'Número de viaje requerido en el XML';
        }

        if (empty($data['header']['vessel_name'])) {
            $errors[] = 'Nombre de buque requerido en el XML';
        }

        foreach ($data['bills_of_lading'] as $blIndex => $bl) {
            $blNum = $blIndex + 1;

            $blRef = trim((string) ($bl['bill_number'] ?? ''));
            $blRef = $blRef !== '' ? $blRef : "BL #{$blNum}";

            if (empty($bl['bill_number'])) {
                $errors[] = "Número de Bill of Lading requerido en {$blRef}";
            }

            if (empty($bl['shipper_name']) && empty($bl['shipper_cuit'])) {
                $errors[] = "Información del shipper requerida en {$blRef}";
            }

            if (empty($bl['consignee_name'])) {
                $errors[] = "Nombre del consignee requerido en {$blRef}";
            }

            if (trim((string) ($bl['cargo_description'] ?? '')) === '') {
                $errors[] = "Descripción de mercadería requerida en {$blRef}";
            }

            $headerGross = $this->parseOptionalWeight(
                isset($bl['gross_weight'])
                    ? (string) $bl['gross_weight']
                    : null
            );

            if ($headerGross === null) {
                $errors[] = "Peso bruto de cabecera ausente en {$blRef}";
            } elseif ($headerGross < 0) {
                $errors[] = "Peso bruto de cabecera negativo en {$blRef}";
            } elseif ($headerGross == 0.0) {
                $this->warnings[] =
                    "{$blRef}: peso bruto de cabecera informado en 0; "
                    . "debe completarse manualmente si corresponde.";
            }

            $headerVolume = $this->parseOptionalWeight(
                isset($bl['measurement'])
                    ? (string) $bl['measurement']
                    : null
            );

            if ($headerVolume !== null) {
                if ($headerVolume < 0) {
                    $errors[] = "Volumen de cabecera negativo en {$blRef}";
                } elseif ($headerVolume == 0.0) {
                    $this->warnings[] =
                        "{$blRef}: volumen informado en 0; "
                        . "debe completarse manualmente si corresponde.";
                }
            }

            if (empty($bl['containers'])) {
                $errors[] = "Al menos un contenedor requerido en {$blRef}";
                continue;
            }

            foreach ($bl['containers'] as $cIndex => $container) {
                $containerNumber = trim(
                    (string) ($container['container_number'] ?? '')
                );

                $lineRef = $containerNumber !== ''
                    ? "{$blRef} contenedor {$containerNumber}"
                    : "{$blRef} línea " . ($cIndex + 1);

                if ($containerNumber === '') {
                    $errors[] = "Número de contenedor requerido en {$lineRef}";
                }

                if (empty($container['container_type'])) {
                    $errors[] = "Tipo de contenedor requerido en {$lineRef}";
                } elseif (
                    $this->resolveContainerType(
                        $container['container_type']
                    ) === null
                ) {
                    $errors[] =
                        'Tipo de contenedor desconocido "'
                        . strtoupper(
                            trim((string) $container['container_type'])
                        )
                        . "\" en {$lineRef}. Debe agregarse su "
                        . 'correspondencia al catálogo antes de importar.';
                }

                $gross = $container['gross_weight_kg'] ?? null;
                $net = $container['net_weight_kg'] ?? null;
                $tare = $container['tare_weight_kg'] ?? null;
                $vgm = $container['vgm'] ?? null;

                if ($gross === null) {
                    $errors[] = "Peso bruto ausente en {$lineRef}";
                } elseif ($gross < 0) {
                    $errors[] = "Peso bruto negativo en {$lineRef}";
                } elseif ((float) $gross === 0.0) {
                    $this->warnings[] =
                        "{$lineRef}: peso bruto informado en 0; "
                        . "debe completarse manualmente.";
                }

                if ($tare === null) {
                    $errors[] = "Peso tara ausente en {$lineRef}";
                } elseif ($tare < 0) {
                    $errors[] = "Peso tara negativo en {$lineRef}";
                } elseif ((float) $tare === 0.0) {
                    $this->warnings[] =
                        "{$lineRef}: peso tara informado en 0; "
                        . "debe completarse manualmente.";
                }

                if ($net !== null) {
                    if ($net < 0) {
                        $errors[] = "Peso neto negativo en {$lineRef}";
                    } elseif ((float) $net === 0.0) {
                        $this->warnings[] =
                            "{$lineRef}: peso neto informado en 0; "
                            . "debe completarse manualmente.";
                    }

                    if ($gross !== null && $net > $gross) {
                        $errors[] =
                            "Peso neto no puede ser mayor al peso bruto "
                            . "en {$lineRef}";
                    }
                }

                if ($vgm !== null) {
                    if ($vgm < 0) {
                        $errors[] = "VGM negativo en {$lineRef}";
                    } elseif ((float) $vgm === 0.0) {
                        $this->warnings[] =
                            "{$lineRef}: VGM informado en 0; "
                            . "debe completarse manualmente.";
                    }
                }
            }
        }

        $this->warnings = array_values(
            array_unique($this->warnings)
        );

        return $errors;
    }

    /**
     * Transformar datos - SOPORTA MÚLTIPLES BillOfLading
     */
    public function transform(array $data): array
    {
        // Transformar cada BL con sus contenedores
        $billsOfLading = [];
        $allContainers = [];
        
        foreach ($data['bills_of_lading'] as $bl) {
            $blContainers = array_map(function($container) {
                return [
                    'line_number' => $container['line_number'],
                    'container_number' => $container['container_number'],
                    'container_type' => strtoupper(trim((string)$container['container_type'])),
                    'tare_weight_kg' => $container['tare_weight_kg'],
                    'net_weight_kg' => $container['net_weight_kg'],
                    'gross_weight_kg' => $container['gross_weight_kg'],
                    'vgm' => $container['vgm'],
                    'seals' => implode(', ', $container['seals']),
                    'commodity_code' => implode(', ', $container['ncm_codes']),
                    'package_description' => $this->getContainerDescription($container['container_type']),
                    // Login no informa país de origen por línea.
                    'country_of_origin' => null
                ];
            }, $bl['containers']);
            
            $billsOfLading[] = [
                'bill_number' => $bl['bill_number'],
                'shipper_name' => $bl['shipper_name'],
                'shipper_cuit' => $bl['shipper_cuit'] ?? null,
                'consignee_name' => $bl['consignee_name'],
                'notify_party_name' => $bl['notify_party_name'],
                'loading_port' => $bl['loading_port'] ?? null,
                'discharge_port' => $bl['discharge_port'] ?? null,
                'bill_date' => $this->parseDate($bl['bill_date'] ?? null),
                'loading_date' => $this->parseDate($bl['loading_date'] ?? null),
                'cargo_description' => $bl['cargo_description'],
                'cargo_marks' => $bl['cargo_marks'] ?? null,
                'total_containers' => count($bl['containers']),

                // Valores estructurados de la cabecera del BL.
                'total_weight_kg' => $this->parseOptionalWeight(
                    isset($bl['gross_weight'])
                        ? (string) $bl['gross_weight']
                        : null
                ),
                'volume_m3' => $this->parseOptionalWeight(
                    isset($bl['measurement'])
                        ? (string) $bl['measurement']
                        : null
                ),

                'containers' => $blContainers
            ];
            
            $allContainers = array_merge($allContainers, $blContainers);
        }
        
        return [
            'voyage' => [
                'voyage_number' => $data['header']['voyage_number'],
                'vessel_name' => $data['header']['vessel_name'],
                'origin_port' => $data['header']['loading_port'],
                'destination_port' => $data['header']['discharge_port'],
                'departure_date' => null,
                'estimated_arrival_date' => null
            ],
            'shipment' => [
                'shipment_number' => 'LGN-' . $data['header']['voyage_number'],
                'status' => 'planning'
            ],
            'bills_of_lading' => $billsOfLading,
            'containers' => $allContainers  // Para compatibilidad
        ];
    }

    /**
     * Mapear tipo de contenedor del XML al sistema
     */
    protected function mapContainerType(string $xmlType): string
    {
        return $this->containerTypeMapping[$xmlType] ?? $xmlType;
    }

    /**
     * Resuelve el ContainerType real a partir del código crudo del XML Login.
     * Flujo: normaliza (trim + mayúsculas) -> aplica alias específicos de Login
     * -> busca exacto por container_types.code. Devuelve null si no hay match
     * (NO cae a ContainerType::first()): el llamador decide el corte.
     */
    protected function resolveContainerType(?string $rawType): ?ContainerType
    {
        if ($rawType === null || trim($rawType) === '') {
            return null;
        }

        $code = strtoupper(trim($rawType));
        $code = $this->loginContainerAliases[$code] ?? $code;

        // Cache por code (incluye resultados null): array_key_exists, no isset,
        // porque isset() trata null como ausente y repetiría el query.
        if (!array_key_exists($code, $this->cachedContainerTypes)) {
            $this->cachedContainerTypes[$code] = ContainerType::where('code', $code)->first();
        }

        return $this->cachedContainerTypes[$code];
    }

    /**
     * Obtener descripción del contenedor
     */
    protected function getContainerDescription(string $type): string
    {
        $descriptions = [
            '40RH' => 'Contenedor refrigerado 40 pies high cube',
            '40HC' => 'Contenedor 40 pies high cube',
            '20DV' => 'Contenedor dry van 20 pies',
            '40DV' => 'Contenedor dry van 40 pies'
        ];

        return $descriptions[$type] ?? "Contenedor tipo {$type}";
    }

    /**
     * Parsear fecha desde string
     */
    protected function parseDate(?string $dateStr, string $modifier = null): string
    {
        if (empty($dateStr)) {
            $date = now();
        } else {
            try {
                $date = \Carbon\Carbon::parse($dateStr);
            } catch (Exception $e) {
                $date = now();
            }
        }

        if ($modifier) {
            $date = $date->modify($modifier);
        }

        return $date->format('Y-m-d H:i:s');
    }

    /**
     * Crear Voyage
     */
    protected function createVoyage(array $data, array $context): Voyage
    {
        // Obtener empresa del contexto
        $company = \App\Models\Company::findOrFail($context['company_id']);
        
        // Buscar puertos basándose en los datos del XML
        $originPort = $this->findPortByName($data['voyage']['origin_port']);
        $destinationPort = $this->findPortByName($data['voyage']['destination_port']);
        
        if (!$originPort) {
            throw new Exception("Puerto de origen '{$data['voyage']['origin_port']}' no encontrado en el sistema");
        }
        
        if (!$destinationPort) {
            throw new Exception("Puerto de destino '{$data['voyage']['destination_port']}' no encontrado en el sistema");
        }

        // Crear o encontrar embarcación líder
        $vesselName = $data['voyage']['vessel_name'];
        $leadVessel = $this->findOrCreateVessel($vesselName, $company->id);

        // El voyage_number es único global. Si ya existe (en cualquier empresa),
        // se bloquea la importación con un error claro en lugar de chocar el índice.
        $this->guardVoyageNumberIsFree($data['voyage']['voyage_number']);

        return Voyage::create([
            'voyage_number' => $data['voyage']['voyage_number'],
            'company_id' => $company->id,
            'lead_vessel_id' => $leadVessel->id,
            'origin_country_id' => $originPort->country_id,
            'destination_country_id' => $destinationPort->country_id,
            'origin_port_id' => $originPort->id,
            'destination_port_id' => $destinationPort->id,
            'departure_date' => $data['voyage']['departure_date'],
            'estimated_arrival_date' => $data['voyage']['estimated_arrival_date'],
            'voyage_type' => $this->determineVoyageType($data),
            'cargo_type' => $this->determineCargoType($data),
            'status' => 'planning',
            'is_convoy' => false,
            'vessel_count' => 1,
            // El archivo no informa capacidades técnicas del buque.
            'total_cargo_capacity_tons' => 0,
            'total_container_capacity' => 0,
            'active' => true,
            'created_date' => now(),
            'created_by_user_id' => $context['user_id']
        ]);
    }

    

    /**
     * Crear Shipment
     */
    protected function createShipment(array $data, Voyage $voyage, array $context): Shipment
    {
        return Shipment::create([
            'shipment_number' => $data['shipment']['shipment_number'],
            'voyage_id' => $voyage->id,
            'vessel_id' => $voyage->lead_vessel_id,
            'sequence_in_voyage' => 1,
            'vessel_role' => 'single',
            'is_lead_vessel' => true,
            // El archivo no informa capacidades técnicas del shipment.
            'cargo_capacity_tons' => 0,
            'container_capacity' => 0,
            'status' => $data['shipment']['status'],
            'active' => true,
            'created_date' => now(),
            'created_by_user_id' => $context['user_id']
        ]);
    }

    /**
     * Dirección del cliente en Login: viene mezclada dentro del nodo de la parte
     * (línea 1 = nombre, líneas siguientes = dirección, última = CUIT/CNPJ).
     * Devuelve todo menos la primera línea; el trait limpia el resto con cleanFileAddress().
     */
    protected function extractAddressFromNode(?string $node): ?string
    {
        if ($node === null || trim($node) === '') {
            return null;
        }
        $lines = preg_split('/\r\n|\r|\n/', $node);
        if (count($lines) < 2) {
            return null; // solo el nombre, sin dirección
        }
        array_shift($lines); // descartar la primera línea (el nombre)
        $rest = trim(implode(' ', $lines));
        return $rest === '' ? null : $rest;
    }

    /**
     * Crear Bill of Lading desde datos transformados de un BL individual
     */
    protected function createBillOfLadingFromData(array $blData, Shipment $shipment, array $context): BillOfLading
    {
        // Crear o encontrar clientes con datos reales del XML
        $shipper = $this->findOrCreateClient(
            $blData['shipper_name'], 
            'shipper',
            $context,
            $blData['shipper_cuit'] ?? null
        );
        
        $consignee = $this->findOrCreateClient(
            $blData['consignee_name'], 
            'consignee',
            $context,
            $this->extractTaxIdFromText($blData['consignee_name'] ?? '')
        );
        
        $notifyParty = null;
        if (!empty($blData['notify_party_name'])) {
            $notifyParty = $this->findOrCreateClient(
                $blData['notify_party_name'], 
                'notify_party',
                $context,
                $this->extractTaxIdFromText($blData['notify_party_name'])
            );
        }

        // Resolver semánticamente; nunca depender del ID del catálogo.
        $cargoType = CargoType::where('name', 'CONTENEDORES')
            ->where('active', true)
            ->first();

        $packagingType = PackagingType::where('code', 'T')
            ->where('active', true)
            ->first();

        if (!$cargoType || !$packagingType) {
            throw new Exception(
                'Catálogo CONTENEDORES/CONTENEDOR no disponible'
            );
        }

        // Puerto propio del BL; si no viene o no está en catálogo, cae al del voyage
        $blLoadingPort = !empty($blData['loading_port'])
            ? $this->findPortByName($blData['loading_port'])
            : null;
        $blDischargePort = !empty($blData['discharge_port'])
            ? $this->findPortByName($blData['discharge_port'])
            : null;

        if (!empty($blData['loading_port']) && !$blLoadingPort) {
            throw new Exception(
                "Puerto de carga '{$blData['loading_port']}' del BL "
                . "{$blData['bill_number']} no encontrado"
            );
        }

        if (!empty($blData['discharge_port']) && !$blDischargePort) {
            throw new Exception(
                "Puerto de descarga '{$blData['discharge_port']}' del BL "
                . "{$blData['bill_number']} no encontrado"
            );
        }

        $bill = BillOfLading::create([
            'shipment_id' => $shipment->id,
            'bill_number' => $blData['bill_number'],
            'shipper_id' => $shipper?->id,
            'consignee_id' => $consignee?->id,
            'notify_party_id' => $notifyParty?->id,
            'loading_port_id' => $blLoadingPort?->id ?? $shipment->voyage->origin_port_id,
            'discharge_port_id' => $blDischargePort?->id ?? $shipment->voyage->destination_port_id,
            'primary_cargo_type_id' => $cargoType->id,
            'primary_packaging_type_id' => $packagingType->id,
            'bill_date' => $blData['bill_date'] ?? now(),
            'loading_date' => $blData['loading_date'] ?? now(),
            'cargo_description' => $blData['cargo_description'],
            'cargo_marks' => $blData['cargo_marks'] ?? null,

            // Login no informa cantidad de bultos: no confundirla con contenedores.
            // La columna mantiene su default interno de esquema.
            'gross_weight_kg' => $blData['total_weight_kg'],
            'volume_m3' => $blData['volume_m3'],
            'container_count' => $blData['total_containers'],
            'status' => 'draft',
            'is_consolidated' => false,
            'created_by_user_id' => $context['user_id']
        ]);

        // Dirección del cliente: Login la trae mezclada en el nodo de cada parte.
        // Etapa 1: persistir en la ficha si el cliente no tiene dirección.
        // Etapa 2: si difiere de la registrada, guardarla como dirección específica del BL.
        foreach ([
            ['client' => $shipper,     'addr' => $this->extractAddressFromNode($blData['shipper_name'] ?? null),      'role' => 'shipper'],
            ['client' => $consignee,   'addr' => $this->extractAddressFromNode($blData['consignee_name'] ?? null),    'role' => 'consignee'],
            ['client' => $notifyParty, 'addr' => $this->extractAddressFromNode($blData['notify_party_name'] ?? null), 'role' => 'notify_party'],
        ] as $p) {
            $this->persistClientAddress($p['client'], $p['addr']);
            if ($c = $this->resolveSpecificAddress($p['client'], $p['addr'], $p['role'])) {
                $bill->specificContacts()->create($c);
            }
        }

        return $bill;
    }

    /**
     * Crear ShipmentItems desde array de contenedores transformados
     */
    protected function createShipmentItemsFromData(array $containers, BillOfLading $billOfLading, array $context): array
    {
        $itemIds = [];

        // Catálogos resueltos semánticamente una sola vez por importación.
        if ($this->cachedCargoType === null) {
            $this->cachedCargoType = CargoType::where('name', 'CONTENEDORES')
                ->where('active', true)
                ->first();
        }

        if ($this->cachedPackagingType === null) {
            $this->cachedPackagingType = PackagingType::where('code', 'T')
                ->where('active', true)
                ->first();
        }

        if (!$this->cachedCargoType || !$this->cachedPackagingType) {
            throw new Exception(
                'Catálogo CONTENEDORES/CONTENEDOR no disponible'
            );
        }
        $cargoType = $this->cachedCargoType;
        $packagingType = $this->cachedPackagingType;

        foreach ($containers as $index => $containerData) {
            $containerNumber = $containerData['container_number'];
            
            // Crear ShipmentItem
            $item = ShipmentItem::create([
                'bill_of_lading_id' => $billOfLading->id,
                // Conservar numeración real del XML (Login usa base 0).
                'line_number' => $containerData['line_number'],
                'item_reference' => 'LGN-' . $containerNumber,
                'item_description' => $containerData['package_description'],
                'cargo_type_id' => $cargoType?->id,
                'packaging_type_id' => $packagingType?->id,
                'package_quantity' => 1,
                'unit_of_measure' => 'KG',
                'gross_weight_kg' => $containerData['gross_weight_kg'],
                'net_weight_kg' => $containerData['net_weight_kg'],
                'country_of_origin' => $containerData['country_of_origin'],
                'commodity_code' => $containerData['commodity_code'],
                'tariff_position' => $containerData['commodity_code'] ?: null,
                'cargo_marks' => $containerData['seals']
                    ? "Seals: {$containerData['seals']}"
                    : null,
                'package_type_description' => $containerData['package_description'],
                'created_date' => now(),
                'created_by_user_id' => $context['user_id']
            ]);

            // Crear Container asociado (evitar duplicados en misma importación)
            if (isset($this->createdContainersInImport[$containerNumber])) {
                $container = $this->createdContainersInImport[$containerNumber];
            } else {
                $containerType = $this->resolveContainerType($containerData['container_type']);
                if ($containerType === null) {
                    throw new Exception(
                        'Tipo de contenedor no resuelto: "'
                        . strtoupper(trim((string)$containerData['container_type']))
                        . '" en el contenedor ' . $containerNumber
                        . '. La importación se detuvo para no persistir un contenedor sin tipo válido.'
                    );
                }

                $container = Container::firstOrCreate(
                    ['container_number' => $containerNumber],
                    [
                        'container_type_id' => $containerType->id,
                        'tare_weight_kg' => $containerData['tare_weight_kg'],

                        // Login no informa máximo técnico ni peso bruto actual.
                        'max_gross_weight_kg' => null,
                        'current_gross_weight_kg' => null,

                        'cargo_weight_kg' => $containerData['net_weight_kg'],
                        'condition' => 'L',
                        'operational_status' => 'loaded',
                        'shipper_seal' => $containerData['seals'],
                        'active' => true,
                        'created_date' => now(),
                        'created_by_user_id' => $context['user_id']
                    ]
                );
                $this->createdContainersInImport[$containerNumber] = $container;
            }

            // Asociar item con contenedor
            $container->shipmentItems()->attach($item->id, [
                'package_quantity' => 1,
                'gross_weight_kg' => $containerData['gross_weight_kg'],
                'net_weight_kg' => $containerData['net_weight_kg'],
                'verified_gross_mass_kg' => $containerData['vgm'],
                'status' => 'loaded',
                'created_date' => now(),
                'created_by_user_id' => $context['user_id']
            ]);

            $itemIds[] = $item->id;
            // Liberar objetos Eloquent de la iteracion
            unset($item, $container);
        }

        return $itemIds;
    }


    /**
     * Encontrar o crear cliente con datos reales
     */
    protected function findOrCreateClient(
        ?string $name,
        string $type,
        array $context,
        ?string $taxId = null
    ): ?Client {
        if ($name === null || trim($name) === '') {
            return null;
        }

        $cleanName = $this->cleanClientName($name);

        /*
         * Login puede traer un identificador estructurado defectuoso
         * y el CUIT/CNPJ correcto dentro del texto de la parte.
         *
         * El estructurado sólo se acepta si su longitud es compatible
         * con el país explícito en el propio XML.
         */
        $structuredTaxId = $taxId;

        $structuredDigits = $structuredTaxId
            ? preg_replace('/\D/', '', $structuredTaxId)
            : null;

        $explicitCountry = null;

        if (preg_match('/\bARGENTINA\b/iu', $name)) {
            $explicitCountry = 'AR';
        } elseif (preg_match('/\bBRASIL\b|\bBRAZIL\b/iu', $name)) {
            $explicitCountry = 'BR';
        }

        if (
            $explicitCountry === 'AR'
            && $structuredDigits !== null
            && strlen($structuredDigits) !== 11
        ) {
            $structuredTaxId = null;
        }

        if (
            $explicitCountry === 'BR'
            && $structuredDigits !== null
            && strlen($structuredDigits) !== 14
        ) {
            $structuredTaxId = null;
        }

        /*
         * Si descartamos el estructurado inválido, resolveTaxId()
         * continúa con el identificador explícito embebido en el texto.
         *
         * PROJECT CARGO:
         * estructurado: 307110915       -> descartado
         * texto:        30-71109158-7   -> 30711091587
         */
        $taxId = $this->resolveTaxId($structuredTaxId, $name);

        $cleanTaxId = $taxId
            ? preg_replace('/\D/', '', $taxId)
            : null;

        $countryCode = null;

        if (preg_match('/\bARGENTINA\b/iu', $name)) {
            $countryCode = 'AR';
        } elseif (preg_match('/\bBRASIL\b|\bBRAZIL\b/iu', $name)) {
            $countryCode = 'BR';
        } elseif ($cleanTaxId && strlen($cleanTaxId) === 11) {
            $countryCode = 'AR';
        } elseif ($cleanTaxId && strlen($cleanTaxId) === 14) {
            $countryCode = 'BR';
        }

        if ($countryCode === null) {
            throw new Exception(
                "Login no permite determinar el país real del cliente {$cleanName}"
            );
        }

        $documentCode = null;

        if ($cleanTaxId !== null) {
            if ($countryCode === 'AR') {
                if (strlen($cleanTaxId) !== 11) {
                    throw new Exception(
                        "Identificación fiscal incompatible con Argentina para {$cleanName}"
                    );
                }

                $documentCode = 'CUIT';
            }

            if ($countryCode === 'BR') {
                if (strlen($cleanTaxId) !== 14) {
                    throw new Exception(
                        "Identificación fiscal incompatible con Brasil para {$cleanName}"
                    );
                }

                $documentCode = 'CNPJ';
            }
        }

        $country = Country::where('alpha2_code', $countryCode)->first();

        if (!$country) {
            throw new Exception(
                "País {$countryCode} no encontrado en catálogo"
            );
        }

        $documentType = null;

        if ($documentCode !== null) {
            $documentType = \App\Models\DocumentType::where(
                    'code',
                    $documentCode
                )
                ->where('country_id', $country->id)
                ->where('active', true)
                ->first();

            if (!$documentType) {
                throw new Exception(
                    "Tipo documental {$documentCode} no encontrado para {$countryCode}"
                );
            }
        }

        /*
         * Si existe identificación fiscal real:
         * identidad = tax_id + país.
         *
         * NO caer a coincidencia por nombre si ese tax no existe.
         */
        if ($cleanTaxId !== null) {
            $client = Client::where('tax_id', $cleanTaxId)
                ->where('country_id', $country->id)
                ->first();

            if ($client) {
                if (
                    $documentType
                    && $client->document_type_id !== null
                    && (int) $client->document_type_id !== (int) $documentType->id
                ) {
                    throw new Exception(
                        "Cliente {$cleanName} tiene tipo documental incompatible"
                    );
                }

                if (
                    $documentType
                    && $client->document_type_id === null
                ) {
                    $client->updateQuietly([
                        'document_type_id' => $documentType->id,
                    ]);
                }

                return $client;
            }
        } else {
            // Sin tax real: nombre exacto + país.
            $client = Client::where('legal_name', $cleanName)
                ->where('country_id', $country->id)
                ->first();

            if ($client) {
                return $client;
            }
        }

        return Client::create([
            'tax_id' => $cleanTaxId,
            'country_id' => $country->id,
            'document_type_id' => $documentType?->id,
            'legal_name' => $cleanName,
            'status' => 'active',
            'created_by_company_id' => $context['company_id'],
            'verified_at' => null,
            'created_by_user_id' => $context['user_id'],
        ]);
    }

    /**
     * Limpiar nombre del cliente
     */
    protected function cleanClientName(string $name): string
    {
        // Dividir por saltos de línea y tomar solo la primera línea (nombre principal)
        $lines = explode("\n", $name);
        $mainName = trim($lines[0]);
        
        // Remover información extra común
        $mainName = preg_replace('/\s*CUIT:\s*[0-9-]+/i', '', $mainName);
        $mainName = preg_replace('/\s*CNPJ:\s*[0-9\/-]+/i', '', $mainName);
        
        return trim($mainName);
    }

    /**
     * Encontrar o crear embarcación con datos del XML
     */
    protected function findOrCreateVessel(string $vesselName, int $companyId): Vessel
    {
        $vesselName = trim($vesselName);

        if ($vesselName === '') {
            throw new Exception('Login no informa nombre de buque');
        }

        $vessel = Vessel::where('name', $vesselName)
            ->where('company_id', $companyId)
            ->first();

        if ($vessel) {
            return $vessel;
        }

        return Vessel::create([
            'name' => $vesselName,
            'company_id' => $companyId,

            // Login no informa estos datos.
            'registration_number' => null,
            'vessel_type_id' => null,
            'flag_country_id' => null,
            'length_meters' => null,
            'beam_meters' => null,
            'draft_meters' => null,
            'gross_tonnage' => null,
            'net_tonnage' => null,
            'cargo_capacity_tons' => null,

            'operational_status' => 'active',
            'active' => true,
        ]);
    }

    /**
     * Generar email para cliente
     */
    protected function generateClientEmail(string $name, string $type): string
    {
        $slug = strtolower(str_replace([' ', '.', ','], '', $name));
        $slug = substr($slug, 0, 20);
        return $slug . '@' . $type . '.login.xml';
    }

    /**
     * Obtener información del formato
     */
    public function getFormatInfo(): array
    {
        return [
            'name' => 'Login XML Parser',
            'description' => 'Parser para manifiestos Login en formato XML con estructura anidada',
            'extensions' => ['xml'],
            'version' => '1.0',
            'features' => [
                'Múltiples contenedores por B/L',
                'Tipos de contenedor: 40RH, 40HC, 20DV, etc.',
                'Sellos múltiples por contenedor',
                'Códigos NCM por línea',
                'VGM (Verified Gross Mass) opcional',
                'Header completo con shipper/consignee'
            ]
        ];
    }

    /**
     * Obtener configuración por defecto
     */
    public function getDefaultConfig(): array
    {
        return [
            'encoding' => 'UTF-8',
            'validate_xml' => true,
            'create_missing_clients' => true,
            'default_currency' => null,
            'default_freight_terms' => null,
            'default_country' => null
        ];
    }

    /**
     * Buscar puerto por nombre (método auxiliar)
     */
    protected function findPortByName(string $portName): ?Port
    {
        if (empty($portName) || $portName === 'Unknown') {
            return null;
        }
        
        // Buscar por código UN/LOCODE primero
        $port = Port::where('code', strtoupper($portName))->first();
        if ($port) return $port;
        
        // Buscar por nombre exacto (prioridad sobre LIKE para evitar
        // matches erróneos: SALVADOR->San Salvador, VITORIA->Praia Mole/Vitória)
        $port = Port::where('name', $portName)->first();
        if ($port) return $port;
        
        // Buscar por nombre parcial solo si no hubo coincidencia exacta
        $port = Port::where('name', 'LIKE', "%{$portName}%")->first();
        if ($port) return $port;
        
        // Mapeos específicos para Login XML
        $mappings = [
            'BUENOS AIRES' => 'ARBUE',
            'ASUNCION' => 'PYASU', 
            'ROSARIO' => 'ARROS',
            'SANTA FE' => 'ARSFE',
            'VILLETA' => 'PYVIL'
        ];
        
        $portCode = $mappings[strtoupper($portName)] ?? null;
        if ($portCode) {
            return Port::where('code', $portCode)->first();
        }
        
        return null;
    }

    /**
     * Determinar voyage_type basándose en datos del XML
     */
    protected function determineVoyageType(array $data): string
    {
        // Para Login XML típicamente es embarcación única
        $vesselName = $data['voyage']['vessel_name'] ?? '';
        
        // Detectar convoy por nombres comunes
        if (stripos($vesselName, 'convoy') !== false || 
            stripos($vesselName, 'remolcador') !== false ||
            stripos($vesselName, 'barcaza') !== false) {
            return 'convoy';
        }
        
        // Detectar flota por múltiples contenedores o gran capacidad
        $totalContainers = count($data['containers'] ?? []);
        if ($totalContainers > 50) {
            return 'fleet';
        }
        
        return 'single_vessel'; // Default más común para Login
    }

    /**
     * Determinar tipo de operación según los países reales de los puertos.
     *
     * Argentina es la referencia operativa de la aplicación:
     * AR -> exterior = export
     * exterior -> AR = import
     * mismo país = cabotage
     * exterior -> exterior = transit
     */
    protected function determineCargoType(array $data): string
    {
        $originPort = $this->findPortByName(
            $data['voyage']['origin_port']
        );

        $destinationPort = $this->findPortByName(
            $data['voyage']['destination_port']
        );

        if (!$originPort || !$destinationPort) {
            throw new Exception(
                'No se puede determinar la operación: puerto no resuelto'
            );
        }

        $originCountry = Country::find($originPort->country_id);
        $destinationCountry = Country::find(
            $destinationPort->country_id
        );

        $originCode = strtoupper(
            trim((string) ($originCountry?->alpha2_code ?? ''))
        );

        $destinationCode = strtoupper(
            trim((string) ($destinationCountry?->alpha2_code ?? ''))
        );

        if ($originCode === '' || $destinationCode === '') {
            throw new Exception(
                'No se puede determinar la operación: país de puerto no resuelto'
            );
        }

        if ($originCode === $destinationCode) {
            return 'cabotage';
        }

        if ($originCode === 'AR') {
            return 'export';
        }

        if ($destinationCode === 'AR') {
            return 'import';
        }

        return 'transit';
    }

    /**
     * Crear registro de importación
     */
    protected function createImportRecord(string $filePath, array $context): ManifestImport
    {
        $fileName = basename($filePath);
        $fileSize = filesize($filePath);
        $fileHash = ManifestImport::generateFileHash($filePath);
        
        return ManifestImport::createForImport([
            'company_id' => $context['company_id'],
            'user_id' => $context['user_id'],
            'file_name' => $fileName,
            'file_format' => 'login_xml',
            'file_size_bytes' => $fileSize,
            'file_hash' => $fileHash,
            'parser_config' => [
                'parser_class' => self::class,
                'format' => 'Login XML'
            ]
        ]);
    }

    /**
     * Completar registro de importación.
     */
    protected function completeImportRecord(
        ManifestImport $importRecord,
        Voyage $voyage,
        array $shipmentIds,
        array $billIds,
        array $itemIds,
        float $startTime,
        array $warnings = []
    ): void {
        $processingTime = microtime(true) - $startTime;

        $createdObjects = [
            'voyages' => [$voyage->id],
            'shipments' => $shipmentIds,
            'bills' => $billIds,
            'items' => $itemIds,
        ];

        $importRecord->recordCreatedObjects($createdObjects);

        $completionData = [
            'voyage_id' => $voyage->id,
            'processing_time_seconds' => round($processingTime, 2),
            'warnings' => $warnings,
            'warnings_count' => count($warnings),
            'notes' => $warnings === []
                ? 'Importación Login XML completada exitosamente'
                : 'Importación Login XML completada con advertencias',
        ];

        if ($warnings === []) {
            $importRecord->markAsCompleted($completionData);
        } else {
            $importRecord->markAsCompletedWithWarnings(
                $completionData
            );
        }

        Log::info('Login XML import record completed', [
            'import_id' => $importRecord->id,
            'processing_time' => round($processingTime, 2) . 's',
            'warnings_count' => count($warnings),
        ]);
    }

}