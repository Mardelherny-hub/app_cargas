<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * MÓDULO 4: WEBSERVICES ADUANA - FASE 1B
     * Tabla webservice_logs - Logs detallados de transacciones webservice
     *
     * Esta tabla registra todos los eventos y logs durante el ciclo de vida de
     * las transacciones webservice para debugging, monitoreo y auditoría.
     *
     * REFERENCIAS CONFIRMADAS DEL SISTEMA:
     * - webservice_transactions (obligatoria, recién creada)
     * - users (opcional, para logs de acciones de usuario)
     *
     * CASOS DE USO:
     * - Debugging de errores SOAP/XML
     * - Monitoreo de performance y tiempos
     * - Auditoría de acciones de usuario
     * - Alertas automáticas por patrones de error
     * - Análisis de tendencias y estadísticas
     */
    public function up(): void
    {
        Schema::create('webservice_logs', function (Blueprint $table) {
            // Primary key
            $table->id();

            // Foreign keys to related tables (defined manually)
            $table->unsignedBigInteger('transaction_id')->comment('Transacción webservice relacionada');
            $table->unsignedBigInteger('user_id')->nullable()->comment('Usuario relacionado (si aplica)');

            // Log classification
            $table->enum('level', [
                'debug',        // Información de debugging detallada
                'info',         // Información general del proceso
                'notice',       // Eventos importantes pero normales
                'warning',      // Advertencias que no impiden el proceso
                'error',        // Errores que afectan la transacción
                'critical',     // Errores críticos del sistema
                'alert',        // Situaciones que requieren atención inmediata
                'emergency'     // Sistema no operativo
            ])->comment('Nivel de severidad del log');

            $table->string('category', 50)->comment('Categoría del log');
            $table->string('subcategory', 50)->nullable()->comment('Subcategoría específica');

            // Log content
            $table->text('message')->comment('Mensaje principal del log');
            $table->text('detailed_message')->nullable()->comment('Mensaje detallado adicional');
            $table->json('context')->nullable()->comment('Contexto adicional en formato JSON');

            // Process tracking
            $table->string('process_step', 100)->nullable()->comment('Paso del proceso (validation, soap_call, parsing, etc.)');
            $table->string('component', 100)->nullable()->comment('Componente del sistema que generó el log');
            $table->string('method', 100)->nullable()->comment('Método o función específica');

            // Technical details
            $table->string('soap_fault_code', 50)->nullable()->comment('Código de fault SOAP');
            $table->text('soap_fault_string')->nullable()->comment('Descripción del fault SOAP');
            $table->integer('http_status_code')->nullable()->comment('Código de respuesta HTTP');
            $table->text('curl_error')->nullable()->comment('Error específico de cURL');

            // Performance metrics
            $table->integer('execution_time_ms')->nullable()->comment('Tiempo de ejecución en milisegundos');
            $table->integer('memory_usage_bytes')->nullable()->comment('Uso de memoria en bytes');
            $table->decimal('cpu_usage_percent', 5, 2)->nullable()->comment('Porcentaje de CPU utilizado');

            // Request/Response details
            $table->text('request_snippet')->nullable()->comment('Fragmento de la solicitud para debugging');
            $table->text('response_snippet')->nullable()->comment('Fragmento de la respuesta para debugging');
            $table->integer('request_size_bytes')->nullable()->comment('Tamaño de la solicitud en bytes');
            $table->integer('response_size_bytes')->nullable()->comment('Tamaño de la respuesta en bytes');

            // Network and connection
            $table->string('server_ip', 45)->nullable()->comment('IP del servidor webservice');
            $table->integer('connection_time_ms')->nullable()->comment('Tiempo de conexión en milisegundos');
            $table->integer('dns_resolution_time_ms')->nullable()->comment('Tiempo de resolución DNS en milisegundos');
            $table->boolean('ssl_verified')->nullable()->comment('Certificado SSL verificado correctamente');

            // Business context
            $table->string('business_reference', 100)->nullable()->comment('Referencia de negocio (BL, contenedor, etc.)');
            $table->string('webservice_operation', 100)->nullable()->comment('Operación del webservice (RegistrarMicDta, etc.)');
            $table->json('affected_entities')->nullable()->comment('Entidades afectadas (IDs de shipments, containers, etc.)');

            // Error correlation
            $table->string('error_group_id', 100)->nullable()->comment('ID para agrupar errores relacionados');
            $table->boolean('is_recurring_error')->default(false)->comment('Error recurrente identificado');
            $table->integer('similar_errors_count')->default(0)->comment('Cantidad de errores similares');

            // Environment and configuration
            $table->enum('environment', ['testing', 'production'])->comment('Ambiente donde ocurrió');
            $table->string('server_hostname', 100)->nullable()->comment('Nombre del servidor de aplicación');
            $table->string('application_version', 20)->nullable()->comment('Versión de la aplicación');

            // Alert and notification
            $table->boolean('requires_alert')->default(false)->comment('Requiere generar alerta');
            $table->boolean('alert_sent')->default(false)->comment('Alerta enviada');
            $table->timestamp('alert_sent_at')->nullable()->comment('Fecha de envío de alerta');
            $table->json('alert_recipients')->nullable()->comment('Destinatarios de la alerta');

            // Data retention and archival
            $table->boolean('is_archived')->default(false)->comment('Log archivado');
            $table->timestamp('archive_date')->nullable()->comment('Fecha de archivado');
            $table->enum('retention_policy', ['short', 'medium', 'long', 'permanent'])->default('medium')->comment('Política de retención');

            // Additional metadata
            $table->json('custom_fields')->nullable()->comment('Campos personalizados adicionales');
            $table->string('correlation_id', 100)->nullable()->comment('ID de correlación para tracking distribuido');
            $table->json('tags')->nullable()->comment('Tags para filtrado y búsqueda');

            // Timestamp with high precision for ordering
            $table->timestamp('created_at', 6)->useCurrent()->comment('Fecha de creación con microsegundos');

            // Indexes for performance and common queries
            $table->index(['transaction_id', 'level'], 'idx_transaction_level');
            $table->index(['level', 'created_at'], 'idx_level_created');
            $table->index(['category', 'subcategory'], 'idx_category_subcategory');
            $table->index(['process_step', 'level'], 'idx_process_level');
            $table->index(['environment', 'created_at'], 'idx_environment_created');
            $table->index(['requires_alert', 'alert_sent'], 'idx_alert_status');
            $table->index(['error_group_id'], 'idx_error_group');
            $table->index(['is_recurring_error', 'created_at'], 'idx_recurring_created');
            $table->index(['correlation_id'], 'idx_correlation');
            $table->index(['is_archived', 'archive_date'], 'idx_archived');

            // Composite indexes for complex queries
            $table->index(['transaction_id', 'created_at', 'level'], 'idx_transaction_timeline');
            $table->index(['category', 'level', 'created_at'], 'idx_category_timeline');

            // Foreign key constraints will be added separately
            $table->foreign('transaction_id')->references('id')->on('webservice_transactions')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('webservice_logs');
    }
};
