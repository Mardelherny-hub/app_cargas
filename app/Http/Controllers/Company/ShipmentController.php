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

        // Consulta con LEFT JOIN directo para estadísticas
        $shipments = Shipment::whereHas('voyage', function($q) use ($company) {
                $q->where('company_id', $company->id);
            })
            ->when($this->isUser() && $this->isOperator(), function($query) {
                $query->where('created_by_user_id', Auth::id());
            })
            ->with([
                'voyage' => function($q) {
                    $q->select('id', 'voyage_number', 'company_id', 'status', 'departure_date', 'estimated_arrival_date');
                },
                'voyage.company:id,legal_name,commercial_name',
                'vessel:id,name,vessel_type_id,registration_number',
                'vessel.vesselType:id,name,code,category'
            ])
            ->leftJoin('shipment_items', 'shipments.id', '=', 'shipment_items.shipment_id')
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
     * AGREGAR ESTE MÉTODO AL FINAL DE LA CLASE ShipmentController
     */
    private function calculateShipmentStats($company): array
    {
        $baseQuery = Shipment::whereHas('voyage', function($q) use ($company) {
            $q->where('company_id', $company->id);
        });

        // Aplicar filtros según el rol del usuario
        if ($this->isUser() && $this->isOperator()) {
            $baseQuery->where('created_by_user_id', Auth::id());
        }

        // Contar total
        $total = $baseQuery->count(); 
        
        // Contar por estado
        $byStatus = $baseQuery
            ->select('status', \DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        return [
            'total' => $total,
            'pending' => ($byStatus['planning'] ?? 0) + ($byStatus['loading'] ?? 0) + ($byStatus['loaded'] ?? 0),
            'in_transit' => $byStatus['in_transit'] ?? 0,
            'delivered' => ($byStatus['arrived'] ?? 0) + ($byStatus['discharging'] ?? 0) + ($byStatus['completed'] ?? 0),
            'delayed' => $byStatus['delayed'] ?? 0,
            'by_status' => $byStatus,
        ];
    }

   /**
     * Mostrar formulario para crear nueva carga.
     */
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

        // Obtener viajes activos de la empresa (estados que permiten agregar shipments)
        $voyages = \App\Models\Voyage::where('company_id', $company->id)
            ->whereIn('status', ['planning', 'preparation', 'loading'])
            ->where('active', true)
            ->with(['originPort', 'destinationPort', 'leadVessel'])
            ->orderBy('departure_date', 'desc')
            ->get()
            ->map(function ($voyage) {
                $departureDate = $voyage->departure_date->format('d/m/Y');
                return [
                    'id' => $voyage->id,
                    'display_name' => "#{$voyage->voyage_number} - {$voyage->originPort->name} → {$voyage->destinationPort->name} ({$departureDate})",
                    'voyage_number' => $voyage->voyage_number,
                    'departure_date' => $voyage->departure_date,
                    'current_shipments_count' => $voyage->shipments()->count()
                ];
            });

        // Verificar si hay viajes disponibles
        if ($voyages->isEmpty()) {
            return redirect()->route('company.voyages.index')
                ->with('error', 'No hay viajes activos disponibles. Debe crear un viaje primero.');
        }

        // Obtener embarcaciones disponibles de la empresa
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

        // Obtener capitanes disponibles
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

        // Obtener voyage_id de la URL si viene desde un viaje específico
        $selectedVoyageId = $request->get('voyage_id');
        $selectedVoyage = null;
        
        if ($selectedVoyageId) {
            $selectedVoyage = $voyages->firstWhere('id', $selectedVoyageId);
            if (!$selectedVoyage) {
                return redirect()->route('company.shipments.create')
                    ->with('error', 'El viaje seleccionado no es válido.');
            }
        }

        // Generar siguiente número de shipment
        $nextShipmentNumber = $this->generateNextShipmentNumber($company);

        // Opciones para los selects
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

        return view('company.shipments.create', compact(
            'voyages',
            'vessels', 
            'captains',
            'vesselRoles',
            'statusOptions',
            'selectedVoyageId',
            'selectedVoyage',
            'nextShipmentNumber',
            'company'
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
    public function store(Request $request)
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

        // Validación completa
        $validated = $request->validate([
            'voyage_id' => 'required|exists:voyages,id',
            'vessel_id' => 'required|exists:vessels,id',
            'captain_id' => 'nullable|exists:captains,id',
            'shipment_number' => 'required|string|max:50|unique:shipments,shipment_number',
            'vessel_role' => 'required|in:single,lead,towed,pushed,escort',
            'convoy_position' => 'nullable|integer|min:1|max:99',
            'is_lead_vessel' => 'nullable|boolean',
            'cargo_capacity_tons' => 'required|numeric|min:0|max:999999.99',
            'container_capacity' => 'required|integer|min:0|max:9999',
            'status' => 'required|in:planning,loading,loaded',
            'special_instructions' => 'nullable|string|max:1000',
            'handling_notes' => 'nullable|string|max:1000',
        ], [
            'voyage_id.exists' => 'El viaje seleccionado no existe.',
            'vessel_id.exists' => 'La embarcación seleccionada no existe.',
            'captain_id.exists' => 'El capitán seleccionado no existe.',
            'shipment_number.unique' => 'Ya existe una carga con este número.',
            'vessel_role.in' => 'El rol de embarcación seleccionado no es válido.',
            'convoy_position.min' => 'La posición en convoy debe ser mayor a 0.',
            'convoy_position.max' => 'La posición en convoy no puede ser mayor a 99.',
            'cargo_capacity_tons.required' => 'La capacidad de carga es requerida.',
            'cargo_capacity_tons.numeric' => 'La capacidad de carga debe ser un número.',
            'cargo_capacity_tons.max' => 'La capacidad de carga no puede exceder 999,999.99 toneladas.',
            'container_capacity.required' => 'La capacidad de contenedores es requerida.',
            'container_capacity.integer' => 'La capacidad de contenedores debe ser un número entero.',
            'container_capacity.max' => 'La capacidad de contenedores no puede exceder 9,999.',
            'status.in' => 'El estado seleccionado no es válido.',
        ]);

        try {
            DB::beginTransaction();

            // Verificar que el voyage pertenezca a la empresa y esté disponible
            $voyage = \App\Models\Voyage::where('id', $validated['voyage_id'])
                ->where('company_id', $company->id)
                ->where('active', true)
                ->whereIn('status', ['planning', 'preparation', 'loading'])
                ->first();

            if (!$voyage) {
                throw new \Exception('El viaje seleccionado no pertenece a su empresa o no está disponible para agregar cargas.');
            }

            // Verificar que la embarcación pertenezca a la empresa y esté disponible
            $vessel = \App\Models\Vessel::where('id', $validated['vessel_id'])
                ->where('company_id', $company->id)
                ->where('active', true)
                ->where('available_for_charter', true)
                ->where('operational_status', 'active')
                ->first();

            if (!$vessel) {
                throw new \Exception('La embarcación seleccionada no pertenece a su empresa o no está disponible.');
            }

            // Verificar que la embarcación no esté ya asignada a otro shipment activo
            $existingShipment = \App\Models\Shipment::where('vessel_id', $validated['vessel_id'])
                ->whereHas('voyage', function($query) {
                    $query->whereIn('status', ['planning', 'preparation', 'loading', 'in_transit']);
                })
                ->where('status', '!=', 'completed')
                ->first();

            if ($existingShipment) {
                throw new \Exception('La embarcación seleccionada ya está asignada a otro shipment activo (Viaje: ' . $existingShipment->voyage->voyage_number . ').');
            }

            // Verificar que no se repita el vessel_id en el mismo voyage
            $duplicateVesselInVoyage = \App\Models\Shipment::where('voyage_id', $validated['voyage_id'])
                ->where('vessel_id', $validated['vessel_id'])
                ->exists();

            if ($duplicateVesselInVoyage) {
                throw new \Exception('Esta embarcación ya está asignada a otro shipment en el mismo viaje.');
            }

            // Validar capitán si se proporciona
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

            // Validaciones de lógica de convoy
            if ($validated['vessel_role'] === 'single') {
                // Si es embarcación única, no debe tener posición ni ser líder
                $validated['convoy_position'] = null;
                $validated['is_lead_vessel'] = false;
            } else {
                // Si es parte de convoy, debe tener posición
                if (empty($validated['convoy_position'])) {
                    throw new \Exception('Para embarcaciones en convoy, la posición es requerida.');
                }

                // Verificar que no se repita la posición en el mismo voyage
                if (!empty($validated['convoy_position'])) {
                    $duplicatePosition = \App\Models\Shipment::where('voyage_id', $validated['voyage_id'])
                        ->where('convoy_position', $validated['convoy_position'])
                        ->exists();

                    if ($duplicatePosition) {
                        throw new \Exception('Ya existe otra embarcación en la posición ' . $validated['convoy_position'] . ' del convoy.');
                    }
                }
            }

            // Calcular la secuencia automáticamente
            $maxSequence = \App\Models\Shipment::where('voyage_id', $validated['voyage_id'])
                ->max('sequence_in_voyage');
            $validated['sequence_in_voyage'] = ($maxSequence ?? 0) + 1;

            // Preparar campos adicionales
            $validated['created_by_user_id'] = Auth::id();
            $validated['created_date'] = now();
            $validated['is_lead_vessel'] = $validated['is_lead_vessel'] ?? false;

            // Inicializar campos con valores por defecto
            $validated['cargo_weight_loaded'] = 0;
            $validated['containers_loaded'] = 0;
            $validated['utilization_percentage'] = 0;
            $validated['active'] = true;
            $validated['requires_attention'] = false;
            $validated['has_delays'] = false;
            $validated['safety_approved'] = false;
            $validated['customs_cleared'] = false;
            $validated['documentation_complete'] = false;
            $validated['cargo_inspected'] = false;

            // Crear el shipment
            $shipment = \App\Models\Shipment::create($validated);

            // Si es embarcación líder, actualizar el voyage
            if ($validated['is_lead_vessel']) {
                $voyage->update([
                    'lead_vessel_id' => $validated['vessel_id'],
                    'captain_id' => $validated['captain_id'],
                ]);
            }

            // Recalcular estadísticas del voyage
            $this->recalculateVoyageStats($voyage);

            DB::commit();

            return redirect()->route('company.shipments.show', $shipment)
                ->with('success', 'Carga creada exitosamente. Número: ' . $shipment->shipment_number);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return redirect()->back()
                ->withErrors(['error' => $e->getMessage()])
                ->withInput();
        } catch (\Throwable $e) {
            DB::rollBack();
            
            Log::error('Error al crear shipment', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
                'company_id' => $company->id,
                'request_data' => $request->all()
            ]);
            
            return redirect()->back()
                ->withErrors(['error' => 'Ocurrió un error inesperado. Por favor intente nuevamente.'])
                ->withInput();
        }
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
                    'destinationCountry:id,name,iso_code'
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
     */
    public function edit(Shipment $shipment)
    {
        // Verificar permisos para editar cargas
        if (!$this->canPerform('view_cargas')) {
            abort(403, 'No tiene permisos para editar cargas.');
        }

        if (!$this->hasCompanyRole('Cargas')) {
            abort(403, 'Su empresa no tiene el rol de Cargas.');
        }

        // Verificar que la carga pertenece a la empresa del usuario
        if (!$this->canAccessCompany($shipment->company_id)) {
            abort(403, 'No tiene permisos para editar esta carga.');
        }

        // Verificar si el usuario puede editar esta carga específica
        if ($this->isUser() && $this->isOperator() && $shipment->created_by !== Auth::id()) {
            abort(403, 'No tiene permisos para editar esta carga.');
        }

        // Verificar estado de la carga
        if (in_array($shipment->status, ['completed', 'cancelled'])) {
            return redirect()->route('company.shipments.show', $shipment)
                ->with('error', 'No se puede editar una carga completada o cancelada.');
        }

        $shipment->load(['company', 'voyage', 'containers']);

        return view('company.shipments.edit', compact('shipment'));
    }

    /**
     * Actualizar carga.
     */
    public function update(Request $request, Shipment $shipment)
    {
        // Verificar permisos para editar cargas
        if (!$this->canPerform('view_cargas')) {
            abort(403, 'No tiene permisos para editar cargas.');
        }

        if (!$this->hasCompanyRole('Cargas')) {
            abort(403, 'Su empresa no tiene el rol de Cargas.');
        }

        // Verificar que la carga pertenece a la empresa del usuario
        if (!$this->canAccessCompany($shipment->company_id)) {
            abort(403, 'No tiene permisos para editar esta carga.');
        }

        // Verificar si el usuario puede editar esta carga específica
        if ($this->isUser() && $this->isOperator() && $shipment->created_by !== Auth::id()) {
            abort(403, 'No tiene permisos para editar esta carga.');
        }

        // Verificar estado de la carga
        if (in_array($shipment->status, ['completed', 'cancelled'])) {
            return redirect()->route('company.shipments.show', $shipment)
                ->with('error', 'No se puede editar una carga completada o cancelada.');
        }

        // Validar datos
        $request->validate([
            'shipment_number' => 'required|string|max:255',
            'origin' => 'required|string|max:255',
            'destination' => 'required|string|max:255',
            'weight' => 'required|numeric|min:0',
            'description' => 'required|string',
        ]);

        // Actualizar la carga
        $shipment->update([
            'shipment_number' => $request->shipment_number,
            'origin' => $request->origin,
            'destination' => $request->destination,
            'weight' => $request->weight,
            'description' => $request->description,
            'updated_by' => Auth::id(),
        ]);

        return redirect()->route('company.shipments.show', $shipment)
            ->with('success', 'Carga actualizada exitosamente.');
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
     * Actualizar estado de la carga.
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

        // Verificar que la carga pertenece a la empresa del usuario
        if (!$this->canAccessCompany($shipment->company_id)) {
            abort(403, 'No tiene permisos para modificar esta carga.');
        }

        // Solo company-admin puede cambiar ciertos estados
        $restrictedStatuses = ['completed', 'cancelled'];
        if (in_array($request->status, $restrictedStatuses) && !$this->isCompanyAdmin()) {
            abort(403, 'No tiene permisos para cambiar a este estado.');
        }

        $request->validate([
            'status' => 'required|in:draft,pending,in_transit,completed,cancelled',
            'notes' => 'nullable|string|max:1000',
        ]);

        $shipment->update([
            'status' => $request->status,
            'status_notes' => $request->notes,
            'updated_by' => Auth::id(),
        ]);

        return redirect()->route('company.shipments.show', $shipment)
            ->with('success', 'Estado de la carga actualizado exitosamente.');
    }

    /**
     * Duplicar carga.
     */
    public function duplicate(Shipment $shipment)
    {
        // Verificar permisos para crear cargas
        if (!$this->canPerform('view_cargas')) {
            abort(403, 'No tiene permisos para crear cargas.');
        }

        if (!$this->hasCompanyRole('Cargas')) {
            abort(403, 'Su empresa no tiene el rol de Cargas.');
        }

        // Verificar que la carga pertenece a la empresa del usuario
        if (!$this->canAccessCompany($shipment->company_id)) {
            abort(403, 'No tiene permisos para duplicar esta carga.');
        }

        // Crear nueva carga basada en la existente
        $newShipment = $shipment->replicate();
        $newShipment->shipment_number = $shipment->shipment_number . '-COPY';
        $newShipment->status = 'draft';
        $newShipment->created_by = Auth::id();
        $newShipment->save();

        return redirect()->route('company.shipments.edit', $newShipment)
            ->with('success', 'Carga duplicada exitosamente. Ajuste los datos según sea necesario.');
    }
}
