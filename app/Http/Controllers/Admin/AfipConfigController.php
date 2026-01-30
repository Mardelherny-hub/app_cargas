<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AfipCustomsOffice;
use App\Models\AfipOperativeLocation;
use App\Models\PortAfipCustoms;
use App\Models\Port;
use App\Models\Country;
use Illuminate\Http\Request;

class AfipConfigController extends Controller
{
    /**
     * Vista principal con tabs: Aduanas, Lugares Operativos, Vínculos
     */
    public function index(Request $request)
    {
        $tab = $request->get('tab', 'customs-offices');
        
        // Aduanas AFIP
        $customsOffices = AfipCustomsOffice::orderBy('code')->get();
        
        // Lugares Operativos con filtro opcional
        $locationsQuery = AfipOperativeLocation::with(['country', 'port']);
        
        if ($request->filled('customs_filter')) {
            $locationsQuery->where('customs_code', $request->customs_filter);
        }
        if ($request->filled('country_filter')) {
            $locationsQuery->where('country_id', $request->country_filter);
        }
        
        $locations = $locationsQuery->orderBy('customs_code')
            ->orderBy('location_code')
            ->paginate(50)
            ->withQueryString();
        
        // Vínculos Puerto-Aduana
        $portCustoms = PortAfipCustoms::with(['port', 'afipCustomsOffice'])
            ->orderBy('port_id')
            ->get();
        
        // Datos para selects
        $countries = Country::where('active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'alpha2_code']);
            
        $ports = Port::where('active', true)
            ->whereHas('country', fn($q) => $q->where('alpha2_code', 'AR'))
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'city']);
        
