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
     * Tabla principal para gestión de clientes con CUIT/RUC
     * 
     * SIMPLIFICACIÓN APLICADA:
     * - ❌ REMOVIDO: client_roles (según feedback del cliente)
     * - Los clientes son solo empresas propietarias de mercadería a transportar
     * - No requieren roles específicos en el sistema
     * - Los propietarios de barcos están separados en vessel_owners
     */
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            // Primary Key
            $table->id();

            // Identificación del cliente (CUIT/RUC)
            $table->string('tax_id', 11)
                ->comment('CUIT Argentina (11 dígitos) o RUC Paraguay');

            // Referencias a catálogos base (FASE 0)
            $table->foreignId('country_id')
                ->constrained('countries')
                ->comment('País del cliente (Argentina/Paraguay)');

            $table->foreignId('document_type_id')
                ->constrained('document_types')
                ->comment('Tipo de documento según país');

            // Datos oficiales del cliente
            $table->string('legal_name', 255)
                ->comment('Razón social oficial registrada');

            // Nombre comercial opcional
            $table->string('commercial_name', 255)
                ->nullable()
                ->comment('Nombre comercial del cliente');

            // Datos de contacto básicos para webservices
            $table->string('address', 500)
                ->nullable()
                ->comment('Dirección principal - utilizada en webservices');

            $table->string('email', 500)
                ->nullable()
                ->comment('Emails principales separados por punto y coma para cartas de aviso');

            // Referencias opcionales a catálogos operativos
            $table->foreignId('primary_port_id')
                ->nullable()
                ->constrained('ports')
                ->comment('Puerto principal de operaciones');

            $table->foreignId('customs_offices_id')
                ->nullable()
                ->constrained('customs_offices')
                ->comment('Aduana habitual de operaciones');

            // Estado y control
            $table->enum('status', ['active', 'inactive', 'suspended'])
                ->default('active')
                ->comment('Estado operativo del cliente');

            // Auditoría y trazabilidad
            $table->foreignId('created_by_company_id')
                ->constrained('companies')
                ->comment('Empresa que creó el registro');

            $table->timestamp('verified_at')
                ->nullable()
                ->comment('Fecha de verificación del CUIT/RUC');

            // Observaciones
            $table->text('notes')
                ->nullable()
                ->comment('Observaciones internas');

            // Timestamps estándar
            $table->timestamps();

            // ÍNDICES OPTIMIZADOS PARA CONSULTAS FRECUENTES

            // Índice único para evitar duplicados CUIT por país
            $table->unique(['tax_id', 'country_id'], 'uk_clients_tax_country');

            // Índices de búsqueda frecuente
            $table->index(['status', 'verified_at'], 'idx_clients_active_verified');
            $table->index(['country_id', 'status'], 'idx_clients_country_status');
            $table->index(['created_by_company_id', 'status'], 'idx_clients_company_status');

            // Índices para búsquedas por texto
            $table->index('legal_name', 'idx_clients_legal_name');
            $table->index('commercial_name', 'idx_clients_commercial_name');
            $table->index('tax_id', 'idx_clients_tax_id');

            // Índices para relaciones operativas
            $table->index(['primary_port_id', 'status'], 'idx_clients_port_status');
            $table->index(['customs_offices_id', 'status'], 'idx_clients_customs_status');

            // Índices de auditoría
            $table->index('created_at', 'idx_clients_created');
            $table->index('verified_at', 'idx_clients_verified');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};