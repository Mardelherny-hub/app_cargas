<?php

use App\Http\Controllers\Company\Simple\ArgentinaDeconsolidatedController;
use Illuminate\Support\Facades\Route;

/**
 * Handler canónico de las URLs de Desconsolidados Argentina.
 *
 * Los nombres históricos show/send siguen definidos una sola vez en
 * routes/company.php. Este archivo se carga después con las mismas URI para
 * que esas URLs sean atendidas por el controlador E2E. Las rutas nuevas y
 * exclusivas de DESC sí se nombran aquí.
 */
Route::prefix('simple/webservices/desconsolidado')
    ->group(function () {
        Route::get('/{voyage}', [ArgentinaDeconsolidatedController::class, 'show'])
            ->whereNumber('voyage');

        Route::post('/{voyage}/send', [ArgentinaDeconsolidatedController::class, 'send'])
            ->whereNumber('voyage');

        Route::post(
            '/{voyage}/containers/{container}/customs',
            [ArgentinaDeconsolidatedController::class, 'saveContainerCustoms']
        )
            ->whereNumber('voyage')
            ->whereNumber('container')
            ->name('company.simple.desconsolidado.container-customs');
    });
