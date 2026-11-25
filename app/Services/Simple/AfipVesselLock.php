<?php

namespace App\Services\Simple;

use App\Models\Company;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Determina si una embarcación de un viaje tiene títulos o MIC/DTA que
 * pueden estar vigentes en AFIP. Criterio conservador: ante la duda, bloquea.
 *
 * - Alcance: transacciones AR del viaje en el ambiente vigente de la empresa,
 *   de las cargas de esa embarcación o sin carga asociada.
 * - Bloquean los registros (RegistrarTitEnvios, RegistrarEnvios,
 *   RegistrarMicDta, RegistrarTitMicDta) en estado processing, sending, sent
 *   o success, y en cancelled cuando conservan respuesta de AFIP.
 * - Un registro se libera sólo con una anulación acreditada posterior:
 *   AnularTitulo exitoso por cada título de su XML, o SolicitarAnularMicDta
 *   con aprobación confirmada para su MIC/DTA.
 * - Desvincular títulos, rectificar el convoy o el reset local no liberan.
 */
class AfipVesselLock
{
    private const TITLE_METHODS = ['RegistrarTitEnvios', 'RegistrarEnvios'];
    private const MICDTA_METHODS = ['RegistrarMicDta', 'RegistrarTitMicDta'];
    private const BLOCKING_STATUSES = ['processing', 'sending', 'sent', 'success'];

    /**
     * @return array{locked: bool, reasons: array<int, array{transaction_id: int, method: string, status: string, message: string}>}
     */
    public function inspectAfipVesselLock(int $companyId, int $voyageId, int $vesselId): array
    {
        $vesselShipmentIds = DB::table('shipments')
            ->where('voyage_id', $voyageId)
            ->where('vessel_id', $vesselId)
            ->pluck('id')
            ->all();

        $transactions = $this->voyageTransactions($companyId, $voyageId);

        $annulments = $transactions->filter(
            fn ($tx) => in_array($tx->method, ['AnularTitulo', 'SolicitarAnularMicDta'], true)
        );

        $reasons = [];

        foreach ($transactions as $tx) {
            if (!in_array($tx->method, array_merge(self::TITLE_METHODS, self::MICDTA_METHODS), true)) {
                continue;
            }

            if ($tx->shipment_id !== null && !in_array((int) $tx->shipment_id, $vesselShipmentIds, true)) {
                continue;
            }

            if (!$this->isBlockingStatus($tx)) {
                continue;
            }

            $message = $this->pendingAnnulmentMessage($tx, $annulments);

            if ($message !== null) {
                $reasons[] = [
                    'transaction_id' => (int) $tx->id,
                    'method' => $tx->method,
                    'status' => $tx->status,
                    'message' => $message,
                ];
            }
        }

        return [
            'locked' => $reasons !== [],
            'reasons' => $reasons,
        ];
    }

    private function voyageTransactions(int $companyId, int $voyageId): Collection
    {
        $query = DB::table('webservice_transactions')
            ->where('company_id', $companyId)
            ->where('voyage_id', $voyageId)
            ->where('country', 'AR');

        $environment = $this->companyEnvironment($companyId);
        if ($environment !== null) {
            $query->where('environment', $environment);
        }

        return $query->orderBy('id')->get()->map(function ($tx) {
            $tx->method = $this->methodName((string) $tx->soap_action);
            $tx->success_data = $this->decode($tx->success_data ?? null);
            $tx->additional_metadata = $this->decode($tx->additional_metadata ?? null);

            return $tx;
        });
    }

    /**
     * Sin ambiente válido no se puede acotar: se consideran todos los
     * ambientes, que es la opción que más bloquea.
     */
    private function companyEnvironment(int $companyId): ?string
    {
        $company = Company::find($companyId);

        if (!$company) {
            return null;
        }

        try {
            return WebserviceEnvironment::resolve($company);
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    private function isBlockingStatus(object $tx): bool
    {
        if (in_array($tx->status, self::BLOCKING_STATUSES, true)) {
            return true;
        }

        // Un cancelled puede venir de un reset local: si conserva respuesta
        // de AFIP, el registro pudo quedar vigente.
        return $tx->status === 'cancelled'
            && (trim((string) $tx->external_reference) !== ''
                || !empty($tx->success_data)
                || trim((string) ($tx->response_xml ?? '')) !== '');
    }

    private function pendingAnnulmentMessage(object $tx, Collection $annulments): ?string
    {
        $later = $annulments->filter(fn ($a) => (int) $a->id > (int) $tx->id);

        if (in_array($tx->method, self::TITLE_METHODS, true)) {
            $titles = $this->xmlValues($tx->request_xml, 'idTitTrans');

            if ($titles === []) {
                return 'Registro de títulos en AFIP sin títulos identificables en el envío.';
            }

            $pending = array_values(array_filter(
                $titles,
                fn ($title) => !$this->titleAnnulled($title, $later)
            ));

            return $pending === []
                ? null
                : 'Títulos registrados en AFIP sin anular: ' . implode(', ', $pending) . '.';
        }

        $micDtaId = $tx->method === 'RegistrarMicDta'
            ? trim((string) $tx->external_reference)
            : ($this->xmlValues($tx->request_xml, 'idMicDta')[0] ?? '');

        if ($this->micDtaAnnulled($tx, $micDtaId, $later)) {
            return null;
        }

        return $micDtaId !== ''
            ? "MIC/DTA {$micDtaId} registrado en AFIP sin anulación aprobada."
            : 'MIC/DTA registrado en AFIP sin número identificable y sin anulación aprobada.';
    }

    private function titleAnnulled(string $title, Collection $later): bool
    {
        return $later->contains(fn ($a) => $a->method === 'AnularTitulo'
            && $a->status === 'success'
            && trim((string) ($a->success_data['titulo_anulado'] ?? '')) === $title);
    }

    private function micDtaAnnulled(object $tx, string $micDtaId, Collection $later): bool
    {
        return $later->contains(function ($a) use ($tx, $micDtaId) {
            if ($a->method !== 'SolicitarAnularMicDta'
                || empty($a->success_data['aprobacion_confirmada_at'])) {
                return false;
            }

            $sameTransaction = $tx->method === 'RegistrarMicDta'
                && (int) ($a->additional_metadata['original_micdta_transaction_id'] ?? 0) === (int) $tx->id;

            $sameMicDta = $micDtaId !== ''
                && trim((string) ($a->success_data['micdta_id'] ?? '')) === $micDtaId;

            return $sameTransaction || $sameMicDta;
        });
    }

    /** soap_action se guarda como "Namespace/Metodo" o sólo "Metodo". */
    private function methodName(string $soapAction): string
    {
        $position = strrpos($soapAction, '/');

        return $position === false ? $soapAction : substr($soapAction, $position + 1);
    }

    private function decode(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        $decoded = json_decode((string) $value, true);

        return is_array($decoded) ? $decoded : [];
    }

    /** @return string[] */
    private function xmlValues(?string $xml, string $element): array
    {
        if ($xml === null || $xml === '') {
            return [];
        }

        preg_match_all('#<(?:\w+:)?' . $element . '>\s*([^<]+?)\s*</(?:\w+:)?' . $element . '>#', $xml, $matches);

        return array_values(array_unique(array_map(
            fn ($value) => html_entity_decode($value, ENT_QUOTES | ENT_XML1),
            $matches[1]
        )));
    }
}
