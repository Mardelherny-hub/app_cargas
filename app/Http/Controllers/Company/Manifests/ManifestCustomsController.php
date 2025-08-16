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
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

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
            'webserviceTransactions'
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

                Log::info('DEBUG - Voyage obtenido correctamente', [
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
     * Obtener voyage validado para envío a aduana - CORREGIDO
     */
    private function getVoyageForCustoms($voyageId)
    {
        Log::info('DEBUG - Voyage encontrado exitosamente', [
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
        
        Log::info('DEBUG - Voyage encontrado exitosamente', [
            'voyage_id' => $voyage->id,
            'voyage_number' => $voyage->voyage_number,
            'shipments_count' => $voyage->shipments()->count()
        ]);
        
        // Validar que el voyage tiene datos necesarios
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
 * Determinar país basado en el tipo de webservice - VERSIÓN ACTUALIZADA CON MANE
 */
private function getCountryFromWebserviceType(string $webserviceType): string
{
    $countryMapping = [
        'anticipada' => 'AR',
        'micdta' => 'AR',
        'desconsolidado' => 'AR',
        'transbordo' => 'AR',
        'mane' => 'AR',  // NUEVO: MANE es de Argentina
        'paraguay_customs' => 'PY',
        'manifiesto' => 'PY',
    ];

    return $countryMapping[$webserviceType] ?? 'AR';
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
        try {
            switch ($webserviceType) {
                case 'anticipada':
                    return $service->registerVoyage($voyage);
                    
                case 'micdta':
                    $firstShipment = $voyage->shipments()->first();
                    if (!$firstShipment) {
                        throw new \Exception('El viaje no tiene shipments para enviar MIC/DTA');
                    }
                    return $service->sendMicDta($firstShipment);

                case 'desconsolidado':
                    $tituloMadre = $voyage->shipments()->first();
                    if (!$tituloMadre) {
                        throw new \Exception('El viaje no tiene título madre para desconsolidar');
                    }
                    
                    $titulosHijos = $tituloMadre->billsOfLading ?? collect();
                    if ($titulosHijos->isEmpty()) {
                        throw new \Exception('El título madre no tiene títulos hijos para desconsolidar');
                    }
                    
                    return $service->registerDeconsolidation($tituloMadre, $titulosHijos);

                case 'transbordo':
                    return $service->registerTransshipment($voyage);

                case 'paraguay_customs':
                    return $service->sendManifest($voyage);
                    
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
        $updateData = [
            'response_at' => now(),
            'updated_at' => now(),
        ];

        if ($response['success'] ?? false) {
            $updateData['status'] = 'success';
            $updateData['response_xml'] = $response['response_xml'] ?? null;
            $updateData['external_reference'] = $response['external_reference'] ?? null;
        } else {
            $updateData['status'] = 'error';
            $updateData['error_message'] = $response['error_message'] ?? 'Error de conectividad con webservice';
            $updateData['error_code'] = $response['error_code'] ?? 'CONNECTIVITY_ERROR';
            $updateData['is_blocking_error'] = $response['is_blocking'] ?? false;
            $updateData['requires_manual_review'] = $response['requires_review'] ?? false;
        }

        $transaction->update($updateData);
    }

    /**
     * Enviar manifiesto individual a la aduana (según país de destino) - VERSIÓN CORREGIDA
     */
    public function send(Request $request, $voyageId)
    {
        $voyage = $this->getVoyageForCustoms($voyageId);

        $request->validate([
            'webservice_type' => 'required|string',
            'environment' => 'required|in:testing,production',
            'priority' => 'nullable|in:normal,high,urgent',
        ]);

        Log::info('DEBUG - Datos del formulario recibidos', [
            'webservice_type' => $request->webservice_type,
            'all_request_data' => $request->all()
        ]);

        Log::info('DEBUG - Validación pasó, obteniendo voyage', [
            'voyage_id' => $voyageId
        ]);

        try {
            // Crear transacción de webservice
            $transaction = $this->createWebserviceTransaction($voyage, $request->all());

            Log::info('DEBUG - Transacción creada', [
                'transaction_id' => $transaction->id,
                'webservice_type' => $request->webservice_type
            ]);

            // Seleccionar servicio según país y tipo
            $service = $this->getWebserviceByType($request->webservice_type, $voyage);

            Log::info('DEBUG - Servicio obtenido, enviando', [
                'service_class' => get_class($service)
            ]);

            // Enviar a aduana usando el método correcto según el tipo
            $response = $this->sendToWebservice($service, $request->webservice_type, $voyage, [
                'transaction_id' => $transaction->transaction_id,
                'environment' => $request->environment,
                'priority' => $request->priority ?? 'normal',
            ]);

            // Actualizar transacción con respuesta
            $this->updateTransactionWithResponse($transaction, $response);

            if ($response['success'] ?? false) {
                return redirect()->route('company.manifests.customs.status', $transaction->id)
                    ->with('success', 'Manifiesto enviado a aduana correctamente.');
            } else {
                $errorMessage = $response['error_message'] ?? 'Error de conectividad con el webservice aduanero';
                return back()->with('error', 'Error en envío: ' . $errorMessage);
            }

        } catch (\Exception $e) {
            Log::error('Error al enviar manifiesto a aduana', [
                'voyage_id' => $voyageId,
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
            ]);

            // Marcar transacción como fallida si existe
            if (isset($transaction)) {
                $transaction->update([
                    'status' => 'error',
                    'error_message' => $e->getMessage(),
                    'response_at' => now(),
                ]);
            }

            return back()->with('error', 'Error al enviar a aduana: ' . $e->getMessage());
        }
    }
    
    /**
     * Preparar datos reales de barcazas para transbordos desde ShipmentItems
     */
    private function prepareBargeDateForTransshipment(Voyage $voyage): array
    {
        $bargeData = [];
        
        // Obtener TODOS los ShipmentItems del voyage (contenedores reales)
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
}