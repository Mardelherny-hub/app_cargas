<?php

namespace App\Services\Simple;

use App\Models\BillOfLading;
use App\Models\ShipmentItem;
use InvalidArgumentException;
use XMLWriter;

/**
 * Vista previa XML de los envíos de UN título repartido entre barcazas.
 * Recibe la salida completa de HidroviaTransshipmentReadinessService::prepare.
 * No es un SOAP válido para transmitir: faltan ATA Buque, lugares operativos,
 * anexos y validación del tratamiento aduanero de bultos en contenedores.
 * No sustituye a SimpleXmlGenerator ni modifica sus transacciones.
 */
final class HidroviaTransshipmentMultiEnvioXmlGenerator
{
    public function __construct(
        private readonly HidroviaTransshipmentContainerBultoWriter $containerBulto
    ) {}

    public function createEnviosFragment(BillOfLading $bill, array $prepared): string
    {
        if ((int) ($prepared['bill_of_lading_id'] ?? 0) !== (int) $bill->id
            || $bill->is_transit_transshipment !== 'S'
            || $bill->is_fractional) {
            throw new InvalidArgumentException('El plan no corresponde a un título de transbordo no fraccionado.');
        }
        if (!$bill->relationLoaded('shipmentItems')) {
            throw new InvalidArgumentException('Cargá los ítems reales del conocimiento antes de generar la vista previa.');
        }
        $items = $bill->shipmentItems->keyBy('id');
        $shipments = $prepared['shipments'] ?? null;
        if (!is_array($shipments) || count($shipments) < 2 || count($shipments) > 999 || $items->isEmpty()) {
            throw new InvalidArgumentException('Se necesitan entre dos y 999 envíos y líneas reales.');
        }
        $declaration = trim((string) $bill->id_decla);
        if ($declaration === '') {
            $legacy = trim((string) $bill->permiso_embarque);
            if ($legacy !== '' && strtoupper($legacy) !== 'VACIO') {
                $declaration = $legacy;
            }
        }
        if ($declaration === '' || mb_strlen($declaration) > 16) {
            throw new InvalidArgumentException('El título necesita su idDecla real (máximo 16 caracteres).');
        }

        // Antes de serializar, comprobar que ninguna fracción se pierde, duplica
        // o excede los totales originales. Nunca sumar pesos mediante float.
        $totals = [];
        $seenShipments = [];
        $seenContainers = [];
        foreach ($shipments as $index => $shipment) {
            if (!is_array($shipment) || (int) ($shipment['id_envio'] ?? 0) !== $index + 1
                || (int) ($shipment['shipment_id'] ?? 0) < 1
                || !is_array($shipment['lines'] ?? null) || $shipment['lines'] === []) {
                throw new InvalidArgumentException('El envío no tiene secuencia, barcaza o líneas válidas.');
            }
            $shipmentId = (int) $shipment['shipment_id'];
            if (isset($seenShipments[$shipmentId])) {
                throw new InvalidArgumentException('Una barcaza no puede aparecer dos veces en el mismo título.');
            }
            $seenShipments[$shipmentId] = true;
            $seenLines = [];
            foreach ($shipment['lines'] as $line) {
                if (!is_array($line)) {
                    throw new InvalidArgumentException('Línea de reparto inválida.');
                }
                $itemId = (int) ($line['shipment_item_id'] ?? 0);
                /** @var ShipmentItem|null $item */
                $item = $items->get($itemId);
                if (!$item || !$item->relationLoaded('containers') || !$item->relationLoaded('packagingType')) {
                    throw new InvalidArgumentException('El reparto refiere una línea sin sus relaciones originales.');
                }
                $containerId = $line['container_id'] ?? null;
                $key = $itemId . ':' . ($containerId ?? '-');
                if (isset($seenLines[$key])) {
                    throw new InvalidArgumentException('La misma línea y contenedor están duplicados en un envío.');
                }
                $seenLines[$key] = true;
                if ($containerId !== null) {
                    $container = $item->containers->firstWhere('id', $containerId);
                    if (!$container || isset($seenContainers[$containerId])) {
                        throw new InvalidArgumentException('Contenedor ausente del ítem o asignado varias veces.');
                    }
                    $seenContainers[$containerId] = true;
                } elseif ($item->containers->isNotEmpty()) {
                    throw new InvalidArgumentException('Una línea contenedorizada debe identificar el contenedor real.');
                }
                $quantity = filter_var($line['package_quantity'] ?? null, FILTER_VALIDATE_INT);
                if ($quantity === false || $quantity < 0 || $quantity > 999999999) {
                    throw new InvalidArgumentException('Cantidad de bultos inválida.');
                }
                if ($containerId === null && $quantity === 0) {
                    throw new InvalidArgumentException('Los graneles requieren su circuito específico por peso.');
                }
                $weight = $this->hundredths($line['gross_weight_kg'] ?? null);
                $totals[$itemId]['quantity'] = ($totals[$itemId]['quantity'] ?? 0) + $quantity;
                $totals[$itemId]['weight'] = ($totals[$itemId]['weight'] ?? 0) + $weight;
            }
        }
        foreach ($items as $item) {
            $quantity = filter_var($item->package_quantity, FILTER_VALIDATE_INT);
            if ($quantity === false || $quantity < 0
                || ($totals[$item->id]['quantity'] ?? -1) !== $quantity
                || ($totals[$item->id]['weight'] ?? -1) !== $this->hundredths($item->gross_weight_kg)) {
                throw new InvalidArgumentException('Los envíos no completan exactamente los bultos y pesos del título.');
            }
        }

        $w = new XMLWriter();
        $w->openMemory();
        $w->startElement('envios');
        foreach ($shipments as $shipment) {
            $w->startElement('Envio');
            $w->startElement('destinaciones');
            $w->startElement('Destinacion');
            $w->writeElement('idDecla', $declaration);
            $w->startElement('items');
            // En una barcaza puede haber varios contenedores de un mismo ítem.
            $byItem = collect($shipment['lines'])->groupBy('shipment_item_id');
            foreach ($byItem as $itemId => $lines) {
                $item = $items->get((int) $itemId);
                $number = filter_var($item->line_number, FILTER_VALIDATE_INT);
                if ($number === false || $number < 1 || $number > 9999) {
                    throw new InvalidArgumentException('Falta el número de ítem aduanero real.');
                }
                $weight = $lines->sum(fn (array $row) => $this->hundredths($row['gross_weight_kg']));
                $w->startElement('Item');
                $w->writeElement('nroItem', (string) $number);
                $w->writeElement('peso', $this->formatWeight($weight, 8));
                $imo = trim((string) $item->imdg_class);
                if ($item->is_dangerous_goods && ($imo === '' || mb_strlen($imo) > 3)) {
                    throw new InvalidArgumentException('La mercadería peligrosa requiere código IMO real.');
                }
                if ($imo !== '' && mb_strlen($imo) <= 3) {
                    $w->writeElement('codIMO', $imo);
                }
                $w->endElement();
            }
            $w->endElement(); // items
            $w->startElement('bultos');
            foreach ($shipment['lines'] as $line) {
                $item = $items->get((int) $line['shipment_item_id']);
                $containerId = $line['container_id'] ?? null;
                if ($containerId !== null) {
                    $container = $item->containers->firstWhere('id', $containerId);
                    $this->containerBulto->write($w, $bill, $item, $container, (string) $line['gross_weight_kg']);
                    continue;
                }
                $code = $this->packagingCode($item);
                $description = trim((string) $item->item_description);
                $marks = trim((string) $item->cargo_marks);
                if ($description === '' || mb_strlen($description) > 500 || mb_strlen($marks) > 100) {
                    throw new InvalidArgumentException('Descripción o marcas de la carga suelta inválidas.');
                }
                $w->startElement('Bulto');
                $w->writeElement('cantBultos', (string) $line['package_quantity']);
                $w->writeElement('cantBultosTotFrac', (string) $line['package_quantity']);
                $w->writeElement('pesoBruto', $this->formatWeight($this->hundredths($line['gross_weight_kg']), 10));
                $w->writeElement('pesoBrutoTotFrac', $this->formatWeight($this->hundredths($line['gross_weight_kg']), 10));
                $w->writeElement('codTipEmbalaje', $code);
                $w->writeElement('descMercaderia', $description);
                if ($marks !== '') {
                    $w->writeElement('marcaNro', $marks);
                }
                $w->writeElement('indCargSuelt', 'S');
                $w->endElement();
            }
            $w->endElement(); // bultos
            $w->endElement(); // Destinacion
            $w->endElement(); // destinaciones
            $w->writeElement('indUltFra', 'S');
            // Pendientes: idFiscalATAMIC y lugares operativos POR envío,
            // nunca reemplazarlos con identificadores ficticios.
            $w->writeElement('idEnvio', (string) $shipment['id_envio']);
            $w->endElement(); // Envio
        }
        $w->endElement(); // envios
        return $w->outputMemory();
    }

