<?php

namespace Tests\Unit\Http\Controllers;

use PHPUnit\Framework\TestCase;

class ShipmentItemEmptyContainerContractTest extends TestCase
{
    private function source(string $path): string
    {
        return file_get_contents(
            dirname(__DIR__, 4) . '/' . $path
        );
    }

    public function test_backend_accepts_and_preserves_imported_container_data(): void
    {
        $source = $this->source(
            'app/Http/Controllers/Company/'
            . 'ShipmentItemControllerCompat.php'
        );

        // La distribución física por contenedor pertenece al contrato
        // canónico del pivot y no depende del formato que originó el dato.
        $this->assertStringContainsString(
            '$allowsUnknownContainerDistribution = true;',
            $source
        );

        $this->assertStringContainsString(
            '$allowsUnknownContainerPackages = true;',
            $source
        );

        $this->assertStringContainsString(
            "'containers.*.condition' => 'nullable|in:L,V'",
            $source
        );

        // CMSP/Login pueden no declarar la distribución física de bultos
        // o peso de carga por contenedor. NULL significa desconocido.
        $this->assertStringContainsString(
            "'nullable|integer|min:0'",
            $source
        );

        $this->assertStringContainsString(
            "'nullable|numeric|min:0'",
            $source
        );

        $this->assertStringContainsString(
            "\$containerData['package_quantity'] ?? null",
            $source
        );

        $this->assertStringContainsString(
            "\$containerData['gross_weight_kg'] ?? null",
            $source
        );

        // Un ítem con todos sus contenedores vacíos se reconoce por
        // el estado real V, sin depender de source_format/manifest_format.
        $this->assertStringContainsString(
            '$isImportedEmptyItem = $allContainersEmpty;',
            $source
        );

        $this->assertStringNotContainsString(
            '$allContainersEmpty'
            . PHP_EOL
            . '            && ($isCmspItem || $isLoginItem)',
            $source
        );

        $this->assertStringContainsString(
            '$allContainersEmpty || $isLoginItem || $isCmspItem',
            $source
        );

        // El tratamiento histórico de contenedor vacío del Compat se conserva.
        $this->assertStringContainsString(
            "if (\$condition === 'V')",
            $source
        );

        $this->assertStringContainsString(
            '!in_array($packages, [0, 1], true)',
            $source
        );

        $this->assertStringContainsString(
            "\$condition === 'V' ? 'empty' : 'loaded'",
            $source
        );

        // El VGM importado debe sobrevivir una edición.
        $this->assertStringContainsString(
            "'verified_gross_mass_kg' => \$existing->pivot->verified_gross_mass_kg",
            $source
        );

        $this->assertStringContainsString(
            "'verified_gross_mass_kg' => \$preserved['verified_gross_mass_kg'] ?? null",
            $source
        );
    }

    public function test_view_allows_zero_values(): void
    {
        $source = $this->source(
            'resources/views/company/'
            . 'shipment-items/edit.blade.php'
        );

        $this->assertSame(
            0,
            substr_count(
                $source,
                'min="0.01"'
            )
        );

        $this->assertStringContainsString(
            'if (packageQuantity < 0)',
            $source
        );

        $this->assertStringNotContainsString(
            'packageQuantity < minimumPackageQuantity',
            $source
        );
    }

    public function test_container_list_is_collapsible_and_can_save_in_place(): void
    {
        $source = $this->source(
            'resources/views/company/'
            . 'shipment-items/edit.blade.php'
        );

        $this->assertStringContainsString(
            '<details class="container-item',
            $source
        );

        $this->assertStringContainsString(
            'class="container-title',
            $source
        );

        $this->assertStringContainsString(
            'Guardar cambios',
            $source
        );

        $this->assertStringContainsString(
            'refreshContainerTitles()',
            $source
        );

        $this->assertStringContainsString(
            "closest('details.container-item')",
            $source
        );
    }

    public function test_existing_zero_values_are_not_blankified(): void
    {
        $source = $this->source(
            'resources/views/company/'
            . 'shipment-items/edit.blade.php'
        );

        $this->assertStringContainsString(
            "containerData.package_quantity ?? ''",
            $source
        );

        $this->assertStringContainsString(
            "containerData.gross_weight_kg ?? ''",
            $source
        );

        $this->assertStringNotContainsString(
            "containerData.package_quantity || ''",
            $source
        );

        $this->assertStringNotContainsString(
            "containerData.gross_weight_kg || ''",
            $source
        );
    }
}
