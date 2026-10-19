<?php

namespace App\Services\Simple;

use App\Models\BillOfLading;
use App\Models\Voyage;
use Illuminate\Validation\ValidationException;

/**
 * Reglas locales para repartir líneas de un mismo BL entre embarcaciones
 * (Shipments) del mismo viaje. No persiste ni realiza llamadas a Aduana.
 *
 * Cada asignación declara shipment_id, shipment_item_id, package_quantity,
 * gross_weight_kg y, cuando corresponda, container_id. Los IDs se contrastan
 * con las relaciones existentes: no se crean títulos ni embarcaciones.
 */
final class HidroviaTransshipmentDistributionValidator
{
    public function validate(Voyage $voyage, BillOfLading $bill, array $allocations, bool $requireComplete = false): void
    {
        if (!$bill->shipment || (int) $bill->shipment->voyage_id !== (int) $voyage->id) {
            $this->reject('El conocimiento no pertenece al viaje seleccionado.');
        }

        $shipments = $voyage->shipments()->pluck('id')->map(fn ($id) => (int) $id)->all();
        $items = $bill->shipmentItems()->with('containers')->get()->keyBy('id');
        if ($items->isEmpty() || ($requireComplete && $allocations === [])) {
            $this->reject('El conocimiento no tiene líneas o asignaciones para distribuir.');
        }

        $totals = [];
        $seen = [];
        $containerShipments = [];
        foreach ($allocations as $index => $row) {
            if (!is_array($row)) {
                $this->reject('Cada asignación debe contener sus datos de mercadería.');
            }
            $shipmentId = filter_var($row['shipment_id'] ?? null, FILTER_VALIDATE_INT);
            $itemId = filter_var($row['shipment_item_id'] ?? null, FILTER_VALIDATE_INT);
            if (!$shipmentId || !in_array($shipmentId, $shipments, true)) {
                $this->reject("Asignación {$index}: la embarcación no pertenece al viaje.");
            }
            $item = $itemId ? $items->get($itemId) : null;
            if (!$item) {
                $this->reject("Asignación {$index}: la línea no pertenece al conocimiento.");
            }

            $containerId = $row['container_id'] ?? null;
            if ($containerId !== null && $containerId !== '') {
                $containerId = filter_var($containerId, FILTER_VALIDATE_INT);
                if (!$containerId || !$item->containers->contains('id', $containerId)) {
                    $this->reject("Asignación {$index}: el contenedor no pertenece a esa línea.");
                }
                if (isset($containerShipments[$containerId]) && $containerShipments[$containerId] !== $shipmentId) {
                    $this->reject("Contenedor {$containerId}: no puede quedar asignado a dos embarcaciones.");
                }
                $containerShipments[$containerId] = $shipmentId;
            } else {
                $containerId = null;
            }

            $key = $itemId . ':' . $shipmentId . ':' . ($containerId ?? '-');
            if (isset($seen[$key])) {
                $this->reject("Asignación {$index}: línea, embarcación y contenedor duplicados.");
            }
            $seen[$key] = true;
            $quantity = filter_var($row['package_quantity'] ?? null, FILTER_VALIDATE_INT);
            if ($quantity === false || $quantity < 0) {
                $this->reject("Asignación {$index}: cantidad de bultos inválida.");
            }
            $weight = $this->weightInHundredths($row['gross_weight_kg'] ?? null, "Asignación {$index}");
            if ($quantity === 0 && $weight === 0 && $containerId === null) {
                $this->reject("Asignación {$index}: no se admite una fila sin mercadería ni contenedor real.");
            }
            $totals[$itemId]['quantity'] = ($totals[$itemId]['quantity'] ?? 0) + $quantity;
            $totals[$itemId]['weight'] = ($totals[$itemId]['weight'] ?? 0) + $weight;
        }

        foreach ($items as $item) {
            $declaredQuantity = filter_var($item->package_quantity, FILTER_VALIDATE_INT);
            if ($declaredQuantity === false || $declaredQuantity < 0) {
                $this->reject("Línea {$item->id}: cantidad declarada inválida.");
            }
            $declaredWeight = $this->weightInHundredths($item->gross_weight_kg, "Línea {$item->id}");
            $distributedQuantity = $totals[$item->id]['quantity'] ?? 0;
            $distributedWeight = $totals[$item->id]['weight'] ?? 0;
            if ($distributedQuantity > $declaredQuantity || $distributedWeight > $declaredWeight) {
                $this->reject("Línea {$item->id}: la distribución supera los bultos o el peso declarado.");
            }
            if ($requireComplete && ($distributedQuantity !== $declaredQuantity || $distributedWeight !== $declaredWeight)) {
                $this->reject("Línea {$item->id}: la distribución debe completar exactamente bultos y peso.");
            }
            if ($requireComplete && $item->containers->isNotEmpty()) {
                $expected = $item->containers->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all();
                $assigned = collect($allocations)
                    ->filter(fn ($row) => (int) ($row['shipment_item_id'] ?? 0) === (int) $item->id)
                    ->pluck('container_id');
                if ($assigned->contains(fn ($id) => $id === null || $id === '')) {
                    $this->reject("Línea {$item->id}: todas las cargas deben indicar su contenedor real.");
                }
                $actual = $assigned->map(fn ($id) => (int) $id)->sort()->values()->all();
                if ($expected !== $actual) {
                    $this->reject("Línea {$item->id}: deben asignarse todos sus contenedores reales una sola vez.");
                }
            }
        }
    }

    /** Conserva dos decimales, sin sumar cantidades mediante float. */
    private function weightInHundredths(mixed $value, string $location): int
    {
        $value = trim((string) $value);
        if (!preg_match('/^\d+(?:\.\d{1,2})?$/D', $value)) {
            $this->reject("{$location}: peso bruto inválido (máximo dos decimales).");
        }
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        if (strlen(ltrim($whole, '0')) > 10) {
            $this->reject("{$location}: peso bruto fuera de rango.");
        }
        return ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
    }

    private function reject(string $message): never
    {
        throw ValidationException::withMessages(['distribution' => $message]);
    }
}
