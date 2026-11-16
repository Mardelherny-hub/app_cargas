<?php

namespace Tests\Unit\Http;

use App\Http\Controllers\Company\Manifests\ManifestImportController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Esquema mínimo aislado en SQLite :memory:. Reglas que la pantalla y el
 * alta aplican antes de encolar una importación a un viaje existente.
 */
class ManifestImportTargetVoyageRulesTest extends TestCase
{
    private const VOYAGE = 10;
    private const BARGE = 100;
    private const NEW_BARGE = 200;
    private const TUG = 300;
    private const FOREIGN_TUG = 400;

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
            $t->id(); $t->string('ws_environment')->nullable(); $t->timestamps();
        });
        Schema::create('vessels', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('company_id'); $t->string('name');
            $t->boolean('active')->default(true); $t->string('operational_status')->default('active');
        });
        Schema::create('voyages', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('company_id'); $t->string('voyage_number');
            $t->string('status')->default('planning'); $t->unsignedBigInteger('lead_vessel_id')->nullable();
            $t->boolean('is_convoy')->default(false); $t->timestamps();
        });
        Schema::create('shipments', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('voyage_id'); $t->unsignedBigInteger('vessel_id');
        });
        Schema::create('webservice_transactions', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('company_id');
            $t->unsignedBigInteger('voyage_id')->nullable(); $t->unsignedBigInteger('shipment_id')->nullable();
            $t->string('soap_action')->nullable(); $t->string('country', 2)->default('AR');
            $t->string('environment')->default('testing'); $t->string('status')->default('pending');
            $t->string('external_reference')->nullable(); $t->longText('request_xml')->nullable();
            $t->json('success_data')->nullable(); $t->json('additional_metadata')->nullable();
        });

        DB::table('companies')->insert([['id' => 1, 'ws_environment' => 'testing'], ['id' => 2, 'ws_environment' => 'testing']]);
        DB::table('vessels')->insert([
            ['id' => self::BARGE, 'company_id' => 1, 'name' => 'BARCAZA 1'],
            ['id' => self::NEW_BARGE, 'company_id' => 1, 'name' => 'BARCAZA 2'],
            ['id' => self::TUG, 'company_id' => 1, 'name' => 'OBSERVADOR'],
            ['id' => self::FOREIGN_TUG, 'company_id' => 2, 'name' => 'AJENO'],
        ]);
        DB::table('voyages')->insert([
            ['id' => self::VOYAGE, 'company_id' => 1, 'voyage_number' => 'V-QA', 'lead_vessel_id' => self::BARGE, 'status' => 'planning'],
            ['id' => 11, 'company_id' => 1, 'voyage_number' => 'V-SALIDO', 'lead_vessel_id' => self::BARGE, 'status' => 'in_transit'],
        ]);
        DB::table('shipments')->insert(['id' => 1, 'voyage_id' => self::VOYAGE, 'vessel_id' => self::BARGE]);
    }

    private function inspect(int $vesselId, ?int $leaderId = null, int $voyageId = self::VOYAGE): array
    {
        return (new ReflectionMethod(ManifestImportController::class, 'inspectTargetVoyage'))
            ->invoke(new ManifestImportController(), 1, $voyageId, $vesselId, $leaderId);
    }

    public function test_vessel_already_in_the_voyage_is_added_without_conversion(): void
    {
        $this->assertSame(['allowed' => true, 'needs_convoy' => false, 'error' => null], $this->inspect(self::BARGE, self::TUG));
    }

    public function test_new_vessel_on_a_simple_voyage_requires_choosing_a_leader(): void
    {
        $check = $this->inspect(self::NEW_BARGE);
        $this->assertFalse($check['allowed']);
        $this->assertTrue($check['needs_convoy']);
        $this->assertStringContainsString('conviértalo en convoy', $check['error']);

        $this->assertSame(['allowed' => true, 'needs_convoy' => true, 'error' => null], $this->inspect(self::NEW_BARGE, self::TUG));
    }

    public function test_leader_must_belong_to_the_company(): void
    {
        $check = $this->inspect(self::NEW_BARGE, self::FOREIGN_TUG);
        $this->assertFalse($check['allowed']);
        $this->assertStringContainsString('líder', $check['error']);
    }

    public function test_conversion_is_blocked_by_afip_records_of_any_vessel_in_the_voyage(): void
    {
        DB::table('webservice_transactions')->insert([
            'company_id' => 1, 'voyage_id' => self::VOYAGE, 'shipment_id' => 1,
            'soap_action' => 'RegistrarMicDta', 'status' => 'success', 'external_reference' => 'M1',
        ]);

        $check = $this->inspect(self::NEW_BARGE, self::TUG);
        $this->assertFalse($check['allowed']);
        $this->assertStringContainsString('BARCAZA 1', $check['error']);
    }

    public function test_voyage_must_be_in_planning(): void
    {
        $check = $this->inspect(self::BARGE, null, 11);
        $this->assertFalse($check['allowed']);
        $this->assertStringContainsString('planificación', $check['error']);
    }
}
