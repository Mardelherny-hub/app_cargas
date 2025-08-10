<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Company\DashboardController;
use App\Http\Controllers\Company\ShipmentController;
use App\Http\Controllers\Company\ShipmentItemController;
use App\Http\Controllers\Company\VoyageController;
use App\Http\Controllers\Company\OperatorController;
use App\Http\Controllers\Company\ReportController;
use App\Http\Controllers\Company\CertificateController;
use App\Http\Controllers\Company\WebServiceController as WebserviceController;
use App\Http\Controllers\Company\ImportController;
use App\Http\Controllers\Company\ExportController;
use App\Http\Controllers\Company\DeconsolidationController;
use App\Http\Controllers\Company\TransferController;
use App\Http\Controllers\Company\SettingsController;
use App\Http\Controllers\Company\ClientController;
use App\Http\Controllers\Company\VesselOwnerController;
use App\Http\Controllers\Company\VesselController;
use App\Http\Controllers\Company\BillOfLadingController;
use App\Http\Controllers\Company\CaptainController;
// ImporterController para KLine.DAT
use App\Http\Controllers\Company\ImporterController;
use App\Http\Controllers\Company\Manifests\ManifestController;
use App\Http\Controllers\Company\Manifests\ManifestImportController;
use App\Http\Controllers\Company\Manifests\ManifestExportController;
use App\Http\Controllers\Company\Manifests\ManifestCustomsController;


/*
|--------------------------------------------------------------------------
| Company Routes
|--------------------------------------------------------------------------
|
| Rutas para Administradores de Empresa y Usuarios regulares.
| Middleware aplicado: ['auth', 'verified', 'role:company-admin|user', 'CompanyAccess']
|
| Los permisos específicos se manejan dentro de cada controlador
| basándose en el rol del usuario y los roles de empresa.
|
*/

// Dashboard de Empresa
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

    // Búsqueda y filtros
    Route::get('/search', [ShipmentController::class, 'search'])->name('search');
    Route::post('/search', [ShipmentController::class, 'searchResults'])->name('search-results');

    // Historial y seguimiento
    Route::get('/{shipment}/history', [ShipmentController::class, 'history'])->name('history');
    Route::get('/{shipment}/tracking', [ShipmentController::class, 'tracking'])->name('tracking');
});

// Gestión de Items de Cargas
Route::prefix('shipment-items')->name('company.shipment-items.')->group(function () {
    Route::get('/create', [ShipmentItemController::class, 'create'])->name('create');
    Route::post('/', [ShipmentItemController::class, 'store'])->name('store');
    Route::get('/{shipmentItem}', [ShipmentItemController::class, 'show'])->name('show');
    Route::get('/{shipmentItem}/edit', [ShipmentItemController::class, 'edit'])->name('edit');
    Route::put('/{shipmentItem}', [ShipmentItemController::class, 'update'])->name('update');
    Route::delete('/{shipmentItem}', [ShipmentItemController::class, 'destroy'])->name('destroy');
    
    // Acciones específicas de items
    Route::patch('/{shipmentItem}/toggle-status', [ShipmentItemController::class, 'toggleStatus'])->name('toggle-status');
    Route::post('/{shipmentItem}/duplicate', [ShipmentItemController::class, 'duplicate'])->name('duplicate');
    
    // Búsqueda y filtros
    Route::get('/search', [ShipmentItemController::class, 'search'])->name('search');
    Route::post('/search', [ShipmentItemController::class, 'searchResults'])->name('search-results');
});

