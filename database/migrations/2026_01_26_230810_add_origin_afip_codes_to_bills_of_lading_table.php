<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agregar códigos AFIP de origen (aduana y lugar operativo)
     * 
     * Complementa los campos de destino ya existentes:
     * - discharge_customs_code
     * - operational_discharge_code
     */
    public function up(): void
    {
        Schema::table('bills_of_lading', function (Blueprint $table) {
            $table->string('origin_customs_code', 10)
                ->nullable()
                ->after('origin_loading_date')
                ->comment('Código aduana de origen AFIP (codAdu)');
            
            $table->string('origin_operative_code', 10)
                ->nullable()
                ->after('origin_customs_code')
                ->comment('Código lugar operativo de origen AFIP (codLugOper)');
        });
    }

    public function down(): void
    {
        Schema::table('bills_of_lading', function (Blueprint $table) {
            $table->dropColumn([
                'origin_customs_code',
                'origin_operative_code',
            ]);
        });
    }
};