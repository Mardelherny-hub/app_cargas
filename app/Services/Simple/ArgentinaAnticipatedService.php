<?php

namespace App\Services\Simple;

use App\Models\Voyage;
use App\Models\Company;
use App\Models\User;
use App\Services\Webservice\SoapClientService;
use App\Services\Simple\SimpleXmlGenerator;
use App\Services\Simple\BaseWebserviceService;
use App\Models\WebserviceResponse;
use App\Models\WebserviceLog;
use App\Models\WebserviceError;
use Exception;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * SISTEMA MODULAR WEBSERVICES - ArgentinaAnticipatedService
 * 
 * Servicio para Información Anticipada Argentina AFIP
 * Extiende BaseWebserviceService para el webservice AFIP de Información Anticipada Marítima.
 * 
 * ESPECIFICACIONES AFIP:
 * - WSDL: https://wsaduhomoext.afip.gob.ar/DIAV2/wgesinformacionanticipada/wgesinformacionanticipada.asmx?wsdl
 * - Namespace: Ar.Gob.Afip.Dga.Org.wgesinformacionanticipada
 * - Métodos: RegistrarViaje, RectificarViaje, RegistrarTitulosCbc
 * 
 * FUNCIONALIDADES:
 * - Registro de viaje ATA MT (más simple que MIC/DTA)
 * - Rectificación de viaje
 * - Registro de títulos ATA CBC
 * - NO requiere TRACKs (diferencia con MIC/DTA)
 * - Datos de cabecera + embarcación + contenedores vacíos
 * 
 * REUTILIZA INFRAESTRUCTURA:
 * - BaseWebserviceService (validaciones, transacciones, logging)
 * - CertificateManagerService (certificados .p12 existentes)
 * - SimpleXmlGenerator (generación XML AFIP)
 * - Modelos existentes: Voyage, Shipment, Company
 */
class ArgentinaAnticipatedService
{
    private Company $company;
    private User $user;
    private SoapClientService $soapClient;
    private array $config;
    private ?int $currentTransactionId = null;

    public function __construct(Company $company, User $user, array $config = [])
    {
        $this->company = $company;
        $this->user = $user;
        $this->soapClient = new SoapClientService($company);
        $this->config = array_merge([
            'webservice_type' => 'anticipada',
            'country' => 'AR',
            'environment' => 'testing',
            'soap_action_registrar_viaje' => 'Ar.Gob.Afip.Dga.Org.wgesinformacionanticipada/RegistrarViaje',
            'soap_action_rectificar_viaje' => 'Ar.Gob.Afip.Dga.Org.wgesinformacionanticipada/RectificarViaje',
            'soap_action_registrar_titulos_cbc' => 'Ar.Gob.Afip.Dga.Org.wgesinformacionanticipada/RegistrarTitulosCbc',
            'timeout_seconds' => 60,
            'max_retries' => 3,
            'require_certificate' => true,
        ], $config);
    }

