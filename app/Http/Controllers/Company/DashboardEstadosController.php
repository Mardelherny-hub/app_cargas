<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Models\Voyage;
use App\Models\Shipment;
use App\Models\BillOfLading;
use App\Models\ShipmentItem;
use App\Traits\UserHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Dashboard de Estados
 * 
 * Controlador para gestión centralizada de estados del sistema
 * Proporciona vista consolidada de todos los elementos y sus estados
 * 
 * PERMISOS REQUERIDOS:
 * - Rol: company-admin o user
 * - Acceso a la empresa correspondiente
 */
class DashboardEstadosController extends Controller
{
    use UserHelper;

    /**
     * Mostrar dashboard consolidado de estados
     */
    public function index(Request $request)
    {
        // Verificar permisos básicos
        if (!$this->isCompanyAdmin() && !$this->isUser()) {
            abort(403, 'No tiene permisos para acceder al dashboard de estados.');
        }

        $company = $this->getUserCompany();
        if (!$company) {
            return redirect()->route('dashboard')
                ->with('error', 'No se encontró la empresa asociada.');
        }

        if (!$this->canAccessCompany($company->id)) {
            abort(403, 'No tiene permisos para acceder a esta empresa.');
        }

        // Obtener filtros de la request
        $filters = $this->getFilters($request);
        
        // Construir métricas por entidad
        $metrics = $this->buildMetrics($company->id, $filters);
        
        // Obtener elementos recientes con cambios de estado
        $recentChanges = $this->getRecentChanges($company->id, $filters);
        
        // Distribución de estados por entidad
        $statusDistribution = $this->getStatusDistribution($company->id, $filters);

        return view('company.dashboard-estados.index', compact(
            'metrics',
            'recentChanges', 
            'statusDistribution',
            'filters'
        ));
    }

    /**
     * Obtener filtros aplicados
     */
    private function getFilters(Request $request): array
    {
        return [
            'entity_type' => $request->get('entity_type', 'all'),
            'status' => $request->get('status', 'all'),
            'date_from' => $request->get('date_from'),
            'date_to' => $request->get('date_to'),
            'search' => $request->get('search'),
        ];
    }

    /**
     * Construir métricas principales por entidad
     */
    private function buildMetrics(int $companyId, array $filters): array
    {
        $baseDate = $filters['date_from'] ? Carbon::parse($filters['date_from']) : null;
        $endDate = $filters['date_to'] ? Carbon::parse($filters['date_to']) : null;

        return [
            'voyages' => $this->getVoyageMetrics($companyId, $baseDate, $endDate),
            'shipments' => $this->getShipmentMetrics($companyId, $baseDate, $endDate),
            'bills_of_lading' => $this->getBillOfLadingMetrics($companyId, $baseDate, $endDate),
            'shipment_items' => $this->getShipmentItemMetrics($companyId, $baseDate, $endDate),
        ];
    }

    /**
     * Métricas de Voyages
     */
    private function getVoyageMetrics(int $companyId, ?Carbon $dateFrom, ?Carbon $dateTo): array
    {
        $query = Voyage::where('company_id', $companyId);
        
        if ($dateFrom) $query->where('created_at', '>=', $dateFrom);
        if ($dateTo) $query->where('created_at', '<=', $dateTo);

        $statusCounts = $query->groupBy('status')
                             ->selectRaw('status, count(*) as count')
                             ->pluck('count', 'status')
                             ->toArray();

        $total = array_sum($statusCounts);

        return [
            'total' => $total,
            'status_counts' => $statusCounts,
            'status_labels' => [
                'planning' => 'Planificación',
                'confirmed' => 'Confirmado', 
                'in_transit' => 'En Tránsito',
                'completed' => 'Completado'
            ]
        ];
    }

    /**
     * Métricas de Shipments
     */
    private function getShipmentMetrics(int $companyId, ?Carbon $dateFrom, ?Carbon $dateTo): array
    {
        $query = Shipment::whereHas('voyage', function($q) use ($companyId) {
            $q->where('company_id', $companyId);
        });
        
        if ($dateFrom) $query->where('created_at', '>=', $dateFrom);
        if ($dateTo) $query->where('created_at', '<=', $dateTo);

        $statusCounts = $query->groupBy('status')
                             ->selectRaw('status, count(*) as count')
                             ->pluck('count', 'status')
                             ->toArray();

        $total = array_sum($statusCounts);

        return [
            'total' => $total,
            'status_counts' => $statusCounts,
            'status_labels' => [
                'planning' => 'Planificación',
                'loading' => 'Cargando',
                'loaded' => 'Cargado',
                'in_transit' => 'En Tránsito',
                'arrived' => 'Arribado',
                'discharging' => 'Descargando',
                'completed' => 'Completado',
                'delayed' => 'Demorado'
            ]
        ];
    }

