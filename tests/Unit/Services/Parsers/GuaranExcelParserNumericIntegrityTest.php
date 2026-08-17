<?php

namespace Tests\Unit\Services\Parsers;

use App\Services\Parsers\GuaranExcelParser;
use ReflectionMethod;
use Tests\TestCase;

class GuaranExcelParserNumericIntegrityTest extends TestCase
{
    private function parseVolume($value): float
    {
        $parser = app(GuaranExcelParser::class);

        $method = new ReflectionMethod(
            GuaranExcelParser::class,
            'parseVolume'
        );
        $method->setAccessible(true);

        return $method->invoke($parser, $value);
    }

    public function test_volume_preserves_comma_decimal(): void
    {
        $this->assertSame(
            76.2,
            $this->parseVolume('76,2')
        );

        $this->assertSame(
            32.8,
            $this->parseVolume('32,8')
        );
    }

    public function test_volume_preserves_integer_and_native_numeric_values(): void
    {
        $this->assertSame(
            50.0,
            $this->parseVolume('50')
        );

        $this->assertSame(
            60.0,
            $this->parseVolume(60)
        );

        $this->assertSame(
            76.2,
            $this->parseVolume(76.2)
        );
    }

    public function test_volume_supports_locale_number_formats(): void
    {
        $this->assertSame(
            33536.670,
            $this->parseVolume('33.536,670')
        );

        $this->assertSame(
            33536.67,
            $this->parseVolume('33,536.67')
        );
    }

    public function test_empty_volume_becomes_zero(): void
    {
        $this->assertSame(
            0.0,
            $this->parseVolume(null)
        );

        $this->assertSame(
            0.0,
            $this->parseVolume('')
        );
    }
}
