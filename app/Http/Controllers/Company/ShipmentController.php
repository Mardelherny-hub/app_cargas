<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Models\Shipment;
use App\Traits\UserHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ShipmentController extends Controller
{
    use UserHelper;

    /**
     * Mostrar lista de cargas.
     */
    public function index()
    {
        // Verificar si el usuario puede ver cargas
        if (!$this->canPerform('view_cargas')) {
            abort(403, 'No tiene permisos para ver cargas.');
        }

        // Verificar que la empresa tenga rol "Cargas"
        if (!$this->hasCompanyRole('Cargas')) {
            abort(403, 'Su empresa no tiene el rol de Cargas.');
        }

        $company = $this->getUserCompany();

        if (!$company) {
            return redirect()->route('dashboard')
                ->with('error', 'No se encontró la empresa asociada.');
        }

        // CORREGIDO: Consulta usando la relación correcta a través de bills_of_lading
        $shipments = Shipment::whereHas('voyage', function($q) use ($company) {
                $q->where('company_id', $company->id);
            })
            ->when($this->isUser() && $this->isOperator(), function($query) {
                // CORREGIDO: Especificar la tabla para evitar ambiguedad
                $query->where('shipments.created_by_user_id', Auth::id());
            })
            ->with([
                'voyage' => function($q) {
                    $q->select('id', 'voyage_number', 'company_id', 'status', 'departure_date', 'estimated_arrival_date');
                },
                'voyage.company:id,legal_name,commercial_name',
                'vessel:id,name,vessel_type_id,registration_number',
                'vessel.vesselType:id,name,code,category'
            ])
            // CORREGIDO: JOIN correcto a través de bills_of_lading
            ->leftJoin('bills_of_lading', 'shipments.id', '=', 'bills_of_lading.shipment_id')
            ->leftJoin('shipment_items', 'bills_of_lading.id', '=', 'shipment_items.bill_of_lading_id')
            ->select(
                'shipments.id',
                'shipments.voyage_id',
                'shipments.vessel_id', 
                'shipments.shipment_number',
                'shipments.status',
                'shipments.cargo_weight_loaded',
                'shipments.containers_loaded',
                'shipments.utilization_percentage',
                'shipments.created_at',
                \DB::raw('COUNT(shipment_items.id) as shipment_items_count'),
                \DB::raw('SUM(shipment_items.package_quantity) as shipment_items_sum_package_quantity'),
                \DB::raw('SUM(shipment_items.gross_weight_kg) as shipment_items_sum_gross_weight_kg')
            )
            ->groupBy(
                'shipments.id',
                'shipments.voyage_id',
                'shipments.vessel_id',
                'shipments.shipment_number',
                'shipments.status',
                'shipments.cargo_weight_loaded',
                'shipments.containers_loaded',
                'shipments.utilization_percentage',
                'shipments.created_at'
            )
            ->latest('shipments.created_at')
            ->paginate(20);

        // Calcular estadísticas generales
        $stats = $this->calculateShipmentStats($company);

        return view('company.shipments.index', compact('shipments', 'stats'));
    }

    /**
     * Calcular estadísticas de shipments para el dashboard.
     */
    private function calculateShipmentStats($company): array
    {
        $baseQuery = Shipment::whereHas('voyage', function($q) use ($company) {
            $q->where('company_id', $company->id);
        });

        // Si es operador, aplicar filtro de ownership
        if ($this->isUser() && $this->isOperator()) {
            $baseQuery->where('created_by_user_id', Auth::id());
        }

        return [
            'total' => $baseQuery->count(),
            'planning' => (clone $baseQuery)->where('status', 'planning')->count(),
            'loading' => (clone $baseQuery)->where('status', 'loading')->count(),
            'loaded' => (clone $baseQuery)->where('status', 'loaded')->count(),
            'in_transit' => (clone $baseQuery)->where('status', 'in_transit')->count(),
            'arrived' => (clone $baseQuery)->where('status', 'arrived')->count(),
            'discharging' => (clone $baseQuery)->where('status', 'discharging')->count(),
            'completed' => (clone $baseQuery)->where('status', 'completed')->count(),
            'delayed' => (clone $baseQuery)->where('status', 'delayed')->count(),
            'cargo_weight_total' => (clone $baseQuery)->sum('cargo_weight_loaded'),
            'containers_total' => (clone $baseQuery)->sum('containers_loaded'),
            'average_utilization' => (clone $baseQuery)->avg('utilization_percentage'),
        ];
    }

   /**
     * Mostrar formulario para crear nueva carga.
     */
   // ===== MÉTODO CREATE - MODIFICADO =====
    public function create(Request $request)
    {
        // Verificar permisos para crear cargas
        if (!$this->canPerform('view_cargas')) {
            abort(403, 'No tiene permisos para crear cargas.');
        }

        if (!$this->hasCompanyRole('Cargas')) {
            abort(403, 'Su empresa no tiene el rol de Cargas.');
        }

        $company = $this->getUserCompany();

        if (!$company) {
            return redirect()->route('company.shipments.index')
                ->with('error', 'No se encontró la empresa asociada.');
        }

        // Obtener voyage_id desde query parameter o request
        $voyageId = $request->get('voyage_id') ?: $request->get('voyage');
        
        if (!$voyageId) {
            return redirect()->route('company.voyages.index')
                ->with('error', 'Debe seleccionar un viaje para crear el shipment.');
        }

        // NUEVO: Cargar viaje con sus datos
        $voyage = \App\Models\Voyage::with(['leadVessel', 'captain'])
            ->where('id', $voyageId)
            ->where('company_id', $company->id)
            ->first();

        if (!$voyage) {
            return redirect()->route('company.voyages.index')
                ->with('error', 'El viaje seleccionado no existe o no pertenece a su empresa.');
        }

        // NUEVO: Verificar que el viaje tenga embarcación y capitán
        if (!$voyage->lead_vessel_id) {
            return redirect()->route('company.voyages.edit', $voyage)
                ->with('error', 'El viaje debe tener una embarcación líder asignada antes de crear shipments. Por favor complete los datos del viaje.');
        }

        // Obtener datos para el formulario (simplificados)
        $formData = $this->getSimplifiedFormData($company, $voyage);

        $nextShipmentNumber = $this->generateNextShipmentNumber($voyage);

        return view('company.shipments.create', compact(
            'shipment',
            'billOfLading', 
            'needsToCreateBL',
            'defaultBLData',
            'cargoTypes', 
            'packagingTypes', 
            'clients',
            'ports',
            'countries',
            'containerTypes', 
        ));
    }

    /**
     * Generar el siguiente número de shipment para la empresa.
     */
    private function generateNextShipmentNumber($company): string
    {
        $year = now()->year;
        $companyCode = strtoupper(substr($company->commercial_name ?? $company->legal_name, 0, 3));
        
        // Buscar el último número para este año y empresa
        $lastShipment = \App\Models\Shipment::whereHas('voyage', function($query) use ($company) {
                $query->where('company_id', $company->id);
            })
            ->where('shipment_number', 'like', "{$companyCode}-{$year}-%")
            ->orderBy('shipment_number', 'desc')
            ->first();

        if ($lastShipment) {
            // Extraer el número secuencial del último shipment
            $parts = explode('-', $lastShipment->shipment_number);
            $lastNumber = isset($parts[2]) ? intval($parts[2]) : 0;
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }

        return sprintf('%s-%d-%04d', $companyCode, $year, $nextNumber);
    }

    /**
     * Almacenar nueva carga.
     */
    // ===== MÉTODO STORE - MODIFICADO =====
    public function store(Request $request)
    {
        // Verificaciones de permisos (mantener igual)...

        try {
            DB::beginTransaction();

            // Validación básica
            $validated = $request->validate([
                'voyage_id' => 'required|exists:voyages,id',
                'shipment_number' => 'required|string|max:50|unique:shipments,shipment_number',
                'vessel_role' => 'required|in:single,lead,follow,support',
                'convoy_position' => 'nullable|integer|min:1|max:10',
                'is_lead_vessel' => 'boolean',
                'cargo_capacity_tons' => 'required|numeric|min:0.01',
                'container_capacity' => 'nullable|integer|min:0',
                'status' => 'required|in:planning,loading,loaded,in_transit,arrived,discharging,completed',
                'special_instructions' => 'nullable|string|max:1000',
                'handling_notes' => 'nullable|string|max:1000',
            ]);

            // NUEVO: Cargar voyage con sus datos
            $voyage = \App\Models\Voyage::with(['leadVessel', 'captain'])
                ->findOrFail($validated['voyage_id']);

            // NUEVO: Validar que el viaje pertenezca a la empresa del usuario
            if ($voyage->company_id !== $this->getUserCompany()->id) {
                throw new \Exception('El viaje no pertenece a su empresa.');
            }

            // NUEVO: Auto-heredar vessel_id y captain_id del viaje
            $validated['vessel_id'] = $voyage->lead_vessel_id;
            $validated['captain_id'] = $voyage->captain_id;

            // NUEVO: Validación automática - no permitir duplicados en el mismo viaje
            $existingShipment = \App\Models\Shipment::where('voyage_id', $validated['voyage_id'])
                ->where('vessel_id', $validated['vessel_id'])
                ->first();

            if ($existingShipment) {
                throw new \Exception('Ya existe un shipment para esta embarcación en el viaje ' . $voyage->voyage_number);
            }

            // Calcular secuencia automáticamente
            $maxSequence = \App\Models\Shipment::where('voyage_id', $validated['voyage_id'])
                ->max('sequence_in_voyage');
            $validated['sequence_in_voyage'] = ($maxSequence ?? 0) + 1;

            // Lógica de convoy simplificada
            if ($validated['vessel_role'] === 'single') {
                $validated['convoy_position'] = null;
                $validated['is_lead_vessel'] = true; // En viaje single, siempre es líder
            } else {
                // Para convoy, validar posición única
                if ($validated['convoy_position']) {
                    $duplicatePosition = \App\Models\Shipment::where('voyage_id', $validated['voyage_id'])
                        ->where('convoy_position', $validated['convoy_position'])
                        ->exists();

                    if ($duplicatePosition) {
                        throw new \Exception('Ya existe otra embarcación en la posición ' . $validated['convoy_position'] . ' del convoy.');
                    }
                }
            }

            // Preparar datos adicionales
            $validated['created_by_user_id'] = Auth::id();
            $validated['created_date'] = now();
            $validated['active'] = true;
            $validated['requires_attention'] = false; // Ya no requiere atención porque hereda datos válidos

            // Crear el shipment
            $shipment = \App\Models\Shipment::create($validated);

            // Actualizar estadísticas del viaje
            $this->updateVoyageStats($voyage);

            DB::commit();

            Log::info('Shipment creado con datos heredados del viaje', [
                'shipment_id' => $shipment->id,
                'voyage_id' => $voyage->id,
                'inherited_vessel_id' => $validated['vessel_id'],
                'inherited_captain_id' => $validated['captain_id'],
                'user_id' => Auth::id()
            ]);

            return redirect()->route('company.shipments.show', $shipment)
                ->with('success', 'Shipment creado exitosamente.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creando shipment: ' . $e->getMessage());
            
            return back()->with('error', 'Error: ' . $e->getMessage())
                        ->withInput();
        }
    }

    // ===== NUEVO MÉTODO: Datos simplificados para formulario =====
    private function getSimplifiedFormData($company, $voyage): array
    {
        return [
            'vesselRoles' => [
                'single' => 'Embarcación Única',
                'lead' => 'Líder de Convoy',
                'follow' => 'Seguidor',
                'support' => 'Apoyo'
            ],
            'statusOptions' => [
                'planning' => 'Planificación',
                'loading' => 'Cargando',
                'loaded' => 'Cargado',
                'in_transit' => 'En Tránsito',
                'arrived' => 'Arribado',
                'discharging' => 'Descargando',
                'completed' => 'Completado'
            ],
            // NUEVO: Información del viaje para mostrar en el formulario
            'voyageInfo' => [
                'vessel_name' => $voyage->leadVessel->name ?? 'Sin embarcación asignada',
                'vessel_type' => $voyage->leadVessel->vesselType->name ?? 'N/A',
                'captain_name' => $voyage->captain->full_name ?? 'Sin capitán asignado',
                'captain_license' => $voyage->captain->license_number ?? 'N/A',
                'cargo_capacity' => $voyage->leadVessel->cargo_capacity_tons ?? 0,
                'container_capacity' => $voyage->leadVessel->container_capacity ?? 0,
            ]
        ];
    }

    // ===== NUEVO MÉTODO: Actualizar estadísticas del viaje =====
    private function updateVoyageStats($voyage): void
    {
        $shipmentStats = $voyage->shipments()->selectRaw('
            COUNT(*) as total_shipments,
            SUM(cargo_weight_loaded) as total_weight,
            SUM(containers_loaded) as total_containers,
            SUM(cargo_capacity_tons) as total_capacity
        ')->first();

        $voyage->update([
            'total_cargo_weight_loaded' => $shipmentStats->total_weight ?? 0,
            'total_containers_loaded' => $shipmentStats->total_containers ?? 0,
            'total_cargo_capacity_tons' => $shipmentStats->total_capacity ?? 0,
            'capacity_utilization_percentage' => $shipmentStats->total_capacity > 0 
                ? (($shipmentStats->total_weight ?? 0) / $shipmentStats->total_capacity) * 100 
                : 0,
            'last_updated_date' => now(),
            'last_updated_by_user_id' => Auth::id()
        ]);
    }

    /**
     * Recalcular estadísticas del voyage después de agregar/modificar shipments.
     */
    private function recalculateVoyageStats(\App\Models\Voyage $voyage)
    {
        $shipments = $voyage->shipments()->get();
        
        $stats = [
            'vessel_count' => $shipments->count(),
            'total_cargo_capacity' => $shipments->sum('cargo_capacity_tons'),
            'total_container_capacity' => $shipments->sum('container_capacity'),
            'total_cargo_loaded' => $shipments->sum('cargo_weight_loaded'),
            'total_containers_loaded' => $shipments->sum('containers_loaded'),
        ];

        // Calcular utilización general
        if ($stats['total_cargo_capacity'] > 0) {
            $stats['cargo_utilization_percentage'] = ($stats['total_cargo_loaded'] / $stats['total_cargo_capacity']) * 100;
        } else {
            $stats['cargo_utilization_percentage'] = 0;
        }

        if ($stats['total_container_capacity'] > 0) {
            $stats['container_utilization_percentage'] = ($stats['total_containers_loaded'] / $stats['total_container_capacity']) * 100;
        } else {
            $stats['container_utilization_percentage'] = 0;
        }

        $voyage->update($stats);
    }

/**
     * Mostrar carga específica con información completa.
     */
    public function show(\App\Models\Shipment $shipment)
    {
        // Verificar permisos básicos
        if (!$this->canPerform('view_cargas')) {
            abort(403, 'No tiene permisos para ver cargas.');
        }

        if (!$this->hasCompanyRole('Cargas')) {
            abort(403, 'Su empresa no tiene el rol de Cargas.');
        }

        $company = $this->getUserCompany();

        if (!$company) {
            return redirect()->route('company.shipments.index')
                ->with('error', 'No se encontró la empresa asociada.');
        }

        // Verificar que la carga pertenezca a la empresa del usuario
        if ($shipment->voyage->company_id !== $company->id) {
            abort(404, 'Carga no encontrada.');
        }

        // Verificar permisos adicionales para usuarios operadores
        if ($this->isUser() && $this->isOperator()) {
            // Los operadores solo pueden ver cargas que crearon
            if ($shipment->created_by_user_id !== Auth::id()) {
                abort(403, 'No tiene permisos para ver esta carga.');
            }
        }

        // Cargar todas las relaciones necesarias
        $shipment->load([
            // Relaciones principales
            'voyage' => function($query) {
                $query->with([
                    'company:id,legal_name,commercial_name',
                    'originPort:id,name,code,city',
                    'destinationPort:id,name,code,city',
                    'originCountry:id,name,iso_code',
                    'destinationCountry:id,name,iso_code',
                ]);
            },
            'vessel' => function($query) {
                $query->with([
                    'vesselType:id,name,category',
                    'owner:id,legal_name',
                    'homePort:id,name,code'
                ]);
            },
            'captain:id,full_name,license_number,license_status,years_of_experience,phone,email',
            
            // Items y cargas
            'shipmentItems' => function($query) {
                $query->with([
                    'cargoType:id,name,description,is_dangerous_goods',
                    'packagingType:id,name,description'
                ])
                ->orderBy('line_number');
            },
            
            // Conocimientos de embarque
            'billsOfLading' => function($query) {
                $query->with([
                    'shipper:id,legal_name',
                    'consignee:id,legal_name',
                    'loadingPort:id,name,code',
                    'dischargePort:id,name,code'
                ])
                ->orderBy('created_at', 'desc');
            },
            
            // Usuario creador
            'createdByUser:id,name,email'
        ]);

        // Calcular estadísticas del shipment
        $stats = $this->calculateShipmentDetailStats($shipment);

        // Obtener otros shipments del mismo voyage para navegación
        $voyageShipments = \App\Models\Shipment::where('voyage_id', $shipment->voyage_id)
            ->where('id', '!=', $shipment->id)
            ->with(['vessel:id,name', 'captain:id,full_name'])
            ->orderBy('sequence_in_voyage')
            ->get(['id', 'vessel_id', 'captain_id', 'shipment_number', 'status', 'sequence_in_voyage']);

        // TODO: Implementar funcionalidad de attachments en el futuro
        $hasAttachments = false;

        // Preparar opciones para cambio de estado (según permisos)
        $statusTransitions = $this->getAvailableStatusTransitions($shipment);

        // Información de permisos del usuario actual
        $userPermissions = [
            'can_edit' => $this->canEditShipment($shipment),
            'can_delete' => $this->canDeleteShipment($shipment),
            'can_change_status' => $this->canChangeShipmentStatus($shipment),
            'can_manage_items' => $this->canManageShipmentItems($shipment),
            'can_view_attachments' => true,
            'can_add_attachments' => $this->canEditShipment($shipment),
        ];

        return view('company.shipments.show', compact(
            'shipment', 
            'stats', 
            'voyageShipments', 
            'hasAttachments',
            'statusTransitions',
            'userPermissions',
            'company'
        ));
    }

    /**
     * Calcular estadísticas detalladas del shipment.
     */
    private function calculateShipmentDetailStats(\App\Models\Shipment $shipment): array
    {
        $items = $shipment->shipmentItems;
        
        return [
            // Estadísticas de items
            'total_items' => $items->count(),
            'total_packages' => $items->sum('package_quantity'),
            'total_gross_weight_kg' => $items->sum('gross_weight_kg'),
            'total_net_weight_kg' => $items->sum('net_weight_kg'),
            'total_volume_m3' => $items->sum('volume_m3'),
            'total_declared_value' => $items->sum('declared_value'),
            
            // Estadísticas de utilización
            'cargo_utilization_percentage' => $shipment->utilization_percentage,
            'container_utilization_percentage' => $shipment->container_capacity > 0 
                ? ($shipment->containers_loaded / $shipment->container_capacity) * 100 
                : 0,
            
            // Estadísticas de mercancías peligrosas
            'dangerous_goods_count' => $items->where('is_dangerous_goods', true)->count(),
            'perishable_goods_count' => $items->where('is_perishable', true)->count(),
            'refrigerated_goods_count' => $items->where('requires_refrigeration', true)->count(),
            
            // Estadísticas de documentación
            'items_requiring_permits' => $items->where('requires_permit', true)->count(),
            'items_requiring_inspection' => $items->where('requires_inspection', true)->count(),
            'items_with_discrepancies' => $items->where('has_discrepancies', true)->count(),
            
            // Estadísticas de conocimientos
            'total_bills_of_lading' => $shipment->billsOfLading->count(),
            'bills_pending' => $shipment->billsOfLading->where('status', 'draft')->count(),
            'bills_issued' => $shipment->billsOfLading->where('status', 'issued')->count(),
            
            // Alertas y atención
            'requires_attention' => $shipment->requires_attention,
            'has_delays' => $shipment->has_delays,
            'safety_approved' => $shipment->safety_approved,
            'customs_cleared' => $shipment->customs_cleared,
            'documentation_complete' => $shipment->documentation_complete,
            'cargo_inspected' => $shipment->cargo_inspected,
        ];
    }

    /**
     * Obtener transiciones de estado disponibles según el estado actual.
     */
    private function getAvailableStatusTransitions(\App\Models\Shipment $shipment): array
    {
        $transitions = [];
        
        switch ($shipment->status) {
            case 'planning':
                $transitions = [
                    'loading' => 'Iniciar Carga',
                    'loaded' => 'Marcar como Cargado'
                ];
                break;
                
            case 'loading':
                $transitions = [
                    'loaded' => 'Completar Carga',
                    'planning' => 'Volver a Planificación'
                ];
                break;
                
            case 'loaded':
                $transitions = [
                    'in_transit' => 'Iniciar Tránsito',
                    'loading' => 'Volver a Carga'
                ];
                break;
                
            case 'in_transit':
                $transitions = [
                    'arrived' => 'Marcar Arribado',
                    'delayed' => 'Marcar Demorado'
                ];
                break;
                
            case 'arrived':
                $transitions = [
                    'discharging' => 'Iniciar Descarga',
                    'in_transit' => 'Volver a Tránsito'
                ];
                break;
                
            case 'discharging':
                $transitions = [
                    'completed' => 'Completar Descarga',
                    'arrived' => 'Volver a Arribado'
                ];
                break;
                
            case 'delayed':
                $transitions = [
                    'in_transit' => 'Reanudar Tránsito',
                    'arrived' => 'Marcar Arribado'
                ];
                break;
        }
        
        return $transitions;
    }

    /**
     * Verificar si el usuario puede editar el shipment.
     */
    private function canEditShipment(\App\Models\Shipment $shipment): bool
    {
        // Company admin puede editar siempre
        if ($this->isCompanyAdmin()) {
            return true;
        }
        
        // Users operadores solo pueden editar shipments en estado planning o loading
        if ($this->isUser() && $this->isOperator()) {
            return in_array($shipment->status, ['planning', 'loading']) 
                   && $shipment->created_by_user_id === Auth::id();
        }
        
        return false;
    }

    /**
     * Verificar si el usuario puede eliminar el shipment.
     */
    private function canDeleteShipment(\App\Models\Shipment $shipment): bool
    {
        // Solo se puede eliminar en estado planning
        if ($shipment->status !== 'planning') {
            return false;
        }
        
        // Company admin puede eliminar
        if ($this->isCompanyAdmin()) {
            return true;
        }
        
        // Users operadores solo pueden eliminar sus propios shipments
        if ($this->isUser() && $this->isOperator()) {
            return $shipment->created_by_user_id === Auth::id();
        }
        
        return false;
    }

    /**
     * Verificar si el usuario puede cambiar el estado del shipment.
     */
    private function canChangeShipmentStatus(\App\Models\Shipment $shipment): bool
    {
        return $this->canEditShipment($shipment);
    }

    /**
     * Verificar si el usuario puede gestionar items del shipment.
     */
    private function canManageShipmentItems(\App\Models\Shipment $shipment): bool
    {
        // Solo en estados que permiten modificación de items
        if (!in_array($shipment->status, ['planning', 'loading'])) {
            return false;
        }
        
        return $this->canEditShipment($shipment);
    }


/**
 * Mostrar formulario para editar carga.
 * MÉTODO CORREGIDO - EXACTOS permisos que index/show/create
 */
public function edit(Shipment $shipment)
{
    // COPIADO EXACTO de index/show/create
    // Verificar si el usuario puede ver cargas
    if (!$this->canPerform('view_cargas')) {
        abort(403, 'No tiene permisos para editar cargas.');
    }

    // Verificar que la empresa tenga rol "Cargas"
    if (!$this->hasCompanyRole('Cargas')) {
        abort(403, 'Su empresa no tiene el rol de Cargas.');
    }

    $company = $this->getUserCompany();

    if (!$company) {
        return redirect()->route('company.shipments.index')
            ->with('error', 'No se encontró la empresa asociada.');
    }

    // COPIADO EXACTO de show() - Verificar que la carga pertenezca a la empresa del usuario
    if ($shipment->voyage->company_id !== $company->id) {
        abort(404, 'Carga no encontrada.');
    }

    // COPIADO EXACTO de show() - Verificar permisos adicionales para usuarios operadores
    if ($this->isUser() && $this->isOperator()) {
        // Los operadores solo pueden ver cargas que crearon
        if ($shipment->created_by_user_id !== Auth::id()) {
            abort(403, 'No tiene permisos para editar esta carga.');
        }
    }

    // Verificar estado de la carga
    if (in_array($shipment->status, ['completed', 'cancelled'])) {
        return redirect()->route('company.shipments.show', $shipment)
            ->with('error', 'No se puede editar una carga completada o cancelada.');
    }

    // Cargar relaciones necesarias para el formulario
    $shipment->load([
        'voyage:id,voyage_number,company_id,origin_port_id,destination_port_id',
        'voyage.company:id,legal_name,commercial_name',  // ← Agregar esta línea
        'voyage.originPort:id,name',
        'voyage.destinationPort:id,name',
        'vessel:id,name',
        'captain:id,first_name,last_name',
        //'containers'
    ]);

    // Obtener datos para el formulario
    $formData = $this->getFormData($company);

    // Extraer las variables para la vista
    $captains = $formData['captains'];
    $vesselRoles = $formData['vesselRoles'];
    $statusOptions = $formData['statusOptions'];

    return view('company.shipments.edit', compact(
        'shipment', 
        'formData', 
        'company',
        'captains',
        'vesselRoles', 
        'statusOptions'
    ));
}

/**
 * MÉTODO CORREGIDO: Obtener datos para formularios - AGREGANDO EMBARCACIONES
 * Basado en el método create() del mismo controlador
 */
private function getFormData($company): array
{
    // COPIADO EXACTO del método create() - Obtener embarcaciones disponibles
    $vessels = \App\Models\Vessel::where('company_id', $company->id)
        ->where('active', true)
        ->where('available_for_charter', true)
        ->where('operational_status', 'active')
        ->with(['vesselType'])
        ->orderBy('name')
        ->get()
        ->map(function ($vessel) {
            $vesselTypeName = $vessel->vesselType->name ?? 'N/A';
            return [
                'id' => $vessel->id,
                'name' => $vessel->name,
                'vessel_type' => $vesselTypeName,
                'registration_number' => $vessel->registration_number,
                'cargo_capacity_tons' => $vessel->cargo_capacity_tons ?? 0,
                'container_capacity' => $vessel->container_capacity ?? 0,
                'display_name' => "{$vessel->name} ({$vesselTypeName}) - {$vessel->registration_number}"
            ];
        });
    
    // COPIADO EXACTO del método create() - Obtener capitanes disponibles
    $captains = \App\Models\Captain::where('active', true)
        ->where('available_for_hire', true)
        ->where('license_status', 'valid')
        ->where(function($query) {
            $query->whereNull('license_expires_at')
                  ->orWhere('license_expires_at', '>=', now());
        })
        ->orderBy('last_name')
        ->orderBy('first_name')
        ->get()
        ->map(function ($captain) {
            $yearsExperience = $captain->years_of_experience ?? 0;
            return [
                'id' => $captain->id,
                'full_name' => $captain->full_name,
                'license_number' => $captain->license_number,
                'years_of_experience' => $yearsExperience,
                'display_name' => "{$captain->full_name} - Lic: {$captain->license_number} ({$yearsExperience} años)"
            ];
        });

    // Opciones para los selects - MISMO CÓDIGO QUE CREATE
    $vesselRoles = [
        'single' => 'Embarcación Única',
        'lead' => 'Líder de Convoy',
        'towed' => 'Remolcada',
        'pushed' => 'Empujada',
        'escort' => 'Escolta'
    ];

    $statusOptions = [
        'planning' => 'Planificación',
        'loading' => 'Cargando',
        'loaded' => 'Cargado'
    ];

    // CORREGIDO: Ahora incluye embarcaciones para shipments que requieren atención
    return [
        'vessels' => $vessels,        // ✅ AGREGADO: Para shipments duplicados
        'captains' => $captains,
        'vesselRoles' => $vesselRoles,
        'statusOptions' => $statusOptions,
    ];
}

/**
 * CORRECCIÓN ADICIONAL: Actualizar carga - EXACTOS permisos que otros métodos
 * Usar las mismas verificaciones que funcionan en index/show/create
 */
public function update(Request $request, Shipment $shipment)
{
    // COPIADO EXACTO de index/show/create
    // Verificar si el usuario puede ver cargas
    if (!$this->canPerform('view_cargas')) {
        abort(403, 'No tiene permisos para editar cargas.');
    }

    // Verificar que la empresa tenga rol "Cargas"
    if (!$this->hasCompanyRole('Cargas')) {
        abort(403, 'Su empresa no tiene el rol de Cargas.');
    }

    $company = $this->getUserCompany();

    if (!$company) {
        return redirect()->route('company.shipments.index')
            ->with('error', 'No se encontró la empresa asociada.');
    }

    // COPIADO EXACTO de show() - Verificar que la carga pertenezca a la empresa del usuario
    if ($shipment->voyage->company_id !== $company->id) {
        abort(404, 'Carga no encontrada.');
    }

    // COPIADO EXACTO de show() - Verificar permisos adicionales para usuarios operadores
    if ($this->isUser() && $this->isOperator()) {
        // Los operadores solo pueden ver cargas que crearon
        if ($shipment->created_by_user_id !== Auth::id()) {
            abort(403, 'No tiene permisos para editar esta carga.');
        }
    }

    // Verificar estado de la carga
    if (in_array($shipment->status, ['completed', 'cancelled'])) {
        return redirect()->route('company.shipments.show', $shipment)
            ->with('error', 'No se puede editar una carga completada o cancelada.');
    }

    // Determinar si vessel_id es editable (solo si requires_attention)
    $vesselValidation = $shipment->requires_attention ? 'required|exists:vessels,id' : 'sometimes';

    // Validar datos - ACTUALIZAR LA VALIDACIÓN EXISTENTE
    $validated = $request->validate([
        'shipment_number' => 'required|string|max:50|unique:shipments,shipment_number,' . $shipment->id,
        'vessel_id' => $vesselValidation,  // ✅ CONDICIONAL: requerido solo si requires_attention
        'captain_id' => 'nullable|exists:captains,id',
        'vessel_role' => 'required|in:single,lead,towed,pushed,escort',
        'convoy_position' => 'nullable|integer|min:1|max:20',
        'is_lead_vessel' => 'boolean',
        'cargo_capacity_tons' => 'required|numeric|min:0|max:99999.99',
        'container_capacity' => 'nullable|integer|min:0|max:9999',
        'status' => 'required|in:planning,loading,loaded,in_transit,arrived,discharging,completed,delayed',
    ]);

    // Si cambió vessel_id (de placeholder a embarcación real), quitar requires_attention
if ($shipment->requires_attention && !empty($validated['vessel_id']) && $validated['vessel_id'] != $shipment->vessel_id) {
    $validated['requires_attention'] = false;
    
    // Limpiar notas de placeholder si existen
    if (strpos($shipment->handling_notes ?? '', '🔄 DUPLICADO DE:') !== false) {
        $validated['handling_notes'] = preg_replace('/🔄 DUPLICADO DE:.*?(?=\n\n|\n[^⚠️]|$)/s', '', $shipment->handling_notes ?? '');
        $validated['handling_notes'] = trim($validated['handling_notes']);
    }
    
    Log::info('Shipment actualizado: embarcación placeholder reemplazada', [
        'shipment_id' => $shipment->id,
        'old_vessel_id' => $shipment->vessel_id,
        'new_vessel_id' => $validated['vessel_id'],
        'user_id' => Auth::id()
    ]);
}

// Si vessel_id no está en la validación (readonly), no incluirlo en updateData
$updateData = [
    'shipment_number' => $validated['shipment_number'],
    'captain_id' => $validated['captain_id'],
    'vessel_role' => $validated['vessel_role'],
    'convoy_position' => $validated['convoy_position'],
    'is_lead_vessel' => $validated['is_lead_vessel'] ?? false,
    'cargo_capacity_tons' => $validated['cargo_capacity_tons'],
    'container_capacity' => $validated['container_capacity'] ?? 0,
    'status' => $validated['status'],
];

// Solo agregar vessel_id si está permitido editarlo
if ($shipment->requires_attention && !empty($validated['vessel_id'])) {
    $updateData['vessel_id'] = $validated['vessel_id'];
    $updateData['requires_attention'] = $validated['requires_attention'] ?? false;
    $updateData['handling_notes'] = $validated['handling_notes'] ?? $shipment->handling_notes;
}

    try {
        DB::beginTransaction();

        // Validaciones de lógica de convoy - COPIADO de store()
        if ($validated['vessel_role'] === 'single') {
            $validated['convoy_position'] = null;
            $validated['is_lead_vessel'] = false;
        } else {
            if (!empty($validated['convoy_position'])) {
                $duplicatePosition = \App\Models\Shipment::where('voyage_id', $shipment->voyage_id)
                    ->where('convoy_position', $validated['convoy_position'])
                    ->where('id', '!=', $shipment->id) // Excluir el shipment actual
                    ->exists();

                if ($duplicatePosition) {
                    throw new \Exception('Ya existe otra embarcación en la posición ' . $validated['convoy_position'] . ' del convoy.');
                }
            }
        }

        // Validar capitán si se proporciona - COPIADO de store()
        if (!empty($validated['captain_id'])) {
            $captain = \App\Models\Captain::where('id', $validated['captain_id'])
                ->where('active', true)
                ->where('available_for_hire', true)
                ->where('license_status', 'valid')
                ->first();

            if (!$captain) {
                throw new \Exception('El capitán seleccionado no está disponible o no tiene licencia válida.');
            }
        }

        // Actualizar campos editables solamente
        $updateData = [
            'shipment_number' => $validated['shipment_number'],
            'captain_id' => $validated['captain_id'],
            'vessel_role' => $validated['vessel_role'],
            'convoy_position' => $validated['convoy_position'],
            'is_lead_vessel' => $validated['is_lead_vessel'] ?? false,
            'cargo_capacity_tons' => $validated['cargo_capacity_tons'],
            'container_capacity' => $validated['container_capacity'] ?? 0,
            'status' => $validated['status'],
        ];

        $shipment->update($updateData);

        // Si cambió a embarcación líder, actualizar el voyage
        if ($validated['is_lead_vessel'] && !$shipment->getOriginal('is_lead_vessel')) {
            $voyage = $shipment->voyage;
            $voyage->update([
                'lead_vessel_id' => $shipment->vessel_id,
                'captain_id' => $validated['captain_id'],
            ]);
        }

        DB::commit();

        Log::info('Shipment actualizado', [
            'shipment_id' => $shipment->id,
            'shipment_number' => $shipment->shipment_number,
            'user_id' => Auth::id()
        ]);

        return redirect()->route('company.shipments.index')
            ->with('success', 'Carga actualizada exitosamente.');

    } catch (\Exception $e) {
        DB::rollBack();
        
        Log::error('Error actualizando shipment', [
            'shipment_id' => $shipment->id,
            'error' => $e->getMessage(),
            'user_id' => Auth::id()
        ]);

        return redirect()->back()
            ->withInput()
            ->with('error', 'Error al actualizar la carga: ' . $e->getMessage());
    }
}

    /**
     * Eliminar carga.
     */
    public function destroy(Shipment $shipment)
    {
        // Solo company-admin puede eliminar cargas
        if (!$this->isCompanyAdmin()) {
            abort(403, 'No tiene permisos para eliminar cargas.');
        }

        if (!$this->hasCompanyRole('Cargas')) {
            abort(403, 'Su empresa no tiene el rol de Cargas.');
        }

        // Verificar que la carga pertenece a la empresa del usuario
        if (!$this->canAccessCompany($shipment->company_id)) {
            abort(403, 'No tiene permisos para eliminar esta carga.');
        }

        // Verificar estado de la carga
        if (in_array($shipment->status, ['completed', 'in_transit'])) {
            return redirect()->route('company.shipments.show', $shipment)
                ->with('error', 'No se puede eliminar una carga completada o en tránsito.');
        }

        $shipment->delete();

        return redirect()->route('company.shipments.index')
            ->with('success', 'Carga eliminada exitosamente.');
    }

    /**
     * Actualizar estado de la carga - MÉTODO CORREGIDO
     */
    public function updateStatus(Request $request, Shipment $shipment)
    {
        // Verificar permisos básicos
        if (!$this->canPerform('view_cargas')) {
            abort(403, 'No tiene permisos para modificar cargas.');
        }

        if (!$this->hasCompanyRole('Cargas')) {
            abort(403, 'Su empresa no tiene el rol de Cargas.');
        }

        $company = $this->getUserCompany();

        if (!$company) {
            return redirect()->route('company.shipments.index')
                ->with('error', 'No se encontró la empresa asociada.');
        }

        // CORREGIDO: Verificar que la carga pertenezca a la empresa del usuario
        // Los shipments no tienen company_id directo, hay que acceder via voyage
        if ($shipment->voyage->company_id !== $company->id) {
            abort(403, 'No tiene permisos para modificar esta carga.');
        }

        // CORREGIDO: Verificar permisos adicionales para usuarios operadores
        if ($this->isUser() && $this->isOperator()) {
            // Los operadores solo pueden modificar cargas que crearon
            if ($shipment->created_by_user_id !== Auth::id()) {
                abort(403, 'No tiene permisos para modificar esta carga.');
            }
        }

        // Solo company-admin puede cambiar ciertos estados críticos
        $restrictedStatuses = ['completed', 'cancelled'];
        if (in_array($request->status, $restrictedStatuses) && !$this->isCompanyAdmin()) {
            abort(403, 'No tiene permisos para cambiar a este estado.');
        }

        // Validar los estados disponibles según el modelo
        $validStatuses = ['planning', 'loading', 'loaded', 'in_transit', 'arrived', 'discharging', 'completed', 'delayed'];
        
        $request->validate([
            'status' => 'required|in:' . implode(',', $validStatuses),
            'notes' => 'nullable|string|max:1000',
        ]);

        try {
            DB::beginTransaction();

            // Obtener el estado anterior para logging
            $oldStatus = $shipment->status;
            $newStatus = $request->status;

            // Actualizar el shipment con campos correctos
            $updateData = [
                'status' => $newStatus,
                'last_updated_date' => now(),
                'last_updated_by_user_id' => Auth::id(),
            ];

            // Agregar notas si existen (usar el campo correcto según el modelo)
            if ($request->filled('notes')) {
                $updateData['handling_notes'] = $request->notes;
            }

            // Actualizar timestamps específicos según el estado
            switch ($newStatus) {
                case 'loading':
                    if (!$shipment->loading_start_time) {
                        $updateData['loading_start_time'] = now();
                    }
                    break;
                    
                case 'loaded':
                    if (!$shipment->loading_end_time) {
                        $updateData['loading_end_time'] = now();
                    }
                    // Si no tenía fecha de inicio, ponerla también
                    if (!$shipment->loading_start_time) {
                        $updateData['loading_start_time'] = now()->subHour(); // Estimado
                    }
                    break;
                    
                case 'in_transit':
                    if (!$shipment->departure_time) {
                        $updateData['departure_time'] = now();
                    }
                    break;
                    
                case 'arrived':
                    if (!$shipment->arrival_time) {
                        $updateData['arrival_time'] = now();
                    }
                    break;
                    
                case 'discharging':
                    if (!$shipment->discharge_start_time) {
                        $updateData['discharge_start_time'] = now();
                    }
                    break;
                    
                case 'completed':
                    if (!$shipment->discharge_end_time) {
                        $updateData['discharge_end_time'] = now();
                    }
                    break;
            }

            $shipment->update($updateData);

            // Log del cambio de estado
            Log::info('Shipment status updated', [
                'shipment_id' => $shipment->id,
                'shipment_number' => $shipment->shipment_number,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'user_id' => Auth::id(),
                'company_id' => $company->id,
                'notes' => $request->notes
            ]);

            DB::commit();

            return redirect()->route('company.shipments.show', $shipment)
                ->with('success', "Estado de la carga actualizado de '{$oldStatus}' a '{$newStatus}' exitosamente.");

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Error updating shipment status', [
                'shipment_id' => $shipment->id,
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            return redirect()->route('company.shipments.show', $shipment)
                ->with('error', 'Error al actualizar el estado: ' . $e->getMessage());
        }
    }

    /**
     * Duplicar carga.
     */
/**
 * Duplicar carga - MÉTODO SEGURO SIN MODIFICAR ESTRUCTURA DB
 * Usa una embarcación placeholder temporal que debe ser cambiada
 */
public function duplicate(Shipment $shipment)
{
    // COPIADO EXACTO de edit() - mismos permisos que funcionan
    if (!$this->canPerform('view_cargas')) {
        abort(403, 'No tiene permisos para crear cargas.');
    }

    if (!$this->hasCompanyRole('Cargas')) {
        abort(403, 'Su empresa no tiene el rol de Cargas.');
    }

    $company = $this->getUserCompany();

    if (!$company) {
        return redirect()->route('company.shipments.index')
            ->with('error', 'No se encontró la empresa asociada.');
    }

    // COPIADO EXACTO de edit() - Verificar que la carga pertenezca a la empresa del usuario
    if ($shipment->voyage->company_id !== $company->id) {
        abort(404, 'Carga no encontrada.');
    }

    // COPIADO EXACTO de edit() - Verificar permisos adicionales para usuarios operadores
    if ($this->isUser() && $this->isOperator()) {
        if ($shipment->created_by_user_id !== Auth::id()) {
            abort(403, 'No tiene permisos para duplicar esta carga.');
        }
    }

    try {
        DB::beginTransaction();

        // 🔧 ESTRATEGIA: Buscar una embarcación disponible como placeholder temporal
        $placeholderVessel = $this->findPlaceholderVessel($company, $shipment->voyage_id);
        
        if (!$placeholderVessel) {
            throw new \Exception('No hay embarcaciones disponibles para crear el duplicado. Debe tener al menos una embarcación disponible en su empresa.');
        }

        // Crear nueva carga basada en la existente
        $newShipment = $shipment->replicate();
        
        // Generar nuevo número único
        $newShipmentNumber = $this->generateNextShipmentNumber($company);
        $newShipment->shipment_number = $newShipmentNumber;
        
        // ✅ CORRECCIÓN: Usar embarcación placeholder temporal
        $newShipment->vessel_id = $placeholderVessel->id;  // Placeholder temporal
        $newShipment->captain_id = null;                   // Sin capitán hasta seleccionar embarcación real
        $newShipment->convoy_position = null;              // Se recalculará
        
        // Resetear estado y metadatos
        $newShipment->status = 'planning';
        $newShipment->created_by_user_id = Auth::id();
        $newShipment->created_date = now();
        
        // ✅ Mantener voyage_id (mismo viaje)
        // ✅ Recalcular secuencia automáticamente
        $maxSequence = \App\Models\Shipment::where('voyage_id', $shipment->voyage_id)
            ->max('sequence_in_voyage');
        $newShipment->sequence_in_voyage = ($maxSequence ?? 0) + 1;
        
        // Resetear campos operacionales
        $newShipment->cargo_weight_loaded = 0;
        $newShipment->containers_loaded = 0;
        $newShipment->utilization_percentage = 0;
        $newShipment->loading_start_time = null;
        $newShipment->loading_end_time = null;
        $newShipment->departure_time = null;
        $newShipment->arrival_time = null;
        $newShipment->discharge_start_time = null;
        $newShipment->discharge_end_time = null;
        
        // Resetear campos de tracking
        $newShipment->current_latitude = null;
        $newShipment->current_longitude = null;
        $newShipment->position_updated_at = null;
        $newShipment->last_communication = null;
        
        // Resetear flags de control
        $newShipment->safety_approved = false;
        $newShipment->customs_cleared = false;
        $newShipment->documentation_complete = false;
        $newShipment->cargo_inspected = false;
        $newShipment->requires_attention = true;  // ⚠️ IMPORTANTE: Requiere atención hasta cambiar embarcación
        $newShipment->has_delays = false;
        $newShipment->on_schedule = true;
        
        // Limpiar campos de costos
        $newShipment->freight_cost = null;
        $newShipment->fuel_cost = null;
        $newShipment->port_charges = null;
        $newShipment->total_cost = null;
        
        // Limpiar incidentes y retrasos
        $newShipment->incidents = null;
        $newShipment->delay_reason = null;
        $newShipment->delay_minutes = null;
        
        // Agregar notas explicativas sobre el placeholder
        $originalVesselName = $shipment->vessel->name ?? 'N/A';
        $newShipment->handling_notes = "🔄 DUPLICADO DE: {$shipment->shipment_number} (Embarcación original: {$originalVesselName})\n" .
                                      "⚠️ ATENCIÓN: Este shipment usa embarcación temporal '{$placeholderVessel->name}'. " .
                                      "DEBE seleccionar la embarcación correcta antes de continuar.\n" .
                                      ($newShipment->handling_notes ?? '');

        $newShipment->save();

        DB::commit();

        Log::info('Shipment duplicado exitosamente con embarcación placeholder', [
            'original_id' => $shipment->id,
            'duplicate_id' => $newShipment->id,
            'original_number' => $shipment->shipment_number,
            'duplicate_number' => $newShipment->shipment_number,
            'original_vessel_id' => $shipment->vessel_id,
            'placeholder_vessel_id' => $placeholderVessel->id,
            'placeholder_vessel_name' => $placeholderVessel->name,
            'voyage_id' => $shipment->voyage_id,
            'user_id' => Auth::id(),
            'company_id' => $company->id
        ]);

        return redirect()->route('company.shipments.edit', $newShipment)
            ->with('warning', "Carga duplicada exitosamente con número {$newShipmentNumber}. ⚠️ IMPORTANTE: Debe seleccionar la embarcación correcta ya que se asignó una embarcación temporal ({$placeholderVessel->name}).");

    } catch (\Exception $e) {
        DB::rollBack();
        
        Log::error('Error al duplicar shipment', [
            'shipment_id' => $shipment->id,
            'error' => $e->getMessage(),
            'user_id' => Auth::id(),
            'company_id' => $company->id
        ]);

        return redirect()->back()
            ->with('error', 'Error al duplicar la carga: ' . $e->getMessage());
    }
}

/**
 * Buscar embarcación placeholder para duplicar shipments
 * MÉTODO AUXILIAR NUEVO
 */
private function findPlaceholderVessel($company, $voyageId): ?\App\Models\Vessel
{
    // Estrategia 1: Buscar embarcaciones de la empresa que NO estén asignadas a ningún shipment activo
    $availableVessel = \App\Models\Vessel::where('company_id', $company->id)
        ->where('active', true)
        ->where('available_for_charter', true)
        ->where('operational_status', 'active')
        ->whereNotIn('id', function($query) {
            $query->select('vessel_id')
                  ->from('shipments')
                  ->whereIn('status', ['planning', 'loading', 'loaded', 'in_transit'])
                  ->whereNotNull('vessel_id');
        })
        ->first();
        
    if ($availableVessel) {
        return $availableVessel;
    }
    
    // Estrategia 2: Si no hay embarcaciones disponibles, usar cualquier embarcación de la empresa
    // (El usuario deberá resolverlo cambiando a una embarcación disponible)
    $anyVessel = \App\Models\Vessel::where('company_id', $company->id)
        ->where('active', true)
        ->first();
        
    return $anyVessel;
}


}