    /**
     * Métricas de Bills of Lading
     */
    private function getBillOfLadingMetrics(int $companyId, ?Carbon $dateFrom, ?Carbon $dateTo): array
    {
        $query = BillOfLading::whereHas('shipment.voyage', function($q) use ($companyId) {
            $q->where('company_id', $companyId);
        });
        
        if ($dateFrom) $query->where('created_at', '>=', $dateFrom);
        if ($dateTo) $query->where('created_at', '<=', $dateTo);

        $statusCounts = $query->groupBy('status')
                             ->selectRaw('status, count(*) as count')
                             ->pluck('count', 'status')
                             ->toArray();

        $total = array_sum($statusCounts);

        return [
            'total' => $total,
            'status_counts' => $statusCounts,
            'status_labels' => [
                'draft' => 'Borrador',
                'pending_review' => 'Pendiente Revisión',
                'verified' => 'Verificado',
                'sent_to_customs' => 'Enviado a Aduanas',
                'accepted' => 'Aceptado',
                'rejected' => 'Rechazado',
                'completed' => 'Completado',
                'cancelled' => 'Cancelado'
            ]
        ];
    }

    /**
     * Métricas de Shipment Items
     */
    private function getShipmentItemMetrics(int $companyId, ?Carbon $dateFrom, ?Carbon $dateTo): array
    {
        $query = ShipmentItem::whereHas('billOfLading.shipment.voyage', function($q) use ($companyId) {
            $q->where('company_id', $companyId);
        });
        
        if ($dateFrom) $query->where('created_at', '>=', $dateFrom);
        if ($dateTo) $query->where('created_at', '<=', $dateTo);

        $statusCounts = $query->groupBy('status')
                             ->selectRaw('status, count(*) as count')
                             ->pluck('count', 'status')
                             ->toArray();

        $total = array_sum($statusCounts);

        return [
            'total' => $total,
            'status_counts' => $statusCounts,
            'status_labels' => [
                'draft' => 'Borrador',
                'validated' => 'Validado',
                'submitted' => 'Enviado',
                'accepted' => 'Aceptado',
                'rejected' => 'Rechazado',
                'modified' => 'Modificado'
            ]
        ];
    }

    /**
     * Obtener cambios recientes de estado
     */
    private function getRecentChanges(int $companyId, array $filters): array
    {
        $changes = [];
        
        // Aquí podríamos implementar un sistema de log de cambios de estado
        // Por ahora retornamos elementos ordenados por fecha de actualización
        
        if ($filters['entity_type'] === 'all' || $filters['entity_type'] === 'voyages') {
            $voyageChanges = Voyage::where('company_id', $companyId)
                ->orderBy('updated_at', 'desc')
                ->limit(5)
                ->get()
                ->map(function($voyage) {
                    return [
                        'type' => 'voyage',
                        'entity' => $voyage,
                        'status' => $voyage->status,
                        'updated_at' => $voyage->updated_at,
                    ];
                });
            $changes = array_merge($changes, $voyageChanges->toArray());
        }

        if ($filters['entity_type'] === 'all' || $filters['entity_type'] === 'shipments') {
            $shipmentChanges = Shipment::with('voyage')
                ->whereHas('voyage', function($q) use ($companyId) {
                    $q->where('company_id', $companyId);
                })
                ->orderBy('updated_at', 'desc')
                ->limit(5)
                ->get()
                ->map(function($shipment) {
                    return [
                        'type' => 'shipment',
                        'entity' => $shipment,
                        'status' => $shipment->status,
                        'updated_at' => $shipment->updated_at,
                    ];
                });
            $changes = array_merge($changes, $shipmentChanges->toArray());
        }

        // Ordenar por fecha de actualización y limitar
        usort($changes, function($a, $b) {
            return $b['updated_at'] <=> $a['updated_at'];
        });

        return array_slice($changes, 0, 10);
    }

    /**
     * Obtener distribución de estados por entidad
     */
    private function getStatusDistribution(int $companyId, array $filters): array
    {
        return [
            'voyages' => $this->getVoyageStatusDistribution($companyId),
            'shipments' => $this->getShipmentStatusDistribution($companyId),
            'bills_of_lading' => $this->getBillOfLadingStatusDistribution($companyId),
            'shipment_items' => $this->getShipmentItemStatusDistribution($companyId),
        ];
    }

    private function getVoyageStatusDistribution(int $companyId): array
    {
        return Voyage::where('company_id', $companyId)
            ->groupBy('status')
            ->selectRaw('status, count(*) as count')
            ->get()
            ->mapWithKeys(function($item) {
                return [$item->status => $item->count];
            })
            ->toArray();
    }

    private function getShipmentStatusDistribution(int $companyId): array
    {
        return Shipment::whereHas('voyage', function($q) use ($companyId) {
                $q->where('company_id', $companyId);
            })
            ->groupBy('status')
            ->selectRaw('status, count(*) as count')
            ->get()
            ->mapWithKeys(function($item) {
                return [$item->status => $item->count];
            })
            ->toArray();
    }

    private function getBillOfLadingStatusDistribution(int $companyId): array
    {
        return BillOfLading::whereHas('shipment.voyage', function($q) use ($companyId) {
                $q->where('company_id', $companyId);
            })
            ->groupBy('status')
            ->selectRaw('status, count(*) as count')
            ->get()
            ->mapWithKeys(function($item) {
                return [$item->status => $item->count];
            })
            ->toArray();
    }

    private function getShipmentItemStatusDistribution(int $companyId): array
    {
        return ShipmentItem::whereHas('billOfLading.shipment.voyage', function($q) use ($companyId) {
                $q->where('company_id', $companyId);
            })
            ->groupBy('status')
            ->selectRaw('status, count(*) as count')
            ->get()
            ->mapWithKeys(function($item) {
                return [$item->status => $item->count];
            })
            ->toArray();
    }
}