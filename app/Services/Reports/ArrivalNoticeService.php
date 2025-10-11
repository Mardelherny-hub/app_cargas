<?php

namespace App\Services\Reports;

use App\Models\Voyage;
use App\Models\Company;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * MÓDULO REPORTES - CARTAS DE AVISO DE LLEGADA
 * 
 * Service para generar notificaciones formales de arribo de mercadería
 * Agrupa conocimientos por consignatario y genera un PDF por cada uno
 * 
 * CARACTERÍSTICAS:
 * - Formato: Carta formal con membrete
 * - Orientación: Portrait (A4 vertical)
 * - Agrupación: Un PDF por consignee
 * - Filtros: Por viaje
 */
class ArrivalNoticeService
{
    private Voyage $voyage;
    private Company $company;
    private array $filters;
    private ?string $generatedBy;

    /**
     * Constructor del servicio
     * 
     * @param Voyage $voyage Viaje del cual generar avisos
     * @param array $filters Filtros adicionales (opcional)
     * @param string|null $generatedBy Usuario que genera el reporte
     */
    public function __construct(Voyage $voyage, array $filters = [], ?string $generatedBy = null)
    {
        $this->voyage = $voyage;
        $this->company = $voyage->company;
        $this->filters = $filters;
        $this->generatedBy = $generatedBy ?? auth()->user()->name ?? 'Sistema';
    }

    /**
     * Preparar datos completos del reporte
     * Agrupa BLs por consignee y retorna array con todos los consignatarios
     * 
     * @return array
     */
    public function prepareData(): array
    {
        // Cargar relaciones necesarias
        $this->voyage->load([
            'leadVessel',
            'destinationPort.country',
            'billsOfLading.consignee.primaryContact',
            'billsOfLading.shipper',
            'billsOfLading.loadingPort',
            'billsOfLading.dischargePort',
        ]);

        // Agrupar BLs por consignatario
        $consigneeGroups = $this->groupByConsignee();

        return [
            'voyage' => $this->formatVoyageData(),
            'company' => $this->formatCompanyData(),
            'consignee_groups' => $consigneeGroups,
            'total_consignees' => count($consigneeGroups),
            'metadata' => $this->generateMetadata(),
        ];
    }

    /**
     * Preparar datos para un consignatario específico
     * Genera carta individual para un solo consignee
     * 
     * @param int $consigneeId ID del consignatario
     * @return array|null
     */
    public function prepareDataForConsignee(int $consigneeId): ?array
    {
        $this->voyage->load([
            'leadVessel',
            'destinationPort.country',
            'billsOfLading' => function ($query) use ($consigneeId) {
                $query->where('consignee_id', $consigneeId);
            },
            'billsOfLading.consignee.primaryContact',
            'billsOfLading.shipper',
        ]);

        $bills = $this->voyage->billsOfLading;

        if ($bills->isEmpty()) {
            return null;
        }

        $consignee = $bills->first()->consignee;

        return [
            'voyage' => $this->formatVoyageData(),
            'company' => $this->formatCompanyData(),
            'consignee' => $this->formatConsigneeData($consignee),
            'bills' => $this->formatBillsForConsignee($bills),
            'totals' => $this->calculateTotalsForBills($bills),
            'metadata' => $this->generateMetadata(),
        ];
    }

    /**
     * Obtener nombre sugerido para el archivo PDF
     * 
     * @param string $format Formato del archivo (pdf)
     * @param int|null $consigneeId ID del consignatario (si es individual)
     * @return string
     */
    public function getSuggestedFilename(string $format = 'pdf', ?int $consigneeId = null): string
    {
        $date = now()->format('Ymd');
        $voyageNumber = str_replace(['/', ' '], '_', $this->voyage->voyage_number);

        if ($consigneeId) {
            $consignee = \App\Models\Client::find($consigneeId);
            $consigneeName = $consignee 
                ? str_replace([' ', '.', ','], '_', substr($consignee->legal_name, 0, 30))
                : 'consignee';
            
            return "aviso_llegada_{$voyageNumber}_{$consigneeName}_{$date}.{$format}";
        }

        return "avisos_llegada_{$voyageNumber}_{$date}.{$format}";
    }

    /**
     * Validar que el viaje tiene datos necesarios
     * 
     * @return bool
     */
    public function validate(): bool
    {
        // Verificar que el viaje tiene bills of lading
        if ($this->voyage->billsOfLading->isEmpty()) {
            return false;
        }

        // Verificar que tiene al menos un consignee válido
        $hasValidConsignee = $this->voyage->billsOfLading
            ->filter(fn($bill) => $bill->consignee !== null)
            ->isNotEmpty();

        if (!$hasValidConsignee) {
            return false;
        }

        // Verificar datos mínimos del viaje
        if (empty($this->voyage->voyage_number) || 
            empty($this->voyage->estimated_arrival_date) ||
            !$this->voyage->leadVessel ||
            !$this->voyage->destinationPort) {
            return false;
        }

        return true;
    }