// Gestión de Conocimientos de Embarque
Route::prefix('bills-of-lading')->name('company.bills-of-lading.')->group(function () {
    // CRUD básico
    Route::get('/', [BillOfLadingController::class, 'index'])->name('index');
    Route::get('/create', [BillOfLadingController::class, 'create'])->name('create');
    Route::post('/', [BillOfLadingController::class, 'store'])->name('store');
    Route::get('/{bill_of_lading}', [BillOfLadingController::class, 'show'])->name('show');
    Route::get('/{bill_of_lading}/edit', [BillOfLadingController::class, 'edit'])->name('edit');
    Route::put('/{bill_of_lading}', [BillOfLadingController::class, 'update'])->name('update');
    Route::delete('/{bill_of_lading}', [BillOfLadingController::class, 'destroy'])->name('destroy');

    // Acciones específicas de conocimientos
    Route::patch('/{bill_of_lading}/verify', [BillOfLadingController::class, 'verify'])->name('verify');
    Route::patch('/{bill_of_lading}/status', [BillOfLadingController::class, 'updateStatus'])->name('update-status');
    Route::post('/{bill_of_lading}/duplicate', [BillOfLadingController::class, 'duplicate'])->name('duplicate');
    
    // Documentos y reportes
    Route::get('/{bill_of_lading}/pdf', [BillOfLadingController::class, 'generatePdf'])->name('pdf');
    Route::get('/{bill_of_lading}/print', [BillOfLadingController::class, 'print'])->name('print');
    
    // Adjuntos
    Route::get('/{bill_of_lading}/attachments', [BillOfLadingController::class, 'attachments'])->name('attachments');
    Route::post('/{bill_of_lading}/attachments', [BillOfLadingController::class, 'uploadAttachment'])->name('upload-attachment');
    Route::delete('/{bill_of_lading}/attachments/{attachment}', [BillOfLadingController::class, 'deleteAttachment'])->name('delete-attachment');

    // Búsqueda y filtros
    Route::get('/search', [BillOfLadingController::class, 'search'])->name('search');
    Route::post('/search', [BillOfLadingController::class, 'searchResults'])->name('search-results');
    
    // Exportación
    Route::post('/export', [BillOfLadingController::class, 'export'])->name('export');
    Route::get('/export/{format}', [BillOfLadingController::class, 'exportByFormat'])->name('export-format');

    // Historial y auditoría
    Route::get('/{bill_of_lading}/history', [BillOfLadingController::class, 'history'])->name('history');
    Route::get('/{bill_of_lading}/audit', [BillOfLadingController::class, 'auditLog'])->name('audit');
});

// Gestión de Viajes
Route::prefix('voyages')->name('company.voyages.')->group(function () {
    Route::get('/', [VoyageController::class, 'index'])->name('index');
    Route::get('/create', [VoyageController::class, 'create'])->name('create');
    Route::post('/', [VoyageController::class, 'store'])->name('store');
    Route::get('/{voyage}', [VoyageController::class, 'show'])->name('show');
    Route::get('/{voyage}/edit', [VoyageController::class, 'edit'])->name('edit');
    Route::put('/{voyage}', [VoyageController::class, 'update'])->name('update');
    Route::delete('/{voyage}', [VoyageController::class, 'destroy'])->name('destroy');

    // Acciones específicas de viajes
    Route::patch('/{voyage}/status', [VoyageController::class, 'updateStatus'])->name('update-status');
    Route::patch('/{voyage}/close', [VoyageController::class, 'close'])->name('close');
    Route::post('/{voyage}/duplicate', [VoyageController::class, 'duplicate'])->name('duplicate');
    Route::get('/{voyage}/pdf', [VoyageController::class, 'generatePdf'])->name('pdf');

    // Manifiestos
    Route::get('/{voyage}/manifest', [VoyageController::class, 'manifest'])->name('manifest');
    Route::get('/{voyage}/manifest/pdf', [VoyageController::class, 'manifestPdf'])->name('manifest-pdf');

    // Contenedores
    Route::get('/{voyage}/containers', [VoyageController::class, 'containers'])->name('containers');
    Route::post('/{voyage}/containers', [VoyageController::class, 'addContainer'])->name('add-container');
    Route::delete('/{voyage}/containers/{container}', [VoyageController::class, 'removeContainer'])->name('remove-container');
});

