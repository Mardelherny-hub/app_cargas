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
        // Estadísticas generales
        $stats = [
            'total_users' => User::count(),
            'active_users' => User::where('active', true)->count(),
            'total_companies' => Company::count(),
            'active_companies' => Company::where('active', true)->count(),
            'total_operators' => Operator::count(),
            'active_operators' => Operator::where('active', true)->count(),
            'recent_logins' => User::whereNotNull('last_access')
            ->where('last_access', '>=', Carbon::now()->subDays(7))
            ->count(),
        ];

        // Distribución de usuarios por rol
        $usersByRole = User::join('model_has_roles', 'users.id', '=', 'model_has_roles.model_id')
        ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
        ->select('roles.name as role', DB::raw('count(*) as count'))
        ->groupBy('roles.name')
        ->get();

        // Empresas recientes
        $recentCompanies = Company::latest()
        ->take(5)
        ->get();

        // Usuarios recientes
        $recentUsers = User::with('roles')
        ->latest()
        ->take(5)
        ->get();

        // Certificados próximos a vencer
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

        // Empresas por país
        $companiesByCountry = Company::select('country', DB::raw('count(*) as count'))
        ->groupBy('country')
        ->get();

        // Alertas del sistema
        $systemAlerts = $this->getSystemAlerts();

        return view('admin.dashboard', compact(
            'stats',
            'usersByRole',
            'recentCompanies',
            'recentUsers',
            'expiringCertificates',
            'systemActivity',
            'companiesByCountry',
            'systemAlerts'
        ));
    }

    /**
     * Obtener alertas del sistema.
     */
    private function getSystemAlerts()
    {
        $alerts = [];

        // Usuarios inactivos con empresas activas
        $inactiveUsersWithActiveCompanies = User::where('active', false)
        ->where('userable_type', 'App\\Models\\Company')
        ->whereHas('userable', function ($query) {
            $query->where('active', true);
        })
        ->count();

        if ($inactiveUsersWithActiveCompanies > 0) {
            $alerts[] = [
                'type' => 'warning',
                'message' => "{$inactiveUsersWithActiveCompanies} usuario(s) inactivo(s) con empresas activas",
                'action' => route('admin.users.index', ['filter' => 'inactive_with_active_companies'])
            ];
        }

        // Certificados vencidos
        $expiredCertificates = Company::whereNotNull('certificate_expires_at')
        ->where('certificate_expires_at', '<', Carbon::now())
        ->where('active', true)
        ->count();

        if ($expiredCertificates > 0) {
            $alerts[] = [
                'type' => 'danger',
                'message' => "{$expiredCertificates} certificado(s) vencido(s)",
                'action' => route('admin.companies.index', ['filter' => 'expired_certificates'])
            ];
        }

        // Empresas sin certificados
        $companiesWithoutCertificates = Company::where('active', true)
        ->whereNull('certificate_path')
        ->count();

        if ($companiesWithoutCertificates > 0) {
            $alerts[] = [
                'type' => 'warning',
                'message' => "{$companiesWithoutCertificates} empresa(s) sin certificados",
                'action' => route('admin.companies.index', ['filter' => 'without_certificates'])
            ];
        }

        // Usuarios sin acceso reciente
        $usersWithoutRecentAccess = User::where('active', true)
        ->where(function ($query) {
            $query->whereNull('last_access')
            ->orWhere('last_access', '<', Carbon::now()->subDays(90));
        })
        ->count();

        if ($usersWithoutRecentAccess > 0) {
            $alerts[] = [
                'type' => 'info',
                'message' => "{$usersWithoutRecentAccess} usuario(s) sin acceso reciente (90+ días)",
                'action' => route('admin.users.index', ['filter' => 'inactive_access'])
            ];
        }

        return $alerts;
    }

    /**
     * Configuración del sistema.
     */
    public function settings()
    {
        return view('admin.settings');
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
