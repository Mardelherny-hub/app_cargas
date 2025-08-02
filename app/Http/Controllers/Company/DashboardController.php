<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Traits\UserHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use App\Models\VesselOwner;  
use App\Models\Shipment;
use App\Models\Voyage;
use App\Models\BillOfLading;
use App\Models\WebserviceTransaction;
use App\Models\Port;
use App\Models\Client;

class DashboardController extends Controller
{
    use UserHelper;

    /**
     * Mostrar dashboard de empresa para company-admin y user.
     */
    public function index()
    {
        $user = $this->getCurrentUser();

        // 1. Verificar permisos básicos (company-admin o user)
        if (!$this->isCompanyAdmin() && !$this->isUser()) {
            abort(403, 'No tiene permisos para acceder al dashboard de empresa.');
        }

        $company = $this->getUserCompany();

        // 2. Verificar que el usuario tenga una empresa asociada
        if (!$company) {
            return redirect()->route('dashboard')
                ->with('error', 'No se encontró la empresa asociada.');
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
        $userInfo = $user->getUserInfo();
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

            // Verificaciones de capacidades empresariales (usando métodos reales)
            'canDoCargas' => $this->hasCompanyRole('Cargas'),
            'canDoDesconsolidacion' => $this->hasCompanyRole('Desconsolidador'),
            'canDoTransbordos' => $this->hasCompanyRole('Transbordos'),
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
            'operatorStats' => $this->getOperatorStatsForAdmin($company),
            'systemHealth' => $this->getSystemHealth($company),
            'recentActivity' => $this->getRecentActivity($company),
            'pendingTasks' => $this->getPendingTasks($company),
        ];
    }

