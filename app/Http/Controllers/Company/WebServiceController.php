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
            ->with(['originPort', 'destinationPort'])  // ✅ CORRECTO
            ->limit(3)
            ->get()
            ->map(function($voyage) use ($certificateStatus) {  // ✅ CON USE
                $hasPendingWS = !$voyage->webserviceTransactions()
                    ->where('status', 'success')
                    ->exists();
                
                return [
                    'id' => $voyage->id,
                    'voyage_number' => $voyage->voyage_number,
                    'route' => ($voyage->originPort->name ?? 'N/A') . ' → ' . ($voyage->destinationPort->name ?? 'N/A'),  // ✅ CORRECTO
                    'shipments_count' => $voyage->shipments()->count(),
                    'has_pending_webservice' => $hasPendingWS,
                    'can_send' => $hasPendingWS && $certificateStatus['has_certificate'] && !$certificateStatus['is_expired']  // ✅ CORRECTO
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

        
    /**
     * Obtener detalles completos de una transacción de webservice
     * Ruta: GET /company/webservices/transaction/{id}
     */
    public function getTransactionDetails($transactionId)
    {
        // 1. Verificar permisos
        if (!$this->canPerform('manage_webservices') && !$this->isUser()) {
            return response()->json([
                'success' => false,
                'message' => 'No tiene permisos para ver los detalles.'
            ], 403);
        }

        $company = $this->getUserCompany();

        if (!$company) {
            return response()->json([
                'success' => false,
                'message' => 'No se encontró la empresa asociada.'
            ], 404);
        }

        try {
            // 2. Buscar la transacción
            $transaction = WebserviceTransaction::where('id', $transactionId)
                ->where('company_id', $company->id)
                ->with(['user:id,name'])
                ->first();

            if (!$transaction) {
                return response()->json([
                    'success' => false,
                    'message' => 'Transacción no encontrada.'
                ], 404);
            }

            // 3. Preparar datos de la transacción
            $transactionData = [
                'id' => $transaction->id,
                'transaction_id' => $transaction->transaction_id,
                'external_reference' => $transaction->external_reference,
                'webservice_type' => $transaction->webservice_type,
                'country' => $transaction->country,
                'webservice_url' => $transaction->webservice_url,
                'status' => $transaction->status,
                'retry_count' => $transaction->retry_count,
                'max_retries' => $transaction->max_retries,
                'environment' => $transaction->environment,
                'user_name' => $transaction->user->name ?? null,
                'created_at' => $transaction->created_at,
                'sent_at' => $transaction->sent_at,
                'response_at' => $transaction->response_at,
                'response_time_ms' => $transaction->response_time_ms,
                'error_code' => $transaction->error_code,
                'error_message' => $transaction->error_message,
                'confirmation_number' => $transaction->confirmation_number,
                'container_count' => $transaction->container_count,
                'total_weight_kg' => $transaction->total_weight_kg,
                'total_value' => $transaction->total_value,
                'currency_code' => $transaction->currency_code,
                'request_xml' => !empty($transaction->request_xml),
                'response_xml' => !empty($transaction->response_xml),
            ];

            // 4. Preparar datos de la respuesta básicos (hasta implementar relación con WebserviceResponse)
            $responseData = null;
            if ($transaction->status === 'success') {
                $responseData = [
                    'confirmation_number' => $transaction->confirmation_number,
                    'reference_number' => $transaction->external_reference,
                    'voyage_number' => null, // TODO: extraer del success_data
                    'manifest_number' => null,
                    'tracking_numbers' => $transaction->tracking_numbers,
                    'container_numbers' => null,
                    'customs_status' => 'approved', // Simulado por ahora
                    'customs_processed_at' => $transaction->response_at,
                    'requires_action' => false,
                    'urgent_action_required' => false,
                    'action_deadline' => null,
                    'action_description' => null,
                    'payment_status' => null,
                    'customs_fees' => null,
                    'documents_required' => false,
                    'documents_approved' => true,
                    'validation_errors' => null,
                    'validation_warnings' => null,
                ];
            }

            return response()->json([
                'success' => true,
                'transaction' => $transactionData,
                'response' => $responseData,
            ]);

        } catch (\Exception $e) {
            Log::error('Error obteniendo detalles de transacción', [
                'transaction_id' => $transactionId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener los detalles: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener XML de request o response de una transacción
     * Ruta: GET /company/webservices/transaction/{id}/xml/{type}
     */
    public function getTransactionXML($transactionId, $type)
    {
        // 1. Verificar permisos
        if (!$this->canPerform('manage_webservices') && !$this->isUser()) {
            return response()->json([
                'success' => false,
                'message' => 'No tiene permisos para ver los XMLs.'
            ], 403);
        }

        $company = $this->getUserCompany();

        if (!$company) {
            return response()->json([
                'success' => false,
                'message' => 'No se encontró la empresa asociada.'
            ], 404);
        }

        // 2. Validar tipo
        if (!in_array($type, ['request', 'response'])) {
            return response()->json([
                'success' => false,
                'message' => 'Tipo de XML inválido.'
            ], 400);
        }

        try {
            // 3. Buscar la transacción
            $transaction = WebserviceTransaction::where('id', $transactionId)
                ->where('company_id', $company->id)
                ->first();

            if (!$transaction) {
                return response()->json([
                    'success' => false,
                    'message' => 'Transacción no encontrada.'
                ], 404);
            }

            // 4. Obtener XML según el tipo
            $xmlField = $type . '_xml';
            $xmlContent = $transaction->{$xmlField};

            if (!$xmlContent) {
                return response()->json([
                    'success' => false,
                    'message' => "No hay XML de {$type} disponible para esta transacción."
                ], 404);
            }

            // 5. Formatear XML para mejor visualización
            $formattedXml = $this->formatXML($xmlContent);

            return response()->json([
                'success' => true,
                'xml' => $formattedXml,
                'type' => $type,
                'transaction_id' => $transaction->transaction_id,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener el XML: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reintentar una transacción fallida
     * Ruta: POST /company/webservices/transaction/{id}/retry
     */
    public function retryTransaction($transactionId)
    {
        // 1. Verificar permisos
        if (!$this->canPerform('manage_webservices')) {
            return response()->json([
                'success' => false,
                'message' => 'No tiene permisos para reintentar transacciones.'
            ], 403);
        }

        $company = $this->getUserCompany();

        if (!$company) {
            return response()->json([
                'success' => false,
                'message' => 'No se encontró la empresa asociada.'
            ], 404);
        }

        try {
            // 2. Buscar la transacción
            $transaction = WebserviceTransaction::where('id', $transactionId)
                ->where('company_id', $company->id)
                ->first();

            if (!$transaction) {
                return response()->json([
                    'success' => false,
                    'message' => 'Transacción no encontrada.'
                ], 404);
            }

            // 3. Validar que se pueda reintentar
            if ($transaction->status === 'success') {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede reintentar una transacción exitosa.'
                ], 400);
            }

            if ($transaction->retry_count >= $transaction->max_retries) {
                return response()->json([
                    'success' => false,
                    'message' => 'Se ha alcanzado el máximo número de reintentos.'
                ], 400);
            }

            // 4. Actualizar transacción para reintento
            $transaction->update([
                'status' => 'pending',
                'retry_count' => $transaction->retry_count + 1,
                'next_retry_at' => null,
                'error_code' => null,
                'error_message' => null,
                'response_at' => null,
                'response_time_ms' => null,
            ]);

            // 5. Log del reintento
            Log::info('Transacción marcada para reintento', [
                'transaction_id' => $transaction->id,
                'company_id' => $company->id,
                'user_id' => auth()->id(),
                'retry_count' => $transaction->retry_count,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Transacción marcada para reintento exitosamente.',
                'retry_count' => $transaction->retry_count,
                'max_retries' => $transaction->max_retries,
            ]);

        } catch (\Exception $e) {
            Log::error('Error al reintentar transacción', [
                'transaction_id' => $transactionId,
                'company_id' => $company->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error interno al reintentar la transacción.'
            ], 500);
        }
    }

    /**
     * Formatear XML para mejor visualización
     */
    private function formatXML(string $xml): string
    {
        try {
            $dom = new \DOMDocument('1.0', 'UTF-8');
            $dom->preserveWhiteSpace = false;
            $dom->formatOutput = true;
            
            // Suprimir warnings de XML malformado
            libxml_use_internal_errors(true);
            $loaded = $dom->loadXML($xml);
            libxml_clear_errors();
            
            if ($loaded) {
                return $dom->saveXML();
            } else {
                // Si no se puede formatear, devolver el XML original
                return $xml;
            }
        } catch (\Exception $e) {
            // Si hay cualquier error, devolver el XML original
            return $xml;
        }
    }

    /**
 * Dashboard de métricas de webservices - DATOS REALES
 * Ruta: GET /company/webservices/dashboard
 */
public function dashboard(Request $request)
{
    // 1. Verificar permisos
    if (!$this->canPerform('manage_webservices') && !$this->isUser()) {
        abort(403, 'No tiene permisos para ver el dashboard de webservices.');
    }

    $company = $this->getUserCompany();

    if (!$company) {
        return redirect()->route('company.webservices.index')
            ->with('error', 'No se encontró la empresa asociada.');
    }

    // 2. Período de análisis (últimos 30 días por defecto)
    $dateFrom = $request->input('date_from', now()->subDays(30)->format('Y-m-d'));
    $dateTo = $request->input('date_to', now()->format('Y-m-d'));

    // 3. Obtener métricas principales
    $metrics = $this->getDashboardMetrics($company, $dateFrom, $dateTo);

    // 4. Datos para filtros
    $availableTypes = $this->getAvailableWebserviceTypes($company);
    $countries = ['AR' => 'Argentina', 'PY' => 'Paraguay'];

    return view('company.webservices.dashboard', compact(
        'company',
        'metrics',
        'availableTypes',
        'countries',
        'dateFrom',
        'dateTo'
    ));
}

/**
 * Obtener métricas del dashboard basadas en datos reales
 */
private function getDashboardMetrics(Company $company, string $dateFrom, string $dateTo): array
{
    // MÉTRICA 1: Resumen general - Queries independientes
    $totalTransactions = WebserviceTransaction::where('company_id', $company->id)
        ->whereBetween('created_at', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'])
        ->count();

    $successTransactions = WebserviceTransaction::where('company_id', $company->id)
        ->whereBetween('created_at', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'])
        ->where('status', 'success')
        ->count();

    $errorTransactions = WebserviceTransaction::where('company_id', $company->id)
        ->whereBetween('created_at', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'])
        ->whereIn('status', ['error', 'expired'])
        ->count();

    $pendingTransactions = WebserviceTransaction::where('company_id', $company->id)
        ->whereBetween('created_at', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'])
        ->whereIn('status', ['pending', 'sending', 'retry'])
        ->count();

    $successRate = $totalTransactions > 0 ? round(($successTransactions / $totalTransactions) * 100, 1) : 0;

    // MÉTRICA 2: Transacciones por país
    $transactionsByCountry = WebserviceTransaction::where('company_id', $company->id)
        ->whereBetween('created_at', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'])
        ->select('country', \DB::raw('count(*) as total'))
        ->groupBy('country')
        ->get()
        ->keyBy('country')
        ->map(fn($item) => $item->total);

    $successByCountry = WebserviceTransaction::where('company_id', $company->id)
        ->whereBetween('created_at', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'])
        ->where('status', 'success')
        ->select('country', \DB::raw('count(*) as total'))
        ->groupBy('country')
        ->get()
        ->keyBy('country')
        ->map(fn($item) => $item->total);

    // MÉTRICA 3: Transacciones por tipo de webservice
    $transactionsByType = WebserviceTransaction::where('company_id', $company->id)
        ->whereBetween('created_at', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'])
        ->select('webservice_type', \DB::raw('count(*) as total'))
        ->groupBy('webservice_type')
        ->orderBy(\DB::raw('count(*)'), 'desc')
        ->get();

    $successByType = WebserviceTransaction::where('company_id', $company->id)
        ->whereBetween('created_at', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'])
        ->where('status', 'success')
        ->select('webservice_type', \DB::raw('count(*) as total'))
        ->groupBy('webservice_type')
        ->get()
        ->keyBy('webservice_type')
        ->map(fn($item) => $item->total);

    // MÉTRICA 4: Tiempo promedio de respuesta
    $avgResponseTime = WebserviceTransaction::where('company_id', $company->id)
        ->whereBetween('created_at', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'])
        ->whereNotNull('response_time_ms')
        ->where('response_time_ms', '>', 0)
        ->avg('response_time_ms');

    $avgResponseTimeByType = WebserviceTransaction::where('company_id', $company->id)
        ->whereBetween('created_at', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'])
        ->whereNotNull('response_time_ms')
        ->where('response_time_ms', '>', 0)
        ->select('webservice_type', \DB::raw('AVG(response_time_ms) as avg_time'))
        ->groupBy('webservice_type')
        ->get()
        ->keyBy('webservice_type')
        ->map(fn($item) => round($item->avg_time, 0));

    // MÉTRICA 5: Evolución temporal (últimos 7 días)
    $dailyStats = WebserviceTransaction::where('company_id', $company->id)
        ->where('created_at', '>=', now()->subDays(7)->startOfDay())
        ->select(
            \DB::raw('DATE(created_at) as date'),
            \DB::raw('count(*) as total'),
            \DB::raw('sum(case when status = "success" then 1 else 0 end) as success'),
            \DB::raw('sum(case when status in ("error", "expired") then 1 else 0 end) as errors')
        )
        ->groupBy(\DB::raw('DATE(created_at)'))
        ->orderBy('date')
        ->get();

    // MÉTRICA 6: Confirmaciones específicas por país
    $argentinaConfirmations = WebserviceTransaction::where('company_id', $company->id)
        ->whereBetween('created_at', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'])
        ->where('country', 'AR')
        ->whereHas('response', function($q) {
            $q->whereNotNull('argentina_tit_envio');
        })->count();

    $paraguayConfirmations = WebserviceTransaction::where('company_id', $company->id)
        ->whereBetween('created_at', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'])
        ->where('country', 'PY')
        ->whereHas('response', function($q) {
            $q->whereNotNull('paraguay_gdsf_reference');
        })->count();

    // MÉTRICA 7: Transacciones recientes
    $recentTransactions = WebserviceTransaction::where('company_id', $company->id)
        ->whereBetween('created_at', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'])
        ->with(['user:id,name', 'voyage:id,voyage_number', 'response'])
        ->orderByDesc('created_at')
        ->limit(10)
        ->get()
        ->map(function($transaction) {
            return [
                'id' => $transaction->id,
                'transaction_id' => $transaction->transaction_id,
                'webservice_type' => $transaction->webservice_type,
                'country' => $transaction->country,
                'status' => $transaction->status,
                'user_name' => $transaction->user->name ?? 'N/A',
                'voyage_number' => $transaction->voyage->voyage_number ?? 'N/A',
                'created_at' => $transaction->created_at->format('d/m/Y H:i'),
                'response_time_ms' => $transaction->response_time_ms,
                'confirmation_number' => $transaction->response->confirmation_number ?? null,
                'argentina_tit_envio' => $transaction->response->argentina_tit_envio ?? null,
                'paraguay_reference' => $transaction->response->paraguay_gdsf_reference ?? null,
            ];
        });

    // MÉTRICA 8: Estados de voyages pendientes (con try-catch por si no existe la tabla)
    try {
        $pendingVoyageStatuses = \App\Models\VoyageWebserviceStatus::where('company_id', $company->id)
            ->whereIn('status', ['pending', 'sending', 'error'])
            ->with(['voyage:id,voyage_number'])
            ->orderByDesc('updated_at')
            ->limit(5)
            ->get()
            ->map(function($status) {
                return [
                    'voyage_number' => $status->voyage->voyage_number ?? 'N/A',
                    'country' => $status->country,
                    'webservice_type' => $status->webservice_type,
                    'status' => $status->status,
                    'updated_at' => $status->updated_at->format('d/m/Y H:i'),
                    'error_message' => $status->error_message,
                ];
            });
    } catch (\Exception $e) {
        $pendingVoyageStatuses = collect([]);
    }

    return [
        'summary' => [
            'total_transactions' => $totalTransactions,
            'success_transactions' => $successTransactions,
            'error_transactions' => $errorTransactions,
            'pending_transactions' => $pendingTransactions,
            'success_rate' => $successRate,
            'avg_response_time_ms' => $avgResponseTime ? round($avgResponseTime, 0) : 0,
        ],
        'by_country' => [
            'transactions' => $transactionsByCountry,
            'success' => $successByCountry,
            'argentina_confirmations' => $argentinaConfirmations,
            'paraguay_confirmations' => $paraguayConfirmations,
        ],
        'by_type' => [
            'transactions' => $transactionsByType,
            'success' => $successByType,
            'avg_response_time' => $avgResponseTimeByType,
        ],
        'timeline' => $dailyStats,
        'recent_transactions' => $recentTransactions,
        'pending_voyage_statuses' => $pendingVoyageStatuses,
    ];
}

}