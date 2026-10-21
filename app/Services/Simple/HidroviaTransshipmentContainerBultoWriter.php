<?php

namespace App\Services\Simple;

use App\Models\BillOfLading;
use App\Models\Container;
use App\Models\ShipmentItem;
use InvalidArgumentException;
use XMLWriter;

/**
 * Escribe únicamente el Bulto de un contenedor con mercadería de trasbordo.
 * Manual ARCA wgesregsintia2 v01.05, pp. 9 y 62-63 (estructura Bulto):
 * cantBultos y marcaNro están prohibidos para contenedores de trasbordo.
 * El manual no resuelve inequívocamente cantBultosTotFrac en este caso:
 * esta clase es un fragmento LOCAL no aprobado aún para envío SOAP.
 *
 * No modifica el generador MIC/DTA histórico, no envía SOAP y no inventa
 * cantidades, códigos ni pesos: éstos provienen de la distribución validada.
 */
final class HidroviaTransshipmentContainerBultoWriter
{
    public function write(
        XMLWriter $writer,
        BillOfLading $bill,
        ShipmentItem $item,
        Container $container,
        string $grossWeightKg
    ): void {
        if ($bill->is_transit_transshipment !== 'S') {
            throw new InvalidArgumentException('El conocimiento no está marcado para tránsito/transbordo.');
        }
        if ($bill->is_fractional) {
            throw new InvalidArgumentException(
                'Los transbordos fraccionados requieren totales por fracción, todavía no implementados.'
            );
        }
        if (strtoupper(trim((string) $container->condition)) === 'V') {
            throw new InvalidArgumentException('Un contenedor vacío no puede declararse como Bulto de mercadería.');
        }

        $number = trim((string) $container->container_number);
        if ($number === '' || mb_strlen($number) > 16) {
            throw new InvalidArgumentException('Falta el número real de contenedor, máximo 16 caracteres.');
        }

        // Peso declarado para ESTA barcaza, no el total del conocimiento.
        $grossWeightKg = trim($grossWeightKg);
        if (!preg_match('/^\d+(?:\.\d{1,4})?$/D', $grossWeightKg)) {
            throw new InvalidArgumentException('Peso de transbordo inválido: máximo cuatro decimales.');
        }
        [$integer, $fraction] = array_pad(explode('.', $grossWeightKg, 2), 2, '');
        if (strlen(ltrim($integer, '0')) > 10) {
            throw new InvalidArgumentException('Peso de transbordo fuera del límite N(14,4).');
        }
        $weight = ltrim($integer, '0');
        $weight = ($weight === '' ? '0' : $weight) . '.' . str_pad($fraction, 4, '0');

        // El EDIFACT 7065 CN representa contenedor. Solo se acepta un código
        // realmente informado o el mapeo ya existente T/CONTENEDOR -> CN.
        $codes = [
            trim((string) $item->packaging_code),
            trim((string) $item->packagingType?->argentina_ws_code),
            trim((string) $item->packagingType?->unece_code),
        ];
        $code = null;
        foreach ($codes as $candidate) {
            if (mb_strlen($candidate) === 2) {
                $code = strtoupper($candidate);
                break;
            }
        }
        if ($code === null
            && strtoupper(trim((string) $item->packagingType?->code)) === 'T'
            && mb_strtoupper(trim((string) $item->packagingType?->name)) === 'CONTENEDOR') {
            $code = 'CN';
        }
        if ($code !== 'CN') {
            throw new InvalidArgumentException(
                'Falta un código de embalaje CN de contenedor real para el transbordo.'
            );
        }

        $description = trim((string) $item->item_description);
        if (mb_strlen($description) > 500) {
            throw new InvalidArgumentException('La descripción de la mercadería supera 500 caracteres.');
        }

        $writer->startElement('Bulto');
        // Prohibidos para contenedores de transbordo: cantBultos, marcaNro.
        // cantBultosTotFrac necesita confirmación de contrato antes de enviar:
        // no inventamos un total ni asumimos que esté prohibido.
        $writer->writeElement('pesoBruto', $weight);
        $writer->writeElement('pesoBrutoTotFrac', $weight);
        $writer->writeElement('codTipEmbalaje', $code);
        if ($description !== '') {
            $writer->writeElement('descMercaderia', $description);
        }
        $writer->writeElement('indCargSuelt', 'N');
        $writer->writeElement('idContenedor', $number);
        $writer->endElement();
    }
}
