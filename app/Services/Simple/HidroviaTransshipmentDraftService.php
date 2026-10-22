<?php

namespace App\Services\Simple;

use App\Models\BillOfLading;
use App\Models\HidroviaTransshipmentAllocation;
use App\Models\Voyage;
use App\Models\WebserviceTransaction;
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

        return DB::transaction(function () use ($voyage, $companyId, $bill, $allocations, $userId) {
            // Un lock por viaje serializa asignaciones de contenedores
            // incluso si llegan desde conocimientos distintos.
            Voyage::query()->where('company_id', $companyId)
                ->lockForUpdate()->findOrFail($voyage->id);
            // El transbordo modifica qué carga corresponderá a cada envío.
            // No permitir cambios si el voyage ya inició la registración
            // de títulos/envíos ante ARCA o existe un resultado incierto.
            $micDtaIniciado = WebserviceTransaction::query()
                ->where('company_id', $companyId)
                ->where('voyage_id', $voyage->id)
                ->where('webservice_type', 'micdta')
                ->where('country', 'AR')
                // Cualquier intento registrado bloquea el reparto. El SOAP
                // existente puede persistir status=error tras un timeout SIN
                // request_xml ni sent_at: la ausencia de esos campos no prueba
                // que ARCA no haya recibido el envío. Revisión manual primero.
                ->where(function ($query) {
                    $query->where('soap_action', 'like', '%/RegistrarTitEnvios')
                        ->orWhere('soap_action', 'like', '%/RegistrarEnvios')
                        ->orWhere('soap_action', 'like', '%/RegistrarMicDta');
                })
                ->exists();
            if ($micDtaIniciado) {
                $this->reject('El viaje ya tiene una operación MIC/DTA iniciada o de resultado incierto: no se puede alterar su distribución.');
            }

            // Serializa las modificaciones concurrentes del mismo título.
            $lockedBill = BillOfLading::query()->lockForUpdate()->findOrFail($bill->id);
            if (!$lockedBill->canBeEdited()) {
                $this->reject('El conocimiento no se encuentra en un borrador editable.');
            }
            if ($lockedBill->is_transit_transshipment !== 'S') {
                $this->reject('El conocimiento no está identificado para tránsito/transbordo.');
            }
            $this->validator->validate($voyage, $lockedBill, $allocations);

            foreach ($allocations as $row) {
                $containerId = $row['container_id'] ?? null;
                if ($containerId === null || $containerId === '') {
                    continue;
                }
                $conflictingAllocation = HidroviaTransshipmentAllocation::query()
                    ->where('container_id', $containerId)
                    ->where('bill_of_lading_id', '!=', $lockedBill->id)
                    ->where('target_shipment_id', '!=', (int) $row['shipment_id'])
                    ->whereHas('billOfLading.shipment', fn ($query) =>
                        $query->where('voyage_id', $voyage->id))
                    ->exists();
                if ($conflictingAllocation) {
                    $this->reject('Un contenedor ya está asignado a otra barcaza del mismo viaje.');
                }
            }

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
