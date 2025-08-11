<?php

namespace App\Http\Controllers\Company\Manifests;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Voyage;
use App\Models\WebserviceTransaction;
use App\Services\Webservice\ArgentinaMicDtaService;
use App\Services\Webservice\ArgentinaAnticipatedService;
use App\Services\Webservice\ParaguayCustomsService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

/**
 * COMPLETADO: ManifestCustomsController
 * 
 * Maneja el envío de manifiestos a aduanas (AFIP Argentina / DNA Paraguay)
 * - Vista para seleccionar manifiestos
 * - Envío a webservices aduaneros
 * - Seguimiento de transacciones
 * - Reintento de envíos fallidos
 */
class ManifestCustomsController extends Controller
{
    /**
     * Vista principal para seleccionar manifiestos y enviar a aduana
     */
    public function index(Request $request)
    {
        // Obtener viajes listos para envío a aduana
        $query = Voyage::with([
            'shipments.billsOfLading', 
            'origin_port.country', 
            'destination_port.country',
            'webserviceTransactions'
        ])
        ->where('company_id', auth()->user()->company_id)
        ->whereHas('shipments') // Solo viajes con cargas
        ->whereIn('status', ['completed', 'in_progress']); // Solo viajes listos

        // Filtrar por país de destino si se especifica
        if ($request->filled('country')) {
            $query->whereHas('destination_port.country', function($q) use ($request) {
                $q->where('iso_code', $request->country);
            });
        }

        // Filtrar por estado de envío
        if ($request->filled('webservice_status')) {
            switch ($request->webservice_status) {
                case 'not_sent':
                    $query->doesntHave('webserviceTransactions');
                    break;
                case 'sent':
                    $query->whereHas('webserviceTransactions', function($q) {
                        $q->where('status', 'success');
                    });
                    break;
                case 'failed':
                    $query->whereHas('webserviceTransactions', function($q) {
                        $q->where('status', 'error');
                    });
                    break;
                case 'pending':
                    $query->whereHas('webserviceTransactions', function($q) {
                        $q->where('status', 'pending');
                    });
                    break;
            }
        }

        $voyages = $query->latest()->paginate(15);

        // Obtener estadísticas de envío
        $stats = $this->getCustomsStats();

        return view('company.manifests.customs', compact('voyages', 'stats'));
    }

    /**
     * Enviar manifiesto individual a la aduana (según país de destino)
     */
    public function send(Request $request, $voyageId)
    {
        $voyage = $this->getVoyageForCustoms($voyageId);

        $request->validate([
            'webservice_type' => 'required|in:anticipada,micdta,paraguay_customs',
            'environment' => 'required|in:testing,production',
            'priority' => 'nullable|in:normal,high,urgent',
        ]);

        try {
            // Crear transacción de webservice
            $transaction = $this->createWebserviceTransaction($voyage, $request->all());

            // Seleccionar servicio según país y tipo
            $service = $this->getWebserviceByType($request->webservice_type, $voyage);

            // Enviar a aduana
            $response = $service->send($voyage, [
                'transaction_id' => $transaction->transaction_id,
                'environment' => $request->environment,
                'priority' => $request->priority ?? 'normal',
            ]);

            // Actualizar transacción con respuesta
            $this->updateTransactionWithResponse($transaction, $response);

            return redirect()->route('company.manifests.customs.status', $transaction->id)
                ->with('success', 'Manifiesto enviado a aduana correctamente.');

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
                
                // Crear transacción
                $transaction = $this->createWebserviceTransaction($voyage, $request->all());
                
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
        $transaction = WebserviceTransaction::with(['user', 'logs'])
            ->where('company_id', auth()->user()->company_id)
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
        $transaction = WebserviceTransaction::where('company_id', auth()->user()->company_id)
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
    // MÉTODOS HELPER PRIVADOS
    // ========================================

    /**
     * Obtener voyage validado para envío a aduana
     */
    private function getVoyageForCustoms($voyageId)
    {
        $voyage = Voyage::with([
            'shipments.billsOfLading',
            'origin_port.country',
            'destination_port.country',
            'company'
        ])
        ->where('company_id', auth()->user()->company_id)
        ->findOrFail($voyageId);

        // Validar que el voyage tiene datos necesarios
        if (!$voyage->shipments()->count()) {
            throw new \Exception('El viaje no tiene shipments para enviar.');
        }

        return $voyage;
    }

    /**
     * Crear transacción de webservice
     */
    private function createWebserviceTransaction(Voyage $voyage, array $data)
    {
        return WebserviceTransaction::create([
            'company_id' => $voyage->company_id,
            'user_id' => Auth::id(),
            'voyage_id' => $voyage->id,
            'webservice_type' => $data['webservice_type'],
            'environment' => $data['environment'],
            'transaction_id' => $this->generateTransactionId($voyage->company_id, $data['webservice_type']),
            'status' => 'pending',
            'voyage_number' => $voyage->voyage_number,
            'vessel_name' => $voyage->vessel->name ?? 'N/A',
            'origin_port' => $voyage->origin_port->code ?? '',
            'destination_port' => $voyage->destination_port->code ?? '',
            'priority' => $data['priority'] ?? 'normal',
            'request_data' => json_encode([
                'voyage' => $voyage->toArray(),
                'shipments_count' => $voyage->shipments()->count(),
                'bills_count' => $voyage->shipments()->withCount('billsOfLading')->get()->sum('bills_of_lading_count')
            ]),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    /**
     * Obtener servicio de webservice por tipo
     */
    private function getWebserviceByType(string $webserviceType, Voyage $voyage = null)
    {
        return match($webserviceType) {
            'anticipada' => new ArgentinaAnticipatedService(),
            'micdta' => new ArgentinaMicDtaService(),
            'paraguay_customs' => new ParaguayCustomsService(),
            default => throw new \Exception("Tipo de webservice no soportado: {$webserviceType}")
        };
    }

    /**
     * Actualizar transacción con respuesta del webservice
     */
    private function updateTransactionWithResponse(WebserviceTransaction $transaction, array $response)
    {
        $updateData = [
            'status' => $response['success'] ? 'success' : 'error',
            'response_at' => now(),
            'response_data' => json_encode($response),
        ];

        if (!$response['success']) {
            $updateData['error_message'] = $response['error'] ?? 'Error desconocido';
        } else {
            $updateData['external_reference'] = $response['reference'] ?? null;
            $updateData['customs_confirmation'] = $response['confirmation'] ?? null;
        }

        $transaction->update($updateData);

        // Log de la operación
        Log::info('Transacción actualizada', [
            'transaction_id' => $transaction->id,
            'status' => $updateData['status'],
            'webservice_type' => $transaction->webservice_type
        ]);
    }

    /**
     * Generar ID único de transacción
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
     * Obtener estadísticas de envíos a aduana
     */
    private function getCustomsStats()
    {
        $companyId = auth()->user()->company_id;

        return [
            'total_sent' => WebserviceTransaction::where('company_id', $companyId)->count(),
            'successful' => WebserviceTransaction::where('company_id', $companyId)
                ->where('status', 'success')->count(),
            'pending' => WebserviceTransaction::where('company_id', $companyId)
                ->where('status', 'pending')->count(),
            'failed' => WebserviceTransaction::where('company_id', $companyId)
                ->where('status', 'error')->count(),
            'last_24h' => WebserviceTransaction::where('company_id', $companyId)
                ->where('created_at', '>=', now()->subDay())->count(),
        ];
    }
}