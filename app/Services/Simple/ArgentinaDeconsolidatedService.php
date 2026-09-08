<?php

namespace App\Services\Simple;

use App\Models\BillOfLading;
use App\Models\Voyage;
use App\Models\WebserviceResponse;
use App\Models\WebserviceTransaction;
use App\Services\Webservice\Argentina\SimpleXmlGeneratorDesconsolidado;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Circuito AFIP ATA Desconsolidador sobre wgesinformacionanticipada.
 *
 * Operaciones soportadas:
 * - RegistrarTitulosDesconsolidador
 * - RectificarTitulosDesconsolidador
 * - EliminarTitulosDesconsolidador
 *
 * La transacción persistida y el IdTransaccion enviado a AFIP son el mismo.
 */
class ArgentinaDeconsolidatedService extends BaseWebserviceService
{
    private ?SimpleXmlGeneratorDesconsolidado $deconsolidatedXml = null;

    protected function getWebserviceConfig(): array
    {
        $environment = $this->company->ws_environment ?? 'testing';

        $urls = [
            'testing' => [
                'endpoint' => 'https://wsaduhomoext.afip.gob.ar/DIAV2/wgesinformacionanticipada/wgesinformacionanticipada.asmx',
                'wsdl' => 'https://wsaduhomoext.afip.gob.ar/DIAV2/wgesinformacionanticipada/wgesinformacionanticipada.asmx?wsdl',
            ],
            'production' => [
                'endpoint' => 'https://webservicesadu.afip.gob.ar/DIAV2/wgesinformacionanticipada/wgesinformacionanticipada.asmx',
                'wsdl' => 'https://webservicesadu.afip.gob.ar/DIAV2/wgesinformacionanticipada/wgesinformacionanticipada.asmx?wsdl',
            ],
        ];

        if (!isset($urls[$environment])) {
            throw new Exception("Ambiente AFIP no válido para desconsolidados: {$environment}");
        }

        return [
            'webservice_type' => 'desconsolidado',
            'country' => 'AR',
            'environment' => $environment,
            'webservice_url' => $urls[$environment]['endpoint'],
            'wsdl_url' => $urls[$environment]['wsdl'],
            'soap_action_registrar' => 'Ar.Gob.Afip.Dga.Org.wgesinformacionanticipada/RegistrarTitulosDesconsolidador',
            'soap_action_rectificar' => 'Ar.Gob.Afip.Dga.Org.wgesinformacionanticipada/RectificarTitulosDesconsolidador',
            'soap_action_eliminar' => 'Ar.Gob.Afip.Dga.Org.wgesinformacionanticipada/EliminarTitulosDesconsolidador',
            'timeout_seconds' => 90,
            'max_retries' => 3,
            'require_certificate' => true,
        ];
    }

    protected function getWebserviceType(): string
    {
        return 'desconsolidado';
    }

    protected function getCountry(): string
    {
        return 'AR';
    }

    protected function getWsdlUrl(): string
    {
        return $this->config['wsdl_url'];
    }

    protected function validateSpecificData(Voyage $voyage): array
    {
        $errors = [];
        $warnings = [];

        if ((int) $voyage->company_id !== (int) $this->company->id) {
            $errors[] = 'El viaje no pertenece a la empresa conectada.';
        }

        if (empty($voyage->argentina_voyage_id)) {
            $errors[] = 'El viaje no tiene IdentificadorViaje AFIP; primero debe existir un RegistrarViaje exitoso.';
        } elseif (mb_strlen((string) $voyage->argentina_voyage_id) > 16) {
            $errors[] = 'El IdentificadorViaje AFIP supera 16 caracteres.';
        }

        if (!$this->company->getCertificatePath()) {
            $errors[] = 'La empresa no tiene certificado Argentina configurado.';
        }

        $bills = $voyage->billsOfLading()
            ->whereNotNull('master_bill_number')
            ->with(['loadingPort', 'dischargePort', 'shipmentItems'])
            ->get();

        if ($bills->isEmpty()) {
            $errors[] = 'No hay conocimientos desconsolidados con master_bill_number en este viaje.';
        }

        foreach ($bills as $bill) {
            if (empty($bill->bill_number)) {
                $errors[] = "BillOfLading {$bill->id}: falta bill_number.";
            }
            if (!$bill->loadingPort || empty($bill->loadingPort->code)) {
                $errors[] = "BL {$bill->bill_number}: falta código de puerto de embarque.";
            }
            if (!$bill->dischargePort || empty($bill->dischargePort->code)) {
                $errors[] = "BL {$bill->bill_number}: falta código de puerto de descarga.";
            }
            if ($bill->shipmentItems->isEmpty()) {
                $errors[] = "BL {$bill->bill_number}: no tiene líneas de mercadería.";
            }
        }

        return [
            'errors' => array_values(array_unique($errors)),
            'warnings' => array_values(array_unique($warnings)),
            'bills_count' => $bills->count(),
        ];
    }

