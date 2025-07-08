<?php

namespace App\Traits;

use App\Models\User;
use App\Models\Company;
use App\Models\Operator;
use Illuminate\Support\Facades\Auth;

trait UserHelper
{
    // ========================================
    // MÉTODOS BÁSICOS DE USUARIO (mantener compatibilidad)
    // ========================================

    /**
     * Obtener el usuario actual.
     */
    protected function getCurrentUser(): ?User
    {
        return Auth::user();
    }

    /**
     * Verificar si el usuario actual tiene una empresa asociada.
     */
    protected function hasUserCompany(): bool
    {
        $user = $this->getCurrentUser();
        return $user ? $user->getCompany() !== null : false;
    }

    /**
     * Obtener la empresa del usuario actual.
     */
    protected function getUserCompany(): ?Company
    {
        $user = $this->getCurrentUser();
        return $user ? $user->getCompany() : null;
    }

    /**
     * Verificar si el usuario actual es un operador.
     */
    protected function isUserOperator(): bool
    {
        $user = $this->getCurrentUser();
        return $user && $user->userable_type === 'App\\Models\\Operator';
    }

    /**
     * Obtener el operador del usuario actual.
     */
    protected function getUserOperator(): ?Operator
    {
        $user = $this->getCurrentUser();

        if ($user && $user->userable_type === 'App\\Models\\Operator') {
            return $user->userable;
        }

        return null;
    }

    /**
     * Obtener el ID de la empresa del usuario (para consultas).
     */
    protected function getUserCompanyId(): ?int
    {
        $company = $this->getUserCompany();
        return $company?->id;
    }

    // ========================================
    // NUEVOS MÉTODOS PARA COMPANY ROLES (Roberto's requirements)
    // ========================================

    /**
     * Obtener los roles de la empresa del usuario.
     */
    protected function getUserCompanyRoles(): array
    {
        $user = $this->getCurrentUser();
        return $user ? $user->getCompanyRoles() : [];
    }

    /**
     * Verificar si la empresa del usuario tiene un rol específico.
     */
    protected function userHasCompanyRole(string $role): bool
    {
        $user = $this->getCurrentUser();
        return $user ? $user->hasCompanyRole($role) : false;
    }

    /**
     * Verificar si el usuario puede usar un webservice específico.
     */
    protected function canUseWebservice(string $webservice): bool
    {
        $user = $this->getCurrentUser();
        return $user ? $user->canUseWebservice($webservice) : false;
    }

    /**
     * Obtener webservices disponibles para el usuario.
     */
    protected function getAvailableWebservices(): array
    {
        $user = $this->getCurrentUser();
        return $user ? $user->getAvailableWebservices() : [];
    }

    /**
     * Obtener funcionalidades disponibles para el usuario.
     */
    protected function getAvailableFeatures(): array
    {
        $user = $this->getCurrentUser();
        return $user ? $user->getAvailableFeatures() : [];
    }

    /**
     * Verificar si puede usar una funcionalidad específica.
     */
    protected function canUseFeature(string $feature): bool
    {
        $availableFeatures = $this->getAvailableFeatures();
        return in_array($feature, $availableFeatures, true);
    }

    // ========================================
    // MÉTODOS DE PERMISOS ACTUALIZADOS (Roberto's logic)
    // ========================================

    /**
     * Verificar si el usuario puede importar (basado en empresa y roles).
     */
    protected function canImport(): bool
    {
        $user = $this->getCurrentUser();
        return $user ? $user->canImport() : false;
    }

    /**
     * Verificar si el usuario puede exportar (basado en empresa y roles).
     */
    protected function canExport(): bool
    {
        $user = $this->getCurrentUser();
        return $user ? $user->canExport() : false;
    }

    /**
     * Verificar si el usuario puede transferir entre empresas (Roberto's requirement).
     */
    protected function canTransferBetweenCompanies(): bool
    {
        $user = $this->getCurrentUser();
        return $user ? $user->canTransferBetweenCompanies() : false;
    }

