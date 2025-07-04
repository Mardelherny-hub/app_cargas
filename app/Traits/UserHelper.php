<?php

namespace App\Traits;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

trait UserHelper
{
    /**
     * Get the company of the authenticated user
     */
    public function getUserCompany(): ?\App\Models\Company
    {
        return auth()->user()?->company;
    }

    /**
     * Get the ID of the authenticated user's company
     */
    public function getUserCompanyId(): ?int
    {
        return $this->getUserCompany()?->id;
    }

    /**
     * Check if user can view data from a specific company
     */
    public function canViewCompany($companyId): bool
    {
        $user = auth()->user();

        if (!$user) {
            return false;
        }

        // Super admin and internal operators can view everything
        if ($user->isSuperAdmin() || $user->isInternalOperator()) {
            return true;
        }

        // Company users can only view their own company
        return $user->belongsToCompany($companyId);
    }

    /**
     * Apply company filters based on user permissions
     */
    public function scopeVisibleToUser(Builder $query, User $user = null): Builder
    {
        $user = $user ?? auth()->user();

        if (!$user) {
            return $query->whereRaw('1 = 0'); // Show nothing if no user
        }

        // Super admin and internal operators see everything
        if ($user->isSuperAdmin() || $user->isInternalOperator()) {
            return $query;
        }

        // Company users only see data from their company
        $companyId = $user->company?->id;
        if ($companyId) {
            return $query->where('company_id', $companyId);
        }

        return $query->whereRaw('1 = 0'); // Show nothing if no company
    }

    /**
     * Get all users from a specific company
     */
    public function getCompanyUsers($companyId): \Illuminate\Database\Eloquent\Collection
    {
        return User::fromCompany($companyId)
                   ->active()
                   ->with(['userable', 'roles'])
                   ->get();
    }

    /**
     * Check if user can perform transfers between companies
     */
    public function canTransfer(): bool
    {
        $user = auth()->user();

        if (!$user) {
            return false;
        }

        // Super admin and internal operators can transfer
        if ($user->isSuperAdmin() || $user->isInternalOperator()) {
            return true;
        }

        // Check if operator has specific permissions
        if ($user->userable_type === 'App\Models\Operator') {
            return $user->userable->can_transfer;
        }

        // Company admins can transfer
        if ($user->isCompanyAdmin()) {
            return true;
        }

        return false;
    }

    /**
     * Get companies that the user can view
     */
    public function getVisibleCompanies(): \Illuminate\Database\Eloquent\Collection
    {
        $user = auth()->user();

        if (!$user) {
            return collect();
        }

        // Super admin and internal operators see all companies
        if ($user->isSuperAdmin() || $user->isInternalOperator()) {
            return \App\Models\Company::active()->get();
        }

        // Company users only see their company
        $company = $user->company;
        if ($company) {
            return collect([$company]);
        }

        return collect();
    }

    /**
     * Check if user can import data
     */
    public function canImport(): bool
    {
        $user = auth()->user();

        if (!$user) {
            return false;
        }

        // Super admin and internal operators can import
        if ($user->isSuperAdmin() || $user->isInternalOperator()) {
            return true;
        }

        // Check specific operator permissions
        if ($user->userable_type === 'App\Models\Operator') {
            return $user->userable->can_import;
        }

        // Company admins can import
        if ($user->isCompanyAdmin()) {
            return true;
        }

        return false;
    }

    /**
     * Check if user can export data
     */
    public function canExport(): bool
    {
        $user = auth()->user();

        if (!$user) {
            return false;
        }

        // Super admin and internal operators can export
        if ($user->isSuperAdmin() || $user->isInternalOperator()) {
            return true;
        }

        // Check specific operator permissions
        if ($user->userable_type === 'App\Models\Operator') {
            return $user->userable->can_export;
        }

        // Company admins can export
        if ($user->isCompanyAdmin()) {
            return true;
        }

        return false;
    }

