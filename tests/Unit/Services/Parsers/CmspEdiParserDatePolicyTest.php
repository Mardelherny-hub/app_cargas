<?php

namespace Tests\Unit\Services\Parsers;

use App\Services\Parsers\CmspEdiParserCompat;
use PHPUnit\Framework\TestCase;

class CmspEdiParserDatePolicyTest extends TestCase
{
    public function test_inverted_source_dates_are_rejected(): void
    {
        $parser = new class extends CmspEdiParserCompat {
            public function check(
                string $departure,
                string $arrival,
                array $data
            ): array {
                $this->assertCuscarChronology(
                    $departure,
                    $arrival,
                    $data
                );

                return $this->stats['warnings'];
            }
        };

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage(
            'La fecha de salida 2026-09-22 (DTM+136) no puede ser posterior a la fecha estimada de llegada 2026-09-21 (DTM+132).'
        );

        $parser->check(
            '2026-09-22 00:00:00',
            '2026-09-21 00:00:00',
            [
                'dates' => [
                    'departure' => '2026-09-22 00:00:00',
                    'estimated_arrival' => '2026-09-21 00:00:00',
                ],
            ]
        );
    }

    public function test_valid_chronology_has_no_warning(): void
    {
        $parser = new class extends CmspEdiParserCompat {
            public function check(
                string $departure,
                string $arrival
            ): array {
                $this->assertCuscarChronology(
                    $departure,
                    $arrival,
                    []
                );

                return $this->stats['warnings'];
            }
        };

        $warnings = $parser->check(
            '2026-09-20 08:34:00',
            '2026-09-21 00:00:00'
        );

        $this->assertSame([], $warnings);
    }

    public function test_operator_dates_override_cuscar_source_when_completed(): void
    {
        $parser = new class extends CmspEdiParserCompat {
            public function dates(
                array $data,
                array $options
            ): array {
                return $this->resolveCuscarOperationalDates(
                    $data,
                    $options
                );
            }
        };

        $dates = $parser->dates(
            [
                'dates' => [
                    'departure' => '2026-09-20 08:34:00',
                    'estimated_arrival' => '2026-09-21 00:00:00',
                ],
            ],
            [
                'departure_date' => '2026-10-01',
                'discharge_date' => '2026-10-02',
            ]
        );

        $this->assertSame(
            '2026-10-01',
            $dates['departure_date']
        );
        $this->assertSame(
            '2026-10-02',
            $dates['estimated_arrival_date']
        );
    }

    public function test_valid_operator_dates_bypass_inverted_source_dates(): void
    {
        $parser = new class extends CmspEdiParserCompat {
            public function effectiveDates(
                array $data,
                array $options
            ): array {
                $dates = $this->resolveCuscarOperationalDates(
                    $data,
                    $options
                );

                $this->assertCuscarChronology(
                    $dates['departure_date'],
                    $dates['estimated_arrival_date'],
                    $data,
                    $options
                );

                return $dates;
            }
        };

        $dates = $parser->effectiveDates(
            [
                'dates' => [
                    'departure' => '2026-09-24 10:00:00',
                    'estimated_arrival' => '2026-09-20 00:00:00',
                ],
            ],
            [
                'departure_date' => '2026-09-19T10:01',
                'discharge_date' => '2026-09-23',
            ]
        );

        $this->assertSame(
            '2026-09-19T10:01',
            $dates['departure_date']
        );
        $this->assertSame(
            '2026-09-23',
            $dates['estimated_arrival_date']
        );
    }

    public function test_invalid_operator_dates_are_rejected_as_form_values(): void
    {
        $parser = new class extends CmspEdiParserCompat {
            public function check(
                array $data,
                array $options
            ): void {
                $dates = $this->resolveCuscarOperationalDates(
                    $data,
                    $options
                );

                $this->assertCuscarChronology(
                    $dates['departure_date'],
                    $dates['estimated_arrival_date'],
                    $data,
                    $options
                );
            }
        };

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage(
            'La fecha de salida 2026-09-24 (formulario) no puede ser posterior a la fecha estimada de llegada 2026-09-23 (formulario).'
        );

        $parser->check(
            [
                'dates' => [
                    'departure' => '2026-09-19 00:00:00',
                    'estimated_arrival' => '2026-09-25 00:00:00',
                ],
            ],
            [
                'departure_date' => '2026-09-24T10:01',
                'discharge_date' => '2026-09-23',
            ]
        );
    }

