<?php

namespace Tests\Unit\Services\Parsers;

use App\Models\BillOfLading;
use App\Models\Company;
use App\Models\Container;
use App\Models\ShipmentItem;
use App\Models\Voyage;
use App\Services\Parsers\CmspEdiParserCompat;
use App\Services\Parsers\TfpTextParserCompat;
use App\Services\Simple\SimpleXmlGenerator;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use ReflectionMethod;
use ReflectionProperty;
use Tests\TestCase;
use XMLWriter;

class EmptyContainerWeightTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:',
            'logging.default' => 'null', 'audit.enabled' => false]);
        DB::purge('sqlite');
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        Schema::create('containers', function (Blueprint $t) {
            $t->id();
            foreach ((new Container())->getFillable() as $field) {
                if (!in_array($field, ['id', 'created_at', 'updated_at'])) {
                    $t->string($field)->nullable();
                }
            }
            $t->timestamps();
        });
        Schema::create('container_types', function (Blueprint $t) {
            $t->id(); $t->string('code'); $t->string('iso_code'); $t->string('iso_size_type')->nullable();
            $t->boolean('active'); $t->decimal('tare_weight_kg');
            $t->decimal('max_gross_weight_kg');
        });
        DB::table('container_types')->insert(['id' => 1, 'code' => '40HC', 'iso_code' => '45G1',
            'active' => true, 'tare_weight_kg' => 9999, 'max_gross_weight_kg' => 30000]);
    }

    private function tfp(array $data): Container
    {
        return (new ReflectionMethod(TfpTextParserCompat::class, 'createContainer'))
            ->invoke(new TfpTextParserCompat(), new BillOfLading(), $data + [
                'numero' => 'TFP'.Container::count(), 'tipo' => '40HC', 'condicion' => 'V',
            ]);
    }

    private function cmsp($tare, $vgm, bool $empty = true): Container
    {
        $number = Container::count() === 0 ? 'BMOU5955186' : 'CMSP'.Container::count();
        $parser = new CmspEdiParserCompat();
        (new ReflectionProperty($parser, 'parsedData'))->setValue($parser, ['equipment' => [
            $number => ['iso_code' => '45G1', 'tare_weight_kg' => $tare,
                'vgm_weight_kg' => $vgm, 'transport_temperature_c' => null],
        ]]);
        $item = Mockery::mock(ShipmentItem::class)->makePartial();
        $relation = Mockery::mock(BelongsToMany::class);
        $relation->shouldReceive('attach')->once()->withArgs(function ($id, $pivot) use ($empty) {
            // El bruto físico normalizado nunca sustituye el peso de mercadería/pivot.
            $this->assertEquals($empty ? 0 : 100, $pivot['gross_weight_kg']);
            return true;
        });
        $item->shouldReceive('containers')->andReturn($relation);
        (new ReflectionMethod($parser, 'createContainer'))->invoke($parser, $number, [
            'description' => $empty ? 'VACIO' : 'CARGA', 'containers' => [$number],
            'gross_weight_kg' => $empty ? 0 : 100, 'package_info' => '1:PK',
        ], $item);
        return Container::where('container_number', $number)->firstOrFail();
    }

    public function test_cmsp_empty_normalizes_only_from_positive_source_tare(): void
    {
        $c = $this->cmsp(3850, 0);
        $this->assertEquals(3850, $c->tare_weight_kg);
        $this->assertEquals(3850, $c->current_gross_weight_kg);
        $this->assertEquals(0, $c->cargo_weight_kg);
        $this->assertEquals(4100, $this->cmsp(3850, 4100)->current_gross_weight_kg);
        foreach ([null, 0, -1, 'invalid'] as $tare) {
            $this->assertNull($this->cmsp($tare, 0)->current_gross_weight_kg);
        }
        $this->assertNull($this->cmsp(3850, 0, false)->current_gross_weight_kg);
    }

    public function test_tfp_source_tare_wins_and_explicit_gross_is_preserved(): void
    {
        $c = $this->tfp(['numero' => 'BMOU4805279', 'tara' => '3860']);
        $this->assertEquals(3860, $c->tare_weight_kg);
        $this->assertEquals(3860, $c->current_gross_weight_kg);
        $this->assertEquals(0, $c->cargo_weight_kg);
        $c = $this->tfp(['tara' => '3860', 'peso' => '4200']);
        $this->assertEquals(4200, $c->current_gross_weight_kg);
        $this->assertEquals(0, $c->cargo_weight_kg);
        $this->assertEquals(3860, $this->tfp(['tara' => '3860', 'peso' => '0'])->current_gross_weight_kg);
    }

    public function test_tfp_missing_and_zero_are_distinct_and_catalog_never_infers_gross(): void
    {
        foreach ([null, '', 'invalid', '0', '-1'] as $tare) {
            $this->assertNull($this->tfp(['tara' => $tare])->current_gross_weight_kg);
            $this->assertEquals(0, $this->tfp(['tara' => $tare, 'peso' => '0'])->current_gross_weight_kg);
        }
        foreach (['H', 'P', 'L'] as $condition) {
            $this->assertNull($this->tfp(['condicion' => $condition, 'tara' => '3860'])->current_gross_weight_kg);
            $c = $this->tfp(['condicion' => $condition, 'tara' => '3860', 'peso' => '12000']);
            $this->assertEquals(12000, $c->current_gross_weight_kg);
            $this->assertEquals(12000, $c->cargo_weight_kg);
        }
    }

    public function test_unsupported_tfp_condition_c_is_not_normalized(): void
    {
        $this->expectException(\Exception::class);
        $this->tfp(['condicion' => 'C', 'tara' => '3860']);
    }

    public function test_reused_container_is_not_overwritten(): void
    {
        $original = $this->tfp(['numero' => 'REUSED', 'tara' => '3860', 'peso' => '4200']);
        $reused = $this->tfp(['numero' => 'REUSED', 'tara' => '5000']);
        $this->assertSame($original->id, $reused->id);
        $this->assertEquals(4200, $reused->current_gross_weight_kg);
    }

    public function test_arca_weight_guards_still_block_invalid_data(): void
    {
        $company = (new Company())->forceFill(['ws_environment' => 'testing']);
        $generator = new SimpleXmlGenerator($company);
        foreach ([[3850, null, 'PesoBruto es obligatorio'], [0, 0, 'mayor que cero'],
            [0, -1, 'mayor que cero'], [null, 3850, 'Tara es obligatoria'],
            [4000, 3850, 'Tara no puede superar']] as [$tare, $gross, $message]) {
            $container = (object) ['container_number' => 'QA', 'containerType' => (object) ['iso_code' => '45G1'],
                'condition' => 'V', 'tare_weight_kg' => $tare, 'current_gross_weight_kg' => $gross];
            $writer = new XMLWriter(); $writer->openMemory();
            try {
                (new ReflectionMethod($generator, 'writeIaContainer'))
                    ->invoke($generator, $writer, $container, new BillOfLading(), null, new Voyage());
                $this->fail('Debía bloquear el peso inválido');
            } catch (\Exception $e) {
                $this->assertStringContainsString($message, $e->getMessage());
            }
        }
    }
}
