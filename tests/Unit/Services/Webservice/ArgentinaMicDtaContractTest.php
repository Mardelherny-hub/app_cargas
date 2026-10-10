<?php

namespace Tests\Unit\Services\Webservice;

use App\Models\BillOfLading;
use App\Models\Company;
use App\Models\Container;
use App\Models\Shipment;
use App\Models\ShipmentItem;
use App\Models\User;
use App\Models\Voyage;
use App\Models\WebserviceTrack;
use App\Models\WebserviceTransaction;
use App\Services\Simple\ArgentinaMicDtaService;
use App\Services\Simple\SimpleXmlGenerator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Mockery;
use ReflectionMethod;
use ReflectionProperty;
use Tests\TestCase;

/**
 * Contract fixtures, never production data or network responses.
 * Manual-del-Usuario-para-Registro-ATA.pdf pp. 54, 57:
 * RegistrarTitEnviosRta.titTracksEnv / titTracksContVacio;
 * TitTrackEnv.tracksEnv.TrackEnv has idTrack and idEnvio.
 * The example on p. 11 spells titTracksContVacios (plural): documentary
 * discrepancy, not silently treated as the singular contract requested here.
 *
 * Only the three existing WS migrations run, on private SQLite :memory:.
 * Business entities are in-memory fixtures; transport and full request/auth
 * generation are mocked. The real track XML writers are exercised separately.
 */
class ArgentinaMicDtaContractTest extends TestCase
{
    private const NS = 'Ar.Gob.Afip.Dga.wgesregsintia2';
    private const ENV_TRACK = '2026AR0000000011';
    private const EMPTY_TRACK = '2026AR0000000022';
    private const OTHER_TRACK = '2026AR0000000033';
    private const MIC_ID = '26ARMIF000000011';

