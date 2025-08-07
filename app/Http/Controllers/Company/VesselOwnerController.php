<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Models\VesselOwner;
use App\Models\Vessel;
use App\Models\Country;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use App\Traits\UserHelper;

class VesselOwnerController extends Controller
{

    use UserHelper;

    /**
     * Display a listing of vessel owners.
     */
    public function index(Request $request)
    {
        $company = $this->getUserCompany();
        $companyId = $company ? $company->id : null;
        if (is_null($companyId)) {
            abort(403, 'No autorizado: usuario sin empresa asignada.');
        }
        $query = VesselOwner::with(['country', 'vessels'])
            ->byCompany($companyId);

        // Filtros
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('legal_name', 'like', "%{$search}%")
                  ->orWhere('commercial_name', 'like', "%{$search}%")
                  ->orWhere('tax_id', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('transportista_type')) {
            $query->where('transportista_type', $request->transportista_type);
        }

        if ($request->filled('country_id')) {
            $query->where('country_id', $request->country_id);
        }

        // Ordenamiento
        $sortBy = $request->get('sort_by', 'legal_name');
        $sortOrder = $request->get('sort_order', 'asc');
        $query->orderBy($sortBy, $sortOrder);

        $vesselOwners = $query->paginate(20)->withQueryString();

        // Obtener países para el filtro
        $countries = Country::orderBy('name')->pluck('name', 'id');

        return view('company.vessel-owners.index', compact(
            'vesselOwners',
            'countries'
        ));
    }

    /**
     * Show the form for creating a new vessel owner.
     */
    public function create()
    {
        $countries = Country::orderBy('name')->pluck('name', 'id');
        
        return view('company.vessel-owners.create', compact('countries'));
    }

    /**
     * Store a newly created vessel owner.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'tax_id' => 'required|string|max:15|unique:vessel_owners',
            'legal_name' => 'required|string|max:200',
            'commercial_name' => 'nullable|string|max:200',
            'country_id' => 'required|exists:countries,id',
            'transportista_type' => 'required|in:O,R',
            'email' => 'nullable|email|max:100',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'webservice_authorized' => 'boolean',
            'notes' => 'nullable|string',
        ]);

        DB::beginTransaction();

        try {
            $validated['company_id'] = Auth::user()->company_id;
            $validated['created_by_user_id'] = Auth::id();
            $validated['status'] = 'active';

            $vesselOwner = VesselOwner::create($validated);

            DB::commit();

            return redirect()
                ->route('company.vessel-owners.show', $vesselOwner)
                ->with('success', 'Propietario de embarcación creado exitosamente.');
                
        } catch (\Exception $e) {
            DB::rollBack();
            
            return back()
                ->withInput()
                ->withErrors(['error' => 'Error al crear el propietario: ' . $e->getMessage()]);
        }
    }

    /**
     * Display the specified vessel owner.
     */
    public function show(VesselOwner $vesselOwner)
    {
         $companyId = $this->getUserCompanyId();
        if (is_null($companyId)) {
            abort(403, 'No autorizado: usuario sin empresa asignada.');
        }

        $vesselOwner->load([
            'country',
            'vessels' => function ($query) {
                $query->with('vesselType')->latest();
            },
            'createdByUser',
            'updatedByUser'
        ]);

        // Estadísticas
        $stats = [
            'total_vessels' => $vesselOwner->vessels->count(),
            'active_vessels' => $vesselOwner->vessels->where('status', 'active')->count(),
            'last_activity' => $vesselOwner->last_activity_at,
        ];

        return view('company.vessel-owners.show', compact('vesselOwner', 'stats'));
    }

    /**
     * Show the form for editing the specified vessel owner.
     */
    public function edit(VesselOwner $vesselOwner)
    {
        // Verificar que pertenece a la empresa del usuario
        $companyId = $this->getUserCompanyId();
        if (is_null($companyId)) {
            abort(403, 'No autorizado: usuario sin empresa asignada.');
        }

        $countries = Country::orderBy('name')->pluck('name', 'id');
        
        return view('company.vessel-owners.edit', compact('vesselOwner', 'countries'));
    }

    /**
     * Update the specified vessel owner.
     */
    public function update(Request $request, VesselOwner $vesselOwner)
    {
        // Verificar que pertenece a la empresa del usuario
        if ($vesselOwner->company_id !== Auth::user()->company_id) {
            abort(403, 'No autorizado para actualizar este propietario.');
        }

        $validated = $request->validate([
            'tax_id' => 'required|string|max:15|unique:vessel_owners,tax_id,' . $vesselOwner->id,
            'legal_name' => 'required|string|max:200',
            'commercial_name' => 'nullable|string|max:200',
            'country_id' => 'required|exists:countries,id',
            'transportista_type' => 'required|in:O,R',
            'email' => 'nullable|email|max:100',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'webservice_authorized' => 'boolean',
            'notes' => 'nullable|string',
        ]);

        DB::beginTransaction();

        try {
            $validated['updated_by_user_id'] = Auth::id();
            $validated['last_activity_at'] = Carbon::now();

            $vesselOwner->update($validated);

            DB::commit();

            return redirect()
                ->route('company.vessel-owners.show', $vesselOwner)
                ->with('success', 'Propietario actualizado exitosamente.');
                
        } catch (\Exception $e) {
            DB::rollBack();
            
            return back()
                ->withInput()
                ->withErrors(['error' => 'Error al actualizar el propietario: ' . $e->getMessage()]);
        }
    }

    /**
     * Remove the specified vessel owner.
     */
    public function destroy(VesselOwner $vesselOwner)
    {
        // Verificar que pertenece a la empresa del usuario
        if ($vesselOwner->company_id !== Auth::user()->company_id) {
            abort(403, 'No autorizado para eliminar este propietario.');
        }

        // Verificar si tiene embarcaciones asociadas
        if ($vesselOwner->vessels()->exists()) {
            return back()->withErrors([
                'error' => 'No se puede eliminar un propietario que tiene embarcaciones asociadas.'
            ]);
        }

        try {
            $vesselOwner->delete();

            return redirect()
                ->route('company.vessel-owners.index')
                ->with('success', 'Propietario eliminado exitosamente.');
                
        } catch (\Exception $e) {
            return back()->withErrors([
                'error' => 'Error al eliminar el propietario: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Toggle the status of the vessel owner.
     */
    public function toggleStatus(VesselOwner $vesselOwner)
    {
        // Verificar que pertenece a la empresa del usuario
        if ($vesselOwner->company_id !== Auth::user()->company_id) {
            abort(403, 'No autorizado para cambiar el estado de este propietario.');
        }

        $newStatus = $vesselOwner->status === 'active' ? 'inactive' : 'active';
        
        $vesselOwner->update([
            'status' => $newStatus,
            'updated_by_user_id' => Auth::id(),
            'last_activity_at' => Carbon::now(),
        ]);

        return back()->with('success', 'Estado actualizado exitosamente.');
    }

    /**
     * Show vessels belonging to the vessel owner.
     */
    public function vessels(VesselOwner $vesselOwner)
    {
        // Verificar que pertenece a la empresa del usuario
        if ($vesselOwner->company_id !== Auth::user()->company_id) {
            abort(403, 'No autorizado para ver las embarcaciones de este propietario.');
        }

        $vessels = $vesselOwner->vessels()
            ->with(['vesselType', 'country'])
            ->paginate(20);

        return view('company.vessel-owners.vessels', compact('vesselOwner', 'vessels'));
    }
}