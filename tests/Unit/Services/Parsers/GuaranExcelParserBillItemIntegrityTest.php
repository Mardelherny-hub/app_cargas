<?php

namespace Tests\Unit\Services\Parsers;

use App\Services\Parsers\GuaranExcelParser;
use Carbon\Carbon;
use ReflectionMethod;
use Tests\TestCase;

class GuaranExcelParserBillItemIntegrityTest extends TestCase
{
    private GuaranExcelParser $parser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->parser = app(GuaranExcelParser::class);
    }

    private function invoke(string $method, array $args = [])
    {
        $reflection = new ReflectionMethod(
            GuaranExcelParser::class,
            $method
        );

        $reflection->setAccessible(true);

        return $reflection->invokeArgs(
            $this->parser,
            $args
        );
    }

    public function test_bl_date_is_preserved_without_inventing_operational_dates(): void
    {
        $dates = $this->invoke(
            'buildBillDocumentDates',
            ['30/06/2025']
        );

        $this->assertInstanceOf(
            Carbon::class,
            $dates['bill_date']
        );

        $this->assertSame(
            '2025-06-30',
            $dates['bill_date']->format('Y-m-d')
        );

        $this->assertNull(
            $dates['loading_date']
        );

        $this->assertNull(
            $dates['discharge_date']
        );
    }

    public function test_explicit_import_dates_are_preserved(): void
    {
        $dates = $this->invoke(
            'buildBillDocumentDates',
            [
                '30/06/2025',
                '2025-07-01',
                '2025-07-03',
            ]
        );

        $this->assertSame(
            '2025-06-30',
            $dates['bill_date']->format('Y-m-d')
        );

        $this->assertSame(
            '2025-07-01',
            $dates['loading_date']->format('Y-m-d')
        );

        $this->assertSame(
            '2025-07-03',
            $dates['discharge_date']->format('Y-m-d')
        );
    }

    public function test_discharge_before_loading_is_rejected(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage(
            'La fecha de descarga no puede ser anterior a la fecha de carga'
        );

        $this->invoke(
            'buildBillDocumentDates',
            [
                '30/06/2025',
                '2025-07-03',
                '2025-07-01',
            ]
        );
    }

    public function test_invalid_explicit_loading_date_is_rejected(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage(
            'Fecha de carga inválida'
        );

        $this->invoke(
            'buildBillDocumentDates',
            [
                '30/06/2025',
                'NO-ES-FECHA',
                null,
            ]
        );
    }

    public function test_invalid_bl_date_is_rejected(): void
    {
        $this->expectException(\Exception::class);

        $this->invoke(
            'buildBillDocumentDates',
            ['NO-ES-FECHA']
        );
    }

    public function test_real_package_quantities_are_preserved(): void
    {
        $this->assertSame(
            1,
            $this->invoke(
                'parsePackageQuantity',
                ['1']
            )
        );

        $this->assertSame(
            10800,
            $this->invoke(
                'parsePackageQuantity',
                ['10800']
            )
        );

        $this->assertSame(
            2768,
            $this->invoke(
                'parsePackageQuantity',
                [' 2768 ']
            )
        );
    }

    public function test_missing_package_quantity_is_not_fabricated(): void
    {
        $this->expectException(\Exception::class);

        $this->invoke(
            'parsePackageQuantity',
            [null]
        );
    }

    public function test_non_integer_package_quantity_is_rejected(): void
    {
        $this->expectException(\Exception::class);

        $this->invoke(
            'parsePackageQuantity',
            ['10,5']
        );
    }

    public function test_zero_package_quantity_is_rejected(): void
    {
        $this->expectException(\Exception::class);

        $this->invoke(
            'parsePackageQuantity',
            ['0']
        );
    }

    public function test_parser_no_longer_has_origin_country_inference_helper(): void
    {
        $this->assertFalse(
            method_exists(
                GuaranExcelParser::class,
                'determineOriginCountry'
            )
        );
    }

    public function test_missing_cargo_marks_are_not_fabricated(): void
    {
        $this->assertNull(
            $this->invoke(
                'normalizeGuaranCargoMarks',
                [null]
            )
        );

        $this->assertNull(
            $this->invoke(
                'normalizeGuaranCargoMarks',
                ['   ']
            )
        );
    }

    public function test_real_cargo_marks_are_preserved(): void
    {
        $this->assertSame(
            'ABC 123',
            $this->invoke(
                'normalizeGuaranCargoMarks',
                [' ABC 123 ']
            )
        );
    }

    public function test_single_guaran_ncm_is_not_invented_as_tariff_position(): void
    {
        $classification = $this->invoke(
            'buildCommodityClassification',
            ['0202']
        );

        $this->assertSame(
            '0202',
            $classification['commodity_code']
        );

        $this->assertNull(
            $classification['tariff_position']
        );
    }

    public function test_multiple_guaran_ncm_codes_are_preserved_as_source_data(): void
    {
        $classification = $this->invoke(
            'buildCommodityClassification',
            ['0202, 0206, 0504']
        );

        $this->assertSame(
            '0202, 0206, 0504',
            $classification['commodity_code']
        );

        $this->assertNull(
            $classification['tariff_position']
        );
    }

    public function test_missing_ncm_remains_missing(): void
    {
        $classification = $this->invoke(
            'buildCommodityClassification',
            [null]
        );

        $this->assertNull(
            $classification['commodity_code']
        );

        $this->assertNull(
            $classification['tariff_position']
        );
    }

}
