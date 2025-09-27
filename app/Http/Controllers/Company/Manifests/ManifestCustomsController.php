<?php

namespace App\Http\Controllers\Company\Manifests;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Voyage;
use App\Models\WebserviceTransaction;
use App\Services\Webservice\ArgentinaMicDtaService;
use App\Services\Webservice\ArgentinaAnticipatedService;
use App\Services\Webservice\ParaguayCustomsService;
use App\Services\Webservice\ArgentinaDeconsolidationService;
use App\Services\Webservice\ArgentinaTransshipmentService;
use App\Services\Webservice\ArgentinaManeService;
use App\Models\BillOfLading;
use App\Models\VoyageWebserviceStatus;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Services\Webservice\ParaguayAttachmentService;
use App\Traits\UserHelper;
use App\Models\Company;
use Illuminate\Support\Facades\DB;
use Exception;

/**
 * COMPLETADO: ManifestCustomsController - VERSIÓN FINAL CORREGIDA
 * 
 * Maneja el envío de manifiestos a aduanas (AFIP Argentina / DNA Paraguay)
 * - Vista para seleccionar manifiestos - CORREGIDA
 * - Envío a webservices aduaneros
 * - Seguimiento de transacciones
 * - Reintento de envíos fallidos
 */
class ManifestCustomsController extends Controller
{
    /**
     * Vista principal para seleccionar manifiestos y enviar a aduana - CORREGIDA
     */
    public function index(Request $request)
    {
        // CORREGIDO: Usar getUserCompany() que es el método que funciona
        $currentUser = auth()->user();
        $company = $currentUser->getUserCompany();
        $companyId = $company ? $company->id : null;
        
        if (!$companyId) {
            return redirect()->route('company.webservices.index')
                ->with('error', 'No se encontró la empresa asociada.');
        }

        // CONSULTA CORREGIDA - MÁS INCLUSIVA
        $query = Voyage::with([
            'shipments.billsOfLading',
            'originPort.country',
            'destinationPort.country',
            'webserviceTransactions',
            'webserviceStatuses',
        ])
        ->where('company_id', $companyId)
        ->whereHas('shipments') // Solo viajes con cargas
        ->where(function ($q) {
            // CORREGIDO: Estados más inclusivos
            $q->whereIn('status', [
                'completed',      // ← Nuestro caso principal
                'approved',
                'in_transit',
                'at_destination',
                'planning',       // Incluido según código original
                'pending',
                'closed',
            ])
            // CORREGIDO: O bien que tenga al menos un B/L en estado válido
            ->orWhereHas('shipments.billsOfLading', function ($qq) {
                $qq->whereIn('status', ['confirmed', 'shipped', 'verified', 'issued', 'draft']);
            });
        });

        // Filtro por país destino (se mantiene igual)
        if ($request->filled('country')) {
            $query->whereHas('destinationPort.country', function($q) use ($request) {
                $q->where('alpha2_code', $request->country);
            });
        }

        // Filtros extra (se mantienen igual)
        if ($request->filled('voyage_number')) {
            $query->where('voyage_number', 'like', '%' . $request->voyage_number . '%');
        }
        if ($request->filled('vessel_id')) {
            $query->where('lead_vessel_id', $request->vessel_id);
        }
        if ($request->filled('webservice_status')) {
            switch ($request->webservice_status) {
                case 'not_sent':
                    $query->doesntHave('webserviceTransactions');
                    break;
                case 'sent':
                    $query->whereHas('webserviceTransactions', fn($q) => $q->where('status', 'success'));
                    break;
                case 'failed':
                    $query->whereHas('webserviceTransactions', fn($q) => $q->where('status', 'error'));
                    break;
                case 'pending':
                    $query->whereHas('webserviceTransactions', fn($q) => $q->where('status', 'pending'));
                    break;
            }
        }

        $voyages = $query->latest()->paginate(15);

        // LOG para debugging - AGREGADO
        Log::info('ManifestCustomsController - Viajes encontrados', [
            'company_id' => $companyId,
            'total_voyages' => $voyages->total(),
            'voyage_numbers' => $voyages->pluck('voyage_number')->toArray(),
            'filters_applied' => $request->only(['country', 'voyage_number', 'webservice_status'])
        ]);

        $stats = $this->getCustomsStats();
        $filters = $this->getFilterData();

        return view('company.manifests.customs', compact('voyages', 'stats', 'filters'));
    }

    /**
     * Vista específica para envío MANE
     */
    public function maneIndex(Request $request)
    {
        // Verificar permisos
        $currentUser = auth()->user();
        $company = $currentUser->getUserCompany();
        
        if (!$company) {
            return redirect()->route('company.webservices.index')
                ->with('error', 'No se encontró la empresa asociada.');
        }
        
        // Verificar que la empresa tenga rol "Cargas"
        if (!$company->hasRole('Cargas')) {
            return redirect()->route('company.manifests.customs.index')
                ->with('error', 'Su empresa no tiene permisos para usar MANE.');
        }
        
        // Verificar ID María
        if (empty($company->id_maria)) {
            return redirect()->route('company.manifests.customs.index')
                ->with('error', 'Su empresa debe tener un ID María configurado para usar MANE.');
        }
        
        // Obtener viajes disponibles para MANE
        $query = Voyage::with([
            'shipments.billsOfLading',
            'originPort.country',
            'destinationPort.country',
            'webserviceTransactions' => function($q) {
                $q->where('webservice_type', 'mane')->latest();
            },
            'leadVessel'
        ])
        ->where('company_id', $company->id)
        ->whereHas('shipments')
        ->whereIn('status', ['completed', 'approved', 'in_transit']);
        
        // Filtros
        if ($request->filled('voyage_number')) {
            $query->where('voyage_number', 'like', '%' . $request->voyage_number . '%');
        }
        
        if ($request->filled('status_filter')) {
            switch($request->status_filter) {
                case 'not_sent':
                    $query->whereDoesntHave('webserviceTransactions', function($q) {
                        $q->where('webservice_type', 'mane')
                        ->where('status', 'success');
                    });
                    break;
                case 'sent':
                    $query->whereHas('webserviceTransactions', function($q) {
                        $q->where('webservice_type', 'mane')
                        ->where('status', 'success');
                    });
                    break;
                case 'error':
                    $query->whereHas('webserviceTransactions', function($q) {
                        $q->where('webservice_type', 'mane')
                        ->where('status', 'error');
                    });
                    break;
            }
        }
        
        $voyages = $query->orderBy('departure_date', 'desc')->paginate(10);
        
        // Calcular estadísticas
        $stats = [
            'available_voyages' => $query->count(),
            'sent_today' => WebserviceTransaction::where('company_id', $company->id)
                ->where('webservice_type', 'mane')
                ->where('status', 'success')
                ->whereDate('sent_at', today())
                ->count(),
            'pending' => Voyage::where('company_id', $company->id)
                ->whereHas('shipments')
                ->whereDoesntHave('webserviceTransactions', function($q) {
                    $q->where('webservice_type', 'mane')
                    ->where('status', 'success');
                })
                ->count(),
            'success_rate' => $this->calculateManeSuccessRate($company->id),
        ];
        
        $environment = config('app.env') === 'production' ? 'production' : 'testing';
        
        return view('company.manifests.customs-mane', compact(
            'company',
            'voyages',
            'stats',
            'environment'
        ));
    }

