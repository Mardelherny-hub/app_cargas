<?php

namespace Tests\Unit\Reports;

use PHPUnit\Framework\TestCase;

class BillOfLadingPdfPaginationContractTest extends TestCase
{
    private function source(string $path): string
    {
        $full = dirname(__DIR__, 3) . '/' . $path;
        $this->assertFileExists($full);

        return file_get_contents($full);
    }

    public function test_bill_pdf_repeats_header_footer_and_numbers_physical_pages(): void
    {
        $template = $this->source('resources/views/company/bills-of-lading/pdf.blade.php');
        $controller = $this->source('app/Http/Controllers/Company/BillOfLadingController.php');

        $this->assertStringContainsString('position: fixed;', $template);
        $this->assertStringContainsString('top: -34mm;', $template);
        $this->assertStringContainsString('bottom: -24mm;', $template);
        $this->assertStringContainsString('display: table-header-group;', $template);

        $this->assertStringContainsString("'Página {PAGE_NUM} de {PAGE_COUNT}'", $controller);
        $this->assertStringContainsString('->getCanvas()->page_text(', $controller);
    }

    public function test_bill_pdf_uses_only_items_from_the_current_bill(): void
    {
        $template = $this->source('resources/views/company/bills-of-lading/pdf.blade.php');
        $controller = $this->source('app/Http/Controllers/Company/BillOfLadingController.php');

        $this->assertStringContainsString('$billOfLading->shipmentItems->count()', $template);
        $this->assertStringContainsString('@foreach($billOfLading->shipmentItems as $item)', $template);
        $this->assertStringNotContainsString('$billOfLading->shipment->shipmentItems', $template);

        $pdfMethod = strstr($controller, 'public function generatePdf(BillOfLading $billOfLading)');
        $this->assertIsString($pdfMethod);
        $printMethod = strstr($pdfMethod, 'public function print(BillOfLading $billOfLading)');
        $this->assertIsString($printMethod);
        $pdfMethodOnly = substr($pdfMethod, 0, strlen($pdfMethod) - strlen($printMethod));

        $this->assertStringContainsString("'shipmentItems.cargoType'", $pdfMethodOnly);
        $this->assertStringContainsString("'shipmentItems.packagingType'", $pdfMethodOnly);
        $this->assertStringNotContainsString("'shipment.shipmentItems.cargoType'", $pdfMethodOnly);
    }

    public function test_long_cargo_sections_are_allowed_to_continue_on_following_pages(): void
    {
        $template = $this->source('resources/views/company/bills-of-lading/pdf.blade.php');

        $this->assertStringContainsString('.cargo-info-section,', $template);
        $this->assertStringContainsString('.cargo-detail-section', $template);
        $this->assertStringContainsString('page-break-inside: auto;', $template);
        $this->assertStringContainsString('class="section cargo-info-section"', $template);
        $this->assertStringContainsString('class="section cargo-detail-section"', $template);
    }
}
