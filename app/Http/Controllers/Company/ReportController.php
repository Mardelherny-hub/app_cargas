<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Operator;
use App\Traits\UserHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\Voyage;
use App\Models\Shipment; 
use App\Models\BillOfLading;
use App\Models\WebserviceTransaction;
use App\Models\Port;
use App\Models\Client;

class ReportController extends Controller
{
    use UserHelper;

    /**
     * Mostrar vista principal de reportes.
     * Filtrada según el rol del usuario y permisos de empresa.
     */
    public function index()
    {
        // 1. Verificar acceso básico a reportes
        // Solo company-admin y user pueden acceder a reportes
        if (!$this->isCompanyAdmin() && !$this->isUser()) {
            abort(403, 'No tiene permisos para acceder a reportes.');
        }

        $company = $this->getUserCompany();

        // 2. Verificar empresa asociada
        if (!$company) {
            return redirect()->route('company.dashboard')
                ->with('error', 'No se encontró la empresa asociada.');
        }

        // 3. Verificar acceso a la empresa
        if (!$this->canAccessCompany($company->id)) {
            abort(403, 'No tiene permisos para acceder a esta empresa.');
        }

        // Obtener estadísticas según el rol del usuario
        $stats = $this->getReportStats($company);

        // Obtener tipos de reportes disponibles según rol y permisos
        $reportTypes = $this->getAvailableReportTypes($company);

        // Obtener reportes recientes (filtrados por rol)
        $recentReports = $this->getRecentReports($company);

        // Obtener permisos específicos para reportes
        $permissions = $this->getReportPermissions();

        return view('company.reports.index', compact(
            'company',
            'stats',
            'reportTypes',
            'recentReports',
            'permissions'
        ));
    }

    /**
     * Reporte de manifiestos.
     * Solo disponible para empresas con rol "Cargas".
     */
    public function manifests(Request $request)
    {
        // 1. Verificar acceso básico
        if (!$this->isCompanyAdmin() && !$this->isUser()) {
            abort(403, 'No tiene permisos para ver reportes de manifiestos.');
        }

        $company = $this->getUserCompany();

        // 2. Verificar empresa y acceso
        if (!$company || !$this->canAccessCompany($company->id)) {
            abort(403, 'No tiene permisos para acceder a esta empresa.');
        }

        // 3. Verificar que la empresa tenga rol "Cargas"
        if (!$this->hasCompanyRole('Cargas')) {
            abort(403, 'Su empresa no tiene permisos para generar manifiestos. Se requiere rol "Cargas".');
        }

        // Aplicar filtros de ownership si es usuario regular
        $manifestsQuery = $this->buildManifestsQuery($company);

        // Si es usuario regular, filtrar solo sus propios registros
        if ($this->isUser()) {
            // TODO: Aplicar filtros de ownership cuando estén los módulos
            $manifestsQuery->where('created_by', $this->getCurrentUser()->id);
        }

        // Aplicar filtros de búsqueda
        $this->applyManifestFilters($manifestsQuery, $request);

        // TODO: Implementar cuando esté el módulo de cargas
        $manifests = $manifestsQuery->paginate(15);

        // Obtener estadísticas filtradas
        $stats = $this->getManifestStats($company);

        // Filtros disponibles
        $filters = $this->getManifestFilters();

        return view('company.reports.manifests', compact(
            'manifests',
            'stats',
            'filters',
            'company'
        ));
    }

    /**
     * Reporte de conocimientos de embarque.
     * Solo disponible para empresas con rol "Cargas".
     */
    public function billsOfLading(Request $request)
    {
        // 1. Verificar permisos básicos
        if (!$this->canPerform('reports.bills_of_lading')) {
            abort(403, 'No tiene permisos para ver reportes de conocimientos de embarque.');
        }

        $company = $this->getUserCompany();

        // 2. Verificar empresa y acceso
        if (!$company || !$this->canAccessCompany($company->id)) {
            abort(403, 'No tiene permisos para acceder a esta empresa.');
        }

        // 3. Verificar que la empresa tenga rol "Cargas"
        if (!$this->hasCompanyRole('Cargas')) {
            abort(403, 'Su empresa no tiene permisos para generar conocimientos de embarque. Se requiere rol "Cargas".');
        }

        // Aplicar filtros de ownership
        $billsQuery = $this->buildBillsOfLadingQuery($company);

        // Si es usuario regular, filtrar solo sus propios registros
        if ($this->isUser()) {
            // TODO: Aplicar filtros de ownership cuando estén los módulos
            $billsQuery->where('created_by_user_id', $this->getCurrentUser()->id);
        }

        // TODO: Implementar cuando esté el módulo de cargas
        $billsOfLading = $billsQuery->paginate(15);

        $stats = $this->getBillsOfLadingStats($company);
        $filters = $this->getBillsOfLadingFilters();

        return view('company.reports.bills-of-lading', compact(
            'billsOfLading',
            'stats',
            'filters',
            'company'
        ));
    }

    /**
     * Reporte MIC/DTA.
     * Solo disponible para empresas con rol "Cargas" y webservices activos.
     */
    public function micdta(Request $request)
    {
        // 1. Verificar permisos básicos
        if (!$this->canPerform('reports.micdta')) {
            abort(403, 'No tiene permisos para ver reportes MIC/DTA.');
        }

        $company = $this->getUserCompany();

        // 2. Verificar empresa y acceso
        if (!$company || !$this->canAccessCompany($company->id)) {
            abort(403, 'No tiene permisos para acceder a esta empresa.');
        }

        // 3. Verificar que la empresa tenga rol "Cargas"
        if (!$this->hasCompanyRole('Cargas')) {
            abort(403, 'Su empresa no tiene permisos para reportes MIC/DTA. Se requiere rol "Cargas".');
        }

        // 4. Verificar que tenga webservices activos
        if (!$company->ws_active) {
            return redirect()->route('company.reports.index')
                ->with('error', 'Los reportes MIC/DTA requieren webservices activos.');
        }

        // Aplicar filtros de ownership
        $micdtaQuery = $this->buildMicdtaQuery($company);

        // Si es usuario regular, filtrar solo sus propios registros
        if ($this->isUser()) {
            // TODO: Aplicar filtros de ownership cuando estén los módulos
            $micdtaQuery->where('created_by', $this->getCurrentUser()->id);
        }

        // TODO: Implementar cuando esté el módulo de webservices
        $micdtaReports = collect();

        $stats = $this->getMicdtaStats($company);
        $filters = $this->getMicdtaFilters();

        return view('company.reports.micdta', compact(
            'micdtaReports',
            'stats',
            'filters',
            'company'
        ));
    }

