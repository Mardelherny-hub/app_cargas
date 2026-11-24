<?php

namespace Tests\Unit\Services;

use App\Models\BillOfLading;
use App\Models\ManifestImport;
use App\Models\Shipment;
use App\Models\ShipmentItem;
use App\Models\Voyage;
use App\Services\ManifestImportRevertService;
use ReflectionMethod;
use Tests\TestCase;
use Tests\Unit\Services\Parsers\Concerns\BuildsManifestImportSchema;

/**
 * El resumen de una reversión habla en español y con el número correcto:
 * "1 carga, 1 viaje", no "1 envíos, 1 viajes". Datos ficticios.
 */
class ManifestImportRevertSummaryTest extends TestCase
{
    use BuildsManifestImportSchema;

    public function test_summary_uses_spanish_names_and_singular_for_one(): void
    {
        $this->useIsolatedDatabase();
        $voyage = Voyage::create(['voyage_number' => 'QA-1', 'company_id' => self::COMPANY]);
        $shipment = Shipment::create(['voyage_id' => $voyage->id]);
        $bills = collect([1, 2])->map(fn ($n) => BillOfLading::create(['shipment_id' => $shipment->id, 'bill_number' => "QA-BL-$n"]));
        $item = ShipmentItem::create(['bill_of_lading_id' => $bills[0]->id, 'line_number' => 1]);
        $import = ManifestImport::create([
            'company_id' => self::COMPANY,
            'status' => 'completed',
            'created_voyage_ids' => [$voyage->id],
            'created_shipment_ids' => [$shipment->id],
            'created_bill_ids' => $bills->pluck('id')->all(),
            'created_item_ids' => [$item->id],
        ]);

        $method = new ReflectionMethod(ManifestImportRevertService::class, 'deleteCreatedObjects');
        $method->setAccessible(true);
        $summary = $method->invoke(app(ManifestImportRevertService::class), $import->fresh());

        $this->assertSame(['2 conocimientos de embarque', '1 ítem de carga', '1 carga', '1 viaje'], $summary);
    }

    public function test_revert_modal_lists_what_is_removed_in_spanish(): void
    {
        $source = file_get_contents(resource_path('views/company/manifests/import-history.blade.php'));

        $this->assertStringNotContainsString('Bills of Lading', $source);
        $this->assertStringNotContainsString('Shipment Items', $source);
    }
}
