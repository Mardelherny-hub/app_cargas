<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\VesselOwner;
use App\Models\Company;
use App\Models\Country;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class VesselOwnerController extends Controller
{
    /**
     * Display a listing of vessel owners for admin.
     */
    public function index(Request $request)
    {
        $query = VesselOwner::with(['country', 'company', 'vessels']);

        // Filtros
        if ($request->filled('search')) {
            $search = $request->search;
            $query->search($search);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('transportista_type')) {
            $query->byTransportistaType($request->transportista_type);
        }

        if ($request->filled('country_id')) {
            $query->where('country_id', $request->country_id);
        }

        if ($request->filled('company_id')) {
            $query->byCompany($request->company_id);
        }

        if ($request->filled('verified')) {
            if ($request->verified == '1') {
                $query->verified();
            } elseif ($request->verified == '0') {
                $query->whereNull('tax_id_verified_at');
            }
        }

        // Ordenamiento
        $sortBy = $request->get('sort_by', 'legal_name');
        $sortOrder = $request->get('sort_order', 'asc');
        
        // Validar campos de ordenamiento
        $allowedSorts = ['legal_name', 'commercial_name', 'tax_id', 'status', 'created_at', 'last_activity_at'];
        if (!in_array($sortBy, $allowedSorts)) {
            $sortBy = 'legal_name';
        }
        
        $query->orderBy($sortBy, $sortOrder);

        $vesselOwners = $query->paginate(20)->withQueryString();

        // Datos para filtros
        $companies = Company::orderBy('legal_name')->pluck('legal_name', 'id');
        $countries = Country::orderBy('name')->pluck('name', 'id');

        // Estadísticas generales
        $stats = [
            'total' => VesselOwner::count(),
            'active' => VesselOwner::active()->count(),
            'inactive' => VesselOwner::inactive()->count(),
            'suspended' => VesselOwner::where('status', 'suspended')->count(),
            'verified' => VesselOwner::verified()->count(),
            'operators' => VesselOwner::byTransportistaType('O')->count(),
            'representatives' => VesselOwner::byTransportistaType('R')->count(),
        ];

        return view('admin.vessel-owners.index', compact(
            'vesselOwners',
            'companies',
            'countries',
            'stats'
        ));
    }

    /**
     * Show the form for creating a new vessel owner.
     */
    public function create()
    {
        $companies = Company::orderBy('legal_name')->pluck('legal_name', 'id');
        $countries = Country::orderBy('name')->pluck('name', 'id');
        
        return view('admin.vessel-owners.create', compact('companies', 'countries'));
    }

    /**
     * Store a newly created vessel owner.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'company_id' => 'required|exists:companies,id',
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
            'status' => 'required|in:active,inactive,suspended,pending_verification',
            'notes' => 'nullable|string',
        ]);

        DB::beginTransaction();

        try {
            $validated['created_by_user_id'] = Auth::id();
            $validated['webservice_authorized'] = $request->boolean('webservice_authorized');

            $vesselOwner = VesselOwner::create($validated);

            DB::commit();

            return redirect()
                ->route('admin.vessel-owners.show', $vesselOwner)
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
        $vesselOwner->load([
            'company',
            'country',
            'vessels' => function ($query) {
                $query->with('vesselType')->latest();
            },
            'createdByUser',
            'updatedByUser'
        ]);

        // Estadísticas del propietario
        $stats = [
            'total_vessels' => $vesselOwner->vessels->count(),
            'active_vessels' => $vesselOwner->vessels->where('active', true)->count(),
            'inactive_vessels' => $vesselOwner->vessels->where('active', false)->count(),
            'last_activity' => $vesselOwner->last_activity_at,
            'verified_at' => $vesselOwner->tax_id_verified_at,
        ];

        // Actividad reciente (últimos 30 días)
        $recentActivity = $vesselOwner->vessels()
            ->where('updated_at', '>=', Carbon::now()->subDays(30))
            ->with('vesselType')
            ->latest('updated_at')
            ->take(5)
            ->get();

        return view('admin.vessel-owners.show', compact('vesselOwner', 'stats', 'recentActivity'));
    }

    /**
     * Show the form for editing the specified vessel owner.
     */
    public function edit(VesselOwner $vesselOwner)
    {
        $companies = Company::orderBy('legal_name')->pluck('legal_name', 'id');
        $countries = Country::orderBy('name')->pluck('name', 'id');
        
        return view('admin.vessel-owners.edit', compact('vesselOwner', 'companies', 'countries'));
    }

    /**
     * Update the specified vessel owner.
     */
    public function update(Request $request, VesselOwner $vesselOwner)
    {
        $validated = $request->validate([
            'company_id' => 'required|exists:companies,id',
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
            'status' => 'required|in:active,inactive,suspended,pending_verification',
            'notes' => 'nullable|string',
        ]);

        DB::beginTransaction();

        try {
            $validated['updated_by_user_id'] = Auth::id();
            $validated['last_activity_at'] = Carbon::now();
            $validated['webservice_authorized'] = $request->boolean('webservice_authorized');

            $vesselOwner->update($validated);

            DB::commit();

            return redirect()
                ->route('admin.vessel-owners.show', $vesselOwner)
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
        // Verificar si tiene embarcaciones asociadas
        if ($vesselOwner->vessels()->exists()) {
            return back()->withErrors([
                'error' => 'No se puede eliminar un propietario que tiene embarcaciones asociadas.'
            ]);
        }

        try {
            $vesselOwner->delete();

            return redirect()
                ->route('admin.vessel-owners.index')
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
        $newStatus = $vesselOwner->status === 'active' ? 'inactive' : 'active';
        
        $vesselOwner->update([
            'status' => $newStatus,
            'updated_by_user_id' => Auth::id(),
            'last_activity_at' => Carbon::now(),
        ]);

        return back()->with('success', 'Estado actualizado exitosamente.');
    }

    /**
     * Verify the vessel owner's tax ID.
     */
    public function verify(VesselOwner $vesselOwner)
    {
        $vesselOwner->markAsVerified();

        return back()->with('success', 'Propietario verificado exitosamente.');
    }

    /**
     * Transfer vessel owner to another company.
     */
    public function transfer(Request $request, VesselOwner $vesselOwner)
    {
        $validated = $request->validate([
            'new_company_id' => 'required|exists:companies,id|different:' . $vesselOwner->company_id,
            'transfer_vessels' => 'boolean',
            'transfer_reason' => 'nullable|string|max:500',
        ]);

        DB::beginTransaction();

        try {
            $oldCompanyId = $vesselOwner->company_id;
            $newCompanyId = $validated['new_company_id'];

            // Actualizar el propietario
            $vesselOwner->update([
                'company_id' => $newCompanyId,
                'updated_by_user_id' => Auth::id(),
                'last_activity_at' => Carbon::now(),
                'notes' => ($vesselOwner->notes ?? '') . "\n\n[Transferido desde empresa ID:{$oldCompanyId} el " . Carbon::now()->format('Y-m-d H:i') . "] " . ($validated['transfer_reason'] ?? 'Sin motivo especificado'),
            ]);

            // Si se solicita, transferir también las embarcaciones
            if ($request->boolean('transfer_vessels')) {
                $vesselOwner->vessels()->update([
                    'company_id' => $newCompanyId,
                    'last_updated_by_user_id' => Auth::id(),
                ]);
            }

            DB::commit();

            $oldCompany = Company::find($oldCompanyId);
            $newCompany = Company::find($newCompanyId);

            return back()->with('success', "Propietario transferido exitosamente de '{$oldCompany->legal_name}' a '{$newCompany->legal_name}'.");
                
        } catch (\Exception $e) {
            DB::rollBack();
            
            return back()->withErrors([
                'error' => 'Error al transferir el propietario: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Bulk action handler.
     */
    public function bulkAction(Request $request)
    {
        $validated = $request->validate([
            'action' => 'required|in:activate,deactivate,suspend,verify',
            'vessel_owner_ids' => 'required|array|min:1',
            'vessel_owner_ids.*' => 'exists:vessel_owners,id',
        ]);

        $vesselOwnerIds = $validated['vessel_owner_ids'];
        $action = $validated['action'];
        $updated = 0;

        DB::beginTransaction();

        try {
            switch ($action) {
                case 'activate':
                    $updated = VesselOwner::whereIn('id', $vesselOwnerIds)
                        ->update([
                            'status' => 'active',
                            'updated_by_user_id' => Auth::id(),
                            'last_activity_at' => Carbon::now(),
                        ]);
                    break;

                case 'deactivate':
                    $updated = VesselOwner::whereIn('id', $vesselOwnerIds)
                        ->update([
                            'status' => 'inactive',
                            'updated_by_user_id' => Auth::id(),
                            'last_activity_at' => Carbon::now(),
                        ]);
                    break;

                case 'suspend':
                    $updated = VesselOwner::whereIn('id', $vesselOwnerIds)
                        ->update([
                            'status' => 'suspended',
                            'updated_by_user_id' => Auth::id(),
                            'last_activity_at' => Carbon::now(),
                        ]);
                    break;

                case 'verify':
                    $updated = VesselOwner::whereIn('id', $vesselOwnerIds)
                        ->whereNull('tax_id_verified_at')
                        ->update([
                            'tax_id_verified_at' => Carbon::now(),
                            'updated_by_user_id' => Auth::id(),
                            'last_activity_at' => Carbon::now(),
                        ]);
                    break;
            }

            DB::commit();

            return back()->with('success', "{$updated} propietarios actualizados exitosamente.");

        } catch (\Exception $e) {
            DB::rollBack();
            
            return back()->withErrors([
                'error' => 'Error en la acción masiva: ' . $e->getMessage()
            ]);
        }
    }
}