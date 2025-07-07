<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Traits\UserHelper;
use Illuminate\Http\Request;

class TripController extends Controller
{
    use UserHelper;

    /**
     * Mostrar lista de viajes.
     */
    public function index(Request $request)
    {
        $company = $this->getUserCompany();

        if (!$company) {
            return redirect()->route('company.dashboard')
                ->with('error', 'No se encontró la empresa asociada.');
        }

        // TODO: Implementar cuando esté el módulo de viajes
        $trips = collect(); // Colección vacía por ahora

        // Estadísticas temporales
        $stats = [
            'total' => 0,
            'active' => 0,
            'completed' => 0,
            'pending' => 0,
        ];

        return view('company.trips.index', compact('trips', 'stats', 'company'));
    }

    /**
     * Mostrar formulario para crear viaje.
     */
    public function create()
    {
        $company = $this->getUserCompany();

        if (!$company) {
            return redirect()->route('company.dashboard')
                ->with('error', 'No se encontró la empresa asociada.');
        }

        return view('company.trips.create', compact('company'));
    }

    /**
     * Crear nuevo viaje.
     */
    public function store(Request $request)
    {
        // TODO: Implementar cuando esté el módulo de viajes
        return redirect()->route('company.trips.index')
            ->with('info', 'Funcionalidad de creación de viajes en desarrollo.');
    }

    /**
     * Mostrar detalles del viaje.
     */
    public function show($id)
    {
        $company = $this->getUserCompany();

        if (!$company) {
            return redirect()->route('company.dashboard')
                ->with('error', 'No se encontró la empresa asociada.');
        }

        // TODO: Implementar cuando esté el módulo de viajes
        return view('company.trips.show', compact('company'))
            ->with('info', 'Funcionalidad de visualización de viajes en desarrollo.');
    }

    /**
     * Mostrar formulario para editar viaje.
     */
    public function edit($id)
    {
        $company = $this->getUserCompany();

        if (!$company) {
            return redirect()->route('company.dashboard')
                ->with('error', 'No se encontró la empresa asociada.');
        }

        // TODO: Implementar cuando esté el módulo de viajes
        return view('company.trips.edit', compact('company'))
            ->with('info', 'Funcionalidad de edición de viajes en desarrollo.');
    }

    /**
     * Actualizar viaje.
     */
    public function update(Request $request, $id)
    {
        // TODO: Implementar cuando esté el módulo de viajes
        return redirect()->route('company.trips.index')
            ->with('info', 'Funcionalidad de actualización de viajes en desarrollo.');
    }

    /**
     * Eliminar viaje.
     */
    public function destroy($id)
    {
        // TODO: Implementar cuando esté el módulo de viajes
        return redirect()->route('company.trips.index')
            ->with('info', 'Funcionalidad de eliminación de viajes en desarrollo.');
    }

    /**
     * Cerrar viaje.
     */
    public function close($id)
    {
        // TODO: Implementar cuando esté el módulo de viajes
        return back()->with('info', 'Funcionalidad de cierre de viajes en desarrollo.');
    }

    /**
     * Reabrir viaje.
     */
    public function reopen($id)
    {
        // TODO: Implementar cuando esté el módulo de viajes
        return back()->with('info', 'Funcionalidad de reapertura de viajes en desarrollo.');
    }

    /**
     * Generar manifiesto del viaje.
     */
    public function manifest($id)
    {
        // TODO: Implementar cuando esté el módulo de viajes
        return back()->with('info', 'Funcionalidad de generación de manifiestos en desarrollo.');
    }

    /**
     * Mostrar cargas del viaje.
     */
    public function shipments($id)
    {
        $company = $this->getUserCompany();

        if (!$company) {
            return redirect()->route('company.dashboard')
                ->with('error', 'No se encontró la empresa asociada.');
        }

        // TODO: Implementar cuando esté el módulo de viajes
        $shipments = collect(); // Colección vacía por ahora

        return view('company.trips.shipments', compact('company', 'shipments'))
            ->with('info', 'Funcionalidad de gestión de cargas en viajes en desarrollo.');
    }

    /**
     * Agregar carga al viaje.
     */
    public function addShipment(Request $request, $id)
    {
        // TODO: Implementar cuando esté el módulo de viajes
        return back()->with('info', 'Funcionalidad de agregado de cargas en desarrollo.');
    }

    /**
     * Remover carga del viaje.
     */
    public function removeShipment($tripId, $shipmentId)
    {
        // TODO: Implementar cuando esté el módulo de viajes
        return back()->with('info', 'Funcionalidad de remoción de cargas en desarrollo.');
    }
}
