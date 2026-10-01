<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

return new class extends Migration
{
    public function up(): void
    {
        $companyId = DB::table('companies')
            ->where('tax_id', '30612732503')
            ->value('id');

        $voyage = DB::table('voyages')
            ->where('id', 369)
            ->where('company_id', $companyId)
            ->where('voyage_number', 'ABX 2620S')
            ->first();

        if (!$companyId || !$voyage) {
            return;
        }

        $import = DB::table('manifest_imports')
            ->where('voyage_id', $voyage->id)
            ->where('file_name', 'like', '504abx2620s%')
            ->where('status', 'completed')
            ->orderByDesc('id')
            ->first();
        if (!$import) {
            throw new RuntimeException('No se encontró la importación fuente de ABX 2620S.');
        }

        $sourcePath = storage_path(
            'app/private/imports/manifests/' . $import->file_name
        );

        if (!is_file($sourcePath)) {
            throw new RuntimeException('No se encontró el XLS fuente de ABX 2620S.');
        }

        $rows = IOFactory::load($sourcePath)
            ->getActiveSheet()
            ->toArray(null, true, true, true);

        $headerRow = null;
        $headerIndex = null;
        foreach (array_slice($rows, 0, 20, true) as $index => $row) {
            if (
                in_array('BL NUMBER', $row, true)
                && in_array('BARGE ID', $row, true)
            ) {
                $headerRow = $row;
                $headerIndex = $index;
                break;
            }
        }

        if (!$headerRow || !$headerIndex) {
            throw new RuntimeException('No se reconoció la cabecera del XLS Guaran.');
        }
        $columns = [];
        foreach ($headerRow as $column => $label) {
            $columns[trim((string) $label)] = $column;
        }

        foreach ([
            'BL NUMBER',
            'POL',
            'POL TERMINAL',
            'PACK TYPE',
            'MARKS & DESCRIPTION',
        ] as $required) {
            if (!isset($columns[$required])) {
                throw new RuntimeException("Falta columna {$required} en el XLS Guaran.");
            }
        }

        $sourceByBill = [];
        foreach ($rows as $index => $row) {
            if ($index <= $headerIndex) {
                continue;
            }

            $billNumber = trim((string) ($row[$columns['BL NUMBER']] ?? ''));
            if ($billNumber === '') {
                continue;
            }

            $sourceByBill[$billNumber][] = $row;
        }

        $resolved = [];
        foreach ($sourceByBill as $billNumber => $sourceRows) {
            $bill = DB::table('bills_of_lading')
                ->where('bill_number', $billNumber)
                ->whereIn(
                    'shipment_id',
                    DB::table('shipments')
                        ->where('voyage_id', $voyage->id)
                        ->pluck('id')
                )
                ->first();

            if (!$bill) {
                throw new RuntimeException(
                    "No se encontró el conocimiento {$billNumber} del viaje."
                );
            }

            $items = DB::table('shipment_items')
                ->where('bill_of_lading_id', $bill->id)
                ->orderBy('line_number')
                ->get();

            if ($items->count() !== count($sourceRows)) {
                throw new RuntimeException(
                    "No coincide la cantidad de líneas del conocimiento {$billNumber}."
                );
            }

            $resolved[] = [
                'bill' => $bill,
                'items' => $items,
                'rows' => $sourceRows,
            ];
        }

        DB::transaction(function () use ($resolved, $columns) {
            foreach ($resolved as $entry) {
                $bill = $entry['bill'];
                $sourceRows = $entry['rows'];

                $terminals = collect($sourceRows)
                    ->map(fn ($row) => trim((string) ($row[$columns['POL TERMINAL']] ?? '')))
                    ->filter()
                    ->unique()
                    ->values();

                $countries = collect($sourceRows)
                    ->map(fn ($row) => strtoupper(substr(
                        trim((string) ($row[$columns['POL']] ?? '')),
                        0,
                        2
                    )))
                    ->filter(fn ($value) => preg_match('/^[A-Z]{2}$/', $value))
                    ->unique()
                    ->values();

                $marks = collect($sourceRows)
                    ->map(fn ($row) => trim((string) ($row[$columns['MARKS & DESCRIPTION']] ?? '')))
                    ->filter()
                    ->unique()
                    ->values();

                $billUpdate = ['updated_at' => now()];
                if ($terminals->count() === 1 && empty($bill->origin_location)) {
                    $billUpdate['origin_location'] = $terminals->first();
                }
                if ($countries->count() === 1 && empty($bill->origin_country_code)) {
                    $billUpdate['origin_country_code'] = $countries->first();
                }
                if ($marks->count() === 1 && empty($bill->cargo_marks)) {
                    $billUpdate['cargo_marks'] = $marks->first();
                }

                if (count($billUpdate) > 1) {
                    DB::table('bills_of_lading')
                        ->where('id', $bill->id)
                        ->update($billUpdate);
                }

                foreach ($entry['items']->values() as $index => $item) {
                    $packType = trim((string) (
                        $sourceRows[$index][$columns['PACK TYPE']] ?? ''
                    ));

                    if ($packType === '' || !empty($item->package_type_description)) {
                        continue;
                    }

                    DB::table('shipment_items')
                        ->where('id', $item->id)
                        ->update([
                            'package_type_description' => $packType,
                            'updated_at' => now(),
                        ]);
                }
            }
        });
    }

    public function down(): void
    {
        // Reparación de datos respaldada por el XLS fuente: no se revierte
        // automáticamente para evitar eliminar información válida.
    }
};
