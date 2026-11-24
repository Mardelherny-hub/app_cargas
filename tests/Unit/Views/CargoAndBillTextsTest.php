<?php

namespace Tests\Unit\Views;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * Textos de conocimientos y cargas en español y con el número correcto.
 */
class CargoAndBillTextsTest extends TestCase
{
    private function line(string $view, string $needle): string
    {
        foreach (file(resource_path("views/company/{$view}.blade.php")) as $line) {
            if (str_contains($line, $needle)) {
                return $line;
            }
        }
        $this->fail("No se encontró {$needle} en {$view}");
    }

    public function test_bill_list_counts_use_singular_for_one(): void
    {
        $line = $this->line('bills-of-lading/index', '$billsOfLading->total() }}');
        $render = fn (int $bills, int $voyages) => trim(Blade::render($line, [
            'billsOfLading' => new LengthAwarePaginator([], $bills, 25),
            'voyageGroups' => collect(array_fill(0, $voyages, 'v')),
        ]));

        $this->assertSame('48 conocimientos en 1 viaje', $render(48, 1));
        $this->assertSame('1 conocimiento en 2 viajes', $render(1, 2));
    }

    public function test_cargo_page_says_carga_and_not_shipment(): void
    {
        foreach (['shipments/show', 'shipments/edit'] as $view) {
            $source = file_get_contents(resource_path("views/company/{$view}.blade.php"));
            $this->assertStringNotContainsString('Este shipment', $source, $view);
        }
        $line = $this->line('shipments/show', 'Esta carga tiene');
        $this->assertSame(
            'Esta carga tiene <strong>116 ítems</strong> de carga registrados.',
            trim(Blade::render($line, ['stats' => ['total_items' => 116]]))
        );
    }
}
