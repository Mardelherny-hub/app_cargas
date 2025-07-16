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
     * Tabla de referencia para tipos de contenedor
     * Soporta contenedores estándar ISO y especializados
     * 
     * TIPOS PRINCIPALES:
     * - Estándar (20', 40', 45')
     * - Refrigerados (reefer)
     * - Open Top, Flat Rack, Tank
     * - Especializados según carga
     */
    public function up(): void
    {
        Schema::create('container_types', function (Blueprint $table) {
            // Primary key
            $table->id();

            // Basic identification
            $table->string('code', 20)->unique()->comment('Código único del tipo de contenedor');
            $table->string('name', 100)->comment('Nombre del tipo de contenedor');
            $table->string('short_name', 30)->nullable()->comment('Nombre corto para UI');
            $table->text('description')->nullable()->comment('Descripción detallada del tipo');

            // ISO classification
            $table->string('iso_code', 10)->nullable()->comment('Código ISO 6346');
            $table->string('iso_size_type', 4)->nullable()->comment('Código ISO tamaño/tipo (ej: 22G1)');
            $table->string('iso_group', 10)->nullable()->comment('Grupo ISO del contenedor');

            // Physical dimensions
            $table->enum('length_feet', [
                '10', '20', '40', '45', '48', '53'
            ])->comment('Longitud en pies');
            
            $table->enum('width_feet', [
                '8', '8.5', '9', '9.5'
            ])->default('8')->comment('Ancho en pies');
            
            $table->enum('height_feet', [
                '8', '8.5', '9', '9.5', '10.5'
            ])->default('8.5')->comment('Alto en pies');

            // Metric dimensions (calculated from feet)
            $table->decimal('length_mm', 8, 2)->comment('Longitud en milímetros');
            $table->decimal('width_mm', 8, 2)->comment('Ancho en milímetros');
            $table->decimal('height_mm', 8, 2)->comment('Alto en milímetros');
            $table->decimal('internal_length_mm', 8, 2)->comment('Longitud interna en milímetros');
            $table->decimal('internal_width_mm', 8, 2)->comment('Ancho interno en milímetros');
            $table->decimal('internal_height_mm', 8, 2)->comment('Alto interno en milímetros');

            // Weight specifications
            $table->decimal('tare_weight_kg', 8, 2)->comment('Peso tara en kilogramos');
            $table->decimal('max_gross_weight_kg', 8, 2)->comment('Peso bruto máximo en kilogramos');
            $table->decimal('max_payload_kg', 8, 2)->comment('Carga útil máxima en kilogramos');

            // Volume specifications
            $table->decimal('internal_volume_m3', 8, 2)->comment('Volumen interno en metros cúbicos');
            $table->decimal('loading_volume_m3', 8, 2)->nullable()->comment('Volumen de carga en metros cúbicos');

            // Container category
            $table->enum('category', [
                'dry_cargo',        // Carga seca
                'refrigerated',     // Refrigerado
                'open_top',         // Techo abierto
                'flat_rack',        // Plataforma
                'tank',             // Tanque
                'bulk',             // Granel
                'specialized'       // Especializado
            ])->default('dry_cargo')->comment('Categoría del contenedor');

            // Special characteristics
            $table->boolean('is_refrigerated')->default(false)->comment('Es refrigerado');
            $table->boolean('is_heated')->default(false)->comment('Tiene calefacción');
            $table->boolean('is_insulated')->default(false)->comment('Está aislado');
            $table->boolean('is_ventilated')->default(false)->comment('Tiene ventilación');
            $table->boolean('has_electrical_supply')->default(false)->comment('Tiene suministro eléctrico');

            // Construction features
            $table->boolean('has_roof')->default(true)->comment('Tiene techo');
            $table->boolean('has_sidewalls')->default(true)->comment('Tiene paredes laterales');
            $table->boolean('has_end_walls')->default(true)->comment('Tiene paredes frontales');
            $table->boolean('has_doors')->default(true)->comment('Tiene puertas');
            $table->boolean('has_removable_top')->default(false)->comment('Techo removible');
            $table->boolean('has_folding_sides')->default(false)->comment('Lados plegables');

            // Door specifications
            $table->enum('door_type', [
                'standard',         // Estándar
                'double_door',      // Doble puerta
                'side_door',        // Puerta lateral
                'end_door',         // Puerta frontal
                'no_door'           // Sin puerta
            ])->default('standard')->comment('Tipo de puerta');

            $table->decimal('door_width_mm', 8, 2)->nullable()->comment('Ancho de puerta en milímetros');
            $table->decimal('door_height_mm', 8, 2)->nullable()->comment('Alto de puerta en milímetros');

            // Temperature control (for refrigerated containers)
            $table->decimal('min_temperature_celsius', 5, 2)->nullable()->comment('Temperatura mínima en Celsius');
            $table->decimal('max_temperature_celsius', 5, 2)->nullable()->comment('Temperatura máxima en Celsius');
            $table->boolean('has_humidity_control')->default(false)->comment('Control de humedad');
            $table->boolean('has_atmosphere_control')->default(false)->comment('Control de atmósfera');

            // Cargo compatibility
            $table->boolean('suitable_for_dangerous_goods')->default(false)->comment('Apto para mercancías peligrosas');
            $table->boolean('suitable_for_food')->default(true)->comment('Apto para alimentos');
            $table->boolean('suitable_for_chemicals')->default(false)->comment('Apto para químicos');
            $table->boolean('suitable_for_liquids')->default(false)->comment('Apto para líquidos');
            $table->boolean('suitable_for_bulk_cargo')->default(false)->comment('Apto para carga a granel');
            $table->boolean('suitable_for_heavy_cargo')->default(true)->comment('Apto para carga pesada');
            $table->boolean('suitable_for_oversized_cargo')->default(false)->comment('Apto para carga sobredimensionada');

            // Handling requirements
            $table->boolean('requires_special_handling')->default(false)->comment('Requiere manejo especial');
            $table->boolean('requires_power_supply')->default(false)->comment('Requiere suministro eléctrico');
            $table->boolean('requires_ventilation')->default(false)->comment('Requiere ventilación');
            $table->boolean('requires_monitoring')->default(false)->comment('Requiere monitoreo');
            $table->boolean('stackable')->default(true)->comment('Apilable');
            $table->integer('max_stack_height')->default(9)->comment('Altura máxima de apilamiento');

            // Certification and standards
            $table->boolean('csc_certified')->default(true)->comment('Certificado CSC');
            $table->boolean('food_grade')->default(false)->comment('Grado alimentario');
            $table->boolean('pharmaceutical_grade')->default(false)->comment('Grado farmacéutico');
            $table->json('certifications')->nullable()->comment('Certificaciones adicionales');

            // Operational characteristics
            $table->integer('typical_lifespan_years')->default(15)->comment('Vida útil típica en años');
            $table->integer('inspection_interval_months')->default(12)->comment('Intervalo de inspección en meses');
            $table->boolean('requires_pretrip_inspection')->default(true)->comment('Requiere inspección previa');
            $table->boolean('requires_cleaning')->default(true)->comment('Requiere limpieza');
            $table->boolean('requires_fumigation')->default(false)->comment('Requiere fumigación');

            // Economic data
            $table->decimal('daily_rental_rate', 8, 2)->nullable()->comment('Tarifa diaria de alquiler');
            $table->decimal('purchase_price_estimate', 10, 2)->nullable()->comment('Precio estimado de compra');
            $table->decimal('maintenance_cost_per_year', 8, 2)->nullable()->comment('Costo mantenimiento anual');

            // Webservice integration
            $table->string('argentina_ws_code', 20)->nullable()->comment('Código para webservice Argentina');
            $table->string('paraguay_ws_code', 20)->nullable()->comment('Código para webservice Paraguay');
            $table->string('customs_code', 20)->nullable()->comment('Código aduanero');
            $table->json('webservice_mapping')->nullable()->comment('Mapeo adicional para webservices');

            // Condition codes for webservices
            $table->json('allowed_conditions')->nullable()->comment('Condiciones permitidas (V, D, S, etc.)');
            $table->text('condition_descriptions')->nullable()->comment('Descripción de condiciones');

            // Port and vessel compatibility
            $table->json('compatible_vessel_types')->nullable()->comment('Tipos de embarcación compatibles');
            $table->json('restricted_ports')->nullable()->comment('Puertos con restricciones');
            $table->json('handling_equipment_required')->nullable()->comment('Equipos de manejo requeridos');

            // Environmental considerations
            $table->boolean('eco_friendly')->default(false)->comment('Amigable con el ambiente');
            $table->decimal('carbon_footprint_kg', 8, 2)->nullable()->comment('Huella de carbono en kg');
            $table->json('environmental_certifications')->nullable()->comment('Certificaciones ambientales');

            // Status and display
            $table->boolean('active')->default(true)->comment('Tipo activo');
            $table->boolean('is_standard')->default(true)->comment('Tipo estándar');
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
            $table->index(['active', 'is_standard'], 'idx_container_types_active_standard');
            $table->index(['category', 'active'], 'idx_container_types_category');
            $table->index(['length_feet', 'active'], 'idx_container_types_length');
            $table->index(['is_refrigerated', 'active'], 'idx_container_types_refrigerated');
            $table->index(['suitable_for_dangerous_goods', 'active'], 'idx_container_types_dangerous');
            $table->index(['suitable_for_food', 'active'], 'idx_container_types_food');
            $table->index(['is_common', 'active'], 'idx_container_types_common');
            $table->index(['code'], 'idx_container_types_code');
            $table->index(['iso_code'], 'idx_container_types_iso');
            $table->index(['display_order'], 'idx_container_types_display_order');

            // Unique constraints
            $table->unique(['iso_code'], 'uk_container_types_iso');

            // Foreign key constraints
            // $table->foreign('created_by_user_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('container_types');
    }
};