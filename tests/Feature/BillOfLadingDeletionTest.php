<?php

namespace Tests\Feature;

use App\Http\Controllers\Company\BillOfLadingController;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BillOfLadingDeletionTest extends TestCase
{
    private const TABLES = [
        'companies', 'clients', 'client_contact_data', 'manifest_imports', 'voyages', 'shipments', 'bills_of_lading',
        'shipment_items', 'containers', 'container_shipment_item', 'webservice_transactions',
        'webservice_tracks', 'voyage_attachments', 'hidrovia_transshipment_allocations',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $sqlite = config('database.connections.sqlite');
        $sqlite['database'] = ':memory:';
        $sqlite['foreign_key_constraints'] = true;
        config([
            'database.default' => 'sqlite',
            'database.connections' => ['sqlite' => $sqlite],
            'cache.default' => 'array', 'session.driver' => 'array', 'logging.default' => 'null',
            'audit.enabled' => false, 'app.key' => 'base64:'.base64_encode(str_repeat('x', 32)),
        ]);
        DB::purge('sqlite');
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        $this->assertSame(1, (int) DB::selectOne('PRAGMA foreign_keys')->foreign_keys);
        (require database_path('migrations/2025_07_04_130831_create_permission_tables.php'))->up();
        Schema::create('companies', function (Blueprint $t) {
            $t->id(); $t->string('legal_name'); $t->boolean('active')->default(true);
            $t->text('company_roles'); $t->string('ws_environment')->default('testing'); $t->timestamps();
        });
        Schema::create('users', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->string('email'); $t->string('password');
            $t->string('userable_type'); $t->unsignedBigInteger('userable_id');
            $t->timestamp('email_verified_at')->nullable(); $t->boolean('active')->default(true); $t->timestamps();
        });
        Schema::create('clients', function (Blueprint $t) {
            $t->id(); $t->string('legal_name'); $t->string('tax_id'); $t->string('status')->default('active');
        });
        Schema::create('client_contact_data', function (Blueprint $t) {
            $t->id(); $t->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $t->boolean('is_primary')->default(false);
        });
        Schema::create('vessels', function (Blueprint $t) { $t->id(); $t->string('name'); });
        Schema::create('ports', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->string('code'); $t->unsignedBigInteger('country_id')->nullable();
            $t->boolean('active')->default(true);
        });
        Schema::create('voyages', function (Blueprint $t) {
            $t->id(); $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->string('voyage_number'); $t->string('status')->default('planning');
            $t->date('departure_date')->nullable();
            foreach (['total_cargo_capacity_tons', 'total_cargo_weight_loaded', 'capacity_utilization_percentage',
                'total_cargo_weight', 'total_cargo_volume'] as $f) $t->decimal($f, 12, 2)->default(0);
            foreach (['vessel_count', 'total_container_capacity', 'total_containers_loaded', 'total_containers',
                'total_bills_of_lading', 'total_packages'] as $f) $t->integer($f)->default(0);
            $t->boolean('has_dangerous_cargo')->default(false); $t->boolean('requires_special_handling')->default(false);
            $t->timestamp('last_updated_date')->nullable(); $t->timestamps();
        });
        Schema::create('shipments', function (Blueprint $t) {
            $t->id(); $t->foreignId('voyage_id')->constrained()->cascadeOnDelete();
            $t->foreignId('vessel_id')->constrained()->restrictOnDelete(); $t->string('shipment_number');
            $t->decimal('cargo_capacity_tons', 10, 2)->default(100);
            $t->decimal('cargo_weight_loaded', 10, 2)->default(0);
            $t->decimal('utilization_percentage', 5, 2)->default(0);
            $t->integer('container_capacity')->default(10); $t->integer('containers_loaded')->default(0);
            $t->boolean('has_dangerous_cargo')->default(false); $t->timestamps();
        });
        Schema::create('bills_of_lading', function (Blueprint $t) {
            $t->id(); $t->foreignId('shipment_id')->constrained()->cascadeOnDelete();
            foreach (['shipper_id', 'consignee_id'] as $f) $t->foreignId($f)->constrained('clients')->restrictOnDelete();
            foreach (['loading_port_id', 'discharge_port_id', 'final_destination_port_id', 'primary_cargo_type_id',
                'created_by_user_id'] as $f) $t->unsignedBigInteger($f)->nullable();
            $t->string('bill_number'); $t->date('bill_date')->nullable(); $t->string('status')->default('draft');
            foreach (['verified_at', 'webservice_sent_at', 'argentina_sent_at', 'paraguay_sent_at', 'free_time_expires_at'] as $f) $t->timestamp($f)->nullable();
            $t->integer('total_packages')->default(20); $t->decimal('gross_weight_kg', 12, 2)->default(200);
            $t->decimal('volume_m3', 10, 3)->default(4);
            foreach (['contains_dangerous_goods', 'requires_refrigeration', 'is_consolidated'] as $f) $t->boolean($f)->default(false);
            $t->softDeletes(); $t->timestamps();
        });
        Schema::create('shipment_items', function (Blueprint $t) {
            $t->id(); $t->foreignId('bill_of_lading_id')->constrained('bills_of_lading')->cascadeOnDelete();
            $t->integer('line_number'); $t->integer('package_quantity')->default(10);
            $t->decimal('gross_weight_kg', 12, 2)->default(100); $t->decimal('volume_m3', 10, 3)->default(2);
            $t->decimal('declared_value', 12, 2)->default(50); $t->boolean('is_dangerous_goods')->default(false);
            $t->boolean('requires_refrigeration')->default(false); $t->string('tariff_position')->nullable();
            $t->string('commodity_code')->nullable(); $t->timestamps();
            $t->unique(['bill_of_lading_id', 'line_number']);
        });
        Schema::create('containers', function (Blueprint $t) {
            $t->id(); $t->string('container_number')->unique(); $t->timestamps();
        });
        Schema::create('container_shipment_item', function (Blueprint $t) {
            $t->id(); $t->foreignId('container_id')->constrained()->cascadeOnDelete();
            $t->foreignId('shipment_item_id')->constrained()->cascadeOnDelete();
            $t->integer('package_quantity'); $t->decimal('gross_weight_kg', 12, 2);
            $t->unique(['container_id', 'shipment_item_id']);
        });
        Schema::create('webservice_transactions', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('company_id'); $t->unsignedBigInteger('voyage_id')->nullable();
            $t->unsignedBigInteger('shipment_id')->nullable(); $t->string('soap_action');
            $t->string('country')->default('AR'); $t->string('environment')->default('testing');
            $t->string('status')->default('success'); $t->string('external_reference')->nullable();
            $t->text('request_xml')->nullable(); $t->text('response_xml')->nullable();
            $t->json('success_data')->nullable(); $t->json('additional_metadata')->nullable();
        });
        Schema::create('webservice_tracks', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('bill_of_lading_id')->nullable();
            $t->unsignedBigInteger('container_id')->nullable(); $t->string('track_number');
        });
        Schema::create('voyage_attachments', function (Blueprint $t) {
            $t->id(); $t->foreignId('voyage_id')->constrained()->cascadeOnDelete();
            $t->foreignId('bill_of_lading_id')->nullable()->constrained('bills_of_lading')->nullOnDelete();
            $t->boolean('sent_to_dna')->default(false);
        });
        Schema::create('hidrovia_transshipment_allocations', function (Blueprint $t) {
            $t->id(); $t->foreignId('bill_of_lading_id')->constrained('bills_of_lading')->restrictOnDelete();
            $t->foreignId('shipment_item_id')->constrained()->restrictOnDelete();
            $t->foreignId('target_shipment_id')->constrained('shipments')->restrictOnDelete();
            $t->foreignId('container_id')->nullable()->constrained()->restrictOnDelete();
            $t->unsignedInteger('package_quantity'); $t->decimal('gross_weight_kg', 12, 2);
        });
        Schema::create('manifest_imports', function (Blueprint $t) {
            $t->id(); $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->foreignId('voyage_id')->nullable()->constrained()->nullOnDelete(); $t->json('created_bill_ids');
        });
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        Role::create(['name' => 'company-admin', 'guard_name' => 'web']);
        DB::table('companies')->insert([
            ['id' => 1, 'legal_name' => 'Empresa A', 'company_roles' => '["Cargas"]'],
            ['id' => 2, 'legal_name' => 'Empresa B', 'company_roles' => '["Cargas"]'],
            ['id' => 3, 'legal_name' => 'Empresa C', 'company_roles' => '["Desconsolidador"]'],
        ]);
        DB::table('clients')->insert(['id' => 1, 'legal_name' => 'Cliente', 'tax_id' => '20123456789']);
        DB::table('vessels')->insert([['id' => 1, 'name' => 'Barcaza A'], ['id' => 2, 'name' => 'Barcaza B']]);
        foreach ([1, 2] as $id) DB::table('voyages')->insert(['id' => $id, 'company_id' => 1, 'voyage_number' => 'V-'.$id]);
        $this->shipment(1, 1, 1);
        $this->shipment(2, 1, 1);
        $this->shipment(3, 1, 2);
        $this->shipment(4, 2, 1);
        $this->actingAs($this->admin());
    }

    private function admin(int $company = 1): User
    {
        $id = DB::table('users')->insertGetId(['name' => 'Usuario', 'email' => $company.'@example.test',
            'password' => 'unused', 'userable_type' => Company::class, 'userable_id' => $company,
            'email_verified_at' => now()]);
        $user = User::findOrFail($id);
        $user->assignRole('company-admin');
        return $user;
    }

    private function shipment(int $id, int $voyage, int $vessel): void
    {
        DB::table('shipments')->insert(['id' => $id, 'voyage_id' => $voyage, 'vessel_id' => $vessel, 'shipment_number' => 'S-'.$id]);
    }

    private function bill(int $id = 1, int $shipment = 1): void
    {
        DB::table('bills_of_lading')->insert(['id' => $id, 'shipment_id' => $shipment,
            'bill_number' => 'BL-'.$id, 'shipper_id' => 1, 'consignee_id' => 1]);
        foreach ([1, 2] as $line) {
            $item = $id * 10 + $line;
            DB::table('shipment_items')->insert(['id' => $item, 'bill_of_lading_id' => $id, 'line_number' => $line]);
            DB::table('containers')->insert(['id' => $item, 'container_number' => 'CONT-'.$item]);
            $this->link($item, $item);
        }
    }

    private function link(int $container, int $item): void
    {
        DB::table('container_shipment_item')->insert(['container_id' => $container, 'shipment_item_id' => $item,
            'package_quantity' => 10, 'gross_weight_kg' => 100]);
    }

    private function removeBill(int $id = 1)
    {
        return $this->from('/company/bills-of-lading')->delete(route('company.bills-of-lading.destroy', $id));
    }

    private function removeVessel()
    {
        return $this->from('/company/bills-of-lading')->delete(route('company.bills-of-lading.destroy-vessel', ['voyage' => 1, 'vessel' => 1]));
    }

    private function snapshot(): array
    {
        $rows = [];
        foreach (self::TABLES as $table) $rows[$table] = DB::table($table)->orderBy('id')->get()->toJson();
        return $rows;
    }

    private function assertBlocked(): void
    {
        $before = $this->snapshot();
        $this->removeBill()->assertRedirect()->assertSessionHas('error')->assertSessionMissing('success');
        $this->assertSame($before, $this->snapshot());
        $this->removeVessel()->assertRedirect()->assertSessionHas('error')->assertSessionMissing('success');
        $this->assertSame($before, $this->snapshot());
    }

    public function test_individual_deletion_preserves_parents_clients_and_import_history(): void
    {
        $this->bill();
        DB::table('manifest_imports')->insert(['company_id' => 1, 'voyage_id' => 1, 'created_bill_ids' => '[1]']);
        $before = $this->snapshot();
        $this->removeBill()->assertRedirect(route('company.bills-of-lading.index'))->assertSessionHas('success')->assertSessionMissing('error');
        $this->assertSoftDeleted('bills_of_lading', ['id' => 1, 'status' => 'draft']);
        $this->assertDatabaseCount('shipment_items', 0);
        $this->assertDatabaseCount('container_shipment_item', 0);
        $this->assertDatabaseCount('containers', 0);
        foreach (['clients', 'manifest_imports', 'companies'] as $table) $this->assertSame($before[$table], $this->snapshot()[$table]);
        $this->assertDatabaseCount('voyages', 2);
        $this->assertDatabaseCount('shipments', 4);
        $this->removeBill()->assertNotFound();
    }

    public function test_shared_container_with_same_shipment_survives_with_other_links(): void
    {
        $this->assertSharedContainerSurvives(1);
    }

    public function test_shared_container_with_another_vessel_survives_with_other_links(): void
    {
        $this->assertSharedContainerSurvives(3);
    }

    public function test_shared_container_with_another_voyage_survives_with_other_links(): void
    {
        $this->assertSharedContainerSurvives(4);
    }

    private function assertSharedContainerSurvives(int $shipment): void
    {
        $this->bill(); $this->bill(2, $shipment); $this->link(11, 21);
        $otherItems = DB::table('shipment_items')->where('bill_of_lading_id', 2)->get()->toJson();
        $otherLinks = DB::table('container_shipment_item')->whereIn('shipment_item_id', [21, 22])->get()->toJson();
        $otherBill = DB::table('bills_of_lading')->where('id', 2)->first();
        $this->removeBill()->assertSessionHas('success')->assertSessionMissing('error');
        $this->assertDatabaseHas('containers', ['id' => 11]);
        $this->assertDatabaseMissing('containers', ['id' => 12]);
        $this->assertDatabaseMissing('shipment_items', ['bill_of_lading_id' => 1]);
        $this->assertDatabaseMissing('container_shipment_item', ['shipment_item_id' => 11]);
        $this->assertSame($otherItems, DB::table('shipment_items')->where('bill_of_lading_id', 2)->get()->toJson());
        $this->assertSame($otherLinks, DB::table('container_shipment_item')->whereIn('shipment_item_id', [21, 22])->get()->toJson());
        $this->assertEquals($otherBill, DB::table('bills_of_lading')->where('id', 2)->first());
    }

    public function test_bulk_deletion_covers_all_shipments_of_pair_and_preserves_other_pairs(): void
    {
        foreach ([1 => 1, 2 => 2, 3 => 3, 4 => 4] as $bill => $shipment) $this->bill($bill, $shipment);
        $otherShipments = DB::table('shipments')->whereIn('id', [3, 4])->get()->toJson();
        $otherVoyage = DB::table('voyages')->where('id', 2)->first();
        $this->removeVessel()->assertSessionHas('success')->assertSessionMissing('error');
        foreach ([1, 2] as $id) {
            $this->assertSoftDeleted('bills_of_lading', ['id' => $id]);
            $this->assertDatabaseMissing('shipment_items', ['bill_of_lading_id' => $id]);
            foreach ([1, 2] as $line) {
                $this->assertDatabaseMissing('containers', ['id' => $id * 10 + $line]);
                $this->assertDatabaseMissing('container_shipment_item', ['shipment_item_id' => $id * 10 + $line]);
            }
        }
        foreach ([3, 4] as $id) $this->assertDatabaseHas('bills_of_lading', ['id' => $id, 'deleted_at' => null]);
        $this->assertDatabaseCount('shipment_items', 4);
        $this->assertDatabaseCount('container_shipment_item', 4);
        $this->assertDatabaseCount('containers', 4);
        $this->assertSame($otherShipments, DB::table('shipments')->whereIn('id', [3, 4])->get()->toJson());
        $this->assertEquals($otherVoyage, DB::table('voyages')->where('id', 2)->first());
    }

    public function test_bulk_validation_failure_leaves_even_earlier_draft_untouched(): void
    {
        $this->bill(); $this->bill(2, 2);
        DB::table('bills_of_lading')->where('id', 2)->update(['status' => 'pending_review']);
        $before = $this->snapshot();
        $this->removeVessel()->assertSessionHas('error')->assertSessionMissing('success');
        $this->assertSame($before, $this->snapshot());
    }

    private function transaction(string $method, bool $withoutShipment = false): void
    {
        DB::table('webservice_transactions')->insert([
            'company_id' => 1, 'voyage_id' => 1, 'shipment_id' => $withoutShipment ? null : 1,
            'soap_action' => 'Ar.Gob.Afip.Dga.wgesregsintia2/'.$method,
            'external_reference' => $method === 'RegistrarMicDta' ? '26ARMIF00000001' : null,
            'request_xml' => '<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"><soap:Body>'
                .'<RegistrarTitEnvios xmlns="Ar.Gob.Afip.Dga.wgesregsintia2"><titTransEnvios>'
                .'<TitTransEnvio><idTitTrans>BL-1</idTitTrans></TitTransEnvio>'
                .'</titTransEnvios></RegistrarTitEnvios></soap:Body></soap:Envelope>',
        ]);
    }

    public function test_registered_afip_titles_block_individual_and_bulk_deletion(): void
    {
        $this->bill(); $this->transaction('RegistrarTitEnvios'); $this->assertBlocked();
        $this->assertStringContainsString('AFIP', session('error'));
    }

    public function test_registered_micdta_without_shipment_blocks_individual_and_bulk_deletion(): void
    {
        $this->bill(); $this->transaction('RegistrarMicDta', true); $this->assertBlocked();
        $this->assertStringContainsString('AFIP', session('error'));
    }

    public function test_verified_status_blocks_deletion(): void
    {
        $this->bill(); DB::table('bills_of_lading')->update(['status' => 'verified']); $this->assertBlocked();
    }

    public function test_verified_timestamp_blocks_even_a_draft(): void
    {
        $this->bill(); DB::table('bills_of_lading')->update(['verified_at' => now()]); $this->assertBlocked();
    }

    public function test_pending_review_blocks_deletion(): void
    {
        $this->bill(); DB::table('bills_of_lading')->update(['status' => 'pending_review']); $this->assertBlocked();
    }

    public function test_webservice_sent_timestamp_blocks_deletion(): void
    {
        $this->bill(); DB::table('bills_of_lading')->update(['webservice_sent_at' => now()]); $this->assertBlocked();
    }

    public function test_argentina_sent_timestamp_blocks_deletion(): void
    {
        $this->bill(); DB::table('bills_of_lading')->update(['argentina_sent_at' => now()]); $this->assertBlocked();
    }

    public function test_paraguay_sent_timestamp_blocks_deletion(): void
    {
        $this->bill(); DB::table('bills_of_lading')->update(['paraguay_sent_at' => now()]); $this->assertBlocked();
    }

    public function test_dna_document_blocks_deletion(): void
    {
        $this->bill(); DB::table('voyage_attachments')->insert(['voyage_id' => 1, 'bill_of_lading_id' => 1, 'sent_to_dna' => true]);
        $this->assertBlocked();
    }

    public function test_bill_track_blocks_deletion(): void
    {
        $this->bill(); DB::table('webservice_tracks')->insert(['bill_of_lading_id' => 1, 'track_number' => 'TRACK-BL']);
        $this->assertBlocked();
    }

    public function test_transshipment_allocation_blocks_deletion(): void
    {
        $this->bill();
        DB::table('hidrovia_transshipment_allocations')->insert(['bill_of_lading_id' => 1, 'shipment_item_id' => 11,
            'target_shipment_id' => 3, 'container_id' => 11, 'package_quantity' => 10, 'gross_weight_kg' => 100]);
        $this->assertBlocked();
    }

    public function test_container_track_preserves_master_while_deleting_unblocked_bill(): void
    {
        $this->bill(); DB::table('webservice_tracks')->insert(['container_id' => 11, 'track_number' => 'TRACK-C']);
        $before = DB::table('webservice_tracks')->get()->toJson();
        $this->removeBill()->assertSessionHas('success')->assertSessionMissing('error');
        $this->assertDatabaseHas('containers', ['id' => 11]);
        $this->assertDatabaseMissing('containers', ['id' => 12]);
        $this->assertDatabaseCount('container_shipment_item', 0);
        $this->assertSame($before, DB::table('webservice_tracks')->get()->toJson());
    }

    public function test_foreign_company_is_forbidden_without_changes(): void
    {
        $this->bill(); $this->actingAs($this->admin(2));
        $before = $this->snapshot();
        $this->removeBill()->assertForbidden(); $this->removeVessel()->assertForbidden();
        $this->assertSame($before, $this->snapshot());
    }

    public function test_company_without_cargas_is_forbidden_without_changes(): void
    {
        $this->bill(); $this->actingAs($this->admin(3));
        $before = $this->snapshot();
        $this->removeBill()->assertForbidden(); $this->removeVessel()->assertForbidden();
        $this->assertSame($before, $this->snapshot());
    }

    public function test_totals_are_recalculated_after_one_and_last_bill(): void
    {
        $this->bill(); $this->bill(2);
        DB::table('shipments')->where('id', 1)->update(['cargo_weight_loaded' => 400, 'containers_loaded' => 4]);
        DB::table('voyages')->where('id', 1)->update(['total_bills_of_lading' => 2, 'total_cargo_volume' => 8,
            'total_packages' => 40, 'total_cargo_weight_loaded' => 400, 'total_containers_loaded' => 4]);
        $this->removeBill()->assertSessionHas('success')->assertSessionMissing('error');
        $this->assertDatabaseHas('shipments', ['id' => 1, 'cargo_weight_loaded' => 200, 'containers_loaded' => 2]);
        $this->assertDatabaseHas('voyages', ['id' => 1, 'total_cargo_weight_loaded' => 200, 'total_cargo_weight' => 200,
            'total_containers_loaded' => 2, 'total_containers' => 2, 'total_bills_of_lading' => 1,
            'total_cargo_volume' => 4, 'total_packages' => 20]);
        $this->removeBill(2)->assertSessionHas('success')->assertSessionMissing('error');
        $this->assertDatabaseHas('shipments', ['id' => 1, 'cargo_weight_loaded' => 0, 'containers_loaded' => 0]);
        $this->assertDatabaseHas('voyages', ['id' => 1, 'total_cargo_weight_loaded' => 0, 'total_cargo_weight' => 0,
            'total_containers_loaded' => 0, 'total_containers' => 0, 'total_bills_of_lading' => 0,
            'total_cargo_volume' => 0, 'total_packages' => 0]);
    }

    public function test_late_database_failure_rolls_back_all_deletions_and_statistics(): void
    {
        $this->bill(); $this->bill(2, 2);
        DB::unprepared("CREATE TRIGGER reject_voyage_update BEFORE UPDATE ON voyages BEGIN SELECT RAISE(ABORT, 'Cannot update voyage'); END");
        $this->assertBlocked();
    }

    public function test_afip_lock_hides_individual_and_bulk_actions_in_controller_view_data(): void
    {
        $this->bill(); $this->transaction('RegistrarMicDta', true);
        $view = app(BillOfLadingController::class)->index(Request::create('/company/bills-of-lading', 'GET', ['shipment_id' => 1]));
        $this->assertFalse($view->getData()['canDeleteBillIds'][1]);
        $this->assertNull($view->getData()['bulkDeleteVesselInfo']);
    }

    public function test_item_allocation_blocks_even_when_its_bill_reference_is_different(): void
    {
        $this->bill(); $this->bill(2, 3);
        DB::table('hidrovia_transshipment_allocations')->insert(['bill_of_lading_id' => 2, 'shipment_item_id' => 11,
            'target_shipment_id' => 3, 'container_id' => 11, 'package_quantity' => 10, 'gross_weight_kg' => 100]);
        $this->assertBlocked();
        $this->assertStringContainsString('distribuciones', session('error'));
    }

    public function test_container_allocation_preserves_master_and_other_bill_distribution(): void
    {
        $this->bill(); $this->bill(2, 3);
        DB::table('hidrovia_transshipment_allocations')->insert(['bill_of_lading_id' => 2, 'shipment_item_id' => 21,
            'target_shipment_id' => 3, 'container_id' => 11, 'package_quantity' => 10, 'gross_weight_kg' => 100]);
        $before = DB::table('hidrovia_transshipment_allocations')->get()->toJson();
        $this->removeBill()->assertSessionHas('success')->assertSessionMissing('error');
        $this->assertSoftDeleted('bills_of_lading', ['id' => 1]);
        $this->assertDatabaseHas('containers', ['id' => 11]);
        $this->assertDatabaseMissing('containers', ['id' => 12]);
        $this->assertDatabaseMissing('shipment_items', ['bill_of_lading_id' => 1]);
        $this->assertSame($before, DB::table('hidrovia_transshipment_allocations')->get()->toJson());
    }

    public function test_foreign_key_failure_after_child_deletion_rolls_back_every_table(): void
    {
        $this->bill(); $this->bill(2, 2);
        DB::unprepared("CREATE TRIGGER reject_statistics_by_fk BEFORE UPDATE ON voyages BEGIN
            INSERT INTO hidrovia_transshipment_allocations
                (bill_of_lading_id, shipment_item_id, target_shipment_id, package_quantity, gross_weight_kg)
                VALUES (1, 11, 999, 1, 1); END");
        $this->assertBlocked();
        $this->assertStringContainsString('No se realizó ningún cambio', session('error'));
    }

    public function test_unlocked_controller_offers_only_accessible_deletion_actions(): void
    {
        $this->bill();
        $view = app(BillOfLadingController::class)->index(Request::create('/company/bills-of-lading', 'GET', ['shipment_id' => 1]));
        $this->assertTrue($view->getData()['canDeleteBillIds'][1]);
        $this->assertSame(1, $view->getData()['bulkDeleteVesselInfo']['count']);
        $this->assertSame(1, $view->getData()['bulkDeleteVesselInfo']['voyage_id']);
        $this->assertSame(1, $view->getData()['bulkDeleteVesselInfo']['vessel_id']);
        DB::table('companies')->where('id', 1)->update(['company_roles' => '["Desconsolidador"]']);
        $this->actingAs(User::findOrFail(auth()->id()));
        $view = app(BillOfLadingController::class)->index(Request::create('/company/bills-of-lading', 'GET', ['shipment_id' => 1]));
        $this->assertFalse($view->getData()['canDeleteBillIds'][1]);
        $this->assertNull($view->getData()['bulkDeleteVesselInfo']);
    }

    public function test_blade_templates_compile_without_writing_view_cache(): void
    {
        foreach (['index', 'show'] as $name) {
            $compiled = app('blade.compiler')->compileString(file_get_contents(resource_path('views/company/bills-of-lading/'.$name.'.blade.php')));
            $this->assertNotEmpty($compiled);
            $path = tempnam(sys_get_temp_dir(), 'bl-blade-');
            try {
                file_put_contents($path, $compiled);
                $output = [];
                exec('php -l '.escapeshellarg($path).' 2>&1', $output, $status);
                $this->assertSame(0, $status, implode("\n", $output));
            } finally {
                unlink($path);
            }
        }
    }
}