    /**
     * Verificar si puede gestionar usuarios (Roberto's hierarchy).
     */
    protected function canManageUsers(): bool
    {
        $user = $this->getCurrentUser();
        return $user ? $user->canManageUsers() : false;
    }

    /**
     * Verificar si puede gestionar empresas.
     */
    protected function canManageCompanies(): bool
    {
        $user = $this->getCurrentUser();
        return $user ? $user->canManageCompanies() : false;
    }

    /**
     * Verificar si puede crear empresas.
     */
    protected function canCreateCompanies(): bool
    {
        $user = $this->getCurrentUser();
        return $user ? $user->canCreateCompanies() : false;
    }

    // ========================================
    // MÉTODOS DE ROLES DE USUARIO (simplificados según Roberto)
    // ========================================

    /**
     * Verificar si el usuario es super administrador.
     */
    protected function isSuperAdmin(): bool
    {
        $user = $this->getCurrentUser();
        return $user && $user->hasRole('super-admin');
    }

    /**
     * Verificar si el usuario es administrador de empresa (el "jefe" de Roberto).
     */
    protected function isCompanyAdmin(): bool
    {
        $user = $this->getCurrentUser();
        return $user && $user->hasRole('company-admin');
    }

    /**
     * Verificar si el usuario es usuario común.
     */
    protected function isRegularUser(): bool
    {
        $user = $this->getCurrentUser();
        return $user && $user->hasRole('user');
    }

    /**
     * Verificar si el usuario tiene acceso administrativo (super-admin o company-admin).
     */
    protected function hasAdminAccess(): bool
    {
        return $this->isSuperAdmin() || $this->isCompanyAdmin();
    }

    // ========================================
    // MÉTODOS DE ACCESO Y FILTRADO
    // ========================================

    /**
     * Verificar si el usuario puede acceder a una empresa específica.
     */
    protected function canAccessCompany($companyId): bool
    {
        $user = $this->getCurrentUser();
        return $user ? $user->canAccessCompany($companyId) : false;
    }

    /**
     * Aplicar filtro de empresa a una consulta (Roberto's isolation).
     */
    protected function applyCompanyFilter($query): void
    {
        $user = $this->getCurrentUser();
        if ($user) {
            $user->applyCompanyFilter($query);
        } else {
            // Si no hay usuario, no ver nada
            $query->whereRaw('1 = 0');
        }
    }

    /**
     * Aplicar filtro de propiedad a una consulta (alias para compatibilidad).
     */
    protected function applyOwnershipFilter($query): void
    {
        $this->applyCompanyFilter($query);
    }

    // ========================================
    // MÉTODOS DE INFORMACIÓN Y DISPLAY
    // ========================================

    /**
     * Obtener el tipo de usuario para mostrar en la interfaz.
     */
    protected function getUserType(): string
    {
        $user = $this->getCurrentUser();
        return $user ? $user->getUserTypeDisplay() : 'Invitado';
    }

    /**
     * Obtener información de la empresa para mostrar.
     */
    protected function getUserCompanyDisplay(): string
    {
        $user = $this->getCurrentUser();
        return $user ? $user->getCompanyDisplay() : 'Sin sesión';
    }

    /**
     * Obtener roles de empresa para mostrar.
     */
    protected function getUserCompanyRolesDisplay(): string
    {
        $user = $this->getCurrentUser();
        return $user ? $user->getCompanyRolesDisplay() : 'Sin roles';
    }

    /**
     * Obtener webservices disponibles para mostrar.
     */
    protected function getUserWebservicesDisplay(): string
    {
        $user = $this->getCurrentUser();
        return $user ? $user->getWebservicesDisplay() : 'Ninguno';
    }

