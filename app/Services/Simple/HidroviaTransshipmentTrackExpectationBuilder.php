<?php

namespace App\Services\Simple;

use App\Models\BillOfLading;
use InvalidArgumentException;

/**
 * Adapta un preflight real de HidroviaTransshipmentReadinessService::prepare()
 * al contrato esperado por HidroviaTransshipmentTrackResponseMapper.
 * No valida permisos, no registra títulos, no escribe BD ni transmite SOAP.
 */
final class HidroviaTransshipmentTrackExpectationBuilder
{
    /** @return array<int, array<string, mixed>> */
    public function fromPrepared(BillOfLading $bill, array $prepared): array
    {
        $billId = (int) $bill->id;
        $number = trim((string) $bill->bill_number);
        $voyageId = filter_var($prepared['voyage_id'] ?? null, FILTER_VALIDATE_INT);

        if ($billId < 1 || (int) ($prepared['bill_of_lading_id'] ?? 0) !== $billId
            || !$voyageId || $voyageId < 1
            || $bill->is_transit_transshipment !== 'S' || $bill->is_fractional
            || $number === '' || mb_strlen($number) > 36) {
            throw new InvalidArgumentException('El conocimiento o preflight de transbordo no es válido.');
        }

        $preparedShipments = $prepared['shipments'] ?? null;
        if (!is_array($preparedShipments)
            || count($preparedShipments) < 2 || count($preparedShipments) > 999) {
            throw new InvalidArgumentException('El transbordo necesita al menos dos envíos reales.');
        }

        $shipments = [];
        $seenShipments = [];
        foreach ($preparedShipments as $index => $preparedShipment) {
            if (!is_array($preparedShipment)) {
                throw new InvalidArgumentException('El preflight contiene un envío inválido.');
            }
            $idEnvio = filter_var($preparedShipment['id_envio'] ?? null, FILTER_VALIDATE_INT);
            $shipmentId = filter_var($preparedShipment['shipment_id'] ?? null, FILTER_VALIDATE_INT);
            $vesselId = filter_var($preparedShipment['vessel_id'] ?? null, FILTER_VALIDATE_INT);
            if (!is_int($index) || !$idEnvio || $idEnvio !== $index + 1
                || !$shipmentId || $shipmentId < 1
                || !$vesselId || $vesselId < 1
                || isset($seenShipments[$shipmentId])
                || !is_array($preparedShipment['lines'] ?? null)
                || $preparedShipment['lines'] === []) {
                throw new InvalidArgumentException('El envío no tiene secuencia, barcaza o reparto real válido.');
            }
            $seenShipments[$shipmentId] = true;
            $shipments[] = [
                'id_envio' => $idEnvio,
                'shipment_id' => $shipmentId,
                'vessel_id' => $vesselId,
            ];
        }

        return [[
            'idTitTrans' => $number,
            'bill_of_lading_id' => $billId,
            'shipments' => $shipments,
        ]];
    }
}
