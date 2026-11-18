<?php

namespace Tests\Unit\Services\Parsers;

use App\Models\Voyage;
use App\Services\Parsers\Concerns\RecalculatesNewVoyageTotals;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RecalculatesNewVoyageTotalsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());

        Schema::create('voyages', function (Blueprint $table) {
            $table->id();
            $table->boolean('active');
            $table->decimal('total_cargo_weight', 12, 2)->nullable();
            $table->decimal('total_cargo_weight_loaded', 10, 2)->nullable();
            $table->integer('total_containers')->nullable();
            $table->integer('total_containers_loaded')->nullable();
            $table->decimal('capacity_utilization_percentage', 5, 2)->nullable();
            $table->timestamp('updated_at')->nullable();
        });
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('voyage_id');
            $table->boolean('active');
            $table->decimal('cargo_weight_loaded', 10, 2)->nullable();
            $table->integer('containers_loaded')->nullable();
            $table->decimal('utilization_percentage', 5, 2)->nullable();
            $table->timestamp('updated_at')->nullable();
        });
        Schema::create('bills_of_lading', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shipment_id');
            $table->decimal('gross_weight_kg', 12, 2);
            $table->softDeletes();
        });
        Schema::create('shipment_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bill_of_lading_id');
            $table->decimal('gross_weight_kg', 12, 2)->nullable();
        });
        Schema::create('container_shipment_item', function (Blueprint $table) {
            $table->unsignedBigInteger('container_id');
            $table->unsignedBigInteger('shipment_item_id');
            $table->decimal('weight_percentage', 5, 2)->nullable();
        });
    }

    public function test_recalculates_active_shipments_from_items_and_is_idempotent(): void
    {
        $timestamp = '2020-01-02 03:04:05';
        DB::table('voyages')->insert([
            ['id' => 1, 'active' => 1, 'total_cargo_weight' => 99, 'total_cargo_weight_loaded' => 99, 'total_containers' => 99, 'total_containers_loaded' => 99, 'capacity_utilization_percentage' => 42, 'updated_at' => $timestamp],
            ['id' => 2, 'active' => 1, 'total_cargo_weight' => 88, 'total_cargo_weight_loaded' => 88, 'total_containers' => 88, 'total_containers_loaded' => 88, 'capacity_utilization_percentage' => 43, 'updated_at' => $timestamp],
        ]);
        DB::table('shipments')->insert([
            ['id' => 1, 'voyage_id' => 1, 'active' => 1, 'cargo_weight_loaded' => 99, 'containers_loaded' => 99, 'utilization_percentage' => 31, 'updated_at' => $timestamp],
            ['id' => 2, 'voyage_id' => 1, 'active' => 1, 'cargo_weight_loaded' => 99, 'containers_loaded' => 99, 'utilization_percentage' => 32, 'updated_at' => $timestamp],
            ['id' => 3, 'voyage_id' => 1, 'active' => 0, 'cargo_weight_loaded' => 77, 'containers_loaded' => 77, 'utilization_percentage' => 33, 'updated_at' => $timestamp],
            ['id' => 4, 'voyage_id' => 2, 'active' => 1, 'cargo_weight_loaded' => 88, 'containers_loaded' => 88, 'utilization_percentage' => 34, 'updated_at' => $timestamp],
            ['id' => 5, 'voyage_id' => 1, 'active' => 1, 'cargo_weight_loaded' => null, 'containers_loaded' => null, 'utilization_percentage' => 35, 'updated_at' => $timestamp],
        ]);
        DB::table('bills_of_lading')->insert([
            ['id' => 1, 'shipment_id' => 1, 'gross_weight_kg' => 218500, 'deleted_at' => null],
            ['id' => 2, 'shipment_id' => 1, 'gross_weight_kg' => 123, 'deleted_at' => null],
            ['id' => 3, 'shipment_id' => 2, 'gross_weight_kg' => 456, 'deleted_at' => null],
            ['id' => 4, 'shipment_id' => 1, 'gross_weight_kg' => 999, 'deleted_at' => $timestamp],
            ['id' => 5, 'shipment_id' => 3, 'gross_weight_kg' => 999, 'deleted_at' => null],
            ['id' => 6, 'shipment_id' => 4, 'gross_weight_kg' => 999, 'deleted_at' => null],
        ]);
        DB::table('shipment_items')->insert([
            ['id' => 1, 'bill_of_lading_id' => 1, 'gross_weight_kg' => 0],
            ['id' => 2, 'bill_of_lading_id' => 2, 'gross_weight_kg' => 0.10],
            ['id' => 3, 'bill_of_lading_id' => 2, 'gross_weight_kg' => 0.20],
            ['id' => 4, 'bill_of_lading_id' => 3, 'gross_weight_kg' => 1.23],
            ['id' => 5, 'bill_of_lading_id' => 3, 'gross_weight_kg' => 0.01],
            ['id' => 6, 'bill_of_lading_id' => 4, 'gross_weight_kg' => 999],
            ['id' => 7, 'bill_of_lading_id' => 5, 'gross_weight_kg' => 999],
            ['id' => 8, 'bill_of_lading_id' => 6, 'gross_weight_kg' => 999],
        ]);
        DB::table('container_shipment_item')->insert([
            ['container_id' => 7, 'shipment_item_id' => 1, 'weight_percentage' => 10],
            ['container_id' => 7, 'shipment_item_id' => 2, 'weight_percentage' => 20],
            ['container_id' => 8, 'shipment_item_id' => 3, 'weight_percentage' => 30],
            ['container_id' => 7, 'shipment_item_id' => 4, 'weight_percentage' => 40],
            ['container_id' => 9, 'shipment_item_id' => 6, 'weight_percentage' => 50],
            ['container_id' => 10, 'shipment_item_id' => 7, 'weight_percentage' => 60],
            ['container_id' => 11, 'shipment_item_id' => 8, 'weight_percentage' => 70],
        ]);

        $before = $this->snapshot();
        $calculator = new class {
            use RecalculatesNewVoyageTotals;

            public function run(Voyage $voyage): void
            {
                $this->recalculateNewVoyageTotals($voyage);
            }
        };
        $voyage = new Voyage();
        $voyage->id = 1;
        $calculator->run($voyage);
        $after = $this->snapshot();

        $this->assertEquals(0.30, $after['shipments'][0]->cargo_weight_loaded);
        $this->assertSame(2, $after['shipments'][0]->containers_loaded);
        $this->assertEquals(1.24, $after['shipments'][1]->cargo_weight_loaded);
        $this->assertSame(1, $after['shipments'][1]->containers_loaded);
        $this->assertEquals(0, $after['shipments'][4]->cargo_weight_loaded);
        $this->assertSame(0, $after['shipments'][4]->containers_loaded);
        $this->assertEquals(1.54, $after['voyages'][0]->total_cargo_weight);
        $this->assertEquals(1.54, $after['voyages'][0]->total_cargo_weight_loaded);
        $this->assertSame(3, $after['voyages'][0]->total_containers);
        $this->assertSame(3, $after['voyages'][0]->total_containers_loaded);

        foreach (['total_cargo_weight', 'total_cargo_weight_loaded', 'total_containers', 'total_containers_loaded'] as $field) {
            $before['voyages'][0]->$field = $after['voyages'][0]->$field;
        }
        foreach ([0, 1, 4] as $index) {
            $before['shipments'][$index]->cargo_weight_loaded = $after['shipments'][$index]->cargo_weight_loaded;
            $before['shipments'][$index]->containers_loaded = $after['shipments'][$index]->containers_loaded;
        }
        $this->assertEquals($before, $after);

        $calculator->run($voyage);
        $this->assertEquals($after, $this->snapshot());
    }

    private function snapshot(): array
    {
        $snapshot = [];
        foreach (['voyages', 'shipments', 'bills_of_lading', 'shipment_items', 'container_shipment_item'] as $table) {
            $snapshot[$table] = DB::table($table)->orderBy($table === 'container_shipment_item' ? 'shipment_item_id' : 'id')->get()->all();
        }

        return $snapshot;
    }
}
