<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Traits\UserHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use App\Models\WebserviceTransaction;
use App\Models\WebserviceLog;
use Exception;

/**
 * WebServiceController - Versión Limpia y Enfocada
 * 
 * RESPONSABILIDADES:
 * - Dashboard principal de webservices ✅
 * - Historial de transacciones 
 * - Consultas de estado
 * - Configuración básica
 * 
 * NO INCLUYE (ya existe en otros controladores):
 * - Importación de manifiestos → ManifestImportController
 * - Envío a aduanas → ManifestCustomsController  
 * - Exportación → ManifestExportController
 */
class WebserviceController extends Controller
{
    use UserHelper;

    /**
     * Dashboard principal de webservices - VERSIÓN MEJORADA
     * Incluye integración con ManifestCustomsController
     */
    public function index()
    {
        // 1. Verificar permisos básicos
        if (!$this->canPerform('manage_webservices') && !$this->isUser()) {
            abort(403, 'No tiene permisos para acceder a webservices.');
        }

        $company = $this->getUserCompany();

        if (!$company) {
            return redirect()->route('company.dashboard')
                ->with('error', 'No se encontró la empresa asociada a su usuario.');
        }

        // 2. Datos para la vista
        $companyRoles = $company->company_roles ?? [];
        $certificateStatus = $this->getCertificateStatus($company);
        $stats = $this->getWebserviceStatistics($company);
        $recentTransactions = $this->getRecentTransactions($company, 5);

        // 3. NUEVO: Acciones rápidas integradas
        $quickActions = $this->getQuickActions($company);

        // 4. NUEVO: Estado de manifiestos pendientes
        $pendingManifests = \App\Models\Voyage::where('company_id', $company->id)
            ->whereHas('shipments')
            ->whereIn('status', ['completed', 'in_progress'])
            ->with(['origin_port', 'destination_port'])
            ->limit(3)
            ->get()
            ->map(function($voyage) {
                $hasPendingWS = !$voyage->webserviceTransactions()
                    ->where('status', 'success')
                    ->exists();
                
                return [
                    'id' => $voyage->id,
                    'voyage_number' => $voyage->voyage_number,
                    'route' => ($voyage->origin_port->name ?? 'N/A') . ' → ' . ($voyage->destination_port->name ?? 'N/A'),
                    'shipments_count' => $voyage->shipments()->count(),
                    'has_pending_webservice' => $hasPendingWS,
                    'can_send' => $hasPendingWS && $certificateStatus['has_certificate'] && !$certificateStatus['is_expired']
                ];
            });

        return view('company.webservices.index', compact(
            'company',
            'companyRoles', 
            'certificateStatus',
            'stats',
            'recentTransactions',
            'quickActions',
            'pendingManifests'
        ));
    }
    
    /**
     * Historial de transacciones de webservices
     * Ruta: GET /company/webservices/history
     */
    public function history(Request $request)
    {
        // 1. Verificar permisos
        if (!$this->canPerform('manage_webservices') && !$this->isUser()) {
            abort(403, 'No tiene permisos para ver el historial.');
        }

        $company = $this->getUserCompany();

        if (!$company) {
            return redirect()->route('company.webservices.index')
                ->with('error', 'No se encontró la empresa asociada.');
        }

        // 2. Obtener transacciones con filtros
        $query = WebserviceTransaction::where('company_id', $company->id)
            ->with(['user:id,name']);

        // Filtros opcionales
        if ($request->filled('webservice_type')) {
            $query->where('webservice_type', $request->webservice_type);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->where('created_at', '<=', $request->date_to . ' 23:59:59');
        }

        $transactions = $query->orderBy('created_at', 'desc')->paginate(20);

        // 3. Datos para filtros
        $availableTypes = $this->getAvailableWebserviceTypes($company);
        $statuses = ['pending' => 'Pendiente', 'success' => 'Exitoso', 'error' => 'Error'];

        return view('company.webservices.history', compact(
            'company',
            'transactions',
            'availableTypes', 
            'statuses'
        ));
    }

