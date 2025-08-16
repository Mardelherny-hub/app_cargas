<?php

namespace App\Services\FileGeneration;

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
 * Servicio para generar archivos MANE para el sistema legacy Malvina de Aduana.
 * 
 * PROPÓSITO:
 * - Generar archivos de texto para sistema Malvina (legacy de Aduana Argentina)
 * - Usar campo IdMaria de la empresa en la primera línea
 * - Formato específico requerido por sistema legacy
 * - Futuro: se reemplazará por webservice MANE cuando esté disponible
 * 
 * CARACTERÍSTICAS:
 * - Solo para empresas con rol "Cargas"
 * - IdMaria obligatorio para generar archivos
 * - Formato de texto plano
 * - Estructurado para importación en Malvina
 * 
 * SOLICITADO POR: Roberto Benbassat (chat WhatsApp)
 * "idMaria va en la primer línea del archivo. Este archivo se envía al sistema Malvina"
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
     * Generar archivo MANE para un viaje específico
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
            'vessel', 
            'captain'
        ])->get();
        if ($shipments->isEmpty()) {
            throw new Exception('El viaje no tiene envíos (shipments) para exportar.');
        }

        // También cargar relaciones del voyage para evitar consultas adicionales
        $voyage->load(['originPort', 'destinationPort']);

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
     * Generar archivo MANE para múltiples viajes
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
     * Construir contenido del archivo para un viaje
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
            $lines[] = $this->buildShipmentLine($shipment);
            
            // Agregar conocimientos del shipment
            $billsOfLading = $shipment->billsOfLading;
            foreach ($billsOfLading as $bill) {
                $lines[] = $this->buildBillOfLadingLine($bill);
            }
        }
        
        // LÍNEA FINAL: Totales
        $lines[] = $this->buildTotalsLine($voyage, $shipments);
        
        return implode($this->config['line_ending'], $lines);
    }

    /**
     * Construir contenido consolidado para múltiples viajes
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
            $shipments = $voyage->shipments()->with([
                'billsOfLading.shipper', 
                'billsOfLading.consignee', 
                'billsOfLading.primaryPackagingType', 
                'vessel', 
                'captain'
            ])->get();
            
            // Cargar relaciones del voyage
            $voyage->load(['originPort', 'destinationPort']);
            
            $lines[] = $this->buildVoyageHeader($voyage);
            
            foreach ($shipments as $shipment) {
                $lines[] = $this->buildShipmentLine($shipment);
                
                $billsOfLading = $shipment->billsOfLading;
                foreach ($billsOfLading as $bill) {
                    $lines[] = $this->buildBillOfLadingLine($bill);
                }
            }
        }
        
        // Totales consolidados
        $lines[] = $this->buildConsolidatedTotalsLine($voyages);
        
        return implode($this->config['line_ending'], $lines);
    }

    /**
     * Construir línea de encabezado del viaje
     */
    private function buildVoyageHeader(Voyage $voyage): string
    {
        return implode($this->config['field_separator'], [
            'VOYAGE',
            $voyage->voyage_number,
            $voyage->originPort?->code ?? '',
            $voyage->destinationPort?->code ?? '',
            $voyage->departure_date?->format($this->config['date_format']) ?? '',
            $voyage->actual_arrival_date?->format($this->config['date_format']) ?? '',
            $voyage->status
        ]);
    }

    /**
     * Construir línea de shipment
     */
    private function buildShipmentLine(Shipment $shipment): string
    {
        return implode($this->config['field_separator'], [
            'SHIPMENT',
            $shipment->shipment_number,
            $shipment->vessel?->name ?? '',
            $shipment->vessel?->imo_number ?? '',
            $shipment->captain?->full_name ?? '',
            $shipment->cargo_weight_loaded ?? '0',
            $shipment->containers_loaded ?? '0'
        ]);
    }

    /**
     * Construir línea de conocimiento de embarque
     */
    private function buildBillOfLadingLine(BillOfLading $bill): string
    {
        return implode($this->config['field_separator'], [
            'BILL',
            $bill->bill_number,
            $bill->shipper?->legal_name ?? '',
            $bill->consignee?->legal_name ?? '',
            $bill->cargo_description ?? '',
            $bill->gross_weight_kg ?? '0',
            $bill->total_packages ?? '0',
            $bill->primaryPackagingType?->name ?? ''
        ]);
    }

    /**
     * Construir línea de totales para un viaje
     */
    private function buildTotalsLine(Voyage $voyage, Collection $shipments): string
    {
        $totalWeight = $shipments->sum('cargo_weight_loaded');
        $totalContainers = $shipments->sum('containers_loaded');
        $totalBills = $shipments->sum(fn($shipment) => $shipment->billsOfLading->count());
        
        return implode($this->config['field_separator'], [
            'TOTALS',
            $voyage->voyage_number,
            $shipments->count(),
            $totalBills,
            $totalWeight,
            $totalContainers
        ]);
    }

    /**
     * Construir header consolidado
     */
    private function buildConsolidatedHeader(Collection $voyages): string
    {
        return implode($this->config['field_separator'], [
            'CONSOLIDATED',
            $voyages->count(),
            Carbon::now()->format($this->config['date_format']),
            Carbon::now()->format($this->config['time_format']),
            $this->company->legal_name
        ]);
    }

    /**
     * Construir totales consolidados
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
     * Generar nombre de archivo para un viaje
     */
    private function generateFilename(Voyage $voyage): string
    {
        $timestamp = Carbon::now()->format('Ymd_His');
        $voyageNumber = preg_replace('/[^A-Za-z0-9_-]/', '_', $voyage->voyage_number);
        
        return "MANE_{$this->company->id_maria}_{$voyageNumber}_{$timestamp}.txt";
    }

    /**
     * Generar nombre de archivo consolidado
     */
    private function generateConsolidatedFilename(Collection $voyages): string
    {
        $timestamp = Carbon::now()->format('Ymd_His');
        $voyageCount = $voyages->count();
        
        return "MANE_{$this->company->id_maria}_CONSOLIDATED_{$voyageCount}voyages_{$timestamp}.txt";
    }

    /**
     * Validar que la empresa puede usar este servicio
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

    /**
     * Obtener información del servicio
     */
    public function getServiceInfo(): array
    {
        return [
            'service_name' => 'MANE File Generator',
            'company' => $this->company->legal_name,
            'id_maria' => $this->company->id_maria,
            'config' => $this->config,
            'supported_operations' => [
                'single_voyage_export',
                'multiple_voyages_export',
                'custom_date_range_export'
            ],
            'file_format' => 'Plain text with pipe delimiters',
            'target_system' => 'Malvina (Aduana Legacy System)',
            'future_replacement' => 'MANE Webservice (when available)'
        ];
    }

    /**
     * Validar si un viaje puede ser exportado
     */
    public function canExportVoyage(Voyage $voyage): bool
    {
        return $voyage->company_id === $this->company->id 
            && $voyage->shipments()->exists()
            && !empty($this->company->id_maria);
    }
}