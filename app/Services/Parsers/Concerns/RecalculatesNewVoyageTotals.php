<?php

namespace App\Services\Parsers\Concerns;

use App\Models\Voyage;
use Illuminate\Support\Facades\DB;

trait RecalculatesNewVoyageTotals
{
    protected function recalculateNewVoyageTotals(Voyage $voyage): void
    {
        $voyageId = $voyage->getKey();

        if (!DB::table('voyages')->where('id', $voyageId)->where('active', true)->exists()) {
            return;
        }

        $shipments = DB::table('shipments')
            ->where('voyage_id', $voyageId)
            ->where('active', true)
            ->pluck('id');

        foreach ($shipments as $shipmentId) {
            $items = DB::table('shipment_items')
                ->join('bills_of_lading', 'bills_of_lading.id', '=', 'shipment_items.bill_of_lading_id')
                ->where('bills_of_lading.shipment_id', $shipmentId)
                ->whereNull('bills_of_lading.deleted_at');

            $weight = (clone $items)
                ->selectRaw('ROUND(COALESCE(SUM(shipment_items.gross_weight_kg), 0), 2) AS total')
                ->value('total');

            $containers = (clone $items)
                ->join('container_shipment_item', 'container_shipment_item.shipment_item_id', '=', 'shipment_items.id')
                ->distinct()
                ->count('container_shipment_item.container_id');

            DB::table('shipments')->where('id', $shipmentId)->update([
                'cargo_weight_loaded' => $weight,
                'containers_loaded' => $containers,
            ]);
        }

        $totals = DB::table('shipments')
            ->where('voyage_id', $voyageId)
            ->where('active', true)
            ->selectRaw('ROUND(COALESCE(SUM(cargo_weight_loaded), 0), 2) AS weight, COALESCE(SUM(containers_loaded), 0) AS containers')
            ->first();

        DB::table('voyages')->where('id', $voyageId)->update([
            'total_cargo_weight' => $totals->weight,
            'total_cargo_weight_loaded' => $totals->weight,
            'total_containers' => $totals->containers,
            'total_containers_loaded' => $totals->containers,
        ]);
    }
}
