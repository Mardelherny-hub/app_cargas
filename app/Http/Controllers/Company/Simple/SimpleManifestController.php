<?php

namespace App\Http\Controllers\Company\Simple;

use App\Http\Controllers\Controller;
use App\Models\Voyage;
use App\Models\VoyageWebserviceStatus;
use App\Models\WebserviceTransaction;
use App\Models\WebserviceLog;
use App\Models\WebserviceResponse;
use App\Services\Simple\ArgentinaMicDtaService;
use App\Services\Simple\ArgentinaMicDtaStatusService;
use App\Services\Simple\ArgentinaMicDtaPositionService;
use App\Traits\UserHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Exception;

/**
 * SISTEMA MODULAR WEBSERVICES - SimpleManifestController
 * 
 * Controlador unificado que maneja TODOS los tipos de webservices aduaneros.
 * Diseño modular preparado para agregar webservices progresivamente.
 * 
 * FUNCIONALIDADES:
 * - Dashboard principal con selector de webservices
 * - Gestión MIC/DTA Argentina (FASE 1 - PRIORITARIO)
 * - Estructura preparada para otros webservices (FASE 2-5)
 * - Integración con servicios modulares Simple/
 * - Compatibilidad con datos existentes del sistema
 * 
 * WEBSERVICES SOPORTADOS (POR FASES):
 * ✅ FASE 1: MIC/DTA Argentina (ArgentinaMicDtaService)
 * 🔄 FASE 2: Información Anticipada Argentina  
 * 🔄 FASE 3: Manifiestos Paraguay
 * 🔄 FASE 4: Desconsolidados Argentina
 * 🔄 FASE 5: Transbordos Argentina/Paraguay
 * 
 * RUTAS MODULARES:
 * /simple/webservices/dashboard       - Dashboard principal
 * /simple/webservices/micdta/*        - MIC/DTA Argentina
 * /simple/webservices/anticipada/*    - Info Anticipada (FASE 2)
 * /simple/webservices/manifiesto/*    - Paraguay (FASE 3)
 * /simple/webservices/desconsolidado/* - Desconsolidados (FASE 4)
 * /simple/webservices/transbordo/*    - Transbordos (FASE 5)
 */
class SimpleManifestController extends Controller
{
    use UserHelper;

    /**
     * Tipos de webservices disponibles (se expande por fases)
     */
    private const WEBSERVICE_TYPES = [
        'micdta' => [
            'name' => 'MIC/DTA Argentina',
            'country' => 'AR',
            'description' => 'Manifiesto Internacional de Carga / Documento de Transporte Aduanero',
            'icon' => 'truck',
            'status' => 'active', // FASE 1
            'service_class' => ArgentinaMicDtaService::class,
        ],
        'anticipada' => [
            'name' => 'Información Anticipada Argentina',
            'country' => 'AR', 
            'description' => 'Envío anticipado de información de viaje a AFIP',
            'icon' => 'clock',
            'status' => 'coming_soon', // FASE 2
            'service_class' => null, // TODO: ArgentinaAnticipatedService::class,
        ],
        'manifiesto' => [
            'name' => 'Manifiestos Paraguay',
            'country' => 'PY',
            'description' => 'Manifiestos de carga para DNA Paraguay',
            'icon' => 'ship',
            'status' => 'coming_soon', // FASE 3
            'service_class' => null, // TODO: ParaguayManifestService::class,
        ],
        'desconsolidado' => [
            'name' => 'Desconsolidados Argentina',
            'country' => 'AR',
            'description' => 'Registro de desconsolidación de contenedores',
            'icon' => 'boxes',
            'status' => 'coming_soon', // FASE 4
            'service_class' => null, // TODO: ArgentinaDeconsolidationService::class,
        ],
        'transbordo' => [
            'name' => 'Transbordos Argentina/Paraguay',
            'country' => 'AR',
            'description' => 'Registro de transbordos y cambios de embarcación',
            'icon' => 'exchange-alt',
            'status' => 'coming_soon', // FASE 5
            'service_class' => null, // TODO: ArgentinaTransshipmentService::class,
        ],
    ];

    // ====================================
    // DASHBOARD PRINCIPAL
    // ====================================

    /**
     * Dashboard principal con selector de webservices
     */
    public function dashboard(Request $request)
    {
        // Verificar permisos
        /* if (!$this->canPerform('webservices.view')) {
            abort(403, 'No tiene permisos para acceder a webservices.');
        } */

        $company = $this->getUserCompany();
        if (!$company) {
            return redirect()->route('company.dashboard')
                ->with('error', 'No se encontró la empresa asociada.');
        }

        // Obtener voyages disponibles para webservices
        $voyages = Voyage::with([
            'leadVessel:id,name,registration_number',
            'originPort:id,name,code,country_id',
            'destinationPort:id,name,code,country_id',
            'webserviceStatuses' => function($query) {
                $query->whereIn('webservice_type', array_keys(self::WEBSERVICE_TYPES));
            }
        ])
        ->where('company_id', $company->id)
        ->where('active', true)
        ->whereNotNull('lead_vessel_id')
        ->latest()
        ->paginate(15);

        // Agregar estadísticas de webservices por voyage
        foreach ($voyages as $voyage) {
            $voyage->webservice_stats = $this->getVoyageWebserviceStats($voyage);
        }

        return view('company.simple.dashboard', [
            'voyages' => $voyages,
            'company' => $company,
            'webservice_types' => self::WEBSERVICE_TYPES,
            'active_webservices' => $this->getActiveWebserviceTypes(),
        ]);
    }

    // ====================================
    // MIC/DTA ARGENTINA (FASE 1)
    // ====================================

    /**
     * Lista de voyages para MIC/DTA Argentina
     */
    public function micDtaIndex(Request $request)
    {
        /* if (!$this->canPerform('webservices.micdta')) {
            abort(403, 'No tiene permisos para MIC/DTA.');
        } */

        $company = $this->getUserCompany();
        if (!$company) {
            return redirect()->route('company.simple.dashboard')
                ->with('error', 'No se encontró la empresa asociada.');
        }

        // Filtros específicos para MIC/DTA
        $voyagesQuery = Voyage::with([
            'leadVessel:id,name,registration_number',
            'originPort:id,name,code',
            'destinationPort:id,name,code',
            'shipments.billsOfLading',
            'webserviceStatuses' => function($query) {
                $query->where('webservice_type', 'micdta')
                      ->where('country', 'AR');
            }
        ])
        ->where('company_id', $company->id)
        ->whereNotNull('lead_vessel_id');

        // Filtro por estado MIC/DTA
        if ($request->filled('status')) {
            $voyagesQuery->whereHas('webserviceStatuses', function($query) use ($request) {
                $query->where('webservice_type', 'micdta')
                      ->where('status', $request->status);
            });
        }

        $voyages = $voyagesQuery->latest()->paginate(20);

        // Agregar información MIC/DTA específica
        foreach ($voyages as $voyage) {
            $voyage->micdta_status = $this->getMicDtaStatus($voyage);
            $voyage->micdta_validation = $this->validateVoyageForMicDta($voyage);
        }

        return view('company.simple.micdta.index', [
            'voyages' => $voyages,
            'company' => $company,
            'status_filter' => $request->status,
        ]);
    }
  

    // ====================================
    // MÉTODOS PREPARADOS PARA OTRAS FASES
    // ====================================

    /**
     * TODO FASE 2: Información Anticipada Argentina
     */
    public function anticipadaIndex(Request $request)
    {
        return $this->renderComingSoon('anticipada', 'Información Anticipada Argentina');
    }

