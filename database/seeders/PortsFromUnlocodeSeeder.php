<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PortsFromUnlocodeSeeder extends Seeder
{
    /** Tus archivos UN/LOCODE */
    protected array $files = [
        'storage/app/2024-2/UNLOCODE/2024-2 UNLOCODE CodeListPart1.csv',
        'storage/app/2024-2/UNLOCODE/2024-2 UNLOCODE CodeListPart2.csv',
        'storage/app/2024-2/UNLOCODE/2024-2 UNLOCODE CodeListPart3.csv',
    ];

    /** Solo agua: 1=mar, 8=vía interior */
    protected bool $waterOnly = true;

    /** Pseudopaíses sin ISO-3166 */
    protected array $skipIso2 = ['XZ']; // International Waters

    /** Tamaño de lote para upsert */
    protected int $batchSize = 1000;

    public function run(): void
    {
        if (!Schema::hasTable('countries') || !Schema::hasTable('ports')) {
            $this->command?->error("Faltan tablas countries/ports.");
            return;
        }

        // detectar columna ISO-2 en countries (alpha2_code o alpha_code)
        $countryIso2Col = null;
        if (Schema::hasColumn('countries', 'alpha2_code'))      $countryIso2Col = 'alpha2_code';
        elseif (Schema::hasColumn('countries', 'alpha_code'))   $countryIso2Col = 'alpha_code';
        else {
            $this->command?->error("La tabla countries no tiene alpha2_code ni alpha_code (ISO-2).");
            return;
        }

        $rows = [];
        $read = 0; $kept = 0;

        foreach ($this->files as $path) {
            $abs = base_path($path);
            if (!is_readable($abs)) {
                $this->command?->warn("No puedo leer: {$path} (lo salto)");
                continue;
            }

            [$fh, $delim] = $this->openWithDetectedDelimiter($abs);
            if (!$fh) { $this->command?->warn("Error al abrir: {$path}"); continue; }

            while (($r = fgetcsv($fh, 0, $delim)) !== false) {
                $read++;
                if (count($r) < 3) continue;

                // detectar posición de ISO-2 en la fila (0 o 1 típicamente)
                $isoIdx = $this->detectIso2Index($r);
                if ($isoIdx === null) continue;

                $iso2  = strtoupper(trim((string)($r[$isoIdx]     ?? '')));   // país (AR, FR, US)
                $loc3  = strtoupper(trim((string)($r[$isoIdx + 1] ?? '')));   // LOCODE ciudad (BUE, CDG, …)
                $city  = $this->toUtf8(trim((string)($r[$isoIdx + 2] ?? ''))); // city_name
                $subdv = $this->toUtf8(trim((string)($r[$isoIdx + 3] ?? ''))); // subdivisión/estado

                if ($iso2 === '' || $loc3 === '' || $city === '' || $iso2 === 'COUNTRY') continue;
                if (in_array($iso2, $this->skipIso2, true)) continue;

                // detectar Function y Coordinates según variante (con o sin columna '#' al inicio)
                $func = $this->pickFirst(
                    [$r[$isoIdx + 5] ?? null, $r[$isoIdx + 4] ?? null],
                    fn($v) => $this->looksLikeFunction($v)
                );
                $coord = $this->pickFirst(
                    [$r[$isoIdx + 9] ?? null, $r[$isoIdx + 8] ?? null],
                    fn($v) => $this->looksLikeCoordinates($v)
                );

                $func = strtoupper(trim((string)$func));
                if ($this->waterOnly) {
                    $hasSea    = strpos($func, '1') !== false;
                    $hasInland = strpos($func, '8') !== false;
                    if (!$hasSea && !$hasInland) continue;
                    $portType = $hasSea && $hasInland ? 'mixed' : ($hasSea ? 'maritime' : 'river');
                } else {
                    $portType = 'mixed';
                }

                // país ya debe existir (no crear aquí)
                $countryId = DB::table('countries')->where($countryIso2Col, $iso2)->value('id');
                if (!$countryId) continue;

                // coordenadas
                [$lat, $lng] = $this->parseUnlocodeCoordinates($coord);

                // armar fila respetando longitudes de tu migración
                $code = $iso2 . $loc3;
                $rows[] = [
                    'code'               => $code,                        // p.ej. ARBUE
                    'name'               => $this->clip($city, 150),
                    'short_name'         => $this->clip($city, 50),
                    'local_name'         => $this->clip($city, 150),
                    'country_id'         => $countryId,
                    'city'               => $this->clip($city, 100),
                    'province_state'     => $this->clip($subdv ?: null, 100),
                    'latitude'           => $lat,
                    'longitude'          => $lng,
                    'port_type'          => $portType,
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
        }

        if ($rows) $this->flushBatch($rows);

        $this->command?->info("Leídas: {$read}. Importadas (solo agua): {$kept}.");
    }

    /* ================== Helpers ================== */

    /** Detecta ; o , como separador (elige el que produce más columnas) */
    protected function openWithDetectedDelimiter(string $file): array
    {
        $sample = @file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        $sample = array_slice($sample, 0, 50);
        $score = function($delim) use ($sample) {
            $m = 0; foreach ($sample as $line) { $m = max($m, count(str_getcsv($line, $delim))); } return $m;
        };
        $delim = $score(';') > $score(',') ? ';' : ',';
        return [fopen($file, 'r'), $delim];
    }

    /** Devuelve índice donde está el ISO-2 en la fila (0 ó 1 según variante) */
    protected function detectIso2Index(array $r): ?int
    {
        for ($i = 0; $i <= 1; $i++) {
            $v = isset($r[$i]) ? strtoupper(trim((string)$r[$i])) : '';
            if (preg_match('/^[A-Z]{2}$/', $v)) return $i;
        }
        return null;
    }

    /** Decide entre varias posiciones posibles usando un validador */
    protected function pickFirst(array $candidates, callable $isValid)
    {
        foreach ($candidates as $v) {
            if ($isValid($v ?? '')) return $v;
        }
        return null;
    }

    /** UN/LOCODE Function (7 chars de dígitos y guiones) */
    protected function looksLikeFunction(?string $v): bool
    {
        if ($v === null) return false;
        $s = strtoupper(trim((string)$v));
        if ($s === '') return false;
        // patrón relajado: debe contener al menos un dígito y tener largo 7±2
        return (bool) preg_match('/[0-9]/', $s) && strlen($s) >= 5 && strlen($s) <= 9;
    }

    /** Coordenadas tipo "DDMMN DDDMMW" (espacio opcional) */
    protected function looksLikeCoordinates(?string $v): bool
    {
        if ($v === null) return false;
        $s = strtoupper(preg_replace('/\s+/', '', trim((string)$v)));
        return (bool) preg_match('/^\d{4}[NS]\d{5}[EW]$/', $s);
    }

    /** “DDMMN DDDMMW” → decimales */
    protected function parseUnlocodeCoordinates(?string $coord): array
    {
        if (!$coord) return [null, null];
        $c = strtoupper(preg_replace('/\s+/', '', trim($coord)));
        if (!preg_match('/^(\d{2})(\d{2})([NS])(\d{3})(\d{2})([EW])$/', $c, $m)) return [null, null];
        [$full,$latD,$latM,$latH,$lonD,$lonM,$lonH] = $m;
        $lat = (int)$latD + ((int)$latM/60);
        $lon = (int)$lonD + ((int)$lonM/60);
        if ($latH === 'S') $lat *= -1;
        if ($lonH === 'W') $lon *= -1;
        return [round($lat, 8), round($lon, 8)];
    }

    /** Recorta a N chars (UTF-8) o null si queda vacío */
    protected function clip(?string $v, int $len): ?string
    {
        if ($v === null) return null;
        $v = trim($v);
        if ($v === '') return null;
        if (mb_strlen($v, 'UTF-8') > $len) $v = mb_substr($v, 0, $len, 'UTF-8');
        return $v;
    }

    protected function flushBatch(array $rows): void
    {
        DB::table('ports')->upsert(
            $rows,
            ['code'],
            ['name','short_name','local_name','country_id','city','province_state','latitude','longitude','port_type','updated_at']
        );
        $this->command?->line("Insertadas/actualizadas: " . count($rows));
    }

    /** Encoding seguro */
    protected function toUtf8(string $v): string
    {
        return mb_check_encoding($v, 'UTF-8') ? $v : @mb_convert_encoding($v, 'UTF-8', 'ISO-8859-1');
    }
}
