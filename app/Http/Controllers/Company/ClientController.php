<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Country;
use App\Models\DocumentType;
use App\Models\Port;
use App\Models\CustomOffice;
use App\Traits\UserHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests; // AGREGADO
use Illuminate\Support\Facades\Log;

/**
 * Controlador de Clientes para Company Admin y Users
 * Maneja la base compartida de clientes (sin propietarios)
 */
class ClientController extends Controller
{
    use UserHelper;
    use AuthorizesRequests; // AGREGADO

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

        return view('company.clients.index', compact('clients', 'countries', 'documentTypes', 'stats'));
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
     * Almacenar nuevo cliente
     */
    /**
     * Almacenar nuevo cliente con contactos múltiples
     */
    public function store(Request $request)
    {
        $this->authorize('create', Client::class);
        
        // Validaciones completas
        $validated = $request->validate([
            // Datos básicos del cliente
            'tax_id' => [
                'required', 
                'string', 
                'max:15',
                'unique:clients,tax_id,NULL,id,country_id,' . $request->country_id
            ],
            'legal_name' => 'required|string|min:3|max:255',
            'commercial_name' => 'nullable|string|max:255',
            'country_id' => 'required|exists:countries,id',
            'document_type_id' => 'required|exists:document_types,id',
            'primary_port_id' => 'nullable|exists:ports,id',
            'custom_office_id' => 'nullable|exists:custom_offices,id',
            'notes' => 'nullable|string|max:1000',
            
            // Contactos múltiples
            'contacts' => 'nullable|array|max:10',
            'contacts.*.contact_type' => 'required_with:contacts.*|in:general,afip,manifests,arrival_notices,emergency,billing,operations',
            'contacts.*.email' => 'nullable|email|max:255',
            'contacts.*.phone' => 'nullable|string|max:20',
            'contacts.*.mobile_phone' => 'nullable|string|max:20',
            'contacts.*.address_line_1' => 'nullable|string|max:255',
            'contacts.*.address_line_2' => 'nullable|string|max:255',
            'contacts.*.city' => 'nullable|string|max:100',
            'contacts.*.state_province' => 'nullable|string|max:100',
            'contacts.*.contact_person_name' => 'nullable|string|max:150',
            'contacts.*.contact_person_position' => 'nullable|string|max:100',
            'contacts.*.notes' => 'nullable|string|max:500',
            'contacts.*.is_primary' => 'nullable|boolean',
        ]);

        DB::beginTransaction();
        try {
            // Limpiar CUIT/RUC
            $validated['tax_id'] = preg_replace('/[^0-9]/', '', $validated['tax_id']);
            
            // Crear cliente
            $client = Client::create([
                'tax_id' => $validated['tax_id'],
                'legal_name' => $validated['legal_name'],
                'commercial_name' => $validated['commercial_name'],
                'country_id' => $validated['country_id'],
                'document_type_id' => $validated['document_type_id'],
                'primary_port_id' => $validated['primary_port_id'],
                'custom_office_id' => $validated['custom_office_id'],
                'notes' => $validated['notes'],
                'created_by_company_id' => $this->getUserCompanyId(),
                'status' => 'active',
                'verified_at' => now(), // Auto-verificado por company admin
            ]);

            // Crear contactos múltiples si se proporcionaron
            if ($request->has('contacts') && is_array($request->contacts)) {
                $this->createMultipleContacts($client, $request->contacts);
            }

            DB::commit();
            
            return redirect()
                ->route('company.clients.show', $client)
                ->with('success', 'Cliente creado exitosamente.');
                
        } catch (\Exception $e) {
            DB::rollBack();
            
            return back()
                ->withInput()
                ->with('error', 'Error al crear el cliente: ' . $e->getMessage());
        }
    }

    /**
     * Crear múltiples contactos para un cliente
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
            $isPrimary = !$hasPrimary && ($contactData['is_primary'] ?? false);
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
            'customOffice',
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
     * Actualizar cliente
     */
    /**
     * Actualizar cliente con contactos múltiples
     */
    public function update(Request $request, Client $client)
    {
        $this->authorize('update', $client);
        
        // Validaciones completas
        $validated = $request->validate([
            // Datos básicos del cliente
            'tax_id' => [
                'required', 
                'string', 
                'max:15',
                'unique:clients,tax_id,' . $client->id . ',id,country_id,' . $request->country_id
            ],
            'legal_name' => 'required|string|min:3|max:255',
            'commercial_name' => 'nullable|string|max:255',
            'country_id' => 'required|exists:countries,id',
            'document_type_id' => 'required|exists:document_types,id',
            'primary_port_id' => 'nullable|exists:ports,id',
            'custom_office_id' => 'nullable|exists:custom_offices,id',
            'notes' => 'nullable|string|max:1000',
            
            // Contactos múltiples
            'contacts' => 'nullable|array|max:10',
            'contacts.*.id' => 'nullable|exists:client_contact_data,id',
            'contacts.*.contact_type' => 'required_with:contacts.*|in:general,afip,manifests,arrival_notices,emergency,billing,operations',
            'contacts.*.email' => 'nullable|email|max:255',
            'contacts.*.phone' => 'nullable|string|max:20',
            'contacts.*.mobile_phone' => 'nullable|string|max:20',
            'contacts.*.address_line_1' => 'nullable|string|max:255',
            'contacts.*.address_line_2' => 'nullable|string|max:255',
            'contacts.*.city' => 'nullable|string|max:100',
            'contacts.*.state_province' => 'nullable|string|max:100',
            'contacts.*.contact_person_name' => 'nullable|string|max:150',
            'contacts.*.contact_person_position' => 'nullable|string|max:100',
            'contacts.*.notes' => 'nullable|string|max:500',
            'contacts.*.is_primary' => 'nullable|boolean',
        ]);

        DB::beginTransaction();
        try {
            // Limpiar CUIT/RUC
            $validated['tax_id'] = preg_replace('/[^0-9]/', '', $validated['tax_id']);
            
            // Actualizar datos del cliente
            $client->update([
                'tax_id' => $validated['tax_id'],
                'legal_name' => $validated['legal_name'],
                'commercial_name' => $validated['commercial_name'],
                'country_id' => $validated['country_id'],
                'document_type_id' => $validated['document_type_id'],
                'primary_port_id' => $validated['primary_port_id'],
                'custom_office_id' => $validated['custom_office_id'],
                'notes' => $validated['notes'],
            ]);

            // Actualizar contactos múltiples
            if ($request->has('contacts') && is_array($request->contacts)) {
                $this->updateMultipleContacts($client, $request->contacts);
            } else {
                // Si no hay contactos, eliminar todos los existentes
                $client->contactData()->delete();
            }

            DB::commit();
            
            return redirect()
                ->route('company.clients.show', $client)
                ->with('success', 'Cliente actualizado exitosamente.');
                
        } catch (\Exception $e) {
            DB::rollBack();
            
            return back()
                ->withInput()
                ->with('error', 'Error al actualizar el cliente: ' . $e->getMessage());
        }
    }

