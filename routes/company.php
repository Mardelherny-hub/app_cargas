<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Company\DashboardController;
use App\Http\Controllers\Company\ShipmentController;
use App\Http\Controllers\Company\TripController;
use App\Http\Controllers\Company\OperatorController;
use App\Http\Controllers\Company\ReportController;
use App\Http\Controllers\Company\CertificateController;
use App\Http\Controllers\Company\WebserviceController;
use App\Http\Controllers\Company\ImportController;
use App\Http\Controllers\Company\ExportController;

/*
|--------------------------------------------------------------------------
| Company Admin Routes
|--------------------------------------------------------------------------
|
| Rutas para el Administrador de Empresa.
| Middleware aplicado: ['auth', 'verified', 'role:company-admin']
|
*/

// Dashboard del Administrador de Empresa
Route::get('/dashboard', [DashboardController::class, 'index'])->name('company.dashboard');

// Gestión de Cargas
Route::prefix('shipments')->name('company.shipments.')->group(function () {
    Route::get('/', [ShipmentController::class, 'index'])->name('index');
    Route::get('/create', [ShipmentController::class, 'create'])->name('create');
    Route::post('/', [ShipmentController::class, 'store'])->name('store');
    Route::get('/{shipment}', [ShipmentController::class, 'show'])->name('show');
    Route::get('/{shipment}/edit', [ShipmentController::class, 'edit'])->name('edit');
    Route::put('/{shipment}', [ShipmentController::class, 'update'])->name('update');
    Route::delete('/{shipment}', [ShipmentController::class, 'destroy'])->name('destroy');

    // Acciones específicas de cargas
    Route::patch('/{shipment}/status', [ShipmentController::class, 'updateStatus'])->name('update-status');
    Route::post('/{shipment}/duplicate', [ShipmentController::class, 'duplicate'])->name('duplicate');
    Route::get('/{shipment}/pdf', [ShipmentController::class, 'generatePdf'])->name('pdf');

    // Adjuntos
    Route::get('/{shipment}/attachments', [ShipmentController::class, 'attachments'])->name('attachments');
    Route::post('/{shipment}/attachments', [ShipmentController::class, 'uploadAttachment'])->name('upload-attachment');
    Route::delete('/{shipment}/attachments/{attachment}', [ShipmentController::class, 'deleteAttachment'])->name('delete-attachment');
});

// Gestión de Viajes
Route::prefix('trips')->name('company.trips.')->group(function () {
    Route::get('/', [TripController::class, 'index'])->name('index');
    Route::get('/create', [TripController::class, 'create'])->name('create');
    Route::post('/', [TripController::class, 'store'])->name('store');
    Route::get('/{trip}', [TripController::class, 'show'])->name('show');
    Route::get('/{trip}/edit', [TripController::class, 'edit'])->name('edit');
    Route::put('/{trip}', [TripController::class, 'update'])->name('update');
    Route::delete('/{trip}', [TripController::class, 'destroy'])->name('destroy');

    // Acciones específicas de viajes
    Route::patch('/{trip}/close', [TripController::class, 'close'])->name('close');
    Route::patch('/{trip}/reopen', [TripController::class, 'reopen'])->name('reopen');
    Route::get('/{trip}/manifest', [TripController::class, 'manifest'])->name('manifest');
    Route::get('/{trip}/shipments', [TripController::class, 'shipments'])->name('shipments');
    Route::post('/{trip}/shipments', [TripController::class, 'addShipment'])->name('add-shipment');
    Route::delete('/{trip}/shipments/{shipment}', [TripController::class, 'removeShipment'])->name('remove-shipment');
});

// Gestión de Operadores
Route::prefix('operators')->name('company.operators.')->group(function () {
    Route::get('/', [OperatorController::class, 'index'])->name('index');
    Route::get('/create', [OperatorController::class, 'create'])->name('create');
    Route::post('/', [OperatorController::class, 'store'])->name('store');
    Route::get('/{operator}', [OperatorController::class, 'show'])->name('show');
    Route::get('/{operator}/edit', [OperatorController::class, 'edit'])->name('edit');
    Route::put('/{operator}', [OperatorController::class, 'update'])->name('update');
    Route::delete('/{operator}', [OperatorController::class, 'destroy'])->name('destroy');

    // Permisos del operador
    Route::get('/{operator}/permissions', [OperatorController::class, 'permissions'])->name('permissions');
    Route::put('/{operator}/permissions', [OperatorController::class, 'updatePermissions'])->name('update-permissions');
    Route::patch('/{operator}/toggle-status', [OperatorController::class, 'toggleStatus'])->name('toggle-status');
});

