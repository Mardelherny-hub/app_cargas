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

class ArgentinaMicDtaPackagingContractTest extends TestCase
{
    private SimpleXmlGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();

        // Sin datos reales, consultas de negocio ni conexiones aduaneras.
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

    private function item(
        string $code = 'T',
        string $name = 'CONTENEDOR',
        ?string $explicit = null,
        ?string $argentinaCode = null,
        ?string $uneceCode = null
    ): ShipmentItem {
        $type = (new PackagingType())->forceFill([
            'id' => 4,
            'code' => $code,
            'name' => $name,
            'argentina_ws_code' => $argentinaCode ?? $code,
            'unece_code' => $uneceCode,
        ]);

        $item = (new ShipmentItem())->forceFill([
            'id' => 31760,
            'line_number' => 1,
            'item_description' => 'S.T.C.: AUTOMOTIVE PARTS',
            'package_quantity' => 13,
            'gross_weight_kg' => 2500,
            'packaging_type_id' => 4,
            'packaging_code' => $explicit,
        ]);
        $item->setRelation('packagingType', $type);

        return $item;
    }

    private function container(): Container
    {
        $container = (new Container())->forceFill([
            'container_number' => 'QA-CONTAINER-1',
            'condition' => 'L',
        ]);
        $container->setRelation('pivot', (new Pivot())->forceFill([
            'package_quantity' => 13,
            'gross_weight_kg' => 2500,
        ]));

        return $container;
    }

    private function packagingCode(ShipmentItem $item, ?Container $container): string
    {
        return (new ReflectionMethod(SimpleXmlGenerator::class, 'micDtaPackagingCode'))
            ->invoke($this->generator, $item, 'RBLEXOL005127-01', $container);
    }

    private function bultoXml(ShipmentItem $item, ?Container $container): string
    {
        $writer = new XMLWriter();
        $writer->openMemory();

        (new ReflectionMethod(SimpleXmlGenerator::class, 'writeMicDtaBulto'))
            ->invoke($this->generator, $writer, $item, $container, 'RBLEXOL005127-01', false);

        return $writer->outputMemory();
    }

    public function test_real_shape_of_loaded_container_uses_cn_without_changing_13_packages(): void
    {
        $item = $this->item();
        $container = $this->container();
        $before = $item->getAttributes();

        $xml = $this->bultoXml($item, $container);

        $this->assertStringContainsString('<cantBultos>13</cantBultos>', $xml);
        $this->assertStringContainsString('<cantBultosTotFrac>13</cantBultosTotFrac>', $xml);
        $this->assertStringContainsString('<codTipEmbalaje>CN</codTipEmbalaje>', $xml);
        $this->assertStringContainsString('<indCargSuelt>N</indCargSuelt>', $xml);
        $this->assertStringContainsString('<idContenedor>QA-CONTAINER-1</idContenedor>', $xml);
        $this->assertSame($before, $item->getAttributes());
    }

    public function test_explicit_two_character_code_takes_priority_over_catalog_and_cn(): void
    {
        // ZT aparece en solicitudes históricas MIC/DTA del sistema.
        $item = $this->item('T', 'CONTENEDOR', 'ZT', 'CN', 'CN');

        $this->assertSame('ZT', $this->packagingCode($item, $this->container()));
        $this->assertStringContainsString(
            '<codTipEmbalaje>ZT</codTipEmbalaje>',
            $this->bultoXml($item, $this->container())
        );
    }

    public function test_two_character_catalog_code_precedes_unece_fallback(): void
    {
        $this->assertSame(
            'ZT',
            $this->packagingCode($this->item('T', 'CONTENEDOR', null, 'ZT', 'CN'), $this->container())
        );
        $this->assertSame(
            'CN',
            $this->packagingCode($this->item('T', 'CONTENEDOR', null, 'T', 'CN'), $this->container())
        );
    }

    public function test_local_container_type_without_linked_container_is_not_assumed_edifact_cn(): void
    {
        $this->expectExceptionMessage(
            'BL RBLEXOL005127-01, línea 1: falta codTipEmbalaje EDIFACT de 2 caracteres'
        );
        $this->packagingCode($this->item(), null);
    }

    public function test_other_local_type_is_not_silently_mapped_to_cn(): void
    {
        $this->expectExceptionMessage('falta codTipEmbalaje EDIFACT de 2 caracteres');
        $this->packagingCode(
            $this->item('N', 'NO RETORNABLE'),
            $this->container()
        );
    }

    public function test_wrong_name_on_code_t_is_not_mapped_to_cn(): void
    {
        $this->expectExceptionMessage('falta codTipEmbalaje EDIFACT de 2 caracteres');
        $this->packagingCode(
            $this->item('T', 'NO RETORNABLE'),
            $this->container()
        );
    }

    public function test_fractionated_title_still_blocks_before_packaging_conversion(): void
    {
        $writer = new XMLWriter();
        $writer->openMemory();

        $this->expectExceptionMessage('es fraccionado');
        (new ReflectionMethod(SimpleXmlGenerator::class, 'writeMicDtaBulto'))
            ->invoke($this->generator, $writer, $this->item(), $this->container(), 'RBLEXOL005127-01', true);
    }

    public function test_both_registrar_methods_reuse_the_same_bulto_writer(): void
    {
        $source = file_get_contents(app_path('Services/Simple/SimpleXmlGenerator.php'));
        $this->assertIsString($source);

        $titStart = strpos($source, 'public function createRegistrarTitEnviosXml(');
        $envStart = strpos($source, 'public function createRegistrarEnviosXml(');
        $this->assertNotFalse($titStart);
        $this->assertNotFalse($envStart);

        // Cuatro llamadas: ítems sueltos y contenedorizados en cada método.
        $this->assertSame(4, substr_count($source, '$this->writeMicDtaBulto('));
        $this->assertStringContainsString(
            '$this->micDtaPackagingCode($item, $billNumber, $container)',
            $source
        );
    }
}
