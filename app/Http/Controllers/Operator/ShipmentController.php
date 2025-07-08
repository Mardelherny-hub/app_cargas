<?php

namespace App\Http\Controllers\Operator;

use App\Http\Controllers\Controller;
use App\Traits\UserHelper;
use Illuminate\Http\Request;

class ShipmentController extends Controller
{
    use UserHelper;

    /**
     * Mostrar lista de cargas propias del operador.
     */
    public function index(Request $request)
    {
        $operator = $this->getUserOperator();

        if (!$operator) {
            return redirect()->route('operator.dashboard')
                ->with('error', 'No se encontró el operador asociado.');
        }

        // TODO: Implementar cuando esté el módulo de cargas
        $shipments = collect(); // Colección vacía por ahora

        // Estadísticas temporales para operador
        $stats = [
            'total' => 0,
            'pending' => 0,
            'completed' => 0,
            'in_transit' => 0,
            'draft' => 0,
        ];

        // Filtros disponibles para búsqueda futura
        $filters = [
            'status' => $request->get('status'),
            'date_from' => $request->get('date_from'),
            'date_to' => $request->get('date_to'),
            'puerto_origen' => $request->get('puerto_origen'),
            'puerto_destino' => $request->get('puerto_destino'),
        ];

        return view('operator.shipments.index', compact('shipments', 'stats', 'operator', 'filters'));
    }

    /**
     * Mostrar formulario para crear nueva carga.
     */
    public function create()
    {
        $operator = $this->getUserOperator();

        if (!$operator) {
            return redirect()->route('operator.dashboard')
                ->with('error', 'No se encontró el operador asociado.');
        }

        // Verificar permisos de creación
        if (!$operator->can_export) {
            return redirect()->route('operator.shipments.index')
                ->with('error', 'No tiene permisos para crear cargas.');
        }

        return view('operator.shipments.create', compact('operator'));
    }

    /**
     * Almacenar nueva carga.
     */
    public function store(Request $request)
    {
        $operator = $this->getUserOperator();

        if (!$operator || !$operator->can_export) {
            return redirect()->route('operator.shipments.index')
                ->with('error', 'No tiene permisos para crear cargas.');
        }

        // TODO: Implementar validación y creación cuando esté el módulo de cargas
        // Validaciones futuras:
        // $request->validate([
        //     'numero_viaje' => 'required|string|max:50',
        //     'fecha_embarque' => 'required|date',
        //     'puerto_origen' => 'required|string',
        //     'puerto_destino' => 'required|string',
        //     'nave_id' => 'required|exists:naves,id',
        //     'capitan_nombre' => 'required|string|max:100',
        // ]);

        return redirect()->route('operator.shipments.index')
            ->with('info', 'Funcionalidad de creación de cargas en desarrollo.');
    }

    /**
     * Mostrar detalles de la carga específica.
     */
    public function show($id)
    {
        $operator = $this->getUserOperator();

        if (!$operator) {
            return redirect()->route('operator.dashboard')
                ->with('error', 'No se encontró el operador asociado.');
        }

        // TODO: Verificar que la carga pertenece al operador
        // $shipment = Shipment::where('operator_id', $operator->id)->findOrFail($id);

        return view('operator.shipments.show', compact('operator'))
            ->with('info', 'Funcionalidad de visualización de cargas en desarrollo.');
    }

    /**
     * Mostrar formulario para editar carga.
     */
    public function edit($id)
    {
        $operator = $this->getUserOperator();

        if (!$operator || !$operator->can_export) {
            return redirect()->route('operator.shipments.index')
                ->with('error', 'No tiene permisos para editar cargas.');
        }

        // TODO: Verificar que la carga pertenece al operador y está en estado editable
        // $shipment = Shipment::where('operator_id', $operator->id)
        //     ->whereIn('status', ['draft', 'pending'])
        //     ->findOrFail($id);

        return view('operator.shipments.edit', compact('operator'))
            ->with('info', 'Funcionalidad de edición de cargas en desarrollo.');
    }

    /**
     * Actualizar carga existente.
     */
    public function update(Request $request, $id)
    {
        $operator = $this->getUserOperator();

        if (!$operator || !$operator->can_export) {
            return redirect()->route('operator.shipments.index')
                ->with('error', 'No tiene permisos para editar cargas.');
        }

        // TODO: Implementar validación y actualización
        return redirect()->route('operator.shipments.index')
            ->with('info', 'Funcionalidad de actualización de cargas en desarrollo.');
    }

    /**
     * Eliminar carga (solo si está en borrador).
     */
    public function destroy($id)
    {
        $operator = $this->getUserOperator();

        if (!$operator || !$operator->can_export) {
            return redirect()->route('operator.shipments.index')
                ->with('error', 'No tiene permisos para eliminar cargas.');
        }

        // TODO: Verificar que la carga está en estado borrador
        // $shipment = Shipment::where('operator_id', $operator->id)
        //     ->where('status', 'draft')
        //     ->findOrFail($id);
        // $shipment->delete();

        return redirect()->route('operator.shipments.index')
            ->with('info', 'Funcionalidad de eliminación de cargas en desarrollo.');
    }