    /**
     * Reporte de cartas de aviso.
     * Solo disponible para empresas con rol "Cargas".
     */
    public function arrivalNotices(Request $request)
    {
        // 1. Verificar permisos básicos
        if (!$this->canPerform('reports.arrival_notices')) {
            abort(403, 'No tiene permisos para ver reportes de cartas de aviso.');
        }

        $company = $this->getUserCompany();

        // 2. Verificar empresa y acceso
        if (!$company || !$this->canAccessCompany($company->id)) {
            abort(403, 'No tiene permisos para acceder a esta empresa.');
        }

        // 3. Verificar que la empresa tenga rol "Cargas"
        if (!$this->hasCompanyRole('Cargas')) {
            abort(403, 'Su empresa no tiene permisos para cartas de aviso. Se requiere rol "Cargas".');
        }

        // Aplicar filtros de ownership
        $noticesQuery = $this->buildArrivalNoticesQuery($company);

        // Si es usuario regular, filtrar solo sus propios registros
        if ($this->isUser()) {
            // TODO: Aplicar filtros de ownership cuando estén los módulos
            $noticesQuery->where('created_by', $this->getCurrentUser()->id);
        }

        // TODO: Implementar cuando esté el módulo de cargas
        $notices = collect();

        $stats = $this->getArrivalNoticesStats($company);
        $filters = $this->getArrivalNoticesFilters();

        return view('company.reports.arrival-notices', compact(
            'notices',
            'stats',
            'filters',
            'company'
        ));
    }

    /**
     * Reportes aduaneros.
     * Disponible según los roles de empresa.
     */
    public function customs(Request $request)
    {
        // 1. Verificar permisos básicos
        if (!$this->canPerform('reports.customs')) {
            abort(403, 'No tiene permisos para ver reportes aduaneros.');
        }

        $company = $this->getUserCompany();

        // 2. Verificar empresa y acceso
        if (!$company || !$this->canAccessCompany($company->id)) {
            abort(403, 'No tiene permisos para acceder a esta empresa.');
        }

        // 3. Verificar que la empresa tenga al menos un rol válido
        $hasValidRole = $this->hasCompanyRole('Cargas') ||
                       $this->hasCompanyRole('Desconsolidador') ||
                       $this->hasCompanyRole('Transbordos');

        if (!$hasValidRole) {
            abort(403, 'Su empresa no tiene roles configurados para reportes aduaneros.');
        }

        // Aplicar filtros de ownership
        $customsQuery = $this->buildCustomsQuery($company);

        // Si es usuario regular, filtrar solo sus propios registros
        if ($this->isUser()) {
            // TODO: Aplicar filtros de ownership cuando estén los módulos
            $customsQuery->where('created_by', $this->getCurrentUser()->id);
        }

        // TODO: Implementar cuando estén los módulos correspondientes
        $customsReports = collect();

        $stats = $this->getCustomsStats($company);
        $filters = $this->getCustomsFilters();

        return view('company.reports.customs', compact(
            'customsReports',
            'stats',
            'filters',
            'company'
        ));
    }

    /**
     * Reporte de cargas.
     * Solo disponible para empresas con rol "Cargas" o "Desconsolidador".
     */
    public function shipments(Request $request)
    {
        // 1. Verificar permisos básicos
        if (!$this->canPerform('reports.shipments')) {
            abort(403, 'No tiene permisos para ver reportes de cargas.');
        }

        $company = $this->getUserCompany();

        // 2. Verificar empresa y acceso
        if (!$company || !$this->canAccessCompany($company->id)) {
            abort(403, 'No tiene permisos para acceder a esta empresa.');
        }

        // 3. Verificar que la empresa tenga rol apropiado
        $canAccessShipments = $this->hasCompanyRole('Cargas') || $this->hasCompanyRole('Desconsolidador');

        if (!$canAccessShipments) {
            abort(403, 'Su empresa no tiene permisos para reportes de cargas. Se requiere rol "Cargas" o "Desconsolidador".');
        }

        // Aplicar filtros de ownership
        $shipmentsQuery = $this->buildShipmentsQuery($company);

        // Si es usuario regular, filtrar solo sus propios registros
        if ($this->isUser()) {
            // TODO: Aplicar filtros de ownership cuando estén los módulos
            $shipmentsQuery->where('created_by', $this->getCurrentUser()->id);
        }

        // TODO: Implementar cuando esté el módulo de cargas
        $shipments = collect();

        $stats = $this->getShipmentsStats($company);
        $filters = $this->getShipmentsFilters();

        return view('company.reports.shipments', compact(
            'shipments',
            'stats',
            'filters',
            'company'
        ));
    }

