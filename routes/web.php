<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WelcomeController;
use App\Http\Controllers\ManualController;
use App\Http\Middleware\CompanyAccess;

/*
|--------------------------------------------------------------------------
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

    // Manual de usuario: disponible para todo usuario autenticado
    Route::get('/manual', [ManualController::class, 'index'])->name('manual.index');
    Route::get('/manual/{tema}/{slug}', [ManualController::class, 'show'])
        ->where(['tema' => '[a-z0-9-]+', 'slug' => '[a-z0-9-]+'])
        ->name('manual.show');

    // Rutas del Super Administrador
    Route::prefix('admin')
        ->middleware(['role:super-admin'])
        ->group(base_path('routes/admin.php'));

    // Rutas del Administrador de Empresa y Usuarios
    // Tanto company-admin como user pueden acceder a estas rutas
    // pero con diferentes niveles de permisos
    Route::prefix('company')
        ->middleware(['role:company-admin|user', CompanyAccess::class])
        ->group(base_path('routes/company.php'));

    // Desconsolidados Argentina: reemplaza las dos definiciones históricas
    // por el controlador E2E canónico sin modificar sus URLs ni nombres.
    Route::prefix('company')
        ->middleware(['role:company-admin|user', CompanyAccess::class])
        ->group(base_path('routes/company_desconsolidado.php'));
});

// Rutas adicionales que requieren verificación de empresa
Route::middleware(['auth', 'verified', CompanyAccess::class])->group(function () {
    // Rutas específicas que requieren verificación de empresa
    // Estas rutas serán manejadas dentro de los controladores específicos
});

// ========================================
// RUTAS DE UTILIDAD Y DESARROLLO
// ========================================

