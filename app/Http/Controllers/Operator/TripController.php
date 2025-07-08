<?php

namespace App\Http\Controllers\Operator;

use App\Http\Controllers\Controller;
use App\Traits\UserHelper;
use Illuminate\Http\Request;

class TripController extends Controller
{
    use UserHelper;

    /**
     * Mostrar lista de viajes propios del operador.
     */
    public function index(Request $request)
    {
        $operator = $this->getUserOperator();

        if (!$operator) {
            return redirect()->route('operator.dashboard')
                ->with('error', 'No se encontró el operador asociado.');
        }

        // TODO: Implementar cuando esté el módulo de viajes
        $trips = collect(); // Colección vacía por ahora

        // Estadísticas temporales para operador
        $stats = [
            'total' => 0,
            'planning' => 0,
            'in_progress' => 0,
            'completed' => 0,
            'cancelled' => 0,
        ];

        // Filtros disponibles para búsqueda futura
        $filters = [
            'status' => $request->get('status'),
            'date_from' => $request->get('date_from'),
            'date_to' => $request->get('date_to'),
            'puerto_origen' => $request->get('puerto_origen'),
            'puerto_destino' => $request->get('puerto_destino'),
        ];

        return view('operator.trips.index', compact('trips', 'stats', 'operator', 'filters'));
    }

    /**
     * Mostrar formulario para crear nuevo viaje.
     */
    public function create()
    {
        $operator = $this->getUserOperator();

        if (!$operator) {
            return redirect()->route('operator.dashboard')
                ->with('error', 'No se encontró el operador asociado.');
        }

        return view('operator.trips.create', compact('operator'));
    }

    /**
     * Almacenar nuevo viaje.
     */
    public function store(Request $request)
    {
        $operator = $this->getUserOperator();

        if (!$operator) {
            return redirect()->route('operator.trips.index')
                ->with('error', 'No se encontró el operador asociado.');
        }

        // TODO: Implementar validación y creación cuando esté el módulo de viajes
        // Validaciones futuras:
        // $request->validate([
        //     'codigo_viaje' => 'nullable|string|max:50|unique:trips',
        //     'fecha_inicio' => 'required|date|after:now',
        //     'fecha_estimada_llegada' => 'nullable|date|after:fecha_inicio',
        //     'puerto_partida' => 'required|string',
        //     'puerto_destino_final' => 'required|string|different:puerto_partida',
        //     'embarcacion_principal' => 'required|exists:embarcaciones,id',
        //     'capitan_viaje' => 'required|string|max:100',
        // ]);

        return redirect()->route('operator.trips.index')
            ->with('info', 'Funcionalidad de creación de viajes en desarrollo.');
    }

    /**
     * Mostrar detalles del viaje específico.
     */
    public function show($id)
    {
        $operator = $this->getUserOperator();

        if (!$operator) {
            return redirect()->route('operator.dashboard')
                ->with('error', 'No se encontró el operador asociado.');
        }

        // TODO: Verificar que el viaje pertenece al operador
        // $trip = Trip::where('operator_id', $operator->id)->findOrFail($id);

        return view('operator.trips.show', compact('operator'))
            ->with('info', 'Funcionalidad de visualización de viajes en desarrollo.');
    }

    /**
     * Mostrar formulario para editar viaje.
     */
    public function edit($id)
    {
        $operator = $this->getUserOperator();

        if (!$operator) {
            return redirect()->route('operator.trips.index')
                ->with('error', 'No se encontró el operador asociado.');
        }

        // TODO: Verificar que el viaje pertenece al operador y está en estado editable
        // $trip = Trip::where('operator_id', $operator->id)
        //     ->whereIn('status', ['planning', 'draft'])
        //     ->findOrFail($id);

        return view('operator.trips.edit', compact('operator'))
            ->with('info', 'Funcionalidad de edición de viajes en desarrollo.');
    }