// Gestión de Propietarios de Embarcaciones
Route::prefix('vessel-owners')->name('company.vessel-owners.')->group(function () {
    Route::get('/', [VesselOwnerController::class, 'index'])->name('index');
    Route::get('/create', [VesselOwnerController::class, 'create'])->name('create');
    Route::post('/', [VesselOwnerController::class, 'store'])->name('store');
    Route::get('/{vesselOwner}', [VesselOwnerController::class, 'show'])->name('show');
    Route::get('/{vesselOwner}/edit', [VesselOwnerController::class, 'edit'])->name('edit');
    Route::put('/{vesselOwner}', [VesselOwnerController::class, 'update'])->name('update');
    Route::delete('/{vesselOwner}', [VesselOwnerController::class, 'destroy'])->name('destroy');
    
    // Acciones específicas
    Route::patch('/{vesselOwner}/toggle-status', [VesselOwnerController::class, 'toggleStatus'])->name('toggle-status');
    Route::get('/{vesselOwner}/vessels', [VesselOwnerController::class, 'vessels'])->name('vessels');
});

// Gestión de Embarcaciones
Route::prefix('vessels')->name('company.vessels.')->group(function () {
    Route::get('/', [VesselController::class, 'index'])->name('index');
    Route::get('/create', [VesselController::class, 'create'])->name('create');
    Route::post('/', [VesselController::class, 'store'])->name('store');
    Route::get('/{vessel}', [VesselController::class, 'show'])->name('show');
    Route::get('/{vessel}/edit', [VesselController::class, 'edit'])->name('edit');
    Route::put('/{vessel}', [VesselController::class, 'update'])->name('update');
    Route::delete('/{vessel}', [VesselController::class, 'destroy'])->name('destroy');
    
    // Acciones específicas
    Route::patch('/{vessel}/toggle-status', [VesselController::class, 'toggleStatus'])->name('toggle-status');
    Route::patch('/{vessel}/toggle-operational-status', [VesselController::class, 'toggleStatus'])->name('toggle-operational-status');
});


// Gestión de Desconsolidación (solo si la empresa tiene rol "Desconsolidador")
Route::prefix('deconsolidation')->name('company.deconsolidation.')->group(function () {
    Route::get('/', [DeconsolidationController::class, 'index'])->name('index');
    Route::get('/create', [DeconsolidationController::class, 'create'])->name('create');
    Route::post('/', [DeconsolidationController::class, 'store'])->name('store');
    Route::get('/{deconsolidation}', [DeconsolidationController::class, 'show'])->name('show');
    Route::get('/{deconsolidation}/edit', [DeconsolidationController::class, 'edit'])->name('edit');
    Route::put('/{deconsolidation}', [DeconsolidationController::class, 'update'])->name('update');
    Route::delete('/{deconsolidation}', [DeconsolidationController::class, 'destroy'])->name('destroy');

    // Acciones específicas de desconsolidación
    Route::patch('/{deconsolidation}/status', [DeconsolidationController::class, 'updateStatus'])->name('update-status');
    Route::get('/{deconsolidation}/pdf', [DeconsolidationController::class, 'generatePdf'])->name('pdf');
});

// Gestión de Transbordos (solo si la empresa tiene rol "Transbordos")
Route::prefix('transfers')->name('company.transfers.')->group(function () {
    Route::get('/', [TransferController::class, 'index'])->name('index');
    Route::get('/create', [TransferController::class, 'create'])->name('create');
    Route::post('/', [TransferController::class, 'store'])->name('store');
    Route::get('/{transfer}', [TransferController::class, 'show'])->name('show');
    Route::get('/{transfer}/edit', [TransferController::class, 'edit'])->name('edit');
    Route::put('/{transfer}', [TransferController::class, 'update'])->name('update');
    Route::delete('/{transfer}', [TransferController::class, 'destroy'])->name('destroy');

    // Acciones específicas de transbordos
    Route::patch('/{transfer}/status', [TransferController::class, 'updateStatus'])->name('update-status');
    Route::get('/{transfer}/pdf', [TransferController::class, 'generatePdf'])->name('pdf');
});

