<?php

namespace Tests\Unit\Models;

use App\Http\Controllers\Company\ShipmentItemController;
use App\Models\ShipmentItem;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class ShipmentItemHeaderIntegrityTest extends TestCase
{
    public function test_non_quantitative_update_does_not_trigger_full_bill_recalculation(): void
    {
        $file = (
            new ReflectionClass(
                ShipmentItem::class
            )
        )->getFileName();

        $source = file_get_contents($file);

        $savedStart = strpos(
            $source,
            'static::saved(function ($shipmentItem) {'
        );

        $deletedStart = strpos(
            $source,
            'static::deleted(function ($shipmentItem) {',
            $savedStart
        );

        $this->assertNotFalse($savedStart);
        $this->assertNotFalse($deletedStart);

        $saved = substr(
            $source,
            $savedStart,
            $deletedStart - $savedStart
        );

        $createdGuard = strpos(
            $saved,
            'if ($shipmentItem->wasRecentlyCreated)'
        );

        $fullRecalc = strpos(
            $saved,
            '$bill->recalculateItemStats();'
        );

        $updates = strpos(
            $saved,
            '$updates = [];'
        );

        $this->assertNotFalse($createdGuard);
        $this->assertNotFalse($fullRecalc);
        $this->assertNotFalse($updates);

        /*
         * El recálculo completo pertenece únicamente al camino CREATE.
         */
        $this->assertLessThan(
            $fullRecalc,
            $createdGuard
        );

        $this->assertLessThan(
            $updates,
            $fullRecalc
        );

        /*
         * Después de entrar al camino UPDATE no puede existir otro
         * recalculateItemStats() completo del Bill of Lading.
         */
        $updatePath = substr(
            $saved,
            $updates
        );

        $this->assertStringNotContainsString(
            '$bill->recalculateItemStats();',
            $updatePath
        );

        foreach ([
            'package_quantity',
            'gross_weight_kg',
            'net_weight_kg',
            'volume_m3',
        ] as $field) {
            $this->assertStringContainsString(
                "wasChanged('{$field}')",
                $updatePath
            );
        }

        $this->assertStringContainsString(
            'if ($updates === [])',
            $updatePath
        );

        $this->assertStringContainsString(
            '$bill->updateQuietly($updates);',
            $updatePath
        );
    }

    public function test_update_contract_accepts_login_persisted_values_without_fabricating_null_measurements(): void
    {
        $file = (
            new ReflectionClass(
                ShipmentItemController::class
            )
        )->getFileName();

        $source = file_get_contents($file);

        $start = strpos(
            $source,
            'public function update(Request $request, ShipmentItem $shipmentItem)'
        );

        $end = strpos(
            $source,
            'private function updateItemContainers(',
            $start
        );

        $this->assertNotFalse($start);
        $this->assertNotFalse($end);

        $update = substr(
            $source,
            $start,
            $end - $start
        );

        $this->assertStringContainsString(
            "'line_number' => 'required|integer|min:0'",
            $update
        );

        $this->assertStringContainsString(
            "'gross_weight_kg' => 'required|numeric|min:0'",
            $update
        );

        $this->assertStringContainsString(
            "'tariff_position' => 'nullable|string|max:16'",
            $update
        );

        $this->assertStringContainsString(
            "'containers.*.gross_weight_kg' => 'required_with:containers|numeric|min:0'",
            $update
        );

        $this->assertStringContainsString(
            "'net_weight_kg' => \$validated['net_weight_kg'] ?? null",
            $update
        );

        $this->assertStringContainsString(
            "'volume_m3' => \$validated['volume_m3'] ?? null",
            $update
        );
    }
}
