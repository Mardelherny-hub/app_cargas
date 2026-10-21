<?php

namespace Tests\Feature;

use App\Http\Controllers\Company\Simple\HidroviaTransshipmentDraftController;
use App\Models\Company;
use App\Models\HidroviaTransshipmentAllocation;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/** QA funcional de editor/API reales, con SQLite aislada y sin llamadas SOAP. */
class HidroviaTransshipmentWorkflowTest extends TestCase
{
    private string $previousConnection;
    private HidroviaTransshipmentDraftController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->previousConnection = config('database.default');
        config([
            'database.default' => 'transshipment_workflow_sqlite',
            'database.connections.transshipment_workflow_sqlite' => [
                'driver' => 'sqlite', 'database' => ':memory:',
                'prefix' => '', 'foreign_key_constraints' => false,
            ],
        ]);
        $schema = Schema::connection('transshipment_workflow_sqlite');
        $schema->create('users', fn (Blueprint $t) => $t->id());
        $schema->create('voyages', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('company_id'); $t->string('voyage_number');
        });
        $schema->create('vessel_types', function (Blueprint $t) {
            $t->id(); $t->string('category');
        });
        $schema->create('vessels', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('vessel_type_id'); $t->string('name');
        });
        $schema->create('shipments', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('voyage_id'); $t->unsignedBigInteger('vessel_id');
            $t->string('shipment_number');
        });
        $schema->create('bills_of_lading', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('shipment_id'); $t->string('bill_number');
            $t->string('status'); $t->string('is_transit_transshipment');
            $t->boolean('is_fractional')->default(false);
            $t->timestamp('verified_at')->nullable(); $t->timestamp('argentina_sent_at')->nullable();
            $t->timestamp('paraguay_sent_at')->nullable(); $t->softDeletes();
        });
        $schema->create('shipment_items', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('bill_of_lading_id');
            $t->integer('line_number'); $t->string('item_description');
            $t->integer('package_quantity'); $t->decimal('gross_weight_kg', 12, 2);
        });
        $schema->create('containers', function (Blueprint $t) {
            $t->id(); $t->string('container_number');
        });
        $schema->create('container_shipment_item', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('shipment_item_id');
            $t->unsignedBigInteger('container_id');
            foreach (['package_quantity', 'gross_weight_kg', 'net_weight_kg', 'volume_m3',
                'quantity_percentage', 'weight_percentage', 'volume_percentage',
                'verified_gross_mass_kg'] as $name) $t->decimal($name)->nullable();
            foreach (['loaded_at', 'sealed_at', 'loading_sequence', 'source_seals',
                'source_line_numbers', 'container_condition', 'status'] as $name) {
                $t->string($name)->nullable();
            }
            $t->timestamps();
        });
        (require database_path('migrations/2026_10_19_123000_create_hidrovia_transshipment_allocations_table.php'))->up();
        (require database_path('migrations/2025_07_23_120939_create_webservice_transactions_table.php'))->up();
        DB::table('users')->insert(['id' => 1]);
        DB::table('voyages')->insert([
            ['id' => 7, 'company_id' => 101, 'voyage_number' => 'QA-7'],
            ['id' => 8, 'company_id' => 202, 'voyage_number' => 'QA-8'],
        ]);
        DB::table('vessel_types')->insert([
            ['id' => 301, 'category' => 'barge'], ['id' => 302, 'category' => 'tugboat'],
        ]);
        DB::table('vessels')->insert([
            ['id' => 201, 'vessel_type_id' => 301, 'name' => 'QA BARCAZA 1'],
            ['id' => 202, 'vessel_type_id' => 301, 'name' => 'QA BARCAZA 2'],
            ['id' => 299, 'vessel_type_id' => 302, 'name' => 'QA REMOLCADOR'],
        ]);
        DB::table('shipments')->insert([
            ['id' => 11, 'voyage_id' => 7, 'vessel_id' => 201, 'shipment_number' => 'B-201'],
            ['id' => 12, 'voyage_id' => 7, 'vessel_id' => 202, 'shipment_number' => 'B-202'],
            ['id' => 13, 'voyage_id' => 7, 'vessel_id' => 299, 'shipment_number' => 'T-299'],
            ['id' => 99, 'voyage_id' => 8, 'vessel_id' => 201, 'shipment_number' => 'B-OTRA'],
        ]);
        DB::table('bills_of_lading')->insert([
            ['id' => 31, 'shipment_id' => 11, 'bill_number' => 'QA-SUELTA', 'status' => 'draft', 'is_transit_transshipment' => 'S', 'is_fractional' => false],
            ['id' => 32, 'shipment_id' => 11, 'bill_number' => 'QA-CONT', 'status' => 'draft', 'is_transit_transshipment' => 'S', 'is_fractional' => false],
            ['id' => 33, 'shipment_id' => 99, 'bill_number' => 'QA-AJENA', 'status' => 'draft', 'is_transit_transshipment' => 'S', 'is_fractional' => false],
            ['id' => 34, 'shipment_id' => 11, 'bill_number' => 'QA-NORMAL', 'status' => 'draft', 'is_transit_transshipment' => 'N', 'is_fractional' => false],
            ['id' => 35, 'shipment_id' => 11, 'bill_number' => 'QA-FRAC', 'status' => 'draft', 'is_transit_transshipment' => 'S', 'is_fractional' => true],
        ]);
        DB::table('shipment_items')->insert([
            ['id' => 41, 'bill_of_lading_id' => 31, 'line_number' => 1, 'item_description' => 'QA SUELTA', 'package_quantity' => 10, 'gross_weight_kg' => '100.25'],
            ['id' => 42, 'bill_of_lading_id' => 32, 'line_number' => 1, 'item_description' => 'QA CONT', 'package_quantity' => 8, 'gross_weight_kg' => '80.00'],
        ]);
        DB::table('containers')->insert([
            ['id' => 51, 'container_number' => 'QA-CONT-51'],
            ['id' => 52, 'container_number' => 'QA-CONT-52'],
        ]);
        DB::table('container_shipment_item')->insert([
            ['shipment_item_id' => 42, 'container_id' => 51],
            ['shipment_item_id' => 42, 'container_id' => 52],
        ]);
        $this->controller = new class extends HidroviaTransshipmentDraftController {
            public bool $allowed = true;
            protected function getUserCompany(): ?Company
            {
                return (new Company())->forceFill(['id' => 101, 'name' => 'QA EMPRESA']);
            }
            protected function hasCompanyRole(string $role): bool
            {
                return $this->allowed && $role === 'Transbordos';
            }
        };
    }

    protected function tearDown(): void
    {
        Auth::logout();
        DB::purge('transshipment_workflow_sqlite');
        config(['database.default' => $this->previousConnection]);
        parent::tearDown();
    }

    private function row(int $shipmentId, int $itemId, int $quantity, string $weight, ?int $containerId = null): array
    {
        return ['shipment_id' => $shipmentId, 'shipment_item_id' => $itemId,
            'container_id' => $containerId, 'package_quantity' => $quantity,
            'gross_weight_kg' => $weight];
    }

    private function save(int $billId, array $rows): array
    {
        return $this->controller->update(Request::create('/', 'PUT', ['allocations' => $rows]),
            7, $billId)->getData(true);
    }

    public function test_carga_suelta_editor_save_reload_and_preflight_without_transmission(): void
    {
        $view = $this->controller->edit(7, 31);
        $this->assertSame('company.simple.transbordo.show', $view->name());
        $this->assertSame([11, 12], $view->getData()['shipments']->pluck('id')->all());
        $this->assertSame([], $this->controller->show(7, 31)->getData(true)['allocations']);
        $result = $this->save(31, [$this->row(12, 41, 6, '60.15'), $this->row(11, 41, 4, '40.10')]);
        $this->assertTrue($result['saved']);
        $this->assertSame(2, $result['allocations_count']);
        $this->assertSame([11, 12], array_column($this->controller->show(7, 31)->getData(true)['allocations'], 'shipment_id'));
        $preflight = $this->controller->preflight(7, 31)->getData(true);
        $this->assertTrue($preflight['valid']);
        $this->assertFalse($preflight['transmitted']);
        $this->assertSame([1, 2], array_column($preflight['distribution']['shipments'], 'id_envio'));
        $this->assertSame([201, 202], array_column($preflight['distribution']['shipments'], 'vessel_id'));
        $this->assertSame(0, DB::table('webservice_transactions')->count());
    }

    public function test_partial_draft_is_saved_but_not_ready_until_completed(): void
    {
        $this->save(31, [$this->row(11, 41, 4, '40.10')]);
        $this->assertSame(1, HidroviaTransshipmentAllocation::count());
        try {
            $this->controller->preflight(7, 31);
            $this->fail('Una distribución parcial no puede estar lista.');
        } catch (ValidationException $error) {
            $this->assertStringContainsString('completar exactamente', $error->errors()['distribution'][0]);
        }
        $this->assertSame(1, HidroviaTransshipmentAllocation::count());
        $this->save(31, [$this->row(11, 41, 4, '40.10'), $this->row(12, 41, 6, '60.15')]);
        $this->assertTrue($this->controller->preflight(7, 31)->getData(true)['valid']);
    }

    public function test_containers_stay_in_their_own_barges_on_reload_and_preflight(): void
    {
        $view = $this->controller->edit(7, 32);
        $this->assertSame([51, 52], $view->getData()['bill']->shipmentItems[0]->containers->pluck('id')->all());
        $this->save(32, [$this->row(11, 42, 3, '30.00', 51), $this->row(12, 42, 5, '50.00', 52)]);
        $rows = $this->controller->show(7, 32)->getData(true)['allocations'];
        $this->assertSame([51, 52], array_column($rows, 'container_id'));
        $response = $this->controller->preflight(7, 32)->getData(true);
        $this->assertTrue($response['valid']);
        $this->assertSame([51, 52], array_map(fn ($shipment) =>
            $shipment['lines'][0]['container_id'], $response['distribution']['shipments']));
        $this->assertSame(0, DB::table('webservice_transactions')->count());
    }

    public function test_invalid_container_duplication_cannot_replace_valid_draft(): void
    {
        $valid = [$this->row(11, 42, 3, '30.00', 51), $this->row(12, 42, 5, '50.00', 52)];
        $this->save(32, $valid);
        try {
            $this->save(32, [$this->row(11, 42, 3, '30.00', 51), $this->row(12, 42, 5, '50.00', 51)]);
            $this->fail('Un contenedor no debe asignarse a dos barcazas.');
        } catch (ValidationException $error) {
            $this->assertStringContainsString('dos embarcaciones', $error->errors()['distribution'][0]);
        }
        $this->assertSame([51, 52], array_column($this->controller->show(7, 32)->getData(true)['allocations'], 'container_id'));
    }

    public function test_foreign_bill_nontransshipment_fractional_and_role_are_denied(): void
    {
        foreach ([33, 34, 35] as $id) {
            try {
                $this->controller->edit(7, $id);
                $this->fail('No debe abrirse el conocimiento ' . $id);
            } catch (HttpException|\Illuminate\Database\Eloquent\ModelNotFoundException $error) {
                $this->assertTrue(true);
            }
        }
        $this->controller->allowed = false;
        foreach (['edit', 'show', 'preflight'] as $action) {
            try {
                $this->controller->$action(7, 31);
                $this->fail('Acceso sin rol en ' . $action);
            } catch (HttpException $error) {
                $this->assertSame(403, $error->getStatusCode());
            }
        }
    }

    public function test_request_validation_rejects_more_than_two_weight_decimals(): void
    {
        try {
            $this->save(31, [$this->row(11, 41, 4, '40.123')]);
            $this->fail('El peso de 3 decimales no debe pasar el controlador.');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey('allocations.0.gross_weight_kg', $error->errors());
        }
        $this->assertSame(0, HidroviaTransshipmentAllocation::count());
    }

    public function test_editor_renders_real_existing_data_and_no_transmit_button(): void
    {
        $user = (new User())->forceFill(['id' => 901, 'name' => 'QA NAVEGACIÓN', 'email' => 'qa@example.invalid']);
        // Evitar consultar tablas de Spatie ajenas al flujo de Transbordos.
        $user->setRelation('roles', new \Illuminate\Database\Eloquent\Collection());
        Auth::setUser($user);
        $html = $this->controller->edit(7, 32)->render();
        $this->assertStringContainsString('QA-CONT-51', $html);
        $this->assertStringContainsString('QA-CONT-52', $html);
        $this->assertStringContainsString('QA BARCAZA 1', $html);
        $this->assertStringContainsString('Guardar borrador', $html);
        $this->assertStringContainsString('Validar distribución guardada', $html);
        $this->assertStringNotContainsString('Enviar a ARCA', $html);
    }
}
