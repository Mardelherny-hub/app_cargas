<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El formato TFP manda todos los precintos de un contenedor juntos en un
     * unico campo NROPRECINTA, separados por espacios. Verificado 07/08/2026
     * sobre ASUNCION B: hay valores de 33, 34, 42, 43 y hasta 51 caracteres
     * ("3191409 0016572 SENACSA 0916609 FX44515475 EX124150"), contra los 50
     * que permitia la columna.
     *
     * Se amplian las tres columnas de precinto por igual: cualquiera puede
     * recibir un valor multiple segun el emisor.
     */
    public function up(): void
    {
        Schema::table('containers', function (Blueprint $table) {
            $table->string('customs_seal', 255)->nullable()->change();
            $table->string('shipper_seal', 255)->nullable()->change();
            $table->string('carrier_seal', 255)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('containers', function (Blueprint $table) {
            $table->string('customs_seal', 50)->nullable()->change();
            $table->string('shipper_seal', 50)->nullable()->change();
            $table->string('carrier_seal', 50)->nullable()->change();
        });
    }
};