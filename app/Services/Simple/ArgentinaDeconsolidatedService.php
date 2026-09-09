<?php

namespace App\Services\Simple;

use App\Models\BillOfLading;
use App\Models\Voyage;
use App\Models\WebserviceResponse;
use App\Models\WebserviceTransaction;
use App\Services\Webservice\Argentina\SimpleXmlGeneratorDesconsolidado;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Servicio AFIP para ATA Desconsolidador.
 *
 * Reglas principales:
 * - una única IdTransaccion por llamada (máximo 20 caracteres);
 * - XML completo + SOAP 1.1 real;
 * - persistencia de request, response, errores y warnings;
 * - ciclo de vida por BillOfLading, derivado de las transacciones exitosas.
 */
class ArgentinaDeconsolidatedService extends BaseWebserviceService
{
    private ?SimpleXmlGeneratorDesconsolidado $deconsolidatedXml = null;

    protected function getWebserviceConfig(): array
    {
        $environment = $this->company->ws_environment ?? 'testing';
        $endpoints = [
            'testing' => [
                'endpoint' => 'https://wsaduhomoext.afip.gob.ar/DIAV2/wgesinformacionanticipada/wgesinformacionanticipada.asmx',
                'wsdl' => 'https://wsaduhomoext.afip.gob.ar/DIAV2/wgesinformacionanticipada/wgesinformacionanticipada.asmx?wsdl',
            ],
            'production' => [
                'endpoint' => 'https://webservicesadu.afip.gob.ar/DIAV2/wgesinformacionanticipada/wgesinformacionanticipada.asmx',
                'wsdl' => 'https://webservicesadu.afip.gob.ar/DIAV2/wgesinformacionanticipada/wgesinformacionanticipada.asmx?wsdl',
            ],
        ];

        if (!isset($endpoints[$environment])) {
            throw new Exception("Ambiente AFIP no válido para desconsolidados: {$environment}");
        }

        return [
            'webservice_type' => 'desconsolidado',
            'country' => 'AR',
            'environment' => $environment,
            'webservice_url' => $endpoints[$environment]['endpoint'],
            'wsdl_url' => $endpoints[$environment]['wsdl'],
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

    /**
     * Validación rápida para la pantalla. La validación contractual completa
     * ocurre dentro del generador inmediatamente antes de solicitar el TA.
     */
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
            ->with(['shipmentItems'])
            ->get();

        if ($bills->isEmpty()) {
            $errors[] = 'No hay conocimientos desconsolidados con master_bill_number en este viaje.';
        }

        foreach ($bills as $bill) {
            if (empty($bill->bill_number)) {
                $errors[] = "BillOfLading {$bill->id}: falta bill_number.";
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
        return $this->executeMethod(
            $voyage,
            'eliminar',
            'EliminarTitulosDesconsolidador',
            $billIds
        );
    }

    /**
     * Estado aduanero derivado por BL a partir del historial exitoso.
     *
     * Valores posibles:
     * - null: nunca registrado (o sin evidencia nueva canónica);
     * - registrar / rectificar: título actualmente registrado;
     * - eliminar: título eliminado en la última operación exitosa.
     */
    public function billLifecycleStates(Voyage $voyage, array $billIds = []): array
    {
        $this->assertVoyageOwnership($voyage);
        $bills = $this->selectedBills($voyage, $billIds);

        return $this->resolveBillLifecycleStates($voyage, $bills);
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
            $bills = $this->selectedBills($voyage, $billIds);
            $this->assertOperationSequence($voyage, $methodType, $bills);

            $transaction = $this->createDeconsolidatedTransaction(
                $voyage,
                $transactionId,
                $methodType,
                $bills
            );
            $this->currentTransactionId = $transaction->id;

            $resolvedBillIds = $bills->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();

            $xml = $this->generateXml(
                $voyage,
                $methodType,
                $transactionId,
                $resolvedBillIds
            );

            $transaction->update([
                'request_xml' => $xml,
                'status' => 'sending',
                'sent_at' => now(),
            ]);

            $result = $this->sendSoapRequest($transaction, $xml, $soapMethod, $methodType);

            if ($result['success']) {
                $this->persistSuccess($transaction, $voyage, $methodType, $bills, $result);

                return [
                    'success' => true,
                    'transaction_id' => $transaction->id,
                    'client_transaction_id' => $transactionId,
                    'identifier' => $result['identifier'],
                    'warnings' => $result['details'],
                    'bill_ids' => $resolvedBillIds,
                    'message' => "{$soapMethod} aceptado por AFIP.",
                ];
            }

            $this->persistError($transaction, $voyage, $methodType, $result);

            return [
                'success' => false,
                'transaction_id' => $transaction->id,
                'client_transaction_id' => $transactionId,
                'bill_ids' => $resolvedBillIds,
                'error' => $result['error_message'],
                'error_message' => $result['error_message'],
                'error_details' => $result['details'],
            ];
        } catch (Exception $exception) {
            if ($transaction) {
                $transaction->update([
                    'status' => 'error',
                    'response_at' => now(),
                    'error_message' => $exception->getMessage(),
                    'error_details' => [[
                        'source' => 'application',
                        'description' => $exception->getMessage(),
                    ]],
                ]);

                $this->updateStatus($voyage, 'error', [
                    'last_transaction_id' => $transaction->transaction_id,
                    'last_error_message' => $exception->getMessage(),
                    'can_send' => true,
                ]);
            }

            $this->logOperation('error', "Error en {$soapMethod}", [
                'voyage_id' => $voyage->id,
                'method' => $methodType,
                'error' => $exception->getMessage(),
            ]);

            return [
                'success' => false,
                'transaction_id' => $transaction?->id,
                'client_transaction_id' => $transactionId,
                'error' => $exception->getMessage(),
                'error_message' => $exception->getMessage(),
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

    private function assertOperationSequence(
        Voyage $voyage,
        string $methodType,
        Collection $bills
    ): void {
        $states = $this->resolveBillLifecycleStates($voyage, $bills);

        $invalid = [];
        foreach ($bills as $bill) {
            $state = $states[(int) $bill->id] ?? null;
            $isActive = in_array($state, ['registrar', 'rectificar'], true);

            if ($methodType === 'registrar' && $isActive) {
                $invalid[] = $bill->bill_number . ' ya está registrado';
            } elseif (in_array($methodType, ['rectificar', 'eliminar'], true) && !$isActive) {
                $invalid[] = $bill->bill_number . ' no tiene un registro vigente';
            }
        }

        if ($invalid !== []) {
            throw new Exception(
                'Secuencia inválida para ' . $methodType . ': ' . implode('; ', $invalid) . '.'
            );
        }
    }

    private function resolveBillLifecycleStates(
        Voyage $voyage,
        Collection $bills
    ): array {
        $states = $bills
            ->mapWithKeys(fn (BillOfLading $bill) => [(int) $bill->id => null])
            ->all();

        if ($bills->isEmpty()) {
            return $states;
        }

        $targetIds = $bills->pluck('id')->map(fn ($id) => (int) $id)->all();

        $transactions = WebserviceTransaction::query()
            ->where('company_id', $this->company->id)
            ->where('voyage_id', $voyage->id)
            ->where('webservice_type', 'desconsolidado')
            ->where('country', 'AR')
            ->where('status', 'success')
            ->orderBy('id')
            ->get(['id', 'additional_metadata']);

        foreach ($transactions as $transaction) {
            $metadata = $transaction->additional_metadata ?? [];
            $method = $metadata['method'] ?? null;
            if (!in_array($method, ['registrar', 'rectificar', 'eliminar'], true)) {
                continue;
            }

            $transactionBillIds = collect($metadata['bill_ids'] ?? [])
                ->map(fn ($id) => (int) $id)
                ->filter(fn ($id) => in_array($id, $targetIds, true))
                ->unique();

            foreach ($transactionBillIds as $billId) {
                $states[$billId] = $method;
            }
        }

        return $states;
    }

    private function selectedBills(Voyage $voyage, array $billIds): Collection
    {
        $ids = $this->normalizeBillIds($billIds);
        $query = $voyage->billsOfLading()->whereNotNull('master_bill_number');

        if ($ids !== []) {
            $query->whereIn('bills_of_lading.id', $ids);
        }

        $bills = $query->orderBy('bills_of_lading.id')->get();
        if ($bills->isEmpty()) {
            throw new Exception('No hay títulos desconsolidados para la operación solicitada.');
        }
        if ($ids !== [] && $bills->count() !== count($ids)) {
            throw new Exception(
                'Uno o más conocimientos seleccionados no pertenecen al viaje o no son desconsolidados.'
            );
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
                'bill_ids' => $bills->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
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

        try {
            $responseXml = (string) $client->__doRequest(
                $xml,
                $this->config['webservice_url'],
                $this->config["soap_action_{$methodType}"],
                SOAP_1_1
            );

            $transaction->update([
                'response_xml' => $responseXml,
                'response_time_ms' => (int) round((microtime(true) - $start) * 1000),
                'response_at' => now(),
            ]);

            return $this->parseAfipResponse($responseXml, $soapMethod);
        } catch (\SoapFault $exception) {
            $lastResponse = $client->__getLastResponse();
            $transaction->update([
                'response_xml' => $lastResponse !== '' ? $lastResponse : null,
                'response_time_ms' => (int) round((microtime(true) - $start) * 1000),
                'response_at' => now(),
            ]);

            return [
                'success' => false,
                'identifier' => null,
                'details' => [[
                    'source' => 'soap_fault',
                    'code' => (string) $exception->faultcode,
                    'description' => $exception->getMessage(),
                    'additional' => null,
                ]],
                'error_message' => 'SOAP Fault: ' . $exception->getMessage(),
            ];
        }
    }

    private function createDeconsolidatedSoapClient(): \SoapClient
    {
        return new \SoapClient($this->config['wsdl_url'], [
            'trace' => true,
            'exceptions' => true,
            'soap_version' => SOAP_1_1,
            'encoding' => 'UTF-8',
            'cache_wsdl' => WSDL_CACHE_NONE,
            'connection_timeout' => $this->config['timeout_seconds'],
        ]);
    }

    private function parseAfipResponse(string $responseXml, string $soapMethod): array
    {
        if (trim($responseXml) === '') {
            return $this->errorResult('AFIP devolvió una respuesta vacía.');
        }

        $dom = new \DOMDocument();
        if (!@$dom->loadXML($responseXml)) {
            return $this->errorResult('La respuesta de AFIP no es XML válido.');
        }

        $fault = $this->firstElementByLocalName($dom, 'Fault');
        if ($fault) {
            $description = $this->elementText($fault, 'faultstring') ?: 'SOAP Fault';

            return $this->errorResult($description, [[
                'source' => 'soap_fault',
                'code' => null,
                'description' => $description,
                'additional' => null,
            ]]);
        }

        $resultName = $soapMethod . 'Result';
        $result = $this->firstElementByLocalName($dom, $resultName);
        if (!$result) {
            return $this->errorResult("AFIP no devolvió {$resultName}.");
        }

        $identifier = $this->elementText($result, 'IdentificadorViaje');
        $details = [];

        $detailNodes = $result->getElementsByTagNameNS('*', 'DetalleError');
        foreach ($detailNodes as $node) {
            if (!$node instanceof \DOMElement) {
                continue;
            }

            $details[] = [
                'source' => 'afip',
                'code' => $this->elementText($node, 'Codigo'),
                'description' => $this->elementText($node, 'Descripcion'),
                'additional' => $this->elementText($node, 'DescripcionAdicional'),
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

        $message = collect($details)
            ->map(fn (array $detail) => implode(' - ', array_filter([
                $detail['code'] ? "AFIP {$detail['code']}" : null,
                $detail['description'] ?: null,
                $detail['additional'] ?: null,
            ])))
            ->filter()
            ->implode(' | ');

        return $this->errorResult(
            $message ?: 'AFIP no devolvió IdentificadorViaje ni detalle de error.',
            $details
        );
    }

    private function firstElementByLocalName(
        \DOMDocument|\DOMElement $context,
        string $localName
    ): ?\DOMElement {
        $nodes = $context->getElementsByTagNameNS('*', $localName);
        $node = $nodes->item(0);

        return $node instanceof \DOMElement ? $node : null;
    }

    private function elementText(\DOMElement $context, string $localName): string
    {
        $element = $this->firstElementByLocalName($context, $localName);
        return $element ? trim((string) $element->textContent) : '';
    }

    private function errorResult(string $message, array $details = []): array
    {
        return [
            'success' => false,
            'identifier' => null,
            'details' => $details,
            'error_message' => $message,
        ];
    }

    private function persistSuccess(
        WebserviceTransaction $transaction,
        Voyage $voyage,
        string $methodType,
        Collection $bills,
        array $result
    ): void {
        $billIds = $bills->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
        $billNumbers = $bills->pluck('bill_number')->values()->all();

        $transaction->update([
            'status' => 'success',
            'external_reference' => $result['identifier'],
            'confirmation_number' => $result['identifier'],
            'success_data' => [
                'method' => $methodType,
                'identifier_viaje' => $result['identifier'],
                'bill_ids' => $billIds,
                'bill_numbers' => $billNumbers,
                'warnings' => $result['details'],
            ],
            'error_code' => null,
            'error_message' => null,
            'error_details' => null,
            'response_at' => now(),
        ]);

        WebserviceResponse::create([
            'transaction_id' => $transaction->id,
            'response_type' => 'success',
            'requires_action' => false,
            'processing_status' => 'completed',
            'confirmation_number' => $result['identifier'],
            'validation_warnings' => $result['details'],
            'customs_status' => 'approved',
            'customs_processed_at' => now(),
            'processed_at' => now(),
            'customs_metadata' => [
                'method' => $methodType,
                'identifier_viaje' => $result['identifier'],
                'bill_ids' => $billIds,
                'bill_numbers' => $billNumbers,
            ],
        ]);

        $this->updateStatus($voyage, 'approved', [
            'last_transaction_id' => $transaction->transaction_id,
            'confirmation_number' => $result['identifier'],
            'external_voyage_number' => $result['identifier'],
            'last_sent_at' => now(),
            'approved_at' => now(),
            'last_error_code' => null,
            'last_error_message' => null,
            'can_send' => true,
        ]);

        $this->logOperation('info', "Desconsolidado {$methodType} aceptado por AFIP", [
            'transaction_id' => $transaction->id,
            'voyage_id' => $voyage->id,
            'bill_ids' => $billIds,
            'identifier' => $result['identifier'],
        ]);
    }

    private function persistError(
        WebserviceTransaction $transaction,
        Voyage $voyage,
        string $methodType,
        array $result
    ): void {
        $firstCode = collect($result['details'])->pluck('code')->filter()->first();

        $transaction->update([
            'status' => 'error',
            'error_code' => $firstCode,
            'error_message' => $result['error_message'],
            'error_details' => $result['details'],
            'response_at' => now(),
        ]);

        WebserviceResponse::create([
            'transaction_id' => $transaction->id,
            'response_type' => 'business_error',
            'requires_action' => true,
            'processing_status' => 'requires_manual',
            'validation_errors' => $result['details'],
            'customs_status' => 'rejected',
            'customs_processed_at' => now(),
            'processed_at' => now(),
            'customs_metadata' => [
                'method' => $methodType,
                'error_message' => $result['error_message'],
            ],
        ]);

        $this->updateStatus($voyage, 'error', [
            'last_transaction_id' => $transaction->transaction_id,
            'last_error_code' => $firstCode,
            'last_error_message' => $result['error_message'],
            'last_sent_at' => now(),
            'can_send' => true,
        ]);
    }

    private function updateStatus(Voyage $voyage, string $status, array $data = []): void
    {
        $row = $this->getWebserviceStatus($voyage);
        $row->update(array_merge([
            'user_id' => $this->user->id,
            'status' => $status,
        ], $data));
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

    /** Idempotencia AFIP: máximo 20 caracteres. */
    protected function generateTransactionId(): string
    {
        return 'DEC' . now()->format('ymdHis') . Str::upper(Str::random(5));
    }
}
