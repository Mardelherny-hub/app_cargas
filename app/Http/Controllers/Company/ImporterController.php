<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Services\Importers\KlineParserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class ImporterController extends Controller
{
    public function showForm()
    {
        return view('company.imports.kline.upload');
    }

    public function import(Request $request)
    {
        $request->validate([
            'dat_file' => 'required|file|mimes:txt,dat',
        ]);

        $path = $request->file('dat_file')->store('imports/kline');
        
        try {
            $fullPath = storage_path('app/' . $path);
            
            // Debug información
            Log::info('Debugging file info:', [
                'path' => $path,
                'fullPath' => $fullPath,
                'file_exists' => file_exists($fullPath),
                'is_readable' => is_readable($fullPath),
                'file_size' => file_exists($fullPath) ? filesize($fullPath) : 'N/A',
                'storage_exists' => Storage::exists($path),
            ]);
            
            if (!Storage::exists($path)) {
                return back()->with('error', 'El archivo no se pudo encontrar.');
            }
        
            // Usar Storage::path() para obtener la ruta completa
            $fullPath = Storage::path($path);
            
            $parser = new KlineParserService($fullPath);
            $parser->parse();
            
            return back()->with('success', 'Archivo KLine.DAT importado correctamente.');
            
        } catch (\Throwable $e) {
            Log::error('Error al importar archivo KLine: ' . $e->getMessage());
            return back()->with('error', 'Ocurrió un error al importar el archivo.');
        }
    }
}