    /**
     * Reporte de viajes.
     * Solo disponible para empresas con rol "Cargas".
     */
    public function voyages(Request $request)
    {
        // 1. Verificar permisos básicos
        if (!$this->canPerform('reports.trips')) {
            abort(403, 'No tiene permisos para ver reportes de viajes.');
        }

        $company = $this->getUserCompany();

        // 2. Verificar empresa y acceso
        if (!$company || !$this->canAccessCompany($company->id)) {
            abort(403, 'No tiene permisos para acceder a esta empresa.');
        }

        // 3. Verificar que la empresa tenga rol "Cargas"
        if (!$this->hasCompanyRole('Cargas')) {
            abort(403, 'Su empresa no tiene permisos para reportes de viajes. Se requiere rol "Cargas".');
        }

        // Aplicar filtros de ownership
        $tripsQuery = $this->buildTripsQuery($company);

        // Si es usuario regular, filtrar solo sus propios registros
        if ($this->isUser()) {
            // TODO: Aplicar filtros de ownership cuando estén los módulos
            $tripsQuery->where('created_by', $this->getCurrentUser()->id);
        }

        // TODO: Implementar cuando esté el módulo de viajes
        $voyages = collect();

        $stats = $this->getTripsStats($company);
        $filters = $this->getTripsFilters();

        return view('company.reports.voyages', compact(
            'voyages',
            'stats',
            'filters',
            'company'
        ));
    }

    /**
     * Reporte de operadores.
     * SOLO COMPANY-ADMIN puede ver reportes de operadores.
     */
    public function operators(Request $request)
    {
        // 1. Verificar que sea company-admin
        if (!$this->isCompanyAdmin()) {
            abort(403, 'Solo los administradores de empresa pueden ver reportes de operadores.');
        }

        // 2. Verificar permisos específicos
        if (!$this->canPerform('reports.operators')) {
            abort(403, 'No tiene permisos para ver reportes de operadores.');
        }

        $company = $this->getUserCompany();

        // 3. Verificar empresa y acceso
        if (!$company || !$this->canAccessCompany($company->id)) {
            abort(403, 'No tiene permisos para acceder a esta empresa.');
        }

        // Obtener operadores con filtros
        $operatorsQuery = $company->operators()->with(['user.roles']);
        $this->applyOperatorReportFilters($operatorsQuery, $request);

        $operators = $operatorsQuery->paginate(15)->withQueryString();

        // Estadísticas de operadores
        $stats = $this->getOperatorsReportStats($company);

        // Filtros disponibles
        $filters = $this->getOperatorsReportFilters();

        return view('company.reports.operators', compact(
            'operators',
            'stats',
            'filters',
            'company'
        ));
    }

    /**
     * Exportar reportes a diferentes formatos.
     */
    public function export(Request $request, $reportType)
    {
        // 1. Verificar permisos básicos para exportar
        if (!$this->canPerform('reports.export')) {
            abort(403, 'No tiene permisos para exportar reportes.');
        }

        $company = $this->getUserCompany();

        // 2. Verificar empresa y acceso
        if (!$company || !$this->canAccessCompany($company->id)) {
            abort(403, 'No tiene permisos para acceder a esta empresa.');
        }

        // 3. Verificar permisos específicos según el tipo de reporte
        if (!$this->canExportReportType($reportType)) {
            abort(403, "No tiene permisos para exportar reportes de tipo '{$reportType}'.");
        }

        $format = $request->input('format', 'pdf');
        $filters = $request->input('filters', []);

        try {
            return $this->generateExport($reportType, $format, $filters, $company);
        } catch (\Exception $e) {
            return back()->with('error', 'Error al generar el reporte: ' . $e->getMessage());
        }
    }

    // =========================================================================
    // MÉTODOS AUXILIARES - ESTADÍSTICAS
    // =========================================================================

    /**
     * Obtener estadísticas generales de reportes según el rol.
     */
    private function getReportStats($company): array
    {
        $stats = [
            'reports_generated_today' => 0, // TODO: Implementar conteo real
            'reports_generated_month' => 0, // TODO: Implementar conteo real
            'most_used_report' => 'manifests', // TODO: Calcular dinámicamente
        ];

        // Estadísticas específicas según rol del usuario
        if ($this->isCompanyAdmin()) {
            $stats['admin_stats'] = [
                'total_operators' => $company->operators()->count(),
                'active_operators' => $company->operators()->where('active', true)->count(),
                'reports_by_operator' => [], // TODO: Implementar cuando estén los módulos
            ];
        }

        if ($this->isUser()) {
            $stats['user_stats'] = [
                'my_reports_today' => 0, // TODO: Implementar conteo personal
                'my_reports_month' => 0, // TODO: Implementar conteo personal
                'last_report_generated' => null, // TODO: Obtener último reporte del usuario
            ];
        }

        return $stats;
    }

