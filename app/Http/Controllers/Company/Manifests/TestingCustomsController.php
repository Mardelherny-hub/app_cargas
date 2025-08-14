<?php

namespace App\Http\Controllers\Company\Manifests;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Voyage;
use App\Models\Company;
use App\Services\Webservice\TestingCustomsService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

/**
 * CONTROLADOR PARA TESTING DE ENVÍOS A ADUANAS
 * 
 * Maneja las pruebas de envío a aduanas Argentina y Paraguay antes 
 * de realizar envíos reales. Basado en ManifestCustomsController existente.
 * 
 * FUNCIONALIDADES:
 * - Vista de selección de viajes para testing
 * - Ejecución de pruebas completas pre-envío
 * - Validación de datos y certificados
 * - Testing de conectividad con webservices
 * - Simulación de envíos en ambiente testing
 * - Reportes de resultados de testing
 * 
 * INTEGRACIÓN:
 * - Utiliza TestingCustomsService creado anteriormente
 * - Compatible con estructura de rutas existente
 * - Sigue patrones de ManifestCustomsController
 * - Respeta sistema de permisos de la aplicación
 */
class TestingCustomsController extends Controller
{
    /**
     * Vista principal para seleccionar viajes y ejecutar testing
     */
    public function index(Request $request)
    {
        // Verificar que el usuario tenga acceso a testing
        if (!$this->canPerformTesting()) {
            return redirect()->route('company.manifests.customs.index')
                ->with('error', 'No tiene permisos para realizar testing de envíos a aduanas.');
        }

        // Obtener viajes candidatos para testing (similar a ManifestCustomsController)
        $query = Voyage::with([
            'shipments.client', 
            'vessel',
            'webserviceTransactions'
        ])
        ->where('company_id', auth()->user()->company_id)
        ->whereHas('shipments') // Solo viajes con cargas
        ->whereIn('status', ['completed', 'in_progress', 'approved']); // Estados válidos para testing

        // Filtros adicionales
        if ($request->filled('voyage_number')) {
            $query->where('voyage_number', 'like', '%' . $request->voyage_number . '%');
        }

        if ($request->filled('vessel_id')) {
            $query->where('vessel_id', $request->vessel_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $voyages = $query->latest()->paginate(15);

        // Obtener estadísticas de testing
        $stats = $this->getTestingStats();

        // Obtener listas para filtros
        $filters = $this->getFilterData();

        return view('company.manifests.testing', compact('voyages', 'stats', 'filters'));
    }

    /**
     * Ejecutar prueba completa de un viaje específico
     */
    public function test(Request $request, $voyageId)
    {
        if (!$this->canPerformTesting()) {
            return response()->json([
                'success' => false,
                'message' => 'No tiene permisos para realizar testing.'
            ], 403);
        }

        $request->validate([
            'webservice_type' => 'required|in:anticipada,micdta,paraguay_customs',
            'environment' => 'required|in:testing',
            'test_type' => 'nullable|in:full,basic,connectivity_only'
        ]);

        try {
            // Obtener viaje con datos necesarios
            $voyage = $this->getVoyageForTesting($voyageId);
            
            // Obtener empresa del usuario
            $company = auth()->user()->userable;
            if (!$company instanceof Company) {
                throw new \Exception('Usuario no está asociado a una empresa válida');
            }

            // Crear servicio de testing
            $testingService = new TestingCustomsService($company);

            // Ejecutar prueba completa
            $testResults = $testingService->runCompleteTest($voyage, [
                'webservice_type' => $request->webservice_type,
                'environment' => $request->environment,
                'test_type' => $request->test_type ?? 'full',
                'user_id' => auth()->id()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Prueba completada correctamente',
                'results' => $testResults
            ]);

        } catch (\Exception $e) {
            Log::error('Error en testing de viaje', [
                'voyage_id' => $voyageId,
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error ejecutando prueba: ' . $e->getMessage(),
                'error_details' => [
                    'file' => basename($e->getFile()),
                    'line' => $e->getLine()
                ]
            ], 500);
        }
    }

    /**
     * Ejecutar testing masivo de múltiples viajes
     */
    public function testBatch(Request $request)
    {
        if (!$this->canPerformTesting()) {
            return back()->with('error', 'No tiene permisos para realizar testing masivo.');
        }

        $request->validate([
            'voyage_ids' => 'required|array|min:1|max:10', // Limitar a 10 para evitar timeout
            'voyage_ids.*' => 'exists:voyages,id',
            'webservice_type' => 'required|in:anticipada,micdta,paraguay_customs',
            'environment' => 'required|in:testing'
        ]);

        try {
            $company = auth()->user()->userable;
            if (!$company instanceof Company) {
                throw new \Exception('Usuario no está asociado a una empresa válida');
            }

            $testingService = new TestingCustomsService($company);
            $results = [
                'total' => count($request->voyage_ids),
                'passed' => 0,
                'failed' => 0,
                'warnings' => 0,
                'details' => []
            ];

            foreach ($request->voyage_ids as $voyageId) {
                try {
                    $voyage = $this->getVoyageForTesting($voyageId);
                    
                    $testResult = $testingService->runCompleteTest($voyage, [
                        'webservice_type' => $request->webservice_type,
                        'environment' => $request->environment,
                        'batch_mode' => true
                    ]);

                    // Contabilizar resultado
                    $status = $testResult['summary']['status'] ?? 'error';
                    switch ($status) {
                        case 'success':
                            $results['passed']++;
                            break;
                        case 'warning':
                            $results['warnings']++;
                            break;
                        default:
                            $results['failed']++;
                    }

                    $results['details'][$voyageId] = [
                        'voyage_number' => $voyage->voyage_number,
                        'status' => $status,
                        'summary' => $testResult['summary'] ?? null
                    ];

                } catch (\Exception $e) {
                    $results['failed']++;
                    $results['details'][$voyageId] = [
                        'voyage_number' => "ID:{$voyageId}",
                        'status' => 'error',
                        'error' => $e->getMessage()
                    ];
                }
            }

            // Generar mensaje de resultado
            $message = "Testing masivo completado: {$results['passed']} exitosos, " .
                      "{$results['warnings']} con advertencias, {$results['failed']} fallidos.";

            return redirect()->route('company.manifests.testing.index')
                ->with('success', $message)
                ->with('batch_results', $results);

        } catch (\Exception $e) {
            Log::error('Error en testing masivo', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage()
            ]);

            return back()->with('error', 'Error en testing masivo: ' . $e->getMessage());
        }
    }

    /**
     * Vista detallada de resultados de una prueba específica
     */
    public function showResults($testId)
    {
        // Esta funcionalidad podría implementarse más adelante
        // para mostrar resultados históricos de testing
        return back()->with('info', 'Vista de resultados detallados pendiente de implementación.');
    }

    /**
     * Exportar resultados de testing a Excel/PDF
     */
    public function exportResults(Request $request)
    {
        $request->validate([
            'format' => 'required|in:excel,pdf',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from'
        ]);

        // Esta funcionalidad podría implementarse más adelante
        return back()->with('info', 'Exportación de resultados pendiente de implementación.');
    }

    // ========================================
    // MÉTODOS PRIVADOS DE APOYO
    // ========================================

    /**
     * Verificar si el usuario puede realizar testing
     */
    private function canPerformTesting(): bool
    {
        $user = auth()->user();
        
        // Verificar que tenga rol apropiado
        if (!$user->hasAnyRole(['company-admin', 'user'])) {
            return false;
        }

        // Verificar que esté asociado a una empresa
        $company = $user->userable;
        if (!$company instanceof Company) {
            return false;
        }

        // Verificar que la empresa tenga certificado (opcional para testing)
        // En testing se puede generar certificado temporal
        
        return true;
    }

    /**
     * Obtener viaje para testing con validaciones
     */
    private function getVoyageForTesting($voyageId): Voyage
    {
        $voyage = Voyage::with([
            'shipments.client',
            'shipments.containers',
            'vessel',
            'origin_port',
            'destination_port'
        ])
        ->where('company_id', auth()->user()->company_id)
        ->findOrFail($voyageId);

        // Validaciones básicas
        if ($voyage->shipments()->count() === 0) {
            throw new \Exception('El viaje no tiene shipments para testing');
        }

        return $voyage;
    }

    /**
     * Obtener estadísticas de testing para el dashboard
     */
    private function getTestingStats(): array
    {
        $companyId = auth()->user()->company_id;

        // Estadísticas básicas de viajes disponibles para testing
        $stats = [
            'total_voyages' => Voyage::where('company_id', $companyId)
                ->whereHas('shipments')
                ->whereIn('status', ['completed', 'in_progress', 'approved'])
                ->count(),
            
            'tested_today' => 0, // Implementar cuando se agregue logging de tests
            'passed_tests' => 0,
            'failed_tests' => 0,
            'success_rate' => 0.0
        ];

        // Estas estadísticas podrían mejorarse con una tabla dedicada
        // para almacenar resultados de testing históricos

        return $stats;
    }

    /**
     * Obtener datos para filtros en la vista
     */
    private function getFilterData(): array
    {
        $companyId = auth()->user()->company_id;

        return [
            'vessels' => \App\Models\Vessel::where('company_id', $companyId)
                ->orderBy('name')
                ->pluck('name', 'id')
                ->toArray(),
            
            'statuses' => [
                'completed' => 'Completado',
                'in_progress' => 'En Progreso',
                'approved' => 'Aprobado'
            ],
            
            'webservice_types' => [
                'anticipada' => 'Información Anticipada (Argentina)',
                'micdta' => 'MIC/DTA (Argentina)',
                'paraguay_customs' => 'Manifiesto (Paraguay)'
            ]
        ];
    }
}