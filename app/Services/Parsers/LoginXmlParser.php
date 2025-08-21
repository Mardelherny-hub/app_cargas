<?php

namespace App\Services\Parsers;

use App\Contracts\ManifestParserInterface;
use App\ValueObjects\ManifestParseResult;
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

    // Mapeo de países por código NCM/origen
    protected array $countryMapping = [
        'default' => 'ARG', // Argentina por defecto para Login
        'argentina' => 'ARG',
        'paraguay' => 'PRY',
        'brasil' => 'BRA',
        'uruguay' => 'URY'
    ];

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

        if (!$user->company_id) {
            throw new Exception('Usuario sin empresa asignada para importación');
        }

        return [
            'company_id' => $user->company_id,
            'user_id' => $user->id
        ];
    }

    /**
     * Parsear el archivo Login XML con contexto específico
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

            // 1. Leer y parsear XML
            $xmlContent = file_get_contents($filePath);
            $xml = new SimpleXMLElement($xmlContent);
            
            // 2. Extraer datos del XML
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
            $billOfLading = $this->createBillOfLading($transformedData, $shipment, $context);
            $shipmentItems = $this->createShipmentItems($transformedData, $billOfLading, $context);
            
            DB::commit();

            Log::info('Login XML parseado exitosamente', [
                'voyage_id' => $voyage->id,
                'shipment_id' => $shipment->id,
                'bill_of_lading_id' => $billOfLading->id,
                'items_created' => count($shipmentItems)
            ]);

            return new ManifestParseResult(
                success: true,
                voyage: $voyage,
                shipments: collect([$shipment]),
                billsOfLading: collect([$billOfLading]),
                shipmentItems: collect($shipmentItems),
                summary: [
                    'format' => 'Login XML',
                    'bills_of_lading' => 1,
                    'containers' => count($transformedData['containers']),
                    'total_weight_kg' => array_sum(array_column($transformedData['containers'], 'gross_weight_kg')),
                    'shipper' => $transformedData['header']['shipper_name'] ?? 'N/A',
                    'consignee' => $transformedData['header']['consignee_name'] ?? 'N/A'
                ]
            );

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error parseando Login XML', [
                'file_path' => $filePath,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return new ManifestParseResult(
                success: false,
                errors: [$e->getMessage()]
            );
        }
    }

    /**
     * Extraer datos del XML parseado
     */
    protected function extractDataFromXml(SimpleXMLElement $xml): array
    {
         Log::debug('=== EXTRAYENDO DATOS XML ===');

        $data = [
            'header' => [],
            'containers' => []
        ];

        // Extraer datos del header del primer BillOfLading
        Log::debug('Buscando BillOfLading->BillOfLadingHeader');
        if (isset($xml->BillOfLading->BillOfLadingHeader)) {
            Log::debug('Header encontrado, extrayendo datos');
             Log::debug('Datos del header:', (array)$xml->BillOfLading->BillOfLadingHeader);        
            $header = $xml->BillOfLading->BillOfLadingHeader;
            
            $data['header'] = [
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
                'cargo_description' => (string)$header->DescriptionOfPackagesAndGoods ?? null
            ];
        }

        // Extraer datos de contenedores del primer BillOfLading
        if (isset($xml->BillOfLading->BillOfLadingLineDetail->BillOfLadingLine)) {
            foreach ($xml->BillOfLading->BillOfLadingLineDetail->BillOfLadingLine as $line) {
                $container = [
                    'line_number' => (int)$line->BillOfLadingLineNumber ?? 0,
                    'container_number' => (string)$line->Container ?? null,
                    'container_type' => (string)$line->Type ?? null,
                    'tare_weight_kg' => $this->parseWeight((string)$line->Tare),
                    'net_weight_kg' => $this->parseWeight((string)$line->NetWeight),
                    'gross_weight_kg' => $this->parseWeight((string)$line->GrossWeight),
                    'vgm' => isset($line->Vgm) ? $this->parseWeight((string)$line->Vgm) : null,
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

                $data['containers'][] = $container;
            }
        }

        return $data;
    }

    /**
     * Parsear peso desde string (puede tener comas como separador decimal)
     */
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
     * Validar datos extraídos
     */
    public function validate(array $data): array
    {
        $errors = [];

        // Validar header
        if (empty($data['header']['bill_number'])) {
            $errors[] = 'Número de Bill of Lading requerido en el XML';
        }

        if (empty($data['header']['shipper_name']) && empty($data['header']['shipper_cuit'])) {
            $errors[] = 'Información del shipper (nombre o CUIT) requerida en el XML';
        }

        if (empty($data['header']['consignee_name'])) {
            $errors[] = 'Nombre del consignee requerido en el XML';
        }

        if (empty($data['header']['loading_port'])) {
            $errors[] = 'Puerto de carga requerido en el XML';
        }

        if (empty($data['header']['discharge_port'])) {
            $errors[] = 'Puerto de descarga requerido en el XML';
        }

        // Validar contenedores
        if (empty($data['containers'])) {
            $errors[] = 'Al menos un contenedor es requerido en el XML';
        }

        foreach ($data['containers'] as $index => $container) {
            $lineNumber = $index + 1;
            
            if (empty($container['container_number'])) {
                $errors[] = "Número de contenedor requerido en línea {$lineNumber}";
            }

            if (empty($container['container_type'])) {
                $errors[] = "Tipo de contenedor requerido en línea {$lineNumber}";
            }

            if ($container['gross_weight_kg'] <= 0) {
                $errors[] = "Peso bruto debe ser mayor a 0 en línea {$lineNumber}";
            }

            if ($container['net_weight_kg'] > $container['gross_weight_kg']) {
                $errors[] = "Peso neto no puede ser mayor al peso bruto en línea {$lineNumber}";
            }

            if ($container['tare_weight_kg'] <= 0) {
                $errors[] = "Peso tara debe ser mayor a 0 en línea {$lineNumber}";
            }
        }

        return $errors;
    }

    public function transform(array $data): array
    {
        return [
            'voyage' => [
                'voyage_number' => $data['header']['voyage_number'] ?? 'LGN-' . date('Y-m-d'),
                'vessel_name' => $data['header']['vessel_name'] ?? 'Login Vessel',
                'origin_port' => $data['header']['loading_port'] ?? 'Unknown',
                'destination_port' => $data['header']['discharge_port'] ?? 'Unknown',
                'departure_date' => $this->parseDate($data['header']['loading_date']),
                'estimated_arrival_date' => $this->parseDate($data['header']['loading_date'], '+3 days')
            ],
            'shipment' => [
                'shipment_number' => 'LGN-' . date('Ymd') . '-001',
                'status' => 'planning'
            ],
            'bill_of_lading' => [
                'bill_number' => $data['header']['bill_number'],
                'shipper_name' => $data['header']['shipper_name'],
                'consignee_name' => $data['header']['consignee_name'],
                'notify_party_name' => $data['header']['notify_party_name'],
                'bill_date' => $this->parseDate($data['header']['bill_date']),
                'loading_date' => $this->parseDate($data['header']['loading_date']),
                'cargo_description' => 'Login XML Import - Multiple containers',
                'total_containers' => count($data['containers']),
                'total_weight_kg' => array_sum(array_column($data['containers'], 'gross_weight_kg'))
            ],
            'containers' => array_map(function($container) {
                return [
                    'line_number' => $container['line_number'],
                    'container_number' => $container['container_number'],
                    'container_type' => $this->mapContainerType($container['container_type']),
                    'tare_weight_kg' => $container['tare_weight_kg'],
                    'net_weight_kg' => $container['net_weight_kg'],
                    'gross_weight_kg' => $container['gross_weight_kg'],
                    'vgm' => $container['vgm'],
                    'seals' => implode(', ', $container['seals']),
                    'commodity_code' => implode(', ', $container['ncm_codes']),
                    'package_description' => $this->getContainerDescription($container['container_type']),
                    'country_of_origin' => $this->countryMapping['default']
                ];
            }, $data['containers'])
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

        return Voyage::create([
            'voyage_number' => $data['voyage']['voyage_number'],
            'company_id' => $company->id,
            'origin_country_id' => $originPort->country_id,
            'destination_country_id' => $destinationPort->country_id,
            'origin_port_id' => $originPort->id,
            'destination_port_id' => $destinationPort->id,
            'departure_date' => $data['voyage']['departure_date'],
            'estimated_arrival_date' => $data['voyage']['estimated_arrival_date'],
            'voyage_type' => 'import',
            'cargo_type' => 'containerized',
            'status' => 'planning',
            'is_convoy' => false,
            'vessel_count' => 1,
            'total_cargo_capacity_tons' => $data['voyage']['total_weight_tons'] ?? 1000.0,
            'total_container_capacity' => count($data['containers']),
            'active' => true,
            'created_date' => now(),
            'created_by_user_id' => $context['user_id']
        ]);
    }

    /**
     * Buscar puerto por nombre
     */
    protected function findPortByName(?string $portName): ?\App\Models\Port
    {
        if (empty($portName)) {
            return null;
        }

        // Normalizaciones conocidas de puertos
        $portMappings = [
            'BUENOS AIRES' => ['Buenos Aires', 'ARBUE', 'BA'],
            'SALVADOR' => ['Salvador', 'BRSAL', 'Salvador de Bahía'],
            'SANTOS' => ['Santos', 'BRSTS'],
            'ASUNCION' => ['Asunción', 'PYASU', 'Asunción'],
            'VILLETA' => ['Villeta', 'PYTVT']
        ];

        $normalizedName = strtoupper(trim($portName));
        
        // Buscar primero por coincidencia exacta
        $port = Port::whereRaw('UPPER(name) = ?', [$normalizedName])->first();
        if ($port) {
            return $port;
        }

        // Buscar por código de puerto
        $port = Port::whereRaw('UPPER(code) = ?', [$normalizedName])->first();
        if ($port) {
            return $port;
        }

        // Buscar en mapeos conocidos
        foreach ($portMappings as $xmlName => $variations) {
            if ($normalizedName === $xmlName) {
                foreach ($variations as $variation) {
                    $port = Port::whereRaw('UPPER(name) LIKE ?', ['%' . strtoupper($variation) . '%'])->first();
                    if ($port) {
                        return $port;
                    }
                }
            }
        }

        // Buscar por coincidencia parcial
        return Port::whereRaw('UPPER(name) LIKE ?', ['%' . $normalizedName . '%'])->first();
    }

    /**
     * Crear Shipment
     */
    protected function createShipment(array $data, Voyage $voyage, array $context): Shipment
    {
        return Shipment::create([
            'shipment_number' => $data['shipment']['shipment_number'],
            'voyage_id' => $voyage->id,
            'sequence_in_voyage' => 1,
            'vessel_role' => 'single',
            'is_lead_vessel' => true,
            'cargo_capacity_tons' => $data['voyage']['total_weight_tons'] ?? 500.0,
            'container_capacity' => count($data['containers']),
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
            $data['bill_of_lading']['shipper_cuit'] ?? null
        );
        
        $consignee = $this->findOrCreateClient(
            $data['bill_of_lading']['consignee_name'], 
            'consignee',
            $context
        );
        
        $notifyParty = null;
        if (!empty($data['bill_of_lading']['notify_party_name'])) {
            $notifyParty = $this->findOrCreateClient(
                $data['bill_of_lading']['notify_party_name'], 
                'notify_party',
                $context
            );
        }

        return BillOfLading::create([
            'shipment_id' => $shipment->id,
            'bill_number' => $data['bill_of_lading']['bill_number'],
            'shipper_id' => $shipper?->id,
            'consignee_id' => $consignee?->id,
            'notify_party_id' => $notifyParty?->id,
            'loading_port_id' => $shipment->voyage->origin_port_id,
            'discharge_port_id' => $shipment->voyage->destination_port_id,
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

        foreach ($data['containers'] as $containerData) {
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

            // Crear Container asociado
            $containerType = ContainerType::where('name', 'LIKE', '%' . $containerData['container_type'] . '%')->first()
                            ?? ContainerType::first();

            $container = Container::create([
                'container_number' => $containerData['container_number'],
                'container_type_id' => $containerType?->id,
                'tare_weight_kg' => $containerData['tare_weight_kg'],
                'max_gross_weight_kg' => $containerData['gross_weight_kg'] + 5000,
                'current_gross_weight_kg' => $containerData['gross_weight_kg'],
                'cargo_weight_kg' => $containerData['net_weight_kg'],
                'condition' => 'L',
                'operational_status' => 'loaded',
                'shipper_seal' => $containerData['seals'],
                'active' => true,
                'created_date' => now(),
                'created_by_user_id' => 1
            ]);

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
     * Encontrar o crear cliente con datos reales
     */
    protected function findOrCreateClient(?string $name, string $type, array $context, ?string $taxId = null): ?Client
    {
        if (empty($name)) {
            return null;
        }

        // Limpiar el nombre
        $cleanName = $this->cleanClientName($name);
        
        // Buscar cliente existente por nombre
        $client = Client::where('name', 'LIKE', '%' . $cleanName . '%')->first();
        
        if ($client) {
            return $client;
        }

        // Si tiene CUIT/CNPJ, buscar por tax_id
        if ($taxId) {
            $cleanTaxId = preg_replace('/[^0-9]/', '', $taxId);
            $client = Client::where('tax_id', $cleanTaxId)->first();
            
            if ($client) {
                return $client;
            }
        }

        // Crear nuevo cliente con datos del XML
        return Client::create([
            'name' => $cleanName,
            'client_type' => $type,
            'tax_id' => $taxId ? preg_replace('/[^0-9]/', '', $taxId) : null,
            'email' => $this->generateClientEmail($cleanName, $type),
            'active' => true,
            'created_date' => now(),
            'created_by_user_id' => $context['user_id']
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
}