<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Traits\UserHelper;
use Illuminate\Http\Request;

class ShipmentController extends Controller
{
    use UserHelper;

    /**
     * Mostrar lista de cargas.
     */
    public function index(Request $request)
    {
        $company = $this->getUserCompany();

        if (!$company) {
            return redirect()->route('company.dashboard')
                ->with('error', 'No se encontró la empresa asociada.');
        }

        // TODO: Implementar cuando esté el módulo de cargas
        $shipments = collect(); // Colección vacía por ahora

        // Estadísticas temporales
        $stats = [
            'total' => 0,
            'pending' => 0,
            'completed' => 0,
            'in_transit' => 0,
        ];

        return view('company.shipments.index', compact('shipments', 'stats', 'company'));
    }

    /**
     * Mostrar formulario para crear carga.
     */
    public function create()
    {
        $company = $this->getUserCompany();

        if (!$company) {
            return redirect()->route('company.dashboard')
                ->with('error', 'No se encontró la empresa asociada.');
        }

        return view('company.shipments.create', compact('company'));
    }

    /**
     * Crear nueva carga.
     */
    public function store(Request $request)
    {
        // TODO: Implementar cuando esté el módulo de cargas
        return redirect()->route('company.shipments.index')
            ->with('info', 'Funcionalidad de creación de cargas en desarrollo.');
    }

    /**
     * Mostrar detalles de la carga.
     */
    public function show($id)
    {
        $company = $this->getUserCompany();

        if (!$company) {
            return redirect()->route('company.dashboard')
                ->with('error', 'No se encontró la empresa asociada.');
        }

        // TODO: Implementar cuando esté el módulo de cargas
        return view('company.shipments.show', compact('company'))
            ->with('info', 'Funcionalidad de visualización de cargas en desarrollo.');
    }

    /**
     * Mostrar formulario para editar carga.
     */
    public function edit($id)
    {
        $company = $this->getUserCompany();

        if (!$company) {
            return redirect()->route('company.dashboard')
                ->with('error', 'No se encontró la empresa asociada.');
        }

        // TODO: Implementar cuando esté el módulo de cargas
        return view('company.shipments.edit', compact('company'))
            ->with('info', 'Funcionalidad de edición de cargas en desarrollo.');
    }

    /**
     * Actualizar carga.
     */
    public function update(Request $request, $id)
    {
        // TODO: Implementar cuando esté el módulo de cargas
        return redirect()->route('company.shipments.index')
            ->with('info', 'Funcionalidad de actualización de cargas en desarrollo.');
    }

    /**
     * Eliminar carga.
     */
    public function destroy($id)
    {
        // TODO: Implementar cuando esté el módulo de cargas
        return redirect()->route('company.shipments.index')
            ->with('info', 'Funcionalidad de eliminación de cargas en desarrollo.');
    }

    /**
     * Actualizar estado de la carga.
     */
    public function updateStatus(Request $request, $id)
    {
        // TODO: Implementar cuando esté el módulo de cargas
        return back()->with('info', 'Funcionalidad de cambio de estado en desarrollo.');
    }

    /**
     * Duplicar carga.
     */
    public function duplicate($id)
    {
        // TODO: Implementar cuando esté el módulo de cargas
        return back()->with('info', 'Funcionalidad de duplicación de cargas en desarrollo.');
    }

    /**
     * Generar PDF de la carga.
     */
    public function generatePdf($id)
    {
        // TODO: Implementar cuando esté el módulo de cargas
        return back()->with('info', 'Funcionalidad de generación de PDF en desarrollo.');
    }
}
