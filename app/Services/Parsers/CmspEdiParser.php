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
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Exception;

/**
 * PARSER PARA CMSP.EDI - FORMATO EDI CUSCAR UN/EDIFACT
 * 
 * Procesa archivos EDI CUSCAR con estructura:
 * - UNB: Interchange Header
 * - UNH: Message Header (CUSCAR)
 * - BGM: Message Type
 * - TDT: Transport Details (vessel info)
 * - LOC: Location (puertos)
 * - CNI: Container Info
 * - GID: Goods (mercadería)
 * - SGP: Equipment Details (contenedores)
 * - MEA: Measurements (pesos/volúmenes)
 * - NAD: Name and Address (partes)
 * 
 * AGENTE: CMSP (Container Management System Paraguay)
 * FORMATO: UN/EDIFACT CUSCAR D.96B
 */
class CmspEdiParser implements ManifestParserInterface
{
    protected array $stats = [
        'processed_containers' => 0,
        'processed_items' => 0,
        'processed_bls' => 0,
        'errors' => 0,
        'warnings' => []
    ];

    protected array $ediSegments = [];
    protected array $parsedData = [];

    /**
     * Verificar si puede parsear el archivo
     */
    public function canParse(string $filePath): bool
    {
        // Verificar extensión EDI
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        if ($extension !== 'edi') {
            return false;
        }

        try {
            $handle = fopen($filePath, 'r');
            if (!$handle) {
                return false;
            }

            // Buscar patrones característicos EDI CUSCAR
            $foundEdiPattern = false;
            $lineCount = 0;
            
            while (!feof($handle) && $lineCount < 10) {
                $line = fgets($handle);
                if ($line === false) break;
                
                $line = trim($line);
                
                // Buscar marcadores EDI CUSCAR específicos
                if (strpos($line, 'UNH+') !== false && strpos($line, '+CUSCAR:D:96B') !== false ||
                    strpos($line, 'UNB+UNOA') !== false ||
                    strpos($line, 'BGM+85+') !== false) {
                    $foundEdiPattern = true;
                    break;
                }
                
                $lineCount++;
            }
            
            fclose($handle);
            return $foundEdiPattern;
            
        } catch (Exception $e) {
            Log::warning('Error checking CMSP EDI file', [
                'file_path' => $filePath,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Parsear el archivo EDI y retornar resultado
     */
    public function parse(string $filePath): ManifestParseResult
    {
        Log::info('Starting CMSP EDI parsing', [
            'file_path' => $filePath,
            'file_size' => filesize($filePath)
        ]);

        try {
            // 1. Leer y parsear segmentos EDI
            $this->parseEdiFile($filePath);

            // 2. Extraer datos estructurados
            $this->extractStructuredData();

            // 3. Validar datos extraídos
            $errors = $this->validate($this->parsedData);
            if (!empty($errors)) {
                throw new Exception('Errores de validación: ' . implode(', ', $errors));
            }

            // 4. Transformar a formato estándar
            $standardData = $this->transform($this->parsedData);

            // 5. Crear objetos de modelo
            return $this->createModelObjects($standardData);

        } catch (Exception $e) {
            $this->stats['errors']++;
            Log::error('CMSP EDI parsing failed', [
                'file_path' => $filePath,
                'error' => $e->getMessage(),
                'stats' => $this->stats
            ]);
            throw $e;
        }
    }

    /**
     * Parsear archivo EDI en segmentos
     */
    protected function parseEdiFile(string $filePath): void
    {
        $content = file_get_contents($filePath);
        if ($content === false) {
            throw new Exception('No se pudo leer el archivo EDI');
        }

        // Separar por segmentos (cada línea o por separador ')
        $lines = preg_split("/[\r\n']+/", $content);
        $this->ediSegments = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;

            // Parsear segmento EDI
            if (preg_match('/^([A-Z]{3})\+(.+)$/', $line, $matches)) {
                $segmentTag = $matches[1];
                $segmentData = $matches[2];

                // Separar elementos por +
                $elements = explode('+', $segmentData);

                $this->ediSegments[] = [
                    'tag' => $segmentTag,
                    'data' => $segmentData,
                    'elements' => $elements
                ];
            }
        }

        Log::info('EDI segments parsed', [
            'total_segments' => count($this->ediSegments)
        ]);
    }

    /**
     * Extraer datos estructurados de segmentos EDI
     */
    protected function extractStructuredData(): void
    {
        $this->parsedData = [
            'interchange' => [],
            'message' => [],
            'vessel' => [],
            'ports' => [],
            'containers' => [],
            'items' => [],
            'parties' => []
        ];

        $currentContainer = null;
        $currentItem = null;
        $allItems = []; // Coleccionar todos los items

        foreach ($this->ediSegments as $segment) {
            switch ($segment['tag']) {
                case 'UNB':
                    $this->parseInterchangeHeader($segment);
                    break;

                case 'UNH':
                    $this->parseMessageHeader($segment);
                    break;

                case 'BGM':
                    $this->parseMessageType($segment);
                    break;

                case 'TDT':
                    $this->parseTransportDetails($segment);
                    break;

                case 'LOC':
                    $this->parseLocation($segment);
                    break;

                case 'CNI':
                    // Guardar contenedor anterior si existe y tiene items
                    if ($currentContainer !== null && !empty($currentContainer['items'])) {
                        $this->parsedData['containers'][] = $currentContainer;
                    }
                    
                    // Crear nuevo contenedor
                    $currentContainer = [
                        'sequence' => $segment['elements'][0] ?? '',
                        'containers' => [],
                        'items' => [],
                        'references' => []
                    ];
                    break;

                case 'RFF':
                    $this->parseReference($segment, $currentContainer);
                    break;

                case 'MEA':
                    $this->parseMeasurements($segment, $currentContainer, $currentItem);
                    break;

                case 'NAD':
                    $this->parseParty($segment);
                    break;

                case 'GID':
                    $currentItem = [
                        'sequence' => $segment['elements'][0] ?? '',
                        'package_info' => $segment['elements'][1] ?? '',
                        'description' => '',
                        'gross_weight_kg' => 0,
                        'tare_weight_kg' => 0,
                        'volume_m3' => 0,
                        'containers' => []
                    ];
                    
                    // Agregar item al contenedor actual
                    if ($currentContainer !== null) {
                        $currentContainer['items'][] = &$currentItem;
                    }
                    
                    // También guardarlo en lista general
                    $allItems[] = &$currentItem;
                    break;

                case 'SGP':
                    if ($currentItem !== null) {
                        $containerNumber = $segment['elements'][0] ?? '';
                        if (!empty($containerNumber)) {
                            $currentItem['containers'][] = $containerNumber;
                        }
                    }
                    break;

                case 'FTX':
                    $this->parseFreeText($segment, $currentItem);
                    break;
            }
        }

        // Agregar el último contenedor si tiene items
        if ($currentContainer !== null && !empty($currentContainer['items'])) {
            $this->parsedData['containers'][] = $currentContainer;
        }

        // Si no hay contenedores con items, crear uno por defecto con todos los items
        if (empty($this->parsedData['containers']) && !empty($allItems)) {
            $this->parsedData['containers'][] = [
                'sequence' => '1',
                'containers' => [],
                'items' => $allItems,
                'references' => []
            ];
        }
    }

    /**
     * Parsear header de intercambio
     */
    protected function parseInterchangeHeader(array $segment): void
    {
        if (count($segment['elements']) >= 4) {
            $this->parsedData['interchange'] = [
                'syntax_id' => $segment['elements'][0] ?? '',
                'sender' => $segment['elements'][1] ?? '',
                'receiver' => $segment['elements'][2] ?? '',
                'date_time' => $segment['elements'][3] ?? ''
            ];
        }
    }

    /**
     * Parsear header de mensaje
     */
    protected function parseMessageHeader(array $segment): void
    {
        if (count($segment['elements']) >= 2) {
            $messageType = explode(':', $segment['elements'][1] ?? '');
            $this->parsedData['message'] = [
                'reference' => $segment['elements'][0] ?? '',
                'type' => $messageType[0] ?? '',
                'version' => $messageType[1] ?? '',
                'release' => $messageType[2] ?? ''
            ];
        }
    }

    /**
     * Parsear tipo de mensaje
     */
    protected function parseMessageType(array $segment): void
    {
        $this->parsedData['message']['document_type'] = $segment['elements'][0] ?? '';
        $this->parsedData['message']['document_number'] = $segment['elements'][1] ?? '';
    }

    /**
     * Parsear detalles de transporte
     */
    protected function parseTransportDetails(array $segment): void
    {
        if (count($segment['elements']) >= 2) {
            $this->parsedData['vessel'] = [
                'transport_stage' => $segment['elements'][0] ?? '',
                'vessel_name' => $segment['elements'][1] ?? '',
                'voyage_number' => $segment['elements'][2] ?? '',
                'carrier' => explode(':', $segment['elements'][4] ?? '')[0] ?? ''
            ];
        }
    }

    /**
     * Parsear ubicaciones/puertos
     */
    protected function parseLocation(array $segment): void
    {
        if (count($segment['elements']) >= 2) {
            $locationType = $segment['elements'][0] ?? '';
            $portCode = explode(':', $segment['elements'][1] ?? '')[0] ?? '';

            if ($locationType === '9') {
                $this->parsedData['ports']['loading'] = $portCode;
            } elseif ($locationType === '60' || $locationType === '11') {
                $this->parsedData['ports']['discharge'] = $portCode;
            }
        }
    }

    /**
     * Parsear información de contenedor
     */
    protected function parseContainerInfo(array $segment): ?array
    {
        if (count($segment['elements']) >= 1) {
            $containerInfo = [
                'sequence' => $segment['elements'][0] ?? '',
                'containers' => [],
                'items' => [],
                'references' => []
            ];
            
            $this->parsedData['containers'][] = $containerInfo;
            return $containerInfo;
        }
        
        return null;
    }

    /**
     * Parsear referencias
     */
    protected function parseReference(array $segment, ?array &$currentContainer): void
    {
        if (count($segment['elements']) >= 1) {
            $refType = explode(':', $segment['elements'][0] ?? '')[0] ?? '';
            $refValue = explode(':', $segment['elements'][0] ?? '')[1] ?? '';

            if ($refType === 'BM' && $currentContainer) {
                $currentContainer['references']['booking'] = $refValue;
            }
        }
    }

    /**
     * Parsear medidas
     */
    protected function parseMeasurements(array $segment, ?array &$currentContainer, ?array &$currentItem): void
    {
        if (count($segment['elements']) >= 3) {
            $measureType = $segment['elements'][0] ?? '';
            $weightData = explode(':', $segment['elements'][2] ?? '');
            $unit = $weightData[0] ?? '';
            $value = $weightData[1] ?? '';

            if ($currentItem && $unit === 'KGM') {
                if ($measureType === 'AAX') {
                    $currentItem['gross_weight_kg'] = (float) $value;
                } elseif ($measureType === 'AAY') {
                    $currentItem['tare_weight_kg'] = (float) $value;
                }
            }

            if ($currentItem && $unit === 'MTQ' && $measureType === 'AAE') {
                $currentItem['volume_m3'] = (float) $value;
            }
        }
    }

    /**
     * Parsear partes (shipper, consignee, etc.)
     */
    protected function parseParty(array $segment): void
    {
        if (count($segment['elements']) >= 3) {
            $partyType = $segment['elements'][0] ?? '';
            $partyName = $segment['elements'][2] ?? '';

            if ($partyType === 'CN') {
                $this->parsedData['parties']['shipper'] = [
                    'name' => $partyName,
                    'type' => 'shipper'
                ];
            } elseif ($partyType === 'CX') {
                $this->parsedData['parties']['consignee'] = [
                    'name' => $partyName,
                    'type' => 'consignee'
                ];
            } elseif ($partyType === 'CZ') {
                $this->parsedData['parties']['notify'] = [
                    'name' => $partyName,
                    'type' => 'notify'
                ];
            }
        }
    }

    /**
     * Parsear ítem de mercadería
     */
    protected function parseGoodsItem(array $segment, ?array &$currentContainer): ?array
    {
        if (count($segment['elements']) >= 2) {
            $item = [
                'sequence' => $segment['elements'][0] ?? '',
                'package_info' => $segment['elements'][1] ?? '',
                'description' => '',
                'gross_weight_kg' => 0,
                'tare_weight_kg' => 0,
                'volume_m3' => 0,
                'containers' => []
            ];

            if ($currentContainer) {
                $currentContainer['items'][] = &$item;
            } else {
                $this->parsedData['items'][] = $item;
            }

            return $item;
        }

        return null;
    }

    /**
     * Parsear colocación de contenedor
     */
    protected function parseContainerPlacement(array $segment, ?array &$currentContainer, ?array &$currentItem): void
    {
        if (count($segment['elements']) >= 1) {
            $containerNumber = $segment['elements'][0] ?? '';

            if ($currentItem && !empty($containerNumber)) {
                $currentItem['containers'][] = $containerNumber;
            }

            if ($currentContainer && !empty($containerNumber)) {
                $currentContainer['containers'][] = $containerNumber;
            }
        }
    }

    /**
     * Parsear texto libre (descripción)
     */
    protected function parseFreeText(array $segment, ?array &$currentItem): void
    {
        if (count($segment['elements']) >= 4 && $currentItem) {
            $description = $segment['elements'][3] ?? '';
            if (!empty($description)) {
                $currentItem['description'] = $description;
            }
        }
    }

    /**
     * Crear objetos de modelo
     */
    protected function createModelObjects(array $data): ManifestParseResult
{
    return DB::transaction(function () use ($data) {
        $voyage = $this->createVoyage($data);
        $shipment = $this->createShipment($voyage, $data);
        $billOfLading = $this->createBillOfLading($shipment, $data);
        $this->createContainersAndItems($billOfLading, $data);

        Log::info('CMSP EDI parsing completed successfully', [
            'voyage_id' => $voyage->id,
            'shipment_id' => $shipment->id,
            'bill_of_lading_id' => $billOfLading->id,
            'stats' => $this->stats
        ]);

        // CORRECCIÓN: Usar variables correctas
        return ManifestParseResult::success(
            voyage: $voyage,
            shipments: [$shipment],
            containers: $this->getCreatedContainers($data), // CAMBIAR esto
            billsOfLading: [$billOfLading],                 // CAMBIAR esto
            statistics: [
                'processed_containers' => $this->stats['processed_containers'],
                'processed_items' => $this->stats['processed_items'],
                'processed_bls' => $this->stats['processed_bls'],
                'errors' => $this->stats['errors'],
                'warnings' => $this->stats['warnings']
            ]
        );
    });
}

    /**
     * Crear voyage
     */
    protected function createVoyage(array $data): Voyage
    {
        // OBTENER COMPANY_ID CORRECTAMENTE
        $user = auth()->user();
        $companyId = null;

        if ($user->company_id) {
            $companyId = $user->company_id;
        } elseif ($user->userable_type === 'App\Models\Company' && $user->userable_id) {
            $companyId = (int) $user->userable_id;
        } else {
            throw new Exception("Usuario no tiene empresa asignada. User ID: {$user->id}");
        }

         Log::info('createVoyage Debug CMSP', [
            'user_id' => $user?->id,
            'user_email' => $user?->email,
            'user_company_id' => $user?->company_id,
            'user_userable_type' => $user?->userable_type,
            'user_userable_id' => $user?->userable_id,
            'computed_company_id' => $companyId
        ]);

        $originPort = $this->findOrCreatePort($data['ports']['loading'] ?? 'ARBUE');
        $destPort = $this->findOrCreatePort($data['ports']['discharge'] ?? 'PYASU');

        $vesselName = $data['vessel']['vessel_name'] ?? 'CMSP-VESSEL';
        $vessel = $this->findOrCreateVessel($vesselName, $companyId);

        $voyageNumber = ($data['vessel']['voyage_number'] ?? '') . '-' . 
                       ($data['message']['document_number'] ?? uniqid());

        // DETERMINAR cargo_type basado en puertos reales del EDI
        $loadingPort = $data['ports']['loading'] ?? 'ARBUE';
        $dischargePort = $data['ports']['discharge'] ?? 'PYASU';
        
        $cargoType = $this->determineCargTypeFromPorts($loadingPort, $dischargePort);

        return Voyage::create([
            'company_id' => $companyId,
            'voyage_number' => $voyageNumber,
            'lead_vessel_id' => $vessel->id,
            'origin_port_id' => $originPort->id,
            'destination_port_id' => $destPort->id,
            'origin_country_id' => $originPort->country_id,         // ← AGREGAR
            'destination_country_id' => $destPort->country_id,      // ← AGREGAR
            'departure_date' => now(),                              // ← AGREGAR
            'estimated_arrival_date' => now()->addDays(2),         // ← AGREGAR
            'vessel_name' => $vesselName,
            'status' => 'planning',
            'cargo_type' => $cargoType,
            'created_by_user_id' => auth()->id(),
            'manifest_format' => 'CMSP_EDI_CUSCAR',
            'import_source' => 'cmsp_edi_parser'
        ]);
    }

    /**
     * Determinar cargo_type basado en puertos origen/destino del EDI
     */
    protected function determineCargTypeFromPorts(string $loadingPort, string $dischargePort): string
    {
        // Mapeo de puertos a países
        $argentinePorts = ['ARBUE'];
        $paraguayanPorts = ['PYASU'];
        
        $isFromArgentina = in_array($loadingPort, $argentinePorts);
        $isToParaguay = in_array($dischargePort, $paraguayanPorts);
        $isFromParaguay = in_array($loadingPort, $paraguayanPorts);
        $isToArgentina = in_array($dischargePort, $argentinePorts);
        
        // ARBUE → PYASU = Import (para Paraguay)
        if ($isFromArgentina && $isToParaguay) {
            return 'import';
        }
        
        // PYASU → ARBUE = Export (desde Paraguay)
        if ($isFromParaguay && $isToArgentina) {
            return 'export';
        }
        
        // Default: transit (tránsito)
        return 'transit';
    }

    /**
     * Buscar o crear vessel basado en nombre del EDI
     */
    protected function findOrCreateVessel(string $vesselName, int $companyId): Vessel
    {
        // Buscar vessel existente por nombre
        $vessel = Vessel::where('name', $vesselName)
            ->where('company_id', $companyId)
            ->first();

        if (!$vessel) {
            $vessel = Vessel::create([
                'name' => $vesselName,
                'registration_number' => 'CMSP-' . uniqid(),
                'company_id' => $companyId,
                'vessel_type_id' => 1, // Default vessel type
                'flag_country_id' => 2, // Paraguay (PY=2)
                'length_meters' => 60.0,
                'beam_meters' => 12.0,
                'draft_meters' => 3.5,
                'gross_tonnage' => 500,  
                'net_tonnage' => 350,            
                'cargo_capacity_tons' => 2000.0,
                'operational_status' => 'active',
                'active' => true,
                'created_by_user_id' => auth()->id()
            ]);

            Log::info('Vessel creado automáticamente desde EDI', [
                'vessel_name' => $vesselName,
                'vessel_id' => $vessel->id
            ]);
        }

        return $vessel;
    }

    /**
     * Crear shipment
     */
    protected function createShipment(Voyage $voyage, array $data): Shipment
    {
        return Shipment::create([
            'voyage_id' => $voyage->id,
            'vessel_id' => $voyage->lead_vessel_id,
            'shipment_number' => 'CMSP-' . ($data['message']['document_number'] ?? uniqid()),
            'sequence_in_voyage' => $this->getNextSequenceInVoyage($voyage->id),
            'vessel_role' => 'single',
            'cargo_capacity_tons' => $voyage->lead_vessel->cargo_capacity_tons ?? 1000, 
            'status' => 'planning',
            'active' => true,
            'created_by_user_id' => auth()->id()
        ]);
    }

    /**
     * Obtener siguiente número de secuencia para el viaje
     */
    protected function getNextSequenceInVoyage(int $voyageId): int
    {
        $maxSequence = Shipment::where('voyage_id', $voyageId)
            ->max('sequence_in_voyage') ?? 0;
        
        return $maxSequence + 1;
    }

    /**
     * Crear bill of lading y sus ítems (si corresponde)
     */
    protected function createBillOfLading(Shipment $shipment, array $data): BillOfLading
    {
        $shipper   = $this->findOrCreateClient($data['parties']['shipper']   ?? null);
        $consignee = $this->findOrCreateClient($data['parties']['consignee'] ?? null);

        $billNumber = $data['message']['document_number'] ?? ('CMSP-' . uniqid());

        // Fechas base
        $billDate = $this->extractBillDate($data) ?? now()->toDateString();
        $loadingDate = $this->extractLoadingDate($data)
            ?? optional($shipment->voyage)->departure_date
            ?? $billDate;

        // (opcional) Si tu schema lo requiere:
        $dischargeDate = $this->extractDischargeDate($data)
            ?? optional($shipment->voyage)->arrival_date
            ?? $loadingDate;

        // Crear BL
        $bl = BillOfLading::create([
            'shipment_id'               => $shipment->id,
            'bill_number'               => (string) $billNumber,   // evita perder ceros
            'shipper_id'                => $shipper?->id,
            'consignee_id'              => $consignee?->id,
            'loading_port_id'           => $shipment->voyage->origin_port_id,
            'discharge_port_id'         => $shipment->voyage->destination_port_id,
            'bill_type'                 => 'house',
            'origin_country_id'         => $shipment->voyage->origin_country_id,
            'destination_country_id'    => $shipment->voyage->destination_country_id,
            'loading_customs_id'        => null,
            'discharge_customs_id'      => null,
            'primary_cargo_type_id'     => $this->getDefaultCargoTypeId(),
            'primary_packaging_type_id' => $this->getDefaultPackagingTypeId(),
            'freight_terms'             => 'prepaid',
            'is_consolidated'           => false,
            'documentation_complete'    => false,
            'customs_cleared'           => false,
            'cargo_description'         => 'Según detalle',
            'total_packages'            => 0,
            'gross_weight_kg'           => 0,
            'net_weight_kg'             => 0,
            'volume_m3'                 => 0,
            'status'                    => 'draft',
            'issue_date'                => now()->toDateString(),
            'bill_date'                 => $billDate,
            'loading_date'              => $loadingDate,
            // 'discharge_date'         => $dischargeDate, // descomentar si es NOT NULL
            'created_by_user_id'        => auth()->id(),
        ]);

        
        return $bl;
    }

    protected function getDefaultCargoTypeId(): int
    {
        return CargoType::where('active', true)->where('is_common', true)->first()?->id ?? 1;
    }

    protected function getDefaultPackagingTypeId(): int  
    {
        return PackagingType::where('active', true)->where('is_common', true)->first()?->id ?? 1;
    }

    protected function extractBillDate(array $data): ?string
    {
        // Ejemplo: $data['message']['prepared_at'] podría venir del EDI (UNB/DTM)
        $raw = $data['message']['prepared_at'] ?? $data['message']['date_time'] ?? null;
        if (!$raw) return null;

        // Casos típicos: “240929:33:00” (YYMMDD:hh:mm) o “2024-09-29 13:33:00”
        // Tomamos solo la fecha.
        // 1) YYMMDD...
        if (preg_match('/^(\d{2})(\d{2})(\d{2})/', $raw, $m)) {
            $year = (int)$m[1];
            $year += $year >= 70 ? 1900 : 2000; // pivot simple
            $month = $m[2];
            $day = $m[3];
            return sprintf('%04d-%02d-%02d', $year, $month, $day);
        }

        // 2) ISO-like
        $ts = strtotime($raw);
        return $ts ? date('Y-m-d', $ts) : null;
    }


    /**
     * Crear contenedores y items
     */
    protected function createContainersAndItems(BillOfLading $billOfLading, array $data): void
    {
        foreach ($data['containers'] as $containerGroup) {
            foreach ($containerGroup['items'] as $item) {
                $shipmentItem = $this->createShipmentItem($billOfLading, $item);

                foreach ($item['containers'] as $containerNumber) {
                    $this->createContainer($containerNumber, $item, $shipmentItem);
                }

                $this->stats['processed_items']++;
            }
        }
    }

    /**
     * Crear shipment item
     */
    protected function createShipmentItem(BillOfLading $billOfLading, array $itemData): ShipmentItem
{
    // Obtener siguiente número de línea
    $lineNumber = ShipmentItem::where('bill_of_lading_id', $billOfLading->id)
                              ->max('line_number') ?? 0;
    $lineNumber++;

    // SANITIZAR descripción para evitar errores de codificación
    $description = $itemData['description'] ?: 'Mercadería según manifiesto EDI';
    $cleanDescription = mb_convert_encoding($description, 'UTF-8', 'UTF-8');
    $cleanDescription = preg_replace('/[^\x20-\x7E\xC0-\xFF]/', '', $cleanDescription); // Remover caracteres problemáticos
    $cleanDescription = mb_substr($cleanDescription, 0, 1000); // Limitar longitud

    return ShipmentItem::create([
        'bill_of_lading_id' => $billOfLading->id,
        'line_number' => $lineNumber,
        'cargo_type_id' => $this->getDefaultCargoTypeId(),
        'packaging_type_id' => $this->getDefaultPackagingTypeId(),
        'package_quantity' => $this->extractPackageCount($itemData['package_info'] ?? ''),
        'item_description' => $cleanDescription, // USAR DESCRIPCIÓN LIMPIA
        'gross_weight_kg' => $itemData['gross_weight_kg'] ?? 0,
        'net_weight_kg' => ($itemData['gross_weight_kg'] ?? 0) - ($itemData['tare_weight_kg'] ?? 0),
        'volume_m3' => $itemData['volume_m3'] ?? 0,
        'created_by_user_id' => auth()->id()
    ]);
}

    /**
     * Crear contenedor
     */
    protected function createContainer(string $containerNumber, array $itemData, ShipmentItem $shipmentItem): void
{
    if (empty($containerNumber)) return;

    // BUSCAR CONTENEDOR EXISTENTE PRIMERO
    $container = Container::where('container_number', $containerNumber)->first();
    
    if ($container) {
        // Contenedor existe, solo hacer el attach
        if (!$shipmentItem->containers->contains($container->id)) {
            $shipmentItem->containers()->attach($container->id, [
                'package_quantity' => $this->extractPackageCount($itemData['package_info'] ?? ''),
                'gross_weight_kg' => $itemData['gross_weight_kg'] ?? 0,
                'net_weight_kg' => ($itemData['gross_weight_kg'] ?? 0) - ($itemData['tare_weight_kg'] ?? 0),
                'volume_m3' => $itemData['volume_m3'] ?? 0
            ]);
        }
        $this->stats['processed_containers']++;
        return;
    }

    // CONTENEDOR NO EXISTE, CREARLO
    $containerType = ContainerType::where('active', true)->where('is_standard', true)->first();
    
    if (!$containerType) {
        $this->stats['warnings'][] = "Tipo de contenedor no encontrado: {$containerNumber}";
        return;
    }

    $condition = stripos($itemData['description'] ?? '', 'VACIO') !== false ? 'V' : 'L';

    $container = Container::create([
        'container_number' => $containerNumber,
        'container_type_id' => $containerType->id,
        'condition' => $condition,
        'tare_weight_kg' => $itemData['tare_weight_kg'] ?? 2200,
        'max_gross_weight_kg' => 30000,
        'current_gross_weight_kg' => $itemData['gross_weight_kg'] ?? 0,
        'cargo_weight_kg' => ($itemData['gross_weight_kg'] ?? 0) - ($itemData['tare_weight_kg'] ?? 2200),
        'operational_status' => 'loaded',
        'active' => true,
        'created_by_user_id' => auth()->id()
    ]);

    $shipmentItem->containers()->attach($container->id, [
        'package_quantity' => $this->extractPackageCount($itemData['package_info'] ?? ''),
        'gross_weight_kg' => $itemData['gross_weight_kg'] ?? 0,
        'net_weight_kg' => ($itemData['gross_weight_kg'] ?? 0) - ($itemData['tare_weight_kg'] ?? 0),
        'volume_m3' => $itemData['volume_m3'] ?? 0
    ]);

    $this->stats['processed_containers']++;
}

    /**
     * Buscar o crear puerto
     */
    protected function findOrCreatePort(string $portCode): Port
    {
        if (empty($portCode)) {
            throw new Exception('Código de puerto vacío');
        }

        $port = Port::where('code', $portCode)->first();

        if (!$port) {
            $portData = [
                'ARBUE' => ['name' => 'Buenos Aires', 'country_id' => 1],
                'PYASU' => ['name' => 'Asunción', 'country_id' => 2],
                'FNX' => ['name' => 'Puerto FNX', 'country_id' => 2]
            ];

            $data = $portData[$portCode] ?? [
                'name' => "Puerto {$portCode}",
                'country_id' => 2
            ];

            $port = Port::create([
                'code' => $portCode,
                'name' => $portNames[$portCode] ?? "Puerto {$portCode}",
                'country_id' => $portCode === 'ARBUE' ? 1 : 2,
                'port_type' => 'river',
                'active' => true,
                'created_by_user_id' => auth()->id()
            ]);

            Log::info('Puerto creado automáticamente', [
                'port_code' => $portCode,
                'port_id' => $port->id
            ]);
        }

        return $port;
    }

    /**
     * Buscar o crear cliente
     */
    protected function findOrCreateClient(?array $partyData): ?Client
    {
        if (!$partyData || empty($partyData['name'])) {
            return null;
        }
    
        // Obtener company_id
        $user = auth()->user();
        $companyId = $user->company_id ?? ($user->userable_type === 'App\Models\Company' ? $user->userable_id : null);
    
        if (!$companyId) {
            throw new Exception("Usuario no tiene empresa asignada. User ID: {$user->id}");
        }
    
        // 1. Buscar por nombre legal
        $client = Client::where('legal_name', $partyData['name'])
            ->where('created_by_company_id', $companyId)
            ->first();
    
        if ($client) {
            Log::info('Cliente encontrado por nombre legal', [
                'name' => $partyData['name'],
                'client_id' => $client->id
            ]);
            return $client;
        }
    
        // 2. Generar y buscar por tax_id
        $taxId = substr(strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $partyData['name'])), 0, 4) . 
                 substr(uniqid(), -4);
    
        $clientByTax = Client::where('tax_id', $taxId)
            ->where('country_id', 1)
            ->first();
    
        if ($clientByTax) {
            Log::info('Cliente encontrado por tax_id', [
                'tax_id' => $taxId,
                'client_id' => $clientByTax->id
            ]);
            return $clientByTax;
        }
    
        // 3. Si no existe, crear nuevo cliente
            $client = Client::create([
                'created_by_company_id' => $companyId,
                'legal_name' => $partyData['name'],
                'commercial_name' => $partyData['name'],
                'tax_id' => $taxId,
                'country_id' => 1,
                'document_type_id' => 1,
            ]);
    
            Log::info('Cliente creado automáticamente', [
                'name' => $partyData['name'],
                'client_id' => $client->id
            ]);
    
            return $client;
        }

    /**
     * Extraer cantidad de paquetes
     */
    protected function extractPackageCount(string $packageInfo): int
    {
        if (preg_match('/^(\d+):/', $packageInfo, $matches)) {
            return (int) $matches[1];
        }
        return 1;
    }

    /**
     * Obtener contenedores creados
     */
    protected function getCreatedContainers(array $data): array
    {
        $containerNumbers = [];

        foreach ($data['containers'] as $containerGroup) {
            foreach ($containerGroup['items'] as $item) {
                $containerNumbers = array_merge($containerNumbers, $item['containers']);
            }
        }

        return Container::whereIn('container_number', $containerNumbers)->get()->toArray();
    }

    /**
     * Validar datos parseados
     */
    public function validate(array $data): array
    {
        $errors = [];

        if (empty($data['vessel']['vessel_name'])) {
            $errors[] = 'Información de embarcación faltante';
        }

        if (empty($data['ports']['loading']) || empty($data['ports']['discharge'])) {
            $errors[] = 'Información de puertos incompleta';
        }

        if (empty($data['containers'])) {
            $errors[] = 'No se encontraron contenedores en el archivo';
        }

        foreach ($data['containers'] as $containerGroup) {
            if (empty($containerGroup['items'])) {
                $errors[] = 'Grupo de contenedores sin items';
                continue;
            }

            foreach ($containerGroup['items'] as $item) {
                if (empty($item['containers'])) {
                    $errors[] = 'Item sin contenedores asociados';
                }
            }
        }

        return $errors;
    }

    /**
     * Transformar datos a formato estándar
     */
    public function transform(array $data): array
    {
        return $data;
    }

    /**
     * Obtener información del formato soportado
     */
    public function getFormatInfo(): array
    {
        return [
            'name' => 'CMSP EDI CUSCAR',
            'description' => 'Archivo EDI CUSCAR UN/EDIFACT D.96B de CMSP Paraguay',
            'extensions' => ['edi'],
            'version' => 'D.96B',
            'parser_class' => self::class,
            'capabilities' => [
                'multiple_containers' => true,
                'weight_measurements' => true,
                'volume_measurements' => true,
                'party_information' => true,
                'transport_details' => true,
                'location_codes' => true,
                'booking_references' => true,
                'empty_container_handling' => true,
                'dangerous_goods' => true
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
            'segment_separator' => "'",
            'element_separator' => '+',
            'component_separator' => ':',
            'escape_character' => '?',
            'validate_containers' => true,
            'validate_weights' => true,
            'create_missing_ports' => true,
            'create_missing_clients' => true,
            'default_tare_weight' => 2200,
            'default_max_gross_weight' => 30000
        ];
    }

    protected function extractLoadingDate(array $data): ?string
    {
        // DTM de carga, fecha de “on-board”, o fecha de retiro en origen si existiera
        $raw = $data['bl']['loading_date']
            ?? $data['voyage']['etd']           // Estimated Time of Departure
            ?? $data['shipment']['loading_date']
            ?? null;

        return $this->normalizeAnyDate($raw);
    }

    protected function extractDischargeDate(array $data): ?string
    {
        $raw = $data['bl']['discharge_date']
            ?? $data['voyage']['eta']           // Estimated Time of Arrival
            ?? $data['shipment']['discharge_date']
            ?? null;

        return $this->normalizeAnyDate($raw);
    }

    protected function normalizeAnyDate(?string $raw): ?string
    {
        if (!$raw) return null;

        // Formato YYMMDD (p.ej. 250826) o YYMMDD:hh:mm (p.ej. 250826:13:33)
        if (preg_match('/^(\d{2})(\d{2})(\d{2})/', $raw, $m)) {
            $y = (int)$m[1]; $y += $y >= 70 ? 1900 : 2000;
            return sprintf('%04d-%02d-%02d', $y, $m[2], $m[3]);
        }

        // ISO-like o cualquier cosa que strtotime entienda
        $ts = strtotime($raw);
        return $ts ? date('Y-m-d', $ts) : null;
    }

    protected function toFloat($v): float
    {
        if ($v === null || $v === '') return 0.0;
        // quita espacios y miles
        $s = trim((string)$v);
        $s = str_replace(['.', ' '], ['', ''], $s); // 25.370,05 -> 25370,05
        $s = str_replace([','], ['.'], $s);         // 25370,05 -> 25370.05
        return (float)$s;
    }

    

    protected function createSingleItemFromHeader(BillOfLading $bl, array $header): ShipmentItem
{
    $lineNumber = $this->nextLineNumber($bl);  // Agregar esta línea

    $desc   = $header['cargo_description'] ?? 'Según detalle';
    $gross  = $this->toFloat($header['gross_weight'] ?? 0);
    $net    = $this->toFloat($header['net_weight']   ?? 0);
    $volume = $this->toFloat($header['measurement']  ?? 0);

    // toma cantidad desde el header si existe; si no, 0
    $qty = (int)($header['packages'] ?? $header['package_quantity'] ?? 0);

    return ShipmentItem::create([
        'bill_of_lading_id'   => $bl->id,
        'sequence_number'         => $lineNumber,
        'description'    => mb_substr($desc, 0, 1000),
        'cargo_type_id'       => $bl->primary_cargo_type_id ?? 1,
        'packaging_type_id'   => $bl->primary_packaging_type_id ?? 1,
        'package_quantity'    => max(0, $qty),       // ← CLAVE PARA TU ERROR
        'gross_weight_kg'     => max(0.0, $gross),
        'net_weight_kg'       => max(0.0, $net),
        'volume_m3'           => max(0.0, $volume),
        'created_by_user_id'  => auth()->id(),
    ]);
}


    protected function createItems(BillOfLading $bl, array $lines, array $header): void
{
    if (empty($lines)) {
        $this->createSingleItemFromHeader($bl, $header);
        return;
    }

    foreach ($lines as $i => $row) {
        $lineNumber = $this->nextLineNumber($bl);  // Agregar esta línea

        $desc   = $row['description'] ?? ($header['cargo_description'] ?? 'Según detalle');
        $gross  = $this->toFloat($row['gross_weight'] ?? 0);
        $net    = $this->toFloat($row['net_weight']   ?? 0);
        $volume = $this->toFloat($row['measurement']  ?? $row['cbm'] ?? 0);

        // soporta ambos nombres; usa el que exista
        $qty = (int)($row['packages'] ?? $row['package_quantity'] ?? 0);

        ShipmentItem::create([
            'bill_of_lading_id' => $bl->id,
            'sequence_number'   => $lineNumber,
            'description'       => mb_substr($desc, 0, 1000),
            'cargo_type_id'     => $bl->primary_cargo_type_id ?? 1,
            'packaging_type_id' => $bl->primary_packaging_type_id ?? 1,
            'package_count'     => max(0, $qty),     // ← CLAVE
            'gross_weight_kg'   => max(0.0, $gross),
            'net_weight_kg'     => max(0.0, $net),
            'volume_m3'         => max(0.0, $volume),
            'created_by_user_id' => auth()->id(),
        ]);
    }

    
}

protected function nextLineNumber(BillOfLading $bl): int
{
    return (int) ($bl->shipmentItems()->max('line_number') ?? 0) + 1;}

}