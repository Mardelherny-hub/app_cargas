<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Company;
use App\Models\Country;
use App\Models\DocumentType;
use App\Models\Port;
use App\Models\CustomOffice;
use App\Http\Requests\CreateClientRequest;
use App\Http\Requests\UpdateClientRequest;
use App\Http\Requests\BulkClientImportRequest;
use App\Services\ClientValidationService;
use App\Services\TaxIdExtractionService;
use App\Services\ClientSuggestionService;
use App\Jobs\VerifyClientTaxIdJob;
use App\Jobs\ProcessBulkClientDataJob;
use App\Traits\UserHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * FASE 4 - INTEGRACIÓN CON EMPRESAS | MÓDULO CLIENTES
 *
 * Controlador Admin para gestión de clientes
 * Solo accesible para super-admin (por middleware en rutas)
 * CORREGIDO: Solo maneja vistas web y redirecciones
 */
class ClientController extends Controller
{
    use UserHelper;

    protected ClientValidationService $validationService;
    protected TaxIdExtractionService $extractionService;
    protected ClientSuggestionService $suggestionService;

    public function __construct(
        ClientValidationService $validationService,
        TaxIdExtractionService $extractionService,
        ClientSuggestionService $suggestionService
    ) {
        $this->validationService = $validationService;
        $this->extractionService = $extractionService;
        $this->suggestionService = $suggestionService;
    }

    /**
     * Listar clientes según permisos del usuario
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        // Obtener clientes accesibles según permisos
        $query = $user->getAccessibleClients();

        // Aplicar filtros de búsqueda
        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('tax_id', 'like', "%{$search}%")
                  ->orWhere('business_name', 'like', "%{$search}%");
            });
        }

        if ($request->filled('country_id')) {
            $query->where('country_id', $request->get('country_id'));
        }

        if ($request->filled('client_type')) {
            $query->where('client_type', $request->get('client_type'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->get('status'));
        }

        if ($request->filled('verification_status')) {
            if ($request->get('verification_status') === 'verified') {
                $query->whereNotNull('verified_at');
            } else {
                $query->whereNull('verified_at');
            }
        }

        // Eager loading para optimizar consultas
        $clients = $query->with([
            'country',
            'documentType',
            'primaryPort',
            'customOffice',
            'companyRelations' => function ($q) {
                $q->where('active', true)->with('company');
            }
        ])
        ->orderBy('updated_at', 'desc')
        ->paginate(25);

        // Datos auxiliares para filtros
        $countries = Country::where('active', true)->orderBy('name')->get();
        $companies = $user->hasRole('super-admin')
            ? Company::where('active', true)->orderBy('business_name')->get()
            : collect([$this->getUserCompany()])->filter();

        // Estadísticas rápidas
        $stats = $this->getClientStats($user);

        return view('admin.clients.index', compact(
            'clients', 'countries', 'companies', 'stats'
        ));
    }

    /**
     * Mostrar formulario de creación de cliente
     */
    public function create()
    {
        $user = Auth::user();

        // Datos para formulario
        $countries = Country::where('active', true)->orderBy('name')->get();
        $companies = $user->hasRole('super-admin')
            ? Company::where('active', true)->orderBy('business_name')->get()
            : collect([$this->getUserCompany()])->filter();

        return view('admin.clients.create', compact('countries', 'companies'));
    }