    /**
     * Calcular tasa de éxito específica para MANE
     */
    private function calculateManeSuccessRate(int $companyId): float
    {
        $total = WebserviceTransaction::where('company_id', $companyId)
            ->where('webservice_type', 'mane')
            ->count();
            
        if ($total === 0) return 0.0;
        
        $successful = WebserviceTransaction::where('company_id', $companyId)
            ->where('webservice_type', 'mane')
            ->where('status', 'success')
            ->count();
        
        return round(($successful / $total) * 100, 1);
    }
    /**
     * Envío masivo de manifiestos seleccionados
     */
    public function sendBatch(Request $request)
    {
        $request->validate([
            'voyage_ids' => 'required|array|min:1',
            'voyage_ids.*' => 'exists:voyages,id',
            'webservice_type' => 'required|in:anticipada,micdta,paraguay_customs',
            'environment' => 'required|in:testing,production',
        ]);

        $results = [
            'success' => 0,
            'failed' => 0,
            'errors' => []
        ];

        foreach ($request->voyage_ids as $voyageId) {
            try {
                $voyage = $this->getVoyageForCustoms($voyageId);

                Log::info('DEBUG - Viaje obtenido correctamente', [
                    'voyage_id' => $voyage->id,
                    'voyage_number' => $voyage->voyage_number,
                    'shipments_count' => $voyage->shipments()->count()
                ]);
                
                // Crear transacción
                $transaction = $this->createWebserviceTransaction($voyage, $request->all());

                Log::info('DEBUG - Transacción creada', [
                    'transaction_id' => $transaction->id
                ]);
                
                // Enviar
                Log::info('🔥 ANTES DE LLAMAR sendToWebservice');
                $service = $this->getWebserviceByType($request->webservice_type, $voyage);
                $response = $service->send($voyage, [
                    'transaction_id' => $transaction->transaction_id,
                    'environment' => $request->environment,
                    'batch_mode' => true,
                ]);

                $this->updateTransactionWithResponse($transaction, $response);
                $results['success']++;

            } catch (\Exception $e) {
                $results['failed']++;
                $results['errors'][] = "Viaje {$voyageId}: " . $e->getMessage();
                
                Log::error('Error en envío masivo', [
                    'voyage_id' => $voyageId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $message = "Envío masivo completado: {$results['success']} exitosos, {$results['failed']} fallidos.";
        
        return redirect()->route('company.manifests.customs.index')
            ->with($results['failed'] > 0 ? 'warning' : 'success', $message)
            ->with('batch_results', $results);
    }

    /**
     * Calcular tasa de éxito de envíos
     */
    private function calculateSuccessRate(int $companyId): float
    {
        $total = WebserviceTransaction::where('company_id', $companyId)->count();
        if ($total === 0) return 0.0;

        $successful = WebserviceTransaction::where('company_id', $companyId)
            ->where('status', 'success')
            ->count();

        return round(($successful / $total) * 100, 1);
    }

    // ==========================================
    // MÉTODOS FALTANTES PARA ManifestCustomsController
    // Agregar estos métodos al final de la clase
    // ==========================================

    /**
     * Mostrar estado de transacción específica
     */
    public function status($transactionId)
    {
        // CORREGIDO: Usar getUserCompany()
        $companyId = auth()->user()->getUserCompany()?->id;
        
        $transaction = WebserviceTransaction::with(['user', 'logs'])
            ->where('company_id', $companyId)
            ->findOrFail($transactionId);

        // Obtener logs relacionados
        $logs = $transaction->logs()->orderBy('created_at', 'desc')->get();

        return view('company.manifests.customs-status', compact('transaction', 'logs'));
    }

    /**
     * Reintentar envío fallido
     */
    public function retry($transactionId)
    {
        // CORREGIDO: Usar getUserCompany()
        $companyId = auth()->user()->getUserCompany()?->id;
        
        $transaction = WebserviceTransaction::where('company_id', $companyId)
            ->where('status', 'error')
            ->findOrFail($transactionId);

        try {
            // Resetear estado de la transacción
            $transaction->update([
                'status' => 'pending',
                'error_message' => null,
                'retry_count' => ($transaction->retry_count ?? 0) + 1,
                'updated_at' => now()
            ]);

            // Obtener servicio y reenviar
            $service = $this->getWebserviceByType($transaction->webservice_type);
            
            $response = $service->retry($transaction);

            // Actualizar con nueva respuesta
            $this->updateTransactionWithResponse($transaction, $response);

            return back()->with('success', 'Reintento de envío iniciado correctamente.');

        } catch (\Exception $e) {
            Log::error('Error en reintento de transacción', [
                'transaction_id' => $transactionId,
                'error' => $e->getMessage()
            ]);

            $transaction->update([
                'status' => 'error',
                'error_message' => $e->getMessage()
            ]);

            return back()->with('error', 'Error en reintento: ' . $e->getMessage());
        }
    }

    // ========================================
    // MÉTODOS HELPER PRIVADOS - CORREGIDOS
    // ========================================

    /**
     * Obtener Viaje validado para envío a aduana - CORREGIDO
     */
    private function getVoyageForCustoms($voyageId)
    {
        Log::info('DEBUG - Viaje encontrado exitosamente', [
            'voyage_id' => $voyageId,
            'company_id' => auth()->user()->company_id
        ]);

        // CORREGIDO: Usar getUserCompany()
        $companyId = auth()->user()->getUserCompany()?->id;
        
        $voyage = Voyage::with([
            'shipments.billsOfLading',
            'originPort.country',
            'destinationPort.country',
            'company'
        ])
        ->where('company_id', $companyId)
        ->findOrFail($voyageId);
        
        Log::info('DEBUG - Viaje encontrado exitosamente', [
            'voyage_id' => $voyage->id,
            'voyage_number' => $voyage->voyage_number,
            'shipments_count' => $voyage->shipments()->count()
        ]);
        
        // Validar que el Viaje tiene datos necesarios
        if (!$voyage->shipments()->count()) {
            throw new \Exception('El viaje no tiene shipments para enviar.');
        }

        
        return $voyage;
    }

    /**
     * Obtener estadísticas de envíos a aduana - CORREGIDO
     */
    private function getCustomsStats()
    {
        // CORREGIDO: Usar getUserCompany()
        $companyId = auth()->user()->getUserCompany()?->id;
        
        // Estadísticas de transacciones
        $transactions = \App\Models\WebserviceTransaction::where('company_id', $companyId);
        
        // Estadísticas de viajes listos
        $readyVoyages = Voyage::where('company_id', $companyId)
            ->whereHas('shipments')
            ->whereIn('status', ['approved', 'completed', 'in_transit', 'at_destination']);
        
        return [
            'ready_voyages' => $readyVoyages->count(),
            'total_sent' => $transactions->whereIn('status', ['success', 'pending'])->count(),
            'successful_sent' => $transactions->where('status', 'success')->count(),
            'failed_sent' => $transactions->where('status', 'error')->count(),
            'pending_sent' => $transactions->where('status', 'pending')->count(),
            'this_month_sent' => $transactions->whereMonth('created_at', now()->month)->count(),
            'argentina_sent' => $transactions->whereIn('webservice_type', ['anticipada', 'micdta'])->count(),
            'paraguay_sent' => $transactions->where('webservice_type', 'paraguay_customs')->count(),
        ];
    }

    /**
     * Obtener datos para filtros de la vista - CORREGIDO
     */
    private function getFilterData()
    {
        // CORREGIDO: Usar getUserCompany()
        $companyId = auth()->user()->getUserCompany()?->id;
        
        return [
            'vessels' => \App\Models\Vessel::where('company_id', $companyId)
                ->where('active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
                
            'countries' => \App\Models\Country::whereIn('alpha2_code', ['AR', 'PY'])
                ->orderBy('name')
                ->get(['id', 'name', 'alpha2_code']),
                
            'status_options' => [
                'not_sent' => 'No Enviados',
                'sent' => 'Enviados Exitosamente', 
                'failed' => 'Envíos Fallidos',
                'pending' => 'Envíos Pendientes'
            ]
        ];
    }

    /**
     * Método temporal para diagnosticar el problema - MEJORADO
     */
    public function debug(Request $request)
    {
        $currentUser = auth()->user();
        
        // PROBAR DIFERENTES FORMAS DE OBTENER COMPANY_ID
        $company = $currentUser->getUserCompany();
        $companyId = $company ? $company->id : null;
        
        Log::info('=== DEBUG MANIFEST CUSTOMS ===', [
            'user_id' => $currentUser->id,
            'user_email' => $currentUser->email,
            'company_id' => $companyId,
            'company_name' => $company ? $company->legal_name : null,
            'timestamp' => now()
        ]);

        // Verificar viaje TESTING-001
        $testingVoyage = Voyage::where('voyage_number', 'TESTING-001')->first();
        
        // Consulta igual que en index() pero usando el companyId correcto
        $queryResult = Voyage::with([
            'shipments.billsOfLading', 
            'originPort.country', 
            'destinationPort.country',
            'webserviceTransactions'
        ])
        ->where('company_id', $companyId)
        ->whereHas('shipments')
        ->where(function ($q) {
            $q->whereIn('status', [
                'completed',
                'approved',
                'in_transit',
                'at_destination',
                'planning',
                'pending',
                'closed',
            ])
            ->orWhereHas('shipments.billsOfLading', function ($qq) {
                $qq->whereIn('status', ['confirmed', 'shipped', 'verified', 'issued', 'draft']);
            });
        })
        ->get();

        return response()->json([
            'CORRECTED_company_id' => $companyId,
            'testing_voyage_found' => $testingVoyage ? 'YES' : 'NO',
            'testing_voyage_company_id' => $testingVoyage?->company_id,
            'user_company_matches_voyage' => $testingVoyage && $testingVoyage->company_id == $companyId,
            'filtered_voyages_found' => $queryResult->count(),
            'voyages_list' => $queryResult->map(function($v) {
                return [
                    'voyage_number' => $v->voyage_number,
                    'status' => $v->status,
                    'shipments_count' => $v->shipments->count(),
                    'bills_count' => $v->shipments->sum(function($s) { 
                        return $s->billsOfLading->count(); 
                    })
                ];
            })
        ]);
    }


//    <?php

// AGREGAR ESTOS MÉTODOS AL FINAL DE ManifestCustomsController.php
// ANTES del último cierre de clase }

    /**
     * Crear transacción de webservice - VERSIÓN CORREGIDA
     * Incluye webservice_url requerido por la tabla
     */
    private function createWebserviceTransaction(Voyage $voyage, array $data)
    {
        // Obtener la URL del webservice según el tipo y ambiente
        $webserviceUrl = $this->getWebserviceUrl($data['webservice_type'], $data['environment']);
        
        // Determinar país basado en el tipo de webservice
        $country = $this->getCountryFromWebserviceType($data['webservice_type']);
        
        return WebserviceTransaction::create([
            'company_id' => $voyage->company_id,
            'user_id' => Auth::id(),
            'voyage_id' => $voyage->id,
            'webservice_type' => $data['webservice_type'],
            'environment' => $data['environment'],
            'country' => $country,
            'webservice_url' => $webserviceUrl, // ✅ CAMPO AGREGADO
            'transaction_id' => $this->generateTransactionId($voyage->company_id, $data['webservice_type']),
            'status' => 'pending',
            'retry_count' => 0,
            'max_retries' => 3,
            'is_blocking_error' => false,
            'requires_manual_review' => false,
            'currency_code' => 'USD',
            'container_count' => $this->getVoyageContainerCount($voyage),
            'bill_of_lading_count' => $this->getVoyageBillOfLadingCount($voyage),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            // Datos adicionales para referencia
            'voyage_number' => $voyage->voyage_number,
            'vessel_name' => $voyage->vessel->name ?? 'N/A',
            'origin_port' => $voyage->origin_port->code ?? '',
            'destination_port' => $voyage->destination_port->code ?? '',
            'priority' => $data['priority'] ?? 'normal',
        ]);
    }

    /**
    * Obtener URL del webservice según tipo y ambiente - VERSIÓN ACTUALIZADA CON MANE
    */
    private function getWebserviceUrl(string $webserviceType, string $environment): string
    {
        $urls = [
            'anticipada' => [
                'testing' => 'https://wsaduhomoext.afip.gob.ar/DGA/wgesinformacionanticipada/wgesinformacionanticipada.asmx',
                'production' => 'https://wsadu.afip.gob.ar/DGA/wgesinformacionanticipada/wgesinformacionanticipada.asmx',
            ],
            'micdta' => [
                'testing' => 'https://wsaduhomoext.afip.gob.ar/DIAV2/wgesregsintia2/wgesregsintia2.asmx',
                'production' => 'https://wsadu.afip.gob.ar/DIAV2/wgesregsintia2/wgesregsintia2.asmx',
            ],
            'paraguay_customs' => [
                'testing' => 'https://securetest.aduana.gov.py/wsdl/gdsf/serviciogdsf',
                'production' => 'https://secure.aduana.gov.py/wsdl/gdsf/serviciogdsf',
            ],
            'desconsolidado' => [
                'testing' => 'https://wsaduhomoext.afip.gob.ar/DIAV2/wgesregsintia2/wgesregsintia2.asmx',
                'production' => 'https://wsadu.afip.gob.ar/DIAV2/wgesregsintia2/wgesregsintia2.asmx',
            ],
            'transbordo' => [
                'testing' => 'https://wsaduhomoext.afip.gob.ar/DIAV2/wgesregsintia2/wgesregsintia2.asmx',
                'production' => 'https://wsadu.afip.gob.ar/DIAV2/wgesregsintia2/wgesregsintia2.asmx',
            ],
            'mane' => [  // NUEVO: URLs para MANE (por ahora archivo local)
                'testing' => 'file://local/mane_export',
                'production' => 'file://local/mane_export',
            ],
        ];

        return $urls[$webserviceType][$environment] ?? $urls['micdta']['testing'];
    }

    /**
     * Obtener tipos de webservice disponibles según roles de empresa - VERSIÓN ACTUALIZADA
     */
    private function getAvailableWebserviceTypes(Company $company): array
    {
        $roles = $company->getRoles() ?? [];
        $types = [];

        if (in_array('Cargas', $roles)) {
            $types['anticipada'] = 'Información Anticipada';
            $types['micdta'] = 'MIC/DTA';
            $types['mane'] = 'MANE/Malvina';  // NUEVO: Agregar MANE para rol Cargas
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
     * Obtener cantidad de contenedores del viaje - MÉTODO HELPER
     * Estructura real: Voyage → Shipment → BillOfLading → ShipmentItem ↔ Container (many-to-many)
     */
    private function getVoyageContainerCount(Voyage $voyage): int
    {
        // Contar contenedores únicos asociados a shipment_items del viaje
        return \DB::table('container_shipment_item')
            ->join('shipment_items', 'container_shipment_item.shipment_item_id', '=', 'shipment_items.id')
            ->join('bills_of_lading', 'shipment_items.bill_of_lading_id', '=', 'bills_of_lading.id')
            ->join('shipments', 'bills_of_lading.shipment_id', '=', 'shipments.id')
            ->where('shipments.voyage_id', $voyage->id)
            ->distinct('container_shipment_item.container_id')
            ->count('container_shipment_item.container_id');
    }

    /**
     * Obtener cantidad de conocimientos de embarque del viaje - MÉTODO HELPER
     * Estructura real: Voyage → Shipment → BillOfLading
     */
    private function getVoyageBillOfLadingCount(Voyage $voyage): int
    {
        return \DB::table('bills_of_lading')
            ->join('shipments', 'bills_of_lading.shipment_id', '=', 'shipments.id')
            ->where('shipments.voyage_id', $voyage->id)
            ->count();
    }

    /**
     * Generar ID único de transacción - MÉTODO HELPER
     */
    private function generateTransactionId(int $companyId, string $webserviceType): string
    {
        $prefix = strtoupper(substr($webserviceType, 0, 3));
        $timestamp = now()->format('YmdHis');
        $random = str_pad(mt_rand(1000, 9999), 4, '0', STR_PAD_LEFT);
        
        return "{$prefix}{$companyId}{$timestamp}{$random}";
    }

    /**
     * Obtener servicio webservice según tipo - MÉTODO HELPER
     */
    private function getWebserviceByType(string $webserviceType, Voyage $voyage = null)
    {
        $company = $voyage ? $voyage->company : auth()->user()->company;
        $user = auth()->user();
        
        switch ($webserviceType) {
            case 'anticipada':
                return new ArgentinaAnticipatedService($company, $user);
            case 'micdta':
                return new ArgentinaMicDtaService($company, $user);
            case 'desconsolidado':
                return new ArgentinaDeconsolidationService($company, $user);
            case 'transbordo':
                return new ArgentinaTransshipmentService($company, $user);
            case 'paraguay_customs':
                return new ParaguayCustomsService($company); // Solo company
             case 'mane':  // NUEVO: Agregar MANE
                return new ArgentinaManeService($company, $user);
            default:
                throw new \Exception("Tipo de webservice no soportado: {$webserviceType}");
        }
    }

    /**
     * Enviar a webservice usando el método correcto según el tipo - VERSIÓN ACTUALIZADA CON MANE
     */
    private function sendToWebservice($service, string $webserviceType, Voyage $voyage, array $options): array
    {
        // ✅ LOG DE DEBUG TEMPORAL  
        Log::info('🔥 ENVIANDO A WEBSERVICE', [
            'service_class' => get_class($service),
            'webservice_type' => $webserviceType,
            'voyage_number' => $voyage->voyage_number,
            'options' => $options
        ]);
        
        try {
            switch ($webserviceType) {
                case 'anticipada':
                    Log::info('🔥 CASO ANTICIPADA - Llamando registerVoyage');
                    $response = $service->registerVoyage($voyage);
                    Log::info('🔥 RESPUESTA RECIBIDA de registerVoyage', [
                        'success' => $response['success'] ?? 'no_definido',
                    ]);
                    return $response;
                    
                    // ✅ NUEVO: Usar automáticamente sistema TRACKs para MIC/DTA
                    Log::info('🔥 CASO MIC/DTA - Usando SISTEMA TRACKs automáticamente');
                    
                    // Obtener primer shipment del voyage para TRACKs
                    $shipment = $voyage->shipments()->first();
                    if (!$shipment) {
                        throw new Exception("El voyage {$voyage->voyage_number} no tiene shipments para procesar con TRACKs");
                    }
                    
                    // Ejecutar flujo TRACKs completo automáticamente
                    $response = $service->sendMicDtaWithTracks($shipment);
                    
                    Log::info('🔥 RESPUESTA TRACKs MIC/DTA', [
                        'success' => $response['success'] ?? false,
                        'tracks_used' => count($response['tracks_used'] ?? []),
                        'transaction_id' => $response['transaction_id'] ?? null,
                    ]);
                    
                    return $response;

                case 'desconsolidado':
                    $tituloMadre = $voyage->shipments()->first();
                    if (!$tituloMadre) {
                        throw new \Exception('El viaje no tiene shipments para desconsolidar');
                    }
                    
                    // Buscar el Bill of Lading maestro (título madre)
                    $masterBill = $tituloMadre->billsOfLading()
                        ->where('is_master_bill', true)
                        ->first();
                    
                    if (!$masterBill) {
                        throw new \Exception('El shipment no tiene un título maestro (Master Bill) para desconsolidar');
                    }
                    
                    // Buscar títulos hijos (House Bills) que referencien al título maestro
                    $houseBills = BillOfLading::where('master_bill_number', $masterBill->bill_number)
                        ->where('is_house_bill', true)
                        ->get();
                    
                    if ($houseBills->isEmpty()) {
                        throw new \Exception('El título madre no tiene títulos hijos para desconsolidar');
                    }
                    
                    // Obtener contenedores reales del shipment
                    $contenedores = [];
                    foreach ($tituloMadre->shipmentItems as $item) {
                        foreach ($item->containers as $container) {
                            $contenedores[] = $container->id;
                        }
                    }
                    
                    // Si no hay contenedores físicos, puede ser carga general (pallets, etc)
                    if (empty($contenedores)) {
                        // Para carga general sin contenedores, usar items como referencia
                        $contenedores = $tituloMadre->shipmentItems->pluck('id')->toArray();
                    }
                    
                    // Preparar datos de títulos hijos basados en House Bills reales
                    $titulosHijos = [];
                    foreach ($houseBills as $houseBill) {
                        $titulosHijos[] = [
                            'numero' => $houseBill->bill_number,
                            'descripcion' => $houseBill->cargo_description ?? 'Título hijo',
                            'peso' => $houseBill->gross_weight_kg ?? 0,
                            'bill_id' => $houseBill->id,
                        ];
                    }
                    
                return $service->registerDeconsolidation($tituloMadre, $contenedores, $titulosHijos);

                case 'transbordo':

                    // ✅ NUEVO: Validar que existan Bills of Lading con datos de consolidación
                    $this->validateTransbordo($voyage);
                    // Para testing/bypass, usar datos mínimos de barcazas
                    $bargeData = [];
                    
                    // Intentar obtener datos reales de barcazas si existen
                    if (method_exists($this, 'prepareBargeDateForTransshipment')) {
                        $bargeData = $this->prepareBargeDateForTransshipment($voyage);
                    }
                    
                    // Si no hay datos reales, crear datos mínimos para testing/bypass
                    if (empty($bargeData)) {
                        $shipments = $voyage->shipments;
                        if ($shipments->count() > 0) {
                            // Crear una barcaza ficticia por cada shipment
                            foreach ($shipments as $index => $shipment) {
                                $bargeData[] = [
                                    'barge_id' => "BARGE-" . ($index + 1),
                                    'vessel_name' => ($voyage->leadVessel?->name ?? 'VESSEL') . ' Barge ' . ($index + 1),
                                    'containers' => [],
                                    'containers_count' => $shipment->containers_loaded ?? 0,
                                    'route' => [
                                        'origin' => $voyage->originPort?->code ?? 'ORIGEN',
                                        'destination' => $voyage->destinationPort?->code ?? 'DESTINO'
                                    ]
                                ];
                            }
                        } else {
                            // Si no hay shipments, crear al menos una barcaza ficticia
                            $bargeData[] = [
                                'barge_id' => 'BARGE-001',
                                'vessel_name' => ($voyage->leadVessel?->name ?? 'VESSEL') . ' Barge 1',
                                'containers' => [],
                                'containers_count' => 0,
                                'route' => [
                                    'origin' => $voyage->originPort?->code ?? 'ORIGEN',
                                    'destination' => $voyage->destinationPort?->code ?? 'DESTINO'
                                ]
                            ];
                        }
                    }
                    
                return $service->registerTransshipment($bargeData, $voyage);

                case 'paraguay_customs':
                return $service->sendImportManifest($voyage, auth()->id());
                    
                case 'mane':  // NUEVO: Agregar caso MANE
                    return $service->sendMane($voyage);

                default:
                    throw new \Exception("Método de envío no implementado para: {$webserviceType}");
            }
        } catch (Exception $e) {
            Log::error('Error en sendToWebservice', [
                'webservice_type' => $webserviceType,
                'voyage_id' => $voyage->id,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'error_code' => 'SEND_ERROR',
                'error_message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Actualizar transacción con respuesta del servicio - MÉTODO HELPER
     */
    private function updateTransactionWithResponse(WebserviceTransaction $transaction, array $response)
{
    // ✅ DEBUG: Verificar nivel de transacciones
    $level = \DB::transactionLevel();
    Log::info('🔧 DB TRANSACTION LEVEL', ['level' => $level, 'transaction_id' => $transaction->id]);

    Log::info('🔧 INICIO updateTransactionWithResponse', [
        'transaction_id' => $transaction->id,
        'response_success' => $response['success'] ?? false
    ]);

    $updateData = [
        'response_at' => now(),
        'updated_at' => now(),
        'response_time_ms' => $response['response_time_ms'] ?? null,
    ];

    if ($response['success'] ?? false) {
        $updateData['status'] = 'success';
        $updateData['confirmation_number'] = $response['confirmation_number'] ?? null;
    } else {
        $updateData['status'] = 'error';
        $updateData['error_message'] = $this->buildErrorMessage($response);
        $updateData['error_code'] = $response['error_code'] ?? 'CONNECTIVITY_ERROR';
    }

    Log::info('🔧 DATOS PARA UPDATE', ['transaction_id' => $transaction->id, 'update_data' => $updateData]);

    // ✅ UPDATE DIRECTO
    $affected = \DB::table('webservice_transactions')
        ->where('id', $transaction->id)
        ->update($updateData);

    Log::info('🔧 UPDATE EJECUTADO', ['transaction_id' => $transaction->id, 'affected_rows' => $affected]);

    // ✅ VERIFICACIÓN INMEDIATA
    $verify = \DB::table('webservice_transactions')
        ->where('id', $transaction->id)
        ->first(['status', 'error_message']);

    Log::info('🔧 VERIFICACIÓN POST-UPDATE', [
        'transaction_id' => $transaction->id,
        'expected_status' => $updateData['status'],
        'actual_status' => $verify->status,
        'match' => $verify->status === $updateData['status']
    ]);
}

    /**
     * ✅ MÉTODO ACTUALIZADO: Enviar manifiesto con soporte para múltiples webservices
     * Ahora soporta estados independientes por webservice específico
     */
    public function send(Request $request, $voyageId)
    {
        
        // ✅ LOG DE DEBUG TEMPORAL
        Log::info('🔥 CONTROLLER SEND INICIADO', [
            'voyage_id' => $voyageId,
            'webservice_type' => $request->webservice_type,
            'user_id' => auth()->id(),
            'request_data' => $request->all()
        ]);

        $voyage = $this->getVoyageForCustoms($voyageId);

        $request->validate([
            'webservice_type' => 'required|string',
            'environment' => 'required|in:testing,production',
            'priority' => 'nullable|in:normal,high,urgent',
        ]);

        Log::info('🔥 VALIDACIÓN PASADA');

        try {
            Log::info('🔥 VALIDACIÓN PASADA');
            // ✅ NUEVO: Determinar país del webservice específico
            $country = $this->getCountryFromWebserviceType($request->webservice_type);
            Log::info('🔥 PAÍS DETERMINADO', ['country' => $country]);
            
            // ✅ NUEVO: Verificar si puede enviar este webservice específico
            $canSend = $this->canSendSpecificWebservice($voyage, $request->webservice_type, $country);
            Log::info('🔥 VERIFICANDO PERMISOS DE ENVÍO ESPECÍFICO', [
                'webservice_type' => $request->webservice_type,
                'country' => $country,
                'can_send_result' => $canSend,
            ]);
            
            if (!$canSend['allowed']) {
                return back()->with('error', 'No se puede enviar: ' . $canSend['reason']);
            }

            Log::info('🔥 VALIDACIÓN PASADA, puede enviar webservice específico', [
                'webservice_type' => $request->webservice_type,
                'country' => $country,
                'can_send' => $canSend,
            ]);

            // Crear transacción de webservice
            $transaction = $this->createWebserviceTransaction($voyage, $request->all());

            Log::info('Transacción creada para envío específico', [
                'transaction_id' => $transaction->id,
                'webservice_type' => $request->webservice_type,
                'country' => $country,
                'voyage_id' => $voyage->id
            ]);

            // ✅ NUEVO: Actualizar estado específico del webservice a 'sending'
            $this->updateSpecificWebserviceStatus(
                $voyage, 
                $request->webservice_type, 
                $country, 
                'sending',
                ['transaction_id' => $transaction->transaction_id]
            );

            // Seleccionar servicio según país y tipo
            $service = $this->getWebserviceByType($request->webservice_type, $voyage);
            Log::info('🔥 SERVICIO CREADO', [
                    'service_class' => get_class($service),
                    'webservice_type' => $request->webservice_type
                ]);
            // Enviar a aduana usando el método correcto según el tipo
            $response = $this->sendToWebservice($service, $request->webservice_type, $voyage, [
                'transaction_id' => $transaction->transaction_id,
                'environment' => $request->environment,
                'priority' => $request->priority ?? 'normal',
            ]);

            // Actualizar transacción con respuesta
            $this->updateTransactionWithResponse($transaction, $response);

            // ✅ NUEVO: Actualizar estado específico según respuesta
            if ($response['success'] ?? false) {
                $this->updateSpecificWebserviceStatus(
                    $voyage, 
                    $request->webservice_type, 
                    $country, 
                    'approved', 
                    [
                        'confirmation_number' => $response['confirmation_number'] ?? null,
                        'external_voyage_number' => $response['external_reference'] ?? $transaction->transaction_id
                    ]
                );
                
                return redirect()->route('company.manifests.customs.status', $transaction->id)
                    ->with('success', "Webservice {$request->webservice_type} enviado a " . strtoupper($country) . ' correctamente.');
            } else {
                // Si falló, actualizar estado específico a error
                $this->updateSpecificWebserviceStatus(
                    $voyage, 
                    $request->webservice_type, 
                    $country, 
                    'error',
                    [
                        'error_code' => $response['error_code'] ?? 'UNKNOWN_ERROR',
                        'error_message' => $this->buildErrorMessage($response)
                    ]
                );
                
                $errorMessage = $this->buildErrorMessage($response);
                return back()->with('error', "Error en envío {$request->webservice_type} a " . strtoupper($country) . ': ' . $errorMessage);
            }

        } catch (\Exception $e) {
            Log::error('Error en envío de manifiesto específico', [
                'voyage_id' => $voyageId,
                'webservice_type' => $request->webservice_type,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // ✅ SI HAY EXCEPCIÓN, actualizar estado específico a error
            if (isset($country) && isset($request->webservice_type)) {
                $this->updateSpecificWebserviceStatus(
                    $voyage, 
                    $request->webservice_type, 
                    $country, 
                    'error',
                    ['error_message' => $e->getMessage()]
                );
            }

            DB::commit(); // Si usas DB::beginTransaction()

            // Al final del método send(), antes del return
            $finalTransactionLevel = \DB::transactionLevel();
            Log::info('🔧 NIVEL FINAL DE TRANSACCIONES', [
                'final_transaction_level' => $finalTransactionLevel,
                'transaction_id' => $transaction->id
            ]);

            // ✅ SI HAY TRANSACCIÓN ACTIVA, HACER COMMIT EXPLÍCITO
            if ($finalTransactionLevel > 0) {
                Log::info('🔧 HACIENDO COMMIT EXPLÍCITO');
                \DB::commit();
            }

            return back()->with('error', 'Error crítico en envío: ' . $e->getMessage());
        }
    }
    
    /**
     * Preparar datos reales de barcazas para transbordos desde ShipmentItems
     */
    private function prepareBargeDateForTransshipment(Voyage $voyage): array
    {
        $bargeData = [];
        
        // Obtener TODOS los ShipmentItems del Viaje (contenedores reales)
        $allItems = collect();
        foreach ($voyage->shipments as $shipment) {
            $items = $shipment->shipmentItems;
            $allItems = $allItems->concat($items);
        }
        
        // Filtrar solo items que tienen contenedores
        $containerItems = $allItems->filter(function ($item) {
            return !empty($item->container_number);
        });
        
        if ($containerItems->isNotEmpty()) {
            // Dividir contenedores en barcazas (máximo 20 por barcaza)
            $containerChunks = $containerItems->chunk(20);
            
            foreach ($containerChunks as $index => $chunk) {
                $bargeData[] = [
                    'barge_id' => ($voyage->vessel->name ?? 'VESSEL') . '-B' . ($index + 1),
                    'vessel_name' => ($voyage->vessel->name ?? 'Unknown Vessel') . ' Barge ' . ($index + 1),
                    'containers_count' => $chunk->count(),
                    'containers' => $chunk->map(function ($item) {
                        return [
                            'container_number' => $item->container_number,
                            'type' => $item->container_type ?: '20GP',
                            'status' => 'full',
                            'weight' => $item->gross_weight ?: 0,
                            'item_reference' => $item->item_reference,
                            'description' => $item->item_description
                        ];
                    })->toArray(),
                    'route' => ($voyage->origin_port->code ?? 'ARBUE') . '-' . ($voyage->destination_port->code ?? 'PYASU'),
                    'vessel_imo' => $voyage->vessel->imo_number ?? null,
                    'voyage_number' => $voyage->voyage_number
                ];
            }
        } else {
            // Si no hay contenedores, usar todos los ShipmentItems como carga general
            $itemChunks = $allItems->chunk(25); // 25 items por barcaza
            
            foreach ($itemChunks as $index => $chunk) {
                $bargeData[] = [
                    'barge_id' => ($voyage->vessel->name ?? 'VESSEL') . '-B' . ($index + 1),
                    'vessel_name' => ($voyage->vessel->name ?? 'Unknown Vessel') . ' Barge ' . ($index + 1),
                    'containers_count' => $chunk->count(),
                    'containers' => $chunk->map(function ($item) {
                        return [
                            'container_number' => $item->container_number ?: 'ITEM-' . $item->id,
                            'type' => $item->container_type ?: 'BULK',
                            'status' => 'full',
                            'weight' => $item->gross_weight ?: 0,
                            'item_reference' => $item->item_reference,
                            'description' => $item->item_description
                        ];
                    })->toArray(),
                    'route' => ($voyage->origin_port->code ?? 'ARBUE') . '-' . ($voyage->destination_port->code ?? 'PYASU'),
                    'vessel_imo' => $voyage->vessel->imo_number ?? null,
                    'voyage_number' => $voyage->voyage_number
                ];
            }
        }
        
        return $bargeData;
    }

    // ========================================
    // ✅ NUEVOS MÉTODOS: SOPORTE MÚLTIPLES WEBSERVICES
    // Complementan el sistema existente sin reemplazarlo
    // ========================================

    /**
     * ✅ NUEVO: Verificar si puede enviar webservice específico
     * Usa el nuevo sistema de estados independientes
     */
    private function canSendSpecificWebservice(Voyage $voyage, string $webserviceType, string $country): array
    {
        // Verificar usando el nuevo sistema de estados independientes
        $webserviceStatus = $voyage->webserviceStatuses()
            ->where('country', $country)
            ->where('webservice_type', $webserviceType)
            ->first();

            Log::info('Webservice status found', [
                'voyage_id' => $voyage->id,
                'webservice_type' => $webserviceType,
                'country' => $country,
                'status' => $webserviceStatus->status,
                'can_send' => $webserviceStatus->canSend(),
            ]);

        if ($webserviceStatus) {
            return [
                'allowed' => $webserviceStatus->canSend(),
                'status' => $webserviceStatus->status,
                'reason' => $webserviceStatus->canSend() ? null : 
                    "Webservice {$webserviceType} ya está en estado: {$webserviceStatus->status}"
            ];
        }

        // Si no existe estado, verificar usando método existente (fallback)
        // Si no existe estado, permitir envío (crear estado automáticamente)
        return [
            'allowed' => true,
            'status' => null,
            'reason' => null
        ];    }

    /**
     * ✅ NUEVO: Actualizar estado de webservice específico
     * Complementa updateCountryStatus() existente con granularidad por webservice
     */
    private function updateSpecificWebserviceStatus(
        Voyage $voyage, 
        string $webserviceType, 
        string $country, 
        string $status, 
        array $additionalData = []
    ): void {
        // Crear o actualizar estado específico del webservice
        $webserviceStatus = VoyageWebserviceStatus::firstOrCreate([
            'voyage_id' => $voyage->id,
            'country' => $country,
            'webservice_type' => $webserviceType,
        ], [
            'company_id' => $voyage->company_id,
            'user_id' => auth()->id(),
            'status' => 'pending',
            'can_send' => true,
            'is_required' => true,
            'retry_count' => 0,
            'max_retries' => 3,
        ]);

        // Preparar datos de actualización
        $updateData = [
            'status' => $status,
            'user_id' => auth()->id(),
        ];

        // Agregar datos específicos según el estado
        switch ($status) {
            case 'sending':
                $updateData['last_sent_at'] = now();
                $updateData['first_sent_at'] = $updateData['first_sent_at'] ?? now();
                if (isset($additionalData['transaction_id'])) {
                    $updateData['last_transaction_id'] = $additionalData['transaction_id'];
                }
                break;

            case 'approved':
                $updateData['approved_at'] = now();
                $updateData['can_send'] = false;
                if (isset($additionalData['confirmation_number'])) {
                    $updateData['confirmation_number'] = $additionalData['confirmation_number'];
                }
                if (isset($additionalData['external_voyage_number'])) {
                    $updateData['external_voyage_number'] = $additionalData['external_voyage_number'];
                }
                break;

            case 'error':
                $updateData['can_send'] = true; // Permitir reintento
                if (isset($additionalData['error_code'])) {
                    $updateData['last_error_code'] = $additionalData['error_code'];
                }
                if (isset($additionalData['error_message'])) {
                    $updateData['last_error_message'] = $additionalData['error_message'];
                }
                break;
        }

        $webserviceStatus->update($updateData);

        // También actualizar el sistema anterior para compatibilidad
        $voyage->updateCountryStatus($country, $status, $additionalData);
    }

    /**
     * ✅ NUEVO: Obtener resumen de webservices disponibles para un voyage
     * Para mostrar en vistas qué webservices pueden enviarse
     */
    private function getAvailableWebservicesForVoyage(Voyage $voyage): array
    {
        $company = $voyage->company;
        $roles = $company->getRoles() ?? [];
        $availableWebservices = [];

        // Argentina - Cargas
        if (in_array('cargas', $roles)) {
            $anticipadaStatus = $voyage->getAnticipadaStatus();
            $micDtaStatus = $voyage->getMicDtaStatus();
            
            $availableWebservices['argentina'] = [
                'anticipada' => [
                    'name' => 'Información Anticipada',
                    'can_send' => $anticipadaStatus ? $anticipadaStatus->canSend() : true,
                    'status' => $anticipadaStatus ? $anticipadaStatus->status : 'pending',
                    'last_sent_at' => $anticipadaStatus?->last_sent_at,
                    'required' => true,
                ],
                'micdta' => [
                    'name' => 'MIC/DTA',
                    'can_send' => $micDtaStatus ? $micDtaStatus->canSend() : true,
                    'status' => $micDtaStatus ? $micDtaStatus->status : 'pending',
                    'last_sent_at' => $micDtaStatus?->last_sent_at,
                    'required' => true,
                    'depends_on' => $anticipadaStatus && $anticipadaStatus->isSuccessful() ? null : 'anticipada',
                ],
            ];
        }

        // Argentina - Desconsolidador
        if (in_array('desconsolidador', $roles)) {
            $desconsolidadoStatus = $voyage->getDesconsolidadoStatus();
            
            $availableWebservices['argentina']['desconsolidado'] = [
                'name' => 'Desconsolidados',
                'can_send' => $desconsolidadoStatus ? $desconsolidadoStatus->canSend() : true,
                'status' => $desconsolidadoStatus ? $desconsolidadoStatus->status : 'pending',
                'last_sent_at' => $desconsolidadoStatus?->last_sent_at,
                'required' => false,
            ];
        }

        // Transbordos (Argentina y Paraguay)
        if (in_array('transbordos', $roles)) {
            $transbordoArStatus = $voyage->getTransbordoStatus('AR');
            $transbordoPyStatus = $voyage->getTransbordoStatus('PY');
            
            $availableWebservices['argentina']['transbordo'] = [
                'name' => 'Transbordos',
                'can_send' => $transbordoArStatus ? $transbordoArStatus->canSend() : true,
                'status' => $transbordoArStatus ? $transbordoArStatus->status : 'pending',
                'last_sent_at' => $transbordoArStatus?->last_sent_at,
                'required' => false,
            ];
            
            $availableWebservices['paraguay']['transbordo'] = [
                'name' => 'Transbordos',
                'can_send' => $transbordoPyStatus ? $transbordoPyStatus->canSend() : true,
                'status' => $transbordoPyStatus ? $transbordoPyStatus->status : 'pending',
                'last_sent_at' => $transbordoPyStatus?->last_sent_at,
                'required' => false,
            ];
        }

        return $availableWebservices;
    }

    /**
     * ✅ NUEVO: Inicializar estados de webservice para Viaje
     * Se llama automáticamente cuando se carga un Viaje en la vista
     */
    private function ensureWebserviceStatusesExist(Voyage $voyage): void
    {
        // Solo crear estados si no existen
        if ($voyage->webserviceStatuses()->count() === 0) {
            $voyage->createInitialWebserviceStatuses();
        }
    }


    /**
     * ✅ NUEVO: Mostrar estados de todos los webservices de un voyage
     * Reemplaza la funcionalidad del enlace "Ver Estados" en customs.blade.php
     */
    # AGREGAR al final de: app/Http/Controllers/Company/Manifests/ManifestCustomsController.php
# ANTES del último }

/**
 * ✅ NUEVO: Mostrar estados de todos los webservices de un voyage
 * Reemplaza la funcionalidad del enlace "Ver Estados" en customs.blade.php
 */
public function voyageStatuses($voyageId)
{
    // Verificar permisos básicos - versión simplificada
    $currentUser = auth()->user();
    $company = $currentUser->getUserCompany();
    
    if (!$company) {
        abort(403, 'No se encontró la empresa asociada.');
    }
    
    // Obtener el Viaje con todas sus relaciones
    $voyage = Voyage::with([
        'webserviceStatuses',
        'webserviceTransactions' => function($query) {
            $query->orderBy('created_at', 'desc');
        },
        'originPort.country',
        'destinationPort.country',
        'company'
    ])
    ->where('company_id', $company->id)
    ->findOrFail($voyageId);

    // Obtener todos los estados de webservice del Viaje
    $webserviceStatuses = $voyage->webserviceStatuses()
        ->orderBy('country')
        ->orderBy('webservice_type')
        ->get();

    // Obtener todas las transacciones del Viaje agrupadas por tipo
    $transactionsByType = $voyage->webserviceTransactions()
        ->orderBy('created_at', 'desc')
        ->get()
        ->groupBy('webservice_type');

    // Preparar datos para la vista
    $statusesData = [];
    
    foreach ($webserviceStatuses as $status) {
        $key = $status->country . '_' . $status->webservice_type;
        
        $statusesData[$key] = [
            'status_record' => $status,
            'transactions' => $transactionsByType->get($status->webservice_type, collect()),
            'country_name' => $status->country === 'AR' ? 'Argentina' : 'Paraguay',
            'webservice_name' => $this->getWebserviceTypeName($status->webservice_type),
            'can_send' => $status->canSend(),
            'last_transaction' => $transactionsByType->get($status->webservice_type)?->first()
        ];
    }

    // También incluir transacciones que no tienen estado en la nueva tabla (sistema legacy)
    foreach ($transactionsByType as $type => $transactions) {
        $country = $this->getCountryFromWebserviceType($type);
        $key = $country . '_' . $type;
        
        if (!isset($statusesData[$key])) {
            $statusesData[$key] = [
                'status_record' => null,
                'transactions' => $transactions,
                'country_name' => $country === 'AR' ? 'Argentina' : 'Paraguay',
                'webservice_name' => $this->getWebserviceTypeName($type),
                'can_send' => true,
                'last_transaction' => $transactions->first()
            ];
        }
    }

    return view('company.manifests.voyage-statuses', compact('voyage', 'statusesData'));
}

    /**
     * Obtener nombre del tipo de webservice
     */
    private function getWebserviceTypeName(string $type): string
    {
        $names = [
            'anticipada' => 'Información Anticipada',
            'micdta' => 'MIC/DTA',
            'desconsolidado' => 'Desconsolidados',
            'transbordo' => 'Transbordos',
            'manifiesto' => 'Manifiestos',
            'mane' => 'MANE/Malvina',
            'paraguay_customs' => 'DNA Paraguay',
            'consulta' => 'Consultas',
            'rectificacion' => 'Rectificaciones',
            'anulacion' => 'Anulaciones',
        ];

        return $names[$type] ?? ucfirst($type);
    }

    /**
     * Obtener país desde tipo de webservice
     */
    private function getCountryFromWebserviceType(string $type): string
    {
        $argentineTypes = ['anticipada', 'micdta', 'desconsolidado', 'transbordo', 'mane'];
        $paraguayTypes = ['paraguay_customs', 'manifiesto'];
        
        if (in_array($type, $argentineTypes)) {
            return 'AR';
        } elseif (in_array($type, $paraguayTypes)) {
            return 'PY';
        }
        
        return 'AR'; // Default
    }

    /**
     * Validar que el viaje tenga los datos necesarios para transbordo
     */
    private function validateTransbordo(Voyage $voyage): void
    {
        // Verificar que exista al menos un Bill of Lading
        $billsCount = BillOfLading::whereHas('shipment', function($query) use ($voyage) {
            $query->where('voyage_id', $voyage->id);
        })->count();
        
        if ($billsCount === 0) {
            throw new \Exception('El viaje no tiene Bills of Lading para enviar a transbordo. Debe crear al menos un conocimiento con items de carga.');
        }
        
        // Verificar que exista al least un Master Bill
        $masterBillExists = BillOfLading::whereHas('shipment', function($query) use ($voyage) {
            $query->where('voyage_id', $voyage->id);
        })->where('is_master_bill', true)->exists();
        
        if (!$masterBillExists) {
            throw new \Exception('El viaje no tiene un Master Bill marcado. Verifique que al menos un conocimiento esté marcado como "is_master_bill = true".');
        }
        
        Log::info('Validación de transbordo exitosa', [
            'voyage_id' => $voyage->id,
            'bills_count' => $billsCount,
            'has_master_bill' => $masterBillExists
        ]);
    }

    /**
     * ✅ NUEVO: Mostrar formulario de adjuntos para Paraguay
     * Integrado en ManifestCustomsController para mantener coherencia
     */
    public function attachmentsIndex(Voyage $voyage)
    {
        // Verificar permisos usando métodos existentes del controlador
        if (!$this->hasCompanyRole('Cargas')) {
            abort(403, 'No tiene permisos para gestionar adjuntos de Paraguay.');
        }

        // Verificar empresa usando método del controlador base
        $company = auth()->user()->getUserCompany();
        if (!$company || !$this->canAccessCompany($voyage->company_id)) {
            abort(403, 'No puede acceder a este viaje.');
        }

        // Cargar relaciones necesarias para la vista
        $voyage->load([
            'originPort:id,name,code', 
            'destinationPort:id,name,code',
            'vessel:id,name,imo_number',
            'shipments.billsOfLading'
        ]);

        // Obtener adjuntos existentes (si los hay)
        $existingAttachments = [];
        // TODO: Implementar consulta de adjuntos existentes cuando se implemente el almacenamiento
        
        return view('company.manifests.customs.attachments', [
            'voyage' => $voyage,
            'company' => $company,
            'existingAttachments' => $existingAttachments,
            'maxFileSize' => '10MB',
            'allowedExtensions' => 'PDF'
        ]);
    }

  

    /**
     * ✅ OPCIONAL: Método helper para verificar si el controlador tiene acceso a empresa
     * Reutiliza lógica existente del sistema
     */
    private function canAccessCompany(int $companyId): bool
    {
        $userCompany = auth()->user()->getUserCompany();
        return $userCompany && $userCompany->id === $companyId;
    }

    /**
     * ✅ OPCIONAL: Método helper para verificar roles de empresa
     * Integrado con el sistema de permisos existente
     */
    private function hasCompanyRole(string $role): bool
    {
        $user = auth()->user();
        $company = $user->getUserCompany();
        
        if (!$company) {
            return false;
        }

        $companyRoles = $company->company_roles ?? [];
        return in_array($role, $companyRoles);
    }

    // ==========================================
    // MÉTODOS DE ADJUNTOS PARAGUAY - AGREGAR AL ManifestCustomsController
    // AGREGAR AL FINAL DE LA CLASE, ANTES DEL ÚLTIMO }
    // ==========================================

    /**
     * ✅ NUEVO: Obtener lista de adjuntos existentes
     * Endpoint: GET /manifests/customs/{voyage}/attachments-list
     */
    public function getAttachmentsList(Voyage $voyage)
    {
        // Verificar permisos
        if (!$this->hasCompanyRole('Cargas')) {
            return response()->json(['error' => 'No tiene permisos'], 403);
        }

        // Verificar empresa
        $company = auth()->user()->getUserCompany();
        if (!$company || !$this->canAccessCompany($voyage->company_id)) {
            return response()->json(['error' => 'No puede acceder a este viaje'], 403);
        }

        try {
            // TODO: Implementar tabla/modelo de adjuntos cuando esté listo
            // Por ahora simular estructura vacía
            
            // Estructura esperada por el frontend:
            $attachments = [];
            // $attachments = VoyageAttachment::where('voyage_id', $voyage->id)
            //     ->where('country', 'PY')
            //     ->orderBy('created_at', 'desc')
            //     ->get()
            //     ->map(function($attachment) {
            //         return [
            //             'id' => $attachment->id,
            //             'name' => $attachment->original_name,
            //             'size' => $this->formatFileSize($attachment->file_size),
            //             'uploaded_at' => $attachment->created_at->format('Y-m-d H:i'),
            //         ];
            //     })->toArray();

            return response()->json($attachments);

        } catch (\Exception $e) {
            Log::error('Error obteniendo lista de adjuntos', [
                'voyage_id' => $voyage->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'error' => 'Error obteniendo adjuntos: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * ✅ NUEVO: Descargar adjunto específico
     * Endpoint: GET /manifests/customs/attachments/{attachment}/download
     */
    public function downloadAttachment($attachmentId)
    {
        // Verificar permisos
        if (!$this->hasCompanyRole('Cargas')) {
            abort(403, 'No tiene permisos para descargar adjuntos');
        }

        try {
            // TODO: Implementar cuando tengamos el modelo de adjuntos
            // $attachment = VoyageAttachment::findOrFail($attachmentId);
            
            // Verificar que pertenece a la empresa
            // if (!$this->canAccessCompany($attachment->voyage->company_id)) {
            //     abort(403, 'No puede acceder a este adjunto');
            // }

            // Por ahora devolver error amigable
            return response()->json([
                'error' => 'Función de descarga pendiente de implementación'
            ], 501);

            // Implementación futura:
            // $filePath = storage_path('app/' . $attachment->file_path);
            // 
            // if (!file_exists($filePath)) {
            //     abort(404, 'Archivo no encontrado');
            // }
            // 
            // return response()->download($filePath, $attachment->original_name);

        } catch (\Exception $e) {
            Log::error('Error descargando adjunto', [
                'attachment_id' => $attachmentId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'error' => 'Error descargando archivo: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * ✅ NUEVO: Eliminar adjunto específico
     * Endpoint: DELETE /manifests/customs/attachments/{attachment}
     */
    public function deleteAttachment($attachmentId)
    {
        // Verificar permisos
        if (!$this->hasCompanyRole('Cargas')) {
            return response()->json(['error' => 'No tiene permisos'], 403);
        }

        try {
            // TODO: Implementar cuando tengamos el modelo de adjuntos
            // $attachment = VoyageAttachment::findOrFail($attachmentId);
            
            // Verificar que pertenece a la empresa
            // if (!$this->canAccessCompany($attachment->voyage->company_id)) {
            //     return response()->json(['error' => 'No puede eliminar este adjunto'], 403);
            // }

            // Por ahora devolver error amigable
            return response()->json([
                'success' => false,
                'error' => 'Función de eliminación pendiente de implementación'
            ], 501);

            // Implementación futura:
            // // Eliminar archivo físico
            // $filePath = storage_path('app/' . $attachment->file_path);
            // if (file_exists($filePath)) {
            //     unlink($filePath);
            // }
            // 
            // // Eliminar registro de BD
            // $attachment->delete();
            // 
            // return response()->json([
            //     'success' => true,
            //     'message' => 'Adjunto eliminado correctamente'
            // ]);

        } catch (\Exception $e) {
            Log::error('Error eliminando adjunto', [
                'attachment_id' => $attachmentId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error eliminando archivo: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * ✅ ACTUALIZAR: Subir adjuntos - VERSIÓN MÚLTIPLE
     * Endpoint: POST /manifests/customs/{voyage}/upload-attachments
     */
    public function uploadAttachments(Request $request, Voyage $voyage)
    {
        // Verificar permisos
        if (!$this->hasCompanyRole('Cargas')) {
            return response()->json(['error' => 'No tiene permisos para subir adjuntos'], 403);
        }

        // Verificar empresa
        $company = auth()->user()->getUserCompany();
        if (!$company || !$this->canAccessCompany($voyage->company_id)) {
            return response()->json(['error' => 'No puede acceder a este viaje'], 403);
        }

        // Validación - MÚLTIPLES archivos
        $request->validate([
            'files' => 'required|array|min:1|max:10', // Hasta 10 archivos
            'files.*' => 'required|file|mimes:pdf|max:10240' // 10MB por archivo PDF
        ]);

        try {
            // OPCIÓN A: Usar ParaguayAttachmentService existente (mantener compatibilidad)
            $attachmentService = new ParaguayAttachmentService($company);
            
            $result = $attachmentService->uploadDocuments(
                $voyage, 
                $request->file('files') ?? [], 
                auth()->id()
            );

            // OPCIÓN B: Implementación directa (cuando tengamos modelo de adjuntos)
            // $uploadedFiles = [];
            // $errors = [];
            // 
            // foreach ($request->file('files') as $index => $file) {
            //     try {
            //         // Generar nombre único
            //         $fileName = time() . '_' . $index . '_' . $file->getClientOriginalName();
            //         
            //         // Guardar archivo
            //         $filePath = $file->storeAs('attachments/paraguay/' . $voyage->id, $fileName);
            //         
            //         // Crear registro en BD
            //         $attachment = VoyageAttachment::create([
            //             'voyage_id' => $voyage->id,
            //             'country' => 'PY',
            //             'webservice_type' => 'paraguay_customs',
            //             'original_name' => $file->getClientOriginalName(),
            //             'file_name' => $fileName,
            //             'file_path' => $filePath,
            //             'file_size' => $file->getSize(),
            //             'mime_type' => $file->getMimeType(),
            //             'uploaded_by' => auth()->id(),
            //         ]);
            //         
            //         $uploadedFiles[] = [
            //             'id' => $attachment->id,
            //             'name' => $attachment->original_name,
            //             'size' => $this->formatFileSize($attachment->file_size),
            //         ];
            //         
            //     } catch (\Exception $e) {
            //         $errors[] = 'Error subiendo ' . $file->getClientOriginalName() . ': ' . $e->getMessage();
            //     }
            // }
            // 
            // return response()->json([
            //     'success' => count($uploadedFiles) > 0,
            //     'uploaded_files' => $uploadedFiles,
            //     'errors' => $errors,
            //     'total_uploaded' => count($uploadedFiles),
            //     'total_errors' => count($errors),
            // ]);

            return response()->json($result);

        } catch (\Exception $e) {
            // Log del error usando el sistema existente del controlador
            Log::error('Error subiendo adjuntos Paraguay múltiples', [
                'voyage_id' => $voyage->id,
                'company_id' => $company->id,
                'user_id' => auth()->id(),
                'files_count' => count($request->file('files') ?? []),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error interno: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * ✅ HELPER: Formatear tamaño de archivo
     */
    private function formatFileSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 1) . ' ' . $units[$i];
    }

    /**
 * Construir mensaje de error detallado desde response
 */
private function buildErrorMessage(array $response): string
{
    // Si hay errores específicos, usarlos
    if (!empty($response['errors']) && is_array($response['errors'])) {
        return implode('. ', $response['errors']);
    }
    
    // Si hay error_message específico
    if (!empty($response['error_message'])) {
        return $response['error_message'];
    }
    
    // Si hay código de error, incluirlo
    if (!empty($response['error_code'])) {
        return "Error {$response['error_code']}: " . ($response['error_details'] ?? 'Error del webservice aduanero');
    }
    
    // Fallback más específico
    return 'Error del webservice aduanero - verificar configuración y datos enviados';
}

    

    /**
     * ✅ NUEVO: Ejecutar solo Paso 1 - RegistrarTitEnvios
     */
    public function sendStep1(Request $request, $voyageId)
    {
        $voyage = $this->getVoyageForCustoms($voyageId);

        $request->validate([
            'environment' => 'required|in:testing,production',
            'priority' => 'nullable|in:normal,high,urgent',
        ]);

        try {
            Log::info('🔥 INICIANDO PASO 1 - RegistrarTitEnvios', [
                'voyage_id' => $voyageId,
                'voyage_number' => $voyage->voyage_number,
                'environment' => $request->environment,
                'user_id' => auth()->id(),
            ]);

            // Obtener primer shipment del voyage
            $shipment = $voyage->shipments()->first();
            if (!$shipment) {
                return response()->json([
                    'success' => false,
                    'error_message' => "El Viaje {$voyage->voyage_number} no tiene shipments asociados",
                ]);
            }

            // Crear servicio MIC/DTA
            $company = auth()->user()->getUserCompany();
            $user = auth()->user();
            $config = ['environment' => $request->environment];
            $service = new \App\Services\Webservice\ArgentinaMicDtaService($company, $user, $config);

            // Ejecutar SOLO RegistrarTitEnvios
            $result = $service->registrarTitEnvios($shipment);

            Log::info('🔥 RESULTADO PASO 1', [
                'success' => $result['success'],
                'transaction_id' => $result['transaction_id'],
                'tracks_count' => count($result['tracks'] ?? []),
            ]);

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'message' => 'Paso 1 completado: Títulos y Envíos registrados',
                    'transaction_id' => $result['transaction_id'],
                    'tracks' => $result['tracks'] ?? [],
                    'tracks_count' => count($result['tracks'] ?? []),
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'error_message' => implode('. ', $result['errors']),
                    'transaction_id' => $result['transaction_id'],
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Error en Paso 1 - RegistrarTitEnvios', [
                'voyage_id' => $voyageId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error_message' => 'Error en Paso 1: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * ✅ NUEVO: Ejecutar solo Paso 2 - RegistrarMicDta con TRACKs validados
     */
    public function sendStep2(Request $request, $voyageId)
    {
        $voyage = $this->getVoyageForCustoms($voyageId);

        $request->validate([
            'step1_transaction_id' => 'required|string',
            'tracks' => 'required|string', // JSON string con array de track_numbers
        ]);

        try {
            Log::info('🔥 INICIANDO PASO 2 - RegistrarMicDta', [
                'voyage_id' => $voyageId,
                'voyage_number' => $voyage->voyage_number,
                'step1_transaction_id' => $request->step1_transaction_id,
                'user_id' => auth()->id(),
            ]);

            // Decodificar TRACKs
            $trackNumbers = json_decode($request->tracks, true);
            if (!is_array($trackNumbers) || empty($trackNumbers)) {
                return response()->json([
                    'success' => false,
                    'error_message' => 'No se proporcionaron TRACKs válidos para el Paso 2',
                ]);
            }

            Log::info('🔥 TRACKs para usar en Paso 2', [
                'tracks_count' => count($trackNumbers),
                'tracks' => $trackNumbers,
            ]);

            // Obtener primer shipment del voyage
            $shipment = $voyage->shipments()->first();
            if (!$shipment) {
                return response()->json([
                    'success' => false,
                    'error_message' => "El Viaje {$voyage->voyage_number} no tiene shipments asociados",
                ]);
            }

            // Crear servicio MIC/DTA
            $company = auth()->user()->getUserCompany();
            $user = auth()->user();
            $service = new \App\Services\Webservice\ArgentinaMicDtaService($company, $user);

            // Ejecutar MIC/DTA usando TRACKs específicos
            $result = $service->sendMicDtaWithTracks($shipment, $trackNumbers);

            Log::info('🔥 RESULTADO PASO 2', [
                'success' => $result['success'],
                'transaction_id' => $result['transaction_id'],
                'tracks_used' => count($result['tracks_used'] ?? []),
            ]);

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'message' => 'Proceso completo: MIC/DTA enviado con TRACKs validados',
                    'transaction_id' => $result['transaction_id'],
                    'tracks_used' => $result['tracks_used'] ?? [],
                    'confirmation_number' => $result['confirmation_number'] ?? null,
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'error_message' => implode('. ', $result['errors']),
                    'transaction_id' => $result['transaction_id'],
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Error en Paso 2 - RegistrarMicDta', [
                'voyage_id' => $voyageId,
                'step1_transaction_id' => $request->step1_transaction_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error_message' => 'Error en Paso 2: ' . $e->getMessage(),
            ]);
        }
    }
   

}