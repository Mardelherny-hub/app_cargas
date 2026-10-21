<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Fecha documentada del arribo del viaje externo ATA-DESC.
        // No crea un Voyage ni asigna fechas a expedientes existentes.
        Schema::table('deconsolidation_operations', function (Blueprint $table) {
            $table->date('arrival_date')->nullable()->after('master_bill_number');
        });
    }

    public function down(): void
    {
        if (DB::table('deconsolidation_operations')
            ->whereNotNull('arrival_date')->exists()) {
            throw new RuntimeException(
                'No se puede eliminar arrival_date: existen fechas documentadas que deben conservarse.'
            );
        }

        Schema::table('deconsolidation_operations', function (Blueprint $table) {
            $table->dropColumn('arrival_date');
        });
    }
};
