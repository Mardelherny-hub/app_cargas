<?php

namespace Tests\Feature\Webservice;

use App\Models\{Company, User, Voyage, WebserviceTransaction, WebserviceResponse, WsaaToken};
use App\Services\Simple\{ArgentinaMicDtaStatusService, SimpleXmlGenerator, WebserviceEnvironment};
use Illuminate\Support\Facades\{DB, Http};
use Mockery;
use ReflectionProperty;
use SoapClient;
use Tests\TestCase;

/**
 * Opt-in: base MySQL temporal con esquema real y fixtures de empresa/usuario/buque/puerto.
 * MICDTA_STATUS_MYSQL_SOCKET y MICDTA_STATUS_MYSQL_DATABASE (nombre *_qa).
 * Todas las escrituras se revierten. Nunca usa la conexión configurada por .env.
 */
class MicDtaStatusMysqlTest extends TestCase
{
    private bool $transactionStarted = false;
    private Company $company;
    private User $user;
    private Voyage $voyage;
    private object $wsaa;

    protected function setUp(): void
    {
        parent::setUp();
        $socket = getenv('MICDTA_STATUS_MYSQL_SOCKET');
        $database = getenv('MICDTA_STATUS_MYSQL_DATABASE');
        if (!$socket || !$database) {
            $this->markTestSkipped('Requiere MySQL temporal con esquema real.');
        }
        $this->assertStringStartsWith('/tmp/', $socket);
        $this->assertStringEndsWith('_qa', $database);
        config(['database.default' => 'mysql', 'database.connections.mysql.host' => 'localhost',
            'database.connections.mysql.unix_socket' => $socket,
            'database.connections.mysql.database' => $database,
            'database.connections.mysql.username' => 'root', 'database.connections.mysql.password' => '',
            'logging.default' => 'null', 'audit.enabled' => false, 'cache.default' => 'array',
            'session.driver' => 'array', 'mail.default' => 'array']);
        DB::purge('mysql');
        $this->assertSame('mysql', DB::connection()->getDriverName());
        $this->assertSame($database, DB::connection()->getDatabaseName());
        $type = DB::selectOne("SHOW COLUMNS FROM webservice_transactions LIKE 'webservice_type'")->Type;
        $this->assertStringStartsWith('enum(', $type);
        $this->assertStringContainsString("'consulta'", $type);
        $this->assertStringNotContainsString("'micdta_status'", $type);
        Http::preventStrayRequests();
        DB::beginTransaction();
        $this->transactionStarted = true;
        $this->company = Company::where('active', true)->firstOrFail();
        $this->company->ws_environment = 'testing';
        $this->company->saveQuietly();
        $this->user = User::firstOrFail();
        $this->user->forceFill(['userable_type' => Company::class, 'userable_id' => $this->company->id])->saveQuietly();
        \Spatie\Permission\Models\Role::findOrCreate('company-admin', 'web');
        $this->user->assignRole('company-admin');
        $this->actingAs($this->user);
        $this->withoutMiddleware();
        $port = DB::table('ports')->first();
        $this->voyage = Voyage::withoutEvents(fn () => Voyage::create([
            'voyage_number' => 'QA-STATUS', 'company_id' => $this->company->id,
            'lead_vessel_id' => DB::table('vessels')->value('id'),
            'origin_country_id' => $port->country_id, 'origin_port_id' => $port->id,
            'destination_country_id' => $port->country_id, 'destination_port_id' => $port->id,
            'voyage_type' => 'single_vessel', 'cargo_type' => 'export',
        ]));
        $this->wsaa = (object) ['calls' => 0, 'fail' => false, 'services' => [], 'environments' => []];
    }

    protected function tearDown(): void
    {
        if ($this->transactionStarted) {
            DB::rollBack();
        }
        parent::tearDown();
    }

    private function original(string $suffix = '1'): WebserviceTransaction
    {
        return WebserviceTransaction::create([
            'company_id' => $this->company->id, 'user_id' => $this->user->id,
            'voyage_id' => $this->voyage->id, 'transaction_id' => 'QA-STATUS-'.$suffix,
            'webservice_type' => 'micdta', 'country' => 'AR', 'status' => 'sent',
            'sent_at' => now(), 'external_reference' => 'QA-MIC-'.$suffix,
            'environment' => $this->company->ws_environment, 'webservice_url' => 'https://unused.invalid',
        ]);
    }

