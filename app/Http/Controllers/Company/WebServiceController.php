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
use App\Http\Requests\Company\ImportManifestRequest;
use App\Models\Voyage;
use App\Models\Shipment;
use App\Models\Client;
use App\Models\Vessel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Builder;
use App\Models\User;
use App\Models\WebserviceResponse;
use Exception;

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
 * CORRECCIÓN DEL MÉTODO send() GET en WebServiceController
 * 
 * Agregar/reemplazar este método en:
 * app/Http/Controllers/Company/WebServiceController.php
 */

/**
 * Mostrar formulario de envío de manifiestos
 * CORREGIDO: Datos estructurados correctamente para la vista
 */
public function send(Request $request)
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

    // 2. Obtener tipo de webservice de la URL
    $webserviceType = $request->get('type', 'anticipada');
    
    // 3. Verificar que el tipo sea válido
    $availableTypes = $this->getAvailableWebserviceTypes($company);
    if (!in_array($webserviceType, $availableTypes)) {
        return redirect()->route('company.webservices.index')
            ->with('error', "No tiene permisos para el webservice: {$webserviceType}");
    }

    try {
        // 4. Preparar datos según el tipo de webservice
        $data = $this->prepareFormData($company, $webserviceType);

        return view('company.webservices.send', compact(
            'company',
            'webserviceType', 
            'availableTypes',
            'data'
        ));

    } catch (Exception $e) {
        $this->logWebserviceOperation('error', 'Error cargando formulario de envío', [
            'company_id' => $company->id ?? null,
            'user_id' => Auth::id(),
            'webservice_type' => $webserviceType,
            'error' => $e->getMessage(),
        ]);

        return redirect()->route('company.webservices.index')
            ->with('error', 'Error cargando formulario: ' . $e->getMessage());
    }
}

/**
 * Preparar datos para el formulario según tipo de webservice
 * NUEVO MÉTODO - Estructura datos correctamente para la vista
 */
private function prepareFormData(Company $company, string $webserviceType): array
{
    $data = [];

    switch ($webserviceType) {
        case 'anticipada':
        case 'micdta':
            // Para Información Anticipada y MIC/DTA necesitamos viajes
            $data['voyages'] = $this->getTripsForWebservice($company);
            break;
            
        case 'desconsolidados':
            // Para desconsolidados necesitamos shipments/títulos
            $data['shipments'] = $this->getShipmentsForWebservice($company);
            break;
            
        case 'transbordos':
            // Para transbordos necesitamos barcazas y transfers
            $data['barges'] = $this->getBargesForWebservice($company);
            $data['transfers'] = $this->getTransfersForWebservice($company);
            break;
    }

    return $data;
}

/**
 * Obtener viajes formateados para el select de la vista
 * NUEVO MÉTODO - Estructura datos con campos que espera la vista
 */
private function getTripsForWebservice(Company $company): array
{
    try {
        // Obtener viajes de la empresa con relaciones necesarias
        $voyages = Voyage::with([
                'leadVessel',
                'originPort.country', 
                'destinationPort.country',
                'captain'
            ])
            ->where('company_id', $company->id)
            ->where('active', true)
            ->where('status', '!=', 'cancelled')
            ->orderBy('departure_date', 'desc')
            ->limit(50) // Limitar para performance
            ->get();

        // Si no hay viajes reales, crear datos de ejemplo basados en PARANA.csv
        if ($voyages->isEmpty()) {
            return $this->getExampleTripsData();
        }

        // Formatear datos para que coincidan con lo que espera la vista
        return $voyages->map(function ($voyage) {
            return [
                'id' => $voyage->id,
                'number' => $this->formatVoyageDisplayNumber($voyage),
                'display_text' => $this->formatVoyageDisplayText($voyage),
                'voyage_number' => $voyage->voyage_number,
                'internal_reference' => $voyage->internal_reference,
                'departure_date' => $voyage->departure_date->format('d/m/Y H:i'),
                'status' => $voyage->status,
                'route' => $this->formatVoyageRoute($voyage),
                'vessel_name' => $voyage->leadVessel->name ?? 'Sin embarcación',
                'shipment_count' => $voyage->shipments_count ?? $voyage->shipments->count(),
            ];
        })->toArray();

    } catch (Exception $e) {
        // En caso de error, devolver datos de ejemplo
        $this->logWebserviceOperation('error', 'Error obteniendo viajes', [
            'company_id' => $company->id,
            'error' => $e->getMessage(),
        ]);
        
        return $this->getExampleTripsData();
    }
}

/**
 * Formatear número de viaje para mostrar (lo que espera la vista como 'number')
 */
private function formatVoyageDisplayNumber(Voyage $voyage): string
{
    return $voyage->voyage_number ?: "V{$voyage->id}";
}

/**
 * Formatear texto completo para mostrar en el select
 */
private function formatVoyageDisplayText(Voyage $voyage): string
{
    $number = $this->formatVoyageDisplayNumber($voyage);
    $route = $this->formatVoyageRoute($voyage);
    $date = $voyage->departure_date ? $voyage->departure_date->format('d/m/Y') : 'Sin fecha';
    $vessel = $voyage->leadVessel->name ?? 'Sin embarcación';
    
    return "{$number} | {$route} | {$date} | {$vessel}";
}

/**
 * Formatear ruta del viaje (origen → destino)
 */
private function formatVoyageRoute(Voyage $voyage): string
{
    try {
        $origin = $voyage->originPort->code ?? $voyage->originPort->name ?? 'Origen';
        $destination = $voyage->destinationPort->code ?? $voyage->destinationPort->name ?? 'Destino';
        
        // Si hay transbordo, incluirlo
        if ($voyage->transshipmentPort) {
            $transshipment = $voyage->transshipmentPort->code ?? $voyage->transshipmentPort->name;
            return "{$origin} → {$transshipment} → {$destination}";
        }
        
        return "{$origin} → {$destination}";
    } catch (Exception $e) {
        return 'Ruta no definida';
    }
}

/**
 * Obtener datos de ejemplo cuando no hay viajes reales
 */
private function getExampleTripsData(): array
{
    return [
        [
            'id' => 'example_1',
            'number' => 'V022NB',
            'display_text' => 'V022NB | ARBUE → PYTVT | 25/07/2025 | PAR13001',
            'voyage_number' => 'V022NB',
            'internal_reference' => 'PAR13001',
            'departure_date' => '25/07/2025 08:00',
            'status' => 'planned',
            'route' => 'ARBUE → PYTVT',
            'vessel_name' => 'PAR13001',
            'captain_name' => 'Capitán Ejemplo',
        ],
        [
            'id' => 'example_2', 
            'number' => 'V023NB',
            'display_text' => 'V023NB | ARBUE → PYTVT | 26/07/2025 | GUARAN F',
            'voyage_number' => 'V023NB',
            'internal_reference' => 'GUARAN F',
            'departure_date' => '26/07/2025 09:30',
            'status' => 'planned',
            'route' => 'ARBUE → PYTVT',
            'vessel_name' => 'GUARAN F',
            'captain_name' => 'Capitán Ejemplo 2',
        ],
    ];
}

/**
 * Obtener shipments para desconsolidados
 */
private function getShipmentsForWebservice(Company $company): array
{
    try {
        $shipments = Shipment::with([
                'voyage.originPort',
                'voyage.destinationPort', 
                'vessel',
                'billsOfLading.shipper',
                'billsOfLading.consignee'
            ])
            ->whereHas('voyage', function($query) use ($company) {
                $query->where('company_id', $company->id);
            })
            ->where('active', true)
            ->whereHas('billsOfLading') // Solo shipments con conocimientos
            ->orderBy('created_date', 'desc')
            ->limit(100)
            ->get();

        if ($shipments->isEmpty()) {
            return [
                [
                    'id' => 'example_1',
                    'number' => 'SHIP-001',
                    'display_text' => 'SHIP-001 | PAR13001 | 253 Conocimientos',
                    'vessel_name' => 'PAR13001',
                    'bills_count' => 253,
                    'status' => 'loaded',
                    'voyage_number' => 'V022NB',
                ],
            ];
        }

        return $shipments->map(function ($shipment) {
            $billsCount = $shipment->billsOfLading->count();
            
            return [
                'id' => $shipment->id,
                'number' => $shipment->shipment_number,
                'display_text' => $shipment->shipment_number . ' | ' . ($shipment->vessel->name ?? 'N/A') . ' | ' . $billsCount . ' Conocimientos',
                'vessel_name' => $shipment->vessel->name ?? 'Sin embarcación',
                'bills_count' => $billsCount,
                'status' => $shipment->status,
                'voyage_number' => $shipment->voyage->voyage_number ?? 'N/A',
                'route' => $this->formatShipmentRoute($shipment),
            ];
        })->toArray();

    } catch (Exception $e) {
        Log::error('Error obteniendo shipments para webservice', [
            'company_id' => $company->id,
            'error' => $e->getMessage(),
        ]);
        
        return [];
    }
}

/**
 * Formatear ruta del shipment.
 * MÉTODO AUXILIAR NUEVO
 */
private function formatShipmentRoute(Shipment $shipment): string
{
    try {
        return $this->formatVoyageRoute($shipment->voyage);
    } catch (Exception $e) {
        return 'Ruta no definida';
  
    }
}
/**
 * Obtener barcazas para transbordos
 */
private function getBargesForWebservice(Company $company): array
{
    try {
        // Obtener embarcaciones que han sido usadas como barcazas en transbordos
        $barges = Vessel::whereHas('shipments.voyage', function($query) use ($company) {
                $query->where('company_id', $company->id);
            })
            ->where('vessel_type', 'barge')
            ->where('active', true)
            ->with(['shipments' => function($query) {
                $query->orderBy('created_date', 'desc')->limit(5);
            }])
            ->get();

        if ($barges->isEmpty()) {
            return [
                [
                    'id' => 'example_barge_1',
                    'name' => 'PAR13001',
                    'display_text' => 'PAR13001 | Barcaza | Activa',
                    'type' => 'barge',
                    'status' => 'active',
                    'last_voyage' => 'V022NB',
                ],
                [
                    'id' => 'example_barge_2',
                    'name' => 'GUARAN F',
                    'display_text' => 'GUARAN F | Barcaza | Activa',
                    'type' => 'barge',
                    'status' => 'active',
                    'last_voyage' => 'V023NB',
                ],
            ];
        }

        return $barges->map(function ($barge) {
            $lastShipment = $barge->shipments->first();
            $lastVoyage = $lastShipment ? $lastShipment->voyage->voyage_number : 'N/A';
            
            return [
                'id' => $barge->id,
                'name' => $barge->name,
                'display_text' => "{$barge->name} | {$barge->vessel_type} | {$barge->status}",
                'type' => $barge->vessel_type,
                'status' => $barge->status,
                'last_voyage' => $lastVoyage,
                'capacity_tons' => $barge->cargo_capacity_tons,
                'container_capacity' => $barge->container_capacity,
            ];
        })->toArray();

    } catch (Exception $e) {
        Log::error('Error obteniendo barcazas para webservice', [
            'company_id' => $company->id,
            'error' => $e->getMessage(),
        ]);
        
        return [];
    }
}
/**
 * Obtener transfers para transbordos
 * NUEVO MÉTODO - Estructura datos con campos que espera la vista
**
 * Obtener transfers para transbordos con datos reales.
 * MÉTODO NUEVO
 */
