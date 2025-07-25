<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Traits\UserHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;
use App\Services\Webservice\ArgentinaAnticipatedService;
use App\Services\Webservice\ArgentinaMicDtaService;
use App\Services\Webservice\ArgentinaTransshipmentService;
use App\Services\Webservice\ArgentinaDeconsolidationService;
use App\Services\Webservice\ParaguayCustomsService;
use App\Models\WebserviceTransaction;
use App\Models\WebserviceLog;
use App\Models\Voyage;
use App\Models\Shipment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WebserviceController extends Controller
{
    use UserHelper;

    /**
     * Vista principal de configuración de webservices.
     */
    public function index()
    {
        // 1. Verificar permisos básicos (company-admin o user con empresa)
        if (!$this->canPerform('manage_webservices') && !$this->hasRole('user')) {
            abort(403, 'No tiene permisos para acceder a webservices.');
        }

        $company = $this->getUserCompany();

        // 2. Verificar que el usuario tenga una empresa asociada
        if (!$company) {
            return redirect()->route('company.dashboard')
                ->with('error', 'No se encontró la empresa asociada a su usuario.');
        }

        // 3. Datos para la vista
        $companyRoles = $company->company_roles ?? [];
        $certificateStatus = $this->getCertificateStatus($company);
        
       // Estadísticas reales del sistema
        $stats = $this->getWebserviceStatistics($company);
        $recentTransactions = $this->getRecentTransactions($company, 5);
        $pendingTrips = $this->getPendingTrips($company)->take(3);

        return view('company.webservices.index', compact(
            'company',
            'companyRoles',
            'certificateStatus',
            'stats',
            'recentTransactions'
        ));
    }

    /**
     * Vista para envío de manifiestos
     */
    public function send(Request $request)
    {
        if (!$this->canPerform('manage_webservices') && !$this->hasRole('user')) {
            abort(403, 'No tiene permisos para enviar manifiestos.');
        }

        $company = $this->getUserCompany();
        if (!$company) {
            return redirect()->route('company.dashboard')
                ->with('error', 'No se encontró la empresa asociada.');
        }

        $companyRoles = $company->company_roles ?? [];
        $webserviceType = $request->get('type', 'anticipada');
        
        // Verificar que la empresa puede usar este webservice
        $availableTypes = $this->getAvailableWebserviceTypes($companyRoles);
        if (!in_array($webserviceType, $availableTypes)) {
            abort(403, 'Su empresa no tiene permisos para este tipo de webservice.');
        }

        // Obtener datos según el tipo de webservice
        $data = $this->getWebserviceData($company, $webserviceType);
        
        return view('company.webservices.send', compact(
            'company',
            'companyRoles', 
            'webserviceType',
            'availableTypes',
            'data'
        ));
    }

    /**
     * Obtener datos para el webservice
     */
    private function getWebserviceData(Company $company, string $type): array
    {
        switch ($type) {
            case 'anticipada':
            case 'micdta':
                // Obtener viajes pendientes de envío
                return [
                    'trips' => $this->getPendingTrips($company),
                    'vessels' => $this->getCompanyVessels($company),
                ];
                
            case 'desconsolidados':
                return [
                    'shipments' => $this->getPendingDeconsolidationShipments($company),
                ];
                
            case 'transbordos':
                return [
                    'transfers' => $this->getPendingTransfers($company),
                    'barges' => $this->getAvailableBarges($company),
                ];
                
            default:
                return [];
        }
    }                         

    private function getPendingDeconsolidationShipments(Company $company): array
    {
        // TODO: Implementar según modelo Shipment
        return [];
    }

    private function getPendingTransfers(Company $company): array
    {
        // TODO: Implementar según modelo Transfer
        return [];
    }

    private function getAvailableBarges(Company $company): array
    {
        // TODO: Implementar según datos PARANA.csv
        return [];
    }

    /**
     * Mostrar configuración específica de webservice.
     */
    public function show($webservice)
    {
        // 1. Verificar permisos básicos
        if (!$this->canPerform('webservice_access')) {
            abort(403, 'No tiene permisos para acceder a webservices.');
        }

        $company = $this->getUserCompany();

        if (!$company) {
            return redirect()->route('company.dashboard')
                ->with('error', 'No se encontró la empresa asociada a su usuario.');
        }

        // 2. Verificar acceso a empresa
        if (!$this->canAccessCompany($company->id)) {
            abort(403, 'No tiene permisos para acceder a esta empresa.');
        }

        // 3. Verificar que la empresa puede usar este webservice
        if (!$company->canUseWebservice($webservice)) {
            abort(403, "No tiene permisos para usar el webservice '{$webservice}'.");
        }

        $webserviceDetails = $this->getWebserviceDetails($company, $webservice);
        $connectionLogs = $this->getConnectionLogs($company, $webservice);

        return view('company.webservices.show', compact(
            'company',
            'webservice',
            'webserviceDetails',
            'connectionLogs'
        ));
    }

    /**
     * Actualizar configuración de webservices (solo company-admin).
     */
    public function updateConfig(Request $request)
    {
        // 1. Verificar permisos básicos
        if (!$this->canPerform('webservice_config')) {
            abort(403, 'No tiene permisos para configurar webservices.');
        }

        // 2. Solo company-admin puede configurar
        if (!$this->isCompanyAdmin()) {
            abort(403, 'Solo los administradores de empresa pueden configurar webservices.');
        }

        $company = $this->getUserCompany();

        if (!$company) {
            return redirect()->route('company.dashboard')
                ->with('error', 'No se encontró la empresa asociada a su usuario.');
        }

        // 3. Verificar acceso a empresa
        if (!$this->canAccessCompany($company->id)) {
            abort(403, 'No tiene permisos para acceder a esta empresa.');
        }

        // 4. Validación
        $request->validate([
            'ws_active' => 'boolean',
            'ws_environment' => 'required|in:testing,production',
            'ws_config' => 'nullable|json',
            'timeout' => 'nullable|integer|min:5|max:300',
            'retry_attempts' => 'nullable|integer|min:1|max:5',
        ], [
            'ws_environment.required' => 'Debe seleccionar un entorno.',
            'ws_environment.in' => 'El entorno debe ser testing o production.',
            'ws_config.json' => 'La configuración debe ser un JSON válido.',
            'timeout.between' => 'El timeout debe estar entre 5 y 300 segundos.',
            'retry_attempts.between' => 'Los reintentos deben estar entre 1 y 5.',
        ]);

        try {
            // Preparar configuración
            $wsConfig = $company->ws_config ?? [];

            // Actualizar configuración básica
            if ($request->filled('timeout')) {
                $wsConfig['timeout'] = $request->timeout;
            }

            if ($request->filled('retry_attempts')) {
                $wsConfig['retry_attempts'] = $request->retry_attempts;
            }

            // Agregar configuración JSON adicional si se proporciona
            if ($request->filled('ws_config')) {
                $additionalConfig = json_decode($request->ws_config, true);
                $wsConfig = array_merge($wsConfig, $additionalConfig);
            }

            // Actualizar empresa
            $company->update([
                'ws_active' => $request->boolean('ws_active'),
                'ws_environment' => $request->ws_environment,
                'ws_config' => $wsConfig,
            ]);

            return redirect()->route('company.webservices.index')
                ->with('success', 'Configuración de webservices actualizada correctamente.');

        } catch (\Exception $e) {
            return back()
                ->withInput()
                ->with('error', 'Error al actualizar la configuración: ' . $e->getMessage());
        }
    }

    /**
     * Probar conexión a webservice específico.
     */
    public function testConnection(Request $request, $webservice)
    {
        // 1. Verificar permisos básicos
        if (!$this->canPerform('webservice_test')) {
            abort(403, 'No tiene permisos para probar webservices.');
        }

        $company = $this->getUserCompany();

        if (!$company) {
            return response()->json(['error' => 'No se encontró la empresa asociada.'], 400);
        }

        // 2. Verificar acceso a empresa
        if (!$this->canAccessCompany($company->id)) {
            return response()->json(['error' => 'No tiene permisos para acceder a esta empresa.'], 403);
        }

        // 3. Verificar que la empresa puede usar este webservice
        if (!$company->canUseWebservice($webservice)) {
            return response()->json(['error' => "No tiene permisos para usar el webservice '{$webservice}'."], 403);
        }

        // 4. Verificar que tiene certificado válido
        $certStatus = $this->getCertificateStatus($company);
        if (!$certStatus['has_certificate'] || $certStatus['is_expired']) {
            return response()->json([
                'error' => 'Certificado digital requerido o vencido para probar webservices.'
            ], 400);
        }

        try {
            $testResult = $this->performConnectionTest($company, $webservice);

            return response()->json([
                'success' => true,
                'result' => $testResult,
                'timestamp' => now()->format('Y-m-d H:i:s'),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'timestamp' => now()->format('Y-m-d H:i:s'),
            ], 500);
        }
    }

    /**
     * Obtener estadísticas de uso de webservices.
     */
    public function statistics()
    {
        // 1. Verificar permisos básicos
        if (!$this->canPerform('webservice_stats')) {
            abort(403, 'No tiene permisos para ver estadísticas de webservices.');
        }

        $company = $this->getUserCompany();

        if (!$company) {
            return redirect()->route('company.dashboard')
                ->with('error', 'No se encontró la empresa asociada a su usuario.');
        }

        // 2. Verificar acceso a empresa
        if (!$this->canAccessCompany($company->id)) {
            abort(403, 'No tiene permisos para acceder a esta empresa.');
        }

        $stats = $this->getWebserviceStatistics($company);

        return view('company.webservices.statistics', compact('company', 'stats'));
    }

    // ========================================
    // MÉTODOS HELPER PRIVADOS
    // ========================================

    /**
     * Obtener configuración de webservices de la empresa.
     */
    private function getWebserviceConfiguration(Company $company): array
    {
        $defaultConfig = $this->getDefaultWebserviceConfig($company->country);

        return [
            'active' => $company->ws_active ?? false,
            'environment' => $company->ws_environment ?? 'testing',
            'timeout' => $company->ws_config['timeout'] ?? 30,
            'retry_attempts' => $company->ws_config['retry_attempts'] ?? 3,
            'custom_config' => $company->ws_config ?? [],
            'default_urls' => $defaultConfig,
        ];
    }

    /**
     * Obtener webservices disponibles según roles de empresa.
     */
    private function getAvailableWebservices(Company $company): array
    {
        $webservices = [];
        $roles = $company->getRoles();

        foreach ($roles as $role) {
            switch ($role) {
                case 'Cargas':
                    $webservices['anticipada'] = [
                        'name' => 'Información Anticipada Marítima',
                        'description' => 'Recepción de información anticipada marítima con generación automática de manifiestos.',
                        'methods' => ['RegistrarViaje', 'RectificarViaje', 'RegistrarTitulosCbc'],
                        'country' => $company->country,
                        'requires_certificate' => true,
                    ];
                    $webservices['micdta'] = [
                        'name' => 'Registro MIC/DTA',
                        'description' => 'Registro de títulos y envíos para el MIC/DTA.',
                        'methods' => ['RegistrarTitEnvios', 'RegistrarEnvios', 'AnularEnvios'],
                        'country' => $company->country,
                        'requires_certificate' => true,
                    ];
                    break;

                case 'Desconsolidador':
                    $webservices['desconsolidados'] = [
                        'name' => 'Desconsolidación',
                        'description' => 'Gestión de títulos madre y títulos hijos en proceso de desconsolidación.',
                        'methods' => ['RegistrarDesconsolidados', 'ConsultarEstado'],
                        'country' => $company->country,
                        'requires_certificate' => true,
                    ];
                    break;

                case 'Transbordos':
                    $webservices['transbordos'] = [
                        'name' => 'Transbordos',
                        'description' => 'Gestión de barcazas y tracking de posición para transbordos.',
                        'methods' => ['RegistrarTransbordo', 'ActualizarPosicion'],
                        'country' => $company->country,
                        'requires_certificate' => true,
                    ];
                    break;
            }
        }

        return $webservices;
    }

    /**
     * Obtener estado general de webservices.
     */
    private function getWebserviceStatus(Company $company): array
    {
        $certStatus = $this->getCertificateStatus($company);

        return [
            'operational' => $company->ws_active &&
                           $certStatus['has_certificate'] &&
                           !$certStatus['is_expired'],
            'environment' => $company->ws_environment ?? 'testing',
            'can_use_production' => $company->ws_environment === 'production' &&
                                   $certStatus['has_certificate'] &&
                                   !$certStatus['is_expired'],
            'last_test' => null, // TODO: Implementar cuando tengamos logs
            'active_connections' => 0, // TODO: Implementar cuando tengamos logs
            'blocked_reason' => $this->getBlockedReason($company, $certStatus),
        ];
    }

    /**
     * Obtener configuración por defecto según país.
     */
    private function getDefaultWebserviceConfig(string $country): array
    {
        if ($country === 'AR') {
            return [
                'testing' => [
                    'anticipada' => 'https://wsaduhomoext.afip.gob.ar/DIAV2/wgesinformacionanticipada/wgesinformacionanticipada.asmx',
                    'micdta' => 'https://wsaduhomoext.afip.gob.ar/DIAV2/wgesregsintia2/wgesregsintia2.asmx',
                    'auth' => 'https://wsaahomo.afip.gov.ar/ws/services/LoginCms',
                ],
                'production' => [
                    'anticipada' => 'https://webservicesadu.afip.gob.ar/DIAV2/wgesinformacionanticipada/wgesinformacionanticipada.asmx',
                    'micdta' => 'https://webservicesadu.afip.gob.ar/DIAV2/wgesregsintia2/wgesregsintia2.asmx',
                    'auth' => 'https://wsaa.afip.gov.ar/ws/services/LoginCms',
                ],
            ];
        } else { // Paraguay
            return [
                'testing' => [
                    'transbordos' => 'https://securetest.aduana.gov.py/wsdl/gdsf/serviciogdsf',
                    'auth' => 'https://securetest.aduana.gov.py/wsdl/wsaaserver/Server',
                ],
                'production' => [
                    'transbordos' => 'https://secure.aduana.gov.py/wsdl/gdsf/serviciogdsf',
                    'auth' => 'https://secure.aduana.gov.py/wsdl/wsaaserver/Server',
                ],
            ];
        }
    }

    /**
     * Obtener razón por la cual los webservices están bloqueados.
     */
    private function getBlockedReason(Company $company, array $certStatus): ?string
    {
        if (!$company->ws_active) {
            return 'Webservices desactivados por el administrador';
        }

        if (!$certStatus['has_certificate']) {
            return 'Certificado digital requerido';
        }

        if ($certStatus['is_expired']) {
            return 'Certificado digital vencido';
        }

        if (!$company->active) {
            return 'Empresa inactiva';
        }

        return null;
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
        ];

        if ($company->certificate_expires_at) {
            $expiresAt = Carbon::parse($company->certificate_expires_at);
            $now = Carbon::now();

            $status['is_expired'] = $expiresAt->isPast();
            $status['expires_soon'] = !$status['is_expired'] && $expiresAt->diffInDays($now) <= 30;
        }

        return $status;
    }

    /**
     * Obtener detalles específicos de un webservice.
     */
    private function getWebserviceDetails(Company $company, string $webservice): array
    {
        $availableWS = $this->getAvailableWebservices($company);

        if (!isset($availableWS[$webservice])) {
            abort(404, "Webservice '{$webservice}' no encontrado.");
        }

        $config = $this->getWebserviceConfiguration($company);
        $defaultUrls = $config['default_urls'];

        $currentUrl = null;
        if (isset($defaultUrls[$config['environment']][$webservice])) {
            $currentUrl = $defaultUrls[$config['environment']][$webservice];
        }

        return [
            'info' => $availableWS[$webservice],
            'current_url' => $currentUrl,
            'environment' => $config['environment'],
            'last_connection' => null, // TODO: Implementar logs
            'total_requests' => 0, // TODO: Implementar logs
            'success_rate' => 0, // TODO: Implementar logs
            'avg_response_time' => 0, // TODO: Implementar logs
        ];
    }

    /**
     * Realizar prueba de conexión a webservice.
     */
    private function performConnectionTest(Company $company, string $webservice): array
    {
        $config = $this->getWebserviceConfiguration($company);
        $defaultUrls = $config['default_urls'];

        if (!isset($defaultUrls[$config['environment']][$webservice])) {
            throw new \Exception("URL no configurada para webservice '{$webservice}' en entorno '{$config['environment']}'");
        }

        $url = $defaultUrls[$config['environment']][$webservice];
        $timeout = $config['timeout'];

        $startTime = microtime(true);

        try {
            // Test básico de conectividad (WSDL)
            $response = Http::timeout($timeout)->get($url . '?wsdl');

            $endTime = microtime(true);
            $responseTime = round(($endTime - $startTime) * 1000, 2);

            if ($response->successful()) {
                return [
                    'status' => 'success',
                    'message' => 'Conexión exitosa al webservice',
                    'url' => $url,
                    'response_time' => $responseTime . ' ms',
                    'environment' => $config['environment'],
                ];
            } else {
                return [
                    'status' => 'error',
                    'message' => 'Error HTTP: ' . $response->status(),
                    'url' => $url,
                    'response_time' => $responseTime . ' ms',
                ];
            }

        } catch (\Exception $e) {
            $endTime = microtime(true);
            $responseTime = round(($endTime - $startTime) * 1000, 2);

            return [
                'status' => 'error',
                'message' => 'Error de conexión: ' . $e->getMessage(),
                'url' => $url,
                'response_time' => $responseTime . ' ms',
            ];
        }
    }

    /**
     * Obtener logs de conexión (placeholder para implementación futura).
     */
    private function getConnectionLogs(Company $company, string $webservice): array
    {
        // TODO: Implementar sistema de logs cuando esté disponible
        return [
            'recent' => [],
            'summary' => [
                'total_requests' => 0,
                'successful_requests' => 0,
                'failed_requests' => 0,
                'avg_response_time' => 0,
            ],
        ];
    }

    /**
    * Implementación del procesamiento de envíos de manifiestos usando
    * servicios webservices existentes y datos reales del sistema.
    * 
    * Integra con:
    * - ArgentinaAnticipatedService (Información Anticipada)
    * - ArgentinaMicDtaService (MIC/DTA)
    * - ArgentinaTransshipmentService (Transbordos)
    * - ArgentinaDeconsolidationService (Desconsolidados)
    * - ParaguayCustomsService (Paraguay Customs)
    * 
    * Datos reales soportados:
    * - Company: MAERSK LINE ARGENTINA S.A. (tax_id: 30123456789)
    * - Roles: ["Cargas", "Desconsolidador", "Transbordos"]
    * - CSV data: PARANA.csv con 253 registros reales
    */

    /**
     * Procesar envío de manifiestos a webservices aduaneros
     * 
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function processSend(Request $request)
    {
        // 1. Validación básica de permisos
        if (!$this->canPerform('manage_webservices') && !$this->hasRole('user')) {
            abort(403, 'No tiene permisos para enviar manifiestos.');
        }

        $company = $this->getUserCompany();
        if (!$company) {
            return redirect()->route('company.webservices.index')
                ->with('error', 'No se encontró la empresa asociada.');
        }

        try {
            // 2. Validación de entrada
            $validated = $request->validate([
                'webservice_type' => 'required|string|in:anticipada,micdta,desconsolidados,transbordos,paraguay',
                'country' => 'required|string|in:AR,PY',
                'environment' => 'required|string|in:testing,production',
                'data_source' => 'required|string|in:voyage_id,shipment_id,manual',
                'voyage_id' => 'nullable|integer|exists:voyages,id',
                'shipment_id' => 'nullable|integer|exists:shipments,id',
                'manual_data' => 'nullable|array',
                'send_immediately' => 'boolean',
            ]);

            // 3. Verificar roles de empresa vs tipo de webservice
            $companyRoles = $company->company_roles ?? [];
            if (!$this->canUseWebserviceType($validated['webservice_type'], $companyRoles)) {
                return redirect()->back()
                    ->with('error', "Su empresa no tiene permisos para el webservice: {$validated['webservice_type']}");
            }

            // 4. Generar ID único de transacción
            $transactionId = $this->generateTransactionId($company->id, $validated['webservice_type']);
            
            // 5. Obtener datos para el envío
            $sendData = $this->prepareSendData($validated, $company);
            
            // 6. Crear registro de transacción
            $transaction = $this->createWebserviceTransaction([
                'company_id' => $company->id,
                'user_id' => Auth::id(),
                'transaction_id' => $transactionId,
                'webservice_type' => $validated['webservice_type'],
                'country' => $validated['country'],
                'environment' => $validated['environment'],
                'status' => 'pending',
                'voyage_id' => $validated['voyage_id'] ?? null,
                'shipment_id' => $validated['shipment_id'] ?? null,
                'additional_metadata' => [
                    'data_source' => $validated['data_source'],
                    'send_immediately' => $validated['send_immediately'] ?? false,
                    'company_roles' => $companyRoles,
                    'request_ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ],
            ]);

            // 7. Log inicio del proceso
            $this->logWebserviceOperation('info', 'Inicio de procesamiento de envío', [
                'transaction_id' => $transaction->id,
                'webservice_type' => $validated['webservice_type'],
                'company_id' => $company->id,
                'company_name' => $company->legal_name,
            ]);

            // 8. Procesar según tipo de webservice
            $result = $this->processWebserviceByType($transaction, $sendData, $validated);

            // 9. Actualizar estado de transacción
            $transaction->update([
                'status' => $result['success'] ? 'success' : 'error',
                'response_at' => now(),
                'confirmation_number' => $result['confirmation_number'] ?? null,
                'error_code' => $result['error_code'] ?? null,
                'error_message' => $result['error_message'] ?? null,
                'success_data' => $result['success_data'] ?? null,
            ]);

            // 10. Respuesta al usuario
            if ($result['success']) {
                return redirect()->route('company.webservices.index')
                    ->with('success', $result['message'])
                    ->with('transaction_id', $transactionId)
                    ->with('confirmation_number', $result['confirmation_number']);
            } else {
                return redirect()->route('company.webservices.send', ['type' => $validated['webservice_type']])
                    ->with('error', $result['message'])
                    ->with('error_details', $result['error_details'] ?? null)
                    ->withInput();
            }

        } catch (ValidationException $e) {
            return redirect()->back()
                ->withErrors($e->errors())
                ->withInput();
                
        } catch (Exception $e) {
            // Log error crítico
            Log::error('Error crítico en processSend', [
                'company_id' => $company->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->route('company.webservices.index')
                ->with('error', 'Error interno del sistema. Por favor contacte al administrador.')
                ->with('error_code', 'INTERNAL_ERROR');
        }
    }

    /**
     * Verificar si empresa puede usar tipo de webservice
     */
    private function canUseWebserviceType(string $webserviceType, array $companyRoles): bool
    {
        $requiredRoles = [
            'anticipada' => ['Cargas'],
            'micdta' => ['Cargas'],
            'desconsolidados' => ['Desconsolidador'],
            'transbordos' => ['Transbordos'],
            'paraguay' => ['Cargas', 'Desconsolidador', 'Transbordos'], // Cualquiera
        ];

        $required = $requiredRoles[$webserviceType] ?? [];
        return empty($required) || !empty(array_intersect($companyRoles, $required));
    }

    /**
     * Generar ID único de transacción
     */
    private function generateTransactionId(int $companyId, string $webserviceType): string
    {
        $prefix = strtoupper(substr($webserviceType, 0, 3));
        $timestamp = now()->format('YmdHis');
        $random = strtoupper(Str::random(4));
        
        return "{$prefix}-{$companyId}-{$timestamp}-{$random}";
    }

    /**
     * Preparar datos para envío basado en fuente
     */
    private function prepareSendData(array $validated, Company $company): array
    {
        $data = [
            'company' => $company,
            'webservice_type' => $validated['webservice_type'],
            'country' => $validated['country'],
            'environment' => $validated['environment'],
        ];

        switch ($validated['data_source']) {
            case 'voyage_id':
                if ($validated['voyage_id']) {
                    $voyage = Voyage::with(['shipments', 'vessel', 'ports'])->find($validated['voyage_id']);
                    $data['voyage'] = $voyage;
                    $data['shipments'] = $voyage->shipments ?? collect();
                }
                break;
                
            case 'shipment_id':
                if ($validated['shipment_id']) {
                    $shipment = Shipment::with(['voyage', 'containers', 'billsOfLading'])->find($validated['shipment_id']);
                    $data['shipment'] = $shipment;
                    $data['voyage'] = $shipment->voyage ?? null;
                }
                break;
                
            case 'manual':
                $data['manual_data'] = $validated['manual_data'] ?? [];
                break;
        }

        return $data;
    }

    /**
     * Crear registro de transacción webservice
     */
    private function createWebserviceTransaction(array $data): WebserviceTransaction
    {
        return WebserviceTransaction::create(array_merge($data, [
            'retry_count' => 0,
            'max_retries' => 3,
            'currency_code' => 'USD',
            'container_count' => 0,
            'bill_of_lading_count' => 0,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]));
    }

    /**
     * Log de operaciones webservice
     */
