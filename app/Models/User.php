<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditableTrait;

class User extends Authenticatable implements Auditable
{
    use HasApiTokens;
    use HasFactory;
    use HasProfilePhoto;
    use Notifiable;
    use TwoFactorAuthenticatable;
    use HasRoles;
    use AuditableTrait;

    protected $fillable = [
        'name',
        'email',
        'password',
        'userable_type',
        'userable_id',
        'last_access',
        'active',
        'timezone',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_access' => 'datetime',
        'active' => 'boolean',
        'password' => 'hashed',
    ];

    protected $appends = [
        'profile_photo_url',
    ];

    /**
     * Polymorphic relationship - can be Company or Operator
     */
    public function userable()
    {
        return $this->morphTo();
    }

    /**
     * Check if user is super admin
     */
    public function isSuperAdmin(): bool
    {
        return $this->hasRole('super-admin');
    }

    /**
     * Check if user is company admin
     */
    public function isCompanyAdmin(): bool
    {
        return $this->hasRole('company-admin');
    }

    /**
     * Check if user is regular user
     */
    public function isUser(): bool
    {
        return $this->hasRole('user');
    }

    /**
     * Check if user is an operator (based on userable_type)
     */
    public function isOperator(): bool
    {
        return $this->userable_type === 'App\Models\Operator';
    }

    /**
     * Get the company associated with the user
     */
    public function getCompanyAttribute()
    {
        if ($this->userable_type === 'App\Models\Company') {
            return $this->userable;
        }

        if ($this->userable_type === 'App\Models\Operator' && $this->userable->company) {
            return $this->userable->company;
        }

        return null;
    }

    /**
     * Check if user belongs to a specific company
     */
    public function belongsToCompany($companyId): bool
    {
        $company = $this->company;
        return $company && $company->id == $companyId;
    }

    /**
     * Update last access timestamp
     */
    public function updateLastAccess()
    {
        $this->update(['last_access' => now()]);
    }

    /**
     * Scope for active users
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    /**
     * Scope for users from a specific company
     */
    public function scopeFromCompany($query, $companyId)
    {
        return $query->where(function ($q) use ($companyId) {
            $q->where('userable_type', 'App\Models\Company')
                ->where('userable_id', $companyId)
                ->orWhereHas('userable', function ($subQuery) use ($companyId) {
                    $subQuery->where('company_id', $companyId);
                });
        });
    }

    /**
     * Get full name for display
     */
    public function getFullNameAttribute(): string
    {
        if ($this->userable_type === 'App\Models\Operator') {
            return trim($this->userable->first_name . ' ' . $this->userable->last_name);
        }

        if ($this->userable_type === 'App\Models\Company') {
            return $this->userable->business_name;
        }

        return $this->name;
    }

    /**
     * Get user type description
     */
    public function getUserTypeAttribute(): string
    {
        if ($this->isSuperAdmin()) {
            return 'Super Administrator';
        }

        if ($this->isCompanyAdmin()) {
            return 'Company Administrator';
        }

        if ($this->isUser()) {
            // Si es un operador, especificarlo
            if ($this->isOperator()) {
                return 'Operator';
            }
            return 'User';
        }

        return 'User';
    }

    /**
     * Get company roles for this user
     */
    public function getCompanyRoles(): array
    {
        $company = $this->company;
        return $company ? ($company->company_roles ?? []) : [];
    }

    /**
     * Get operator permissions for this user
     */
    public function getOperatorPermissions(): array
    {
        if (!$this->isOperator() || !$this->userable) {
            return [
                'can_import' => false,
                'can_export' => false,
                'can_transfer' => false,
            ];
        }

        return [
            'can_import' => $this->userable->can_import ?? false,
            'can_export' => $this->userable->can_export ?? false,
            'can_transfer' => $this->userable->can_transfer ?? false,
        ];
    }