    /**
     * TODO FASE 3: Manifiestos Paraguay
     */
    public function manifiestoIndex(Request $request)
    {
        return $this->renderComingSoon('manifiesto', 'Manifiestos Paraguay');
    }

    /**
     * TODO FASE 4: Desconsolidados Argentina
     */
    public function desconsolidadoIndex(Request $request)
    {
        return $this->renderComingSoon('desconsolidado', 'Desconsolidados Argentina');
    }

    /**
     * TODO FASE 5: Transbordos Argentina/Paraguay
     */
    public function transbordoIndex(Request $request)
    {
        return $this->renderComingSoon('transbordo', 'Transbordos Argentina/Paraguay');
    }

    // ====================================
    // MÉTODOS AUXILIARES
    // ====================================

    /**
     * Obtener estadísticas de webservices por voyage
     */
    private function getVoyageWebserviceStats(Voyage $voyage): array
    {
        $stats = [];
        
        foreach (array_keys(self::WEBSERVICE_TYPES) as $type) {
            $status = $voyage->webserviceStatuses
                ->where('webservice_type', $type)
                ->first();

            $stats[$type] = [
                'status' => $status ? $status->status : 'not_configured',
                'can_send' => $status ? $status->can_send : false,
                'last_sent_at' => $status ? $status->last_sent_at : null,
            ];
        }

        return $stats;
    }

  

    /**
     * Obtener tipos de webservices activos
     */
    private function getActiveWebserviceTypes(): array
    {
        return array_filter(self::WEBSERVICE_TYPES, function($type) {
            return $type['status'] === 'active';
        });
    }

