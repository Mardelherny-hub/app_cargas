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
     * Tabla de referencia para tipos de embalaje
     * Soporta diferentes tipos de empaque según estándares internacionales
     * 
     * TIPOS PRINCIPALES:
     * - Pallets, Cajas, Sacos, Tambores
     * - Contenedores, Bolsas, Fardos
     * - Embalajes especializados por industria
     */
    public function up(): void
    {
        Schema::create('packaging_types', function (Blueprint $table) {
            // Primary key
            $table->id();

            // Basic identification
            $table->string('code', 20)->unique()->comment('Código único del tipo de embalaje');
            $table->string('name', 100)->comment('Nombre del tipo de embalaje');
            $table->string('short_name', 30)->nullable()->comment('Nombre corto para UI');
            $table->text('description')->nullable()->comment('Descripción detallada del tipo');

            // International classification
            $table->string('unece_code', 10)->nullable()->comment('Código UN/ECE para embalajes');
            $table->string('iso_code', 10)->nullable()->comment('Código ISO si aplica');
            $table->string('imdg_code', 10)->nullable()->comment('Código IMDG para mercancías peligrosas');

            // Packaging category
            $table->enum('category', [
                'pallet',           // Pallet/Tarima
                'box',              // Caja/Cartón
                'bag',              // Bolsa/Saco
                'drum',             // Tambor/Bidón
                'container',        // Contenedor pequeño
                'bundle',           // Fardo/Atado
                'roll',             // Rollo
                'bale',             // Fardo prensado
                'crate',            // Cajón/Jaula
                'barrel',           // Barril
                'tank',             // Tanque pequeño
                'tray',             // Bandeja
                'bulk',             // A granel
                'specialized'       // Especializado
            ])->comment('Categoría del embalaje');

            $table->enum('material_type', [
                'wood',             // Madera
                'cardboard',        // Cartón
                'plastic',          // Plástico
                'metal',            // Metal
                'fabric',           // Tela/Tejido
                'paper',            // Papel
                'composite',        // Compuesto
                'glass',            // Vidrio
                'rubber',           // Caucho
                'other'             // Otro
            ])->nullable()->comment('Material principal del embalaje');

            // Physical specifications
            $table->decimal('length_mm', 8, 2)->nullable()->comment('Longitud en milímetros');
            $table->decimal('width_mm', 8, 2)->nullable()->comment('Ancho en milímetros');
            $table->decimal('height_mm', 8, 2)->nullable()->comment('Alto en milímetros');
            $table->decimal('diameter_mm', 8, 2)->nullable()->comment('Diámetro en milímetros (cilíndricos)');
            $table->decimal('volume_liters', 8, 2)->nullable()->comment('Volumen en litros');
            $table->decimal('volume_m3', 8, 3)->nullable()->comment('Volumen en metros cúbicos');

            // Weight specifications
            $table->decimal('empty_weight_kg', 8, 2)->nullable()->comment('Peso vacío en kilogramos');
            $table->decimal('max_gross_weight_kg', 8, 2)->nullable()->comment('Peso bruto máximo en kilogramos');
            $table->decimal('max_net_weight_kg', 8, 2)->nullable()->comment('Peso neto máximo en kilogramos');
            $table->decimal('weight_tolerance_percent', 5, 2)->default(5.00)->comment('Tolerancia de peso en porcentaje');

            // Structural characteristics
            $table->boolean('is_stackable')->default(true)->comment('Es apilable');
            $table->integer('max_stack_height')->nullable()->comment('Altura máxima de apilamiento');
            $table->decimal('stacking_weight_limit_kg', 8, 2)->nullable()->comment('Límite de peso para apilamiento');
            $table->boolean('is_reusable')->default(true)->comment('Es reutilizable');
            $table->boolean('is_returnable')->default(false)->comment('Es retornable');
            $table->boolean('is_collapsible')->default(false)->comment('Es plegable/colapsable');

            // Handling characteristics
            $table->boolean('requires_palletizing')->default(false)->comment('Requiere paletizado');
            $table->boolean('requires_strapping')->default(false)->comment('Requiere zunchado');
            $table->boolean('requires_wrapping')->default(false)->comment('Requiere envolvimiento');
            $table->boolean('requires_special_handling')->default(false)->comment('Requiere manejo especial');
            $table->enum('handling_equipment', [
                'manual',           // Manual
                'forklift',         // Montacargas
                'crane',            // Grúa
                'conveyor',         // Transportador
                'specialized'       // Especializado
            ])->default('manual')->comment('Equipo de manejo requerido');

            // Environmental characteristics
            $table->boolean('is_weatherproof')->default(false)->comment('Resistente al clima');
            $table->boolean('is_moisture_resistant')->default(false)->comment('Resistente a la humedad');
            $table->boolean('is_uv_resistant')->default(false)->comment('Resistente a rayos UV');
            $table->decimal('min_temperature_celsius', 5, 2)->nullable()->comment('Temperatura mínima en Celsius');
            $table->decimal('max_temperature_celsius', 5, 2)->nullable()->comment('Temperatura máxima en Celsius');
            $table->boolean('requires_ventilation')->default(false)->comment('Requiere ventilación');
            $table->boolean('requires_humidity_control')->default(false)->comment('Requiere control de humedad');

            // Cargo compatibility
            $table->boolean('suitable_for_food')->default(true)->comment('Apto para alimentos');
            $table->boolean('suitable_for_liquids')->default(false)->comment('Apto para líquidos');
            $table->boolean('suitable_for_powders')->default(false)->comment('Apto para polvos');
            $table->boolean('suitable_for_chemicals')->default(false)->comment('Apto para químicos');
            $table->boolean('suitable_for_dangerous_goods')->default(false)->comment('Apto para mercancías peligrosas');
            $table->boolean('suitable_for_fragile_items')->default(false)->comment('Apto para artículos frágiles');
            $table->boolean('suitable_for_heavy_items')->default(true)->comment('Apto para artículos pesados');
            $table->boolean('suitable_for_bulk_cargo')->default(false)->comment('Apto para carga a granel');

            // Protection characteristics
            $table->boolean('provides_cushioning')->default(false)->comment('Proporciona amortiguación');
            $table->boolean('provides_impact_protection')->default(false)->comment('Protección contra impactos');
            $table->boolean('provides_theft_protection')->default(false)->comment('Protección contra robo');
            $table->boolean('is_tamper_evident')->default(false)->comment('Evidencia de manipulación');
            $table->boolean('is_child_resistant')->default(false)->comment('Resistente a niños');
            $table->boolean('is_hermetic')->default(false)->comment('Hermético');

            // Sustainability and recycling
            $table->boolean('is_recyclable')->default(true)->comment('Es reciclable');
            $table->boolean('is_biodegradable')->default(false)->comment('Es biodegradable');
            $table->boolean('is_compostable')->default(false)->comment('Es compostable');
            $table->boolean('contains_recycled_material')->default(false)->comment('Contiene material reciclado');
            $table->decimal('recycled_content_percent', 5, 2)->nullable()->comment('Porcentaje de contenido reciclado');
            $table->string('disposal_instructions', 500)->nullable()->comment('Instrucciones de disposición');

            // Economic factors
            $table->decimal('unit_cost', 8, 2)->nullable()->comment('Costo unitario');
            $table->decimal('cost_per_kg', 8, 2)->nullable()->comment('Costo por kilogramo');
            $table->decimal('cost_per_m3', 8, 2)->nullable()->comment('Costo por metro cúbico');
            $table->boolean('cost_varies_by_quantity')->default(true)->comment('Costo varía por cantidad');
            $table->integer('minimum_order_quantity')->default(1)->comment('Cantidad mínima de orden');

            // Regulatory and certification
            $table->boolean('fda_approved')->default(false)->comment('Aprobado por FDA');
            $table->boolean('food_contact_safe')->default(false)->comment('Seguro para contacto alimentario');
            $table->boolean('pharmaceutical_grade')->default(false)->comment('Grado farmacéutico');
            $table->json('certifications')->nullable()->comment('Certificaciones adicionales');
            $table->json('regulatory_compliance')->nullable()->comment('Cumplimiento regulatorio');

            // Labeling and marking
            $table->boolean('requires_labeling')->default(true)->comment('Requiere etiquetado');
            $table->boolean('allows_printing')->default(true)->comment('Permite impresión');
            $table->boolean('requires_hazmat_marking')->default(false)->comment('Requiere marcado de mercancías peligrosas');
            $table->json('required_markings')->nullable()->comment('Marcados requeridos');
            $table->json('prohibited_markings')->nullable()->comment('Marcados prohibidos');

            // Webservice integration
            $table->string('argentina_ws_code', 20)->nullable()->comment('Código para webservice Argentina');
            $table->string('paraguay_ws_code', 20)->nullable()->comment('Código para webservice Paraguay');
            $table->string('customs_code', 20)->nullable()->comment('Código aduanero');
            $table->string('senasa_code', 20)->nullable()->comment('Código SENASA');
            $table->json('webservice_mapping')->nullable()->comment('Mapeo adicional para webservices');

            // Industry-specific data
            $table->json('industry_applications')->nullable()->comment('Aplicaciones por industria');
            $table->json('commodity_compatibility')->nullable()->comment('Compatibilidad con commodities');
            $table->json('seasonal_considerations')->nullable()->comment('Consideraciones estacionales');

            // Quality and testing
            $table->boolean('requires_testing')->default(false)->comment('Requiere pruebas');
            $table->integer('testing_frequency_days')->nullable()->comment('Frecuencia de pruebas en días');
            $table->json('quality_standards')->nullable()->comment('Estándares de calidad');
            $table->decimal('acceptable_defect_rate_percent', 5, 2)->default(2.00)->comment('Tasa aceptable de defectos');

            // Availability and supply chain
            $table->boolean('widely_available')->default(true)->comment('Ampliamente disponible');
            $table->integer('typical_lead_time_days')->nullable()->comment('Tiempo de entrega típico en días');
            $table->json('preferred_suppliers')->nullable()->comment('Proveedores preferidos');
            $table->json('alternative_types')->nullable()->comment('Tipos alternativos');

            // Status and display
            $table->boolean('active')->default(true)->comment('Tipo activo');
            $table->boolean('is_standard')->default(true)->comment('Tipo estándar');
            $table->boolean('is_common')->default(false)->comment('Tipo común/frecuente');
            $table->boolean('is_specialized')->default(false)->comment('Tipo especializado');
            $table->boolean('is_deprecated')->default(false)->comment('Tipo obsoleto');
            $table->integer('display_order')->default(999)->comment('Orden de visualización');
            $table->string('icon', 100)->nullable()->comment('Icono para UI');
            $table->string('color_code', 7)->nullable()->comment('Color para gráficos');

            // Audit trail
            $table->timestamp('created_date')->useCurrent()->comment('Fecha de creación');
            $table->unsignedBigInteger('created_by_user_id')->nullable()->comment('Usuario que creó el registro');
            $table->timestamps();

            // Performance indexes
            $table->index(['active', 'is_standard'], 'idx_packaging_types_active_standard');
            $table->index(['category', 'active'], 'idx_packaging_types_category');
            $table->index(['material_type', 'active'], 'idx_packaging_types_material');
            $table->index(['suitable_for_food', 'active'], 'idx_packaging_types_food');
            $table->index(['suitable_for_dangerous_goods', 'active'], 'idx_packaging_types_dangerous');
            $table->index(['suitable_for_liquids', 'active'], 'idx_packaging_types_liquids');
            $table->index(['is_reusable', 'active'], 'idx_packaging_types_reusable');
            $table->index(['is_recyclable', 'active'], 'idx_packaging_types_recyclable');
            $table->index(['is_common', 'active'], 'idx_packaging_types_common');
            $table->index(['code'], 'idx_packaging_types_code');
            $table->index(['unece_code'], 'idx_packaging_types_unece');
            $table->index(['display_order'], 'idx_packaging_types_display_order');

            // Unique constraints
            $table->unique(['unece_code'], 'uk_packaging_types_unece');

            // Foreign key constraints
            // $table->foreign('created_by_user_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('packaging_types');
    }
};