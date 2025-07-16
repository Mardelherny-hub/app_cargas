<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * MÓDULO 3: VIAJES Y CARGAS
     * Tabla para capitanes de embarcaciones
     * Gestiona datos personales, licencias, certificaciones y competencias
     * 
     * CARACTERÍSTICAS:
     * - Datos personales y contacto
     * - Licencias y certificaciones por país
     * - Competencias técnicas y restricciones
     * - Historial de experiencia y evaluaciones
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
            $table->foreignId('country_id')->nullable()->constrained('countries')->comment('País de residencia');

            // Identity documents
            $table->string('document_type', 20)->nullable()->comment('Tipo de documento principal');
            $table->string('document_number', 50)->nullable()->comment('Número de documento');
            $table->date('document_expires_at')->nullable()->comment('Vencimiento documento');
            $table->string('passport_number', 50)->nullable()->comment('Número de pasaporte');
            $table->date('passport_expires_at')->nullable()->comment('Vencimiento pasaporte');
            $table->string('visa_number', 50)->nullable()->comment('Número de visa');
            $table->date('visa_expires_at')->nullable()->comment('Vencimiento visa');

            // Professional identification
            $table->string('license_number', 50)->unique()->comment('Número de licencia de capitán');
            $table->string('license_type', 100)->nullable()->comment('Tipo de licencia');
            $table->string('license_issuing_authority', 100)->nullable()->comment('Autoridad emisora de licencia');
            $table->foreignId('license_country_id')->nullable()->constrained('countries')->comment('País emisor de licencia');
            $table->date('license_issued_at')->nullable()->comment('Fecha emisión licencia');
            $table->date('license_expires_at')->nullable()->comment('Fecha vencimiento licencia');
            $table->enum('license_status', ['active', 'suspended', 'revoked', 'expired'])->default('active')->comment('Estado de la licencia');

            // Additional certifications
            $table->json('certifications')->nullable()->comment('Certificaciones adicionales (JSON array)');
            $table->json('endorsements')->nullable()->comment('Endosos especiales (JSON array)');
            $table->json('training_certificates')->nullable()->comment('Certificados de entrenamiento (JSON array)');

            // Professional competencies
            $table->enum('license_class', [
                'river_pilot',          // Piloto fluvial
                'maritime_captain',     // Capitán marítimo
                'inland_captain',       // Capitán fluvial
                'tugboat_captain',      // Capitán de remolcador
                'barge_captain',        // Capitán de barcaza
                'mixed_license'         // Licencia mixta
            ])->comment('Clase de licencia');

            $table->boolean('can_navigate_rivers')->default(true)->comment('Puede navegar ríos');
            $table->boolean('can_navigate_maritime')->default(false)->comment('Puede navegar marítimo');
            $table->boolean('can_navigate_coastal')->default(false)->comment('Puede navegar costero');
            $table->boolean('can_operate_tugboats')->default(false)->comment('Puede operar remolcadores');
            $table->boolean('can_operate_barges')->default(true)->comment('Puede operar barcazas');
            $table->boolean('can_handle_dangerous_cargo')->default(false)->comment('Puede manejar carga peligrosa');
            $table->boolean('can_handle_passengers')->default(false)->comment('Puede transportar pasajeros');

            // Geographic restrictions and authorizations
            $table->json('authorized_waterways')->nullable()->comment('Vías navegables autorizadas');
            $table->json('authorized_ports')->nullable()->comment('Puertos autorizados');
            $table->json('restricted_areas')->nullable()->comment('Áreas restringidas');
            $table->json('route_certifications')->nullable()->comment('Certificaciones de ruta específicas');

            // Vessel type restrictions
            $table->decimal('max_vessel_length', 8, 2)->nullable()->comment('Longitud máxima de embarcación en metros');
            $table->decimal('max_vessel_beam', 8, 2)->nullable()->comment('Manga máxima de embarcación en metros');
            $table->decimal('max_vessel_draft', 8, 2)->nullable()->comment('Calado máximo de embarcación en metros');
            $table->decimal('max_gross_tonnage', 10, 2)->nullable()->comment('Tonelaje bruto máximo');
            $table->integer('max_horsepower')->nullable()->comment('Potencia máxima en HP');
            $table->json('authorized_vessel_types')->nullable()->comment('Tipos de embarcación autorizados');

            // Experience and qualifications
            $table->integer('years_of_experience')->default(0)->comment('Años de experiencia');
            $table->integer('total_voyages')->default(0)->comment('Total de viajes realizados');
            $table->decimal('total_nautical_miles', 12, 2)->default(0)->comment('Millas náuticas totales');
            $table->date('first_license_date')->nullable()->comment('Fecha primera licencia');
            $table->date('last_voyage_date')->nullable()->comment('Fecha último viaje');
            $table->integer('voyages_last_year')->default(0)->comment('Viajes último año');

            // Health and fitness
            $table->date('medical_certificate_expires_at')->nullable()->comment('Vencimiento certificado médico');
            $table->boolean('has_medical_restrictions')->default(false)->comment('Tiene restricciones médicas');
            $table->text('medical_restrictions')->nullable()->comment('Descripción restricciones médicas');
            $table->date('last_medical_exam')->nullable()->comment('Último examen médico');
            $table->boolean('requires_glasses')->default(false)->comment('Requiere anteojos');
            $table->boolean('color_blind')->default(false)->comment('Daltónico');

            // Safety and compliance
            $table->date('safety_training_expires_at')->nullable()->comment('Vencimiento entrenamiento seguridad');
            $table->date('dangerous_goods_training_expires_at')->nullable()->comment('Vencimiento entrenamiento mercancías peligrosas');
            $table->integer('safety_violations')->default(0)->comment('Violaciones de seguridad');
            $table->integer('accidents_reported')->default(0)->comment('Accidentes reportados');
            $table->date('last_safety_incident')->nullable()->comment('Último incidente de seguridad');
            $table->json('safety_record')->nullable()->comment('Registro de seguridad detallado');

            // Performance metrics
            $table->decimal('performance_rating', 3, 2)->nullable()->comment('Calificación de desempeño (1-5)');
            $table->integer('on_time_deliveries')->default(0)->comment('Entregas a tiempo');
            $table->integer('delayed_deliveries')->default(0)->comment('Entregas retrasadas');
            $table->decimal('fuel_efficiency_rating', 3, 2)->nullable()->comment('Calificación eficiencia combustible');
            $table->json('customer_ratings')->nullable()->comment('Calificaciones de clientes');

            // Languages and communication
            $table->json('languages_spoken')->nullable()->comment('Idiomas hablados con nivel');
            $table->boolean('radio_operator_license')->default(false)->comment('Licencia operador de radio');
            $table->string('radio_call_sign', 20)->nullable()->comment('Señal de llamada radio');
            $table->boolean('english_proficiency')->default(false)->comment('Dominio del inglés');

            // Employment and availability
            $table->enum('employment_status', [
                'employee',             // Empleado
                'contractor',           // Contratista
                'freelance',           // Freelance
                'retired',             // Retirado
                'unavailable'          // No disponible
            ])->default('employee')->comment('Estado laboral');

            $table->foreignId('primary_company_id')->nullable()->constrained('companies')->comment('Empresa principal');
            $table->json('authorized_companies')->nullable()->comment('Empresas autorizadas para trabajar');
            $table->boolean('available_for_hire')->default(true)->comment('Disponible para contratación');
            $table->date('available_from')->nullable()->comment('Disponible desde');
            $table->date('available_until')->nullable()->comment('Disponible hasta');
            $table->json('preferred_routes')->nullable()->comment('Rutas preferidas');

            // Financial information
            $table->decimal('daily_rate', 8, 2)->nullable()->comment('Tarifa diaria');
            $table->decimal('overtime_rate', 8, 2)->nullable()->comment('Tarifa horas extra');
            $table->boolean('includes_meals')->default(false)->comment('Incluye comidas');
            $table->boolean('includes_accommodation')->default(false)->comment('Incluye alojamiento');
            $table->string('preferred_payment_method', 50)->nullable()->comment('Método de pago preferido');

            // Insurance and liability
            $table->string('insurance_policy_number', 100)->nullable()->comment('Número póliza seguro');
            $table->date('insurance_expires_at')->nullable()->comment('Vencimiento seguro');
            $table->decimal('insurance_coverage_amount', 12, 2)->nullable()->comment('Monto cobertura seguro');
            $table->boolean('bonded')->default(false)->comment('Afianzado');
            $table->string('bond_number', 100)->nullable()->comment('Número de fianza');

            // Webservice integration
            $table->string('argentina_ws_id', 50)->nullable()->comment('ID en webservice Argentina');
            $table->string('paraguay_ws_id', 50)->nullable()->comment('ID en webservice Paraguay');
            $table->json('webservice_data')->nullable()->comment('Datos adicionales para webservices');

            // Notes and observations
            $table->text('notes')->nullable()->comment('Notas adicionales');
            $table->text('internal_notes')->nullable()->comment('Notas internas (no visibles para cliente)');
            $table->json('tags')->nullable()->comment('Etiquetas para clasificación');

            // Status and control
            $table->boolean('active')->default(true)->comment('Capitán activo');
            $table->boolean('verified')->default(false)->comment('Datos verificados');
            $table->boolean('background_checked')->default(false)->comment('Verificación de antecedentes realizada');
            $table->date('last_background_check')->nullable()->comment('Última verificación antecedentes');
            $table->boolean('requires_approval')->default(false)->comment('Requiere aprobación para viajes');

            // Audit trail
            $table->timestamp('created_date')->useCurrent()->comment('Fecha de creación');
            $table->unsignedBigInteger('created_by_user_id')->nullable()->comment('Usuario que creó el registro');
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

            // Foreign key constraints
            $table->foreign('country_id')->references('id')->on('countries')->onDelete('set null');
            $table->foreign('license_country_id')->references('id')->on('countries')->onDelete('set null');
            $table->foreign('primary_company_id')->references('id')->on('companies')->onDelete('set null');
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