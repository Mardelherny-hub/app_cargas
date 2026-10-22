<?php

namespace App\Http\Controllers\Company\Simple;

use App\Http\Controllers\Controller;
use App\Models\BillOfLading;
use App\Models\HidroviaTransshipmentAllocation;
use App\Models\Voyage;
use App\Services\Simple\HidroviaTransshipmentDistributionValidator;
use App\Services\Simple\HidroviaTransshipmentDraftService;
use App\Services\Simple\HidroviaTransshipmentReadinessService;
use App\Traits\UserHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** API interna para borradores: no registra envíos ni llama a ARCA. */
class HidroviaTransshipmentDraftController extends Controller
{
    use UserHelper;

    /**
     * Pantalla de distribución del conocimiento para barcazas del mismo viaje.
     * Sólo lectura al abrirla; guardar y preflight usan la API existente.
     * No invoca webservices aduaneros.
     */
    public function edit(int $voyage, int $bill): View
    {
        [$voyageRecord, $billRecord] = $this->authorizedBill($voyage, $bill);
        abort_unless($billRecord->is_transit_transshipment === 'S', 404);
        abort_if($billRecord->is_fractional, 422,
            'Los conocimientos fraccionados aún no están habilitados para pruebas de Transbordos.');

        $billRecord->load('shipmentItems.containers');
        $shipments = $voyageRecord->shipments()
            ->whereHas('vessel.vesselType', fn ($query) => $query->where('category', 'barge'))
            ->with('vessel')
            ->orderBy('id')
            ->get();

        return view('company.simple.transbordo.show', [
            'voyage' => $voyageRecord,
            'bill' => $billRecord,
            'shipments' => $shipments,
        ]);
    }

    public function show(int $voyage, int $bill): JsonResponse
    {
        [$voyageRecord, $billRecord] = $this->authorizedBill($voyage, $bill);
        $rows = HidroviaTransshipmentAllocation::query()
            ->where('bill_of_lading_id', $billRecord->id)
            ->orderBy('shipment_item_id')->orderBy('target_shipment_id')
            ->orderBy('container_id')
            ->get(['shipment_item_id', 'target_shipment_id', 'container_id',
                'package_quantity', 'gross_weight_kg'])
            ->map(fn (HidroviaTransshipmentAllocation $row) => [
                'shipment_id' => (int) $row->target_shipment_id,
                'shipment_item_id' => (int) $row->shipment_item_id,
                'container_id' => $row->container_id !== null ? (int) $row->container_id : null,
                'package_quantity' => (int) $row->package_quantity,
                'gross_weight_kg' => $row->gross_weight_kg,
            ]);

        return response()->json([
            'voyage_id' => $voyageRecord->id,
            'bill_of_lading_id' => $billRecord->id,
            'allocations' => $rows,
        ]);
    }

    public function update(Request $request, int $voyage, int $bill): JsonResponse
    {
        [$voyageRecord, $billRecord, $companyId] = $this->authorizedBill($voyage, $bill);
        $data = $request->validate([
            'allocations' => ['present', 'array', 'max:1000'],
            'allocations.*.shipment_id' => ['required', 'integer', 'min:1'],
            'allocations.*.shipment_item_id' => ['required', 'integer', 'min:1'],
            'allocations.*.container_id' => ['nullable', 'integer', 'min:1'],
            'allocations.*.package_quantity' => ['required', 'integer', 'min:0'],
            'allocations.*.gross_weight_kg' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999.99'],
        ]);
        $records = app(HidroviaTransshipmentDraftService::class)->replaceDraft(
            $voyageRecord, $companyId, $billRecord, $data['allocations'],
            $this->getCurrentUser()?->id
        );

        return response()->json([
            'saved' => true,
            'bill_of_lading_id' => $billRecord->id,
            'allocations_count' => $records->count(),
            'message' => 'Distribución guardada como borrador. No se transmitió a Aduana.',
        ]);
    }

    public function preflight(int $voyage, int $bill): JsonResponse
    {
        [$voyageRecord, $billRecord, $companyId] = $this->authorizedBill($voyage, $bill);
        $ready = app(HidroviaTransshipmentReadinessService::class)->prepare(
            $voyageRecord, $companyId, $billRecord
        );
        return response()->json([
            'valid' => true,
            'transmitted' => false,
            'distribution' => $ready,
        ]);
    }

