<?php

namespace Tests\Unit\Services\Parsers;

use App\Services\Parsers\G2OceanXmlParser;
use ReflectionMethod;
use Tests\TestCase;

class G2OceanXmlParserCoreIntegrityTest extends TestCase
{
    private G2OceanXmlParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = app(G2OceanXmlParser::class);
    }

    private function invoke(string $method, array $args = [])
    {
        $ref = new ReflectionMethod(
            G2OceanXmlParser::class,
            $method
        );

        $ref->setAccessible(true);

        return $ref->invokeArgs($this->parser, $args);
    }

    public function test_date_only_has_no_runtime_time(): void
    {
        $date = $this->invoke(
            'parseDate',
            ['20/02/2025']
        );

        $this->assertSame(
            '2025-02-20 00:00:00',
            $date->format('Y-m-d H:i:s')
        );
    }

    public function test_marks_are_not_fabricated(): void
    {
        $this->assertNull(
            $this->invoke(
                'normalizeG2OceanCargoMarks',
                ['']
            )
        );

        $this->assertSame(
            'N/M',
            $this->invoke(
                'normalizeG2OceanCargoMarks',
                ['N/M']
            )
        );
    }

    public function test_package_quantity_is_strict(): void
    {
        $this->assertSame(
            279,
            $this->invoke(
                'parseG2OceanPackageQuantity',
                [279]
            )
        );
    }

    public function test_zero_packages_fail(): void
    {
        $this->expectException(\Exception::class);

        $this->invoke(
            'parseG2OceanPackageQuantity',
            [0]
        );
    }

    public function test_mt_is_converted_without_net_estimate(): void
    {
        $this->assertSame(
            22180.0,
            $this->invoke(
                'g2OceanGrossKg',
                [22.18]
            )
        );
    }

    public function test_prepaid_comes_from_source(): void
    {
        $this->assertSame(
            'prepaid',
            $this->invoke(
                'resolveG2OceanFreightTerms',
                [[[
                    'description' =>
                        'PREPAID ABROAD L/C NO. 123',
                ]]]
            )
        );
    }

    public function test_collect_comes_from_source(): void
    {
        $this->assertSame(
            'collect',
            $this->invoke(
                'resolveG2OceanFreightTerms',
                [[[
                    'description' => 'FREIGHT COLLECT',
                ]]]
            )
        );
    }

    public function test_unknown_freight_is_not_fabricated(): void
    {
        $this->assertNull(
            $this->invoke(
                'resolveG2OceanFreightTerms',
                [[[
                    'description' => 'STEEL PLATES',
                ]]]
            )
        );
    }

    public function test_freight_amount_does_not_imply_payment_terms(): void
    {
        $this->assertNull(
            $this->invoke(
                'resolveG2OceanFreightTerms',
                [[[
                    'description' => 'FREIGHT : USD18600',
                ]]]
            )
        );
    }
}
