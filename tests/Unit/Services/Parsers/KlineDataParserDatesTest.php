<?php

namespace Tests\Unit\Services\Parsers;

use App\Services\Parsers\KlineDataParser;
use ReflectionMethod;
use Tests\TestCase;

class KlineDataParserDatesTest extends TestCase
{
    private function resolveDates(
        array $options = [],
        array $extractedDates = []
    ): array {
        $parser = new KlineDataParser();

        $method = new ReflectionMethod(
            KlineDataParser::class,
            'resolveVoyageDates'
        );

        $method->setAccessible(true);

        return $method->invoke($parser, $options, $extractedDates);
    }

    public function test_voyage_dates_are_not_fabricated_and_follow_priority_contract(): void
    {
        // Sin ninguna fuente: no fabricar fechas.
        $dates = $this->resolveDates();

        $this->assertNull($dates['etd']);
        $this->assertNull($dates['eta']);

        // Fechas inequívocamente extraídas del archivo.
        $dates = $this->resolveDates([], [
            'etd' => '2025-04-29',
            'eta' => '2025-05-10',
        ]);

        $this->assertSame('2025-04-29', $dates['etd']->format('Y-m-d'));
        $this->assertSame('2025-05-10', $dates['eta']->format('Y-m-d'));

        // Options explícitas tienen prioridad.
        $dates = $this->resolveDates([
            'dates' => [
                'etd' => '2025-06-01',
                'eta' => '2025-06-15',
            ],
        ], [
            'etd' => '2025-04-29',
            'eta' => '2025-05-10',
        ]);

        $this->assertSame('2025-06-01', $dates['etd']->format('Y-m-d'));
        $this->assertSame('2025-06-15', $dates['eta']->format('Y-m-d'));

        // Una opción parcial sólo reemplaza el campo informado.
        $dates = $this->resolveDates([
            'dates' => [
                'etd' => '2025-07-01',
            ],
        ], [
            'etd' => '2025-04-29',
            'eta' => '2025-05-10',
        ]);

        $this->assertSame('2025-07-01', $dates['etd']->format('Y-m-d'));
        $this->assertSame('2025-05-10', $dates['eta']->format('Y-m-d'));

        // Una opción vacía no debe borrar una fecha válida del archivo.
        $dates = $this->resolveDates([
            'dates' => [
                'etd' => '',
                'eta' => null,
            ],
        ], [
            'etd' => '2025-04-29',
            'eta' => '2025-05-10',
        ]);

        $this->assertSame('2025-04-29', $dates['etd']->format('Y-m-d'));
        $this->assertSame('2025-05-10', $dates['eta']->format('Y-m-d'));
    }
}