    /**
     * Obtener tipos de reportes disponibles según rol y permisos.
     */
    private function getAvailableReportTypes($company): array
    {
        $reportTypes = [];

        // Reportes para empresas con rol "Cargas"
        if ($this->hasCompanyRole('Cargas')) {
            $reportTypes['manifests'] = [
                'name' => 'Manifiestos de Carga',
                'description' => 'Documentos que detallan todas las cargas de un viaje específico.',
                'icon' => 'document-text',
                'color' => 'blue',
                'available' => $this->canPerform('reports.manifests'),
                'route' => route('company.reports.manifests'),
            ];

            $reportTypes['bills-of-lading'] = [
                'name' => 'Conocimientos de Embarque',
                'description' => 'Documentos contractuales entre el transportista y el cargador.',
                'icon' => 'clipboard-list',
                'color' => 'green',
                'available' => $this->canPerform('reports.bills_of_lading'),
                'route' => route('company.reports.bills-of-lading'),
            ];

            $reportTypes['micdta'] = [
                'name' => 'Reportes MIC/DTA',
                'description' => 'Manifiestos Internacionales de Carga y Declaraciones de Tránsito Aduanero.',
                'icon' => 'shield-check',
                'color' => 'purple',
                'available' => $company->ws_active && $this->canPerform('reports.micdta'),
                'route' => route('company.reports.micdta'),
                'requires_webservice' => true,
            ];

            $reportTypes['arrival-notices'] = [
                'name' => 'Cartas de Aviso',
                'description' => 'Notificaciones de llegada de mercadería para consignatarios.',
                'icon' => 'mail',
                'color' => 'yellow',
                'available' => $this->canPerform('reports.arrival_notices'),
                'route' => route('company.reports.arrival-notices'),
            ];

            $reportTypes['voyages'] = [
                'name' => 'Reportes de Viajes',
                'description' => 'Información detallada sobre viajes realizados.',
                'icon' => 'truck',
                'color' => 'indigo',
                'available' => $this->canPerform('reports.trips'),
                'route' => route('company.reports.voyages'),
            ];
        }

        // Reportes para empresas con rol "Cargas" o "Desconsolidador"
        if ($this->hasCompanyRole('Cargas') || $this->hasCompanyRole('Desconsolidador')) {
            $reportTypes['shipments'] = [
                'name' => 'Reportes de Cargas',
                'description' => 'Información detallada sobre cargas procesadas.',
                'icon' => 'archive',
                'color' => 'gray',
                'available' => $this->canPerform('reports.shipments'),
                'route' => route('company.reports.shipments'),
            ];
        }

        // Reportes aduaneros (disponible para todos los roles de empresa)
        $hasAnyBusinessRole = $this->hasCompanyRole('Cargas') ||
                             $this->hasCompanyRole('Desconsolidador') ||
                             $this->hasCompanyRole('Transbordos');

        if ($hasAnyBusinessRole) {
            $reportTypes['customs'] = [
                'name' => 'Reportes Aduaneros',
                'description' => 'Documentación requerida por las autoridades aduaneras.',
                'icon' => 'flag',
                'color' => 'red',
                'available' => $this->canPerform('reports.customs'),
                'route' => route('company.reports.customs'),
            ];
        }

        // Reportes para empresas con rol "Desconsolidador"
        if ($this->hasCompanyRole('Desconsolidador')) {
            $reportTypes['deconsolidation'] = [
                'name' => 'Reportes de Desconsolidación',
                'description' => 'Títulos de desconsolidación y proceso de fraccionamiento.',
                'icon' => 'cube-transparent',
                'color' => 'orange',
                'available' => $this->canPerform('reports.deconsolidation'),
                'route' => route('company.reports.deconsolidation'),
            ];
        }

        // Reportes para empresas con rol "Transbordos"
        if ($this->hasCompanyRole('Transbordos')) {
            $reportTypes['transshipment'] = [
                'name' => 'Reportes de Transbordos',
                'description' => 'Operaciones de transbordo y transferencia entre embarcaciones.',
                'icon' => 'switch-horizontal',
                'color' => 'cyan',
                'available' => $this->canPerform('reports.transshipment'),
                'route' => route('company.reports.transshipment'),
            ];
        }

        // Reportes de operadores (solo company-admin)
        if ($this->isCompanyAdmin()) {
            $reportTypes['operators'] = [
                'name' => 'Reportes de Operadores',
                'description' => 'Estadísticas y actividad de operadores de la empresa.',
                'icon' => 'user-group',
                'color' => 'teal',
                'available' => $this->canPerform('reports.operators'),
                'route' => route('company.reports.operators'),
                'admin_only' => true,
            ];
        }

        return $reportTypes;
    }

    /**
     * Obtener reportes recientes filtrados por rol.
     */
    private function getRecentReports($company): array
    {
        $recentReports = [];

        // Obtener últimos manifiestos
        $recentVoyages = Voyage::where('company_id', $company->id)
            ->whereHas('shipments.billsOfLading')
            ->with('leadVessel')
            ->orderBy('created_at', 'desc')
            ->take(3)
            ->get();

        foreach ($recentVoyages as $voyage) {
            $recentReports[] = [
                'type' => 'manifest',
                'name' => "Manifiesto #{$voyage->voyage_number}",
                'generated_at' => $voyage->created_at,
                'generated_by' => $voyage->createdByUser->name ?? 'Sistema',
                'status' => $voyage->status,
            ];
        }

        // Obtener últimas transacciones de webservice
        $recentWebservices = WebserviceTransaction::where('company_id', $company->id)
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->take(2)
            ->get();

        foreach ($recentWebservices as $ws) {
            $recentReports[] = [
                'type' => strtoupper($ws->webservice_type),
                'name' => "Transacción {$ws->webservice_type} #{$ws->transaction_id}",
                'generated_at' => $ws->created_at,
                'generated_by' => $ws->user->name ?? 'Sistema',
                'status' => $ws->status,
            ];
        }

        return $recentReports;
    }
        
    // =========================================================================
    // MÉTODOS AUXILIARES - QUERIES Y FILTROS
    // =========================================================================

    /**
     * Construir query base para manifiestos.
     */
    private function buildManifestsQuery($company)
    {
        return Voyage::with(['shipments.billsOfLading', 'leadVessel', 'originPort', 'destinationPort'])
            ->where('company_id', $company->id)
            ->where('active', true)
            ->whereHas('shipments.billsOfLading')
            ->orderBy('departure_date', 'desc');
    }

    /**
     * Construir query base para conocimientos de embarque.
     */
private function buildBillsOfLadingQuery($company)
{
    return BillOfLading::with([
        'shipment.voyage:id,voyage_number,company_id',
        'shipper:id,legal_name,tax_id',
        'consignee:id,legal_name,tax_id',
        'loadingPort:id,name,country_id',
        'dischargePort:id,name,country_id'
    ])
    ->whereHas('shipment.voyage', function ($q) use ($company) {
        $q->where('company_id', $company->id);
    })
    ->orderBy('created_at', 'desc'); // Cambiar por created_at que siempre existe
}

    /**
     * Construir query base para reportes MIC/DTA.
     */
    private function buildMicdtaQuery($company)
    {
        return WebserviceTransaction::with(['voyage', 'shipment'])
            ->where('company_id', $company->id)
            ->whereIn('webservice_type', ['micdta', 'anticipada'])
            ->where('status', '!=', 'cancelled')
            ->orderBy('created_at', 'desc');
    }

