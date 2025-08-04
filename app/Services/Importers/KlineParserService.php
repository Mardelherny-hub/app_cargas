<?php
namespace App\Services\Importers;

use App\Models\Voyage;
use App\Models\Shipment;
use App\Models\BillOfLading;
use App\Models\ShipmentItem;
use App\Models\Client;
use App\Models\Port;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class KlineParserService
{
    protected array $lines;
    protected array $stats = [
        'processed' => 0,
        'errors' => 0,
        'warnings' => []
    ];

    public function __construct(string $filePath)
    {
        $this->lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    }

    public function parse(): array
    {
        Log::info('Starting KLine parse process', [
            'total_lines' => count($this->lines),
            'sample_lines' => array_slice($this->lines, 0, 10)
        ]);

        $bills = $this->groupByBillOfLading();
        
        foreach ($bills as $bl) {
            try {
                DB::transaction(function () use ($bl) {
                    $this->storeFromParsedData($bl);
                });
                $this->stats['processed']++;
            } catch (\Exception $e) {
                $this->stats['errors']++;
                $this->stats['warnings'][] = "Error procesando B/L {$bl['bl']}: " . $e->getMessage();
                Log::error('Error processing B/L', [
                    'bl' => $bl['bl'],
                    'error' => $e->getMessage(),
                    'data_keys' => array_keys($bl['data'] ?? [])
                ]);
            }
        }

        Log::info('KLine parse completed', $this->stats);
        return ['stats' => $this->stats];
    }

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

            if (Str::startsWith($type, 'BLNOREC')) {
                if ($currentBl) {
                    $records[] = ['bl' => $currentBl, 'data' => $currentData];
                    $currentData = [];
                }
                $currentBl = $content;
            }

            if ($currentBl) {
                $currentData[$type][] = $content;
            }
        }

        if ($currentBl) {
            $records[] = ['bl' => $currentBl, 'data' => $currentData];
        }

        Log::info('Grouped records', ['total_bills' => count($records)]);
        return $records;
    }

    protected function storeFromParsedData(array $record): void
    {
        $blNumber = $record['bl'];
        $data = $record['data'];

        Log::info("Processing B/L: {$blNumber}", [
            'data_keys' => array_keys($data)
        ]);

        // Extraer información de puertos del archivo KLine
        $portInfo = $this->extractPortInfo($data);
        $voyageInfo = $this->extractVoyageInfo($data);

        Log::info("Extracted info for B/L {$blNumber}", [
            'voyage_info' => $voyageInfo,
            'port_info' => $portInfo
        ]);

        // Buscar o crear puertos - CRÍTICO: deben existir para el Voyage
        $originPort = $this->findOrCreatePort($portInfo['origin'], 'Origen');
        $destinationPort = $this->findOrCreatePort($portInfo['destination'], 'Destino');

        if (!$originPort || !$destinationPort) {
            throw new \Exception("No se pudieron determinar los puertos válidos para B/L: {$blNumber}. Origen: {$portInfo['origin']}, Destino: {$portInfo['destination']}");
        }

        // Crear voyage con puertos válidos
        $voyage = $this->findOrCreateVoyage($voyageInfo, $originPort, $destinationPort);

        // Crear shipment
        $shipment = $this->createShipment($voyage, $blNumber);

        // Crear bill of lading
        $bill = $this->createBillOfLading($shipment, $blNumber, $data);

        // Procesar items de carga
        $this->processShipmentItems($bill, $data);

        Log::info("Successfully imported KLine B/L: {$blNumber}", [
            'voyage_id' => $voyage->id,
            'shipment_id' => $shipment->id,
            'bill_id' => $bill->id
        ]);
    }

    protected function extractPortInfo(array $data): array
    {
        $portInfo = [
            'origin' => null,
            'destination' => null,
            'loading_port' => null,
            'discharge_port' => null
        ];

        // Mapeo de tipos de registro KLine a información de puertos
        $portMappings = [
            // Registros de puertos de carga
            'LOADPORT' => 'origin',
            'PLDREC0' => 'origin',
            'POLREC0' => 'origin',
            'LOADING' => 'origin',
            
            // Registros de puertos de descarga  
            'DISCPORT' => 'destination',
            'PODREC0' => 'destination',
            'DISCREC0' => 'destination',
            'DISCHARGE' => 'destination',
            
            // Registros genéricos de puerto
            'PORTREC0' => 'generic',
            'PORTREC1' => 'generic',
        ];

        // Buscar información de puertos en los datos
        foreach ($portMappings as $recordType => $portType) {
            if (!empty($data[$recordType])) {
                foreach ($data[$recordType] as $line) {
                    $portCode = $this->extractPortCodeFromLine($line);
                    if ($portCode) {
                        if ($portType === 'generic') {
                            // Para registros genéricos, usar el primero como origen y segundo como destino
                            if (!$portInfo['origin']) {
                                $portInfo['origin'] = $portCode;
                            } elseif (!$portInfo['destination']) {
                                $portInfo['destination'] = $portCode;
                            }
                        } else {
                            $portInfo[$portType] = $portCode;
                        }
                        
                        Log::debug("Found port in {$recordType}: {$portCode} -> {$portType}");
                        break; // Tomar el primer puerto válido de este tipo
                    }
                }
            }
        }

        // Si no encontramos puertos específicos, buscar en cualquier registro que contenga códigos de puerto conocidos
        if (!$portInfo['origin'] || !$portInfo['destination']) {
            $this->searchPortsInAllRecords($data, $portInfo);
        }

        // Valores por defecto si no se encuentran puertos
        if (!$portInfo['origin']) {
            $portInfo['origin'] = 'UNKNOWN-ORIGIN';
            Log::warning('No origin port found, using default');
        }
        
        if (!$portInfo['destination']) {
            $portInfo['destination'] = 'UNKNOWN-DEST';
            Log::warning('No destination port found, using default');
        }

        return $portInfo;
    }

    protected function extractPortCodeFromLine(string $line): ?string
    {
        // Patrones comunes para extraer códigos de puerto
        $patterns = [
            '/^([A-Z]{2,6})\s/',           // Código al inicio: "ARBUE Buenos Aires"
            '/\s([A-Z]{2,6})\s/',          // Código en el medio
            '/^([A-Z]{2,6})$/',            // Solo código
            '/PORT\s*:?\s*([A-Z]{2,6})/',  // "PORT: ARBUE"
            '/([A-Z]{2}[A-Z0-9]{3,4})\s/', // Patrón específico de puertos
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, strtoupper($line), $matches)) {
                $code = $matches[1];
                
                // Validar que parece un código de puerto válido
                if (strlen($code) >= 3 && strlen($code) <= 6 && ctype_alnum($code)) {
                    return $code;
                }
            }
        }

        return null;
    }

    protected function searchPortsInAllRecords(array $data, array &$portInfo): void
    {
        // Códigos de puerto conocidos (puedes expandir esta lista)
        $knownPorts = [
            'ARBUE', 'ARROS', 'ARCAM', 'ARCON', 'ARSFE', 'ARFOR', 'ARBAR',
            'PYASU', 'PYCON', 'PYTVT', 'PYVIL', 'PYITA', 'PYOLD',
            'BRMNG', 'BRRIG', 'BRPOR', 'BRSDR', 'BRSSZ',
            'UYMON', 'UYMVD', 'UYNPA', 'UYCOL'
        ];

        foreach ($data as $recordType => $lines) {
            if (is_array($lines)) {
                foreach ($lines as $line) {
                    foreach ($knownPorts as $portCode) {
                        if (stripos($line, $portCode) !== false) {
                            if (!$portInfo['origin']) {
                                $portInfo['origin'] = $portCode;
                                Log::info("Found origin port {$portCode} in {$recordType}: {$line}");
                            } elseif (!$portInfo['destination'] && $portCode !== $portInfo['origin']) {
                                $portInfo['destination'] = $portCode;
                                Log::info("Found destination port {$portCode} in {$recordType}: {$line}");
                                return; // Tenemos ambos puertos
                            }
                        }
                    }
                }
            }
        }
    }

    protected function extractVoyageInfo(array $data): array
    {
        $voyageInfo = [
            'voyage_number' => null,
            'vessel_name' => null,
            'voyage_ref' => null
        ];

        // Buscar información de voyage en diferentes tipos de registro
        $voyageRecords = ['VOYGREC0', 'VESSELREC', 'VOYREC0', 'SHIPREC0', 'VSLREC0'];
        
        foreach ($voyageRecords as $recordType) {
            if (!empty($data[$recordType])) {
                foreach ($data[$recordType] as $line) {
                    // Intentar extraer información de voyage y vessel
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

        // Generar número de voyage
        if ($voyageInfo['voyage_ref']) {
            $voyageInfo['voyage_number'] = "KLINE-{$voyageInfo['voyage_ref']}-" . now()->format('Ymd');
        } else {
            $voyageInfo['voyage_number'] = 'KLINE-' . now()->format('Ymd-His');
        }

        return $voyageInfo;
    }

    protected function findOrCreatePort(?string $portCode, string $portType = 'Puerto'): ?Port
    {
        if (!$portCode) {
            return null;
        }

        // Buscar puerto existente por código
        $port = Port::where('code', $portCode)->first();
        
        if (!$port) {
            Log::info("Creating new port: {$portCode}");
            
            // Crear puerto con información básica
            $port = Port::create([
                'code' => $portCode,
                'name' => $this->getPortNameFromCode($portCode),
                'short_name' => $portCode,
                'country_id' => $this->getCountryIdFromPortCode($portCode),
                'port_type' => $this->guessPortType($portCode),
                'port_category' => 'commercial',
                'active' => true,
                'accepts_new_vessels' => true,
                'handles_containers' => true,
                'handles_general_cargo' => true,
                'created_by_user_id' => auth()->id(),
            ]);
        }

        return $port;
    }

    protected function getPortNameFromCode(string $code): string
    {
        // Mapeo de códigos conocidos a nombres
        $portNames = [
            'ARBUE' => 'Buenos Aires',
            'ARROS' => 'Rosario',
            'ARCAM' => 'Campana',
            'ARCON' => 'Concepción del Uruguay',
            'ARSFE' => 'Santa Fe',
            'PYASU' => 'Asunción',
            'PYCON' => 'Concepción',
            'PYTVT' => 'Terminal Villeta',
            'PYVIL' => 'Villeta',
            'UNKNOWN-ORIGIN' => 'Puerto de Origen Desconocido',
            'UNKNOWN-DEST' => 'Puerto de Destino Desconocido',
        ];

        return $portNames[$code] ?? "Puerto {$code}";
    }

    protected function getCountryIdFromPortCode(string $code): int
    {
        // Obtener ID del país basado en el prefijo del código
        $countryPrefix = substr($code, 0, 2);
        
        $countryMappings = [
            'AR' => 1, // Argentina (ajustar según tu base de datos)
            'PY' => 2, // Paraguay
            'BR' => 3, // Brasil
            'UY' => 4, // Uruguay
        ];

        return $countryMappings[$countryPrefix] ?? 1; // Default a Argentina
    }

    protected function guessPortType(string $code): string
    {
        // Los puertos fluviales argentinos y paraguayos son principalmente river
        $riverPrefixes = ['AR', 'PY'];
        $prefix = substr($code, 0, 2);
        
        return in_array($prefix, $riverPrefixes) ? 'river' : 'maritime';
    }

    protected function findOrCreateVoyage(array $voyageInfo, Port $originPort, Port $destinationPort): Voyage
    {
        // Buscar voyage existente por número
        $voyage = Voyage::where('voyage_number', $voyageInfo['voyage_number'])->first();
        
        if (!$voyage) {
            Log::info("Creating new voyage: {$voyageInfo['voyage_number']}");
            
            $voyage = Voyage::create([
                'voyage_number' => $voyageInfo['voyage_number'],
                'internal_reference' => $voyageInfo['voyage_ref'],
                'company_id' => 1, // Ajustar según tu lógica de empresa
                'origin_port_id' => $originPort->id,
                'destination_port_id' => $destinationPort->id,
                'origin_country_id' => $originPort->country_id,
                'destination_country_id' => $destinationPort->country_id,
                'voyage_type' => 'commercial',
                'cargo_type' => 'general',
                'status' => 'planning',
                'priority_level' => 'normal',
                'departure_date' => now()->addDays(1), // Fecha estimada
                'estimated_arrival_date' => now()->addDays(3),
                'is_convoy' => false,
                'vessel_count' => 1,
                'active' => true,
                'created_by_user_id' => auth()->id(),
                'created_date' => now(),
            ]);
        }

        return $voyage;
    }

    protected function createShipment(Voyage $voyage, string $blNumber): Shipment
    {
        $shipmentNumber = "SHIP-{$blNumber}-" . now()->format('His');
        
        return Shipment::create([
            'voyage_id' => $voyage->id,
            'shipment_number' => $shipmentNumber,
            'sequence_in_voyage' => 1,
            'vessel_role' => 'single',
            'cargo_capacity_tons' => 1000.00, // Valor por defecto
            'container_capacity' => 50,
            'status' => 'planning',
            'active' => true,
            'created_by_user_id' => auth()->id(),
            'created_date' => now(),
        ]);
    }

    protected function createBillOfLading(Shipment $shipment, string $blNumber, array $data): BillOfLading
    {
        return BillOfLading::create([
            'shipment_id' => $shipment->id,
            'bill_number' => $blNumber,
            'status' => 'draft',
            'bill_date' => now(),
            'loading_port_id' => $shipment->voyage->origin_port_id,
            'discharge_port_id' => $shipment->voyage->destination_port_id,
            'cargo_description' => $this->extractCargoDescription($data),
            'total_packages' => $this->extractTotalPackages($data),
            'gross_weight_kg' => $this->extractGrossWeight($data),
            'volume_m3' => $this->extractVolume($data),
            'freight_terms' => 'PREPAID',
            'bill_type' => 'original',
            'created_by_user_id' => auth()->id(),
        ]);
    }

    protected function processShipmentItems(BillOfLading $bill, array $data): void
    {
        $lineNumber = 1;
        
        // Procesar descripciones de mercancía
        if (!empty($data['DESCREC0'])) {
            foreach ($data['DESCREC0'] as $line) {
                ShipmentItem::create([
                    'shipment_id' => $bill->shipment_id,
                    'bill_of_lading_id' => $bill->id,
                    'line_number' => $lineNumber++,
                    'item_description' => $line,
                    'package_quantity' => $this->extractPackageQuantity($line),
                    'gross_weight_kg' => $this->extractWeight($line),
                    'volume_m3' => $this->extractVolume($line),
                    'currency_code' => 'USD',
                    'unit_of_measure' => 'PKG',
                    'status' => 'draft',
                    'created_by_user_id' => auth()->id(),
                    'created_date' => now(),
                ]);
            }
        }

        // Si no hay descripciones, crear un item genérico
        if (empty($data['DESCREC0'])) {
            ShipmentItem::create([
                'shipment_id' => $bill->shipment_id,
                'bill_of_lading_id' => $bill->id,
                'line_number' => 1,
                'item_description' => 'Mercadería General según B/L ' . $bill->bill_number,
                'package_quantity' => 1,
                'gross_weight_kg' => 0,
                'currency_code' => 'USD',
                'unit_of_measure' => 'PKG',
                'status' => 'draft',
                'created_by_user_id' => auth()->id(),
                'created_date' => now(),
            ]);
        }
    }

    // Métodos auxiliares para extraer información específica
    protected function extractCargoDescription(array $data): string
    {
        if (!empty($data['DESCREC0'])) {
            return implode(' | ', array_slice($data['DESCREC0'], 0, 3));
        }
        return 'Mercadería General';
    }

    protected function extractTotalPackages(array $data): int
    {
        // Buscar información de paquetes en registros de cantidad
        foreach (['QTYREC0', 'QNTREC0', 'PKGREC0'] as $key) {
            if (!empty($data[$key])) {
                foreach ($data[$key] as $line) {
                    if (preg_match('/(\d+)/', $line, $matches)) {
                        return (int) $matches[1];
                    }
                }
            }
        }
        return 1;
    }

    protected function extractGrossWeight(array $data): float
    {
        // Buscar peso en registros específicos
        foreach (['WGTREC0', 'GWTREC0', 'WEIGHTREC'] as $key) {
            if (!empty($data[$key])) {
                foreach ($data[$key] as $line) {
                    $weight = $this->extractWeight($line);
                    if ($weight > 0) {
                        return $weight;
                    }
                }
            }
        }
        return 0;
    }

    protected function extractPackageQuantity(string $line): int
    {
        // Buscar números que puedan representar cantidad de paquetes
        if (preg_match('/(\d+)\s*(PKG|PACKAGES|BULTOS|PCS)/i', $line, $matches)) {
            return (int) $matches[1];
        }
        
        // Si encuentra solo un número al inicio
        if (preg_match('/^(\d+)\s/', $line, $matches)) {
            return (int) $matches[1];
        }
        
        return 1;
    }

    protected function extractWeight(string $line): float
    {
        // Intentar extraer peso de la línea
        if (preg_match('/(\d+(?:\.\d+)?)\s*(KG|KGS|LB|LBS|TON|TONS)/i', $line, $matches)) {
            $weight = (float) $matches[1];
            $unit = strtoupper($matches[2]);
            
            // Convertir a kilogramos
            return match($unit) {
                'LB', 'LBS' => $weight * 0.453592,
                'TON', 'TONS' => $weight * 1000,
                default => $weight,
            };
        }
        
        return 0;
    }

    protected function extractVolume(string $line): float
    {
        // Intentar extraer volumen de la línea
        if (preg_match('/(\d+(?:\.\d+)?)\s*(CBM|M3|CFT|FT3)/i', $line, $matches)) {
            $volume = (float) $matches[1];
            $unit = strtoupper($matches[2]);
            
            // Convertir a metros cúbicos
            return match($unit) {
                'CFT', 'FT3' => $volume * 0.0283168,
                default => $volume,
            };
        }
        
        return 0;
    }
}