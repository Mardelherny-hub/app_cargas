<?php

namespace App\Http\Controllers\Company\Manifests;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Voyage;
use App\Services\Webservices\Argentina\MicDtaService;
use App\Services\Webservices\Argentina\AnticipadaService;
use App\Services\Webservices\Paraguay\CustomsManifestService;
use Illuminate\Support\Facades\Log;

class ManifestCustomsController extends Controller
{
    /**
     * Enviar manifiesto a la aduana (según país de destino).
     */
    public function send(Request $request, $voyageId)
    {
        $voyage = Voyage::with('shipments.billsOfLading')
            ->where('company_id', auth()->user()->company_id)
            ->findOrFail($voyageId);

        try {
            $country = $voyage->destination_port->country->iso_code ?? 'AR';

            switch ($country) {
                case 'AR':
                    $service = $request->input('mode') === 'anticipada'
                        ? app(AnticipadaService::class)
                        : app(MicDtaService::class);
                    break;
                case 'PY':
                    $service = app(CustomsManifestService::class);
                    break;
                default:
                    throw new \Exception("País no soportado para envío a aduana.");
            }

            $response = $service->send($voyage);

            return redirect()->route('company.manifests.show', $voyageId)
                ->with('success', 'Enviado a aduana correctamente.');

        } catch (\Exception $e) {
            Log::error('Error al enviar a aduana: ' . $e->getMessage());
            return back()->withErrors(['customs' => 'Error al enviar a la aduana.']);
        }
    }
}
