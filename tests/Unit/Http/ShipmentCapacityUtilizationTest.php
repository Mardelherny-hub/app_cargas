<?php

namespace Tests\Unit\Http;

use App\Http\Controllers\Company\ShipmentController;
use App\Models\Company;
use App\Models\Shipment;
use App\Models\Voyage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Unit\Services\Parsers\Concerns\BuildsManifestImportSchema;

/**
 * Uso de capacidad del viaje al crear una carga en un convoy: el peso cargado
 * está en kg y la capacidad en toneladas. Datos ficticios en SQLite :memory:.
 */
class ShipmentCapacityUtilizationTest extends TestCase
{
    use BuildsManifestImportSchema;

    private const BARGE = 2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useIsolatedDatabase();
        $this->addVessel('REMOLCADOR QA');
        DB::table('vessels')->where('id', self::VESSEL)->update(['cargo_capacity_tons' => 1000]);
        DB::table('vessels')->insert([
            'id' => self::BARGE, 'company_id' => self::COMPANY, 'name' => 'BARCAZA QA',
            'active' => 1, 'cargo_capacity_tons' => 500, 'container_capacity' => 0,
        ]);
        DB::table('voyages')->insert([
            'id' => 1, 'company_id' => self::COMPANY, 'lead_vessel_id' => self::VESSEL,
            'voyage_number' => 'QA-01', 'status' => 'planning', 'is_convoy' => 1,
        ]);
    }

    /** Carga existente del viaje, con su peso en kg. */
    protected function existingLoad(float $kg): void
    {
        DB::table('shipments')->insert([
            'voyage_id' => 1, 'vessel_id' => self::VESSEL, 'shipment_number' => 'QA-01-01',
            'sequence_in_voyage' => 1, 'vessel_role' => 'lead', 'is_lead_vessel' => 1,
            'status' => 'planning', 'cargo_capacity_tons' => 1000, 'cargo_weight_loaded' => $kg,
        ]);
    }

    protected function controller(): ShipmentController
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

    /** Alta de la barcaza empujada, como la envía el formulario. */
    protected function storeBarge()
    {
        $request = Request::create('/company/shipments', 'POST', [
            'voyage_id' => 1,
            'vessel_id' => self::BARGE,
            'shipment_number' => 'QA-01-02',
            'vessel_role' => 'pushed',
            'convoy_position' => 2,
            'status' => 'planning',
            'is_lead_vessel' => '0',
        ]);
        $this->app->instance('request', $request);

        return $this->controller()->store($request);
    }

    public function test_convoy_utilization_converts_kg_to_tons(): void
    {
        // 1.473.555 kg sobre 1.000 t + 500 t = 98,237 %.
        $this->existingLoad(1473555);

        $this->storeBarge();

        $this->assertTrue(Shipment::where('shipment_number', 'QA-01-02')->exists());
        $this->assertSame('98.24', Voyage::findOrFail(1)->capacity_utilization_percentage);
    }
}
