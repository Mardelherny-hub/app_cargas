<?php

namespace App\Services\Webservice;

use App\Models\Company;
use App\Models\Voyage;
use App\Models\Shipment;
use App\Models\BillOfLading;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Collection;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Log;

/**
 * MÓDULO 5: ENVÍOS ADUANEROS ADICIONALES - MANE/Malvina File Generator
 * 
 * VERSIÓN CORREGIDA: Todos los campos verificados contra modelos reales
 * 
 * Servicio para generar archivos MANE y preparar datos XML para webservice MANE.
 * ACTUALIZADO: Agregado soporte para webservice directo además de archivos
 * 
 * PROPÓSITO:
 * - Generar archivos de texto para sistema Malvina (legacy de Aduana Argentina)
 * - NUEVO: Preparar datos XML para envío directo vía webservice MANE
 * - Usar campo IdMaria de la empresa en la primera línea/header
 * - Formato específico requerido por sistema legacy y webservice
 * 
 * CARACTERÍSTICAS:
 * - Solo para empresas con rol "Cargas"
 * - IdMaria obligatorio para generar archivos
 * - Formato de texto plano para archivo
 * - NUEVO: Formato estructurado para XML webservice
 * - Estructurado para importación en Malvina y envío SOAP
 */
class ManeFileGeneratorService
{
    private Company $company;
    private array $config;

    /**
     * Configuración por defecto para archivos MANE
     */
    private const DEFAULT_CONFIG = [
        'charset' => 'UTF-8',
        'line_ending' => "\r\n",
        'field_separator' => '|',
        'date_format' => 'Y-m-d',
        'time_format' => 'H:i:s',
        'include_headers' => false,
        'max_file_size' => 5242880, // 5MB
        // NUEVO: Configuración para webservice
        'webservice_enabled' => false,
        'xml_version' => '1.0',
        'xml_encoding' => 'UTF-8',
    ];

    /**
     * Constructor del servicio
     */
    public function __construct(Company $company, array $config = [])
    {
        $this->validateCompany($company);
        $this->company = $company;
        $this->config = array_merge(self::DEFAULT_CONFIG, $config);
    }

    /**
     * NUEVO: Preparar datos para XML del webservice MANE
     * VERSIÓN CORREGIDA con campos reales verificados
     * 
     * Este método prepara los datos en una estructura que puede ser
     * consumida por XmlSerializerService para generar el XML SOAP
     * 
     * @param Voyage $voyage El viaje a procesar
     * @param string $transactionId ID de la transacción del webservice
     * @return array Datos estructurados para XML
     */
    public function prepareXmlData(Voyage $voyage, string $transactionId): array
    {
        // Validar que el viaje pertenece a la empresa
        if ($voyage->company_id !== $this->company->id) {
            throw new Exception('El viaje no pertenece a la empresa configurada.');
        }

        // Cargar todas las relaciones necesarias
        $voyage->load([
            'originPort.country',
            'destinationPort.country',
            'leadVessel.vesselType',      // CORREGIDO: leadVessel en lugar de vessel
            'captain',
            'shipments.billsOfLading.shipper',
            'shipments.billsOfLading.consignee',
            'shipments.billsOfLading.primaryPackagingType',
            'shipments.vessel',            // CORREGIDO: vessel de cada shipment
            'shipments.captain'            // Captain de cada shipment
        ]);

        // Validar que hay datos para enviar
        if ($voyage->shipments->isEmpty()) {
            throw new Exception('El viaje no tiene envíos (shipments) para procesar.');
        }

        // Construir estructura de datos para XML con campos REALES
        $xmlData = [
            'transaction_id' => $transactionId,
            'timestamp' => Carbon::now()->toIso8601String(),
            'company' => [
                'id_maria' => $this->company->id_maria,
                'cuit' => $this->company->tax_id,
                'legal_name' => $this->company->legal_name,
                'role' => 'TRANSPORTISTA',
            ],
            'voyage' => [
                'voyage_number' => $voyage->voyage_number,
                // CORREGIDO: Usar campos del vessel principal
                'imo_number' => $voyage->leadVessel->imo_number ?? null,
                'call_sign' => $voyage->leadVessel->call_sign ?? null,
                'flag' => $voyage->leadVessel->flag_country_id ?? null,
                'origin' => [
                    'port_code' => $voyage->originPort->code ?? '',
                    'port_name' => $voyage->originPort->name ?? '',
                    'country_code' => $voyage->originPort->country->alpha2_code ?? 'AR',
                ],
                'destination' => [
                    'port_code' => $voyage->destinationPort->code ?? '',
                    'port_name' => $voyage->destinationPort->name ?? '',
                    'country_code' => $voyage->destinationPort->country->alpha2_code ?? 'PY',
                ],
                'departure_date' => $voyage->departure_date ? Carbon::parse($voyage->departure_date)->format('Y-m-d') : null,
                'arrival_date' => $voyage->arrival_date ? Carbon::parse($voyage->arrival_date)->format('Y-m-d') : null,
            ],
            'vessel' => $this->prepareVesselData($voyage),
            'captain' => $this->prepareCaptainData($voyage),
            'shipments' => $this->prepareShipmentsData($voyage->shipments),
            'summary' => $this->prepareSummaryData($voyage),
        ];

        Log::info('Datos XML MANE preparados', [
            'company_id' => $this->company->id,
            'voyage_id' => $voyage->id,
            'transaction_id' => $transactionId,
            'shipments_count' => count($xmlData['shipments']),
        ]);

        return $xmlData;
    }

