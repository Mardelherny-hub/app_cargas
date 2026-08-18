<?php

namespace Tests\Unit\Services\Parsers;

use App\Services\Parsers\KlineDataParser;
use DomainException;
use ReflectionMethod;
use Tests\TestCase;

class KlineDataParserCommercialIntegrityTest extends TestCase
{
    public function test_unknown_declared_value_has_no_currency(): void
    {
        $this->assertNull(
            $this->resolveCurrency(null, null)
        );
    }

    public function test_currency_without_declared_value_is_not_persisted(): void
    {
        $this->assertNull(
            $this->resolveCurrency(null, 'USD')
        );
    }

    public function test_explicit_currency_is_preserved_when_value_exists(): void
    {
        $this->assertSame(
            'BRL',
            $this->resolveCurrency(1500.00, 'brl')
        );
    }

    public function test_value_without_currency_is_rejected(): void
    {
        $this->expectException(DomainException::class);

        $this->resolveCurrency(
            1500.00,
            null
        );
    }

    private function resolveCurrency(
        ?float $value,
        ?string $currency
    ): ?string {
        $parser = new KlineDataParser();

        $method = new ReflectionMethod(
            $parser,
            'resolveDeclaredValueCurrency'
        );
        $method->setAccessible(true);

        return $method->invoke(
            $parser,
            $value,
            $currency
        );
    }
}
