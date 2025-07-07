<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Internal\DashboardController;
use App\Http\Controllers\Internal\MonitoringController;
use App\Http\Controllers\Internal\CompanyController;
use App\Http\Controllers\Internal\ShipmentController;
use App\Http\Controllers\Internal\TripController;
use App\Http\Controllers\Internal\TransferController;
use App\Http\Controllers\Internal\ReportController;
use App\Http\Controllers\Internal\WebserviceController;
use App\Http\Controllers\Internal\SupportController;

/*
|--------------------------------------------------------------------------
| Internal Operator Routes
|--------------------------------------------------------------------------
|
| Rutas para el Operador Interno del sistema.
| Middleware aplicado: ['auth', 'verified', 'role:internal-operator']
|
*/

// Dashboard del Operador Interno
Route::get('/dashboard', [DashboardController::class, 'index'])->name('internal.dashboard');

// Monitoreo General del Sistema
Route::prefix('monitoring')->name('internal.monitoring.')->group(function () {
    Route::get('/', [MonitoringController::class, 'index'])->name('index');
    Route::get('/companies', [MonitoringController::class, 'companies'])->name('companies');
    Route::get('/shipments', [MonitoringController::class, 'shipments'])->name('shipments');
    Route::get('/trips', [MonitoringController::class, 'trips'])->name('trips');
    Route::get('/webservices', [MonitoringController::class, 'webservices'])->name('webservices');
    Route::get('/system-health', [MonitoringController::class, 'systemHealth'])->name('system-health');

    // Alertas y notificaciones
    Route::get('/alerts', [MonitoringController::class, 'alerts'])->name('alerts');
    Route::patch('/alerts/{alert}/mark-read', [MonitoringController::class, 'markAlertAsRead'])->name('mark-alert-read');
    Route::delete('/alerts/{alert}', [MonitoringController::class, 'dismissAlert'])->name('dismiss-alert');
});

// Gestión de Empresas (Vista general)
Route::prefix('companies')->name('internal.companies.')->group(function () {
    Route::get('/', [CompanyController::class, 'index'])->name('index');
    Route::get('/{company}', [CompanyController::class, 'show'])->name('show');
    Route::get('/{company}/details', [CompanyController::class, 'details'])->name('details');
    Route::get('/{company}/operators', [CompanyController::class, 'operators'])->name('operators');
    Route::get('/{company}/shipments', [CompanyController::class, 'shipments'])->name('shipments');
    Route::get('/{company}/trips', [CompanyController::class, 'trips'])->name('trips');
    Route::get('/{company}/statistics', [CompanyController::class, 'statistics'])->name('statistics');

    // Acciones de soporte
    Route::patch('/{company}/toggle-webservice', [CompanyController::class, 'toggleWebservice'])->name('toggle-webservice');
    Route::post('/{company}/test-certificate', [CompanyController::class, 'testCertificate'])->name('test-certificate');
    Route::post('/{company}/send-notification', [CompanyController::class, 'sendNotification'])->name('send-notification');
});

// Gestión de Cargas (Vista global)
Route::prefix('shipments')->name('internal.shipments.')->group(function () {
    Route::get('/', [ShipmentController::class, 'index'])->name('index');
    Route::get('/{shipment}', [ShipmentController::class, 'show'])->name('show');
    Route::get('/{shipment}/history', [ShipmentController::class, 'history'])->name('history');
    Route::get('/{shipment}/webservice-status', [ShipmentController::class, 'webserviceStatus'])->name('webservice-status');

    // Acciones de soporte
    Route::patch('/{shipment}/priority', [ShipmentController::class, 'updatePriority'])->name('update-priority');
    Route::post('/{shipment}/resend-webservice', [ShipmentController::class, 'resendWebservice'])->name('resend-webservice');
    Route::post('/{shipment}/fix-data', [ShipmentController::class, 'fixData'])->name('fix-data');
    Route::post('/{shipment}/add-note', [ShipmentController::class, 'addNote'])->name('add-note');
});

// Gestión de Viajes (Vista global)
Route::prefix('trips')->name('internal.trips.')->group(function () {
    Route::get('/', [TripController::class, 'index'])->name('index');
    Route::get('/{trip}', [TripController::class, 'show'])->name('show');
    Route::get('/{trip}/tracking', [TripController::class, 'tracking'])->name('tracking');
    Route::get('/{trip}/manifest', [TripController::class, 'manifest'])->name('manifest');

    // Acciones de soporte
    Route::patch('/{trip}/status', [TripController::class, 'updateStatus'])->name('update-status');
    Route::post('/{trip}/force-close', [TripController::class, 'forceClose'])->name('force-close');
    Route::post('/{trip}/emergency-stop', [TripController::class, 'emergencyStop'])->name('emergency-stop');
});

// Gestión de Transferencias entre Empresas
Route::prefix('transfers')->name('internal.transfers.')->group(function () {
    Route::get('/', [TransferController::class, 'index'])->name('index');
    Route::get('/create', [TransferController::class, 'create'])->name('create');
    Route::post('/', [TransferController::class, 'store'])->name('store');
    Route::get('/{transfer}', [TransferController::class, 'show'])->name('show');
    Route::patch('/{transfer}/approve', [TransferController::class, 'approve'])->name('approve');
    Route::patch('/{transfer}/reject', [TransferController::class, 'reject'])->name('reject');
    Route::delete('/{transfer}', [TransferController::class, 'destroy'])->name('destroy');

    // Transferencias masivas
    Route::get('/batch/create', [TransferController::class, 'batchCreate'])->name('batch-create');
    Route::post('/batch', [TransferController::class, 'batchStore'])->name('batch-store');
    Route::get('/batch/{batch}', [TransferController::class, 'batchShow'])->name('batch-show');
});