    /**
     * NUEVO: Preparar datos del buque para XML
     * VERSIÓN CORREGIDA con campos reales
     */
    private function prepareVesselData(Voyage $voyage): array
    {
        $vessel = $voyage->leadVessel;
        
        if (!$vessel) {
            return [
                'name' => 'NO ESPECIFICADO',
                'type' => 'BAR', // Barcaza por defecto
                'registration_number' => '',
            ];
        }

        return [
            'name' => $vessel->name,
            'type' => $this->mapVesselType($vessel->vesselType),
            'registration_number' => $vessel->registration_number ?? '',  // CORREGIDO
            'year_built' => $vessel->built_date ? Carbon::parse($vessel->built_date)->year : null, // CORREGIDO
            'length_meters' => $vessel->length_meters ?? null,            // CORREGIDO
            'beam_meters' => $vessel->beam_meters ?? null,                // CORREGIDO
            'draft_meters' => $vessel->draft_meters ?? null,              // CORREGIDO
            'gross_tonnage' => $vessel->gross_tonnage ?? null,
            'net_tonnage' => $vessel->net_tonnage ?? null,
            'cargo_capacity' => $vessel->cargo_capacity_tons ?? null,     // CORREGIDO
        ];
    }

    /**
     * NUEVO: Preparar datos del capitán para XML
     * Campos verificados contra modelo Captain
     */
    private function prepareCaptainData(Voyage $voyage): array
    {
        $captain = $voyage->captain;
        
        if (!$captain) {
            return [
                'name' => 'NO ESPECIFICADO',
                'document_type' => 'DNI',
                'document_number' => '',
            ];
        }

        return [
            'name' => $captain->full_name ?? trim($captain->first_name . ' ' . $captain->last_name),
            'document_type' => $captain->document_type ?? 'DNI',
            'document_number' => $captain->document_number ?? '',
            'nationality' => $captain->nationality ?? 'AR',
            'license_number' => $captain->license_number ?? '',
            'license_country' => $captain->license_country_id ?? null,
        ];
    }

