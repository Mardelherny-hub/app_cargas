<?php

namespace App\Services\Webservice\Argentina;

use App\Models\BillOfLading;
use App\Models\Client;
use App\Models\Company;
use App\Models\Container;
use App\Models\Voyage;
use App\Models\WsaaToken;
use App\Services\Webservice\CertificateManagerService;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Exception;
use Illuminate\Support\Collection;
use Throwable;
use XMLWriter;

/**
 * XML SOAP para ATA Desconsolidador - wgesinformacionanticipada.
 *
 * Fuente contractual: Manual del Desarrollador AFIP v4.11 (18/12/2018).
 * No completa datos ausentes con valores simulados ni deriva campos de otros
 * conceptos que tengan una semántica distinta dentro de la aplicación.
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
            $this->requiredString(
                $bill->loadingPort?->code,
                "BL {$bill->id}: CodigoPuertoEmbarque",
                5
            );
            $this->requiredString(
                $bill->bill_number,
                "BL {$bill->id}: NumeroConocimiento",
                18
            );
        }

        $auth = $this->getWsaaTokens();
        $writer = $this->createWriter();
        $this->startEnvelope($writer, 'EliminarTitulosDesconsolidador');
        $this->writeAuthentication($writer, $auth);

        $writer->startElement('argEliminarTitulosDesconsolidador');
        $writer->writeElement('IdTransaccion', $transactionId);
        $writer->startElement('InformacionTitulosDesconsolidadorDoc');
        $writer->writeElement('IdentificadorViaje', (string) $this->voyage->argentina_voyage_id);
        $writer->startElement('PuertosConocimientos');

        foreach ($bills as $bill) {
            $writer->startElement('PuertoConocimiento');
            $writer->writeElement('CodigoPuertoEmbarque', (string) $bill->loadingPort->code);
            $writer->writeElement('NumeroConocimiento', (string) $bill->bill_number);
            $writer->endElement();
        }

        $writer->endElement();
        $writer->endElement();
        $writer->endElement();
        $this->endEnvelope($writer);

        return $writer->outputMemory();
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

        // Recién se solicita/reutiliza el TA después de superar validación local.
        $auth = $this->getWsaaTokens();
        $writer = $this->createWriter();
        $this->startEnvelope($writer, $method);
        $this->writeAuthentication($writer, $auth);

        $writer->startElement($argumentName);
        $writer->writeElement('IdTransaccion', $transactionId);
        $writer->startElement('InformacionTitulosDesconsolidadorDoc');
        $writer->writeElement('IdentificadorViaje', (string) $this->voyage->argentina_voyage_id);
        $writer->startElement('TitulosDesconsolidador');

        foreach ($bills as $bill) {
            $this->writeTitle($writer, $bill);
        }

        $writer->endElement();
        $writer->endElement();
        $writer->endElement();
        $this->endEnvelope($writer);

        return $writer->outputMemory();
    }

    private function getDesconsolidatedBills(array $billIds): Collection
    {
        $ids = collect($billIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        // Esta es la semántica vigente de la aplicación para un título hijo:
        // BillOfLading con master_bill_number informado.
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

        if ($ids !== []) {
            $query->whereIn('bills_of_lading.id', $ids);
        }

        $bills = $query->orderBy('bills_of_lading.id')->get();

        if ($bills->isEmpty()) {
            throw new Exception('No hay títulos desconsolidados para procesar.');
        }

        if ($ids !== [] && $bills->count() !== count($ids)) {
            throw new Exception(
                'Uno o más conocimientos seleccionados no pertenecen al viaje o no son desconsolidados.'
            );
        }

        return $bills;
    }

    private function validateBill(BillOfLading $bill): void
    {
        $prefix = "BL {$bill->id}";

        $this->requiredDate($bill->loading_date, "{$prefix}: FechaEmbarque");
        $this->requiredString($bill->loadingPort?->code, "{$prefix}: CodigoPuertoEmbarque", 5);

        // v4.11: sólo FechaCargaLugarOrigen es opcional para Desconsolidador.
        $this->requiredString($bill->origin_location, "{$prefix}: LugarOrigen", 50);
        $this->requiredString($bill->origin_country_code, "{$prefix}: CodigoPaisLugarOrigen", 3);

        $this->requiredString($bill->bill_number, "{$prefix}: NumeroConocimiento", 18);
        $this->requiredString($bill->dischargePort?->code, "{$prefix}: CodigoPuertoDescarga", 5);
        $this->requiredString($bill->destination_country_code, "{$prefix}: CodigoPaisDestino", 3);
        $this->requiredString($bill->cargo_marks, "{$prefix}: MarcaBultos", 80);
        $this->requiredFlag($bill->is_consolidated, "{$prefix}: IndicadorConsolidado");
        $this->requiredFlag($bill->is_transit_transshipment, "{$prefix}: IndicadorTransitoTrasbordo");
        $this->requiredString($bill->discharge_customs_code, "{$prefix}: CodigoAduanaDescarga", 3);
        $this->requiredString(
            $bill->operational_discharge_code,
            "{$prefix}: CodigoLugarOperativoDescarga",
            5
        );

        if ($bill->shipmentItems->isEmpty()) {
            throw new Exception("{$prefix}: debe tener al menos una línea de mercadería.");
        }

        // AFIP define estos atributos a nivel título. En la app viven en las
        // líneas; por eso todas las líneas del BL deben contener el mismo valor.
        $this->singleItemValue($bill, 'tariff_position', 'PosicionArancelaria', 16, true);
        $this->singleItemFlag(
            $bill,
            'is_secure_logistics_operator',
            'IndicadorOperadorLogisticoSeguro'
        );
        $this->singleItemFlag($bill, 'is_monitored_transit', 'IndicadorTransitoMonitoreado');
        $this->singleItemFlag($bill, 'is_renar', 'IndicadorRenar');
        $this->singleItemValue(
            $bill,
            'foreign_forwarder_name',
            'RazonSocialFowarderExterior',
            70,
            true
        );

        $lineNumbers = [];
        foreach ($bill->shipmentItems as $item) {
            if ($item->line_number === null || $item->line_number === '') {
                throw new Exception("{$prefix}: ShipmentItem {$item->id} no tiene NumeroLinea.");
            }

            $lineNumber = (int) $item->line_number;
            if ($lineNumber < 0 || $lineNumber > 999) {
                throw new Exception(
                    "{$prefix}: ShipmentItem {$item->id} NumeroLinea fuera de rango Int(3)."
                );
            }
            if (in_array($lineNumber, $lineNumbers, true)) {
                throw new Exception("{$prefix}: NumeroLinea {$lineNumber} está repetido.");
            }
            $lineNumbers[] = $lineNumber;

            $packagingCode = $item->packaging_code ?: $item->packagingType?->argentina_ws_code;
            $this->requiredString(
                $packagingCode,
                "{$prefix}: ShipmentItem {$item->id} CodigoEmbalaje",
                2
            );

            if ($item->container_condition !== null && $item->container_condition !== '') {
                $this->containerCondition(
                    $item->container_condition,
                    "{$prefix}: ShipmentItem {$item->id} CondicionContenedor"
                );
            }

            $this->requiredIntegerLike(
                $item->package_quantity,
                "{$prefix}: ShipmentItem {$item->id} CantidadManifestada",
                9
            );
            $this->requiredIntegerLike(
                $item->gross_weight_kg,
                "{$prefix}: ShipmentItem {$item->id} PesoVolumenManifestado",
                12
            );
            $this->requiredString(
                $item->item_description,
                "{$prefix}: ShipmentItem {$item->id} DescripcionMercaderia",
                80
            );
            $this->requiredString(
                $item->cargo_marks,
                "{$prefix}: ShipmentItem {$item->id} NumeroBultos",
                100
            );
        }

        foreach ($this->billContainers($bill) as $container) {
            $this->validateContainer($container, $bill);
        }
    }

    private function validateContainer(Container $container, BillOfLading $bill): void
    {
        $prefix = "BL {$bill->id}: Contenedor {$container->id}";
        $characteristics = $container->argentina_container_code
            ?: $container->containerType?->argentina_ws_code;

        $this->containerOperatorCuit($container, $prefix);
        $this->requiredString($characteristics, "{$prefix}: CaracteristicasContenedor", 4);
        $this->requiredString($container->container_number, "{$prefix}: IdentificadorContenedor", 20);
        $this->containerCondition($container->container_condition, "{$prefix}: CondicionContenedor");
        $this->requiredIntegerLike($container->tare_weight_kg, "{$prefix}: Tara", 10);
        $this->requiredIntegerLike($container->current_gross_weight_kg, "{$prefix}: PesoBruto", 14);
        $this->requiredString($bill->discharge_customs_code, "{$prefix}: CodigoAduana", 3);
        $this->requiredString(
            $bill->operational_discharge_code,
            "{$prefix}: CodigoLugarOperativoDescarga",
            5
        );
    }

    private function writeTitle(XMLWriter $writer, BillOfLading $bill): void
    {
        $writer->startElement('TituloDesconsolidador');
        $writer->writeElement('FechaEmbarque', $this->formatDate($bill->loading_date));
        $writer->writeElement('CodigoPuertoEmbarque', (string) $bill->loadingPort->code);
        $this->writeOptionalDate($writer, 'FechaCargaLugarOrigen', $bill->origin_loading_date);
        $writer->writeElement('LugarOrigen', (string) $bill->origin_location);
        $writer->writeElement('CodigoPaisLugarOrigen', (string) $bill->origin_country_code);
        $writer->writeElement('NumeroConocimiento', (string) $bill->bill_number);
        $this->writeOptionalString(
            $writer,
            'CodigoPuertoTrasbordo',
            $bill->transshipmentPort?->code,
            5
        );
        $writer->writeElement('CodigoPuertoDescarga', (string) $bill->dischargePort->code);
        $this->writeOptionalDate($writer, 'FechaDescarga', $bill->discharge_date);
        $writer->writeElement('CodigoPaisDestino', (string) $bill->destination_country_code);
        $writer->writeElement('MarcaBultos', (string) $bill->cargo_marks);
        $this->writeOptionalString($writer, 'Consignatario', $bill->consignee?->legal_name, 80);
        $this->writeOptionalString(
            $writer,
            'NotificarA',
            $bill->notifyParty?->legal_name ?: $bill->notify_party_text,
            35
        );
        $writer->writeElement('IndicadorConsolidado', $this->normalizeFlag($bill->is_consolidated));
        $writer->writeElement(
            'IndicadorTransitoTrasbordo',
            $this->normalizeFlag($bill->is_transit_transshipment)
        );

        $this->writeOptionalString(
            $writer,
            'TipoDocumentoDestinatarioMercaderia',
            $this->consigneeDocumentType($bill),
            4
        );
        $this->writeOptionalString(
            $writer,
            'IdentificadorDestinatarioMercaderia',
            $this->consigneeTaxId($bill),
            11
        );

        // No existe en la app un campo inequívoco para país de emisión de pasaporte.
        $writer->writeElement(
            'PosicionArancelaria',
            $this->singleItemValue($bill, 'tariff_position', 'PosicionArancelaria', 16, true)
        );
        $writer->writeElement(
            'IndicadorOperadorLogisticoSeguro',
            $this->singleItemFlag(
                $bill,
                'is_secure_logistics_operator',
                'IndicadorOperadorLogisticoSeguro'
            )
        );
        $writer->writeElement(
            'IndicadorTransitoMonitoreado',
            $this->singleItemFlag($bill, 'is_monitored_transit', 'IndicadorTransitoMonitoreado')
        );
        $writer->writeElement(
            'IndicadorRenar',
            $this->singleItemFlag($bill, 'is_renar', 'IndicadorRenar')
        );
        $writer->writeElement(
            'RazonSocialFowarderExterior',
            $this->singleItemValue(
                $bill,
                'foreign_forwarder_name',
                'RazonSocialFowarderExterior',
                70,
                true
            )
        );
        $this->writeOptionalString(
            $writer,
            'IndicadorTributarioForwarderExterior',
            $this->singleItemValue(
                $bill,
                'foreign_forwarder_tax_id',
                'IndicadorTributarioForwarderExterior',
                35,
                false
            ),
            35
        );
        $this->writeOptionalString(
            $writer,
            'CodigoPaisEmisorIdentificadorForwarderExterior',
            $this->singleItemValue(
                $bill,
                'foreign_forwarder_country',
                'CodigoPaisEmisorIdentificadorForwarderExterior',
                3,
                false
            ),
            3
        );

        // No se reutiliza discrepancy_notes como Comentario: semántica distinta.
        $writer->writeElement(
            'CodigoLugarOperativoDescarga',
            (string) $bill->operational_discharge_code
        );
        $writer->writeElement('CodigoAduanaDescarga', (string) $bill->discharge_customs_code);

        $this->writeMerchandise($writer, $bill);
        $this->writeContainers($writer, $bill);

        // El ejemplo oficial lo ubica al final del TituloDesconsolidador.
        $this->writeOptionalString(
            $writer,
            'IdentificadorTituloMadre',
            $bill->master_bill_number,
            23
        );
        $writer->endElement();
    }

    private function writeMerchandise(XMLWriter $writer, BillOfLading $bill): void
    {
        $writer->startElement('Mercaderias');

        foreach ($bill->shipmentItems->sortBy('line_number') as $item) {
            $packagingCode = $item->packaging_code ?: $item->packagingType?->argentina_ws_code;

            $writer->startElement('LineaMercaderia');
            $writer->writeElement('NumeroLinea', (string) ((int) $item->line_number));
            $writer->writeElement('CodigoEmbalaje', (string) $packagingCode);

            // TipoEmbalaje es opcional y no existe un mapeo AFIP inequívoco en el modelo.
            if ($item->container_condition !== null && $item->container_condition !== '') {
                $writer->writeElement(
                    'CondicionContenedor',
                    strtoupper((string) $item->container_condition)
                );
            }

            $writer->writeElement('CantidadManifestada', $this->integerString($item->package_quantity));
            $writer->writeElement(
                'PesoVolumenManifestado',
                $this->integerString($item->gross_weight_kg)
            );
            $writer->writeElement('DescripcionMercaderia', (string) $item->item_description);
            $writer->writeElement('NumeroBultos', (string) $item->cargo_marks);
            $this->writeOptionalString($writer, 'TipoCarga', $item->cargoType?->webservice_code, 3);
            $this->writeOptionalString($writer, 'Comentario', $item->comments, 60);
            $writer->endElement();
        }

        $writer->endElement();
    }

    private function writeContainers(XMLWriter $writer, BillOfLading $bill): void
    {
        $containers = $this->billContainers($bill);
        if ($containers->isEmpty()) {
            return;
        }

        $writer->startElement('Contenedores');

        foreach ($containers as $container) {
            $characteristics = $container->argentina_container_code
                ?: $container->containerType?->argentina_ws_code;

            $writer->startElement('Contenedor');
            $writer->writeElement(
                'CuitAtaOperadorContenedor',
                $this->containerOperatorCuit($container, "Contenedor {$container->id}")
            );
            $writer->writeElement('CaracteristicasContenedor', (string) $characteristics);
            $writer->writeElement('IdentificadorContenedor', (string) $container->container_number);
            $writer->writeElement(
                'CondicionContenedor',
                strtoupper((string) $container->container_condition)
            );
            $writer->writeElement('Tara', $this->integerString($container->tare_weight_kg));
            $writer->writeElement(
                'PesoBruto',
                $this->integerString($container->current_gross_weight_kg)
            );
            $this->writeOptionalString(
                $writer,
                'NumeroPrecintoOrigen',
                $container->shipper_seal,
                35
            );
            $this->writeOptionalDate(
                $writer,
                'FechaVencimientoContenedor',
                $container->csc_expiry_date
            );

            // Acep, puertos y lugar de origen del contenedor son opcionales;
            // no hay campos inequívocos para ellos en Container.
            $writer->writeElement('CodigoAduana', (string) $bill->discharge_customs_code);
            $writer->writeElement(
                'CodigoLugarOperativoDescarga',
                (string) $bill->operational_discharge_code
            );
            $writer->endElement();
        }

        $writer->endElement();
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

        $taxId = Client::query()
            ->whereKey($container->operator_client_id)
            ->value('tax_id');
        $clean = preg_replace('/\D+/', '', (string) $taxId);

        if (strlen($clean) !== 11) {
            throw new Exception("{$prefix}: el CUIT del operador de contenedor debe tener 11 dígitos.");
        }

        return $clean;
    }

    private function singleItemFlag(BillOfLading $bill, string $field, string $label): string
    {
        $value = strtoupper((string) $this->singleItemValue($bill, $field, $label, 1, true));
        if (!in_array($value, ['S', 'N'], true)) {
            throw new Exception("BL {$bill->id}: {$label} debe ser S o N.");
        }

        return $value;
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
            ->filter(fn ($value) => $value !== null && trim((string) $value) !== '')
            ->map(fn ($value) => trim((string) $value))
            ->unique()
            ->values();

        if ($values->count() > 1) {
            throw new Exception(
                "BL {$bill->id}: {$label} tiene valores distintos entre líneas; AFIP admite uno por título."
            );
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
        $explicit = $this->singleItemValue(
            $bill,
            'consignee_document_type',
            'TipoDocumentoDestinatarioMercaderia',
            4,
            false
        );

        return $explicit ?: $this->optionalString(
            $bill->consignee?->documentType?->code,
            'TipoDocumentoDestinatarioMercaderia',
            4
        );
    }

    private function consigneeTaxId(BillOfLading $bill): ?string
    {
        $explicit = $this->singleItemValue(
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
            throw new Exception(
                'El viaje no tiene IdentificadorViaje AFIP. Primero debe existir un RegistrarViaje exitoso.'
            );
        }
        if (mb_strlen($identifier) > 16) {
            throw new Exception('IdentificadorViaje AFIP supera los 16 caracteres permitidos.');
        }
    }

    private function createWriter(): XMLWriter
    {
        $writer = new XMLWriter();
        $writer->openMemory();
        $writer->startDocument('1.0', 'UTF-8');
        return $writer;
    }

    private function startEnvelope(XMLWriter $writer, string $method): void
    {
        $writer->startElementNs('soapenv', 'Envelope', 'http://schemas.xmlsoap.org/soap/envelope/');
        $writer->startElementNs('soapenv', 'Header', null);
        $writer->endElement();
        $writer->startElementNs('soapenv', 'Body', null);
        $writer->startElement($method);
        $writer->writeAttribute('xmlns', self::NAMESPACE);
    }

    private function endEnvelope(XMLWriter $writer): void
    {
        $writer->endElement(); // método
        $writer->endElement(); // Body
        $writer->endElement(); // Envelope
        $writer->endDocument();
    }

    private function writeAuthentication(XMLWriter $writer, array $auth): void
    {
        $cuit = preg_replace('/\D+/', '', (string) $this->company->tax_id);
        if (strlen($cuit) !== 11) {
            throw new Exception('CUIT de la empresa conectada inválido; debe tener 11 dígitos.');
        }

        $writer->startElement('argWSAutenticacionEmpresa');
        $writer->writeElement('Token', $auth['token']);
        $writer->writeElement('Sign', $auth['sign']);
        $writer->writeElement('CuitEmpresaConectada', $cuit);
        $writer->writeElement('TipoAgente', 'TRSP');
        $writer->writeElement('Rol', 'TRSP');
        $writer->endElement();
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

        $certificate = (new CertificateManagerService($this->company))->readCertificate();
        if (!$certificate || empty($certificate['cert']) || empty($certificate['pkey'])) {
            throw new Exception(
                'No se pudo leer certificado y clave privada de la empresa para WSAA.'
            );
        }

        $signedTicket = $this->signLoginTicket($this->generateLoginTicket(), $certificate);
        $tokens = $this->callWsaa($signedTicket);
        $issuedAt = $tokens['generation_time'] ?? now();
        $expiresAt = $tokens['expiration_time'] ?? now()->addHours(12);

        WsaaToken::createToken([
            'company_id' => $this->company->id,
            'service_name' => self::SERVICE_NAME,
            'environment' => $this->environment,
            'token' => $tokens['token'],
            'sign' => $tokens['sign'],
            'issued_at' => $issuedAt,
            'expires_at' => $expiresAt,
            'generation_time' => $issuedAt instanceof DateTimeInterface
                ? $issuedAt->format('c')
                : (string) $issuedAt,
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
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $uniqueId = (int) min(time(), 2147483647);

        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<loginTicketRequest version="1.0"><header>'
            . '<uniqueId>' . $uniqueId . '</uniqueId>'
            . '<generationTime>'
            . $now->modify('-5 minutes')->format('Y-m-d\TH:i:s\Z')
            . '</generationTime>'
            . '<expirationTime>'
            . $now->modify('+12 hours')->format('Y-m-d\TH:i:s\Z')
            . '</expirationTime>'
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

            $certificateContent = (string) $certificate['cert'];
            foreach (($certificate['extracerts'] ?? []) as $extraCertificate) {
                $certificateContent .= "\n" . $extraCertificate;
            }

            file_put_contents($certFile, $certificateContent);
            file_put_contents($keyFile, (string) $certificate['pkey']);

            $command = sprintf(
                'openssl smime -sign -in %s -out %s -signer %s -inkey %s -outform DER -nodetach 2>&1',
                escapeshellarg($ticketFile),
                escapeshellarg($signedFile),
                escapeshellarg($certFile),
                escapeshellarg($keyFile)
            );

            exec($command, $output, $returnCode);
            if ($returnCode !== 0 || !is_file($signedFile) || filesize($signedFile) === 0) {
                throw new Exception(
                    'Error firmando LoginTicket WSAA: ' . implode(' | ', $output)
                );
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

        $xml = simplexml_load_string((string) $response->loginCmsReturn);
        if ($xml === false || empty($xml->credentials->token) || empty($xml->credentials->sign)) {
            throw new Exception('Respuesta WSAA inválida o sin Token/Sign.');
        }

        return [
            'token' => (string) $xml->credentials->token,
            'sign' => (string) $xml->credentials->sign,
            'generation_time' => isset($xml->header->generationTime)
                ? new DateTimeImmutable((string) $xml->header->generationTime)
                : null,
            'expiration_time' => isset($xml->header->expirationTime)
                ? new DateTimeImmutable((string) $xml->header->expirationTime)
                : null,
            'unique_id' => isset($xml->header->uniqueId)
                ? (string) $xml->header->uniqueId
                : null,
        ];
    }

    private function requiredString($value, string $label, int $maxLength): string
    {
        $text = trim((string) ($value ?? ''));
        if ($text === '') {
            throw new Exception("Falta campo obligatorio {$label}.");
        }
        if (mb_strlen($text) > $maxLength) {
            throw new Exception("{$label} supera {$maxLength} caracteres.");
        }

        return $text;
    }

    private function optionalString($value, string $label, int $maxLength): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        $text = trim((string) $value);
        if (mb_strlen($text) > $maxLength) {
            throw new Exception("{$label} supera {$maxLength} caracteres.");
        }

        return $text;
    }

    private function requiredFlag($value, string $label): string
    {
        $flag = $this->normalizeFlag($value);
        if (!in_array($flag, ['S', 'N'], true)) {
            throw new Exception("{$label} debe ser S o N.");
        }

        return $flag;
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
        $code = strtoupper(trim((string) ($value ?? '')));
        if ($code === '' || mb_strlen($code) !== 1) {
            throw new Exception(
                "{$label} debe contener un código CONCTD_DESC de un carácter."
            );
        }

        return $code;
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
            return $value instanceof DateTimeInterface
                ? $value->format('Y-m-d\TH:i:s')
                : (new DateTimeImmutable((string) $value))->format('Y-m-d\TH:i:s');
        } catch (Throwable $exception) {
            throw new Exception(
                'Fecha inválida para XML AFIP: ' . (string) $value,
                0,
                $exception
            );
        }
    }

    private function requiredIntegerLike($value, string $label, int $maxDigits): string
    {
        if ($value === null || $value === '' || !is_numeric($value)) {
            throw new Exception("Falta o es inválido el campo obligatorio {$label}.");
        }

        return $this->integerString($value, $label, $maxDigits);
    }

    private function integerString($value, string $label = 'valor', ?int $maxDigits = null): string
    {
        if (!is_numeric($value)) {
            throw new Exception("{$label} debe ser numérico.");
        }

        $number = (float) $value;
        if ($number < 0 || floor($number) !== $number) {
            throw new Exception("{$label} debe ser un entero no negativo según contrato AFIP.");
        }

        $text = number_format($number, 0, '.', '');
        if ($maxDigits !== null && strlen($text) > $maxDigits) {
            throw new Exception("{$label} supera Int({$maxDigits}).");
        }

        return $text;
    }

    private function writeOptionalString(
        XMLWriter $writer,
        string $name,
        $value,
        int $maxLength
    ): void {
        $text = $this->optionalString($value, $name, $maxLength);
        if ($text !== null) {
            $writer->writeElement($name, $text);
        }
    }

    private function writeOptionalDate(XMLWriter $writer, string $name, $value): void
    {
        if ($value) {
            $writer->writeElement($name, $this->formatDate($value));
        }
    }
}