    /**
     * Update last access for the user
     */
    public function updateLastAccess(): void
    {
        $user = auth()->user();

        if ($user) {
            $user->updateLastAccess();

            // Also update in related entity if necessary
            if ($user->userable_type === 'App\Models\Company') {
                $user->userable->update(['last_access' => now()]);
            } elseif ($user->userable_type === 'App\Models\Operator') {
                $user->userable->update(['last_access' => now()]);
            }
        }
    }

    /**
     * Get user's full name
     */
    public function getUserFullName(): string
    {
        $user = auth()->user();

        if (!$user) {
            return 'Unknown User';
        }

        return $user->full_name;
    }

    /**
     * Get user type description
     */
    public function getUserType(): string
    {
        $user = auth()->user();

        if (!$user) {
            return 'Unknown';
        }

        return $user->user_type;
    }

    /**
     * Check if user can manage users
     */
    public function canManageUsers(): bool
    {
        $user = auth()->user();

        if (!$user) {
            return false;
        }

        return $user->can('users.create') || $user->can('users.edit') || $user->can('users.delete');
    }

    /**
     * Check if user can manage companies
     */
    public function canManageCompanies(): bool
    {
        $user = auth()->user();

        if (!$user) {
            return false;
        }

        return $user->can('companies.create') || $user->can('companies.edit') || $user->can('companies.delete');
    }

    /**
     * Check if user can access webservices
     */
    public function canAccessWebservices(): bool
    {
        $user = auth()->user();

        if (!$user) {
            return false;
        }

        // Must have webservice permissions
        if (!$user->can('webservices.send')) {
            return false;
        }

        // Must have a company with valid certificate
        $company = $user->company;
        if (!$company) {
            return false;
        }

        return $company->canUseWebservices();
    }

    /**
     * Get user's country based on company
     */
    public function getUserCountry(): ?string
    {
        $company = $this->getUserCompany();
        return $company?->country;
    }

    /**
     * Check if user is from Argentina
     */
    public function isFromArgentina(): bool
    {
        return $this->getUserCountry() === 'AR';
    }

    /**
     * Check if user is from Paraguay
     */
    public function isFromParaguay(): bool
    {
        return $this->getUserCountry() === 'PY';
    }

    /**
     * Apply ownership filter to query based on user permissions
     */
    public function applyOwnershipFilter(Builder $query, string $companyField = 'company_id'): Builder
    {
        $user = auth()->user();

        if (!$user) {
            return $query->whereRaw('1 = 0');
        }

        // Super admin and internal operators see everything
        if ($user->isSuperAdmin() || $user->isInternalOperator()) {
            return $query;
        }

        // Company users only see their own data
        $companyId = $user->company?->id;
        if ($companyId) {
            return $query->where($companyField, $companyId);
        }

        return $query->whereRaw('1 = 0');
    }

    /**
     * Get menu items based on user permissions
     */
    public function getMenuItems(): array
    {
        $user = auth()->user();

        if (!$user) {
            return [];
        }

        $menu = [];

        // Dashboard (everyone)
        $menu[] = ['name' => 'Dashboard', 'route' => 'dashboard', 'icon' => 'home'];

        // Companies (if can view)
        if ($user->can('companies.view')) {
            $menu[] = ['name' => 'Companies', 'route' => 'companies.index', 'icon' => 'building'];
        }

        // Trips (if can view)
        if ($user->can('trips.view')) {
            $menu[] = ['name' => 'Trips', 'route' => 'trips.index', 'icon' => 'ship'];
        }

        // Shipments (if can view)
        if ($user->can('shipments.view')) {
            $menu[] = ['name' => 'Shipments', 'route' => 'shipments.index', 'icon' => 'box'];
        }

        // Reports (if can view)
        if ($user->can('reports.manifests')) {
            $menu[] = ['name' => 'Reports', 'route' => 'reports.index', 'icon' => 'document'];
        }

        // Users (if can manage)
        if ($this->canManageUsers()) {
            $menu[] = ['name' => 'Users', 'route' => 'users.index', 'icon' => 'users'];
        }

        // Administration (if super admin)
        if ($user->isSuperAdmin()) {
            $menu[] = ['name' => 'Administration', 'route' => 'admin.index', 'icon' => 'cog'];
        }

        return $menu;
    }
}
