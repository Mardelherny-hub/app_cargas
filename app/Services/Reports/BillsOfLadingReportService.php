<?php

namespace App\Services\Reports;

use App\Models\BillOfLading;
use App\Models\Company;
use Illuminate\Support\Collection;
use Carbon\Carbon;

/**
 * Servicio para generar Listado de Conocimientos de Embarque
 * Reporte filtrable por fechas, clientes, puertos y estado
 */
class BillsOfLadingReportService
{
    private Company $company;
    private array $filters;
    private ?string $generatedBy;

    public function __construct(Company $company, array $filters = [], ?string $generatedBy = null)
    {
        $this->company = $company;
        $this->filters = $filters;
        $this->generatedBy = $generatedBy ?? auth()->user()->name ?? 'Sistema';
    }

    public function prepareData(): array
    {
        $bills = $this->getBillsOfLading();
        
        return [
            'company' => $this->formatCompanyData(),
            'bills_of_lading' => $this->formatBillsOfLading($bills),
            'totals' => $this->calculateTotals($bills),
            'filters' => $this->getAppliedFilters(),
            'metadata' => $this->generateMetadata($bills->count()),
        ];
    }

    private function getBillsOfLading(): Collection
    {
        $query = BillOfLading::whereHas('shipment.voyage', function($q) {
                $q->where('company_id', $this->company->id);
            })
            ->with([
                'shipper', 'consignee', 'notifyParty',
                'loadingPort.country', 'dischargePort.country',
                'shipment.voyage', 'shipment.vessel',
                'shipmentItems'
            ]);

        // Filtros de fecha
        if (!empty($this->filters['date_from'])) {
            $query->where('bill_date', '>=', $this->filters['date_from']);
        }
        if (!empty($this->filters['date_to'])) {
            $query->where('bill_date', '<=', $this->filters['date_to']);
        }

        // Filtros de clientes
        if (!empty($this->filters['shipper_id'])) {
            $query->where('shipper_id', $this->filters['shipper_id']);
        }
        if (!empty($this->filters['consignee_id'])) {
            $query->where('consignee_id', $this->filters['consignee_id']);
        }

        // Filtros de puertos
        if (!empty($this->filters['loading_port_id'])) {
            $query->where('loading_port_id', $this->filters['loading_port_id']);
        }
        if (!empty($this->filters['discharge_port_id'])) {
            $query->where('discharge_port_id', $this->filters['discharge_port_id']);
        }

        // Filtro de estado
        if (!empty($this->filters['status'])) {
            $query->where('status', $this->filters['status']);
        }

        return $query->orderBy('bill_date', 'desc')->get();
    }

    private function formatCompanyData(): array
    {
        return [
            'legal_name' => $this->company->legal_name ?? 'No especificado',
            'commercial_name' => $this->company->commercial_name ?? null,
            'tax_id' => $this->company->tax_id ?? null,
        ];
    }

    private function formatBillsOfLading(Collection $bills): Collection
    {
        return $bills->map(function ($bill, $index) {
            return [
                'line_number' => $index + 1,
                'bill_number' => $bill->bill_number ?? 'S/N',
                'bill_date' => $bill->bill_date ? Carbon::parse($bill->bill_date)->format('d/m/Y') : 'N/A',
                'loading_date' => $bill->loading_date ? Carbon::parse($bill->loading_date)->format('d/m/Y') : null,
                'voyage_number' => $bill->shipment->voyage->voyage_number ?? 'N/A',
                'vessel_name' => $bill->shipment->vessel->name ?? 'N/A',
                'shipper_name' => $bill->shipper ? ($bill->shipper->commercial_name ?: $bill->shipper->legal_name) : 'N/A',
                'shipper_tax_id' => $bill->shipper->tax_id ?? null,
                'consignee_name' => $bill->consignee ? ($bill->consignee->commercial_name ?: $bill->consignee->legal_name) : 'N/A',
                'consignee_tax_id' => $bill->consignee->tax_id ?? null,
                'loading_port' => $bill->loadingPort->name ?? 'N/A',
                'loading_port_code' => $bill->loadingPort->code ?? null,
                'loading_country' => $bill->loadingPort->country->name ?? null,
                'discharge_port' => $bill->dischargePort->name ?? 'N/A',
                'discharge_port_code' => $bill->dischargePort->code ?? null,
                'discharge_country' => $bill->dischargePort->country->name ?? null,
                'total_packages' => $bill->total_packages ?? 0,
                'gross_weight_kg' => $bill->gross_weight_kg ?? 0,
                'net_weight_kg' => $bill->net_weight_kg ?? 0,
                'volume_m3' => $bill->volume_m3 ?? 0,
                'cargo_description' => $bill->cargo_description ?? 'N/A',
                'status' => $bill->status ?? 'draft',
                'status_label' => $this->getStatusLabel($bill->status),
                'contains_dangerous_goods' => $bill->contains_dangerous_goods ?? false,
                'requires_refrigeration' => $bill->requires_refrigeration ?? false,
            ];
        });
    }

