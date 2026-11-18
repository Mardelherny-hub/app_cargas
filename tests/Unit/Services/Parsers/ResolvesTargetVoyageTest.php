<?php

namespace Tests\Unit\Services\Parsers;

use App\Models\Company;
use App\Models\ManifestImport;
use App\Models\Shipment;
use App\Models\User;
use App\Models\Voyage;
use App\Services\Parsers\Concerns\ResolvesTargetVoyage;
use DomainException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Esquema mínimo aislado en SQLite :memory:: nunca se ejecutan migraciones
 * sobre una base externa ni se envía nada a AFIP.
 */
class ResolvesTargetVoyageTest extends TestCase
{
    private const VOYAGE = 10;
    private const LEAD = 100;
    private const BARGE = 200;
    private const OTHER_BARGE = 300;

    private object $parser;

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
            $t->id(); $t->string('legal_name')->nullable(); $t->string('ws_environment')->nullable(); $t->timestamps();
        });
        Schema::create('users', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->string('email'); $t->string('password');
            $t->string('userable_type'); $t->unsignedBigInteger('userable_id'); $t->timestamps();
        });
        Schema::create('voyages', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('company_id'); $t->string('voyage_number');
            $t->string('status')->default('planning'); $t->unsignedBigInteger('lead_vessel_id')->nullable();
            $t->string('voyage_type')->default('single_vessel'); $t->boolean('is_convoy')->default(false);
            $t->integer('vessel_count')->default(1); $t->timestamps();
        });
        Schema::create('shipments', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('voyage_id'); $t->unsignedBigInteger('vessel_id');
            $t->string('shipment_number'); $t->integer('sequence_in_voyage');
            $t->string('vessel_role'); $t->boolean('is_lead_vessel')->default(false);
            $t->unsignedBigInteger('created_by_user_id')->nullable(); $t->timestamps();
            $t->unique(['voyage_id', 'vessel_id']); $t->unique(['voyage_id', 'sequence_in_voyage']);
        });
        Schema::create('bills_of_lading', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('shipment_id'); $t->string('bill_number'); $t->softDeletes();
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
            foreach (['voyage', 'shipment', 'bill', 'item', 'container', 'client', 'port'] as $type) {
                $t->json("created_{$type}_ids")->nullable(); $t->integer("created_{$type}s")->default(0);
            }
            $t->json('parser_config')->nullable(); $t->timestamps();
        });

        DB::table('companies')->insert([
            ['id' => 1, 'legal_name' => 'QA', 'ws_environment' => 'testing'],
            ['id' => 2, 'legal_name' => 'Otra', 'ws_environment' => 'testing'],
        ]);
        DB::table('voyages')->insert([
            ['id' => self::VOYAGE, 'company_id' => 1, 'voyage_number' => 'V-QA', 'lead_vessel_id' => self::LEAD],
            ['id' => 11, 'company_id' => 2, 'voyage_number' => 'V-OTRA', 'lead_vessel_id' => self::LEAD],
            ['id' => 12, 'company_id' => 1, 'voyage_number' => 'V-SALIDO', 'lead_vessel_id' => self::LEAD],
        ]);
        DB::table('voyages')->where('id', 12)->update(['status' => 'in_transit']);
        DB::table('shipments')->insert([
            'id' => 1, 'voyage_id' => self::VOYAGE, 'vessel_id' => self::LEAD, 'shipment_number' => 'S-1',
            'sequence_in_voyage' => 1, 'vessel_role' => 'single', 'is_lead_vessel' => true,
        ]);
        DB::table('bills_of_lading')->insert([
            ['shipment_id' => 1, 'bill_number' => 'BL-EXISTENTE'],
            ['shipment_id' => 99, 'bill_number' => 'BL-OTRO-VIAJE'],
        ]);

        $userId = DB::table('users')->insertGetId([
            'name' => 'QA', 'email' => 'qa@example.test', 'password' => 'unused',
            'userable_type' => Company::class, 'userable_id' => 1,
        ]);
        $this->actingAs(User::findOrFail($userId));

        $this->parser = new class {
            use ResolvesTargetVoyage {
                resolveTargetVoyage as public;
                resolveTargetShipment as public;
                skipRepeatedBill as public;
                repeatedBillsWarning as public;
                recordTargetVoyageObjects as public;
            }
        };
    }

    private function convoy(): void
    {
        DB::table('voyages')->where('id', self::VOYAGE)->update(['is_convoy' => true, 'voyage_type' => 'convoy']);
    }

    private function shipmentFor(Voyage $voyage, int $vessel): Shipment
    {
        // Las estadísticas del viaje las recalculan los eventos del modelo,
        // ajenos a este contrato.
        return Shipment::withoutEvents(
            fn () => $this->parser->resolveTargetShipment($voyage, $vessel, ['shipment_number' => 'S-NUEVA'])
        );
    }

    private function expectDomainError(string $fragment, array $options): void
    {
        try {
            $this->parser->resolveTargetVoyage($options);
            $this->fail('Se esperaba un error de dominio.');
        } catch (DomainException $e) {
            $this->assertStringContainsString($fragment, $e->getMessage());
        }
    }

    public function test_without_target_the_current_flow_is_untouched(): void
    {
        $this->assertNull($this->parser->resolveTargetVoyage(['vessel_id' => self::LEAD]));
        $this->assertNull($this->parser->resolveTargetVoyage(['vessel_id' => self::LEAD, 'target_voyage_id' => null]));
    }

    public function test_target_voyage_must_belong_to_company_and_be_in_planning(): void
    {
        $voyage = $this->parser->resolveTargetVoyage(['target_voyage_id' => self::VOYAGE, 'vessel_id' => self::LEAD]);
        $this->assertSame(self::VOYAGE, $voyage->id);

        $this->expectDomainError('no pertenece a su empresa', ['target_voyage_id' => 11, 'vessel_id' => self::LEAD]);
        $this->expectDomainError('planificación', ['target_voyage_id' => 12, 'vessel_id' => self::LEAD]);
        $this->expectDomainError('confirmar la embarcación', ['target_voyage_id' => self::VOYAGE]);
    }

    public function test_afip_lock_blocks_the_target(): void
    {
        DB::table('webservice_transactions')->insert([
            'company_id' => 1, 'voyage_id' => self::VOYAGE, 'shipment_id' => 1, 'status' => 'success',
            'soap_action' => 'Ar.Gob.Afip.Dga.wgesregsintia2/RegistrarMicDta', 'external_reference' => '26ARMIF0001',
        ]);

        $this->expectDomainError('26ARMIF0001', ['target_voyage_id' => self::VOYAGE, 'vessel_id' => self::LEAD]);
    }

    public function test_another_vessel_requires_a_convoy(): void
    {
        $this->expectDomainError('convertirlo en convoy', ['target_voyage_id' => self::VOYAGE, 'vessel_id' => self::BARGE]);

        $this->convoy();
        $voyage = $this->parser->resolveTargetVoyage(['target_voyage_id' => self::VOYAGE, 'vessel_id' => self::BARGE]);
        $this->assertSame(self::VOYAGE, $voyage->id);
    }

    public function test_existing_vessel_shipment_is_reused(): void
    {
        $voyage = Voyage::findOrFail(self::VOYAGE);
        $shipment = $this->shipmentFor($voyage, self::LEAD);

        $this->assertSame(1, $shipment->id);
        $this->assertSame(1, Shipment::count());
    }

    public function test_new_vessel_gets_next_sequence_and_convoy_role(): void
    {
        $this->convoy();
        $voyage = Voyage::findOrFail(self::VOYAGE);

        $barge = $this->shipmentFor($voyage, self::BARGE);
        $this->assertSame(2, $barge->sequence_in_voyage);
        $this->assertSame('towed', $barge->vessel_role);
        $this->assertFalse($barge->is_lead_vessel);
        $this->assertSame('S-NUEVA', $barge->shipment_number);

        $other = $this->shipmentFor($voyage, self::OTHER_BARGE);
        $this->assertSame(3, $other->sequence_in_voyage);
    }

    public function test_new_shipment_records_the_importing_user_even_if_the_parser_omits_it(): void
    {
        $this->convoy();
        $barge = $this->shipmentFor(Voyage::findOrFail(self::VOYAGE), self::BARGE);

        $this->assertSame(auth()->id(), (int) $barge->fresh()->created_by_user_id);
    }

    public function test_importing_user_prevails_over_the_creator_sent_by_the_parser(): void
    {
        $this->convoy();
        $voyage = Voyage::findOrFail(self::VOYAGE);

        // Los parsers que ya lo envían mandan el mismo usuario: no hay conflicto.
        $same = Shipment::withoutEvents(fn () => $this->parser->resolveTargetShipment(
            $voyage, self::BARGE, ['shipment_number' => 'S-NUEVA', 'created_by_user_id' => auth()->id()]
        ));
        $this->assertSame(auth()->id(), (int) $same->fresh()->created_by_user_id);

        $other = Shipment::withoutEvents(fn () => $this->parser->resolveTargetShipment(
            $voyage, self::OTHER_BARGE, ['shipment_number' => 'S-OTRA', 'created_by_user_id' => 999]
        ));
        $this->assertSame(auth()->id(), (int) $other->fresh()->created_by_user_id);
    }

    public function test_reused_shipment_keeps_its_original_creator(): void
    {
        $shipment = $this->shipmentFor(Voyage::findOrFail(self::VOYAGE), self::LEAD);

        $this->assertSame(1, $shipment->id);
        $this->assertNull($shipment->fresh()->created_by_user_id);
    }

    public function test_lead_without_shipment_is_created_as_lead(): void
    {
        DB::table('shipments')->delete();
        $this->convoy();
        $lead = $this->shipmentFor(Voyage::findOrFail(self::VOYAGE), self::LEAD);

        $this->assertSame('lead', $lead->vessel_role);
        $this->assertTrue($lead->is_lead_vessel);
        $this->assertSame(1, $lead->sequence_in_voyage);
    }

    public function test_repeated_bills_are_skipped_only_within_the_voyage(): void
    {
        $voyage = Voyage::findOrFail(self::VOYAGE);

        $this->assertTrue($this->parser->skipRepeatedBill($voyage, ' BL-EXISTENTE '));
        $this->assertTrue($this->parser->skipRepeatedBill($voyage, 'BL-EXISTENTE'));
        $this->assertFalse($this->parser->skipRepeatedBill($voyage, 'BL-OTRO-VIAJE'));
        $this->assertFalse($this->parser->skipRepeatedBill($voyage, 'BL-NUEVO'));
        $this->assertFalse($this->parser->skipRepeatedBill($voyage, ''));

        $this->assertSame(
            'Se saltearon 1 conocimientos que ya existían en el viaje: BL-EXISTENTE.',
            $this->parser->repeatedBillsWarning()
        );
    }

    public function test_no_repeated_bills_means_no_warning(): void
    {
        $this->assertNull($this->parser->repeatedBillsWarning());
    }

    public function test_recording_never_includes_target_voyage_or_existing_shipment(): void
    {
        $voyage = Voyage::findOrFail(self::VOYAGE);
        $this->shipmentFor($voyage, self::LEAD);
        $this->parser->skipRepeatedBill($voyage, 'BL-EXISTENTE');
        $import = ManifestImport::forceCreate(['company_id' => 1]);

        $this->parser->recordTargetVoyageObjects($import, $voyage, [
            'shipment' => [1], 'bill' => [5, 6], 'item' => [7], 'container' => [],
        ]);

        $import->refresh();
        $this->assertSame([], $import->created_voyage_ids);
        $this->assertSame([], $import->created_shipment_ids);
        $this->assertSame([5, 6], $import->created_bill_ids);
        $this->assertSame([], $import->created_container_ids);
        $this->assertSame(self::VOYAGE, (int) $import->voyage_id);
        $this->assertSame([
            'voyage_id' => self::VOYAGE, 'shipment_created' => false, 'skipped_bills' => ['BL-EXISTENTE'],
        ], $import->parser_config['target_voyage']);

        $this->assertSame(
            ['voyages' => [], 'shipments' => [], 'bills' => [5, 6], 'containers' => [], 'items' => [7]],
            $import->getAllCreatedObjectIds()
        );
    }

    public function test_recording_includes_a_shipment_created_by_this_import(): void
    {
        $this->convoy();
        $voyage = Voyage::findOrFail(self::VOYAGE);
        $barge = $this->shipmentFor($voyage, self::BARGE);
        $import = ManifestImport::forceCreate(['company_id' => 1]);

        $this->parser->recordTargetVoyageObjects($import, $voyage, [
            'shipment' => [$barge->id], 'bill' => [], 'item' => [], 'container' => [],
        ]);

        $this->assertSame([$barge->id], $import->fresh()->created_shipment_ids);
        $this->assertTrue($import->fresh()->parser_config['target_voyage']['shipment_created']);
    }

    public function test_recording_requires_every_object_list(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->parser->recordTargetVoyageObjects(
            ManifestImport::forceCreate(['company_id' => 1]),
            Voyage::findOrFail(self::VOYAGE),
            ['shipment' => [], 'bill' => [], 'item' => []]
        );
    }
}