    /**
     * NUEVO: Preparar datos de shipments para XML
     * VERSIÓN CORREGIDA con campos reales
     */
    private function prepareShipmentsData(Collection $shipments): array
    {
        $shipmentsData = [];

        foreach ($shipments as $shipment) {
            $shipmentData = [
                'shipment_number' => $shipment->shipment_number,
                'booking_number' => $shipment->booking_number,
                'type' => $shipment->type ?? 'FCL',
                'cargo' => [
                    'weight_loaded' => $shipment->cargo_weight_loaded ?? 0,
                    'weight_unloaded' => 0,  // CORREGIDO: campo no existe, usar 0
                    'containers_loaded' => $shipment->containers_loaded ?? 0,
                    'containers_unloaded' => 0,  // CORREGIDO: campo no existe, usar 0
                    'capacity_tons' => $shipment->cargo_capacity_tons ?? 0,
                ],
                'vessel' => [
                    'id' => $shipment->vessel_id,
                    'name' => $shipment->vessel->name ?? 'NO ESPECIFICADO',
                ],
                'captain' => [
                    'id' => $shipment->captain_id,
                    'name' => $shipment->captain ? $shipment->captain->full_name : 'NO ESPECIFICADO',
                ],
                'bills_of_lading' => [],
                'containers' => [],
            ];

            // Agregar Bills of Lading con campos REALES
            foreach ($shipment->billsOfLading as $bill) {
                $shipmentData['bills_of_lading'][] = [
                    'number' => $bill->number,
                    'type' => $bill->type ?? 'house',
                    'issue_date' => $bill->issue_date ? Carbon::parse($bill->issue_date)->format('Y-m-d') : null,
                    'shipper' => [
                        'name' => $bill->shipper->legal_name ?? $bill->shipper->name ?? 'NO ESPECIFICADO',
                        'tax_id' => $bill->shipper->tax_id ?? '',
                        'address' => $bill->shipper->address ?? '',
                        'country' => $bill->shipper->country ?? 'AR',
                    ],
                    'consignee' => [
                        'name' => $bill->consignee->legal_name ?? $bill->consignee->name ?? 'NO ESPECIFICADO',
                        'tax_id' => $bill->consignee->tax_id ?? '',
                        'address' => $bill->consignee->address ?? '',
                        'country' => $bill->consignee->country ?? 'PY',
                    ],
                    'description' => $bill->cargo_description ?? '',
                    'weight' => $bill->gross_weight ?? 0,
                    'volume' => $bill->volume ?? 0,
                    'packages' => $bill->number_of_packages ?? 0,
                    'package_type' => $bill->primaryPackagingType->code ?? 'PK',
                ];
            }

            // NOTA: Los contenedores están relacionados a través de ShipmentItems
            // Por ahora agregamos un array vacío hasta verificar la relación correcta
            // shipments -> billsOfLading -> shipmentItems -> containers
            $shipmentData['containers'] = $this->prepareContainersData($shipment);

            $shipmentsData[] = $shipmentData;
        }

        return $shipmentsData;
    }

    /**
     * NUEVO: Preparar datos de contenedores
     * NOTA: La relación es compleja: Shipment -> BillOfLading -> ShipmentItem -> Container
     */
    private function prepareContainersData(Shipment $shipment): array
    {
        $containersData = [];
        
        // Obtener contenedores a través de la relación correcta
        // shipmentItems es un hasManyThrough definido en el modelo Shipment
        $shipmentItems = $shipment->shipmentItems()->with('containers.containerType')->get();
        
        foreach ($shipmentItems as $item) {
            foreach ($item->containers as $container) {  // ✅ CORRECTO - 'containers' plural
                $containersData[] = [
                    'number' => $container->container_number,
                    'type_code' => $container->containerType->code ?? '40HC',
                    'type_name' => $container->containerType->name ?? 'Container',
                    'size_feet' => $container->containerType->length_feet ?? 40,
                    'teu' => $this->calculateTEU($container->containerType),
                    'seal_number' => $container->seal_number ?? '',
                    'tare_weight' => $container->tare_weight_kg ?? 0,
                    'max_payload' => $container->max_payload_kg ?? 0,
                    'condition' => $container->condition ?? 'FCL',
                ];
            }
        }
        
        return $containersData;
    }

    /**
     * Calcular TEU basado en el tipo de contenedor
     */
    private function calculateTEU($containerType): int
    {
        if (!$containerType) return 2;
        
        $lengthFeet = $containerType->length_feet ?? 40;
        
        if ($lengthFeet <= 20) return 1;
        if ($lengthFeet <= 40) return 2;
        return 3; // Para contenedores más grandes
    }

