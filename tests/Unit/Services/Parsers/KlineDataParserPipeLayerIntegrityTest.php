<?php

namespace Tests\Unit\Services\Parsers;

use App\Services\Parsers\KlineDataParser;
use Illuminate\Support\Str;
use ReflectionMethod;
use Tests\TestCase;

class KlineDataParserPipeLayerIntegrityTest extends TestCase
{
    private function data(): array
    {
        return [
            'CMMDREC0' => [
                '0000001NAUT00000022UNIT'
                . '01786400000KGS000344520M3',
            ],
            'DESCREC0' => [
                '001001NCM: 8426.49.90.100F',
                '003001TRACTOR TIENDETUBOS',
                '004001SUPERIOR SPX 660',
                '005001DESMONTADO EN:',
                '006001- MAQUINARIA PRINCIPAL S./N.: PX',
                '0070010155',
                '008001- CONTRAPESO GRANDE S./N.: PC039',
                '009001- CONTRAPESO PEQUENO S./N.: PC039',
                '010001- PLUMA DE ELEVACION S./N.: PC039',
                '011001- SOPORTE DE BRAZO S./N.: PC039',
                '013001CARGO LOADED ON MAFI NO.',
                '014001KLRH4004127',
                '174001CONSOLIDATED CARGO',
            ],
            'MARKREC0' => [
                '001001SELF PROPELLED UNIT',
                '002001PC039',
                '004001STATIC PIECE',
                '005001STATIC PIECE',
                '006001STATIC PIECE',
                '007001STATIC PIECE',
            ],
        ];
    }

    public function test_real_pipe_layer_measurements_are_preserved(): void
    {
        $m = $this->invokeParser(
            'extractRealMeasurements',
            [$this->data()]
        );

        $this->assertSame(22, $m['package_quantity']);
        $this->assertSame(178640.0, $m['gross_weight_kg']);
        $this->assertNull($m['net_weight_kg']);
        $this->assertSame(344.52, $m['volume_m3']);
    }

    public function test_pipe_layer_is_non_containerized_not_invented_roro(): void
    {
        $this->assertSame(
            'ONC001',
            $this->invokeParser(
                'resolveCargoTypeCode',
                [$this->data(), 'KKLUATM02176']
            )
        );
    }

    public function test_explicit_singular_unit_resolves_to_pieces(): void
    {
        $this->assertSame(
            'PCS',
            $this->invokeParser(
                'resolveUnitOfMeasure',
                [$this->data(), 'KKLUATM02176']
            )
        );
    }

    public function test_extended_ncm_preserves_base_eight_digits(): void
    {
        $this->assertSame(
            ['84264990'],
            $this->invokeParser(
                'extractNCMCodes',
                [$this->data()]
            )
        );
    }

    public function test_description_preserves_pipe_layer_identity_without_inventing_quantity(): void
    {
        $descriptions = $this->invokeParser(
            'extractCargoDescriptions',
            [$this->data()]
        );

        $this->assertCount(1, $descriptions);

        $description = $descriptions[0];

        $this->assertStringContainsString(
            'TRACTOR TIENDETUBOS',
            $description
        );

        $this->assertStringContainsString(
            'SUPERIOR SPX 660',
            $description
        );

        $this->assertStringContainsString(
            'DESMONTADO',
            $description
        );

        $this->assertStringContainsString(
            'MAFI',
            $description
        );

        // 22 es cantidad declarada de unidades/piezas,
        // no cantidad demostrada de tractores completos.
        $this->assertStringNotContainsString(
            '22 TRACTOR',
            Str::upper($description)
        );
    }

    private function invokeParser(
        string $method,
        array $arguments
    ): mixed {
        $parser = new KlineDataParser();

        $reflection = new ReflectionMethod(
            $parser,
            $method
        );
        $reflection->setAccessible(true);

        return $reflection->invokeArgs(
            $parser,
            $arguments
        );
    }
}
