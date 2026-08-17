<?php

namespace Tests\Unit\Services\Parsers;

use App\Services\Parsers\KlineDataParser;
use ReflectionMethod;
use Tests\TestCase;

class KlineDataParserMeasurementIntegrityTest extends TestCase
{
    private function invokeParser(string $method, array $arguments = []): mixed
    {
        $parser = new KlineDataParser();

        $reflection = new ReflectionMethod(
            KlineDataParser::class,
            $method
        );

        $reflection->setAccessible(true);

        return $reflection->invokeArgs($parser, $arguments);
    }

    public function test_missing_measurements_remain_unknown(): void
    {
        $this->assertSame(
            [
                'package_quantity' => null,
                'gross_weight_kg' => null,
                'net_weight_kg' => null,
                'volume_m3' => null,
            ],
            $this->invokeParser('extractRealMeasurements', [[]])
        );
    }

    public function test_explicit_zero_measurements_are_preserved_as_zero(): void
    {
        $measurements = $this->invokeParser(
            'extractRealMeasurements',
            [[
                'CMMDREC0' => [
                    '000001NAUT00000000VEHICLES 00000000000KGS000000000M3',
                ],
            ]]
        );

        $this->assertSame(0, $measurements['package_quantity']);
        $this->assertSame(0.0, $measurements['gross_weight_kg']);
        $this->assertNull($measurements['net_weight_kg']);
        $this->assertSame(0.0, $measurements['volume_m3']);
    }

    public function test_quantity_and_gross_survive_when_volume_is_absent(): void
    {
        $measurements = $this->invokeParser(
            'extractRealMeasurements',
            [[
                'CMMDREC0' => [
                    '000001NAUT00000185VEHICLES 01517000000KGS',
                ],
            ]]
        );

        $this->assertSame(185, $measurements['package_quantity']);
        $this->assertSame(
            151700.0,
            $measurements['gross_weight_kg']
        );
        $this->assertNull($measurements['volume_m3']);
    }

    public function test_missing_required_quantity_is_rejected(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('no informa cantidad de bultos');

        $this->invokeParser(
            'assertRequiredMeasurements',
            [[
                'package_quantity' => null,
                'gross_weight_kg' => 100.0,
                'net_weight_kg' => null,
                'volume_m3' => null,
            ], 'TEST-BL']
        );
    }

    public function test_missing_required_gross_weight_is_rejected(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('no informa peso bruto');

        $this->invokeParser(
            'assertRequiredMeasurements',
            [[
                'package_quantity' => 1,
                'gross_weight_kg' => null,
                'net_weight_kg' => null,
                'volume_m3' => null,
            ], 'TEST-BL']
        );
    }
}
