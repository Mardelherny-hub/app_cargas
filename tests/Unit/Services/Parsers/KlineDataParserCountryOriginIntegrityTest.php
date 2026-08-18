<?php

namespace Tests\Unit\Services\Parsers;

use App\Services\Parsers\KlineDataParser;
use ReflectionMethod;
use Tests\TestCase;

class KlineDataParserCountryOriginIntegrityTest extends TestCase
{
    public function test_explicit_brazil_origin_is_preserved(): void
    {
        $this->assertSame(
            'BR',
            $this->extract([
                'MARKREC0' => [
                    'ORIGEM - BRASIL',
                ],
            ])
        );
    }

    public function test_explicit_argentina_origin_is_preserved(): void
    {
        $this->assertSame(
            'AR',
            $this->extract([
                'DESCREC0' => [
                    'ORIGEN - ARGENTINA',
                ],
            ])
        );
    }

    public function test_brazilian_loading_port_is_not_cargo_origin(): void
    {
        $this->assertNull(
            $this->extract([
                'GNRLREC' => [
                    '20250514 BRPNG PARANAGUA',
                ],
            ])
        );
    }

    public function test_colombian_loading_port_is_not_cargo_origin(): void
    {
        $this->assertNull(
            $this->extract([
                'GNRLREC' => [
                    '20250429 COCTG CARTAGENA',
                ],
            ])
        );
    }

    public function test_missing_origin_remains_unknown(): void
    {
        $this->assertNull(
            $this->extract([])
        );
    }

    private function extract(array $data): ?string
    {
        $parser = new KlineDataParser();

        $method = new ReflectionMethod(
            $parser,
            'extractCountryOfOrigin'
        );
        $method->setAccessible(true);

        return $method->invoke(
            $parser,
            $data
        );
    }
}
