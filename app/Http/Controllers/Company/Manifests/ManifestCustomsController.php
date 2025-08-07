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
     * Ver estado de una transacción específica
     */
    public function status($transactionId)
    {
        $transaction = WebserviceTransaction::with(['voyage.shipments.billsOfLading'])
            ->where('company_id', auth()->user()->company_id)
            ->findOrFail($transactionId);

        return view('company.manifests.customs-status', compact('transaction'));
    }

    /**
     * Reintentar envío de transacción fallida
     */
    public function retry($transactionId)
    {
        $transaction = WebserviceTransaction::where('company_id', auth()->user()->company_id)
            ->where('status', 'error')
            ->findOrFail($transactionId);

        try {
            $voyage = $transaction->voyage;
            $service = $this->getWebserviceByType($transaction->webservice_type, $voyage);

            // Marcar como reintento
            $transaction->update([
                'status' => 'pending',
                'retry_count' => ($transaction->retry_count ?? 0) + 1,
                'error_message' => null,
            ]);

            $response = $service->send($voyage, [
                'transaction_id' => $transaction->transaction_id,
                'environment' => $transaction->environment,
                'is_retry' => true,
            ]);

            $this->updateTransactionWithResponse($transaction, $response);

            return redirect()->route('company.manifests.customs.status', $transaction->id)
                ->with('success', 'Reintento de envío exitoso.');

        } catch (\Exception $e) {
            $transaction->update([
                'status' => 'error',
                'error_message' => $e->getMessage(),
                'response_at' => now(),
            ]);

            return back()->with('error', 'Error en reintento: ' . $e->getMessage());
        }
    }

    // =========================================================================
    // MÉTODOS HELPER PRIVADOS
    // =========================================================================

    /**
     * Obtener voyage con validaciones de seguridad
     */
    private function getVoyageForCustoms($voyageId): Voyage
    {
        return Voyage::with([
            'shipments.billsOfLading.shipper',
            'shipments.billsOfLading.consignee',
            'shipments.billsOfLading.notifyParty',
            'shipments.billsOfLading.shipmentItems',
            'shipments.vessel',
            'origin_port.country',
            'destination_port.country',
            'company'
        ])
        ->where('company_id', auth()->user()->company_id)
        ->whereIn('status', ['completed', 'in_progress'])
        ->findOrFail($voyageId);
    }

    /**
     * Crear transacción de webservice
     */
    private function createWebserviceTransaction(Voyage $voyage, array $data): WebserviceTransaction
    {
        return WebserviceTransaction::create([
            'company_id' => auth()->user()->company_id,
            'user_id' => Auth::id(),
            'voyage_id' => $voyage->id,
            'webservice_type' => $data['webservice_type'],
            'environment' => $data['environment'],
            'country' => $voyage->destination_port->country->iso_code ?? 'AR',
            'transaction_id' => $this->generateTransactionId($voyage),
            'status' => 'pending',
            'priority' => $data['priority'] ?? 'normal',
            'bl_number' => $voyage->shipments->first()->billsOfLading->first()->bl_number ?? null,
            'voyage_number' => $voyage->voyage_number,
            'vessel_name' => $voyage->shipments->first()->vessel->name ?? 'N/A',
            'pol_code' => $voyage->origin_port->code ?? 'UNKNOWN',
            'pod_code' => $voyage->destination_port->code ?? 'UNKNOWN',
            'container_count' => $voyage->shipments->sum('containers_loaded'),
            'bill_of_lading_count' => $voyage->shipments->sum(function($s) { 
                return $s->billsOfLading->count(); 
            }),
            'total_weight_kg' => $voyage->shipments->sum('cargo_weight_loaded'),
            'currency_code' => 'USD',
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    /**
     * Obtener servicio de webservice según tipo
     */
    private function getWebserviceByType(string $type, Voyage $voyage)
    {
        $country = $voyage->destination_port->country->iso_code ?? 'AR';

        return match($type) {
            'anticipada' => app(ArgentinaAnticipatedService::class),
            'micdta' => app(ArgentinaMicDtaService::class),
            'paraguay_customs' => app(ParaguayCustomsService::class),
            default => throw new \Exception("Tipo de webservice no soportado: {$type}")
        };
    }

    /**
     * Actualizar transacción con respuesta del webservice
     */
    private function updateTransactionWithResponse(WebserviceTransaction $transaction, $response): void
    {
        $updateData = [
            'response_at' => now(),
            'response_time_ms' => $response['response_time_ms'] ?? null,
        ];

        if ($response['success'] ?? false) {
            $updateData['status'] = 'success';
            $updateData['confirmation_number'] = $response['confirmation_number'] ?? null;
            $updateData['success_data'] = $response['data'] ?? null;
            $updateData['tracking_numbers'] = $response['tracking_numbers'] ?? null;
        } else {
            $updateData['status'] = 'error';
            $updateData['error_code'] = $response['error_code'] ?? 'UNKNOWN';
            $updateData['error_message'] = $response['error_message'] ?? 'Error desconocido';
            $updateData['error_details'] = $response['error_details'] ?? null;
        }

        $transaction->update($updateData);
    }

    /**
     * Generar ID único de transacción
     */
    private function generateTransactionId(Voyage $voyage): string
    {
        $prefix = 'MNF';
        $companyCode = str_pad(auth()->user()->company_id, 3, '0', STR_PAD_LEFT);
        $timestamp = now()->format('ymdHis');
        $random = str_pad(mt_rand(0, 999), 3, '0', STR_PAD_LEFT);

        return "{$prefix}{$companyCode}{$timestamp}{$random}";
    }

    /**
     * Obtener estadísticas de envíos a aduana
     */
    private function getCustomsStats(): array
    {
        $companyId = auth()->user()->company_id;
        
        // Ensure company_id is an integer, default to 0 if null
        $companyId = (int) $companyId;

        return [
            'total_voyages' => Voyage::where('company_id', $companyId)
                ->whereHas('shipments')
                ->whereIn('status', ['completed', 'in_progress'])
                ->count(),
            
            'not_sent' => Voyage::where('company_id', $companyId)
                ->whereHas('shipments')
                ->whereIn('status', ['completed', 'in_progress'])
                ->doesntHave('webserviceTransactions')
                ->count(),
                
            'sent_success' => WebserviceTransaction::where('company_id', $companyId)
                ->where('status', 'success')
                ->count(),
                
            'sent_failed' => WebserviceTransaction::where('company_id', $companyId)
                ->where('status', 'error')
                ->count(),
                
            'pending' => WebserviceTransaction::where('company_id', $companyId)
                ->where('status', 'pending')
                ->count(),
                
            'success_rate' => $this->calculateSuccessRate($companyId),
        ];
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
}