<?php

namespace Tests\Unit\Services\Webservice;

use App\Models\BillOfLading;
use App\Models\HidroviaTransshipmentAllocation;
use App\Models\Voyage;
use App\Services\Simple\HidroviaTransshipmentDistributionValidator;
use App\Services\Simple\HidroviaTransshipmentDraftService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class HidroviaTransshipmentDraftServiceTest extends TestCase
{
    private string $previousConnection;
    private Voyage $voyage;
    private BillOfLading $bill;
    private HidroviaTransshipmentDraftService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->previousConnection = config('database.default');
        config([
            'database.default' => 'transshipment_draft_sqlite',
            'database.connections.transshipment_draft_sqlite' => [
                'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
                'foreign_key_constraints' => false,
            ],
        ]);
        $schema = Schema::connection('transshipment_draft_sqlite');
        $schema->create('users', function ($t) { $t->id(); });
        $schema->create('shipments', function ($t) {
            $t->id(); $t->unsignedBigInteger('voyage_id');
        });
        $schema->create('bills_of_lading', function ($t) {
            $t->id(); $t->unsignedBigInteger('shipment_id'); $t->softDeletes();
            $t->string('status'); $t->string('is_transit_transshipment');
            $t->timestamp('verified_at')->nullable();
            $t->timestamp('argentina_sent_at')->nullable();
            $t->timestamp('paraguay_sent_at')->nullable();
        });
        $schema->create('shipment_items', function ($t) {
            $t->id(); $t->unsignedBigInteger('bill_of_lading_id');
            $t->integer('package_quantity'); $t->decimal('gross_weight_kg', 12, 2);
        });
        $schema->create('containers', function ($t) { $t->id(); });
        $schema->create('container_shipment_item', function ($t) {
            $t->id(); $t->unsignedBigInteger('shipment_item_id'); $t->unsignedBigInteger('container_id');
            foreach (['package_quantity', 'gross_weight_kg', 'net_weight_kg', 'volume_m3', 'quantity_percentage',
                'weight_percentage', 'volume_percentage', 'verified_gross_mass_kg'] as $name) {
                $t->decimal($name)->nullable();
            }
            foreach (['loaded_at', 'sealed_at', 'loading_sequence', 'source_seals',
                'source_line_numbers', 'container_condition', 'status'] as $name) {
                $t->string($name)->nullable();
            }
            $t->timestamps();
        });
        // Probar el esquema de producción definido en la migración real.
        (require database_path('migrations/2026_10_19_123000_create_hidrovia_transshipment_allocations_table.php'))->up();
        DB::table('shipments')->insert([
            ['id' => 11, 'voyage_id' => 7], ['id' => 12, 'voyage_id' => 7],
            ['id' => 99, 'voyage_id' => 8],
        ]);
        DB::table('bills_of_lading')->insert([
            'id' => 31, 'shipment_id' => 11, 'status' => 'draft', 'is_transit_transshipment' => 'S',
        ]);
        DB::table('shipment_items')->insert([
            'id' => 41, 'bill_of_lading_id' => 31, 'package_quantity' => 10,
            'gross_weight_kg' => '100.25',
        ]);
        $this->voyage = (new Voyage())->forceFill(['id' => 7, 'company_id' => 101]);
        $this->bill = BillOfLading::findOrFail(31);
        $this->service = new HidroviaTransshipmentDraftService(new HidroviaTransshipmentDistributionValidator());
    }

    protected function tearDown(): void
    {
        DB::purge('transshipment_draft_sqlite');
        config(['database.default' => $this->previousConnection]);
        parent::tearDown();
    }

    private function row(int $shipmentId, int $quantity, string $weight): array
    {
        return ['shipment_id' => $shipmentId, 'shipment_item_id' => 41,
            'package_quantity' => $quantity, 'gross_weight_kg' => $weight];
    }

    private function reject(array $rows, string $message, int $companyId = 101): void
    {
        try {
            $this->service->replaceDraft($this->voyage, $companyId, $this->bill, $rows);
            $this->fail('Se aceptó un reemplazo inválido.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString($message, $exception->errors()['distribution'][0]);
        }
    }

    public function test_saves_one_title_across_two_barges_without_creating_other_bills(): void
    {
        $result = $this->service->replaceDraft($this->voyage, 101, $this->bill,
            [$this->row(11, 4, '40.10'), $this->row(12, 6, '60.15')]);
        $this->assertCount(2, $result);
        $this->assertSame([11, 12], $result->pluck('target_shipment_id')->all());
        $this->assertSame(['40.10', '60.15'], $result->pluck('gross_weight_kg')->all());
        $this->assertSame(1, BillOfLading::count());
        $this->assertSame(1, DB::table('shipment_items')->count());
    }

    public function test_replace_is_atomic_and_uses_latest_total(): void
    {
        $this->service->replaceDraft($this->voyage, 101, $this->bill,
            [$this->row(11, 4, '40.10'), $this->row(12, 6, '60.15')]);
        $this->reject([$this->row(11, 11, '100.25')], 'supera');
        $this->assertSame(2, HidroviaTransshipmentAllocation::count());
        $this->service->replaceDraft($this->voyage, 101, $this->bill, [$this->row(12, 10, '100.25')]);
        $this->assertSame(1, HidroviaTransshipmentAllocation::count());
        $this->assertSame(12, HidroviaTransshipmentAllocation::first()->target_shipment_id);
    }

    public function test_rejects_other_company_and_noneditable_or_nontransshipment_bills(): void
    {
        $this->reject([$this->row(11, 10, '100.25')], 'no pertenece', 999);
        DB::table('bills_of_lading')->where('id', 31)->update(['status' => 'verified']);
        $this->reject([$this->row(11, 10, '100.25')], 'borrador editable');
        DB::table('bills_of_lading')->where('id', 31)->update([
            'status' => 'draft', 'is_transit_transshipment' => 'N',
        ]);
        $this->reject([$this->row(11, 10, '100.25')], 'no está identificado');
    }

    public function test_never_transmits_or_modifies_original_title_when_draft_is_saved(): void
    {
        $this->service->replaceDraft($this->voyage, 101, $this->bill,
            [$this->row(11, 4, '40.10')]);
        $this->assertSame('draft', BillOfLading::findOrFail(31)->status);
        $this->assertSame('S', BillOfLading::findOrFail(31)->is_transit_transshipment);
        $this->assertSame(11, (int) BillOfLading::findOrFail(31)->shipment_id);
    }
}
