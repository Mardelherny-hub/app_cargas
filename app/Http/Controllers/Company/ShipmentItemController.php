<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Models\ShipmentItem;
use App\Models\Shipment;
use App\Models\Client;
use App\Models\Country;
use App\Models\CargoType;
use App\Models\PackagingType;
use App\Traits\UserHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * MÓD SHIPMENT ITEMS
 * 
 * Controlador de Items de Shipments para Company Admin y Users
 * Maneja CRUD completo con control de acceso por rol "Cargas"
 * 
 * PERMISOS REQUERIDOS:
 * - Permiso: "view_cargas" 
 * - Rol empresa: "Cargas"
 * - Operadores: Solo sus propios items (via shipment ownership)
 * 
 * ESTADOS EDITABLES: planning, loading
 * JERARQUÍA: Voyages → Shipments → Shipment Items
 */
class ShipmentItemController extends Controller
{
    use UserHelper;

    /**
     * Mostrar formulario para crear nuevo item.
     * CORREGIDO: Soporte para shipment_id (backward compatibility) y bill_of_lading_id
     */
    /**
     * Mostrar formulario para crear nuevo item.
     * MODIFICADO: Preparar datos para componente Livewire
     */

    public function create(Request $request)
    {
        // Verificar permisos para crear items
        if (!$this->canPerform('view_cargas')) {
            abort(403, 'No tiene permisos para crear items de shipments.');
        }

        if (!$this->hasCompanyRole('Cargas')) {
            abort(403, 'Su empresa no tiene el rol de Cargas.');
        }

        $company = $this->getUserCompany();
        $billOfLading = null;
        $shipment = null;
        
        // NUEVO: Flag para indicar si necesita crear BL
        $needsToCreateBL = false;

        // NUEVO: Manejar tanto shipment_id como bill_of_lading_id
        $shipmentId = $request->get('shipment_id') ?: $request->get('shipment');
        $billOfLadingId = $request->get('bill_of_lading_id') ?: $request->get('bill_of_lading');

        if ($billOfLadingId) {
            // Flujo normal: crear item para un bill of lading existente
            $billOfLading = \App\Models\BillOfLading::with(['shipment.voyage'])->findOrFail($billOfLadingId);
            $shipment = $billOfLading->shipment;
            $needsToCreateBL = false;
        } elseif ($shipmentId) {
            // Flujo backward compatible: crear item para un shipment
            $shipment = \App\Models\Shipment::with(['voyage'])->findOrFail($shipmentId);
            
            // Buscar si ya existe un bill of lading para este shipment
            $billOfLading = \App\Models\BillOfLading::where('shipment_id', $shipment->id)->first();
            
            if (!$billOfLading) {
                // MODIFICADO: No crear automáticamente, solo marcar que se necesita
                $needsToCreateBL = true;
            }
        } else {
            return redirect()->route('company.shipments.index')
                ->with('error', 'Debe especificar un shipment o conocimiento de embarque para crear el item.');
        }

        // Validar que el shipment esté en estado editable
        if (!in_array($shipment->status, ['planning', 'loading'])) {
            return redirect()->route('company.shipments.show', $shipment)
                ->with('error', 'No se pueden agregar items a este shipment en su estado actual.');
        }

        // Calcular el próximo número de línea
        $nextLineNumber = 1;
        if ($billOfLading) {
            $maxLineNumber = \App\Models\ShipmentItem::where('bill_of_lading_id', $billOfLading->id)->max('line_number');
            $nextLineNumber = $maxLineNumber ? $maxLineNumber + 1 : 1;
        }

        // Preparar datos por defecto para BL si es necesario
        $defaultBLData = null;
        if ($needsToCreateBL) {
            $defaultBLData = [
                'bill_number' => 'BL-' . date('Y') . '-' . str_pad($shipment->id, 6, '0', STR_PAD_LEFT),
                'loading_port_id' => $shipment->voyage->departure_port_id ?? null,
                'discharge_port_id' => $shipment->voyage->arrival_port_id ?? null,
                'bill_date' => today()->format('Y-m-d'),
                'loading_date' => $shipment->voyage->departure_date ?? today()->format('Y-m-d'),
                'freight_terms' => 'prepaid',
                'payment_terms' => 'cash',
                'currency_code' => 'USD',
                'primary_cargo_type_id' => CargoType::where('active', true)->first()?->id,
                'primary_packaging_type_id' => PackagingType::where('active', true)->first()?->id,
            ];
        }

        // Cargar catálogos necesarios
        $cargoTypes = CargoType::where('active', true)->orderBy('name')->get();
        $packagingTypes = PackagingType::where('active', true)->orderBy('name')->get();
        $clients = Client::where('status', 'active')->orderBy('legal_name')->get();
        $ports = \App\Models\Port::where('active', true)->orderBy('name')->get();
        $countries = \App\Models\Country::orderBy('name')->get();
        
        // NUEVO: Cargar tipos de contenedores para la nueva funcionalidad
        $containerTypes = \App\Models\ContainerType::where('active', true)
            ->orderBy('display_order')
            ->orderBy('name')
            ->get();

        return view('company.shipment-items.create', compact(
            'shipment',
            'billOfLading', 
            'needsToCreateBL',
            'defaultBLData',
            'cargoTypes',
            'packagingTypes',
            'clients',
            'ports',
            'countries',
            'containerTypes', // NUEVO
            'nextLineNumber'
        ));
    }

