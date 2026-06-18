<?php

namespace Tests\Unit;

use Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use App\Services\Parsers\Concerns\ExtractsEmbeddedTaxId;

class ExtractsEmbeddedTaxIdTest extends TestCase
{
    private object $helper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->helper = new class {
            use ExtractsEmbeddedTaxId;
            public function extract(?string $t): ?string { return $this->extractEmbeddedTaxId($t); }
            public function resolve(?string $s, ?string $n = null, ?string $a = null): ?string {
                return $this->resolveTaxId($s, $n, $a);
            }
        };
    }

    #[DataProvider('casosExtract')]
    public function test_extraccion_tax_embebido(?string $entrada, ?string $esperado): void
    {
        $this->assertSame($esperado, $this->helper->extract($entrada));
    }

    public static function casosExtract(): array
    {
        return [
            'CUIT NBR con espacio' => ['CUIT NBR 20-23208649-2', '20232086492'],
            'CUIT NBR con punto'   => ['CUIT NBR.30597797644', '30597797644'],
            'RUC / TAX ID'         => ['RUC / TAX ID: 80028211-6', '800282116'],
            'CNPJ con barra'       => ['CNPJ: 00.913.443/0001-73', '00913443000173'],
            'CUIT normal'          => ['CUIT: 30-59742920-3', '30597429203'],
            'RUC paraguayo'        => ['RUC: 80078410-3 Asuncion', '800784103'],
            'NIT colombiano'       => ['NIT 860.025.792-3', '8600257923'],
            'solo ceros -> null'   => ['CUIT: 00000000000', null],
            'NCM no es tax'        => ['NCM: 0202.30', null],
            'HS CODE no es tax'    => ['HS CODE: 4104.11.14', null],
            'nombre sin tax'       => ['WEBER SERVICIOS INTERNACIONALES SA', null],
            'same as consignee'    => ['SAME AS CONSIGNEE', null],
            'vacio'                => ['', null],
            'null'                 => [null, null],
        ];
    }

    #[DataProvider('casosResolve')]
    public function test_resolve_prioridad(?string $struct, ?string $name, ?string $addr, ?string $esperado): void
    {
        $this->assertSame($esperado, $this->helper->resolve($struct, $name, $addr));
    }

    public static function casosResolve(): array
    {
        return [
            // 1. estructurado válido gana, aunque nombre/dirección tengan otro
            'estructurado gana' => ['30-11111111-7', 'EMP CUIT: 30-22222222-9', 'CALLE X CUIT 30-33333333-1', '30111111117'],
            // 2. estructurado vacío -> toma del nombre
            'fallback a nombre' => [null, 'EMP CUIT: 30-22222222-9', 'CALLE X', '30222222229'],
            // 3. nombre sin tax -> toma de dirección
            'fallback a direccion' => [null, 'EMP SA', 'CALLE X RUC: 80078410-3', '800784103'],
            // 4. estructurado solo ceros -> se ignora, busca fallback
            'estructurado ceros -> fallback' => ['00000000000', 'EMP CUIT: 30-22222222-9', null, '30222222229'],
            // 5. sin tax en ningún lado -> null
            'sin tax -> null' => [null, 'EMP SA', 'CALLE SIN DATOS', null],
        ];
    }
}
