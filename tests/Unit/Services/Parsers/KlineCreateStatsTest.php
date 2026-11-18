<?php

namespace Tests\Unit\Services\Parsers;

use App\Services\Parsers\KlineDataParser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;
use Tests\Unit\Services\Parsers\Concerns\BuildsManifestImportSchema;

class KlineCreateStatsTest extends TestCase
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
        DB::table('cargo_types')->insert(['code' => 'VEH001', 'name' => 'VEHICULOS', 'active' => 1]);
        DB::table('packaging_types')->insert(['code' => 'N', 'name' => 'NO RETORNABLE', 'active' => 1]);
    }

    public function test_create_recalculates_totals_from_imported_items(): void
    {
        $dat = <<<'DAT'
BLNORECKKLU-TEST-01
GNRLREC0ARBUE PYASU
PTYIREC00001SH SHIPPER TEST
PTYIREC00001CN CONSIGNEE TEST
CMMDREC00000001NAUT00000002VEHICLES00015000000KGS000003000M3
DESCREC0000001TWO VEHICLES
DAT;
        $temporary = tempnam(sys_get_temp_dir(), 'kln');
        $path = $temporary . '.dat';
        rename($temporary, $path);

        try {
            file_put_contents($path, $dat);
            $parser = app(KlineDataParser::class);
            $this->assertTrue($parser->canParse($path));
            $result = $parser->parse($path, ['vessel_id' => self::VESSEL, 'voyage_number' => 'KLINE-TEST-01']);
            $this->assertTrue($result->success, implode(' | ', $result->errors));

            $this->assertSame(1, DB::table('voyages')->count());
            $this->assertSame(1, DB::table('shipments')->count());
            $this->assertSame(1, DB::table('bills_of_lading')->count());

            $shipment = DB::table('shipments')->first();
            $voyage = DB::table('voyages')->where('id', $shipment->voyage_id)->first();
            $items = DB::table('shipment_items')
                ->join('bills_of_lading', 'bills_of_lading.id', '=', 'shipment_items.bill_of_lading_id')
                ->where('bills_of_lading.shipment_id', $shipment->id);
            $this->assertGreaterThanOrEqual(1, (clone $items)->count());
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
