<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WelcomeController;
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
});

// Rutas adicionales que requieren verificación de empresa
Route::middleware(['auth', 'verified', CompanyAccess::class])->group(function () {
    // Rutas específicas que requieren verificación de empresa
    // Estas rutas serán manejadas dentro de los controladores específicos
});
