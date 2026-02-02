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
use App\Services\Simple\ArgentinaAnticipatedService;
// ⬇️ NUEVO: import del servicio PY (lo implemento en el siguiente paso)
use App\Services\Simple\ParaguayDnaService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Services\Simple\ArgentinaDeconsolidatedService;

use App\Traits\UserHelper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
        // 🇦🇷 ARGENTINA - ORDEN OBLIGATORIO DE ENVÍO
        'anticipada' => [
            'name' => 'Información Anticipada',
            'country' => 'AR', 
            'description' => 'Envío anticipado de información de viaje a AFIP (para IMPORTACIÓN)',
            'icon' => 'clock',
            'status' => 'active',
            'priority' => 1, // ← NUEVO
            'requires' => null, // ← NUEVO: no requiere nada previo
            'service_class' => ArgentinaAnticipatedService::class,
        ],
        'micdta' => [
            'name' => 'MIC/DTA',
            'country' => 'AR',
            'description' => 'MIC/DTA - Para EXPORTACIÓN de mercadería (proceso independiente)',
            'icon' => 'truck',
            'status' => 'active',
            'priority' => 2, // ← NUEVO
            //'requires' => 'anticipada', // ← NUEVO: requiere anticipada enviada
            'service_class' => ArgentinaMicDtaService::class,
        ],
        'desconsolidado' => [
            'name' => 'Desconsolidados',
            'country' => 'AR',
            'description' => 'Registro de desconsolidación de contenedores',
            'icon' => 'boxes',
            'status' => 'coming_soon',
            'priority' => 3, // ← NUEVO
            'requires' => 'anticipada', // ← NUEVO
            'service_class' => null,
        ],
        'transbordo' => [
            'name' => 'Transbordos',
            'country' => 'AR',
            'description' => 'Registro de transbordos y cambios de embarcación',
            'icon' => 'exchange-alt',
            'status' => 'coming_soon',
            'priority' => 4, // ← NUEVO
            'requires' => 'anticipada', // ← NUEVO
            'service_class' => null,
        ],
        
        // 🇵🇾 PARAGUAY - MANIFIESTO FLUVIAL (GDSF)
        'manifiesto' => [
            'name' => 'Manifiesto Fluvial (DNA)',
            'country' => 'PY',
            'description' => 'XFFM/XFBL/XFBT/XISP/XRSP/XFCT vía DNA GDSF',
            'icon' => 'ship',
            'status' => 'active',              
            'priority' => 3,
            'requires' => null,
            'service_class' => ParaguayDnaService::class, 
        ],
    ];

        /**
     * ================================================================================
     * MÉTODO GENÉRICO AFIP - CORAZÓN DEL SISTEMA
     * ================================================================================
     * 
     * Método genérico que maneja TODOS los 18 métodos AFIP de manera consistente.
     * Centraliza la lógica: validación → servicio → respuesta → auditoría
     * 
     * @param string $method Nombre del método AFIP (ej: 'RegistrarTitEnvios')
     * @param Request $request Datos de entrada del usuario
     * @param Voyage $voyage Voyage a procesar
     * @return array Resultado con success/error/data/transaction_id
     */
    private function executeAfipMethod(string $method, Request $request, Voyage $voyage): array
    {
        try {
            // 1. VALIDACIONES BÁSICAS
            $company = $this->getUserCompany();
            if ($voyage->company_id !== $company->id) {
                return [
                    'success' => false,
                    'error' => 'Viaje no pertenece a su empresa',
                    'error_code' => 'UNAUTHORIZED_VOYAGE'
                ];
            }

            // 2. INSTANCIAR SERVICIO MICDTA
            $micDtaService = new ArgentinaMicDtaService($company, Auth::user());
            
            // 3. VALIDAR QUE EL Viaje PUEDE SER PROCESADO  
            $validation = $micDtaService->canProcessVoyage($voyage);
            if (!$validation['can_process']) {
                return [
                    'success' => false,
                    'error' => 'Viaje no válido para ' . $method,
                    'validation_errors' => $validation['errors'],
                    'warnings' => $validation['warnings'],
                    'error_code' => 'VALIDATION_FAILED'
                ];
            }

            // 4. PREPARAR DATOS ESPECÍFICOS DEL MÉTODO
            $methodData = $this->prepareMethodData($method, $request, $voyage);
            
            // 5. EJECUTAR MÉTODO EN EL SERVICIO  
            $result = $micDtaService->executeMethod($method, $voyage, $methodData);
            
            // 6. PROCESAR RESULTADO Y RESPONDER
            if ($result['success']) {
                return [
                    'success' => true,
                    'message' => $method . ' ejecutado exitosamente',
                    'data' => [
                        'transaction_id' => $result['transaction_id'] ?? null,
                        'external_reference' => $result['external_reference'] ?? null,
                        'method' => $method,
                        'timestamp' => now()->toISOString(),
                        'tracks_generated' => $result['tracks_generated'] ?? 0,
                        'response_data' => $result['response_data'] ?? null
                    ]
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'Error ejecutando ' . $method,
                    'details' => $result['error_message'] ?? 'Error desconocido',
                    'error_code' => $result['error_code'] ?? 'METHOD_EXECUTION_FAILED',
                    'transaction_id' => $result['transaction_id'] ?? null
                ];
            }

        } catch (Exception $e) {
            // LOG CRÍTICO DE ERROR
            \Log::error('Error crítico en executeAfipMethod', [
                'method' => $method,
                'voyage_id' => $voyage->id,
                'company_id' => $company->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => 'Error interno del servidor',
                'details' => app()->environment('local') ? $e->getMessage() : 'Contacte al administrador',
                'error_code' => 'INTERNAL_SERVER_ERROR'
            ];
        }
    }

    /**
     * Preparar datos específicos según el método AFIP
     * 
     * @param string $method Método AFIP
     * @param Request $request Request con datos
     * @param Voyage $voyage Voyage a procesar
     * @return array Datos preparados para el método
     */
    private function prepareMethodData(string $method, Request $request, Voyage $voyage): array
    {
        $baseData = [
            'force_send' => $request->boolean('force_send', false),
            'user_notes' => $request->input('notes', ''),
            'environment' => $this->getUserCompany()->ws_environment ?? 'testing',
        ];

        // DATOS ESPECÍFICOS POR MÉTODO
        switch ($method) {
            case 'RegistrarTitEnvios':
            case 'RegistrarEnvios':
            case 'RegistrarMicDta':
                // Métodos principales - usar datos base
                return $baseData;
                
            case 'RegistrarConvoy':
                return array_merge($baseData, [
                    'convoy_id' => $request->input('convoy_id'),
                    'convoy_name' => $request->input('convoy_name', 'CONVOY_' . $voyage->voyage_number),
                    'convoy_sequence' => $request->input('convoy_sequence', 1)
                ]);
                
            case 'AsignarATARemol':
                return array_merge($baseData, [
                    'remolcador_id' => $request->input('remolcador_id'),
                    'ata_remolcador' => $request->input('ata_remolcador')
                ]);
                
            case 'RegistrarTitMicDta':
                return array_merge($baseData, [
                    'id_micdta' => $request->input('id_micdta'),
                    'titulos' => $request->input('titulos', [])
                ]);
                
            case 'DesvincularTitMicDta':
                return array_merge($baseData, [
                    'id_micdta' => $request->input('id_micdta'),
                    'titulos' => $request->input('titulos', [])
                ]);
                
            case 'AnularTitulo':
                return array_merge($baseData, [
                    'titulo_id' => $request->input('titulo_id', 'ALL'),  // ✅ Default 'ALL'
                    'anular_todos' => $request->input('anular_todos', true),  // ✅ Agregar
                    'motivo_anulacion' => $request->input('motivo_anulacion', 'Anulación total solicitada')
                ]);
                \Log::info('🟢 prepareMethodData AnularTitulo', ['result' => $result]);

            case 'AnularEnvios':
                return array_merge($baseData, [
                    'anular_todos' => $request->boolean('anular_todos', false),
                    'motivo_anulacion' => $request->input('motivo_anulacion', ''),
                    'envios_ids' => $request->input('envios_ids', []),
                    'tracks' => $request->input('tracks', [])
                ]);
                              
            case 'RegistrarSalidaZonaPrimaria':
                return array_merge($baseData, [
                    'fecha_salida' => $request->input('fecha_salida'),
                    'puerto_salida' => $request->input('puerto_salida'),
                    'aduana_salida' => $request->input('aduana_salida'),
                ]);
                
            case 'RegistrarArriboZonaPrimaria':
                return array_merge($baseData, [
                    'nro_viaje' => $request->input('nro_viaje'),
                    'cod_adu' => $request->input('cod_adu'),
                    'cod_lug_oper' => $request->input('cod_lug_oper'),
                    'desc_amarre' => $request->input('desc_amarre', ''),
                ]);
                
            case 'AnularArriboZonaPrimaria':
                return array_merge($baseData, [
                    'arribo_id' => $request->input('arribo_id'),
                    'motivo_anulacion' => $request->input('motivo_anulacion', ''),
                ]);
                
            case 'ConsultarMicDtaAsig':
            case 'ConsultarTitEnviosReg':
            case 'ConsultarPrecumplido':
                // Métodos de consulta - solo necesitan datos base
                return $baseData;
                
            case 'SolicitarAnularMicDta':
                return array_merge($baseData, [
                    'id_micdta' => $request->input('micdta_id'),
                    'desc_motivo' => $request->input('motivo_anulacion', ''),
                ]);
                
            case 'Dummy':
                // Método de test - solo necesita datos base
                return $baseData;
                
            case 'RegistrarConvoy':
                return array_merge($baseData, [
                    'convoy_id' => $request->input('convoy_id'),
                    'convoy_name' => $request->input('convoy_name', 'CONVOY_' . $voyage->voyage_number),
                    'convoy_sequence' => $request->input('convoy_sequence', 1),
                ]);
                
            case 'AsignarATARemol':
                return array_merge($baseData, [
                    'remolcador_id' => $request->input('remolcador_id'),
                    'ata_remolcador' => $request->input('ata_remolcador'),
                    'micdta_id' => $request->input('micdta_id'),
                ]);
                
            case 'RectifConvoyMicDta':
                return array_merge($baseData, [
                    'convoy_id' => $request->input('convoy_id'),
                    'micdta_id' => $request->input('micdta_id'),
                    'campo_a_rectificar' => $request->input('campo_a_rectificar'),
                    'valor_nuevo' => $request->input('valor_nuevo'),
                ]);
                
            default:
            
                return $baseData; 
        }
    }

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
    // INFORMACIÓN ANTICIPADA ARGENTINA
    // ====================================

    /**
     * Lista de voyages para Información Anticipada Argentina
     */
    public function anticipadaIndex(Request $request)
    {
        if (!$this->canPerform('manage_webservices')) {
            abort(403, 'No tiene permisos para gestionar webservices.');
        }

        $company = $this->getUserCompany();
        if (!$company) {
            return redirect()->route('company.simple.dashboard')
                ->with('error', 'No se encontró la empresa asociada.');
        }

        $voyagesQuery = Voyage::where('company_id', $company->id)
            ->with([
                'originPort', 'destinationPort', 'leadVessel',
                'webserviceStatuses' => function($q) {
                    $q->where('webservice_type', 'anticipada');
                }
            ])
            ->whereHas('shipments');

        // Filtro por estado si se proporciona
        if ($request->filled('status')) {
            $voyagesQuery->whereHas('webserviceStatuses', function($query) use ($request) {
                $query->where('webservice_type', 'anticipada')
                    ->where('status', $request->status);
            });
        }

        $voyages = $voyagesQuery->orderBy('departure_date', 'desc')->paginate(15);

        return view('company.simple.anticipada.index', [
            'voyages' => $voyages,
            'company' => $company,
            'status_filter' => $request->status,
            'webservice_type' => 'anticipada',
            'webservice_config' => self::WEBSERVICE_TYPES['anticipada'],
        ]);
    }

    /**
     * Vista detallada para envío de Información Anticipada
     */
    public function anticipadaShow(Voyage $voyage)
    {
        if (!$this->canPerform('manage_webservices')) {
            abort(403, 'No tiene permisos para gestionar webservices.');
        }

        $company = $this->getUserCompany();
        if ($voyage->company_id !== $company->id) {
            abort(403, 'No tiene permisos para este voyage.');
        }

        // Cargar relaciones necesarias
        $voyage->load([
            'company', 'leadVessel', 'captain',
            'originPort.country', 'destinationPort.country',
            'shipments.vessel', 'shipments.captain',
            'shipments.billsOfLading.shipmentItems.containers',
            'webserviceStatuses' => function($q) {
                $q->where('webservice_type', 'anticipada');
            }
        ]);

        // Validar con el servicio
        $service = new ArgentinaAnticipatedService($company, auth()->user());
        $validation = $service->canProcessVoyage($voyage);

        // Obtener historial de transacciones
        $transactions = $voyage->webserviceTransactions()
            ->where('webservice_type', 'anticipada')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return view('company.simple.anticipada.show', [
            'voyage' => $voyage,
            'validation' => $validation,
            'transactions' => $transactions,
            'webservice_config' => self::WEBSERVICE_TYPES['anticipada'],
        ]);
    }

    /**
     * Procesar envío de Información Anticipada (AJAX)
     */
    public function anticipadaSend(Request $request, Voyage $voyage)
    {
        try {
            // 1. VERIFICAR PERMISOS
            if (!$this->canPerform('manage_webservices')) {
                return response()->json([
                    'success' => false, 
                    'error' => 'Sin permisos para gestionar webservices',
                    'error_code' => 'PERMISSION_DENIED'
                ], 403);
            }

            $company = $this->getUserCompany();
            if ($voyage->company_id !== $company->id) {
                return response()->json([
                    'success' => false, 
                    'error' => 'Viaje no pertenece a su empresa',
                    'error_code' => 'UNAUTHORIZED'
                ], 403);
            }

            // 2. VALIDAR MÉTODO
            $method = $request->input('method', 'RegistrarViaje');
            $validMethods = ['RegistrarViaje', 'RectificarViaje', 'RegistrarTitulosCbc', 'CerrarViaje'];
            
            if (!in_array($method, $validMethods)) {
                return response()->json([
                    'success' => false, 
                    'error' => 'Método no válido',
                    'error_code' => 'INVALID_METHOD'
                ], 400);
            }

            // 3. CREAR SERVICIO Y VALIDAR VOYAGE
            $service = new \App\Services\Simple\ArgentinaAnticipatedService($company, auth()->user());
            
            // ✅ VALIDACIÓN PREVIA
            $validation = $service->canProcessVoyage($voyage);
            
            if (!$validation['can_process']) {
                return response()->json([
                    'success' => false,
                    'error' => "Viaje no válido para {$method}",
                    'validation_errors' => $validation['errors'],
                    'warnings' => $validation['warnings'],
                    'error_code' => 'VALIDATION_FAILED'
                ]);
            }

            // 4. PREPARAR OPCIONES
            $options = [
                'method' => $method,
                'user_notes' => $request->input('notes', ''),
                'environment' => $request->input('environment', 'testing'),
            ];

            // Si es rectificación, agregar datos adicionales
            if ($method === 'RectificarViaje') {
                $options['rectification_reason'] = $request->input('rectification_reason', '');
            }

            // 5. ENVIAR A AFIP
            $result = $service->sendWebservice($voyage, $options);

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'message' => "{$method} enviado exitosamente a AFIP",
                    'data' => [
                        'transaction_id' => $result['transaction_id'],
                        'external_reference' => $result['external_reference'] ?? null,
                        'method' => $method,
                    ]
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'error' => $result['error_message'] ?? 'Error en envío a AFIP',
                    'error_code' => $result['error_code'] ?? 'WEBSERVICE_ERROR',
                    'validation_errors' => $result['validation_errors'] ?? [],
                    'warnings' => $result['warnings'] ?? [],
                    'data' => [
                        'transaction_id' => $result['transaction_id'] ?? null,
                        'method' => $method,
                    ]
                ]);
            }

        } catch (Exception $e) {
            Log::error('Error en anticipadaSend: ' . $e->getMessage(), [
                'voyage_id' => $voyage->id,
                'user_id' => auth()->id(),
                'method' => $request->input('method'),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error interno del servidor',
                'details' => config('app.debug') ? $e->getMessage() : null,
                'error_code' => 'INTERNAL_ERROR'
            ], 500);
        }
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
    // =============================
    // DESCONSOLIDADO (AFIP) – SHOW
    // =============================    
    /**
     * DESCONSOLIDADO - ENVIAR (REGISTRAR/RECTIFICAR/ELIMINAR)
     * Maneja las 3 operaciones según el parámetro 'action'
     */
    public function desconsolidadoSend(Request $request, $voyageId)
    {
        $voyage = Voyage::findOrFail($voyageId);
        
        // Verificar acceso
        $company = $this->getUserCompany();
        if ($voyage->company_id !== $company->id) {
            return redirect()->back()->with('error', 'No tiene permisos para este viaje.');
        }

        // Obtener acción (registrar, rectificar, eliminar)
        $action = $request->input('action', 'registrar');
        
        try {
            $user = $this->getCurrentUser();
            $service = new ArgentinaDeconsolidatedService($company, $user);

            // Ejecutar según la acción
            switch ($action) {
                case 'registrar':
                    $result = $service->registrarTitulos($voyage);
                    $successMessage = 'Títulos desconsolidados registrados exitosamente en AFIP.';
                    break;
                    
                case 'rectificar':
                    $result = $service->rectificarTitulos($voyage);
                    $successMessage = 'Títulos desconsolidados rectificados exitosamente en AFIP.';
                    break;
                    
                case 'eliminar':
                    $result = $service->eliminarTitulos($voyage);
                    $successMessage = 'Títulos desconsolidados eliminados exitosamente en AFIP.';
                    break;
                    
                default:
                    return redirect()->back()->with('error', 'Acción no válida.');
            }

            // Procesar resultado
            if ($result['success']) {
                return redirect()
                    ->route('company.manifests.simple.desconsolidado.show', $voyage)
                    ->with('success', $successMessage);
            } else {
                return redirect()
                    ->route('company.manifests.simple.desconsolidado.show', $voyage)
                    ->with('error', 'Error: ' . ($result['error'] ?? 'Error desconocido'));
            }

        } catch (Exception $e) {
            Log::error('Error en desconsolidadoSend', [
                'voyage_id' => $voyage->id,
                'action' => $action,
                'error' => $e->getMessage(),
            ]);

            return redirect()
                ->route('company.manifests.simple.desconsolidado.show', $voyage)
                ->with('error', 'Error procesando solicitud: ' . $e->getMessage());
        }
    }

    /**
     * DESCONSOLIDADO - MOSTRAR VISTA
     * Prepara todas las variables necesarias para la vista de desconsolidados
     */
    public function desconsolidadoShow($voyageId)
    {
        // 1. Cargar voyage con relaciones
        $voyage = Voyage::with([
            'leadVessel',
            'originPort',
            'destinationPort',
            'company',
            'billsOfLading.shipmentItems',
            'billsOfLading.loadingPort',
            'billsOfLading.dischargePort'
        ])->findOrFail($voyageId);

        // 2. Verificar acceso de empresa
        $company = $this->getUserCompany();
        if ($voyage->company_id !== $company->id) {
            abort(403, 'No tiene permisos para ver este viaje.');
        }

        // 3. Contar BLs desconsolidados (con master_bill_number)
        $desconsolidatedBillsCount = $voyage->billsOfLading()
            ->whereNotNull('master_bill_number')
            ->count();

        // 4. Contar contenedores (usando la relación correcta)
        $containersCount = \DB::table('container_shipment_item')
            ->join('shipment_items', 'container_shipment_item.shipment_item_id', '=', 'shipment_items.id')
            ->join('bills_of_lading', 'shipment_items.bill_of_lading_id', '=', 'bills_of_lading.id')
            ->join('shipments', 'bills_of_lading.shipment_id', '=', 'shipments.id')
            ->where('shipments.voyage_id', $voyage->id)
            ->distinct('container_shipment_item.container_id')
            ->count('container_shipment_item.container_id');

        // 5. Validar con el servicio
        $user = $this->getCurrentUser();
        $service = new ArgentinaDeconsolidatedService($company, $user);
        $validation = $service->canProcessVoyage($voyage);

        // 6. Obtener estados de las operaciones
        $estados = $this->obtenerEstadosDesconsolidado($voyage);

        // 7. Obtener historial de transacciones
        $transactions = WebserviceTransaction::where('voyage_id', $voyage->id)
            ->where('webservice_type', 'desconsolidados')
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

        // 8. Retornar vista con todas las variables
        return view('company.simple.desconsolidado.show', [
            'voyage' => $voyage,
            'desconsolidatedBillsCount' => $desconsolidatedBillsCount,
            'containersCount' => $containersCount,
            'validation' => $validation,
            'estados' => $estados,
            'transactions' => $transactions,
        ]);
    }

    /**
     * HELPER: Obtener estados de operaciones desconsolidado
     */
    private function obtenerEstadosDesconsolidado(Voyage $voyage): array
    {
        $estados = [
            'registrar' => 'pending',
            'rectificar' => 'pending',
            'eliminar' => 'pending',
        ];

        // Verificar si hay transacciones exitosas por cada método
        $registrarSuccess = WebserviceTransaction::where('voyage_id', $voyage->id)
            ->where('webservice_type', 'desconsolidados')
            ->where('status', 'success')
            ->whereJsonContains('additional_metadata->method', 'registrar')
            ->exists();

        $rectificarSuccess = WebserviceTransaction::where('voyage_id', $voyage->id)
            ->where('webservice_type', 'desconsolidados')
            ->where('status', 'success')
            ->whereJsonContains('additional_metadata->method', 'rectificar')
            ->exists();

        $eliminarSuccess = WebserviceTransaction::where('voyage_id', $voyage->id)
            ->where('webservice_type', 'desconsolidados')
            ->where('status', 'success')
            ->whereJsonContains('additional_metadata->method', 'eliminar')
            ->exists();

        if ($registrarSuccess) {
            $estados['registrar'] = 'success';
        }

        if ($rectificarSuccess) {
            $estados['rectificar'] = 'success';
        }

        if ($eliminarSuccess) {
            $estados['eliminar'] = 'success';
        }

        return $estados;
    }

   

    

    /**
     * TODO FASE 5: Transbordos Argentina/Paraguay
     */
    public function transbordoIndex(Request $request)
    {
        return $this->renderComingSoon('transbordo', 'Transbordos Argentina/Paraguay');
    }

    /**
     * ========================================================================
     * PARAGUAY - MANIFIESTO FLUVIAL DNA (GDSF)
     * ========================================================================
     */

    /**
     * Show - Vista detallada para envío GDSF Paraguay
     */
    public function manifiestoShow(Voyage $voyage)
    {
        // Verificar permisos
        if (!$this->hasCompanyRole('Cargas')) {
            abort(403, 'No tiene permisos para acceder a manifiestos Paraguay');
        }

        if (!$this->canAccessCompany($voyage->company_id)) {
            abort(403, 'No puede acceder a este viaje');
        }

        // Cargar relaciones necesarias
        $voyage->load([
            'leadVessel',
            'originPort.country',
            'destinationPort.country',
            'captain',
            'company',
            'shipments.billsOfLading.shipper',
            'shipments.billsOfLading.consignee',
        ]);

        // Inicializar service
        $service = new \App\Services\Simple\ParaguayDnaService(
            $voyage->company,
            auth()->user()
        );

        // Validar voyage
        $validation = $service->canProcessVoyage($voyage);

        // Obtener transacciones existentes
        $transactions = WebserviceTransaction::where('voyage_id', $voyage->id)
            ->where('webservice_type', 'manifiesto')
            ->where('country', 'PY')
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->get();

        // Obtener transacciones específicas por método
        // ✅ CORRECTO - Usar additional_metadata
        $xffmTransaction = $transactions->first(function($t) {
            return ($t->additional_metadata['tipo_mensaje'] ?? null) === 'XFFM';
        });

        $xfblTransaction = $transactions->first(function($t) {
            return ($t->additional_metadata['tipo_mensaje'] ?? null) === 'XFBL';
        });

        $xfbtTransaction = $transactions->first(function($t) {
            return ($t->additional_metadata['tipo_mensaje'] ?? null) === 'XFBT';
        });

        $xfctTransaction = $transactions->first(function($t) {
            return ($t->additional_metadata['tipo_mensaje'] ?? null) === 'XFCT';
        });

        // Estados de cada método
        $xffmStatus = $xffmTransaction && $xffmTransaction->status === 'sent' ? 'sent' : 'pending';
        $xfblStatus = $xfblTransaction && $xfblTransaction->status === 'sent' ? 'sent' : 'pending';
        $xfbtStatus = $xfbtTransaction && $xfbtTransaction->status === 'sent' ? 'sent' : 'pending';
        $xfctStatus = $xfctTransaction && $xfctTransaction->status === 'sent' ? 'sent' : 'pending';

        // Contar BLs y contenedores
        $blCount = $voyage->shipments->flatMap->billsOfLading->count();
        // Contar BLs y contenedores
        $blCount = $voyage->shipments->flatMap->billsOfLading->count();

        // Contar contenedores a través de shipmentItems
        $containerCount = $voyage->shipments
            ->flatMap->billsOfLading
            ->flatMap->shipmentItems
            ->flatMap->containers
            ->unique('id')
            ->count();

        $send_route = route('company.simple.manifiesto.send', $voyage);

        return view('company.simple.manifiesto.show', compact(
            'voyage',
            'validation',
            'transactions',
            'xffmTransaction',
            'xfblTransaction',
            'xfbtTransaction',
            'xfctTransaction',
            'xffmStatus',
            'xfblStatus',
            'xfbtStatus',
            'xfctStatus',
            'blCount',
            'containerCount',
            'send_route'
        ));
    }

    /**
     * Send - Procesar envío AJAX de métodos GDSF
     */
    public function manifiestoSend(Request $request, Voyage $voyage)
    {
        // Verificar permisos
        if (!$this->hasCompanyRole('Cargas')) {
            return response()->json([
                'success' => false,
                'error_message' => 'No tiene permisos para enviar manifiestos'
            ], 403);
        }

        if (!$this->canAccessCompany($voyage->company_id)) {
            return response()->json([
                'success' => false,
                'error_message' => 'No puede acceder a este viaje'
            ], 403);
        }

        // Validar método
        $request->validate([
            'method' => 'required|in:XFFM,XFBL,XFBT,XFCT'
        ]);

        $method = $request->input('method');

        try {
            // Inicializar service
            $service = new \App\Services\Simple\ParaguayDnaService(
                $voyage->company,
                auth()->user()
            );

            // Ejecutar método correspondiente
            $result = match($method) {
                'XFFM' => $service->sendXffm($voyage, ['force_resend' => $request->input('force_resend', false)]),
                'XFBL' => $service->sendXfbl($voyage, ['force_resend' => $request->input('force_resend', false)]),
                'XFBT' => $service->sendXfbt($voyage, ['force_resend' => $request->input('force_resend', false)]),
                'XFCT' => $service->sendXfct($voyage),
                default => ['success' => false, 'error_message' => 'Método no válido']
            };

            // Log de auditoría
            Log::info('Método GDSF ejecutado', [
                'voyage_id' => $voyage->id,
                'method' => $method,
                'success' => $result['success'],
                'user_id' => auth()->id()
            ]);

            return response()->json($result);

        } catch (\Exception $e) {
            Log::error('Error ejecutando método GDSF', [
                'voyage_id' => $voyage->id,
                'method' => $method,
                'error' => $e->getMessage(),
                'user_id' => auth()->id()
            ]);

            return response()->json([
                'success' => false,
                'error_message' => 'Error interno: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Descargar XML generado para homologación
     * UBICACIÓN: Agregar en SimpleManifestController.php después del método manifiestoSend()
     * 
     * Permite descargar los XMLs GDSF generados (XFFM, XFBL, XFBT, XFCT)
     * para revisión y validación manual durante homologación DNA Paraguay
     */
    public function downloadXml(Request $request, Voyage $voyage, string $type)
{
    // Verificar permisos
    if (!$this->hasCompanyRole('Cargas')) {
        abort(403, 'No tiene permisos');
    }

    if (!$this->canAccessCompany($voyage->company_id)) {
        abort(403, 'No puede acceder a este viaje');
    }

    // Buscar la transacción con el XML
    $transaction = \App\Models\WebserviceTransaction::where('voyage_id', $voyage->id)
        ->where('country', 'PY')
        ->whereJsonContains('additional_metadata->tipo_mensaje', $type)
        ->whereNotNull('request_xml')
        ->latest()
        ->first();

    if (!$transaction || !$transaction->request_xml) {
        return redirect()->back()->with('error', "XML no encontrado para {$type}");
    }

    // Nombre del archivo
    $filename = sprintf('%s_%s_%s.xml', $type, $voyage->voyage_number, now()->format('YmdHis'));

    // Descargar
    return response($transaction->request_xml, 200)
        ->header('Content-Type', 'application/xml')
        ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
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
    /**
 * Procesar envío MIC/DTA (AJAX) - MÉTODO MEJORADO
 */
public function micDtaSend(Request $request, Voyage $voyage)
{
    try {
        // 1. VERIFICAR PERMISOS
        if (!$this->canPerform('manage_webservices')) {
            return response()->json([
                'success' => false,
                'error' => 'Sin permisos para gestionar webservices',
                'error_code' => 'PERMISSION_DENIED'
            ], 403);
        }

        $company = $this->getUserCompany();
        if ($voyage->company_id !== $company->id) {
            return response()->json([
                'success' => false,
                'error' => 'Viaje no pertenece a su empresa',
                'error_code' => 'UNAUTHORIZED'
            ], 403);
        }

        // 2. CREAR SERVICIO Y VALIDAR VOYAGE
        $micDtaService = new ArgentinaMicDtaService($company, Auth::user());
        
        // ✅ VALIDACIÓN PREVIA (igual que los otros métodos)
        $validation = $micDtaService->canProcessVoyage($voyage);
        
        if (!$validation['can_process']) {
            return response()->json([
                'success' => false,
                'error' => 'Viaje no válido para MIC/DTA',
                'validation_errors' => $validation['errors'],
                'warnings' => $validation['warnings'],
                'error_code' => 'VALIDATION_FAILED'
            ], 422);
        }

        // 3. VERIFICAR QUE NO ESTÁ YA EN PROCESO
        $status = $micDtaService->getWebserviceStatus($voyage);
        if ($status && in_array($status->status, ['sending', 'validating'])) {
            return response()->json([
                'success' => false,
                'error' => 'MIC/DTA ya está siendo procesado',
                'current_status' => $status->status,
                'error_code' => 'ALREADY_PROCESSING'
            ], 409);
        }

        // 4. ENVIAR WEBSERVICE
        $result = $micDtaService->sendWebservice($voyage, [
            'force_send' => $request->boolean('force_send', false),
            'user_notes' => $request->input('notes', ''),
            'environment' => $company->ws_environment ?? 'testing',
        ]);

        // 5. PROCESAR RESULTADO
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
                'error' => $result['error_message'] ?? 'Error enviando MIC/DTA',
                'error_code' => $result['error_code'] ?? 'WEBSERVICE_ERROR',
                'validation_errors' => $result['validation_errors'] ?? [],
                'warnings' => $result['warnings'] ?? [],
                'transaction_id' => $result['transaction_id'] ?? null,
            ], 400);
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
            'details' => config('app.debug') ? $e->getMessage() : null,
            'error_code' => 'INTERNAL_ERROR'
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

            // Crear servicio y obtener validación completa
            $service = new \App\Services\Simple\ArgentinaMicDtaService($company, auth()->user());
            $validation = $service->canProcessVoyage($voyage);
            
            // ✅ FORMATO DETALLADO PARA DEBUG
            return response()->json([
                'can_process' => $validation['can_process'],
                'summary' => [
                    'errors_count' => count($validation['errors']),
                    'warnings_count' => count($validation['warnings']),
                    'details_count' => count($validation['details'] ?? []),
                ],
                'errors' => $validation['errors'],
                'warnings' => $validation['warnings'],
                'details' => $validation['details'] ?? [],
                'voyage_info' => [
                    'id' => $voyage->id,
                    'voyage_number' => $voyage->voyage_number,
                    'company_id' => $voyage->company_id,
                    'shipments_count' => $voyage->shipments->count(),
                ],
            ], 200, [], JSON_PRETTY_PRINT);

        } catch (Exception $e) {
            return response()->json([
                'error' => 'Error en validación',
                'details' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
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
        $canRegisterMicDta = $this->canSendMicDta($voyage);

        $company = $this->getUserCompany();
        if ($voyage->company_id !== $company->id) {
            abort(403, 'Viaje no pertenece a su empresa.');
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

        return view('company.simple.micdta.methods-dashboard', [
            'voyage' => $voyage,
            'company' => $company,
            'micdta_status' => $micdta_status,
            'validation' => $validation,
            'certificate_valid' => $certificateValidation['is_valid'],
            'certificate_errors' => $certificateValidation['errors'] ?? [],
            'canRegisterMicDta' => $canRegisterMicDta,
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
     * Consultar estado AFIP de un Viaje específico (AJAX)
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
                    'error' => 'Viaje no encontrado'
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
                    'error' => 'Viaje no encontrado'
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
     * Obtener estado AFIP actual de un Viaje para la vista (AJAX)
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
                    'error' => 'Viaje no encontrado'
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

    /**
     * ================================================================================
     * 18 MÉTODOS AFIP - FLUJO PRINCIPAL CONVOY BARCAZAS  
     * ================================================================================
     */

    /**
     * 1. RegistrarTitEnvios - Registra títulos de transporte (genera TRACKs)
     * 
     * Ruta: POST /webservices/micdta/{voyage}/registrar-tit-envios
     * 
     * @param Request $request
     * @param Voyage $voyage
     * @return \Illuminate\Http\JsonResponse
     */
    public function registrarTitEnvios(Request $request, Voyage $voyage)
    {
        try {
            // Validación específica del método
            $request->validate([
                'force_send' => 'boolean',
                'notes' => 'string|max:500'
            ]);

            // Ejecutar método AFIP usando el método genérico
            $result = $this->executeAfipMethod('RegistrarTitEnvios', $request, $voyage);
            
            // Determinar código de respuesta HTTP
            $httpCode = $result['success'] ? 200 : 400;
            if (isset($result['error_code'])) {
                $httpCode = match($result['error_code']) {
                    'UNAUTHORIZED_VOYAGE' => 403,
                    'VALIDATION_FAILED' => 422,
                    'INTERNAL_SERVER_ERROR' => 500,
                    default => 400
                };
            }

            return response()->json($result, $httpCode);

        } catch (\Illuminate\Validation\ValidationException $e) {
            // ✅ MEJORADO: Convertir errores de validación a lista legible
            $errorMessages = [];
            foreach ($e->errors() as $field => $messages) {
                foreach ($messages as $message) {
                    $errorMessages[] = $message;
                }
            }
            
            return response()->json([
                'success' => false,
                'error' => 'Este método requiere parámetros adicionales',
                'validation_errors' => array_merge(
                    ['Este método AFIP requiere configurar parámetros específicos antes de ejecutarlo.', ''],
                    $errorMessages,
                    ['', 'Nota: Estos métodos están diseñados para ser ejecutados desde formularios específicos con datos adicionales.']
                ),
                'error_code' => 'MISSING_PARAMETERS'
            ], 422);
        } catch (Exception $e) {
            \Log::error('Error en registrarTitEnvios', [
                'voyage_id' => $voyage->id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Error interno procesando RegistrarTitEnvios',
                'error_code' => 'INTERNAL_ERROR'
            ], 500);
        }
    }

    /**
     * 2. RegistrarEnvios - Registra envíos detallados (valida TRACKs)
     * 
     * Ruta: POST /webservices/micdta/{voyage}/registrar-envios
     * 
     * @param Request $request
     * @param Voyage $voyage
     * @return \Illuminate\Http\JsonResponse
     */
    public function registrarEnvios(Request $request, Voyage $voyage)
    {
        try {
            // Validación específica del método
            $request->validate([
                'force_send' => 'boolean',
                'notes' => 'string|max:500'
            ]);

            // Ejecutar método AFIP
            $result = $this->executeAfipMethod('RegistrarEnvios', $request, $voyage);
            
            // Código de respuesta HTTP
            $httpCode = $result['success'] ? 200 : 400;
            if (isset($result['error_code'])) {
                $httpCode = match($result['error_code']) {
                    'UNAUTHORIZED_VOYAGE' => 403,
                    'VALIDATION_FAILED' => 422,
                    'INTERNAL_SERVER_ERROR' => 500,
                    default => 400
                };
            }

            return response()->json($result, $httpCode);

        } catch (\Illuminate\Validation\ValidationException $e) {
            // ✅ MEJORADO: Convertir errores de validación a lista legible
            $errorMessages = [];
            foreach ($e->errors() as $field => $messages) {
                foreach ($messages as $message) {
                    $errorMessages[] = $message;
                }
            }
            
            return response()->json([
                'success' => false,
                'error' => 'Este método requiere parámetros adicionales',
                'validation_errors' => array_merge(
                    ['Este método AFIP requiere configurar parámetros específicos antes de ejecutarlo.', ''],
                    $errorMessages,
                    ['', 'Nota: Estos métodos están diseñados para ser ejecutados desde formularios específicos con datos adicionales.']
                ),
                'error_code' => 'MISSING_PARAMETERS'
            ], 422);
        } catch (Exception $e) {
            \Log::error('Error en registrarEnvios', [
                'voyage_id' => $voyage->id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Error interno procesando RegistrarEnvios',
                'error_code' => 'INTERNAL_ERROR'
            ], 500);
        }
    }

    /**
     * 3. RegistrarMicDta - Registra MIC/DTA completo (consume TRACKs)
     * 
     * Ruta: POST /webservices/micdta/{voyage}/registrar-micdta
     * 
     * @param Request $request
     * @param Voyage $voyage
     * @return \Illuminate\Http\JsonResponse
     */
    public function registrarMicDta(Request $request, Voyage $voyage)
    {
        try {
            // Validación específica del método
            $request->validate([
                'force_send' => 'boolean',
                'notes' => 'string|max:500'
            ]);

            // Ejecutar método AFIP
            $result = $this->executeAfipMethod('RegistrarMicDta', $request, $voyage);
            
            // Código de respuesta HTTP
            $httpCode = $result['success'] ? 200 : 400;
            if (isset($result['error_code'])) {
                $httpCode = match($result['error_code']) {
                    'UNAUTHORIZED_VOYAGE' => 403,
                    'VALIDATION_FAILED' => 422,
                    'INTERNAL_SERVER_ERROR' => 500,
                    default => 400
                };
            }

            return response()->json($result, $httpCode);

        } catch (\Illuminate\Validation\ValidationException $e) {
            // ✅ MEJORADO: Convertir errores de validación a lista legible
            $errorMessages = [];
            foreach ($e->errors() as $field => $messages) {
                foreach ($messages as $message) {
                    $errorMessages[] = $message;
                }
            }
            
            return response()->json([
                'success' => false,
                'error' => 'Este método requiere parámetros adicionales',
                'validation_errors' => array_merge(
                    ['Este método AFIP requiere configurar parámetros específicos antes de ejecutarlo.', ''],
                    $errorMessages,
                    ['', 'Nota: Estos métodos están diseñados para ser ejecutados desde formularios específicos con datos adicionales.']
                ),
                'error_code' => 'MISSING_PARAMETERS'
            ], 422);
        } catch (Exception $e) {
            \Log::error('Error en registrarMicDta', [
                'voyage_id' => $voyage->id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Error interno procesando RegistrarMicDta',
                'error_code' => 'INTERNAL_ERROR'
            ], 500);
        }
    }

    /**
     * ================================================================================
     * GESTIÓN CONVOY
     * ================================================================================
     */

    /**
     * 4. RegistrarConvoy - Agrupa MIC/DTAs en convoy
     * 
     * Ruta: POST /webservices/micdta/{voyage}/registrar-convoy
     * 
     * @param Request $request
     * @param Voyage $voyage
     * @return \Illuminate\Http\JsonResponse
     */
    public function registrarConvoy(Request $request, Voyage $voyage)
    {
        try {
            $request->validate([
                'convoy_id' => 'string|max:20',
                'convoy_name' => 'string|max:50',
                'convoy_sequence' => 'integer|min:1|max:99',
                'force_send' => 'boolean',
                'notes' => 'string|max:500'
            ]);

            $result = $this->executeAfipMethod('RegistrarConvoy', $request, $voyage);
            
            $httpCode = $result['success'] ? 200 : ($result['error_code'] === 'UNAUTHORIZED_VOYAGE' ? 403 : 400);
            return response()->json($result, $httpCode);

        } catch (\Illuminate\Validation\ValidationException $e) {
            // ✅ MEJORADO: Convertir errores de validación a lista legible
            $errorMessages = [];
            foreach ($e->errors() as $field => $messages) {
                foreach ($messages as $message) {
                    $errorMessages[] = $message;
                }
            }
            
            return response()->json([
                'success' => false,
                'error' => 'Este método requiere parámetros adicionales',
                'validation_errors' => array_merge(
                    ['Este método AFIP requiere configurar parámetros específicos antes de ejecutarlo.', ''],
                    $errorMessages,
                    ['', 'Nota: Estos métodos están diseñados para ser ejecutados desde formularios específicos con datos adicionales.']
                ),
                'error_code' => 'MISSING_PARAMETERS'
            ], 422);
        } catch (Exception $e) {
            \Log::error('Error en registrarConvoy', ['voyage_id' => $voyage->id, 'error' => $e->getMessage()]);
            return response()->json(['success' => false, 'error' => 'Error interno procesando RegistrarConvoy'], 500);
        }
    }

    /**
     * 5. AsignarATARemol - Asigna remolcador ATA
     * 
     * Ruta: POST /webservices/micdta/{voyage}/asignar-ata-remol
     * 
     * @param Request $request
     * @param Voyage $voyage
     * @return \Illuminate\Http\JsonResponse
     */
    public function asignarATARemol(Request $request, Voyage $voyage)
    {
        try {
            $request->validate([
                'remolcador_id' => 'required|string|max:20',
                'ata_remolcador' => 'required|string|max:30',
                'force_send' => 'boolean',
                'notes' => 'string|max:500'
            ]);

            $result = $this->executeAfipMethod('AsignarATARemol', $request, $voyage);
            
            $httpCode = $result['success'] ? 200 : ($result['error_code'] === 'UNAUTHORIZED_VOYAGE' ? 403 : 400);
            return response()->json($result, $httpCode);

        } catch (\Illuminate\Validation\ValidationException $e) {
            // ✅ MEJORADO: Convertir errores de validación a lista legible
            $errorMessages = [];
            foreach ($e->errors() as $field => $messages) {
                foreach ($messages as $message) {
                    $errorMessages[] = $message;
                }
            }
            
            return response()->json([
                'success' => false,
                'error' => 'Este método requiere parámetros adicionales',
                'validation_errors' => array_merge(
                    ['Este método AFIP requiere configurar parámetros específicos antes de ejecutarlo.', ''],
                    $errorMessages,
                    ['', 'Nota: Estos métodos están diseñados para ser ejecutados desde formularios específicos con datos adicionales.']
                ),
                'error_code' => 'MISSING_PARAMETERS'
            ], 422);
        } catch (Exception $e) {
            \Log::error('Error en asignarATARemol', ['voyage_id' => $voyage->id, 'error' => $e->getMessage()]);
            return response()->json(['success' => false, 'error' => 'Error interno procesando AsignarATARemol'], 500);
        }
    }

    /**
     * 6. RectifConvoyMicDta - Rectifica convoy MIC/DTA
     * 
     * Ruta: POST /webservices/micdta/{voyage}/rectif-convoy-micdta
     * 
     * @param Request $request
     * @param Voyage $voyage
     * @return \Illuminate\Http\JsonResponse
     */
    public function rectifConvoyMicDta(Request $request, Voyage $voyage)
    {
        try {
            $request->validate([
                'convoy_id' => 'required|string|max:20',
                'rectification_reason' => 'required|string|max:200',
                'force_send' => 'boolean',
                'notes' => 'string|max:500'
            ]);

            $result = $this->executeAfipMethod('RectifConvoyMicDta', $request, $voyage);
            
            $httpCode = $result['success'] ? 200 : ($result['error_code'] === 'UNAUTHORIZED_VOYAGE' ? 403 : 400);
            return response()->json($result, $httpCode);

        } catch (\Illuminate\Validation\ValidationException $e) {
            // ✅ MEJORADO: Convertir errores de validación a lista legible
            $errorMessages = [];
            foreach ($e->errors() as $field => $messages) {
                foreach ($messages as $message) {
                    $errorMessages[] = $message;
                }
            }
            
            return response()->json([
                'success' => false,
                'error' => 'Este método requiere parámetros adicionales',
                'validation_errors' => array_merge(
                    ['Este método AFIP requiere configurar parámetros específicos antes de ejecutarlo.', ''],
                    $errorMessages,
                    ['', 'Nota: Estos métodos están diseñados para ser ejecutados desde formularios específicos con datos adicionales.']
                ),
                'error_code' => 'MISSING_PARAMETERS'
            ], 422);
        } catch (Exception $e) {
            \Log::error('Error en rectifConvoyMicDta', ['voyage_id' => $voyage->id, 'error' => $e->getMessage()]);
            return response()->json(['success' => false, 'error' => 'Error interno procesando RectifConvoyMicDta'], 500);
        }
    }

    /**
     * ================================================================================
     * GESTIÓN TÍTULOS
     * ================================================================================
     */

    /**
     * 7. RegistrarTitMicDta - Registra título MIC/DTA
     * 
     * Ruta: POST /webservices/micdta/{voyage}/registrar-tit-micdta
     * 
     * @param Request $request
     * @param Voyage $voyage
     * @return \Illuminate\Http\JsonResponse
     */
    public function registrarTitMicDta(Request $request, Voyage $voyage)
    {
        try {
            // ✅ VALIDACIÓN OPCIONAL: Solo valida si se proveen manualmente
            $request->validate([
                'id_micdta' => 'nullable|string|max:16',
                'titulos' => 'nullable|array|min:1',
                'titulos.*' => 'string|max:36',
                'force_send' => 'boolean',
                'notes' => 'string|max:500'
            ]);

            $result = $this->executeAfipMethod('RegistrarTitMicDta', $request, $voyage);
            
            $httpCode = $result['success'] ? 200 : ($result['error_code'] === 'UNAUTHORIZED_VOYAGE' ? 403 : 400);
            return response()->json($result, $httpCode);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Datos de entrada inválidos',
                'validation_errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            \Log::error('Error en registrarTitMicDta', ['voyage_id' => $voyage->id, 'error' => $e->getMessage()]);
            return response()->json(['success' => false, 'error' => 'Error interno procesando RegistrarTitMicDta'], 500);
        }
    }

    /**
     * 8. DesvincularTitMicDta - Desvincula título
     * 
     * Ruta: POST /webservices/micdta/{voyage}/desvincular-tit-micdta
     * 
     * @param Request $request
     * @param Voyage $voyage
     * @return \Illuminate\Http\JsonResponse
     */
    public function desvincularTitMicDta(Request $request, Voyage $voyage)
    {   
        try {
            $request->validate([
                'id_micdta' => 'nullable|string|max:16',  // ✅ nullable
                'titulos' => 'nullable|array|min:1',      // ✅ nullable
                'titulos.*' => 'string|max:36',
                'motivo_desvinculacion' => 'string|max:200',
                'force_send' => 'boolean',
                'notes' => 'string|max:500'
            ]);

            $result = $this->executeAfipMethod('DesvincularTitMicDta', $request, $voyage);
            
            $httpCode = $result['success'] ? 200 : ($result['error_code'] === 'UNAUTHORIZED_VOYAGE' ? 403 : 400);
            return response()->json($result, $httpCode);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Datos de entrada inválidos',
                'validation_errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            \Log::error('Error en desvincularTitMicDta', ['voyage_id' => $voyage->id, 'error' => $e->getMessage()]);
            return response()->json(['success' => false, 'error' => 'Error interno procesando DesvincularTitMicDta'], 500);
        }
    }

    /**
     * Obtener títulos vinculados al MIC/DTA (AJAX)
     * 
     * Ruta: GET /webservices/micdta/{voyage}/titulos-vinculados
     * 
     * @param Voyage $voyage
     * @return \Illuminate\Http\JsonResponse
     */
    public function getTitulosVinculados(Voyage $voyage)
    {
        try {
            $company = $this->getUserCompany();
            if ($voyage->company_id !== $company->id) {
                return response()->json([
                    'success' => false,
                    'error' => 'No autorizado'
                ], 403);
            }

            // Buscar última vinculación exitosa
            $lastVinculacion = \App\Models\WebserviceTransaction::where('voyage_id', $voyage->id)
                ->where('webservice_type', 'micdta')
                ->where('soap_action', 'like', '%RegistrarTitMicDta%')
                ->where('status', 'success')
                ->latest()
                ->first();

            if (!$lastVinculacion) {
                return response()->json([
                    'success' => false,
                    'error' => 'No se encontraron títulos vinculados',
                    'titulos' => []
                ]);
            }

            // Obtener títulos (manejar diferentes formatos)
            $titulos = $lastVinculacion->success_data['titulos_vinculados'] ?? 
                    $lastVinculacion->success_data['titulos'] ?? 
                    [];

            // Si es array asociativo con 'titulos', extraerlo
            if (is_array($titulos) && isset($titulos[0]) === false && isset($titulos['titulos'])) {
                $titulos = $titulos['titulos'];
            }

            // Si está vacío o es un contador, obtener del voyage
            if (empty($titulos) || is_int($titulos)) {
                $titulos = $voyage->shipments->pluck('shipment_number')->filter()->toArray();
            }

            return response()->json([
                'success' => true,
                'titulos' => array_values($titulos),
                'count' => count($titulos),
                'vinculacion_id' => $lastVinculacion->id,
                'vinculado_en' => $lastVinculacion->created_at->format('d/m/Y H:i')
            ]);

        } catch (Exception $e) {
            \Log::error('Error en getTitulosVinculados', [
                'voyage_id' => $voyage->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Obtener títulos registrados en AFIP (AJAX)
     * 
     * Ruta: GET /webservices/micdta/{voyage}/titulos-registrados
     * 
     * @param Voyage $voyage
     * @return \Illuminate\Http\JsonResponse
     */
    public function getTitulosRegistrados(Voyage $voyage)
    {
        try {
            $company = $this->getUserCompany();
            if ($voyage->company_id !== $company->id) {
                return response()->json([
                    'success' => false,
                    'error' => 'No autorizado'
                ], 403);
            }

            // Obtener shipments que tienen registro exitoso en AFIP
            $titulosRegistrados = \App\Models\WebserviceTransaction::where('voyage_id', $voyage->id)
                ->where('webservice_type', 'micdta')
                ->where('soap_action', 'like', '%RegistrarTitEnvios%')
                ->where('status', 'success')
                ->whereNotNull('shipment_id')
                ->with('shipment:id,shipment_number')
                ->get()
                ->pluck('shipment.shipment_number')
                ->filter()
                ->unique()
                ->values();

            // Obtener MIC/DTAs registrados (para modal SolicitarAnularMicDta)
            $micDtaIds = \App\Models\WebserviceTransaction::where('voyage_id', $voyage->id)
                ->where('webservice_type', 'micdta')
                ->where('status', 'success')
                ->where(function($q) {
                    $q->where('soap_action', 'like', '%RegistrarMicDta%')
                      ->orWhere('soap_action', 'like', '%registrar-micdta%');
                })
                ->whereNotNull('external_reference')
                ->pluck('external_reference')
                ->filter()
                ->unique()
                ->values()
                ->toArray();

            return response()->json([
                'success' => true,
                'titulos' => $titulosRegistrados,
                'count' => $titulosRegistrados->count(),
                'micdta_ids' => $micDtaIds,
            ]);

        } catch (Exception $e) {
            \Log::error('Error en getTitulosRegistrados', [
                'voyage_id' => $voyage->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * 9. AnularTitulo - Anula título
     * 
     * Ruta: POST /webservices/micdta/{voyage}/anular-titulo
     * 
     * @param Request $request
     * @param Voyage $voyage
     * @return \Illuminate\Http\JsonResponse
     */
    public function anularTitulo(Request $request, Voyage $voyage)
    {
        try {
            // LOG TEMPORAL
        \Log::info('🔴 anularTitulo - Datos recibidos', [
            'all_data' => $request->all(),
            'titulo_id' => $request->input('titulo_id'),
            'motivo_anulacion' => $request->input('motivo_anulacion'),
        ]);

            $request->validate([
                'titulo_id' => 'required|string|max:36',  // ✅ nullable
                'motivo_anulacion' => 'required|string|max:200',
                'anular_todos' => 'boolean',  // ✅ Agregar este campo
                'force_send' => 'boolean',
                'notes' => 'string|max:500'
            ]);

            $result = $this->executeAfipMethod('AnularTitulo', $request, $voyage);
            
            $httpCode = $result['success'] ? 200 : ($result['error_code'] === 'UNAUTHORIZED_VOYAGE' ? 403 : 400);
            return response()->json($result, $httpCode);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Datos de entrada inválidos',
                'validation_errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            \Log::error('Error en anularTitulo', ['voyage_id' => $voyage->id, 'error' => $e->getMessage()]);
            return response()->json(['success' => false, 'error' => 'Error interno procesando AnularTitulo'], 500);
        }
    }

    /**
     * ================================================================================
     * ZONA PRIMARIA
     * ================================================================================
     */

    /**
     * 10. RegistrarSalidaZonaPrimaria - Salida zona primaria
     * 
     * Ruta: POST /webservices/micdta/{voyage}/registrar-salida-zona-primaria
     * 
     * @param Request $request
     * @param Voyage $voyage
     * @return \Illuminate\Http\JsonResponse
     */
    public function registrarSalidaZonaPrimaria(Request $request, Voyage $voyage)
    {
        try {
            $request->validate([
                'fecha_salida' => 'required|date',
                'puerto_salida' => 'required|string|max:10',
                'aduana_salida' => 'required|string|max:3',
                'force_send' => 'boolean',
                'notes' => 'string|max:500'
            ]);

            $result = $this->executeAfipMethod('RegistrarSalidaZonaPrimaria', $request, $voyage);
            
            $httpCode = $result['success'] ? 200 : ($result['error_code'] === 'UNAUTHORIZED_VOYAGE' ? 403 : 400);
            return response()->json($result, $httpCode);

        } catch (\Illuminate\Validation\ValidationException $e) {
            // ✅ MEJORADO: Convertir errores de validación a lista legible
            $errorMessages = [];
            foreach ($e->errors() as $field => $messages) {
                foreach ($messages as $message) {
                    $errorMessages[] = $message;
                }
            }
            
            return response()->json([
                'success' => false,
                'error' => 'Este método requiere parámetros adicionales',
                'validation_errors' => array_merge(
                    ['Este método AFIP requiere configurar parámetros específicos antes de ejecutarlo.', ''],
                    $errorMessages,
                    ['', 'Nota: Estos métodos están diseñados para ser ejecutados desde formularios específicos con datos adicionales.']
                ),
                'error_code' => 'MISSING_PARAMETERS'
            ], 422);
        } catch (Exception $e) {
            \Log::error('Error en registrarSalidaZonaPrimaria', ['voyage_id' => $voyage->id, 'error' => $e->getMessage()]);
            return response()->json(['success' => false, 'error' => 'Error interno procesando RegistrarSalidaZonaPrimaria'], 500);
        }
    }

    /**
     * 11. RegistrarArriboZonaPrimaria - Arribo zona primaria
     * 
     * Ruta: POST /webservices/micdta/{voyage}/registrar-arribo-zona-primaria
     * 
     * @param Request $request
     * @param Voyage $voyage
     * @return \Illuminate\Http\JsonResponse
     */
    public function registrarArriboZonaPrimaria(Request $request, Voyage $voyage)
    {
        try {
            $request->validate([
                'nro_viaje' => 'required|string|max:20',
                'cod_adu' => 'required|string|max:3',
                'cod_lug_oper' => 'required|string|max:5',
                'desc_amarre' => 'nullable|string|max:50',
                'force_send' => 'boolean',
                'notes' => 'string|max:500'
            ]);

            $result = $this->executeAfipMethod('RegistrarArriboZonaPrimaria', $request, $voyage);
            
            $httpCode = $result['success'] ? 200 : ($result['error_code'] === 'UNAUTHORIZED_VOYAGE' ? 403 : 400);
            return response()->json($result, $httpCode);

        } catch (\Illuminate\Validation\ValidationException $e) {
            // ✅ MEJORADO: Convertir errores de validación a lista legible
            $errorMessages = [];
            foreach ($e->errors() as $field => $messages) {
                foreach ($messages as $message) {
                    $errorMessages[] = $message;
                }
            }
            
            return response()->json([
                'success' => false,
                'error' => 'Este método requiere parámetros adicionales',
                'validation_errors' => array_merge(
                    ['Este método AFIP requiere configurar parámetros específicos antes de ejecutarlo.', ''],
                    $errorMessages,
                    ['', 'Nota: Estos métodos están diseñados para ser ejecutados desde formularios específicos con datos adicionales.']
                ),
                'error_code' => 'MISSING_PARAMETERS'
            ], 422);
        } catch (Exception $e) {
            \Log::error('Error en registrarArriboZonaPrimaria', ['voyage_id' => $voyage->id, 'error' => $e->getMessage()]);
            return response()->json(['success' => false, 'error' => 'Error interno procesando RegistrarArriboZonaPrimaria'], 500);
        }
    }

    /**
     * 12. AnularArriboZonaPrimaria - Anular arribo zona primaria
     * 
     * Ruta: POST /webservices/micdta/{voyage}/anular-arribo-zona-primaria
     * 
     * @param Request $request
     * @param Voyage $voyage
     * @return \Illuminate\Http\JsonResponse
     */
    public function anularArriboZonaPrimaria(Request $request, Voyage $voyage)
    {
        try {
            $request->validate([
                'arribo_id' => 'required|string|max:20',
                'motivo_anulacion' => 'required|string|max:200',
                'force_send' => 'boolean',
                'notes' => 'string|max:500'
            ]);

            $result = $this->executeAfipMethod('AnularArriboZonaPrimaria', $request, $voyage);
            
            $httpCode = $result['success'] ? 200 : ($result['error_code'] === 'UNAUTHORIZED_VOYAGE' ? 403 : 400);
            return response()->json($result, $httpCode);

        } catch (\Illuminate\Validation\ValidationException $e) {
            // ✅ MEJORADO: Convertir errores de validación a lista legible
            $errorMessages = [];
            foreach ($e->errors() as $field => $messages) {
                foreach ($messages as $message) {
                    $errorMessages[] = $message;
                }
            }
            
            return response()->json([
                'success' => false,
                'error' => 'Este método requiere parámetros adicionales',
                'validation_errors' => array_merge(
                    ['Este método AFIP requiere configurar parámetros específicos antes de ejecutarlo.', ''],
                    $errorMessages,
                    ['', 'Nota: Estos métodos están diseñados para ser ejecutados desde formularios específicos con datos adicionales.']
                ),
                'error_code' => 'MISSING_PARAMETERS'
            ], 422);
        } catch (Exception $e) {
            \Log::error('Error en anularArriboZonaPrimaria', ['voyage_id' => $voyage->id, 'error' => $e->getMessage()]);
            return response()->json(['success' => false, 'error' => 'Error interno procesando AnularArriboZonaPrimaria'], 500);
        }
    }

    /**
     * ================================================================================
     * CONSULTAS
     * ================================================================================
     */

    /**
     * 13. ConsultarMicDtaAsig - Consultar MIC/DTA asignado
     * 
     * Ruta: POST /webservices/micdta/{voyage}/consultar-micdta-asig
     * 
     * @param Request $request
     * @param Voyage $voyage
     * @return \Illuminate\Http\JsonResponse
     */
    public function consultarMicDtaAsig(Request $request, Voyage $voyage)
    {
        try {
            $request->validate([
                'external_reference' => 'string|max:50',
                'micdta_id' => 'string|max:20',
                'force_send' => 'boolean',
                'notes' => 'string|max:500'
            ]);

            $result = $this->executeAfipMethod('ConsultarMicDtaAsig', $request, $voyage);
            
            $httpCode = $result['success'] ? 200 : ($result['error_code'] === 'UNAUTHORIZED_VOYAGE' ? 403 : 400);
            return response()->json($result, $httpCode);

        } catch (\Illuminate\Validation\ValidationException $e) {
            // ✅ MEJORADO: Convertir errores de validación a lista legible
            $errorMessages = [];
            foreach ($e->errors() as $field => $messages) {
                foreach ($messages as $message) {
                    $errorMessages[] = $message;
                }
            }
            
            return response()->json([
                'success' => false,
                'error' => 'Este método requiere parámetros adicionales',
                'validation_errors' => array_merge(
                    ['Este método AFIP requiere configurar parámetros específicos antes de ejecutarlo.', ''],
                    $errorMessages,
                    ['', 'Nota: Estos métodos están diseñados para ser ejecutados desde formularios específicos con datos adicionales.']
                ),
                'error_code' => 'MISSING_PARAMETERS'
            ], 422);
        } catch (Exception $e) {
            \Log::error('Error en consultarMicDtaAsig', ['voyage_id' => $voyage->id, 'error' => $e->getMessage()]);
            return response()->json(['success' => false, 'error' => 'Error interno procesando ConsultarMicDtaAsig'], 500);
        }
    }

    /**
     * 14. ConsultarTitEnviosReg - Consultar títulos envíos registrados
     * 
     * Ruta: POST /webservices/micdta/{voyage}/consultar-tit-envios-reg
     * 
     * @param Request $request
     * @param Voyage $voyage
     * @return \Illuminate\Http\JsonResponse
     */
    public function consultarTitEnviosReg(Request $request, Voyage $voyage)
    {
        try {
            $request->validate([
                'titulo_id' => 'string|max:36',
                'fecha_desde' => 'date',
                'fecha_hasta' => 'date|after_or_equal:fecha_desde',
                'force_send' => 'boolean',
                'notes' => 'string|max:500'
            ]);

            $result = $this->executeAfipMethod('ConsultarTitEnviosReg', $request, $voyage);
            
            $httpCode = $result['success'] ? 200 : ($result['error_code'] === 'UNAUTHORIZED_VOYAGE' ? 403 : 400);
            return response()->json($result, $httpCode);

        } catch (\Illuminate\Validation\ValidationException $e) {
            // ✅ MEJORADO: Convertir errores de validación a lista legible
            $errorMessages = [];
            foreach ($e->errors() as $field => $messages) {
                foreach ($messages as $message) {
                    $errorMessages[] = $message;
                }
            }
            
            return response()->json([
                'success' => false,
                'error' => 'Este método requiere parámetros adicionales',
                'validation_errors' => array_merge(
                    ['Este método AFIP requiere configurar parámetros específicos antes de ejecutarlo.', ''],
                    $errorMessages,
                    ['', 'Nota: Estos métodos están diseñados para ser ejecutados desde formularios específicos con datos adicionales.']
                ),
                'error_code' => 'MISSING_PARAMETERS'
            ], 422);
        } catch (Exception $e) {
            \Log::error('Error en consultarTitEnviosReg', ['voyage_id' => $voyage->id, 'error' => $e->getMessage()]);
            return response()->json(['success' => false, 'error' => 'Error interno procesando ConsultarTitEnviosReg'], 500);
        }
    }

    /**
     * 15. ConsultarPrecumplido - Consultar precumplido
     * 
     * Ruta: POST /webservices/micdta/{voyage}/consultar-precumplido
     * 
     * @param Request $request
     * @param Voyage $voyage
     * @return \Illuminate\Http\JsonResponse
     */
    public function consultarPrecumplido(Request $request, Voyage $voyage)
    {
        try {
            $request->validate([
                'precumplido_id' => 'string|max:20',
                'estado' => 'string|in:pendiente,aprobado,rechazado',
                'force_send' => 'boolean',
                'notes' => 'string|max:500'
            ]);

            $result = $this->executeAfipMethod('ConsultarPrecumplido', $request, $voyage);
            
            $httpCode = $result['success'] ? 200 : ($result['error_code'] === 'UNAUTHORIZED_VOYAGE' ? 403 : 400);
            return response()->json($result, $httpCode);

        } catch (\Illuminate\Validation\ValidationException $e) {
            // ✅ MEJORADO: Convertir errores de validación a lista legible
            $errorMessages = [];
            foreach ($e->errors() as $field => $messages) {
                foreach ($messages as $message) {
                    $errorMessages[] = $message;
                }
            }
            
            return response()->json([
                'success' => false,
                'error' => 'Este método requiere parámetros adicionales',
                'validation_errors' => array_merge(
                    ['Este método AFIP requiere configurar parámetros específicos antes de ejecutarlo.', ''],
                    $errorMessages,
                    ['', 'Nota: Estos métodos están diseñados para ser ejecutados desde formularios específicos con datos adicionales.']
                ),
                'error_code' => 'MISSING_PARAMETERS'
            ], 422);
        } catch (Exception $e) {
            \Log::error('Error en consultarPrecumplido', ['voyage_id' => $voyage->id, 'error' => $e->getMessage()]);
            return response()->json(['success' => false, 'error' => 'Error interno procesando ConsultarPrecumplido'], 500);
        }
    }

    /**
     * Mostrar dashboard de métodos AFIP
     */
    public function methodsDashboard(Voyage $voyage)
    {
        $company = $this->getUserCompany();
        if ($voyage->company_id !== $company->id) {
            abort(403, 'Voyage no pertenece a su empresa.');
        }

        // Cargar datos necesarios
        $voyage->load(['leadVessel', 'originPort', 'destinationPort']);
        $micdta_status = $this->getMicDtaStatus($voyage);

        return view('company.simple.micdta.methods-dashboard', [
            'voyage' => $voyage,
            'company' => $company,
            'micdta_status' => $micdta_status,
        ]);
    }

    /**
     * ================================================================================
     * ANULACIONES + TESTING
     * ================================================================================
     */

    /**
     * 16. SolicitarAnularMicDta - Solicitar anular MIC/DTA
     * 
     * Ruta: POST /webservices/micdta/{voyage}/solicitar-anular-micdta
     * 
     * @param Request $request
     * @param Voyage $voyage
     * @return \Illuminate\Http\JsonResponse
     */
    public function solicitarAnularMicDta(Request $request, Voyage $voyage)
    {
        try {
            $request->validate([
                'micdta_id' => 'required|string|max:20',
                'motivo_anulacion' => 'required|string|max:200',
                'justificacion' => 'string|max:500',
                'force_send' => 'boolean',
                'notes' => 'string|max:500'
            ]);

            $result = $this->executeAfipMethod('SolicitarAnularMicDta', $request, $voyage);
            
            $httpCode = $result['success'] ? 200 : ($result['error_code'] === 'UNAUTHORIZED_VOYAGE' ? 403 : 400);
            return response()->json($result, $httpCode);

        } catch (\Illuminate\Validation\ValidationException $e) {
            // ✅ MEJORADO: Convertir errores de validación a lista legible
            $errorMessages = [];
            foreach ($e->errors() as $field => $messages) {
                foreach ($messages as $message) {
                    $errorMessages[] = $message;
                }
            }
            
            return response()->json([
                'success' => false,
                'error' => 'Este método requiere parámetros adicionales',
                'validation_errors' => array_merge(
                    ['Este método AFIP requiere configurar parámetros específicos antes de ejecutarlo.', ''],
                    $errorMessages,
                    ['', 'Nota: Estos métodos están diseñados para ser ejecutados desde formularios específicos con datos adicionales.']
                ),
                'error_code' => 'MISSING_PARAMETERS'
            ], 422);
        } catch (Exception $e) {
            \Log::error('Error en solicitarAnularMicDta', ['voyage_id' => $voyage->id, 'error' => $e->getMessage()]);
            return response()->json(['success' => false, 'error' => 'Error interno procesando SolicitarAnularMicDta'], 500);
        }
    }

    /**
     * 17. AnularEnvios - Anular envíos
     * 
     * Ruta: POST /webservices/micdta/{voyage}/anular-envios
     * 
     * @param Request $request
     * @param Voyage $voyage
     * @return \Illuminate\Http\JsonResponse
     */
    public function anularEnvios(Request $request, Voyage $voyage)
    {
        try {

            \Log::info('🔴 anularEnvios - Datos recibidos', [
                'all_data' => $request->all(),
                'anular_todos' => $request->input('anular_todos'),
                'motivo' => $request->input('motivo_anulacion'),
            ]);

            $request->validate([
                'motivo_anulacion' => 'required|string|max:200',
                'anular_todos' => 'boolean',
                'envios_ids' => 'nullable|array',
                'envios_ids.*' => 'string|max:36',
                'tracks' => 'nullable|array',
                'force_send' => 'boolean',
                'notes' => 'string|max:500'
            ]);

            $result = $this->executeAfipMethod('AnularEnvios', $request, $voyage);
            
            $httpCode = $result['success'] ? 200 : ($result['error_code'] === 'UNAUTHORIZED_VOYAGE' ? 403 : 400);
            return response()->json($result, $httpCode);

        } catch (\Illuminate\Validation\ValidationException $e) {
            // ✅ MEJORADO: Convertir errores de validación a lista legible
            $errorMessages = [];
            foreach ($e->errors() as $field => $messages) {
                foreach ($messages as $message) {
                    $errorMessages[] = $message;
                }
            }
            
            return response()->json([
                'success' => false,
                'error' => 'Este método requiere parámetros adicionales',
                'validation_errors' => array_merge(
                    ['Este método AFIP requiere configurar parámetros específicos antes de ejecutarlo.', ''],
                    $errorMessages,
                    ['', 'Nota: Estos métodos están diseñados para ser ejecutados desde formularios específicos con datos adicionales.']
                ),
                'error_code' => 'MISSING_PARAMETERS'
            ], 422);
        } catch (Exception $e) {
            \Log::error('Error en anularEnvios', ['voyage_id' => $voyage->id, 'error' => $e->getMessage()]);
            return response()->json(['success' => false, 'error' => 'Error interno procesando AnularEnvios'], 500);
        }
    }

    /**
     * 18. Dummy - Método de prueba AFIP
     * 
     * Ruta: POST /webservices/micdta/{voyage}/dummy
     * 
     * @param Request $request
     * @param Voyage $voyage
     * @return \Illuminate\Http\JsonResponse
     */
    public function dummy(Request $request, Voyage $voyage)
    {
        try {
            $request->validate([
                'test_parameter' => 'string|max:100',
                'force_send' => 'boolean',
                'notes' => 'string|max:500'
            ]);

            $result = $this->executeAfipMethod('Dummy', $request, $voyage);
            
            $httpCode = $result['success'] ? 200 : ($result['error_code'] === 'UNAUTHORIZED_VOYAGE' ? 403 : 400);
            return response()->json($result, $httpCode);

        } catch (\Illuminate\Validation\ValidationException $e) {
            // ✅ MEJORADO: Convertir errores de validación a lista legible
            $errorMessages = [];
            foreach ($e->errors() as $field => $messages) {
                foreach ($messages as $message) {
                    $errorMessages[] = $message;
                }
            }
            
            return response()->json([
                'success' => false,
                'error' => 'Este método requiere parámetros adicionales',
                'validation_errors' => array_merge(
                    ['Este método AFIP requiere configurar parámetros específicos antes de ejecutarlo.', ''],
                    $errorMessages,
                    ['', 'Nota: Estos métodos están diseñados para ser ejecutados desde formularios específicos con datos adicionales.']
                ),
                'error_code' => 'MISSING_PARAMETERS'
            ], 422);
        } catch (Exception $e) {
            \Log::error('Error en dummy', ['voyage_id' => $voyage->id, 'error' => $e->getMessage()]);
            return response()->json(['success' => false, 'error' => 'Error interno procesando Dummy'], 500);
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

}