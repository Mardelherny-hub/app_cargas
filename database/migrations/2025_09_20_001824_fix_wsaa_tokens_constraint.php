<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Corregir restricción única problemática en wsaa_tokens
     * 
     * PROBLEMA: La restricción actual incluye 'status' lo que impide 
     * múltiples tokens 'revoked' para la misma empresa+servicio+ambiente
     * 
     * SOLUCIÓN: Eliminar restricción problemática y manejar unicidad 
     * a nivel de aplicación (ya implementado en createToken())
     */
    public function up(): void
    {
        Schema::table('wsaa_tokens', function (Blueprint $table) {
            // Eliminar restricción única problemática que incluye status
            $table->dropUnique('uk_wsaa_active_token');
            
            // Agregar índice compuesto para performance (sin restricción única)
            $table->index(['company_id', 'service_name', 'environment'], 'idx_wsaa_company_service_env');
        });
    }

    /**
     * Revertir cambios
     */
    public function down(): void
    {
        Schema::table('wsaa_tokens', function (Blueprint $table) {
            // Eliminar índice agregado
            $table->dropIndex('idx_wsaa_company_service_env');
            
            // Restaurar restricción única original (solo si no hay conflictos)
            try {
                $table->unique(['company_id', 'service_name', 'environment', 'status'], 'uk_wsaa_active_token');
            } catch (\Exception $e) {
                // Si falla, significa que hay datos conflictivos
                \Log::warning('No se pudo restaurar restricción única wsaa_tokens: ' . $e->getMessage());
            }
        });
    }
};