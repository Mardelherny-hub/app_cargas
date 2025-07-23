<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * MÓDULO 4: WEBSERVICES ADUANA - FASE 1D
     * Tabla webservice_errors - Catálogo de errores conocidos de webservices
     *
     * Esta tabla mantiene un catálogo centralizado de todos los errores conocidos
     * de los webservices aduaneros con sus soluciones, permitiendo diagnóstico
     * automático y asistencia al usuario.
     *
     * CASOS DE USO:
     * - Mapeo automático de códigos de error a soluciones
     * - Clasificación de errores bloqueantes vs recuperables
     * - Estadísticas de frecuencia de errores
     * - Guías de resolución para operadores
     * - Patrones de error para mejora continua
     *
     * ERRORES TÍPICOS IDENTIFICADOS:
     * - Argentina: Códigos SOAP Fault, errores de validación CUIT
     * - Paraguay: Errores GDSF, problemas de certificados
     * - Generales: Timeouts, problemas de conectividad, XML mal formado
     */
    public function up(): void
    {
        Schema::create('webservice_errors', function (Blueprint $table) {
            // Primary key
            $table->id();

            // Error classification
            $table->enum('country', ['AR', 'PY', 'COMMON'])->comment('País del webservice (o común a ambos)');
            $table->enum('webservice_type', [
                'anticipada',        // Información Anticipada Argentina
                'micdta',           // MIC/DTA Argentina
                'desconsolidado',   // Desconsolidados Argentina
                'transbordo',       // Transbordos Argentina/Paraguay
                'manifiesto',       // Manifiestos Paraguay
                'consulta',         // Consultas de estado
                'rectificacion',    // Rectificaciones
                'anulacion',        // Anulaciones
                'authentication',   // Errores de autenticación
                'common'            // Errores comunes a todos
            ])->comment('Tipo de webservice donde ocurre el error');

            // Error identification
            $table->string('error_code', 50)->comment('Código de error del webservice');
            $table->string('error_subcode', 50)->nullable()->comment('Subcódigo o detalle adicional');
            $table->string('soap_fault_code', 50)->nullable()->comment('Código SOAP Fault específico');
            $table->integer('http_status_code')->nullable()->comment('Código HTTP asociado');

            // Error description and context
            $table->string('error_title', 200)->comment('Título corto del error');
            $table->text('error_description')->comment('Descripción completa del error');
            $table->text('technical_details')->nullable()->comment('Detalles técnicos adicionales');
            $table->json('error_patterns')->nullable()->comment('Patrones de texto que identifican este error');

            // Error categorization
            $table->enum('category', [
                'validation',       // Errores de validación de datos
                'authentication',   // Problemas de autenticación/certificados
                'authorization',    // Problemas de permisos
                'business_logic',   // Errores de reglas de negocio
                'data_format',      // Problemas de formato de datos
                'network',          // Problemas de conectividad
                'timeout',          // Timeouts y demoras
                'system',           // Errores del sistema aduanero
                'configuration',    // Problemas de configuración
                'unknown'           // Errores no clasificados
            ])->comment('Categoría del error');

            $table->enum('severity', [
                'low',              // Error menor, no bloquea operación
                'medium',           // Error importante, requiere atención
                'high',             // Error crítico, bloquea operación
                'critical'          // Error que afecta múltiples operaciones
            ])->comment('Severidad del error');

            // Error behavior
            $table->boolean('is_blocking')->default(true)->comment('Error bloquea completamente la operación');
            $table->boolean('allows_retry')->default(true)->comment('Permite reintento automático');
            $table->boolean('requires_manual_intervention')->default(false)->comment('Requiere intervención manual');
            $table->boolean('is_temporary')->default(false)->comment('Error típicamente temporal');

            // Solution and resolution
            $table->text('suggested_solution')->comment('Solución sugerida para el usuario');
            $table->text('technical_solution')->nullable()->comment('Solución técnica detallada');
            $table->json('resolution_steps')->nullable()->comment('Pasos específicos de resolución');
            $table->json('prevention_measures')->nullable()->comment('Medidas para prevenir el error');

            // User guidance
            $table->text('user_message')->nullable()->comment('Mensaje amigable para mostrar al usuario');
            $table->text('operator_notes')->nullable()->comment('Notas específicas para operadores');
            $table->json('related_documentation')->nullable()->comment('Links a documentación relacionada');
            $table->string('knowledge_base_url', 500)->nullable()->comment('URL a base de conocimiento');

            // Error context and conditions
            $table->json('common_causes')->nullable()->comment('Causas comunes de este error');
            $table->json('typical_scenarios')->nullable()->comment('Escenarios típicos donde ocurre');
            $table->json('related_fields')->nullable()->comment('Campos de datos relacionados con el error');
            $table->json('prerequisite_conditions')->nullable()->comment('Condiciones que deben cumplirse');

            // Retry and recovery logic
            $table->integer('max_retry_attempts')->default(3)->comment('Máximo número de reintentos sugeridos');
            $table->integer('retry_delay_seconds')->default(60)->comment('Delay entre reintentos en segundos');
            $table->boolean('exponential_backoff')->default(true)->comment('Usar backoff exponencial');
            $table->json('retry_conditions')->nullable()->comment('Condiciones específicas para reintento');

            // Statistics and monitoring
            $table->integer('frequency_count')->default(0)->comment('Número de veces que ocurrió este error');
            $table->timestamp('first_occurrence')->nullable()->comment('Primera vez que se registró');
            $table->timestamp('last_occurrence')->nullable()->comment('Última vez que ocurrió');
            $table->decimal('resolution_rate_percent', 5, 2)->default(0)->comment('Tasa de resolución exitosa');

            // Related errors and grouping
            $table->string('error_group', 100)->nullable()->comment('Grupo de errores relacionados');
            $table->json('similar_error_codes')->nullable()->comment('Códigos de error similares');
            $table->unsignedBigInteger('parent_error_id')->nullable()->comment('Error padre si es un subtipo');

            // Version and environment
            $table->json('affected_versions')->nullable()->comment('Versiones de webservice afectadas');
            $table->json('environment_specific')->nullable()->comment('Información específica por ambiente');
            $table->boolean('testing_only')->default(false)->comment('Error que solo ocurre en testing');
            $table->boolean('production_only')->default(false)->comment('Error que solo ocurre en producción');

            // Alerting and escalation
            $table->boolean('requires_immediate_alert')->default(false)->comment('Requiere alerta inmediata');
            $table->json('alert_recipients')->nullable()->comment('Destinatarios de alertas');
            $table->integer('alert_threshold')->nullable()->comment('Umbral de occurrencias para alertar');
            $table->enum('escalation_level', ['none', 'team', 'management', 'vendor'])->default('none')->comment('Nivel de escalación');

            // Maintenance and updates
            $table->boolean('is_active')->default(true)->comment('Error activo en el catálogo');
            $table->boolean('is_deprecated')->default(false)->comment('Error deprecado/obsoleto');
            $table->string('deprecated_reason', 500)->nullable()->comment('Razón de deprecación');
            $table->timestamp('last_reviewed_at')->nullable()->comment('Última revisión del error');
            $table->unsignedBigInteger('reviewed_by_user_id')->nullable()->comment('Usuario que revisó');

            // Additional metadata
            $table->json('custom_attributes')->nullable()->comment('Atributos personalizados adicionales');
            $table->json('integration_data')->nullable()->comment('Datos para integraciones externas');
            $table->text('internal_notes')->nullable()->comment('Notas internas del equipo de desarrollo');

            // Standard timestamps
            $table->timestamp('created_at')->useCurrent()->comment('Fecha de creación');
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate()->comment('Fecha de actualización');

            // Indexes for performance and common queries
            $table->index(['country', 'webservice_type'], 'idx_country_webservice');
            $table->index(['error_code', 'country'], 'idx_error_code_country');
            $table->index(['category', 'severity'], 'idx_category_severity');
            $table->index(['is_blocking', 'allows_retry'], 'idx_blocking_retry');
            $table->index(['frequency_count', 'last_occurrence'], 'idx_frequency_recent');
            $table->index(['is_active', 'is_deprecated'], 'idx_active_status');
            $table->index(['error_group'], 'idx_error_group');
            $table->index(['requires_immediate_alert', 'alert_threshold'], 'idx_alert_config');
            $table->index(['soap_fault_code'], 'idx_soap_fault');
            $table->index(['http_status_code'], 'idx_http_status');

            // Composite indexes for complex queries
            $table->index(['country', 'webservice_type', 'category'], 'idx_full_classification');
            $table->index(['severity', 'frequency_count', 'last_occurrence'], 'idx_priority_analysis');
            $table->index(['is_active', 'country', 'error_code'], 'idx_active_lookup');

            // Unique constraints for error identification
            $table->unique(['country', 'webservice_type', 'error_code', 'error_subcode'], 'uk_error_identification');

            // Foreign key constraints will be added separately
            // $table->foreign('parent_error_id')->references('id')->on('webservice_errors')->onDelete('set null');
            // $table->foreign('reviewed_by_user_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('webservice_errors');
    }
};
