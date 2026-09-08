<?php

use App\Http\Controllers\Company\Simple\ArgentinaDeconsolidatedController;
use Illuminate\Support\Facades\Route;

/**
 * Override canónico de las dos rutas históricas de Desconsolidados Argentina.
 *
 * Se carga después de routes/company.php con exactamente el mismo método, URI
 * y nombre. Illuminate\Routing\RouteCollection conserva la definición más
 * reciente para esa clave, sin cambiar enlaces existentes de la aplicación.
 *
 * Este archivo puede incorporarse directamente a company.php cuando se haga
 * una limpieza posterior del controlador monolítico.
 */
Route::prefix('simple/webservices/desconsolidado')
    ->name('company.simple.desconsolidado.')
    ->group(function () {
        Route::get('/{voyage}', [ArgentinaDeconsolidatedController::class, 'show'])
            ->whereNumber('voyage')
            ->name('show');

        Route::post('/{voyage}/send', [ArgentinaDeconsolidatedController::class, 'send'])
            ->whereNumber('voyage')
            ->name('send');
    });