    /**
     * Duplicar carga existente para crear una nueva.
     */
    public function duplicate($id)
    {
        $operator = $this->getUserOperator();

        if (!$operator || !$operator->can_export) {
            return redirect()->route('operator.shipments.index')
                ->with('error', 'No tiene permisos para duplicar cargas.');
        }

        // TODO: Implementar duplicación
        // $originalShipment = Shipment::where('operator_id', $operator->id)->findOrFail($id);
        // $newShipment = $originalShipment->replicate();
        // $newShipment->numero_viaje = null; // Se asignará automáticamente
        // $newShipment->status = 'draft';
        // $newShipment->save();

        return redirect()->route('operator.shipments.index')
            ->with('info', 'Funcionalidad de duplicación de cargas en desarrollo.');
    }

    /**
     * Actualizar estado de la carga.
     */
    public function updateStatus(Request $request, $id)
    {
        $operator = $this->getUserOperator();

        if (!$operator) {
            return back()->with('error', 'No se encontró el operador asociado.');
        }

        // TODO: Implementar actualización de estado con validaciones
        // Estados permitidos para operador externo: draft -> pending
        // $shipment = Shipment::where('operator_id', $operator->id)->findOrFail($id);
        // $newStatus = $request->input('status');

        // Validar transiciones de estado permitidas
        // if ($shipment->status === 'draft' && $newStatus === 'pending') {
        //     $shipment->update(['status' => $newStatus]);
        //     return back()->with('success', 'Estado actualizado correctamente.');
        // }

        return back()->with('info', 'Funcionalidad de cambio de estado en desarrollo.');
    }

    /**
     * Generar PDF de la carga.
     */
    public function generatePdf($id)
    {
        $operator = $this->getUserOperator();

        if (!$operator) {
            return back()->with('error', 'No se encontró el operador asociado.');
        }

        // TODO: Implementar generación de PDF
        // $shipment = Shipment::where('operator_id', $operator->id)->findOrFail($id);
        // $pdf = PDF::loadView('operator.shipments.pdf', compact('shipment'));
        // return $pdf->download('carga-' . $shipment->numero_viaje . '.pdf');

        return back()->with('info', 'Funcionalidad de generación de PDF en desarrollo.');
    }

    /**
     * Búsqueda de cargas con filtros.
     */
    public function search(Request $request)
    {
        $operator = $this->getUserOperator();

        if (!$operator) {
            return redirect()->route('operator.dashboard')
                ->with('error', 'No se encontró el operador asociado.');
        }

        return view('operator.shipments.search', compact('operator'));
    }

    /**
     * Resultados de búsqueda de cargas.
     */
    public function searchResults(Request $request)
    {
        $operator = $this->getUserOperator();

        if (!$operator) {
            return redirect()->route('operator.dashboard')
                ->with('error', 'No se encontró el operador asociado.');
        }

        // TODO: Implementar búsqueda con filtros
        $results = collect();
        $searchTerm = $request->input('search');

        return view('operator.shipments.search-results', compact('operator', 'results', 'searchTerm'));
    }

    /**
     * Mostrar historial de la carga.
     */
    public function history($id)
    {
        $operator = $this->getUserOperator();

        if (!$operator) {
            return redirect()->route('operator.dashboard')
                ->with('error', 'No se encontró el operador asociado.');
        }

        // TODO: Implementar historial usando auditoría
        // $shipment = Shipment::where('operator_id', $operator->id)->findOrFail($id);
        // $history = $shipment->audits()->with('user')->latest()->get();

        $history = collect();

        return view('operator.shipments.history', compact('operator', 'history'));
    }

    /**
     * Mostrar tracking de la carga.
     */
    public function tracking($id)
    {
        $operator = $this->getUserOperator();

        if (!$operator) {
            return redirect()->route('operator.dashboard')
                ->with('error', 'No se encontró el operador asociado.');
        }

        // TODO: Implementar tracking en tiempo real
        // $shipment = Shipment::where('operator_id', $operator->id)->findOrFail($id);
        // $trackingEvents = $shipment->trackingEvents()->latest()->get();

        $trackingEvents = collect();

        return view('operator.shipments.tracking', compact('operator', 'trackingEvents'));
    }

    /**
     * Verificar permisos específicos del operador.
     */
    private function checkOperatorPermissions($action)
    {
        $operator = $this->getUserOperator();

        if (!$operator) {
            return false;
        }

        switch ($action) {
            case 'create':
            case 'edit':
            case 'duplicate':
                return $operator->can_export;
            case 'view':
                return true; // Todos pueden ver sus propias cargas
            case 'delete':
                return $operator->can_export; // Solo quien puede crear, puede eliminar
            default:
                return false;
        }
    }
}