// Gestión de Operadores (solo company-admin)
Route::prefix('operators')->name('company.operators.')->group(function () {
    Route::get('/', [OperatorController::class, 'index'])->name('index');
    Route::get('/create', [OperatorController::class, 'create'])->name('create');
    Route::post('/', [OperatorController::class, 'store'])->name('store');
    Route::get('/{operator}', [OperatorController::class, 'show'])->name('show');
    Route::get('/{operator}/edit', [OperatorController::class, 'edit'])->name('edit');
    Route::put('/{operator}', [OperatorController::class, 'update'])->name('update');
    Route::delete('/{operator}', [OperatorController::class, 'destroy'])->name('destroy');

    // Acciones específicas de operadores
    Route::patch('/{operator}/toggle-status', [OperatorController::class, 'toggleStatus'])->name('toggle-status');
    Route::put('/{operator}/permissions', [OperatorController::class, 'updatePermissions'])->name('update-permissions');
});

// Importación/Exportación (según permisos del operador)
Route::prefix('import')->name('company.import.')->group(function () {
    Route::get('/', [ImportController::class, 'index'])->name('index');
    Route::get('/excel', [ImportController::class, 'excel'])->name('excel');
    Route::post('/excel', [ImportController::class, 'processExcel'])->name('process-excel');
    Route::get('/xml', [ImportController::class, 'xml'])->name('xml');
    Route::post('/xml', [ImportController::class, 'processXml'])->name('process-xml');
    Route::get('/edi', [ImportController::class, 'edi'])->name('edi');
    Route::post('/edi', [ImportController::class, 'processEdi'])->name('process-edi');

    // Historial de importaciones
    Route::get('/history', [ImportController::class, 'history'])->name('history');
    Route::get('/history/{import}', [ImportController::class, 'showImport'])->name('show-import');
});

Route::prefix('export')->name('company.export.')->group(function () {
    Route::get('/', [ExportController::class, 'index'])->name('index');
    Route::get('/excel', [ExportController::class, 'excel'])->name('excel');
    Route::post('/excel', [ExportController::class, 'generateExcel'])->name('generate-excel');
    Route::get('/xml', [ExportController::class, 'xml'])->name('xml');
    Route::post('/xml', [ExportController::class, 'generateXml'])->name('generate-xml');
    Route::get('/edi', [ExportController::class, 'edi'])->name('edi');
    Route::post('/edi', [ExportController::class, 'generateEdi'])->name('generate-edi');

    // Historial de exportaciones
    Route::get('/history', [ExportController::class, 'history'])->name('history');
    Route::get('/history/{export}', [ExportController::class, 'showExport'])->name('show-export');
});

// Webservices
Route::prefix('webservices')->name('company.webservices.')->group(function () {
    Route::get('/', [WebserviceController::class, 'index'])->name('index');
    Route::get('/send', [WebserviceController::class, 'send'])->name('send');
    Route::post('/send', [WebserviceController::class, 'processSend'])->name('process-send');
    Route::get('/query', [WebserviceController::class, 'query'])->name('query');
    Route::post('/query', [WebserviceController::class, 'processQuery'])->name('process-query');
    Route::get('/rectify', [WebserviceController::class, 'rectify'])->name('rectify');
    Route::post('/rectify', [WebserviceController::class, 'processRectify'])->name('process-rectify');
    Route::get('/cancel', [WebserviceController::class, 'cancel'])->name('cancel');
    Route::post('/cancel', [WebserviceController::class, 'processCancel'])->name('process-cancel');

    // Historial de webservices
    Route::get('/history', [WebserviceController::class, 'history'])->name('history');
    Route::get('/history/{webservice}', [WebserviceController::class, 'showWebservice'])->name('show-webservice');

   // Acciones adicionales del historial
    Route::post('/retry/{webservice}', [WebserviceController::class, 'retryTransaction'])->name('retry-transaction');
    Route::post('/send-pending/{webservice}', [WebserviceController::class, 'processPendingTransaction'])->name('send-pending-transaction');
    Route::post('/export', [WebserviceController::class, 'export'])->name('export');
    
    // Descargas - NUEVAS RUTAS
    Route::get('/download/{webservice}/xml', [WebserviceController::class, 'downloadXml'])->name('download-xml');
    Route::get('/download/{webservice}/pdf', [WebserviceController::class, 'downloadPdf'])->name('download-pdf');

    // Datos PARANA para autocompletar (AJAX)
    Route::get('/parana-data', [WebserviceController::class, 'getParanaData'])->name('parana-data');

    // Importación de manifiestos
    Route::get('/import', [WebserviceController::class, 'showImport'])->name('import');
    Route::post('/import', [WebserviceController::class, 'importManifest'])->name('process-import');

});

