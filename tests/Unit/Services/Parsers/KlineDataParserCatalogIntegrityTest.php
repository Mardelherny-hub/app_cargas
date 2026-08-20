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

    public function test_explicit_roro_mark_has_priority_over_vehicle_description(): void
    {
        $code = $this->invokeParser(
            'resolveCargoTypeCode',
            [[
                'MARKREC0' => [
                    '0001001RO-RO',
                ],
                'DESCREC0' => [
                    '0001001ONE USED COMBINE',
                    '0001002UNPACKED AND UNPROTECTED VEHICLES.',
                ],
            ], 'KKLU-RORO']
        );

        $this->assertSame('RORO001', $code);
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

    public function test_kline_uses_no_retornable_as_packaging_fallback(): void
    {
        $packagingId = $this->invokeParser(
            'resolvePackagingTypeId',
            [[
                'CMMDREC0' => [
                    '0000001NAUT00000185VEHICLES',
                ],
            ]]
        );

        $packaging = \App\Models\PackagingType::find(
            $packagingId
        );

        $this->assertNotNull($packaging);
        $this->assertSame('N', $packaging->code);
        $this->assertSame(
            'NO RETORNABLE',
            $packaging->name
        );
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
