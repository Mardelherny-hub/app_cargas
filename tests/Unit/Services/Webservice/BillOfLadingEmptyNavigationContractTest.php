<?php

namespace Tests\Unit\Services\Webservice;

use App\Livewire\BillOfLadingEditForm;
use App\Models\BillOfLading;
use App\Models\Container;
use App\Models\ShipmentItem;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Mockery;
use ReflectionMethod;
use Tests\TestCase;

class BillOfLadingEmptyNavigationContractTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Todo el guardado ocurre en SQLite temporal, sin datos del operador.
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'database.connections.sqlite.foreign_key_constraints' => false,
            'logging.default' => 'null',
        ]);
        DB::purge('sqlite');
        \Illuminate\Support\Facades\Schema::create('companies', function ($table) {
            $table->id();
            $table->string('legal_name');
        });
        \Illuminate\Support\Facades\Schema::create('voyages', function ($table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
        });
        \Illuminate\Support\Facades\Schema::create('shipments', function ($table) {
            $table->id();
            $table->unsignedBigInteger('voyage_id');
        });
        DB::table('companies')->insert(['id' => 1, 'legal_name' => 'Navigation fixture']);
        DB::table('voyages')->insert(['id' => 1, 'company_id' => 1]);
        DB::table('shipments')->insert(['id' => 393, 'voyage_id' => 1]);

        $company = \App\Models\Company::findOrFail(1);
        $user = (new \App\Models\User())->forceFill([
            'id' => 1, 'userable_type' => \App\Models\Company::class, 'userable_id' => 1,
        ]);
        $role = (new \Spatie\Permission\Models\Role())->forceFill([
            'id' => 1, 'name' => 'company-admin', 'guard_name' => 'web',
        ]);
        $user->setRelation('roles', new Collection([$role]));
        $user->setRelation('userable', $company);
        $this->actingAs($user);
    }

    private function editForm(string $containerCondition): BillOfLadingEditForm
    {
        $item = (new ShipmentItem())->forceFill(['id' => 31737]);
        $container = (new Container())->forceFill([
            'id' => 41048,
            'condition' => $containerCondition,
            'container_condition' => $containerCondition === 'V' ? 'P' : 'H',
        ]);
        $item->setRelation('containers', new Collection([$container]));

        $existingItems = Mockery::mock(HasMany::class);
        $existingItems->shouldReceive('exists')->once()->andReturn(true);
        if ($containerCondition === 'V') {
            $existingItems->shouldReceive('orderBy')
                ->once()->with('id')->andReturnSelf();
            $existingItems->shouldReceive('first')->once()->andReturn($item);
        }

        $contacts = Mockery::mock(HasMany::class);
        $contacts->shouldReceive('where')->times(3)->andReturnSelf();
        $contacts->shouldReceive('first')->times(3)->andReturn(null);

        $stored = (new BillOfLading())->forceFill([
            'id' => 13146,
            'total_packages' => 0,
            'gross_weight_kg' => 0,
        ]);

        $bill = Mockery::mock(BillOfLading::class)->makePartial();
        $bill->forceFill(['id' => 13146, 'bill_number' => 'VACIOS']);
        $bill->setRelation('shipmentItems', new Collection([$item]));
        $bill->shouldReceive('shipmentItems')->andReturn($existingItems);
        $bill->shouldReceive('fresh')->once()->andReturn($stored);
        $bill->shouldReceive('update')->once()->withArgs(function (array $data) {
            $this->assertNull($data['origin_location']);
            $this->assertNull($data['origin_country_code']);
            $this->assertNotNull($data['origin_loading_date']);
            return true;
        })->andReturn(true);
        $bill->shouldReceive('specificContacts')->times(3)->andReturn($contacts);

        $form = new BillOfLadingEditForm();
        $form->billOfLading = $bill;
        $form->shipment_id = 393;
        $form->bill_number = 'VACIOS';
        $form->bill_date = '2026-10-08';
        $form->loading_date = '2026-10-08';
        $form->origin_loading_date = '2026-10-08';
        $form->origin_location = '';
        $form->origin_country_code = '';

        return $form;
    }

    public function test_empty_container_is_recognized_without_database(): void
    {
        $form = new BillOfLadingEditForm();
        $bill = new BillOfLading();
        $item = new ShipmentItem();
        $container = (new Container())->forceFill([
            'condition' => 'V',
            'container_condition' => 'V',
        ]);
        $item->setRelation('containers', new Collection([$container]));
        $bill->setRelation('shipmentItems', new Collection([$item]));
        $form->billOfLading = $bill;

        $method = new ReflectionMethod($form, 'billHasEmptyContainer');
        $this->assertTrue($method->invoke($form));

        $container->condition = 'L';
        $container->container_condition = 'H';
        $this->assertFalse($method->invoke($form));
    }

    public function test_empty_bill_saves_with_optional_origin_fields_and_opens_existing_item(): void
    {
        $form = $this->editForm('V');
        $response = $form->submit();

        $this->assertNotNull($response);
        $this->assertStringEndsWith(
            '/company/shipment-items/31737/edit',
            $response->getTargetUrl()
        );
        $this->assertSame('', $form->origin_location);
        $this->assertSame('', $form->origin_country_code);
    }

    public function test_loaded_bill_preserves_normal_navigation(): void
    {
        $response = $this->editForm('L')->submit();

        $this->assertNotNull($response);
        $this->assertStringEndsWith(
            '/company/bills-of-lading/13146',
            $response->getTargetUrl()
        );
    }
}
