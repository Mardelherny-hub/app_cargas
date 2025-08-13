<?php

namespace App\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\BillOfLading;
use App\Models\ShipmentItem;

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
        $transitions = [
            'draft' => ['pending_review', 'verified', 'cancelled'],
            'pending_review' => ['draft', 'verified', 'rejected'],
            'verified' => ['sent_to_customs', 'rejected'],
            'sent_to_customs' => ['accepted', 'rejected'],
            'accepted' => ['completed'],
            'rejected' => ['draft', 'pending_review'],
            'completed' => [],
            'cancelled' => []
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
            'submitted' => 'Enviado',
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
        } else {
            $modelCompanyId = $this->model->billOfLading->shipment->voyage->company_id;
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

        // Verificar rol de empresa "Cargas" - necesito revisar cómo se almacenan
        // Temporalmente omito esta validación para probar
        // TODO: Implementar verificación de company_roles correctamente
        
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

    public function initiateStatusChange($status)
    {
        $this->selectedStatus = $status;
        
        // Estados que requieren confirmación
        $requiresConfirmation = ['sent_to_customs', 'rejected', 'cancelled', 'completed'];
        
        if (in_array($status, $requiresConfirmation) || $this->showReason) {
            $this->showModal = true;
        } else {
            $this->changeStatus();
        }
    }

    public function changeStatus()
    {
        if (empty($this->selectedStatus)) {
            $this->addError('selectedStatus', 'Debe seleccionar un estado.');
            return;
        }

        if (!array_key_exists($this->selectedStatus, $this->availableStatuses)) {
            $this->addError('selectedStatus', 'Estado no válido.');
            return;
        }

        try {
            DB::beginTransaction();

            $oldStatus = $this->currentStatus;
            
            // Actualizar el modelo
            $this->model->update([
                'status' => $this->selectedStatus,
                'last_updated_by_user_id' => Auth::id(),
            ]);

            // Agregar nota si se proporcionó
            if (!empty($this->reason)) {
                $this->addStatusNote($oldStatus, $this->selectedStatus, $this->reason);
            }

            DB::commit();

            // Actualizar propiedades del componente
            $this->currentStatus = $this->selectedStatus;
            $this->loadAvailableStatuses();
            $this->resetForm();

            // Log del cambio
            Log::info('Estado cambiado via Livewire', [
                'model_type' => $this->modelType,
                'model_id' => $this->model->id,
                'from_status' => $oldStatus,
                'to_status' => $this->selectedStatus,
                'user_id' => Auth::id(),
                'reason' => $this->reason
            ]);

            // Emitir evento para actualizar otros componentes
            $this->dispatch('statusChanged', [
                'modelType' => $this->modelType,
                'modelId' => $this->model->id,
                'newStatus' => $this->selectedStatus
            ]);

            // Mostrar mensaje de éxito
            session()->flash('message', 'Estado actualizado exitosamente.');

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Error cambiando estado via Livewire', [
                'model_type' => $this->modelType,
                'model_id' => $this->model->id,
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            $this->addError('general', 'Error al cambiar el estado: ' . $e->getMessage());
        }
    }

    private function addStatusNote($oldStatus, $newStatus, $reason)
    {
        if ($this->modelType === BillOfLading::class) {
            $currentNotes = $this->model->internal_notes ?? '';
            $logEntry = sprintf(
                "[%s] Estado cambiado de '%s' a '%s' por %s - %s",
                now()->format('Y-m-d H:i:s'),
                $oldStatus,
                $newStatus,
                Auth::user()->name,
                $reason
            );
            
            $this->model->update([
                'internal_notes' => $currentNotes . "\n" . $logEntry
            ]);
        }
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
            'draft' => 'bg-gray-100 text-gray-800',
            'pending_review' => 'bg-yellow-100 text-yellow-800',
            'verified' => 'bg-blue-100 text-blue-800',
            'sent_to_customs' => 'bg-purple-100 text-purple-800',
            'accepted' => 'bg-green-100 text-green-800',
            'rejected' => 'bg-red-100 text-red-800',
            'completed' => 'bg-green-100 text-green-800',
            'cancelled' => 'bg-red-100 text-red-800',
            'validated' => 'bg-blue-100 text-blue-800',
            'submitted' => 'bg-purple-100 text-purple-800',
            'modified' => 'bg-orange-100 text-orange-800'
        ];

        return $colors[$this->currentStatus] ?? 'bg-gray-100 text-gray-800';
    }

    public function getStatusLabelProperty()
    {
        $labels = [
            'draft' => 'Borrador',
            'pending_review' => 'Pendiente Revisión',
            'verified' => 'Verificado',
            'sent_to_customs' => 'Enviado a Aduanas',
            'accepted' => 'Aceptado',
            'rejected' => 'Rechazado',
            'completed' => 'Completado',
            'cancelled' => 'Cancelado',
            'validated' => 'Validado',
            'submitted' => 'Enviado',
            'modified' => 'Modificado'
        ];

        return $labels[$this->currentStatus] ?? 'Desconocido';
    }

    public function render()
    {
        return view('livewire.status-changer');
    }
}