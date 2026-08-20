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
    use EnsuresUniqueVoyageNumber;
    use ResolvesClientAddresses;

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
                $portInfo = $this->extractPortInfo($firstBL['data'] ?? []);

                // 🔒 Guard estricto: no continuar si falta alguno
                if (empty($portInfo['origin']) || empty($portInfo['destination'])) {
                    throw new \DomainException(
                        "No se detectaron ambos puertos (origen/destino) en el archivo KLine. " .
                        "Detectado -> origen: " . ($portInfo['origin'] ?? 'null') .
                        ", destino: " . ($portInfo['destination'] ?? 'null') . ". " .
                        "Revise que existan UN/LOCODE en líneas con contexto (POL/POD/PORT/LOADING/DISCHARGE/ORIGIN/DEST)."
                    );
                }

                // Puertos del viaje: se resuelven una vez desde el primer BL y
                // sirven de referencia general del viaje y de fallback por BL.
                $originPort      = $this->findOrCreatePort($portInfo['origin']);
                $destinationPort = $this->findOrCreatePort($portInfo['destination']);

                $dates      = $this->extractDates($firstBL['data']);
                $voyageInfo = $this->extractVoyageInfo($firstBL['data']);

                // CORREGIDO: Crear voyage usando $options
                $voyage = $this->createVoyage(
                    $voyageInfo,
                    $originPort,
                    $destinationPort,
                    $options,
                    $dates
                );
                
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

                        // Cada BL debe conservar sus propios puertos.
                        // Nunca sustituirlos silenciosamente por los del viaje.
                        $blPortInfo = $this->extractPortInfo($blData['data']);

                        if (
                            empty($blPortInfo['origin'])
                            || empty($blPortInfo['destination'])
                        ) {
                            throw new \DomainException(
                                "K-Line no informa ambos puertos para el BL {$blNumber}."
                            );
                        }

                        $blOriginPort = $this->findOrCreatePort(
                            $blPortInfo['origin']
                        );

                        $blDestinationPort = $this->findOrCreatePort(
                            $blPortInfo['destination']
                        );

                        // Crear BillOfLading
                        $bill = $this->createBillOfLading($shipment, $blNumber, $blData['data'], $blOriginPort, $blDestinationPort);
                        $createdBills[] = $bill;

                        // CORREGIDO: Crear ShipmentItems con campos obligatorios
                        $items = $this->createShipmentItems($bill, $blData['data']);
                        $allItems = array_merge($allItems, $items);

                        $this->stats['created_bills']++;
                        
                    } catch (Exception $e) {
                        $this->stats['errors']++;
                        $this->stats['warnings'][] =
                            "Error procesando BL {$blData['bl']}: "
                            . $e->getMessage();

                        Log::error('Error processing BL', [
                            'bl' => $blData['bl'],
                            'error' => $e->getMessage(),
                        ]);

                        // La importación K-Line es atómica:
                        // un BL inválido invalida el archivo completo.
                        throw $e;
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
            // Viaje ya existente (bloqueo global de duplicado): mensaje amable, no SQL crudo.
            if (strpos($e->getMessage(), 'voyages_voyage_number_unique') !== false) {
                if (isset($importRecord)) {
                    $importRecord->markAsFailed([
                        'Este archivo ya fue importado anteriormente. El viaje ya existe en el sistema y no se duplicó ningún dato.'
                    ], ['errors_count' => 1]);
                }
                return ManifestParseResult::failure([
                    'Este archivo ya fue importado anteriormente. El viaje ya existe en el sistema y no se duplicó ningún dato. Si necesita importarlo de nuevo, primero revierta la importación desde el Historial de Importaciones.'
                ], [], $this->stats);
            }

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
     * Resolver fechas del viaje sin fabricar valores.
     *
     * Prioridad:
     * 1) fechas explícitas recibidas en options;
     * 2) fechas inequívocamente extraídas del archivo;
     * 3) null.
     */
    protected function resolveVoyageNumber(
        array $voyageInfo,
        array $options = []
    ): string {
        $sourceNumber = trim(
            (string) ($voyageInfo['voyage_number'] ?? '')
        );

        $operatorNumber = trim(
            (string) ($options['voyage_number'] ?? '')
        );

        $number = $sourceNumber !== ''
            ? $sourceNumber
            : $operatorNumber;

        if ($number === '') {
            throw new \DomainException(
                'K-Line no informa número de viaje. '
                . 'Debe ingresarlo al importar el manifiesto.'
            );
        }

        if (
            str_starts_with(
                strtoupper($number),
                'KLINE-'
            )
        ) {
            return $number;
        }

        return 'KLINE-' . $number;
    }

    protected function resolveVoyageDates(
        array $sourceDates,
        array $options = []
    ): array {
        $sourceDeparture = $sourceDates['etd'] ?? null;
        $sourceArrival = $sourceDates['eta'] ?? null;

        $operatorDeparture =
            $options['departure_date'] ?? null;

        $departure = !empty($sourceDeparture)
            ? Carbon::parse($sourceDeparture)
            : (
                !empty($operatorDeparture)
                    ? Carbon::parse($operatorDeparture)
                    : null
            );

        $arrival = !empty($sourceArrival)
            ? Carbon::parse($sourceArrival)
            : null;

        return [
            'departure_date' => $departure,
            'estimated_arrival_date' => $arrival,
        ];
    }

    protected function createVoyage(
        array $voyageInfo,
        Port $originPort,
        Port $destinationPort,
        array $options = [],
        array $extractedDates = []
    ): Voyage
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

        $voyageNumber = $this->resolveVoyageNumber(
            $voyageInfo,
            $options
        );

        // El voyage_number es único global. Si ya existe (en cualquier empresa),
        // se bloquea la importación con un error claro en lugar de reusar el viaje.
        $this->guardVoyageNumberIsFree($voyageNumber);

        $resolvedDates = $this->resolveVoyageDates(
            $extractedDates,
            $options
        );

        $etd = $resolvedDates['departure_date'];
        $eta = $resolvedDates['estimated_arrival_date'];


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
            'shipment_number' => 'KLINE-SHIP-' . preg_replace(
                '/^KLINE-/i',
                '',
                $voyage->voyage_number
            ),
            'sequence_in_voyage' => 1,
            'vessel_role' => 'single',
            'cargo_capacity_tons' => $vessel->cargo_capacity_tons,
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
        [$shipperLines, $consigneeLines, $notifyLines] =
            $this->extractPartyLinesFromPTYI($data);

        $shipperCountryAlpha2 =
            $this->extractStructuredPartyCountry($data, 'SH');
        $consigneeCountryAlpha2 =
            $this->extractStructuredPartyCountry($data, 'CN');
        $notifyCountryAlpha2 =
            $this->extractStructuredPartyCountry($data, 'NP');

        $shipperData   = $this->buildClientDataFromLines($shipperLines);
        $consigneeData = $this->buildClientDataFromLines($consigneeLines);
        $notifyData = $notifyLines !== []
            ? $this->buildClientDataFromLines($notifyLines)
            : null;

        // 3) Fechas + flete
        $dates        = $this->extractDates($data);                // ['etd','eta','bl_date']
        $importDate = now()->toDateString();
        $freightTerms = $this->extractFreightTerms($data);         // 'prepaid'|'collect'|default
        $freight      = $this->extractFreightCharges($data, $freightTerms); // ['terms','currency','amount']

        // 4) Clientes (ORIGEN → shipper, DESTINO → consignee)
        $shipper = $this->findOrCreateClient(
            $shipperData,
            $companyId,
            $shipperLines,
            $originPort,
            $shipperCountryAlpha2
        );

        $consignee = $this->findOrCreateClient(
            $consigneeData,
            $companyId,
            $consigneeLines,
            $destinationPort,
            $consigneeCountryAlpha2
        );

        $notify = $notifyData !== null
            ? $this->findOrCreateClient(
                $notifyData,
                $companyId,
                $notifyLines,
                $destinationPort,
                $notifyCountryAlpha2
            )
            : null;

        // 5) Atributos de flete (solo si existen columnas)
        $freightAttrs = [];
        if (\Schema::hasColumn('bills_of_lading', 'freight_terms') && ($freight['terms'] ?? null)) {
            $freightAttrs['freight_terms'] = $freight['terms'];
        }
        if (
            \Schema::hasColumn('bills_of_lading', 'currency_code')
            && ($freight['currency'] ?? null)
        ) {
            $freightAttrs['currency_code'] = $freight['currency'];
        }
        if (\Schema::hasColumn('bills_of_lading', 'freight_amount') && array_key_exists('amount', $freight) && $freight['amount'] !== null) {
            $freightAttrs['freight_amount'] = $freight['amount'];
        }

        // 6) Campos base del BL (seguros)
        $commodityCodes = $this->extractNCMCodes($data);

        $blMeasurements = $this->extractRealMeasurements($data);
        $this->assertRequiredMeasurements(
            $blMeasurements,
            $blNumber
        );

        $blAttrs = [
            'shipment_id'       => $shipment->id,
            'bill_number'       => $blNumber,
            // Regla operativa: si K-Line no informa la fecha,
            // usar la fecha del día de la importación.
            'bill_date'         => $dates['bl_date'] ?? $importDate,
            'loading_date'      => $dates['etd'] ?? $importDate,
            // FIX bug #5: usar descripción real extraída de DESCREC en lugar de leyenda fija
            'cargo_description' => $this->resolveCargoDescription(
                $data,
                $blNumber
            ),
            'status'            => 'draft',
            'master_bl_number'  => $this->extractMasterBL($data),
            // FIX bugs #3, #4: cargo y packaging correctos para K-Line (carga no contenedorizada)
            'primary_cargo_type_id' => $this->resolveCargoTypeId($data, $blNumber),  // OTRA CARGA NO CONTENEDORIZADA
            'primary_packaging_type_id' => $this->resolvePackagingTypeId($data),  // NO RETORNABLE
            // FIX bug #6b: BL no estaba recibiendo cargo_marks (quedaba vacío en BD)
            'cargo_marks'               => $this->extractCargoMarks($data),
            // Mantener un código principal por compatibilidad y preservar
            // además todos los NCM/HS explícitos informados por K-Line.
            'commodity_code'             => $commodityCodes[0] ?? null,
            'commodity_codes'            => $commodityCodes ?: null,
            // FIX QA #5: sincronizar totales del BL desde los datos reales del archivo
            // (antes quedaban en 0/1 hardcoded aunque los items sí tuvieran datos)
            'gross_weight_kg'   => $blMeasurements['gross_weight_kg'],
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

        if (\Schema::hasColumn('bills_of_lading', 'notify_party_id')) {
            $blAttrs['notify_party_id'] = $notify?->id;
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
        $bill = BillOfLading::create(array_merge($blAttrs, $freightAttrs));

        // 9) Direcciones de las partes (regla 18/6): ficha si no tiene (Etapa 1),
        //    específica del BL si difiere (Etapa 2). NP no se procesa: Kline no
        //    crea notify en el BL (en archivos vistos NP = consignee siempre).
        $shipperAddr   = $this->buildAddressFromPartyLines($shipperLines);
        $consigneeAddr = $this->buildAddressFromPartyLines($consigneeLines);
        $notifyAddr = $notify
            ? $this->buildAddressFromPartyLines($notifyLines)
            : null;

        $this->persistClientAddress($shipper, $shipperAddr);
        if ($c = $this->resolveSpecificAddress($shipper, $shipperAddr, 'shipper')) {
            $bill->specificContacts()->create($c);
        }

        $this->persistClientAddress($consignee, $consigneeAddr);
        if ($c = $this->resolveSpecificAddress($consignee, $consigneeAddr, 'consignee')) {
            $bill->specificContacts()->create($c);
        }

        if ($notify) {
            $this->persistClientAddress($notify, $notifyAddr);

            if ($c = $this->resolveSpecificAddress(
                $notify,
                $notifyAddr,
                'notify_party'
            )) {
                $bill->specificContacts()->create($c);
            }
        }

        return $bill;
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

        $this->assertCargoDescriptions(
            $descriptions,
            $bill->bill_number
        );

        // Una sola lectura de mediciones por BL.
        $realMeasurements = $this->extractRealMeasurements($data);

        $this->assertRequiredMeasurements(
            $realMeasurements,
            $bill->bill_number
        );

        if (
            ($realMeasurements['_package_quantity_explicit'] ?? false)
            && (int) $realMeasurements['package_quantity'] === 0
        ) {
            $this->stats['warnings'][] =
                "BL {$bill->bill_number}: K-Line informa cantidad "
                . "de bultos en 0. Se importó en 0 y debe completarse "
                . "manualmente.";
        }

        if (
            ($realMeasurements['_gross_weight_explicit'] ?? false)
            && (float) $realMeasurements['gross_weight_kg'] == 0.0
        ) {
            $this->stats['warnings'][] =
                "BL {$bill->bill_number}: K-Line informa peso bruto "
                . "en 0. Se importó en 0 y debe completarse "
                . "manualmente.";
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
            $countryOfOrigin = $this->extractCountryOfOrigin($data); // NUEVO

            $item = ShipmentItem::create([
                'bill_of_lading_id' => $bill->id,
                'line_number' => $lineNumber,
                'item_description' => $description,
                // FIX bugs #3, #4: K-Line no contenedorizado
                'cargo_type_id' => $this->resolveCargoTypeId($data, $bill->bill_number),     // OTRA CARGA NO CONTENEDORIZADA
                'packaging_type_id' => $this->resolvePackagingTypeId($data), // NO RETORNABLE
                'package_quantity' => $realMeasurements['package_quantity'], // REAL
                'gross_weight_kg' => $realMeasurements['gross_weight_kg'], // REAL
                'net_weight_kg' => $realMeasurements['net_weight_kg'], // REAL
                'volume_m3' => $realMeasurements['volume_m3'], // REAL
                'declared_value' => null, // No disponible en archivo
                'currency_code' => $this->resolveDeclaredValueCurrency(null, null),
                'commodity_code' => $ncmCode ?: null, // REAL o null
                'country_of_origin' => $countryOfOrigin, // REAL
                'cargo_marks' => $cargoMarks, // REAL
                'unit_of_measure' => $this->resolveUnitOfMeasure($data, $bill->bill_number),
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



    // Detecta país desde evidencia propia del party; puerto sólo como último fallback.
    protected function detectCountryIdFromParty(array $partyLines, ?Port $likelyPort = null): ?int
    {
        $text = Str::upper(
            Str::ascii(
                implode(' ', array_map('strval', $partyLines))
            )
        );

        /*
         * K-Line puede codificar ISO2 inmediatamente después del rol:
         * PTYIREC0001SHMX...
         * PTYIREC0001SHUS...
         *
         * Esta evidencia tiene prioridad sobre cualquier inferencia por puerto.
         */
        foreach ($partyLines as $partyLine) {
            $partyLine = Str::upper(
                Str::ascii(trim((string) $partyLine))
            );

            if (
                preg_match(
                    '/^PTYIREC\d{4}(?:SH|CN|NP)([A-Z]{2})\d/',
                    $partyLine,
                    $matches
                )
            ) {
                $countryId = \App\Models\Country::query()
                    ->whereRaw(
                        'UPPER(alpha2_code) = ?',
                        [$matches[1]]
                    )
                    ->value('id');

                if ($countryId) {
                    return (int) $countryId;
                }
            }
        }

        // País declarado textualmente por la propia parte.
        $map = [
            'ARGENTINA'         => 'AR',
            'PARAGUAY'          => 'PY',
            'BRASIL'            => 'BR',
            'BRAZIL'            => 'BR',
            'URUGUAY'           => 'UY',
            'MEXICO'            => 'MX',
            'UNITED STATES'     => 'US',
            'CANADA'            => 'CA',
            'REPUBLIC OF KOREA' => 'KR',

            'ARG.' => 'AR',
            'PAR.' => 'PY',
            'BRA.' => 'BR',
            'URU.' => 'UY',
        ];

        foreach ($map as $needle => $alpha2) {
            if (str_contains($text, $needle)) {
                return \App\Models\Country::query()
                    ->whereRaw(
                        'UPPER(alpha2_code) = ?',
                        [$alpha2]
                    )
                    ->value('id');
            }
        }

        // "USA" sólo como palabra completa.
        if (preg_match('/\bUSA\b/', $text)) {
            return \App\Models\Country::query()
                ->whereRaw('UPPER(alpha2_code) = ?', ['US'])
                ->value('id');
        }

        // CUIT argentino como evidencia fiscal.
        if (preg_match('/\b\d{2}-?\d{8}-?\d\b/', $text)) {
            return \App\Models\Country::query()
                ->whereRaw('UPPER(alpha2_code) = ?', ['AR'])
                ->value('id');
        }

        // Último recurso: país del puerto probable.
        if (
            $likelyPort
            && \Schema::hasColumn('ports', 'country_id')
        ) {
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
            $voyageInfo['voyage_number'] = null;
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
     * Extrae el identificador fiscal y conserva el tipo declarado por K-Line.
     *
     * Los marcadores genéricos (por ejemplo TAX ID) pueden aportar un número
     * real, pero no autorizan a inventar CUIT/RUC/NIT/CNPJ.
     */
    protected function extractTaxIdentityFromLine(string $line): ?array
    {
        $taxId = $this->extractTaxIdFromLine($line);

        if ($taxId === null) {
            return null;
        }

        $taxType = null;

        if (preg_match(
            '/(CUIT(?:\s+NBR)?|CNPJ|NIT|R\.?U\.?C\.?(?:\s*\/\s*TAX\s*ID)?)\s*[:#.]?\s*([0-9][0-9.\-\/]{5,})/i',
            $line,
            $matches
        )) {
            $candidateTaxId = preg_replace('/\D/', '', $matches[2]);

            // El marcador sólo tipifica al mismo número que realmente
            // reconoció el helper fiscal común.
            if ($candidateTaxId === $taxId) {
                $label = strtoupper(
                    preg_replace('/[^A-Z]/i', '', $matches[1])
                );

                $taxType = match (true) {
                    str_starts_with($label, 'CUIT') => 'CUIT',
                    str_starts_with($label, 'CNPJ') => 'CNPJ',
                    str_starts_with($label, 'NIT') => 'NIT',
                    str_starts_with($label, 'RUC') => 'RUC',
                    default => null,
                };
            }
        }

        return [
            'tax_id' => $taxId,
            'tax_type' => $taxType,
        ];
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
        if (preg_match('/^(.+?)\s+(?:NIT[:\s]|CUIT[:\s]|CNPJ[:\s]|RUC[:\s]|VAT[:\s]|,|$)/', $cleanLine, $matches)) {
            $companyName = trim($matches[1]);
        } else {
            // Si no hay patrón específico, tomar hasta el primer grupo de espacios largos
            $parts = preg_split('/\s{3,}/', $cleanLine, 2);
            $companyName = trim($parts[0]);
        }

        // FIX: formato de ancho fijo -> el nombre es el primer segmento de columna.
        // Si el marcador fiscal (ej. CUIT) viene DESPUES de la direccion, $matches[1]
        // arrastra la corrida de espacios + direccion, supera los 100 chars y el
        // metodo devolvia null ("Cliente Desconocido"). Cortar en 2+ espacios lo evita.
        $companyName = trim(preg_split('/\s{2,}/', $companyName, 2)[0]);
        
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

                // groupByBillOfLading() deja en MARKREC los 6 dígitos
                // de secuencia/subsecuencia.
                // Ej: 001001RENAULT - ORIGEN - -> RENAULT - ORIGEN -
                $cleanLine = preg_replace('/^\d{6}/', '', $cleanLine);
                $cleanLine = trim($cleanLine);

                if ($cleanLine === '') {
                    continue;
                }

                $upper = strtoupper($cleanLine);

                // HS CODE / NCM son información aduanera, no marcas.
                if (
                    str_contains($upper, 'HS CODE:') ||
                    str_contains($upper, 'NCM:')
                ) {
                    continue;
                }

                // Continuaciones puramente numéricas de HS CODE tampoco son marcas.
                if (preg_match('/^\d{2}\.\d{2}\.\d{2}(?:\.\d{2})?$/', $cleanLine)) {
                    continue;
                }

                $marks[] = $cleanLine;
            }
        }

        if (empty($marks)) {
            return 'SM';
        }

        return implode(' / ', array_values(array_unique($marks)));
    }
    /**
     * Extraer todos los códigos NCM/HS explícitos del BL.
     *
     * El primer código conserva el orden de prioridad del contrato K-Line:
     * CMMDREC estructurado primero; luego NCM/HS explícitos en texto.
     *
     * No se buscan números arbitrarios en las descripciones.
     */
    protected function extractNCMCodes(array $data): array
    {
        $codes = [];

        $append = function (?string $raw) use (&$codes): void {
            if ($raw === null) {
                return;
            }

            $digits = preg_replace('/\D+/', '', $raw);

            /*
             * La aplicación conserva NCM base de 8 dígitos:
             * - HS 6 dígitos -> completar 00;
             * - códigos extendidos -> conservar primeros 8;
             * - NCM 8 -> conservar tal cual.
             */
            if (strlen($digits) === 6) {
                $digits .= '00';
            } elseif (strlen($digits) >= 8) {
                $digits = substr($digits, 0, 8);
            } else {
                return;
            }

            if (!in_array($digits, $codes, true)) {
                $codes[] = $digits;
            }
        };

        // Fuente estructurada: máxima prioridad.
        foreach ($data['CMMDREC0'] ?? [] as $line) {
            if (preg_match(
                '/M3\s+([0-9]{8})(?:\s|$)/i',
                trim((string) $line),
                $matches
            )) {
                $append($matches[1]);
            }
        }

        /*
         * Variantes reales K-Line:
         * HS CODE 842482
         * HS CODE: 870195 / 843149
         * NCM: 8705.20
         * NCM 8426.41.90
         * NCM 8479.82.10.900C
         */
        foreach (['DESCREC0', 'MARKREC0'] as $recordType) {
            foreach ($data[$recordType] ?? [] as $line) {
                $line = trim(
                    preg_replace(
                        '/^\d{6}/',
                        '',
                        trim((string) $line)
                    )
                );

                if (!preg_match(
                    '/\b(?:NCM|HS\s*CODE)\s*:?\s*(.+)$/i',
                    $line,
                    $matches
                )) {
                    continue;
                }

                $payload = preg_split(
                    '/\b(?:NET\s+WEIGHT|GROSS\s+WEIGHT|WEIGHT|KGS|CBM)\b/i',
                    $matches[1],
                    2
                )[0];

                if (!preg_match_all(
                    '/(?<!\d)[0-9][0-9.,]*(?:[A-Z])?(?!\d)/i',
                    $payload,
                    $codeMatches
                )) {
                    continue;
                }

                foreach ($codeMatches[0] as $rawCode) {
                    $append($rawCode);
                }
            }
        }

        return $codes;
    }

    protected function extractNCMCode(array $data): ?string
    {
        return $this->extractNCMCodes($data)[0] ?? null;
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
            'package_quantity' => null,
            'gross_weight_kg' => null,
            'net_weight_kg' => null,
            'volume_m3' => null,

            // Opción C:
            // distinguir "0 informado por K-Line" de "dato ausente".
            '_package_quantity_explicit' => false,
            '_gross_weight_explicit' => false,
            '_volume_explicit' => false,
        ];

        /*
         * CMMDREC es la fuente estructurada principal.
         *
         * Cada dato se extrae independientemente. La ausencia de volumen,
         * por ejemplo, no debe hacer perder una cantidad o un peso bruto
         * que sí fueron informados.
         */
        if (!empty($data['CMMDREC0'])) {
            foreach ($data['CMMDREC0'] as $line) {
                if (preg_match('/NAUT(\d+)/i', $line, $matches)) {
                    $measurements['_package_quantity_explicit'] = true;
                    $measurements['package_quantity'] =
                        (int) $matches[1];
                }

                if (preg_match('/(\d+)KGS/i', $line, $matches)) {
                    // K-Line: 4 decimales implícitos.
                    // Un cero explícito se conserva como cero.
                    $measurements['_gross_weight_explicit'] = true;
                    $measurements['gross_weight_kg'] =
                        ((float) $matches[1]) / 10000;
                }

                if (preg_match('/(\d+)M3/i', $line, $matches)) {
                    // K-Line: 3 decimales implícitos.
                    $measurements['_volume_explicit'] = true;
                    $measurements['volume_m3'] =
                        ((float) $matches[1]) / 1000;
                }
            }
        }

        /*
         * DESCREC puede aportar peso neto y volumen textual.
         * El volumen textual sólo es fallback del CMMDREC.
         */
        if (!empty($data['DESCREC0'])) {
            foreach ($data['DESCREC0'] as $line) {
                if (
                    preg_match(
                        '/(?:NET\s*WEIGHT|N\.?\s*W\.?)\s*[:.]?\s*([0-9\.,]+)\s*KGS/i',
                        $line,
                        $matches
                    )
                ) {
                    $netWeight = $this->normalizeNumber($matches[1]);

                    if ($netWeight !== null) {
                        $measurements['net_weight_kg'] = $netWeight;
                    }
                }

                if (
                    $measurements['volume_m3'] === null
                    && !($measurements['_volume_explicit'] ?? false)
                    && preg_match(
                        '/M3[:\s]+([0-9\.,]+)/i',
                        $line,
                        $matches
                    )
                ) {
                    $volume = $this->normalizeNumber($matches[1]);

                    if ($volume !== null) {
                        $measurements['volume_m3'] = $volume;
                    }
                }
            }
        }

        /*
         * Si CMMDREC trae cero, K-Line puede informar los pesos
         * de los componentes en DESCREC.
         *
         * Sólo usar expresiones inequívocas. No interpretar números
         * aislados ni tonelajes descriptivos.
         */
        if (
            $measurements['gross_weight_kg'] === null
            && !($measurements['_gross_weight_explicit'] ?? false)
            && !empty($data['DESCREC0'])
        ) {
            // Primero: total bruto explícito.
            foreach ($data['DESCREC0'] as $line) {
                $cleanLine = trim(
                    preg_replace('/^\d{6}/', '', trim((string) $line))
                );

                if (
                    preg_match(
                        '/^(?:GROSS\s+WEIGHT|G\.?\s*W\.?)\s*[:.]?\s*([0-9.,]+)\s*(KGS?|LBS)\b/i',
                        $cleanLine,
                        $matches
                    )
                ) {
                    $value = $this->normalizeNumber($matches[1]);

                    if ($value !== null && $value > 0) {
                        $measurements['gross_weight_kg'] =
                            strtoupper($matches[2]) === 'LBS'
                                ? $value * 0.45359237
                                : $value;

                        break;
                    }
                }
            }

            // Segundo: pesos explícitos de componentes; se suman.
            if ($measurements['gross_weight_kg'] === null) {
                $componentWeightKg = 0.0;
                $componentCount = 0;

                foreach ($data['DESCREC0'] as $line) {
                    $cleanLine = trim(
                        preg_replace('/^\d{6}/', '', trim((string) $line))
                    );

                    if (
                        !preg_match(
                            '/^WEIGHT\s+([0-9.,]+)\s*(KGS?|LBS)\b/i',
                            $cleanLine,
                            $matches
                        )
                    ) {
                        continue;
                    }

                    $value = $this->normalizeNumber($matches[1]);

                    if ($value === null || $value <= 0) {
                        continue;
                    }

                    $componentWeightKg +=
                        strtoupper($matches[2]) === 'LBS'
                            ? $value * 0.45359237
                            : $value;

                    $componentCount++;
                }

                if ($componentCount > 0 && $componentWeightKg > 0) {
                    $measurements['gross_weight_kg'] =
                        $componentWeightKg;
                }
            }
        }

        return $measurements;
    }

    /**
     * Los campos obligatorios del modelo no pueden completarse con valores
     * inventados cuando K-Line no los informa.
     */
    protected function assertRequiredMeasurements(
        array $measurements,
        string $blNumber
    ): void {
        $missing = [];

        if (
            ($measurements['package_quantity'] ?? null) === null
        ) {
            $missing[] = 'cantidad total de bultos';
        }

        if (
            ($measurements['gross_weight_kg'] ?? null) === null
        ) {
            $missing[] = 'peso bruto';
        }

        if ($missing !== []) {
            throw new \DomainException(
                "K-Line BL {$blNumber}: no se pudo determinar de forma "
                . "inequívoca " . implode(' ni ', $missing) . "."
            );
        }
    }

    protected function extractCountryOfOrigin(array $data): ?string
    {
        foreach (['MARKREC0', 'DESCREC0'] as $recordType) {
            foreach (($data[$recordType] ?? []) as $line) {
                $source = Str::upper(
                    Str::ascii((string) $line)
                );

                if (
                    preg_match(
                        '/\b(?:ORIGEM|ORIGEN)\s*[-:]?\s*(?:BRASIL|BRAZIL)\b/',
                        $source
                    )
                ) {
                    return 'BR';
                }

                if (
                    preg_match(
                        '/\b(?:ORIGEM|ORIGEN)\s*[-:]?\s*ARGENTINA\b/',
                        $source
                    )
                ) {
                    return 'AR';
                }
            }
        }

        return null;
    }

    /**
     * Extraer descripciones de carga - CORREGIDO: información específica del tipo
     */
    protected function assertCargoDescriptions(
        array $descriptions,
        string $blNumber
    ): void {
        $descriptions = array_values(
            array_filter(
                $descriptions,
                fn ($description) =>
                    trim((string) $description) !== ''
            )
        );

        if ($descriptions === []) {
            throw new \DomainException(
                "K-Line no informa una descripción de mercadería "
                . "utilizable para el BL {$blNumber}."
            );
        }
    }

    protected function resolveCargoTypeCode(
        array $data,
        string $blNumber
    ): string {
        $lines = [];

        foreach (['MARKREC0', 'DESCREC0', 'CMMDREC0'] as $recordType) {
            foreach (($data[$recordType] ?? []) as $line) {
                $lines[] = (string) $line;
            }
        }

        $sourceText = Str::upper(
            Str::ascii(implode("\n", $lines))
        );

        /*
         * Modalidad explícita declarada por K-Line.
         * Tiene prioridad sobre palabras como VEHICLE/TRACTOR/COMBINE
         * que puedan aparecer en la descripción de la mercadería.
         */
        if (
            preg_match(
                '/(?<![A-Z])RO[ -]?RO(?![A-Z])/',
                $sourceText
            )
        ) {
            return 'RORO001';
        }

        // Clasificación explícita histórica de K-Line.
        if (
            preg_match(
                '/(?<![A-Z])(?:VEHICLES?|VEHICULOS?)(?![A-Z])/',
                $sourceText
            )
        ) {
            return 'VEH001';
        }

        // El archivo real KKLUATM02175 declara excavadoras hidráulicas.
        // No inferir RORO por "SELF PROPELLED UNIT": el DAT no declara
        // modalidad RoRo. Se conserva como carga no contenedorizada.
        if (
            preg_match(
                '/(?<![A-Z])(?:ESCAVADORAS?|EXCAVATORS?)(?![A-Z])/',
                $sourceText
            )
        ) {
            return 'ONC001';
        }

        /*
         * Variante real KKLUATM02176: tractor tiendetubos desmontado.
         * MAFI / SELF PROPELLED UNIT no implica clasificar como RORO.
         * El BL declara carga no contenedorizada.
         */
        if (
            preg_match(
                '/(?<![A-Z])TRACTOR(?:ES)?\s+TIENDETUBOS(?![A-Z])/',
                $sourceText
            )
        ) {
            return 'ONC001';
        }

        /*
         * Variante real KKLUATM02177:
         * tractores/talleres automáticos de soldadura sobre orugas.
         * Se conservan como maquinaria no contenedorizada.
         */
        if (
            preg_match(
                '/(?:TRACTOR(?:ES)?\s+DE\s+SOLDADURA|TALLER\w*\s+AUTOMATICO\s+DE\s+SOLDADURA)/',
                $sourceText
            )
        ) {
            return 'ONC001';
        }

        /*
         * Variante real KKLUATM02183:
         * cosechadora / maquinaria rodante.
         */
        if (
            preg_match(
                '/(?<![A-Z])COSECHADORAS?(?![A-Z])/',
                $sourceText
            )
        ) {
            return 'VEH001';
        }

        /*
         * Variantes reales restantes del DAT K-Line 18ago:
         * fumigadoras y casas rodantes.
         */
        if (
            preg_match(
                '/(?<![A-Z])(?:FUMIGADORAS?|CASAS?\s+RODANTES?)(?![A-Z])/',
                $sourceText
            )
        ) {
            return 'VEH001';
        }

        throw new \DomainException(
            "K-Line no informa un tipo de carga reconocido "
            . "para el BL {$blNumber}."
        );
    }

    protected function resolveCargoTypeId(
        array $data,
        string $blNumber
    ): int {
        $code = $this->resolveCargoTypeCode(
            $data,
            $blNumber
        );

        $id = \App\Models\CargoType::query()
            ->where('code', $code)
            ->where('active', true)
            ->value('id');

        if (!$id) {
            throw new \DomainException(
                "No existe un tipo de carga activo con código "
                . "{$code} para el BL {$blNumber}."
            );
        }

        return (int) $id;
    }

    protected function resolveUnitOfMeasure(
        array $data,
        string $blNumber
    ): string {
        // CMMDREC informa la unidad explícitamente.
        // Ejemplo real: NAUT00000002UNITS.
        foreach (($data['CMMDREC0'] ?? []) as $line) {
            $source = Str::upper(
                Str::ascii((string) $line)
            );

            if (
                preg_match(
                    '/NAUT\d+\s*(?:UNITS?|VEHICLES?)(?![A-Z])/',
                    $source
                )
            ) {
                return 'PCS';
            }
        }

        // Compatibilidad con los DAT históricos de vehículos.
        $cargoTypeCode = $this->resolveCargoTypeCode(
            $data,
            $blNumber
        );

        return match ($cargoTypeCode) {
            'VEH001', 'RORO001' => 'PCS',
            default => throw new \DomainException(
                "K-Line no tiene una unidad de medida definida "
                . "para el tipo de carga {$cargoTypeCode} "
                . "del BL {$blNumber}."
            ),
        };
    }

    protected function resolvePackagingTypeId(
        array $data
    ): int {
        $id = \App\Models\PackagingType::query()
            ->where('code', 'N')
            ->where('active', true)
            ->value('id');

        if (!$id) {
            throw new \DomainException(
                'No existe el tipo de embalaje activo NO RETORNABLE (N).'
            );
        }

        return (int) $id;
    }

    protected function resolveCargoDescription(
        array $data,
        string $blNumber
    ): string {
        $descriptions = $this->extractCargoDescriptions($data);

        $this->assertCargoDescriptions(
            $descriptions,
            $blNumber
        );

        return implode(' / ', $descriptions);
    }

    /**
     * Recupera descripción útil directamente de DESCREC cuando ninguna
     * de las variantes especializadas reconoció la mercadería.
     *
     * Se elimina únicamente texto operativo/legal/documental conocido.
     * No se reinterpretan los datos de la carga.
     */
    protected function extractSemanticDescriptionLines(array $data): array
    {
        $result = [];
        $insideCarrierBoilerplate = false;
        $skipOnBoardContinuation = false;

        foreach ($data['DESCREC0'] ?? [] as $rawLine) {
            $line = trim(
                preg_replace(
                    '/^\d{6}/',
                    '',
                    trim((string) $rawLine)
                )
            );

            if ($line === '') {
                continue;
            }

            $upper = Str::upper(Str::ascii($line));

            /*
             * Bloque legal estándar K-Line.
             */
            if (
                str_contains(
                    $upper,
                    'UNPACKED AND UNPROTECTED VEHICLES'
                )
            ) {
                $insideCarrierBoilerplate = true;
                continue;
            }

            if ($insideCarrierBoilerplate) {
                if (
                    str_contains(
                        $upper,
                        'EXPRESSLY RESERVED'
                    )
                ) {
                    $insideCarrierBoilerplate = false;
                }

                continue;
            }

            /*
             * "LADEN ON BOARD..." puede continuar en 1 o 2 DESCREC:
             *
             * LADEN ON BOARD GOODWOOD 48 AT
             * FREEPORT, TX (UNITED STATES) ON 7
             * -17-26
             *
             * Es dato operativo, no descripción de mercadería.
             */
            if (
                preg_match(
                    '/^(?:LADEN|LOADED|SHIPPED)\s+ON\s+BOARD\b/i',
                    $line
                )
            ) {
                $skipOnBoardContinuation =
                    !preg_match(
                        '/\d{1,2}-\d{1,2}-\d{2,4}\b/',
                        $line
                    );

                continue;
            }

            if ($skipOnBoardContinuation) {
                if (
                    preg_match(
                        '/\d{1,2}-\d{1,2}-\d{2,4}\b/',
                        $line
                    )
                    || preg_match(
                        '/^-\d{1,2}-\d{2,4}$/',
                        $line
                    )
                ) {
                    $skipOnBoardContinuation = false;
                }

                continue;
            }

            /*
             * Contacto / datos fiscales / documentación operativa.
             */
            if (
                str_contains($line, '@')
                || preg_match(
                    '/^\*{0,3}(?:'
                    . 'E-?MAIL'
                    . '|PH(?:O(?:NE)?)?'
                    . '|FAX'
                    . '|TEL(?:EPHONE|EFONO|EFONE)?'
                    . '|TAX\s+(?:NO|ID)'
                    . '|RFC'
                    . '|CUIT'
                    . '|CNPJ'
                    . '|RUC'
                    . ')\b/i',
                    $line
                )
                || preg_match(
                    '/^\+?\d[\d\s().-]{5,}$/',
                    $line
                )
            ) {
                continue;
            }

            /*
             * Campos que ya tienen destino propio o documentación
             * aduanera/comercial, no descripción física.
             */
            if (
                preg_match(
                    '/^(?:'
                    . 'FREIGHT\s+PREPAID'
                    . '|FLETE\s+PAGADO'
                    . '|SHIPPED\s+UNDER'
                    . '|INVOICE\s+NO\.?:'
                    . '|FATURAS?:'
                    . '|PROFORMA:'
                    . '|NCM\b'
                    . '|H\/S\s+CODE\b'
                    . '|HS\s+CODE\b'
                    . '|AES(?:\s+ITN)?\b'
                    . '|ITN:'
                    . '|NOEEI\b'
                    . '|NO\s+AES\s+REQUIRED'
                    . '|T&E\s+ENTRY'
                    . '|DU-?E:'
                    . '|DUE:'
                    . '|\*\*FMC:'
                    . '|PREPARED\s+BY'
                    . '|\d{2}-\d{2}-\d{4}\s+\d{2}:\d{2}'
                    . '|CONSOLIDATED\s+(?:CARGO|SHIPMENT)'
                    . '|MERCHANDISE\s+IN\s+MONITORED\s+TRANSIT'
                    . '|C-\d+$'
                    . ')/i',
                    $line
                )
            ) {
                continue;
            }

            if (
                preg_match(
                    '/^(?:'
                    . 'MONTEVIDEO,\s*URUGUAY'
                    . '|\*BUENOS\s+AIRES\.?\s+ARGENTINA'
                    . '|TO'
                    . ')$/i',
                    $line
                )
            ) {
                continue;
            }

            $result[] = $line;
        }

        return array_values(array_unique($result));
    }


    protected function extractCargoDescriptions(array $data): array
    {
        $descriptions = [];

        // Comportamiento histórico: vehículos.
        if (!empty($data['DESCREC0'])) {
            $quantity = '';
            $cargoType = '';
            $brand = '';

            foreach ($data['DESCREC0'] as $line) {
                $cleanLine = trim($line);
                if ($cleanLine === '') {
                    continue;
                }

                $cleanLine = preg_replace('/^\d{6}/', '', $cleanLine);
                $cleanLine = trim($cleanLine);

                if ($cleanLine === '') {
                    continue;
                }

                if (
                    preg_match(
                        '/^(\d+)\s+(VEHICULOS?.*?)(?:\s+MARCA\s+(.+?))?(?:\s+-\s*)?$/i',
                        $cleanLine,
                        $matches
                    )
                ) {
                    $quantity = (int) $quantity + (int) $matches[1];
                    $cargoType = trim($matches[2]);

                    if (isset($matches[3])) {
                        $brand = trim($matches[3]);
                    }
                }
            }

            if ($quantity && $cargoType) {
                $description = $quantity . ' ' . $cargoType;

                if ($brand) {
                    $description .= ' ' . $brand;
                }

                $descriptions[] = $description;
            }
        }

        /*
         * Variante real KKLUATM02175: excavadoras.
         *
         * El CMMD sólo dice "2 UNITS"; DESCREC contiene el significado real,
         * modelos y números de serie. Debe persistirse como UN solo item para
         * no repetir las medidas totales del BL.
         */
        if (empty($descriptions) && !empty($data['DESCREC0'])) {
            $cleanLines = [];
            $hasExcavator = false;

            foreach ($data['DESCREC0'] as $line) {
                $cleanLine = trim(
                    preg_replace('/^\d{6}/', '', trim((string) $line))
                );

                if ($cleanLine === '') {
                    continue;
                }

                $upper = Str::upper(
                    Str::ascii($cleanLine)
                );

                if (
                    preg_match(
                        '/(?<![A-Z])(?:ESCAVADORAS?|EXCAVATORS?)(?![A-Z])/',
                        $upper
                    )
                ) {
                    $hasExcavator = true;
                }

                $cleanLines[] = $cleanLine;
            }

            if ($hasExcavator) {
                $details = [];

                foreach ($cleanLines as $line) {
                    $upper = Str::upper(
                        Str::ascii($line)
                    );

                    if (
                        preg_match('/^LOADED AS PER BELOW\s*:?\s*$/', $upper)
                        || preg_match('/^NCM\s*:/', $upper)
                        || preg_match('/^CONSOLIDATED CARGO\s*$/', $upper)
                        || preg_match('/^ESCAVADORA IDRAULICA\s*$/', $upper)
                    ) {
                        continue;
                    }

                    $details[] = $line;
                }

                $details = array_values(array_unique($details));

                $quantity = $this->extractRealMeasurements($data)[
                    'package_quantity'
                ];

                $description =
                    ($quantity !== null ? $quantity . ' ' : '')
                    . ($quantity === 1
                        ? 'ESCAVADORA HIDRAULICA'
                        : 'ESCAVADORAS HIDRAULICAS');

                if ($details !== []) {
                    $description .= ' / ' . implode(' / ', $details);
                }

                $descriptions[] = $description;
            }
        }

        /*
         * Variante real KKLUATM02176: tractor tiendetubos.
         *
         * CMMD informa 22 UNIT, pero eso no demuestra 22 tractores
         * completos porque la maquinaria viene desmontada en piezas.
         */
        if (empty($descriptions) && !empty($data['DESCREC0'])) {
            $hasPipeLayer = false;
            $hasSuperiorSpx660 = false;
            $isDismantled = false;
            $loadedOnMafi = false;

            foreach ($data['DESCREC0'] as $line) {
                $cleanLine = trim(
                    preg_replace(
                        '/^\d{6}/',
                        '',
                        trim((string) $line)
                    )
                );

                if ($cleanLine === '') {
                    continue;
                }

                $upper = Str::upper(
                    Str::ascii($cleanLine)
                );

                if (
                    preg_match(
                        '/(?<![A-Z])TRACTOR(?:ES)?\s+TIENDETUBOS(?![A-Z])/',
                        $upper
                    )
                ) {
                    $hasPipeLayer = true;
                }

                if (str_contains($upper, 'SUPERIOR SPX 660')) {
                    $hasSuperiorSpx660 = true;
                }

                if (str_contains($upper, 'DESMONTADO EN')) {
                    $isDismantled = true;
                }

                if (str_contains($upper, 'CARGO LOADED ON MAFI')) {
                    $loadedOnMafi = true;
                }
            }

            if ($hasPipeLayer) {
                $parts = [
                    'TRACTOR TIENDETUBOS',
                ];

                if ($hasSuperiorSpx660) {
                    $parts[] = 'SUPERIOR SPX 660';
                }

                if ($isDismantled) {
                    $parts[] = 'DESMONTADO';
                }

                if ($loadedOnMafi) {
                    $parts[] = 'CARGO LOADED ON MAFI';
                }

                $descriptions[] = implode(
                    ' / ',
                    $parts
                );
            }
        }

        /*
         * Variante real KKLUATM02177:
         * maquinaria de soldadura sobre orugas.
         *
         * No anteponer "1 UNIT": CMMD informa una unidad declarada del BL,
         * mientras DESCREC detalla múltiples equipos.
         */
        if (empty($descriptions) && !empty($data['DESCREC0'])) {
            $hasWeldingEquipment = false;
            $models = [];

            foreach ($data['DESCREC0'] as $line) {
                $cleanLine = trim(
                    preg_replace(
                        '/^\d{6}/',
                        '',
                        trim((string) $line)
                    )
                );

                if ($cleanLine === '') {
                    continue;
                }

                $upper = Str::upper(
                    Str::ascii($cleanLine)
                );

                if (
                    preg_match(
                        '/(?:TRACTOR(?:ES)?\s+DE\s+SOLDADURA|TALLER\w*\s+AUTOMATICO\s+DE\s+SOLDADURA)/',
                        $upper
                    )
                ) {
                    $hasWeldingEquipment = true;
                }

                if (
                    str_contains($upper, 'MOROOKA MST-1500 VD')
                    || str_contains($upper, 'SUPERIOR SRT 155')
                    || str_contains($upper, 'SUPERIOR SRT155')
                ) {
                    $models[] = $cleanLine;
                }
            }

            if ($hasWeldingEquipment) {
                $models = array_values(array_unique($models));

                $description = 'MAQUINARIA DE SOLDADURA SOBRE ORUGAS';

                if ($models !== []) {
                    $description .= ' / ' . implode(' / ', $models);
                }

                $descriptions[] = $description;
            }
        }

        /*
         * Variante real KKLUATM02183:
         * cosechadora John Deere.
         */
        if (empty($descriptions) && !empty($data['DESCREC0'])) {
            $hasHarvester = false;
            $model = null;
            $serial = null;

            foreach ($data['DESCREC0'] as $line) {
                $cleanLine = trim(
                    preg_replace(
                        '/^\d{6}/',
                        '',
                        trim((string) $line)
                    )
                );

                if ($cleanLine === '') {
                    continue;
                }

                $upper = Str::upper(
                    Str::ascii($cleanLine)
                );

                if (
                    preg_match(
                        '/(?<![A-Z])COSECHADORAS?(?![A-Z])/',
                        $upper
                    )
                ) {
                    $hasHarvester = true;
                }

                if (
                    str_contains(
                        $upper,
                        'COSECHADORA JOHN DEERE'
                    )
                ) {
                    $model = $cleanLine;
                }

                if (
                    preg_match(
                        '/^S\/N\s+(.+)$/i',
                        $cleanLine,
                        $matches
                    )
                ) {
                    $serial = 'S/N ' . trim($matches[1]);
                }
            }

            if ($hasHarvester) {
                $parts = [];

                $parts[] = $model
                    ?? 'COSECHADORA';

                if ($serial !== null) {
                    $parts[] = $serial;
                }

                $descriptions[] = implode(
                    ' / ',
                    $parts
                );
            }
        }

        /*
         * Fumigadora John Deere.
         */
        if (empty($descriptions) && !empty($data['DESCREC0'])) {
            $hasSprayer = false;
            $identity = null;
            $serial = null;

            foreach ($data['DESCREC0'] as $line) {
                $cleanLine = trim(
                    preg_replace(
                        '/^\d{6}/',
                        '',
                        trim((string) $line)
                    )
                );

                if ($cleanLine === '') {
                    continue;
                }

                $upper = Str::upper(Str::ascii($cleanLine));

                if (
                    preg_match(
                        '/(?<![A-Z])FUMIGADORAS?(?![A-Z])/',
                        $upper
                    )
                ) {
                    $hasSprayer = true;
                }

                if (
                    str_contains($upper, 'FUMIGADORA')
                    && str_contains($upper, 'JOHN DEERE')
                ) {
                    $identity = $cleanLine;
                }

                if (
                    preg_match(
                        '/^SERIE\s+(.+)$/i',
                        $cleanLine,
                        $matches
                    )
                ) {
                    $serial = 'SERIE ' . trim($matches[1]);
                }
            }

            if ($hasSprayer) {
                $parts = [
                    $identity ?? 'FUMIGADORA',
                ];

                if ($serial !== null) {
                    $parts[] = $serial;
                }

                $descriptions[] = implode(' / ', $parts);
            }
        }

        /*
         * Casa rodante.
         */
        if (empty($descriptions) && !empty($data['DESCREC0'])) {
            $hasMotorhome = false;
            $brand = null;
            $year = null;

            foreach ($data['DESCREC0'] as $line) {
                $cleanLine = trim(
                    preg_replace(
                        '/^\d{6}/',
                        '',
                        trim((string) $line)
                    )
                );

                if ($cleanLine === '') {
                    continue;
                }

                $upper = Str::upper(Str::ascii($cleanLine));

                if (
                    preg_match(
                        '/(?<![A-Z])CASAS?\s+RODANTES?(?![A-Z])/',
                        $upper
                    )
                ) {
                    $hasMotorhome = true;
                }

                if (
                    str_contains($upper, 'MERCEDES BENZ')
                    || $upper === 'THMC'
                ) {
                    $brand = $cleanLine;
                }

                if (
                    preg_match('/^ANO\s+(\d{4})$/', $upper, $matches)
                ) {
                    $year = $matches[1];
                }
            }

            if ($hasMotorhome) {
                $parts = ['CASA RODANTE'];

                if ($brand !== null) {
                    $parts[] = $brand;
                }

                if ($year !== null) {
                    $parts[] = 'AÑO ' . $year;
                }

                $descriptions[] = implode(' / ', $parts);
            }
        }

        /*
         * SKID STEER LOADER: preservar identidad real informada
         * por DESCREC en lugar de reducirla a "1 vehicles".
         */
        if (empty($descriptions) && !empty($data['DESCREC0'])) {
            $parts = [];
            $hasSkidSteer = false;

            foreach ($data['DESCREC0'] as $line) {
                $cleanLine = trim(
                    preg_replace('/^\\d{6}/', '', trim((string) $line))
                );

                if ($cleanLine === '') {
                    continue;
                }

                $upper = Str::upper(Str::ascii($cleanLine));

                if (str_contains($upper, 'SKID STEER LOADER')) {
                    $hasSkidSteer = true;
                    $parts[] = $cleanLine;
                    continue;
                }

                if (
                    preg_match(
                        '/^(?:S\\/N|PIN|ENG S\\/N)\\s*:/i',
                        $cleanLine
                    )
                ) {
                    $parts[] = $cleanLine;
                }
            }

            if ($hasSkidSteer) {
                $descriptions[] = implode(
                    ' / ',
                    array_values(array_unique($parts))
                );
            }
        }

        /*
         * Fallback general: si DESCREC sí describe la mercadería,
         * nunca reducirla a "1 unit", "3 vehicles", etc.
         */
        if (empty($descriptions) && !empty($data['DESCREC0'])) {
            $semanticLines =
                $this->extractSemanticDescriptionLines($data);

            if ($semanticLines !== []) {
                $descriptions[] = implode(
                    ' / ',
                    $semanticLines
                );
            }
        }

        // Fallback histórico: sólo cuando no existe descripción semántica.
        if (empty($descriptions) && !empty($data['CMMDREC0'])) {
            foreach ($data['CMMDREC0'] as $line) {
                if (preg_match('/NAUT(\d+)([A-Z]+)/i', $line, $matches)) {
                    $qty = ltrim($matches[1], '0') ?: '1';
                    $type = strtolower($matches[2]);

                    $typeMap = [
                        'VEHICLES' => 'Vehículos',
                        'UNITS' => 'Unidades',
                        'NAUT' => 'Unidades',
                    ];

                    $typeDesc = $typeMap[$type] ?? $type;
                    $descriptions[] = $qty . ' ' . $typeDesc;
                }
            }
        }

        return $descriptions;
    }

    // Extraer términos de flete únicamente cuando K-Line los informa.
    protected function extractFreightTerms(array $data): ?string
    {
        if (!empty($data['FRTCREC0'])) {
            foreach ($data['FRTCREC0'] as $line) {
                $l = strtoupper(trim((string) $line));

                // POFT = Prepaid Ocean Freight
                // COFT = Collect Ocean Freight
                if (str_contains($l, 'POFT')) {
                    return 'prepaid';
                }

                if (str_contains($l, 'COFT')) {
                    return 'collect';
                }
            }
        }

        return null;
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

    protected function resolveDeclaredValueCurrency(
        ?float $declaredValue,
        ?string $currency
    ): ?string {
        if ($declaredValue === null) {
            return null;
        }

        $normalizedCurrency = strtoupper(
            trim((string) $currency)
        );

        if ($normalizedCurrency === '') {
            throw new \DomainException(
                'Un valor declarado informado requiere una moneda.'
            );
        }

        if (!preg_match('/^[A-Z]{3}$/', $normalizedCurrency)) {
            throw new \DomainException(
                "Código de moneda inválido: {$normalizedCurrency}."
            );
        }

        return $normalizedCurrency;
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

            // Importe estructurado K-Line:
            // G000213807880 -> 213807.880
            // El campo tiene 3 decimales implícitos.
            if (
                $res['amount'] === null &&
                preg_match('/G(\d{12})(?=[A-Z]|\s|$)/', $u, $m)
            ) {
                $res['amount'] = ((int) $m[1]) / 1000;
            }

            // Fallback para variantes que expresen el monto con punto/coma.
            if ($res['amount'] === null) {
                if (preg_match_all('/\b\d{1,3}(?:[.,]\d{3})*(?:[.,]\d{2})\b|\b\d+(?:[.,]\d{2})\b/', $u, $m)) {
                    foreach ($m[0] as $cand) {
                        $val = $this->normalizeNumber($cand);

                        if ($val !== null && $val > 0) {
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
        $res = [
            'etd' => null,
            'eta' => null,
            'bl_date' => null,
        ];

        /*
         * GNRLREC informa OPYYYYMMDD.
         *
         * Validado contra el archivo real K-Line 18/08:
         * en los 9 BL que además declaran "LADEN ON BOARD ... M-D-YY",
         * ambas fechas coinciden exactamente.
         *
         * Por lo tanto OP = fecha de carga/embarque del BL.
         */
        $operationDate = null;

        foreach ($data as $recordType => $lines) {
            if (
                !str_starts_with((string) $recordType, 'GNRLREC')
                || !is_array($lines)
            ) {
                continue;
            }

            foreach ($lines as $line) {
                if (
                    !preg_match(
                        '/\bOP(20\d{6})/',
                        strtoupper((string) $line),
                        $matches
                    )
                ) {
                    continue;
                }

                $raw = $matches[1];

                $date = \DateTimeImmutable::createFromFormat(
                    '!Ymd',
                    $raw
                );

                $errors = \DateTimeImmutable::getLastErrors();

                if (
                    $date !== false
                    && (
                        $errors === false
                        || (
                            $errors['warning_count'] === 0
                            && $errors['error_count'] === 0
                        )
                    )
                    && $date->format('Ymd') === $raw
                ) {
                    $operationDate = $date->format('Y-m-d');
                    break 2;
                }
            }
        }

        /*
         * Buscar además fecha explícita de embarque en DESCREC.
         * K-Line puede partirla entre registros:
         *
         * LADEN ON BOARD GOODWOOD 48 AT
         * FREEPORT, TX ON 7
         * -17-26
         */
        $descriptionText = '';

        foreach ($data['DESCREC0'] ?? [] as $line) {
            $clean = trim(
                preg_replace(
                    '/^\d{6}/',
                    '',
                    trim((string) $line)
                )
            );

            if ($clean !== '') {
                $descriptionText .= ' ' . $clean;
            }
        }

        $descriptionText = preg_replace(
            '/\s+/',
            ' ',
            trim($descriptionText)
        );

        // Reconstruir fechas partidas: "7 -17-26" -> "7-17-26".
        $descriptionText = preg_replace(
            '/(\d)\s*-\s*(\d)/',
            '$1-$2',
            $descriptionText
        );

        $onBoardDate = null;

        if (
            preg_match(
                '/(?:LADEN|LOADED|SHIPPED)\s+ON\s+BOARD\b'
                . '.{0,250}?'
                . '\b(\d{1,2})-(\d{1,2})-(\d{2}|\d{4})\b/i',
                $descriptionText,
                $matches
            )
        ) {
            $month = (int) $matches[1];
            $day = (int) $matches[2];
            $year = (int) $matches[3];

            if ($year < 100) {
                $year += 2000;
            }

            if (checkdate($month, $day, $year)) {
                $onBoardDate = sprintf(
                    '%04d-%02d-%02d',
                    $year,
                    $month,
                    $day
                );
            }
        }

        if (
            $operationDate !== null
            && $onBoardDate !== null
            && $operationDate !== $onBoardDate
        ) {
            throw new \DomainException(
                "K-Line informa fechas de embarque contradictorias: "
                . "GNRLREC OP={$operationDate}, "
                . "DESCREC ON BOARD={$onBoardDate}."
            );
        }

        $res['etd'] = $onBoardDate ?? $operationDate;

        /*
         * Mantener búsqueda contextual para ETA y fecha de emisión del BL.
         * No interpretar cualquier YYYYMMDD de GNRLREC como bill_date.
         */
        foreach ($data as $recordType => $lines) {
            if (!is_array($lines)) {
                continue;
            }

            foreach ($lines as $line) {
                $u = strtoupper((string) $line);

                if (
                    !preg_match_all(
                        '/\b(\d{4}-\d{2}-\d{2}'
                        . '|\d{2}\/\d{2}\/\d{4}'
                        . '|\d{2}-\d{2}-\d{4})\b/',
                        $u,
                        $matches
                    )
                ) {
                    continue;
                }

                foreach ($matches[1] as $raw) {
                    $date = $this->normalizeDate($raw);

                    if (!$date) {
                        continue;
                    }

                    if (
                        !$res['eta']
                        && (
                            str_contains($u, ' ETA')
                            || str_contains($u, 'ARRIV')
                            || str_contains($u, ' DISCH')
                        )
                    ) {
                        $res['eta'] = $date;
                    }

                    if (
                        !$res['bl_date']
                        && (
                            str_contains($u, 'B/L')
                            || str_contains($u, 'BL DATE')
                            || str_contains($u, 'ISSUE')
                        )
                    ) {
                        $res['bl_date'] = $date;
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
    // OPCIÓN B (QA): validar candidatos sintácticos contra el catálogo ports.
    // Los falsos positivos con forma LOCODE (p.ej. "DELTA" de "DELTA DOCK" = DE+LTA)
    // no existen en ports y se descartan acá, de forma genérica y sin excepciones
    // puntuales. Conservamos los crudos para debug. Los alias curados (abajo) sí
    // pasan al resolver estricto aunque falten en catálogo, para surfacear el error.
    $rawSyntactic = $foundCodes;
    $dbSet = array_flip(
        \App\Models\Port::query()
            ->pluck('code')
            ->map(fn ($c) => strtoupper($c))
            ->all()
    );
    $foundCodes = array_values(array_filter(
        $foundCodes,
        fn ($c) => isset($dbSet[$c])
    ));
    // 2) Fallback por NOMBRE (alias): corre solo cuando NO hay suficientes puertos
    // VÁLIDOS en catálogo (antes contaba candidatos sin validar, y un falso positivo
    // como "DELTA" inflaba el conteo a 2 y bloqueaba este fallback).
    if (count($foundCodes) < 2) {
        // Alias de la ZONA (podés ajustar el puerto destino del alias si querés)
        $aliasMap = [
            'DELTA DOCK'      => 'ARBUE',   // Delta Dock = terminal; se informa Buenos Aires (confirmado Roberto 24/06)
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

    // Si no quedan candidatos resolubles, logueamos los crudos detectados para
    // distinguir un falso positivo de un hueco real de catálogo, y devolvemos
    // null/null (la validación aguas arriba reportará la falta de puertos).
    if (empty($foundCodes)) {
        Log::warning('KLine extractPortInfo: sin puertos resolubles', [
            'raw_syntactic' => $rawSyntactic ?? [],
        ]);
        return $portInfo;
    }

    // 3) Ordenar: puertos en catálogo primero (reusa $dbSet ya consultado arriba).
    // $foundCodes acá = sintácticos validados + alias curados; un alias que resuelva
    // a un código ausente (p.ej. ARCAM) queda en $notInDb y pasa igual al resolver
    // estricto, que tirará el error claro "no existe en catálogo".
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
     * Resolver el tipo fiscal únicamente cuando la fuente lo declara.
     *
     * La combinación tipo + país debe existir en el catálogo. Si hay
     * contradicción, se aborta en lugar de guardar una identidad falsa.
     */
    protected function resolveDocumentTypeId(
        ?string $taxType,
        ?int $countryId
    ): ?int {
        $taxType = strtoupper(trim((string) $taxType));

        if ($taxType === '') {
            return null;
        }

        if (!$countryId) {
            throw new \DomainException(
                "No se puede resolver {$taxType} sin país."
            );
        }

        $documentTypeId = DocumentType::query()
            ->where('code', $taxType)
            ->where('country_id', $countryId)
            ->where('active', true)
            ->value('id');

        if (!$documentTypeId) {
            throw new \DomainException(
                "El tipo fiscal {$taxType} no corresponde al país {$countryId} " .
                "o no existe en el catálogo."
            );
        }

        return (int) $documentTypeId;
    }


    /**
     * Buscar o crear cliente - CORREGIDO: usar estructura real de tabla clients
     */
    protected function resolveRequiredClientName(?string $name): string
    {
        $name = trim((string) $name);

        if ($name === '') {
            throw new \DomainException(
                'K-Line no informa el nombre de una parte obligatoria del BL.'
            );
        }

        $normalized = Str::upper(Str::ascii($name));

        if (in_array($normalized, [
            'CLIENTE DESCONOCIDO',
            'EMBARCADOR DESCONOCIDO',
            'CONSIGNATARIO DESCONOCIDO',
        ], true)) {
            throw new \DomainException(
                "K-Line no admite una parte sintética: {$name}."
            );
        }

        return $name;
    }

    protected function findOrCreateClient(
        array $clientData,
        int $companyId,
        array $partyLines = [],
        ?Port $originPort = null,
        ?string $structuredCountryAlpha2 = null
    ): Client
    {
        $name = $this->resolveRequiredClientName(
            $clientData['name'] ?? null
        );
        $taxId   = $clientData['tax_id'] ?? null;
        $taxType = $clientData['tax_type'] ?? null;

        // Normalizar el identificador fiscal a solo dígitos.
        $normTaxId = $taxId
            ? preg_replace('/\D+/', '', $taxId)
            : null;

        if ($normTaxId === '') {
            $normTaxId = null;
        }

        /*
         * Resolver país respetando la evidencia más fuerte:
         *
         * 1) tipo fiscal explícito (CUIT/CNPJ/RUC/NIT);
         * 2) país explícito en las líneas de la parte;
         * 3) puerto probable como último fallback.
         *
         * Ejemplo real K-Line:
         * notify en Foz do Iguacu con CNPJ brasileño. No puede tomar
         * Argentina simplemente porque el BL descarga en Argentina.
         */
        $countryId = null;
        $structuredCountryId = null;

        if ($structuredCountryAlpha2 !== null) {
            $structuredCountryId = \App\Models\Country::query()
                ->whereRaw(
                    'UPPER(alpha2_code) = ?',
                    [strtoupper($structuredCountryAlpha2)]
                )
                ->value('id');

            if (!$structuredCountryId) {
                throw new \DomainException(
                    "K-Line informa país {$structuredCountryAlpha2} "
                    . "para '{$name}', pero no existe en catálogo."
                );
            }

            $structuredCountryId = (int) $structuredCountryId;
        }

        if ($taxType !== null) {
            $taxDocumentCountries = DocumentType::query()
                ->whereRaw('UPPER(code) = ?', [strtoupper($taxType)])
                ->where('active', true)
                ->pluck('country_id')
                ->filter()
                ->unique()
                ->values();

            if ($taxDocumentCountries->count() === 1) {
                $countryId = (int) $taxDocumentCountries->first();
            }
        }

        if (
            $countryId !== null
            && $structuredCountryId !== null
            && $countryId !== $structuredCountryId
        ) {
            throw new \DomainException(
                "La parte '{$name}' declara {$taxType}, pero el país "
                . "{$structuredCountryAlpha2} informado por PTYIREC "
                . "no coincide con el catálogo fiscal."
            );
        }

        // Buscar únicamente evidencia explícita de país en la parte.
        $partyCountryId = !empty($partyLines)
            ? $this->detectCountryIdFromParty($partyLines, null)
            : null;

        if (
            $structuredCountryId !== null
            && $partyCountryId !== null
            && $structuredCountryId !== (int) $partyCountryId
        ) {
            throw new \DomainException(
                "La parte '{$name}' informa país "
                . "{$structuredCountryAlpha2} en PTYIREC, "
                . "pero sus datos textuales indican otro país."
            );
        }

        if (
            $countryId !== null
            && $partyCountryId !== null
            && $countryId !== (int) $partyCountryId
        ) {
            throw new \DomainException(
                "La parte '{$name}' declara {$taxType}, pero el país informado "
                . "en sus datos no coincide con el catálogo fiscal."
            );
        }

        if (
            $countryId === null
            && $structuredCountryId !== null
        ) {
            $countryId = $structuredCountryId;
        }

        if ($countryId === null && $partyCountryId !== null) {
            $countryId = (int) $partyCountryId;
        }

        if ($countryId === null && $originPort) {
            $countryId = $originPort->country_id ?: null;
        }

        if (\Schema::hasColumn('clients', 'country_id') && is_null($countryId)) {
            throw new \DomainException(
                "No se pudo determinar el país para el cliente '{$name}'."
            );
        }

        // 1) Buscar por identidad fiscal completa.
        if ($normTaxId) {
            $client = Client::query()
                ->where('tax_id', $normTaxId)
                ->where('country_id', $countryId)
                ->first();

            if ($client) {
                Log::info(
                    'Cliente existente encontrado por tax_id y país',
                    [
                        'client_id' => $client->id,
                        'tax_id' => $normTaxId,
                        'country_id' => $countryId,
                    ]
                );

                return $client;
            }
        }

        // 2) Buscar por nombre + país únicamente cuando la fuente NO
        // informó identificador fiscal. Si informó tax_id, ese identificador
        // tiene prioridad y un nombre coincidente no puede contradecirlo.
        if (!$normTaxId) {
            $client = Client::query()
                ->where('legal_name', $name)
                ->where('country_id', $countryId)
                ->first();

            if ($client) {
                Log::info(
                    'Cliente existente encontrado por nombre y país',
                    [
                        'client_id' => $client->id,
                        'name' => $name,
                        'country_id' => $countryId,
                    ]
                );

                return $client;
            }
        }

        // El tipo documental sólo se asigna si la fuente aportó un número
        // fiscal y además declaró explícitamente qué tipo es.
        $documentTypeId = $normTaxId
            ? $this->resolveDocumentTypeId($taxType, $countryId)
            : null;

        $client = Client::withoutEvents(fn () => Client::create([
            'tax_id' => $normTaxId,
            'country_id' => $countryId,
            'document_type_id' => $documentTypeId,
            'legal_name' => $name,
            'commercial_name' => $name,
            'status' => 'active',
            'created_by_company_id' => $companyId,
            'verified_at' => null,

            'address' => $clientData['address'] ?? null,
            'email' => $clientData['email'] ?? null,
            'notes' => 'Cliente creado desde archivo KLine DAT',
        ]));

        Log::info('Cliente creado desde KLine', [
            'client_id' => $client->id,
            'legal_name' => $client->legal_name,
            'tax_id' => $client->tax_id,
            'country_id' => $client->country_id,
            'document_type_id' => $client->document_type_id,
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
                'default_document_type_id' => null,
                'default_country_id' => null
            ],
            'cargo' => [
                'default_cargo_type_id' => 1,
                'default_packaging_type_id' => 1,
                'default_freight_terms' => 'prepaid'
            ]
        ];
    }

    /**
     * País ISO2 estructurado informado por K-Line junto al rol:
     * 0001SHMX2554480...
     * 0001SHUS2545869...
     */
    protected function extractStructuredPartyCountry(
        array $data,
        string $role
    ): ?string {
        $role = strtoupper($role);

        if (!in_array($role, ['SH', 'CN', 'NP'], true)) {
            throw new \InvalidArgumentException(
                "Rol PTYIREC K-Line inválido: {$role}"
            );
        }

        foreach ($data['PTYIREC0'] ?? [] as $raw) {
            $line = Str::upper(
                Str::ascii(trim((string) $raw))
            );

            if (
                preg_match(
                    '/^\d+' . preg_quote($role, '/') . '([A-Z]{2})(?=\d)/',
                    $line,
                    $matches
                )
            ) {
                $alpha2 = $matches[1];

                $exists = \App\Models\Country::query()
                    ->whereRaw(
                        'UPPER(alpha2_code) = ?',
                        [$alpha2]
                    )
                    ->exists();

                if ($exists) {
                    return $alpha2;
                }
            }
        }

        return null;
    }


    // Extrae bloques de líneas de PTYIREC0 para SHIPPER / CONSIGNEE (heurística simple)
    protected function extractPartyLinesFromPTYI(array $data): array
    {
        $shipper = [];
        $consignee = [];
        $notify = [];

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
            } elseif (preg_match('/^(\d+)NP\S*\s+(.+)$/', $trim, $m)) {
                $notify[] = $m[2];
            }
        }

        return [$shipper, $consignee, $notify];
    }

    // Construye clientData (name/tax/email/address) a partir de líneas
    protected function buildClientDataFromLines(array $lines): array
    {
        $name = null; $tax = null; $taxType = null; $email = null;

        foreach ($lines as $ln) {
            $trim = trim($ln);
            if ($trim === '') continue;

            // email
            if (!$email && preg_match('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', $trim, $m)) {
                $email = $m[0];
            }

            // FIX bug QA #4: usar extractTaxIdFromLine que ya detecta NIT/CUIT/CNPJ/RUC.
            // Antes solo detectaba CUIT argentino (\d{2}-\d{8}-\d) y NIT colombiano "860.025.792-3" quedaba afuera.
            $identity = $this->extractTaxIdentityFromLine($trim);

            if ($identity) {
                if (!$tax) {
                    $tax = $identity['tax_id'];
                }

                // Si primero apareció un TAX ID genérico y después el mismo
                // número con marcador específico, conservar el tipo explícito.
                if (
                    !$taxType
                    && $identity['tax_id'] === $tax
                    && !empty($identity['tax_type'])
                ) {
                    $taxType = $identity['tax_type'];
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

        // address: dirección limpia de la parte (segmentos sin el nombre, por cleanFileAddress).
        // Antes se guardaba el bloque entero concatenado (nombre + fiscal + contacto).
        $address = $this->buildAddressFromPartyLines($lines);

        return [
            'name'    => $name,
            'tax_id'   => $tax,
            'tax_type' => $taxType,
            'email'    => $email,
            'address'  => $address,
        ];
    }

    /**
     * Arma la dirección de una parte Kline a partir de sus líneas PTYIREC.
     * Las líneas son de ancho fijo: los campos se separan por corridas de 2+
     * espacios. El primer segmento de la primera línea es el nombre de la
     * empresa y se descarta; el resto pasa por cleanFileAddress (que quita
     * la línea fiscal NIT/CUIT/CNPJ y corta la cola ATN/ATENCION/TEL).
     * Validado contra los 11 bloques reales de Kline.DAT (13/07/2026).
     */
    protected function buildAddressFromPartyLines(array $lines): ?string
    {
        $parts = [];
        foreach (array_values($lines) as $i => $line) {
            $segs = preg_split('/\s{2,}/', trim((string) $line), -1, PREG_SPLIT_NO_EMPTY);
            if (!is_array($segs) || empty($segs)) {
                continue;
            }
            if ($i === 0) {
                array_shift($segs); // primer segmento de la primera línea = nombre
            }
            if (!empty($segs)) {
                $parts[] = implode(' ', array_map('trim', $segs));
            }
        }

        return $this->cleanFileAddress(implode(' ', $parts));
    }

}
