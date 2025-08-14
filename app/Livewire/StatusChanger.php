<?php

namespace App\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\BillOfLading;
use App\Models\ShipmentItem;
use App\Models\Voyage;
use App\Models\Shipment;

class StatusChanger extends Component
{
    public $model;
    public $modelType;
    public $currentStatus;
    public $availableStatuses = [];
    public $selectedStatus = '';
    public $reason = '';
    public $showReason = false;
    public $size = 'normal';
    public $showAsDropdown = true;
    public $showModal = false;

    protected $listeners = ['refreshStatus' => '$refresh'];

    public function mount($model, $showReason = false, $size = 'normal', $showAsDropdown = true)
    {
        $this->model = $model;
        $this->modelType = get_class($model);
        $this->currentStatus = $model->status;
        $this->showReason = $showReason;
        $this->size = $size;
        $this->showAsDropdown = $showAsDropdown;
        
        $this->loadAvailableStatuses();
    }

    public function loadAvailableStatuses()
    {
        if ($this->modelType === BillOfLading::class) {
            $this->availableStatuses = $this->getBillOfLadingTransitions();
        } elseif ($this->modelType === ShipmentItem::class) {
            $this->availableStatuses = $this->getShipmentItemTransitions();
        } elseif ($this->modelType === Voyage::class) {
            $this->availableStatuses = $this->getVoyageTransitions();
        } elseif ($this->modelType === Shipment::class) {
            $this->availableStatuses = $this->getShipmentTransitions();
        }
        
        // Debug temporal - remover después
        \Log::info('StatusChanger Debug', [
            'model_type' => $this->modelType,
            'current_status' => $this->currentStatus,
            'available_statuses' => $this->availableStatuses,
            'user_id' => Auth::id(),
            'user_roles' => Auth::user()->roles ?? 'no roles',
            'company_roles' => Auth::user()->company_roles ?? 'no company roles'
        ]);
    }

    private function getBillOfLadingTransitions()
    {
        // CORREGIDO: Estados reales según enum de la base de datos
        $transitions = [
            'draft' => ['pending_review', 'verified', 'cancelled'],
            'pending_review' => ['draft', 'verified', 'rejected'],
            'verified' => ['sent_to_customs', 'rejected'],
            'sent_to_customs' => ['accepted', 'rejected'],
            'accepted' => ['completed'],
            'rejected' => ['draft', 'pending_review'],
            'completed' => [], // Estado final
            'cancelled' => []  // Estado final
        ];

        $labels = [
            'draft' => 'Borrador',
            'pending_review' => 'Pendiente Revisión',
            'verified' => 'Verificado',
            'sent_to_customs' => 'Enviado a Aduanas',
            'accepted' => 'Aceptado',
            'rejected' => 'Rechazado',
            'completed' => 'Completado',
            'cancelled' => 'Cancelado'
        ];

        $availableStatuses = $transitions[$this->currentStatus] ?? [];
        $result = [];
        
        foreach ($availableStatuses as $status) {
            if ($this->canUserChangeToStatus($status)) {
                $result[$status] = $labels[$status] ?? $status;
            }
        }

        return $result;
    }

private function getShipmentItemTransitions()
    {
        $transitions = [
            'draft' => ['validated', 'rejected'],
            'validated' => ['submitted', 'rejected'],
            'submitted' => ['accepted', 'rejected'],
            'accepted' => [],
            'rejected' => ['draft', 'validated'],
            'modified' => ['validated', 'rejected']
        ];

        $labels = [
            'draft' => 'Borrador',
            'validated' => 'Validado',
            'submitted' => 'Enviado a Aduana',
            'accepted' => 'Aceptado',
            'rejected' => 'Rechazado',
            'modified' => 'Modificado'
        ];

        $availableStatuses = $transitions[$this->currentStatus] ?? [];
        $result = [];
        
        foreach ($availableStatuses as $status) {
            if ($this->canUserChangeToStatus($status)) {
                $result[$status] = $labels[$status] ?? $status;
            }
        }

        return $result;
    }

