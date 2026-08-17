<?php

namespace Tests\Unit\Services\Parsers;

use App\Services\Parsers\TfpTextParser;
use ReflectionMethod;
use Tests\TestCase;

class TfpTextParserClientIdentityTest extends TestCase
{
    protected function invoke(
        TfpTextParser $parser,
        string $method,
        array $arguments = []
    ): mixed {
        $reflection = new ReflectionMethod(
            TfpTextParser::class,
            $method
        );

        $reflection->setAccessible(true);

        return $reflection->invokeArgs(
            $parser,
            $arguments
        );
    }

    public function test_real_tfp_parties_without_tax_do_not_get_fake_identity(): void
    {
        $parser = new TfpTextParser();

        $shipper = $this->invoke(
            $parser,
            'resolveClientTaxIdentity',
            [
                null,
                'MAERSK LINE ARGENTINA S.A.',
                'Av. del Libertador 1969 - Vicente López',
            ]
        );

        $consignee = $this->invoke(
            $parser,
            'resolveClientTaxIdentity',
            [
                null,
                'CARGOPACK PARAGUAY S.A.',
                'SPANO Y ANDRADE',
            ]
        );

        $this->assertSame(
            [
                'tax_id' => null,
                'tax_type' => null,
            ],
            $shipper
        );

        $this->assertSame(
            [
                'tax_id' => null,
                'tax_type' => null,
            ],
            $consignee
        );
    }

    public function test_explicit_embedded_tax_types_are_preserved(): void
    {
        $parser = new TfpTextParser();

        $ruc = $this->invoke(
            $parser,
            'resolveClientTaxIdentity',
            [
                null,
                'EMPRESA PARAGUAYA',
                'RUC: 80094634-0 CALLE ROQUE CENTURION',
            ]
        );

        $cuit = $this->invoke(
            $parser,
            'resolveClientTaxIdentity',
            [
                null,
                'EMPRESA ARGENTINA',
                'CHILE 801 CUIT 30-69318494-7',
            ]
        );

        $this->assertSame('800946340', $ruc['tax_id']);
        $this->assertSame('RUC', $ruc['tax_type']);

        $this->assertSame('30693184947', $cuit['tax_id']);
        $this->assertSame('CUIT', $cuit['tax_type']);
    }

    public function test_structured_ruc_is_preserved_as_ruc(): void
    {
        $parser = new TfpTextParser();

        $identity = $this->invoke(
            $parser,
            'resolveClientTaxIdentity',
            [
                '80094634-0',
                'EMPRESA PARAGUAYA',
                'ASUNCION',
            ]
        );

        $this->assertSame('800946340', $identity['tax_id']);
        $this->assertSame('RUC', $identity['tax_type']);
    }

    public function test_generic_tax_id_never_infers_document_type_by_length(): void
    {
        $parser = new TfpTextParser();

        $identity = $this->invoke(
            $parser,
            'resolveClientTaxIdentity',
            [
                null,
                'EMPRESA',
                'TAX ID: 30693184947',
            ]
        );

        $this->assertSame('30693184947', $identity['tax_id']);
        $this->assertNull($identity['tax_type']);
    }

    public function test_explicit_tax_type_maps_to_its_jurisdiction(): void
    {
        $parser = new TfpTextParser();

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

        $this->assertNull(
            $this->invoke(
                $parser,
                'countryAlpha2ForTaxType',
                [null]
            )
        );
    }

    public function test_conflicting_structured_and_embedded_identity_is_rejected(): void
    {
        $parser = new TfpTextParser();

        $this->expectException(\DomainException::class);

        $this->invoke(
            $parser,
            'resolveClientTaxIdentity',
            [
                '80094634-0',
                'EMPRESA',
                'CUIT 30-69318494-7',
            ]
        );
    }
}