    private function calculateTotals(Collection $bills): array
    {
        return [
            'total_bills' => $bills->count(),
            'total_packages' => $bills->sum('total_packages') ?? 0,
            'total_gross_weight_kg' => $bills->sum('gross_weight_kg') ?? 0,
            'total_net_weight_kg' => $bills->sum('net_weight_kg') ?? 0,
            'total_volume_m3' => $bills->sum('volume_m3') ?? 0,
            'total_gross_weight_tons' => round(($bills->sum('gross_weight_kg') ?? 0) / 1000, 3),
            'total_net_weight_tons' => round(($bills->sum('net_weight_kg') ?? 0) / 1000, 3),
            'by_status' => $bills->groupBy('status')->map->count()->toArray(),
            'unique_shippers' => $bills->pluck('shipper_id')->unique()->count(),
            'unique_consignees' => $bills->pluck('consignee_id')->unique()->count(),
            'bills_with_dangerous_goods' => $bills->where('contains_dangerous_goods', true)->count(),
        ];
    }

    private function getAppliedFilters(): array
    {
        $applied = [];
        
        if (!empty($this->filters['date_from'])) {
            $applied[] = 'Desde: ' . Carbon::parse($this->filters['date_from'])->format('d/m/Y');
        }
        if (!empty($this->filters['date_to'])) {
            $applied[] = 'Hasta: ' . Carbon::parse($this->filters['date_to'])->format('d/m/Y');
        }
        if (!empty($this->filters['status'])) {
            $applied[] = 'Estado: ' . $this->getStatusLabel($this->filters['status']);
        }
        
        return $applied;
    }

    private function generateMetadata(int $recordCount): array
    {
        return [
            'report_type' => 'Listado de Conocimientos',
            'report_code' => 'BILLS_OF_LADING',
            'generated_at' => Carbon::now()->format('d/m/Y H:i:s'),
            'generated_by' => $this->generatedBy,
            'generated_by_company' => $this->company->legal_name ?? 'Sistema',
            'record_count' => $recordCount,
            'period' => $this->getPeriodText(),
        ];
    }

    private function getPeriodText(): string
    {
        if (!empty($this->filters['date_from']) && !empty($this->filters['date_to'])) {
            return Carbon::parse($this->filters['date_from'])->format('d/m/Y') . ' - ' . 
                   Carbon::parse($this->filters['date_to'])->format('d/m/Y');
        }
        if (!empty($this->filters['date_from'])) {
            return 'Desde ' . Carbon::parse($this->filters['date_from'])->format('d/m/Y');
        }
        if (!empty($this->filters['date_to'])) {
            return 'Hasta ' . Carbon::parse($this->filters['date_to'])->format('d/m/Y');
        }
        return 'Todos los períodos';
    }

    private function getStatusLabel(string $status): string
    {
        $labels = [
            'draft' => 'Borrador',
            'verified' => 'Verificado',
            'sent_to_customs' => 'Enviado a Aduana',
            'customs_approved' => 'Aprobado por Aduana',
            'in_transit' => 'En Tránsito',
            'delivered' => 'Entregado',
            'cancelled' => 'Cancelado',
        ];
        
        return $labels[$status] ?? ucfirst($status);
    }

    public function getSuggestedFilename(string $format = 'pdf'): string
    {
        $date = Carbon::now()->format('Ymd_His');
        $extension = $format === 'pdf' ? 'pdf' : 'xlsx';
        
        return "Listado_Conocimientos_{$date}.{$extension}";
    }

    public function validate(): bool
    {
        if (!$this->company->id) {
            throw new \Exception('Empresa no especificada.');
        }
        
        return true;
    }
}