    private function service(int $calls = 1, ?string $response = null): ArgentinaMicDtaStatusService
    {
        $environment = $this->company->ws_environment;
        $generator = new class($this->company, $this->wsaa) extends SimpleXmlGenerator {
            public function __construct(Company $company, private object $issuer) { parent::__construct($company); $this->issuer->environments[] = $company->ws_environment; }
            protected function requestWsaaTokens(string $serviceName): array
            {
                $this->issuer->calls++;
                $this->issuer->services[] = $serviceName;
                if ($this->issuer->fail) { throw new \RuntimeException('Certificado no emitido por AC de confianza <externo>'); }
                return ['token' => 'WSAA-QA<&token', 'sign' => 'WSAA-QA<&sign'];
            }
        };
        $native = Mockery::mock(SoapClient::class);
        $expectation = $native->shouldReceive('__doRequest')->times($calls);
        if ($calls) {
            $expectation->withArgs(function ($xml, $endpoint, $action, $version, $oneWay) use ($environment) {
                $this->assertSame(WebserviceEnvironment::argentinaEndpoint($this->company, 'wgesregsintia2'), $endpoint);
                $this->assertSame('Ar.Gob.Afip.Dga.wgesregsintia2/ConsultarEstadoMicDta', $action);
                $this->assertSame(SOAP_1_2, $version);
                $this->assertFalse($oneWay);
                $this->assertStringNotContainsString('TESTING_TOKEN', $xml);
                $this->assertStringNotContainsString('TESTING_SIGN', $xml);
                $doc = new \DOMDocument(); $this->assertTrue($doc->loadXML($xml));
                $xp = new \DOMXPath($doc);
                $this->assertSame('WSAA-QA<&token', $xp->evaluate('string(//*[local-name()="ticket"])'));
                $this->assertSame('WSAA-QA<&sign', $xp->evaluate('string(//*[local-name()="sign"])'));
                $this->assertSame($environment, $this->company->ws_environment);
                return true;
            })->andReturn($response ?? '<r xmlns="urn:qa"><EstadoMicDta>ACEPTADO</EstadoMicDta></r>');
        }
        $service = new class($this->company, $this->user, $native) extends ArgentinaMicDtaStatusService {
            public function __construct(Company $company, User $user, private SoapClient $fake) { parent::__construct($company, $user); }
            protected function createSoapClient(): SoapClient { return $this->fake; }
        };
        (new ReflectionProperty($service, 'xmlSerializer'))->setValue($service, $generator);
        $this->app->bind(ArgentinaMicDtaStatusService::class, function ($app, array $parameters) use ($service) {
            $this->assertSame($this->company->id, $parameters['company']->id);
            $this->assertSame($this->user->id, $parameters['user']->id);
            return $service;
        });
        return $service;
    }

    private function routeUrl(string $method): string
    {
        foreach (app('router')->getRoutes() as $route) {
            if (str_ends_with($route->getActionName(), '@'.$method)) {
                return '/'.str_replace('{voyage}', (string) $this->voyage->id, $route->uri());
            }
        }
        $this->fail('Ruta real no encontrada: '.$method);
    }

    public function test_individual_testing_creates_valid_transaction_and_response_with_real_auth_path(): void
    {
        $original = $this->original();
        $this->service();
        $response = $this->getJson($this->routeUrl('consultarEstadoIndividual'))->assertOk()->assertJsonPath('success', true);
        $id = $response->json('resultado.resultados.0.consulta_transaction_id');
        $query = WebserviceTransaction::findOrFail($id);
        $this->assertSame('consulta', $query->webservice_type);
        $this->assertSame('Ar.Gob.Afip.Dga.wgesregsintia2/ConsultarEstadoMicDta', $query->soap_action);
        $this->assertSame($original->id, $query->additional_metadata['original_transaction_id']);
        $this->assertSame('status_check', $query->additional_metadata['consultation_type']);
        $this->assertNotEmpty($query->request_xml);
        $this->assertNotEmpty($query->response_xml);
        $this->assertSame('success', $original->fresh()->status);
        $this->assertSame('success', WebserviceResponse::where('transaction_id', $id)->firstOrFail()->response_type);
        $this->assertSame(1, $this->wsaa->calls);
        $this->assertSame(['wgesregsintia2'], $this->wsaa->services);
        $this->assertNotNull(WsaaToken::getValidToken($this->company->id, 'wgesregsintia2', 'testing'));
    }

