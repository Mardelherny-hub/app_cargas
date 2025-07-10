<?php

namespace App\Traits;

use Illuminate\Support\Facades\Auth;
use App\Models\Company;
use App\Models\User;

trait UserHelper
{
    /**
     * Obtener el usuario actual autenticado.
     */
    protected function getCurrentUser(): ?User
    {
        return Auth::user();
    }

    /**
     * Obtener la empresa del usuario actual.
     */
    protected function getUserCompany(): ?Company
    {
        $user = $this->getCurrentUser();

        if (!$user) {
            return null;
        }

        // Si el usuario está directamente asociado a una empresa
        if ($user->userable_type === 'App\Models\Company') {
            return $user->userable;
        }

        // Si el usuario es un operador asociado a una empresa
        if ($user->userable_type === 'App\Models\Operator' && $user->userable) {
            return $user->userable->company;
        }

        return null;
    }

    /**
     * Verificar si el usuario puede acceder a una empresa específica.
     */
    protected function canAccessCompany($companyId): bool
    {
        $user = $this->getCurrentUser();

        if (!$user) {
            return false;
        }

        // Solo super admin puede acceder a cualquier empresa
        if ($user->hasRole('super-admin')) {
            return true;
        }

        // Company admin y users solo pueden acceder a su propia empresa
        $userCompany = $this->getUserCompany();
        return $userCompany && $userCompany->id == $companyId;
    }

    /**
     * Obtener el ID de la empresa del usuario (para consultas).
     */
    protected function getUserCompanyId(): ?int
    {
        $company = $this->getUserCompany();
        return $company?->id;
    }

    /**
     * Verificar si el usuario tiene acceso administrativo.
     */
    protected function hasAdminAccess(): bool
    {
        $user = $this->getCurrentUser();

        if (!$user) {
            return false;
        }

        return $user->hasRole('super-admin') || $user->hasRole('company-admin');
    }

    /**
     * Verificar si el usuario es super administrador.
     */
    protected function isSuperAdmin(): bool
    {
        $user = $this->getCurrentUser();
        return $user && $user->hasRole('super-admin');
    }

    /**
     * Verificar si el usuario es administrador de empresa.
     */
    protected function isCompanyAdmin(): bool
    {
        $user = $this->getCurrentUser();
        return $user && $user->hasRole('company-admin');
    }

    /**
     * Verificar si el usuario es usuario regular.
     */
    protected function isUser(): bool
    {
        $user = $this->getCurrentUser();
        return $user && $user->hasRole('user');
    }

    /**
     * Verificar si el usuario es operador (independiente del rol).
     */
    protected function isOperator(): bool
    {
        $user = $this->getCurrentUser();
        return $user && $user->userable_type === 'App\Models\Operator';
    }

    /**
     * Obtener el tipo de usuario para mostrar en la interfaz.
     */
    protected function getUserType(): string
    {
        $user = $this->getCurrentUser();

        if (!$user) {
            return 'Invitado';
        }

        if ($user->hasRole('super-admin')) {
            return 'Super Administrador';
        }

        if ($user->hasRole('company-admin')) {
            return 'Administrador de Empresa';
        }

        if ($user->hasRole('user')) {
            // Si es un operador, especificarlo
            if ($user->userable_type === 'App\Models\Operator') {
                return 'Operador';
            }
            return 'Usuario';
        }

        return 'Usuario';
    }

    /**
     * Obtener información resumida del usuario para mostrar en la interfaz.
     */
    protected function getUserInfo(): array
    {
        $user = $this->getCurrentUser();
        $company = $this->getUserCompany();

        if (!$user) {
            return [
                'name' => 'Invitado',
                'email' => null,
                'type' => 'Invitado',
                'company' => null,
                'roles' => [],
                'permissions' => []
            ];
        }

        $info = [
            'name' => $user->name,
            'email' => $user->email,
            'type' => $this->getUserType(),
            'company' => $company ? $company->business_name : null,
            'roles' => $user->roles->pluck('name')->toArray(),
            'permissions' => $user->getAllPermissions()->pluck('name')->toArray()
        ];

        // Agregar información específica de empresa si aplica
        if ($company) {
            $info['company_id'] = $company->id;
            $info['company_roles'] = $company->company_roles ?? [];
            $info['company_active'] = $company->active;
        }

        // Agregar información específica de operador si aplica
        if ($user->userable_type === 'App\Models\Operator' && $user->userable) {
            $operator = $user->userable;
            $info['operator_permissions'] = [
                'can_import' => $operator->can_import ?? false,
                'can_export' => $operator->can_export ?? false,
                'can_transfer' => $operator->can_transfer ?? false,
            ];
            $info['operator_active'] = $operator->active;
        }

        return $info;
    }

    /**
     * Verificar si el usuario tiene un rol específico de empresa.
     */
    protected function hasCompanyRole(string $businessRole): bool
    {
        $company = $this->getUserCompany();

        if (!$company) {
            return false;
        }

        $companyRoles = $company->company_roles ?? [];
        return in_array($businessRole, $companyRoles);
    }

    /**
     * Verificar si el usuario puede realizar una acción específica.
     */
    protected function canPerform(string $action): bool
    {
        $user = $this->getCurrentUser();

        if (!$user) {
            return false;
        }

        // Super admin puede hacer todo
        if ($user->hasRole('super-admin')) {
            return true;
        }

        // Company admin puede hacer todo en su empresa
        if ($user->hasRole('company-admin')) {
            return true;
        }

        // Users tienen permisos limitados según sus roles de empresa y operador
        if ($user->hasRole('user')) {
            switch ($action) {
                case 'import':
                    return $this->isOperator() && $user->userable->can_import;
                case 'export':
                    return $this->isOperator() && $user->userable->can_export;
                case 'transfer':
                    return $this->isOperator() && $user->userable->can_transfer;
                case 'view_cargas':
                    return $this->hasCompanyRole('Cargas');
                case 'view_deconsolidation':
                    return $this->hasCompanyRole('Desconsolidador');
                case 'view_transfers':
                    return $this->hasCompanyRole('Transbordos');
                case 'view_reports':
                    return true; // Todos los users pueden ver reportes
                default:
                    return false;
            }
        }

        return false;
    }

    /**
     * Obtener los roles de empresa del usuario.
     */
    protected function getUserCompanyRoles(): array
    {
        $company = $this->getUserCompany();
        return $company ? ($company->company_roles ?? []) : [];
    }

    /**
     * Obtener los permisos de operador del usuario.
     */
    protected function getUserOperatorPermissions(): array
    {
        $user = $this->getCurrentUser();

        if (!$user || $user->userable_type !== 'App\Models\Operator' || !$user->userable) {
            return [
                'can_import' => false,
                'can_export' => false,
                'can_transfer' => false,
            ];
        }

        return [
            'can_import' => $user->userable->can_import ?? false,
            'can_export' => $user->userable->can_export ?? false,
            'can_transfer' => $user->userable->can_transfer ?? false,
        ];
    }


    public function canImport()
    {
        $operator = $this->getUserOperator();
        return $operator && $operator->can_import;
    }

    public function canExport()
    {
        $operator = $this->getUserOperator();
        return $operator && $operator->can_export;
    }

    public function canTransfer()
    {
        $operator = $this->getUserOperator();
        return $operator && $operator->can_transfer;
    }

    public function getUserOperator()
    {
        $user = $this->getCurrentUser();
        if ($user && $user->userable_type === 'App\Models\Operator') {
            return $user->userable;
        }
        return null;
    }
}
