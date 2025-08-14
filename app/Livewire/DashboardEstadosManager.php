<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Voyage;
use App\Models\Shipment;
use App\Models\BillOfLading;
use App\Models\ShipmentItem;
use App\Traits\UserHelper;

class DashboardEstadosManager extends Component
{
    use WithPagination, UserHelper;

    public $filters = [];
    public $selectedEntity = 'voyages';
    public $selectedItems = [];
    public $selectAll = false;
    public $bulkAction = '';
    public $newStatus = '';
    public $reason = '';
    public $showBulkModal = false;
    public $search = '';

    // Estados disponibles por entidad
    public $entityStatuses = [
        'voyages' => [
            'planning' => 'Planificación',
            'confirmed' => 'Confirmado',
            'in_transit' => 'En Tránsito',
            'completed' => 'Completado'
        ],
        'shipments' => [
            'planning' => 'Planificación',
            'loading' => 'Cargando',
            'loaded' => 'Cargado',
            'in_transit' => 'En Tránsito',
            'arrived' => 'Arribado',
            'discharging' => 'Descargando',
            'completed' => 'Completado',
            'delayed' => 'Demorado'
        ],
        'bills_of_lading' => [
            'draft' => 'Borrador',
            'pending_review' => 'Pendiente Revisión',
            'verified' => 'Verificado',
            'sent_to_customs' => 'Enviado a Aduanas',
            'accepted' => 'Aceptado',
            'rejected' => 'Rechazado',
            'completed' => 'Completado',
            'cancelled' => 'Cancelado'
        ],
        'shipment_items' => [
            'draft' => 'Borrador',
            'validated' => 'Validado',
            'submitted' => 'Enviado',
            'accepted' => 'Aceptado',
            'rejected' => 'Rechazado',
            'modified' => 'Modificado'
        ]
    ];

    protected $listeners = ['refreshData' => '$refresh'];

    public function mount($filters = [])
    {
        $this->filters = $filters;
    }

    /**
     * Cambiar entidad seleccionada
     */
    public function changeEntity($entity)
    {
        $this->selectedEntity = $entity;
        $this->selectedItems = [];
        $this->selectAll = false;
        $this->resetPage();
    }

    /**
     * Alternar selección de todos los elementos
     */
    public function toggleSelectAll()
    {
        if ($this->selectAll) {
            $this->selectedItems = $this->getAllItemIds();
        } else {
            $this->selectedItems = [];
        }
    }

    /**
     * Obtener todos los IDs de la página actual
     */
    private function getAllItemIds()
    {
        return $this->getItems()->pluck('id')->toArray();
    }

    /**
     * Iniciar acción masiva
     */
    public function initiateBulkAction($action)
    {
        if (empty($this->selectedItems)) {
            session()->flash('error', 'Debe seleccionar al menos un elemento.');
            return;
        }

        $this->bulkAction = $action;
        
        if ($action === 'update_status') {
            $this->showBulkModal = true;
        }
    }

    /**
     * Ejecutar actualización masiva de estado
     */
    public function executeBulkStatusUpdate()
    {
        if (empty($this->newStatus)) {
            $this->addError('newStatus', 'Debe seleccionar un estado.');
            return;
        }

        if (empty($this->selectedItems)) {
            session()->flash('error', 'No hay elementos seleccionados.');
            return;
        }

        // Verificar permisos
        if (!$this->isCompanyAdmin() && !$this->canPerform('edit_cargas')) {
            session()->flash('error', 'No tiene permisos para realizar esta acción.');
            return;
        }

        try {
            DB::beginTransaction();

            $company = $this->getUserCompany();
            $updatedCount = 0;

            switch ($this->selectedEntity) {
                case 'voyages':
                    $updatedCount = Voyage::where('company_id', $company->id)
                        ->whereIn('id', $this->selectedItems)
                        ->update([
                            'status' => $this->newStatus,
                            'last_updated_by_user_id' => Auth::id(),
                            'updated_at' => now()
                        ]);
                    break;

                case 'shipments':
                    $updatedCount = Shipment::whereHas('voyage', function($q) use ($company) {
                            $q->where('company_id', $company->id);
                        })
                        ->whereIn('id', $this->selectedItems)
                        ->update([
                            'status' => $this->newStatus,
                            'last_updated_by_user_id' => Auth::id(),
                            'updated_at' => now()
                        ]);
                    break;

                case 'bills_of_lading':
                    $updatedCount = BillOfLading::whereHas('shipment.voyage', function($q) use ($company) {
                            $q->where('company_id', $company->id);
                        })
                        ->whereIn('id', $this->selectedItems)
                        ->update([
                            'status' => $this->newStatus,
                            'last_updated_by_user_id' => Auth::id(),
                            'updated_at' => now()
                        ]);
                    break;

                case 'shipment_items':
                    $updatedCount = ShipmentItem::whereHas('billOfLading.shipment.voyage', function($q) use ($company) {
                            $q->where('company_id', $company->id);
                        })
                        ->whereIn('id', $this->selectedItems)
                        ->update([
                            'status' => $this->newStatus,
                            'last_updated_by_user_id' => Auth::id(),
                            'updated_at' => now()
                        ]);
                    break;
            }

            DB::commit();

            // Log de la acción
            Log::info('Actualización masiva de estados', [
                'entity' => $this->selectedEntity,
                'items_count' => count($this->selectedItems),
                'updated_count' => $updatedCount,
                'new_status' => $this->newStatus,
                'user_id' => Auth::id(),
                'company_id' => $company->id,
                'reason' => $this->reason
            ]);

            session()->flash('message', "Se actualizaron {$updatedCount} elementos exitosamente.");
            
            // Resetear formulario
            $this->resetBulkForm();
            
            // Emitir evento para actualizar métricas
            $this->dispatch('refreshDashboard');

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Error en actualización masiva de estados', [
                'entity' => $this->selectedEntity,
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            session()->flash('error', 'Error al actualizar los estados: ' . $e->getMessage());
        }
    }

