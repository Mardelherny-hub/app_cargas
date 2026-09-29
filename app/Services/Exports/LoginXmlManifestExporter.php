<?php

namespace App\Services\Exports;

use App\Models\BillOfLading;
use App\Models\Container;
use App\Models\ShipmentItem;
use App\Models\Voyage;
use DOMDocument;
use DOMElement;
use DomainException;

class LoginXmlManifestExporter
{
    public function generate(Voyage $voyage): string
    {
        $voyage->loadMissing([
            'leadVessel',
            'shipments.vessel',
            'shipments.billsOfLading.shipper.contactData',
            'shipments.billsOfLading.consignee.contactData',
            'shipments.billsOfLading.notifyParty.contactData',
            'shipments.billsOfLading.specificContacts',
            'shipments.billsOfLading.loadingPort',
            'shipments.billsOfLading.dischargePort',
            'shipments.billsOfLading.shipmentItems.containers.containerType',
        ]);

        $billsCount = $voyage->shipments->sum(
            fn ($shipment) => $shipment->billsOfLading->count()
        );

        if ($billsCount === 0) {
            throw new DomainException(
                'El viaje no contiene conocimientos para exportar.'
            );
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        $document->formatOutput = true;

        $root = $document->createElement('BillOfLadingRoot');
        $document->appendChild($root);

        foreach ($voyage->shipments as $shipment) {
            $vesselName = trim((string) (
                $shipment->vessel?->name
                ?? $voyage->leadVessel?->name
                ?? ''
            ));

            if ($vesselName === '') {
                throw new DomainException(
                    "El viaje {$voyage->voyage_number} no tiene buque informado."
                );
            }

            foreach ($shipment->billsOfLading as $bill) {
                $this->appendBill(
                    $document,
                    $root,
                    $voyage,
                    $bill,
                    $vesselName
                );
            }
        }

        return $document->saveXML();
    }

    private function appendBill(
        DOMDocument $document,
        DOMElement $root,
        Voyage $voyage,
        BillOfLading $bill,
        string $vesselName
    ): void {
        if ($bill->shipmentItems->count() !== 1) {
            throw new DomainException(
                "El conocimiento {$bill->bill_number} tiene "
                . $bill->shipmentItems->count()
                . ' ítems. El formato Login admite una sola mercadería '
                . 'comercial por conocimiento y no se exportará perdiendo datos.'
            );
        }

        /** @var ShipmentItem $item */
        $item = $bill->shipmentItems->first();

        if ($item->containers->isEmpty()) {
            throw new DomainException(
                "El conocimiento {$bill->bill_number} no tiene contenedores."
            );
        }

        $billElement = $document->createElement('BillOfLading');
        $root->appendChild($billElement);

        $header = $document->createElement('BillOfLadingHeader');
        $billElement->appendChild($header);

        $shipper = $bill->getShipperCompleteData();
        $consignee = $bill->getConsigneeCompleteData();
        $notify = $bill->getNotifyPartyCompleteData();

        $loadingPort = trim((string) ($bill->loadingPort?->name ?? ''));
        $dischargePort = trim((string) ($bill->dischargePort?->name ?? ''));

        if ($loadingPort === '' || $dischargePort === '') {
            throw new DomainException(
                "El conocimiento {$bill->bill_number} no tiene puertos "
                . 'de carga y descarga completos.'
            );
        }

        $grossWeight = $bill->gross_weight_kg;

        if ($grossWeight === null || $grossWeight === '') {
            $grossWeight = $item->containers->sum(
                fn (Container $container) =>
                    (float) ($container->pivot?->gross_weight_kg ?? 0)
            );
        }

        if ((float) $grossWeight < 0) {
            throw new DomainException(
                "El conocimiento {$bill->bill_number} tiene peso bruto inválido."
            );
        }

        $this->addText(
            $document,
            $header,
            'ShipperExporter',
            $this->partyText($shipper)
        );
        $this->addText(
            $document,
            $header,
            'ShipperExporterCUIT',
            $this->cleanTaxId($shipper['tax_id'] ?? null)
        );
        $this->addText(
            $document,
            $header,
            'Consignee',
            $this->partyText($consignee)
        );
        $this->addText(
            $document,
            $header,
            'NotifyParty',
            ($notify['company_name'] ?? '') === 'No aplica'
                ? ''
                : $this->partyText($notify)
        );
        $this->addText(
            $document,
            $header,
            'BookingNumber',
            $bill->booking_number
        );
        $this->addText(
            $document,
            $header,
            'BillOfLadingNumber',
            $bill->bill_number
        );
        $this->addText(
            $document,
            $header,
            'TypeOfMove',
            $bill->type_of_move
        );
        $this->addText(
            $document,
            $header,
            'FinalPortOfLoading',
            $loadingPort
        );
        $this->addText(
            $document,
            $header,
            'InitialVesselVoyFlag',
            $vesselName . '/' . $voyage->voyage_number
        );
        $this->addText(
            $document,
            $header,
            'InitalPortOfLoading',
            $loadingPort
        );
        $this->addText(
            $document,
            $header,
            'PortOfDischarge',
            $dischargePort
        );
        $this->addText(
            $document,
            $header,
            'NoOfPkgsHm',
            $bill->container_summary
                ?: $this->containerSummary($item)
        );
        $this->addText(
            $document,
            $header,
            'DescriptionOfPackagesAndGoods',
            $bill->cargo_description ?: $item->item_description
        );
        $this->addText(
            $document,
            $header,
            'Email',
            $bill->source_email
        );
        $this->addText(
            $document,
            $header,
            'GrossWeight',
            $this->number($grossWeight)
        );
        $this->addText(
            $document,
            $header,
            'Measurement',
            $bill->volume_m3 !== null
                ? $this->number($bill->volume_m3)
                : ''
        );
        $this->addText(
            $document,
            $header,
            'MksAndNos',
            $bill->cargo_marks
        );

        $this->appendDangerousGoods(
            $document,
            $header,
            $bill
        );

        $this->addText(
            $document,
            $header,
            'ExportReferences',
            $bill->export_references
        );

        $lineDetail = $document->createElement(
            'BillOfLadingLineDetail'
        );
        $billElement->appendChild($lineDetail);

        $commodityCodes = $this->commodityCodes($bill, $item);

        foreach ($item->containers->values() as $index => $container) {
            $this->appendContainerLine(
                $document,
                $lineDetail,
                $container,
                $commodityCodes,
                $index
            );
        }
    }

    private function appendContainerLine(
        DOMDocument $document,
        DOMElement $lineDetail,
        Container $container,
        array $commodityCodes,
        int $fallbackLineNumber
    ): void {
        $typeCode = trim((string) $container->containerType?->code);

        if ($typeCode === '') {
            throw new DomainException(
                "El contenedor {$container->container_number} no tiene "
                . 'tipo de contenedor informado.'
            );
        }

        if (
            $container->tare_weight_kg === null
            || $container->pivot?->gross_weight_kg === null
        ) {
            throw new DomainException(
                "El contenedor {$container->container_number} no tiene "
                . 'tara/peso bruto suficientes para exportar Login XML.'
            );
        }

        $line = $document->createElement('BillOfLadingLine');
        $lineDetail->appendChild($line);

        $sourceLines = $this->decodeList(
            $container->pivot?->source_line_numbers
        );

        $this->addText(
            $document,
            $line,
            'BillOfLadingLineNumber',
            (string) ($sourceLines[0] ?? $fallbackLineNumber)
        );
        $this->addText(
            $document,
            $line,
            'Container',
            $container->container_number
        );
        $this->addText(
            $document,
            $line,
            'Type',
            strtoupper($typeCode)
        );
        $this->addText(
            $document,
            $line,
            'Tare',
            $this->number($container->tare_weight_kg)
        );
        $this->addText(
            $document,
            $line,
            'NetWeight',
            $container->pivot?->net_weight_kg !== null
                ? $this->number($container->pivot->net_weight_kg)
                : ''
        );
        $this->addText(
            $document,
            $line,
            'Vgm',
            $container->pivot?->verified_gross_mass_kg !== null
                ? $this->number(
                    $container->pivot->verified_gross_mass_kg
                )
                : ''
        );
        $this->addText(
            $document,
            $line,
            'GrossWeight',
            $this->number($container->pivot->gross_weight_kg)
        );

        $seals = $this->decodeList(
            $container->pivot?->source_seals
        );

        if ($seals === []) {
            $seals = $this->additionalSeals($container);
        }

        if ($seals !== []) {
            $sealElement = $document->createElement('Seal');
            $line->appendChild($sealElement);

            foreach ($seals as $seal) {
                $this->addText(
                    $document,
                    $sealElement,
                    'Nseal',
                    $seal
                );
            }
        }

        if ($commodityCodes !== []) {
            $ncmElement = $document->createElement('Ncm');
            $line->appendChild($ncmElement);

            foreach ($commodityCodes as $code) {
                $this->addText(
                    $document,
                    $ncmElement,
                    'Nncm',
                    $code
                );
            }
        }
    }

    private function appendDangerousGoods(
        DOMDocument $document,
        DOMElement $header,
        BillOfLading $bill
    ): void {
        $details = array_values(array_filter(
            $bill->dangerous_goods_details ?? [],
            fn ($detail) => is_array($detail)
        ));

        if (
            $details === []
            && (
                trim((string) $bill->un_number) !== ''
                || trim((string) $bill->imdg_class) !== ''
            )
        ) {
            $details[] = [
                'un_number' => $bill->un_number,
                'imdg_class' => $bill->imdg_class,
            ];
        }

        if ($details === []) {
            return;
        }

        $imo = $document->createElement('Imo');
        $header->appendChild($imo);

        foreach ($details as $detail) {
            $un = trim((string) ($detail['un_number'] ?? ''));
            $class = trim((string) ($detail['imdg_class'] ?? ''));

            if ($un === '' && $class === '') {
                continue;
            }

            $nimo = $document->createElement('Nimo');
            $imo->appendChild($nimo);

            $this->addText($document, $nimo, 'Un', $un);
            $this->addText($document, $nimo, 'Classe', $class);
        }
    }

    private function commodityCodes(
        BillOfLading $bill,
        ShipmentItem $item
    ): array {
        $codes = $bill->commodity_codes ?? [];

        if (!is_array($codes)) {
            $codes = [];
        }

        $codes[] = $bill->commodity_code;
        $codes[] = $item->commodity_code;
        $codes[] = $item->tariff_position;

        return array_values(array_unique(array_filter(
            array_map(
                fn ($code) => trim((string) $code),
                $codes
            ),
            fn ($code) => $code !== ''
        )));
    }

    private function additionalSeals(Container $container): array
    {
        $raw = $container->additional_seals ?? [];

        if (!is_array($raw)) {
            return [];
        }

        $seals = [];

        foreach ($raw as $seal) {
            $value = is_array($seal)
                ? ($seal['seal_number'] ?? null)
                : $seal;

            $value = trim((string) $value);

            if ($value !== '') {
                $seals[] = $value;
            }
        }

        return array_values(array_unique($seals));
    }

    private function containerSummary(ShipmentItem $item): string
    {
        $groups = [];

        foreach ($item->containers as $container) {
            $type = strtoupper(trim((string) $container->containerType?->code));

            if ($type === '') {
                continue;
            }

            $groups[$type] = ($groups[$type] ?? 0) + 1;
        }

        return implode(
            ' ',
            array_map(
                fn ($type, $count) => $count . 'x' . $type,
                array_keys($groups),
                array_values($groups)
            )
        );
    }

    private function partyText(array $party): string
    {
        $name = trim((string) ($party['company_name'] ?? ''));
        $address = trim((string) ($party['address'] ?? ''));
        $taxId = $this->cleanTaxId($party['tax_id'] ?? null);

        $lines = array_values(array_filter([
            $name,
            $address,
            $this->taxLine($taxId),
        ], fn ($line) => trim((string) $line) !== ''));

        return implode("\n", array_unique($lines));
    }

    private function taxLine(string $taxId): string
    {
        if ($taxId === '') {
            return '';
        }

        return match (strlen($taxId)) {
            11 => 'CUIT: ' . $taxId,
            14 => 'CNPJ: ' . $taxId,
            default => 'CUIT/RUC: ' . $taxId,
        };
    }

    private function cleanTaxId(mixed $taxId): string
    {
        return preg_replace('/\D/', '', (string) $taxId) ?: '';
    }

    private function decodeList(mixed $value): array
    {
        if (is_array($value)) {
            $decoded = $value;
        } elseif (is_string($value) && trim($value) !== '') {
            $json = json_decode($value, true);
            $decoded = is_array($json) ? $json : [$value];
        } else {
            $decoded = [];
        }

        return array_values(array_unique(array_filter(
            array_map(
                fn ($item) => trim((string) $item),
                $decoded
            ),
            fn ($item) => $item !== ''
        )));
    }

    private function number(mixed $value): string
    {
        return rtrim(
            rtrim(number_format((float) $value, 3, '.', ''), '0'),
            '.'
        );
    }

    private function addText(
        DOMDocument $document,
        DOMElement $parent,
        string $name,
        mixed $value
    ): DOMElement {
        $element = $document->createElement($name);
        $element->appendChild(
            $document->createTextNode((string) ($value ?? ''))
        );
        $parent->appendChild($element);

        return $element;
    }
}
