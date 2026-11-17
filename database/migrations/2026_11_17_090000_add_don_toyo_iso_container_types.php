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

        DB::transaction(function () {
            $pending = [];

            foreach ($this->containerTypes() as $type) {
                // Consultas independientes: ninguna coincidencia habilita sobrescrituras.
                $byCode = DB::table('container_types')->where('code', $type['code'])->first();
                $byIso = DB::table('container_types')->where('iso_code', $type['iso_code'])->first();

                if ($byCode !== null && $byCode->iso_code !== $type['iso_code']) {
                    throw new RuntimeException("Conflicto en container_types: el código {$type['code']} pertenece a otro ISO. No se modificó el registro.");
                }

                foreach ([$byCode, $byIso] as $existing) {
                    if ($existing !== null && (
                        $existing->iso_size_type !== $type['iso_size_type']
                        || $existing->argentina_ws_code !== $type['argentina_ws_code']
                    )) {
                        throw new RuntimeException("Conflicto en container_types para ISO {$type['iso_code']}: iso_size_type o argentina_ws_code no coincide. Revisar el registro existente sin sobrescribirlo.");
                    }
                }

                if ($byCode === null && $byIso === null) {
                    $pending[] = $type;
                }
            }

            foreach ($pending as $type) {
                DB::table('container_types')->insert(array_merge($type, [
                    'created_date' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]));
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('container_types')) {
            return;
        }

        DB::transaction(function () {
            foreach ($this->containerTypes() as $type) {
                // La descripción completa identifica las altas propias; no alcanza el ISO.
                DB::table('container_types')
                    ->where('code', $type['code'])
                    ->where('iso_code', $type['iso_code'])
                    ->where('iso_size_type', $type['iso_size_type'])
                    ->where('argentina_ws_code', $type['argentina_ws_code'])
                    ->where('description', $type['description'])
                    ->delete();
            }
        });
    }

    private function containerTypes(): array
    {
        // Referencias copiadas de ContainerTypesSeeder: no son capacidades certificadas
        // por ISO 6346. Incluyen dimensiones internas, tara, bruto, carga y volumen.
        // No se consulta ni se ejecuta el seeder durante la migración.
        $standardReference = [
            'length_feet' => '40',
            'width_feet' => '8',
            'height_feet' => '8.5',
            'length_mm' => 12192.00,
            'width_mm' => 2438.00,
            'height_mm' => 2591.00,
            'internal_length_mm' => 12032.00,
            'internal_width_mm' => 2352.00,
            'internal_height_mm' => 2393.00,
            'tare_weight_kg' => 3740.00,
            'max_gross_weight_kg' => 30480.00,
            'max_payload_kg' => 26740.00,
            'internal_volume_m3' => 67.70,
        ];

        $highCubeReference = array_replace($standardReference, [
            'height_feet' => '9.5',
            'height_mm' => 2896.00,
            'internal_height_mm' => 2698.00,
            'tare_weight_kg' => 3900.00,
            'max_payload_kg' => 26580.00,
            'internal_volume_m3' => 76.30,
        ]);

        $common = [
            'is_refrigerated' => false,
            'is_heated' => false,
            'is_insulated' => false,
            'is_ventilated' => false,
            'has_electrical_supply' => false,
            'has_end_walls' => true,
            'has_folding_sides' => false,
            'suitable_for_dangerous_goods' => false,
            'suitable_for_food' => false,
            'food_grade' => false,
            'csc_certified' => false,
            'certifications' => null,
            'active' => true,
            'is_standard' => true,
            'is_common' => false,
            // No se infieren equivalencias aduaneras ni certificaciones del ISO.
            'paraguay_ws_code' => null,
            'customs_code' => null,
        ];

        return [
            array_merge($common, $highCubeReference, [
                'code' => '45G0',
                'iso_code' => '45G0',
                'iso_size_type' => '45G0',
                'argentina_ws_code' => '45G0',
                'name' => 'Contenedor 40 pies High Cube carga general ISO 45G0',
                'short_name' => '45G0',
                'description' => 'Catálogo Don Toyo ISO 45G0: 40 pies High Cube carga general. Referencias físicas heredadas de 40HC en ContainerTypesSeeder; no son especificaciones certificadas del equipo.',
                'iso_group' => 'high_cube',
                'category' => 'dry_cargo',
                'has_roof' => true,
                'has_sidewalls' => true,
                'has_doors' => true,
                'has_removable_top' => false,
                'door_type' => 'standard',
                'door_width_mm' => 2340.00,
                'door_height_mm' => 2585.00,
                'loading_volume_m3' => 75.20,
                'max_stack_height' => 7,
                'is_specialized' => false,
                'webservice_mapping' => json_encode(['type' => 'GP', 'size' => '40', 'height' => 'High', 'iso_code' => '45G0']),
            ]),
            // 40GP aporta las referencias físicas de 40 pies y altura estándar.
            // 20OT aporta la configuración Open Top, no sus medidas de 20 pies.
            array_merge($common, $standardReference, [
                'code' => '42U1',
                'iso_code' => '42U1',
                'iso_size_type' => '42U1',
                'argentina_ws_code' => '42U1',
                'name' => 'Contenedor 40 pies Open Top altura estándar ISO 42U1',
                'short_name' => '42U1',
                'description' => 'Catálogo Don Toyo ISO 42U1: 40 pies Open Top altura 8 pies 6 pulgadas. Referencias físicas heredadas de 40GP y configuración de 20OT en ContainerTypesSeeder; pesos, volumen y medidas internas no certificados para este subtipo.',
                'iso_group' => 'open_top',
                'category' => 'open_top',
                'is_ventilated' => true,
                'has_roof' => false,
                'has_sidewalls' => true,
                'has_doors' => true,
                'has_removable_top' => true,
                'door_type' => 'standard',
                'door_width_mm' => 2340.00,
                'door_height_mm' => 2280.00,
                'loading_volume_m3' => null,
                'is_specialized' => true,
                'suitable_for_oversized_cargo' => true,
                'requires_special_handling' => true,
                'webservice_mapping' => json_encode(['type' => 'UT', 'size' => '40', 'special' => 'open_top', 'iso_code' => '42U1']),
            ]),
            // No hay Flat Rack en el seeder. 40GP sólo aporta referencias físicas
            // obligatorias de 40 pies estándar, no una capacidad útil de Flat Rack.
            array_merge($common, $standardReference, [
                'code' => '42P1',
                'iso_code' => '42P1',
                'iso_size_type' => '42P1',
                'argentina_ws_code' => '42P1',
                'name' => 'Contenedor 40 pies Flat Rack extremos fijos ISO 42P1',
                'short_name' => '42P1',
                'description' => 'Catálogo Don Toyo ISO 42P1: 40 pies Flat Rack altura 8 pies 6 pulgadas con extremos fijos. Referencias físicas obligatorias heredadas de 40GP en ContainerTypesSeeder; medidas internas, pesos y volumen son referencias de catálogo, no capacidad útil certificada de Flat Rack.',
                'iso_group' => 'flat_rack',
                'category' => 'flat_rack',
                'has_roof' => false,
                'has_sidewalls' => false,
                'has_doors' => false,
                'has_removable_top' => false,
                'door_type' => 'no_door',
                'door_width_mm' => null,
                'door_height_mm' => null,
                'loading_volume_m3' => null,
                'is_specialized' => true,
                'suitable_for_oversized_cargo' => true,
                'requires_special_handling' => true,
                'webservice_mapping' => json_encode(['type' => 'P1', 'size' => '40', 'special' => 'flat_rack', 'iso_code' => '42P1']),
            ]),
        ];
    }
};