// Reportes
Route::prefix('reports')->name('company.reports.')->group(function () {
    Route::get('/', [ReportController::class, 'index'])->name('index');
    Route::get('/manifests', [ReportController::class, 'manifests'])->name('manifests');
    Route::get('/bills-of-lading', [ReportController::class, 'billsOfLading'])->name('bills-of-lading');
    Route::get('/micdta', [ReportController::class, 'micdta'])->name('micdta');
    Route::get('/arrival-notices', [ReportController::class, 'arrivalNotices'])->name('arrival-notices');
    Route::get('/customs', [ReportController::class, 'customs'])->name('customs');
    Route::get('/shipments', [ReportController::class, 'shipments'])->name('shipments');
    Route::get('/voyages', [ReportController::class, 'voyages'])->name('voyages');
    Route::get('/operators', [ReportController::class, 'operators'])->name('operators');
    Route::get('/deconsolidation', [ReportController::class, 'deconsolidation'])->name('deconsolidation');
    Route::get('/transshipment', [ReportController::class, 'transshipment'])->name('transshipment');

    // Exportación de reportes
    Route::post('/export/{report}', [ReportController::class, 'export'])->name('export');
});

// Certificados (solo company-admin)
Route::prefix('certificates')->name('company.certificates.')->group(function () {
    Route::get('/', [CertificateController::class, 'index'])->name('index');
    Route::get('/upload', [CertificateController::class, 'upload'])->name('upload');
    Route::post('/upload', [CertificateController::class, 'processUpload'])->name('process-upload');
    Route::get('/{certificate}', [CertificateController::class, 'show'])->name('show');
    Route::delete('/{certificate}', [CertificateController::class, 'destroy'])->name('destroy');

    // Renovación de certificados
    Route::get('/{certificate}/renew', [CertificateController::class, 'renew'])->name('renew');
    Route::post('/{certificate}/renew', [CertificateController::class, 'processRenew'])->name('process-renew');
});

// Gestión de Clientes (base compartida) - ORDEN CORREGIDO
Route::prefix('clients')->name('company.clients.')->group(function () {
    
    // 1. PRIMERO: Rutas específicas (sin parámetros)
    Route::get('/', [ClientController::class, 'index'])->name('index');
    Route::get('/search', [ClientController::class, 'search'])->name('search');
    Route::get('/suggestions', [ClientController::class, 'suggestions'])->name('suggestions');
    Route::post('/validate-tax-id', [ClientController::class, 'validateTaxId'])->name('validate-tax-id');
    
    // 2. Rutas SOLO para company-admin (específicas)
    Route::middleware(['role:company-admin'])->group(function () {
        Route::get('/create', [ClientController::class, 'create'])->name('create');
        Route::post('/', [ClientController::class, 'store'])->name('store');
    });
    
    // 3. DESPUÉS: Rutas con parámetros {client}
    Route::get('/{client}', [ClientController::class, 'show'])->name('show');
    Route::get('/{client}/contacts', [ClientController::class, 'contacts'])->name('contacts');
    
    // 4. Más rutas SOLO para company-admin (con parámetros)
    Route::middleware(['role:company-admin'])->group(function () {
        Route::get('/{client}/edit', [ClientController::class, 'edit'])->name('edit');
        Route::put('/{client}', [ClientController::class, 'update'])->name('update');
        Route::delete('/{client}', [ClientController::class, 'destroy'])->name('destroy');
        Route::post('/{client}/contacts', [ClientController::class, 'storeContact'])->name('store-contact');
        Route::put('/{client}/contacts/{contact}', [ClientController::class, 'updateContact'])->name('update-contact');
        Route::delete('/{client}/contacts/{contact}', [ClientController::class, 'destroyContact'])->name('destroy-contact');
        Route::patch('/{client}/toggle-status', [ClientController::class, 'toggleStatus'])->name('toggle-status');
    });
});


