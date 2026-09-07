<?php

namespace Tests\Unit\Services\Parsers;

use App\Models\Container;
use App\Models\ShipmentItem;
use App\Services\Parsers\GuaranExcelParser;
use ReflectionMethod;
use Tests\TestCase;

class GuaranExcelParserContainerIntegrityTest extends TestCase
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

    private function type(
        int $id = 10,
        float $maxGross = 34000.0
    ): object {
        return (object) [
            'id' => $id,
            'max_gross_weight_kg' => $maxGross,
        ];
    }

    public function test_full_container_preserves_real_weights_and_nominal_capacity(): void
    {
        $data = $this->invoke(
            'buildContainerCreationData',
            [[
                'CONTAINER_NUMBER' => 'CXRU1198452',
                'CONTAINER_STATUS' => 'F',
                'GROSS_WEIGHT' => '33.536,670',
                'NET_WEIGHT' => '29.116,670',
                'TARE_WEIGHT' => '4.420,000',
                'SEAL_NO' => 'EU28224912, 3354491, SENACSA0879625',
                'TEMP_MIN' => '-18',
                'TEMP_MAX' => null,
            ], $this->type()]
        );

        $this->assertSame('CXRU1198452', $data['container_number']);
        $this->assertSame(10, $data['container_type_id']);

        $this->assertSame('L', $data['condition']);
        $this->assertSame('loaded', $data['operational_status']);

        $this->assertSame(4420.0, $data['tare_weight_kg']);
        $this->assertSame(33536.67, $data['current_gross_weight_kg']);
        $this->assertSame(29116.67, $data['cargo_weight_kg']);

        $this->assertSame(34000.0, $data['max_gross_weight_kg']);

        $this->assertTrue($data['temperature_controlled']);
        $this->assertNull($data['set_temperature']);

        $this->assertArrayNotHasKey('size_feet', $data);
        $this->assertArrayNotHasKey('current_status', $data);
        $this->assertArrayNotHasKey('temperature_min', $data);
        $this->assertArrayNotHasKey('temperature_max', $data);
        $this->assertArrayNotHasKey('is_reefer', $data);
    }

    public function test_empty_container_has_zero_cargo_weight(): void
    {
        $data = $this->invoke(
            'buildContainerCreationData',
            [[
                'CONTAINER_NUMBER' => 'MSCU2558317',
                'CONTAINER_STATUS' => 'E',
                'GROSS_WEIGHT' => '1.900,000',
                'NET_WEIGHT' => '1.900,000',
                'TARE_WEIGHT' => '1.900,000',
                'SEAL_NO' => 'EMPTY-SEAL',
                'TEMP_MIN' => null,
                'TEMP_MAX' => null,
            ], $this->type(5, 30480.0)]
        );

        $this->assertSame('V', $data['condition']);
        $this->assertSame('empty', $data['operational_status']);

        $this->assertSame(1900.0, $data['tare_weight_kg']);
        $this->assertSame(1900.0, $data['current_gross_weight_kg']);

        // NET=TARE es la convención documental del archivo para E,
        // pero la unidad físicamente vacía transporta cero carga.
        $this->assertSame(0.0, $data['cargo_weight_kg']);

        $this->assertSame(30480.0, $data['max_gross_weight_kg']);
        $this->assertFalse($data['temperature_controlled']);
    }

    public function test_seal_longer_than_old_fifty_character_limit_is_preserved(): void
    {
        $seal = 'FX40000031, 3283563, 0000203, 000531, 1749509, EX102437';

        $this->assertGreaterThan(50, strlen($seal));

        $normalized = $this->invoke(
            'normalizeShipperSeal',
            [$seal]
        );

        $this->assertSame($seal, $normalized);
    }

    public function test_seal_larger_than_database_capacity_is_rejected(): void
    {
        $this->expectException(\Exception::class);

        $this->invoke(
            'normalizeShipperSeal',
            [str_repeat('X', 256)]
        );
    }

    public function test_container_number_is_never_silently_truncated(): void
    {
        $this->expectException(\Exception::class);

        $this->invoke(
            'normalizeContainerNumber',
            ['ABCD123456789012']
        );
    }

    public function test_unknown_or_missing_status_is_rejected(): void
    {
        $this->expectException(\Exception::class);

        $this->invoke(
            'mapContainerConditionToEnum',
            ['UNKNOWN']
        );
    }

    public function test_known_statuses_are_mapped_without_guessing(): void
    {
        $this->assertSame(
            'L',
            $this->invoke(
                'mapContainerConditionToEnum',
                ['F']
            )
        );

        $this->assertSame(
            'V',
            $this->invoke(
                'mapContainerConditionToEnum',
                ['E']
            )
        );
    }

    public function test_unknown_container_type_is_rejected(): void
    {
        $this->expectException(\Exception::class);

        $this->invoke(
            'resolveContainerTypeByCode',
            ['ZZ-NO-EXISTE-999']
        );
    }

    public function test_real_guaran_container_types_resolve_strictly(): void
    {
        $expected = [
            '20DV' => '22G1',
            '20OT' => '22U1',
            '20RF' => '22R1',
            '20TN' => '22T1',
            '40HC' => '45G1',
            '40RH' => '45R1',
        ];

        foreach ($expected as $raw => $iso) {
            $type = $this->invoke(
                'resolveContainerTypeByCode',
                [$raw]
            );

            $this->assertSame(
                $iso,
                $type->iso_code,
                "Falló {$raw}"
            );
        }
    }

    public function test_container_item_relation_starts_planned_not_loaded(): void
    {
        $container = new Container();
        $container->setAttribute('id', 50);

        $item = new ShipmentItem();
        $item->setAttribute('id', 60);
        $item->setAttribute('package_quantity', 1);
        $item->setAttribute('gross_weight_kg', 1900);
        $item->setAttribute('net_weight_kg', 1900);
        $item->setAttribute('volume_m3', 0);

        $pivot = $this->invoke(
            'buildContainerItemPivotData',
            [$container, $item]
        );

        $this->assertSame('planned', $pivot['status']);
        $this->assertSame(50, $pivot['container_id']);
        $this->assertSame(60, $pivot['shipment_item_id']);
    }

}
