<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Country;
use App\Models\DocumentType;
use App\Models\Port;
use App\Models\CustomOffice;
use App\Models\Company;
use App\Http\Requests\CreateClientRequest;
use App\Http\Requests\UpdateClientRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * FASE 1 - MÓDULO EMPRESAS Y CLIENTES
 *
 * Controlador Admin para gestión completa de clientes
 * CORRECCIÓN CRÍTICA: client_type → client_roles (múltiples roles)
 */
class ClientController extends Controller
{
    /**
     * Listar clientes con filtros y búsqueda.
     */
    public function index(Request $request)
    {
        $query = Client::with([
            'country:id,name,alpha2_code',
            'documentType:id,name',
            'primaryPort:id,name',
            'customOffice:id,name',
            'createdByCompany:id,legal_name',
            'primaryContact'
        ]);

        // Búsqueda por texto
        if ($request->filled('search')) {
            $search = trim($request->get('search'));
            $query->where(function($q) use ($search) {
                $q->where('legal_name', 'like', "%{$search}%")
                  ->orWhere('tax_id', 'like', "%{$search}%")
                  ->orWhereHas('primaryContact', function($contact) use ($search) {
                      $contact->where('email', 'like', "%{$search}%");
                  });
            });
        }

        // CORRECCIÓN: Filtro por roles de cliente (JSON)
        if ($request->filled('client_role')) {
            $role = $request->get('client_role');
            $validRoles = ['shipper', 'consignee', 'notify_party'];
            if (in_array($role, $validRoles)) {
                $query->whereJsonContains('client_roles', $role);
            }
        }

        // Filtro por país
        if ($request->filled('country_id')) {
            $query->where('country_id', $request->get('country_id'));
        }

        // Filtro por estado
        if ($request->filled('status')) {
            $query->where('status', $request->get('status'));
        }

        // Filtro por verificación
        if ($request->filled('verified')) {
            if ($request->get('verified') === 'yes') {
                $query->whereNotNull('verified_at');
            } else {
                $query->whereNull('verified_at');
            }
        }

        // Ordenamiento
        $sortBy = $request->get('sort', 'updated_at');
        $sortOrder = $request->get('order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Paginación
        $clients = $query->paginate(25);

        // Datos auxiliares
        $countries = Country::where('active', true)->orderBy('name')->get();
        $companies = Company::where('active', true)->orderBy('legal_name')->get();
        
        // Estadísticas básicas
        $stats = [
            'total' => Client::count(),
            'verified' => Client::whereNotNull('verified_at')->count(),
            'pending' => Client::whereNull('verified_at')->count(),
            'inactive' => Client::where('status', 'inactive')->count(),
            // CORRECCIÓN: Estadísticas por roles (JSON)
            'by_role' => [
                'shipper' => Client::whereJsonContains('client_roles', 'shipper')->count(),
                'consignee' => Client::whereJsonContains('client_roles', 'consignee')->count(),
                'notify_party' => Client::whereJsonContains('client_roles', 'notify_party')->count(),
            ],
        ];

        return view('admin.clients.index', compact('clients', 'countries', 'companies', 'stats'));
    }

    /**
     * Mostrar formulario de creación.
     */
    public function create()
    {
        $countries = Country::where('active', true)->orderBy('name')->get();
        $documentTypes = DocumentType::where('active', true)->orderBy('name')->get();
        $ports = Port::where('active', true)->orderBy('name')->get();
        $customOffices = CustomOffice::where('active', true)->orderBy('name')->get();

        return view('admin.clients.create', compact('countries', 'documentTypes', 'ports', 'customOffices'));
    }

    /**
     * Crear nuevo cliente.
     * CORRECCIÓN: Usa CreateClientRequest con client_roles
     */
    public function store(CreateClientRequest $request)
    {
        try {
            DB::beginTransaction();

            // Los datos ya vienen validados del FormRequest
            $validatedData = $request->validated();

            // Limpiar CUIT/RUC
            $validatedData['tax_id'] = preg_replace('/[^0-9]/', '', $validatedData['tax_id']);

            // Crear cliente
            $client = Client::create($validatedData);

            // Crear múltiples contactos si se proporcionan
            if ($request->has('contacts') && is_array($request->contacts)) {
                $this->createMultipleContacts($client, $request->contacts);
            }

            DB::commit();

            return redirect()
                ->route('admin.clients.show', $client)
                ->with('success', 'Cliente creado exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Error creating client', [
                'data' => $request->validated(),
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            return back()
                ->withInput()
                ->withErrors(['error' => 'Error al crear cliente: ' . $e->getMessage()]);
        }
    }

    /**
     * Mostrar cliente específico.
     */
    public function show(Client $client)
    {
        $client->load([
            'country', 
            'documentType', 
            'primaryPort',
            'customOffice',
            'createdByCompany',
            'contactData' => function($query) {
                $query->where('active', true)->orderBy('is_primary', 'desc');
            }
        ]);

        return view('admin.clients.show', compact('client'));
    }

    /**
     * Mostrar formulario de edición.
     */
    public function edit(Client $client)
    {
        $countries = Country::where('active', true)->orderBy('name')->get();
        $documentTypes = DocumentType::where('active', true)->orderBy('name')->get();
        $ports = Port::where('active', true)->orderBy('name')->get();
        $customOffices = CustomOffice::where('active', true)->orderBy('name')->get();

        // Cargar contactos para edición
        $client->load(['contactData' => function($query) {
            $query->where('active', true)->orderBy('is_primary', 'desc')->orderBy('contact_type');
        }]);

        return view('admin.clients.edit', compact('client', 'countries', 'documentTypes', 'ports', 'customOffices'));
    }

    /**
     * Actualizar cliente.
     * CORRECCIÓN: Usa UpdateClientRequest con client_roles
     */
    public function update(UpdateClientRequest $request, Client $client)
    {
        try {
            DB::beginTransaction();

            // Los datos ya vienen validados del FormRequest
            $validatedData = $request->validated();

            // Limpiar CUIT/RUC si se proporciona
            if (isset($validatedData['tax_id'])) {
                $validatedData['tax_id'] = preg_replace('/[^0-9]/', '', $validatedData['tax_id']);
            }
            
            // Actualizar cliente
            $client->update($validatedData);

            // Actualizar múltiples contactos
            if ($request->has('contacts') && is_array($request->contacts)) {
                $this->updateMultipleContacts($client, $request->contacts);
            }

            DB::commit();

            return redirect()
                ->route('admin.clients.show', $client)
                ->with('success', 'Cliente actualizado exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Error updating client', [
                'client_id' => $client->id,
                'data' => $request->validated(),
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            return back()
                ->withInput()
                ->withErrors(['error' => 'Error al actualizar cliente: ' . $e->getMessage()]);
        }
    }

    /**
     * Eliminar cliente.
     */
    public function destroy(Client $client)
    {
        try {
            // Solo cambiar estado, no eliminar físicamente
            $client->update(['status' => 'inactive']);

            return redirect()
                ->route('admin.clients.index')
                ->with('success', 'Cliente desactivado exitosamente');

        } catch (\Exception $e) {
            Log::error('Error deleting client', [
                'client_id' => $client->id,
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            return back()->with('error', 'Error al desactivar cliente');
        }
    }

    /**
     * Verificar cliente CUIT/RUC.
     */
    public function verify(Client $client)
    {
        $client->update(['verified_at' => now()]);
        
        return back()->with('success', 'Cliente verificado exitosamente');
    }

    /**
     * Cambiar estado del cliente.
     */
    public function toggleStatus(Client $client)
    {
        $newStatus = $client->status === 'active' ? 'inactive' : 'active';
        $client->update(['status' => $newStatus]);
        
        return back()->with('success', 'Estado del cliente actualizado');
    }

    /**
     * Transferir cliente (placeholder).
     */
    public function transfer(Client $client)
    {
        return back()->with('info', 'Funcionalidad de transferencia en desarrollo');
    }

    /**
     * Importación masiva (placeholder).
     */
    public function bulkImport(Request $request)
    {
        $request->validate([
            'import_file' => 'required|file|mimes:csv,xlsx,xls|max:10240',
            'import_type' => 'required|in:clients,client_data'
        ]);

        return back()->with('info', 'Funcionalidad de importación masiva en desarrollo');
    }

    /**
     * Búsqueda AJAX para autocompletado.
     * CORRECCIÓN: Busca en client_roles (JSON)
     */
    public function search(Request $request)
    {
        $query = $request->get('q', '');
        
        if (strlen($query) < 2) {
            return response()->json([]);
        }

        $clients = Client::where('status', 'active')
            ->where(function($q) use ($query) {
                $q->where('legal_name', 'like', "%{$query}%")
                  ->orWhere('tax_id', 'like', "%{$query}%");
            })
            ->with('country:id,name')
            ->limit(10)
            ->get(['id', 'legal_name', 'tax_id', 'client_roles', 'country_id']);

        return response()->json($clients);
    }

    /**
     * API Helper: Obtener datos para formularios.
     * CORRECCIÓN: Devuelve client_roles disponibles
     */
    public function getFormData()
    {
        return response()->json([
            'client_roles' => Client::getClientRoleOptions(),
            'statuses' => Client::getStatusOptions(),
            'countries' => Country::where('active', true)->get(['id', 'name']),
            'document_types' => DocumentType::where('active', true)->get(['id', 'name']),
            'ports' => Port::where('active', true)->get(['id', 'name']),
            'customs_offices' => CustomOffice::where('active', true)->get(['id', 'name']),
        ]);
    }

    /**
     * Crear múltiples contactos para un cliente.
     */
    private function createMultipleContacts(Client $client, array $contacts): void
    {
        $hasPrimary = false;
        
        foreach ($contacts as $index => $contactData) {
            // Validar que tenga al menos email o teléfono
            if (empty($contactData['email']) && empty($contactData['phone']) && empty($contactData['mobile_phone'])) {
                continue;
            }
            
            // Solo un contacto puede ser primario
            $isPrimary = !$hasPrimary && ($contactData['is_primary'] ?? $index === 0);
            if ($isPrimary) {
                $hasPrimary = true;
            }
            
            $client->contactData()->create([
                'contact_type' => $contactData['contact_type'] ?? 'general',
                'email' => $contactData['email'] ?? null,
                'phone' => $contactData['phone'] ?? null,
                'mobile_phone' => $contactData['mobile_phone'] ?? null,
                'address_line_1' => $contactData['address_line_1'] ?? null,
                'address_line_2' => $contactData['address_line_2'] ?? null,
                'city' => $contactData['city'] ?? null,
                'state_province' => $contactData['state_province'] ?? null,
                'contact_person_name' => $contactData['contact_person_name'] ?? null,
                'contact_person_position' => $contactData['contact_person_position'] ?? null,
                'notes' => $contactData['notes'] ?? null,
                'is_primary' => $isPrimary,
                'active' => true,
                'created_by_user_id' => auth()->id(),
            ]);
        }
    }

    /**
     * Actualizar múltiples contactos para un cliente.
     */
    private function updateMultipleContacts(Client $client, array $contacts): void
    {
        $hasPrimary = false;
        
        foreach ($contacts as $index => $contactData) {
            // Validar que tenga al menos email o teléfono
            if (empty($contactData['email']) && empty($contactData['phone']) && empty($contactData['mobile_phone'])) {
                continue;
            }
            
            // Solo un contacto puede ser primario
            $isPrimary = !$hasPrimary && ($contactData['is_primary'] ?? $index === 0);
            if ($isPrimary) {
                $hasPrimary = true;
            }
            
            $contactAttributes = [
                'contact_type' => $contactData['contact_type'] ?? 'general',
                'email' => $contactData['email'] ?? null,
                'phone' => $contactData['phone'] ?? null,
                'mobile_phone' => $contactData['mobile_phone'] ?? null,
                'address_line_1' => $contactData['address_line_1'] ?? null,
                'address_line_2' => $contactData['address_line_2'] ?? null,
                'city' => $contactData['city'] ?? null,
                'state_province' => $contactData['state_province'] ?? null,
                'contact_person_name' => $contactData['contact_person_name'] ?? null,
                'contact_person_position' => $contactData['contact_person_position'] ?? null,
                'notes' => $contactData['notes'] ?? null,
                'is_primary' => $isPrimary,
                'active' => true,
                'updated_by_user_id' => auth()->id(),
            ];
            
            if (!empty($contactData['id'])) {
                // Actualizar contacto existente
                $client->contactData()->where('id', $contactData['id'])->update($contactAttributes);
            } else {
                // Crear nuevo contacto
                $contactAttributes['created_by_user_id'] = auth()->id();
                $client->contactData()->create($contactAttributes);
            }
        }
    }
}