<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $types = DB::table('container_types');
        if ($types->where('code', '45R5')->orWhere('iso_code', '45R5')->exists()) {
            return;
        }

        // Dimensiones internas y pesos de referencia del 40RH del catálogo.
        DB::table('container_types')->insert([
            'code' => '45R5',
            'name' => 'Contenedor 40 pies High Cube refrigerado y calefaccionado',
            'short_name' => '45R5',
            'description' => 'ISO 6346 45R5: equipo integrado con refrigeración mecánica y calefacción. Medidas internas, pesos y volumen de referencia de 40RH, sin certificación para un equipo particular.',
            'iso_code' => '45R5',
            'iso_size_type' => '45R5',
            'iso_group' => 'reefer',
            'length_feet' => '40',
            'width_feet' => '8',
            'height_feet' => '9.5',
            'length_mm' => 12192,
            'width_mm' => 2438,
            'height_mm' => 2896,
            'internal_length_mm' => 11577,
            'internal_width_mm' => 2294,
            'internal_height_mm' => 2521,
            'tare_weight_kg' => 4200,
            'max_gross_weight_kg' => 34000,
            'max_payload_kg' => 29800,
            'internal_volume_m3' => 67,
            'category' => 'refrigerated',
            'is_refrigerated' => true,
            'is_heated' => true,
            'is_insulated' => true,
            'csc_certified' => false,
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // El tipo puede existir antes de la migración o estar vinculado a contenedores.
    }
};