    /**
     * Obtener estadísticas de operadores para admin (estructura específica para la vista).
     */
    private function getOperatorStatsForAdmin(Company $company): array
    {
        return [
            'total' => $company->operators()->count(),
            'active' => $company->operators()->where('active', true)->count(),
            'inactive' => $company->operators()->where('active', false)->count(),
            'with_import_permission' => $company->operators()->where('can_import', true)->count(),
            'with_export_permission' => $company->operators()->where('can_export', true)->count(),
            'with_transfer_permission' => $company->operators()->where('can_transfer', true)->count(),
            'recent_logins' => $company->operators()->whereHas('user', function ($q) {
                $q->where('last_access', '>=', now()->subDays(7));
            })->count(),
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
     * Obtener estadísticas personales del usuario (estructura específica para la vista).
     */
    private function getPersonalStats($user): array
    {
        if (!$this->isUser()) {
            return [
                'my_shipments' => 0,
                'pending_shipments' => 0,
                'completed_shipments' => 0,
                'my_trips' => 0,
                'permissions_summary' => [
                    'can_import' => false,
                    'can_export' => false,
                    'can_transfer' => false,
                ]
            ];
        }

        $operator = $this->getUserOperator();

        return [
            'my_shipments' => 0, // TODO: Implementar cuando esté el módulo de cargas
            'pending_shipments' => 0, // TODO: Implementar cuando esté el módulo de cargas
            'completed_shipments' => 0, // TODO: Implementar cuando esté el módulo de cargas
            'my_trips' => 0, // TODO: Implementar cuando esté el módulo de viajes
            'last_activity' => $user->last_access,
            'permissions_summary' => [
                'can_import' => $operator ? $operator->can_import : false,
                'can_export' => $operator ? $operator->can_export : false,
                'can_transfer' => $operator ? $operator->can_transfer : false,
            ]
        ];
    }

    /**
     * Obtener acciones disponibles para el usuario.
     */
    private function getAvailableActions($user): array
    {
        if (!$this->isUser()) {
            return [];
        }

        $actions = [];
        $operator = $this->getUserOperator();

        if ($operator && $operator->can_import) {
            $actions[] = [
                'title' => 'Importar Datos',
                'icon' => 'download',
                'route' => route('company.import.index'),
            ];
        }

        if ($operator && $operator->can_export) {
            $actions[] = [
                'title' => 'Exportar Datos',
                'icon' => 'upload',
                'route' => route('company.export.index'),
            ];
        }

        if ($this->hasCompanyRole('Cargas')) {
            $actions[] = [
                'title' => 'Gestionar Cargas',
                'icon' => 'box',
                'route' => route('company.shipments.index'),
            ];

            $actions[] = [
                'title' => 'Gestionar Viajes',
                'icon' => 'truck',
                'route' => route('company.voyages.index'),
            ];
        }

        return $actions;
    }

    /**
     * Obtener alertas personales del usuario.
     */
    private function getPersonalAlerts($user): array
    {
        if (!$this->isUser()) {
            return [];
        }

        $alerts = [];
        $operator = $this->getUserOperator();

        // Verificar si el operador está inactivo
        if ($operator && !$operator->active) {
            $alerts[] = [
                'type' => 'warning',
                'message' => 'Su cuenta de operador está desactivada. Contacte al administrador.',
            ];
        }

        // Verificar si no tiene permisos
        if ($operator && !$operator->can_import && !$operator->can_export && !$operator->can_transfer) {
            $alerts[] = [
                'type' => 'warning',
                'message' => 'No tiene permisos asignados. Contacte al administrador para configurar sus permisos.',
            ];
        }

        return $alerts;
    }

    /**
     * Obtener trabajo reciente del usuario.
     */
    private function getRecentWork($user): array
    {
        // TODO: Implementar cuando estén los módulos de cargas y viajes
        return [];
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
        if (!$company->certificate_expires_at) {
            return [
                'status' => 'none',
                'expires_soon' => false,
                'is_expired' => false,
                'days_remaining' => null,
            ];
        }

        $expiresAt = Carbon::parse($company->certificate_expires_at);
        $now = Carbon::now();
        $daysRemaining = $now->diffInDays($expiresAt, false);

        if ($daysRemaining < 0) {
            return [
                'status' => 'expired',
                'expires_soon' => false,
                'is_expired' => true,
                'days_remaining' => abs($daysRemaining),
            ];
        } elseif ($daysRemaining <= 30) {
            return [
                'status' => 'expiring',
                'expires_soon' => true,
                'is_expired' => false,
                'days_remaining' => $daysRemaining,
            ];
        }

        return [
            'status' => 'valid',
            'expires_soon' => false,
            'is_expired' => false,
            'days_remaining' => $daysRemaining,
        ];
    }

    /**
     * Obtener estado de webservices.
     */
    private function getWebserviceStatus(Company $company): array
    {
        return [
            'active' => (bool) $company->ws_active,
            'environment' => $company->ws_environment ?? 'testing',
            'last_connection' => null, // TODO: Implementar cuando esté el módulo de webservices
        ];
    }

    /**
     * Obtener alertas de la empresa.
     */
    private function getCompanyAlerts(Company $company): array
    {
        $alerts = [];

        // Verificar certificado
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

        // Verificar operadores activos
        if ($this->isCompanyAdmin()) {
            $activeOperators = $company->operators()->where('active', true)->count();
            if ($activeOperators === 0) {
                $alerts[] = [
                    'type' => 'warning',
                    'message' => 'No hay operadores activos configurados.',
                    'action' => route('company.operators.index'),
                ];
            }
        }

        // Verificar webservices
        if (!$company->ws_active && !empty($company->company_roles)) {
            $alerts[] = [
                'type' => 'info',
                'message' => 'Los webservices están desactivados. Algunas funcionalidades no estarán disponibles.',
                'action' => null,
            ];
        }

         $pendingVerification = VesselOwner::byCompany($company->id)->where('status', 'pending_verification')->count();
        if ($pendingVerification > 0) {
            $alerts[] = [
                'type' => 'warning',
                'message' => "Tiene {$pendingVerification} propietario(s) pendiente(s) de verificación fiscal.",
                'action' => route('company.vessel-owners.index', ['status' => 'pending_verification']),
            ];
        }

        return $alerts;
    }

    /**
     * Obtener estado de salud del sistema.
     */
    private function getSystemHealth(Company $company): array
    {
        return [
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
            $priority = $certStatus['is_expired'] ? 'high' : 'medium';
            $tasks[] = [
                'priority' => $priority,
                'title' => 'Renovar certificado digital',
                'description' => $certStatus['is_expired']
                    ? 'El certificado digital ha expirado'
                    : "El certificado expira en {$certStatus['days_remaining']} días",
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

        // Verificar roles de empresa
        if (empty($company->company_roles)) {
            $tasks[] = [
                'priority' => 'medium',
                'title' => 'Configurar roles de empresa',
                'description' => 'No se han asignado roles específicos a la empresa',
                'action' => null, // TODO: Agregar ruta cuando esté disponible
            ];
        }

        return $tasks;
    }

    /**
     * Obtener permisos específicos para elementos de la interfaz.
     */
    private function getUIPermissions(): array
    {
        $user = $this->getCurrentUser();
        $isCompanyAdmin = $this->isCompanyAdmin();

        return [
            // Gestión de la empresa (solo company-admin puede gestionar)
            'canManageOperators' => $isCompanyAdmin,
            'canManageCertificates' => $isCompanyAdmin,
            'canManageSettings' => $isCompanyAdmin,
            'canViewReports' => $this->canPerform('view_reports'),
            'canManageWebservices' => $isCompanyAdmin,

            // Operaciones específicas por rol de empresa (usando métodos reales)
            'canManageCargas' => $this->hasCompanyRole('Cargas'),
            'canManageDesconsolidacion' => $this->hasCompanyRole('Desconsolidador'),
            'canManageTransbordos' => $this->hasCompanyRole('Transbordos'),

            // Permisos granulares de operador (solo para users) - usando métodos reales
            'canImport' => $this->canPerform('import'),
            'canExport' => $this->canPerform('export'),
            'canTransfer' => $this->canPerform('transfer'),

            // Navegación (usando métodos reales de canPerform)
            'canAccessTrips' => $this->canPerform('view_cargas'), // Los viajes requieren rol Cargas
            'canAccessShipments' => $this->canPerform('view_cargas'), // Las cargas requieren rol Cargas
            'canAccessImport' => $this->canPerform('import'),
            'canAccessExport' => $this->canPerform('export'),
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
        $vesselOwnersQuery = VesselOwner::byCompany($company->id);
        $stats['vessel_owners'] = [
            'total' => $vesselOwnersQuery->count(),
            'active' => $vesselOwnersQuery->where('status', 'active')->count(),
            'pending_verification' => $vesselOwnersQuery->where('status', 'pending_verification')->count(),
            'webservice_authorized' => $vesselOwnersQuery->where('webservice_authorized', true)->count(),
            'created_this_month' => $vesselOwnersQuery->where('created_at', '>=', now()->startOfMonth())->count(),
        ];

        // Estadísticas específicas por rol de empresa
        $companyRoles = $company->company_roles ?? [];

         if (in_array('Cargas', $companyRoles)) {
            $shipments = Shipment::whereHas('voyage', function($query) use ($company) {
                $query->where('company_id', $company->id);
            });

            $voyages = Voyage::where('company_id', $company->id)->where('active', true);

            $stats['cargas_stats'] = [
                'recent_shipments' => $shipments->where('created_at', '>=', now()->subDays(7))->count(),
                'pending_shipments' => $shipments->where('status', 'pending')->count(),
                'completed_trips' => $voyages->where('status', 'completed')->count(),
                'active_trips' => $voyages->whereIn('status', ['in_progress', 'loading'])->count(),
            ];
        }

        if (in_array('Desconsolidador', $companyRoles)) {
            $deconsolidations = WebserviceTransaction::where('company_id', $company->id)
                ->where('webservice_type', 'desconsolidados');

            $stats['desconsolidacion_stats'] = [
                'pending_deconsolidations' => $deconsolidations->where('status', 'pending')->count(),
                'completed_deconsolidations' => $deconsolidations->where('status', 'success')->count(),
            ];
        }

        if (in_array('Transbordos', $companyRoles)) {
            $transfers = WebserviceTransaction::where('company_id', $company->id)
                ->where('webservice_type', 'transbordos');

            $stats['transbordos_stats'] = [
                'pending_transfers' => $transfers->where('status', 'pending')->count(),
                'completed_transfers' => $transfers->where('status', 'success')->count(),
            ];
        }

        return $stats;
    }
}
