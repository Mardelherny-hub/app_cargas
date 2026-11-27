<?php

namespace Tests\Unit\Services\Parsers;

use App\Models\BillOfLading;
use App\Models\Container;
use App\Services\Parsers\TfpTextParserCompat;
use Exception;
use Illuminate\Support\Facades\DB;
use ReflectionMethod;
use Tests\TestCase;
use Tests\Unit\Services\Parsers\Concerns\BuildsManifestImportSchema;

class TfpTextParserMissingPackagesTest extends TestCase
{
    use BuildsManifestImportSchema;

    public function test_empty_container_with_missing_packages_saves_zero_on_item_and_link(): void
    {
        $this->useIsolatedDatabase();
        DB::table('packaging_types')->where('name', 'CONTENEDOR')->update(['code' => 'T']);
        $bill = BillOfLading::create(['bill_number' => 'REGRESSION-TFP']);
        $parser = new TfpTextParserCompat();
        $createItem = new ReflectionMethod($parser, 'createShipmentItem');
        $attach = new ReflectionMethod($parser, 'attachContainerToItem');

        foreach (['VACIO', 'VACIOS'] as $index => $description) {
            $number = 'TSTU000000' . ($index + 1);
            $container = Container::create(['container_number' => $number, 'condition' => 'V']);
            $item = $createItem->invoke($parser, $bill, [
                'naturaleza_mercaderia' => $description,
                'cant_total_bultos' => '',
                'peso_total_bultos' => '',
                'contenedor' => $number,
            ], true);
            $attach->invoke($parser, $container, $item, ['cantidad' => '']);

            $this->assertSame('0', (string) $item->fresh()->package_quantity);
            $this->assertSame('0', (string) DB::table('container_shipment_item')
                ->where('shipment_item_id', $item->id)->value('package_quantity'));
        }
    }

    public function test_loaded_container_with_missing_packages_is_rejected(): void
    {
        $this->useIsolatedDatabase();
        $bill = BillOfLading::create(['bill_number' => 'REGRESSION-TFP']);
        $parser = new TfpTextParserCompat();
        $createItem = new ReflectionMethod($parser, 'createShipmentItem');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('TFP: cantidad de bultos ausente o inválida.');

        $createItem->invoke($parser, $bill, [
            'naturaleza_mercaderia' => 'CARGA GENERAL',
            'cant_total_bultos' => '',
            'peso_total_bultos' => '100',
            'contenedor' => 'TSTU0000001',
        ], true);
    }
}
