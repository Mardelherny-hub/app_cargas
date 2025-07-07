<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Operator;
use App\Models\User;
use App\Traits\UserHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    use UserHelper;

    /**
     * Mostrar el dashboard del Operador Interno.
     */
    public function index()
    {
        $user = Auth::user();

        // Estadísticas generales del sistema
        $stats = [
            'total_companies' => Company::count(),
            'active_companies' => Company::where('active', true)->count(),
            'companies_with_certificates' => Company::whereNotNull('certificate_path')->count(),
            'companies_with_active_ws' => Company::where('ws_active', true)->count(),
            'total_external_operators' => Operator::where('type', 'external')->count(),
            'active_external_operators' => Operator::where('type', 'external')->where('active', true)->count(),
            'recent_activity' => User::where('last_access', '>=', Carbon::now()->subDays(7))->count(),
            'system_health' => 'good', // TODO: Implementar sistema de salud
        ];

        // Empresas que requieren atención
        $companiesNeedingAttention = $this->getCompaniesNeedingAttention();

        // Actividad reciente del sistema
        $recentActivity = $this->getSystemActivity();

        // Alertas críticas
        $criticalAlerts = $this->getCriticalAlerts();

        // Estadísticas por país
        $companiesByCountry = Company::select('country', DB::raw('count(*) as total'))
        ->groupBy('country')
        ->get()
        ->keyBy('country')
        ->toArray();

        // Empresas más activas
        $activeCompanies = Company::withCount(['operators' => function ($query) {
            $query->whereHas('user', function ($q) {
                $q->where('last_access', '>=', Carbon::now()->subDays(30));
            });
        }])
        ->orderBy('operators_count', 'desc')
        ->take(10)
        ->get();

        // Webservices con problemas
        $webserviceIssues = $this->getWebserviceIssues();

        return view('internal.dashboard', compact(
            'stats',
            'companiesNeedingAttention',
            'recentActivity',
            'criticalAlerts',
            'companiesByCountry',
            'activeCompanies',
            'webserviceIssues'
        ));
    }

    /**
     * Obtener empresas que requieren atención.
     */
    private function getCompaniesNeedingAttention()
    {
        $companies = [];

        // Empresas con certificados vencidos
        $expiredCertificates = Company::whereNotNull('certificate_expires_at')
        ->where('certificate_expires_at', '<', Carbon::now())
        ->where('active', true)
        ->take(5)
        ->get();

        foreach ($expiredCertificates as $company) {
            $companies[] = [
                'company' => $company,
                'issue' => 'Certificado vencido',
                'priority' => 'high',
                'days_overdue' => Carbon::now()->diffInDays($company->certificate_expires_at),
            ];
        }

        // Empresas con certificados próximos a vencer
        $expiringSoon = Company::whereNotNull('certificate_expires_at')
        ->where('certificate_expires_at', '>=', Carbon::now())
        ->where('certificate_expires_at', '<=', Carbon::now()->addDays(30))
        ->where('active', true)
        ->take(5)
        ->get();

        foreach ($expiringSoon as $company) {
            $companies[] = [
                'company' => $company,
                'issue' => 'Certificado próximo a vencer',
                'priority' => 'medium',
                'days_to_expiry' => Carbon::now()->diffInDays($company->certificate_expires_at),
            ];
        }

        // Empresas activas sin certificado
        $withoutCertificate = Company::where('active', true)
        ->whereNull('certificate_path')
        ->take(5)
        ->get();

        foreach ($withoutCertificate as $company) {
            $companies[] = [
                'company' => $company,
                'issue' => 'Sin certificado digital',
                'priority' => 'medium',
                'days_since_creation' => Carbon::now()->diffInDays($company->created_at),
            ];
        }

        // Empresas con operadores inactivos
        $inactiveOperators = Company::whereHas('operators', function ($query) {
            $query->where('active', false);
        })
        ->withCount(['operators' => function ($query) {
            $query->where('active', false);
        }])
        ->having('operators_count', '>', 0)
        ->take(5)
        ->get();

        foreach ($inactiveOperators as $company) {
            $companies[] = [
                'company' => $company,
                'issue' => $company->operators_count . ' operador(es) inactivo(s)',
                'priority' => 'low',
                'inactive_count' => $company->operators_count,
            ];
        }

        // Ordenar por prioridad
        $priorityOrder = ['high' => 1, 'medium' => 2, 'low' => 3];
        usort($companies, function ($a, $b) use ($priorityOrder) {
            return $priorityOrder[$a['priority']] <=> $priorityOrder[$b['priority']];
        });

        return array_slice($companies, 0, 10);
    }

    /**
     * Obtener actividad reciente del sistema.
     */
    private function getSystemActivity()
    {
        $activities = [];

        // Nuevas empresas
        $newCompanies = Company::where('created_at', '>=', Carbon::now()->subDays(7))
        ->latest()
        ->take(3)
        ->get();

        foreach ($newCompanies as $company) {
            $activities[] = [
                'type' => 'company_created',
                'message' => 'Nueva empresa registrada: ' . $company->business_name,
                'date' => $company->created_at,
                'icon' => 'building',
                'color' => 'green',
                'link' => route('internal.companies.show', $company),
            ];
        }

        // Nuevos operadores
        $newOperators = Operator::where('type', 'external')
        ->where('created_at', '>=', Carbon::now()->subDays(7))
        ->with('company')
        ->latest()
        ->take(3)
        ->get();

        foreach ($newOperators as $operator) {
            $activities[] = [
                'type' => 'operator_created',
                'message' => 'Nuevo operador: ' . $operator->first_name . ' ' . $operator->last_name . ' (' . $operator->company->business_name . ')',
                'date' => $operator->created_at,
                'icon' => 'user-plus',
                'color' => 'blue',
                'link' => route('internal.companies.operators', $operator->company),
            ];
        }

        // Accesos recientes
        $recentAccesses = User::where('last_access', '>=', Carbon::now()->subHours(24))
        ->with('roles')
        ->latest('last_access')
        ->take(5)
        ->get();

        foreach ($recentAccesses as $user) {
            $activities[] = [
                'type' => 'user_access',
                'message' => 'Acceso: ' . $user->name . ' (' . $user->roles->first()?->name . ')',
                'date' => $user->last_access,
                'icon' => 'login',
                'color' => 'gray',
            ];
        }

        // Ordenar por fecha
        usort($activities, function ($a, $b) {
            return $b['date'] <=> $a['date'];
        });

        return array_slice($activities, 0, 15);
    }

    /**
     * Obtener alertas críticas del sistema.
     */
    private function getCriticalAlerts()
    {
        $alerts = [];

        // Certificados vencidos
        $expiredCertificates = Company::whereNotNull('certificate_expires_at')
        ->where('certificate_expires_at', '<', Carbon::now())
        ->where('active', true)
        ->count();

        if ($expiredCertificates > 0) {
            $alerts[] = [
                'type' => 'danger',
                'message' => "{$expiredCertificates} empresa(s) con certificados vencidos",
                'action' => route('internal.companies.index', ['filter' => 'expired_certificates']),
                'priority' => 'high',
            ];
        }

        // Empresas activas sin certificado
        $withoutCertificate = Company::where('active', true)
        ->whereNull('certificate_path')
        ->count();

        if ($withoutCertificate > 0) {
            $alerts[] = [
                'type' => 'warning',
                'message' => "{$withoutCertificate} empresa(s) sin certificado digital",
                'action' => route('internal.companies.index', ['filter' => 'without_certificate']),
                'priority' => 'medium',
            ];
        }

        // Usuarios sin acceso reciente
        $inactiveUsers = User::where('active', true)
        ->where(function ($query) {
            $query->whereNull('last_access')
            ->orWhere('last_access', '<', Carbon::now()->subDays(90));
        })
        ->count();

        if ($inactiveUsers > 0) {
            $alerts[] = [
                'type' => 'info',
                'message' => "{$inactiveUsers} usuario(s) sin acceso reciente (90+ días)",
                'action' => route('internal.monitoring.companies'),
                'priority' => 'low',
            ];
        }

        // Empresas con webservices inactivos
        $inactiveWebservices = Company::where('active', true)
        ->where('ws_active', false)
        ->count();

        if ($inactiveWebservices > 0) {
            $alerts[] = [
                'type' => 'warning',
                'message' => "{$inactiveWebservices} empresa(s) con webservices inactivos",
                'action' => route('internal.webservices.index'),
                'priority' => 'medium',
            ];
        }

        return $alerts;
    }

    /**
     * Obtener problemas de webservices.
     */
    private function getWebserviceIssues()
    {
        // TODO: Implementar cuando esté el módulo de webservices
        return [
            'total_companies' => Company::where('ws_active', true)->count(),
            'with_issues' => 0,
            'last_24h_errors' => 0,
            'pending_sends' => 0,
            'failed_sends' => 0,
        ];
    }

    /**
     * Herramientas del sistema.
     */
    public function tools()
    {
        return view('internal.tools');
    }
}
