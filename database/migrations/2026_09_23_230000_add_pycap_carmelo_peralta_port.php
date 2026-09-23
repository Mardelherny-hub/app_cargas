<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const PORT_CODE = 'PYCAP';
    private const NOTE = 'Catálogo explícito requerido por archivos Navsur: Capitán Carmelo Peralta.';

    public function up(): void
    {
        if (DB::table('ports')->where('code', self::PORT_CODE)->exists()) {
            return;
        }

        $paraguayId = DB::table('countries')
            ->where('alpha2_code', 'PY')
            ->value('id');

        if (!$paraguayId) {
            throw new RuntimeException(
                'No se puede registrar PYCAP: Paraguay (PY) no existe en countries.'
            );
        }

        DB::table('ports')->insert([
            'code' => self::PORT_CODE,
            'name' => 'Capitán Carmelo Peralta',
            'local_name' => 'Capitán Carmelo Peralta',
            'country_id' => $paraguayId,
            'city' => 'Capitán Carmelo Peralta',
            'port_type' => 'river',
            'handles_containers' => true,
            'handles_bulk_cargo' => true,
            'handles_general_cargo' => true,
            'has_customs_office' => true,
            'webservice_code' => self::PORT_CODE,
            'active' => true,
            'accepts_new_vessels' => true,
            'special_notes' => self::NOTE,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('ports')
            ->where('code', self::PORT_CODE)
            ->where('special_notes', self::NOTE)
            ->delete();
    }
};
