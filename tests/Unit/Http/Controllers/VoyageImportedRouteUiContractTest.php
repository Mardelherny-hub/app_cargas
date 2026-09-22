<?php

namespace Tests\Unit\Http\Controllers;

use PHPUnit\Framework\TestCase;

class VoyageImportedRouteUiContractTest extends TestCase
{
    public function test_edit_form_keeps_imported_route_available_outside_manual_country_whitelist(): void
    {
        $root = dirname(__DIR__, 4);

        $controller = file_get_contents(
            $root . '/app/Http/Controllers/Company/VoyageController.php'
        );

        $view = file_get_contents(
            $root . '/resources/views/company/voyages/edit.blade.php'
        );

        $this->assertStringContainsString(
            '$formData = $this->getFormData($voyage);',
            $controller
        );

        foreach ([
            '$voyage?->origin_country_id',
            '$voyage?->destination_country_id',
            '$voyage?->origin_port_id',
            '$voyage?->destination_port_id',
            "orWhereIn('id', \$currentCountryIds)",
            "orWhereIn('id', \$currentPortIds)",
        ] as $token) {
            $this->assertStringContainsString($token, $controller);
        }

        $this->assertStringContainsString(
            "old('origin_country_id', \$voyage->origin_country_id)",
            $view
        );

        $this->assertStringContainsString(
            "old('origin_port_id', \$voyage->origin_port_id)",
            $view
        );
    }
}
