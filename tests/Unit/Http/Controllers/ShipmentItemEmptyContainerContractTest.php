<?php

namespace Tests\Unit\Http\Controllers;

use PHPUnit\Framework\TestCase;

class ShipmentItemEmptyContainerContractTest extends TestCase
{
    private function source(string $path): string
    {
        return file_get_contents(dirname(__DIR__, 4) . '/' . $path);
    }

    private function inputById(string $source, string $id): string
    {
        preg_match(
            '/<input\b(?=[^>]*id="' . preg_quote($id, '/') . '")[^>]*>/s',
            $source,
            $matches
        );

        $this->assertNotEmpty($matches, "No existe input #{$id}");

        return $matches[0];
    }

    public function test_empty_container_backend_contract(): void
    {
        $source = $this->source(
            'app/Http/Controllers/Company/ShipmentItemController.php'
        );

        $this->assertStringContainsString(
            "'containers.*.condition' => 'nullable|in:L,V'",
            $source
        );

        $this->assertStringContainsString(
            "'containers.*.package_quantity' => 'required_with:containers|integer|min:0'",
            $source
        );

        $this->assertStringContainsString(
            "'containers.*.gross_weight_kg' => 'required_with:containers|numeric|min:0'",
            $source
        );

        $this->assertStringContainsString(
            "if (\$condition === 'V')",
            $source
        );

        $this->assertStringContainsString(
            "\$condition === 'V' ? 'empty' : 'loaded'",
            $source
        );

        $this->assertStringContainsString(
            "\$condition === 'V' ? \$container->current_gross_weight_kg",
            $source
        );

        $this->assertStringContainsString(
            "\$condition === 'V' ? null : \$containerData['gross_weight_kg']",
            $source
        );
    }

    public function test_loaded_container_still_requires_positive_cargo(): void
    {
        $source = $this->source(
            'app/Http/Controllers/Company/ShipmentItemController.php'
        );

        $this->assertStringContainsString(
            'if ($packages < 1 || $grossWeight <= 0)',
            $source
        );
    }

    public function test_browser_accepts_zero_item_totals(): void
    {
        $source = $this->source(
            'resources/views/company/shipment-items/edit.blade.php'
        );

        foreach (['package_quantity', 'gross_weight_kg'] as $id) {
            $tag = $this->inputById($source, $id);

            $this->assertStringContainsString(
                'min="0"',
                $tag,
                "#{$id} debe aceptar cero"
            );
        }

        $this->assertStringContainsString(
            'if (packageQuantity < 0)',
            $source
        );
    }

    public function test_zero_from_existing_container_is_not_changed_to_blank(): void
    {
        $source = $this->source(
            'resources/views/company/shipment-items/edit.blade.php'
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