    public function test_mass_production_reaches_transport_twice_reusing_ta(): void
    {
        $this->company->ws_environment = 'production'; $this->company->saveQuietly();
        $this->original('1'); $this->original('2');
        $this->service(2);
        $this->postJson($this->routeUrl('consultarEstadoMasivo'))->assertOk()
            ->assertJsonPath('success', true)->assertJsonPath('resultado.consultas_exitosas', 2);
        $this->assertSame(1, $this->wsaa->calls);
        $this->assertSame(['production'], $this->wsaa->environments);
        $this->assertSame(2, WebserviceTransaction::where('webservice_type', 'consulta')->where('environment', 'production')->count());
    }

    public function test_wsaa_failure_is_false_preserves_detail_and_never_fabricates_xml(): void
    {
        $this->original(); $this->wsaa->fail = true; $this->service(0);
        $response = $this->getJson($this->routeUrl('consultarEstadoIndividual'))->assertOk()->assertJsonPath('success', false);
        $this->assertStringContainsString('Certificado no emitido', $response->json('details'));
        $this->assertSame('STATUS_QUERY_ERROR', $response->json('error_code'));
        $query = WebserviceTransaction::findOrFail($response->json('transaction_record_id'));
        $this->assertSame($response->json('details'), $query->error_message);
        $this->assertNull($query->request_xml); $this->assertNull($query->response_xml);
    }

    public function test_all_soap_queries_fail_preserving_xml_and_code(): void
    {
        $this->original('1'); $this->original('2');
        $fault = '<s:Envelope xmlns:s="http://www.w3.org/2003/05/soap-envelope"><s:Body><s:Fault><s:Code><s:Value>s:Sender</s:Value></s:Code><s:Reason><s:Text>Detalle externo</s:Text></s:Reason></s:Fault></s:Body></s:Envelope>';
        $this->service(2, $fault);
        $response = $this->postJson($this->routeUrl('consultarEstadoMasivo'))->assertOk()
            ->assertJsonPath('success', false)->assertJsonPath('resultado.consultas_error', 2)
            ->assertJsonPath('details', 'Detalle externo')->assertJsonPath('error_code', 's:Sender');
        foreach (WebserviceTransaction::where('webservice_type', 'consulta')->get() as $query) {
            $this->assertNotEmpty($query->request_xml);
            $this->assertSame($fault, $query->response_xml);
            $this->assertSame('system_error', WebserviceResponse::where('transaction_id', $query->id)->firstOrFail()->response_type);
        }
    }

    public function test_real_database_insert_failure_is_not_success_and_does_not_call_wsaa(): void
    {
        $this->original();
        $this->user->id = 4294967295; // FK inexistente, sólo dentro de esta prueba.
        $service = $this->service(0);
        $result = $service->consultarEstadoTransacciones();
        $this->assertFalse($result['success']);
        $this->assertSame(1, $result['consultas_error']);
        $this->assertStringContainsString('SQLSTATE', $result['resultados'][0]['error']);
        $this->assertSame(0, $this->wsaa->calls);
    }

    public function test_null_and_invalid_environments_block_before_transport(): void
    {
        foreach ([null, 'invalid'] as $environment) {
            $this->company->ws_environment = $environment;
            try {
                $this->service(0);
                $this->fail('Debió bloquear ambiente inválido');
            } catch (\InvalidArgumentException $e) {
                $this->assertStringContainsString('ambiente', $e->getMessage());
            }
        }
        $this->assertSame(0, $this->wsaa->calls);
    }

    public function test_missing_status_in_response_cannot_report_success(): void
    {
        $this->original();
        $result = $this->service(1, '<respuesta/>')->consultarEstadoTransacciones();
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('no contiene EstadoMicDta', $result['error']);
    }
}
