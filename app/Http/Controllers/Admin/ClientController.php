<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Company;
use App\Models\Country;
use App\Models\DocumentType;
use App\Models\Port;
use App\Models\CustomOffice;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * CONTROLADOR CORREGIDO: BASE COMPARTIDA + SIN OWNER
 * 
 * Cambios aplicados:
 * - ❌ REMOVIDO: uso de companyRelations (base compartida)
 * - ❌ REMOVIDO: client_type 'owner' de validaciones
 * - ✅ ADAPTADO: control de acceso para base compartida
 * - ✅ MANTIENE: funcionalidad de datos de contacto
 */
class ClientController extends Controller
{
    /**
     * Listar clientes con datos de contacto.
     * 
     * CORRECCIÓN: Adaptado para base de datos compartida
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        
        // Query base con contacto principal incluido (sin companyRelations)
        $query = Client::with([
            'country',
            'primaryContact',  // Mantener datos de contacto
            'createdByCompany:id,legal_name,commercial_name' // Solo para auditoría
        ]);

        // Control de acceso adaptado para base compartida
        if ($user->hasRole('super-admin')) {
            // Super admin ve todos los clientes
        } else {
            // Base compartida: todos los usuarios pueden ver todos los clientes activos
            // Se puede implementar filtros adicionales según necesidades de negocio
            $query->where('status', 'active')
                  ->whereNotNull('verified_at');
        }

        // Filtros de búsqueda
        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function($q) use ($search) {
                $q->where('legal_name', 'like', "%{$search}%")
                  ->orWhere('tax_id', 'like', "%{$search}%")
                  ->orWhereHas('primaryContact', function($contact) use ($search) {
                      $contact->where('email', 'like', "%{$search}%");
                  });
            });
        }

        // Filtro por tipo de cliente (sin 'owner')
        if ($request->filled('client_type')) {
            $validTypes = ['shipper', 'consignee', 'notify_party'];
            if (in_array($request->get('client_type'), $validTypes)) {
                $query->where('client_type', $request->get('client_type'));
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
            'by_type' => Client::select('client_type', DB::raw('count(*) as count'))
                              ->whereIn('client_type', ['shipper', 'consignee', 'notify_party'])
                              ->groupBy('client_type')
                              ->pluck('count', 'client_type')
                              ->toArray(),
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
     * 
     * CORRECCIÓN: Removido 'owner' de tipos válidos
     */
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'tax_id' => [
                'required', 
                'string', 
                'max:15',
                'unique:clients,tax_id,NULL,id,country_id,' . $request->country_id
            ],
            'legal_name' => 'required|string|min:3|max:255',
            'country_id' => 'required|exists:countries,id',
            'document_type_id' => 'nullable|exists:document_types,id',
            'client_type' => 'required|in:shipper,consignee,notify_party', // ← REMOVIDO 'owner'
            'primary_port_id' => 'nullable|exists:ports,id',
            'customs_offices_id' => 'nullable|exists:custom_offices,id',
            'status' => 'sometimes|in:active,inactive,suspended',
            'notes' => 'nullable|string|max:1000',
            // Datos de contacto opcionales
            // Múltiples contactos con tipos
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
            'contacts.*.is_primary' => 'nullable|boolean',        ]);

        try {
            DB::beginTransaction();

            // Limpiar CUIT/RUC
            $validatedData['tax_id'] = preg_replace('/[^0-9]/', '', $validatedData['tax_id']);
            
            // Agregar auditoría
            $validatedData['created_by_company_id'] = auth()->user()->companies()->first()?->id;

            // Crear cliente
            $client = Client::create($validatedData);

            // Crear contacto principal si se proporcionaron datos
            // Crear múltiples contactos si se proporcionaron
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
                'data' => $validatedData,
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
     * 
     * CORRECCIÓN: Sin companyRelations
     */
    public function show(Client $client)
    {
        // Cargar relaciones (sin companyRelations)
                $client->load([
            'country', 
            'documentType', 
            'primaryPort',
            'customOffice',
            'createdByCompany:id,legal_name,commercial_name',
            'contactData' => function($query) {
                $query->where('active', true)
                    ->orderBy('is_primary', 'desc')
                    ->orderBy('contact_type')
                    ->orderBy('created_at');
            }
        ]);

        // Organizar contactos por tipo para la vista
        $contactsByType = $client->contactData->groupBy('contact_type');
        $contactTypes = \App\Models\ClientContactData::CONTACT_TYPES;

        return view('admin.clients.show', compact('client', 'contactsByType', 'contactTypes'));

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

        // Cargar contacto principal para edición
        $client->load(['contactData' => function($query) {
            $query->where('active', true)->orderBy('is_primary', 'desc')->orderBy('contact_type');
        }]);

        return view('admin.clients.edit', compact('client', 'countries', 'documentTypes', 'ports', 'customOffices'));
    }

    /**
     * Actualizar cliente.
     * 
     * CORRECCIÓN: Removido 'owner' de tipos válidos
     */
    public function update(Request $request, Client $client)
    {
        $validatedData = $request->validate([
            'tax_id' => [
                'required', 
                'string', 
                'max:15',
                'unique:clients,tax_id,' . $client->id . ',id,country_id,' . $request->country_id
            ],
            'legal_name' => 'required|string|min:3|max:255',
            'country_id' => 'required|exists:countries,id',
            'document_type_id' => 'nullable|exists:document_types,id',
            'client_type' => 'required|in:shipper,consignee,notify_party', // ← REMOVIDO 'owner'
            'primary_port_id' => 'nullable|exists:ports,id',
            'customs_offices_id' => 'nullable|exists:custom_offices,id',
            'status' => 'sometimes|in:active,inactive,suspended',
            'notes' => 'nullable|string|max:1000',
            // Datos de contacto opcionales
            'contact_email' => 'nullable|email|max:100',
            'contact_phone' => 'nullable|string|max:50',
            'contact_address' => 'nullable|string|max:500',
            'contact_city' => 'nullable|string|max:100',
        ]);

        try {
            DB::beginTransaction();

            // Limpiar CUIT/RUC
            $validatedData['tax_id'] = preg_replace('/[^0-9]/', '', $validatedData['tax_id']);
            
            // Actualizar cliente
            $client->update($validatedData);

            // Actualizar o crear contacto principal
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
                'data' => $validatedData,
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
            // Los datos de contacto se eliminan automáticamente por cascade
            $clientName = $client->legal_name;
            $client->delete();

            return redirect()
                ->route('admin.clients.index')
                ->with('success', "Cliente '{$clientName}' eliminado exitosamente");

        } catch (\Exception $e) {
            Log::error('Error deleting client', [
                'client_id' => $client->id,
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            return back()
                ->withErrors(['error' => 'Error al eliminar cliente: ' . $e->getMessage()]);
        }
    }

    /**
     * Verificar cliente.
     */
    public function verify(Client $client)
    {
        $client->update([
            'verified_at' => now(),
            'updated_at' => now()
        ]);
        
        return back()->with('success', 'Cliente verificado exitosamente');
    }

    /**
     * Cambiar estado del cliente.
     */
    public function toggleStatus(Client $client)
    {
        $newStatus = $client->status === 'active' ? 'inactive' : 'active';
        $client->update(['status' => $newStatus]);
        
        $statusText = $newStatus === 'active' ? 'activado' : 'desactivado';
        return back()->with('success', "Cliente {$statusText} exitosamente");
    }

    /**
     * Transferir cliente.
     * 
     * CORRECCIÓN: Adaptado para base compartida (sin transferencias entre empresas)
     */
    public function transfer(Client $client, Request $request)
    {
        // En base compartida, no hay transferencias entre empresas
        // Solo cambio de empresa que creó el registro para auditoría
        
        if (!auth()->user()->hasRole('super-admin')) {
            return back()->with('error', 'Solo super administradores pueden cambiar la empresa creadora');
        }

        $request->validate([
            'new_company_id' => 'required|exists:companies,id'
        ]);

        $client->update([
            'created_by_company_id' => $request->new_company_id,
            'updated_at' => now()
        ]);

        return back()->with('success', 'Empresa creadora actualizada exitosamente');
    }

    /**
     * Importación masiva.
     * 
     * NOTA: Mantener funcionalidad básica, adaptar Jobs si es necesario
     */
    public function bulkImport(Request $request)
    {
        $request->validate([
            'import_file' => 'required|file|mimes:csv,xlsx,xls|max:10240',
            'import_type' => 'required|in:clients,client_data'
        ]);

        // TODO: Adaptar ProcessBulkClientDataJob para base compartida
        return back()->with('info', 'Funcionalidad de importación masiva en desarrollo para base compartida');
    }

    /**
     * API Helper: Obtener datos para formularios.
     * 
     * CORRECCIÓN: Sin 'owner' en client_types
     */
    public function getFormData()
    {
        return response()->json([
            'client_types' => [
                'shipper' => 'Cargador/Exportador',
                'consignee' => 'Consignatario/Importador',
                'notify_party' => 'Notificatario'
            ], // ← REMOVIDO 'owner'
            'statuses' => Client::getStatusOptions(),
            'countries' => Country::where('active', true)->get(['id', 'name']),
            'document_types' => DocumentType::where('active', true)->get(['id', 'name']),
            'ports' => Port::where('active', true)->get(['id', 'name']),
            'customs_offices' => CustomOffice::where('active', true)->get(['id', 'name']),
        ]);
    }

    /**
     * Búsqueda AJAX para autocompletado.
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
            ->whereIn('client_type', ['shipper', 'consignee', 'notify_party']) // ← REMOVIDO 'owner'
            ->with('country:id,name')
            ->limit(10)
            ->get(['id', 'legal_name', 'tax_id', 'client_type', 'country_id']);

        return response()->json($clients);
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
     * Actualizar múltiples contactos de un cliente.
     */
    private function updateMultipleContacts(Client $client, array $contacts): void
    {
        // Remover contactos existentes que no estén en la nueva lista
        $keepIds = collect($contacts)->pluck('id')->filter();
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

    /**
     * Obtener tipos de contacto para formularios.
     */
    public function getContactTypes(): array
    {
        return \App\Models\ClientContactData::CONTACT_TYPES;
    }

    /**
     * Obtener estadísticas de contactos para la vista.
     */
    private function getContactStats(Client $client): array
    {
        return [
            'total_contacts' => $client->contactData()->where('active', true)->count(),
            'has_afip_contact' => $client->hasContactType('afip'),
            'has_arrival_notices' => $client->hasContactType('arrival_notices'),
            'arrival_notice_emails' => $client->getArrivalNoticeEmails(),
            'primary_contact' => $client->primaryContact,
        ];
    }
}