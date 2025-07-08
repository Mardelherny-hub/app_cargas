<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class Company extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_name',
        'commercial_name',
        'tax_id',
        'country',
        'email',
        'phone',
        'address',
        'city',
        'postal_code',
        'company_roles',        // NUEVO: Roles de empresa
        'roles_config',         // NUEVO: Configuración por roles
        'certificate_path',
        'certificate_password',
        'certificate_alias',
        'certificate_expires_at',
        'ws_config',
        'ws_active',
        'ws_environment',
        'active',
        'created_date',
        'last_access',
    ];

    protected $casts = [
        'company_roles' => 'array',      // NUEVO: Cast a array
        'roles_config' => 'array',       // NUEVO: Cast a array
        'ws_config' => 'array',
        'ws_active' => 'boolean',
        'active' => 'boolean',
        'certificate_expires_at' => 'datetime',
        'created_date' => 'datetime',
        'last_access' => 'datetime',
    ];

    protected $hidden = [
        'certificate_password',
    ];

    // ========================================
    // NUEVOS MÉTODOS PARA COMPANY ROLES (Roberto's requirements)
    // ========================================

    /**
     * Obtener todos los roles de la empresa.
     */
    public function getRoles(): array
    {
        // Si company_roles es null, retornar default ["Cargas"]
        return $this->company_roles ?? ['Cargas'];
    }

    /**
     * Verificar si la empresa tiene un rol específico.
     */
    public function hasRole(string $role): bool
    {
        $roles = $this->getRoles(); // Esto ya maneja el default
        return in_array($role, $roles, true);
    }

    /**
     * Agregar un rol a la empresa.
     */
    public function addRole(string $role): self
    {
        $roles = $this->getRoles();
        if (!in_array($role, $roles, true)) {
            $roles[] = $role;
            $this->company_roles = $roles;
            $this->save();
        }
        return $this;
    }

    /**
     * Quitar un rol de la empresa.
     */
    public function removeRole(string $role): self
    {
        $roles = $this->getRoles();
        $roles = array_filter($roles, fn($r) => $r !== $role);
        $this->company_roles = array_values($roles);
        $this->save();
        return $this;
    }

    /**
     * Verificar si puede usar un webservice específico según sus roles.
     */
    public function canUseWebservice(string $webservice): bool
    {
        $availableWebservices = $this->getAvailableWebservices();
        return in_array($webservice, $availableWebservices, true);
    }

    /**
     * Verificar si puede realizar una operación específica según sus roles (Roberto's complete operations).
     */
    public function canPerformOperation(string $operation): bool
    {
        $availableOperations = $this->getAvailableOperations();
        return in_array($operation, $availableOperations, true);
    }

    /**
     * Obtener webservices disponibles según los roles de la empresa.
     */
    public function getAvailableWebservices(): array
    {
        $webservices = [];
        $roles = $this->getRoles();

        foreach ($roles as $role) {
            switch ($role) {
                case 'Cargas':
                    $webservices = array_merge($webservices, ['anticipada', 'micdta']);
                    // Mane será webservice en el futuro
                    break;
                case 'Desconsolidador':
                    $webservices[] = 'desconsolidados';
                    break;
                case 'Transbordos':
                    $webservices[] = 'transbordos';
                    break;
            }
        }

        return array_unique($webservices);
    }

    /**
     * Obtener todas las operaciones disponibles según los roles (Roberto's complete business operations).
     */
    public function getAvailableOperations(): array
    {
        $operations = [];
        $roles = $this->getRoles();

        foreach ($roles as $role) {
            switch ($role) {
                case 'Cargas':
                    $operations = array_merge($operations, [
                        // Webservices directos
                        'webservice_anticipada',
                        'webservice_micdta',
                        // Archivos para sistemas legacy/externos
                        'file_anticipada',      // Para empresas con sistema propio
                        'file_mane_malvina',    // Para sistema Malvina (legacy)
                        // Futuras operaciones
                        'webservice_mane',      // Futuro webservice de Mane
                    ]);
                    break;
                case 'Desconsolidador':
                    $operations = array_merge($operations, [
                        'webservice_desconsolidados',
                        'file_desconsolidados', // Por si en futuro necesitan archivos
                    ]);
                    break;
                case 'Transbordos':
                    $operations = array_merge($operations, [
                        'webservice_transbordos',
                        'file_transbordos',     // Por si en futuro necesitan archivos
                    ]);
                    break;
            }
        }

        return array_unique($operations);
    }

    /**
     * Obtener funcionalidades disponibles según los roles.
     */
    public function getAvailableFeatures(): array
    {
        $features = [];
        $roles = $this->getRoles();

        foreach ($roles as $role) {
            switch ($role) {
                case 'Cargas':
                    $features = array_merge($features, [
                        'shipments',           // Gestión de cargas
                        'containers',          // Contenedores
                        'manifests',           // Manifiestos
                        'reports',             // Reportes
                        'anticipada_operations', // Operaciones de anticipada (WS + archivos)
                        'micdta_operations',   // Operaciones MIC/DTA
                        'mane_operations',     // Operaciones Mane (Malvina + futuro WS)
                        'file_generation',     // Generación de archivos
                    ]);
                    break;
                case 'Desconsolidador':
                    $features = array_merge($features, [
                        'deconsolidations',    // Desconsolidaciones
                        'titulo_madre',        // Títulos madre
                        'titulo_hijos',        // Títulos hijos
                        'consolidation_reports', // Reportes específicos
                    ]);
                    break;
                case 'Transbordos':
                    $features = array_merge($features, [
                        'transshipments',      // Transbordos
                        'barges',              // Barcazas
                        'position_tracking',   // Seguimiento de posiciones
                        'barge_division',      // División de cargas en barcazas
                        'route_tracking',      // Seguimiento de rutas
                    ]);
                    break;
            }
        }

        return array_unique($features);
    }

    /**
     * Verificar si la empresa puede realizar transferencias (Roberto's requirement).
     */
    public function canTransferToCompany(): bool
    {
        // Todas las empresas pueden exportar/importar según Roberto
        return $this->active;
    }

    /**
     * Obtener roles disponibles en el sistema.
     */
    public static function getAvailableRoles(): array
    {
        return ['Cargas', 'Desconsolidador', 'Transbordos'];
    }

    /**
     * Scope para empresas con rol específico.
     */
    public function scopeWithRole($query, string $role)
    {
        return $query->whereJsonContains('company_roles', $role);
    }

    // ========================================
    // RELACIONES (mantener las existentes)
    // ========================================

    /**
     * Usuarios que son administradores de esta empresa.
     */
    public function users()
    {
        return $this->morphMany(User::class, 'userable');
    }

    /**
     * Operadores asociados a esta empresa.
     */
    public function operators()
    {
        return $this->hasMany(Operator::class);
    }

    // ========================================
    // SCOPES EXISTENTES (mantener)
    // ========================================

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function scopeWithValidCertificates($query)
    {
        return $query->whereNotNull('certificate_path')
            ->where('certificate_expires_at', '>', now());
    }

    public function scopeWithExpiredCertificates($query)
    {
        return $query->whereNotNull('certificate_expires_at')
            ->where('certificate_expires_at', '<', now());
    }

    public function scopeWithoutCertificates($query)
    {
        return $query->whereNull('certificate_path');
    }

    public function scopeByCountry($query, $country)
    {
        return $query->where('country', $country);
    }

    // ========================================
    // ACCESSORS EXISTENTES (mantener)
    // ========================================

    public function getFullNameAttribute()
    {
        return $this->commercial_name ?: $this->business_name;
    }

    public function getCountryNameAttribute()
    {
        return $this->country === 'AR' ? 'Argentina' : 'Paraguay';
    }

    public function getHasCertificateAttribute()
    {
        return !empty($this->certificate_path);
    }

    public function getIsCertificateExpiredAttribute()
    {
        return $this->certificate_expires_at && $this->certificate_expires_at->isPast();
    }

    public function getIsCertificateExpiringSoonAttribute()
    {
        return $this->certificate_expires_at &&
            $this->certificate_expires_at->isFuture() &&
            $this->certificate_expires_at->diffInDays(now()) <= 30;
    }

    public function getCertificateStatusAttribute()
    {
        if (!$this->has_certificate) {
            return 'none';
        }

        if ($this->is_certificate_expired) {
            return 'expired';
        }

        if ($this->is_certificate_expiring_soon) {
            return 'warning';
        }

        return 'valid';
    }

    public function getCertificateDaysToExpiryAttribute()
    {
        if (!$this->certificate_expires_at) {
            return null;
        }

        return now()->diffInDays($this->certificate_expires_at, false);
    }

    // NUEVO: Accessor para mostrar roles como string
    public function getRolesDisplayAttribute()
    {
        $roles = $this->getRoles();
        return empty($roles) ? 'Sin roles' : implode(', ', $roles);
    }

    // NUEVO: Mutator para company_roles
    public function setCompanyRolesAttribute($value)
    {
        // Si es null o array vacío, setear default
        if (empty($value)) {
            $this->attributes['company_roles'] = json_encode(['Cargas']);
        } else {
            $this->attributes['company_roles'] = json_encode($value);
        }
    }

    // NUEVO: Accessor para mostrar webservices disponibles
    public function getWebservicesDisplayAttribute()
    {
        $webservices = $this->getAvailableWebservices();
        return empty($webservices) ? 'Ninguno' : implode(', ', $webservices);
    }

    // ========================================
    // MUTATORS EXISTENTES (mantener)
    // ========================================

    public function setCertificatePasswordAttribute($value)
    {
        if ($value) {
            $this->attributes['certificate_password'] = encrypt($value);
        }
    }

    public function getCertificatePasswordAttribute($value)
    {
        return $value ? decrypt($value) : null;
    }

    // ========================================
    // MÉTODOS DE CERTIFICADOS (mantener existentes)
    // ========================================

    public function deleteCertificate()
    {
        if ($this->certificate_path && Storage::exists($this->certificate_path)) {
            Storage::delete($this->certificate_path);
        }

        $this->update([
            'certificate_path' => null,
            'certificate_password' => null,
            'certificate_alias' => null,
            'certificate_expires_at' => null,
        ]);
    }

    public function updateLastAccess()
    {
        $this->update(['last_access' => now()]);
    }

    // ========================================
    // VALIDACIONES ESPECÍFICAS POR ROL
    // ========================================

    /**
     * Validar configuración según roles.
     */
    public function validateRoleConfiguration(): array
    {
        $errors = [];
        $roles = $this->getRoles();

        foreach ($roles as $role) {
            switch ($role) {
                case 'Cargas':
                case 'Desconsolidador':
                case 'Transbordos':
                    // Verificar que tenga certificado para webservices
                    if (!$this->has_certificate) {
                        $errors[] = "Rol {$role} requiere certificado digital configurado";
                    }

                    if ($this->is_certificate_expired) {
                        $errors[] = "Certificado vencido para rol {$role}";
                    }

                    if (!$this->ws_active) {
                        $errors[] = "Webservices desactivados para rol {$role}";
                    }
                    break;
            }
        }

        return $errors;
    }

    /**
     * Verificar si la empresa está lista para operar.
     */
    public function isReadyToOperate(): bool
    {
        return $this->active &&
               !empty($this->getRoles()) &&
               empty($this->validateRoleConfiguration());
    }
}
