<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $companyId = DB::table('companies')
            ->where('tax_id', '30612732503')
            ->value('id');

        $voyage = DB::table('voyages')
            ->where('id', 369)
            ->where('company_id', $companyId)
            ->where('voyage_number', 'ABX 2620S')
            ->first();

        if (!$companyId || !$voyage) {
            return;
        }

        $shipmentIds = DB::table('shipments')
            ->where('voyage_id', $voyage->id)
            ->pluck('id');

        $bills = DB::table('bills_of_lading')
            ->whereIn('shipment_id', $shipmentIds)
            ->get();
        $voyageCodes = DB::table('shipment_items')
            ->whereIn('bill_of_lading_id', $bills->pluck('id'))
            ->whereNotNull('operational_discharge_code')
            ->where('operational_discharge_code', '<>', '')
            ->pluck('operational_discharge_code')
            ->map(fn ($value) => trim((string) $value))
            ->unique()
            ->values();

        if ($voyageCodes->count() !== 1) {
            throw new RuntimeException(
                'ABX 2620S no tiene un único lugar operativo de descarga en sus ítems.'
            );
        }

        $operativeCode = (string) $voyageCodes->first();

        $location = DB::table('afip_operative_locations')
            ->where('location_code', $operativeCode)
            ->where('is_active', true)
            ->first();

        if (!$location) {
            throw new RuntimeException(
                "El lugar operativo {$operativeCode} no existe o no está activo."
            );
        }

        DB::transaction(function () use ($bills, $operativeCode, $location) {
            foreach ($bills as $bill) {
                $itemCodes = DB::table('shipment_items')
                    ->where('bill_of_lading_id', $bill->id)
                    ->whereNotNull('operational_discharge_code')
                    ->where('operational_discharge_code', '<>', '')
                    ->pluck('operational_discharge_code')
                    ->map(fn ($value) => trim((string) $value))
                    ->unique()
                    ->values();

                if ($itemCodes->count() !== 1) {
                    throw new RuntimeException(
                        "El conocimiento {$bill->bill_number} no tiene un lugar operativo único."
                    );
                }

                if ((string) $itemCodes->first() !== $operativeCode) {
                    throw new RuntimeException(
                        "El conocimiento {$bill->bill_number} difiere del lugar operativo del viaje."
                    );
                }

                DB::table('bills_of_lading')
                    ->where('id', $bill->id)
                    ->update([
                        'operational_discharge_code' => $operativeCode,
                        'discharge_customs_code' => (string) $location->customs_code,
                        'updated_at' => now(),
                    ]);
            }
        });
    }

    public function down(): void
    {
        // Backfill respaldado por los ítems importados del mismo viaje.
        // No se revierte automáticamente para no restaurar datos inconsistentes.
    }
};
