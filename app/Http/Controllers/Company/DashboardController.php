<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Operator;
use App\Traits\UserHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class DashboardController extends Controller
{
    use UserHelper;

    /**
     * Mostrar el dashboard de la empresa (company-admin o user).
     */
    public function index()
    {
        // 1. Verificar permisos básicos de acceso al dashboard
        if (!$this->canPerform('dashboard_access')) {
            abort(403, 'No tiene permisos para acceder al dashboard de empresa.');
        }

        $user = $this->getCurrentUser();
        $company = $this->getUserCompany();

        // 2. Verificar que el usuario tenga una empresa asociada
        if (!$company) {
            return redirect()->route('dashboard')
                ->with('error', 'No se encontró la empresa asociada a su usuario.');
        }

        // 3. Verificar acceso específico a esta empresa
        if (!$this->canAccessCompany($company->id)) {
            abort(403, 'No tiene permisos para acceder a esta empresa.');
        }

        // 4. Verificar permisos específicos según el rol
        if ($this->isUser()) {
            // Los usuarios regulares necesitan verificaciones adicionales
            if (!$this->hasValidOperatorPermissions()) {
                return redirect()->route('dashboard')
                    ->with('error', 'Su cuenta no tiene permisos de operador configurados.');
            }
        }

        // Obtener información contextual del usuario
        $userInfo = $this->getUserInfo();
        $companyRoles = $this->getUserCompanyRoles();
        $operatorPermissions = $this->getUserOperatorPermissions();

        // Obtener estadísticas de la empresa
        $stats = $this->getCompanyStats($company);

        // Obtener datos específicos del dashboard según el rol
        $dashboardData = $this->getDashboardData($company, $user);

        // Obtener permisos específicos para mostrar/ocultar elementos de la interfaz
        $permissions = $this->getUIPermissions();

        // Configurar datos para la vista
        $viewData = [
            'company' => $company,
            'user' => $user,
            'userInfo' => $userInfo,
            'companyRoles' => $companyRoles,
            'operatorPermissions' => $operatorPermissions,
            'stats' => $stats,
            'permissions' => $permissions,

            // Flags de rol para la vista
            'isCompanyAdmin' => $this->isCompanyAdmin(),
            'isUser' => $this->isUser(),

            // Verificaciones de capacidades empresariales
            'canDoCargas' => $this->canDoCargas(),
            'canDoDesconsolidacion' => $this->canDoDesconsolidacion(),
            'canDoTransbordos' => $this->canDoTransbordos(),
        ];

        // Agregar datos específicos del dashboard
        $viewData = array_merge($viewData, $dashboardData);

        return view('company.dashboard', $viewData);
    }

    /**
     * Verificar si el usuario tiene permisos de operador válidos.
     */
    private function hasValidOperatorPermissions(): bool
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
     * Obtener permisos específicos para elementos de la interfaz.
     */
    private function getUIPermissions(): array
    {
        return [
            // Gestión de la empresa
            'canManageOperators' => $this->canPerform('manage_operators'),
            'canManageCertificates' => $this->canPerform('manage_certificates'),
            'canManageSettings' => $this->canPerform('manage_settings'),
            'canViewReports' => $this->canPerform('view_reports'),
            'canManageWebservices' => $this->canPerform('manage_webservices'),

            // Operaciones específicas por rol de empresa
            'canManageCargas' => $this->canDoCargas(),
            'canManageDesconsolidacion' => $this->canDoDesconsolidacion(),
            'canManageTransbordos' => $this->canDoTransbordos(),

            // Permisos granulares de operador (solo para users)
            'canImport' => $this->canImport(),
            'canExport' => $this->canExport(),
            'canTransfer' => $this->canTransfer(),

            // Navegación
            'canAccessTrips' => $this->canPerform('access_trips'),
            'canAccessShipments' => $this->canPerform('access_shipments'),
            'canAccessImport' => $this->canPerform('access_import'),
            'canAccessExport' => $this->canPerform('access_export'),
        ];
    }

    /**
     * Obtener estadísticas de la empresa.
     */
    private function getCompanyStats(Company $company): array
    {
        $stats = [
            'total_operators' => $company->operators()->count(),
            'active_operators' => $company->operators()->where('active', true)->count(),
            'operators_with_import' => $company->operators()->where('can_import', true)->count(),
            'operators_with_export' => $company->operators()->where('can_export', true)->count(),
            'operators_with_transfer' => $company->operators()->where('can_transfer', true)->count(),
            'last_activity' => $company->updated_at,
        ];

        // Estadísticas específicas por rol de empresa
        $companyRoles = $company->company_roles ?? [];

        if (in_array('Cargas', $companyRoles)) {
            $stats['cargas_stats'] = [
                'recent_shipments' => 0, // TODO: Implementar cuando esté el módulo de cargas
                'pending_shipments' => 0, // TODO: Implementar cuando esté el módulo de cargas
                'completed_trips' => 0, // TODO: Implementar cuando esté el módulo de viajes
                'active_trips' => 0, // TODO: Implementar cuando esté el módulo de viajes
            ];
        }

        if (in_array('Desconsolidador', $companyRoles)) {
            $stats['desconsolidacion_stats'] = [
                'pending_deconsolidations' => 0, // TODO: Implementar cuando esté el módulo
                'completed_deconsolidations' => 0, // TODO: Implementar cuando esté el módulo
            ];
        }

        if (in_array('Transbordos', $companyRoles)) {
            $stats['transbordos_stats'] = [
                'pending_transfers' => 0, // TODO: Implementar cuando esté el módulo
                'completed_transfers' => 0, // TODO: Implementar cuando esté el módulo
            ];
        }

        return $stats;
    }

    /**
     * Obtener datos del dashboard según el rol del usuario.
     */
    private function getDashboardData(Company $company, $user): array
    {
        $data = [];

        // Datos comunes para todos los usuarios
        $data['certificateStatus'] = $this->getCertificateStatus($company);
        $data['webserviceStatus'] = $this->getWebserviceStatus($company);
        $data['companyAlerts'] = $this->getCompanyAlerts($company);
        $data['companyRolesInfo'] = $this->getCompanyRolesInfo($company);

        // Datos específicos para company-admin
        if ($this->isCompanyAdmin()) {
            $data['adminDashboard'] = $this->getAdminDashboardData($company);
        }

        // Datos específicos para user
        if ($this->isUser()) {
            $data['userDashboard'] = $this->getUserDashboardData($user);
        }

        return $data;
    }

    /**
     * Obtener datos específicos del dashboard para company-admin.
     */
    private function getAdminDashboardData(Company $company): array
    {
        return [
            'recentOperators' => $company->operators()
                ->with('user')
                ->latest()
                ->take(5)
                ->get(),
            'operatorStats' => $this->getOperatorStats($company),
            'systemHealth' => $this->getSystemHealth($company),
            'recentActivity' => $this->getRecentActivity($company),
            'pendingTasks' => $this->getPendingTasks($company),
        ];
    }

    /**
     * Obtener datos específicos del dashboard para user.
     */
    private function getUserDashboardData($user): array
    {
        return [
            'personalStats' => $this->getPersonalStats($user),
            'availableActions' => $this->getAvailableActions($user),
            'personalAlerts' => $this->getPersonalAlerts($user),
            'recentWork' => $this->getRecentWork($user),
        ];
    }

    /**
     * Obtener información sobre los roles de empresa.
     */
    private function getCompanyRolesInfo(Company $company): array
    {
        $roles = $company->company_roles ?? [];

        return [
            'roles' => $roles,
            'hasCargas' => in_array('Cargas', $roles),
            'hasDesconsolidador' => in_array('Desconsolidador', $roles),
            'hasTransbordos' => in_array('Transbordos', $roles),
            'rolesCount' => count($roles),
            'capabilities' => $this->getCapabilitiesByRoles($roles),
        ];
    }

    /**
     * Obtener capacidades según los roles de empresa.
     */
    private function getCapabilitiesByRoles(array $roles): array
    {
        $capabilities = [];

        foreach ($roles as $role) {
            switch ($role) {
                case 'Cargas':
                    $capabilities[] = 'Gestión de cargas y viajes';
                    $capabilities[] = 'Webservices MIC/DTA';
                    $capabilities[] = 'Manifiestos y conocimientos';
                    break;
                case 'Desconsolidador':
                    $capabilities[] = 'Desconsolidación de cargas';
                    $capabilities[] = 'Webservices de desconsolidados';
                    break;
                case 'Transbordos':
                    $capabilities[] = 'Gestión de transbordos';
                    $capabilities[] = 'Webservices de transbordos';
                    break;
            }
        }

        return array_unique($capabilities);
    }

    /**
     * Obtener estado del certificado.
     */
    private function getCertificateStatus(Company $company): array
    {
        $status = [
            'has_certificate' => !empty($company->certificate_expires_at),
            'expires_at' => $company->certificate_expires_at,
            'is_expired' => false,
            'expires_soon' => false,
            'status' => 'none',
            'days_remaining' => null,
        ];

        if ($company->certificate_expires_at) {
            $expiresAt = Carbon::parse($company->certificate_expires_at);
            $now = Carbon::now();

            $status['is_expired'] = $expiresAt->isPast();
            $status['expires_soon'] = $expiresAt->diffInDays($now) <= 30;
            $status['days_remaining'] = $expiresAt->diffInDays($now);

            if ($status['is_expired']) {
                $status['status'] = 'expired';
            } elseif ($status['expires_soon']) {
                $status['status'] = 'expiring';
            } else {
                $status['status'] = 'valid';
            }
        }

        return $status;
    }

    /**
     * Obtener estado de webservices.
     */
    private function getWebserviceStatus(Company $company): array
    {
        return [
            'active' => $company->ws_active ?? false,
            'environment' => $company->ws_environment ?? 'test',
            'can_use_production' => $company->ws_active && $company->ws_environment === 'production',
            'requires_certificate' => true,
            'certificate_valid' => $this->getCertificateStatus($company)['status'] === 'valid',
            'last_connection' => null, // TODO: Implementar cuando esté el módulo de webservices
            'pending_sends' => 0, // TODO: Implementar cuando esté el módulo de webservices
            'failed_sends' => 0, // TODO: Implementar cuando esté el módulo de webservices
            'success_rate' => 0, // TODO: Implementar cuando esté el módulo de webservices
        ];
    }

    /**
     * Obtener alertas de la empresa.
     */
    private function getCompanyAlerts(Company $company): array
    {
        $alerts = [];

        // Alerta de certificado expirado
        $certStatus = $this->getCertificateStatus($company);
        if ($certStatus['is_expired']) {
            $alerts[] = [
                'type' => 'error',
                'message' => 'El certificado digital ha expirado. Los webservices no funcionarán.',
                'action' => route('company.certificates.index'),
            ];
        } elseif ($certStatus['expires_soon']) {
            $alerts[] = [
                'type' => 'warning',
                'message' => "El certificado digital expira en {$certStatus['days_remaining']} días.",
                'action' => route('company.certificates.index'),
            ];
        }

        // Alerta de empresa inactiva
        if (!$company->active) {
            $alerts[] = [
                'type' => 'error',
                'message' => 'La empresa está inactiva. Contacte al administrador.',
                'action' => null,
            ];
        }

        // Alerta de falta de operadores activos (solo para company-admin)
        if ($this->isCompanyAdmin()) {
            $activeOperators = $company->operators()->where('active', true)->count();
            if ($activeOperators === 0) {
                $alerts[] = [
                    'type' => 'warning',
                    'message' => 'No tiene operadores activos configurados.',
                    'action' => route('company.operators.index'),
                ];
            }
        }

        return $alerts;
    }

    /**
     * Obtener estadísticas de operadores (solo para company-admin).
     */
    private function getOperatorStats(Company $company): array
    {
        if (!$this->isCompanyAdmin()) {
            return [];
        }

        $operators = $company->operators()->with('user')->get();

        return [
            'total' => $operators->count(),
            'active' => $operators->where('active', true)->count(),
            'with_import_permission' => $operators->where('can_import', true)->count(),
            'with_export_permission' => $operators->where('can_export', true)->count(),
            'with_transfer_permission' => $operators->where('can_transfer', true)->count(),
            'recent_logins' => $operators->filter(function ($operator) {
                return $operator->user && $operator->user->last_access &&
                       $operator->user->last_access >= Carbon::now()->subDays(7);
            })->count(),
        ];
    }

    /**
     * Obtener salud del sistema para la empresa (solo para company-admin).
     */
    private function getSystemHealth(Company $company): array
    {
        if (!$this->isCompanyAdmin()) {
            return [];
        }

        return [
            'overall_status' => 'good', // TODO: Implementar lógica real
            'certificate_status' => $this->getCertificateStatus($company)['status'],
            'webservice_status' => $company->ws_active ? 'active' : 'inactive',
            'operators_status' => $company->operators()->where('active', true)->count() > 0 ? 'active' : 'inactive',
            'company_roles_configured' => !empty($company->company_roles),
        ];
    }

    /**
     * Obtener actividad reciente de la empresa.
     */
    private function getRecentActivity(Company $company): array
    {
        return [
            // TODO: Implementar cuando estén los módulos de cargas, viajes, etc.
            'recent_shipments' => [],
            'recent_trips' => [],
            'recent_operators' => $company->operators()->with('user')->latest()->take(3)->get(),
        ];
    }

    /**
     * Obtener tareas pendientes (solo para company-admin).
     */
    private function getPendingTasks(Company $company): array
    {
        if (!$this->isCompanyAdmin()) {
            return [];
        }

        $tasks = [];

        // Verificar certificado
        $certStatus = $this->getCertificateStatus($company);
        if ($certStatus['expires_soon'] || $certStatus['is_expired']) {
            $tasks[] = [
                'priority' => 'high',
                'title' => 'Renovar certificado digital',
                'description' => 'El certificado digital necesita ser renovado',
                'action' => route('company.certificates.index'),
            ];
        }

        // Verificar operadores
        $activeOperators = $company->operators()->where('active', true)->count();
        if ($activeOperators === 0) {
            $tasks[] = [
                'priority' => 'medium',
                'title' => 'Configurar operadores',
                'description' => 'No hay operadores activos configurados',
                'action' => route('company.operators.index'),
            ];
        }

        return $tasks;
    }

    /**
     * Obtener estadísticas personales del usuario (solo para user).
     */
    private function getPersonalStats($user): array
    {
        if (!$this->isUser()) {
            return [];
        }

        return [
            'my_shipments' => 0, // TODO: Implementar cuando esté el módulo de cargas
            'pending_shipments' => 0, // TODO: Implementar cuando esté el módulo de cargas
            'completed_shipments' => 0, // TODO: Implementar cuando esté el módulo de cargas
            'my_trips' => 0, // TODO: Implementar cuando esté el módulo de viajes
            'active_trips' => 0, // TODO: Implementar cuando esté el módulo de viajes
            'last_activity' => $user->last_access,
            'permissions_summary' => $this->getPersonalPermissionsSummary(),
        ];
    }

    /**
     * Obtener resumen de permisos personales.
     */
    private function getPersonalPermissionsSummary(): array
    {
        return [
            'can_import' => $this->canImport(),
            'can_export' => $this->canExport(),
            'can_transfer' => $this->canTransfer(),
            'company_roles' => $this->getUserCompanyRoles(),
            'operator_type' => $this->getUserOperator()?->type ?? 'unknown',
        ];
    }

    /**
     * Obtener acciones disponibles para el usuario (solo para user).
     */
    private function getAvailableActions($user): array
    {
        if (!$this->isUser()) {
            return [];
        }

        $actions = [];

        // Acciones según permisos de operador
        if ($this->canImport()) {
            $actions[] = [
                'title' => 'Importar Cargas',
                'description' => 'Importar información de cargas',
                'icon' => 'download',
                'route' => route('company.import.index'),
            ];
        }

        if ($this->canExport()) {
            $actions[] = [
                'title' => 'Exportar Datos',
                'description' => 'Exportar información de la empresa',
                'icon' => 'upload',
                'route' => route('company.export.index'),
            ];
        }

        if ($this->canTransfer()) {
            $actions[] = [
                'title' => 'Gestionar Transbordos',
                'description' => 'Administrar transbordos',
                'icon' => 'truck',
                'route' => route('company.transfers.index'),
            ];
        }

        // Acciones según roles de empresa
        if ($this->canDoCargas()) {
            $actions[] = [
                'title' => 'Gestionar Cargas',
                'description' => 'Administrar cargas y viajes',
                'icon' => 'box',
                'route' => route('company.shipments.index'),
            ];
        }

        return $actions;
    }

    /**
     * Obtener alertas personales (solo para user).
     */
    private function getPersonalAlerts($user): array
    {
        if (!$this->isUser()) {
            return [];
        }

        $alerts = [];

        // Verificar estado del operador
        $operator = $this->getUserOperator();
        if ($operator && !$operator->active) {
            $alerts[] = [
                'type' => 'warning',
                'message' => 'Su cuenta de operador está inactiva.',
            ];
        }

        // Verificar permisos
        if (!$this->canImport() && !$this->canExport() && !$this->canTransfer()) {
            $alerts[] = [
                'type' => 'warning',
                'message' => 'No tiene permisos de operador configurados.',
            ];
        }

        return $alerts;
    }

    /**
     * Obtener trabajo reciente del usuario (solo para user).
     */
    private function getRecentWork($user): array
    {
        if (!$this->isUser()) {
            return [];
        }

        return [
            // TODO: Implementar cuando estén los módulos específicos
            'recent_shipments' => [],
            'recent_trips' => [],
            'recent_imports' => [],
            'recent_exports' => [],
        ];
    }

        /**
         * Verifica si la empresa tiene el rol "Cargas".
         */
        private function canDoCargas(): bool
        {
            $company = $this->getUserCompany();
            if (!$company) {
                return false;
            }
            $roles = $company->company_roles ?? [];
            return in_array('Cargas', $roles);
        }

        /**
         * Verifica si la empresa tiene el rol "Desconsolidador".
         */
        private function canDoDesconsolidacion(): bool
        {
            $company = $this->getUserCompany();
            if (!$company) {
                return false;
            }
            $roles = $company->company_roles ?? [];
            return in_array('Desconsolidador', $roles);
        }

        /**
         * Verifica si la empresa tiene el rol "Transbordos".
         */
        private function canDoTransbordos(): bool
        {
            $company = $this->getUserCompany();
            if (!$company) {
                return false;
            }
            $roles = $company->company_roles ?? [];
            return in_array('Transbordos', $roles);
        }
}
