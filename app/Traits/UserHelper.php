<?php

namespace App\Traits;

use Illuminate\Support\Facades\Auth;
use App\Models\Company;
use App\Models\User;
use Carbon\Carbon;

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
    public function getUserSummaryInfo(): array
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
            'company' => $company ? $company->legal_name : null,
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
     * MÉTODO CORREGIDO: Se agregaron todas las acciones faltantes
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
            switch ($action) {
                // Acceso básico
                case 'dashboard_access':
                case 'view_reports':
                case 'access_trips':
                case 'access_shipments':
                    return true;

                // Gestión administrativa
                case 'manage_operators':
                case 'manage_certificates':
                case 'manage_settings':
                case 'manage_webservices':
                    return true;

                // Acceso a módulos específicos
                case 'access_import':
                case 'access_export':
                    return true;

                // Acciones de importación/exportación/transferencia
                case 'import':
                case 'export':
                case 'transfer':
                    return true;

                // Visualización por roles de empresa
                case 'view_cargas':
                    return $this->hasCompanyRole('Cargas');
                case 'view_deconsolidation':
                    return $this->hasCompanyRole('Desconsolidador');
                case 'view_transfers':
                    return $this->hasCompanyRole('Transbordos');

                default:
                    return false;
            }
        }

        // Users tienen permisos limitados según sus roles de empresa y operador
        if ($user->hasRole('user')) {
            switch ($action) {
                // Acceso básico para users
                case 'dashboard_access':
                case 'view_reports':
                    return true;

                // Acciones de operador (requieren permisos específicos)
                case 'import':
                    return $this->isOperator() && $user->userable->can_import;
                case 'export':
                    return $this->isOperator() && $user->userable->can_export;
                case 'transfer':
                    return $this->isOperator() && $user->userable->can_transfer;

                // Acceso a módulos según permisos de operador
                case 'access_import':
                    return $this->isOperator() && $user->userable->can_import;
                case 'access_export':
                    return $this->isOperator() && $user->userable->can_export;

                // Acceso a secciones según roles de empresa
                case 'access_trips':
                case 'access_shipments':
                    return $this->hasCompanyRole('Cargas');

                case 'view_cargas':
                    return $this->hasCompanyRole('Cargas');
                case 'view_deconsolidation':
                    return $this->hasCompanyRole('Desconsolidador');
                case 'view_transfers':
                    return $this->hasCompanyRole('Transbordos');

                // Gestión administrativa (NO permitida para users)
                case 'manage_operators':
                case 'manage_certificates':
                case 'manage_settings':
                case 'manage_webservices':
                    return false;

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

    /**
     * Verificar si el usuario puede importar.
     */
    public function canImport()
    {
        $operator = $this->getUserOperator();
        return $operator && $operator->can_import;
    }

    /**
     * Verificar si el usuario puede exportar.
     */
    public function canExport()
    {
        $operator = $this->getUserOperator();
        return $operator && $operator->can_export;
    }

    /**
     * Verificar si el usuario puede transferir.
     */
    public function canTransfer()
    {
        $operator = $this->getUserOperator();
        return $operator && $operator->can_transfer;
    }

    /**
     * Obtener el operador del usuario.
     */
    public function getUserOperator()
    {
        $user = $this->getCurrentUser();
        if ($user && $user->userable_type === 'App\Models\Operator') {
            return $user->userable;
        }
        return null;
    }

    /**
     * Verificar si el usuario tiene permisos de operador válidos.
     * MÉTODO FALTANTE: Movido desde DashboardController
     */
    protected function hasValidOperatorPermissions(): bool
    {
        if (!$this->isUser()) {
            return true; // Los company-admin no necesitan esta verificación
        }

        $operator = $this->getUserOperator();
        if (!$operator) {
            return false; // Los users deben tener un operador asociado
        }

        // Verificar que el operador esté activo
        if (!$operator->active) {
            return false;
        }

        // Verificar que tenga al menos un permiso
        return $operator->can_import || $operator->can_export || $operator->can_transfer;
    }

    /**
     * Obtener estado del certificado de la empresa.
     * MÉTODO FALTANTE: Usado en DashboardController
     */
    protected function getCertificateStatus(Company $company): array
    {
        $status = [
            'has_certificate' => !empty($company->certificate_path),
            'is_valid' => false,
            'expires_at' => $company->certificate_expires_at,
            'days_until_expiry' => null,
            'is_expired' => false,
            'needs_renewal' => false,
        ];

        if ($company->certificate_expires_at) {
            $now = Carbon::now();
            $expiryDate = Carbon::parse($company->certificate_expires_at);

            $status['days_until_expiry'] = $now->diffInDays($expiryDate, false);
            $status['is_expired'] = $expiryDate->isPast();
            $status['is_valid'] = !$status['is_expired'];
            $status['needs_renewal'] = $status['days_until_expiry'] <= 30; // Renovar si quedan 30 días o menos
        }

        return $status;
    }

    /**
     * Obtener estado de webservices de la empresa.
     * MÉTODO FALTANTE: Usado en DashboardController
     */
    protected function getWebserviceStatus(Company $company): array
    {
        return [
            'is_active' => $company->ws_active ?? false,
            'environment' => $company->ws_environment ?? 'testing',
            'last_connection' => $company->ws_last_connection,
            'connection_status' => $company->ws_status ?? 'disconnected',
            'has_errors' => $company->ws_last_error !== null,
            'last_error' => $company->ws_last_error,
        ];
    }

    /**
     * Obtener alertas de la empresa.
     * MÉTODO FALTANTE: Usado en DashboardController
     */
    protected function getCompanyAlerts(Company $company): array
    {
        $alerts = [];

        // Alertas de certificado
        $certStatus = $this->getCertificateStatus($company);
        if ($certStatus['is_expired']) {
            $alerts[] = [
                'type' => 'error',
                'title' => 'Certificado vencido',
                'message' => 'El certificado digital de la empresa ha vencido.',
            ];
        } elseif ($certStatus['needs_renewal']) {
            $alerts[] = [
                'type' => 'warning',
                'title' => 'Certificado por vencer',
                'message' => "El certificado vence en {$certStatus['days_until_expiry']} días.",
            ];
        }

        // Alertas de webservices
        $wsStatus = $this->getWebserviceStatus($company);
        if ($wsStatus['has_errors']) {
            $alerts[] = [
                'type' => 'warning',
                'title' => 'Error en webservice',
                'message' => 'Se detectaron errores en la conexión a webservices.',
            ];
        }

        // Alertas de operadores
        $inactiveOperators = $company->operators()->where('active', false)->count();
        if ($inactiveOperators > 0) {
            $alerts[] = [
                'type' => 'info',
                'title' => 'Operadores inactivos',
                'message' => "Hay {$inactiveOperators} operadores inactivos.",
            ];
        }

        return $alerts;
    }

    /**
     * Obtener estadísticas de operadores.
     * MÉTODO FALTANTE: Usado en DashboardController para company-admin
     */
    protected function getOperatorStats(Company $company): array
    {
        return [
            'total' => $company->operators()->count(),
            'active' => $company->operators()->where('active', true)->count(),
            'inactive' => $company->operators()->where('active', false)->count(),
            'with_import_permission' => $company->operators()->where('can_import', true)->count(),
            'with_export_permission' => $company->operators()->where('can_export', true)->count(),
            'with_transfer_permission' => $company->operators()->where('can_transfer', true)->count(),
            'external' => $company->operators()->where('type', 'external')->count(),
            'internal' => $company->operators()->where('type', 'internal')->count(),
        ];
    }

    /**
     * Obtener estado del sistema para la empresa.
     * MÉTODO FALTANTE: Usado en DashboardController para company-admin
     */
    protected function getSystemHealth(Company $company): array
    {
        return [
            'database_status' => 'online', // TODO: Implementar verificación real
            'webservice_status' => $company->ws_active ? 'active' : 'inactive',
            'certificate_status' => $this->getCertificateStatus($company)['is_valid'] ? 'valid' : 'invalid',
            'last_backup' => null, // TODO: Implementar cuando esté el sistema de backups
            'storage_usage' => 0, // TODO: Implementar verificación de almacenamiento
        ];
    }

    /**
     * Obtener actividad reciente de la empresa.
     * MÉTODO FALTANTE: Usado en DashboardController para company-admin
     */
    protected function getRecentActivity(Company $company): array
    {
        // TODO: Implementar cuando estén los módulos de auditoría
        return [
            'recent_logins' => [],
            'recent_operations' => [],
            'recent_changes' => [],
        ];
    }

    /**
     * Obtener tareas pendientes de la empresa.
     * MÉTODO FALTANTE: Usado en DashboardController para company-admin
     */
    protected function getPendingTasks(Company $company): array
    {
        $tasks = [];

        // Verificar certificado
        $certStatus = $this->getCertificateStatus($company);
        if ($certStatus['needs_renewal']) {
            $tasks[] = [
                'title' => 'Renovar certificado',
                'priority' => $certStatus['is_expired'] ? 'high' : 'medium',
                'due_date' => $certStatus['expires_at'],
            ];
        }

        // Verificar operadores inactivos
        $inactiveOperators = $company->operators()->where('active', false)->count();
        if ($inactiveOperators > 0) {
            $tasks[] = [
                'title' => 'Revisar operadores inactivos',
                'priority' => 'low',
                'due_date' => null,
            ];
        }

        return $tasks;
    }

    /**
     * Obtener estadísticas personales del usuario.
     * MÉTODO FALTANTE: Usado en DashboardController para users
     */
    protected function getPersonalStats($user): array
    {
        if (!$this->isUser()) {
            return [];
        }

        return [
            'permissions_summary' => [
                'can_import' => $this->canImport(),
                'can_export' => $this->canExport(),
                'can_transfer' => $this->canTransfer(),
            ],
            'activity_summary' => [
                'last_login' => $user->last_access,
                'total_operations' => 0, // TODO: Implementar cuando estén los módulos
                'recent_operations' => 0, // TODO: Implementar cuando estén los módulos
            ],
            'operator_info' => [
                'type' => $this->getUserOperator()?->type ?? 'unknown',
                'company_roles' => $this->getUserCompanyRoles(),
                'active' => $this->getUserOperator()?->active ?? false,
            ],
        ];
    }
}