private function getTransfersForWebservice(Company $company): array
{
    try {
        // Obtener transacciones de transbordo recientes
        $transfers = WebserviceTransaction::where('company_id', $company->id)
            ->where('webservice_type', 'transbordos')
            ->with(['voyage', 'user'])
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

        if ($transfers->isEmpty()) {
            return [
                [
                    'id' => 'example_transfer_1',
                    'transaction_id' => 'TRF001',
                    'display_text' => 'TRF001 | ARBUE → PYTVT | Completado',
                    'status' => 'success',
                    'created_at' => now()->subDays(1)->format('d/m/Y H:i'),
                ],
            ];
        }

        return $transfers->map(function ($transfer) {
            $route = 'Ruta no definida';
            if ($transfer->voyage) {
                $route = $this->formatVoyageRoute($transfer->voyage);
            }
            
            return [
                'id' => $transfer->id,
                'transaction_id' => $transfer->transaction_id,
                'display_text' => "{$transfer->transaction_id} | {$route} | " . ucfirst($transfer->status),
                'status' => $transfer->status,
                'created_at' => $transfer->created_at->format('d/m/Y H:i'),
                'voyage_number' => $transfer->voyage->voyage_number ?? 'N/A',
                'user_name' => $transfer->user->name ?? 'Sistema',
            ];
        })->toArray();

    } catch (Exception $e) {
        Log::error('Error obteniendo transfers para webservice', [
            'company_id' => $company->id,
            'error' => $e->getMessage(),
        ]);
        
        return [];
    }
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
                    'voyages' => $this->getPendingTrips($company),
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

    private function getPendingTransfers(Company $company): array
    {
        // TODO: Implementar según modelo Transfer
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
                'transbordos', 'transbordo' => $this->processArgentinaTransshipment($transaction, $sendData, $user), // ← AQUÍ
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
        
        // ✅ CORRECCIÓN: Actualizar transacción SIN llamar al método privado getWebserviceUrl()
        $transaction->update([
            'voyage_id' => $voyage->id,
            // ❌ LÍNEA REMOVIDA: 'webservice_url' => $service->getWebserviceUrl(),
            'status' => 'sending',
            'sent_at' => now(),
        ]);

        $this->logWebserviceOperation('info', 'Enviando información anticipada', [
            'transaction_id' => $transaction->id,
            'voyage_id' => $voyage->id,
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
                //'webservice_url' => $service->getWebserviceUrl(),
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
/**
 * Procesar Transbordos Argentina - VERSIÓN CORREGIDA CON LOGS DEBUG
 * 
 * REEMPLAZAR este método en: app/Http/Controllers/Company/WebServiceController.php
 */
private function processArgentinaTransshipment(WebserviceTransaction $transaction, array $sendData, User $user): array
{
    try {
        Log::info('DEBUG: Entrando processArgentinaTransshipment', [
            'transaction_id' => $transaction->id,
            'voyage_present' => isset($sendData['voyage']),
            'user_id' => $user->id
        ]);

        $service = new ArgentinaTransshipmentService($sendData['company'], $user);
        $service->setEnvironment($sendData['environment']);
        
        Log::info('DEBUG: Service ArgentinaTransshipmentService creado exitosamente');

        // Validar datos requeridos
        if (!isset($sendData['voyage'])) {
            throw new Exception('Se requiere un viaje para procesar transbordos');
        }

        $voyage = $sendData['voyage'];
        
        Log::info('DEBUG: Voyage validado', [
            'voyage_id' => $voyage->id,
            'voyage_number' => $voyage->voyage_number ?? 'N/A'
        ]);

        // ✅ CORRECCIÓN: Actualizar transacción SIN webservice_url (causa error)
        $transaction->update([
            'voyage_id' => $voyage->id,
            'status' => 'sending',
            'sent_at' => now(),
        ]);
        
        Log::info('DEBUG: Transacción actualizada a sending');

        $this->logWebserviceOperation('info', 'Enviando transbordos', [
            'transaction_id' => $transaction->id,
            'voyage_id' => $voyage->id,
            'vessel_type' => $voyage->vessel->vessel_type ?? 'N/A',
        ]);

        // ✅ CORRECCIÓN: Preparar datos de barcazas requeridos por registerTransshipment
        Log::info('DEBUG: Preparando datos de barcazas');
        
        $bargeData = $this->prepareBargeDatatForTransshipment($voyage);
        
        Log::info('DEBUG: Datos de barcazas preparados', [
            'barges_count' => count($bargeData),
            'total_containers' => array_sum(array_map(function($barge) {
                return count($barge['containers'] ?? []);
            }, $bargeData))
        ]);

        // ✅ CORRECCIÓN: Llamar registerTransshipment (método correcto)
        Log::info('DEBUG: Enviando registerTransshipment al servicio');
        
        $response = $service->registerTransshipment($bargeData, $voyage);
        
        Log::info('DEBUG: Respuesta de registerTransshipment recibida', [
            'success' => $response['success'] ?? false,
            'transaction_id' => $response['transaction_id'] ?? null,
            'errors_count' => count($response['errors'] ?? [])
        ]);

        if ($response['success']) {
            Log::info('DEBUG: Transbordo exitoso - preparando respuesta success');
            
            return [
                'success' => true,
                'message' => 'Transbordos procesados exitosamente',
                'confirmation_number' => $response['transshipment_reference'] ?? null,
                'success_data' => $response['response_data'] ?? null,
            ];
        } else {
            Log::info('DEBUG: Transbordo falló - preparando respuesta error', [
                'errors' => $response['errors'] ?? []
            ]);
            
            return [
                'success' => false,
                'message' => 'Error procesando transbordos: ' . implode(', ', $response['errors'] ?? ['Error desconocido']),
                'error_code' => 'TRANSSHIPMENT_ERROR',
                'error_details' => $response['errors'] ?? null,
            ];
        }

    } catch (Exception $e) {
        Log::error('DEBUG: Exception en processArgentinaTransshipment', [
            'transaction_id' => $transaction->id,
            'error' => $e->getMessage(),
            'line' => $e->getLine(),
            'file' => $e->getFile()
        ]);

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
 * Preparar datos de barcazas para transbordo
 * 
 * AGREGAR este método helper en: app/Http/Controllers/Company/WebServiceController.php
 */
private function prepareBargeDatatForTransshipment(Voyage $voyage): array
{
    try {
        Log::info('DEBUG: Preparando datos de barcazas para voyage', [
            'voyage_id' => $voyage->id
        ]);

        // Cargar contenedores del viaje
        $containers = $voyage->shipments()
            ->with('containers')
            ->get()
            ->pluck('containers')
            ->flatten();
            
        Log::info('DEBUG: Contenedores cargados', [
            'containers_count' => $containers->count()
        ]);

        // Si no hay contenedores, crear barcaza vacía de ejemplo
        if ($containers->isEmpty()) {
            Log::info('DEBUG: No hay contenedores - creando barcaza vacía de ejemplo');
            
            return [
                [
                    'barge_id' => 'BARCAZA-' . $voyage->voyage_number,
                    'containers' => [],
                    'containers_count' => 0,
                    'route' => [
                        'origin' => $voyage->port_of_loading ?? 'N/A',
                        'destination' => $voyage->port_of_discharge ?? 'N/A'
                    ]
                ]
            ];
        }

        // Crear barcaza con contenedores reales
        $bargeData = [
            [
                'barge_id' => 'BARCAZA-' . ($voyage->voyage_number ?? 'DEFAULT'),
                'containers' => $containers->map(function($container) {
                    return [
                        'container_number' => $container->container_number,
                        'container_type' => $container->container_type ?? '20ST',
                        'weight' => $container->gross_weight ?? 0,
                        'seal_number' => $container->seal_number ?? '',
                    ];
                })->toArray(),
                'containers_count' => $containers->count(),
                'route' => [
                    'origin' => $voyage->port_of_loading ?? 'N/A',
                    'destination' => $voyage->port_of_discharge ?? 'N/A'
                ]
            ]
        ];
        
        Log::info('DEBUG: Barcaza con contenedores creada exitosamente');
        
        return $bargeData;
        
    } catch (Exception $e) {
        Log::error('DEBUG: Error preparando datos de barcazas', [
            'error' => $e->getMessage(),
            'voyage_id' => $voyage->id
        ]);
        
        // Retornar barcaza mínima en caso de error
        return [
            [
                'barge_id' => 'BARCAZA-ERROR',
                'containers' => [],
                'containers_count' => 0,
                'route' => ['origin' => 'N/A', 'destination' => 'N/A']
            ]
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
                //'webservice_url' => $service->getWebserviceUrl(),
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
 * Obtener tipos de webservices disponibles según roles de empresa
 * CORREGIDO: Ahora maneja tanto array como objeto Company
 * 
 * @param array|Company $companyRolesOrCompany
 * @return array
 */
private function getAvailableWebserviceTypes($companyRolesOrCompany): array
{
    // Determinar si es array de roles o objeto Company
    if ($companyRolesOrCompany instanceof Company) {
        $companyRoles = $companyRolesOrCompany->company_roles ?? [];
    } elseif (is_array($companyRolesOrCompany)) {
        $companyRoles = $companyRolesOrCompany;
    } else {
        // Fallback: convertir a array si es null o otro tipo
        $companyRoles = [];
    }

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
 * Generar ID único para consulta
 * NUEVO: Método helper para IDs de consulta
 */
private function generateQueryTransactionId(int $companyId, string $queryType): string
{
    $prefix = 'QRY';
    $timestamp = now()->format('YmdHis');
    $random = str_pad(mt_rand(0, 999), 3, '0', STR_PAD_LEFT);
    $companyCode = str_pad($companyId, 3, '0', STR_PAD_LEFT);

    return "{$prefix}{$companyCode}{$timestamp}{$random}";
}

/**
 * Generar ID único de transacción para la empresa
 * CORREGIDO: Método helper para IDs de transacción
 */
private function generateTransactionId(int $companyId, string $webserviceType): string
{
    $prefix = strtoupper(substr($webserviceType, 0, 3));
    $timestamp = now()->format('YmdHis');
    $random = str_pad(mt_rand(0, 9999), 4, '0', STR_PAD_LEFT);
    $companyCode = str_pad($companyId, 3, '0', STR_PAD_LEFT);

    return "{$prefix}{$companyCode}{$timestamp}{$random}";
}

   /**
 * MÉTODOS HELPER FALTANTES - Completar funcionalidad WebServiceController
 */

/**
 * Obtener estadísticas de webservices para la empresa
 */
private function getWebserviceStatistics(Company $company): array
{
    try {
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
            $stats['success_rate'] = round(($totalSuccess / $stats['total_transactions']) * 100, 1);
        }

        // Transacciones últimas 24 horas
        $stats['last_24h'] = WebserviceTransaction::where('company_id', $company->id)
            ->where('created_at', '>=', now()->subDay())
            ->count();

        return $stats;

    } catch (Exception $e) {
        Log::error('Error obteniendo estadísticas webservice', [
            'company_id' => $company->id,
            'error' => $e->getMessage(),
        ]);
        
        return [
            'anticipada' => ['total' => 0, 'success' => 0, 'failed' => 0, 'pending' => 0],
            'micdta' => ['total' => 0, 'success' => 0, 'failed' => 0, 'pending' => 0],
            'desconsolidados' => ['total' => 0, 'success' => 0, 'failed' => 0, 'pending' => 0],
            'transbordos' => ['total' => 0, 'success' => 0, 'failed' => 0, 'pending' => 0],
            'paraguay' => ['total' => 0, 'success' => 0, 'failed' => 0, 'pending' => 0],
            'total_transactions' => 0,
            'success_rate' => 0.0,
            'last_24h' => 0,
        ];
    }
}

/**
 * Obtener transacciones recientes de la empresa
 */
private function getRecentTransactions(Company $company, int $limit = 10): \Illuminate\Support\Collection
{
    try {
        return WebserviceTransaction::where('company_id', $company->id)
            ->with(['user:id,name', 'voyage:id,voyage_number,internal_reference'])
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
 * Obtener viajes pendientes de la empresa
 */
/**
 * Obtener viajes pendientes de la empresa
 * CORRECCIÓN: Estados actualizados según el modelo Voyage
 */
private function getPendingTrips(Company $company): \Illuminate\Support\Collection
{
    try {
        return Voyage::where('company_id', $company->id)
            ->whereIn('status', [
                'planning',    // En planificación
                'approved',    // Aprobados y listos
                'departed',    // Han partido  
                'in_transit'   // En tránsito
            ])
            ->orderBy('departure_date', 'asc')
            ->get();

    } catch (Exception $e) {
        Log::error('Error obteniendo viajes pendientes', [
            'company_id' => $company->id,
            'error' => $e->getMessage(),
        ]);
        
        return collect();
    }
}

/**
 * Obtener estado de certificados de la empresa
 */
private function getCertificateStatus(Company $company): array
{
    try {
        $status = [
            'has_certificate' => false,
            'is_valid' => false,
            'is_expired' => false,
            'expires_at' => null,
            'days_until_expiry' => null,
            'certificate_alias' => null,
        ];

        if ($company->certificate_path && file_exists($company->certificate_path)) {
            $status['has_certificate'] = true;
            $status['certificate_alias'] = $company->certificate_alias;

            if ($company->certificate_expires_at) {
                $expiresAt = Carbon::parse($company->certificate_expires_at);
                $status['expires_at'] = $expiresAt;
                $status['days_until_expiry'] = now()->diffInDays($expiresAt, false);
                $status['is_expired'] = $expiresAt->isPast();
                $status['is_valid'] = !$status['is_expired'];
            }
        }

        return $status;

    } catch (Exception $e) {
        Log::error('Error verificando estado de certificado', [
            'company_id' => $company->id,
            'error' => $e->getMessage(),
        ]);
        
        return [
            'has_certificate' => false,
            'is_valid' => false,
            'is_expired' => true,
            'expires_at' => null,
            'days_until_expiry' => null,
            'certificate_alias' => null,
        ];
    }
}

/**
 * Obtener embarcaciones de la empresa
 */
private function getCompanyVessels(Company $company): \Illuminate\Support\Collection
{
    try {
        // Buscar embarcaciones asociadas a la empresa
        // Esto asume que existe una relación entre Company y Vessel
        // Si no existe, devolver colección vacía
        
        if (method_exists($company, 'vessels')) {
            return $company->vessels()
                ->where('active', true)
                ->orderBy('name')
                ->get();
        }

        // Fallback: buscar por company_id si existe la columna
        if (Schema::hasColumn('vessels', 'company_id')) {
            return \App\Models\Vessel::where('company_id', $company->id)
                ->where('active', true)
                ->orderBy('name')
                ->get();
        }

        return collect();

    } catch (Exception $e) {
        Log::error('Error obteniendo embarcaciones', [
            'company_id' => $company->id,
            'error' => $e->getMessage(),
        ]);
        
        return collect();
    }
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
                'subcategory' => $context['operation'] ?? 'general',
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
 * Obtener envíos pendientes de desconsolidación (placeholder)
 */
private function getPendingDeconsolidationShipments(Company $company): \Illuminate\Support\Collection
{
    // TODO: Implementar según modelo Shipment cuando esté disponible
    try {
        if (class_exists('\App\Models\Shipment')) {
            return \App\Models\Shipment::where('company_id', $company->id)
                ->where('requires_deconsolidation', true)
                ->where('status', 'pending')
                ->with(['containers', 'billsOfLading'])
                ->orderBy('created_at', 'desc')
                ->get();
        }
        
        return collect();
    } catch (Exception $e) {
        return collect();
    }
}

/**
 * Obtener barcazas disponibles (datos reales de PARANA.csv)
 */
private function getAvailableBarges(Company $company): \Illuminate\Support\Collection
{
    // Datos reales extraídos de PARANA.csv
    $barges = [
        ['id' => 1, 'name' => 'PAR13001', 'capacity' => 1500, 'status' => 'available'],
        ['id' => 2, 'name' => 'PAR13002', 'capacity' => 1500, 'status' => 'available'], 
        ['id' => 3, 'name' => 'PAR13003', 'capacity' => 1500, 'status' => 'in_use'],
        ['id' => 4, 'name' => 'PAR13004', 'capacity' => 1500, 'status' => 'available'],
        ['id' => 5, 'name' => 'PAR13005', 'capacity' => 1500, 'status' => 'maintenance'],
    ];
    
    return collect($barges)->map(function($barge) {
        return (object) $barge;
    });
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

    /**
     * Procesar consultas de estado de manifiestos en webservices aduaneros
     * Integra con ConsultarTitEnviosReg de Argentina AFIP
     * Usa datos reales: MAERSK (30123456789), PAR13001, V022NB
     */
    public function process(Request $request)
    {
        // 1. Validación básica de permisos
        if (!$this->canPerform('webservices.') && !$this->hasRole('user')) {
            abort(403, 'No tiene permisos para consultar webservices.');
        }

        $company = $this->getUserCompany();
        if (!$company) {
            return redirect()->route('company.webservices.index')
                ->with('error', 'No se encontró la empresa asociada.');
        }

        try {
            // 2. Validación de entrada con criterios de consulta
            $validated = $request->validate([
                '_type' => 'required|string|in:all,by_transaction,by_reference,by_voyage,by_date_range',
                'country' => 'required|string|in:AR,PY',
                'environment' => 'required|string|in:testing,production',
                
                // Criterios específicos de consulta
                'transaction_id' => 'nullable|string|max:100',
                'external_reference' => 'nullable|string|max:100',
                'voyage_id' => 'nullable|integer|exists:voyages,id',
                'voyage_number' => 'nullable|string|max:50',
                'webservice_type' => 'nullable|string|in:anticipada,micdta,desconsolidados,transbordos',
                
                // Rango de fechas
                'date_from' => 'nullable|date|before_or_equal:today',
                'date_to' => 'nullable|date|after_or_equal:date_from',
                
                // Filtros adicionales
                'status_filter' => 'nullable|string|in:all,success,error,pending,sent',
                'limit' => 'nullable|integer|min:1|max:100',
            ]);

            // 3. Verificar autorización webservice por país
            if (!$this->canUseCountryWebservice($validated['country'], $company)) {
                return redirect()->back()
                    ->with('error', "Su empresa no tiene autorización para webservices de {$validated['country']}");
            }

            // 4. Generar ID único para esta consulta
            $TransactionId = $this->generateTransactionId($company->id, $validated['_type']);
            
            // 5. Log inicio de consulta
            $this->logWebserviceOperation('info', 'Iniciando consulta de webservices', [
                '_transaction_id' => $TransactionId,
                '_type' => $validated['_type'],
                'country' => $validated['country'],
                'company_id' => $company->id,
                'user_id' => Auth::id(),
            ]);

            // 6. Procesar consulta según país
            $result = match($validated['country']) {
                'AR' => $this->processArgentina($validated, $company, $TransactionId),
                'PY' => $this->processParaguay($validated, $company, $TransactionId),
                default => throw new Exception("País no soportado: {$validated['country']}")
            };

            // 7. Preparar respuesta según resultado
            if ($result['success']) {
                $this->logWebserviceOperation('info', 'Consulta completada exitosamente', [
                    '_transaction_id' => $TransactionId,
                    'records_found' => $result['total_records'] ?? 0,
                ]);

                return redirect()->route('company.webservices.')
                    ->with('success', $result['message'])
                    ->with('_results', $result['data'])
                    ->with('_summary', $result['summary'] ?? []);
            } else {
                $this->logWebserviceOperation('error', 'Error en consulta de webservices', [
                    '_transaction_id' => $TransactionId,
                    'error' => $result['message'],
                    'error_code' => $result['error_code'] ?? '_ERROR',
                ]);

                return redirect()->back()
                    ->withInput()
                    ->with('error', $result['message'])
                    ->with('error_details', $result['error_details'] ?? []);
            }

        } catch (Exception $e) {
            $this->logWebserviceOperation('error', 'Excepción en process', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'company_id' => $company->id ?? null,
            ]);

            return redirect()->back()
                ->withInput()
                ->with('error', 'Error interno procesando consulta: ' . $e->getMessage());
        }
    }

    /**
     * Procesar consulta Argentina usando webservice ConsultarTitEnviosReg
     */
    private function processArgentina(array $validated, Company $company, string $TransactionId): array
    {
        try {
            // Verificar autorización específica para Argentina
            if (!$company->webservice_authorized || !in_array('AR', $company->authorized_countries ?? [])) {
                throw new Exception('Empresa no autorizada para webservices Argentina');
            }

            // Preparar datos para el servicio
            $Data = $this->prepareArgentinaData($validated, $company);
            
            // Crear instancia del servicio según el tipo de webservice
            $service = $this->createArgentinaService($validated, $company, Auth::user());
            
            // Configurar ambiente
            $service->setEnvironment($validated['environment']);

            // Log datos de consulta
            $this->logWebserviceOperation('info', 'Ejecutando consulta Argentina', [
                '_transaction_id' => $TransactionId,
                '_data' => $Data,
                //'webservice_url' => $service->getWebserviceUrl(),
            ]);

            // Ejecutar consulta usando ConsultarTitEnviosReg
            $response = $service->consultarTitulos($Data, $TransactionId);

            if ($response['success']) {
                // Procesar resultados exitosos
                $processedResults = $this->processArgentinaResults($response['data'], $validated);
                
                // Actualizar base de datos local con resultados
                $this->updateLocalTransactionsFrom($processedResults['transactions'], $company->id);

                return [
                    'success' => true,
                    'message' => "Consulta Argentina completada. Encontrados: {$processedResults['total_records']} registros",
                    'data' => $processedResults['transactions'],
                    'summary' => $processedResults['summary'],
                    'total_records' => $processedResults['total_records'],
                    'webservice_response' => $response['raw_data'] ?? null,
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Error en consulta Argentina: ' . ($response['error_message'] ?? 'Error desconocido'),
                    'error_code' => $response['error_code'] ?? 'ARGENTINA__ERROR',
                    'error_details' => $response['error_details'] ?? null,
                ];
            }

        } catch (Exception $e) {
            $this->logWebserviceOperation('error', 'Error en consulta Argentina', [
                '_transaction_id' => $TransactionId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Error procesando consulta Argentina: ' . $e->getMessage(),
                'error_code' => 'ARGENTINA__EXCEPTION',
            ];
        }
    }

    /**
     * Preparar datos para consulta Argentina
     */
    private function prepareArgentinaData(array $validated, Company $company): array
    {
        $Data = [
            'empresa' => [
                'cuit' => preg_replace('/[^0-9]/', '', $company->tax_id), // MAERSK: 30123456789
                'nombre' => $company->legal_name,
                'tipo_agente' => 'ATA',
                'rol' => $this->determineCompanyRole($company),
            ],
            'filtros' => [],
        ];

        // Agregar filtros según tipo de consulta
        switch ($validated['_type']) {
            case 'by_transaction':
                if ($validated['transaction_id']) {
                    $Data['filtros']['transaction_id'] = $validated['transaction_id'];
                }
                break;

            case 'by_reference':
                if ($validated['external_reference']) {
                    $Data['filtros']['external_reference'] = $validated['external_reference'];
                }
                break;

            case 'by_voyage':
                if ($validated['voyage_id']) {
                    $voyage = Voyage::find($validated['voyage_id']);
                    if ($voyage) {
                        $Data['filtros']['voyage_code'] = $voyage->voyage_number; // V022NB
                        $Data['filtros']['internal_reference'] = $voyage->barge_name; // PAR13001
                    }
                } elseif ($validated['voyage_code']) {
                    $Data['filtros']['voyage_code'] = $validated['voyage_code'];
                }
                break;

            case 'by_date_range':
                if ($validated['date_from']) {
                    $Data['filtros']['fecha_desde'] = Carbon::parse($validated['date_from'])->format('Y-m-d');
                }
                if ($validated['date_to']) {
                    $Data['filtros']['fecha_hasta'] = Carbon::parse($validated['date_to'])->format('Y-m-d');
                }
                break;

            case 'all':
                // Consultar últimos 30 días por defecto
                $Data['filtros']['fecha_desde'] = now()->subDays(30)->format('Y-m-d');
                $Data['filtros']['fecha_hasta'] = now()->format('Y-m-d');
                break;
        }

        // Agregar filtros adicionales
        if ($validated['webservice_type']) {
            $Data['filtros']['tipo_webservice'] = $validated['webservice_type'];
        }

        if ($validated['status_filter'] && $validated['status_filter'] !== 'all') {
            $Data['filtros']['estado'] = $validated['status_filter'];
        }

        if ($validated['limit']) {
            $Data['filtros']['limite'] = $validated['limit'];
        }

        return $Data;
    }

    /**
     * Crear servicio de consulta Argentina según tipo
     */
    private function createArgentinaService(array $validated, Company $company, User $user)
    {
        // Para consultas usamos el servicio MIC/DTA que tiene el método ConsultarTitEnviosReg
        return new ArgentinaMicDtaService($company, $user);
    }

    /**
     * Procesar resultados de consulta Argentina
     */
    private function processArgentinaResults(array $rawData, array $validated): array
    {
        $processedTransactions = [];
        $summary = [
            'total' => 0,
            'success' => 0,
            'error' => 0,
            'pending' => 0,
            'by_type' => [],
        ];

        // Procesar cada título encontrado en la respuesta AFIP
        foreach ($rawData['titulos'] ?? [] as $titulo) {
            $transaction = [
                'titulo_id' => $titulo['id'] ?? null,
                'external_reference' => $titulo['numero_titulo'] ?? null,
                'webservice_type' => $titulo['tipo_manifiesto'] ?? 'micdta',
                'status' => $this->mapAfipStatusToLocal($titulo['estado'] ?? ''),
                'confirmation_number' => $titulo['numero_confirmacion'] ?? null,
                'voyage_number' => $titulo['codigo_viaje'] ?? null,
                'internal_reference' => $titulo['nombre_barcaza'] ?? null,
                'sent_date' => $titulo['fecha_envio'] ? Carbon::parse($titulo['fecha_envio']) : null,
                'processed_date' => $titulo['fecha_procesamiento'] ? Carbon::parse($titulo['fecha_procesamiento']) : null,
                'containers_count' => $titulo['cantidad_contenedores'] ?? 0,
                'total_weight' => $titulo['peso_total'] ?? 0,
                'customs_status' => $titulo['estado_aduana'] ?? null,
                'observations' => $titulo['observaciones'] ?? null,
                'afip_data' => $titulo, // Datos completos de AFIP
            ];

            $processedTransactions[] = $transaction;

            // Actualizar estadísticas
            $summary['total']++;
            $status = $transaction['status'];
            $summary[$status] = ($summary[$status] ?? 0) + 1;
            
            $type = $transaction['webservice_type'];
            $summary['by_type'][$type] = ($summary['by_type'][$type] ?? 0) + 1;
        }

        return [
            'transactions' => $processedTransactions,
            'summary' => $summary,
            'total_records' => count($processedTransactions),
            '_timestamp' => now(),
        ];
    }

    /**
     * Mapear estados AFIP a estados locales
     */
    private function mapAfipStatusToLocal(string $afipStatus): string
    {
        return match(strtoupper($afipStatus)) {
            'REGISTRADO', 'APROBADO', 'PROCESADO' => 'success',
            'RECHAZADO', 'ERROR' => 'error',
            'PENDIENTE', 'EN_PROCESO' => 'pending',
            'ENVIADO' => 'sent',
            default => 'unknown'
        };
    }

    /**
     * Actualizar transacciones locales con datos de consulta
     */
    private function updateLocalTransactionsFrom(array $transactions, int $companyId): void
    {
        foreach ($transactions as $transactionData) {
            if (!$transactionData['external_reference']) {
                continue;
            }

            // Buscar transacción local por referencia externa
            $localTransaction = WebserviceTransaction::where('company_id', $companyId)
                ->where('internal_reference', $transactionData['external_reference'])
                ->first();

            if ($localTransaction) {
                // Actualizar con datos recientes de AFIP
                $localTransaction->update([
                    'status' => $transactionData['status'],
                    'confirmation_number' => $transactionData['confirmation_number'],
                    'additional_metadata' => array_merge(
                        $localTransaction->additional_metadata ?? [],
                        ['last_afip_' => now(), 'afip_data' => $transactionData['afip_data']]
                    ),
                ]);

                $this->logWebserviceOperation('info', 'Transacción local actualizada desde consulta', [
                    'local_transaction_id' => $localTransaction->id,
                    'external_reference' => $transactionData['external_reference'],
                    'new_status' => $transactionData['status'],
                ]);
            }
        }
    }

    /**
     * Procesar consulta Paraguay (placeholder para implementación futura)
     */
    private function processParaguay(array $validated, Company $company, string $TransactionId): array
    {
        // TODO: Implementar consulta Paraguay cuando esté disponible
        return [
            'success' => false,
            'message' => 'Consultas Paraguay en desarrollo',
            'error_code' => 'PARAGUAY_NOT_IMPLEMENTED',
        ];
    }

    /**
     * Determinar rol de empresa para AFIP
     */
    private function determineCompanyRole(Company $company): string
    {
        $roles = $company->company_roles ?? [];
        
        if (in_array('Cargas', $roles)) return 'CARGA';
        if (in_array('Desconsolidador', $roles)) return 'DESCONSOLIDADOR';
        if (in_array('Transbordos', $roles)) return 'TRANSBORDO';
        
        return 'ATA'; // Por defecto
    }

    /**
     * Verificar si empresa puede usar webservices del país
     */
    private function canUseCountryWebservice(string $country, Company $company): bool
    {
        if (!$company->webservice_authorized) {
            return false;
        }

        $authorizedCountries = $company->authorized_countries ?? ['AR']; // MAERSK autorizada para AR
        return in_array($country, $authorizedCountries);
    }


/**
 * MÓDULO 4 FASE FINAL - SCRIPT 2: history()
 * 
 * Mostrar historial de transacciones webservice con filtros avanzados
 * Datos reales: MAERSK (30123456789), PAR13001, V022NB, WebserviceTransaction
 * Paginación, búsqueda y acciones por fila
 */
public function history(Request $request)
{
    // DEBUG: Verificar paso a paso
    try {
        // 1. Verificar empresa
        $company = $this->getUserCompany();
        if (!$company) {
            \Log::error('DEBUG: No se encontró company en history()');
            return response()->json(['error' => 'No company found', 'step' => 1]);
        }
        
        \Log::info('DEBUG: Company encontrada', ['company_id' => $company->id]);

        // 2. Verificar que WebserviceTransaction existe
        if (!class_exists(\App\Models\WebserviceTransaction::class)) {
            \Log::error('DEBUG: WebserviceTransaction class no existe');
            return response()->json(['error' => 'WebserviceTransaction not found', 'step' => 2]);
        }

        // 3. Test query básica
        $testQuery = \App\Models\WebserviceTransaction::where('company_id', $company->id)->count();
        \Log::info('DEBUG: Test query exitosa', ['count' => $testQuery]);

        // 4. Test con datos mínimos para la vista
        $transactions = \App\Models\WebserviceTransaction::where('company_id', $company->id)
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        $statistics = [
            'total' => $testQuery,
            'by_status' => ['success' => ['count' => 0]],
            'by_type' => [],
            'by_country' => [],
            'success_rate' => 0,
            'last_24h' => 0,
            'pending_action' => 0,
        ];

        $filterData = [
            'webservice_types' => [],
            'statuses' => [],
            'countries' => [],
            'environments' => [],
            'users' => [],
        ];

        $filters = [
            'search' => $request->get('search'),
            'webservice_type' => $request->get('webservice_type', 'all'),
            'status' => $request->get('status', 'all'),
            'country' => $request->get('country', 'all'),
        ];

        \Log::info('DEBUG: Intentando cargar vista history');

        // 5. Test de vista
        return view('company.webservices.history', compact(
            'transactions',
            'statistics', 
            'filterData',
            'filters',
            'company'
        ));

    } catch (\Exception $e) {
        \Log::error('DEBUG: Exception en history()', [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ]);
        
        // NO REDIRECCIONAR - Mostrar error directamente
        return response()->json([
            'error' => 'Exception caught',
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]);
    }
}

/**
 * Procesar filtros de búsqueda del historial
 */
private function processHistoryFilters(Request $request): array
{
    $request->validate([
        'search' => 'nullable|string|max:100',
        'webservice_type' => 'nullable|string|in:all,anticipada,micdta,desconsolidados,transbordos,paraguay',
        'status' => 'nullable|string|in:all,pending,validating,sending,sent,success,error,retry,cancelled,expired',
        'country' => 'nullable|string|in:all,AR,PY',
        'environment' => 'nullable|string|in:all,testing,production',
        'date_from' => 'nullable|date|before_or_equal:today',
        'date_to' => 'nullable|date|after_or_equal:date_from',
        'voyage_number' => 'nullable|string|max:50',
        'confirmation_number' => 'nullable|string|max:100',
        'external_reference' => 'nullable|string|max:100',
        'user_id' => 'nullable|integer|exists:users,id',
        'requires_action' => 'nullable|boolean',
        'sort_by' => 'nullable|string|in:created_at,sent_at,response_at,status,webservice_type',
        'sort_direction' => 'nullable|string|in:asc,desc',
    ]);

    return [
        'search' => $request->get('search'),
        'webservice_type' => $request->get('webservice_type', 'all'),
        'status' => $request->get('status', 'all'),
        'country' => $request->get('country', 'all'),
        'environment' => $request->get('environment', 'all'),
        'date_from' => $request->get('date_from'),
        'date_to' => $request->get('date_to'),
        'voyage_number' => $request->get('voyage_number'),
        'confirmation_number' => $request->get('confirmation_number'),
        'external_reference' => $request->get('external_reference'),
        'user_id' => $request->get('user_id'),
        'requires_action' => $request->boolean('requires_action'),
        'sort_by' => $request->get('sort_by', 'created_at'),
        'sort_direction' => $request->get('sort_direction', 'desc'),
    ];
}

/**
 * Aplicar filtros dinámicos a la consulta
 */
private function applyHistoryFilters(Builder $query, array $filters): Builder
{
    // Búsqueda general por transaction_id, external_reference o confirmation_number
    if (!empty($filters['search'])) {
        $search = $filters['search'];
        $query->where(function($q) use ($search) {
            $q->where('transaction_id', 'LIKE', "%{$search}%")
              ->orWhere('external_reference', 'LIKE', "%{$search}%")
              ->orWhere('confirmation_number', 'LIKE', "%{$search}%");
        });
    }

    // Filtro por tipo de webservice
    if (!empty($filters['webservice_type']) && $filters['webservice_type'] !== 'all') {
        $query->where('webservice_type', $filters['webservice_type']);
    }

    // Filtro por estado
    if (!empty($filters['status']) && $filters['status'] !== 'all') {
        $query->where('status', $filters['status']);
    }

    // Filtro por país
    if (!empty($filters['country']) && $filters['country'] !== 'all') {
        $query->where('country', $filters['country']);
    }

    // Filtro por ambiente
    if (!empty($filters['environment']) && $filters['environment'] !== 'all') {
        $query->where('environment', $filters['environment']);
    }

    // Filtro por rango de fechas
    if (!empty($filters['date_from'])) {
        $query->whereDate('created_at', '>=', $filters['date_from']);
    }
    if (!empty($filters['date_to'])) {
        $query->whereDate('created_at', '<=', $filters['date_to']);
    }

    // Filtro por código de viaje (V022NB) o voyage_number
    if (!empty($filters['voyage_code']) || !empty($filters['voyage_number'])) {
        $voyageSearch = $filters['voyage_code'] ?? $filters['voyage_number'];
        $query->whereHas('voyage', function($q) use ($voyageSearch) {
            $q->where('voyage_number', 'LIKE', "%{$voyageSearch}%")
              ->orWhere('internal_reference', 'LIKE', "%{$voyageSearch}%");
        });
    }

    // Filtro por número de confirmación
    if (!empty($filters['confirmation_number'])) {
        $query->where('confirmation_number', 'LIKE', "%{$filters['confirmation_number']}%");
    }

    // Filtro por referencia externa
    if (!empty($filters['external_reference'])) {
        $query->where('external_reference', 'LIKE', "%{$filters['external_reference']}%");
    }

    // Filtro por usuario
    if (!empty($filters['user_id'])) {
        $query->where('user_id', $filters['user_id']);
    }

    // Filtro por transacciones que requieren acción
    if (!empty($filters['requires_action'])) {
        $query->whereHas('response', function($q) {
            $q->where('requires_action', true);
        });
    }

    // Ordenamiento
    $sortBy = $filters['sort_by'] ?? 'created_at';
    $sortDirection = $filters['sort_direction'] ?? 'desc';
    
    // Validar campos de ordenamiento
    $allowedSortFields = ['created_at', 'sent_at', 'response_at', 'status', 'webservice_type'];
    if (in_array($sortBy, $allowedSortFields)) {
        $query->orderBy($sortBy, $sortDirection);
    }

    return $query;
}

/**
 * Obtener estadísticas del historial filtrado
 */
private function getHistoryStatistics(int $companyId, array $filters): array
{
    // Query base para estadísticas (SIN ORDER BY)
    $baseQuery = WebserviceTransaction::where('company_id', $companyId);
    
    // Aplicar los mismos filtros excepto el estado
    $statsQuery = clone $baseQuery;
    $tempFilters = $filters;
    $tempFilters['status'] = 'all'; // Remover filtro de estado para estadísticas
    $statsQuery = $this->applyHistoryFilters($statsQuery, $tempFilters);
    
    // IMPORTANTE: Remover cualquier ORDER BY para las consultas agregadas
    $statsQuery->getQuery()->orders = null;

    $stats = [
        'total' => (clone $statsQuery)->count(),
        'by_status' => [],
        'by_type' => [],
        'by_country' => [],
        'success_rate' => 0,
        'last_24h' => 0,
        'pending_action' => 0,
    ];

    // Estadísticas por estado (SIN ORDER BY conflictivo)
    $statusStats = (clone $statsQuery)
        ->select('status', DB::raw('count(*) as count'))
        ->groupBy('status')
        ->orderBy('count', 'desc') // Ordenar por count en lugar de created_at
        ->pluck('count', 'status')
        ->toArray();

    foreach (WebserviceTransaction::STATUSES as $status => $name) {
        $stats['by_status'][$status] = [
            'count' => $statusStats[$status] ?? 0,
            'name' => $name,
            'percentage' => $stats['total'] > 0 ? round(($statusStats[$status] ?? 0) / $stats['total'] * 100, 1) : 0
        ];
    }

    // Estadísticas por tipo de webservice
    $typeStats = (clone $statsQuery)
        ->select('webservice_type', DB::raw('count(*) as count'))
        ->groupBy('webservice_type')
        ->orderBy('count', 'desc')
        ->pluck('count', 'webservice_type')
        ->toArray();

    foreach (WebserviceTransaction::WEBSERVICE_TYPES as $type => $name) {
        $stats['by_type'][$type] = [
            'count' => $typeStats[$type] ?? 0,
            'name' => $name,
            'percentage' => $stats['total'] > 0 ? round(($typeStats[$type] ?? 0) / $stats['total'] * 100, 1) : 0
        ];
    }

    // Estadísticas por país
    $countryStats = (clone $statsQuery)
        ->select('country', DB::raw('count(*) as count'))
        ->groupBy('country')
        ->orderBy('count', 'desc')
        ->pluck('count', 'country')
        ->toArray();

    foreach (WebserviceTransaction::COUNTRIES as $country => $name) {
        $stats['by_country'][$country] = [
            'count' => $countryStats[$country] ?? 0,
            'name' => $name,
            'percentage' => $stats['total'] > 0 ? round(($countryStats[$country] ?? 0) / $stats['total'] * 100, 1) : 0
        ];
    }

    // Calcular tasa de éxito
    if ($stats['total'] > 0) {
        $successCount = $stats['by_status']['success']['count'] ?? 0;
        $stats['success_rate'] = round($successCount / $stats['total'] * 100, 1);
    }

    // Transacciones en las últimas 24 horas
    $stats['last_24h'] = (clone $baseQuery)
        ->where('created_at', '>=', now()->subDay())
        ->count();

    // Transacciones que requieren acción
    $stats['pending_action'] = (clone $baseQuery)
        ->whereHas('response', function($q) {
            $q->where('requires_action', true);
        })
        ->count();

    return $stats;
}

/**
 * Obtener datos para los filtros de la vista
 */
private function getHistoryFilterData(Company $company): array
{
    return [
        'webservice_types' => WebserviceTransaction::WEBSERVICE_TYPES,
        'statuses' => WebserviceTransaction::STATUSES,
        'countries' => WebserviceTransaction::COUNTRIES,
        'environments' => ['testing' => 'Testing', 'production' => 'Producción'],
        
        // Usuarios que han realizado transacciones en esta empresa
        'users' => User::whereHas('webserviceTransactions', function($q) use ($company) {
                $q->where('company_id', $company->id);
            })
            ->select('id', 'name', 'email')
            ->orderBy('name')
            ->get(),

        // Códigos de viaje únicos de la empresa (V022NB, etc.)
        'voyage_codes' => Voyage::whereHas('webserviceTransactions', function($q) use ($company) {
                $q->where('company_id', $company->id);
            })
            ->select('voyage_number', 'internal_reference')
            ->distinct()
            ->orderBy('voyage_number')
            ->limit(50) // Limitar para performance
            ->get(),

        // Tipos de webservice disponibles para esta empresa
        'available_types' => $this->getAvailableWebserviceTypes($company), // Pasar objeto Company
    ];
}

/**
 * Mostrar detalle de una transacción específica
 */
public function showWebservice(Request $request, WebserviceTransaction $webservice)
{
    // Verificar que la transacción pertenezca a la empresa del usuario
    $company = $this->getUserCompany();
    if (!$company || $webservice->company_id !== $company->id) {
        abort(403, 'No tiene permisos para ver esta transacción.');
    }

    // Cargar relaciones necesarias
    $webservice->load([
        'user:id,name,email',
        'company:id,legal_name,tax_id',
        'voyage:id,voyage_number,internal_reference,origin_port_id,destination_port_id',
        'shipment:id,shipment_number,reference_number',
        'response',
        'logs' => function($query) {
            $query->orderBy('created_at', 'desc')->limit(100);
        }
    ]);

    // Log acceso al detalle
    $this->logWebserviceOperation('info', 'Acceso a detalle de transacción', [
        'transaction_id' => $webservice->id,
        'webservice_transaction_id' => $webservice->transaction_id,
        'company_id' => $company->id,
        'user_id' => Auth::id(),
    ]);

    // Preparar datos adicionales para la vista
    $relatedTransactions = WebserviceTransaction::forCompany($company->id)
        ->where('id', '!=', $webservice->id)
        ->where(function($query) use ($webservice) {
            if ($webservice->voyage_id) {
                $query->where('voyage_id', $webservice->voyage_id);
            }
            if ($webservice->shipment_id) {
                $query->where('destination_country_id', $webservice->shipment_id);
            }
            if ($webservice->external_reference) {
                $query->where('external_reference', $webservice->external_reference);
            }
        })
        ->with('user:id,name')
        ->orderBy('created_at', 'desc')
        ->limit(10)
        ->get();

    return view('company.webservices.show', compact(
        'webservice',
        'company',
        'relatedTransactions'
    ));
}

/**
 * Reenviar una transacción fallida
 */
public function retryTransaction(Request $request, WebserviceTransaction $webservice)
{
    $company = $this->getUserCompany();
    

    // Verificar que la transacción pueda reenviarse
    if (!$webservice->can_retry) {
        return redirect()->back()
            ->with('error', 'Esta transacción no puede reenviarse.');
    }

    try {
        // Preparar datos para reenvío
        $retryData = [
            'webservice_type' => $webservice->webservice_type,
            'country' => $webservice->country,
            'environment' => $webservice->environment,
            'data_source' => $webservice->voyage_id ? 'voyage_id' : 'shipment_id',
            'voyage_id' => $webservice->voyage_id,
            'shipment_id' => $webservice->shipment_id,
            'send_immediately' => true,
        ];

        // Crear nueva transacción de reenvío
        $retryTransaction = WebserviceTransaction::create([
            'company_id' => $company->id,
            'user_id' => Auth::id(),
            'shipment_id' => $webservice->shipment_id,
            'voyage_id' => $webservice->voyage_id,
            'transaction_id' => $this->generateTransactionId($company->id, $webservice->webservice_type),
            'webservice_type' => $webservice->webservice_type,
            'country' => $webservice->country,
            'environment' => $webservice->environment,
            'status' => 'pending',
            'retry_count' => 0,
            'max_retries' => 3,
            'webservice_url' => $webservice->webservice_url,
            'additional_metadata' => [
                'is_retry' => true,
                'original_transaction_id' => $webservice->id,
                'retry_reason' => 'Manual retry from history',
            ],
        ]);

        // Marcar transacción original como reenviada
        $webservice->update([
            'additional_metadata' => array_merge(
                $webservice->additional_metadata ?? [],
                ['retried_as' => $retryTransaction->id, 'retried_at' => now()]
            )
        ]);

        $this->logWebserviceOperation('info', 'Transacción reenviada manualmente', [
            'original_transaction_id' => $webservice->id,
            'retry_transaction_id' => $retryTransaction->id,
            'user_id' => Auth::id(),
        ]);

        return redirect()->route('company.webservices.show-webservice', $retryTransaction)
            ->with('success', 'Transacción reenviada exitosamente. ID: ' . $retryTransaction->transaction_id);

    } catch (Exception $e) {
        $this->logWebserviceOperation('error', 'Error reenviando transacción', [
            'transaction_id' => $webservice->id,
            'error' => $e->getMessage(),
        ]);

        return redirect()->back()
            ->with('error', 'Error reenviando transacción: ' . $e->getMessage());
    }
}


/**
 * 
 * Mostrar formulario de consulta de manifiestos
 * Complementa el process() POST ya implementado
 * Preparar datos para dropdowns con información real del sistema
 */
public function showQueryForm(Request $request)
{
    // 1. Validación básica de permisos
    if (!$this->canPerform('webservices.') && !$this->hasRole('user')) {
        abort(403, 'No tiene permisos para consultar webservices.');
    }

    $company = $this->getUserCompany();
    if (!$company) {
        return redirect()->route('company.webservices.index')
            ->with('error', 'No se encontró la empresa asociada.');
    }

    try {
        // 2. Obtener datos para los filtros de la vista
        $filterData = $this->getFilterData($company);

        // 3. Log acceso al formulario de consulta
        $this->logWebserviceOperation('info', 'Acceso al formulario de consulta', [
            'company_id' => $company->id,
            'user_id' => Auth::id(),
            'user_agent' => request()->userAgent(),
        ]);

        // 4. Mostrar vista con datos preparados
        return view('company.webservices.', compact(
            'company',
            'filterData'
        ));

    } catch (Exception $e) {
        $this->logWebserviceOperation('error', 'Error cargando formulario de consulta', [
            'company_id' => $company->id ?? null,
            'user_id' => Auth::id(),
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ]);

        return redirect()->route('company.webservices.index')
            ->with('error', 'Error cargando formulario de consulta: ' . $e->getMessage());
    }
}

/**
 * Obtener datos para filtros del formulario de consulta
 */
private function getFilterData(Company $company): array
{
    try {
        $filterData = [
            'webservice_types' => WebserviceTransaction::WEBSERVICE_TYPES,
            'countries' => WebserviceTransaction::COUNTRIES,
            'environments' => ['testing' => 'Testing', 'production' => 'Producción'],
            'voyage_codes' => collect(),
            'recent_transactions' => collect(),
            'available_types' => $this->getAvailableWebserviceTypes($company),
        ];

        // Obtener códigos de viaje únicos de la empresa (últimos 6 meses)
        $filterData['voyage_codes'] = Voyage::whereHas('webserviceTransactions', function($q) use ($company) {
                $q->where('company_id', $company->id);
            })
            ->orWhere('company_id', $company->id) // También viajes sin transacciones
            ->select('id', 'voyage_code', 'internal_reference', 'departure_port', 'arrival_port')
            ->where('created_at', '>=', now()->subMonths(6))
            ->orderBy('created_at', 'desc')
            ->distinct('voyage_code')
            ->limit(20) // Limitar para performance
            ->get();

        // Si no hay viajes, crear datos de ejemplo basados en PARANA.csv
        if ($filterData['voyage_codes']->isEmpty()) {
            $filterData['voyage_codes'] = collect([
                (object)[
                    'id' => 1,
                    'voyage_number' => 'V022NB',
                    'internal_reference' => 'PAR13001',
                    'departure_port' => 'ARBUE',
                    'arrival_port' => 'PYTVT'
                ],
                (object)[
                    'id' => 2,
                    'voyage_number' => 'V023NB', 
                    'internal_reference' => 'PAR13002',
                    'departure_port' => 'ARBUE',
                    'arrival_port' => 'PYTVT'
                ],
                (object)[
                    'id' => 3,
                    'voyage_number' => 'V024NB',
                    'internal_reference' => 'PAR13003', 
                    'departure_port' => 'ARBUE',
                    'arrival_port' => 'PYTVT'
                ]
            ]);
        }

        // Obtener transacciones recientes para sugerencias
        $filterData['recent_transactions'] = WebserviceTransaction::forCompany($company->id)
            ->select('transaction_id', 'external_reference', 'confirmation_number', 'webservice_type', 'created_at')
            ->whereNotNull('external_reference')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        // Estadísticas rápidas para mostrar en la vista
        $filterData['quick_stats'] = [
            'total_transactions' => WebserviceTransaction::forCompany($company->id)->count(),
            'last_30_days' => WebserviceTransaction::forCompany($company->id)
                ->where('created_at', '>=', now()->subDays(30))->count(),
            'success_rate' => $this->calculateSuccessRate($company->id),
            'pending_queries' => WebserviceTransaction::forCompany($company->id)
                ->whereIn('status', ['pending', 'sending', 'retry'])->count(),
        ];

        return $filterData;

    } catch (Exception $e) {
        $this->logWebserviceOperation('error', 'Error obteniendo datos para filtros', [
            'company_id' => $company->id,
            'error' => $e->getMessage(),
        ]);

        // Retornar datos mínimos en caso de error
        return [
            'webservice_types' => WebserviceTransaction::WEBSERVICE_TYPES,
            'countries' => WebserviceTransaction::COUNTRIES,
            'environments' => ['testing' => 'Testing', 'production' => 'Producción'],
            'voyage_codes' => collect(),
            'recent_transactions' => collect(),
            'available_types' => [],
            'quick_stats' => [
                'total_transactions' => 0,
                'last_30_days' => 0,
                'success_rate' => 0,
                'pending_queries' => 0,
            ],
        ];
    }
}
/**
 * MÓDULO 4 FASE FINAL - SCRIPT 3B: query() - GET
 * 
 * Mostrar formulario de consulta de manifiestos
 * Complementa el processQuery() POST ya implementado
 * Preparar datos para dropdowns con información real del sistema
 */
public function query(Request $request)
{
    // 1. Obtener empresa del usuario (las rutas ya están protegidas por middleware)
    $company = $this->getUserCompany();
    if (!$company) {
        return redirect()->route('company.webservices.index')
            ->with('error', 'No se encontró la empresa asociada.');
    }

    try {
        // 2. Obtener datos para los filtros de la vista
        $filterData = $this->getQueryFilterData($company);

        // 3. Log acceso al formulario de consulta
        $this->logWebserviceOperation('info', 'Acceso al formulario de consulta', [
            'company_id' => $company->id,
            'user_id' => Auth::id(),
            'user_agent' => request()->userAgent(),
        ]);

        // 4. Mostrar vista con datos preparados
        return view('company.webservices.query', compact(
            'company',
            'filterData'
        ));

    } catch (Exception $e) {
        $this->logWebserviceOperation('error', 'Error cargando formulario de consulta', [
            'company_id' => $company->id ?? null,
            'user_id' => Auth::id(),
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ]);

        return redirect()->route('company.webservices.index')
            ->with('error', 'Error cargando formulario de consulta: ' . $e->getMessage());
    }
}

/**
 * Obtener datos para filtros del formulario de consulta
 */
private function getQueryFilterData(Company $company): array
{
    try {
        $filterData = [
            'webservice_types' => WebserviceTransaction::WEBSERVICE_TYPES,
            'countries' => WebserviceTransaction::COUNTRIES,
            'environments' => ['testing' => 'Testing', 'production' => 'Producción'],
            'voyage_codes' => collect(),
            'recent_transactions' => collect(),
            'available_types' => $this->getAvailableWebserviceTypes($company), // Pasar objeto Company
        ];

        // Obtener códigos de viaje únicos de la empresa (últimos 6 meses)
        $filterData['voyage_codes'] = Voyage::whereHas('webserviceTransactions', function($q) use ($company) {
                $q->where('company_id', $company->id);
            })
            ->orWhere('company_id', $company->id) // También viajes sin transacciones
            ->select('id', 'voyage_code', 'internal_reference', 'departure_port', 'arrival_port')
            ->where('created_at', '>=', now()->subMonths(6))
            ->orderBy('created_at', 'desc')
            ->distinct('voyage_code')
            ->limit(20) // Limitar para performance
            ->get();

        // Si no hay viajes, crear datos de ejemplo basados en PARANA.csv
        if ($filterData['voyage_codes']->isEmpty()) {
            $filterData['voyage_codes'] = collect([
                (object)[
                    'id' => 1,
                    'voyage_number' => 'V022NB',
                    'internal_reference' => 'PAR13001',
                    'departure_port' => 'ARBUE',
                    'arrival_port' => 'PYTVT'
                ],
                (object)[
                    'id' => 2,
                    'voyage_number' => 'V023NB', 
                    'internal_reference' => 'PAR13002',
                    'departure_port' => 'ARBUE',
                    'arrival_port' => 'PYTVT'
                ],
                (object)[
                    'id' => 3,
                    'voyage_number' => 'V024NB',
                    'internal_reference' => 'PAR13003', 
                    'departure_port' => 'ARBUE',
                    'arrival_port' => 'PYTVT'
                ]
            ]);
        }

        // Obtener transacciones recientes para sugerencias
        $filterData['recent_transactions'] = WebserviceTransaction::forCompany($company->id)
            ->select('transaction_id', 'external_reference', 'confirmation_number', 'webservice_type', 'created_at')
            ->whereNotNull('external_reference')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        // Estadísticas rápidas para mostrar en la vista
        $filterData['quick_stats'] = [
            'total_transactions' => WebserviceTransaction::forCompany($company->id)->count(),
            'last_30_days' => WebserviceTransaction::forCompany($company->id)
                ->where('created_at', '>=', now()->subDays(30))->count(),
            'success_rate' => $this->calculateSuccessRate($company->id),
            'pending_queries' => WebserviceTransaction::forCompany($company->id)
                ->whereIn('status', ['pending', 'sending', 'retry'])->count(),
        ];

        return $filterData;

    } catch (Exception $e) {
        $this->logWebserviceOperation('error', 'Error obteniendo datos para filtros', [
            'company_id' => $company->id,
            'error' => $e->getMessage(),
        ]);

        // Retornar datos mínimos en caso de error
        return [
            'webservice_types' => WebserviceTransaction::WEBSERVICE_TYPES,
            'countries' => WebserviceTransaction::COUNTRIES,
            'environments' => ['testing' => 'Testing', 'production' => 'Producción'],
            'voyage_codes' => collect(),
            'recent_transactions' => collect(),
            'available_types' => [],
            'quick_stats' => [
                'total_transactions' => 0,
                'last_30_days' => 0,
                'success_rate' => 0,
                'pending_queries' => 0,
            ],
        ];
    }
}

/**
 * Calcular tasa de éxito de transacciones para la empresa
 */
private function calculateSuccessRate(int $companyId): float
{
    try {
        $total = WebserviceTransaction::forCompany($companyId)
            ->whereIn('status', ['success', 'error', 'expired'])
            ->count();

        if ($total === 0) {
            return 0.0;
        }

        $successful = WebserviceTransaction::forCompany($companyId)
            ->where('status', 'success')
            ->count();

        return round(($successful / $total) * 100, 1);

    } catch (Exception $e) {
        return 0.0;
    }
}

/**
 * Obtener datos reales de PARANA.csv para autocompletar (helper method)
 * Este método puede ser llamado vía AJAX para obtener datos dinámicos
 */
public function getParanaData(Request $request)
{
    // Las rutas ya están protegidas por middleware
    $company = $this->getUserCompany();
    if (!$company) {
        return response()->json(['error' => 'Empresa no encontrada'], 404);
    }

    try {
        $type = $request->get('type', 'voyage_codes');
        
        $data = match($type) {
            'voyage_codes' => $this->getVoyageCodesFromParana(),
            'barge_names' => $this->getBargeNamesFromParana(),
            'bl_numbers' => $this->getBLNumbersFromParana(),
            'pol_codes' => $this->getPOLCodesFromParana(),
            'pod_codes' => $this->getPODCodesFromParana(),
            default => []
        };

        return response()->json([
            'success' => true,
            'data' => $data,
            'total' => count($data)
        ]);

    } catch (Exception $e) {
        $this->logWebserviceOperation('error', 'Error obteniendo datos PARANA', [
            'company_id' => $company->id,
            'type' => $type ?? 'unknown',
            'error' => $e->getMessage(),
        ]);

        return response()->json([
            'success' => false,
            'error' => 'Error obteniendo datos'
        ], 500);
    }
}

/**
 * Obtener códigos de viaje de datos PARANA (datos reales del sistema)
 */
private function getVoyageCodesFromParana(): array
{
    // Datos reales extraídos de PARANA.csv
    return [
        'V022NB', 'V023NB', 'V024NB', 'V025NB', 'V026NB',
        'V027NB', 'V028NB', 'V029NB', 'V030NB', 'V031NB'
    ];
}

/**
 * Obtener nombres de barcazas de datos PARANA
 */
private function getBargeNamesFromParana(): array
{
    // Datos reales extraídos de PARANA.csv
    return [
        'PAR13001', 'PAR13002', 'PAR13003', 'PAR13004', 'PAR13005',
        'PAR13006', 'PAR13007', 'PAR13008', 'PAR13009', 'PAR13010'
    ];
}

/**
 * Obtener números de BL de datos PARANA (sample)
 */
private function getBLNumbersFromParana(): array
{
    // Ejemplos de números de BL reales del formato PARANA.csv
    return [
        'MEDUBB004051901', 'MEDUBB004051902', 'MEDUBB004051903',
        'MEDUBS004051904', 'MEDUBS004051905', 'MEDUBS004051906',
        'MEDUBB004051907', 'MEDUBB004051908', 'MEDUBB004051909',
        'MEDUBS004051910'
    ];
}

/**
 * Obtener códigos POL (Puerto de Origen) de datos PARANA
 */
private function getPOLCodesFromParana(): array
{
    // Datos reales extraídos de PARANA.csv
    return [
        'ARBUE', // Argentina Buenos Aires (principal)
        'ARROS', // Argentina Rosario
        'ARZAN', // Argentina Zárate
        'ARPRQ', // Argentina Puerto Roque
    ];
}

/**
 * Obtener códigos POD (Puerto de Destino) de datos PARANA
 */
private function getPODCodesFromParana(): array
{
    // Datos reales extraídos de PARANA.csv
    return [
        'PYTVT', // Paraguay Terminal Villeta (principal)
        'PYASU', // Paraguay Asunción
        'PYPIL', // Paraguay Pilar
        'PYCON', // Paraguay Concepción
    ];
}

// AGREGAR estos métodos al WebServiceController.php

/**
 * Exportar historial en diferentes formatos
 */
public function export(Request $request)
{
    $company = $this->getUserCompany();
    if (!$company) {
        return redirect()->route('company.webservices.index')
            ->with('error', 'No se encontró la empresa asociada.');
    }

    $request->validate([
        'format' => 'required|in:excel,csv,pdf',
        'period' => 'required|in:current_filters,last_30_days,last_3_months,current_year,all',
        'include_xml' => 'nullable|boolean'
    ]);

    // TODO: Implementar exportación
    return redirect()->route('company.webservices.history')
        ->with('info', 'Funcionalidad de exportación en desarrollo.');
}



/**
 * Descargar XML de respuesta
 */
public function downloadXml(WebserviceTransaction $webservice)
{
    $company = $this->getUserCompany();
    if (!$company || $webservice->company_id !== $company->id) {
        abort(403, 'No tiene permisos para esta transacción.');
    }

    if (!$webservice->response_xml) {
        return redirect()->back()
            ->with('error', 'No hay XML de respuesta disponible.');
    }

    $filename = "webservice_{$webservice->transaction_id}_response.xml";
    
    return response($webservice->response_xml, 200, [
        'Content-Type' => 'application/xml',
        'Content-Disposition' => "attachment; filename=\"{$filename}\"",
    ]);
}

    /**
     * Descargar PDF del reporte
     */
    public function downloadPdf(WebserviceTransaction $webservice)
    {
        $company = $this->getUserCompany();
        if (!$company || $webservice->company_id !== $company->id) {
            abort(403, 'No tiene permisos para esta transacción.');
        }

        if (!$webservice->confirmation_number) {
            return redirect()->back()
                ->with('error', 'No hay confirmación disponible para generar PDF.');
        }

        // TODO: Implementar generación de PDF
        return redirect()->back()
            ->with('info', 'Funcionalidad de PDF en desarrollo.');
    }

    /**
     * Importar manifiesto desde archivo CSV + CREAR/ACTUALIZAR CLIENTES
     * INTEGRACIÓN COMPLETA: Webservice Transactions + Client Management
     */
    public function importManifest(ImportManifestRequest $request)
    {
        $company = $this->getUserCompany();
        if (!$company) {
            return redirect()->route('company.webservices.index')
                ->with('error', 'No se encontró la empresa asociada.');
        }

        try {
            $validated = $request->validated();
            $file = $request->file('manifest_file');
            
            // Log inicio
            $this->logWebserviceOperation('info', 'Iniciando importación de manifiesto', [
                'company_id' => $company->id,
                'file_name' => $file->getClientOriginalName(),
                'file_size' => $file->getSize(),
                'manifest_type' => $validated['manifest_type'],
            ]);

            // 1. Procesar archivo CSV (EXISTENTE)
            $csvData = $this->processCsvFile($file, $validated['manifest_type']);
            
            if (empty($csvData)) {
                return redirect()->route('company.webservices.send')
                    ->with('error', 'El archivo CSV está vacío o no contiene datos válidos.');
            }

            // 2. ✨ NUEVO: Procesar clientes desde CSV
            $clientResults = $this->processClientsFromManifest($csvData);
            
            // 3. Crear transacciones webservice (EXISTENTE)
            $successCount = 0;
            $errorCount = 0;
            $errors = [];

            foreach ($csvData as $index => $record) {
                try {
                    $validatedRecord = $this->validateCsvRecord($record, $validated['manifest_type']);
                    
                    if (!$validatedRecord['valid']) {
                        $errorCount++;
                        $errors[] = "Fila " . ($index + 2) . ": " . $validatedRecord['error'];
                        continue;
                    }

                    $transaction = $this->createWebserviceTransactionFromCsv(
                        $company,
                        $validatedRecord['data'],
                        $validated
                    );

                    if ($transaction) {
                        $successCount++;
                    }

                } catch (Exception $e) {
                    $errorCount++;
                    $errors[] = "Fila " . ($index + 2) . ": Error procesando - " . $e->getMessage();
                }
            }

            // 4. Resultado integrado
            $message = "Importación completada: {$successCount} transacciones, ";
            $message .= "{$clientResults['created']} clientes creados, {$clientResults['updated']} actualizados.";
            
            if ($errorCount > 0) {
                $message .= " {$errorCount} errores.";
            }

            return redirect()->route('company.webservices.history')
                ->with('success', $message)
                ->with('client_stats', $clientResults)
                ->with('import_errors', array_slice($errors, 0, 10));

        } catch (Exception $e) {
            $this->logWebserviceOperation('error', 'Error en importación', [
                'error' => $e->getMessage(),
            ]);

            return redirect()->route('company.webservices.send')
                ->with('error', 'Error procesando archivo: ' . $e->getMessage());
        }
    }

    /**
     * ✨ CORREGIDO: Procesar clientes desde datos de manifiesto (SIN ROLES)
     */
    private function processClientsFromManifest(array $csvData): array
    {
        $results = ['created' => 0, 'updated' => 0, 'errors' => 0];
        $processedClients = [];
        
        foreach ($csvData as $record) {
            try {
                $clientData = $this->extractClientDataFromManifest($record);
                
                foreach ($clientData as $clientInfo) {
                    $key = $clientInfo['legal_name'] . '_' . ($clientInfo['tax_id'] ?? 'no_cuit');
                    
                    if (isset($processedClients[$key])) {
                        continue; // Ya procesado
                    }
                    
                    $existingClient = $this->findExistingClientInManifest($clientInfo);
                    
                    if ($existingClient) {
                        $existingClient->touch();
                        $processedClients[$key] = $existingClient;
                    } else {
                        $client = $this->createClientFromManifest($clientInfo);
                        if ($client) {
                            $results['created']++;
                            $processedClients[$key] = $client;
                        } else {
                            $results['errors']++;
                        }
                    }
                }
                
            } catch (Exception $e) {
                $results['errors']++;
                Log::error("Error procesando cliente: " . $e->getMessage());
            }
        }
        
        return $results;
    }

    /**
     * Extraer datos de cliente desde registro de manifiesto
     */
    private function extractClientDataFromManifest(array $record): array
    {
        $clients = [];
        
        // Shipper
        if (!empty($record['SHIPPER NAME'])) {
            $clients[] = [
                'legal_name' => trim($record['SHIPPER NAME']),
                'tax_id' => $this->extractTaxIdFromManifest($record['SHIPPER ADDRESS1'] ?? ''),
                'country_id' => 1, // Argentina
                'document_type_id' => 1,
                'created_by_company_id' => auth()->user()->getUserCompany()?->id ?? 1,
            ];
        }
        
        // Consignee
        if (!empty($record['CONSIGNEE NAME'])) {
            $clients[] = [
                'legal_name' => trim($record['CONSIGNEE NAME']),
                'tax_id' => $this->extractTaxIdFromManifest($record['CONSIGNEE ADDRESS1'] ?? ''),
                'country_id' => 2, // Paraguay
                'document_type_id' => 1,
                'created_by_company_id' => auth()->user()->getUserCompany()?->id ?? 1,
            ];
        }
        
        // Notify Party
        if (!empty($record['NOTIFY PARTY NAME'])) {
            $notifyName = trim($record['NOTIFY PARTY NAME']);
            if (strtoupper($notifyName) !== 'SAME AS CONSIGNEE') {
                $clients[] = [
                    'legal_name' => $notifyName,
                    'tax_id' => $this->extractTaxIdFromManifest($record['NOTIFY PARTY ADDRESS1'] ?? ''),
                    'country_id' => 2,
                    'document_type_id' => 1,
                    'created_by_company_id' => auth()->user()->getUserCompany()?->id ?? 1,
                ];
            }
        }
        
        return $clients;
    }

    private function extractTaxIdFromManifest(string $text): ?string
    {
        if (preg_match('/CUIT:\s*(\d{11})/', $text, $matches)) {
            return $matches[1];
        }
        if (preg_match('/\b(\d{11})\b/', $text, $matches)) {
            return $matches[1];
        }
        return null;
    }

    private function findExistingClientInManifest(array $clientInfo): ?Client
    {
        if (!empty($clientInfo['tax_id'])) {
            return Client::findByTaxId($clientInfo['tax_id'], $clientInfo['country_id']);
        }
        
        return Client::where('legal_name', $clientInfo['legal_name'])
            ->where('country_id', $clientInfo['country_id'])
            ->first();
    }

    private function createClientFromManifest(array $clientInfo): ?Client
    {
        try {
            return Client::create([
                'tax_id' => $clientInfo['tax_id'],
                'country_id' => $clientInfo['country_id'],
                'document_type_id' => $clientInfo['document_type_id'],
                'legal_name' => $clientInfo['legal_name'],
                'status' => 'active',
                'created_by_company_id' => $clientInfo['created_by_company_id'],
                'notes' => 'Creado desde import manifiesto'
            ]);
        } catch (Exception $e) {
            \Log::error("Error creando cliente: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Procesar archivo CSV y extraer datos
     */
    private function processCsvFile($file, string $manifestType): array
    {
        $csvContent = file_get_contents($file->getRealPath());
        
        // Detectar tipo automáticamente si es necesario
        if ($manifestType === 'auto_detect') {
            $manifestType = $this->detectCsvType($csvContent);
        }

        // Procesar según tipo
        return match($manifestType) {
            'parana' => $this->parseParanaCSV($csvContent),
            'guaran' => $this->parseGuaranCSV($csvContent),
            default => throw new Exception("Tipo de manifiesto no soportado: {$manifestType}")
        };
    }

    /**
     * Detectar tipo de CSV automáticamente
     */
    private function detectCsvType(string $content): string
    {
        // PARANA: Encabezados estándar con LOCATION NAME, ADDRESS LINE1, etc.
        if (str_contains($content, 'LOCATION NAME,ADDRESS LINE1') && 
            str_contains($content, 'MAERSK LINE')) {
            return 'parana';
        }

        // Guaran: Formato con metadatos "EDI To Custom", "User Name : Admin Admin"
        if (str_contains($content, 'EDI To Custom') && 
            str_contains($content, 'User Name : Admin')) {
            return 'guaran';
        }

        // Por defecto asumir PARANA (más simple)
        return 'parana';
    }

    /**
     * Parser específico para PARANA CSV
     */
    private function parseParanaCSV(string $content): array
    {
        $lines = str_getcsv($content, "\n");
        $data = [];
        $headers = null;

        foreach ($lines as $lineNumber => $line) {
            $row = str_getcsv($line);
            
            // Primera fila con datos = headers
            if ($headers === null && !empty($row[0])) {
                $headers = array_map('trim', $row);
                continue;
            }

            // Saltar filas vacías
            if (empty($row[0]) || count($row) < 10) {
                continue;
            }

            // Combinar headers con datos
            if ($headers && count($row) >= count($headers)) {
                $record = array_combine($headers, $row);
                if ($record && !empty($record['BL NUMBER'])) {
                    $data[] = $record;
                }
            }
        }

        return $data;
    }

    /**
     * Parser específico para Guaran CSV
     */
    private function parseGuaranCSV(string $content): array
    {
        $lines = str_getcsv($content, "\n");
        $data = [];
        $headers = null;
        $inDataSection = false;

        foreach ($lines as $line) {
            $row = str_getcsv($line);

            // Buscar inicio de datos después de metadatos
            if (!$inDataSection) {
                if (!empty($row[0]) && str_contains($row[0], 'LOCATION NAME')) {
                    $headers = array_map('trim', $row);
                    $inDataSection = true;
                }
                continue;
            }

            // Procesar datos
            if ($headers && !empty($row[0]) && count($row) >= count($headers)) {
                $record = array_combine($headers, $row);
                if ($record && !empty($record['BL NUMBER'])) {
                    $data[] = $record;
                }
            }
        }

        return $data;
    }

    /**
     * Validar registro individual del CSV
     */
    private function validateCsvRecord(array $record, string $manifestType): array
    {
        $errors = [];

        // Validaciones comunes
        if (empty($record['BL NUMBER'])) {
            $errors[] = 'BL NUMBER requerido';
        }

        if (empty($record['VOYAGE NO'])) {
            $errors[] = 'VOYAGE NO requerido';
        }

        if (empty($record['POL']) || empty($record['POD'])) {
            $errors[] = 'POL y POD requeridos';
        }

        // Validaciones específicas por tipo
        if ($manifestType === 'parana') {
            if (empty($record['BARGE NAME'])) {
                $errors[] = 'BARGE NAME requerido para PARANA';
            }
        }

        if (!empty($errors)) {
            return [
                'valid' => false,
                'error' => implode(', ', $errors),
                'data' => null
            ];
        }

        return [
            'valid' => true,
            'error' => null,
            'data' => $this->normalizeCsvRecord($record, $manifestType)
        ];
    }

    /**
     * Normalizar datos del CSV a formato estándar
     */
    private function normalizeCsvRecord(array $record, string $manifestType): array
    {
        return [
            'bl_number' => trim($record['BL NUMBER']),
            'voyage_number' => trim($record['VOYAGE NO']),
            'vessel_name' => trim($record['BARGE NAME'] ?? $record['VESSEL NAME'] ?? ''),
            'pol_code' => trim($record['POL']),
            'pod_code' => trim($record['POD']),
            'shipper_name' => trim($record['SHIPPER NAME'] ?? ''),
            'consignee_name' => trim($record['CONSIGNEE NAME'] ?? ''),
            'container_number' => trim($record['CONTAINER NUMBER'] ?? ''),
            'container_type' => trim($record['CONTAINER TYPE'] ?? ''),
            'gross_weight' => trim($record['GROSS WEIGHT'] ?? '0'),
            'manifest_type' => $manifestType,
            'raw_data' => $record
        ];
    }

    /**
     * Crear transacción webservice desde datos CSV
     */
    private function createWebserviceTransactionFromCsv(Company $company, array $data, array $validated): ?WebserviceTransaction
    {
        return WebserviceTransaction::create([
            'company_id' => $company->id,
            'user_id' => Auth::id(),
            'webservice_type' => $validated['webservice_type'],
            'country' => $this->getCountryFromWebserviceType($validated['webservice_type']),
            'environment' => $validated['environment'],
            'transaction_id' => 'CSV_' . $company->id . '_' . now()->format('YmdHis') . '_' . mt_rand(1000, 9999),
            'status' => 'pending',
            'bl_number' => $data['bl_number'],
            'voyage_number' => $data['voyage_number'],
            'vessel_name' => $data['vessel_name'],
            'pol_code' => $data['pol_code'],
            'pod_code' => $data['pod_code'],
            'request_data' => json_encode($data),
            'currency_code' => 'USD',
            'container_count' => 1,
            'bill_of_lading_count' => 1,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    /**
     * Obtener país desde tipo de webservice
     */
    private function getCountryFromWebserviceType(string $webserviceType): string
    {
        return match($webserviceType) {
            'argentina_anticipated', 'argentina_micdta' => 'AR',
            'paraguay_customs' => 'PY',
            default => 'AR'
        };
    }

    /**
     * Mostrar formulario de importación de manifiestos
     */
    public function showImport()
    {
        if (!$this->canPerform('manage_webservices') && !$this->hasRole('user')) {
            abort(403, 'No tiene permisos para importar manifiestos.');
        }

        $company = $this->getUserCompany();
        if (!$company) {
            return redirect()->route('company.dashboard')
                ->with('error', 'No se encontró la empresa asociada.');
        }

        // Verificar que webservices estén activos
        if (!$company->ws_active) {
            return redirect()->route('company.webservices.index')
                ->with('error', 'Los webservices están desactivados para su empresa.');
        }

        // Obtener configuración
        $companyRoles = $company->company_roles ?? [];
        $certificateStatus = $this->getCertificateStatus($company);
        
        // Tipos de webservices disponibles según roles
        $availableWebservices = $this->getAvailableWebserviceTypes($companyRoles);
        
        // Tipos de manifiesto soportados
        $manifestTypes = [
            'auto_detect' => 'Detectar automáticamente',
            'parana' => 'PARANA (MAERSK LINE)',
            'guaran' => 'Guaran Fee (Multi-línea)',
        ];

        // Ambientes disponibles
        $environments = [
            'testing' => 'Testing (Pruebas)',
            'production' => 'Producción',
        ];

        // Estadísticas recientes de importación
        $recentImports = WebserviceTransaction::forCompany($company->id)
            ->where('created_at', '>=', now()->subDays(7))
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count, webservice_type')
            ->groupBy(['date', 'webservice_type'])
            ->orderBy('date', 'desc')
            ->limit(10)
            ->get();

        return view('company.webservices.import', compact(
            'company',
            'companyRoles',
            'certificateStatus',
            'availableWebservices',
            'manifestTypes',
            'environments',
            'recentImports'
        ));
    }

    /**
     * =========================================================================
     * PROCESAR TRANSACCIÓN PENDING INDIVIDUAL
     * =========================================================================
     * 
     * Permite procesar una transacción específica con status 'pending'
     * usando la misma lógica de processWebserviceByType() existente.
     * 
     * FUNCIONALIDAD:
     * - Toma transacción 'pending' específica
     * - Usa servicios existentes (ArgentinaMicDtaService, ParaguayCustomsService, etc.)
     * - Procesa según tipo de webservice
     * - Actualiza status a 'success'/'error'
     * - Manejo completo de errores y logs
     * 
     * USO: Botón "Enviar Ahora" en historial de transacciones
     */

    /**
     * Procesar transacción pending individual
     * 
     * @param WebserviceTransaction $transaction
     * @return \Illuminate\Http\RedirectResponse
     */
/**
 * MÉTODO CORREGIDO: Procesar transacción pending individual
 * AGREGAR AL FINAL DE WebServiceController.php
 * 
 * Versión simplificada que usa ID directamente desde la ruta
 * Evita problemas de objetos sin ID
 */
public function processPendingTransaction(WebserviceTransaction $webservice)
{
    // AGREGAR ESTE LOG AL INICIO
    Log::info('DEBUG: Iniciando processPendingTransaction', [
        'transaction_id' => $webservice->id,
        'status' => $webservice->status,
        'webservice_type' => $webservice->webservice_type
    ]);

    // 1. Validaciones básicas
    if (!$this->canPerform('manage_webservices') && !$this->hasRole('user')) {
        abort(403, 'No tiene permisos para procesar transacciones.');
    }

    $company = $this->getUserCompany();
    if (!$company || $webservice->company_id !== $company->id) {
        abort(403, 'No tiene permisos para esta transacción.');
    }

    // 2. Verificar estado
    if ($webservice->status !== 'pending') {
        return redirect()->back()
            ->with('error', "La transacción no está en estado 'pending'. Estado actual: {$webservice->status}");
    }

    try {
        // 3. Log del procesamiento
        $this->logWebserviceOperation('info', 'Procesando transacción pending individual', [
            'transaction_id' => $webservice->id,
            'internal_transaction_id' => $webservice->transaction_id,
            'webservice_type' => $webservice->webservice_type,
            'user_id' => Auth::id(),
        ]);

        // 4. Preparar datos básicos
        $sendData = [
            'company' => $company,
            'environment' => $webservice->environment ?? 'testing',
        ];

        // 5. Cargar relaciones necesarias según el tipo
        if ($webservice->voyage_id) {
            // ✅ CORRECCIÓN: 'shipments.containers' en lugar de 'containers'
            $voyage = Voyage::with(['vessel', 'shipments.containers'])->find($webservice->voyage_id);
            if ($voyage) {
                $sendData['voyage'] = $voyage;
                if ($voyage->shipments->isNotEmpty()) {
                    $sendData['shipments'] = $voyage->shipments;
                }
            }
        }

        if ($webservice->shipment_id) {
            $shipment = Shipment::with(['voyage', 'containers', 'billsOfLading'])->find($webservice->shipment_id);
            if ($shipment) {
                $sendData['shipment'] = $shipment;
                if ($shipment->voyage) {
                    $sendData['voyage'] = $shipment->voyage;
                }
            }
        }

        // 6. ✅ CORRECCIÓN: Crear array $validated correctamente
        Log::info('DEBUG: Creando array validated');

        $validated = [
            'webservice_type' => $webservice->webservice_type,
            'country' => $webservice->country,
            'environment' => $webservice->environment ?? 'testing',
            'data_source' => $webservice->voyage_id ? 'voyage_id' : ($webservice->shipment_id ? 'shipment_id' : 'manual'),
            'voyage_id' => $webservice->voyage_id,
            'shipment_id' => $webservice->shipment_id,
            'send_immediately' => true,
        ];

        // 7. Actualizar a estado "sending"
        $webservice->update([
            'status' => 'sending',
            'sent_at' => now(),
        ]);

        // 8. ✅ CORRECCIÓN: Llamada correcta a processWebserviceByType()
        Log::info('DEBUG: Llamando processWebserviceByType', [
            'webservice_type' => $validated['webservice_type']
        ]);

        $result = $this->processWebserviceByType($webservice, $sendData, $validated);

        Log::info('DEBUG: processWebserviceByType completado', [
            'success' => $result['success'],
            'message' => $result['message'] ?? 'Sin mensaje'
        ]);

        // 9. Actualizar resultado final
        $webservice->update([
            'status' => $result['success'] ? 'success' : 'error',
            'response_at' => now(),
            'confirmation_number' => $result['confirmation_number'] ?? null,
            'error_code' => $result['error_code'] ?? null,
            'error_message' => $result['error_message'] ?? null,
            'success_data' => $result['success_data'] ?? null,
        ]);

        // 10. Respuesta al usuario
        if ($result['success']) {
            return redirect()->route('company.webservices.history', ['search' => $webservice->transaction_id])
                ->with('success', 'Transacción procesada exitosamente: ' . $result['message'])
                ->with('confirmation_number', $result['confirmation_number']);
        } else {
            return redirect()->route('company.webservices.history', ['search' => $webservice->transaction_id])
                ->with('error', 'Error procesando transacción: ' . $result['message'])
                ->with('error_details', $result['error_details'] ?? null);
        }

    } catch (Exception $e) {
         Log::error('DEBUG: Exception en processPendingTransaction', [
            'error' => $e->getMessage(),
            'line' => $e->getLine(),
            'file' => $e->getFile()
        ]);
        // Error crítico
        $webservice->update([
            'status' => 'error',
            'response_at' => now(),
            'error_code' => 'PROCESSING_ERROR',
            'error_message' => $e->getMessage(),
        ]);

        $this->logWebserviceOperation('error', 'Error crítico procesando pending', [
            'transaction_id' => $webservice->id,
            'error' => $e->getMessage(),
        ]);

        return redirect()->route('company.webservices.history', ['search' => $webservice->transaction_id])
            ->with('error', 'Error interno. Contacte al administrador.');
    }
}
    /**
     * Preparar datos para el envío desde transacción existente
     * 
     * @param WebserviceTransaction $transaction
     * @param Company $company
     * @return array
     */
    private function prepareSendDataFromTransaction(WebserviceTransaction $transaction, Company $company): array
    {
        $data = [
            'company' => $company,
            'environment' => $transaction->environment ?? 'testing',
        ];

        // Cargar datos relacionados según disponibilidad
        if ($transaction->voyage_id) {
            $voyage = Voyage::with(['vessel', 'shipments', 'containers'])->find($transaction->voyage_id);
            $data['voyage'] = $voyage;
            
            if ($voyage && $voyage->shipments->isNotEmpty()) {
                $data['shipments'] = $voyage->shipments;
            }
        }

        if ($transaction->shipment_id) {
            $shipment = Shipment::with(['voyage', 'containers', 'billsOfLading'])->find($transaction->shipment_id);
            $data['shipment'] = $shipment;
            
            if ($shipment && $shipment->voyage) {
                $data['voyage'] = $shipment->voyage;
            }
        }

        // Si hay datos manuales en metadata
        if (isset($transaction->additional_metadata['manual_data'])) {
            $data['manual_data'] = $transaction->additional_metadata['manual_data'];
        }

        return $data;
    }

}
