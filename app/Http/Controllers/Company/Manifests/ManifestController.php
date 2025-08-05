<?php

namespace App\Http\Controllers\Company\Manifests;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Voyage;
use App\Models\Shipment;
use App\Models\BillOfLading;

class ManifestController extends Controller
{
    /**
     * Mostrar listado de manifiestos agrupados por viaje.
     */
    public function index(Request $request)
    {
        $voyages = Voyage::with('shipments.billsOfLading')
            ->where('company_id', auth()->user()->company_id)
            ->latest()
            ->paginate(20);

        return view('company.manifests.index', [
            'voyages' => $voyages
        ]);
    }

    /**
     * Formulario para crear un nuevo manifiesto manualmente.
     */
    public function create()
    {
        return view('company.manifests.create');
    }

    /**
     * Almacenar manifiesto manual nuevo.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'voyage_number' => 'required|string|unique:voyages,voyage_number',
            'origin_port_id' => 'required|exists:ports,id',
            'destination_port_id' => 'required|exists:ports,id',
        ]);

        $data['company_id'] = auth()->user()->company_id;
        $data['created_by_user_id'] = auth()->id();

        $voyage = Voyage::create($data);

        return redirect()->route('company.manifests.show', $voyage->id);
    }

    /**
     * Mostrar detalle completo del manifiesto.
     */
    public function show($id)
    {
        $voyage = Voyage::with('shipments.billsOfLading.shipper', 'shipments.vessel')
            ->where('company_id', auth()->user()->company_id)
            ->findOrFail($id);

        return view('company.manifests.show', [
            'voyage' => $voyage
        ]);
    }

    /**
     * Editar manifiesto existente (solo si está en estado editable).
     */
    public function edit($id)
    {
        $voyage = Voyage::where('company_id', auth()->user()->company_id)
            ->where('status', 'planning')
            ->findOrFail($id);

        return view('company.manifests.edit', [
            'voyage' => $voyage
        ]);
    }

    /**
     * Actualizar datos del manifiesto.
     */
    public function update(Request $request, $id)
    {
        $voyage = Voyage::where('company_id', auth()->user()->company_id)
            ->where('status', 'planning')
            ->findOrFail($id);

        $data = $request->validate([
            'origin_port_id' => 'required|exists:ports,id',
            'destination_port_id' => 'required|exists:ports,id',
        ]);

        $voyage->update($data);

        return redirect()->route('company.manifests.show', $voyage->id);
    }

    /**
     * Eliminar manifiesto (solo si está vacío y en planificación).
     */
    public function destroy($id)
    {
        $voyage = Voyage::where('company_id', auth()->user()->company_id)
            ->where('status', 'planning')
            ->doesntHave('shipments')
            ->findOrFail($id);

        $voyage->delete();

        return redirect()->route('company.manifests.index')->with('success', 'Manifiesto eliminado.');
    }
}