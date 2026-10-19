<?php

namespace Tests\Unit\Services\Webservice;

use App\Models\BillOfLading;
use App\Models\Voyage;
use App\Services\Simple\HidroviaTransshipmentDistributionValidator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/** Todas las consultas se ejecutan contra SQLite en memoria, sin datos reales. */
class HidroviaTransshipmentDistributionValidatorTest extends TestCase
{
    private string $previousConnection;
    private Voyage $voyage;
    private BillOfLading $bill;
    private HidroviaTransshipmentDistributionValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->previousConnection = config('database.default');
        config([
            'database.default' => 'transshipment_validator_sqlite',
            'database.connections.transshipment_validator_sqlite' => [
                'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
                'foreign_key_constraints' => false,
            ],
        ]);
        $schema = Schema::connection('transshipment_validator_sqlite');
        $schema->create('shipments', function ($t) {
            $t->id(); $t->unsignedBigInteger('voyage_id');
        });
        $schema->create('bills_of_lading', function ($t) {
            $t->id(); $t->unsignedBigInteger('shipment_id'); $t->softDeletes();
        });
        $schema->create('shipment_items', function ($t) {
            $t->id(); $t->unsignedBigInteger('bill_of_lading_id');
            $t->integer('package_quantity'); $t->decimal('gross_weight_kg', 12, 2);
        });
        $schema->create('containers', function ($t) {
            $t->id();
        });
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
        DB::table('shipments')->insert([
            ['id' => 11, 'voyage_id' => 7],
            ['id' => 12, 'voyage_id' => 7],
            ['id' => 99, 'voyage_id' => 8],
        ]);
        DB::table('bills_of_lading')->insert([
            ['id' => 31, 'shipment_id' => 11],
            ['id' => 32, 'shipment_id' => 99],
        ]);
        DB::table('shipment_items')->insert([
            ['id' => 41, 'bill_of_lading_id' => 31, 'package_quantity' => 10, 'gross_weight_kg' => '100.25'],
            ['id' => 42, 'bill_of_lading_id' => 31, 'package_quantity' => 2, 'gross_weight_kg' => '20.50'],
            ['id' => 43, 'bill_of_lading_id' => 32, 'package_quantity' => 2, 'gross_weight_kg' => '10.00'],
        ]);
        DB::table('containers')->insert([['id' => 51], ['id' => 52]]);
        DB::table('container_shipment_item')->insert([
            ['shipment_item_id' => 41, 'container_id' => 51],
            ['shipment_item_id' => 41, 'container_id' => 52],
        ]);
        $this->voyage = (new Voyage())->forceFill(['id' => 7]);
        $this->bill = BillOfLading::findOrFail(31);
        $this->validator = new HidroviaTransshipmentDistributionValidator();
    }

    protected function tearDown(): void
    {
        DB::purge('transshipment_validator_sqlite');
        config(['database.default' => $this->previousConnection]);
        parent::tearDown();
    }

    private function row(int $shipment, int $item, int $packages, string $weight, ?int $container = null): array
    {
        return ['shipment_id' => $shipment, 'shipment_item_id' => $item,
            'package_quantity' => $packages, 'gross_weight_kg' => $weight, 'container_id' => $container];
    }

    private function reject(array $rows, string $message, bool $complete = false): void
    {
        try {
            $this->validator->validate($this->voyage, $this->bill, $rows, $complete);
            $this->fail('La distribución inválida fue aceptada.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString($message, $exception->errors()['distribution'][0]);
        }
    }

    public function test_valid_distribution_across_two_barges_preserves_exact_weight_and_quantities(): void
    {
        $rows = [$this->row(11, 41, 4, '40.10', 51), $this->row(12, 41, 6, '60.15', 52),
            $this->row(12, 42, 2, '20.50')];
        $this->validator->validate($this->voyage, $this->bill, $rows, true);
        $this->assertCount(3, $rows);
    }

    public function test_foreign_shipment_is_rejected(): void
    {
        $this->reject([$this->row(99, 41, 1, '1.00')], 'no pertenece al viaje');
    }

    public function test_foreign_bill_and_line_are_rejected(): void
    {
        $this->reject([$this->row(11, 43, 1, '1.00')], 'no pertenece al conocimiento');
        $this->bill = BillOfLading::findOrFail(32);
        $this->reject([], 'no pertenece al viaje');
    }

    public function test_duplicate_assignment_is_rejected(): void
    {
        $this->reject([$this->row(11, 41, 1, '1.00'), $this->row(11, 41, 2, '2.00')], 'duplicados');
    }

    public function test_container_cannot_be_placed_on_two_barges_or_wrong_line(): void
    {
        $this->reject([$this->row(11, 41, 4, '40.10', 51), $this->row(12, 41, 6, '60.15', 51)],
            'dos embarcaciones');
        $this->reject([$this->row(11, 42, 1, '1.00', 51)], 'no pertenece a esa línea');
    }

    public function test_oversubscription_and_incomplete_distribution_are_rejected(): void
    {
        $this->reject([$this->row(11, 41, 11, '100.25')], 'supera');
        $this->reject([$this->row(11, 41, 10, '100.26')], 'supera');
        $this->reject([
            $this->row(11, 41, 4, '40.10', 51),
            $this->row(12, 41, 6, '60.15', 52),
        ], 'completar exactamente', true);
    }

    public function test_complete_containerized_distribution_requires_each_linked_container_once(): void
    {
        $this->reject([
            $this->row(11, 41, 10, '100.25'),
            $this->row(12, 42, 2, '20.50'),
        ], 'contenedor real', true);
        $this->reject([
            $this->row(11, 41, 10, '100.25', 51),
            $this->row(12, 42, 2, '20.50'),
        ], 'todos sus contenedores reales', true);
    }

    public function test_invalid_precision_and_negative_quantity_are_rejected(): void
    {
        $this->reject([$this->row(11, 41, -1, '1.00')], 'cantidad');
        $this->reject([$this->row(11, 41, 1, '1.999')], 'dos decimales');
        $this->reject([$this->row(11, 41, 0, '0.00')], 'sin mercadería ni contenedor');
        $this->reject([$this->row(11, 41, 1, '10000000000.00')], 'fuera de rango');
    }
}
