<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(
            'container_shipment_item',
            function (Blueprint $table) {
                /*
                 * Los precintos pertenecen a esta operación concreta.
                 * El mismo contenedor físico puede tener otros precintos
                 * en un viaje posterior.
                 */
                $table->json('source_seals')
                    ->nullable();

                /*
                 * Una misma unidad física puede aparecer repetida en
                 * distintas BillOfLadingLine del archivo fuente.
                 * Se conservan todos los números originales.
                 */
                $table->json('source_line_numbers')
                    ->nullable();

                /*
                 * Condición aduanera de esta operación.
                 * H = House / casa a casa
                 * P = Pier / muelle a muelle
                 */
                $table->char('container_condition', 1)
                    ->nullable();
            }
        );
    }

    public function down(): void
    {
        Schema::table(
            'container_shipment_item',
            function (Blueprint $table) {
                $table->dropColumn([
                    'source_seals',
                    'source_line_numbers',
                    'container_condition',
                ]);
            }
        );
    }
};
