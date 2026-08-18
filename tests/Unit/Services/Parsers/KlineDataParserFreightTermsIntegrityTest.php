<?php

namespace Tests\Unit\Services\Parsers;

use App\Services\Parsers\KlineDataParser;
use ReflectionMethod;
use Tests\TestCase;

class KlineDataParserFreightTermsIntegrityTest extends TestCase
{
    public function test_explicit_prepaid_is_preserved(): void
    {
        $this->assertSame(
            'prepaid',
            $this->extract([
                'FRTCREC0' => [
                    '001POFT OCEAN FREIGHT',
                ],
            ])
        );
    }

    public function test_explicit_collect_is_preserved(): void
    {
        $this->assertSame(
            'collect',
            $this->extract([
                'FRTCREC0' => [
                    '001COFT OCEAN FREIGHT',
                ],
            ])
        );
    }

    public function test_missing_freight_record_remains_unknown(): void
    {
        $this->assertNull(
            $this->extract([])
        );
    }

    public function test_freight_record_without_terms_remains_unknown(): void
    {
        $this->assertNull(
            $this->extract([
                'FRTCREC0' => [
                    '001USD 1500.00',
                ],
            ])
        );
    }

    private function extract(array $data): ?string
    {
        $parser = new KlineDataParser();

        $method = new ReflectionMethod(
            $parser,
            'extractFreightTerms'
        );
        $method->setAccessible(true);

        return $method->invoke(
            $parser,
            $data
        );
    }
}
