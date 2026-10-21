<?php

namespace Tests\Feature;

use App\Http\Controllers\Company\Simple\HidroviaTransshipmentDraftController;
use Illuminate\Http\Request;
use Tests\TestCase;

class HidroviaTransshipmentDraftRoutesTest extends TestCase
{
    public function test_internal_draft_routes_resolve_to_the_intended_controller(): void
    {
        $entries = [
            ['GET', 'drafts.show', 'show', '/distribution'],
            ['PUT', 'drafts.update', 'update', '/distribution'],
            ['POST', 'drafts.preflight', 'preflight', '/distribution/preflight'],
        ];
        foreach ($entries as [$verb, $suffix, $action, $end]) {
            $route = app('router')->getRoutes()->match(Request::create(
                '/company/simple/webservices/transbordo/7/bills/31' . $end, $verb
            ));
            $this->assertSame('company.simple.transbordo.' . $suffix, $route->getName());
            $this->assertSame(HidroviaTransshipmentDraftController::class . '@' . $action,
                $route->getActionName());
            $this->assertContains('auth', $route->gatherMiddleware());
            $this->assertContains('role:company-admin|user', $route->gatherMiddleware());
        }
    }

    public function test_anonymous_users_cannot_read_or_write_distribution(): void
    {
        $params = ['voyage' => 7, 'bill' => 31];
        $this->get(route('company.simple.transbordo.drafts.show', $params))->assertRedirect(route('login'));
        $this->putJson(route('company.simple.transbordo.drafts.update', $params), ['allocations' => []])
            ->assertUnauthorized();
        $this->postJson(route('company.simple.transbordo.drafts.preflight', $params))
            ->assertUnauthorized();
    }

    public function test_controller_reads_only_its_company_and_returns_the_put_input_shape(): void
    {
        $previous = config('database.default');
        config([
            'database.default' => 'transshipment_controller_sqlite',
            'database.connections.transshipment_controller_sqlite' => [
                'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
                'foreign_key_constraints' => false,
            ],
        ]);
        try {
            $schema = \Illuminate\Support\Facades\Schema::connection('transshipment_controller_sqlite');
            $schema->create('voyages', function ($t) {
                $t->id(); $t->unsignedBigInteger('company_id');
            });
            $schema->create('shipments', function ($t) {
                $t->id(); $t->unsignedBigInteger('voyage_id');
            });
            $schema->create('bills_of_lading', function ($t) {
                $t->id(); $t->unsignedBigInteger('shipment_id'); $t->softDeletes();
            });
            $schema->create('hidrovia_transshipment_allocations', function ($t) {
                $t->id(); $t->unsignedBigInteger('bill_of_lading_id');
                $t->unsignedBigInteger('shipment_item_id'); $t->unsignedBigInteger('target_shipment_id');
                $t->unsignedBigInteger('container_id')->nullable();
                $t->integer('package_quantity'); $t->decimal('gross_weight_kg', 12, 2);
            });
            $db = \Illuminate\Support\Facades\DB::connection('transshipment_controller_sqlite');
            $db->table('voyages')->insert([
                ['id' => 7, 'company_id' => 202],
                ['id' => 8, 'company_id' => 101],
            ]);
            $db->table('shipments')->insert(['id' => 11, 'voyage_id' => 8]);
            $db->table('bills_of_lading')->insert(['id' => 31, 'shipment_id' => 11]);
            $db->table('hidrovia_transshipment_allocations')->insert([
                'bill_of_lading_id' => 31, 'shipment_item_id' => 41,
                'target_shipment_id' => 11, 'container_id' => null,
                'package_quantity' => 10, 'gross_weight_kg' => '100.25',
            ]);
            $controller = new class extends HidroviaTransshipmentDraftController {
                protected function getUserCompany(): ?\App\Models\Company
                {
                    return (new \App\Models\Company())->forceFill(['id' => 101]);
                }
                protected function hasCompanyRole(string $role): bool
                {
                    return $role === 'Transbordos';
                }
            };
            try {
                $controller->show(7, 31);
                $this->fail('El expediente de otra empresa no debe ser visible.');
            } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $exception) {
                $this->assertSame(\App\Models\Voyage::class, $exception->getModel());
            }
            $response = $controller->show(8, 31)->getData(true);
            $this->assertSame(31, $response['bill_of_lading_id']);
            $this->assertSame(11, $response['allocations'][0]['shipment_id']);
            $this->assertArrayNotHasKey('target_shipment_id', $response['allocations'][0]);
            $this->assertSame('100.25', $response['allocations'][0]['gross_weight_kg']);
        } finally {
            \Illuminate\Support\Facades\DB::purge('transshipment_controller_sqlite');
            config(['database.default' => $previous]);
        }
    }

    public function test_new_draft_controller_has_no_external_soap_method(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Company/Simple/HidroviaTransshipmentDraftController.php'));
        $this->assertNotFalse($source);
        foreach (['sendSoapRequest(', 'RegistrarTitEnvios(', 'ejecutarMetodo(', 'SoapClient(', '__doRequest('] as $operation) {
            $this->assertStringNotContainsString($operation, $source);
        }
    }
}
