<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * TABLA: client_contact_data
     * PROPÓSITO: Almacenar información de contacto detallada de clientes
     * DISEÑO: Tabla separada para mantener clients como core inmutable
     * COMPATIBILIDAD: Argentina y Paraguay - webservices AR/PY
     */
    public function up(): void
    {
        Schema::create('client_contact_data', function (Blueprint $table) {
            // Primary Key
            $table->id();

            // Relación con cliente (obligatoria)
            $table->foreignId('client_id')
                ->constrained('clients')
                ->onDelete('cascade')
                ->comment('Cliente al que pertenece esta información de contacto');

            // ============================================
            // INFORMACIÓN DE CONTACTO PRINCIPAL
            // ============================================
            
            // Emails
            $table->string('email', 255)
                ->nullable()
                ->comment('Email principal del cliente');
                
            $table->string('secondary_email', 255)
                ->nullable()
                ->comment('Email secundario o alternativo');

            // Teléfonos
            $table->string('phone', 20)
                ->nullable()
                ->comment('Teléfono fijo principal');
                
            $table->string('mobile_phone', 20)
                ->nullable()
                ->comment('Teléfono móvil/celular');
                
            $table->string('fax', 20)
                ->nullable()
                ->comment('Número de fax');

            // ============================================
            // DIRECCIÓN FÍSICA COMPLETA
            // ============================================
            
            $table->string('address_line_1', 255)
                ->nullable()
                ->comment('Dirección principal (calle y número)');
                
            $table->string('address_line_2', 255)
                ->nullable()
                ->comment('Dirección complementaria (piso, depto, entre calles)');
                
            $table->string('city', 100)
                ->nullable()
                ->comment('Ciudad');
                
            $table->string('state_province', 100)
                ->nullable()
                ->comment('Provincia/Estado/Departamento');
                
            $table->string('postal_code', 20)
                ->nullable()
                ->comment('Código postal');

            // Coordenadas (opcional para futuras funcionalidades)
            $table->decimal('latitude', 10, 8)
                ->nullable()
                ->comment('Latitud para geolocalización');
                
            $table->decimal('longitude', 11, 8)
                ->nullable()
                ->comment('Longitud para geolocalización');

            // ============================================
            // PERSONA DE CONTACTO
            // ============================================
            
            $table->string('contact_person_name', 255)
                ->nullable()
                ->comment('Nombre del contacto principal');
                
            $table->string('contact_person_position', 100)
                ->nullable()
                ->comment('Cargo del contacto principal');
                
            $table->string('contact_person_phone', 20)
                ->nullable()
                ->comment('Teléfono directo del contacto');
                
            $table->string('contact_person_email', 255)
                ->nullable()
                ->comment('Email directo del contacto');

            // ============================================
            // HORARIOS Y CONFIGURACIÓN
            // ============================================
            
            $table->json('business_hours')
                ->nullable()
                ->comment('Horarios de atención (JSON: días y horarios)');
                
            $table->string('timezone', 50)
                ->nullable()
                ->default('America/Argentina/Buenos_Aires')
                ->comment('Zona horaria del cliente');

            // ============================================
            // PREFERENCIAS DE COMUNICACIÓN
            // ============================================
            
            $table->json('communication_preferences')
                ->nullable()
                ->comment('Preferencias de comunicación (JSON: email, sms, llamadas)');
                
            $table->boolean('accepts_email_notifications')
                ->default(true)
                ->comment('Acepta notificaciones por email');
                
            $table->boolean('accepts_sms_notifications')
                ->default(false)
                ->comment('Acepta notificaciones por SMS');

            // ============================================
            // OBSERVACIONES Y NOTAS
            // ============================================
            
            $table->text('notes')
                ->nullable()
                ->comment('Observaciones adicionales de contacto');
                
            $table->text('internal_notes')
                ->nullable()
                ->comment('Notas internas no visibles para el cliente');

            // ============================================
            // ESTADO Y AUDITORÍA
            // ============================================
            
            $table->boolean('active')
                ->default(true)
                ->comment('Estado activo de la información de contacto');
                
            $table->boolean('is_primary')
                ->default(false)
                ->comment('Es la información de contacto principal del cliente');
                
            $table->boolean('verified')
                ->default(false)
                ->comment('Información verificada');
                
            $table->timestamp('verified_at')
                ->nullable()
                ->comment('Fecha de verificación de la información');

            // Auditoría de creación y modificación
            $table->foreignId('created_by_user_id')
                ->nullable()
                ->constrained('users')
                ->onDelete('set null')
                ->comment('Usuario que creó el registro');
                
            $table->foreignId('updated_by_user_id')
                ->nullable()
                ->constrained('users')
                ->onDelete('set null')
                ->comment('Usuario que actualizó por última vez');

            // Timestamps estándar
            $table->timestamps();

            // ============================================
            // ÍNDICES PARA OPTIMIZACIÓN DE CONSULTAS
            // ============================================

            // Índice principal para consultas por cliente
            $table->index(['client_id', 'active'], 'idx_client_active');
            
            // Índice para contacto principal
            $table->index(['client_id', 'is_primary', 'active'], 'idx_client_primary_contact');
            
            // Índices para búsquedas por email
            $table->index('email', 'idx_email_search');
            $table->index(['email', 'active'], 'idx_email_active');
            
            // Índices para búsquedas por teléfono
            $table->index('phone', 'idx_phone_search');
            $table->index('mobile_phone', 'idx_mobile_search');
            
            // Índices para búsquedas por ubicación
            $table->index(['city', 'state_province'], 'idx_location');
            $table->index(['postal_code', 'active'], 'idx_postal_active');
            
            // Índice para persona de contacto
            $table->index('contact_person_name', 'idx_contact_person');
            
            // Índices para verificación y auditoría
            $table->index(['verified', 'active'], 'idx_verified_active');
            $table->index(['created_at', 'client_id'], 'idx_created_audit');
            $table->index(['updated_at', 'client_id'], 'idx_updated_audit');

            // Índice compuesto para notificaciones
            $table->index(['active', 'accepts_email_notifications'], 'idx_email_notifications');
            $table->index(['active', 'accepts_sms_notifications'], 'idx_sms_notifications');

            // Índice único para evitar múltiples contactos primarios por cliente
            // Cambiado a índice parcial para que solo aplique cuando is_primary = 1
            // Laravel no soporta índices parciales nativamente, se usa raw statement después de crear la tabla
        });

        // Se elimina el índice único para is_primary porque MySQL no soporta índices parciales
        // La unicidad del contacto primario se maneja en la lógica de la aplicación (modelo)

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('client_contact_data');
    }
};