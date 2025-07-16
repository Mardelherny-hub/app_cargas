<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * MÓDULO 3: VIAJES Y CARGAS - captains (CORREGIDA)
     * Tabla para capitanes de embarcaciones
     * Gestiona datos personales, licencias, certificaciones y competencias
     * 
     * FIX: Nombres explícitos de foreign key constraints para evitar duplicados
     */
    public function up(): void
    {
        Schema::create('captains', function (Blueprint $table) {
            // Primary key
            $table->id();

            // Basic personal information
            $table->string('first_name', 100)->comment('Nombre(s)');
            $table->string('last_name', 100)->comment('Apellido(s)');
            $table->string('full_name', 255)->comment('Nombre completo');
            $table->date('birth_date')->nullable()->comment('Fecha de nacimiento');
            $table->enum('gender', ['male', 'female', 'other', 'not_specified'])->nullable()->comment('Género');
            $table->string('nationality', 3)->nullable()->comment('Nacionalidad (código país)');
            $table->string('blood_type', 5)->nullable()->comment('Tipo de sangre');

            // Contact information
            $table->string('email', 255)->nullable()->comment('Email de contacto');
            $table->string('phone', 30)->nullable()->comment('Teléfono principal');
            $table->string('mobile_phone', 30)->nullable()->comment('Teléfono móvil');
            $table->string('emergency_contact_name', 100)->nullable()->comment('Contacto de emergencia');
            $table->string('emergency_contact_phone', 30)->nullable()->comment('Teléfono de emergencia');
            $table->string('emergency_contact_relationship', 50)->nullable()->comment('Relación contacto emergencia');

            // Address information
            $table->text('address')->nullable()->comment('Dirección completa');
            $table->string('city', 100)->nullable()->comment('Ciudad');
            $table->string('state_province', 100)->nullable()->comment('Estado/Provincia');
            $table->string('postal_code', 20)->nullable()->comment('Código postal');
            
            // Foreign keys (definidos SIN auto-constraint para controlar nombres)
            $table->unsignedBigInteger('country_id')->nullable()->comment('País de residencia');
            $table->unsignedBigInteger('license_country_id')->nullable()->comment('País emisor de licencia');
            $table->unsignedBigInteger('primary_company_id')->nullable()->comment('Empresa principal');

            // Document identification
            $table->string('document_type', 20)->nullable()->comment('Tipo de documento (DNI, passport, etc.)');
            $table->string('document_number', 50)->nullable()->comment('Número de documento');
            $table->date('document_expires')->nullable()->comment('Vencimiento documento');

            // License information
            $table->string('license_number', 100)->unique()->comment('Número de licencia náutica');
            $table->enum('license_class', [
                'recreational',     // Recreativa
                'commercial',       // Comercial
                'master',          // Patrón
                'captain',         // Capitán
                'pilot'            // Práctico
            ])->default('commercial')->comment('Clase de licencia');
            
            $table->enum('license_status', [
                'active',          // Activa
                'expired',         // Vencida
                'suspended',       // Suspendida
                'revoked',         // Revocada
                'pending_renewal'  // Pendiente renovación
            ])->default('active')->comment('Estado de la licencia');

            $table->date('license_issued_at')->nullable()->comment('Fecha emisión licencia');
            $table->date('license_expires_at')->nullable()->comment('Fecha vencimiento licencia');

            // Medical and safety certifications
            $table->string('medical_certificate_number', 100)->nullable()->comment('Número certificado médico');
            $table->date('medical_certificate_expires_at')->nullable()->comment('Vencimiento certificado médico');
            $table->string('safety_training_certificate', 100)->nullable()->comment('Certificado entrenamiento seguridad');
            $table->date('safety_training_expires_at')->nullable()->comment('Vencimiento entrenamiento seguridad');

            // Professional experience
            $table->integer('years_of_experience')->default(0)->comment('Años de experiencia');
            $table->date('first_voyage_date')->nullable()->comment('Fecha primer viaje');
            $table->date('last_voyage_date')->nullable()->comment('Fecha último viaje');
            $table->integer('total_voyages_completed')->default(0)->comment('Total viajes completados');

            // Current employment status
            $table->enum('employment_status', [
                'employed',        // Empleado
                'freelance',       // Freelance
                'unemployed',      // Desempleado
                'retired'          // Retirado
            ])->default('employed')->comment('Estado laboral');

            $table->boolean('available_for_hire')->default(true)->comment('Disponible para contratación');
            $table->decimal('daily_rate', 8, 2)->nullable()->comment('Tarifa diaria');
            $table->string('rate_currency', 3)->default('USD')->comment('Moneda de la tarifa');

            // Performance and ratings
            $table->decimal('performance_rating', 3, 2)->nullable()->comment('Calificación desempeño (1-5)');
            $table->integer('safety_incidents')->default(0)->comment('Incidentes de seguridad');
            $table->text('performance_notes')->nullable()->comment('Notas de desempeño');

            // Competencies and restrictions
            $table->json('vessel_type_competencies')->nullable()->comment('Competencias por tipo de embarcación');
            $table->json('cargo_type_competencies')->nullable()->comment('Competencias por tipo de carga');
            $table->json('route_restrictions')->nullable()->comment('Restricciones de rutas');
            $table->json('additional_certifications')->nullable()->comment('Certificaciones adicionales');

            // Status flags
            $table->boolean('active')->default(true)->comment('Capitán activo');
            $table->boolean('verified')->default(false)->comment('Datos verificados');
            $table->text('verification_notes')->nullable()->comment('Notas de verificación');

            // Audit trail
            $table->timestamp('created_date')->useCurrent()->comment('Fecha creación');
            $table->unsignedBigInteger('created_by_user_id')->nullable()->comment('Usuario creador');
            $table->timestamp('last_updated_date')->useCurrent()->useCurrentOnUpdate()->comment('Última actualización');
            $table->unsignedBigInteger('last_updated_by_user_id')->nullable()->comment('Último usuario que actualizó');
            $table->timestamps();

            // Performance indexes
            $table->index(['active', 'available_for_hire'], 'idx_captains_active_available');
            $table->index(['license_status', 'active'], 'idx_captains_license_status');
            $table->index(['license_class', 'active'], 'idx_captains_license_class');
            $table->index(['employment_status', 'active'], 'idx_captains_employment');
            $table->index(['primary_company_id', 'active'], 'idx_captains_company');
            $table->index(['country_id'], 'idx_captains_country');
            $table->index(['license_country_id'], 'idx_captains_license_country');
            $table->index(['license_expires_at'], 'idx_captains_license_expiry');
            $table->index(['medical_certificate_expires_at'], 'idx_captains_medical_expiry');
            $table->index(['safety_training_expires_at'], 'idx_captains_safety_expiry');
            $table->index(['last_voyage_date'], 'idx_captains_last_voyage');
            $table->index(['performance_rating'], 'idx_captains_performance');
            $table->index(['years_of_experience'], 'idx_captains_experience');

            // Unique constraints
            $table->unique(['license_number'], 'uk_captains_license');
            $table->unique(['document_type', 'document_number'], 'uk_captains_document');

            // Foreign key constraints CON NOMBRES EXPLÍCITOS para evitar duplicados
            $table->foreign('country_id', 'fk_captains_residence_country')
                  ->references('id')->on('countries')->onDelete('set null');
                  
            $table->foreign('license_country_id', 'fk_captains_license_country')
                  ->references('id')->on('countries')->onDelete('set null');
                  
            $table->foreign('primary_company_id', 'fk_captains_primary_company')
                  ->references('id')->on('companies')->onDelete('set null');

            // Audit FKs commented until user system is fully confirmed
            // $table->foreign('created_by_user_id')->references('id')->on('users')->onDelete('set null');
            // $table->foreign('last_updated_by_user_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('captains');
    }
};