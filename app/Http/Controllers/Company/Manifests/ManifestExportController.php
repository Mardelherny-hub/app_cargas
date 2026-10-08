<?php

namespace App\Http\Controllers\Company\Manifests;

use App\Http\Controllers\Controller;
use App\Models\BillOfLading;
use App\Models\Container;
use App\Models\ShipmentItem;
use App\Models\Voyage;
use App\Services\Exports\LoginXmlManifestExporter;
use Illuminate\Support\Facades\Response;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;

class ManifestExportController extends Controller
{
    private const PARANA_COLUMNS = [
        'LOCATION_NAME', 'ADDRESS_LINE1', 'ADDRESS_LINE2', 'ADDRESS_LINE3',
        'CITY', 'ZIP', 'COUNTRY_NAME', 'TELEPHONE_NO', 'FAX_NO', 'EMAIL_ID',
        'MANIFEST_TYPE', 'BARGE_ID', 'BARGE_NAME', 'VOYAGE_NO', 'BL_NUMBER',
        'BL_DATE', 'POL', 'POL_TERMINAL', 'POD', 'POD_TERMINAL',
        'FREIGHT_TERMS', 'SHIPPER_NAME', 'SHIPPER_ADDRESS1',
        'SHIPPER_ADDRESS2', 'SHIPPER_ADDRESS3', 'SHIPPER_CITY', 'SHIPPER_ZIP',
        'SHIPPER_COUNTRY', 'SHIPPER_PHONE', 'SHIPPER_FAX', 'CONSIGNEE_NAME',
        'CONSIGNEE_ADDRESS1', 'CONSIGNEE_ADDRESS2', 'CONSIGNEE_ADDRESS3',
        'CONSIGNEE_CITY', 'CONSIGNEE_ZIP', 'CONSIGNEE_COUNTRY',
        'CONSIGNEE_PHONE', 'CONSIGNEE_FAX', 'NOTIFY_PARTY_NAME',
        'NOTIFY_PARTY_ADDRESS1', 'NOTIFY_PARTY_ADDRESS2',
        'NOTIFY_PARTY_ADDRESS3', 'NOTIFY_PARTY_CITY', 'NOTIFY_PARTY_ZIP',
        'NOTIFY_PARTY_COUNTRY', 'NOTIFY_PARTY_PHONE', 'NOTIFY_PARTY_FAX',
        'PFD', 'CONTAINER_NUMBER', 'CONTAINER_TYPE', 'CONTAINER_STATUS',
        'SEAL_NO', 'PACK_TYPE', 'NUMBER_OF_PACKAGES', 'GROSS_WEIGHT',
        'NET_WEIGHT', 'TARE_WEIGHT', 'VOLUME', 'REMARKS',
        'MARKS_DESCRIPTION', 'DESCRIPTION', 'IMO_NUMBER', 'UN_NUMBER',
        'FLASH_POINT', 'TEMP_MAX', 'TEMP_MIN', 'NCM', 'REMARKS1', 'REMARKS2',
        'REMARKS3', 'MLO_BL_NR', 'PERMISO',
    ];

    private const GUARAN_COLUMNS = [
        'LOCATION_NAME', 'ADDRESS_LINE1', 'ADDRESS_LINE2', 'ADDRESS_LINE3',
        'CITY', 'ZIP', 'COUNTRY_NAME', 'TELEPHONE_NO', 'FAX_NO', 'EMAIL_ID',
        'MANIFEST_TYPE', 'BARGE_ID', 'BARGE_NAME', 'VOYAGE_NO', 'BL_NUMBER',
        'BL_DATE', 'POL', 'POL_TERMINAL', 'POD', 'POD_TERMINAL',
        'FREIGHT_TERMS', 'SHIPPER_NAME', 'SHIPPER_ADDRESS1',
        'SHIPPER_ADDRESS2', 'SHIPPER_ADDRESS3', 'SHIPPER_CITY', 'SHIPPER_ZIP',
        'SHIPPER_COUNTRY', 'SHIPPER_PHONE', 'SHIPPER_FAX', 'CONSIGNEE_NAME',
        'CONSIGNEE_ADDRESS1', 'CONSIGNEE_ADDRESS2', 'CONSIGNEE_ADDRESS3',
        'CONSIGNEE_CITY', 'CONSIGNEE_ZIP', 'CONSIGNEE_COUNTRY',
        'CONSIGNEE_PHONE', 'CONSIGNEE_FAX', 'NOTIFY_PARTY_NAME',
        'NOTIFY_PARTY_ADDRESS1', 'NOTIFY_PARTY_ADDRESS2',
        'NOTIFY_PARTY_ADDRESS3', 'NOTIFY_PARTY_CITY', 'NOTIFY_PARTY_ZIP',
        'NOTIFY_PARTY_COUNTRY', 'NOTIFY_PARTY_PHONE', 'NOTIFY_PARTY_FAX',
        'PFD', 'CONTAINER_NUMBER', 'CONTAINER_TYPE', 'CONTAINER_STATUS',
        'SEAL_NO', 'PACK_TYPE', 'NUMBER_OF_PACKAGES', 'GROSS_WEIGHT',
        'NET_WEIGHT', 'TARE_WEIGHT', 'VOLUME', 'REMARKS',
        'MARKS_DESCRIPTION', 'DESCRIPTION', 'IMO_NUMBER', 'UN_NUMBER',
        'FLASH_POINT', 'TEMP_MAX', 'TEMP_MIN', 'NCM', 'REMARKS1', 'REMARKS2',
        'REMARKS3', 'MLO_BL_NR',
    ];

    public function index()
    {
        $voyages = Voyage::with([
            'shipments.billsOfLading',
            'originPort',
            'destinationPort',
        ])
            ->where('company_id', $this->getAuthenticatedCompanyId())
            ->whereHas('shipments')
            ->where('status', '!=', 'cancelled')
            ->latest()
            ->paginate(15);

        return view('company.manifests.export', compact('voyages'));
    }

    public function exportParana($voyageId)
    {
        $voyage = $this->getVoyageForExport($voyageId);

        try {
            $path = $this->generateCarrierSpreadsheet($voyage, 'parana');

            return response()
                ->download(
                    $path,
                    'PARANA_' . $this->safeFilePart($voyage->voyage_number) . '.xlsx'
                )
                ->deleteFileAfterSend(true);
        } catch (\Throwable $e) {
            return back()->with(
                'error',
                'Error generando archivo PARANA: ' . $e->getMessage()
            );
        }
    }

