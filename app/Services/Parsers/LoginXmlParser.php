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

    /**
     * Tipo fiscal explícitamente demostrado dentro del Login.xml actual,
     * indexado por tax_id normalizado.
     *
     * Permite que una aparición genérica "Tax ID" reutilice la semántica
     * demostrada por otra aparición del MISMO número como CUIT/CNPJ/etc.
     */
    protected array $loginTaxTypeById = [];

    // Mapeo de países por código NCM/origen
    protected array $countryMapping = [
        'default' => 'ARG', // Argentina por defecto para Login
        'argentina' => 'ARG',
        'paraguay' => 'PRY',
        'brasil' => 'BRA',
        'uruguay' => 'URY'
    ];

    // Contenedores creados durante la importación actual (evita duplicados)
    protected array $createdContainersInImport = [];

    // Cache de catálogos para evitar queries repetidas en el loop de BLs/contenedores.
    protected ?CargoType $cachedCargoType = null;
    protected ?PackagingType $cachedPackagingType = null;
    protected array $cachedContainerTypes = [];

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
                
                $itemIds = $this->createShipmentItemsFromData($blData['containers'], $billOfLading, $context);
                foreach ($itemIds as $iid) {
                    $allItemIds[] = $iid;
                }
                
                // Liberar el objeto BL de memoria tras usar su id
                unset($billOfLading);
            }
            
            $createdContainerIds = collect($this->createdContainersInImport)
                ->filter(fn ($container) => $container->wasRecentlyCreated)
                ->pluck('id')
                ->values()
                ->all();

            $this->completeImportRecord(
                $importRecord,
                $voyage,
                [$shipment->id],
                $allBillIds,
                $allItemIds,
                $createdContainerIds,
                $startTime
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
                statistics: [
                    'format' => 'Login XML',
                    'bills_of_lading' => count($allBillIds),
                    'containers' => count($transformedData['containers']),
                    'total_weight_kg' => array_sum(array_column($transformedData['containers'], 'gross_weight_kg')),
                    'shipper' => 'Multiple shippers',
                    'consignee' => 'Multiple consignees'
                ]
            );

        } catch (Exception $e) {
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

        // Antes de resolver clientes, reunir la semántica fiscal explícita
        // disponible en todo el manifiesto.
        $this->buildLoginTaxTypeIndex($xml);

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
            
            // Extraer datos del header de este BL
            $oceanFreight = $this->extractOceanFreight($header);

            $blData = [
                'bill_number' => (string)$header->BillOfLadingNumber ?? null,
                'shipper_name' => (string)$header->ShipperExporter ?? null,
                'shipper_cuit' => (string)$header->ShipperExporterCUIT ?? null,
                'consignee_name' => (string)$header->Consignee ?? null,
                'notify_party_name' => (string)$header->NotifyParty ?? null,
                'booking_number' => (string)$header->BookingNumber ?? null,
                'vessel_name' => (string)$header->InitialVesselVoyFlag ?? null,
                'loading_port' => (string)$header->InitalPortOfLoading ?? (string)$header->FinalPortOfLoading ?? null,
                'discharge_port' => (string)$header->PortOfDischarge ?? null,
                'gross_weight' => (string)$header->GrossWeight ?? null,
                'measurement' => (string)$header->Measurement ?? null,
                'cargo_description' => (string)$header->DescriptionOfPackagesAndGoods ?? null,
                'freight_terms' => $oceanFreight['terms'],
                'freight_amount' => $oceanFreight['amount'],
                'freight_currency' => $oceanFreight['currency'],
                'loading_date' => null,
                'bill_date' => null,
                'voyage_number' => (string)$header->BookingNumber ?? null,
                'containers' => []  // Containers de este BL específico
            ];
            
            // Extraer contenedores del BL y fusionar líneas repetidas del
            // mismo contenedor. Login puede emitir una segunda línea sólo para
            // completar VGM/sellos/NCM.
            $containersByNumber = [];

            if (isset($billOfLading->BillOfLadingLineDetail->BillOfLadingLine)) {
                foreach ($billOfLading->BillOfLadingLineDetail->BillOfLadingLine as $line) {
                    $container = [
                        'line_number' => (int) $line->BillOfLadingLineNumber,
                        'container_number' => trim((string) $line->Container),
                        'container_type' => trim((string) $line->Type),
                        'tare_weight_kg' => $this->parseWeight((string) $line->Tare),
                        'net_weight_kg' => $this->parseWeight((string) $line->NetWeight),
                        'gross_weight_kg' => $this->parseWeight((string) $line->GrossWeight),
                        'vgm' => isset($line->Vgm)
                            ? $this->parseWeight((string) $line->Vgm)
                            : null,
                        'seals' => [],
                        'ncm_codes' => [],
                    ];

                    foreach ($line->Seal->Nseal ?? [] as $seal) {
                        $container['seals'][] = trim((string) $seal);
                    }

                    foreach ($line->Ncm->Nncm ?? [] as $ncm) {
                        $container['ncm_codes'][] = trim((string) $ncm);
                    }

                    $number = $container['container_number'];

                    if ($number === '') {
                        throw new Exception('Login XML: línea sin número de contenedor.');
                    }

                    if (isset($containersByNumber[$number])) {
                        $existing = $containersByNumber[$number];

                        foreach ([
                            'container_type',
                            'tare_weight_kg',
                            'net_weight_kg',
                            'gross_weight_kg',
                        ] as $field) {
                            if ($existing[$field] !== $container[$field]) {
                                throw new Exception(
                                    "Login XML: líneas contradictorias para contenedor {$number}."
                                );
                            }
                        }

                        if (
                            $existing['vgm'] !== null
                            && $container['vgm'] !== null
                            && $existing['vgm'] !== $container['vgm']
                        ) {
                            throw new Exception(
                                "Login XML: VGM contradictorio para contenedor {$number}."
                            );
                        }

                        $existing['vgm'] ??= $container['vgm'];
                        $existing['seals'] = array_values(array_unique(
                            array_merge($existing['seals'], $container['seals'])
                        ));
                        $existing['ncm_codes'] = array_values(array_unique(
                            array_merge($existing['ncm_codes'], $container['ncm_codes'])
                        ));

                        $containersByNumber[$number] = $existing;
                        continue;
                    }

                    $containersByNumber[$number] = $container;
                }
            }

            $blData['containers'] = array_values($containersByNumber);

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

    /**
     * Parsear peso desde string (puede tener comas como separador decimal)
     */
    protected function extractOceanFreight(SimpleXMLElement $header): array
    {
        foreach ($header->Fees->Fee ?? [] as $fee) {
            if (strcasecmp(trim((string) $fee->Description), 'Ocean Freight') !== 0) {
                continue;
            }

            $terms = strtolower(trim((string) $fee->Payment));

            if (!in_array($terms, ['prepaid', 'collect'], true)) {
                throw new Exception("Login XML: modalidad Ocean Freight '{$terms}' no soportada.");
            }

            $currency = strtoupper(trim((string) $fee->Currency));

            if ($currency === '') {
                throw new Exception('Login XML: Ocean Freight sin moneda.');
            }

            return [
                'terms' => $terms,
                'amount' => $this->parseWeight((string) $fee->Amount),
                'currency' => $currency,
            ];
        }

        return ['terms' => null, 'amount' => null, 'currency' => null];
    }

    protected function parseWeight(string $weightStr): float
    {
        if (empty($weightStr)) {
            return 0.0;
        }

        // Reemplazar coma por punto para decimales
        $normalized = str_replace(',', '.', $weightStr);
        
        // Remover cualquier carácter no numérico excepto punto y signo negativo
        $cleaned = preg_replace('/[^0-9.-]/', '', $normalized);
        
        return (float)$cleaned;
    }

    /**
     * Verificar que la longitud del identificador sea compatible con
     * el tipo fiscal explícitamente declarado.
     */
    protected function isTaxIdCompatibleWithType(
        ?string $taxId,
        ?string $taxType
    ): bool {
        if (!$taxId || !$taxType) {
            return false;
        }

        $length = strlen(preg_replace('/\D/', '', $taxId));

        return match (strtoupper($taxType)) {
            'CUIT' => $length === 11,
            'CNPJ' => $length === 14,
            'RUC' => $length >= 7 && $length <= 9,
            'NIT' => $length >= 9 && $length <= 10,
            default => false,
        };
    }

    /**
     * Construir evidencia fiscal del manifiesto completo.
     *
     * Sólo indexa marcadores explícitos: CUIT/CNPJ/RUC/NIT.
     * TAX ID genérico no crea semántica por sí mismo.
     */
    protected function buildLoginTaxTypeIndex(\SimpleXMLElement $xml): void
    {
        $this->loginTaxTypeById = [];

        $headers = $xml->xpath(
            '//*[local-name()="BillOfLadingHeader"]'
        ) ?: [];

        foreach ($headers as $header) {
            // Campo estructurado cuyo significado es inequívocamente CUIT.
            $structured = $this->resolveTaxId(
                trim((string) ($header->ShipperExporterCUIT ?? '')),
                null,
                null
            );

            if ($this->isTaxIdCompatibleWithType($structured, 'CUIT')) {
                $this->rememberLoginTaxType($structured, 'CUIT');
            }

            foreach ([
                'ShipperExporter',
                'Consignee',
                'NotifyParty',
            ] as $field) {
                $text = trim((string) ($header->{$field} ?? ''));

                if ($text === '') {
                    continue;
                }

                $identity = $this->extractTaxIdentityFromText($text);

                if (
                    $identity !== null
                    && $identity['tax_id'] !== null
                    && $identity['tax_type'] !== null
                ) {
                    $this->rememberLoginTaxType(
                        $identity['tax_id'],
                        $identity['tax_type']
                    );
                }
            }
        }
    }

    protected function rememberLoginTaxType(
        string $taxId,
        string $taxType
    ): void {
        $existing = $this->loginTaxTypeById[$taxId] ?? null;

        if ($existing !== null && $existing !== $taxType) {
            throw new \DomainException(
                "Login XML declara el identificador {$taxId} como " .
                "{$existing} y {$taxType} dentro del mismo manifiesto."
            );
        }

        $this->loginTaxTypeById[$taxId] = $taxType;
    }

    /**
     * Extraer identificador fiscal conservando el tipo declarado por Login.
     *
     * El archivo real también contiene:
     * - CNPJ sin ":" ni espacio;
     * - "CPNJ" como typo del emisor;
     * - TAX ID genérico, que aporta número pero NO tipo documental.
     */
    protected function extractTaxIdentityFromText(?string $text): ?array
    {
        $text = trim((string) $text);

        if ($text === '') {
            return null;
        }

        // "C/O ..." introduce otra persona/entidad dentro del domicilio.
        // Su CUIT no identifica a la parte principal del BL.
        $primaryText = preg_split(
            '/\bC\/O\b|\bCARE\s+OF\b/iu',
            $text,
            2
        )[0] ?? $text;

        $typedPatterns = [
            'CUIT' => '/\bCUIT(?:\s+NBR)?\s*:?\s*([0-9][0-9.\-\/]*)/iu',
            // CPNJ está verificado en Login.xml y se interpreta como CNPJ.
            'CNPJ' => '/\b(?:CNPJ|CPNJ)\s*:?\s*([0-9][0-9.\-\/]*)/iu',
            'RUC' => '/\bR\.?U\.?C\.?\s*:?\s*([0-9][0-9.\-\/]*)/iu',
            'NIT' => '/\bNIT\s*:?\s*([0-9][0-9.\-\/]*)/iu',
        ];

        foreach ($typedPatterns as $taxType => $pattern) {
            if (!preg_match($pattern, $primaryText, $matches)) {
                continue;
            }

            $taxId = $this->resolveTaxId(
                $matches[1],
                null,
                null
            );

            if ($taxId !== null) {
                return [
                    'tax_id' => $taxId,
                    'tax_type' => $taxType,
                ];
            }
        }

        if (preg_match(
            '/\bTAX\s*ID\s*:?\s*([0-9][0-9.\-\/]*)/iu',
            $primaryText,
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

    /**
     * Resolver número + tipo sin inventar semántica fiscal.
     *
     * El campo estructurado tiene prioridad. Si el mismo documento también
     * aparece en el texto y contradice al campo estructurado, se aborta.
     */
    protected function resolveClientTaxIdentity(
        ?string $structuredTaxId,
        ?string $text,
        ?string $declaredTaxType = null
    ): array {
        $declaredTaxType = strtoupper(
            trim((string) $declaredTaxType)
        );

        $declaredTaxType = $declaredTaxType !== ''
            ? $declaredTaxType
            : null;

        $structured = $this->resolveTaxId(
            $structuredTaxId,
            null,
            null
        );

        // Un campo llamado CUIT no se acepta sólo porque contiene dígitos:
        // debe ser compatible con la estructura del tipo declarado.
        // Login.xml contiene al menos un CUIT estructurado truncado.
        if (
            $structured !== null
            && $declaredTaxType !== null
            && !$this->isTaxIdCompatibleWithType(
                $structured,
                $declaredTaxType
            )
        ) {
            // El campo estructurado puede venir truncado. No se acepta sólo
            // porque contiene dígitos; se descarta y se conserva la evidencia
            // fiscal válida de la parte principal.
            $structured = null;
        }

        // Toda resolución textual se limita a la parte principal.
        // Un identificador que aparezca únicamente dentro de C/O / CARE OF
        // pertenece a otra persona o entidad y no identifica al cliente.
        $primaryText = preg_split(
            '/\\bC\\/O\\b|\\bCARE\\s+OF\\b/iu',
            (string) $text,
            2
        )[0] ?? (string) $text;

        $embedded = $this->extractTaxIdentityFromText($primaryText);

        if (
            $structured !== null
            && $embedded !== null
            && $embedded['tax_id'] !== $structured
        ) {
            throw new \DomainException(
                'Login XML informa dos identificadores fiscales válidos ' .
                'y distintos para la misma parte.'
            );
        }

        $taxId = $structured
            ?? ($embedded['tax_id'] ?? null)
            ?? $this->resolveTaxId(null, $primaryText, null);

        // El tipo declarado pertenece al campo estructurado. Si ese campo
        // estaba vacío o fue descartado por inválido, no debe imponer CUIT
        // sobre la evidencia textual válida.
        $taxType = $structured !== null
            ? $declaredTaxType
            : null;

        if (
            $taxType === null
            && $embedded !== null
            && $embedded['tax_id'] === $taxId
        ) {
            $taxType = $embedded['tax_type'];
        }

        // Una aparición genérica TAX ID puede adquirir tipo únicamente
        // si el MISMO número aparece explícitamente tipificado en otro
        // BL del mismo Login.xml.
        if (
            $taxId !== null
            && $taxType === null
            && isset($this->loginTaxTypeById[$taxId])
        ) {
            $taxType = $this->loginTaxTypeById[$taxId];
        }

        if (
            $taxId !== null
            && $taxType !== null
            && !$this->isTaxIdCompatibleWithType($taxId, $taxType)
        ) {
            throw new \DomainException(
                "El identificador {$taxId} no es compatible con {$taxType}."
            );
        }

        if (
            $taxType !== null
            && $embedded !== null
            && $embedded['tax_type'] !== null
            && $embedded['tax_id'] === $taxId
            && $embedded['tax_type'] !== $taxType
        ) {
            throw new \DomainException(
                'Login XML informa tipos fiscales contradictorios ' .
                'para la misma parte.'
            );
        }

        if ($taxId === null) {
            $taxType = null;
        }

        return [
            'tax_id' => $taxId,
            'tax_type' => $taxType,
        ];
    }

    /**
     * Resolver país sólo desde evidencia del documento.
     *
     * CUIT/CNPJ/RUC/NIT tienen país semántico propio. Si no hay tipo fiscal,
     * se inspecciona únicamente el domicilio (no el nombre de la empresa)
     * para evitar confundir "BRASIL" dentro de una razón social con país.
     */
    protected function inferClientCountryAlpha2(
        ?string $taxType,
        ?string $text
    ): ?string {
        $countryFromType = match (strtoupper((string) $taxType)) {
            'CUIT' => 'AR',
            'CNPJ' => 'BR',
            'RUC' => 'PY',
            'NIT' => 'CO',
            default => null,
        };

        $address = $this->extractAddressFromNode($text) ?? '';

        $countriesInAddress = [];

        $patterns = [
            'AR' => '/\bARGENTINA\b/iu',
            'BR' => '/\b(?:BRASIL|BRAZIL)\b/iu',
            'PY' => '/\b(?:PARAGUAY|PARAGUAI)\b/iu',
            'UY' => '/\b(?:URUGUAY|URUGUAI)\b/iu',
            'CO' => '/\bCOLOMBIA\b/iu',
        ];

        foreach ($patterns as $alpha2 => $pattern) {
            if (preg_match($pattern, $address)) {
                $countriesInAddress[] = $alpha2;
            }
        }

        $countriesInAddress = array_values(
            array_unique($countriesInAddress)
        );

        if (count($countriesInAddress) > 1) {
            throw new \DomainException(
                'Login XML informa más de un país para la misma parte.'
            );
        }

        $countryFromAddress = $countriesInAddress[0] ?? null;

        if (
            $countryFromType !== null
            && $countryFromAddress !== null
            && $countryFromType !== $countryFromAddress
        ) {
            throw new \DomainException(
                "El tipo fiscal {$taxType} contradice el país informado " .
                'en el domicilio de la parte.'
            );
        }

        return $countryFromType ?? $countryFromAddress;
    }

    /**
     * Compatibilidad con los consumidores existentes que sólo necesitan
     * el número fiscal.
     */
    protected function extractTaxIdFromText(string $text): ?string
    {
        return $this->extractTaxIdentityFromText($text)['tax_id'] ?? null;
    }

    /**
     * Validar datos extraídos - SOPORTA MÚLTIPLES BillOfLading
     */
    public function validate(array $data): array
    {
        $errors = [];

        // Validar que existan BLs
        if (empty($data['bills_of_lading'])) {
            $errors[] = 'Al menos un Bill of Lading es requerido en el XML';
            return $errors;
        }

        // Validar header principal (para voyage/shipment)
        if (empty($data['header']['loading_port'])) {
            $errors[] = 'Puerto de carga requerido en el XML';
        }

        if (empty($data['header']['discharge_port'])) {
            $errors[] = 'Puerto de descarga requerido en el XML';
        }

        // Validar cada BL individualmente
        foreach ($data['bills_of_lading'] as $blIndex => $bl) {
            $blNum = $blIndex + 1;
            $blRef = $bl['bill_number'] ?? "BL #{$blNum}";
            
            if (empty($bl['bill_number'])) {
                $errors[] = "Número de Bill of Lading requerido en {$blRef}";
            }

            if (empty($bl['shipper_name']) && empty($bl['shipper_cuit'])) {
                $errors[] = "Información del shipper requerida en {$blRef}";
            }

            if (empty($bl['consignee_name'])) {
                $errors[] = "Nombre del consignee requerido en {$blRef}";
            }

            // Validar contenedores de este BL
            if (empty($bl['containers'])) {
                $errors[] = "Al menos un contenedor requerido en {$blRef}";
            }

            foreach ($bl['containers'] as $cIndex => $container) {
                $lineRef = "{$blRef} línea " . ($cIndex + 1);
                
                if (empty($container['container_number'])) {
                    $errors[] = "Número de contenedor requerido en {$lineRef}";
                }

                if (empty($container['container_type'])) {
                    $errors[] = "Tipo de contenedor requerido en {$lineRef}";
                } elseif ($this->resolveContainerType($container['container_type']) === null) {
                    $errors[] = "Tipo de contenedor desconocido \"" . strtoupper(trim((string)$container['container_type']))
                        . "\" en el contenedor " . ($container['container_number'] ?? 's/n')
                        . " ({$lineRef}). Debe agregarse su correspondencia al catálogo antes de importar.";
                }

                if ($container['gross_weight_kg'] <= 0) {
                    $errors[] = "Peso bruto debe ser mayor a 0 en {$lineRef}";
                }

                if ($container['net_weight_kg'] > $container['gross_weight_kg']) {
                    $errors[] = "Peso neto no puede ser mayor al peso bruto en {$lineRef}";
                }

                if ($container['tare_weight_kg'] <= 0) {
                    $errors[] = "Peso tara debe ser mayor a 0 en {$lineRef}";
                }
            }
        }

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
                'cargo_description' => $bl['cargo_description'] ?? null,
                'freight_terms' => $bl['freight_terms'] ?? null,
                'freight_amount' => $bl['freight_amount'] ?? null,
                'freight_currency' => $bl['freight_currency'] ?? null,
                'total_containers' => count($bl['containers']),
                'total_weight_kg' => array_sum(array_column($bl['containers'], 'gross_weight_kg')),
                'containers' => $blContainers
            ];
            
            $allContainers = array_merge($allContainers, $blContainers);
        }
        
        $service = $this->parseVesselVoyageFlag(
            $data['header']['vessel_name'] ?? null
        );

        return [
            'voyage' => [
                'voyage_number' => $service['voyage_number'],
                'vessel_name' => $service['vessel_name'],
                'origin_port' => $data['header']['loading_port'] ?? null,
                'destination_port' => null,
                'departure_date' => $this->parseDate($data['header']['loading_date'] ?? null),
                'estimated_arrival_date' => null
            ],
            'shipment' => [
                'shipment_number' => 'LGN-' . $service['voyage_number'],
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
    protected function parseDate(?string $dateStr, string $modifier = null): ?string
    {
        $dateStr = trim((string) $dateStr);

        if ($dateStr === '') {
            return null;
        }

        try {
            $date = \Carbon\Carbon::parse($dateStr);
        } catch (Exception $e) {
            throw new Exception("Login XML: fecha inválida '{$dateStr}'.");
        }

        if ($modifier) {
            $date = $date->modify($modifier);
        }

        return $date->format('Y-m-d H:i:s');
    }

    protected function parseVesselVoyageFlag(?string $raw): array
    {
        $raw = trim((string) $raw);
        $pos = strrpos($raw, '/');

        if ($raw === '' || $pos === false) {
            throw new Exception(
                "Login XML: InitialVesselVoyFlag inválido o ausente."
            );
        }

        $vessel = trim(substr($raw, 0, $pos));
        $voyage = trim(substr($raw, $pos + 1));

        if ($vessel === '' || $voyage === '') {
            throw new Exception(
                "Login XML: no se pudo separar buque y viaje."
            );
        }

        return [
            'vessel_name' => $vessel,
            'voyage_number' => $voyage,
        ];
    }

    /**
     * Crear Voyage
     */
    protected function createVoyage(array $data, array $context): Voyage
    {
        $company = \App\Models\Company::findOrFail($context['company_id']);
        
        $originPort = $this->findPortByName(
            $data['voyage']['origin_port'] ?? ''
        );
        
        if (!$originPort) {
            throw new Exception('Login XML: puerto de origen no resuelto.');
        }
        
        $destinationPorts = collect($data['bills_of_lading'] ?? [])
            ->pluck('discharge_port')
            ->filter()
            ->unique()
            ->map(function ($name) {
                $port = $this->findPortByName($name);

                if (!$port) {
                    throw new Exception(
                        "Login XML: puerto de descarga '{$name}' no resuelto."
                    );
                }

                return $port;
            })
            ->values();

        if ($destinationPorts->isEmpty()) {
            throw new Exception(
                'Login XML: no hay puertos de descarga declarados.'
            );
        }

        $hasMultipleDestinations =
            $destinationPorts->pluck('id')->unique()->count() > 1;

        $destinationPort = $hasMultipleDestinations
            ? null
            : $destinationPorts->first();

        $vesselName = trim((string) ($data['voyage']['vessel_name'] ?? ''));
        $leadVessel = $this->findOrCreateVessel(
            $vesselName,
            $company->id
        );

        $this->guardVoyageNumberIsFree(
            $data['voyage']['voyage_number']
        );

        $originIso = strtoupper(
            (string) $originPort->country?->alpha2_code
        );

        $destinationIsos = $destinationPorts
            ->map(fn ($port) =>
                strtoupper((string) $port->country?->alpha2_code)
            )
            ->unique();

        $cargoType = match (true) {
            $destinationIsos->count() === 1
                && $destinationIsos->first() === $originIso
                => 'cabotage',
            $originIso === 'AR'
                => 'export',
            $destinationIsos->count() === 1
                && $destinationIsos->first() === 'AR'
                => 'import',
            default => 'transit',
        };

        return Voyage::create([
            'voyage_number' => $data['voyage']['voyage_number'],
            'company_id' => $company->id,
            'lead_vessel_id' => $leadVessel->id,
            'origin_country_id' => $originPort->country_id,
            'origin_port_id' => $originPort->id,
            'destination_country_id' => $destinationPort?->country_id,
            'destination_port_id' => $destinationPort?->id,
            'has_multiple_destinations' => $hasMultipleDestinations,
            'departure_date' => $data['voyage']['departure_date'],
            'estimated_arrival_date' =>
                $data['voyage']['estimated_arrival_date'],
            'voyage_type' => 'single_vessel',
            'cargo_type' => $cargoType,
            'status' => 'planning',
            'is_convoy' => false,
            'vessel_count' => 1,
            'total_cargo_capacity_tons' =>
                $leadVessel->cargo_capacity_tons ?? 0,
            'total_container_capacity' =>
                $leadVessel->container_capacity ?? 0,
            'active' => true,
            'created_date' => now(),
            'created_by_user_id' => $context['user_id'],
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
            'cargo_capacity_tons' => $voyage->leadVessel?->cargo_capacity_tons,
            'container_capacity' => $voyage->leadVessel?->container_capacity ?? 0,
            'status' => $data['shipment']['status'],
            'active' => true,
            'created_date' => now(),
            'created_by_user_id' => $context['user_id']
        ]);
    }

    /**
     * Crear Bill of Lading
     */
    protected function createBillOfLading(array $data, Shipment $shipment, array $context): BillOfLading
    {
        // Crear o encontrar clientes con datos reales del XML
        $shipper = $this->findOrCreateClient(
            $data['bill_of_lading']['shipper_name'], 
            'shipper',
            $context,
            $data['bill_of_lading']['shipper_cuit'] ?? null,
            'CUIT'
        );
        
        $consignee = $this->findOrCreateClient(
            $data['bill_of_lading']['consignee_name'], 
            'consignee',
            $context,
            $data['bill_of_lading']['consignee_tax_id'] ?? null
        );
        
        $notifyParty = null;
        if (!empty($data['bill_of_lading']['notify_party_name'])) {
            $notifyParty = $this->findOrCreateClient(
                $data['bill_of_lading']['notify_party_name'], 
                'notify_party',
                $context
            );
        }

        // Obtener cargo type por defecto
        $cargoType = CargoType::where('active', true)->first();
        $packagingType = PackagingType::where('active', true)->first();


        return BillOfLading::create([
            'shipment_id' => $shipment->id,
            'bill_number' => $data['bill_of_lading']['bill_number'],
            'shipper_id' => $shipper?->id,
            'consignee_id' => $consignee?->id,
            'notify_party_id' => $notifyParty?->id,
            'loading_port_id' => $shipment->voyage->origin_port_id,
            'discharge_port_id' => $shipment->voyage->destination_port_id,
            'primary_cargo_type_id' => $cargoType?->id ?? 1,        
            'primary_packaging_type_id' => $packagingType?->id ?? 1,
            'bill_date' => $data['bill_of_lading']['bill_date'] ?? now(),
            'loading_date' => $data['bill_of_lading']['loading_date'] ?? now(),
            'cargo_description' => $data['bill_of_lading']['cargo_description'],
            'total_packages' => $data['bill_of_lading']['total_containers'],
            'gross_weight_kg' => $data['bill_of_lading']['total_weight_kg'],
            'container_count' => $data['bill_of_lading']['total_containers'],
            'freight_terms' => 'prepaid',
            'currency_code' => 'USD',
            'status' => 'draft',
            'is_consolidated' => false,
            'created_by_user_id' => $context['user_id']
        ]);
    }

    /**
     * Crear ShipmentItems (uno por contenedor)
     */
    protected function createShipmentItems(array $data, BillOfLading $billOfLading): array
    {
        $items = [];
        
        $cargoType = CargoType::where('name', 'LIKE', '%container%')->first() 
                     ?? CargoType::first();
        $packagingType = PackagingType::where('name', 'LIKE', '%container%')->first() 
                         ?? PackagingType::first();

        foreach ($containers as $containerData) {
            $containerNumber = $containerData['container_number'];
            
            // Crear ShipmentItem
            $item = ShipmentItem::create([
                'bill_of_lading_id' => $billOfLading->id,
                'line_number' => $containerData['line_number'],
                'item_reference' => 'LGN-' . $containerData['container_number'],
                'item_description' => $containerData['package_description'],
                'cargo_type_id' => $cargoType?->id,
                'packaging_type_id' => $packagingType?->id,
                'package_quantity' => 1,
                'gross_weight_kg' => $containerData['gross_weight_kg'],
                'net_weight_kg' => $containerData['net_weight_kg'],
                'country_of_origin' => $containerData['country_of_origin'],
                'commodity_code' => $containerData['commodity_code'],
                'cargo_marks' => $containerData['seals'] ? "Seals: {$containerData['seals']}" : null,
                'package_type_description' => $containerData['package_description'],
                'created_date' => now(),
                'created_by_user_id' => 1
            ]);

            // Crear Container asociado (evitar duplicados)
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
                        'max_gross_weight_kg' => $containerData['gross_weight_kg'] + 5000,
                        'current_gross_weight_kg' => $containerData['gross_weight_kg'],
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
                'status' => 'loaded',
                'created_date' => now(),
                'created_by_user_id' => 1
            ]);

            $items[] = $item;
        }

        return $items;
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
            $blData['shipper_cuit'] ?? null,
            'CUIT'
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

        // Login es siempre carga contenedorizada
        $cargoType = CargoType::where('code', 'CON001')->where('active', true)->firstOrFail();        // CONTENEDORES
        $packagingType = PackagingType::where('code', 'T')->where('active', true)->firstOrFail(); // CONTENEDOR

        // Puerto propio del BL; si no viene o no está en catálogo, cae al del voyage
        $blLoadingPort = !empty($blData['loading_port'])
            ? $this->findPortByName($blData['loading_port'])
            : null;
        $blDischargePort = !empty($blData['discharge_port'])
            ? $this->findPortByName($blData['discharge_port'])
            : null;

        if (!$blLoadingPort || !$blDischargePort) {
            throw new Exception(
                "Login XML: puerto de carga o descarga no resuelto para BL '{$blData['bill_number']}'."
            );
        }

        $bill = BillOfLading::create([
            'shipment_id' => $shipment->id,
            'bill_number' => $blData['bill_number'],
            'shipper_id' => $shipper?->id,
            'consignee_id' => $consignee?->id,
            'notify_party_id' => $notifyParty?->id,
            'loading_port_id' => $blLoadingPort->id,
            'discharge_port_id' => $blDischargePort->id,
            'primary_cargo_type_id' => $cargoType->id,
            'primary_packaging_type_id' => $packagingType->id,
            'bill_date' => $blData['bill_date'],
            'loading_date' => $blData['loading_date'],
            'cargo_description' => $blData['cargo_description'],
            'total_packages' => $blData['total_containers'],
            'gross_weight_kg' => $blData['total_weight_kg'],
            'container_count' => $blData['total_containers'],
            'freight_terms' => $blData['freight_terms'],
            'freight_amount' => $blData['freight_amount'],
            'currency_code' => $blData['freight_currency'],
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

        // Catálogos resueltos una sola vez por importación (cache de instancia).
        // Login es siempre carga contenedorizada: CONTENEDORES (9) / CONTENEDOR (4)
        if ($this->cachedCargoType === null) {
            $this->cachedCargoType = CargoType::where('code', 'CON001')->where('active', true)->firstOrFail();
        }
        if ($this->cachedPackagingType === null) {
            $this->cachedPackagingType = PackagingType::where('code', 'T')->where('active', true)->firstOrFail();
        }
        $cargoType = $this->cachedCargoType;
        $packagingType = $this->cachedPackagingType;

        foreach ($containers as $index => $containerData) {
            $containerNumber = $containerData['container_number'];
            
            // Crear ShipmentItem
            $item = ShipmentItem::create([
                'bill_of_lading_id' => $billOfLading->id,
                'line_number' => $index + 1,
                'item_reference' => 'LGN-' . $containerNumber,
                'item_description' => $billOfLading->cargo_description,
                'cargo_type_id' => $cargoType->id,
                'packaging_type_id' => $packagingType->id,
                'package_quantity' => 1,
                'unit_of_measure' => 'KG',
                'gross_weight_kg' => $containerData['gross_weight_kg'],
                'net_weight_kg' => $containerData['net_weight_kg'],
                'country_of_origin' => $containerData['country_of_origin'],
                'commodity_code' => $containerData['commodity_code'],
                'tariff_position' => null,
                'cargo_marks' => $containerData['seals'] ? "Seals: {$containerData['seals']}" : null,
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
                        'max_gross_weight_kg' => $containerType->max_gross_weight_kg,
                        'current_gross_weight_kg' => $containerData['gross_weight_kg'],
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
        ?string $taxId = null,
        ?string $declaredTaxType = null
    ): ?Client {
        if (empty($name)) {
            return null;
        }

        $cleanName = $this->cleanClientName($name);

        $identity = $this->resolveClientTaxIdentity(
            $taxId,
            $name,
            $declaredTaxType
        );

        $cleanTaxId = $identity['tax_id'];
        $taxType = $identity['tax_type'];

        $countryAlpha2 = $this->inferClientCountryAlpha2(
            $taxType,
            $name
        );

        if ($countryAlpha2 === null) {
            throw new \DomainException(
                "Login XML no informa un país confiable para '{$cleanName}'. " .
                'No se creará un cliente con país inventado.'
            );
        }

        $countryId = \App\Models\Country::query()
            ->where('alpha2_code', $countryAlpha2)
            ->value('id');

        if (!$countryId) {
            throw new \DomainException(
                "No existe el país {$countryAlpha2} en el catálogo."
            );
        }

        Log::debug('findOrCreateClient - identidad resuelta', [
            'name' => $cleanName,
            'type' => $type,
            'tax_id' => $cleanTaxId,
            'tax_type' => $taxType,
            'country' => $countryAlpha2,
            'country_id' => $countryId,
        ]);

        // Con identificador fiscal, la identidad es tax_id + country_id.
        if ($cleanTaxId) {
            $client = Client::query()
                ->where('tax_id', $cleanTaxId)
                ->where('country_id', $countryId)
                ->first();

            if ($client) {
                return $client;
            }
        } else {
            // Sin identificador: sólo reutilizar otro cliente también sin tax_id,
            // del mismo país y con el mismo nombre normalizado.
            $client = Client::query()
                ->whereNull('tax_id')
                ->where('country_id', $countryId)
                ->whereRaw(
                    'UPPER(TRIM(legal_name)) = ?',
                    [mb_strtoupper(trim($cleanName))]
                )
                ->first();

            if ($client) {
                return $client;
            }
        }

        $documentTypeId = null;

        if ($cleanTaxId && $taxType) {
            $documentTypeId = \App\Models\DocumentType::query()
                ->where('code', $taxType)
                ->where('country_id', $countryId)
                ->where('active', true)
                ->value('id');

            if (!$documentTypeId) {
                throw new \DomainException(
                    "El tipo fiscal {$taxType} no corresponde al país " .
                    "{$countryAlpha2} o no existe en el catálogo."
                );
            }
        }

        return Client::create([
            'tax_id' => $cleanTaxId,
            'country_id' => (int) $countryId,
            'legal_name' => $cleanName,
            'document_type_id' => $documentTypeId
                ? (int) $documentTypeId
                : null,
            'status' => 'active',
            'created_by_company_id' => $context['company_id'],
            'verified_at' => now(),
            'created_by_user_id' => $context['user_id'],
        ]);
    }

    /**
     * Limpiar nombre del cliente
     */
    protected function cleanClientName(string $name): string
    {
        // La primera línea contiene la razón social; las siguientes son domicilio.
        $lines = preg_split('/\r\n|\r|\n/', $name);
        $mainName = trim($lines[0] ?? '');

        // El archivo real puede pegar CUIT/CNPJ/CPNJ/TAX ID a la razón social.
        // Desde el marcador fiscal en adelante ya no forma parte del nombre.
        $mainName = preg_replace(
            '/\s+(?:CUIT(?:\s+NBR)?|CNPJ|CPNJ|R\.?U\.?C\.?|NIT|TAX\s*ID)' .
            '\s*:?\s*[0-9][0-9.\-\/]*.*$/iu',
            '',
            $mainName
        );

        return trim($mainName, " \t\n\r\0\x0B-–—,;:");
    }

    /**
     * Encontrar o crear embarcación con datos del XML
     */
    protected function findOrCreateVessel(
        string $vesselName,
        int $companyId
    ): Vessel {
        $vesselName = trim($vesselName);

        if ($vesselName === '') {
            throw new Exception('Login XML: nombre de buque ausente.');
        }

        $vessel = Vessel::where('company_id', $companyId)
            ->whereRaw('UPPER(name) = ?', [mb_strtoupper($vesselName)])
            ->first();

        if ($vessel) {
            return $vessel;
        }

        return Vessel::create([
            'name' => $vesselName,
            'company_id' => $companyId,
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
            'default_currency' => 'USD',
            'default_freight_terms' => 'prepaid',
            'default_country' => 'ARG'
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
     * Determinar cargo_type basándose en puertos de origen y destino
     */
    protected function determineCargoType(array $data): string
    {
        try {
            $originPortName = $data['voyage']['origin_port'] ?? '';
            $destPortName = $data['voyage']['destination_port'] ?? '';
            
            // Usar el método existente findPortByName()
            $loadingPort = $this->findPortByName($originPortName);
            $dischargePort = $this->findPortByName($destPortName);
            
            if ($loadingPort && $dischargePort) {
                $originCountry = $loadingPort->country_id;
                $destCountry = $dischargePort->country_id;
                
                // Argentina (1) -> Paraguay (2) = Export
                if ($originCountry == 1 && $destCountry == 2) {
                    return 'export';
                }
                
                // Paraguay (2) -> Argentina (1) = Import  
                if ($originCountry == 2 && $destCountry == 1) {
                    return 'import';
                }
                
                // Mismo país = Cabotaje
                if ($originCountry == $destCountry) {
                    return 'cabotage';
                }
                
                // Brasil/Uruguay/otros = Tránsito
                if (in_array($originCountry, [3, 4]) || in_array($destCountry, [3, 4])) {
                    return 'transit';
                }
            }
            
            // Análisis por nombres si no encontramos en BD
            $originUpper = strtoupper($originPortName);
            $destUpper = strtoupper($destPortName);
            
            $argentinePorts = ['BUENOS AIRES', 'ROSARIO', 'SANTA FE', 'CONCEPCION'];
            $paraguayPorts = ['ASUNCION', 'VILLETA'];
            
            $isOriginArgentina = collect($argentinePorts)->contains(fn($port) => stripos($originUpper, $port) !== false);
            $isDestArgentina = collect($argentinePorts)->contains(fn($port) => stripos($destUpper, $port) !== false);
            $isOriginParaguay = collect($paraguayPorts)->contains(fn($port) => stripos($originUpper, $port) !== false);
            $isDestParaguay = collect($paraguayPorts)->contains(fn($port) => stripos($destUpper, $port) !== false);
            
            if ($isOriginArgentina && $isDestParaguay) return 'export';
            if ($isOriginParaguay && $isDestArgentina) return 'import';
            
        } catch (Exception $e) {
            Log::warning('Error determinando cargo_type', [
                'error' => $e->getMessage(),
                'origin_port' => $data['voyage']['origin_port'] ?? 'N/A',
                'dest_port' => $data['voyage']['destination_port'] ?? 'N/A'
            ]);
        }
        
        return 'import'; // Default para Login (mayormente importaciones)
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
     * Completar registro de importación
     */
    protected function completeImportRecord(
        ManifestImport $importRecord,
        Voyage $voyage,
        array $shipmentIds,
        array $billIds,
        array $itemIds,
        array $containerIds,
        float $startTime
    ): void {
        $processingTime = microtime(true) - $startTime;
        
        $createdObjects = [
            'voyage' => [$voyage->id],
            'shipment' => $shipmentIds,
            'bill' => $billIds,
            'item' => $itemIds,
            'container' => $containerIds,
        ];
        
        $importRecord->recordExplicitlyCreatedObjects($createdObjects);
        $importRecord->markAsCompleted([
            'voyage_id' => $voyage->id,
            'processing_time_seconds' => round($processingTime, 2),
            'notes' => 'Importación Login XML completada exitosamente'
        ]);
        
        Log::info('Login XML import record completed', [
            'import_id' => $importRecord->id,
            'processing_time' => round($processingTime, 2) . 's'
        ]);
    }
}
