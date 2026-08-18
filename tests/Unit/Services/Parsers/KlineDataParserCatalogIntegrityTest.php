<?php

namespace Tests\Unit\Services\Parsers;

use App\Services\Parsers\KlineDataParser;
use DomainException;
use ReflectionMethod;
use Tests\TestCase;

class KlineDataParserCatalogIntegrityTest extends TestCase
{
    public function test_vehicle_source_resolves_semantic_cargo_code(): void
    {
        $code = $this->invokeParser(
            'resolveCargoTypeCode',
            [[
                'CMMDREC0' => [
                    '0000001NAUT00000185VEHICLES'
                    . '01517000000KGS001585450M3'
                    . '                           87032100',
                ],
            ], 'KKLU-TEST']
        );

        $this->assertSame('VEH001', $code);
    }

    public function test_spanish_vehicle_description_resolves_semantic_cargo_code(): void
    {
        $code = $this->invokeParser(
            'resolveCargoTypeCode',
            [[
                'DESCREC0' => [
                    '000001185 VEHICULOS DE PASSEO RENAULT',
                ],
            ], 'KKLU-TEST']
        );

        $this->assertSame('VEH001', $code);
    }

    public function test_unknown_cargo_is_not_forced_into_generic_catalog(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('KKLU-UNKNOWN');

        $this->invokeParser(
            'resolveCargoTypeCode',
            [[
                'DESCREC0' => [
                    '000001MAQUINARIA INDUSTRIAL',
                ],
            ], 'KKLU-UNKNOWN']
        );
    }

    public function test_kline_does_not_invent_packaging_type(): void
    {
        $packaging = $this->invokeParser(
            'resolvePackagingTypeId',
            [[
                'CMMDREC0' => [
                    '0000001NAUT00000185VEHICLES',
                ],
            ]]
        );

        $this->assertNull($packaging);
    }

    private function invokeParser(
        string $method,
        array $arguments
    ): mixed {
        $parser = new KlineDataParser();

        $reflection = new ReflectionMethod(
            $parser,
            $method
        );
        $reflection->setAccessible(true);

        return $reflection->invokeArgs(
            $parser,
            $arguments
        );
    }
}
