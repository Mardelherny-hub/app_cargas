<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Operator\DashboardController;
use App\Http\Controllers\Operator\ShipmentController;
use App\Http\Controllers\Operator\TripController;
use App\Http\Controllers\Operator\ImportController;
use App\Http\Controllers\Operator\ReportController;
use App\Http\Controllers\Operator\WebserviceController;
use App\Http\Controllers\Operator\AttachmentController;
use App\Http\Controllers\Operator\HelpController;

/*
|--------------------------------------------------------------------------
| External Operator Routes
|--------------------------------------------------------------------------
|
| Rutas para el Operador Externo de empresa.
| Middleware aplicado: ['auth', 'verified', 'role:external-operator', 'company.access']
|
*/

// Dashboard del Operador Externo
Route::get('/dashboard', [DashboardController::class, 'index'])->name('operator.dashboard');

// Gestión de Cargas (Solo propias)
Route::prefix('shipments')->name('operator.shipments.')->group(function () {
    Route::get('/', [ShipmentController::class, 'index'])->name('index');
    Route::get('/create', [ShipmentController::class, 'create'])->name('create');
    Route::post('/', [ShipmentController::class, 'store'])->name('store');
    Route::get('/{shipment}', [ShipmentController::class, 'show'])->name('show');
    Route::get('/{shipment}/edit', [ShipmentController::class, 'edit'])->name('edit');
    Route::put('/{shipment}', [ShipmentController::class, 'update'])->name('update');
    Route::delete('/{shipment}', [ShipmentController::class, 'destroy'])->name('destroy');

    // Acciones básicas
    Route::post('/{shipment}/duplicate', [ShipmentController::class, 'duplicate'])->name('duplicate');
    Route::get('/{shipment}/pdf', [ShipmentController::class, 'generatePdf'])->name('pdf');
    Route::patch('/{shipment}/status', [ShipmentController::class, 'updateStatus'])->name('update-status');

    // Búsqueda y filtros
    Route::get('/search', [ShipmentController::class, 'search'])->name('search');
    Route::post('/search', [ShipmentController::class, 'searchResults'])->name('search-results');

    // Historial
    Route::get('/{shipment}/history', [ShipmentController::class, 'history'])->name('history');
    Route::get('/{shipment}/tracking', [ShipmentController::class, 'tracking'])->name('tracking');
});

// Gestión de Viajes (Solo propios)
Route::prefix('trips')->name('operator.trips.')->group(function () {
    Route::get('/', [TripController::class, 'index'])->name('index');
    Route::get('/create', [TripController::class, 'create'])->name('create');
    Route::post('/', [TripController::class, 'store'])->name('store');
    Route::get('/{trip}', [TripController::class, 'show'])->name('show');
    Route::get('/{trip}/edit', [TripController::class, 'edit'])->name('edit');
    Route::put('/{trip}', [TripController::class, 'update'])->name('update');
    Route::delete('/{trip}', [TripController::class, 'destroy'])->name('destroy');

    // Gestión de cargas en el viaje
    Route::get('/{trip}/shipments', [TripController::class, 'shipments'])->name('shipments');
    Route::post('/{trip}/shipments', [TripController::class, 'addShipment'])->name('add-shipment');
    Route::delete('/{trip}/shipments/{shipment}', [TripController::class, 'removeShipment'])->name('remove-shipment');

    // Manifiestos
    Route::get('/{trip}/manifest', [TripController::class, 'manifest'])->name('manifest');
    Route::get('/{trip}/manifest/pdf', [TripController::class, 'manifestPdf'])->name('manifest-pdf');

    // Estado del viaje
    Route::patch('/{trip}/close', [TripController::class, 'close'])->name('close');
    Route::patch('/{trip}/reopen', [TripController::class, 'reopen'])->name('reopen');
});

// Gestión de Adjuntos
Route::prefix('attachments')->name('operator.attachments.')->group(function () {
    Route::get('/', [AttachmentController::class, 'index'])->name('index');
    Route::post('/upload', [AttachmentController::class, 'upload'])->name('upload');
    Route::get('/{attachment}', [AttachmentController::class, 'show'])->name('show');
    Route::get('/{attachment}/download', [AttachmentController::class, 'download'])->name('download');
    Route::delete('/{attachment}', [AttachmentController::class, 'destroy'])->name('destroy');

    // Adjuntos por carga
    Route::get('/shipment/{shipment}', [AttachmentController::class, 'byShipment'])->name('by-shipment');
    Route::post('/shipment/{shipment}', [AttachmentController::class, 'uploadToShipment'])->name('upload-to-shipment');

    // Adjuntos por viaje
    Route::get('/trip/{trip}', [AttachmentController::class, 'byTrip'])->name('by-trip');
    Route::post('/trip/{trip}', [AttachmentController::class, 'uploadToTrip'])->name('upload-to-trip');
});

// Importación de Datos (Si tiene permisos)
Route::prefix('import')->name('operator.import.')->middleware('can:import.excel')->group(function () {
    Route::get('/', [ImportController::class, 'index'])->name('index');
    Route::post('/excel', [ImportController::class, 'excel'])->name('excel');
    Route::post('/xml', [ImportController::class, 'xml'])->name('xml')->middleware('can:import.xml');
    Route::post('/edi', [ImportController::class, 'edi'])->name('edi')->middleware('can:import.edi');
    Route::post('/cuscar', [ImportController::class, 'cuscar'])->name('cuscar')->middleware('can:import.cuscar');
    Route::post('/txt', [ImportController::class, 'txt'])->name('txt')->middleware('can:import.txt');

    // Plantillas
    Route::get('/templates', [ImportController::class, 'templates'])->name('templates');
    Route::get('/templates/excel', [ImportController::class, 'downloadExcelTemplate'])->name('download-excel-template');

    // Historial de importaciones
    Route::get('/history', [ImportController::class, 'history'])->name('history');
    Route::get('/history/{import}', [ImportController::class, 'showImport'])->name('show-import');
});

