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
use App\Http\Controllers\Company\DashboardEstadosController;
use App\Http\Controllers\Company\Manifests\TestingCustomsController;
use App\Http\Controllers\Company\ManeFileController;
use App\Http\Controllers\Company\VoyageWizardController;
use App\Http\Controllers\Company\Simple\SimpleManifestController;

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
    Route::get('/{bill_of_lading}/check-items', [BillOfLadingController::class, 'checkItems'])->name('check-items');
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
    Route::get('/{bill_of_lading}/template', [BillOfLadingController::class, 'downloadTemplate'])->name('template');
    Route::post('/{bill_of_lading}/import-items', [BillOfLadingController::class, 'importItems'])->name('import-items');

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
    // === WIZARD PARA CREAR VIAJES COMPLETOS ===
    Route::prefix('wizard')->name('wizard.')->group(function () {
        Route::get('/', [VoyageWizardController::class, 'index'])->name('index');
        Route::match(['GET', 'POST'], '/step1', [VoyageWizardController::class, 'step1'])->name('step1');
        Route::match(['GET', 'POST'], '/step2', [VoyageWizardController::class, 'step2'])->name('step2');
        Route::match(['GET', 'POST'], '/step3', [VoyageWizardController::class, 'step3'])->name('step3');
        Route::match(['GET', 'POST'], '/step4', [VoyageWizardController::class, 'step4'])->name('step4');
        Route::post('/cancel', [VoyageWizardController::class, 'cancel'])->name('cancel');
    });

    // CRUD básico
    Route::get('/', [VoyageController::class, 'index'])->name('index');
    Route::get('/create', [VoyageController::class, 'create'])->name('create');
    Route::post('/', [VoyageController::class, 'store'])->name('store');
    Route::get('/{voyage}', [VoyageController::class, 'show'])->name('show');
    Route::get('/{voyage}/detail', [VoyageController::class, 'showDetail'])->name('detail');
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

    // Validador
    Route::post('/{voyage}/validate-customs', [VoyageController::class, 'validateForCustoms'])->name('validate-customs');
});


// ========================================
// WIZARD DE VIAJES COMPLETOS - INDEPENDIENTE
// ========================================
Route::prefix('voyage-wizard')->name('voyage-wizard.')->group(function () {
    // PASO 1: Datos del Viaje
    Route::get('/step1', [VoyageWizardController::class, 'step1'])->name('step1');
    Route::post('/step1', [VoyageWizardController::class, 'storeStep1'])->name('store-step1');
    
    // PASO 2: Conocimientos de Embarque  
    Route::get('/step2', [VoyageWizardController::class, 'step2'])->name('step2');
    Route::post('/step2', [VoyageWizardController::class, 'storeStep2'])->name('store-step2');
    
    // PASO 3: Mercadería y Contenedores
    Route::get('/step3', [VoyageWizardController::class, 'step3'])->name('step3');
    Route::post('/step3', [VoyageWizardController::class, 'storeStep3'])->name('store-step3');
    
    // UTILIDADES
    Route::get('/cancel', [VoyageWizardController::class, 'cancel'])->name('cancel');
    Route::get('/summary', [VoyageWizardController::class, 'summary'])->name('summary');
    Route::get('/', [VoyageWizardController::class, 'start'])->name('start'); // Página de inicio
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


// Webservices - USAR WebserviceController (sin 'S')
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

    Route::get('/dashboard', [WebserviceController::class, 'dashboard'])->name('dashboard');
    
    // NUEVAS RUTAS para detalles de transacciones
    Route::get('/transaction/{id}', [WebserviceController::class, 'getTransactionDetails'])
          ->name('transaction')
          ->where('id', '[0-9]+');
          
    Route::get('/transaction/{id}/xml/{type}', [WebserviceController::class, 'getTransactionXML'])
          ->name('transaction.xml')
          ->where('id', '[0-9]+')
          ->where('type', 'request|response');
          
    Route::post('/transaction/{id}/retry', [WebserviceController::class, 'retryTransaction'])
          ->name('transaction.retry')
          ->where('id', '[0-9]+');

    // Acciones adicionales del historial
    Route::post('/retry/{webservice}', [WebserviceController::class, 'retryTransaction'])->name('retry-transaction');
    Route::post('/send-pending/{webservice}', [WebserviceController::class, 'processPendingTransaction'])->name('send-pending-transaction');
    Route::post('/export', [WebserviceController::class, 'export'])->name('export');
    
    // Descargas
    Route::get('/download/{webservice}/xml', [WebserviceController::class, 'downloadXml'])->name('download-xml');
    Route::get('/download/{webservice}/pdf', [WebserviceController::class, 'downloadPdf'])->name('download-pdf');

    // Datos PARANA para autocompletar (AJAX)
    Route::get('/parana-data', [WebserviceController::class, 'getParanaData'])->name('parana-data');

    // Importación de manifiestos - REDIRECCIÓN
    Route::get('/import', function() {
        return redirect()->route('company.manifests.import.index')
            ->with('info', 'Use este formulario para importar manifiestos en los formatos soportados.');
    })->name('import');

    Route::post('/import', function(Request $request) {
        return redirect()->route('company.manifests.import.store')
            ->withInput($request->all());
    })->name('process-import');
});

