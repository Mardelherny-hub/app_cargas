<?php

use App\Http\Controllers\Company\Simple\ArgentinaDeconsolidatedController;
use Illuminate\Support\Facades\Route;

/**
 * Handler canónico de las URLs de Desconsolidados Argentina.
 *
 * Se carga después de routes/company.php con las mismas URI y nombres para
 * que tanto el matching HTTP como route(...) resuelvan al controlador E2E.
 */
Route::prefix('simple/webservices/desconsolidado')
    ->group(function () {
        Route::get('/{voyage}', [ArgentinaDeconsolidatedController::class, 'show'])
            ->whereNumber('voyage')
            ->name('company.simple.desconsolidado.show');

        Route::post('/{voyage}/send', [ArgentinaDeconsolidatedController::class, 'send'])
            ->whereNumber('voyage')
            ->name('company.simple.desconsolidado.send');

        Route::post(
            '/{voyage}/containers/{container}/customs',
            [ArgentinaDeconsolidatedController::class, 'saveContainerCustoms']
        )
            ->whereNumber('voyage')
            ->whereNumber('container')
            ->name('company.simple.desconsolidado.container-customs');
    });
