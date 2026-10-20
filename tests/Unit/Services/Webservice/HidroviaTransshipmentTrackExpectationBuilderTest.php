<?php

namespace Tests\Unit\Services\Webservice;

use App\Models\BillOfLading;
use App\Services\Simple\HidroviaTransshipmentTrackExpectationBuilder;
use App\Services\Simple\HidroviaTransshipmentTrackResponseMapper;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Contrato entre el preflight existente y el mapeo de TRACK de RegistrarTitEnvios.
 * Sólo objetos en memoria, sin BD, WSAA ni SOAP real.
 */
class HidroviaTransshipmentTrackExpectationBuilderTest extends TestCase
{
    private function bill(array $overrides = []): BillOfLading
    {
        return (new BillOfLading())->forceFill(array_replace([
            'id' => 31,
            'bill_number' => '000123',
            'is_transit_transshipment' => 'S',
            'is_fractional' => false,
        ], $overrides));
    }

    private function prepared(): array
    {
        // Misma forma que HidroviaTransshipmentReadinessService::prepare().
        return [
            'voyage_id' => 7,
            'bill_of_lading_id' => 31,
            'shipments' => [
                [
                    'id_envio' => 1, 'shipment_id' => 11,
                    'shipment_number' => 'BAR-11', 'vessel_id' => 21,
                    'lines' => [[
                        'shipment_item_id' => 41, 'container_id' => null,
                        'package_quantity' => 4, 'gross_weight_kg' => '40.10',
                    ]],
                ],
                [
                    'id_envio' => 2, 'shipment_id' => 12,
                    'shipment_number' => 'BAR-12', 'vessel_id' => 22,
                    'lines' => [[
                        'shipment_item_id' => 41, 'container_id' => null,
                        'package_quantity' => 6, 'gross_weight_kg' => '60.15',
                    ]],
                ],
            ],
        ];
    }

    private function build(BillOfLading $bill, array $prepared): array
    {
        return (new HidroviaTransshipmentTrackExpectationBuilder())->fromPrepared($bill, $prepared);
    }

