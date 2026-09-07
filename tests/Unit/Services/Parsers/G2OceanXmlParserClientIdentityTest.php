<?php

namespace Tests\Unit\Services\Parsers;

use App\Services\Parsers\G2OceanXmlParser;
use ReflectionMethod;
use Tests\TestCase;

class G2OceanXmlParserClientIdentityTest extends TestCase
{
    protected function invoke(
        G2OceanXmlParser $parser,
        string $method,
        array $arguments = []
    ): mixed {
        $reflection = new ReflectionMethod(
            G2OceanXmlParser::class,
            $method
        );

        $reflection->setAccessible(true);

        return $reflection->invokeArgs(
            $parser,
            $arguments
        );
    }

    public function test_real_g2ocean_cuit_variants_are_preserved(): void
    {
        $parser = new G2OceanXmlParser();

        $cases = [
            [
                'CUIT NBR 20-23208649-2 SUIPACHA 1067 CABA ARGENTINA',
                '20232086492',
            ],
            [
                'REPUBLICA ARGENTINA, CUIT NBR.30597797644',
                '30597797644',
            ],
            [
                'PROVINCIA DE BUENOS AIRES, ARGENTINA CUIT: 30-59742920-3',
                '30597429203',
            ],
            [
                'AIRES ARGENTINA CUIT:30597429203',
                '30597429203',
            ],
            [
                'SAN LUIS, ARGENTINA, CUIT 3062116026-1',
                '30621160261',
            ],
        ];

        foreach ($cases as [$address, $expectedTaxId]) {
            $identity = $this->invoke(
                $parser,
                'resolvePartyTaxIdentity',
                ['EMPRESA', $address]
            );

            $this->assertSame(
                $expectedTaxId,
                $identity['tax_id']
            );

            $this->assertSame(
                'CUIT',
                $identity['tax_type']
            );
        }
    }

    public function test_real_foreign_shipper_without_tax_gets_no_fake_identity(): void
    {
        $parser = new G2OceanXmlParser();

        $identity = $this->invoke(
            $parser,
            'resolvePartyTaxIdentity',
            [
                'FOREIGN SHIPPER',
                'UNIT 3203 TRINITY TOWER 575 WU SONG ROAD SHANGHAI CHINA',
            ]
        );

        $this->assertSame(
            [
                'tax_id' => null,
                'tax_type' => null,
            ],
            $identity
        );
    }

    public function test_explicit_foreign_fiscal_types_are_preserved(): void
    {
        $parser = new G2OceanXmlParser();

        $ruc = $this->invoke(
            $parser,
            'resolvePartyTaxIdentity',
            ['EMPRESA', 'RUC: 80078410-3 ASUNCION']
        );

        $cnpj = $this->invoke(
            $parser,
            'resolvePartyTaxIdentity',
            ['EMPRESA', 'CNPJ 20.403.699/0001-48']
        );

        $nit = $this->invoke(
            $parser,
            'resolvePartyTaxIdentity',
            ['EMPRESA', 'NIT 900123456-7']
        );

        $this->assertSame(
            ['tax_id' => '800784103', 'tax_type' => 'RUC'],
            $ruc
        );

        $this->assertSame(
            ['tax_id' => '20403699000148', 'tax_type' => 'CNPJ'],
            $cnpj
        );

        $this->assertSame(
            ['tax_id' => '9001234567', 'tax_type' => 'NIT'],
            $nit
        );
    }

    public function test_generic_tax_id_never_invents_document_type(): void
    {
        $parser = new G2OceanXmlParser();

        $identity = $this->invoke(
            $parser,
            'resolvePartyTaxIdentity',
            [
                'EMPRESA',
                'TAX ID: 92102433000923',
            ]
        );

        $this->assertSame(
            '92102433000923',
            $identity['tax_id']
        );

        $this->assertNull(
            $identity['tax_type']
        );
    }

    public function test_explicit_tax_type_defines_jurisdiction(): void
    {
        $parser = new G2OceanXmlParser();

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

    public function test_conflicting_name_and_address_identity_is_rejected(): void
    {
        $parser = new G2OceanXmlParser();

        $this->expectException(\DomainException::class);

        $this->invoke(
            $parser,
            'resolvePartyTaxIdentity',
            [
                'EMPRESA CUIT 30-59742920-3',
                'RUC: 80078410-3',
            ]
        );
    }
}
