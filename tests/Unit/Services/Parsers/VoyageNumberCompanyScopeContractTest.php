<?php

namespace Tests\Unit\Services\Parsers;

use Tests\TestCase;

class VoyageNumberCompanyScopeContractTest extends TestCase
{
    public function test_voyage_number_uniqueness_is_scoped_by_company_and_vessel(): void
    {
        $trait = file_get_contents(
            app_path('Services/Parsers/Concerns/EnsuresUniqueVoyageNumber.php')
        );

        $this->assertIsString($trait);
        $this->assertStringContainsString(
            "Voyage::where('company_id', \$companyId)",
            $trait
        );
        $this->assertStringContainsString(
            "->where('lead_vessel_id', \$vesselId)",
            $trait
        );
        $this->assertStringContainsString(
            "->where('voyage_number', \$voyageNumber)",
            $trait
        );
        $this->assertStringContainsString(
            'ya existe para esta embarcación en su empresa',
            $trait
        );

        $migrations = glob(
            database_path(
                'migrations/*scope_voyage_number_by_company_and_vessel.php'
            )
        );

        $this->assertNotEmpty($migrations);

        $migration = file_get_contents($migrations[0]);
        $normalizedMigration = preg_replace('/\s+/', ' ', $migration);

        $this->assertStringContainsString(
            "dropUnique('voyages_voyage_number_unique')",
            $normalizedMigration
        );
        $this->assertStringContainsString(
            "unique( ['company_id', 'lead_vessel_id', 'voyage_number'], 'voyages_voyage_number_unique' )",
            $normalizedMigration
        );

        foreach ([
            app_path('Http/Controllers/Company/VoyageWizardController.php'),
            app_path('Http/Controllers/Company/VoyageController.php'),
        ] as $controllerPath) {
            $controller = file_get_contents($controllerPath);
            $this->assertStringContainsString("lead_vessel_id", $controller);
            $this->assertStringContainsString("voyage_number", $controller);
        }
    }
}
