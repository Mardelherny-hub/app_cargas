<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * MÓDULO 4: WEBSERVICES ADUANA - FASE 1C
     * Tabla webservice_responses - Respuestas estructuradas de webservices
     *
     * Esta tabla almacena las respuestas procesadas y estructuradas de los webservices
     * aduaneros, extrayendo información específica de negocio para uso posterior.
     *
     * REFERENCIAS CONFIRMADAS DEL SISTEMA:
     * - webservice_transactions (obligatoria, recién creada)
     *
     * CASOS DE USO ESPECÍFICOS:
     * - Argentina MIC/DTA: <RegistrarTitEnviosResponse> con tracks y números
     * - Argentina Anticipada: Números de viaje asignados por aduana
     * - Paraguay: Referencias de manifiestos y estados
     * - Consultas de estado: Actualizaciones de seguimiento
     * - Rectificaciones: Nuevas referencias tras correcciones
     */
    public function up(): void
    {
        Schema::create('webservice_responses', function (Blueprint $table) {
            // Primary key
            $table->id();

            // Foreign key to transaction (defined manually)
            $table->unsignedBigInteger('transaction_id')->comment('Transacción webservice relacionada');

            // Response classification
            $table->enum('response_type', [
                'success',          // Respuesta exitosa completa
                'partial_success',  // Éxito parcial (algunos elementos fallaron)
                'business_error',   // Error de reglas de negocio
                'validation_error', // Error de validación de datos
                'system_error',     // Error del sistema aduanero
                'timeout',          // Timeout del webservice
                'unknown'           // Respuesta no clasificada
            ])->comment('Tipo de respuesta recibida');

            $table->boolean('requires_action')->default(false)->comment('Requiere acción adicional del usuario');
            $table->enum('processing_status', [
                'completed',        // Procesamiento completado
                'pending',          // Pendiente de procesamiento adicional
                'requires_retry',   // Requiere reintento
                'requires_manual',  // Requiere intervención manual
                'cancelled'         // Procesamiento cancelado
            ])->default('completed')->comment('Estado del procesamiento de la respuesta');

            // Core response data from webservices
            $table->string('confirmation_number', 100)->nullable()->comment('Número de confirmación principal');
            $table->string('reference_number', 100)->nullable()->comment('Número de referencia aduanera');
            $table->string('voyage_number', 50)->nullable()->comment('Número de viaje asignado');
            $table->string('manifest_number', 50)->nullable()->comment('Número de manifiesto');

            // Tracking and follow-up numbers
            $table->json('tracking_numbers')->nullable()->comment('Números de seguimiento (array de strings)');
            $table->json('container_tracks')->nullable()->comment('Tracks específicos por contenedor');
            $table->json('bill_tracks')->nullable()->comment('Tracks específicos por conocimiento');
            $table->json('customs_references')->nullable()->comment('Referencias aduaneras adicionales');

            // Argentina specific fields (based on MIC/DTA documentation)
            $table->string('argentina_tit_envio', 50)->nullable()->comment('Número TitEnvio Argentina');
            $table->json('argentina_tracks_env')->nullable()->comment('TracksEnv de respuesta Argentina');
            $table->json('argentina_tracks_cont_vacios')->nullable()->comment('TracksContVacios Argentina');
            $table->string('argentina_id_transaccion', 100)->nullable()->comment('IdTransaccion confirmado');

            // Paraguay specific fields
            $table->string('paraguay_gdsf_reference', 50)->nullable()->comment('Referencia GDSF Paraguay');
            $table->string('paraguay_manifest_id', 50)->nullable()->comment('ID de manifiesto Paraguay');
            $table->json('paraguay_control_data')->nullable()->comment('Datos de control Paraguay');

            // Status information from customs
            $table->string('customs_status', 50)->nullable()->comment('Estado en el sistema aduanero');
            $table->timestamp('customs_processed_at')->nullable()->comment('Fecha de procesamiento aduanero');
            $table->string('customs_office_code', 20)->nullable()->comment('Código de oficina aduanera');
            $table->string('customs_officer', 100)->nullable()->comment('Funcionario aduanero responsable');

            // Business validation results
            $table->boolean('data_validated')->default(false)->comment('Datos validados por aduana');
            $table->json('validation_warnings')->nullable()->comment('Advertencias de validación');
            $table->json('validation_errors')->nullable()->comment('Errores de validación específicos');
            $table->decimal('total_amount_accepted', 12, 2)->nullable()->comment('Monto total aceptado');
            $table->decimal('total_weight_accepted', 12, 2)->nullable()->comment('Peso total aceptado');

            // Document status
            $table->boolean('documents_approved')->default(false)->comment('Documentos aprobados');
            $table->json('pending_documents')->nullable()->comment('Documentos pendientes de entrega');
            $table->json('rejected_documents')->nullable()->comment('Documentos rechazados');
            $table->timestamp('documents_due_date')->nullable()->comment('Fecha límite para documentos');

            // Payment and fees
            $table->decimal('customs_fees', 10, 2)->nullable()->comment('Tasas aduaneras aplicadas');
            $table->decimal('processing_fees', 10, 2)->nullable()->comment('Tasas de procesamiento');
            $table->string('payment_reference', 100)->nullable()->comment('Referencia de pago');
            $table->enum('payment_status', [
                'not_required', 'pending', 'paid', 'overdue', 'cancelled'
            ])->nullable()->comment('Estado del pago');

            // Follow-up actions required
            $table->json('required_actions')->nullable()->comment('Acciones requeridas del usuario');
            $table->json('next_steps')->nullable()->comment('Próximos pasos sugeridos');
            $table->timestamp('action_deadline')->nullable()->comment('Fecha límite para acciones');
            $table->boolean('urgent_action_required')->default(false)->comment('Acción urgente requerida');

            // Rectification and amendments
            $table->boolean('allows_rectification')->default(true)->comment('Permite rectificación');
            $table->boolean('allows_cancellation')->default(true)->comment('Permite cancelación');
            $table->json('rectifiable_fields')->nullable()->comment('Campos que pueden rectificarse');
            $table->timestamp('rectification_deadline')->nullable()->comment('Fecha límite para rectificar');

            // Additional customs data
            $table->json('customs_metadata')->nullable()->comment('Metadatos adicionales de aduana');
            $table->json('inspection_requirements')->nullable()->comment('Requerimientos de inspección');
            $table->json('special_conditions')->nullable()->comment('Condiciones especiales aplicadas');
            $table->json('exemptions_applied')->nullable()->comment('Exenciones aplicadas');

            // Response processing metadata
            $table->timestamp('processed_at')->useCurrent()->comment('Fecha de procesamiento de la respuesta');
            $table->string('processor_version', 20)->nullable()->comment('Versión del procesador de respuestas');
            $table->json('parsing_errors')->nullable()->comment('Errores durante el parseo');
            $table->json('mapping_warnings')->nullable()->comment('Advertencias de mapeo de campos');

            // Integration with business entities
            $table->json('updated_entities')->nullable()->comment('Entidades actualizadas tras la respuesta');
            $table->json('entity_status_changes')->nullable()->comment('Cambios de estado en entidades');
            $table->boolean('automatic_updates_applied')->default(false)->comment('Actualizaciones automáticas aplicadas');

            // Notification and communication
            $table->boolean('notifications_sent')->default(false)->comment('Notificaciones enviadas');
            $table->json('notification_recipients')->nullable()->comment('Destinatarios de notificaciones');
            $table->timestamp('notifications_sent_at')->nullable()->comment('Fecha de envío de notificaciones');

            // Data retention and archival
            $table->boolean('is_archived')->default(false)->comment('Respuesta archivada');
            $table->timestamp('archive_date')->nullable()->comment('Fecha de archivado');
            $table->enum('retention_level', ['temporary', 'standard', 'extended', 'permanent'])->default('standard')->comment('Nivel de retención');

            // Standard timestamps
            $table->timestamp('created_at')->useCurrent()->comment('Fecha de creación');
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate()->comment('Fecha de actualización');

            // Indexes for performance and business queries
            $table->index(['transaction_id'], 'idx_transaction');
            $table->index(['response_type', 'processing_status'], 'idx_response_processing');
            $table->index(['confirmation_number'], 'idx_confirmation');
            $table->index(['reference_number'], 'idx_reference');
            $table->index(['voyage_number'], 'idx_voyage');
            $table->index(['manifest_number'], 'idx_manifest');
            $table->index(['requires_action', 'urgent_action_required'], 'idx_actions_required');
            $table->index(['customs_processed_at', 'customs_status'], 'idx_customs_timeline');
            $table->index(['action_deadline', 'requires_action'], 'idx_deadline_actions');
            $table->index(['payment_status', 'customs_fees'], 'idx_payment_status');
            $table->index(['documents_due_date', 'documents_approved'], 'idx_documents_status');
            $table->index(['is_archived', 'archive_date'], 'idx_archived');

            // Composite indexes for complex business queries
            $table->index(['processing_status', 'requires_action', 'created_at'], 'idx_status_action_timeline');
            $table->index(['response_type', 'customs_status', 'processed_at'], 'idx_type_status_timeline');

            // Unique constraints
            $table->unique(['transaction_id'], 'uk_transaction_response');

            // Foreign key constraints will be added separately
            // $table->foreign('transaction_id')->references('id')->on('webservice_transactions')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('webservice_responses');
    }
};

