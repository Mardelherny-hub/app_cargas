<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * CargoTypesSeeder - ACTUALIZADO CON DATOS DE tipocar.txt
 * 
 * MÓDULO 3: VIAJES Y CARGAS
 * Seeder para tipos de carga usando datos del sistema existente + datos nuevos de tipocar.txt
 * 100% coherente con migración create_cargo_types_table.php
 * 
 * DATOS AGREGADOS desde tipocar.txt:
 * 01,DOCUMENTOS - 02,ENVIOS DE BAJO VALOR - 04,ENVIOS DE ALTO VALOR
 * 05,OTRA CARGA NO CONTEN - 06,VEHICULOS - 07,ROLL-ON ROLL-OFF  
 * 08,PALETIZADAS - 09,CONTENEDORES - 10,BREAKBULK
 * 11,CARGA PELIGROSA - 12,BUQUES DE CARGA GENE - 13,CARGA LIQUIDA
 * 14,CARGA CON CONTROL DE - 15,CARGA CONTAMINANTE D - 16,LA CARGA NO ES PELIG
 */
class CargoTypesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $now = Carbon::now();

        $cargoTypes = [
            // === DATOS ORIGINALES DEL SISTEMA ===
            
            // Contenedores (mantener original)
            [
                'code' => 'CONT001',
                'name' => 'Contenedores ISO',
                'short_name' => 'Contenedores',
                'description' => 'Carga transportada en contenedores estándar ISO',
                'cargo_nature' => 'mixed',
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
                'display_order' => 10,
                'created_date' => $now,
            ],

            // Carga General (mantener original)
            [
                'code' => 'GEN001',
                'name' => 'Carga General',
                'short_name' => 'General',
                'description' => 'Carga diversa no especializada',
                'cargo_nature' => 'mixed',
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
                'display_order' => 20,
                'created_date' => $now,
            ],

            // === NUEVOS DATOS DESDE tipocar.txt ===

            // 01,DOCUMENTOS
            [
                'code' => 'DOC001',
                'name' => 'DOCUMENTOS',
                'short_name' => 'Documentos',
                'description' => 'Documentos y correspondencia',
                'parent_id' => null,
                'level' => 0,
                'cargo_nature' => 'other',
                'packaging_type' => 'break_bulk',
                'requires_refrigeration' => false,
                'requires_special_handling' => false,
                'is_dangerous_goods' => false,
                'requires_permits' => false,
                'is_perishable' => false,
                'is_fragile' => true,
                'requires_fumigation' => false,
                'can_be_mixed' => true,
                'allows_consolidation' => true,
                'allows_deconsolidation' => true,
                'allows_transshipment' => true,
                'active' => true,
                'is_common' => true,
                'display_order' => 100,
                'created_date' => $now,
            ],

            // 02,ENVIOS DE BAJO VALOR
            [
                'code' => 'EBV001',
                'name' => 'ENVIOS DE BAJO VALOR',
                'short_name' => 'Bajo Valor',
                'description' => 'Envíos de mercadería de bajo valor comercial',
                'parent_id' => null,
                'level' => 0,
                'cargo_nature' => 'mixed',
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
                'display_order' => 110,
                'created_date' => $now,
            ],

            // 04,ENVIOS DE ALTO VALOR
            [
                'code' => 'EAV001',
                'name' => 'ENVIOS DE ALTO VALOR',
                'short_name' => 'Alto Valor',
                'description' => 'Envíos de mercadería de alto valor comercial',
                'parent_id' => null,
                'level' => 0,
                'cargo_nature' => 'mixed',
                'packaging_type' => 'containerized',
                'requires_refrigeration' => false,
                'requires_special_handling' => true,
                'is_dangerous_goods' => false,
                'requires_permits' => true,
                'is_perishable' => false,
                'is_fragile' => true,
                'requires_fumigation' => false,
                'can_be_mixed' => false,
                'allows_consolidation' => true,
                'allows_deconsolidation' => true,
                'allows_transshipment' => true,
                'active' => true,
                'is_common' => false,
                'display_order' => 120,
                'created_date' => $now,
            ],

            // 05,OTRA CARGA NO CONTEN
            [
                'code' => 'ONC001',
                'name' => 'OTRA CARGA NO CONTENEDORIZADA',
                'short_name' => 'No Contenedorizada',
                'description' => 'Carga general no transportada en contenedores',
                'parent_id' => null,
                'level' => 0,
                'cargo_nature' => 'solid',
                'packaging_type' => 'break_bulk',
                'requires_refrigeration' => false,
                'requires_special_handling' => true,
                'is_dangerous_goods' => false,
                'requires_permits' => false,
                'is_perishable' => false,
                'is_fragile' => false,
                'requires_fumigation' => false,
                'can_be_mixed' => true,
                'allows_consolidation' => false,
                'allows_deconsolidation' => false,
                'allows_transshipment' => true,
                'active' => true,
                'is_common' => true,
                'display_order' => 130,
                'created_date' => $now,
            ],

            // 06,VEHICULOS
            [
                'code' => 'VEH001',
                'name' => 'VEHICULOS',
                'short_name' => 'Vehículos',
                'description' => 'Vehículos automotores y maquinaria rodante',
                'parent_id' => null,
                'level' => 0,
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
                'display_order' => 140,
                'created_date' => $now,
            ],

            // 07,ROLL-ON ROLL-OFF
            [
                'code' => 'RORO001',
                'name' => 'ROLL-ON ROLL-OFF',
                'short_name' => 'Ro-Ro',
                'description' => 'Carga rodante embarcada/desembarcada por medios propios',
                'parent_id' => null,
                'level' => 0,
                'cargo_nature' => 'solid',
                'packaging_type' => 'ro_ro',
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
                'display_order' => 150,
                'created_date' => $now,
            ],

            // 08,PALETIZADAS
            [
                'code' => 'PAL001',
                'name' => 'PALETIZADAS',
                'short_name' => 'Paletizadas',
                'description' => 'Mercadería organizada y transportada en pallets',
                'parent_id' => null,
                'level' => 0,
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
                'display_order' => 160,
                'created_date' => $now,
            ],

            // 09,CONTENEDORES (versión específica del .txt)
            [
                'code' => 'CON001',
                'name' => 'CONTENEDORES',
                'short_name' => 'Contenedores',
                'description' => 'Carga transportada en contenedores ISO',
                'parent_id' => null,
                'level' => 0,
                'cargo_nature' => 'mixed',
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
                'display_order' => 170,
                'created_date' => $now,
            ],

            // 10,BREAKBULK
            [
                'code' => 'BRK001',
                'name' => 'BREAKBULK',
                'short_name' => 'Breakbulk',
                'description' => 'Carga general fraccionada no contenedorizada',
                'parent_id' => null,
                'level' => 0,
                'cargo_nature' => 'solid',
                'packaging_type' => 'break_bulk',
                'requires_refrigeration' => false,
                'requires_special_handling' => true,
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
                'display_order' => 180,
                'created_date' => $now,
            ],

            // 11,CARGA PELIGROSA
            [
                'code' => 'PEL001',
                'name' => 'CARGA PELIGROSA',
                'short_name' => 'Peligrosa',
                'description' => 'Mercancías peligrosas según clasificación IMDG',
                'parent_id' => null,
                'level' => 0,
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
                'display_order' => 190,
                'created_date' => $now,
            ],

            // 12,BUQUES DE CARGA GENE
            [
                'code' => 'BGE001',
                'name' => 'BUQUES DE CARGA GENERAL',
                'short_name' => 'Carga General',
                'description' => 'Carga diversa transportada en buques de carga general',
                'parent_id' => null,
                'level' => 0,
                'cargo_nature' => 'mixed',
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
                'display_order' => 200,
                'created_date' => $now,
            ],

            // 13,CARGA LIQUIDA
            [
                'code' => 'LIQ001',
                'name' => 'CARGA LIQUIDA',
                'short_name' => 'Líquida',
                'description' => 'Líquidos transportados en tanques o contenedores especializados',
                'parent_id' => null,
                'level' => 0,
                'cargo_nature' => 'liquid',
                'packaging_type' => 'bulk',
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
                'display_order' => 210,
                'created_date' => $now,
            ],

            // 14,CARGA CON CONTROL DE
            [
                'code' => 'CCD001',
                'name' => 'CARGA CON CONTROL DE TEMPERATURA',
                'short_name' => 'Refrigerada',
                'description' => 'Carga que requiere control específico de temperatura',
                'parent_id' => null,
                'level' => 0,
                'cargo_nature' => 'mixed',
                'packaging_type' => 'containerized',
                'requires_refrigeration' => true,
                'requires_special_handling' => true,
                'is_dangerous_goods' => false,
                'requires_permits' => false,
                'is_perishable' => true,
                'is_fragile' => true,
                'requires_fumigation' => false,
                'can_be_mixed' => false,
                'allows_consolidation' => true,
                'allows_deconsolidation' => true,
                'allows_transshipment' => true,
                'active' => true,
                'is_common' => true,
                'display_order' => 220,
                'created_date' => $now,
            ],

            // 15,CARGA CONTAMINANTE D
            [
                'code' => 'COD001',
                'name' => 'CARGA CONTAMINANTE DECLARADA',
                'short_name' => 'Contaminante',
                'description' => 'Carga con potencial contaminante declarado',
                'parent_id' => null,
                'level' => 0,
                'cargo_nature' => 'mixed',
                'packaging_type' => 'containerized',
                'requires_refrigeration' => false,
                'requires_special_handling' => true,
                'is_dangerous_goods' => true,
                'requires_permits' => true,
                'is_perishable' => false,
                'is_fragile' => false,
                'requires_fumigation' => true,
                'can_be_mixed' => false,
                'allows_consolidation' => false,
                'allows_deconsolidation' => false,
                'allows_transshipment' => true,
                'active' => true,
                'is_common' => false,
                'display_order' => 230,
                'created_date' => $now,
            ],

            // 16,LA CARGA NO ES PELIG
            [
                'code' => 'NPE001',
                'name' => 'CARGA NO PELIGROSA',
                'short_name' => 'No Peligrosa',
                'description' => 'Carga general sin clasificación de mercancía peligrosa',
                'parent_id' => null,
                'level' => 0,
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
                'display_order' => 240,
                'created_date' => $now,
            ],
        ];

        // Insertar todos los registros
        DB::table('cargo_types')->insert($cargoTypes);

        $this->command->info('✅ Tipos de carga creados exitosamente');
        $this->command->info('📦 Total de tipos creados: ' . count($cargoTypes));
        $this->command->line('');
        $this->command->line('Tipos creados:');
        foreach ($cargoTypes as $type) {
            $this->command->line("  - {$type['code']}: {$type['name']}");
        }
    }
}