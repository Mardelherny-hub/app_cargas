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
use App\Models\Operator;

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
     * Obtener estadísticas de la empresa - SOLO CAMPOS REALES.
     */
    private function getCompanyStats(Company $company): array
    {
        // Estadísticas básicas de operadores
        $operatorsQuery = Operator::where('company_id', $company->id);
        $stats = [
            'total_operators' => $operatorsQuery->count(),
            'active_operators' => $operatorsQuery->where('active', true)->count(),
            'operators_with_import' => $operatorsQuery->where('can_import', true)->count(),
            'operators_with_export' => $operatorsQuery->where('can_export', true)->count(),
            'operators_with_transfer' => $operatorsQuery->where('can_transfer', true)->count(),
            'last_activity' => $company->updated_at,
        ];

        // Estadísticas de viajes - SOLO CAMPOS REALES
        $voyagesQuery = Voyage::where('company_id', $company->id);
        $stats['active_voyages'] = $voyagesQuery->whereIn('status', ['planning', 'approved', 'in_progress'])->count();
        $stats['completed_voyages'] = $voyagesQuery->where('status', 'completed')->count();
        $stats['total_voyages'] = $voyagesQuery->count();

        // Estadísticas de cargas - SOLO CAMPOS REALES
        $shipmentsQuery = Shipment::whereHas('voyage', function($query) use ($company) {
            $query->where('company_id', $company->id);
        });
        $stats['pending_shipments'] = $shipmentsQuery->where('status', 'planning')->count();
        $stats['total_shipments'] = $shipmentsQuery->count();
        $stats['recent_shipments'] = $shipmentsQuery->where('created_at', '>=', now()->subDays(7))->count();

        // Estadísticas de manifiestos del mes - SOLO CAMPOS REALES
        $billsQuery = BillOfLading::whereHas('shipment.voyage', function($query) use ($company) {
            $query->where('company_id', $company->id);
        });
        $stats['monthly_manifests'] = $billsQuery->whereBetween('created_at', [
            now()->startOfMonth(),
            now()->endOfMonth()
        ])->count();

        // Estadísticas de webservices - SOLO CAMPOS REALES
        $webservicesQuery = WebserviceTransaction::where('company_id', $company->id);
        $stats['weekly_webservices'] = $webservicesQuery->where('created_at', '>=', now()->subDays(7))->count();
        $stats['total_webservices'] = $webservicesQuery->count();
        $stats['successful_webservices'] = $webservicesQuery->where('status', 'success')->count();

        // Estadísticas de propietarios de embarcaciones - SOLO CAMPOS REALES
        $vesselOwnersQuery = VesselOwner::byCompany($company->id);
        $stats['vessel_owners'] = [
            'total' => $vesselOwnersQuery->count(),
            'active' => $vesselOwnersQuery->where('status', 'active')->count(),
            'pending_verification' => $vesselOwnersQuery->where('status', 'pending_verification')->count(),
            'webservice_authorized' => $vesselOwnersQuery->where('webservice_authorized', true)->count(),
            'created_this_month' => $vesselOwnersQuery->where('created_at', '>=', now()->startOfMonth())->count(),
        ];

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
     * Obtener datos específicos del dashboard para company-admin - SOLO CAMPOS REALES.
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
            'recentActivity' => $this->getRecentActivity($company),
        ];
    }

    /**
     * Obtener estadísticas de operadores para admin - SOLO CAMPOS REALES.
     */
    private function getOperatorStatsForAdmin(Company $company): array
    {
        $operatorsQuery = Operator::where('company_id', $company->id);
        
        return [
            'total' => $operatorsQuery->count(),
            'active' => $operatorsQuery->where('active', true)->count(),
            'inactive' => $operatorsQuery->where('active', false)->count(),
            'with_import_permission' => $operatorsQuery->where('can_import', true)->count(),
            'with_export_permission' => $operatorsQuery->where('can_export', true)->count(),
            'with_transfer_permission' => $operatorsQuery->where('can_transfer', true)->count(),
            'recent_logins' => $operatorsQuery->whereHas('user', function ($q) {
                $q->where('last_access', '>=', now()->subDays(7));
            })->count(),
        ];
    }

    /**
     * Obtener actividad reciente de la empresa - SOLO CAMPOS REALES.
     */
    private function getRecentActivity(Company $company): array
    {
        $activities = [];

        // Actividad de viajes recientes - SOLO CAMPOS REALES
        $recentVoyages = Voyage::where('company_id', $company->id)
            ->latest()
            ->take(3)
            ->get(['id', 'voyage_number', 'internal_reference', 'created_at']);

        foreach ($recentVoyages as $voyage) {
            $activities[] = [
                'description' => "Nuevo viaje: {$voyage->voyage_number}" . ($voyage->internal_reference ? " ({$voyage->internal_reference})" : ""),
                'time' => $voyage->created_at->diffForHumans(),
                'type' => 'voyage'
            ];
        }

        // Actividad de webservices recientes - SOLO CAMPOS REALES
        $recentWebservices = WebserviceTransaction::where('company_id', $company->id)
            ->latest()
            ->take(2)
            ->get(['webservice_type', 'status', 'created_at']);

        foreach ($recentWebservices as $ws) {
            $activities[] = [
                'description' => "Webservice {$ws->webservice_type}: {$ws->status}",
                'time' => $ws->created_at->diffForHumans(),
                'type' => 'webservice'
            ];
        }

        // Ordenar por tiempo
        usort($activities, function($a, $b) {
            return strtotime($b['time']) - strtotime($a['time']);
        });

        return array_slice($activities, 0, 5);
    }

    /**
     * Obtener datos específicos del dashboard para user - SOLO CAMPOS REALES.
     */
    private function getUserDashboardData($user): array
    {
        return [
            'personalStats' => $this->getPersonalStats($user),
            'personalAlerts' => $this->getPersonalAlerts($user),
            'recentWork' => $this->getRecentWork($user),
        ];
    }

    /**
     * Obtener estadísticas personales del usuario - SOLO CAMPOS REALES.
     */
    private function getPersonalStats($user): array
    {
        if (!$this->isUser()) {
            return [
                'voyages_created' => 0,
                'shipments_processed' => 0,
                'manifests_sent' => 0,
            ];
        }

        $company = $this->getUserCompany();

        // Estadísticas reales del trabajo del operador - SOLO CAMPOS REALES
        $voyagesCreated = Voyage::where('company_id', $company->id)
            ->where('created_by_user_id', $user->id)
            ->count();

        $shipmentsProcessed = Shipment::whereHas('voyage', function($query) use ($company) {
            $query->where('company_id', $company->id);
        })->where('created_by_user_id', $user->id)->count();

        $manifestsSent = WebserviceTransaction::where('company_id', $company->id)
            ->where('user_id', $user->id)
            ->where('status', 'success')
            ->count();

        return [
            'voyages_created' => $voyagesCreated,
            'shipments_processed' => $shipmentsProcessed,
            'manifests_sent' => $manifestsSent,
        ];
    }

    /**
     * Obtener alertas personales del usuario - DATOS REALES.
     */
    private function getPersonalAlerts($user): array
    {
        $alerts = [];
        
        if (!$this->isUser()) {
            return $alerts;
        }

        $operator = $this->getUserOperator();
        
        // Verificar si el operador tiene pocos permisos
        $permissions = 0;
        if ($operator->can_import) $permissions++;
        if ($operator->can_export) $permissions++;
        if ($operator->can_transfer) $permissions++;

        if ($permissions <= 1) {
            $alerts[] = [
                'message' => 'Tiene permisos limitados. Contacte al administrador para ampliar sus capacidades.',
                'type' => 'warning'
            ];
        }

        // Verificar si no ha tenido actividad reciente
        if ($user->last_access && $user->last_access->lt(now()->subDays(7))) {
            $alerts[] = [
                'message' => 'No ha tenido actividad reciente. Revise las tareas pendientes.',
                'type' => 'info'
            ];
        }

        return $alerts;
    }

    /**
     * Obtener trabajo reciente del usuario - SOLO CAMPOS REALES.
     */
    private function getRecentWork($user): array
    {
        $work = [];
        
        if (!$this->isUser()) {
            return $work;
        }

        $company = $this->getUserCompany();

        // Viajes recientes creados por el usuario - SOLO CAMPOS REALES
        $recentVoyages = Voyage::where('company_id', $company->id)
            ->where('created_by_user_id', $user->id)
            ->latest()
            ->take(3)
            ->get(['voyage_number', 'internal_reference', 'created_at']);

        foreach ($recentVoyages as $voyage) {
            $work[] = [
                'title' => "Viaje: {$voyage->voyage_number}",
                'description' => $voyage->internal_reference ? "Ref: {$voyage->internal_reference}" : "Viaje registrado",
                'time' => $voyage->created_at->diffForHumans(),
                'type' => 'voyage'
            ];
        }

        // Transacciones webservice recientes - SOLO CAMPOS REALES
        $recentTransactions = WebserviceTransaction::where('company_id', $company->id)
            ->where('user_id', $user->id)
            ->latest()
            ->take(2)
            ->get(['webservice_type', 'status', 'created_at']);

        foreach ($recentTransactions as $transaction) {
            $work[] = [
                'title' => "Webservice: {$transaction->webservice_type}",
                'description' => "Estado: {$transaction->status}",
                'time' => $transaction->created_at->diffForHumans(),
                'type' => 'webservice'
            ];
        }

        return array_slice($work, 0, 5);
    }

    /**
     * Obtener estado de certificados - DATOS REALES.
     */
    private function getCertificateStatus(Company $company): array
    {
        // Los certificados se manejan directamente en el modelo Company
        $hasCertificate = !empty($company->certificate_path);
        $isValid = $hasCertificate && $company->certificate_expires_at && $company->certificate_expires_at > now();

        return [
            'afip' => [
                'exists' => $hasCertificate,
                'valid' => $isValid,
                'expires_at' => $company->certificate_expires_at,
            ],
            'dna' => [
                'exists' => $hasCertificate, // Mismo certificado para ambos servicios
                'valid' => $isValid,
                'expires_at' => $company->certificate_expires_at,
            ]
        ];
    }

    /**
     * Obtener estado de webservices - DATOS REALES.
     */
    private function getWebserviceStatus(Company $company): array
    {
        return [
            'active' => $company->ws_active,
            'anticipada' => $company->ws_anticipada,
            'micdta' => $company->ws_micdta,
            'desconsolidados' => $company->ws_desconsolidados,
            'transbordos' => $company->ws_transbordos,
        ];
    }

    /**
     * Obtener alertas de la empresa - DATOS REALES.
     */
    private function getCompanyAlerts(Company $company): array
    {
        $alerts = [];

        // Verificar certificados próximos a vencer
        $certificateStatus = $this->getCertificateStatus($company);
        
        if ($certificateStatus['afip']['exists'] && $certificateStatus['afip']['expires_at'] < now()->addDays(30)) {
            $alerts[] = [
                'title' => 'Certificado AFIP próximo a vencer',
                'message' => 'El certificado AFIP vence el ' . $certificateStatus['afip']['expires_at']->format('d/m/Y'),
                'type' => 'warning'
            ];
        }

        if ($certificateStatus['dna']['exists'] && $certificateStatus['dna']['expires_at'] < now()->addDays(30)) {
            $alerts[] = [
                'title' => 'Certificado DNA próximo a vencer',
                'message' => 'El certificado DNA vence el ' . $certificateStatus['dna']['expires_at']->format('d/m/Y'),
                'type' => 'warning'
            ];
        }

        // Verificar si no hay operadores activos
        if ($company->operators()->where('active', true)->count() === 0) {
            $alerts[] = [
                'title' => 'Sin operadores activos',
                'message' => 'La empresa no tiene operadores activos. Cree al menos uno para operar.',
                'type' => 'error'
            ];
        }

        // Verificar webservices inactivos
        if (!$company->ws_active) {
            $alerts[] = [
                'title' => 'Webservices desactivados',
                'message' => 'Los webservices están desactivados. Configure certificados para activarlos.',
                'type' => 'warning'
            ];
        }

        return $alerts;
    }

    /**
     * Obtener permisos específicos para mostrar/ocultar elementos de la interfaz.
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
}