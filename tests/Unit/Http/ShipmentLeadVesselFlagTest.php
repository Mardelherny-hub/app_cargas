<?php

namespace Tests\Unit\Http;

use App\Http\Controllers\Company\ShipmentController;
use App\Models\Company;
use App\Models\Shipment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Unit\Services\Parsers\Concerns\BuildsManifestImportSchema;

/**
 * La marca de embarcación líder de una carga sale del valor enviado por el
 * formulario, no de que el campo exista: el alta lo envía siempre oculto.
 * Datos ficticios en SQLite :memory:.
 */
class ShipmentLeadVesselFlagTest extends TestCase
{
    use BuildsManifestImportSchema;

    private const BARGE = 2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useIsolatedDatabase();
        $this->addVessel('REMOLCADOR QA');
        DB::table('vessels')->insert([
            'id' => self::BARGE, 'company_id' => self::COMPANY, 'name' => 'BARCAZA QA',
            'active' => 1, 'cargo_capacity_tons' => 1500, 'container_capacity' => 0,
        ]);
        DB::table('voyages')->insert([
            'id' => 1, 'company_id' => self::COMPANY, 'lead_vessel_id' => self::VESSEL,
            'voyage_number' => 'QA-01', 'status' => 'planning', 'is_convoy' => 1,
        ]);
    }

    private function controller(): ShipmentController
    {
        return new class extends ShipmentController {
            protected function canPerform(string $action): bool
            {
                return true;
            }

            protected function hasCompanyRole(string $businessRole): bool
            {
                return true;
            }

            protected function getUserCompany(): ?Company
            {
                return Company::find(1);
            }

            protected function isUser(): bool
            {
                return false;
            }
        };
    }

    private function store(array $fields): Shipment
    {
        $request = Request::create('/company/shipments', 'POST', array_merge([
            'voyage_id' => 1,
            'shipment_number' => 'QA-01-02',
            'status' => 'planning',
            'is_lead_vessel' => '0',
        ], $fields));
        $this->app->instance('request', $request);

        $this->controller()->store($request);

        return Shipment::where('shipment_number', 'QA-01-02')->firstOrFail();
    }

    public function test_pushed_barge_is_not_saved_as_lead(): void
    {
        $shipment = $this->store(['vessel_id' => self::BARGE, 'vessel_role' => 'pushed', 'convoy_position' => 2]);

        $this->assertFalse($shipment->is_lead_vessel);
    }

    public function test_lead_role_is_saved_as_lead(): void
    {
        $shipment = $this->store(['vessel_id' => self::VESSEL, 'vessel_role' => 'lead', 'convoy_position' => 1]);

        $this->assertTrue($shipment->is_lead_vessel);
    }

    public function test_update_reads_the_sent_value(): void
    {
        $shipment = Shipment::create([
            'voyage_id' => 1, 'vessel_id' => self::BARGE, 'shipment_number' => 'QA-01-02',
            'sequence_in_voyage' => 2, 'vessel_role' => 'pushed', 'convoy_position' => 2,
            'is_lead_vessel' => true, 'status' => 'planning', 'created_by_user_id' => self::USER,
        ]);

        $request = Request::create("/company/shipments/{$shipment->id}", 'PUT', [
            'shipment_number' => 'QA-01-02',
            'vessel_role' => 'pushed',
            'convoy_position' => 2,
            'is_lead_vessel' => '0',
            'captain_id' => '',
            'status' => 'planning',
        ]);
        $this->app->instance('request', $request);

        $this->controller()->update($request, $shipment);

        $this->assertFalse($shipment->fresh()->is_lead_vessel);
    }
}