    /**
     * Validación local del contrato RegistrarViaje.
     * No autentica contra WSAA ni transmite a Aduana.
     */
    private function validateSpecificData(Voyage $voyage): array
    {
        $validation = ['errors' => [], 'warnings' => []];

        if (!$voyage || !$voyage->id) {
            $validation['errors'][] = 'Viaje no válido o no encontrado';
            return $validation;
        }

        $voyage->loadMissing([
            'leadVessel.flagCountry',
            'captain.documentCountry',
            'originPort.country',
            'destinationPort.country',
            'originPort.primaryCustomsOffice',
            'destinationPort.primaryCustomsOffice',
            'originCustoms',
            'destinationCustoms',
            'shipments.billsOfLading',
        ]);

        if ((int) $voyage->company_id !== (int) $this->company->id) {
            $validation['errors'][] = 'Viaje no pertenece a la empresa autenticada';
        }

        $vessel = $voyage->leadVessel;
        if (!$vessel) {
            $validation['errors'][] = 'IdentificadorMedioTransporte: falta embarcación líder';
        } else {
            $vesselIdentifier = trim((string) (
                $vessel->registration_number ?: $vessel->name
            ));
            if ($vesselIdentifier === '') {
                $validation['errors'][] = 'IdentificadorMedioTransporte es obligatorio';
            } elseif (mb_strlen($vesselIdentifier) > 40) {
                $validation['errors'][] = 'IdentificadorMedioTransporte supera 40 caracteres';
            }

            if (!$this->hasAfipCountryCode($vessel->flagCountry)) {
                $validation['errors'][] = 'La nacionalidad de la embarcación no tiene código PAY_PAIS de Aduana';
            }
        }

        if (!$voyage->originPort) {
            $validation['errors'][] = 'CodigoPuertoOrigen es obligatorio';
        } else {
            if (mb_strlen(trim((string) $voyage->originPort->code)) !== 5) {
                $validation['errors'][] = 'CodigoPuertoOrigen debe tener 5 caracteres POR_PAIS';
            }
            if (!$this->hasAfipCountryCode($voyage->originPort->country)) {
                $validation['errors'][] = 'CodigoPaisProcedencia no tiene código PAY_PAIS de Aduana';
            }
        }

        if (
            $voyage->destinationPort
            && $voyage->destinationPort->country
            && !$this->hasAfipCountryCode($voyage->destinationPort->country)
        ) {
            $validation['errors'][] = 'CodigoPaisFinViaje no tiene código PAY_PAIS de Aduana';
        }

        if (!$voyage->estimated_arrival_date) {
            $validation['errors'][] = 'FechaArribo es obligatoria para Información Anticipada';
        }

        foreach ([
            'IndicadorTransporteVacio' => $voyage->is_empty_transport,
            'IndicadorMercaderiaAbordo' => $voyage->has_cargo_onboard,
        ] as $label => $value) {
            if (!in_array(strtoupper(trim((string) $value)), ['S', 'N'], true)) {
                $validation['errors'][] = "{$label} debe ser S o N";
            }
        }

        $carrierName = trim((string) (
            $this->company->legal_name ?: $this->company->commercial_name
        ));
        if ($carrierName === '') {
            $validation['errors'][] = 'DesignacionTransportista es obligatoria';
        } elseif (mb_strlen($carrierName) > 35) {
            $validation['errors'][] = 'DesignacionTransportista supera 35 caracteres';
        }

        $companyCountry = \App\Models\Country::where(
            'alpha2_code',
            strtoupper(trim((string) $this->company->country))
        )->first();
        if (!$this->hasAfipCountryCode($companyCountry)) {
            $validation['errors'][] = 'CodigoPaisTransportista no tiene código PAY_PAIS de Aduana';
        }

        $argentinePort = null;
        $explicitCustoms = null;
        $operativeField = null;

        if ($voyage->originPort?->country?->alpha2_code === 'AR') {
            $argentinePort = $voyage->originPort;
            $explicitCustoms = $voyage->originCustoms;
            $operativeField = 'origin_operative_code';
        } elseif ($voyage->destinationPort?->country?->alpha2_code === 'AR') {
            $argentinePort = $voyage->destinationPort;
            $explicitCustoms = $voyage->destinationCustoms;
            $operativeField = 'operational_discharge_code';
        }

        if ($argentinePort) {
            $operativeCodes = $voyage->shipments
                ->flatMap(fn ($shipment) => $shipment->billsOfLading)
                ->pluck($operativeField)
                ->filter(fn ($value) => trim((string) $value) !== '')
                ->map(fn ($value) => trim((string) $value))
                ->unique()
                ->values();

            if ($operativeCodes->count() > 1) {
                $validation['errors'][] = 'El viaje contiene más de un lugar operativo argentino; no puede elegirse uno automáticamente';
            } elseif ($operativeCodes->count() === 1) {
                $location = \App\Models\AfipOperativeLocation::where(
                    'location_code',
                    $operativeCodes->first()
                )->where('is_active', true)->first();

                if (!$location) {
                    $validation['errors'][] = 'CodigoLugarOperativo no existe en el catálogo LOT_ADUA';
                } elseif (!preg_match('/^\\d{3}$/', (string) $location->customs_code)) {
                    $validation['errors'][] = 'El lugar operativo no tiene CodigoAduana BUR_DESC válido';
                }
            } elseif (!$this->resolveThreeDigitCustomsCode(
                $explicitCustoms,
                $argentinePort->primaryCustomsOffice
            )) {
                $validation['errors'][] = 'CodigoAduana BUR_DESC no puede resolverse sin inventar datos';
            }
        } else {
            $validation['errors'][] = 'El viaje no contiene un puerto argentino para informar CodigoAduana';
        }

        $invalidEmptyContainers = 0;
        $seenEmptyContainers = [];
        foreach ($voyage->shipments as $shipment) {
            foreach ($shipment->billsOfLading as $bill) {
                $bill->loadMissing([
                    'loadingPort',
                    'dischargePort',
                    'shipmentItems.containers.containerType',
                ]);

                foreach ($bill->shipmentItems as $item) {
                    foreach ($item->containers as $container) {
                        $condition = strtoupper(trim((string) (
                            $item->pivot?->container_condition
                            ?: $item->container_condition
                            ?: $container->container_condition
                        )));
                        if ($condition === '' && strtoupper(trim((string) $container->condition)) === 'V') {
                            $condition = 'V';
                        }
                        if (!in_array($condition, ['V', 'C'], true)) {
                            continue;
                        }

                        $key = trim((string) $container->container_number);
                        if ($key !== '' && isset($seenEmptyContainers[$key])) {
                            continue;
                        }
                        $seenEmptyContainers[$key] = true;

                        $type = trim((string) (
                            $container->containerType?->iso_code
                            ?: $container->containerType?->code
                        ));
                        $tare = $container->tare_weight_kg;
                        $gross = $container->current_gross_weight_kg
                            ?? $item->pivot?->gross_weight_kg;
                        $operative = trim((string) (
                            $bill->operational_discharge_code
                            ?: $item->operational_discharge_code
                        ));
                        $location = $operative !== ''
                            ? \App\Models\AfipOperativeLocation::where('location_code', $operative)
                                ->where('is_active', true)
                                ->first()
                            : null;

                        $validWeight = is_numeric($tare)
                            && is_numeric($gross)
                            && (float) $tare >= 0
                            && (float) $gross >= 0
                            && abs((float) $tare - round((float) $tare)) <= 0.000001
                            && abs((float) $gross - round((float) $gross)) <= 0.000001
                            && (float) $tare <= (float) $gross
                            && strlen((string) (int) round((float) $tare)) <= 10
                            && strlen((string) (int) round((float) $gross)) <= 14;

                        $validPorts = $condition !== 'V' || (
                            mb_strlen(trim((string) $bill->loadingPort?->code)) === 5
                            && mb_strlen(trim((string) $bill->dischargePort?->code)) === 5
                        );

                        if (
                            $key === '' || mb_strlen($key) > 20
                            || mb_strlen($type) !== 4
                            || !$validWeight
                            || !$location
                            || !preg_match('/^\d{3}$/', (string) $location?->customs_code)
                            || !$validPorts
                        ) {
                            $invalidEmptyContainers++;
                        }
                    }
                }
            }
        }

        if ($invalidEmptyContainers > 0) {
            $validation['errors'][] = "{$invalidEmptyContainers} contenedores vacíos/correo tienen datos obligatorios inválidos";
        }

        if ($voyage->captain_id && !$voyage->captain) {
            $validation['warnings'][] = 'El capitán asignado no pudo resolverse';
        }

        if ($voyage->captain) {
            if ($voyage->captain->document_type && mb_strlen((string) $voyage->captain->document_type) > 4) {
                $validation['errors'][] = 'TipoIdentificadorCapitan supera 4 caracteres';
            }
            if ($voyage->captain->document_number && mb_strlen((string) $voyage->captain->document_number) > 35) {
                $validation['errors'][] = 'NumeroIdentificadorCapitan supera 35 caracteres';
            }
        }

        if (
            $voyage->special_instructions
            && mb_strlen((string) $voyage->special_instructions) > 60
        ) {
            $validation['errors'][] = 'Comentario del viaje supera 60 caracteres';
        }

        $validation['errors'] = array_values(array_unique($validation['errors']));
        $validation['warnings'] = array_values(array_unique($validation['warnings']));

        return $validation;
    }

    private function hasAfipCountryCode($country): bool
    {
        if (!$country) {
            return false;
        }

        $afipCode = trim((string) ($country->codigo_afip ?? ''));
        if (preg_match('/^\\d{3}$/', $afipCode)) {
            return true;
        }

        $customsCode = trim((string) ($country->customs_code ?? ''));
        if (preg_match('/^\\d{3}$/', $customsCode)) {
            return true;
        }

        $numericCode = trim((string) ($country->numeric_code ?? ''));
        if ($numericCode !== '' && ctype_digit($numericCode)) {
            $numericCode = str_pad($numericCode, 3, '0', STR_PAD_LEFT);
        }

        return preg_match('/^\\d{3}$/', $numericCode) === 1;
    }

    private function resolveThreeDigitCustomsCode(...$customsSources): ?string
    {
        foreach ($customsSources as $source) {
            foreach ([
                $source?->webservice_code ?? null,
                $source?->code ?? null,
            ] as $candidate) {
                $digits = preg_replace('/\\D+/', '', (string) $candidate);
                if (strlen($digits) === 3) {
                    return $digits;
                }
            }
        }

        return null;
    }

    /**
     * Validar si el voyage puede ser procesado para Información Anticipada.
     */
    public function canProcessVoyage(Voyage $voyage): array
    {
        $validation = $this->validateSpecificData($voyage);
        $validation['can_process'] = empty($validation['errors']);

        return $validation;
    }

