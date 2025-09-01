<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CountriesAllToFull extends Command
{
    protected $signature = 'countries:all-to-full
        {in : Ruta al all.csv (lukes ISO-3166)}
        {--out=database/data/countries_full.csv : Ruta de salida del CSV final}';

    protected $description = 'Convierte all.csv (ISO-3166) al formato countries_full.csv usado por BaseCatalogsSeeder';

    public function handle(): int
    {
        $in  = $this->argument('in');
        $out = base_path($this->option('out'));

        if (!is_readable($in)) {
            $this->error("No puedo leer: {$in}");
            return self::FAILURE;
        }

        $fh = fopen($in, 'r');
        if (!$fh) {
            $this->error("No pude abrir: {$in}");
            return self::FAILURE;
        }

        // Leer cabecera de all.csv (lukes): name,alpha-2,alpha-3,country-code,...
        $header = fgetcsv($fh);
        if (!$header) {
            fclose($fh);
            $this->error("CSV vacío o sin cabeceras: {$in}");
            return self::FAILURE;
        }
        $map = [];
        foreach ($header as $i => $h) {
            $map[strtolower(trim((string)$h))] = $i;
        }

        // Campos mínimos requeridos
        foreach (['alpha-2','alpha-3','country-code','name'] as $need) {
            if (!array_key_exists($need, $map)) {
                fclose($fh);
                $this->error("Falta columna '{$need}' en {$in}");
                return self::FAILURE;
            }
        }

        // Preparar salida
        $outDir = dirname($out);
        if (!is_dir($outDir)) @mkdir($outDir, 0777, true);
        $ofh = fopen($out, 'w');
        if (!$ofh) {
            fclose($fh);
            $this->error("No puedo escribir: {$out}");
            return self::FAILURE;
        }

        // Cabecera EXACTA que usa tu BaseCatalogsSeeder
        $outHeader = [
            'iso_code','alpha2_code','numeric_code','name','official_name',
            'nationality','customs_code','senasa_code','document_format','currency_code',
            'timezone','primary_language','allows_import','allows_export','allows_transit',
            'requires_visa','active','display_order','is_primary'
        ];
        fputcsv($ofh, $outHeader);

        $display = 1;
        $rows = 0;

        while (($r = fgetcsv($fh)) !== false) {
            $alpha2  = strtoupper(trim((string)($r[$map['alpha-2']] ?? '')));
            $alpha3  = strtoupper(trim((string)($r[$map['alpha-3']] ?? '')));
            $numeric = trim((string)($r[$map['country-code']] ?? ''));
            $name    = trim((string)($r[$map['name']] ?? ''));

            if ($alpha2 === '' || $alpha3 === '') {
                continue; // fila incompleta
            }

            // Escribimos usando defaults neutros donde no hay dato fuente
            $row = [
                $alpha3,            // iso_code (alpha-3)
                $alpha2,            // alpha2_code
                $numeric !== '' ? str_pad($numeric, 3, '0', STR_PAD_LEFT) : null, // numeric_code
                $name ?: $alpha3,   // name
                $name ?: null,      // official_name (no suponemos alternativo)
                null,               // nationality
                null,               // customs_code
                null,               // senasa_code
                null,               // document_format
                null,               // currency_code
                null,               // timezone
                null,               // primary_language
                1,                  // allows_import (neutral)
                1,                  // allows_export (neutral)
                1,                  // allows_transit (neutral)
                0,                  // requires_visa (neutral)
                1,                  // active
                $display++,         // display_order incremental
                0,                  // is_primary
            ];

            fputcsv($ofh, $row);
            $rows++;
        }

        fclose($fh);
        fclose($ofh);

        $this->info("✓ Generado: " . str_replace(base_path().'/', '', $out));
        $this->line("Total países exportados: {$rows}");
        $this->line("Ahora corré: php artisan db:seed --class=BaseCatalogsSeeder");
        return self::SUCCESS;
    }
}
