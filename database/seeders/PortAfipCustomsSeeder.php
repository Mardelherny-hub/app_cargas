<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Port;
use App\Models\AfipCustomsOffice;

class PortAfipCustomsSeeder extends Seeder
{
    /**
     * Poblar relación Puerto ↔ Aduana AFIP
     * 
     * Basado en matches por ciudad/nombre
     */
    public function run(): void
    {
        $this->command->info('🔗 Vinculando Puertos con Aduanas AFIP...');

        // Matches Argentina (puerto_code => [aduana_codes])
        $argentinaMatches = [
            'ARBUE' => ['001', '091', '092'],
            'ARBHI' => ['003'],
            'ARCMP' => ['008'],
            'ARBQS' => ['010'],
            'ARCOL' => ['013'],
            'ARCRD' => ['014'],
            'ARCOU' => ['015'],
            'ARCNQ' => ['018'],
            'ARPUD' => ['019'],
            'ARDME' => ['020'],
            'ARFMA' => ['024'],
            'AROYA' => ['025'],
            'ARGHU' => ['026'],
            'ARLPG' => ['033'],
            'ARMDQ' => ['037'],
            'ARNEC' => ['040'],
            'ARPRA' => ['041'],
            'ARPSS' => ['046'],
            'ARPMY' => ['047'],
            'ARRGL' => ['048'],
            'ARRGA' => ['049'],
            'ARROS' => ['052'],
            'ARSLO' => ['057'],
            'ARSNS' => ['059'],
            'ARSPD' => ['060'],
            'ARRZA' => ['061'],
            'ARSFN' => ['062'],
            'ARUSH' => ['067'],
            'ARVCN' => ['069'],
            'ARTUC' => ['074'],
            'ARCVI' => ['087'],
            'ARPGV' => ['269'],
        ];

        $count = 0;

        foreach ($argentinaMatches as $portCode => $aduanaCodes) {
            $port = Port::where('code', $portCode)->first();
            if (!$port) continue;

            foreach ($aduanaCodes as $index => $aduanaCode) {
                $aduana = AfipCustomsOffice::where('code', $aduanaCode)->first();
                if (!$aduana) continue;

                DB::table('port_afip_customs')->insertOrIgnore([
                    'port_id' => $port->id,
                    'afip_customs_office_id' => $aduana->id,
                    'is_default' => $index === 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $count++;
            }
        }

        $this->command->info("✅ Argentina: {$count} vínculos creados");

        // Paraguay - En PY la aduana = lugar operativo
        // Los relacionamos vía afip_operative_locations después
        $this->command->info("ℹ️  Paraguay: Las aduanas se relacionan vía lugares operativos (customs_code = location_code)");

        $total = DB::table('port_afip_customs')->count();
        $this->command->info("✅ Total vínculos puerto-aduana: {$total}");
    }
}