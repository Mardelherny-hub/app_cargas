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

    public function test_cuscar_source_dates_keep_priority_over_form_fallbacks(): void
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
            '2026-09-20 08:34:00',
            $dates['departure_date']
        );
        $this->assertSame(
            '2026-09-21 00:00:00',
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
