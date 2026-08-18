<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

/**
 * Compatibilidad con la antigua entrada exclusiva de K-Line.
 *
 * La importación real se realiza únicamente mediante ManifestImportController,
 * que usa ManifestParserFactory, tracking, cola y KlineDataParser.
 */
class ImporterController extends Controller
{
    public function showForm(): RedirectResponse
    {
        return redirect()->route('company.manifests.import.index');
    }

    public function import(): RedirectResponse
    {
        return redirect()
            ->route('company.manifests.import.index')
            ->with(
                'error',
                'El importador K-Line anterior fue retirado. '
                . 'Seleccione el archivo y la embarcación desde este formulario.'
            );
    }
}
