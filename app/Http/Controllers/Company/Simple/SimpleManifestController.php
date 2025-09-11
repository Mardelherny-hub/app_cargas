<?php

namespace App\Http\Controllers\Company\Simple;

use App\Http\Controllers\Controller;
use App\Models\Voyage;
use App\Models\VoyageWebserviceStatus;
use App\Models\WebserviceTransaction;
use App\Services\Simple\ArgentinaMicDtaService;
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

    /**
     * Formulario de envío MIC/DTA para voyage específico
     */
    public function micDtaShow(Voyage $voyage)
    {
        if (!$this->canPerform('webservices.micdta')) {
            abort(403, 'No tiene permisos para MIC/DTA.');
        }

        $company = $this->getUserCompany();
        if ($voyage->company_id !== $company->id) {
            abort(403, 'Voyage no pertenece a su empresa.');
        }

        // Cargar relaciones necesarias
        $voyage->load([
            'leadVessel',
            'originPort.country',
            'destinationPort.country',
            'shipments.billsOfLading.shipmentItems',
            'webserviceStatuses' => function($query) {
                $query->where('webservice_type', 'micdta');
            },
            'webserviceTransactions' => function($query) {
                $query->where('webservice_type', 'micdta')
                      ->latest();
            }
        ]);

        // Validar voyage para MIC/DTA
        $validation = $this->validateVoyageForMicDta($voyage);
        $micDtaStatus = $this->getMicDtaStatus($voyage);

        return view('company.simple.micdta.form', [
            'voyage' => $voyage,
            'company' => $company,
            'validation' => $validation,
            'micdta_status' => $micDtaStatus,
            'last_transactions' => $voyage->webserviceTransactions->take(5),
        ]);
    }

    /**
     * Procesar envío MIC/DTA
     */
    public function micDtaSend(Request $request, Voyage $voyage)
    {
        if (!$this->canPerform('webservices.send')) {
            return response()->json([
                'success' => false,
                'message' => 'No tiene permisos para enviar webservices.'
            ], 403);
        }

        $company = $this->getUserCompany();
        if ($voyage->company_id !== $company->id) {
            return response()->json([
                'success' => false,
                'message' => 'Voyage no pertenece a su empresa.'
            ], 403);
        }

        try {
            // Crear servicio MIC/DTA
            $micDtaService = new ArgentinaMicDtaService($company, Auth::user());

            // Validar antes de enviar
            $validation = $micDtaService->canProcessVoyage($voyage);
            if (!$validation['can_process']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Voyage no válido para MIC/DTA',
                    'errors' => $validation['errors']
                ], 422);
            }

            // Enviar MIC/DTA
            $result = $micDtaService->sendWebservice($voyage, [
                'force_send' => $request->boolean('force_send', false),
                'test_mode' => $request->boolean('test_mode', true),
            ]);

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'message' => 'MIC/DTA enviado exitosamente',
                    'confirmation_number' => $result['confirmation_number'] ?? null,
                    'transaction_id' => $result['transaction_id'] ?? null,
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Error enviando MIC/DTA: ' . $result['error_message'],
                    'transaction_id' => $result['transaction_id'] ?? null,
                ], 422);
            }

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error interno: ' . $e->getMessage()
            ], 500);
        }
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
     * Obtener estado específico MIC/DTA
     */
    private function getMicDtaStatus(Voyage $voyage): ?VoyageWebserviceStatus
    {
        return $voyage->webserviceStatuses
            ->where('webservice_type', 'micdta')
            ->where('country', 'AR')
            ->first();
    }

    /**
     * Validar voyage para MIC/DTA
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
     * Obtener tipos de webservices activos
     */
    private function getActiveWebserviceTypes(): array
    {
        return array_filter(self::WEBSERVICE_TYPES, function($type) {
            return $type['status'] === 'active';
        });
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
}