    private ArgentinaMicDtaService $service;
    private SimpleXmlGenerator $xml;
    private Company $company;
    private Voyage $voyage;
    private Shipment $shipment;
    private int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'database.connections.sqlite.foreign_key_constraints' => false,
            'logging.default' => 'null',
            'audit.enabled' => false,
        ]);
        DB::purge('sqlite');
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        Http::preventStrayRequests();
        // These migrations define the actual fields, enum CHECKs and indexes.
        // Foreign keys to unrelated business tables are disabled, not fabricated.
        foreach ([
            '2025_07_23_120939_create_webservice_transactions_table.php',
            '2025_07_23_152316_create_webservice_responses_table.php',
            '2025_09_08_122553_create_webservice_tracks_table.php',
        ] as $migration) {
            (require database_path('migrations/' . $migration))->up();
        }
        $this->company = (new Company())->forceFill(['id' => 1, 'ws_environment' => 'testing']);
        $user = (new User())->forceFill(['id' => 1]);
        // This override is only the network boundary, never the business logic.
        $this->service = new class($this->company, $user) extends ArgentinaMicDtaService {
            public ?\SoapClient $contractSoap = null;
            protected function createSoapClient(): \SoapClient
            {
                if (!$this->contractSoap) {
                    throw new \LogicException('Unexpected SOAP attempt in contract test');
                }
                return $this->contractSoap;
            }
        };
        $this->xml = Mockery::mock(SimpleXmlGenerator::class);
        (new ReflectionProperty($this->service, 'xmlSerializer'))->setValue($this->service, $this->xml);
        $this->shipment = $this->shipmentFixture(11);
        $this->voyage = (new Voyage())->forceFill(['id' => 7, 'company_id' => 1, 'voyage_number' => 'QA-MIC']);
        $this->voyage->setRelation('shipments', new Collection([$this->shipment]));
        $this->voyage->setRelation('billsOfLading', new Collection());
        $this->voyage->setRelation('leadVessel', null);
    }

    private function shipmentFixture(int $id): Shipment
    {
        $shipment = Mockery::mock(Shipment::class)->makePartial();
        $shipment->forceFill(['id' => $id, 'voyage_id' => 7, 'shipment_number' => 'QA-' . $id]);
        // The wrapper refreshes business relations: no business DB is needed.
        $shipment->shouldReceive('load')->with(['vessel.vesselType', 'vessel.flagCountry', 'captain', 'billsOfLading'])
            ->andReturnSelf();
        $shipment->setRelation('vessel', null);
        $shipment->setRelation('captain', null);
        $shipment->setRelation('billsOfLading', new Collection());
        return $shipment;
    }

    private function invoke(string $method, ...$arguments): mixed
    {
        return (new ReflectionMethod(ArgentinaMicDtaService::class, $method))
            ->invoke($this->service, ...$arguments);
    }

    private function transaction(array $overrides = []): WebserviceTransaction
    {
        return WebserviceTransaction::create(array_replace([
            'company_id' => 1, 'user_id' => 1, 'voyage_id' => 7, 'shipment_id' => 11,
            'transaction_id' => 'QA-' . ++$this->sequence,
            'webservice_type' => 'micdta', 'country' => 'AR', 'environment' => 'testing',
            'webservice_url' => 'https://example.invalid/never-called',
            'soap_action' => self::NS . '/RegistrarTitEnvios', 'status' => 'success',
        ], $overrides));
    }

    private function track(WebserviceTransaction $transaction, string $number, array $overrides = []): WebserviceTrack
    {
        return WebserviceTrack::create(array_replace([
            'webservice_transaction_id' => $transaction->id,
            'shipment_id' => $transaction->shipment_id,
            'track_number' => $number, 'track_type' => 'envio',
            'webservice_method' => 'RegistrarTitEnvios',
            'reference_type' => 'shipment', 'reference_number' => 'QA-' . $transaction->shipment_id,
            'generated_at' => now(), 'status' => 'generated',
            'created_by_user_id' => 1, 'process_chain' => ['RegistrarTitEnvios'],
        ], $overrides));
    }

    private function response(string $method, string $body): string
    {
        return '<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"><soap:Body><'
            . $method . 'Response xmlns="' . self::NS . '"><' . $method . 'Result>'
            . $body . '</' . $method . 'Result></' . $method . 'Response></soap:Body></soap:Envelope>';
    }

    private function soap(string $method, string $body): \SoapClient
    {
        $soap = Mockery::mock(\SoapClient::class); // No constructor / WSDL fetch.
        $soap->shouldReceive('__doRequest')->once()
            ->with(Mockery::type('string'), Mockery::type('string'), self::NS . '/' . $method, SOAP_1_1, false)
            ->andReturn($this->response($method, $body));
        return $soap;
    }

    private function expectMicDta(array $expectedTracks, ?string $expectedTransactionId = null): void
    {
        $this->xml->shouldReceive('createRegistrarMicDtaXml')->once()
            ->withArgs(function ($voyage, $tracks, $transactionId, $shipment) use ($expectedTracks, $expectedTransactionId) {
                $this->assertSame($this->voyage, $voyage);
                $this->assertSame($this->shipment, $shipment);
                $this->assertSame($expectedTracks, $tracks);
                if ($expectedTransactionId !== null) {
                    $this->assertSame($expectedTransactionId, $transactionId);
                }
                return true;
            })->andReturn('<RegistrarMicDta/>');
        $this->service->contractSoap = $this->soap('RegistrarMicDta', '<idMicDta>' . self::MIC_ID . '</idMicDta>');
    }

    public function test_tit_envios_official_groups_are_persisted_as_distinct_track_types(): void
    {
        $this->xml->shouldReceive('createRegistrarTitEnviosXml')->once()->andReturn('<RegistrarTitEnvios/>');
        $body = '<titTracksEnv><TitTrackEnv><idTitTrans>QA-BL-1</idTitTrans><tracksEnv>'
            . '<TrackEnv><idTrack>' . self::ENV_TRACK . '</idTrack><idEnvio>1</idEnvio></TrackEnv>'
            . '</tracksEnv></TitTrackEnv></titTracksEnv>'
            . '<titTracksContVacio><TitTrackContVacio><idTitTrans>QA-BL-2</idTitTrans>'
            . '<idTrack>' . self::EMPTY_TRACK . '</idTrack></TitTrackContVacio></titTracksContVacio>';
        $result = $this->invoke('sendTitEnvios', $this->soap('RegistrarTitEnvios', $body), $this->shipment);
        $this->assertTrue($result['success'], json_encode($result));
        $this->assertSame([self::ENV_TRACK, self::EMPTY_TRACK], $result['tracks']);
        $this->assertSame([
            self::ENV_TRACK => 'envio', self::EMPTY_TRACK => 'contenedor_vacio',
        ], WebserviceTrack::orderBy('id')->pluck('track_type', 'track_number')->all());
        $this->assertSame(['carga_suelta' => [self::ENV_TRACK], 'cont_vacios' => [self::EMPTY_TRACK]],
            $this->invoke('getTracksFromPreviousTransactions', $this->voyage)[11]);
        $transaction = WebserviceTransaction::findOrFail($result['transaction_record_id']);
        $this->assertSame(['envio' => [self::ENV_TRACK], 'contenedor_vacio' => [self::EMPTY_TRACK]],
            $transaction->success_data['track_groups']);
    }

    public function test_registrar_envios_requires_an_existing_title_before_generating_xml_or_sending(): void
    {
        $this->xml->shouldNotReceive('createRegistrarEnviosXml');
        $soap = Mockery::mock(\SoapClient::class);
        $soap->shouldNotReceive('__doRequest');
        $result = $this->invoke('sendEnvios', $soap, $this->shipment);
        $this->assertFalse($result['success']);
        $this->assertSame('MISSING_TITLE_ID', $result['error_code']);
        $this->assertSame(0, WebserviceTransaction::count());
        $this->assertSame(0, WebserviceTrack::count());
    }

    public function test_registrar_envios_without_real_tracks_blocks_and_never_creates_fake_tracks(): void
    {
        // RegistrarEnvios adds shipments to a title already registered by RegistrarTitEnvios.
        $this->transaction(['external_reference' => 'QA-TITLE-REGISTERED']);
        $this->xml->shouldReceive('createRegistrarEnviosXml')->once()
            ->with($this->shipment, 'QA-TITLE-REGISTERED', Mockery::type('string'))
            ->andReturn('<RegistrarEnvios/>');
        $body = '<tracksEnv><TrackEnv><idTrack>TEST_TRACK_11</idTrack><idEnvio>1</idEnvio></TrackEnv></tracksEnv>';
        $result = $this->invoke('sendEnvios', $this->soap('RegistrarEnvios', $body), $this->shipment);
        $this->assertFalse($result['success']);
        $this->assertSame('MISSING_TRACKS', $result['error_code']);
        $this->assertSame([], $result['tracks']);
        $this->assertSame([], WebserviceTransaction::findOrFail($result['transaction_record_id'])->tracking_numbers);
        $this->assertSame(0, WebserviceTrack::count());
    }

    public function test_empty_registrar_envios_response_also_blocks(): void
    {
        // RegistrarEnvios adds shipments to a title already registered by RegistrarTitEnvios.
        $this->transaction(['external_reference' => 'QA-TITLE-REGISTERED']);
        $this->xml->shouldReceive('createRegistrarEnviosXml')->once()
            ->with($this->shipment, 'QA-TITLE-REGISTERED', Mockery::type('string'))
            ->andReturn('<RegistrarEnvios/>');
        $result = $this->invoke('sendEnvios', $this->soap('RegistrarEnvios', '<tracksEnv/>'), $this->shipment);
        $this->assertFalse($result['success']);
        $this->assertSame('MISSING_TRACKS', $result['error_code']);
        $this->assertSame(0, WebserviceTrack::count());
    }

    public function test_registrar_envios_persists_and_recovers_tracks_from_transaction_not_enum_table(): void
    {
        // SQLite translates the real migration enum into a CHECK constraint.
        $ddl = DB::selectOne("SELECT sql FROM sqlite_master WHERE name = 'webservice_tracks'")->sql;
        $this->assertStringContainsString("'RegistrarTitEnvios'", $ddl);
        $this->assertStringNotContainsString("'RegistrarEnvios'", $ddl);
        $this->assertNotContains('RegistrarEnvios', WebserviceTrack::WEBSERVICE_METHODS);
        // RegistrarEnvios adds shipments to a title already registered by RegistrarTitEnvios.
        $this->transaction(['external_reference' => 'QA-TITLE-REGISTERED']);
        $this->xml->shouldReceive('createRegistrarEnviosXml')->once()
            ->with($this->shipment, 'QA-TITLE-REGISTERED', Mockery::type('string'))
            ->andReturn('<RegistrarEnvios/>');
        $body = '<tracksEnv><TrackEnv><idTrack>' . self::ENV_TRACK . '</idTrack><idEnvio>1</idEnvio>'
            . '</TrackEnv></tracksEnv>';
        $result = $this->invoke('sendEnvios', $this->soap('RegistrarEnvios', $body), $this->shipment);
        $this->assertTrue($result['success'], json_encode($result));
        $transaction = WebserviceTransaction::findOrFail($result['transaction_record_id']);
        $this->assertSame([self::ENV_TRACK], $transaction->tracking_numbers);
        $this->assertSame('success', $transaction->status);
        $this->assertSame(0, WebserviceTrack::count());
        // Remove response fallback to demonstrate tracking_numbers is sufficient.
        $transaction->update(['response_xml' => null]);
        $this->assertSame([11 => ['carga_suelta' => [self::ENV_TRACK], 'cont_vacios' => []]],
            $this->invoke('getTracksFromPreviousTransactions', $this->voyage));
    }

    public function test_recovery_separates_shipments_categories_and_excludes_fake_and_foreign_tracks(): void
    {
        $a = $this->transaction();
        $this->track($a, self::ENV_TRACK);
        $this->track($a, self::EMPTY_TRACK, ['track_type' => 'contenedor_vacio']);
        $this->track($a, 'TEST_TRACK_11');
        $this->track($a, '2026AR0000000044', ['afip_metadata' => ['is_fake' => true]]);
        $this->track($this->transaction(['shipment_id' => 12]), self::OTHER_TRACK);
        $this->track($this->transaction(['voyage_id' => 8]), '2026AR0000000055');
        $this->track($this->transaction(['company_id' => 2]), '2026AR0000000066');
        $this->transaction(['soap_action' => self::NS . '/RegistrarEnvios',
            'tracking_numbers' => [self::ENV_TRACK, 'TEST_TRACK_OLD']]);
        $this->transaction(['soap_action' => self::NS . '/RegistrarTitMicDta',
            'tracking_numbers' => ['2026AR0000000077']]);
        $groups = $this->invoke('getTracksFromPreviousTransactions', $this->voyage);
        $this->assertSame([
            11 => ['carga_suelta' => [self::ENV_TRACK], 'cont_vacios' => [self::EMPTY_TRACK]],
            12 => ['carga_suelta' => [self::OTHER_TRACK], 'cont_vacios' => []],
        ], $groups);
        $this->assertSame($groups[11], $this->invoke('tracksForShipment', $groups, $this->shipment));
        $this->assertSame($groups[12], $this->invoke('tracksForShipment', $groups, $this->shipmentFixture(12)));
        $this->assertSame(['carga_suelta' => [], 'cont_vacios' => []],
            $this->invoke('tracksForShipment', $groups, $this->shipmentFixture(13)));
    }

    public function test_only_fake_persisted_tracks_block_micdta_before_xml_or_soap(): void
    {
        $transaction = $this->transaction();
        $this->track($transaction, 'TEST_TRACK_11');
        $this->track($transaction, self::ENV_TRACK, ['afip_metadata' => ['is_fake' => true]]);
        $this->xml->shouldNotReceive('createRegistrarMicDtaXml');
        $result = $this->invoke('processRegistrarMicDta', $this->voyage, []);
        $this->assertFalse($result['success']);
        $this->assertSame('MISSING_TRACKS', $result['error_code']);
        $this->assertSame(1, WebserviceTransaction::count());
        $this->assertSame(['generated'], WebserviceTrack::distinct()->pluck('status')->all());
    }

    public function test_consumption_updates_existing_tracks_without_duplicates_or_other_shipments(): void
    {
        $a = $this->transaction();
        $first = $this->track($a, self::ENV_TRACK);
        $second = $this->track($a, self::EMPTY_TRACK, ['track_type' => 'contenedor_vacio']);
        $other = $this->track($this->transaction(['shipment_id' => 12]), self::OTHER_TRACK);
        $ids = WebserviceTrack::orderBy('id')->pluck('id')->all();
        $groups = ['carga_suelta' => [self::ENV_TRACK, self::OTHER_TRACK], 'cont_vacios' => [self::EMPTY_TRACK]];
        $this->invoke('saveTracks', $this->voyage, $groups, $this->shipment);
        $this->invoke('saveTracks', $this->voyage, $groups, $this->shipment);
        $this->assertSame($ids, WebserviceTrack::orderBy('id')->pluck('id')->all());
        foreach ([$first, $second] as $track) {
            $track->refresh();
            $this->assertSame('used_in_micdta', $track->status);
            $this->assertNotNull($track->used_at);
            $this->assertSame(['RegistrarTitEnvios', 'used_in_micdta'], $track->process_chain);
        }
        $this->assertSame('generated', $other->fresh()->status);
    }

    public function test_pending_transaction_is_scoped_by_voyage_shipment_and_soap_action(): void
    {
        $target = $this->transaction(['soap_action' => self::NS . '/RegistrarMicDta', 'status' => 'pending',
            'created_at' => now()->subMinute()]);
        $decoys = [
            $this->transaction(['soap_action' => self::NS . '/RegistrarMicDta', 'status' => 'pending', 'voyage_id' => 8]),
            $this->transaction(['soap_action' => self::NS . '/RegistrarMicDta', 'status' => 'pending', 'shipment_id' => 12]),
            $this->transaction(['soap_action' => self::NS . '/RegistrarTitMicDta', 'status' => 'pending']),
        ];
        $groups = ['carga_suelta' => [self::ENV_TRACK], 'cont_vacios' => []];
        $this->expectMicDta($groups, $target->transaction_id);
        $result = $this->invoke('registrarMicDta', $this->voyage, $groups, $this->shipment);
        $this->assertTrue($result['success'], json_encode($result));
        $this->assertSame($target->transaction_id, $result['transaction_id']);
        $this->assertSame('success', $target->fresh()->status);
        $this->assertSame(4, WebserviceTransaction::count());
        foreach ($decoys as $decoy) {
            $this->assertSame('pending', $decoy->fresh()->status);
            $this->assertNull($decoy->fresh()->request_xml);
        }
    }

    public function test_wrapper_uses_real_filtered_tracks_and_recognizes_mic_dta_id_not_other_operation_success(): void
    {
        $tx = $this->transaction();
        $real = $this->track($tx, self::ENV_TRACK);
        $fake = $this->track($tx, self::OTHER_TRACK, ['afip_metadata' => ['is_fake' => true]]);
        $this->track($tx, 'TEST_TRACK_11');
        $otherOperation = $this->transaction(['soap_action' => self::NS . '/RegistrarTitMicDta',
            'external_reference' => 'QA-TITLE-NOT-A-MIC']);
        $this->expectMicDta(['carga_suelta' => [self::ENV_TRACK], 'cont_vacios' => []]);
        $result = $this->invoke('processRegistrarMicDta', $this->voyage, []);
        $this->assertTrue($result['success'], json_encode($result));
        $this->assertSame([self::MIC_ID], $result['micdta_ids']);
        $this->assertCount(1, $result['results_per_shipment']);
        $this->assertSame(self::MIC_ID, $result['results_per_shipment'][0]['mic_dta_id']);
        $this->assertSame('used_in_micdta', $real->fresh()->status);
        $this->assertSame('generated', $fake->fresh()->status);
        $this->assertSame(3, WebserviceTrack::count());
        $this->assertSame('QA-TITLE-NOT-A-MIC', $otherOperation->fresh()->external_reference);
    }

    public function test_existing_successful_registrar_micdta_is_reused_without_sending_again(): void
    {
        $this->track($this->transaction(), self::ENV_TRACK);
        $this->transaction(['soap_action' => self::NS . '/RegistrarTitMicDta', 'external_reference' => 'QA-TITLE']);
        $this->transaction(['soap_action' => self::NS . '/RegistrarMicDta', 'external_reference' => self::MIC_ID]);
        $this->xml->shouldNotReceive('createRegistrarMicDtaXml');
        $result = $this->invoke('processRegistrarMicDta', $this->voyage, []);
        $this->assertTrue($result['success']);
        $this->assertSame([self::MIC_ID], $result['micdta_ids']);
        $this->assertSame([], $result['results_per_shipment']);
    }

    public function test_prevalidation_blocks_micdta_header_missing_after_cuscar_import(): void
    {
        $vessel = (new \App\Models\Vessel())->forceFill([
            'name' => '250-22/300-1',
            'registration_number' => null,
        ]);
        $vessel->setRelation(
            'vesselType',
            (new \App\Models\VesselType())->forceFill([
                'code' => 'SELF_CARGO_001',
            ])
        );
        $vessel->setRelation(
            'flagCountry',
            (new \App\Models\Country())->forceFill([
                'alpha2_code' => 'PY',
            ])
        );

        $owner = (new \App\Models\Client())->forceFill([
            'legal_name' => 'MSG',
            'address' => null,
            'tax_id' => '30712412093',
        ]);
        $owner->setRelation(
            'country',
            (new \App\Models\Country())->forceFill([
                'alpha2_code' => 'PY',
            ])
        );
        $vessel->setRelation('owner', $owner);

        $shipment = (new Shipment())->forceFill([
            'id' => 386,
            'shipment_number' => 'CMSP-QA',
        ]);
        $shipment->setRelation('vessel', $vessel);
        $shipment->setRelation('captain', null);

        $voyage = (new Voyage())->forceFill(['id' => 380]);
        $voyage->setRelation('leadVessel', $vessel);
        $voyage->setRelation('captain', null);

        $errors = $this->invoke(
            'validateRegistrarMicDtaShipmentHeader',
            $shipment,
            $voyage
        );

        $this->assertContains(
            'Shipment CMSP-QA: matrícula de embarcación ausente o mayor a 10 caracteres.',
            $errors
        );
        $this->assertContains(
            'Shipment CMSP-QA: domicilio del propietario ausente o mayor a 150 caracteres.',
            $errors
        );
        $this->assertContains(
            'Shipment CMSP-QA: RegistrarMicDta requiere capitán para esta embarcación.',
            $errors
        );
    }

    public function test_preview_does_not_invent_registrar_envios_title_id(): void
    {
        $source = file_get_contents(
            base_path(
                'app/Http/Controllers/Company/Simple/SimpleManifestController.php'
            )
        );

        $this->assertIsString($source);

        $start = strpos($source, 'public function micDtaPreviewXml');
        $end = strpos($source, 'private function getMicDtaStatus', $start);

        $this->assertNotFalse($start);
        $this->assertNotFalse($end);

        $preview = substr($source, $start, $end - $start);

        $this->assertStringContainsString(
            'createRegistrarTitEnviosXml',
            $preview
        );
        $this->assertStringNotContainsString(
            'createRegistrarEnviosXml',
            $preview
        );
        $this->assertStringContainsString(
            "'envios_xml' => null",
            $preview
        );
    }

    public function test_real_xml_writers_keep_cargo_and_empty_container_tracks_separate(): void
    {
        $loose = (new ShipmentItem())->setRelation('containers', new Collection());
        $empty = (new Container())->forceFill(['condition' => 'V']);
        $boxed = (new ShipmentItem())->setRelation('containers', new Collection([$empty]));
        $bill = (new BillOfLading())->setRelation('shipmentItems', new Collection([$loose, $boxed]));
        $shipment = (new Shipment())->setRelation('billsOfLading', new Collection([$bill]));
        $generator = new SimpleXmlGenerator($this->company);
        $writer = new \XMLWriter();
        $writer->openMemory();
        $writer->startElement('micDta');
        $tracks = ['carga_suelta' => [self::ENV_TRACK], 'cont_vacios' => [self::EMPTY_TRACK]];
        foreach (['writeCargasSueltasIdTrack', 'writeTitTransContVaciosIdTrack'] as $method) {
            (new ReflectionMethod($generator, $method))->invoke($generator, $writer, $this->voyage, $tracks, $shipment);
        }
        $writer->endElement();
        $xml = simplexml_load_string($writer->outputMemory());
        $this->assertSame([self::ENV_TRACK], array_map('strval', $xml->xpath('//cargasSueltasIdTrack/cargaSueltaIdTrack')));
        $this->assertSame([self::EMPTY_TRACK], array_map('strval', $xml->xpath('//titTransContVaciosIdTrack/titTransContVacioIdTrack')));
    }
}
