<?php

namespace Tests\Unit\Services\Parsers;

use App\Services\Parsers\NavsurTextParser;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class NavsurTextParserStage1IntegrityTest extends TestCase
{
    private function invoke(
        NavsurTextParser $parser,
        string $method,
        array $args = []
    ): mixed {
        $m = new ReflectionMethod(
            NavsurTextParser::class,
            $method
        );

        $m->setAccessible(true);

        return $m->invokeArgs($parser, $args);
    }

    public function test_explicit_zero_measurements_are_preserved(): void
    {
        $section = <<<'TXT'
ITEM: /*1*/
EMBALAJE: /*BAGS*/
MERCADERIA: /*PRODUCTO NCM: 84295199*/
CANTIDAD: /*0*/
PESONETO: /*0*/
PESOBRUTO: /*0*/
CUBITAJE: /*0*/
IMO: /**/
PARTIDAARANCELARIA: /**/
TXT;

        $parser = new NavsurTextParser();

        $items = $this->invoke(
            $parser,
            'parseItemsSection',
            [$section]
        );

        $this->assertCount(1, $items);
        $this->assertSame(0, $items[0]['cantidad']);
        $this->assertSame(0.0, $items[0]['peso_neto']);
        $this->assertSame(0.0, $items[0]['peso_bruto']);
        $this->assertSame(0.0, $items[0]['cubitaje']);
    }

    public function test_absent_required_measurements_remain_null(): void
    {
        $section = <<<'TXT'
ITEM: /*1*/
EMBALAJE: /*BAGS*/
MERCADERIA: /*PRODUCTO*/
IMO: /**/
PARTIDAARANCELARIA: /**/
TXT;

        $parser = new NavsurTextParser();

        $items = $this->invoke(
            $parser,
            'parseItemsSection',
            [$section]
        );

        $this->assertNull($items[0]['cantidad']);
        $this->assertNull($items[0]['peso_neto']);
        $this->assertNull($items[0]['peso_bruto']);
        $this->assertNull($items[0]['cubitaje']);
    }

    public function test_ncm_is_normalized_and_used_as_tariff_position(): void
    {
        $parser = new NavsurTextParser();

        $item = [
            'partida_arancelaria' => '',
            'mercaderia' =>
                'WHEEL LOADER / NCM: 8429.51.99',
        ];

        $ncm = $this->invoke(
            $parser,
            'resolveItemCommodityCode',
            [$item]
        );

        $this->assertSame('84295199', $ncm);

        /*
         * Contrato Stage 1:
         * NCM y Posición Arancelaria son el mismo dato de negocio.
         * El modelo mantiene ambos campos internos por compatibilidad.
         */
        $parserFile = (new \ReflectionClass(
            NavsurTextParser::class
        ))->getFileName();

        $source = file_get_contents($parserFile);

        $this->assertStringContainsString(
            "'commodity_code' => \$commodityCode",
            $source
        );

        $this->assertStringContainsString(
            "'tariff_position' => \$commodityCode",
            $source
        );
    }

    public function test_validation_accepts_explicit_zero_but_rejects_absent(): void
    {
        $data = [[
            'numero_bl' => 'TEST001',
            'buque' => 'VICKY B',
            'viaje' => '0092B',
            'puerto_carga' => 'PYCAP',
            'puerto_descarga' => 'ARBUE',
            'cargador_nombre' => 'SHIPPER',
            'consignatario_nombre' => 'CONSIGNEE',
            'containers' => [[
                'cod_contenedor' => 'AAAA0000001',
                'tipo_contenedor' => '40HC',
                'items' => [[
                    'mercaderia' => 'PRODUCTO',
                    'cantidad' => 0,
                    'peso_bruto' => 0.0,
                    'peso_neto' => 0.0,
                    'cubitaje' => 0.0,
                ]],
            ]],
        ]];

        $parser = new NavsurTextParser();

        $this->assertSame(
            [],
            $parser->validate($data)
        );

        $data[0]['containers'][0]['items'][0]['cantidad'] = null;
        $data[0]['containers'][0]['items'][0]['peso_bruto'] = null;

        $errors = $parser->validate($data);

        $joined = implode(' ', $errors);

        $this->assertStringContainsString(
            'cantidad no informada',
            $joined
        );

        $this->assertStringContainsString(
            'peso bruto no informado',
            $joined
        );
    }

    public function test_ncm_normalization_to_eight_digits(): void
    {
        $parser = new NavsurTextParser();

        $this->assertSame(
            '84295199',
            $this->invoke(
                $parser,
                'normalizeCommodityCode',
                ['8429.51.99']
            )
        );

        $this->assertSame(
            '12345600',
            $this->invoke(
                $parser,
                'normalizeCommodityCode',
                ['1234.56']
            )
        );

        $this->assertNull(
            $this->invoke(
                $parser,
                'normalizeCommodityCode',
                ['1234']
            )
        );
    }
}
