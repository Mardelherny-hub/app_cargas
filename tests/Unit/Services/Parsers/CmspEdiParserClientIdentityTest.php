<?php

namespace Tests\Unit\Services\Parsers;

use App\Services\Parsers\CmspEdiParser;
use ReflectionMethod;
use Tests\TestCase;

class CmspEdiParserClientIdentityTest extends TestCase
{
    protected function invoke(
        CmspEdiParser $parser,
        string $method,
        array $arguments = []
    ): mixed {
        $reflection = new ReflectionMethod(
            CmspEdiParser::class,
            $method
        );

        $reflection->setAccessible(true);

        return $reflection->invokeArgs(
            $parser,
            $arguments
        );
    }

    public function test_real_adz_preserves_tax_without_inventing_cuit(): void
    {
        $parser = new CmspEdiParser();

        $identity = $this->invoke(
            $parser,
            'resolvePartyTaxIdentity',
            [[
                'name' => 'MSG S.R.L. BUENOS AIRES - ARGENTINA',
                'address' => null,
                'type' => 'consignee',
                'tax_id' => '30-712412093',
                'tax_type' => null,
            ]]
        );

        $this->assertSame(
            '30712412093',
            $identity['tax_id']
        );

        // RFF+ADZ identifica fiscalmente, pero no declara que sea CUIT.
        $this->assertNull(
            $identity['tax_type']
        );
    }

    public function test_explicit_cuit_is_preserved_as_cuit(): void
    {
        $parser = new CmspEdiParser();

        $taxId = '30585343427';

        $taxType = $this->invoke(
            $parser,
            'extractExplicitTaxTypeFromText',
            [
                'AGENCIA MARITIMA INTERNACIONAL SA CUIT 30-58534342-7 DIRECCION',
                $taxId,
            ]
        );

        $this->assertSame('CUIT', $taxType);
    }

    public function test_real_parties_provide_country_from_nad_text(): void
    {
        $parser = new CmspEdiParser();

        $this->assertSame(
            'AR',
            $this->invoke(
                $parser,
                'countryAlpha2FromPartyText',
                ['MSG S.R.L. BUENOS AIRES - ARGENTINA']
            )
        );

        $this->assertSame(
            'PY',
            $this->invoke(
                $parser,
                'countryAlpha2FromPartyText',
                ['CMSP S.A. ASUNCION - PARAGUAY']
            )
        );
    }

    public function test_party_without_tax_gets_no_fake_identity(): void
    {
        $parser = new CmspEdiParser();

        $identity = $this->invoke(
            $parser,
            'resolvePartyTaxIdentity',
            [[
                'name' => 'CMSP S.A. ASUNCION - PARAGUAY',
                'address' => null,
                'type' => 'shipper',
                'tax_id' => null,
                'tax_type' => null,
            ]]
        );

        $this->assertSame(
            [
                'tax_id' => null,
                'tax_type' => null,
            ],
            $identity
        );
    }

    public function test_generic_tax_id_does_not_invent_document_type(): void
    {
        $parser = new CmspEdiParser();

        $identity = $this->invoke(
            $parser,
            'resolvePartyTaxIdentity',
            [[
                'name' => 'EMPRESA TAX ID: 92102433000923',
                'address' => 'PARAGUAY',
                'type' => 'shipper',
                'tax_id' => null,
                'tax_type' => null,
            ]]
        );

        $this->assertSame(
            '92102433000923',
            $identity['tax_id']
        );

        $this->assertNull(
            $identity['tax_type']
        );
    }

    public function test_explicit_types_define_their_jurisdiction(): void
    {
        $parser = new CmspEdiParser();

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
}
