<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * MÓDULO 4: WEBSERVICES ADUANA - WSAA Tokens Cache
     * Tabla especializada para tokens de autenticación WSAA de AFIP
     * 
     * PROPÓSITO:
     * - Cachear tokens WSAA válidos para evitar re-autenticación
     * - Prevenir error "El CEE ya posee un TA valido" 
     * - Permitir múltiples tokens por empresa (diferentes servicios)
     * - Auditoría y debugging de autenticación AFIP
     * 
     * FUNCIONALIDADES:
     * - Un token por empresa+servicio
     * - Expiración automática de tokens
     * - Limpieza de tokens vencidos
     * - Auditoría de uso de tokens
     * - Soporte para múltiples ambientes (testing/production)
     * 
     * REFERENCIAS CONFIRMADAS:
     * - companies (tabla confirmada con id como unsignedBigInteger)
     */
    public function up(): void
    {
        Schema::create('wsaa_tokens', function (Blueprint $table) {
            // Primary key
            $table->id();

            // Foreign key a companies (confirmada en project_knowledge_search)
            $table->unsignedBigInteger('company_id')->comment('Empresa propietaria del token');

            // Identificación del servicio AFIP
            $table->string('service_name', 50)->comment('Nombre del servicio AFIP (wgesregsintia2, etc.)');
            $table->enum('environment', ['testing', 'production'])->comment('Ambiente del webservice');
            
            // Datos del token WSAA
            $table->text('token')->comment('Token WSAA de AFIP');
            $table->text('sign')->comment('Firma WSAA de AFIP');
            $table->timestamp('issued_at')->comment('Fecha/hora de emisión del token');
            $table->timestamp('expires_at')->comment('Fecha/hora de expiración del token');
            
            // Metadatos del token
            $table->string('generation_time', 50)->comment('Generation time del LoginTicket original');
            $table->string('unique_id', 100)->comment('Unique ID del LoginTicket original');
            $table->string('certificate_used', 255)->nullable()->comment('Certificado utilizado para generar el token');
            
            // Control de uso
            $table->integer('usage_count')->default(0)->comment('Cantidad de veces que se usó el token');
            $table->timestamp('last_used_at')->nullable()->comment('Última vez que se usó el token');
            
            // Estado del token
            $table->enum('status', ['active', 'expired', 'revoked', 'error'])->default('active')->comment('Estado del token');
            $table->text('error_message')->nullable()->comment('Mensaje de error si el token falló');
            
            // Auditoría
            $table->string('created_by_process', 100)->comment('Proceso que creó el token');
            $table->json('creation_context')->nullable()->comment('Contexto de creación (voyage_id, transaction_id, etc.)');
            
            // Timestamps estándar
            $table->timestamps();

            // ÍNDICES OPTIMIZADOS

            // Índice principal para búsquedas rápidas
            $table->index(['company_id', 'service_name', 'environment', 'status'], 'idx_wsaa_lookup');
            
            // Índice para limpieza de tokens expirados
            $table->index(['expires_at', 'status'], 'idx_wsaa_cleanup');
            
            // Índice para tokens activos
            $table->index(['status', 'expires_at'], 'idx_wsaa_active');
            
            // Índice para auditoría por empresa
            $table->index(['company_id', 'created_at'], 'idx_wsaa_company_audit');
            
            // Índice para estadísticas de uso
            $table->index(['last_used_at', 'usage_count'], 'idx_wsaa_usage_stats');

            // RESTRICCIONES ÚNICAS
            
            // Un token activo por empresa+servicio+ambiente
            $table->unique(['company_id', 'service_name', 'environment', 'status'], 'uk_wsaa_active_token');

            // FOREIGN KEY CONSTRAINTS
            
            $table->foreign('company_id', 'fk_wsaa_tokens_company')
                ->references('id')->on('companies')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wsaa_tokens');
    }
};