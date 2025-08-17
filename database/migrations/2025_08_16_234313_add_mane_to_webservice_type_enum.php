<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * MÓDULO 5: WEBSERVICE MANE - FASE FINAL
     * Agregar 'mane' al enum webservice_type en tablas del sistema webservice
     * 
     * PROPÓSITO:
     * - Permitir registro de transacciones MANE en webservice_transactions
     * - Permitir errores MANE en webservice_errors 
     * - Completar integración del módulo MANE/Malvina
     * 
     * COMPATIBILIDAD:
     * - Mantiene todos los valores existentes del enum
     * - Agrega 'mane' para transacciones del sistema Malvina
     * - Funciona con MySQL 5.7+ y MariaDB
     */
    public function up(): void
    {
        // 1. Agregar 'mane' al enum de webservice_transactions
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

        // 2. Agregar 'mane' al enum de webservice_errors  
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
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revertir webservice_transactions - quitar 'mane' del enum
        DB::statement("ALTER TABLE webservice_transactions MODIFY COLUMN webservice_type ENUM(
            'anticipada',
            'micdta',
            'desconsolidado',
            'transbordo', 
            'manifiesto',
            'consulta',
            'rectificacion',
            'anulacion',
            'paraguay_customs'
        ) COMMENT 'Tipo de webservice'");

        // Revertir webservice_errors - quitar 'mane' del enum
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
            'common'
        ) COMMENT 'Tipo de webservice donde ocurre el error'");
    }
};