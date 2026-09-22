<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('container_types')) {
            return;
        }

        if (
            DB::table('container_types')->where('code', '45HC')->exists()
            || DB::table('container_types')->where('iso_code', 'L5G1')->exists()
        ) {
            return;
        }

        DB::table('container_types')->insert([
            'code' => '45HC',
            'name' => 'Contenedor 45 pies High Cube',
            'short_name' => '45HC',
            'description' => 'Contenedor General Purpose de 45 pies High Cube (ISO L5G1)',
            'iso_code' => 'L5G1',
            'iso_size_type' => 'L5G1',
            'iso_group' => 'high_cube',
            'length_feet' => '45',
            'width_feet' => '8',
            'height_feet' => '9.5',
            'length_mm' => 13716.00,
            'width_mm' => 2438.00,
            'height_mm' => 2896.00,
            'internal_length_mm' => 13556.00,
            'internal_width_mm' => 2352.00,
            'internal_height_mm' => 2698.00,
            'tare_weight_kg' => 4800.00,
            'max_gross_weight_kg' => 30480.00,
            'max_payload_kg' => 25680.00,
            'internal_volume_m3' => 86.00,
            'loading_volume_m3' => 85.00,
            'category' => 'dry_cargo',
            'is_refrigerated' => false,
            'is_heated' => false,
            'is_insulated' => false,
            'is_ventilated' => false,
            'has_electrical_supply' => false,
            'has_roof' => true,
            'has_sidewalls' => true,
            'has_end_walls' => true,
            'has_doors' => true,
            'has_removable_top' => false,
            'has_folding_sides' => false,
            'door_type' => 'standard',
            'door_width_mm' => 2340.00,
            'door_height_mm' => 2585.00,
            'min_temperature_celsius' => null,
            'max_temperature_celsius' => null,
            'has_humidity_control' => false,
            'has_atmosphere_control' => false,
            'suitable_for_dangerous_goods' => false,
            'suitable_for_food' => true,
            'suitable_for_chemicals' => false,
            'suitable_for_liquids' => false,
            'suitable_for_bulk_cargo' => false,
            'suitable_for_heavy_cargo' => true,
            'suitable_for_oversized_cargo' => true,
            'requires_special_handling' => false,
            'requires_power_supply' => false,
            'requires_ventilation' => false,
            'requires_monitoring' => false,
            'stackable' => true,
            'max_stack_height' => 7,
            'csc_certified' => true,
            'food_grade' => true,
            'pharmaceutical_grade' => false,
            'certifications' => json_encode(['CSC', 'ISO']),
            'typical_lifespan_years' => 15,
            'inspection_interval_months' => 12,
            'requires_pretrip_inspection' => true,
            'requires_cleaning' => true,
            'requires_fumigation' => false,
            'daily_rental_rate' => null,
            'purchase_price_estimate' => null,
            'maintenance_cost_per_year' => null,
            'argentina_ws_code' => '45HC',
            'paraguay_ws_code' => '45HC',
            'customs_code' => '8609001000',
            'webservice_mapping' => json_encode([
                'type' => 'GP',
                'size' => '45',
                'height' => 'High',
            ]),
            'allowed_conditions' => json_encode(['V', 'L', 'D']),
            'condition_descriptions' => 'V=Vacío, L=Lleno, D=Dañado',
            'compatible_vessel_types' => json_encode([
                'container_vessel',
                'multipurpose',
            ]),
            'restricted_ports' => null,
            'handling_equipment_required' => json_encode([
                'high_spreader',
                'crane',
            ]),
            'eco_friendly' => false,
            'carbon_footprint_kg' => null,
            'environmental_certifications' => null,
            'active' => true,
            'is_standard' => true,
            'is_common' => false,
            'is_specialized' => false,
            'display_order' => 31,
            'icon' => 'fas fa-cube',
            'color_code' => '#FF6347',
            'created_date' => now(),
            'created_by_user_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        if (!Schema::hasTable('container_types')) {
            return;
        }

        DB::table('container_types')
            ->where('code', '45HC')
            ->where('iso_code', 'L5G1')
            ->delete();
    }
};
