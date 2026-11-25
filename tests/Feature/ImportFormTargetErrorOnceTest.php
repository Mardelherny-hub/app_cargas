<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

/**
 * Formulario de importación: cuando el servidor rechaza el viaje destino, el
 * aviso aparece una sola vez, en la caja que también usa la verificación en vivo.
 */
class ImportFormTargetErrorOnceTest extends TestCase
{
    private const MESSAGE = 'La embarcación VICKY B tiene registros vigentes en AFIP para este viaje: MIC/DTA M1 registrado en AFIP sin anulación aprobada.';

    private function targetBlock(ViewErrorBag $errors): string
    {
        $source = file_get_contents(resource_path('views/company/manifests/import.blade.php'));
        $start = strpos($source, '<div id="target-voyage-block"');
        $end = strpos($source, '<!-- Tipo de operación CUSCAR -->', $start);

        return Blade::render(substr($source, $start, $end - $start), [
            'errors' => $errors,
            'planningVoyages' => collect(),
            'vessels' => collect(),
        ]);
    }

    public function test_server_rejection_is_shown_once_in_the_live_box(): void
    {
        $errors = (new ViewErrorBag())->put('default', new MessageBag(['target_voyage_id' => self::MESSAGE]));

        $html = $this->targetBlock($errors);

        $this->assertSame(1, substr_count($html, self::MESSAGE));
        $this->assertMatchesRegularExpression('/<div id="target-error"[^>]*display:block;">' . preg_quote(self::MESSAGE, '/') . '<\/div>/', $html);
    }

    public function test_box_is_hidden_without_errors(): void
    {
        $html = $this->targetBlock(new ViewErrorBag());

        $this->assertMatchesRegularExpression('/<div id="target-error"[^>]*display:none;"><\/div>/', $html);
    }
}
