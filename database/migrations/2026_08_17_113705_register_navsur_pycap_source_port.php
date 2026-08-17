<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const NOTE =
        'Código de puerto declarado por fuente Navsur. '
        . 'PYCAP se interpreta como Capitán Carmelo Peralta. '
        . 'No implica código UN/LOCODE, AFIP ni DNA verificado.';

    public function up(): void
    {
        $countryId = DB::table('countries')
            ->whereRaw('UPPER(alpha2_code) = ?', ['PY'])
            ->value('id');

        if (!$countryId) {
            throw new RuntimeException(
                'No existe Paraguay (PY) en countries.'
            );
        }

        $existing = DB::table('ports')
            ->where('code', 'PYCAP')
            ->first();

        if ($existing) {
            if ((int) $existing->country_id !== (int) $countryId) {
                throw new RuntimeException(
                    'PYCAP ya existe asociado a otro país.'
                );
            }

            return;
        }

        DB::table('ports')->insert([
            /*
             * Código recibido literalmente de Navsur.
             * No se declara como UN/LOCODE oficial.
             */
            'code' => 'PYCAP',
            'name' => 'Capitán Carmelo Peralta',
            'short_name' => 'Carmelo Peralta',
            'local_name' => 'Capitán Carmelo Peralta',
            'country_id' => $countryId,
            'city' => 'Capitán Carmelo Peralta',

            /*
             * La localidad se encuentra sobre el río Paraguay.
             * No inventamos categoría aduanera ni códigos oficiales.
             */
            'port_type' => 'river',
            'port_category' => 'minor',

            /*
             * El fixture Navsur transporta contenedores por este punto.
             */
            'handles_containers' => true,
            'handles_bulk_cargo' => false,
            'handles_general_cargo' => true,
            'handles_passengers' => false,
            'handles_dangerous_goods' => false,

            /*
             * Sin código aduanero verificado:
             * no habilitar capacidades de transmisión.
             */
            'has_customs_office' => false,
            'afip_code' => null,
            'dna_code' => null,
            'webservice_code' => null,
            'supports_anticipada' => false,
            'supports_micdta' => false,
            'supports_manifest' => false,

            'active' => true,
            'accepts_new_vessels' => true,
            'special_notes' => self::NOTE,
        ]);
    }

    public function down(): void
    {
        $port = DB::table('ports')
            ->where('code', 'PYCAP')
            ->first();

        if (!$port) {
            return;
        }

        if ($port->special_notes !== self::NOTE) {
            throw new RuntimeException(
                'PYCAP fue modificado después de la migración; '
                . 'no se elimina automáticamente.'
            );
        }

        DB::table('ports')
            ->where('id', $port->id)
            ->delete();
    }
};