    /**
     * Procesar envío MIC/DTA (AJAX) - MÉTODO CORREGIDO
     */
    public function micDtaSend(Request $request, Voyage $voyage)
    {
        try {
            // Validar permisos
            $company = $this->getUserCompany();
            if ($voyage->company_id !== $company->id) {
                return response()->json([
                    'success' => false,
                    'error' => 'Voyage no pertenece a su empresa.',
                ], 403);
            }

            // Validar que el voyage puede ser procesado
            $micDtaService = new ArgentinaMicDtaService($company, Auth::user());
            $validation = $micDtaService->canProcessVoyage($voyage);
            
            if (!$validation['can_process']) {
                return response()->json([
                    'success' => false,
                    'error' => 'Voyage no válido para MIC/DTA',
                    'validation_errors' => $validation['errors'],
                    'warnings' => $validation['warnings'],
                ], 400);
            }

            // Verificar que no está ya en proceso
            $status = $micDtaService->getWebserviceStatus($voyage);
            if (in_array($status->status, ['sending', 'validating'])) {
                return response()->json([
                    'success' => false,
                    'error' => 'MIC/DTA ya está siendo procesado',
                    'current_status' => $status->status,
                ], 409);
            }

            // ENVIAR WEBSERVICE CON SERVICIOS CORREGIDOS
            $result = $micDtaService->sendWebservice($voyage, [
                'force_send' => $request->boolean('force_send', false),
                'user_notes' => $request->input('notes', ''),
                'environment' => $company->ws_environment ?? 'testing',
            ]);

            // Procesar resultado
            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'message' => 'MIC/DTA enviado exitosamente',
                    'data' => [
                        'transaction_id' => $result['transaction_id'] ?? null,
                        'mic_dta_id' => $result['mic_dta_id'] ?? null,
                        'tracks_generated' => $result['tracks_saved'] ?? 0,
                        'timestamp' => now()->toISOString(),
                    ],
                    'redirect_url' => route('company.simple.micdta.show', $voyage->id),
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'error' => 'Error enviando MIC/DTA',
                    'details' => $result['error_message'] ?? 'Error desconocido',
                    'error_code' => $result['error_code'] ?? 'UNKNOWN_ERROR',
                    'transaction_id' => $result['transaction_id'] ?? null,
                ], 500);
            }

        } catch (Exception $e) {
            Log::error('Error en micDtaSend', [
                'voyage_id' => $voyage->id,
                'company_id' => $company->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error interno del servidor',
                'details' => app()->environment('local') ? $e->getMessage() : 'Contacte al administrador',
            ], 500);
        }
    }

    /**
     * Obtener estado detallado de MIC/DTA via AJAX
     */
    public function micDtaStatus(Voyage $voyage)
    {
        try {
            $company = $this->getUserCompany();
            if ($voyage->company_id !== $company->id) {
                return response()->json(['error' => 'No autorizado'], 403);
            }
            //obtener el shipment
            $shipment_id = $voyage->shipments()->first()->id;
            

            $micDtaService = new ArgentinaMicDtaService($company, Auth::user());
            $status = $micDtaService->getWebserviceStatus($voyage);
            $validation = $micDtaService->canProcessVoyage($voyage);

            // Obtener últimas transacciones
            $recentTransactions = WebserviceTransaction::where('voyage_id', $voyage->id)
                ->where('webservice_type', 'micdta')
                ->where('country', 'AR')
                ->latest()
                ->limit(5)
                ->get(['id', 'transaction_id', 'status', 'created_at', 'error_message']);

            // Obtener TRACKs si existen
            $tracks = \App\Models\WebserviceTrack::where('shipment_id', $shipment_id)
                ->where('status', 'used_in_micdta')
                ->get(['track_number', 'shipment_id', 'generated_at']);

            return response()->json([
                'status' => [
                    'current' => $status->status,
                    'can_send' => $validation['can_process'],
                    'last_sent_at' => $status->last_sent_at,
                    'retry_count' => $status->retry_count,
                ],
                'validation' => $validation,
                'tracks' => $tracks->groupBy('shipment_id'),
                'recent_transactions' => $recentTransactions,
                'timestamp' => now()->toISOString(),
            ]);

        } catch (Exception $e) {
            return response()->json([
                'error' => 'Error obteniendo estado MIC/DTA',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Reenviar MIC/DTA (para casos de error)
     */
    public function micDtaResend(Request $request, Voyage $voyage)
    {
        try {
            $company = $this->getUserCompany();
            if ($voyage->company_id !== $company->id) {
                return response()->json(['error' => 'No autorizado'], 403);
            }

            // Resetear estado para permitir reenvío
            $status = VoyageWebserviceStatus::where('voyage_id', $voyage->id)
                ->where('webservice_type', 'micdta')
                ->where('country', 'AR')
                ->first();

            if ($status) {
                $status->update([
                    'status' => 'pending',
                    'retry_count' => 0,
                    'last_error_message' => null,
                ]);
            }

            // Reenviar
            return $this->micDtaSend($request, $voyage);

        } catch (Exception $e) {
            return response()->json([
                'error' => 'Error reenviando MIC/DTA',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Previsualizar XML que se enviará (para debug)
     */
    public function micDtaPreviewXml(Voyage $voyage)
    {
        try {
            $company = $this->getUserCompany();
            if ($voyage->company_id !== $company->id) {
                return response()->json(['error' => 'No autorizado'], 403);
            }

            $micDtaService = new ArgentinaMicDtaService($company, Auth::user());
            $xmlGenerator = $micDtaService->getXmlSerializer();

            // Generar XMLs de ejemplo
            $shipment = $voyage->shipments()->first();
            if (!$shipment) {
                return response()->json(['error' => 'No hay shipments para previsualizar'], 400);
            }

            $xmlTitEnvios = $xmlGenerator->createRegistrarTitEnviosXml($shipment, 'PREVIEW_' . time());
            $xmlEnvios = $xmlGenerator->createRegistrarEnviosXml($shipment, 'PREVIEW_' . time());

            return response()->json([
                'preview' => [
                    'titenvios_xml' => $xmlTitEnvios,
                    'envios_xml' => $xmlEnvios,
                    'shipment_id' => $shipment->id,
                    'generated_at' => now()->toISOString(),
                ],
                'info' => [
                    'shipments_count' => $voyage->shipments()->count(),
                    'bills_of_lading_count' => $voyage->billsOfLading()->count(),
                    'total_weight' => $voyage->billsOfLading()
                        ->join('shipment_items', 'bills_of_lading.id', '=', 'shipment_items.bill_of_lading_id')
                        ->sum('shipment_items.gross_weight_kg'),
                ],
            ]);

        } catch (Exception $e) {
            return response()->json([
                'error' => 'Error generando preview XML',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * MÉTODOS FALTANTES - Agregar al SimpleManifestController.php
     */

    /**
     * Obtener estado específico MIC/DTA para un voyage
     */
    private function getMicDtaStatus(Voyage $voyage): ?VoyageWebserviceStatus
    {
        return VoyageWebserviceStatus::where('voyage_id', $voyage->id)
            ->where('company_id', $this->getUserCompany()->id)
            ->where('webservice_type', 'micdta')
            ->where('country', 'AR')
            ->first();
    }

    /**
     * Validar datos específicos para MIC/DTA Argentina
     */
    private function validateVoyageForMicDta(Voyage $voyage): array
    {
        try {
            $company = $this->getUserCompany();
            $micDtaService = new ArgentinaMicDtaService($company, Auth::user());
            return $micDtaService->canProcessVoyage($voyage);
        } catch (Exception $e) {
            return [
                'can_process' => false,
                'errors' => ['Error validando voyage: ' . $e->getMessage()],
                'warnings' => [],
            ];
        }
    }

    /**
     * Validar voyage via AJAX (para el JavaScript)
     */
    public function micDtaValidate(Voyage $voyage)
    {
        try {
            $company = $this->getUserCompany();
            if ($voyage->company_id !== $company->id) {
                return response()->json(['error' => 'No autorizado'], 403);
            }

            $validation = $this->validateVoyageForMicDta($voyage);
            
            return response()->json($validation);

        } catch (Exception $e) {
            return response()->json([
                'error' => 'Error en validación',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtener log de actividad via AJAX
     */
    public function micDtaActivity(Voyage $voyage)
    {
        try {
            $company = $this->getUserCompany();
            if ($voyage->company_id !== $company->id) {
                return response()->json(['error' => 'No autorizado'], 403);
            }

            // Obtener transacciones recientes
            $recentTransactions = WebserviceTransaction::where('voyage_id', $voyage->id)
                ->where('webservice_type', 'micdta')
                ->where('country', 'AR')
                ->latest()
                ->limit(10)
                ->get(['id', 'transaction_id', 'status', 'created_at', 'error_message']);

            // Obtener logs recientes
            $recentLogs = WebserviceLog::whereIn('transaction_id', $recentTransactions->pluck('id'))
                ->latest()
                ->limit(20)
                ->get(['level', 'message', 'created_at', 'context']);

            return response()->json([
                'recent_transactions' => $recentTransactions,
                'recent_logs' => $recentLogs,
                'timestamp' => now()->toISOString(),
            ]);

        } catch (Exception $e) {
            return response()->json([
                'error' => 'Error cargando actividad',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Completar método micDtaShow con datos faltantes
     */
    public function micDtaShow(Voyage $voyage)
    {
        $company = $this->getUserCompany();
        if ($voyage->company_id !== $company->id) {
            abort(403, 'Voyage no pertenece a su empresa.');
        }

        // Cargar relaciones necesarias
        $voyage->load([
            'leadVessel',
            'originPort',
            'destinationPort',
            'shipments.billsOfLading.shipmentItems',
            'billsOfLading'
        ]);

        // Obtener estado y validación MIC/DTA
        $micdta_status = $this->getMicDtaStatus($voyage);
        $validation = $this->validateVoyageForMicDta($voyage);

        // Obtener información del certificado
        $certificateManager = new \App\Services\Webservice\CertificateManagerService($company);
        $certificateValidation = $certificateManager->validateCompanyCertificate();

        return view('company.simple.micdta.form', [
            'voyage' => $voyage,
            'company' => $company,
            'micdta_status' => $micdta_status,
            'validation' => $validation,
            'certificate_valid' => $certificateValidation['is_valid'],
            'certificate_errors' => $certificateValidation['errors'] ?? [],
        ]);
    }

    /**
     * Método para obtener información de configuración de empresa
     */
    private function getCompanyWebserviceConfig()
    {
        $company = $this->getUserCompany();
        
        return [
            'ws_environment' => $company->ws_environment ?? 'testing',
            'ws_active' => $company->ws_active ?? false,
            'certificate_configured' => !empty($company->certificate_path),
            'certificate_path' => $company->certificate_path,
        ];
    }

    /**
     * Método auxiliar para verificar si puede enviar MIC/DTA
     */
    private function canSendMicDta(Voyage $voyage): bool
    {
        $validation = $this->validateVoyageForMicDta($voyage);
        $status = $this->getMicDtaStatus($voyage);
        
        return $validation['can_process'] && 
               (!$status || !in_array($status->status, ['sending', 'validating']));
    }

    /**
     * Método para limpiar estado de webservice (útil para testing)
     */
    public function micDtaReset(Voyage $voyage)
    {
        try {
            $company = $this->getUserCompany();
            if ($voyage->company_id !== $company->id) {
                return response()->json(['error' => 'No autorizado'], 403);
            }

            // Solo en ambiente de desarrollo/testing
            if (app()->environment('production')) {
                return response()->json(['error' => 'No disponible en producción'], 403);
            }

            // Resetear estado
            VoyageWebserviceStatus::where('voyage_id', $voyage->id)
                ->where('webservice_type', 'micdta')
                ->where('country', 'AR')
                ->delete();

            return response()->json([
                'success' => true,
                'message' => 'Estado MIC/DTA reseteado',
            ]);

        } catch (Exception $e) {
            return response()->json([
                'error' => 'Error reseteando estado',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Renderizar página "próximamente" para fases futuras
     */
    private function renderComingSoon(string $type, string $name): \Illuminate\View\View
    {
        return view('company.simple.coming-soon', [
            'webservice_type' => $type,
            'webservice_name' => $name,
            'webservice_info' => self::WEBSERVICE_TYPES[$type] ?? null,
            'current_phase' => 1, // FASE 1 activa
        ]);
    }

    /**
     * ================================================================================
     * CONSULTAS DE ESTADO MIC/DTA - NUEVA FUNCIONALIDAD
     * ================================================================================
     */

    /**
     * Consultar estado AFIP de un voyage específico (AJAX)
     * 
     * Ruta: GET /simple/webservices/micdta/{voyage}/consultar-estado
     * 
     * @param int $voyageId
     * @return \Illuminate\Http\JsonResponse
     */
    public function consultarEstadoIndividual(int $voyageId)
    {
        try {
            $company = $this->getUserCompany();
            $user = auth()->user();
            
            if (!$company || !$user) {
                return response()->json([
                    'success' => false,
                    'error' => 'Usuario o empresa no encontrados'
                ], 403);
            }

            // Verificar que el voyage existe y pertenece a la empresa
            $voyage = Voyage::where('id', $voyageId)
                ->where('company_id', $company->id)
                ->with(['leadVessel', 'originPort', 'destinationPort'])
                ->first();

            if (!$voyage) {
                return response()->json([
                    'success' => false,
                    'error' => 'Voyage no encontrado'
                ], 404);
            }

            // Buscar transacciones MIC/DTA exitosas usando WebserviceTransaction
            $transacciones = WebserviceTransaction::where('voyage_id', $voyage->id)
                ->where('company_id', $company->id)
                ->where('webservice_type', 'micdta')
                ->where('status', 'sent')
                ->whereNotNull('external_reference')
                ->get();

            if ($transacciones->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'error' => 'No hay transacciones MIC/DTA enviadas para consultar',
                    'voyage_number' => $voyage->voyage_number,
                ]);
            }

            // Usar el servicio de consulta de estados
            $statusService = new ArgentinaMicDtaStatusService($company, $user);
            $transactionIds = $transacciones->pluck('id')->toArray();
            
            $resultado = $statusService->consultarEstadoTransacciones($transactionIds);

            return response()->json([
                'success' => true,
                'voyage_number' => $voyage->voyage_number,
                'transacciones_consultadas' => count($transactionIds),
                'resultado' => $resultado,
                'timestamp' => now()->toISOString(),
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Error consultando estado: ' . $e->getMessage(),
                'voyage_id' => $voyageId,
            ], 500);
        }
    }
    /**
     * Consultar estados de todos los voyages pendientes (AJAX)
     * 
     * Ruta: POST /simple/webservices/micdta/consultar-estados-masivo
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function consultarEstadoMasivo(Request $request)
    {
        try {
            $company = $this->getUserCompany();
            $user = auth()->user();

            if (!$company || !$user) {
                return response()->json([
                    'success' => false,
                    'error' => 'Usuario o empresa no encontrados'
                ], 403);
            }

            // Usar el servicio para consultar todos los pendientes
            $statusService = new ArgentinaMicDtaStatusService($company, $user);
            $resultado = $statusService->consultarEstadoTransacciones();

            return response()->json([
                'success' => true,
                'company_name' => $company->legal_name,
                'resultado' => $resultado,
                'timestamp' => now()->toISOString(),
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Error en consulta masiva: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtener historial de consultas de un voyage (AJAX)
     * 
     * Ruta: GET /simple/webservices/micdta/{voyage}/historial-consultas
     * 
     * @param int $voyageId
     * @return \Illuminate\Http\JsonResponse
     */
    public function obtenerHistorialConsultas(int $voyageId)
    {
        try {
            $company = $this->getUserCompany();
            
            if (!$company) {
                return response()->json([
                    'success' => false,
                    'error' => 'Empresa no encontrada'
                ], 403);
            }

            $voyage = Voyage::where('id', $voyageId)
                ->where('company_id', $company->id)
                ->first();

            if (!$voyage) {
                return response()->json([
                    'success' => false,
                    'error' => 'Voyage no encontrado'
                ], 404);
            }

            // Obtener historial usando modelos existentes
            $transacciones = WebserviceTransaction::where('voyage_id', $voyage->id)
                ->where('company_id', $company->id)
                ->whereIn('webservice_type', ['micdta', 'micdta_status'])
                ->orderBy('created_at', 'desc')
                ->get();

            $historial = [];
            foreach ($transacciones as $transaccion) {
                $historial[] = [
                    'transaction_id' => $transaccion->id,
                    'transaction_external_id' => $transaccion->transaction_id,
                    'webservice_type' => $transaccion->webservice_type,
                    'status' => $transaccion->status,
                    'method' => $transaccion->method_name,
                    'sent_at' => $transaccion->sent_at?->format('d/m/Y H:i'),
                    'response_at' => $transaccion->response_at?->format('d/m/Y H:i'),
                    'response_time_ms' => $transaccion->response_time_ms,
                    'external_reference' => $transaccion->external_reference,
                    'error_message' => $transaccion->error_message,
                ];
            }

            return response()->json([
                'success' => true,
                'voyage_number' => $voyage->voyage_number,
                'historial' => $historial,
                'total_transacciones' => count($historial),
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Error obteniendo historial: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtener estado AFIP actual de un voyage para la vista (AJAX)
     * 
     * Ruta: GET /simple/webservices/micdta/{voyage}/estado-afip
     * 
     * @param int $voyageId
     * @return \Illuminate\Http\JsonResponse
     */
    public function obtenerEstadoAfip(int $voyageId)
    {
        try {
            $company = $this->getUserCompany();
            
            if (!$company) {
                return response()->json([
                    'success' => false,
                    'error' => 'Empresa no encontrada'
                ], 403);
            }

            $voyage = Voyage::where('id', $voyageId)
                ->where('company_id', $company->id)
                ->first();

            if (!$voyage) {
                return response()->json([
                    'success' => false,
                    'error' => 'Voyage no encontrado'
                ], 404);
            }

            // USAR EL MÉTODO EXISTENTE getMicDtaStatus() que devuelve VoyageWebserviceStatus
            $micDtaStatus = $this->getMicDtaStatus($voyage);
            
            // Obtener la transacción MIC/DTA más reciente
            $transaccion = WebserviceTransaction::where('voyage_id', $voyage->id)
                ->where('company_id', $company->id)
                ->where('webservice_type', 'micdta')
                ->where('status', 'sent')
                ->whereNotNull('external_reference')
                ->latest('sent_at')
                ->first();

            if (!$transaccion) {
                return response()->json([
                    'success' => false,
                    'estado_afip' => [
                        'codigo' => 'no_enviado',
                        'descripcion' => 'MIC/DTA no enviado',
                        'color' => 'gray',
                        'icono' => 'clock',
                        'accion' => 'enviar',
                    ]
                ]);
            }

            // Obtener la consulta de estado más reciente
            $consultaEstado = WebserviceTransaction::where('voyage_id', $voyage->id)
                ->where('company_id', $company->id)
                ->where('webservice_type', 'micdta_status')
                ->latest('created_at')
                ->first();

            $estadoAfip = $this->determinarEstadoAfipParaVista($transaccion, $consultaEstado);

            return response()->json([
                'success' => true,
                'voyage_number' => $voyage->voyage_number,
                'external_reference' => $transaccion->external_reference,
                'sent_at' => $transaccion->sent_at?->format('d/m/Y H:i'),
                'last_check' => $consultaEstado?->created_at?->format('d/m/Y H:i'),
                'estado_afip' => $estadoAfip,
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Error obteniendo estado: ' . $e->getMessage(),
            ], 500);
        }
    }

    // ======================= MÉTODOS AUXILIARES =======================

    /**
     * Actualizar cache de estados para un voyage específico
     */
    private function actualizarCacheEstadosVoyage(Voyage $voyage): void
    {
        try {
            // Actualizar información MIC/DTA del voyage
            $voyage->micdta_status = $this->getMicDtaStatus($voyage);
            $voyage->micdta_validation = $this->validateVoyageForMicDta($voyage);
            
            // Actualizar campos calculados si es necesario
            $voyage->loadMissing([
                'webserviceTransactions' => function($query) {
                    $query->where('webservice_type', 'micdta')
                        ->whereNotNull('external_reference');
                }
            ]);

        } catch (Exception $e) {
            // Log error pero no fallar
            \Log::warning('Error actualizando cache estados voyage', [
                'voyage_id' => $voyage->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Actualizar cache de estados para toda la empresa
     */
    private function actualizarCacheEstadosEmpresa($company): void
    {
        try {
            // Invalidar cache de estados si se usa
            // Esto forza a recalcular estados en la próxima carga de vista
            \Cache::forget("micdta_states_{$company->id}");
            
        } catch (Exception $e) {
            \Log::warning('Error actualizando cache empresa', [
                'company_id' => $company->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Determinar estado AFIP para mostrar en vista
     */
    private function determinarEstadoAfipParaVista($transaccion, $consultaEstado): array
    {
        $default = [
            'codigo' => 'unknown',
            'descripcion' => 'Estado desconocido',
            'color' => 'gray',
            'icono' => 'question',
            'accion' => 'consultar',
            'puede_consultar' => true,
        ];

        if (!$transaccion) {
            return array_merge($default, [
                'codigo' => 'no_enviado',
                'descripcion' => 'No enviado',
                'accion' => 'enviar',
                'puede_consultar' => false,
            ]);
        }

        // Si no hay consulta de estado, mostrar como "pendiente consulta"
        if (!$consultaEstado) {
            return array_merge($default, [
                'codigo' => 'pendiente_consulta',
                'descripcion' => 'Pendiente consulta',
                'color' => 'yellow',
                'icono' => 'clock',
                'accion' => 'consultar',
            ]);
        }

        // Mapear estados según metadata de consulta
        $metadata = $consultaEstado->additional_metadata;
        if (isset($metadata['afip_status']['estado_normalizado'])) {
            $estado = $metadata['afip_status']['estado_normalizado'];
            
            $mapeoEstados = [
                'approved' => [
                    'codigo' => 'aprobado',
                    'descripcion' => 'Aprobado por AFIP',
                    'color' => 'green',
                    'icono' => 'check',
                    'accion' => 'ver',
                    'puede_consultar' => true,
                ],
                'rejected' => [
                    'codigo' => 'rechazado', 
                    'descripcion' => 'Rechazado por AFIP',
                    'color' => 'red',
                    'icono' => 'x',
                    'accion' => 'ver_error',
                    'puede_consultar' => true,
                ],
                'processing' => [
                    'codigo' => 'procesando',
                    'descripcion' => 'En procesamiento',
                    'color' => 'blue',
                    'icono' => 'loader',
                    'accion' => 'consultar',
                    'puede_consultar' => true,
                ],
                'pending' => [
                    'codigo' => 'pendiente',
                    'descripcion' => 'Pendiente en AFIP',
                    'color' => 'yellow',
                    'icono' => 'clock',
                    'accion' => 'consultar',
                    'puede_consultar' => true,
                ],
            ];

            return $mapeoEstados[$estado] ?? $default;
        }

        return $default;
    }

    /**
     * Helper para obtener el estado string del voyage (compatible con vista)
     * CORRIGE el problema de acceso a objeto como array
     */
    private function getMicDtaStatusString(Voyage $voyage): string
    {
        $statusObject = $this->getMicDtaStatus($voyage);
        
        if (!$statusObject) {
            return 'not_sent';
        }
        
        // Mapear estados del objeto VoyageWebserviceStatus a strings simples para la vista
        $statusMap = [
            'sent' => 'sent',
            'approved' => 'sent', // Para efectos de la vista, ambos se muestran como "enviado"
            'pending' => 'pending',
            'error' => 'error',
            'rejected' => 'error',
            'sending' => 'pending',
            'validating' => 'pending',
        ];
        
        return $statusMap[$statusObject->status] ?? 'not_sent';
    }

    /**
     * ================================================================================
     * FIN EXTENSIÓN CONSULTAS ESTADO
     * ================================================================================
     */


    /**
     * Actualizar posición GPS de un voyage específico (AJAX)
     * 
     * Ruta: POST /simple/webservices/micdta/{voyage}/actualizar-posicion
     * 
     * @param Request $request
     * @param int $voyageId
     * @return \Illuminate\Http\JsonResponse
     */
    public function actualizarPosicionIndividual(Request $request, int $voyageId)
    {
        try {
            // Validar entrada
            $request->validate([
                'latitude' => 'required|numeric|between:-90,90',
                'longitude' => 'required|numeric|between:-180,180',
                'source' => 'nullable|string|max:50',
            ]);

            $company = $this->getUserCompany();
            $user = auth()->user();
            
            if (!$company || !$user) {
                return response()->json([
                    'success' => false,
                    'error' => 'Usuario o empresa no encontrados'
                ], 403);
            }

            // Verificar que el voyage existe y pertenece a la empresa
            $voyage = Voyage::where('id', $voyageId)
                ->where('company_id', $company->id)
                ->with(['leadVessel', 'originPort', 'destinationPort', 'shipments'])
                ->first();

            if (!$voyage) {
                return response()->json([
                    'success' => false,
                    'error' => 'Voyage no encontrado'
                ], 404);
            }

            // Usar el servicio de actualización GPS
            $positionService = new ArgentinaMicDtaPositionService($company, $user);
            $resultado = $positionService->actualizarPosicion(
                $voyage,
                $request->input('latitude'),
                $request->input('longitude'),
                [
                    'source' => $request->input('source', 'manual'),
                    'user_notes' => $request->input('notes', ''),
                ]
            );

            // Respuesta exitosa
            if ($resultado['success']) {
                return response()->json([
                    'success' => true,
                    'message' => $resultado['message'],
                    'voyage_number' => $voyage->voyage_number,
                    'coordinates' => $resultado['coordinates'],
                    'control_point_detected' => $resultado['control_point_detected'] ?? null,
                    'distance_moved_meters' => $resultado['distance_moved_meters'] ?? null,
                    'time_since_last_update' => $resultado['time_since_last_update'] ?? null,
                    'transaction_id' => $resultado['transaction_id'] ?? null,
                    'skipped' => $resultado['skipped'] ?? false,
                    'timestamp' => now()->toISOString(),
                ]);
            } else {
                // Respuesta de error
                return response()->json([
                    'success' => false,
                    'error' => $resultado['error'],
                    'voyage_number' => $voyage->voyage_number,
                    'transaction_id' => $resultado['transaction_id'] ?? null,
                ], 400);
            }

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Datos de entrada inválidos',
                'validation_errors' => $e->errors(),
            ], 422);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Error interno: ' . $e->getMessage(),
                'voyage_id' => $voyageId,
            ], 500);
        }
    }

    /**
     * Actualizar posiciones GPS de múltiples shipments de un voyage (AJAX)
     * 
     * Ruta: POST /simple/webservices/micdta/{voyage}/actualizar-posiciones-masiva
     * 
     * @param Request $request
     * @param int $voyageId
     * @return \Illuminate\Http\JsonResponse
     */
    public function actualizarPosicionMasiva(Request $request, int $voyageId)
    {
        try {
            $company = $this->getUserCompany();
            $user = auth()->user();
            
            if (!$company || !$user) {
                return response()->json([
                    'success' => false,
                    'error' => 'Usuario o empresa no encontrados'
                ], 403);
            }

            // Verificar que el voyage existe y pertenece a la empresa
            $voyage = Voyage::where('id', $voyageId)
                ->where('company_id', $company->id)
                ->with(['leadVessel', 'originPort', 'destinationPort', 'shipments'])
                ->first();

            if (!$voyage) {
                return response()->json([
                    'success' => false,
                    'error' => 'Voyage no encontrado'
                ], 404);
            }

            // Usar el servicio para actualización masiva
            $positionService = new ArgentinaMicDtaPositionService($company, $user);
            $resultado = $positionService->actualizarPosicionesVoyage($voyage);

            return response()->json([
                'success' => $resultado['success'],
                'message' => $resultado['message'],
                'voyage_number' => $voyage->voyage_number,
                'shipments_processed' => $resultado['shipments_processed'] ?? 0,
                'updates_sent' => $resultado['updates_sent'] ?? 0,
                'errors' => $resultado['errors'] ?? 0,
                'skipped' => $resultado['skipped'] ?? 0,
                'resultados' => $resultado['resultados'] ?? [],
                'timestamp' => now()->toISOString(),
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Error en actualización masiva: ' . $e->getMessage(),
                'voyage_id' => $voyageId,
            ], 500);
        }
    }

    /**
     * Obtener historial de posiciones GPS de un voyage (AJAX)
     * 
     * Ruta: GET /simple/webservices/micdta/{voyage}/historial-posiciones
     * 
     * @param Request $request
     * @param int $voyageId
     * @return \Illuminate\Http\JsonResponse
     */
    public function obtenerHistorialPosiciones(Request $request, int $voyageId)
    {
        try {
            $company = $this->getUserCompany();
            
            if (!$company) {
                return response()->json([
                    'success' => false,
                    'error' => 'Empresa no encontrada'
                ], 403);
            }

            $voyage = Voyage::where('id', $voyageId)
                ->where('company_id', $company->id)
                ->first();

            if (!$voyage) {
                return response()->json([
                    'success' => false,
                    'error' => 'Voyage no encontrado'
                ], 404);
            }

            // Parámetros opcionales
            $dias = $request->input('days', 7);
            $dias = min(max($dias, 1), 30); // Entre 1 y 30 días

            // Usar el servicio para obtener historial
            $positionService = new ArgentinaMicDtaPositionService($company, auth()->user());
            $historial = $positionService->obtenerHistorialPosiciones($voyage, $dias);

            // Obtener estadísticas adicionales
            $estadisticas = $this->calcularEstadisticasGPS($historial);

            return response()->json([
                'success' => true,
                'voyage_number' => $voyage->voyage_number,
                'historial' => $historial,
                'estadisticas' => $estadisticas,
                'periodo_dias' => $dias,
                'total_posiciones' => count($historial),
                'timestamp' => now()->toISOString(),
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Error obteniendo historial: ' . $e->getMessage(),
                'voyage_id' => $voyageId,
            ], 500);
        }
    }

    /**
     * Obtener puntos de control AFIP disponibles (AJAX)
     * 
     * Ruta: GET /simple/webservices/micdta/puntos-control
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function obtenerPuntosControl(Request $request)
    {
        try {
            // Puntos de control AFIP para hidrovía Paraná
            $puntosControl = [
                'ARBUE' => [
                    'codigo' => 'ARBUE',
                    'nombre' => 'Puerto Buenos Aires',
                    'pais' => 'Argentina',
                    'coordenadas' => [
                        'lat' => -34.6118,
                        'lng' => -58.3960,
                    ],
                    'radio_km' => 5,
                    'descripcion' => 'Puerto principal de Buenos Aires en el Río de la Plata',
                ],
                'ARROS' => [
                    'codigo' => 'ARROS',
                    'nombre' => 'Puerto Rosario',
                    'pais' => 'Argentina',
                    'coordenadas' => [
                        'lat' => -32.9442,
                        'lng' => -60.6505,
                    ],
                    'radio_km' => 3,
                    'descripcion' => 'Puerto cerealero de Rosario, Santa Fe',
                ],
                'PYASU' => [
                    'codigo' => 'PYASU',
                    'nombre' => 'Puerto Asunción',
                    'pais' => 'Paraguay',
                    'coordenadas' => [
                        'lat' => -25.2637,
                        'lng' => -57.5759,
                    ],
                    'radio_km' => 4,
                    'descripcion' => 'Puerto principal de Asunción, Paraguay',
                ],
                'PYTVT' => [
                    'codigo' => 'PYTVT',
                    'nombre' => 'Terminal Villeta',
                    'pais' => 'Paraguay',
                    'coordenadas' => [
                        'lat' => -25.5097,
                        'lng' => -57.5522,
                    ],
                    'radio_km' => 2,
                    'descripcion' => 'Terminal de contenedores Villeta, Paraguay',
                ],
            ];

            return response()->json([
                'success' => true,
                'puntos_control' => array_values($puntosControl),
                'total_puntos' => count($puntosControl),
                'hidropía' => 'Paraná',
                'timestamp' => now()->toISOString(),
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Error obteniendo puntos de control: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtener estado GPS actual de un voyage para la vista (AJAX)
     * 
     * Ruta: GET /simple/webservices/micdta/{voyage}/estado-gps
     * 
     * @param int $voyageId
     * @return \Illuminate\Http\JsonResponse
     */
    public function obtenerEstadoGps(int $voyageId)
    {
        try {
            $company = $this->getUserCompany();
            
            if (!$company) {
                return response()->json([
                    'success' => false,
                    'error' => 'Empresa no encontrada'
                ], 403);
            }

            $voyage = Voyage::where('id', $voyageId)
                ->where('company_id', $company->id)
                ->with(['shipments' => function($query) {
                    $query->whereNotNull('current_latitude')
                        ->whereNotNull('current_longitude');
                }])
                ->first();

            if (!$voyage) {
                return response()->json([
                    'success' => false,
                    'error' => 'Voyage no encontrado'
                ], 404);
            }

            // Obtener última actualización GPS enviada a AFIP
            $ultimaActualizacion = WebserviceTransaction::where('voyage_id', $voyage->id)
                ->where('company_id', $company->id)
                ->where('webservice_type', 'micdta_position')
                ->where('status', 'sent')
                ->latest('sent_at')
                ->first();

            // Obtener coordenadas actuales de shipments
            $shipmentsConGps = $voyage->shipments->filter(function($shipment) {
                return $shipment->current_latitude && $shipment->current_longitude;
            });

            // Verificar si puede actualizar GPS (versión segura sin reflection)
            $puedeActualizar = $this->puedeActualizarGpsSafe($voyage);

            $estadoGps = [
                'tiene_coordenadas' => $shipmentsConGps->isNotEmpty(),
                'shipments_con_gps' => $shipmentsConGps->count(),
                'total_shipments' => $voyage->shipments->count(),
                'ultima_actualizacion_afip' => $ultimaActualizacion ? [
                    'enviada_at' => $ultimaActualizacion->sent_at->toISOString(),
                    'coordenadas' => $ultimaActualizacion->additional_metadata['coordinates'] ?? null,
                    'punto_control' => $ultimaActualizacion->additional_metadata['control_point_detected'] ?? null,
                    'transaction_id' => $ultimaActualizacion->id,
                ] : null,
                'coordenadas_actuales' => $shipmentsConGps->map(function($shipment) {
                    return [
                        'shipment_id' => $shipment->id,
                        'lat' => $shipment->current_latitude,
                        'lng' => $shipment->current_longitude,
                        'actualizada_at' => $shipment->position_updated_at?->toISOString(),
                    ];
                })->values(),
                'puede_actualizar' => $puedeActualizar,
            ];

            return response()->json([
                'success' => true,
                'voyage_number' => $voyage->voyage_number,
                'estado_gps' => $estadoGps,
                'timestamp' => now()->toISOString(),
            ]);

        } catch (Exception $e) {
            // Log del error para debugging
            \Log::error('Error en obtenerEstadoGps', [
                'voyage_id' => $voyageId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error obteniendo estado GPS: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Verificar si el voyage puede recibir actualizaciones GPS (versión segura)
     */
    private function puedeActualizarGpsSafe(Voyage $voyage): array
    {
        try {
            // Verificar si el voyage tiene MIC/DTA enviado exitosamente
            $micDtaEnviado = WebserviceTransaction::where('voyage_id', $voyage->id)
                ->where('company_id', $voyage->company_id)
                ->where('webservice_type', 'micdta')
                ->where('status', 'sent')
                ->whereNotNull('external_reference')
                ->exists();

            if (!$micDtaEnviado) {
                return [
                    'puede_actualizar' => false,
                    'razon' => 'MIC/DTA no enviado o no exitoso'
                ];
            }

            // Verificar si el voyage tiene shipments
            if ($voyage->shipments->isEmpty()) {
                return [
                    'puede_actualizar' => false,
                    'razon' => 'Voyage sin shipments'
                ];
            }

            // Verificar límites de actualización diarios (máximo 96 por día = cada 15 min)
            $actualizacionesHoy = WebserviceTransaction::where('voyage_id', $voyage->id)
                ->where('company_id', $voyage->company_id)
                ->where('webservice_type', 'micdta_position')
                ->whereDate('created_at', today())
                ->count();

            if ($actualizacionesHoy >= 96) {
                return [
                    'puede_actualizar' => false,
                    'razon' => 'Límite diario de actualizaciones alcanzado (96/día)'
                ];
            }

            // Verificar intervalo mínimo (15 minutos)
            $ultimaActualizacion = WebserviceTransaction::where('voyage_id', $voyage->id)
                ->where('company_id', $voyage->company_id)
                ->where('webservice_type', 'micdta_position')
                ->latest('created_at')
                ->first();

            if ($ultimaActualizacion) {
                $minutosSinceUltima = now()->diffInMinutes($ultimaActualizacion->created_at);
                if ($minutosSinceUltima < 15) {
                    return [
                        'puede_actualizar' => false,
                        'razon' => "Debe esperar {$minutosSinceUltima} minutos más (mínimo 15 min entre actualizaciones)"
                    ];
                }
            }

            return [
                'puede_actualizar' => true,
                'razon' => 'Voyage listo para actualización GPS'
            ];

        } catch (Exception $e) {
            \Log::error('Error en puedeActualizarGpsSafe', [
                'voyage_id' => $voyage->id,
                'error' => $e->getMessage()
            ]);

            // En caso de error, permitir actualización pero con advertencia
            return [
                'puede_actualizar' => true,
                'razon' => 'Verificación de estado no disponible - permitiendo actualización'
            ];
        }
    }

    /**
     * ======================= MÉTODOS AUXILIARES GPS =======================
     */

    /**
     * Calcular estadísticas del historial GPS
     */
    private function calcularEstadisticasGPS(array $historial): array
    {
        if (empty($historial)) {
            return [
                'total_puntos' => 0,
                'distancia_total_km' => 0,
                'puntos_control_detectados' => 0,
                'periodo_activo_horas' => 0,
            ];
        }

        $totalPuntos = count($historial);
        $distanciaTotal = 0;
        $puntosControl = 0;

        // Calcular distancia total recorrida
        for ($i = 1; $i < count($historial); $i++) {
            $punto1 = $historial[$i - 1];
            $punto2 = $historial[$i];
            
            $distanciaTotal += $this->calcularDistanciaHaversine(
                $punto1['lat'], $punto1['lng'],
                $punto2['lat'], $punto2['lng']
            ) / 1000; // Convertir a km
        }

        // Contar puntos de control detectados
        foreach ($historial as $punto) {
            if ($punto['control_point']) {
                $puntosControl++;
            }
        }

        // Calcular periodo activo
        $periodoHoras = 0;
        if (count($historial) > 1) {
            $primero = strtotime($historial[count($historial) - 1]['timestamp']);
            $ultimo = strtotime($historial[0]['timestamp']);
            $periodoHoras = ($ultimo - $primero) / 3600;
        }

        return [
            'total_puntos' => $totalPuntos,
            'distancia_total_km' => round($distanciaTotal, 2),
            'puntos_control_detectados' => $puntosControl,
            'periodo_activo_horas' => round($periodoHoras, 1),
            'velocidad_promedio_kmh' => $periodoHoras > 0 ? round($distanciaTotal / $periodoHoras, 1) : 0,
        ];
    }

    /**
     * Verificar si el voyage puede recibir actualizaciones GPS
     */
    private function puedeActualizarGps(Voyage $voyage): array
    {
        try {
            $company = $this->getUserCompany();
            $positionService = new ArgentinaMicDtaPositionService($company, auth()->user());
            
            // Usar validación interna del servicio
            $reflection = new \ReflectionClass($positionService);
            $method = $reflection->getMethod('validarVoyageParaActualizacion');
            $method->setAccessible(true);
            
            $validacion = $method->invokeArgs($positionService, [$voyage]);
            
            return [
                'puede_actualizar' => $validacion['puede_actualizar'],
                'razon' => $validacion['error'] ?? 'Voyage válido para actualizaciones GPS',
                'tiene_micdta' => isset($validacion['micdta_transaction']),
            ];
            
        } catch (Exception $e) {
            return [
                'puede_actualizar' => false,
                'razon' => 'Error verificando permisos GPS: ' . $e->getMessage(),
                'tiene_micdta' => false,
            ];
        }
    }

    /**
     * Calcular distancia entre dos puntos GPS (fórmula haversine)
     */
    private function calcularDistanciaHaversine(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371000; // Radio de la Tierra en metros

        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLng / 2) * sin($dLng / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    /**
     * ================================================================================
     * MÉTODOS AUXILIARES GPS ADICIONALES
     * ================================================================================
     */

    /**
     * Validar coordenadas GPS sin enviar a AFIP (AJAX)
     * 
     * Ruta: POST /simple/webservices/micdta/validar-coordenadas
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function validarCoordenadas(Request $request)
    {
        try {
            $request->validate([
                'latitude' => 'required|numeric|between:-90,90',
                'longitude' => 'required|numeric|between:-180,180',
            ]);

            $lat = $request->input('latitude');
            $lng = $request->input('longitude');

            $validaciones = [];

            // Validación básica GPS
            $validaciones['gps_valido'] = [
                'valido' => true,
                'mensaje' => 'Coordenadas GPS válidas',
            ];

            // Validación específica hidrovía Paraná
            $enHidrovia = ($lat >= -35 && $lat <= -20) && ($lng >= -62 && $lng <= -54);
            $validaciones['hidrovia_parana'] = [
                'valido' => $enHidrovia,
                'mensaje' => $enHidrovia 
                    ? 'Coordenadas dentro del rango esperado para hidrovía Paraná' 
                    : 'Advertencia: Coordenadas fuera del rango típico de hidrovía Paraná',
            ];

            // Detectar punto de control cercano
            $puntoControl = $this->detectarPuntoControlPorCoordenadas($lat, $lng);
            $validaciones['punto_control'] = [
                'detectado' => $puntoControl !== null,
                'punto' => $puntoControl,
                'mensaje' => $puntoControl 
                    ? "Cerca de punto de control: {$puntoControl['nombre']}" 
                    : 'No hay puntos de control AFIP cercanos',
            ];

            // Calcular distancia a puertos principales
            $distancias = $this->calcularDistanciasPuertos($lat, $lng);
            $validaciones['distancias_puertos'] = $distancias;

            return response()->json([
                'success' => true,
                'coordenadas' => [
                    'lat' => $lat,
                    'lng' => $lng,
                ],
                'validaciones' => $validaciones,
                'puede_enviar_afip' => $validaciones['gps_valido']['valido'],
                'recomendacion' => $enHidrovia 
                    ? 'Coordenadas aptas para envío AFIP' 
                    : 'Revisar coordenadas - pueden estar fuera del área típica',
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Coordenadas inválidas',
                'validation_errors' => $e->errors(),
            ], 422);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Error validando coordenadas: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Detectar punto de control por coordenadas (AJAX)
     * 
     * Ruta: POST /simple/webservices/micdta/detectar-punto-control
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function detectarPuntoControl(Request $request)
    {
        try {
            $request->validate([
                'latitude' => 'required|numeric|between:-90,90',
                'longitude' => 'required|numeric|between:-180,180',
                'radio_km' => 'nullable|numeric|min:0.1|max:50',
            ]);

            $lat = $request->input('latitude');
            $lng = $request->input('longitude');
            $radioKm = $request->input('radio_km', 10); // Radio por defecto 10km

            $puntoDetectado = $this->detectarPuntoControlPorCoordenadas($lat, $lng, $radioKm);
            
            // Obtener todos los puntos con distancias
            $todosPuntos = $this->calcularDistanciasTodosPuntos($lat, $lng);

            return response()->json([
                'success' => true,
                'coordenadas' => [
                    'lat' => $lat,
                    'lng' => $lng,
                ],
                'radio_busqueda_km' => $radioKm,
                'punto_detectado' => $puntoDetectado,
                'todos_los_puntos' => $todosPuntos,
                'detectado' => $puntoDetectado !== null,
                'timestamp' => now()->toISOString(),
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Parámetros inválidos',
                'validation_errors' => $e->errors(),
            ], 422);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Error detectando punto de control: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtener configuración GPS AFIP (AJAX)
     * 
     * Ruta: GET /simple/webservices/micdta/config-gps
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function obtenerConfigGps(Request $request)
    {
        try {
            $company = $this->getUserCompany();
            
            if (!$company) {
                return response()->json([
                    'success' => false,
                    'error' => 'Empresa no encontrada'
                ], 403);
            }

            $config = [
                'limites_coordenadas' => [
                    'latitud' => ['min' => -90, 'max' => 90],
                    'longitud' => ['min' => -180, 'max' => 180],
                ],
                'hidrovia_parana' => [
                    'latitud' => ['min' => -35, 'max' => -20],
                    'longitud' => ['min' => -62, 'max' => -54],
                    'descripcion' => 'Rango típico para hidrovía Paraná (Argentina-Paraguay)',
                ],
                'actualizacion_gps' => [
                    'intervalo_minimo_minutos' => 15,
                    'max_actualizaciones_diarias' => 96,
                    'tolerancia_movimiento_metros' => 50,
                    'descripcion' => 'Configuración AFIP para actualizaciones GPS',
                ],
                'puntos_control' => [
                    'total' => 4,
                    'radio_deteccion_km' => [
                        'ARBUE' => 5,
                        'ARROS' => 3,
                        'PYASU' => 4,
                        'PYTVT' => 2,
                    ],
                    'descripcion' => 'Puntos de control AFIP en hidrovía Paraná',
                ],
                'webservice_afip' => [
                    'ambiente' => $company->ws_environment ?? 'testing',
                    'url_testing' => 'https://wsaduhomoext.afip.gob.ar/DIAV2/wgesregsintia2/wgesregsintia2.asmx?wsdl',
                    'url_production' => 'https://webservicesadu.afip.gob.ar/DIAV2/wgesregsintia2/wgesregsintia2.asmx?wsdl',
                    'metodo' => 'ActualizarPosicion',
                    'timeout_segundos' => 30,
                ],
                'empresa' => [
                    'cuit' => $company->tax_id,
                    'razon_social' => $company->legal_name,
                    'certificado_configurado' => !empty($company->certificate_path),
                    'ambiente_webservice' => $company->ws_environment ?? 'testing',
                ],
            ];

            return response()->json([
                'success' => true,
                'configuracion_gps' => $config,
                'timestamp' => now()->toISOString(),
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Error obteniendo configuración GPS: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * ======================= MÉTODOS AUXILIARES PRIVADOS GPS =======================
     */

    /**
     * Detectar punto de control por coordenadas
     */
    private function detectarPuntoControlPorCoordenadas(float $lat, float $lng, float $radioKm = 10): ?array
    {
        $puntosControl = [
            'ARBUE' => [
                'codigo' => 'ARBUE',
                'nombre' => 'Puerto Buenos Aires',
                'lat' => -34.6118,
                'lng' => -58.3960,
                'radio_km' => 5,
            ],
            'ARROS' => [
                'codigo' => 'ARROS',
                'nombre' => 'Puerto Rosario',
                'lat' => -32.9442,
                'lng' => -60.6505,
                'radio_km' => 3,
            ],
            'PYASU' => [
                'codigo' => 'PYASU',
                'nombre' => 'Puerto Asunción',
                'lat' => -25.2637,
                'lng' => -57.5759,
                'radio_km' => 4,
            ],
            'PYTVT' => [
                'codigo' => 'PYTVT',
                'nombre' => 'Terminal Villeta',
                'lat' => -25.5097,
                'lng' => -57.5522,
                'radio_km' => 2,
            ],
        ];

        foreach ($puntosControl as $codigo => $punto) {
            $distanciaKm = $this->calcularDistanciaHaversine(
                $lat, $lng, 
                $punto['lat'], $punto['lng']
            ) / 1000;
            
            $radioEfectivo = min($radioKm, $punto['radio_km']);
            
            if ($distanciaKm <= $radioEfectivo) {
                return [
                    'codigo' => $codigo,
                    'nombre' => $punto['nombre'],
                    'distancia_km' => round($distanciaKm, 2),
                    'radio_deteccion_km' => $radioEfectivo,
                    'coordenadas' => [
                        'lat' => $punto['lat'],
                        'lng' => $punto['lng'],
                    ],
                ];
            }
        }

        return null;
    }

    /**
     * Calcular distancias a todos los puntos de control
     */
    private function calcularDistanciasTodosPuntos(float $lat, float $lng): array
    {
        $puntosControl = [
            'ARBUE' => ['nombre' => 'Puerto Buenos Aires', 'lat' => -34.6118, 'lng' => -58.3960],
            'ARROS' => ['nombre' => 'Puerto Rosario', 'lat' => -32.9442, 'lng' => -60.6505],
            'PYASU' => ['nombre' => 'Puerto Asunción', 'lat' => -25.2637, 'lng' => -57.5759],
            'PYTVT' => ['nombre' => 'Terminal Villeta', 'lat' => -25.5097, 'lng' => -57.5522],
        ];

        $distancias = [];
        foreach ($puntosControl as $codigo => $punto) {
            $distanciaKm = $this->calcularDistanciaHaversine(
                $lat, $lng, 
                $punto['lat'], $punto['lng']
            ) / 1000;

            $distancias[] = [
                'codigo' => $codigo,
                'nombre' => $punto['nombre'],
                'distancia_km' => round($distanciaKm, 2),
                'coordenadas' => [
                    'lat' => $punto['lat'],
                    'lng' => $punto['lng'],
                ],
            ];
        }

        // Ordenar por distancia
        usort($distancias, function($a, $b) {
            return $a['distancia_km'] <=> $b['distancia_km'];
        });

        return $distancias;
    }

    /**
     * Calcular distancias a puertos principales
     */
    private function calcularDistanciasPuertos(float $lat, float $lng): array
    {
        $puertos = [
            'Buenos Aires' => ['lat' => -34.6118, 'lng' => -58.3960],
            'Rosario' => ['lat' => -32.9442, 'lng' => -60.6505],
            'Asunción' => ['lat' => -25.2637, 'lng' => -57.5759],
            'Villeta' => ['lat' => -25.5097, 'lng' => -57.5522],
        ];

        $distancias = [];
        foreach ($puertos as $nombre => $coordenadas) {
            $distanciaKm = $this->calcularDistanciaHaversine(
                $lat, $lng,
                $coordenadas['lat'], $coordenadas['lng']
            ) / 1000;

            $distancias[$nombre] = [
                'distancia_km' => round($distanciaKm, 2),
                'coordenadas' => $coordenadas,
            ];
        }

        return $distancias;
    }
}