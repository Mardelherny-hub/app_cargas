<?php

namespace App\Http\Controllers\Company\Simple;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

/**
 * Retira de circulación el CRUD histórico de desconsolidación que trabajaba
 * con datos de ejemplo/TODO. No modifica ni elimina datos empresariales.
 */
class LegacyDeconsolidationRedirectController extends Controller
{
    public function __invoke(): RedirectResponse
    {
        return redirect()
            ->route('company.simple.dashboard')
            ->with(
                'error',
                'El módulo histórico de desconsolidación fue reemplazado por el circuito real de Desconsolidados Argentina.'
            );
    }
}