    /**
     * Actualizar viaje existente.
     */
    public function update(Request $request, $id)
    {
        $operator = $this->getUserOperator();

        if (!$operator) {
            return redirect()->route('operator.trips.index')
                ->with('error', 'No se encontró el operador asociado.');
        }

        // TODO: Implementar validación y actualización
        return redirect()->route('operator.trips.index')
            ->with('info', 'Funcionalidad de actualización de viajes en desarrollo.');
    }

    /**
     * Eliminar viaje (solo si está en planificación).
     */
    public function destroy($id)
    {
        $operator = $this->getUserOperator();

        if (!$operator) {
            return redirect()->route('operator.trips.index')
                ->with('error', 'No se encontró el operador asociado.');
        }

        // TODO: Verificar que el viaje está en estado de planificación
        // $trip = Trip::where('operator_id', $operator->id)
        //     ->where('status', 'planning')
        //     ->findOrFail($id);
        // $trip->delete();

        return redirect()->route('operator.trips.index')
            ->with('info', 'Funcionalidad de eliminación de viajes en desarrollo.');
    }

    /**
     * Cerrar viaje (completar).
     */
    public function close($id)
    {
        $operator = $this->getUserOperator();

        if (!$operator) {
            return back()->with('error', 'No se encontró el operador asociado.');
        }

        // TODO: Implementar cierre de viaje
        // $trip = Trip::where('operator_id', $operator->id)->findOrFail($id);
        // if ($trip->status === 'in_progress') {
        //     $trip->update([
        //         'status' => 'completed',
        //         'fecha_real_llegada' => now(),
        //         'closed_by' => auth()->id(),
        //     ]);
        //     return back()->with('success', 'Viaje cerrado exitosamente.');
        // }

        return back()->with('info', 'Funcionalidad de cierre de viajes en desarrollo.');
    }

    /**
     * Reabrir viaje cerrado.
     */
    public function reopen($id)
    {
        $operator = $this->getUserOperator();

        if (!$operator) {
            return back()->with('error', 'No se encontró el operador asociado.');
        }

        // TODO: Implementar reapertura de viaje
        // $trip = Trip::where('operator_id', $operator->id)->findOrFail($id);
        // if ($trip->status === 'completed') {
        //     $trip->update([
        //         'status' => 'in_progress',
        //         'fecha_real_llegada' => null,
        //         'reopened_by' => auth()->id(),
        //     ]);
        //     return back()->with('success', 'Viaje reabierto exitosamente.');
        // }

        return back()->with('info', 'Funcionalidad de reapertura de viajes en desarrollo.');
    }

    /**
     * Generar manifiesto del viaje.
     */
    public function manifest($id)
    {
        $operator = $this->getUserOperator();

        if (!$operator) {
            return back()->with('error', 'No se encontró el operador asociado.');
        }

        // TODO: Implementar generación de manifiesto
        // $trip = Trip::where('operator_id', $operator->id)->with('shipments')->findOrFail($id);
        // $pdf = PDF::loadView('operator.trips.manifest-pdf', compact('trip'));
        // return $pdf->download('manifiesto-' . $trip->codigo_viaje . '.pdf');

        return back()->with('info', 'Funcionalidad de generación de manifiestos en desarrollo.');
    }

    /**
     * Mostrar cargas asignadas al viaje.
     */
    public function shipments($id)
    {
        $operator = $this->getUserOperator();

        if (!$operator) {
            return redirect()->route('operator.dashboard')
                ->with('error', 'No se encontró el operador asociado.');
        }

        // TODO: Implementar cuando esté el módulo de viajes
        // $trip = Trip::where('operator_id', $operator->id)->findOrFail($id);
        // $assignedShipments = $trip->shipments;
        // $availableShipments = Shipment::where('operator_id', $operator->id)
        //     ->where('status', 'pending')
        //     ->whereNull('trip_id')
        //     ->get();

        $assignedShipments = collect();
        $availableShipments = collect();

        return view('operator.trips.shipments', compact('operator', 'assignedShipments', 'availableShipments'))
            ->with('info', 'Funcionalidad de gestión de cargas en viajes en desarrollo.');
    }

