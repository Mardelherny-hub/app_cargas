<?php

namespace Tests\Unit\Services\Parsers;

use App\Services\Parsers\LoginXmlParser;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;

class LoginXmlParserStage1IntegrityTest extends TestCase
{
    private function invoke(
        LoginXmlParser $parser,
        string $method,
        array $args = []
    ): mixed {
        $reflection = new ReflectionMethod(
            LoginXmlParser::class,
            $method
        );

        $reflection->setAccessible(true);

        return $reflection->invokeArgs(
            $parser,
            $args
        );
    }

    private function warnings(
        LoginXmlParser $parser
    ): array {
        $property = new ReflectionProperty(
            LoginXmlParser::class,
            'warnings'
        );

        $property->setAccessible(true);

        return $property->getValue($parser);
    }

    private function validationFixture(): array
    {
        return [
            'header' => [
                'loading_port' => 'BUENOS AIRES',
                'discharge_port' => 'NAVEGANTES',
                'voyage_number' => '004N',
                'vessel_name' => 'LOG-IN EXPERIENCE',
            ],

            'bills_of_lading' => [[
                'bill_number' => 'TEST-LOGIN-001',
                'shipper_name' => 'SHIPPER TEST',
                'consignee_name' => 'CONSIGNEE TEST',
                'cargo_description' => 'CARGA TEST',

                // Cabecera válida para aislar la prueba del contenedor.
                'gross_weight' => '100',
                'measurement' => null,

                'containers' => [[
                    'container_number' => 'CAIU7576710',

                    /*
                     * Vacío deliberadamente:
                     * genera únicamente el error de tipo requerido y evita
                     * consultar el catálogo durante este Unit Test.
                     */
                    'container_type' => '',

                    'gross_weight_kg' => 0.0,
                    'tare_weight_kg' => 0.0,
                    'net_weight_kg' => 0.0,
                    'vgm' => 0.0,
                ]],
            ]],
        ];
    }

    public function test_optional_weight_preserves_explicit_zero_and_absence(): void
    {
        $parser = new LoginXmlParser();

        $this->assertNull(
            $this->invoke(
                $parser,
                'parseOptionalWeight',
                [null]
            )
        );

        $this->assertNull(
            $this->invoke(
                $parser,
                'parseOptionalWeight',
                ['']
            )
        );

        $this->assertNull(
            $this->invoke(
                $parser,
                'parseOptionalWeight',
                ['   ']
            )
        );

        $this->assertSame(
            0.0,
            $this->invoke(
                $parser,
                'parseOptionalWeight',
                ['0']
            )
        );

        $this->assertSame(
            0.0,
            $this->invoke(
                $parser,
                'parseOptionalWeight',
                ['0,00']
            )
        );

        $this->assertSame(
            1234.56,
            $this->invoke(
                $parser,
                'parseOptionalWeight',
                ['1234,56']
            )
        );
    }

    public function test_validation_warns_for_zero_but_rejects_absent_required_weights(): void
    {
        $parser = new LoginXmlParser();

        $data = $this->validationFixture();

        $errors = $parser->validate($data);
        $warnings = $this->warnings($parser);

        $errorText = implode(
            ' | ',
            $errors
        );

        $warningText = implode(
            ' | ',
            $warnings
        );

        /*
         * El tipo vacío produce un error independiente.
         * Lo que importa aquí es que cero NO sea interpretado como ausencia.
         */
        $this->assertStringNotContainsString(
            'Peso bruto ausente',
            $errorText
        );

        $this->assertStringNotContainsString(
            'Peso tara ausente',
            $errorText
        );

        $this->assertCount(
            4,
            $warnings
        );

        $this->assertStringContainsString(
            'peso bruto informado en 0',
            $warningText
        );

        $this->assertStringContainsString(
            'peso tara informado en 0',
            $warningText
        );

        $this->assertStringContainsString(
            'peso neto informado en 0',
            $warningText
        );

        $this->assertStringContainsString(
            'VGM informado en 0',
            $warningText
        );

        /*
         * Ahora ausencia real en los dos pesos obligatorios.
         */
        $data['bills_of_lading'][0]['containers'][0]
            ['gross_weight_kg'] = null;

        $data['bills_of_lading'][0]['containers'][0]
            ['tare_weight_kg'] = null;

        $data['bills_of_lading'][0]['containers'][0]
            ['net_weight_kg'] = null;

        $data['bills_of_lading'][0]['containers'][0]
            ['vgm'] = null;

        $errors = $parser->validate($data);
        $warnings = $this->warnings($parser);

        $errorText = implode(
            ' | ',
            $errors
        );

        $this->assertStringContainsString(
            'Peso bruto ausente',
            $errorText
        );

        $this->assertStringContainsString(
            'Peso tara ausente',
            $errorText
        );

        $this->assertSame(
            [],
            $warnings
        );
    }

    public function test_parse_transaction_finishes_tracking_before_commit_and_catches_throwables(): void
    {
        $file = (
            new ReflectionClass(
                LoginXmlParser::class
            )
        )->getFileName();

        $source = file_get_contents($file);

        $start = strpos(
            $source,
            'protected function parseWithContext'
        );

        $end = strpos(
            $source,
            'protected function extractDataFromXml',
            $start
        );

        $this->assertNotFalse($start);
        $this->assertNotFalse($end);

        $method = substr(
            $source,
            $start,
            $end - $start
        );

        $begin = strpos(
            $method,
            'DB::beginTransaction();'
        );

        $createImport = strpos(
            $method,
            '$this->createImportRecord('
        );

        $completeImport = strpos(
            $method,
            '$this->completeImportRecord('
        );

        $commit = strpos(
            $method,
            'DB::commit();'
        );

        $catchThrowable = strpos(
            $method,
            'catch (\Throwable $e)'
        );

        $rollback = strpos(
            $method,
            'DB::rollBack();'
        );

        $this->assertNotFalse($begin);
        $this->assertNotFalse($createImport);
        $this->assertNotFalse($completeImport);
        $this->assertNotFalse($commit);
        $this->assertNotFalse($catchThrowable);
        $this->assertNotFalse($rollback);

        $this->assertLessThan(
            $createImport,
            $begin
        );

        $this->assertLessThan(
            $completeImport,
            $createImport
        );

        $this->assertLessThan(
            $commit,
            $completeImport
        );

        $this->assertLessThan(
            $catchThrowable,
            $commit
        );

        $this->assertGreaterThan(
            $catchThrowable,
            $rollback
        );
    }
}
