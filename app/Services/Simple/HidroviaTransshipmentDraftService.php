<?php

namespace App\Services\Simple;

use App\Models\BillOfLading;
use App\Models\HidroviaTransshipmentAllocation;
use App\Models\Voyage;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Almacena únicamente distribuciones en borrador; nunca transmite a ARCA. */
final class HidroviaTransshipmentDraftService
{
    public function __construct(
        private readonly HidroviaTransshipmentDistributionValidator $validator
    ) {}

    /**
     * Reemplazo atómico de toda la distribución del título.
     * El ID de empresa se obtiene de la sesión/autorización, nunca del payload.
     */
    public function replaceDraft(
        Voyage $voyage,
        int $companyId,
        BillOfLading $bill,
        array $allocations,
        ?int $userId = null
    ): Collection {
        if ((int) $voyage->company_id !== $companyId) {
            $this->reject('El viaje no pertenece a la empresa autorizada.');
        }

        return DB::transaction(function () use ($voyage, $bill, $allocations, $userId) {
            // Serializa las modificaciones concurrentes del mismo título.
            $lockedBill = BillOfLading::query()->lockForUpdate()->findOrFail($bill->id);
            if (!$lockedBill->canBeEdited()) {
                $this->reject('El conocimiento no se encuentra en un borrador editable.');
            }
            if ($lockedBill->is_transit_transshipment !== 'S') {
                $this->reject('El conocimiento no está identificado para tránsito/transbordo.');
            }
            $this->validator->validate($voyage, $lockedBill, $allocations);

            HidroviaTransshipmentAllocation::query()
                ->where('bill_of_lading_id', $lockedBill->id)
                ->delete();

            foreach ($allocations as $row) {
                HidroviaTransshipmentAllocation::create([
                    'bill_of_lading_id' => $lockedBill->id,
                    'shipment_item_id' => (int) $row['shipment_item_id'],
                    'target_shipment_id' => (int) $row['shipment_id'],
                    'container_id' => $row['container_id'] ?? null,
                    'package_quantity' => (int) $row['package_quantity'],
                    'gross_weight_kg' => $row['gross_weight_kg'],
                    'created_by_user_id' => $userId,
                ]);
            }

            return HidroviaTransshipmentAllocation::query()
                ->where('bill_of_lading_id', $lockedBill->id)
                ->orderBy('shipment_item_id')->orderBy('target_shipment_id')
                ->orderBy('container_id')->get();
        });
    }

    private function reject(string $message): never
    {
        throw ValidationException::withMessages(['distribution' => $message]);
    }
}
