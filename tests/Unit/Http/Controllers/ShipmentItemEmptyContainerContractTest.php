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

    public function test_backend_accepts_and_preserves_empty_container(): void
    {
        $source = $this->source(
            'app/Http/Controllers/Company/'
            . 'ShipmentItemController.php'
        );

        $this->assertStringContainsString(
            "'containers.*.condition' => 'nullable|in:L,V'",
            $source
        );

        $this->assertStringContainsString(
            'required_with:containers|integer|min:0',
            $source
        );

        $this->assertStringContainsString(
            'required_with:containers|numeric|min:0',
            $source
        );

        $this->assertStringContainsString(
            "if (\$condition === 'V')",
            $source
        );

        $this->assertStringContainsString(
            "\$packages !== 0",
            $source
        );

        $this->assertStringContainsString(
            'abs($grossWeight) > 0.00001',
            $source
        );

        $this->assertStringContainsString(
            "\$condition === 'V' ? 'empty' : 'loaded'",
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
