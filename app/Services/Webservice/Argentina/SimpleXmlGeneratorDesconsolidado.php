<?php

namespace App\Services\Webservice\Argentina;

use App\Models\BillOfLading;
use App\Models\Client;
use App\Models\Company;
use App\Models\DeconsolidationOperation;
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
 * Contratos usados:
 * - Manual del Desarrollador AFIP wgesinformacionanticipada v4.11.
 * - Datos a transmitir por ATA-DESC (obligatoriedad/condicionalidad específica).
 *
 * No completa datos ausentes con valores simulados ni reutiliza campos con
 * otra semántica sólo para satisfacer el XML.
 */
class SimpleXmlGeneratorDesconsolidado
{
    private const NAMESPACE = 'Ar.Gob.Afip.Dga.Org.wgesinformacionanticipada';
    private const SERVICE_NAME = 'wgesinformacionanticipada';

    private Voyage|DeconsolidationOperation $voyage;
    private Company $company;
    private string $environment;

    public function __construct(Voyage|DeconsolidationOperation $voyage, array $config = [])
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
                "Conocimiento {$bill->bill_number}: código del puerto de embarque para eliminar el título",
                5
            );
            $this->requiredString(
                $bill->bill_number,
                "Conocimiento {$bill->bill_number}: número de conocimiento",
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

    /**
     * Ejecuta exactamente las validaciones locales que anteceden al WSAA,
     * sin solicitar Token/Sign ni abrir conexión con AFIP.
     */
    public function validateLocalData(string $operation = 'registrar', array $billIds = []): array
    {
        $errors = [];

        if (!in_array($operation, ['registrar', 'rectificar', 'eliminar'], true)) {
            return [
                'is_valid' => false,
                'errors' => ["Operación desconsolidada desconocida: {$operation}."],
            ];
        }

        try {
            $this->validateVoyageIdentifier();
        } catch (Throwable $exception) {
            $errors[] = $exception->getMessage();
        }

        try {
            $bills = $this->getDesconsolidatedBills($billIds);
        } catch (Throwable $exception) {
            $errors[] = $exception->getMessage();

            return [
                'is_valid' => false,
                'errors' => array_values(array_unique($errors)),
            ];
        }

        foreach ($bills as $bill) {
            try {
                if ($operation === 'eliminar') {
                    $this->requiredString(
                        $bill->loadingPort?->code,
                        "Conocimiento {$bill->bill_number}: código del puerto de embarque para eliminar el título",
                        5
                    );
                    $this->requiredString(
                        $bill->bill_number,
                        "Conocimiento {$bill->bill_number}: número de conocimiento",
                        18
                    );
                } else {
                    $this->validateBill($bill);
                }
            } catch (Throwable $exception) {
                $errors[] = $exception->getMessage();
            }
        }

        $errors = array_values(array_unique($errors));

        return [
            'is_valid' => $errors === [],
            'errors' => $errors,
        ];
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

        // El TA se obtiene recién después de superar toda validación local.
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

        $query = $this->voyage->billsOfLading()
            ->whereNotNull('master_bill_number')
            ->with([
                'loadingPort',
                'dischargePort.country',
                'transshipmentPort',
                'shipper',
                'consignee.documentType',
                'notifyParty',
                'shipmentItems.packagingType',
                'shipmentItems.cargoType',
                'shipmentItems.containers.containerType',
            ]);

        if ($this->voyage instanceof DeconsolidationOperation) {
            $query->whereNull('shipment_id');
        }

        if ($ids !== []) {
            $query->whereIn('bills_of_lading.id', $ids);
        }

        $bills = $query->orderBy('bills_of_lading.id')->get();

        if ($bills->isEmpty()) {
            throw new Exception('No hay conocimientos hijos para procesar en esta operación.');
        }

        if ($ids !== [] && $bills->count() !== count($ids)) {
            throw new Exception(
                'Uno o más conocimientos seleccionados no pertenecen a esta operación o no están habilitados para desconsolidación.'
            );
        }

        return $bills;
    }

    private function masterTitleIdentifier(BillOfLading $bill): string
    {
        $prefix = "Conocimiento {$bill->bill_number}";
        $masterNumber = $this->requiredString(
            $bill->master_bill_number,
            "{$prefix}: número del conocimiento madre",
            18
        );

        $masterPort = strtoupper(trim((string) data_get(
            $bill->webservice_data,
            'argentina.desconsolidated.master_loading_port_code'
        )));

        if ($masterPort === '') {
            throw new Exception(
                "{$prefix}: falta el código del puerto de embarque del conocimiento madre."
            );
        }

        if (!preg_match('/^[A-Z0-9]{5}$/', $masterPort)) {
            throw new Exception(
                "{$prefix}: el código del puerto de embarque del conocimiento madre "
                . "debe tener 5 caracteres alfanuméricos."
            );
        }

        return $masterPort . $masterNumber;
    }

    private function tariffPositionForBill(BillOfLading $bill): string
    {
        $value = $this->singleItemValue(
            $bill,
            'tariff_position',
            'posición arancelaria (NCM)',
            16,
            true
        );

        if ($this->normalizeFlag($bill->is_consolidated) === 'N') {
            $length = mb_strlen($value);
            if ($length < 7 || $length > 15) {
                throw new Exception(
                    "Conocimiento {$bill->bill_number}: la posición arancelaria (NCM) debe tener entre "
                    . "7 y 15 caracteres en conocimientos no consolidados."
                );
            }
        }

        return $value;
    }

    /**
     * ARCA rechaza ATA-DESC cuando el destino/puerto de descarga no es
     * Argentina (errores 11413 y 11416). Se valida con el país real del
     * puerto cuando la relación está disponible, sin inventar códigos.
     */
    private function validateArgentinaDestination(
        BillOfLading $bill,
        string $prefix
    ): void {
        $country = $bill->dischargePort?->country;

        if (!$country) {
            return;
        }

        if (strtoupper(trim((string) $country->alpha2_code)) !== 'AR') {
            throw new Exception(
                "{$prefix}: ATA-DESC exige que el puerto de descarga "
                . "pertenezca a Argentina."
            );
        }

        $afipCountryCode = trim((string) ($country->codigo_afip ?? ''));
        if (
            $afipCountryCode !== ''
            && trim((string) $bill->destination_country_code)
                !== $afipCountryCode
        ) {
            throw new Exception(
                "{$prefix}: el código del país de destino no corresponde a Argentina."
            );
        }
    }

    private function validateBill(BillOfLading $bill): void
    {
        $prefix = "Conocimiento {$bill->bill_number}";

        $this->masterTitleIdentifier($bill);
        $this->exactLengthString(
            $bill->destination_country_code,
            "{$prefix}: código del país de destino",
            3
        );
        $this->validateArgentinaDestination($bill, $prefix);
        $this->requiredString($bill->bill_number, "{$prefix}: número de conocimiento", 18);
        $this->requiredString(
            $this->billMarks($bill),
            "{$prefix}: marcas o números de bultos",
            80
        );
        $this->requiredFlag($bill->is_consolidated, "{$prefix}: condición de consolidado");
        $this->requiredFlag(
            $bill->is_transit_transshipment,
            "{$prefix}: indicador de tránsito o transbordo"
        );

        $hasOriginLocation = trim((string) ($bill->origin_location ?? '')) !== '';
        if ($hasOriginLocation) {
            $this->requiredString($bill->origin_location, "{$prefix}: lugar de origen", 50);
            $this->requiredDate($bill->origin_loading_date, "{$prefix}: fecha de carga en origen");
            $this->requiredString(
                $bill->origin_country_code,
                "{$prefix}: código del país de origen",
                3
            );
        } elseif ($bill->origin_loading_date || trim((string) ($bill->origin_country_code ?? '')) !== '') {
            throw new Exception(
                "{$prefix}: para informar la fecha o el país de carga, también se debe indicar el lugar de origen."
            );
        }

        if ($bill->shipmentItems->isEmpty()) {
            throw new Exception("{$prefix}: agregá al menos una línea de mercadería antes de transmitir.");
        }

        $this->tariffPositionForBill($bill);
        $this->singleItemFlag(
            $bill,
            'is_secure_logistics_operator',
            'operador logístico seguro'
        );
        $this->singleItemFlag($bill, 'is_monitored_transit', 'tránsito monitoreado');
        $this->singleItemFlag($bill, 'is_renar', 'RENAR');
        $forwarderName = $this->forwarderNameForBill($bill);

        if ($this->normalizeFlag($bill->is_consolidated) === 'S') {
            $this->requiredString(
                $forwarderName,
                "{$prefix}: razón social del agente de carga en el exterior",
                70
            );
        }

        $lineNumbers = [];
        foreach ($bill->shipmentItems as $item) {
            if ($item->line_number === null || $item->line_number === '' || !is_numeric($item->line_number)) {
                throw new Exception("{$prefix}: hay una línea de mercadería sin número válido. Revisá sus datos.");
            }

            $lineNumber = (int) $item->line_number;
            if ($lineNumber < 1 || $lineNumber > 999) {
                throw new Exception(
                    "{$prefix}: el número de la línea {$item->line_number} debe estar entre 1 y 999."
                );
            }
            if (in_array($lineNumber, $lineNumbers, true)) {
                throw new Exception("{$prefix}: el número de línea {$lineNumber} está repetido.");
            }
            $lineNumbers[] = $lineNumber;

            $packagingCode = $this->packagingCodeForItem(
                $item,
                "{$prefix}: Línea {$item->line_number}"
            );

            $this->containerCondition(
                $item->container_condition,
                "{$prefix}: línea {$item->line_number}, condición H/P"
            );

            $manifestedQuantity = $packagingCode === '05'
                ? $item->containers->count()
                : $item->package_quantity;

            $this->requiredIntegerLike(
                $manifestedQuantity,
                "{$prefix}: línea {$item->line_number}, cantidad manifestada",
                9
            );
            $this->requiredDecimal(
                $item->gross_weight_kg,
                "{$prefix}: línea {$item->line_number}, peso bruto declarado"
            );
            $this->requiredString(
                $item->item_description,
                "{$prefix}: línea {$item->line_number}, descripción de mercadería",
                80
            );
            $this->requiredString(
                $this->packageMarksForItem($item),
                "{$prefix}: línea {$item->line_number}, marcas o números de bultos",
                100
            );
        }

        foreach ($this->billContainers($bill) as $container) {
            $this->validateContainer($container, $bill);
        }
    }

    private function validateContainer(Container $container, BillOfLading $bill): void
    {
        $prefix = "Conocimiento {$bill->bill_number}: Contenedor {$container->container_number}";

        $this->requiredString($container->container_number, "{$prefix}: número de contenedor", 20);
        $this->containerConditionForBill($container, $bill);

        if ($container->operator_client_id) {
            $this->containerOperatorCuit($container, $prefix);
        }

        if ($container->argentina_container_code !== null
            && trim((string) $container->argentina_container_code) !== '') {
            $this->optionalString(
                $container->argentina_container_code,
                "{$prefix}: código de características del contenedor",
                4
            );
        }

        $this->containerValidityData($container, $prefix);
    }

    private function writeTitle(XMLWriter $writer, BillOfLading $bill): void
    {
        $writer->startElement('TituloDesconsolidador');

        $this->writeOptionalDate($writer, 'FechaEmbarque', $bill->loading_date);
        $this->writeOptionalString($writer, 'CodigoPuertoEmbarque', $bill->loadingPort?->code, 5);

        if (trim((string) ($bill->origin_location ?? '')) !== '') {
            $writer->writeElement('FechaCargaLugarOrigen', $this->formatDate($bill->origin_loading_date));
            $writer->writeElement('LugarOrigen', (string) $bill->origin_location);
            $writer->writeElement('CodigoPaisLugarOrigen', (string) $bill->origin_country_code);
        }

        $writer->writeElement('NumeroConocimiento', (string) $bill->bill_number);
        $this->writeOptionalString(
            $writer,
            'CodigoPuertoTrasbordo',
            $bill->transshipmentPort?->code,
            5
        );
        $this->writeOptionalString($writer, 'CodigoPuertoDescarga', $bill->dischargePort?->code, 5);
        $this->writeOptionalDate($writer, 'FechaDescarga', $bill->discharge_date);
        $writer->writeElement('CodigoPaisDestino', (string) $bill->destination_country_code);
        $writer->writeElement('MarcaBultos', $this->billMarks($bill));
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

        $writer->writeElement(
            'PosicionArancelaria',
            $this->tariffPositionForBill($bill)
        );
        $writer->writeElement(
            'IndicadorOperadorLogisticoSeguro',
            $this->singleItemFlag(
                $bill,
                'is_secure_logistics_operator',
                'operador logístico seguro'
            )
        );
        $writer->writeElement(
            'IndicadorTransitoMonitoreado',
            $this->singleItemFlag($bill, 'is_monitored_transit', 'tránsito monitoreado')
        );
        $writer->writeElement(
            'IndicadorRenar',
            $this->singleItemFlag($bill, 'is_renar', 'RENAR')
        );

        $this->writeOptionalString(
            $writer,
            'RazonSocialFowarderExterior',
            $this->forwarderNameForBill($bill),
            70
        );
        $this->writeOptionalString(
            $writer,
            'IndicadorTributarioForwarderExterior',
            $this->singleItemValue(
                $bill,
                'foreign_forwarder_tax_id',
                'identificador tributario del agente de carga del exterior',
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
                'código de país del agente de carga del exterior',
                3,
                false
            ),
            3
        );

        $this->writeOptionalString(
            $writer,
            'CodigoLugarOperativoDescarga',
            $bill->operational_discharge_code,
            5
        );
        $this->writeOptionalString(
            $writer,
            'CodigoAduanaDescarga',
            $bill->discharge_customs_code,
            3
        );

        $this->writeMerchandise($writer, $bill);
        $this->writeContainers($writer, $bill);

        $writer->writeElement(
            'IdentificadorTituloMadre',
            $this->masterTitleIdentifier($bill)
        );
        $writer->endElement();
    }

    private function writeMerchandise(XMLWriter $writer, BillOfLading $bill): void
    {
        $writer->startElement('Mercaderias');

        foreach ($bill->shipmentItems->sortBy('line_number') as $item) {
            $packagingCode = $this->packagingCodeForItem(
                $item,
                "Conocimiento {$bill->bill_number}: Línea {$item->line_number}"
            );
            $manifestedQuantity = $packagingCode === '05'
                ? $item->containers->count()
                : $item->package_quantity;

            $writer->startElement('LineaMercaderia');
            $writer->writeElement('NumeroLinea', (string) ((int) $item->line_number));
            $writer->writeElement('CodigoEmbalaje', $packagingCode);
            $writer->writeElement(
                'CondicionContenedor',
                $this->containerCondition(
                    $item->container_condition,
                    "Conocimiento {$bill->bill_number}: línea {$item->line_number}, condición H/P"
                )
            );
            $writer->writeElement(
                'CantidadManifestada',
                $this->integerString($manifestedQuantity)
            );
            $writer->writeElement(
                'PesoVolumenManifestado',
                $this->decimalString($item->gross_weight_kg)
            );
            $writer->writeElement('DescripcionMercaderia', (string) $item->item_description);
            $writer->writeElement(
                'NumeroBultos',
                $this->packageMarksForItem($item)
            );
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
            $writer->startElement('Contenedor');

            if ($container->operator_client_id) {
                $writer->writeElement(
                    'CuitAtaOperadorContenedor',
                    $this->containerOperatorCuit($container, "Contenedor {$container->container_number}")
                );
            }

            // Sólo el código AFIP explícito de la unidad. No se deriva del tipo
            // genérico porque no es posible distinguir una clasificación interna
            // de un dato declarado para esta transmisión.
            $this->writeOptionalString(
                $writer,
                'CaracteristicasContenedor',
                $container->argentina_container_code,
                4
            );

            $writer->writeElement('IdentificadorContenedor', (string) $container->container_number);
            $writer->writeElement(
                'CondicionContenedor',
                $this->containerConditionForBill($container, $bill)
            );

            // Tara y PesoBruto permanecen disponibles para la gestión empresarial,
            // pero DESC no los transmite porque hoy pueden provenir tanto de una
            // fuente real como de defaults operativos históricos y el modelo no
            // registra esa procedencia. Ambos son optativos para ATA-DESC.

            $shipperSeal = trim((string) $container->shipper_seal);
            if (
                $shipperSeal !== ''
                && mb_strlen($shipperSeal) <= 35
            ) {
                $writer->writeElement(
                    'NumeroPrecintoOrigen',
                    $shipperSeal
                );
            }

            [$expiry, $acep] = $this->containerValidityData(
                $container,
                "Conocimiento {$bill->bill_number}: Contenedor {$container->container_number}"
            );

            if ($expiry !== null) {
                $writer->writeElement(
                    'FechaVencimientoContenedor',
                    $this->formatDate($expiry)
                );
            } elseif ($acep !== null) {
                $writer->writeElement('Acep', $acep);
            }

            $this->writeOptionalString(
                $writer,
                'CodigoAduana',
                $bill->discharge_customs_code,
                3
            );
            $this->writeOptionalString(
                $writer,
                'CodigoLugarOperativoDescarga',
                $bill->operational_discharge_code,
                5
            );
            $writer->endElement();
        }

        $writer->endElement();
    }

    /**
     * Regla confirmada por el cliente (02/10/2026):
     * si el contenedor no trae vencimiento CSC ni ACEP, la fecha a informar
     * es FechaArribo del viaje + 480 días.
     *
     * Se conserva cualquier CSC/ACEP real ya cargado y no se usa expiry_date
     * genérico, porque ese campo puede tener otra procedencia.
     *
     * @return array{0:mixed,1:?string}
     */
    private function containerValidityData(
        Container $container,
        string $prefix
    ): array {
        $acep = $this->optionalString(
            $container->acep,
            "{$prefix}: ACEP",
            20
        );

        if (
            $acep !== null
            && !preg_match('/^[A-Za-z0-9]+$/', $acep)
        ) {
            throw new Exception(
                "{$prefix}: ACEP sólo puede contener letras y números."
            );
        }

        $expiry = $container->csc_expiry_date;

        if ($expiry && $acep !== null) {
            throw new Exception(
                "{$prefix}: informá la fecha de vencimiento CSC o el código ACEP, pero no ambos a la vez."
            );
        }

        if (!$expiry && $acep === null) {
            $arrival = $this->voyage instanceof Voyage
                ? $this->voyage->estimated_arrival_date
                : null;

            if (!$arrival) {
                throw new Exception(
                    "{$prefix}: no se pudo determinar el vencimiento del contenedor. "
                    . "No hay vencimiento CSC ni ACEP informado, y falta la fecha de arribo para calcular los 480 días. Revisá la documentación del contenedor y solicitá la actualización del dato antes de transmitir."
                );
            }

            $expiry = $arrival->copy()->addDays(480);
        }

        if ($expiry) {
            $this->formatDate($expiry);
        }

        return [$expiry, $acep];
    }

    private function packagingCodeForItem($item, string $prefix): string
    {
        $explicit = trim((string) $item->packaging_code);
        if ($explicit !== '') {
            return $this->requiredString(
                $explicit,
                "{$prefix}: código de embalaje",
                2
            );
        }

        if ($item->containers->isNotEmpty()) {
            // Contrato ATA-DESC / wgesinformacionanticipada:
            // 05 representa mercadería contenedorizada.
            return '05';
        }

        return $this->requiredString(
            $item->packagingType?->argentina_ws_code,
            "{$prefix}: código de embalaje",
            2
        );
    }

    private function packageMarksForItem($item): string
    {
        $marks = trim((string) $item->cargo_marks);
        if ($marks === '') {
            $marks = trim((string) $item->package_numbers);
        }

        return $marks;
    }

    /**
     * Criterio operativo confirmado por Roberto: el forwarder exterior es
     * el cargador. Se respeta un valor explícito por línea y, si falta,
     * se usa la razón social real del shipper del conocimiento.
     */
    private function forwarderNameForBill(BillOfLading $bill): ?string
    {
        $explicit = $this->singleItemValue(
            $bill,
            'foreign_forwarder_name',
            'razón social del agente de carga del exterior',
            70,
            false
        );

        if ($explicit !== null && $explicit !== '') {
            return $explicit;
        }

        return $this->optionalString(
            $bill->shipper?->legal_name,
            "Conocimiento {$bill->bill_number}: razón social del agente de carga del exterior",
            70
        );
    }

    private function billMarks(BillOfLading $bill): string
    {
        $marks = trim((string) $bill->cargo_marks);
        if ($marks !== '') {
            return $marks;
        }

        $itemMarks = $bill->shipmentItems
            ->map(fn ($item) => $this->packageMarksForItem($item))
            ->filter(fn ($value) => $value !== '')
            ->unique()
            ->values();

        if ($itemMarks->count() > 1) {
            throw new Exception(
                "Conocimiento {$bill->bill_number}: las marcas de los bultos deben coincidir en todas las líneas."
            );
        }

        return (string) ($itemMarks->first() ?? '');
    }

    private function billContainers(BillOfLading $bill): Collection
    {
        return $bill->shipmentItems
            ->flatMap(fn ($item) => $item->containers)
            ->unique('id')
            ->values();
    }

    private function containerConditionForBill(
        Container $container,
        BillOfLading $bill
    ): string {
        $conditions = $bill->shipmentItems
            ->filter(fn ($item) => $item->containers->contains('id', $container->id))
            ->map(fn ($item) => strtoupper(trim((string) $item->container_condition)))
            ->filter(fn ($condition) => $condition !== '')
            ->unique()
            ->values();

        if ($conditions->isEmpty()) {
            throw new Exception(
                "Conocimiento {$bill->bill_number}: el contenedor {$container->container_number} no tiene una condición H/P válida en sus líneas vinculadas."
            );
        }

        if ($conditions->count() > 1) {
            throw new Exception(
                "Conocimiento {$bill->bill_number}: el contenedor {$container->container_number} está vinculado a líneas con condiciones H/P diferentes."
            );
        }

        return $this->containerCondition(
            $conditions->first(),
            "Conocimiento {$bill->bill_number}: contenedor {$container->container_number}, condición H/P"
        );
    }

    private function containerOperatorCuit(Container $container, string $prefix): string
    {
        if (!$container->operator_client_id) {
            throw new Exception("{$prefix}: falta identificar al operador del contenedor.");
        }

        $taxId = Client::query()
            ->whereKey($container->operator_client_id)
            ->value('tax_id');
        $clean = preg_replace('/\D+/', '', (string) $taxId);

        if (strlen($clean) !== 11) {
            throw new Exception("{$prefix}: el CUIT del operador del contenedor debe tener 11 dígitos. Revisá sus datos en el catálogo.");
        }

        return $clean;
    }

    private function singleItemFlag(BillOfLading $bill, string $field, string $label): string
    {
        $value = strtoupper((string) $this->singleItemValue($bill, $field, $label, 1, true));
        if (!in_array($value, ['S', 'N'], true)) {
            throw new Exception("Conocimiento {$bill->bill_number}: seleccioná Sí o No para {$label}.");
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
                "Conocimiento {$bill->bill_number}: {$label} debe tener el mismo valor en todas las líneas."
            );
        }

        $value = $values->first();
        if ($required && ($value === null || $value === '')) {
            throw new Exception("Conocimiento {$bill->bill_number}: falta completar {$label}.");
        }
        if ($value !== null && mb_strlen($value) > $maxLength) {
            throw new Exception("Conocimiento {$bill->bill_number}: {$label} supera {$maxLength} caracteres.");
        }

        return $value;
    }

    private function consigneeDocumentType(BillOfLading $bill): ?string
    {
        $explicit = $this->singleItemValue(
            $bill,
            'consignee_document_type',
            'tipo de documento del destinatario',
            4,
            false
        );

        if ($explicit !== null) {
            return $explicit;
        }

        // El tipo documental local no se deduce de un registro extranjero.
        if (strtoupper((string) $bill->consignee?->country?->alpha2_code) !== 'AR') {
            return null;
        }

        return $this->optionalString(
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
            'identificación del destinatario para Aduana',
            11,
            false
        );

        // La identificación extranjera no se transforma en un documento ARCA.
        $value = $explicit;
        if ($value === null
            && strtoupper((string) $bill->consignee?->country?->alpha2_code) === 'AR') {
            $value = $bill->consignee?->tax_id;
        }

        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        // Sólo normalizar separadores de un identificador realmente numérico.
        if (!preg_match('/^[0-9.\s-]+$/', (string) $value)) {
            throw new Exception("Conocimiento {$bill->bill_number}: la identificación del destinatario informada para Aduana debe ser numérica.");
        }

        $clean = preg_replace('/\D+/', '', (string) $value);
        if ($clean === '' || strlen($clean) > 11) {
            throw new Exception("Conocimiento {$bill->bill_number}: la identificación del destinatario no cumple el formato requerido para Aduana.");
        }

        return $clean;
    }

    private function validateTransactionId(string $transactionId): void
    {
        if ($transactionId === '' || mb_strlen($transactionId) > 20) {
            throw new Exception('La referencia de transacción es obligatoria y admite hasta 20 caracteres.');
        }
    }

    private function validateVoyageIdentifier(): void
    {
        $identifier = trim((string) ($this->voyage->argentina_voyage_id ?? ''));
        if ($identifier === '') {
            throw new Exception(
                'Falta el identificador de viaje AFIP necesario para procesar este expediente.'
            );
        }
        if (mb_strlen($identifier) > 16) {
            throw new Exception('El identificador de viaje AFIP admite hasta 16 caracteres.');
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
        $writer->endElement();
        $writer->endElement();
        $writer->endElement();
        $writer->endDocument();
    }

    private function writeAuthentication(XMLWriter $writer, array $auth): void
    {
        $cuit = preg_replace('/\D+/', '', (string) $this->company->tax_id);
        if (strlen($cuit) !== 11) {
            throw new Exception('El CUIT de la empresa conectada debe tener 11 dígitos. Revisá su configuración.');
        }

        $writer->startElement('argWSAutenticacionEmpresa');
        $writer->writeElement('Token', $auth['token']);
        $writer->writeElement('Sign', $auth['sign']);
        $writer->writeElement('CuitEmpresaConectada', $cuit);
        $writer->writeElement('TipoAgente', 'TRSP');
        $writer->writeElement('Rol', 'TRSP');
        $writer->endElement();
    }

    protected function getWsaaTokens(): array
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

        $certificateManager = new CertificateManagerService($this->company, ['country' => 'AR']);
        $certificate = $certificateManager->readCertificate();
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
            'certificate_used' => $this->company->certificate_path
                ?: $this->company->getCertificatePathForCountry('argentina'),
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
            throw new Exception('No fue posible preparar los archivos de autenticación con Aduana. Consultá al administrador.');
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
            throw new Exception('La autenticación con Aduana no devolvió una respuesta completa. Consultá al administrador.');
        }

        $xml = simplexml_load_string((string) $response->loginCmsReturn);
        if ($xml === false || empty($xml->credentials->token) || empty($xml->credentials->sign)) {
            throw new Exception('La respuesta de autenticación con Aduana no contiene las credenciales requeridas. Consultá al administrador.');
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

    private function exactLengthString(
        $value,
        string $label,
        int $length
    ): string {
        $text = $this->requiredString($value, $label, $length);

        if (mb_strlen($text) !== $length) {
            throw new Exception(
                "{$label} debe tener {$length} caracteres."
            );
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

        if ($value === 1 || $value === '1') {
            return 'S';
        }

        if ($value === 0 || $value === '0') {
            return 'N';
        }

        return strtoupper(trim((string) ($value ?? '')));
    }

    private function containerCondition($value, string $label): string
    {
        $code = strtoupper(trim((string) ($value ?? '')));
        if (!in_array($code, ['H', 'P'], true)) {
            throw new Exception("{$label} debe ser H o P según ATA-DESC.");
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
                'La fecha indicada no tiene un formato válido para Aduana: ' . (string) $value,
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
            throw new Exception("{$label} supera {$maxDigits} dígitos.");
        }

        return $text;
    }

    private function requiredDecimal($value, string $label): string
    {
        if ($value === null || $value === '' || !is_numeric($value)) {
            throw new Exception("Falta o es inválido el campo obligatorio {$label}.");
        }

        $number = (float) $value;
        if ($number < 0) {
            throw new Exception("{$label} no puede ser negativo.");
        }

        return $this->decimalString($value);
    }

    private function decimalString($value): string
    {
        if (!is_numeric($value)) {
            throw new Exception('El valor decimal informado para Aduana debe ser numérico.');
        }

        $text = number_format((float) $value, 3, '.', '');
        return rtrim(rtrim($text, '0'), '.');
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