// Reportes Básicos
Route::prefix('reports')->name('operator.reports.')->group(function () {
    Route::get('/', [ReportController::class, 'index'])->name('index');
    Route::get('/my-shipments', [ReportController::class, 'myShipments'])->name('my-shipments');
    Route::get('/my-trips', [ReportController::class, 'myTrips'])->name('my-trips');
    Route::get('/statistics', [ReportController::class, 'statistics'])->name('statistics');

    // Generar reportes básicos
    Route::post('/generate/shipments', [ReportController::class, 'generateShipments'])->name('generate-shipments');
    Route::post('/generate/trips', [ReportController::class, 'generateTrips'])->name('generate-trips');
    Route::post('/generate/manifest', [ReportController::class, 'generateManifest'])->name('generate-manifest');

    // Reportes específicos (si tiene permisos)
    Route::get('/bills-of-lading', [ReportController::class, 'billsOfLading'])->name('bills-of-lading')->middleware('can:reports.bills_of_lading');
    Route::get('/micdta', [ReportController::class, 'micdta'])->name('micdta')->middleware('can:reports.micdta');
    Route::get('/arrival-notices', [ReportController::class, 'arrivalNotices'])->name('arrival-notices')->middleware('can:reports.arrival_notices');

    // Exportación
    Route::post('/export/shipments', [ReportController::class, 'exportShipments'])->name('export-shipments')->middleware('can:export.data');
    Route::post('/export/trips', [ReportController::class, 'exportTrips'])->name('export-trips')->middleware('can:export.data');
});

// Webservices (Solo consulta)
Route::prefix('webservices')->name('operator.webservices.')->group(function () {
    Route::get('/', [WebserviceController::class, 'index'])->name('index');
    Route::get('/status', [WebserviceController::class, 'status'])->name('status');
    Route::get('/my-shipments', [WebserviceController::class, 'myShipments'])->name('my-shipments');
    Route::get('/logs', [WebserviceController::class, 'logs'])->name('logs');

    // Consultas (si tiene permisos)
    Route::post('/query/{shipment}', [WebserviceController::class, 'queryShipment'])->name('query-shipment')->middleware('can:webservices.query');
    Route::post('/send/{shipment}', [WebserviceController::class, 'sendShipment'])->name('send-shipment')->middleware('can:webservices.send');

    // Historial de envíos
    Route::get('/history', [WebserviceController::class, 'history'])->name('history');
    Route::get('/history/{shipment}', [WebserviceController::class, 'shipmentHistory'])->name('shipment-history');
});

// Transferencias (Si tiene permisos)
Route::prefix('transfers')->name('operator.transfers.')->middleware('can:trips.transfer')->group(function () {
    Route::get('/', [TripController::class, 'transfers'])->name('index');
    Route::get('/create', [TripController::class, 'createTransfer'])->name('create');
    Route::post('/', [TripController::class, 'storeTransfer'])->name('store');
    Route::get('/{transfer}', [TripController::class, 'showTransfer'])->name('show');
    Route::patch('/{transfer}/cancel', [TripController::class, 'cancelTransfer'])->name('cancel');

    // Transferencias recibidas
    Route::get('/received', [TripController::class, 'receivedTransfers'])->name('received');
    Route::patch('/received/{transfer}/accept', [TripController::class, 'acceptTransfer'])->name('accept');
    Route::patch('/received/{transfer}/reject', [TripController::class, 'rejectTransfer'])->name('reject');
});

// Ayuda y Soporte
Route::prefix('help')->name('operator.help.')->group(function () {
    Route::get('/', [HelpController::class, 'index'])->name('index');
    Route::get('/getting-started', [HelpController::class, 'gettingStarted'])->name('getting-started');
    Route::get('/shipments', [HelpController::class, 'shipments'])->name('shipments');
    Route::get('/trips', [HelpController::class, 'trips'])->name('trips');
    Route::get('/imports', [HelpController::class, 'imports'])->name('imports');
    Route::get('/reports', [HelpController::class, 'reports'])->name('reports');
    Route::get('/webservices', [HelpController::class, 'webservices'])->name('webservices');
    Route::get('/faq', [HelpController::class, 'faq'])->name('faq');

    // Tickets de soporte
    Route::get('/tickets', [HelpController::class, 'tickets'])->name('tickets');
    Route::get('/tickets/create', [HelpController::class, 'createTicket'])->name('create-ticket');
    Route::post('/tickets', [HelpController::class, 'storeTicket'])->name('store-ticket');
    Route::get('/tickets/{ticket}', [HelpController::class, 'showTicket'])->name('show-ticket');
    Route::post('/tickets/{ticket}/reply', [HelpController::class, 'replyTicket'])->name('reply-ticket');
});

// Configuración Personal
Route::prefix('settings')->name('operator.settings.')->group(function () {
    Route::get('/', [DashboardController::class, 'settings'])->name('index');
    Route::put('/profile', [DashboardController::class, 'updateProfile'])->name('update-profile');
    Route::put('/password', [DashboardController::class, 'updatePassword'])->name('update-password');
    Route::put('/preferences', [DashboardController::class, 'updatePreferences'])->name('update-preferences');
    Route::put('/notifications', [DashboardController::class, 'updateNotifications'])->name('update-notifications');
});
