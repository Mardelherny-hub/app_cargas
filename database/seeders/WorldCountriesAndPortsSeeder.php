<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class WorldCountriesAndPortsSeeder extends Seeder
{
    /**
     * Rutas por defecto a tus archivos UN/LOCODE Part1/2/3 (con espacios).
     * Ajustalas si las guardaste en otro lugar.
     */
    protected array $unlocodeParts = [
        'storage/app/2024-2/UNLOCODE/2024-2 UNLOCODE CodeListPart1.csv',
        'storage/app/2024-2/UNLOCODE/2024-2 UNLOCODE CodeListPart2.csv',
        'storage/app/2024-2/UNLOCODE/2024-2 UNLOCODE CodeListPart3.csv',
    ];

    /**
     * (Opcional) CSV con nombres oficiales de países (mínimo columnas: iso2,name).
     * Si no existe este archivo, se crean los países usando solo iso2 y, si hay
     * columna 'name', se setea igual al iso2 para no inventar datos.
     */
    protected ?string $countriesNamesCsv = 'database/data/iso3166_countries.csv';

    /** Tamaño de lote para upsert de puertos */
    protected int $batchSize = 1000;

    /** Solo puertos por agua (marítimo y fluvial) -> usa Function UN/LOCODE: 1=marítimo, 8=interior */
    protected bool $waterOnly = true;

    public function run(): void
    {
        if (!Schema::hasTable('countries') || !Schema::hasTable('ports')) {
            $this->command?->error("Faltan tablas countries/ports. Corre migraciones primero.");
            return;
        }

        // NO crear países acá. Ya deben estar cargados por BaseCatalogsSeeder.
        $this->command?->info("1) Usando países existentes (no se crean).");

        // Pseudopaíses de UN/LOCODE que NO existen en ISO-3166 (no tienen iso3 en tu schema)
        $skipIso2 = ['XZ']; // Installations in International Waters

        $rows = [];
        $read = 0;
        $kept = 0;
        $perIso2 = [];

        // Si tenés un CSV normalizado, podés apuntar acá y salir de Part1/2/3
        $parts = $this->unlocodeParts ?? [
            'storage/app/2024-2/UNLOCODE/2024-2 UNLOCODE CodeListPart1.csv',
            'storage/app/2024-2/UNLOCODE/2024-2 UNLOCODE CodeListPart2.csv',
            'storage/app/2024-2/UNLOCODE/2024-2 UNLOCODE CodeListPart3.csv',
        ];

        $this->command?->info("2) Importando puertos (solo agua=" . ($this->waterOnly ? 'sí' : 'no') . ")...");

        foreach ($parts as $part) {
            if (!is_readable($part)) {
                $this->command?->warn("No puedo leer: {$part}. Lo salto.");
                continue;
            }
            $fh = fopen($part, 'r');
            if (!$fh) {
                $this->command?->warn("Error al abrir: {$part}. Lo salto.");
                continue;
            }

            while (($cols = fgetcsv($fh)) !== false) {
                $read++;
                if (count($cols) < 4) continue;

                // Layout maquetado típico:
                // [0]=#, [1]=Country(ISO2), [2]=Location(3), [3]=Name, [4]=SubDiv, [5]=Function,
                // [6]=Status, [7]=Date, [8]=IATA, [9]=Coordinates, [10]=Remarks
                $iso2  = strtoupper(trim((string)($cols[1] ?? '')));
                $loc3  = strtoupper(trim((string)($cols[2] ?? '')));
                $name  = $this->toUtf8(trim((string)($cols[3] ?? '')));
                $subdv = $this->toUtf8(trim((string)($cols[4] ?? '')));
                $func  = strtoupper(trim((string)($cols[5] ?? '')));
                $coord = strtoupper(trim((string)($cols[9] ?? '')));

                if ($iso2 === '' || $iso2 === 'COUNTRY' || $loc3 === '' || $name === '') continue;
                if (in_array($iso2, $skipIso2, true)) continue; // XZ, etc.

                // Solo agua: 1=marítimo, 8=vía navegable interior
                $hasSea    = strpos($func, '1') !== false;
                $hasInland = strpos($func, '8') !== false;
                if ($this->waterOnly && !$hasSea && !$hasInland) continue;

                $portType = $hasSea && $hasInland ? 'mixed' : ($hasSea ? 'maritime' : 'river');

                // Buscar country_id por alpha2_code (NO crear países aquí)
                $countryId = DB::table('countries')
                    ->where('alpha2_code', $iso2)
                    ->value('id');

                if (!$countryId) {
                    static $warned = [];
                    if (empty($warned[$iso2])) {
                        $this->command?->warn("País faltante (ISO2={$iso2}). Cargá países con BaseCatalogsSeeder. Omitiendo puertos de {$iso2}.");
                        $warned[$iso2] = true;
                    }
                    continue;
                }

                [$lat, $lng] = $this->parseUnlocodeCoordinates($coord);
                $code = $iso2 . $loc3;

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
                    'port_type'          => $portType,
                    'created_date'       => now(),
                    'created_by_user_id' => null,
                    'created_at'         => now(),
                    'updated_at'         => now(),
                ];
                $kept++;
                $perIso2[$iso2] = ($perIso2[$iso2] ?? 0) + 1;

                if (count($rows) >= $this->batchSize) {
                    $this->flushPortsBatch($rows);
                    $rows = [];
                }
            }
            fclose($fh);
        }

        if ($rows) {
            $this->flushPortsBatch($rows);
        }

        $this->command?->info("Leídas: {$read}. Importadas (puertos por agua): {$kept}.");
        if ($kept > 0) {
            ksort($perIso2);
            $sample = array_slice($perIso2, 0, 15, true);
            $this->command?->line("Ejemplo por país (primeros 15): " . json_encode($sample));
        }
    }


    /* ===================== Helpers ===================== */

    /**
     * Convierte a UTF-8 desde ISO-8859-1 si fuera necesario (sin fallar).
     */
    protected function toUtf8(string $value): string
    {
        if ($value === '') return $value;
        // Heurística simple: si no es UTF-8 válido, intentamos latin1
        if (!mb_check_encoding($value, 'UTF-8')) {
            return @mb_convert_encoding($value, 'UTF-8', 'ISO-8859-1');
        }
        return $value;
    }

    /**
     * Carga un mapa opcional iso2 => name desde CSV externo si existe.
     * Formato esperado (sin BOM): iso2,name
     */
    protected function loadCountryNamesOptional(): array
    {
        $map = [];
        $path = $this->countriesNamesCsv;
        if (!$path || !is_readable($path)) {
            return $map;
        }
        $fh = fopen($path, 'r');
        if (!$fh) return $map;

        // Intentamos detectar cabecera
        $first = fgetcsv($fh);
        if (!$first) { fclose($fh); return $map; }

        $header = array_map(fn($h) => strtolower(trim((string)$h)), $first);
        $colIso2 = array_search('iso2', $header);
        $colName = array_search('name', $header);

        if ($colIso2 === false || $colName === false) {
            // Tal vez no tiene cabecera y es directo "iso2,name": reusamos la primera fila
            $iso2 = strtoupper(trim((string)$first[0] ?? ''));
            $name = $this->toUtf8(trim((string)$first[1] ?? ''));
            if ($iso2 && $name) $map[$iso2] = $name;
            // Leer el resto
            while (($row = fgetcsv($fh)) !== false) {
                $iso2 = strtoupper(trim((string)($row[0] ?? '')));
                $name = $this->toUtf8(trim((string)($row[1] ?? '')));
                if ($iso2 && $name) $map[$iso2] = $name;
            }
            fclose($fh);
            return $map;
        }

        while (($row = fgetcsv($fh)) !== false) {
            $iso2 = strtoupper(trim((string)($row[$colIso2] ?? '')));
            $name = $this->toUtf8(trim((string)($row[$colName] ?? '')));
            if ($iso2 && $name) $map[$iso2] = $name;
        }
        fclose($fh);
        return $map;
    }

    /**
     * Inserta/actualiza por lotes la tabla 'ports' usando 'code' como clave.
     * No tocamos flags operativas/hardware más allá de lo básico.
     */
    protected function flushPortsBatch(array $rows): void
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

    protected function countryIdByAlpha2(string $iso2): ?int
{
    $row = \Illuminate\Support\Facades\DB::table('countries')
        ->where('alpha2_code', strtoupper($iso2))
        ->first(['id']);
    return $row ? (int) $row->id : null;
}

    /**
     * Asegura que exista un país con ese ISO2; si no existe, lo crea con datos mínimos.
     * Si hay 'name' en countries y tenemos un nombre oficial en $countryNames, lo usamos.
     */
    protected function ensureCountryId(string $iso2, array $countryNames): ?int
    {
        $iso2 = strtoupper($iso2);
        if ($iso2 === '') return null;

        if (!Schema::hasTable('countries')) return null;

        // Columnas existentes en la tabla countries
        $cols = Schema::getColumnListing('countries');

        // Encontrar la mejor columna para ISO2
        $iso2Col = null;
        // 1) Donde arma la lista de columnas posibles para ISO-2:
foreach (['alpha2_code','iso2','iso_alpha2','alpha2','code2','cca2','iso_3166_1_alpha2','code'] as $c) {
    if (in_array($c, $cols, true)) { $iso2Col = $c; break; }
}

// 2) Al armar $payload para insertar el país:
if (in_array('alpha2_code', $cols, true))      $payload['alpha2_code'] = $iso2;
elseif (in_array('iso2', $cols, true))         $payload['iso2'] = $iso2;
elseif (in_array('iso_alpha2', $cols, true))   $payload['iso_alpha2'] = $iso2;

        if (!$iso2Col) {
            $this->command?->warn("No hay columna para guardar ISO2 en 'countries'.");
            return null;
        }

        // Buscar existente
        $row = DB::table('countries')->where($iso2Col, $iso2)->first(['id']);
        if ($row) return (int)$row->id;

        // Preparar inserción mínima
        $payload = [$iso2Col => $iso2];
        if (in_array('name', $cols, true)) {
            $payload['name'] = $countryNames[$iso2] ?? $iso2; // no inventamos: sin dataset, usamos ISO2
        }
        if (in_array('created_at', $cols, true)) $payload['created_at'] = now();
        if (in_array('updated_at', $cols, true)) $payload['updated_at'] = now();

        try {
            $id = DB::table('countries')->insertGetId($payload);
            return (int)$id;
        } catch (\Throwable $e) {
            // Si hubo condición de carrera, reintentar lectura
            $row = DB::table('countries')->where($iso2Col, $iso2)->first(['id']);
            if ($row) return (int)$row->id;
            $this->command?->warn("No pude crear país ISO2={$iso2}: {$e->getMessage()}");
            return null;
        }
    }

    /**
     * Parsea coordenadas UN/LOCODE "DDMMN DDDMMW" (o sin espacio) a decimales.
     */
    protected function parseUnlocodeCoordinates(?string $coord): array
    {
        if (!$coord) return [null, null];

        $coord = strtoupper(trim($coord));
        $coord = preg_replace('/\s+/', '', $coord);

        if (!preg_match('/^(\d{2})(\d{2})([NS])(\d{3})(\d{2})([EW])$/', $coord, $m)) {
            // Intento con espacio opcional
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
