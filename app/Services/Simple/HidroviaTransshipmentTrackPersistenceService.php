<?php

namespace App\Services\Simple;

use App\Models\BillOfLading;
use App\Models\Shipment;
use App\Models\Voyage;
use App\Models\WebserviceTrack;
use App\Models\WebserviceTransaction;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Guarda TRACKs reales de un RegistrarTitEnvios aceptado, usando la tabla actual.
 * No transmite SOAP, no cambia el circuito normal MIC/DTA ni crea referencias.
 * Requiere la lista de títulos/envíos validada al preparar la distribución.
 */
final class HidroviaTransshipmentTrackPersistenceService
{
    public function __construct(
        private readonly HidroviaTransshipmentTrackResponseMapper $mapper
    ) {}

    /** @return array<int, WebserviceTrack> */
    public function persistRegistrarTitEnvios(int $transactionId, array $expectedTitles): array
    {
        return DB::transaction(function () use ($transactionId, $expectedTitles): array {
            $transaction = WebserviceTransaction::query()->lockForUpdate()->findOrFail($transactionId);
            if ($transaction->status !== 'success'
                || $transaction->webservice_type !== 'micdta'
                || $transaction->country !== 'AR'
                || $transaction->soap_action !== 'Ar.Gob.Afip.Dga.wgesregsintia2/RegistrarTitEnvios'
                || !$transaction->voyage_id || !$transaction->company_id || !$transaction->user_id
                || trim((string) $transaction->response_xml) === '') {
                throw new InvalidArgumentException('Se requiere una transacción RegistrarTitEnvios de ARCA exitosa y con respuesta real.');
            }

            // Serializar por viaje: dos transacciones distintas no pueden
            // adjudicar TRACKs distintos al mismo título/envío simultáneamente.
            $voyage = Voyage::query()->where('company_id', $transaction->company_id)
                ->lockForUpdate()->find($transaction->voyage_id);
            if (!$voyage) {
                throw new InvalidArgumentException('La transacción no corresponde a un viaje autorizado.');
            }

            $mapped = $this->mapper->mapRegistrarTitEnvios($transaction->response_xml, $expectedTitles);
            $billIds = array_values(array_unique(array_column($mapped, 'bill_of_lading_id')));
            $shipmentIds = array_values(array_unique(array_column($mapped, 'shipment_id')));
            $bills = BillOfLading::query()->whereIn('id', $billIds)
                ->where('is_transit_transshipment', 'S')
                ->whereHas('shipment', fn ($query) => $query->where('voyage_id', $voyage->id))
                ->pluck('id')->map(fn ($id) => (int) $id)->all();
            if (count($bills) !== count($billIds)) {
                throw new InvalidArgumentException('Un conocimiento no pertenece al viaje o no es transbordo.');
            }
            $shipments = Shipment::query()->where('voyage_id', $voyage->id)
                ->whereIn('id', $shipmentIds)
                ->get(['id', 'vessel_id'])->keyBy('id');
            if ($shipments->count() !== count($shipmentIds)) {
                throw new InvalidArgumentException('Una barcaza no pertenece al viaje de la transacción.');
            }
            foreach ($mapped as $row) {
                if ((int) $shipments->get($row['shipment_id'])?->vessel_id
                    !== (int) $row['afip_metadata']['vessel_id']) {
                    throw new InvalidArgumentException('La embarcación de un TRACK no coincide con el envío real.');
                }
            }

            $trackNumbers = array_column($mapped, 'track_number');
            $existing = WebserviceTrack::query()
                ->where('webservice_method', 'RegistrarTitEnvios')
                ->where(fn ($query) => $query
                    ->whereIn('track_number', $trackNumbers)
                    ->orWhereIn('bill_of_lading_id', $billIds))
                ->lockForUpdate()->get();
            $byTrack = $existing->keyBy('track_number');
            foreach ($mapped as $row) {
                $idEnvio = (int) $row['afip_metadata']['idEnvio'];
                $title = $row['afip_title_number'];
                foreach ($existing as $prior) {
                    $sameTrack = $prior->track_number === $row['track_number'];
                    $sameEnvio = (int) $prior->bill_of_lading_id === $row['bill_of_lading_id']
                        && (int) data_get($prior->afip_metadata, 'idEnvio') === $idEnvio;
                    if (!$sameTrack && !$sameEnvio) {
                        continue;
                    }
                    $identical = $sameTrack && $sameEnvio
                        && (int) $prior->webservice_transaction_id === (int) $transaction->id
                        && (int) $prior->shipment_id === $row['shipment_id']
                        && $prior->track_type === 'envio'
                        && $prior->reference_type === 'bill'
                        && $prior->reference_number === $row['reference_number']
                        && $prior->afip_title_number === $title
                        && (int) data_get($prior->afip_metadata, 'vessel_id') === $row['afip_metadata']['vessel_id']
                        && data_get($prior->afip_metadata, 'idTitTrans') === $title;
                    if (!$identical) {
                        throw new InvalidArgumentException(
                            'Un TRACK o título/envío ya está registrado con otra asociación. No se sobrescribe.'
                        );
                    }
                }
            }

            $results = [];
            foreach ($mapped as $row) {
                $previous = $byTrack->get($row['track_number']);
                if ($previous) {
                    // Idempotencia: no cambiar estado, fechas ni cadena de uso.
                    $results[] = $previous;
                    continue;
                }
                $results[] = WebserviceTrack::create($row + [
                    'webservice_transaction_id' => $transaction->id,
                    'generated_at' => $transaction->response_at ?? now(),
                    'status' => 'generated',
                    'created_by_user_id' => $transaction->user_id,
                    'created_from_ip' => $transaction->ip_address,
                    'process_chain' => ['RegistrarTitEnvios'],
                ]);
            }
            return $results;
        });
    }
}
