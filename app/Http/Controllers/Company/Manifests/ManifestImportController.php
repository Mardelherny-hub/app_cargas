<?php

namespace App\Http\Controllers\Company\Manifests;

use App\Http\Controllers\Controller;
use App\Services\Parsers\ManifestParserFactory;
use App\Models\Vessel;
use App\ValueObjects\ManifestParseResult;
use App\Models\ManifestImport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Exception;
use Illuminate\Support\Str;

/**
 * CONTROLADOR UNIFICADO PARA IMPORTACIÓN DE MANIFIESTOS - VERSIÓN CORREGIDA
 * 
 * CORRECCIÓN CRÍTICA APLICADA:
 * ✅ Preservar extensión original del archivo al almacenar
 * ✅ Mejorar detección de parser basada en contenido Y extensión
 * ✅ Logging detallado para debugging de auto-detección
 * ✅ Fallback inteligente cuando falla detección por contenido
 * 
 * FORMATOS SOPORTADOS:
 * - KLine.DAT (✅ integrado)
 * - PARANA.xlsx (✅ integrado) 
 * - Guaran.csv (✅ integrado)
 * - Login.xml (pendiente)
 * - TFP.txt (pendiente)
 * - CMSP.EDI (pendiente)
 * - Navsur.txt (pendiente)
 */
class ManifestImportController extends Controller
{
    protected ManifestParserFactory $parserFactory;

    public function __construct()
    {
        $this->parserFactory = new ManifestParserFactory();
    }

    /**
     * Mostrar formulario de importación con información de formatos soportados
     */
    public function showForm()
    {
        $supportedFormats = $this->parserFactory->getSupportedFormats();
        $formatStats = $this->parserFactory->getFormatStatistics();

        // Obtener embarcaciones disponibles de la empresa
        $company = auth()->user()->company;
        $vessels = Vessel::where('company_id', $company->id)
            ->where('active', true)
            ->where('operational_status', 'active')
            ->orderBy('name')
            ->get();

        return view('company.manifests.import', [
            'supportedFormats' => $supportedFormats,
            'formatStats' => $formatStats,
            'vessels' => $vessels
        ]);
    }

    /**
     * Procesar archivo importado con auto-detección de formato - CORREGIDO
     */
    public function store(Request $request)
    {
        $request->validate([
            'manifest_file' => 'required|file|max:10240', // 10MB max
            'vessel_id' => 'required|exists:vessels,id'
        ], [
            'manifest_file.required' => 'Debe seleccionar un archivo para importar.',
            'manifest_file.file' => 'El archivo seleccionado no es válido.',
            'manifest_file.max' => 'El archivo no puede ser mayor a 10MB.'
        ]);

        // Verificar que el vessel pertenece a la empresa del usuario
        $company = auth()->user()->company;
        $vessel = Vessel::where('id', $request->vessel_id)
            ->where('company_id', $company->id)
            ->where('active', true)
            ->where('operational_status', 'active')
            ->first();

        if (!$vessel) {
            return back()->with('error', 'La embarcación seleccionada no es válida o no pertenece a su empresa.');
        }

        $uploadedFile = $request->file('manifest_file');
        $originalName = $uploadedFile->getClientOriginalName();
        $originalExtension = $uploadedFile->getClientOriginalExtension();

        // ✅ CORREGIDO: Almacenar archivo preservando la extensión original
        $fileName = $this->generateUniqueFileName($originalName, $originalExtension);
        $path = $uploadedFile->storeAs('imports/manifests', $fileName, 'local');
        $fullPath = Storage::path($path);

        Log::info('Starting manifest import process - IMPROVED', [
            'original_name' => $originalName,
            'original_extension' => $originalExtension,
            'stored_filename' => $fileName,
            'stored_path' => $path,
            'full_path' => $fullPath,
            'file_size' => filesize($fullPath),
            'user_id' => auth()->id(),
            'company_id' => auth()->user()->company_id
        ]);

        try {
            // ✅ MEJORADO: Auto-detectar parser con información adicional
            $parser = $this->parserFactory->getParser($fullPath);
            
            Log::info('Parser detected for import', [
                'parser_class' => get_class($parser),
                'original_name' => $originalName,
                'detected_extension' => pathinfo($fullPath, PATHINFO_EXTENSION),
                'file_format' => $parser->getFormatInfo()['name'] ?? 'Unknown'
            ]);

            // Procesar archivo SIN transacción externa (los parsers manejan sus propias transacciones)
            $result = $parser->parse($fullPath, ['vessel_id' => $vessel->id]);

            // Limpiar archivo temporal
            Storage::delete($path);

            // Manejar resultado según éxito/fallo
            return $this->handleImportResult($result, $originalName);

        } catch (Exception $e) {
            // Limpiar archivo temporal en caso de error
            Storage::delete($path);
            
            Log::error('Critical error during manifest import', [
                'original_name' => $originalName,
                'original_extension' => $originalExtension,
                'stored_path' => $path,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id(),
                'compatible_parsers' => $this->getCompatibleParsersForDebugging($fullPath, $originalExtension)
            ]);

            return back()
                ->withInput()
                ->with('error', 'Error crítico durante la importación: ' . $e->getMessage())
                ->with('error_details', [
                    'file' => $originalName,
                    'extension' => $originalExtension,
                    'error_type' => 'critical_error',
                    'suggestion' => $this->generateSuggestionBasedOnExtension($originalExtension)
                ]);
        }
    }

