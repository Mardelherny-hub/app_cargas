<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Company;
use App\Models\Country;
use App\Models\DocumentType;
use App\Models\Port;
use App\Models\CustomOffice;
use App\Http\Requests\CreateClientRequest;
use App\Http\Requests\UpdateClientRequest;
use App\Services\ClientValidationService;
use App\Services\TaxIdExtractionService;
use App\Services\ClientSuggestionService;
use App\Jobs\VerifyClientTaxIdJob;
use App\Traits\UserHelper;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * FASE 4 - INTEGRACIÓN CON EMPRESAS | MÓDULO CLIENTES API
 *
 * Controlador API para gestión de clientes
 * Para frontend React/Vue, móvil, y integraciones externas
 * Accesible según middleware de rutas (super-admin o company-admin|user)
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
     * Listar clientes con paginación y filtros
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();

            // Obtener clientes accesibles según permisos
            $query = $user->getAccessibleClients();

            // Aplicar filtros
            $this->applyFilters($query, $request);

            // Configurar paginación
            $perPage = min($request->get('per_page', 15), 100);

            // Eager loading optimizado
            $clients = $query->with([
                'country:id,name,alpha2_code',
                'documentType:id,name,code',
                'primaryPort:id,name,code',
                'customOffice:id,name,code',
                'companyRelations' => function ($q) {
                    $q->where('active', true)
                      ->with('company:id,legal_name,cuit')
                      ->select('id', 'client_id', 'company_id', 'relation_type', 'can_edit');
                }
            ])
            ->orderBy($request->get('sort', 'updated_at'), $request->get('order', 'desc'))
            ->paginate($perPage);

            // Estadísticas adicionales si se solicitan
            $includeStats = $request->boolean('include_stats');
            $stats = $includeStats ? $this->getClientStats($user) : null;

            return response()->json([
                'success' => true,
                'data' => $clients,
                'stats' => $stats,
                'filters' => $this->getAvailableFilters($user)
            ]);

        } catch (\Exception $e) {
            Log::error('Error in API clients index', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener clientes',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mostrar cliente específico
     */
    public function show(Client $client): JsonResponse
    {
        try {
            // Verificar acceso usando UserHelper
            if (!$this->isSuperAdmin() && !Auth::user()->canUseClient($client)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No autorizado para ver este cliente'
                ], 403);
            }

            // Cargar relaciones detalladas
            $client->load([
                'country',
                'documentType',
                'primaryPort',
                'customOffice',
                'companyRelations.company',
                'documentData' => function ($query) {
                    $query->latest()->limit(5);
                }
            ]);

            // Información adicional
            $additionalData = [
                'can_edit' => Auth::user()->canEditClient($client),
                'can_use' => Auth::user()->canUseClient($client),
                'verification_status' => $client->verified_at ? 'verified' : 'pending',
                'last_activity' => $client->updated_at
            ];

            return response()->json([
                'success' => true,
                'data' => $client,
                'additional' => $additionalData
            ]);

        } catch (\Exception $e) {
            Log::error('Error showing client', [
                'client_id' => $client->id,
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener cliente',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Crear nuevo cliente
     */
    public function store(CreateClientRequest $request): JsonResponse
    {
        try {
            // Solo super admin y company admin pueden crear clientes
            if (!$this->isSuperAdmin() && !$this->isCompanyAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No autorizado para crear clientes'
                ], 403);
            }

            DB::beginTransaction();

            $user = Auth::user();
            $data = $request->validated();

            // Asignar empresa creadora
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

            // Programar verificación asíncrona
            if ($client->tax_id) {
                VerifyClientTaxIdJob::dispatch($client);
            }

            // Log de actividad
            $user->logClientActivity($client, 'created', [
                'via' => 'api',
                'tax_id' => $client->tax_id
            ]);

            DB::commit();

            // Cargar relaciones para respuesta
            $client->load(['country', 'documentType', 'companyRelations.company']);

            return response()->json([
                'success' => true,
                'message' => 'Cliente creado exitosamente',
                'data' => $client
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creating client via API', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
                'data' => $request->validated()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al crear cliente',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar cliente existente
     */
    public function update(UpdateClientRequest $request, Client $client): JsonResponse
    {
        try {
            // Verificar permisos usando UserHelper
            if (!$this->isSuperAdmin() && !Auth::user()->canEditClient($client)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No autorizado para editar este cliente'
                ], 403);
            }

            DB::beginTransaction();

            $user = Auth::user();
            $data = $request->validated();

            $oldTaxId = $client->tax_id;
            $client->update($data);

            // Re-verificar si cambió el CUIT/RUC
            if ($client->wasChanged('tax_id')) {
                $client->update(['verified_at' => null]);
                VerifyClientTaxIdJob::dispatch($client);
            }

            // Log de actividad
            $user->logClientActivity($client, 'updated', [
                'via' => 'api',
                'changes' => $client->getChanges(),
                'old_tax_id' => $oldTaxId
            ]);

            DB::commit();

            // Cargar relaciones actualizadas
            $client->fresh()->load(['country', 'documentType', 'companyRelations.company']);

            return response()->json([
                'success' => true,
                'message' => 'Cliente actualizado exitosamente',
                'data' => $client
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error updating client via API', [
                'client_id' => $client->id,
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar cliente',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar cliente (soft delete)
     */
    public function destroy(Client $client): JsonResponse
    {
        try {
            // Solo super admin o company admin que creó el cliente puede eliminar
            $user = Auth::user();
            if (!$this->isSuperAdmin() &&
                (!$this->isCompanyAdmin() || $client->created_by_company_id !== $this->getUserCompanyId())) {
                return response()->json([
                    'success' => false,
                    'message' => 'No autorizado para eliminar este cliente'
                ], 403);
            }

            DB::beginTransaction();

            // Desactivar relaciones con empresas
            $client->companyRelations()->update(['active' => false]);

            // Soft delete
            $client->delete();

            // Log de actividad
            $user->logClientActivity($client, 'deleted', [
                'via' => 'api',
                'tax_id' => $client->tax_id
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Cliente eliminado exitosamente'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error deleting client via API', [
                'client_id' => $client->id,
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar cliente',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // =====================================================
    // ENDPOINTS ESPECÍFICOS DE CLIENTES
    // =====================================================

    /**
     * Verificar CUIT/RUC de cliente
     */
    public function verify(Client $client): JsonResponse
    {
        try {
            // Verificar permisos usando UserHelper
            if (!$this->isSuperAdmin() && !Auth::user()->canEditClient($client)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No autorizado para verificar este cliente'
                ], 403);
            }

            $user = Auth::user();

            // Ejecutar verificación
            VerifyClientTaxIdJob::dispatch($client);

            // Log de actividad
            $user->logClientActivity($client, 'verification_requested', ['via' => 'api']);

            return response()->json([
                'success' => true,
                'message' => 'Verificación de CUIT/RUC iniciada',
                'data' => [
                    'client_id' => $client->id,
                    'status' => 'verification_pending'
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error verifying client via API', [
                'client_id' => $client->id,
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al verificar cliente',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cambiar estado del cliente
     */
    public function toggleStatus(Client $client): JsonResponse
    {
        try {
            // Verificar permisos usando UserHelper
            if (!$this->isSuperAdmin() && !Auth::user()->canEditClient($client)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No autorizado para cambiar estado de este cliente'
                ], 403);
            }

            $user = Auth::user();
            $newStatus = $client->status === 'active' ? 'inactive' : 'active';

            $client->update(['status' => $newStatus]);

            // Log de actividad
            $user->logClientActivity($client, 'status_changed', [
                'via' => 'api',
                'old_status' => $client->getOriginal('status'),
                'new_status' => $newStatus
            ]);

            return response()->json([
                'success' => true,
                'message' => "Cliente {$newStatus}",
                'data' => [
                    'client_id' => $client->id,
                    'status' => $newStatus
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error toggling client status via API', [
                'client_id' => $client->id,
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al cambiar estado',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Buscar clientes por CUIT/RUC o nombre
     */
    public function search(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'q' => 'required|string|min:3|max:50',
                'limit' => 'integer|min:1|max:50'
            ]);

            $user = Auth::user();
            $query = $request->get('q');
            $limit = $request->get('limit', 10);

            // Buscar en clientes accesibles
            $clients = $user->getAccessibleClients()
                ->where(function ($q) use ($query) {
                    $q->where('tax_id', 'like', "%{$query}%")
                      ->orWhere('legal_name', 'like', "%{$query}%");
                })
                ->where('status', 'active')
                ->with(['country:id,name,alpha2_code', 'documentType:id,name'])
                ->limit($limit)
                ->get();

            return response()->json([
                'success' => true,
                'data' => $clients,
                'query' => $query,
                'total' => $clients->count()
            ]);

        } catch (\Exception $e) {
            Log::error('Error searching clients via API', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
                'query' => $request->get('q')
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error en búsqueda',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener sugerencias de clientes mientras se escribe
     */
    public function suggestions(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'q' => 'required|string|min:2|max:20',
                'type' => 'in:tax_id,legal_name,both'
            ]);

            $user = Auth::user();
            $query = $request->get('q');
            $type = $request->get('type', 'both');

            // Usar el método searchClients() del User que SÍ existe
            $suggestions = $user->searchClients($query, 10);

            // Si es específicamente por nombre, usar el servicio que existe
            if ($type === 'legal_name') {
                $companyId = $user->hasRole('super-admin') ? null : $this->getUserCompanyId();
                $nameMatches = $this->suggestionService->suggestFromName($query, $companyId);

                // Convertir formato del servicio al formato esperado
                $suggestions = $nameMatches->map(function ($match) {
                    return [
                        'id' => $match['client_id'],
                        'tax_id' => $match['tax_id'],
                        'legal_name' => $match['legal_name'],
                        'client_type' => $match['client_type'] ?? null
                    ];
                });
            }

            return response()->json([
                'success' => true,
                'data' => $suggestions,
                'query' => $query,
                'type' => $type
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting client suggestions via API', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener sugerencias',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener datos para formularios (países, tipos doc, etc.)
     */
    public function formData(): JsonResponse
    {
        try {
            $user = Auth::user();

            $data = [
                'countries' => Country::where('active', true)
                    ->select('id', 'name', 'alpha2_code', 'document_format')
                    ->orderBy('name')
                    ->get(),

                'document_types' => DocumentType::where('active', true)
                    ->with('country:id,name,alpha2_code')
                    ->select('id', 'name', 'code', 'country_id', 'validation_pattern')
                    ->orderBy('name')
                    ->get(),

                'ports' => Port::where('active', true)
                    ->with('country:id,name,alpha2_code')
                    ->select('id', 'name', 'code', 'country_id')
                    ->orderBy('name')
                    ->get(),

                'customs_offices' => CustomOffice::where('active', true)
                    ->with('country:id,name,alpha2_code')
                    ->select('id', 'name', 'code', 'country_id')
                    ->orderBy('name')
                    ->get(),

                'companies' => $user->hasRole('super-admin')
                    ? Company::where('active', true)
                        ->select('id', 'legal_name', 'cuit')
                        ->orderBy('legal_name')
                        ->get()
                    : collect([$this->getUserCompany()])->filter()->map(function ($company) {
                        return [
                            'id' => $company->id,
                            'legal_name' => $company->legal_name,
                            'cuit' => $company->cuit
                        ];
                    }),

                'client_types' => Client::CLIENT_TYPES,
                'statuses' => Client::STATUSES
            ];

            return response()->json([
                'success' => true,
                'data' => $data
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting form data via API', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener datos del formulario',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Validar CUIT/RUC antes de guardar
     */
    public function validateTaxId(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'tax_id' => 'required|string|max:20',
                'country_id' => 'required|exists:countries,id'
            ]);

            $taxId = $request->get('tax_id');
            $countryId = $request->get('country_id');

            // Obtener país
            $country = Country::find($countryId);

            // Validar formato
            $validation = $this->validationService->validateTaxIdForCountry($taxId, $country->alpha2_code);

            // Verificar si ya existe
            $exists = Client::where('tax_id', $taxId)
                ->where('country_id', $countryId)
                ->exists();

            return response()->json([
                'success' => true,
                'data' => [
                    'valid' => $validation['valid'],
                    'message' => $validation['message'] ?? null,
                    'exists' => $exists,
                    'formatted' => $validation['formatted'] ?? $taxId
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error validating tax ID via API', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al validar CUIT/RUC',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // =====================================================
    // MÉTODOS AUXILIARES PRIVADOS
    // =====================================================

    /**
     * Aplicar filtros a la consulta
     */
    private function applyFilters($query, Request $request): void
    {
        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('tax_id', 'like', "%{$search}%")
                  ->orWhere('legal_name', 'like', "%{$search}%");
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

        if ($request->filled('verified')) {
            if ($request->boolean('verified')) {
                $query->whereNotNull('verified_at');
            } else {
                $query->whereNull('verified_at');
            }
        }

        if ($request->filled('company_id')) {
            $query->whereHas('companyRelations', function ($q) use ($request) {
                $q->where('company_id', $request->get('company_id'))
                  ->where('active', true);
            });
        }
    }

    /**
     * Obtener filtros disponibles para el usuario
     */
    private function getAvailableFilters($user): array
    {
        return [
            'countries' => Country::where('active', true)
                ->select('id', 'name', 'alpha2_code')
                ->orderBy('name')
                ->get(),

            'client_types' => Client::CLIENT_TYPES,
            'statuses' => Client::STATUSES,

            'companies' => $user->hasRole('super-admin')
                ? Company::where('active', true)
                    ->select('id', 'legal_name')
                    ->orderBy('legal_name')
                    ->get()
                : collect([$this->getUserCompany()])->filter()->map(function ($company) {
                    return [
                        'id' => $company->id,
                        'legal_name' => $company->legal_name
                    ];
                })
        ];
    }

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
}