private function logWebserviceOperation(string $level, string $message, array $context = []): void
{
    // Log en archivo Laravel (siempre)
    Log::{$level}($message, $context);
    
    // Log en tabla solo si hay transaction_id
    if (isset($context['transaction_id']) && $context['transaction_id']) {
        try {
            WebserviceLog::create([
                'transaction_id' => $context['transaction_id'],
                'level' => $level,
                'message' => $message,
                'category' => 'webservice_controller',
                'subcategory' => 'process_send',
                'context' => array_merge($context, [
                    'client_ip' => request()->ip(),
                    'client_user_agent' => request()->userAgent(),
                ]),
                'environment' => app()->environment() === 'production' ? 'production' : 'testing',
            ]);
        } catch (Exception $e) {
            Log::error('Error logging to webservice_logs table', [
                'original_message' => $message,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

    /**
     * Procesar webservice según tipo específico
     * 
     * @param WebserviceTransaction $transaction
     * @param array $sendData
     * @param array $validated
     * @return array
     */
    private function processWebserviceByType(WebserviceTransaction $transaction, array $sendData, array $validated): array
    {
        $user = Auth::user();
        $company = $sendData['company'];
        
        try {
            // Log inicio del procesamiento
            $this->logWebserviceOperation('info', 'Iniciando procesamiento por tipo', [
                'transaction_id' => $transaction->id,
                'webservice_type' => $validated['webservice_type'],
                'country' => $validated['country'],
                'environment' => $validated['environment'],
            ]);

            $result = match($validated['webservice_type']) {
                'anticipada' => $this->processArgentinaAnticipated($transaction, $sendData, $user),
                'micdta' => $this->processArgentinaMicDta($transaction, $sendData, $user),
                'desconsolidados' => $this->processArgentinaDeconsolidation($transaction, $sendData, $user),
                'transbordos' => $this->processArgentinaTransshipment($transaction, $sendData, $user),
                'paraguay' => $this->processParaguayCustoms($transaction, $sendData, $user),
                default => throw new Exception("Tipo de webservice no soportado: {$validated['webservice_type']}")
            };

            return $result;

        } catch (Exception $e) {
            $this->logWebserviceOperation('error', 'Error en processWebserviceByType', [
                'transaction_id' => $transaction->id,
                'webservice_type' => $validated['webservice_type'],
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'Error procesando webservice: ' . $e->getMessage(),
                'error_code' => 'PROCESSING_ERROR',
                'error_details' => [
                    'type' => get_class($e),
                    'message' => $e->getMessage(),
                    'line' => $e->getLine(),
                ]
            ];
        }
    }

    /**
     * Procesar Información Anticipada Argentina
     */
    private function processArgentinaAnticipated(WebserviceTransaction $transaction, array $sendData, User $user): array
    {
        try {
            $service = new ArgentinaAnticipatedService($sendData['company'], $user);
            $service->setEnvironment($sendData['environment']);

            // Validar datos requeridos
            if (!isset($sendData['voyage'])) {
                throw new Exception('Se requiere un viaje para enviar información anticipada');
            }

            $voyage = $sendData['voyage'];
            
            // Actualizar transacción con datos del viaje
            $transaction->update([
                'voyage_id' => $voyage->id,
                'webservice_url' => $service->getWebserviceUrl(),
                'status' => 'sending',
                'sent_at' => now(),
            ]);

            $this->logWebserviceOperation('info', 'Enviando información anticipada', [
                'transaction_id' => $transaction->id,
                'voyage_id' => $voyage->id,
                'voyage_code' => $voyage->voyage_code,
                'vessel_name' => $voyage->vessel->name ?? 'N/A',
            ]);

            // Enviar usando el servicio
            $response = $service->sendVoyageData($voyage, $transaction->transaction_id);

            if ($response['success']) {
                return [
                    'success' => true,
                    'message' => 'Información anticipada enviada exitosamente',
                    'confirmation_number' => $response['confirmation_number'] ?? null,
                    'success_data' => $response['data'] ?? null,
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Error en el webservice de información anticipada: ' . ($response['error_message'] ?? 'Error desconocido'),
                    'error_code' => $response['error_code'] ?? 'WEBSERVICE_ERROR',
                    'error_details' => $response['error_details'] ?? null,
                ];
            }

        } catch (Exception $e) {
            $this->logWebserviceOperation('error', 'Error en información anticipada', [
                'transaction_id' => $transaction->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Error procesando información anticipada: ' . $e->getMessage(),
                'error_code' => 'ANTICIPADA_ERROR',
            ];
        }
    }

    /**
     * Procesar MIC/DTA Argentina
     */
    private function processArgentinaMicDta(WebserviceTransaction $transaction, array $sendData, User $user): array
    {
        try {
            $service = new ArgentinaMicDtaService($sendData['company'], $user);
            $service->setEnvironment($sendData['environment']);

            // Validar datos requeridos
            if (!isset($sendData['shipment']) && !isset($sendData['voyage'])) {
                throw new Exception('Se requiere un embarque o viaje para registrar MIC/DTA');
            }

            $shipment = $sendData['shipment'] ?? null;
            $voyage = $sendData['voyage'] ?? $shipment->voyage ?? null;

            if (!$voyage) {
                throw new Exception('No se pudo determinar el viaje para el MIC/DTA');
            }

            // Actualizar transacción
            $transaction->update([
                'voyage_id' => $voyage->id,
                'shipment_id' => $shipment->id ?? null,
                'webservice_url' => $service->getWebserviceUrl(),
                'status' => 'sending',
                'sent_at' => now(),
            ]);

            $this->logWebserviceOperation('info', 'Enviando MIC/DTA', [
                'transaction_id' => $transaction->id,
                'voyage_id' => $voyage->id,
                'shipment_id' => $shipment->id ?? null,
            ]);

            // Enviar usando el servicio
            if ($shipment) {
                $response = $service->registerShipment($shipment, $transaction->transaction_id);
            } else {
                $response = $service->registerVoyage($voyage, $transaction->transaction_id);
            }

            if ($response['success']) {
                return [
                    'success' => true,
                    'message' => 'MIC/DTA registrado exitosamente',
                    'confirmation_number' => $response['confirmation_number'] ?? null,
                    'success_data' => $response['data'] ?? null,
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Error en el registro MIC/DTA: ' . ($response['error_message'] ?? 'Error desconocido'),
                    'error_code' => $response['error_code'] ?? 'MICDTA_ERROR',
                    'error_details' => $response['error_details'] ?? null,
                ];
            }

        } catch (Exception $e) {
            $this->logWebserviceOperation('error', 'Error en MIC/DTA', [
                'transaction_id' => $transaction->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Error procesando MIC/DTA: ' . $e->getMessage(),
                'error_code' => 'MICDTA_ERROR',
            ];
        }
    }

    /**
     * Procesar Desconsolidados Argentina
     */
    private function processArgentinaDeconsolidation(WebserviceTransaction $transaction, array $sendData, User $user): array
    {
        try {
            $service = new ArgentinaDeconsolidationService($sendData['company'], $user);
            $service->setEnvironment($sendData['environment']);

            // Validar datos requeridos
            if (!isset($sendData['shipment'])) {
                throw new Exception('Se requiere un embarque para procesar desconsolidados');
            }

            $shipment = $sendData['shipment'];

            // Actualizar transacción
            $transaction->update([
                'shipment_id' => $shipment->id,
                'voyage_id' => $shipment->voyage_id ?? null,
                'webservice_url' => $service->getWebserviceUrl(),
                'status' => 'sending',
                'sent_at' => now(),
            ]);

            $this->logWebserviceOperation('info', 'Enviando desconsolidados', [
                'transaction_id' => $transaction->id,
                'shipment_id' => $shipment->id,
                'bl_number' => $shipment->bl_number ?? 'N/A',
            ]);

            // Enviar usando el servicio
            $response = $service->processDeconsolidation($shipment, $transaction->transaction_id);

            if ($response['success']) {
                return [
                    'success' => true,
                    'message' => 'Desconsolidados procesados exitosamente',
                    'confirmation_number' => $response['confirmation_number'] ?? null,
                    'success_data' => $response['data'] ?? null,
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Error procesando desconsolidados: ' . ($response['error_message'] ?? 'Error desconocido'),
                    'error_code' => $response['error_code'] ?? 'DECONSOLIDATION_ERROR',
                    'error_details' => $response['error_details'] ?? null,
                ];
            }

        } catch (Exception $e) {
            $this->logWebserviceOperation('error', 'Error en desconsolidados', [
                'transaction_id' => $transaction->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Error procesando desconsolidados: ' . $e->getMessage(),
                'error_code' => 'DECONSOLIDATION_ERROR',
            ];
        }
    }

    /**
     * Procesar Transbordos Argentina
     */
    private function processArgentinaTransshipment(WebserviceTransaction $transaction, array $sendData, User $user): array
    {
        try {
            $service = new ArgentinaTransshipmentService($sendData['company'], $user);
            $service->setEnvironment($sendData['environment']);

            // Validar datos requeridos
            if (!isset($sendData['voyage'])) {
                throw new Exception('Se requiere un viaje para procesar transbordos');
            }

            $voyage = $sendData['voyage'];

            // Actualizar transacción
            $transaction->update([
                'voyage_id' => $voyage->id,
                'webservice_url' => $service->getWebserviceUrl(),
                'status' => 'sending',
                'sent_at' => now(),
            ]);

            $this->logWebserviceOperation('info', 'Enviando transbordos', [
                'transaction_id' => $transaction->id,
                'voyage_id' => $voyage->id,
                'vessel_type' => $voyage->vessel->vessel_type ?? 'N/A',
            ]);

            // Enviar usando el servicio
            $response = $service->processTransshipment($voyage, $transaction->transaction_id);

            if ($response['success']) {
                return [
                    'success' => true,
                    'message' => 'Transbordos procesados exitosamente',
                    'confirmation_number' => $response['confirmation_number'] ?? null,
                    'success_data' => $response['data'] ?? null,
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Error procesando transbordos: ' . ($response['error_message'] ?? 'Error desconocido'),
                    'error_code' => $response['error_code'] ?? 'TRANSSHIPMENT_ERROR',
                    'error_details' => $response['error_details'] ?? null,
                ];
            }

        } catch (Exception $e) {
            $this->logWebserviceOperation('error', 'Error en transbordos', [
                'transaction_id' => $transaction->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Error procesando transbordos: ' . $e->getMessage(),
                'error_code' => 'TRANSSHIPMENT_ERROR',
            ];
        }
    }

    /**
     * Procesar Paraguay Customs
     */
    private function processParaguayCustoms(WebserviceTransaction $transaction, array $sendData, User $user): array
    {
        try {
            $service = new ParaguayCustomsService($sendData['company'], $user);
            $service->setEnvironment($sendData['environment']);

            // Determinar datos a enviar
            $voyage = $sendData['voyage'] ?? null;
            $shipment = $sendData['shipment'] ?? null;

            if (!$voyage && !$shipment) {
                throw new Exception('Se requiere un viaje o embarque para Paraguay');
            }

            // Actualizar transacción
            $transaction->update([
                'voyage_id' => $voyage->id ?? $shipment->voyage_id ?? null,
                'shipment_id' => $shipment->id ?? null,
                'webservice_url' => $service->getWebserviceUrl(),
                'status' => 'sending',
                'sent_at' => now(),
            ]);

            $this->logWebserviceOperation('info', 'Enviando a Paraguay', [
                'transaction_id' => $transaction->id,
                'voyage_id' => $voyage->id ?? null,
                'shipment_id' => $shipment->id ?? null,
            ]);

            // Enviar usando el servicio
            if ($shipment) {
                $response = $service->sendShipmentData($shipment, $transaction->transaction_id);
            } else {
                $response = $service->sendVoyageData($voyage, $transaction->transaction_id);
            }

            if ($response['success']) {
                return [
                    'success' => true,
                    'message' => 'Datos enviados a Paraguay exitosamente',
                    'confirmation_number' => $response['confirmation_number'] ?? null,
                    'success_data' => $response['data'] ?? null,
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Error enviando a Paraguay: ' . ($response['error_message'] ?? 'Error desconocido'),
                    'error_code' => $response['error_code'] ?? 'PARAGUAY_ERROR',
                    'error_details' => $response['error_details'] ?? null,
                ];
            }

        } catch (Exception $e) {
            $this->logWebserviceOperation('error', 'Error en Paraguay', [
                'transaction_id' => $transaction->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Error enviando a Paraguay: ' . $e->getMessage(),
                'error_code' => 'PARAGUAY_ERROR',
            ];
        }
    }

        

    /**
     * Obtener viajes pendientes de envío para la empresa
     * 
     * @param Company $company
     * @return \Illuminate\Database\Eloquent\Collection
     */
    private function getPendingTrips(Company $company): \Illuminate\Database\Eloquent\Collection
    {
        try {
            // Obtener viajes de la empresa que no han sido enviados a webservices
           $trips = Voyage::with(['vessel', 'shipments', 'ports'])
                ->whereHas('shipments', function($query) use ($company) {
                    $query->where('company_id', $company->id);
                })
                ->where('status', '!=', 'completed')
                ->whereNotExists(function($query) {
                    $query->select(\DB::raw(1))
                        ->from('webservice_transactions')
                        ->whereColumn('webservice_transactions.voyage_id', 'voyages.id')
                        ->where('webservice_transactions.status', 'success');
                })
                ->orderBy('departure_date', 'asc')
                ->limit(20)
                ->get();

            // Log para monitoreo
            $this->logWebserviceOperation('info', 'Viajes pendientes obtenidos', [
                'company_id' => $company->id,
                'trips_count' => $trips->count(),
            ]);

            return $trips;

        } catch (Exception $e) {
            Log::error('Error obteniendo viajes pendientes', [
                'company_id' => $company->id,
                'error' => $e->getMessage(),
            ]);
            
            return collect();
        }
    }

    /**
     * Obtener embarcaciones de la empresa
     * 
     * @param Company $company
     * @return \Illuminate\Database\Eloquent\Collection
     */
    private function getCompanyVessels(Company $company): \Illuminate\Database\Eloquent\Collection
    {
        try {
            $vessels = \App\Models\Vessel::where('company_id', $company->id)
                ->where('active', true)
                ->with(['vesselType', 'currentPort'])
                ->orderBy('name', 'asc')
                ->get();

            $this->logWebserviceOperation('info', 'Embarcaciones obtenidas', [
                'company_id' => $company->id,
                'vessels_count' => $vessels->count(),
            ]);

            return $vessels;

        } catch (Exception $e) {
            Log::error('Error obteniendo embarcaciones', [
                'company_id' => $company->id,
                'error' => $e->getMessage(),
            ]);
            
            return collect();
        }
    }



    /**
     * Obtener tipos de webservices disponibles según roles de empresa
     * 
     * @param array $companyRoles
     * @return array
     */
    private function getAvailableWebserviceTypes(array $companyRoles): array
    {
        $allTypes = [
            'anticipada' => [
                'name' => 'Información Anticipada',
                'description' => 'Registro anticipado de viajes y manifiestos',
                'country' => 'Argentina',
                'required_roles' => ['Cargas'],
            ],
            'micdta' => [
                'name' => 'MIC/DTA',
                'description' => 'Registro de MIC/DTA para remolcadores',
                'country' => 'Argentina',
                'required_roles' => ['Cargas'],
            ],
            'desconsolidados' => [
                'name' => 'Desconsolidados',
                'description' => 'Gestión de títulos madre/hijo',
                'country' => 'Argentina',
                'required_roles' => ['Desconsolidador'],
            ],
            'transbordos' => [
                'name' => 'Transbordos',
                'description' => 'División de cargas y barcazas',
                'country' => 'Argentina',
                'required_roles' => ['Transbordos'],
            ],
            'paraguay' => [
                'name' => 'Paraguay Customs',
                'description' => 'Webservices aduaneros de Paraguay',
                'country' => 'Paraguay',
                'required_roles' => ['Cargas', 'Desconsolidador', 'Transbordos'],
            ],
        ];

        $availableTypes = [];
        
        foreach ($allTypes as $type => $config) {
            if (empty($config['required_roles']) || !empty(array_intersect($companyRoles, $config['required_roles']))) {
                $availableTypes[] = $type;
            }
        }

        return $availableTypes;
    }

    /**
     * Obtener estadísticas de transacciones webservice para la empresa
     * 
     * @param Company $company
     * @return array
     */
    private function getWebserviceStatistics(Company $company): array
    {
        $stats = [
            'anticipada' => ['total' => 0, 'success' => 0, 'failed' => 0, 'pending' => 0],
            'micdta' => ['total' => 0, 'success' => 0, 'failed' => 0, 'pending' => 0],
            'desconsolidados' => ['total' => 0, 'success' => 0, 'failed' => 0, 'pending' => 0],
            'transbordos' => ['total' => 0, 'success' => 0, 'failed' => 0, 'pending' => 0],
            'paraguay' => ['total' => 0, 'success' => 0, 'failed' => 0, 'pending' => 0],
            'total_transactions' => 0,
            'success_rate' => 0.0,
            'last_24h' => 0,
        ];

        try {
            $transactions = WebserviceTransaction::where('company_id', $company->id)
                ->where('created_at', '>=', now()->subDays(30))
                ->get();

            foreach ($transactions as $transaction) {
                $type = $transaction->webservice_type;
                
                if (isset($stats[$type])) {
                    $stats[$type]['total']++;
                    
                    switch ($transaction->status) {
                        case 'success':
                            $stats[$type]['success']++;
                            break;
                        case 'error':
                        case 'expired':
                            $stats[$type]['failed']++;
                            break;
                        case 'pending':
                        case 'sending':
                        case 'retry':
                            $stats[$type]['pending']++;
                            break;
                    }
                }
            }

            // Calcular totales
            $stats['total_transactions'] = $transactions->count();
            $totalSuccess = collect($stats)->sum('success');
            
            if ($stats['total_transactions'] > 0) {
                $stats['success_rate'] = round(($totalSuccess / $stats['total_transactions']) * 100, 2);
            }

            // Transacciones últimas 24h
            $stats['last_24h'] = $transactions->where('created_at', '>=', now()->subDay())->count();

            $this->logWebserviceOperation('info', 'Estadísticas obtenidas', [
                'company_id' => $company->id,
                'total_transactions' => $stats['total_transactions'],
                'success_rate' => $stats['success_rate'],
            ]);

        } catch (Exception $e) {
            Log::error('Error obteniendo estadísticas webservice', [
                'company_id' => $company->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $stats;
    }

    /**
     * Obtener transacciones recientes para mostrar en dashboard
     * 
     * @param Company $company
     * @param int $limit
     * @return \Illuminate\Database\Eloquent\Collection
     */
    private function getRecentTransactions(Company $company, int $limit = 10): \Illuminate\Database\Eloquent\Collection
    {
        try {
            return WebserviceTransaction::where('company_id', $company->id)
                ->with(['user', 'voyage', 'shipment'])
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get();

        } catch (Exception $e) {
            Log::error('Error obteniendo transacciones recientes', [
                'company_id' => $company->id,
                'error' => $e->getMessage(),
            ]);
            
            return collect();
        }
    }

    /**
     * Validar datos antes del envío a webservice
     * 
     * @param array $sendData
     * @param string $webserviceType
     * @return array
     */
    private function validateWebserviceData(array $sendData, string $webserviceType): array
    {
        $errors = [];
        $company = $sendData['company'];

        // Validaciones comunes
        if (!$company) {
            $errors[] = 'Empresa requerida';
        }

        if (!$this->getCertificateStatus($company)['certificate_valid']) {
            $errors[] = 'Certificado digital inválido o expirado';
        }

        // Validaciones específicas por tipo
        switch ($webserviceType) {
            case 'anticipada':
                if (!isset($sendData['voyage'])) {
                    $errors[] = 'Viaje requerido para información anticipada';
                } elseif (!$sendData['voyage']->vessel) {
                    $errors[] = 'Embarcación requerida para el viaje';
                }
                break;

            case 'micdta':
                if (!isset($sendData['voyage']) && !isset($sendData['shipment'])) {
                    $errors[] = 'Viaje o embarque requerido para MIC/DTA';
                }
                break;

            case 'desconsolidados':
                if (!isset($sendData['shipment'])) {
                    $errors[] = 'Embarque requerido para desconsolidados';
                } elseif (!$sendData['shipment']->containers || $sendData['shipment']->containers->isEmpty()) {
                    $errors[] = 'Contenedores requeridos para desconsolidados';
                }
                break;

            case 'transbordos':
                if (!isset($sendData['voyage'])) {
                    $errors[] = 'Viaje requerido para transbordos';
                }
                break;

            case 'paraguay':
                if (!isset($sendData['voyage']) && !isset($sendData['shipment'])) {
                    $errors[] = 'Viaje o embarque requerido para Paraguay';
                }
                break;
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Formatear respuesta para mostrar al usuario
     * 
     * @param array $result
     * @param string $webserviceType
     * @return array
     */
    private function formatUserResponse(array $result, string $webserviceType): array
    {
        $typeNames = [
            'anticipada' => 'Información Anticipada',
            'micdta' => 'MIC/DTA',
            'desconsolidados' => 'Desconsolidados',
            'transbordos' => 'Transbordos',
            'paraguay' => 'Paraguay Customs',
        ];

        $typeName = $typeNames[$webserviceType] ?? ucfirst($webserviceType);

        if ($result['success']) {
            $message = "✅ {$typeName} enviado exitosamente";
            if (isset($result['confirmation_number'])) {
                $message .= " - Confirmación: {$result['confirmation_number']}";
            }
        } else {
            $message = "❌ Error enviando {$typeName}: " . ($result['message'] ?? 'Error desconocido');
        }

        return array_merge($result, ['formatted_message' => $message]);
    }

    /**
     * Obtener configuración de ambientes disponibles
     * 
     * @param Company $company
     * @return array
     */
    private function getAvailableEnvironments(Company $company): array
    {
        $webserviceUrls = $company->webservice_urls ?? [];
        
        return [
            'testing' => [
                'available' => !empty($webserviceUrls['testing'] ?? []),
                'name' => 'Testing/Homologación',
                'description' => 'Ambiente de pruebas',
            ],
            'production' => [
                'available' => !empty($webserviceUrls['production'] ?? []),
                'name' => 'Producción',
                'description' => 'Ambiente productivo',
            ],
        ];
    }
}