    /**
     * Obtener información resumida del usuario para mostrar en la interfaz.
     */
    protected function getUserInfo(): array
    {
        $user = $this->getCurrentUser();

        if (!$user) {
            return [
                'name' => 'Invitado',
                'type' => 'Invitado',
                'company' => 'Sin sesión',
                'company_roles' => [],
                'webservices' => [],
                'can_import' => false,
                'can_export' => false,
                'can_transfer' => false,
                'can_manage_users' => false,
                'can_manage_companies' => false,
            ];
        }

        return [
            'name' => $user->name,
            'email' => $user->email,
            'type' => $user->getUserTypeDisplay(),
            'company' => $user->getCompanyDisplay(),
            'company_roles' => $user->getCompanyRoles(),
            'company_roles_display' => $user->getCompanyRolesDisplay(),
            'webservices' => $user->getAvailableWebservices(),
            'webservices_display' => $user->getWebservicesDisplay(),
            'features' => $user->getAvailableFeatures(),
            'can_import' => $user->canImport(),
            'can_export' => $user->canExport(),
            'can_transfer' => $user->canTransferBetweenCompanies(),
            'can_manage_users' => $user->canManageUsers(),
            'can_manage_companies' => $user->canManageCompanies(),
            'can_create_companies' => $user->canCreateCompanies(),
            'last_access' => $user->last_access,
            'is_properly_configured' => $user->isProperlyConfigured(),
        ];
    }

    // ========================================
    // MÉTODOS DE VALIDACIÓN
    // ========================================

    /**
     * Verificar si el usuario está configurado correctamente.
     */
    protected function isUserProperlyConfigured(): bool
    {
        $user = $this->getCurrentUser();
        return $user ? $user->isProperlyConfigured() : false;
    }

    /**
     * Obtener errores de configuración del usuario.
     */
    protected function getUserConfigurationErrors(): array
    {
        $user = $this->getCurrentUser();
        return $user ? $user->getConfigurationErrors() : ['Usuario no autenticado'];
    }

    /**
     * Verificar si puede cambiar su password (Roberto's requirement).
     */
    protected function canChangePassword(): bool
    {
        $user = $this->getCurrentUser();
        return $user ? $user->canChangePassword() : false;
    }

    // ========================================
    // MÉTODOS ESPECÍFICOS POR ROLES DE EMPRESA (Roberto's business logic)
    // ========================================

    /**
     * Verificar si puede trabajar con cargas (empresa con rol "Cargas").
     */
    protected function canWorkWithCargas(): bool
    {
        return $this->userHasCompanyRole('Cargas') || $this->isSuperAdmin();
    }

    /**
     * Verificar si puede trabajar con desconsolidaciones (empresa con rol "Desconsolidador").
     */
    protected function canWorkWithDesconsolidaciones(): bool
    {
        return $this->userHasCompanyRole('Desconsolidador') || $this->isSuperAdmin();
    }

    /**
     * Verificar si puede trabajar con transbordos (empresa con rol "Transbordos").
     */
    protected function canWorkWithTransbordos(): bool
    {
        return $this->userHasCompanyRole('Transbordos') || $this->isSuperAdmin();
    }

    /**
     * Verificar si puede usar webservice anticipada.
     */
    protected function canUseAnticipadaWebservice(): bool
    {
        return $this->canUseWebservice('anticipada');
    }

    /**
     * Verificar si puede usar webservice micdta.
     */
    protected function canUseMicdtaWebservice(): bool
    {
        return $this->canUseWebservice('micdta');
    }

    /**
     * Verificar si puede usar webservice desconsolidados.
     */
    protected function canUseDesconsolidadosWebservice(): bool
    {
        return $this->canUseWebservice('desconsolidados');
    }

    /**
     * Verificar si puede usar webservice transbordos.
     */
    protected function canUseTransbordosWebservice(): bool
    {
        return $this->canUseWebservice('transbordos');
    }

    // ========================================
    // MÉTODOS DE NAVEGACIÓN Y REDIRECCIÓN
    // ========================================

    /**
     * Obtener la URL del dashboard apropiado para el usuario.
     */
    protected function getUserDashboardUrl(): string
    {
        $user = $this->getCurrentUser();

        if (!$user) {
            return route('welcome');
        }

        if ($user->hasRole('super-admin')) {
            return route('admin.dashboard');
        }

        if ($user->hasRole('company-admin')) {
            return route('company.dashboard');
        }

        if ($user->hasRole('user')) {
            // Los usuarios comunes van al mismo dashboard que company-admin
            return route('company.dashboard');
        }

        // Fallback
        return route('dashboard');
    }

