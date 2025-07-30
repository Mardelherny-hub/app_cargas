<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Models\Shipment;
use App\Traits\UserHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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
    public function create()
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

        // Datos adicionales para el formulario
        $data = [
            'company' => $company,
            'canManageAll' => $this->isCompanyAdmin(),
            'userInfo' => $this->getUserInfo(),
        ];

        return view('company.shipments.create', $data);
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

        // Validación básica
        $validated = $request->validate([
            'voyage_id' => 'required|exists:voyages,id',
            'vessel_id' => 'required|exists:vessels,id',
            'shipment_number' => 'required|string|max:50',
            'cargo_capacity_tons' => 'required|numeric|min:0',
            'container_capacity' => 'required|integer|min:0',
            // Agregar más validaciones según necesidad
        ]);

        // CORREGIDO: Verificar que el voyage pertenezca a la empresa
        $voyage = \App\Models\Voyage::where('id', $validated['voyage_id'])
            ->where('company_id', $company->id)
            ->first();

        if (!$voyage) {
            return redirect()->back()
                ->withErrors(['voyage_id' => 'El viaje seleccionado no pertenece a su empresa.'])
                ->withInput();
        }

        // Crear el shipment
        $validated['created_by_user_id'] = Auth::id();
        $validated['created_date'] = now();
        $validated['status'] = 'planning';

        $shipment = \App\Models\Shipment::create($validated);

        return redirect()->route('company.shipments.show', $shipment)
            ->with('success', 'Carga creada exitosamente.');
    }

    /**
     * Mostrar carga específica.
     */
    public function show(\App\Models\Shipment $shipment)
    {
        // Verificar que la carga pertenezca a la empresa del usuario
        $company = $this->getUserCompany();
        
        if (!$company || $shipment->voyage->company_id !== $company->id) {
            abort(404, 'Carga no encontrada.');
        }

        // Cargar relaciones necesarias
        $shipment->load([
            'voyage',
            'vessel',
            'captain',
            'shipmentItems.cargoType',
            'shipmentItems.packagingType',
            'billsOfLading'
        ]);

        return view('company.shipments.show', compact('shipment'));
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
