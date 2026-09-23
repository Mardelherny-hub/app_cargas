<?php

namespace Tests\Unit\Services\Parsers;

use App\Services\Parsers\G2OceanXmlParser;
use App\Services\Parsers\GuaranExcelParser;
use App\Services\Parsers\KlineDataParser;
use App\Services\Parsers\LoginXmlParser;
use App\Services\Parsers\NavsurTextParser;
use App\Services\Parsers\ParanaExcelParser;
use App\Services\Parsers\TfpTextParser;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class ImporterDateIntegrityContractTest extends TestCase
{
    private function invoke(object $parser, string $method, array $args = []): mixed
    {
        $reflection = new ReflectionMethod($parser::class, $method);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs($parser, $args);
    }

    public function test_kline_on_board_date_is_loading_not_voyage_departure(): void
    {
        $parser = new KlineDataParser();

        $dates = $this->invoke($parser, 'extractDates', [[
            'GNRLREC0' => ['000001 OP20260717'],
            'DESCREC0' => [
                '000001 LADEN ON BOARD GOODWOOD 48 AT FREEPORT, TX ON 7-17-26',
            ],
        ]]);

        $this->assertSame('2026-07-17', $dates['loading_date']);
        $this->assertNull($dates['etd']);
        $this->assertNull($dates['eta']);
        $this->assertNull($dates['bl_date']);
    }

    public function test_kline_does_not_fabricate_missing_bl_dates(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 4) . '/app/Services/Parsers/KlineDataParser.php'
        );

        $start = strpos($source, 'protected function createBillOfLading(');
        $end = strpos($source, 'protected function createShipmentItem(', $start);
        $block = substr($source, $start, $end - $start);

        $this->assertStringNotContainsString(
            'now()->toDateString()',
            $block
        );
        $this->assertStringContainsString(
            "'bill_date'         => \$dates['bl_date'] ?? null",
            $block
        );
        $this->assertStringContainsString(
            "'loading_date'      => \$dates['loading_date'] ?? null",
            $block
        );
    }

    public function test_login_missing_dates_remain_null(): void
    {
        $parser = new LoginXmlParser();

        $this->assertNull(
            $this->invoke($parser, 'parseDate', [null])
        );

        $this->assertNull(
            $this->invoke($parser, 'parseDate', [''])
        );
    }

    public function test_login_invalid_non_empty_date_is_rejected(): void
    {
        $parser = new LoginXmlParser();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Login XML informa una fecha inválida');

        $this->invoke(
            $parser,
            'parseDate',
            ['fecha-imposible']
        );
    }

    public function test_navsur_does_not_use_import_day_as_document_date(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 4) . '/app/Services/Parsers/NavsurTextParser.php'
        );

        $start = strpos($source, 'protected function createBillOfLading(');
        $end = strpos($source, 'protected function createShipmentItem(', $start);
        $block = substr($source, $start, $end - $start);

        $this->assertStringNotContainsString('today()', $block);
        $this->assertStringContainsString("'bill_date' => null", $block);
        $this->assertStringContainsString("'loading_date' => null", $block);
    }

    public function test_parana_missing_dates_remain_missing(): void
    {
        $parser = new ParanaExcelParser();

        $timing = $this->invoke(
            $parser,
            'buildParanaVoyageTiming'
        );
        $billDates = $this->invoke(
            $parser,
            'buildParanaBillDates',
            [null]
        );

        $this->assertNull($timing['departure_date']);
        $this->assertNull($timing['estimated_arrival_date']);
        $this->assertNull($billDates['bill_date']);
        $this->assertNull($billDates['loading_date']);
    }

    public function test_guaran_keeps_operational_dates_null_when_not_entered(): void
    {
        $parser = new GuaranExcelParser();

        $dates = $this->invoke(
            $parser,
            'buildBillDocumentDates',
            ['02/07/2025', null, null]
        );

        $this->assertSame(
            '2025-07-02',
            $dates['bill_date']->toDateString()
        );
        $this->assertNull($dates['loading_date']);
        $this->assertNull($dates['discharge_date']);
    }

    public function test_tfp_missing_dates_are_not_synthesized(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 4) . '/app/Services/Parsers/TfpTextParser.php'
        );

        $this->assertStringContainsString(
            "'bill_date' => null",
            $source
        );
        $this->assertStringContainsString(
            "'loading_date' => null",
            $source
        );
        $this->assertStringContainsString(
            "'departure_date' => null",
            $source
        );
        $this->assertStringContainsString(
            "'estimated_arrival_date' => null",
            $source
        );
    }

    public function test_g2ocean_missing_date_remains_null(): void
    {
        $parser = new G2OceanXmlParser();

        $this->assertNull(
            $this->invoke($parser, 'parseDate', [''])
        );
    }
}
