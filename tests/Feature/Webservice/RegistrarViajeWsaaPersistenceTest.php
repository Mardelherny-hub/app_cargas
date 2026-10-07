<?php

namespace Tests\Feature\Webservice;

use App\Models\Company;
use App\Models\Country;
use App\Models\Port;
use App\Models\User;
use App\Models\Vessel;
use App\Models\VesselOwner;
use App\Models\Voyage;
use App\Models\WsaaToken;
use App\Services\Simple\ArgentinaAnticipatedService;
use App\Services\Simple\SimpleXmlGenerator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use ReflectionMethod;
use RuntimeException;
use Tests\TestCase;

class RegistrarViajeWsaaPersistenceTest extends TestCase
{
    private object $wsaa;

    protected function setUp(): void
    {
        parent::setUp();
        // Sin RefreshDatabase: únicamente SQLite en memoria, sin datos externos.
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:',
            'cache.default' => 'array', 'logging.default' => 'null', 'audit.enabled' => false]);
        DB::purge('sqlite');
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        Schema::create('companies', fn (Blueprint $t) => $t->id());
        DB::table('companies')->insert([['id' => 1], ['id' => 2]]);
        foreach (glob(database_path('migrations/*wsaa*')) as $migration) {
            (require $migration)->up();
        }
        Schema::create('shipments', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('voyage_id');
        });
        Schema::create('bills_of_lading', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('shipment_id'); $t->softDeletes();
        });
        Schema::create('webservice_transactions', function (Blueprint $t) {
            $t->id();
            foreach (['company_id', 'user_id', 'voyage_id', 'retry_count', 'max_retries',
                'container_count', 'bill_of_lading_count'] as $field) {
                $t->unsignedBigInteger($field);
            }
            foreach (['transaction_id', 'webservice_type', 'country', 'webservice_url',
                'soap_action', 'status', 'environment', 'currency_code'] as $field) {
                $t->string($field);
            }
            $t->boolean('is_blocking_error')->default(false);
            $t->boolean('requires_manual_review')->default(false);
            $t->text('additional_metadata'); $t->timestamps();
        });
        $this->wsaa = new class {
            public int $loginCmsCalls = 0;
            public array $transactionLevels = [];
            public array $businessObservations = [];
            public bool $fail = false;

            public function loginCms(): array
            {
                $this->loginCmsCalls++;
                $this->transactionLevels[] = DB::transactionLevel();
                if ($this->fail) {
                    throw new RuntimeException('WSAA simulado no disponible');
                }
                return ['token' => 'fake-token-'.$this->loginCmsCalls, 'sign' => 'fake-sign'];
            }
        };
    }

    private function company(int $id = 1): Company
    {
        return (new Company())->forceFill(['id' => $id, 'tax_id' => '30123456789',
            'country' => 'AR', 'ws_environment' => 'testing']);
    }

    private function generator(int $company = 1, string $environment = 'testing'): SimpleXmlGenerator
    {
        $issuer = $this->company($company);
        $issuer->ws_environment = $environment;
        return new class($issuer, [], $this->wsaa) extends SimpleXmlGenerator {
            public function __construct(Company $company, array $config, private object $wsaa)
            {
                parent::__construct($company, $config);
            }

            protected function requestWsaaTokens(string $serviceName): array
            {
                return $this->wsaa->loginCms();
            }

            public function createRegistrarViajeXml(Voyage $voyage, string $transactionId, array $voyageData = []): string
            {
                // Fallo posterior a autenticar, en la operación real del servicio.
                $token = (new ReflectionMethod(SimpleXmlGenerator::class, 'getWSAATokens'))
                    ->invoke($this, 'wgesinformacionanticipada');
                $this->wsaa->businessObservations[] = [
                    'level' => DB::transactionLevel(),
                    'transactions' => DB::table('webservice_transactions')->count(),
                    'token' => $token['token'],
                ];
                throw new RuntimeException('Validación posterior del viaje simulada');
            }
        };
    }

    private function tokens(SimpleXmlGenerator $generator, string $service = 'wgesinformacionanticipada'): array
    {
        return (new ReflectionMethod(SimpleXmlGenerator::class, 'getWSAATokens'))
            ->invoke($generator, $service);
    }

    private function voyage(): Voyage
    {
        $country = (new Country())->forceFill(['codigo_afip' => '200', 'alpha2_code' => 'AR']);
        $owner = (new VesselOwner())->forceFill(['legal_name' => 'Transportista QA'])
            ->setRelation('country', $country);
        $vessel = (new Vessel())->forceFill(['name' => 'Buque QA'])
            ->setRelation('flagCountry', $country)->setRelation('owner', $owner);
        $port = (new Port())->forceFill(['code' => 'ARBUE', 'afip_code' => '033'])
            ->setRelation('country', $country)->setRelation('primaryCustomsOffice', null);
        $voyage = Mockery::mock(Voyage::class)->makePartial();
        $voyage->forceFill(['id' => 1, 'company_id' => 1, 'voyage_number' => 'QA-WSAA',
            'lead_vessel_id' => 1, 'origin_port_id' => 1, 'destination_port_id' => 1,
            'estimated_arrival_date' => now(), 'is_empty_transport' => 'S', 'has_cargo_onboard' => 'N']);
        $voyage->setRelation('leadVessel', $vessel)->setRelation('originPort', $port)
            ->setRelation('destinationPort', $port)->setRelation('originCustoms', null)
            ->setRelation('destinationCustoms', null)->setRelation('captain', null)
            ->setRelation('shipments', new Collection());
        $voyage->shouldReceive('loadMissing')->andReturnSelf();
        $voyage->shouldReceive('load')->andReturnSelf();
        return $voyage;
    }

    private function service(): ArgentinaAnticipatedService
    {
        // Cada intento usa una instancia nueva: la reutilización depende de WsaaToken.
        $this->app->bind(SimpleXmlGenerator::class, fn () => $this->generator());
        return new ArgentinaAnticipatedService($this->company(), (new User())->forceFill(['id' => 1]));
    }

    public function test_ta_survives_business_rollback_and_second_attempt_does_not_login_again(): void
    {
        $service = $this->service();
        $voyage = $this->voyage();
        $this->assertSame(0, WsaaToken::count());
        for ($attempt = 1; $attempt <= 2; $attempt++) {
            $result = $service->registrarViaje($voyage);
            $this->assertFalse($result['success']);
            $this->assertSame('Validación posterior del viaje simulada', $result['error_message']);
            $this->assertSame(0, DB::transactionLevel());
            $this->assertSame(0, DB::table('webservice_transactions')->count());
            $this->assertSame(1, WsaaToken::count());
            $this->assertSame('fake-token-1', WsaaToken::sole()->token);
            $this->assertTrue(WsaaToken::sole()->isValid());
            $this->assertSame(1, $this->wsaa->loginCmsCalls);
        }
        $this->assertSame([0], $this->wsaa->transactionLevels);
        $this->assertSame([
            ['level' => 1, 'transactions' => 1, 'token' => 'fake-token-1'],
            ['level' => 1, 'transactions' => 1, 'token' => 'fake-token-1'],
        ], $this->wsaa->businessObservations);
    }

    public function test_existing_valid_ta_is_reused_by_a_new_generator(): void
    {
        $first = $this->tokens($this->generator());
        $this->assertSame($first, $this->tokens($this->generator()));
        $this->assertSame(1, $this->wsaa->loginCmsCalls);
        $this->assertSame(1, WsaaToken::sole()->usage_count);
    }

    public function test_expired_ta_requests_a_new_one(): void
    {
        $this->tokens($this->generator());
        $old = WsaaToken::sole();
        $old->update(['expires_at' => now()->subMinute()]);
        $second = $this->tokens($this->generator());
        $this->assertSame('fake-token-2', $second['token']);
        $this->assertSame(2, $this->wsaa->loginCmsCalls);
        $this->assertSame('expired', $old->fresh()->status);
    }

    public function test_different_company_does_not_reuse_ta(): void
    {
        $first = $this->tokens($this->generator(1));
        $other = $this->tokens($this->generator(2));
        $this->assertNotSame($first['token'], $other['token']);
        $this->assertSame(2, $this->wsaa->loginCmsCalls);
        $this->assertSame([1, 2], WsaaToken::orderBy('company_id')->pluck('company_id')->all());
    }

    public function test_different_service_does_not_reuse_ta(): void
    {
        $first = $this->tokens($this->generator());
        $other = $this->tokens($this->generator(), 'other-qa-service');
        $this->assertNotSame($first['token'], $other['token']);
        $this->assertSame(2, $this->wsaa->loginCmsCalls);
        $this->assertSame(2, WsaaToken::count());
    }

    public function test_different_environment_does_not_reuse_ta(): void
    {
        $first = $this->tokens($this->generator());
        $other = $this->tokens($this->generator(1, 'production'));
        $this->assertNotSame($first['token'], $other['token']);
        $this->assertSame(2, $this->wsaa->loginCmsCalls);
        $this->assertSame(2, WsaaToken::count());
    }

    public function test_ambient_transaction_is_preserved_and_wsaa_is_not_called(): void
    {
        $service = $this->service();
        DB::beginTransaction();
        try {
            $result = $service->registrarViaje($this->voyage());
            $this->assertFalse($result['success']);
            $this->assertStringContainsString('fuera de una transacción', $result['error_message']);
            $this->assertNull($result['transaction_id']);
            $this->assertSame(1, DB::transactionLevel());
            $this->assertSame(0, $this->wsaa->loginCmsCalls);
            $this->assertSame(0, WsaaToken::count());
        } finally {
            DB::rollBack();
        }
    }

    public function test_wsaa_failure_does_not_start_business_transaction(): void
    {
        $this->wsaa->fail = true;
        $result = $this->service()->registrarViaje($this->voyage());
        $this->assertFalse($result['success']);
        $this->assertSame('WSAA simulado no disponible', $result['error_message']);
        $this->assertNull($result['transaction_id']);
        $this->assertSame(0, DB::transactionLevel());
        $this->assertSame(0, DB::table('webservice_transactions')->count());
        $this->assertSame(0, WsaaToken::count());
    }
}
