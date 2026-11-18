<?php

namespace Tests\Unit\Services\Parsers;

use App\Models\Port;
use App\Models\User;
use App\Models\Vessel;
use App\Models\Voyage;
use App\Services\Parsers\NavsurTextParser;
use App\Services\Parsers\TfpTextParser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use Tests\TestCase;

/**
 * El viaje creado por TFP y Navsur registra al usuario que importó el
 * archivo, como en el resto de los formatos. Base SQLite en memoria con
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

        foreach ([Voyage::class, Vessel::class] as $model) {
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

        DB::table('users')->insert([
            'id' => self::USER, 'name' => 'Usuario de prueba', 'email' => 'usuario@example.test',
            'password' => 'x', 'userable_type' => 'App\Models\Company', 'userable_id' => self::COMPANY,
        ]);
        DB::table('vessels')->insert([
            'id' => 1, 'company_id' => self::COMPANY, 'name' => self::VESSEL_NAME,
            'cargo_capacity_tons' => 1000, 'container_capacity' => 50, 'active' => 1,
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

    public static function fakePort(string $code): Port
    {
        $id = $code === 'ARBUE' ? 1 : 2;

        return (new Port())->forceFill(['id' => $id, 'code' => $code, 'country_id' => $id]);
    }
}
