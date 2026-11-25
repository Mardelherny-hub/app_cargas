<?php

namespace App\Services\Parsers\Concerns;

use App\Models\BillOfLading;
use App\Models\ManifestImport;
use App\Models\Shipment;
use App\Models\Voyage;
use App\Services\Simple\AfipVesselLock;
use DomainException;

/**
 * Agrega el contenido de un archivo a un viaje existente.
 *
 * Contrato para los parsers:
 * - Sin opción target_voyage_id, resolveTargetVoyage() devuelve null y el
 *   parser sigue exactamente su flujo actual.
 * - Con destino: se usa ese viaje sin validar ni modificar su número ni sus
 *   datos; options['vessel_id'] es la embarcación confirmada.
 * - La carga destino es la existente de esa embarcación en el viaje o una
 *   nueva con la siguiente sequence_in_voyage.
 * - Los conocimientos cuyo bill_number ya existe en el viaje se saltean y
 *   se informan como advertencia.
 * - El registro de objetos creados nunca incluye el viaje destino ni una
 *   carga que ya existía (recordTargetVoyageObjects()).
 */
trait ResolvesTargetVoyage
{
    /** @var string[] */
    protected array $skippedRepeatedBills = [];

    protected bool $targetShipmentCreated = false;

    protected function resolveTargetVoyage(array $options): ?Voyage
    {
        $targetVoyageId = $options['target_voyage_id'] ?? null;

        if (empty($targetVoyageId)) {
            return null;
        }

        $vesselId = (int) ($options['vessel_id'] ?? 0);
        if ($vesselId <= 0) {
            throw new DomainException('Debe confirmar la embarcación para agregar el archivo al viaje.');
        }

        $companyId = $this->targetVoyageCompanyId();

        $voyage = Voyage::where('id', (int) $targetVoyageId)
            ->where('company_id', $companyId)
            ->first();

        if (!$voyage) {
            throw new DomainException('El viaje elegido no existe o no pertenece a su empresa.');
        }

        if ($voyage->status !== 'planning') {
            throw new DomainException(
                "Solo se pueden agregar archivos a viajes en planificación. El viaje {$voyage->voyage_number} no lo está."
            );
        }

        $lock = app(AfipVesselLock::class)->inspectAfipVesselLock($companyId, (int) $voyage->id, $vesselId);
        if ($lock['locked']) {
            $name = \App\Models\Vessel::whereKey($vesselId)->value('name') ?? 'elegida';

            throw new DomainException(
                "La embarcación {$name} tiene registros vigentes en AFIP para este viaje: "
                . implode(' ', array_column($lock['reasons'], 'message'))
            );
        }

        $vesselInVoyage = (int) $voyage->lead_vessel_id === $vesselId
            || Shipment::where('voyage_id', $voyage->id)->where('vessel_id', $vesselId)->exists();

        if (!$vesselInVoyage && !$voyage->is_convoy) {
            throw new DomainException(
                "El viaje {$voyage->voyage_number} no es un convoy. Para agregar otra embarcación, primero debe convertirlo en convoy."
            );
        }

        return $voyage;
    }

    /**
     * Devuelve la carga de la embarcación en el viaje o crea una nueva.
     * $attributes son los datos que el parser usaría para crear su carga;
     * el viaje, la embarcación, la secuencia, el rol y el usuario creador
     * los define este método.
     */
    protected function resolveTargetShipment(Voyage $voyage, int $vesselId, array $attributes): Shipment
    {
        $existing = Shipment::where('voyage_id', $voyage->id)
            ->where('vessel_id', $vesselId)
            ->first();

        if ($existing) {
            $this->targetShipmentCreated = false;

            return $existing;
        }

        $isLead = (int) $voyage->lead_vessel_id === $vesselId;
        $sequence = (int) Shipment::where('voyage_id', $voyage->id)->max('sequence_in_voyage') + 1;

        $shipment = Shipment::create(array_merge($attributes, [
            'voyage_id' => $voyage->id,
            'vessel_id' => $vesselId,
            'sequence_in_voyage' => $sequence,
            'vessel_role' => $isLead ? ($voyage->is_convoy ? 'lead' : 'single') : 'towed',
            'is_lead_vessel' => $isLead,
            'created_by_user_id' => auth()->id() ?? ($attributes['created_by_user_id'] ?? null),
        ]));

        $this->targetShipmentCreated = true;

        return $shipment;
    }

    /**
     * true si el conocimiento ya existe en el viaje: el parser debe
     * saltearlo. Queda anotado para la advertencia final.
     */
    protected function skipRepeatedBill(Voyage $voyage, ?string $billNumber): bool
    {
        $billNumber = trim((string) $billNumber);

        if ($billNumber === '') {
            return false;
        }

        $repeated = BillOfLading::where('bill_number', $billNumber)
            ->whereIn('shipment_id', Shipment::where('voyage_id', $voyage->id)->select('id'))
            ->exists();

        if ($repeated && !in_array($billNumber, $this->skippedRepeatedBills, true)) {
            $this->skippedRepeatedBills[] = $billNumber;
        }

        return $repeated;
    }

    protected function repeatedBillsWarning(): ?string
    {
        if ($this->skippedRepeatedBills === []) {
            return null;
        }

        return 'Se saltearon ' . count($this->skippedRepeatedBills)
            . ' conocimientos que ya existían en el viaje: '
            . implode(', ', $this->skippedRepeatedBills) . '.';
    }

    /**
     * Registra lo creado al agregar a un viaje existente. Exige las cuatro
     * listas para que la reversión no reconstruya nada desde el viaje.
     *
     * @param array{shipment: array, bill: array, item: array, container: array} $objects
     */
    protected function recordTargetVoyageObjects(ManifestImport $import, Voyage $voyage, array $objects): void
    {
        foreach (['shipment', 'bill', 'item', 'container'] as $type) {
            if (!array_key_exists($type, $objects)) {
                throw new \InvalidArgumentException("Falta la lista de objetos creados: {$type}");
            }
        }

        $import->recordExplicitlyCreatedObjects([
            'voyage' => [],
            'shipment' => $this->targetShipmentCreated ? $objects['shipment'] : [],
            'bill' => $objects['bill'],
            'item' => $objects['item'],
            'container' => $objects['container'],
        ]);

        $import->update([
            'voyage_id' => $voyage->id,
            'parser_config' => array_merge($import->parser_config ?? [], [
                'target_voyage' => [
                    'voyage_id' => $voyage->id,
                    'shipment_created' => $this->targetShipmentCreated,
                    'skipped_bills' => $this->skippedRepeatedBills,
                ],
            ]),
        ]);
    }

    private function targetVoyageCompanyId(): int
    {
        // Usuario de empresa u operador: el accessor company resuelve ambos.
        $companyId = auth()->user()?->company?->id;

        if (!$companyId) {
            throw new DomainException('No se pudo determinar la empresa para agregar el archivo al viaje.');
        }

        return (int) $companyId;
    }
}
