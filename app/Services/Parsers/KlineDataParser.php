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
use App\Models\Vessel;
use App\Services\Parsers\Concerns\ExtractsEmbeddedTaxId;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Exception;
use App\Models\ManifestImport;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;


/**
 * PARSER PARA KLINE.DAT - VERSIÓN CORREGIDA FINAL
 * 
 * CORRECCIONES APLICADAS BASÁNDOSE EN PARANA EXITOSO:
 * ✅ Método groupByBillOfLading() corregido basándose en KlineParserService funcional
 * ✅ Campos obligatorios completados según migraciones verificadas
 * ✅ ManifestParseResult::failure() en lugar de throw Exception
 * ✅ Validaciones de duplicados que funcionan correctamente
 * ✅ company_id obtenido correctamente como PARANA
 * ✅ vessel_id pasado en $options obligatorio
 * ✅ Creación completa de todos los objetos (Voyage, BillOfLading, ShipmentItems)
 */
class KlineDataParser implements ManifestParserInterface
{
    use ExtractsEmbeddedTaxId;

    protected array $lines;
    protected array $stats = [
        'processed' => 0,
        'errors' => 0,
        'warnings' => [],
        'created_voyages' => 0,
        'created_shipments' => 0,
        'created_bills' => 0
    ];
    // Permitir alta automática de puertos faltantes
    protected bool $autoCreateMissingPorts = true;

    // Solo aceptamos UN/LOCODE cuyo prefijo (país) esté habilitado
    //protected array $allowedCountryAlpha2 = ['AR', 'PY', 'BR', 'UY'];


    /**
     * Verificar si puede parsear el archivo
     */
    public function canParse(string $filePath): bool
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        if (!in_array($extension, ['dat', 'txt'])) {
            return false;
        }

        if (!file_exists($filePath)) {
            return false;
        }

        $handle = fopen($filePath, 'r');
        if (!$handle) {
            return false;
        }

        $sampleLines = [];
        for ($i = 0; $i < 10 && !feof($handle); $i++) {
            $line = fgets($handle);
            if ($line !== false) {
                $sampleLines[] = trim($line);
            }
        }
        fclose($handle);

        // Buscar patrones KLine típicos
        foreach ($sampleLines as $line) {
            if (preg_match('/^(BLNOREC|GNRLREC|BLRFREC0|BOOKREC0|PTYIREC0|CMMDREC0|DESCREC0|MARKREC0|CARCREC0|FRTCREC0)/', $line)) {
                return true;
            }
        }

        return false;
    }

    // Crea (o busca) un puerto por UN/LOCODE. Aplica validaciones mínimas.
