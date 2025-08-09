<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Models\Vessel;
use App\Models\VesselOwner;
use App\Models\VesselType;
use App\Traits\UserHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Carbon\Carbon;

class VesselController extends Controller
{
    use UserHelper;

    /**
     * Display a listing of vessels.
     */
    public function index(Request $request)
    {
        // Verificar permisos básicos
        if (!$this->canPerform('dashboard_access')) {
            abort(403, 'No tiene permisos para acceder a este módulo.');
        }

        $company = $this->getUserCompany();
        if (!$company) {
            return redirect()->route('company.dashboard')
                ->with('error', 'No se encontró la empresa asociada.');
        }

        // Query base - solo embarcaciones de propietarios de la empresa
        $query = Vessel::whereHas('vesselOwner', function ($q) use ($company) {
            $q->where('company_id', $company->id);
        })->with(['vesselOwner', 'vesselType']);

        // Filtros
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('imo_number', 'like', "%{$search}%")
                  ->orWhereHas('vesselOwner', function ($q2) use ($search) {
                      $q2->where('legal_name', 'like', "%{$search}%");
                  });
            });
        }

        if ($request->filled('vessel_type_id')) {
            $query->where('vessel_type_id', $request->vessel_type_id);
        }

        if ($request->filled('owner_id')) {
            $query->where('owner_id', $request->owner_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Ordenamiento
        $sortBy = $request->get('sort_by', 'name');
        $sortOrder = $request->get('sort_order', 'asc');
        
        $allowedSorts = ['name', 'imo_number', 'length_meters', 'gross_tonnage', 'created_at'];
        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortOrder);
        }

        $vessels = $query->paginate(20)->withQueryString();

        // Datos para filtros
        $vesselTypes = VesselType::active()->orderBy('name')->pluck('name', 'id');
        $vesselOwners = VesselOwner::byCompany($company->id)
            ->active()
            ->orderBy('legal_name')
            ->pluck('legal_name', 'id');

        return view('company.vessels.index', compact(
            'vessels',
            'vesselTypes', 
            'vesselOwners'
        ));
    }

    /**
     * Show the form for creating a new vessel.
     */
    public function create()
    {
        if (!$this->canPerform('dashboard_access')) {
            abort(403, 'No tiene permisos para crear embarcaciones.');
        }

        $company = $this->getUserCompany();
        if (!$company) {
            return redirect()->route('company.vessels.index')
                ->with('error', 'No se encontró la empresa asociada.');
        }

        // Solo propietarios de la empresa del usuario
        $vesselOwners = VesselOwner::byCompany($company->id)
            ->active()
            ->orderBy('legal_name')
            ->pluck('legal_name', 'id');

        if ($vesselOwners->isEmpty()) {
            return redirect()->route('company.vessels.index')
                ->with('error', 'Debe crear al menos un propietario de embarcaciones antes de registrar una embarcación.');
        }

        $vesselTypes = VesselType::active()->orderBy('name')->pluck('name', 'id');

        return view('company.vessels.create', compact('vesselOwners', 'vesselTypes'));
    }

/**
 * Store a newly created vessel.
 */
