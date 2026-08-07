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
use App\Services\Parsers\Concerns\ResolvesClientAddresses;
use App\Services\Parsers\Concerns\EnsuresUniqueVoyageNumber;
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
    use EnsuresUniqueVoyageNumber;
    use ResolvesClientAddresses;

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

            // Tolerar archivos en ISO-8859-1/Latin-1 (algunos generadores TFP no emiten UTF-8)
            if (!mb_check_encoding($content, 'UTF-8')) {
                $content = mb_convert_encoding($content, 'UTF-8', 'ISO-8859-1');
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
                    $bill = $this->createBillOfLading($shipment, $header, !empty($containers), $containers);
                    $allBills[] = $bill;
                    $this->stats['processed_bls']++;
                    
                    // Crear contenedores
                    $billContainers = [];
                    foreach ($containers as $containerData) {
                        $container = $this->createContainer($bill, $containerData);
                        if ($container) {
                            $allContainers[] = $container;
                            // Se guarda junto al dato del archivo: PESO y CANTIDAD
                            // son propios de cada contenedor y hacen falta para el
                            // pivote (ver attachContainerToItem).
                            $billContainers[] = ['model' => $container, 'data' => $containerData];
                            $this->stats['processed_containers']++;
                        }
                    }
                    
                    // Crear items
                    $billItems = [];
                    foreach ($lines as $lineData) {
                        $item = $this->createShipmentItem($bill, $lineData, !empty($containers));
                        if ($item) {
                            $allItems[] = $item;
                            // Se guarda con su dato de archivo: el campo CONTENEDOR
                            // de cada bloque LINEAS dice a que contenedor pertenece
                            // esa mercaderia.
                            $billItems[] = ['model' => $item, 'data' => $lineData];
                            $this->stats['processed_items']++;
                        }
                    }

                    // Vincular los contenedores del BL con su ítem (pivote container_shipment_item).
                    // Cada contenedor se vincula con SU mercaderia, buscando por el
                    // campo CONTENEDOR del bloque LINEAS. Antes se usaba siempre
                    // $billItems[0] y todos los contenedores del conocimiento
                    // recibian los bultos y el peso de la primera mercaderia
                    // (reportado por Roberto 07/08/2026: TGBU8924639 mostraba
                    // 21983,15 cuando su linea declara 21096).
                    if (!empty($billItems) && !empty($billContainers)) {
                        foreach ($billContainers as $c) {
                            $numero = $c['model']->container_number;

                            $propio = null;
                            foreach ($billItems as $bi) {
                                if (strcasecmp(trim($bi['data']['contenedor'] ?? ''), $numero) === 0) {
                                    $propio = $bi;
                                    break;
                                }
                            }

                            // Sin coincidencia (el archivo no declara CONTENEDOR en
                            // LINEAS) se mantiene el comportamiento anterior.
                            $elegido = $propio ?? $billItems[0];

                            $this->attachContainerToItem(
                                $c['model'],
                                $elegido['model'],
                                $c['data'] + [
                                    'peso'     => $c['data']['peso']     ?? $elegido['data']['peso_total_bultos']  ?? null,
                                    'cantidad' => $c['data']['cantidad'] ?? $elegido['data']['cant_total_bultos'] ?? null,
                                ]
                            );
                        }
                    }

                    // Consignar en el BL la descripción y la posición arancelaria
                    // tomadas del primer ítem (en TFP hay un ítem por BL).
                    if (!empty($billItems)) {
                        $firstItem = $billItems[0]['model'];
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
                        'created_containers'      => count($allContainers),
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
            // Viaje ya existente (bloqueo global de duplicado): mensaje amable, no SQL crudo.
            if (strpos($e->getMessage(), 'voyages_voyage_number_unique') !== false) {
                if (isset($importRecord)) {
                    $importRecord->markAsFailed([
                        'Este archivo ya fue importado anteriormente. El viaje ya existe en el sistema y no se duplicó ningún dato.'
                    ], ['import_statistics' => $this->stats]);
                }
                return ManifestParseResult::failure([
                    'Este archivo ya fue importado anteriormente. El viaje ya existe en el sistema y no se duplicó ningún dato. Si necesita importarlo de nuevo, primero revierta la importación desde el Historial de Importaciones.'
                ], $this->stats['warnings'], $this->stats);
            }

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

        // TARA, TEMPERATURA y OBS existen en la variante del formato que no trae
        // PESO ni CANTIDAD (verificado 06/08/2026 sobre ASUNCION B y VICKY B).
        $map = [
            'condicion' => 'CONDICION:',
            'tipo' => 'TIPO:',
            'medida' => 'MEDIDA:',
            'nro_precinta' => 'NROPRECINTA:',
            'numero' => 'NUMERO:',
            'peso' => 'PESO:',
            'cantidad' => 'CANTIDAD:',
            'tara' => 'TARA:',
            'temperatura' => 'TEMPERATURA:',
            'obs' => 'OBS:',
        ];

        foreach ($lines as $rawLine) {
            $line = trim($rawLine);
            if ($line === '') continue;

            // Un contenedor nuevo empieza en CONDICION:, que es el primer campo
            // de cada bloque. Antes se cerraba al ver CANTIDAD:, que es opcional:
            // en los archivos que no lo traen (ASUNCION B, VICKY B) no se cerraba
            // ninguno y cada contenedor pisaba al anterior, quedando solo el
            // ultimo. Delimitar por el campo de apertura no depende de que
            // existan los opcionales.
            if (stripos($line, 'CONDICION:') !== false && !empty($current)) {
                $containers[] = $current;
                $current = [];
            }

            foreach ($map as $k => $label) {
                if (stripos($line, $label) !== false) {
                    $val = $this->extractValueFromLine($line, $label);
                    if ($val !== null) {
                        $current[$k] = $val;
                    }
                    break;
                }
            }
        }

        if (!empty($current)) {
            $containers[] = $current;
        }

        return $containers;
    }

    /**
     * Cada mercaderia del conocimiento es su propio bloque **LINEAS**...**FIN
     * LINEAS**. Hasta 07/08/2026 se usaba preg_match (singular), que devuelve
     * solo la primera coincidencia: de las 119 mercaderias de ASUNCION B se
     * leian 52, una por conocimiento, y el resto se perdia en silencio.
     */
    protected function parseLines(string $section): array
    {
        if (!preg_match_all('/\*\*LINEAS\*\*(.*?)\*\*FIN LINEAS\*\*/is', $section, $m)) {
            return [];
        }

        $rows = [];

        foreach ($m[1] as $block) {
            // Un bloque LINEAS puede contener VARIAS mercaderias, no una.
            // Verificado 07/08/2026 sobre ASUNCION B: 52 bloques con 119
            // mercaderias en total; uno solo tiene 50. Como extractValue toma
            // la primera coincidencia del bloque, se leia una por conocimiento
            // y el resto se perdia en silencio.
            //
            // Cada mercaderia abre en CANTPARCIALBULTOS:, que es su primer
            // campo. Se corta ahi y cada fragmento se lee por separado.
            $fragmentos = preg_split('/(?=CANTPARCIALBULTOS:)/i', $block);

            foreach ($fragmentos as $frag) {
                if (stripos($frag, 'CANTPARCIALBULTOS:') === false) {
                    continue;
                }

                $row = [
                    'cant_total_bultos' => $this->extractValue($frag, 'CANTTOTALBULTOS:'),
                    'naturaleza_mercaderia' => $this->extractValue($frag, 'NATURALEZAMERCADERIA:'),
                    'peso_total_bultos' => $this->extractValue($frag, 'PESOTOTALBULTOS:'),
                    'tipo_embalaje' => $this->extractValue($frag, 'TIPOEMBALAJE:'),
                    'cod_armonizado' => $this->extractValue($frag, 'CODARMONIZADO:'),
                    'volumen_total' => $this->extractValue($frag, 'VOLUMENTOTAL:'),
                    // CONTENEDOR vincula la mercaderia con su contenedor; OBS trae
                    // el permiso de embarque en este formato.
                    'contenedor' => $this->extractValue($frag, 'CONTENEDOR:'),
                    'obs' => $this->extractValue($frag, 'OBS:'),
                ];

                if (!empty(array_filter($row, fn($v) => $v !== null && $v !== ''))) {
                    $rows[] = $row;
                }
            }
        }

        return $rows;
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

        // El voyage_number es único global. Si ya existe (en cualquier empresa),
        // se bloquea la importación con un error claro en lugar de chocar el índice.
        $this->guardVoyageNumberIsFree($data['voyage_number']);

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

    protected function createBillOfLading(Shipment $shipment, array $data, bool $hasContainers = false, array $containers = []): BillOfLading
    {
        $shipper = $this->findOrCreateClient($data['cargador'] ?? 'Cargador TFP', 'shipper', $data['cargador_ruc'] ?? null, $data['cargador_domicilio'] ?? null);
        $consignee = $this->findOrCreateClient($data['consignatario'] ?? 'Consignatario TFP', 'consignee', $data['consignatario_ruc'] ?? null, $data['consignatario_domicilio'] ?? null);

        // Algunos generadores TFP emiten NOTIFICATARIO como "nombre del consignatario + dirección"
        // pegados (verificado contra archivo real 13/07/2026). Si el notificatario empieza con el
        // nombre del consignatario del MISMO BL y le sobra texto, es el mismo cliente y el
        // sobrante es dirección: se evita crear un duplicado con nombre basura.
        $notifyName = trim($data['notificatario'] ?? '');
        $consigneeName = trim($data['consignatario'] ?? '');
        $notifyExtraAddr = null;
        if ($notifyName !== '' && $consigneeName !== ''
            && $notifyName !== $consigneeName
            && str_starts_with($notifyName, $consigneeName)) {
            $notifyExtraAddr = trim(substr($notifyName, strlen($consigneeName)));
            $notify = $consignee;
            Log::info('TFP: notificatario = consignatario + dirección pegada', [
                'notificatario_archivo' => $notifyName,
                'direccion_extraida' => $notifyExtraAddr,
            ]);
        } else {
            $notify = $this->findOrCreateClient($data['notificatario'] ?? 'Notificatario TFP', 'notify', $data['notificatario_ruc'] ?? null, $data['notificatario_domicilio'] ?? null);
        }

        $loadingPort = $this->findOrCreatePort($data['cod_puerto_carga'] ?? 'ARBAI');
        $dischargePort = $this->findOrCreatePort($data['cod_puerto_descarga'] ?? 'PYPSE');

        // El permiso de embarque viene en el OBS de los contenedores, repetido en
        // cada uno pero unico por conocimiento: verificado 07/08/2026 sobre
        // DTY260626N (184 conocimientos, 370 OBS con dato, ninguno con dos
        // permisos distintos). Formato "26001TRB3011733H". Se toma el primero
        // que lo declare. La columna es string(100).
        $permisoEmbarque = null;
        foreach ($containers as $c) {
            $obs = trim($c['obs'] ?? '');
            if ($obs !== '') {
                $permisoEmbarque = mb_substr($obs, 0, 100);
                break;
            }
        }

        $bill = BillOfLading::create([
            'shipment_id' => $shipment->id,
            'bill_number' => $data['bl_numero'],
            'bill_date' => now(),
            'loading_date' => now()->addDays(1),
            'shipper_id' => $shipper->id,
            'consignee_id' => $consignee->id,
            'notify_party_id' => $notify->id,
            'loading_port_id' => $loadingPort->id,
            'discharge_port_id' => $dischargePort->id,
            // Prioridad: campo TRB de la cabecera; si no viene, el OBS de los
            // contenedores (ver arriba). Este archivo lo trae solo en OBS.
            'permiso_embarque' => !empty($data['trb']) ? $data['trb'] : $permisoEmbarque,
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

        // Dirección del cliente: persistir en ficha (cliente nuevo/sin dirección)
        // o guardar dirección específica del conocimiento (cliente existente con dirección distinta).
        foreach ([
            ['client' => $shipper,   'addr' => $data['cargador_domicilio'] ?? null,      'role' => 'shipper'],
            ['client' => $consignee, 'addr' => $data['consignatario_domicilio'] ?? null, 'role' => 'consignee'],
            ['client' => $notify,    'addr' => $data['notificatario_domicilio'] ?? $notifyExtraAddr, 'role' => 'notify_party'],
        ] as $p) {
            $this->persistClientAddress($p['client'], $p['addr']);
            if ($c = $this->resolveSpecificAddress($p['client'], $p['addr'], $p['role'])) {
                $bill->specificContacts()->create($c);
            }
        }

        return $bill;
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
            'condition' => $this->mapTfpCondition($data['condicion'] ?? null)['condition'],
            'container_condition' => $this->mapTfpCondition($data['condicion'] ?? null)['container_condition'],
            'shipper_seal' => $data['nro_precinta'] ?? null,
            'operational_status' => 'loaded',
            'active' => true,
        ]);
    }

    /**
     * El campo CONDICION del formato TFP no tiene un unico significado: distintos
     * emisores lo usan para cosas distintas. Verificado 05/08/2026 contra archivos
     * reales en produccion:
     *   - "P" en contenedores 20DV con 23.529 kg y 23 bultos, o sea llenos: no puede
     *     ser "Parcial" del enum condition, es "muelle a muelle" del catalogo AFIP.
     *   - "H" (casa a casa, AFIP) rompia la importacion: no existe en condition.
     *   - "V" aparece en otros archivos y solo tiene sentido como vacio.
     *
     * Por eso no se elige un significado: cada valor va a la columna donde ese
     * valor es valido. container_condition es el que el serializador transmite a
     * AFIP como <condicion> (ver GuaranExcelParser L771).
     *
     * @return array{condition: string, container_condition: string}
     */
    protected function mapTfpCondition(?string $raw): array
    {
        $valor = strtoupper(trim((string) $raw));

        // Catalogo AFIP casa/muelle -> container_condition
        if (in_array($valor, ['H', 'P'], true)) {
            return ['condition' => 'L', 'container_condition' => $valor];
        }

        // Catalogo de estado del contenedor -> condition
        if (in_array($valor, ['V', 'D', 'S', 'L', 'R'], true)) {
            return ['condition' => $valor, 'container_condition' => 'P'];
        }

        // Desconocido o vacio: defaults de siempre, no se inventa nada.
        return ['condition' => 'L', 'container_condition' => 'P'];
    }

    protected function createShipmentItem(BillOfLading $bill, array $data, bool $hasContainers = false): ?ShipmentItem
    {
        $lineNumber = ShipmentItem::where('bill_of_lading_id', $bill->id)->max('line_number') ?? 0;
        $lineNumber++;

        return ShipmentItem::create([
            'bill_of_lading_id' => $bill->id,
            'line_number' => $lineNumber,
            'item_description' => $data['naturaleza_mercaderia'] ?? 'Mercadería general',
            // CANTTOTALBULTOS viene vacio en parte de los contenedores (50 de 119
            // en ASUNCION B, verificado 06/08/2026). extractValue devuelve '' y no
            // null en ese caso, asi que el ?? no se activaba e intval('') daba 0:
            // el item quedaba sin bultos y el total del conocimiento en cero.
            // Criterio de Roberto (05/08): sin bultos declarados, 1 por contenedor.
            // El total del conocimiento se recalcula desde los items, con lo cual
            // termina siendo la cantidad de contenedores, como el pidio.
            'package_quantity' => max(1, intval($data['cant_total_bultos'] ?? 0)),
            'gross_weight_kg' => floatval($data['peso_total_bultos'] ?? 0),
            'net_weight_kg' => floatval($data['peso_total_bultos'] ?? 0) * 0.95,
            'volume_m3' => floatval($data['volumen_total'] ?? 0),
            // Mismo criterio que el BL: CargoType 9 (CONTENEDORES) + Packaging 4 (CONTENEDOR) si hay contenedores.
            'cargo_type_id' => $hasContainers ? 9 : 1,
            'packaging_type_id' => $hasContainers ? 4 : 1,
            'commodity_code' => $data['cod_armonizado'] ?? null,
            'tariff_position' => $data['cod_armonizado'] ?: null,
            'created_by_user_id' => auth()->id()
        ]);
    }

    /**
     * Vincular un Container con un ShipmentItem en el pivote container_shipment_item.
     */
    protected function attachContainerToItem(Container $container, ShipmentItem $item, array $containerData = []): void
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
            // PESO y CANTIDAD del propio contenedor cuando el archivo los declara.
            // Antes se copiaban los totales del item a CADA contenedor: con dos
            // contenedores de 1100 y 1048 bultos, los dos quedaban en 2148 y la
            // validacion del formulario rechazaba la edicion porque la suma (4296)
            // no coincidia con el total del item (reportado por Roberto 07/08/2026).
            // Si el archivo no los trae, se cae al total del item como antes.
            'package_quantity' => isset($containerData['cantidad']) && $containerData['cantidad'] !== ''
                ? (int) floatval($containerData['cantidad'])
                : $item->package_quantity,
            'gross_weight_kg' => isset($containerData['peso']) && $containerData['peso'] !== ''
                ? floatval($containerData['peso'])
                : $item->gross_weight_kg,
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

    protected function findOrCreateClient(string $name, string $type, ?string $taxId = null, ?string $address = null): Client
    {
        $user = auth()->user();
        $companyId = $user->userable_type === 'App\Models\Company' ? $user->userable_id : 
                     ($user->userable->company_id ?? null);

        $name = trim($name);
        if (empty($name)) $name = 'Cliente TFP';

        // Prioridad: RUC declarado (CARGADORRUC/CONSIGNATARIORUC/NOTIFICATARIORUC) >
        // tax embebido en el nombre > tax embebido en el domicilio. No se fabrica.
        //
        // El domicilio se agrego el 07/08/2026: los campos *RUC vienen vacios en
        // buena parte de los archivos y el emisor escribe el identificador dentro
        // de *DOMICILIO ("RUC: 80094634-0 CALLE ROQUE CENTURION...", "CHILE 801
        // ... CUIT 30-69318494-7"). Sin mirarlo ahi se daba de alta un cliente
        // nuevo por cada importacion del mismo (reportado por Roberto 06/08).
        $normTaxId = $this->resolveTaxId($taxId, $name, $address);

        // 1) Buscar por tax_id real (si hay)
        if ($normTaxId) {
            $client = Client::where('tax_id', $normTaxId)->first();
            if ($client) return $client;
        }

        // 2) Buscar por nombre
        $client = Client::where('legal_name', $name)->first();
        if ($client) return $client;

        // País inferido por longitud del tax_id (regla QA 30/06, misma que Guaran). Solo afecta
        // clientes NUEVOS (los existentes ya retornaron arriba sin tocar su país).
        // 11 dígitos -> Argentina (11); 7-9 -> Paraguay (174). Sin tax o longitud atípica:
        // default Paraguay 174 (TFP es tráfico AR->PY) con warning para revisión, porque
        // country_id es NOT NULL y no hay columna de país en el archivo TFP.
        $countryId = 174;
        if ($normTaxId) {
            $taxLen = strlen($normTaxId);
            if ($taxLen === 11) {
                $countryId = 11;
                Log::info('TFP: pais inferido por tax_id', ['tax_id' => $normTaxId, 'len' => $taxLen, 'country_id' => 11, 'pais' => 'Argentina']);
            } elseif ($taxLen >= 7 && $taxLen <= 9) {
                $countryId = 174;
                Log::info('TFP: pais inferido por tax_id', ['tax_id' => $normTaxId, 'len' => $taxLen, 'country_id' => 174, 'pais' => 'Paraguay']);
            } else {
                Log::warning('TFP: longitud de tax_id atipica, pais default (revisar)', ['tax_id' => $normTaxId, 'len' => $taxLen, 'country_id' => $countryId]);
            }
        } else {
            Log::warning('TFP: cliente sin tax_id, pais NO confiable (revisar)', ['name' => $name, 'country_id' => $countryId]);
        }

        return Client::create([
            'tax_id' => $normTaxId,
            // Argentina(11)->CUIT(1), Paraguay(174)->RUC(3). El TIPO siempre corresponde al país.
            'country_id' => $countryId,
            'document_type_id' => $countryId === 11 ? 1 : 3,
            'legal_name' => $name,
            'commercial_name' => $name,
            'status' => 'active',
            'created_by_company_id' => $companyId,
            'verified_at' => now()
        ]);
    }

    /**
     * Resolver puerto por código. NUNCA auto-crea puertos (política del proyecto:
     * el catálogo tiene ~17.500; un código desconocido debe dar error claro).
     * Los generadores TFP usan códigos propios que no son UN/LOCODE; se mapean
     * con aliases verificados contra archivos reales (13/07/2026):
     *   ARBAI ("BUENOS AIRES") -> ARBUE | PYTV ("TERPORT-VILLETA") -> PYTVT
     *   PYSEF ("PUERTO SEGURO FLUVIAL") -> PYPSE (alta deliberada en catálogo)
     */
    protected function findOrCreatePort(string $code): Port
    {
        $code = strtoupper(trim($code));

        $aliases = [
            'ARBAI' => 'ARBUE',
            'PYTV'  => 'PYTVT',
            'PYSEF' => 'PYPSE',
            'PYTVI' => 'PYTVT',   // "TERPORT VILLETA" (verificado 06/08/2026 contra archivo VICKY B)
        ];
        $resolved = $aliases[$code] ?? $code;

        $port = Port::where('code', $resolved)->first();
        if ($port) {
            if ($resolved !== $code) {
                Log::info('TFP: puerto mapeado por alias', ['codigo_archivo' => $code, 'codigo_resuelto' => $resolved]);
            }
            return $port;
        }

        throw new Exception(
            "Puerto desconocido en el archivo TFP: '{$code}'. " .
            "No existe en el catálogo de puertos y no se crean puertos automáticamente. " .
            "Verifique el código o solicite el alta del puerto al administrador."
        );
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