<?php

namespace Tests\Unit\Http\Controllers;

use PHPUnit\Framework\TestCase;

class ManifestImportOperationTypeContractTest extends TestCase
{
    public function test_import_form_exposes_explicit_cuscar_operation_type(): void
    {
        $root = dirname(__DIR__, 4);

        $view = file_get_contents(
            $root . '/resources/views/company/manifests/import.blade.php'
        );

        $this->assertStringContainsString(
            'name="operation_type"',
            $view
        );
        $this->assertStringContainsString(
            'value="import"',
            $view
        );
        $this->assertStringContainsString(
            'value="export"',
            $view
        );
        $this->assertStringContainsString(
            'requerido para CUSCAR',
            $view
        );
    }

    public function test_controller_rejects_inverted_manual_dates_before_queue(): void
    {
        $root = dirname(__DIR__, 4);

        $controller = file_get_contents(
            $root . '/app/Http/Controllers/Company/Manifests/ManifestImportController.php'
        );

        $this->assertStringContainsString(
            "filled('departure_date')",
            $controller
        );
        $this->assertStringContainsString(
            "filled('discharge_date')",
            $controller
        );
        $this->assertStringContainsString(
            'La fecha de descarga no puede ser anterior a la fecha de salida.',
            $controller
        );
    }

    public function test_controller_validates_and_forwards_operation_type(): void
    {
        $root = dirname(__DIR__, 4);

        $controller = file_get_contents(
            $root . '/app/Http/Controllers/Company/Manifests/ManifestImportController.php'
        );

        $this->assertStringContainsString(
            "'operation_type' => 'nullable|in:import,export'",
            $controller
        );

        $this->assertStringContainsString(
            "\$request->input('operation_type')",
            $controller
        );

        $this->assertStringContainsString(
            'CmspEdiParserCompat',
            $controller
        );

        $this->assertStringContainsString(
            'Para archivos CUSCAR debe seleccionar',
            $controller
        );
    }
}
