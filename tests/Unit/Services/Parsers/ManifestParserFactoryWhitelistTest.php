<?php

namespace Tests\Unit\Services\Parsers;

use App\Services\Parsers\CmspEdiParser;
use App\Services\Parsers\G2OceanXmlParser;
use App\Services\Parsers\GuaranExcelParser;
use App\Services\Parsers\KlineDataParser;
use App\Services\Parsers\LoginXmlParser;
use App\Services\Parsers\ManifestParserFactory;
use App\Services\Parsers\NavsurTextParser;
use App\Services\Parsers\ParanaExcelParser;
use App\Services\Parsers\TfpTextParser;
use Tests\TestCase;

class ManifestParserFactoryWhitelistTest extends TestCase
{
    public function test_all_eight_audited_parsers_are_enabled(): void
    {
        $factory = new ManifestParserFactory();

        $this->assertSame(
            [
                KlineDataParser::class,
                ParanaExcelParser::class,
                GuaranExcelParser::class,
                LoginXmlParser::class,
                TfpTextParser::class,
                CmspEdiParser::class,
                NavsurTextParser::class,
                G2OceanXmlParser::class,
            ],
            $factory->getAvailableParsers()
        );
    }

    public function test_all_historical_import_extensions_are_enabled(): void
    {
        $factory = new ManifestParserFactory();

        $this->assertSame(
            ['dat', 'txt', 'xlsx', 'xls', 'xml', 'edi'],
            $factory->getFormatStatistics()['extensions_supported']
        );
    }
}
