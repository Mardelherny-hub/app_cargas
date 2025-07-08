<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Company;
use App\Models\Operator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    /**
     * Mostrar el dashboard del Super Administrador.
     */
    public function index()
    {
        // Estadísticas generales actualizadas según Roberto
        $stats = [
            'total_users' => User::count(),
            'active_users' => User::where('active', true)->count(),
            'total_companies' => Company::count(),
            'active_companies' => Company::where('active', true)->count(),
            'companies_with_roles' => Company::where('active', true)
                                         ->whereJsonLength('company_roles', '>', 0)
                                         ->count(),
            'recent_logins' => User::whereNotNull('last_access')
                                 ->where('last_access', '>=', Carbon::now()->subDays(7))
                                 ->count(),
            'properly_configured_users' => $this->getProperlyConfiguredUsersCount(),
        ];

        // Distribución de usuarios por rol (simplificados según Roberto)
        $usersByRole = User::join('model_has_roles', 'users.id', '=', 'model_has_roles.model_id')
                          ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
                          ->select('roles.name as role', DB::raw('count(*) as count'))
                          ->groupBy('roles.name')
                          ->get();

        // NUEVO: Distribución de empresas por roles de negocio (Roberto's key requirement)
        $companiesByBusinessRole = $this->getCompaniesByBusinessRole();

        // NUEVO: Estadísticas de webservices disponibles por empresa
        $webserviceStats = $this->getWebserviceStats();

        // Empresas recientes con sus roles
        $recentCompanies = Company::with([])
                                 ->latest()
                                 ->take(5)
                                 ->get();

        // Usuarios recientes con información de empresa
        $recentUsers = User::with('roles')
                          ->latest()
                          ->take(5)
                          ->get();

        // Certificados próximos a vencer (crítico para webservices)
        $expiringCertificates = Company::whereNotNull('certificate_expires_at')
                                      ->where('certificate_expires_at', '<=', Carbon::now()->addDays(30))
                                      ->where('certificate_expires_at', '>=', Carbon::now())
                                      ->get();

        // Actividad del sistema (últimos 30 días)
        $systemActivity = User::where('last_access', '>=', Carbon::now()->subDays(30))
                             ->selectRaw('DATE(last_access) as date, COUNT(*) as logins')
                             ->groupBy('date')
                             ->orderBy('date')
                             ->get();

        // Empresas por país con estadísticas de roles
        $companiesByCountry = $this->getCompaniesByCountryWithRoles();

        // NUEVO: Alertas específicas para la nueva estructura (Roberto's requirements)
        $systemAlerts = $this->getSystemAlerts();

        // NUEVO: Empresas que necesitan configuración
        $companiesNeedingSetup = $this->getCompaniesNeedingSetup();

        return view('admin.dashboard', compact(
            'stats',
            'usersByRole',
            'companiesByBusinessRole',      // NUEVO
            'webserviceStats',              // NUEVO
            'recentCompanies',
            'recentUsers',
            'expiringCertificates',
            'systemActivity',
            'companiesByCountry',
            'systemAlerts',
            'companiesNeedingSetup'         // NUEVO
        ));
    }

    /**
     * NUEVO: Obtener distribución de empresas por roles de negocio (Roberto's requirement).
     */
    private function getCompaniesByBusinessRole(): array
    {
        $companies = Company::where('active', true)->get();

        $roleStats = [
            'Cargas' => 0,
            'Desconsolidador' => 0,
            'Transbordos' => 0,
            'Multiples' => 0,  // Empresas con más de un rol
            'Sin_roles' => 0,
        ];

        foreach ($companies as $company) {
            $roles = $company->getRoles();

            if (empty($roles)) {
                $roleStats['Sin_roles']++;
                continue;
            }

            if (count($roles) > 1) {
                $roleStats['Multiples']++;
                continue;
            }

            $role = $roles[0];
            if (isset($roleStats[$role])) {
                $roleStats[$role]++;
            }
        }

        return $roleStats;
    }

    /**
     * NUEVO: Obtener estadísticas de webservices (Roberto's operations).
     */
    private function getWebserviceStats(): array
    {
        $companies = Company::where('active', true)->get();

        $webserviceStats = [
            'anticipada' => 0,
            'micdta' => 0,
            'desconsolidados' => 0,
            'transbordos' => 0,
            'multiple_ws' => 0,  // Empresas con múltiples webservices
        ];

        foreach ($companies as $company) {
            $webservices = $company->getAvailableWebservices();

            if (count($webservices) > 1) {
                $webserviceStats['multiple_ws']++;
            }

            foreach ($webservices as $ws) {
                if (isset($webserviceStats[$ws])) {
                    $webserviceStats[$ws]++;
                }
            }
        }

        return $webserviceStats;
    }

    /**
     * NUEVO: Obtener empresas por país con información de roles.
     */
    private function getCompaniesByCountryWithRoles(): array
    {
        $companiesByCountry = Company::select('country', DB::raw('count(*) as count'))
                                   ->groupBy('country')
                                   ->get();

        $result = [];
        foreach ($companiesByCountry as $countryData) {
            $country = $countryData->country;
            $companies = Company::where('country', $country)->where('active', true)->get();

            $rolesInCountry = [];
            foreach ($companies as $company) {
                $roles = $company->getRoles();
                $rolesInCountry = array_merge($rolesInCountry, $roles);
            }

            $result[] = [
                'country' => $country,
                'count' => $countryData->count,
                'active_count' => $companies->count(),
                'unique_roles' => array_unique($rolesInCountry),
            ];
        }

        return $result;
    }

    /**
     * NUEVO: Obtener usuarios correctamente configurados.
     */
    private function getProperlyConfiguredUsersCount(): int
    {
        $users = User::where('active', true)->get();
        $properlyConfigured = 0;

        foreach ($users as $user) {
            if ($user->isProperlyConfigured()) {
                $properlyConfigured++;
            }
        }

        return $properlyConfigured;
    }

    /**
     * NUEVO: Obtener empresas que necesitan configuración.
     */
    private function getCompaniesNeedingSetup(): array
    {
        $companies = Company::where('active', true)->get();
        $needingSetup = [];

        foreach ($companies as $company) {
            $errors = $company->validateRoleConfiguration();
            if (!empty($errors)) {
                $needingSetup[] = [
                    'company' => $company,
                    'errors' => $errors,
                ];
            }
        }

        return $needingSetup;
    }

    /**
     * Obtener alertas del sistema actualizadas para Roberto's requirements.
     */
    private function getSystemAlerts(): array
    {
        $alerts = [];

        // Alertas críticas de configuración
        $companiesWithoutRoles = Company::where('active', true)
                                       ->where(function($query) {
                                           $query->whereNull('company_roles')
                                                 ->orWhereJsonLength('company_roles', 0);
                                       })
                                       ->count();

        if ($companiesWithoutRoles > 0) {
            $alerts[] = [
                'type' => 'danger',
                'title' => 'Empresas sin roles asignados',
                'message' => "Hay {$companiesWithoutRoles} empresa(s) activa(s) sin roles de negocio asignados.",
                'action' => route('admin.companies.index'),
            ];
        }

        // Alertas de certificados vencidos (crítico para webservices)
        $expiredCertificates = Company::where('active', true)
                                     ->whereNotNull('certificate_expires_at')
                                     ->where('certificate_expires_at', '<', Carbon::now())
                                     ->count();

        if ($expiredCertificates > 0) {
            $alerts[] = [
                'type' => 'danger',
                'title' => 'Certificados vencidos',
                'message' => "Hay {$expiredCertificates} empresa(s) con certificados digitales vencidos.",
                'action' => route('admin.companies.index'),
            ];
        }

        // Alertas de usuarios mal configurados
        $misconfiguredUsers = User::where('active', true)->get()->filter(function($user) {
            return !$user->isProperlyConfigured();
        })->count();

        if ($misconfiguredUsers > 0) {
            $alerts[] = [
                'type' => 'warning',
                'title' => 'Usuarios mal configurados',
                'message' => "Hay {$misconfiguredUsers} usuario(s) con problemas de configuración.",
                'action' => route('admin.users.index'),
            ];
        }

        // Alerta de empresas sin administrador (company-admin)
        $companiesWithoutAdmin = Company::where('active', true)
                                       ->whereDoesntHave('users', function($query) {
                                           $query->whereHas('roles', function($roleQuery) {
                                               $roleQuery->where('name', 'company-admin');
                                           });
                                       })
                                       ->count();

        if ($companiesWithoutAdmin > 0) {
            $alerts[] = [
                'type' => 'warning',
                'title' => 'Empresas sin administrador',
                'message' => "Hay {$companiesWithoutAdmin} empresa(s) sin administrador asignado.",
                'action' => route('admin.companies.index'),
            ];
        }

        // Certificados próximos a vencer (30 días)
        $expiringCertificates = Company::where('active', true)
                                      ->whereNotNull('certificate_expires_at')
                                      ->where('certificate_expires_at', '<=', Carbon::now()->addDays(30))
                                      ->where('certificate_expires_at', '>=', Carbon::now())
                                      ->count();

        if ($expiringCertificates > 0) {
            $alerts[] = [
                'type' => 'warning',
                'title' => 'Certificados por vencer',
                'message' => "Hay {$expiringCertificates} certificado(s) que vencen en los próximos 30 días.",
                'action' => route('admin.companies.index'),
            ];
        }

        // NUEVO: Alerta si hay empresas con webservices inactivos
        $companiesWithInactiveWS = Company::where('active', true)
                                         ->where('ws_active', false)
                                         ->whereJsonLength('company_roles', '>', 0)
                                         ->count();

        if ($companiesWithInactiveWS > 0) {
            $alerts[] = [
                'type' => 'info',
                'title' => 'Webservices inactivos',
                'message' => "Hay {$companiesWithInactiveWS} empresa(s) con webservices desactivados.",
                'action' => route('admin.companies.index'),
            ];
        }

        return $alerts;
    }

    /**
     * Configuración del sistema (mantener compatibilidad).
     */
    public function settings()
    {
        return view('admin.system.settings');
    }

    /**
     * Actualizar configuración del sistema.
     */
    public function updateSettings(Request $request)
    {
        $request->validate([
            'app_name' => 'required|string|max:255',
            'app_description' => 'nullable|string|max:500',
            'maintenance_mode' => 'boolean',
            'max_upload_size' => 'required|integer|min:1|max:100',
            'session_timeout' => 'required|integer|min:15|max:480',
        ]);

        // Actualizar configuraciones
        // Esto se puede implementar con un sistema de configuración personalizado
        // o usando config/cache

        return redirect()->route('admin.settings')
                        ->with('success', 'Configuración actualizada correctamente.');
    }

    /**
     * Herramientas del sistema.
     */
    public function tools()
    {
        return view('admin.tools');
    }

    /**
     * Mantenimiento del sistema.
     */
    public function maintenance()
    {
        return view('admin.maintenance');
    }
}