// Gestión de Certificados
Route::prefix('certificates')->name('company.certificates.')->group(function () {
    Route::get('/', [CertificateController::class, 'index'])->name('index');
    Route::post('/upload', [CertificateController::class, 'upload'])->name('upload');
    Route::delete('/{certificate}', [CertificateController::class, 'destroy'])->name('destroy');
    Route::post('/test', [CertificateController::class, 'test'])->name('test');
    Route::get('/info', [CertificateController::class, 'info'])->name('info');
});

// Configuración de Webservices
Route::prefix('webservices')->name('company.webservices.')->group(function () {
    Route::get('/', [WebserviceController::class, 'index'])->name('index');
    Route::put('/config', [WebserviceController::class, 'updateConfig'])->name('update-config');
    Route::post('/test', [WebserviceController::class, 'test'])->name('test');
    Route::get('/status', [WebserviceController::class, 'status'])->name('status');

    // Envío de datos
    Route::post('/send/{shipment}', [WebserviceController::class, 'sendShipment'])->name('send-shipment');
    Route::post('/send/{trip}', [WebserviceController::class, 'sendTrip'])->name('send-trip');
    Route::post('/rectify/{shipment}', [WebserviceController::class, 'rectifyShipment'])->name('rectify-shipment');
    Route::post('/cancel/{shipment}', [WebserviceController::class, 'cancelShipment'])->name('cancel-shipment');

    // Consultas
    Route::get('/query/{shipment}', [WebserviceController::class, 'queryShipment'])->name('query-shipment');
    Route::get('/logs', [WebserviceController::class, 'logs'])->name('logs');
});

// Importación y Exportación
Route::prefix('import')->name('company.import.')->group(function () {
    Route::get('/', [ImportController::class, 'index'])->name('index');
    Route::post('/excel', [ImportController::class, 'excel'])->name('excel');
    Route::post('/xml', [ImportController::class, 'xml'])->name('xml');
    Route::post('/edi', [ImportController::class, 'edi'])->name('edi');
    Route::post('/cuscar', [ImportController::class, 'cuscar'])->name('cuscar');
    Route::post('/txt', [ImportController::class, 'txt'])->name('txt');
    Route::get('/templates', [ImportController::class, 'templates'])->name('templates');
    Route::get('/history', [ImportController::class, 'history'])->name('history');
});

Route::prefix('export')->name('company.export.')->group(function () {
    Route::get('/', [ExportController::class, 'index'])->name('index');
    Route::post('/shipments', [ExportController::class, 'shipments'])->name('shipments');
    Route::post('/trips', [ExportController::class, 'trips'])->name('trips');
    Route::post('/custom', [ExportController::class, 'custom'])->name('custom');
});

// Reportes
Route::prefix('reports')->name('company.reports.')->group(function () {
    Route::get('/', [ReportController::class, 'index'])->name('index');
    Route::get('/manifests', [ReportController::class, 'manifests'])->name('manifests');
    Route::get('/bills-of-lading', [ReportController::class, 'billsOfLading'])->name('bills-of-lading');
    Route::get('/micdta', [ReportController::class, 'micdta'])->name('micdta');
    Route::get('/arrival-notices', [ReportController::class, 'arrivalNotices'])->name('arrival-notices');
    Route::get('/customs', [ReportController::class, 'customs'])->name('customs');
    Route::get('/statistics', [ReportController::class, 'statistics'])->name('statistics');

    // Generar reportes
    Route::post('/generate/manifest', [ReportController::class, 'generateManifest'])->name('generate-manifest');
    Route::post('/generate/bill-of-lading', [ReportController::class, 'generateBillOfLading'])->name('generate-bill-of-lading');
    Route::post('/generate/micdta', [ReportController::class, 'generateMicdta'])->name('generate-micdta');
    Route::post('/generate/arrival-notice', [ReportController::class, 'generateArrivalNotice'])->name('generate-arrival-notice');
});

// Configuración de la Empresa
Route::prefix('settings')->name('company.settings.')->group(function () {
    Route::get('/', [DashboardController::class, 'settings'])->name('index');
    Route::put('/company', [DashboardController::class, 'updateCompany'])->name('update-company');
    Route::put('/preferences', [DashboardController::class, 'updatePreferences'])->name('update-preferences');
});
