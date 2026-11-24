<?php

namespace Tests\Unit\Views;

use Illuminate\Support\Facades\Blade;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Cuando no se pueden eliminar conocimientos (por ejemplo, embarcación con
 * MIC/DTA vigente en AFIP), la pantalla dice por qué en lugar de ocultar el
 * botón sin explicación. Datos ficticios.
 */
class BillDeletionBlockedReasonTest extends TestCase
{
    private const REASON = 'La embarcación está bloqueada por AFIP. MIC/DTA QA-1 registrado en AFIP sin anulación aprobada.';

    /** Bloque de la vista entre dos marcas, para renderizarlo sin el layout ni el resto de la pantalla. */
    private function fragment(string $view, string $from, string $to): string
    {
        $source = file_get_contents(resource_path("views/company/bills-of-lading/{$view}.blade.php"));
        $start = strpos($source, $from);
        $end = strpos($source, $to, $start);
        $this->assertNotFalse($start, $from);
        $this->assertNotFalse($end, $to);

        return substr($source, $start, $end - $start);
    }

    public function test_vessel_list_explains_why_bills_cannot_be_deleted(): void
    {
        $html = Blade::render(
            $this->fragment('index', '@if(!empty($bulkDeleteBlocked))', '{{-- FILTROS DE CONOCIMIENTOS --}}'),
            ['bulkDeleteBlocked' => ['vessel' => 'BARCAZA QA', 'reason' => self::REASON]]
        );

        $this->assertStringContainsString('No se pueden eliminar los conocimientos de BARCAZA QA.', $html);
        $this->assertStringContainsString(self::REASON, $html);
    }

    public function test_bill_page_explains_why_it_cannot_be_deleted(): void
    {
        $html = Blade::render(
            '@if(true) @if(false) @' . $this->fragment('show', "elseif(!empty(\$permissions['deleteBlockedReason']))", '</x-slot>'),
            ['permissions' => ['canDelete' => false, 'deleteBlockedReason' => self::REASON]]
        );

        $this->assertStringContainsString('No se puede eliminar: ' . self::REASON, $html);
    }

    public function test_controller_passes_the_reasons_to_both_screens(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/Company/BillOfLadingController.php'));

        $this->assertStringContainsString("'deleteBlockedReason' => \$deletionReason,", $controller);
        $this->assertStringContainsString("'bulkDeleteBlocked',", $controller);
    }
}