// Reportes
Route::prefix('reports')->name('company.reports.')->group(function () {
    Route::get('/', [ReportController::class, 'index'])->name('index');
    Route::get('/manifests', [ReportController::class, 'manifests'])->name('manifests');
    Route::get('/bills-of-lading', [ReportController::class, 'billsOfLading'])->name('bills-of-lading');
    Route::get('/micdta', [ReportController::class, 'micdta'])->name('micdta');
    Route::get('/customs-manifest', [ReportController::class, 'customsManifest'])->name('customs-manifest');
    Route::get('/arrival-notices', [ReportController::class, 'arrivalNotices'])->name('arrival-notices');
    // ⭐ NUEVA RUTA AJAX: Obtener consignatarios de un viaje
    Route::get('/arrival-notices/consignees', [ReportController::class, 'getVoyageConsignees'])->name('arrival-notices.consignees');

    Route::get('/customs', [ReportController::class, 'customs'])->name('customs');
    Route::get('/shipments', [ReportController::class, 'shipments'])->name('shipments');
    Route::get('/voyages', [ReportController::class, 'voyages'])->name('voyages');
    Route::get('/operators', [ReportController::class, 'operators'])->name('operators');
    Route::get('/deconsolidation', [ReportController::class, 'deconsolidation'])->name('deconsolidation');
    Route::get('/transshipment', [ReportController::class, 'transshipment'])->name('transshipment');

    // Exportación de reportes
    Route::post('/export/{report}', [ReportController::class, 'export'])->name('export');

});

