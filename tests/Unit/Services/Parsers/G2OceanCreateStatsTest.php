<?php

namespace Tests\Unit\Services\Parsers;

use App\Services\Parsers\G2OceanXmlParser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;
use Tests\Unit\Services\Parsers\Concerns\BuildsManifestImportSchema;

class G2OceanCreateStatsTest extends TestCase
{
    use BuildsManifestImportSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useIsolatedDatabase();
        Schema::table('voyages', fn (Blueprint $table) => $table->boolean('active')->default(true)->change());
        Schema::table('shipments', fn (Blueprint $table) => $table->boolean('active')->default(true)->change());
        $this->addVessel('TEST VESSEL');
        $this->addClient('SHIPPER TEST', '99999999991', self::AR);
        $this->addClient('CONSIGNEE TEST', '9999991', self::PY);
    }

    public function test_create_recalculates_totals_from_imported_items(): void
    {
        $xml = <<<'XML'
<Envelope>
  <bill_of_lading>
    <bl_header>
      <blNo>G2-TEST-01</blNo><vesselName>TEST VESSEL</vesselName><voyageNo>G2-VOY-01</voyageNo>
      <portOfLoading><UNLocation><UNLOCODE>ARBUE</UNLOCODE></UNLocation></portOfLoading>
      <portOfDischarge><UNLocation><UNLOCODE>PYASU</UNLOCODE></UNLocation></portOfDischarge>
    </bl_header>
    <bl_party>
      <bl_shipper><partyInfo><organizationName1>SHIPPER TEST</organizationName1></partyInfo></bl_shipper>
      <bl_consignee><partyInfo><organizationName1>CONSIGNEE TEST</organizationName1></partyInfo></bl_consignee>
    </bl_party>
    <bl_detail_list><bl_detail>
      <itemSNo>1</itemSNo><noOfPkgs>2</noOfPkgs><pkgType>PACKAGES</pkgType>
      <weight>1.5</weight><weightUOM>MT</weightUOM><measure>3</measure>
      <cargoDesc><line>GENERAL CARGO</line></cargoDesc>
    </bl_detail></bl_detail_list>
  </bill_of_lading>
</Envelope>
XML;
        $temporary = tempnam(sys_get_temp_dir(), 'g2o');
        $path = $temporary . '.xml';
        rename($temporary, $path);

        try {
            file_put_contents($path, $xml);
            $parser = app(G2OceanXmlParser::class);
            $this->assertTrue($parser->canParse($path));
            $result = $parser->parse($path, ['vessel_id' => self::VESSEL]);
            $this->assertTrue($result->success, implode(' | ', $result->errors));

            $this->assertSame(1, DB::table('voyages')->count());
            $this->assertSame(1, DB::table('shipments')->count());
            $this->assertSame(1, DB::table('bills_of_lading')->count());
            $this->assertGreaterThanOrEqual(1, DB::table('shipment_items')->count());

            $shipment = DB::table('shipments')->first();
            $voyage = DB::table('voyages')->where('id', $shipment->voyage_id)->first();
            $items = DB::table('shipment_items')
                ->join('bills_of_lading', 'bills_of_lading.id', '=', 'shipment_items.bill_of_lading_id')
                ->where('bills_of_lading.shipment_id', $shipment->id);
            $weight = (float) (clone $items)->sum('shipment_items.gross_weight_kg');
            $containers = (clone $items)
                ->join('container_shipment_item', 'container_shipment_item.shipment_item_id', '=', 'shipment_items.id')
                ->distinct()->count('container_shipment_item.container_id');

            $this->assertEquals(1500.0, $weight);
            $this->assertEquals($weight, (float) $shipment->cargo_weight_loaded);
            $this->assertEquals($weight, (float) $voyage->total_cargo_weight);
            $this->assertEquals($weight, (float) $voyage->total_cargo_weight_loaded);
            $this->assertSame(0, $containers);
            $this->assertSame($containers, (int) $shipment->containers_loaded);
            $this->assertSame($containers, (int) $voyage->total_containers);
            $this->assertSame($containers, (int) $voyage->total_containers_loaded);
        } finally {
            @unlink($path);
        }
    }
}
