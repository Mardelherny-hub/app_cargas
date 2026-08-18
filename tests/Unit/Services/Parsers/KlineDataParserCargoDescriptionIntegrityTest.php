<?php

namespace Tests\Unit\Services\Parsers;

use App\Services\Parsers\KlineDataParser;
use DomainException;
use ReflectionMethod;
use Tests\TestCase;

class KlineDataParserCargoDescriptionIntegrityTest extends TestCase
{
    public function test_missing_source_description_remains_unknown(): void
    {
        $descriptions = $this->invokeParser(
            'extractCargoDescriptions',
            [[]]
        );

        $this->assertSame([], $descriptions);
    }

    public function test_structured_cmmdrec_description_is_preserved(): void
    {
        $descriptions = $this->invokeParser(
            'extractCargoDescriptions',
            [[
                'CMMDREC0' => [
                    '0000001NAUT00000185VEHICLES'
                    . '01517000000KGS001585450M3'
                    . '                           87032100',
                ],
            ]]
        );

        $this->assertCount(1, $descriptions);
        $this->assertStringContainsString(
            '185',
            $descriptions[0]
        );
        $this->assertStringContainsStringIgnoringCase(
            'vehicles',
            $descriptions[0]
        );
    }

    public function test_missing_description_is_rejected(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('KKLU-TEST');

        $this->invokeParser(
            'assertCargoDescriptions',
            [[], 'KKLU-TEST']
        );
    }

    private function invokeParser(
        string $method,
        array $arguments
    ): mixed {
        $parser = new KlineDataParser();

        $reflection = new ReflectionMethod(
            $parser,
            $method
        );
        $reflection->setAccessible(true);

        return $reflection->invokeArgs(
            $parser,
            $arguments
        );
    }
}
