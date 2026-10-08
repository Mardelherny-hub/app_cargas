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

        $this->assertStringContainsString('>Formato actual</option>', $view);
        $this->assertStringContainsString('>Cargo Manifest</option>', $view);
        $this->assertStringNotContainsString('formato según muestra', $view);
        $this->assertStringContainsString('name="filters[template]"', $view);
    }

    public function test_mic_screen_exposes_current_and_sample_based_pdf(): void
    {
        $view = $this->source('resources/views/company/reports/micdta.blade.php');

        $this->assertStringContainsString('Reporte actual', $view);
        $this->assertStringContainsString('Formato MIC/DTA', $view);
        $this->assertStringContainsString('name="filters[template]" value="standard"', $view);
        $this->assertStringContainsString('name="filters[template]" value="client"', $view);
        $this->assertStringContainsString('Formato MIC/DTA según muestra - Viajes con conocimientos', $view);

        $controller = $this->source('app/Http/Controllers/Company/ReportController.php');
        $this->assertStringContainsString('$printableVoyages = Voyage::with([', $controller);
        $this->assertStringContainsString("->whereHas('billsOfLading')", $controller);
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

    public function test_client_templates_keep_fields_from_roberto_samples(): void
    {
        $manifest = $this->source(
            'resources/views/company/reports/pdf/manifest-client.blade.php'
        );
        $mic = $this->source(
            'resources/views/company/reports/pdf/micdta-client.blade.php'
        );
        $micService = $this->source(
            'app/Services/Reports/MicDtaReportService.php'
        );

        // Cargo Manifest entregado: operador en cabecera y Date of Sailing
        // dentro de la segunda fila, sin una fila extra de matrícula/IMO.
        $this->assertStringContainsString('Date of Sailing', $manifest);
        $this->assertStringNotContainsString(
            'Datos de Registro de la Embarcación',
            $manifest
        );

        // MIC/DTA entregado: Roberto marcó el permiso de embarque en campo 16.
        $this->assertStringContainsString(
            "'export_permit' => \$bill->permiso_embarque ?? ''",
            $micService
        );
        $this->assertStringContainsString(
            "(\$bill['export_permit'] ?? '') ?:",
            $mic
        );
    }

    public function test_manifest_is_ordered_and_grouped_by_ports_before_bill_number(): void
    {
        $service = $this->source('app/Services/Reports/ManifestReportService.php');
        $template = $this->source('resources/views/company/reports/pdf/manifest-client.blade.php');

        $this->assertStringContainsString("'port_groups' =>", $service);
        $this->assertStringContainsString('loadingPort?->code', $service);
        $this->assertStringContainsString('dischargePort?->code', $service);
        $this->assertStringContainsString('bill_number', $service);
        $this->assertStringContainsString('@forelse($port_groups as $group)', $template);
        $this->assertStringContainsString('$group[\'loading_port\']', $template);
        $this->assertStringContainsString('$group[\'discharge_port\']', $template);
    }

    public function test_manifest_repeats_full_document_header_on_physical_continuation_pages(): void
    {
        $manifest = $this->source('resources/views/company/reports/pdf/manifest-client.blade.php');

        $theadPosition = strpos($manifest, '<thead>');
        $titlePosition = strpos($manifest, '<div class="title">Cargo Manifest</div>');
        $shipPosition = strpos($manifest, 'Name of Ship');
        $tbodyPosition = strpos($manifest, '<tbody>');

        $this->assertNotFalse($theadPosition);
        $this->assertNotFalse($titlePosition);
        $this->assertNotFalse($shipPosition);
        $this->assertNotFalse($tbodyPosition);
        $this->assertGreaterThan($theadPosition, $titlePosition);
        $this->assertGreaterThan($theadPosition, $shipPosition);
        $this->assertLessThan($tbodyPosition, $titlePosition);
        $this->assertLessThan($tbodyPosition, $shipPosition);
        $this->assertStringContainsString('display: table-header-group', $manifest);
        $this->assertStringContainsString('page-number-placeholder', $manifest);
        $this->assertStringNotContainsString('{{ $loop->iteration }}', $manifest);

        $controller = $this->source('app/Http/Controllers/Company/ReportController.php');
        $this->assertStringContainsString("if (\$template === 'client')", $controller);
        $this->assertStringContainsString("'{PAGE_NUM}'", $controller);
        $this->assertStringContainsString('->getCanvas()->page_text(', $controller);
    }

    public function test_mic_client_template_aligns_items_with_containers_and_keeps_footer_on_each_page(): void
    {
        $service = $this->source('app/Services/Reports/MicDtaReportService.php');
        $template = $this->source('resources/views/company/reports/pdf/micdta-client.blade.php');

        $this->assertStringContainsString("'container_count' =>", $service);
        $this->assertStringContainsString("\$item['containers']", $template);
        $this->assertStringContainsString("\$row['gross_weight_kg']", $template);
        $this->assertStringContainsString("\$row['quantity'] }} BULTOS", $template);
        $this->assertStringContainsString("\$row['description']", $template);
        $this->assertStringContainsString("\$row['container_text']", $template);
        $this->assertStringContainsString("'CONTENEDOR' : 'CONTENEDORES'", $template);
        $this->assertStringNotContainsString('PRECINTO:', $template);
        $this->assertStringContainsString('$showVesselRegistration', $template);

        $this->assertStringContainsString('$documentPages', $template);
        $this->assertStringContainsString('$pageLineBudget = 18;', $template);
        $this->assertStringContainsString(
            '$pageCount = max(1, (int) ceil($lineCount / $pageLineBudget));',
            $template
        );
        $this->assertStringContainsString(
            "'rows' => collect([\$row])",
            $template
        );
        $this->assertStringContainsString(
            "->flatMap(fn (\$item) => \$item['containers'] ?? [])",
            $template
        );
        $this->assertStringContainsString('Page: &nbsp; {{ $pageIndex + 1 }}', $template);
        $this->assertStringContainsString('.page-footer', $template);
        $this->assertStringContainsString('position: absolute;', $template);
        $this->assertStringContainsString('bottom: 0;', $template);

        $field15 = strstr($template, '15. Números de los precintos');
        $this->assertIsString($field15);
        $field16 = strstr($field15, '16. Observaciones de la Aduana');
        $this->assertIsString($field16);
        $field15Only = substr($field15, 0, strlen($field15) - strlen($field16));
        $this->assertStringNotContainsString('$container[\'seals\']', $field15Only);
    }

}
