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
    public function parse(string $filePath, array $options = []): ManifestParseResult
    {
        $startTime = microtime(true);
        
        try {
            // Validar vessel_id obligatorio
            if (empty($options['vessel_id'])) {
                return ManifestParseResult::failure([
                    'vessel_id es obligatorio para procesar archivo G2Ocean'
                ]);
            }

            // Crear registro de importación
            $importRecord = $this->createImportRecord($filePath, $options);

            // Parsear XML
            $xml = simplexml_load_file($filePath);
            if (!$xml) {
                return ManifestParseResult::failure([
                    'No se pudo parsear el archivo XML de G2Ocean'
                ]);
            }

            Log::info('Starting G2Ocean parse process', [
                'file_path' => $filePath,
                'vessel_id' => $options['vessel_id']
            ]);

            return DB::transaction(function () use ($xml, $options, $importRecord, $startTime, $filePath) {
                // Extraer datos del envelope
                $envelopeData = $this->extractEnvelopeData($xml);
                
                // Obtener todos los bills of lading
                $billsData = $this->extractBillsOfLading($xml);
                
                if (empty($billsData)) {
                    return ManifestParseResult::failure([
                        'No se encontraron conocimientos de embarque en el archivo G2Ocean'
                    ]);
                }

                // Verificar duplicados
                $duplicateCheck = $this->checkForDuplicateBills($billsData);
                if ($duplicateCheck['all_duplicates']) {
                    return ManifestParseResult::failure([
                        'Este archivo ya fue importado anteriormente. Todos los conocimientos de embarque ya existen en el sistema.'
                    ], [], $this->stats);
                }

                // Usar el primer BL para crear voyage y shipment
                $firstBL = reset($billsData);
                
                // Crear voyage
                $voyage = $this->createVoyage($firstBL, $options);
                
                // Crear shipment
                $shipment = $this->createShipment($voyage, $options);

                // Procesar cada BL
                $createdBills = [];
                $createdItems = [];
                
                foreach ($billsData as $blData) {
                    try {
                        // Verificar duplicado
                        $blNumber = $blData['bl_number'];
                        $existingBL = BillOfLading::where('bill_number', $blNumber)->first();
                        
                        if ($existingBL) {
                            $this->stats['warnings'][] = "BL {$blNumber} ya existe, omitiendo";
                            continue;
                        }

                        // Crear BillOfLading
                        $bill = $this->createBillOfLading($shipment, $blData);
                        $createdBills[] = $bill;

                        // Crear ShipmentItems
                        $items = $this->createShipmentItems($bill, $blData);
                        $createdItems = array_merge($createdItems, $items);

                        $this->stats['created_bills']++;
                        
                    } catch (Exception $e) {
                        $this->stats['errors']++;
                        $this->stats['warnings'][] = "Error procesando BL {$blData['bl_number']}: " . $e->getMessage();
                        Log::error('Error processing G2Ocean BL', [
                            'bl' => $blData['bl_number'],
                            'error' => $e->getMessage()
                        ]);
                    }
                }

                if (empty($createdBills)) {
                    return ManifestParseResult::failure([
                        'No se pudo crear ningún Bill of Lading del archivo G2Ocean'
                    ], $this->stats['warnings'], $this->stats);
                }

                Log::info('G2Ocean parsing completed successfully', [
                    'voyage_id' => $voyage->id,
                    'bills_created' => count($createdBills),
                    'items_created' => count($createdItems)
                ]);

                // Completar registro de importación
                $this->completeImportRecord($importRecord, $voyage, $createdBills, $createdItems, [], $startTime);

                return ManifestParseResult::success(
                    voyage: $voyage,
                    shipments: [$shipment],
                    containers: [],
                    billsOfLading: $createdBills,
                    statistics: array_merge($this->stats, [
                        'processed_items' => count($createdItems),
                        'total_bills' => count($createdBills),
                        'import_id' => $importRecord->id
                    ])
                );
            });

        } catch (Exception $e) {
            Log::error('Critical error in G2Ocean parser', [
                'file_path' => $filePath,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            if (isset($importRecord)) {
                $processingTime = microtime(true) - $startTime;
                $importRecord->markAsFailed([$e->getMessage()], [
                    'processing_time_seconds' => round($processingTime, 2),
                    'errors_count' => 1
                ]);
            }

            return ManifestParseResult::failure([
                'Error al procesar archivo G2Ocean: ' . $e->getMessage()
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
            return ['name' => 'Desconocido', 'address' => '', 'tax_id' => null];
        }

        $name = (string)($partyInfo->organizationName1 ?? '');
        $address = $this->buildAddress($partyInfo->addressInfo ?? null);
        $taxId = $this->extractTaxId($name . ' ' . $address);

        return [
            'name' => $name ?: 'Desconocido',
            'address' => $address,
            'tax_id' => $taxId
        ];
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
        
        return [
            'item_number' => (int)($detail->itemSNo ?? 1),
            'description' => $description,
            'packages' => (int)($detail->noOfPkgs ?? 1),
            'package_type' => (string)($detail->pkgType ?? 'PACKAGES'),
            'weight_mt' => (float)($detail->weight ?? 0),
            'weight_unit' => (string)($detail->weightUOM ?? 'MT'),
            'volume' => (float)($detail->measure ?? 0),
            'volume_unit' => (string)($detail->measureUOM ?? 'M³'),
            'marks' => (string)($detail->marks ?? 'S/M'),
            // Extraer NCM/HS del texto de descripción
            'commodity_code' => $this->extractCommodityCode($description),
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
     * Parsear fecha desde formato YYYYMMDD
     */
    protected function parseDate(string $dateStr): ?Carbon
    {
        if (empty($dateStr) || strlen($dateStr) !== 8) {
            return null;
        }

        try {
            return Carbon::createFromFormat('Ymd', $dateStr);
        } catch (Exception $e) {
            Log::warning('Error parsing date: ' . $dateStr);
            return null;
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
    protected function createVoyage(array $blData, array $options): Voyage
    {
        // Obtener company_id
        $user = auth()->user();
        if ($user->company_id) {
            $companyId = $user->company_id;
        } elseif ($user->userable_type === 'App\Models\Company' && $user->userable_id) {
            $companyId = (int) $user->userable_id;
        } else {
            throw new Exception("Usuario no tiene empresa asignada");
        }

        // Obtener vessel
        $vesselId = $options['vessel_id'];
        $vessel = Vessel::find($vesselId);
        if (!$vessel) {
            throw new Exception("Vessel con ID {$vesselId} no encontrado");
        }

        // Crear puertos
        $originPort = $this->findOrCreatePort($blData['loading_port_code']);
        $destinationPort = $this->findOrCreatePort($blData['discharge_port_code']);

        // Generar voyage number
        $voyageNumber = 'G2O-' . ($blData['vessel_name'] ?? 'VESSEL') . '-' . ($blData['voyage_number'] ?? date('Ymd'));

        // Verificar duplicado
        $existingVoyage = Voyage::where('voyage_number', $voyageNumber)
            ->where('company_id', $companyId)
            ->first();

        if ($existingVoyage) {
            return $existingVoyage;
        }

        // Fechas
        $etd = $blData['loading_date'] ?: Carbon::now()->addDays(7);
        $eta = (clone $etd)->addDays(14); // Viaje marítimo típico

        $voyageData = [
            'company_id' => $companyId,
            'voyage_number' => $voyageNumber,
            'origin_port_id' => $originPort->id,
            'destination_port_id' => $destinationPort->id,
            'lead_vessel_id' => $vessel->id,
            'origin_country_id' => $originPort->country_id,
            'destination_country_id' => $destinationPort->country_id,
            'voyage_type' => 'single_vessel',
            'cargo_type' => 'import',
            'status' => 'planning',
            'created_by_user_id' => $user->id,
        ];

        // Agregar fechas según campos disponibles
        // Campos de fecha obligatorios
        $voyageData['departure_date'] = $etd;
        $voyageData['estimated_arrival_date'] = $eta;

        $voyage = Voyage::create($voyageData);
        $this->stats['created_voyages']++;
        
        return $voyage;
    }

    /**
     * Crear shipment
     */
    protected function createShipment(Voyage $voyage, array $options): Shipment
    {
        $vesselId = $options['vessel_id'];
        $vessel = Vessel::find($vesselId);

        return Shipment::create([
            'voyage_id' => $voyage->id,
            'vessel_id' => $vessel->id,
            'shipment_number' => 'G2O-SHIP-' . now()->format('YmdHis'),
            'sequence_in_voyage' => 1,
            'vessel_role' => 'single',
            'cargo_capacity_tons' => $vessel->cargo_capacity_tons ?? 10000.0,
            'container_capacity' => $vessel->container_capacity ?? 0,
            'status' => 'planning',
            'active' => true,
            'created_by_user_id' => auth()->id()
        ]);
    }

    /**
     * Crear BillOfLading
     */
    protected function createBillOfLading(Shipment $shipment, array $blData): BillOfLading
    {
        // Crear puertos
        $loadingPort = $this->findOrCreatePort($blData['loading_port_code']);
        $dischargePort = $this->findOrCreatePort($blData['discharge_port_code']);

        // Obtener company_id
        $companyId = $shipment->voyage->company_id;

        // Crear clientes
        $shipper = $this->findOrCreateClient($blData['shipper'], $companyId, $loadingPort);
        $consignee = $this->findOrCreateClient($blData['consignee'], $companyId, $dischargePort);
        $notify = null;
        if (!empty($blData['notify']['name']) && $blData['notify']['name'] !== '(NF) SAME AS CONSIGNEE') {
            $notify = $this->findOrCreateClient($blData['notify'], $companyId, $dischargePort);
        }

        // Calcular totales de carga
        $totalPackages = array_sum(array_column($blData['cargo_items'], 'packages'));
        $totalWeight = array_sum(array_column($blData['cargo_items'], 'weight_mt'));
        $totalVolume = array_sum(array_column($blData['cargo_items'], 'volume'));

        return BillOfLading::create([
            'shipment_id' => $shipment->id,
            'bill_number' => $blData['bl_number'],
            'bill_date' => $blData['issue_date'] ?: now(),
            'loading_date' => $blData['loading_date'] ?: now()->addDays(1),
            'shipper_id' => $shipper->id,
            'consignee_id' => $consignee->id,
            'notify_party_id' => $notify?->id,
            'loading_port_id' => $loadingPort->id,
            'discharge_port_id' => $dischargePort->id,
            'primary_cargo_type_id' => $this->getDefaultCargoTypeId(),
            'primary_packaging_type_id' => $this->getDefaultPackagingTypeId(),
            'total_packages' => max($totalPackages, 1),
            'gross_weight_kg' => $totalWeight * 1000, // MT a KG
            'net_weight_kg' => $totalWeight * 1000 * 0.9, // Estimación 90%
            'volume_m3' => $totalVolume,
            'cargo_description' => $this->buildCargoDescription($blData['cargo_items']),
            'freight_terms' => 'prepaid',
            'status' => 'draft',
            'created_by_user_id' => auth()->id()
        ]);
    }

    /**
     * Crear ShipmentItems
     */
    protected function createShipmentItems(BillOfLading $bill, array $blData): array
    {
        $items = [];
        
        foreach ($blData['cargo_items'] as $cargoItem) {
            $item = ShipmentItem::create([
                'bill_of_lading_id' => $bill->id,
                'line_number' => $cargoItem['item_number'],
                'item_description' => $cargoItem['description'],
                'cargo_type_id' => $this->getDefaultCargoTypeId(),
                'packaging_type_id' => $this->getPackagingTypeByName($cargoItem['package_type']),
                'package_quantity' => $cargoItem['packages'],
                'gross_weight_kg' => $cargoItem['weight_mt'] * 1000, // MT a KG
                'net_weight_kg' => $cargoItem['weight_mt'] * 1000 * 0.9, // Estimación
                'volume_m3' => $cargoItem['volume'],
                'cargo_marks' => $cargoItem['marks'] ?: 'S/M',
                // Campo NCM/HS extraído de la descripción
                'commodity_code' => $cargoItem['commodity_code'] ?? null,
                'created_by_user_id' => auth()->id()
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
            throw new Exception("Código de puerto no puede estar vacío");
        }

        $code = strtoupper(trim($portCode));

        // Buscar existente
        $port = Port::where('code', $code)->first();
        if ($port) {
            return $port;
        }

        // Verificar que el país existe
        $alpha2 = substr($code, 0, 2);
        $countryId = Country::whereRaw('UPPER(alpha2_code)=?', [$alpha2])->value('id');
        
        if (!$countryId) {
            throw new Exception("No se encontró país para código {$alpha2}");
        }

        // Crear puerto
        return Port::create([
            'code' => $code,
            'name' => $this->getPortNameFromCode($code),
            'country_id' => $countryId,
            'active' => true,
            'city' => $this->getCityFromCode($code)
        ]);
    }

    /**
     * Buscar/crear cliente
     */
    protected function findOrCreateClient(array $clientData, int $companyId, Port $defaultPort): Client
    {
        $name = $clientData['name'] ?? 'Cliente Desconocido';
        $taxId = $clientData['tax_id'];

        // Buscar existente
        if ($taxId) {
            $client = Client::where('tax_id', $taxId)->first();
            if ($client) {
                return $client;
            }
        }

        $client = Client::where('legal_name', $name)->first();
        if ($client) {
            return $client;
        }

        // Crear nuevo
        return Client::create([
            'tax_id' => $taxId ?: $this->generateUniqueValidTaxId($name),
            'country_id' => $defaultPort->country_id,
            'document_type_id' => 1,
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

    protected function buildCargoDescription(array $cargoItems): string
    {
        $descriptions = array_column($cargoItems, 'description');
        return implode('; ', array_unique($descriptions)) ?: 'Mercadería general según manifiesto G2Ocean';
    }

    protected function generateUniqueValidTaxId(string $clientName): string
    {
        $base = preg_replace('/[^0-9]/', '', $clientName);
        if (strlen($base) < 3) {
            $base = str_pad($base, 3, '0');
        }
        
        $timestamp = substr(time(), -5);
        $random = str_pad(mt_rand(0, 999), 3, '0', STR_PAD_LEFT);
        
        return substr($base . $timestamp . $random, 0, 11);
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
        $processingTime = microtime(true) - $startTime;
        
        $createdObjects = [
            'voyages' => [$voyage->id],
            'shipments' => [$voyage->shipments()->first()->id ?? null],
            'bills' => array_map(fn($bill) => $bill->id, $bills),
            'items' => array_map(fn($item) => $item->id, $items),
            'containers' => array_map(fn($container) => $container->id, $containers)
        ];
        
        $createdObjects = array_map(fn($ids) => array_filter($ids), $createdObjects);
        
        $importRecord->recordCreatedObjects($createdObjects);
        $importRecord->markAsCompleted([
            'voyage_id' => $voyage->id,
            'processing_time_seconds' => round($processingTime, 2),
            'notes' => 'Importación G2Ocean XML completada exitosamente'
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
                'default_document_type_id' => 1
            ],
            'cargo' => [
                'default_cargo_type_id' => 1,
                'default_packaging_type_id' => 1,
                'convert_mt_to_kg' => true
            ]
        ];
    }
}