<?php

namespace Tests\Unit\Services;

use App\Models\ManifestImport;
use App\Models\Voyage;
use App\Services\ManifestImportRevertService;
use App\Services\VoyageConvoyConversionService;
use DomainException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Esquema mínimo aislado en SQLite :memory:, con los eventos de Shipment
 * activos. Nada se consulta ni se envía a AFIP.
 */
class ManifestImportRevertServiceTest extends TestCase
{
    private const VOYAGE = 10;
    private const BARGE = 100;     // embarcación del viaje importado
    private const NEW_BARGE = 200; // embarcación agregada desde otro archivo
    private const TUG = 300;       // líder del convoy, sin carga

    private ManifestImportRevertService $service;

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

        Schema::create('companies', function (Blueprint $t) {
            $t->id(); $t->string('ws_environment')->nullable(); $t->timestamps();
        });
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
            $t->timestamps();
            $t->unique(['voyage_id', 'vessel_id']); $t->unique(['voyage_id', 'sequence_in_voyage']);
        });
        Schema::create('bills_of_lading', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('shipment_id'); $t->string('bill_number'); $t->timestamps(); $t->softDeletes();
        });
        Schema::create('shipment_items', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('bill_of_lading_id');
        });
        Schema::create('containers', function (Blueprint $t) {
            $t->id(); $t->string('container_number');
        });
        Schema::create('container_shipment_item', function (Blueprint $t) {
            $t->unsignedBigInteger('container_id'); $t->unsignedBigInteger('shipment_item_id');
        });
        Schema::create('webservice_transactions', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('company_id');
            $t->unsignedBigInteger('voyage_id')->nullable(); $t->unsignedBigInteger('shipment_id')->nullable();
            $t->string('soap_action')->nullable(); $t->string('country', 2)->default('AR');
            $t->string('environment')->default('testing'); $t->string('status')->default('pending');
            $t->string('external_reference')->nullable(); $t->longText('request_xml')->nullable();
            $t->json('success_data')->nullable(); $t->json('additional_metadata')->nullable();
        });
        Schema::create('manifest_imports', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('company_id'); $t->unsignedBigInteger('voyage_id')->nullable();
            $t->string('status')->default('completed'); $t->boolean('can_be_reverted')->default(true);
            $t->string('revert_blocked_reason')->nullable(); $t->timestamp('reverted_at')->nullable();
            $t->unsignedBigInteger('reverted_by_user_id')->nullable(); $t->json('revert_details')->nullable();
            foreach (['voyage', 'shipment', 'bill', 'item', 'container', 'client', 'port'] as $type) {
                $t->json("created_{$type}_ids")->nullable(); $t->integer("created_{$type}s")->default(0);
            }
            $t->json('parser_config')->nullable(); $t->timestamps();
        });

        DB::table('companies')->insert(['id' => 1, 'ws_environment' => 'testing']);

        $this->service = app(ManifestImportRevertService::class);
    }

    /** Importación que crea el viaje simple con un conocimiento. */
    private function originImport(): ManifestImport
    {
        DB::table('voyages')->insert([
            'id' => self::VOYAGE, 'company_id' => 1, 'voyage_number' => 'V-QA', 'lead_vessel_id' => self::BARGE,
        ]);
        DB::table('shipments')->insert([
            'id' => 1, 'voyage_id' => self::VOYAGE, 'vessel_id' => self::BARGE, 'shipment_number' => 'SHP-V-QA-001',
            'sequence_in_voyage' => 1, 'vessel_role' => 'single', 'is_lead_vessel' => true,
        ]);
        DB::table('bills_of_lading')->insert(['id' => 1, 'shipment_id' => 1, 'bill_number' => 'BL-ORIGEN']);

        $import = ManifestImport::forceCreate(['company_id' => 1, 'voyage_id' => self::VOYAGE]);
        $import->recordExplicitlyCreatedObjects([
            'voyage' => [self::VOYAGE], 'shipment' => [1], 'bill' => [1], 'item' => [], 'container' => [],
        ]);

        return $import->fresh();
    }

    /**
     * Importación agregada. Con $leader, convierte el viaje en convoy como
     * lo hace el job antes del parseo.
     */
    private function addedImport(int $vesselId, string $billNumber, ?int $leader = null, ?int $reusedContainerId = null): ManifestImport
    {
        $conversion = app(VoyageConvoyConversionService::class);
        $snapshot = $leader !== null ? $conversion->convert(Voyage::findOrFail(self::VOYAGE), $leader, $vesselId) : null;

        $shipmentId = DB::table('shipments')->where('voyage_id', self::VOYAGE)->where('vessel_id', $vesselId)->value('id');
        $createdShipments = [];
        if (!$shipmentId) {
            $sequence = (int) DB::table('shipments')->where('voyage_id', self::VOYAGE)->max('sequence_in_voyage') + 1;
            // Como el parser: con el modelo, para que se recalcule vessel_count.
            $shipmentId = \App\Models\Shipment::create([
                'voyage_id' => self::VOYAGE, 'vessel_id' => $vesselId, 'shipment_number' => "SHP-V-QA-00{$sequence}",
                'sequence_in_voyage' => $sequence, 'vessel_role' => 'towed', 'is_lead_vessel' => false,
                'cargo_capacity_tons' => null,
            ])->id;
            $createdShipments[] = $shipmentId;
        }
        $billId = DB::table('bills_of_lading')->insertGetId(['shipment_id' => $shipmentId, 'bill_number' => $billNumber]);
        $itemId = DB::table('shipment_items')->insertGetId(['bill_of_lading_id' => $billId]);
        // Con $reusedContainerId, el parser reutiliza un contenedor existente por número.
        $containerId = $reusedContainerId
            ?? DB::table('containers')->insertGetId(['container_number' => "CONT-{$billNumber}"]);
        DB::table('container_shipment_item')->insert(['container_id' => $containerId, 'shipment_item_id' => $itemId]);

        $import = ManifestImport::forceCreate([
            'company_id' => 1, 'voyage_id' => self::VOYAGE,
            'parser_config' => ['target_voyage' => ['voyage_id' => self::VOYAGE]],
        ]);
        $import->recordExplicitlyCreatedObjects([
            'voyage' => [], 'shipment' => $createdShipments, 'bill' => [$billId], 'item' => [$itemId], 'container' => [$containerId],
        ]);
        if ($snapshot !== null) {
            $conversion->attachToImport($import, $snapshot);
        }

        return $import->fresh();
    }

    private function state(): array
    {
        return [
            'voyage' => (array) DB::table('voyages')->where('id', self::VOYAGE)
                ->first(['voyage_type', 'is_convoy', 'lead_vessel_id', 'vessel_count']),
            'shipments' => DB::table('shipments')->orderBy('id')
                ->get(['id', 'vessel_id', 'vessel_role', 'is_lead_vessel', 'convoy_position', 'sequence_in_voyage'])
                ->map(fn ($row) => (array) $row)->all(),
            'bills' => DB::table('bills_of_lading')->whereNull('deleted_at')->orderBy('id')->pluck('bill_number')->all(),
            'items' => DB::table('shipment_items')->count(),
            'containers' => DB::table('containers')->count(),
            'pivot' => DB::table('container_shipment_item')->count(),
        ];
    }

    private function revert(ManifestImport $import): array
    {
        return $this->service->revertImport($import->fresh(), 1, 'QA', 'Prueba');
    }

    public function test_reverting_an_import_that_converted_to_convoy_leaves_the_voyage_exactly_as_before(): void
    {
        $this->originImport();
        $before = $this->state();

        $added = $this->addedImport(self::NEW_BARGE, 'BL-AGREGADO', self::TUG);
        $converted = $this->state();
        $this->assertSame('convoy', $converted['voyage']['voyage_type']);
        $this->assertSame(self::TUG, (int) $converted['voyage']['lead_vessel_id']);
        $this->assertSame(3, (int) $converted['voyage']['vessel_count']);

        $summary = $this->revert($added);

        $this->assertSame($before, $this->state());
        $this->assertContains('el viaje volvió a su configuración anterior', $summary);
        $this->assertSame('reverted', $added->fresh()->status);
    }

    public function test_reverting_an_added_import_without_conversion_only_removes_what_it_added(): void
    {
        $this->originImport();
        $before = $this->state();

        $added = $this->addedImport(self::BARGE, 'BL-MISMA-BARCAZA');
        $this->revert($added);

        $this->assertSame($before, $this->state());
    }

    public function test_revert_keeps_a_container_shared_with_another_voyage(): void
    {
        // Otro viaje ya usa el contenedor.
        DB::table('voyages')->insert(['id' => 99, 'company_id' => 1, 'voyage_number' => 'V-OTRO', 'lead_vessel_id' => self::TUG]);
        DB::table('shipments')->insert(['id' => 99, 'voyage_id' => 99, 'vessel_id' => self::TUG, 'shipment_number' => 'SHP-OTRO',
            'sequence_in_voyage' => 1, 'vessel_role' => 'single', 'is_lead_vessel' => true]);
        DB::table('bills_of_lading')->insert(['id' => 99, 'shipment_id' => 99, 'bill_number' => 'BL-OTRO']);
        DB::table('shipment_items')->insert(['id' => 99, 'bill_of_lading_id' => 99]);
        $shared = DB::table('containers')->insertGetId(['container_number' => 'MSCU1234565']);
        DB::table('container_shipment_item')->insert(['container_id' => $shared, 'shipment_item_id' => 99]);

        $this->originImport();
        $before = $this->state();

        $added = $this->addedImport(self::BARGE, 'BL-REUTILIZA', null, $shared);
        $this->assertSame([$shared], $added->created_container_ids);

        $this->revert($added);

        $this->assertSame($before, $this->state());
        $this->assertTrue(DB::table('containers')->where('id', $shared)->exists());
        $this->assertSame(
            [['container_id' => $shared, 'shipment_item_id' => 99]],
            DB::table('container_shipment_item')->get()->map(fn ($row) => (array) $row)->all()
        );
    }

    public function test_reverting_the_whole_voyage_goes_from_newest_added_import_to_the_origin(): void
    {
        $origin = $this->originImport();
        $convoy = $this->addedImport(self::NEW_BARGE, 'BL-AGREGADO-1', self::TUG);
        $newest = $this->addedImport(self::NEW_BARGE, 'BL-AGREGADO-2');

        // La conversión no se deshace mientras exista una importación más nueva.
        try {
            $this->revert($convoy);
            $this->fail('Se esperaba DomainException');
        } catch (DomainException $e) {
            $this->assertStringContainsString('más nuevas', $e->getMessage());
        }

        $order = [];
        Event::listen('eloquent.updated: ' . ManifestImport::class, function (ManifestImport $import) use (&$order) {
            if ($import->wasChanged('reverted_at')) {
                $order[] = $import->id;
            }
        });

        $summaries = $this->service->revertVoyage(Voyage::findOrFail(self::VOYAGE), 1, 'QA', 'Prueba');

        $this->assertSame([$newest->id, $convoy->id, $origin->id], $order);
        $this->assertSame([$newest->id, $convoy->id, $origin->id], array_keys($summaries));
        $this->assertSame(0, DB::table('voyages')->count());
        $this->assertSame(0, DB::table('shipments')->count());
        $this->assertSame(0, DB::table('bills_of_lading')->whereNull('deleted_at')->count());
        $this->assertSame(0, DB::table('shipment_items')->count());
        $this->assertSame(0, DB::table('containers')->count());
    }

    public function test_added_import_without_explicit_tracking_is_rejected(): void
    {
        $this->originImport();
        $added = $this->addedImport(self::BARGE, 'BL-SIN-REGISTRO');
        DB::table('manifest_imports')->where('id', $added->id)->update(['created_item_ids' => null]);
        $before = $this->state();

        $this->expectException(DomainException::class);
        try {
            $this->revert($added);
        } finally {
            $this->assertSame($before, $this->state());
        }
    }

    public function test_afip_records_block_the_revert(): void
    {
        $this->originImport();
        $added = $this->addedImport(self::BARGE, 'BL-ENVIADO');
        DB::table('webservice_transactions')->insert([
            'company_id' => 1, 'voyage_id' => self::VOYAGE, 'shipment_id' => 1,
            'soap_action' => 'RegistrarMicDta', 'status' => 'success', 'external_reference' => 'M1',
        ]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('registros vigentes en AFIP');
        $this->revert($added);
    }

    public function test_whole_voyage_revert_fails_atomically(): void
    {
        $this->originImport();
        $this->addedImport(self::BARGE, 'BL-1');
        DB::table('voyages')->update(['status' => 'in_transit']);
        $before = $this->state();

        try {
            $this->service->revertVoyage(Voyage::findOrFail(self::VOYAGE), 1, 'QA', 'Prueba');
            $this->fail('Se esperaba DomainException');
        } catch (DomainException) {
            $this->assertSame($before, $this->state());
            $this->assertSame(0, ManifestImport::whereNotNull('reverted_at')->count());
        }
    }
}
