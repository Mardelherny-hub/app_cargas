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
use App\Models\Port;
use App\Models\Vessel;
use App\Models\ContainerType;
use App\Models\CargoType;
use App\Models\PackagingType;
use App\Models\ManifestImport;
use App\Services\Parsers\Concerns\ExtractsEmbeddedTaxId;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Exception;

/**
 * PARSER PARA TFP.TXT - FORMATO JERÁRQUICO CON MARCADORES **...**
 * 
 * Estructura:
 * - **BL** ... **FIN BL**
 * - **CONTENEDORES** ... **FIN CONTENEDORES**
 * - **LINEAS** ... **FIN LINEAS**
 * - Valores: CAMPO: /*valor*
 */
class TfpTextParser implements ManifestParserInterface
{
    use ExtractsEmbeddedTaxId;

    protected array $stats = [
        'processed_bls' => 0,
        'processed_containers' => 0,
        'processed_items' => 0,
        'errors' => 0,
        'warnings' => []
    ];

    public function canParse(string $filePath): bool
    {
        if (strtolower(pathinfo($filePath, PATHINFO_EXTENSION)) !== 'txt') {
            return false;
        }

        $head = @file_get_contents($filePath, false, null, 0, 4096) ?: '';
        return strpos($head, 'BLNUMERO:') !== false
            && strpos($head, 'BLMARITIMONUMERO:') !== false;
    }