    /**
     * Generar nombre único preservando extensión original - NUEVO
     */
    protected function generateUniqueFileName(string $originalName, string $extension): string
    {
        // Limpiar nombre original para seguridad
        $baseName = pathinfo($originalName, PATHINFO_FILENAME);
        $safeName = Str::slug($baseName);
        
        // Si el nombre queda vacío después de limpiar, usar timestamp
        if (empty($safeName)) {
            $safeName = 'manifest_' . now()->format('YmdHis');
        }

        // Generar sufijo único
        $uniqueSuffix = '_' . now()->format('YmdHis') . '_' . Str::random(8);
        
        // Asegurar que la extensión tenga punto
        $cleanExtension = $extension ? '.' . ltrim($extension, '.') : '';
        
        return $safeName . $uniqueSuffix . $cleanExtension;
    }

    /**
     * Obtener parsers compatibles para debugging - NUEVO
     */
    protected function getCompatibleParsersForDebugging(string $filePath, string $originalExtension): array
    {
        try {
            if (file_exists($filePath)) {
                return $this->parserFactory->getCompatibleParsers($filePath);
            }
        } catch (Exception $e) {
            Log::warning('Error getting compatible parsers for debugging', [
                'error' => $e->getMessage()
            ]);
        }

        return ['original_extension' => $originalExtension];
    }

    /**
     * Generar sugerencia basada en extensión - NUEVO
     */
    protected function generateSuggestionBasedOnExtension(string $extension): string
    {
        $suggestions = [
            'dat' => 'Verifique que el archivo .DAT tenga formato KLine válido con registros BLNOREC.',
            'xlsx' => 'Verifique que el archivo Excel tenga el formato esperado de PARANA.',
            'csv' => 'Verifique que el archivo CSV tenga la estructura correcta de Guaraní.',
            'xml' => 'Los archivos XML aún no están soportados (próxima versión).',
            'txt' => 'Verifique que el archivo de texto tenga formato válido.',
            'edi' => 'Los archivos EDI aún no están soportados (próxima versión).',
            '' => 'El archivo no tiene extensión. Asegúrese de que sea un formato soportado (.dat, .xlsx, .csv).'
        ];

        return $suggestions[strtolower($extension)] ?? 
               'Verifique que el archivo tenga un formato soportado: .dat, .xlsx, .csv, .xml, .txt, .edi';
    }

