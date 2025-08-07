<?php

namespace App\Http\Controllers\Company\Manifests;

use App\Http\Controllers\Controller;
use App\Traits\UserHelper;
use Illuminate\Http\Request;
use App\Models\Voyage;
use App\Models\Shipment;
use App\Models\BillOfLading;
use App\Models\Port;
use App\Models\Country;

class ManifestController extends Controller
{
    use UserHelper;

    /**
     * Mostrar listado de manifiestos agrupados por viaje.
     */
    public function index(Request $request)
    {
        // 1. Verificar permisos y obtener empresa
        if (!$this->canPerform('view_reports') && !$this->hasCompanyRole('Cargas')) {
            abort(403, 'No tiene permisos para ver manifiestos. Se requiere rol "Cargas".');
        }

        $company = $this->getUserCompany();
        if (!$company) {
            return redirect()->route('company.dashboard')
                ->with('error', 'No se encontró la empresa asociada.');
        }

        // 2. Obtener voyages de la empresa del usuario CON relaciones de puertos
        $voyages = Voyage::with([
            'shipments.billsOfLading',
            'originPort:id,name,code,country_id',  // Solo campos necesarios
            'destinationPort:id,name,code,country_id',
            'originPort.country:id,name,alpha2_code',
            'destinationPort.country:id,name,alpha2_code'
        ])
        ->where('company_id', $company->id)
        ->latest()
        ->paginate(20);

        return view('company.manifests.index', [
            'voyages' => $voyages,
            'company' => $company
        ]);
    }

    /**
     * Formulario para crear un nuevo manifiesto manualmente.
     */
    public function create()
    {
        // 1. Verificar permisos
        if (!$this->canPerform('manage_settings') && !$this->hasCompanyRole('Cargas')) {
            abort(403, 'No tiene permisos para crear manifiestos. Se requiere rol "Cargas".');
        }

        $company = $this->getUserCompany();
        if (!$company) {
            return redirect()->route('company.dashboard')
                ->with('error', 'No se encontró la empresa asociada.');
        }

        // 2. Obtener países de Argentina y Paraguay
        $argentina = Country::where('alpha2_code', 'AR')->first();
        $paraguay = Country::where('alpha2_code', 'PY')->first();

        if (!$argentina || !$paraguay) {
            return redirect()->route('company.manifests.index')
                ->with('error', 'Error de configuración: países no encontrados en el sistema.');
        }

        // 3. Obtener puertos activos de ambos países
        $ports = Port::active()
            ->whereIn('country_id', [$argentina->id, $paraguay->id])
            ->with('country')
            ->orderBy('country_id')
            ->orderBy('display_order')
            ->orderBy('name')
            ->get();

        return view('company.manifests.create', [
            'company' => $company,
            'ports' => $ports,
            'argentina' => $argentina,
            'paraguay' => $paraguay
        ]);
    }

    /**
     * Almacenar manifiesto manual nuevo.
     */
    public function store(Request $request)
    {
        // 1. Verificar permisos
        if (!$this->canPerform('manage_settings') && !$this->hasCompanyRole('Cargas')) {
            abort(403, 'No tiene permisos para crear manifiestos. Se requiere rol "Cargas".');
        }

        $company = $this->getUserCompany();
        if (!$company) {
            return redirect()->route('company.dashboard')
                ->with('error', 'No se encontró la empresa asociada.');
        }

        // 2. Validar datos de entrada
        $data = $request->validate([
            'voyage_number' => [
                'required',
                'string',
                'max:50',
                'regex:/^[A-Z0-9\-_]+$/i',
                function ($attribute, $value, $fail) use ($company) {
                    // Verificar unicidad dentro de la empresa
                    $exists = Voyage::where('company_id', $company->id)
                        ->where('voyage_number', $value)
                        ->exists();
                    
                    if ($exists) {
                        $fail('El número de viaje ya existe en su empresa.');
                    }
                },
            ],
            'origin_port_id' => 'required|exists:ports,id',
            'destination_port_id' => 'required|exists:ports,id|different:origin_port_id',
        ], [
            'voyage_number.required' => 'El número de viaje es obligatorio.',
            'voyage_number.regex' => 'El número de viaje solo puede contener letras, números, guiones y guiones bajos.',
            'origin_port_id.required' => 'Debe seleccionar el puerto de origen.',
            'origin_port_id.exists' => 'El puerto de origen seleccionado no es válido.',
            'destination_port_id.required' => 'Debe seleccionar el puerto de destino.',
            'destination_port_id.exists' => 'El puerto de destino seleccionado no es válido.',
            'destination_port_id.different' => 'El puerto de destino debe ser diferente al puerto de origen.',
        ]);

        // 3. Verificar que los puertos estén activos
        $originPort = Port::active()->find($data['origin_port_id']);
        $destinationPort = Port::active()->find($data['destination_port_id']);

        if (!$originPort || !$destinationPort) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Uno o ambos puertos seleccionados no están disponibles.');
        }

        // 4. Agregar datos adicionales para el voyage
        $voyageData = array_merge($data, [
            'company_id' => $company->id,
            'created_by_user_id' => $this->getCurrentUser()->id,
            'status' => 'planning', // Estado inicial
            'origin_country_id' => $originPort->country_id,
            'destination_country_id' => $destinationPort->country_id,
            'voyage_type' => 'single_vessel', 
            'cargo_type' => 'export', 
            'priority_level' => 'normal', 
            'created_date' => now(),
        ]);

