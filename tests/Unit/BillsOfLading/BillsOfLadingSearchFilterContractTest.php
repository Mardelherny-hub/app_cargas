<?php

namespace Tests\Unit\BillsOfLading;

use PHPUnit\Framework\TestCase;

class BillsOfLadingSearchFilterContractTest extends TestCase
{
    private function source(string $path): string
    {
        $full = dirname(__DIR__, 3) . '/' . $path;
        $this->assertFileExists($full);

        return file_get_contents($full);
    }

    public function test_main_bl_search_exposes_requested_voyage_and_port_filters(): void
    {
        $view = $this->source('resources/views/company/bills-of-lading/index.blade.php');

        foreach ([
            'name="voyage_id"',
            'name="loading_port_id"',
            'name="discharge_port_id"',
            'name="final_destination_port_id"',
            'Buscar conocimientos',
        ] as $needle) {
            $this->assertStringContainsString($needle, $view);
        }
    }

    public function test_main_bl_query_applies_all_requested_filters(): void
    {
        $controller = $this->source('app/Http/Controllers/Company/BillOfLadingController.php');

        $this->assertStringContainsString("filled('voyage_id')", $controller);
        $this->assertStringContainsString("where('voyage_id', \$voyageId)", $controller);
        $this->assertStringContainsString("filled('loading_port_id')", $controller);
        $this->assertStringContainsString("filled('discharge_port_id')", $controller);
        $this->assertStringContainsString("filled('final_destination_port_id')", $controller);
        $this->assertStringContainsString("'destinationPorts' => Port::where('active', true)", $controller);
    }

    public function test_report_export_exposes_and_applies_same_four_filters(): void
    {
        $view = $this->source('resources/views/company/reports/bills-of-lading.blade.php');
        $service = $this->source('app/Services/Reports/BillsOfLadingReportService.php');

        foreach ([
            'name="filters[voyage_id]"',
            'name="filters[loading_port_id]"',
            'name="filters[discharge_port_id]"',
            'name="filters[final_destination_port_id]"',
        ] as $needle) {
            $this->assertStringContainsString($needle, $view);
        }

        $this->assertStringContainsString("\$this->filters['voyage_id']", $service);
        $this->assertStringContainsString("\$this->filters['loading_port_id']", $service);
        $this->assertStringContainsString("\$this->filters['discharge_port_id']", $service);
        $this->assertStringContainsString("\$this->filters['final_destination_port_id']", $service);
    }

    public function test_destination_is_kept_distinct_from_discharge(): void
    {
        $controller = $this->source('app/Http/Controllers/Company/BillOfLadingController.php');
        $service = $this->source('app/Services/Reports/BillsOfLadingReportService.php');

        $this->assertStringContainsString("'finalDestinationPort:id,name,country_id'", $controller);
        $this->assertStringContainsString("'finalDestinationPort.country'", $service);
        $this->assertStringContainsString("'final_destination_port' => \$bill->finalDestinationPort->name", $service);
    }
}
