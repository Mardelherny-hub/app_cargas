<?php

namespace Tests\Unit\Services\Parsers;

use App\Models\BillOfLading;
use App\Models\Container;
use App\Models\ShipmentItem;
use App\Services\Parsers\CmspEdiParser;
use App\Services\Parsers\NavsurTextParser;
use App\Services\Parsers\ParanaExcelParser;
use App\Services\Parsers\TfpTextParser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use Tests\TestCase;
use Tests\Unit\Services\Parsers\Concerns\BuildsManifestImportSchema;

/**
 * Condición casa/muelle de los contenedores importados: si el archivo no la
 * informa, va House. Números ficticios.
 */
class ImportedContainerConditionTest extends TestCase
{
    use BuildsManifestImportSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useIsolatedDatabase();
        Schema::table('container_types', fn (Blueprint $t) => $t->text('iso_size_type')->nullable());
        $this->addContainerTypes(['22G1', '20DV']);
    }

    private function cuscarParser(string $segments): CmspEdiParser
    {
        $tmp = tempnam(sys_get_temp_dir(), 'cuscar-hp-');
        file_put_contents($tmp, "UNH+1+CUSCAR:D:96B:UN'\n" . $segments);
        try {
            $parser = new CmspEdiParser();
            (new ReflectionMethod($parser, 'parseEdiFile'))->invoke($parser, $tmp);
            (new ReflectionMethod($parser, 'extractStructuredData'))->invoke($parser);

            return $parser;
        } finally {
            @unlink($tmp);
        }
    }

    public function test_cuscar_container_without_condition_is_house(): void
    {
        $item = ShipmentItem::create(['bill_of_lading_id' => 1, 'line_number' => 1]);
        $item->setRelation('containers', collect());
        $parser = $this->cuscarParser("EQD+CN+ABCU1234567+22G1:6346:5++2+5'\nSEL+QA500001+CA'\n");

        (new ReflectionMethod($parser, 'createContainer'))
            ->invoke($parser, 'ABCU1234567', ['package_info' => '10:PK', 'gross_weight_kg' => 1000], $item);

        $this->assertSame('H', Container::where('container_number', 'ABCU1234567')->value('container_condition'));
    }

    public function test_existing_container_keeps_its_condition(): void
    {
        Container::create(['container_number' => 'ABCU1234567', 'container_condition' => 'P']);
        $item = ShipmentItem::create(['bill_of_lading_id' => 1, 'line_number' => 1]);
        $item->setRelation('containers', collect());
        $parser = $this->cuscarParser("EQD+CN+ABCU1234567+22G1:6346:5++2+5'\n");

        (new ReflectionMethod($parser, 'createContainer'))
            ->invoke($parser, 'ABCU1234567', ['package_info' => '10:PK', 'gross_weight_kg' => 1000], $item);

        $this->assertSame('P', Container::where('container_number', 'ABCU1234567')->value('container_condition'));
    }

    public function test_tfp_physical_state_without_house_or_pier_is_house(): void
    {
        $map = new ReflectionMethod(TfpTextParser::class, 'mapTfpCondition');
        $parser = (new \ReflectionClass(TfpTextParser::class))->newInstanceWithoutConstructor();

        foreach (['', 'L', 'V', 'D', 'S', 'R'] as $value) {
            $this->assertSame('H', $map->invoke($parser, $value)['container_condition'], "CONDICION '{$value}'");
        }
        $this->assertSame('P', $map->invoke($parser, 'P')['container_condition']);
        $this->assertSame('H', $map->invoke($parser, 'H')['container_condition']);
    }

    public function test_navsur_and_parana_create_house_containers(): void
    {
        foreach ([NavsurTextParser::class, ParanaExcelParser::class] as $class) {
            $source = file_get_contents((new \ReflectionClass($class))->getFileName());
            $start = strpos($source, 'function createContainer(');
            $end = strpos($source, 'protected function', $start + 30);
            $body = substr($source, $start, $end - $start);

            $this->assertStringContainsString("'container_condition' => Container::CONDITION_HOUSE_TO_HOUSE", $body, $class);
        }
    }
}
