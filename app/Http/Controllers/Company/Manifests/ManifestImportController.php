<?php

namespace App\Http\Controllers\Company\Manifests;

use App\Http\Controllers\Controller;
use App\Services\Parsers\ManifestParserFactory;
use App\ValueObjects\ManifestParseResult;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Exception;

/**
 * CONTROLADOR UNIFICADO PARA IMPORTACIÓN DE MANIFIESTOS
 * 
 * Maneja la importación de todos los tipos de manifiestos usando
 * auto-detección de formato y parsers especializados.
 * 
 * FORMATOS SOPORTADOS:
 * - KLine.DAT (ya integrado)
 * - PARANA.xlsx (pendiente)
 * - Guaran.csv (pendiente)
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

        return view('company.manifests.import', [
            'supportedFormats' => $supportedFormats,
            'formatStats' => $formatStats
        ]);
    }

    /**
     * Procesar archivo importado con auto-detección de formato
     */
    public function store(Request $request)
    {
        $request->validate([
            'manifest_file' => 'required|file|max:10240', // 10MB max
        ], [
            'manifest_file.required' => 'Debe seleccionar un archivo para importar.',
            'manifest_file.file' => 'El archivo seleccionado no es válido.',
            'manifest_file.max' => 'El archivo no puede ser mayor a 10MB.'
        ]);

        // Almacenar archivo temporalmente
        $originalName = $request->file('manifest_file')->getClientOriginalName();
        $path = $request->file('manifest_file')->store('imports/manifests', 'local');
        $fullPath = Storage::path($path);

        Log::info('Starting manifest import process', [
            'original_name' => $originalName,
            'stored_path' => $path,
            'full_path' => $fullPath,
            'file_size' => filesize($fullPath),
            'user_id' => auth()->id(),
            'company_id' => auth()->user()->company_id
        ]);

        try {
            // Auto-detectar parser apropiado
            $parser = $this->parserFactory->getParser($fullPath);
            
            Log::info('Parser detected for import', [
                'parser_class' => get_class($parser),
                'original_name' => $originalName
            ]);

            // Procesar archivo en transacción
            $result = DB::transaction(function () use ($parser, $fullPath, $originalName) {
                return $parser->parse($fullPath);
            });

            // Limpiar archivo temporal
            Storage::delete($path);

            // Manejar resultado según éxito/fallo
            return $this->handleImportResult($result, $originalName);

        } catch (Exception $e) {
            // Limpiar archivo temporal en caso de error
            Storage::delete($path);
            
            Log::error('Critical error during manifest import', [
                'original_name' => $originalName,
                'stored_path' => $path,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id()
            ]);

            return back()
                ->withInput()
                ->with('error', 'Error crítico durante la importación: ' . $e->getMessage())
                ->with('error_details', [
                    'file' => $originalName,
                    'error_type' => 'critical_error',
                    'suggestion' => 'Verifique que el archivo tenga el formato correcto y vuelva a intentar.'
                ]);
        }
    }

    /**
     * Manejar resultado de importación y generar respuesta apropiada
     */
    protected function handleImportResult(ManifestParseResult $result, string $fileName): \Illuminate\Http\RedirectResponse
    {
        $stats = $result->getStatsSummary();

        if ($result->isSuccessful()) {
            // Importación completamente exitosa
            Log::info('Manifest import completed successfully', [
                'file_name' => $fileName,
                'stats' => $stats
            ]);

            $message = $this->buildSuccessMessage($result, $fileName);
            
            return redirect()
                ->route('company.manifests.index')
                ->with('success', $message)
                ->with('import_stats', $stats)
                ->with('voyage_id', $result->voyage?->id);

        } elseif ($result->success && $result->hasWarnings()) {
            // Importación exitosa con advertencias
            Log::warning('Manifest import completed with warnings', [
                'file_name' => $fileName,
                'stats' => $stats,
                'warnings' => $result->warnings
            ]);

            $message = $this->buildWarningMessage($result, $fileName);
            
            return redirect()
                ->route('company.manifests.index')
                ->with('warning', $message)
                ->with('import_stats', $stats)
                ->with('warnings', $result->warnings)
                ->with('voyage_id', $result->voyage?->id);

        } else {
            // Importación fallida
            Log::error('Manifest import failed', [
                'file_name' => $fileName,
                'stats' => $stats,
                'errors' => $result->errors
            ]);

            $message = $this->buildErrorMessage($result, $fileName);
            
            return back()
                ->withInput()
                ->with('error', $message)
                ->with('import_errors', $result->errors)
                ->with('import_stats', $stats);
        }
    }

    /**
     * Construir mensaje de éxito
     */
    protected function buildSuccessMessage(ManifestParseResult $result, string $fileName): string
    {
        $stats = $result->getStatsSummary();
        
        $message = "✅ Archivo '{$fileName}' importado exitosamente.\n\n";
        
        if ($result->voyage) {
            $message .= "🚢 Viaje creado: {$result->voyage->voyage_number}\n";
        }
        
        $message .= "📊 Resumen:\n";
        $message .= "• {$stats['shipments_count']} embarque(s)\n";
        $message .= "• {$stats['bills_count']} conocimiento(s) de embarque\n";
        $message .= "• {$stats['processed_items']} elemento(s) procesado(s)";

        if ($stats['containers_count'] > 0) {
            $message .= "\n• {$stats['containers_count']} contenedor(es)";
        }

        return $message;
    }

    /**
     * Construir mensaje de advertencia
     */
    protected function buildWarningMessage(ManifestParseResult $result, string $fileName): string
    {
        $stats = $result->getStatsSummary();
        
        $message = "⚠️ Archivo '{$fileName}' importado con advertencias.\n\n";
        
        if ($result->voyage) {
            $message .= "🚢 Viaje creado: {$result->voyage->voyage_number}\n";
        }
        
        $message .= "📊 Resumen:\n";
        $message .= "• {$stats['processed_items']} elemento(s) procesado(s)\n";
        $message .= "• {$stats['warnings_count']} advertencia(s)\n\n";
        
        $message .= "⚠️ Advertencias encontradas:\n";
        foreach (array_slice($result->warnings, 0, 3) as $warning) {
            $message .= "• " . $warning . "\n";
        }
        
        if (count($result->warnings) > 3) {
            $message .= "• ... y " . (count($result->warnings) - 3) . " más";
        }

        return $message;
    }

    /**
     * Construir mensaje de error
     */
    protected function buildErrorMessage(ManifestParseResult $result, string $fileName): string
    {
        $stats = $result->getStatsSummary();
        
        $message = "❌ Error al importar archivo '{$fileName}'.\n\n";
        
        if ($stats['errors_count'] > 0) {
            $message .= "Errores encontrados:\n";
            foreach (array_slice($result->errors, 0, 3) as $error) {
                $message .= "• " . $error . "\n";
            }
            
            if (count($result->errors) > 3) {
                $message .= "• ... y " . (count($result->errors) - 3) . " errores más";
            }
        }

        $message .= "\n💡 Sugerencias:\n";
        $message .= "• Verifique que el archivo tenga el formato correcto\n";
        $message .= "• Revise que los datos estén completos\n";
        $message .= "• Contacte al administrador si el problema persiste";

        return $message;
    }

    /**
     * Mostrar historial de importaciones
     */
    public function history(Request $request)
    {
        // TODO: Implementar historial de importaciones
        // Por ahora, redirigir al índice de manifiestos
        return redirect()->route('company.manifests.index')
            ->with('info', 'Historial de importaciones: funcionalidad pendiente de implementación.');
    }

    /**
     * Obtener información de formatos soportados (AJAX)
     */
    public function getSupportedFormats(): \Illuminate\Http\JsonResponse
    {
        try {
            $formats = $this->parserFactory->getSupportedFormats();
            $stats = $this->parserFactory->getFormatStatistics();

            return response()->json([
                'success' => true,
                'formats' => $formats,
                'statistics' => $stats
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Validar archivo antes de subir (AJAX)
     */
    public function validateFile(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'file' => 'required|file|max:10240'
        ]);

        try {
            $file = $request->file('file');
            $tempPath = $file->store('temp', 'local');
            $fullPath = Storage::path($tempPath);

            // Verificar si algún parser puede procesar el archivo
            $canProcess = $this->parserFactory->canProcessFile($fullPath);
            
            // Limpiar archivo temporal
            Storage::delete($tempPath);

            if ($canProcess) {
                return response()->json([
                    'success' => true,
                    'message' => 'Archivo válido y procesable',
                    'file_info' => [
                        'name' => $file->getClientOriginalName(),
                        'size' => $file->getSize(),
                        'extension' => $file->getClientOriginalExtension()
                    ]
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'error' => 'Formato de archivo no soportado',
                    'suggestion' => 'Verifique que el archivo sea de uno de los formatos soportados'
                ], 422);
            }

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Error al validar archivo: ' . $e->getMessage()
            ], 500);
        }
    }
}