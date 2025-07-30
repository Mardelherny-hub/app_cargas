<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Models\Voyage;
use App\Models\Vessel;
use App\Models\Captain;
use App\Models\Country;
use App\Models\Port;
use App\Models\Container;
use App\Models\Shipment;
use App\Models\ShipmentItem;
use App\Traits\UserHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class VoyageController extends Controller
{
    use UserHelper;

    /**
     * Mostrar lista de viajes.
     */
    public function index(Request $request)
    {
        // 1. Verificar permiso básico para ver cargas (viajes son parte del módulo cargas)
        if (!$this->canPerform('view_cargas')) {
            abort(403, 'No tiene permisos para ver viajes.');
        }

        // 2. Verificar que la empresa tenga rol "Cargas"
        if (!$this->hasCompanyRole('Cargas')) {
            abort(403, 'Su empresa no tiene el rol de Cargas para gestionar viajes.');
        }

        // 3. Obtener empresa del usuario
        $company = $this->getUserCompany();

        if (!$company) {
            return redirect()->route('company.dashboard')
                ->with('error', 'No se encontró la empresa asociada.');
        }

        // 4. Construir consulta base filtrada por empresa
        $query = Voyage::where('company_id', $company->id);

        // 5. Aplicar filtros adicionales según el rol de usuario
        if ($this->isUser() && $this->isOperator()) {
            // Los usuarios operadores solo ven sus propios viajes
            $query->where('created_by_user_id', Auth::id());
        }

        // 6. Aplicar filtros de búsqueda si existen
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('voyage_number', 'LIKE', "%{$search}%")
                  ->orWhere('internal_reference', 'LIKE', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // 7. Ejecutar consulta con relaciones
        $voyages = $query->with([
                'company', 
                'leadVessel', 
                'captain',
                'originPort', 
                'destinationPort',
                'shipments'
            ])
            ->latest('departure_date')
            ->paginate(20);

        // 8. Obtener estadísticas
        $stats = $this->getVoyageStats($company);

        return view('company.voyages.index', compact('voyages', 'stats', 'company'));
    }

/**
 * Mostrar formulario para crear viaje.
 */
 public function create()
    {
        // 1. Verificar permisos
        if (!$this->canPerform('access_trips')) {
            abort(403, 'No tiene permisos para acceder a viajes.');
        }

        if (!$this->hasCompanyRole('Cargas')) {
            abort(403, 'Su empresa no tiene el rol de Cargas.');
        }

        // 2. Obtener empresa del usuario
        $company = $this->getUserCompany();
        if (!$company) {
            return redirect()->route('company.voyages.index')
                ->with('error', 'No se encontró la empresa asociada.');
        }

        // 3. Consultar embarcaciones disponibles de la empresa
        $vessels = Vessel::where('active', true)
            ->where('available_for_charter', true)
            ->where('operational_status', 'active')
            ->where('company_id', $company->id)
            ->with('vesselType:id,name')
            ->select('id', 'name', 'vessel_type_id')
            ->orderBy('name')
            ->get();

        // 4. Consultar capitanes activos y disponibles
        $captains = Captain::where('active', true)
            ->where('available_for_hire', true)
            ->where('license_status', 'valid')
            ->where(function($query) {
                $query->whereNull('license_expires_at')
                      ->orWhere('license_expires_at', '>', now());
            })
            ->select('id', 'full_name', 'license_number')
            ->orderBy('full_name')
            ->get();

        // 5. Consultar países activos para rutas
        $countries = Country::where('active', true)
            ->where(function($query) {
                $query->where('allows_import', true)
                      ->orWhere('allows_export', true);
            })
            ->select('id', 'name', 'iso_code')
            ->orderBy('display_order')
            ->orderBy('name')
            ->get();

        // 6. Consultar todos los puertos activos (agrupados por país para JavaScript)
        $ports = Port::where('active', true)
            ->where('accepts_new_vessels', true)
            ->with('country:id,name')
            ->select('id', 'name', 'code', 'city', 'country_id')
            ->orderBy('country_id')
            ->orderBy('name')
            ->get();

        // Agrupar puertos por país para JavaScript
        $portsByCountry = $ports->groupBy('country_id');

        // 7. Validar datos mínimos requeridos
        if ($vessels->isEmpty()) {
            return redirect()->route('company.voyages.index')
                ->with('error', 'No hay embarcaciones disponibles para crear un viaje. Verifique el estado de sus embarcaciones.');
        }

        if ($countries->isEmpty()) {
            return redirect()->route('company.voyages.index')
                ->with('error', 'No hay países disponibles para crear rutas. Contacte al administrador.');
        }

        // 8. Preparar datos para la vista
        return view('company.voyages.create', [
            'company' => $company,
            'vessels' => $vessels,
            'captains' => $captains,
            'countries' => $countries,
            'ports' => $ports,
            'portsByCountry' => $portsByCountry,
            'canManageAll' => $this->isCompanyAdmin(),
            //'userInfo' => $this->getUserInfo(),
        ]);
    }
    /**
     * Almacenar nuevo viaje.
     */
    public function store(Request $request)
    {
         // 1. Verificar permisos
        if (!$this->canPerform('access_trips')) {
            abort(403, 'No tiene permisos para acceder a viajes.');
        }

        if (!$this->hasCompanyRole('Cargas')) {
            abort(403, 'Su empresa no tiene el rol de Cargas.');
        }

        // 2. Obtener empresa
        $company = $this->getUserCompany();

        if (!$company) {
            return redirect()->route('company.voyages.index')
                ->with('error', 'No se encontró la empresa asociada.');
        }

        // 3. Validar datos (usando campos reales de la migración)
        $request->validate([
            'voyage_number' => 'required|string|max:50|unique:voyages,voyage_number',
            'internal_reference' => 'nullable|string|max:100',
            'lead_vessel_id' => 'required|exists:vessels,id',
            'captain_id' => 'nullable|exists:captains,id',
            'origin_country_id' => 'required|exists:countries,id',
            'origin_port_id' => 'required|exists:ports,id',
            'destination_country_id' => 'required|exists:countries,id',
            'destination_port_id' => 'required|exists:ports,id',
            'departure_date' => 'required|date|after:now',
            'estimated_arrival_date' => 'required|date|after:departure_date',
            'voyage_type' => 'required|in:regular,charter,emergency',
            'cargo_type' => 'required|in:containers,bulk,general,liquid',
        ]);

        // 4. Crear el viaje
        $voyage = Voyage::create([
            'company_id' => $company->id,
            'voyage_number' => $request->voyage_number,
            'internal_reference' => $request->internal_reference,
            'lead_vessel_id' => $request->lead_vessel_id,
            'captain_id' => $request->captain_id,
            'origin_country_id' => $request->origin_country_id,
            'origin_port_id' => $request->origin_port_id,
            'destination_country_id' => $request->destination_country_id,
            'destination_port_id' => $request->destination_port_id,
            'departure_date' => $request->departure_date,
            'estimated_arrival_date' => $request->estimated_arrival_date,
            'voyage_type' => $request->voyage_type,
            'cargo_type' => $request->cargo_type,
            'status' => 'planning',
            'created_by_user_id' => Auth::id(),
            'created_date' => now(),
        ]);

        return redirect()->route('company.voyages.show', $voyage)
            ->with('success', 'Viaje creado exitosamente.');
    }

    /**
     * Mostrar detalles del viaje.
     */
    public function show(Voyage $voyage)
    {
        // 1. Verificar permisos
        if (!$this->canPerform('view_cargas')) {
            abort(403, 'No tiene permisos para ver viajes.');
        }

        if (!$this->hasCompanyRole('Cargas')) {
            abort(403, 'Su empresa no tiene el rol de Cargas.');
        }

        // 2. Verificar que el viaje pertenece a la empresa del usuario
        if (!$this->canAccessCompany($voyage->company_id)) {
            abort(403, 'No tiene permisos para ver este viaje.');
        }

        // 3. Verificar si el usuario puede ver este viaje específico
        if ($this->isUser() && $this->isOperator() && $voyage->created_by_user_id !== Auth::id()) {
            abort(403, 'No tiene permisos para ver este viaje.');
        }

        // 4. Cargar relaciones necesarias
        $voyage->load([
            'company', 
            'leadVessel', 
            'captain',
            'originPort', 
            'destinationPort',
            'transshipmentPort',
            'shipments',
            //'createdByUser'
        ]);

        return view('company.voyages.show', compact('voyage'));
    }

    /**
     * Mostrar formulario para editar viaje.
     */
    public function edit(Voyage $voyage)
    {
        // 1. Verificar permisos
        if (!$this->canPerform('trips_edit')) {
            abort(403, 'No tiene permisos para editar viajes.');
        }

        if (!$this->hasCompanyRole('Cargas')) {
            abort(403, 'Su empresa no tiene el rol de Cargas.');
        }

        // 2. Verificar ownership
        if (!$this->canAccessCompany($voyage->company_id)) {
            abort(403, 'No tiene permisos para editar este viaje.');
        }

        if ($this->isUser() && $this->isOperator() && $voyage->created_by_user_id !== Auth::id()) {
            abort(403, 'No tiene permisos para editar este viaje.');
        }

        // 3. Verificar estado editable
        if (in_array($voyage->status, ['completed', 'cancelled'])) {
            return redirect()->route('company.voyages.show', $voyage)
                ->with('error', 'No se puede editar un viaje completado o cancelado.');
        }

        // 4. Cargar datos necesarios
        $voyage->load(['company', 'leadVessel', 'captain', 'originPort', 'destinationPort']);

        return view('company.voyages.edit', compact('voyage'));
    }

    /**
     * Actualizar viaje.
     */
    public function update(Request $request, Voyage $voyage)
    {
        // 1. Verificar permisos (misma lógica que edit)
        if (!$this->canPerform('trips_edit')) {
            abort(403, 'No tiene permisos para editar viajes.');
        }

        if (!$this->hasCompanyRole('Cargas')) {
            abort(403, 'Su empresa no tiene el rol de Cargas.');
        }

        if (!$this->canAccessCompany($voyage->company_id)) {
            abort(403, 'No tiene permisos para editar este viaje.');
        }

        if ($this->isUser() && $this->isOperator() && $voyage->created_by_user_id !== Auth::id()) {
            abort(403, 'No tiene permisos para editar este viaje.');
        }

        // 2. Verificar estado
        if (in_array($voyage->status, ['completed', 'cancelled'])) {
            return redirect()->route('company.voyages.show', $voyage)
                ->with('error', 'No se puede editar un viaje completado o cancelado.');
        }

        // 3. Validar datos
        $request->validate([
            'voyage_number' => 'required|string|max:50|unique:voyages,voyage_number,' . $voyage->id,
            'internal_reference' => 'nullable|string|max:100',
            'lead_vessel_id' => 'required|exists:vessels,id',
            'captain_id' => 'nullable|exists:captains,id',
            'departure_date' => 'required|date',
            'estimated_arrival_date' => 'required|date|after:departure_date',
        ]);

        // 4. Actualizar
        $voyage->update([
            'voyage_number' => $request->voyage_number,
            'internal_reference' => $request->internal_reference,
            'lead_vessel_id' => $request->lead_vessel_id,
            'captain_id' => $request->captain_id,
            'departure_date' => $request->departure_date,
            'estimated_arrival_date' => $request->estimated_arrival_date,
            'last_updated_by_user_id' => Auth::id(),
            'last_updated_date' => now(),
        ]);

        return redirect()->route('company.voyages.show', $voyage)
            ->with('success', 'Viaje actualizado exitosamente.');
    }

    /**
     * Eliminar viaje.
     */
    public function destroy(Voyage $voyage)
    {
        // 1. Solo company-admin puede eliminar
        if (!$this->isCompanyAdmin()) {
            abort(403, 'No tiene permisos para eliminar viajes.');
        }

        if (!$this->hasCompanyRole('Cargas')) {
            abort(403, 'Su empresa no tiene el rol de Cargas.');
        }

        // 2. Verificar ownership
        if (!$this->canAccessCompany($voyage->company_id)) {
            abort(403, 'No tiene permisos para eliminar este viaje.');
        }

        // 3. Verificar estado eliminable
        if (in_array($voyage->status, ['in_progress', 'completed'])) {
            return redirect()->route('company.voyages.show', $voyage)
                ->with('error', 'No se puede eliminar un viaje en progreso o completado.');
        }

        // 4. Verificar que no tenga shipments
        if ($voyage->shipments()->count() > 0) {
            return redirect()->route('company.voyages.show', $voyage)
                ->with('error', 'No se puede eliminar un viaje que tiene envíos asociados.');
        }

        $voyage->delete();

        return redirect()->route('company.voyages.index')
            ->with('success', 'Viaje eliminado exitosamente.');
    }

    /**
     * Actualizar estado del viaje.
     */
    public function updateStatus(Request $request, Voyage $voyage)
    {
        // 1. Verificar permisos básicos
        if (!$this->canPerform('view_cargas')) {
            abort(403, 'No tiene permisos para modificar viajes.');
        }

        if (!$this->hasCompanyRole('Cargas')) {
            abort(403, 'Su empresa no tiene el rol de Cargas.');
        }

        // 2. Verificar ownership
        if (!$this->canAccessCompany($voyage->company_id)) {
            abort(403, 'No tiene permisos para modificar este viaje.');
        }

        // 3. Solo company-admin puede cambiar ciertos estados
        $restrictedStatuses = ['completed', 'cancelled'];
        if (in_array($request->status, $restrictedStatuses) && !$this->isCompanyAdmin()) {
            abort(403, 'No tiene permisos para cambiar a este estado.');
        }

        // 4. Validar
        $request->validate([
            'status' => 'required|in:planning,loading,in_progress,arrived,completed,cancelled',
            'notes' => 'nullable|string|max:1000',
        ]);

        // 5. Actualizar
        $voyage->update([
            'status' => $request->status,
            'voyage_notes' => $request->notes,
            'last_updated_by_user_id' => Auth::id(),
            'last_updated_date' => now(),
        ]);

        return redirect()->route('company.voyages.show', $voyage)
            ->with('success', 'Estado del viaje actualizado exitosamente.');
    }

    /**
     * Obtener estadísticas de viajes para la empresa.
     */
    private function getVoyageStats($company)
    {
        $baseQuery = Voyage::where('company_id', $company->id);

        // Si es usuario operador, filtrar solo sus viajes
        if ($this->isUser() && $this->isOperator()) {
            $baseQuery->where('created_by_user_id', Auth::id());
        }

        return [
            'total' => $baseQuery->count(),
            'planning' => $baseQuery->where('status', 'planning')->count(),
            'in_progress' => $baseQuery->where('status', 'in_progress')->count(),
            'completed' => $baseQuery->where('status', 'completed')->count(),
            'this_month' => $baseQuery->whereMonth('departure_date', now()->month)->count(),
        ];
    }
}