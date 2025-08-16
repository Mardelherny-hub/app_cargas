<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Services\FileGeneration\ManeFileGeneratorService;
use App\Models\Voyage;
use App\Traits\UserHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;
use Exception;

/**
 * MÓDULO 5: ENVÍOS ADUANEROS ADICIONALES - ManeFileController
 * 
 * Controlador para generar archivos MANE para el sistema legacy Malvina de Aduana.
 * 
 * FUNCIONALIDADES:
 * - Generar archivos MANE para viajes individuales
 * - Generar archivos MANE consolidados para múltiples viajes
 * - Descargar archivos generados
 * - Solo para empresas con rol "Cargas" y IdMaria configurado
 * 
 * CARACTERÍSTICAS:
 * - Usa ManeFileGeneratorService
 * - Validaciones de permisos y empresa
 * - Sistema de logs para auditoría
 * 
 * PATRÓN SEGUIDO: Igual que otros controladores Company (VoyageController, ShipmentController)
 */
class ManeFileController extends Controller
{
    use UserHelper;

    /**
     * Mostrar vista principal para generar archivos MANE
     */
    public function index(Request $request)
    {
        // Verificar permisos básicos
        if (!$this->canPerform('view_cargas')) {
            abort(403, 'No tiene permisos para generar archivos MANE.');
        }

        // Verificar que la empresa tenga rol "Cargas"
        if (!$this->hasCompanyRole('Cargas')) {
            abort(403, 'Su empresa no tiene el rol de Cargas necesario para generar archivos MANE.');
        }

        $company = $this->getUserCompany();
        if (!$company) {
            return redirect()->route('company.dashboard')
                ->with('error', 'No se encontró la empresa asociada.');
        }

        // Verificar que la empresa tenga IdMaria configurado
        if (empty($company->id_maria)) {
            return redirect()->route('company.dashboard')
                ->with('error', 'Su empresa debe tener un ID María configurado para generar archivos MANE. Contacte al administrador.');
        }

        // Obtener viajes disponibles para MANE
        $availableVoyages = $this->getAvailableVoyages($company, $request);

        // Obtener archivos MANE generados recientemente
        $recentFiles = $this->getRecentManeFiles($company);

        return view('company.mane.index', compact(
            'company',
            'availableVoyages',
            'recentFiles'
        ));
    }

