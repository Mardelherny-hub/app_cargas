<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('countries', function (Blueprint $table) {
            // Código de país propio de AFIP/María (3 dígitos, ej. Argentina=200, Paraguay=221,
            // Brasil=203). Distinto del numeric_code (ISO) y del customs_code (alfabético).
            // Usado en el archivo MANE/SIM (campos país de la carátula) y potencialmente en AFIP WS.
            $table->string('codigo_afip', 3)->nullable()->after('customs_code');
        });
    }

    public function down(): void
    {
        Schema::table('countries', function (Blueprint $table) {
            $table->dropColumn('codigo_afip');
        });
    }
};