<?php

namespace Tests\Unit\Services\Parsers;

use App\Services\Parsers\TfpTextParser;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

class TfpTextParserStage1IntegrityTest extends TestCase
{
    private function invoke(
        TfpTextParser $parser,
        string $method,
        array $args = []
    ): mixed {
        $reflection = new ReflectionMethod(
            TfpTextParser::class,
            $method
        );

        $reflection->setAccessible(true);

        return $reflection->invokeArgs($parser, $args);
    }

    public function test_multiple_containers_survive_when_weight_and_quantity_are_absent(): void
    {
        $section = <<<'TXT'
**CONTENEDORES**
CONDICION: /*H*/
TIPO: /*20DV*/
MEDIDA: /*20*/
TARA: /*0*/
NROPRECINTA: /*3518757*/
NUMERO: /*MSBU3423400*/
OBS: /**/
CONDICION: /*H*/
TIPO: /*20DV*/
MEDIDA: /*20*/
TARA: /*0*/
NROPRECINTA: /*3518762*/
NUMERO: /*MSBU3760226*/
OBS: /**/
**FIN CONTENEDORES**
TXT;

        $parser = new TfpTextParser();

        $containers = $this->invoke(
            $parser,
            'parseContainers',
            [$section]
        );

        $this->assertCount(2, $containers);
        $this->assertSame('MSBU3423400', $containers[0]['numero']);
        $this->assertSame('MSBU3760226', $containers[1]['numero']);
        $this->assertSame('0', $containers[0]['tara']);
        $this->assertArrayNotHasKey('peso', $containers[0]);
        $this->assertArrayNotHasKey('cantidad', $containers[0]);
    }

    public function test_explicit_zero_container_measurements_are_preserved(): void
    {
        $section = <<<'TXT'
**CONTENEDORES**
CONDICION: /*V*/
TIPO: /*40HC*/
MEDIDA: /*40HC*/
NUMERO: /*TEST0000001*/
PESO: /*0*/
CANTIDAD: /*0*/
OBS: /*26001TRB3013791N*/
**FIN CONTENEDORES**
TXT;

        $parser = new TfpTextParser();

        $containers = $this->invoke(
            $parser,
            'parseContainers',
            [$section]
        );

        $this->assertCount(1, $containers);
        $this->assertSame('0', $containers[0]['peso']);
        $this->assertSame('0', $containers[0]['cantidad']);
        $this->assertSame('26001TRB3013791N', $containers[0]['obs']);
    }

    public function test_multiple_merchandise_rows_inside_one_line_block_are_not_lost(): void
    {
        $section = <<<'TXT'
**LINEAS**
CANTPARCIALBULTOS: /*960*/
CANTTOTALBULTOS: /*960*/
CODARMONIZADO: /*1701.99.10*/
NATURALEZAMERCADERIA: /*AZUCAR ORGANICA NCM: 17019910*/
OBS: /**/
PESOTOTALBULTOS: /*24380*/
VOLUMENTOTAL: /*30*/
TIPOEMBALAJE: /*BAGS*/
CONTENEDOR: /*MSBU3423400*/
CANTPARCIALBULTOS: /*960*/
CANTTOTALBULTOS: /*960*/
CODARMONIZADO: /*1701.99.10*/
NATURALEZAMERCADERIA: /*AZUCAR ORGANICA NCM: 17019910*/
OBS: /**/
PESOTOTALBULTOS: /*24300*/
VOLUMENTOTAL: /*30*/
TIPOEMBALAJE: /*BAGS*/
CONTENEDOR: /*MSBU3760226*/
**FIN LINEAS**
TXT;

        $parser = new TfpTextParser();

        $items = $this->invoke(
            $parser,
            'parseLines',
            [$section]
        );

        $this->assertCount(2, $items);
        $this->assertSame('MSBU3423400', $items[0]['contenedor']);
        $this->assertSame('24380', $items[0]['peso_total_bultos']);
        $this->assertSame('MSBU3760226', $items[1]['contenedor']);
        $this->assertSame('24300', $items[1]['peso_total_bultos']);
    }

    public function test_ncm_fallback_from_description_uses_project_normalization(): void
    {
        $parser = new TfpTextParser();

        $this->assertSame(
            '3808.92',
            $this->invoke(
                $parser,
                'extractNcmFromText',
                ['PRODUCTO NCM NO.: 3808.92.99']
            )
        );

        $this->assertSame(
            '5802',
            $this->invoke(
                $parser,
                'extractNcmFromText',
                ['FABRIC COTTON NCM: 5802']
            )
        );

        $this->assertNull(
            $this->invoke(
                $parser,
                'extractNcmFromText',
                ['MERCADERIA SIN NCM INFORMADA']
            )
        );
    }

    public function test_tfp_condition_catalogs_are_not_confused(): void
    {
        $parser = new TfpTextParser();

        $this->assertSame(
            ['condition' => 'L', 'container_condition' => 'H'],
            $this->invoke($parser, 'mapTfpCondition', ['H'])
        );

        $this->assertSame(
            ['condition' => 'L', 'container_condition' => 'P'],
            $this->invoke($parser, 'mapTfpCondition', ['P'])
        );

        $this->assertSame(
            ['condition' => 'V', 'container_condition' => 'P'],
            $this->invoke($parser, 'mapTfpCondition', ['V'])
        );
    }

    public function test_tax_id_is_resolved_from_real_tfp_name_or_address_fields(): void
    {
        $parser = new TfpTextParser();

        $this->assertSame(
            '801107881',
            $this->invoke(
                $parser,
                'resolveTaxId',
                [null, "RHENUS LOGISTICS PARAGUAY S.A.\nRUC: 80110788-1", null]
            )
        );

        $this->assertSame(
            '30211234567',
            $this->invoke(
                $parser,
                'resolveTaxId',
                [null, 'EMPRESA ARGENTINA', 'CHILE 801 - CUIT 30-21123456-7']
            )
        );
    }

    public function test_ncm_fallback_must_feed_both_customs_fields(): void
    {
        $file = (new ReflectionClass(TfpTextParser::class))->getFileName();
        $source = file_get_contents($file);

        $this->assertStringContainsString(
            "'commodity_code' => !empty(\$data['cod_armonizado'])",
            $source
        );

        /*
         * Contrato Stage 1:
         * si CODARMONIZADO viene vacío y el NCM se recupera desde la descripción,
         * el mismo valor normalizado debe persistirse también en tariff_position.
         * Hoy este test protege específicamente el caso BM ROSA informado por el
         * cliente, donde CODARMONIZADO viene vacío y el NCM está en el texto.
         */
        $this->assertStringContainsString(
            "'tariff_position' => !empty(\$data['cod_armonizado'])",
            $source
        );

        $this->assertStringContainsString(
            "extractNcmFromText(\$data['naturaleza_mercaderia'] ?? null)",
            $source
        );
    }
}