    /**
     * Prepara y reserva, SIN TRANSMITIR, un título de carga suelta repartido
     * entre barcazas. Usa la autenticación del generador MIC/DTA existente.
     * ATA y lugares operativos se reciben explícitamente por barcaza:
     * el sistema no los deduce ni crea datos aduaneros.
     *
     * Esta acción todavía no tiene ruta HTTP: se habilita en otra intervención.
     */
    public function reserveHomologation(Request $request, int $voyage, int $bill): JsonResponse
    {
        [$voyageRecord, $billRecord, $companyId] = $this->authorizedBill($voyage, $bill);
        $user = $this->getCurrentUser();
        $company = $this->getUserCompany();
        abort_unless($user && $this->isCompanyAdmin() && $company
            && \App\Services\Simple\WebserviceEnvironment::resolve($company) === 'testing', 403);
        abort_unless($billRecord->is_transit_transshipment === 'S' && !$billRecord->is_fractional, 422);

        $data = $request->validate([
            'customsByShipmentId' => ['required', 'array', 'min:2', 'max:999'],
            'customsByShipmentId.*.idFiscalATAMIC' => ['required', 'string', 'regex:/^[0-9]{11,14}$/'],
            'customsByShipmentId.*.lugOperOrigen.codLugOper' => ['required', 'string', 'max:5'],
            'customsByShipmentId.*.lugOperOrigen.codCiu' => ['required', 'string', 'regex:/^[A-Za-z]{2}[A-Za-z0-9]{3}$/'],
            'customsByShipmentId.*.lugOperDestino.codLugOper' => ['required', 'string', 'max:7'],
            'customsByShipmentId.*.lugOperDestino.codCiu' => ['required', 'string', 'regex:/^[A-Za-z]{2}[A-Za-z0-9]{3}$/'],
        ]);

        // El generador actual emite TODOS los títulos del shipment de origen.
        // Para esta primera operación, impedir que una reserva incluya otros BL.
        $origin = $billRecord->shipment;
        if (!$origin || $origin->billsOfLading()->count() !== 1) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'bill' => 'El generador MIC/DTA requiere un único conocimiento en el embarque de origen.',
            ]);
        }

        $billRecord->load('shipmentItems.containers');
        if ($billRecord->shipmentItems->contains(
            fn ($item) => $item->containers->isNotEmpty()
        )) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'bill' => 'Los contenedores requieren homologación específica; no se prepara un envío real.',
            ]);
        }

        $prepared = app(HidroviaTransshipmentReadinessService::class)->prepare(
            $voyageRecord, $companyId, $billRecord
        );
        $supplied = $data['customsByShipmentId'];
        if (count($supplied) !== count($prepared['shipments'])) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'customsByShipmentId' => 'Los datos aduaneros deben corresponder a todas las barcazas.',
            ]);
        }
        foreach ($prepared['shipments'] as $shipment) {
            if (!isset($supplied[$shipment['shipment_id']])) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'customsByShipmentId' =>
                        'Faltan datos ATA y lugares operativos de una barcaza del reparto.',
                ]);
            }
        }

        // Evitar obtener credenciales WSAA si ya hubo una operación MIC/DTA
        // de este viaje. reserveHidroviaTitEnviosForReview repite el bloqueo
        // bajo lock en BD para evitar carreras y duplicaciones.
        $attempted = \App\Models\WebserviceTransaction::query()
            ->where('company_id', $companyId)
            ->where('voyage_id', $voyageRecord->id)
            ->where('webservice_type', 'micdta')
            ->where('country', 'AR')
            ->whereIn('soap_action', [
                'Ar.Gob.Afip.Dga.wgesregsintia2/RegistrarTitEnvios',
                'Ar.Gob.Afip.Dga.wgesregsintia2/RegistrarEnvios',
                'Ar.Gob.Afip.Dga.wgesregsintia2/RegistrarMicDta',
            ])->exists();
        if ($attempted) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'bill' => 'El viaje ya tiene una operación MIC/DTA; se requiere revisión antes de otra reserva.',
            ]);
        }

        $service = new \App\Services\Simple\ArgentinaMicDtaService($company, $user);
        $transactionReference = 'TIT' . bin2hex(random_bytes(6));
        $template = $service->getXmlSerializer()
            ->createRegistrarTitEnviosXml($origin, $transactionReference);
        $transaction = $service->reserveHidroviaTitEnviosForReview(
            $voyageRecord, $billRecord->id, $template, $supplied
        );

        // Nunca devolver XML con Token/Sign, ni credenciales WSAA.
        return response()->json([
            'transaction_record_id' => (int) $transaction->id,
            'bill_of_lading_id' => (int) $billRecord->id,
            'status' => $transaction->status,
            'shipments_count' => count($prepared['shipments']),
            'transmitted' => false,
            'message' => 'Reserva guardada para revisión. No se transmitió a ARCA.',
        ], 201);
    }

    /**
     * Confirma y despacha UNA reserva ya preparada hacia homologación ARCA.
     * El formulario nunca proporciona SOAP, credenciales ni ambiente.
     */
    public function sendHomologation(Request $request, int $voyage, int $bill): JsonResponse
    {
        [$voyageRecord, $billRecord, $companyId] = $this->authorizedBill($voyage, $bill);
        $user = $this->getCurrentUser();
        abort_unless($user && $this->isCompanyAdmin(), 403);

        $company = $this->getUserCompany();
        abort_unless($company
            && \App\Services\Simple\WebserviceEnvironment::resolve($company) === 'testing'
            && (app()->environment('testing') || app()->environment('production')), 403);

        $data = $request->validate([
            'transaction_record_id' => ['required', 'integer', 'min:1'],
            'confirm' => ['required', 'accepted'],
        ]);

        $transactionId = \Illuminate\Support\Facades\DB::transaction(function () use (
            $voyageRecord, $billRecord, $companyId, $user, $data, $company
        ): int {
            Voyage::query()->where('company_id', $companyId)
                ->lockForUpdate()->findOrFail($voyageRecord->id);

            $transaction = \App\Models\WebserviceTransaction::query()
                ->where('company_id', $companyId)
                ->where('voyage_id', $voyageRecord->id)
                ->lockForUpdate()->findOrFail((int) $data['transaction_record_id']);

            if ((int) $transaction->shipment_id !== (int) $billRecord->shipment_id
                || $billRecord->is_transit_transshipment !== 'S'
                || $billRecord->is_fractional
                || $transaction->webservice_type !== 'micdta'
                || $transaction->country !== 'AR'
                || $transaction->soap_action
                    !== 'Ar.Gob.Afip.Dga.wgesregsintia2/RegistrarTitEnvios'
                || $transaction->environment !== 'testing'
                || $transaction->webservice_url
                    !== \App\Services\Simple\WebserviceEnvironment::argentinaEndpoint(
                        $company, 'wgesregsintia2'
                    )
                || $transaction->status !== 'validating'
                || !$transaction->requires_manual_review
                || $transaction->reviewer_user_id !== null
                || $transaction->reviewed_at !== null
                || $transaction->sent_at !== null
                || $transaction->response_at !== null
                || trim((string) $transaction->response_xml) !== ''
                || trim((string) $transaction->request_xml) === ''
                || $transaction->is_blocking_error
                || (int) $transaction->retry_count !== 0
                || (int) $transaction->max_retries !== 0
                || $transaction->next_retry_at !== null) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'transaction_record_id' =>
                        'La reserva no está disponible para homologación o requiere conciliación.',
                ]);
            }

            $transaction->update([
                'requires_manual_review' => false,
                'reviewer_user_id' => (string) $user->id,
                'reviewed_at' => now(),
            ]);

            return (int) $transaction->id;
        });

        $service = app()->makeWith(\App\Services\Simple\ArgentinaMicDtaService::class, [
            'company' => $company,
            'user' => $user,
        ]);
        $result = $service->sendReservedHidroviaTitEnviosInQa(
            $voyageRecord, (int) $billRecord->id, $transactionId, true
        );

        return response()->json([
            'success' => (bool) ($result['success'] ?? false),
            'transaction_record_id' => $transactionId,
            'requires_manual_review' => (bool) ($result['requires_manual_review'] ?? false),
            'tracks' => $result['success'] ? ($result['tracks'] ?? []) : [],
            'message' => $result['success']
                ? 'Homologación registrada. Revisá los TRACKs devueltos por ARCA.'
                : 'Resultado no confirmado: revisar la transacción antes de volver a operar.',
        ], $result['success'] ? 200 : 409);
    }

    /** Empresa y rol provienen del usuario, nunca del formulario. */
    private function authorizedBill(int $voyageId, int $billId): array
    {
        $company = $this->getUserCompany();
        abort_unless($company && $this->hasCompanyRole('Transbordos'), 403);
        $voyage = Voyage::query()->where('company_id', $company->id)->findOrFail($voyageId);
        $bill = $voyage->billsOfLading()->findOrFail($billId);
        return [$voyage, $bill, (int) $company->id];
    }
}
