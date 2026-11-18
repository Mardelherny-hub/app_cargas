<?php

namespace Tests\Unit\Services;

use App\Models\ManifestImport;
use App\Models\User;
use App\Models\Voyage;
use App\Services\VoyageConvoyConversionService;
use DomainException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Esquema mínimo aislado en SQLite :memory:, con los eventos de Shipment
 * activos para que vessel_count se recalcule como en la app.
 */
class VoyageConvoyConversionServiceTest extends TestCase
{
    private const VOYAGE = 10;
    private const BARGE = 100;     // embarcación actual del viaje simple
    private const NEW_BARGE = 200; // embarcación del archivo
    private const TUG = 300;       // remolcador líder sin carga

    private VoyageConvoyConversionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'logging.default' => 'null',
            'audit.enabled' => false,
            'audit.drivers.database.connection' => null,
        ]);
        DB::purge('sqlite');
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());

        Schema::create('voyages', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('company_id'); $t->string('voyage_number');
            $t->string('status')->default('planning'); $t->unsignedBigInteger('lead_vessel_id')->nullable();
            $t->string('voyage_type')->default('single_vessel'); $t->boolean('is_convoy')->default(false);
            $t->integer('vessel_count')->default(1);
            foreach (['total_cargo_capacity_tons', 'total_container_capacity', 'total_cargo_weight_loaded',
                'total_containers_loaded', 'capacity_utilization_percentage', 'total_containers',
                'total_cargo_weight'] as $column) {
                $t->decimal($column, 12, 2)->default(0);
            }
            $t->boolean('has_dangerous_cargo')->default(false); $t->boolean('requires_special_handling')->default(false);
            $t->timestamp('last_updated_date')->nullable(); $t->timestamps();
        });
        Schema::create('shipments', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('voyage_id'); $t->unsignedBigInteger('vessel_id');
            $t->string('shipment_number'); $t->integer('sequence_in_voyage');
            $t->string('vessel_role'); $t->boolean('is_lead_vessel')->default(false);
            $t->integer('convoy_position')->nullable(); $t->decimal('cargo_capacity_tons', 10, 2)->nullable();
            $t->integer('container_capacity')->default(0); $t->string('status')->default('planning');
            $t->unsignedBigInteger('created_by_user_id')->nullable(); $t->timestamps();
            $t->unique(['voyage_id', 'vessel_id']); $t->unique(['voyage_id', 'sequence_in_voyage']);
        });
        Schema::create('bills_of_lading', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('shipment_id'); $t->string('bill_number'); $t->softDeletes();
        });
        Schema::create('manifest_imports', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('company_id'); $t->unsignedBigInteger('voyage_id')->nullable();
            foreach (['voyage', 'shipment', 'bill', 'item', 'container', 'client', 'port'] as $type) {
                $t->json("created_{$type}_ids")->nullable(); $t->integer("created_{$type}s")->default(0);
            }
            $t->json('parser_config')->nullable(); $t->timestamps();
        });

        DB::table('voyages')->insert([
            'id' => self::VOYAGE, 'company_id' => 1, 'voyage_number' => 'V-QA', 'lead_vessel_id' => self::BARGE,
        ]);
        DB::table('shipments')->insert([
            'id' => 1, 'voyage_id' => self::VOYAGE, 'vessel_id' => self::BARGE, 'shipment_number' => 'S-1',
            'sequence_in_voyage' => 1, 'vessel_role' => 'single', 'is_lead_vessel' => true, 'convoy_position' => 3,
        ]);

        $this->service = new VoyageConvoyConversionService();
    }

    private function voyage(): Voyage
    {
        return Voyage::findOrFail(self::VOYAGE);
    }

    private function state(): array
    {
        return [
            'voyage' => (array) DB::table('voyages')->where('id', self::VOYAGE)
                ->first(['voyage_type', 'is_convoy', 'lead_vessel_id', 'vessel_count']),
            'shipments' => DB::table('shipments')->orderBy('id')
                ->get(['id', 'vessel_id', 'vessel_role', 'is_lead_vessel', 'convoy_position', 'sequence_in_voyage'])
                ->map(fn ($row) => (array) $row)->all(),
        ];
    }

    public function test_leader_without_cargo_gets_its_own_shipment_and_current_vessel_is_towed(): void
    {
        $snapshot = $this->service->convert($this->voyage(), self::TUG, self::NEW_BARGE);

        $voyage = $this->voyage();
        $this->assertSame('convoy', $voyage->voyage_type);
        $this->assertTrue($voyage->is_convoy);
        $this->assertSame(self::TUG, (int) $voyage->lead_vessel_id);
        $this->assertSame(2, (int) $voyage->vessel_count);

        $this->assertSame(['towed', 0], [
            DB::table('shipments')->where('id', 1)->value('vessel_role'),
            (int) DB::table('shipments')->where('id', 1)->value('is_lead_vessel'),
        ]);
        $leader = DB::table('shipments')->where('vessel_id', self::TUG)->first();
        $this->assertSame(['lead', 1, 2, 'SHP-V-QA-002'],
            [$leader->vessel_role, (int) $leader->is_lead_vessel, (int) $leader->sequence_in_voyage, $leader->shipment_number]);

        $this->assertSame([
            'voyage_id' => self::VOYAGE,
            'voyage' => ['voyage_type' => 'single_vessel', 'is_convoy' => false, 'lead_vessel_id' => self::BARGE],
            'shipments' => [1 => ['vessel_role' => 'single', 'is_lead_vessel' => true]],
            'created_shipment_id' => (int) $leader->id,
        ], $snapshot);
    }

    public function test_restore_leaves_voyage_and_existing_shipments_exactly_as_before(): void
    {
        $before = $this->state();

        $snapshot = $this->service->convert($this->voyage(), self::TUG, self::NEW_BARGE);
        $this->assertNotSame($before, $this->state());

        // Lo que la importación crea se elimina en la reversión antes de restaurar.
        $snapshot = json_decode(json_encode($snapshot), true);
        $this->service->restore($snapshot);

        $this->assertSame($before, $this->state());
    }

    public function test_existing_vessel_as_leader_only_changes_its_role(): void
    {
        $before = $this->state();
        $snapshot = $this->service->convert($this->voyage(), self::BARGE, self::NEW_BARGE);

        $this->assertSame([1 => ['vessel_role' => 'single']], $snapshot['shipments']);
        $this->assertNull($snapshot['created_shipment_id']);
        $this->assertSame('lead', DB::table('shipments')->where('id', 1)->value('vessel_role'));
        $this->assertSame(1, DB::table('shipments')->count());

        $this->service->restore(json_decode(json_encode($snapshot), true));
        $this->assertSame($before, $this->state());
    }

    public function test_file_vessel_as_leader_leaves_its_shipment_to_the_parser(): void
    {
        $snapshot = $this->service->convert($this->voyage(), self::NEW_BARGE, self::NEW_BARGE);

        $this->assertNull($snapshot['created_shipment_id']);
        $this->assertSame(1, DB::table('shipments')->count());
        $this->assertSame(self::NEW_BARGE, (int) $this->voyage()->lead_vessel_id);
        $this->assertSame('towed', DB::table('shipments')->where('id', 1)->value('vessel_role'));
    }

    public function test_snapshot_is_stored_in_the_import_and_leader_shipment_counts_as_created(): void
    {
        $import = ManifestImport::forceCreate([
            'company_id' => 1, 'voyage_id' => self::VOYAGE,
            'created_shipment_ids' => [5], 'parser_config' => ['target_voyage' => ['voyage_id' => self::VOYAGE]],
        ]);

        $snapshot = $this->service->convert($this->voyage(), self::TUG, self::NEW_BARGE);
        $this->service->attachToImport($import, $snapshot);

        $import->refresh();
        $this->assertSame([5, $snapshot['created_shipment_id']], $import->created_shipment_ids);
        $this->assertSame(['voyage_id' => self::VOYAGE], $import->parser_config['target_voyage']);
        $this->assertSame($snapshot['voyage'], $import->parser_config['convoy_conversion']['voyage']);
    }

    public function test_leader_shipment_records_the_user_who_converts(): void
    {
        $user = new User();
        $user->id = 77;
        $this->actingAs($user);

        $snapshot = $this->service->convert($this->voyage(), self::TUG, self::NEW_BARGE);

        $this->assertSame(77, (int) DB::table('shipments')
            ->where('id', $snapshot['created_shipment_id'])->value('created_by_user_id'));
        $this->assertNull(DB::table('shipments')->where('id', 1)->value('created_by_user_id'));
    }

    public function test_convoy_cannot_be_converted_again(): void
    {
        DB::table('voyages')->update(['is_convoy' => true, 'voyage_type' => 'convoy']);

        $this->expectException(DomainException::class);
        $this->service->convert($this->voyage(), self::TUG, self::NEW_BARGE);
    }

    public function test_restore_refuses_when_leader_shipment_received_bills(): void
    {
        $snapshot = $this->service->convert($this->voyage(), self::TUG, self::NEW_BARGE);
        DB::table('bills_of_lading')->insert(['shipment_id' => $snapshot['created_shipment_id'], 'bill_number' => 'BL-1']);

        try {
            $this->service->restore($snapshot);
            $this->fail('Se esperaba DomainException');
        } catch (DomainException) {
            $this->assertSame('convoy', $this->voyage()->voyage_type);
            $this->assertSame('towed', DB::table('shipments')->where('id', 1)->value('vessel_role'));
        }
    }
}
