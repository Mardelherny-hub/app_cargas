<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class VesselTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * 
     * Seed para tipos de embarcación del sistema de cargas fluviales y marítimas
     * Incluye barcazas, remolcadores, autopropulsados, empujadores y mixtos
     * 
     * CORREGIDO: Usa nombres exactos de la migración create_vessel_types_table.php
     */
    public function run(): void
    {
        $this->command->info('🚢 Creando tipos de embarcación...');

        // Verificar si ya existen tipos de embarcación
        $existingCount = DB::table('vessel_types')->count();
        
        if ($existingCount > 0) {
            $this->command->warn("⚠️  Ya existen {$existingCount} tipos de embarcación.");
            $this->command->info('Saltando creación para evitar duplicados...');
            return;
        }

        $vesselTypes = $this->getVesselTypesData();

        foreach ($vesselTypes as $type) {
            DB::table('vessel_types')->insert(array_merge($type, [
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
                'created_date' => Carbon::now(),
            ]));
        }

        $this->command->info('✅ ' . count($vesselTypes) . ' tipos de embarcación creados exitosamente');
        
        // Mostrar estadísticas
        $stats = [
            'barge' => collect($vesselTypes)->where('category', 'barge')->count(),
            'tugboat' => collect($vesselTypes)->where('category', 'tugboat')->count(),
            'self_propelled' => collect($vesselTypes)->where('category', 'self_propelled')->count(),
            'pusher' => collect($vesselTypes)->where('category', 'pusher')->count(),
            'mixed' => collect($vesselTypes)->where('category', 'mixed')->count(),
        ];

        $this->command->table(
            ['Categoría', 'Cantidad'],
            [
                ['Barcazas', $stats['barge']],
                ['Remolcadores', $stats['tugboat']],
                ['Autopropulsados', $stats['self_propelled']],
                ['Empujadores', $stats['pusher']],
                ['Mixtos', $stats['mixed']],
            ]
        );
    }

    /**
     * Obtener datos de tipos de embarcación.
     * CORREGIDO: Nombres de columnas coinciden exactamente con la migración
     */
    private function getVesselTypesData(): array
    {
        return [
            // ========== BARCAZAS ==========
            [
                'code' => 'BARGE_STD_001',
                'name' => 'Barcaza Estándar de Contenedores',
                'short_name' => 'Barcaza Contenedores',
                'description' => 'Barcaza estándar diseñada específicamente para transporte de contenedores de 20\' y 40\'. Ideal para rutas fluviales principales.',
                'category' => 'barge',
                'propulsion_type' => 'pushed',
                
                // Physical specifications
                'min_length' => 80.0,
                'max_length' => 120.0,
                'min_beam' => 12.0,
                'max_beam' => 18.0,
                'min_draft' => 2.5,
                'max_draft' => 4.0,
                
                // Capacity specifications
                'min_cargo_capacity' => 800.0,
                'max_cargo_capacity' => 1500.0,
                'min_container_capacity' => 40,
                'max_container_capacity' => 120,
                'min_liquid_capacity' => null,
                'max_liquid_capacity' => null,
                
                // Operational characteristics
                'typical_crew_size' => 0,
                'max_crew_size' => 0,
                'typical_speed' => 0.0,
                'max_speed' => 0.0,
                'fuel_consumption_per_day' => null,
                
                // Cargo compatibility
                'handles_containers' => true,
                'handles_bulk_cargo' => false,
                'handles_general_cargo' => true,
                'handles_liquid_cargo' => false,
                'handles_dangerous_goods' => true,
                'handles_refrigerated_cargo' => false,
                'handles_oversized_cargo' => false,
                
                // Navigation capabilities
                'river_navigation' => true,
                'maritime_navigation' => true,
                'coastal_navigation' => false,
                'lake_navigation' => false,
                'min_water_depth' => 3.0,
                
                // Convoy capabilities
                'can_be_lead_vessel' => false,
                'can_be_in_convoy' => true,
                'can_push_barges' => false,
                'can_tow_barges' => false,
                'max_barges_in_convoy' => null,
                
                // Environmental and safety
                'requires_pilot' => false,
                'requires_tugboat_assistance' => false,
                'environmental_restrictions' => null,
                'seasonal_restrictions' => null,
                'weather_limitations' => null,
                
                // Documentation and certification
                'requires_special_permits' => false,
                'requires_insurance' => true,
                'requires_safety_certificate' => true,
                'required_certifications' => null,
                
                // International classifications
                'imo_type_code' => null,
                'inland_vessel_code' => 'BC001',
                'imdg_class' => null,
                
                // Webservice integration
                'argentina_ws_code' => 'BARCAZA_CONT',
                'paraguay_ws_code' => 'BC_001',
                'webservice_mapping' => null,
                
                // Economic and operational data
                'daily_charter_rate' => 1200.00,
                'fuel_cost_per_day' => null,
                'typical_voyage_duration' => 5,
                'loading_time_hours' => 8,
                'unloading_time_hours' => 8,
                
                // Port compatibility
                'compatible_ports' => null,
                'restricted_ports' => null,
                'preferred_berths' => null,
                
                // Maintenance and lifecycle
                'typical_lifespan_years' => 25,
                'maintenance_interval_days' => 180,
                'requires_dry_dock' => true,
                'dry_dock_interval_months' => 24,
                
                // Status and display
                'active' => true,
                'is_common' => true,
                'is_specialized' => false,
                'display_order' => 10,
                'icon' => 'ship-container',
                'color_code' => '#3B82F6',
            ],
            
            [
                'code' => 'BARGE_BULK_001',
                'name' => 'Barcaza de Carga a Granel',
                'short_name' => 'Barcaza Granel',
                'description' => 'Barcaza especializada en transporte de granos, minerales y otros productos a granel. Equipada con sistema de descarga neumática.',
                'category' => 'barge',
                'propulsion_type' => 'pushed',
                
                // Physical specifications
                'min_length' => 90.0,
                'max_length' => 140.0,
                'min_beam' => 15.0,
                'max_beam' => 22.0,
                'min_draft' => 3.0,
                'max_draft' => 5.0,
                
                // Capacity specifications
                'min_cargo_capacity' => 1200.0,
                'max_cargo_capacity' => 2500.0,
                'min_container_capacity' => null,
                'max_container_capacity' => null,
                'min_liquid_capacity' => null,
                'max_liquid_capacity' => null,
                
                // Operational characteristics
                'typical_crew_size' => 0,
                'max_crew_size' => 0,
                'typical_speed' => 0.0,
                'max_speed' => 0.0,
                'fuel_consumption_per_day' => null,
                
                // Cargo compatibility
                'handles_containers' => false,
                'handles_bulk_cargo' => true,
                'handles_general_cargo' => false,
                'handles_liquid_cargo' => false,
                'handles_dangerous_goods' => false,
                'handles_refrigerated_cargo' => false,
                'handles_oversized_cargo' => true,
                
                // Navigation capabilities
                'river_navigation' => true,
                'maritime_navigation' => true,
                'coastal_navigation' => false,
                'lake_navigation' => false,
                'min_water_depth' => 3.5,
                
                // Convoy capabilities
                'can_be_lead_vessel' => false,
                'can_be_in_convoy' => true,
                'can_push_barges' => false,
                'can_tow_barges' => false,
                'max_barges_in_convoy' => null,
                
                // Environmental and safety
                'requires_pilot' => false,
                'requires_tugboat_assistance' => false,
                'environmental_restrictions' => null,
                'seasonal_restrictions' => null,
                'weather_limitations' => null,
                
                // Documentation and certification
                'requires_special_permits' => false,
                'requires_insurance' => true,
                'requires_safety_certificate' => true,
                'required_certifications' => null,
                
                // International classifications
                'imo_type_code' => null,
                'inland_vessel_code' => 'BG001',
                'imdg_class' => null,
                
                // Webservice integration
                'argentina_ws_code' => 'BARCAZA_GRANEL',
                'paraguay_ws_code' => 'BG_001',
                'webservice_mapping' => null,
                
                // Economic and operational data
                'daily_charter_rate' => 1500.00,
                'fuel_cost_per_day' => null,
                'typical_voyage_duration' => 7,
                'loading_time_hours' => 12,
                'unloading_time_hours' => 10,
                
                // Port compatibility
                'compatible_ports' => null,
                'restricted_ports' => null,
                'preferred_berths' => null,
                
                // Maintenance and lifecycle
                'typical_lifespan_years' => 30,
                'maintenance_interval_days' => 120,
                'requires_dry_dock' => true,
                'dry_dock_interval_months' => 18,
                
                // Status and display
                'active' => true,
                'is_common' => true,
                'is_specialized' => true,
                'display_order' => 20,
                'icon' => 'ship-bulk',
                'color_code' => '#059669',
            ],
            
            [
                'code' => 'BARGE_TANK_001',
                'name' => 'Barcaza Tanque para Líquidos',
                'short_name' => 'Barcaza Tanque',
                'description' => 'Barcaza especializada para transporte de combustibles, aceites y productos químicos líquidos. Cumple estándares internacionales de seguridad.',
                'category' => 'barge',
                'propulsion_type' => 'pushed',
                
                // Physical specifications
                'min_length' => 70.0,
                'max_length' => 110.0,
                'min_beam' => 11.0,
                'max_beam' => 16.0,
                'min_draft' => 2.0,
                'max_draft' => 3.5,
                
                // Capacity specifications
                'min_cargo_capacity' => 600.0,
                'max_cargo_capacity' => 1200.0,
                'min_container_capacity' => null,
                'max_container_capacity' => null,
                'min_liquid_capacity' => 800.0,
                'max_liquid_capacity' => 1500.0,
                
                // Operational characteristics
                'typical_crew_size' => 0,
                'max_crew_size' => 0,
                'typical_speed' => 0.0,
                'max_speed' => 0.0,
                'fuel_consumption_per_day' => null,
                
                // Cargo compatibility
                'handles_containers' => false,
                'handles_bulk_cargo' => false,
                'handles_general_cargo' => false,
                'handles_liquid_cargo' => true,
                'handles_dangerous_goods' => true,
                'handles_refrigerated_cargo' => false,
                'handles_oversized_cargo' => false,
                
                // Navigation capabilities
                'river_navigation' => true,
                'maritime_navigation' => true,
                'coastal_navigation' => false,
                'lake_navigation' => false,
                'min_water_depth' => 2.5,
                
                // Convoy capabilities
                'can_be_lead_vessel' => false,
                'can_be_in_convoy' => true,
                'can_push_barges' => false,
                'can_tow_barges' => false,
                'max_barges_in_convoy' => null,
                
                // Environmental and safety
                'requires_pilot' => true,
                'requires_tugboat_assistance' => false,
                'environmental_restrictions' => null,
                'seasonal_restrictions' => null,
                'weather_limitations' => null,
                
                // Documentation and certification
                'requires_special_permits' => true,
                'requires_insurance' => true,
                'requires_safety_certificate' => true,
                'required_certifications' => null,
                
                // International classifications
                'imo_type_code' => null,
                'inland_vessel_code' => 'BT001',
                'imdg_class' => 'Class_3',
                
                // Webservice integration
                'argentina_ws_code' => 'BARCAZA_TANQUE',
                'paraguay_ws_code' => 'BT_001',
                'webservice_mapping' => null,
                
                // Economic and operational data
                'daily_charter_rate' => 1800.00,
                'fuel_cost_per_day' => null,
                'typical_voyage_duration' => 4,
                'loading_time_hours' => 6,
                'unloading_time_hours' => 6,
                
                // Port compatibility
                'compatible_ports' => null,
                'restricted_ports' => null,
                'preferred_berths' => null,
                
                // Maintenance and lifecycle
                'typical_lifespan_years' => 20,
                'maintenance_interval_days' => 90,
                'requires_dry_dock' => true,
                'dry_dock_interval_months' => 12,
                
                // Status and display
                'active' => true,
                'is_common' => false,
                'is_specialized' => true,
                'display_order' => 30,
                'icon' => 'ship-tank',
                'color_code' => '#DC2626',
            ],
            
            [
                'code' => 'BARGE_MULTI_001',
                'name' => 'Barcaza Multipropósito',
                'short_name' => 'Barcaza Multi',
                'description' => 'Barcaza versátil diseñada para transportar diferentes tipos de carga. Ideal para rutas con carga mixta y operaciones flexibles.',
                'category' => 'barge',
                'propulsion_type' => 'pushed',
                
                // Physical specifications
                'min_length' => 75.0,
                'max_length' => 115.0,
                'min_beam' => 13.0,
                'max_beam' => 17.0,
                'min_draft' => 2.5,
                'max_draft' => 4.0,
                
                // Capacity specifications
                'min_cargo_capacity' => 700.0,
                'max_cargo_capacity' => 1300.0,
                'min_container_capacity' => 20,
                'max_container_capacity' => 80,
                'min_liquid_capacity' => null,
                'max_liquid_capacity' => null,
                
                // Operational characteristics
                'typical_crew_size' => 0,
                'max_crew_size' => 0,
                'typical_speed' => 0.0,
                'max_speed' => 0.0,
                'fuel_consumption_per_day' => null,
                
                // Cargo compatibility
                'handles_containers' => true,
                'handles_bulk_cargo' => true,
                'handles_general_cargo' => true,
                'handles_liquid_cargo' => false,
                'handles_dangerous_goods' => false,
                'handles_refrigerated_cargo' => false,
                'handles_oversized_cargo' => true,
                
                // Navigation capabilities
                'river_navigation' => true,
                'maritime_navigation' => true,
                'coastal_navigation' => false,
                'lake_navigation' => false,
                'min_water_depth' => 3.0,
                
                // Convoy capabilities
                'can_be_lead_vessel' => false,
                'can_be_in_convoy' => true,
                'can_push_barges' => false,
                'can_tow_barges' => false,
                'max_barges_in_convoy' => null,
                
                // Environmental and safety
                'requires_pilot' => false,
                'requires_tugboat_assistance' => false,
                'environmental_restrictions' => null,
                'seasonal_restrictions' => null,
                'weather_limitations' => null,
                
                // Documentation and certification
                'requires_special_permits' => false,
                'requires_insurance' => true,
                'requires_safety_certificate' => true,
                'required_certifications' => null,
                
                // International classifications
                'imo_type_code' => null,
                'inland_vessel_code' => 'BM001',
                'imdg_class' => null,
                
                // Webservice integration
                'argentina_ws_code' => 'BARCAZA_MULTI',
                'paraguay_ws_code' => 'BM_001',
                'webservice_mapping' => null,
                
                // Economic and operational data
                'daily_charter_rate' => 1350.00,
                'fuel_cost_per_day' => null,
                'typical_voyage_duration' => 6,
                'loading_time_hours' => 10,
                'unloading_time_hours' => 10,
                
                // Port compatibility
                'compatible_ports' => null,
                'restricted_ports' => null,
                'preferred_berths' => null,
                
                // Maintenance and lifecycle
                'typical_lifespan_years' => 28,
                'maintenance_interval_days' => 180,
                'requires_dry_dock' => true,
                'dry_dock_interval_months' => 24,
                
                // Status and display
                'active' => true,
                'is_common' => true,
                'is_specialized' => false,
                'display_order' => 40,
                'icon' => 'ship-multi',
                'color_code' => '#7C3AED',
            ],

            // ========== REMOLCADORES ==========
            [
                'code' => 'TUG_HARBOR_001',
                'name' => 'Remolcador de Puerto',
                'short_name' => 'Remolcador Puerto',
                'description' => 'Remolcador potente diseñado para maniobras en puerto y asistencia a embarcaciones grandes. Alta maniobrabilidad en espacios reducidos.',
                'category' => 'tugboat',
                'propulsion_type' => 'self_propelled',
                
                // Physical specifications
                'min_length' => 25.0,
                'max_length' => 40.0,
                'min_beam' => 8.0,
                'max_beam' => 12.0,
                'min_draft' => 3.0,
                'max_draft' => 5.0,
                
                // Capacity specifications
                'min_cargo_capacity' => 50.0,
                'max_cargo_capacity' => 150.0,
                'min_container_capacity' => null,
                'max_container_capacity' => null,
                'min_liquid_capacity' => null,
                'max_liquid_capacity' => null,
                
                // Operational characteristics
                'typical_crew_size' => 8,
                'max_crew_size' => 12,
                'typical_speed' => 10.0,
                'max_speed' => 14.0,
                'fuel_consumption_per_day' => 2000,
                
                // Cargo compatibility
                'handles_containers' => false,
                'handles_bulk_cargo' => false,
                'handles_general_cargo' => false,
                'handles_liquid_cargo' => false,
                'handles_dangerous_goods' => false,
                'handles_refrigerated_cargo' => false,
                'handles_oversized_cargo' => false,
                
                // Navigation capabilities
                'river_navigation' => true,
                'maritime_navigation' => true,
                'coastal_navigation' => true,
                'lake_navigation' => false,
                'min_water_depth' => 4.0,
                
                // Convoy capabilities
                'can_be_lead_vessel' => true,
                'can_be_in_convoy' => true,
                'can_push_barges' => true,
                'can_tow_barges' => true,
                'max_barges_in_convoy' => 6,
                
                // Environmental and safety
                'requires_pilot' => false,
                'requires_tugboat_assistance' => false,
                'environmental_restrictions' => null,
                'seasonal_restrictions' => null,
                'weather_limitations' => null,
                
                // Documentation and certification
                'requires_special_permits' => false,
                'requires_insurance' => true,
                'requires_safety_certificate' => true,
                'required_certifications' => null,
                
                // International classifications
                'imo_type_code' => 'TUG_001',
                'inland_vessel_code' => 'TP001',
                'imdg_class' => null,
                
                // Webservice integration
                'argentina_ws_code' => 'REMOLCADOR_PUERTO',
                'paraguay_ws_code' => 'TP_001',
                'webservice_mapping' => null,
                
                // Economic and operational data
                'daily_charter_rate' => 2500.00,
                'fuel_cost_per_day' => 1800.00,
                'typical_voyage_duration' => 1,
                'loading_time_hours' => 2,
                'unloading_time_hours' => 2,
                
                // Port compatibility
                'compatible_ports' => null,
                'restricted_ports' => null,
                'preferred_berths' => null,
                
                // Maintenance and lifecycle
                'typical_lifespan_years' => 30,
                'maintenance_interval_days' => 120,
                'requires_dry_dock' => true,
                'dry_dock_interval_months' => 18,
                
                // Status and display
                'active' => true,
                'is_common' => true,
                'is_specialized' => true,
                'display_order' => 50,
                'icon' => 'ship-tugboat',
                'color_code' => '#EF4444',
            ],
            
            [
                'code' => 'TUG_RIVER_001',
                'name' => 'Remolcador Fluvial',
                'short_name' => 'Remolcador Fluvial',
                'description' => 'Remolcador especializado para navegación fluvial. Diseño optimizado para aguas poco profundas y corrientes fuertes.',
                'category' => 'tugboat',
                'propulsion_type' => 'self_propelled',
                
                // Physical specifications
                'min_length' => 30.0,
                'max_length' => 45.0,
                'min_beam' => 9.0,
                'max_beam' => 13.0,
                'min_draft' => 2.5,
                'max_draft' => 4.0,
                
                // Capacity specifications
                'min_cargo_capacity' => 80.0,
                'max_cargo_capacity' => 200.0,
                'min_container_capacity' => null,
                'max_container_capacity' => null,
                'min_liquid_capacity' => null,
                'max_liquid_capacity' => null,
                
                // Operational characteristics
                'typical_crew_size' => 10,
                'max_crew_size' => 15,
                'typical_speed' => 8.0,
                'max_speed' => 12.0,
                'fuel_consumption_per_day' => 2500,
                
                // Cargo compatibility
                'handles_containers' => false,
                'handles_bulk_cargo' => false,
                'handles_general_cargo' => false,
                'handles_liquid_cargo' => false,
                'handles_dangerous_goods' => false,
                'handles_refrigerated_cargo' => false,
                'handles_oversized_cargo' => false,
                
                // Navigation capabilities
                'river_navigation' => true,
                'maritime_navigation' => false,
                'coastal_navigation' => false,
                'lake_navigation' => true,
                
                // Convoy capabilities
                'can_be_lead_vessel' => true,
                'can_be_in_convoy' => true,
                'can_push_barges' => true,
                'can_tow_barges' => true,
                'max_barges_in_convoy' => 8,
                
                // Environmental and safety
                'requires_pilot' => false,
                'requires_tugboat_assistance' => false,
                'environmental_restrictions' => null,
                'seasonal_restrictions' => null,
                'weather_limitations' => null,
                
                // Documentation and certification
                'requires_special_permits' => false,
                'requires_insurance' => true,
                'requires_safety_certificate' => true,
                'required_certifications' => null,
                
                // International classifications
                'imo_type_code' => null,
                'inland_vessel_code' => 'TR001',
                'imdg_class' => null,
                
                // Webservice integration
                'argentina_ws_code' => 'REMOLCADOR_FLUVIAL',
                'paraguay_ws_code' => 'TR_001',
                'webservice_mapping' => null,
                
                // Economic and operational data
                'daily_charter_rate' => 3000.00,
                'fuel_cost_per_day' => 2200.00,
                'typical_voyage_duration' => 3,
                'loading_time_hours' => 4,
                'unloading_time_hours' => 4,
                
                // Port compatibility
                'compatible_ports' => null,
                'restricted_ports' => null,
                'preferred_berths' => null,
                
                // Maintenance and lifecycle
                'typical_lifespan_years' => 35,
                'maintenance_interval_days' => 180,
                'requires_dry_dock' => true,
                'dry_dock_interval_months' => 24,
                
                // Status and display
                'active' => true,
                'is_common' => true,
                'is_specialized' => true,
                'display_order' => 60,
                'icon' => 'ship-tugboat-river',
                'color_code' => '#F59E0B',
            ],

            // ========== EMPUJADORES ==========
            [
                'code' => 'PUSHER_STD_001',
                'name' => 'Empujador Estándar',
                'short_name' => 'Empujador',
                'description' => 'Embarcación empujadora para formación de convoy con barcazas. Optimizada para empuje y eficiencia de combustible en trayectos largos.',
                'category' => 'pusher',
                'propulsion_type' => 'self_propelled',
                
                // Physical specifications
                'min_length' => 35.0,
                'max_length' => 55.0,
                'min_beam' => 12.0,
                'max_beam' => 16.0,
                'min_draft' => 3.0,
                'max_draft' => 5.0,
                
                // Capacity specifications
                'min_cargo_capacity' => 100.0,
                'max_cargo_capacity' => 300.0,
                'min_container_capacity' => null,
                'max_container_capacity' => null,
                'min_liquid_capacity' => null,
                'max_liquid_capacity' => null,
                
                // Operational characteristics
                'typical_crew_size' => 12,
                'max_crew_size' => 16,
                'typical_speed' => 9.0,
                'max_speed' => 15.0,
                'fuel_consumption_per_day' => 3500,
                
                // Cargo compatibility
                'handles_containers' => false,
                'handles_bulk_cargo' => false,
                'handles_general_cargo' => false,
                'handles_liquid_cargo' => false,
                'handles_dangerous_goods' => false,
                'handles_refrigerated_cargo' => false,
                'handles_oversized_cargo' => false,
                
                // Navigation capabilities
                'river_navigation' => true,
                'maritime_navigation' => true,
                'coastal_navigation' => false,
                'lake_navigation' => false,
                'min_water_depth' => 3.5,
                
                // Convoy capabilities
                'can_be_lead_vessel' => true,
                'can_be_in_convoy' => true,
                'can_push_barges' => true,
                'can_tow_barges' => false,
                'max_barges_in_convoy' => 8,
                
                // Environmental and safety
                'requires_pilot' => false,
                'requires_tugboat_assistance' => false,
                'environmental_restrictions' => null,
                'seasonal_restrictions' => null,
                'weather_limitations' => null,
                
                // Documentation and certification
                'requires_special_permits' => false,
                'requires_insurance' => true,
                'requires_safety_certificate' => true,
                'required_certifications' => null,
                
                // International classifications
                'imo_type_code' => null,
                'inland_vessel_code' => 'PE001',
                'imdg_class' => null,
                
                // Webservice integration
                'argentina_ws_code' => 'EMPUJADOR_STD',
                'paraguay_ws_code' => 'PE_001',
                'webservice_mapping' => null,
                
                // Economic and operational data
                'daily_charter_rate' => 3500.00,
                'fuel_cost_per_day' => 3200.00,
                'typical_voyage_duration' => 8,
                'loading_time_hours' => 6,
                'unloading_time_hours' => 6,
                
                // Port compatibility
                'compatible_ports' => null,
                'restricted_ports' => null,
                'preferred_berths' => null,
                
                // Maintenance and lifecycle
                'typical_lifespan_years' => 32,
                'maintenance_interval_days' => 180,
                'requires_dry_dock' => true,
                'dry_dock_interval_months' => 18,
                
                // Status and display
                'active' => true,
                'is_common' => true,
                'is_specialized' => true,
                'display_order' => 70,
                'icon' => 'ship-pusher',
                'color_code' => '#059669',
            ],
            
            [
                'code' => 'PUSHER_HEAVY_001',
                'name' => 'Empujador Pesado',
                'short_name' => 'Empujador Pesado',
                'description' => 'Empujador de alta potencia para convoy de múltiples barcazas. Diseñado para cargas pesadas y largas distancias.',
                'category' => 'pusher',
                'propulsion_type' => 'self_propelled',
                
                // Physical specifications
                'min_length' => 45.0,
                'max_length' => 70.0,
                'min_beam' => 14.0,
                'max_beam' => 20.0,
                'min_draft' => 3.5,
                'max_draft' => 6.0,
                
                // Capacity specifications
                'min_cargo_capacity' => 200.0,
                'max_cargo_capacity' => 500.0,
                'min_container_capacity' => null,
                'max_container_capacity' => null,
                'min_liquid_capacity' => null,
                'max_liquid_capacity' => null,
                
                // Operational characteristics
                'typical_crew_size' => 15,
                'max_crew_size' => 20,
                'typical_speed' => 8.0,
                'max_speed' => 12.0,
                'fuel_consumption_per_day' => 5000,
                
                // Cargo compatibility
                'handles_containers' => false,
                'handles_bulk_cargo' => false,
                'handles_general_cargo' => false,
                'handles_liquid_cargo' => false,
                'handles_dangerous_goods' => false,
                'handles_refrigerated_cargo' => false,
                'handles_oversized_cargo' => false,
                
                // Navigation capabilities
                'river_navigation' => true,
                'maritime_navigation' => true,
                'coastal_navigation' => false,
                'lake_navigation' => false,
                'min_water_depth' => 4.0,
                
                // Convoy capabilities
                'can_be_lead_vessel' => true,
                'can_be_in_convoy' => true,
                'can_push_barges' => true,
                'can_tow_barges' => false,
                'max_barges_in_convoy' => 12,
                
                // Environmental and safety
                'requires_pilot' => true,
                'requires_tugboat_assistance' => false,
                'environmental_restrictions' => null,
                'seasonal_restrictions' => null,
                'weather_limitations' => null,
                
                // Documentation and certification
                'requires_special_permits' => true,
                'requires_insurance' => true,
                'requires_safety_certificate' => true,
                'required_certifications' => null,
                
                // International classifications
                'imo_type_code' => null,
                'inland_vessel_code' => 'PP001',
                'imdg_class' => null,
                
                // Webservice integration
                'argentina_ws_code' => 'EMPUJADOR_PESADO',
                'paraguay_ws_code' => 'PP_001',
                'webservice_mapping' => null,
                
                // Economic and operational data
                'daily_charter_rate' => 4500.00,
                'fuel_cost_per_day' => 4200.00,
                'typical_voyage_duration' => 12,
                'loading_time_hours' => 8,
                'unloading_time_hours' => 8,
                
                // Port compatibility
                'compatible_ports' => null,
                'restricted_ports' => null,
                'preferred_berths' => null,
                
                // Maintenance and lifecycle
                'typical_lifespan_years' => 35,
                'maintenance_interval_days' => 150,
                'requires_dry_dock' => true,
                'dry_dock_interval_months' => 12,
                
                // Status and display
                'active' => true,
                'is_common' => false,
                'is_specialized' => true,
                'display_order' => 80,
                'icon' => 'ship-pusher-heavy',
                'color_code' => '#7C2D12',
            ],

            // ========== AUTOPROPULSADOS ==========
            [
                'code' => 'SELF_CARGO_001',
                'name' => 'Buque de Carga Autopropulsado',
                'short_name' => 'Autopropulsado Carga',
                'description' => 'Buque autopropulsado para carga general y contenedores. Ideal para rutas directas sin necesidad de remolque.',
                'category' => 'self_propelled',
                'propulsion_type' => 'self_propelled',
                
                // Physical specifications
                'min_length' => 80.0,
                'max_length' => 150.0,
                'min_beam' => 15.0,
                'max_beam' => 25.0,
                'min_draft' => 4.0,
                'max_draft' => 8.0,
                
                // Capacity specifications
                'min_cargo_capacity' => 1000.0,
                'max_cargo_capacity' => 5000.0,
                'min_container_capacity' => 50,
                'max_container_capacity' => 200,
                'min_liquid_capacity' => null,
                'max_liquid_capacity' => null,
                
                // Operational characteristics
                'typical_crew_size' => 20,
                'max_crew_size' => 30,
                'typical_speed' => 12.0,
                'max_speed' => 18.0,
                'fuel_consumption_per_day' => 4000,
                
                // Cargo compatibility
                'handles_containers' => true,
                'handles_bulk_cargo' => true,
                'handles_general_cargo' => true,
                'handles_liquid_cargo' => false,
                'handles_dangerous_goods' => true,
                'handles_refrigerated_cargo' => true,
                'handles_oversized_cargo' => true,
                
                // Navigation capabilities
                'river_navigation' => true,
                'maritime_navigation' => true,
                'coastal_navigation' => true,
                'lake_navigation' => false,
                'min_water_depth' => 5.0,
                
                // Convoy capabilities
                'can_be_lead_vessel' => true,
                'can_be_in_convoy' => false,
                'can_push_barges' => false,
                'can_tow_barges' => false,
                'max_barges_in_convoy' => null,
                
                // Environmental and safety
                'requires_pilot' => true,
                'requires_tugboat_assistance' => false,
                'environmental_restrictions' => null,
                'seasonal_restrictions' => null,
                'weather_limitations' => null,
                
                // Documentation and certification
                'requires_special_permits' => false,
                'requires_insurance' => true,
                'requires_safety_certificate' => true,
                'required_certifications' => null,
                
                // International classifications
                'imo_type_code' => 'CARGO_001',
                'inland_vessel_code' => 'AC001',
                'imdg_class' => null,
                
                // Webservice integration
                'argentina_ws_code' => 'AUTOPROPULSADO',
                'paraguay_ws_code' => 'AC_001',
                'webservice_mapping' => null,
                
                // Economic and operational data
                'daily_charter_rate' => 5000.00,
                'fuel_cost_per_day' => 3800.00,
                'typical_voyage_duration' => 10,
                'loading_time_hours' => 16,
                'unloading_time_hours' => 16,
                
                // Port compatibility
                'compatible_ports' => null,
                'restricted_ports' => null,
                'preferred_berths' => null,
                
                // Maintenance and lifecycle
                'typical_lifespan_years' => 25,
                'maintenance_interval_days' => 180,
                'requires_dry_dock' => true,
                'dry_dock_interval_months' => 18,
                
                // Status and display
                'active' => true,
                'is_common' => true,
                'is_specialized' => false,
                'display_order' => 90,
                'icon' => 'ship-cargo',
                'color_code' => '#8B5CF6',
            ],
        ];
    }
}