    private function validateTitlesData(Voyage $voyage, bool $closing = false): array
    {
        $validation = ['errors' => [], 'warnings' => []];

        if ((int) $voyage->company_id !== (int) $this->company->id) {
            $validation['errors'][] = 'Viaje no pertenece a la empresa autenticada';
        }

        $identifier = trim((string) $voyage->argentina_voyage_id);
        if (mb_strlen($identifier) !== 16) {
            $validation['errors'][] = 'IdentificadorViaje AFIP debe tener 16 caracteres';
        }

        $query = $voyage->billsOfLading()->with([
            'loadingPort.country',
            'dischargePort.country',
            'transshipmentPort.country',
            'shipmentItems.packagingType',
            'shipmentItems.cargoType',
            'shipmentItems.containers.containerType',
            'shipmentItems.containers.operatorClient',
        ]);

        if ($closing) {
            $query->whereHas('dischargePort.country', function ($q) {
                $q->where('alpha2_code', '!=', 'AR');
            });
        }

        $bills = $query->get();
        if ($bills->isEmpty()) {
            $validation['errors'][] = $closing
                ? 'No hay conocimientos con descarga fuera de Argentina para CerrarViaje'
                : 'No hay conocimientos para RegistrarTitulosCbc';
            return $validation;
        }

        $missingOrigin = 0;
        $missingOriginCountry = 0;
        $missingTitleMarks = 0;
        $missingForwarder = 0;
        $missingPackaging = 0;
        $missingLineMarks = 0;
        $invalidDescription = 0;
        $invalidWeight = 0;
        $invalidContainer = 0;
        $missingClosingOperator = 0;

        foreach ($bills as $bill) {
            $billNumber = trim((string) $bill->bill_number);
            $billLabel = $billNumber !== '' ? $billNumber : '#' . $bill->id;
            $items = $bill->shipmentItems;

            if ($billNumber === '') {
                $validation['errors'][] = "Conocimiento {$billLabel}: NumeroConocimiento es obligatorio";
            } elseif (mb_strlen($billNumber) > 18) {
                $validation['errors'][] = "Conocimiento {$billLabel}: NumeroConocimiento supera 18 caracteres";
            }

            if (!$bill->loading_date) {
                $validation['errors'][] = "Conocimiento {$billLabel}: falta FechaEmbarque";
            }
            if (!$bill->loadingPort || mb_strlen(trim((string) $bill->loadingPort?->code)) !== 5) {
                $validation['errors'][] = "Conocimiento {$billLabel}: CodigoPuertoEmbarque inválido";
            }
            if (!$bill->dischargePort || mb_strlen(trim((string) $bill->dischargePort?->code)) !== 5) {
                $validation['errors'][] = "Conocimiento {$billLabel}: CodigoPuertoDescarga inválido";
            }
            if (!$closing) {
                if (trim((string) $bill->origin_location) === '') {
                    $missingOrigin++;
                } elseif (mb_strlen((string) $bill->origin_location) > 50) {
                    $validation['errors'][] = "Conocimiento {$billLabel}: LugarOrigen supera 50 caracteres";
                }

                if (!$this->hasAfipCountryValue($bill->origin_country_code)) {
                    $missingOriginCountry++;
                }
            }

            $destinationCountry = trim((string) $bill->destination_country_code);
            if ($destinationCountry !== '') {
                if (!$this->hasAfipCountryValue($destinationCountry)) {
                    $validation['errors'][] = "Conocimiento {$billLabel}: CodigoPaisDestino no es PAY_PAIS válido";
                }
            } elseif (!$this->hasAfipCountryCode($bill->dischargePort?->country)) {
                $validation['errors'][] = "Conocimiento {$billLabel}: no puede resolverse CodigoPaisDestino";
            }

            $billMarks = trim((string) $bill->cargo_marks);
            $itemMarks = $items->pluck('cargo_marks')
                ->filter(fn ($value) => trim((string) $value) !== '')
                ->map(fn ($value) => trim((string) $value))
                ->unique()
                ->values();
            if ($billMarks === '' && $itemMarks->isEmpty()) {
                $missingTitleMarks++;
            } elseif ($billMarks === '' && $itemMarks->count() > 1) {
                $validation['errors'][] = "Conocimiento {$billLabel}: hay varias MarcaBultos y no existe una a nivel conocimiento";
            } elseif (mb_strlen($billMarks !== '' ? $billMarks : (string) $itemMarks->first()) > 80) {
                $validation['errors'][] = "Conocimiento {$billLabel}: MarcaBultos supera 80 caracteres";
            }

            $tariffs = $items->pluck('tariff_position')
                ->filter(fn ($value) => trim((string) $value) !== '')
                ->merge($items->pluck('commodity_code')->filter(fn ($value) => trim((string) $value) !== ''))
                ->map(fn ($value) => trim((string) $value))
                ->unique()
                ->values();
            $tariff = trim((string) $bill->commodity_code);
            if ($tariff === '') {
                if ($tariffs->count() !== 1) {
                    $validation['errors'][] = "Conocimiento {$billLabel}: PosicionArancelaria no puede determinarse inequívocamente";
                } else {
                    $tariff = (string) $tariffs->first();
                }
            }
            if ($tariff !== '' && mb_strlen($tariff) > 16) {
                $validation['errors'][] = "Conocimiento {$billLabel}: PosicionArancelaria supera 16 caracteres";
            }
            if (!$bill->is_consolidated && $tariff !== '' && (mb_strlen($tariff) < 7 || mb_strlen($tariff) > 15)) {
                $validation['errors'][] = "Conocimiento {$billLabel}: PosicionArancelaria debe tener 7 a 15 caracteres cuando no es consolidado";
            }

            $forwarders = $items->pluck('foreign_forwarder_name')
                ->filter(fn ($value) => trim((string) $value) !== '')
                ->map(fn ($value) => trim((string) $value))
                ->unique()
                ->values();
            if ($forwarders->isEmpty()) {
                $missingForwarder++;
            } elseif ($forwarders->count() > 1) {
                $validation['errors'][] = "Conocimiento {$billLabel}: RazonSocialFowarderExterior difiere entre ítems";
            } elseif (mb_strlen((string) $forwarders->first()) > 70) {
                $validation['errors'][] = "Conocimiento {$billLabel}: RazonSocialFowarderExterior supera 70 caracteres";
            }

            foreach ([
                'is_secure_logistics_operator' => 'IndicadorOperadorLogisticoSeguro',
                'is_monitored_transit' => 'IndicadorTransitoMonitoreado',
                'is_renar' => 'IndicadorRenar',
            ] as $field => $label) {
                $values = $items->pluck($field)
                    ->filter(fn ($value) => trim((string) $value) !== '')
                    ->map(fn ($value) => strtoupper(trim((string) $value)))
                    ->unique()
                    ->values();
                if ($values->count() !== 1 || !in_array((string) $values->first(), ['S', 'N'], true)) {
                    $validation['errors'][] = "Conocimiento {$billLabel}: {$label} debe ser único y S/N";
                }
            }

            if (!$closing) {
                $operativeValues = $items->pluck('operational_discharge_code')
                    ->filter(fn ($value) => trim((string) $value) !== '')
                    ->map(fn ($value) => trim((string) $value))
                    ->unique()
                    ->values();
                $operative = trim((string) $bill->operational_discharge_code);
                if ($operative === '') {
                    if ($operativeValues->count() !== 1) {
                        $validation['errors'][] = "Conocimiento {$billLabel}: CodigoLugarOperativoDescarga no puede determinarse inequívocamente";
                        $operative = '';
                    } else {
                        $operative = (string) $operativeValues->first();
                    }
                }

                if ($operative !== '') {
                    $location = \App\Models\AfipOperativeLocation::where('location_code', $operative)
                        ->where('is_active', true)
                        ->first();
                    if (!$location) {
                        $validation['errors'][] = "Conocimiento {$billLabel}: CodigoLugarOperativoDescarga {$operative} no existe en LOT_ADUA";
                    } else {
                        $providedCustoms = trim((string) $bill->discharge_customs_code);
                        if ($providedCustoms === '') {
                            $itemCustoms = $items->pluck('discharge_customs_code')
                                ->filter(fn ($value) => trim((string) $value) !== '')
                                ->map(fn ($value) => trim((string) $value))
                                ->unique()
                                ->values();
                            if ($itemCustoms->count() === 1) {
                                $providedCustoms = (string) $itemCustoms->first();
                            } elseif ($itemCustoms->count() > 1) {
                                $validation['errors'][] = "Conocimiento {$billLabel}: CodigoAduanaDescarga difiere entre ítems";
                            }
                        }
                        if ($providedCustoms !== '' && str_pad(preg_replace('/\\D+/', '', $providedCustoms), 3, '0', STR_PAD_LEFT) !== (string) $location->customs_code) {
                            $validation['errors'][] = "Conocimiento {$billLabel}: CodigoAduanaDescarga no corresponde a LOT_ADUA {$operative}";
                        }
                    }
                }
            }

            $seenContainers = [];
            $seenLines = [];
            foreach ($items as $itemIndex => $item) {
                $line = (int) ($item->line_number ?: ($itemIndex + 1));
                if ($line < 1 || $line > 999 || isset($seenLines[$line])) {
                    $validation['errors'][] = "Conocimiento {$billLabel}: NumeroLinea inválido o repetido";
                }
                $seenLines[$line] = true;

                $packagingCode = trim((string) $item->packaging_code);
                if (mb_strlen($packagingCode) !== 2) {
                    $missingPackaging++;
                }

                if ($item->package_quantity === null || !is_numeric($item->package_quantity) || (int) $item->package_quantity < 0 || (int) $item->package_quantity > 999999999) {
                    $validation['errors'][] = "Conocimiento {$billLabel}: CantidadManifestada inválida";
                }

                $weight = $item->gross_weight_kg;
                if ($weight === null || !is_numeric($weight) || (float) $weight < 0 || abs((float) $weight - round((float) $weight)) > 0.000001 || strlen((string) (int) round((float) $weight)) > 12) {
                    $invalidWeight++;
                }

                $description = trim((string) $item->item_description);
                if ($description === '' || mb_strlen($description) > 80) {
                    $invalidDescription++;
                }

                $lineMarks = trim((string) $item->cargo_marks);
                if ($lineMarks === '') {
                    $missingLineMarks++;
                } elseif (mb_strlen($lineMarks) > 100) {
                    $validation['errors'][] = "Conocimiento {$billLabel}: NumeroBultos supera 100 caracteres";
                }

                foreach ($item->containers as $container) {
                    $key = (string) $container->container_number;
                    if ($key !== '' && isset($seenContainers[$key])) {
                        continue;
                    }
                    $seenContainers[$key] = true;

                    $type = trim((string) ($container->containerType?->iso_code ?: $container->containerType?->code));
                    $condition = strtoupper(trim((string) ($item->pivot?->container_condition ?: $item->container_condition ?: $container->container_condition)));
                    if ($condition === '' && strtoupper(trim((string) $container->condition)) === 'V') {
                        $condition = 'V';
                    }
                    $tare = $container->tare_weight_kg;
                    $gross = $container->current_gross_weight_kg ?? $item->pivot?->gross_weight_kg;
                    $tareDigits = is_numeric($tare) ? strlen((string) (int) round((float) $tare)) : 99;
                    $grossDigits = is_numeric($gross) ? strlen((string) (int) round((float) $gross)) : 99;

                    if (
                        $key === '' || mb_strlen($key) > 20
                        || mb_strlen($type) !== 4
                        || !in_array($condition, ['H', 'P', 'V', 'C'], true)
                        || $tare === null || !is_numeric($tare)
                        || $gross === null || !is_numeric($gross)
                        || abs((float) $tare - round((float) $tare)) > 0.000001
                        || abs((float) $gross - round((float) $gross)) > 0.000001
                        || $tareDigits > 10
                        || $grossDigits > 14
                        || (float) $tare > (float) $gross
                    ) {
                        $invalidContainer++;
                    }

                    if ($closing) {
                        $operatorTaxId = preg_replace(
                            '/\\D+/',
                            '',
                            (string) ($container->operatorClient?->tax_id ?: data_get($container->webservice_data, 'operator_tax_id'))
                        );
                        if (strlen($operatorTaxId) !== 11) {
                            $missingClosingOperator++;
                        }
                    }
                }
            }
        }

        $summary = [
            [$missingOrigin, 'conocimientos sin LugarOrigen'],
            [$missingOriginCountry, 'conocimientos sin CodigoPaisLugarOrigen PAY_PAIS'],
            [$missingTitleMarks, 'conocimientos sin MarcaBultos'],
            [$missingForwarder, 'conocimientos sin RazonSocialFowarderExterior'],
            [$missingPackaging, 'líneas sin CodigoEmbalaje NEB_DESC de 2 caracteres'],
            [$missingLineMarks, 'líneas sin NumeroBultos'],
            [$invalidDescription, 'líneas con DescripcionMercaderia ausente o mayor a 80 caracteres'],
            [$invalidWeight, 'líneas con PesoVolumenManifestado inválido para Int(12)'],
            [$invalidContainer, 'contenedores con datos obligatorios inválidos'],
            [$missingClosingOperator, 'contenedores de cierre sin CuitAtaOperadorContenedor válido'],
        ];

        foreach ($summary as [$count, $message]) {
            if ($count > 0) {
                $validation['errors'][] = "{$count} {$message}";
            }
        }

        $validation['errors'] = array_values(array_unique($validation['errors']));
        return $validation;
    }

