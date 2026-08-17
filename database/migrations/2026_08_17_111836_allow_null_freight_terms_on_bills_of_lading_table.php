<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * El manifiesto puede no declarar modalidad de pago.
         *
         * NULL significa "no informado por la fuente".
         * No ampliar el enum ni convertir ausencia en prepaid.
         */
        DB::statement(
            "ALTER TABLE bills_of_lading
             MODIFY freight_terms
             ENUM('prepaid','collect','third_party')
             NULL DEFAULT NULL"
        );
    }

    public function down(): void
    {
        /*
         * No fabricar prepaid para poder hacer rollback.
         * Si existen datos NULL, el rollback requiere una
         * decisión explícita sobre esos BL.
         */
        if (
            DB::table('bills_of_lading')
                ->whereNull('freight_terms')
                ->exists()
        ) {
            throw new RuntimeException(
                'No se puede restaurar freight_terms NOT NULL: '
                . 'existen BL cuyo término de flete no fue informado.'
            );
        }

        DB::statement(
            "ALTER TABLE bills_of_lading
             MODIFY freight_terms
             ENUM('prepaid','collect','third_party')
             NOT NULL DEFAULT 'prepaid'"
        );
    }
};
