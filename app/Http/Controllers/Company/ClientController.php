<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Country;
use App\Models\DocumentType;
use App\Models\Port;
use App\Models\CustomOffice;
use App\Http\Requests\CreateClientRequest; // CORRECCIÓN: Agregado
use App\Http\Requests\UpdateClientRequest; // CORRECCIÓN: Agregado
use App\Traits\UserHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Log;

/**
 * Controlador de Clientes para Company Admin y Users
 * Maneja la base compartida de clientes (sin propietarios)
 * CORRECCIÓN: Soporte para client_roles (JSON array)
 */
class ClientController extends Controller
{
    use UserHelper;
    use AuthorizesRequests;

    /**
     * Listar clientes de la base compartida
     */
    public function index(Request $request)
    {
        // Verificar autorización usando policy
        $this->authorize('viewAny', Client::class);
        
        // Query base - solo clientes activos de la base compartida
        $query = Client::with([
            'country:id,name,iso_code', 
            'documentType:id,name',
            'createdByCompany:id,legal_name'
        ])
        ->where('status', 'active')
        ->whereNotNull('verified_at');

        // Filtros de búsqueda
        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function($q) use ($search) {
                $q->where('legal_name', 'like', "%{$search}%")
                  ->orWhere('commercial_name', 'like', "%{$search}%")
                  ->orWhere('tax_id', 'like', "%{$search}%");
            });
        }

        if ($request->filled('country_id')) {
            $query->where('country_id', $request->get('country_id'));
        }

        if ($request->filled('document_type_id')) {
            $query->where('document_type_id', $request->get('document_type_id'));
        }       

        // Paginación
        $clients = $query->orderBy('legal_name')->paginate(25);

        // Datos auxiliares para filtros
        $countries = Country::where('active', true)->orderBy('name')->get();
        $documentTypes = DocumentType::where('active', true)->orderBy('name')->get();
        
        // Estadísticas básicas
        $stats = [
            'total' => Client::where('status', 'active')->count(),
            'verified' => Client::where('status', 'active')->whereNotNull('verified_at')->count(),
            'recent' => Client::where('status', 'active')->where('created_at', '>=', now()->subDays(30))->count(),
        ];

        return view('company.clients.index', compact('clients', 'countries', 'documentTypes','stats'));
    }

    /**
     * Mostrar formulario de creación (solo company-admin)
     */
    public function create()
    {
        $this->authorize('create', Client::class);
        
        $countries = Country::where('active', true)->orderBy('name')->get();
        $documentTypes = DocumentType::where('active', true)->orderBy('name')->get();
        $ports = Port::where('active', true)->orderBy('name')->get();
        $customOffices = CustomOffice::where('active', true)->orderBy('name')->get();

        return view('company.clients.create', compact('countries', 'documentTypes', 'ports', 'customOffices'));
    }

    /**
     * Almacenar nuevo cliente con contactos múltiples
     * CORRECCIÓN: Usar CreateClientRequest con client_roles
     */
    public function store(CreateClientRequest $request)
    {
        $this->authorize('create', Client::class);
        
        try {
            DB::beginTransaction();

            // Los datos ya vienen validados del FormRequest con client_roles
            $validatedData = $request->validated();

            // Limpiar CUIT/RUC
            $validatedData['tax_id'] = preg_replace('/[^0-9\-]/', '', $validatedData['tax_id']);

            // Establecer valores por defecto
            $validatedData['status'] = 'active';
            $company = $this->getUserCompany();
            $validatedData['created_by_company_id'] = $company ? $company->id : null;
            // Crear cliente
            $client = Client::create($validatedData);

            // Crear múltiples contactos si se proporcionan
            if ($request->has('contacts') && is_array($request->contacts)) {
                $this->createMultipleContacts($client, $request->contacts);
            }

            DB::commit();

            return redirect()
                ->route('company.clients.show', $client)
                ->with('success', 'Cliente creado exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Error creating client in company', [
                'data' => $request->validated(),
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
                'company_id' => $this->getUserCompanyId()
            ]);

            return back()
                ->withInput()
                ->withErrors(['error' => 'Error al crear cliente: ' . $e->getMessage()]);
        }
    }

    /**
     * Crear múltiples contactos para un cliente
     */
    private function createMultipleContacts(Client $client, array $contactsData): void
    {
        $hasPrimary = false;

        foreach ($contactsData as $index => $contactData) {
            // Validar que tenga al menos email o teléfono
            if (empty($contactData['email']) && empty($contactData['phone']) && empty($contactData['mobile_phone'])) {
                continue;
            }

            // El primer contacto válido es principal si no se especifica otro
            $isPrimary = isset($contactData['is_primary']) ? (bool) $contactData['is_primary'] : ($index === 0 && !$hasPrimary ? true : false);
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
     * Mostrar cliente específico
     */
    public function show(Client $client)
    {
        $this->authorize('view', $client);
        
        $client->load([
            'country',
            'documentType', 
            'primaryPort',
            'customsOffice',
            'createdByCompany'
        ]);

        return view('company.clients.show', compact('client'));
    }

    /**
     * Mostrar formulario de edición (solo company-admin)
     */
    public function edit(Client $client)
    {
        $this->authorize('update', $client);
        
        $countries = Country::where('active', true)->orderBy('name')->get();
        $documentTypes = DocumentType::where('active', true)->orderBy('name')->get();
        $ports = Port::where('active', true)->orderBy('name')->get();
        $customOffices = CustomOffice::where('active', true)->orderBy('name')->get();

        return view('company.clients.edit', compact('client', 'countries', 'documentTypes', 'ports', 'customOffices'));
    }

    /**
     * Actualizar cliente con contactos múltiples
     * CORRECCIÓN: Usar UpdateClientRequest con client_roles
     */
    public function update(UpdateClientRequest $request, Client $client)
    {
        $this->authorize('update', $client);
        //dd($request);
        try {
            DB::beginTransaction();

            // Los datos ya vienen validados del FormRequest con client_roles
            $validatedData = $request->validated();

            // Limpiar CUIT/RUC si se proporciona
            if (isset($validatedData['tax_id'])) {
                $validatedData['tax_id'] = preg_replace('/[^0-9\-]/', '', $validatedData['tax_id']);
            }

            // Actualizar cliente
            $client->update($validatedData);
            //dd($request);

            $contactsPayload = $request->input('contacts');

            // Fallback: si la vista no envía contacts[], armamos uno a partir de los campos raíz
            if (empty($contactsPayload) || !is_array($contactsPayload)) {
                $contactsPayload = [[
                    'id'                      => null,
                    'contact_type'            => 'general',
                    'email'                   => $request->input('email'),
                    'phone'                   => $request->input('phone'),
                    'mobile_phone'            => $request->input('mobile_phone'),
                    'address_line_1'          => $request->input('address_line_1'),
                    'address_line_2'          => $request->input('address_line_2'),
                    'city'                    => $request->input('city'),
                    'state_province'          => $request->input('state_province'),
                    'contact_person_name'     => $request->input('contact_person_name'),
                    'contact_person_position' => $request->input('contact_person_position'),
                    'notes'                   => $request->input('notes'),
                    'is_primary'              => true,
                    'active'                  => true,
                ]];
            }
                
                $this->updateClientContacts($client, $request->contacts);
            
            DB::commit();

            return redirect()
                ->route('company.clients.show', $client)
                ->with('success', 'Cliente actualizado exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Error updating client in company', [
                'client_id' => $client->id,
                'data' => $request->validated(),
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
                'company_id' => $this->getUserCompanyId()
            ]);

            return back()
                ->withInput()
                ->withErrors(['error' => 'Error al actualizar cliente: ' . $e->getMessage()]);
        }
    }

    private function updateClientContacts(Client $client, array $contactsData): void
    {
        // 1) Filtrar filas vacías (si no tienen nada relevante, se ignoran)
        $filtered = [];
        foreach ($contactsData as $row) {
            if (
                !empty($row['email']) ||
                !empty($row['phone']) ||
                !empty($row['mobile_phone']) ||
                !empty($row['contact_person_name']) ||
                !empty($row['address_line_1']) ||
                !empty($row['city']) ||
                !empty($row['notes'])
            ) {
                $filtered[] = $row;
            }
        }

        // Si no quedó ninguna fila, se eliminan todos los contactos del cliente
        if (empty($filtered)) {
            $client->contactData()->delete();
            return;
        }

        // 2) Normalizar primario: si ninguno viene marcado, el primero válido será principal
        $hasPrimary = false;
        foreach ($filtered as &$row) {
            $row['is_primary'] = !empty($row['is_primary']) ? 1 : 0;
            if ($row['is_primary']) { $hasPrimary = true; }
        }
        unset($row);
        if (!$hasPrimary && count($filtered) > 0) {
            $filtered[0]['is_primary'] = 1;
        }

        // 3) Mapear existentes por ID
        $existing = $client->contactData()->get()->keyBy('id');
        $keptIds = [];

        // 4) UPSERT por cada contacto del formulario
        foreach ($filtered as $row) {
            $payload = [
                'contact_type'            => $row['contact_type']            ?? 'general',
                'email'                   => $row['email']                   ?? null,
                'phone'                   => $row['phone']                   ?? null,
                'mobile_phone'            => $row['mobile_phone']            ?? null,
                'address_line_1'          => $row['address_line_1']          ?? null,
                'address_line_2'          => $row['address_line_2']          ?? null,
                'city'                    => $row['city']                    ?? null,
                'state_province'          => $row['state_province']          ?? null,
                'contact_person_name'     => $row['contact_person_name']     ?? null,
                'contact_person_position' => $row['contact_person_position'] ?? null,
                'notes'                   => $row['notes']                   ?? null,
                'is_primary'              => !empty($row['is_primary']),
            ];

            $id = isset($row['id']) ? (int) $row['id'] : null;

            if ($id && $existing->has($id)) {
                // Actualizar existente
                $existing[$id]->update($payload);
                $keptIds[] = $id;
            } else {
                // Crear nuevo
                $payload['active'] = true;
                $payload['created_by_user_id'] = \Illuminate\Support\Facades\Auth::id();
                $new = $client->contactData()->create($payload);
                $keptIds[] = $new->id;
            }
        }

        // 5) Eliminar los que ya no están en el formulario
        $client->contactData()->whereNotIn('id', $keptIds)->delete();

        // 6) Asegurar un único "is_primary"
        $primary = $client->contactData()->where('is_primary', true)->orderBy('id')->first();
        if (!$primary) {
            $first = $client->contactData()->orderBy('id')->first();
            if ($first) {
                $first->update(['is_primary' => true]);
            }
        } else {
            $client->contactData()->where('id', '!=', $primary->id)->update(['is_primary' => false]);
        }
    }



    /**
     * Eliminar cliente (solo company-admin)
     */
    public function destroy(Client $client)
    {
        $this->authorize('delete', $client);
        
        try {
            DB::beginTransaction();

            // Soft delete del cliente
            $client->update(['status' => 'inactive']);

            DB::commit();

            return redirect()
                ->route('company.clients.index')
                ->with('success', 'Cliente eliminado exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Error deleting client in company', [
                'client_id' => $client->id,
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
                'company_id' => $this->getUserCompanyId()
            ]);

            return back()
                ->withErrors(['error' => 'Error al eliminar cliente']);
        }
    }

    /**
     * Búsqueda rápida de clientes
     */
    public function search(Request $request)
    {
        $this->authorize('viewAny', Client::class);
        
        $search = $request->get('q', '');
        
        if (strlen($search) < 2) {
            return response()->json([]);
        }

        $clients = Client::where('status', 'active')
            ->whereNotNull('verified_at')
            ->where(function($query) use ($search) {
                $query->where('legal_name', 'like', "%{$search}%")
                      ->orWhere('commercial_name', 'like', "%{$search}%")
                      ->orWhere('tax_id', 'like', "%{$search}%");
            })
            ->select('id', 'legal_name', 'commercial_name', 'tax_id', 'client_roles')
            ->limit(15)
            ->get();

        return response()->json($clients);
    }

    /**
     * Sugerencias de clientes para autocompletado
     */
    public function suggestions(Request $request)
    {
        $this->authorize('viewAny', Client::class);
        
        $term = $request->get('term', '');
        $role = $request->get('role'); // Filtrar por rol específico
        
        if (strlen($term) < 2) {
            return response()->json([]);
        }

        $query = Client::where('status', 'active')
            ->whereNotNull('verified_at')
            ->where(function($q) use ($term) {
                $q->where('legal_name', 'like', "%{$term}%")
                  ->orWhere('tax_id', 'like', "%{$term}%");
            });

        // Filtrar por rol si se especifica
        if ($role && in_array($role, ['shipper', 'consignee', 'notify_party'])) {
            $query->whereJsonContains('client_roles', $role);
        }

        $suggestions = $query->select('id', 'legal_name', 'tax_id', 'client_roles')
            ->limit(10)
            ->get()
            ->map(function($client) {
                return [
                    'id' => $client->id,
                    'label' => $client->legal_name . ' (' . $client->tax_id . ')',
                    'value' => $client->legal_name,
                    'tax_id' => $client->tax_id,
                    'roles' => $client->client_roles ?? []
                ];
            });

        return response()->json($suggestions);
    }

    /**
     * Validar CUIT/RUC
     */
    public function validateTaxId(Request $request)
    {
        $request->validate([
            'tax_id' => 'required|string',
            'country_id' => 'required|exists:countries,id'
        ]);

        $exists = Client::where('tax_id', $request->tax_id)
            ->where('country_id', $request->country_id)
            ->exists();

        return response()->json([
            'exists' => $exists,
            'message' => $exists ? 'El CUIT/RUC ya está registrado' : 'CUIT/RUC disponible'
        ]);
    }

    /**
     * Cambiar estado del cliente (solo company-admin)
     */
    public function toggleStatus(Client $client)
    {
        $this->authorize('update', $client);
        
        $newStatus = $client->status === 'active' ? 'inactive' : 'active';
        $client->update(['status' => $newStatus]);

        $message = $newStatus === 'active' ? 'Cliente activado' : 'Cliente desactivado';
        
        return back()->with('success', $message);
    }

    /**
     * Gestionar contactos del cliente
     */
    public function contacts(Client $client)
    {
        $this->authorize('view', $client);
        
        // Aquí se implementará la gestión de contactos múltiples
        // Por ahora retorna vista básica
        return view('company.clients.contacts', compact('client'));
    }

   

    /**
     * Almacenar nuevo contacto para el cliente
     */
    public function storeContact(Request $request, Client $client)
    {
        $this->authorize('update', $client);
        
        $validated = $request->validate([
            'contact_person_name' => 'nullable|string|max:255',
            'contact_person_position' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'mobile_phone' => 'nullable|string|max:50',
            'address_line_1' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:1000',
            'is_primary' => 'boolean',
            'contact_type' => 'required|string|in:general,afip,manifests,arrival_notices,emergency,billing,operations'
        ]);

        // Validar que tenga al menos un método de contacto
        if (empty($validated['email']) && empty($validated['phone']) && empty($validated['mobile_phone'])) {
            return response()->json([
                'success' => false,
                'errors' => ['email' => ['Debe proporcionar al menos un email o teléfono']]
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Si se marca como principal, quitar principal de otros contactos
            if ($validated['is_primary'] ?? false) {
                $client->contactData()->update(['is_primary' => false]);
            }

            // Crear el contacto
            $contact = $client->contactData()->create([
                'contact_type' => $validated['contact_type'],
                'contact_person_name' => $validated['contact_person_name'],
                'contact_person_position' => $validated['contact_person_position'],
                'email' => $validated['email'],
                'phone' => $validated['phone'],
                'mobile_phone' => $validated['mobile_phone'],
                'address_line_1' => $validated['address_line_1'],
                'city' => $validated['city'],
                'notes' => $validated['notes'],
                'is_primary' => $validated['is_primary'] ?? false,
                'active' => true,
                'created_by_user_id' => Auth::id(),
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Contacto creado exitosamente',
                'contact' => $contact
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Error creating contact', [
                'client_id' => $client->id,
                'data' => $validated,
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al crear el contacto'
            ], 500);
        }
    }

    /**
     * Actualizar contacto existente
     */
    public function updateContact(Request $request, Client $client, $contactId)
    {
        $this->authorize('update', $client);
        
        $contact = $client->contactData()->findOrFail($contactId);
        
        $validated = $request->validate([
            'contact_person_name' => 'nullable|string|max:255',
            'contact_person_position' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'mobile_phone' => 'nullable|string|max:50',
            'address_line_1' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:1000',
            'is_primary' => 'boolean',
            'contact_type' => 'required|string|in:general,afip,manifests,arrival_notices,emergency,billing,operations'
        ]);

        // Validar que tenga al menos un método de contacto
        if (empty($validated['email']) && empty($validated['phone']) && empty($validated['mobile_phone'])) {
            return response()->json([
                'success' => false,
                'errors' => ['email' => ['Debe proporcionar al menos un email o teléfono']]
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Si se marca como principal, quitar principal de otros contactos
            if ($validated['is_primary'] ?? false) {
                $client->contactData()->where('id', '!=', $contact->id)->update(['is_primary' => false]);
            }

            // Actualizar el contacto
            $contact->update([
                'contact_type' => $validated['contact_type'],
                'contact_person_name' => $validated['contact_person_name'],
                'contact_person_position' => $validated['contact_person_position'],
                'email' => $validated['email'],
                'phone' => $validated['phone'],
                'mobile_phone' => $validated['mobile_phone'],
                'address_line_1' => $validated['address_line_1'],
                'city' => $validated['city'],
                'notes' => $validated['notes'],
                'is_primary' => $validated['is_primary'] ?? false,
                'updated_by_user_id' => Auth::id(),
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Contacto actualizado exitosamente',
                'contact' => $contact
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Error updating contact', [
                'client_id' => $client->id,
                'contact_id' => $contact->id,
                'data' => $validated,
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar el contacto'
            ], 500);
        }
    }

    /**
     * Eliminar contacto
     */
    public function destroyContact(Client $client, $contactId)
    {
        $this->authorize('update', $client);
        
        $contact = $client->contactData()->findOrFail($contactId);
        
        // No permitir eliminar el contacto principal si es el único
        if ($contact->is_primary && $client->contactData()->count() === 1) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede eliminar el único contacto del cliente'
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Si eliminamos el contacto principal y hay otros, marcar el siguiente como principal
            if ($contact->is_primary) {
                $nextContact = $client->contactData()->where('id', '!=', $contact->id)->first();
                if ($nextContact) {
                    $nextContact->update(['is_primary' => true]);
                }
            }

            $contact->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Contacto eliminado exitosamente'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Error deleting contact', [
                'client_id' => $client->id,
                'contact_id' => $contact->id,
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar el contacto'
            ], 500);
        }
    }
}