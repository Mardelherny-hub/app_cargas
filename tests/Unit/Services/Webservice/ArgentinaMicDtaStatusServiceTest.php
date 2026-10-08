<?php

namespace Tests\Unit\Services\Webservice;

use App\Models\Company;
use App\Models\User;
use App\Models\Voyage;
use App\Models\WebserviceTransaction;
use App\Services\Simple\ArgentinaMicDtaStatusService;
use App\Services\Simple\BaseWebserviceService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use ReflectionMethod;
use ReflectionProperty;
use SoapClient;
use Tests\TestCase;

class ArgentinaMicDtaStatusServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:',
            'logging.default' => 'null', 'audit.enabled' => false]);
        DB::purge('sqlite');
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        Schema::create('webservice_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->string('webservice_type');
            $table->string('status');
            $table->string('external_reference')->nullable();
            $table->timestamp('sent_at')->nullable();
        });
    }

    private function service(): ArgentinaMicDtaStatusService
    {
        $company = (new Company())->forceFill(['id' => 1, 'ws_environment' => 'testing']);
        return new ArgentinaMicDtaStatusService($company, new User());
    }

    public function test_real_class_loads_and_inherits_native_soap_property(): void
    {
        $service = $this->service();
        $property = new ReflectionProperty($service, 'soapClient');
        $this->assertSame(BaseWebserviceService::class, $property->getDeclaringClass()->getName());
        $this->assertNull($property->getValue($service));
        // Cliente sin WSDL: su construcción no hace ninguna conexión.
        $native = new SoapClient(null, ['location' => 'http://unused.invalid', 'uri' => 'urn:test']);
        $property->setValue($service, $native);
        $this->assertSame($native, $property->getValue($service));
    }

    public function test_public_individual_and_mass_queries_without_pending_transactions_do_not_send(): void
    {
        $service = $this->service();
        $native = Mockery::mock(SoapClient::class);
        $native->shouldNotReceive('__doRequest');
        (new ReflectionProperty($service, 'soapClient'))->setValue($service, $native);
        foreach ([[], [123]] as $ids) {
            $result = $service->consultarEstadoTransacciones($ids);
            $this->assertFalse($result['success']);
            $this->assertSame(0, $result['consultas_realizadas']);
        }
    }

    public function test_voyage_send_adapter_rejects_without_creating_a_new_soap_operation(): void
    {
        $result = (new ReflectionMethod(ArgentinaMicDtaStatusService::class, 'sendSpecificWebservice'))
            ->invoke($this->service(), new Voyage(), []);
        $this->assertFalse($result['success']);
        $this->assertSame('STATUS_REQUIRES_TRANSACTION_QUERY', $result['error_code']);
    }

    public function test_existing_soap_operation_uses_simple_native_transport(): void
    {
        $service = $this->service();
        $transaction = Mockery::mock(WebserviceTransaction::class)->makePartial();
        $transaction->shouldReceive('update')->times(3)->andReturn(true);
        $native = Mockery::mock(SoapClient::class);
        $native->shouldReceive('__doRequest')->once()
            ->with('<consulta/>', 'https://wsaduhomoext.afip.gob.ar/DIAV2/wgesregsintia2/wgesregsintia2.asmx',
                'Ar.Gob.Afip.Dga.wgesregsintia2/ConsultarEstadoMicDta', SOAP_1_2, false)
            ->andReturn('<respuesta/>');
        $result = (new ReflectionMethod($service, 'enviarConsultaSoap'))
            ->invoke($service, $transaction, $native, '<consulta/>');
        $this->assertTrue($result['success']);
        $this->assertSame('<respuesta/>', $result['response_data']);
    }
}
