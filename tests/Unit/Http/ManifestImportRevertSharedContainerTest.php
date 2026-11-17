<?php

namespace Tests\Unit\Http;

use App\Http\Controllers\Company\Manifests\ManifestImportController;
use App\Models\Company;
use App\Models\ManifestImport;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Esquema mínimo aislado en SQLite :memory:. Un contenedor reutilizado por
 * número entre dos viajes no se pierde al revertir una de las importaciones.
 */
class ManifestImportRevertSharedContainerTest extends TestCase
{
    private const SHARED = 500;
    private const OWN = 501;

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

        Schema::create('companies', function (Blueprint $t) {
            $t->id(); $t->string('legal_name')->nullable(); $t->timestamps();
        });
        Schema::create('voyages', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('company_id'); $t->string('voyage_number');
            $t->string('status')->default('planning'); $t->timestamps();
        });
        Schema::create('shipments', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('voyage_id'); $t->unsignedBigInteger('vessel_id')->nullable(); $t->timestamps();
        });
        Schema::create('bills_of_lading', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('shipment_id'); $t->string('bill_number');
            $t->timestamps(); $t->softDeletes();
        });
        Schema::create('shipment_items', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('bill_of_lading_id'); $t->timestamps();
        });
        Schema::create('containers', function (Blueprint $t) {
            $t->id(); $t->string('container_number'); $t->timestamps();
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
            $t->longText('response_xml')->nullable(); $t->json('success_data')->nullable(); $t->json('additional_metadata')->nullable();
        });
        Schema::create('manifest_imports', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('company_id'); $t->unsignedBigInteger('voyage_id')->nullable();
            $t->string('status')->default('completed'); $t->boolean('can_be_reverted')->default(true);
            $t->string('revert_blocked_reason')->nullable();
            foreach (['voyage', 'shipment', 'bill', 'item', 'container'] as $type) {
                $t->json("created_{$type}_ids")->nullable();
            }
            $t->timestamp('reverted_at')->nullable(); $t->unsignedBigInteger('reverted_by_user_id')->nullable();
            $t->json('revert_details')->nullable(); $t->timestamps();
        });

        DB::table('companies')->insert(['id' => 1, 'legal_name' => 'Empresa QA']);

        // Viaje A, importado antes: su ítem usa el contenedor compartido.
        DB::table('voyages')->insert(['id' => 1, 'company_id' => 1, 'voyage_number' => 'V-A']);
        DB::table('shipments')->insert(['id' => 10, 'voyage_id' => 1]);
        DB::table('bills_of_lading')->insert(['id' => 100, 'shipment_id' => 10, 'bill_number' => 'BL-A']);
        DB::table('shipment_items')->insert(['id' => 1000, 'bill_of_lading_id' => 100]);

        // Viaje B: reutiliza el contenedor compartido y crea uno propio.
        DB::table('voyages')->insert(['id' => 2, 'company_id' => 1, 'voyage_number' => 'V-B']);
        DB::table('shipments')->insert(['id' => 20, 'voyage_id' => 2]);
        DB::table('bills_of_lading')->insert(['id' => 200, 'shipment_id' => 20, 'bill_number' => 'BL-B']);
        DB::table('shipment_items')->insert(['id' => 2000, 'bill_of_lading_id' => 200]);

        DB::table('containers')->insert([
            ['id' => self::SHARED, 'container_number' => 'MSCU1234565'],
            ['id' => self::OWN, 'container_number' => 'TGHU7654321'],
        ]);
        DB::table('container_shipment_item')->insert([
            ['container_id' => self::SHARED, 'shipment_item_id' => 1000],
            ['container_id' => self::SHARED, 'shipment_item_id' => 2000],
            ['container_id' => self::OWN, 'shipment_item_id' => 2000],
        ]);

        // La importación B anota el contenedor reutilizado como creado.
        DB::table('manifest_imports')->insert([
            'id' => 2, 'company_id' => 1, 'voyage_id' => 2,
            'created_voyage_ids' => '[2]', 'created_shipment_ids' => '[20]', 'created_bill_ids' => '[200]',
            'created_item_ids' => '[2000]', 'created_container_ids' => json_encode([self::SHARED, self::OWN]),
        ]);

        $user = new User(['name' => 'Operador QA']);
        $user->id = 7;
        $user->userable_type = Company::class;
        $user->setRelation('userable', Company::find(1));
        Auth::setUser($user);
    }

    public function test_reverting_import_b_keeps_the_container_shared_with_voyage_a(): void
    {
        (new ManifestImportController())->revert(new Request(), ManifestImport::findOrFail(2));

        $this->assertSame('reverted', ManifestImport::find(2)->status);

        // El contenedor compartido y su vínculo con el viaje A quedan intactos.
        $this->assertTrue(DB::table('containers')->where('id', self::SHARED)->exists());
        $this->assertSame(
            [['container_id' => self::SHARED, 'shipment_item_id' => 1000]],
            DB::table('container_shipment_item')->get()->map(fn ($row) => (array) $row)->all()
        );
        $this->assertTrue(DB::table('voyages')->where('id', 1)->exists());
        $this->assertTrue(DB::table('shipment_items')->where('id', 1000)->exists());
        $this->assertNull(DB::table('bills_of_lading')->where('id', 100)->value('deleted_at'));

        // Lo propio de la importación B se elimina.
        $this->assertFalse(DB::table('containers')->where('id', self::OWN)->exists());
        $this->assertFalse(DB::table('shipment_items')->where('id', 2000)->exists());
        $this->assertNotNull(DB::table('bills_of_lading')->where('id', 200)->value('deleted_at'));
        $this->assertFalse(DB::table('voyages')->where('id', 2)->exists());
    }
}