    /**
     * Construir query base para cartas de aviso.
     */
    private function buildArrivalNoticesQuery($company)
    {
        return BillOfLading::with(['shipment.voyage', 'consignee', 'notifyParty', 'dischargePort'])
            ->whereHas('shipment.voyage', function($query) use ($company) {
                $query->where('company_id', $company->id);
            })
            ->whereNotNull('arrival_date')
            ->where('status', 'in_transit')
            ->orderBy('arrival_date', 'desc');
    }

    /**
     * Construir query base para reportes aduaneros.
     */
    private function buildCustomsQuery($company)
    {
        return WebserviceTransaction::with(['voyage', 'shipment'])
            ->where('company_id', $company->id)
            ->whereIn('webservice_type', ['micdta', 'anticipada', 'desconsolidados', 'transbordos'])
            ->whereIn('status', ['success', 'error', 'pending'])
            ->orderBy('created_at', 'desc');
    }

    /**
     * Construir query base para cargas.
     */
    private function buildShipmentsQuery($company)
    {
        return Shipment::with(['voyage', 'vessel', 'billsOfLading', 'shipmentItems'])
            ->whereHas('voyage', function($query) use ($company) {
                $query->where('company_id', $company->id);
            })
            ->where('active', true)
            ->orderBy('created_date', 'desc');
    }

