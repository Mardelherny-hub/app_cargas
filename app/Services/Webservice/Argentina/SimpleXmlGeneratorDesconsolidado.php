<?php

namespace App\Services\Webservice\Argentina;

use App\Models\BillOfLading;
use App\Models\Client;
use App\Models\Company;
use App\Models\Container;
use App\Models\Voyage;
use App\Models\WsaaToken;
use App\Services\Webservice\CertificateManagerService;
use Exception;
use Illuminate\Support\Collection;

/**
 * XML SOAP para ATA Desconsolidador - wgesinformacionanticipada.
 *
 * Fuente contractual: Manual del Desarrollador AFIP, versión 4.11.
 * No completa obligatorios ausentes con valores por defecto.
 */
class SimpleXmlGeneratorDesconsolidado
{
    private const NAMESPACE = 'Ar.Gob.Afip.Dga.Org.wgesinformacionanticipada';
    private const SERVICE_NAME = 'wgesinformacionanticipada';

    private Voyage $voyage;
    private Company $company;
    private string $environment;

    public function __construct(Voyage $voyage, array $config = [])
    {
        $this->voyage = $voyage;
        $this->company = $voyage->company;
        $this->environment = $config['environment']
            ?? $this->company->ws_environment
            ?? 'testing';

        if (!in_array($this->environment, ['testing', 'production'], true)) {
            throw new Exception("Ambiente AFIP no válido: {$this->environment}");
        }
    }

    public function generateRegistrar(string $transactionId, array $billIds = []): string
    {
        return $this->generateTitlesOperation(
            'RegistrarTitulosDesconsolidador',
            'argRegistrarTitulosDesconsolidador',
            $transactionId,
            $billIds
        );
    }

    public function generateRectificar(string $transactionId, array $billIds = []): string
    {
        return $this->generateTitlesOperation(
            'RectificarTitulosDesconsolidador',
            'argRectificarTitulosDesconsolidador',
            $transactionId,
            $billIds
        );
    }

    public function generateEliminar(string $transactionId, array $billIds = []): string
    {
        $this->validateTransactionId($transactionId);
        $this->validateVoyageIdentifier();

        $bills = $this->getDesconsolidatedBills($billIds);
        foreach ($bills as $bill) {
            $this->requiredString($bill->loadingPort?->code, "BL {$bill->id}: CodigoPuertoEmbarque", 5);
            $this->requiredString($bill->bill_number, "BL {$bill->id}: NumeroConocimiento", 18);
        }

        // Autenticación recién después de terminar las validaciones locales.
        $auth = $this->getWsaaTokens();

        $w = $this->createWriter();
        $this->startEnvelope($w, 'EliminarTitulosDesconsolidador');
        $this->writeAuthentication($w, $auth);

        $w->startElement('argEliminarTitulosDesconsolidador');
            $w->writeElement('IdTransaccion', $transactionId);
            $w->startElement('InformacionTitulosDesconsolidadorDoc');
                $w->writeElement('IdentificadorViaje', $this->voyage->argentina_voyage_id);
                $w->startElement('PuertosConocimientos');
                    foreach ($bills as $bill) {
                        $w->startElement('PuertoConocimiento');
                            $w->writeElement('CodigoPuertoEmbarque', $bill->loadingPort->code);
                            $w->writeElement('NumeroConocimiento', $bill->bill_number);
                        $w->endElement();
                    }
                $w->endElement();
            $w->endElement();
        $w->endElement();

        $this->endEnvelope($w);
        return $w->outputMemory();
    }

    private function generateTitlesOperation(
        string $method,
        string $argumentName,
        string $transactionId,
        array $billIds
    ): string {
        $this->validateTransactionId($transactionId);
        $this->validateVoyageIdentifier();

        $bills = $this->getDesconsolidatedBills($billIds);
        foreach ($bills as $bill) {
            $this->validateBill($bill);
        }

        // No solicitar/cachar un TA de WSAA si el viaje no supera validación local.
        $auth = $this->getWsaaTokens();

        $w = $this->createWriter();
        $this->startEnvelope($w, $method);
        $this->writeAuthentication($w, $auth);

        $w->startElement($argumentName);
            $w->writeElement('IdTransaccion', $transactionId);
            $w->startElement('InformacionTitulosDesconsolidadorDoc');
                $w->writeElement('IdentificadorViaje', $this->voyage->argentina_voyage_id);
                $w->startElement('TitulosDesconsolidador');
                    foreach ($bills as $bill) {
                        $this->writeTitle($w, $bill);
                    }
                $w->endElement();
            $w->endElement();
        $w->endElement();

        $this->endEnvelope($w);
        return $w->outputMemory();
    }