    /**
     * Consultar estado de manifiestos enviados
     * Ruta: GET /company/webservices/query
     */
    public function query()
    {
        // 1. Verificar permisos
        if (!$this->canPerform('manage_webservices') && !$this->isUser()) {
            abort(403, 'No tiene permisos para consultar estados.');
        }

        $company = $this->getUserCompany();

        if (!$company) {
            return redirect()->route('company.webservices.index')
                ->with('error', 'No se encontró la empresa asociada.');
        }

        // 2. Verificar certificado
        $certificateStatus = $this->getCertificateStatus($company);
        if (!$certificateStatus['has_certificate'] || $certificateStatus['is_expired']) {
            return redirect()->route('company.certificates.index')
                ->with('error', 'Debe tener un certificado digital válido para consultar estados.');
        }

        // 3. Datos para la vista
        $availableTypes = $this->getAvailableWebserviceTypes($company);
        
        return view('company.webservices.query', compact(
            'company',
            'certificateStatus',
            'availableTypes'
        ));
    }

    /**
     * Procesar consulta de estado (POST)
     * Ruta: POST /company/webservices/query
     */
    public function processQuery(Request $request)
    {
        // 1. Validación
        $request->validate([
            'query_type' => 'required|in:transaction_id,reference',
            'query_value' => 'required|string|min:3',
            'webservice_type' => 'required|string',
        ]);

        $company = $this->getUserCompany();

        try {
            // 2. Buscar transacción según el tipo de consulta
            if ($request->query_type === 'transaction_id') {
                $transaction = WebserviceTransaction::where('company_id', $company->id)
                    ->where('transaction_id', $request->query_value)
                    ->where('webservice_type', $request->webservice_type)
                    ->first();
            } else {
                $transaction = WebserviceTransaction::where('company_id', $company->id)
                    ->where('external_reference', $request->query_value)
                    ->where('webservice_type', $request->webservice_type)
                    ->first();
            }

            if (!$transaction) {
                return back()
                    ->withInput()
                    ->with('error', 'No se encontró ninguna transacción con los datos proporcionados.');
            }

            // 3. Obtener logs relacionados
            $logs = WebserviceLog::where('transaction_id', $transaction->id)
                ->orderBy('created_at', 'desc')
                ->get();

            return view('company.webservices.query-result', compact(
                'company',
                'transaction',
                'logs'
            ));

        } catch (Exception $e) {
            Log::error('Error en consulta de webservice', [
                'company_id' => $company->id,
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return back()
                ->withInput()
                ->with('error', 'Error al consultar el estado: ' . $e->getMessage());
        }
    }

    // ========================================
    // MÉTODOS HELPER PRIVADOS
    // ========================================

    /**
     * Obtener estado del certificado digital
     */
    private function getCertificateStatus(Company $company): array
    {
        $hasCert = !empty($company->certificate_path) && Storage::exists($company->certificate_path);
        $isExpired = $company->certificate_expires_at && $company->certificate_expires_at->isPast();
        $isExpiringSoon = $company->certificate_expires_at && $company->certificate_expires_at->isBefore(now()->addDays(30));
        
        return [
            'has_certificate' => $hasCert,
            'is_expired' => $isExpired,
            'is_expiring_soon' => $isExpiringSoon,
            'needs_renewal' => $isExpired || $isExpiringSoon,
            'expires_at' => $company->certificate_expires_at,
            'status_text' => $this->getCertificateStatusText($hasCert, $isExpired, $isExpiringSoon),
            'status_color' => $this->getCertificateStatusColor($hasCert, $isExpired, $isExpiringSoon),
            'days_to_expiry' => $company->certificate_expires_at ? 
                now()->diffInDays($company->certificate_expires_at, false) : null,
        ];
    }

    /**
     * Obtener texto del estado del certificado
     */
    private function getCertificateStatusText(bool $hasCert, bool $isExpired, bool $isExpiringSoon): string
    {
        if (!$hasCert) return 'Sin certificado';
        if ($isExpired) return 'Vencido';
        if ($isExpiringSoon) return 'Por vencer';
        return 'Válido';
    }

    /**
     * Obtener color del estado del certificado
     */
    private function getCertificateStatusColor(bool $hasCert, bool $isExpired, bool $isExpiringSoon): string
    {
        if (!$hasCert || $isExpired) return 'red';
        if ($isExpiringSoon) return 'yellow';
        return 'green';
    }

    /**
     * Obtener estadísticas de webservices
     */
    private function getWebserviceStatistics(Company $company): array
    {
        try {
            if (!Schema::hasTable('webservice_transactions')) {
                return $this->getDefaultStats();
            }

            $transactions = WebserviceTransaction::where('company_id', $company->id)
                ->where('created_at', '>=', now()->subDays(30))
                ->get();

            $total = $transactions->count();
            $successful = $transactions->where('status', 'success')->count();

            return [
                'total_transactions' => $total,
                'success_rate' => $total > 0 ? round(($successful / $total) * 100, 1) : 0.0,
                'last_24h' => $transactions->where('created_at', '>=', now()->subDay())->count(),
                'last_7d' => $transactions->where('created_at', '>=', now()->subDays(7))->count(),
                'last_30d' => $total,
                'anticipada' => $this->getStatsForType($transactions, 'anticipada'),
                'micdta' => $this->getStatsForType($transactions, 'micdta'),
                'desconsolidados' => $this->getStatsForType($transactions, 'desconsolidados'),
                'transbordos' => $this->getStatsForType($transactions, 'transbordos'),
                'paraguay' => $this->getStatsForType($transactions, 'paraguay'),
            ];

        } catch (Exception $e) {
            Log::warning('Error obteniendo estadísticas', [
                'company_id' => $company->id,
                'error' => $e->getMessage()
            ]);
            
            return $this->getDefaultStats();
        }
    }

    /**
     * Obtener estadísticas por tipo de webservice
     */
    private function getStatsForType($transactions, string $type): array
    {
        $filtered = $transactions->where('webservice_type', $type);
        
        return [
            'total' => $filtered->count(),
            'success' => $filtered->where('status', 'success')->count(),
            'failed' => $filtered->whereIn('status', ['error', 'expired'])->count(),
            'pending' => $filtered->where('status', 'pending')->count(),
        ];
    }

    /**
     * Estadísticas por defecto cuando no hay datos
     */
    private function getDefaultStats(): array
    {
        $empty = ['total' => 0, 'success' => 0, 'failed' => 0, 'pending' => 0];
        
        return [
            'total_transactions' => 0,
            'success_rate' => 0.0,
            'last_24h' => 0,
            'last_7d' => 0,
            'last_30d' => 0,
            'anticipada' => $empty,
            'micdta' => $empty,
            'desconsolidados' => $empty,
            'transbordos' => $empty,
            'paraguay' => $empty,
        ];
    }

    /**
     * Obtener transacciones recientes
     */
    private function getRecentTransactions(Company $company, int $limit = 5)
    {
        try {
            if (!Schema::hasTable('webservice_transactions')) {
                return collect([]);
            }

            return WebserviceTransaction::where('company_id', $company->id)
                ->with(['user:id,name'])
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get()
                ->map(function ($transaction) {
                    return [
                        'id' => $transaction->id,
                        'type' => $transaction->webservice_type,
                        'status' => $transaction->status,
                        'transaction_id' => $transaction->transaction_id,
                        'created_at' => $transaction->created_at,
                        'user_name' => $transaction->user->name ?? 'Sistema',
                        'status_color' => $this->getStatusColor($transaction->status),
                        'status_text' => $this->getStatusText($transaction->status),
                    ];
                });

        } catch (Exception $e) {
            Log::warning('Error obteniendo transacciones recientes', [
                'company_id' => $company->id,
                'error' => $e->getMessage()
            ]);
            
            return collect([]);
        }
    }

    /**
     * Obtener tipos de webservice disponibles según roles
     */
    private function getAvailableWebserviceTypes(Company $company): array
    {
        $roles = $company->company_roles ?? [];
        $types = [];

        if (in_array('Cargas', $roles)) {
            $types['anticipada'] = 'Información Anticipada';
            $types['micdta'] = 'MIC/DTA';
        }

        if (in_array('Desconsolidador', $roles)) {
            $types['desconsolidados'] = 'Desconsolidados';
        }

        if (in_array('Transbordos', $roles)) {
            $types['transbordos'] = 'Transbordos';
        }

        if ($company->country === 'PY' || in_array($company->country, ['AR', 'PY'])) {
            $types['paraguay'] = 'DNA Paraguay';
        }

        return $types;
    }

    /**
     * Obtener color del estado
     */
    private function getStatusColor(string $status): string
    {
        return match($status) {
            'success' => 'green',
            'pending' => 'yellow',
            'error', 'expired' => 'red',
            default => 'gray'
        };
    }

    /**
     * Obtener texto del estado
     */
    private function getStatusText(string $status): string
    {
        return match($status) {
            'success' => 'Exitoso',
            'pending' => 'Pendiente', 
            'error' => 'Error',
            'expired' => 'Expirado',
            default => ucfirst($status)
        };
    }

    /**
     * Redireccionar a ManifestCustomsController para envío real
     * Este método conecta el dashboard de webservices con el envío real
     */
    public function redirectToCustoms(Request $request)
    {
        // 1. Verificar permisos
        if (!$this->canPerform('manage_webservices') && !$this->isUser()) {
            abort(403, 'No tiene permisos para enviar a aduanas.');
        }

        $company = $this->getUserCompany();

        if (!$company) {
            return redirect()->route('company.webservices.index')
                ->with('error', 'No se encontró la empresa asociada.');
        }

        // 2. Verificar certificado
        $certificateStatus = $this->getCertificateStatus($company);
        if (!$certificateStatus['has_certificate'] || $certificateStatus['is_expired']) {
            return redirect()->route('company.certificates.index')
                ->with('warning', 'Debe tener un certificado digital válido para enviar manifiestos a la aduana.');
        }

        // 3. Verificar que tiene viajes para enviar
        $pendingVoyages = \App\Models\Voyage::where('company_id', $company->id)
            ->whereHas('shipments')
            ->whereIn('status', ['completed', 'in_progress'])
            ->count();

        if ($pendingVoyages === 0) {
            return redirect()->route('company.manifests.import.index')
                ->with('info', 'Primero debe importar manifiestos antes de enviarlos a la aduana.');
        }

        // 4. Redireccionar con parámetros si los hay
        $params = [];
        if ($request->filled('webservice_type')) {
            $params['webservice_type'] = $request->webservice_type;
        }
        if ($request->filled('country')) {
            $params['country'] = $request->country;
        }

        return redirect()->route('company.manifests.customs.index', $params)
            ->with('success', 'Puede seleccionar los manifiestos a enviar a la aduana.');
    }

    /**
     * Método helper para obtener accesos directos desde el dashboard
     */
    public function getQuickActions(Company $company): array
    {
        $actions = [];

        // Verificar certificado
        $certificateStatus = $this->getCertificateStatus($company);
        
        if (!$certificateStatus['has_certificate']) {
            $actions[] = [
                'title' => 'Configurar Certificado',
                'description' => 'Subir certificado digital .p12 requerido',
                'route' => 'company.certificates.index',
                'icon' => '🔐',
                'priority' => 'high',
                'color' => 'red'
            ];
        }

        // Verificar manifiestos pendientes
        $pendingVoyages = \App\Models\Voyage::where('company_id', $company->id)
            ->whereHas('shipments')
            ->whereIn('status', ['completed', 'in_progress'])
            ->whereDoesntHave('webserviceTransactions', function($q) {
                $q->where('status', 'success');
            })
            ->count();

        if ($pendingVoyages > 0) {
            $actions[] = [
                'title' => 'Enviar Manifiestos',
                'description' => "{$pendingVoyages} manifiestos listos para enviar",
                'route' => 'company.manifests.customs.index',
                'icon' => '📤',
                'priority' => 'medium',
                'color' => 'blue'
            ];
        }

        // Transacciones fallidas
        $failedTransactions = \App\Models\WebserviceTransaction::where('company_id', $company->id)
            ->where('status', 'error')
            ->where('created_at', '>=', now()->subDays(7))
            ->count();

        if ($failedTransactions > 0) {
            $actions[] = [
                'title' => 'Revisar Errores',
                'description' => "{$failedTransactions} transacciones fallidas",
                'route' => 'company.webservices.history',
                'params' => ['status' => 'error'],
                'icon' => '⚠️',
                'priority' => 'high',
                'color' => 'yellow'
            ];
        }

        // Si no hay certificado válido, no mostrar acciones de envío
        if ($certificateStatus['is_expired']) {
            $actions = array_filter($actions, function($action) {
                return $action['route'] !== 'company.manifests.customs.index';
            });
        }

        return $actions;
    }

}