<?php

namespace App\Services\Simple;

use App\Models\BillOfLading;
use App\Models\HidroviaTransshipmentAllocation;
use App\Models\Shipment;
use App\Models\Voyage;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Prepara los envíos reales para revisión; no genera SOAP ni solicita WSAA. */
final class HidroviaTransshipmentReadinessService
{
    public function __construct(
        private readonly HidroviaTransshipmentDistributionValidator $validator
    ) {}

    /**
     * @return array{voyage_id:int,bill_of_lading_id:int,shipments:array<int,array<string,mixed>>}
     */
    public function prepare(Voyage $voyage, int $companyId, BillOfLading $bill): array
    {
        if ((int) $voyage->company_id !== $companyId) {
            $this->reject('El viaje no pertenece a la empresa autorizada.');
        }

        return DB::transaction(function () use ($voyage, $bill) {
            $lockedBill = BillOfLading::query()->lockForUpdate()->findOrFail($bill->id);
            if ($lockedBill->is_transit_transshipment !== 'S') {
                $this->reject('El conocimiento no está identificado como tránsito/transbordo.');
            }
            $records = HidroviaTransshipmentAllocation::query()
                ->where('bill_of_lading_id', $lockedBill->id)
                ->orderBy('target_shipment_id')->orderBy('shipment_item_id')
                ->orderBy('container_id')->get();

            $rows = $records->map(fn (HidroviaTransshipmentAllocation $record) => [
                'shipment_id' => $record->target_shipment_id,
                'shipment_item_id' => $record->shipment_item_id,
                'container_id' => $record->container_id,
                'package_quantity' => $record->package_quantity,
                'gross_weight_kg' => $record->gross_weight_kg,
            ])->all();
            // Verifica nuevamente contra los datos originales en BD, no confía
            // en una validación realizada al guardar un borrador anterior.
            $this->validator->validate($voyage, $lockedBill, $rows, true);

            $targets = Shipment::query()
                ->where('voyage_id', $voyage->id)
                ->with('vessel.vesselType')
                ->whereIn('id', $records->pluck('target_shipment_id')->unique())
                ->get(['id', 'voyage_id', 'vessel_id', 'shipment_number'])
                ->keyBy('id');
            $shipments = [];
            $idEnvio = 0;
            foreach ($records->groupBy('target_shipment_id') as $targetId => $allocations) {
                // idEnvio es N(3) y lo informa el ATA: secuencia estable
                // dentro del título, ordenada por el shipment de destino.
                if (++$idEnvio > 999) {
                    $this->reject('El título supera los 999 envíos permitidos por ARCA.');
                }
                $target = $targets->get((int) $targetId);
                if (!$target || !$target->vessel_id || $target->vessel?->vesselType?->category !== 'barge') {
                    $this->reject('La distribución requiere una barcaza real registrada para cada envío.');
                }
                $shipments[] = [
                    'id_envio' => $idEnvio,
                    'shipment_id' => (int) $target->id,
                    'shipment_number' => $target->shipment_number,
                    'vessel_id' => (int) $target->vessel_id,
                    'lines' => $allocations->map(fn (HidroviaTransshipmentAllocation $item) => [
                        'shipment_item_id' => (int) $item->shipment_item_id,
                        'container_id' => $item->container_id !== null ? (int) $item->container_id : null,
                        'package_quantity' => (int) $item->package_quantity,
                        'gross_weight_kg' => $item->gross_weight_kg,
                    ])->values()->all(),
                ];
            }
            return [
                'voyage_id' => (int) $voyage->id,
                'bill_of_lading_id' => (int) $lockedBill->id,
                'shipments' => $shipments,
            ];
        });
    }

    private function reject(string $message): never
    {
        throw ValidationException::withMessages(['distribution' => $message]);
    }
}