    /**
     * Construir query base para viajes.
     */
    private function buildTripsQuery($company)
    {
        return Voyage::with(['shipments', 'leadVessel', 'originPort', 'destinationPort'])
            ->where('company_id', $company->id)
            ->where('active', true)
            ->orderBy('departure_date', 'desc');
    }
    /**
     * Aplicar filtros específicos para reportes de operadores.
     */
    private function applyOperatorReportFilters($query, Request $request)
    {
        if ($request->filled('status')) {
            $query->where('active', $request->status === 'active');
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('permission')) {
            $permission = $request->permission;
            if (in_array($permission, ['can_import', 'can_export', 'can_transfer'])) {
                $query->where($permission, true);
            }
        }

        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->where('created_at', '<=', $request->date_to . ' 23:59:59');
        }
    }

    // =========================================================================
    // MÉTODOS AUXILIARES - ESTADÍSTICAS ESPECÍFICAS
    // =========================================================================

    /**
     * Obtener estadísticas de manifiestos.
     */
    private function getManifestStats($company): array
    {
        $voyages = Voyage::where('company_id', $company->id)
            ->where('active', true)
            ->whereHas('shipments.billsOfLading');

        return [
            'total' => $voyages->count(),
            'this_month' => $voyages->whereMonth('created_at', now()->month)->count(),
            'pending' => $voyages->where('status', 'pending')->count(),
            'completed' => $voyages->where('status', 'completed')->count(),
        ];
    }

    /**
     * Obtener estadísticas de conocimientos de embarque.
     */
    private function getBillsOfLadingStats($company): array
    {
        $bills = BillOfLading::whereHas('shipment.voyage', function($query) use ($company) {
            $query->where('company_id', $company->id);
        });

        return [
            'total' => $bills->count(),
            'this_month' => $bills->whereMonth('created_at', now()->month)->count(),
            'pending' => $bills->where('status', 'pending')->count(),
            'completed' => $bills->where('status', 'issued')->count(),
        ];
    }

    /**
     * Obtener estadísticas MIC/DTA.
     */
    private function getMicdtaStats($company): array
    {
        $transactions = WebserviceTransaction::where('company_id', $company->id)
            ->whereIn('webservice_type', ['micdta', 'anticipada']);

        $total = $transactions->count();
        $successful = $transactions->where('status', 'success')->count();

        return [
            'sent' => $total,
            'pending' => $transactions->where('status', 'pending')->count(),
            'failed' => $transactions->where('status', 'error')->count(),
            'success_rate' => $total > 0 ? round(($successful / $total) * 100, 1) : 0,
        ];
    }

    /**
     * Obtener estadísticas de cartas de aviso.
     */
    private function getArrivalNoticesStats($company): array
    {
        $notices = BillOfLading::whereHas('shipment.voyage', function($query) use ($company) {
            $query->where('company_id', $company->id);
        })->whereNotNull('arrival_date');

        return [
            'sent' => $notices->whereNotNull('notify_party_id')->count(),
            'pending' => $notices->where('status', 'in_transit')->count(),
            'this_month' => $notices->whereMonth('arrival_date', now()->month)->count(),
        ];
    }

    /**
     * Obtener estadísticas aduaneras.
     */
    private function getCustomsStats($company): array
    {
        $transactions = WebserviceTransaction::where('company_id', $company->id);

        return [
            'reports_generated' => $transactions->count(),
            'pending_submissions' => $transactions->where('status', 'pending')->count(),
            'approved' => $transactions->where('status', 'success')->count(),
        ];
    }

    /**
     * Obtener estadísticas de cargas.
     */
    private function getShipmentsStats($company): array
    {
        $shipments = Shipment::whereHas('voyage', function($query) use ($company) {
            $query->where('company_id', $company->id);
        });

        return [
            'total' => $shipments->count(),
            'this_month' => $shipments->whereMonth('created_at', now()->month)->count(),
            'pending' => $shipments->where('status', 'pending')->count(),
            'completed' => $shipments->where('status', 'completed')->count(),
        ];
    }

    /**
     * Obtener estadísticas de viajes.
     */
    private function getTripsStats($company): array
    {
        $voyages = Voyage::where('company_id', $company->id)->where('active', true);

        return [
            'total' => $voyages->count(),
            'this_month' => $voyages->whereMonth('created_at', now()->month)->count(),
            'active' => $voyages->whereIn('status', ['in_progress', 'loading'])->count(),
            'completed' => $voyages->where('status', 'completed')->count(),
        ];
    }

    /**
     * Obtener estadísticas de operadores para reportes.
     */
    private function getOperatorsReportStats($company): array
    {
        $operators = $company->operators();

        return [
            'total' => $operators->count(),
            'active' => $operators->where('active', true)->count(),
            'internal' => $operators->where('type', 'internal')->count(),
            'external' => $operators->where('type', 'external')->count(),
            'with_import' => $operators->where('can_import', true)->count(),
            'with_export' => $operators->where('can_export', true)->count(),
            'with_transfer' => $operators->where('can_transfer', true)->count(),
        ];
    }

    // =========================================================================
    // MÉTODOS AUXILIARES - FILTROS Y PERMISOS
    // =========================================================================

    /**
     * Obtener filtros para manifiestos.
     */
    private function getManifestFilters(): array
    {
        return [
            'status' => ['pending', 'completed', 'sent'],
            'period' => ['today', 'week', 'month', 'quarter'],
            'destination' => Port::where('active', true)
                ->orderBy('name')
                ->pluck('name', 'id')
                ->toArray(),
        ];
    }

    /**
     * Obtener filtros para conocimientos de embarque.
     */
    private function getBillsOfLadingFilters(): array
    {
        return [
            'status' => ['pending', 'issued', 'in_transit', 'delivered'],
            'period' => ['today', 'week', 'month', 'quarter'],
            'shipper' => Client::whereHas('shipper_bills')
                ->orderBy('legal_name')
                ->pluck('legal_name', 'id')
                ->toArray(),
        ];
    }

    /**
     * Obtener filtros MIC/DTA.
     */
    private function getMicdtaFilters(): array
    {
        return [
            'status' => ['pending', 'sent', 'accepted', 'rejected'],
            'type' => ['mic', 'dta'],
            'period' => ['today', 'week', 'month', 'quarter'],
        ];
    }

    /**
     * Obtener filtros para cartas de aviso.
     */
    private function getArrivalNoticesFilters(): array
    {
        return [
            'status' => ['pending', 'sent', 'delivered'],
            'period' => ['today', 'week', 'month', 'quarter'],
        ];
    }

    /**
     * Obtener filtros aduaneros.
     */
    private function getCustomsFilters(): array
    {
        return [
            'type' => ['import', 'export', 'transit'],
            'status' => ['pending', 'submitted', 'approved', 'rejected'],
            'period' => ['today', 'week', 'month', 'quarter'],
        ];
    }

    /**
     * Obtener filtros para cargas.
     */
    private function getShipmentsFilters(): array
    {
        return [
            'status' => ['pending', 'in_transit', 'delivered'],
            'type' => ['import', 'export'],
            'period' => ['today', 'week', 'month', 'quarter'],
        ];
    }

    /**
     * Obtener filtros para viajes.
     */
    private function getTripsFilters(): array
    {
        return [
            'status' => ['planned', 'in_progress', 'completed'],
            'destination' => [], // TODO: Obtener destinos disponibles
            'period' => ['today', 'week', 'month', 'quarter'],
        ];
    }

    /**
     * Obtener filtros para reportes de operadores.
     */
    private function getOperatorsReportFilters(): array
    {
        return [
            'status' => ['active', 'inactive'],
            'type' => ['internal', 'external'],
            'permission' => ['can_import', 'can_export', 'can_transfer'],
            'period' => ['today', 'week', 'month', 'quarter'],
        ];
    }

    /**
     * Obtener permisos específicos para reportes.
     */
    private function getReportPermissions(): array
    {
        return [
            'canExport' => $this->canPerform('reports.export'),
            'canViewManifests' => $this->hasCompanyRole('Cargas') && $this->canPerform('reports.manifests'),
            'canViewBillsOfLading' => $this->hasCompanyRole('Cargas') && $this->canPerform('reports.bills_of_lading'),
            'canViewMicdta' => $this->hasCompanyRole('Cargas') && $this->canPerform('reports.micdta'),
            'canViewArrivalNotices' => $this->hasCompanyRole('Cargas') && $this->canPerform('reports.arrival_notices'),
            'canViewCustoms' => $this->canPerform('reports.customs'),
            'canViewShipments' => ($this->hasCompanyRole('Cargas') || $this->hasCompanyRole('Desconsolidador')) && $this->canPerform('reports.shipments'),
            'canViewTrips' => $this->hasCompanyRole('Cargas') && $this->canPerform('reports.trips'),
            'canViewOperators' => $this->isCompanyAdmin() && $this->canPerform('reports.operators'),
             'canViewDeconsolidation' => $this->hasCompanyRole('Desconsolidador') && $this->canPerform('reports.deconsolidation'),
            'canViewTransshipment' => $this->hasCompanyRole('Transbordos') && $this->canPerform('reports.transshipment'),
        ];
    }

    /**
     * Verificar si puede exportar un tipo específico de reporte.
     */
    private function canExportReportType($reportType): bool
    {
        switch ($reportType) {
            case 'manifests':
                return $this->hasCompanyRole('Cargas') && $this->canPerform('reports.manifests');
            case 'bills-of-lading':
                return $this->hasCompanyRole('Cargas') && $this->canPerform('reports.bills_of_lading');
            case 'micdta':
                return $this->hasCompanyRole('Cargas') && $this->canPerform('reports.micdta');
            case 'arrival-notices':
                return $this->hasCompanyRole('Cargas') && $this->canPerform('reports.arrival_notices');
            case 'customs':
                return $this->canPerform('reports.customs');
            case 'shipments':
                return ($this->hasCompanyRole('Cargas') || $this->hasCompanyRole('Desconsolidador')) && $this->canPerform('reports.shipments');
            case 'voyages':
                return $this->hasCompanyRole('Cargas') && $this->canPerform('reports.trips');
            case 'operators':
                return $this->isCompanyAdmin() && $this->canPerform('reports.operators');
            case 'deconsolidation':
                return $this->hasCompanyRole('Desconsolidador') && $this->canPerform('reports.deconsolidation');
            case 'transshipment':
                return $this->hasCompanyRole('Transbordos') && $this->canPerform('reports.transshipment');
            default:
                return false;
        }
    }

    /**
     * Generar exportación del reporte.
     */
    private function generateExport($reportType, $format, $filters, $company)
    {
        // TODO: Implementar generación real de reportes
        // Por ahora retornar un archivo de ejemplo

        $filename = "{$reportType}_" . now()->format('Y-m-d_H-i-s') . ".{$format}";

        if ($format === 'pdf') {
            // TODO: Generar PDF real
            return response()->json(['message' => 'Generación de PDF en desarrollo']);
        } elseif ($format === 'excel') {
            // TODO: Generar Excel real
            return response()->json(['message' => 'Generación de Excel en desarrollo']);
        } else {
            return response()->json(['error' => 'Formato no soportado'], 400);
        }
    }

    // =========================================================================
    // MÉTODOS AUXILIARES - APLICAR FILTROS
    // =========================================================================

    /**
     * Aplicar filtros específicos para manifiestos.
     */
    private function applyManifestFilters($query, Request $request)
    {
        // TODO: Implementar filtros específicos cuando esté el módulo de cargas
    }

    /**
 * Reporte de desconsolidación.
 * Solo disponible para empresas con rol "Desconsolidador".
 */
