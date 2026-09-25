<?php

namespace Tests\Unit\Http\Controllers\Company;

use Tests\TestCase;

class ShipmentItemSealValidationContractTest extends TestCase
{
    public function test_existing_real_guaran_seals_fit_edit_validation(): void
    {
        foreach ([
            app_path('Http/Controllers/Company/ShipmentItemController.php'),
            app_path('Http/Controllers/Company/ShipmentItemControllerCompat.php'),
        ] as $path) {
            $source = file_get_contents($path);

            $this->assertStringContainsString(
                "'containers.*.seal_number' => 'nullable|string|max:255'",
                $source
            );
            $this->assertStringNotContainsString(
                "'containers.*.seal_number' => 'nullable|string|max:50'",
                $source
            );
        }
    }
}
