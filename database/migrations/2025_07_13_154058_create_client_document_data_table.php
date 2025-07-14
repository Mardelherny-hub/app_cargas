<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * FASE 2 - SISTEMA DE DATOS VARIABLES
     * Tabla para datos contextuales de clientes según documento/viaje
     * Resuelve el problema de "datos oficiales vs datos de impresión"
     *
     * REQUERIMIENTO ESPECÍFICO:
     * "para imprimir los conocimientos se requiere que los datos sean la copia fiel
     * del conocimiento oceánico original, por eso a veces en la base de datos figure
     * por ej un cliente que tiene cuit y la razón social es ALUAR S.A. con la
     * dirección San José 1234 pero en el conocimiento original se requiere que diga
     * ALUAR SOCIEDAD ANONIMA y en la dirección Salta 4532"
     */
    public function up(): void
    {
        Schema::create('client_document_data', function (Blueprint $table) {
            // Primary Key
            $table->id();

            // Referencia al cliente base
            $table->foreignId('client_id')
                ->constrained('clients')
                ->onDelete('cascade')
                ->comment('Cliente al que pertenecen estos datos variables');

            // Contexto de aplicación de los datos
            $table->enum('context_type', [
                'bill_of_lading',  // Conocimiento de embarque específico
                'manifest',        // Manifiesto específico
                'voyage',          // Viaje específico
                'default'          // Datos por defecto para el cliente
            ])->comment('Tipo de contexto donde aplicar estos datos');

            // ID del documento/viaje específico (nullable para contexto default)
            $table->unsignedBigInteger('context_id')
                ->nullable()
                ->comment('ID del documento/viaje específico (NULL para default)');

            // Datos variables para impresión
            $table->string('display_name', 255)
                ->comment('Nombre/razón social para mostrar en documento');

            $table->text('display_address')
                ->nullable()
                ->comment('Dirección completa para mostrar en documento');

            $table->string('display_city', 100)
                ->nullable()
                ->comment('Ciudad para mostrar en documento');

            $table->string('display_postal_code', 20)
                ->nullable()
                ->comment('Código postal para mostrar en documento');

            $table->string('display_phone', 50)
                ->nullable()
                ->comment('Teléfono para mostrar en documento');

            $table->string('display_email', 150)
                ->nullable()
                ->comment('Email para mostrar en documento');

            // Datos adicionales flexibles (JSON)
            $table->json('additional_data')
                ->nullable()
                ->comment('Datos adicionales específicos del contexto (JSON)');

            // Control de versiones por defecto
            $table->boolean('is_default')
                ->default(false)
                ->comment('Indica si son los datos por defecto para el cliente');

            // Prioridad en caso de múltiples registros para mismo contexto
            $table->integer('priority')
                ->default(1)
                ->comment('Prioridad de aplicación (1 = más alta)');

            // Control de vigencia temporal
            $table->timestamp('valid_from')
                ->nullable()
                ->comment('Fecha desde cuándo son válidos estos datos');

            $table->timestamp('valid_until')
                ->nullable()
                ->comment('Fecha hasta cuándo son válidos estos datos');

            // Auditoría
            $table->foreignId('created_by_user_id')
                ->constrained('users')
                ->onDelete('restrict')
                ->comment('Usuario que creó estos datos variables');

            $table->foreignId('company_id')
                ->constrained('companies')
                ->onDelete('cascade')
                ->comment('Empresa que gestiona estos datos');

            // Observaciones sobre los datos variables
            $table->text('notes')
                ->nullable()
                ->comment('Observaciones sobre por qué se usan estos datos específicos');

            // Timestamps estándar
            $table->timestamps();

            // ÍNDICES OPTIMIZADOS PARA CONSULTAS FRECUENTES

            // Índice único para evitar duplicados por contexto específico
            $table->unique([
                'client_id',
                'context_type',
                'context_id',
                'company_id'
            ], 'unique_client_context');

            // Índice para consultas por cliente y tipo de contexto
            $table->index(['client_id', 'context_type'], 'idx_client_context_type');

            // Índice para datos por defecto
            $table->index(['client_id', 'is_default', 'company_id'], 'idx_client_default_data');

            // Índice para consultas por empresa
            $table->index(['company_id', 'context_type'], 'idx_company_context');

            // Índice para búsquedas por contexto específico
            $table->index(['context_type', 'context_id'], 'idx_context_search');

            // Índice para datos vigentes
            $table->index(['valid_from', 'valid_until'], 'idx_validity_period');

            // Índice compuesto para consultas de aplicación
            $table->index([
                'client_id',
                'context_type',
                'context_id',
                'priority'
            ], 'idx_client_context_priority');

            // Índice para auditoría por usuario
            $table->index(['created_by_user_id', 'created_at'], 'idx_audit_by_user');

            // Índice para búsquedas por nombre display
            $table->index('display_name', 'idx_display_name_search');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('client_document_data');
    }

};