    /**
     * Almacenar nuevo item.
     * CORREGIDO: Soporte para shipment_id y bill_of_lading_id con debug completo
     */
    public function store(Request $request)
    {
        // DEBUG: Log todos los datos del request
        \Log::info('ShipmentItem Store Debug - Start:', [
            'all_request_data' => $request->all(),
            'user_id' => Auth::id(),
        ]);

        // Verificar permisos para crear items
        if (!$this->canPerform('view_cargas')) {
            \Log::error('Permission denied: view_cargas');
            abort(403, 'No tiene permisos para crear items de shipments.');
        }

        if (!$this->hasCompanyRole('Cargas')) {
            \Log::error('Company role denied: Cargas');
            abort(403, 'Su empresa no tiene el rol de Cargas.');
        }

        \Log::info('Permissions OK, proceeding with validation');

        try {
            // CORREGIDO: Validar datos con soporte para ambos flujos
            $validated = $request->validate([
                // Permitir tanto shipment_id como bill_of_lading_id
                'shipment_id' => 'nullable|exists:shipments,id',
                'bill_of_lading_id' => 'nullable|exists:bills_of_lading,id',
                'cargo_type_id' => 'required|exists:cargo_types,id',
                'packaging_type_id' => 'required|exists:packaging_types,id',
                'line_number' => 'required|integer|min:1',
                'item_reference' => 'nullable|string|max:100',
                'lot_number' => 'nullable|string|max:50',
                'serial_number' => 'nullable|string|max:100',
                'package_quantity' => 'required|integer|min:1',
                'gross_weight_kg' => 'required|numeric|min:0.01',
                'net_weight_kg' => 'nullable|numeric|min:0',
                'volume_m3' => 'nullable|numeric|min:0',
                'declared_value' => 'nullable|numeric|min:0',
                'currency_code' => 'required|string|size:3',
                'item_description' => 'required|string|max:1000',
                'cargo_marks' => 'nullable|string|max:500',
                'commodity_code' => 'nullable|string|max:20',
                'commodity_description' => 'nullable|string|max:255',
                'brand' => 'nullable|string|max:100',
                'model' => 'nullable|string|max:100',
                'manufacturer' => 'nullable|string|max:200',
                'country_of_origin' => 'nullable|string|size:2',
                'package_type_description' => 'nullable|string|max:100',
                'units_per_package' => 'nullable|integer|min:1',
                'unit_of_measure' => 'required|string|max:10',
                'is_dangerous_goods' => 'boolean',
                'un_number' => 'nullable|string|max:10',
                'imdg_class' => 'nullable|string|max:10',
                'is_perishable' => 'boolean',
                'is_fragile' => 'boolean',
                'requires_refrigeration' => 'boolean',
                'temperature_min' => 'nullable|numeric',
                'temperature_max' => 'nullable|numeric',
                'requires_permit' => 'boolean',
                'permit_number' => 'nullable|string|max:50',
                'requires_inspection' => 'boolean',
                'inspection_type' => 'nullable|string|max:100',
            ]);

            \Log::info('Validation passed:', [
                'shipment_id' => $validated['shipment_id'] ?? 'NULL',
                'bill_of_lading_id' => $validated['bill_of_lading_id'] ?? 'NULL',
                'line_number' => $validated['line_number'],
                'item_description' => substr($validated['item_description'], 0, 50) . '...'
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            \Log::error('Validation failed:', [
                'errors' => $e->errors(),
                'request_data' => $request->all()
            ]);
            
            return back()->withErrors($e->errors())->withInput();
        }

        // Validar que se proporcione al menos uno
        if (empty($validated['shipment_id']) && empty($validated['bill_of_lading_id'])) {
            \Log::error('Neither shipment_id nor bill_of_lading_id provided');
            return back()->withErrors(['bill_of_lading_id' => 'Debe especificar un shipment o conocimiento de embarque.'])
                         ->withInput();
        }

        $billOfLading = null;
        $shipment = null;

        try {
            if (!empty($validated['bill_of_lading_id'])) {
                // Flujo normal: usar bill of lading existente
                \Log::info('Using existing bill_of_lading_id: ' . $validated['bill_of_lading_id']);
                $billOfLading = \App\Models\BillOfLading::with(['shipment'])->findOrFail($validated['bill_of_lading_id']);
                $shipment = $billOfLading->shipment;
                \Log::info('Found billOfLading and shipment:', [
                    'bill_id' => $billOfLading->id,
                    'shipment_id' => $shipment->id ?? 'NULL'
                ]);
            } elseif (!empty($validated['shipment_id'])) {
                // Flujo backward compatible: crear/usar bill of lading para shipment
                \Log::info('Using shipment_id: ' . $validated['shipment_id']);
                $shipment = \App\Models\Shipment::with(['voyage'])->findOrFail($validated['shipment_id']);
                
                // Buscar bill of lading existente o crear uno nuevo
                $billOfLading = \App\Models\BillOfLading::where('shipment_id', $shipment->id)->first();
                
                if (!$billOfLading) {
                    \Log::info('Creating default bill of lading for shipment');
                    $billOfLading = $this->createDefaultBillOfLading($shipment);
                    \Log::info('Created bill of lading with ID: ' . $billOfLading->id);
                } else {
                    \Log::info('Found existing bill of lading with ID: ' . $billOfLading->id);
                }
                
                // Actualizar validated data con el bill_of_lading_id correcto
                $validated['bill_of_lading_id'] = $billOfLading->id;
                \Log::info('Updated bill_of_lading_id to: ' . $billOfLading->id);
            }

            // Verificar acceso
            if (!$this->canAccessCompany($shipment->voyage->company_id)) {
                \Log::error('Access denied to company: ' . $shipment->voyage->company_id);
                abort(403, 'No tiene permisos para agregar items a este shipment.');
            }

            \Log::info('Access check passed');

            // Verificar si puede gestionar items
            if (!$this->canManageShipmentItems($shipment)) {
                \Log::error('Cannot manage shipment items for shipment: ' . $shipment->id);
                return redirect()->route('company.shipments.show', $shipment)
                    ->with('error', 'No puede agregar items a este shipment en su estado actual.');
            }

            \Log::info('Can manage items check passed');

            // Verificar que el line_number no esté duplicado en el bill_of_lading
            $existingItem = \App\Models\ShipmentItem::where('bill_of_lading_id', $billOfLading->id)
                                           ->where('line_number', $validated['line_number'])
                                           ->first();

            if ($existingItem) {
                \Log::error('Line number already exists:', [
                    'bill_of_lading_id' => $billOfLading->id,
                    'line_number' => $validated['line_number'],
                    'existing_item_id' => $existingItem->id
                ]);
                return back()->withErrors(['line_number' => 'El número de línea ya existe en este conocimiento de embarque.'])
                             ->withInput();
            }

            \Log::info('Line number check passed');

        } catch (\Exception $e) {
            \Log::error('Error in pre-creation logic: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            return back()->with('error', 'Error en la preparación: ' . $e->getMessage())
                        ->withInput();
        }

        try {
            \Log::info('Starting database transaction');
            DB::beginTransaction();

            // CORREGIDO: Asegurar que bill_of_lading_id esté presente y remover shipment_id
            unset($validated['shipment_id']);
            $validated['bill_of_lading_id'] = $billOfLading->id;

            \Log::info('Creating ShipmentItem with data:', [
                'bill_of_lading_id' => $validated['bill_of_lading_id'],
                'line_number' => $validated['line_number'],
                'item_description' => substr($validated['item_description'], 0, 50) . '...',
                'package_quantity' => $validated['package_quantity'],
                'gross_weight_kg' => $validated['gross_weight_kg']
            ]);

            // Crear el item
            $shipmentItem = \App\Models\ShipmentItem::create([
                ...$validated,
                'status' => $this->getItemStatusFromShipment($shipment),
                'created_date' => now(),
                'created_by_user_id' => Auth::id(),
                'last_updated_date' => now(),
                'last_updated_by_user_id' => Auth::id(),
            ]);

            \Log::info('ShipmentItem created successfully with ID: ' . $shipmentItem->id);

            // Recalcular estadísticas del shipment
            \Log::info('Recalculating shipment stats');
            $shipment->recalculateItemStats();
            \Log::info('Shipment stats recalculated');

            DB::commit();
            \Log::info('Transaction committed successfully');

            \Log::info('Redirecting to shipment show page');
            return redirect()->route('company.shipments.show', $shipment)
                ->with('success', 'Item agregado exitosamente al shipment.');

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error creating shipment item: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'validated_data' => $validated,
                'trace' => $e->getTraceAsString()
            ]);
            
            return back()->with('error', 'Error al crear el item: ' . $e->getMessage())
                        ->withInput();
        }
    }

/**
     * NUEVO: Crear un bill of lading por defecto para un shipment
     */
    private function createDefaultBillOfLading(\App\Models\Shipment $shipment): \App\Models\BillOfLading
    {
        \Log::info('Creating default BillOfLading for shipment: ' . $shipment->id);

        // Obtener el primer cliente activo como shipper por defecto
        $defaultClient = \App\Models\Client::where('status', 'active')->first();
        
        if (!$defaultClient) {
            \Log::error('No active clients found for creating bill of lading');
            throw new \Exception('No hay clientes activos para crear el conocimiento de embarque');
        }

        \Log::info('Using default client: ' . $defaultClient->id . ' - ' . $defaultClient->legal_name);

        try {
            $billOfLading = \App\Models\BillOfLading::create([
                'shipment_id' => $shipment->id,
                'bill_number' => 'BL-' . $shipment->shipment_number . '-' . date('ymd'),
                'shipper_id' => $defaultClient->id,
                'consignee_id' => $defaultClient->id,
                'loading_port_id' => $shipment->voyage->origin_port_id,
                'discharge_port_id' => $shipment->voyage->destination_port_id,
                'primary_cargo_type_id' => \App\Models\CargoType::where('active', true)->first()->id ?? 1,
                'primary_packaging_type_id' => \App\Models\PackagingType::where('active', true)->first()->id ?? 1,
                'bill_date' => now(),
                'loading_date' => now(),
                'freight_terms' => 'prepaid',
                'payment_terms' => 'cash',
                'currency_code' => 'USD',
                'status' => 'draft',
                // CORREGIDO: Agregar campos obligatorios que faltaban
                'total_packages' => 0,
                'gross_weight_kg' => 0.00,
                'net_weight_kg' => 0.00,
                'volume_m3' => 0.00,
                'measurement_unit' => 'KG',
                'container_count' => 0,
                'cargo_description' => 'Pendiente de definir',
                'created_by_user_id' => Auth::id(),
            ]);

            \Log::info('BillOfLading created successfully with ID: ' . $billOfLading->id);
            return $billOfLading;

        } catch (\Exception $e) {
            \Log::error('Error creating default BillOfLading: ' . $e->getMessage(), [
                'shipment_id' => $shipment->id,
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine()
            ]);
            throw $e;
        }
    }


    /**
     * CORREGIDO: Verificar si el usuario puede gestionar items del bill of lading.
     */
    private function canManageBillOfLadingItems(\App\Models\BillOfLading $billOfLading): bool
    {
        // Solo en estados que permiten modificación de items
        if (!in_array($billOfLading->status, ['draft', 'in_progress'])) {
            return false;
        }
        
        // Company admin puede gestionar todos los items
        if ($this->isCompanyAdmin()) {
            return true;
        }
        
        // Users operadores solo pueden gestionar items de sus propios shipments
        if ($this->isUser() && $this->isOperator()) {
            return $billOfLading->shipment->created_by_user_id === Auth::id();
        }
        
        return false;
    }

    /**
     * CORREGIDO: Obtener estado del item basado en el estado del bill of lading.
     */
    private function getItemStatusFromBillOfLading(\App\Models\BillOfLading $billOfLading): string
    {
        return match($billOfLading->status) {
            'draft' => 'draft',
            'in_progress' => 'validated',
            'issued', 'verified' => 'submitted',
            'delivered' => 'accepted',
            default => 'draft'
        };
    }

    /**
     * Mostrar detalles del item.
     */
    public function show(ShipmentItem $shipmentItem)
    {
        // Verificar permisos para ver items
        if (!$this->canPerform('view_cargas')) {
            abort(403, 'No tiene permisos para ver items de shipments.');
        }

        if (!$this->hasCompanyRole('Cargas')) {
            abort(403, 'Su empresa no tiene el rol de Cargas.');
        }

        // Verificar acceso al shipment del item
        if (!$this->canAccessCompany($shipmentItem->shipment->voyage->company_id)) {
            abort(403, 'No tiene permisos para ver este item.');
        }

        // Verificar si es operador y solo puede ver sus propios items
        if ($this->isUser() && $this->isOperator()) {
            if ($shipmentItem->shipment->created_by_user_id !== Auth::id()) {
                abort(403, 'No tiene permisos para ver este item.');
            }
        }

        $shipmentItem->load([
            'billOfLading.shipment.voyage', 
            'billOfLading.shipment.vessel', 
            'cargoType', 
            'packagingType'
        ]);
        return view('company.shipment-items.show', compact('shipmentItem'));
    }
/**
     * Mostrar formulario para editar item.
     */
    public function edit(ShipmentItem $shipmentItem)
    {
        // Verificar permisos para editar items
        if (!$this->canPerform('view_cargas')) {
            abort(403, 'No tiene permisos para editar items de shipments.');
        }

        if (!$this->hasCompanyRole('Cargas')) {
            abort(403, 'Su empresa no tiene el rol de Cargas.');
        }

        // Verificar acceso al shipment del item
        if (!$this->canAccessCompany($shipmentItem->shipment->voyage->company_id)) {
            abort(403, 'No tiene permisos para editar este item.');
        }

        // Verificar si es operador y solo puede editar sus propios items
        if ($this->isUser() && $this->isOperator()) {
            if ($shipmentItem->shipment->created_by_user_id !== Auth::id()) {
                abort(403, 'No tiene permisos para editar este item.');
            }
        }

        $company = $this->getUserCompany();

        // Cargar datos necesarios para el formulario
        $cargoTypes = CargoType::where('active', true)->orderBy('name')->get();
        $packagingTypes = PackagingType::where('active', true)->orderBy('name')->get();
        $clients = Client::where('status', 'active')
                        ->orderBy('legal_name')
                        ->get();
        
        // NUEVO: Cargar tipos de contenedor para la edición
        $containerTypes = \App\Models\ContainerType::where('active', true)
                            ->orderBy('display_order')
                            ->orderBy('name')
                            ->get();

        // NUEVO: Cargar contenedores existentes del item
        $existingContainers = $shipmentItem->containers()->with('containerType')->get();
        
        // NUEVO: Cargar tipos de contenedor para la edición
$containerTypes = \App\Models\ContainerType::where('active', true)
                    ->orderBy('display_order')
                    ->orderBy('name')
                    ->get();

// NUEVO: Cargar contenedores existentes del item
$existingContainers = $shipmentItem->containers()->with('containerType')->get();

// Preparar datos de contenedores para el formulario
$containerData = [];
foreach ($existingContainers as $container) {
    $pivot = $container->pivot;
    $containerData[] = [
        'id' => $container->id,
        'container_number' => $container->container_number,
        'container_type_id' => $container->container_type_id,
        'seal_number' => $container->shipper_seal,
        'tare_weight' => $container->tare_weight_kg,
        'package_quantity' => $pivot->package_quantity,
        'gross_weight_kg' => $pivot->gross_weight_kg,
        'net_weight_kg' => $pivot->net_weight_kg,
        'volume_m3' => $pivot->volume_m3,
        'loading_sequence' => $pivot->loading_sequence,
        'notes' => $pivot->notes,
    ];
}

return view('company.shipment-items.edit', compact(
    'shipmentItem',
    'cargoTypes',
    'packagingTypes',
    'clients',
    'containerTypes',    // AGREGAR ESTA LÍNEA
    'containerData'      // AGREGAR ESTA LÍNEA
));

        return view('company.shipment-items.edit', compact(
            'shipmentItem',
            'cargoTypes',
            'packagingTypes',
            'containerTypes',
            'containerData'
        ));
    }

    /**
     * Actualizar item con múltiples contenedores.
     */
    public function update(Request $request, ShipmentItem $shipmentItem)
    {
        // Verificar permisos para editar items
        if (!$this->canPerform('view_cargas')) {
            abort(403, 'No tiene permisos para editar items de shipments.');
        }

        if (!$this->hasCompanyRole('Cargas')) {
            abort(403, 'Su empresa no tiene el rol de Cargas.');
        }

        // Verificar acceso al shipment del item
        if (!$this->canAccessCompany($shipmentItem->shipment->voyage->company_id)) {
            abort(403, 'No tiene permisos para editar este item.');
        }

        // Verificar si el usuario puede gestionar items de este shipment
        if (!$this->canManageShipmentItems($shipmentItem->shipment)) {
            return redirect()->route('company.shipment-items.show', $shipmentItem)
                ->with('error', 'No puede editar items de este shipment en su estado actual.');
        }

        // Verificar si es operador y solo puede editar sus propios items
        if ($this->isUser() && $this->isOperator()) {
            if ($shipmentItem->shipment->created_by_user_id !== Auth::id()) {
                abort(403, 'No tiene permisos para editar este item.');
            }
        }

        // Reglas de validación básicas
        $rules = [
            'line_number' => 'required|integer|min:1',
            'item_reference' => 'nullable|string|max:100',
            'item_description' => 'required|string|max:1000',
            'cargo_type_id' => 'required|exists:cargo_types,id,active,1',
            'packaging_type_id' => 'required|exists:packaging_types,id,active,1',
            'package_quantity' => 'required|integer|min:1',
            'gross_weight_kg' => 'required|numeric|min:0.01',
            'net_weight_kg' => 'nullable|numeric|min:0',
            'volume_m3' => 'nullable|numeric|min:0',
            'declared_value' => 'nullable|numeric|min:0',
            'currency_code' => 'required|in:USD,ARS,PYG,EUR',
            'unit_of_measure' => 'required|in:PCS,KG,LT,M3,BOX',
            'country_of_origin' => 'nullable|string|size:2',
            'cargo_marks' => 'nullable|string|max:500',
            'commodity_code' => 'nullable|string|max:20',
            'commodity_description' => 'nullable|string|max:255',
            'brand' => 'nullable|string|max:100',
            'model' => 'nullable|string|max:100',
            'manufacturer' => 'nullable|string|max:200',
            'lot_number' => 'nullable|string|max:50',
            'serial_number' => 'nullable|string|max:100',
            
            // Campos booleanos
            'is_dangerous_goods' => 'boolean',
            'is_perishable' => 'boolean',
            'is_fragile' => 'boolean',
            'requires_refrigeration' => 'boolean',
            'requires_permit' => 'boolean',
            'requires_inspection' => 'boolean',
            
            // Campos condicionales
            'un_number' => 'nullable|string|max:10',
            'imdg_class' => 'nullable|string|max:10',
            'temperature_min' => 'nullable|numeric',
            'temperature_max' => 'nullable|numeric',
            'permit_number' => 'nullable|string|max:50',
            'inspection_type' => 'nullable|in:customs,quality,sanitary,security,environmental',
            
            // NUEVO: Validación de contenedores
            'containers' => 'sometimes|array',
            'containers.*.id' => 'nullable|integer',
            'containers.*.container_number' => 'required_with:containers|string|max:20',
            'containers.*.container_type_id' => 'required_with:containers|exists:container_types,id',
            'containers.*.seal_number' => 'nullable|string|max:50',
            'containers.*.tare_weight' => 'nullable|numeric|min:0',
            'containers.*.package_quantity' => 'required_with:containers|integer|min:1',
            'containers.*.gross_weight_kg' => 'required_with:containers|numeric|min:0.01',
            'containers.*.net_weight_kg' => 'nullable|numeric|min:0',
            'containers.*.volume_m3' => 'nullable|numeric|min:0',
            'containers.*.loading_sequence' => 'nullable|string|max:10',
            'containers.*.notes' => 'nullable|string|max:500',
        ];

        $validated = $request->validate($rules);

        // NUEVO: Validar que es carga contenedorizada si hay contenedores
        $isContainerCargo = $this->isContainerizedCargo($validated['cargo_type_id']);
        if (!empty($validated['containers']) && !$isContainerCargo) {
            return redirect()->back()
                ->withInput()
                ->withErrors(['containers' => 'Solo se pueden asignar contenedores a carga contenedorizada.']);
        }

        // NUEVO: Validar totales de contenedores
        if ($isContainerCargo && !empty($validated['containers'])) {
            $totalPackageQuantity = collect($validated['containers'])->sum('package_quantity');
            $totalGrossWeight = collect($validated['containers'])->sum('gross_weight_kg');

            if ($totalPackageQuantity != $validated['package_quantity']) {
                return redirect()->back()
                    ->withInput()
                    ->withErrors(['containers' => "La suma de bultos en contenedores ({$totalPackageQuantity}) debe coincidir con el total del ítem ({$validated['package_quantity']})."]);
            }

            if (abs($totalGrossWeight - $validated['gross_weight_kg']) > 0.01) {
                return redirect()->back()
                    ->withInput()
                    ->withErrors(['containers' => "La suma de peso bruto en contenedores ({$totalGrossWeight}) debe coincidir con el total del ítem ({$validated['gross_weight_kg']})."]);
            }
        }

        try {
            DB::beginTransaction();

            // Actualizar campos del item
            $itemData = [
                'line_number' => $validated['line_number'],
                'item_reference' => $validated['item_reference'],
                'item_description' => $validated['item_description'],
                'cargo_type_id' => $validated['cargo_type_id'],
                'packaging_type_id' => $validated['packaging_type_id'],
                'package_quantity' => $validated['package_quantity'],
                'gross_weight_kg' => $validated['gross_weight_kg'],
                'net_weight_kg' => $validated['net_weight_kg'] ?? 0,
                'volume_m3' => $validated['volume_m3'] ?? 0,
                'declared_value' => $validated['declared_value'] ?? 0,
                'currency_code' => $validated['currency_code'],
                'unit_of_measure' => $validated['unit_of_measure'],
                'country_of_origin' => $validated['country_of_origin'],
                'cargo_marks' => $validated['cargo_marks'],
                'commodity_code' => $validated['commodity_code'],
                'commodity_description' => $validated['commodity_description'],
                'brand' => $validated['brand'],
                'model' => $validated['model'],
                'manufacturer' => $validated['manufacturer'],
                'lot_number' => $validated['lot_number'],
                'serial_number' => $validated['serial_number'],
                'is_dangerous_goods' => $validated['is_dangerous_goods'] ?? false,
                'is_perishable' => $validated['is_perishable'] ?? false,
                'is_fragile' => $validated['is_fragile'] ?? false,
                'requires_refrigeration' => $validated['requires_refrigeration'] ?? false,
                'requires_permit' => $validated['requires_permit'] ?? false,
                'requires_inspection' => $validated['requires_inspection'] ?? false,
                'un_number' => $validated['un_number'],
                'imdg_class' => $validated['imdg_class'],
                'temperature_min' => $validated['temperature_min'],
                'temperature_max' => $validated['temperature_max'],
                'permit_number' => $validated['permit_number'],
                'inspection_type' => $validated['inspection_type'],
                'last_updated_date' => now(),
                'last_updated_by_user_id' => Auth::id(),
            ];

            $shipmentItem->update($itemData);

            // NUEVO: Manejar contenedores solo si es carga contenedorizada
            if ($isContainerCargo) {
                $this->updateItemContainers($shipmentItem, $validated['containers'] ?? []);
            } else {
                // Si ya no es carga contenedorizada, eliminar vínculos existentes
                $this->removeAllContainers($shipmentItem);
            }

            DB::commit();

            Log::info('ShipmentItem actualizado', [
                'item_id' => $shipmentItem->id,
                'containers_count' => count($validated['containers'] ?? []),
                'user_id' => Auth::id()
            ]);

            return redirect()->route('company.shipment-items.show', $shipmentItem)
                ->with('success', 'Item actualizado exitosamente.');

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Error actualizando ShipmentItem: ' . $e->getMessage(), [
                'item_id' => $shipmentItem->id,
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'user_id' => Auth::id()
            ]);

            return redirect()->back()
                ->withInput()
                ->with('error', 'Error al actualizar el item: ' . $e->getMessage());
        }
    }

   

    private function isContainerizedCargo($cargoTypeId)
{
    $cargoType = \App\Models\CargoType::find($cargoTypeId);
    if (!$cargoType) return false;
    
    $name = strtolower($cargoType->name);
    return str_contains($name, 'container') || str_contains($name, 'contenedor');
}