    private function hasAfipCountryValue($value): bool
    {
        $value = strtoupper(trim((string) $value));
        if ($value === '') {
            return false;
        }

        if (strlen($value) === 2) {
            $country = \App\Models\Country::where('alpha2_code', $value)->first();
            return $this->hasAfipCountryCode($country);
        }

        if (!preg_match('/^\\d{3}$/', $value)) {
            return false;
        }

        return \App\Models\Country::query()
            ->get()
            ->contains(function ($country) use ($value) {
                $codes = [
                    trim((string) ($country->codigo_afip ?? '')),
                    trim((string) ($country->customs_code ?? '')),
                    str_pad(trim((string) ($country->numeric_code ?? '')), 3, '0', STR_PAD_LEFT),
                ];

                return in_array($value, $codes, true);
            });
    }

    /**
     * Registrar operación en logs
     */
    private function logOperation(string $level, string $message, array $context = []): void
    {
        $context = array_merge([
            'service' => 'ArgentinaAnticipatedService',
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
        ], $context);

        match($level) {
            'debug' => \Log::debug($message, $context),
            'info' => \Log::info($message, $context),
            'warning' => \Log::warning($message, $context),
            'error' => \Log::error($message, $context),
            default => \Log::info($message, $context),
        };
    }

    /**
     * MÉTODO PRINCIPAL: RegistrarViaje - Registro de viaje ATA MT
     * 
     * Registra información anticipada del viaje completo con datos de cabecera,
     * embarcación, capitán y contenedores vacíos/correo.
     */
    public function registrarViaje(Voyage $voyage, array $options = []): array
    {
        $validation = $this->validateSpecificData($voyage);
        if (!empty($validation['errors'])) {
            return [
                'success' => false,
                'transaction_id' => null,
                'external_reference' => null,
                'error_message' => implode('; ', $validation['errors']),
                'validation_errors' => $validation['errors'],
                'warnings' => $validation['warnings'],
            ];
        }

        try {
            DB::beginTransaction();

            // Crear transacción
            $transaction = $this->createWebserviceTransaction($voyage, array_merge($options, [
                'method' => 'RegistrarViaje',
                'soap_action' => $this->config['soap_action_registrar_viaje'],
            ]));
            
            $this->currentTransactionId = $transaction->id;

            // Cargar relaciones necesarias
            $voyage->load([
                'company',
                'leadVessel',
                'captain',
                'originPort.country',
                'destinationPort.country',
                'shipments.vessel',
                'shipments.captain',
                'shipments.billsOfLading.shipmentItems.containers'
            ]);

            // Generar XML para RegistrarViaje
            $transactionId = $transaction->transaction_id;
            $xmlGenerator = new SimpleXmlGenerator($this->company, $this->config);
            $xmlContent = $xmlGenerator->createRegistrarViajeXml($voyage, $transactionId);

            if (!$xmlContent) {
                throw new Exception('Error generando XML para RegistrarViaje');
            }

            // Crear cliente SOAP
            $soapClient = $this->soapClient->createClient($this->config['webservice_type'], $this->config['environment']);


            // Enviar request SOAP
            $soapResult = $this->sendSoapRequest($transaction, $soapClient, $xmlContent, 'RegistrarViaje');

            // Usar el IdentificadorViaje ya extraído por sendSoapRequest()
            $voyageIdentifier = $soapResult['external_reference'] ?? null;

            if ($soapResult['success']) {
                $transaction->update([
                    'status' => 'success',
                    'external_reference' => $voyageIdentifier,
                    'completed_at' => now(),
                ]);
                
                // ✅ PERSISTIR ESTADO DEL WEBSERVICE
                $this->updateWebserviceStatus($voyage, 'sent', [
                    'transaction_id' => $transaction->id,
                    'external_reference' => $voyageIdentifier,
                ]);

                // ✅ ACTUALIZAR VOYAGE CON IdentificadorViaje DE AFIP
                $voyage->update([
                    'argentina_voyage_id' => $voyageIdentifier,
                ]);

                Log::info('WebserviceSimple [anticipada]: RegistrarViaje enviado exitosamente', [
                    'voyage_id' => $voyage->id,
                    'transaction_id' => $transaction->id,
                    'external_reference' => $voyageIdentifier,
                ]);
                
                DB::commit();
                
                return [
                    'success' => true,
                    'transaction_id' => $transaction->id,
                    'external_reference' => $voyageIdentifier,
                    'error_message' => null,
                ];
            } else {
                $errorMessage = $soapResult['error_message'] ?? 'Error desconocido en envío SOAP';
                
                $transaction->update([
                    'status' => 'error',
                    'error_message' => $errorMessage,
                ]);

                $this->updateWebserviceStatus($voyage, 'error', [
                    'last_error_at' => now(),
                    'last_error_message' => $errorMessage,
                ]);

                Log::info('🔍 DEBUG: Antes de commit', [
                    'transaction_id' => $transaction->id,
                    'has_request_xml' => isset($soapResult['request_xml']),
                    'has_response_xml' => isset($soapResult['response_xml']),
                    'request_xml_length' => isset($soapResult['request_xml']) ? strlen($soapResult['request_xml']) : 0,
                    'response_xml_length' => isset($soapResult['response_xml']) ? strlen($soapResult['response_xml']) : 0,
                ]);

                DB::commit();

                Log::info('🔍 DEBUG: Después de commit exitoso');
                
                return [
                    'success' => false,
                    'transaction_id' => $transaction->id,
                    'external_reference' => null,
                    'error_message' => $errorMessage,
                ];
            }

        } catch (Exception $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            
            $this->logOperation('error', 'Error en RegistrarViaje', [
                'error' => $e->getMessage(),
                'voyage_id' => $voyage->id,
            ]);

            return [
                'success' => false,
                'transaction_id' => $this->currentTransactionId,
                'external_reference' => null,
                'error_message' => $e->getMessage(),
            ];
        }
    }