        try {
            // 5. Crear el voyage
            $voyage = Voyage::create($voyageData);

            // 6. Log de la operación
            \Log::info('Manifiesto creado manualmente', [
                'voyage_id' => $voyage->id,
                'voyage_number' => $voyage->voyage_number,
                'company_id' => $company->id,
                'user_id' => $this->getCurrentUser()->id,
                'origin_port' => $originPort->name,
                'destination_port' => $destinationPort->name,
            ]);

            return redirect()->route('company.manifests.show', $voyage->id)
                ->with('success', "Manifiesto '{$voyage->voyage_number}' creado exitosamente. Ahora puede agregar embarques.");

        } catch (\Exception $e) {
            \Log::error('Error creando manifiesto', [
                'error' => $e->getMessage(),
                'company_id' => $company->id,
                'user_id' => $this->getCurrentUser()->id,
                'voyage_number' => $data['voyage_number'],
            ]);

            return redirect()->back()
                ->withInput()
                ->with('error', 'Error creando el manifiesto: ' . $e->getMessage());
        }
    }

    /**
     * Mostrar detalle completo del manifiesto.
     */
    public function show($id)
    {
        // 1. Verificar permisos
        if (!$this->canPerform('view_reports') && !$this->hasCompanyRole('Cargas')) {
            abort(403, 'No tiene permisos para ver manifiestos.');
        }

        $company = $this->getUserCompany();
        if (!$company) {
            return redirect()->route('company.dashboard')
                ->with('error', 'No se encontró la empresa asociada.');
        }

        // 2. Obtener voyage con sus relaciones
        $voyage = Voyage::with([
            'shipments.billsOfLading.shipper',
            'shipments.vessel',
            'originPort.country',
            'destinationPort.country',
            'company'
        ])
        ->where('company_id', $company->id)
        ->findOrFail($id);

        return view('company.manifests.show', [
            'voyage' => $voyage,
            'company' => $company
        ]);
    }

    /**
     * Editar manifiesto existente (solo si está en estado editable).
     */
    public function edit($id)
    {
        // 1. Verificar permisos
        if (!$this->canPerform('manage_settings') && !$this->hasCompanyRole('Cargas')) {
            abort(403, 'No tiene permisos para editar manifiestos.');
        }

        $company = $this->getUserCompany();
        if (!$company) {
            return redirect()->route('company.dashboard')
                ->with('error', 'No se encontró la empresa asociada.');
        }

        // 2. Obtener voyage editable
        $voyage = Voyage::where('company_id', $company->id)
            ->where('status', 'planning')
            ->with(['originPort', 'destinationPort'])
            ->findOrFail($id);

        // 3. Obtener puertos disponibles
        $argentina = Country::where('alpha2_code', 'AR')->first();
        $paraguay = Country::where('alpha2_code', 'PY')->first();

        $ports = Port::active()
            ->whereIn('country_id', [$argentina->id, $paraguay->id])
            ->with('country')
            ->orderBy('country_id')
            ->orderBy('name')
            ->get();

        return view('company.manifests.edit', [
            'voyage' => $voyage,
            'company' => $company,
            'ports' => $ports
        ]);
    }

    /**
     * Actualizar datos del manifiesto.
     */
    public function update(Request $request, $id)
    {
        // 1. Verificar permisos
        if (!$this->canPerform('manage_settings') && !$this->hasCompanyRole('Cargas')) {
            abort(403, 'No tiene permisos para editar manifiestos.');
        }

        $company = $this->getUserCompany();
        if (!$company) {
            return redirect()->route('company.dashboard')
                ->with('error', 'No se encontró la empresa asociada.');
        }

        // 2. Obtener voyage editable
        $voyage = Voyage::where('company_id', $company->id)
            ->where('status', 'planning')
            ->findOrFail($id);

        // 3. Validar datos
        $data = $request->validate([
            'origin_port_id' => 'required|exists:ports,id',
            'destination_port_id' => 'required|exists:ports,id|different:origin_port_id',
        ]);

        // 4. Actualizar voyage
        $voyage->update([
            'origin_port_id' => $data['origin_port_id'],
            'destination_port_id' => $data['destination_port_id'],
            'last_updated_by_user_id' => $this->getCurrentUser()->id,
        ]);

        return redirect()->route('company.manifests.show', $voyage->id)
            ->with('success', 'Manifiesto actualizado exitosamente.');
    }

    /**
     * Eliminar manifiesto (solo si está vacío y en planificación).
     */
    public function destroy($id)
    {
        // 1. Verificar permisos
        if (!$this->canPerform('manage_settings') && !$this->hasCompanyRole('Cargas')) {
            abort(403, 'No tiene permisos para eliminar manifiestos.');
        }

        $company = $this->getUserCompany();
        if (!$company) {
            return redirect()->route('company.dashboard')
                ->with('error', 'No se encontró la empresa asociada.');
        }

        // 2. Obtener voyage eliminable
        $voyage = Voyage::where('company_id', $company->id)
            ->where('status', 'planning')
            ->doesntHave('shipments')
            ->findOrFail($id);

        $voyageNumber = $voyage->voyage_number;
        $voyage->delete();

        return redirect()->route('company.manifests.index')
            ->with('success', "Manifiesto '{$voyageNumber}' eliminado exitosamente.");
    }
}