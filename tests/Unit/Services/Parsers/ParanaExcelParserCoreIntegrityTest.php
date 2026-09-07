<?php

namespace Tests\Unit\Services\Parsers;

use App\Services\Parsers\ParanaExcelParser;
use ReflectionMethod;
use Tests\TestCase;

class ParanaExcelParserCoreIntegrityTest extends TestCase
{
    private ParanaExcelParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = app(ParanaExcelParser::class);
    }

    private function invoke(string $method, array $args = [])
    {
        $reflection = new ReflectionMethod(
            ParanaExcelParser::class,
            $method
        );
        $reflection->setAccessible(true);

        return $reflection->invokeArgs(
            $this->parser,
            $args
        );
    }

    public function test_voyage_dates_are_not_invented(): void
    {
        $timing = $this->invoke(
            'buildParanaVoyageTiming'
        );

        $this->assertNull($timing['departure_date']);
        $this->assertNull(
            $timing['estimated_arrival_date']
        );
    }

    public function test_missing_bl_date_remains_missing(): void
    {
        $dates = $this->invoke(
            'buildParanaBillDates',
            [null]
        );

        $this->assertNull($dates['bill_date']);
        $this->assertNull($dates['loading_date']);
    }

    public function test_loading_date_is_not_copied_from_bl_date(): void
    {
        $dates = $this->invoke(
            'buildParanaBillDates',
            ['2025-06-30']
        );

        $this->assertSame(
            '2025-06-30',
            $dates['bill_date']->format('Y-m-d')
        );

        $this->assertNull($dates['loading_date']);
    }

    public function test_packages_are_strict(): void
    {
        $this->assertSame(
            25,
            $this->invoke(
                'parseParanaPackageQuantity',
                ['25']
            )
        );
    }

    public function test_zero_packages_are_rejected(): void
    {
        $this->expectException(\Exception::class);

        $this->invoke(
            'parseParanaPackageQuantity',
            ['0']
        );
    }

    public function test_missing_marks_are_not_fabricated(): void
    {
        $this->assertNull(
            $this->invoke(
                'normalizeParanaCargoMarks',
                [null]
            )
        );

        $this->assertNull(
            $this->invoke(
                'normalizeParanaCargoMarks',
                ['N/A']
            )
        );
    }

    public function test_container_status_is_strict(): void
    {
        $full = $this->invoke(
            'mapParanaContainerState',
            ['F']
        );

        $this->assertSame('L', $full['condition']);
        $this->assertSame(
            'loaded',
            $full['operational_status']
        );

        $empty = $this->invoke(
            'mapParanaContainerState',
            ['E']
        );

        $this->assertSame('V', $empty['condition']);
        $this->assertSame(
            'empty',
            $empty['operational_status']
        );
    }

    public function test_real_supported_container_types_resolve(): void
    {
        $cases = [
            '20DV' => '20GP',
            '40DV' => '40GP',
            '40HC' => '40HC',
            '40RH' => '40RH',
            '20TN' => '20TN',
        ];

        foreach ($cases as $source => $expected) {
            $type = $this->invoke(
                'findExistingContainerType',
                [$source]
            );

            $this->assertSame(
                $expected,
                $type->code,
                $source
            );
        }
    }

    public function test_40fr_is_rejected_instead_of_fabricated_as_40hc(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('40FR');

        $this->invoke(
            'findExistingContainerType',
            ['40FR']
        );
    }
}
