<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * AGREGAR CAMPOS AFIP PARA WEBSERVICE WGESINFORMACIONANTICIPADA
     * Clase: Titulo - Método: RegistrarTitulosCbc
     * 
     * Campos según documentación AFIP página 56:
     * - LugarOrigen (String 50)
     * - CodigoPaisLugarOrigen (String 3) - PAY_PAIS
     * - CodigoPaisDestino (String 3) - PAY_PAIS
     * - CodigoAduanaDescarga (String 3) - BUR_DESC
     * - CodigoLugarOperativoDescarga (String 5) - LOT_ADUA
     * - FechaCargaLugarOrigen (Datetime) - Opcional
     */
    public function up(): void
    {
        Schema::table('bills_of_lading', function (Blueprint $table) {
            // Campos de origen
            $table->string('origin_location', 50)
                ->nullable()
                ->after('loading_date')
                ->comment('Lugar de origen de la carga (AFIP: LugarOrigen)');
            
            $table->char('origin_country_code', 3)
                ->nullable()
                ->after('origin_location')
                ->comment('Código país lugar de origen (AFIP: CodigoPaisLugarOrigen - PAY_PAIS)');
            
            $table->datetime('origin_loading_date')
                ->nullable()
                ->after('origin_country_code')
                ->comment('Fecha de carga en lugar de origen (AFIP: FechaCargaLugarOrigen)');
            
            // Campos de destino
            $table->char('destination_country_code', 3)
                ->nullable()
                ->after('discharge_date')
                ->comment('Código país de destino (AFIP: CodigoPaisDestino - PAY_PAIS)');
            
            // Códigos aduaneros de descarga
            $table->string('discharge_customs_code', 3)
                ->nullable()
                ->after('destination_country_code')
                ->comment('Código de aduana de descarga (AFIP: CodigoAduanaDescarga - BUR_DESC)');
            
            $table->string('operational_discharge_code', 5)
                ->nullable()
                ->after('discharge_customs_code')
                ->comment('Código lugar operativo de descarga (AFIP: CodigoLugarOperativoDescarga - LOT_ADUA)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bills_of_lading', function (Blueprint $table) {
            $table->dropColumn([
                'origin_location',
                'origin_country_code',
                'origin_loading_date',
                'destination_country_code',
                'discharge_customs_code',
                'operational_discharge_code',
            ]);
        });
    }
};