private function updateItemContainers(ShipmentItem $shipmentItem, array $containersData)
{
    // Eliminar contenedores existentes
    $shipmentItem->containers()->detach();
    
    // Crear nuevos contenedores
    foreach ($containersData as $containerData) {
        $container = \App\Models\Container::create([
            'container_number' => $containerData['container_number'],
            'container_type_id' => $containerData['container_type_id'],
            'tare_weight_kg' => $containerData['tare_weight'] ?? 2200,
            'max_gross_weight_kg' => 30000,
            'current_gross_weight_kg' => $containerData['gross_weight_kg'],
            'condition' => 'L',
            'operational_status' => 'loaded',
            'active' => true,
            'created_date' => now(),
            'created_by_user_id' => Auth::id(),
        ]);

        // Vincular con el item
        $shipmentItem->containers()->attach($container->id, [
            'package_quantity' => $containerData['package_quantity'],
            'gross_weight_kg' => $containerData['gross_weight_kg'],
            'net_weight_kg' => $containerData['net_weight_kg'] ?? 0,
            'volume_m3' => $containerData['volume_m3'] ?? 0,
            'created_date' => now(),
            'created_by_user_id' => Auth::id(),
        ]);
    }
}

private function removeAllContainers(ShipmentItem $shipmentItem)
{
    $containerIds = $shipmentItem->containers()->pluck('containers.id')->toArray();
    $shipmentItem->containers()->detach();
    
    // Eliminar contenedores huérfanos
    \App\Models\Container::whereIn('id', $containerIds)
        ->whereDoesntHave('shipmentItems')
        ->delete();
}



    /**
     * Eliminar item.
     */
    public function destroy(ShipmentItem $shipmentItem)
    {
        // Verificar permisos para eliminar items
        if (!$this->canPerform('view_cargas')) {
            abort(403, 'No tiene permisos para eliminar items de shipments.');
        }

        if (!$this->hasCompanyRole('Cargas')) {
            abort(403, 'Su empresa no tiene el rol de Cargas.');
        }

        // Verificar acceso al shipment del item
        if (!$this->canAccessCompany($shipmentItem->shipment->voyage->company_id)) {
            abort(403, 'No tiene permisos para eliminar este item.');
        }

        // Verificar si el usuario puede gestionar items de este shipment
        if (!$this->canManageShipmentItems($shipmentItem->shipment)) {
            return redirect()->route('company.shipment-items.show', $shipmentItem)
                ->with('error', 'No puede eliminar items de este shipment en su estado actual.');
        }

        // Solo company-admin puede eliminar items
        if (!$this->isCompanyAdmin()) {
            abort(403, 'Solo el administrador de empresa puede eliminar items.');
        }

        try {
            DB::beginTransaction();

            $shipment = $shipmentItem->shipment;
            $shipmentItem->delete();

            // Recalcular estadísticas del shipment
            $shipment->recalculateItemStats();

            DB::commit();

            return redirect()->route('company.shipments.show', $shipment)
                ->with('success', 'Item eliminado exitosamente.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error eliminando shipment item: ' . $e->getMessage());
            
            return back()->with('error', 'Error al eliminar el item. Intente nuevamente.');
        }
    }

    /**
     * Duplicar item.
     */
    public function duplicate(ShipmentItem $shipmentItem)
    {
        // Verificar permisos para crear items
        if (!$this->canPerform('view_cargas')) {
            abort(403, 'No tiene permisos para duplicar items de shipments.');
        }

        if (!$this->hasCompanyRole('Cargas')) {
            abort(403, 'Su empresa no tiene el rol de Cargas.');
        }

        // Verificar acceso al shipment del item
        if (!$this->canAccessCompany($shipmentItem->shipment->voyage->company_id)) {
            abort(403, 'No tiene permisos para duplicar este item.');
        }

        // Verificar si el usuario puede gestionar items de este shipment
        if (!$this->canManageShipmentItems($shipmentItem->shipment)) {
            return redirect()->route('company.shipment-items.show', $shipmentItem)
                ->with('error', 'No puede duplicar items de este shipment en su estado actual.');
        }

        try {
            DB::beginTransaction();

            // Obtener el siguiente line_number disponible
            $nextLineNumber = $shipmentItem->shipment->shipmentItems()->max('line_number') + 1;

            // Crear item duplicado
            $newItem = $shipmentItem->replicate();
            $newItem->line_number = $nextLineNumber;
            $newItem->item_reference = $shipmentItem->item_reference ? $shipmentItem->item_reference . '-COPY' : null;
            $newItem->status = 'draft';
            $newItem->created_date = now();
            $newItem->created_by_user_id = Auth::id();
            $newItem->last_updated_date = now();
            $newItem->last_updated_by_user_id = Auth::id();
            $newItem->save();

            // Recalcular estadísticas del shipment
            $shipmentItem->shipment->recalculateItemStats();

            DB::commit();

            return redirect()->route('company.shipment-items.edit', $newItem)
                ->with('success', 'Item duplicado exitosamente. Ajuste los datos según sea necesario.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error duplicando shipment item: ' . $e->getMessage());
            
            return back()->with('error', 'Error al duplicar el item. Intente nuevamente.');
        }
    }

    /**
     * Cambiar estado del item.
     */
    public function toggleStatus(Request $request, ShipmentItem $shipmentItem)
    {
        // Verificar permisos básicos
        if (!$this->canPerform('view_cargas')) {
            abort(403, 'No tiene permisos para modificar items de shipments.');
        }

        if (!$this->hasCompanyRole('Cargas')) {
            abort(403, 'Su empresa no tiene el rol de Cargas.');
        }

        // Verificar acceso al shipment del item
        if (!$this->canAccessCompany($shipmentItem->shipment->voyage->company_id)) {
            abort(403, 'No tiene permisos para modificar este item.');
        }

        $request->validate([
            'status' => 'required|in:draft,validated,submitted,accepted,rejected',
            'notes' => 'nullable|string|max:500',
        ]);

        try {
            $shipmentItem->update([
                'status' => $request->status,
                'requires_review' => $request->status === 'rejected',
                'discrepancy_notes' => $request->notes,
                'last_updated_date' => now(),
                'last_updated_by_user_id' => Auth::id(),
            ]);

            return redirect()->route('company.shipment-items.show', $shipmentItem)
                ->with('success', 'Estado del item actualizado exitosamente.');

        } catch (\Exception $e) {
            Log::error('Error actualizando estado de shipment item: ' . $e->getMessage());
            
            return back()->with('error', 'Error al actualizar el estado. Intente nuevamente.');
        }
    }

    /**
     * Búsqueda de items.
     */
    public function search(Request $request)
    {
        // Verificar permisos
        if (!$this->canPerform('view_cargas')) {
            abort(403, 'No tiene permisos para buscar items de shipments.');
        }

        if (!$this->hasCompanyRole('Cargas')) {
            abort(403, 'Su empresa no tiene el rol de Cargas.');
        }

        $company = $this->getUserCompany();
        $query = $request->get('q', '');

        if (strlen($query) < 2) {
            return response()->json([]);
        }

        // CORREGIDO: Usar relación a través de billOfLading
        $itemsQuery = ShipmentItem::whereHas('billOfLading.shipment.voyage', function($q) use ($company) {
            $q->where('company_id', $company->id);
        });

        // Aplicar filtros de ownership para operadores
        if ($this->isUser() && $this->isOperator()) {
            $itemsQuery->whereHas('billOfLading.shipment', function($q) {
                $q->where('created_by_user_id', Auth::id());
            });
        }

        $items = $itemsQuery
            ->where(function($q) use ($query) {
                $q->where('item_reference', 'LIKE', "%{$query}%")
                  ->orWhere('item_description', 'LIKE', "%{$query}%")
                  ->orWhere('commodity_code', 'LIKE', "%{$query}%")
                  ->orWhere('lot_number', 'LIKE', "%{$query}%")
                  ->orWhere('serial_number', 'LIKE', "%{$query}%");
            })
            ->with(['billOfLading.shipment:id,shipment_number', 'cargoType:id,name'])
            ->limit(10)
            ->get(['id', 'item_reference', 'item_description', 'commodity_code', 'bill_of_lading_id', 'cargo_type_id']);

        return response()->json($items->map(function($item) {
            return [
                'id' => $item->id,
                'text' => $item->item_reference . ' - ' . $item->item_description,
                'shipment' => $item->billOfLading->shipment->shipment_number,
                'cargo_type' => $item->cargoType->name,
                'url' => route('company.shipment-items.show', $item),
            ];
        }));
    }

    // MÉTODOS PRIVADOS DE UTILIDAD

    /**
     * Verificar si el usuario puede gestionar items del shipment.
     * Copiado de ShipmentController para tener acceso local.
     */
    private function canManageShipmentItems(Shipment $shipment): bool
    {
        // Solo en estados que permiten modificación de items
        if (!in_array($shipment->status, ['planning', 'loading'])) {
            return false;
        }
        
        // Company admin puede gestionar todos los items
        if ($this->isCompanyAdmin()) {
            return true;
        }
        
        // Users operadores solo pueden gestionar items de sus propios shipments
        if ($this->isUser() && $this->isOperator()) {
            return $shipment->created_by_user_id === Auth::id();
        }
        
        return false;
    }

    /**
     * Obtener estado del item basado en el estado del shipment.
     */
    private function getItemStatusFromShipment(Shipment $shipment): string
    {
        return match($shipment->status) {
            'planning' => 'draft',
            'loading' => 'validated',
            'loaded', 'in_transit' => 'submitted',
            'arrived', 'discharging' => 'accepted',
            'completed' => 'accepted',
            default => 'draft'
        };
    }

    /**
 * NUEVO: Crear un bill of lading con datos específicos (usado por Livewire)
 * Método público para ser llamado desde el componente
 */