    /**
     * RectificarViaje - Rectificación de viaje ATA MT
     */
    public function rectificarViaje(Voyage $voyage, array $options = []): array
    {
        $validation = $this->validateSpecificData($voyage);
        if (!empty($validation['errors'])) {
            return [
                'success' => false,
                'transaction_id' => null,
                'external_reference' => null,
                'error_message' => implode('; ', $validation['errors']),
                'validation_errors' => $validation['errors'],
                'warnings' => $validation['warnings'],
            ];
        }

        try {
            DB::beginTransaction();

            // Crear transacción
            $transaction = $this->createWebserviceTransaction($voyage, array_merge($options, [
                'method' => 'RectificarViaje',
                'soap_action' => $this->config['soap_action_rectificar_viaje'],
            ]));
            
            $this->currentTransactionId = $transaction->id;

            // Verificar que existe un viaje previo enviado
            $previousTransaction = $voyage->webserviceTransactions()
                ->where('webservice_type', 'anticipada')
                ->where('status', 'success')
                ->whereNotNull('external_reference')
                ->latest()
                ->first();

            if (!$previousTransaction) {
                throw new Exception('No se encontró un viaje previo enviado para rectificar');
            }

            // Cargar relaciones necesarias
            $voyage->load([
                'company',
                'leadVessel', 
                'captain',
                'originPort.country',
                'destinationPort.country',
                'shipments.vessel',
                'shipments.captain',
                'shipments.billsOfLading.shipmentItems.containers'
            ]);

            // Generar XML para RectificarViaje
            $transactionId = $transaction->transaction_id;
            $rectificationData = array_merge($options, [
                'original_external_reference' => $previousTransaction->external_reference,
            ]);
            
            $xmlGenerator = new SimpleXmlGenerator($this->company, $this->config);
            $xmlContent = $xmlGenerator->createRectificarViajeXml($voyage, $rectificationData, $transactionId);

            if (!$xmlContent) {
                throw new Exception('Error generando XML para RectificarViaje');
            }

            // Crear cliente SOAP y enviar
            $soapClient = $this->soapClient->createClient($this->config['webservice_type'], $this->config['environment']);
            $soapResult = $this->sendSoapRequest($transaction, $soapClient, $xmlContent, 'RectificarViaje');

            // Procesar respuesta
            // Procesar respuesta
            $voyageIdentifier = $soapResult['external_reference'] ?? null;

            if ($soapResult['success']) {
                $transaction->update([
                    'status' => 'success',
                    'external_reference' => $voyageIdentifier,
                    'completed_at' => now(),
                ]);
                
                // Actualizar estado del webservice
                $this->updateWebserviceStatus($voyage, 'sent', [
                    'transaction_id' => $transaction->id,
                    'external_reference' => $voyageIdentifier,
                ]);

                Log::info('RectificarViaje enviado exitosamente', [
                    'voyage_id' => $voyage->id,
                    'transaction_id' => $transaction->id,
                    'external_reference' => $voyageIdentifier,
                ]);
                
                DB::commit();
                $soapResult['transaction_id'] = $transaction->id;
                return $soapResult;
            } else {
                $transaction->update([
                    'status' => 'error',
                    'error_message' => $soapResult['error_message'] ?? 'Error desconocido',
                ]);

                $this->updateWebserviceStatus($voyage, 'error', [
                    'last_error_at' => now(),
                    'last_error_message' => $soapResult['error_message'] ?? 'Error desconocido',
                ]);

                DB::commit();
                $soapResult['transaction_id'] = $transaction->id;
                return $soapResult;
            } 

        } catch (Exception $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            
            $this->logOperation('error', 'Error en RectificarViaje', [
                'error' => $e->getMessage(),
                'voyage_id' => $voyage->id,
            ]);

            return [
                'success' => false,
                'error_message' => $e->getMessage(),
                'transaction_id' => $this->currentTransactionId,
            ];
        }
    }

