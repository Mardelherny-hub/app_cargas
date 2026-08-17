<?php

namespace Tests\Unit\Services\Parsers;

use App\Services\Parsers\NavsurTextParser;
use ReflectionMethod;
use Tests\TestCase;

class NavsurTextParserClientIdentityTest extends TestCase
{
    protected function invoke(
        NavsurTextParser $parser,
        string $method,
        array $arguments = []
    ): mixed {
        $reflection = new ReflectionMethod(
            NavsurTextParser::class,
            $method
        );

        $reflection->setAccessible(true);

        return $reflection->invokeArgs(
            $parser,
            $arguments
        );
    }

    public function test_real_navsur_parties_do_not_get_fake_tax_identity(): void
    {
        $parser = new NavsurTextParser();

        $shipper = $this->invoke(
            $parser,
            'resolveClientTaxIdentity',
            [
                'MSC MEDITERRANEAN SHIPPING COMPANY PARAGUAY S.A',
                null,
            ]
        );

        $consignee = $this->invoke(
            $parser,
            'resolveClientTaxIdentity',
            [
                'MSC BUENOS AIRES',
                null,
            ]
        );

        $this->assertSame(
            ['tax_id' => null, 'tax_type' => null],
            $shipper
        );

        $this->assertSame(
            ['tax_id' => null, 'tax_type' => null],
            $consignee
        );
    }

    public function test_port_code_prefix_provides_country_context(): void
    {
        $parser = new NavsurTextParser();

        $this->assertSame(
            'PY',
            $this->invoke(
                $parser,
                'countryAlpha2FromPortCode',
                ['PYCAP']
            )
        );

        $this->assertSame(
            'AR',
            $this->invoke(
                $parser,
                'countryAlpha2FromPortCode',
                ['ARBUE']
            )
        );

        $this->assertNull(
            $this->invoke(
                $parser,
                'countryAlpha2FromPortCode',
                ['UNKNOWN']
            )
        );
    }

    public function test_explicit_fiscal_types_are_preserved(): void
    {
        $parser = new NavsurTextParser();

        $cuit = $this->invoke(
            $parser,
            'resolveClientTaxIdentity',
            [
                'EMPRESA CUIT 30-69318494-7',
                null,
            ]
        );

        $ruc = $this->invoke(
            $parser,
            'resolveClientTaxIdentity',
            [
                'EMPRESA',
                'RUC: 80078410-3 ASUNCION',
            ]
        );

        $cnpj = $this->invoke(
            $parser,
            'resolveClientTaxIdentity',
            [
                'EMPRESA CNPJ 20.403.699/0001-48',
                null,
            ]
        );

        $this->assertSame(
            ['tax_id' => '30693184947', 'tax_type' => 'CUIT'],
            $cuit
        );

        $this->assertSame(
            ['tax_id' => '800784103', 'tax_type' => 'RUC'],
            $ruc
        );

        $this->assertSame(
            ['tax_id' => '20403699000148', 'tax_type' => 'CNPJ'],
            $cnpj
        );
    }

    public function test_generic_tax_id_does_not_invent_document_type(): void
    {
        $parser = new NavsurTextParser();

        $identity = $this->invoke(
            $parser,
            'resolveClientTaxIdentity',
            [
                'EMPRESA TAX ID: 92102433000923',
                null,
            ]
        );

        $this->assertSame('92102433000923', $identity['tax_id']);
        $this->assertNull($identity['tax_type']);
    }

    public function test_unrelated_numbers_are_not_tax_identity(): void
    {
        $parser = new NavsurTextParser();

        $identity = $this->invoke(
            $parser,
            'resolveClientTaxIdentity',
            [
                'MSC BUENOS AIRES',
                'AVENIDA 1234 PISO 5',
            ]
        );

        $this->assertSame(
            ['tax_id' => null, 'tax_type' => null],
            $identity
        );
    }

    public function test_explicit_tax_type_defines_its_jurisdiction(): void
    {
        $parser = new NavsurTextParser();

        $this->assertSame(
            'AR',
            $this->invoke(
                $parser,
                'countryAlpha2ForTaxType',
                ['CUIT']
            )
        );

        $this->assertSame(
            'PY',
            $this->invoke(
                $parser,
                'countryAlpha2ForTaxType',
                ['RUC']
            )
        );

        $this->assertSame(
            'BR',
            $this->invoke(
                $parser,
                'countryAlpha2ForTaxType',
                ['CNPJ']
            )
        );

        $this->assertSame(
            'CO',
            $this->invoke(
                $parser,
                'countryAlpha2ForTaxType',
                ['NIT']
            )
        );
    }
}
