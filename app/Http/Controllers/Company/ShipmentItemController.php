<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Models\ShipmentItem;
use App\Models\Shipment;
use App\Models\Client;
use App\Models\CargoType;
use App\Models\PackagingType;
use App\Traits\UserHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * MÓDULO 3: VIAJES Y CARGAS - SHIPMENT ITEMS
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

        // Obtener shipment_id del query parameter
        $shipmentId = $request->get('shipment_id') ?: $request->get('shipment');
        
        if (!$shipmentId) {
            return redirect()->route('company.shipments.index')
                ->with('error', 'Debe especificar un shipment para crear el item.');
        }

        $shipment = Shipment::findOrFail($shipmentId);

        // Verificar acceso al shipment
        if (!$this->canAccessCompany($shipment->voyage->company_id)) {
            abort(403, 'No tiene permisos para agregar items a este shipment.');
        }

        $company = $this->getUserCompany();

        // Debug: Verificar datos del shipment
        \Log::info('Debug ShipmentItem Create:', [
            'shipment_id' => $shipment->id,
            'shipment_status' => $shipment->status,
            'company_id' => $shipment->voyage->company_id ?? 'N/A',
            'user_company_id' => $company->id ?? 'N/A',
            'user_id' => Auth::id(),
            'shipment_created_by' => $shipment->created_by_user_id,
            'can_manage_result' => $this->canManageShipmentItems($shipment),
        ]);

        // Verificar si el usuario puede gestionar items de este shipment
        if (!$this->canManageShipmentItems($shipment)) {
            return redirect()->route('company.shipments.show', $shipment)
                ->with('error', 'No puede agregar items a este shipment en su estado actual.');
        }

        $company = $this->getUserCompany();

        // Cargar datos necesarios para el formulario
        $cargoTypes = CargoType::where('active', true)->orderBy('name')->get();
        $packagingTypes = PackagingType::where('active', true)->orderBy('name')->get();
        $clients = Client::where('status', 'active')
                        ->orderBy('legal_name')
                        ->get();

        // Obtener el siguiente line_number
        $nextLineNumber = $shipment->shipmentItems()->max('line_number') + 1;

        return view('company.shipment-items.create', compact(
            'shipment',
            'cargoTypes',
            'packagingTypes',
            'clients',
            'nextLineNumber'
        ));
    }

    /**
     * Almacenar nuevo item.
     */
    public function store(Request $request)
    {
        // Verificar permisos para crear items
        if (!$this->canPerform('view_cargas')) {
            abort(403, 'No tiene permisos para crear items de shipments.');
        }

        if (!$this->hasCompanyRole('Cargas')) {
            abort(403, 'Su empresa no tiene el rol de Cargas.');
        }

        $shipment = Shipment::findOrFail($request->shipment_id);

        // Verificar acceso al shipment
        if (!$this->canAccessCompany($shipment->company_id)) {
            abort(403, 'No tiene permisos para agregar items a este shipment.');
        }

        // Verificar si el usuario puede gestionar items de este shipment
        if (!$this->canManageShipmentItems($shipment)) {
            return redirect()->route('company.shipments.show', $shipment)
                ->with('error', 'No puede agregar items a este shipment en su estado actual.');
        }

        // Validar datos
        $validated = $request->validate([
            'shipment_id' => 'required|exists:shipments,id',
            'client_id' => 'nullable|exists:clients,id',
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
            'country_of_origin' => 'nullable|string|size:3',
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

        // Verificar que el line_number no esté duplicado en el shipment
        $existingItem = ShipmentItem::where('shipment_id', $shipment->id)
                                   ->where('line_number', $validated['line_number'])
                                   ->first();

        if ($existingItem) {
            return back()->withErrors(['line_number' => 'El número de línea ya existe en este shipment.'])
                        ->withInput();
        }

        try {
            DB::beginTransaction();

            // Crear el item
            $shipmentItem = ShipmentItem::create([
                ...$validated,
                'status' => $this->getItemStatusFromShipment($shipment),
                'created_date' => now(),
                'created_by_user_id' => Auth::id(),
                'last_updated_date' => now(),
                'last_updated_by_user_id' => Auth::id(),
            ]);

            // Recalcular estadísticas del shipment
            $shipment->recalculateItemStats();

            DB::commit();

            return redirect()->route('company.shipments.show', $shipment)
                ->with('success', 'Item agregado exitosamente al shipment.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creando shipment item: ' . $e->getMessage());
            
            return back()->with('error', 'Error al crear el item. Intente nuevamente.')
                        ->withInput();
        }
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
            'shipment.voyage.company',
            'shipment.vessel',
            'client',
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

        $company = $this->getUserCompany();

        // Cargar datos necesarios para el formulario
        $cargoTypes = CargoType::where('active', true)->orderBy('name')->get();
        $packagingTypes = PackagingType::where('active', true)->orderBy('name')->get();
        $clients = Client::where('status', 'active')
                        ->orderBy('legal_name')
                        ->get();

        $shipmentItem->load(['shipment.voyage', 'shipment.vessel', 'client', 'cargoType', 'packagingType']);

        return view('company.shipment-items.edit', compact(
            'shipmentItem',
            'cargoTypes',
            'packagingTypes',
            'clients'
        ));
    }

    /**
     * Actualizar item.
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

        // Validar datos (mismas reglas que store)
        $validated = $request->validate([
            'client_id' => 'nullable|exists:clients,id',
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
            'country_of_origin' => 'nullable|string|size:3',
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

        // Verificar que el line_number no esté duplicado (excepto el item actual)
        $existingItem = ShipmentItem::where('shipment_id', $shipmentItem->shipment_id)
                                   ->where('line_number', $validated['line_number'])
                                   ->where('id', '!=', $shipmentItem->id)
                                   ->first();

        if ($existingItem) {
            return back()->withErrors(['line_number' => 'El número de línea ya existe en este shipment.'])
                        ->withInput();
        }

        try {
            DB::beginTransaction();

            // Actualizar el item
            $shipmentItem->update([
                ...$validated,
                'last_updated_date' => now(),
                'last_updated_by_user_id' => Auth::id(),
            ]);

            // Recalcular estadísticas del shipment
            $shipmentItem->shipment->recalculateItemStats();

            DB::commit();

            return redirect()->route('company.shipment-items.show', $shipmentItem)
                ->with('success', 'Item actualizado exitosamente.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error actualizando shipment item: ' . $e->getMessage());
            
            return back()->with('error', 'Error al actualizar el item. Intente nuevamente.')
                        ->withInput();
        }
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

        $itemsQuery = ShipmentItem::whereHas('shipment', function($q) use ($company) {
            $q->whereHas('voyage', function($vq) use ($company) {
                $vq->where('company_id', $company->id);
            });
        });

        // Aplicar filtros de ownership para operadores
        if ($this->isUser() && $this->isOperator()) {
            $itemsQuery->whereHas('shipment', function($q) {
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
            ->with(['shipment:id,shipment_number', 'cargoType:id,name'])
            ->limit(10)
            ->get(['id', 'item_reference', 'item_description', 'commodity_code', 'shipment_id', 'cargo_type_id']);

        return response()->json($items->map(function($item) {
            return [
                'id' => $item->id,
                'text' => $item->item_reference . ' - ' . $item->item_description,
                'shipment' => $item->shipment->shipment_number,
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
}