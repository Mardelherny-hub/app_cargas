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

    public function test_bl_date_is_preserved_without_inventing_loading_date(): void
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
}
