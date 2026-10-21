<?php

namespace Tests\Unit\Services\Webservice;

use App\Models\Company;
use App\Models\DeconsolidationOperation;
use App\Models\User;
use App\Models\WebserviceTransaction;
use App\Services\Simple\ArgentinaDeconsolidatedService;
use App\Services\Webservice\Argentina\SimpleXmlGeneratorDesconsolidado;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ArgentinaDeconsolidatedIndependentFlowTest extends TestCase
{
    private string $previous;

    protected function setUp(): void
    {
        parent::setUp();
        $this->previous = config('database.default');
        config(['database.connections.ata_desc_flow_test' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
            'foreign_key_constraints' => false,
        ], 'database.default' => 'ata_desc_flow_test']);
        $schema = Schema::connection('ata_desc_flow_test');
        $schema->create('deconsolidation_operations', function ($table) {
            $table->increments('id'); $table->unsignedInteger('company_id');
        });
        $schema->create('bills_of_lading', function ($table) {
            $table->increments('id'); $table->unsignedInteger('deconsolidation_operation_id');
            $table->unsignedInteger('shipment_id')->nullable(); $table->string('bill_number');
            $table->string('argentina_status')->nullable(); $table->string('webservice_status')->nullable();
            // Igual que la tabla real: NO existe webservice_response_at.
            $table->timestamp('argentina_sent_at')->nullable();
            $table->timestamps(); $table->softDeletes();
        });
        $schema->create('shipment_items', function ($table) {
            $table->increments('id'); $table->unsignedInteger('bill_of_lading_id');
            $table->timestamps(); $table->softDeletes();
        });
        $schema->create('webservice_transactions', function ($table) {
            $table->increments('id'); $table->unsignedInteger('company_id');
            $table->unsignedInteger('user_id'); $table->unsignedInteger('voyage_id')->nullable();
            $table->string('transaction_id'); $table->string('webservice_type'); $table->string('country');
            $table->string('webservice_url'); $table->string('soap_action'); $table->string('status');
            $table->string('environment'); $table->string('certificate_used')->nullable();
            $table->integer('bill_of_lading_count'); $table->integer('container_count');
            $table->integer('retry_count')->default(0); $table->integer('max_retries')->default(3);
            $table->boolean('is_blocking_error')->default(false);
            $table->boolean('requires_manual_review')->default(false);
            $table->string('currency_code')->default('USD');
            $table->text('additional_metadata'); $table->text('request_xml')->nullable();
            $table->text('response_xml')->nullable(); $table->timestamp('sent_at')->nullable();
            $table->timestamp('response_at')->nullable(); $table->string('external_reference')->nullable();
            $table->string('confirmation_number')->nullable(); $table->string('error_code')->nullable();
            $table->text('error_message')->nullable();
            $table->text('error_details')->nullable(); $table->timestamps();
        });
        DB::table('deconsolidation_operations')->insert(['id' => 11, 'company_id' => 101]);
        DB::table('bills_of_lading')->insert(['id' => 44, 'deconsolidation_operation_id' => 11,
            'bill_number' => 'HOUSE44', 'shipment_id' => null]);
    }

    protected function tearDown(): void
    {
        DB::purge('ata_desc_flow_test');
        config(['database.default' => $this->previous]);
        parent::tearDown();
    }

    private function service(string $response): ArgentinaDeconsolidatedService
    {
        $company = new Company(); $company->id = 101; $company->ws_environment = 'testing';
        $company->certificate_path = 'test-certificate-not-used';
        $user = new User(); $user->id = 205;
        $generator = Mockery::mock(SimpleXmlGeneratorDesconsolidado::class);
        $generator->shouldReceive('validateLocalData')->andReturn(['is_valid' => true, 'errors' => []]);
        foreach (['generateRegistrar', 'generateRectificar', 'generateEliminar'] as $method) {
            $generator->shouldReceive($method)->andReturn('<SOAP>FAKE</SOAP>');
        }
        return new class($company, $user, $generator, $response) extends ArgentinaDeconsolidatedService {
            public function __construct($company, $user, private $generator, private string $response)
            { parent::__construct($company, $user); }
            protected function independentXmlGenerator(DeconsolidationOperation $operation): SimpleXmlGeneratorDesconsolidado
            { return $this->generator; }
            protected function sendSoapRequest(WebserviceTransaction $transaction, string $xml, string $soapMethod, string $methodType): array
            {
                if ($this->response === 'uncertain') {
                    return ['success' => false, 'identifier' => null, 'details' => [], 'error_message' => 'Sin respuesta'];
                }
                $transaction->update(['response_xml' => '<SOAP>MOCK RESPONSE</SOAP>']);
                return ['success' => true, 'identifier' => 'VIAJE-TEST', 'details' => [], 'error_message' => null];
            }
        };
    }

    #[Test]
    public function register_rectify_and_delete_follow_real_transaction_history_with_mock_soap(): void
    {
        $operation = new DeconsolidationOperation(); $operation->id = 11; $operation->company_id = 101;
        $service = $this->service('ok');
        foreach (['registrar', 'rectificar', 'eliminar'] as $method) {
            $result = $service->ejecutarExpediente($operation, $method, [44]);
            $this->assertTrue($result['success']);
            $this->assertSame($method, data_get(WebserviceTransaction::find($result['transaction_id'])->additional_metadata, 'method'));
        }
        $this->assertSame(3, WebserviceTransaction::count());
        $this->assertSame('deleted', DB::table('bills_of_lading')->where('id', 44)->value('argentina_status'));
    }

    #[Test]
    public function rejected_transaction_needing_manual_review_blocks_retransmission(): void
    {
        $operation = new DeconsolidationOperation();
        $operation->id = 11;
        $operation->company_id = 101;

        WebserviceTransaction::create([
            'company_id' => 101,
            'user_id' => 205,
            'voyage_id' => null,
            'transaction_id' => 'DEC12345678901234567',
            'webservice_type' => 'desconsolidado',
            'country' => 'AR',
            'webservice_url' => 'test-endpoint',
            'soap_action' => 'RegistrarTitulosDesconsolidador',
            'status' => 'error',
            'environment' => 'testing',
            'certificate_used' => 'test-certificate-not-used',
            'bill_of_lading_count' => 1,
            'container_count' => 0,
            'error_code' => '42076',
            'requires_manual_review' => true,
            'additional_metadata' => [
                'deconsolidation_operation_id' => 11,
                'bill_ids' => [44],
                'method' => 'registrar',
            ],
        ]);

        try {
            $this->service('ok')->ejecutarExpediente($operation, 'registrar', [44]);
            $this->fail('No se debe reenviar un rechazo pendiente de revisión.');
        } catch (\Exception $exception) {
            $this->assertStringContainsString(
                'rechazo aduanero pendiente de revisión',
                $exception->getMessage()
            );
        }

        $this->assertSame(1, WebserviceTransaction::count());
    }

    #[Test]
    public function missing_response_blocks_retry(): void
    {
        $operation = new DeconsolidationOperation(); $operation->id = 11; $operation->company_id = 101;
        $service = $this->service('uncertain');
        $result = $service->ejecutarExpediente($operation, 'registrar', [44]);
        $this->assertFalse($result['success']);
        $this->assertSame('sending', WebserviceTransaction::find($result['transaction_id'])->status);
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('transmisión pendiente');
        $service->ejecutarExpediente($operation, 'registrar', [44]);
    }
}