// Reportes Globales
Route::prefix('reports')->name('internal.reports.')->group(function () {
    Route::get('/', [ReportController::class, 'index'])->name('index');
    Route::get('/cross-company', [ReportController::class, 'crossCompany'])->name('cross-company');
    Route::get('/system-usage', [ReportController::class, 'systemUsage'])->name('system-usage');
    Route::get('/webservice-stats', [ReportController::class, 'webserviceStats'])->name('webservice-stats');
    Route::get('/error-analysis', [ReportController::class, 'errorAnalysis'])->name('error-analysis');
    Route::get('/performance', [ReportController::class, 'performance'])->name('performance');

    // Reportes personalizados
    Route::get('/custom/create', [ReportController::class, 'customCreate'])->name('custom-create');
    Route::post('/custom', [ReportController::class, 'customStore'])->name('custom-store');
    Route::get('/custom/{report}', [ReportController::class, 'customShow'])->name('custom-show');

    // Exportación de reportes
    Route::post('/export/cross-company', [ReportController::class, 'exportCrossCompany'])->name('export-cross-company');
    Route::post('/export/system-usage', [ReportController::class, 'exportSystemUsage'])->name('export-system-usage');
    Route::post('/export/webservice-stats', [ReportController::class, 'exportWebserviceStats'])->name('export-webservice-stats');
});

// Gestión de Webservices (Vista global)
Route::prefix('webservices')->name('internal.webservices.')->group(function () {
    Route::get('/', [WebserviceController::class, 'index'])->name('index');
    Route::get('/status', [WebserviceController::class, 'status'])->name('status');
    Route::get('/logs', [WebserviceController::class, 'logs'])->name('logs');
    Route::get('/errors', [WebserviceController::class, 'errors'])->name('errors');
    Route::get('/statistics', [WebserviceController::class, 'statistics'])->name('statistics');

    // Acciones de mantenimiento
    Route::post('/test-connection', [WebserviceController::class, 'testConnection'])->name('test-connection');
    Route::post('/clear-cache', [WebserviceController::class, 'clearCache'])->name('clear-cache');
    Route::post('/retry-failed', [WebserviceController::class, 'retryFailed'])->name('retry-failed');
    Route::post('/bulk-resend', [WebserviceController::class, 'bulkResend'])->name('bulk-resend');

    // Configuración global
    Route::get('/config', [WebserviceController::class, 'config'])->name('config');
    Route::put('/config', [WebserviceController::class, 'updateConfig'])->name('update-config');
});

// Soporte Técnico
Route::prefix('support')->name('internal.support.')->group(function () {
    Route::get('/', [SupportController::class, 'index'])->name('index');
    Route::get('/tickets', [SupportController::class, 'tickets'])->name('tickets');
    Route::get('/tickets/{ticket}', [SupportController::class, 'showTicket'])->name('show-ticket');
    Route::patch('/tickets/{ticket}/assign', [SupportController::class, 'assignTicket'])->name('assign-ticket');
    Route::patch('/tickets/{ticket}/close', [SupportController::class, 'closeTicket'])->name('close-ticket');
    Route::post('/tickets/{ticket}/reply', [SupportController::class, 'replyTicket'])->name('reply-ticket');

    // Herramientas de diagnóstico
    Route::get('/diagnostics', [SupportController::class, 'diagnostics'])->name('diagnostics');
    Route::post('/diagnostics/company/{company}', [SupportController::class, 'runDiagnostics'])->name('run-diagnostics');
    Route::post('/diagnostics/user/{user}', [SupportController::class, 'runUserDiagnostics'])->name('run-user-diagnostics');

    // Base de conocimientos
    Route::get('/knowledge-base', [SupportController::class, 'knowledgeBase'])->name('knowledge-base');
    Route::get('/knowledge-base/create', [SupportController::class, 'createArticle'])->name('create-article');
    Route::post('/knowledge-base', [SupportController::class, 'storeArticle'])->name('store-article');
    Route::get('/knowledge-base/{article}', [SupportController::class, 'showArticle'])->name('show-article');
    Route::get('/knowledge-base/{article}/edit', [SupportController::class, 'editArticle'])->name('edit-article');
    Route::put('/knowledge-base/{article}', [SupportController::class, 'updateArticle'])->name('update-article');
});

// Herramientas Avanzadas
Route::prefix('tools')->name('internal.tools.')->group(function () {
    Route::get('/', [DashboardController::class, 'tools'])->name('index');
    Route::get('/data-migration', [DashboardController::class, 'dataMigration'])->name('data-migration');
    Route::post('/data-migration/run', [DashboardController::class, 'runDataMigration'])->name('run-data-migration');
    Route::get('/bulk-operations', [DashboardController::class, 'bulkOperations'])->name('bulk-operations');
    Route::post('/bulk-operations/run', [DashboardController::class, 'runBulkOperation'])->name('run-bulk-operation');
    Route::get('/system-maintenance', [DashboardController::class, 'systemMaintenance'])->name('system-maintenance');
    Route::post('/system-maintenance/run', [DashboardController::class, 'runSystemMaintenance'])->name('run-system-maintenance');
});