    /**
     * Verificar si el usuario está en su dashboard correcto.
     */
    protected function isOnCorrectDashboard(): bool
    {
        $currentRoute = request()->route()?->getName();
        $correctDashboard = $this->getUserDashboardUrl();

        return $currentRoute && str_contains($correctDashboard, $currentRoute);
    }

    // ========================================
    // MÉTODOS DE AUDITORÍA
    // ========================================

    /**
     * Actualizar último acceso del usuario.
     */
    protected function updateUserLastAccess(): void
    {
        $user = $this->getCurrentUser();
        if ($user) {
            $user->updateLastAccess();
        }
    }

    /**
     * Verificar si el usuario ha estado activo recientemente.
     */
    protected function isUserRecentlyActive(int $days = 30): bool
    {
        $user = $this->getCurrentUser();

        if (!$user || !$user->last_access) {
            return false;
        }

        return $user->last_access->gte(now()->subDays($days));
    }

    // ========================================
    // MÉTODOS DE COMPATIBILIDAD (deprecated pero mantenidos)
    // ========================================

    /**
     * @deprecated Use canTransferBetweenCompanies() instead
     */
    protected function canTransfer(): bool
    {
        return $this->canTransferBetweenCompanies();
    }

    /**
     * @deprecated Use isCompanyAdmin() instead
     */
    protected function isInternalOperator(): bool
    {
        // Para compatibilidad, mapear a company-admin
        return $this->isCompanyAdmin();
    }

    /**
     * @deprecated Use isRegularUser() instead
     */
    protected function isExternalOperator(): bool
    {
        // Para compatibilidad, mapear a user regular
        return $this->isRegularUser();
    }

    // ========================================
    // HELPERS PARA VISTAS Y COMPONENTES
    // ========================================

    /**
     * Obtener menú de navegación apropiado para el usuario.
     */
    protected function getUserMenuItems(): array
    {
        $user = $this->getCurrentUser();

        if (!$user) {
            return [];
        }

        $menu = [];

        // Dashboard siempre disponible
        $menu[] = [
            'label' => 'Dashboard',
            'route' => $this->getUserDashboardUrl(),
            'icon' => 'dashboard'
        ];

        // Super admin ve todo
        if ($user->hasRole('super-admin')) {
            $menu[] = ['label' => 'Empresas', 'route' => route('admin.companies.index'), 'icon' => 'building'];
            $menu[] = ['label' => 'Usuarios', 'route' => route('admin.users.index'), 'icon' => 'users'];
            $menu[] = ['label' => 'Sistema', 'route' => route('admin.system.index'), 'icon' => 'settings'];
        }

        // Company admin y usuarios ven según roles de empresa
        if ($user->hasRole('company-admin') || $user->hasRole('user')) {
            if ($this->canWorkWithCargas()) {
                $menu[] = ['label' => 'Cargas', 'route' => route('company.shipments.index'), 'icon' => 'truck'];
                $menu[] = ['label' => 'Viajes', 'route' => route('company.trips.index'), 'icon' => 'ship'];
            }

            if ($this->canWorkWithDesconsolidaciones()) {
                $menu[] = ['label' => 'Desconsolidados', 'route' => route('company.deconsolidations.index'), 'icon' => 'split'];
            }

            if ($this->canWorkWithTransbordos()) {
                $menu[] = ['label' => 'Transbordos', 'route' => route('company.transshipments.index'), 'icon' => 'transfer'];
            }

            if ($user->hasRole('company-admin')) {
                $menu[] = ['label' => 'Usuarios', 'route' => route('company.users.index'), 'icon' => 'users'];
            }

            $menu[] = ['label' => 'Reportes', 'route' => route('company.reports.index'), 'icon' => 'chart'];
        }

        return $menu;
    }
}
