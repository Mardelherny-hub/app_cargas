<?php

namespace Tests\Unit\Services\Parsers;

use PHPUnit\Framework\TestCase;

class NavsurPortCatalogIntegrityContractTest extends TestCase
{
    public function test_pycap_is_versioned_as_carmelo_peralta_in_paraguay(): void
    {
        $migration = file_get_contents(
            dirname(__DIR__, 4)
            . '/database/migrations/2026_09_23_230000_add_pycap_carmelo_peralta_port.php'
        );

        $this->assertStringContainsString("'PYCAP'", $migration);
        $this->assertStringContainsString('Capitán Carmelo Peralta', $migration);
        $this->assertStringContainsString("->where('alpha2_code', 'PY')", $migration);
        $this->assertStringContainsString("'port_type' => 'river'", $migration);
        $this->assertStringContainsString("'has_customs_office' => true", $migration);
    }

    public function test_pycap_is_not_silently_aliased_to_another_port(): void
    {
        $resolver = file_get_contents(
            dirname(__DIR__, 4)
            . '/app/Services/Parsers/Concerns/ResolvesPorts.php'
        );

        $this->assertStringNotContainsString("'PYCAP' =>", $resolver);
    }
}
