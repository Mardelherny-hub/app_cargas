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
use Illuminate\Support\Facades\Log;

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
            //dd($company);
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
 * Almacenar nuevo viaje - MÉTODO CORREGIDO
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

    // 3. Mapeo de valores del formulario a ENUM de BD
    $voyageTypeMapping = [
        'regular' => 'single_vessel',
        'charter' => 'convoy',
        'emergency' => 'fleet'
    ];

    $cargoTypeMapping = [
        'containers' => 'export',
        'bulk' => 'import', 
        'general' => 'transit',
        'liquid' => 'transshipment'
    ];

    // 4. Validar datos con valores ENUM correctos
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
    ], [
        // Mensajes personalizados
        'voyage_number.unique' => 'Ya existe un viaje con este número.',
        'lead_vessel_id.exists' => 'La embarcación seleccionada no existe.',
        'captain_id.exists' => 'El capitán seleccionado no existe.',
        'origin_port_id.exists' => 'El puerto de origen seleccionado no existe.',
        'destination_port_id.exists' => 'El puerto de destino seleccionado no existe.',
        'departure_date.after' => 'La fecha de salida debe ser posterior a la fecha actual.',
        'estimated_arrival_date.after' => 'La fecha de llegada debe ser posterior a la fecha de salida.',
    ]);

    // 5. Validaciones de ownership y coherencia
    try {
        DB::beginTransaction();

        // Verificar que la embarcación pertenece a la empresa
        $vessel = Vessel::where('id', $request->lead_vessel_id)
                       ->where('company_id', $company->id)
                       ->where('active', true)
                       ->first();

        if (!$vessel) {
            throw new \Exception('La embarcación seleccionada no pertenece a su empresa o no está disponible.');
        }

        // Verificar capitán disponible (si se seleccionó)
        if ($request->captain_id) {
            $captain = Captain::where('id', $request->captain_id)
                             ->where('active', true)
                             ->where('available_for_hire', true)
                             ->where('license_status', 'valid')
                             ->first();

            if (!$captain) {
                throw new \Exception('El capitán seleccionado no está disponible o no tiene licencia válida.');
            }
        }

        // Verificar coherencia puerto-país (origen)
        $originPort = Port::where('id', $request->origin_port_id)
                         ->where('country_id', $request->origin_country_id)
                         ->where('active', true)
                         ->first();

        if (!$originPort) {
            throw new \Exception('El puerto de origen no corresponde al país seleccionado.');
        }

        // Verificar coherencia puerto-país (destino)
        $destinationPort = Port::where('id', $request->destination_port_id)
                              ->where('country_id', $request->destination_country_id)
                              ->where('active', true)
                              ->first();

        if (!$destinationPort) {
            throw new \Exception('El puerto de destino no corresponde al país seleccionado.');
        }

        // Verificar que origen y destino sean diferentes
        if ($request->origin_port_id == $request->destination_port_id) {
            throw new \Exception('El puerto de origen y destino no pueden ser el mismo.');
        }

        // 6. Crear el viaje con valores correctos
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
            // Mapear valores del formulario a ENUM de BD
            'voyage_type' => $voyageTypeMapping[$request->voyage_type],
            'cargo_type' => $cargoTypeMapping[$request->cargo_type],
            'status' => 'planning',
            'created_by_user_id' => Auth::id(),
            // REMOVIDO 'created_date' - Laravel maneja created_at automáticamente
        ]);

        DB::commit();

        return redirect()->route('company.voyages.show', $voyage)
            ->with('success', 'Viaje creado exitosamente.');

    } catch (\Exception $e) {
        DB::rollback();
        
        return redirect()->back()
            ->withInput()
            ->with('error', 'Error al crear el viaje: ' . $e->getMessage());
    }
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
        // 1. Verificar permisos - CORREGIDO
        if (!Auth::user()->can('voyages.edit')) {
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

        // 4. Obtener datos del formulario - AGREGADO
        $formData = $this->getFormData();

        // 5. Determinar permisos del usuario para la vista - AGREGADO
        $userPermissions = [
            'can_edit' => $this->isCompanyAdmin() || (Auth::user()->can('voyages.edit') && $this->hasCompanyRole('Cargas')),
            'can_delete' => $this->isCompanyAdmin() && $this->hasCompanyRole('Cargas'),
        ];

        // 6. Cargar datos necesarios del voyage - RELACIONES EXISTENTES
        $voyage->load([
            'company', 
            'vessel' => function($query) {
                $query->select('id', 'name', 'imo_number', 'max_cargo_capacity');
            }, 
            'captain' => function($query) {
                $query->select('id', 'full_name', 'license_number');
            }, 
            'originPort' => function($query) {
                $query->select('id', 'name', 'code');
            }, 
            'destinationPort' => function($query) {
                $query->select('id', 'name', 'code');
            },
            'originCountry' => function($query) {
                $query->select('id', 'name', 'iso_code');
            },
            'destinationCountry' => function($query) {
                $query->select('id', 'name', 'iso_code');
            }
        ]);

        // 7. Si hay operator_id, cargar el operador manualmente
        if ($voyage->operator_id) {
            $voyage->operator = \App\Models\Operator::select('id', 'first_name', 'last_name', 'position')
                ->find($voyage->operator_id);
            if ($voyage->operator) {
                $voyage->operator->full_name = trim($voyage->operator->first_name . ' ' . $voyage->operator->last_name);
            }
        }

        // 7. Pasar todas las variables necesarias a la vista - CORREGIDO
        return view('company.voyages.edit', compact('voyage', 'formData', 'userPermissions'));
    }

    /**
     * CORRECCIÓN DEFINITIVA - Método update()
     */
    public function update(Request $request, Voyage $voyage)
    {
        // 1. Verificar permisos
        if (!Auth::user()->can('voyages.edit')) {
            abort(403, 'No tiene permisos para editar viajes.');
        }

        if (!$this->hasCompanyRole('Cargas')) {
            abort(403, 'Su empresa no tiene el rol de Cargas.');
        }

        if (!$this->canAccessCompany($voyage->company_id)) {
            abort(403, 'No tiene permisos para editar este viaje.');
        }

        // 2. Validar datos - EXACTOS según ENUM de BD
        // Validación dinámica según el estado del viaje
        $rules = [
            'voyage_number' => 'required|string|max:50',
            'internal_reference' => 'nullable|string|max:100',
            'status' => 'required|in:draft,planning,loading,in_progress,arrived,completed,cancelled',
            'vessel_id' => 'required|exists:vessels,id',
            'captain_id' => 'required|exists:captains,id',
            'origin_country_id' => 'required|exists:countries,id',
            'origin_port_id' => 'required|exists:ports,id',
            'destination_country_id' => 'required|exists:countries,id',
            'destination_port_id' => 'required|exists:ports,id|different:origin_port_id',
            'estimated_duration_hours' => 'nullable|numeric|min:0.5|max:720',
        ];

        // Las fechas solo son obligatorias para estados avanzados
        if (in_array($request->status, ['loading', 'in_progress', 'arrived', 'completed'])) {
            $rules['planned_departure_date'] = 'required|date';
            $rules['planned_arrival_date'] = 'required|date|after:planned_departure_date';
        } else {
            $rules['planned_departure_date'] = 'nullable|date';
            $rules['planned_arrival_date'] = 'nullable|date|after:planned_departure_date';
        }

        $request->validate($rules);

        try {
            DB::beginTransaction();

            // 3. Actualizar con campos EXACTOS de la migración
            $voyage->update([
                'voyage_number' => $request->voyage_number,
                'internal_reference' => $request->internal_reference,
                'status' => $request->status,
                'lead_vessel_id' => $request->vessel_id,
                'captain_id' => $request->captain_id,
                'origin_country_id' => $request->origin_country_id,
                'origin_port_id' => $request->origin_port_id,
                'destination_country_id' => $request->destination_country_id,
                'destination_port_id' => $request->destination_port_id,
                'departure_date' => $request->planned_departure_date,
                'estimated_arrival_date' => $request->planned_arrival_date,
                'last_updated_by_user_id' => Auth::id(),
                'last_updated_date' => now(),
            ]);

            DB::commit();

            return redirect()->route('company.voyages.show', $voyage)
                ->with('success', 'Viaje actualizado exitosamente.');

        } catch (\Exception $e) {
            DB::rollback();
            return redirect()->back()
                ->withInput()
                ->with('error', 'Error al actualizar el viaje: ' . $e->getMessage());
        }
    }

    /**
 * CORRECCIÓN DEFINITIVA - getFormData() optimizado
 * Aplicando EXACTAMENTE la misma solución que en BillOfLading
 */
