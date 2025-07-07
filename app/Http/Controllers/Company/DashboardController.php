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
     * Mostrar el dashboard del Administrador de Empresa.
     */
    public function index()
    {
        $user = $this->getCurrentUser();
        $company = $this->getUserCompany();

        if (!$company) {
            return redirect()->route('dashboard')
            ->with('error', 'No se encontró la empresa asociada a su usuario.');
        }

        // Estadísticas de la empresa
        $stats = [
            'total_operators' => $company->operators()->count(),
            'active_operators' => $company->operators()->where('active', true)->count(),
            'recent_shipments' => 0, // TODO: Implementar cuando esté el módulo de cargas
            'pending_shipments' => 0, // TODO: Implementar cuando esté el módulo de cargas
            'completed_trips' => 0, // TODO: Implementar cuando esté el módulo de viajes
            'active_trips' => 0, // TODO: Implementar cuando esté el módulo de viajes
        ];

        // Operadores recientes
        $recentOperators = $company->operators()
        ->with('user')
        ->latest()
        ->take(5)
        ->get();

        // Estado del certificado
        $certificateStatus = $this->getCertificateStatus($company);

        // Configuración de webservices
        $webserviceStatus = [
            'active' => $company->ws_active,
            'environment' => $company->ws_environment,
            'last_connection' => null, // TODO: Implementar cuando esté el módulo de webservices
            'pending_sends' => 0, // TODO: Implementar cuando esté el módulo de webservices
            'failed_sends' => 0, // TODO: Implementar cuando esté el módulo de webservices
        ];

        // Actividad reciente
        $recentActivity = $this->getRecentActivity($company);

        // Alertas de la empresa
        $companyAlerts = $this->getCompanyAlerts($company);

        return view('company.dashboard', compact(
            'company',
            'stats',
            'recentOperators',
            'certificateStatus',
            'webserviceStatus',
            'recentActivity',
            'companyAlerts'
        ));
    }

    /**
     * Obtener estado del certificado.
     */
    private function getCertificateStatus($company)
    {
        $status = [
            'has_certificate' => !empty($company->certificate_path),
            'expires_at' => $company->certificate_expires_at,
            'is_expired' => false,
            'expires_soon' => false,
            'status' => 'none',
            'message' => 'Sin certificado',
        ];

        if ($company->certificate_expires_at) {
            $expiresAt = Carbon::parse($company->certificate_expires_at);
            $now = Carbon::now();
            $daysToExpiry = $now->diffInDays($expiresAt, false);

            if ($daysToExpiry < 0) {
                $status['is_expired'] = true;
                $status['status'] = 'expired';
                $status['message'] = 'Certificado vencido hace ' . abs($daysToExpiry) . ' días';
            } elseif ($daysToExpiry <= 30) {
                $status['expires_soon'] = true;
                $status['status'] = 'warning';
                $status['message'] = 'Certificado vence en ' . $daysToExpiry . ' días';
            } else {
                $status['status'] = 'valid';
                $status['message'] = 'Certificado válido por ' . $daysToExpiry . ' días';
            }
        }

        return $status;
    }

    /**
     * Obtener actividad reciente de la empresa.
     */
    private function getRecentActivity($company)
    {
        $activities = [];

        // Operadores recientes
        $recentOperators = $company->operators()
        ->with('user')
        ->where('created_at', '>=', Carbon::now()->subDays(30))
        ->latest()
        ->take(3)
        ->get();

        foreach ($recentOperators as $operator) {
            $activities[] = [
                'type' => 'operator_created',
                'message' => 'Operador creado: ' . $operator->first_name . ' ' . $operator->last_name,
                'date' => $operator->created_at,
                'icon' => 'user-plus',
                'color' => 'blue',
            ];
        }

        // Accesos recientes de operadores
        $recentAccesses = $company->operators()
        ->with('user')
        ->whereHas('user', function ($query) {
            $query->where('last_access', '>=', Carbon::now()->subDays(7));
        })
        ->get();

        foreach ($recentAccesses as $operator) {
            if ($operator->user && $operator->user->last_access) {
                $activities[] = [
                    'type' => 'operator_access',
                    'message' => 'Acceso: ' . $operator->first_name . ' ' . $operator->last_name,
                    'date' => $operator->user->last_access,
                    'icon' => 'login',
                    'color' => 'green',
                ];
            }
        }

        // Ordenar por fecha
        usort($activities, function ($a, $b) {
            return $b['date'] <=> $a['date'];
        });

        return array_slice($activities, 0, 10);
    }

    /**
     * Obtener alertas de la empresa.
     */
    private function getCompanyAlerts($company)
    {
        $alerts = [];

        // Certificado vencido o próximo a vencer
        $certificateStatus = $this->getCertificateStatus($company);
        if ($certificateStatus['is_expired']) {
            $alerts[] = [
                'type' => 'danger',
                'message' => $certificateStatus['message'],
                'action' => route('company.certificates.index')
            ];
        } elseif ($certificateStatus['expires_soon']) {
            $alerts[] = [
                'type' => 'warning',
                'message' => $certificateStatus['message'],
                'action' => route('company.certificates.index')
            ];
        }

        // Sin certificado
        if (!$certificateStatus['has_certificate']) {
            $alerts[] = [
                'type' => 'warning',
                'message' => 'No tiene certificado digital configurado',
                'action' => route('company.certificates.index')
            ];
        }

        // Operadores inactivos
        $inactiveOperators = $company->operators()->where('active', false)->count();
        if ($inactiveOperators > 0) {
            $alerts[] = [
                'type' => 'info',
                'message' => "{$inactiveOperators} operador(es) inactivo(s)",
                'action' => route('company.operators.index', ['filter' => 'inactive'])
            ];
        }

        // Webservices inactivos
        if (!$company->ws_active) {
            $alerts[] = [
                'type' => 'warning',
                'message' => 'Webservices desactivados',
                'action' => route('company.webservices.index')
            ];
        }

        return $alerts;
    }

    /**
     * Configuración de la empresa.
     */
    public function settings()
    {
        $company = $this->getUserCompany();

        if (!$company) {
            return redirect()->route('company.dashboard')
            ->with('error', 'No se encontró la empresa asociada a su usuario.');
        }

        return view('company.settings', compact('company'));
    }

    /**
     * Actualizar información de la empresa.
     */
    public function updateCompany(Request $request)
    {
        $company = $this->getUserCompany();

        if (!$company) {
            return redirect()->route('company.dashboard')
            ->with('error', 'No se encontró la empresa asociada a su usuario.');
        }

        $request->validate([
            'business_name' => 'required|string|max:255',
            'commercial_name' => 'nullable|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
        ]);

        $company->update($request->only([
            'business_name',
            'commercial_name',
            'email',
            'phone',
            'address',
            'city',
            'postal_code',
        ]));

        return redirect()->route('company.settings')
        ->with('success', 'Información de la empresa actualizada correctamente.');
    }

    /**
     * Actualizar preferencias de la empresa.
     */
    public function updatePreferences(Request $request)
    {
        $company = $this->getUserCompany();

        if (!$company) {
            return redirect()->route('company.dashboard')
            ->with('error', 'No se encontró la empresa asociada a su usuario.');
        }

        $request->validate([
            'ws_environment' => 'required|in:testing,production',
            'ws_active' => 'boolean',
            'timezone' => 'required|string|max:50',
        ]);

        $company->update([
            'ws_environment' => $request->ws_environment,
            'ws_active' => $request->boolean('ws_active'),
                         // timezone se puede guardar en ws_config como JSON
                         'ws_config' => array_merge($company->ws_config ?? [], [
                             'timezone' => $request->timezone,
                         ]),
        ]);

        return redirect()->route('company.settings')
        ->with('success', 'Preferencias actualizadas correctamente.');
    }
}