// ========================================
// CERTIFICADOS DIGITALES (solo company-admin)
// ========================================
Route::prefix('certificates')->name('company.certificates.')->middleware(['role:company-admin'])->group(function () {
    // Vista principal de certificados
    Route::get('/', [CertificateController::class, 'index'])->name('index');
    
    // Subida de certificados
    Route::get('/upload', [CertificateController::class, 'upload'])->name('upload');
    Route::post('/upload', [CertificateController::class, 'processUpload'])->name('process-upload');
    
    // Ver detalles del certificado actual de la empresa (SIN parámetro)
    Route::get('/details', [CertificateController::class, 'show'])->name('show');
    
    // Eliminar certificado actual de la empresa (SIN parámetro) 
    Route::delete('/delete', [CertificateController::class, 'destroy'])->name('destroy');
    
    // Renovación de certificados (SIN parámetro)
    Route::get('/renew', [CertificateController::class, 'renew'])->name('renew');
    Route::post('/renew', [CertificateController::class, 'processRenew'])->name('process-renew');

    // NUEVA RUTA PARA GENERAR CERTIFICADO DE TESTING
    Route::post('/generate-test', [CertificateController::class, 'generateTestCertificate'])->name('generate-test');

    // NUEVA RUTA PARA TESTING COMPLETO
    Route::post('/test', [CertificateController::class, 'testCertificate'])->name('test');

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
        Route::get('/report', [ManifestImportController::class, 'showReport'])->name('report');
        Route::get('/history/{import}', [ManifestImportController::class, 'showImport'])->name('show');
        Route::post('/{import}/revert', [ManifestImportController::class, 'revert'])->name('revert');
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

    // === ENVÍO A ADUANA - Actualizar para incluir MANE ===
    Route::prefix('customs')->name('customs.')->group(function () {
    // Rutas existentes
    Route::get('/', [ManifestCustomsController::class, 'index'])->name('index');
    Route::get('/debug', [ManifestCustomsController::class, 'debug'])->name('debug');
    Route::post('/send-batch', [ManifestCustomsController::class, 'sendBatch'])->name('sendBatch');
    Route::post('/{voyageId}/send', [ManifestCustomsController::class, 'send'])->name('send');
    // ✅ NUEVAS: Rutas para proceso paso a paso MIC/DTA
    Route::post('/{voyageId}/send-step1', [ManifestCustomsController::class, 'sendStep1'])->name('send-step1');
    Route::post('/{voyageId}/send-step2', [ManifestCustomsController::class, 'sendStep2'])->name('send-step2');
    Route::get('/{transactionId}/status', [ManifestCustomsController::class, 'status'])->name('status');
    Route::post('/{transactionId}/retry', [ManifestCustomsController::class, 'retry'])->name('retry');
    Route::get('/voyage/{voyageId}/statuses', [ManifestCustomsController::class, 'voyageStatuses'])->name('voyage-statuses');
    
    // NUEVO: Ruta específica para vista MANE
    Route::get('/mane', [ManifestCustomsController::class, 'maneIndex'])->name('mane');

    Route::get('/{voyage}/attachments', [ManifestCustomsController::class, 'attachmentsIndex'])->name('attachments');
    Route::get('/{voyage}/attachments-list', [ManifestCustomsController::class, 'getAttachmentsList'])->name('attachments-list');
    Route::post('/{voyage}/upload-attachments', [ManifestCustomsController::class, 'uploadAttachments'])->name('upload-attachments');
    Route::get('/attachments/{attachment}/download', [ManifestCustomsController::class, 'downloadAttachment'])->name('attachment-download');
    Route::delete('/attachments/{attachment}', [ManifestCustomsController::class, 'deleteAttachment'])->name('attachment-delete');
});

    // === 🧪 TESTING DE ENVÍOS A ADUANA - NUEVA SECCIÓN ===
    Route::prefix('testing')->name('testing.')->group(function () {
        Route::get('/', [TestingCustomsController::class, 'index'])->name('index');
        Route::post('/{voyageId}/test', [TestingCustomsController::class, 'test'])->name('test');
        Route::post('/test-batch', [TestingCustomsController::class, 'testBatch'])->name('testBatch');
        Route::get('/results/{testId}', [TestingCustomsController::class, 'showResults'])->name('results');
        Route::post('/export-results', [TestingCustomsController::class, 'exportResults'])->name('exportResults');
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

// Generación de archivos MANE/Malvina (solo empresas con rol "Cargas")
    Route::prefix('mane')->name('company.mane.')->group(function () {
        Route::get('/', [ManeFileController::class, 'index'])->name('index');
        Route::post('/voyage/{voyage}', [ManeFileController::class, 'generateForVoyage'])->name('generate-voyage');
        Route::post('/consolidated', [ManeFileController::class, 'generateConsolidated'])->name('generate-consolidated');
        Route::get('/download/{filename}', [ManeFileController::class, 'download'])->name('download');
    });



// ========================================
// DASHBOARD DE ESTADOS 
// ========================================
Route::prefix('dashboard-estados')->name('company.dashboard-estados.')->group(function () {
    // Vista principal del dashboard
    Route::get('/', [DashboardEstadosController::class, 'index'])->name('index');
    
    // API endpoints para datos dinámicos
    Route::get('/api/metrics', [DashboardEstadosController::class, 'getMetrics'])->name('api.metrics');
    Route::get('/api/recent-changes', [DashboardEstadosController::class, 'getRecentChanges'])->name('api.recent-changes');
    Route::get('/api/status-distribution', [DashboardEstadosController::class, 'getStatusDistribution'])->name('api.status-distribution');
    
    // Acciones rápidas para cambios de estado
    Route::post('/bulk-update', [DashboardEstadosController::class, 'bulkUpdateStatus'])->name('bulk-update');
    Route::get('/export', [DashboardEstadosController::class, 'exportStatusReport'])->name('export');
});



// Configuración (solo company-admin)
Route::prefix('settings')->name('company.settings.')->group(function () {
    Route::get('/', [SettingsController::class, 'index'])->name('index');
    Route::put('/general', [SettingsController::class, 'updateGeneral'])->name('update-general');
    Route::put('/security', [SettingsController::class, 'updateSecurity'])->name('update-security');
    Route::put('/notifications', [SettingsController::class, 'updateNotifications'])->name('update-notifications');
    Route::put('/preferences', [SettingsController::class, 'updatePreferences'])->name('update-preferences');
        Route::put('/webservices', [SettingsController::class, 'updateWebservices'])->name('update-webservices'); 
});


// Importación de archivos KLine.DAT
Route::get('/imports/kline', [ImporterController::class, 'showForm'])->name('company.imports.kline');
Route::post('/imports/kline', [ImporterController::class, 'import'])->name('company.imports.kline');



// Diagnóstico de webservices (para debugging de conectividad)
Route::get('/diagnostic/webservices', function() {
    $company = auth()->user()->getUserCompany();
    if (!$company) {
        return response()->json(['error' => 'No company found for user']);
    }
    
    try {
        $testingService = new \App\Services\Webservice\TestingCustomsService($company);
        
        // Crear un voyage dummy para el test (requerido por runCompleteTest)
        $voyage = $company->voyages()->first();
        if (!$voyage) {
            return response()->json([
                'error' => 'No voyage found for testing',
                'message' => 'Se necesita al menos un voyage para realizar el test de conectividad'
            ]);
        }
        
        // Test completo de conectividad
        $result = $testingService->runCompleteTest($voyage, [
            'environment' => 'testing',
            'webservice_type' => 'micdta'
        ]);
        
        return response()->json([
            'timestamp' => now()->toISOString(),
            'server_info' => [
                'environment' => app()->environment(),
                'php_version' => PHP_VERSION,
                'laravel_version' => app()->version(),
            ],
            'connectivity_test' => $result['connectivity_test'] ?? $result,
            'company_config' => [
                'ws_config' => $company->ws_config,
                'ws_environment' => $company->ws_environment,
                'certificate_configured' => !empty($company->certificate_path),
            ]
        ], 200, [], JSON_PRETTY_PRINT);
        
    } catch (\Exception $e) {
        
        return response()->json([
            'error' => 'Test failed',
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ], 500);
    }
   
})->name('diagnostic.webservices');


// =============================================================================
// SISTEMA MODULAR WEBSERVICES - RUTAS SIMPLES (FASE 1)
// =============================================================================

/**
 * SISTEMA SIMPLE/MODULAR DE WEBSERVICES
 * 
 * Estas rutas funcionan EN PARALELO al sistema existente de webservices.
 * Prefijo '/simple/webservices' para evitar conflictos.
 * 
 * FASE 1: MIC/DTA Argentina (PRIORITARIO)
 * FASE 2-5: Otros webservices se agregan progresivamente
 * 
 * Controlador único: SimpleManifestController maneja TODOS los webservices
 */
Route::prefix('simple/webservices')->name('company.simple.')->group(function () {
    
    // ====================================
    // DASHBOARD PRINCIPAL MODULAR
    // ====================================
    
    /**
     * Dashboard principal con selector de webservices
     * Muestra todos los voyages disponibles y estados de todos los webservices
     */
    Route::get('/', [App\Http\Controllers\Company\Simple\SimpleManifestController::class, 'dashboard'])
        ->name('dashboard');

    // ====================================
    // MIC/DTA ARGENTINA (FASE 1 - ACTIVO)
    // ====================================
    
    Route::prefix('micdta')->name('micdta.')->group(function () {
        
        /**
         * Lista de voyages disponibles para MIC/DTA Argentina
         * Filtros: estado, fecha, empresa
         */
        Route::get('/', [App\Http\Controllers\Company\Simple\SimpleManifestController::class, 'micDtaIndex'])
            ->name('index');
        
        /**
         * Formulario de envío MIC/DTA para voyage específico
         * Incluye validación, vista previa y opciones de envío
         */
        Route::get('/{voyage}', [App\Http\Controllers\Company\Simple\SimpleManifestController::class, 'micDtaShow'])
            ->name('show')
            ->whereNumber('voyage');
        
        /**
         * Procesar envío MIC/DTA (AJAX)
         * Ejecuta flujo secuencial: RegistrarTitEnvios → RegistrarMicDta
         */
        Route::post('/{voyage}/send', [App\Http\Controllers\Company\Simple\SimpleManifestController::class, 'micDtaSend'])
            ->name('send')
            ->whereNumber('voyage');

        // Agregar dentro del grupo Route::prefix('micdta')->name('micdta.')->group(function () {

        // Rutas AJAX para funcionalidad dinámica
        Route::get('/{voyage}/status', [App\Http\Controllers\Company\Simple\SimpleManifestController::class, 'micDtaStatus'])
            ->name('status')
            ->whereNumber('voyage');

        Route::get('/{voyage}/validate', [App\Http\Controllers\Company\Simple\SimpleManifestController::class, 'micDtaValidate'])
            ->name('validate')
            ->whereNumber('voyage');

        Route::get('/{voyage}/preview-xml', [App\Http\Controllers\Company\Simple\SimpleManifestController::class, 'micDtaPreviewXml'])
            ->name('preview-xml')
            ->whereNumber('voyage');

        Route::get('/{voyage}/activity', [App\Http\Controllers\Company\Simple\SimpleManifestController::class, 'micDtaActivity'])
            ->name('activity')
            ->whereNumber('voyage');

        // Rutas adicionales útiles
        Route::post('/{voyage}/resend', [App\Http\Controllers\Company\Simple\SimpleManifestController::class, 'micDtaResend'])
            ->name('resend')
            ->whereNumber('voyage');

        // Solo para desarrollo - resetear estado
        Route::delete('/{voyage}/reset', [App\Http\Controllers\Company\Simple\SimpleManifestController::class, 'micDtaReset'])
            ->name('reset')
            ->whereNumber('voyage');

        /**
         * ================================================================================
         * CONSULTAS DE ESTADO MIC/DTA - NUEVAS RUTAS
         * ================================================================================
         */

        // Consulta de estado individual por voyage (AJAX)
        Route::get('/{voyage}/consultar-estado', [App\Http\Controllers\Company\Simple\SimpleManifestController::class, 'consultarEstadoIndividual'])
            ->name('consultar-estado')
            ->whereNumber('voyage');

        // Consulta masiva de estados pendientes (AJAX) 
        Route::post('/consultar-estados-masivo', [App\Http\Controllers\Company\Simple\SimpleManifestController::class, 'consultarEstadoMasivo'])
            ->name('consultar-estados-masivo');

        // Obtener historial de consultas de un voyage (AJAX)
        Route::get('/{voyage}/historial-consultas', [App\Http\Controllers\Company\Simple\SimpleManifestController::class, 'obtenerHistorialConsultas'])
            ->name('historial-consultas')
            ->whereNumber('voyage');

        // Obtener estado AFIP actual para la vista (AJAX)
        Route::get('/{voyage}/estado-afip', [App\Http\Controllers\Company\Simple\SimpleManifestController::class, 'obtenerEstadoAfip'])
            ->name('estado-afip')
            ->whereNumber('voyage');

        /**
         * ================================================================================
         * 18 MÉTODOS AFIP ESPECÍFICOS - RUTAS INDIVIDUALES
         * ================================================================================
         */

        // === MÉTODOS PRINCIPALES (1-3) ===
        Route::post('/{voyage}/registrar-tit-envios', [App\Http\Controllers\Company\Simple\SimpleManifestController::class, 'registrarTitEnvios'])
            ->name('registrar-tit-envios')
            ->whereNumber('voyage');

        Route::post('/{voyage}/registrar-envios', [App\Http\Controllers\Company\Simple\SimpleManifestController::class, 'registrarEnvios'])
            ->name('registrar-envios')
            ->whereNumber('voyage');

        Route::post('/{voyage}/registrar-micdta', [App\Http\Controllers\Company\Simple\SimpleManifestController::class, 'registrarMicDta'])
            ->name('registrar-micdta')
            ->whereNumber('voyage');

        // === GESTIÓN CONVOY (4-6) ===
        Route::post('/{voyage}/registrar-convoy', [App\Http\Controllers\Company\Simple\SimpleManifestController::class, 'registrarConvoy'])
            ->name('registrar-convoy')
            ->whereNumber('voyage');

        Route::post('/{voyage}/asignar-ata-remol', [App\Http\Controllers\Company\Simple\SimpleManifestController::class, 'asignarATARemol'])
            ->name('asignar-ata-remol')
            ->whereNumber('voyage');

        Route::post('/{voyage}/rectif-convoy-micdta', [App\Http\Controllers\Company\Simple\SimpleManifestController::class, 'rectifConvoyMicDta'])
            ->name('rectif-convoy-micdta')
            ->whereNumber('voyage');

        // === GESTIÓN TÍTULOS (7-9) ===
        Route::post('/{voyage}/registrar-tit-micdta', [App\Http\Controllers\Company\Simple\SimpleManifestController::class, 'registrarTitMicDta'])
            ->name('registrar-tit-micdta')
            ->whereNumber('voyage');

        Route::post('/{voyage}/desvincular-tit-micdta', [App\Http\Controllers\Company\Simple\SimpleManifestController::class, 'desvincularTitMicDta'])
            ->name('desvincular-tit-micdta')
            ->whereNumber('voyage');

        Route::post('/{voyage}/anular-titulo', [App\Http\Controllers\Company\Simple\SimpleManifestController::class, 'anularTitulo'])
            ->name('anular-titulo')
            ->whereNumber('voyage');

        // === ZONA PRIMARIA (10-12) ===
        Route::post('/{voyage}/registrar-salida-zona-primaria', [App\Http\Controllers\Company\Simple\SimpleManifestController::class, 'registrarSalidaZonaPrimaria'])
            ->name('registrar-salida-zona-primaria')
            ->whereNumber('voyage');

        Route::post('/{voyage}/registrar-arribo-zona-primaria', [App\Http\Controllers\Company\Simple\SimpleManifestController::class, 'registrarArriboZonaPrimaria'])
            ->name('registrar-arribo-zona-primaria')
            ->whereNumber('voyage');

        Route::post('/{voyage}/anular-arribo-zona-primaria', [App\Http\Controllers\Company\Simple\SimpleManifestController::class, 'anularArriboZonaPrimaria'])
            ->name('anular-arribo-zona-primaria')
            ->whereNumber('voyage');

        // === CONSULTAS (13-15) ===
        Route::post('/{voyage}/consultar-micdta-asig', [App\Http\Controllers\Company\Simple\SimpleManifestController::class, 'consultarMicDtaAsig'])
            ->name('consultar-micdta-asig')
            ->whereNumber('voyage');

        Route::post('/{voyage}/consultar-tit-envios-reg', [App\Http\Controllers\Company\Simple\SimpleManifestController::class, 'consultarTitEnviosReg'])
            ->name('consultar-tit-envios-reg')
            ->whereNumber('voyage');

        Route::post('/{voyage}/consultar-precumplido', [App\Http\Controllers\Company\Simple\SimpleManifestController::class, 'consultarPrecumplido'])
            ->name('consultar-precumplido')
            ->whereNumber('voyage');

        // === ANULACIONES + TESTING (16-18) ===
        Route::post('/{voyage}/solicitar-anular-micdta', [App\Http\Controllers\Company\Simple\SimpleManifestController::class, 'solicitarAnularMicDta'])
            ->name('solicitar-anular-micdta')
            ->whereNumber('voyage');

        Route::post('/{voyage}/anular-envios', [App\Http\Controllers\Company\Simple\SimpleManifestController::class, 'anularEnvios'])
            ->name('anular-envios')
            ->whereNumber('voyage');

        Route::post('/{voyage}/dummy', [App\Http\Controllers\Company\Simple\SimpleManifestController::class, 'dummy'])
            ->name('dummy')
            ->whereNumber('voyage');

        // === DASHBOARD MÉTODOS AFIP ===
        Route::get('/{voyage}/methods-dashboard', [App\Http\Controllers\Company\Simple\SimpleManifestController::class, 'methodsDashboard'])
            ->name('methods-dashboard')
            ->whereNumber('voyage');

        // Ruta para obtener títulos vinculados (AJAX)
        Route::get('/{voyage}/titulos-vinculados', [App\Http\Controllers\Company\Simple\SimpleManifestController::class, 'getTitulosVinculados'])
            ->name('titulos-vinculados')
            ->whereNumber('voyage');

        // Ruta para obtener títulos registrados (AJAX)
        Route::get('/{voyage}/titulos-registrados', [App\Http\Controllers\Company\Simple\SimpleManifestController::class, 'getTitulosRegistrados'])
            ->name('titulos-registrados')
            ->whereNumber('voyage');
    });

    // ====================================
    // INFORMACIÓN ANTICIPADA ARGENTINA (FASE 2 - PRÓXIMAMENTE)
    // ====================================
    
    Route::prefix('anticipada')->name('anticipada.')->group(function () {
        
        /**
         * Lista de voyages para Información Anticipada
         * FASE 2: Implementar ArgentinaAnticipatedService
         */
        Route::get('/', [App\Http\Controllers\Company\Simple\SimpleManifestController::class, 'anticipadaIndex'])
            ->name('index');
            
        Route::get('/{voyage}', [App\Http\Controllers\Company\Simple\SimpleManifestController::class, 'anticipadaShow'])
            ->name('show')
            ->whereNumber('voyage');

        Route::post('/{voyage}/send', [App\Http\Controllers\Company\Simple\SimpleManifestController::class, 'anticipadaSend'])
            ->name('send')
            ->whereNumber('voyage');
    });

    // ============================
    // Paraguay – Manifiesto (DNA)
    // ============================
    Route::prefix('manifiesto')->name('manifiesto.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Company\Simple\SimpleManifestController::class, 'manifiestoIndex'])
            ->name('index');

        Route::get('/{voyage}', [\App\Http\Controllers\Company\Simple\SimpleManifestController::class, 'manifiestoShow'])
            ->whereNumber('voyage')
            ->name('show');

        Route::post('/{voyage}/send', [\App\Http\Controllers\Company\Simple\SimpleManifestController::class, 'manifiestoSend'])
            ->whereNumber('voyage')
            ->name('send');
    });


    // ============================
    // Argentina – Desconsolidado
    // ============================
    Route::prefix('desconsolidado')->name('desconsolidado.')->group(function () {
        // (opcional) listado, si después querés un index:
        // Route::get('/', [\App\Http\Controllers\Company\Simple\SimpleManifestController::class, 'desconsolidadoIndex'])
        //     ->name('index');

        // Form / pantalla por viaje
        Route::get('/{voyage}', [\App\Http\Controllers\Company\Simple\SimpleManifestController::class, 'desconsolidadoShow'])
            ->whereNumber('voyage')
            ->name('show');

        // Envío a AFIP (POST)
        Route::post('/{voyage}/send', [\App\Http\Controllers\Company\Simple\SimpleManifestController::class, 'desconsolidadoSend'])
            ->whereNumber('voyage')
            ->name('send');
    });


    // ====================================
    // TRANSBORDOS ARGENTINA/PARAGUAY (FASE 5 - PRÓXIMAMENTE)
    // ====================================
    
    Route::prefix('transbordo')->name('transbordo.')->group(function () {
        
        /**
         * Lista de voyages para Transbordos
         * FASE 5: Implementar ArgentinaTransshipmentService
         */
        Route::get('/', [App\Http\Controllers\Company\Simple\SimpleManifestController::class, 'transbordoIndex'])
            ->name('index');
            
        // TODO FASE 5: Rutas específicas transbordo
        // Route::get('/{voyage}', [SimpleManifestController::class, 'transbordoShow'])->name('show');
        // Route::post('/{voyage}/send', [SimpleManifestController::class, 'transbordoSend'])->name('send');
    });
    
});

// =============================================================================
// FIN SISTEMA MODULAR WEBSERVICES
// =============================================================================