    protected function sendSpecificWebservice(Voyage $voyage, array $options = []): array
    {
        $method = $options['method'] ?? 'registrar';
        $billIds = $this->normalizeBillIds($options['bill_ids'] ?? []);

        return match ($method) {
            'registrar' => $this->registrarTitulos($voyage, $billIds),
            'rectificar' => $this->rectificarTitulos($voyage, $billIds),
            'eliminar' => $this->eliminarTitulos($voyage, $billIds),
            default => throw new Exception("Método desconsolidado desconocido: {$method}"),
        };
    }

    public function registrarTitulos(Voyage $voyage, array $billIds = []): array
    {
        return $this->executeMethod(
            $voyage,
            'registrar',
            'RegistrarTitulosDesconsolidador',
            $billIds
        );
    }

    public function rectificarTitulos(Voyage $voyage, array $billIds = []): array
    {
        return $this->executeMethod(
            $voyage,
            'rectificar',
            'RectificarTitulosDesconsolidador',
            $billIds
        );
    }

    public function eliminarTitulos(Voyage $voyage, array $billIds = []): array
    {
        if ($billIds === []) {
            return [
                'success' => false,
                'error' => 'Debe seleccionar explícitamente al menos un conocimiento para eliminar.',
                'error_message' => 'Debe seleccionar explícitamente al menos un conocimiento para eliminar.',
                'transaction_id' => null,
            ];
        }

        return $this->executeMethod(
            $voyage,
            'eliminar',
            'EliminarTitulosDesconsolidador',
            $billIds
        );
    }

