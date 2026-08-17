<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('container_shipment_item', function (Blueprint $table) {
            $table->integer('package_quantity')
                ->nullable()
                ->comment('Cantidad de bultos en este contenedor')
                ->change();

            $table->decimal('gross_weight_kg', 12, 2)
                ->nullable()
                ->comment('Peso bruto en este contenedor')
                ->change();
        });
    }

    public function down(): void
    {
        $hasUnknownDistribution = DB::table('container_shipment_item')
            ->whereNull('package_quantity')
            ->orWhereNull('gross_weight_kg')
            ->exists();

        if ($hasUnknownDistribution) {
            throw new RuntimeException(
                'No se puede restaurar NOT NULL en container_shipment_item: '
                . 'existen distribuciones por contenedor todavía desconocidas.'
            );
        }

        Schema::table('container_shipment_item', function (Blueprint $table) {
            $table->integer('package_quantity')
                ->nullable(false)
                ->comment('Cantidad de bultos en este contenedor')
                ->change();

            $table->decimal('gross_weight_kg', 12, 2)
                ->nullable(false)
                ->comment('Peso bruto en este contenedor')
                ->change();
        });
    }
};
