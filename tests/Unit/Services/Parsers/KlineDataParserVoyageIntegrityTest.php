<?php

namespace Tests\Unit\Services\Parsers;

use App\Services\Parsers\KlineDataParser;
use ReflectionMethod;
use Tests\TestCase;

class KlineDataParserVoyageIntegrityTest extends TestCase
{
    private function invokeParser(
        string $method,
        array $arguments = []
    ): mixed {
        $parser = new KlineDataParser();

        $reflection = new ReflectionMethod(
            KlineDataParser::class,
            $method
        );

        $reflection->setAccessible(true);

        return $reflection->invokeArgs(
            $parser,
            $arguments
        );
    }

    public function test_missing_source_voyage_number_remains_unknown(): void
    {
        $info = $this->invokeParser(
            'extractVoyageInfo',
            [[]]
        );

        $this->assertNull($info['voyage_number']);
        $this->assertNull($info['voyage_ref']);
    }

    public function test_operator_voyage_number_is_used_when_source_is_missing(): void
    {
        $number = $this->invokeParser(
            'resolveVoyageNumber',
            [
                ['voyage_number' => null],
                ['voyage_number' => '07LSRPFSR'],
            ]
        );

        $this->assertSame(
            'KLINE-07LSRPFSR',
            $number
        );
    }

    public function test_source_voyage_number_has_priority(): void
    {
        $number = $this->invokeParser(
            'resolveVoyageNumber',
            [
                ['voyage_number' => 'SOURCE01'],
                ['voyage_number' => 'OPERATOR01'],
            ]
        );

        $this->assertSame(
            'KLINE-SOURCE01',
            $number
        );
    }

    public function test_missing_voyage_number_is_rejected(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage(
            'Debe ingresarlo al importar'
        );

        $this->invokeParser(
            'resolveVoyageNumber',
            [
                ['voyage_number' => null],
                [],
            ]
        );
    }

    public function test_missing_dates_remain_unknown(): void
    {
        $dates = $this->invokeParser(
            'resolveVoyageDates',
            [[], []]
        );

        $this->assertNull($dates['departure_date']);
        $this->assertNull(
            $dates['estimated_arrival_date']
        );
    }

    public function test_operator_departure_is_respected_without_fake_eta(): void
    {
        $dates = $this->invokeParser(
            'resolveVoyageDates',
            [
                [],
                [
                    'departure_date' =>
                        '2026-08-17 12:30:00',
                ],
            ]
        );

        $this->assertSame(
            '2026-08-17 12:30:00',
            $dates['departure_date']
                ->format('Y-m-d H:i:s')
        );

        $this->assertNull(
            $dates['estimated_arrival_date']
        );
    }

    public function test_source_dates_override_operator_departure(): void
    {
        $dates = $this->invokeParser(
            'resolveVoyageDates',
            [
                [
                    'etd' => '2025-05-14',
                    'eta' => '2025-05-20',
                ],
                [
                    'departure_date' => '2026-08-17',
                ],
            ]
        );

        $this->assertSame(
            '2025-05-14',
            $dates['departure_date']->toDateString()
        );

        $this->assertSame(
            '2025-05-20',
            $dates['estimated_arrival_date']
                ->toDateString()
        );
    }
}
