<?php

namespace Tests\Unit\Services\Webservice;

use App\Http\Controllers\Company\Simple\SimpleManifestController;
use App\Models\Company;
use App\Models\Shipment;
use App\Models\User;
use App\Models\Voyage;
use App\Models\WebserviceTransaction;
use App\Services\Simple\ArgentinaMicDtaService;
use App\Services\Simple\SimpleXmlGenerator;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use ReflectionMethod;
use ReflectionProperty;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class MicDtaErrorPresentationTest extends TestCase
{
    private const CAUSE = 'Certificado no emitido por AC de confianza';

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:',
            'logging.default' => 'null', 'audit.enabled' => false]);
        DB::purge('sqlite');
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        Schema::create('webservice_transactions', function (Blueprint $table) {
            $table->id();
            foreach (['retry_count', 'max_retries', 'container_count', 'bill_of_lading_count'] as $name) {
                $table->integer($name)->default(0);
            }
            $table->boolean('requires_manual_review')->default(false);
            $table->string('currency_code')->nullable();
            foreach (['company_id', 'user_id', 'shipment_id', 'voyage_id'] as $name) {
                $table->unsignedBigInteger($name)->nullable();
            }
            foreach (['transaction_id', 'webservice_type', 'country', 'soap_action', 'status',
                'environment', 'webservice_url', 'method_name', 'error_code'] as $name) {
                $table->string($name)->nullable();
            }
            foreach (['request_xml', 'response_xml', 'error_message', 'error_details'] as $name) {
                $table->text($name)->nullable();
            }
            $table->boolean('is_blocking_error')->default(false);
            $table->timestamp('response_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    private function company(): Company
    {
        return (new Company())->forceFill(['id' => 1, 'ws_environment' => 'testing']);
    }

    private function send(bool $preSoap): array
    {
        $service = new ArgentinaMicDtaService($this->company(), (new User())->forceFill(['id' => 2]));
        $xml = Mockery::mock(SimpleXmlGenerator::class);
        $expectation = $xml->shouldReceive('createRegistrarTitEnviosXml')->once();
        if ($preSoap) {
            $expectation->andThrow(new \Exception(self::CAUSE));
        } else {
            $expectation->andReturn('<RegistrarTitEnvios/>');
        }
        (new ReflectionProperty($service, 'xmlSerializer'))->setValue($service, $xml);
        $soap = Mockery::mock();
        if ($preSoap) {
            $soap->shouldNotReceive('__doRequest');
        } else {
            $soap->shouldReceive('__doRequest')->once()->andReturn(
                '<RegistrarTitEnviosResponse xmlns="Ar.Gob.Afip.Dga.wgesregsintia2"><ListaErrores><DetalleError><Codigo>123</Codigo>'
                . '<Descripcion>Rechazo de prueba</Descripcion></DetalleError></ListaErrores></RegistrarTitEnviosResponse>'
            );
        }
        $shipment = (new Shipment())->forceFill(['id' => 381, 'voyage_id' => 369]);
        return (new ReflectionMethod($service, 'sendTitEnvios'))->invoke($service, $soap, $shipment);
    }

    private function modal(array $payload): string
    {
        $source = file_get_contents(resource_path('views/company/simple/micdta/methods-dashboard.blade.php'));
        $start = strpos($source, '    function esc(str)');
        $end = strpos($source, '        // 2) ÉXITO', $start);
        $js = substr($source, $start, $end - $start) . "\n}";
        $harness = <<<'JS'
const elements = {};
global.document = {getElementById: id => elements[id] ??= {classList: {remove() {}}}};
JS;
        $harness .= "\n" . $js . "\nshowResultModal('RegistrarTitEnvios', "
            . json_encode($payload, JSON_THROW_ON_ERROR) . ", false);\n"
            . "process.stdout.write(elements.resultMessage.innerHTML);";
        $process = new Process(['node', '-e', $harness]);
        $process->mustRun();
        return $process->getOutput();
    }

    public function test_wsaa_failure_preserves_cause_and_leaves_method_xml_absent(): void
    {
        $result = $this->send(true);
        $this->assertFalse($result['success']);
        $this->assertSame(self::CAUSE, $result['error_message']);
        $this->assertSame('TITENVIOS_ERROR', $result['error_code']);
        $transaction = WebserviceTransaction::findOrFail($result['transaction_record_id']);
        $this->assertSame(self::CAUSE, $transaction->error_message);
        $this->assertNull($transaction->request_xml);
        $this->assertNull($transaction->response_xml);
        $this->assertSame('error', $transaction->status);
    }

    public function test_details_take_priority_with_context_code_and_transaction_escaped(): void
    {
        $html = $this->modal(['success' => false, 'error' => 'Error ejecutando RegistrarTitEnvios',
            'details' => self::CAUSE . ' <script>alert("x")</script>',
            'error_code' => '<TITENVIOS_ERROR>', 'transaction_record_id' => 483]);
        $this->assertLessThan(strpos($html, 'Contexto:'), strpos($html, self::CAUSE));
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;TITENVIOS_ERROR&gt;', $html);
        $this->assertStringContainsString('Transacción:</strong> 483', $html);
    }

    public function test_error_fallback_and_nested_payload_keep_existing_information(): void
    {
        $html = $this->modal(['error' => 'Error genérico', 'code' => 'ERROR']);
        $this->assertStringContainsString('Error genérico', $html);
        $this->assertStringContainsString('Código:</strong> ERROR', $html);
        $this->assertStringNotContainsString('Contexto:', $html);
        $html = $this->modal(['data' => ['details' => self::CAUSE, 'error' => 'Contexto',
            'error_code' => 'TITENVIOS_ERROR', 'transaction_id' => 'TIT_381']]);
        $this->assertStringContainsString(self::CAUSE, $html);
        $this->assertStringContainsString('TIT_381', $html);
        $this->assertStringContainsString('TITENVIOS_ERROR', $html);
    }

    public function test_soap_rejection_keeps_actual_xml_and_specific_error(): void
    {
        $result = $this->send(false);
        $transaction = WebserviceTransaction::findOrFail($result['transaction_record_id']);
        $this->assertSame('<RegistrarTitEnvios/>', $transaction->request_xml);
        $this->assertStringContainsString('RegistrarTitEnviosResponse', $transaction->response_xml);
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Rechazo de prueba', $transaction->error_message);
        $this->assertStringContainsString('Rechazo de prueba', $this->modal($result));
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_controller_propagates_specific_cause_code_and_record_to_actual_modal(): void
    {
        $service = Mockery::mock('overload:' . ArgentinaMicDtaService::class);
        $service->shouldReceive('canProcessVoyage')->once()->andReturn(['can_process' => true]);
        $service->shouldReceive('executeMethod')->once()->andReturn([
            'success' => false, 'error_message' => self::CAUSE,
            'error_code' => 'TITENVIOS_ERROR', 'transaction_record_id' => 483,
        ]);
        Auth::shouldReceive('user')->andReturn(new User());
        $controller = new class($this->company()) extends SimpleManifestController {
            public function __construct(private Company $testCompany) {}
            protected function getUserCompany(): ?Company { return $this->testCompany; }
        };
        $voyage = (new Voyage())->forceFill(['id' => 369, 'company_id' => 1]);
        $response = $controller->registrarTitEnvios(Request::create('/', 'POST'), $voyage);
        $this->assertSame(400, $response->getStatusCode());
        $payload = $response->getData(true);
        $this->assertSame(self::CAUSE, $payload['details']);
        $this->assertSame('TITENVIOS_ERROR', $payload['error_code']);
        $this->assertSame(483, $payload['transaction_record_id']);
        $this->assertStringContainsString(self::CAUSE, $this->modal($payload));
        $this->assertArrayNotHasKey('request_xml', $payload);
        $this->assertArrayNotHasKey('response_xml', $payload);
    }
}
