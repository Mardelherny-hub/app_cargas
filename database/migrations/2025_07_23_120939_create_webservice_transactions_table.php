<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * MÓDULO 4: WEBSERVICES ADUANA - FASE 1A
     * Tabla webservice_transactions - Transacciones de webservices aduaneros
     * 
     * Esta tabla registra todas las transacciones enviadas a webservices de:
     * - Argentina (AFIP): Información Anticipada, MIC/DTA, Desconsolidados, Transbordos
     * - Paraguay (DNA/Aduanas): Manifiestos, Transbordos
     * 
     * REFERENCIAS CONFIRMADAS DEL SISTEMA:
     * - companies, users (obligatorias)
     * - shipments, voyages (opcionales según tipo de webservice)
     * 
     * COMPATIBLE CON WEBSERVICES IDENTIFICADOS:
     * - Argentina MIC/DTA: SOAPAction "Ar.Gob.Afip.Dga.wgesregsintia2/RegistrarMicDta"
     * - Argentina Anticipada: "Ar.Gob.Afip.Dga.Org.wgesinformacionanticipada/RegistrarViaje"
     * - Paraguay: "https://securetest.aduana.gov.py/wsdl/gdsf/serviciogdsf"
     */
    public function up(): void
    {
        Schema::create('webservice_transactions', function (Blueprint $table) {
            // Primary key
            $table->id();

            // Foreign keys to existing system tables (defined manually)
            $table->unsignedBigInteger('company_id')->comment('Empresa que realiza la transacción');
            $table->unsignedBigInteger('user_id')->comment('Usuario que inició la transacción');
            $table->unsignedBigInteger('shipment_id')->nullable()->comment('Envío relacionado (si aplica)');
            $table->unsignedBigInteger('voyage_id')->nullable()->comment('Viaje relacionado (si aplica)');

            // Transaction identification
            $table->string('transaction_id', 100)->comment('ID único de transacción generado por el sistema');
            $table->string('external_reference', 100)->nullable()->comment('Referencia externa del webservice');
            $table->string('batch_id', 50)->nullable()->comment('ID de lote para envíos masivos');

            // Webservice configuration
            $table->enum('webservice_type', [
                'anticipada',        // Información Anticipada Argentina
                'micdta',           // MIC/DTA Argentina  
                'desconsolidado',   // Desconsolidados Argentina
                'transbordo',       // Transbordos Argentina/Paraguay
                'manifiesto',       // Manifiestos Paraguay
                'consulta',         // Consultas de estado
                'rectificacion',    // Rectificaciones
                'anulacion'         // Anulaciones
            ])->comment('Tipo de webservice');

            $table->enum('country', ['AR', 'PY'])->comment('País del webservice (Argentina/Paraguay)');
            $table->string('webservice_url', 500)->comment('URL del webservice utilizada');
            $table->string('soap_action', 200)->nullable()->comment('SOAPAction del webservice');

            // Transaction status and lifecycle
            $table->enum('status', [
                'pending',          // Pendiente de envío
                'validating',       // En validación pre-envío
                'sending',          // Enviando al webservice
                'sent',            // Enviado exitosamente
                'success',         // Respuesta exitosa recibida
                'error',           // Error en la transacción
                'retry',           // En reintento
                'cancelled',       // Cancelado por usuario
                'expired'          // Expirado por timeout
            ])->default('pending')->comment('Estado de la transacción');

            // Retry logic
            $table->integer('retry_count')->default(0)->comment('Número de reintentos realizados');
            $table->integer('max_retries')->default(3)->comment('Máximo número de reintentos');
            $table->timestamp('next_retry_at')->nullable()->comment('Próximo intento programado');
            $table->json('retry_intervals')->nullable()->comment('Intervalos de reintento configurados');

            // XML content
            $table->longText('request_xml')->nullable()->comment('XML de solicitud enviado');
            $table->longText('response_xml')->nullable()->comment('XML de respuesta recibido');
            $table->text('request_headers')->nullable()->comment('Headers HTTP de la solicitud');
            $table->text('response_headers')->nullable()->comment('Headers HTTP de la respuesta');

            // Error handling
            $table->string('error_code', 50)->nullable()->comment('Código de error del webservice');
            $table->text('error_message')->nullable()->comment('Mensaje de error');
            $table->json('error_details')->nullable()->comment('Detalles adicionales del error');
            $table->boolean('is_blocking_error')->default(false)->comment('Error que bloquea reintentos');

            // Success data
            $table->string('confirmation_number', 100)->nullable()->comment('Número de confirmación del webservice');
            $table->json('success_data')->nullable()->comment('Datos adicionales de respuesta exitosa');
            $table->json('tracking_numbers')->nullable()->comment('Números de seguimiento asignados');

            // Timing information
            $table->timestamp('sent_at')->nullable()->comment('Fecha y hora de envío');
            $table->timestamp('response_at')->nullable()->comment('Fecha y hora de respuesta');
            $table->integer('response_time_ms')->nullable()->comment('Tiempo de respuesta en milisegundos');
            $table->timestamp('expires_at')->nullable()->comment('Fecha de expiración de la transacción');

            // Validation and processing
            $table->json('validation_errors')->nullable()->comment('Errores de validación pre-envío');
            $table->boolean('requires_manual_review')->default(false)->comment('Requiere revisión manual');
            $table->string('reviewer_user_id', 20)->nullable()->comment('Usuario que revisó manualmente');
            $table->timestamp('reviewed_at')->nullable()->comment('Fecha de revisión manual');

            // Business context
            $table->decimal('total_weight_kg', 12, 2)->nullable()->comment('Peso total del envío');
            $table->decimal('total_value', 12, 2)->nullable()->comment('Valor total declarado');
            $table->string('currency_code', 3)->default('USD')->comment('Moneda del valor declarado');
            $table->integer('container_count')->default(0)->comment('Cantidad de contenedores');
            $table->integer('bill_of_lading_count')->default(0)->comment('Cantidad de conocimientos');

            // Environment and configuration
            $table->enum('environment', ['testing', 'production'])->comment('Ambiente del webservice');
            $table->string('certificate_used', 100)->nullable()->comment('Certificado digital utilizado');
            $table->json('webservice_config')->nullable()->comment('Configuración específica utilizada');

            // Audit and metadata
            $table->string('ip_address', 45)->nullable()->comment('IP desde donde se realizó el envío');
            $table->string('user_agent', 500)->nullable()->comment('User agent del cliente');
            $table->json('additional_metadata')->nullable()->comment('Metadatos adicionales');

            // Standard timestamps
            $table->timestamp('created_at')->useCurrent()->comment('Fecha de creación');
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate()->comment('Fecha de actualización');

            // Indexes for performance
            $table->index(['company_id', 'status'], 'idx_company_status');
            $table->index(['webservice_type', 'country'], 'idx_webservice_country');
            $table->index(['status', 'next_retry_at'], 'idx_status_retry');
            $table->index(['created_at', 'company_id'], 'idx_created_company');
            $table->index(['transaction_id'], 'idx_transaction_id');
            $table->index(['external_reference'], 'idx_external_reference');
            $table->index(['sent_at', 'response_at'], 'idx_timing');

            // Unique constraints
            $table->unique(['company_id', 'transaction_id'], 'uk_company_transaction');

            // Foreign key constraints will be added separately after confirming table structure
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('shipment_id')->references('id')->on('shipments')->onDelete('set null');
            $table->foreign('voyage_id')->references('id')->on('voyages')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('webservice_transactions');
    }
};