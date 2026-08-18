<?php

namespace Tests\Unit\Services\Parsers;

use App\Services\Parsers\KlineDataParser;
use DomainException;
use ReflectionMethod;
use Tests\TestCase;

class KlineDataParserUnitIntegrityTest extends TestCase
{
    public function test_vehicle_quantity_uses_piece_unit(): void
    {
        $this->assertSame(
            'PCS',
            $this->resolve([
                'CMMDREC0' => [
                    '0000001NAUT00000185VEHICLES01517000000KGS001585450M3',
                ],
            ], 'KKLU-VEHICLE')
        );
    }

    public function test_spanish_vehicle_quantity_uses_piece_unit(): void
    {
        $this->assertSame(
            'PCS',
            $this->resolve([
                'DESCREC0' => [
                    '001001185 VEHICULOS DE PASSEO RENAULT',
                ],
            ], 'KKLU-VEHICULO')
        );
    }

    public function test_unknown_cargo_does_not_receive_piece_unit(): void
    {
        $this->expectException(DomainException::class);

        $this->resolve([
            'DESCREC0' => [
                '00100110 MAQUINARIA INDUSTRIAL',
            ],
        ], 'KKLU-UNKNOWN');
    }

    private function resolve(
        array $data,
        string $blNumber
    ): string {
        $parser = new KlineDataParser();

        $method = new ReflectionMethod(
            $parser,
            'resolveUnitOfMeasure'
        );
        $method->setAccessible(true);

        return $method->invoke(
            $parser,
            $data,
            $blNumber
        );
    }
}
