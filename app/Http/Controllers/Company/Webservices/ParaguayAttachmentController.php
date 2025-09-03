<?php

namespace App\Http\Controllers\Company\Webservices;

use App\Http\Controllers\Controller;
use App\Services\Webservice\ParaguayAttachmentService;
use App\Models\Voyage;
use App\Traits\UserHelper;
use Illuminate\Http\Request;

class ParaguayAttachmentController extends Controller
{
    use UserHelper;

    /**
     * Mostrar formulario de adjuntos para Paraguay
     */
    public function index(Voyage $voyage)
    {
        if (!$this->hasCompanyRole('Cargas')) {
            abort(403, 'No tiene permisos para adjuntos de Paraguay.');
        }

        if (!$this->canAccessCompany($voyage->company_id)) {
            abort(403, 'No puede acceder a este viaje.');
        }

        return view('company.webservices.paraguay.attachments', compact('voyage'));
    }

    /**
     * Subir documentos a Paraguay
     */
    public function upload(Request $request, Voyage $voyage)
    {
        if (!$this->hasCompanyRole('Cargas')) {
            return response()->json(['error' => 'No tiene permisos'], 403);
        }

        if (!$this->canAccessCompany($voyage->company_id)) {
            return response()->json(['error' => 'No puede acceder a este viaje'], 403);
        }

        $request->validate([
            'files.*' => 'required|file|mimes:pdf|max:10240'
        ]);

        try {
            $service = new ParaguayAttachmentService($voyage->company);
            $result = $service->uploadDocuments(
                $voyage, 
                $request->file('files') ?? [], 
                auth()->id()
            );

            return response()->json($result);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Error interno: ' . $e->getMessage()
            ], 500);
        }
    }
}