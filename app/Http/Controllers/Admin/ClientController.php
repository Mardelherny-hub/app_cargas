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
 * CONTROLADOR ACTUALIZADO CON INTEGRACIÓN CLIENT_CONTACT_DATA
 * Maneja clientes y sus datos de contacto (email, dirección, teléfono)
 */
class ClientController extends Controller
{
    /**
     * Listar clientes con datos de contacto
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        
        // Query base con contacto principal incluido
        $query = Client::with([
            'country',
            'primaryContact',  // ← NUEVO: Incluir contacto principal
            'companyRelations.company'
        ]);

        // Control de acceso
        if ($user->hasRole('super-admin')) {
            // Super admin ve todos
        } else {
            // Otros usuarios ven solo sus clientes accesibles
            $userCompanies = $user->companies->pluck('id')->toArray();
            $query->whereHas('companyRelations', function($q) use ($userCompanies) {
                $q->whereIn('company_id', $userCompanies)
                  ->where('active', true);
            });
        }

        // Filtros de búsqueda
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('legal_name', 'like', "%{$search}%")
                  ->orWhere('tax_id', 'like', "%{$search}%")
                  ->orWhereHas('primaryContact', function($contactQuery) use ($search) {
                      $contactQuery->where('email', 'like', "%{$search}%")
                               ->orWhere('phone', 'like', "%{$search}%")
                               ->orWhere('city', 'like', "%{$search}%");
                  });
            });
        }

        // Filtro por país
        if ($request->filled('country_id')) {
            $query->where('country_id', $request->country_id);
        }

        // Filtro por tipo de cliente
        if ($request->filled('client_type')) {
            $query->where('client_type', $request->client_type);
        }

        // Filtro por estado
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Paginación
        $clients = $query->orderBy('updated_at', 'desc')->paginate(25);

        // Datos auxiliares para filtros
        $countries = Country::where('active', true)->orderBy('name')->get();
        $companies = Company::where('active', true)->orderBy('legal_name')->get();
        
        // Estadísticas básicas
        $stats = [
            'total' => Client::count(),
            'verified' => Client::whereNotNull('verified_at')->count(),
            'pending' => Client::whereNull('verified_at')->count(),
            'inactive' => Client::where('status', 'inactive')->count(),
            'with_contact' => Client::whereHas('primaryContact')->count(), // ← NUEVO
        ];

        return view('admin.clients.index', compact(
            'clients', 'countries', 'companies', 'stats'
        ));
    }

    /**
     * Mostrar cliente específico con toda su información de contacto
     */
    public function show(Client $client)
    {
        // Cargar todas las relaciones necesarias
        $client->load([
            'country',
            'documentType', 
            'primaryPort',
            'customOffice',
            'createdByCompany',
            'primaryContact',     // ← Contacto principal
            'activeContacts',     // ← Todos los contactos activos
            'companyRelations.company'
        ]);

        return view('admin.clients.show', compact('client'));
    }

    /**
     * Mostrar formulario de creación
     */
    public function create()
    {
        $user = Auth::user();

        // Datos para selects
        $countries = Country::where('active', true)->orderBy('name')->get();
        $documentTypes = DocumentType::where('active', true)->get();
        $ports = Port::where('active', true)->orderBy('name')->get();
        $customsOffices = CustomOffice::where('active', true)->orderBy('name')->get();
        
        // Empresas disponibles según permisos
        $companies = $user->hasRole('super-admin') 
            ? Company::where('active', true)->orderBy('legal_name')->get()
            : $user->companies()->where('active', true)->get();

        return view('admin.clients.create', compact(
            'countries', 'documentTypes', 'ports', 'customsOffices', 'companies'
        ));
    }

    /**
     * Crear nuevo cliente con datos de contacto
     */
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            // Datos básicos del cliente
            'tax_id' => 'required|string|max:11',
            'legal_name' => 'required|string|max:255',
            'country_id' => 'required|exists:countries,id',
            'document_type_id' => 'required|exists:document_types,id',
            'client_type' => 'required|in:shipper,consignee,notify_party,owner',
            'primary_port_id' => 'nullable|exists:ports,id',
            'customs_offices_id' => 'nullable|exists:customs_offices,id',
            'created_by_company_id' => 'required|exists:companies,id',
            'status' => 'sometimes|in:active,inactive,suspended',
            'notes' => 'nullable|string|max:1000',
            