    /**
     * RegistrarTitulosCbc - Registro de títulos ATA CBC
     */
    public function registrarTitulosCbc(Voyage $voyage, array $options = []): array
    {
        $validation = $this->validateTitlesData($voyage, false);
        if (!empty($validation['errors'])) {
            return [
                'success' => false,
                'transaction_id' => null,
                'external_reference' => $voyage->argentina_voyage_id,
                'error_message' => implode('; ', $validation['errors']),
                'validation_errors' => $validation['errors'],
            ];
        }

        try {
            DB::beginTransaction();

            // Crear transacción
            $transaction = $this->createWebserviceTransaction($voyage, array_merge($options, [
                'method' => 'RegistrarTitulosCbc',
                'soap_action' => $this->config['soap_action_registrar_titulos_cbc'],
            ]));
            
            $this->currentTransactionId = $transaction->id;

            // Cargar relaciones necesarias
            $voyage->load([
                'company',
                'leadVessel',
                'captain', 
                'originPort.country',
                'destinationPort.country',
                'shipments.vessel',
                'shipments.captain',
                'shipments.billsOfLading.shipmentItems.containers'
            ]);

            // Generar XML para RegistrarTitulosCbc
            $transactionId = $transaction->transaction_id;
            $xmlGenerator = new SimpleXmlGenerator($this->company, $this->config);
            $xmlContent = $xmlGenerator->createRegistrarTitulosCbcXml($voyage, $options, $transactionId);

            if (!$xmlContent) {
                throw new Exception('Error generando XML para RegistrarTitulosCbc');
            }

            // Crear cliente SOAP y enviar
            $soapClient = $this->soapClient->createClient($this->config['webservice_type'], $this->config['environment']);
            $soapResult = $this->sendSoapRequest($transaction, $soapClient, $xmlContent, 'RegistrarTitulosCbc');

            // Procesar respuesta
            $voyageIdentifier = $soapResult['external_reference'] ?? null;

            if ($soapResult['success']) {
                $transaction->update([
                    'status' => 'success',
                    'external_reference' => $voyageIdentifier,
                    'completed_at' => now(),
                ]);


                // Actualizar estado del webservice
                $this->updateWebserviceStatus($voyage, 'sent', [
                    'transaction_id' => $transaction->id,
                    'external_reference' => $voyageIdentifier,
                ]);

                Log::info('RegistrarTitulosCbc enviado exitosamente', [
                    'voyage_id' => $voyage->id,
                    'transaction_id' => $transaction->id,
                    'external_reference' => $voyageIdentifier,
                ]);
                
                DB::commit();
                $soapResult['transaction_id'] = $transaction->id;
                return $soapResult;
            } else {
                $transaction->update([
                    'status' => 'error',
                    'error_message' => $soapResult['error_message'] ?? 'Error desconocido',
                ]);

                $this->updateWebserviceStatus($voyage, 'error', [
                    'last_error_at' => now(),
                    'last_error_message' => $soapResult['error_message'] ?? 'Error desconocido',
                ]);

                DB::commit();
                $soapResult['transaction_id'] = $transaction->id;
                return $soapResult;
            }

        } catch (Exception $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            
            $this->logOperation('error', 'Error en RegistrarTitulosCbc', [
                'error' => $e->getMessage(),
                'voyage_id' => $voyage->id,
            ]);

            return [
                'success' => false,
                'error_message' => $e->getMessage(),
                'transaction_id' => $this->currentTransactionId,
            ];
        }
    }

