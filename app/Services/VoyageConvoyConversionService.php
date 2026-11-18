<?php

namespace App\Services;

use App\Models\BillOfLading;
use App\Models\ManifestImport;
use App\Models\Shipment;
use App\Models\Voyage;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Convierte un viaje simple en convoy al agregarle otra embarcación desde
 * una importación, y lo deja exactamente como estaba al revertirla.
 *
 * La foto previa guarda solo lo que la conversión cambia:
 * - del viaje: voyage_type, is_convoy y lead_vessel_id;
 * - de cada carga existente: los campos de rol que cambiaron, con su valor
 *   anterior;
 * - la carga creada para el líder, si el líder no tenía carga y no es la
 *   embarcación del archivo (el líder puede ir sin carga).
 */
class VoyageConvoyConversionService
{
    private const VOYAGE_FIELDS = ['voyage_type', 'is_convoy', 'lead_vessel_id'];
    private const SHIPMENT_FIELDS = ['vessel_role', 'is_lead_vessel'];

    /**
     * @return array{voyage_id:int, voyage:array, shipments:array<int,array>, created_shipment_id:?int}
     */
    public function convert(Voyage $voyage, int $leaderVesselId, int $fileVesselId): array
    {
        if ($voyage->is_convoy) {
            throw new DomainException("El viaje {$voyage->voyage_number} ya es un convoy.");
        }

        return DB::transaction(function () use ($voyage, $leaderVesselId, $fileVesselId) {
            $snapshot = [
                'voyage_id' => (int) $voyage->id,
                'voyage' => [
                    'voyage_type' => $voyage->voyage_type,
                    'is_convoy' => (bool) $voyage->is_convoy,
                    'lead_vessel_id' => $voyage->lead_vessel_id !== null ? (int) $voyage->lead_vessel_id : null,
                ],
                'shipments' => [],
                'created_shipment_id' => null,
            ];

            $voyage->update([
                'voyage_type' => 'convoy',
                'is_convoy' => true,
                'lead_vessel_id' => $leaderVesselId,
            ]);

            $leaderHasShipment = false;

            foreach (Shipment::where('voyage_id', $voyage->id)->get() as $shipment) {
                $isLeader = (int) $shipment->vessel_id === $leaderVesselId;
                $leaderHasShipment = $leaderHasShipment || $isLeader;

                $target = [
                    'vessel_role' => $isLeader ? 'lead' : 'towed',
                    'is_lead_vessel' => $isLeader,
                ];

                $previous = [];
                foreach (self::SHIPMENT_FIELDS as $field) {
                    if ($shipment->{$field} !== $target[$field]) {
                        $previous[$field] = $shipment->{$field};
                    }
                }

                if ($previous !== []) {
                    $snapshot['shipments'][(int) $shipment->id] = $previous;
                    $shipment->update(array_intersect_key($target, $previous));
                }
            }

            // La carga de la embarcación del archivo la crea el parser.
            if (!$leaderHasShipment && $leaderVesselId !== $fileVesselId) {
                $sequence = (int) Shipment::where('voyage_id', $voyage->id)->max('sequence_in_voyage') + 1;

                $leader = Shipment::create([
                    'voyage_id' => $voyage->id,
                    'vessel_id' => $leaderVesselId,
                    'shipment_number' => sprintf('SHP-%s-%03d', $voyage->voyage_number, $sequence),
                    'sequence_in_voyage' => $sequence,
                    'vessel_role' => 'lead',
                    'is_lead_vessel' => true,
                    'cargo_capacity_tons' => null,
                    'container_capacity' => 0,
                    'status' => 'planning',
                    'created_by_user_id' => auth()->id(),
                ]);

                $snapshot['created_shipment_id'] = (int) $leader->id;
            }

            return $snapshot;
        });
    }

    /**
     * Guarda la foto en la importación y registra la carga del líder como
     * creada, para que la reversión la elimine.
     */
    public function attachToImport(ManifestImport $import, array $snapshot): void
    {
        $import->update([
            'parser_config' => array_merge($import->parser_config ?? [], [
                'convoy_conversion' => $snapshot,
            ]),
        ]);

        if ($snapshot['created_shipment_id'] !== null) {
            $import->recordExplicitlyCreatedObjects([
                'shipment' => array_merge(
                    (array) ($import->created_shipment_ids ?? []),
                    [$snapshot['created_shipment_id']]
                ),
            ]);
        }
    }

    /**
     * Deja el viaje y sus cargas como estaban antes de la conversión.
     */
    public function restore(array $snapshot): void
    {
        DB::transaction(function () use ($snapshot) {
            $createdId = $snapshot['created_shipment_id'] ?? null;

            if ($createdId !== null && ($leader = Shipment::find($createdId))) {
                if (BillOfLading::withTrashed()->where('shipment_id', $leader->id)->exists()) {
                    throw new DomainException(
                        'La carga del líder tiene conocimientos cargados después de la conversión; no se puede deshacer.'
                    );
                }
                $leader->delete();
            }

            foreach ($snapshot['shipments'] ?? [] as $shipmentId => $fields) {
                Shipment::where('id', (int) $shipmentId)
                    ->first()
                    ?->update(array_intersect_key($fields, array_flip(self::SHIPMENT_FIELDS)));
            }

            $voyage = Voyage::findOrFail($snapshot['voyage_id']);
            $voyage->update(array_intersect_key($snapshot['voyage'], array_flip(self::VOYAGE_FIELDS)));
            $voyage->refresh()->recalculateShipmentStats();
        });
    }
}
