<?php

namespace Tests\Unit\Services\Parsers;

use App\Models\Container;
use App\Models\ShipmentItem;
use App\Services\Parsers\TfpTextParser;
use Illuminate\Support\Facades\DB;
use ReflectionMethod;
use Tests\TestCase;
use Tests\Unit\Services\Parsers\Concerns\BuildsManifestImportSchema;

/**
 * El vínculo de cada conocimiento guarda los precintos que declara su archivo,
 * aunque el contenedor ya exista con otros. Números ficticios.
 */
class TfpTextParserSealTest extends TestCase
{
    use BuildsManifestImportSchema;

    public function test_link_keeps_the_seals_declared_by_this_file(): void
    {
        $this->useIsolatedDatabase();
        $container = Container::create(['container_number' => 'TSTU0000001', 'shipper_seal' => 'QA000099']);
        $withSeals = ShipmentItem::create(['bill_of_lading_id' => 1, 'line_number' => 1, 'package_quantity' => 5]);
        $withoutSeals = ShipmentItem::create(['bill_of_lading_id' => 2, 'line_number' => 1, 'package_quantity' => 5]);

        $attach = new ReflectionMethod(TfpTextParser::class, 'attachContainerToItem');
        $attach->setAccessible(true);
        $parser = new TfpTextParser();
        $attach->invoke($parser, $container, $withSeals, ['nro_precinta' => 'QA100001 QA100002  QA100001']);
        $attach->invoke($parser, $container, $withoutSeals, ['nro_precinta' => '']);

        $links = DB::table('container_shipment_item')->orderBy('shipment_item_id')->pluck('source_seals')->all();
        $this->assertSame(['QA100001', 'QA100002'], json_decode($links[0], true));
        $this->assertNull($links[1]);
        $this->assertSame('QA000099', $container->fresh()->shipper_seal);
    }
}
