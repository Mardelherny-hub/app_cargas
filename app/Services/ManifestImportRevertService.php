<?php

namespace App\Services;

use App\Models\BillOfLading;
use App\Models\Container;
use App\Models\ManifestImport;
use App\Models\Shipment;
use App\Models\ShipmentItem;
use App\Models\Voyage;
use App\Services\Simple\AfipVesselLock;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Reversión de importaciones de manifiestos.
 *
 * - Importación que creó su viaje: elimina lo creado, como siempre.
 * - Importación agregada a un viaje existente: elimina solo lo que agregó y,
 *   si convirtió el viaje en convoy, lo deja como estaba.
 * - Viaje completo: revierte las importaciones agregadas de la más nueva a
 *   la más vieja y después la que creó el viaje.
 */
class ManifestImportRevertService
{
    private const TRACKED_FIELDS = [
        'created_voyage_ids', 'created_shipment_ids', 'created_bill_ids',
        'created_item_ids', 'created_container_ids',
    ];

    public function __construct(
        private VoyageConvoyConversionService $convoyConversion,
        private AfipVesselLock $afipLock,
    ) {
    }

    /**
     * @return string[] resumen de lo eliminado
     */
    public function revertImport(ManifestImport $import, int $userId, string $userName, string $reason): array
    {
        return DB::transaction(function () use ($import, $userId, $userName, $reason) {
            $this->guardRevertible($import);

            if ($this->isTargeted($import)) {
                $this->guardTargetedImport($import);
            } else {
                $this->guardNoActiveAddedImports($import);
            }

            $summary = $this->deleteCreatedObjects($import);

            if ($this->isTargeted($import)) {
                $this->recalculateReceivingShipments($import);
            }

            $snapshot = $import->parser_config['convoy_conversion'] ?? null;
            if (is_array($snapshot)) {
                $this->convoyConversion->restore($snapshot);
                $summary[] = 'el viaje volvió a su configuración anterior';
            } elseif ($this->isTargeted($import)) {
                // La eliminación masiva no dispara eventos de Shipment.
                Voyage::find($import->voyage_id)?->recalculateShipmentStats();
            }

            $import->markAsReverted($userId, [
                'reverted_objects' => $summary,
                'reverted_by' => $userName,
                'reverted_reason' => $reason,
            ]);

            return $summary;
        });
    }

    /**
     * Revierte todas las importaciones vigentes del viaje: primero las
     * agregadas, de la más nueva a la más vieja; al final la que lo creó.
     *
     * @return array<int, string[]> resumen por importación
     */
    public function revertVoyage(Voyage $voyage, int $userId, string $userName, string $reason): array
    {
        return DB::transaction(function () use ($voyage, $userId, $userName, $reason) {
            $imports = $this->activeImports($voyage->id);

            if ($imports->isEmpty()) {
                throw new DomainException("El viaje {$voyage->voyage_number} no tiene importaciones para revertir.");
            }

            [$added, $origin] = $imports->partition(fn (ManifestImport $import) => $this->isTargeted($import));

            if ($origin->count() > 1) {
                throw new DomainException(
                    "El viaje {$voyage->voyage_number} tiene más de una importación que lo creó; reviértalas de a una."
                );
            }

            $summaries = [];
            foreach ($added->sortByDesc('id')->concat($origin) as $import) {
                $summaries[$import->id] = $this->revertImport($import->fresh(), $userId, $userName, $reason);
            }

            return $summaries;
        });
    }

    public function isTargeted(ManifestImport $import): bool
    {
        return isset($import->parser_config['target_voyage']);
    }

    private function activeImports(int $voyageId)
    {
        return ManifestImport::where('voyage_id', $voyageId)
            ->whereNull('reverted_at')
            ->whereIn('status', ['completed', 'completed_with_warnings'])
            ->orderBy('id')
            ->get();
    }

    private function guardRevertible(ManifestImport $import): void
    {
        if (!$import->can_be_reverted || $import->reverted_at) {
            throw new DomainException(
                'La importación no puede ser revertida' . ($import->revert_blocked_reason ? ': ' . $import->revert_blocked_reason : '.')
            );
        }

        $voyage = $import->voyage_id ? Voyage::find($import->voyage_id) : null;

        if ($voyage && $voyage->status !== 'planning') {
            throw new DomainException('Solo se pueden revertir importaciones con viajes en estado de planificación.');
        }

        if ($voyage) {
            $this->guardAfipLock($import, $voyage);
        }
    }

    /**
     * Una importación agregada solo se revierte con registro explícito de
     * lo creado: nunca se reconstruye desde el viaje.
     */
    private function guardTargetedImport(ManifestImport $import): void
    {
        foreach (self::TRACKED_FIELDS as $field) {
            // [] es registro explícito de "creó cero"; NULL es histórico.
            if (($import->getAttributes()[$field] ?? null) === null) {
                throw new DomainException(
                    'La importación agregada no tiene registro completo de lo creado; no se puede revertir sin afectar el viaje.'
                );
            }
        }

        if (!empty($import->created_voyage_ids)) {
            throw new DomainException('Una importación agregada no puede registrar el viaje como creado.');
        }

        // Una conversión a convoy solo se deshace si nada se agregó después.
        if (isset($import->parser_config['convoy_conversion'])) {
            $newer = ManifestImport::where('voyage_id', $import->voyage_id)
                ->where('id', '>', $import->id)
                ->whereNull('reverted_at')
                ->whereIn('status', ['completed', 'completed_with_warnings'])
                ->exists();

            if ($newer) {
                throw new DomainException(
                    'Esta importación convirtió el viaje en convoy y después se agregaron otros archivos. '
                    . 'Revierta primero las importaciones más nuevas.'
                );
            }
        }
    }