    /**
     * Agrupar Bills of Lading por consignatario
     * 
     * @return array
     */
    private function groupByConsignee(): array
    {
        $groups = [];

        $billsByConsignee = $this->voyage->billsOfLading
            ->filter(fn($bill) => $bill->consignee !== null)
            ->groupBy('consignee_id');

        foreach ($billsByConsignee as $consigneeId => $bills) {
            $consignee = $bills->first()->consignee;

            $groups[] = [
                'consignee_id' => $consigneeId,
                'consignee' => $this->formatConsigneeData($consignee),
                'bills' => $this->formatBillsForConsignee($bills),
                'totals' => $this->calculateTotalsForBills($bills),
            ];
        }

        return $groups;
    }

    /**
     * Formatear datos del viaje
     * 
     * @return array
     */
    private function formatVoyageData(): array
    {
        return [
            'voyage_number' => $this->voyage->voyage_number ?? 'N/A',
            'vessel_name' => $this->voyage->leadVessel->name ?? 'N/A',
            'estimated_arrival_date' => $this->voyage->estimated_arrival_date 
                ? Carbon::parse($this->voyage->estimated_arrival_date)->format('d/m/Y')
                : 'N/A',
            'destination_port_name' => $this->voyage->destinationPort->name ?? 'N/A',
            'destination_port_full_address' => $this->getPortFullAddress(),
        ];
    }

    /**
     * Formatear datos de la empresa emisora
     * 
     * @return array
     */
    private function formatCompanyData(): array
    {
        return [
            'legal_name' => $this->company->legal_name ?? 'N/A',
            'commercial_name' => $this->company->commercial_name,
            'tax_id' => $this->company->tax_id ?? '',
            'address' => $this->company->address ?? '',
            'city' => $this->company->city ?? '',
            'postal_code' => $this->company->postal_code ?? '',
            'email' => $this->company->email ?? '',
            'phone' => $this->company->phone ?? '',
            'full_address' => $this->getCompanyFullAddress(),
        ];
    }

    /**
     * Formatear datos del consignatario
     * 
     * @param \App\Models\Client $consignee
     * @return array
     */
    private function formatConsigneeData($consignee): array
    {
        $primaryContact = $consignee->primaryContact;

        return [
            'legal_name' => $consignee->legal_name ?? 'N/A',
            'commercial_name' => $consignee->commercial_name,
            'tax_id' => $consignee->tax_id ?? '',
            'email' => $primaryContact->email ?? '',
            'phone' => $primaryContact->phone ?? $primaryContact->mobile_phone ?? '',
            'address' => $primaryContact->full_address ?? 'Dirección no disponible',
        ];
    }

    /**
     * Formatear bills of lading para un consignatario
     * 
     * @param Collection $bills
     * @return array
     */
    private function formatBillsForConsignee($bills): array
    {
        return $bills->map(function ($bill) {
            return [
                'bill_number' => $bill->bill_number ?? 'N/A',
                'shipper_name' => $bill->shipper->legal_name ?? 'N/A',
                'total_packages' => $bill->total_packages ?? 0,
                'gross_weight_kg' => $bill->gross_weight_kg ?? 0,
                'cargo_description' => $bill->cargo_description ?? 'N/A',
                'loading_port' => $bill->loadingPort->name ?? 'N/A',
            ];
        })->toArray();
    }

    /**
     * Calcular totales para un conjunto de bills
     * 
     * @param Collection $bills
     * @return array
     */
    private function calculateTotalsForBills($bills): array
    {
        return [
            'total_bills' => $bills->count(),
            'total_packages' => $bills->sum('total_packages') ?? 0,
            'total_weight_kg' => $bills->sum('gross_weight_kg') ?? 0,
        ];
    }

    /**
     * Generar metadata del reporte
     * 
     * @return array
     */
    private function generateMetadata(): array
    {
        return [
            'generated_at' => now()->format('d/m/Y H:i:s'),
            'generated_by' => $this->generatedBy,
            'company_name' => $this->company->legal_name ?? 'N/A',
            'filters_applied' => !empty($this->filters) ? $this->filters : null,
        ];
    }

    /**
     * Obtener dirección completa del puerto de destino
     * 
     * @return string
     */
    private function getPortFullAddress(): string
    {
        $port = $this->voyage->destinationPort;
        if (!$port) return 'N/A';

        $parts = array_filter([
            $port->name,
            $port->address ?? null,
            $port->city ?? null,
            $port->country->name ?? null,
        ]);

        return implode(', ', $parts);
    }

    /**
     * Obtener dirección completa de la empresa
     * 
     * @return string
     */
    private function getCompanyFullAddress(): string
    {
        $parts = array_filter([
            $this->company->address,
            $this->company->city,
            $this->company->postal_code,
        ]);

        return implode(', ', $parts);
    }
}