    /**
     * CerrarViaje - Cierre de Información Anticipada
     * 
     * Cierra el viaje enviando conocimientos que NO descargan en Argentina.
     * Requisito: El viaje debe tener argentina_voyage_id (RegistrarViaje previo).
     * 
     * @param Voyage $voyage
     * @param array $options
     * @return array
     */
    public function cerrarViaje(Voyage $voyage, array $options = []): array
    {
        try {
            // 1. VALIDAR PREREQUISITOS
            if (empty($voyage->argentina_voyage_id)) {
                return [
                    'success' => false,
                    'transaction_id' => null,
                    'external_reference' => null,
                    'error_message' => 'El viaje debe tener IdentificadorViaje de AFIP. Primero ejecute RegistrarViaje.',
                ];
            }
            
            // 2. VALIDAR TÍTULOS DE CIERRE SIN AUTENTICAR NI TRANSMITIR
            $validation = $this->validateTitlesData($voyage, true);
            if (!empty($validation['errors'])) {
                return [
                    'success' => false,
                    'transaction_id' => null,
                    'external_reference' => $voyage->argentina_voyage_id,
                    'error_message' => implode('; ', $validation['errors']),
                    'validation_errors' => $validation['errors'],
                ];
            }

            DB::beginTransaction();
            
            // 3. CREAR TRANSACCIÓN
            $transaction = $this->createWebserviceTransaction($voyage, [
                'method' => 'CerrarViaje',
                'soap_action' => 'Ar.Gob.Afip.Dga.Org.wgesinformacionanticipada/CerrarViaje',
            ]);
            $this->currentTransactionId = $transaction->id;
            
            // 4. Generar XML con el mismo IdTransaccion persistido
            $xmlGenerator = new SimpleXmlGenerator($this->company, $this->config);
            $requestXml = $xmlGenerator->generateCerrarViajeXml(
                $voyage,
                $this->company,
                $transaction->transaction_id
            );
            
            Log::info("XML CerrarViaje generado", [
                'voyage_id' => $voyage->id,
                'xml_size' => strlen($requestXml),
            ]);
            
            // 5. CREAR CLIENTE SOAP
            $soapClient = $this->soapClient->createClient($this->config['webservice_type'], $this->config['environment']);

            // 5.1 ENVIAR REQUEST SOAP
            $soapResult = $this->sendSoapRequest($transaction, $soapClient, $requestXml, 'CerrarViaje');
            
            // 6. PROCESAR RESPUESTA
            if ($soapResult['success']) {
                // Actualizar transacción como exitosa
                $transaction->update([
                    'status' => 'success',
                    'completed_at' => now(),
                ]);
                
                // Actualizar estado del viaje
                $voyage->update([
                    'argentina_status' => 'approved',
                ]);
                
                // ✅ PERSISTIR ESTADO DEL WEBSERVICE
                $this->updateWebserviceStatus($voyage, 'approved', [
                    'transaction_id' => $transaction->id,
                ]);
                
                Log::info('CerrarViaje enviado exitosamente', [
                    'voyage_id' => $voyage->id,
                    'transaction_id' => $transaction->id,
                    'argentina_voyage_id' => $voyage->argentina_voyage_id,
                ]);
                
                DB::commit();
                
                return [
                    'success' => true,
                    'transaction_id' => $transaction->id,
                    'external_reference' => $voyage->argentina_voyage_id,
                    'error_message' => null,
                ];
                
            } else {
                // Error en envío
                $errorMessage = $soapResult['error_message'] ?? 'Error desconocido en envío SOAP';
                
                $transaction->update([
                    'status' => 'error',
                    'error_message' => $errorMessage,
                ]);
                
                $this->updateWebserviceStatus($voyage, 'error', [
                    'last_error_at' => now(),
                    'last_error_message' => $errorMessage,
                ]);
                
                DB::commit();
                
                return [
                    'success' => false,
                    'transaction_id' => $transaction->id,
                    'external_reference' => null,
                    'error_message' => $errorMessage,
                ];
            }
            
        } catch (\Exception $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            
            $this->logOperation('error', 'Error en CerrarViaje', [
                'error' => $e->getMessage(),
                'voyage_id' => $voyage->id,
            ]);
            
            return [
                'success' => false,
                'transaction_id' => $this->currentTransactionId ?? null,
                'external_reference' => null,
                'error_message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Enviar request SOAP específico para Información Anticipada
     */
    private function sendSoapRequest(
        $transaction,
        $soapClient,
        string $xmlContent,
        string $method
    ): array {
        try {
            $this->createWebserviceLog(
                $transaction->id,
                'info',
                'soap_request',
                "Iniciando envío SOAP {$method}",
                [
                    'xml_size_kb' => round(strlen($xmlContent) / 1024, 2),
                    'method' => $method,
                    'environment' => $this->config['environment'] ?? 'testing',
                ]
            );

            $transaction->update([
                'status' => 'sending',
                'sent_at' => now(),
            ]);

            $endpoint = $this->getServiceEndpoint();
            $soapAction = "Ar.Gob.Afip.Dga.Org.wgesinformacionanticipada/{$method}";

            $startTime = microtime(true);
            $response = $soapClient->__doRequest(
                $xmlContent,
                $endpoint,
                $soapAction,
                SOAP_1_1
            );
            $responseTime = round((microtime(true) - $startTime) * 1000);

            if (!is_string($response) || trim($response) === '') {
                throw new Exception('AFIP devolvió una respuesta SOAP vacía.');
            }

            $parsed = $this->parseBusinessResponse($response, $method);
            $externalReference = $parsed['external_reference'] ?? null;

            if (
                $method === 'RegistrarViaje'
                && ($parsed['success'] ?? false)
                && !$externalReference
            ) {
                $parsed['success'] = false;
                $parsed['error_message'] = 'AFIP no devolvió IdentificadorViaje.';
            }

            $transaction->update([
                'request_xml' => $xmlContent,
                'response_xml' => $response,
                'response_time_ms' => $responseTime,
                'response_at' => now(),
                'external_reference' => $externalReference,
            ]);

            $result = array_merge($parsed, [
                'response_data' => $response,
                'response_xml' => $response,
                'request_xml' => $xmlContent,
                'response_time_ms' => $responseTime,
                'external_reference' => $externalReference,
            ]);

            foreach ($result['errors'] ?? [] as $error) {
                $this->registerWebserviceError(
                    (string) ($error['code'] ?? 'AFIP_ERROR'),
                    trim(
                        (string) ($error['description'] ?? 'Error de negocio AFIP')
                        . (!empty($error['additional'])
                            ? ' - ' . $error['additional']
                            : '')
                    )
                );
            }

            $this->createWebserviceResponse(
                $transaction->id,
                (bool) ($result['success'] ?? false),
                [
                    'external_reference' => $externalReference,
                    'response_time_ms' => $responseTime,
                    'method' => $method,
                    'errors' => $result['errors'] ?? [],
                    'warnings' => $result['warnings'] ?? [],
                ]
            );

            $this->createWebserviceLog(
                $transaction->id,
                ($result['success'] ?? false) ? 'info' : 'error',
                'completion',
                ($result['success'] ?? false)
                    ? 'Proceso completado exitosamente'
                    : 'Proceso rechazado por AFIP',
                [
                    'method' => $method,
                    'final_status' => ($result['success'] ?? false) ? 'success' : 'error',
                    'error_message' => $result['error_message'] ?? null,
                ]
            );

            return $result;
        } catch (Exception $e) {
            $this->createWebserviceLog(
                $transaction->id,
                'critical',
                'exception',
                "Excepción durante envío SOAP: {$e->getMessage()}",
                [
                    'method' => $method,
                    'exception_class' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]
            );

            $this->registerWebserviceError('EXCEPTION', $e->getMessage());

            return [
                'success' => false,
                'error_message' => $e->getMessage(),
                'response_time_ms' => null,
            ];
        }
    }

    private function parseBusinessResponse(string $response, string $method): array
    {
        $dom = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $loaded = $dom->loadXML($response);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (!$loaded) {
            return [
                'success' => false,
                'error_message' => 'AFIP devolvió XML inválido.',
                'errors' => [],
                'warnings' => [],
            ];
        }

        $xpath = new \DOMXPath($dom);

        $fault = $xpath->query('//*[local-name()="Fault"]')->item(0);
        if ($fault) {
            $faultText = trim($fault->textContent);
            return [
                'success' => false,
                'error_message' => $faultText !== '' ? $faultText : 'SOAP Fault de AFIP.',
                'errors' => [['code' => 'SOAP_FAULT', 'description' => $faultText]],
                'warnings' => [],
            ];
        }

        $result = $xpath->query(
            '//*[local-name()="' . $method . 'Result"]'
        )->item(0);

        if (!$result) {
            return [
                'success' => false,
                'error_message' => "No se encontró {$method}Result en la respuesta de AFIP.",
                'errors' => [],
                'warnings' => [],
            ];
        }

        $errors = [];
        $warnings = [];

        foreach ($xpath->query('.//*[local-name()="DetalleError"]', $result) as $detail) {
            $codeNode = $xpath->query('./*[local-name()="Codigo"]', $detail)->item(0);
            $descriptionNode = $xpath->query('./*[local-name()="Descripcion"]', $detail)->item(0);
            $additionalNode = $xpath->query('./*[local-name()="DescripcionAdicional"]', $detail)->item(0);

            $code = trim((string) ($codeNode?->textContent ?? ''));
            $description = trim((string) ($descriptionNode?->textContent ?? ''));
            $additional = trim((string) ($additionalNode?->textContent ?? ''));

            if ($code === '') {
                continue;
            }

            if ($code === '0') {
                if ($description !== '' || $additional !== '') {
                    $warnings[] = [
                        'code' => $code,
                        'description' => $description,
                        'additional' => $additional,
                    ];
                }
                continue;
            }

            $errors[] = [
                'code' => $code,
                'description' => $description,
                'additional' => $additional,
            ];
        }

        $identifierNode = $xpath->query(
            './/*[local-name()="IdentificadorViaje"]',
            $result
        )->item(0);
        $identifier = trim((string) ($identifierNode?->textContent ?? ''));

        if (!empty($errors)) {
            $first = $errors[0];
            $message = trim(
                ($first['description'] ?: 'AFIP rechazó la operación.')
                . ($first['additional'] ? ' - ' . $first['additional'] : '')
            );

            return [
                'success' => false,
                'external_reference' => $identifier ?: null,
                'error_code' => $first['code'],
                'error_message' => $message,
                'errors' => $errors,
                'warnings' => $warnings,
            ];
        }

        return [
            'success' => true,
            'external_reference' => $identifier ?: null,
            'error_message' => null,
            'errors' => [],
            'warnings' => $warnings,
        ];
    }

    /**
     * Crear transacción webservice
     */
    /**
     * Crear transacción webservice
     */
    private function createWebserviceTransaction(
        Voyage $voyage,
        array $options = []
    ): \App\Models\WebserviceTransaction {
        $method = $options['method'] ?? 'RegistrarViaje';

        $transactionId = $options['transaction_id'] ?? (
            'IA'
            . now()->format('ymdHis')
            . str_pad((string) ($voyage->id % 100000), 5, '0', STR_PAD_LEFT)
            . random_int(0, 9)
        );

        if (strlen($transactionId) > 20) {
            throw new Exception('IdTransaccion de Información Anticipada supera 20 caracteres.');
        }

        return \App\Models\WebserviceTransaction::create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'voyage_id' => $voyage->id,
            'transaction_id' => $transactionId,
            'webservice_type' => 'anticipada',
            'country' => 'AR',
            'webservice_url' => $this->getServiceEndpoint(),
            'soap_action' => $options['soap_action']
                ?? 'Ar.Gob.Afip.Dga.Org.wgesinformacionanticipada/RegistrarViaje',
            'additional_metadata' => ['method' => $method],
            'status' => 'pending',
            'retry_count' => 0,
            'max_retries' => 3,
            'environment' => $this->config['environment'],
            'currency_code' => 'USD',
            'container_count' => 0,
            'bill_of_lading_count' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function getServiceEndpoint(): string
    {
        return ($this->config['environment'] ?? 'testing') === 'production'
            ? 'https://webservicesadu.afip.gob.ar/DIAV2/wgesinformacionanticipada/wgesinformacionanticipada.asmx'
            : 'https://wsaduhomoext.afip.gob.ar/DIAV2/wgesinformacionanticipada/wgesinformacionanticipada.asmx';
    }

    /**
     * Método público principal para envío
     */
    public function sendWebservice(Voyage $voyage, array $options = []): array
    {
        $method = $options['method'] ?? 'RegistrarViaje';
        
        switch ($method) {
            case 'RegistrarViaje':
                return $this->registrarViaje($voyage, $options);
                
            case 'RectificarViaje':
                return $this->rectificarViaje($voyage, $options);
                
            case 'RegistrarTitulosCbc':
                return $this->registrarTitulosCbc($voyage, $options);

            case 'CerrarViaje':
                return $this->cerrarViaje($voyage, $options);
                
            default:
                return [
                    'success' => false,
                    'error_message' => "Método no válido: {$method}",
                ];
        }
    }

    /**
     * Actualizar estado del webservice en VoyageWebserviceStatus
     */
    private function updateWebserviceStatus(Voyage $voyage, string $status, array $data = []): void
    {
        // Obtener o crear el estado del webservice
        $webserviceStatus = \App\Models\VoyageWebserviceStatus::firstOrCreate(
            [
                'voyage_id' => $voyage->id,
                'country' => 'AR',
                'webservice_type' => 'anticipada',
            ],
            [
                'company_id' => $this->company->id,
                'user_id' => $this->user->id,
                'status' => 'pending',
                'can_send' => true,
                'is_required' => true,
                'retry_count' => 0,
                'max_retries' => 3,
            ]
        );

        // Actualizar según el estado
        switch ($status) {
            case 'sent':
                $webserviceStatus->markAsSent(
                    $data['transaction_id'] ?? null,
                    $this->user->id
                );
                
                // Si tenemos external_reference, guardarlo también
                if (isset($data['external_reference'])) {
                    $webserviceStatus->update([
                        'external_voyage_number' => $data['external_reference'],
                    ]);
                }
                break;

            case 'approved':
                $webserviceStatus->markAsApproved(
                    $data['confirmation_number'] ?? null,
                    $data['external_reference'] ?? null,
                    $this->user->id
                );
                break;

            case 'error':
                $webserviceStatus->markAsError(
                    $data['error_code'] ?? null,
                    $data['error_message'] ?? null,
                    $this->user->id
                );
                break;
        }

        $this->logOperation('info', 'Estado de webservice actualizado', [
            'voyage_id' => $voyage->id,
            'status' => $status,
            'webservice_status_id' => $webserviceStatus->id,
        ]);
    }

    /**
     * Crear log de webservice
     */
    private function createWebserviceLog(
        int $transactionId, 
        string $level, 
        string $category,
        string $message, 
        array $context = []
    ): void
    {
        try {
            WebserviceLog::create([
                'transaction_id' => $transactionId,
                'user_id' => $this->user->id,
                'level' => $level,
                'category' => $category,
                'message' => $message,
                'context' => !empty($context) ? $context : null,
                'environment' => $this->config['environment'],
                'webservice_operation' => $context['method'] ?? 'anticipada',
                'created_at' => now(),
            ]);
        } catch (Exception $e) {
            Log::error('Error creando WebserviceLog: ' . $e->getMessage());
        }
    }

    /**
     * Crear respuesta estructurada
     */
    private function createWebserviceResponse(
        int $transactionId,
        bool $isSuccess,
        array $responseData
    ): void
    {
        try {
            $responseType = $isSuccess ? 'success' : 'business_error';
            
            // Extraer IdentificadorViaje si existe
            $voyageNumber = null;
            if (isset($responseData['external_reference'])) {
                $voyageNumber = $responseData['external_reference'];
            }
            
            WebserviceResponse::create([
                'transaction_id' => $transactionId,
                'response_type' => $responseType,
                'processing_status' => $isSuccess ? 'completed' : 'requires_manual',
                'requires_action' => !$isSuccess,
                'voyage_number' => $voyageNumber,
                'customs_metadata' => $responseData,
                'customs_status' => $isSuccess ? 'approved' : 'rejected',
                'customs_processed_at' => now(),
                'processed_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (Exception $e) {
            Log::error('Error creando WebserviceResponse: ' . $e->getMessage());
        }
    }

    /**
     * Registrar error en catálogo (si no existe)
     */
    private function registerWebserviceError(string $errorCode, string $errorMessage): void
    {
        try {
            // Buscar si el error ya existe
            $existingError = WebserviceError::where('country', 'AR')
                ->where('webservice_type', 'anticipada')
                ->where('error_code', $errorCode)
                ->first();
                
            if (!$existingError) {
                // Crear nuevo error en catálogo
                WebserviceError::create([
                    'country' => 'AR',
                    'webservice_type' => 'anticipada',
                    'error_code' => $errorCode,
                    'error_title' => 'Error AFIP ' . $errorCode,
                    'error_description' => $errorMessage,
                    'category' => 'business_logic',
                    'severity' => 'high',
                    'is_blocking' => true,
                    'allows_retry' => false,
                    'suggested_solution' => 'Verificar datos según documentación AFIP',
                    'frequency_count' => 1,
                    'first_occurrence' => now(),
                    'last_occurrence' => now(),
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                // Actualizar contador de frecuencia
                $existingError->increment('frequency_count');
                $existingError->update(['last_occurrence' => now()]);
            }
        } catch (Exception $e) {
            Log::error('Error registrando WebserviceError: ' . $e->getMessage());
        }
    }

    


}