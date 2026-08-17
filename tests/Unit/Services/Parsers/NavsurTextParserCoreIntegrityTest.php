<?php

namespace Tests\Unit\Services\Parsers;

use App\Services\Parsers\NavsurTextParser;
use ReflectionMethod;
use Tests\TestCase;

class NavsurTextParserCoreIntegrityTest extends TestCase
{
    private NavsurTextParser $parser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->parser = app(
            NavsurTextParser::class
        );
    }

    private function invoke(
        string $method,
        array $args = []
    ) {
        $ref = new ReflectionMethod(
            NavsurTextParser::class,
            $method
        );

        $ref->setAccessible(true);

        return $ref->invokeArgs(
            $this->parser,
            $args
        );
    }

    public function test_latin_number_is_parsed_correctly(): void
    {
        $this->assertSame(
            21976.0,
            $this->invoke(
                'parseNavsurNumber',
                ['21.976,00']
            )
        );
    }

    public function test_net_weight_can_be_recovered_from_source_description(): void
    {
        $net = $this->invoke(
            'resolveNavsurNetWeight',
            [[
                'peso_neto' => '0',
                'mercaderia' =>
                    'TOTAL NET WEIGHT: 21.976,00 KGS',
            ]]
        );

        $this->assertSame(21976.0, $net);
    }

    public function test_missing_net_is_not_invented(): void
    {
        $this->assertNull(
            $this->invoke(
                'resolveNavsurNetWeight',
                [[
                    'peso_neto' => '0',
                    'mercaderia' => 'STEEL GOODS',
                ]]
            )
        );
    }

    public function test_package_quantity_is_strict(): void
    {
        $this->assertSame(
            10,
            $this->invoke(
                'parseNavsurPackageQuantity',
                ['10']
            )
        );
    }

    public function test_zero_packages_are_rejected(): void
    {
        $this->expectException(\Exception::class);

        $this->invoke(
            'parseNavsurPackageQuantity',
            ['0']
        );
    }

    public function test_unknown_freight_is_not_prepaid(): void
    {
        $this->assertNull(
            $this->invoke(
                'mapFreightTerms',
                ['']
            )
        );

        $this->assertNull(
            $this->invoke(
                'mapFreightTerms',
                ['UNKNOWN']
            )
        );
    }

    public function test_prepaid_is_preserved(): void
    {
        $this->assertSame(
            'prepaid',
            $this->invoke(
                'mapFreightTerms',
                ['PREPAID']
            )
        );
    }

    public function test_fixture_packaging_is_not_mapped_to_false_catalog_type(): void
    {
        foreach (
            [
                'BAGS',
                'CARTONS',
                'PALLET(S)',
                'BARREL(S)',
            ] as $source
        ) {
            $this->assertNull(
                $this->invoke(
                    'resolveNavsurPackagingTypeId',
                    [$source]
                ),
                $source
            );
        }
    }

    public function test_real_container_types_are_preserved(): void
    {
        $cases = [
            '20DV' => '20GP',
            '40HC' => '40HC',
            '40RH' => '40RH',
        ];

        foreach ($cases as $source => $expected) {
            $type = $this->invoke(
                'findOrCreateContainerType',
                [$source, '']
            );

            $this->assertSame(
                $expected,
                $type->code
            );
        }
    }

    public function test_flat_rack_is_not_degraded_to_gp(): void
    {
        $this->expectException(\Exception::class);

        $this->invoke(
            'findOrCreateContainerType',
            ['40FR', '40']
        );
    }

    public function test_hs_code_is_preserved_as_commodity_only(): void
    {
        $code = $this->invoke(
            'extractNavsurCommodityCode',
            [[
                'partida_arancelaria' => '',
                'mercaderia' =>
                    'PRODUCT HS CODE: 4104.11.14',
            ]]
        );

        $this->assertSame(
            '4104.11.14',
            $code
        );
    }
}
