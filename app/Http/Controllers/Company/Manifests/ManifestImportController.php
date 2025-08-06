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
     * Mostrar historial de importaciones
     */
    public function history(Request $request)
    {
        // Por ahora retornamos vista simple, se puede expandir para mostrar logs de importación
        return view('company.manifests.import-history');
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
        
        $message .= "📊 Estadísticas:\n";
        $message .= "• Embarques procesados: {$stats['shipments']}\n";
        $message .= "• Contenedores procesados: {$stats['containers']}\n";
        $message .= "• BL procesados: {$stats['bills_of_lading']}\n";
        
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
        
        $message .= "📊 Estadísticas:\n";
        $message .= "• Embarques procesados: {$stats['shipments']}\n";
        $message .= "• Contenedores procesados: {$stats['containers']}\n";
        $message .= "• BL procesados: {$stats['bills_of_lading']}\n\n";
        
        $message .= "⚠️ Advertencias encontradas:\n";
        foreach ($result->warnings as $warning) {
            $message .= "• {$warning}\n";
        }
        
        return $message;
    }

    /**
     * Construir mensaje de error
     */
    protected function buildErrorMessage(ManifestParseResult $result, string $fileName): string
    {
        $message = "❌ Error al importar archivo '{$fileName}'.\n\n";
        
        $message .= "❌ Errores encontrados:\n";
        foreach ($result->errors as $error) {
            $message .= "• {$error}\n";
        }
        
        return $message;
    }
}