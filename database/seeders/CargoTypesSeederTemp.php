<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CargoTypesSeederTemp extends Seeder
{
    /**
     * Run the database seeds.
     * 
     * MÓDULO 3: VIAJES Y CARGAS
     * Seeder para tipos de carga más comunes en transporte fluvial/marítimo
     * Compatible con estándares internacionales y webservices AR/PY
     */
    public function run(): void
    {
        $now = Carbon::now();

        $cargoTypes = [
            // Carga General
            [
                'code' => 'GEN001',
                'name' => 'Carga General',
                'short_name' => 'General',
                'description' => 'Mercadería diversa empacada en bultos individuales',
                'cargo_nature' => 'solid',
                'packaging_type' => 'break_bulk',
                'requires_refrigeration' => false,
                'requires_special_handling' => false,
                'is_dangerous_goods' => false,
                'requires_permits' => false,
                'is_perishable' => false,
                'is_fragile' => false,
                'requires_fumigation' => false,
                'can_be_mixed' => true,
                'allows_consolidation' => true,
                'allows_deconsolidation' => true,
                'allows_transshipment' => true,
                'active' => true,
                'is_common' => true,
                'display_order' => 10,
                'created_date' => $now,
            ],

            // Contenedores
            [
                'code' => 'CON001',
                'name' => 'Contenedores',
                'short_name' => 'Containers',
                'description' => 'Carga en contenedores estándar ISO',
                'cargo_nature' => 'solid',
                'packaging_type' => 'containerized',
                'requires_refrigeration' => false,
                'requires_special_handling' => false,
                'is_dangerous_goods' => false,
                'requires_permits' => false,
                'is_perishable' => false,
                'is_fragile' => false,
                'requires_fumigation' => false,
                'can_be_mixed' => true,
                'allows_consolidation' => true,
                'allows_deconsolidation' => true,
                'allows_transshipment' => true,
                'active' => true,
                'is_common' => true,
                'display_order' => 20,
                'created_date' => $now,
            ],

            // Carga a Granel
            [
                'code' => 'BLK001',
                'name' => 'Carga a Granel',
                'short_name' => 'Granel',
                'description' => 'Mercadería sin empacar transportada a granel',
                'cargo_nature' => 'solid',
                'packaging_type' => 'bulk',
                'requires_refrigeration' => false,
                'requires_special_handling' => true,
                'is_dangerous_goods' => false,
                'requires_permits' => false,
                'is_perishable' => false,
                'is_fragile' => false,
                'requires_fumigation' => false,
                'can_be_mixed' => false,
                'allows_consolidation' => false,
                'allows_deconsolidation' => false,
                'allows_transshipment' => true,
                'active' => true,
                'is_common' => true,
                'display_order' => 30,
                'created_date' => $now,
            ],

            // Carga Refrigerada
            [
                'code' => 'REF001',
                'name' => 'Carga Refrigerada',
                'short_name' => 'Refrigerada',
                'description' => 'Mercadería que requiere control de temperatura',
                'cargo_nature' => 'solid',
                'packaging_type' => 'containerized',
                'requires_refrigeration' => true,
                'requires_special_handling' => true,
                'is_dangerous_goods' => false,
                'requires_permits' => false,
                'is_perishable' => true,
                'is_fragile' => false,
                'requires_fumigation' => false,
                'can_be_mixed' => false,
                'allows_consolidation' => true,
                'allows_deconsolidation' => true,
                'allows_transshipment' => true,
                'active' => true,
                'is_common' => true,
                'display_order' => 40,
                'created_date' => $now,
            ],

            // Mercancías Peligrosas
            [
                'code' => 'DNG001',
                'name' => 'Mercancías Peligrosas',
                'short_name' => 'Peligrosas',
                'description' => 'Mercadería clasificada como peligrosa según IMDG',
                'cargo_nature' => 'mixed',
                'packaging_type' => 'containerized',
                'requires_refrigeration' => false,
                'requires_special_handling' => true,
                'is_dangerous_goods' => true,
                'requires_permits' => true,
                'is_perishable' => false,
                'is_fragile' => false,
                'requires_fumigation' => false,
                'can_be_mixed' => false,
                'allows_consolidation' => false,
                'allows_deconsolidation' => false,
                'allows_transshipment' => true,
                'active' => true,
                'is_common' => true,
                'display_order' => 50,
                'created_date' => $now,
            ],

            // Carga Líquida
            [
                'code' => 'LIQ001',
                'name' => 'Carga Líquida',
                'short_name' => 'Líquida',
                'description' => 'Líquidos transportados en tanques o contenedores especiales',
                'cargo_nature' => 'liquid',
                'packaging_type' => 'bulk',
                'requires_refrigeration' => false,
                'requires_special_handling' => true,
                'is_dangerous_goods' => false,
                'requires_permits' => false,
                'is_perishable' => false,
                'is_fragile' => false,
                'requires_fumigation' => false,
                'can_be_mixed' => false,
                'allows_consolidation' => false,
                'allows_deconsolidation' => false,
                'allows_transshipment' => true,
                'active' => true,
                'is_common' => true,
                'display_order' => 60,
                'created_date' => $now,
            ],

            // Vehículos
            [
                'code' => 'VEH001',
                'name' => 'Vehículos',
                'short_name' => 'Vehículos',
                'description' => 'Automóviles, camiones, maquinaria y equipos rodantes',
                'cargo_nature' => 'solid',
                'packaging_type' => 'ro_ro',
                'requires_refrigeration' => false,
                'requires_special_handling' => true,
                'is_dangerous_goods' => false,
                'requires_permits' => true,
                'is_perishable' => false,
                'is_fragile' => false,
                'requires_fumigation' => false,
                'can_be_mixed' => false,
                'allows_consolidation' => false,
                'allows_deconsolidation' => false,
                'allows_transshipment' => true,
                'active' => true,
                'is_common' => true,
                'display_order' => 70,
                'created_date' => $now,
            ],

            // Gases
            [
                'code' => 'GAS001',
                'name' => 'Gases',
                'short_name' => 'Gases',
                'description' => 'Gases comprimidos o licuados',
                'cargo_nature' => 'gas',
                'packaging_type' => 'containerized',
                'requires_refrigeration' => false,
                'requires_special_handling' => true,
                'is_dangerous_goods' => true,
                'requires_permits' => true,
                'is_perishable' => false,
                'is_fragile' => false,
                'requires_fumigation' => false,
                'can_be_mixed' => false,
                'allows_consolidation' => false,
                'allows_deconsolidation' => false,
                'allows_transshipment' => false,
                'active' => true,
                'is_common' => false,
                'display_order' => 80,
                'created_date' => $now,
            ],
        ];

        DB::table('cargo_types')->insert($cargoTypes);

        $this->command->info('✅ Tipos de carga creados exitosamente');
    }
}