    /**
     * Check if user can perform a specific action
     */
    public function canPerform(string $action): bool
    {
        // Super admin can do everything
        if ($this->isSuperAdmin()) {
            return true;
        }

        // Company admin can do everything in their company
        if ($this->isCompanyAdmin()) {
            return true;
        }

        // Regular users have limited permissions
        if ($this->isUser()) {
            $companyRoles = $this->getCompanyRoles();
            $operatorPermissions = $this->getOperatorPermissions();

            switch ($action) {
                case 'import':
                    return $operatorPermissions['can_import'];
                case 'export':
                    return $operatorPermissions['can_export'];
                case 'transfer':
                    return $operatorPermissions['can_transfer'];
                case 'view_cargas':
                    return in_array('Cargas', $companyRoles);
                case 'view_deconsolidation':
                    return in_array('Desconsolidador', $companyRoles);
                case 'view_transfers':
                    return in_array('Transbordos', $companyRoles);
                case 'view_reports':
                    return true; // All users can view reports
                default:
                    return false;
            }
        }

        return false;
    }

    /**
     * Get user information for display
     */
    public function getUserInfo(): array
    {
        $company = $this->company;

        $info = [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'type' => $this->getUserTypeAttribute(),
            'active' => $this->active,
            'last_access' => $this->last_access,
            'roles' => $this->roles->pluck('name')->toArray(),
        ];

        // Company information
        if ($company) {
            $info['company'] = [
                'id' => $company->id,
                'name' => $company->business_name,
                'roles' => $company->company_roles ?? [],
                'active' => $company->active,
            ];
        }

        // Operator information
        if ($this->isOperator() && $this->userable) {
            $info['operator'] = [
                'id' => $this->userable->id,
                'full_name' => $this->userable->first_name . ' ' . $this->userable->last_name,
                'can_import' => $this->userable->can_import ?? false,
                'can_export' => $this->userable->can_export ?? false,
                'can_transfer' => $this->userable->can_transfer ?? false,
                'active' => $this->userable->active,
            ];
        }

        return $info;
    }

    /**
     * Audit configuration
     */
    protected $auditInclude = [
        'email',
        'userable_type',
        'userable_id',
        'active',
    ];

    public function getDescriptionForEvent(string $eventName): string
    {
        return "User {$this->email} was {$eventName}";
    }

