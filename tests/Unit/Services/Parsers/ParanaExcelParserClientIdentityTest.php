<?php

namespace Tests\Unit\Services\Parsers;

use App\Services\Parsers\ParanaExcelParser;
use ReflectionMethod;
use Tests\TestCase;

class ParanaExcelParserClientIdentityTest extends TestCase
{
    protected function invoke(
        ParanaExcelParser $parser,
        string $method,
        array $arguments = []
    ): mixed {
        $reflection = new ReflectionMethod(
            ParanaExcelParser::class,
            $method
        );

        $reflection->setAccessible(true);

        return $reflection->invokeArgs(
            $parser,
            $arguments
        );
    }

    public function test_real_parana_cuit_is_preserved(): void
    {
        $parser = new ParanaExcelParser();

        $identity = $this->invoke(
            $parser,
            'resolveClientTaxIdentity',
            [
                'MAERSK ARGENTINA S.A.',
                'CUIT: 30688415531',
            ]
        );

        $this->assertSame(
            [
                'tax_id' => '30688415531',
                'tax_type' => 'CUIT',
            ],
            $identity
        );
    }

    public function test_real_parana_ruc_is_preserved(): void
    {
        $parser = new ParanaExcelParser();

        $identity = $this->invoke(
            $parser,
            'resolveClientTaxIdentity',
            [
                'EMPRESA PARAGUAYA',
                'ASUNCION PARAGUAY RUC: 80078410-3',
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

    public function test_taxid_is_preserved_without_inventing_document_type(): void
    {
        $parser = new ParanaExcelParser();

        $identity = $this->invoke(
            $parser,
            'resolveClientTaxIdentity',
            [
                'EMPRESA',
                'PARAGUAY TAXID: 92102433000923',
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

    public function test_rut_is_generic_and_supports_real_uruguay_length(): void
    {
        $parser = new ParanaExcelParser();

        $identity = $this->invoke(
            $parser,
            'resolveClientTaxIdentity',
            [
                'INTERAG S.A.',
                'URUGUAY RUT: 214572370019',
            ]
        );

        $this->assertSame(
            '214572370019',
            $identity['tax_id']
        );

        $this->assertNull(
            $identity['tax_type']
        );

        $this->assertSame(
            'UY',
            $this->invoke(
                $parser,
                'countryAlpha2FromPartyText',
                ['INTERAG S.A. URUGUAY RUT: 214572370019']
            )
        );
    }

    public function test_rut_does_not_imply_uruguay(): void
    {
        $parser = new ParanaExcelParser();

        $identity = $this->invoke(
            $parser,
            'resolveClientTaxIdentity',
            [
                'COMERCIAL E INDUSTRIAL SULMETAL S.A.',
                'CAPIATA PARAGUAY RUT:80044231-8',
            ]
        );

        $this->assertSame(
            '800442318',
            $identity['tax_id']
        );

        $this->assertNull(
            $identity['tax_type']
        );

        $this->assertSame(
            'PY',
            $this->invoke(
                $parser,
                'countryAlpha2FromPartyText',
                ['CAPIATA PARAGUAY RUT:80044231-8']
            )
        );
    }

    public function test_party_without_fiscal_marker_gets_no_fake_identity(): void
    {
        $parser = new ParanaExcelParser();

        $identity = $this->invoke(
            $parser,
            'resolveClientTaxIdentity',
            [
                'CLIENTE PARAGUAY',
                'AVENIDA 1234 PISO 5 ASUNCION PARAGUAY',
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

    public function test_explicit_tax_types_define_their_jurisdiction(): void
    {
        $parser = new ParanaExcelParser();

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

    public function test_declared_country_codes_and_names_are_understood(): void
    {
        $parser = new ParanaExcelParser();

        $cases = [
            'AR' => 'AR',
            'ARG' => 'AR',
            'ARGENTINA' => 'AR',
            'PY' => 'PY',
            'PRY' => 'PY',
            'PARAGUAY' => 'PY',
            'UY' => 'UY',
            'URY' => 'UY',
            'URUGUAY' => 'UY',
            'BRASIL' => 'BR',
            'COLOMBIA' => 'CO',
        ];

        foreach ($cases as $input => $expected) {
            $this->assertSame(
                $expected,
                $this->invoke(
                    $parser,
                    'countryAlpha2FromDeclaredValue',
                    [$input]
                )
            );
        }
    }

    public function test_conflicting_fiscal_identities_are_rejected(): void
    {
        $parser = new ParanaExcelParser();

        $this->expectException(\DomainException::class);

        $this->invoke(
            $parser,
            'resolveClientTaxIdentity',
            [
                'EMPRESA CUIT: 30688415531',
                'PARAGUAY RUC: 80078410-3',
            ]
        );
    }
}