private function getFormData()
{
    // LIMITAR TAMAÑOS INICIALES para colecciones pequeñas
    $LIMIT = 10;
    
    $company = $this->getUserCompany();

    // --- EMBARCACIONES (activas de la compañía) - LIMITADAS
    $vessels = \App\Models\Vessel::where('company_id', $company->id)
        ->where('active', true)
        ->select('id', 'name', 'imo_number', 'cargo_capacity_tons')
        ->orderBy('name')
        ->limit($LIMIT)
        ->get();
    
    // --- CAPITANES (activos) - AMPLIADA: empresa + freelance + disponibles
    $captains = \App\Models\Captain::where('active', true)
        ->where('available_for_hire', true)
        ->where(function($query) use ($company) {
            $query->where('primary_company_id', $company->id)  // De la empresa
                ->orWhereNull('primary_company_id')           // Freelance
                ->orWhere('employment_status', 'freelance');  // Disponibles
        })
        ->select('id', 'full_name', 'license_number', 'employment_status')
        ->orderBy('full_name')
        ->limit($LIMIT)
        ->get();
    
    // --- OPERADORES (activos de la compañía) - LIMITADOS
    $operators = \App\Models\Operator::where('company_id', $company->id)
        ->where('active', true)
        ->selectRaw('id, CONCAT(first_name, " ", last_name) as full_name, position')
        ->orderBy('first_name')
        ->limit($LIMIT)
        ->get();
    
    // --- PAÍSES (activos) - Solo Argentina y Paraguay como en BL
    $argentina = \App\Models\Country::where('alpha2_code', 'AR')->first();
    $paraguay = \App\Models\Country::where('alpha2_code', 'PY')->first();

    $countryIds = collect([$argentina?->id, $paraguay?->id])->filter()->values();

    $countries = \App\Models\Country::where('active', true)
        ->whereIn('id', $countryIds)
        ->select('id', 'name', 'alpha2_code')
        ->orderBy('name')
        ->get();
    
    // --- PUERTOS (activos) - TODOS los de Argentina y Paraguay (como en BL)
    $ports = \App\Models\Port::where('active', true)
        ->whereIn('country_id', $countryIds)
        ->select('id', 'name', 'code', 'city', 'country_id')
        ->orderBy('name')
        ->get();

    return [
        'vessels' => $vessels,
        'captains' => $captains,
        'operators' => $operators,
        'countries' => $countries,
        'ports' => $ports,
    ];
}

    /**
     * Eliminar viaje - VERSIÓN CORREGIDA
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

        // 3. CORREGIDO: Usar el método canBeDeleted() del modelo (más completo)
        if (!$voyage->canBeDeleted()) {
            return redirect()->route('company.voyages.show', $voyage)
                ->with('error', 'No se puede eliminar este viaje. Verifique que esté en estado eliminable y no tenga cargas, conocimientos de embarque o documentación asociada.');
        }

        try {
            // 4. AGREGADO: Usar transacción para seguridad
            DB::beginTransaction();

            $voyageNumber = $voyage->voyage_number;
            
            // 5. CORREGIDO: Verificación adicional de seguridad antes de eliminar
            if ($voyage->shipments()->count() > 0) {
                DB::rollBack();
                return redirect()->route('company.voyages.show', $voyage)
                    ->with('error', 'No se puede eliminar un viaje que tiene envíos asociados.');
            }

            if ($voyage->billsOfLading()->count() > 0) {
                DB::rollBack();
                return redirect()->route('company.voyages.show', $voyage)
                    ->with('error', 'No se puede eliminar un viaje que tiene conocimientos de embarque asociados.');
            }

            // 6. Eliminar el viaje
            $voyage->delete();

            DB::commit();

            // 7. AGREGADO: Log para auditoría
            Log::info('Viaje eliminado', [
                'voyage_id' => $voyage->id,
                'voyage_number' => $voyageNumber,
                'company_id' => $voyage->company_id,
                'user_id' => Auth::id()
            ]);

            return redirect()->route('company.voyages.index')
                ->with('success', "Viaje {$voyageNumber} eliminado exitosamente.");

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Error al eliminar viaje', [
                'voyage_id' => $voyage->id,
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            return redirect()->back()
                ->with('error', 'Error al eliminar el viaje: ' . $e->getMessage());
        }
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
 * Validar viaje para webservices aduaneros
 */
    public function validateForCustoms(Request $request, Voyage $voyage)
    {
        try {
            $request->validate([
                'webservice_type' => 'required|string',
                'country' => 'required|string|in:AR,PY'
            ]);

            $validator = new \App\Actions\Customs\ValidateVoyageForCustoms();
            $result = $validator->validate(
                $voyage, 
                $request->webservice_type, 
                $request->country, 
                ['user' => auth()->user()]
            );

            return response()->json([
                'success' => true,
                'validation_result' => $result,
                'summary' => $validator->getValidationSummary(),
                'grouped_errors' => $validator->getGroupedErrors()
            ]);

        } catch (\Exception $e) {
            \Log::error('Error en validateForCustoms:', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'voyage_id' => $voyage->id
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error interno: ' . $e->getMessage()
            ], 500);
        }
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

    /**
     * Mostrar gestión de contenedores del viaje.
     */
    public function containers(Voyage $voyage)
    {
        // 1. Verificar permisos básicos
        if (!$this->canPerform('view_cargas')) {
            abort(403, 'No tiene permisos para gestionar contenedores.');
        }

        if (!$this->hasCompanyRole('Cargas')) {
            abort(403, 'Su empresa no tiene el rol de Cargas.');
        }

        // 2. Verificar ownership del viaje
        if (!$this->canAccessCompany($voyage->company_id)) {
            abort(403, 'No tiene permisos para gestionar contenedores de este viaje.');
        }

        // 3. Verificar si el usuario puede gestionar este viaje específico
        if ($this->isUser() && $this->isOperator() && $voyage->created_by_user_id !== Auth::id()) {
            abort(403, 'No tiene permisos para gestionar contenedores de este viaje.');
        }

        // 4. Verificar que el viaje esté en estado editable
        if (!in_array($voyage->status, ['planning', 'loading'])) {
            return redirect()->route('company.voyages.show', $voyage)
                ->with('error', 'Solo se pueden gestionar contenedores en viajes en estado de planificación o carga.');
        }

        // 5. Cargar datos necesarios para la vista
        $voyage->load([
            'shipments.vessel',
            'shipments.captain',
            'company',
            'leadVessel',
            'originPort',
            'destinationPort'
        ]);

        // 6. Obtener contenedores existentes del viaje usando la relación existente
        $existingContainers = $voyage->getAllContainers()->get();

        // 7. Obtener estadísticas de contenedores por shipment
        $shipmentStats = $voyage->shipments->map(function ($shipment) {
            return [
                'id' => $shipment->id,
                'shipment_number' => $shipment->shipment_number,
                'vessel_name' => $shipment->vessel->name ?? 'Sin embarcación',
                'container_capacity' => $shipment->container_capacity,
                'containers_loaded' => $shipment->containers_loaded,
                'available_capacity' => $shipment->container_capacity - $shipment->containers_loaded,
            ];
        });

        // 8. Preparar datos para la vista
        return view('company.voyages.containers', compact(
            'voyage',
            'existingContainers', 
            'shipmentStats'
        ));
    }

    /**
     * Mostrar el manifiesto del viaje.
     */
    public function manifest(Voyage $voyage)
    {
        // 1. Verificar permisos básicos
        if (!$this->canPerform('view_reports') && !$this->hasCompanyRole('Cargas')) {
            abort(403, 'No tiene permisos para ver manifiestos.');
        }

        // 2. Verificar que el viaje pertenece a la empresa del usuario
        if (!$this->canAccessCompany($voyage->company_id)) {
            abort(403, 'No tiene permisos para ver este manifiesto.');
        }

        // 3. Verificar si el usuario puede ver este viaje específico
        if ($this->isUser() && $this->isOperator() && $voyage->created_by_user_id !== Auth::id()) {
            abort(403, 'No tiene permisos para ver este manifiesto.');
        }

        // 4. Cargar relaciones necesarias para el manifiesto
        $voyage->load([
            'shipments.billsOfLading.shipper',
            'shipments.billsOfLading.consignee', 
            'shipments.vessel',
            'shipments.captain',
            'originPort.country',
            'destinationPort.country',
            'company'
        ]);

        return view('company.voyages.manifest', compact('voyage'));
    }

    /**
     * Generar PDF del manifiesto del viaje.
     */
    public function manifestPdf(Voyage $voyage)
    {
        // 1. Verificar permisos básicos
        if (!$this->canPerform('view_reports') && !$this->hasCompanyRole('Cargas')) {
            abort(403, 'No tiene permisos para generar manifiestos.');
        }

        // 2. Verificar que el viaje pertenece a la empresa del usuario
        if (!$this->canAccessCompany($voyage->company_id)) {
            abort(403, 'No tiene permisos para generar este manifiesto.');
        }

        // 3. Verificar si el usuario puede ver este viaje específico
        if ($this->isUser() && $this->isOperator() && $voyage->created_by_user_id !== Auth::id()) {
            abort(403, 'No tiene permisos para generar este manifiesto.');
        }

        // 4. Cargar relaciones necesarias para el manifiesto
        $voyage->load([
            'shipments.billsOfLading.shipper',
            'shipments.billsOfLading.consignee',
            'shipments.vessel',
            'shipments.captain',
            'originPort.country',
            'destinationPort.country',
            'company'
        ]);

        // 5. Generar PDF (usando la vista del manifiesto)
        $pdf = \PDF::loadView('company.voyages.manifest-pdf', compact('voyage'));
        
        $filename = "manifest-{$voyage->voyage_number}-" . date('Y-m-d') . ".pdf";
        
        return $pdf->download($filename);
    }

    /**
     * Mostrar vista detallada completa del viaje - "Cockpit" del viaje
     * Incluye: Resumen ejecutivo + Shipments + Bills of Lading + Items + Contenedores
     * 
     * BASADO EN ESTRUCTURA VERIFICADA:
     * Voyage → Shipments → Bills of Lading → Shipment Items → Containers
     */
    public function showDetail($id)
    {
        try {
            // 1. Cargar viaje con todas las relaciones verificadas
            $voyage = Voyage::with([
                // Relaciones básicas del viaje
                'company',
                'leadVessel',
                'captain', 
                'originCountry',
                'destinationCountry',
                'originPort',
                'destinationPort',
                'transshipmentPort',
                
                // Jerarquía completa de datos
                'shipments' => function($query) {
                    $query->orderBy('sequence_in_voyage');
                },
                'shipments.vessel',
                'shipments.captain',
                'shipments.billsOfLading' => function($query) {
                    $query->orderBy('bill_number');
                },
                'shipments.billsOfLading.shipper',
                'shipments.billsOfLading.consignee',
                'shipments.billsOfLading.notifyParty',
                'shipments.billsOfLading.shipmentItems.cargoType',
                'shipments.billsOfLading.shipmentItems.packagingType',
                'shipments.billsOfLading.shipmentItems.containers'
            ])->findOrFail($id);

            // 2. Verificar permisos (usar métodos existentes del controlador)
            if (!$this->canAccessCompany($voyage->company_id)) {
                abort(403, 'No tiene permisos para ver este viaje.');
            }

            // 3. Calcular estadísticas del viaje usando relaciones verificadas
            $stats = $this->calculateVoyageStats($voyage);
            
            // 4. Preparar datos de completitud para cada shipment
            $shipmentData = $this->prepareShipmentData($voyage->shipments);
            
            // 5. Calcular estado general del viaje
            $voyageStatus = $this->calculateVoyageStatus($voyage, $stats);

            return view('company.voyages.detail', compact(
                'voyage',
                'stats', 
                'shipmentData',
                'voyageStatus'
            ));

        } catch (\Exception $e) {
            return back()->with('error', 'Error cargando detalles del viaje: ' . $e->getMessage());
        }
    }

    /**
     * Calcular estadísticas del viaje usando relaciones verificadas
     */
    private function calculateVoyageStats(Voyage $voyage): array
    {
        $totalShipments = $voyage->shipments->count();
        $totalBillsOfLading = $voyage->shipments->sum(function($shipment) {
            return $shipment->billsOfLading->count();
        });
        
        $totalItems = 0;
        $totalWeight = 0;
        $totalContainers = 0;
        $uniqueContainers = collect();
        
        foreach ($voyage->shipments as $shipment) {
            foreach ($shipment->billsOfLading as $bl) {
                $totalItems += $bl->shipmentItems->count();
                $totalWeight += $bl->shipmentItems->sum('gross_weight_kg');
                
                // Contar contenedores únicos
                foreach ($bl->shipmentItems as $item) {
                    foreach ($item->containers as $container) {
                        $uniqueContainers->push($container->id);
                    }
                }
            }
        }
        
        $totalContainers = $uniqueContainers->unique()->count();
        
        return [
            'total_shipments' => $totalShipments,
            'total_bills_of_lading' => $totalBillsOfLading,
            'total_items' => $totalItems,
            'total_weight_kg' => $totalWeight,
            'total_containers' => $totalContainers,
            'capacity_utilization' => $voyage->total_cargo_capacity_tons > 0 
                ? round(($totalWeight / 1000) / $voyage->total_cargo_capacity_tons * 100, 2)
                : 0,
            'container_utilization' => $voyage->total_container_capacity > 0
                ? round($totalContainers / $voyage->total_container_capacity * 100, 2) 
                : 0
        ];
    }

    /**
     * Preparar datos de completitud para cada shipment
     */
    private function prepareShipmentData($shipments): array
    {
        $shipmentData = [];
        
        foreach ($shipments as $shipment) {
            $billsCount = $shipment->billsOfLading->count();
            $verifiedBills = $shipment->billsOfLading->whereNotNull('verified_at')->count();
            $itemsCount = $shipment->billsOfLading->sum(function($bl) {
                return $bl->shipmentItems->count();
            });
            
            $shipmentData[] = [
                'shipment' => $shipment,
                'bills_count' => $billsCount,
                'verified_bills' => $verifiedBills,
                'items_count' => $itemsCount,
                'completion_percentage' => $billsCount > 0 ? round($verifiedBills / $billsCount * 100) : 0,
                'has_items' => $itemsCount > 0,
                'ready_for_customs' => $billsCount > 0 && $verifiedBills === $billsCount && $itemsCount > 0
            ];
        }
        
        return $shipmentData;
    }

    /**
     * Calcular estado general del viaje
     */
    private function calculateVoyageStatus(Voyage $voyage, array $stats): array
    {
        $readyShipments = collect($this->prepareShipmentData($voyage->shipments))
            ->where('ready_for_customs', true)
            ->count();
        
        $totalShipments = $voyage->shipments->count();
        $completionPercentage = $totalShipments > 0 ? round($readyShipments / $totalShipments * 100) : 0;
        
        // Determinar estado general
        $overallStatus = 'draft';
        if ($completionPercentage === 100) {
            $overallStatus = 'ready';
        } elseif ($completionPercentage > 0) {
            $overallStatus = 'in_progress';
        }
        
        return [
            'overall_status' => $overallStatus,
            'completion_percentage' => $completionPercentage,
            'ready_shipments' => $readyShipments,
            'total_shipments' => $totalShipments,
            'can_send_to_customs' => $completionPercentage === 100 && $stats['total_bills_of_lading'] > 0
        ];
    }

    /**
 * Generar PDF general del viaje (diferente al manifest PDF)
 * Este PDF muestra información completa del viaje para impresión/archivo
 */