    private function packagingCode(ShipmentItem $item): string
    {
        foreach ([$item->packaging_code, $item->packagingType?->argentina_ws_code,
            $item->packagingType?->unece_code] as $candidate) {
            $code = strtoupper(trim((string) $candidate));
            if (mb_strlen($code) === 2) {
                return $code;
            }
        }
        throw new InvalidArgumentException('La carga suelta no tiene código de embalaje EDIFACT real.');
    }

    private function hundredths(mixed $value): int
    {
        $value = trim((string) $value);
        if (!preg_match('/^\d+(?:\.\d{1,2})?$/D', $value)) {
            throw new InvalidArgumentException('El peso debe ser positivo y tener como máximo dos decimales.');
        }
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        if (strlen(ltrim($whole, '0')) > 10) {
            throw new InvalidArgumentException('El peso supera el rango permitido.');
        }
        return (int) $whole * 100 + (int) str_pad($fraction, 2, '0');
    }

    private function formatWeight(int $hundredths, int $maxIntegerDigits): string
    {
        $whole = intdiv($hundredths, 100);
        if (strlen((string) $whole) > $maxIntegerDigits) {
            throw new InvalidArgumentException('El peso supera el rango aduanero permitido.');
        }
        return $whole . '.' . str_pad((string) ($hundredths % 100), 2, '0', STR_PAD_LEFT) . '00';
    }
}
