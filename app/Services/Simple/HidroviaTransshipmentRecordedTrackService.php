<?php

namespace App\Services\Simple;

use App\Models\Voyage;
use App\Models\WebserviceTransaction;
use App\Models\WebserviceTrack;
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

        // Ya comprueba respuesta ARCA, transacción, barcazas, conflictos,
        // atomicidad e idempotencia; no duplicar esa lógica aquí.
        return $this->persistence->persistRegistrarTitEnvios($transactionId, $expected);
    }
}
