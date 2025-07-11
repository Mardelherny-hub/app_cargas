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

        // Construir consulta base
        $query = Shipment::where('company_id', $company->id);

        // Aplicar filtros adicionales según el rol
        if ($this->isUser() && $this->isOperator()) {
            // Los usuarios operadores solo ven sus propias cargas
            $query->where('created_by', Auth::id());
        }

        $shipments = $query->with(['trip', 'containers'])
            ->latest()
            ->paginate(20);

        return view('company.shipments.index', compact('shipments'));
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

        // Validar datos
        $request->validate([
            'shipment_number' => 'required|string|max:255',
            'origin' => 'required|string|max:255',
            'destination' => 'required|string|max:255',
            'weight' => 'required|numeric|min:0',
            'description' => 'required|string',
            // Agregar más validaciones según necesidades
        ]);

        // Crear la carga
        $shipment = Shipment::create([
            'company_id' => $company->id,
            'shipment_number' => $request->shipment_number,
            'origin' => $request->origin,
            'destination' => $request->destination,
            'weight' => $request->weight,
            'description' => $request->description,
            'created_by' => Auth::id(),
            'status' => 'draft',
        ]);

        return redirect()->route('company.shipments.show', $shipment)
            ->with('success', 'Carga creada exitosamente.');
    }

    /**
     * Mostrar detalles de una carga.
     */
    public function show(Shipment $shipment)
    {
        // Verificar permisos para ver cargas
        if (!$this->canPerform('view_cargas')) {
            abort(403, 'No tiene permisos para ver cargas.');
        }

        if (!$this->hasCompanyRole('Cargas')) {
            abort(403, 'Su empresa no tiene el rol de Cargas.');
        }

        // Verificar que la carga pertenece a la empresa del usuario
        if (!$this->canAccessCompany($shipment->company_id)) {
            abort(403, 'No tiene permisos para ver esta carga.');
        }

        // Verificar si el usuario puede ver esta carga específica
        if ($this->isUser() && $this->isOperator() && $shipment->created_by !== Auth::id()) {
            abort(403, 'No tiene permisos para ver esta carga.');
        }

        $shipment->load(['company', 'trip', 'containers', 'attachments']);

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

        $shipment->load(['company', 'trip', 'containers']);

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
