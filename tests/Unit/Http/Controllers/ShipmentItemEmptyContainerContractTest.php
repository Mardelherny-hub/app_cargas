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

        // El runtime real usa el controller Compat. La fuente se reconoce
        // primero por el BL y, como respaldo, por manifest_format del viaje.
        $this->assertStringContainsString(
            'optional($shipmentItem->shipment->voyage)->manifest_format',
            $source
        );

        $this->assertStringContainsString(
            "['LOGIN_XML', 'CMSP_EDI_CUSCAR']",
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
