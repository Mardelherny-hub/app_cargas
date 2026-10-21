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
