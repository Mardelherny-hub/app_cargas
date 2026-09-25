<?php

namespace Tests\Unit\Services\Parsers;

use Tests\TestCase;

class VoyageNumberCompanyScopeContractTest extends TestCase
{
    public function test_voyage_number_uniqueness_is_scoped_by_company(): void
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
            "->where('voyage_number', \$voyageNumber)",
            $trait
        );
        $this->assertStringContainsString(
            'ya existe en su empresa',
            $trait
        );

        $migrations = glob(
            database_path('migrations/*scope_voyage_number_by_company.php')
        );

        $this->assertNotEmpty($migrations);

        $migration = file_get_contents($migrations[0]);
        $normalizedMigration = preg_replace('/\\s+/', ' ', $migration);

        $this->assertStringContainsString(
            "dropUnique('voyages_voyage_number_unique')",
            $normalizedMigration
        );
        $this->assertStringContainsString(
            "unique( ['company_id', 'voyage_number'], 'voyages_voyage_number_unique' )",
            $normalizedMigration
        );
    }
}
