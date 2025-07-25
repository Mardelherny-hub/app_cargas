<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Traits\UserHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;

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
        
        // Estadísticas mock (temporal)
        $stats = [
            'anticipada' => ['total' => 0, 'success' => 0, 'failed' => 0],
            'micdta' => ['total' => 0, 'success' => 0, 'failed' => 0],
            'desconsolidados' => ['total' => 0, 'success' => 0, 'failed' => 0],
            'transbordos' => ['total' => 0, 'success' => 0, 'failed' => 0],
            'paraguay' => ['total' => 0, 'success' => 0, 'failed' => 0],
        ];
        
        $recentTransactions = collect(); // Temporal vacío

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
     * Obtener tipos de webservice disponibles según roles
     */
    private function getAvailableWebserviceTypes(array $companyRoles): array
    {
        $types = [];
        
        if (in_array('Cargas', $companyRoles)) {
            $types[] = 'anticipada';
            $types[] = 'micdta';
        }
        
        if (in_array('Desconsolidador', $companyRoles)) {
            $types[] = 'desconsolidados';
        }
        
        if (in_array('Transbordos', $companyRoles)) {
            $types[] = 'transbordos';
        }
        
        return $types;
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

    /**
     * Helpers temporales (implementar según modelos existentes)
     */
    private function getPendingTrips(Company $company): array
    {
        // TODO: Implementar con modelos Trip/Voyage reales
        return [];
    }

    private function getCompanyVessels(Company $company): array
    {
        // TODO: Implementar con modelos Vessel reales  
        return [];
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
     * Obtener estadísticas de webservices (placeholder para implementación futura).
     */
    private function getWebserviceStatistics(Company $company): array
    {
        // TODO: Implementar cuando tengamos sistema de logs
        return [
            'total_requests' => 0,
            'requests_today' => 0,
            'requests_this_week' => 0,
            'requests_this_month' => 0,
            'success_rate' => 0,
            'avg_response_time' => 0,
            'most_used_webservice' => null,
            'peak_hours' => [],
        ];
    }
}