    public function test_partial_operator_departure_is_rejected_even_if_source_has_arrival(): void
    {
        $parser = new class extends CmspEdiParserCompat {
            public function dates(array $data, array $options): array
            {
                return $this->resolveCuscarOperationalDates(
                    $data,
                    $options
                );
            }
        };

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage(
            'deben completarse juntas o dejarse ambas vacías'
        );

        $parser->dates(
            [
                'dates' => [
                    'departure' => '2026-09-18 08:00:00',
                    'estimated_arrival' => '2026-09-23 00:00:00',
                ],
            ],
            [
                'departure_date' => '2026-09-19T10:01',
                'discharge_date' => null,
            ]
        );
    }

    public function test_partial_operator_discharge_is_rejected_even_if_source_has_departure(): void
    {
        $parser = new class extends CmspEdiParserCompat {
            public function dates(array $data, array $options): array
            {
                return $this->resolveCuscarOperationalDates(
                    $data,
                    $options
                );
            }
        };

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage(
            'deben completarse juntas o dejarse ambas vacías'
        );

        $parser->dates(
            [
                'dates' => [
                    'departure' => '2026-09-18 08:00:00',
                    'estimated_arrival' => '2026-09-23 00:00:00',
                ],
            ],
            [
                'departure_date' => null,
                'discharge_date' => '2026-09-23',
            ]
        );
    }

    public function test_empty_operator_pair_never_invents_or_mixes_source_dates(): void
    {
        $parser = new class extends CmspEdiParserCompat {
            public function dates(array $data): array
            {
                return $this->resolveCuscarOperationalDates(
                    $data,
                    [
                        'departure_date' => null,
                        'discharge_date' => null,
                    ]
                );
            }
        };

        $both = $parser->dates([
            'dates' => [
                'departure' => '2026-09-19 10:01:00',
                'estimated_arrival' => '2026-09-23 00:00:00',
            ],
        ]);

        $this->assertSame(
            '2026-09-19 10:01:00',
            $both['departure_date']
        );
        $this->assertSame(
            '2026-09-23 00:00:00',
            $both['estimated_arrival_date']
        );

        $arrivalOnly = $parser->dates([
            'dates' => [
                'estimated_arrival' => '2026-09-23 00:00:00',
            ],
        ]);

        $this->assertNull($arrivalOnly['departure_date']);
        $this->assertSame(
            '2026-09-23 00:00:00',
            $arrivalOnly['estimated_arrival_date']
        );

        $departureOnly = $parser->dates([
            'dates' => [
                'departure' => '2026-09-19 10:01:00',
            ],
        ]);

        $this->assertSame(
            '2026-09-19 10:01:00',
            $departureOnly['departure_date']
        );
        $this->assertNull(
            $departureOnly['estimated_arrival_date']
        );

        $neither = $parser->dates(['dates' => []]);

        $this->assertNull($neither['departure_date']);
        $this->assertNull($neither['estimated_arrival_date']);
    }

    public function test_manual_pair_uses_only_form_values_even_when_source_has_both(): void
    {
        $parser = new class extends CmspEdiParserCompat {
            public function dates(array $data, array $options): array
            {
                return $this->resolveCuscarOperationalDates(
                    $data,
                    $options
                );
            }
        };

        $dates = $parser->dates(
            [
                'dates' => [
                    'departure' => '2026-03-03 15:05:00',
                    'estimated_arrival' => '2026-03-02 00:00:00',
                ],
            ],
            [
                'departure_date' => '2026-09-19T10:01',
                'discharge_date' => '2026-09-23',
            ]
        );

        $this->assertSame(
            '2026-09-19T10:01',
            $dates['departure_date']
        );
        $this->assertSame(
            '2026-09-23',
            $dates['estimated_arrival_date']
        );
    }

    public function test_manifest_history_uses_late_static_binding_for_parser_class(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 4)
            . '/app/Services/Parsers/CmspEdiParser.php'
        );

        $this->assertStringContainsString(
            "'parser_class' => static::class",
            $source
        );
        $this->assertStringNotContainsString(
            "'parser_class' => self::class",
            $source
        );
    }
}