    /**
     * Agregar carga al viaje.
     */
    public function addShipment(Request $request, $id)
    {
        $operator = $this->getUserOperator();

        if (!$operator) {
            return back()->with('error', 'No se encontró el operador asociado.');
        }

        // TODO: Implementar asignación de carga
        // $trip = Trip::where('operator_id', $operator->id)->findOrFail($id);
        // $shipment = Shipment::where('operator_id', $operator->id)
        //     ->where('id', $request->shipment_id)
        //     ->findOrFail();

        // Verificar capacidad disponible
        // if ($trip->checkCapacity($shipment->peso_total)) {
        //     $shipment->update(['trip_id' => $trip->id]);
        //     return back()->with('success', 'Carga agregada al viaje exitosamente.');
        // }

        return back()->with('info', 'Funcionalidad de asignación de cargas en desarrollo.');
    }

    /**
     * Remover carga del viaje.
     */
    public function removeShipment($tripId, $shipmentId)
    {
        $operator = $this->getUserOperator();

        if (!$operator) {
            return back()->with('error', 'No se encontró el operador asociado.');
        }

        // TODO: Implementar remoción de carga
        // $trip = Trip::where('operator_id', $operator->id)->findOrFail($tripId);
        // $shipment = $trip->shipments()->findOrFail($shipmentId);
        // $shipment->update(['trip_id' => null]);

        return back()->with('info', 'Funcionalidad de remoción de cargas en desarrollo.');
    }

    // === FUNCIONALIDADES DE TRANSFERENCIA ===

    /**
     * Mostrar transferencias del operador.
     */
    public function transfers()
    {
        $operator = $this->getUserOperator();

        if (!$operator || !$operator->can_transfer) {
            return redirect()->route('operator.dashboard')
                ->with('error', 'No tiene permisos para gestionar transferencias.');
        }

        // TODO: Implementar cuando esté el módulo de transferencias
        $sentTransfers = collect();
        $receivedTransfers = collect();

        return view('operator.transfers.index', compact('operator', 'sentTransfers', 'receivedTransfers'))
            ->with('info', 'Funcionalidad de transferencias en desarrollo.');
    }

    /**
     * Crear nueva transferencia.
     */
    public function createTransfer()
    {
        $operator = $this->getUserOperator();

        if (!$operator || !$operator->can_transfer) {
            return redirect()->route('operator.dashboard')
                ->with('error', 'No tiene permisos para crear transferencias.');
        }

        return view('operator.transfers.create', compact('operator'))
            ->with('info', 'Funcionalidad de creación de transferencias en desarrollo.');
    }

    /**
     * Almacenar transferencia.
     */
    public function storeTransfer(Request $request)
    {
        $operator = $this->getUserOperator();

        if (!$operator || !$operator->can_transfer) {
            return redirect()->route('operator.transfers.index')
                ->with('error', 'No tiene permisos para crear transferencias.');
        }

        // TODO: Implementar creación de transferencia
        // $request->validate([
        //     'trip_id' => 'required|exists:trips,id',
        //     'target_operator_id' => 'required|exists:operators,id',
        //     'reason' => 'required|string|max:255',
        //     'notes' => 'nullable|string',
        // ]);

        return redirect()->route('operator.transfers.index')
            ->with('info', 'Funcionalidad de creación de transferencias en desarrollo.');
    }

    /**
     * Mostrar transferencia específica.
     */
    public function showTransfer($id)
    {
        $operator = $this->getUserOperator();

        if (!$operator || !$operator->can_transfer) {
            return redirect()->route('operator.dashboard')
                ->with('error', 'No tiene permisos para ver transferencias.');
        }

        // TODO: Verificar que la transferencia pertenece al operador
        return view('operator.transfers.show', compact('operator'))
            ->with('info', 'Funcionalidad de visualización de transferencias en desarrollo.');
    }

    /**
     * Cancelar transferencia enviada.
     */
    public function cancelTransfer($id)
    {
        $operator = $this->getUserOperator();

        if (!$operator || !$operator->can_transfer) {
            return back()->with('error', 'No tiene permisos para cancelar transferencias.');
        }

        // TODO: Implementar cancelación
        // $transfer = Transfer::where('from_operator_id', $operator->id)
        //     ->where('status', 'pending')
        //     ->findOrFail($id);
        // $transfer->update(['status' => 'cancelled']);

        return back()->with('info', 'Funcionalidad de cancelación de transferencias en desarrollo.');
    }

