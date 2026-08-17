<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * NULL = la fuente declara un embalaje que nuestro catálogo
         * todavía no representa fielmente.
         *
         * Nunca mapear BAGS/PALLETS/CARTONS/BARRELS a categorías
         * distintas sólo para satisfacer NOT NULL.
         */
        DB::statement(
            'ALTER TABLE bills_of_lading
             MODIFY primary_packaging_type_id BIGINT UNSIGNED NULL'
        );

        DB::statement(
            'ALTER TABLE shipment_items
             MODIFY packaging_type_id BIGINT UNSIGNED NULL'
        );
    }

    public function down(): void
    {
        if (
            DB::table('bills_of_lading')
                ->whereNull('primary_packaging_type_id')
                ->exists()
            || DB::table('shipment_items')
                ->whereNull('packaging_type_id')
                ->exists()
        ) {
            throw new RuntimeException(
                'No se puede restaurar packaging NOT NULL: '
                . 'existen manifiestos sin equivalencia de embalaje.'
            );
        }

        DB::statement(
            'ALTER TABLE bills_of_lading
             MODIFY primary_packaging_type_id BIGINT UNSIGNED NOT NULL'
        );

        DB::statement(
            'ALTER TABLE shipment_items
             MODIFY packaging_type_id BIGINT UNSIGNED NOT NULL'
        );
    }
};