    public function parse(string $filePath): ManifestParseResult
    {
        $startTime = microtime(true);

        try {
            Log::info('Starting TFP parse', ['file' => $filePath]);

            // Registrar la importación (con dup-check por hash)
            $importRecord = $this->createImportRecord($filePath);

            $content = @file_get_contents($filePath);
            if ($content === false || $content === '') {
                throw new Exception('No se pudo leer el archivo o está vacío.');
            }

            $content = str_replace(["\r\n", "\r"], "\n", $content);
            $blBlocks = $this->extractBlBlocks($content);

            if (empty($blBlocks)) {
                return ManifestParseResult::failure(['No se encontraron bloques **BL** en el archivo.']);
            }

            // Transacción para persistir todo
            $result = DB::transaction(function () use ($blBlocks, $importRecord, $startTime) {
                // Crear voyage único
                $voyageData = $this->extractVoyageData($blBlocks[0]);
                $voyage = $this->findOrCreateVoyage($voyageData);
                
                // Crear shipment
                $shipment = $this->findOrCreateShipment($voyage, $voyageData);
                
                $allBills = [];
                $allContainers = [];
                $allItems = [];

                foreach ($blBlocks as $block) {
                    $header = $this->parseHeader($block);
                    $containers = $this->parseContainers($block);
                    $lines = $this->parseLines($block);

                    // Validar BL duplicado
                    $existing = BillOfLading::where('bill_number', $header['bl_numero'])->first();
                    if ($existing) {
                        throw new Exception("Ya existe un BL con número: {$header['bl_numero']}");
                    }

                    // Crear BillOfLading
                    $bill = $this->createBillOfLading($shipment, $header, !empty($containers));
                    $allBills[] = $bill;
                    $this->stats['processed_bls']++;
                    
                    // Crear contenedores
                    $billContainers = [];
                    foreach ($containers as $containerData) {
                        $container = $this->createContainer($bill, $containerData);
                        if ($container) {
                            $allContainers[] = $container;
                            $billContainers[] = $container;
                            $this->stats['processed_containers']++;
                        }
                    }
                    
                    // Crear items
                    $billItems = [];
                    foreach ($lines as $lineData) {
                        $item = $this->createShipmentItem($bill, $lineData);
                        if ($item) {
                            $allItems[] = $item;
                            $billItems[] = $item;
                            $this->stats['processed_items']++;
                        }
                    }

                    // Vincular los contenedores del BL con su ítem (pivote container_shipment_item).
                    if (!empty($billItems) && !empty($billContainers)) {
                        foreach ($billContainers as $c) {
                            $this->attachContainerToItem($c, $billItems[0]);
                        }
                    }

                    // Consignar en el BL la descripción y la posición arancelaria
                    // tomadas del primer ítem (en TFP hay un ítem por BL).
                    if (!empty($billItems)) {
                        $firstItem = $billItems[0];
                        $bill->update([
                            'cargo_description' => $firstItem->item_description ?: $bill->cargo_description,
                            'commodity_code'    => $firstItem->commodity_code ?: null,
                            'tariff_position'   => $firstItem->tariff_position ?: null,
                        ]);
                    }
                }

                // Registrar objetos creados y completar el registro de importación.
                // El revert reconstruye items/containers (incluido el pivote) desde el voyage_id.
                if ($importRecord) {
                    $importRecord->recordCreatedObjects([
                        'voyage'   => [$voyage->id],
                        'shipment' => [$shipment->id],
                        'bill'     => array_map(fn($b) => $b->id, $allBills),
                        'item'     => array_map(fn($i) => $i->id, $allItems),
                    ]);
                    $importRecord->markAsCompleted([
                        'voyage_id'               => $voyage->id,
                        'created_bills'           => count($allBills),
                        'created_items'           => count($allItems),
                        'processing_time_seconds' => round(microtime(true) - $startTime, 2),
                        'import_statistics'       => $this->stats,
                        'notes'                   => 'Importación TFP completada',
                    ]);
                }

                return [
                    'voyage' => $voyage,
                    'shipment' => $shipment,
                    'bills' => $allBills,
                    'containers' => $allContainers,
                    'items' => $allItems
                ];
            });

            Log::info('TFP parsing completed', $this->stats);

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
                $importRecord->markAsFailed([$e->getMessage()], [
                    'import_statistics' => $this->stats,
                ]);
            }
            Log::error('TFP parsing failed', [
                'file' => $filePath,
                'error' => $e->getMessage()
            ]);
            return ManifestParseResult::failure(
                [$e->getMessage()],
                $this->stats['warnings'],
                $this->stats
            );
        }
    }

    protected function extractBlBlocks(string $content): array
    {
        $blocks = [];
        if (preg_match_all('/\*\*BL\*\*(.*?)\*\*FIN BL\*\*/is', $content, $m)) {
            foreach ($m[1] as $chunk) {
                $blocks[] = $chunk;
            }
        }
        return $blocks;
    }

    protected function parseHeader(string $section): array
    {
        $fields = [
            'bl_numero' => 'BLNUMERO:',
            'bl_maritimo_numero' => 'BLMARITIMONUMERO:',
            'trb' => 'TRB:',
            'buque' => 'BUQUE:',
            'consolidado' => 'CONSOLIDADO:',
            'consignatario' => 'CONSIGNATARIO:',
            'consignatario_domicilio' => 'CONSIGNATARIODOMICILIO:',
            'consignatario_ruc' => 'CONSIGNATARIORUC:',
            'cargador' => 'CARGADOR:',
            'cargador_domicilio' => 'CARGADORDOMICILIO:',
            'cargador_ruc' => 'CARGADORRUC:',
            'notificatario' => 'NOTIFICATARIO:',
            'notificatario_domicilio' => 'NOTIFICATARIODOMICILIO:',
            'notificatario_ruc' => 'NOTIFICATARIORUC:',
            'medio_transp' => 'MEDIOTRANSP:',
            'cod_puerto_carga' => 'CODPUERTOCARGA:',
            'puerto_carga' => 'PUERTOCARGA:',
            'cod_puerto_descarga' => 'CODPUERTODESCARGA:',
            'puerto_descarga' => 'PUERTODESCARGA:',
        ];

        $out = [];
        foreach ($fields as $key => $label) {
            $out[$key] = $this->extractValue($section, $label);
        }
        return $out;
    }

    protected function parseContainers(string $section): array
    {
        if (!preg_match('/\*\*CONTENEDORES\*\*(.*?)\*\*FIN CONTENEDORES\*\*/is', $section, $m)) {
            return [];
        }

        $block = trim($m[1]);
        if ($block === '') return [];

        $lines = preg_split('/\R+/', $block);
        $containers = [];
        $current = [];

        $map = [
            'condicion' => 'CONDICION:',
            'tipo' => 'TIPO:',
            'medida' => 'MEDIDA:',
            'nro_precinta' => 'NROPRECINTA:',
            'numero' => 'NUMERO:',
            'peso' => 'PESO:',
            'cantidad' => 'CANTIDAD:',
        ];

        foreach ($lines as $rawLine) {
            $line = trim($rawLine);
            if ($line === '') continue;

            foreach ($map as $k => $label) {
                if (stripos($line, $label) !== false) {
                    $val = $this->extractValueFromLine($line, $label);
                    if ($val !== null) {
                        $current[$k] = $val;
                    }
                    break;
                }
            }

            if (stripos($line, 'CANTIDAD:') !== false) {
                if (!empty($current)) {
                    $containers[] = $current;
                    $current = [];
                }
            }
        }

        if (!empty($current)) {
            $containers[] = $current;
        }

        return $containers;
    }

    protected function parseLines(string $section): array
    {
        if (!preg_match('/\*\*LINEAS\*\*(.*?)\*\*FIN LINEAS\*\*/is', $section, $m)) {
            return [];
        }

        $block = $m[1];

        $row = [
            'cant_total_bultos' => $this->extractValue($block, 'CANTTOTALBULTOS:'),
            'naturaleza_mercaderia' => $this->extractValue($block, 'NATURALEZAMERCADERIA:'),
            'peso_total_bultos' => $this->extractValue($block, 'PESOTOTALBULTOS:'),
            'tipo_embalaje' => $this->extractValue($block, 'TIPOEMBALAJE:'),
            'cod_armonizado' => $this->extractValue($block, 'CODARMONIZADO:'),
            'volumen_total' => $this->extractValue($block, 'VOLUMENTOTAL:'),
        ];

        return (empty(array_filter($row, fn($v) => $v !== null && $v !== ''))) ? [] : [$row];
    }

