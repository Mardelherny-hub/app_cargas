<?php

namespace Tests\Unit\Services\Parsers;

use App\Services\Parsers\KlineDataParser;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class KlineDataParserClientIdentityTest extends TestCase
{
    private function build(array $lines): array
    {
        $parser = new KlineDataParser();

        $method = new ReflectionMethod(
            KlineDataParser::class,
            'buildClientDataFromLines'
        );

        $method->setAccessible(true);

        return $method->invoke($parser, $lines);
    }

    public function test_preserves_explicit_foreign_tax_document_types(): void
    {
        $cases = [
            [
                'lines' => [
                    'RENAULT SOFASA S.A.S  NIT 860.025.792-3',
                ],
                'tax_id' => '8600257923',
                'tax_type' => 'NIT',
            ],
            [
                'lines' => [
                    'RENAULT DO BRASIL S.A  CNPJ: 00.913.443/0001-73',
                ],
                'tax_id' => '00913443000173',
                'tax_type' => 'CNPJ',
            ],
            [
                'lines' => [
                    'RENAULT ARGENTINA S/A  CUIT 30-50331781-4',
                ],
                'tax_id' => '30503317814',
                'tax_type' => 'CUIT',
            ],
            [
                'lines' => [
                    'JUMBO INTERNACIONAL SA  RUC 80019278-8',
                ],
                'tax_id' => '800192788',
                'tax_type' => 'RUC',
            ],
        ];

        foreach ($cases as $case) {
            $data = $this->build($case['lines']);

            $this->assertSame(
                $case['tax_id'],
                $data['tax_id']
            );

            $this->assertSame(
                $case['tax_type'],
                $data['tax_type']
            );
        }
    }

    public function test_vat_marker_reuses_real_tax_identity_without_inventing_type(): void
    {
        $data = $this->build([
            '11 IN SA VAT 80086986-9',
        ]);

        $this->assertSame('11 IN SA', $data['name']);
        $this->assertSame('800869869', $data['tax_id']);
        $this->assertNull($data['tax_type']);
    }

    public function test_generic_tax_id_does_not_invent_document_type(): void
    {
        $data = $this->build([
            'GENERIC COMPANY  TAX ID: 123456789',
        ]);

        $this->assertSame('123456789', $data['tax_id']);
        $this->assertNull($data['tax_type']);
    }

    public function test_number_without_fiscal_marker_is_not_treated_as_tax_id(): void
    {
        $data = $this->build([
            'RENAULT ARGENTINA S.A.',
            'BUENOS AIRES 1111',
        ]);

        $this->assertNull($data['tax_id']);
        $this->assertNull($data['tax_type']);
    }
}
