<?php

namespace Tests\Unit\Services\Webservice;

use App\Models\WebserviceTrack;
use App\Models\WebserviceTransaction;
use App\Services\Simple\HidroviaTransshipmentTrackPersistenceService;
use App\Services\Simple\HidroviaTransshipmentTrackResponseMapper;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Persistencia real en la tabla webservice_tracks con migraciones WS originales.
 * Sólo SQLite :memory:. Las tres tablas de negocio reproducen exclusivamente
 * las columnas REALES consultadas, tomadas de sus migraciones existentes.
 * No conecta a ARCA, no crea envíos ni modifica datos persistentes.
 */
class HidroviaTransshipmentTrackPersistenceServiceTest extends TestCase
{
    private const ACTION = 'Ar.Gob.Afip.Dga.wgesregsintia2/RegistrarTitEnvios';
    private const FIRST = '2026AR0000000011';
    private const SECOND = '2026AR0000000022';
    private const THIRD = '2026AR0000000033';

    private HidroviaTransshipmentTrackPersistenceService $service;
    private WebserviceTransaction $transaction;

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

        foreach ([
            '2025_07_23_120939_create_webservice_transactions_table.php',
            '2025_09_08_122553_create_webservice_tracks_table.php',
        ] as $migration) {
            (require database_path('migrations/' . $migration))->up();
        }
        // Campos de negocio ya definidos por las migraciones 2025-07-16.
        Schema::create('voyages', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('company_id');
        });
        Schema::create('shipments', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('voyage_id');
            $table->unsignedBigInteger('vessel_id');
        });
        Schema::create('bills_of_lading', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('shipment_id');
            $table->string('is_transit_transshipment', 1);
            $table->softDeletes();
        });
        DB::table('voyages')->insert(['id' => 7, 'company_id' => 1]);
        DB::table('shipments')->insert([
            ['id' => 11, 'voyage_id' => 7, 'vessel_id' => 21],
            ['id' => 12, 'voyage_id' => 7, 'vessel_id' => 22],
        ]);
        DB::table('bills_of_lading')->insert([
            ['id' => 31, 'shipment_id' => 11, 'is_transit_transshipment' => 'S'],
            ['id' => 32, 'shipment_id' => 11, 'is_transit_transshipment' => 'S'],
        ]);
        $this->transaction = $this->transaction();
        $this->service = new HidroviaTransshipmentTrackPersistenceService(
            new HidroviaTransshipmentTrackResponseMapper()
        );
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
                'idTitTrans' => '123456',
                'bill_of_lading_id' => 32,
                'shipments' => [
                    ['id_envio' => 1, 'shipment_id' => 11, 'vessel_id' => 21],
                ],
            ],
        ];
    }

    private function response(string $first = self::FIRST, string $second = self::SECOND): string
    {
        // El orden recibido es diferente al de los envíos solicitados.
        $body = '<titTracksEnv>'
            . '<TitTrackEnv><idTitTrans>123456</idTitTrans><tracksEnv>'
            . '<TrackEnv><idTrack>' . self::THIRD . '</idTrack><idEnvio>1</idEnvio></TrackEnv>'
            . '</tracksEnv></TitTrackEnv>'
            . '<TitTrackEnv><idTitTrans>BL-A</idTitTrans><tracksEnv>'
            . '<TrackEnv><idTrack>' . $second . '</idTrack><idEnvio>2</idEnvio></TrackEnv>'
            . '<TrackEnv><idTrack>' . $first . '</idTrack><idEnvio>1</idEnvio></TrackEnv>'
            . '</tracksEnv></TitTrackEnv></titTracksEnv>';
        return '<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">'
            . '<soap:Body><RegistrarTitEnviosResponse><RegistrarTitEnviosResult>'
            . $body . '</RegistrarTitEnviosResult></RegistrarTitEnviosResponse></soap:Body></soap:Envelope>';
    }

    private function transaction(array $overrides = []): WebserviceTransaction
    {
        return WebserviceTransaction::create(array_replace([
            'company_id' => 1, 'user_id' => 1, 'voyage_id' => 7,
            'transaction_id' => 'QA-MULTIBARGE-' . (WebserviceTransaction::count() + 1),
            'webservice_type' => 'micdta', 'country' => 'AR', 'environment' => 'testing',
            'webservice_url' => 'https://example.invalid/not-called',
            'soap_action' => self::ACTION, 'status' => 'success',
            'response_xml' => $this->response(),
            'ip_address' => '127.0.0.1',
        ], $overrides));
    }

    private function persist(?array $expected = null, ?int $transactionId = null): array
    {
        return $this->service->persistRegistrarTitEnvios(
            $transactionId ?? $this->transaction->id,
            $expected ?? $this->expected()
        );
    }

    private function reject(string $message, ?array $expected = null, ?int $transactionId = null): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);
        try {
            $this->persist($expected, $transactionId);
        } finally {
            $this->assertSame(0, WebserviceTrack::count(), 'No debe dejar TRACKs parciales.');
        }
    }

    public function test_persists_each_real_track_for_its_bill_shipment_and_id_envio(): void
    {
        $rows = $this->persist();
        $this->assertCount(3, $rows);
        $this->assertSame([self::FIRST, self::SECOND, self::THIRD], array_column($rows, 'track_number'));
        $this->assertSame([11, 12, 11], array_map(fn ($row) => (int) $row->shipment_id, $rows));
        $this->assertSame([31, 31, 32], array_map(fn ($row) => (int) $row->bill_of_lading_id, $rows));
        $this->assertSame([1, 2, 1], array_map(fn ($row) => $row->afip_metadata['idEnvio'], $rows));
        $this->assertSame([21, 22, 21], array_map(fn ($row) => $row->afip_metadata['vessel_id'], $rows));
        $this->assertSame(['BL-A', 'BL-A', '123456'], array_column($rows, 'afip_title_number'));
        $this->assertSame('123456', $rows[2]->reference_number);
        $this->assertSame('RegistrarTitEnvios', $rows[0]->webservice_method);
        $this->assertSame('envio', $rows[0]->track_type);
        $this->assertSame('bill', $rows[0]->reference_type);
        $this->assertSame((int) $this->transaction->id, (int) $rows[0]->webservice_transaction_id);
        $this->assertSame('127.0.0.1', $rows[0]->created_from_ip);
        $this->assertSame(['RegistrarTitEnvios'], $rows[0]->process_chain);
        $this->assertSame(3, WebserviceTrack::count());
    }

    public function test_replay_is_idempotent_and_preserves_used_track_status_and_timestamps(): void
    {
        $first = $this->persist();
        $first[0]->update(['status' => 'used_in_micdta', 'used_at' => now(),
            'process_chain' => ['RegistrarTitEnvios', 'RegistrarMicDta']]);
        $before = WebserviceTrack::orderBy('id')->get()
            ->map(fn (WebserviceTrack $track) => $track->getRawOriginal())->all();
        $again = $this->persist();
        $after = WebserviceTrack::orderBy('id')->get()
            ->map(fn (WebserviceTrack $track) => $track->getRawOriginal())->all();
        $this->assertSame($before, $after);
        $this->assertSame(array_map(fn ($row) => $row->id, $first),
            array_map(fn ($row) => $row->id, $again));
        $this->assertSame('used_in_micdta', $again[0]->status);
        $this->assertSame(3, WebserviceTrack::count());
    }

    public function test_transaction_must_be_successful(): void
    {
        $this->transaction->update(['status' => 'sent']);
        $this->reject('exitosa');
    }

    public function test_transaction_must_be_correct_operation_and_country(): void
    {
        foreach ([
            'soap_action' => 'Ar.Gob.Afip.Dga.wgesregsintia2/RegistrarEnvios',
            'country' => 'PY',
            'webservice_type' => 'transbordo',
            'response_xml' => '',
        ] as $field => $value) {
            $this->transaction->update([$field => $value]);
            try {
                $this->persist();
                $this->fail("Se aceptó {$field} incorrecto.");
            } catch (InvalidArgumentException $e) {
                $this->assertStringContainsString('exitosa', $e->getMessage());
                $this->assertSame(0, WebserviceTrack::count());
            }
            $this->transaction->refresh();
            $this->transaction->update([$field => match ($field) {
                'soap_action' => self::ACTION,
                'country' => 'AR',
                'webservice_type' => 'micdta',
                'response_xml' => $this->response(),
            }]);
        }
    }

    public function test_company_must_own_voyage(): void
    {
        DB::table('voyages')->where('id', 7)->update(['company_id' => 2]);
        $this->reject('viaje autorizado');
    }

    public function test_bill_must_belong_to_voyage(): void
    {
        DB::table('shipments')->where('id', 11)->update(['voyage_id' => 8]);
        $this->reject('conocimiento no pertenece');
    }

    public function test_bill_must_be_transshipment_and_not_soft_deleted(): void
    {
        DB::table('bills_of_lading')->where('id', 31)->update(['is_transit_transshipment' => 'N']);
        $this->reject('no es transbordo');
    }

    public function test_deleted_bill_cannot_generate_tracks(): void
    {
        DB::table('bills_of_lading')->where('id', 31)->update(['deleted_at' => now()]);
        $this->reject('conocimiento no pertenece');
    }

    public function test_barge_must_belong_to_voyage(): void
    {
        DB::table('shipments')->where('id', 12)->update(['voyage_id' => 8]);
        $this->reject('Una barcaza no pertenece');
    }

    public function test_vessel_must_match_real_shipment(): void
    {
        $expected = $this->expected();
        $expected[0]['shipments'][1]['vessel_id'] = 99;
        $this->reject('embarcación de un TRACK', $expected);
    }

    public function test_conflicting_track_number_from_another_transaction_is_rejected(): void
    {
        $other = $this->transaction(['response_xml' => $this->response(self::FIRST, '2026AR0000000099')]);
        $this->persist(null, $other->id);
        $count = WebserviceTrack::count();
        $this->transaction->update(['response_xml' => $this->response()]);
        try {
            $this->persist();
            $this->fail('Un TRACK no puede pasar de una transacción a otra.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('No se sobrescribe', $e->getMessage());
            $this->assertSame($count, WebserviceTrack::count());
        }
    }

    public function test_different_track_for_same_bill_id_envio_is_rejected(): void
    {
        $this->persist();
        $count = WebserviceTrack::count();
        $this->transaction->update(['response_xml' => $this->response('2026AR0000000099')]);
        try {
            $this->persist();
            $this->fail('Se aceptó un nuevo TRACK para el mismo envío.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('No se sobrescribe', $e->getMessage());
            $this->assertSame($count, WebserviceTrack::count());
        }
    }

    public function test_failure_during_second_insert_rolls_back_first_insert(): void
    {
        // Fallo artificial exclusivamente en la BD en memoria para probar
        // atomicidad, sin alterar el código productivo ni el esquema real.
        DB::statement("CREATE TRIGGER qa_reject_second_track BEFORE INSERT ON webservice_tracks "
            . "WHEN NEW.track_number = '" . self::SECOND . "' "
            . "BEGIN SELECT RAISE(ABORT, 'qa simulated insert failure'); END");
        $this->expectException(QueryException::class);
        try {
            $this->persist();
        } finally {
            $this->assertSame(0, WebserviceTrack::count());
        }
    }
}
