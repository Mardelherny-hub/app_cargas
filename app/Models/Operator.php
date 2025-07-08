<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Operator extends Model
{
    use HasFactory;

    protected $fillable = [
        'first_name',
        'last_name',
        'document_number',
        'phone',
        'position',
        'company_id',
        'type',
        'special_permissions',
        'can_import',
        'can_export',
        'can_transfer',
        'active',
        'created_date',
        'last_access',
    ];

    protected $casts = [
        'special_permissions' => 'array',
        'can_import' => 'boolean',
        'can_export' => 'boolean',
        'can_transfer' => 'boolean',
        'active' => 'boolean',
        'created_date' => 'datetime',
        'last_access' => 'datetime',
    ];

    // ========================================
    // RELACIONES
    // ========================================

    /**
     * Usuario asociado al operador.
     */
    public function user()
    {
        return $this->morphOne(User::class, 'userable');
    }

    /**
     * Empresa asociada (solo para operadores externos).
     */
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    // ========================================
    // MÉTODOS ACTUALIZADOS PARA COMPANY ROLES (Roberto's requirements)
    // ========================================

    /**
     * Verificar si puede importar (considerando roles de empresa).
     */
    public function canImport(): bool
    {
        // Operadores internos pueden importar siempre
        if ($this->type === 'internal') {
            return $this->can_import;
        }

        // Operadores externos necesitan tanto permiso individual como que su empresa esté activa
        if ($this->type === 'external') {
            if (!$this->can_import) {
                return false;
            }

            // Verificar que la empresa esté activa y tenga roles
            $company = $this->company;
            return $company &&
                   $company->active &&
                   !empty($company->getRoles());
        }

        return false;
    }

    /**
     * Verificar si puede exportar (considerando roles de empresa).
     */
    public function canExport(): bool
    {
        // Operadores internos pueden exportar siempre
        if ($this->type === 'internal') {
            return $this->can_export;
        }

        // Operadores externos necesitan tanto permiso individual como que su empresa esté activa
        if ($this->type === 'external') {
            if (!$this->can_export) {
                return false;
            }

            // Verificar que la empresa esté activa y tenga roles
            $company = $this->company;
            return $company &&
                   $company->active &&
                   !empty($company->getRoles());
        }

        return false;
    }

    /**
     * Verificar si puede transferir entre empresas (Roberto's requirement).
     */
    public function canTransferBetweenCompanies(): bool
    {
        // Operadores internos siempre pueden transferir (manejan múltiples empresas)
        if ($this->type === 'internal') {
            return $this->can_transfer;
        }

        // Operadores externos solo si tienen permiso Y su empresa permite transferencias
        if ($this->type === 'external') {
            if (!$this->can_transfer) {
                return false;
            }

            $company = $this->company;
            return $company && $company->canTransferToCompany();
        }

        return false;
    }

    /**
     * Verificar si puede usar un webservice específico.
     */
    public function canUseWebservice(string $webservice): bool
    {
        // Operadores internos pueden usar cualquier webservice
        if ($this->type === 'internal') {
            return true;
        }

        // Operadores externos dependen de los roles de su empresa
        if ($this->type === 'external') {
            $company = $this->company;
            return $company ? $company->canUseWebservice($webservice) : false;
        }

        return false;
    }

    /**
     * Obtener webservices disponibles según la empresa (Roberto's company roles).
     */
    public function getAvailableWebservices(): array
    {
        // Operadores internos pueden usar todos
        if ($this->type === 'internal') {
            return ['anticipada', 'micdta', 'desconsolidados', 'transbordos'];
        }

        // Operadores externos según su empresa
        if ($this->type === 'external') {
            $company = $this->company;
            return $company ? $company->getAvailableWebservices() : [];
        }

        return [];
    }

    /**
     * Obtener funcionalidades disponibles según la empresa.
     */
    public function getAvailableFeatures(): array
    {
        // Operadores internos pueden usar todas
        if ($this->type === 'internal') {
            return [
                'shipments', 'containers', 'reports', 'manifests',
                'deconsolidations', 'titulo_madre', 'titulo_hijos',
                'transshipments', 'barges', 'position_tracking'
            ];
        }

        // Operadores externos según su empresa
        if ($this->type === 'external') {
            $company = $this->company;
            return $company ? $company->getAvailableFeatures() : [];
        }

        return [];
    }

    /**
     * Verificar si puede acceder a una funcionalidad específica.
     */
    public function canUseFeature(string $feature): bool
    {
        $availableFeatures = $this->getAvailableFeatures();
        return in_array($feature, $availableFeatures, true);
    }

    // ========================================
    // MÉTODOS DE INFORMACIÓN Y DISPLAY
    // ========================================

    /**
     * Obtener nombre completo del operador.
     */
    public function getFullNameAttribute(): string
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }

    /**
     * Obtener tipo de operador para mostrar.
     */
    public function getTypeDisplayAttribute(): string
    {
        return $this->type === 'internal' ? 'Operador Interno' : 'Operador Externo';
    }

    /**
     * Obtener empresa para mostrar.
     */
    public function getCompanyDisplayAttribute(): string
    {
        if ($this->type === 'internal') {
            return 'Sistema Central';
        }

        $company = $this->company;
        return $company ? $company->full_name : 'Sin empresa';
    }

    /**
     * Obtener roles de empresa para mostrar (solo operadores externos).
     */
    public function getCompanyRolesDisplayAttribute(): string
    {
        if ($this->type === 'internal') {
            return 'Todos los roles';
        }

        $company = $this->company;
        if (!$company) {
            return 'Sin empresa';
        }

        $roles = $company->getRoles();
        return empty($roles) ? 'Sin roles' : implode(', ', $roles);
    }

    /**
     * Obtener permisos individuales para mostrar.
     */
    public function getIndividualPermissionsAttribute(): array
    {
        return [
            'import' => $this->can_import,
            'export' => $this->can_export,
            'transfer' => $this->can_transfer,
        ];
    }

    /**
     * Obtener permisos efectivos (considerando empresa) para mostrar.
     */
    public function getEffectivePermissionsAttribute(): array
    {
        return [
            'import' => $this->canImport(),
            'export' => $this->canExport(),
            'transfer' => $this->canTransferBetweenCompanies(),
        ];
    }

    // ========================================
    // MÉTODOS DE VALIDACIÓN
    // ========================================

    /**
     * Verificar si el operador está configurado correctamente.
     */
    public function isProperlyConfigured(): bool
    {
        if (!$this->active) {
            return false;
        }

        // Operadores internos solo necesitan estar activos
        if ($this->type === 'internal') {
            return true;
        }

        // Operadores externos necesitan empresa activa con roles
        if ($this->type === 'external') {
            $company = $this->company;
            return $company &&
                   $company->active &&
                   !empty($company->getRoles()) &&
                   $company->isReadyToOperate();
        }

        return false;
    }

    /**
     * Obtener errores de configuración.
     */
    public function getConfigurationErrors(): array
    {
        $errors = [];

        if (!$this->active) {
            $errors[] = 'Operador inactivo';
        }

        // Verificaciones específicas para operadores externos
        if ($this->type === 'external') {
            if (!$this->company_id) {
                $errors[] = 'Operador externo sin empresa asociada';
                return $errors;
            }

            $company = $this->company;
            if (!$company) {
                $errors[] = 'Empresa no encontrada';
                return $errors;
            }

            if (!$company->active) {
                $errors[] = 'Empresa inactiva';
            }

            $companyRoles = $company->getRoles();
            if (empty($companyRoles)) {
                $errors[] = 'Empresa sin roles asignados';
            }

            // Agregar errores de configuración de empresa
            $companyErrors = $company->validateRoleConfiguration();
            $errors = array_merge($errors, $companyErrors);
        }

        return $errors;
    }

    /**
     * Verificar si tiene permisos especiales.
     */
    public function hasSpecialPermission(string $permission): bool
    {
        $specialPermissions = $this->special_permissions ?? [];
        return in_array($permission, $specialPermissions, true);
    }

    // ========================================
    // SCOPES
    // ========================================

    /**
     * Scope para operadores activos.
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    /**
     * Scope para operadores por tipo.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope para operadores internos.
     */
    public function scopeInternal($query)
    {
        return $query->where('type', 'internal');
    }

    /**
     * Scope para operadores externos.
     */
    public function scopeExternal($query)
    {
        return $query->where('type', 'external');
    }

    /**
     * Scope para operadores de una empresa específica.
     */
    public function scopeOfCompany($query, int $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    /**
     * Scope para operadores con permisos específicos.
     */
    public function scopeWithPermission($query, string $permission)
    {
        return $query->where("can_{$permission}", true);
    }

    /**
     * Scope para operadores configurados correctamente.
     */
    public function scopeProperlyConfigured($query)
    {
        return $query->where('active', true)
            ->where(function ($q) {
                // Operadores internos están siempre configurados si están activos
                $q->where('type', 'internal')
                  // O operadores externos con empresa activa
                  ->orWhere(function ($subQ) {
                      $subQ->where('type', 'external')
                           ->whereHas('company', function ($companyQ) {
                               $companyQ->where('active', true)
                                        ->whereJsonLength('company_roles', '>', 0);
                           });
                  });
            });
    }

    // ========================================
    // MÉTODOS DE AUDITORÍA Y ACTIVIDAD
    // ========================================

    /**
     * Actualizar último acceso.
     */
    public function updateLastAccess(): void
    {
        $this->update(['last_access' => now()]);
    }

    /**
     * Verificar si el operador ha estado activo recientemente.
     */
    public function isRecentlyActive(int $days = 30): bool
    {
        if (!$this->last_access) {
            return false;
        }

        return $this->last_access->gte(now()->subDays($days));
    }

    /**
     * Obtener días desde último acceso.
     */
    public function getDaysSinceLastAccessAttribute(): ?int
    {
        if (!$this->last_access) {
            return null;
        }

        return $this->last_access->diffInDays(now());
    }

    // ========================================
    // FACTORY Y TESTING HELPERS
    // ========================================

    /**
     * Crear operador interno de prueba.
     */
    public static function createInternalForTesting(array $attributes = []): self
    {
        return self::factory()->internal()->create($attributes);
    }

    /**
     * Crear operador externo de prueba.
     */
    public static function createExternalForTesting(Company $company, array $attributes = []): self
    {
        return self::factory()->forCompany($company)->create($attributes);
    }
}