public function generatePdf(Voyage $voyage)
{
    // 1. Verificar permisos básicos
    if (!$this->canPerform('view_reports') && !$this->hasCompanyRole('Cargas')) {
        abort(403, 'No tiene permisos para generar PDFs.');
    }

    // 2. Verificar que el viaje pertenece a la empresa del usuario
    if (!$this->canAccessCompany($voyage->company_id)) {
        abort(403, 'No tiene permisos para generar este PDF.');
    }

    // 3. Verificar si el usuario puede ver este viaje específico
    if ($this->isUser() && $this->isOperator() && $voyage->created_by_user_id !== Auth::id()) {
        abort(403, 'No tiene permisos para generar este PDF.');
    }

    // 4. Cargar relaciones necesarias para el PDF completo
    $voyage->load([
        'company',
        'leadVessel',
        'captain',
        'originCountry',
        'destinationCountry', 
        'originPort',
        'destinationPort',
        'transshipmentPort',
        'shipments' => function($query) {
            $query->orderBy('sequence_in_voyage');
        },
        'shipments.vessel',
        'shipments.captain',
        'shipments.billsOfLading' => function($query) {
            $query->orderBy('bill_number');
        },
        'shipments.billsOfLading.shipper',
        'shipments.billsOfLading.consignee',
        'shipments.billsOfLading.notifyParty',
        'shipments.billsOfLading.shipmentItems.cargoType',
        'shipments.billsOfLading.shipmentItems.packagingType',
        'shipments.billsOfLading.shipmentItems.containers'
    ]);

    // 5. Calcular estadísticas para el PDF
    $stats = $this->calculateVoyageStats($voyage);

    // 6. Generar PDF usando vista específica
    $pdf = \PDF::loadView('company.voyages.manifest-pdf', compact('voyage'));    
    // 7. Configurar PDF
    $pdf->setPaper('A4', 'portrait');
    $pdf->setOptions([
        'defaultFont' => 'Arial',
        'isHtml5ParserEnabled' => true,
        'isRemoteEnabled' => true
    ]);
    
    $filename = "viaje-{$voyage->voyage_number}-" . date('Y-m-d') . ".pdf";
    
    return $pdf->download($filename);
}
}