    /**
     * Mostrar transferencias recibidas.
     */
    public function receivedTransfers()
    {
        $operator = $this->getUserOperator();

        if (!$operator || !$operator->can_transfer) {
            return redirect()->route('operator.dashboard')
                ->with('error', 'No tiene permisos para gestionar transferencias.');
        }

        // TODO: Implementar consulta de transferencias recibidas
        $receivedTransfers = collect();

        return view('operator.transfers.received', compact('operator', 'receivedTransfers'))
            ->with('info', 'Funcionalidad de transferencias recibidas en desarrollo.');
    }

    /**
     * Aceptar transferencia recibida.
     */
    public function acceptTransfer($id)
    {
        $operator = $this->getUserOperator();

        if (!$operator || !$operator->can_transfer) {
            return back()->with('error', 'No tiene permisos para aceptar transferencias.');
        }

        // TODO: Implementar aceptación
        // $transfer = Transfer::where('to_operator_id', $operator->id)
        //     ->where('status', 'pending')
        //     ->findOrFail($id);

        // DB::transaction(function () use ($transfer) {
        //     $transfer->update(['status' => 'accepted']);
        //     $transfer->trip->update(['operator_id' => $transfer->to_operator_id]);
        // });

        return back()->with('info', 'Funcionalidad de aceptación de transferencias en desarrollo.');
    }

    /**
     * Rechazar transferencia recibida.
     */
    public function rejectTransfer($id)
    {
        $operator = $this->getUserOperator();

        if (!$operator || !$operator->can_transfer) {
            return back()->with('error', 'No tiene permisos para rechazar transferencias.');
        }

        // TODO: Implementar rechazo
        // $transfer = Transfer::where('to_operator_id', $operator->id)
        //     ->where('status', 'pending')
        //     ->findOrFail($id);
        // $transfer->update(['status' => 'rejected']);

        return back()->with('info', 'Funcionalidad de rechazo de transferencias en desarrollo.');
    }

    // === MÉTODOS AUXILIARES ===

    /**
     * Verificar permisos específicos del operador para viajes.
     */
    private function checkTripPermissions($action)
    {
        $operator = $this->getUserOperator();

        if (!$operator) {
            return false;
        }

        switch ($action) {
            case 'create':
            case 'edit':
            case 'close':
            case 'reopen':
                return true; // Todos los operadores pueden gestionar sus viajes
            case 'transfer':
                return $operator->can_transfer;
            case 'view':
                return true; // Todos pueden ver sus propios viajes
            case 'delete':
                return true; // Solo viajes en planificación
            default:
                return false;
        }
    }

    /**
     * Obtener estados de viaje permitidos para el operador.
     */
    private function getAllowedTripStatuses()
    {
        return [
            'planning' => 'En Planificación',
            'in_progress' => 'En Curso',
            'completed' => 'Completado',
            'cancelled' => 'Cancelado',
        ];
    }

    /**
     * Validar transiciones de estado de viaje.
     */
    private function isValidStatusTransition($currentStatus, $newStatus)
    {
        $allowedTransitions = [
            'planning' => ['in_progress', 'cancelled'],
            'in_progress' => ['completed', 'cancelled'],
            'completed' => ['in_progress'], // Solo para reapertura
            'cancelled' => ['planning'], // Solo para reactivación
        ];

        return in_array($newStatus, $allowedTransitions[$currentStatus] ?? []);
    }

    /**
     * Calcular estadísticas del viaje.
     */
    private function calculateTripStatistics($trip)
    {
        // TODO: Implementar cálculos cuando esté el módulo
        return [
            'total_shipments' => 0,
            'total_weight' => 0,
            'capacity_used' => 0,
            'estimated_duration' => 0,
            'actual_duration' => 0,
        ];
    }
}
