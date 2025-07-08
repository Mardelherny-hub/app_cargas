<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasRoles;

    protected $fillable = [
        'name',
        'email',
        'password',
        'userable_type',    // Polimorfismo
        'userable_id',      // Polimorfismo
        'last_access',
        'active',
        'timezone',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'last_access' => 'datetime',
        'active' => 'boolean',
    ];

    // ========================================
    // RELACIONES POLIMÓRFICAS
    // ========================================

    /**
     * Relación polimórfica con Company o Operator.
     */
    public function userable()
    {
        return $this->morphTo();
    }

    // ========================================
    // NUEVOS MÉTODOS PARA COMPANY ROLES (Roberto's requirements)
    // ========================================

    /**
     * Obtener la empresa asociada al usuario.
     */
    public function getCompany(): ?Company
    {
        // Usuario directo de empresa
        if ($this->userable_type === 'App\Models\Company' && $this->userable_id) {
            return $this->userable;
        }

        // Usuario que es operador con empresa
        if ($this->userable_type === 'App\Models\Operator' && $this->userable_id) {
            return $this->userable->company ?? null;
        }

        return null;
    }

    /**
     * Obtener los roles de la empresa asociada (Roberto's key requirement).
     */
    public function getCompanyRoles(): array
    {
        $company = $this->getCompany();
        return $company ? $company->getRoles() : [];
    }

    /**
     * Verificar si la empresa del usuario tiene un rol específico.
     */
    public function hasCompanyRole(string $role): bool
    {
        $company = $this->getCompany();
        return $company ? $company->hasRole($role) : false;
    }

    /**
     * Verificar si puede usar un webservice específico según los roles de su empresa.
     */
    public function canUseWebservice(string $webservice): bool
    {
        // Super admin puede usar cualquier webservice
        if ($this->hasRole('super-admin')) {
            return true;
        }

        $company = $this->getCompany();
        return $company ? $company->canUseWebservice($webservice) : false;
    }

    /**
     * Verificar si puede realizar una operación específica (Roberto's complete operations).
     */
    public function canPerformOperation(string $operation): bool
    {
        // Super admin puede realizar cualquier operación
        if ($this->hasRole('super-admin')) {
            return true;
        }

        $company = $this->getCompany();
        return $company ? $company->canPerformOperation($operation) : false;
    }

    /**
     * Verificar si puede generar archivos para una operación.
     */
    public function canGenerateFiles(string $operation): bool
    {
        // Super admin puede generar cualquier archivo
        if ($this->hasRole('super-admin')) {
            return true;
        }

        $company = $this->getCompany();
        return $company ? $company->canGenerateFiles($operation) : false;
    }

    /**
     * Verificar si puede usar webservice directo para una operación.
     */
    public function canUseDirectWebservice(string $operation): bool
    {
        // Super admin puede usar cualquier webservice
        if ($this->hasRole('super-admin')) {
            return true;
        }

        $company = $this->getCompany();
        return $company ? $company->canUseDirectWebservice($operation) : false;
    }

    /**
     * Obtener webservices disponibles según la empresa.
     */
    public function getAvailableWebservices(): array
    {
        // Super admin puede usar todos
        if ($this->hasRole('super-admin')) {
            return ['anticipada', 'micdta', 'desconsolidados', 'transbordos'];
        }

        $company = $this->getCompany();
        return $company ? $company->getAvailableWebservices() : [];
    }

    /**
     * Obtener funcionalidades disponibles según los roles de la empresa.
     */
    public function getAvailableFeatures(): array
    {
        // Super admin puede usar todas las funcionalidades
        if ($this->hasRole('super-admin')) {
            return [
                'shipments', 'containers', 'reports', 'manifests',
                'deconsolidations', 'titulo_madre', 'titulo_hijos',
                'transshipments', 'barges', 'position_tracking',
                'anticipada_operations', 'micdta_operations', 'mane_operations',
                'file_generation'
            ];
        }

        $company = $this->getCompany();
        return $company ? $company->getAvailableFeatures() : [];
    }

    // ========================================
    // MÉTODOS ESPECÍFICOS PARA OPERACIONES (Roberto's business operations)
    // ========================================

    /**
     * Verificar si puede trabajar con anticipada.
     */
    public function canWorkWithAnticipada(): bool
    {
        if ($this->hasRole('super-admin')) return true;
        $company = $this->getCompany();
        return $company ? $company->canWorkWithAnticipada() : false;
    }

    /**
     * Verificar si puede trabajar con MIC/DTA.
     */
    public function canWorkWithMicdta(): bool
    {
        if ($this->hasRole('super-admin')) return true;
        $company = $this->getCompany();
        return $company ? $company->canWorkWithMicdta() : false;
    }

    /**
     * Verificar si puede trabajar con Mane.
     */
    public function canWorkWithMane(): bool
    {
        if ($this->hasRole('super-admin')) return true;
        $company = $this->getCompany();
        return $company ? $company->canWorkWithMane() : false;
    }

    /**
     * Verificar si puede generar archivos para Malvina.
     */
    public function canGenerateMalvinaFiles(): bool
    {
        if ($this->hasRole('super-admin')) return true;
        $company = $this->getCompany();
        return $company ? $company->canGenerateMalvinaFiles() : false;
    }

    /**
     * Verificar si puede generar archivos de anticipada para terceros.
     */
    public function canGenerateAnticipadaFiles(): bool
    {
        if ($this->hasRole('super-admin')) return true;
        $company = $this->getCompany();
        return $company ? $company->canGenerateAnticipadaFiles() : false;
    }

    /**
     * Obtener tipos de output disponibles para una operación.
     */
    public function getAvailableOutputTypes(string $operation): array
    {
        if ($this->hasRole('super-admin')) {
            return ['webservice', 'file', 'file_malvina'];
        }

        $company = $this->getCompany();
        return $company ? $company->getAvailableOutputTypes($operation) : [];
    }

    // ========================================
    // MÉTODOS DE PERMISOS ACTUALIZADOS (Roberto's logic)
    // ========================================

    /**
     * Verificar si puede importar (ahora basado en roles de empresa).
     */
    public function canImport(): bool
    {
        // Super admin puede todo
        if ($this->hasRole('super-admin')) {
            return true;
        }

        // Company admin y user pueden importar si su empresa lo permite
        if ($this->hasRole('company-admin') || $this->hasRole('user')) {
            $company = $this->getCompany();
            if ($company && $company->active) {
                // Cualquier empresa puede importar según Roberto
                return true;
            }
        }

        return false;
    }

    /**
     * Verificar si puede exportar (ahora basado en roles de empresa).
     */
    public function canExport(): bool
    {
        // Super admin puede todo
        if ($this->hasRole('super-admin')) {
            return true;
        }

        // Company admin y user pueden exportar si su empresa lo permite
        if ($this->hasRole('company-admin') || $this->hasRole('user')) {
            $company = $this->getCompany();
            if ($company && $company->active) {
                // Cualquier empresa puede exportar según Roberto
                return true;
            }
        }

        return false;
    }

    /**
     * Verificar si puede transferir datos entre empresas (Roberto's requirement).
     */
    public function canTransferBetweenCompanies(): bool
    {
        // Super admin puede todo
        if ($this->hasRole('super-admin')) {
            return true;
        }

        // Company admin puede transferir según Roberto
        if ($this->hasRole('company-admin')) {
            $company = $this->getCompany();
            return $company && $company->canTransferToCompany();
        }

        return false;
    }

    /**
     * Verificar si puede gestionar usuarios (Roberto's hierarchy).
     */
    public function canManageUsers(): bool
    {
        // Super admin gestiona todo
        if ($this->hasRole('super-admin')) {
            return true;
        }

        // Company admin gestiona usuarios de su empresa (Roberto's "jefe")
        if ($this->hasRole('company-admin')) {
            return $this->getCompany() !== null;
        }

        return false;
    }

    /**
     * Verificar si puede gestionar empresas.
     */
    public function canManageCompanies(): bool
    {
        // Solo super admin puede gestionar empresas según Roberto
        return $this->hasRole('super-admin');
    }

    /**
     * Verificar si puede crear empresas.
     */
    public function canCreateCompanies(): bool
    {
        // Solo super admin puede crear empresas según Roberto
        return $this->hasRole('super-admin');
    }

    // ========================================
    // MÉTODOS DE ACCESO Y FILTRADO
    // ========================================

    /**
     * Verificar si puede acceder a una empresa específica.
     */
    public function canAccessCompany(int $companyId): bool
    {
        // Super admin puede acceder a cualquier empresa
        if ($this->hasRole('super-admin')) {
            return true;
        }

        // Otros usuarios solo pueden acceder a su propia empresa
        $company = $this->getCompany();
        return $company && $company->id === $companyId;
    }

    /**
     * Aplicar filtro de empresa a una consulta.
     */
    public function applyCompanyFilter($query): void
    {
        // Super admin ve todo
        if ($this->hasRole('super-admin')) {
            return;
        }

        // Otros usuarios solo ven de su empresa
        $company = $this->getCompany();
        if ($company) {
            $query->where('company_id', $company->id);
        } else {
            // Si no tiene empresa, no ve nada
            $query->whereRaw('1 = 0');
        }
    }

    // ========================================
    // MÉTODOS DE INFORMACIÓN Y DISPLAY
    // ========================================

    /**
     * Obtener el tipo de usuario para mostrar.
     */
    public function getUserTypeDisplay(): string
    {
        if ($this->hasRole('super-admin')) {
            return 'Super Administrador';
        }

        if ($this->hasRole('company-admin')) {
            return 'Administrador de Empresa';
        }

        if ($this->hasRole('user')) {
            return 'Usuario';
        }

        return 'Sin rol asignado';
    }

    /**
     * Obtener información de la empresa para mostrar.
     */
    public function getCompanyDisplay(): string
    {
        $company = $this->getCompany();

        if (!$company) {
            return $this->hasRole('super-admin') ? 'Sistema Central' : 'Sin empresa';
        }

        return $company->full_name;
    }

    /**
     * Obtener roles de empresa para mostrar.
     */
    public function getCompanyRolesDisplay(): string
    {
        $roles = $this->getCompanyRoles();
        return empty($roles) ? 'Sin roles' : implode(', ', $roles);
    }

    /**
     * Obtener webservices disponibles para mostrar.
     */
    public function getWebservicesDisplay(): string
    {
        $webservices = $this->getAvailableWebservices();
        return empty($webservices) ? 'Ninguno' : implode(', ', $webservices);
    }

    // ========================================
    // MÉTODOS DE VALIDACIÓN
    // ========================================

    /**
     * Verificar si el usuario está configurado correctamente.
     */
    public function isProperlyConfigured(): bool
    {
        // Super admin siempre está configurado
        if ($this->hasRole('super-admin')) {
            return true;
        }

        // Company admin y user necesitan empresa
        if ($this->hasRole('company-admin') || $this->hasRole('user')) {
            $company = $this->getCompany();
            return $company && $company->active && !empty($company->getRoles());
        }

        return false;
    }

    /**
     * Obtener errores de configuración.
     */
    public function getConfigurationErrors(): array
    {
        $errors = [];

        // Super admin no tiene errores de configuración
        if ($this->hasRole('super-admin')) {
            return $errors;
        }

        // Verificar empresa
        $company = $this->getCompany();
        if (!$company) {
            $errors[] = 'Usuario sin empresa asociada';
            return $errors;
        }

        if (!$company->active) {
            $errors[] = 'Empresa inactiva';
        }

        $companyRoles = $company->getRoles();
        if (empty($companyRoles)) {
            $errors[] = 'Empresa sin roles asignados';
        }

        // Verificar configuración de empresa por roles
        $companyErrors = $company->validateRoleConfiguration();
        $errors = array_merge($errors, $companyErrors);

        return $errors;
    }

    // ========================================
    // SCOPES
    // ========================================

    /**
     * Scope para usuarios activos.
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    /**
     * Scope para usuarios de una empresa específica.
     */
    public function scopeOfCompany($query, int $companyId)
    {
        return $query->where(function ($q) use ($companyId) {
            // Usuarios directos de empresa
            $q->where(function ($subQ) use ($companyId) {
                $subQ->where('userable_type', 'App\Models\Company')
                     ->where('userable_id', $companyId);
            })
            // Usuarios operadores de empresa
            ->orWhereHas('userable', function ($subQ) use ($companyId) {
                $subQ->where('userable_type', 'App\Models\Operator')
                     ->where('company_id', $companyId);
            });
        });
    }

    /**
     * Scope para usuarios con rol específico.
     */
    public function scopeWithRole($query, string $roleName)
    {
        return $query->whereHas('roles', function ($q) use ($roleName) {
            $q->where('name', $roleName);
        });
    }

    // ========================================
    // MÉTODOS DE AUDITORÍA
    // ========================================

    /**
     * Actualizar último acceso.
     */
    public function updateLastAccess(): void
    {
        $this->update(['last_access' => now()]);

        // También actualizar en la empresa si es necesario
        $company = $this->getCompany();
        if ($company) {
            $company->updateLastAccess();
        }
    }

    /**
     * Verificar si puede cambiar su password (Roberto's requirement).
     */
    public function canChangePassword(): bool
    {
        // Todos los usuarios pueden cambiar su password según Roberto
        return $this->active;
    }
}