protected function extractValue(string $scope, string $label): ?string
    {
        $pattern = '/' . preg_quote($label, '/') . '\s*\/\*(.*?)\*\//is';
        if (preg_match($pattern, $scope, $m)) {
            return trim($m[1]);
        }
        return null;
    }

    protected function extractValueFromLine(string $line, string $label): ?string
    {
        $pattern = '/' . preg_quote($label, '/') . '\s*\/\*(.*?)\*\//i';
        if (preg_match($pattern, $line, $m)) {
            return trim($m[1]);
        }
        return null;
    }

    protected function extractVoyageData(string $firstBlock): array
    {
        $header = $this->parseHeader($firstBlock);
        return [
            'voyage_number' => 'TFP-' . date('Ymd-His'),
            'vessel_name' => $header['buque'] ?? 'TFP VESSEL',
            'pol' => $header['cod_puerto_carga'] ?? 'ARBAI',
            'pod' => $header['cod_puerto_descarga'] ?? 'PYPSE',
        ];
    }

    protected function findOrCreateVoyage(array $data): Voyage
    {
        $user = auth()->user();
        $companyId = null;

        if ($user->userable_type === 'App\Models\Company' && $user->userable_id) {
            $companyId = (int) $user->userable_id;
        } elseif ($user->userable_type === 'App\Models\Operator' && $user->userable) {
            $companyId = $user->userable->company_id;
        }

        if (!$companyId) {
            throw new Exception("Usuario no tiene empresa asignada.");
        }

        $vessel = Vessel::firstOrCreate(
            ['name' => $data['vessel_name']],
            [
                'company_id' => $companyId,
                'registration_number' => $data['vessel_name'],
                'vessel_type_id' => 1,
                'flag_country_id' => 1,
                'length_meters' => 50.0,
                'beam_meters' => 12.0,
                'draft_meters' => 3.0,
                'cargo_capacity_tons' => 1000.0,
                'operational_status' => 'active',
                'active' => true
            ]
        );

        $originPort = $this->findOrCreatePort($data['pol']);
        $destPort = $this->findOrCreatePort($data['pod']);

        $voyage = Voyage::create([
            'voyage_number' => $data['voyage_number'],
            'company_id' => $companyId,
            'lead_vessel_id' => $vessel->id,
            'origin_port_id' => $originPort->id,
            'destination_port_id' => $destPort->id,
            'origin_country_id' => $originPort->country_id ?? 1,
            'destination_country_id' => $destPort->country_id ?? 2,
            'status' => 'planning',
            'voyage_type' => 'single_vessel',
            'cargo_type' => 'export',
            'departure_date' => now()->addDays(7),
            'estimated_arrival_date' => now()->addDays(10),
            'total_cargo_capacity_tons' => $vessel->cargo_capacity_tons ?? 1000.0,
            'total_container_capacity' => 40,
            'total_cargo_weight_loaded' => 0,
            'total_containers_loaded' => 0,
            'capacity_utilization_percentage' => 0
        ]);

        return $voyage;
    }

    protected function findOrCreateShipment(Voyage $voyage, array $data): Shipment
    {
        return Shipment::create([
            'voyage_id' => $voyage->id,
            'vessel_id' => $voyage->lead_vessel_id,
            'shipment_number' => 'TFP-' . now()->format('YmdHis'),
            'sequence_in_voyage' => 1,
            'vessel_role' => 'single',
            'is_lead_vessel' => true,
            'cargo_capacity_tons' => 5000,
            'container_capacity' => 200,
            'status' => 'planning'
        ]);
    }

    protected function createBillOfLading(Shipment $shipment, array $data, bool $hasContainers = false): BillOfLading
    {
        $shipper = $this->findOrCreateClient($data['cargador'] ?? 'Cargador TFP', 'shipper', $data['cargador_ruc'] ?? null);
        $consignee = $this->findOrCreateClient($data['consignatario'] ?? 'Consignatario TFP', 'consignee', $data['consignatario_ruc'] ?? null);
        $notify = $this->findOrCreateClient($data['notificatario'] ?? 'Notificatario TFP', 'notify', $data['notificatario_ruc'] ?? null);

        $loadingPort = $this->findOrCreatePort($data['cod_puerto_carga'] ?? 'ARBAI');
        $dischargePort = $this->findOrCreatePort($data['cod_puerto_descarga'] ?? 'PYPSE');

        return BillOfLading::create([
            'shipment_id' => $shipment->id,
            'bill_number' => $data['bl_numero'],
            'bill_date' => now(),
            'loading_date' => now()->addDays(1),
            'shipper_id' => $shipper->id,
            'consignee_id' => $consignee->id,
            'notify_party_id' => $notify->id,
            'loading_port_id' => $loadingPort->id,
            'discharge_port_id' => $dischargePort->id,
            'permiso_embarque' => $data['trb'] ?? null,
            'freight_terms' => 'prepaid',
            'status' => 'draft',
            // Si el BL trae contenedores: CargoType 9 (CONTENEDORES) + Packaging 4 (CONTENEDOR).
            // Si no, se mantiene el default (1 = DOCUMENTOS / A GRANEL).
            'primary_cargo_type_id' => $hasContainers ? 9 : 1,
            'primary_packaging_type_id' => $hasContainers ? 4 : 1,
            'gross_weight_kg' => 0,
            'net_weight_kg' => 0,
            'total_packages' => 1,
            'cargo_description' => 'Mercadería importada desde TFP',
            'is_consolidated' => strtoupper($data['consolidado'] ?? 'N') === 'S',
        ]);
    }

    protected function createContainer(BillOfLading $bill, array $data): ?Container
    {
        if (empty($data['numero'])) {
            return null;
        }

        $existing = Container::where('container_number', $data['numero'])->first();
        if ($existing) {
            // Reutilizar un contenedor existente es normal: el contenedor es un activo
            // físico que se repite entre viajes. No se expone como advertencia al usuario.
            Log::info('Contenedor reutilizado', ['container_number' => $data['numero']]);
            return $existing;
        }

        $containerType = $this->findOrCreateContainerType($data['tipo'] ?? '20DV');

        return Container::create([
            'container_number' => $data['numero'],
            'container_type_id' => $containerType->id,
            'tare_weight_kg' => 2300,
            'max_gross_weight_kg' => 30000,
            'current_gross_weight_kg' => floatval($data['peso'] ?? 0),
            'cargo_weight_kg' => floatval($data['peso'] ?? 0),
            'condition' => strtoupper($data['condicion'] ?? 'L'),
            'shipper_seal' => $data['nro_precinta'] ?? null,
            'operational_status' => 'loaded',
            'active' => true,
        ]);
    }

    protected function createShipmentItem(BillOfLading $bill, array $data): ?ShipmentItem
    {
        $lineNumber = ShipmentItem::where('bill_of_lading_id', $bill->id)->max('line_number') ?? 0;
        $lineNumber++;

        return ShipmentItem::create([
            'bill_of_lading_id' => $bill->id,
            'line_number' => $lineNumber,
            'item_description' => $data['naturaleza_mercaderia'] ?? 'Mercadería general',
            'package_quantity' => intval($data['cant_total_bultos'] ?? 1),
            'gross_weight_kg' => floatval($data['peso_total_bultos'] ?? 0),
            'net_weight_kg' => floatval($data['peso_total_bultos'] ?? 0) * 0.95,
            'volume_m3' => floatval($data['volumen_total'] ?? 0),
            'cargo_type_id' => 1,
            'packaging_type_id' => 1,
            'commodity_code' => $data['cod_armonizado'] ?? null,
            'tariff_position' => $data['cod_armonizado'] ?: null,
            'created_by_user_id' => auth()->id()
        ]);
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
     * Registrar la importación en ManifestImport (con dup-check por hash).
     */
    protected function createImportRecord(string $filePath): ManifestImport
    {
        $user = auth()->user();
        if (!$user) {
            throw new Exception('Usuario no autenticado para crear registro de importación');
        }

        $fileName = basename($filePath);
        $fileSize = file_exists($filePath) ? filesize($filePath) : null;
        $fileHash = file_exists($filePath) ? ManifestImport::generateFileHash($filePath) : null;

        $companyId = null;
        if ($user->userable_type === 'App\Models\Company' && $user->userable_id) {
            $companyId = (int) $user->userable_id;
        } elseif ($user->userable_type === 'App\Models\Operator' && $user->userable) {
            $companyId = $user->userable->company_id;
        }

        if ($fileHash && $companyId) {
            $existing = ManifestImport::isFileAlreadyImported($fileHash, $companyId);
            if ($existing) {
                throw new Exception("Este archivo ya fue importado anteriormente (ID: {$existing->id})");
            }
        }

        return ManifestImport::createForImport([
            'company_id'      => $companyId,
            'user_id'         => $user->id,
            'file_name'       => $fileName,
            'file_format'     => 'tfp',
            'file_size_bytes' => $fileSize,
            'file_hash'       => $fileHash,
            'parser_config'   => [
                'parser_class' => self::class,
            ],
        ]);
    }

    protected function findOrCreateClient(string $name, string $type, ?string $taxId = null): Client
    {
        $user = auth()->user();
        $companyId = $user->userable_type === 'App\Models\Company' ? $user->userable_id : 
                     ($user->userable->company_id ?? null);

        $name = trim($name);
        if (empty($name)) $name = 'Cliente TFP';

        // Prioridad: RUC declarado (CARGADORRUC/CONSIGNATARIORUC/NOTIFICATARIORUC) >
        // tax embebido en el nombre (ej. "TECNOMOTOR S.A. TAX ID 80002427-3"). No se fabrica.
        $normTaxId = $this->resolveTaxId($taxId, $name);

        // 1) Buscar por tax_id real (si hay)
        if ($normTaxId) {
            $client = Client::where('tax_id', $normTaxId)->first();
            if ($client) return $client;
        }

        // 2) Buscar por nombre
        $client = Client::where('legal_name', $name)->first();
        if ($client) return $client;

        return Client::create([
            'tax_id' => $normTaxId,
            'country_id' => 1,
            'document_type_id' => 1,
            'legal_name' => $name,
            'commercial_name' => $name,
            'status' => 'active',
            'created_by_company_id' => $companyId,
            'verified_at' => now()
        ]);
    }

    protected function findOrCreatePort(string $code): Port
    {
        if (empty($code)) $code = 'UNKNOWN';
        $code = strtoupper(trim($code));

        $port = Port::where('code', $code)->first();
        if ($port) return $port;

        $countryId = 1;
        $cityName = 'Ciudad Desconocida';

        if (str_starts_with($code, 'PY')) {
            $countryId = 2;
            $cityName = 'Asunción';
        } elseif (str_starts_with($code, 'BR')) {
            $countryId = 3;
            $cityName = 'Porto Alegre';
        } else {
            $cityName = 'Buenos Aires';
        }

        return Port::create([
            'code' => $code,
            'name' => 'Puerto ' . $code,
            'city' => $cityName,
            'country_id' => $countryId,
            'port_type' => 'river',
            'active' => true,
        ]);
    }

    protected function findOrCreateContainerType(string $code): ContainerType
    {
        $code = strtoupper(trim($code));

        $mapping = [
            '20DV' => '20GP',
            '40DV' => '40GP',
            '20GP' => '20GP',
            '40GP' => '40GP',
            '40HC' => '40HC',
        ];

        $mappedCode = $mapping[$code] ?? '20GP';
        
        $type = ContainerType::where('code', $mappedCode)->where('active', true)->first();
        
        if ($type) {
            if ($code !== $mappedCode) {
                $this->stats['warnings'][] = "Tipo contenedor '{$code}' mapeado a '{$mappedCode}'";
            }
            return $type;
        }

        $type = ContainerType::where('active', true)->first();
        if ($type) {
            $this->stats['warnings'][] = "Tipo '{$code}' no encontrado, usando '{$type->code}'";
            return $type;
        }

        throw new Exception("No hay tipos de contenedor en container_types. Ejecute ContainerTypesSeeder.");
    }

    public function getDefaultConfig(): array
    {
        return ['required_fields' => ['BLNUMERO', 'BUQUE']];
    }

    public function validate(array $data): array
    {
        return [];
    }

    public function transform(array $data): array
    {
        return $data;
    }

    public function getFormatInfo(): array
    {
        return [
            'markers' => ['**BL**', '**FIN BL**', '**CONTENEDORES**', '**LINEAS**'],
            'notes' => 'Valores entre /* ... */'
        ];
    }
}