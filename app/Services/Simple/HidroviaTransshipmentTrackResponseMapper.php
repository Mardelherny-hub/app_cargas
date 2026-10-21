<?php

namespace App\Services\Simple;

use DOMDocument;
use DOMNode;
use DOMXPath;
use InvalidArgumentException;

/**
 * Resuelve TRACKs de RegistrarTitEnvios por (idTitTrans, idEnvio).
 *
 * Cada título esperado usa datos ya verificados por la aplicación:
 * [idTitTrans, bill_of_lading_id, shipments: [id_envio, shipment_id, vessel_id]].
 * Los registros devueltos corresponden a campos reales de WebserviceTrack.
 * No escribe en BD, no interpreta aceptación aduanera ni transmite SOAP.
 */
final class HidroviaTransshipmentTrackResponseMapper
{
    /** @return array<int, array<string, mixed>> */
    public function mapRegistrarTitEnvios(string $responseXml, array $expectedTitles): array
    {
        if ($expectedTitles === []) {
            throw new InvalidArgumentException('No hay títulos de transbordo esperados.');
        }

        $expected = [];
        $billIds = [];
        foreach ($expectedTitles as $title) {
            if (!is_array($title)) {
                throw new InvalidArgumentException('El título esperado debe contener datos reales.');
            }
            $number = trim((string) ($title['idTitTrans'] ?? ''));
            $billId = filter_var($title['bill_of_lading_id'] ?? null, FILTER_VALIDATE_INT);
            $shipments = $title['shipments'] ?? null;
            // Prefijo para que PHP no transforme idTitTrans='123' en clave 123.
            $titleKey = 'title:' . $number;
            if ($number === '' || mb_strlen($number) > 36 || !$billId || $billId < 1
                || !is_array($shipments) || $shipments === []
                || isset($expected[$titleKey]) || isset($billIds[$billId])) {
                throw new InvalidArgumentException('El título esperado es inválido o está duplicado.');
            }
            $billIds[$billId] = true;
            $byEnvio = [];
            // Una barcaza puede transportar varios títulos diferentes.
            // Unicidad de shipment sólo DENTRO de cada título.
            $shipmentIds = [];
            foreach ($shipments as $shipment) {
                if (!is_array($shipment)) {
                    throw new InvalidArgumentException('Cada envío esperado debe identificar su barcaza.');
                }
                $idEnvio = filter_var($shipment['id_envio'] ?? null, FILTER_VALIDATE_INT);
                $shipmentId = filter_var($shipment['shipment_id'] ?? null, FILTER_VALIDATE_INT);
                $vesselId = filter_var($shipment['vessel_id'] ?? null, FILTER_VALIDATE_INT);
                if (!$idEnvio || $idEnvio < 1 || $idEnvio > 999
                    || !$shipmentId || $shipmentId < 1 || !$vesselId || $vesselId < 1
                    || isset($byEnvio[$idEnvio]) || isset($shipmentIds[$shipmentId])) {
                    throw new InvalidArgumentException('El envío esperado tiene idEnvio o barcaza inválida o duplicada.');
                }
                $shipmentIds[$shipmentId] = true;
                $byEnvio[$idEnvio] = [
                    'shipment_id' => $shipmentId,
                    'vessel_id' => $vesselId,
                    'bill_of_lading_id' => $billId,
                ];
            }
            $expected[$titleKey] = $byEnvio;
        }

        $dom = new DOMDocument();
        if (trim($responseXml) === '' || !@$dom->loadXML($responseXml, LIBXML_NONET)) {
            throw new InvalidArgumentException('La respuesta XML de RegistrarTitEnvios está vacía o es inválida.');
        }
        $xpath = new DOMXPath($dom);
        if ($xpath->query('//*[local-name()="Fault"]')->length > 0) {
            throw new InvalidArgumentException('ARCA devolvió SOAP Fault: no se pueden asociar TRACKs.');
        }
        foreach ($xpath->query('//*[local-name()="DetalleError"]/*[local-name()="Codigo"]') as $code) {
            $value = trim((string) $code->textContent);
            if ($value !== '' && $value !== '0') {
                throw new InvalidArgumentException('ARCA devolvió un error aduanero: no se pueden asociar TRACKs.');
            }
        }
        // Una operación de carga no puede ignorar TRACKs de títulos de vacíos.
        if ($xpath->query('//*[local-name()="titTracksContVacio" or local-name()="titTracksContVacios"]//*[local-name()="idTrack"]')->length > 0) {
            throw new InvalidArgumentException('La respuesta incluye TRACKs de contenedores vacíos no esperados.');
        }

        $titles = $xpath->query('//*[local-name()="titTracksEnv"]/*[local-name()="TitTrackEnv"]');
        if (!$titles || $titles->length !== count($expected)) {
            throw new InvalidArgumentException('ARCA no devolvió exactamente los títulos esperados.');
        }
        $received = [];
        $seenTracks = [];
        foreach ($titles as $node) {
            $number = $this->childText($xpath, $node, 'idTitTrans');
            $titleKey = 'title:' . $number;
            if (!isset($expected[$titleKey]) || isset($received[$titleKey])) {
                throw new InvalidArgumentException("ARCA devolvió un título desconocido o repetido: {$number}.");
            }
            $tracks = $xpath->query('./*[local-name()="tracksEnv"]/*[local-name()="TrackEnv"]', $node);
            if (!$tracks || $tracks->length !== count($expected[$titleKey])) {
                throw new InvalidArgumentException("Cantidad de envíos incorrecta para el título {$number}.");
            }
            $received[$titleKey] = [];
            foreach ($tracks as $trackNode) {
                $track = $this->childText($xpath, $trackNode, 'idTrack');
                $envioText = $this->childText($xpath, $trackNode, 'idEnvio');
                if ($track === '' || mb_strlen($track) > 50 || str_starts_with($track, 'TEST_TRACK_')) {
                    throw new InvalidArgumentException('ARCA devolvió un TRACK vacío, ficticio o fuera de rango.');
                }
                if (!ctype_digit($envioText) || strlen($envioText) > 3
                    || (int) $envioText < 1 || (int) $envioText > 999) {
                    throw new InvalidArgumentException("ARCA devolvió un idEnvio inválido para {$number}.");
                }
                $idEnvio = (int) $envioText;
                if (!isset($expected[$titleKey][$idEnvio]) || isset($received[$titleKey][$idEnvio])) {
                    throw new InvalidArgumentException("idEnvio desconocido o repetido para {$number}: {$envioText}.");
                }
                if (isset($seenTracks[$track])) {
                    throw new InvalidArgumentException("ARCA devolvió el mismo TRACK para distintos envíos: {$track}.");
                }
                $seenTracks[$track] = true;
                $received[$titleKey][$idEnvio] = $track;
            }
        }

        // Rechazar cualquier idTrack externo a TrackEnv: no descartar TRACKs.
        if ($xpath->query('//*[local-name()="idTrack"]')->length !== count($seenTracks)) {
            throw new InvalidArgumentException('La respuesta contiene TRACKs sin relación inequívoca con su envío.');
        }

        // La salida sigue el orden de los títulos/envíos ESPERADOS, nunca el
        // orden de la respuesta SOAP, que ARCA puede devolver diferente.
        $mapped = [];
        foreach ($expected as $titleKey => $envios) {
            $number = substr($titleKey, strlen('title:'));
            foreach ($envios as $idEnvio => $shipment) {
                if (!isset($received[$titleKey][$idEnvio])) {
                    throw new InvalidArgumentException("Falta el TRACK para {$number}, envío {$idEnvio}.");
                }
                $mapped[] = [
                    'shipment_id' => $shipment['shipment_id'],
                    'bill_of_lading_id' => $shipment['bill_of_lading_id'],
                    'track_number' => $received[$titleKey][$idEnvio],
                    'track_type' => 'envio',
                    'webservice_method' => 'RegistrarTitEnvios',
                    'reference_type' => 'bill',
                    'reference_number' => $number,
                    'afip_title_number' => $number,
                    'afip_metadata' => [
                        'idTitTrans' => $number,
                        'idEnvio' => $idEnvio,
                        'vessel_id' => $shipment['vessel_id'],
                        'source_method' => 'RegistrarTitEnvios',
                        'track_group' => 'envio',
                    ],
                ];
            }
        }
        return $mapped;
    }

    private function childText(DOMXPath $xpath, DOMNode $parent, string $name): string
    {
        $nodes = $xpath->query('./*[local-name()="' . $name . '"]', $parent);
        if (!$nodes || $nodes->length !== 1) {
            throw new InvalidArgumentException("La respuesta de ARCA no contiene un único {$name}.");
        }
        return trim((string) $nodes->item(0)->textContent);
    }
}
