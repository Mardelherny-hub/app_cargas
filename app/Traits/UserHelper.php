<?php

namespace App\Traits;

use App\Models\User;
use App\Models\Company;
use App\Models\Operator;
use Illuminate\Support\Facades\Auth;

trait UserHelper
{
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

        if (!$user) {
            return false;
        }

        return $user->userable_type === 'App\\Models\\Company' && $user->userable_id;
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

        // Si el usuario es directamente una empresa
        if ($user->userable_type === 'App\\Models\\Company' && $user->userable_id) {
            return $user->userable;
        }

        // Si el usuario es un operador con empresa
        if ($user->userable_type === 'App\\Models\\Operator' && $user->userable_id) {
            $operator = $user->userable;
            return $operator?->company;
        }

        return null;
    }

    /**
     * Verificar si el usuario actual es un operador.
     */
    protected function isUserOperator(): bool
    {
        $user = $this->getCurrentUser();

        if (!$user) {
            return false;
        }

        return $user->userable_type === 'App\\Models\\Operator' && $user->userable_id;
    }

    /**
     * Obtener el operador del usuario actual.
     */
    protected function getUserOperator(): ?Operator
    {
        $user = $this->getCurrentUser();

        if (!$user) {
            return null;
        }

        if ($user->userable_type === 'App\\Models\\Operator' && $user->userable_id) {
            return $user->userable;
        }

        return null;
    }

    /**
     * Verificar si el usuario puede importar.
     */
    protected function canImport(): bool
    {
        $user = $this->getCurrentUser();

        if (!$user) {
            return false;
        }

        // Super admin puede todo
        if ($user->hasRole('super-admin')) {
            return true;
        }

        // Administradores de empresa pueden importar
        if ($user->hasRole('company-admin')) {
            return true;
        }

        // Operadores internos pueden importar
        if ($user->hasRole('internal-operator')) {
            return true;
        }

        // Operadores externos solo si tienen el permiso
        if ($user->hasRole('external-operator')) {
            $operator = $this->getUserOperator();
            return $operator?->can_import ?? false;
        }

        return false;
    }

    /**
     * Verificar si el usuario puede exportar.
     */
    protected function canExport(): bool
    {
        $user = $this->getCurrentUser();

        if (!$user) {
            return false;
        }

        // Super admin puede todo
        if ($user->hasRole('super-admin')) {
            return true;
        }

        // Administradores de empresa pueden exportar
        if ($user->hasRole('company-admin')) {
            return true;
        }

        // Operadores internos pueden exportar
        if ($user->hasRole('internal-operator')) {
            return true;
        }

        // Operadores externos solo si tienen el permiso
        if ($user->hasRole('external-operator')) {
            $operator = $this->getUserOperator();
            return $operator?->can_export ?? false;
        }

        return false;
    }

    /**
     * Verificar si el usuario puede transferir.
     */
    protected function canTransfer(): bool
    {
        $user = $this->getCurrentUser();

        if (!$user) {
            return false;
        }

        // Super admin puede todo
        if ($user->hasRole('super-admin')) {
            return true;
        }

        // Administradores de empresa pueden transferir
        if ($user->hasRole('company-admin')) {
            return true;
        }

        // Operadores internos pueden transferir
        if ($user->hasRole('internal-operator')) {
            return true;
        }

        // Operadores externos solo si tienen el permiso
        if ($user->hasRole('external-operator')) {
            $operator = $this->getUserOperator();
            return $operator?->can_transfer ?? false;
        }

        return false;
    }

    /**
     * Aplicar filtro de propiedad a una consulta (para empresas).
     */
    protected function applyOwnershipFilter($query): void
    {
        $user = $this->getCurrentUser();

        if (!$user) {
            return;
        }

        // Super admin y operadores internos ven todo
        if ($user->hasRole('super-admin') || $user->hasRole('internal-operator')) {
            return;
        }

        // Administradores de empresa y operadores externos solo ven de su empresa
        $company = $this->getUserCompany();
        if ($company) {
            $query->where('company_id', $company->id);
        } else {
            // Si no tiene empresa, no ve nada
            $query->whereRaw('1 = 0');
        }
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

        // Super admin y operadores internos pueden acceder a cualquier empresa
        if ($user->hasRole('super-admin') || $user->hasRole('internal-operator')) {
            return true;
        }

        // Otros usuarios solo pueden acceder a su propia empresa
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

        return $user->hasRole('super-admin') ||
        $user->hasRole('company-admin') ||
        $user->hasRole('internal-operator');
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
     * Verificar si el usuario es operador interno.
     */
    protected function isInternalOperator(): bool
    {
        $user = $this->getCurrentUser();
        return $user && $user->hasRole('internal-operator');
    }

    /**
     * Verificar si el usuario es operador externo.
     */
    protected function isExternalOperator(): bool
    {
        $user = $this->getCurrentUser();
        return $user && $user->hasRole('external-operator');
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

        if ($user->hasRole('internal-operator')) {
            return 'Operador Interno';
        }

        if ($user->hasRole('external-operator')) {
            return 'Operador Externo';
        }

        return 'Usuario';
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
                'company' => null,
                'permissions' => [],
            ];
        }

        $company = $this->getUserCompany();
        $operator = $this->getUserOperator();

        return [
            'name' => $user->name,
            'email' => $user->email,
            'type' => $this->getUserType(),
            'company' => $company?->business_name,
            'company_id' => $company?->id,
            'operator' => $operator?->full_name,
            'permissions' => [
                'can_import' => $this->canImport(),
                'can_export' => $this->canExport(),
                'can_transfer' => $this->canTransfer(),
                'has_admin_access' => $this->hasAdminAccess(),
            ],
            'roles' => $user->roles->pluck('name')->toArray(),
        ];
    }
}