    private function getDesconsolidatedBills(array $billIds = []): Collection
    {
        $query = $this->voyage->billsOfLading()
            ->whereNotNull('master_bill_number')
            ->with([
                'loadingPort',
                'dischargePort',
                'transshipmentPort',
                'consignee.documentType',
                'notifyParty',
                'shipmentItems.packagingType',
                'shipmentItems.cargoType',
                'shipmentItems.containers.containerType',
            ]);

        $ids = collect($billIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($ids !== []) {
            $query->whereIn('bills_of_lading.id', $ids);
        }

        $bills = $query->orderBy('bills_of_lading.id')->get();
        if ($bills->isEmpty()) {
            throw new Exception('No hay títulos desconsolidados para procesar.');
        }

        if ($ids !== [] && $bills->count() !== count($ids)) {
            throw new Exception('Uno o más conocimientos seleccionados no pertenecen al viaje o no son desconsolidados.');
        }

        return $bills;
    }

    private function validateTransactionId(string $transactionId): void
    {
        if ($transactionId === '' || mb_strlen($transactionId) > 20) {
            throw new Exception('IdTransaccion es obligatorio y no puede superar 20 caracteres.');
        }
    }

    private function validateVoyageIdentifier(): void
    {
        $identifier = trim((string) ($this->voyage->argentina_voyage_id ?? ''));
        if ($identifier === '') {
            throw new Exception('El viaje no tiene IdentificadorViaje AFIP. Primero debe existir un RegistrarViaje exitoso.');
        }
        if (mb_strlen($identifier) > 16) {
            throw new Exception('IdentificadorViaje AFIP supera los 16 caracteres permitidos.');
        }
    }

    private function validateBill(BillOfLading $bill): void
    {
        $prefix = "BL {$bill->id}";

        $this->requiredDate($bill->loading_date, "{$prefix}: FechaEmbarque");
        $this->requiredString($bill->loadingPort?->code, "{$prefix}: CodigoPuertoEmbarque", 5);
        $this->optionalString($bill->origin_location, "{$prefix}: LugarOrigen", 50);
        $this->optionalString($bill->origin_country_code, "{$prefix}: CodigoPaisLugarOrigen", 3);
        $this->requiredString($bill->bill_number, "{$prefix}: NumeroConocimiento", 18);
        $this->requiredString($bill->dischargePort?->code, "{$prefix}: CodigoPuertoDescarga", 5);
        $this->requiredString($bill->destination_country_code, "{$prefix}: CodigoPaisDestino", 3);
        $this->requiredString($bill->cargo_marks, "{$prefix}: MarcaBultos", 80);
        $this->requiredFlag($bill->is_consolidated, "{$prefix}: IndicadorConsolidado");
        $this->requiredFlag($bill->is_transit_transshipment, "{$prefix}: IndicadorTransitoTrasbordo");
        $this->requiredString($bill->discharge_customs_code, "{$prefix}: CodigoAduanaDescarga", 3);
        $this->requiredString($bill->operational_discharge_code, "{$prefix}: CodigoLugarOperativoDescarga", 5);

        if ($bill->shipmentItems->isEmpty()) {
            throw new Exception("{$prefix}: debe tener al menos una línea de mercadería.");
        }

        // Estos atributos son a nivel título en el contrato. En la app existen en
        // shipment_items: deben coincidir entre todas las líneas, nunca se inventan.
        $this->singleItemValue($bill, 'tariff_position', 'PosicionArancelaria', 16, true);
        $this->singleItemFlag($bill, 'is_secure_logistics_operator', 'IndicadorOperadorLogisticoSeguro');
        $this->singleItemFlag($bill, 'is_monitored_transit', 'IndicadorTransitoMonitoreado');
        $this->singleItemFlag($bill, 'is_renar', 'IndicadorRenar');
        $this->singleItemValue($bill, 'foreign_forwarder_name', 'RazonSocialFowarderExterior', 70, true);

        $lineNumbers = [];
        foreach ($bill->shipmentItems as $item) {
            if ($item->line_number === null || $item->line_number === '') {
                throw new Exception("{$prefix}: ShipmentItem {$item->id} no tiene NumeroLinea.");
            }

            $line = (int) $item->line_number;
            if ($line < 0 || $line > 999) {
                throw new Exception("{$prefix}: ShipmentItem {$item->id} NumeroLinea fuera de rango 0-999.");
            }
            if (in_array($line, $lineNumbers, true)) {
                throw new Exception("{$prefix}: NumeroLinea {$line} está repetido.");
            }
            $lineNumbers[] = $line;

            $packagingCode = $item->packaging_code ?: $item->packagingType?->argentina_ws_code;
            $this->requiredString($packagingCode, "{$prefix}: ShipmentItem {$item->id} CodigoEmbalaje", 2);

            // CondicionContenedor de LineaMercaderia es opcional en el contrato.
            if ($item->container_condition !== null && $item->container_condition !== '') {
                $this->containerCondition($item->container_condition, "{$prefix}: ShipmentItem {$item->id} CondicionContenedor");
            }

            if ($item->package_quantity === null) {
                throw new Exception("{$prefix}: ShipmentItem {$item->id} no tiene CantidadManifestada.");
            }
            if ($item->gross_weight_kg === null) {
                throw new Exception("{$prefix}: ShipmentItem {$item->id} no tiene PesoVolumenManifestado.");
            }

            $this->requiredString($item->item_description, "{$prefix}: ShipmentItem {$item->id} DescripcionMercaderia", 80);
            $this->requiredString($item->cargo_marks, "{$prefix}: ShipmentItem {$item->id} NumeroBultos", 100);
        }

        foreach ($this->billContainers($bill) as $container) {
            $this->validateContainer($container, $bill);
        }
    }

    private function validateContainer(Container $container, BillOfLading $bill): void
    {
        $prefix = "BL {$bill->id}: Contenedor {$container->id}";

        $this->requiredString($container->container_number, "{$prefix}: IdentificadorContenedor", 20);
        $this->containerCondition($container->container_condition, "{$prefix}: CondicionContenedor");

        $characteristics = $container->argentina_container_code ?: $container->containerType?->argentina_ws_code;
        $this->requiredString($characteristics, "{$prefix}: CaracteristicasContenedor", 4);

        if ($container->tare_weight_kg === null) {
            throw new Exception("{$prefix}: falta Tara.");
        }
        if ($container->current_gross_weight_kg === null) {
            throw new Exception("{$prefix}: falta PesoBruto.");
        }

        // El historial 4.3 del manual vuelve obligatorio este dato.
        $this->containerOperatorCuit($container, $prefix);

        // En la clase Contenedor, aduana y lugar operativo son obligatorios.
        $this->requiredString($bill->discharge_customs_code, "{$prefix}: CodigoAduana", 3);
        $this->requiredString($bill->operational_discharge_code, "{$prefix}: CodigoLugarOperativoDescarga", 5);

        // FechaVencimientoContenedor y Acep son opcionales; no bloquear por ausencia.
    }

    private function writeTitle(\XMLWriter $w, BillOfLading $bill): void
    {
        $w->startElement('TituloDesconsolidador');
            $w->writeElement('FechaEmbarque', $this->formatDate($bill->loading_date));
            $w->writeElement('CodigoPuertoEmbarque', $bill->loadingPort->code);
            $this->writeOptionalDate($w, 'FechaCargaLugarOrigen', $bill->origin_loading_date);
            $this->writeOptionalString($w, 'LugarOrigen', $bill->origin_location, 50);
            $this->writeOptionalString($w, 'CodigoPaisLugarOrigen', $bill->origin_country_code, 3);
            $w->writeElement('NumeroConocimiento', $bill->bill_number);
            $this->writeOptionalString($w, 'CodigoPuertoTrasbordo', $bill->transshipmentPort?->code, 5);
            $w->writeElement('CodigoPuertoDescarga', $bill->dischargePort->code);
            $this->writeOptionalDate($w, 'FechaDescarga', $bill->discharge_date);
            $w->writeElement('CodigoPaisDestino', $bill->destination_country_code);
            $w->writeElement('MarcaBultos', $bill->cargo_marks);
            $this->writeOptionalString($w, 'Consignatario', $bill->consignee?->legal_name, 80);
            $this->writeOptionalString($w, 'NotificarA', $bill->notifyParty?->legal_name ?: $bill->notify_party_text, 35);
            $w->writeElement('IndicadorConsolidado', $this->normalizeFlag($bill->is_consolidated));
            $w->writeElement('IndicadorTransitoTrasbordo', $this->normalizeFlag($bill->is_transit_transshipment));

            $this->writeOptionalString($w, 'TipoDocumentoDestinatarioMercaderia', $this->consigneeDocumentType($bill), 4);
            $this->writeOptionalString($w, 'IdentificadorDestinatarioMercaderia', $this->consigneeTaxId($bill), 11);
            // No existe un campo inequívoco en la app para CodigoPaisEmisionPasaporteDestinatario.

            $w->writeElement('PosicionArancelaria', $this->singleItemValue($bill, 'tariff_position', 'PosicionArancelaria', 16, true));
            $w->writeElement('IndicadorOperadorLogisticoSeguro', $this->singleItemFlag($bill, 'is_secure_logistics_operator', 'IndicadorOperadorLogisticoSeguro'));
            $w->writeElement('IndicadorTransitoMonitoreado', $this->singleItemFlag($bill, 'is_monitored_transit', 'IndicadorTransitoMonitoreado'));
            $w->writeElement('IndicadorRenar', $this->singleItemFlag($bill, 'is_renar', 'IndicadorRenar'));
            $w->writeElement('RazonSocialFowarderExterior', $this->singleItemValue($bill, 'foreign_forwarder_name', 'RazonSocialFowarderExterior', 70, true));
            $this->writeOptionalString($w, 'IndicadorTributarioForwarderExterior', $this->singleItemValue($bill, 'foreign_forwarder_tax_id', 'IndicadorTributarioForwarderExterior', 35, false), 35);
            $this->writeOptionalString($w, 'CodigoPaisEmisorIdentificadorForwarderExterior', $this->singleItemValue($bill, 'foreign_forwarder_country', 'CodigoPaisEmisorIdentificadorForwarderExterior', 3, false), 3);
            // Comentario de título es opcional y BillOfLading no posee hoy un campo con esa semántica exacta.

            // Orden según los XML de ejemplo del método.
            $w->writeElement('CodigoLugarOperativoDescarga', $bill->operational_discharge_code);
            $w->writeElement('CodigoAduanaDescarga', $bill->discharge_customs_code);

            $this->writeMerchandise($w, $bill);
            $this->writeContainers($w, $bill);
            $this->writeOptionalString($w, 'IdentificadorTituloMadre', $bill->master_bill_number, 23);
        $w->endElement();
    }

    private function writeMerchandise(\XMLWriter $w, BillOfLading $bill): void
    {
        $w->startElement('Mercaderias');
        foreach ($bill->shipmentItems->sortBy('line_number') as $item) {
            $packagingCode = $item->packaging_code ?: $item->packagingType?->argentina_ws_code;

            $w->startElement('LineaMercaderia');
                $w->writeElement('NumeroLinea', (string) ((int) $item->line_number));
                $w->writeElement('CodigoEmbalaje', $packagingCode);
                if ($item->container_condition !== null && $item->container_condition !== '') {
                    $w->writeElement('CondicionContenedor', strtoupper((string) $item->container_condition));
                }
                $w->writeElement('CantidadManifestada', (string) $item->package_quantity);
                $w->writeElement('PesoVolumenManifestado', $this->formatNumber($item->gross_weight_kg));
                $w->writeElement('DescripcionMercaderia', $item->item_description);
                $w->writeElement('NumeroBultos', $item->cargo_marks);
                $this->writeOptionalString($w, 'TipoCarga', $item->cargoType?->webservice_code, 3);
                $this->writeOptionalString($w, 'Comentario', $item->comments, 60);
            $w->endElement();
        }
        $w->endElement();
    }

    private function writeContainers(\XMLWriter $w, BillOfLading $bill): void
    {
        $containers = $this->billContainers($bill);
        if ($containers->isEmpty()) {
            return;
        }

        $w->startElement('Contenedores');
        foreach ($containers as $container) {
            $characteristics = $container->argentina_container_code ?: $container->containerType?->argentina_ws_code;

            $w->startElement('Contenedor');
                $w->writeElement('CuitAtaOperadorContenedor', $this->containerOperatorCuit($container, "Contenedor {$container->id}"));
                $w->writeElement('CaracteristicasContenedor', $characteristics);
                $w->writeElement('IdentificadorContenedor', $container->container_number);
                $w->writeElement('CondicionContenedor', strtoupper((string) $container->container_condition));
                $w->writeElement('Tara', $this->formatNumber($container->tare_weight_kg));
                $w->writeElement('PesoBruto', $this->formatNumber($container->current_gross_weight_kg));
                $this->writeOptionalString($w, 'NumeroPrecintoOrigen', $container->shipper_seal, 35);
                $this->writeOptionalDate($w, 'FechaVencimientoContenedor', $container->csc_expiry_date);

                // No se deriva CodigoLugarOrigen desde origin_location: son conceptos distintos.
                $w->writeElement('CodigoAduana', $bill->discharge_customs_code);
                $w->writeElement('CodigoLugarOperativoDescarga', $bill->operational_discharge_code);
            $w->endElement();
        }
        $w->endElement();
    }

    private function billContainers(BillOfLading $bill): Collection
    {
        return $bill->shipmentItems
            ->flatMap(fn ($item) => $item->containers)
            ->unique('id')
            ->values();
    }

    private function containerOperatorCuit(Container $container, string $prefix): string
    {
        if (!$container->operator_client_id) {
            throw new Exception("{$prefix}: falta operator_client_id.");
        }

        $taxId = Client::query()->whereKey($container->operator_client_id)->value('tax_id');
        $taxId = preg_replace('/\D+/', '', (string) $taxId);
        if (strlen($taxId) !== 11) {
            throw new Exception("{$prefix}: el CUIT del operador de contenedor debe tener 11 dígitos.");
        }

        return $taxId;
    }

    private function singleItemFlag(BillOfLading $bill, string $field, string $label): string
    {
        $value = $this->singleItemValue($bill, $field, $label, 1, true);
        if (!in_array(strtoupper((string) $value), ['S', 'N'], true)) {
            throw new Exception("BL {$bill->id}: {$label} debe ser S o N.");
        }
        return strtoupper((string) $value);
    }

    private function singleItemValue(
        BillOfLading $bill,
        string $field,
        string $label,
        int $maxLength,
        bool $required
    ): ?string {
        $values = $bill->shipmentItems
            ->pluck($field)
            ->filter(fn ($v) => $v !== null && trim((string) $v) !== '')
            ->map(fn ($v) => trim((string) $v))
            ->unique()
            ->values();

        if ($values->count() > 1) {
            throw new Exception("BL {$bill->id}: {$label} tiene valores distintos entre líneas; AFIP admite uno por título.");
        }

        $value = $values->first();
        if ($required && ($value === null || $value === '')) {
            throw new Exception("BL {$bill->id}: falta {$label}.");
        }
        if ($value !== null && mb_strlen($value) > $maxLength) {
            throw new Exception("BL {$bill->id}: {$label} supera {$maxLength} caracteres.");
        }

        return $value;
    }

    private function consigneeDocumentType(BillOfLading $bill): ?string
    {
        $explicit = $this->singleItemValue($bill, 'consignee_document_type', 'TipoDocumentoDestinatarioMercaderia', 4, false);
        if ($explicit !== null) {
            return $explicit;
        }

        return $this->optionalString($bill->consignee?->documentType?->code, 'TipoDocumentoDestinatarioMercaderia', 4);
    }

    private function consigneeTaxId(BillOfLading $bill): ?string
    {
        $explicit = $this->singleItemValue($bill, 'consignee_tax_id', 'IdentificadorDestinatarioMercaderia', 11, false);
        $value = $explicit ?: $bill->consignee?->tax_id;
        if (!$value) {
            return null;
        }

        $clean = preg_replace('/\D+/', '', (string) $value);
        if ($clean === '' || strlen($clean) > 11) {
            throw new Exception("BL {$bill->id}: IdentificadorDestinatarioMercaderia inválido.");
        }
        return $clean;
    }

    private function createWriter(): \XMLWriter
    {
        $w = new \XMLWriter();
        $w->openMemory();
        $w->startDocument('1.0', 'UTF-8');
        return $w;
    }

    private function startEnvelope(\XMLWriter $w, string $method): void
    {
        $w->startElementNs('soapenv', 'Envelope', 'http://schemas.xmlsoap.org/soap/envelope/');
        $w->startElementNs('soapenv', 'Header', null);
        $w->endElement();
        $w->startElementNs('soapenv', 'Body', null);

        // Namespace por defecto en el método: todos sus descendientes quedan
        // dentro del contrato AFIP, como en los ejemplos SOAP 1.1 del manual.
        $w->startElement($method);
        $w->writeAttribute('xmlns', self::NAMESPACE);
    }

    private function endEnvelope(\XMLWriter $w): void
    {
        $w->endElement();
        $w->endElement();
        $w->endElement();
        $w->endDocument();
    }

    private function writeAuthentication(\XMLWriter $w, array $auth): void
    {
        $cuit = preg_replace('/\D+/', '', (string) $this->company->tax_id);
        if (strlen($cuit) !== 11) {
            throw new Exception('CUIT de la empresa conectada inválido; debe tener 11 dígitos.');
        }

        $w->startElement('argWSAutenticacionEmpresa');
            $w->writeElement('Token', $auth['token']);
            $w->writeElement('Sign', $auth['sign']);
            $w->writeElement('CuitEmpresaConectada', $cuit);
            $w->writeElement('TipoAgente', 'TRSP');
            $w->writeElement('Rol', 'TRSP');
        $w->endElement();
    }

    private function getWsaaTokens(): array
    {
        $cached = WsaaToken::getValidToken($this->company->id, self::SERVICE_NAME, $this->environment);
        if ($cached) {
            $cached->markAsUsed();
            return ['token' => $cached->token, 'sign' => $cached->sign];
        }

        $certificate = (new CertificateManagerService($this->company))->readCertificate();
        if (!$certificate || empty($certificate['cert']) || empty($certificate['pkey'])) {
            throw new Exception('No se pudo leer certificado y clave privada de la empresa para WSAA.');
        }

        $signedTicket = $this->signLoginTicket($this->generateLoginTicket(), $certificate);
        $tokens = $this->callWsaa($signedTicket);

        WsaaToken::createToken([
            'company_id' => $this->company->id,
            'service_name' => self::SERVICE_NAME,
            'environment' => $this->environment,
            'token' => $tokens['token'],
            'sign' => $tokens['sign'],
            'issued_at' => $tokens['generation_time'] ?? now(),
            'expires_at' => $tokens['expiration_time'] ?? now()->addHours(12),
            'generation_time' => ($tokens['generation_time'] ?? now())->format('c'),
            'unique_id' => (string) ($tokens['unique_id'] ?? uniqid('', true)),
            'certificate_used' => $this->company->getCertificatePath(),
            'usage_count' => 0,
            'status' => 'active',
            'created_by_process' => self::class,
            'creation_context' => ['method' => 'getWsaaTokens', 'service' => self::SERVICE_NAME],
        ]);

        return ['token' => $tokens['token'], 'sign' => $tokens['sign']];
    }

    private function generateLoginTicket(): string
    {
        $uniqueId = (int) min(time(), 2147483647);
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<loginTicketRequest version="1.0"><header>'
            . '<uniqueId>' . $uniqueId . '</uniqueId>'
            . '<generationTime>' . $now->sub(new \DateInterval('PT5M'))->format('Y-m-d\TH:i:s\Z') . '</generationTime>'
            . '<expirationTime>' . $now->add(new \DateInterval('PT12H'))->format('Y-m-d\TH:i:s\Z') . '</expirationTime>'
            . '</header><service>' . self::SERVICE_NAME . '</service></loginTicketRequest>';
    }

    private function signLoginTicket(string $ticket, array $certificate): string
    {
        $ticketFile = tempnam(sys_get_temp_dir(), 'wsaa_ticket_');
        $certFile = tempnam(sys_get_temp_dir(), 'wsaa_cert_');
        $keyFile = tempnam(sys_get_temp_dir(), 'wsaa_key_');
        $signedFile = tempnam(sys_get_temp_dir(), 'wsaa_signed_');

        if (!$ticketFile || !$certFile || !$keyFile || !$signedFile) {
            throw new Exception('No se pudieron crear temporales para WSAA.');
        }

        try {
            file_put_contents($ticketFile, $ticket);
            $certContent = $certificate['cert'];
            foreach (($certificate['extracerts'] ?? []) as $extra) {
                $certContent .= "\n" . $extra;
            }
            file_put_contents($certFile, $certContent);
            file_put_contents($keyFile, $certificate['pkey']);

            $command = sprintf(
                'openssl smime -sign -in %s -out %s -signer %s -inkey %s -outform DER -nodetach 2>&1',
                escapeshellarg($ticketFile),
                escapeshellarg($signedFile),
                escapeshellarg($certFile),
                escapeshellarg($keyFile)
            );
            exec($command, $output, $returnCode);

            if ($returnCode !== 0 || !is_file($signedFile) || filesize($signedFile) === 0) {
                throw new Exception('Error firmando LoginTicket WSAA: ' . implode(' | ', $output));
            }

            return base64_encode((string) file_get_contents($signedFile));
        } finally {
            @unlink($ticketFile);
            @unlink($certFile);
            @unlink($keyFile);
            @unlink($signedFile);
        }
    }

    private function callWsaa(string $signedTicket): array
    {
        $wsdl = $this->environment === 'production'
            ? 'https://wsaa.afip.gov.ar/ws/services/LoginCms?wsdl'
            : 'https://wsaahomo.afip.gov.ar/ws/services/LoginCms?wsdl';

        $client = new \SoapClient($wsdl, [
            'trace' => true,
            'exceptions' => true,
            'cache_wsdl' => WSDL_CACHE_NONE,
        ]);

        $response = $client->loginCms(['in0' => $signedTicket]);
        if (!isset($response->loginCmsReturn)) {
            throw new Exception('WSAA no devolvió loginCmsReturn.');
        }

        $xml = simplexml_load_string($response->loginCmsReturn);
        if ($xml === false || empty($xml->credentials->token) || empty($xml->credentials->sign)) {
            throw new Exception('Respuesta WSAA inválida o sin Token/Sign.');
        }

        return [
            'token' => (string) $xml->credentials->token,
            'sign' => (string) $xml->credentials->sign,
            'generation_time' => isset($xml->header->generationTime) ? new \DateTimeImmutable((string) $xml->header->generationTime) : null,
            'expiration_time' => isset($xml->header->expirationTime) ? new \DateTimeImmutable((string) $xml->header->expirationTime) : null,
            'unique_id' => isset($xml->header->uniqueId) ? (string) $xml->header->uniqueId : null,
        ];
    }

    private function requiredString($value, string $label, int $maxLength): string
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            throw new Exception("Falta campo obligatorio {$label}.");
        }
        if (mb_strlen($value) > $maxLength) {
            throw new Exception("{$label} supera {$maxLength} caracteres.");
        }
        return $value;
    }

    private function optionalString($value, string $label, int $maxLength): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }
        $value = trim((string) $value);
        if (mb_strlen($value) > $maxLength) {
            throw new Exception("{$label} supera {$maxLength} caracteres.");
        }
        return $value;
    }

    private function requiredFlag($value, string $label): string
    {
        $value = $this->normalizeFlag($value);
        if (!in_array($value, ['S', 'N'], true)) {
            throw new Exception("{$label} debe ser S o N.");
        }
        return $value;
    }

    private function normalizeFlag($value): string
    {
        if (is_bool($value)) {
            return $value ? 'S' : 'N';
        }
        return strtoupper(trim((string) ($value ?? '')));
    }

    private function containerCondition($value, string $label): string
    {
        $value = strtoupper(trim((string) ($value ?? '')));
        if ($value === '' || mb_strlen($value) > 1) {
            throw new Exception("{$label} debe contener un código CONCTD_DESC de un carácter.");
        }
        return $value;
    }

    private function requiredDate($value, string $label): string
    {
        if (!$value) {
            throw new Exception("Falta campo obligatorio {$label}.");
        }
        return $this->formatDate($value);
    }

    private function formatDate($value): string
    {
        try {
            return $value instanceof \DateTimeInterface
                ? $value->format('Y-m-d\TH:i:s')
                : (new \DateTimeImmutable((string) $value))->format('Y-m-d\TH:i:s');
        } catch (\Throwable $e) {
            throw new Exception('Fecha inválida para XML AFIP: ' . (string) $value, 0, $e);
        }
    }

    private function formatNumber($value): string
    {
        if (!is_numeric($value)) {
            throw new Exception('Valor numérico inválido para XML AFIP: ' . (string) $value);
        }
        $formatted = rtrim(rtrim(number_format((float) $value, 4, '.', ''), '0'), '.');
        return $formatted === '' ? '0' : $formatted;
    }

    private function writeOptionalString(\XMLWriter $w, string $name, $value, int $maxLength): void
    {
        $value = $this->optionalString($value, $name, $maxLength);
        if ($value !== null) {
            $w->writeElement($name, $value);
        }
    }

    private function writeOptionalDate(\XMLWriter $w, string $name, $value): void
    {
        if ($value) {
            $w->writeElement($name, $this->formatDate($value));
        }
    }
}
