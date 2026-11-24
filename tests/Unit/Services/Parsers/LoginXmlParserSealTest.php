<?php

namespace Tests\Unit\Services\Parsers;

use App\Models\Container;
use App\Services\Parsers\LoginXmlParser;
use ReflectionMethod;
use ReflectionProperty;
use Tests\TestCase;
use Tests\Unit\Services\Parsers\Concerns\BuildsManifestImportSchema;

/**
 * Precintos de Login: el archivo no indica el rol. Números ficticios.
 */
class LoginXmlParserSealTest extends TestCase
{
    use BuildsManifestImportSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useIsolatedDatabase();
    }

    private function store(LoginXmlParser $parser, Container $container, array $seals, bool $createdHere): void
    {
        $method = new ReflectionMethod($parser, 'storeFileSeals');
        $method->setAccessible(true);
        $method->invoke($parser, $container, $seals, $createdHere);
    }

    private function warnings(LoginXmlParser $parser): array
    {
        return (new ReflectionProperty($parser, 'warnings'))->getValue($parser);
    }

    public function test_file_seals_of_a_new_container_go_to_the_shipper_seal(): void
    {
        $parser = new LoginXmlParser();
        $container = Container::create(['container_number' => 'TSTU0000001']);

        $this->store($parser, $container, ['QA700001', 'QA700002', 'QA700001'], true);
        $this->store($parser, $container, ['QA700002', 'QA700003'], true);

        $this->assertSame('QA700001 QA700002 QA700003', $container->fresh()->shipper_seal);
        $this->assertNull($container->fresh()->additional_seals);
        $this->assertSame([], $this->warnings($parser));
    }

    public function test_existing_container_is_not_changed_and_is_reported(): void
    {
        $parser = new LoginXmlParser();
        $container = Container::create(['container_number' => 'TSTU0000002', 'carrier_seal' => 'QA800001']);

        $this->store($parser, $container, ['QA800001', 'QA800002'], false);

        $this->assertNull($container->fresh()->shipper_seal);
        $this->assertSame(
            ['Contenedor TSTU0000002: ya existía y no tiene el precinto QA800002 del archivo; no se modificó.'],
            $this->warnings($parser)
        );
    }

    public function test_long_seal_is_kept_whole_with_a_warning(): void
    {
        $parser = new LoginXmlParser();
        $container = Container::create(['container_number' => 'TSTU0000003']);

        $this->store($parser, $container, ['QA9000000000000001'], true);

        $this->assertSame('QA9000000000000001', $container->fresh()->shipper_seal);
        $this->assertSame(
            ['Contenedor TSTU0000003: el precinto QA9000000000000001 supera 15 caracteres.'],
            $this->warnings($parser)
        );
    }

    public function test_new_container_no_longer_keeps_file_seals_only_as_additional(): void
    {
        $source = file_get_contents(app_path('Services/Parsers/LoginXmlParser.php'));

        $this->assertStringNotContainsString("'additional_seals' =>", $source);
        $this->assertStringContainsString('$this->storeFileSeals(', $source);
    }
}
