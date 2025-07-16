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
     * Tabla para embarcaciones específicas (barcos, barcazas individuales)
     * Cada registro representa una embarcación real con sus características específicas
     * 
     * REFERENCIAS CONFIRMADAS DEL SISTEMA:
     * - companies (empresa propietaria)
     * - vessel_types (tipo de embarcación)
     * - captains (capitán principal)
     * - countries (país de bandera)
     * - ports (puerto base)
     * - users (auditoría)
     */
    public function up(): void
    {
        Schema::create('vessels', function (Blueprint $table) {
            // Primary key
            $table->id();

            // Basic identification
            $table->string('name', 100)->comment('Nombre de la embarcación');
            $table->string('registration_number', 50)->unique()->comment('Número de matrícula/registro');
            $table->string('imo_number', 20)->nullable()->unique()->comment('Número IMO (si aplica)');
            $table->string('call_sign', 20)->nullable()->comment('Señal de llamada');
            $table->string('mmsi_number', 20)->nullable()->comment('Número MMSI');

            // Foreign keys (definidos manualmente para evitar conflictos de nombres)
            $table->unsignedBigInteger('company_id')->comment('Empresa propietaria');
            $table->unsignedBigInteger('vessel_type_id')->comment('Tipo de embarcación');
            $table->unsignedBigInteger('flag_country_id')->comment('País de bandera');
            $table->unsignedBigInteger('home_port_id')->nullable()->comment('Puerto base');
            $table->unsignedBigInteger('primary_captain_id')->nullable()->comment('Capitán principal');
            // Physical specifications (specific to this vessel)
            $table->decimal('length_meters', 8, 2)->comment('Longitud en metros');
            $table->decimal('beam_meters', 8, 2)->comment('Manga en metros');
            $table->decimal('draft_meters', 8, 2)->comment('Calado en metros');
            $table->decimal('depth_meters', 8, 2)->nullable()->comment('Puntal en metros');
            $table->decimal('gross_tonnage', 10, 2)->nullable()->comment('Tonelaje bruto');
            $table->decimal('net_tonnage', 10, 2)->nullable()->comment('Tonelaje neto');
            $table->decimal('deadweight_tonnage', 10, 2)->nullable()->comment('Porte bruto');

            // Capacity specifications
            $table->decimal('cargo_capacity_tons', 10, 2)->comment('Capacidad de carga en toneladas');
            $table->integer('container_capacity')->nullable()->comment('Capacidad en contenedores TEU');
            $table->decimal('liquid_capacity_m3', 10, 2)->nullable()->comment('Capacidad líquidos en m³');
            $table->decimal('fuel_capacity_liters', 10, 2)->nullable()->comment('Capacidad combustible en litros');
            $table->decimal('fresh_water_capacity_liters', 8, 2)->nullable()->comment('Capacidad agua dulce en litros');

            // Engine and propulsion
            $table->string('engine_type', 100)->nullable()->comment('Tipo de motor');
            $table->string('engine_manufacturer', 100)->nullable()->comment('Fabricante del motor');
            $table->string('engine_model', 100)->nullable()->comment('Modelo del motor');
            $table->integer('engine_power_hp')->nullable()->comment('Potencia del motor en HP');
            $table->integer('engine_hours')->default(0)->comment('Horas de motor');
            $table->decimal('max_speed_knots', 5, 2)->nullable()->comment('Velocidad máxima en nudos');
            $table->decimal('cruising_speed_knots', 5, 2)->nullable()->comment('Velocidad de crucero en nudos');

            // Construction details
            $table->date('built_date')->nullable()->comment('Fecha de construcción');
            $table->string('shipyard', 100)->nullable()->comment('Astillero constructor');
            $table->string('hull_material', 50)->nullable()->comment('Material del casco');
            $table->string('hull_number', 50)->nullable()->comment('Número de casco');
            $table->integer('keel_laid_year')->nullable()->comment('Año de puesta de quilla');

            // Current operational status
            $table->enum('operational_status', [
                'active',           // Activo
                'maintenance',      // En mantenimiento
                'dry_dock',         // En dique seco
                'inactive',         // Inactivo
                'under_repair',     // En reparación
                'decommissioned'    // Fuera de servicio
            ])->default('active')->comment('Estado operacional');

            $table->enum('ownership_type', [
                'owned',            // Propio
                'chartered',        // Fletado
                'leased',           // Arrendado
                'managed'           // Administrado
            ])->default('owned')->comment('Tipo de propiedad');

            // Current location and availability
            $table->foreignId('current_port_id')->nullable()->constrained('ports')->comment('Puerto actual');
            $table->decimal('current_latitude', 10, 8)->nullable()->comment('Latitud actual');
            $table->decimal('current_longitude', 11, 8)->nullable()->comment('Longitud actual');
            $table->timestamp('location_updated_at')->nullable()->comment('Última actualización de ubicación');
            $table->boolean('available_for_charter')->default(true)->comment('Disponible para fletamento');
            $table->date('available_from')->nullable()->comment('Disponible desde');
            $table->date('available_until')->nullable()->comment('Disponible hasta');

            // Crew information
            $table->integer('crew_capacity')->nullable()->comment('Capacidad de tripulación');
            $table->integer('current_crew_size')->default(0)->comment('Tripulación actual');
            $table->boolean('crew_quarters_available')->default(true)->comment('Camarotes disponibles');
            $table->integer('passenger_capacity')->default(0)->comment('Capacidad de pasajeros');

            // Safety and certification
            $table->string('safety_certificate_number', 100)->nullable()->comment('Número certificado seguridad');
            $table->date('safety_certificate_expires')->nullable()->comment('Vencimiento certificado seguridad');
            $table->string('insurance_policy_number', 100)->nullable()->comment('Número póliza seguro');
            $table->date('insurance_expires')->nullable()->comment('Vencimiento seguro');
            $table->decimal('insurance_value', 12, 2)->nullable()->comment('Valor asegurado');
            $table->date('last_inspection_date')->nullable()->comment('Última inspección');
            $table->date('next_inspection_due')->nullable()->comment('Próxima inspección');

            // Maintenance information
            $table->date('last_maintenance_date')->nullable()->comment('Último mantenimiento');
            $table->date('next_maintenance_due')->nullable()->comment('Próximo mantenimiento');
            $table->integer('maintenance_interval_days')->default(365)->comment('Intervalo mantenimiento en días');
            $table->date('last_dry_dock_date')->nullable()->comment('Último dique seco');
            $table->date('next_dry_dock_due')->nullable()->comment('Próximo dique seco');

            // Economic information
            $table->decimal('daily_charter_rate', 8, 2)->nullable()->comment('Tarifa diaria de fletamento');
            $table->decimal('fuel_consumption_per_day', 8, 2)->nullable()->comment('Consumo combustible diario');
            $table->decimal('daily_operating_cost', 8, 2)->nullable()->comment('Costo operativo diario');
            $table->decimal('purchase_value', 12, 2)->nullable()->comment('Valor de compra');
            $table->decimal('current_market_value', 12, 2)->nullable()->comment('Valor de mercado actual');

            // Cargo handling equipment
            $table->json('onboard_equipment')->nullable()->comment('Equipos a bordo (grúas, etc.)');
            $table->json('cargo_handling_capabilities')->nullable()->comment('Capacidades de manejo de carga');
            $table->boolean('has_cranes')->default(false)->comment('Tiene grúas');
            $table->boolean('has_conveyor_system')->default(false)->comment('Tiene sistema transportador');
            $table->boolean('has_refrigeration')->default(false)->comment('Tiene refrigeración');

            // Communications and navigation
            $table->json('communication_equipment')->nullable()->comment('Equipos de comunicación');
            $table->json('navigation_equipment')->nullable()->comment('Equipos de navegación');
            $table->boolean('has_gps')->default(true)->comment('Tiene GPS');
            $table->boolean('has_radar')->default(false)->comment('Tiene radar');
            $table->boolean('has_ais')->default(false)->comment('Tiene AIS');

            // Environmental compliance
            $table->string('marpol_certificate', 100)->nullable()->comment('Certificado MARPOL');
            $table->date('marpol_expires')->nullable()->comment('Vencimiento MARPOL');
            $table->boolean('green_technology')->default(false)->comment('Tecnología verde');
            $table->json('environmental_certifications')->nullable()->comment('Certificaciones ambientales');

            // Webservice integration
            $table->string('argentina_vessel_code', 50)->nullable()->comment('Código embarcación Argentina');
            $table->string('paraguay_vessel_code', 50)->nullable()->comment('Código embarcación Paraguay');
            $table->json('webservice_data')->nullable()->comment('Datos adicionales webservices');

            // Operational history counters
            $table->integer('total_voyages')->default(0)->comment('Total de viajes realizados');
            $table->decimal('total_nautical_miles', 12, 2)->default(0)->comment('Millas náuticas totales');
            $table->decimal('total_cargo_transported', 12, 2)->default(0)->comment('Carga total transportada');
            $table->integer('voyages_this_year')->default(0)->comment('Viajes este año');
            $table->date('last_voyage_date')->nullable()->comment('Fecha último viaje');

            // Contract and charter information
            $table->text('charter_terms')->nullable()->comment('Términos de fletamento');
            $table->date('charter_expires')->nullable()->comment('Vencimiento fletamento');
            $table->decimal('charter_rate', 8, 2)->nullable()->comment('Tarifa fletamento');
            $table->string('charter_type', 50)->nullable()->comment('Tipo de fletamento');

            // Documents and photos
            $table->json('required_documents')->nullable()->comment('Documentos requeridos');
            $table->json('uploaded_documents')->nullable()->comment('Documentos subidos');
            $table->json('vessel_photos')->nullable()->comment('Fotos de la embarcación');

            // Notes and observations
            $table->text('description')->nullable()->comment('Descripción de la embarcación');
            $table->text('operational_notes')->nullable()->comment('Notas operacionales');
            $table->text('maintenance_notes')->nullable()->comment('Notas de mantenimiento');
            $table->text('restrictions')->nullable()->comment('Restricciones operacionales');
            $table->json('special_capabilities')->nullable()->comment('Capacidades especiales');

            // Status and control
            $table->boolean('active')->default(true)->comment('Embarcación activa');
            $table->boolean('verified')->default(false)->comment('Datos verificados');
            $table->boolean('inspection_current')->default(false)->comment('Inspección al día');
            $table->boolean('insurance_current')->default(false)->comment('Seguro al día');
            $table->boolean('certificates_current')->default(false)->comment('Certificados al día');

            // Audit trail
            $table->timestamp('created_date')->useCurrent()->comment('Fecha de creación');
            $table->unsignedBigInteger('created_by_user_id')->nullable()->comment('Usuario que creó el registro');
            $table->timestamp('last_updated_date')->useCurrent()->useCurrentOnUpdate()->comment('Última actualización');
            $table->unsignedBigInteger('last_updated_by_user_id')->nullable()->comment('Último usuario que actualizó');
            $table->timestamps();

            // Performance indexes
            $table->index(['company_id', 'active'], 'idx_vessels_company_active');
            $table->index(['vessel_type_id', 'active'], 'idx_vessels_type_active');
            $table->index(['operational_status', 'active'], 'idx_vessels_status');
            $table->index(['available_for_charter', 'active'], 'idx_vessels_charter_available');
            $table->index(['current_port_id', 'active'], 'idx_vessels_current_port');
            $table->index(['home_port_id', 'active'], 'idx_vessels_home_port');
            $table->index(['primary_captain_id'], 'idx_vessels_captain');
            $table->index(['flag_country_id'], 'idx_vessels_flag');
            $table->index(['next_inspection_due'], 'idx_vessels_inspection_due');
            $table->index(['next_maintenance_due'], 'idx_vessels_maintenance_due');
            $table->index(['insurance_expires'], 'idx_vessels_insurance_expiry');
            $table->index(['safety_certificate_expires'], 'idx_vessels_safety_expiry');
            $table->index(['available_from', 'available_until'], 'idx_vessels_availability');
            $table->index(['last_voyage_date'], 'idx_vessels_last_voyage');
            $table->index(['verified'], 'idx_vessels_verified');

            // Unique constraints for external identifiers
            $table->unique(['registration_number'], 'uk_vessels_registration');
            $table->unique(['imo_number'], 'uk_vessels_imo');
            $table->unique(['call_sign'], 'uk_vessels_call_sign');

           // Foreign key constraints con nombres explícitos
            $table->foreign('company_id', 'fk_vessels_company')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('vessel_type_id', 'fk_vessels_type')->references('id')->on('vessel_types')->onDelete('restrict');
            $table->foreign('flag_country_id', 'fk_vessels_flag_country')->references('id')->on('countries')->onDelete('restrict');
            $table->foreign('home_port_id', 'fk_vessels_home_port')->references('id')->on('ports')->onDelete('set null');
            $table->foreign('current_port_id', 'fk_vessels_current_port')->references('id')->on('ports')->onDelete('set null');
            $table->foreign('primary_captain_id', 'fk_vessels_captain')->references('id')->on('captains')->onDelete('set null');// Audit FKs commented until user system is fully confirmed
            // $table->foreign('created_by_user_id')->references('id')->on('users')->onDelete('set null');
            // $table->foreign('last_updated_by_user_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vessels');
    }
};