    private function getVoyageTransitions()
{
    $transitions = [
        'planning' => ['confirmed'],
        'confirmed' => ['in_transit'],
        'in_transit' => ['completed'],
        'completed' => []
    ];

    $labels = [
        'planning' => 'Planificación',
        'confirmed' => 'Confirmado',
        'in_transit' => 'En Tránsito', 
        'completed' => 'Completado'
    ];

    $availableStatuses = $transitions[$this->currentStatus] ?? [];
    $result = [];
    
    foreach ($availableStatuses as $status) {
        if ($this->canUserChangeToStatus($status)) {
            $result[$status] = $labels[$status] ?? $status;
        }
    }

    return $result;
}

private function getShipmentTransitions()
{
    $transitions = [
        'planning' => ['loading', 'delayed'],
        'loading' => ['loaded', 'delayed'],
        'loaded' => ['in_transit'],
        'in_transit' => ['arrived', 'delayed'],
        'arrived' => ['discharging'],
        'discharging' => ['completed'],
        'completed' => [],
        'delayed' => ['loading', 'in_transit', 'arrived']
    ];

    $labels = [
        'planning' => 'Planificación',
        'loading' => 'Cargando',
        'loaded' => 'Cargado',
        'in_transit' => 'En Tránsito',
        'arrived' => 'Arribado',
        'discharging' => 'Descargando',
        'completed' => 'Completado',
        'delayed' => 'Demorado'
    ];

    $availableStatuses = $transitions[$this->currentStatus] ?? [];
    $result = [];
    
    foreach ($availableStatuses as $status) {
        if ($this->canUserChangeToStatus($status)) {
            $result[$status] = $labels[$status] ?? $status;
        }
    }

    return $result;
}

private function canUserChangeToStatus($status)
{
    $user = Auth::user();
    
    if (!$user) {
        \Log::info('StatusChanger: No user authenticated');
        return false;
    }

    // Obtener la empresa del usuario usando la relación polimórfica
    $userCompanyId = null;
    if ($user->userable_type === 'App\Models\Company') {
        $userCompanyId = $user->userable_id;
    } elseif ($user->userable_type === 'App\Models\Operator' && $user->userable) {
        $userCompanyId = $user->userable->company_id ?? null;
    }

    // Verificar acceso a la empresa
    if ($this->modelType === BillOfLading::class) {
        $modelCompanyId = $this->model->shipment->voyage->company_id;
    } elseif ($this->modelType === ShipmentItem::class) {
        $modelCompanyId = $this->model->billOfLading->shipment->voyage->company_id;
    } elseif ($this->modelType === Voyage::class) {
        $modelCompanyId = $this->model->company_id;
    } elseif ($this->modelType === Shipment::class) {
        $modelCompanyId = $this->model->voyage->company_id;
    } else {
        return false;
    }

    if ($userCompanyId !== $modelCompanyId) {
        \Log::info('StatusChanger: Company mismatch', [
            'user_company' => $userCompanyId,
            'model_company' => $modelCompanyId,
            'user_userable_type' => $user->userable_type,
            'status' => $status
        ]);
        return false;
    }

    // Estados que requieren permisos especiales
    $restrictedTransitions = ['sent_to_customs', 'accepted', 'completed'];
    if (in_array($status, $restrictedTransitions)) {
        $hasSpecialPermission = $user->hasRole('admin') || $user->hasRole('company-admin');
        if (!$hasSpecialPermission) {
            \Log::info('StatusChanger: Missing special permission for restricted transition', [
                'status' => $status,
                'user_roles' => $user->roles->pluck('name'),
                'has_admin' => $user->hasRole('admin'),
                'has_company_admin' => $user->hasRole('company-admin')
            ]);
            return false;
        }
    }

    \Log::info('StatusChanger: Permission granted', ['status' => $status]);
    return true;
}

    public function initiateStatusChange($newStatus)
    {
        $this->selectedStatus = $newStatus;
        
        // Requerir motivo para ciertos estados críticos
        if (in_array($newStatus, ['rejected', 'cancelled', 'modified'])) {
            $this->showModal = true;
        } else {
            $this->confirmStatusChange();
        }
    }

    public function confirmStatusChange()
    {
        $this->validate([
            'selectedStatus' => 'required|string',
            'reason' => in_array($this->selectedStatus, ['rejected', 'cancelled']) ? 'required|string|min:5' : 'nullable|string'
        ], [
            'reason.required' => 'Debe especificar un motivo para este cambio de estado.',
            'reason.min' => 'El motivo debe tener al menos 5 caracteres.'
        ]);

        try {
            DB::beginTransaction();

            $oldStatus = $this->currentStatus;
            $newStatus = $this->selectedStatus;
            $reason = $this->reason;

            // Actualizar el estado
            $this->model->update(['status' => $newStatus]);

            // TODO: Aquí se implementarán las notificaciones por email
            $this->sendStatusChangeNotification($oldStatus, $newStatus, $reason);

            // Agregar log al modelo si tiene campo internal_notes
            if ($this->model->getTable() === 'bills_of_lading' || $this->model->getTable() === 'shipment_items') {
                $this->addStatusChangeLog($oldStatus, $newStatus, $reason);
            }

            DB::commit();

            // Actualizar el estado local
            $this->currentStatus = $newStatus;
            $this->loadAvailableStatuses();
            $this->resetForm();

            // Emitir evento para refrescar otros componentes
            $this->dispatch('statusChanged', [
                'model_type' => $this->modelType,
                'model_id' => $this->model->id,
                'old_status' => $oldStatus,
                'new_status' => $newStatus
            ]);

            session()->flash('message', 'Estado actualizado exitosamente.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error updating status: ' . $e->getMessage());
            $this->addError('general', 'Error al actualizar el estado: ' . $e->getMessage());
        }
    }

