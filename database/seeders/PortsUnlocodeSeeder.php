<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PortsUnlocodeSeeder extends Seeder
{
    protected string $csvPath = 'storage/app/unlocode_2024-2_columns.csv';
    protected ?array $countryFilter = null; // ej: ['AR','PY'] para filtrar por país
    protected int $batchSize = 1000;

    public function run(): void
    {
        if (!is_readable($this->csvPath)) {
            $this->command?->error("No puedo leer el archivo CSV: {$this->csvPath}");
            return;
        }

        $iso2Column = $this->detectCountryIso2Column();
        if (!$iso2Column) {
            $this->command?->warn("No se detectó columna ISO2 típica en 'countries'. Intentaré fallback por 'code' o 'iso3'.");
        }

        $fh = fopen($this->csvPath, 'r');
        if (!$fh) {
            $this->command?->error("No pude abrir el CSV: {$this->csvPath}");
            return;
        }

        $header = fgetcsv($fh);
        if (!$header) {
            $this->command?->error('CSV vacío o sin cabecera.');
            fclose($fh);
            return;
        }

        $header = array_map(fn($h) => strtolower(trim((string)$h)), $header);
        $idx = fn(string $name) => array_search(strtolower($name), $header);

        $colCountry     = $idx('country');
        $colLocation    = $idx('location');
        $colName        = $idx('name');
        $colSubDiv      = $idx('subdiv');
        $colCoordinates = $idx('coordinates');
        $colFunction    = $idx('function'); // ← usamos Function para filtrar sólo agua

        foreach ([$colCountry, $colLocation, $colName, $colFunction] as $must) {
            if ($must === false) {
                $this->command?->error("El CSV debe incluir columnas: Country, Location, Name y Function.");
                fclose($fh);
                return;
            }
        }

        $rows = [];
        $read = 0;
        $kept = 0;

        while (($data = fgetcsv($fh)) !== false) {
            $read++;

            $iso2  = strtoupper(trim((string)($data[$colCountry] ?? '')));
            $loc3  = strtoupper(trim((string)($data[$colLocation] ?? '')));
            $name  = trim((string)($data[$colName] ?? ''));
            $subdv = $colSubDiv !== false ? trim((string)($data[$colSubDiv] ?? '')) : null;
            $coord = $colCoordinates !== false ? trim((string)($data[$colCoordinates] ?? '')) : null;
            $func  = strtoupper(trim((string)($data[$colFunction] ?? '')));

            if ($this->countryFilter && !in_array($iso2, $this->countryFilter, true)) {
                continue;
            }
            if ($iso2 === '' || $loc3 === '' || $name === '') {
                continue;
            }

            // --- SOLO POR AGUA ---
            $hasSea    = strpos($func, '1') !== false; // puerto marítimo
            $hasInland = strpos($func, '8') !== false; // puerto fluvial / vía navegable interior
            if (!$hasSea && !$hasInland) {
                continue; // descartamos lo que no es por agua
            }

            // Seteo de port_type según Function
            $portType = $hasSea && $hasInland ? 'mixed' : ($hasSea ? 'maritime' : 'river');

            $code = $iso2 . $loc3;

            $countryId = $this->resolveCountryId($iso2, $iso2Column);
            if (!$countryId) {
                $this->command?->warn("Sin country_id para ISO2={$iso2} (code={$code}). Omitido.");
                continue;
            }

            [$lat, $lng] = $this->parseUnlocodeCoordinates($coord);

            $rows[] = [
                'code'               => $code,
                'name'               => $name,
                'short_name'         => $name,
                'local_name'         => $name,
                'country_id'         => $countryId,
                'city'               => $name,
                'province_state'     => $subdv ?: null,
                'latitude'           => $lat,
                'longitude'          => $lng,
                'port_type'          => $portType, // ← 'maritime' | 'river' | 'mixed'
                'created_date'       => now(),
                'created_by_user_id' => null,
                'created_at'         => now(),
                'updated_at'         => now(),
            ];

            $kept++;

            if (count($rows) >= $this->batchSize) {
                $this->flushBatch($rows);
                $rows = [];
            }
        }

        fclose($fh);

        if (count($rows)) {
            $this->flushBatch($rows);
        }

        $this->command?->info("Leídas: {$read}. Importadas (solo agua): {$kept}.");
    }

    protected function flushBatch(array $rows): void
    {
        DB::table('ports')->upsert(
            $rows,
            ['code'],
            [
                'name',
                'short_name',
                'local_name',
                'country_id',
                'city',
                'province_state',
                'latitude',
                'longitude',
                'port_type',
                'updated_at',
            ]
        );
        $this->command?->line("Insertadas/actualizadas: " . count($rows));
    }

    protected function detectCountryIso2Column(): ?string
    {
        if (!Schema::hasTable('countries')) {
            $this->command?->error("No existe la tabla 'countries'. Seedéala antes.");
            return null;
        }

        $candidates = [
            'iso2', 'iso_alpha2', 'alpha2', 'code2', 'cca2', 'iso_3166_1_alpha2'
        ];

        foreach ($candidates as $col) {
            if (Schema::hasColumn('countries', $col)) {
                return $col;
            }
        }

        return null;
    }

    protected function resolveCountryId(string $iso2, ?string $iso2Column): ?int
    {
        if ($iso2Column) {
            $row = DB::table('countries')->where($iso2Column, $iso2)->first(['id']);
            if ($row) return (int) $row->id;
        }

        if (Schema::hasColumn('countries', 'code')) {
            $row = DB::table('countries')->where('code', $iso2)->first(['id']);
            if ($row) return (int) $row->id;
        }

        if (Schema::hasColumn('countries', 'iso3')) {
            $row = DB::table('countries')->where('iso3', $iso2)->first(['id']);
            if ($row) return (int) $row->id;
        }

        return null;
    }

    protected function parseUnlocodeCoordinates(?string $coord): array
    {
        if (!$coord) return [null, null];

        $coord = strtoupper(trim($coord));
        $coord = preg_replace('/\s+/', '', $coord);

        if (!preg_match('/^(\d{2})(\d{2})([NS])(\d{3})(\d{2})([EW])$/', $coord, $m)) {
            if (!preg_match('/^(\d{2})(\d{2})([NS])\s?(\d{3})(\d{2})([EW])$/', $coord, $m)) {
                return [null, null];
            }
        }

        [$full, $latD, $latM, $latHem, $lonD, $lonM, $lonHem] = $m;

        $lat = (int)$latD + ((int)$latM / 60);
        $lon = (int)$lonD + ((int)$lonM / 60);

        if ($latHem === 'S') $lat *= -1;
        if ($lonHem === 'W') $lon *= -1;

        return [round($lat, 8), round($lon, 8)];
    }
}