protected function findOrCreatePort(string $portCode, string $defaultName = null): Port
{
    $code = strtoupper(trim($portCode ?? ''));

    // 1) UN/LOCODE estricto: AA999
    if ($code === '' || !preg_match('/^[A-Z]{2}[A-Z0-9]{3}$/', $code)) {
        throw new \InvalidArgumentException("Código de puerto inválido (no UN/LOCODE): {$portCode}");
    }

    // 2) Prefijo de país habilitado (lista blanca)
    //$alpha2 = substr($code, 0, 2);
    //if (!in_array($alpha2, $this->allowedCountryAlpha2, true)) {
    //    throw new \DomainException("Código de puerto {$code} rechazado: país {$alpha2} no habilitado.");
    //}

    // 2) Verificar que el país existe en BD
    $alpha2 = substr($code, 0, 2);
    $countryExists = Country::whereRaw('UPPER(alpha2_code)=?', [$alpha2])->exists();
    if (!$countryExists) {
        throw new \DomainException("Código de puerto {$code} rechazado: país {$alpha2} no existe en base de datos.");
    }

    // 3) Si ya existe, usarlo
    if ($port = Port::where('code', $code)->first()) {
        return $port;
    }

    // 4) Si no existe, abortar con mensaje descriptivo.
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
     * Parsear archivo KLine.DAT - CORREGIDO: registrar importación
     */
    public function parse(string $filePath, array $options = []): ManifestParseResult
    {
        $startTime = microtime(true);
        
        try {
            $this->lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            
            Log::info('Starting KLine parse process', [
                'file_path' => $filePath,
                'total_lines' => count($this->lines),
                'vessel_id' => $options['vessel_id'] ?? 'no vessel_id provided'
            ]);

            // NUEVO: Crear registro de importación
            $importRecord = $this->createImportRecord($filePath, $options);

            return DB::transaction(function () use ($options, $importRecord, $startTime, $filePath) {
                // CORREGIDO: Agrupar líneas por BL usando lógica funcional de KlineParserService
                $bills = $this->groupByBillOfLading();
                
                if (empty($bills)) {
                    return ManifestParseResult::failure([
                        'No se encontraron Bills of Lading válidos en el archivo KLine'
                    ]);
                }

                // NUEVO: Verificar duplicados ANTES de procesar
                $duplicateCheck = $this->checkForDuplicateBills($bills);
                if ($duplicateCheck['all_duplicates']) {
                    return ManifestParseResult::failure([
                        'Este archivo ya fue importado anteriormente. Todos los conocimientos de embarque ya existen en el sistema.'
                    ], [], array_merge($this->stats, [
                        'duplicate_bills' => $duplicateCheck['existing_count'],
                        'total_bills' => count($bills),
                        'existing_bill_numbers' => array_slice($duplicateCheck['existing_numbers'], 0, 5)
                    ]));
                } elseif ($duplicateCheck['has_duplicates']) {
                    $this->stats['warnings'][] = "Se encontraron {$duplicateCheck['existing_count']} conocimientos duplicados que serán omitidos.";
                }

                // Usar el primer BL para crear voyage y shipment
                $firstBL = reset($bills);
                $portInfo = $this->extractPortInfo($firstBL['data'] ?? $data ?? []);

                // 🔒 Guard estricto: no continuar si falta alguno
                if (empty($portInfo['origin']) || empty($portInfo['destination'])) {
                    throw new \DomainException(
                        "No se detectaron ambos puertos (origen/destino) en el archivo KLine. " .
                        "Detectado -> origen: " . ($portInfo['origin'] ?? 'null') .
                        ", destino: " . ($portInfo['destination'] ?? 'null') . ". " .
                        "Revise que existan UN/LOCODE en líneas con contexto (POL/POD/PORT/LOADING/DISCHARGE/ORIGIN/DEST)."
                    );
                }

                $originPort      = $this->findOrCreatePort($portInfo['origin']);
                $destinationPort = $this->findOrCreatePort($portInfo['destination']);

                $dates      = $this->extractDates($firstBL['data']); // ← NUEVO
                $voyageInfo = $this->extractVoyageInfo($firstBL['data']);

                // Crear puertos
                $originPort = $this->findOrCreatePort($portInfo['origin'], 'Buenos Aires');
                $destinationPort = $this->findOrCreatePort($portInfo['destination'], 'Terminal Villeta');

                // CORREGIDO: Crear voyage usando $options
                $voyage = $this->createVoyage($voyageInfo, $originPort, $destinationPort, $options);
                
                // CORREGIDO: Crear shipment usando $options
                $shipment = $this->createShipment($voyage, $options);

                // Procesar cada BL
                $createdBills = [];
                $allItems = [];
                
                foreach ($bills as $blData) {
                    try {
                        // CORREGIDO: Verificar duplicado BL (ya verificado en batch, pero por seguridad)
                        $blNumber = $this->cleanBillNumber($blData['bl']);
                        $existingBL = BillOfLading::where('bill_number', $blNumber)->first();
                        
                        if ($existingBL) {
                            // Skip silenciosamente, ya fue reportado en el check inicial
                            continue;
                        }

                        // Crear BillOfLading
                        $bill = $this->createBillOfLading($shipment, $blNumber, $blData['data'], $originPort, $destinationPort);
                        $createdBills[] = $bill;

                        // CORREGIDO: Crear ShipmentItems con campos obligatorios
                        $items = $this->createShipmentItems($bill, $blData['data']);
                        $allItems = array_merge($allItems, $items);

                        $this->stats['created_bills']++;
                        
                    } catch (Exception $e) {
                        $this->stats['errors']++;
                        $this->stats['warnings'][] = "Error procesando BL {$blData['bl']}: " . $e->getMessage();
                        Log::error('Error processing BL', [
                            'bl' => $blData['bl'],
                            'error' => $e->getMessage()
                        ]);
                    }
                }

                // CORREGIDO: Verificar que se crearon objetos
                if (empty($createdBills)) {
                    return ManifestParseResult::failure([
                        'No se pudo crear ningún Bill of Lading del archivo KLine'
                    ], $this->stats['warnings'], $this->stats);
                }

                Log::info('KLine parsing completed successfully', [
                    'voyage_id' => $voyage->id,
                    'bills_created' => count($createdBills),
                    'items_created' => count($allItems)
                ]);

                // NUEVO: Registrar objetos creados y completar importación
                $this->completeImportRecord($importRecord, $voyage, $createdBills, $allItems, [], $startTime);

                return ManifestParseResult::success(
                    voyage: $voyage,
                    shipments: [$shipment],
                    containers: [], // KLine DAT no maneja contenedores típicamente
                    billsOfLading: $createdBills,
                    statistics: array_merge($this->stats, [
                        'processed_items' => count($allItems),
                        'total_bills' => count($createdBills),
                        'import_id' => $importRecord->id // Agregar ID del registro
                    ])
                );
            });

        } catch (Exception $e) {
            Log::error('Critical error in KLine parser', [
                'file_path' => $filePath,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // NUEVO: Marcar importación como fallida
            if (isset($importRecord)) {
                $processingTime = microtime(true) - $startTime;
                $importRecord->markAsFailed([$e->getMessage()], [
                    'processing_time_seconds' => round($processingTime, 2),
                    'errors_count' => 1
                ]);
            }

            // CORREGIDO: Retornar ManifestParseResult::failure en lugar de throw
            return ManifestParseResult::failure([
                'Error al procesar archivo KLine: ' . $e->getMessage()
            ], [], $this->stats);
        }
    }

    /**
     * Agrupar líneas por Bill of Lading - CORREGIDO: usar lógica del KlineParserService funcional
     */
    protected function groupByBillOfLading(): array
    {
        $records = [];
        $currentBl = null;
        $currentData = [];

        foreach ($this->lines as $lineNumber => $line) {
            if (strlen($line) < 8) {
                continue; // Skip lines that are too short
            }

            $type = trim(substr($line, 0, 8));
            $content = trim(substr($line, 8));

            Log::debug("Processing line {$lineNumber}", [
                'type' => $type,
                'content' => substr($content, 0, 50) . (strlen($content) > 50 ? '...' : '')
            ]);

            // CORREGIDO: usar Str::startsWith como en KlineParserService funcional
            if (Str::startsWith($type, 'BLNOREC')) {
                if ($currentBl) {
                    $records[] = ['bl' => $currentBl, 'data' => $currentData];
                    $currentData = [];
                }
                // FIX bug #2: BLNOREC no tiene seq number; el BL empieza en pos 7.
                // Los tipos K-Line son de 7 chars; el resto del parser usa offset 8
                // deliberadamente para sub-clasificar por primer dígito del seq.
                $blContent = trim(substr($line, 7));
                $currentBl = $this->cleanBillNumber($blContent);
            }

            // CORREGIDO: agregar datos solo si hay un BL actual
            if ($currentBl) {
                $currentData[$type][] = $content;
            }
        }

        // Guardar último BL
        if ($currentBl) {
            $records[] = ['bl' => $currentBl, 'data' => $currentData];
        }

        Log::info('Grouped records', ['total_bills' => count($records)]);
        return $records;
    }

    /**
     * Limpiar y truncar bill_number - NUEVO
     */
    protected function cleanBillNumber(string $rawBillNumber): string
    {
        // 1. Limpiar espacios extras y caracteres de control
        $cleaned = trim(preg_replace('/\s+/', ' ', $rawBillNumber));
        
        // 2. Extraer solo la parte del número del B/L
        if (preg_match('/^([A-Z0-9\-\/]+)/', $cleaned, $matches)) {
            $billNumber = $matches[1];
        } else {
            // Fallback: tomar primeros 20 caracteres alfanuméricos
            $billNumber = preg_replace('/[^A-Z0-9\-\/]/', '', substr($cleaned, 0, 20));
        }
        
        // 3. Asegurar que no esté vacío
        if (empty($billNumber)) {
            $billNumber = 'KLINE_' . uniqid();
        }
        
        // 4. Truncar a 50 caracteres máximo (límite BD)
        return substr($billNumber, 0, 50);
    }

    /**
     * Crear voyage - CORREGIDO: como PARANA
     */
    protected function createVoyage(array $voyageInfo, Port $originPort, Port $destinationPort, array $options = []): Voyage
    {
        // CORREGIDO: Obtener company_id como PARANA
        $user = auth()->user();
        if ($user->company_id) {
            $companyId = $user->company_id;
        } elseif ($user->userable_type === 'App\Models\Company' && $user->userable_id) {
            $companyId = (int) $user->userable_id;
        } else {
            throw new Exception("Usuario no tiene empresa asignada. User ID: {$user->id}");
        }

        // CORREGIDO: Usar vessel seleccionado como PARANA
        $vesselId = $options['vessel_id'] ?? null;
        if (!$vesselId) {
            throw new Exception("vessel_id es obligatorio para crear voyage");
        }

        $vessel = Vessel::find($vesselId);
        if (!$vessel) {
            throw new Exception("Vessel con ID {$vesselId} no encontrado");
        }

        $voyageNumber = 'KLINE-' . ($voyageInfo['voyage_number'] ?? date('YmdHis'));

        // CORREGIDO: Verificar duplicado voyage sin throw Exception
        $existingVoyage = Voyage::where('voyage_number', $voyageNumber)
            ->where('company_id', $companyId)
            ->first();

        if ($existingVoyage) {
            Log::info('Voyage ya existe, reutilizando', ['voyage_id' => $existingVoyage->id]);
            return $existingVoyage;
        }

        // Fechas estimadas desde el .DAT (si existen) o fallback
        $dates = $this->extractDates($firstBL['data'] ?? $data ?? []); // si ya lo tenés, reutilizalo

        // Fechas estimadas: se pueden pasar por $options['dates']; si no, defaults
        // Esperado: $options['dates'] = ['etd' => 'YYYY-MM-DD', 'eta' => 'YYYY-MM-DD']
        $optDates = $options['dates'] ?? [];
        $etd = !empty($optDates['etd']) ? Carbon::parse($optDates['etd']) : Carbon::now()->addDays(7);
        $eta = !empty($optDates['eta']) ? Carbon::parse($optDates['eta']) : (clone $etd)->addDays(7);
        ;


        $voyageData = [
            'company_id' => $companyId,
            'voyage_number' => $voyageNumber,
            'origin_port_id' => $originPort->id,
            'destination_port_id' => $destinationPort->id,
            'lead_vessel_id' => $vessel->id,
            'origin_country_id' => $originPort->country_id,
            'destination_country_id' => $destinationPort->country_id,
            'voyage_type' => 'single_vessel',
            'cargo_type' => 'export',
            'status' => 'planning',
            'created_by_user_id' => $user->id,
        ];

        // salida (ETD)
        if (Schema::hasColumn('voyages', 'estimated_departure_date')) {
            $voyageData['estimated_departure_date'] = $etd;
        } elseif (Schema::hasColumn('voyages', 'departure_date')) {
            $voyageData['departure_date'] = $etd;
        } elseif (Schema::hasColumn('voyages', 'estimated_departure_at')) {
            $voyageData['estimated_departure_at'] = $etd;
        } elseif (Schema::hasColumn('voyages', 'departure_at')) {
            $voyageData['departure_at'] = $etd;
        }

        // llegada (ETA)  ← tu error venía por NO setear estimated_arrival_date
        if (Schema::hasColumn('voyages', 'estimated_arrival_date')) {
            $voyageData['estimated_arrival_date'] = $eta;
        } elseif (Schema::hasColumn('voyages', 'arrival_date')) {
            $voyageData['arrival_date'] = $eta;
        } elseif (Schema::hasColumn('voyages', 'estimated_arrival_at')) {
            $voyageData['estimated_arrival_at'] = $eta;
        } elseif (Schema::hasColumn('voyages', 'arrival_at')) {
            $voyageData['arrival_at'] = $eta;
        }

        $voyage = Voyage::create($voyageData);


        $this->stats['created_voyages']++;
        return $voyage;
    }
    

    /**
     * Crear shipment - CORREGIDO: como PARANA
     */
    protected function createShipment(Voyage $voyage, array $options = []): Shipment
    {
        $vesselId = $options['vessel_id'] ?? null;
        if (!$vesselId) {
            throw new Exception("vessel_id es obligatorio para crear shipment");
        }

        $vessel = Vessel::find($vesselId);
        if (!$vessel) {
            throw new Exception("Vessel con ID {$vesselId} no encontrado");
        }

        $shipment = Shipment::create([
            'voyage_id' => $voyage->id,
            'vessel_id' => $vessel->id,
            'shipment_number' => 'KLINE-SHIP-' . now()->format('YmdHis'),
            'sequence_in_voyage' => 1,
            'vessel_role' => 'single',
            'cargo_capacity_tons' => $vessel->cargo_capacity_tons ?? 1000.0,
            'container_capacity' => $vessel->container_capacity ?? 0,
            'status' => 'planning',
            'active' => true,
            'created_by_user_id' => auth()->id()
        ]);

        $this->stats['created_shipments']++;
        return $shipment;
    }

    /**
     * Crear bill of lading - CORREGIDO: campos obligatorios verificados
     */
    protected function createBillOfLading(
            Shipment $shipment,
            string $blNumber,
            array $data,
            Port $originPort,
            Port $destinationPort
        ): BillOfLading
    {
        // 1) company_id (antes de usarlo en clientes)
        $companyId =
            ($shipment->company_id ?? null)
            ?? ($shipment->created_by_company_id ?? null)
            ?? (!empty($shipment->voyage_id) ? (int) \App\Models\Voyage::whereKey($shipment->voyage_id)->value('company_id') : null)
            ?? (auth()->user()->company_id ?? null);

        if (!$companyId) {
            throw new \DomainException("No puedo determinar company_id para el BL {$blNumber}.");
        }

        // 2) Partes (líneas + datos mínimos)
        [$shipperLines, $consigneeLines] = $this->extractPartyLinesFromPTYI($data);
        $shipperData   = $this->buildClientDataFromLines($shipperLines);
        $consigneeData = $this->buildClientDataFromLines($consigneeLines);

        // 3) Fechas + flete
        $dates        = $this->extractDates($data);                // ['etd','eta','bl_date']
        $freightTerms = $this->extractFreightTerms($data);         // 'prepaid'|'collect'|default
        $freight      = $this->extractFreightCharges($data, $freightTerms); // ['terms','currency','amount']

        // 4) Clientes (ORIGEN → shipper, DESTINO → consignee)
        $shipper   = $this->findOrCreateClient($shipperData,   $companyId, $shipperLines,   $originPort);
        $consignee = $this->findOrCreateClient($consigneeData, $companyId, $consigneeLines, $destinationPort);

        // 5) Atributos de flete (solo si existen columnas)
        $freightAttrs = [];
        if (\Schema::hasColumn('bills_of_lading', 'freight_terms') && ($freight['terms'] ?? null)) {
            $freightAttrs['freight_terms'] = $freight['terms'];
        }
        if (\Schema::hasColumn('bills_of_lading', 'freight_currency_code') && ($freight['currency'] ?? null)) {
            $freightAttrs['freight_currency_code'] = $freight['currency'];
        }
        if (\Schema::hasColumn('bills_of_lading', 'freight_amount') && array_key_exists('amount', $freight) && $freight['amount'] !== null) {
            $freightAttrs['freight_amount'] = $freight['amount'];
        }

        // 6) Campos base del BL (seguros)
        $blAttrs = [
            'shipment_id'       => $shipment->id,
            'bill_number'       => $blNumber,
            'bill_date'         => $dates['bl_date'] ?? now(),
            'loading_date'      => $dates['etd'] ?? now()->addDays(1),
            // FIX bug #5: usar descripción real extraída de DESCREC en lugar de leyenda fija
            'cargo_description' => implode(' / ', $this->extractCargoDescriptions($data)) ?: 'Mercadería según manifiesto KLine',
            'status'            => 'draft',
            'master_bl_number'  => $this->extractMasterBL($data),
            // FIX bugs #3, #4: cargo y packaging correctos para K-Line (carga no contenedorizada)
            'primary_cargo_type_id'     => 5,  // OTRA CARGA NO CONTENEDORIZADA
            'primary_packaging_type_id' => 2,  // NO RETORNABLE
            // FIX bug #6b: BL no estaba recibiendo cargo_marks (quedaba vacío en BD)
            'cargo_marks'               => $this->extractCargoMarks($data),
            // FIX QA #5: sincronizar totales del BL desde los datos reales del archivo
            // (antes quedaban en 0/1 hardcoded aunque los items sí tuvieran datos)
            'gross_weight_kg'   => ($blMeasurements = $this->extractRealMeasurements($data))['gross_weight_kg'],
            'net_weight_kg'     => $blMeasurements['net_weight_kg'],
            'total_packages'    => $blMeasurements['package_quantity'],
            'volume_m3'         => $blMeasurements['volume_m3'],
            'created_by_user_id'=> auth()->id(),
        ];

        // 7) Relaciones SOLO si existen columnas en tu tabla
        if (\Schema::hasColumn('bills_of_lading', 'shipper_id')) {
            $blAttrs['shipper_id'] = $shipper->id;
        } elseif (\Schema::hasColumn('bills_of_lading', 'shipper_client_id')) {
            $blAttrs['shipper_client_id'] = $shipper->id;
        }

        if (\Schema::hasColumn('bills_of_lading', 'consignee_id')) {
            $blAttrs['consignee_id'] = $consignee->id;
        } elseif (\Schema::hasColumn('bills_of_lading', 'consignee_client_id')) {
            $blAttrs['consignee_client_id'] = $consignee->id;
        }

        if (\Schema::hasColumn('bills_of_lading', 'loading_port_id')) {
            $blAttrs['loading_port_id'] = $originPort->id;
        } elseif (\Schema::hasColumn('bills_of_lading', 'origin_port_id')) {
            $blAttrs['origin_port_id'] = $originPort->id;
        }

        if (\Schema::hasColumn('bills_of_lading', 'discharge_port_id')) {
            $blAttrs['discharge_port_id'] = $destinationPort->id;
        } elseif (\Schema::hasColumn('bills_of_lading', 'destination_port_id')) {
            $blAttrs['destination_port_id'] = $destinationPort->id;
        }

        // 8) Crear BL (merge correcto con flete)
        return BillOfLading::create(array_merge($blAttrs, $freightAttrs));
    }



    /**
     * Crear ShipmentItems - CORREGIDO: usar bill_of_lading_id y campos obligatorios
     */
    protected function createShipmentItems(BillOfLading $bill, array $data): array
    {
        $items = [];
        $lineNumber = 1;

        // Extraer descripciones de carga de los registros KLine
        $descriptions = $this->extractCargoDescriptions($data);
        
        if (empty($descriptions)) {
            // Crear al menos un item por defecto
            $descriptions = ['Mercadería general según KLine DAT'];
        }

        foreach ($descriptions as $description) {
            // CORREGIDO: Verificar duplicado line_number sin throw Exception
            $existingItem = ShipmentItem::where('bill_of_lading_id', $bill->id)
                                      ->where('line_number', $lineNumber)
                                      ->first();
            
            if ($existingItem) {
                $this->stats['warnings'][] = "Line number {$lineNumber} ya existe en BL {$bill->bill_number}";
                $lineNumber++;
                continue;
            }

            // Extraer información REAL del archivo
            $cargoMarks = $this->extractCargoMarks($data);
            $ncmCode = $this->extractNCMCode($data);
            $realMeasurements = $this->extractRealMeasurements($data); // NUEVO
            $countryOfOrigin = $this->extractCountryOfOrigin($data); // NUEVO

            $item = ShipmentItem::create([
                'bill_of_lading_id' => $bill->id,
                'line_number' => $lineNumber,
                'item_description' => $description,
                // FIX bugs #3, #4: K-Line no contenedorizado
                'cargo_type_id' => 5,     // OTRA CARGA NO CONTENEDORIZADA
                'packaging_type_id' => 2, // NO RETORNABLE
                'package_quantity' => $realMeasurements['package_quantity'], // REAL
                'gross_weight_kg' => $realMeasurements['gross_weight_kg'], // REAL
                'net_weight_kg' => $realMeasurements['net_weight_kg'], // REAL
                'volume_m3' => $realMeasurements['volume_m3'], // REAL
                'declared_value' => null, // No disponible en archivo
                'currency_code' => 'USD', // Por defecto, cambiar si se encuentra
                'commodity_code' => $ncmCode ?: null, // REAL o null
                'country_of_origin' => $countryOfOrigin, // REAL
                'cargo_marks' => $cargoMarks, // REAL
                'unit_of_measure' => 'PCS', // TODO: Extraer real
                'status' => 'draft',
                'created_by_user_id' => auth()->id()
            ]);
            
            $items[] = $item;
            $lineNumber++;
        }

        Log::info('ShipmentItems creados', [
            'bill_id' => $bill->id,
            'items_count' => count($items)
        ]);

        return $items;
    }



    // Detecta país desde las líneas del party (Shipper/Consignee) y/o un puerto "probable"
    protected function detectCountryIdFromParty(array $partyLines, ?Port $likelyPort = null): ?int
    {
        $text = strtoupper(implode(' ', array_map('strval', $partyLines)));

        // Palabras clave frecuentes
        $map = [
            'ARGENTINA' => 'AR', 'PARAGUAY' => 'PY',
            'BRASIL'    => 'BR', 'URUGUAY'  => 'UY',
            'ARG.'      => 'AR', 'PAR.'     => 'PY',
            'BRA.'      => 'BR', 'URU.'     => 'UY',
        ];
        foreach ($map as $needle => $alpha2) {
            if (str_contains($text, $needle)) {
                return \App\Models\Country::whereRaw('UPPER(alpha2_code)=?', [$alpha2])->value('id');
            }
        }

        // CUIT (AR) como pista
        if (preg_match('/\b\d{2}-?\d{8}-?\d\b/', $text)) {
            return \App\Models\Country::whereRaw('UPPER(alpha2_code)=?', ['AR'])->value('id');
        }

        // Heurística suave: país del puerto más probable (si existe)
        if ($likelyPort && \Schema::hasColumn('ports','country_id')) {
            return $likelyPort->country_id ?: null;
        }

        return null;
    }


    /**
     * Crear registro de importación - NUEVO
     */
    protected function createImportRecord(string $filePath, array $options = []): ManifestImport
    {
        $user = auth()->user();
        if (!$user) {
            throw new \Exception('Usuario no autenticado para crear registro de importación');
        }
        
        $fileName = basename($filePath);
        $fileSize = file_exists($filePath) ? filesize($filePath) : null;
        $fileHash = file_exists($filePath) ? ManifestImport::generateFileHash($filePath) : null;
        
        // Verificar archivo duplicado
        if ($fileHash) {
            $companyId = $user->userable_type === 'App\Models\Company' ? $user->userable_id : null;
            if ($companyId) {
                $existingImport = ManifestImport::isFileAlreadyImported($fileHash, $companyId);
                if ($existingImport) {
                    throw new \Exception("Este archivo ya fue importado anteriormente (ID: {$existingImport->id})");
                }
            }
        }
        
        $companyId = $user->userable_type === 'App\Models\Company' ? $user->userable_id : null;
        
        return ManifestImport::createForImport([
            'company_id' => $companyId,
            'user_id' => $user->id,
            'file_name' => $fileName,
            'file_format' => 'kline',
            'file_size_bytes' => $fileSize,
            'file_hash' => $fileHash,
            'parser_config' => [
                'parser_class' => self::class,
                'options' => $options,
                'vessel_id' => $options['vessel_id'] ?? null
            ]
        ]);
    }

    /**
     * Completar registro de importación - NUEVO
     */
    protected function completeImportRecord(
        ManifestImport $importRecord, 
        Voyage $voyage, 
        array $bills, 
        array $items,
        array $containers,
        float $startTime
    ): void {
        $processingTime = microtime(true) - $startTime;
        
        // Registrar IDs de objetos creados
        $createdObjects = [
            'voyages' => [$voyage->id],
            'shipments' => [$voyage->shipments()->first()->id ?? null],
            'bills' => array_map(fn($bill) => $bill->id, $bills),
            'items' => array_map(fn($item) => $item->id, $items),
            'containers' => array_map(fn($container) => $container->id, $containers)
        ];
        
        // Filtrar nulls
        $createdObjects = array_map(fn($ids) => array_filter($ids), $createdObjects);
        
        $importRecord->recordCreatedObjects($createdObjects);
        $importRecord->markAsCompleted([
            'voyage_id' => $voyage->id,
            'processing_time_seconds' => round($processingTime, 2),
            'notes' => 'Importación KLine DAT completada exitosamente'
        ]);
        
        Log::info('KLine import record completed', [
            'import_id' => $importRecord->id,
            'processing_time' => round($processingTime, 2) . 's'
        ]);
    }

    /**
     * Extraer información del viaje
     */
    protected function extractVoyageInfo(array $data): array
    {
        $voyageInfo = [
            'voyage_number' => null,
            'vessel_name' => null,
            'voyage_ref' => null
        ];

        $voyageRecords = ['VOYGREC0', 'VESSELREC', 'VOYREC0', 'SHIPREC0', 'VSLREC0'];
        
        foreach ($voyageRecords as $recordType) {
            if (!empty($data[$recordType])) {
                foreach ($data[$recordType] as $line) {
                    if (preg_match('/^([A-Z0-9\-\/]+)\s*(.*)$/i', trim($line), $matches)) {
                        if (!$voyageInfo['voyage_ref']) {
                            $voyageInfo['voyage_ref'] = $matches[1];
                        }
                        if (!$voyageInfo['vessel_name'] && !empty(trim($matches[2]))) {
                            $voyageInfo['vessel_name'] = trim($matches[2]);
                        }
                    }
                }
            }
        }

        if ($voyageInfo['voyage_ref']) {
            $voyageInfo['voyage_number'] = $voyageInfo['voyage_ref'];
        } else {
            $voyageInfo['voyage_number'] = date('YmdHis');
        }

        return $voyageInfo;
    }

    /**
     * Extraer información de clientes - CORREGIDO: usar patrones KLine estándar genéricos
     */
    protected function extractClientInfo(array $data): array
    {
        $clientInfo = [
            'shipper' => ['name' => 'Embarcador Desconocido', 'tax_id' => null], // AGREGAR tax_id
            'consignee' => ['name' => 'Consignatario Desconocido', 'tax_id' => null] // AGREGAR tax_id
        ];

        // CORREGIDO: Buscar en registros PTYIREC usando códigos estándar KLine
        if (!empty($data['PTYIREC0'])) {
            foreach ($data['PTYIREC0'] as $line) {
                $cleanLine = trim($line);
                
                // PATRÓN GENÉRICO: PTYIREC000XSH para Shipper
                // FIX bug #1: K-Line pega el tax_id al código SH/CN sin espacios (ej: "0001SHCO2325138    NOMBRE..."). \S*\s+ consume el tax_id pegado antes del nombre.
                if (preg_match('/^(\d+)SH\S*\s+(.+)$/', $cleanLine, $matches)) {
                    $shipperName = $this->extractCompanyNameFromLine($matches[2]);
                    $shipperTaxId = $this->extractTaxIdFromLine($matches[2]); // AGREGAR
                    if ($shipperName) {
                        $clientInfo['shipper']['name'] = $shipperName;
                        $clientInfo['shipper']['tax_id'] = $shipperTaxId; // AGREGAR
                    }
                }
                // PATRÓN GENÉRICO: PTYIREC000XCN para Consignee
                elseif (preg_match('/^(\d+)CN\S*\s+(.+)$/', $cleanLine, $matches)) {
                    $consigneeName = $this->extractCompanyNameFromLine($matches[2]);
                    $consigneeTaxId = $this->extractTaxIdFromLine($matches[2]); // AGREGAR
                    if ($consigneeName) {
                        $clientInfo['consignee']['name'] = $consigneeName;
                        $clientInfo['consignee']['tax_id'] = $consigneeTaxId; // AGREGAR
                    }
                }
            }
        }

        // FALLBACK: Si no encontramos en PTYIREC0, buscar en otros registros
        if ($clientInfo['shipper']['name'] === 'Embarcador Desconocido' || 
            $clientInfo['consignee']['name'] === 'Consignatario Desconocido') {
            
            $fallbackRecords = ['PTYIREC1', 'PTYIREC2', 'PTYIREC3', 'SHPREC0', 'CONSREC0'];
            
            foreach ($fallbackRecords as $recordType) {
                if (!empty($data[$recordType])) {
                    foreach ($data[$recordType] as $line) {
                        $cleanLine = trim($line);
                        
                        // FIX bug #1: \S*\s+ consume tax_id pegado al código
                        if (preg_match('/^(\d+)SH\S*\s+(.+)$/', $cleanLine, $matches)) {
                            $shipperName = $this->extractCompanyNameFromLine($matches[2]);
                            $shipperTaxId = $this->extractTaxIdFromLine($matches[2]); // NUEVO
                            if ($shipperName) {
                                $clientInfo['shipper']['name'] = $shipperName;
                                $clientInfo['shipper']['tax_id'] = $shipperTaxId; // NUEVO
                            }
                        }
                        // PATRÓN GENÉRICO: PTYIREC000XCN para Consignee  
                        elseif (preg_match('/^(\d+)CN\S*\s+(.+)$/', $cleanLine, $matches)) {
                            $consigneeName = $this->extractCompanyNameFromLine($matches[2]);
                            $consigneeTaxId = $this->extractTaxIdFromLine($matches[2]); // NUEVO
                            if ($consigneeName) {
                                $clientInfo['consignee']['name'] = $consigneeName;
                                $clientInfo['consignee']['tax_id'] = $consigneeTaxId; // NUEVO
                            }
                        }
                    }
                }
            }
        }
        
        Log::info('Información de clientes extraída de KLine', [
            'shipper' => $clientInfo['shipper']['name'],
            'consignee' => $clientInfo['consignee']['name']
        ]);
        
        return $clientInfo;
    }

    /**
     * Extraer nombre de empresa desde línea KLine - CORREGIDO: separar RUC/CUIT
     */
    protected function extractCompanyNameFromLine(string $line): ?string
    {
        $cleanLine = trim($line);
        
        if (strlen($cleanLine) < 3) {
            return null;
        }
        
        // CORREGIDO: Buscar el nombre de la empresa (antes de NIT/CUIT/RUC)
        if (preg_match('/^(.+?)\s+(?:NIT[:\s]|CUIT[:\s]|CNPJ[:\s]|RUC[:\s]|,|$)/', $cleanLine, $matches)) {
            $companyName = trim($matches[1]);
        } else {
            // Si no hay patrón específico, tomar hasta el primer grupo de espacios largos
            $parts = preg_split('/\s{3,}/', $cleanLine, 2);
            $companyName = trim($parts[0]);
        }
        
        // Validar que parece un nombre de empresa válido
        if (strlen($companyName) < 3 || strlen($companyName) > 100) {
            return null;
        }
        
        // Limpiar caracteres extraños manteniendo acentos y caracteres especiales de empresas
        $companyName = preg_replace('/[^\p{L}\p{N}\s\.\&\,\-\/\(\)]/u', ' ', $companyName);
        $companyName = trim(preg_replace('/\s+/', ' ', $companyName));
        
        return $companyName ?: null;
    }

    /**
     * Extraer RUC/CUIT desde línea KLine - NUEVO método
     */
    protected function extractTaxIdFromLine(string $line): ?string
    {
        // Delega en el helper común (cubre NIT/CUIT/CUIT NBR/CNPJ/RUC/TAX ID,
        // normaliza a solo dígitos y rechaza ceros). Se aplica sobre el segmento
        // de la parte ya aislado por la lógica posicional de Kline, no sobre el archivo.
        return $this->extractEmbeddedTaxId($line);
    }

    /**
     * Extraer marcas de carga - NUEVO método para manejar MARKREC correctamente
     */
    protected function extractCargoMarks(array $data): string
    {
        $marks = [];
        
        if (!empty($data['MARKREC0'])) {
            foreach ($data['MARKREC0'] as $line) {
                $cleanLine = trim($line);
                // Saltar líneas vacías o que solo tienen espacios
                if (strlen($cleanLine) > 5) { // Al menos algo de contenido
                    // Extraer solo la parte útil, saltar códigos HS redundantes
                    if (!str_contains($cleanLine, 'HS CODE:') && !str_contains($cleanLine, 'NCM:')) {
                        $marks[] = $cleanLine;
                    }
                }
            }
        }
        
        // FIX bug #6a: si no hay marcas útiles, retornar "SM" (sin barra, como pidió Roberto)
        if (empty($marks)) {
            return 'SM';
        }
        
        // Unir marcas encontradas
        $marksText = implode(' / ', array_unique($marks));
        
        // FIX bug #6a: si las marcas son solo códigos técnicos, también "SM"
        if (strlen($marksText) < 5 || 
            str_contains($marksText, 'HS CODE') || 
            str_contains($marksText, 'NCM')) {
            return 'SM';
        }
        
        return $marksText;
    }

    /**
     * Extraer código NCM - NUEVO método para capturar de múltiples registros
     */
    protected function extractNCMCode(array $data): ?string
    {
        $ncmPatterns = [
            // Patrón en DESCREC: "NCM: 87.04.3190"
            '/NCM[:\s]+([0-9]{2}\.?[0-9]{2}\.?[0-9]{2}\.?[0-9]{2})/',
            // Patrón en DESCREC: "HS CODE: 87.03.22"  
            '/HS\s+CODE[:\s]+([0-9]{2}\.?[0-9]{2}\.?[0-9]{2})/',
            // Patrón en MARKREC: "HS CODE: 87.03.22"
            '/HS\s+CODE[:\s]+([0-9]{2}\.?[0-9]{2}\.?[0-9]{2})/',
            // Patrón en CMMDREC al final: "87032100"
            '/([0-9]{8})$/',
        ];
        
        // Buscar en registros de descripción primero
        $searchRecords = ['DESCREC0', 'MARKREC0', 'CMMDREC0'];
        
        foreach ($searchRecords as $recordType) {
            if (!empty($data[$recordType])) {
                foreach ($data[$recordType] as $line) {
                    $cleanLine = trim($line);
                    
                    foreach ($ncmPatterns as $pattern) {
                        if (preg_match($pattern, $cleanLine, $matches)) {
                            $ncmCode = str_replace('.', '', $matches[1]); // Remover puntos
                            
                            // Validar formato NCM (8 dígitos)
                            if (preg_match('/^[0-9]{8}$/', $ncmCode)) {
                                return $ncmCode;
                            }
                            
                            // Si es HS Code más corto, completar con ceros
                            if (preg_match('/^[0-9]{6}$/', $ncmCode)) {
                                return $ncmCode . '00';
                            }
                        }
                    }
                }
            }
        }
        
        return null;
    }

    /**
     * Extraer Master Bill of Lading - NUEVO método para identificar MBL
     */
    protected function extractMasterBL(array $data): ?string
    {
        // Buscar en BLRFREC (BL Reference Record)
        if (!empty($data['BLRFREC0'])) {
            foreach ($data['BLRFREC0'] as $line) {
                $cleanLine = trim($line);
                
                // Extraer código después de BN
                if (preg_match('/^BN(.+)$/', $cleanLine, $matches)) {
                    $mbl = trim($matches[1]);
                    if (strlen($mbl) > 3) {
                        return $mbl;
                    }
                }
            }
        }
        
        // Buscar en BOOKREC (Booking Record) como alternativa
        if (!empty($data['BOOKREC0'])) {
            foreach ($data['BOOKREC0'] as $line) {
                $cleanLine = trim($line);
                
                // Tomar el código completo del booking
                if (strlen($cleanLine) > 3) {
                    return $cleanLine;
                }
            }
        }
        
        return null;
    }
   
    /**
     * Extraer datos reales de peso y medidas - CRÍTICO para aduana
     */
    protected function extractRealMeasurements(array $data): array
    {
        $measurements = [
            'package_quantity' => 1,
            'gross_weight_kg' => 0.0,
            'net_weight_kg' => 0.0,
            'volume_m3' => 0.0
        ];
        
        // Extraer de CMMDREC (datos principales)
        if (!empty($data['CMMDREC0'])) {
            foreach ($data['CMMDREC0'] as $line) {
                // Patrón: NAUT00000572VEHICLES...06661940000KGS006743880M3
                if (preg_match('/NAUT(\d+).*?(\d+)KGS(\d+)M3/', $line, $matches)) {
                    $measurements['package_quantity'] = intval(ltrim($matches[1], '0')) ?: 1;
                    // FIX bug #7: K-Line EDI usa 4 decimales fijos para peso (no 3)
                    // Archivo CMMDREC: "00595580000KGS" = 59558.0000 KG
                    $measurements['gross_weight_kg'] = floatval($matches[2]) / 10000;
                    // FIX escala volumen: K-Line EDI usa 3 decimales fijos (no 6)
                    // Archivo CMMDREC: "000580070M3" = 580.070 M3
                    $measurements['volume_m3'] = floatval($matches[3]) / 1000;
                }
            }
        }
        
        // Extraer peso neto de DESCREC
        if (!empty($data['DESCREC0'])) {
            foreach ($data['DESCREC0'] as $line) {
                // Patrón: "NET WEIGHT: 666.194,00 KGS"
                if (preg_match('/NET WEIGHT[:\s]+([0-9\.,]+)\s*KGS/i', $line, $matches)) {
                    $netWeight = str_replace(['.', ','], ['', '.'], $matches[1]);
                    $measurements['net_weight_kg'] = floatval($netWeight);
                }
                
                // Patrón: "M3: 6.743,88"
                if (preg_match('/M3[:\s]+([0-9\.,]+)/i', $line, $matches)) {
                    $volume = str_replace(',', '.', $matches[1]);
                    $measurements['volume_m3'] = floatval($volume);
                }
            }
        }
        
        return $measurements;
    }

    /**
     * Extraer país de origen REAL - CRÍTICO para aduana
     */
    protected function extractCountryOfOrigin(array $data): string
    {
        // Buscar en DESCREC "ORIGEN - BRASIL" o similar
        if (!empty($data['MARKREC0'])) {
            foreach ($data['MARKREC0'] as $line) {
                if (preg_match('/ORIGEM?\s*-?\s*(BRASIL|BRAZIL)/i', $line)) {
                    return 'BR';
                }
                if (preg_match('/ORIGEN\s*-?\s*(ARGENTINA)/i', $line)) {
                    return 'AR';
                }
            }
        }
        
        // Determinar por puertos del viaje
        if (!empty($data['GNRLREC'])) {
            foreach ($data['GNRLREC'] as $line) {
                if (str_contains($line, 'BRPNG')) return 'BR'; // Paranaguá
                if (str_contains($line, 'COCTG')) return 'CO'; // Cartagena
            }
        }
        
        return 'BR'; // Default basado en archivo ejemplo
    }

    /**
     * Extraer descripciones de carga - CORREGIDO: información específica del tipo
     */
    protected function extractCargoDescriptions(array $data): array
    {
        $descriptions = [];
        
        // Buscar información principal de carga en DESCREC
        if (!empty($data['DESCREC0'])) {
            $quantity = '';
            $cargoType = '';
            $brand = '';
            $model = '';
            
            foreach ($data['DESCREC0'] as $line) {
                $cleanLine = trim($line);
                
                if (empty($cleanLine)) continue;
                
                // FIX bug QA #3: el content de DESCREC viene con prefijo numérico
                // de 6 chars (3 del seq + 3 del sub-seq) por el offset 8 del groupBy.
                // Ej: "DESCREC000100116 VEHICULOS..." -> content "00100116 VEHICULOS..."
                // Sin limpiar, "00100116" se interpretaba como cantidad en lugar de "16".
                $cleanLine = preg_replace('/^\d{6}/', '', $cleanLine);
                $cleanLine = trim($cleanLine);
                if (empty($cleanLine)) continue;
                
                // Extraer cantidad y tipo de vehículos
                // FIX QA #6: sumar cantidades cuando hay múltiples grupos en el mismo BL
                // (este DAT tiene "16 VEHICULOS" + "27 VEHICULOS" = 43 total)
                if (preg_match('/^(\d+)\s+(VEHICULOS?.*?)(?:\s+MARCA\s+(.+?))?(?:\s+-\s*)?$/i', $cleanLine, $matches)) {
                    $quantity = (int)$quantity + (int)$matches[1];
                    $cargoType = trim($matches[2]);
                    if (isset($matches[3])) {
                        $brand = trim($matches[3]);
                    }
                }
                // FIX QA #6: se quitó la extracción de "modelo específico" porque tomaba
                // líneas documentales como "EMISION DE ORIGINALES EN DESTINO" o "SEA WAYBILL"
                // como modelo. Los modelos reales (ej "HJD5M2DAMY DUSTER ICONIC 1.3T 4X4")
                // contienen puntos y no matcheaban el regex de todas formas.
            }
            
            // Construir descripción específica
            if ($quantity && $cargoType) {
                $description = $quantity . ' ' . $cargoType;
                
                if ($brand) {
                    $description .= ' ' . $brand;
                }
                
                $descriptions[] = $description;
            }
        }
        
        // Si no se encontró información específica, usar información de CMMDREC
        if (empty($descriptions) && !empty($data['CMMDREC0'])) {
            foreach ($data['CMMDREC0'] as $line) {
                if (preg_match('/NAUT(\d+)([A-Z]+)/i', $line, $matches)) {
                    $qty = ltrim($matches[1], '0') ?: '1';
                    $type = strtolower($matches[2]);
                    
                    $typeMap = [
                        'VEHICLES' => 'Vehículos',
                        'UNITS' => 'Unidades',
                        'NAUT' => 'Unidades'
                    ];
                    
                    $typeDesc = $typeMap[$type] ?? $type;
                    $descriptions[] = $qty . ' ' . $typeDesc;
                }
            }
        }
        
        // Fallback si no se encuentra nada específico
        if (empty($descriptions)) {
            $descriptions[] = 'Mercadería según manifiesto KLine';
        }
        
        return $descriptions;
    }

    // NUEVO: extraer términos de flete desde FRTCREC0
    protected function extractFreightTerms(array $data): string
    {
        // Default conservador si no hay registro
        $terms = 'prepaid';

        if (!empty($data['FRTCREC0'])) {
            foreach ($data['FRTCREC0'] as $line) {
                $l = strtoupper(trim($line));
                // Códigos típicos en KLine:
                // POFT = Prepaid Ocean Freight ; COFT = Collect Ocean Freight
                if (str_contains($l, 'POFT')) {
                    return 'prepaid';
                }
                if (str_contains($l, 'COFT')) {
                    return 'collect';
                }
            }
        }

        return $terms;
    }

    // Normaliza un número con coma/punto a float (e.g. "1.234,56" -> 1234.56)
    protected function normalizeNumber(string $raw): ?float
    {
        $s = preg_replace('/[^\d.,]/', '', $raw ?? '');
        if ($s === '') return null;

        if (str_contains($s, '.') && str_contains($s, ',')) {
            // asume . miles y , decimales  ->  1.234,56
            $s = str_replace('.', '', $s);
            $s = str_replace(',', '.', $s);
        } elseif (str_contains($s, ',')) {
            // asume , decimales ->  123,45
            $s = str_replace(',', '.', $s);
        }
        if (!is_numeric($s)) return null;
        return (float) $s;
    }

    // Detecta código de moneda razonable dentro de una línea
    protected function detectCurrencyCode(string $u): ?string
    {
        // Priorizamos códigos ISO si aparecen
        foreach (['USD','ARS','PYG','BRL','UYU'] as $iso) {
            if (str_contains($u, $iso)) return $iso;
        }
        // Heurísticas por símbolo/palabras
        if (str_contains($u, 'U$S') || str_contains($u, 'US$')) return 'USD';
        if (str_contains($u, ' R$') || str_contains($u, 'REAIS') || str_contains($u, 'REALES')) return 'BRL';
        if (str_contains($u, ' G$') || str_contains($u, 'GUARANI')) return 'PYG';
        if (preg_match('/(^|[^A-Z])\$(\s|[0-9])/', $u)) return 'ARS'; // $ aislado: preferimos ARS
        return null;
    }

    // Extrae términos (prepaid/collect), moneda y monto (si aparece) desde FRTCREC0
    protected function extractFreightCharges(array $data, ?string $termsHint = null): array
    {
        $res = ['terms' => $termsHint, 'currency' => null, 'amount' => null];

        if (empty($data['FRTCREC0'])) return $res;

        foreach ($data['FRTCREC0'] as $line) {
            $u = strtoupper((string)$line);

            // Términos
            if (str_contains($u, 'POFT')) $res['terms'] = 'prepaid';
            if (str_contains($u, 'COFT')) $res['terms'] = 'collect';

            // Moneda
            $cur = $this->detectCurrencyCode($u);
            if ($cur && !$res['currency']) $res['currency'] = $cur;

            // Monto (primer número "grande" con 2 decimales)
            if (preg_match_all('/\b\d{1,3}(?:[.,]\d{3})*(?:[.,]\d{2})\b|\b\d+(?:[.,]\d{2})\b/', $u, $m)) {
                foreach ($m[0] as $cand) {
                    $val = $this->normalizeNumber($cand);
                    if ($val !== null && $val > 0) {
                        // Tomamos el primero razonable y salimos
                        if ($res['amount'] === null) {
                            $res['amount'] = $val;
                            break;
                        }
                    }
                }
            }
        }

        return $res;
    }


    // Normaliza fechas a YYYY-MM-DD
    protected function normalizeDate(string $raw): ?string
    {
        $raw = trim($raw);

        // YYYY-MM-DD
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
            return $raw;
        }

        // DD/MM/YYYY
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $raw, $m)) {
            return "{$m[3]}-{$m[2]}-{$m[1]}";
        }

        // DD-MM-YYYY
        if (preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $raw, $m)) {
            return "{$m[3]}-{$m[2]}-{$m[1]}";
        }

        return null;
    }

    // Busca ETD / ETA / BL DATE en el contenido del .DAT
    protected function extractDates(array $data): array
    {
        $res = ['etd' => null, 'eta' => null, 'bl_date' => null];

        foreach ($data as $recordType => $lines) {
            if (!is_array($lines)) continue;

            foreach ($lines as $line) {
                $u = strtoupper((string)$line);

                // Captura cualquier fecha común: YYYY-MM-DD | DD/MM/YYYY | DD-MM-YYYY
                if (!preg_match_all('/\b(\d{4}-\d{2}-\d{2}|\d{2}\/\d{2}\/\d{4}|\d{2}-\d{2}-\d{4})\b/', $u, $m)) {
                    continue;
                }

                foreach ($m[1] as $raw) {
                    $yyyy_mm_dd = $this->normalizeDate($raw);
                    if (!$yyyy_mm_dd) continue;

                    // Heurísticas simples por palabra clave
                    if (!$res['etd'] && (str_contains($u, ' ETD') || str_contains($u, 'DEPART') || str_contains($u, ' LOAD'))) {
                        $res['etd'] = $yyyy_mm_dd;
                    }
                    if (!$res['eta'] && (str_contains($u, ' ETA') || str_contains($u, 'ARRIV') || str_contains($u, ' DISCH'))) {
                        $res['eta'] = $yyyy_mm_dd;
                    }
                    if (
                        !$res['bl_date'] &&
                        (str_contains($u, 'B/L') || str_contains($u, 'BL DATE') || str_contains($u, 'ISSUE'))
                    ) {
                        $res['bl_date'] = $yyyy_mm_dd;
                    }
                }
            }
        }

        return $res;
    }


    /**
     * Buscar o crear puerto - IGUAL QUE PARANA
     */
