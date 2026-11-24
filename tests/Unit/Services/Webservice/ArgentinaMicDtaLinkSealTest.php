<?php

namespace Tests\Unit\Services\Webservice;

use App\Models\Container;
use App\Services\Simple\SimpleXmlGenerator;
use Illuminate\Database\Eloquent\Relations\Pivot;
use ReflectionClass;
use ReflectionMethod;
use Tests\TestCase;
use XMLWriter;

/**
 * El MIC/DTA declara los precintos del vínculo del contenedor con el
 * conocimiento de ese viaje; los campos del contenedor quedan como respaldo.
 * Números ficticios.
 */
class ArgentinaMicDtaLinkSealTest extends TestCase
{
    private SimpleXmlGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->generator = (new ReflectionClass(SimpleXmlGenerator::class))->newInstanceWithoutConstructor();
    }

    private function container(?string $linkSeals): Container
    {
        $container = new Container([
            'container_number' => 'TSTU0000001',
            'argentina_container_code' => '42G1',
            'container_condition' => 'H',
            'condition' => 'L',
            'shipper_seal' => 'QA000099',
        ]);
        $pivot = new Pivot();
        $pivot->source_seals = $linkSeals;
        $container->setRelation('pivot', $pivot);

        return $container;
    }

    private function invokeGenerator(string $method, array $args): mixed
    {
        $reflection = new ReflectionMethod($this->generator, $method);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs($this->generator, $args);
    }

    private function sealsWritten(Container $container, array $linkSeals): array
    {
        $w = new XMLWriter();
        $w->openMemory();
        $this->invokeGenerator('writeMicDtaContainer', [$w, $container, 'QA', $linkSeals]);
        preg_match_all('#<precinto>([^<]*)</precinto>#', $w->outputMemory(), $matches);

        return $matches[1];
    }

    public function test_link_seals_are_declared_instead_of_the_container_fields(): void
    {
        $container = $this->container(json_encode(['QA100001', 'QA100002']));
        $linkSeals = $this->invokeGenerator('linkSealsByContainer', [collect([$container])]);

        $this->assertSame(['QA100001', 'QA100002'], $this->sealsWritten($container, $linkSeals['TSTU0000001']));
    }

    public function test_container_fields_are_the_fallback_without_link_seals(): void
    {
        $container = $this->container(null);
        $linkSeals = $this->invokeGenerator('linkSealsByContainer', [collect([$container])]);

        $this->assertSame([], $linkSeals);
        $this->assertSame(['QA000099'], $this->sealsWritten($container, []));
    }

    public function test_links_of_the_same_container_are_merged_without_repeats(): void
    {
        $linkSeals = $this->invokeGenerator('linkSealsByContainer', [collect([
            $this->container(json_encode(['QA100001', 'QA100002'])),
            $this->container(json_encode(['QA100002 QA100003'])),
            $this->container(null),
        ])]);

        $this->assertSame(['TSTU0000001' => ['QA100001', 'QA100002', 'QA100003']], $linkSeals);
    }
}
