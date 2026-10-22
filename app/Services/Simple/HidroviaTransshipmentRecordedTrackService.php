<?php

namespace App\Services\Simple;

use App\Models\Voyage;
use App\Models\WebserviceTransaction;
use App\Models\WebserviceTrack;
use DOMDocument;
use DOMElement;
use DOMXPath;
use InvalidArgumentException;

/**
 * Vincula un RegistrarTitEnvios aceptado con el reparto real de un conocimiento.
 * No crea transacciones, no solicita WSAA ni efectúa llamadas SOAP.
 */
final class HidroviaTransshipmentRecordedTrackService
{
    public function __construct(
        private readonly HidroviaTransshipmentReadinessService $readiness,
        private readonly HidroviaTransshipmentTrackExpectationBuilder $expectations,
        private readonly HidroviaTransshipmentTrackPersistenceService $persistence,
    ) {}

    /** @return array<int, WebserviceTrack> */
    public function persistAcceptedResponse(int $companyId, int $transactionId, int $billId): array
    {
        if ($companyId < 1 || $transactionId < 1 || $billId < 1) {
            throw new InvalidArgumentException('Se requieren empresa, transacción y conocimiento reales.');
        }

        $transaction = WebserviceTransaction::query()->findOrFail($transactionId);
        if ((int) $transaction->company_id !== $companyId
            || $transaction->status !== 'success'
            || $transaction->webservice_type !== 'micdta'
            || $transaction->country !== 'AR'
            || $transaction->soap_action !== 'Ar.Gob.Afip.Dga.wgesregsintia2/RegistrarTitEnvios'
            || !$transaction->voyage_id
            || trim((string) $transaction->response_xml) === '') {
            throw new InvalidArgumentException('La transacción no es un RegistrarTitEnvios aceptado de la empresa.');
        }

        $voyage = Voyage::query()->where('company_id', $companyId)
            ->findOrFail($transaction->voyage_id);
        $bill = $voyage->billsOfLading()->findOrFail($billId);

        // La secuencia idEnvio/barcaza se deriva de asignaciones actuales:
        // no se acepta un mapa de TRACKs construido por el consumidor.
        $prepared = $this->readiness->prepare($voyage, $companyId, $bill);
        $expected = $this->expectations->fromPrepared($bill, $prepared);
        if ($transaction->shipment_id !== null
            && (int) $transaction->shipment_id !== (int) $bill->shipment_id) {
            throw new InvalidArgumentException('La transacción pertenece a otro conocimiento de origen.');
        }
        $this->assertSentRequestMatches($transaction->request_xml, $bill->bill_number, $prepared);

        // Ya comprueba respuesta ARCA, transacción, barcazas, conflictos,
        // atomicidad e idempotencia; no duplicar esa lógica aquí.
        return $this->persistence->persistRegistrarTitEnvios($transactionId, $expected);
    }

    /**
     * El idEnvio de la respuesta debe haber existido en la solicitud REAL.
     * Verificar además los ítems y pesos enviados por cada secuencia evita
     * asociar TRACKs a un borrador diferente del transmitido a ARCA.
     */
    private function assertSentRequestMatches(?string $requestXml, string $billNumber, array $prepared): void
    {
        $dom = new DOMDocument();
        if (!is_string($requestXml) || trim($requestXml) === ''
            || !@$dom->loadXML($requestXml, LIBXML_NONET)) {
            throw new InvalidArgumentException('Falta el XML real enviado a ARCA para comprobar los TRACKs.');
        }
        $xpath = new DOMXPath($dom);
        $titles = $xpath->query('//*[local-name()="argRegistrarTitEnviosParam"]'
            . '/*[local-name()="titulosTransEnvios"]/*[local-name()="TitTransEnvio"]');
        if ($titles->length !== 1
            || $this->text($xpath, $titles->item(0), 'idTitTrans') !== trim($billNumber)) {
            throw new InvalidArgumentException('El XML transmitido no corresponde a un único conocimiento esperado.');
        }

        $sent = $xpath->query('./*[local-name()="envios"]/*[local-name()="Envio"]', $titles->item(0));
        if ($sent->length !== count($prepared['shipments'])) {
            throw new InvalidArgumentException('La cantidad de envíos transmitidos difiere del reparto guardado.');
        }

        $expectedItems = [];
        foreach ($prepared['shipments'] as $shipment) {
            $weights = [];
            foreach ($shipment['lines'] as $line) {
                $item = (int) $line['shipment_item_id'];
                $weights[$item] = ($weights[$item] ?? 0) + $this->hundredths($line['gross_weight_kg']);
            }
            $expectedItems[(int) $shipment['id_envio']] = $weights;
        }

        $actualIds = [];
        foreach ($sent as $envio) {
            $id = $this->text($xpath, $envio, 'idEnvio');
            if (!ctype_digit($id) || !isset($expectedItems[(int) $id])
                || isset($actualIds[(int) $id])) {
                throw new InvalidArgumentException('El XML transmitido contiene idEnvio ajeno o duplicado.');
            }
            $actualIds[(int) $id] = true;
            $items = $xpath->query('./*[local-name()="destinaciones"]'
                . '/*[local-name()="Destinacion"]/*[local-name()="items"]/*[local-name()="Item"]', $envio);
            // Los IDs locales no se transmiten; nroItem se obtiene de las líneas reales.
            $billItems = \App\Models\ShipmentItem::query()
                ->whereIn('id', array_keys($expectedItems[(int) $id]))
                ->get(['id', 'line_number'])->keyBy('line_number');
            $weightsSent = [];
            foreach ($items as $item) {
                $number = $this->text($xpath, $item, 'nroItem');
                $local = $billItems->get($number);
                if (!$local || isset($weightsSent[$local->id])) {
                    throw new InvalidArgumentException('El XML transmitido contiene ítems ajenos o duplicados.');
                }
                $weightsSent[$local->id] = $this->hundredths($this->text($xpath, $item, 'peso'));
            }
            ksort($weightsSent);
            $expected = $expectedItems[(int) $id];
            ksort($expected);
            if ($weightsSent !== $expected) {
                throw new InvalidArgumentException('El XML transmitido no coincide con los pesos distribuidos.');
            }
        }
    }

    private function text(DOMXPath $xpath, DOMElement $parent, string $name): string
    {
        $nodes = $xpath->query('./*[local-name()="' . $name . '"]', $parent);
        if ($nodes->length !== 1) {
            throw new InvalidArgumentException("El XML transmitido requiere un único {$name}.");
        }
        return trim($nodes->item(0)->textContent);
    }

    private function hundredths(mixed $value): int
    {
        $value = trim((string) $value);
        if (!preg_match('/^(\d+)(?:\.(\d{1,4}))?$/D', $value, $parts)) {
            throw new InvalidArgumentException('Peso del XML transmitido inválido.');
        }
        $fraction = str_pad($parts[2] ?? '', 4, '0');
        if (substr($fraction, 2) !== '00' || strlen(ltrim($parts[1], '0')) > 10) {
            throw new InvalidArgumentException('El peso transmitido no coincide con la precisión del borrador.');
        }
        return (int) $parts[1] * 100 + (int) substr($fraction, 0, 2);
    }
}