    /**
     * Actualizar múltiples contactos de un cliente
     */
    private function updateMultipleContacts(Client $client, array $contacts): void
    {
        // Obtener IDs de contactos que se mantienen
        $keepIds = collect($contacts)->pluck('id')->filter()->values();
        
        // Eliminar contactos que no estén en la nueva lista
        $client->contactData()->whereNotIn('id', $keepIds)->delete();
        
        $hasPrimary = false;
        
        foreach ($contacts as $index => $contactData) {
            // Validar que tenga al menos email o teléfono
            if (empty($contactData['email']) && empty($contactData['phone']) && empty($contactData['mobile_phone'])) {
                continue;
            }
            
            // Solo un contacto puede ser primario
            $isPrimary = !$hasPrimary && ($contactData['is_primary'] ?? false);
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
            ];
            
            if (!empty($contactData['id'])) {
                // Actualizar contacto existente
                $client->contactData()->where('id', $contactData['id'])->update($contactAttributes);
            } else {
                // Crear nuevo contacto
                $client->contactData()->create(array_merge($contactAttributes, [
                    'created_by_user_id' => auth()->id(),
                ]));
            }
        }
        
        // Si no hay contacto primario marcado, hacer primario al primer contacto
        if (!$hasPrimary && $client->contactData()->count() > 0) {
            $client->contactData()->first()->update(['is_primary' => true]);
        }
    }

    /**
     * Eliminar cliente (solo company-admin)
     */
    public function destroy(Client $client)
    {
        $this->authorize('delete', $client);
        
        try {
            $client->update(['status' => 'inactive']);
            
            return redirect()
                ->route('company.clients.index')
                ->with('success', 'Cliente desactivado exitosamente.');
                
        } catch (\Exception $e) {
            return back()->with('error', 'Error al desactivar el cliente: ' . $e->getMessage());
        }
    }

    /**
     * Búsqueda de clientes (AJAX)
     */
    public function search(Request $request)
    {
        $query = Client::where('status', 'active')
            ->whereNotNull('verified_at');

        if ($request->filled('q')) {
            $search = $request->get('q');
            $query->where(function($q) use ($search) {
                $q->where('legal_name', 'like', "%{$search}%")
                  ->orWhere('commercial_name', 'like', "%{$search}%")
                  ->orWhere('tax_id', 'like', "%{$search}%");
            });
        }

        $clients = $query->with(['country:id,name'])
            ->select('id', 'legal_name', 'commercial_name', 'tax_id', 'country_id')
            ->limit(10)
            ->get();

        return response()->json($clients);
    }

    /**
     * Sugerencias de clientes (AJAX)
     */
    public function suggestions(Request $request)
    {
        $query = Client::where('status', 'active')
            ->whereNotNull('verified_at');

        if ($request->filled('tax_id')) {
            $taxId = $request->get('tax_id');
            $query->where('tax_id', 'like', "%{$taxId}%");
        }

        $suggestions = $query->select('id', 'legal_name', 'tax_id')
            ->limit(5)
            ->get();

        return response()->json($suggestions);
    }

    /**
     * Validar CUIT/RUC (AJAX)
     */
    public function validateTaxId(Request $request)
    {
        $taxId = $request->get('tax_id');
        $countryId = $request->get('country_id');

        if (!$taxId || !$countryId) {
            return response()->json(['valid' => false, 'message' => 'Datos insuficientes']);
        }

        // Verificar si ya existe
        $exists = Client::where('tax_id', $taxId)
            ->where('country_id', $countryId)
            ->exists();

        if ($exists) {
            return response()->json(['valid' => false, 'message' => 'El CUIT/RUC ya existe']);
        }

        // Aquí se pueden agregar validaciones específicas por país
        return response()->json(['valid' => true, 'message' => 'CUIT/RUC válido']);
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
}