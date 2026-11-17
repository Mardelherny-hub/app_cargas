<?php

namespace Tests\Unit\Http;

use App\Http\Controllers\Company\Manifests\ManifestImportController;
use App\Models\ManifestImport;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Informe final de una importación agregada a un viaje existente. Sin base
 * de datos: la importación se arma en memoria.
 */
class ManifestImportRepeatedBillsReportTest extends TestCase
{
    private function report(?array $parserConfig): ?array
    {
        $import = new ManifestImport();
        $import->parser_config = $parserConfig;

        return (new ReflectionMethod(ManifestImportController::class, 'targetVoyageReport'))
            ->invoke(new ManifestImportController(), $import);
    }

    public function test_import_that_created_its_voyage_has_no_target_report(): void
    {
        $this->assertNull($this->report(null));
        $this->assertNull($this->report(['source' => 'navsur']));
    }

    public function test_report_lists_skipped_repeated_bills_and_convoy_conversion(): void
    {
        $report = $this->report([
            'target_voyage' => ['voyage_id' => 10, 'shipment_created' => true, 'skipped_bills' => ['BL-1', 'BL-2']],
            'convoy_conversion' => ['voyage_id' => 10],
        ]);

        $this->assertSame(
            ['skipped_bills' => ['BL-1', 'BL-2'], 'shipment_created' => true, 'converted_to_convoy' => true],
            $report
        );

        $html = view('company.manifests.import-target-voyage-report', ['targetVoyage' => $report])->render();
        $this->assertStringContainsString('Conocimientos salteados porque ya existían en el viaje (2)', $html);
        $this->assertStringContainsString('<li>BL-1</li>', $html);
        $this->assertStringContainsString('<li>BL-2</li>', $html);
        $this->assertStringContainsString('se convirtió en convoy', $html);
    }

    public function test_report_without_repeated_bills_says_so(): void
    {
        $report = $this->report(['target_voyage' => ['voyage_id' => 10, 'shipment_created' => false, 'skipped_bills' => []]]);

        $html = view('company.manifests.import-target-voyage-report', ['targetVoyage' => $report])->render();
        $this->assertStringContainsString('No se salteó ningún conocimiento por repetido.', $html);
        $this->assertStringContainsString('carga existente de la embarcación', $html);
        $this->assertStringNotContainsString('convoy', $html);
    }

    public function test_bill_numbers_are_escaped(): void
    {
        $report = $this->report(['target_voyage' => ['skipped_bills' => ['<b>BL</b>']]]);

        $html = view('company.manifests.import-target-voyage-report', ['targetVoyage' => $report])->render();
        $this->assertStringContainsString('&lt;b&gt;BL&lt;/b&gt;', $html);
    }
}
