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
 * AnularTitulo annuls a single title: a RegistrarTitEnvios registration only
 * becomes cancelled once every title in its own request_xml was annulled.
 *
 * Only the WS migrations run, on private SQLite :memory:. The SOAP transport
 * is mocked and the WSAA token is a local fixture: nothing is sent to AFIP.
 */
class ArgentinaMicDtaAnularTituloScopeTest extends TestCase
{
    private const NS = 'Ar.Gob.Afip.Dga.wgesregsintia2';

    private ArgentinaMicDtaService $service;
    private Voyage $voyage;
    private ?int $lastAnnulSecond = null;

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

        $this->voyage = (new Voyage())->forceFill(['id' => 7, 'company_id' => 1, 'voyage_number' => 'QA-ANULAR']);
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
    }

    /** RegistrarTitEnvios request with one cargo title per BL and optional empty-container titles. */
    private function registrationXml(array $cargoTitles, array $emptyTitles = []): string
    {
        $cargo = '';
        foreach ($cargoTitles as $title) {
            $cargo .= '<TitTransEnvio><codViaTrans>8</codViaTrans><idTitTrans>' . $title . '</idTitTrans>'
                . '<envios><Envio><idEnvio>1</idEnvio></Envio></envios></TitTransEnvio>';
        }
        $empty = '';
        foreach ($emptyTitles as $title) {
            $empty .= '<TitTransContVacio><idTitTrans>' . $title . '</idTitTrans>'
                . '<idContenedores><string>QAUU0000001</string></idContenedores></TitTransContVacio>';
        }

        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"><soap:Body>'
            . '<RegistrarTitEnvios xmlns="' . self::NS . '"><argRegistrarTitEnviosParam>'
            . '<idTransaccion>TITQA</idTransaccion>'
            . '<titulosTransEnvios>' . $cargo . '</titulosTransEnvios>'
            . ($empty !== '' ? '<titulosTransContVacios>' . $empty . '</titulosTransContVacios>' : '')
            . '</argRegistrarTitEnviosParam></RegistrarTitEnvios></soap:Body></soap:Envelope>';
    }

    private function registration(?string $requestXml, array $overrides = []): WebserviceTransaction
    {
        return WebserviceTransaction::create(array_merge([
            'company_id' => 1, 'user_id' => 1, 'voyage_id' => 7, 'shipment_id' => 70,
            'transaction_id' => 'TIT' . uniqid(), 'webservice_type' => 'micdta',
            'country' => 'AR', 'environment' => 'testing',
            'webservice_url' => 'https://example.invalid/never-called',
            'soap_action' => self::NS . '/RegistrarTitEnvios', 'status' => 'success',
            'request_xml' => $requestXml,
            'external_reference' => 'QA-BL-A',
            'success_data' => ['id_tit_trans' => 'QA-BL-A', 'tracks_count' => 1],
        ], $overrides));
    }

    private function annul(string $title, bool $afipRejects = false): array
    {
        // The AnularTitulo transaction_id embeds time(): one annulment per second per voyage.
        while ($this->lastAnnulSecond !== null && time() <= $this->lastAnnulSecond) {
            usleep(50000);
        }
        $this->lastAnnulSecond = time();

        $detalle = $afipRejects
            ? '<DetalleError><Codigo>27100</Codigo><Descripcion>Titulo afectado</Descripcion><TipoMensaje>Error</TipoMensaje></DetalleError>'
            : '<DetalleError><Codigo>0</Codigo><Descripcion>Ejecucion exitosa.</Descripcion><TipoMensaje>Informativo</TipoMensaje></DetalleError>';
        $response = '<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"><soap:Body>'
            . '<AnularTituloResponse xmlns="' . self::NS . '"><AnularTituloResult>'
            . '<ListaErrores>' . $detalle . '</ListaErrores>'
            . '</AnularTituloResult></AnularTituloResponse></soap:Body></soap:Envelope>';

        $soap = Mockery::mock(\SoapClient::class); // No constructor / WSDL fetch.
        $soap->shouldReceive('__doRequest')->once()
            ->with(Mockery::on(fn ($xml) => str_contains($xml, '<argIdTitTrans>' . $title . '</argIdTitTrans>')),
                Mockery::type('string'), self::NS . '/AnularTitulo', SOAP_1_1, false)
            ->andReturn($response);
        $this->service->contractSoap = $soap;

        return (new ReflectionMethod(ArgentinaMicDtaService::class, 'processAnularTitulo'))
            ->invoke($this->service, $this->voyage, [
                'titulo_id' => $title,
                'motivo_anulacion' => 'Prueba de contrato',
            ]);
    }

    private function statusOf(WebserviceTransaction $transaction): string
    {
        return $transaction->fresh()->status;
    }

    public function test_registration_is_cancelled_only_after_every_title_is_annulled(): void
    {
        $xml = $this->registrationXml(['QA-BL-A', 'QA-BL-B']);
        $registration = $this->registration($xml);

        $result = $this->annul('QA-BL-A');
        $this->assertTrue($result['success'], json_encode($result));
        $this->assertSame([
            'success' => true,
            'method' => 'AnularTitulo',
            'message' => 'Título QA-BL-A anulado exitosamente en AFIP',
            'titulo_anulado' => 'QA-BL-A',
        ], array_intersect_key($result, array_flip(['success', 'method', 'message', 'titulo_anulado'])));
        $this->assertSame('success', $this->statusOf($registration));

        $annulment = WebserviceTransaction::where('soap_action', self::NS . '/AnularTitulo')->sole();
        $this->assertSame('success', $annulment->status);
        $this->assertSame('QA-BL-A', $annulment->success_data['titulo_anulado']);

        $this->assertTrue($this->annul('QA-BL-B')['success']);
        $fresh = $registration->fresh();
        $this->assertSame('cancelled', $fresh->status);
        $this->assertSame('Anulado en AFIP: Prueba de contrato', $fresh->error_message);
        $this->assertSame($xml, $fresh->request_xml);
        $this->assertSame('QA-BL-A', $fresh->external_reference);
        $this->assertSame(['id_tit_trans' => 'QA-BL-A', 'tracks_count' => 1], $fresh->success_data);
    }

    public function test_sent_registration_keeps_sent_while_a_title_is_pending(): void
    {
        $registration = $this->registration($this->registrationXml(['QA-BL-A', 'QA-BL-B']), ['status' => 'sent']);

        $this->annul('QA-BL-B');

        $this->assertSame('sent', $this->statusOf($registration));
    }

    public function test_rejected_annulment_does_not_count(): void
    {
        $registration = $this->registration($this->registrationXml(['QA-BL-A', 'QA-BL-B']));

        $this->annul('QA-BL-A');
        $rejected = $this->annul('QA-BL-B', true);

        $this->assertFalse($rejected['success']);
        $this->assertSame('success', $this->statusOf($registration));
    }

    public function test_empty_container_title_must_also_be_annulled(): void
    {
        $registration = $this->registration($this->registrationXml(['QA-BL-A'], ['QA-VACIOS']));

        $this->annul('QA-BL-A');
        $this->assertSame('success', $this->statusOf($registration));

        $this->annul('QA-VACIOS');
        $this->assertSame('cancelled', $this->statusOf($registration));
    }

    public function test_registration_without_readable_xml_keeps_status(): void
    {
        $missing = $this->registration(null);
        $invalid = $this->registration('<no-es-xml');

        $this->annul('QA-BL-A');

        $this->assertSame('success', $this->statusOf($missing));
        $this->assertSame('success', $this->statusOf($invalid));
    }

    public function test_other_company_or_environment_is_not_affected(): void
    {
        $xml = $this->registrationXml(['QA-BL-A']);
        $otherCompany = $this->registration($xml, ['company_id' => 2]);
        $otherEnvironment = $this->registration($xml, ['environment' => 'production']);
        $same = $this->registration($xml);

        $this->annul('QA-BL-A');

        $this->assertSame('success', $this->statusOf($otherCompany));
        $this->assertSame('success', $this->statusOf($otherEnvironment));
        $this->assertSame('cancelled', $this->statusOf($same));
    }

    public function test_later_registration_is_not_cancelled_by_earlier_annulment(): void
    {
        $this->annul('QA-BL-A');
        $later = $this->registration($this->registrationXml(['QA-BL-A', 'QA-BL-B']));

        $this->annul('QA-BL-B');

        $this->assertSame('success', $this->statusOf($later));
    }
}