    private function reject(BillOfLading $bill, array $prepared, string $message): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);
        $this->build($bill, $prepared);
    }

    public function test_adapts_exact_preflight_shape_without_inventing_title_or_vessel_data(): void
    {
        $bill = $this->bill();
        $prepared = $this->prepared();
        $billBefore = $bill->getAttributes();
        $preparedBefore = $prepared;

        $expected = $this->build($bill, $prepared);

        $this->assertSame([[
            'idTitTrans' => '000123',
            'bill_of_lading_id' => 31,
            'shipments' => [
                ['id_envio' => 1, 'shipment_id' => 11, 'vessel_id' => 21],
                ['id_envio' => 2, 'shipment_id' => 12, 'vessel_id' => 22],
            ],
        ]], $expected);
        $this->assertSame($billBefore, $bill->getAttributes());
        $this->assertSame($preparedBefore, $prepared);
        $this->assertIsString($expected[0]['idTitTrans']);
    }

    public function test_builder_preserves_three_barge_assignments_and_their_order(): void
    {
        $prepared = $this->prepared();
        $prepared['shipments'][] = [
            'id_envio' => 3, 'shipment_id' => 13, 'vessel_id' => 23,
            'lines' => [['shipment_item_id' => 42, 'package_quantity' => 1, 'gross_weight_kg' => '10.00']],
        ];
        $result = $this->build($this->bill(), $prepared);
        $this->assertSame([1, 2, 3], array_column($result[0]['shipments'], 'id_envio'));
        $this->assertSame([11, 12, 13], array_column($result[0]['shipments'], 'shipment_id'));
        $this->assertSame([21, 22, 23], array_column($result[0]['shipments'], 'vessel_id'));
    }

    public function test_builder_output_matches_real_response_mapper_contract(): void
    {
        $expected = $this->build($this->bill(), $this->prepared());
        $response = '<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">'
            . '<soap:Body><RegistrarTitEnviosResponse><RegistrarTitEnviosResult>'
            . '<titTracksEnv><TitTrackEnv><idTitTrans>000123</idTitTrans><tracksEnv>'
            . '<TrackEnv><idTrack>2026AR0000000022</idTrack><idEnvio>2</idEnvio></TrackEnv>'
            . '<TrackEnv><idTrack>2026AR0000000011</idTrack><idEnvio>1</idEnvio></TrackEnv>'
            . '</tracksEnv></TitTrackEnv></titTracksEnv>'
            . '</RegistrarTitEnviosResult></RegistrarTitEnviosResponse></soap:Body></soap:Envelope>';
        $rows = (new HidroviaTransshipmentTrackResponseMapper())
            ->mapRegistrarTitEnvios($response, $expected);
        $this->assertSame(['2026AR0000000011', '2026AR0000000022'], array_column($rows, 'track_number'));
        $this->assertSame([11, 12], array_column($rows, 'shipment_id'));
        $this->assertSame([31, 31], array_column($rows, 'bill_of_lading_id'));
        $this->assertSame(['000123', '000123'], array_column($rows, 'afip_title_number'));
        $this->assertSame([1, 2], array_column(array_column($rows, 'afip_metadata'), 'idEnvio'));
    }

    public function test_preflight_must_belong_to_same_bill(): void
    {
        $prepared = $this->prepared();
        $prepared['bill_of_lading_id'] = 99;
        $this->reject($this->bill(), $prepared, 'preflight de transbordo no es válido');
    }

    public function test_real_positive_voyage_is_required(): void
    {
        $prepared = $this->prepared();
        unset($prepared['voyage_id']);
        $this->reject($this->bill(), $prepared, 'preflight de transbordo no es válido');
    }

    public function test_non_transshipment_bill_is_rejected(): void
    {
        $this->reject($this->bill(['is_transit_transshipment' => 'N']),
            $this->prepared(), 'preflight de transbordo no es válido');
    }

    public function test_fractional_bill_is_not_silently_supported(): void
    {
        $this->reject($this->bill(['is_fractional' => true]),
            $this->prepared(), 'preflight de transbordo no es válido');
    }

    public function test_missing_real_title_is_rejected(): void
    {
        $this->reject($this->bill(['bill_number' => '  ']),
            $this->prepared(), 'preflight de transbordo no es válido');
    }

    public function test_title_above_thirty_six_characters_is_rejected(): void
    {
        $this->reject($this->bill(['bill_number' => str_repeat('X', 37)]),
            $this->prepared(), 'preflight de transbordo no es válido');
    }

    public function test_two_shipments_are_required_for_multiple_barges(): void
    {
        $prepared = $this->prepared();
        array_pop($prepared['shipments']);
        $this->reject($this->bill(), $prepared, 'al menos dos envíos reales');
    }

    public function test_missing_shipments_collection_is_rejected(): void
    {
        $prepared = $this->prepared();
        unset($prepared['shipments']);
        $this->reject($this->bill(), $prepared, 'al menos dos envíos reales');
    }

    public function test_more_than_nine_hundred_ninety_nine_shipments_are_rejected(): void
    {
        $prepared = $this->prepared();
        $prepared['shipments'] = [];
        for ($i = 1; $i <= 1000; $i++) {
            $prepared['shipments'][] = [
                'id_envio' => $i, 'shipment_id' => $i, 'vessel_id' => $i,
                'lines' => [['shipment_item_id' => 41]],
            ];
        }
        $this->reject($this->bill(), $prepared, 'al menos dos envíos reales');
    }

    public function test_missing_or_repeated_id_envio_is_rejected(): void
    {
        foreach ([null, 1, 3, 0] as $idEnvio) {
            $prepared = $this->prepared();
            $prepared['shipments'][1]['id_envio'] = $idEnvio;
            try {
                $this->build($this->bill(), $prepared);
                $this->fail('Se aceptó idEnvio inválido.');
            } catch (InvalidArgumentException $error) {
                $this->assertStringContainsString('secuencia, barcaza o reparto', $error->getMessage());
            }
        }
    }

    public function test_same_shipment_cannot_appear_twice_in_one_title(): void
    {
        $prepared = $this->prepared();
        $prepared['shipments'][1]['shipment_id'] = 11;
        $this->reject($this->bill(), $prepared, 'secuencia, barcaza o reparto');
    }

    public function test_vessel_id_is_required_for_each_shipment(): void
    {
        $prepared = $this->prepared();
        $prepared['shipments'][1]['vessel_id'] = null;
        $this->reject($this->bill(), $prepared, 'secuencia, barcaza o reparto');
    }

    public function test_lines_cannot_be_missing_or_empty(): void
    {
        foreach ([null, []] as $lines) {
            $prepared = $this->prepared();
            $prepared['shipments'][1]['lines'] = $lines;
            try {
                $this->build($this->bill(), $prepared);
                $this->fail('Se aceptó un envío sin reparto real.');
            } catch (InvalidArgumentException $error) {
                $this->assertStringContainsString('secuencia, barcaza o reparto', $error->getMessage());
            }
        }
    }

    public function test_non_array_shipment_is_rejected(): void
    {
        $prepared = $this->prepared();
        $prepared['shipments'][1] = 'barcaza desconocida';
        $this->reject($this->bill(), $prepared, 'envío inválido');
    }

    public function test_preflight_shipment_keys_cannot_be_arbitrary_names(): void
    {
        $prepared = $this->prepared();
        $prepared['shipments'] = [
            'first' => $prepared['shipments'][0],
            'second' => $prepared['shipments'][1],
        ];
        $this->reject($this->bill(), $prepared, 'secuencia, barcaza o reparto');
    }
}
