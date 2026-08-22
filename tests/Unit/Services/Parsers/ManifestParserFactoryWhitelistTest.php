<?php

namespace Tests\Unit\Services\Parsers;

use App\Services\Parsers\CmspEdiParser;
use App\Services\Parsers\KlineDataParser;
use App\Services\Parsers\LoginXmlParser;
use App\Services\Parsers\NavsurTextParser;
use App\Services\Parsers\ManifestParserFactory;
use Tests\TestCase;

class ManifestParserFactoryWhitelistTest extends TestCase
{
    public function test_only_audited_parsers_are_enabled(): void
    {
        $factory = new ManifestParserFactory();

        $this->assertSame(
            [
                KlineDataParser::class,
                NavsurTextParser::class,
                CmspEdiParser::class,
                LoginXmlParser::class,
            ],
            $factory->getAvailableParsers()
        );
    }

    public function test_only_audited_extensions_are_enabled(): void
    {
        $factory = new ManifestParserFactory();

        $this->assertSame(
            ['dat', 'txt', 'edi', 'xml'],
            $factory->getFormatStatistics()['extensions_supported']
        );
    }
}