    /**
     * Generar archivo MANE para un viaje específico
     */
    public function generateForVoyage(Request $request, Voyage $voyage)
    {
        // Verificar permisos básicos
        if (!$this->canPerform('view_cargas')) {
            abort(403, 'No tiene permisos para generar archivos MANE.');
        }

        $company = $this->getUserCompany();
        if (!$company) {
            return response()->json(['error' => 'Empresa no encontrada'], 404);
        }

        // Verificar que el viaje pertenece a la empresa
        if ($voyage->company_id !== $company->id) {
            abort(403, 'No tiene permisos para generar archivos MANE de este viaje.');
        }

        // Verificar que la empresa tenga rol "Cargas"
        if (!$this->hasCompanyRole('Cargas')) {
            abort(403, 'Su empresa no tiene el rol de Cargas.');
        }

        // Verificar IdMaria
        if (empty($company->id_maria)) {
            return response()->json([
                'error' => 'La empresa debe tener un ID María configurado para generar archivos MANE.'
            ], 422);
        }

        try {
            // Crear servicio generador
            $generator = new ManeFileGeneratorService($company);

            // Generar archivo
            $filepath = $generator->generateForVoyage($voyage);

            // Log de la operación
            Log::info('Archivo MANE generado exitosamente', [
                'company_id' => $company->id,
                'voyage_id' => $voyage->id,
                'voyage_number' => $voyage->voyage_number,
                'user_id' => auth()->id(),
                'filepath' => $filepath
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Archivo MANE generado exitosamente',
                'filename' => basename($filepath),
                'download_url' => route('company.mane.download', ['filename' => basename($filepath)])
            ]);

        } catch (Exception $e) {
            Log::error('Error generando archivo MANE', [
                'company_id' => $company->id,
                'voyage_id' => $voyage->id,
                'error' => $e->getMessage(),
                'user_id' => auth()->id()
            ]);

            return response()->json([
                'error' => 'Error generando archivo MANE: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generar archivo MANE consolidado para múltiples viajes
     */
    public function generateConsolidated(Request $request)
    {
        // Validar request
        $request->validate([
            'voyage_ids' => 'required|array|min:1',
            'voyage_ids.*' => 'required|integer|exists:voyages,id'
        ]);

        // Verificar permisos
        if (!$this->canPerform('view_cargas')) {
            abort(403, 'No tiene permisos para generar archivos MANE.');
        }

        $company = $this->getUserCompany();
        if (!$company) {
            return response()->json(['error' => 'Empresa no encontrada'], 404);
        }

        // Verificar rol de empresa
        if (!$this->hasCompanyRole('Cargas')) {
            abort(403, 'Su empresa no tiene el rol de Cargas.');
        }

        // Verificar IdMaria
        if (empty($company->id_maria)) {
            return response()->json([
                'error' => 'La empresa debe tener un ID María configurado.'
            ], 422);
        }

        try {
            // Obtener viajes y verificar ownership
            $voyages = Voyage::whereIn('id', $request->voyage_ids)
                           ->where('company_id', $company->id)
                           ->get();

            if ($voyages->count() !== count($request->voyage_ids)) {
                return response()->json([
                    'error' => 'Algunos viajes no pertenecen a su empresa o no existen.'
                ], 422);
            }

            // Crear servicio generador
            $generator = new ManeFileGeneratorService($company);

            // Generar archivo consolidado
            $filepath = $generator->generateForMultipleVoyages($voyages);

            // Log de la operación
            Log::info('Archivo MANE consolidado generado exitosamente', [
                'company_id' => $company->id,
                'voyages_count' => $voyages->count(),
                'voyage_ids' => $request->voyage_ids,
                'user_id' => auth()->id(),
                'filepath' => $filepath
            ]);

            return response()->json([
                'success' => true,
                'message' => "Archivo MANE consolidado generado exitosamente ({$voyages->count()} viajes)",
                'filename' => basename($filepath),
                'download_url' => route('company.mane.download', ['filename' => basename($filepath)])
            ]);

        } catch (Exception $e) {
            Log::error('Error generando archivo MANE consolidado', [
                'company_id' => $company->id,
                'voyage_ids' => $request->voyage_ids,
                'error' => $e->getMessage(),
                'user_id' => auth()->id()
            ]);

            return response()->json([
                'error' => 'Error generando archivo consolidado: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Descargar archivo MANE generado
     */
    public function download(Request $request, string $filename)
    {
        // Verificar permisos básicos
        if (!$this->canPerform('view_cargas')) {
            abort(403, 'No tiene permisos para descargar archivos MANE.');
        }

        $company = $this->getUserCompany();
        if (!$company) {
            abort(404, 'Empresa no encontrada');
        }

        // Verificar que la empresa tenga rol "Cargas"
        if (!$this->hasCompanyRole('Cargas')) {
            abort(403, 'Su empresa no tiene el rol de Cargas.');
        }

        // Sanitizar nombre de archivo
        $filename = basename($filename);
        $filepath = "mane_exports/{$filename}";

        // Verificar que el archivo existe
        if (!Storage::exists($filepath)) {
            abort(404, 'Archivo no encontrado');
        }

        // Verificar que el archivo pertenece a la empresa (por ID María en el nombre)
        if (!str_contains($filename, $company->id_maria)) {
            abort(403, 'No tiene permisos para descargar este archivo.');
        }

        // Log del download
        Log::info('Descarga de archivo MANE', [
            'company_id' => $company->id,
            'filename' => $filename,
            'user_id' => auth()->id()
        ]);

        // Retornar archivo para descarga
        return Storage::download($filepath, $filename, [
            'Content-Type' => 'text/plain',
        ]);
    }

    /**
     * Obtener viajes disponibles para generar archivos MANE
     */
    private function getAvailableVoyages($company, Request $request)
    {
        $query = Voyage::where('company_id', $company->id)
                      ->whereHas('shipments')
                      ->with(['originPort', 'destinationPort', 'leadVessel']);

        // Filtros opcionales
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('date_from')) {
            $query->where('departure_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->where('departure_date', '<=', $request->date_to);
        }

        return $query->orderBy('departure_date', 'desc')
                    ->paginate(20)
                    ->withQueryString();
    }

    /**
     * Obtener archivos MANE generados recientemente
     */
    private function getRecentManeFiles($company)
    {
        $files = collect();
        
        try {
            // Obtener archivos de la empresa del directorio mane_exports
            $allFiles = Storage::files('mane_exports');
            
            // Filtrar archivos de esta empresa (por IdMaria)
            $companyFiles = collect($allFiles)->filter(function ($file) use ($company) {
                return str_contains(basename($file), $company->id_maria);
            });

            // Obtener información de archivos
            foreach ($companyFiles as $file) {
                $files->push([
                    'filename' => basename($file),
                    'size' => Storage::size($file),
                    'modified' => Storage::lastModified($file),
                    'download_url' => route('company.mane.download', ['filename' => basename($file)])
                ]);
            }

            // Ordenar por fecha de modificación (más recientes primero)
            return $files->sortByDesc('modified')->take(10);

        } catch (Exception $e) {
            Log::warning('Error obteniendo archivos MANE recientes', [
                'company_id' => $company->id,
                'error' => $e->getMessage()
            ]);
            
            return collect();
        }
    }
}