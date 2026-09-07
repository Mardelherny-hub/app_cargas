<?php

namespace Tests\Unit\Services\Parsers;

use App\Services\Parsers\GuaranExcelParser;
use ReflectionMethod;
use Tests\TestCase;

class GuaranExcelParserClientIdentityTest extends TestCase
{
    protected function invoke(
        GuaranExcelParser $parser,
        string $method,
        array $arguments = []
    ): mixed {
        $reflection = new ReflectionMethod(
            GuaranExcelParser::class,
            $method
        );

        $reflection->setAccessible(true);

        return $reflection->invokeArgs(
            $parser,
            $arguments
        );
    }

    public function test_real_guaran_ruc_is_preserved(): void
    {
        $parser = new GuaranExcelParser();

        $identity = $this->invoke(
            $parser,
            'resolveClientTaxIdentity',
            [
                'MSC MEDITERRANEAN SHIPPING COMPANY PARAGUAY S.A',
                'RUC: 80078410-3 Asunción - Paraguay',
            ]
        );

        $this->assertSame(
            [
                'tax_id' => '800784103',
                'tax_type' => 'RUC',
            ],
            $identity
        );
    }

    public function test_real_guaran_cuit_variants_are_preserved(): void
    {
        $parser = new GuaranExcelParser();

        $cases = [
            [
                'MSC ARGENTINA',
                'CUIT 30-69318494-7',
                '30693184947',
            ],
            [
                'Agencia Maritima Internacional SA',
                '25 de mayo 555 p19 CABA CUIT 3058534342-7',
                '30585343427',
            ],
        ];

        foreach ($cases as [$name, $address, $taxId]) {
            $identity = $this->invoke(
                $parser,
                'resolveClientTaxIdentity',
                [$name, $address]
            );

            $this->assertSame(
                $taxId,
                $identity['tax_id']
            );

            $this->assertSame(
                'CUIT',
                $identity['tax_type']
            );
        }
    }

    public function test_generic_tax_id_never_invents_document_type(): void
    {
        $parser = new GuaranExcelParser();

        $identity = $this->invoke(
            $parser,
            'resolveClientTaxIdentity',
            [
                'EMPRESA',
                'PARAGUAY TAX ID: 92102433000923',
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

    public function test_party_without_tax_gets_no_fake_identity(): void
    {
        $parser = new GuaranExcelParser();

        $identity = $this->invoke(
            $parser,
            'resolveClientTaxIdentity',
            [
                'CORPORACION AVICOLA S.A.',
                'SAN ANTONIO PARAGUAY',
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

    public function test_explicit_tax_types_define_jurisdiction(): void
    {
        $parser = new GuaranExcelParser();

        $cases = [
            'CUIT' => 'AR',
            'RUC' => 'PY',
            'CNPJ' => 'BR',
            'NIT' => 'CO',
        ];

        foreach ($cases as $type => $country) {
            $this->assertSame(
                $country,
                $this->invoke(
                    $parser,
                    'countryAlpha2ForTaxType',
                    [$type]
                )
            );
        }
    }

    public function test_real_port_codes_provide_fallback_country_context(): void
    {
        $parser = new GuaranExcelParser();

        $this->assertSame(
            'PY',
            $this->invoke(
                $parser,
                'countryAlpha2FromPortCode',
                ['PYASU']
            )
        );

        $this->assertSame(
            'PY',
            $this->invoke(
                $parser,
                'countryAlpha2FromPortCode',
                ['PYVLL']
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
    }

    public function test_country_declared_in_text_is_preserved(): void
    {
        $parser = new GuaranExcelParser();

        $this->assertSame(
            'PY',
            $this->invoke(
                $parser,
                'countryAlpha2FromPartyText',
                ['RUC: 80078410-3 ASUNCION - PARAGUAY']
            )
        );

        $this->assertSame(
            'AR',
            $this->invoke(
                $parser,
                'countryAlpha2FromPartyText',
                ['BUENOS AIRES ARGENTINA']
            )
        );
    }

    public function test_conflicting_explicit_fiscal_identities_are_rejected(): void
    {
        $parser = new GuaranExcelParser();

        $this->expectException(\DomainException::class);

        $this->invoke(
            $parser,
            'resolveClientTaxIdentity',
            [
                'EMPRESA CUIT 30-69318494-7',
                'RUC: 80078410-3 PARAGUAY',
            ]
        );
    }
}