    /**
     * Mostrar historial de importaciones
     */
    public function history(Request $request)
    {
        $company = auth()->user()->company;
        
        if (!$company) {
            return redirect()->route('company.manifests.import.index')
                ->with('error', 'No se encontró la empresa asociada.');
        }

        // Obtener importaciones de la empresa con relaciones
        $imports = ManifestImport::forCompany($company->id)
            ->with(['user', 'voyage', 'voyage.originPort', 'voyage.destinationPort'])
            ->latest()
            ->paginate(20);

        return view('company.manifests.import-history', [
            'imports' => $imports,
            'company' => $company
        ]);
    }

    /**
     * Mostrar detalles de una importación específica
     */
    public function showImport(Request $request, ManifestImport $import)
    {
        // Verificar que pertenece a la empresa del usuario
        $company = auth()->user()->company;
        
        if ($import->company_id !== $company->id) {
            abort(403, 'No tiene permisos para ver esta importación.');
        }

        // Cargar relaciones necesarias
        $import->load(['user', 'voyage', 'revertedByUser']);

        return view('company.manifests.import-details', [
            'import' => $import,
            'company' => $company
        ]);
    }

    /**
     * Manejar resultado de importación y generar respuesta apropiada
     */
    protected function handleImportResult(ManifestParseResult $result, string $fileName): \Illuminate\Http\RedirectResponse
    {
        $stats = $result->getStatsSummary();

        // Preparar datos para el reporte
        $reportData = $this->prepareReportData($result, $fileName, $stats);

        if ($result->isSuccessful()) {
            // Importación completamente exitosa
            Log::info('Manifest import completed successfully', [
                'file_name' => $fileName,
                'stats' => $stats
            ]);

            return redirect()
                ->route('company.manifests.import.report')
                ->with('import_report_data', $reportData)
                ->with('success', $this->buildSuccessMessage($result, $fileName));

        } elseif ($result->success && $result->hasWarnings()) {
            // Importación exitosa con advertencias
            Log::warning('Manifest import completed with warnings', [
                'file_name' => $fileName,
                'stats' => $stats,
                'warnings' => $result->warnings
            ]);

            return redirect()
                ->route('company.manifests.import.report')
                ->with('import_report_data', $reportData)
                ->with('warning', $this->buildWarningMessage($result, $fileName));

        } else {
            // Importación falló
            Log::error('Manifest import failed', [
                'file_name' => $fileName,
                'stats' => $stats,
                'errors' => $result->errors
            ]);

            // Para errores, redirigir al reporte también pero con datos de error
            return redirect()
                ->route('company.manifests.import.report')
                ->with('import_report_data', $reportData)
                ->with('error', 'La importación falló: ' . implode('; ', $result->errors));
        }
    }
    /**
     * Construir mensaje de éxito detallado
     */
    protected function buildSuccessMessage(ManifestParseResult $result, string $fileName): string
    {
        $stats = $result->getStatsSummary();
        
        $message = "✅ Archivo '{$fileName}' importado exitosamente.";
        
        if ($result->voyage) {
            $message .= " Viaje: {$result->voyage->voyage_number}";
        }
        
        if (!empty($stats)) {
            $details = [];
            if (isset($stats['bills'])) $details[] = "{$stats['bills']} conocimientos";
            if (isset($stats['shipments'])) $details[] = "{$stats['shipments']} envíos";
            if (isset($stats['items'])) $details[] = "{$stats['items']} items";
            
            if (!empty($details)) {
                $message .= " (" . implode(', ', $details) . ")";
            }
        }
        
        return $message;
    }

