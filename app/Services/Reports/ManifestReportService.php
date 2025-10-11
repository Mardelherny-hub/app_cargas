<?php

namespace App\Services\Reports;

use App\Models\Voyage;
use Illuminate\Support\Collection;
use Carbon\Carbon;

/**
 * Servicio para generar Manifiestos de Carga
 * 
 * Genera reportes PDF y Excel con el listado completo de conocimientos
 * de embarque de un viaje específico.
 * 
 * BASADO EN CAMPOS REALES VERIFICADOS EN PROJECT KNOWLEDGE
 */
class ManifestReportService
{
    private Voyage $voyage;
    private array $filters;
    private ?string $generatedBy;

    /**
     * Constructor
     * 
     * @param Voyage $voyage Viaje para el cual generar el manifiesto
     * @param array $filters Filtros opcionales
     * @param string|null $generatedBy Nombre del usuario que genera el reporte
     */
    public function __construct(Voyage $voyage, array $filters = [], ?string $generatedBy = null)
    {
        $this->voyage = $voyage;
        $this->filters = $filters;
        $this->generatedBy = $generatedBy ?? auth()->user()->name ?? 'Sistema';
    }

    /**
     * Preparar todos los datos necesarios para el reporte
     * 
     * @return array Datos estructurados listos para PDF/Excel
     */
    public function prepareData(): array
    {
        // Cargar todas las relaciones necesarias de una vez (eager loading)
        $this->voyage->load([
            'company',
            'leadVessel',
            'captain',
            'originPort.country',
            'destinationPort.country',
            'transshipmentPort.country',
            'billsOfLading.shipper',
            'billsOfLading.consignee',
            'billsOfLading.loadingPort',
            'billsOfLading.dischargePort',
            'billsOfLading.shipmentItems'
        ]);

        return [
            'voyage' => $this->formatVoyageData(),
            'bills_of_lading' => $this->formatBillsOfLading(),
            'totals' => $this->calculateTotals(),
            'metadata' => $this->generateMetadata(),
        ];
    }

    /**
     * Formatear datos del viaje para el encabezado
     * 
     * @return array Datos del viaje formateados
     */
    private function formatVoyageData(): array
    {
        $voyage = $this->voyage;

        return [
            // Identificación
            'voyage_number' => $voyage->voyage_number ?? 'S/N',
            'internal_reference' => $voyage->internal_reference ?? null,
            
            // Embarcación y tripulación
            'vessel_name' => $voyage->leadVessel->name ?? 'No especificado',
            'vessel_registration' => $voyage->leadVessel->registration_number ?? null,
            'vessel_imo' => $voyage->leadVessel->imo_number ?? null,
            'captain_name' => $voyage->captain ? $voyage->captain->full_name : 'No asignado',
            'captain_license' => $voyage->captain->license_number ?? null,
            
            // Puertos y ruta
            'origin_port' => $voyage->originPort->name ?? 'No especificado',
            'origin_port_code' => $voyage->originPort->code ?? null,
            'origin_country' => $voyage->originPort->country->name ?? null,
            'destination_port' => $voyage->destinationPort->name ?? 'No especificado',
            'destination_port_code' => $voyage->destinationPort->code ?? null,
            'destination_country' => $voyage->destinationPort->country->name ?? null,
            'transshipment_port' => $voyage->transshipmentPort ? $voyage->transshipmentPort->name : null,
            
            // Fechas
            'departure_date' => $voyage->departure_date ? 
                Carbon::parse($voyage->departure_date)->format('d/m/Y') : 'No especificado',
            'estimated_arrival_date' => $voyage->estimated_arrival_date ? 
                Carbon::parse($voyage->estimated_arrival_date)->format('d/m/Y') : 'No especificado',
            'actual_arrival_date' => $voyage->actual_arrival_date ? 
                Carbon::parse($voyage->actual_arrival_date)->format('d/m/Y') : null,
            
            // Tipo y estado
            'voyage_type' => $voyage->voyage_type ?? 'Standard',
            'cargo_type' => $voyage->cargo_type ?? 'General',
            'status' => $voyage->status ?? 'active',
            'is_convoy' => $voyage->is_convoy ?? false,
            'vessel_count' => $voyage->vessel_count ?? 1,
            
            // Datos de la empresa
            'company_name' => $voyage->company->legal_name ?? 'No especificado',
            'company_commercial_name' => $voyage->company->commercial_name ?? null,
            'company_tax_id' => $voyage->company->tax_id ?? null,
        ];
    }

