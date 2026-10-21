<?php

namespace Tests\Unit\Services\Webservice;

use App\Services\Simple\HidroviaTransshipmentTrackResponseMapper;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Respuestas SOAP sintéticas de RegistrarTitEnvios.
 * No consulta bases de datos ni WSAA y no guarda TRACKs.
 * La aceptación real por ARCA y la persistencia se validarán por separado.
 */
class HidroviaTransshipmentTrackResponseMapperTest extends TestCase
{
    private HidroviaTransshipmentTrackResponseMapper $mapper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mapper = new HidroviaTransshipmentTrackResponseMapper();
    }

    private function expected(): array
    {
        return [
            [
                'idTitTrans' => 'BL-A',
                'bill_of_lading_id' => 31,
                'shipments' => [
                    ['id_envio' => 1, 'shipment_id' => 11, 'vessel_id' => 21],
                    ['id_envio' => 2, 'shipment_id' => 12, 'vessel_id' => 22],
                ],
            ],
            [
                // Un mismo shipment puede transportar títulos diferentes.
                'idTitTrans' => '123456',
                'bill_of_lading_id' => 32,
                'shipments' => [
                    ['id_envio' => 1, 'shipment_id' => 11, 'vessel_id' => 21],
                ],
            ],
        ];
    }

    private function responseBody(): string
    {
        // Los títulos y los TrackEnv llegan en orden contrario al solicitado.
        return '<titTracksEnv>'
            . '<TitTrackEnv><idTitTrans>123456</idTitTrans><tracksEnv>'
            . '<TrackEnv><idTrack>2026AR0000000033</idTrack><idEnvio>001</idEnvio></TrackEnv>'
            . '</tracksEnv></TitTrackEnv>'
            . '<TitTrackEnv><idTitTrans>BL-A</idTitTrans><tracksEnv>'
            . '<TrackEnv><idTrack>2026AR0000000022</idTrack><idEnvio>2</idEnvio></TrackEnv>'
            . '<TrackEnv><idTrack>2026AR0000000011</idTrack><idEnvio>1</idEnvio></TrackEnv>'
            . '</tracksEnv></TitTrackEnv>'
            . '</titTracksEnv>';
    }

    private function soap(string $body): string
    {
        return '<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">'
            . '<soap:Body><RegistrarTitEnviosResponse><RegistrarTitEnviosResult>'
            . $body
            . '</RegistrarTitEnviosResult></RegistrarTitEnviosResponse></soap:Body>'
            . '</soap:Envelope>';
    }

    private function map(?string $body = null, ?array $expected = null): array
    {
        return $this->mapper->mapRegistrarTitEnvios(
            $this->soap($body ?? $this->responseBody()),
            $expected ?? $this->expected()
        );
    }

    private function reject(string $reason, string $xml, ?array $expected = null): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($reason);
        $this->mapper->mapRegistrarTitEnvios($xml, $expected ?? $this->expected());
    }

    public function test_tracks_are_joined_by_title_and_id_envio_not_soap_order(): void
    {
        $expected = $this->expected();
        $snapshot = $expected;
        $mapped = $this->map(null, $expected);

        $this->assertCount(3, $mapped);
        $this->assertSame([
            '2026AR0000000011', '2026AR0000000022', '2026AR0000000033',
        ], array_column($mapped, 'track_number'));
        $this->assertSame([11, 12, 11], array_column($mapped, 'shipment_id'));
        $this->assertSame([31, 31, 32], array_column($mapped, 'bill_of_lading_id'));
        $this->assertSame(['BL-A', 'BL-A', '123456'], array_column($mapped, 'afip_title_number'));
        $this->assertSame([1, 2, 1], array_column(array_column($mapped, 'afip_metadata'), 'idEnvio'));
        $this->assertSame([21, 22, 21], array_column(array_column($mapped, 'afip_metadata'), 'vessel_id'));
        $this->assertSame($snapshot, $expected);
    }

    public function test_output_reuses_existing_webservice_track_fields_without_creating_records(): void
    {
        $mapped = $this->map();
        foreach ($mapped as $row) {
            $this->assertSame([
                'shipment_id', 'bill_of_lading_id', 'track_number', 'track_type',
                'webservice_method', 'reference_type', 'reference_number',
                'afip_title_number', 'afip_metadata',
            ], array_keys($row));
            $this->assertSame('envio', $row['track_type']);
            $this->assertSame('RegistrarTitEnvios', $row['webservice_method']);
            $this->assertSame('bill', $row['reference_type']);
            $this->assertSame($row['reference_number'], $row['afip_title_number']);
            $this->assertSame($row['reference_number'], $row['afip_metadata']['idTitTrans']);
            $this->assertSame('RegistrarTitEnvios', $row['afip_metadata']['source_method']);
        }
        $this->assertSame('123456', $mapped[2]['reference_number']);
        $this->assertIsString($mapped[2]['reference_number']);
    }

    public function test_zero_success_code_does_not_invalidate_real_track_response(): void
    {
        $this->assertCount(3, $this->map($this->responseBody()
            . '<ListaErrores><DetalleError><Codigo>0</Codigo></DetalleError></ListaErrores>'));
    }

    public function test_soap_fault_blocks_even_if_tracks_are_present(): void
    {
        $this->reject('SOAP Fault', $this->soap($this->responseBody()
            . '<soap:Fault><faultcode>soap:Server</faultcode></soap:Fault>'));
    }

    public function test_arca_error_blocks_even_if_tracks_are_present(): void
    {
        $this->reject('error aduanero', $this->soap($this->responseBody()
            . '<ListaErrores><DetalleError><Codigo>41933</Codigo></DetalleError></ListaErrores>'));
    }

    public function test_empty_or_malformed_xml_is_rejected(): void
    {
        foreach (['', '<soap:Envelope>'] as $xml) {
            try {
                $this->mapper->mapRegistrarTitEnvios($xml, $this->expected());
                $this->fail('Se aceptó XML vacío o inválido.');
            } catch (InvalidArgumentException $error) {
                $this->assertStringContainsString('vacía o es inválida', $error->getMessage());
            }
        }
    }

    public function test_unknown_title_is_rejected(): void
    {
        $this->reject('título desconocido', $this->soap(str_replace(
            '<idTitTrans>123456</idTitTrans>', '<idTitTrans>999999</idTitTrans>', $this->responseBody()
        )));
    }

    public function test_missing_title_is_rejected(): void
    {
        $body = preg_replace(
            '~<TitTrackEnv><idTitTrans>123456</idTitTrans>.*?</TitTrackEnv>~s',
            '', $this->responseBody()
        );
        $this->reject('exactamente los títulos', $this->soap($body));
    }

    public function test_duplicated_title_is_rejected(): void
    {
        // Duplicar el bloque completo con dos TrackEnv para llegar al control
        // de título repetido, no al rechazo previo por cantidad de envíos.
        preg_match('~<TitTrackEnv><idTitTrans>BL-A</idTitTrans>.*?</TitTrackEnv>~s',
            $this->responseBody(), $matches);
        $this->assertArrayHasKey(0, $matches);
        $duplicated = preg_replace(
            '~<TitTrackEnv><idTitTrans>123456</idTitTrans>.*?</TitTrackEnv>~s',
            $matches[0], $this->responseBody()
        );
        $this->assertIsString($duplicated);
        $this->reject('título desconocido o repetido', $this->soap($duplicated));
    }

    public function test_unknown_id_envio_is_rejected(): void
    {
        $this->reject('idEnvio desconocido', $this->soap(str_replace(
            '<idEnvio>2</idEnvio>', '<idEnvio>3</idEnvio>', $this->responseBody()
        )));
    }

    public function test_duplicate_id_envio_is_rejected(): void
    {
        $this->reject('idEnvio desconocido o repetido', $this->soap(str_replace(
            '<idEnvio>2</idEnvio>', '<idEnvio>1</idEnvio>', $this->responseBody()
        )));
    }

    public function test_duplicate_track_across_titles_is_rejected(): void
    {
        $this->reject('mismo TRACK', $this->soap(str_replace(
            '2026AR0000000033', '2026AR0000000011', $this->responseBody()
        )));
    }

    public function test_missing_track_in_envio_is_rejected(): void
    {
        $this->reject('TRACK vacío', $this->soap(str_replace(
            '<idTrack>2026AR0000000022</idTrack>', '<idTrack></idTrack>', $this->responseBody()
        )));
    }

    public function test_track_outside_track_env_is_rejected(): void
    {
        $this->reject('sin relación inequívoca', $this->soap($this->responseBody()
            . '<idTrack>2026AR0000000099</idTrack>'));
    }

    public function test_unexpected_empty_container_track_is_rejected(): void
    {
        $this->reject('contenedores vacíos', $this->soap($this->responseBody()
            . '<titTracksContVacio><TitTrackContVacio><idTrack>2026AR0000000099</idTrack>'
            . '</TitTrackContVacio></titTracksContVacio>'));
    }

    public function test_fake_track_is_rejected(): void
    {
        $this->reject('TRACK vacío, ficticio', $this->soap(str_replace(
            '2026AR0000000022', 'TEST_TRACK_FAKE', $this->responseBody()
        )));
    }

    public function test_missing_expected_titles_is_rejected_before_parsing(): void
    {
        $this->reject('No hay títulos', $this->soap($this->responseBody()), []);
    }

    public function test_duplicate_expected_bill_is_rejected(): void
    {
        $titles = $this->expected();
        $titles[1]['bill_of_lading_id'] = 31;
        $this->reject('inválido o está duplicado', $this->soap($this->responseBody()), $titles);
    }

    public function test_same_shipment_cannot_appear_twice_within_one_title(): void
    {
        $titles = $this->expected();
        $titles[0]['shipments'][1]['shipment_id'] = 11;
        $this->reject('barcaza inválida o duplicada', $this->soap($this->responseBody()), $titles);
    }
}