    public function exportGuaran($voyageId)
    {
        $voyage = $this->getVoyageForExport($voyageId);

        try {
            $path = $this->generateCarrierSpreadsheet($voyage, 'guaran');

            return response()
                ->download(
                    $path,
                    'GUARAN_' . $this->safeFilePart($voyage->voyage_number) . '.xlsx'
                )
                ->deleteFileAfterSend(true);
        } catch (\Throwable $e) {
            return back()->with(
                'error',
                'Error generando archivo Guaran: ' . $e->getMessage()
            );
        }
    }

    public function exportLogin($voyageId)
    {
        $voyage = $this->getVoyageForExport($voyageId);

        try {
            $xmlContent = (new LoginXmlManifestExporter())->generate($voyage);
            $filename = 'LOGIN_' . $this->safeFilePart($voyage->voyage_number) . '.xml';

            return Response::make($xmlContent, 200, [
                'Content-Type' => 'application/xml; charset=Windows-1252',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]);
        } catch (\Throwable $e) {
            return back()->with(
                'error',
                'Error generando archivo Login XML: ' . $e->getMessage()
            );
        }
    }

    public function exportTfp($voyageId)
    {
        $voyage = $this->getVoyageForExport($voyageId);

        try {
            $content = $this->generateTfpText($voyage);
            $filename = 'TFP_' . $this->safeFilePart($voyage->voyage_number) . '.txt';

            return Response::make($content, 200, [
                'Content-Type' => 'text/plain; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]);
        } catch (\Throwable $e) {
            return back()->with(
                'error',
                'Error generando archivo TFP: ' . $e->getMessage()
            );
        }
    }

    public function exportEdi($voyageId)
    {
        $voyage = $this->getVoyageForExport($voyageId);

        try {
            $content = $this->generateEdiCuscar($voyage);
            $filename = 'CUSCAR_' . $this->safeFilePart($voyage->voyage_number) . '.edi';

            return Response::make($content, 200, [
                'Content-Type' => 'text/plain; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]);
        } catch (\Throwable $e) {
            return back()->with(
                'error',
                'Error generando archivo EDI/CUSCAR: ' . $e->getMessage()
            );
        }
    }

    private function getVoyageForExport($voyageId): Voyage
    {
        return Voyage::with([
            'shipments.vessel.owner',
            'leadVessel.owner',
            'shipments.billsOfLading.shipper.contactData',
            'shipments.billsOfLading.shipper.country',
            'shipments.billsOfLading.consignee.contactData',
            'shipments.billsOfLading.consignee.country',
            'shipments.billsOfLading.notifyParty.contactData',
            'shipments.billsOfLading.notifyParty.country',
            'shipments.billsOfLading.specificContacts',
            'shipments.billsOfLading.loadingPort.country',
            'shipments.billsOfLading.dischargePort.country',
            'shipments.billsOfLading.shipmentItems.cargoType',
            'shipments.billsOfLading.shipmentItems.packagingType',
            'shipments.billsOfLading.shipmentItems.containers.containerType',
            'shipments.billsOfLading.shipmentItems.containers.operatorClient',
            'originPort.country',
            'destinationPort.country',
            'company',
        ])
            ->where('company_id', $this->getAuthenticatedCompanyId())
            ->findOrFail($voyageId);
    }

    private function getAuthenticatedCompanyId(): int
    {
        $company = auth()->user()?->getUserCompany();

        if (!$company) {
            abort(403, 'No tiene una empresa asignada.');
        }

        return (int) $company->id;
    }

    private function generateCarrierSpreadsheet(Voyage $voyage, string $format): string
    {
        $columns = $format === 'parana'
            ? self::PARANA_COLUMNS
            : self::GUARAN_COLUMNS;

        $rows = $this->buildCarrierRows($voyage, $columns);

        if ($rows === []) {
            throw new RuntimeException(
                'El viaje no contiene conocimientos con mercadería para exportar.'
            );
        }

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(strtoupper($format));

        if ($format === 'parana') {
            $sheet->fromArray($columns, null, 'A1');
            $sheet->setCellValue('A1', 'LOCATION NAME');

            $metadata = array_fill_keys($columns, '');
            $metadata['LOCATION_NAME'] = 'PARANA';
            $metadata['BARGE_ID'] = $this->voyageVessel($voyage)->registration_number ?? '';
            $metadata['BARGE_NAME'] = $this->voyageVessel($voyage)->name;
            $metadata['VOYAGE_NO'] = $this->requiredText(
                $voyage->voyage_number,
                'Número de viaje'
            );
            $metadata['POL'] = $this->portCode(
                $voyage->originPort,
                'puerto de origen'
            );
            $metadata['POD'] = $this->portCode(
                $voyage->destinationPort,
                'puerto de destino'
            );

            $sheet->fromArray(
                array_values($metadata),
                null,
                'A2'
            );

            $startRow = 3;
        } else {
            $sheet->setCellValue('A1', 'EDI TO CUSTOM - GUARAN');
            $sheet->fromArray($columns, null, 'A6');
            $startRow = 7;
        }

        foreach ($rows as $index => $row) {
            $sheet->fromArray(
                array_map(
                    static fn (string $column) => $row[$column] ?? '',
                    $columns
                ),
                null,
                'A' . ($startRow + $index)
            );
        }

        $path = tempnam(sys_get_temp_dir(), $format . '-export-');

        if ($path === false) {
            throw new RuntimeException(
                'No se pudo crear el archivo temporal de exportación.'
            );
        }

        $xlsxPath = $path . '.xlsx';
        @unlink($path);

        try {
            (new Xlsx($spreadsheet))->save($xlsxPath);
        } finally {
            $spreadsheet->disconnectWorksheets();
        }

        return $xlsxPath;
    }

    private function buildCarrierRows(Voyage $voyage, array $columns): array
    {
        $rows = [];

        foreach ($voyage->shipments as $shipment) {
            $vessel = $shipment->vessel ?: $this->voyageVessel($voyage);

            foreach ($shipment->billsOfLading as $bill) {
                $this->validateCarrierBill($bill, $vessel);

                foreach ($bill->shipmentItems as $item) {
                    $description = $this->requiredText(
                        $item->item_description,
                        "Descripción del ítem {$item->line_number} del BL {$bill->bill_number}"
                    );

                    $containers = $item->containers->isEmpty()
                        ? collect([null])
                        : $item->containers;

                    foreach ($containers as $container) {
                        $rows[] = $this->buildCarrierRow(
                            $voyage,
                            $bill,
                            $item,
                            $container,
                            $vessel,
                            $description,
                            $columns
                        );
                    }
                }
            }
        }

        return $rows;
    }

    private function buildCarrierRow(
        Voyage $voyage,
        BillOfLading $bill,
        ShipmentItem $item,
        ?Container $container,
        $vessel,
        string $description,
        array $columns
    ): array {
        $row = array_fill_keys($columns, '');

        $shipper = $bill->getShipperCompleteData();
        $consignee = $bill->getConsigneeCompleteData();
        $notify = $bill->getNotifyPartyCompleteData();

        $loadingPort = $bill->loadingPort ?: $voyage->originPort;
        $dischargePort = $bill->dischargePort ?: $voyage->destinationPort;

        $row['LOCATION_NAME'] = $voyage->company?->legal_name
            ?: $voyage->company?->commercial_name
            ?: '';
        $row['COUNTRY_NAME'] = $voyage->company?->country ?? '';
        $row['BARGE_ID'] = $vessel->registration_number ?? '';
        $row['BARGE_NAME'] = $vessel->name;
        $row['VOYAGE_NO'] = $voyage->voyage_number;
        $row['BL_NUMBER'] = $bill->bill_number;
        $row['BL_DATE'] = $bill->bill_date->format('Y-m-d');
        $row['POL'] = $this->portCode($loadingPort, 'puerto de carga');
        $row['POD'] = $this->portCode($dischargePort, 'puerto de descarga');
        $row['FREIGHT_TERMS'] = $bill->freight_terms ?? '';

        $row['SHIPPER_NAME'] = $shipper['company_name'];
        $row['SHIPPER_ADDRESS1'] = $this->partyAddressWithTax($shipper);
        $row['SHIPPER_COUNTRY'] = $bill->shipper?->country?->name ?? '';
        $row['SHIPPER_PHONE'] = $shipper['phone'] ?? '';

        $row['CONSIGNEE_NAME'] = $consignee['company_name'];
        $row['CONSIGNEE_ADDRESS1'] = $this->partyAddressWithTax($consignee);
        $row['CONSIGNEE_COUNTRY'] = $bill->consignee?->country?->name ?? '';
        $row['CONSIGNEE_PHONE'] = $consignee['phone'] ?? '';

        if ($bill->notifyParty || !empty($bill->notify_party_text)) {
            $row['NOTIFY_PARTY_NAME'] = $notify['company_name'] ?? '';
            $row['NOTIFY_PARTY_ADDRESS1'] = $this->partyAddressWithTax($notify);
            $row['NOTIFY_PARTY_COUNTRY'] = $bill->notifyParty?->country?->name ?? '';
            $row['NOTIFY_PARTY_PHONE'] = $notify['phone'] ?? '';
        }

        if ($container) {
            $typeCode = trim((string) $container->containerType?->code);

            if ($typeCode === '') {
                throw new RuntimeException(
                    "El contenedor {$container->container_number} no tiene tipo informado."
                );
            }

            $row['CONTAINER_NUMBER'] = $container->container_number;
            $row['CONTAINER_TYPE'] = $typeCode;
            $row['CONTAINER_STATUS'] = $container->condition === 'V' ? 'E' : 'F';
            $row['SEAL_NO'] = implode(' / ', $this->containerSeals($container));
            $row['TARE_WEIGHT'] = $this->numericText($container->tare_weight_kg);
        }

        $pivot = $container?->pivot;
        $row['PACK_TYPE'] = $this->packagingText($item);
        $row['NUMBER_OF_PACKAGES'] = $this->numericText(
            $pivot?->package_quantity ?? $item->package_quantity
        );
        $row['GROSS_WEIGHT'] = $this->numericText(
            $pivot?->gross_weight_kg ?? $item->gross_weight_kg
        );
        $row['NET_WEIGHT'] = $this->numericText(
            $pivot?->net_weight_kg ?? $item->net_weight_kg
        );
        $row['VOLUME'] = $this->numericText(
            $pivot?->volume_m3 ?? $item->volume_m3
        );
        $row['REMARKS'] = $item->comments ?? '';
        $row['MARKS_DESCRIPTION'] = $item->cargo_marks ?? '';
        $row['DESCRIPTION'] = $description;
        $row['UN_NUMBER'] = $item->un_number ?? '';
        $row['TEMP_MAX'] = $this->numericText($item->temperature_max);
        $row['TEMP_MIN'] = $this->numericText($item->temperature_min);
        $row['NCM'] = $item->commodity_code ?: $item->tariff_position ?: '';
        $row['MLO_BL_NR'] = $bill->master_bill_number ?? '';

        if (array_key_exists('PERMISO', $row)) {
            $row['PERMISO'] = $bill->permiso_embarque
                ?: $item->permit_number
                ?: '';
        }

        return $row;
    }

    private function validateCarrierBill(BillOfLading $bill, $vessel): void
    {
        $this->requiredText($bill->bill_number, 'Número de conocimiento');

        if (!$bill->bill_date) {
            throw new RuntimeException(
                "El BL {$bill->bill_number} no tiene fecha de emisión."
            );
        }

        if (!$vessel || trim((string) $vessel->name) === '') {
            throw new RuntimeException(
                "El BL {$bill->bill_number} no tiene buque asociado."
            );
        }

        if (!$bill->shipper) {
            throw new RuntimeException(
                "El BL {$bill->bill_number} no tiene cargador."
            );
        }

        if (!$bill->consignee) {
            throw new RuntimeException(
                "El BL {$bill->bill_number} no tiene consignatario."
            );
        }

        if ($bill->shipmentItems->isEmpty()) {
            throw new RuntimeException(
                "El BL {$bill->bill_number} no tiene ítems de mercadería."
            );
        }
    }

    private function generateTfpText(Voyage $voyage): string
    {
        $lines = ['**INICIO ARCHIVO**'];

        foreach ($voyage->shipments as $shipment) {
            $vessel = $shipment->vessel ?: $this->voyageVessel($voyage);

            if (!$vessel || trim((string) $vessel->name) === '') {
                throw new RuntimeException(
                    "El viaje {$voyage->voyage_number} no tiene buque para exportar TFP."
                );
            }

            foreach ($shipment->billsOfLading as $bill) {
                if ($bill->shipmentItems->isEmpty()) {
                    throw new RuntimeException(
                        "El BL {$bill->bill_number} no tiene ítems para exportar TFP."
                    );
                }

                $shipper = $this->requiredParty($bill, 'shipper');
                $consignee = $this->requiredParty($bill, 'consignee');
                $notify = $bill->getNotifyPartyCompleteData();

                $shipperTfp = $this->tfpPartyData($bill->shipper, $shipper);
                $consigneeTfp = $this->tfpPartyData($bill->consignee, $consignee);
                $notifyTfp = $this->tfpPartyData($bill->notifyParty, $notify);

                $loadingPort = $bill->loadingPort ?: $voyage->originPort;
                $dischargePort = $bill->dischargePort ?: $voyage->destinationPort;

                $transportDescription = trim((string) (
                    $vessel->owner?->legal_name
                    ?: $vessel->owner?->commercial_name
                    ?: ''
                ));

                $lines[] = '  **BL**';
                $lines[] = $this->tfpLine('BLNUMERO', $bill->bill_number);
                $lines[] = $this->tfpLine(
                    'BLMARITIMONUMERO',
                    $bill->master_bill_number ?? ''
                );
                $lines[] = $this->tfpLine('TRB', $bill->permiso_embarque ?? '');
                $lines[] = $this->tfpLine('ADUANADESTINO', $bill->discharge_customs_code ?? '');
                $lines[] = $this->tfpLine('BUQUE', $vessel->name);
                $lines[] = $this->tfpLine('CIAAEREA', '');
                $lines[] = $this->tfpLine(
                    'CONSOLIDADO',
                    (int) $bill->getRawOriginal('is_consolidated') === 1 ? 'S' : 'N'
                );
                $lines[] = $this->tfpLine('DESCTRANSP', $transportDescription);
                $lines[] = $this->tfpLine(
                    'CONSIGNATARIO',
                    $consignee['company_name']
                );
                $lines[] = $this->tfpLine(
                    'CONSIGNATARIODOMICILIO',
                    $consigneeTfp['address']
                );
                $lines[] = $this->tfpLine(
                    'CONSIGNATARIORUC',
                    $consigneeTfp['structured_ruc']
                );
                $lines[] = $this->tfpLine(
                    'CARGADOR',
                    $shipper['company_name']
                );
                $lines[] = $this->tfpLine(
                    'CARGADORDOMICILIO',
                    $shipperTfp['address']
                );
                $lines[] = $this->tfpLine(
                    'CARGADORRUC',
                    $shipperTfp['structured_ruc']
                );

                $notifyName = ($notify['company_name'] ?? '') === 'No aplica'
                    ? ''
                    : ($notify['company_name'] ?? '');

                $lines[] = $this->tfpLine('NOTIFICATARIO', $notifyName);
                $lines[] = $this->tfpLine(
                    'NOTIFICATARIODOMICILIO',
                    $notifyName !== '' ? $notifyTfp['address'] : ''
                );
                $lines[] = $this->tfpLine(
                    'NOTIFICATARIORUC',
                    $notifyName !== ''
                        ? $notifyTfp['structured_ruc']
                        : ''
                );
                $lines[] = $this->tfpLine('FRACCIONADO', $bill->is_fractional ? 'S' : 'N');
                // Todos los archivos TFP reales disponibles usan 8MTR para transporte acuático.
                $lines[] = $this->tfpLine('MEDIOTRANSP', '8MTR');
                $lines[] = $this->tfpLine('CODPAISORIGEN', $bill->origin_country_code ?? '');
                $lines[] = $this->tfpLine('PAISORIGEN', '');
                $lines[] = $this->tfpLine('CODPUERTOORIGEN', '');
                $lines[] = $this->tfpLine('PUERTOORIGEN', '');
                $lines[] = $this->tfpLine('PAISPUERTO', '');
                $lines[] = $this->tfpLine('PAISTRASBORDO', '');
                $lines[] = $this->tfpLine(
                    'CODPUERTOCARGA',
                    $this->portCode($loadingPort, 'puerto de carga TFP')
                );
                $lines[] = $this->tfpLine(
                    'PUERTOCARGA',
                    $this->requiredText($loadingPort?->name, 'Nombre puerto de carga TFP')
                );
                $lines[] = $this->tfpLine(
                    'CODPUERTODESCARGA',
                    $this->portCode($dischargePort, 'puerto de descarga TFP')
                );
                $lines[] = $this->tfpLine(
                    'PUERTODESCARGA',
                    $this->requiredText(
                        $dischargePort?->name,
                        'Nombre puerto de descarga TFP'
                    )
                );
                $lines[] = $this->tfpLine('CODTERMINALCARGA', '');
                $lines[] = $this->tfpLine('TERMINALCARGA', '');
                $lines[] = $this->tfpLine('CODTERMINALDESCARGA', '');
                $lines[] = $this->tfpLine('TERMINALDESCARGA', '');
                $lines[] = $this->tfpLine(
                    'CODLUGAROPERATIVA',
                    $bill->operational_discharge_code ?? ''
                );
                $lines[] = $this->tfpLine('LUGAROPERATIVA', '');
                $lines[] = $this->tfpLine('BARCAZA', '');
                // ENTRANSITO aparece vacío en todos los archivos TFP de referencia disponibles.
                // No se reutiliza el indicador aduanero tránsito/transbordo porque no se confirmó
                // que represente el mismo concepto en este formato.
                $lines[] = $this->tfpLine('ENTRANSITO', '');

                $lines[] = '    **CONTENEDORES**';
                foreach ($this->uniqueBillContainers($bill) as $container) {
                    $storedType = $this->requiredText(
                        $container->containerType?->code,
                        "Tipo del contenedor {$container->container_number}"
                    );
                    $type = match (strtoupper($storedType)) {
                        '20GP' => '20DV',
                        '40GP' => '40DV',
                        default => $storedType,
                    };

                    $operationalCondition = strtoupper(trim((string) $container->condition));
                    if ($operationalCondition === 'V') {
                        $condition = 'V';
                    } elseif (in_array($container->container_condition, ['H', 'P'], true)) {
                        $condition = $container->container_condition;
                    } elseif (in_array($operationalCondition, ['D', 'S', 'L', 'R'], true)) {
                        $condition = $operationalCondition;
                    } else {
                        $condition = '';
                    }

                    $operatorName = trim((string) (
                        $container->operatorClient?->commercial_name
                        ?: $container->operatorClient?->legal_name
                        ?: ''
                    ));
                    $lines[] = $this->tfpLine('CONDICION', $condition);
                    $lines[] = $this->tfpLine('LINEA', $operatorName);
                    $lines[] = $this->tfpLine('TIPO', $type);
                    $lines[] = $this->tfpLine('MEDIDA', $type);
                    $lines[] = $this->tfpLine(
                        'TARA',
                        $this->numericText($container->tare_weight_kg)
                    );
                    $lines[] = $this->tfpLine(
                        'TEMPERATURA',
                        $this->numericText($container->set_temperature)
                    );

                    $reeferActivo = data_get(
                        $container->webservice_data,
                        'tfp.reefer_activo'
                    );
                    if ($reeferActivo !== null) {
                        $lines[] = $this->tfpLine(
                            'REEFERACTIVO',
                            $reeferActivo
                        );
                    }

                    $tfpSeals = preg_replace(
                        '/\s*,\s*/',
                        ' ',
                        implode(' ', $this->containerSeals($container))
                    );
                    $lines[] = $this->tfpLine('NROPRECINTA', $tfpSeals);
                    $lines[] = $this->tfpLine(
                        'NUMERO',
                        $container->container_number
                    );
                    // TFP declara PESO y CANTIDAD por contenedor.
                    // El pivote conserva esos valores del archivo fuente; si el
                    // peso no está en el pivote se usa el peso bruto real del
                    // contenedor. Nunca se copia el total del ítem a cada caja.
                    $containerGross = $container->pivot?->gross_weight_kg;
                    if ($containerGross === null) {
                        $containerGross = $container->current_gross_weight_kg;
                    }
                    $containerPackages = $container->pivot?->package_quantity;

                    $lines[] = $this->tfpLine(
                        'PESO',
                        $containerGross !== null
                            ? $this->numericText($containerGross)
                            : ''
                    );
                    $lines[] = $this->tfpLine(
                        'CANTIDAD',
                        $containerPackages !== null
                            ? $this->numericText($containerPackages)
                            : ''
                    );
                    $lines[] = $this->tfpLine(
                        'OBS',
                        $bill->permiso_embarque ?? ''
                    );
                }
                $lines[] = '    **FIN CONTENEDORES**';

                $lines[] = '    **LINEAS**';
                $billHasContainers = $this->uniqueBillContainers($bill)->isNotEmpty();

                foreach ($bill->shipmentItems as $item) {
                    $description = $this->requiredText(
                        $item->item_description,
                        "Descripción TFP del BL {$bill->bill_number}"
                    );

                    $normalizedDescription = mb_strtoupper(trim($description));
                    $isEmptyCargo = in_array(
                        $normalizedDescription,
                        ['VACIO', 'VACIOS'],
                        true
                    );

                    $packages = $item->package_quantity;
                    $gross = $item->gross_weight_kg;
                    $volume = $item->volume_m3;

                    if (!$isEmptyCargo) {
                        if (!is_numeric($packages) || (float) $packages <= 0) {
                            throw new RuntimeException(
                                "El ítem {$item->line_number} del BL {$bill->bill_number} "
                                . 'no tiene cantidad de bultos válida para TFP.'
                            );
                        }

                        if (!is_numeric($gross) || (float) $gross <= 0) {
                            throw new RuntimeException(
                                "El ítem {$item->line_number} del BL {$bill->bill_number} "
                                . 'no tiene peso bruto válido para TFP.'
                            );
                        }
                    }

                    // TFP tiene un bloque de mercadería por ítem. Los contenedores
                    // se declaran aparte y no deben multiplicar bultos/peso del ítem.
                    // Si la fuente preservó una asociación explícita CONTENEDOR, se
                    // vuelve a emitir; de lo contrario queda vacío.
                    $sourceContainerRaw = data_get(
                        $item->webservice_data,
                        'tfp.contenedor'
                    );
                    $sourceContainer = trim((string) ($sourceContainerRaw ?? ''));

                    if (
                        $sourceContainer !== ''
                        && !$item->containers->contains(
                            fn ($container) => strcasecmp(
                                (string) $container->container_number,
                                $sourceContainer
                            ) === 0
                        )
                    ) {
                        throw new RuntimeException(
                            "El ítem {$item->line_number} del BL {$bill->bill_number} "
                            . "declara el contenedor {$sourceContainer}, pero no está asociado."
                        );
                    }

                    $lines[] = $this->tfpLine('CANTPARCIALBULTOS', $packages);
                    $lines[] = $this->tfpLine('CANTTOTALBULTOS', $packages);
                    $lines[] = $this->tfpLine(
                        'CODARMONIZADO',
                        $item->commodity_code ?: $item->tariff_position ?: ''
                    );
                    $lines[] = $this->tfpLine(
                        'NATURALEZAMERCADERIA',
                        $description
                    );
                    $lines[] = $this->tfpLine(
                        'OBS',
                        $item->permit_number ?: $bill->permiso_embarque ?: ''
                    );
                    $lines[] = $this->tfpLine('PESOPARCIALBULTOS', $gross);
                    $lines[] = $this->tfpLine('PESOTOTALBULTOS', $gross);
                    $lines[] = $this->tfpLine('VOLUMENPARCIAL', $volume);
                    $lines[] = $this->tfpLine('VOLUMENTOTAL', $volume);
                    $lines[] = $this->tfpLine(
                        'TIPOEMBALAJE',
                        $this->packagingText($item)
                    );
                    $lines[] = $this->tfpLine(
                        'MERCADERIASUELTA',
                        $billHasContainers ? 'NO' : 'SI'
                    );
                    $lines[] = $this->tfpLine(
                        'ESCOMBUSTIBLE',
                        data_get($item->webservice_data, 'tfp.es_combustible') ?? ''
                    );
                    $lines[] = $this->tfpLine(
                        'IMDG',
                        $item->imdg_class ?: $item->cargoType?->imdg_class ?: ''
                    );
                    if ($sourceContainerRaw !== null) {
                        $lines[] = $this->tfpLine('CONTENEDOR', $sourceContainer);
                    }
                }
                $lines[] = '    **FIN LINEAS**';

                $lines[] = '  **FIN BL**';
            }
        }

        $lines[] = '**FIN ARCHIVO**';

        return implode("\n", $lines) . "\n";
    }

    private function generateEdiCuscar(Voyage $voyage): string
    {
        $vessel = $this->voyageVessel($voyage);
        $vesselName = $this->requiredText(
            $vessel?->name,
            'Buque para exportación CUSCAR'
        );
        $voyageNumber = $this->requiredText(
            $voyage->voyage_number,
            'Número de viaje CUSCAR'
        );
        $originCode = $this->portCode($voyage->originPort, 'puerto de carga CUSCAR');
        $destinationCode = $this->portCode(
            $voyage->destinationPort,
            'puerto de descarga CUSCAR'
        );

        $allBills = $voyage->shipments
            ->flatMap(fn ($shipment) => $shipment->billsOfLading)
            ->values();

        if ($allBills->isEmpty()) {
            throw new RuntimeException(
                'El viaje no contiene conocimientos para exportar CUSCAR.'
            );
        }

        $equipment = $this->collectEdiEquipment($allBills);

        if ($equipment === []) {
            throw new RuntimeException(
                'CUSCAR requiere contenedores asociados a los ítems.'
            );
        }

        $sender = $voyage->company?->tax_id
            ?: $voyage->company?->legal_name
            ?: '';
        $carrier = $voyage->company?->legal_name
            ?: $voyage->company?->commercial_name
            ?: '';
        $flag = $vessel?->flagCountry?->alpha2_code ?? '';
        $control = now()->format('YmdHis');

        $segments = [];
        $segments[] = "UNB+UNOA:2+"
            . $this->ediText($sender)
            . "++"
            . now()->format('ymd:Hi')
            . "+"
            . $control
            . "'";

        $message = [];
        $message[] = "UNH+1+CUSCAR:D:96B:UN'";
        $message[] = "BGM+85+" . $this->ediReference($voyageNumber) . "+9'";
        $message[] = "DTM+137:" . now()->format('YmdHi') . ":203'";

        if (!$voyage->estimated_arrival_date) {
            throw new RuntimeException(
                "El viaje {$voyageNumber} no tiene fecha estimada de llegada "
                . 'requerida por CUSCAR.'
            );
        }

        $message[] = "DTM+132:"
            . $voyage->estimated_arrival_date->format('Ymd')
            . ":102'";

        if ($voyage->departure_date) {
            $message[] = "DTM+136:"
                . $voyage->departure_date->format('Ymd')
                . ":102'";
        }

        $message[] = "TDT+20+"
            . $this->ediReference($voyageNumber)
            . "+1++"
            . $this->ediText($carrier)
            . "+++:103::"
            . $this->ediText($vesselName)
            . ":"
            . $this->ediText($flag)
            . "'";
        $message[] = "LOC+9+" . $this->ediReference($originCode) . "::139'";
        $message[] = "LOC+60+" . $this->ediReference($destinationCode) . "::139'";

        foreach ($equipment as $entry) {
            /** @var Container $container */
            $container = $entry['container'];
            $message[] = "EQD+CN+"
                . $this->ediReference($container->container_number)
                . "+"
                . $this->ediReference($entry['iso_code'])
                . "::5+2+3+"
                . ($container->condition === 'V' ? '4' : '5')
                . "'";

            if ($container->tare_weight_kg !== null) {
                $message[] = "MEA+AAE+T+KGM:"
                    . $this->numericText($container->tare_weight_kg)
                    . "'";
            }

            if ($entry['vgm'] !== null) {
                $message[] = "MEA+AAE+VGM+KGM:"
                    . $this->numericText($entry['vgm'])
                    . "'";
            }

            foreach ($this->ediContainerSeals($container) as $seal) {
                if ($seal['issuer'] === '') {
                    throw new RuntimeException(
                        "El precinto {$seal['number']} del contenedor {$container->container_number} "
                        . 'no tiene emisor informado (SH/CU/CA/otro código). CUSCAR no se exportará inventando ese rol.'
                    );
                }

                $message[] = 'SEL+'
                    . $this->ediText($seal['number'])
                    . '+'
                    . $this->ediText($seal['issuer'])
                    . "'";
            }

            if ($container->set_temperature !== null) {
                $message[] = "TMP+2+"
                    . $this->numericText($container->set_temperature)
                    . ":CEL'";
            }
        }

        foreach ($allBills as $billIndex => $bill) {
            $billNumber = $this->ediReference(
                $this->requiredText($bill->bill_number, 'Número de BL CUSCAR')
            );

            if ($bill->shipmentItems->isEmpty()) {
                throw new RuntimeException(
                    "El BL {$bill->bill_number} no tiene ítems para exportar CUSCAR."
                );
            }

            $message[] = 'CNI+' . ($billIndex + 1) . "++'";
            $message[] = 'RFF+BM:' . $billNumber . "'";

            $billLoadingPort = $bill->loadingPort ?: $voyage->originPort;
            $billDischargePort = $bill->dischargePort ?: $voyage->destinationPort;
            $message[] = 'LOC+9+'
                . $this->ediReference(
                    $this->portCode($billLoadingPort, 'puerto de carga del BL CUSCAR')
                )
                . "::139'";
            $message[] = 'LOC+11+'
                . $this->ediReference(
                    $this->portCode($billDischargePort, 'puerto de descarga del BL CUSCAR')
                )
                . "::139'";

            if ($bill->permiso_embarque) {
                $message[] = 'RFF+EP:'
                    . $this->ediReference($bill->permiso_embarque)
                    . "'";
            }

            if ($bill->gross_weight_kg !== null) {
                $message[] = 'MEA+AAX+G+KGM:'
                    . $this->numericText($bill->gross_weight_kg)
                    . "'";
            }

            $this->appendEdiParty($message, 'CN', $bill->getConsigneeCompleteData());
            $this->appendEdiParty($message, 'CZ', $bill->getShipperCompleteData());

            $notify = $bill->getNotifyPartyCompleteData();
            if (($notify['company_name'] ?? '') !== 'No aplica') {
                $this->appendEdiParty($message, 'CX', $notify);
            }

            foreach ($bill->shipmentItems as $itemIndex => $item) {
                if ($item->containers->isEmpty()) {
                    throw new RuntimeException(
                        "El ítem {$item->line_number} del BL {$bill->bill_number} "
                        . 'no tiene contenedor asociado; CUSCAR no puede representarlo.'
                    );
                }

                $empty = $item->containers->every(
                    fn (Container $container) => $container->condition === 'V'
                );

                if (!$empty && $item->gross_weight_kg === null) {
                    throw new RuntimeException(
                        "El ítem {$item->line_number} del BL {$bill->bill_number} "
                        . 'no tiene peso bruto para CUSCAR.'
                    );
                }

                $packages = $item->package_quantity;

                if ($packages === null || !is_numeric($packages)) {
                    throw new RuntimeException(
                        "El ítem {$item->line_number} del BL {$bill->bill_number} "
                        . 'no tiene cantidad de bultos para CUSCAR.'
                    );
                }

                $packageCode = $item->packaging_code
                    ?: $item->packagingType?->code
                    ?: '';
                $packageDescription = $item->package_type_description
                    ?: $item->packagingType?->name
                    ?: '';

                $message[] = 'GID+'
                    . ($itemIndex + 1)
                    . '+'
                    . (int) $packages
                    . ':'
                    . $this->ediText($packageCode)
                    . ':::'
                    . $this->ediText($packageDescription)
                    . "'";

                if (trim((string) $item->item_description) !== '') {
                    $message[] = 'FTX+AAA+++'
                        . $this->ediText($item->item_description)
                        . "'";
                }

                if ($item->gross_weight_kg !== null) {
                    $message[] = 'MEA+AAE+G+KGM:'
                        . $this->numericText($item->gross_weight_kg)
                        . "'";
                }

                if ($item->volume_m3 !== null) {
                    $message[] = 'MEA+AAE+AAW+MTQ:'
                        . $this->numericText($item->volume_m3)
                        . "'";
                }

                foreach ($item->containers as $container) {
                    $message[] = 'SGP+'
                        . $this->ediReference($container->container_number)
                        . "+'";
                }

                if ($item->is_dangerous_goods || $item->un_number) {
                    $message[] = 'DGS+IMD+'
                        . $this->ediText($item->imdg_class ?? '')
                        . '+'
                        . $this->ediText($item->un_number ?? '')
                        . "'";
                }

                if ($item->cargo_marks) {
                    $message[] = 'PCI++'
                        . $this->ediText($item->cargo_marks)
                        . "'";
                }

                $commodity = $item->commodity_code ?: $item->tariff_position;
                if ($commodity) {
                    $message[] = 'CST+1+'
                        . $this->ediText($commodity)
                        . "+:169'";
                }
            }
        }

        $message[] = 'UNT+' . (count($message) + 1) . "+1'";
        $segments = array_merge($segments, $message);
        $segments[] = 'UNZ+1+' . $control . "'";

        return implode("\n", $segments) . "\n";
    }

    private function collectEdiEquipment($bills): array
    {
        $equipment = [];

        foreach ($bills as $bill) {
            foreach ($bill->shipmentItems as $item) {
                foreach ($item->containers as $container) {
                    $number = trim((string) $container->container_number);

                    if ($number === '') {
                        throw new RuntimeException(
                            "El BL {$bill->bill_number} tiene un contenedor sin número."
                        );
                    }

                    $isoCode = trim((string) (
                        $container->containerType?->iso_code
                        ?: $container->containerType?->iso_size_type
                    ));

                    if ($isoCode === '') {
                        throw new RuntimeException(
                            "El contenedor {$number} no tiene código ISO para CUSCAR."
                        );
                    }

                    $vgm = $container->pivot?->verified_gross_mass_kg;
                    $vgm = $vgm !== null && (float) $vgm > 0
                        ? (float) $vgm
                        : null;

                    if (!isset($equipment[$number])) {
                        $equipment[$number] = [
                            'container' => $container,
                            'iso_code' => $isoCode,
                            'vgm_values' => [],
                        ];
                    } elseif (
                        strtoupper($equipment[$number]['iso_code'])
                        !== strtoupper($isoCode)
                    ) {
                        throw new RuntimeException(
                            "El contenedor {$number} aparece con más de un tipo ISO."
                        );
                    }

                    if ($vgm !== null) {
                        $equipment[$number]['vgm_values'][] = $vgm;
                    }
                }
            }
        }

        foreach ($equipment as $number => &$entry) {
            $values = array_values(array_unique(array_map(
                static fn ($value) => round((float) $value, 3),
                $entry['vgm_values']
            )));

            if (count($values) > 1) {
                throw new RuntimeException(
                    "El contenedor {$number} tiene más de un VGM asociado."
                );
            }

            $entry['vgm'] = $values[0] ?? null;
            unset($entry['vgm_values']);
        }
        unset($entry);

        return array_values($equipment);
    }

    private function appendEdiParty(array &$segments, string $role, array $party): void
    {
        $name = trim((string) ($party['company_name'] ?? ''));

        if ($name === '' || $name === 'Sin cliente asignado') {
            throw new RuntimeException(
                "CUSCAR requiere la parte {$role} con nombre informado."
            );
        }

        $address = trim((string) ($party['address'] ?? ''));
        $partyText = $this->ediText($name);

        if ($address !== '') {
            $partyText .= ':' . $this->ediText($address);
        }

        $segments[] = 'NAD+' . $role . '++' . $partyText . "'";

        $taxId = $this->digitsOnly($party['tax_id'] ?? '');
        if ($taxId !== '') {
            $segments[] = 'RFF+ADZ:' . $taxId . "'";
        }
    }

    private function uniqueBillContainers(BillOfLading $bill)
    {
        return $bill->shipmentItems
            ->flatMap(fn (ShipmentItem $item) => $item->containers)
            ->unique('container_number')
            ->values();
    }

    private function voyageVessel(Voyage $voyage)
    {
        return $voyage->shipments
            ->pluck('vessel')
            ->filter()
            ->first()
            ?: $voyage->leadVessel;
    }

    private function requiredParty(BillOfLading $bill, string $role): array
    {
        $party = match ($role) {
            'shipper' => $bill->getShipperCompleteData(),
            'consignee' => $bill->getConsigneeCompleteData(),
            default => [],
        };

        $name = trim((string) ($party['company_name'] ?? ''));

        if ($name === '' || $name === 'Sin cliente asignado') {
            throw new RuntimeException(
                "El BL {$bill->bill_number} no tiene {$role} informado."
            );
        }

        return $party;
    }

    private function tfpPartyData($client, array $party): array
    {
        $address = trim((string) ($party['address'] ?? ''));
        $taxId = $this->digitsOnly($party['tax_id'] ?? '');
        $country = strtoupper(trim((string) ($client?->country?->alpha2_code ?? '')));

        if ($taxId === '') {
            return [
                'address' => $address,
                'structured_ruc' => '',
            ];
        }

        if ($country === 'PY') {
            return [
                'address' => $address,
                'structured_ruc' => $taxId,
            ];
        }

        $identityText = trim(
            (string) ($party['company_name'] ?? '') . ' ' . $address
        );

        if (!str_contains($this->digitsOnly($identityText), $taxId)) {
            $label = match ($country) {
                'AR' => 'CUIT',
                'BR' => 'CNPJ',
                default => 'TAX ID',
            };

            $address = trim(
                $address
                . ($address !== '' ? ' - ' : '')
                . $label
                . ': '
                . $taxId
            );
        }

        return [
            'address' => $address,
            'structured_ruc' => '',
        ];
    }

    private function partyAddressWithTax(array $party): string
    {
        $parts = [];

        $address = trim((string) ($party['address'] ?? ''));
        if ($address !== '') {
            $parts[] = $address;
        }

        $taxId = $this->digitsOnly($party['tax_id'] ?? '');
        if ($taxId !== '') {
            $parts[] = 'TAX ID: ' . $taxId;
        }

        return implode(' - ', $parts);
    }

    private function containerSeals(Container $container): array
    {
        $seals = [];

        $source = $container->pivot?->source_seals;
        if (is_string($source) && trim($source) !== '') {
            $decoded = json_decode($source, true);
            if (is_array($decoded)) {
                $seals = array_merge($seals, $decoded);
            }
        } elseif (is_array($source)) {
            $seals = array_merge($seals, $source);
        }

        foreach ([
            $container->shipper_seal,
            $container->customs_seal,
            $container->carrier_seal,
        ] as $seal) {
            if (trim((string) $seal) !== '') {
                $seals[] = $seal;
            }
        }

        $seals = array_merge($seals, $this->additionalSealValues($container));

        return array_values(array_unique(array_filter(array_map(
            static fn ($seal) => trim((string) $seal),
            $seals
        ))));
    }

    private function ediContainerSeals(Container $container): array
    {
        $entries = [];

        $addSeal = static function (array &$entries, $number, $issuer = null): void {
            $number = trim((string) $number);
            $issuer = strtoupper(trim((string) ($issuer ?? '')));

            if ($number === '') {
                return;
            }

            if (!isset($entries[$number]) || ($entries[$number]['issuer'] === '' && $issuer !== '')) {
                $entries[$number] = [
                    'number' => $number,
                    'issuer' => $issuer,
                ];
            }
        };

        $source = $container->pivot?->source_seals;
        if (is_string($source) && trim($source) !== '') {
            $decoded = json_decode($source, true);
            $source = is_array($decoded) ? $decoded : [$source];
        }
        if (is_array($source)) {
            foreach ($source as $seal) {
                $addSeal($entries, $seal);
            }
        }

        $addSeal($entries, $container->shipper_seal, 'SH');
        $addSeal($entries, $container->customs_seal, 'CU');
        $addSeal($entries, $container->carrier_seal, 'CA');

        $additional = $container->additional_seals ?? [];
        if (is_array($additional)) {
            foreach ($additional as $seal) {
                if (is_array($seal)) {
                    $addSeal(
                        $entries,
                        $seal['seal_number'] ?? null,
                        $seal['issuer_code'] ?? null
                    );
                } else {
                    $addSeal($entries, $seal);
                }
            }
        }

        return array_values($entries);
    }

    private function additionalSealValues(Container $container): array
    {
        $raw = $container->additional_seals ?? [];

        if (!is_array($raw)) {
            return [];
        }

        $values = [];

        foreach ($raw as $seal) {
            $value = is_array($seal)
                ? ($seal['seal_number'] ?? null)
                : $seal;

            if (trim((string) $value) !== '') {
                $values[] = trim((string) $value);
            }
        }

        return array_values(array_unique($values));
    }

    private function packagingText(ShipmentItem $item): string
    {
        return trim((string) (
            $item->packaging_code
            ?: $item->package_type_description
            ?: $item->packagingType?->code
            ?: $item->packagingType?->name
            ?: ''
        ));
    }

    private function portCode($port, string $label): string
    {
        if (!$port) {
            throw new RuntimeException("Falta {$label}.");
        }

        return $this->requiredText(
            $port->code ?: $port->webservice_code,
            "Código de {$label}"
        );
    }

    private function requiredText($value, string $label): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            throw new RuntimeException("Falta {$label}.");
        }

        return $value;
    }

    private function numericText($value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if (!is_numeric($value)) {
            throw new RuntimeException(
                "Valor numérico inválido para exportación: {$value}"
            );
        }

        return rtrim(
            rtrim(number_format((float) $value, 3, '.', ''), '0'),
            '.'
        );
    }

    private function tfpLine(string $label, $value): string
    {
        $value = str_replace(
            ["\r", "\n", '/*', '*/'],
            [' ', ' ', '/ *', '* /'],
            (string) ($value ?? '')
        );

        return '      ' . $label . ': /*' . trim($value) . '*/';
    }

    private function ediText($value): string
    {
        $value = (string) ($value ?? '');

        return strtr($value, [
            '?' => '??',
            '+' => '?+',
            ':' => '?:',
            "'" => "?'",
        ]);
    }

    private function ediReference($value): string
    {
        $value = $this->requiredText($value, 'Referencia EDI');

        if (preg_match("/[:+'?]/", $value)) {
            throw new RuntimeException(
                "La referencia EDI '{$value}' contiene un carácter reservado no soportado."
            );
        }

        return $value;
    }

    private function digitsOnly($value): string
    {
        return preg_replace('/\D/', '', (string) ($value ?? '')) ?: '';
    }

    private function safeFilePart(string $value): string
    {
        $safe = preg_replace('/[^A-Za-z0-9._-]+/', '_', trim($value));

        return trim((string) $safe, '_') ?: 'viaje';
    }
}