    /**
     * La importación que creó el viaje no se revierte mientras el viaje
     * tenga archivos agregados vigentes: borraría también lo agregado.
     */
    private function guardNoActiveAddedImports(ManifestImport $import): void
    {
        if (!$import->voyage_id) {
            return;
        }

        $hasAdded = $this->activeImports($import->voyage_id)
            ->contains(fn (ManifestImport $other) => $other->id !== $import->id && $this->isTargeted($other));

        if ($hasAdded) {
            throw new DomainException(
                'Este viaje tiene archivos agregados. Use “Revertir viaje completo” o revierta primero lo agregado.'
            );
        }
    }

    /**
     * Con registros vigentes en AFIP no se revierte. Lo agregado afecta a
     * sus embarcaciones; una conversión o el viaje creado, a todas.
     */
    private function guardAfipLock(ManifestImport $import, Voyage $voyage): void
    {
        $allVessels = !$this->isTargeted($import) || isset($import->parser_config['convoy_conversion']);

        $shipmentIds = collect($import->created_shipment_ids ?? [])
            ->merge(BillOfLading::withTrashed()->whereIn('id', $import->created_bill_ids ?? [])->pluck('shipment_id'));

        $vesselIds = Shipment::where('voyage_id', $voyage->id)
            ->when(!$allVessels, fn ($query) => $query->whereIn('id', $shipmentIds))
            ->pluck('vessel_id')
            ->unique();

        foreach ($vesselIds as $vesselId) {
            $lock = $this->afipLock->inspectAfipVesselLock((int) $voyage->company_id, (int) $voyage->id, (int) $vesselId);

            if ($lock['locked']) {
                throw new DomainException(
                    'La embarcación tiene registros vigentes en AFIP para este viaje: '
                    . implode(' ', array_column($lock['reasons'], 'message'))
                );
            }
        }
    }

    /**
     * Las cargas existentes que recibieron conocimientos recalculan su peso
     * y sus contenedores desde lo que les queda; el viaje se recalcula
     * desde sus cargas.
     */
    private function recalculateReceivingShipments(ManifestImport $import): void
    {
        $shipmentIds = BillOfLading::withTrashed()
            ->whereIn('id', $import->created_bill_ids ?? [])
            ->pluck('shipment_id')
            ->unique()
            ->diff($import->created_shipment_ids ?? []);

        $pivot = $import->detectContainerPivot();

        Shipment::whereIn('id', $shipmentIds)->get()->each(function (Shipment $shipment) use ($pivot) {
            $shipment->recalculateItemStats();

            if ($pivot) {
                // Contenedores físicos distintos vinculados a los ítems que le quedan.
                $shipment->update(['containers_loaded' => DB::table($pivot['table'])
                    ->whereIn($pivot['item_fk'], $shipment->shipmentItems()->select('shipment_items.id'))
                    ->distinct()
                    ->count($pivot['container_fk'])]);
            }
        });
    }

    /**
     * Elimina lo creado por la importación, dependencias primero.
     *
     * @return string[]
     */
    private function deleteCreatedObjects(ManifestImport $import): array
    {
        $created = $import->getAllCreatedObjectIds();
        Log::info('REVERT - Objetos a eliminar:', ['import_id' => $import->id, 'created_objects' => $created]);

        $summary = [];

        // Pivote: solo las filas de los ítems de esta importación. Un contenedor
        // reutilizado conserva sus vínculos con ítems de otros viajes.
        $pivot = $import->detectContainerPivot();
        if ($pivot && !empty($created['items'])) {
            DB::table($pivot['table'])->whereIn($pivot['item_fk'], $created['items'])->delete();
        }

        $steps = [
            'bills' => [BillOfLading::class, 'conocimientos de embarque'],
            'items' => [ShipmentItem::class, 'items de carga'],
            'containers' => [Container::class, 'contenedores'],
            'shipments' => [Shipment::class, 'envíos'],
            'voyages' => [Voyage::class, 'viajes'],
        ];

        foreach ($steps as $key => [$model, $label]) {
            // Un contenedor que sigue vinculado a ítems de otro viaje ya existía:
            // los parsers lo reutilizan por número. Solo se borra si quedó sin vínculos.
            if ($key === 'containers' && $pivot && !empty($created[$key])) {
                $stillLinked = DB::table($pivot['table'])
                    ->whereIn($pivot['container_fk'], $created[$key])
                    ->pluck($pivot['container_fk'])
                    ->all();
                $created[$key] = array_values(array_diff($created[$key], $stillLinked));
            }

            if (empty($created[$key])) {
                continue;
            }

            $count = $model::whereIn('id', $created[$key])->delete();
            Log::info('REVERT - Eliminados', ['type' => $key, 'count' => $count]);

            if ($count > 0) {
                $summary[] = "{$count} {$label}";
            }
        }

        return $summary;
    }
}