    /**
     * Construir mensaje de advertencia detallado
     */
    protected function buildWarningMessage(ManifestParseResult $result, string $fileName): string
    {
        $stats = $result->getStatsSummary();
        
        $message = "⚠️ Archivo '{$fileName}' importado con advertencias.";
        
        if ($result->voyage) {
            $message .= " Viaje: {$result->voyage->voyage_number}";
        }
        
        if (!empty($stats)) {
            $details = [];
            if (isset($stats['bills'])) $details[] = "{$stats['bills']} conocimientos";
            if (isset($stats['warnings'])) $details[] = "{$stats['warnings']} advertencias";
            
            if (!empty($details)) {
                $message .= " (" . implode(', ', $details) . ")";
            }
        }
        
        $message .= " Revise los detalles antes de continuar.";
        
        return $message;
    }

    /**
     * Preparar datos para el reporte de importación
     */
    protected function prepareReportData(ManifestParseResult $result, string $fileName, array $stats): array
    {
        $user = auth()->user();
        
        $reportData = [
            'importResult' => [
                'success' => $result->isSuccessful(),
                'hasWarnings' => $result->hasWarnings(),
            ],
            'fileInfo' => [
                'name' => $fileName,
                'imported_at' => now()->format('d/m/Y H:i:s'),
                'imported_by' => $user->name ?? 'Usuario',
            ],
            'stats' => $stats,
            'voyage' => null,
            'createdRecords' => [],
            'warnings' => $result->warnings ?? [],
            'errors' => $result->errors ?? [],
        ];

        // Agregar datos del viaje si existe
        if ($result->voyage) {
            $reportData['voyage'] = [
                'id' => $result->voyage->id,
                'voyage_number' => $result->voyage->voyage_number,
                'status' => $result->voyage->status ?? 'planning',
                'vessel_name' => $result->voyage->vessel->name ?? null,
                'origin_port' => $result->voyage->originPort->name ?? null,
                'destination_port' => $result->voyage->destinationPort->name ?? null,
                'departure_date' => $result->voyage->departure_date ? $result->voyage->departure_date->format('d/m/Y') : null,
            ];
        }

        // Agregar registros creados
        $reportData['createdRecords'] = [
            'billsOfLading' => $this->formatBillsOfLading($result->billsOfLading ?? []),
            'containers' => $this->formatContainers($result->containers ?? []),
        ];

        return $reportData;
    }

    /**
     * Formatear datos de Bills of Lading para el reporte
     */
    protected function formatBillsOfLading(array $billsOfLading): array
    {
        $formatted = [];
        
        foreach ($billsOfLading as $bl) {
            $formatted[] = [
                'id' => $bl->id,
                'bl_number' => $bl->bl_number ?? 'N/A',
                'shipper_name' => $bl->shipper->legal_name ?? 'N/A',
                'consignee_name' => $bl->consignee->legal_name ?? 'N/A',
                'items_count' => $bl->shipmentItems ? $bl->shipmentItems->count() : 0,
            ];
        }
        
        return $formatted;
    }

    /**
     * Formatear datos de contenedores para el reporte
     */
    protected function formatContainers(array $containers): array
    {
        $formatted = [];
        
        foreach ($containers as $container) {
            $formatted[] = [
                'id' => $container->id ?? null,
                'container_number' => $container->container_number ?? $container['number'] ?? 'N/A',
                'container_type' => $container->container_type ?? $container['type'] ?? 'N/A',
                'gross_weight' => $container->gross_weight_kg ?? $container['gross_weight'] ?? 0,
            ];
        }
        
        return $formatted;
    }

    /**
     * Mostrar reporte detallado de importación
     */
    public function showReport(Request $request)
    {
        // Verificar que venimos de una importación exitosa
        if (!$request->session()->has('import_report_data')) {
            return redirect()->route('company.manifests.import.index')
                ->with('error', 'No hay datos de importación para mostrar.');
        }

        $reportData = $request->session()->get('import_report_data');
        
        // Limpiar datos de sesión después de obtenerlos
        $request->session()->forget('import_report_data');

        return view('company.manifests.import-report', $reportData);
    }
}