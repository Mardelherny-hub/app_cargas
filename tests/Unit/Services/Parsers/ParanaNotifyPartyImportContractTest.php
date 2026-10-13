<?php

namespace Tests\Unit\Services\Parsers;

use App\Services\Parsers\ParanaExcelParser;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use ReflectionMethod;
use Tests\TestCase;

class ParanaNotifyPartyImportContractTest extends TestCase
{
    public function test_parana_notify_columns_are_read_without_losing_the_declared_data(): void
    {
        $sheet = (new Spreadsheet())->getActiveSheet();
        $sheet->setCellValue('O3', 'BL-QA-001');
        $sheet->setCellValue('AN3', 'MSC ARGENTINA');
        $sheet->setCellValue('AO3', 'CUIT 30-69318494-7 - TAX ID: 30693184947');
        $sheet->setCellValue('AT3', 'ARGENTINA');

        $parser = new ParanaExcelParser();
        $data = (new ReflectionMethod($parser, 'extractRowData'))->invoke($parser, $sheet, 3);

        $this->assertSame('BL-QA-001', $data['BL_NUMBER']);
        $this->assertSame('MSC ARGENTINA', $data['NOTIFY_PARTY_NAME']);
        $this->assertSame('CUIT 30-69318494-7 - TAX ID: 30693184947', $data['NOTIFY_PARTY_ADDRESS1']);
        $this->assertSame('ARGENTINA', $data['NOTIFY_PARTY_COUNTRY']);
    }

    public function test_parana_bill_creation_links_notify_party_and_preserves_specific_address(): void
    {
        $source = file_get_contents(app_path('Services/Parsers/ParanaExcelParser.php'));
        $this->assertIsString($source);

        $start = strpos($source, 'protected function createBillOfLading(');
        $end = strpos($source, 'protected function nullableSourceText(', $start);
        $this->assertNotFalse($start);
        $this->assertNotFalse($end);
        $method = substr($source, $start, $end - $start);

        $this->assertStringContainsString("\$data['NOTIFY_PARTY_NAME']", $method);
        $this->assertStringContainsString("\$data['NOTIFY_PARTY_COUNTRY']", $method);
        $this->assertStringContainsString("'notify_party_id' => \$notifyParty?->id", $method);
        $this->assertStringContainsString("\$this->persistClientAddress(\$notifyParty, \$notifyAddr)", $method);
        $this->assertStringContainsString("\$this->resolveSpecificAddress(\$notifyParty, \$notifyAddr, 'notify_party')", $method);
        $this->assertStringContainsString('if ($notifyParty)', $method);
    }
}
