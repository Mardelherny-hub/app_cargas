<?php

namespace Tests\Unit\Services\Webservice;

use App\Models\Voyage;
use App\Services\Simple\ArgentinaMicDtaService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionClass;
use Tests\TestCase;

/**
 * El indicador de tránsito/transbordo por sí solo no bloquea el MIC/DTA
 * convencional: solo lo bloquea un reparto entre barcazas guardado.
 */
class HidroviaTransshipmentConventionalGateTest extends TestCase
{
    private string $previousConnection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->previousConnection = config('database.default');
        config([
            'database.default' => 'transshipment_gate_sqlite',
            'database.connections.transshipment_gate_sqlite' => [
                'driver' => 'sqlite', 'database' => ':memory:',
                'prefix' => '', 'foreign_key_constraints' => false,
            ],
        ]);
        $schema = Schema::connection('transshipment_gate_sqlite');
        $schema->create('voyages', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('company_id'); $t->string('voyage_number');
            $t->timestamps(); $t->softDeletes();
        });
        $schema->create('shipments', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('voyage_id'); $t->unsignedBigInteger('vessel_id');
        });
        $schema->create('bills_of_lading', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('shipment_id'); $t->string('bill_number');
            $t->string('is_transit_transshipment'); $t->softDeletes();
        });
        $schema->create('hidrovia_transshipment_allocations', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('bill_of_lading_id');
        });

        DB::table('voyages')->insert([
            ['id' => 1, 'company_id' => 7, 'voyage_number' => 'SIMPLE-SIN-MARCA'],
            ['id' => 2, 'company_id' => 7, 'voyage_number' => 'SIMPLE-MARCADO'],
            ['id' => 3, 'company_id' => 7, 'voyage_number' => 'CONVOY-MARCADO'],
            ['id' => 4, 'company_id' => 7, 'voyage_number' => 'CONVOY-REPARTIDO'],
        ]);
        DB::table('shipments')->insert([
            ['id' => 10, 'voyage_id' => 1, 'vessel_id' => 1],
            ['id' => 20, 'voyage_id' => 2, 'vessel_id' => 1],
            ['id' => 30, 'voyage_id' => 3, 'vessel_id' => 2],
            ['id' => 31, 'voyage_id' => 3, 'vessel_id' => 3],
            ['id' => 40, 'voyage_id' => 4, 'vessel_id' => 2],
            ['id' => 41, 'voyage_id' => 4, 'vessel_id' => 3],
        ]);
        DB::table('bills_of_lading')->insert([
            ['id' => 100, 'shipment_id' => 10, 'bill_number' => 'BL-N', 'is_transit_transshipment' => 'N'],
            ['id' => 200, 'shipment_id' => 20, 'bill_number' => 'BL-S', 'is_transit_transshipment' => 'S'],
            ['id' => 300, 'shipment_id' => 30, 'bill_number' => 'BL-S1', 'is_transit_transshipment' => 'S'],
            ['id' => 301, 'shipment_id' => 31, 'bill_number' => 'BL-S2', 'is_transit_transshipment' => 'S'],
            ['id' => 400, 'shipment_id' => 40, 'bill_number' => 'BL-REP', 'is_transit_transshipment' => 'S'],
            ['id' => 401, 'shipment_id' => 41, 'bill_number' => 'BL-SOLO', 'is_transit_transshipment' => 'S'],
        ]);
        DB::table('hidrovia_transshipment_allocations')->insert([
            ['id' => 1, 'bill_of_lading_id' => 400],
        ]);
    }

    protected function tearDown(): void
    {
        config(['database.default' => $this->previousConnection]);
        parent::tearDown();
    }

    public function test_voyage_without_transit_flag_is_not_blocked(): void
    {
        $this->assertFalse($this->requiresTransshipmentFlow(1));
    }

    public function test_single_vessel_voyage_with_transit_flag_is_not_blocked(): void
    {
        $this->assertFalse($this->requiresTransshipmentFlow(2));
    }

    public function test_convoy_with_transit_flag_and_no_distribution_is_not_blocked(): void
    {
        $this->assertFalse($this->requiresTransshipmentFlow(3));
    }

    public function test_convoy_with_saved_distribution_is_blocked(): void
    {
        $this->assertTrue($this->requiresTransshipmentFlow(4));
    }

    private function requiresTransshipmentFlow(int $voyageId): bool
    {
        $voyage = Voyage::withoutGlobalScopes()->findOrFail($voyageId);
        $service = (new ReflectionClass(ArgentinaMicDtaService::class))->newInstanceWithoutConstructor();
        $method = new \ReflectionMethod(ArgentinaMicDtaService::class, 'requiresHidroviaTransshipmentFlow');
        $method->setAccessible(true);

        return $method->invoke($service, $voyage);
    }
}
