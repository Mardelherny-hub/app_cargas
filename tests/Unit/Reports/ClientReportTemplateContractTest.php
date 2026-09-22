<?php

namespace Tests\Unit\Reports;

use PHPUnit\Framework\TestCase;

class ClientReportTemplateContractTest extends TestCase
{
    private function source(string $path): string
    {
        $full = dirname(__DIR__, 3) . '/' . $path;
        $this->assertFileExists($full);

        return file_get_contents($full);
    }

    public function test_existing_reports_are_preserved_and_client_formats_are_parallel_outputs(): void
    {
        $controller = $this->source('app/Http/Controllers/Company/ReportController.php');

        $this->assertStringContainsString("'company.reports.pdf.manifest-client'", $controller);
        $this->assertStringContainsString("'company.reports.pdf.manifest'", $controller);
        $this->assertStringContainsString("'company.reports.pdf.micdta-client'", $controller);
        $this->assertStringContainsString("'company.reports.pdf.micdta'", $controller);
        $this->assertStringContainsString("\$template === 'client' ? 'portrait' : 'landscape'", $controller);
    }

    public function test_nested_filter_payload_used_by_report_forms_is_normalized(): void
    {
        $controller = $this->source('app/Http/Controllers/Company/ReportController.php');

        $this->assertStringContainsString("\$request->input('filters', [])", $controller);
        $this->assertStringContainsString("array_merge(\$legacyFilters, \$nestedFilters)", $controller);
    }

    public function test_manifest_screen_exposes_both_presentations(): void
    {
        $view = $this->source('resources/views/company/reports/manifests.blade.php');

        $this->assertStringContainsString('Reporte del sistema (actual)', $view);
        $this->assertStringContainsString('Cargo Manifest - formato según muestra', $view);
        $this->assertStringContainsString('name="filters[template]"', $view);
    }

    public function test_mic_screen_exposes_current_and_sample_based_pdf(): void
    {
        $view = $this->source('resources/views/company/reports/micdta.blade.php');

        $this->assertStringContainsString('Reporte actual', $view);
        $this->assertStringContainsString('Formato MIC/DTA', $view);
        $this->assertStringContainsString('name="filters[template]" value="standard"', $view);
        $this->assertStringContainsString('name="filters[template]" value="client"', $view);
    }

    public function test_new_templates_keep_the_supplied_document_structure(): void
    {
        $manifest = $this->source('resources/views/company/reports/pdf/manifest-client.blade.php');
        $mic = $this->source('resources/views/company/reports/pdf/micdta-client.blade.php');

        foreach ([
            'Cargo Manifest',
            'Name of Ship',
            'Port of Loading',
            'Port of Discharge',
            'Port of Destination',
            'B/L No.',
            'Gross Wt.',
            'Measur.',
        ] as $label) {
            $this->assertStringContainsString($label, $manifest);
        }

        foreach ([
            'MIC / DTA',
            '1. Nombre y domicilio de la transportadora',
            '3. Nro. MIC',
            '10. Conocimiento',
            '12. Peso bruto Kg.',
            '15. Números de los precintos',
            '22. Transportista responsable del 4to tramo',
        ] as $label) {
            $this->assertStringContainsString($label, $mic);
        }
    }
}
