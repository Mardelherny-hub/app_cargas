<?php

namespace Tests\Unit\Models;

use App\Models\ManifestImport;
use InvalidArgumentException;
use ReflectionMethod;
use Tests\TestCase;

class ManifestImportCreatedObjectsTest extends TestCase
{
    public function test_explicit_tracking_writes_real_database_columns(): void
    {
        $import = new class extends ManifestImport {
            public array $capturedUpdate = [];

            public function update(array $attributes = [], array $options = [])
            {
                $this->capturedUpdate = $attributes;

                return true;
            }
        };

        $import->recordExplicitlyCreatedObjects([
            'voyage' => [3],
            'shipment' => [5],
            'bill' => [10, 11],
            'item' => [20, null, 21, 20],
            'container' => [],
        ]);

        $this->assertSame(
            [3],
            $import->capturedUpdate['created_voyage_ids']
        );
        $this->assertSame(
            1,
            $import->capturedUpdate['created_voyages']
        );

        $this->assertSame(
            [5],
            $import->capturedUpdate['created_shipment_ids']
        );
        $this->assertSame(
            1,
            $import->capturedUpdate['created_shipments']
        );

        $this->assertSame(
            [10, 11],
            $import->capturedUpdate['created_bill_ids']
        );
        $this->assertSame(
            2,
            $import->capturedUpdate['created_bills']
        );

        $this->assertSame(
            [20, 21],
            $import->capturedUpdate['created_item_ids']
        );
        $this->assertSame(
            2,
            $import->capturedUpdate['created_items']
        );

        $this->assertSame(
            [],
            $import->capturedUpdate['created_container_ids']
        );
        $this->assertSame(
            0,
            $import->capturedUpdate['created_containers']
        );
    }

    public function test_distinguishes_legacy_null_from_explicit_empty_tracking(): void
    {
        $method = new ReflectionMethod(
            ManifestImport::class,
            'hasExplicitCreatedObjectTracking'
        );
        $method->setAccessible(true);

        $legacy = new ManifestImport();
        $legacy->setRawAttributes([
            'created_container_ids' => null,
        ]);

        $this->assertFalse(
            $method->invoke(
                $legacy,
                'created_container_ids'
            )
        );

        $explicitEmpty = new ManifestImport();
        $explicitEmpty->setRawAttributes([
            'created_container_ids' => '[]',
        ]);

        $this->assertTrue(
            $method->invoke(
                $explicitEmpty,
                'created_container_ids'
            )
        );

        $explicitIds = new ManifestImport();
        $explicitIds->setRawAttributes([
            'created_container_ids' => '[4,8]',
        ]);

        $this->assertTrue(
            $method->invoke(
                $explicitIds,
                'created_container_ids'
            )
        );
    }

    public function test_explicit_tracking_rejects_non_canonical_or_unknown_types(): void
    {
        $import = new class extends ManifestImport {
            public function update(array $attributes = [], array $options = [])
            {
                return true;
            }
        };

        $this->expectException(InvalidArgumentException::class);

        // Plural deliberadamente inválido en el contrato explícito.
        $import->recordExplicitlyCreatedObjects([
            'containers' => [1],
        ]);
    }

    public function test_legacy_tracking_behavior_is_not_changed(): void
    {
        $import = new class extends ManifestImport {
            public array $capturedUpdate = [];

            public function update(array $attributes = [], array $options = [])
            {
                $this->capturedUpdate = $attributes;

                return true;
            }
        };

        // El contrato histórico ignora arrays vacíos.
        $import->recordCreatedObjects([
            'containers' => [],
        ]);

        $this->assertSame([], $import->capturedUpdate);
    }
}
