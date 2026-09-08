<?php

use App\Http\Controllers\Company\Simple\ArgentinaDeconsolidatedController;
use Illuminate\Support\Facades\Route;

/**
 * Handler canónico de las dos URLs históricas de Desconsolidados Argentina.
 *
 * Los nombres de ruta siguen definidos una sola vez en routes/company.php.
 * Este archivo se carga después, con las mismas URI y métodos HTTP pero sin
 * nombres duplicados, para que esas URLs sean atendidas por el controlador E2E.
 */
Route::prefix('simple/webservices/desconsolidado')
    ->group(function () {
        Route::get('/{voyage}', [ArgentinaDeconsolidatedController::class, 'show'])
            ->whereNumber('voyage');

        Route::post('/{voyage}/send', [ArgentinaDeconsolidatedController::class, 'send'])
            ->whereNumber('voyage');
    });
