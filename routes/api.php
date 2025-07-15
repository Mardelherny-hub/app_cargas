<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ClientController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Rutas API para módulo de clientes - FASE 4
Route::prefix('v1')->middleware(['auth:sanctum'])->group(function () {

    // Rutas de clientes con control de acceso
    Route::prefix('clients')->middleware(['client.access:view'])->group(function () {

        // Rutas básicas CRUD
        Route::get('/', [ClientController::class, 'index'])->name('api.clients.index');
        Route::post('/', [ClientController::class, 'store'])
            ->middleware(['client.access:create'])
            ->name('api.clients.store');
        Route::get('/{client}', [ClientController::class, 'show'])
            ->middleware(['client.access:view'])
            ->name('api.clients.show');
        Route::put('/{client}', [ClientController::class, 'update'])
            ->middleware(['client.access:edit'])
            ->name('api.clients.update');
        Route::delete('/{client}', [ClientController::class, 'destroy'])
            ->middleware(['client.access:delete'])
            ->name('api.clients.destroy');

        // Búsqueda y sugerencias
        Route::get('/search', [ClientController::class, 'search'])->name('api.clients.search');
        Route::get('/suggestions', [ClientController::class, 'suggestions'])->name('api.clients.suggestions');

        // Acciones específicas
        Route::patch('/{client}/verify', [ClientController::class, 'verify'])
            ->middleware(['client.access:verify'])
            ->name('api.clients.verify');
        Route::patch('/{client}/toggle-status', [ClientController::class, 'toggleStatus'])
            ->middleware(['client.access:edit'])
            ->name('api.clients.toggle-status');

        // Datos auxiliares
        Route::get('/form-data', [ClientController::class, 'formData'])->name('api.clients.form-data');
        Route::post('/validate-tax-id', [ClientController::class, 'validateTaxId'])->name('api.clients.validate-tax-id');
    });

    // Rutas admin
    Route::prefix('admin')->middleware(['role:super-admin'])->group(function () {
        Route::post('/clients/{client}/transfer', [ClientController::class, 'transfer'])
            ->middleware(['client.access:transfer'])
            ->name('api.admin.clients.transfer');
    });
});