public function createBillOfLadingWithData(\App\Models\Shipment $shipment, array $blData): \App\Models\BillOfLading
{
    \Log::info('Creating BillOfLading with specific data for shipment: ' . $shipment->id);

    try {
        // Validar datos mínimos requeridos
        $requiredFields = ['shipper_id', 'consignee_id', 'loading_port_id', 'discharge_port_id', 
                          'primary_cargo_type_id', 'primary_packaging_type_id'];
        
        foreach ($requiredFields as $field) {
            if (empty($blData[$field])) {
                throw new \Exception("Campo requerido faltante: {$field}");
            }
        }

        $billOfLading = \App\Models\BillOfLading::create([
            'shipment_id' => $shipment->id,
            'bill_number' => $blData['bill_number'] ?? 'BL-' . $shipment->shipment_number . '-' . date('ymd'),
            'shipper_id' => $blData['shipper_id'],
            'consignee_id' => $blData['consignee_id'],
            'notify_party_id' => $blData['notify_party_id'] ?? null, // ← AQUÍ está el fix!
            'loading_port_id' => $blData['loading_port_id'],
            'discharge_port_id' => $blData['discharge_port_id'],
            'primary_cargo_type_id' => $blData['primary_cargo_type_id'],
            'primary_packaging_type_id' => $blData['primary_packaging_type_id'],
            'bill_date' => $blData['bill_date'] ?? now(),
            'loading_date' => $blData['loading_date'] ?? now(),
            'freight_terms' => $blData['freight_terms'] ?? 'prepaid',
            'payment_terms' => $blData['payment_terms'] ?? 'cash',
            'currency_code' => $blData['currency_code'] ?? 'USD',
            'status' => 'draft',
            'total_packages' => 0,
            'gross_weight_kg' => 0.00,
            'net_weight_kg' => 0.00,
            'volume_m3' => 0.00,
            'measurement_unit' => 'KG',
            'container_count' => 0,
            'cargo_description' => $blData['cargo_description'] ?? 'Pendiente de definir',
            'created_by_user_id' => Auth::id(),
        ]);

        return $billOfLading;

    } catch (\Exception $e) {
        \Log::error('Error creating BillOfLading with data: ' . $e->getMessage());
        throw $e;
    }
}
}