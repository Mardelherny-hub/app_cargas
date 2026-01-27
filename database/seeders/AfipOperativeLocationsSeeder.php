<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Country;

class AfipOperativeLocationsSeeder extends Seeder
{
    /**
     * Seed AFIP operative locations from CSV files.
     * 
     * Fuentes:
     * - afip_aduanas.csv (solo referencia, no se usa tabla separada)
     * - afip_locations_argentina.csv
     * - afip_locations_foreign.csv
     */
    public function run(): void
    {
        $this->command->info('🏛️ Cargando Lugares Operativos AFIP...');

        // Cache de países por código ISO
        $countries = Country::pluck('id', 'alpha2_code')->toArray();

        // Cargar Argentina
        $this->loadFromCsv(
            database_path('seeders/data/afip_locations_argentina.csv'),
            $countries,
            false, // is_foreign = false
            'Argentina'
        );

        // Cargar Extranjeros
        $this->loadFromCsv(
            database_path('seeders/data/afip_locations_foreign.csv'),
            $countries,
            true, // is_foreign = true
            'Extranjeros'
        );

        $total = DB::table('afip_operative_locations')->count();
        $this->command->info("✅ Total lugares operativos cargados: {$total}");
    }

    private function loadFromCsv(string $path, array $countries, bool $isForeign, string $label): void
    {
        if (!file_exists($path)) {
            $this->command->error("❌ Archivo no encontrado: {$path}");
            return;
        }

        $handle = fopen($path, 'r');
        $header = fgetcsv($handle, 0, ';'); // Leer header
        
        $count = 0;
        $skipped = 0;
        $batch = [];

        while (($row = fgetcsv($handle, 0, ';')) !== false) {
            if (count($row) < 4) {
                $skipped++;
                continue;
            }

            $countryCode = trim($row[0]);
            $countryId = $countries[$countryCode] ?? null;

            if (!$countryId) {
                $skipped++;
                continue;
            }

            $batch[] = [
                'country_id' => $countryId,
                'port_id' => null,
                'customs_code' => trim($row[1]),
                'location_code' => trim($row[2]),
                'description' => trim($row[3]),
                'is_foreign' => $isForeign,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            // Insertar en lotes de 100
            if (count($batch) >= 100) {
                DB::table('afip_operative_locations')->insert($batch);
                $count += count($batch);
                $batch = [];
            }
        }

        // Insertar resto
        if (!empty($batch)) {
            DB::table('afip_operative_locations')->insert($batch);
            $count += count($batch);
        }

        fclose($handle);

        $this->command->info("  📍 {$label}: {$count} registros (omitidos: {$skipped})");
    }
}