    /**
     * NUEVO: Preparar datos de resumen para XML
     * VERSIÓN CORREGIDA con campos reales
     */
    private function prepareSummaryData(Voyage $voyage): array
    {
        $totalWeight = 0;
        $totalContainers = 0;
        $totalBills = 0;
        $totalPackages = 0;

        foreach ($voyage->shipments as $shipment) {
            $totalWeight += $shipment->cargo_weight_loaded ?? 0;
            $totalContainers += $shipment->containers_loaded ?? 0;
            $totalBills += $shipment->billsOfLading->count();
            $totalPackages += $shipment->billsOfLading->sum('number_of_packages');
        }

        return [
            'total_shipments' => $voyage->shipments->count(),
            'total_bills_of_lading' => $totalBills,
            'total_weight_kg' => $totalWeight,
            'total_containers' => $totalContainers,
            'total_packages' => $totalPackages,
            'generated_at' => Carbon::now()->toIso8601String(),
            'generated_by' => auth()->user()->name ?? 'Sistema',
        ];
    }

    /**
     * NUEVO: Mapear tipo de embarcación a código MANE
     * Ahora recibe el objeto VesselType
     */
    private function mapVesselType($vesselType): string
    {
        if (!$vesselType) return 'BAR';
        
        // Usar el código del webservice si existe
        if ($vesselType->argentina_ws_code) {
            return $vesselType->argentina_ws_code;
        }
        
        // Mapeo basado en el código del tipo
        $mapping = [
            'BARGE' => 'BAR',
            'TUGBOAT' => 'EMP',
            'SELF_PROPELLED' => 'BUM',
            'CONTAINER' => 'PCC',
            'BULK' => 'GRA',
        ];

        $code = strtoupper($vesselType->code ?? '');
        return $mapping[$code] ?? 'BAR';
    }

    /**
     * Generar archivo MANE para un viaje específico (método original mantenido)
     */
    public function generateForVoyage(Voyage $voyage): string
    {
        // Validar que el viaje pertenece a la empresa
        if ($voyage->company_id !== $this->company->id) {
            throw new Exception('El viaje no pertenece a la empresa configurada.');
        }

        // Validar que hay datos para exportar
        $shipments = $voyage->shipments()->with([
            'billsOfLading.shipper', 
            'billsOfLading.consignee', 
            'billsOfLading.primaryPackagingType',
            'vessel',     // CORREGIDO: vessel en lugar de barco
            'captain'
        ])->get();
        
        if ($shipments->isEmpty()) {
            throw new Exception('El viaje no tiene envíos (shipments) para exportar.');
        }

        // También cargar relaciones del voyage para evitar consultas adicionales
        $voyage->load(['originPort', 'destinationPort', 'leadVessel']);

        // Generar contenido del archivo
        $content = $this->buildFileContent($voyage, $shipments);
        
        // Crear archivo en storage
        $filename = $this->generateFilename($voyage);
        $filepath = "mane_exports/{$filename}";
        
        Storage::put($filepath, $content);
        
        Log::info("Archivo MANE generado", [
            'company_id' => $this->company->id,
            'voyage_id' => $voyage->id,
            'filename' => $filename,
            'size' => strlen($content)
        ]);

        return $filepath;
    }

    /**
     * Generar archivo MANE para múltiples viajes (método original mantenido)
     */
    public function generateForMultipleVoyages(Collection $voyages): string
    {
        if ($voyages->isEmpty()) {
            throw new Exception('No hay viajes para exportar.');
        }

        // Validar que todos los viajes pertenecen a la empresa
        $invalidVoyages = $voyages->filter(fn($voyage) => $voyage->company_id !== $this->company->id);
        if ($invalidVoyages->isNotEmpty()) {
            throw new Exception('Algunos viajes no pertenecen a la empresa configurada.');
        }

        // Generar contenido consolidado
        $content = $this->buildConsolidatedContent($voyages);
        
        // Crear archivo en storage
        $filename = $this->generateConsolidatedFilename($voyages);
        $filepath = "mane_exports/{$filename}";
        
        Storage::put($filepath, $content);
        
        Log::info("Archivo MANE consolidado generado", [
            'company_id' => $this->company->id,
            'voyages_count' => $voyages->count(),
            'filename' => $filename,
            'size' => strlen($content)
        ]);

        return $filepath;
    }

