<?php

namespace Tests\Unit\Services\Parsers;

use App\Models\{ManifestImport, Shipment, Voyage};
use App\Services\Parsers\LoginXmlParserCompat;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use ReflectionMethod;
use ReflectionProperty;
use Tests\TestCase;
use Tests\Unit\Services\Parsers\Concerns\BuildsManifestImportSchema;

class LoginXmlParserDestinacionTest extends TestCase
{
    use BuildsManifestImportSchema;

    public function test_persists_unique_destinacion_and_warns_about_conflicting_values(): void
    {
        $this->useIsolatedDatabase();
        DB::table('packaging_types')->where('name', 'CONTENEDOR')->update(['code' => 'T']);
        $parser = new LoginXmlParserCompat();
        $voyage = (new Voyage())->forceFill(['id' => 1, 'origin_port_id' => 1, 'destination_port_id' => 2]);
        $shipment = (new Shipment())->forceFill(['id' => 1])->setRelation('voyage', $voyage);
        $create = new ReflectionMethod($parser, 'createBillOfLadingFromData');
        $cases = [
            ['CARGA  24001EC01000201A', null, '24001EC01000201A', null],
            ['CARGA NO_PERMISO: 24001EC03000202B', null, '24001EC03000202B', null],
            ['CARGA PE: 24001ET01000203C', '24001ET01000203C', '24001ET01000203C', '24001ET01000203C'],
            ['CARGA NO_PERMISO', null, null, null],
            ['CARGA', '24001EC01000204D', '24001EC01000204D', null],
            ['CARGA PE: 24001EC01000205E', '24001EC03000206F', null, '24001EC01000205E'],
        ];
        $billIds = [];
        foreach ($cases as $index => [$description, $export, $expected, $legacy]) {
            $data = [
                'bill_number' => 'QA-' . $index,
                'shipper_name' => null, 'consignee_name' => null,
                'cargo_description' => $description, 'export_references' => $export,
                'total_weight_kg' => 1, 'volume_m3' => 1, 'total_containers' => 1,
            ];
            $bill = Model::withoutEvents(fn () => $create->invoke($parser, $data, $shipment, ['user_id' => self::USER]))->fresh();
            $billIds[] = $bill->id;
            $this->assertSame($description, $bill->cargo_description);
            $this->assertSame($expected, $bill->id_decla);
            $this->assertSame($legacy, $bill->permiso_embarque);
        }

        $warnings = (new ReflectionProperty($parser, 'warnings'))->getValue($parser);
        $import = ManifestImport::create(['status' => 'processing', 'file_name' => 'qa.xml']);
        (new ReflectionMethod($parser, 'completeImportRecord'))
            ->invoke($parser, $import, $voyage, [$shipment->id], $billIds, [], microtime(true), $warnings);
        $import->refresh();
        $this->assertSame('completed_with_warnings', $import->status);
        $this->assertSame(['BL QA-5: destinaciones distintas: 24001EC01000205E, 24001EC03000206F.'], $import->warnings);
    }
}
