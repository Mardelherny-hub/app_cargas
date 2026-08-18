<?php

namespace Tests\Unit\Services\Parsers;

use App\Services\Parsers\KlineDataParser;
use ReflectionMethod;
use Tests\TestCase;

class KlineDataParserExcavatorIntegrityTest extends TestCase
{
    private function data(): array
    {
        return [
            'CMMDREC0' => [
                '0000001NAUT00000002UNITS'
                . '00700000000KGS000235240M3',
            ],
            'DESCREC0' => [
                '001001LOADED AS PER BELOW:',
                '003001NCM: 8429.52.19.000B',
                '005001ESCAVADORA IDRAULICA',
                '006001HITACHI ZX350LCNM-3',
                '007001S./.N.: HCMBFP00V00058893',
                '009001ESCAVADORA IDRAULICA',
                '010001HITACHI ZX350LCNM-5',
                '011001CHASIS S./N.: HCMDDD51A00070946',
                '013001CONSOLIDATED CARGO',
            ],
            'MARKREC0' => [
                '001001SELF PROPELLED UNIT',
                '002001EC057',
                '004001SELF PROPELLED UNIT',
                '005001EC164D',
            ],
        ];
    }

    public function test_real_excavator_measurements_are_preserved(): void
    {
        $m = $this->invokeParser(
            'extractRealMeasurements',
            [$this->data()]
        );

        $this->assertSame(2, $m['package_quantity']);
        $this->assertSame(70000.0, $m['gross_weight_kg']);
        $this->assertNull($m['net_weight_kg']);
        $this->assertSame(235.24, $m['volume_m3']);
    }

    public function test_excavator_is_non_containerized_not_invented_roro(): void
    {
        $this->assertSame(
            'ONC001',
            $this->invokeParser(
                'resolveCargoTypeCode',
                [$this->data(), 'KKLUATM02175']
            )
        );
    }

    public function test_explicit_units_resolve_to_pieces(): void
    {
        $this->assertSame(
            'PCS',
            $this->invokeParser(
                'resolveUnitOfMeasure',
                [$this->data(), 'KKLUATM02175']
            )
        );
    }

    public function test_extended_ncm_preserves_base_eight_digits(): void
    {
        $this->assertSame(
            ['84295219'],
            $this->invokeParser('extractNCMCodes', [$this->data()])
        );

        $this->assertSame(
            '84295219',
            $this->invokeParser('extractNCMCode', [$this->data()])
        );
    }

    public function test_description_preserves_excavator_identity(): void
    {
        $descriptions = $this->invokeParser(
            'extractCargoDescriptions',
            [$this->data()]
        );

        $this->assertCount(1, $descriptions);

        $description = $descriptions[0];

        $this->assertStringContainsString(
            '2 ESCAVADORAS HIDRAULICAS',
            $description
        );
        $this->assertStringContainsString(
            'HITACHI ZX350LCNM-3',
            $description
        );
        $this->assertStringContainsString(
            'HCMBFP00V00058893',
            $description
        );
        $this->assertStringContainsString(
            'HITACHI ZX350LCNM-5',
            $description
        );
        $this->assertStringContainsString(
            'HCMDDD51A00070946',
            $description
        );

        $this->assertNotSame('2 units', strtolower($description));
    }

    private function invokeParser(string $method, array $args): mixed
    {
        $parser = new KlineDataParser();

        $reflection = new ReflectionMethod(
            KlineDataParser::class,
            $method
        );
        $reflection->setAccessible(true);

        return $reflection->invokeArgs($parser, $args);
    }
}
