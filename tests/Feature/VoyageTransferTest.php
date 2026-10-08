<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Operator;
use App\Models\User;
use App\Models\Voyage;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class VoyageTransferTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Esquema mínimo aislado: nunca ejecutar migraciones ni limpiar una BD externa.
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:',
            'session.driver' => 'array', 'cache.default' => 'array', 'audit.enabled' => false,
            'audit.drivers.database.connection' => null, 'app.key' => 'base64:'.base64_encode(str_repeat('x', 32))]);
        DB::purge('sqlite');
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        (require database_path('migrations/2025_07_04_130831_create_permission_tables.php'))->up();
        (require database_path('migrations/2025_07_04_131055_create_audits_table.php'))->up();
        Schema::create('companies', function (Blueprint $t) {
            $t->id(); $t->string('legal_name'); $t->boolean('active'); $t->text('company_roles'); $t->timestamps();
        });
        Schema::create('users', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->string('email'); $t->string('password');
            $t->string('userable_type'); $t->unsignedBigInteger('userable_id');
            $t->timestamp('email_verified_at')->nullable(); $t->boolean('active')->default(true); $t->timestamps();
        });
        Schema::create('operators', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('company_id');
            $t->string('type')->default('external');
            $t->boolean('can_transfer')->default(false);
            $t->boolean('active')->default(true);
            $t->timestamps();
        });
        Schema::create('voyages', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('company_id'); $t->string('voyage_number');
            $t->unsignedBigInteger('created_by_user_id')->nullable();
            $t->string('status')->default('planning'); $t->date('departure_date')->nullable();
            foreach (['lead_vessel_id', 'captain_id', 'origin_port_id', 'destination_port_id', 'transshipment_port_id'] as $field) $t->unsignedBigInteger($field)->nullable();
            foreach (['argentina', 'paraguay'] as $country) {
                $t->timestamp($country.'_sent_at')->nullable();
                $t->string($country.'_status')->nullable(); $t->string($country.'_voyage_id')->nullable();
            }
            $t->timestamps();
        });
        foreach (['shipments' => 'voyage_id', 'bills_of_lading' => 'shipment_id', 'shipment_items' => 'bill_of_lading_id',
            'containers' => 'bill_of_lading_id', 'voyage_attachments' => 'voyage_id'] as $table => $parent) {
            Schema::create($table, function (Blueprint $t) use ($parent) { $t->id(); $t->unsignedBigInteger($parent); });
        }
        Schema::create('container_shipment_item', function (Blueprint $t) {
            $t->unsignedBigInteger('container_id'); $t->unsignedBigInteger('shipment_item_id');
        });
        foreach (['vessels', 'captains', 'ports'] as $table) Schema::create($table, fn (Blueprint $t) => $t->id());
        Schema::create('webservice_transactions', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('company_id');
            $t->unsignedBigInteger('voyage_id')->nullable(); $t->unsignedBigInteger('shipment_id')->nullable();
            $t->string('status')->default('pending'); $t->timestamp('sent_at')->nullable();
            $t->timestamp('response_at')->nullable(); $t->text('request_xml')->nullable();
            $t->text('response_xml')->nullable(); $t->string('confirmation_number')->nullable();
        });
        Schema::create('voyage_webservice_statuses', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('voyage_id'); $t->string('status');
            $t->string('confirmation_number')->nullable(); $t->string('external_voyage_number')->nullable();
            foreach (['first_sent_at', 'last_sent_at', 'approved_at'] as $f) $t->timestamp($f)->nullable();
        });
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        Role::create(['name' => 'company-admin', 'guard_name' => 'web']);
        Role::create(['name' => 'user', 'guard_name' => 'web']);
        Permission::create(['name' => 'voyages.transfer', 'guard_name' => 'web']);
        DB::table('companies')->insert([
            ['id' => 1, 'legal_name' => 'Origen QA', 'active' => true, 'company_roles' => '["Cargas"]'],
            ['id' => 2, 'legal_name' => 'Destino QA', 'active' => true, 'company_roles' => '["Cargas"]'],
            ['id' => 3, 'legal_name' => 'Inactiva QA', 'active' => false, 'company_roles' => '["Cargas"]'],
        ]);
        DB::table('voyages')->insert(['id' => 1, 'company_id' => 1, 'voyage_number' => 'VIAJE-QA']);
    }

    private function admin(int $company = 1, bool $permission = true, string $role = 'company-admin'): User
    {
        $id = DB::table('users')->insertGetId(['name' => 'QA', 'email' => uniqid().'@example.test', 'password' => 'unused',
            'userable_type' => Company::class, 'userable_id' => $company, 'email_verified_at' => now(), 'active' => true]);
        $user = User::findOrFail($id);
        $user->assignRole($role);
        if ($permission) $user->givePermissionTo('voyages.transfer');
        return $user;
    }

    private function operatorUser(
        int $company = 1,
        bool $canTransfer = true,
        bool $permission = true,
        bool $active = true
    ): User {
        $operator = Operator::create([
            'company_id' => $company,
            'type' => 'external',
            'can_transfer' => $canTransfer,
            'active' => $active,
        ]);

        $id = DB::table('users')->insertGetId([
            'name' => 'Operador QA',
            'email' => uniqid().'@example.test',
            'password' => 'unused',
            'userable_type' => Operator::class,
            'userable_id' => $operator->id,
            'email_verified_at' => now(),
            'active' => true,
        ]);

        $user = User::findOrFail($id);
        $user->assignRole('user');
        if ($permission) {
            $user->givePermissionTo('voyages.transfer');
        }

        return $user;
    }

    private function sendTransfer($destination = 2)
    {
        return $this->from('/company/voyages/1')->post(route('company.voyages.transfer', 1), ['destination_company_id' => $destination]);
    }

    public function test_authorized_transfer_preserves_children_and_creates_audit(): void
    {
        foreach (['shipments' => 'voyage_id', 'bills_of_lading' => 'shipment_id', 'shipment_items' => 'bill_of_lading_id',
            'containers' => 'bill_of_lading_id', 'voyage_attachments' => 'voyage_id'] as $table => $parent) {
            DB::table($table)->insert(['id' => 1, $parent => 1]);
        }
        DB::table('container_shipment_item')->insert(['container_id' => 1, 'shipment_item_id' => 1]);
        DB::table('webservice_transactions')->insert(['company_id' => 1, 'voyage_id' => 1, 'status' => 'pending']);
        $tables = ['shipments', 'bills_of_lading', 'shipment_items', 'containers', 'container_shipment_item', 'voyage_attachments', 'webservice_transactions'];
        $before = []; foreach ($tables as $table) $before[$table] = DB::table($table)->get()->toJson();
        $user = $this->admin(); $this->actingAs($user);
        $this->sendTransfer()->assertRedirect(route('company.voyages.index'))->assertSessionHas('success');
        $this->assertDatabaseHas('voyages', ['id' => 1, 'company_id' => 2, 'voyage_number' => 'VIAJE-QA']);
        foreach ($tables as $table) $this->assertSame($before[$table], DB::table($table)->get()->toJson(), $table);
        $audit = DB::table('audits')->sole();
        $this->assertEquals($user->id, $audit->user_id);
        $this->assertSame(Voyage::class, $audit->auditable_type);
        $this->assertEquals(1, $audit->auditable_id);
        $this->assertSame('transferred', $audit->event);
        $this->assertSame(['company_id' => 1], json_decode($audit->old_values, true));
        $this->assertSame(['company_id' => 2], json_decode($audit->new_values, true));
        $this->assertNotNull($audit->created_at);
        $this->get(route('company.voyages.show', 1))->assertForbidden();

        // Ejecutar ruta, middleware y controller reales; excluir sólo el render del layout ajeno al flujo.
        app('view.engine.resolver')->register('blade', fn () => new class implements \Illuminate\Contracts\View\Engine {
            public function get($path, array $data = []) { return 'Vista del viaje'; }
        });
        $this->actingAs($this->admin(2))->get(route('company.voyages.show', 1))->assertOk()->assertViewHas('voyage', fn ($v) => $v->company_id === 2);
        DB::table('companies')->where('id', 2)->update(['company_roles' => '[]']);
        $this->actingAs($this->admin(2))->get(route('company.voyages.show', 1))->assertOk();
        $this->get(route('company.voyages.index'))->assertOk()
            ->assertViewHas('voyages', fn ($voyages) => $voyages->pluck('id')->all() === [1]);
        $this->actingAs($this->admin(1))->get(route('company.voyages.index'))->assertOk()
            ->assertViewHas('voyages', fn ($voyages) => $voyages->isEmpty());
    }

    public function test_third_company_admin_cannot_access_received_voyage(): void
    {
        $this->actingAs($this->admin());
        $this->sendTransfer()->assertSessionHasNoErrors();
        DB::table('companies')->where('id', 3)->update(['active' => true, 'company_roles' => '[]']);
        $thirdAdmin = $this->admin(3);
        Permission::create(['name' => 'voyages.edit', 'guard_name' => 'web']);
        $thirdAdmin->givePermissionTo('voyages.edit');
        $this->actingAs($thirdAdmin);
        $this->get(route('company.voyages.show', 1))->assertForbidden();
        $this->get(route('company.voyages.edit', 1))->assertForbidden();
        $this->put(route('company.voyages.update', 1), [])->assertForbidden();
        $this->assertDatabaseHas('voyages', ['id' => 1, 'company_id' => 2]);
    }

    public function test_destination_operator_without_cargas_does_not_gain_admin_access(): void
    {
        $this->actingAs($this->admin());
        $this->sendTransfer()->assertSessionHasNoErrors();
        DB::table('companies')->where('id', 2)->update(['company_roles' => '[]']);
        DB::table('operators')->insert([
            'id' => 1,
            'company_id' => 2,
            'type' => 'external',
            'can_transfer' => true,
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $operator = $this->admin(2, true, 'user');
        DB::table('users')->where('id', $operator->id)->update([
            'userable_type' => \App\Models\Operator::class, 'userable_id' => 1,
        ]);
        $operator->refresh();
        Permission::create(['name' => 'voyages.edit', 'guard_name' => 'web']);
        $operator->givePermissionTo('voyages.edit');
        $this->actingAs($operator);
        $this->get(route('company.voyages.index'))->assertForbidden();
        $this->get(route('company.voyages.show', 1))->assertForbidden();
        $this->get(route('company.voyages.edit', 1))->assertForbidden();
        $this->put(route('company.voyages.update', 1), [])->assertForbidden();
        $this->assertDatabaseHas('voyages', ['id' => 1, 'company_id' => 2]);
    }

    public function test_other_company_cannot_transfer(): void
    {
        $this->actingAs($this->admin(2)); $this->sendTransfer()->assertForbidden();
        $this->assertDatabaseHas('voyages', ['id' => 1, 'company_id' => 1]);
    }

    public function test_admin_without_permission_cannot_transfer(): void
    {
        $this->actingAs($this->admin(1, false)); $this->sendTransfer()->assertForbidden();
    }

    public function test_non_operator_user_with_permission_cannot_transfer(): void
    {
        $this->actingAs($this->admin(1, true, 'user'));
        $this->sendTransfer()->assertForbidden();
    }

    public function test_operator_with_can_transfer_can_transfer_own_voyage(): void
    {
        $operator = $this->operatorUser();
        DB::table('voyages')->where('id', 1)->update([
            'created_by_user_id' => $operator->id,
        ]);

        $this->actingAs($operator);

        $view = app(\App\Http\Controllers\Company\VoyageController::class)
            ->show(Voyage::findOrFail(1));
        $this->assertTrue($view->getData()['canTransferVoyage']);
        $this->assertFalse($view->getData()['transferBlocked']);
        $this->assertSame(
            [2],
            $view->getData()['transferCompanies']->pluck('id')->all()
        );

        $this->sendTransfer()
            ->assertRedirect(route('company.voyages.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('voyages', [
            'id' => 1,
            'company_id' => 2,
            'created_by_user_id' => $operator->id,
        ]);
        $this->assertDatabaseHas('audits', [
            'event' => 'transferred',
            'auditable_id' => 1,
        ]);
    }

    public function test_operator_without_can_transfer_cannot_transfer(): void
    {
        $operator = $this->operatorUser(canTransfer: false);
        DB::table('voyages')->where('id', 1)->update([
            'created_by_user_id' => $operator->id,
        ]);

        $this->actingAs($operator);
        $this->sendTransfer()->assertForbidden();
        $this->assertDatabaseHas('voyages', ['id' => 1, 'company_id' => 1]);
    }

    public function test_operator_cannot_transfer_another_operators_voyage(): void
    {
        $owner = $this->operatorUser();
        $other = $this->operatorUser();
        DB::table('voyages')->where('id', 1)->update([
            'created_by_user_id' => $owner->id,
        ]);

        $this->actingAs($other);
        $this->sendTransfer()->assertForbidden();
        $this->assertDatabaseHas('voyages', ['id' => 1, 'company_id' => 1]);
    }

    public function test_inactive_operator_cannot_transfer(): void
    {
        $operator = $this->operatorUser(active: false);
        DB::table('voyages')->where('id', 1)->update([
            'created_by_user_id' => $operator->id,
        ]);

        $this->actingAs($operator);
        $this->sendTransfer()->assertForbidden();
        $this->assertDatabaseHas('voyages', ['id' => 1, 'company_id' => 1]);
    }

    public function test_invalid_destinations_are_rejected(): void
    {
        $this->actingAs($this->admin());
        foreach ([1, 3, 999, null, 'invalid'] as $destination) {
            $this->sendTransfer($destination)->assertSessionHasErrors('destination_company_id');
            $this->assertDatabaseHas('voyages', ['id' => 1, 'company_id' => 1]);
        }
        $this->assertDatabaseCount('audits', 0);
    }

    public function test_transmissions_direct_or_via_shipment_block_and_keep_history(): void
    {
        $this->actingAs($this->admin());
        DB::table('shipments')->insert(['id' => 7, 'voyage_id' => 1]);
        foreach ([['voyage_id' => 1], ['shipment_id' => 7]] as $link) {
            foreach ([['sent_at' => now(), 'status' => 'error'], ['status' => 'success'], ['status' => 'sending'], ['response_xml' => '<error/>']] as $evidence) {
                DB::table('webservice_transactions')->delete();
                DB::table('webservice_transactions')->insert($link + $evidence + ['company_id' => 1]);
                $before = DB::table('webservice_transactions')->get()->toJson();
                $this->sendTransfer()->assertSessionHasErrors('transfer');
                $this->assertSame($before, DB::table('webservice_transactions')->get()->toJson());
                $this->assertDatabaseHas('voyages', ['id' => 1, 'company_id' => 1]);
            }
        }
        $this->assertDatabaseCount('audits', 0);
    }

    public function test_local_validation_error_without_send_does_not_block(): void
    {
        DB::table('webservice_transactions')->insert(['company_id' => 1, 'voyage_id' => 1, 'status' => 'error', 'response_at' => now(), 'request_xml' => '<prepared/>']);
        $this->actingAs($this->admin()); $this->sendTransfer()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('voyages', ['id' => 1, 'company_id' => 2]);
        $this->assertDatabaseHas('webservice_transactions', ['company_id' => 1]);
    }

    public function test_country_and_webservice_status_evidence_block(): void
    {
        $this->actingAs($this->admin());
        DB::table('voyages')->where('id', 1)->update(['argentina_sent_at' => now()]);
        $this->sendTransfer()->assertSessionHasErrors('transfer');
        DB::table('voyages')->where('id', 1)->update(['argentina_sent_at' => null]);
        DB::table('voyage_webservice_statuses')->insert(['voyage_id' => 1, 'status' => 'error', 'first_sent_at' => now()]);
        $this->sendTransfer()->assertSessionHasErrors('transfer');
        $this->assertDatabaseHas('voyages', ['id' => 1, 'company_id' => 1]);
    }


    public function test_other_voyage_transmission_does_not_block(): void
    {
        DB::table('webservice_transactions')->insert(['company_id' => 1, 'voyage_id' => 99, 'status' => 'success']);
        $this->actingAs($this->admin()); $this->sendTransfer()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('voyages', ['id' => 1, 'company_id' => 2]);
        $this->assertDatabaseHas('webservice_transactions', ['company_id' => 1, 'voyage_id' => 99]);
    }

    public function test_status_confirmation_without_timestamp_blocks(): void
    {
        $this->actingAs($this->admin());
        foreach (['confirmation_number', 'external_voyage_number'] as $field) {
            DB::table('voyage_webservice_statuses')->delete();
            DB::table('voyage_webservice_statuses')->insert(['voyage_id' => 1, 'status' => 'error', $field => 'QA-1']);
            $this->sendTransfer()->assertSessionHasErrors('transfer');
            $this->assertDatabaseHas('voyages', ['id' => 1, 'company_id' => 1]);
        }
    }

    public function test_audit_failure_leaves_voyage_unchanged(): void
    {
        Schema::drop('audits');
        $this->actingAs($this->admin()); $this->sendTransfer()->assertSessionHasErrors('transfer');
        $this->assertDatabaseHas('voyages', ['id' => 1, 'company_id' => 1]);
    }

    public function test_update_failure_rolls_back_audit(): void
    {
        DB::unprepared("CREATE TRIGGER fail_transfer BEFORE UPDATE ON voyages BEGIN SELECT RAISE(ABORT, 'QA'); END");
        $this->actingAs($this->admin()); $this->sendTransfer()->assertSessionHasErrors('transfer');
        $this->assertDatabaseCount('audits', 0);
        $this->assertDatabaseHas('voyages', ['id' => 1, 'company_id' => 1]);
    }
}