    /**
     * Cancelar acción masiva
     */
    public function cancelBulkAction()
    {
        $this->resetBulkForm();
    }

    /**
     * Resetear formulario de acción masiva
     */
    private function resetBulkForm()
    {
        $this->bulkAction = '';
        $this->newStatus = '';
        $this->reason = '';
        $this->showBulkModal = false;
        $this->selectedItems = [];
        $this->selectAll = false;
        $this->resetErrorBag();
    }

    /**
     * Obtener elementos según la entidad seleccionada
     */
    public function getItems()
    {
        $company = $this->getUserCompany();
        $query = null;

        switch ($this->selectedEntity) {
            case 'voyages':
                $query = Voyage::where('company_id', $company->id)
                    ->with(['leadVessel', 'originPort', 'destinationPort']);
                break;

            case 'shipments':
                $query = Shipment::whereHas('voyage', function($q) use ($company) {
                        $q->where('company_id', $company->id);
                    })
                    ->with(['voyage', 'vessel']);
                break;

            case 'bills_of_lading':
                $query = BillOfLading::whereHas('shipment.voyage', function($q) use ($company) {
                        $q->where('company_id', $company->id);
                    })
                    ->with(['shipment.voyage', 'shipper', 'consignee']);
                break;

            case 'shipment_items':
                $query = ShipmentItem::whereHas('billOfLading.shipment.voyage', function($q) use ($company) {
                        $q->where('company_id', $company->id);
                    })
                    ->with(['billOfLading.shipment.voyage']);
                break;
        }

        // Aplicar filtros
        if (!empty($this->search)) {
            $search = $this->search;
            $query->where(function($q) use ($search) {
                switch ($this->selectedEntity) {
                    case 'voyages':
                        $q->where('voyage_number', 'like', "%{$search}%")
                          ->orWhere('internal_reference', 'like', "%{$search}%");
                        break;
                    case 'shipments':
                        $q->where('shipment_number', 'like', "%{$search}%")
                          ->orWhere('internal_reference', 'like', "%{$search}%");
                        break;
                    case 'bills_of_lading':
                        $q->where('bill_number', 'like', "%{$search}%")
                          ->orWhere('house_bill_number', 'like', "%{$search}%");
                        break;
                    case 'shipment_items':
                        $q->where('item_reference', 'like', "%{$search}%")
                          ->orWhere('item_description', 'like', "%{$search}%");
                        break;
                }
            });
        }

        return $query->orderBy('updated_at', 'desc')->paginate(20);
    }

    /**
     * Obtener color del estado
     */
    public function getStatusColor($status)
    {
        $colors = [
            'planning' => 'bg-gray-100 text-gray-800',
            'confirmed' => 'bg-blue-100 text-blue-800',
            'draft' => 'bg-gray-100 text-gray-800',
            'pending_review' => 'bg-yellow-100 text-yellow-800',
            'verified' => 'bg-blue-100 text-blue-800',
            'sent_to_customs' => 'bg-purple-100 text-purple-800',
            'accepted' => 'bg-green-100 text-green-800',
            'rejected' => 'bg-red-100 text-red-800',
            'completed' => 'bg-green-100 text-green-800',
            'cancelled' => 'bg-red-100 text-red-800',
            'loading' => 'bg-orange-100 text-orange-800',
            'loaded' => 'bg-blue-100 text-blue-800',
            'in_transit' => 'bg-purple-100 text-purple-800',
            'arrived' => 'bg-green-100 text-green-800',
            'discharging' => 'bg-yellow-100 text-yellow-800',
            'delayed' => 'bg-red-100 text-red-800',
            'validated' => 'bg-blue-100 text-blue-800',
            'submitted' => 'bg-purple-100 text-purple-800',
            'modified' => 'bg-orange-100 text-orange-800'
        ];

        return $colors[$status] ?? 'bg-gray-100 text-gray-800';
    }

    public function render()
    {
        $items = $this->getItems();
        $availableStatuses = $this->entityStatuses[$this->selectedEntity] ?? [];
        return view('livewire.dashboard-estados-manager', [
            'items' => $items,
            'availableStatuses' => $availableStatuses
        ]);
    }
}