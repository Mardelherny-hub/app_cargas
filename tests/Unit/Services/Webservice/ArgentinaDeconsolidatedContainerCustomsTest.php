<?php

namespace Tests\Unit\Services\Webservice;

use App\Http\Controllers\Company\Simple\DeconsolidationContainerLinkController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Tests\TestCase;

class ArgentinaDeconsolidatedContainerCustomsTest extends TestCase
{
    #[Test]
    public function documentary_update_has_a_scoped_patch_route_and_draft_form(): void
    {
        $route = app('router')->getRoutes()->getByName(
            'company.simple.desconsolidado.operations.bills.items.containers.customs'
        );

        $this->assertNotNull($route);
        $this->assertContains('PATCH', $route->methods());
        $this->assertSame(
            DeconsolidationContainerLinkController::class . '@updateCustoms',
            $route->getActionName()
        );

        $view = file_get_contents(resource_path(
            'views/company/simple/desconsolidado/operations/show.blade.php'
        ));
        $this->assertStringContainsString('containers.customs', $view);
        $this->assertStringContainsString('name="csc_expiry_date"', $view);
        $this->assertStringContainsString('name="acep"', $view);
        $this->assertStringContainsString("@method('PATCH')", $view);
        $this->assertStringContainsString('@if($canLinkContainer)', $view);
    }

    #[Test]
    public function shared_catalog_guard_rejects_other_operations_and_voyages(): void
    {
        $previous = config('database.default');
        config([
            'database.connections.ata_desc_container_scope_test' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => false,
            ],
            'database.default' => 'ata_desc_container_scope_test',
        ]);

        try {
            $schema = Schema::connection('ata_desc_container_scope_test');
            $schema->create('bills_of_lading', function ($table) {
                $table->increments('id');
                $table->unsignedBigInteger('deconsolidation_operation_id')->nullable();
            });
            $schema->create('shipment_items', function ($table) {
                $table->increments('id');
                $table->unsignedBigInteger('bill_of_lading_id');
            });
            $schema->create('container_shipment_item', function ($table) {
                $table->unsignedBigInteger('container_id');
                $table->unsignedBigInteger('shipment_item_id');
            });

            DB::table('bills_of_lading')->insert([
                ['id' => 10, 'deconsolidation_operation_id' => 1],
                ['id' => 11, 'deconsolidation_operation_id' => 1],
                ['id' => 20, 'deconsolidation_operation_id' => 2],
                ['id' => 30, 'deconsolidation_operation_id' => null],
            ]);
            DB::table('shipment_items')->insert([
                ['id' => 101, 'bill_of_lading_id' => 10],
                ['id' => 102, 'bill_of_lading_id' => 11],
                ['id' => 201, 'bill_of_lading_id' => 20],
                ['id' => 301, 'bill_of_lading_id' => 30],
            ]);

            $guard = new ReflectionMethod(
                DeconsolidationContainerLinkController::class,
                'containerHasOtherUses'
            );
            $controller = app(DeconsolidationContainerLinkController::class);

            DB::table('container_shipment_item')->insert([
                'container_id' => 41, 'shipment_item_id' => 101,
            ]);
            $this->assertFalse($guard->invoke($controller, 41, 1));

            DB::table('container_shipment_item')->insert([
                'container_id' => 41, 'shipment_item_id' => 102,
            ]);
            $this->assertFalse($guard->invoke($controller, 41, 1));

            DB::table('container_shipment_item')->insert([
                'container_id' => 41, 'shipment_item_id' => 201,
            ]);
            $this->assertTrue($guard->invoke($controller, 41, 1));

            DB::table('container_shipment_item')->insert([
                'container_id' => 42, 'shipment_item_id' => 301,
            ]);
            $this->assertTrue($guard->invoke($controller, 42, 1));
        } finally {
            DB::purge('ata_desc_container_scope_test');
            config(['database.default' => $previous]);
        }
    }

    #[Test]
    public function update_is_limited_to_draft_owned_container_and_documentary_fields(): void
    {
        $source = file_get_contents(app_path(
            'Http/Controllers/Company/Simple/DeconsolidationContainerLinkController.php'
        ));
        $start = strpos($source, 'public function updateCustoms(');
        $end = strpos($source, 'public function register(', $start);
        $this->assertNotFalse($start);
        $this->assertNotFalse($end);
        $method = substr($source, $start, $end - $start);

        foreach ([
            '$this->authorizedItem(',
            '$this->assertEditableBill(',
            '$this->assertContainerizedDraft(',
            '->lockForUpdate()',
            '$record->created_by_user_id',
            '$this->containerHasOtherUses(',
            "'csc_expiry_date' =>",
            "'acep' =>",
            "'last_updated_by_user_id' =>",
        ] as $invariant) {
            $this->assertStringContainsString($invariant, $method);
        }
        $this->assertStringNotContainsString('sendSoapRequest', $method);
        $this->assertStringNotContainsString('ejecutarExpediente', $method);
    }
}
