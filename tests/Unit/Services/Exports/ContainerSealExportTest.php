<?php

namespace Tests\Unit\Services\Exports;

use App\Http\Controllers\Company\Manifests\ManifestExportController;
use App\Models\Container;
use App\Services\Exports\LoginXmlManifestExporter;
use Illuminate\Database\Eloquent\Relations\Pivot;
use ReflectionClass;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Un campo de precinto puede traer varios números separados por espacio,
 * igual que los lee el MIC/DTA. Las exportaciones los separan y no los
 * repiten. Números ficticios.
 */
class ContainerSealExportTest extends TestCase
{
    private function container(): Container
    {
        $container = new Container([
            'container_number' => 'TSTU0000001',
            'shipper_seal' => 'QA100001 QA100002',
            'customs_seal' => 'QA100003',
            'additional_seals' => [],
        ]);

        $pivot = new Pivot();
        $pivot->source_seals = json_encode(['QA100001', 'QA100002']);
        $container->setRelation('pivot', $pivot);

        return $container;
    }

    private function seals(object $target, string $method, Container $container): array
    {
        $reflection = new ReflectionMethod($target, $method);
        $reflection->setAccessible(true);

        return $reflection->invoke($target, $container);
    }

    public function test_login_export_lists_each_seal_once(): void
    {
        $this->assertSame(
            ['QA100001', 'QA100002', 'QA100003'],
            $this->seals(new LoginXmlManifestExporter(), 'containerSeals', $this->container())
        );
    }

    public function test_manifest_export_lists_each_seal_once(): void
    {
        $controller = (new ReflectionClass(ManifestExportController::class))->newInstanceWithoutConstructor();

        $this->assertSame(
            ['QA100001', 'QA100002', 'QA100003'],
            $this->seals($controller, 'containerSeals', $this->container())
        );
    }

    public function test_edi_export_lists_each_seal_once_with_its_issuer(): void
    {
        $controller = (new ReflectionClass(ManifestExportController::class))->newInstanceWithoutConstructor();

        $this->assertSame(
            [
                ['number' => 'QA100001', 'issuer' => 'SH'],
                ['number' => 'QA100002', 'issuer' => 'SH'],
                ['number' => 'QA100003', 'issuer' => 'CU'],
            ],
            $this->seals($controller, 'ediContainerSeals', $this->container())
        );
    }
}