    /**
     * Construir contenido del archivo para un viaje (método original mantenido)
     * VERSIÓN CORREGIDA con campos reales
     */
    private function buildFileContent(Voyage $voyage, Collection $shipments): string
    {
        $lines = [];
        
        // LÍNEA 1: ID MARÍA (OBLIGATORIO)
        $lines[] = $this->company->id_maria;
        
        // LÍNEA 2: Información del viaje
        $lines[] = $this->buildVoyageHeader($voyage);
        
        // LÍNEAS 3+: Información de shipments y conocimientos
        foreach ($shipments as $shipment) {
            // Línea de shipment
            $lines[] = $this->buildShipmentLine($shipment);
            
            // Líneas de conocimientos de embarque
            foreach ($shipment->billsOfLading as $bill) {
                $lines[] = $this->buildBillOfLadingLine($bill, $shipment);
            }
        }
        
        // LÍNEA FINAL: Totales
        $lines[] = $this->buildTotalsLine($voyage, $shipments);
        
        return implode($this->config['line_ending'], $lines);
    }

    /**
     * Construir contenido consolidado para múltiples viajes (método original mantenido)
     */
    private function buildConsolidatedContent(Collection $voyages): string
    {
        $lines = [];
        
        // LÍNEA 1: ID MARÍA (OBLIGATORIO)
        $lines[] = $this->company->id_maria;
        
        // LÍNEA 2: Header consolidado
        $lines[] = $this->buildConsolidatedHeader($voyages);
        
        // Procesar cada viaje
        foreach ($voyages as $voyage) {
            $voyage->load(['originPort', 'destinationPort', 'leadVessel', 'shipments.billsOfLading']);
            
            // Línea separadora de viaje
            $lines[] = $this->buildVoyageSeparator($voyage);
            
            // Información del viaje
            $lines[] = $this->buildVoyageHeader($voyage);
            
            // Shipments del viaje
            foreach ($voyage->shipments as $shipment) {
                $lines[] = $this->buildShipmentLine($shipment);
                
                foreach ($shipment->billsOfLading as $bill) {
                    $lines[] = $this->buildBillOfLadingLine($bill, $shipment);
                }
            }
        }
        
        // LÍNEA FINAL: Totales consolidados
        $lines[] = $this->buildConsolidatedTotalsLine($voyages);
        
        return implode($this->config['line_ending'], $lines);
    }

    /**
     * Construir línea de encabezado del viaje (método original mantenido)
     * VERSIÓN CORREGIDA con campos reales
     */
    private function buildVoyageHeader(Voyage $voyage): string
    {
        $vessel = $voyage->leadVessel;
        
        return implode($this->config['field_separator'], [
            'VOYAGE',
            $voyage->voyage_number,
            $vessel->imo_number ?? '',
            $vessel->call_sign ?? '',
            $vessel->flag_country_id ?? 'AR',
            $voyage->originPort->code ?? '',
            $voyage->destinationPort->code ?? '',
            $voyage->departure_date ? Carbon::parse($voyage->departure_date)->format($this->config['date_format']) : '',
            $voyage->arrival_date ? Carbon::parse($voyage->arrival_date)->format($this->config['date_format']) : ''
        ]);
    }

    /**
     * Construir línea de shipment (método original mantenido)
     * VERSIÓN CORREGIDA con campos reales
     */
    private function buildShipmentLine(Shipment $shipment): string
    {
        return implode($this->config['field_separator'], [
            'SHIPMENT',
            $shipment->shipment_number,
            $shipment->booking_number ?? '',
            $shipment->type ?? 'FCL',
            $shipment->cargo_weight_loaded ?? 0,
            0,  // cargo_weight_unloaded no existe
            $shipment->containers_loaded ?? 0,
            0   // containers_unloaded no existe
        ]);
    }