    /**
     * Determina si el usuario está correctamente configurado según su tipo y rol.
     */
    public function isProperlyConfigured(): bool
    {
        // Debe estar activo y tener al menos un rol
        if (!$this->active || $this->roles->isEmpty()) {
            return false;
        }

        // Si es operador, debe tener datos completos y empresa activa
        if ($this->isOperator()) {
            $operator = $this->userable;
            if (!$operator || !$operator->active) {
                return false;
            }
            $company = $operator->company ?? null;
            if (!$company || !$company->active) {
                return false;
            }
        }

        // Si es company-admin, debe tener empresa activa
        if ($this->isCompanyAdmin()) {
            $company = $this->company;
            if (!$company || !$company->active) {
                return false;
            }
        }

        // Si es super-admin, solo debe estar activo
        return true;
    }


/**
 * FASE 1 - MÓDULO EMPRESAS Y CLIENTES
 *
 */

// =====================================================
// MÉTODOS DE ACCESO A CLIENTES (FASE 1)
// =====================================================

/**
 * Obtener clientes accesibles según empresa y permisos del usuario.
 * Implementa criterio: "Usuarios ven solo clientes de su empresa"
 */
public function getAccessibleClients()
{
    // Super admin ve todos los clientes
    if ($this->hasRole('super-admin')) {
        return Client::query();
    }

    // Company admin y users ven solo clientes de su empresa
    if ($this->hasRole(['company-admin', 'user'])) {
        $company = $this->getUserCompany();

        if (!$company) {
            return Client::whereRaw('1 = 0'); // Query vacío
        }

        return Client::whereHas('companyRelations', function ($query) use ($company) {
            $query->where('company_id', $company->id)
                  ->where('active', true);
        });
    }

    return Client::whereRaw('1 = 0'); // Query vacío por defecto
}

/**
 * Verificar si el usuario puede editar un cliente específico.
 * Implementa criterio: "Permisos de edición respetan relaciones empresa-cliente"
 */
public function canEditClient(Client $client): bool
{
    // Super admin puede editar todos los clientes
    if ($this->hasRole('super-admin')) {
        return true;
    }

    // Company admin puede editar si su empresa tiene permisos
    if ($this->hasRole('company-admin')) {
        $company = $this->getUserCompany();

        if (!$company) {
            return false;
        }

        return $company->canManageClient($client);
    }

    // Users NO pueden editar clientes (solo usar)
    return false;
}

/**
 * Verificar si el usuario puede usar un cliente en operaciones.
 */
public function canUseClient(Client $client): bool
{
    // Super admin puede usar cualquier cliente
    if ($this->hasRole('super-admin')) {
        return true;
    }

    // Company admin y users pueden usar clientes de su empresa
    if ($this->hasRole(['company-admin', 'user'])) {
        $company = $this->getUserCompany();

        if (!$company) {
            return false;
        }

        return $company->hasClientRelation($client);
    }

    return false;
}

/**
 * Obtener clientes recientes del usuario (actividad reciente).
 */
public function getRecentClients(int $limit = 10)
{
    $accessibleClients = $this->getAccessibleClients();

    return $accessibleClients
        ->with(['country', 'companyRelations' => function ($query) {
            $query->where('active', true)
                  ->orderBy('last_activity_at', 'desc');
        }])
        ->orderBy('updated_at', 'desc')
        ->limit($limit)
        ->get();
}

// =====================================================
// MÉTODOS ESPECÍFICOS POR ROL
// =====================================================

/**
 * Obtener clientes editables para el usuario.
 * Solo company-admin puede editar, users solo pueden usar.
 */
public function getEditableClients()
{
    // Super admin puede editar todos
    if ($this->hasRole('super-admin')) {
        return Client::query();
    }

    // Company admin puede editar clientes donde su empresa tiene permiso
    if ($this->hasRole('company-admin')) {
        $company = $this->getUserCompany();

        if (!$company) {
            return Client::whereRaw('1 = 0');
        }

        return Client::whereHas('companyRelations', function ($query) use ($company) {
            $query->where('company_id', $company->id)
                  ->where('can_edit', true)
                  ->where('active', true);
        });
    }

    // Users no pueden editar
    return Client::whereRaw('1 = 0');
}

/**
 * Obtener clientes para operaciones específicas del usuario.
 * Considera permisos de operador (can_import, can_export, can_transfer).
 */
public function getClientsForOperation(string $operation)
{
    $accessibleClients = $this->getAccessibleClients();

    // Filtrar según la operación y permisos del usuario
    if ($this->hasRole('user') && $this->isOperator()) {
        $operator = $this->userable;

        switch ($operation) {
            case 'import':
                if (!$operator->can_import) {
                    return Client::whereRaw('1 = 0');
                }
                // Solo consignatarios y notificados para importación
                $accessibleClients->whereIn('client_type', ['consignee', 'notify_party']);
                break;

            case 'export':
                if (!$operator->can_export) {
                    return Client::whereRaw('1 = 0');
                }
                // Solo cargadores y propietarios para exportación
                $accessibleClients->whereIn('client_type', ['shipper', 'owner']);
                break;

            case 'transfer':
                if (!$operator->can_transfer) {
                    return Client::whereRaw('1 = 0');
                }
                // Todos los tipos para transferencia
                break;
        }
    }

    return $accessibleClients->where('status', 'active');
}

// =====================================================
// MÉTODOS DE BÚSQUEDA Y FILTROS
// =====================================================

/**
 * Buscar clientes accesibles por CUIT/RUC.
 */
public function findAccessibleClientByTaxId(string $taxId): ?Client
{
    return $this->getAccessibleClients()
        ->where('tax_id', $taxId)
        ->where('status', 'active')
        ->first();
}

/**
 * Obtener clientes por país (solo accesibles).
 */
public function getClientsByCountry(string $countryCode)
{
    return $this->getAccessibleClients()
        ->whereHas('country', function ($query) use ($countryCode) {
            $query->where('iso_code', $countryCode);
        })
        ->where('status', 'active')
        ->get();
}

/**
 * Obtener clientes por tipo (solo accesibles).
 */
public function getClientsByType(string $clientType)
{
    return $this->getAccessibleClients()
        ->where('client_type', $clientType)
        ->where('status', 'active')
        ->get();
}

/**
 * Buscar clientes con autocompletado.
 */
public function searchClients(string $search, int $limit = 10)
{
    return $this->getAccessibleClients()
        ->where(function ($query) use ($search) {
            $query->where('legal_name', 'like', "%{$search}%")
                  ->orWhere('tax_id', 'like', "%{$search}%");
        })
        ->where('status', 'active')
        ->limit($limit)
        ->get(['id', 'tax_id', 'legal_name', 'client_type']);
}

// =====================================================
// MÉTODOS PARA WEBSERVICES Y OPERACIONES
// =====================================================

/**
 * Obtener clientes compatibles para webservices del usuario.
 */
public function getWebserviceCompatibleClients(string $wsType = null)
{
    $company = $this->getUserCompany();

    if (!$company) {
        return collect();
    }

    return $company->getClientsForWebservice($wsType);
}

/**
 * Verificar si el usuario puede usar un cliente para webservices.
 */
public function canUseClientForWebservice(Client $client, string $wsType): bool
{
    if (!$this->canUseClient($client)) {
        return false;
    }

    $company = $this->getUserCompany();
    if (!$company) {
        return false;
    }

    $config = $company->getClientWebserviceConfig($client);

    if (!$config) {
        return false;
    }

    switch ($wsType) {
        case 'anticipada':
            return $config['can_use_anticipada'];
        case 'micdta':
            return $config['can_use_micdta'];
        case 'desconsolidados':
            return $config['can_use_desconsolidados'];
        case 'transbordos':
            return $config['can_use_transbordos'];
        default:
            return false;
    }
}

// =====================================================
// MÉTODOS DE ACTIVIDAD Y AUDITORÍA
// =====================================================

/**
 * Registrar actividad con un cliente.
 */
public function logClientActivity(Client $client, string $action, array $details = []): bool
{
    $company = $this->getUserCompany();

    if (!$company) {
        return false;
    }

    // Actualizar actividad en la relación empresa-cliente
    $company->updateClientActivity($client);

    // Opcionalmente guardar log detallado
    logger()->info('Client activity', [
        'user_id' => $this->id,
        'company_id' => $company->id,
        'client_id' => $client->id,
        'action' => $action,
        'details' => $details,
        'timestamp' => now()->toISOString()
    ]);

    return true;
}

/**
 * Obtener estadísticas de uso de clientes del usuario.
 */
public function getClientUsageStats(): array
{
    $company = $this->getUserCompany();

    if (!$company) {
        return [
            'total_accessible' => 0,
            'total_editable' => 0,
            'by_type' => [],
            'by_country' => []
        ];
    }

    $accessible = $this->getAccessibleClients()->get();
    $editable = $this->getEditableClients()->get();

    return [
        'total_accessible' => $accessible->count(),
        'total_editable' => $editable->count(),
        'by_type' => $accessible->countBy('client_type'),
        'by_country' => $accessible->countBy('country.iso_code'),
        'verified' => $accessible->whereNotNull('verified_at')->count(),
        'unverified' => $accessible->whereNull('verified_at')->count()
    ];
}

// =====================================================
// MÉTODOS AUXILIARES
// =====================================================

/**
 * Verificar si el usuario tiene permisos específicos sobre clientes.
 */
public function hasClientPermission(string $permission, Client $client = null): bool
{
    switch ($permission) {
        case 'view_any':
            return $this->hasRole(['super-admin', 'company-admin', 'user']);

        case 'create':
            return $this->hasRole(['super-admin', 'company-admin']);

        case 'edit':
            return $client ? $this->canEditClient($client) :
                   $this->hasRole(['super-admin', 'company-admin']);

        case 'delete':
            return $this->hasRole('super-admin') ||
                   ($this->hasRole('company-admin') && $client &&
                    $client->created_by_company_id === $this->getUserCompanyId());

        case 'verify':
            return $client ? $this->canEditClient($client) :
                   $this->hasRole(['super-admin', 'company-admin']);

        case 'use':
            return $client ? $this->canUseClient($client) : true;

        default:
            return false;
    }
}

/**
 * Obtener clientes favoritos/más usados del usuario.
 */
public function getFavoriteClients(int $limit = 5)
{
    // Por ahora basado en actividad reciente
    // En el futuro se puede implementar sistema de favoritos real
    return $this->getRecentClients($limit);
}
}
