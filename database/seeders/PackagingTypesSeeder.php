<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PackagingTypesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * 
     * MÓDULO 3: VIAJES Y CARGAS
     * Seeder para tipos de embalaje más comunes según estándares internacionales
     * Compatible con códigos UN/ECE y webservices AR/PY
     * 
     * 100% coherente con migración create_packaging_types_table.php
     */
    public function run(): void
    {
        $now = Carbon::now();

        $packagingTypes = [
            // 1. PALLET ESTÁNDAR
            [
                // Basic identification
                'code' => 'PAL001',
                'name' => 'Pallet Estándar',
                'short_name' => 'Pallet',
                'description' => 'Plataforma de madera estándar para facilitar manejo y almacenamiento',

                // International classification
                'unece_code' => 'PAL',
                'iso_code' => 'PAL',
                'imdg_code' => null,

                // Packaging category
                'category' => 'pallet',
                'material_type' => 'wood',

                // Physical specifications
                'length_mm' => 1200.00,
                'width_mm' => 1000.00,
                'height_mm' => 150.00,
                'diameter_mm' => null,
                'volume_liters' => null,
                'volume_m3' => 0.180,

                // Weight specifications
                'empty_weight_kg' => 25.00,
                'max_gross_weight_kg' => 1500.00,
                'max_net_weight_kg' => 1475.00,
                'weight_tolerance_percent' => 5.00,

                // Structural characteristics
                'is_stackable' => true,
                'max_stack_height' => 10,
                'stacking_weight_limit_kg' => 15000.00,
                'is_reusable' => true,
                'is_returnable' => true,
                'is_collapsible' => false,

                // Handling characteristics
                'requires_palletizing' => false,
                'requires_strapping' => false,
                'requires_wrapping' => false,
                'requires_special_handling' => false,
                'handling_equipment' => 'forklift',

                // Environmental characteristics
                'is_weatherproof' => false,
                'is_moisture_resistant' => false,
                'is_uv_resistant' => false,
                'min_temperature_celsius' => -40.00,
                'max_temperature_celsius' => 60.00,
                'requires_ventilation' => false,
                'requires_humidity_control' => false,

                // Cargo compatibility
                'suitable_for_food' => true,
                'suitable_for_liquids' => false,
                'suitable_for_powders' => false,
                'suitable_for_chemicals' => false,
                'suitable_for_dangerous_goods' => false,
                'suitable_for_fragile_items' => true,
                'suitable_for_heavy_items' => true,
                'suitable_for_bulk_cargo' => false,

                // Protection characteristics
                'provides_cushioning' => false,
                'provides_impact_protection' => false,
                'provides_theft_protection' => false,
                'is_tamper_evident' => false,
                'is_child_resistant' => false,
                'is_hermetic' => false,

                // Sustainability and recycling
                'is_recyclable' => true,
                'is_biodegradable' => true,
                'is_compostable' => false,
                'contains_recycled_material' => false,
                'recycled_content_percent' => null,
                'disposal_instructions' => 'Reciclar en centros especializados en madera',

                // Economic factors
                'unit_cost' => 15.00,
                'cost_per_kg' => 0.60,
                'cost_per_m3' => 83.33,
                'cost_varies_by_quantity' => true,
                'minimum_order_quantity' => 10,

                // Regulatory and certification
                'fda_approved' => false,
                'food_contact_safe' => true,
                'pharmaceutical_grade' => false,
                'certifications' => json_encode(['ISPM-15']),
                'regulatory_compliance' => json_encode(['ISPM-15', 'NIMF-15']),

                // Labeling and marking
                'requires_labeling' => true,
                'allows_printing' => true,
                'requires_hazmat_marking' => false,
                'required_markings' => json_encode(['ISPM-15', 'País de origen']),
                'prohibited_markings' => null,

                // Webservice integration
                'argentina_ws_code' => 'PAL',
                'paraguay_ws_code' => 'PAL',
                'customs_code' => '441010',
                'senasa_code' => null,
                'webservice_mapping' => json_encode(['type' => 'platform', 'material' => 'wood']),

                // Industry-specific data
                'industry_applications' => json_encode(['logistics', 'agriculture', 'manufacturing']),
                'commodity_compatibility' => json_encode(['general_cargo', 'agricultural_products']),
                'seasonal_considerations' => json_encode(['humidity_control_required']),

                // Quality and testing
                'requires_testing' => false,
                'testing_frequency_days' => null,
                'quality_standards' => json_encode(['ISPM-15', 'EPAL']),
                'acceptable_defect_rate_percent' => 2.00,

                // Availability and supply chain
                'widely_available' => true,
                'typical_lead_time_days' => 3,
                'preferred_suppliers' => json_encode(['Local wood suppliers']),
                'alternative_types' => json_encode(['Plastic pallets', 'Metal pallets']),

                // Status and display
                'active' => true,
                'is_standard' => true,
                'is_common' => true,
                'is_specialized' => false,
                'is_deprecated' => false,
                'display_order' => 10,
                'icon' => 'fas fa-pallet',
                'color_code' => '#8B4513',

                // Audit trail
                'created_date' => $now,
                'created_by_user_id' => null,
            ],

            // 2. CAJAS DE CARTÓN
            [
                // Basic identification
                'code' => 'BOX001',
                'name' => 'Cajas de Cartón Corrugado',
                'short_name' => 'Cajas',
                'description' => 'Embalaje de cartón corrugado para mercadería general y alimentos',

                // International classification
                'unece_code' => 'BX',
                'iso_code' => '4G',
                'imdg_code' => null,

                // Packaging category
                'category' => 'box',
                'material_type' => 'cardboard',

                // Physical specifications
                'length_mm' => 400.00,
                'width_mm' => 300.00,
                'height_mm' => 200.00,
                'diameter_mm' => null,
                'volume_liters' => 24.00,
                'volume_m3' => 0.024,

                // Weight specifications
                'empty_weight_kg' => 0.50,
                'max_gross_weight_kg' => 25.00,
                'max_net_weight_kg' => 24.50,
                'weight_tolerance_percent' => 5.00,

                // Structural characteristics
                'is_stackable' => true,
                'max_stack_height' => 20,
                'stacking_weight_limit_kg' => 500.00,
                'is_reusable' => false,
                'is_returnable' => false,
                'is_collapsible' => true,

                // Handling characteristics
                'requires_palletizing' => true,
                'requires_strapping' => false,
                'requires_wrapping' => false,
                'requires_special_handling' => false,
                'handling_equipment' => 'manual',

                // Environmental characteristics
                'is_weatherproof' => false,
                'is_moisture_resistant' => false,
                'is_uv_resistant' => false,
                'min_temperature_celsius' => -10.00,
                'max_temperature_celsius' => 50.00,
                'requires_ventilation' => true,
                'requires_humidity_control' => true,

                // Cargo compatibility
                'suitable_for_food' => true,
                'suitable_for_liquids' => false,
                'suitable_for_powders' => true,
                'suitable_for_chemicals' => false,
                'suitable_for_dangerous_goods' => false,
                'suitable_for_fragile_items' => true,
                'suitable_for_heavy_items' => false,
                'suitable_for_bulk_cargo' => false,

                // Protection characteristics
                'provides_cushioning' => true,
                'provides_impact_protection' => true,
                'provides_theft_protection' => false,
                'is_tamper_evident' => false,
                'is_child_resistant' => false,
                'is_hermetic' => false,

                // Sustainability and recycling
                'is_recyclable' => true,
                'is_biodegradable' => true,
                'is_compostable' => true,
                'contains_recycled_material' => true,
                'recycled_content_percent' => 80.00,
                'disposal_instructions' => 'Reciclar en contenedores de papel y cartón',

                // Economic factors
                'unit_cost' => 2.50,
                'cost_per_kg' => 5.00,
                'cost_per_m3' => 104.17,
                'cost_varies_by_quantity' => true,
                'minimum_order_quantity' => 100,

                // Regulatory and certification
                'fda_approved' => true,
                'food_contact_safe' => true,
                'pharmaceutical_grade' => false,
                'certifications' => json_encode(['FDA', 'FSC']),
                'regulatory_compliance' => json_encode(['FDA', 'CE', 'MERCOSUR']),

                // Labeling and marking
                'requires_labeling' => true,
                'allows_printing' => true,
                'requires_hazmat_marking' => false,
                'required_markings' => json_encode(['Recycling symbol', 'Food safe']),
                'prohibited_markings' => json_encode(['Heavy metals inks']),

                // Webservice integration
                'argentina_ws_code' => 'CX',
                'paraguay_ws_code' => 'CX',
                'customs_code' => '481910',
                'senasa_code' => 'CART001',
                'webservice_mapping' => json_encode(['type' => 'box', 'material' => 'cardboard']),

                // Industry-specific data
                'industry_applications' => json_encode(['food', 'pharmaceuticals', 'consumer_goods']),
                'commodity_compatibility' => json_encode(['dry_goods', 'packaged_food', 'electronics']),
                'seasonal_considerations' => json_encode(['avoid_high_humidity', 'protect_from_rain']),

                // Quality and testing
                'requires_testing' => true,
                'testing_frequency_days' => 90,
                'quality_standards' => json_encode(['ISTA', 'TAPPI']),
                'acceptable_defect_rate_percent' => 1.00,

                // Availability and supply chain
                'widely_available' => true,
                'typical_lead_time_days' => 7,
                'preferred_suppliers' => json_encode(['Corrugated manufacturers']),
                'alternative_types' => json_encode(['Plastic boxes', 'Wooden crates']),

                // Status and display
                'active' => true,
                'is_standard' => true,
                'is_common' => true,
                'is_specialized' => false,
                'is_deprecated' => false,
                'display_order' => 20,
                'icon' => 'fas fa-box',
                'color_code' => '#D2691E',

                // Audit trail
                'created_date' => $now,
                'created_by_user_id' => null,
            ],

            // 3. SACOS/BOLSAS
            [
                // Basic identification
                'code' => 'BAG001',
                'name' => 'Sacos de Polipropileno',
                'short_name' => 'Sacos',
                'description' => 'Bolsas de polipropileno tejido para graneles y productos agrícolas',

                // International classification
                'unece_code' => 'SA',
                'iso_code' => '5H3',
                'imdg_code' => null,

                // Packaging category
                'category' => 'bag',
                'material_type' => 'fabric',

                // Physical specifications
                'length_mm' => 600.00,
                'width_mm' => 400.00,
                'height_mm' => 100.00,
                'diameter_mm' => null,
                'volume_liters' => 50.00,
                'volume_m3' => 0.050,

                // Weight specifications
                'empty_weight_kg' => 0.20,
                'max_gross_weight_kg' => 50.00,
                'max_net_weight_kg' => 49.80,
                'weight_tolerance_percent' => 5.00,

                // Structural characteristics
                'is_stackable' => true,
                'max_stack_height' => 15,
                'stacking_weight_limit_kg' => 750.00,
                'is_reusable' => true,
                'is_returnable' => false,
                'is_collapsible' => true,

                // Handling characteristics
                'requires_palletizing' => true,
                'requires_strapping' => false,
                'requires_wrapping' => false,
                'requires_special_handling' => false,
                'handling_equipment' => 'manual',

                // Environmental characteristics
                'is_weatherproof' => false,
                'is_moisture_resistant' => true,
                'is_uv_resistant' => true,
                'min_temperature_celsius' => -20.00,
                'max_temperature_celsius' => 80.00,
                'requires_ventilation' => true,
                'requires_humidity_control' => false,

                // Cargo compatibility
                'suitable_for_food' => true,
                'suitable_for_liquids' => false,
                'suitable_for_powders' => true,
                'suitable_for_chemicals' => false,
                'suitable_for_dangerous_goods' => false,
                'suitable_for_fragile_items' => false,
                'suitable_for_heavy_items' => true,
                'suitable_for_bulk_cargo' => true,

                // Protection characteristics
                'provides_cushioning' => false,
                'provides_impact_protection' => false,
                'provides_theft_protection' => false,
                'is_tamper_evident' => false,
                'is_child_resistant' => false,
                'is_hermetic' => false,

                // Sustainability and recycling
                'is_recyclable' => true,
                'is_biodegradable' => false,
                'is_compostable' => false,
                'contains_recycled_material' => true,
                'recycled_content_percent' => 30.00,
                'disposal_instructions' => 'Reciclar en centros especializados en plásticos',

                // Economic factors
                'unit_cost' => 1.20,
                'cost_per_kg' => 6.00,
                'cost_per_m3' => 24.00,
                'cost_varies_by_quantity' => true,
                'minimum_order_quantity' => 500,

                // Regulatory and certification
                'fda_approved' => true,
                'food_contact_safe' => true,
                'pharmaceutical_grade' => false,
                'certifications' => json_encode(['FDA', 'BRC']),
                'regulatory_compliance' => json_encode(['FDA', 'EU_10-2011']),

                // Labeling and marking
                'requires_labeling' => true,
                'allows_printing' => true,
                'requires_hazmat_marking' => false,
                'required_markings' => json_encode(['Recycling code', 'Food grade']),
                'prohibited_markings' => null,

                // Webservice integration
                'argentina_ws_code' => 'SA',
                'paraguay_ws_code' => 'SA',
                'customs_code' => '630532',
                'senasa_code' => 'SACO001',
                'webservice_mapping' => json_encode(['type' => 'bag', 'material' => 'woven_plastic']),

                // Industry-specific data
                'industry_applications' => json_encode(['agriculture', 'food_processing', 'construction']),
                'commodity_compatibility' => json_encode(['grains', 'flour', 'sugar', 'cement']),
                'seasonal_considerations' => json_encode(['protect_from_rodents', 'moisture_control']),

                // Quality and testing
                'requires_testing' => true,
                'testing_frequency_days' => 180,
                'quality_standards' => json_encode(['ASTM_D5034', 'ISO_9073']),
                'acceptable_defect_rate_percent' => 2.00,

                // Availability and supply chain
                'widely_available' => true,
                'typical_lead_time_days' => 14,
                'preferred_suppliers' => json_encode(['Plastic weavers']),
                'alternative_types' => json_encode(['Paper bags', 'Jute bags']),

                // Status and display
                'active' => true,
                'is_standard' => true,
                'is_common' => true,
                'is_specialized' => false,
                'is_deprecated' => false,
                'display_order' => 30,
                'icon' => 'fas fa-shopping-bag',
                'color_code' => '#32CD32',

                // Audit trail
                'created_date' => $now,
                'created_by_user_id' => null,
            ],

            // 4. TAMBORES METÁLICOS
            [
                // Basic identification
                'code' => 'DRM001',
                'name' => 'Tambores Metálicos',
                'short_name' => 'Tambores',
                'description' => 'Contenedores cilíndricos metálicos para líquidos y sustancias peligrosas',

                // International classification
                'unece_code' => 'DR',
                'iso_code' => '1A1',
                'imdg_code' => '1A1',

                // Packaging category
                'category' => 'drum',
                'material_type' => 'metal',

                // Physical specifications
                'length_mm' => null,
                'width_mm' => null,
                'height_mm' => 900.00,
                'diameter_mm' => 570.00,
                'volume_liters' => 200.00,
                'volume_m3' => 0.200,

                // Weight specifications
                'empty_weight_kg' => 18.00,
                'max_gross_weight_kg' => 250.00,
                'max_net_weight_kg' => 232.00,
                'weight_tolerance_percent' => 3.00,

                // Structural characteristics
                'is_stackable' => true,
                'max_stack_height' => 3,
                'stacking_weight_limit_kg' => 750.00,
                'is_reusable' => true,
                'is_returnable' => true,
                'is_collapsible' => false,

                // Handling characteristics
                'requires_palletizing' => false,
                'requires_strapping' => false,
                'requires_wrapping' => false,
                'requires_special_handling' => true,
                'handling_equipment' => 'forklift',

                // Environmental characteristics
                'is_weatherproof' => true,
                'is_moisture_resistant' => true,
                'is_uv_resistant' => true,
                'min_temperature_celsius' => -40.00,
                'max_temperature_celsius' => 200.00,
                'requires_ventilation' => false,
                'requires_humidity_control' => false,

                // Cargo compatibility
                'suitable_for_food' => false,
                'suitable_for_liquids' => true,
                'suitable_for_powders' => true,
                'suitable_for_chemicals' => true,
                'suitable_for_dangerous_goods' => true,
                'suitable_for_fragile_items' => false,
                'suitable_for_heavy_items' => true,
                'suitable_for_bulk_cargo' => false,

                // Protection characteristics
                'provides_cushioning' => false,
                'provides_impact_protection' => true,
                'provides_theft_protection' => true,
                'is_tamper_evident' => true,
                'is_child_resistant' => false,
                'is_hermetic' => true,

                // Sustainability and recycling
                'is_recyclable' => true,
                'is_biodegradable' => false,
                'is_compostable' => false,
                'contains_recycled_material' => true,
                'recycled_content_percent' => 50.00,
                'disposal_instructions' => 'Limpiar completamente antes de reciclar como metal',

                // Economic factors
                'unit_cost' => 45.00,
                'cost_per_kg' => 2.50,
                'cost_per_m3' => 225.00,
                'cost_varies_by_quantity' => true,
                'minimum_order_quantity' => 50,

                // Regulatory and certification
                'fda_approved' => false,
                'food_contact_safe' => false,
                'pharmaceutical_grade' => false,
                'certifications' => json_encode(['UN_SPEC', 'DOT']),
                'regulatory_compliance' => json_encode(['ADR', 'IMDG', 'UN_SPEC']),

                // Labeling and marking
                'requires_labeling' => true,
                'allows_printing' => false,
                'requires_hazmat_marking' => true,
                'required_markings' => json_encode(['UN_specification', 'Hazmat_placards']),
                'prohibited_markings' => json_encode(['Food_safe_symbols']),

                // Webservice integration
                'argentina_ws_code' => 'DR',
                'paraguay_ws_code' => 'DR',
                'customs_code' => '732111',
                'senasa_code' => null,
                'webservice_mapping' => json_encode(['type' => 'drum', 'material' => 'steel', 'dangerous' => true]),

                // Industry-specific data
                'industry_applications' => json_encode(['chemical', 'petroleum', 'industrial']),
                'commodity_compatibility' => json_encode(['chemicals', 'oils', 'solvents']),
                'seasonal_considerations' => json_encode(['temperature_expansion', 'pressure_monitoring']),

                // Quality and testing
                'requires_testing' => true,
                'testing_frequency_days' => 365,
                'quality_standards' => json_encode(['UN_SPEC', 'ASTM_D5276']),
                'acceptable_defect_rate_percent' => 0.50,

                // Availability and supply chain
                'widely_available' => true,
                'typical_lead_time_days' => 21,
                'preferred_suppliers' => json_encode(['Steel drum manufacturers']),
                'alternative_types' => json_encode(['Plastic drums', 'Fiber drums']),

                // Status and display
                'active' => true,
                'is_standard' => true,
                'is_common' => true,
                'is_specialized' => true,
                'is_deprecated' => false,
                'display_order' => 40,
                'icon' => 'fas fa-drum',
                'color_code' => '#708090',

                // Audit trail
                'created_date' => $now,
                'created_by_user_id' => null,
            ],

            // 5. CONTENEDORES PLÁSTICOS
            [
                // Basic identification
                'code' => 'CTR001',
                'name' => 'Contenedores Plásticos Reutilizables',
                'short_name' => 'Contenedores',
                'description' => 'Contenedores plásticos reutilizables para almacenamiento y transporte',

                // International classification
                'unece_code' => 'CT',
                'iso_code' => '3H1',
                'imdg_code' => null,

                // Packaging category
                'category' => 'container',
                'material_type' => 'plastic',

                // Physical specifications
                'length_mm' => 800.00,
                'width_mm' => 600.00,
                'height_mm' => 400.00,
                'diameter_mm' => null,
                'volume_liters' => 180.00,
                'volume_m3' => 0.180,

                // Weight specifications
                'empty_weight_kg' => 8.00,
                'max_gross_weight_kg' => 100.00,
                'max_net_weight_kg' => 92.00,
                'weight_tolerance_percent' => 5.00,

                // Structural characteristics
                'is_stackable' => true,
                'max_stack_height' => 8,
                'stacking_weight_limit_kg' => 800.00,
                'is_reusable' => true,
                'is_returnable' => true,
                'is_collapsible' => false,

                // Handling characteristics
                'requires_palletizing' => false,
                'requires_strapping' => false,
                'requires_wrapping' => false,
                'requires_special_handling' => false,
                'handling_equipment' => 'manual',

                // Environmental characteristics
                'is_weatherproof' => true,
                'is_moisture_resistant' => true,
                'is_uv_resistant' => true,
                'min_temperature_celsius' => -30.00,
                'max_temperature_celsius' => 60.00,
                'requires_ventilation' => false,
                'requires_humidity_control' => false,

                // Cargo compatibility
                'suitable_for_food' => true,
                'suitable_for_liquids' => false,
                'suitable_for_powders' => true,
                'suitable_for_chemicals' => false,
                'suitable_for_dangerous_goods' => false,
                'suitable_for_fragile_items' => true,
                'suitable_for_heavy_items' => true,
                'suitable_for_bulk_cargo' => false,

                // Protection characteristics
                'provides_cushioning' => false,
                'provides_impact_protection' => true,
                'provides_theft_protection' => false,
                'is_tamper_evident' => false,
                'is_child_resistant' => false,
                'is_hermetic' => false,

                // Sustainability and recycling
                'is_recyclable' => true,
                'is_biodegradable' => false,
                'is_compostable' => false,
                'contains_recycled_material' => true,
                'recycled_content_percent' => 25.00,
                'disposal_instructions' => 'Reciclar en contenedores de plástico',

                // Economic factors
                'unit_cost' => 25.00,
                'cost_per_kg' => 3.13,
                'cost_per_m3' => 138.89,
                'cost_varies_by_quantity' => true,
                'minimum_order_quantity' => 20,

                // Regulatory and certification
                'fda_approved' => true,
                'food_contact_safe' => true,
                'pharmaceutical_grade' => false,
                'certifications' => json_encode(['FDA', 'HACCP']),
                'regulatory_compliance' => json_encode(['FDA', 'EU_10-2011']),

                // Labeling and marking
                'requires_labeling' => true,
                'allows_printing' => true,
                'requires_hazmat_marking' => false,
                'required_markings' => json_encode(['Recycling_code', 'Food_safe']),
                'prohibited_markings' => null,

                // Webservice integration
                'argentina_ws_code' => 'CT',
                'paraguay_ws_code' => 'CT',
                'customs_code' => '392690',
                'senasa_code' => 'CONT001',
                'webservice_mapping' => json_encode(['type' => 'container', 'material' => 'plastic']),

                // Industry-specific data
                'industry_applications' => json_encode(['food_service', 'retail', 'logistics']),
                'commodity_compatibility' => json_encode(['food_products', 'consumer_goods']),
                'seasonal_considerations' => null,

                // Quality and testing
                'requires_testing' => false,
                'testing_frequency_days' => null,
                'quality_standards' => json_encode(['ISO_12048']),
                'acceptable_defect_rate_percent' => 2.00,

                // Availability and supply chain
                'widely_available' => true,
                'typical_lead_time_days' => 10,
                'preferred_suppliers' => json_encode(['Plastic container manufacturers']),
                'alternative_types' => json_encode(['Metal containers', 'Wooden crates']),

                // Status and display
                'active' => true,
                'is_standard' => true,
                'is_common' => true,
                'is_specialized' => false,
                'is_deprecated' => false,
                'display_order' => 50,
                'icon' => 'fas fa-archive',
                'color_code' => '#4169E1',

                // Audit trail
                'created_date' => $now,
                'created_by_user_id' => null,
            ],

            // 6. FARDOS
            [
                // Basic identification
                'code' => 'BND001',
                'name' => 'Fardos Textiles',
                'short_name' => 'Fardos',
                'description' => 'Mercadería textil o fibras atadas en forma de fardo compacto',

                // International classification
                'unece_code' => 'BD',
                'iso_code' => 'BD',
                'imdg_code' => null,

                // Packaging category
                'category' => 'bundle',
                'material_type' => 'fabric',

                // Physical specifications
                'length_mm' => 1000.00,
                'width_mm' => 500.00,
                'height_mm' => 300.00,
                'diameter_mm' => null,
                'volume_liters' => 150.00,
                'volume_m3' => 0.150,

                // Weight specifications
                'empty_weight_kg' => 2.00,
                'max_gross_weight_kg' => 80.00,
                'max_net_weight_kg' => 78.00,
                'weight_tolerance_percent' => 5.00,

                // Structural characteristics
                'is_stackable' => true,
                'max_stack_height' => 5,
                'stacking_weight_limit_kg' => 400.00,
                'is_reusable' => false,
                'is_returnable' => false,
                'is_collapsible' => false,

                // Handling characteristics
                'requires_palletizing' => true,
                'requires_strapping' => true,
                'requires_wrapping' => true,
                'requires_special_handling' => false,
                'handling_equipment' => 'forklift',

                // Environmental characteristics
                'is_weatherproof' => false,
                'is_moisture_resistant' => false,
                'is_uv_resistant' => false,
                'min_temperature_celsius' => 0.00,
                'max_temperature_celsius' => 40.00,
                'requires_ventilation' => true,
                'requires_humidity_control' => true,

                // Cargo compatibility
                'suitable_for_food' => false,
                'suitable_for_liquids' => false,
                'suitable_for_powders' => false,
                'suitable_for_chemicals' => false,
                'suitable_for_dangerous_goods' => false,
                'suitable_for_fragile_items' => false,
                'suitable_for_heavy_items' => false,
                'suitable_for_bulk_cargo' => true,

                // Protection characteristics
                'provides_cushioning' => true,
                'provides_impact_protection' => false,
                'provides_theft_protection' => false,
                'is_tamper_evident' => false,
                'is_child_resistant' => false,
                'is_hermetic' => false,

                // Sustainability and recycling
                'is_recyclable' => true,
                'is_biodegradable' => true,
                'is_compostable' => true,
                'contains_recycled_material' => false,
                'recycled_content_percent' => null,
                'disposal_instructions' => 'Separar materiales para reciclaje textil',

                // Economic factors
                'unit_cost' => 5.00,
                'cost_per_kg' => 0.063,
                'cost_per_m3' => 33.33,
                'cost_varies_by_quantity' => true,
                'minimum_order_quantity' => 1,

                // Regulatory and certification
                'fda_approved' => false,
                'food_contact_safe' => false,
                'pharmaceutical_grade' => false,
                'certifications' => null,
                'regulatory_compliance' => json_encode(['MERCOSUR_textiles']),

                // Labeling and marking
                'requires_labeling' => true,
                'allows_printing' => false,
                'requires_hazmat_marking' => false,
                'required_markings' => json_encode(['Content_description', 'Weight']),
                'prohibited_markings' => null,

                // Webservice integration
                'argentina_ws_code' => 'BD',
                'paraguay_ws_code' => 'BD',
                'customs_code' => '630790',
                'senasa_code' => null,
                'webservice_mapping' => json_encode(['type' => 'bundle', 'material' => 'textile']),

                // Industry-specific data
                'industry_applications' => json_encode(['textile', 'fashion', 'recycling']),
                'commodity_compatibility' => json_encode(['cotton', 'wool', 'synthetic_fibers']),
                'seasonal_considerations' => json_encode(['protect_from_moisture', 'pest_control']),

                // Quality and testing
                'requires_testing' => false,
                'testing_frequency_days' => null,
                'quality_standards' => null,
                'acceptable_defect_rate_percent' => 5.00,

                // Availability and supply chain
                'widely_available' => true,
                'typical_lead_time_days' => 1,
                'preferred_suppliers' => json_encode(['Textile processors']),
                'alternative_types' => json_encode(['Compressed bales', 'Wrapped bundles']),

                // Status and display
                'active' => true,
                'is_standard' => true,
                'is_common' => true,
                'is_specialized' => false,
                'is_deprecated' => false,
                'display_order' => 60,
                'icon' => 'fas fa-layer-group',
                'color_code' => '#DDA0DD',

                // Audit trail
                'created_date' => $now,
                'created_by_user_id' => null,
            ],

            // 7. A GRANEL
            [
                // Basic identification
                'code' => 'BLK001',
                'name' => 'Carga a Granel',
                'short_name' => 'Granel',
                'description' => 'Mercadería transportada sin embalaje específico, directamente en bodega',

                // International classification
                'unece_code' => 'BK',
                'iso_code' => 'BK',
                'imdg_code' => null,

                // Packaging category
                'category' => 'bulk',
                'material_type' => 'other',

                // Physical specifications
                'length_mm' => null,
                'width_mm' => null,
                'height_mm' => null,
                'diameter_mm' => null,
                'volume_liters' => null,
                'volume_m3' => null,

                // Weight specifications
                'empty_weight_kg' => 0.00,
                'max_gross_weight_kg' => null,
                'max_net_weight_kg' => null,
                'weight_tolerance_percent' => 10.00,

                // Structural characteristics
                'is_stackable' => false,
                'max_stack_height' => null,
                'stacking_weight_limit_kg' => null,
                'is_reusable' => false,
                'is_returnable' => false,
                'is_collapsible' => false,

                // Handling characteristics
                'requires_palletizing' => false,
                'requires_strapping' => false,
                'requires_wrapping' => false,
                'requires_special_handling' => true,
                'handling_equipment' => 'specialized',

                // Environmental characteristics
                'is_weatherproof' => false,
                'is_moisture_resistant' => false,
                'is_uv_resistant' => false,
                'min_temperature_celsius' => null,
                'max_temperature_celsius' => null,
                'requires_ventilation' => true,
                'requires_humidity_control' => false,

                // Cargo compatibility
                'suitable_for_food' => true,
                'suitable_for_liquids' => false,
                'suitable_for_powders' => true,
                'suitable_for_chemicals' => false,
                'suitable_for_dangerous_goods' => false,
                'suitable_for_fragile_items' => false,
                'suitable_for_heavy_items' => true,
                'suitable_for_bulk_cargo' => true,

                // Protection characteristics
                'provides_cushioning' => false,
                'provides_impact_protection' => false,
                'provides_theft_protection' => false,
                'is_tamper_evident' => false,
                'is_child_resistant' => false,
                'is_hermetic' => false,

                // Sustainability and recycling
                'is_recyclable' => false,
                'is_biodegradable' => false,
                'is_compostable' => false,
                'contains_recycled_material' => false,
                'recycled_content_percent' => null,
                'disposal_instructions' => 'Sin embalaje para disposición',

                // Economic factors
                'unit_cost' => 0.00,
                'cost_per_kg' => 0.00,
                'cost_per_m3' => 0.00,
                'cost_varies_by_quantity' => false,
                'minimum_order_quantity' => 1,

                // Regulatory and certification
                'fda_approved' => false,
                'food_contact_safe' => true,
                'pharmaceutical_grade' => false,
                'certifications' => null,
                'regulatory_compliance' => json_encode(['Bulk_cargo_standards']),

                // Labeling and marking
                'requires_labeling' => false,
                'allows_printing' => false,
                'requires_hazmat_marking' => false,
                'required_markings' => null,
                'prohibited_markings' => null,

                // Webservice integration
                'argentina_ws_code' => 'BK',
                'paraguay_ws_code' => 'BK',
                'customs_code' => null,
                'senasa_code' => 'GRANEL',
                'webservice_mapping' => json_encode(['type' => 'bulk', 'material' => 'none']),

                // Industry-specific data
                'industry_applications' => json_encode(['agriculture', 'mining', 'commodities']),
                'commodity_compatibility' => json_encode(['grains', 'coal', 'iron_ore', 'soybeans']),
                'seasonal_considerations' => json_encode(['weather_protection', 'fumigation_requirements']),

                // Quality and testing
                'requires_testing' => false,
                'testing_frequency_days' => null,
                'quality_standards' => json_encode(['IMO_bulk_codes']),
                'acceptable_defect_rate_percent' => 10.00,

                // Availability and supply chain
                'widely_available' => true,
                'typical_lead_time_days' => 0,
                'preferred_suppliers' => null,
                'alternative_types' => json_encode(['Containerized_bulk', 'Bagged_bulk']),

                // Status and display
                'active' => true,
                'is_standard' => true,
                'is_common' => true,
                'is_specialized' => true,
                'is_deprecated' => false,
                'display_order' => 70,
                'icon' => 'fas fa-mountain',
                'color_code' => '#8B4513',

                // Audit trail
                'created_date' => $now,
                'created_by_user_id' => null,
            ],

            // 8. ROLLOS
            [
                // Basic identification
                'code' => 'ROL001',
                'name' => 'Rollos de Papel',
                'short_name' => 'Rollos',
                'description' => 'Papel, cartón o material similar enrollado en bobinas',

                // International classification
                'unece_code' => 'RO',
                'iso_code' => 'RO',
                'imdg_code' => null,

                // Packaging category
                'category' => 'roll',
                'material_type' => 'paper',

                // Physical specifications
                'length_mm' => 1200.00,
                'width_mm' => null,
                'height_mm' => null,
                'diameter_mm' => 800.00,
                'volume_liters' => 600.00,
                'volume_m3' => 0.600,

                // Weight specifications
                'empty_weight_kg' => 5.00,
                'max_gross_weight_kg' => 500.00,
                'max_net_weight_kg' => 495.00,
                'weight_tolerance_percent' => 5.00,

                // Structural characteristics
                'is_stackable' => false,
                'max_stack_height' => null,
                'stacking_weight_limit_kg' => null,
                'is_reusable' => false,
                'is_returnable' => false,
                'is_collapsible' => false,

                // Handling characteristics
                'requires_palletizing' => true,
                'requires_strapping' => true,
                'requires_wrapping' => true,
                'requires_special_handling' => true,
                'handling_equipment' => 'crane',

                // Environmental characteristics
                'is_weatherproof' => false,
                'is_moisture_resistant' => false,
                'is_uv_resistant' => false,
                'min_temperature_celsius' => 5.00,
                'max_temperature_celsius' => 35.00,
                'requires_ventilation' => true,
                'requires_humidity_control' => true,

                // Cargo compatibility
                'suitable_for_food' => false,
                'suitable_for_liquids' => false,
                'suitable_for_powders' => false,
                'suitable_for_chemicals' => false,
                'suitable_for_dangerous_goods' => false,
                'suitable_for_fragile_items' => true,
                'suitable_for_heavy_items' => true,
                'suitable_for_bulk_cargo' => false,

                // Protection characteristics
                'provides_cushioning' => false,
                'provides_impact_protection' => false,
                'provides_theft_protection' => false,
                'is_tamper_evident' => false,
                'is_child_resistant' => false,
                'is_hermetic' => false,

                // Sustainability and recycling
                'is_recyclable' => true,
                'is_biodegradable' => true,
                'is_compostable' => true,
                'contains_recycled_material' => true,
                'recycled_content_percent' => 60.00,
                'disposal_instructions' => 'Reciclar en contenedores de papel',

                // Economic factors
                'unit_cost' => 15.00,
                'cost_per_kg' => 0.03,
                'cost_per_m3' => 25.00,
                'cost_varies_by_quantity' => true,
                'minimum_order_quantity' => 10,

                // Regulatory and certification
                'fda_approved' => false,
                'food_contact_safe' => false,
                'pharmaceutical_grade' => false,
                'certifications' => json_encode(['FSC', 'PEFC']),
                'regulatory_compliance' => json_encode(['Forest_certification']),

                // Labeling and marking
                'requires_labeling' => true,
                'allows_printing' => false,
                'requires_hazmat_marking' => false,
                'required_markings' => json_encode(['Roll_specifications', 'Handling_instructions']),
                'prohibited_markings' => null,

                // Webservice integration
                'argentina_ws_code' => 'RO',
                'paraguay_ws_code' => 'RO',
                'customs_code' => '481092',
                'senasa_code' => null,
                'webservice_mapping' => json_encode(['type' => 'roll', 'material' => 'paper']),

                // Industry-specific data
                'industry_applications' => json_encode(['printing', 'packaging', 'construction']),
                'commodity_compatibility' => json_encode(['newsprint', 'kraft_paper', 'cardboard']),
                'seasonal_considerations' => json_encode(['moisture_protection', 'temperature_control']),

                // Quality and testing
                'requires_testing' => false,
                'testing_frequency_days' => null,
                'quality_standards' => json_encode(['TAPPI_standards']),
                'acceptable_defect_rate_percent' => 3.00,

                // Availability and supply chain
                'widely_available' => true,
                'typical_lead_time_days' => 5,
                'preferred_suppliers' => json_encode(['Paper mills']),
                'alternative_types' => json_encode(['Sheet_format', 'Cut_sizes']),

                // Status and display
                'active' => true,
                'is_standard' => true,
                'is_common' => true,
                'is_specialized' => false,
                'is_deprecated' => false,
                'display_order' => 80,
                'icon' => 'fas fa-scroll',
                'color_code' => '#F5F5DC',

                // Audit trail
                'created_date' => $now,
                'created_by_user_id' => null,
            ],
        ];

        // Insertar todos los registros
        DB::table('packaging_types')->insert($packagingTypes);

        $this->command->info('✅ Tipos de embalaje creados exitosamente');
        $this->command->info('📦 Total de tipos creados: ' . count($packagingTypes));
        $this->command->line('');
        $this->command->line('Tipos creados:');
        foreach ($packagingTypes as $type) {
            $this->command->line("  - {$type['code']}: {$type['name']}");
        }
    }
}