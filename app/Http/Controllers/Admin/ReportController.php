<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Company;
use App\Models\Operator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;
use Carbon\Carbon;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;

class ReportController extends Controller
{
    /**
     * Vista principal de reportes del sistema.
     */
    public function index()
    {
        // Estadísticas generales para la vista principal
        $stats = [
            'total_users' => User::count(),
            'active_users' => User::where('active', true)->count(),
            'total_companies' => Company::count(),
            'active_companies' => Company::where('active', true)->count(),
            'total_operators' => Operator::count(),
            'recent_activity' => User::whereNotNull('last_access')
                ->where('last_access', '>=', Carbon::now()->subDays(7))
                ->count(),
        ];

        // Distribución de usuarios por rol
        $usersByRole = User::join('model_has_roles', 'users.id', '=', 'model_has_roles.model_id')
            ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
            ->select('roles.name as role', DB::raw('count(*) as count'))
            ->groupBy('roles.name')
            ->get();

        // Empresas por país
        $companiesByCountry = Company::select('country', DB::raw('count(*) as count'))
            ->groupBy('country')
            ->get();

        // Actividad reciente (últimos 7 días)
        $recentActivity = User::whereNotNull('last_access')
            ->where('last_access', '>=', Carbon::now()->subDays(7))
            ->selectRaw('DATE(last_access) as date, COUNT(*) as logins')
            ->groupBy('date')
            ->orderBy('date', 'desc')
            ->get();

        // Certificados próximos a vencer
        $expiringCertificates = Company::whereNotNull('certificate_expires_at')
            ->where('certificate_expires_at', '<=', Carbon::now()->addDays(30))
            ->where('certificate_expires_at', '>=', Carbon::now())
            ->with(['operators' => function($query) {
                $query->where('active', true);
            }])
            ->get();

        return view('admin.reports.index', compact(
            'stats',
            'usersByRole',
            'companiesByCountry',
            'recentActivity',
            'expiringCertificates'
        ));
    }

    /**
     * Reporte detallado de usuarios.
     */
    public function users(Request $request)
    {
        $query = User::with(['roles', 'userable']);

        // Filtros
        if ($request->filled('role')) {
            $query->whereHas('roles', function($q) use ($request) {
                $q->where('name', $request->role);
            });
        }

        if ($request->filled('status')) {
            $active = $request->status === 'active';
            $query->where('active', $active);
        }

        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->where('created_at', '<=', $request->date_to . ' 23:59:59');
        }

        $users = $query->orderBy('created_at', 'desc')->paginate(20);

        // Estadísticas para el reporte
        $stats = [
            'total' => User::count(),
            'active' => User::where('active', true)->count(),
            'inactive' => User::where('active', false)->count(),
            'recent_logins' => User::whereNotNull('last_access')
                ->where('last_access', '>=', Carbon::now()->subDays(7))
                ->count(),
        ];

        // Distribución por roles
        $roleDistribution = User::join('model_has_roles', 'users.id', '=', 'model_has_roles.model_id')
            ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
            ->select('roles.name as role', DB::raw('count(*) as count'))
            ->groupBy('roles.name')
            ->get();

