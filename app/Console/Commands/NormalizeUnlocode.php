<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Normaliza los archivos UN/LOCODE oficiales (Part1/2/3) en formato "maquetado"
 * a un CSV tabular con cabeceras estándar:
 * Country, Location, Name, SubDiv, Function, Status, Date, IATA, Coordinates, Remarks
 *
 * Uso:
 *   php artisan ports:normalize-unlocode storage/app/2024-2\ UNLOCODE\ CodeListPart1.csv \
 *       storage/app/2024-2\ UNLOCODE\ CodeListPart2.csv \
 *       storage/app/2024-2\ UNLOCODE\ CodeListPart3.csv \
 *       storage/app/unlocode_2024-2_columns.csv
 */
class NormalizeUnlocode extends Command
{
    protected $signature = 'ports:normalize-unlocode 
        {part1 : Ruta a CodeListPart1.csv}
        {part2 : Ruta a CodeListPart2.csv}
        {part3 : Ruta a CodeListPart3.csv}
        {output : Ruta al CSV de salida normalizado}';

    protected $description = 'Normaliza UN/LOCODE Part1/2/3 en un CSV tabular estándar';

    public function handle(): int
    {
        $parts = [
            $this->argument('part1'),
            $this->argument('part2'),
            $this->argument('part3'),
        ];
        $out   = $this->argument('output');

        $rows = [];
        foreach ($parts as $file) {
            if (!is_readable($file)) {
                $this->error("No puedo leer: {$file}");
                return self::FAILURE;
            }

            $this->info("Procesando {$file}...");
            $fh = fopen($file, 'r');
            if (!$fh) {
                $this->error("Error al abrir: {$file}");
                return self::FAILURE;
            }

            while (($cols = fgetcsv($fh)) !== false) {
                // Cada fila maquetada suele tener:
                // [0]=#, [1]=Country, [2]=Location, [3]=Name, [4]=SubDiv, [5]=Function, [6]=Status,
                // [7]=Date, [8]=IATA, [9]=Coordinates, [10]=Remarks
                if (count($cols) < 4) continue; // salteamos basura

                $country = trim($cols[1] ?? '');
                $location= trim($cols[2] ?? '');
                $name    = trim($cols[3] ?? '');

                if ($country === '' || $location === '' || $name === '' || strtoupper($country) === 'COUNTRY') {
                    continue; // salteamos cabeceras
                }

                $rows[] = [
                    'Country'     => $country,
                    'Location'    => $location,
                    'Name'        => $name,
                    'SubDiv'      => trim($cols[4] ?? ''),
                    'Function'    => trim($cols[5] ?? ''),
                    'Status'      => trim($cols[6] ?? ''),
                    'Date'        => trim($cols[7] ?? ''),
                    'IATA'        => trim($cols[8] ?? ''),
                    'Coordinates' => trim($cols[9] ?? ''),
                    'Remarks'     => trim($cols[10] ?? ''),
                ];
            }
            fclose($fh);
        }

        // Escribir CSV final
        $outFh = fopen($out, 'w');
        fputcsv($outFh, ['Country','Location','Name','SubDiv','Function','Status','Date','IATA','Coordinates','Remarks']);
        foreach ($rows as $row) {
            fputcsv($outFh, $row);
        }
        fclose($outFh);

        $this->info("Generado CSV normalizado: {$out}");
        $this->info("Total filas: " . count($rows));
        return self::SUCCESS;
    }
}
