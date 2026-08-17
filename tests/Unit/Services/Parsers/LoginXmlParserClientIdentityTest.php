<?php

namespace Tests\Unit\Services\Parsers;

use App\Services\Parsers\LoginXmlParser;
use DomainException;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class LoginXmlParserClientIdentityTest extends TestCase
{
    private LoginXmlParser $parser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->parser = new LoginXmlParser();
    }

    private function invoke(string $method, array $args): mixed
    {
        $reflection = new ReflectionMethod(
            LoginXmlParser::class,
            $method
        );

        $reflection->setAccessible(true);

        return $reflection->invokeArgs($this->parser, $args);
    }

    public function test_recognizes_real_login_fiscal_variants(): void
    {
        $cases = [
            [
                'text' => 'PBBPOLISUR S.R.L. ARGENTINA, CUIT: 30560254195',
                'tax_id' => '30560254195',
                'tax_type' => 'CUIT',
            ],
            [
                'text' => 'Caravan do Brasil Trading Ltda CNPJ: 20.403.699/0001-48',
                'tax_id' => '20403699000148',
                'tax_type' => 'CNPJ',
            ],
            [
                'text' => 'DOW BRASIL LTDA ITAJAI, CNPJ60435351/0003-19',
                'tax_id' => '60435351000319',
                'tax_type' => 'CNPJ',
            ],
            [
                'text' => 'HELLMANN DO BRASIL LTDA. CPNJ:03.414.316/0005-41',
                'tax_id' => '03414316000541',
                'tax_type' => 'CNPJ',
            ],
            [
                'text' => 'COMISSARIA PIBERNAT LTDA BRAZIL Tax ID:92102433000923',
                'tax_id' => '92102433000923',
                'tax_type' => null,
            ],
        ];

        foreach ($cases as $case) {
            $identity = $this->invoke(
                'resolveClientTaxIdentity',
                [null, $case['text'], null]
            );

            $this->assertSame(
                $case['tax_id'],
                $identity['tax_id']
            );

            $this->assertSame(
                $case['tax_type'],
                $identity['tax_type']
            );
        }
    }

    public function test_country_comes_from_fiscal_type_or_address(): void
    {
        $this->assertSame(
            'AR',
            $this->invoke(
                'inferClientCountryAlpha2',
                [
                    'CUIT',
                    "EMPRESA SA\nBUENOS AIRES - ARGENTINA",
                ]
            )
        );

        $this->assertSame(
            'BR',
            $this->invoke(
                'inferClientCountryAlpha2',
                [
                    'CNPJ',
                    "EMPRESA LTDA\nSANTOS",
                ]
            )
        );

        $this->assertSame(
            'BR',
            $this->invoke(
                'inferClientCountryAlpha2',
                [
                    null,
                    "COMISSARIA PIBERNAT LTDA\nITAJAI - SC - BRAZIL\nTax ID:92102433000923",
                ]
            )
        );

        $this->assertNull(
            $this->invoke(
                'inferClientCountryAlpha2',
                [
                    null,
                    'EMPRESA SIN PAIS NI DOCUMENTO',
                ]
            )
        );
    }

    public function test_conflicting_type_and_address_country_is_rejected(): void
    {
        $this->expectException(DomainException::class);

        $this->invoke(
            'inferClientCountryAlpha2',
            [
                'CNPJ',
                "EMPRESA LTDA\nBUENOS AIRES - ARGENTINA",
            ]
        );
    }

    public function test_clean_name_removes_real_fiscal_suffix_variants(): void
    {
        $this->assertSame(
            'HELLMANN WORLDWIDE LOGISTICS DO BRASIL LTDA.',
            $this->invoke(
                'cleanClientName',
                [
                    "HELLMANN WORLDWIDE LOGISTICS DO BRASIL LTDA. CPNJ:03.414.316/0005-41\nAV. ANA COSTA 433",
                ]
            )
        );

        $this->assertSame(
            'HELLMANN WORLDWIDE LOGISTICS S A',
            $this->invoke(
                'cleanClientName',
                [
                    'HELLMANN WORLDWIDE LOGISTICS S A CUIT 30-67658927-5',
                ]
            )
        );
    }

    public function test_invalid_structured_cuit_uses_primary_party_cuit_before_care_of(): void
    {
        $identity = $this->invoke(
            'resolveClientTaxIdentity',
            [
                '307110915',
                "PROJECT CARGO S.A.\n"
                    . "CUIT 30-71109158-7\n"
                    . "C/O ALBERTO ADRIAN OSA CUIT: 20-13138070-5\n"
                    . "TIGRE, BUENOS AIRES, ARGENTINA",
                'CUIT',
            ]
        );

        $this->assertSame(
            '30711091587',
            $identity['tax_id']
        );

        $this->assertSame(
            'CUIT',
            $identity['tax_type']
        );
    }

    public function test_generic_tax_id_uses_explicit_type_from_same_manifest(): void
    {
        $xml = simplexml_load_string(<<<'XML'
<Root>
    <BillOfLading>
        <BillOfLadingHeader>
            <BillOfLadingNumber>1</BillOfLadingNumber>
            <NotifyParty>
                INDÚSTRIA E COMÉRCIO DE EMBALAGENS MAXIPLAST LTDA
                CNPJ: 01.731.676/0001-18
            </NotifyParty>
        </BillOfLadingHeader>
    </BillOfLading>
    <BillOfLading>
        <BillOfLadingHeader>
            <BillOfLadingNumber>2</BillOfLadingNumber>
            <NotifyParty>
                IND E COM DE EMBALAGENS MAXIPLAST LTDA
                Tax ID: 01731676000118
            </NotifyParty>
        </BillOfLadingHeader>
    </BillOfLading>
</Root>
XML);

        $this->invoke(
            'buildLoginTaxTypeIndex',
            [$xml]
        );

        $identity = $this->invoke(
            'resolveClientTaxIdentity',
            [
                null,
                'IND E COM DE EMBALAGENS MAXIPLAST LTDA Tax ID: 01731676000118',
                null,
            ]
        );

        $this->assertSame(
            '01731676000118',
            $identity['tax_id']
        );

        $this->assertSame(
            'CNPJ',
            $identity['tax_type']
        );

        $this->assertSame(
            'BR',
            $this->invoke(
                'inferClientCountryAlpha2',
                [
                    $identity['tax_type'],
                    'IND E COM DE EMBALAGENS MAXIPLAST LTDA Tax ID: 01731676000118',
                ]
            )
        );
    }


    public function test_tax_id_inside_care_of_does_not_identify_primary_party(): void
    {
        $identity = $this->invoke(
            'resolveClientTaxIdentity',
            [
                null,
                "EMPRESA PRINCIPAL S.A.\n"
                    . "C/O ALBERTO ADRIAN OSA CUIT: 20-13138070-5",
                null,
            ]
        );

        $this->assertNull($identity['tax_id']);
        $this->assertNull($identity['tax_type']);
    }

    public function test_invalid_structured_cuit_does_not_override_explicit_cnpj(): void
    {
        $identity = $this->invoke(
            'resolveClientTaxIdentity',
            [
                '123456789',
                'EMPRESA BRASIL LTDA CNPJ: 20.403.699/0001-48',
                'CUIT',
            ]
        );

        $this->assertSame(
            '20403699000148',
            $identity['tax_id']
        );

        $this->assertSame(
            'CNPJ',
            $identity['tax_type']
        );
    }

}