    private function sendStatusChangeNotification($oldStatus, $newStatus, $reason)
    {
        // TODO: Implementar sistema de notificaciones por email
        // Esta función se implementará en el siguiente paso
        \Log::info('Status change notification needed', [
            'model_type' => $this->modelType,
            'model_id' => $this->model->id,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'reason' => $reason,
            'changed_by' => Auth::user()->name ?? 'Unknown'
        ]);
    }

    private function addStatusChangeLog($oldStatus, $newStatus, $reason)
    {
        $currentNotes = $this->model->internal_notes ?? '';
        $reason = $reason ? ' - Motivo: ' . $reason : '';
        
        $logEntry = sprintf(
            "[%s] Estado cambiado de '%s' a '%s' por %s%s",
            now()->format('Y-m-d H:i:s'),
            $this->getStatusLabel($oldStatus),
            $this->getStatusLabel($newStatus),
            Auth::user()->name ?? 'Usuario desconocido',
            $reason
        );
        
        $this->model->update([
            'internal_notes' => $currentNotes . "\n" . $logEntry
        ]);
    }

    public function cancelModal()
    {
        $this->resetForm();
    }

    private function resetForm()
    {
        $this->selectedStatus = '';
        $this->reason = '';
        $this->showModal = false;
        $this->resetErrorBag();
    }

 public function getStatusColorProperty()
{
    $colors = [
        // Bills of Lading
        'draft' => 'bg-gray-100 text-gray-800',
        'pending_review' => 'bg-yellow-100 text-yellow-800',
        'verified' => 'bg-blue-100 text-blue-800',
        'sent_to_customs' => 'bg-purple-100 text-purple-800',
        'accepted' => 'bg-green-100 text-green-800',
        'rejected' => 'bg-red-100 text-red-800',
        'completed' => 'bg-green-100 text-green-800',
        'cancelled' => 'bg-red-100 text-red-800',
        
        // Shipment Items
        'validated' => 'bg-blue-100 text-blue-800',
        'submitted' => 'bg-purple-100 text-purple-800',
        'modified' => 'bg-orange-100 text-orange-800',
        
        // Voyages
        'planning' => 'bg-gray-100 text-gray-800',
        'confirmed' => 'bg-blue-100 text-blue-800',
        'in_transit' => 'bg-purple-100 text-purple-800',
        
        // Shipments
        'loading' => 'bg-orange-100 text-orange-800',
        'loaded' => 'bg-blue-100 text-blue-800',
        'arrived' => 'bg-green-100 text-green-800',
        'discharging' => 'bg-yellow-100 text-yellow-800',
        'delayed' => 'bg-red-100 text-red-800'
    ];

    return $colors[$this->currentStatus] ?? 'bg-gray-100 text-gray-800';
}

    public function getStatusLabelProperty()
    {
        return $this->getStatusLabel($this->currentStatus);
    }

   private function getStatusLabel($status)
{
    $labels = [
        // Bills of Lading
        'draft' => 'Borrador',
        'pending_review' => 'Pendiente Revisión',
        'verified' => 'Verificado',
        'sent_to_customs' => 'Enviado a Aduanas',
        'accepted' => 'Aceptado',
        'rejected' => 'Rechazado',
        'completed' => 'Completado',
        'cancelled' => 'Cancelado',
        
        // Shipment Items
        'validated' => 'Validado',
        'submitted' => 'Enviado a Aduana',
        'modified' => 'Modificado',
        
        // Voyages
        'planning' => 'Planificación',
        'confirmed' => 'Confirmado',
        'in_transit' => 'En Tránsito',
        
        // Shipments
        'loading' => 'Cargando',
        'loaded' => 'Cargado',
        'arrived' => 'Arribado',
        'discharging' => 'Descargando',
        'delayed' => 'Demorado'
    ];

    return $labels[$status] ?? 'Desconocido';
}

    public function render()
    {
        return view('livewire.status-changer');
    }
}