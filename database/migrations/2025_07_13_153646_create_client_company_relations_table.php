<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * FASE 1 - MÓDULO EMPRESAS Y CLIENTES
     * Tabla de relaciones M:N entre clientes y empresas
     * Permite gestión flexible de acceso y permisos por empresa
     */
    public function up(): void
    {
        Schema::create('client_company_relations', function (Blueprint $table) {
            // Primary Key
            $table->id();

            // Referencias principales
            $table->foreignId('client_id')
                ->constrained('clients')
                ->onDelete('cascade')
                ->comment('Cliente relacionado');

            $table->foreignId('company_id')
                ->constrained('companies')
                ->onDelete('cascade')
                ->comment('Empresa relacionada');

            // Tipo de relación comercial
            $table->enum('relation_type', [
                'customer',  // Cliente es cliente de la empresa
                'provider',  // Cliente es proveedor de la empresa
                'both'       // Relación bidireccional
            ])->default('customer')
            ->comment('Tipo de relación comercial');

            // Permisos de gestión
            $table->boolean('can_edit')
                ->default(true)
                ->comment('Empresa puede editar datos del cliente');

            // Estado de la relación
            $table->boolean('active')
                ->default(true)
                ->comment('Relación activa/inactiva');

            // Información adicional de la relación
            $table->decimal('credit_limit', 15, 2)
                ->nullable()
                ->comment('Límite de crédito asignado');

            $table->string('internal_code', 50)
                ->nullable()
                ->comment('Código interno de la empresa para el cliente');

            $table->enum('priority', ['low', 'normal', 'high', 'critical'])
                ->default('normal')
                ->comment('Prioridad del cliente para la empresa');

            // Configuración específica por relación
            $table->json('relation_config')
                ->nullable()
                ->comment('Configuración específica de la relación (JSON)');

            // Auditoría
            $table->foreignId('created_by_user_id')
                ->nullable()
                ->constrained('users')
                ->onDelete('set null')
                ->comment('Usuario que creó la relación');

            $table->timestamp('last_activity_at')
                ->nullable()
                ->comment('Última actividad en la relación');

            // Timestamps estándar
            $table->timestamps();

            // ÍNDICES OPTIMIZADOS PARA CONSULTAS FRECUENTES

            // Índice único para evitar relaciones duplicadas
            $table->unique(['client_id', 'company_id'], 'unique_client_company');

            // Índice para consultas por empresa
            $table->index(['company_id', 'active'], 'idx_company_active');

            // Índice para consultas por cliente
            $table->index(['client_id', 'active'], 'idx_client_active');

            // Índice para consultas por tipo de relación
            $table->index(['relation_type', 'active'], 'idx_relation_type_active');

            // Índice compuesto para permisos de edición
            $table->index(['company_id', 'can_edit', 'active'], 'idx_company_edit_permissions');

            // Índice para búsquedas por código interno
            $table->index(['company_id', 'internal_code'], 'idx_company_internal_code');

            // Índice para consultas por prioridad
            $table->index(['company_id', 'priority', 'active'], 'idx_company_priority');

            // Índice para actividad reciente
            $table->index(['last_activity_at', 'active'], 'idx_recent_activity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('client_company_relations');
    }
};
