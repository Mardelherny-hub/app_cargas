<?php

namespace App\Services\Reports;

use App\Models\Voyage;
use App\Models\BillOfLading;
use App\Models\Shipment;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Service para generar Manifiesto Aduanero
 * 
 * Reporte oficial para presentación física ante autoridades aduaneras.
 * Incluye códigos aduaneros, datos de transbordo y formato oficial.
 * 
 * DIFERENCIA con MIC/DTA:
 * - MIC/DTA: XML para webservice AFIP
 * - Manifiesto Aduanero: PDF imprimible para presentar en aduana
 */
class CustomsManifestReportService
{
    protected $voyage;
    protected $filters;
    protected $userName;

    public function __construct(Voyage $voyage, array $filters = [], string $userName = 'Sistema')
    {
        $this->voyage = $voyage;
        $this->filters = $filters;
        $this->userName = $userName;
    }

    /**
     * Validar que el viaje tenga datos suficientes para generar el reporte
     */
    public function validate(): void
    {
        // Validar que el viaje tenga embarcación principal
        if (!$this->voyage->leadVessel) {
            throw new \Exception('El viaje no tiene una embarcación principal asignada.');
        }

        // Validar que el viaje tenga puerto de origen
        if (!$this->voyage->originPort) {
            throw new \Exception('El viaje no tiene puerto de origen definido.');
        }

        // Validar que el viaje tenga puerto de destino
        if (!$this->voyage->destinationPort) {
            throw new \Exception('El viaje no tiene puerto de destino definido.');
        }

        // Validar que el viaje tenga al menos un conocimiento
        $billsCount = BillOfLading::whereHas('shipment', function($query) {
            $query->where('voyage_id', $this->voyage->id);
        })->count();

        if ($billsCount === 0) {
            throw new \Exception('El viaje no tiene conocimientos de embarque (Bills of Lading) para incluir en el manifiesto.');
        }
    }

    /**
     * Preparar todos los datos necesarios para el reporte
     */
    public function prepareData(): array
    {
        // Cargar relaciones necesarias
        $this->voyage->load([
            'leadVessel',
            'originPort.country',
            'destinationPort.country',
            'transshipmentPort.country',
            'originCustoms',
            'destinationCustoms',
            'transshipmentCustoms',
            'company'
        ]);

        // Obtener todos los conocimientos del viaje con sus relaciones
        $billsOfLading = $this->getBillsOfLading();

        // Calcular totales
        $totals = $this->calculateTotals($billsOfLading);

        // Preparar información de transbordo si existe
        $transshipmentInfo = $this->getTransshipmentInfo();

        return [
            'voyage' => $this->voyage,
            'bills_of_lading' => $billsOfLading,
            'totals' => $totals,
            'transshipment_info' => $transshipmentInfo,
            'generation_date' => Carbon::now(),
            'generated_by' => $this->userName,
            'company' => $this->voyage->company,
            'filters' => $this->filters,
        ];
    }

    /**
     * Obtener Bills of Lading del viaje con todas sus relaciones
     */
    protected function getBillsOfLading()
    {
        $query = BillOfLading::whereHas('shipment', function($q) {
            $q->where('voyage_id', $this->voyage->id);
        })
        ->with([
            'shipment',
            'shipper',
            'consignee',
            'notifyParty',
            'loadingPort.country',
            'dischargePort.country',
            'loadingCustoms',
            'dischargeCustoms',
            'primaryCargoType',
            'primaryPackagingType',
            'shipmentItems' => function($q) {
                $q->select([
                    'id',
                    'bill_of_lading_id',
                    'package_quantity',
                    'gross_weight_kg',
                    'net_weight_kg',
                    'volume_m3',
                    'item_description',
                    'commodity_code',
                    'tariff_position',
                    'discharge_customs_code',
                    'operational_discharge_code'
                ]);
            }
        ]);

        // Aplicar filtros adicionales si existen
        if (!empty($this->filters['consignee_id'])) {
            $query->where('consignee_id', $this->filters['consignee_id']);
        }

        if (!empty($this->filters['discharge_port_id'])) {
            $query->where('discharge_port_id', $this->filters['discharge_port_id']);
        }

        // Ordenar por número de conocimiento
        $query->orderBy('bill_number');

        return $query->get();
    }

    /**
     * Calcular totales del manifiesto
     */
    protected function calculateTotals($billsOfLading): array
    {
        $totals = [
            'total_bills' => $billsOfLading->count(),
            'total_packages' => 0,
            'total_gross_weight' => 0,
            'total_net_weight' => 0,
            'total_volume' => 0,
        ];

        foreach ($billsOfLading as $bl) {
            $totals['total_packages'] += $bl->total_packages ?? 0;
            $totals['total_gross_weight'] += $bl->gross_weight_kg ?? 0;
            $totals['total_net_weight'] += $bl->net_weight_kg ?? 0;
            $totals['total_volume'] += $bl->volume_m3 ?? 0;
        }

        return $totals;
    }

    /**
     * Obtener información de transbordo si existe
     */
    protected function getTransshipmentInfo(): ?array
    {
        if (!$this->voyage->has_transshipment) {
            return null;
        }

        $info = [
            'has_transshipment' => true,
            'transshipment_port' => $this->voyage->transshipmentPort,
        ];

        // Buscar información de transbordo en los shipments
        $shipments = Shipment::where('voyage_id', $this->voyage->id)
            ->whereNotNull('origin_manifest_id')
            ->orWhereNotNull('origin_transport_doc')
            ->get();

        if ($shipments->isNotEmpty()) {
            $info['origin_manifests'] = $shipments->pluck('origin_manifest_id')->filter()->unique();
            $info['origin_transport_docs'] = $shipments->pluck('origin_transport_doc')->filter()->unique();
        }

        return $info;
    }

    /**
     * Obtener nombre del archivo PDF
     */
    public function getFileName(): string
    {
        $voyageNumber = str_replace(['/', '\\', ' '], '_', $this->voyage->voyage_number);
        $date = Carbon::now()->format('Ymd_His');
        
        return "Manifiesto_Aduanero_{$voyageNumber}_{$date}.pdf";
    }

    /**
     * Obtener configuración para el PDF
     */
    public function getPdfConfig(): array
    {
        return [
            'orientation' => 'landscape', // Apaisado para más columnas
            'paper_size' => 'a4',
            'margin_top' => 15,
            'margin_right' => 10,
            'margin_bottom' => 15,
            'margin_left' => 10,
        ];
    }
}