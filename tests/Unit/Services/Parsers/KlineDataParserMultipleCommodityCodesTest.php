<?php

namespace Tests\Unit\Services\Parsers;

use App\Services\Parsers\KlineDataParser;
use ReflectionMethod;
use Tests\TestCase;

class KlineDataParserMultipleCommodityCodesTest extends TestCase
{
    private function codes(array $data): array
    {
        $parser = new KlineDataParser();

        $method = new ReflectionMethod(
            KlineDataParser::class,
            'extractNCMCodes'
        );

        $method->setAccessible(true);

        return $method->invoke($parser, $data);
    }

    public function test_extracts_all_explicit_hs_codes_without_duplication(): void
    {
        $codes = $this->codes([
            'DESCREC0' => [
                '000001HS CODE: 87.03.22 - 87.03.23',
            ],
        ]);

        $this->assertSame([
            '87032200',
            '87032300',
        ], $codes);
    }

    public function test_structured_code_is_primary_and_additional_ncm_is_preserved(): void
    {
        $codes = $this->codes([
            'CMMDREC0' => [
                '000001NAUT00000572VEHICLES 06661940000KGS006743880M3 87032100',
            ],
            'DESCREC0' => [
                '000001NCM: 87.03.2100 / 8703.23.10',
            ],
        ]);

        $this->assertSame([
            '87032100',
            '87032310',
        ], $codes);
    }

    public function test_repeated_code_is_stored_only_once(): void
    {
        $codes = $this->codes([
            'CMMDREC0' => [
                '000001NAUT00000572VEHICLES 06661940000KGS006743880M3 87032100',
            ],
            'DESCREC0' => [
                '000001NCM: 87.03.2100',
            ],
        ]);

        $this->assertSame([
            '87032100',
        ], $codes);
    }
}
