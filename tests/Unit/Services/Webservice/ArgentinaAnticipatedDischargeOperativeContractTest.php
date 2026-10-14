<?php

namespace Tests\Unit\Services\Webservice;

use App\Models\BillOfLading;
use App\Models\Client;
use App\Models\Company;
use App\Models\Container;
use App\Models\ContainerType;
use App\Models\Country;
use App\Models\Port;
use App\Models\ShipmentItem;
use App\Models\User;
use App\Models\Voyage;
use App\Services\Simple\ArgentinaAnticipatedService;
use App\Services\Simple\SimpleXmlGenerator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use ReflectionMethod;
use Tests\TestCase;
use XMLWriter;

class ArgentinaAnticipatedDischargeOperativeContractTest extends TestCase
{
    private SimpleXmlGenerator $generator;
    private BillOfLading $bill;
    private ShipmentItem $item;
    private Voyage $voyage;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'database.connections.sqlite.foreign_key_constraints' => false,
            'logging.default' => 'null',
        ]);
        DB::purge('sqlite');
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());

        Schema::create('afip_operative_locations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('port_id');
            $table->string('location_code');
            $table->string('customs_code');
            $table->boolean('is_active');
        });

        $country = (new Country())->forceFill([
            'id' => 1,
            'alpha2_code' => 'AR',
            'codigo_afip' => '032',
        ]);
        $dischargePort = (new Port())->forceFill(['id' => 146, 'code' => 'ARBUE']);
        $dischargePort->setRelation('country', $country);

        // Evitar consultas ajenas a la resolución que se está probando.
        $this->bill = new class extends BillOfLading {
            public function loadMissing($relations)
            {
                return $this;
            }
        };
        $this->bill->forceFill([
            'id' => 13145,
            'bill_number' => 'RBLCAP005243-01',
            'commodity_code' => '1701.99.10',
            'loading_date' => '2026-10-10',
            'discharge_date' => '2026-10-13',
            'is_consolidated' => 'N',
            'is_transit_transshipment' => 'N',
            'cargo_marks' => 'QA',
        ]);
        $this->bill->setRelation('loadingPort',
            (new Port())->forceFill(['id' => 100, 'code' => 'PYASU']));
        $this->bill->setRelation('dischargePort', $dischargePort);
        // Para CBC el cargador es el forwarder real (Roberto, 04/10).
        // El generador debe usarlo incluso si IndicadorConsolidado=N.
        $this->bill->setRelation('shipper',
            (new Client())->forceFill(['legal_name' => 'CARGADOR QA']));
        foreach (['transshipmentPort', 'consignee', 'notifyParty'] as $relation) {
            $this->bill->setRelation($relation, null);
        }

        $this->item = (new ShipmentItem())->forceFill([
            'id' => 31727,
            'line_number' => 1,
            'item_description' => 'ARROZ',
            'packaging_code' => 'PK',
            'package_quantity' => 1,
            'gross_weight_kg' => 100,
            'cargo_marks' => 'QA',
            'is_secure_logistics_operator' => 'N',
            'is_monitored_transit' => 'N',
            'is_renar' => 'N',
        ]);
        $this->item->setRelation('containers', new Collection());
        $this->item->setRelation('cargoType', null);
        $this->bill->setRelation('shipmentItems', new Collection([$this->item]));

        $this->voyage = (new Voyage())->forceFill(['id' => 7]);
        $this->generator = new SimpleXmlGenerator(
            (new Company())->forceFill(['id' => 1, 'ws_environment' => 'testing'])
        );
    }

    private function locations(array $rows): void
    {
        DB::table('afip_operative_locations')->delete();

        if ($rows !== []) {
            DB::table('afip_operative_locations')->insert($rows);
        }
    }

    private function location(string $code = '10073', int $portId = 146): array
    {
        return [
            'port_id' => $portId,
            'location_code' => $code,
            'customs_code' => '001',
            'is_active' => true,
        ];
    }

    private function render(string $method, array $arguments): string
    {
        $writer = new XMLWriter();
        $writer->openMemory();
        (new ReflectionMethod(SimpleXmlGenerator::class, $method))
            ->invoke($this->generator, $writer, ...$arguments);

        return $writer->outputMemory();
    }

    private function preflightOperativeErrors(): array
    {
        $voyage = Mockery::mock(Voyage::class)->makePartial();
        $voyage->forceFill([
            'id' => 7,
            'company_id' => 1,
            'argentina_voyage_id' => '2026000000000001',
        ]);
        $voyage->setRelation('shipments', new Collection());

        $query = Mockery::mock(HasManyThrough::class);
        $query->shouldReceive('with')->once()->andReturnSelf();
        $query->shouldReceive('whereHas')->once()->andReturnSelf();
        $query->shouldReceive('get')->once()
            ->andReturn(new Collection([$this->bill]));
        $voyage->shouldReceive('billsOfLading')->once()->andReturn($query);

        $service = new ArgentinaAnticipatedService(
            (new Company())->forceFill(['id' => 1, 'ws_environment' => 'testing']),
            (new User())->forceFill(['id' => 1])
        );

        $result = (new ReflectionMethod(
            ArgentinaAnticipatedService::class,
            'validateTitlesData'
        ))->invoke($service, $voyage);

        return collect($result['errors'])
            ->filter(fn ($error) => str_contains(
                $error,
                'CodigoLugarOperativoDescarga'
            ))->values()->all();
    }

    private function container(string $condition): Container
    {
        $container = (new Container())->forceFill([
            'container_number' => $condition === 'V' ? 'CAAU8193944' : 'SEGU7679464',
            'condition' => $condition,
            'container_condition' => $condition === 'V' ? 'P' : 'H',
            'tare_weight_kg' => 3850,
            'current_gross_weight_kg' => 10000,
            'expiry_date' => '2027-12-01',
        ]);
        $container->setRelation('containerType',
            (new ContainerType())->forceFill(['iso_code' => '45G1']));
        $container->setRelation('operatorClient', null);

        return $container;
    }

    public function test_unique_active_port_location_aligns_preflight_and_three_xml_writers(): void
    {
        $this->locations([$this->location()]);
        $before = $this->bill->getAttributes();

        $this->assertSame([], $this->preflightOperativeErrors());

        $title = $this->render('writeIaTitle', [
            $this->bill, $this->voyage, false,
        ]);
        $this->assertStringContainsString(
            '<ar:CodigoLugarOperativoDescarga>10073</ar:CodigoLugarOperativoDescarga>',
            $title
        );
        $this->assertStringContainsString(
            '<ar:CodigoAduanaDescarga>001</ar:CodigoAduanaDescarga>',
            $title
        );
        $this->assertStringContainsString(
            '<ar:PosicionArancelaria>1701.99.10</ar:PosicionArancelaria>',
            $title
        );
        $this->assertStringContainsString(
            '<ar:RazonSocialFowarderExterior>CARGADOR QA</ar:RazonSocialFowarderExterior>',
            $title
        );

        $cargoContainer = $this->render('writeIaTitleContainer', [
            $this->container('L'), $this->bill, $this->item,
            $this->voyage, false,
        ]);
        $this->assertStringContainsString(
            '<ar:CodigoLugarOperativoDescarga>10073</ar:CodigoLugarOperativoDescarga>',
            $cargoContainer
        );

        $emptyBill = (new BillOfLading())->forceFill([
            'bill_number' => 'VACIOS',
            'loading_date' => '2026-10-08',
            'origin_loading_date' => '2026-10-08',
        ]);
        $emptyBill->setRelation('loadingPort',
            $this->bill->getRelation('loadingPort'));
        $emptyBill->setRelation('dischargePort',
            $this->bill->getRelation('dischargePort'));
        $emptyContainer = $this->render('writeIaContainer', [
            $this->container('V'), $emptyBill, null, $this->voyage,
        ]);
        $this->assertStringContainsString(
            '<CodigoLugarOperativoDescarga>10073</CodigoLugarOperativoDescarga>',
            $emptyContainer
        );
        $this->assertStringNotContainsString(
            '<CodigoLugarOrigen>',
            $emptyContainer
        );
        $this->assertSame($before, $this->bill->getAttributes());
    }

    public function test_multiple_port_locations_cannot_be_guessed(): void
    {
        $this->locations([
            $this->location('10073'),
            $this->location('11021'),
        ]);
        $this->assertCount(1, $this->preflightOperativeErrors());
        $this->expectExceptionMessage(
            'CodigoLugarOperativoDescarga es obligatorio'
        );
        $this->render('writeIaTitle', [
            $this->bill, $this->voyage, false,
        ]);
    }

    public function test_explicit_bill_location_wins_over_multiple_options(): void
    {
        $this->locations([
            $this->location('10073'),
            $this->location('11021'),
        ]);
        $this->bill->operational_discharge_code = '11021';

        $this->assertSame([], $this->preflightOperativeErrors());
        $xml = $this->render('writeIaTitle', [
            $this->bill, $this->voyage, false,
        ]);
        $this->assertStringContainsString(
            '<ar:CodigoLugarOperativoDescarga>11021</ar:CodigoLugarOperativoDescarga>',
            $xml
        );
    }

    public function test_unique_location_belonging_to_another_port_is_not_used(): void
    {
        $this->locations([$this->location('10073', 999)]);

        $this->assertCount(1, $this->preflightOperativeErrors());
        $this->expectExceptionMessage(
            'CodigoLugarOperativoDescarga es obligatorio'
        );
        $this->render('writeIaTitle', [
            $this->bill, $this->voyage, false,
        ]);
    }

    public function test_conflicting_item_locations_are_not_silently_overwritten(): void
    {
        $this->locations([$this->location('10073')]);
        $this->item->operational_discharge_code = '10073';
        $second = (new ShipmentItem())->forceFill([
            'id' => 31728,
            'line_number' => 2,
            'operational_discharge_code' => '11021',
        ]);
        $second->setRelation('containers', new Collection());
        $this->bill->setRelation('shipmentItems',
            new Collection([$this->item, $second]));

        $this->assertCount(1, $this->preflightOperativeErrors());
        $this->expectExceptionMessage(
            'los ítems tienen valores distintos para operational_discharge_code'
        );
        $this->render('writeIaTitle', [
            $this->bill, $this->voyage, false,
        ]);
    }
}
