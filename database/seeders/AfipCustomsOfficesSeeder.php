<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AfipCustomsOfficesSeeder extends Seeder
{
    /**
     * Seed AFIP customs offices from CSV file.
     * 
     * Fuente: afip_aduanas.csv (67 aduanas argentinas)
     */
    public function run(): void
    {
        $this->command->info('🏛️ Cargando Aduanas AFIP...');

        $path = database_path('seeders/data/afip_aduanas.csv');

        if (!file_exists($path)) {
            $this->command->error("❌ Archivo no encontrado: {$path}");
            return;
        }

        $handle = fopen($path, 'r');
        $header = fgetcsv($handle, 0, ';'); // Leer header
        
        $count = 0;

        while (($row = fgetcsv($handle, 0, ';')) !== false) {
            if (count($row) < 2) {
                continue;
            }

            DB::table('afip_customs_offices')->insert([
                'code' => trim($row[0]),
                'name' => trim($row[1]),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $count++;
        }

        fclose($handle);

        $this->command->info("✅ Aduanas cargadas: {$count}");
    }
}