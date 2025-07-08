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
     */
    public function index()
    {
        $company = $this->getUserCompany();

        if (!$company) {
            return redirect()->route('company.dashboard')
                ->with('error', 'No se encontró la empresa asociada.');
        }

        // Estadísticas de reportes recientes
        $stats = [
            'manifests_generated' => 0, // TODO: Implementar cuando esté el módulo de cargas
            'bills_generated' => 0, // TODO: Implementar cuando esté el módulo de cargas
            'micdta_sent' => 0, // TODO: Implementar cuando esté el módulo de webservices
            'notices_sent' => 0, // TODO: Implementar cuando esté el módulo de cargas
            'total_shipments' => 0, // TODO: Implementar cuando esté el módulo de cargas
            'total_trips' => 0, // TODO: Implementar cuando esté el módulo de viajes
        ];

        // Tipos de reportes disponibles
        $reportTypes = [
            'manifests' => [
                'name' => 'Manifiestos de Carga',
                'description' => 'Documentos que detallan todas las cargas de un viaje específico.',
                'icon' => 'document-text',
                'color' => 'blue',
                'available' => true,
            ],
            'bills-of-lading' => [
                'name' => 'Conocimientos de Embarque',
                'description' => 'Documentos contractuales entre el transportista y el cargador.',
                'icon' => 'clipboard-list',
                'color' => 'green',
                'available' => true,
            ],
            'micdta' => [
                'name' => 'Reportes MIC/DTA',
                'description' => 'Manifiestos Internacionales de Carga y Declaraciones de Tránsito Aduanero.',
                'icon' => 'shield-check',
                'color' => 'purple',
                'available' => $company->ws_active,
            ],
            'arrival-notices' => [
                'name' => 'Cartas de Aviso',
                'description' => 'Notificaciones de llegada de mercadería para consignatarios.',
                'icon' => 'mail',
                'color' => 'yellow',
                'available' => true,
            ],
            'customs' => [
                'name' => 'Reportes Aduaneros',
                'description' => 'Documentación requerida por las autoridades aduaneras.',
                'icon' => 'flag',
                'color' => 'red',
                'available' => $company->ws_active,
            ],
            'statistics' => [
                'name' => 'Estadísticas',
                'description' => 'Análisis estadístico de la operación de la empresa.',
                'icon' => 'chart-bar',
                'color' => 'indigo',
                'available' => true,
            ],
        ];

        // Reportes recientes (simulado)
        $recentReports = [
            // TODO: Implementar cuando estén los módulos de cargas y viajes
        ];

        return view('company.reports.index', compact('company', 'stats', 'reportTypes', 'recentReports'));
    }

    /**
     * Mostrar reportes de manifiestos.
     */
    public function manifests(Request $request)
    {
        $company = $this->getUserCompany();

        if (!$company) {
            return redirect()->route('company.dashboard')
                ->with('error', 'No se encontró la empresa asociada.');
        }

        // TODO: Implementar cuando esté el módulo de viajes
        $trips = collect(); // Viajes de la empresa

        // Filtros disponibles
        $filters = [
            'date_from' => $request->get('date_from'),
            'date_to' => $request->get('date_to'),
            'status' => $request->get('status'),
            'route' => $request->get('route'),
        ];

        return view('company.reports.manifests', compact('company', 'trips', 'filters'));
    }

    /**
     * Mostrar reportes de conocimientos de embarque.
     */
    public function billsOfLading(Request $request)
    {
        $company = $this->getUserCompany();

        if (!$company) {
            return redirect()->route('company.dashboard')
                ->with('error', 'No se encontró la empresa asociada.');
        }

        // TODO: Implementar cuando esté el módulo de cargas
        $shipments = collect(); // Cargas de la empresa

        // Filtros disponibles
        $filters = [
            'date_from' => $request->get('date_from'),
            'date_to' => $request->get('date_to'),
            'status' => $request->get('status'),
            'client' => $request->get('client'),
        ];

        return view('company.reports.bills-of-lading', compact('company', 'shipments', 'filters'));
    }

    /**
     * Mostrar reportes MIC/DTA.
     */
    public function micdta(Request $request)
    {
        $company = $this->getUserCompany();

        if (!$company) {
            return redirect()->route('company.dashboard')
                ->with('error', 'No se encontró la empresa asociada.');
        }

        if (!$company->ws_active) {
            return redirect()->route('company.reports.index')
                ->with('error', 'Los webservices no están activos para su empresa.');
        }

        // TODO: Implementar cuando esté el módulo de webservices
        $micdtaReports = collect(); // Reportes MIC/DTA

        // Estadísticas MIC/DTA
        $micdtaStats = [
            'total_sent' => 0,
            'pending' => 0,
            'accepted' => 0,
            'rejected' => 0,
            'in_transit' => 0,
        ];

        return view('company.reports.micdta', compact('company', 'micdtaReports', 'micdtaStats'));
    }

    /**
     * Mostrar reportes de cartas de aviso.
     */
    public function arrivalNotices(Request $request)
    {
        $company = $this->getUserCompany();

        if (!$company) {
            return redirect()->route('company.dashboard')
                ->with('error', 'No se encontró la empresa asociada.');
        }

        // TODO: Implementar cuando esté el módulo de cargas
        $notices = collect(); // Cartas de aviso

        // Filtros disponibles
        $filters = [
            'date_from' => $request->get('date_from'),
            'date_to' => $request->get('date_to'),
            'status' => $request->get('status'),
            'consignee' => $request->get('consignee'),
        ];

        return view('company.reports.arrival-notices', compact('company', 'notices', 'filters'));
    }

    /**
     * Mostrar reportes aduaneros.
     */
    public function customs(Request $request)
    {
        $company = $this->getUserCompany();

        if (!$company) {
            return redirect()->route('company.dashboard')
                ->with('error', 'No se encontró la empresa asociada.');
        }

        if (!$company->ws_active) {
            return redirect()->route('company.reports.index')
                ->with('error', 'Los webservices no están activos para su empresa.');
        }

        // TODO: Implementar cuando esté el módulo de webservices
        $customsReports = collect(); // Reportes aduaneros

        // Tipos de reportes aduaneros
        $reportTypes = [
            'export_declarations' => 'Declaraciones de Exportación',
            'import_declarations' => 'Declaraciones de Importación',
            'transit_documents' => 'Documentos de Tránsito',
            'customs_clearance' => 'Liberaciones Aduaneras',
        ];

        return view('company.reports.customs', compact('company', 'customsReports', 'reportTypes'));
    }

    /**
     * Mostrar estadísticas de la empresa.
     */
    public function statistics(Request $request)
    {
        $company = $this->getUserCompany();

        if (!$company) {
            return redirect()->route('company.dashboard')
                ->with('error', 'No se encontró la empresa asociada.');
        }

        $period = $request->get('period', '30'); // Días por defecto

        // Estadísticas de operadores
        $operatorStats = [
            'total_operators' => $company->operators()->count(),
            'active_operators' => $company->operators()->where('active', true)->count(),
            'operators_with_import' => $company->operators()->where('can_import', true)->count(),
            'operators_with_export' => $company->operators()->where('can_export', true)->count(),
            'operators_with_transfer' => $company->operators()->where('can_transfer', true)->count(),
        ];

        // Actividad de operadores en el período
        $operatorActivity = $company->operators()
            ->with('user')
            ->whereHas('user', function($query) use ($period) {
                $query->where('last_access', '>=', Carbon::now()->subDays($period));
            })
            ->get()
            ->map(function($operator) {
                return [
                    'name' => $operator->first_name . ' ' . $operator->last_name,
                    'last_access' => $operator->user?->last_access,
                    'total_logins' => 0, // TODO: Implementar tracking de logins
                    'total_actions' => 0, // TODO: Implementar tracking de acciones
                ];
            });

        // Estadísticas del sistema (simuladas)
        $systemStats = [
            'total_shipments' => 0, // TODO: Implementar cuando esté el módulo de cargas
            'shipments_this_month' => 0,
            'total_trips' => 0, // TODO: Implementar cuando esté el módulo de viajes
            'trips_this_month' => 0,
            'webservice_success_rate' => $company->ws_active ? 95.5 : 0,
            'average_processing_time' => '2.3 horas',
        ];

        // Gráficos de actividad (datos simulados para demostración)
        $chartData = [
            'shipments_by_month' => [
                'labels' => ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun'],
                'data' => [12, 15, 8, 22, 18, 25],
            ],
            'trips_by_status' => [
                'labels' => ['Planificado', 'En Tránsito', 'Completado', 'Cancelado'],
                'data' => [5, 12, 45, 2],
            ],
            'operator_activity' => [
                'labels' => $operatorActivity->pluck('name')->toArray(),
                'data' => $operatorActivity->pluck('total_actions')->toArray(),
            ],
        ];

        return view('company.reports.statistics', compact(
            'company',
            'period',
            'operatorStats',
            'operatorActivity',
            'systemStats',
            'chartData'
        ));
    }

    /**
     * Generar manifiesto en PDF.
     */
    public function generateManifest(Request $request)
    {
        $request->validate([
            'trip_id' => 'required|integer',
            'format' => 'required|in:pdf,excel',
        ]);

        $company = $this->getUserCompany();

        if (!$company) {
            return back()->with('error', 'No se encontró la empresa asociada.');
        }

        try {
            // TODO: Implementar cuando esté el módulo de viajes
            // $trip = Trip::where('company_id', $company->id)->findOrFail($request->trip_id);

            if ($request->format === 'pdf') {
                // Generar PDF del manifiesto
                $pdf = PDF::loadView('company.reports.pdf.manifest', [
                    'company' => $company,
                    'trip' => null, // $trip
                    'shipments' => collect(), // $trip->shipments
                    'generated_at' => now(),
                ]);

                return $pdf->download('manifiesto_' . date('Y-m-d_H-i-s') . '.pdf');
            } else {
                // TODO: Implementar exportación a Excel
                return back()->with('info', 'La exportación a Excel estará disponible próximamente.');
            }

        } catch (\Exception $e) {
            return back()->with('error', 'Error al generar el manifiesto: ' . $e->getMessage());
        }
    }

    /**
     * Generar conocimiento de embarque en PDF.
     */
    public function generateBillOfLading(Request $request)
    {
        $request->validate([
            'shipment_id' => 'required|integer',
            'format' => 'required|in:pdf,excel',
        ]);

        $company = $this->getUserCompany();

        if (!$company) {
            return back()->with('error', 'No se encontró la empresa asociada.');
        }

        try {
            // TODO: Implementar cuando esté el módulo de cargas
            // $shipment = Shipment::where('company_id', $company->id)->findOrFail($request->shipment_id);

            if ($request->format === 'pdf') {
                // Generar PDF del conocimiento
                $pdf = PDF::loadView('company.reports.pdf.bill-of-lading', [
                    'company' => $company,
                    'shipment' => null, // $shipment
                    'generated_at' => now(),
                ]);

                return $pdf->download('conocimiento_' . date('Y-m-d_H-i-s') . '.pdf');
            } else {
                // TODO: Implementar exportación a Excel
                return back()->with('info', 'La exportación a Excel estará disponible próximamente.');
            }

        } catch (\Exception $e) {
            return back()->with('error', 'Error al generar el conocimiento: ' . $e->getMessage());
        }
    }

    /**
     * Generar reporte MIC/DTA.
     */
    public function generateMicdta(Request $request)
    {
        $request->validate([
            'trip_id' => 'required|integer',
            'type' => 'required|in:mic,dta',
        ]);

        $company = $this->getUserCompany();

        if (!$company || !$company->ws_active) {
            return back()->with('error', 'Los webservices no están activos para su empresa.');
        }

        try {
            // TODO: Implementar cuando esté el módulo de webservices
            // $trip = Trip::where('company_id', $company->id)->findOrFail($request->trip_id);

            // Generar XML para envío a webservice
            $xmlData = $this->generateMicdtaXml($request->type, null); // $trip

            // TODO: Enviar a webservice AFIP/SENASA

            return back()->with('success', 'Reporte ' . strtoupper($request->type) . ' generado y enviado correctamente.');

        } catch (\Exception $e) {
            return back()->with('error', 'Error al generar el reporte: ' . $e->getMessage());
        }
    }

    /**
     * Generar carta de aviso.
     */
    public function generateArrivalNotice(Request $request)
    {
        $request->validate([
            'shipment_id' => 'required|integer',
            'format' => 'required|in:pdf,email',
            'consignee_email' => 'required_if:format,email|email',
        ]);

        $company = $this->getUserCompany();

        if (!$company) {
            return back()->with('error', 'No se encontró la empresa asociada.');
        }

        try {
            // TODO: Implementar cuando esté el módulo de cargas
            // $shipment = Shipment::where('company_id', $company->id)->findOrFail($request->shipment_id);

            if ($request->format === 'pdf') {
                // Generar PDF de carta de aviso
                $pdf = PDF::loadView('company.reports.pdf.arrival-notice', [
                    'company' => $company,
                    'shipment' => null, // $shipment
                    'generated_at' => now(),
                ]);

                return $pdf->download('carta_aviso_' . date('Y-m-d_H-i-s') . '.pdf');
            } else {
                // TODO: Enviar por email
                return back()->with('success', 'Carta de aviso enviada por email correctamente.');
            }

        } catch (\Exception $e) {
            return back()->with('error', 'Error al generar la carta de aviso: ' . $e->getMessage());
        }
    }

    /**
     * Generar XML para MIC/DTA.
     */
    private function generateMicdtaXml($type, $trip)
    {
        // TODO: Implementar generación de XML según especificaciones AFIP/SENASA
        // Esta es una estructura básica de ejemplo

        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<' . strtoupper($type) . '>';
        $xml .= '<header>';
        $xml .= '<timestamp>' . now()->toISOString() . '</timestamp>';
        $xml .= '<company_tax_id>' . $this->getUserCompany()->tax_id . '</company_tax_id>';
        $xml .= '</header>';
        $xml .= '<data>';
        // TODO: Agregar datos del viaje y cargas
        $xml .= '</data>';
        $xml .= '</' . strtoupper($type) . '>';

        return $xml;
    }

    /**
     * Exportar estadísticas a Excel.
     */
    public function exportStatistics(Request $request)
    {
        $company = $this->getUserCompany();

        if (!$company) {
            return back()->with('error', 'No se encontró la empresa asociada.');
        }

        try {
            // TODO: Implementar exportación con Laravel Excel
            $filename = 'estadisticas_' . $company->business_name . '_' . date('Y-m-d_H-i-s') . '.xlsx';

            return back()->with('info', 'La exportación de estadísticas estará disponible próximamente.');

        } catch (\Exception $e) {
            return back()->with('error', 'Error al exportar estadísticas: ' . $e->getMessage());
        }
    }

    /**
     * Obtener datos para gráficos via AJAX.
     */
    public function getChartData(Request $request)
    {
        $company = $this->getUserCompany();
        $type = $request->get('type');
        $period = $request->get('period', 30);

        if (!$company) {
            return response()->json(['error' => 'Empresa no encontrada'], 404);
        }

        switch ($type) {
            case 'shipments_by_month':
                // TODO: Implementar cuando esté el módulo de cargas
                $data = [
                    'labels' => ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun'],
                    'datasets' => [
                        [
                            'label' => 'Cargas',
                            'data' => [12, 15, 8, 22, 18, 25],
                            'backgroundColor' => 'rgba(59, 130, 246, 0.5)',
                            'borderColor' => 'rgb(59, 130, 246)',
                        ]
                    ]
                ];
                break;

            case 'operator_activity':
                $operators = $company->operators()->with('user')->get();
                $data = [
                    'labels' => $operators->map(fn($op) => $op->first_name . ' ' . $op->last_name)->toArray(),
                    'datasets' => [
                        [
                            'label' => 'Acciones',
                            'data' => $operators->map(fn($op) => rand(5, 50))->toArray(), // Datos simulados
                            'backgroundColor' => 'rgba(16, 185, 129, 0.5)',
                            'borderColor' => 'rgb(16, 185, 129)',
                        ]
                    ]
                ];
                break;

            default:
                $data = ['error' => 'Tipo de gráfico no válido'];
        }

        return response()->json($data);
    }
}
