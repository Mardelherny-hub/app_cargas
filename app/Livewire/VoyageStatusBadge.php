<?php

namespace App\Livewire;

use App\Models\Voyage;
use Livewire\Component;
use Illuminate\Support\Facades\Auth;

class VoyageStatusBadge extends Component
{
    public Voyage $voyage;
    public $showConfirmation = false;
    public $nextStatus = null;
    public $affectedShipments = [];

    protected $listeners = ['refreshVoyage' => '$refresh'];

    public function mount(Voyage $voyage)
    {
        $this->voyage = $voyage;
    }

    public function getNextStatusProperty()
    {
        $statusFlow = [
            'planning' => 'approved',
            'approved' => 'in_transit',
            'in_transit' => 'at_destination',
            'at_destination' => 'completed',
            'completed' => null, // Estado final
            'cancelled' => null, // Estado final
            'delayed' => 'in_transit', // Puede volver a tránsito
        ];

        return $statusFlow[$this->voyage->status] ?? null;
    }

    public function getStatusColorProperty()
    {
        $colors = [
            'planning' => 'bg-blue-100 text-blue-800',
            'approved' => 'bg-green-100 text-green-800',
            'in_transit' => 'bg-yellow-100 text-yellow-800',
            'at_destination' => 'bg-purple-100 text-purple-800',
            'completed' => 'bg-green-100 text-green-800',
            'cancelled' => 'bg-red-100 text-red-800',
            'delayed' => 'bg-red-100 text-red-800',
        ];

        return $colors[$this->voyage->status] ?? 'bg-gray-100 text-gray-800';
    }

    public function getStatusLabelProperty()
    {
        $labels = [
            'planning' => 'Planificación',
            'approved' => 'Aprobado',
            'in_transit' => 'En Tránsito',
            'at_destination' => 'En Destino',
            'completed' => 'Completado',
            'cancelled' => 'Cancelado',
            'delayed' => 'Demorado',
        ];

        return $labels[$this->voyage->status] ?? 'Desconocido';
    }

    public function initiateStatusChange()
    {
        // Solo permitir si hay un siguiente estado
        if (!$this->nextStatus) {
            return;
        }

        // Verificar permisos básicos (usar los existentes)
        if (!Auth::user() || $this->voyage->company_id !== Auth::user()->company_id) {
            session()->flash('error', 'No tiene permisos para cambiar este viaje.');
            return;
        }

        // Obtener shipments afectados
        $this->affectedShipments = $this->getAffectedShipments();
        
        // Mostrar confirmación
        $this->showConfirmation = true;
    }

    public function confirmStatusChange($updateShipments = false)
    {
        try {
            // Actualizar voyage
            $this->voyage->update([
                'status' => $this->nextStatus,
                'last_updated_by_user_id' => Auth::id(),
                'last_updated_date' => now(),
            ]);

            // Actualizar shipments si se solicitó
            if ($updateShipments && !empty($this->affectedShipments)) {
                $suggestedShipmentStatus = $this->getSuggestedShipmentStatus();
                
                foreach ($this->affectedShipments as $shipmentData) {
                    $shipment = $this->voyage->shipments()->find($shipmentData['id']);
                    if ($shipment) {
                        $shipment->update([
                            'status' => $suggestedShipmentStatus,
                            'last_updated_by_user_id' => Auth::id(),
                            'last_updated_date' => now(),
                        ]);
                    }
                }
            }

            // Emitir evento
            $this->emit('voyage-status-changed', $this->voyage->id, $this->nextStatus);

            // Mensaje de éxito
            session()->flash('success', "Estado del viaje actualizado a: {$this->statusLabel}");

            // Cerrar modal
            $this->closeConfirmation();

            // Refrescar datos
            $this->voyage->refresh();

        } catch (\Exception $e) {
            session()->flash('error', 'Error al actualizar estado: ' . $e->getMessage());
        }
    }

    public function closeConfirmation()
    {
        $this->showConfirmation = false;
        $this->affectedShipments = [];
    }

    private function getAffectedShipments()
    {
        return $this->voyage->shipments->map(function ($shipment) {
            return [
                'id' => $shipment->id,
                'number' => $shipment->shipment_number,
                'vessel' => $shipment->vessel->name ?? 'N/A',
                'current_status' => $shipment->status,
                'suggested_status' => $this->getSuggestedShipmentStatus(),
            ];
        })->toArray();
    }

    private function getSuggestedShipmentStatus()
    {
        $suggestions = [
            'approved' => 'loaded',      // Viaje aprobado → cargas listas
            'in_transit' => 'in_transit', // Viaje en tránsito → cargas en tránsito
            'at_destination' => 'arrived', // Viaje llegó → cargas arribadas
            'completed' => 'completed',   // Viaje completado → cargas completadas
        ];

        return $suggestions[$this->nextStatus] ?? null;
    }

    public function render()
    {
        return view('livewire.voyage-status-badge');
    }
}