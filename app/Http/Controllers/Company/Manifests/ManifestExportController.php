<?php

namespace App\Http\Controllers\Company\Manifests;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Voyage;
use App\Exports\ParanaExport;
use App\Exports\GuaranExport;
use Maatwebsite\Excel\Facades\Excel;

class ManifestExportController extends Controller
{
    /**
     * Exportar manifiesto a formato PARANA (xlsx).
     */
    public function exportParana($voyageId)
    {
        $voyage = Voyage::with('shipments.billsOfLading')
            ->where('company_id', auth()->user()->company_id)
            ->findOrFail($voyageId);

        return Excel::download(new ParanaExport($voyage), 'PARANA_' . $voyage->voyage_number . '.xlsx');
    }

    /**
     * Exportar manifiesto a formato GUARAN (csv).
     */
    public function exportGuaran($voyageId)
    {
        $voyage = Voyage::with('shipments.billsOfLading')
            ->where('company_id', auth()->user()->company_id)
            ->findOrFail($voyageId);

        return Excel::download(new GuaranExport($voyage), 'GUARAN_' . $voyage->voyage_number . '.csv', 
            \Maatwebsite\Excel\Excel::CSV);
    }
}
