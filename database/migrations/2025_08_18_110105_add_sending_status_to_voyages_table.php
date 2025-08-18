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
     * ACTUALIZACIÓN: tabla voyages
     * Agregar 'sending' a los enums argentina_status y paraguay_status
     * 
     * PROPÓSITO:
     * - Permitir estado 'sending' durante envío a webservices
     * - Mantener compatibilidad con controladores existentes
     */
    public function up(): void
    {
        // Actualizar argentina_status para incluir 'sending'
        DB::statement("ALTER TABLE voyages MODIFY COLUMN argentina_status ENUM(
            'pending', 
            'sending',
            'sent', 
            'approved', 
            'rejected', 
            'error'
        ) COMMENT 'Estado en webservice Argentina'");

        // Actualizar paraguay_status para incluir 'sending'  
        DB::statement("ALTER TABLE voyages MODIFY COLUMN paraguay_status ENUM(
            'pending',
            'sending', 
            'sent', 
            'approved', 
            'rejected', 
            'error'
        ) COMMENT 'Estado en webservice Paraguay'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revertir argentina_status
        DB::statement("ALTER TABLE voyages MODIFY COLUMN argentina_status ENUM(
            'pending', 
            'sent', 
            'approved', 
            'rejected', 
            'error'
        ) COMMENT 'Estado en webservice Argentina'");

        // Revertir paraguay_status
        DB::statement("ALTER TABLE voyages MODIFY COLUMN paraguay_status ENUM(
            'pending', 
            'sent', 
            'approved', 
            'rejected', 
            'error'
        ) COMMENT 'Estado en webservice Paraguay'");
    }
};