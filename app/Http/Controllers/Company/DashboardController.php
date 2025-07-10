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
        $user = $this->getCurrentUser();
        $company = $this->getUserCompany();

        if (!$company) {
            return redirect()->route('dashboard')
                ->with('error', 'No se encontró la empresa asociada a su usuario.');
        }

        // Verificar acceso a la empresa
        if (!$this->canAccessCompany($company->id)) {
            abort(403, 'No tiene permisos para acceder a esta empresa.');
        }

        // Obtener información del usuario
        $userInfo = $this->getUserInfo();
        $companyRoles = $this->getUserCompanyRoles();
        $operatorPermissions = $this->getUserOperatorPermissions();

        // Estadísticas de la empresa
        $stats = $this->getCompanyStats($company);

        // Datos específicos según rol
        $dashboardData = $this->getDashboardData($company, $user);

        // Configurar vista según rol del usuario
        $viewData = [
            'company' => $company,
            'user' => $user,
            'userInfo' => $userInfo,
            'companyRoles' => $companyRoles,
            'operatorPermissions' => $operatorPermissions,
            'stats' => $stats,
            'isCompanyAdmin' => $this->isCompanyAdmin(),
            'isUser' => $this->isUser(),
            'canManageOperators' => $this->canPerform('manage_operators'),
            'canManageCertificates' => $this->canPerform('manage_certificates'),
            'canManageSettings' => $this->canPerform('manage_settings'),
        ];

        // Agregar datos específicos del dashboard
        $viewData = array_merge($viewData, $dashboardData);

        return view('company.dashboard', $viewData);
    }

    /**
     * Obtener estadísticas de la empresa.
     */
    private function getCompanyStats(Company $company): array
    {
        return [
            'total_operators' => $company->operators()->count(),
            'active_operators' => $company->operators()->where('active', true)->count(),
            'recent_shipments' => 0, // TODO: Implementar cuando esté el módulo de cargas
            'pending_shipments' => 0, // TODO: Implementar cuando esté el módulo de cargas
            'completed_trips' => 0, // TODO: Implementar cuando esté el módulo de viajes
            'active_trips' => 0, // TODO: Implementar cuando esté el módulo de viajes
            'last_activity' => $company->updated_at,
        ];
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
        $data['recentActivity'] = $this->getRecentActivity($company);

        // Datos específicos para company-admin
        if ($this->isCompanyAdmin()) {
            $data['recentOperators'] = $company->operators()
                ->with('user')
                ->latest()
                ->take(5)
                ->get();

            $data['operatorStats'] = $this->getOperatorStats($company);
            $data['systemHealth'] = $this->getSystemHealth($company);
        }

        // Datos específicos para user
        if ($this->isUser()) {
            $data['personalStats'] = $this->getPersonalStats($user);
            $data['availableActions'] = $this->getAvailableActions($user);
            $data['personalAlerts'] = $this->getPersonalAlerts($user);
        }

        return $data;
    }

    /**
     * Obtener estado del certificado.
     */
    private function getCertificateStatus(Company $company): array
    {
        $status = [
            'has_certificate' => !empty($company->certificate_path),
            'expires_at' => $company->certificate_expires_at,
            'is_expired' => false,
            'expires_soon' => false,
            'status' => 'valid',
        ];

        if ($company->certificate_expires_at) {
            $expiresAt = Carbon::parse($company->certificate_expires_at);
            $now = Carbon::now();

            $status['is_expired'] = $expiresAt->isPast();
            $status['expires_soon'] = $expiresAt->diffInDays($now) <= 30;

            if ($status['is_expired']) {
                $status['status'] = 'expired';
            } elseif ($status['expires_soon']) {
                $status['status'] = 'expiring';
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

        // Alerta de certificado
        $certificateStatus = $this->getCertificateStatus($company);
        if ($certificateStatus['is_expired']) {
            $alerts[] = [
                'type' => 'danger',
                'title' => 'Certificado Expirado',
                'message' => 'El certificado digital ha expirado. Renueve inmediatamente.',
                'action' => route('company.certificates.index'),
            ];
        } elseif ($certificateStatus['expires_soon']) {
            $alerts[] = [
                'type' => 'warning',
                'title' => 'Certificado por Vencer',
                'message' => 'El certificado digital expira en menos de 30 días.',
                'action' => route('company.certificates.index'),
            ];
        }

        // Alerta de webservices
        if (!($company->ws_active ?? false)) {
            $alerts[] = [
                'type' => 'info',
                'title' => 'Webservices Inactivos',
                'message' => 'Los webservices no están configurados o están inactivos.',
                'action' => route('company.settings.index'),
            ];
        }

        return $alerts;
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
            'recent_operators' => [],
        ];
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
                return $operator->user && $operator->user->last_access >= Carbon::now()->subDays(7);
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
        ];
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
        $companyRoles = $this->getUserCompanyRoles();
        $operatorPermissions = $this->getUserOperatorPermissions();

        // Acciones según roles de empresa
        if (in_array('Cargas', $companyRoles)) {
            $actions[] = [
                'name' => 'Gestionar Cargas',
                'icon' => 'truck',
                'route' => 'company.shipments.index',
                'description' => 'Ver y gestionar cargas de la empresa',
            ];
        }

        if (in_array('Desconsolidador', $companyRoles)) {
            $actions[] = [
                'name' => 'Desconsolidación',
                'icon' => 'package',
                'route' => 'company.deconsolidation.index',
                'description' => 'Gestionar procesos de desconsolidación',
            ];
        }

        if (in_array('Transbordos', $companyRoles)) {
            $actions[] = [
                'name' => 'Transbordos',
                'icon' => 'exchange-alt',
                'route' => 'company.transfers.index',
                'description' => 'Gestionar transbordos',
            ];
        }

        // Acciones según permisos de operador
        if ($operatorPermissions['can_import']) {
            $actions[] = [
                'name' => 'Importar Datos',
                'icon' => 'upload',
                'route' => 'company.import.index',
                'description' => 'Importar datos desde archivos',
            ];
        }

        if ($operatorPermissions['can_export']) {
            $actions[] = [
                'name' => 'Exportar Datos',
                'icon' => 'download',
                'route' => 'company.export.index',
                'description' => 'Exportar datos a archivos',
            ];
        }

        // Acciones comunes
        $actions[] = [
            'name' => 'Reportes',
            'icon' => 'chart-bar',
            'route' => 'company.reports.index',
            'description' => 'Ver reportes y estadísticas',
        ];

        return $actions;
    }

    /**
     * Obtener alertas personales del usuario (solo para user).
     */
    private function getPersonalAlerts($user): array
    {
        if (!$this->isUser()) {
            return [];
        }

        $alerts = [];

        // Alerta de primer acceso
        if (!$user->last_access) {
            $alerts[] = [
                'type' => 'info',
                'title' => '¡Bienvenido!',
                'message' => 'Complete su perfil y explore las funcionalidades disponibles.',
                'action' => route('profile.show'),
            ];
        }

        // TODO: Agregar más alertas personales según el contexto

        return $alerts;
    }

    /**
     * Extender el método canPerform para incluir acciones específicas de empresa.
     */
    public function canPerform(string $action): bool
    {
        $basePermission = parent::canPerform($action);

        // Si ya tiene permiso por el método base, devolver true
        if ($basePermission) {
            return true;
        }

        // Permisos específicos de empresa
        switch ($action) {
            case 'manage_operators':
            case 'manage_certificates':
            case 'manage_settings':
                return $this->isCompanyAdmin();
            default:
                return false;
        }
    }
}