    /**
     * Formatear lista de conocimientos de embarque
     * 
     * @return Collection Colección de BLs formateados
     */
    private function formatBillsOfLading(): Collection
    {
        $bills = $this->voyage->billsOfLading;

        // Aplicar filtros si existen
        if (!empty($this->filters['status'])) {
            $bills = $bills->where('status', $this->filters['status']);
        }

        if (!empty($this->filters['shipper_id'])) {
            $bills = $bills->where('shipper_id', $this->filters['shipper_id']);
        }

        if (!empty($this->filters['consignee_id'])) {
            $bills = $bills->where('consignee_id', $this->filters['consignee_id']);
        }

        return $bills->map(function ($bill, $index) {
            return [
                // Identificación
                'line_number' => $index + 1,
                'bill_number' => $bill->bill_number ?? 'S/N',
                'manifest_line_number' => $bill->manifest_line_number ?? null,
                
                // Partes involucradas
                'shipper_name' => $bill->shipper ? 
                    ($bill->shipper->commercial_name ?: $bill->shipper->legal_name) : 
                    'No especificado',
                'shipper_tax_id' => $bill->shipper->tax_id ?? null,
                'consignee_name' => $bill->consignee ? 
                    ($bill->consignee->commercial_name ?: $bill->consignee->legal_name) : 
                    'No especificado',
                'consignee_tax_id' => $bill->consignee->tax_id ?? null,
                
                // Puertos
                'loading_port' => $bill->loadingPort->name ?? 'N/A',
                'discharge_port' => $bill->dischargePort->name ?? 'N/A',
                
                // Cantidades y medidas
                'total_packages' => $bill->total_packages ?? 0,
                'gross_weight_kg' => $bill->gross_weight_kg ?? 0,
                'net_weight_kg' => $bill->net_weight_kg ?? 0,
                'volume_m3' => $bill->volume_m3 ?? 0,
                
                // Descripción de carga
                'cargo_description' => $bill->cargo_description ?? 'No especificado',
                'cargo_marks' => $bill->cargo_marks ?? null,
                'commodity_code' => $bill->commodity_code ?? null,
                
                // Características especiales
                'contains_dangerous_goods' => $bill->contains_dangerous_goods ?? false,
                'requires_refrigeration' => $bill->requires_refrigeration ?? false,
                'un_number' => $bill->un_number ?? null,
                
                // Estado y fechas
                'status' => $bill->status ?? 'draft',
                'bill_date' => $bill->bill_date ? 
                    Carbon::parse($bill->bill_date)->format('d/m/Y') : null,
                'loading_date' => $bill->loading_date ? 
                    Carbon::parse($bill->loading_date)->format('d/m/Y') : null,
            ];
        });
    }

    /**
     * Calcular totales del manifiesto
     * 
     * @return array Totales calculados
     */
    private function calculateTotals(): array
    {
        $bills = $this->voyage->billsOfLading;

        // Aplicar los mismos filtros que en formatBillsOfLading
        if (!empty($this->filters['status'])) {
            $bills = $bills->where('status', $this->filters['status']);
        }

        if (!empty($this->filters['shipper_id'])) {
            $bills = $bills->where('shipper_id', $this->filters['shipper_id']);
        }

        if (!empty($this->filters['consignee_id'])) {
            $bills = $bills->where('consignee_id', $this->filters['consignee_id']);
        }

        return [
            'total_bills' => $bills->count(),
            'total_packages' => $bills->sum('total_packages') ?? 0,
            'total_gross_weight_kg' => $bills->sum('gross_weight_kg') ?? 0,
            'total_net_weight_kg' => $bills->sum('net_weight_kg') ?? 0,
            'total_volume_m3' => $bills->sum('volume_m3') ?? 0,
            
            // Conversiones útiles
            'total_gross_weight_tons' => round(($bills->sum('gross_weight_kg') ?? 0) / 1000, 3),
            'total_net_weight_tons' => round(($bills->sum('net_weight_kg') ?? 0) / 1000, 3),
            
            // Estadísticas adicionales
            'unique_shippers' => $bills->pluck('shipper_id')->unique()->count(),
            'unique_consignees' => $bills->pluck('consignee_id')->unique()->count(),
            'bills_with_dangerous_goods' => $bills->where('contains_dangerous_goods', true)->count(),
            'bills_requiring_refrigeration' => $bills->where('requires_refrigeration', true)->count(),
        ];
    }

    /**
     * Generar metadata del reporte
     * 
     * @return array Metadata de auditoría
     */
    private function generateMetadata(): array
    {
        return [
            'report_type' => 'Manifiesto de Carga',
            'report_code' => 'MANIFEST',
            'generated_at' => Carbon::now()->format('d/m/Y H:i:s'),
            'generated_by' => $this->generatedBy,
            'generated_by_company' => $this->voyage->company->legal_name ?? 'Sistema',
            'voyage_id' => $this->voyage->id,
            'filters_applied' => !empty($this->filters) ? json_encode($this->filters) : null,
        ];
    }

    /**
     * Obtener nombre de archivo sugerido
     * 
     * @param string $format 'pdf' o 'excel'
     * @return string Nombre de archivo
     */
    public function getSuggestedFilename(string $format = 'pdf'): string
    {
        $voyageNumber = preg_replace('/[^A-Za-z0-9]/', '', $this->voyage->voyage_number ?? 'VOYAGE');
        $date = Carbon::now()->format('Ymd_His');
        $extension = $format === 'pdf' ? 'pdf' : 'xlsx';
        
        return "Manifiesto_{$voyageNumber}_{$date}.{$extension}";
    }

    /**
     * Validar que el viaje tiene datos suficientes para generar reporte
     * 
     * @return bool
     * @throws \Exception Si faltan datos críticos
     */
    public function validate(): bool
    {
        if (!$this->voyage->voyage_number) {
            throw new \Exception('El viaje no tiene número asignado.');
        }

        if ($this->voyage->billsOfLading->isEmpty()) {
            throw new \Exception('El viaje no tiene conocimientos de embarque asociados.');
        }

        if (!$this->voyage->company_id) {
            throw new \Exception('El viaje no está asociado a ninguna empresa.');
        }

        return true;
    }

    /**
     * Obtener el viaje actual
     * 
     * @return Voyage
     */
    public function getVoyage(): Voyage
    {
        return $this->voyage;
    }

    /**
     * Obtener filtros aplicados
     * 
     * @return array
     */
    public function getFilters(): array
    {
        return $this->filters;
    }
}