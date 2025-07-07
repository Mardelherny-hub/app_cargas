<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\CompanyController;
use App\Http\Controllers\Admin\SystemController;
use App\Http\Controllers\Admin\ReportController;

/*
|--------------------------------------------------------------------------
| Admin Routes
|--------------------------------------------------------------------------
|
| Rutas para el Super Administrador del sistema.
| Middleware aplicado: ['auth', 'verified', 'role:super-admin']
|
*/

// Dashboard del Super Administrador
Route::get('/dashboard', [DashboardController::class, 'index'])->name('admin.dashboard');

// Gestión de Usuarios
Route::prefix('users')->name('admin.users.')->group(function () {
    Route::get('/', [UserController::class, 'index'])->name('index');
    Route::get('/create', [UserController::class, 'create'])->name('create');
    Route::post('/', [UserController::class, 'store'])->name('store');
    Route::get('/{user}', [UserController::class, 'show'])->name('show');
    Route::get('/{user}/edit', [UserController::class, 'edit'])->name('edit');
    Route::put('/{user}', [UserController::class, 'update'])->name('update');
    Route::delete('/{user}', [UserController::class, 'destroy'])->name('destroy');

    // Acciones específicas de usuarios
    Route::patch('/{user}/toggle-status', [UserController::class, 'toggleStatus'])->name('toggle-status');
    Route::patch('/{user}/reset-password', [UserController::class, 'resetPassword'])->name('reset-password');
    Route::get('/{user}/permissions', [UserController::class, 'permissions'])->name('permissions');
    Route::put('/{user}/permissions', [UserController::class, 'updatePermissions'])->name('update-permissions');
});

// Gestión de Empresas
Route::prefix('companies')->name('admin.companies.')->group(function () {
    Route::get('/', [CompanyController::class, 'index'])->name('index');
    Route::get('/create', [CompanyController::class, 'create'])->name('create');
    Route::post('/', [CompanyController::class, 'store'])->name('store');
    Route::get('/{company}', [CompanyController::class, 'show'])->name('show');
    Route::get('/{company}/edit', [CompanyController::class, 'edit'])->name('edit');
    Route::put('/{company}', [CompanyController::class, 'update'])->name('update');
    Route::delete('/{company}', [CompanyController::class, 'destroy'])->name('destroy');

    // Gestión de certificados
    Route::get('/{company}/certificates', [CompanyController::class, 'certificates'])->name('certificates');
    Route::post('/{company}/certificates', [CompanyController::class, 'uploadCertificate'])->name('upload-certificate');
    Route::delete('/{company}/certificates', [CompanyController::class, 'deleteCertificate'])->name('delete-certificate');

    // Configuración de webservices
    Route::get('/{company}/webservices', [CompanyController::class, 'webservices'])->name('webservices');
    Route::put('/{company}/webservices', [CompanyController::class, 'updateWebservices'])->name('update-webservices');
    Route::post('/{company}/webservices/test', [CompanyController::class, 'testWebservice'])->name('test-webservice');

    // Operadores de la empresa
    Route::get('/{company}/operators', [CompanyController::class, 'operators'])->name('operators');
});

// Configuración del Sistema
Route::prefix('system')->name('admin.system.')->group(function () {
    Route::get('/settings', [SystemController::class, 'settings'])->name('settings');
    Route::put('/settings', [SystemController::class, 'updateSettings'])->name('update-settings');
    Route::get('/maintenance', [SystemController::class, 'maintenance'])->name('maintenance');
    Route::post('/maintenance/enable', [SystemController::class, 'enableMaintenance'])->name('enable-maintenance');
    Route::post('/maintenance/disable', [SystemController::class, 'disableMaintenance'])->name('disable-maintenance');

    // Auditoría y logs
    Route::get('/audit', [SystemController::class, 'audit'])->name('audit');
    Route::get('/logs', [SystemController::class, 'logs'])->name('logs');
    Route::delete('/logs', [SystemController::class, 'clearLogs'])->name('clear-logs');

    // Backups
    Route::get('/backups', [SystemController::class, 'backups'])->name('backups');
    Route::post('/backups', [SystemController::class, 'createBackup'])->name('create-backup');
    Route::delete('/backups/{backup}', [SystemController::class, 'deleteBackup'])->name('delete-backup');

    // Comandos del sistema
    Route::get('/commands', [SystemController::class, 'commands'])->name('commands');
    Route::post('/commands/verify-users', [SystemController::class, 'verifyUsers'])->name('verify-users');
    Route::post('/commands/optimize', [SystemController::class, 'optimize'])->name('optimize');
});

// Reportes y Estadísticas
Route::prefix('reports')->name('admin.reports.')->group(function () {
    Route::get('/', [ReportController::class, 'index'])->name('index');
    Route::get('/users', [ReportController::class, 'users'])->name('users');
    Route::get('/companies', [ReportController::class, 'companies'])->name('companies');
    Route::get('/system-stats', [ReportController::class, 'systemStats'])->name('system-stats');
    Route::get('/activity', [ReportController::class, 'activity'])->name('activity');

    // Exportar reportes
    Route::get('/export/users', [ReportController::class, 'exportUsers'])->name('export-users');
    Route::get('/export/companies', [ReportController::class, 'exportCompanies'])->name('export-companies');
    Route::get('/export/activity', [ReportController::class, 'exportActivity'])->name('export-activity');
});

// Gestión de Roles y Permisos
Route::prefix('roles')->name('admin.roles.')->group(function () {
    Route::get('/', [UserController::class, 'roles'])->name('index');
    Route::get('/permissions', [UserController::class, 'permissions'])->name('permissions');
    Route::put('/permissions', [UserController::class, 'updateRolePermissions'])->name('update-permissions');
});
