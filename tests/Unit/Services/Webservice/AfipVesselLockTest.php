<?php

namespace Tests\Unit\Services\Webservice;

use App\Services\Simple\AfipVesselLock;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Esquema mínimo aislado en SQLite :memory:. Las transacciones son fixtures
 * locales: no se consulta ni se envía nada a AFIP.
 */
class AfipVesselLockTest extends TestCase
{
    private const NS = 'Ar.Gob.Afip.Dga.wgesregsintia2/';
    private const VOYAGE = 10;
    private const VESSEL = 100;
    private const OTHER_VESSEL = 200;
    private const SHIPMENT = 1000;
    private const OTHER_SHIPMENT = 2000;

    private AfipVesselLock $lock;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'logging.default' => 'null',
            'audit.enabled' => false,
        ]);
        DB::purge('sqlite');
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());

        Schema::create('companies', function (Blueprint $t) {
            $t->id(); $t->string('legal_name')->nullable(); $t->string('ws_environment')->nullable(); $t->timestamps();
        });
        Schema::create('shipments', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('voyage_id'); $t->unsignedBigInteger('vessel_id');
        });
        Schema::create('webservice_transactions', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('company_id');
            $t->unsignedBigInteger('voyage_id')->nullable(); $t->unsignedBigInteger('shipment_id')->nullable();
            $t->string('soap_action')->nullable(); $t->string('country', 2)->default('AR');
            $t->string('environment')->default('testing'); $t->string('status')->default('pending');
            $t->string('external_reference')->nullable(); $t->longText('request_xml')->nullable();
            $t->longText('response_xml')->nullable();
            $t->json('success_data')->nullable(); $t->json('additional_metadata')->nullable();
        });

        DB::table('companies')->insert(['id' => 1, 'legal_name' => 'QA', 'ws_environment' => 'testing']);
        DB::table('shipments')->insert([
            ['id' => self::SHIPMENT, 'voyage_id' => self::VOYAGE, 'vessel_id' => self::VESSEL],
            ['id' => self::OTHER_SHIPMENT, 'voyage_id' => self::VOYAGE, 'vessel_id' => self::OTHER_VESSEL],
        ]);

        $this->lock = new AfipVesselLock();
    }

    private function tx(string $method, array $overrides = []): int
    {
        foreach (['success_data', 'additional_metadata'] as $json) {
            if (isset($overrides[$json])) {
                $overrides[$json] = json_encode($overrides[$json]);
            }
        }

        return DB::table('webservice_transactions')->insertGetId(array_replace([
            'company_id' => 1, 'voyage_id' => self::VOYAGE, 'shipment_id' => self::SHIPMENT,
            'soap_action' => self::NS . $method, 'status' => 'success',
        ], $overrides));
    }

    private function titles(string ...$titles): string
    {
        $body = implode('', array_map(fn ($t) => "<TitTransEnvio><idTitTrans>{$t}</idTitTrans></TitTransEnvio>", $titles));

        return '<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"><soap:Body>'
            . '<RegistrarTitEnvios xmlns="Ar.Gob.Afip.Dga.wgesregsintia2"><titTransEnvios>' . $body
            . '</titTransEnvios></RegistrarTitEnvios></soap:Body></soap:Envelope>';
    }

    private function annulTitle(string $title, string $status = 'success'): int
    {
        return $this->tx('AnularTitulo', [
            'shipment_id' => null, 'status' => $status, 'success_data' => ['titulo_anulado' => $title],
        ]);
    }

    private function inspect(int $vessel = self::VESSEL): array
    {
        return $this->lock->inspectAfipVesselLock(1, self::VOYAGE, $vessel);
    }

    public function test_vessel_never_sent_is_free(): void
    {
        $this->assertSame(['locked' => false, 'reasons' => []], $this->inspect());
    }

    public function test_registered_title_locks_until_every_title_is_annulled(): void
    {
        $this->tx('RegistrarTitEnvios', ['request_xml' => $this->titles('BL-1', 'BL-2')]);
        $this->assertTrue($this->inspect()['locked']);

        $this->annulTitle('BL-1');
        $result = $this->inspect();
        $this->assertTrue($result['locked']);
        $this->assertStringContainsString('BL-2', $result['reasons'][0]['message']);
        $this->assertStringNotContainsString('BL-1', $result['reasons'][0]['message']);

        $this->annulTitle('BL-2', 'error');
        $this->assertTrue($this->inspect()['locked']);

        $this->annulTitle('BL-2');
        $this->assertFalse($this->inspect()['locked']);
    }

    public function test_new_registration_after_annulment_locks_again(): void
    {
        $this->tx('RegistrarTitEnvios', ['request_xml' => $this->titles('BL-1')]);
        $this->annulTitle('BL-1');
        $this->assertFalse($this->inspect()['locked']);

        $this->tx('RegistrarEnvios', ['request_xml' => $this->titles('BL-1')]);
        $this->assertTrue($this->inspect()['locked']);
    }

    public function test_blocking_and_non_blocking_statuses(): void
    {
        foreach (['pending', 'error', 'retry', 'rejected', 'skipped'] as $status) {
            $this->tx('RegistrarTitEnvios', ['status' => $status, 'request_xml' => $this->titles('BL-' . $status)]);
        }
        $this->assertFalse($this->inspect()['locked']);

        foreach (['processing', 'sending', 'sent', 'success'] as $status) {
            DB::table('webservice_transactions')->delete();
            $this->tx('RegistrarMicDta', ['status' => $status, 'external_reference' => '26ARMIF00000001']);
            $this->assertTrue($this->inspect()['locked'], $status);
        }
    }

    public function test_cancelled_locks_only_with_afip_response_evidence(): void
    {
        $this->tx('RegistrarTitEnvios', ['status' => 'cancelled', 'request_xml' => $this->titles('BL-1')]);
        $this->assertFalse($this->inspect()['locked']);

        $this->tx('RegistrarTitEnvios', ['status' => 'cancelled', 'external_reference' => 'BL-2',
            'request_xml' => $this->titles('BL-2')]);
        $this->assertTrue($this->inspect()['locked']);

        DB::table('webservice_transactions')->delete();
        $this->tx('RegistrarMicDta', ['status' => 'cancelled', 'success_data' => ['idMicDta' => 'X']]);
        $this->assertTrue($this->inspect()['locked']);

        // Reset local que solo conserva la respuesta de AFIP.
        DB::table('webservice_transactions')->delete();
        $this->tx('RegistrarTitEnvios', ['status' => 'cancelled', 'request_xml' => $this->titles('BL-3'),
            'response_xml' => '<soap:Envelope><soap:Body><RegistrarTitEnviosResponse/></soap:Body></soap:Envelope>']);
        $this->assertTrue($this->inspect()['locked']);

        DB::table('webservice_transactions')->delete();
        $this->tx('RegistrarTitEnvios', ['status' => 'cancelled', 'request_xml' => $this->titles('BL-4'),
            'response_xml' => '   ']);
        $this->assertFalse($this->inspect()['locked']);
    }

    public function test_scope_by_vessel_environment_company_and_country(): void
    {
        $this->tx('RegistrarTitEnvios', ['shipment_id' => self::OTHER_SHIPMENT, 'request_xml' => $this->titles('BL-9')]);
        $this->tx('RegistrarTitEnvios', ['environment' => 'production', 'request_xml' => $this->titles('BL-8')]);
        $this->tx('RegistrarTitEnvios', ['company_id' => 2, 'request_xml' => $this->titles('BL-7')]);
        $this->tx('RegistrarTitEnvios', ['country' => 'PY', 'request_xml' => $this->titles('BL-6')]);
        $this->tx('RegistrarTitEnvios', ['voyage_id' => 11, 'request_xml' => $this->titles('BL-5')]);
        $this->assertFalse($this->inspect()['locked']);
        $this->assertTrue($this->inspect(self::OTHER_VESSEL)['locked']);
    }

    public function test_transaction_without_shipment_locks_every_vessel_of_the_voyage(): void
    {
        $this->tx('RegistrarMicDta', ['shipment_id' => null, 'external_reference' => '26ARMIF00000001']);
        $this->assertTrue($this->inspect()['locked']);
        $this->assertTrue($this->inspect(self::OTHER_VESSEL)['locked']);
    }

    public function test_micdta_is_released_only_by_confirmed_annulment(): void
    {
        $micDta = $this->tx('RegistrarMicDta', ['external_reference' => '26ARMIF00000001']);

        $this->tx('SolicitarAnularMicDta', ['success_data' => ['micdta_id' => '26ARMIF00000001'],
            'additional_metadata' => ['original_micdta_transaction_id' => $micDta]]);
        $this->assertTrue($this->inspect()['locked']);

        $this->tx('SolicitarAnularMicDta', ['success_data' => [
            'micdta_id' => '26ARMIF00000001', 'aprobacion_confirmada_at' => '2026-11-16 10:00:00',
        ]]);
        $this->assertFalse($this->inspect()['locked']);
    }

    public function test_micdta_annulment_is_matched_by_original_transaction(): void
    {
        $micDta = $this->tx('RegistrarMicDta');
        $this->assertTrue($this->inspect()['locked']);

        $this->tx('SolicitarAnularMicDta', [
            'success_data' => ['aprobacion_confirmada_at' => '2026-11-16 10:00:00'],
            'additional_metadata' => ['original_micdta_transaction_id' => $micDta],
        ]);
        $this->assertFalse($this->inspect()['locked']);
    }

    public function test_link_titles_to_micdta_is_released_by_its_micdta_annulment(): void
    {
        $xml = '<RegistrarTitMicDta><titMicDtas><TitMicDta><idMicDta>26ARMIF00000001</idMicDta>'
            . '</TitMicDta></titMicDtas></RegistrarTitMicDta>';
        $this->tx('RegistrarTitMicDta', ['request_xml' => $xml]);
        $this->assertTrue($this->inspect()['locked']);

        $this->tx('SolicitarAnularMicDta', ['success_data' => [
            'micdta_id' => '26ARMIF00000001', 'aprobacion_confirmada_at' => '2026-11-16 10:00:00',
        ]]);
        $this->assertFalse($this->inspect()['locked']);
    }

    public function test_registration_without_identifiable_title_or_micdta_locks(): void
    {
        $this->tx('RegistrarTitEnvios');
        $this->tx('RegistrarTitMicDta');
        $result = $this->inspect();
        $this->assertTrue($result['locked']);
        $this->assertCount(2, $result['reasons']);
    }

    public function test_desvincular_rectificar_and_annulment_before_registration_do_not_release(): void
    {
        $this->annulTitle('BL-1');
        $this->tx('RegistrarTitEnvios', ['request_xml' => $this->titles('BL-1')]);
        $this->tx('DesvincularTitMicDta');
        $this->tx('RectifConvoyMicDta');
        $result = $this->inspect();
        $this->assertTrue($result['locked']);
        $this->assertSame('RegistrarTitEnvios', $result['reasons'][0]['method']);
    }

    public function test_soap_action_without_namespace_is_recognized(): void
    {
        $this->tx('RegistrarTitEnvios', ['soap_action' => 'RegistrarMicDta', 'external_reference' => 'M1']);
        $this->assertTrue($this->inspect()['locked']);
    }

    public function test_company_without_valid_environment_considers_every_environment(): void
    {
        DB::table('companies')->where('id', 1)->update(['ws_environment' => null]);
        $this->tx('RegistrarMicDta', ['environment' => 'production', 'external_reference' => 'M1']);
        $this->assertTrue($this->inspect()['locked']);
    }

    public function test_messages_avoid_internal_method_names(): void
    {
        $this->tx('RegistrarMicDta', ['external_reference' => 'QA-MIC-1']);
        $this->tx('RegistrarTitMicDta');
        $this->tx('RegistrarTitEnvios', ['request_xml' => '<sin-titulos/>']);

        $messages = array_column($this->inspect()['reasons'], 'message');

        $this->assertSame([
            'MIC/DTA QA-MIC-1 registrado en AFIP sin anulación aprobada.',
            'MIC/DTA registrado en AFIP sin número identificable y sin anulación aprobada.',
            'Registro de títulos en AFIP sin títulos identificables en el envío.',
        ], $messages);
    }
}