public function deconsolidation(Request $request)
{
    // 1. Verificar permisos básicos
    if (!$this->canPerform('reports.deconsolidation')) {
        abort(403, 'No tiene permisos para ver reportes de desconsolidación.');
    }

    $company = $this->getUserCompany();

    // 2. Verificar empresa y acceso
    if (!$company || !$this->canAccessCompany($company->id)) {
        abort(403, 'No tiene permisos para acceder a esta empresa.');
    }

    // 3. Verificar que la empresa tenga rol "Desconsolidador"
    if (!$this->hasCompanyRole('Desconsolidador')) {
        abort(403, 'Su empresa no tiene permisos para ver reportes de desconsolidación. Se requiere rol "Desconsolidador".');
    }

    // Aplicar filtros de ownership si es usuario regular
    $deconsolidationQuery = $this->buildDeconsolidationQuery($company);

    // Si es usuario regular, filtrar solo sus propios registros
    if ($this->isUser()) {
        // TODO: Aplicar filtros de ownership cuando estén los módulos
        $deconsolidationQuery->where('created_by', $this->getCurrentUser()->id);
    }

    // Aplicar filtros de búsqueda
    $this->applyDeconsolidationFilters($deconsolidationQuery, $request);

    // TODO: Implementar cuando esté el módulo de desconsolidación
    $deconsolidations = collect(); // Colección vacía por ahora

    // Obtener estadísticas filtradas
    $stats = $this->getDeconsolidationStats($company);

    // Filtros disponibles
    $filters = $this->getDeconsolidationFilters();

    return view('company.reports.deconsolidation', compact(
        'deconsolidations',
        'stats',
        'filters',
        'company'
    ));
}

/**
 * Reporte de transbordos.
 * Solo disponible para empresas con rol "Transbordos".
 */
public function transshipment(Request $request)
{
    // 1. Verificar permisos básicos
    if (!$this->canPerform('reports.transshipment')) {
        abort(403, 'No tiene permisos para ver reportes de transbordos.');
    }

    $company = $this->getUserCompany();

    // 2. Verificar empresa y acceso
    if (!$company || !$this->canAccessCompany($company->id)) {
        abort(403, 'No tiene permisos para acceder a esta empresa.');
    }

    // 3. Verificar que la empresa tenga rol "Transbordos"
    if (!$this->hasCompanyRole('Transbordos')) {
        abort(403, 'Su empresa no tiene permisos para ver reportes de transbordos. Se requiere rol "Transbordos".');
    }

    // Aplicar filtros de ownership si es usuario regular
    $transshipmentQuery = $this->buildTransshipmentQuery($company);

    // Si es usuario regular, filtrar solo sus propios registros
    if ($this->isUser()) {
        // TODO: Aplicar filtros de ownership cuando estén los módulos
        $transshipmentQuery->where('created_by', $this->getCurrentUser()->id);
    }

    // Aplicar filtros de búsqueda
    $this->applyTransshipmentFilters($transshipmentQuery, $request);

    // TODO: Implementar cuando esté el módulo de transbordos
    $transshipments = collect(); // Colección vacía por ahora

    // Obtener estadísticas filtradas
    $stats = $this->getTransshipmentStats($company);

    // Filtros disponibles
    $filters = $this->getTransshipmentFilters();

    return view('company.reports.transshipment', compact(
        'transshipments',
        'stats',
        'filters',
        'company'
    ));
}

// =========================================================================
// MÉTODOS AUXILIARES A AGREGAR
// =========================================================================

/**
 * Construir query base para desconsolidación.
 */
private function buildDeconsolidationQuery($company)
{
    return WebserviceTransaction::with(['voyage', 'shipment'])
        ->where('company_id', $company->id)
        ->where('webservice_type', 'desconsolidados')
        ->where('status', '!=', 'cancelled')
        ->orderBy('created_at', 'desc');
}

/**
 * Construir query base para transbordos.
 */
private function buildTransshipmentQuery($company)
{
     return WebserviceTransaction::with(['voyage', 'shipment'])
        ->where('company_id', $company->id)
        ->where('webservice_type', 'transbordos')
        ->where('status', '!=', 'cancelled')
        ->orderBy('created_at', 'desc');
}

/**
 * Aplicar filtros de desconsolidación.
 */