    private function executeMethod(
        Voyage $voyage,
        string $methodType,
        string $soapMethod,
        array $billIds
    ): array {
        $transactionId = $this->generateTransactionId();
        $transaction = null;

        try {
            $this->assertVoyageOwnership($voyage);
            $selectedBills = $this->selectedBills($voyage, $billIds);

            $transaction = $this->createDeconsolidatedTransaction(
                $voyage,
                $transactionId,
                $methodType,
                $selectedBills
            );
            $this->currentTransactionId = $transaction->id;

            $xml = $this->generateXml($voyage, $methodType, $transactionId, $billIds);

            $transaction->update([
                'request_xml' => $xml,
                'status' => 'sending',
                'sent_at' => now(),
            ]);

            $soapResult = $this->sendSoapRequest($transaction, $xml, $soapMethod, $methodType);

            if ($soapResult['success']) {
                $this->persistSuccess($transaction, $voyage, $methodType, $selectedBills, $soapResult);

                return [
                    'success' => true,
                    'transaction_id' => $transaction->id,
                    'client_transaction_id' => $transactionId,
                    'identifier' => $soapResult['identifier'],
                    'warnings' => $soapResult['details'],
                    'message' => "{$soapMethod} aceptado por AFIP.",
                ];
            }

            $this->persistError($transaction, $voyage, $methodType, $soapResult);

            return [
                'success' => false,
                'transaction_id' => $transaction->id,
                'client_transaction_id' => $transactionId,
                'error' => $soapResult['error_message'],
                'error_message' => $soapResult['error_message'],
                'error_details' => $soapResult['details'],
            ];
        } catch (Exception $e) {
            if ($transaction) {
                $transaction->update([
                    'status' => 'error',
                    'response_at' => now(),
                    'error_message' => $e->getMessage(),
                    'error_details' => [
                        ['source' => 'application', 'description' => $e->getMessage()],
                    ],
                ]);

                $this->updateWebserviceStatus($voyage, 'error', [
                    'last_transaction_id' => $transaction->transaction_id,
                    'last_error_message' => $e->getMessage(),
                    'can_send' => true,
                ]);
            }

            $this->logOperation('error', "Error en {$soapMethod}", [
                'voyage_id' => $voyage->id,
                'method' => $methodType,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'transaction_id' => $transaction?->id,
                'client_transaction_id' => $transactionId,
                'error' => $e->getMessage(),
                'error_message' => $e->getMessage(),
            ];
        } finally {
            $this->currentTransactionId = null;
        }
    }

    private function assertVoyageOwnership(Voyage $voyage): void
    {
        if ((int) $voyage->company_id !== (int) $this->company->id) {
            throw new Exception('El viaje no pertenece a la empresa conectada.');
        }
    }

    private function selectedBills(Voyage $voyage, array $billIds): Collection
    {
        $query = $voyage->billsOfLading()->whereNotNull('master_bill_number');

        if ($billIds !== []) {
            $query->whereIn('bills_of_lading.id', $billIds);
        }

        $bills = $query->orderBy('bills_of_lading.id')->get();

        if ($bills->isEmpty()) {
            throw new Exception('No hay títulos desconsolidados para la operación solicitada.');
        }

        if ($billIds !== [] && $bills->count() !== count($billIds)) {
            throw new Exception('Uno o más conocimientos seleccionados no pertenecen al viaje o no son desconsolidados.');
        }

        return $bills;
    }

    private function generateXml(
        Voyage $voyage,
        string $methodType,
        string $transactionId,
        array $billIds
    ): string {
        if (!$this->deconsolidatedXml) {
            $this->deconsolidatedXml = new SimpleXmlGeneratorDesconsolidado($voyage, [
                'environment' => $this->config['environment'],
            ]);
        }

        return match ($methodType) {
            'registrar' => $this->deconsolidatedXml->generateRegistrar($transactionId, $billIds),
            'rectificar' => $this->deconsolidatedXml->generateRectificar($transactionId, $billIds),
            'eliminar' => $this->deconsolidatedXml->generateEliminar($transactionId, $billIds),
            default => throw new Exception("Tipo de operación desconocido: {$methodType}"),
        };
    }

    private function createDeconsolidatedTransaction(
        Voyage $voyage,
        string $transactionId,
        string $methodType,
        Collection $bills
    ): WebserviceTransaction {
        return WebserviceTransaction::create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'voyage_id' => $voyage->id,
            'transaction_id' => $transactionId,
            'webservice_type' => 'desconsolidado',
            'country' => 'AR',
            'webservice_url' => $this->config['webservice_url'],
            'soap_action' => $this->config["soap_action_{$methodType}"],
            'status' => 'validating',
            'retry_count' => 0,
            'max_retries' => $this->config['max_retries'],
            'environment' => $this->config['environment'],
            'certificate_used' => $this->company->getCertificatePath(),
            'currency_code' => 'USD',
            'container_count' => $this->countContainers($bills),
            'bill_of_lading_count' => $bills->count(),
            'additional_metadata' => [
                'method' => $methodType,
                'bill_ids' => $bills->pluck('id')->values()->all(),
                'bill_numbers' => $bills->pluck('bill_number')->values()->all(),
                'argentina_voyage_id' => $voyage->argentina_voyage_id,
            ],
        ]);
    }

    private function sendSoapRequest(
        WebserviceTransaction $transaction,
        string $xml,
        string $soapMethod,
        string $methodType
    ): array {
        $client = $this->createDeconsolidatedSoapClient();
        $start = microtime(true);
        $responseXml = '';

        try {
            $responseXml = (string) $client->__doRequest(
                $xml,
                $this->config['webservice_url'],
                $this->config["soap_action_{$methodType}"],
                SOAP_1_1
            );

            $responseTime = (int) round((microtime(true) - $start) * 1000);

            $transaction->update([
                'request_xml' => $xml,
                'response_xml' => $responseXml,
                'response_time_ms' => $responseTime,
                'response_at' => now(),
            ]);

            return $this->parseAfipResponse($responseXml, $soapMethod);
        } catch (\SoapFault $e) {
            $responseTime = (int) round((microtime(true) - $start) * 1000);
            $lastResponse = (string) ($client->__getLastResponse() ?: $responseXml);

            $transaction->update([
                'response_xml' => $lastResponse ?: null,
                'response_time_ms' => $responseTime,
                'response_at' => now(),
            ]);

            return [
                'success' => false,
                'identifier' => null,
                'details' => [[
                    'source' => 'soap_fault',
                    'code' => (string) $e->faultcode,
                    'description' => $e->getMessage(),
                    'additional' => null,
                ]],
                'error_message' => 'SOAP Fault: ' . $e->getMessage(),
            ];
        }
    }

    private function createDeconsolidatedSoapClient(): \SoapClient
    {
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
            ],
        ]);

        return new \SoapClient($this->config['wsdl_url'], [
            'trace' => true,
            'exceptions' => true,
            'soap_version' => SOAP_1_1,
            'encoding' => 'UTF-8',
            'cache_wsdl' => WSDL_CACHE_NONE,
            'connection_timeout' => $this->config['timeout_seconds'],
            'stream_context' => $context,
        ]);
    }

    private function parseAfipResponse(string $responseXml, string $soapMethod): array
    {
        if (trim($responseXml) === '') {
            return [
                'success' => false,
                'identifier' => null,
                'details' => [],
                'error_message' => 'AFIP devolvió una respuesta vacía.',
            ];
        }

        $dom = new \DOMDocument();
        if (!@$dom->loadXML($responseXml)) {
            return [
                'success' => false,
                'identifier' => null,
                'details' => [],
                'error_message' => 'La respuesta de AFIP no es XML válido.',
            ];
        }

        $xpath = new \DOMXPath($dom);
        $fault = $xpath->query('//*[local-name()="Fault"]')->item(0);
        if ($fault) {
            $faultString = $xpath->query('.//*[local-name()="faultstring"]', $fault)->item(0)?->textContent
                ?: 'SOAP Fault sin descripción';

            return [
                'success' => false,
                'identifier' => null,
                'details' => [[
                    'source' => 'soap_fault',
                    'code' => null,
                    'description' => trim($faultString),
                    'additional' => null,
                ]],
                'error_message' => trim($faultString),
            ];
        }

        $result = $xpath->query('//*[local-name()="' . $soapMethod . 'Result"]')->item(0);
        if (!$result) {
            return [
                'success' => false,
                'identifier' => null,
                'details' => [],
                'error_message' => "AFIP no devolvió {$soapMethod}Result.",
            ];
        }

        $identifier = trim((string) ($xpath->query('.//*[local-name()="IdentificadorViaje"]', $result)->item(0)?->textContent ?? ''));
        $details = [];

        foreach ($xpath->query('.//*[local-name()="DetalleError"]', $result) as $errorNode) {
            $details[] = [
                'source' => 'afip',
                'code' => trim((string) ($xpath->query('./*[local-name()="Codigo"]', $errorNode)->item(0)?->textContent ?? '')),
                'description' => trim((string) ($xpath->query('./*[local-name()="Descripcion"]', $errorNode)->item(0)?->textContent ?? '')),
                'additional' => trim((string) ($xpath->query('./*[local-name()="DescripcionAdicional"]', $errorNode)->item(0)?->textContent ?? '')),
            ];
        }

        if ($identifier !== '') {
            return [
                'success' => true,
                'identifier' => $identifier,
                'details' => $details,
                'error_message' => null,
            ];
        }

        $messages = collect($details)
            ->map(function (array $detail): string {
                $parts = array_filter([
                    $detail['code'] ? "AFIP {$detail['code']}" : null,
                    $detail['description'] ?: null,
                    $detail['additional'] ?: null,
                ]);
                return implode(' - ', $parts);
            })
            ->filter()
            ->values();

        return [
            'success' => false,
            'identifier' => null,
            'details' => $details,
            'error_message' => $messages->isNotEmpty()
                ? $messages->implode(' | ')
                : 'AFIP no devolvió IdentificadorViaje ni detalle de error.',
        ];
    }

    private function persistSuccess(
        WebserviceTransaction $transaction,
        Voyage $voyage,
        string $methodType,
        Collection $bills,
        array $soapResult
    ): void {
        $transaction->update([
            'status' => 'success',
            'external_reference' => $soapResult['identifier'],
            'confirmation_number' => $soapResult['identifier'],
            'success_data' => [
                'method' => $methodType,
                'identifier_viaje' => $soapResult['identifier'],
                'bill_ids' => $bills->pluck('id')->values()->all(),
                'bill_numbers' => $bills->pluck('bill_number')->values()->all(),
                'warnings' => $soapResult['details'],
            ],
            'error_code' => null,
            'error_message' => null,
            'error_details' => null,
            'response_at' => now(),
        ]);

        WebserviceResponse::create([
            'transaction_id' => $transaction->id,
            'response_type' => $soapResult['details'] === [] ? 'success' : 'partial_success',
            'requires_action' => false,
            'processing_status' => 'completed',
            'confirmation_number' => $soapResult['identifier'],
            'external_reference' => $soapResult['identifier'],
            'voyage_number' => $voyage->voyage_number,
            'bill_of_lading_numbers' => $bills->pluck('bill_number')->values()->all(),
            'validation_warnings' => $soapResult['details'],
            'customs_status' => 'approved',
            'customs_processed_at' => now(),
            'processed_at' => now(),
            'is_final_response' => true,
            'additional_data' => ['method' => $methodType],
        ]);

        $this->updateWebserviceStatus($voyage, 'approved', [
            'last_transaction_id' => $transaction->transaction_id,
            'confirmation_number' => $soapResult['identifier'],
            'external_voyage_number' => $soapResult['identifier'],
            'last_sent_at' => now(),
            'approved_at' => now(),
            'last_error_code' => null,
            'last_error_message' => null,
            'can_send' => true,
        ]);

        $this->logOperation('info', "Desconsolidado {$methodType} aceptado por AFIP", [
            'transaction_id' => $transaction->id,
            'id_transaccion' => $transaction->transaction_id,
            'voyage_id' => $voyage->id,
            'bill_ids' => $bills->pluck('id')->values()->all(),
            'identifier' => $soapResult['identifier'],
            'warnings' => $soapResult['details'],
        ]);
    }

    private function persistError(
        WebserviceTransaction $transaction,
        Voyage $voyage,
        string $methodType,
        array $soapResult
    ): void {
        $firstCode = collect($soapResult['details'])->pluck('code')->filter()->first();

        $transaction->update([
            'status' => 'error',
            'error_code' => $firstCode,
            'error_message' => $soapResult['error_message'],
            'error_details' => $soapResult['details'],
            'response_at' => now(),
        ]);

        WebserviceResponse::create([
            'transaction_id' => $transaction->id,
            'response_type' => 'business_error',
            'requires_action' => true,
            'processing_status' => 'requires_manual',
            'voyage_number' => $voyage->voyage_number,
            'business_errors' => $soapResult['details'],
            'customs_status' => 'rejected',
            'customs_processed_at' => now(),
            'processed_at' => now(),
            'is_final_response' => true,
            'additional_data' => ['method' => $methodType],
        ]);

        $this->updateWebserviceStatus($voyage, 'error', [
            'last_transaction_id' => $transaction->transaction_id,
            'last_error_code' => $firstCode,
            'last_error_message' => $soapResult['error_message'],
            'last_sent_at' => now(),
            'can_send' => true,
        ]);

        $this->logOperation('error', "Desconsolidado {$methodType} rechazado por AFIP", [
            'transaction_id' => $transaction->id,
            'id_transaccion' => $transaction->transaction_id,
            'voyage_id' => $voyage->id,
            'error' => $soapResult['error_message'],
            'details' => $soapResult['details'],
        ]);
    }

    private function countContainers(Collection $bills): int
    {
        return $bills
            ->flatMap(fn (BillOfLading $bill) => $bill->shipmentItems()->with('containers')->get())
            ->flatMap(fn ($item) => $item->containers)
            ->unique('id')
            ->count();
    }

    private function normalizeBillIds(array $billIds): array
    {
        return collect($billIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * IdTransaccion: máximo 20 caracteres según el manual AFIP.
     */
    protected function generateTransactionId(): string
    {
        return 'DEC' . now()->format('ymdHis') . Str::upper(Str::random(5));
    }
}
