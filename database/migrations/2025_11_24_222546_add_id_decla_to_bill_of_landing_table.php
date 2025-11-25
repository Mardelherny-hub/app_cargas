<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * AGREGAR CAMPO id_decla PARA WEBSERVICE AFIP RegistrarTitEnvios
     * 
     * Según manual AFIP - Estructura Destinacion:
     * - idDecla: Identificador de la Destinación - C(16) - OBLIGATORIO
     * - Ejemplo formato: "25001TRB3025222E"
     * 
     * Este campo contiene el número de destinación aduanera pre-cumplida
     * que viene del sistema Malvina de AFIP y es requerido para asociar
     * el envío con la declaración aduanera correspondiente.
     */
    public function up(): void
    {
        Schema::table('bills_of_lading', function (Blueprint $table) {
            $table->string('id_decla', 16)
                ->nullable()
                ->after('permiso_embarque')
                ->comment('Identificador Destinación Aduanera AFIP (ej: 25001TRB3025222E) - Obligatorio para RegistrarTitEnvios');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bills_of_lading', function (Blueprint $table) {
            $table->dropColumn('id_decla');
        });
    }
};