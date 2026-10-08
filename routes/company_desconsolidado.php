<?php

use App\Http\Controllers\Company\Simple\ArgentinaDeconsolidatedController;
use App\Http\Controllers\Company\Simple\LegacyDeconsolidationRedirectController;
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

/**
 * El CRUD histórico /deconsolidation usaba datos de ejemplo y TODOs, incluso
 * para supuestas operaciones DESA. Se conservan sus URI y nombres sólo para
 * que enlaces antiguos no fallen, pero toda entrada queda redirigida al
 * módulo real. Ninguna operación legacy puede persistir ni simular un envío.
 */
Route::prefix('deconsolidation')
    ->group(function () {
        Route::get('/', LegacyDeconsolidationRedirectController::class)
            ->name('company.deconsolidation.index');
        Route::get('/create', LegacyDeconsolidationRedirectController::class)
            ->name('company.deconsolidation.create');
        Route::post('/', LegacyDeconsolidationRedirectController::class)
            ->name('company.deconsolidation.store');
        Route::get('/{deconsolidation}', LegacyDeconsolidationRedirectController::class)
            ->name('company.deconsolidation.show');
        Route::get('/{deconsolidation}/edit', LegacyDeconsolidationRedirectController::class)
            ->name('company.deconsolidation.edit');
        Route::put('/{deconsolidation}', LegacyDeconsolidationRedirectController::class)
            ->name('company.deconsolidation.update');
        Route::delete('/{deconsolidation}', LegacyDeconsolidationRedirectController::class)
            ->name('company.deconsolidation.destroy');
        Route::patch('/{deconsolidation}/status', LegacyDeconsolidationRedirectController::class)
            ->name('company.deconsolidation.update-status');
        Route::get('/{deconsolidation}/pdf', LegacyDeconsolidationRedirectController::class)
            ->name('company.deconsolidation.pdf');
    });
