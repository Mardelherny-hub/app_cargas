<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\CompanyController;
use App\Http\Controllers\Admin\SystemController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\ClientController;
use App\Http\Controllers\Admin\VesselOwnerController;
use App\Http\Controllers\Admin\VesselTypeController;
use App\Http\Controllers\Admin\CertificateController;

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

// Configuración general (vista + acciones)
Route::get('/settings', [SettingsController::class, 'index'])->name('admin.settings');
Route::put('/settings/general', [SettingsController::class, 'updateGeneral'])->name('settings.updateGeneral');
Route::put('/settings/security', [SettingsController::class, 'updateSecurity'])->name('settings.updateSecurity');
Route::patch('/settings/maintenance', [SettingsController::class, 'toggleMaintenance'])->name('settings.toggleMaintenance');

// Configuración General tools
Route::get('/tools', [SettingsController::class, 'index'])->name('admin.tools');

// Mantenimiento
Route::get('/maintenance', [SettingsController::class, 'maintenance'])->name('admin.maintenance');

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

    // ========================================
    // CERTIFICADOS DIGITALES (solo company-admin)
    // ========================================
    Route::prefix('certificates')->name('company.certificates.')->middleware(['role:company-admin'])->group(function () {
        // Vista principal de certificados
        Route::get('/', [CertificateController::class, 'index'])->name('index');
        
        // Subida de certificados
        Route::get('/upload', [CertificateController::class, 'upload'])->name('upload');
        Route::post('/upload', [CertificateController::class, 'processUpload'])->name('processUpload');
        
        // Ver detalles del certificado actual de la empresa (SIN parámetro)
        Route::get('/details', [CertificateController::class, 'show'])->name('show');
        
        // Eliminar certificado actual de la empresa (SIN parámetro) 
        Route::delete('/delete', [CertificateController::class, 'destroy'])->name('destroy');
        
        // Renovación de certificados (SIN parámetro)
        Route::get('/renew', [CertificateController::class, 'renew'])->name('renew');
        Route::post('/renew', [CertificateController::class, 'processRenew'])->name('processRenew');
    });
    // Configuración de webservices
    Route::get('/{company}/webservices', [CompanyController::class, 'webservices'])->name('webservices');
    Route::put('/{company}/webservices', [CompanyController::class, 'updateWebservices'])->name('update-webservices');
    Route::post('/{company}/webservices/test', [CompanyController::class, 'testWebservice'])->name('test-webservice');

    // Operadores de la empresa
    Route::get('/{company}/operators', [CompanyController::class, 'operators'])->name('operators');
});

// Gestión de Clientes - NUEVO MÓDULO FASE 4
Route::prefix('clients')->name('admin.clients.')
    ->group(function () {
        // Rutas básicas CRUD
        Route::get('/', [ClientController::class, 'index'])->name('index');
        Route::get('/create', [ClientController::class, 'create'])
            
            ->name('create');
        Route::post('/', [ClientController::class, 'store'])
            
            ->name('store');
        Route::get('/{client}', [ClientController::class, 'show'])            
            ->name('show');
        Route::get('/{client}/edit', [ClientController::class, 'edit'])
            ->name('edit');
        Route::put('/{client}', [ClientController::class, 'update'])
            ->name('update');
        Route::delete('/{client}', [ClientController::class, 'destroy'])
            ->name('destroy');

        // Acciones específicas
        Route::patch('/{client}/verify', [ClientController::class, 'verify'])
            ->name('verify');
        Route::patch('/{client}/toggle-status', [ClientController::class, 'toggleStatus'])
            ->name('toggle-status');
        Route::post('/{client}/transfer', [ClientController::class, 'transfer'])
            ->name('transfer');
        Route::post('/bulk-import', [ClientController::class, 'bulkImport'])
            
            ->name('bulk-import');
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

// Gestión de Propietarios de Embarcaciones
Route::prefix('vessel-owners')->name('admin.vessel-owners.')->group(function () {
    Route::get('/', [VesselOwnerController::class, 'index'])->name('index');
    Route::get('/create', [VesselOwnerController::class, 'create'])->name('create');
    Route::post('/', [VesselOwnerController::class, 'store'])->name('store');
    Route::get('/{vesselOwner}', [VesselOwnerController::class, 'show'])->name('show');
    Route::get('/{vesselOwner}/edit', [VesselOwnerController::class, 'edit'])->name('edit');
    Route::put('/{vesselOwner}', [VesselOwnerController::class, 'update'])->name('update');
    Route::delete('/{vesselOwner}', [VesselOwnerController::class, 'destroy'])->name('destroy');

    // Acciones específicas de propietarios
    Route::patch('/{vesselOwner}/verify', [VesselOwnerController::class, 'verify'])->name('verify');
    Route::patch('/{vesselOwner}/toggle-status', [VesselOwnerController::class, 'toggleStatus'])->name('toggle-status');
    Route::post('/{vesselOwner}/transfer', [VesselOwnerController::class, 'transfer'])->name('transfer');
    Route::post('/bulk-action', [VesselOwnerController::class, 'bulkAction'])->name('bulk-action');
});

// Gestión de Tipos de Embarcación (VesselType)
Route::prefix('vessel-types')->name('admin.vessel-types.')->group(function () {
    Route::get('/', [VesselTypeController::class, 'index'])->name('index');
    Route::get('/create', [VesselTypeController::class, 'create'])->name('create');
    Route::post('/', [VesselTypeController::class, 'store'])->name('store');
    Route::get('/{vesselType}', [VesselTypeController::class, 'show'])->name('show');
    Route::get('/{vesselType}/edit', [VesselTypeController::class, 'edit'])->name('edit');
    Route::put('/{vesselType}', [VesselTypeController::class, 'update'])->name('update');
    Route::delete('/{vesselType}', [VesselTypeController::class, 'destroy'])->name('destroy');

    // Acciones específicas de tipos de embarcación
    Route::patch('/{vesselType}/toggle-status', [VesselTypeController::class, 'toggleStatus'])->name('toggle-status');
    Route::post('/{vesselType}/duplicate', [VesselTypeController::class, 'duplicate'])->name('duplicate');
    Route::post('/import-csv', [VesselTypeController::class, 'importFromCsv'])->name('import-csv');
    Route::post('/bulk-action', [VesselTypeController::class, 'bulkAction'])->name('bulk-action');
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

    Route::get('/clients', [ReportController::class, 'clients'])->name('clients');
    Route::get('/export/clients', [ReportController::class, 'exportClients'])->name('export-clients');
});

// Gestión de Roles y Permisos
Route::prefix('roles')->name('admin.roles.')->group(function () {
    Route::get('/', [UserController::class, 'roles'])->name('index');
    Route::get('/permissions', [UserController::class, 'permissions'])->name('permissions');
    Route::put('/permissions', [UserController::class, 'updateRolePermissions'])->name('update-permissions');
});
