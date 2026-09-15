<?php

namespace App\Services\Imports;

use App\Models\BillOfLading;
use App\Services\Parsers\GuaranExcelParser;
use App\Services\Parsers\KlineDataParser;
use App\Services\Parsers\LoginXmlParser;
use App\Services\Parsers\NavsurTextParser;
use App\Services\Parsers\ParanaExcelParser;
use App\Services\Parsers\TfpTextParser;
use App\ValueObjects\ManifestParseResult;
use Carbon\Carbon;
use DateTimeInterface;
use InvalidArgumentException;

/**
 * Aplica las fechas operativas ingresadas al importar sin reemplazar fechas
 * reales del archivo cuando el formato las informa de manera explícita.
 *
 * La regla común es:
 * - salida: el archivo tiene prioridad; la fecha manual es respaldo;
 * - carga/descarga: la fecha manual completa lo que el archivo no informa;
 * - en formatos que no poseen una fecha de carga propia del BL, la fecha
 *   manual de carga reemplaza los defaults/derivaciones históricos del parser.
 *
 * No modifica bill_date: esa fecha continúa siendo documental y pertenece
 * exclusivamente a la fuente que corresponda a cada formato.
 */
class ManifestImportDateService
{
    /**
     * Formatos cuyo valor persistido de loading_date puede ser un default o una
     * derivación y no una fecha de carga explícita propia del conocimiento.
     *
     * GUARAN / PARANA: BL_DATE es fecha documental, no loading_date.
     * LOGIN / NAVSUR / TFP: el formato no informa loading_date.
     * K-LINE: el parser deriva loading_date de ETD o de la fecha de importación.
     *
     * Los formatos no incluidos conservan cualquier loading_date no nula que
     * ya haya resuelto su parser. Si queda nula, la fecha manual funciona como
     * respaldo igual que para el resto.
     */
    private const MANUAL_LOADING_WHEN_PROVIDED = [
        GuaranExcelParser::class,
        KlineDataParser::class,
        LoginXmlParser::class,
        NavsurTextParser::class,
        ParanaExcelParser::class,
        TfpTextParser::class,
    ];

    public function apply(
        object $parser,
        ManifestParseResult $result,
        array $options
    ): void {
        $manualDeparture = $this->normalizeDateTime(
            $options['departure_date'] ?? null
        );
        $manualLoading = $this->normalizeDate(
            $options['loading_date'] ?? null
        );
        $manualDischarge = $this->normalizeDate(
            $options['discharge_date'] ?? null
        );

        if (
            $manualDeparture === null
            && $manualLoading === null
            && $manualDischarge === null
        ) {
            return;
        }

        $voyage = $result->voyage;

        if (!$voyage) {
            return;
        }

        // La fecha del archivo siempre conserva prioridad para la salida.
        if ($manualDeparture !== null && $voyage->departure_date === null) {
            if (
                $voyage->estimated_arrival_date !== null
                && $manualDeparture->gt(
                    Carbon::parse($voyage->estimated_arrival_date)
                )
            ) {
                throw new InvalidArgumentException(
                    'La fecha de salida no puede ser posterior a la fecha estimada de llegada informada por el archivo.'
                );
            }

            $voyage->departure_date = $manualDeparture;
            $voyage->save();
        }

        if ($manualLoading === null && $manualDischarge === null) {
            return;
        }

        $parserClass = $parser::class;

        $bills = BillOfLading::query()
            ->whereHas('shipment', function ($query) use ($voyage) {
                $query->where('voyage_id', $voyage->id);
            })
            ->get();

        foreach ($bills as $bill) {
            $currentLoading = $bill->loading_date;
            $currentDischarge = $bill->discharge_date;

            $newLoading = $currentLoading;
            $newDischarge = $currentDischarge;

            if (
                $manualLoading !== null
                && $this->shouldApplyManualLoading(
                    $parserClass,
                    $currentLoading !== null
                )
            ) {
                $newLoading = $manualLoading;
            }

            // Si ya existe descarga persistida por el parser, se conserva.
            if ($manualDischarge !== null && $currentDischarge === null) {
                $newDischarge = $manualDischarge;
            }

            if (
                $newLoading !== null
                && $newDischarge !== null
                && Carbon::parse($newDischarge)->lt(Carbon::parse($newLoading))
            ) {
                throw new InvalidArgumentException(
                    "La fecha de descarga no puede ser anterior a la de carga en el conocimiento {$bill->bill_number}."
                );
            }

            $changes = [];

            if (!$this->sameDateTime($currentLoading, $newLoading)) {
                $changes['loading_date'] = $newLoading;
            }

            if (!$this->sameDateTime($currentDischarge, $newDischarge)) {
                $changes['discharge_date'] = $newDischarge;
            }

            if ($changes !== []) {
                $bill->update($changes);
            }
        }
    }

    public function shouldApplyManualLoading(
        string $parserClass,
        bool $hasCurrentLoading
    ): bool {
        if (!$hasCurrentLoading) {
            return true;
        }

        return in_array(
            $parserClass,
            self::MANUAL_LOADING_WHEN_PROVIDED,
            true
        );
    }

    private function normalizeDateTime(mixed $value): ?Carbon
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        return Carbon::parse((string) $value);
    }

    private function normalizeDate(mixed $value): ?Carbon
    {
        $date = $this->normalizeDateTime($value);

        return $date?->startOfDay();
    }

    private function sameDateTime(mixed $left, mixed $right): bool
    {
        if ($left === null || $right === null) {
            return $left === null && $right === null;
        }

        $leftValue = $left instanceof DateTimeInterface
            ? $left->format('Y-m-d H:i:s')
            : Carbon::parse($left)->format('Y-m-d H:i:s');

        $rightValue = $right instanceof DateTimeInterface
            ? $right->format('Y-m-d H:i:s')
            : Carbon::parse($right)->format('Y-m-d H:i:s');

        return $leftValue === $rightValue;
    }
}
