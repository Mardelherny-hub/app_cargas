<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * CORRECCIÓN 1: PROPIETARIOS DE EMBARCACIONES
     * 
     * Tabla para propietarios de barcos/embarcaciones
     * Separada del modelo Client para cumplir con los requerimientos:
     * - Solo gestionable por superadmin y company-admin
     * - Asociados a empresas específicas 
     * - Campo transportista_type obligatorio para webservices (O/R)
     * 
     * REFERENCIAS:
     * - companies (empresa asociada)
     * - countries (país del propietario)
     * - users (auditoría)
     */
    public function up(): void
    {
        Schema::create('vessel_owners', function (Blueprint $table) {
            // Primary key
            $table->id();

            // Identificación fiscal básica
            $table->string('tax_id', 15)->unique()->comment('CUIT/RUC del propietario');
            $table->string('legal_name', 200)->comment('Razón social oficial');
            $table->string('commercial_name', 200)->nullable()->comment('Nombre comercial');
            
            // Relación con empresa (obligatoria)
            $table->foreignId('company_id')->constrained()->onDelete('restrict')
                  ->comment('Empresa a la que pertenece el propietario');
            
            // País del propietario
            $table->foreignId('country_id')->constrained()->comment('País del propietario');
            
            // Campo crítico para webservices
            $table->enum('transportista_type', ['O', 'R'])
                  ->comment('Tipo de transportista: O=Operador, R=Representante (requerido por webservices)');
            
            // Datos de contacto básicos
            $table->string('email', 100)->nullable()->comment('Email de contacto');
            $table->string('phone', 50)->nullable()->comment('Teléfono de contacto');
            $table->text('address')->nullable()->comment('Dirección fiscal');
            $table->string('city', 100)->nullable()->comment('Ciudad');
            $table->string('postal_code', 20)->nullable()->comment('Código postal');
            
            // Estados y validaciones
            $table->enum('status', ['active', 'inactive', 'suspended', 'pending_verification'])
                  ->default('active')->comment('Estado del propietario');
            
            $table->timestamp('tax_id_verified_at')->nullable()
                  ->comment('Fecha de verificación del CUIT/RUC');
            
            $table->boolean('webservice_authorized')->default(false)
                  ->comment('Autorizado para uso en webservices');
            
            // Configuración específica para webservices
            $table->json('webservice_config')->nullable()
                  ->comment('Configuración específica para webservices (JSON)');
            
            // Observaciones y notas
            $table->text('notes')->nullable()->comment('Observaciones internas');
            
            // Auditoría y control
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')
                  ->comment('Usuario que creó el registro');
            
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')
                  ->comment('Último usuario que modificó el registro');
            
            $table->timestamp('last_activity_at')->nullable()
                  ->comment('Última actividad registrada');
            
            // Timestamps
            $table->timestamps();
            
            // Índices para optimización
            $table->index(['company_id', 'status']);
            $table->index(['country_id', 'transportista_type']);
            $table->index(['status', 'webservice_authorized']);
            $table->index('tax_id_verified_at');
            
            // Índice compuesto para búsquedas frecuentes
            $table->index(['company_id', 'transportista_type', 'status'], 'idx_company_type_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vessel_owners');
    }
};