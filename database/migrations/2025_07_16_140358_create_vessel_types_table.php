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
     * Tabla de referencia para tipos de embarcación
     * Soporta transporte fluvial y marítimo con diferentes categorías
     * 
     * CATEGORÍAS PRINCIPALES:
     * - Barcazas (barge): Para transporte de carga
     * - Remolcadores (tugboat): Para arrastrar/empujar barcazas
     * - Autopropulsados (self_propelled): Barcos independientes
     * - Mixtos (mixed): Combinación de categorías
     */
    public function up(): void
    {
        Schema::create('vessel_types', function (Blueprint $table) {
            // Primary key
            $table->id();

            // Basic identification
            $table->string('code', 20)->unique()->comment('Código único del tipo de embarcación');
            $table->string('name', 100)->comment('Nombre del tipo de embarcación');
            $table->string('short_name', 30)->nullable()->comment('Nombre corto para UI');
            $table->text('description')->nullable()->comment('Descripción detallada del tipo');

            // Vessel classification
            $table->enum('category', [
                'barge',           // Barcaza
                'tugboat',         // Remolcador
                'self_propelled',  // Autopropulsado
                'pusher',          // Empujador
                'mixed'            // Mixto
            ])->comment('Categoría principal de la embarcación');

            $table->enum('propulsion_type', [
                'self_propelled',  // Autopropulsado
                'towed',          // Remolcado
                'pushed',         // Empujado
                'hybrid'          // Híbrido
            ])->default('self_propelled')->comment('Tipo de propulsión');

            // Physical specifications
            $table->decimal('min_length', 8, 2)->nullable()->comment('Longitud mínima en metros');
            $table->decimal('max_length', 8, 2)->nullable()->comment('Longitud máxima en metros');
            $table->decimal('min_beam', 8, 2)->nullable()->comment('Manga mínima en metros');
            $table->decimal('max_beam', 8, 2)->nullable()->comment('Manga máxima en metros');
            $table->decimal('min_draft', 8, 2)->nullable()->comment('Calado mínimo en metros');
            $table->decimal('max_draft', 8, 2)->nullable()->comment('Calado máximo en metros');

            // Capacity specifications
            $table->decimal('min_cargo_capacity', 10, 2)->nullable()->comment('Capacidad mínima de carga en toneladas');
            $table->decimal('max_cargo_capacity', 10, 2)->nullable()->comment('Capacidad máxima de carga en toneladas');
            $table->integer('min_container_capacity')->nullable()->comment('Capacidad mínima de contenedores');
            $table->integer('max_container_capacity')->nullable()->comment('Capacidad máxima de contenedores');
            $table->decimal('min_liquid_capacity', 10, 2)->nullable()->comment('Capacidad mínima líquidos en m³');
            $table->decimal('max_liquid_capacity', 10, 2)->nullable()->comment('Capacidad máxima líquidos en m³');

            // Operational characteristics
            $table->integer('typical_crew_size')->nullable()->comment('Tamaño típico de tripulación');
            $table->integer('max_crew_size')->nullable()->comment('Tamaño máximo de tripulación');
            $table->decimal('typical_speed', 5, 2)->nullable()->comment('Velocidad típica en nudos');
            $table->decimal('max_speed', 5, 2)->nullable()->comment('Velocidad máxima en nudos');
            $table->integer('fuel_consumption_per_day')->nullable()->comment('Consumo combustible por día en litros');

            // Cargo compatibility
            $table->boolean('handles_containers')->default(true)->comment('Maneja contenedores');
            $table->boolean('handles_bulk_cargo')->default(true)->comment('Maneja carga a granel');
            $table->boolean('handles_general_cargo')->default(true)->comment('Maneja carga general');
            $table->boolean('handles_liquid_cargo')->default(false)->comment('Maneja carga líquida');
            $table->boolean('handles_dangerous_goods')->default(false)->comment('Maneja mercancías peligrosas');
            $table->boolean('handles_refrigerated_cargo')->default(false)->comment('Maneja carga refrigerada');
            $table->boolean('handles_oversized_cargo')->default(false)->comment('Maneja carga sobredimensionada');

            // Navigation capabilities
            $table->boolean('river_navigation')->default(true)->comment('Navegación fluvial');
            $table->boolean('maritime_navigation')->default(false)->comment('Navegación marítima');
            $table->boolean('coastal_navigation')->default(false)->comment('Navegación costera');
            $table->boolean('lake_navigation')->default(false)->comment('Navegación lacustre');
            $table->decimal('min_water_depth', 8, 2)->nullable()->comment('Profundidad mínima requerida en metros');

            // Convoy capabilities
            $table->boolean('can_be_lead_vessel')->default(true)->comment('Puede ser embarcación líder');
            $table->boolean('can_be_in_convoy')->default(true)->comment('Puede ir en convoy');
            $table->boolean('can_push_barges')->default(false)->comment('Puede empujar barcazas');
            $table->boolean('can_tow_barges')->default(false)->comment('Puede remolcar barcazas');
            $table->integer('max_barges_in_convoy')->nullable()->comment('Máximo de barcazas en convoy');

            // Environmental and safety
            $table->boolean('requires_pilot')->default(false)->comment('Requiere piloto');
            $table->boolean('requires_tugboat_assistance')->default(false)->comment('Requiere asistencia de remolcador');
            $table->json('environmental_restrictions')->nullable()->comment('Restricciones ambientales');
            $table->json('seasonal_restrictions')->nullable()->comment('Restricciones estacionales');
            $table->json('weather_limitations')->nullable()->comment('Limitaciones climáticas');

            // Documentation and certification
            $table->boolean('requires_special_permits')->default(false)->comment('Requiere permisos especiales');
            $table->boolean('requires_insurance')->default(true)->comment('Requiere seguro');
            $table->boolean('requires_safety_certificate')->default(true)->comment('Requiere certificado de seguridad');
            $table->json('required_certifications')->nullable()->comment('Certificaciones requeridas');

            // International classifications
            $table->string('imo_type_code', 10)->nullable()->comment('Código tipo IMO');
            $table->string('inland_vessel_code', 10)->nullable()->comment('Código embarcación fluvial');
            $table->string('imdg_class', 10)->nullable()->comment('Clase IMDG para mercancías peligrosas');

            // Webservice integration
            $table->string('argentina_ws_code', 20)->nullable()->comment('Código para webservice Argentina');
            $table->string('paraguay_ws_code', 20)->nullable()->comment('Código para webservice Paraguay');
            $table->json('webservice_mapping')->nullable()->comment('Mapeo adicional para webservices');

            // Economic and operational data
            $table->decimal('daily_charter_rate', 10, 2)->nullable()->comment('Tarifa diaria de alquiler');
            $table->decimal('fuel_cost_per_day', 10, 2)->nullable()->comment('Costo combustible por día');
            $table->integer('typical_voyage_duration')->nullable()->comment('Duración típica de viaje en días');
            $table->integer('loading_time_hours')->nullable()->comment('Tiempo de carga en horas');
            $table->integer('unloading_time_hours')->nullable()->comment('Tiempo de descarga en horas');

            // Port compatibility
            $table->json('compatible_ports')->nullable()->comment('Puertos compatibles (array de port_ids)');
            $table->json('restricted_ports')->nullable()->comment('Puertos restringidos (array de port_ids)');
            $table->json('preferred_berths')->nullable()->comment('Muelles preferidos por puerto');

            // Maintenance and lifecycle
            $table->integer('typical_lifespan_years')->nullable()->comment('Vida útil típica en años');
            $table->integer('maintenance_interval_days')->nullable()->comment('Intervalo mantenimiento en días');
            $table->boolean('requires_dry_dock')->default(false)->comment('Requiere dique seco');
            $table->integer('dry_dock_interval_months')->nullable()->comment('Intervalo dique seco en meses');

            // Status and display
            $table->boolean('active')->default(true)->comment('Tipo activo');
            $table->boolean('is_common')->default(false)->comment('Tipo común/frecuente');
            $table->boolean('is_specialized')->default(false)->comment('Tipo especializado');
            $table->integer('display_order')->default(999)->comment('Orden de visualización');
            $table->string('icon', 100)->nullable()->comment('Icono para UI');
            $table->string('color_code', 7)->nullable()->comment('Color para gráficos');

            // Audit trail
            $table->timestamp('created_date')->useCurrent()->comment('Fecha de creación');
            $table->unsignedBigInteger('created_by_user_id')->nullable()->comment('Usuario que creó el registro');
            $table->timestamps();

            // Performance indexes
            $table->index(['active', 'is_common'], 'idx_vessel_types_active_common');
            $table->index(['category', 'active'], 'idx_vessel_types_category');
            $table->index(['propulsion_type', 'active'], 'idx_vessel_types_propulsion');
            $table->index(['handles_containers', 'active'], 'idx_vessel_types_containers');
            $table->index(['handles_bulk_cargo', 'active'], 'idx_vessel_types_bulk');
            $table->index(['river_navigation', 'active'], 'idx_vessel_types_river');
            $table->index(['maritime_navigation', 'active'], 'idx_vessel_types_maritime');
            $table->index(['can_be_lead_vessel', 'active'], 'idx_vessel_types_lead');
            $table->index(['can_be_in_convoy', 'active'], 'idx_vessel_types_convoy');
            $table->index(['code'], 'idx_vessel_types_code');
            $table->index(['display_order'], 'idx_vessel_types_display_order');

            // Foreign key constraints
            // $table->foreign('created_by_user_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vessel_types');
    }
};