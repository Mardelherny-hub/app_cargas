<?php

namespace Tests\Unit\Services\Parsers;

use App\Models\BillOfLading;
use App\Models\Container;
use App\Models\ShipmentItem;
use App\Services\Parsers\CmspEdiParser;
use App\Services\Parsers\NavsurTextParser;
use App\Services\Parsers\ParanaExcelParser;
use App\Services\Parsers\TfpTextParser;
use App\Services\Simple\SimpleXmlGenerator;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use Tests\TestCase;
use Tests\Unit\Services\Parsers\Concerns\BuildsManifestImportSchema;
use XMLWriter;

/**
 * Condición casa/muelle de los contenedores importados: si el archivo no la
 * informa, va House. La que declara el MIC/DTA sale del vínculo con el
 * conocimiento del viaje. Números ficticios.
 */
class ImportedContainerConditionTest extends TestCase
{
    use BuildsManifestImportSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useIsolatedDatabase();
        Schema::table('container_types', fn (Blueprint $t) => $t->text('iso_size_type')->nullable());
        Schema::table('container_shipment_item', function (Blueprint $t) {
            foreach (['quantity_percentage', 'weight_percentage', 'volume_percentage', 'loaded_at', 'sealed_at', 'loading_sequence'] as $column) {
                $t->text($column)->nullable();
            }
        });
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

    public function test_existing_pier_container_is_declared_house_for_this_voyage(): void
    {
        // Contenedor de un viaje anterior, en P.
        Container::create(['container_number' => 'ABCU1234567', 'container_condition' => 'P',
            'argentina_container_code' => '22G1', 'condition' => 'L', 'shipper_seal' => 'QA500001']);
        $item = ShipmentItem::create(['bill_of_lading_id' => 1, 'line_number' => 1]);
        $item->setRelation('containers', collect());
        $parser = $this->cuscarParser("EQD+CN+ABCU1234567+22G1:6346:5++2+5'\nSEL+QA500001+CA'\n");

        (new ReflectionMethod($parser, 'createContainer'))
            ->invoke($parser, 'ABCU1234567', ['package_info' => '10:PK', 'gross_weight_kg' => 1000], $item);

        // El contenedor maestro no cambia; el vínculo de este viaje va House.
        $this->assertSame('P', Container::where('container_number', 'ABCU1234567')->value('container_condition'));
        $linked = $item->fresh()->containers;
        $this->assertSame('H', $linked->first()->pivot->container_condition);

        $generator = (new \ReflectionClass(SimpleXmlGenerator::class))->newInstanceWithoutConstructor();
        $conditions = (new ReflectionMethod($generator, 'linkConditionByContainer'))->invoke($generator, $linked, 'QA');
        $w = new XMLWriter();
        $w->openMemory();
        (new ReflectionMethod($generator, 'writeMicDtaContainer'))
            ->invoke($generator, $w, $linked->first(), 'QA', [], $conditions['ABCU1234567'] ?? null);

        $this->assertStringContainsString('<condicion>H</condicion>', $w->outputMemory());
    }

    public function test_container_condition_is_the_fallback_without_link_condition(): void
    {
        $generator = (new \ReflectionClass(SimpleXmlGenerator::class))->newInstanceWithoutConstructor();
        $container = new Container(['container_number' => 'ABCU1234567', 'container_condition' => 'P',
            'argentina_container_code' => '22G1', 'condition' => 'L', 'shipper_seal' => 'QA500001']);
        $container->setRelation('pivot', new Pivot());
        $w = new XMLWriter();
        $w->openMemory();

        $conditions = (new ReflectionMethod($generator, 'linkConditionByContainer'))->invoke($generator, collect([$container]), 'QA');
        (new ReflectionMethod($generator, 'writeMicDtaContainer'))->invoke($generator, $w, $container, 'QA', [], null);

        $this->assertSame([], $conditions);
        $this->assertStringContainsString('<condicion>P</condicion>', $w->outputMemory());
    }

    public function test_links_with_different_conditions_are_reported(): void
    {
        $generator = (new \ReflectionClass(SimpleXmlGenerator::class))->newInstanceWithoutConstructor();
        $links = collect(['H', 'P'])->map(function (string $condition) {
            $container = new Container(['container_number' => 'ABCU1234567']);
            $pivot = new Pivot();
            $pivot->container_condition = $condition;

            return $container->setRelation('pivot', $pivot);
        });

        $this->expectExceptionMessage('QA: el contenedor ABCU1234567 tiene condición H/P distinta entre sus conocimientos (H, P).');
        (new ReflectionMethod($generator, 'linkConditionByContainer'))->invoke($generator, $links, 'QA');
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
