<?php

namespace Tests\Unit\Jobs;

use App\Jobs\ProcessManifestImportJob;
use App\Models\Voyage;
use App\ValueObjects\ManifestParseResult;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use ReflectionProperty;
use Tests\TestCase;

/**
 * Esquema mínimo aislado en SQLite :memory:. Con viaje destino, los ajustes
 * posteriores al parseo no tocan el viaje ni los conocimientos existentes.
 */
class ProcessManifestImportJobTargetVoyageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'logging.default' => 'null',
            'audit.enabled' => false,
        ]);
        DB::purge('sqlite');
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());

        Schema::create('voyages', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('company_id'); $t->string('voyage_number');
            $t->date('departure_date')->nullable(); $t->date('estimated_arrival_date')->nullable(); $t->timestamps();
        });
        Schema::create('shipments', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('voyage_id');
        });
        Schema::create('bills_of_lading', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('shipment_id'); $t->string('bill_number');
            $t->string('is_transit_transshipment', 1)->default('N');
            $t->date('loading_date')->nullable(); $t->timestamps(); $t->softDeletes();
        });
        Schema::create('shipment_items', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('bill_of_lading_id');
        });
        Schema::create('manifest_imports', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('voyage_id')->nullable(); $t->json('created_bill_ids')->nullable();
            $t->timestamps();
        });

        DB::table('voyages')->insert(['id' => 1, 'company_id' => 1, 'voyage_number' => 'V-QA', 'departure_date' => '2026-11-01']);
        DB::table('shipments')->insert([['id' => 1, 'voyage_id' => 1], ['id' => 2, 'voyage_id' => 1]]);
        DB::table('bills_of_lading')->insert([
            ['id' => 1, 'shipment_id' => 1, 'bill_number' => 'BL-EXISTENTE'],
            ['id' => 2, 'shipment_id' => 2, 'bill_number' => 'BL-AGREGADO'],
        ]);
        DB::table('manifest_imports')->insert([
            ['id' => 1, 'voyage_id' => 1, 'created_bill_ids' => json_encode([1])],
            ['id' => 2, 'voyage_id' => 1, 'created_bill_ids' => json_encode([2])],
        ]);
    }

    private function job(?int $targetVoyageId, ?string $departureDate = null, ?string $loadingDate = null): ProcessManifestImportJob
    {
        return new ProcessManifestImportJob(1, 'imports/manifests/qa.txt', 100, 1, 'qa.txt',
            $departureDate, null, $loadingDate, null, null, $targetVoyageId);
    }

    private function applyStep(ProcessManifestImportJob $job, string $method, ?int $targetVoyageId): void
    {
        $result = ManifestParseResult::success(voyage: Voyage::findOrFail(1));

        if ($targetVoyageId !== null) {
            $ids = (new ReflectionMethod($job, 'targetImportBillIds'))->invoke($job, $result);
            (new ReflectionProperty($job, 'scopedBillIds'))->setValue($job, $ids);
        }

        $arguments = $method === 'applyOperationalImportDates' ? [new \stdClass(), $result] : [$result];
        (new ReflectionMethod($job, $method))->invoke($job, ...$arguments);
    }

    public function test_target_voyage_option_is_carried_by_the_job(): void
    {
        $this->assertSame(10, $this->job(10)->targetVoyageId);
        $this->assertNull($this->job(null)->targetVoyageId);
    }

    public function test_target_scope_is_the_bills_created_by_the_latest_import(): void
    {
        $job = $this->job(1);
        $ids = (new ReflectionMethod($job, 'targetImportBillIds'))
            ->invoke($job, ManifestParseResult::success(voyage: Voyage::findOrFail(1)));

        $this->assertSame([2], $ids);
    }

    public function test_without_target_transit_flag_keeps_applying_to_the_whole_voyage(): void
    {
        $this->applyStep($this->job(null), 'applyTransitTransshipment', null);

        $this->assertSame(['S', 'S'], DB::table('bills_of_lading')->orderBy('id')->pluck('is_transit_transshipment')->all());
    }

    public function test_with_target_transit_flag_only_touches_added_bills(): void
    {
        $this->applyStep($this->job(1), 'applyTransitTransshipment', 1);

        $this->assertSame(['N', 'S'], DB::table('bills_of_lading')->orderBy('id')->pluck('is_transit_transshipment')->all());
    }

    public function test_with_target_voyage_dates_are_untouched_and_bill_dates_are_scoped(): void
    {
        $this->applyStep($this->job(1, '2026-12-01', '2026-11-20'), 'applyOperationalImportDates', 1);

        $this->assertSame('2026-11-01', (string) DB::table('voyages')->value('departure_date'));
        $this->assertNull(DB::table('bills_of_lading')->where('id', 1)->value('loading_date'));
        $this->assertStringStartsWith('2026-11-20', (string) DB::table('bills_of_lading')->where('id', 2)->value('loading_date'));
    }

    public function test_without_target_departure_date_still_overrides_the_voyage(): void
    {
        $this->applyStep($this->job(null, '2026-12-01'), 'applyOperationalImportDates', null);

        $this->assertStringStartsWith('2026-12-01', (string) DB::table('voyages')->value('departure_date'));
    }
}