// =============================================================================
// RUTAS DE MANIFIESTOS - ORDEN CORREGIDO PARA EVITAR CONFLICTOS
// =============================================================================

Route::prefix('manifests')->name('company.manifests.')->group(function () {
    
    // === RUTAS ESPECÍFICAS PRIMERO (antes de rutas con parámetros) ===
    Route::get('/', [ManifestController::class, 'index'])->name('index');
    Route::get('/create', [ManifestController::class, 'create'])->name('create');
    Route::get('/summary', [ManifestController::class, 'summary'])->name('summary');
    Route::get('/reports', [ManifestController::class, 'reports'])->name('reports');
    Route::post('/', [ManifestController::class, 'store'])->name('store');

    // === IMPORTACIÓN - ANTES DE RUTAS CON PARÁMETROS ===
    Route::prefix('import')->name('import.')->group(function () {
        Route::get('/', [ManifestImportController::class, 'showForm'])->name('index');
        Route::post('/', [ManifestImportController::class, 'store'])->name('store');
        Route::get('/history', [ManifestImportController::class, 'history'])->name('history');
    });

    // === EXPORTACIÓN - ANTES DE RUTAS CON PARÁMETROS ===
    Route::prefix('export')->name('export.')->group(function () {
        Route::get('/', [ManifestExportController::class, 'index'])->name('index');
        Route::get('/{voyageId}/parana', [ManifestExportController::class, 'exportParana'])->name('parana');
        Route::get('/{voyageId}/guaran', [ManifestExportController::class, 'exportGuaran'])->name('guaran');
        Route::get('/{voyageId}/login', [ManifestExportController::class, 'exportLogin'])->name('login');
        Route::get('/{voyageId}/tfp', [ManifestExportController::class, 'exportTfp'])->name('tfp');
        Route::get('/{voyageId}/edi', [ManifestExportController::class, 'exportEdi'])->name('edi');
    });

    // === ENVÍO A ADUANA - ANTES DE RUTAS CON PARÁMETROS ===
    Route::prefix('customs')->name('customs.')->group(function () {
        Route::get('/', [ManifestCustomsController::class, 'index'])->name('index');
            Route::post('/send-batch', [ManifestCustomsController::class, 'sendBatch'])->name('sendBatch'); 
        Route::post('/{voyageId}/send', [ManifestCustomsController::class, 'send'])->name('send');
        Route::get('/{transactionId}/status', [ManifestCustomsController::class, 'status'])->name('status');
        Route::post('/{transactionId}/retry', [ManifestCustomsController::class, 'retry'])->name('retry');
    });

    // === RUTAS CON PARÁMETROS AL FINAL (para evitar conflictos) ===
    Route::get('/{id}', [ManifestController::class, 'show'])->name('show');
    Route::get('/{id}/edit', [ManifestController::class, 'edit'])->name('edit');
    Route::put('/{id}', [ManifestController::class, 'update'])->name('update');
    Route::delete('/{id}', [ManifestController::class, 'destroy'])->name('destroy');
});


