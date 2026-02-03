<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * MIGRACIÓN: Agregar valores faltantes al ENUM webservice_type
     * 
     * PROBLEMA RESUELTO:
     * Error "Data truncated for column 'webservice_type'" al usar valores
     * que no existían en el ENUM (convoy, ata_remolcador, salida_zona_primaria, anular_micdta)
     * 
     * TABLAS AFECTADAS:
     * - webservice_transactions
     * - webservice_errors
     * - voyage_webservice_statuses
     * 
     * NUEVOS VALORES:
     * - convoy: RegistrarConvoy (agrupar MIC/DTAs)
     * - ata_remolcador: AsignarATARemol (asignar CUIT remolcador)
     * - salida_zona_primaria: RegistrarSalidaZonaPrimaria
     * - anular_micdta: SolicitarAnularMicDta
     */
    public function up(): void
    {
        // 1. webservice_transactions
        DB::statement("ALTER TABLE webservice_transactions MODIFY COLUMN webservice_type ENUM(
            'anticipada',
            'micdta',
            'desconsolidado',
            'transbordo',
            'manifiesto',
            'consulta',
            'rectificacion',
            'anulacion',
            'paraguay_customs',
            'mane',
            'convoy',
            'ata_remolcador',
            'salida_zona_primaria',
            'anular_micdta'
        ) COMMENT 'Tipo de webservice'");

        // 2. webservice_errors
        DB::statement("ALTER TABLE webservice_errors MODIFY COLUMN webservice_type ENUM(
            'anticipada',
            'micdta',
            'desconsolidado',
            'transbordo',
            'manifiesto',
            'consulta',
            'rectificacion',
            'anulacion',
            'authentication',
            'common',
            'mane',
            'convoy',
            'ata_remolcador',
            'salida_zona_primaria',
            'anular_micdta'
        ) COMMENT 'Tipo de webservice donde ocurre el error'");

        // 3. voyage_webservice_statuses
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
            'paraguay_customs',
            'convoy',
            'ata_remolcador',
            'salida_zona_primaria',
            'anular_micdta'
        ) COMMENT 'Tipo específico de webservice'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 1. webservice_transactions - quitar nuevos valores
        DB::statement("ALTER TABLE webservice_transactions MODIFY COLUMN webservice_type ENUM(
            'anticipada',
            'micdta',
            'desconsolidado',
            'transbordo',
            'manifiesto',
            'consulta',
            'rectificacion',
            'anulacion',
            'paraguay_customs',
            'mane'
        ) COMMENT 'Tipo de webservice'");

        // 2. webservice_errors - quitar nuevos valores
        DB::statement("ALTER TABLE webservice_errors MODIFY COLUMN webservice_type ENUM(
            'anticipada',
            'micdta',
            'desconsolidado',
            'transbordo',
            'manifiesto',
            'consulta',
            'rectificacion',
            'anulacion',
            'authentication',
            'common',
            'mane'
        ) COMMENT 'Tipo de webservice donde ocurre el error'");

        // 3. voyage_webservice_statuses - quitar nuevos valores
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
};