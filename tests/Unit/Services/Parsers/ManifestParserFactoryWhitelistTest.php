<?php

namespace Tests\Unit\Services\Parsers;

use App\Services\Parsers\CmspEdiParserCompat;
use App\Services\Parsers\G2OceanXmlParserCompat;
use App\Services\Parsers\GuaranExcelParserCompat;
use App\Services\Parsers\KlineDataParserCompat;
use App\Services\Parsers\LoginXmlParserCompat;
use App\Services\Parsers\ManifestParserFactory;
use App\Services\Parsers\NavsurTextParserCompat;
use App\Services\Parsers\ParanaExcelParserCompat;
use App\Services\Parsers\TfpTextParserCompat;
use Tests\TestCase;

class ManifestParserFactoryWhitelistTest extends TestCase
{
    public function test_all_eight_audited_parsers_are_enabled(): void
    {
        $factory = new ManifestParserFactory();

        $this->assertSame(
            [
                KlineDataParserCompat::class,
                ParanaExcelParserCompat::class,
                GuaranExcelParserCompat::class,
                LoginXmlParserCompat::class,
                TfpTextParserCompat::class,
                CmspEdiParserCompat::class,
                NavsurTextParserCompat::class,
                G2OceanXmlParserCompat::class,
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

    public function test_historical_tfp_signature_is_detected(): void
    {
        $path = $this->writeTemporaryImportFile(
            '.txt',
            "**BL**\nBLNUMERO: /*3185*\nBLMARITIMONUMERO: /*3185*\n"
        );

        try {
            $this->assertTrue(
                (new TfpTextParserCompat())->canParse($path)
            );
        } finally {
            @unlink($path);
        }
    }

    public function test_historical_login_signature_is_detected(): void
    {
        $path = $this->writeTemporaryImportFile(
            '.xml',
            '<BillOfLadingRoot>'
            . '<BillOfLading>'
            . '<BillOfLadingHeader />'
            . '<BillOfLadingLineDetail>'
            . '<BillOfLadingLine>'
            . '<Container />'
            . '<Tare />'
            . '<NetWeight />'
            . '<GrossWeight />'
            . '</BillOfLadingLine>'
            . '</BillOfLadingLineDetail>'
            . '</BillOfLading>'
            . '</BillOfLadingRoot>'
        );

        try {
            $this->assertTrue(
                (new LoginXmlParserCompat())->canParse($path)
            );
        } finally {
            @unlink($path);
        }
    }

    public function test_cuscar_is_detected_by_content_with_dot_one_extension(): void
    {
        $path = $this->writeTemporaryImportFile(
            '.1',
            "UNA:+.? '\nUNB+UNOA:1+SENDER+RECEIVER+260806:2035+1'\n"
            . "UNH+1+CUSCAR:D:96B:UN'\nBGM+85+1+9'\n"
        );

        try {
            $this->assertTrue(
                (new CmspEdiParserCompat())->canParse($path)
            );
        } finally {
            @unlink($path);
        }
    }

    private function writeTemporaryImportFile(
        string $suffix,
        string $content
    ): string {
        $path = sys_get_temp_dir()
            . DIRECTORY_SEPARATOR
            . 'app_cargas_parser_'
            . bin2hex(random_bytes(8))
            . $suffix;

        file_put_contents($path, $content);

        return $path;
    }
}
