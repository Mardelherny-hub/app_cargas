<?php

namespace Tests\Unit\Services\Parsers;

use App\Models\Company;
use App\Models\Container;
use App\Services\Parsers\CmspEdiParser;
use App\Services\Simple\SimpleXmlGenerator;
use Database\Seeders\ContainerTypesSeeder;
use Illuminate\Support\Facades\DB;
use ReflectionMethod;
use Tests\TestCase;
use XMLWriter;

class CmspEdiParser45R5Test extends TestCase
{
    public function test_carei_eqd_size_reaches_the_mic_dta_xml(): void
    {
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        (require database_path('migrations/2025_07_16_140614_create_container_types_table.php'))->up();
        $this->seed(ContainerTypesSeeder::class);
        $before = DB::table('container_types')->orderBy('id')->get()->toArray();

        $migration = require database_path('migrations/2026_11_27_130000_add_45r5_container_type.php');
        $migration->up();
        $migration->up();
        $this->assertEquals($before, DB::table('container_types')->where('code', '!=', '45R5')->orderBy('id')->get()->toArray());
        $this->assertSame(1, DB::table('container_types')->where('code', '45R5')->count());

        $parser = new CmspEdiParser();
        $resolve = new ReflectionMethod($parser, 'resolveActiveContainerTypeByIso');
        $generator = new SimpleXmlGenerator(new Company(['ws_environment' => 'testing']));
        $write = new ReflectionMethod($generator, 'writeMicDtaContainer');

        foreach ([
            'EQD+CN+HLBU9748451+45R5++3+8' => 'HLK4035646',
            'EQD+CN+HLBU9819750+45R5++3+8' => 'HLK4035641',
        ] as $eqd => $seal) {
            [, , $number, $iso] = explode('+', $eqd);
            $type = $resolve->invoke($parser, $iso);
            $this->assertSame('45R5', $type->code);
            $container = new Container(['container_number' => $number, 'container_condition' => 'H']);
            $container->setRelation('containerType', $type);

            $xml = new XMLWriter();
            $xml->openMemory();
            $write->invoke($generator, $xml, $container, 'HLCUSHA2604AYDA1', [$seal]);
            $this->assertStringContainsString('<codMedida>45R5</codMedida>', $xml->outputMemory());
        }
    }
}