    /**
     * Almacenar nuevo cliente
     */
    public function store(CreateClientRequest $request)
    {
        try {
            DB::beginTransaction();

            $user = Auth::user();
            $data = $request->validated();

            // Asignar empresa que crea el cliente
            $data['created_by_company_id'] = $user->hasRole('super-admin')
                ? ($data['company_id'] ?? null)
                : $this->getUserCompanyId();

            // Crear cliente
            $client = Client::create($data);

            // Crear relación empresa-cliente
            if ($data['created_by_company_id']) {
                $client->companyRelations()->create([
                    'company_id' => $data['created_by_company_id'],
                    'relation_type' => $data['relation_type'] ?? 'customer',
                    'can_edit' => true,
                    'active' => true
                ]);
            }

            // Programar verificación asíncrona de CUIT/RUC
            if ($client->tax_id) {
                VerifyClientTaxIdJob::dispatch($client);
            }

            // Log de actividad
            $user->logClientActivity($client, 'created', [
                'tax_id' => $client->tax_id,
                'business_name' => $client->business_name
            ]);

            DB::commit();

            return redirect()
                ->route('admin.clients.show', $client)
                ->with('success', 'Cliente creado exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creating client', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
                'data' => $data ?? []
            ]);

            return back()
                ->withErrors(['error' => 'Error al crear cliente: ' . $e->getMessage()])
                ->withInput();
        }
    }

    /**
     * Mostrar detalles de cliente específico
     */
    public function show(Client $client)
    {
        $user = Auth::user();

        // Cargar relaciones necesarias
        $client->load([
            'country',
            'documentType',
            'primaryPort',
            'customOffice',
            'companyRelations.company',
            'documentData' => function ($query) {
                $query->orderBy('created_at', 'desc');
            }
        ]);

        // Obtener estadísticas de uso del cliente
        $usageStats = $this->getClientUsageStats($client);

        // Historial reciente de actividades
        $recentActivity = $this->getClientRecentActivity($client, 10);

        return view('admin.clients.show', compact(
            'client', 'usageStats', 'recentActivity'
        ));
    }

    /**
     * Mostrar formulario de edición
     */
    public function edit(Client $client)
    {
        $user = Auth::user();

        // Cargar datos necesarios
        $client->load(['country', 'documentType', 'companyRelations.company']);

        $countries = Country::where('active', true)->orderBy('name')->get();
        $documentTypes = DocumentType::where('active', true)->orderBy('name')->get();
        $ports = Port::where('active', true)->orderBy('name')->get();
        $customsOffices = CustomOffice::where('active', true)->orderBy('name')->get();

        $companies = $user->hasRole('super-admin')
            ? Company::where('active', true)->orderBy('business_name')->get()
            : collect([$this->getUserCompany()])->filter();

        return view('admin.clients.edit', compact(
            'client', 'countries', 'documentTypes', 'ports', 'customsOffices', 'companies'
        ));
    }

    /**
     * Actualizar cliente existente
     */
    public function update(UpdateClientRequest $request, Client $client)
    {
        try {
            DB::beginTransaction();

            $user = Auth::user();
            $data = $request->validated();

            // Actualizar datos básicos
            $client->update($data);

            // Re-verificar si cambió el CUIT/RUC
            if ($client->wasChanged('tax_id')) {
                $client->update(['verified_at' => null]);
                VerifyClientTaxIdJob::dispatch($client);
            }

            // Log de actividad
            $user->logClientActivity($client, 'updated', [
                'changes' => $client->getChanges()
            ]);

            DB::commit();

            return redirect()
                ->route('admin.clients.show', $client)
                ->with('success', 'Cliente actualizado exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error updating client', [
                'client_id' => $client->id,
                'error' => $e->getMessage(),
                'user_id' => $user->id
            ]);

            return back()
                ->withErrors(['error' => 'Error al actualizar cliente'])
                ->withInput();
        }
    }

    /**
     * Eliminar cliente (soft delete)
     */
    public function destroy(Client $client)
    {
        try {
            // Solo super admin o company admin que creó el cliente puede eliminar
            $user = Auth::user();
            if (!$this->isSuperAdmin() &&
                (!$this->isCompanyAdmin() || $client->created_by_company_id !== $this->getUserCompanyId())) {
                abort(403, 'No autorizado para eliminar este cliente');
            }

            DB::beginTransaction();

            $user = Auth::user();

            // Desactivar relaciones con empresas
            $client->companyRelations()->update(['active' => false]);

            // Soft delete del cliente
            $client->delete();

            // Log de actividad
            $user->logClientActivity($client, 'deleted', [
                'tax_id' => $client->tax_id,
                'business_name' => $client->business_name
            ]);

            DB::commit();

            return redirect()
                ->route('admin.clients.index')
                ->with('success', 'Cliente eliminado exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error deleting client', [
                'client_id' => $client->id,
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            return back()->withErrors(['error' => 'Error al eliminar cliente']);
        }
    }

    // =====================================================
    // MÉTODOS ESPECÍFICOS PARA CLIENTES
    // =====================================================

    /**
     * Verificar CUIT/RUC de cliente manualmente
     */
    public function verify(Client $client)
    {
        try {
            // Verificar permisos usando UserHelper
            if (!$this->isSuperAdmin() && !Auth::user()->canEditClient($client)) {
                abort(403, 'No autorizado para verificar este cliente');
            }

            $user = Auth::user();

            // Ejecutar verificación
            VerifyClientTaxIdJob::dispatch($client);

            // Log de actividad
            $user->logClientActivity($client, 'verification_requested');

            return back()->with('success', 'Verificación de CUIT/RUC iniciada');

        } catch (\Exception $e) {
            Log::error('Error verifying client', [
                'client_id' => $client->id,
                'error' => $e->getMessage()
            ]);

            return back()->withErrors(['error' => 'Error al verificar cliente']);
        }
    }

    /**
     * Cambiar estado activo/inactivo del cliente
     */
    public function toggleStatus(Client $client)
    {
        try {
            // Verificar permisos usando UserHelper
            if (!$this->isSuperAdmin() && !Auth::user()->canEditClient($client)) {
                abort(403, 'No autorizado para cambiar estado de este cliente');
            }

            $user = Auth::user();
            $newStatus = $client->status === 'active' ? 'inactive' : 'active';

            $client->update(['status' => $newStatus]);

            // Log de actividad
            $user->logClientActivity($client, 'status_changed', [
                'old_status' => $client->getOriginal('status'),
                'new_status' => $newStatus
            ]);

            return back()->with('success', "Cliente {$newStatus}");

        } catch (\Exception $e) {
            Log::error('Error toggling client status', [
                'client_id' => $client->id,
                'error' => $e->getMessage()
            ]);

            return back()->withErrors(['error' => 'Error al cambiar estado']);
        }
    }

    /**
     * Importación masiva de clientes - CORREGIDO
     */
    public function bulkImport(BulkClientImportRequest $request)
    {
        try {
            // Solo super admin y company admin pueden crear clientes
            if (!$this->isSuperAdmin() && !$this->isCompanyAdmin()) {
                abort(403, 'No autorizado para importar clientes');
            }

            $user = Auth::user();
            $data = $request->validated();
            $file = $request->file('file');

            $companyId = $user->hasRole('super-admin')
                ? ($data['company_id'] ?? null)
                : $this->getUserCompanyId();

            if (!$companyId) {
                throw new \Exception('Empresa no especificada para la importación');
            }

            // Procesar archivo de forma asíncrona
            ProcessBulkClientDataJob::dispatch($file->path(), $companyId, $user->id);

            return back()->with('success', 'Importación masiva iniciada. Recibirá notificación al completarse.');

        } catch (\Exception $e) {
            Log::error('Error in bulk import', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            return back()->withErrors(['error' => 'Error en importación masiva: ' . $e->getMessage()]);
        }
    }

    /**
     * Transferir cliente entre empresas - CORREGIDO
     */
    public function transfer(Request $request, Client $client)
    {
        try {
            // Solo super admin puede transferir clientes entre empresas
            if (!$this->isSuperAdmin()) {
                abort(403, 'Solo Super Admin puede transferir clientes entre empresas');
            }

            $request->validate([
                'from_company_id' => 'required|exists:companies,id',
                'to_company_id' => 'required|exists:companies,id|different:from_company_id',
                'relation_type' => 'required|in:customer,provider,both',
                'transfer_edit_permissions' => 'boolean'
            ]);

            DB::beginTransaction();

            $user = Auth::user();
            $fromCompanyId = $request->get('from_company_id');
            $toCompanyId = $request->get('to_company_id');

            // Desactivar relación anterior
            $client->companyRelations()
                ->where('company_id', $fromCompanyId)
                ->update(['active' => false]);

            // Crear nueva relación
            $client->companyRelations()->create([
                'company_id' => $toCompanyId,
                'relation_type' => $request->get('relation_type'),
                'can_edit' => $request->boolean('transfer_edit_permissions'),
                'active' => true
            ]);

            // Log de actividad
            $user->logClientActivity($client, 'transferred', [
                'from_company_id' => $fromCompanyId,
                'to_company_id' => $toCompanyId,
                'relation_type' => $request->get('relation_type')
            ]);

            DB::commit();

            return back()->with('success', 'Cliente transferido exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error transferring client', [
                'client_id' => $client->id,
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            return back()->withErrors(['error' => 'Error al transferir cliente: ' . $e->getMessage()]);
        }
    }

    // =====================================================
    // MÉTODOS AUXILIARES PRIVADOS
    // =====================================================

    /**
     * Obtener estadísticas de clientes para el usuario
     */
    private function getClientStats($user): array
    {
        $query = $user->getAccessibleClients();

        return [
            'total' => $query->count(),
            'active' => $query->where('status', 'active')->count(),
            'verified' => $query->whereNotNull('verified_at')->count(),
            'by_type' => $query->select('client_type', DB::raw('count(*) as count'))
                ->groupBy('client_type')
                ->pluck('count', 'client_type')
                ->toArray(),
            'by_country' => $query->join('countries', 'clients.country_id', '=', 'countries.id')
                ->select('countries.name', DB::raw('count(*) as count'))
                ->groupBy('countries.id', 'countries.name')
                ->pluck('count', 'name')
                ->toArray()
        ];
    }

    /**
     * Obtener estadísticas de uso específicas de un cliente
     */
    private function getClientUsageStats(Client $client): array
    {
        return [
            'total_companies' => $client->companyRelations()->where('active', true)->count(),
            'can_edit_companies' => $client->companyRelations()
                ->where('active', true)
                ->where('can_edit', true)
                ->count(),
            'last_activity' => $client->updated_at,
            'verification_status' => $client->verified_at ? 'verified' : 'pending'
        ];
    }

    /**
     * Obtener actividad reciente del cliente
     */
    private function getClientRecentActivity(Client $client, int $limit = 10): array
    {
        return [
            [
                'action' => 'created',
                'date' => $client->created_at,
                'user' => 'Sistema'
            ],
            [
                'action' => 'last_updated',
                'date' => $client->updated_at,
                'user' => 'Sistema'
            ]
        ];
    }
}
