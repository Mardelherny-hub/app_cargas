<?php

namespace App\Http\Controllers\Company\Manifests;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use App\Services\Importers\ParanaParserService;
use App\Services\Importers\GuaranParserService;

class ManifestImportController extends Controller
{
    /**
     * Formulario para importar manifiesto desde archivo.
     */
    public function showForm()
    {
        return view('company.manifests.import');
    }

    /**
     * Procesar archivo importado.
     */
    public function store(Request $request)
    {
        $request->validate([
            'manifest_file' => 'required|file|mimes:txt,csv,xlsx',
        ]);

        $path = $request->file('manifest_file')->store('imports/manifests');
        $fullPath = storage_path('app/' . $path);

        try {
            $extension = $request->file('manifest_file')->getClientOriginalExtension();

            if ($extension === 'csv') {
                $result = app(GuaranParserService::class)->parse($fullPath);
            } elseif ($extension === 'xlsx') {
                $result = app(ParanaParserService::class)->parse($fullPath);
            } else {
                throw new \Exception("Formato no soportado para importación de manifiesto.");
            }

            return redirect()->route('company.manifests.index')->with('success', 'Archivo importado correctamente.');
        } catch (\Exception $e) {
            Log::error('Error al importar manifiesto: ' . $e->getMessage());
            return back()->withErrors(['manifest_file' => 'Ocurrió un error al procesar el archivo.']);
        }
    }
}
