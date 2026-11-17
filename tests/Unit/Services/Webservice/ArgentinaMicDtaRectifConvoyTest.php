<?php

namespace Tests\Unit\Services\Webservice;

use App\Models\Company;
use App\Models\User;
use App\Models\Voyage;
use App\Models\WebserviceTransaction;
use App\Models\WsaaToken;
use App\Services\Simple\ArgentinaMicDtaService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Mockery;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Contract fixtures, never production data or network responses.
 * RectifConvoyMicDta must not be stored as successful when AFIP reports
 * errors inside ListaErrores/DetalleError, the same rule AnularTitulo applies.
 *
 * Only the WS migrations run, on private SQLite :memory:. The SOAP transport
 * is mocked and the WSAA token is a local fixture: nothing is sent to AFIP.
 */
class ArgentinaMicDtaRectifConvoyTest extends TestCase
{
    private const NS = 'Ar.Gob.Afip.Dga.wgesregsintia2';
    private const NRO_VIAJE = 'QA-VIAJE-001';

    private ArgentinaMicDtaService $service;
    private Voyage $voyage;

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
        foreach ([
            '2025_07_23_120939_create_webservice_transactions_table.php',
            '2025_09_16_204927_create_wsaa_tokens_table.php',
        ] as $migration) {
            (require database_path('migrations/' . $migration))->up();
        }

        $company = (new Company())->forceFill([
            'id' => 1, 'ws_environment' => 'testing', 'tax_id' => '30000000007',
        ]);
        $user = (new User())->forceFill(['id' => 1]);

        // This override is only the network boundary, never the business logic.
        $this->service = new class($company, $user) extends ArgentinaMicDtaService {
            public ?\SoapClient $contractSoap = null;
            protected function createSoapClient(): \SoapClient
            {
                if (!$this->contractSoap) {
                    throw new \LogicException('Unexpected SOAP attempt in contract test');
                }
                return $this->contractSoap;
            }
        };

        $this->voyage = (new Voyage())->forceFill(['id' => 7, 'company_id' => 1, 'voyage_number' => 'QA-RECTIF']);
        $this->voyage->exists = true;
        $this->voyage->setRelation('company', $company);

        // Local WSAA token fixture so the XML generator never requests one.
        WsaaToken::create([
            'company_id' => 1, 'service_name' => 'wgesregsintia2', 'environment' => 'testing',
            'token' => 'QA-TOKEN', 'sign' => 'QA-SIGN',
            'issued_at' => now(), 'expires_at' => now()->addHour(), 'status' => 'active',
            'generation_time' => now()->toIso8601String(), 'unique_id' => 'QA-UNIQUE',
            'created_by_process' => 'contract-test',
        ]);

        // The convoy being rectified was registered before.
        WebserviceTransaction::create([
            'company_id' => 1, 'user_id' => 1, 'voyage_id' => 7,
            'transaction_id' => 'QA-CONVOY-1', 'webservice_type' => 'micdta',
            'country' => 'AR', 'environment' => 'testing',
            'webservice_url' => 'https://example.invalid/never-called',
            'soap_action' => self::NS . '/RegistrarConvoy', 'status' => 'success',
            'confirmation_number' => self::NRO_VIAJE,
        ]);
    }

    private function rectify(string $resultBody): array
    {
        $response = '<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"><soap:Body>'
            . '<RectifConvoyMicDtaResponse xmlns="' . self::NS . '"><RectifConvoyMicDtaResult>'
            . $resultBody
            . '</RectifConvoyMicDtaResult></RectifConvoyMicDtaResponse></soap:Body></soap:Envelope>';

        $soap = Mockery::mock(\SoapClient::class); // No constructor / WSDL fetch.
        $soap->shouldReceive('__doRequest')->once()
            ->with(Mockery::type('string'), Mockery::type('string'), self::NS . '/RectifConvoyMicDta', SOAP_1_1, false)
            ->andReturn($response);
        $this->service->contractSoap = $soap;

        return (new ReflectionMethod(ArgentinaMicDtaService::class, 'processRectifConvoyMicDta'))
            ->invoke($this->service, $this->voyage, [
                'nro_viaje' => self::NRO_VIAJE,
                'desc_motivo' => 'Cambio de barcaza',
                'rectif_convoy' => ['id_micdta_remol' => '26ARMIF000000011'],
            ]);
    }

    private function detalle(string $codigo, string $descripcion, string $tipo): string
    {
        return '<DetalleError><Codigo>' . $codigo . '</Codigo><Descripcion>' . $descripcion
            . '</Descripcion><TipoMensaje>' . $tipo . '</TipoMensaje></DetalleError>';
    }

    private function rectifTransaction(): WebserviceTransaction
    {
        return WebserviceTransaction::where('soap_action', 'RectifConvoyMicDta')->sole();
    }

    public function test_afip_error_in_response_stores_error_and_returns_failure(): void
    {
        $result = $this->rectify('<ListaErrores>'
            . $this->detalle('27107', 'Existe un evento pendiente', 'Error')
            . '</ListaErrores>');

        $this->assertFalse($result['success'], json_encode($result));
        $this->assertSame('AFIP_ERROR', $result['error_code']);
        $this->assertSame('Error AFIP: [27107] Existe un evento pendiente', $result['error_message']);
        $this->assertSame('27107', $result['afip_messages']['errores'][0]['codigo']);

        $transaction = $this->rectifTransaction();
        $this->assertSame('error', $transaction->status);
        $this->assertSame('Error AFIP: [27107] Existe un evento pendiente', $transaction->error_message);
        $this->assertNotNull($transaction->response_xml);
    }

    public function test_successful_response_stores_success(): void
    {
        $result = $this->rectify('<ListaErrores>'
            . $this->detalle('0', 'Ejecucion exitosa.', 'Informativo')
            . '</ListaErrores>');

        $this->assertTrue($result['success'], json_encode($result));
        $this->assertSame('RectifConvoyMicDta', $result['method']);

        $transaction = $this->rectifTransaction();
        $this->assertSame('success', $transaction->status);
        $this->assertNull($transaction->error_message);
    }
}
