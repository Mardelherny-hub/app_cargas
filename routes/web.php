<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WelcomeController;

/*
 | --*------------------------------------------------------------------------
 | Web Routes
 |--------------------------------------------------------------------------
 |
 | Aquí se registran las rutas web para la aplicación. Estas rutas
 | son cargadas por el RouteServiceProvider dentro de un grupo que
 | contiene el middleware "web".
 |
 */

// Ruta pública de bienvenida
Route::get('/', [WelcomeController::class, 'index'])->name('welcome');

// Rutas de autenticación (Jetstream)
// Estas rutas son manejadas automáticamente por Jetstream

// Dashboard genérico (fallback)
Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

// Incluir rutas por área de usuario
Route::middleware(['auth', 'verified'])->group(function () {

    // Rutas del Super Administrador
    Route::prefix('admin')
    ->middleware(['role:super-admin'])
    ->group(base_path('routes/admin.php'));

    // Rutas del Administrador de Empresa
    Route::prefix('company')
    ->middleware(['role:company-admin'])
    ->group(base_path('routes/company.php'));

    // Rutas del Operador Interno
    Route::prefix('internal')
    ->middleware(['role:internal-operator'])
    ->group(base_path('routes/internal.php'));

    // Rutas del Operador Externo
    Route::prefix('operator')
    ->middleware(['role:external-operator', 'company.access'])
    ->group(base_path('routes/operator.php'));
});

// Middleware personalizado para verificar acceso por empresa
Route::middleware(['auth', 'verified', 'company.access'])->group(function () {
    // Rutas que requieren verificación de empresa
    // Estas rutas serán agregadas en los archivos específicos por rol
});