private function applyDeconsolidationFilters($query, Request $request)
{
    if ($request->filled('status')) {
        $query->where('status', $request->status);
    }

    if ($request->filled('period')) {
        $this->applyPeriodFilter($query, $request->period);
    }

    if ($request->filled('date_from')) {
        $query->where('created_at', '>=', $request->date_from);
    }

    if ($request->filled('date_to')) {
        $query->where('created_at', '<=', $request->date_to . ' 23:59:59');
    }

    if ($request->filled('search')) {
        $search = $request->search;
        $query->where(function($q) use ($search) {
            $q->where('transaction_id', 'like', "%{$search}%")
              ->orWhereHas('voyage', function($voyage) use ($search) {
                  $voyage->where('voyage_number', 'like', "%{$search}%");
              });
        });
    }
}

/**
 * Aplicar filtros de transbordos.
 */
private function applyTransshipmentFilters($query, Request $request)
{
    if ($request->filled('status')) {
        $query->where('status', $request->status);
    }

    if ($request->filled('period')) {
        $this->applyPeriodFilter($query, $request->period);
    }

    if ($request->filled('date_from')) {
        $query->where('created_at', '>=', $request->date_from);
    }

    if ($request->filled('date_to')) {
        $query->where('created_at', '<=', $request->date_to . ' 23:59:59');
    }

    if ($request->filled('route')) {
        $query->whereHas('voyage', function($voyage) use ($request) {
            $voyage->whereHas('originPort', function($port) use ($request) {
                $port->where('code', 'like', "%{$request->route}%");
            })->orWhereHas('destinationPort', function($port) use ($request) {
                $port->where('code', 'like', "%{$request->route}%");
            });
        });
    }

    if ($request->filled('search')) {
        $search = $request->search;
        $query->where(function($q) use ($search) {
            $q->where('transaction_id', 'like', "%{$search}%")
              ->orWhereHas('voyage', function($voyage) use ($search) {
                  $voyage->where('voyage_number', 'like', "%{$search}%");
              });
        });
    }
}
/**
 * Obtener estadísticas de desconsolidación.
 */
private function getDeconsolidationStats($company): array
{
    $transactions = WebserviceTransaction::where('company_id', $company->id)
        ->where('webservice_type', 'desconsolidados');

    return [
        'total' => $transactions->count(),
        'this_month' => $transactions->whereMonth('created_at', now()->month)->count(),
        'pending' => $transactions->where('status', 'pending')->count(),
        'completed' => $transactions->where('status', 'success')->count(),
    ];
}

/**
 * Obtener estadísticas de transbordos.
 */
private function getTransshipmentStats($company): array
{
    $transactions = WebserviceTransaction::where('company_id', $company->id)
        ->where('webservice_type', 'transbordos');

    return [
        'total' => $transactions->count(),
        'this_month' => $transactions->whereMonth('created_at', now()->month)->count(),
        'pending' => $transactions->where('status', 'pending')->count(),
        'completed' => $transactions->where('status', 'success')->count(),
    ];
}
/**
 * Obtener filtros para desconsolidación.
 */
private function getDeconsolidationFilters(): array
{
    return [
        'status' => [
            'pending' => 'Pendiente',
            'processing' => 'Procesando', 
            'success' => 'Completado',
            'error' => 'Error'
        ],
        'period' => [
            'today' => 'Hoy',
            'week' => 'Esta semana',
            'month' => 'Este mes',
            'quarter' => 'Este trimestre'
        ],
        'container_type' => $this->getAvailableContainerTypes(),
    ];
}

/**
 * Obtener filtros para transbordos.
 */
private function getTransshipmentFilters(): array
{
    return [
        'status' => [
            'pending' => 'Pendiente',
            'in_transit' => 'En tránsito',
            'success' => 'Completado',
            'error' => 'Error'
        ],
        'period' => [
            'today' => 'Hoy',
            'week' => 'Esta semana', 
            'month' => 'Este mes',
            'quarter' => 'Este trimestre'
        ],
        'route' => $this->getAvailableRoutes(),
    ];
}

/**
 * Aplicar filtro de período.
 */
private function applyPeriodFilter($query, $period)
{
    switch ($period) {
        case 'today':
            $query->whereDate('created_at', now()->toDateString());
            break;
        case 'week':
            $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
            break;
        case 'month':
            $query->whereMonth('created_at', now()->month)
                  ->whereYear('created_at', now()->year);
            break;
        case 'quarter':
            $query->whereBetween('created_at', [now()->startOfQuarter(), now()->endOfQuarter()]);
            break;
    }
}

/**
 * Obtener tipos de contenedor disponibles.
 */
private function getAvailableContainerTypes(): array
{
    try {
        if (class_exists(\App\Models\ContainerType::class)) {
            return \App\Models\ContainerType::where('active', true)
                ->pluck('name', 'code')
                ->toArray();
        }
    } catch (\Exception $e) {
        // Si no existe el modelo, devolver tipos comunes
    }
    
    return [
        '20GP' => 'Contenedor 20\' General',
        '40GP' => 'Contenedor 40\' General', 
        '40HC' => 'Contenedor 40\' High Cube',
        '20RF' => 'Contenedor 20\' Refrigerado',
        '40RF' => 'Contenedor 40\' Refrigerado',
    ];
}

/**
 * Obtener rutas disponibles.
 */
private function getAvailableRoutes(): array
{
    try {
        if (class_exists(\App\Models\Port::class)) {
            $ports = \App\Models\Port::where('active', true)
                ->orderBy('code')
                ->pluck('code', 'code')
                ->toArray();
            
            return $ports;
        }
    } catch (\Exception $e) {
        // Si no existe el modelo, devolver rutas comunes
    }
    
    return [
        'ARBUE' => 'Buenos Aires, AR',
        'PYASU' => 'Asunción, PY',
        'PYTVT' => 'Villeta, PY',
        'PYCON' => 'Concepción, PY',
    ];
}
}