    /**
     * Construir línea de conocimiento de embarque (método original mantenido)
     */
    private function buildBillOfLadingLine(BillOfLading $bill, Shipment $shipment): string
    {
        return implode($this->config['field_separator'], [
            'BL',
            $bill->number,
            $bill->type ?? 'house',
            $bill->issue_date ? Carbon::parse($bill->issue_date)->format($this->config['date_format']) : '',
            $bill->shipper->legal_name ?? $bill->shipper->name ?? 'NO ESPECIFICADO',
            $bill->consignee->legal_name ?? $bill->consignee->name ?? 'NO ESPECIFICADO',
            $bill->cargo_description ?? '',
            $bill->gross_weight ?? 0,
            $bill->volume ?? 0,
            $bill->number_of_packages ?? 0,
            $bill->primaryPackagingType->code ?? 'PK'
        ]);
    }

    /**
     * Construir línea de totales (método original mantenido)
     */
    private function buildTotalsLine(Voyage $voyage, Collection $shipments): string
    {
        $totalWeight = $shipments->sum('cargo_weight_loaded');
        $totalContainers = $shipments->sum('containers_loaded');
        $totalBills = $shipments->sum(fn($shipment) => $shipment->billsOfLading->count());
        
        return implode($this->config['field_separator'], [
            'TOTALS',
            $shipments->count(),
            $totalBills,
            $totalWeight,
            $totalContainers
        ]);
    }

    /**
     * Construir header consolidado (método original mantenido)
     */
    private function buildConsolidatedHeader(Collection $voyages): string
    {
        return implode($this->config['field_separator'], [
            'CONSOLIDATED',
            Carbon::now()->format($this->config['date_format'] . ' ' . $this->config['time_format']),
            $voyages->count(),
            $this->company->legal_name
        ]);
    }

    /**
     * Construir separador de viaje (método original mantenido)
     */
    private function buildVoyageSeparator(Voyage $voyage): string
    {
        return implode($this->config['field_separator'], [
            '--- VOYAGE START ---',
            $voyage->voyage_number
        ]);
    }

    /**
     * Construir totales consolidados (método original mantenido)
     */
    private function buildConsolidatedTotalsLine(Collection $voyages): string
    {
        $totalShipments = 0;
        $totalBills = 0;
        $totalWeight = 0;
        $totalContainers = 0;
        
        foreach ($voyages as $voyage) {
            $shipments = $voyage->shipments;
            $totalShipments += $shipments->count();
            $totalWeight += $shipments->sum('cargo_weight_loaded');
            $totalContainers += $shipments->sum('containers_loaded');
            $totalBills += $shipments->sum(fn($shipment) => $shipment->billsOfLading->count());
        }
        
        return implode($this->config['field_separator'], [
            'CONSOLIDATED_TOTALS',
            $voyages->count(),
            $totalShipments,
            $totalBills,
            $totalWeight,
            $totalContainers
        ]);
    }

    /**
     * Generar nombre de archivo para un viaje (método original mantenido)
     */
    private function generateFilename(Voyage $voyage): string
    {
        $timestamp = Carbon::now()->format('Ymd_His');
        $voyageNumber = preg_replace('/[^A-Za-z0-9_-]/', '_', $voyage->voyage_number);
        
        return "MANE_{$this->company->id_maria}_{$voyageNumber}_{$timestamp}.txt";
    }

    /**
     * Generar nombre de archivo consolidado (método original mantenido)
     */
    private function generateConsolidatedFilename(Collection $voyages): string
    {
        $timestamp = Carbon::now()->format('Ymd_His');
        $voyageCount = $voyages->count();
        
        return "MANE_{$this->company->id_maria}_CONSOLIDATED_{$voyageCount}voyages_{$timestamp}.txt";
    }

    /**
     * Validar que la empresa puede usar este servicio (método original mantenido)
     */
    private function validateCompany(Company $company): void
    {
        // Validar que la empresa tiene el rol "Cargas"
        if (!$company->hasRole('Cargas')) {
            throw new Exception('La empresa debe tener el rol "Cargas" para generar archivos MANE.');
        }

        // Validar que la empresa tiene IdMaria configurado
        if (empty($company->id_maria)) {
            throw new Exception('La empresa debe tener un ID María configurado para generar archivos MANE.');
        }

        // Validar que la empresa está activa
        if (!$company->active) {
            throw new Exception('La empresa debe estar activa para generar archivos MANE.');
        }
    }
}