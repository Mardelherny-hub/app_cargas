<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * ACTUALIZACIÓN: voyage_webservice_statuses
     * Agregar 'paraguay_customs' al enum webservice_type
     * 
     * PROPÓSITO:
     * - Permitir estados de Paraguay Customs en la nueva tabla
     * - Mantener consistencia con webservice_transactions
     * - Soportar bypass para Paraguay
     */
    public function up(): void
    {
        // Agregar 'paraguay_customs' al enum de voyage_webservice_statuses
        DB::statement("ALTER TABLE voyage_webservice_statuses MODIFY COLUMN webservice_type ENUM(
            'anticipada',
            'micdta', 
            'desconsolidado',
            'transbordo',
            'manifiesto',
            'mane',
            'consulta',
            'rectificacion',
            'anulacion',
            'paraguay_customs'
        ) COMMENT 'Tipo específico de webservice'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revertir: quitar 'paraguay_customs' del enum
        DB::statement("ALTER TABLE voyage_webservice_statuses MODIFY COLUMN webservice_type ENUM(
            'anticipada',
            'micdta',
            'desconsolidado',
            'transbordo',
            'manifiesto',
            'mane',
            'consulta',
            'rectificacion',
            'anulacion'
        ) COMMENT 'Tipo específico de webservice'");
    }
};