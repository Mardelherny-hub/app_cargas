<?php

namespace App\Services\Reports;

use App\Models\Voyage;
use App\Models\Company;
use App\Models\WebserviceTransaction;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class MicdtaReportService
{
    private Voyage $voyage;
    private Company $company;
    private array $filters;
    private ?string $generatedBy;

    public function __construct(Voyage $voyage, array $filters = [], ?string $generatedBy = null)
    {
        $this->voyage = $voyage;
        $this->company = $voyage->company;
        $this->filters = $filters;
        $this->generatedBy = $generatedBy ?? auth()->user()->name ?? 'Sistema';
    }

    public function prepareData(): array
    {
        $this->voyage->load([
            'leadVessel.flagCountry',
            'originPort.country',
            'destinationPort.country',
            'transshipmentPort',
            'shipments.vessel',
            'shipments.billsOfLading.shipper.contactData',
            'shipments.billsOfLading.consignee.contactData',
            'shipments.billsOfLading.notifyParty.contactData',
            'shipments.billsOfLading.specificContacts.contactData.client',
            'shipments.billsOfLading.loadingPort',
            'shipments.billsOfLading.dischargePort',
            'shipments.billsOfLading.finalDestinationPort',
            'shipments.billsOfLading.loadingCustoms',
            'shipments.billsOfLading.dischargeCustoms',
            'shipments.billsOfLading.primaryCargoType',
            'shipments.billsOfLading.primaryPackagingType',
            'shipments.billsOfLading.shipmentItems.packagingType',
            'shipments.billsOfLading.shipmentItems.containers.containerType',
        ]);

        return [
            'voyage' => $this->formatVoyageData(),
            'company' => $this->formatCompanyData(),
            'shipments' => $this->formatShipmentsData(),
            'client_template_bills' => $this->formatClientTemplateBills(),
            'micdta' => $this->formatMicDtaTransaction(),
            'totals' => $this->calculateTotals(),
            'metadata' => $this->generateMetadata(),
        ];
    }

    public function getSuggestedFilename(string $format = 'pdf'): string
    {
        $date = now()->format('Ymd');
        $voyageNumber = str_replace(['/', ' '], '_', $this->voyage->voyage_number);
        $prefix = ($this->filters['template'] ?? 'standard') === 'client'
            ? 'micdta_formato'
            : 'micdta';

        return "{$prefix}_{$voyageNumber}_{$date}.{$format}";
    }

    public function validate(): bool
    {
        if ($this->voyage->shipments->isEmpty()) {
            return false;
        }

        if (empty($this->voyage->voyage_number) || 
            !$this->voyage->leadVessel ||
            !$this->voyage->originPort ||
            !$this->voyage->destinationPort) {
            return false;
        }

        return true;
    }

    private function formatVoyageData(): array
    {
        return [
            'voyage_number' => $this->voyage->voyage_number ?? 'N/A',
            'vessel_name' => $this->voyage->leadVessel->name ?? 'N/A',
            'vessel_registration' => $this->voyage->leadVessel->registration_number ?? '',
            'imo_number' => $this->voyage->leadVessel->imo_number ?? '',
            'vessel_flag' => $this->voyage->leadVessel?->flagCountry?->name ?? '',
            'departure_date' => $this->voyage->departure_date 
                ? Carbon::parse($this->voyage->departure_date)->format('d/m/Y')
                : 'N/A',
            'arrival_date' => $this->voyage->estimated_arrival_date 
                ? Carbon::parse($this->voyage->estimated_arrival_date)->format('d/m/Y')
                : 'N/A',
            'origin_port' => $this->voyage->originPort->name ?? 'N/A',
            'origin_port_code' => $this->voyage->originPort->code ?? '',
            'origin_customs' => 'N/A',
            'origin_customs_code' => '',
            'destination_port' => $this->voyage->destinationPort->name ?? 'N/A',
            'destination_port_code' => $this->voyage->destinationPort->code ?? '',
            'destination_customs' => 'N/A',
            'destination_customs_code' => '',
            'transshipment_port' => $this->voyage->transshipmentPort->name ?? null,
            'has_transshipment' => $this->voyage->has_transshipment ? 'Sí' : 'No',
        ];
    }

    private function formatCompanyData(): array
    {
        return [
            'legal_name' => $this->company->legal_name ?? 'N/A',
            'commercial_name' => $this->company->commercial_name,
            'tax_id' => $this->company->tax_id ?? '',
            'address' => $this->company->address ?? '',
            'city' => $this->company->city ?? '',
            'email' => $this->company->email ?? '',
            'phone' => $this->company->phone ?? '',
        ];
    }

    private function formatShipmentsData(): array
    {
        return $this->voyage->shipments->map(function ($shipment) {
            return [
                'shipment_number' => $shipment->shipment_number ?? 'N/A',
                'vessel_name' => $shipment->vessel->name ?? 'N/A',
                'origin_manifest_id' => $shipment->origin_manifest_id ?? '',
                'origin_transport_doc' => $shipment->origin_transport_doc ?? '',
                'bills' => $this->formatBillsForShipment($shipment),
            ];
        })->toArray();
    }

    private function formatBillsForShipment($shipment): array
    {
        return $shipment->billsOfLading->map(function ($bill) {
            return [
                'bill_number' => $bill->bill_number ?? 'N/A',
                'shipper_name' => $bill->shipper->legal_name ?? 'N/A',
                'shipper_tax_id' => $bill->shipper->tax_id ?? '',
                'consignee_name' => $bill->consignee->legal_name ?? 'N/A',
                'consignee_tax_id' => $bill->consignee->tax_id ?? '',
                'loading_port' => $bill->loadingPort->name ?? 'N/A',
                'loading_port_code' => $bill->loadingPort->code ?? '',
                'loading_customs' => $bill->loadingCustoms->name ?? '',
                'loading_customs_code' => $bill->loadingCustoms->code ?? '',
                'discharge_port' => $bill->dischargePort->name ?? 'N/A',
                'discharge_port_code' => $bill->dischargePort->code ?? '',
                'discharge_customs' => $bill->dischargeCustoms->name ?? '',
                'discharge_customs_code' => $bill->dischargeCustoms->code ?? '',
                'total_packages' => $bill->total_packages ?? 0,
                'gross_weight_kg' => $bill->gross_weight_kg ?? 0,
                'net_weight_kg' => $bill->net_weight_kg ?? 0,
                'volume_m3' => $bill->volume_m3 ?? 0,
                'cargo_description' => $bill->cargo_description ?? 'N/A',
                'cargo_type' => $bill->primaryCargoType->name ?? '',
                'packaging_type' => $bill->primaryPackagingType->name ?? '',
                'is_consolidated' => $bill->is_consolidated ?? 'N',
                'is_transit_transshipment' => $bill->is_transit_transshipment ?? 'N',
            ];
        })->toArray();
    }

    private function formatClientTemplateBills(): array
    {
        return $this->voyage->shipments
            ->flatMap(function ($shipment) {
                return $shipment->billsOfLading->map(function ($bill) use ($shipment) {
                    $shipper = $bill->getShipperCompleteData();
                    $consignee = $bill->getConsigneeCompleteData();
                    $notify = $bill->getNotifyPartyCompleteData();

                    $items = $bill->shipmentItems->map(function ($item) {
                        $containers = $item->containers->map(function ($container) {
                            $seals = collect([
                                $container->customs_seal,
                                $container->shipper_seal,
                                $container->carrier_seal,
                            ])->merge(is_array($container->additional_seals) ? $container->additional_seals : [])
                              ->filter()
                              ->unique()
                              ->values()
                              ->implode(', ');

                            return [
                                'number' => $container->full_container_number ?: $container->container_number,
                                'type' => $container->containerType?->iso_code
                                    ?: $container->containerType?->iso_size_type
                                    ?: $container->containerType?->code,
                                'seals' => $seals,
                            ];
                        })->values()->all();

                        return [
                            'quantity' => $item->package_quantity,
                            'package_type' => $item->package_type_description
                                ?: $item->packagingType?->name,
                            'description' => $item->item_description ?: $item->commodity_description,
                            'commodity_code' => $item->commodity_code,
                            'gross_weight_kg' => $item->gross_weight_kg,
                            'net_weight_kg' => $item->net_weight_kg,
                            'volume_m3' => $item->volume_m3,
                            'declared_value' => $item->declared_value,
                            'cargo_marks' => $item->cargo_marks,
                            'containers' => $containers,
                        ];
                    })->values()->all();

                    $containers = collect($items)
                        ->flatMap(fn ($item) => $item['containers'])
                        ->unique('number')
                        ->values()
                        ->all();

                    return [
                        'shipment_number' => $shipment->shipment_number ?? '',
                        'bill_number' => $bill->bill_number ?? '',
                        'bill_date' => $bill->bill_date?->format('d/m/Y'),
                        'shipper' => $shipper,
                        'consignee' => $consignee,
                        'notify' => $notify,
                        'loading_port' => $bill->loadingPort?->name ?? '',
                        'discharge_port' => $bill->dischargePort?->name ?? '',
                        'final_destination_port' => $bill->finalDestinationPort?->name
                            ?? $bill->dischargePort?->name
                            ?? '',
                        'total_packages' => $bill->total_packages ?? 0,
                        'gross_weight_kg' => $bill->gross_weight_kg ?? 0,
                        'volume_m3' => $bill->volume_m3 ?? 0,
                        'cargo_description' => $bill->cargo_description ?? '',
                        'cargo_marks' => $bill->cargo_marks ?? '',
                        'declared_value' => collect($bill->shipmentItems)->sum('declared_value'),
                        'currency_code' => $bill->currency_code ?? 'USD',
                        'customs_remarks' => $bill->customs_remarks ?? '',
                        'is_transit' => $bill->is_transit_transshipment === 'S',
                        'items' => $items,
                        'containers' => $containers,
                    ];
                });
            })
            ->values()
            ->all();
    }

    private function formatMicDtaTransaction(): array
    {
        $transaction = WebserviceTransaction::query()
            ->where('company_id', $this->company->id)
            ->where('voyage_id', $this->voyage->id)
            ->where('webservice_type', 'micdta')
            ->where('status', 'success')
            ->latest('id')
            ->first();

        if (!$transaction) {
            return [
                'number' => '',
                'date' => $this->voyage->departure_date
                    ? Carbon::parse($this->voyage->departure_date)->format('d/m/Y')
                    : '',
                'customs_observation' => '',
            ];
        }

        $tracking = collect($transaction->tracking_numbers ?? [])->flatten()->filter()->implode(', ');

        return [
            'number' => $transaction->confirmation_number
                ?: $transaction->external_reference
                ?: $transaction->transaction_id
                ?: '',
            'date' => ($transaction->response_at ?: $transaction->sent_at ?: $transaction->created_at)
                ? Carbon::parse($transaction->response_at ?: $transaction->sent_at ?: $transaction->created_at)->format('d/m/Y')
                : '',
            'customs_observation' => $tracking,
        ];
    }

    private function calculateTotals(): array
    {
        $allBills = $this->voyage->billsOfLading;

        return [
            'total_shipments' => $this->voyage->shipments->count(),
            'total_bills' => $allBills->count(),
            'total_packages' => $allBills->sum('total_packages') ?? 0,
            'total_gross_weight_kg' => $allBills->sum('gross_weight_kg') ?? 0,
            'total_net_weight_kg' => $allBills->sum('net_weight_kg') ?? 0,
            'total_volume_m3' => $allBills->sum('volume_m3') ?? 0,
        ];
    }

    private function generateMetadata(): array
    {
        return [
            'generated_at' => now()->format('d/m/Y H:i:s'),
            'generated_by' => $this->generatedBy,
            'company_name' => $this->company->legal_name ?? 'N/A',
            'report_type' => 'MIC/DTA - AFIP',
        ];
    }
}