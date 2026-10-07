<?php

namespace Tests\Unit\Services\Webservice;

use App\Models\BillOfLading;
use App\Models\Company;
use App\Models\Container;
use App\Models\ContainerType;
use App\Models\Port;
use App\Models\Voyage;
use App\Services\Simple\SimpleXmlGenerator;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use Tests\TestCase;
use XMLWriter;

class EmptyContainerOriginTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:',
            'logging.default' => 'null']);
        DB::purge('sqlite');
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        Schema::create('countries', function (Blueprint $t) {
            $t->id(); $t->string('alpha2_code'); $t->string('codigo_afip');
        });
        DB::table('countries')->insert(['alpha2_code' => 'PY', 'codigo_afip' => '221']);
        Schema::create('afip_operative_locations', function (Blueprint $t) {
            $t->id(); $t->string('location_code'); $t->string('customs_code'); $t->boolean('is_active');
        });
        DB::table('afip_operative_locations')->insert([
            'location_code' => '11021', 'customs_code' => '001', 'is_active' => true,
        ]);
    }

    private function bill(array $attributes = []): BillOfLading
    {
        $bill = (new BillOfLading())->forceFill($attributes + [
            'loading_date' => '2026-09-18', 'discharge_date' => '2026-09-20',
            'operational_discharge_code' => '11021',
        ]);
        $bill->setRelation('loadingPort', (new Port())->forceFill(['code' => 'PYASU']));
        $bill->setRelation('dischargePort', (new Port())->forceFill(['code' => 'ARBUE']));
        return $bill;
    }

    private function render(BillOfLading $bill, string $condition = 'V'): string
    {
        $container = (new Container())->forceFill([
            'container_number' => 'BEAU6267394', 'condition' => $condition,
            'container_condition' => $condition, 'tare_weight_kg' => 3850,
            'current_gross_weight_kg' => 3850, 'expiry_date' => '2027-12-01',
        ]);
        $container->setRelation('containerType', (new ContainerType())->forceFill(['iso_code' => '45G1']));
        $container->setRelation('operatorClient', null);
        $generator = new SimpleXmlGenerator((new Company())->forceFill(['ws_environment' => 'testing']));
        $writer = new XMLWriter();
        $writer->openMemory();
        (new ReflectionMethod($generator, 'writeIaContainer'))
            ->invoke($generator, $writer, $container, $bill, null, new Voyage());
        return $writer->outputMemory();
    }

    public function test_empty_without_origin_omits_block_and_preserves_loading_date(): void
    {
        $bill = $this->bill();
        $before = $bill->getAttributes();
        $xml = $this->render($bill);
        $this->assertStringContainsString('<FechaEmbarque>2026-09-18T00:00:00</FechaEmbarque>', $xml);
        foreach (['FechaCargaLugarOrigen', 'CodigoLugarOrigen', 'CodigoPaisLugarOrigen'] as $tag) {
            $this->assertStringNotContainsString('<'.$tag.'>', $xml);
        }
        $this->assertSame($before, $bill->getAttributes());
        $this->assertNull($bill->origin_loading_date);
    }

    public function test_origin_date_requires_place(): void
    {
        $this->expectExceptionMessage('CodigoLugarOrigen es obligatorio');
        $this->render($this->bill(['origin_loading_date' => '2026-09-17']));
    }

    public function test_origin_place_requires_country(): void
    {
        $this->expectExceptionMessage('CodigoPaisLugarOrigen es obligatorio');
        $this->render($this->bill(['origin_location' => 'CCPMI']));
    }

    public function test_origin_place_and_country_require_origin_date(): void
    {
        $this->expectExceptionMessage('FechaCargaLugarOrigen es obligatoria cuando se informa CodigoLugarOrigen');
        $this->render($this->bill(['origin_location' => 'CCPMI', 'origin_country_code' => 'PY']));
    }

    public function test_country_alone_is_not_a_coherent_block(): void
    {
        $this->expectExceptionMessage('CodigoLugarOrigen es obligatorio');
        $this->render($this->bill(['origin_country_code' => 'PY']));
    }

    public function test_complete_origin_serializes_real_values_in_order_without_mutation(): void
    {
        $bill = $this->bill(['origin_loading_date' => '2026-09-17',
            'origin_location' => 'CCPMI', 'origin_country_code' => 'PY']);
        $before = $bill->getAttributes();
        $xml = $this->render($bill);
        $this->assertStringContainsString(
            '<FechaEmbarque>2026-09-18T00:00:00</FechaEmbarque>'.
            '<FechaCargaLugarOrigen>2026-09-17T00:00:00</FechaCargaLugarOrigen>'.
            '<CodigoLugarOrigen>CCPMI</CodigoLugarOrigen>'.
            '<CodigoPaisLugarOrigen>221</CodigoPaisLugarOrigen>'.
            '<CodigoPuertoDescarga>ARBUE</CodigoPuertoDescarga>', $xml
        );
        $this->assertSame($before, $bill->getAttributes());
    }

    public function test_loading_port_requires_loading_date(): void
    {
        $this->expectExceptionMessage('FechaEmbarque es obligatoria cuando se informa CodigoPuertoEmbarque');
        $this->render($this->bill(['loading_date' => null]));
    }

    public function test_mail_container_does_not_evaluate_origin_or_loading_date(): void
    {
        $xml = $this->render($this->bill(['loading_date' => null, 'origin_location' => 'CCPMI']), 'C');
        $this->assertStringNotContainsString('FechaCargaLugarOrigen', $xml);
        $this->assertStringNotContainsString('FechaEmbarque', $xml);
    }

    public function test_house_and_pier_remain_outside_this_voyage_writer(): void
    {
        foreach (['H', 'P'] as $condition) {
            try {
                $this->render($this->bill(), $condition);
                $this->fail('RegistrarViaje no admite H/P');
            } catch (\Exception $e) {
                $this->assertStringContainsString('RegistrarViaje sólo admite contenedores vacíos o de correo', $e->getMessage());
            }
        }
    }
}
