<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * MÓDULO 5: ENVÍOS ADUANEROS ADICIONALES - FASE 1
     * Agregar campo IdMaria para generación de archivos MANE/Malvina
     * 
     * PROPÓSITO:
     * - Campo IdMaria (varchar 10) requerido para sistema Malvina de Aduana
     * - Se usa en la primera línea del archivo MANE
     * - Solicitado por Roberto Benbassat en chat WhatsApp
     * 
     * COMPATIBILIDAD:
     * - Solo para empresas con rol "Cargas" 
     * - Sistemas legacy de Aduana Argentina
     * - Futuro webservice MANE cuando esté disponible
     */
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            // Campo IdMaria para archivos MANE/Malvina
            $table->string('id_maria', 10)
                ->nullable()
                ->after('roles_config')
                ->comment('ID María para sistema Malvina de Aduana (MANE legacy)');
                
            // Índice para búsquedas por IdMaria
            $table->index('id_maria', 'idx_companies_id_maria');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropIndex('idx_companies_id_maria');
            $table->dropColumn('id_maria');
        });
    }
};