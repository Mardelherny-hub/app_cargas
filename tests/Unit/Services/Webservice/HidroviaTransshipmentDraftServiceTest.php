<?php

namespace Tests\Unit\Services\Webservice;

use App\Models\BillOfLading;
use App\Models\HidroviaTransshipmentAllocation;
use App\Models\Voyage;
use App\Services\Simple\HidroviaTransshipmentDistributionValidator;
use App\Services\Simple\HidroviaTransshipmentDraftService;
use App\Services\Simple\HidroviaTransshipmentReadinessService;
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
        $schema->create('voyages', function ($t) { $t->id(); $t->unsignedBigInteger('company_id'); });
        $schema->create('vessel_types', function ($t) { $t->id(); $t->string('category'); });
        $schema->create('vessels', function ($t) { $t->id(); $t->unsignedBigInteger('vessel_type_id'); });
        $schema->create('shipments', function ($t) {
            $t->id(); $t->unsignedBigInteger('voyage_id');
            $t->unsignedBigInteger('vessel_id'); $t->string('shipment_number');
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
        (require database_path('migrations/2025_07_23_120939_create_webservice_transactions_table.php'))->up();
        DB::table('voyages')->insert([['id' => 7, 'company_id' => 101], ['id' => 8, 'company_id' => 202]]);
        DB::table('vessel_types')->insert([
            ['id' => 301, 'category' => 'barge'],
            ['id' => 302, 'category' => 'tugboat'],
        ]);
        DB::table('vessels')->insert([
            ['id' => 201, 'vessel_type_id' => 301],
            ['id' => 202, 'vessel_type_id' => 301],
            ['id' => 299, 'vessel_type_id' => 302],
        ]);
        DB::table('shipments')->insert([
            ['id' => 11, 'voyage_id' => 7, 'vessel_id' => 201, 'shipment_number' => 'B-201'],
            ['id' => 12, 'voyage_id' => 7, 'vessel_id' => 202, 'shipment_number' => 'B-202'],
            ['id' => 99, 'voyage_id' => 8, 'vessel_id' => 299, 'shipment_number' => 'B-299'],
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

    public function test_readiness_returns_real_shipments_and_lines_in_stable_order(): void
    {
        $this->service->replaceDraft($this->voyage, 101, $this->bill,
            [$this->row(12, 6, '60.15'), $this->row(11, 4, '40.10')]);
        $result = (new HidroviaTransshipmentReadinessService(
            new HidroviaTransshipmentDistributionValidator()
        ))->prepare($this->voyage, 101, $this->bill);
        $this->assertSame(31, $result['bill_of_lading_id']);
        $this->assertSame([11, 12], array_column($result['shipments'], 'shipment_id'));
        $this->assertSame([1, 2], array_column($result['shipments'], 'id_envio'));
        $this->assertSame([201, 202], array_column($result['shipments'], 'vessel_id'));
        $this->assertSame(['B-201', 'B-202'], array_column($result['shipments'], 'shipment_number'));
        $this->assertSame(41, $result['shipments'][0]['lines'][0]['shipment_item_id']);
        $this->assertSame('40.10', $result['shipments'][0]['lines'][0]['gross_weight_kg']);
        $again = (new HidroviaTransshipmentReadinessService(
            new HidroviaTransshipmentDistributionValidator()
        ))->prepare($this->voyage, 101, $this->bill);
        $this->assertSame($result, $again, 'La numeración no puede cambiar entre dos preflight.');
    }

    public function test_readiness_rejects_partial_distribution_before_any_soap(): void
    {
        $this->service->replaceDraft($this->voyage, 101, $this->bill, [$this->row(11, 4, '40.10')]);
        try {
            (new HidroviaTransshipmentReadinessService(
                new HidroviaTransshipmentDistributionValidator()
            ))->prepare($this->voyage, 101, $this->bill);
            $this->fail('Un reparto incompleto no puede quedar listo para Aduana.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('completar exactamente', $exception->errors()['distribution'][0]);
        }
    }

    public function test_readiness_rejects_tugboat_as_destination_for_cargo(): void
    {
        DB::table('vessels')->where('id', 202)->update(['vessel_type_id' => 302]);
        $this->service->replaceDraft($this->voyage, 101, $this->bill,
            [$this->row(11, 4, '40.10'), $this->row(12, 6, '60.15')]);
        try {
            (new HidroviaTransshipmentReadinessService(
                new HidroviaTransshipmentDistributionValidator()
            ))->prepare($this->voyage, 101, $this->bill);
            $this->fail('No se puede declarar un remolcador como barcaza de destino.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('barcaza real', $exception->errors()['distribution'][0]);
        }
    }

    public function test_container_cannot_change_barge_across_two_titulos_in_same_voyage(): void
    {
        DB::table('bills_of_lading')->insert([
            'id' => 32, 'shipment_id' => 11, 'status' => 'draft', 'is_transit_transshipment' => 'S',
        ]);
        DB::table('shipment_items')->insert([
            'id' => 42, 'bill_of_lading_id' => 32, 'package_quantity' => 2,
            'gross_weight_kg' => '10.00',
        ]);
        DB::table('containers')->insert(['id' => 51]);
        DB::table('container_shipment_item')->insert([
            ['shipment_item_id' => 41, 'container_id' => 51],
            ['shipment_item_id' => 42, 'container_id' => 51],
        ]);
        $main = array_merge($this->row(11, 10, '100.25'), ['container_id' => 51]);
        $this->service->replaceDraft($this->voyage, 101, $this->bill, [$main]);
        $otherBill = BillOfLading::findOrFail(32);
        $wrongBarge = [
            'shipment_id' => 12, 'shipment_item_id' => 42,
            'package_quantity' => 2, 'gross_weight_kg' => '10.00', 'container_id' => 51,
        ];
        try {
            $this->service->replaceDraft($this->voyage, 101, $otherBill, [$wrongBarge]);
            $this->fail('El mismo contenedor no puede quedar en dos barcazas del viaje.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('otra barcaza', $exception->errors()['distribution'][0]);
        }
        $this->assertSame(1, HidroviaTransshipmentAllocation::count());
        $this->service->replaceDraft($this->voyage, 101, $otherBill, [
            array_replace($wrongBarge, ['shipment_id' => 11]),
        ]);
        $this->assertSame(2, HidroviaTransshipmentAllocation::count());
    }

    public function test_any_prior_micdta_registration_attempt_freezes_the_distribution(): void
    {
        $this->service->replaceDraft($this->voyage, 101, $this->bill,
            [$this->row(11, 10, '100.25')]);

        // Un status=error sin sent_at/request_xml también puede provenir de un
        // timeout: no demuestra que ARCA nunca recibió la operación.
        foreach (['pending', 'validating', 'sending', 'retry', 'sent', 'success',
            'error', 'expired', 'cancelled'] as $status) {
            $transactionId = DB::table('webservice_transactions')->insertGetId([
                'company_id' => 101,
                'user_id' => 1,
                'voyage_id' => 7,
                'shipment_id' => 11,
                'transaction_id' => 'TIT-' . $status,
                'webservice_type' => 'micdta',
                'country' => 'AR',
                'webservice_url' => 'https://example.invalid',
                'environment' => 'testing',
                'soap_action' => 'Ar.Gob.Afip.Dga.wgesregsintia2/RegistrarTitEnvios',
                'status' => $status,
            ]);

            $this->reject([$this->row(12, 10, '100.25')], 'resultado incierto');
            $this->assertSame(11, (int) HidroviaTransshipmentAllocation::first()->target_shipment_id,
                "El borrador no debe modificarse tras una transacción {$status}.");
            $this->assertSame(1, HidroviaTransshipmentAllocation::count());
            DB::table('webservice_transactions')->where('id', $transactionId)->delete();
        }
    }

    public function test_unrelated_webservice_transactions_do_not_freeze_the_voyage(): void
    {
        $this->service->replaceDraft($this->voyage, 101, $this->bill,
            [$this->row(11, 10, '100.25')]);

        $base = [
            'company_id' => 101, 'user_id' => 1, 'voyage_id' => 7,
            'shipment_id' => 11, 'webservice_type' => 'micdta',
            'country' => 'AR', 'webservice_url' => 'https://example.invalid',
            'environment' => 'testing',
            'soap_action' => 'Ar.Gob.Afip.Dga.wgesregsintia2/RegistrarTitEnvios',
            'status' => 'error',
        ];
        $unrelated = [
            'different_company' => ['company_id' => 202],
            'different_voyage' => ['voyage_id' => 8],
            'different_country' => ['country' => 'PY'],
            'different_webservice' => ['webservice_type' => 'anticipada'],
            'different_operation' => ['soap_action' => 'Ar.Gob.Afip.Dga.wgesregsintia2/ConsultarTitEnviosReg'],
        ];
        foreach ($unrelated as $name => $difference) {
            $transactionId = DB::table('webservice_transactions')->insertGetId(array_replace(
                $base, ['transaction_id' => 'QA-' . $name], $difference
            ));

            $target = (int) HidroviaTransshipmentAllocation::first()->target_shipment_id === 11
                ? 12 : 11;
            $saved = $this->service->replaceDraft($this->voyage, 101, $this->bill,
                [$this->row($target, 10, '100.25')]);
            $this->assertCount(1, $saved);
            $this->assertSame($target, (int) $saved->first()->target_shipment_id,
                "La transacción {$name} no debe inmovilizar el viaje.");
            DB::table('webservice_transactions')->where('id', $transactionId)->delete();
        }
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