// Gestión de Capitanes
Route::prefix('captains')->name('company.captains.')->group(function () {
    // 1. RUTAS ESPECÍFICAS PRIMERO (sin parámetros)
    Route::get('/', [CaptainController::class, 'index'])->name('index');
    Route::get('/search', [CaptainController::class, 'search'])->name('search');
    Route::post('/search', [CaptainController::class, 'searchResults'])->name('search-results');
    
    // 2. RUTAS SOLO PARA COMPANY-ADMIN (sin parámetros)
    Route::middleware(['role:company-admin'])->group(function () {
        Route::get('/create', [CaptainController::class, 'create'])->name('create');
        Route::post('/', [CaptainController::class, 'store'])->name('store');
        Route::post('/import', [CaptainController::class, 'import'])->name('import');
        Route::get('/export', [CaptainController::class, 'export'])->name('export');
    });

    // 3. RUTAS CON PARÁMETROS {captain} - Acceso general
    Route::get('/{captain}', [CaptainController::class, 'show'])->name('show');
    Route::get('/{captain}/statistics', [CaptainController::class, 'statistics'])->name('statistics');
    Route::get('/{captain}/voyages', [CaptainController::class, 'voyages'])->name('voyages');
    Route::get('/{captain}/vessels', [CaptainController::class, 'vessels'])->name('vessels');

    // 4. RUTAS CON PARÁMETROS {captain} - SOLO COMPANY-ADMIN
    Route::middleware(['role:company-admin'])->group(function () {
        Route::get('/{captain}/edit', [CaptainController::class, 'edit'])->name('edit');
        Route::put('/{captain}', [CaptainController::class, 'update'])->name('update');
        Route::delete('/{captain}', [CaptainController::class, 'destroy'])->name('destroy');
        Route::patch('/{captain}/toggle-status', [CaptainController::class, 'toggleStatus'])->name('toggle-status');
        Route::patch('/{captain}/assign-company', [CaptainController::class, 'assignToCompany'])->name('assign-company');
        Route::patch('/{captain}/update-performance', [CaptainController::class, 'updatePerformance'])->name('update-performance');
    });

    // 5. RUTAS DE REPORTES Y DOCUMENTOS
    Route::get('/{captain}/pdf', [CaptainController::class, 'generatePdf'])->name('pdf');
    Route::get('/{captain}/certificates', [CaptainController::class, 'certificates'])->name('certificates');
    Route::get('/{captain}/performance-report', [CaptainController::class, 'performanceReport'])->name('performance-report');

    // 6. RUTAS DE CERTIFICADOS Y DOCUMENTOS - SOLO COMPANY-ADMIN
    Route::middleware(['role:company-admin'])->group(function () {
        Route::post('/{captain}/certificates', [CaptainController::class, 'uploadCertificate'])->name('upload-certificate');
        Route::delete('/{captain}/certificates/{certificate}', [CaptainController::class, 'deleteCertificate'])->name('delete-certificate');
        Route::patch('/{captain}/certificates/{certificate}/verify', [CaptainController::class, 'verifyCertificate'])->name('verify-certificate');
    });
});

// Configuración (solo company-admin)
Route::prefix('settings')->name('company.settings.')->group(function () {
    Route::get('/', [SettingsController::class, 'index'])->name('index');
    Route::put('/general', [SettingsController::class, 'updateGeneral'])->name('update-general');
    Route::put('/security', [SettingsController::class, 'updateSecurity'])->name('update-security');
    Route::put('/notifications', [SettingsController::class, 'updateNotifications'])->name('update-notifications');
    Route::put('/preferences', [SettingsController::class, 'updatePreferences'])->name('update-preferences');
});

// Importación de archivos KLine.DAT
Route::get('/imports/kline', [ImporterController::class, 'showForm'])->name('company.imports.kline');
Route::post('/imports/kline', [ImporterController::class, 'import'])->name('company.imports.kline');



