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
     * Resuelve la problemática de registro simple y datos variables
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

            // CORRECCIÓN: Roles múltiples del cliente en operaciones (JSON)
            $table->json('client_roles')
                ->comment('Array de roles del cliente: [shipper, consignee, notify_party]');

            // Datos oficiales del cliente
            $table->string('legal_name', 255)
                ->comment('Razón social oficial registrada');

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
            $table->unique(['tax_id', 'country_id'], 'unique_tax_id_per_country');

            // Índices compuestos para consultas por empresa
            $table->index(['created_by_company_id', 'status'], 'idx_company_status');

            // Índices para ubicación y operaciones
            $table->index(['primary_port_id', 'status'], 'idx_port_status');
            $table->index(['customs_offices_id', 'status'], 'idx_customs_status');
            $table->index(['country_id', 'status'], 'idx_country_status');

            // Índices para búsquedas rápidas por CUIT
            $table->index('tax_id', 'idx_tax_id_search');
            $table->index(['tax_id', 'status'], 'idx_tax_id_status');

            // Índices para verificación y auditoría
            $table->index(['verified_at', 'status'], 'idx_verified_status');
            $table->index(['created_at', 'created_by_company_id'], 'idx_created_audit');

            // Índice compuesto para webservices (clientes operativos)
            $table->index(['status', 'verified_at', 'country_id'], 'idx_webservice_ready');

            // Índice para búsquedas por nombre (útil para autocompletado)
            $table->index('legal_name', 'idx_legal_name_search');
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