public function store(Request $request)
{
    if (!$this->canPerform('dashboard_access')) {
        abort(403, 'No tiene permisos para crear embarcaciones.');
    }

    $company = $this->getUserCompany();
    if (!$company) {
        return back()->withInput()
            ->withErrors(['error' => 'No se encontró la empresa asociada.']);
    }

    $validated = $request->validate([
        'name' => 'required|string|max:100',
        'registration_number' => 'required|string|max:50|unique:vessels,registration_number',
        'imo_number' => 'nullable|string|max:20|unique:vessels,imo_number',
        'vessel_type_id' => 'required|exists:vessel_types,id',
        'vessel_owner_id' => 'required|exists:vessel_owners,id',
        'flag_country_id' => 'required|exists:countries,id',
        // DIMENSIONES OBLIGATORIAS
        'length_meters' => 'required|numeric|min:0|max:999.99',
        'beam_meters' => 'required|numeric|min:0|max:100',
        'draft_meters' => 'required|numeric|min:0|max:50',
        'depth_meters' => 'nullable|numeric|min:0|max:50',
        'cargo_capacity_tons' => 'required|numeric|min:0|max:99999.99', // ← OBLIGATORIO FALTANTE
        // OPCIONALES
        'gross_tonnage' => 'nullable|numeric|min:0|max:999999.99',
        'container_capacity' => 'nullable|integer|min:0|max:99999',
        'status' => 'required|in:active,inactive,maintenance,dry_dock,under_repair,decommissioned'
    ]);

    // Verificar que el propietario pertenece a la empresa del usuario
    $owner = VesselOwner::find($validated['vessel_owner_id']);
    if (!$owner || $owner->company_id !== $company->id) {
        return back()->withInput()
            ->withErrors(['vessel_owner_id' => 'El propietario seleccionado no pertenece a su empresa.']);
    }

    DB::beginTransaction();

    try {
        $vesselData = [
            // IDENTIFICACIÓN
            'name' => $validated['name'],
            'registration_number' => $validated['registration_number'],
            'imo_number' => $validated['imo_number'],
            
            // RELACIONES
            'vessel_type_id' => $validated['vessel_type_id'],
            'owner_id' => $validated['vessel_owner_id'],
            'flag_country_id' => $validated['flag_country_id'],
            'company_id' => $company->id,
            
            // DIMENSIONES OBLIGATORIAS
            'length_meters' => $validated['length_meters'],
            'beam_meters' => $validated['beam_meters'],
            'draft_meters' => $validated['draft_meters'],
            'depth_meters' => $validated['depth_meters'],
            'cargo_capacity_tons' => $validated['cargo_capacity_tons'], // ← AGREGAR
            
            // OPCIONALES
            'gross_tonnage' => $validated['gross_tonnage'],
            'container_capacity' => $validated['container_capacity'],
            'operational_status' => $validated['status'],
            
            // AUDITORÍA
            'created_by_user_id' => Auth::id(),
            'last_updated_by_user_id' => Auth::id(),
        ];

        $vessel = Vessel::create($vesselData);

        DB::commit();

        return redirect()->route('company.vessels.show', $vessel)
            ->with('success', 'Embarcación creada exitosamente.');

    } catch (\Exception $e) {
        DB::rollBack();
        
        return back()->withInput()
            ->withErrors(['error' => 'Error al crear la embarcación: ' . $e->getMessage()]);
    }
}

    /**
     * Display the specified vessel.
     */
    public function show(Vessel $vessel)
    {
        // Verificar que la embarcación pertenece a un propietario de la empresa del usuario
        $company = $this->getUserCompany();
        if (!$company || !$vessel->vesselOwner || $vessel->vesselOwner->company_id !== $company->id) {
            abort(403, 'No tiene permisos para ver esta embarcación.');
        }

        $vessel->load([
            'vesselOwner',
            'vesselType', 
            'createdByUser',
            'updatedByUser'
        ]);

        // Estadísticas básicas de la embarcación
        $stats = [
            'age_years' => $vessel->created_at ? Carbon::now()->diffInYears($vessel->created_at) : 0,
            'last_updated' => $vessel->updated_at,
            'status' => $vessel->status,
            'has_imo' => !empty($vessel->imo_number),
        ];

        return view('company.vessels.show', compact('vessel', 'stats'));
    }

    /**
     * Show the form for editing the specified vessel.
     */
    public function edit(Vessel $vessel)
    {
        // Verificar que la embarcación pertenece a un propietario de la empresa del usuario
        $company = $this->getUserCompany();
        if (!$company || !$vessel->vesselOwner || $vessel->vesselOwner->company_id !== $company->id) {
            abort(403, 'No tiene permisos para editar esta embarcación.');
        }

        // Solo propietarios de la empresa del usuario
        $vesselOwners = VesselOwner::byCompany($company->id)
            ->active()
            ->orderBy('legal_name')
            ->pluck('legal_name', 'id');

        $vesselTypes = VesselType::active()->orderBy('name')->pluck('name', 'id');

        return view('company.vessels.edit', compact('vessel', 'vesselOwners', 'vesselTypes'));
    }

    /**
     * Update the specified vessel.
     */
    public function update(Request $request, Vessel $vessel)
    {
        // Verificar que la embarcación pertenece a un propietario de la empresa del usuario
        $company = $this->getUserCompany();
        if (!$company || !$vessel->vesselOwner || $vessel->vesselOwner->company_id !== $company->id) {
            abort(403, 'No tiene permisos para editar esta embarcación.');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'imo_number' => [
                'nullable',
                'string',
                'max:20',
                Rule::unique('vessels', 'imo_number')->ignore($vessel->id)
            ],
            'vessel_type_id' => 'required|exists:vessel_types,id',
            'owner_id' => [
                'required',
                'exists:vessel_owners,id',
                function ($attribute, $value, $fail) use ($company) {
                    $owner = VesselOwner::find($value);
                    if (!$owner || $owner->company_id !== $company->id) {
                        $fail('El propietario seleccionado no pertenece a su empresa.');
                    }
                }
            ],
            'length_meters' => 'nullable|numeric|min:0|max:999.99',
            'gross_tonnage' => 'nullable|numeric|min:0|max:999999.99',
            'container_capacity' => 'nullable|integer|min:0|max:99999',
            'status' => 'required|in:active,inactive,maintenance,dry_dock'
        ]);

        DB::beginTransaction();

        try {
            $validated['last_updated_by_user_id'] = Auth::id();
            
            $vessel->update($validated);

            DB::commit();

            return redirect()->route('company.vessels.show', $vessel)
                ->with('success', 'Embarcación actualizada exitosamente.');

        } catch (\Exception $e) {
            DB::rollBack();
            
            return back()->withInput()
                ->withErrors(['error' => 'Error al actualizar la embarcación: ' . $e->getMessage()]);
        }
    }

    /**
     * Remove the specified vessel.
     */
    public function destroy(Vessel $vessel)
    {
        // Verificar que la embarcación pertenece a un propietario de la empresa del usuario
        $company = $this->getUserCompany();
        if (!$company || !$vessel->vesselOwner || $vessel->vesselOwner->company_id !== $company->id) {
            abort(403, 'No tiene permisos para eliminar esta embarcación.');
        }

        // Verificar si la embarcación tiene registros relacionados
        // TODO: Agregar verificaciones cuando existan otros modelos relacionados
        // if ($vessel->voyages()->exists()) {
        //     return back()->withErrors([
        //         'error' => 'No se puede eliminar una embarcación que tiene viajes asociados.'
        //     ]);
        // }

        try {
            $vessel->delete();

            return redirect()->route('company.vessels.index')
                ->with('success', 'Embarcación eliminada exitosamente.');
                
        } catch (\Exception $e) {
            return back()->withErrors([
                'error' => 'Error al eliminar la embarcación: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Toggle the status of the vessel.
     */
    public function toggleStatus(Vessel $vessel)
    {
        // Verificar que la embarcación pertenece a un propietario de la empresa del usuario
        $company = $this->getUserCompany();
        if (!$company || !$vessel->vesselOwner || $vessel->vesselOwner->company_id !== $company->id) {
            abort(403, 'No tiene permisos para cambiar el estado de esta embarcación.');
        }

        $newStatus = $vessel->status === 'active' ? 'inactive' : 'active';
        
        $vessel->update([
            'status' => $newStatus,
            'last_updated_by_user_id' => Auth::id(),
        ]);

        return back()->with('success', 'Estado de la embarcación actualizado exitosamente.');
    }
}