        return view('admin.afip-config.index', compact(
            'tab',
            'customsOffices',
            'locations',
            'portCustoms',
            'countries',
            'ports'
        ));
    }

    // =========================================================================
    // ADUANAS AFIP
    // =========================================================================

    public function storeCustomsOffice(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|size:3|unique:afip_customs_offices,code',
            'name' => 'required|string|max:100',
        ]);

        AfipCustomsOffice::create($validated);

        return redirect()
            ->route('admin.afip-config.index', ['tab' => 'customs-offices'])
            ->with('success', 'Aduana creada correctamente.');
    }

    public function updateCustomsOffice(Request $request, AfipCustomsOffice $customsOffice)
    {
        $validated = $request->validate([
            'code' => 'required|string|size:3|unique:afip_customs_offices,code,' . $customsOffice->id,
            'name' => 'required|string|max:100',
        ]);

        $customsOffice->update($validated);

        return redirect()
            ->route('admin.afip-config.index', ['tab' => 'customs-offices'])
            ->with('success', 'Aduana actualizada correctamente.');
    }

    public function destroyCustomsOffice(AfipCustomsOffice $customsOffice)
    {
        // Verificar si tiene lugares operativos asociados
        $locationsCount = AfipOperativeLocation::where('customs_code', $customsOffice->code)->count();
        
        if ($locationsCount > 0) {
            return redirect()
                ->route('admin.afip-config.index', ['tab' => 'customs-offices'])
                ->with('error', "No se puede eliminar: tiene {$locationsCount} lugares operativos asociados.");
        }

        $customsOffice->delete();

        return redirect()
            ->route('admin.afip-config.index', ['tab' => 'customs-offices'])
            ->with('success', 'Aduana eliminada correctamente.');
    }

    public function toggleCustomsOffice(AfipCustomsOffice $customsOffice)
    {
        $customsOffice->update(['is_active' => !$customsOffice->is_active]);

        $status = $customsOffice->is_active ? 'activada' : 'desactivada';
        
        return redirect()
            ->route('admin.afip-config.index', ['tab' => 'customs-offices'])
            ->with('success', "Aduana {$status} correctamente.");
    }

    // =========================================================================
    // LUGARES OPERATIVOS
    // =========================================================================

    public function storeLocation(Request $request)
    {
        $validated = $request->validate([
            'country_id' => 'required|exists:countries,id',
            'port_id' => 'nullable|exists:ports,id',
            'customs_code' => 'required|string|max:10',
            'location_code' => 'required|string|max:10',
            'description' => 'required|string|max:150',
            'is_foreign' => 'boolean',
        ]);

        $validated['is_foreign'] = $request->boolean('is_foreign');

        AfipOperativeLocation::create($validated);

        return redirect()
            ->route('admin.afip-config.index', ['tab' => 'locations'])
            ->with('success', 'Lugar operativo creado correctamente.');
    }

    public function updateLocation(Request $request, AfipOperativeLocation $location)
    {
        $validated = $request->validate([
            'country_id' => 'required|exists:countries,id',
            'port_id' => 'nullable|exists:ports,id',
            'customs_code' => 'required|string|max:10',
            'location_code' => 'required|string|max:10',
            'description' => 'required|string|max:150',
            'is_foreign' => 'boolean',
        ]);

        $validated['is_foreign'] = $request->boolean('is_foreign');

        $location->update($validated);

        return redirect()
            ->route('admin.afip-config.index', ['tab' => 'locations'])
            ->with('success', 'Lugar operativo actualizado correctamente.');
    }

    public function destroyLocation(AfipOperativeLocation $location)
    {
        $location->delete();

        return redirect()
            ->route('admin.afip-config.index', ['tab' => 'locations'])
            ->with('success', 'Lugar operativo eliminado correctamente.');
    }

    public function toggleLocation(AfipOperativeLocation $location)
    {
        $location->update(['is_active' => !$location->is_active]);

        $status = $location->is_active ? 'activado' : 'desactivado';
        
        return redirect()
            ->route('admin.afip-config.index', ['tab' => 'locations'])
            ->with('success', "Lugar operativo {$status} correctamente.");
    }

    /**
     * API: Obtener lugares por código de aduana (para selects dinámicos)
     */
    public function locationsByCustoms(string $customsCode)
    {
        $locations = AfipOperativeLocation::where('customs_code', $customsCode)
            ->where('is_active', true)
            ->orderBy('location_code')
            ->get(['id', 'location_code', 'description']);

        return response()->json($locations);
    }

    // =========================================================================
    // VÍNCULOS PUERTO-ADUANA
    // =========================================================================

    public function attachPort(Request $request)
    {
        $validated = $request->validate([
            'port_id' => 'required|exists:ports,id',
            'afip_customs_office_id' => 'required|exists:afip_customs_offices,id',
            'is_default' => 'boolean',
        ]);

        $validated['is_default'] = $request->boolean('is_default');

        // Si es default, quitar default de otros vínculos del mismo puerto
        if ($validated['is_default']) {
            PortAfipCustoms::where('port_id', $validated['port_id'])
                ->update(['is_default' => false]);
        }

        PortAfipCustoms::create($validated);

        return redirect()
            ->route('admin.afip-config.index', ['tab' => 'port-customs'])
            ->with('success', 'Vínculo puerto-aduana creado correctamente.');
    }

    public function detachPort(PortAfipCustoms $portAfipCustoms)
    {
        $portAfipCustoms->delete();

        return redirect()
            ->route('admin.afip-config.index', ['tab' => 'port-customs'])
            ->with('success', 'Vínculo eliminado correctamente.');
    }

    public function setDefaultPort(PortAfipCustoms $portAfipCustoms)
    {
        // Quitar default de otros vínculos del mismo puerto
        PortAfipCustoms::where('port_id', $portAfipCustoms->port_id)
            ->update(['is_default' => false]);

        // Marcar este como default
        $portAfipCustoms->update(['is_default' => true]);

        return redirect()
            ->route('admin.afip-config.index', ['tab' => 'port-customs'])
            ->with('success', 'Aduana marcada como predeterminada.');
    }

    public function vincularPuerto(Request $request, AfipOperativeLocation $location)
    {
        $validated = $request->validate([
            'port_id' => 'required|exists:ports,id',
        ]);

        $location->update(['port_id' => $validated['port_id']]);

        $port = Port::find($validated['port_id']);

        return redirect()
            ->route('admin.afip-config.index', ['tab' => 'locations'])
            ->with('success', "Lugar {$location->location_code} vinculado a puerto {$port->code}.");
    }
}