            // Datos de contacto (nuevos)
            'contact_email' => 'nullable|email|max:255',
            'contact_phone' => 'nullable|string|max:20', 
            'contact_mobile_phone' => 'nullable|string|max:20',
            'contact_address_line_1' => 'nullable|string|max:255',
            'contact_address_line_2' => 'nullable|string|max:255',
            'contact_city' => 'nullable|string|max:100',
            'contact_state_province' => 'nullable|string|max:100',
            'contact_postal_code' => 'nullable|string|max:20',
            'contact_person_name' => 'nullable|string|max:255',
            'contact_person_position' => 'nullable|string|max:255',
            'contact_person_phone' => 'nullable|string|max:20',
            'contact_person_email' => 'nullable|email|max:255',
            'accepts_email_notifications' => 'nullable|boolean',
            'accepts_sms_notifications' => 'nullable|boolean',
        ]);

        try {
            DB::beginTransaction();

            // Crear cliente
            $clientData = collect($validatedData)->only([
                'tax_id', 'legal_name', 'country_id', 'document_type_id', 
                'client_type', 'primary_port_id', 'customs_offices_id', 
                'created_by_company_id', 'status', 'notes'
            ])->toArray();

            $client = Client::create($clientData);

            // Crear datos de contacto si se proporcionaron
            $contactData = collect($validatedData)
                ->only([
                    'contact_email', 'contact_phone', 'contact_mobile_phone',
                    'contact_address_line_1', 'contact_address_line_2', 
                    'contact_city', 'contact_state_province', 'contact_postal_code',
                    'contact_person_name', 'contact_person_position',
                    'contact_person_phone', 'contact_person_email',
                    'accepts_email_notifications', 'accepts_sms_notifications'
                ])
                ->mapWithKeys(function ($value, $key) {
                    // Remover prefijo 'contact_' de las claves
                    return [str_replace('contact_', '', $key) => $value];
                })
                ->filter() // Remover valores vacíos
                ->toArray();

            if (!empty($contactData)) {
                $contactData['created_by_user_id'] = Auth::id();
                $client->createPrimaryContact($contactData);
            }

            DB::commit();

            return redirect()
                ->route('admin.clients.show', $client)
                ->with('success', 'Cliente creado exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creating client', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
                'data' => $validatedData
            ]);

            return back()
                ->withInput()
                ->withErrors(['error' => 'Error al crear cliente: ' . $e->getMessage()]);
        }
    }

    /**
     * Mostrar formulario de edición
     */
    public function edit(Client $client)
    {
        $user = Auth::user();

        // Verificar permisos
        if (!$user->hasRole('super-admin') && !$user->canEditClient($client)) {
            abort(403, 'No tienes permisos para editar este cliente');
        }

        // Cargar datos de contacto
        $client->load('primaryContact');

        // Datos para selects
        $countries = Country::where('active', true)->orderBy('name')->get();
        $documentTypes = DocumentType::where('active', true)->get();
        $ports = Port::where('active', true)->orderBy('name')->get();
        $customsOffices = CustomOffice::where('active', true)->orderBy('name')->get();
        
        $companies = $user->hasRole('super-admin') 
            ? Company::where('active', true)->orderBy('legal_name')->get()
            : collect([$user->companies()->where('active', true)->first()])->filter();

        return view('admin.clients.edit', compact(
            'client', 'countries', 'documentTypes', 'ports', 'customsOffices', 'companies'
        ));
    }

    /**
     * Actualizar cliente y datos de contacto
     */
    public function update(Request $request, Client $client)
    {
        $validatedData = $request->validate([
            // Datos básicos del cliente
            'tax_id' => 'required|string|max:11',
            'legal_name' => 'required|string|max:255',
            'country_id' => 'required|exists:countries,id',
            'document_type_id' => 'required|exists:document_types,id',
            'client_type' => 'required|in:shipper,consignee,notify_party,owner',
            'primary_port_id' => 'nullable|exists:ports,id',
            'customs_offices_id' => 'nullable|exists:customs_offices,id',
            'status' => 'sometimes|in:active,inactive,suspended',
            'notes' => 'nullable|string|max:1000',
            
            // Datos de contacto
            'contact_email' => 'nullable|email|max:255',
            'contact_phone' => 'nullable|string|max:20',
            'contact_mobile_phone' => 'nullable|string|max:20',
            'contact_address_line_1' => 'nullable|string|max:255',
            'contact_address_line_2' => 'nullable|string|max:255',
            'contact_city' => 'nullable|string|max:100',
            'contact_state_province' => 'nullable|string|max:100',
            'contact_postal_code' => 'nullable|string|max:20',
            'contact_person_name' => 'nullable|string|max:255',
            'contact_person_position' => 'nullable|string|max:255',
            'contact_person_phone' => 'nullable|string|max:20',
            'contact_person_email' => 'nullable|email|max:255',
            'accepts_email_notifications' => 'nullable|boolean',
            'accepts_sms_notifications' => 'nullable|boolean',
        ]);

        try {
            DB::beginTransaction();

            // Actualizar datos básicos del cliente
            $clientData = collect($validatedData)->only([
                'tax_id', 'legal_name', 'country_id', 'document_type_id',
                'client_type', 'primary_port_id', 'customs_offices_id',
                'status', 'notes'
            ])->toArray();

            $client->update($clientData);

            // Actualizar datos de contacto
            $contactData = collect($validatedData)
                ->only([
                    'contact_email', 'contact_phone', 'contact_mobile_phone',
                    'contact_address_line_1', 'contact_address_line_2',
                    'contact_city', 'contact_state_province', 'contact_postal_code',
                    'contact_person_name', 'contact_person_position',
                    'contact_person_phone', 'contact_person_email',
                    'accepts_email_notifications', 'accepts_sms_notifications'
                ])
                ->mapWithKeys(function ($value, $key) {
                    return [str_replace('contact_', '', $key) => $value];
                })
                ->filter()
                ->toArray();

            if (!empty($contactData)) {
                $contactData['updated_by_user_id'] = Auth::id();
                $client->updateOrCreatePrimaryContact($contactData);
            }

            DB::commit();

            return redirect()
                ->route('admin.clients.show', $client)
                ->with('success', 'Cliente actualizado exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error updating client', [
                'client_id' => $client->id,
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            return back()
                ->withInput()
                ->withErrors(['error' => 'Error al actualizar cliente: ' . $e->getMessage()]);
        }
    }

    /**
     * Eliminar cliente
     */
    public function destroy(Client $client)
    {
        try {
            // Los datos de contacto se eliminan automáticamente por cascade
            $client->delete();

            return redirect()
                ->route('admin.clients.index')
                ->with('success', 'Cliente eliminado exitosamente');

        } catch (\Exception $e) {
            Log::error('Error deleting client', [
                'client_id' => $client->id,
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            return back()
                ->withErrors(['error' => 'Error al eliminar cliente']);
        }
    }

    /**
     * Verificar cliente
     */
    public function verify(Client $client)
    {
        $client->update(['verified_at' => now()]);
        
        return back()->with('success', 'Cliente verificado exitosamente');
    }

    /**
     * Cambiar estado del cliente
     */
    public function toggleStatus(Client $client)
    {
        $newStatus = $client->status === 'active' ? 'inactive' : 'active';
        $client->update(['status' => $newStatus]);
        
        return back()->with('success', 'Estado del cliente actualizado');
    }

    /**
     * Transferir cliente (placeholder)
     */
    public function transfer(Client $client)
    {
        return back()->with('info', 'Funcionalidad de transferencia en desarrollo');
    }

    /**
     * Importación masiva (placeholder)
     */
    public function bulkImport(Request $request)
    {
        return back()->with('info', 'Funcionalidad de importación masiva en desarrollo');
    }

    /**
     * API Helper: Obtener datos para formularios
     */
    public function getFormData()
    {
        return response()->json([
            'client_types' => Client::getClientTypeOptions(),
            'statuses' => Client::getStatusOptions(),
            'countries' => Country::where('active', true)->get(['id', 'name']),
            'document_types' => DocumentType::where('active', true)->get(['id', 'name']),
            'ports' => Port::where('active', true)->get(['id', 'name']),
            'customs_offices' => CustomOffice::where('active', true)->get(['id', 'name']),
        ]);
    }
}