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
            // $manifestsQuery->where('created_by', $this->getCurrentUser()->id);
        }

        // Aplicar filtros de búsqueda
        $this->applyManifestFilters($manifestsQuery, $request);

        // TODO: Implementar cuando esté el módulo de cargas
        $manifests = collect(); // Colección vacía por ahora

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
        if (!$this->canPerform('reports_bills_of_lading')) {
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
            // $billsQuery->where('created_by', $this->getCurrentUser()->id);
        }

        // TODO: Implementar cuando esté el módulo de cargas
        $bills = collect();

        $stats = $this->getBillsOfLadingStats($company);
        $filters = $this->getBillsOfLadingFilters();

        return view('company.reports.bills-of-lading', compact(
            'bills',
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
        if (!$this->canPerform('reports_micdta')) {
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
            // $micdtaQuery->where('created_by', $this->getCurrentUser()->id);
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
        if (!$this->canPerform('reports_arrival_notices')) {
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
            // $noticesQuery->where('created_by', $this->getCurrentUser()->id);
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
        if (!$this->canPerform('reports_customs')) {
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
            // $customsQuery->where('created_by', $this->getCurrentUser()->id);
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
        if (!$this->canPerform('reports_shipments')) {
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
            // $shipmentsQuery->where('created_by', $this->getCurrentUser()->id);
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
    public function trips(Request $request)
    {
        // 1. Verificar permisos básicos
        if (!$this->canPerform('reports_trips')) {
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
            // $tripsQuery->where('created_by', $this->getCurrentUser()->id);
        }

        // TODO: Implementar cuando esté el módulo de viajes
        $trips = collect();

        $stats = $this->getTripsStats($company);
        $filters = $this->getTripsFilters();

        return view('company.reports.trips', compact(
            'trips',
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
        if (!$this->canPerform('reports_operators')) {
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
        if (!$this->canPerform('reports_export')) {
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
                'available' => $this->canPerform('reports_manifests'),
                'route' => route('company.reports.manifests'),
            ];

            $reportTypes['bills-of-lading'] = [
                'name' => 'Conocimientos de Embarque',
                'description' => 'Documentos contractuales entre el transportista y el cargador.',
                'icon' => 'clipboard-list',
                'color' => 'green',
                'available' => $this->canPerform('reports_bills_of_lading'),
                'route' => route('company.reports.bills-of-lading'),
            ];

            $reportTypes['micdta'] = [
                'name' => 'Reportes MIC/DTA',
                'description' => 'Manifiestos Internacionales de Carga y Declaraciones de Tránsito Aduanero.',
                'icon' => 'shield-check',
                'color' => 'purple',
                'available' => $company->ws_active && $this->canPerform('reports_micdta'),
                'route' => route('company.reports.micdta'),
                'requires_webservice' => true,
            ];

            $reportTypes['arrival-notices'] = [
                'name' => 'Cartas de Aviso',
                'description' => 'Notificaciones de llegada de mercadería para consignatarios.',
                'icon' => 'mail',
                'color' => 'yellow',
                'available' => $this->canPerform('reports_arrival_notices'),
                'route' => route('company.reports.arrival-notices'),
            ];

            $reportTypes['trips'] = [
                'name' => 'Reportes de Viajes',
                'description' => 'Información detallada sobre viajes realizados.',
                'icon' => 'truck',
                'color' => 'indigo',
                'available' => $this->canPerform('reports_trips'),
                'route' => route('company.reports.trips'),
            ];
        }

        // Reportes para empresas con rol "Cargas" o "Desconsolidador"
        if ($this->hasCompanyRole('Cargas') || $this->hasCompanyRole('Desconsolidador')) {
            $reportTypes['shipments'] = [
                'name' => 'Reportes de Cargas',
                'description' => 'Información detallada sobre cargas procesadas.',
                'icon' => 'archive',
                'color' => 'gray',
                'available' => $this->canPerform('reports_shipments'),
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
                'available' => $this->canPerform('reports_customs'),
                'route' => route('company.reports.customs'),
            ];
        }

        // Reportes de operadores (solo company-admin)
        if ($this->isCompanyAdmin()) {
            $reportTypes['operators'] = [
                'name' => 'Reportes de Operadores',
                'description' => 'Estadísticas y actividad de operadores de la empresa.',
                'icon' => 'user-group',
                'color' => 'teal',
                'available' => $this->canPerform('reports_operators'),
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
        // TODO: Implementar cuando estén los módulos de reportes
        // Los usuarios regulares solo ven sus propios reportes
        // Los company-admin ven todos los reportes de la empresa

        return [
            // Estructura de ejemplo:
            // [
            //     'type' => 'manifest',
            //     'name' => 'Manifiesto #12345',
            //     'generated_at' => Carbon::now()->subHours(2),
            //     'generated_by' => 'María González',
            //     'status' => 'completed',
            // ],
        ];
    }

    // =========================================================================
    // MÉTODOS AUXILIARES - QUERIES Y FILTROS
    // =========================================================================

    /**
     * Construir query base para manifiestos.
     */
    private function buildManifestsQuery($company)
    {
        // TODO: Implementar cuando esté el módulo de cargas
        return collect();
    }

    /**
     * Construir query base para conocimientos de embarque.
     */
    private function buildBillsOfLadingQuery($company)
    {
        // TODO: Implementar cuando esté el módulo de cargas
        return collect();
    }

    /**
     * Construir query base para reportes MIC/DTA.
     */
    private function buildMicdtaQuery($company)
    {
        // TODO: Implementar cuando esté el módulo de webservices
        return collect();
    }

    /**
     * Construir query base para cartas de aviso.
     */
    private function buildArrivalNoticesQuery($company)
    {
        // TODO: Implementar cuando esté el módulo de cargas
        return collect();
    }

    /**
     * Construir query base para reportes aduaneros.
     */
    private function buildCustomsQuery($company)
    {
        // TODO: Implementar cuando estén los módulos correspondientes
        return collect();
    }

    /**
     * Construir query base para cargas.
     */
    private function buildShipmentsQuery($company)
    {
        // TODO: Implementar cuando esté el módulo de cargas
        return collect();
    }

    /**
     * Construir query base para viajes.
     */
    private function buildTripsQuery($company)
    {
        // TODO: Implementar cuando esté el módulo de viajes
        return collect();
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
        // TODO: Implementar cuando esté el módulo de cargas
        return [
            'total' => 0,
            'this_month' => 0,
            'pending' => 0,
            'completed' => 0,
        ];
    }

    /**
     * Obtener estadísticas de conocimientos de embarque.
     */
    private function getBillsOfLadingStats($company): array
    {
        // TODO: Implementar cuando esté el módulo de cargas
        return [
            'total' => 0,
            'this_month' => 0,
            'pending' => 0,
            'completed' => 0,
        ];
    }

    /**
     * Obtener estadísticas MIC/DTA.
     */
    private function getMicdtaStats($company): array
    {
        // TODO: Implementar cuando esté el módulo de webservices
        return [
            'sent' => 0,
            'pending' => 0,
            'failed' => 0,
            'success_rate' => 0,
        ];
    }

    /**
     * Obtener estadísticas de cartas de aviso.
     */
    private function getArrivalNoticesStats($company): array
    {
        // TODO: Implementar cuando esté el módulo de cargas
        return [
            'sent' => 0,
            'pending' => 0,
            'this_month' => 0,
        ];
    }

    /**
     * Obtener estadísticas aduaneras.
     */
    private function getCustomsStats($company): array
    {
        // TODO: Implementar cuando estén los módulos correspondientes
        return [
            'reports_generated' => 0,
            'pending_submissions' => 0,
            'approved' => 0,
        ];
    }

    /**
     * Obtener estadísticas de cargas.
     */
    private function getShipmentsStats($company): array
    {
        // TODO: Implementar cuando esté el módulo de cargas
        return [
            'total' => 0,
            'this_month' => 0,
            'pending' => 0,
            'completed' => 0,
        ];
    }

    /**
     * Obtener estadísticas de viajes.
     */
    private function getTripsStats($company): array
    {
        // TODO: Implementar cuando esté el módulo de viajes
        return [
            'total' => 0,
            'this_month' => 0,
            'active' => 0,
            'completed' => 0,
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
            'destination' => [], // TODO: Obtener destinos disponibles
        ];
    }

    /**
     * Obtener filtros para conocimientos de embarque.
     */
    private function getBillsOfLadingFilters(): array
    {
        return [
            'status' => ['draft', 'issued', 'delivered'],
            'period' => ['today', 'week', 'month', 'quarter'],
            'shipper' => [], // TODO: Obtener cargadores disponibles
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
            'canExport' => $this->canPerform('reports_export'),
            'canViewManifests' => $this->hasCompanyRole('Cargas') && $this->canPerform('reports_manifests'),
            'canViewBillsOfLading' => $this->hasCompanyRole('Cargas') && $this->canPerform('reports_bills_of_lading'),
            'canViewMicdta' => $this->hasCompanyRole('Cargas') && $this->canPerform('reports_micdta'),
            'canViewArrivalNotices' => $this->hasCompanyRole('Cargas') && $this->canPerform('reports_arrival_notices'),
            'canViewCustoms' => $this->canPerform('reports_customs'),
            'canViewShipments' => ($this->hasCompanyRole('Cargas') || $this->hasCompanyRole('Desconsolidador')) && $this->canPerform('reports_shipments'),
            'canViewTrips' => $this->hasCompanyRole('Cargas') && $this->canPerform('reports_trips'),
            'canViewOperators' => $this->isCompanyAdmin() && $this->canPerform('reports_operators'),
        ];
    }

    /**
     * Verificar si puede exportar un tipo específico de reporte.
     */
    private function canExportReportType($reportType): bool
    {
        switch ($reportType) {
            case 'manifests':
                return $this->hasCompanyRole('Cargas') && $this->canPerform('reports_manifests');
            case 'bills-of-lading':
                return $this->hasCompanyRole('Cargas') && $this->canPerform('reports_bills_of_lading');
            case 'micdta':
                return $this->hasCompanyRole('Cargas') && $this->canPerform('reports_micdta');
            case 'arrival-notices':
                return $this->hasCompanyRole('Cargas') && $this->canPerform('reports_arrival_notices');
            case 'customs':
                return $this->canPerform('reports_customs');
            case 'shipments':
                return ($this->hasCompanyRole('Cargas') || $this->hasCompanyRole('Desconsolidador')) && $this->canPerform('reports_shipments');
            case 'trips':
                return $this->hasCompanyRole('Cargas') && $this->canPerform('reports_trips');
            case 'operators':
                return $this->isCompanyAdmin() && $this->canPerform('reports_operators');
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
}
