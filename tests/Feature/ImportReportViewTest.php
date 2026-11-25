<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * Reporte de importación tal como lo ve el usuario: estado en español,
 * advertencias reconocibles, viaje agregado sin decir "creado" y cabecera
 * que se apila en pantallas angostas. Datos ficticios.
 */
class ImportReportViewTest extends TestCase
{
    private function render(array $overrides = []): string
    {
        $data = array_replace_recursive([
            'importResult' => ['success' => true, 'hasWarnings' => false],
            'fileInfo' => ['name' => 'navsur_QA.txt', 'imported_at' => '24/11/2026 10:00:00', 'imported_by' => 'Usuario QA'],
            'stats' => [],
            'voyage' => [
                'id' => 1, 'voyage_number' => 'QA-1', 'status' => 'planning', 'vessel_name' => 'BARCAZA QA',
                'origin_port' => 'Puerto QA 1', 'destination_port' => 'Puerto QA 2', 'departure_date' => null,
            ],
            'createdRecords' => ['billsOfLading' => [], 'containers' => []],
            'counts' => ['billsOfLading' => 3, 'containers' => 2, 'items' => 2],
            'warnings' => [],
            'errors' => [],
            'targetVoyage' => null,
        ], $overrides);

        // Contenido real del reporte, sin el layout global (navegación autenticada).
        $source = file_get_contents(resource_path('views/company/manifests/import-report.blade.php'));
        $source = str_replace(['<x-app-layout>', '</x-app-layout>', '<x-slot name="header">', '</x-slot>'], '', $source);

        return Blade::render($source, $data);
    }

    public function test_voyage_status_is_shown_in_spanish(): void
    {
        $html = $this->render();

        $this->assertStringContainsString('Planificación', $html);
        $this->assertStringNotContainsString('Planning', $html);
        $this->assertStringContainsString('📋 Viaje Creado', $html);
    }

    public function test_import_with_warnings_says_so_in_the_title(): void
    {
        $html = $this->render(['importResult' => ['hasWarnings' => true], 'warnings' => ['Aviso QA']]);

        $this->assertStringContainsString('Importación Completada con Advertencias', $html);
        $this->assertStringNotContainsString('Completada Exitosamente', $html);
    }

    public function test_file_added_to_an_existing_voyage_is_not_reported_as_a_new_voyage(): void
    {
        $html = $this->render(['targetVoyage' => ['skipped_bills' => [], 'shipment_created' => false, 'converted_to_convoy' => false]]);

        $this->assertStringNotContainsString('Viaje Creado', $html);
        $this->assertStringContainsString('📋 Viaje</h5>', $html);
    }

    public function test_header_stacks_on_narrow_screens(): void
    {
        $html = $this->render();

        $this->assertStringContainsString('flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4', $html);
        $this->assertStringContainsString('<strong class="break-all">navsur_QA.txt</strong>', $html);
    }
}
