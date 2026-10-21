<?php

namespace Tests\Feature;

use App\Models\ShipmentItem;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class ArgentinaDeconsolidatedItemIndicatorRetentionTest extends TestCase
{
    private const FIELDS = [
        'is_secure_logistics_operator',
        'is_monitored_transit',
        'is_renar',
    ];

    public function test_edit_form_restores_each_saved_yes_and_no_indicator(): void
    {
        foreach (self::FIELDS as $field) {
            foreach (['S', 'N'] as $value) {
                $item = new ShipmentItem();
                $item->{$field} = $value;

                $html = $this->renderIndicator($item, $field);

                $this->assertMatchesRegularExpression(
                    '/<option value="' . $value . '"\s+selected(?:\s|>)/',
                    $html,
                    "La edición debe recuperar {$field}={$value} desde el registro."
                );

                $other = $value === 'S' ? 'N' : 'S';
                $this->assertDoesNotMatchRegularExpression(
                    '/<option value="' . $other . '"\s+selected(?:\s|>)/',
                    $html
                );
            }
        }
    }

    public function test_validation_old_input_takes_priority_over_saved_indicator(): void
    {
        $item = new ShipmentItem();
        $item->is_renar = 'N';

        $this->withSession(['_old_input' => ['is_renar' => 'S']]);
        // Blade::render no ejecuta middleware HTTP; vincular la sesión al request.
        request()->setLaravelSession($this->app['session.store']);
        $this->assertSame('S', old('is_renar'));
        $html = $this->renderIndicator($item, 'is_renar');

        $this->assertMatchesRegularExpression(
            '/<option value="S"\s+selected(?:\s|>)/',
            $html
        );
        $this->assertDoesNotMatchRegularExpression(
            '/<option value="N"\s+selected(?:\s|>)/',
            $html
        );
    }

    private function renderIndicator(ShipmentItem $item, string $field): string
    {
        $view = file_get_contents(resource_path(
            'views/company/simple/desconsolidado/operations/bills/items/create.blade.php'
        ));

        // Compilar el <select> verdadero del formulario, no una copia del código.
        $start = strpos($view, '<select id="{{ $indicator[\'name\'] }}"');
        $this->assertNotFalse($start);
        $end = strpos($view, '</select>', $start);
        $this->assertNotFalse($end);

        return Blade::render(substr($view, $start, $end + 9 - $start), [
            'editingItem' => $item,
            'indicator' => ['name' => $field],
            'errors' => new ViewErrorBag(),
        ]);
    }
}
