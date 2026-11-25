<?php

namespace Tests\Unit\Http;

use App\Http\Controllers\Company\ShipmentController;
use App\Models\Company;
use App\Models\Shipment;
use App\Models\Voyage;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
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

    public function test_database_error_reaches_the_screen_without_sql(): void
    {
        $this->existingLoad(1000);
        Shipment::creating(function () {
            throw new QueryException('mysql', 'update `voyages` set `capacity_utilization_percentage` = ?', [98237],
                new \PDOException("SQLSTATE[22003]: Numeric value out of range: 1264 Out of range value for column 'capacity_utilization_percentage'"));
        });

        $response = $this->storeBarge();
        $error = $response->getSession()->get('error');

        $this->assertStringStartsWith('No se pudo crear la carga: ocurrió un error al guardar los datos.', $error);
        $this->assertStringNotContainsString('SQLSTATE', $error);
        $this->assertStringNotContainsString('voyages', $error);
        $this->assertFalse(Shipment::where('shipment_number', 'QA-01-02')->exists());
    }

    public function test_create_and_edit_screens_show_the_session_error(): void
    {
        session()->put('error', 'No se pudo crear la carga: prueba.');

        foreach (['create', 'edit'] as $view) {
            $source = file_get_contents(resource_path("views/company/shipments/{$view}.blade.php"));
            $start = strpos($source, '{{-- Mensajes flash --}}');
            $this->assertNotFalse($start, $view);
            $end = strpos($source, '{{-- Errores', $start);

            $this->assertStringContainsString('No se pudo crear la carga: prueba.', Blade::render(substr($source, $start, $end - $start)), $view);
        }
    }

    public function test_over_capacity_is_saved_and_warned(): void
    {
        // 1.800.000 kg sobre 1.500 t = 120 %.
        $this->existingLoad(1800000);

        $response = $this->storeBarge();

        $this->assertTrue(Shipment::where('shipment_number', 'QA-01-02')->exists());
        $this->assertSame('120.00', Voyage::findOrFail(1)->capacity_utilization_percentage);
        $this->assertSame(
            'El peso cargado del viaje supera su capacidad: 120,00 % de la capacidad de sus embarcaciones. '
            . 'Revise el peso de los ítems y la capacidad declarada de las embarcaciones.',
            $response->getSession()->get('warning')
        );
    }

    public function test_value_beyond_the_column_is_capped(): void
    {
        // 30.000.000 kg sobre 1.500 t = 2.000 %: la columna admite hasta 999,99.
        $this->existingLoad(30000000);

        $response = $this->storeBarge();

        $this->assertTrue(Shipment::where('shipment_number', 'QA-01-02')->exists());
        $this->assertSame('999.99', Voyage::findOrFail(1)->capacity_utilization_percentage);
        $this->assertStringContainsString('2.000,00 %', $response->getSession()->get('warning'));
    }

    public function test_no_warning_within_capacity(): void
    {
        $this->existingLoad(1473555);

        $this->assertNull($this->storeBarge()->getSession()->get('warning'));
    }

    public function test_cargo_page_shows_the_warning(): void
    {
        session()->put('warning', 'El peso cargado del viaje supera su capacidad: prueba.');
        $source = file_get_contents(resource_path('views/company/shipments/show.blade.php'));
        $start = strpos($source, '{{-- Mensajes Flash --}}');
        $end = strpos($source, "@if(session('error'))", $start);

        $this->assertStringContainsString('supera su capacidad: prueba.', Blade::render(substr($source, $start, $end - $start)));
    }
}
