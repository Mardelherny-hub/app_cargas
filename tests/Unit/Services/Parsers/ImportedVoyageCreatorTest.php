<?php

namespace Tests\Unit\Services\Parsers;

use App\Models\Port;
use App\Models\Shipment;
use App\Models\User;
use App\Models\Vessel;
use App\Models\Voyage;
use App\Services\Parsers\NavsurTextParser;
use App\Services\Parsers\NavsurTextParserCompat;
use App\Services\Parsers\TfpTextParser;
use App\Services\Parsers\TfpTextParserCompat;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use Tests\TestCase;

/**
 * El viaje y la carga creados por TFP y Navsur registran al usuario que
 * importó el archivo, como en el resto de los formatos. Base SQLite en memoria con
 * datos ficticios; puertos y tipo de carga se resuelven fuera del alcance.
 */
class ImportedVoyageCreatorTest extends TestCase
{
    private const COMPANY = 1;
    private const USER = 7;
    private const VESSEL_NAME = 'BARCAZA DEMO';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'logging.default' => 'null',
            'audit.enabled' => false,
            'audit.drivers.database.connection' => null,
        ]);
        DB::purge('sqlite');
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());

        foreach ([Voyage::class, Vessel::class, Shipment::class] as $model) {
            $instance = new $model();
            Schema::create($instance->getTable(), function (Blueprint $t) use ($instance) {
                $t->id();
                foreach (array_unique($instance->getFillable()) as $column) {
                    str_ends_with($column, '_id')
                        ? $t->unsignedBigInteger($column)->nullable()
                        : $t->text($column)->nullable();
                }
                $t->timestamps();
                $t->softDeletes();
            });
        }
        Schema::create('users', function (Blueprint $t) {
            $t->id();
            foreach (['name', 'email', 'password', 'userable_type'] as $column) {
                $t->text($column)->nullable();
            }
            $t->unsignedBigInteger('userable_id')->nullable();
            $t->timestamps();
        });

        if (!Schema::hasColumn('vessels', 'operational_status')) {
            Schema::table('vessels', fn (Blueprint $t) => $t->text('operational_status')->nullable());
        }

        DB::table('users')->insert([
            'id' => self::USER, 'name' => 'Usuario de prueba', 'email' => 'usuario@example.test',
            'password' => 'x', 'userable_type' => 'App\Models\Company', 'userable_id' => self::COMPANY,
        ]);
        DB::table('vessels')->insert([
            'id' => 1, 'company_id' => self::COMPANY, 'name' => self::VESSEL_NAME,
            'cargo_capacity_tons' => 1000, 'container_capacity' => 50, 'active' => 1,
            'operational_status' => 'active',
        ]);

        Auth::login(User::findOrFail(self::USER));
    }

    private function createVoyage(object $parser, array $args): Voyage
    {
        $method = new ReflectionMethod($parser, 'findOrCreateVoyage');
        $method->setAccessible(true);

        return $method->invokeArgs($parser, $args);
    }

    public function test_tfp_records_the_importing_user_as_voyage_creator(): void
    {
        $parser = new class extends TfpTextParser {
            protected function findOrCreatePort(string $code): Port
            {
                return ImportedVoyageCreatorTest::fakePort($code);
            }

            protected function resolveTfpVoyageCargoType(int $companyId, Port $origin, Port $destination): string
            {
                return 'export';
            }
        };

        $voyage = $this->createVoyage($parser, [
            ['voyage_number' => 'TFP-0000000000000001', 'vessel_name' => self::VESSEL_NAME, 'pol' => 'ARBUE', 'pod' => 'PYASU'],
            ['vessel_id' => 1],
        ]);

        $this->assertSame(self::USER, (int) $voyage->fresh()->created_by_user_id);
    }

    public function test_navsur_records_the_importing_user_as_voyage_creator(): void
    {
        $parser = new class extends NavsurTextParser {
            protected function resolvePortStrict(?string $code): Port
            {
                return ImportedVoyageCreatorTest::fakePort((string) $code);
            }

            protected function resolveVoyageCargoTypeForCompany(int $companyId, Port $originPort, Port $destinationPort): string
            {
                return 'export';
            }
        };

        $voyage = $this->createVoyage($parser, [
            ['voyage_number' => 'NAV-0001', 'vessel_name' => self::VESSEL_NAME, 'pol' => 'ARBUE', 'pod' => 'PYASU', 'flag' => null],
        ]);

        $this->assertSame(self::USER, (int) $voyage->fresh()->created_by_user_id);
    }

    public function test_tfp_compat_records_the_importing_user_as_voyage_creator(): void
    {
        $parser = new class extends TfpTextParserCompat {
            protected function findOrCreatePort(string $code): Port
            {
                return ImportedVoyageCreatorTest::fakePort($code);
            }

            protected function resolveTfpVoyageCargoType(int $companyId, Port $origin, Port $destination): string
            {
                return 'export';
            }
        };

        $voyage = $this->createVoyage($parser, [
            ['voyage_number' => 'TFP-0000000000000002', 'vessel_name' => self::VESSEL_NAME, 'pol' => 'ARBUE', 'pod' => 'PYASU'],
            ['vessel_id' => 1, 'voyage_number' => 'V-REAL-01'],
        ]);

        $this->assertSame('V-REAL-01', $voyage->voyage_number);
        $this->assertSame(self::USER, (int) $voyage->fresh()->created_by_user_id);
    }

    public function test_navsur_compat_records_the_importing_user_as_voyage_creator(): void
    {
        $parser = new class extends NavsurTextParserCompat {
            public function useOptions(array $options): static
            {
                $this->importOptions = $options;

                return $this;
            }

            protected function resolvePortStrict(?string $code): Port
            {
                return ImportedVoyageCreatorTest::fakePort((string) $code);
            }

            protected function resolveVoyageCargoTypeForCompany(int $companyId, Port $originPort, Port $destinationPort): string
            {
                return 'export';
            }
        };

        $voyage = $this->createVoyage($parser->useOptions(['vessel_id' => 1]), [
            ['voyage_number' => 'NAV-0002', 'vessel_name' => self::VESSEL_NAME, 'pol' => 'ARBUE', 'pod' => 'PYASU', 'flag' => null],
        ]);

        $this->assertSame(1, (int) $voyage->lead_vessel_id);
        $this->assertSame(self::USER, (int) $voyage->fresh()->created_by_user_id);
    }

    private function createShipment(object $parser): Shipment
    {
        $voyage = Voyage::create([
            'voyage_number' => 'V-CARGA-01', 'company_id' => self::COMPANY, 'lead_vessel_id' => 1, 'status' => 'planning',
        ]);
        $method = new ReflectionMethod($parser, 'findOrCreateShipment');
        $method->setAccessible(true);

        return $method->invokeArgs($parser, [$voyage, []]);
    }

    public function test_tfp_compat_records_the_importing_user_as_shipment_creator(): void
    {
        $shipment = $this->createShipment(new TfpTextParserCompat());

        $this->assertSame(1, (int) $shipment->vessel_id);
        $this->assertSame(self::USER, (int) $shipment->fresh()->created_by_user_id);
    }

    public function test_navsur_compat_records_the_importing_user_as_shipment_creator(): void
    {
        $shipment = $this->createShipment(new NavsurTextParserCompat());

        $this->assertSame(1, (int) $shipment->vessel_id);
        $this->assertSame(self::USER, (int) $shipment->fresh()->created_by_user_id);
    }

    public static function fakePort(string $code): Port
    {
        $id = $code === 'ARBUE' ? 1 : 2;

        return (new Port())->forceFill(['id' => $id, 'code' => $code, 'country_id' => $id]);
    }
}
