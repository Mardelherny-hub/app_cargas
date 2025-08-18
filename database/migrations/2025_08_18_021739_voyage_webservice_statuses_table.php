<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * MIGRACIÓN: TABLA voyage_webservice_statuses
     * 
     * PROPÓSITO: Reemplazar campos únicos argentina_status/paraguay_status
     * por un sistema de estados independientes por webservice.
     * 
     * PERMITE:
     * - Múltiples webservices por voyage (anticipada + micdta + desconsolidado + transbordo)
     * - Estados independientes por tipo de webservice
     * - Historial de cambios de estado
     * - Reintentos específicos por webservice
     * - Consultas eficientes por país/tipo
     */
    public function up(): void
    {
        Schema::create('voyage_webservice_statuses', function (Blueprint $table) {
            $table->id();
            
            // ========================================
            // RELACIONES PRINCIPALES
            // ========================================
            $table->foreignId('company_id')
                ->constrained('companies')
                ->onDelete('cascade')
                ->comment('Empresa propietaria del voyage');
                
            $table->foreignId('voyage_id')
                ->constrained('voyages')
                ->onDelete('cascade')
                ->comment('Viaje al que pertenece el estado');
                
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->onDelete('set null')
                ->comment('Usuario que realizó la última actualización');

            // ========================================
            // IDENTIFICACIÓN DEL WEBSERVICE
            // ========================================
            $table->enum('country', ['AR', 'PY'])
                ->comment('País del webservice (Argentina/Paraguay)');
                
            $table->enum('webservice_type', [
                'anticipada',       // Información Anticipada Argentina
                'micdta',          // MIC/DTA Argentina  
                'desconsolidado',  // Desconsolidados Argentina
                'transbordo',      // Transbordos Argentina/Paraguay
                'manifiesto',      // Manifiestos Paraguay
                'mane',           // MANE/Malvina Argentina
                'consulta',       // Consultas de estado
                'rectificacion',  // Rectificaciones
                'anulacion'       // Anulaciones
            ])->comment('Tipo específico de webservice');

            // ========================================
            // ESTADO Y CONTROL
            // ========================================
            $table->enum('status', [
                'not_required',    // No se requiere para este voyage
                'pending',         // Pendiente de envío
                'validating',      // En validación pre-envío
                'sending',         // Enviando al webservice
                'sent',           // Enviado exitosamente
                'approved',       // Aprobado por aduana
                'rejected',       // Rechazado por aduana
                'error',          // Error técnico
                'retry',          // En proceso de reintento
                'cancelled',      // Cancelado por usuario
                'expired'         // Expirado
            ])->default('pending')
            ->comment('Estado actual del webservice para este voyage');
            
            $table->boolean('can_send')
                ->default(true)
                ->comment('Si está habilitado para envío');
                
            $table->boolean('is_required')
                ->default(true)
                ->comment('Si es obligatorio para este voyage');

            // ========================================
            // REFERENCIAS EXTERNAS
            // ========================================
            $table->string('last_transaction_id', 100)
                ->nullable()
                ->comment('ID de la última transacción realizada');
                
            $table->string('confirmation_number', 100)
                ->nullable()
                ->comment('Número de confirmación de aduana');
                
            $table->string('external_voyage_number', 50)
                ->nullable()
                ->comment('Número de viaje asignado por aduana');

            // ========================================
            // CONTROL DE ERRORES Y REINTENTOS
            // ========================================
            $table->integer('retry_count')
                ->default(0)
                ->comment('Número de reintentos realizados');
                
            $table->integer('max_retries')
                ->default(3)
                ->comment('Máximo número de reintentos permitidos');
                
            $table->timestamp('next_retry_at')
                ->nullable()
                ->comment('Fecha del próximo reintento programado');
                
            $table->string('last_error_code', 50)
                ->nullable()
                ->comment('Código del último error');
                
            $table->text('last_error_message')
                ->nullable()
                ->comment('Mensaje del último error');

            // ========================================
            // FECHAS Y AUDITORÍA
            // ========================================
            $table->timestamp('first_sent_at')
                ->nullable()
                ->comment('Fecha del primer envío');
                
            $table->timestamp('last_sent_at')
                ->nullable()
                ->comment('Fecha del último envío');
                
            $table->timestamp('approved_at')
                ->nullable()
                ->comment('Fecha de aprobación por aduana');
                
            $table->timestamp('expires_at')
                ->nullable()
                ->comment('Fecha de expiración del estado');

            $table->timestamps();

            // ========================================
            // ÍNDICES Y RESTRICCIONES
            // ========================================
            
            // Índice único: Un estado por voyage + país + tipo de webservice
            $table->unique(
                ['voyage_id', 'country', 'webservice_type'], 
                'unique_voyage_country_webservice'
            );
            
            // Índices para consultas frecuentes
            $table->index(['company_id', 'status'], 'idx_company_status');
            $table->index(['voyage_id', 'status'], 'idx_voyage_status');
            $table->index(['country', 'webservice_type', 'status'], 'idx_country_type_status');
            $table->index(['last_sent_at', 'status'], 'idx_sent_status');
            $table->index(['next_retry_at'], 'idx_retry_queue');
            
            // Índice para búsquedas por confirmation_number
            $table->index(['confirmation_number'], 'idx_confirmation');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('voyage_webservice_statuses');
    }
};