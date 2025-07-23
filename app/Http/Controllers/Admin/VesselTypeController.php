<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\VesselType;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;

/**
 * MÓDULO 3: VIAJES Y CARGAS - ADMIN VESSEL TYPES
 *
 * Controller para administración de tipos de embarcación
 * Acceso: Solo super-admin (tabla de referencia del sistema)
 * 
 * Funcionalidades:
 * - CRUD completo con validaciones robustas
 * - Duplicar tipos existentes
 * - Importar desde CSV
 * - Acciones masivas
 * - Filtros avanzados
 */
class VesselTypeController extends Controller
{  
    /**
     * Display a listing of vessel types for admin.
     */
    public function index(Request $request)
    {
        $query = VesselType::with(['createdByUser', 'vessels']);

        // Filtros
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('code', 'LIKE', "%{$search}%")
                  ->orWhere('short_name', 'LIKE', "%{$search}%")
                  ->orWhere('description', 'LIKE', "%{$search}%");
            });
        }

        if ($request->filled('category')) {
            $query->byCategory($request->category);
        }

        if ($request->filled('propulsion_type')) {
            $query->where('propulsion_type', $request->propulsion_type);
        }

        if ($request->filled('status')) {
            if ($request->status === 'active') {
                $query->active();
            } elseif ($request->status === 'inactive') {
                $query->where('active', false);
            }
        }

        if ($request->filled('type_filter')) {
            if ($request->type_filter === 'common') {
                $query->common();
            } elseif ($request->type_filter === 'specialized') {
                $query->specialized();
            }
        }

        if ($request->filled('cargo_capability')) {
            $capability = $request->cargo_capability;
            switch ($capability) {
                case 'containers':
                    $query->handlesContainers();
                    break;
                case 'bulk_cargo':
                    $query->handlesBulkCargo();
                    break;
                case 'liquid_cargo':
                    $query->where('handles_liquid_cargo', true);
                    break;
                case 'dangerous_goods':
                    $query->where('handles_dangerous_goods', true);
                    break;
                case 'passengers':
                    $query->where('handles_passengers', true);
                    break;
            }
        }

        if ($request->filled('navigation_type')) {
            $navType = $request->navigation_type;
            if ($navType === 'river') {
                $query->riverNavigation();
            } elseif ($navType === 'maritime') {
                $query->maritimeNavigation();
            }
        }

        if ($request->filled('convoy_capability')) {
            if ($request->convoy_capability === 'lead') {
                $query->canLead();
            } elseif ($request->convoy_capability === 'convoy') {
                $query->canBeInConvoy();
            }
        }

        // Ordenamiento
        $sortBy = $request->get('sort_by', 'display_order');
        $sortOrder = $request->get('sort_order', 'asc');
        
        // Validar campos de ordenamiento
        $allowedSorts = [
            'display_order', 'name', 'code', 'category', 'propulsion_type', 
            'created_at', 'min_deadweight', 'max_deadweight'
        ];
        if (!in_array($sortBy, $allowedSorts)) {
            $sortBy = 'display_order';
        }
        
        if ($sortBy === 'display_order') {
            $query->ordered();
        } else {
            $query->orderBy($sortBy, $sortOrder);
        }

        $vesselTypes = $query->paginate(15)->withQueryString();

        // Datos para filtros
        $filterData = [
            'categories' => VesselType::CATEGORIES,
            'propulsion_types' => VesselType::PROPULSION_TYPES,
            'cargo_capabilities' => VesselType::CARGO_CAPABILITIES,
            'navigation_types' => VesselType::NAVIGATION_TYPES,
        ];

        // Estadísticas generales
        $stats = [
            'total' => VesselType::count(),
            'active' => VesselType::active()->count(),
            'inactive' => VesselType::where('active', false)->count(),
            'common' => VesselType::common()->count(),
            'specialized' => VesselType::specialized()->count(),
            'by_category' => [
                'barge' => VesselType::byCategory('barge')->count(),
                'tugboat' => VesselType::byCategory('tugboat')->count(),
                'self_propelled' => VesselType::byCategory('self_propelled')->count(),
                'pusher' => VesselType::byCategory('pusher')->count(),
                'mixed' => VesselType::byCategory('mixed')->count(),
            ],
        ];

        return view('admin.vessel-types.index', compact(
            'vesselTypes',
            'filterData',
            'stats'
        ));
    }

    /**
     * Show the form for creating a new vessel type.
     */
    public function create()
    {
        $formData = $this->getFormData();
        
        return view('admin.vessel-types.create', compact('formData'));
    }

    /**
     * Store a newly created vessel type.
     */
    public function store(Request $request)
    {
        $validated = $this->validateVesselType($request);

        DB::beginTransaction();

        try {
            $validated['created_by_user_id'] = Auth::id();
            $validated['created_date'] = Carbon::now();
            $validated['active'] = $request->boolean('active', true);
            $validated['is_common'] = $request->boolean('is_common', false);
            $validated['is_specialized'] = $request->boolean('is_specialized', false);

            // Establecer display_order si no se proporciona
            if (!isset($validated['display_order'])) {
                $validated['display_order'] = VesselType::max('display_order') + 10;
            }

            $vesselType = VesselType::create($validated);

            DB::commit();

            return redirect()
                ->route('admin.vessel-types.show', $vesselType)
                ->with('success', 'Tipo de embarcación creado exitosamente.');
                
        } catch (\Exception $e) {
            DB::rollBack();
            
            return back()
                ->withInput()
                ->withErrors(['error' => 'Error al crear el tipo de embarcación: ' . $e->getMessage()]);
        }
    }

    /**
     * Display the specified vessel type.
     */
    public function show(VesselType $vesselType)
    {
        $vesselType->load([
            'createdByUser',
            'vessels' => function ($query) {
                $query->with(['company', 'vesselOwner'])->latest();
            }
        ]);

        // Estadísticas específicas del tipo
        $stats = [
            'total_vessels' => $vesselType->vessels->count(),
            'active_vessels' => $vesselType->vessels->where('status', 'active')->count(),
            'companies_using' => $vesselType->vessels->pluck('company_id')->unique()->count(),
            'avg_deadweight' => $vesselType->vessels->avg('deadweight_tons') ?? 0,
        ];

        // Capacidades de carga agrupadas
        $cargoCapabilities = [
            'containers' => $vesselType->handles_containers,
            'bulk_cargo' => $vesselType->handles_bulk_cargo,
            'liquid_cargo' => $vesselType->handles_liquid_cargo,
            'general_cargo' => $vesselType->handles_general_cargo,
            'dangerous_goods' => $vesselType->handles_dangerous_goods,
            'passengers' => $vesselType->handles_passengers,
        ];

        // Características de navegación
        $navigationFeatures = [
            'river_navigation' => $vesselType->river_navigation,
            'maritime_navigation' => $vesselType->maritime_navigation,
            'can_be_lead_vessel' => $vesselType->can_be_lead_vessel,
            'can_be_in_convoy' => $vesselType->can_be_in_convoy,
        ];

        return view('admin.vessel-types.show', compact(
            'vesselType',
            'stats',
            'cargoCapabilities',
            'navigationFeatures'
        ));
    }

    /**
     * Show the form for editing the specified vessel type.
     */
    public function edit(VesselType $vesselType)
    {
        $formData = $this->getFormData();
        
        return view('admin.vessel-types.edit', compact('vesselType', 'formData'));
    }

    /**
     * Update the specified vessel type.
     */
    public function update(Request $request, VesselType $vesselType)
    {
        $validated = $this->validateVesselType($request, $vesselType->id);

        DB::beginTransaction();

        try {
            $validated['active'] = $request->boolean('active');
            $validated['is_common'] = $request->boolean('is_common');
            $validated['is_specialized'] = $request->boolean('is_specialized');

            $vesselType->update($validated);

            DB::commit();

            return redirect()
                ->route('admin.vessel-types.show', $vesselType)
                ->with('success', 'Tipo de embarcación actualizado exitosamente.');
                
        } catch (\Exception $e) {
            DB::rollBack();
            
            return back()
                ->withInput()
                ->withErrors(['error' => 'Error al actualizar el tipo de embarcación: ' . $e->getMessage()]);
        }
    }

    /**
     * Remove the specified vessel type.
     */
    public function destroy(VesselType $vesselType)
    {
        // Verificar si tiene embarcaciones asociadas
        if ($vesselType->vessels()->exists()) {
            return back()->withErrors([
                'error' => 'No se puede eliminar un tipo de embarcación que tiene embarcaciones asociadas.'
            ]);
        }

        try {
            $vesselType->delete();

            return redirect()
                ->route('admin.vessel-types.index')
                ->with('success', 'Tipo de embarcación eliminado exitosamente.');
                
        } catch (\Exception $e) {
            return back()->withErrors([
                'error' => 'Error al eliminar el tipo de embarcación: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Toggle the status of the vessel type.
     */
    public function toggleStatus(VesselType $vesselType)
    {
        $newStatus = !$vesselType->active;
        
        $vesselType->update([
            'active' => $newStatus,
            'updated_at' => Carbon::now(),
        ]);

        $statusText = $newStatus ? 'activado' : 'desactivado';

        return back()->with('success', "Tipo de embarcación {$statusText} exitosamente.");
    }

    /**
     * Duplicate an existing vessel type.
     */
    public function duplicate(Request $request, VesselType $vesselType)
    {
        $request->validate([
            'new_code' => 'required|string|max:50|unique:vessel_types,code',
            'new_name' => 'required|string|max:200',
            'copy_specifications' => 'boolean',
            'copy_capabilities' => 'boolean',
        ]);

        DB::beginTransaction();

        try {
            $attributes = $vesselType->toArray();
            
            // Remover campos que no deben duplicarse
            unset($attributes['id'], $attributes['created_at'], $attributes['updated_at']);
            
            // Establecer nuevos valores
            $attributes['code'] = $request->new_code;
            $attributes['name'] = $request->new_name;
            $attributes['short_name'] = $request->new_name;
            $attributes['description'] = "Duplicado de: {$vesselType->name}";
            $attributes['created_by_user_id'] = Auth::id();
            $attributes['created_date'] = Carbon::now();
            $attributes['display_order'] = VesselType::max('display_order') + 10;
            $attributes['is_common'] = false; // Los duplicados inician como no comunes
            
            // Si no se copian especificaciones, usar valores por defecto
            if (!$request->boolean('copy_specifications')) {
                $specificationFields = [
                    'min_length', 'max_length', 'min_beam', 'max_beam',
                    'min_draft', 'max_draft', 'min_deadweight', 'max_deadweight',
                    'crew_capacity', 'passenger_capacity', 'fuel_capacity_liters',
                    'max_speed_knots', 'service_speed_knots', 'max_convoy_size',
                    'typical_lifespan_years', 'maintenance_interval_months',
                    'dry_dock_interval_months'
                ];
                
                foreach ($specificationFields as $field) {
                    $attributes[$field] = null;
                }
            }
            
            // Si no se copian capacidades, usar valores por defecto
            if (!$request->boolean('copy_capabilities')) {
                $capabilityFields = [
                    'handles_containers', 'handles_bulk_cargo', 'handles_liquid_cargo',
                    'handles_general_cargo', 'handles_dangerous_goods', 'handles_passengers'
                ];
                
                foreach ($capabilityFields as $field) {
                    $attributes[$field] = false;
                }
            }

            $duplicatedType = VesselType::create($attributes);

            DB::commit();

            return redirect()
                ->route('admin.vessel-types.show', $duplicatedType)
                ->with('success', "Tipo de embarcación duplicado exitosamente como '{$request->new_name}'.");
                
        } catch (\Exception $e) {
            DB::rollBack();
            
            return back()->withErrors([
                'error' => 'Error al duplicar el tipo de embarcación: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Import vessel types from CSV.
     */
    public function importFromCsv(Request $request)
    {
        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt|max:2048',
            'skip_header' => 'boolean',
            'update_existing' => 'boolean',
        ]);

        try {
            $file = $request->file('csv_file');
            $content = file_get_contents($file->getPathname());
            $lines = explode("\n", $content);
            
            if ($request->boolean('skip_header')) {
                array_shift($lines); // Remover primera línea (header)
            }
            
            $imported = 0;
            $updated = 0;
            $errors = [];
            
            DB::beginTransaction();
            
            foreach ($lines as $index => $line) {
                $line = trim($line);
                if (empty($line)) continue;
                
                $data = str_getcsv($line);
                
                // Validar que tenga al menos los campos mínimos
                if (count($data) < 3) {
                    $errors[] = "Línea " . ($index + 1) . ": Datos insuficientes";
                    continue;
                }
                
                try {
                    $vesselTypeData = [
                        'code' => $data[0] ?? '',
                        'name' => $data[1] ?? '',
                        'category' => $data[2] ?? 'barge',
                        'propulsion_type' => $data[3] ?? 'pushed',
                        'description' => $data[4] ?? null,
                        'min_deadweight' => is_numeric($data[5] ?? null) ? (float)$data[5] : null,
                        'max_deadweight' => is_numeric($data[6] ?? null) ? (float)$data[6] : null,
                        'handles_containers' => isset($data[7]) ? filter_var($data[7], FILTER_VALIDATE_BOOLEAN) : false,
                        'handles_bulk_cargo' => isset($data[8]) ? filter_var($data[8], FILTER_VALIDATE_BOOLEAN) : false,
                        'river_navigation' => isset($data[9]) ? filter_var($data[9], FILTER_VALIDATE_BOOLEAN) : true,
                        'maritime_navigation' => isset($data[10]) ? filter_var($data[10], FILTER_VALIDATE_BOOLEAN) : false,
                        'active' => isset($data[11]) ? filter_var($data[11], FILTER_VALIDATE_BOOLEAN) : true,
                        'created_by_user_id' => Auth::id(),
                        'created_date' => Carbon::now(),
                        'display_order' => (VesselType::max('display_order') ?? 0) + 10 + $imported,
                    ];
                    
                    // Verificar si ya existe
                    $existing = VesselType::where('code', $vesselTypeData['code'])->first();
                    
                    if ($existing) {
                        if ($request->boolean('update_existing')) {
                            $existing->update($vesselTypeData);
                            $updated++;
                        } else {
                            $errors[] = "Línea " . ($index + 1) . ": Código '{$vesselTypeData['code']}' ya existe";
                        }
                    } else {
                        VesselType::create($vesselTypeData);
                        $imported++;
                    }
                    
                } catch (\Exception $e) {
                    $errors[] = "Línea " . ($index + 1) . ": " . $e->getMessage();
                }
            }
            
            DB::commit();
            
            $message = "Importación completada: {$imported} tipos creados, {$updated} actualizados.";
            
            if (!empty($errors)) {
                $errorMessage = "Errores encontrados:\n" . implode("\n", array_slice($errors, 0, 5));
                if (count($errors) > 5) {
                    $errorMessage .= "\n... y " . (count($errors) - 5) . " errores más.";
                }
                
                return back()
                    ->with('warning', $message)
                    ->withErrors(['import' => $errorMessage]);
            }
            
            return back()->with('success', $message);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            return back()->withErrors([
                'error' => 'Error durante la importación: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Bulk action handler.
     */
    public function bulkAction(Request $request)
    {
        $validated = $request->validate([
            'action' => 'required|in:activate,deactivate,delete,mark_common,mark_specialized',
            'vessel_type_ids' => 'required|array|min:1',
            'vessel_type_ids.*' => 'exists:vessel_types,id',
        ]);

        $vesselTypes = VesselType::whereIn('id', $validated['vessel_type_ids']);
        $count = $vesselTypes->count();

        try {
            switch ($validated['action']) {
                case 'activate':
                    $vesselTypes->update(['active' => true]);
                    $message = "{$count} tipos de embarcación activados.";
                    break;
                    
                case 'deactivate':
                    $vesselTypes->update(['active' => false]);
                    $message = "{$count} tipos de embarcación desactivados.";
                    break;
                    
                case 'mark_common':
                    $vesselTypes->update(['is_common' => true]);
                    $message = "{$count} tipos marcados como comunes.";
                    break;
                    
                case 'mark_specialized':
                    $vesselTypes->update(['is_specialized' => true]);
                    $message = "{$count} tipos marcados como especializados.";
                    break;
                    
                case 'delete':
                    // Verificar que ninguno tenga embarcaciones asociadas
                    $withVessels = $vesselTypes->whereHas('vessels')->count();
                    if ($withVessels > 0) {
                        return back()->withErrors([
                            'error' => "No se pueden eliminar {$withVessels} tipos que tienen embarcaciones asociadas."
                        ]);
                    }
                    
                    $vesselTypes->delete();
                    $message = "{$count} tipos de embarcación eliminados.";
                    break;
            }

            return back()->with('success', $message);
            
        } catch (\Exception $e) {
            return back()->withErrors([
                'error' => 'Error al ejecutar la acción masiva: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Validate vessel type data.
     */
    private function validateVesselType(Request $request, ?int $ignoreId = null): array
    {
        $rules = [
            'code' => ['required', 'string', 'max:50', Rule::unique('vessel_types')->ignore($ignoreId)],
            'name' => 'required|string|max:200',
            'short_name' => 'nullable|string|max:100',
            'description' => 'nullable|string|max:1000',
            'category' => ['required', Rule::in(array_keys(VesselType::CATEGORIES))],
            'propulsion_type' => ['required', Rule::in(array_keys(VesselType::PROPULSION_TYPES))],
            
            // Dimensiones
            'min_length' => 'nullable|numeric|min:0|max:500',
            'max_length' => 'nullable|numeric|min:0|max:500|gte:min_length',
            'min_beam' => 'nullable|numeric|min:0|max:100',
            'max_beam' => 'nullable|numeric|min:0|max:100|gte:min_beam',
            'min_draft' => 'nullable|numeric|min:0|max:50',
            'max_draft' => 'nullable|numeric|min:0|max:50|gte:min_draft',
            'min_deadweight' => 'nullable|numeric|min:0|max:50000',
            'max_deadweight' => 'nullable|numeric|min:0|max:50000|gte:min_deadweight',
            
            // Capacidades
            'crew_capacity' => 'nullable|integer|min:0|max:200',
            'passenger_capacity' => 'nullable|integer|min:0|max:1000',
            'fuel_capacity_liters' => 'nullable|numeric|min:0|max:1000000',
            'max_speed_knots' => 'nullable|numeric|min:0|max:50',
            'service_speed_knots' => 'nullable|numeric|min:0|max:50|lte:max_speed_knots',
            'max_convoy_size' => 'nullable|integer|min:1|max:20',
            
            // Especificaciones técnicas
            'engine_configuration' => 'nullable|string|max:500',
            'construction_materials' => 'nullable|string|max:500',
            'typical_lifespan_years' => 'nullable|integer|min:1|max:100',
            'environmental_standards' => 'nullable|string|max:500',
            'regulatory_requirements' => 'nullable|string|max:500',
            'maintenance_interval_months' => 'nullable|integer|min:1|max:60',
            'dry_dock_interval_months' => 'nullable|integer|min:6|max:120',
            'display_order' => 'nullable|integer|min:0|max:9999',
            'icon' => 'nullable|string|max:100',
            'color_code' => 'nullable|string|max:7|regex:/^#[0-9A-Fa-f]{6}$/',
            
            // Campos booleanos se manejan automáticamente
        ];

        return $request->validate($rules);
    }

    /**
     * Get form data for create/edit views.
     */
    private function getFormData(): array
    {
        return [
            'categories' => VesselType::CATEGORIES,
            'propulsion_types' => VesselType::PROPULSION_TYPES,
            'cargo_capabilities' => VesselType::CARGO_CAPABILITIES,
            'navigation_types' => VesselType::NAVIGATION_TYPES,
            'users' => User::orderBy('name')->pluck('name', 'id'),
        ];
    }
}