protected function extractPortInfo(array $data): array
{
    // Sin defaults: se completan cuando se detecta algo
    $portInfo = ['origin' => null, 'destination' => null];

    // Países existentes (prefijo AA del UN/LOCODE)
    $alpha2Set = array_flip(
        \App\Models\Country::query()
            ->pluck('alpha2_code')
            ->map(fn ($c) => strtoupper($c))
            ->all()
    );

    $foundCodes = [];

    foreach ($data as $recordType => $lines) {
        if (!is_array($lines)) continue;

        // FIX QA: solo procesar registros del manifiesto (GNRLREC tiene los puertos
        // reales del viaje). Antes detectaba puertos en HEADREC (RIO DE JANEIRO falso
        // positivo) y en PTYIREC (BUENOS AIRES de direcciones de partes).
        if (!str_starts_with($recordType, 'GNRLREC')) continue;

        foreach ($lines as $line) {
            $u = ' ' . strtoupper((string)$line);

            // Evitar líneas de buque (CAPRI, etc.)
            if (str_contains($u, ' VESSEL') || str_contains($u, ' SHIP')
                || str_contains($u, ' BUQUE') || str_contains($u, ' BARCO')
                || str_contains($u, ' NAVIO') || str_contains($u, ' NAVE')) {
                continue;
            }

            // 1) Detección amplia de UN/LOCODE: AA999, con anti-falsos (cola con ≥2 letras)
            if (preg_match_all('/(?<![A-Z0-9])[A-Z]{2}[A-Z0-9]{3}(?![A-Z0-9])/', $u, $m)) {
                foreach ($m[0] as $code) {
                    $alpha2 = substr($code, 0, 2);
                    if (!isset($alpha2Set[$alpha2])) continue;

                    $tail = substr($code, 2, 3);
                    if (preg_match_all('/[A-Z]/', $tail) < 2) continue; // evita AR00F

                    $foundCodes[] = $code;
                }
            }
        }
    }

    // Únicos y en orden
    $foundCodes = array_values(array_unique($foundCodes));

    // 2) Fallback por NOMBRE (alias) si no encontramos suficientes códigos UN/LOCODE.
    // FIX QA #2: antes solo corría si $foundCodes estaba vacío, lo que dejaba afuera
    // casos como K-Line donde COCTG (Cartagena) era válido pero "DELTA DOCK"/AR00F
    // no se detectaba (AR00F es descartado por anti-falsos UN/LOCODE).
    // Ahora corre siempre que falte algún puerto (origen o destino).
    if (count($foundCodes) < 2) {
        // Alias de la ZONA (podés ajustar el puerto destino del alias si querés)
        $aliasMap = [
            'DELTA DOCK'      => 'ARCAM',   // Campana (Delta Dock - Lima)
            'RIO DE JANEIRO'  => 'BRRIO',   // Rio de Janeiro
            'BUENOS AIRES'    => 'ARBUE',   // Puerto de Buenos Aires
            'ASUNCION'        => 'PYASU',   // Asunción
            'VILLET'          => 'PYVLL',   // Villeta / Villeta*
            'TERMINAL VILLET' => 'PYTVT',   // Terminal Villeta (si aparece)
            'CAMPANA'         => 'ARCAM',   // Campana
        ];

        foreach ($data as $recordType => $lines) {
            if (!is_array($lines)) continue;

            // FIX QA: idem arriba — solo GNRLREC (evita BUENOS AIRES/RIO DE JANEIRO
            // de direcciones de partes o de la cabecera del archivo).
            if (!str_starts_with($recordType, 'GNRLREC')) continue;

            foreach ($lines as $line) {
                $u = ' ' . strtoupper((string)$line);

                // Excluir líneas de buque
                if (str_contains($u, ' VESSEL') || str_contains($u, ' SHIP')
                    || str_contains($u, ' BUQUE') || str_contains($u, ' BARCO')
                    || str_contains($u, ' NAVIO') || str_contains($u, ' NAVE')) {
                    continue;
                }

                foreach ($aliasMap as $needle => $code) {
                    if (str_contains($u, ' ' . $needle)) {
                        // País del alias debe existir en tabla countries
                        $alpha2 = substr($code, 0, 2);
                        if (!isset($alpha2Set[$alpha2])) continue;

                        $foundCodes[] = $code;
                    }
                }
            }
        }

        $foundCodes = array_values(array_unique($foundCodes));
    }

    // Si aún no hay candidatos, devolvemos null/null (validará aguas arriba)
    if (empty($foundCodes)) {
        return $portInfo;
    }

    // 3) Preferir puertos ya existentes en BD
    $dbSet = array_flip(
        \App\Models\Port::query()
            ->pluck('code')
            ->map(fn ($c) => strtoupper($c))
            ->all()
    );

    $inDb = []; $notInDb = [];
    foreach ($foundCodes as $code) {
        if (isset($dbSet[$code])) {
            $inDb[] = $code;
        } else {
            $notInDb[] = $code;
        }
    }
    $candidates = array_merge($inDb, $notInDb);

    // 4) Asignación con prioridad local: K-Line es importación inbound,
    // así que AR/PY es DESTINO (puerto de descarga), no origen.
    // FIX QA: la regla anterior preferOrigin=['AR','PY'] estaba pensada para
    // exportaciones (carga que sale de AR/PY). Para K-Line es al revés.
    $preferDestination = ['AR', 'PY'];
    $origin = null; $destination = null;

    foreach ($candidates as $code) {
        if (in_array(substr($code, 0, 2), $preferDestination, true)) { $destination = $code; break; }
    }
    if (!$destination) $destination = $candidates[0];

    foreach ($candidates as $code) {
        if ($code !== $destination) { $origin = $code; break; }
    }

    $portInfo['origin']      = $origin;
    $portInfo['destination'] = $destination;

    return $portInfo;
}




    /**
     * Buscar o crear cliente - CORREGIDO: usar estructura real de tabla clients
     */
    protected function findOrCreateClient(array $clientData, int $companyId, array $partyLines = [], ?Port $originPort = null): Client
    {
        $name  = trim($clientData['name'] ?? 'Cliente Desconocido');
        $taxId = $clientData['tax_id'] ?? null;

        // Normalizar tax_id (ej. CUIT -> 11 dígitos)
        $normTaxId = $taxId ? preg_replace('/\D+/', '', $taxId) : null;

        // 1) Buscar por tax_id (si hay)
        if ($normTaxId) {
            if ($client = Client::where('tax_id', $normTaxId)->first()) {
                Log::info('Cliente existente encontrado por tax_id', ['client_id' => $client->id, 'tax_id' => $normTaxId]);
                return $client;
            }
        }

        // 2) Buscar por nombre (legal_name)
        if ($client = Client::where('legal_name', $name)->first()) {
            Log::info('Cliente existente encontrado por nombre', ['client_id' => $client->id, 'name' => $name]);
            return $client;
        }

        
        // 3. Crear nuevo cliente con campos REALES de la tabla

       $countryId = null;
        if (!empty($partyLines) && $originPort) {
            $countryId = $this->detectCountryIdFromParty($partyLines, $originPort) ?? $originPort->country_id;
        } elseif ($originPort) {
            $countryId = $originPort->country_id;
        }

        // Guard si la columna existe y no pudimos resolver país
        if (\Schema::hasColumn('clients', 'country_id') && is_null($countryId)) {
            throw new \DomainException(
                "No se pudo inferir el país para el cliente '{$name}'. " .
                "Revise líneas del party o puertos del BL."
            );
        }


        $client = Client::create([
            // Obligatorios según tu migración
            'tax_id'               => $normTaxId,
            'country_id'           => $countryId,
            'document_type_id'     => 1, // si 1 = CUIT/Doc local (mantengo tu default)
            'legal_name'           => $name,
            'commercial_name'      => $name,
            'status'               => 'active',
            'created_by_company_id'=> $companyId,
            'verified_at'          => now(),

            // Opcionales si vienen
            'address'              => $clientData['address'] ?? null,
            'email'                => $clientData['email'] ?? null,
            'notes'                => 'Cliente creado desde archivo KLine DAT',
        ]);

        
        Log::info('Cliente creado desde KLine', [
            'client_id' => $client->id,
            'legal_name' => $client->legal_name,
            'tax_id' => $client->tax_id
        ]);
        
        return $client;
    }

    /**
     * Verificar duplicados en lote ANTES de procesar - NUEVO
     */
    protected function checkForDuplicateBills(array $bills): array
    {
        $billNumbers = [];
        $existingNumbers = [];
        
        // Extraer todos los números de BL del archivo
        foreach ($bills as $blData) {
            $blNumber = $this->cleanBillNumber($blData['bl']);
            if (!empty($blNumber)) {
                $billNumbers[] = $blNumber;
            }
        }
        
        // Verificar cuáles ya existen en BD
        if (!empty($billNumbers)) {
            $existing = BillOfLading::whereIn('bill_number', $billNumbers)
                                   ->pluck('bill_number')
                                   ->toArray();
            $existingNumbers = $existing;
        }
        
        $totalBills = count($billNumbers);
        $existingCount = count($existingNumbers);
        $allDuplicates = ($totalBills > 0 && $existingCount === $totalBills);
        
        Log::info('Verificación de duplicados KLine', [
            'total_bills' => $totalBills,
            'existing_count' => $existingCount,
            'all_duplicates' => $allDuplicates,
            'existing_numbers' => array_slice($existingNumbers, 0, 3)
        ]);
        
        return [
            'all_duplicates' => $allDuplicates,
            'has_duplicates' => $existingCount > 0,
            'existing_count' => $existingCount,
            'total_count' => $totalBills,
            'existing_numbers' => $existingNumbers
        ];
    }

    // Resuelve UN/LOCODE a partir de un nombre de puerto/ciudad usando la BD.
    // No crea puertos; solo intenta encontrar coincidencias en ports.name / ports.city.
    protected function resolvePortCodeByName(string $raw): ?string
    {
        $u = strtoupper(trim($raw));
        if ($u === '') return null;

        // Limpieza básica
        $u = preg_replace('/[^\p{L}\p{N}\s\-]/u', '', $u); // quita puntuación rara
        $u = preg_replace('/\s+/', ' ', $u);

        // 1) Intento exacto (name/city)
        $exact = \App\Models\Port::query()
            ->whereRaw('UPPER(name) = ?', [$u])
            ->orWhereRaw('UPPER(city) = ?', [$u])
            ->first();
        if ($exact) return strtoupper($exact->code);

        // 2) Intento parcial por tokens significativos (>=4 chars, sin palabras genéricas)
        $stop = ['PORT','PUERTO','TERMINAL','DE','DEL','DOCK','MUELLE','CITY'];
        $tokens = array_values(array_filter(explode(' ', $u), fn($w) => strlen($w) >= 4 && !in_array($w, $stop, true)));
        if (empty($tokens)) return null;

        $q = \App\Models\Port::query();
        foreach ($tokens as $t) {
            $q->orWhereRaw('UPPER(name) LIKE ?', ["%$t%"])
            ->orWhereRaw('UPPER(city) LIKE ?', ["%$t%"]);
        }
        $hit = $q->orderBy('display_order')->first();
        return $hit ? strtoupper($hit->code) : null;
    }


    /**
     * Helper methods
     */
    protected function getCountryFromPortCode(string $portCode): int
    {
        $countryMappings = [
            'AR' => 1, 'PY' => 2, 'BR' => 3, 'UY' => 4
        ];
        return $countryMappings[substr($portCode, 0, 2)] ?? 1;
    }

    protected function getPortNameFromCode(string $portCode): string
    {
        $portNames = [
            'ARBUE' => 'Buenos Aires',
            'PYTVT' => 'Terminal Villeta',
            'PYASU' => 'Asunción'
        ];
        return $portNames[$portCode] ?? ucfirst(strtolower($portCode));
    }

    protected function getCityFromCode(string $portCode, string $defaultCity): string
    {
        $cityMap = [
            'ARBUE' => 'Buenos Aires',
            'ARROS' => 'Rosario', 
            'ARSFE' => 'Santa Fe',
            'PYASU' => 'Asunción',
            'PYTVT' => 'Villeta',
            'PYCON' => 'Concepción',
        ];
        
        return $cityMap[$portCode] ?? $defaultCity;
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
            'name' => 'KLine Data Format',
            'description' => 'Formato de archivo de datos .DAT de K-Line',
            'extensions' => ['dat', 'txt'],
            'features' => ['multiple_bills_per_file', 'automatic_voyage_creation', 'port_detection']
        ];
    }

    public function getDefaultConfig(): array
    {
        return [
            'parsing' => [
                'line_encoding' => 'UTF-8',
                'skip_empty_lines' => true,
                'min_line_length' => 8,
                'record_type_length' => 8
            ],
            'ports' => [
                'auto_create_missing' => true,
                'default_origin' => 'ARBUE',
                'default_destination' => 'PYTVT',
                'known_ports' => [
                    'ARBUE' => 'Buenos Aires',
                    'ARROS' => 'Rosario',
                    'ARCAM' => 'Campana',
                    'PYASU' => 'Asunción',
                    'PYCON' => 'Concepción',
                    'PYTVT' => 'Terminal Villeta'
                ]
            ],
            'clients' => [
                'auto_create_missing' => true,
                'default_document_type_id' => 1,
                'default_country_id' => 1
            ],
            'cargo' => [
                'default_cargo_type_id' => 1,
                'default_packaging_type_id' => 1,
                'default_freight_terms' => 'prepaid'
            ]
        ];
    }

    // Extrae bloques de líneas de PTYIREC0 para SHIPPER / CONSIGNEE (heurística simple)
    protected function extractPartyLinesFromPTYI(array $data): array
    {
        $shipper = [];
        $consignee = [];

        $lines = $data['PTYIREC0'] ?? [];
        if (!is_array($lines)) $lines = [];

        // FIX bug #1: K-Line usa códigos SH/CN/NP pegados al seq (ej: "0001SH...").
        // No usa los textos "SHIPPER" o "CONSIGNEE" que esperaba la versión anterior.
        foreach ($lines as $raw) {
            $trim = trim((string)$raw);
            if ($trim === '') continue;

            // Detectar código de parte después del seq numérico
            if (preg_match('/^(\d+)SH\S*\s+(.+)$/', $trim, $m)) {
                $shipper[] = $m[2];
            } elseif (preg_match('/^(\d+)CN\S*\s+(.+)$/', $trim, $m)) {
                $consignee[] = $m[2];
            }
            // NP (notify party) no se usa en createBillOfLading actualmente
        }

        return [$shipper, $consignee];
    }

    // Construye clientData (name/tax/email/address) a partir de líneas
    protected function buildClientDataFromLines(array $lines): array
    {
        $name = null; $tax = null; $email = null;

        foreach ($lines as $ln) {
            $trim = trim($ln);
            if ($trim === '') continue;

            // email
            if (!$email && preg_match('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', $trim, $m)) {
                $email = $m[0];
            }

            // FIX bug QA #4: usar extractTaxIdFromLine que ya detecta NIT/CUIT/CNPJ/RUC.
            // Antes solo detectaba CUIT argentino (\d{2}-\d{8}-\d) y NIT colombiano "860.025.792-3" quedaba afuera.
            if (!$tax) {
                $candidateTax = $this->extractTaxIdFromLine($trim);
                if ($candidateTax) {
                    $tax = $candidateTax;
                }
            }

            // FIX bug QA #4: usar extractCompanyNameFromLine que separa el nombre de la empresa
            // de la dirección y del NIT/CUIT. Antes guardaba la línea entera concatenada.
            if (!$name) {
                $candidate = $this->extractCompanyNameFromLine($trim);
                if ($candidate) {
                    $name = $candidate;
                }
            }
        }

        // address = todo junto (para guardar dirección si sirve)
        $address = null;
        if (!empty($lines)) {
            $address = trim(implode(' ', array_map('trim', $lines)));
            $address = $address !== '' ? $address : null;
        }

        return [
            'name'    => $name ?? 'Cliente Desconocido',
            'tax_id'  => $tax,
            'email'   => $email,
            'address' => $address,
        ];
    }

}