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
 * Genera los XML SOAP del webservice AFIP wgesinformacionanticipada
 * para ATA Desconsolidador.
 *
 * Contrato documental:
 * - RegistrarTitulosDesconsolidador
 * - RectificarTitulosDesconsolidador
 * - EliminarTitulosDesconsolidador
 *
 * No completa datos faltantes con valores simulados. Todo campo obligatorio
 * debe existir en los modelos del viaje o la generación falla explícitamente.
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
                        $portCode = $this->requiredString(
                            $bill->loadingPort?->code,
                            "BL {$bill->id}: CodigoPuertoEmbarque",
                            5
                        );
                        $billNumber = $this->requiredString(
                            $bill->bill_number,
                            "BL {$bill->id}: NumeroConocimiento",
                            18
                        );

                        $w->startElement('PuertoConocimiento');
                            $w->writeElement('CodigoPuertoEmbarque', $portCode);
                            $w->writeElement('NumeroConocimiento', $billNumber);
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
        $auth = $this->getWsaaTokens();

        foreach ($bills as $bill) {
            $this->validateBill($bill);
        }

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
                'loadingPort.country',
                'dischargePort.country',
                'transshipmentPort.country',
                'finalDestinationPort.country',
                'consignee.country',
                'consignee.documentType',
                'notifyParty.country',
                'shipmentItems.packagingType',
                'shipmentItems.cargoType',
                'shipmentItems.containers.containerType',
            ]);

        if ($billIds !== []) {
            $query->whereIn('bills_of_lading.id', array_values(array_unique(array_map('intval', $billIds))));
        }

        $bills = $query->orderBy('bills_of_lading.id')->get();

        if ($bills->isEmpty()) {
            throw new Exception('No hay títulos desconsolidados para procesar.');
        }

        if ($billIds !== [] && $bills->count() !== count(array_unique(array_map('intval', $billIds)))) {
            throw new Exception('Uno o más conocimientos seleccionados no pertenecen al viaje o no son títulos desconsolidados.');
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
        $identifier = (string) ($this->voyage->argentina_voyage_id ?? '');

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

        $this->requiredString($bill->master_bill_number, "{$prefix}: IdentificadorTituloMadre", 23);
        $this->requiredDate($bill->loading_date, "{$prefix}: FechaEmbarque");
        $this->requiredString($bill->loadingPort?->code, "{$prefix}: CodigoPuertoEmbarque", 5);
        $this->requiredString($bill->origin_location, "{$prefix}: LugarOrigen", 50);
        $this->requiredString($bill->origin_country_code, "{$prefix}: CodigoPaisLugarOrigen", 3);
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

        $this->resolveSingleTitleValue($bill, 'tariff_position', 'PosicionArancelaria', 16, true);
        $this->resolveSingleTitleFlag($bill, 'is_secure_logistics_operator', 'IndicadorOperadorLogisticoSeguro');
        $this->resolveSingleTitleFlag($bill, 'is_monitored_transit', 'IndicadorTransitoMonitoreado');
        $this->resolveSingleTitleFlag($bill, 'is_renar', 'IndicadorRenar');
        $this->resolveSingleTitleValue($bill, 'foreign_forwarder_name', 'RazonSocialFowarderExterior', 70, true);

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
            $this->requiredContainerCondition($item->container_condition, "{$prefix}: ShipmentItem {$item->id} CondicionContenedor");

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
        $this->requiredContainerCondition($container->container_condition, "{$prefix}: CondicionContenedor");

        if (!$container->operator_client_id) {
            throw new Exception("{$prefix}: falta operador de contenedor (operator_client_id).");
        }

        $operatorTaxId = Client::query()->whereKey($container->operator_client_id)->value('tax_id');
        $operatorTaxId = preg_replace('/\D+/', '', (string) $operatorTaxId);
        if (strlen($operatorTaxId) !== 11) {
            throw new Exception("{$prefix}: el CUIT del operador de contenedor debe tener 11 dígitos.");
        }

        $characteristics = $container->argentina_container_code ?: $container->containerType?->argentina_ws_code;
        $this->requiredString($characteristics, "{$prefix}: CaracteristicasContenedor", 4);

        if (!$container->csc_expiry_date) {
            throw new Exception("{$prefix}: falta FechaVencimientoContenedor; el modelo no dispone de un campo ACEP alternativo.");
        }
    }

    private function writeTitle(\XMLWriter $w, BillOfLading $bill): void
    {
        $w->startElement('TituloDesconsolidador');
            $w->writeElement('FechaEmbarque', $this->formatDate($bill->loading_date));
            $w->writeElement('CodigoPuertoEmbarque', $bill->loadingPort->code);

            $this->writeOptionalDate($w, 'FechaCargaLugarOrigen', $bill->origin_loading_date);
            $w->writeElement('LugarOrigen', $bill->origin_location);
            $w->writeElement('CodigoPaisLugarOrigen', $bill->origin_country_code);
            $w->writeElement('NumeroConocimiento', $bill->bill_number);

            $this->writeOptionalString($w, 'CodigoPuertoTrasbordo', $bill->transshipmentPort?->code, 5);
            $w->writeElement('CodigoPuertoDescarga', $bill->dischargePort->code);
            $this->writeOptionalDate($w, 'FechaDescarga', $bill->discharge_date);
            $w->writeElement('CodigoPaisDestino', $bill->destination_country_code);
            $w->writeElement('MarcaBultos', $bill->cargo_marks);

            $this->writeOptionalString($w, 'Consignatario', $bill->consignee?->legal_name, 80);
            $notifyName = $bill->notifyParty?->legal_name ?: $bill->notify_party_text;
            $this->writeOptionalString($w, 'NotificarA', $notifyName, 35);

            $w->writeElement('IndicadorConsolidado', $this->normalizeFlag($bill->is_consolidated));
            $w->writeElement('IndicadorTransitoTrasbordo', $this->normalizeFlag($bill->is_transit_transshipment));

            $documentType = $this->resolveConsigneeDocumentType($bill);
            $consigneeTaxId = $this->resolveConsigneeTaxId($bill);
            $this->writeOptionalString($w, 'TipoDocumentoDestinatarioMercaderia', $documentType, 4);
            if ($consigneeTaxId !== null) {
                $w->writeElement('IdentificadorDestinatarioMercaderia', $consigneeTaxId);
            }

            $w->writeElement('PosicionArancelaria', $this->resolveSingleTitleValue($bill, 'tariff_position', 'PosicionArancelaria', 16, true));
            $w->writeElement('IndicadorOperadorLogisticoSeguro', $this->resolveSingleTitleFlag($bill, 'is_secure_logistics_operator', 'IndicadorOperadorLogisticoSeguro'));
            $w->writeElement('IndicadorTransitoMonitoreado', $this->resolveSingleTitleFlag($bill, 'is_monitored_transit', 'IndicadorTransitoMonitoreado'));
            $w->writeElement('IndicadorRenar', $this->resolveSingleTitleFlag($bill, 'is_renar', 'IndicadorRenar'));
            $w->writeElement('RazonSocialFowarderExterior', $this->resolveSingleTitleValue($bill, 'foreign_forwarder_name', 'RazonSocialFowarderExterior', 70, true));

            $this->writeOptionalString(
                $w,
                'IndicadorTributarioForwarderExterior',
                $this->resolveSingleTitleValue($bill, 'foreign_forwarder_tax_id', 'IndicadorTributarioForwarderExterior', 35, false),
                35
            );
            $this->writeOptionalString(
                $w,
                'CodigoPaisEmisorIdentificadorForwarderExterior',
                $this->resolveSingleTitleValue($bill, 'foreign_forwarder_country', 'CodigoPaisEmisorIdentificadorForwarderExterior', 3, false),
                3
            );

            $this->writeOptionalString($w, 'Comentario', $bill->discrepancy_notes, 60);
            $w->writeElement('CodigoLugarOperativoDescarga', $bill->operational_discharge_code);
            $w->writeElement('CodigoAduanaDescarga', $bill->discharge_customs_code);

            $this->writeMerchandise($w, $bill);
            $this->writeContainers($w, $bill);

            $w->writeElement('IdentificadorTituloMadre', $bill->master_bill_number);
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
                $this->writeOptionalString($w, 'TipoEmbalaje', null, 1);
                $w->writeElement('CondicionContenedor', $item->container_condition);
                $w->writeElement('CantidadManifestada', (string) $item->package_quantity);
                $w->writeElement('PesoVolumenManifestado', $this->formatNumber($item->gross_weight_kg));
                $w->writeElement('DescripcionMercaderia', $item->item_description);
                $w->writeElement('NumeroBultos', $item->cargo_marks);

                $cargoTypeCode = $item->cargoType?->argentina_ws_code;
                $this->writeOptionalString($w, 'TipoCarga', $cargoTypeCode, 3);
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
            $operatorTaxId = Client::query()->whereKey($container->operator_client_id)->value('tax_id');
            $operatorTaxId = preg_replace('/\D+/', '', (string) $operatorTaxId);
            $characteristics = $container->argentina_container_code ?: $container->containerType?->argentina_ws_code;

            $w->startElement('Contenedor');
                $w->writeElement('CuitAtaOperadorContenedor', $operatorTaxId);
                $w->writeElement('CaracteristicasContenedor', $characteristics);
                $w->writeElement('IdentificadorContenedor', $container->container_number);
                $w->writeElement('CondicionContenedor', $container->container_condition);

                if ($container->tare_weight_kg !== null) {
                    $w->writeElement('Tara', $this->formatNumber($container->tare_weight_kg));
                }
                if ($container->current_gross_weight_kg !== null) {
                    $w->writeElement('PesoBruto', $this->formatNumber($container->current_gross_weight_kg));
                }

                $this->writeOptionalString($w, 'NumeroPrecintoOrigen', $container->shipper_seal, 35);
                $w->writeElement('FechaVencimientoContenedor', $this->formatDate($container->csc_expiry_date));

                $this->writeOptionalString($w, 'CodigoPuertoEmbarque', $bill->loadingPort?->code, 5);
                $this->writeOptionalDate($w, 'FechaEmbarque', $bill->loading_date);
                $this->writeOptionalDate($w, 'FechaCargaLugarOrigen', $bill->origin_loading_date);
                $this->writeOptionalString($w, 'CodigoLugarOrigen', $bill->origin_location, 50);
                $this->writeOptionalString($w, 'CodigoPaisLugarOrigen', $bill->origin_country_code, 3);
                $this->writeOptionalString($w, 'CodigoPuertoDescarga', $bill->dischargePort?->code, 5);
                $this->writeOptionalDate($w, 'FechaDescarga', $bill->discharge_date);
                $this->writeOptionalString($w, 'CodigoAduana', $bill->discharge_customs_code, 3);
                $this->writeOptionalString($w, 'CodigoLugarOperativoDescarga', $bill->operational_discharge_code, 5);
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

    private function resolveSingleTitleFlag(BillOfLading $bill, string $field, string $label): string
    {
        $values = $bill->shipmentItems
            ->pluck($field)
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->map(fn ($value) => strtoupper(trim((string) $value)))
            ->unique()
            ->values();

        if ($values->count() !== 1 || !in_array($values->first(), ['S', 'N'], true)) {
            throw new Exception("BL {$bill->id}: {$label} debe existir y tener un único valor S/N en sus líneas.");
        }

        return $values->first();
    }

    private function resolveSingleTitleValue(
        BillOfLading $bill,
        string $field,
        string $label,
        int $maxLength,
        bool $required
    ): ?string {
        $values = $bill->shipmentItems
            ->pluck($field)
            ->filter(fn ($value) => $value !== null && trim((string) $value) !== '')
            ->map(fn ($value) => trim((string) $value))
            ->unique()
            ->values();

        if ($values->count() > 1) {
            throw new Exception("BL {$bill->id}: {$label} tiene valores distintos entre líneas y el contrato admite uno por título.");
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

    private function resolveConsigneeDocumentType(BillOfLading $bill): ?string
    {
        $explicit = $this->resolveSingleTitleValue(
            $bill,
            'consignee_document_type',
            'TipoDocumentoDestinatarioMercaderia',
            4,
            false
        );

        if ($explicit !== null) {
            return $explicit;
        }

        $code = $bill->consignee?->documentType?->code;
        return $code ? $this->optionalString($code, 'TipoDocumentoDestinatarioMercaderia', 4) : null;
    }

    private function resolveConsigneeTaxId(BillOfLading $bill): ?string
    {
        $explicit = $this->resolveSingleTitleValue(
            $bill,
            'consignee_tax_id',
            'IdentificadorDestinatarioMercaderia',
            11,
            false
        );

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
        $w->writeAttribute('xmlns:ar', self::NAMESPACE);
        $w->startElementNs('soapenv', 'Header', null);
        $w->endElement();
        $w->startElementNs('soapenv', 'Body', null);
        $w->startElementNs('ar', $method, null);
    }

    private function endEnvelope(\XMLWriter $w): void
    {
        $w->endElement(); // method
        $w->endElement(); // Body
        $w->endElement(); // Envelope
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
        $cached = WsaaToken::getValidToken(
            $this->company->id,
            self::SERVICE_NAME,
            $this->environment
        );

        if ($cached) {
            $cached->markAsUsed();
            return ['token' => $cached->token, 'sign' => $cached->sign];
        }

        $certificateManager = new CertificateManagerService($this->company);
        $certificate = $certificateManager->readCertificate();
        if (!$certificate || empty($certificate['cert']) || empty($certificate['pkey'])) {
            throw new Exception('No se pudo leer certificado y clave privada de la empresa para WSAA.');
        }

        $loginTicket = $this->generateLoginTicket();
        $signedTicket = $this->signLoginTicket($loginTicket, $certificate);
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
            'creation_context' => [
                'method' => 'getWsaaTokens',
                'service' => self::SERVICE_NAME,
            ],
        ]);

        return ['token' => $tokens['token'], 'sign' => $tokens['sign']];
    }

    private function generateLoginTicket(): string
    {
        $uniqueId = (int) min(time(), 2147483647);
        $nowUtc = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $generationTime = $nowUtc->sub(new \DateInterval('PT5M'));
        $expirationTime = $nowUtc->add(new \DateInterval('PT12H'));

        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<loginTicketRequest version="1.0">'
            . '<header>'
            . '<uniqueId>' . $uniqueId . '</uniqueId>'
            . '<generationTime>' . $generationTime->format('Y-m-d\TH:i:s\Z') . '</generationTime>'
            . '<expirationTime>' . $expirationTime->format('Y-m-d\TH:i:s\Z') . '</expirationTime>'
            . '</header>'
            . '<service>' . self::SERVICE_NAME . '</service>'
            . '</loginTicketRequest>';
    }

    private function signLoginTicket(string $loginTicket, array $certificate): string
    {
        $loginTicketFile = tempnam(sys_get_temp_dir(), 'wsaa_ticket_');
        $certificateFile = tempnam(sys_get_temp_dir(), 'wsaa_cert_');
        $privateKeyFile = tempnam(sys_get_temp_dir(), 'wsaa_key_');
        $signedFile = tempnam(sys_get_temp_dir(), 'wsaa_signed_');

        if (!$loginTicketFile || !$certificateFile || !$privateKeyFile || !$signedFile) {
            throw new Exception('No se pudieron crear archivos temporales para firmar WSAA.');
        }

        try {
            file_put_contents($loginTicketFile, $loginTicket);

            $certificateContent = $certificate['cert'];
            if (!empty($certificate['extracerts']) && is_array($certificate['extracerts'])) {
                foreach ($certificate['extracerts'] as $extraCertificate) {
                    $certificateContent .= "\n" . $extraCertificate;
                }
            }

            file_put_contents($certificateFile, $certificateContent);
            file_put_contents($privateKeyFile, $certificate['pkey']);

            $command = sprintf(
                'openssl smime -sign -in %s -out %s -signer %s -inkey %s -outform DER -nodetach 2>&1',
                escapeshellarg($loginTicketFile),
                escapeshellarg($signedFile),
                escapeshellarg($certificateFile),
                escapeshellarg($privateKeyFile)
            );

            exec($command, $output, $returnCode);
            if ($returnCode !== 0 || !is_file($signedFile) || filesize($signedFile) === 0) {
                throw new Exception('Error firmando LoginTicket WSAA: ' . implode(' | ', $output));
            }

            return base64_encode((string) file_get_contents($signedFile));
        } finally {
            @unlink($loginTicketFile);
            @unlink($certificateFile);
            @unlink($privateKeyFile);
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

        $generationTime = isset($xml->header->generationTime)
            ? new \DateTimeImmutable((string) $xml->header->generationTime)
            : null;
        $expirationTime = isset($xml->header->expirationTime)
            ? new \DateTimeImmutable((string) $xml->header->expirationTime)
            : null;

        return [
            'token' => (string) $xml->credentials->token,
            'sign' => (string) $xml->credentials->sign,
            'generation_time' => $generationTime,
            'expiration_time' => $expirationTime,
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
        $normalized = $this->normalizeFlag($value);
        if (!in_array($normalized, ['S', 'N'], true)) {
            throw new Exception("{$label} debe ser S o N.");
        }

        return $normalized;
    }

    private function normalizeFlag($value): string
    {
        if (is_bool($value)) {
            return $value ? 'S' : 'N';
        }

        return strtoupper(trim((string) ($value ?? '')));
    }

    private function requiredContainerCondition($value, string $label): string
    {
        $condition = strtoupper(trim((string) ($value ?? '')));
        if (!in_array($condition, ['H', 'P'], true)) {
            throw new Exception("{$label} debe ser H o P.");
        }

        return $condition;
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
