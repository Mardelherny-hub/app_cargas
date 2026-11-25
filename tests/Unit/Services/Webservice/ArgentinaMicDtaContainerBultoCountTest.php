<?php

namespace Tests\Unit\Services\Webservice;

use App\Models\Company;
use App\Models\Container;
use App\Models\PackagingType;
use App\Models\ShipmentItem;
use App\Services\Simple\SimpleXmlGenerator;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Facades\DB;
use ReflectionMethod;
use Tests\TestCase;
use XMLWriter;

/**
 * MIC/DTA: un ítem con envase CONTENEDOR repartido en varios contenedores sin
 * bultos por vínculo se declara como un bulto CN por contenedor, de modo que
 * el total del BL es la cantidad de contenedores. Datos ficticios.
 */
class ArgentinaMicDtaContainerBultoCountTest extends TestCase
{
    private SimpleXmlGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'logging.default' => 'null',
        ]);
        DB::purge('sqlite');

        $this->generator = new SimpleXmlGenerator(
            (new Company())->forceFill(['ws_environment' => 'testing'])
        );
    }

    private function item(string $code = 'T', string $name = 'CONTENEDOR'): ShipmentItem
    {
        $item = (new ShipmentItem())->forceFill([
            'line_number' => 1,
            'item_description' => 'S.T.C.: 1200 CARTONS FROZEN MEAT',
            'package_quantity' => 1200,
            'gross_weight_kg' => 75000,
            'packaging_code' => 'CT',
        ]);
        $item->setRelation('packagingType', (new PackagingType())->forceFill([
            'code' => $code,
            'name' => $name,
            'argentina_ws_code' => $code,
        ]));

        return $item;
    }

    private function container(string $number, $quantity): Container
    {
        $container = (new Container())->forceFill(['container_number' => $number]);
        $container->setRelation('pivot', (new Pivot())->forceFill([
            'package_quantity' => $quantity,
            'gross_weight_kg' => 25000,
        ]));

        return $container;
    }

    /** @return \SimpleXMLElement[] Bultos escritos, uno por contenedor. */
    private function bultos(ShipmentItem $item, array $containers): array
    {
        $writer = new XMLWriter();
        $writer->openMemory();
        $writer->startElement('Bultos');
        $method = new ReflectionMethod(SimpleXmlGenerator::class, 'writeMicDtaBulto');
        foreach ($containers as $container) {
            $method->invoke($this->generator, $writer, $item, $container, 'QA-BL-01', false);
        }
        $writer->endElement();

        return iterator_to_array(simplexml_load_string($writer->outputMemory())->Bulto, false);
    }

    public function test_three_containers_without_packages_declare_three_cn_bultos(): void
    {
        $bultos = $this->bultos($this->item(), [
            $this->container('QAAU0000001', 0),
            $this->container('QAAU0000002', 0),
            $this->container('QAAU0000003', 0),
        ]);

        $this->assertCount(3, $bultos);
        $this->assertSame(3, array_sum(array_map(fn ($b) => (int) $b->cantBultosTotFrac, $bultos)));
        foreach ($bultos as $bulto) {
            $this->assertSame('1', (string) $bulto->cantBultos);
            $this->assertSame('1', (string) $bulto->cantBultosTotFrac);
            $this->assertSame('CN', (string) $bulto->codTipEmbalaje);
            $this->assertSame('25000.0000', (string) $bulto->pesoBruto);
        }
    }

    public function test_empty_link_quantity_is_declared_as_one_container(): void
    {
        [$bulto] = $this->bultos($this->item(), [$this->container('QAAU0000001', null)]);

        $this->assertSame('1', (string) $bulto->cantBultos);
        $this->assertSame('CN', (string) $bulto->codTipEmbalaje);
    }

    public function test_real_link_quantity_is_unchanged(): void
    {
        [$bulto] = $this->bultos($this->item(), [$this->container('QAAU0000001', 1200)]);

        $this->assertSame('1200', (string) $bulto->cantBultos);
        $this->assertSame('1200', (string) $bulto->cantBultosTotFrac);
        $this->assertSame('CT', (string) $bulto->codTipEmbalaje);
    }

    public function test_item_that_is_not_container_packaging_is_unchanged(): void
    {
        [$bulto] = $this->bultos($this->item('N', 'NO RETORNABLE'), [$this->container('QAAU0000001', 0)]);

        $this->assertSame('0', (string) $bulto->cantBultos);
        $this->assertSame('0', (string) $bulto->cantBultosTotFrac);
        $this->assertSame('CT', (string) $bulto->codTipEmbalaje);
    }
}
