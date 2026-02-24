<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agregar campo is_fractional independiente de is_consolidated
     * 
     * Punto o) DNA Paraguay: "Conocimientos con indicador fraccionado = 'S' y otros 'N'"
     * indFraccTransp debe ser independiente de indConsol
     */
    public function up(): void
    {
        Schema::table('bills_of_lading', function (Blueprint $table) {
            $table->boolean('is_fractional')
                ->default(false)
                ->after('is_consolidated')
                ->comment('Indicador fraccionamiento transporte (S/N) - independiente de consolidado');
        });
    }

    public function down(): void
    {
        Schema::table('bills_of_lading', function (Blueprint $table) {
            $table->dropColumn('is_fractional');
        });
    }
};