        return view('admin.reports.users', compact('users', 'stats', 'roleDistribution'));
    }

    /**
     * Reporte detallado de empresas.
     */
    public function companies(Request $request)
    {
        $query = Company::with(['operators' => function($q) {
            $q->where('active', true);
        }]);

        // Filtros
        if ($request->filled('country')) {
            $query->where('country', $request->country);
        }

        if ($request->filled('status')) {
            $active = $request->status === 'active';
            $query->where('active', $active);
        }

        if ($request->filled('certificate_status')) {
            if ($request->certificate_status === 'with_certificate') {
                $query->whereNotNull('certificate_path');
            } elseif ($request->certificate_status === 'without_certificate') {
                $query->whereNull('certificate_path');
            } elseif ($request->certificate_status === 'expiring') {
                $query->whereNotNull('certificate_expires_at')
                    ->where('certificate_expires_at', '<=', Carbon::now()->addDays(30))
                    ->where('certificate_expires_at', '>=', Carbon::now());
            }
        }

        if ($request->filled('ws_status')) {
            $wsActive = $request->ws_status === 'active';
            $query->where('ws_active', $wsActive);
        }

        $companies = $query->orderBy('created_date', 'desc')->paginate(15);

        // Estadísticas
        $stats = [
            'total' => Company::count(),
            'active' => Company::where('active', true)->count(),
            'with_certificates' => Company::whereNotNull('certificate_path')->count(),
            'ws_active' => Company::where('ws_active', true)->count(),
            'argentina' => Company::where('country', 'AR')->count(),
            'paraguay' => Company::where('country', 'PY')->count(),
        ];

        return view('admin.reports.companies', compact('companies', 'stats'));
    }

    /**
     * Estadísticas del sistema.
     */
    public function systemStats()
    {
        // Estadísticas generales
        $generalStats = [
            'total_users' => User::count(),
            'active_users' => User::where('active', true)->count(),
            'total_companies' => Company::count(),
            'active_companies' => Company::where('active', true)->count(),
            'total_operators' => Operator::count(),
            'active_operators' => Operator::where('active', true)->count(),
        ];

        // Crecimiento mensual de usuarios (últimos 12 meses)
        $userGrowth = User::selectRaw('YEAR(created_at) as year, MONTH(created_at) as month, COUNT(*) as count')
            ->where('created_at', '>=', Carbon::now()->subMonths(12))
            ->groupBy('year', 'month')
            ->orderBy('year', 'asc')
            ->orderBy('month', 'asc')
            ->get();

        // Crecimiento de empresas
        $companyGrowth = Company::selectRaw('YEAR(created_date) as year, MONTH(created_date) as month, COUNT(*) as count')
            ->where('created_date', '>=', Carbon::now()->subMonths(12))
            ->groupBy('year', 'month')
            ->orderBy('year', 'asc')
            ->orderBy('month', 'asc')
            ->get();

        // Actividad de logins (últimos 30 días)
        $loginActivity = User::whereNotNull('last_access')
            ->where('last_access', '>=', Carbon::now()->subDays(30))
            ->selectRaw('DATE(last_access) as date, COUNT(*) as logins')
            ->groupBy('date')
            ->orderBy('date', 'asc')
            ->get();

        // Distribución de operadores
        $operatorDistribution = [
            'internal' => Operator::where('type', 'internal')->count(),
            'external' => Operator::where('type', 'external')->count(),
            'with_import_permission' => Operator::where('can_import', true)->count(),
            'with_export_permission' => Operator::where('can_export', true)->count(),
            'with_transfer_permission' => Operator::where('can_transfer', true)->count(),
        ];

        // Estado de certificados
        $certificateStats = [
            'total_with_certificate' => Company::whereNotNull('certificate_path')->count(),
            'expiring_soon' => Company::whereNotNull('certificate_expires_at')
                ->where('certificate_expires_at', '<=', Carbon::now()->addDays(30))
                ->where('certificate_expires_at', '>=', Carbon::now())
                ->count(),
            'expired' => Company::whereNotNull('certificate_expires_at')
                ->where('certificate_expires_at', '<', Carbon::now())
                ->count(),
        ];

        return view('admin.reports.system-stats', compact(
            'generalStats',
            'userGrowth',
            'companyGrowth',
            'loginActivity',
            'operatorDistribution',
            'certificateStats'
        ));
    }

    /**
     * Reporte de actividad del sistema.
     */
    public function activity(Request $request)
    {
        $days = $request->get('days', 30);

        // Actividad de logins
        $loginActivity = User::whereNotNull('last_access')
            ->where('last_access', '>=', Carbon::now()->subDays($days))
            ->selectRaw('DATE(last_access) as date, COUNT(*) as logins')
            ->groupBy('date')
            ->orderBy('date', 'desc')
            ->get();

        // Usuarios más activos
        $activeUsers = User::with('roles')
            ->whereNotNull('last_access')
            ->where('last_access', '>=', Carbon::now()->subDays($days))
            ->orderBy('last_access', 'desc')
            ->take(10)
            ->get();

        // Nuevos registros por día
        $newRegistrations = User::where('created_at', '>=', Carbon::now()->subDays($days))
            ->selectRaw('DATE(created_at) as date, COUNT(*) as registrations')
            ->groupBy('date')
            ->orderBy('date', 'desc')
            ->get();

        // Empresas más activas (basado en último acceso de sus usuarios)
        $activeCompanies = Company::with(['operators.user'])
            ->whereHas('operators.user', function($query) use ($days) {
                $query->whereNotNull('last_access')
                    ->where('last_access', '>=', Carbon::now()->subDays($days));
            })
            ->get()
            ->map(function($company) use ($days) {
                $lastActivity = $company->operators
                    ->pluck('user')
                    ->filter()
                    ->where('last_access', '>=', Carbon::now()->subDays($days))
                    ->max('last_access');

                $company->last_activity = $lastActivity;
                return $company;
            })
            ->sortByDesc('last_activity')
            ->take(10);

        return view('admin.reports.activity', compact(
            'loginActivity',
            'activeUsers',
            'newRegistrations',
            'activeCompanies',
            'days'
        ));
    }

    /**
     * Exportar reporte de usuarios a Excel.
     */
    public function exportUsers(Request $request)
    {
        $users = User::with(['roles', 'userable'])->get();

        $data = $users->map(function($user) {
            return [
                'ID' => $user->id,
                'Nombre' => $user->name,
                'Email' => $user->email,
                'Rol' => $user->roles->pluck('name')->join(', '),
                'Tipo' => $user->userable_type ? class_basename($user->userable_type) : 'N/A',
                'Activo' => $user->active ? 'Sí' : 'No',
                'Último Acceso' => $user->last_access ? $user->last_access->format('d/m/Y H:i') : 'Nunca',
                'Fecha Registro' => $user->created_at->format('d/m/Y H:i'),
                'Zona Horaria' => $user->timezone ?? 'UTC',
            ];
        });

        $filename = 'reporte_usuarios_' . Carbon::now()->format('Y-m-d_H-i-s') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function() use ($data) {
            $file = fopen('php://output', 'w');

            // BOM para UTF-8
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            // Headers
            fputcsv($file, array_keys($data->first()));

            // Data
            foreach ($data as $row) {
                fputcsv($file, $row);
            }

            fclose($file);
        };

        return Response::stream($callback, 200, $headers);
    }

    /**
     * Exportar reporte de empresas a Excel.
     */
    public function exportCompanies(Request $request)
    {
        $companies = Company::with('operators')->get();

        $data = $companies->map(function($company) {
            return [
                'ID' => $company->id,
                'Razón Social' => $company->business_name,
                'Nombre Comercial' => $company->commercial_name,
                'CUIT' => $company->tax_id,
                'País' => $company->country === 'AR' ? 'Argentina' : 'Paraguay',
                'Email' => $company->email,
                'Teléfono' => $company->phone,
                'Ciudad' => $company->city,
                'Dirección' => $company->address,
                'Certificado' => $company->certificate_path ? 'Sí' : 'No',
                'Certificado Vence' => $company->certificate_expires_at ?
                    $company->certificate_expires_at->format('d/m/Y') : 'N/A',
                'WebServices Activo' => $company->ws_active ? 'Sí' : 'No',
                'Entorno WS' => $company->ws_environment ?? 'N/A',
                'Operadores' => $company->operators->count(),
                'Activa' => $company->active ? 'Sí' : 'No',
                'Fecha Registro' => $company->created_date ?
                    Carbon::parse($company->created_date)->format('d/m/Y') : 'N/A',
                'Último Acceso' => $company->last_access ?
                    Carbon::parse($company->last_access)->format('d/m/Y H:i') : 'Nunca',
            ];
        });

        $filename = 'reporte_empresas_' . Carbon::now()->format('Y-m-d_H-i-s') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function() use ($data) {
            $file = fopen('php://output', 'w');

            // BOM para UTF-8
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            // Headers
            fputcsv($file, array_keys($data->first()));

            // Data
            foreach ($data as $row) {
                fputcsv($file, $row);
            }

            fclose($file);
        };

        return Response::stream($callback, 200, $headers);
    }

    /**
     * Exportar reporte de actividad a Excel.
     */
    public function exportActivity(Request $request)
    {
        $days = $request->get('days', 30);

        $users = User::with('roles')
            ->whereNotNull('last_access')
            ->where('last_access', '>=', Carbon::now()->subDays($days))
            ->orderBy('last_access', 'desc')
            ->get();

        $data = $users->map(function($user) {
            return [
                'ID' => $user->id,
                'Nombre' => $user->name,
                'Email' => $user->email,
                'Rol' => $user->roles->pluck('name')->join(', '),
                'Último Acceso' => $user->last_access->format('d/m/Y H:i'),
                'Días desde último acceso' => Carbon::now()->diffInDays($user->last_access),
                'Activo' => $user->active ? 'Sí' : 'No',
            ];
        });

        $filename = 'reporte_actividad_' . $days . '_dias_' . Carbon::now()->format('Y-m-d_H-i-s') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function() use ($data) {
            $file = fopen('php://output', 'w');

            // BOM para UTF-8
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            // Headers
            if ($data->isNotEmpty()) {
                fputcsv($file, array_keys($data->first()));

                // Data
                foreach ($data as $row) {
                    fputcsv($file, $row);
                }
            }

            fclose($file);
        };

        return Response::stream($callback, 200, $headers);
    }
}
