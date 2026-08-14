<?php

namespace App\Services\Simple;

use App\Models\Company;
use App\Models\Shipment;
use App\Models\Voyage;
use App\Models\BillOfLading;
use Illuminate\Support\Facades\Log;

use Exception;

/**
 * SISTEMA SIMPLE WEBSERVICES - Generador XML CORREGIDO
 * 
 * SOLUCIÓN DEFINITIVA para problemas AFIP MIC/DTA
 * Flujo correcto: RegistrarTitEnvios -> RegistrarEnvios -> RegistrarMicDta
 * XML según especificación exacta AFIP
 * 
 * CAMBIOS CRÍTICOS:
 * - Estructura XML exacta según AFIP
 * - Campos obligatorios completos
 * - Validaciones peso/cantidad > 0
 * - Namespace correcto
 * - Separación clara de métodos
 */
class SimpleXmlGenerator
{
    private Company $company;
    private const AFIP_NAMESPACE = 'Ar.Gob.Afip.Dga.wgesregsintia2';
    private const AFIP_ANTICIPADA_NAMESPACE = 'Ar.Gob.Afip.Dga.Org.wgesinformacionanticipada';
    private const WSDL_URL = 'https://wsaduhomoext.afip.gob.ar/DIAV2/wgesregsintia2/wgesregsintia2.asmx?wsdl';
    private array $config;

    public function __construct(Company $company, array $config = [])
    {
        $this->company = $company;
        $this->config = $config;
    }

    /**
     * PASO 1: RegistrarTitEnvios - SOLO registra el título del transporte
     * NO incluye envíos detallados - esos van en RegistrarEnvios
     */
    public function createRegistrarTitEnviosXml(Shipment $shipment, string $transactionId): string
    {
        // ============ DIAGNÓSTICO COMPLETO CUIT ============
        \Log::info("=== DIAGNÓSTICO REGISTRAR TIT ENVIOS ===", [
            'company_id' => $this->company->id,
            'company_name' => $this->company->name,
            'company_tax_id_RAW' => $this->company->tax_id,
            'company_tax_id_CLEANED' => preg_replace('/[^0-9]/', '', $this->company->tax_id),
            'shipment_id' => $shipment->id,
            'transaction_id' => $transactionId,
        ]);

        // Verificar si hay shipper/consignee con CUIT diferente
        $billsOfLading = $shipment->billsOfLading()->with(['shipper', 'consignee'])->get();
        foreach ($billsOfLading as $bl) {
            \Log::info("BL Tax IDs", [
                'bl_id' => $bl->id,
                'bl_number' => $bl->bill_number,
                'shipper_tax_id' => $bl->shipper?->tax_id,
                'consignee_tax_id' => $bl->consignee?->tax_id,
            ]);
        }

        // Verificar WSAA tokens
        $wsaa = $this->getWSAATokens();
        \Log::info("WSAA Tokens obtenidos", [
            'token_length' => strlen($wsaa['token']),
            'cuit_from_wsaa' => $wsaa['cuit'] ?? 'NO DEFINIDO',
            'company_tax_id' => $this->company->tax_id,
            'MATCH' => ($wsaa['cuit'] ?? '') === preg_replace('/[^0-9]/', '', $this->company->tax_id) ? 'SI' : 'NO'
        ]);
        try {
            // Cargar relaciones necesarias
            $voyage = $shipment->voyage()->with([
                'originPort.country', 
                'destinationPort.country',
                'originCustoms',
                'destinationCustoms'
            ])->first();
            
            $billsOfLading = $shipment->billsOfLading()->with([
                'shipper',
                'consignee', 
                'notifyParty',
                'shipmentItems.packagingType',
                'shipmentItems.containers'
            ])->get();

            if ($billsOfLading->isEmpty()) {
                throw new \Exception("El shipment {$shipment->shipment_number} no tiene Bills of Lading");
            }

            $wsaa = $this->getWSAATokens();
            
            // Códigos de puertos y aduanas
            $codAduOrigen = $this->getPortCustomsCode($voyage->originPort?->code ?? 'ARBUE');
            //$codAduDest = $this->getPortCustomsCode($voyage->destinationPort?->code ?? 'PYASU');
            $codAduDest = str_pad($this->getPortCustomsCode($voyage->destinationPort?->code ?? 'PYASU'), 3, '0', STR_PAD_LEFT);
            $codPaisOrigen = $voyage->originPort?->country?->iso2_code ?? 'AR';
            $codPaisDest = $voyage->destinationPort?->country?->iso2_code ?? 'PY';
            // Buscar lugar operativo vinculado al puerto de origen
            $operativeLocationOrigen = \App\Models\AfipOperativeLocation::where('port_id', $voyage->originPort?->id)
                ->where('is_active', true)
                ->first();
            $codLugOperOrigen = $operativeLocationOrigen?->location_code ?? '001';
            // Buscar lugar operativo vinculado al puerto de destino
            $operativeLocationDest = \App\Models\AfipOperativeLocation::where('port_id', $voyage->destinationPort?->id)
                ->where('is_active', true)
                ->first();
            $codLugOperDest = $operativeLocationDest?->location_code ?? '001';
            $codCiuOrigen = $voyage->originPort?->code ?? 'ARBUE';
            $codCiuDest = $voyage->destinationPort?->code ?? 'PYASU';

            // Crear XMLWriter
            $w = new \XMLWriter();
            $w->openMemory();
            $w->startDocument('1.0', 'UTF-8');

            // Envelope SOAP con namespace SOAP-ENV (como Roberto)
            $w->startElementNs('SOAP-ENV', 'Envelope', 'http://schemas.xmlsoap.org/soap/envelope/');
            $w->writeAttribute('xmlns:xsd', 'http://www.w3.org/2001/XMLSchema');
            $w->writeAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
            
            $w->startElementNs('SOAP-ENV', 'Body', null);
                $w->startElement('RegistrarTitEnvios');
                $w->writeAttribute('xmlns', self::AFIP_NAMESPACE);

                // === AUTENTICACIÓN ===
                $w->startElement('argWSAutenticacionEmpresa');
                    $w->writeElement('Token', $wsaa['token']);
                    $w->writeElement('Sign', $wsaa['sign']);
                    //$w->writeElement('CuitEmpresaConectada', preg_replace('/[^0-9]/', '', $this->company->tax_id));
                    $w->writeElement('CuitEmpresaConectada', (string)$this->company->tax_id);
                    $w->writeElement('TipoAgente', 'TRSP'); // CORREGIDO: TRSP no ATA
                    $w->writeElement('Rol', 'TRSP');
                $w->endElement();

                // === PARÁMETROS PRINCIPALES ===
                $w->startElement('argRegistrarTitEnviosParam');
                    $w->writeElement('idTransaccion', substr($transactionId, 0, 15));

                    // === TÍTULOS DE TRANSPORTE CON ENVÍOS ===
                    $w->startElement('titulosTransEnvios');
                    
                    $envioIndex = 1;
                    $allContainers = collect();
                    $emptyContainerTitles = collect();

                    foreach ($billsOfLading as $bol) {
                        /*
                         * Un BL cuyos contenedores son todos condición V
                         * corresponde a un Título de Transporte de
                         * contenedores vacíos.
                         *
                         * No debe enviarse también como TitTransEnvio.
                         */
                        $bolContainers = $bol->shipmentItems
                            ->flatMap(
                                fn ($item) => $item->containers ?? collect()
                            )
                            ->unique('container_number')
                            ->values();

                        $isEmptyContainerTitle =
                            $bolContainers->isNotEmpty()
                            && $bolContainers->every(
                                fn ($container) => $container->condition === 'V'
                            );

                        if ($isEmptyContainerTitle) {
                            $emptyContainerTitles->push([
                                'bol' => $bol,
                                'containers' => $bolContainers,
                            ]);

                            foreach ($bolContainers as $container) {
                                $allContainers->push($container);
                            }

                            continue;
                        }
                        /*
                         * Los códigos aduaneros de RegistrarTitEnvios
                         * pertenecen al conocimiento.
                         *
                         * No inferirlos desde el puerto ni completar con
                         * códigos genéricos.
                         */
                        $bolCodAduOrigen = trim(
                            (string) ($bol->origin_customs_code ?? '')
                        );

                        if ($bolCodAduOrigen === '') {
                            throw new Exception(
                                "BL {$bol->bill_number}: falta la aduana AFIP de origen."
                            );
                        }

                        $bolCodLugOperOrigen = trim(
                            (string) ($bol->origin_operative_code ?? '')
                        );

                        if ($bolCodLugOperOrigen === '') {
                            throw new Exception(
                                "BL {$bol->bill_number}: falta el lugar operativo AFIP de origen."
                            );
                        }

                        $bolCodAduDest = trim(
                            (string) ($bol->discharge_customs_code ?? '')
                        );

                        if ($bolCodAduDest === '') {
                            throw new Exception(
                                "BL {$bol->bill_number}: falta la aduana AFIP de destino."
                            );
                        }

                        $bolCodLugOperDest = trim(
                            (string) ($bol->operational_discharge_code ?? '')
                        );

                        if ($bolCodLugOperDest === '') {
                            throw new Exception(
                                "BL {$bol->bill_number}: falta el lugar operativo AFIP de destino."
                            );
                        }
                        
                        $w->startElement('TitTransEnvio');
                            
                            // Datos básicos del título
                            $w->writeElement('codViaTrans', '8'); // Hidrovía
                            $w->writeElement('idTitTrans', $bol->bill_number);
                            $w->writeElement('obsDeclaAduInter', $bol->cargo_description ?? 'CARGA GENERAL');
                            
                            // === REMITENTE (shipper) ===
                            $this->writeRemitente($w, $bol);
                            
                            // === CONSIGNATARIO ===
                            $this->writeConsignatario($w, $bol);
                            
                            // === DESTINATARIO (igual que consignatario normalmente) ===
                            $this->writeDestinatario($w, $bol);
                            
                            // === NOTIFICADO ===
                            $this->writeNotificado($w, $bol);
                            
                            // Indicadores
                            $w->writeElement('indFinCom', 'S');
                            $w->writeElement('indFraccTransp', $bol->is_fractional ? 'S' : 'N');
                            $w->writeElement('indConsol', $bol->is_consolidated ? 'S' : 'N');
                            
                            // Origen
                            $w->startElement('origen');
                                $w->writeElement('codAdu', $bolCodAduOrigen);
                            $w->endElement();

                            // Destino
                            $w->startElement('destino');
                                $w->writeElement('codPais', $codPaisDest);
                                $w->writeElement('codAdu', $bolCodAduDest);
                            $w->endElement();
                            
                            // === ENVÍOS ===
                            $w->startElement('envios');
                                $w->startElement('Envio');
                                    
                                    // === DESTINACIONES ===
                                    $w->startElement('destinaciones');
                                    
                                    // Verificar que BL tenga id_decla
                                    if (empty($bol->permiso_embarque)) {
                                        throw new Exception("BL {$bol->bill_number} no tiene Permiso de Embarque. Campo obligatorio para AFIP.");
                                    }
                                    
                                    $w->startElement('Destinacion');
                                        $w->writeElement('idDecla', substr($bol->permiso_embarque, 0, 16));
                                        $w->writeElement('montoFob', '0');
                                        $w->writeElement('montoFlete', '0');
                                        $w->writeElement('montoSeg', '0');
                                        $w->writeElement('codDivisaFob', '');
                                        $w->writeElement('codDivisaFle', '');
                                        $w->writeElement('codDivisaSeg', '');
                                        
                                        // Items de la destinación
                                        $w->startElement('items');
                                        $itemIndex = 1;
                                        foreach ($bol->shipmentItems as $item) {
                                            $w->startElement('Item');
                                                $w->writeElement('nroItem', (string)$itemIndex);
                                                $w->writeElement('peso', number_format($item->gross_weight_kg ?? 0, 0, '', ''));
                                            $w->endElement();
                                            $itemIndex++;
                                        }
                                        if ($bol->shipmentItems->isEmpty()) {
                                            // Al menos un item por defecto
                                            $w->startElement('Item');
                                                $w->writeElement('nroItem', '1');
                                                $w->writeElement('peso', number_format($bol->gross_weight_kg ?? 1000, 0, '', ''));
                                            $w->endElement();
                                        }
                                        $w->endElement(); // items
                                        
                                        // === BULTOS ===
                                        $w->startElement('bultos');
                                        foreach ($bol->shipmentItems as $item) {
                                            // Obtener contenedores del item
                                            $itemContainers = $item->containers ?? collect();
                                            
                                            if ($itemContainers->isEmpty()) {
                                                // Bulto sin contenedor (carga suelta)
                                                $w->startElement('Bulto');
                                                    $w->writeElement('cantBultos', (string)($item->package_quantity ?? 1));
                                                    $w->writeElement('cantBultosTotFrac', (string)($item->package_quantity ?? 1));
                                                    $w->writeElement('pesoBruto', number_format($item->gross_weight_kg ?? 0, 0, '', ''));
                                                    $w->writeElement('pesoBrutoTotFrac', number_format($item->gross_weight_kg ?? 0, 0, '', ''));
                                                    $codEmbalaje = $item->packagingType?->argentina_ws_code ?? 'BG';
                                                    $w->writeElement('codTipEmbalaje', (strlen($codEmbalaje) === 2) ? $codEmbalaje : 'BG');
                                                    $w->writeElement('descMercaderia', substr($item->item_description ?? 'MERCADERIA', 0, 100));
                                                    $w->writeElement('marcaNro', !empty($item->cargo_marks) ? $item->cargo_marks : 'S/M');
                                                    $w->writeElement('indCargSuelt', 'S');
                                                $w->endElement();
                                            } else {
                                                // Bultos con contenedores
                                                foreach ($itemContainers as $container) {
                                                    $pivot = $container->pivot ?? null;
                                                    $pesoContainer = $pivot?->gross_weight_kg ?? $item->gross_weight_kg ?? 0;
                                                    // Cuando hay contenedor, cantBultos debe ser 0 según AFIP
                                                    // Cuando hay contenedor: cantBultos=0, cantBultosTotFrac=total de contenedores
                                                    $totalContainersInItem = $itemContainers->count();

                                                    $w->startElement('Bulto');
                                                        $w->writeElement('cantBultos', '0');
                                                        $w->writeElement('cantBultosTotFrac', (string)$totalContainersInItem);                                                        $w->writeElement('pesoBruto', number_format($pesoContainer, 0, '', ''));
                                                        $w->writeElement('pesoBrutoTotFrac', number_format($pesoContainer, 0, '', ''));
                                                        //$codEmbalaje = $item->packagingType?->argentina_ws_code ?? 'CN';
                                                        // TEMPORAL: ZW hardcodeado para contenedores - pruebas AFIP
                                                        $codEmbalaje = 'ZT';
                                                        $w->writeElement('codTipEmbalaje', (strlen($codEmbalaje) === 2) ? $codEmbalaje : 'CN');
                                                        $w->writeElement('descMercaderia', substr($item->item_description ?? 'MERCADERIA EN CONTENEDOR', 0, 100));
                                                        $w->writeElement('marcaNro', !empty($item->cargo_marks) ? $item->cargo_marks : 'S/M');
                                                        $w->writeElement('indCargSuelt', 'N');
                                                        $w->writeElement('idContenedor', $container->container_number);
                                                    $w->endElement();
                                                    
                                                    // Registrar contenedor para sección global
                                                    $allContainers->push($container);
                                                }
                                            }
                                        }
                                        $w->endElement(); // bultos
                                        
                                    $w->endElement(); // Destinacion
                                    $w->endElement(); // destinaciones
                                    
                                    // Campos obligatorios del Envio
                                    $w->writeElement('indUltFra', 'S');
                                    $w->writeElement('idFiscalATAMIC', preg_replace('/[^0-9]/', '', $this->company->tax_id));
                                    
                                    // Lugar operativo origen
                                    $w->startElement('lugOperOrigen');
                                        $w->writeElement('codLugOper', $bolCodLugOperOrigen);
                                        $w->writeElement('codCiu', $codCiuOrigen);
                                    $w->endElement();

                                    // Lugar operativo destino
                                    $w->startElement('lugOperDestino');
                                        $w->writeElement('codLugOper', $bolCodLugOperDest);
                                        $w->writeElement('codCiu', $codCiuDest);
                                    $w->endElement();
                                    
                                    // idEnvio AL FINAL (importante!)
                                    $w->writeElement('idEnvio', (string)$envioIndex);
                                    
                                $w->endElement(); // Envio
                            $w->endElement(); // envios
                            
                        $w->endElement(); // TitTransEnvio
                        $envioIndex++;
                    }
                    
                    $w->endElement(); // titulosTransEnvios

                    // === TÍTULOS CONTENEDORES VACÍOS (solo si hay) ===
                    if ($emptyContainerTitles->isNotEmpty()) {
                        $w->startElement('titulosTransContVacios');

                        foreach ($emptyContainerTitles as $emptyTitle) {
                            $emptyBol = $emptyTitle['bol'];
                            $emptyContainers = $emptyTitle['containers'];

                            $emptyBillNumber = trim(
                                (string) ($emptyBol->bill_number ?? '')
                            );

                            if ($emptyBillNumber === '') {
                                throw new Exception(
                                    'Existe un título de contenedores vacíos '
                                    . 'sin identificador de transporte.'
                                );
                            }

                            $vaciosCodAduOrigen = trim(
                                (string) ($emptyBol->origin_customs_code ?? '')
                            );

                            if ($vaciosCodAduOrigen === '') {
                                throw new Exception(
                                    "BL {$emptyBillNumber}: falta la aduana AFIP "
                                    . 'de origen para contenedores vacíos.'
                                );
                            }

                            $vaciosCodLugOperOrigen = trim(
                                (string) ($emptyBol->origin_operative_code ?? '')
                            );

                            if ($vaciosCodLugOperOrigen === '') {
                                throw new Exception(
                                    "BL {$emptyBillNumber}: falta el lugar operativo "
                                    . 'AFIP de origen para contenedores vacíos.'
                                );
                            }

                            $vaciosCodAduDest = trim(
                                (string) ($emptyBol->discharge_customs_code ?? '')
                            );

                            if ($vaciosCodAduDest === '') {
                                throw new Exception(
                                    "BL {$emptyBillNumber}: falta la aduana AFIP "
                                    . 'de destino para contenedores vacíos.'
                                );
                            }

                            $vaciosCodLugOperDest = trim(
                                (string) ($emptyBol->operational_discharge_code ?? '')
                            );

                            $vaciosCodCiuOrigen = trim(
                                (string) ($emptyBol->loadingPort?->code ?? '')
                            );

                            if ($vaciosCodCiuOrigen === '') {
                                throw new Exception(
                                    "BL {$emptyBillNumber}: falta la ciudad/puerto "
                                    . 'de origen para contenedores vacíos.'
                                );
                            }

                            $vaciosCodCiuDest = trim(
                                (string) ($emptyBol->dischargePort?->code ?? '')
                            );

                            if ($vaciosCodCiuDest === '') {
                                throw new Exception(
                                    "BL {$emptyBillNumber}: falta la ciudad/puerto "
                                    . 'de destino para contenedores vacíos.'
                                );
                            }

                            $vaciosCodPaisDest = trim(
                                (string) (
                                    $emptyBol->dischargePort?->country?->alpha2_code
                                    ?? ''
                                )
                            );

                            if ($vaciosCodPaisDest === '') {
                                throw new Exception(
                                    "BL {$emptyBillNumber}: falta el país de destino "
                                    . 'para contenedores vacíos.'
                                );
                            }

                            $w->startElement('TitTransContVacio');

                                $w->writeElement('codViaTrans', '8');

                                // Identificador real del título informado en el BL.
                                $w->writeElement(
                                    'idTitTrans',
                                    substr($emptyBillNumber, 0, 36)
                                );

                                $w->startElement('idContenedores');
                                foreach ($emptyContainers as $emptyContainer) {
                                    $w->writeElement(
                                        'idCont',
                                        $emptyContainer->container_number
                                    );
                                }
                                $w->endElement();

                                // Las partes pertenecen a este BL vacío concreto.
                                $this->writeRemitente($w, $emptyBol);
                                $this->writeConsignatario($w, $emptyBol);
                                $this->writeDestinatario($w, $emptyBol);

                                $w->startElement('origen');
                                    $w->writeElement(
                                        'codAdu',
                                        substr($vaciosCodAduOrigen, 0, 3)
                                    );
                                    $w->writeElement(
                                        'codLugOper',
                                        substr($vaciosCodLugOperOrigen, 0, 5)
                                    );
                                    $w->writeElement(
                                        'codCiu',
                                        substr($vaciosCodCiuOrigen, 0, 5)
                                    );
                                $w->endElement();

                                $w->startElement('destino');
                                    $w->writeElement(
                                        'codPais',
                                        substr($vaciosCodPaisDest, 0, 2)
                                    );
                                    $w->writeElement(
                                        'codAdu',
                                        substr($vaciosCodAduDest, 0, 9)
                                    );

                                    // En destino el lugar operativo es optativo
                                    // según la estructura DestinoContVacia.
                                    if ($vaciosCodLugOperDest !== '') {
                                        $w->writeElement(
                                            'codLugOper',
                                            substr($vaciosCodLugOperDest, 0, 9)
                                        );
                                    }

                                    $w->writeElement(
                                        'codCiu',
                                        substr($vaciosCodCiuDest, 0, 5)
                                    );
                                $w->endElement();

                                $w->writeElement(
                                    'idFiscalATAMIC',
                                    preg_replace(
                                        '/[^0-9]/',
                                        '',
                                        $this->company->tax_id
                                    )
                                );

                            $w->endElement(); // TitTransContVacio
                        }

                        $w->endElement(); // titulosTransContVacios
                    }

                    // === CONTENEDORES (todos, llenos y vacíos) ===
                    $allContainersUnique = $allContainers
                        ->unique('container_number')
                        ->values();
                    
                    if ($allContainersUnique->isNotEmpty()) {
                        $w->startElement('contenedores');
                        foreach ($allContainersUnique as $container) {
                            $w->startElement('Contenedor');
                                $w->writeElement('id', $container->container_number);
                                
                                // Código de medida ISO
                                $codMedida = $container->containerType?->iso_code ?? '22G1';
                                $w->writeElement('codMedida', $codMedida);
                                
                                // Condición AFIP: H=house(casa a casa), P=pier(muelle a muelle)
                                $condicion = $container->container_condition ?: 'H';
                                $w->writeElement('condicion', $condicion);
                                
                                // Precintos
                                $precinto = $container->shipper_seal ?? $container->carrier_seal ?? $container->customs_seal;
                                if ($precinto) {
                                    $w->startElement('precintos');
                                        $w->writeElement('precinto', $precinto);
                                    $w->endElement();
                                }
                            $w->endElement();
                        }
                        $w->endElement(); // contenedores
                    }

                $w->endElement(); // argRegistrarTitEnviosParam
                $w->endElement(); // RegistrarTitEnvios
            $w->endElement(); // Body
            $w->endElement(); // Envelope

            $w->endDocument();
            
            $xml = $w->outputMemory();
            \Log::info("XML RegistrarTitEnvios generado correctamente", ['length' => strlen($xml)]);
            
            return $xml;

        } catch (\Exception $e) {
            \Log::error('Error en createRegistrarTitEnviosXml: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Helper: Escribir sección Remitente
     * Usa dirección específica del BL si existe, sino fallback al cliente
     */
    private function writeRemitente(\XMLWriter $w, BillOfLading $bol): void
    {
        $shipper = $bol->shipper;
        $codPais = $shipper?->country?->iso2_code ?? 'AR';
        
        // Verificar si hay dirección específica para este BL
        $specific = $bol->specificContacts()->where('role', 'shipper')->where('use_specific_data', true)->first();
        
        $w->startElement('remitente');
            $w->writeElement('codPais', $codPais);
            
            // Nombre: específico o del cliente
            $nombre = ($specific && $specific->specific_company_name) 
                ? $specific->specific_company_name 
                : ($shipper?->legal_name ?? $shipper?->name ?? 'REMITENTE');
            $w->writeElement('nomRazSoc', substr($nombre, 0, 50));
            
            $w->startElement('domicilio');
                if ($specific) {
                    // Usar datos específicos del BL
                    $w->writeElement('barrio', substr($specific->specific_address_line_2 ?? 'x', 0, 50) ?: 'x');
                    $w->writeElement('ciudad', substr($specific->specific_city ?? 'x', 0, 50) ?: 'x');
                    $w->writeElement('codPostal', substr($specific->specific_postal_code ?? 'x', 0, 8) ?: 'x');
                    $w->writeElement('estado', substr($specific->specific_state_province ?? 'x', 0, 50) ?: 'x');
                    $w->writeElement('nombreCalle', substr($specific->specific_address_line_1 ?? 'x', 0, 150) ?: 'x');
                } else {
                    // Fallback: datos del cliente
                    $w->writeElement('barrio', substr($shipper?->district ?? 'x', 0, 50) ?: 'x');
                    $w->writeElement('ciudad', substr($shipper?->city ?? 'x', 0, 50) ?: 'x');
                    $w->writeElement('codPostal', substr($shipper?->postal_code ?? 'x', 0, 8) ?: 'x');
                    $w->writeElement('estado', substr($shipper?->state ?? 'x', 0, 50) ?: 'x');
                    $w->writeElement('nombreCalle', substr($shipper?->address ?? 'x', 0, 150) ?: 'x');
                }
            $w->endElement();
            
            $w->writeElement('idFiscal', preg_replace('/[^0-9]/', '', $shipper?->tax_id ?? $this->company->tax_id));
            
            // tipDocIdent y nroDocIdent solo para extranjeros
            if ($codPais !== 'AR') {
                $w->writeElement('tipDocIdent', 'CUIT');
                $w->writeElement('nroDocIdent', preg_replace('/[^0-9]/', '', $shipper?->tax_id ?? ''));
            }
        $w->endElement();
    }

    /**
     * Helper: Escribir sección Consignatario
     * Usa dirección específica del BL si existe, sino fallback al cliente
     */
    private function writeConsignatario(\XMLWriter $w, BillOfLading $bol): void
    {
        $consignee = $bol->consignee;
        
        // Verificar si hay dirección específica para este BL
        $specific = $bol->specificContacts()->where('role', 'consignee')->where('use_specific_data', true)->first();
        
        $w->startElement('consignatario');
            // Nombre: específico o del cliente
            $nombre = ($specific && $specific->specific_company_name) 
                ? $specific->specific_company_name 
                : ($consignee?->legal_name ?? $consignee?->name ?? 'CONSIGNATARIO');
            $w->writeElement('nomRazSoc', substr($nombre, 0, 50));
            
            $w->startElement('domicilio');
                if ($specific) {
                    // Usar datos específicos del BL
                    $w->writeElement('barrio', substr($specific->specific_address_line_2 ?? 'x', 0, 50) ?: 'x');
                    $w->writeElement('ciudad', substr($specific->specific_city ?? 'x', 0, 50) ?: 'x');
                    $w->writeElement('codPostal', substr($specific->specific_postal_code ?? 'x', 0, 8) ?: 'x');
                    $w->writeElement('estado', substr($specific->specific_state_province ?? 'x', 0, 50) ?: 'x');
                    $w->writeElement('nombreCalle', substr($specific->specific_address_line_1 ?? 'x', 0, 150) ?: 'x');
                } else {
                    // Fallback: datos del cliente
                    $w->writeElement('barrio', substr($consignee?->district ?? 'x', 0, 50) ?: 'x');
                    $w->writeElement('ciudad', substr($consignee?->city ?? 'x', 0, 50) ?: 'x');
                    $w->writeElement('codPostal', substr($consignee?->postal_code ?? 'x', 0, 8) ?: 'x');
                    $w->writeElement('estado', substr($consignee?->state ?? 'x', 0, 50) ?: 'x');
                    $w->writeElement('nombreCalle', substr($consignee?->address ?? 'x', 0, 150) ?: 'x');
                }
            $w->endElement();
            
            $w->writeElement('idFiscal', preg_replace('/[^0-9]/', '', $consignee?->tax_id ?? ''));
        $w->endElement();
    }

    /**
     * Helper: Escribir sección Destinatario
     * Usa dirección específica del consignee del BL si existe, sino fallback al cliente
     * (Destinatario normalmente es igual al consignatario)
     */
    private function writeDestinatario(\XMLWriter $w, BillOfLading $bol): void
    {
        $consignee = $bol->consignee;
        
        // Destinatario usa los mismos datos específicos del consignee
        $specific = $bol->specificContacts()->where('role', 'consignee')->where('use_specific_data', true)->first();
        
        $w->startElement('destinatario');
            // Nombre: específico o del cliente
            $nombre = ($specific && $specific->specific_company_name) 
                ? $specific->specific_company_name 
                : ($consignee?->legal_name ?? $consignee?->name ?? 'DESTINATARIO');
            $w->writeElement('nomRazSoc', substr($nombre, 0, 50));
            
            $w->startElement('domicilio');
                if ($specific) {
                    $w->writeElement('barrio', substr($specific->specific_address_line_2 ?? 'x', 0, 50) ?: 'x');
                    $w->writeElement('ciudad', substr($specific->specific_city ?? 'x', 0, 50) ?: 'x');
                    $w->writeElement('codPostal', substr($specific->specific_postal_code ?? 'x', 0, 8) ?: 'x');
                    $w->writeElement('estado', substr($specific->specific_state_province ?? 'x', 0, 50) ?: 'x');
                    $w->writeElement('nombreCalle', substr($specific->specific_address_line_1 ?? 'x', 0, 150) ?: 'x');
                } else {
                    $w->writeElement('barrio', substr($consignee?->district ?? 'x', 0, 50) ?: 'x');
                    $w->writeElement('ciudad', substr($consignee?->city ?? 'x', 0, 50) ?: 'x');
                    $w->writeElement('codPostal', substr($consignee?->postal_code ?? 'x', 0, 8) ?: 'x');
                    $w->writeElement('estado', substr($consignee?->state ?? 'x', 0, 50) ?: 'x');
                    $w->writeElement('nombreCalle', substr($consignee?->address ?? 'x', 0, 150) ?: 'x');
                }
            $w->endElement();
        $w->endElement();
    }

    /**
     * Helper: Escribir sección Notificado
     * Usa dirección específica del BL si existe, sino fallback al cliente
     */
    private function writeNotificado(\XMLWriter $w, BillOfLading $bol): void
    {
        $notify = $bol->notifyParty ?? $bol->consignee;
        
        // Verificar si hay dirección específica para notify_party en este BL
        $specific = $bol->specificContacts()->where('role', 'notify_party')->where('use_specific_data', true)->first();
        
        $w->startElement('notificado');
            // Nombre: específico o del cliente
            $nombre = ($specific && $specific->specific_company_name) 
                ? $specific->specific_company_name 
                : ($notify?->legal_name ?? $notify?->name ?? 'A QUIEN CORRESPONDA');
            $w->writeElement('nomRazSoc', substr($nombre, 0, 50));
            
            $w->startElement('domicilio');
                if ($specific) {
                    $w->writeElement('barrio', substr($specific->specific_address_line_2 ?? 'x', 0, 50) ?: 'x');
                    $w->writeElement('ciudad', substr($specific->specific_city ?? 'x', 0, 50) ?: 'x');
                    $w->writeElement('codPostal', substr($specific->specific_postal_code ?? 'x', 0, 8) ?: 'x');
                    $w->writeElement('estado', substr($specific->specific_state_province ?? 'x', 0, 50) ?: 'x');
                    $w->writeElement('nombreCalle', substr($specific->specific_address_line_1 ?? 'x', 0, 150) ?: 'x');
                } else {
                    $w->writeElement('barrio', substr($notify?->district ?? 'x', 0, 50) ?: 'x');
                    $w->writeElement('ciudad', substr($notify?->city ?? 'x', 0, 50) ?: 'x');
                    $w->writeElement('codPostal', substr($notify?->postal_code ?? 'x', 0, 8) ?: 'x');
                    $w->writeElement('estado', substr($notify?->state ?? 'x', 0, 50) ?: 'x');
                    $w->writeElement('nombreCalle', substr($notify?->address ?? 'x', 0, 150) ?: 'x');
                }
            $w->endElement();
            
            $w->writeElement('idFiscal', preg_replace('/[^0-9]/', '', $notify?->tax_id ?? ''));
        $w->endElement();
    }
    
    /**
     * PASO 2: RegistrarEnvios - Agregar envíos a un Título YA REGISTRADO
     * 
     * Genera XML según especificación AFIP para incorporar nuevos envíos
     * a un título de transporte previamente registrado con RegistrarTitEnvios.
     * 
     * Estructura basada en XML exitoso del cliente y manual AFIP.
     * 
     * @param Shipment $shipment Shipment con los nuevos envíos a agregar
     * @param string $idTitTrans ID del título YA REGISTRADO (de RegistrarTitEnvios)
     * @param string $transactionId ID único de transacción (máx 15 chars)
     * @return string XML completo según especificación AFIP
     * @throws Exception Si faltan datos obligatorios
     */
    public function createRegistrarEnviosXml(Shipment $shipment, string $idTitTrans, string $transactionId): string
    {
        try {
            \Log::info("=== GENERANDO XML RegistrarEnvios ===", [
                'shipment_id' => $shipment->id,
                'id_tit_trans' => $idTitTrans,
                'transaction_id' => $transactionId,
            ]);

            // Cargar relaciones necesarias
            $voyage = $shipment->voyage()->with(['originPort', 'destinationPort'])->first();

            /*
             * RegistrarEnvios trabaja sobre un único Título de Transporte.
             *
             * En RegistrarTitEnvios la aplicación informa como idTitTrans
             * el bill_number del conocimiento. Por lo tanto, esta llamada
             * debe incluir solamente el BL correspondiente a ese idTitTrans.
             */
            $billsOfLading = $shipment->billsOfLading()
                ->where('bill_number', $idTitTrans)
                ->with(['shipmentItems.containers', 'shipmentItems.packagingType'])
                ->get();

            if ($billsOfLading->isEmpty()) {
                throw new Exception(
                    "Shipment {$shipment->id} no contiene el título {$idTitTrans} para generar RegistrarEnvios."
                );
            }

            // Obtener tokens WSAA
            $wsaa = $this->getWSAATokens();

            // Códigos de lugares operativos desde puertos
            // Buscar lugar operativo vinculado al puerto de origen
            $operativeLocationOrigen = \App\Models\AfipOperativeLocation::where('port_id', $voyage->originPort?->id)
                ->where('is_active', true)
                ->first();
            $codLugOperOrigen = $operativeLocationOrigen?->location_code ?? '001';
            $codCiuOrigen = $voyage->originPort?->code ?? 'ARBUE';
            // Buscar lugar operativo vinculado al puerto de destino
            $operativeLocationDest = \App\Models\AfipOperativeLocation::where('port_id', $voyage->destinationPort?->id)
                ->where('is_active', true)
                ->first();
            $codLugOperDest = $operativeLocationDest?->location_code ?? '001';
            $codCiuDest = $voyage->destinationPort?->code ?? 'PYASU';

            // Crear XMLWriter
            $w = new \XMLWriter();
            $w->openMemory();
            $w->startDocument('1.0', 'UTF-8');

            // Envelope SOAP (mismo estilo que RegistrarTitEnvios exitoso)
            $w->startElementNs('SOAP-ENV', 'Envelope', 'http://schemas.xmlsoap.org/soap/envelope/');
            $w->writeAttribute('xmlns:xsd', 'http://www.w3.org/2001/XMLSchema');
            $w->writeAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');

            $w->startElementNs('SOAP-ENV', 'Body', null);
                $w->startElement('RegistrarEnvios');
                $w->writeAttribute('xmlns', self::AFIP_NAMESPACE);

                // === AUTENTICACIÓN (igual que RegistrarTitEnvios) ===
                $w->startElement('argWSAutenticacionEmpresa');
                    $w->writeElement('Token', $wsaa['token']);
                    $w->writeElement('Sign', $wsaa['sign']);
                    $w->writeElement('CuitEmpresaConectada', preg_replace('/[^0-9]/', '', $this->company->tax_id));
                    $w->writeElement('TipoAgente', 'TRSP');
                    $w->writeElement('Rol', 'TRSP');
                $w->endElement();

                // === PARÁMETROS REGISTRAR ENVIOS ===
                $w->startElement('argRegistrarEnviosParam');
                    
                    // idTransaccion - máximo 15 caracteres
                    $w->writeElement('idTransaccion', substr($transactionId, 0, 15));
                    
                    // idTitTrans - ID del título YA REGISTRADO (obligatorio)
                    $w->writeElement('idTitTrans', $idTitTrans);

                    // === ENVÍOS ===
                    $w->startElement('envios');
                    
                    $envioIndex = 1;
                    $allContainers = collect();

                   foreach ($billsOfLading as $bol) {
                        // Códigos AFIP desde el BL (prioridad) o fallback
                        $bolCodLugOperOrigen = $bol->origin_operative_code ?: $codLugOperOrigen;
                        //$bolCodLugOperDest = $bol->operational_discharge_code ?: $codLugOperDest;
                        $bolCodLugOperDest = str_pad($bol->operational_discharge_code ?: $codLugOperDest, 3, '0', STR_PAD_LEFT);
                        
                        // Validar identificador de la destinación.
                        // En la app este dato se persiste en permiso_embarque.
                        if (empty($bol->permiso_embarque)) {
                            throw new \Exception("BL {$bol->bill_number} no tiene Permiso de Embarque. Campo obligatorio para AFIP.");
                        }

                        $w->startElement('Envio');

                            // === DESTINACIONES ===
                            $w->startElement('destinaciones');
                                $w->startElement('Destinacion');
                                    
                                    // idDecla - Obligatorio C(16)
                                    $w->writeElement(
                                        'idDecla',
                                        substr($bol->permiso_embarque, 0, 16)
                                    );
                                    
                                    // Montos - Obligatorios N(18,2) - Cliente usa 0
                                    $w->writeElement('montoFob', '0');
                                    $w->writeElement('montoFlete', '0');
                                    $w->writeElement('montoSeg', '0');
                                    
                                    // Códigos divisa - Cliente los envía vacíos
                                    $w->writeElement('codDivisaFob', '');
                                    $w->writeElement('codDivisaFle', '');
                                    $w->writeElement('codDivisaSeg', '');

                                    // === ITEMS ===
                                    $w->startElement('items');
                                    
                                    if ($bol->shipmentItems->isNotEmpty()) {
                                        $itemIndex = 1;
                                        foreach ($bol->shipmentItems as $item) {
                                            $w->startElement('Item');
                                                // nroItem - Obligatorio C(4)
                                                $w->writeElement('nroItem', (string)$itemIndex);
                                                // peso - Obligatorio N(12,4)
                                                $peso = $item->gross_weight_kg ?? 0;
                                                $w->writeElement('peso', number_format($peso, 4, '.', ''));
                                            $w->endElement(); // Item
                                            $itemIndex++;
                                        }
                                    } else {
                                        // Al menos un item con datos del BL
                                        $w->startElement('Item');
                                            $w->writeElement('nroItem', '1');
                                            $peso = $bol->gross_weight_kg ?? 1;
                                            $w->writeElement('peso', number_format($peso, 4, '.', ''));
                                        $w->endElement();
                                    }
                                    
                                    $w->endElement(); // items

                                    // === BULTOS (orden exacto del cliente) ===
                                    $w->startElement('bultos');
                                    
                                    if ($bol->shipmentItems->isNotEmpty()) {
                                        foreach ($bol->shipmentItems as $item) {
                                            $itemContainers = $item->containers ?? collect();
                                            
                                            if ($itemContainers->isEmpty()) {
                                                // Bulto SIN contenedor (carga suelta)
                                                $this->writeBultoElement($w, $item, null);
                                            } else {
                                                // Bulto CON contenedor(es)
                                                foreach ($itemContainers as $container) {
                                                    $this->writeBultoElement($w, $item, $container);
                                                    $allContainers->push($container);
                                                }
                                            }
                                        }
                                    } else {
                                        // Bulto por defecto desde BL
                                        $this->writeBultoFromBol($w, $bol);
                                    }
                                    
                                    $w->endElement(); // bultos

                                $w->endElement(); // Destinacion
                            $w->endElement(); // destinaciones

                            // === CAMPOS OBLIGATORIOS DEL ENVÍO ===
                            
                            // indUltFra - Obligatorio C(1) - S/N
                            $w->writeElement('indUltFra', 'S');
                            
                            // idFiscalATAMIC - Obligatorio C(14)
                            $w->writeElement('idFiscalATAMIC', preg_replace('/[^0-9]/', '', $this->company->tax_id));
                            
                            // lugOperOrigen - Obligatorio
                            $w->startElement('lugOperOrigen');
                                $w->writeElement('codLugOper', $bolCodLugOperOrigen);
                                $w->writeElement('codCiu', $codCiuOrigen);
                            $w->endElement();

                            // lugOperDestino - Obligatorio
                            $w->startElement('lugOperDestino');
                                $w->writeElement('codLugOper', $bolCodLugOperDest);
                                $w->writeElement('codCiu', $codCiuDest);
                            $w->endElement();
                            
                            // idEnvio - Obligatorio N(3) - AL FINAL
                            $w->writeElement('idEnvio', (string)$envioIndex);

                        $w->endElement(); // Envio
                        $envioIndex++;
                    }
                    
                    $w->endElement(); // envios

                    // === CONTENEDORES (opcional, al final si hay) ===
                    $uniqueContainers = $allContainers->unique('id');
                    if ($uniqueContainers->isNotEmpty()) {
                        $w->startElement('contenedores');
                        foreach ($uniqueContainers as $container) {
                            $this->writeContenedorElement($w, $container);
                        }
                        $w->endElement(); // contenedores
                    }

                $w->endElement(); // argRegistrarEnviosParam
                $w->endElement(); // RegistrarEnvios
            $w->endElement(); // Body
            $w->endElement(); // Envelope

            $w->endDocument();
            
            $xmlContent = $w->outputMemory();
            
            \Log::info("XML RegistrarEnvios generado correctamente", [
                'bls_count' => $billsOfLading->count(),
                'containers_count' => $uniqueContainers->count(),
                'xml_length' => strlen($xmlContent),
            ]);
            
            return $xmlContent;

        } catch (Exception $e) {
            \Log::error('Error en createRegistrarEnviosXml: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Helper: Escribir elemento Bulto con orden exacto del cliente
     * 
     * Orden: cantBultos → cantBultosTotFrac → pesoBruto → pesoBrutoTotFrac →
     *        codTipEmbalaje → descMercaderia → marcaNro → indCargSuelt → idContenedor
     */
    private function writeBultoElement(\XMLWriter $w, \App\Models\ShipmentItem $item, ?\App\Models\Container $container = null): void
    {
        $pivot = $container?->pivot ?? null;
        
        // Obtener valores de pivot si existe, sino del item
        $pesoBruto = $pivot?->gross_weight_kg ?? $item->gross_weight_kg ?? 0;

        // Cuando hay contenedor, cantBultos = 0 según AFIP
        // Cuando es carga suelta, usar la cantidad real
        $cantBultos = $container ? 0 : ($pivot?->package_quantity ?? $item->package_quantity ?? 1);

        // Asegurar mínimos solo para carga suelta
        if (!$container) {
            $cantBultos = max(1, (int)$cantBultos);
        }
        $pesoBruto = max(0, (float)$pesoBruto);

        $w->startElement('Bulto');
            
            // cantBultos - Obligatorio N(9)
            $w->writeElement('cantBultos', (string)$cantBultos);
            
            // cantBultosTotFrac - Obligatorio N(9) - mismo valor si no fraccionado
            $w->writeElement('cantBultosTotFrac', (string)$cantBultos);
            
            // pesoBruto - Obligatorio N(14,4)
            $w->writeElement('pesoBruto', number_format($pesoBruto, 4, '.', ''));
            
            // pesoBrutoTotFrac - Obligatorio N(14,4) - mismo valor si no fraccionado
            $w->writeElement('pesoBrutoTotFrac', number_format($pesoBruto, 4, '.', ''));
            
            // codTipEmbalaje - Obligatorio C(2) - EDIFACT 7065
            // TEMPORAL: ZW hardcodeado para pruebas AFIP - TODO: implementar lógica completa
            $codEmbalaje = $container ? 'ZW' : ($item->packagingType?->argentina_ws_code ?? 'BG');
            $w->writeElement('codTipEmbalaje', $codEmbalaje);
            
            // descMercaderia - Obligatorio C(500)
            $descripcion = $item->item_description ?? 'MERCADERIA GENERAL';
            $w->writeElement('descMercaderia', substr($descripcion, 0, 500));
            
            // marcaNro - Opcional C(100) - Cliente usa "S/M"
            $marcas = $item->cargo_marks ?? 'S/M';
            $w->writeElement('marcaNro', substr($marcas, 0, 100));
            
            // indCargSuelt - Obligatorio C(1) - S/N
            $indCargSuelt = $container ? 'N' : 'S';
            $w->writeElement('indCargSuelt', $indCargSuelt);
            
            // idContenedor - Opcional C(16) - solo si hay contenedor
            if ($container && !empty($container->container_number)) {
                $w->writeElement('idContenedor', $container->container_number);
            }

        $w->endElement(); // Bulto
    }

    /**
     * Helper: Escribir Bulto desde BillOfLading (cuando no hay items)
     */
    private function writeBultoFromBol(\XMLWriter $w, \App\Models\BillOfLading $bol): void
    {
        // Detectar si es carga containerizada
        $isContainerized = $bol->primaryCargoType?->packaging_type === 'containerized';
        $cantBultos = $isContainerized ? 0 : max(1, (int)($bol->total_packages ?? 1));
        $pesoBruto = max(0, (float)($bol->gross_weight_kg ?? 0));

        $w->startElement('Bulto');
            $w->writeElement('cantBultos', (string)$cantBultos);
            $w->writeElement('cantBultosTotFrac', (string)$cantBultos);
            $w->writeElement('pesoBruto', number_format($pesoBruto, 4, '.', ''));
            $w->writeElement('pesoBrutoTotFrac', number_format($pesoBruto, 4, '.', ''));
            $w->writeElement('codTipEmbalaje', $bol->primaryPackagingType?->argentina_ws_code ?? 'CN');
            $w->writeElement('descMercaderia', substr($bol->cargo_description ?? 'MERCADERIA GENERAL', 0, 500));
            $w->writeElement('marcaNro', substr($bol->cargo_marks ?? 'S/M', 0, 100));
            $w->writeElement('indCargSuelt', 'S');
        $w->endElement();
    }

    /**
     * Helper: Escribir elemento Contenedor
     */
    private function writeContenedorElement(\XMLWriter $w, \App\Models\Container $container): void
    {
        $w->startElement('Contenedor');
            
            // id - número del contenedor
            $w->writeElement('id', $container->container_number);
            
            // codMedida - código ISO del contenedor (ej: 22G1, 42G1)
            $codMedida = $container->argentina_container_code ?? $container->container_type ?? '22G1';
            $w->writeElement('codMedida', $codMedida);
            
            // condicion AFIP: H=house(casa a casa), P=pier(muelle a muelle)
            $condicion = $container->container_condition ?: 'H';
            $w->writeElement('condicion', $condicion);
            
            // precintos - opcional
            $precinto = $container->shipper_seal ?? $container->carrier_seal ?? $container->customs_seal;
            if ($precinto) {
                $w->startElement('precintos');
                    $w->writeElement('precinto', $precinto);
                $w->endElement();
            }

        $w->endElement(); // Contenedor
    }

    /**
     * PASO 3: RegistrarMicDta - CORREGIDO según Manual AFIP
     * 
     * Registra el MIC/DTA con todos los campos obligatorios:
     * - Transportista (estructura completa)
     * - Propietario vehículo (estructura completa)
     * - Conductores (capitán)
     * - TRACKs de carga suelta
     * - TRACKs de contenedores vacíos
     * - Contenedores con carga
     * - Ruta informática con eventos programados
     * - Embarcación (estructura completa)
     * 
     * @param Voyage $voyage Viaje con relaciones cargadas
     * @param array $tracks Array de TRACKs ['carga_suelta' => [...], 'cont_vacios' => [...]]
     * @param string $transactionId ID único de transacción (máx 15 chars)
     * @return string XML completo
     */
    public function createRegistrarMicDtaXml(Voyage $voyage, array $tracks, string $transactionId, ?\App\Models\Shipment $shipment = null): string
    {
        try {
            // Cargar relaciones necesarias
            $voyage->load(['leadVessel.vesselType', 'leadVessel.flagCountry', 'captain', 'originPort.country', 'destinationPort.country']);
            
            // Si viene shipment específico (convoy), usar su vessel y captain
            if ($shipment) {
                $shipment->load(['vessel.vesselType', 'vessel.flagCountry', 'captain']);
                $vessel = $shipment->vessel ?? $voyage->leadVessel;
                $captain = $shipment->captain ?? $voyage->captain;
            } else {
                $vessel = $voyage->leadVessel;
                $captain = $voyage->captain;
            }
            
            $originPort = $voyage->originPort;
            $destinationPort = $voyage->destinationPort;
            
            // Validaciones
            if (!$vessel) {
                throw new \Exception('Voyage debe tener embarcación asignada');
            }
            $tipEmb = $this->mapVesselType($vessel->vesselType->code ?? 'BAR');
            if (!$captain && $tipEmb !== 'BAR') {
                throw new \Exception('Voyage debe tener capitán asignado');
            }
            if (!$originPort || !$destinationPort) {
                throw new \Exception('Voyage debe tener puertos de origen y destino');
            }
            
            $cuit = preg_replace('/[^0-9]/', '', $this->company->tax_id);

            $w = new \XMLWriter();
            $w->openMemory();
            $w->startDocument('1.0', 'UTF-8');

            // Envelope SOAP
            $w->startElementNs('soap', 'Envelope', 'http://schemas.xmlsoap.org/soap/envelope/');
            $w->writeAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
            $w->writeAttribute('xmlns:xsd', 'http://www.w3.org/2001/XMLSchema');
            
            $w->startElementNs('soap', 'Body', null);
                $w->startElement('RegistrarMicDta');
                $w->writeAttribute('xmlns', self::AFIP_NAMESPACE);

                // === argWSAutenticacionEmpresa ===
                $wsaa = $this->getWSAATokens('wgesregsintia2');

                $w->startElement('argWSAutenticacionEmpresa');
                    $w->writeElement('Token', $wsaa['token']);
                    $w->writeElement('Sign', $wsaa['sign']);
                    $w->writeElement('CuitEmpresaConectada', $cuit);
                    $w->writeElement('TipoAgente', 'TRSP');
                    $w->writeElement('Rol', 'TRSP');
                $w->endElement();


                // === argRegistrarMicDtaParam ===
                $w->startElement('argRegistrarMicDtaParam');
                    
                    // idTransaccion (obligatorio, máx 15 chars)
                    $w->writeElement('idTransaccion', substr($transactionId, 0, 15));
                    
                    // === micDta (estructura principal) ===
                    $w->startElement('micDta');
                        
                        // codViaTrans - 8 para hidrovía (obligatorio)
                        $w->writeElement('codViaTrans', '8');
                        
                        // === transportista (obligatorio) ===
                        $this->writeTransportistaElement($w);
                        
                        // === propVehiculo (obligatorio) ===
                        $this->writePropVehiculoElement($w);
                        
                        // === Determinar si este shipment va en lastre ===
                        $esLastre = false;
                        if ($shipment && $voyage->vessel_count > 1 && $shipment->is_lead_vessel) {
                            $vesselCategory = $vessel->vesselType?->category ?? '';
                            if ($vesselCategory !== 'barge') {
                                // Lead en convoy: lastre SOLO si no tiene BLs con carga
                                $tieneCarga = $shipment->billsOfLading()->count() > 0;
                                $esLastre = !$tieneCarga;
                            }
                        }
                        if (!$esLastre) {
                            $esLastre = ($voyage->has_cargo_onboard === 'N') ? true : false;
                        }
                        
                        // === indEnLastre (obligatorio S/N) ===
                        $w->writeElement('indEnLastre', $esLastre ? 'S' : 'N');
                        
                        // === conductores (datos del capitán - NO enviar para barcazas) ===
                        $tipEmb = $this->mapVesselType($vessel->vesselType->code ?? 'BAR');
                        if ($tipEmb !== 'BAR') {
                            $this->writeConductoresElement($w, $captain);
                        }
                        
                        // === Secciones de carga: OMITIR si va en lastre (AFIP error 27171) ===
                        if (!$esLastre) {
                            // === cargasSueltasIdTrack (TRACKs de carga suelta) ===
                            $this->writeCargasSueltasIdTrack($w, $voyage);
                            
                            // === titTransContVaciosIdTrack (TRACKs de contenedores vacíos) ===
                            $this->writeTitTransContVaciosIdTrack($w, $tracks);
                            
                            // === contenedoresConCarga (IDs de contenedores con carga) ===
                            $this->writeContenedoresConCarga($w, $voyage);
                        }
                        
                        // === rutasInf (ruta informática obligatoria) ===
                        // Obtener códigos AFIP desde el primer BL del shipment o voyage
                        $firstBol = $shipment?->billsOfLading->first()
                                ?? $voyage->shipments->first()?->billsOfLading->first() 
                                ?? $voyage->billsOfLading->first();
                        $codLugOperOrigen = $firstBol?->origin_operative_code ?: '10073';
                        $codLugOperDest = str_pad($firstBol?->operational_discharge_code ?: '001', 3, '0', STR_PAD_LEFT);

                        $this->writeRutasInf($w, $voyage, $codLugOperOrigen, $codLugOperDest);
                        
                        // === embarcacion (obligatorio) ===
                        $this->writeEmbarcacionElement($w, $vessel, $voyage);
                        
                    $w->endElement(); // micDta
                $w->endElement(); // argRegistrarMicDtaParam
                $w->endElement(); // RegistrarMicDta
            $w->endElement(); // Body
            $w->endElement(); // Envelope

            $w->endDocument();
            
            $xml = $w->outputMemory();
            
            \Log::info('RegistrarMicDta XML generado', [
                'voyage_id' => $voyage->id,
                'transaction_id' => $transactionId,
                'xml_length' => strlen($xml)
            ]);
            
            return $xml;

        } catch (\Exception $e) {
            \Log::error('Error en createRegistrarMicDtaXml: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Escribe elemento Transportista según AFIP
     */
    private function writeTransportistaElement(\XMLWriter $w): void
    {
        $w->startElement('transportista');
            // nombre (obligatorio, C50)
            $w->writeElement('nombre', substr(htmlspecialchars($this->company->legal_name ?? $this->company->name), 0, 50));
            
            // domicilio (obligatorio - estructura completa requerida por AFIP)
            $w->startElement('domicilio');
                $w->writeElement('ciudad', substr($this->company->city ?? 'S/D', 0, 50));
                $w->writeElement('codPostal', substr($this->company->postal_code ?? '0000', 0, 8));
                $w->writeElement('estado', substr($this->company->state ?? 'BUENOS AIRES', 0, 50));
                $w->writeElement('nombreCalle', substr($this->company->address ?? 'S/D', 0, 150));
            $w->endElement();
            
            // codPais (obligatorio, C2 - ISO 3166-1 Alfa 2)
            $w->writeElement('codPais', 'AR');
            
            // idFiscal (obligatorio, C14 - CUIT)
            $cuit = preg_replace('/[^0-9]/', '', $this->company->tax_id);
            $w->writeElement('idFiscal', $cuit);
            
            // tipTrans (obligatorio, C1 - R=Regular, O=Ocasional)
            $w->writeElement('tipTrans', 'R');
        $w->endElement();
    }

    /**
     * Escribe elemento PropVehiculo según AFIP
     */
    private function writePropVehiculoElement(\XMLWriter $w): void
    {
        $w->startElement('propVehiculo');
            // nombre (obligatorio, C50)
            $w->writeElement('nombre', substr(htmlspecialchars($this->company->legal_name ?? $this->company->name), 0, 50));
            
            // domicilio (obligatorio - estructura completa requerida por AFIP)
            $w->startElement('domicilio');
                $w->writeElement('ciudad', substr($this->company->city ?? 'S/D', 0, 50));
                $w->writeElement('codPostal', substr($this->company->postal_code ?? '0000', 0, 8));
                $w->writeElement('estado', substr($this->company->state ?? 'BUENOS AIRES', 0, 50));
                $w->writeElement('nombreCalle', substr($this->company->address ?? 'S/D', 0, 150));
            $w->endElement();
            
            // codPais (obligatorio, C2)
            $w->writeElement('codPais', 'AR');
            
            // idFiscal (obligatorio, C14)
            $cuit = preg_replace('/[^0-9]/', '', $this->company->tax_id);
            $w->writeElement('idFiscal', $cuit);
        $w->endElement();
    }

    /**
     * Escribe elemento Conductores (capitán) según AFIP
     */
    private function writeConductoresElement(\XMLWriter $w, $captain): void
    {
        $w->startElement('conductores');
            $w->startElement('Conductor');
                // nombre (obligatorio, C150)
                $nombre = $captain->full_name ?? trim($captain->first_name . ' ' . $captain->last_name);
                $w->writeElement('nombre', substr(htmlspecialchars($nombre), 0, 150));
                
                // tipDocIdent (obligatorio, C3) - DNI, PAS, CI, etc.
                $tipDoc = $this->mapDocumentType($captain->document_type ?? 'DNI');
                $w->writeElement('tipDocIdent', $tipDoc);
                
                // nroDocIdent (obligatorio, C16)
                $nroDoc = preg_replace('/[^0-9A-Za-z]/', '', $captain->document_number ?? '');
                $w->writeElement('nroDocIdent', substr($nroDoc, 0, 16));
            $w->endElement();
        $w->endElement();
    }

    /**
     * Mapea tipo de documento al código AFIP
     */
    private function mapDocumentType(?string $type): string
    {
        return match(strtoupper($type ?? 'DNI')) {
            'DNI' => 'DNI',
            'PASSPORT', 'PASAPORTE', 'PAS' => 'PAS',
            'CI', 'CEDULA' => 'CI',
            'LE', 'LIBRETA' => 'LE',
            'LC' => 'LC',
            default => 'DNI'
        };
    }

    /**
     * Escribe TRACKs de carga suelta - CORREGIDO
     * 
     * IMPORTANTE: cargasSueltasIdTrack es SOLO para items SIN contenedor (indCargSuelt=S)
     * Si todos los items tienen contenedor, este elemento va vacío.
     * Los TrackEnv de AFIP NO van aquí - AFIP los vincula internamente.
     * 
     * @param \XMLWriter $w
     * @param Voyage $voyage Para detectar si hay carga suelta real
     */
    /**
     * Escribe TRACKs de carga suelta - CORREGIDO
     * 
     * IMPORTANTE: cargasSueltasIdTrack es SOLO para items SIN contenedor (indCargSuelt=S)
     * Si todos los items tienen contenedor, NO se escribe este elemento.
     * Los TrackEnv de AFIP NO van aquí - AFIP los vincula internamente.
     * 
     * @param \XMLWriter $w
     * @param Voyage $voyage Para detectar si hay carga suelta real
     */
    private function writeCargasSueltasIdTrack(\XMLWriter $w, Voyage $voyage): void
    {
        // Obtener items SIN contenedor (carga suelta real)
        $itemsSinContenedor = $voyage->shipments()
            ->with('billsOfLading.shipmentItems.containers')
            ->get()
            ->flatMap(fn($s) => $s->billsOfLading)
            ->flatMap(fn($bl) => $bl->shipmentItems)
            ->filter(fn($item) => $item->containers->isEmpty());
        
        // Solo escribir el elemento si hay items sin contenedor
        // AFIP no acepta el elemento vacío
        if ($itemsSinContenedor->isEmpty()) {
            \Log::info('cargasSueltasIdTrack OMITIDO - todos los items tienen contenedor', [
                'voyage_id' => $voyage->id,
            ]);
            return; // No escribir nada
        }
        
        // Hay carga suelta, generar IDs únicos
        $w->startElement('cargasSueltasIdTrack');
        
        $year = date('Y');
        $country = 'AR';
        $usedIds = [];
        
        foreach ($itemsSinContenedor as $index => $item) {
            // Generar ID único por item de carga suelta
            // Formato AFIP: YYYYAR99999999X (16 chars)
            $sequence = str_pad($index + 1, 8, '0', STR_PAD_LEFT);
            $baseId = $year . $country . $sequence;
            
            // Calcular dígito verificador simple
            $checkDigit = $this->calculateTrackCheckDigit($baseId);
            $uniqueId = $baseId . $checkDigit;
            
            // Evitar duplicados dentro del mismo envío
            if (!in_array($uniqueId, $usedIds)) {
                $w->writeElement('cargaSueltaIdTrack', $uniqueId);
                $usedIds[] = $uniqueId;
            }
        }
        
        $w->endElement();
        
        \Log::info('cargasSueltasIdTrack generados para carga suelta', [
            'voyage_id' => $voyage->id,
            'items_sin_contenedor' => $itemsSinContenedor->count(),
            'ids_generados' => $usedIds,
        ]);
    }
    
    /**
     * Calcula dígito verificador para Track ID (formato AFIP)
     */
    private function calculateTrackCheckDigit(string $baseId): string
    {
        $sum = 0;
        for ($i = 0; $i < strlen($baseId); $i++) {
            $char = $baseId[$i];
            if (is_numeric($char)) {
                $sum += (int)$char;
            } else {
                $sum += ord($char) - 64; // A=1, B=2, etc.
            }
        }
        $remainder = $sum % 36;
        
        // Devolver letra si >= 10, sino número
        if ($remainder >= 10) {
            return chr(55 + $remainder); // 10=A, 11=B, etc.
        }
        return (string)$remainder;
    }

    /**
     * Escribe TRACKs de títulos de contenedores vacíos
     */
    private function writeTitTransContVaciosIdTrack(\XMLWriter $w, array $tracks): void
    {
        $tracksContVacios = $tracks['cont_vacios'] ?? $tracks['contenedores_vacios'] ?? [];
        
        // Solo escribir el elemento si hay contenedores vacíos
        if (!empty($tracksContVacios) && is_array($tracksContVacios)) {
            $w->startElement('titTransContVaciosIdTrack');
            foreach ($tracksContVacios as $trackId) {
                if (is_string($trackId) || is_numeric($trackId)) {
                    $w->writeElement('titTransContVacioIdTrack', (string)$trackId);
                }
            }
            $w->endElement();
        }
    }
    /**
     * Escribe IDs de contenedores con carga
     */
    private function writeContenedoresConCarga(\XMLWriter $w, Voyage $voyage): void
    {
        $w->startElement('contenedoresConCarga');
        
        // Obtener contenedores del voyage
        $containers = $voyage->shipments()
            ->with('billsOfLading.shipmentItems.containers')
            ->get()
            ->flatMap(fn($s) => $s->billsOfLading)
            ->flatMap(fn($bl) => $bl->shipmentItems)
            ->flatMap(fn($item) => $item->containers ?? collect())
            ->filter(fn($c) => $c->condition !== 'V') // Solo contenedores con carga (no vacíos)
            ->unique('container_number');
        
        foreach ($containers as $container) {
            if (!empty($container->container_number)) {
                $w->writeElement('idCont', substr($container->container_number, 0, 16));
            }
        }
        
        $w->endElement();
    }

    /**
     * Escribe Ruta Informática según WSDL AFIP
     * ORDEN CORRECTO según XSD: idRefUniTrs, descRutItinerarios, plazo, eventosProg
     */
    private function writeRutasInf(\XMLWriter $w, Voyage $voyage, string $codLugOperOrigen = '10073', string $codLugOperDest = '001'): void
    {
        $w->startElement('rutasInf');
            $w->startElement('RutInf');
                
                // 1. idRefUniTrs - vacío según XML exitoso Roberto
                $w->startElement('idRefUniTrs');
                    $w->writeElement('idRefUniTr', '');
                $w->endElement();
                
                // 2. descRutItinerarios (C500)
                $descripcion = sprintf(
                    'Viaje %s: %s (%s) a %s (%s)',
                    $voyage->voyage_number,
                    $voyage->originPort->name ?? 'ORIGEN',
                    $voyage->originPort->code ?? 'XXX',
                    $voyage->destinationPort->name ?? 'DESTINO',
                    $voyage->destinationPort->code ?? 'XXX'
                );
                $w->writeElement('descRutItinerarios', substr($descripcion, 0, 500));
                
                // 3. plazo (N3 - días de viaje)
                $plazo = 1;
                if ($voyage->departure_date && $voyage->estimated_arrival_date) {
                    $plazo = (int) max(1, floor($voyage->departure_date->diffInDays($voyage->estimated_arrival_date)));
                }
                $w->writeElement('plazo', (string)min($plazo, 999));
                
                // 4. eventosProg (mínimo PATAI y FITAI)
                // Obtener códigos AFIP desde el primer BL del shipment
                $w->startElement('eventosProg');
                    $this->writeEventoProg($w, $voyage->originPort, $voyage->departure_date, 'PATAI', 1, $codLugOperOrigen);
                    $this->writeEventoProg($w, $voyage->destinationPort, $voyage->estimated_arrival_date, 'FITAI', 2, $codLugOperDest);
                $w->endElement();
                
            $w->endElement(); // RutInf
        $w->endElement(); // rutasInf
    }

    /**
     * Escribe un EventoProg individual
     */
    private function writeEventoProg(\XMLWriter $w, $port, $fecha, string $tipoEvento, int $orden, ?string $codLugOper = null): void
    {
        $w->startElement('EventoProg');
            
            // codPais (obligatorio, C2)
            $codPais = $port->country->iso2_code ?? $port->country->alpha2_code ?? 'AR';
            $w->writeElement('codPais', strtoupper($codPais));
            
            // codAdu (obligatorio excepto EPTAI, C9)
            if ($tipoEvento !== 'EPTAI') {
                $codAdu = $this->getPortCustomsCode($port->code ?? '');
                $w->writeElement('codAdu', $codAdu);
            }
            
            // codCiu (obligatorio excepto EPTAI, C5 - UN/LOCODE)
            if ($tipoEvento !== 'EPTAI') {
                $w->writeElement('codCiu', substr($port->code ?? 'XXXXX', 0, 5));
            }
            
            // codLugOper (obligatorio excepto EPTAI, C9)
            if ($tipoEvento !== 'EPTAI') {
                $codLugOper = trim((string) $codLugOper);

                if ($codLugOper === '') {
                    throw new Exception(
                        "Falta el lugar operativo AFIP para el evento {$tipoEvento}."
                    );
                }

                $w->writeElement(
                    'codLugOper',
                    substr($codLugOper, 0, 9)
                );
            }
            
            // fecha (obligatorio excepto EPTAI, formato YYYYMMDDHHMMSS + zona horaria)
            // Ejemplo AFIP: 20080417000000-03
            // fecha formato AFIP: YYYYMMDD000000-03 (C17 - horas en ceros + zona horaria)
            if ($tipoEvento !== 'EPTAI' && $fecha) {
                $fechaFormateada = $fecha->format('Ymd') . '000000-03';
                $w->writeElement('fecha', $fechaFormateada);
            }
            
            // id (obligatorio, C5 - PATAI/EPTAI/FITAI)
            $w->writeElement('id', $tipoEvento);
            
            // orden (obligatorio, N2)
            $w->writeElement('orden', (string)$orden);
            
        $w->endElement();
    }

    /**
     * Escribe elemento Embarcación según AFIP
     */
    private function writeEmbarcacionElement(\XMLWriter $w, $vessel, Voyage $voyage): void
    {
        $w->startElement('embarcacion');
            
            // codPais (obligatorio, C2 - país de bandera)
            $codPais = $vessel->flagCountry->alpha2_code ?? 'AR';
            $w->writeElement('codPais', strtoupper($codPais));
            
            // id (obligatorio, C10 - matrícula)
            $w->writeElement('id', substr($vessel->registration_number ?? 'SIN_REG', 0, 10));
            
            // nombre (obligatorio, C50)
            $w->writeElement('nombre', substr(htmlspecialchars($vessel->name ?? 'SIN_NOMBRE'), 0, 50));
            
            // Determinar si es convoy (más de 1 embarcación en el viaje)
            $esConvoy = $voyage->shipments->count() > 1;
            
            // tipEmb (obligatorio, C3 - EMP/REM/BUM/BAR)
            // Contextual: autopropulsado (BUM) como cabecera de convoy → EMP ante AFIP
            $tipEmb = $this->mapVesselType($vessel->vesselType->code ?? 'BAR');
            if ($esConvoy && $tipEmb === 'BUM') {
                $tipEmb = 'EMP';
            }
            $w->writeElement('tipEmb', $tipEmb);
            
            // indIntegraConvoy (obligatorio, S/N)
            // AFIP: Si el viaje tiene más de 1 embarcación, TODOS integran convoy (S)
            // Solo autopropulsados que viajan SOLOS llevan indIntegraConvoy=N
            $integraConvoy = $esConvoy ? 'S' : 'N';
            $w->writeElement('indIntegraConvoy', $integraConvoy);
            
            // idFiscalATARemol (SOLO si integra convoy - CUIT del ATA remolcador)
            // AFIP: "Si indIntegraConvoy=N, no debe ser informado"
            if ($integraConvoy === 'S' && $tipEmb === 'BAR') {
                $w->writeElement('idFiscalATARemol', preg_replace('/[^0-9]/', '', $this->company->tax_id));
            }
            
        $w->endElement();
    }

    /**
     * Mapea tipo de embarcación al código AFIP
     */
    private function mapVesselType(?string $code): string
    {
        return match(strtoupper($code ?? 'BAR')) {
            'EMP', 'EMPUJE', 'EMPUJADOR' => 'EMP',
            'REM', 'REMOLCADOR' => 'REM',
            'BUM', 'BUQUE', 'BUQUE_MOTOR', 'SELF_CARGO_001' => 'BUM',
            'BAR', 'BARCAZA' => 'BAR',
            default => 'BAR'
        };
    }

    /**
     * Obtener tokens WSAA - MÉTODO SIN CAMBIOS (funciona correctamente)
     */
    private function getWSAATokens(string $serviceName = 'wgesregsintia2'): array
    {
        try {
            // Verificar cache primero
            $cachedToken = \App\Models\WsaaToken::getValidToken(
                $this->company->id, 
                $serviceName, 
                $this->config['environment'] ?? 'testing'
            );
            
            if ($cachedToken) {
                $cachedToken->markAsUsed();
                return [
                    'token' => $cachedToken->token,
                    'sign' => $cachedToken->sign,
                    'cuit' => $this->company->tax_id
                ];
            }
            
            // Generar nuevo token
            $certificateManager = new \App\Services\Webservice\CertificateManagerService($this->company);
            $certData = $certificateManager->readCertificate();
            
            if (!$certData) {
                throw new Exception("No se pudo leer el certificado .p12");
            }
            
            $loginTicket = $this->generateLoginTicket($serviceName);
            $signedTicket = $this->signLoginTicket($loginTicket, $certData);
            $wsaaTokens = $this->callWSAA($signedTicket);
            
            // Guardar en cache
            \App\Models\WsaaToken::createToken([
                'company_id' => $this->company->id,
                'service_name' => $serviceName,
                'environment' => $this->config['environment'] ?? 'testing',
                'token' => $wsaaTokens['token'],
                'sign' => $wsaaTokens['sign'],
                'issued_at' => now(),
                'expires_at' => now()->addHours(12),
                'generation_time' => date('c'),
                'unique_id' => uniqid(),
                'certificate_used' => $this->company->certificate_path,
                'usage_count' => 0,
                'status' => 'active',
                'created_by_process' => 'SimpleXmlGenerator',
                'creation_context' => ['method' => 'getWSAATokens', 'service' => $serviceName],
            ]);
            
            return [
                'token' => $wsaaTokens['token'],
                'sign' => $wsaaTokens['sign'],
                'cuit' => $this->company->tax_id
            ];
            
        } catch (Exception $e) {
            \Log::info("WSAA ERROR: " . $e->getMessage());
            throw $e;
        }
    }

    private function generateLoginTicket(string $serviceName = 'wgesregsintia2'): string
    {
        $uniqueId = (int) min(time(), 2147483647);
        $nowUtc = new \DateTime('now', new \DateTimeZone('UTC'));
        $generationTime = (clone $nowUtc)->sub(new \DateInterval('PT5M'));
        $expirationTime = (clone $nowUtc)->add(new \DateInterval('PT12H'));
        
        $generationTimeStr = $generationTime->format('Y-m-d\TH:i:s\Z');
        $expirationTimeStr = $expirationTime->format('Y-m-d\TH:i:s\Z');
        
        return '<?xml version="1.0" encoding="UTF-8"?>' .
               '<loginTicketRequest version="1.0">' .
                   '<header>' .
                       '<uniqueId>' . $uniqueId . '</uniqueId>' .
                       '<generationTime>' . $generationTimeStr . '</generationTime>' .
                       '<expirationTime>' . $expirationTimeStr . '</expirationTime>' .
                   '</header>' .
                   '<service>' . $serviceName . '</service>' .
               '</loginTicketRequest>';
    }

    private function signLoginTicket(string $loginTicket, array $certData): string
    {
        $loginTicketFile = tempnam(sys_get_temp_dir(), 'loginticket_') . '.xml';
        file_put_contents($loginTicketFile, $loginTicket);
        
        $certFile = tempnam(sys_get_temp_dir(), 'cert_') . '.pem';
        $certContent = $certData['cert'];
        if (isset($certData['extracerts']) && is_array($certData['extracerts'])) {
            foreach ($certData['extracerts'] as $extraCert) {
                $certContent .= "\n" . $extraCert;
            }
        }
        file_put_contents($certFile, $certContent);
        
        $keyFile = tempnam(sys_get_temp_dir(), 'key_') . '.pem';
        file_put_contents($keyFile, $certData['pkey']);
        
        $outputFile = tempnam(sys_get_temp_dir(), 'signed_') . '.p7s';
        
        $command = sprintf(
            'openssl smime -sign -in %s -out %s -signer %s -inkey %s -outform DER -nodetach 2>&1',
            escapeshellarg($loginTicketFile),
            escapeshellarg($outputFile),
            escapeshellarg($certFile),
            escapeshellarg($keyFile)
        );
        
        exec($command, $output, $returnCode);
        
        if ($returnCode === 0 && file_exists($outputFile)) {
            $signature = file_get_contents($outputFile);
            $signatureBase64 = base64_encode($signature);
        } else {
            $result = openssl_pkcs7_sign(
                $loginTicketFile,
                $outputFile,
                $certData['cert'],
                $certData['pkey'],
                [],
                PKCS7_BINARY | PKCS7_NOATTR
            );
            
            if (!$result || !file_exists($outputFile)) {
                throw new Exception("Error firmando LoginTicket: " . implode(', ', $output));
            }
            
            $signature = file_get_contents($outputFile);
            $signatureBase64 = base64_encode($signature);
        }
        
        @unlink($loginTicketFile);
        @unlink($certFile);
        @unlink($keyFile);
        @unlink($outputFile);
        
        return $signatureBase64;
    }

    private function callWSAA(string $signedTicket): array
    {
        $wsdlUrl = 'https://wsaahomo.afip.gov.ar/ws/services/LoginCms?wsdl';
        
        $client = new \SoapClient($wsdlUrl, [
            'trace' => true,
            'exceptions' => true,
            'stream_context' => stream_context_create([
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false
                ]
            ])
        ]);
        
        $response = $client->loginCms(['in0' => $signedTicket]);
        
        if (!isset($response->loginCmsReturn)) {
            throw new Exception("Error en respuesta WSAA");
        }
        
        $xml = simplexml_load_string($response->loginCmsReturn);
        
        return [
            'token' => (string)$xml->credentials->token,
            'sign' => (string)$xml->credentials->sign
        ];
    }

    /**
     * Validación mínima del XML generado
     */
    public function validateXml(string $xml): bool
    {
        $dom = new \DOMDocument();
        return @$dom->loadXML($xml) !== false;
    }

    /**
     * PASO 3: RegistrarConvoy - Agrupar múltiples MIC/DTA en convoy
     * Genera XML según especificación exacta AFIP
     * 
     * @param array $convoyData Datos del convoy
     * @param string $transactionId ID único de transacción (máx 15 chars)
     * @return string|null XML completo o null si error
     */
    public function createRegistrarConvoyXml(array $convoyData, string $transactionId): ?string
    {
        try {
            // Validar datos obligatorios
            if (empty($convoyData['remolcador_micdta_id'])) {
                throw new Exception('ID MIC/DTA remolcador obligatorio');
            }
            
            if (empty($convoyData['barcazas_micdta_ids']) || !is_array($convoyData['barcazas_micdta_ids'])) {
                throw new Exception('IDs MIC/DTA barcazas obligatorios');
            }
            
           // Obtener tokens WSAA para wgesregsintia2
            $wsaaTokens = $this->getWSAATokens('wgesregsintia2');
            $cuit = preg_replace('/[^0-9]/', '', $this->company->tax_id);

            // Crear documento XML (mismo patrón que RegistrarMicDta - sin soap:Header)
            $xml = '<?xml version="1.0" encoding="UTF-8"?>';
            $xml .= '<soap:Envelope ';
            $xml .= 'xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" ';
            $xml .= 'xmlns:xsd="http://www.w3.org/2001/XMLSchema" ';
            $xml .= 'xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">';
            
            // Body con método RegistrarConvoy (autenticación dentro del Body)
            $xml .= '<soap:Body>';
            $xml .= '<RegistrarConvoy xmlns="' . self::AFIP_NAMESPACE . '">';
            
            // Autenticación empresa con Token y Sign (igual que RegistrarMicDta)
            $xml .= '<argWSAutenticacionEmpresa>';
            $xml .= '<Token>' . htmlspecialchars($wsaaTokens['token']) . '</Token>';
            $xml .= '<Sign>' . htmlspecialchars($wsaaTokens['sign']) . '</Sign>';
            $xml .= '<CuitEmpresaConectada>' . htmlspecialchars($cuit) . '</CuitEmpresaConectada>';
            $xml .= '<TipoAgente>TRSP</TipoAgente>';
            $xml .= '<Rol>TRSP</Rol>';
            $xml .= '</argWSAutenticacionEmpresa>';
            
            // Parámetros específicos RegistrarConvoy
            $xml .= '<argRegistrarConvoyParam>';
            
            // ID Transacción (máximo 15 caracteres según AFIP)
            $xml .= '<idTransaccion>' . htmlspecialchars(substr($transactionId, 0, 15)) . '</idTransaccion>';
            
            // ID MIC/DTA del remolcador (máximo 16 caracteres)
            $remolcadorId = substr($convoyData['remolcador_micdta_id'], 0, 16);
            $xml .= '<idMicDtaRemol>' . htmlspecialchars($remolcadorId) . '</idMicDtaRemol>';
            
            // Lista de IDs MIC/DTA de barcazas del convoy
            $xml .= '<idMicDta>';
            foreach ($convoyData['barcazas_micdta_ids'] as $barcazaId) {
                $barcazaIdTrimmed = substr($barcazaId, 0, 16); // Máximo 16 caracteres
                $xml .= '<idMicDta>' . htmlspecialchars($barcazaIdTrimmed) . '</idMicDta>';
            }
            $xml .= '</idMicDta>';
            
            $xml .= '</argRegistrarConvoyParam>';
            $xml .= '</RegistrarConvoy>';
            $xml .= '</soap:Body>';
            $xml .= '</soap:Envelope>';

            return $xml;

        } catch (Exception $e) {
            \Log::info("SimpleXmlGenerator: Error creando XML RegistrarConvoy - " . $e->getMessage());
            return null;
        }
    }

    /**
     * PASO COMPLEMENTARIO: AsignarATARemol - Asignar CUIT del ATA Remolcador
     * Genera XML según especificación exacta AFIP
     * 
     * @param array $asignacionData Datos de asignación
     * @param string $transactionId ID único de transacción (máx 15 chars)
     * @return string|null XML completo o null si error
     */
    public function createAsignarATARemolXml(array $asignacionData, string $transactionId): ?string
    {
        try {
            // Validar datos obligatorios
            if (empty($asignacionData['id_micdta'])) {
                throw new Exception('ID MIC/DTA obligatorio');
            }
            
            if (empty($asignacionData['cuit_ata_remolcador'])) {
                throw new Exception('CUIT ATA Remolcador obligatorio');
            }

            // Validar formato CUIT (11 dígitos)
            $cuitRemolcador = preg_replace('/[^0-9]/', '', $asignacionData['cuit_ata_remolcador']);
            if (strlen($cuitRemolcador) !== 11) {
                throw new Exception('CUIT ATA Remolcador debe tener 11 dígitos');
            }

            // Obtener tokens WSAA
            $wsaaTokens = $this->getWSAATokens();
            
            // Crear documento XML
            $xml = '<?xml version="1.0" encoding="UTF-8"?>';
            
            // Envelope SOAP con namespaces
            $xml .= '<soap:Envelope ';
            $xml .= 'xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" ';
            $xml .= 'xmlns:xsd="http://www.w3.org/2001/XMLSchema" ';
            $xml .= 'xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">';
            
            // Header con autenticación WSAA
            $xml .= '<soap:Header>';
            $xml .= '<Auth>';
            $xml .= '<Token>' . htmlspecialchars($wsaaTokens['token']) . '</Token>';
            $xml .= '<Sign>' . htmlspecialchars($wsaaTokens['sign']) . '</Sign>';
            $xml .= '<Cuit>' . htmlspecialchars($wsaaTokens['cuit']) . '</Cuit>';
            $xml .= '</Auth>';
            $xml .= '</soap:Header>';
            
            // Body con método AsignarATARemol
            $xml .= '<soap:Body>';
            $xml .= '<AsignarATARemol xmlns="' . self::AFIP_NAMESPACE . '">';
            
            // Autenticación empresa (obligatorio AFIP)
            $xml .= '<argWSAutenticacionEmpresa>';
            $xml .= '<CuitEmpresaConectada>' . htmlspecialchars($wsaaTokens['cuit']) . '</CuitEmpresaConectada>';
            $xml .= '<TipoAgente>TRSP</TipoAgente>'; // Transportista
            $xml .= '<Rol>TRSP</Rol>'; // Rol transportista
            $xml .= '</argWSAutenticacionEmpresa>';
            
            // Parámetros específicos AsignarATARemol
            $xml .= '<argAsignarATARemolParam>';
            
            // ID MIC/DTA (máximo 16 caracteres según AFIP)
            $idMicDta = substr($asignacionData['id_micdta'], 0, 16);
            $xml .= '<idMicDta>' . htmlspecialchars($idMicDta) . '</idMicDta>';
            
            // CUIT ATA Remolcador (máximo 14 caracteres, pero normalmente 11)
            $xml .= '<idFiscalATARemol>' . htmlspecialchars($cuitRemolcador) . '</idFiscalATARemol>';
            
            $xml .= '</argAsignarATARemolParam>';
            $xml .= '</AsignarATARemol>';
            $xml .= '</soap:Body>';
            $xml .= '</soap:Envelope>';

            return $xml;

        } catch (Exception $e) {
            \Log::info("SimpleXmlGenerator: Error creando XML AsignarATARemol - " . $e->getMessage());
            return null;
        }
    }

    /**
     * PASO 4: RegistrarSalidaZonaPrimaria - Registrar salida de puerto
     * Genera XML según especificación exacta AFIP y XML exitoso cliente
     * 
     * CORREGIDO: Token y Sign DENTRO de argWSAutenticacionEmpresa (no en Header)
     * 
     * @param array $salidaData Datos de salida (requiere 'nro_viaje')
     * @param string $transactionId ID único de transacción (máx 15 chars)
     * @return string|null XML completo o null si error
     */
    public function createRegistrarSalidaZonaPrimariaXml(array $salidaData, string $transactionId): ?string
    {
        try {
            // Validar datos obligatorios
            if (empty($salidaData['nro_viaje'])) {
                throw new Exception('Número de viaje (nroViaje) obligatorio');
            }
            
            // Obtener tokens WSAA
            $wsaaTokens = $this->getWSAATokens();
            
            // Crear documento XML
            $xml = '<?xml version="1.0"?>';
            
            // Envelope SOAP con namespaces (formato exacto XML exitoso cliente)
            $xml .= '<SOAP-ENV:Envelope ';
            $xml .= 'xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/" ';
            $xml .= 'xmlns:xsd="http://www.w3.org/2001/XMLSchema" ';
            $xml .= 'xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">';
            
            // Body con método RegistrarSalidaZonaPrimaria (SIN soap:Header)
            $xml .= '<SOAP-ENV:Body>';
            $xml .= '<RegistrarSalidaZonaPrimaria xmlns="' . self::AFIP_NAMESPACE . '">';
            
            // Autenticación empresa con Token y Sign DENTRO (según XML exitoso)
            $xml .= '<argWSAutenticacionEmpresa>';
            $xml .= '<Token>' . htmlspecialchars($wsaaTokens['token']) . '</Token>';
            $xml .= '<Sign>' . htmlspecialchars($wsaaTokens['sign']) . '</Sign>';
            $xml .= '<CuitEmpresaConectada>' . htmlspecialchars($wsaaTokens['cuit']) . '</CuitEmpresaConectada>';
            $xml .= '<TipoAgente>TRSP</TipoAgente>';
            $xml .= '<Rol>TRSP</Rol>';
            $xml .= '</argWSAutenticacionEmpresa>';
            
            // Número de viaje (único parámetro requerido)
            $xml .= '<argNroViaje>' . htmlspecialchars($salidaData['nro_viaje']) . '</argNroViaje>';
            
            $xml .= '</RegistrarSalidaZonaPrimaria>';
            $xml .= '</SOAP-ENV:Body>';
            $xml .= '</SOAP-ENV:Envelope>';
            
            return $xml;
            
        } catch (Exception $e) {
            \Log::info("SimpleXmlGenerator: Error creando XML RegistrarSalidaZonaPrimaria - " . $e->getMessage());
            return null;
        }
    }

    /**
     * SolicitarAnularMicDta - Solicitar anulación de MIC/DTA
     * Genera XML según formato exacto XML exitoso Roberto
     * 
     * CORREGIDO: Token y Sign DENTRO de argWSAutenticacionEmpresa (no en Header separado)
     * 
     * @param array $anulacionData Datos de anulación
     * @param string $transactionId ID único de transacción (máx 15 chars)
     * @return string|null XML completo o null si error
     */
    public function createSolicitarAnularMicDtaXml(array $anulacionData, string $transactionId): ?string
    {
        try {
            // Validar datos obligatorios
            if (empty($anulacionData['id_micdta'])) {
                throw new Exception('ID MIC/DTA obligatorio');
            }
            
            if (empty($anulacionData['desc_motivo'])) {
                throw new Exception('Descripción del motivo de anulación obligatoria');
            }

            // Validar longitudes según AFIP
            if (strlen($anulacionData['id_micdta']) > 16) {
                throw new Exception('ID MIC/DTA no puede exceder 16 caracteres');
            }
            
            if (strlen($anulacionData['desc_motivo']) > 50) {
                throw new Exception('Descripción del motivo no puede exceder 50 caracteres');
            }

            // Obtener tokens WSAA
            $wsaaTokens = $this->getWSAATokens();
            
            // Crear documento XML (formato exacto XML exitoso Roberto)
            $xml = '<?xml version="1.0"?>';
            
            // Envelope SOAP con namespaces (formato Roberto exitoso)
            $xml .= '<SOAP-ENV:Envelope ';
            $xml .= 'xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/" ';
            $xml .= 'xmlns:xsd="http://www.w3.org/2001/XMLSchema" ';
            $xml .= 'xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">';
            
            // Body directo SIN soap:Header (Token/Sign van dentro de argWSAutenticacionEmpresa)
            $xml .= '<SOAP-ENV:Body>';
            $xml .= '<SolicitarAnularMicDta xmlns="' . self::AFIP_NAMESPACE . '">';
            
            // Autenticación empresa con Token y Sign DENTRO (según XML exitoso Roberto)
            $xml .= '<argWSAutenticacionEmpresa>';
            $xml .= '<Token>' . htmlspecialchars($wsaaTokens['token']) . '</Token>';
            $xml .= '<Sign>' . htmlspecialchars($wsaaTokens['sign']) . '</Sign>';
            $xml .= '<CuitEmpresaConectada>' . htmlspecialchars($wsaaTokens['cuit']) . '</CuitEmpresaConectada>';
            $xml .= '<TipoAgente>TRSP</TipoAgente>';
            $xml .= '<Rol>TRSP</Rol>';
            $xml .= '</argWSAutenticacionEmpresa>';
            
            // Parámetros específicos SolicitarAnularMicDta
            $xml .= '<argSolicitarAnularMicDtaParam>';
            
            // ID MIC/DTA (máximo 16 caracteres)
            $xml .= '<idMicDta>' . htmlspecialchars($anulacionData['id_micdta']) . '</idMicDta>';
            
            // Descripción del motivo (máximo 50 caracteres)
            $xml .= '<descMotivo>' . htmlspecialchars($anulacionData['desc_motivo']) . '</descMotivo>';
            
            $xml .= '</argSolicitarAnularMicDtaParam>';
            $xml .= '</SolicitarAnularMicDta>';
            $xml .= '</SOAP-ENV:Body>';
            $xml .= '</SOAP-ENV:Envelope>';

            return $xml;

        } catch (Exception $e) {
            \Log::info("SimpleXmlGenerator: Error creando XML SolicitarAnularMicDta - " . $e->getMessage());
            return null;
        }
    }

    /**
     * RectifConvoyMicDta - Rectificar convoy/MIC-DTA existente
     * Genera XML según especificación exacta AFIP
     * 
     * @param array $rectifData Datos de rectificación
     * @param string $transactionId ID único de transacción (máx 15 chars)
     * @return string|null XML completo o null si error
     */
    public function createRectifConvoyMicDtaXml(array $rectifData, string $transactionId): ?string
    {
        try {
            // Validar datos obligatorios AFIP
            if (empty($rectifData['nro_viaje'])) {
                throw new Exception('Número de viaje (nroViaje) obligatorio');
            }
            
            if (empty($rectifData['desc_motivo'])) {
                throw new Exception('Descripción del motivo de rectificación obligatoria');
            }

            // Validar que al menos uno de los tipos de rectificación esté presente
            $tieneRectifConvoy = !empty($rectifData['rectif_convoy']);
            $tieneRectifMicDta = !empty($rectifData['rectif_micdta']);
            
            if (!$tieneRectifConvoy && !$tieneRectifMicDta) {
                throw new Exception('Debe especificar rectif_convoy y/o rectif_micdta');
            }

            // Obtener tokens WSAA
            $wsaa = $this->getWSAATokens('wgesregsintia2');

            // Crear XMLWriter
            $w = new \XMLWriter();
            $w->openMemory();
            $w->startDocument('1.0', 'UTF-8');

            // Envelope SOAP
            $w->startElementNs('soap', 'Envelope', 'http://schemas.xmlsoap.org/soap/envelope/');
            $w->writeAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
            $w->writeAttribute('xmlns:xsd', 'http://www.w3.org/2001/XMLSchema');
            
            $w->startElementNs('soap', 'Body', 'http://schemas.xmlsoap.org/soap/envelope/');
                $w->startElement('RectifConvoyMicDta');
                $w->writeAttribute('xmlns', self::AFIP_NAMESPACE);

                // Autenticación empresa
                $w->startElement('argWSAutenticacionEmpresa');
                    $w->writeElement('Token', $wsaa['token']);
                    $w->writeElement('Sign', $wsaa['sign']);
                    $w->writeElement('CuitEmpresaConectada', (string)$this->company->tax_id);
                    $w->writeElement('TipoAgente', 'TRSP');
                    $w->writeElement('Rol', 'TRSP');
                $w->endElement();

                // Parámetros RectifConvoyMicDta
                $w->startElement('argRectifConvoyMicDtaParam');
                    
                    // ID Transacción (máximo 15 caracteres AFIP)
                    $w->writeElement('idTransaccion', substr($transactionId, 0, 15));
                    
                    // Número de viaje (obligatorio, C(13) según manual AFIP)
                    $nroViaje = substr((string)($rectifData['nro_viaje'] ?? ''), 0, 15);
                    $w->writeElement('nroViaje', $nroViaje);
                    
                    // Rectificar configuración de convoy (si se especifica)
                    if ($tieneRectifConvoy) {
                        $w->startElement('rectifConvoy');
                            
                            if (!empty($rectifData['rectif_convoy']['id_micdta_remol'])) {
                                $w->writeElement('idMicDtaRemol', substr($rectifData['rectif_convoy']['id_micdta_remol'], 0, 16));
                            }
                            
                            if (!empty($rectifData['rectif_convoy']['barcazas_micdta_ids'])) {
                                $w->startElement('idMicDta');
                                foreach ($rectifData['rectif_convoy']['barcazas_micdta_ids'] as $barcazaId) {
                                    $w->writeElement('idMicDta', substr($barcazaId, 0, 16));
                                }
                                $w->endElement(); // idMicDta
                            }
                            
                        $w->endElement(); // rectifConvoy
                    }
                    
                    // Rectificar datos MIC/DTA (si se especifica)
                    if ($tieneRectifMicDta) {
                        $w->startElement('rectifMicDta');
                            
                            // ID del MIC/DTA a rectificar
                            if (!empty($rectifData['rectif_micdta']['id_micdta'])) {
                                $w->writeElement('idMicDta', substr($rectifData['rectif_micdta']['id_micdta'], 0, 16));
                            }
                            
                            // Conductores (puede ser nil según AFIP)
                            $w->startElement('conductores');
                            if (!empty($rectifData['rectif_micdta']['conductores'])) {
                                foreach ($rectifData['rectif_micdta']['conductores'] as $conductor) {
                                    $w->startElement('Conductor');
                                    // Agregar datos del conductor si es necesario
                                    $w->endElement();
                                }
                            } else {
                                // Elementos nil según ejemplo AFIP
                                $w->startElement('Conductor');
                                $w->writeAttribute('xsi:nil', 'true');
                                $w->endElement();
                            }
                            $w->endElement(); // conductores
                            
                            // Transportista
                            if (!empty($rectifData['rectif_micdta']['transportista'])) {
                                $transportista = $rectifData['rectif_micdta']['transportista'];
                                $w->startElement('transportista');
                                    $w->writeElement('nombre', htmlspecialchars($transportista['nombre'] ?? $this->company->legal_name));
                                    $w->startElement('domicilio');
                                    $w->writeAttribute('xsi:nil', 'true');
                                    $w->endElement();
                                    $w->writeElement('codPais', $transportista['cod_pais'] ?? '032'); // Argentina
                                    $w->writeElement('idFiscal', $transportista['id_fiscal'] ?? (string)$this->company->tax_id);
                                    $w->writeElement('tipTrans', $transportista['tip_trans'] ?? 'TER'); // Terrestre
                                $w->endElement(); // transportista
                            }
                            
                            // Propietario del vehículo
                            if (!empty($rectifData['rectif_micdta']['prop_vehiculo'])) {
                                $propVehiculo = $rectifData['rectif_micdta']['prop_vehiculo'];
                                $w->startElement('propVehiculo');
                                    $w->writeElement('nombre', htmlspecialchars($propVehiculo['nombre'] ?? $this->company->legal_name));
                                    $w->startElement('domicilio');
                                    $w->writeAttribute('xsi:nil', 'true');
                                    $w->endElement();
                                    $w->writeElement('codPais', $propVehiculo['cod_pais'] ?? '032'); // Argentina
                                    $w->writeElement('idFiscal', $propVehiculo['id_fiscal'] ?? (string)$this->company->tax_id);
                                $w->endElement(); // propVehiculo
                            }
                            
                            // Rectificar embarcación
                            if (!empty($rectifData['rectif_micdta']['rectif_embarcacion'])) {
                                $embarcacion = $rectifData['rectif_micdta']['rectif_embarcacion'];
                                $w->startElement('rectifEmbarcacion');
                                    $w->writeElement('codPais', $embarcacion['cod_pais'] ?? '032'); // Argentina
                                    $w->writeElement('id', $embarcacion['id'] ?? 'SIN_ID');
                                    $w->writeElement('nombre', htmlspecialchars($embarcacion['nombre'] ?? 'SIN_NOMBRE'));
                                    $w->writeElement('tipEmb', $embarcacion['tip_emb'] ?? 'BAR'); // Barcaza
                                $w->endElement(); // rectifEmbarcacion
                            }
                            
                        $w->endElement(); // rectifMicDta
                    }
                    
                    // Descripción del motivo (obligatorio)
                    $w->writeElement('descMotivo', htmlspecialchars(substr($rectifData['desc_motivo'], 0, 50)));
                    
                $w->endElement(); // argRectifConvoyMicDtaParam
                $w->endElement(); // RectifConvoyMicDta
            $w->endElement(); // Body
            $w->endElement(); // Envelope

            $w->endDocument();
            return $w->outputMemory();

        } catch (Exception $e) {
            \Log::info('Error en createRectifConvoyMicDtaXml: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * ConsultarMicDtaAsig - Consulta de MIC/DTA asignados al ATA remolcador/empujador
     * Genera XML según especificación AFIP para consultar MIC/DTA asignados
     * 
     * @param array $consultaData Datos de consulta (opcional: filtros)
     * @param string $transactionId ID único de transacción (máx 15 chars)
     * @return string|null XML completo o null si error
     */
    public function createConsultarMicDtaAsigXml(array $consultaData = [], string $transactionId = ''): ?string
    {
        try {
            // Obtener tokens WSAA
            $wsaa = $this->getWSAATokens();

            // XML según manual AFIP y XML exitoso Roberto: solo autenticación, sin parámetros adicionales
            $xml = '<?xml version="1.0"?>';
            $xml .= '<SOAP-ENV:Envelope ';
            $xml .= 'xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/" ';
            $xml .= 'xmlns:xsd="http://www.w3.org/2001/XMLSchema" ';
            $xml .= 'xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">';

            $xml .= '<SOAP-ENV:Body>';
            $xml .= '<ConsultarMicDtaAsig xmlns="' . self::AFIP_NAMESPACE . '">';

            $xml .= '<argWSAutenticacionEmpresa>';
            $xml .= '<Token>' . htmlspecialchars($wsaa['token']) . '</Token>';
            $xml .= '<Sign>' . htmlspecialchars($wsaa['sign']) . '</Sign>';
            $xml .= '<CuitEmpresaConectada>' . htmlspecialchars($wsaa['cuit']) . '</CuitEmpresaConectada>';
            $xml .= '<TipoAgente>TRSP</TipoAgente>';
            $xml .= '<Rol>TRSP</Rol>';
            $xml .= '</argWSAutenticacionEmpresa>';

            $xml .= '</ConsultarMicDtaAsig>';
            $xml .= '</SOAP-ENV:Body>';
            $xml .= '</SOAP-ENV:Envelope>';

            return $xml;

        } catch (Exception $e) {
            \Log::info('Error en createConsultarMicDtaAsigXml: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * ConsultarTitEnviosReg - Consultar títulos y envíos registrados
     * Genera XML según especificación exacta AFIP
     * 
     * @param string $transactionId ID único de transacción (opcional)
     * @return string|null XML completo o null si error
     */
    public function createConsultarTitEnviosRegXml(string $transactionId = ''): ?string
    {
        try {
            // Obtener tokens WSAA
            $wsaa = $this->getWSAATokens();

            // XML con formato exacto de Roberto (SOAP-ENV namespace)
            $xml = '<?xml version="1.0"?>';
            $xml .= '<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">';
            $xml .= '<SOAP-ENV:Body>';
            $xml .= '<ConsultarTitEnviosReg xmlns="' . self::AFIP_NAMESPACE . '">';
            
            $xml .= '<argWSAutenticacionEmpresa>';
            $xml .= '<Token>' . htmlspecialchars($wsaa['token']) . '</Token>';
            $xml .= '<Sign>' . htmlspecialchars($wsaa['sign']) . '</Sign>';
            $xml .= '<CuitEmpresaConectada>' . htmlspecialchars((string)$this->company->tax_id) . '</CuitEmpresaConectada>';
            $xml .= '<TipoAgente>TRSP</TipoAgente>';
            $xml .= '<Rol>TRSP</Rol>';
            $xml .= '</argWSAutenticacionEmpresa>';
            
            $xml .= '</ConsultarTitEnviosReg>';
            $xml .= '</SOAP-ENV:Body>';
            $xml .= '</SOAP-ENV:Envelope>';

            return $xml;

        } catch (Exception $e) {
            \Log::info('Error en createConsultarTitEnviosRegXml: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * RegistrarArriboZonaPrimaria - Registrar arribo a zona primaria (llegada)
     * Genera XML según especificación AFIP (contraparte de salida)
     * 
     * @param array $arriboData Datos de arribo (nro_viaje requerido)
     * @param string $transactionId ID único de transacción (máx 15 chars)
     * @return string|null XML completo o null si error
     */
    public function createRegistrarArriboZonaPrimariaXml(array $arriboData, string $transactionId = ''): ?string
    {
        try {
            // Validar datos obligatorios
            if (empty($arriboData['nro_viaje'])) {
                throw new Exception('Número de viaje (nroViaje) obligatorio');
            }
            if (empty($arriboData['cod_adu'])) {
                throw new Exception('Código de aduana (codAdu) obligatorio');
            }
            if (empty($arriboData['cod_lug_oper'])) {
                throw new Exception('Código de lugar operativo (codLugOper) obligatorio');
            }

            // Obtener tokens WSAA
            $wsaa = $this->getWSAATokens();

            // Generar idTransaccion si no se proporcionó
            if (empty($transactionId)) {
                $transactionId = (string)time();
            }

            // XML con formato SOAP-ENV (igual al XML exitoso de Roberto)
            $xml = '<?xml version="1.0"?>';
            $xml .= '<SOAP-ENV:Envelope ';
            $xml .= 'xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/" ';
            $xml .= 'xmlns:xsd="http://www.w3.org/2001/XMLSchema" ';
            $xml .= 'xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">';

            $xml .= '<SOAP-ENV:Body>';
            $xml .= '<RegistrarArriboZonaPrimaria xmlns="' . self::AFIP_NAMESPACE . '">';

            // Autenticación empresa con Token y Sign DENTRO
            $xml .= '<argWSAutenticacionEmpresa>';
            $xml .= '<Token>' . htmlspecialchars($wsaa['token']) . '</Token>';
            $xml .= '<Sign>' . htmlspecialchars($wsaa['sign']) . '</Sign>';
            $xml .= '<CuitEmpresaConectada>' . htmlspecialchars($wsaa['cuit']) . '</CuitEmpresaConectada>';
            $xml .= '<TipoAgente>TRSP</TipoAgente>';
            $xml .= '<Rol>TRSP</Rol>';
            $xml .= '</argWSAutenticacionEmpresa>';

            // Parámetros específicos del método (según XML exitoso Roberto + nroViaje que AFIP exigió)
            $xml .= '<argRegistrarArriboZonaPrimariaParam>';
            $xml .= '<idTransaccion>' . htmlspecialchars(substr($transactionId, 0, 15)) . '</idTransaccion>';
            $xml .= '<codAdu>' . htmlspecialchars($arriboData['cod_adu']) . '</codAdu>';
            $xml .= '<codLugOper>' . htmlspecialchars($arriboData['cod_lug_oper']) . '</codLugOper>';
            if (!empty($arriboData['desc_amarre'])) {
                $xml .= '<descAmarre>' . htmlspecialchars($arriboData['desc_amarre']) . '</descAmarre>';
            } else {
                $xml .= '<descAmarre xsi:nil="true" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"/>';
            }
            $xml .= '<nroViaje>' . htmlspecialchars($arriboData['nro_viaje']) . '</nroViaje>';
            $xml .= '</argRegistrarArriboZonaPrimariaParam>';

            $xml .= '</RegistrarArriboZonaPrimaria>';
            $xml .= '</SOAP-ENV:Body>';
            $xml .= '</SOAP-ENV:Envelope>';

            return $xml;

        } catch (Exception $e) {
            \Log::info('Error en createRegistrarArriboZonaPrimariaXml: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * AnularTitulo - Anular títulos de transporte
     * Genera XML según especificación exacta AFIP
     * 
     * @param array $anulacionData Datos de anulación (id_titulo requerido)
     * @param string $transactionId ID único de transacción (opcional)
     * @return string|null XML completo o null si error
     */
    public function createAnularTituloXml(array $anulacionData, string $transactionId = ''): ?string
    {
        try {
            // Validar datos obligatorios
            if (empty($anulacionData['id_titulo'])) {
                throw new Exception('ID del título de transporte (idTitTrans) obligatorio');
            }

            // Validar longitud según AFIP (basado en otros métodos)
            if (strlen($anulacionData['id_titulo']) > 50) {
                throw new Exception('ID del título no puede exceder 50 caracteres');
            }

            // Obtener tokens WSAA
            $wsaa = $this->getWSAATokens();

            // Crear XMLWriter
            $w = new \XMLWriter();
            $w->openMemory();
            $w->startDocument('1.0', 'UTF-8');

            // Envelope SOAP
            $w->startElementNs('soap', 'Envelope', 'http://schemas.xmlsoap.org/soap/envelope/');
            $w->writeAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
            $w->writeAttribute('xmlns:xsd', 'http://www.w3.org/2001/XMLSchema');
            
            $w->startElementNs('soap', 'Body', 'http://schemas.xmlsoap.org/soap/envelope/');
                $w->startElement('AnularTitulo');
                $w->writeAttribute('xmlns', self::AFIP_NAMESPACE);

                // Autenticación empresa (obligatorio)
                $w->startElement('argWSAutenticacionEmpresa');
                    $w->writeElement('Token', $wsaa['token']);
                    $w->writeElement('Sign', $wsaa['sign']);
                    $w->writeElement('CuitEmpresaConectada', (string)$this->company->tax_id);
                    $w->writeElement('TipoAgente', 'TRSP');
                    $w->writeElement('Rol', 'TRSP');
                $w->endElement();

                // ID del título de transporte (único parámetro específico)
                $w->writeElement('argIdTitTrans', (string)$anulacionData['id_titulo']);

                $w->endElement(); // AnularTitulo
            $w->endElement(); // Body
            $w->endElement(); // Envelope

            $w->endDocument();
            return $w->outputMemory();

        } catch (Exception $e) {
            \Log::info('Error en createAnularTituloXml: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * RegistrarTitMicDta - Vincular títulos de transporte a MIC/DTA existente
     * Genera XML según especificación AFIP para registrar títulos a un MIC/DTA
     * 
     * @param array $vinculacionData Datos de vinculación (id_micdta, titulos)
     * @param string $transactionId ID único de transacción (máx 15 chars)
     * @return string|null XML completo o null si error
     */
    public function createRegistrarTitMicDtaXml(array $vinculacionData, string $transactionId, Voyage $voyage): ?string
    {
        try {
            // Validar datos obligatorios
            if (empty($vinculacionData['id_micdta'])) {
                throw new Exception('ID MIC/DTA obligatorio');
            }
            
            if (empty($vinculacionData['contenedores_con_carga']) && empty($vinculacionData['cargas_sueltas_tracks'])) {
                throw new Exception('Se requiere al menos contenedores con carga o TRACKs de carga suelta');
            }
            if (empty($vinculacionData['nro_viaje'])) {
                throw new Exception('Número de viaje (nroViaje) obligatorio');
            }

            // Validar longitudes según AFIP
            if (strlen($vinculacionData['id_micdta']) > 16) {
                throw new Exception('ID MIC/DTA no puede exceder 16 caracteres');
            }
            
            if (strlen($transactionId) > 15) {
                throw new Exception('ID Transacción no puede exceder 15 caracteres');
            }

            // Obtener tokens WSAA
            $wsaa = $this->getWSAATokens();

            // Crear XMLWriter
            $w = new \XMLWriter();
            $w->openMemory();
            $w->startDocument('1.0', 'UTF-8');

            // Envelope SOAP
            $w->startElementNs('soap', 'Envelope', 'http://schemas.xmlsoap.org/soap/envelope/');
            $w->writeAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
            $w->writeAttribute('xmlns:xsd', 'http://www.w3.org/2001/XMLSchema');
            
            $w->startElementNs('soap', 'Body', 'http://schemas.xmlsoap.org/soap/envelope/');
                $w->startElement('RegistrarTitMicDta');
                $w->writeAttribute('xmlns', self::AFIP_NAMESPACE);

                // Autenticación empresa (obligatorio para todos los métodos AFIP)
                $w->startElement('argWSAutenticacionEmpresa');
                    $w->writeElement('Token', $wsaa['token']);
                    $w->writeElement('Sign', $wsaa['sign']);
                    $w->writeElement('CuitEmpresaConectada', (string)$this->company->tax_id);
                    $w->writeElement('TipoAgente', 'TRSP');
                    $w->writeElement('Rol', 'TRSP');
                $w->endElement();

                // Parámetros específicos del método
                $w->startElement('argRegistrarTitMicDtaParam');
                    $w->writeElement('idTransaccion', substr($transactionId, 0, 15));
                    $w->writeElement('nroViaje', htmlspecialchars($vinculacionData['nro_viaje']));
                    $w->startElement('titMicDtas');
                        $w->startElement('TitMicDta');
                            $w->writeElement('idMicDta', htmlspecialchars($vinculacionData['id_micdta']));
                            if (!empty($vinculacionData['contenedores_con_carga'])) {
                                $w->startElement('contenedoresConCarga');
                                foreach ($vinculacionData['contenedores_con_carga'] as $idCont) {
                                    $w->writeElement('idCont', htmlspecialchars($idCont));
                                }
                                $w->endElement(); // contenedoresConCarga
                            }
                            if (!empty($vinculacionData['cargas_sueltas_tracks'])) {
                                $w->startElement('cargasSueltasIdTrack');
                                foreach ($vinculacionData['cargas_sueltas_tracks'] as $track) {
                                    $w->writeElement('cargaSueltaIdTrack', htmlspecialchars($track));
                                }
                                $w->endElement(); // cargasSueltasIdTrack
                            }
                        $this->writeRutasInf($w, $voyage);
                        $w->endElement(); // TitMicDta
                    $w->endElement(); // titMicDtas
                $w->endElement(); // argRegistrarTitMicDtaParam

                $w->endElement(); // argRegistrarTitMicDtaParam
                $w->endElement(); // RegistrarTitMicDta
            $w->endElement(); // Body
            $w->endElement(); // Envelope

            $w->endDocument();
            return $w->outputMemory();

        } catch (Exception $e) {
            \Log::info('Error en createRegistrarTitMicDtaXml: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * DesvincularTitMicDta - Desvincular títulos de transporte de MIC/DTA
     * Genera XML según especificación AFIP para desvincular títulos de un MIC/DTA
     * 
     * @param array $desvinculacionData Datos de desvinculación (id_micdta, titulos)
     * @param string $transactionId ID único de transacción (máx 15 chars)
     * @return string|null XML completo o null si error
     */
    public function createDesvincularTitMicDtaXml(array $desvinculacionData, string $transactionId): ?string
    {
        try {
            // Validar datos obligatorios
            if (empty($desvinculacionData['id_micdta'])) {
                throw new Exception('ID MIC/DTA obligatorio');
            }
            
            if (empty($desvinculacionData['titulos']) || !is_array($desvinculacionData['titulos'])) {
                throw new Exception('Lista de títulos obligatoria');
            }

            // Validar longitudes según AFIP
            if (strlen($desvinculacionData['id_micdta']) > 16) {
                throw new Exception('ID MIC/DTA no puede exceder 16 caracteres');
            }
            
            if (strlen($transactionId) > 15) {
                throw new Exception('ID Transacción no puede exceder 15 caracteres');
            }

            // Obtener tokens WSAA
            $wsaa = $this->getWSAATokens();

            // Crear XMLWriter
            $w = new \XMLWriter();
            $w->openMemory();
            $w->startDocument('1.0', 'UTF-8');

            // Envelope SOAP
            $w->startElementNs('soap', 'Envelope', 'http://schemas.xmlsoap.org/soap/envelope/');
            $w->writeAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
            $w->writeAttribute('xmlns:xsd', 'http://www.w3.org/2001/XMLSchema');
            
            $w->startElementNs('soap', 'Body', 'http://schemas.xmlsoap.org/soap/envelope/');
                $w->startElement('DesvincularTitMicDta');
                $w->writeAttribute('xmlns', self::AFIP_NAMESPACE);

                // Autenticación empresa (obligatorio para todos los métodos AFIP)
                $w->startElement('argWSAutenticacionEmpresa');
                    $w->writeElement('Token', $wsaa['token']);
                    $w->writeElement('Sign', $wsaa['sign']);
                    $w->writeElement('CuitEmpresaConectada', (string)$this->company->tax_id);
                    $w->writeElement('TipoAgente', 'TRSP');
                    $w->writeElement('Rol', 'TRSP');
                $w->endElement();

                // Parámetros específicos del método
                $w->startElement('argDesvincularTitMicDtaParam');
                    
                    // ID Transacción (obligatorio)
                    $w->writeElement('idTransaccion', substr($transactionId, 0, 15));
                    
                    // ID MIC/DTA del cual desvincular títulos (obligatorio)
                    $w->writeElement('idMicDta', htmlspecialchars($desvinculacionData['id_micdta']));
                    
                    // Lista de títulos de transporte a desvincular
                    $w->startElement('idTitTrans');
                    foreach ($desvinculacionData['titulos'] as $titulo) {
                        $tituloId = is_array($titulo) ? ($titulo['id'] ?? $titulo['id_titulo'] ?? '') : (string)$titulo;
                        if (!empty($tituloId)) {
                            $w->writeElement('string', htmlspecialchars(substr($tituloId, 0, 36)));
                        }
                    }
                    $w->endElement(); // idTitTrans
                    
                $w->endElement(); // argDesvincularTitMicDtaParam
                $w->endElement(); // DesvincularTitMicDta
            $w->endElement(); // Body
            $w->endElement(); // Envelope

            $w->endDocument();
            return $w->outputMemory();

        } catch (Exception $e) {
            \Log::info('Error en createDesvincularTitMicDtaXml: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * AnularEnvios - Anular conjunto de envíos por IDs de seguimiento
     * Genera XML según especificación AFIP para anular envíos específicos
     * 
     * @param array $anulacionData Datos de anulación (tracks requeridos)
     * @param string $transactionId ID único de transacción (opcional)
     * @return string|null XML completo o null si error
     */
    public function createAnularEnviosXml(array $anulacionData, string $transactionId = ''): ?string
    {
        try {
            // Validar datos obligatorios
            if (empty($anulacionData['tracks']) || !is_array($anulacionData['tracks'])) {
                throw new Exception('Lista de tracks (IDs de seguimiento) obligatoria');
            }

            // Obtener tokens WSAA
            $wsaa = $this->getWSAATokens();

            // Crear XMLWriter
            $w = new \XMLWriter();
            $w->openMemory();
            $w->startDocument('1.0', 'UTF-8');

            // Envelope SOAP
            $w->startElementNs('soap', 'Envelope', 'http://schemas.xmlsoap.org/soap/envelope/');
            $w->writeAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
            $w->writeAttribute('xmlns:xsd', 'http://www.w3.org/2001/XMLSchema');
            
            $w->startElementNs('soap', 'Body', 'http://schemas.xmlsoap.org/soap/envelope/');
                $w->startElement('AnularEnvios');
                $w->writeAttribute('xmlns', self::AFIP_NAMESPACE);

                // Autenticación empresa (obligatorio)
                $w->startElement('argWSAutenticacionEmpresa');
                    $w->writeElement('Token', $wsaa['token']);
                    $w->writeElement('Sign', $wsaa['sign']);
                    $w->writeElement('CuitEmpresaConectada', (string)$this->company->tax_id);
                    $w->writeElement('TipoAgente', 'TRSP');
                    $w->writeElement('Rol', 'TRSP');
                $w->endElement();

                // Lista de IDs de tracks a anular
                $w->startElement('argIdTracks');
                foreach ($anulacionData['tracks'] as $track) {
                    $trackId = is_array($track) ? ($track['id'] ?? $track['track_id'] ?? '') : (string)$track;
                    if (!empty($trackId)) {
                        $w->writeElement('string', htmlspecialchars($trackId));
                    }
                }
                $w->endElement(); // argIdTracks

                $w->endElement(); // AnularEnvios
            $w->endElement(); // Body
            $w->endElement(); // Envelope

            $w->endDocument();
            return $w->outputMemory();

        } catch (Exception $e) {
            \Log::info('Error en createAnularEnviosXml: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Dummy - Testing de conectividad del webservice AFIP
     * Genera XML según especificación AFIP para verificar funcionamiento
     * 
     * @return string|null XML completo o null si error
     */
    public function createDummyXml(): ?string
    {
        try {
            // XML con formato exacto SOAP-ENV
            $xml = '<?xml version="1.0"?>';
            $xml .= '<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">';
            $xml .= '<SOAP-ENV:Body>';
            $xml .= '<Dummy xmlns="' . self::AFIP_NAMESPACE . '"/>';
            $xml .= '</SOAP-ENV:Body>';
            $xml .= '</SOAP-ENV:Envelope>';

            return $xml;

        } catch (Exception $e) {
            \Log::info('Error en createDummyXml: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * ConsultarPrecumplido - Consultar valores de precumplido de destinación
     * Genera XML según especificación AFIP para consultar precumplidos
     * 
     * @param array $consultaData Datos de consulta (destinacion_id, etc.)
     * @param string $transactionId ID único de transacción (opcional)
     * @return string|null XML completo o null si error
     */
    public function createConsultarPrecumplidoXml(array $consultaData, string $transactionId = ''): ?string
    {
        try {
            // Obtener tokens WSAA
            $wsaa = $this->getWSAATokens();

            // XML con formato exacto de Roberto (SOAP-ENV namespace)
            $xml = '<?xml version="1.0"?>';
            $xml .= '<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">';
            $xml .= '<SOAP-ENV:Body>';
            $xml .= '<ConsultarPrecumplido xmlns="' . self::AFIP_NAMESPACE . '">';
            
            $xml .= '<argWSAutenticacionEmpresa>';
            $xml .= '<Token>' . htmlspecialchars($wsaa['token']) . '</Token>';
            $xml .= '<Sign>' . htmlspecialchars($wsaa['sign']) . '</Sign>';
            $xml .= '<CuitEmpresaConectada>' . htmlspecialchars((string)$this->company->tax_id) . '</CuitEmpresaConectada>';
            $xml .= '<TipoAgente>TRSP</TipoAgente>';
            $xml .= '<Rol>TRSP</Rol>';
            $xml .= '</argWSAutenticacionEmpresa>';
            
            // Parámetros de consulta (si se especifican)
            if (!empty($consultaData)) {
                $xml .= '<argConsultarPrecumplidoParam>';
                if (!empty($transactionId)) {
                    $xml .= '<idTransaccion>' . htmlspecialchars(substr($transactionId, 0, 15)) . '</idTransaccion>';
                }
                if (!empty($consultaData['destinacion_id'])) {
                    $xml .= '<idDestinacion>' . htmlspecialchars($consultaData['destinacion_id']) . '</idDestinacion>';
                }
                if (!empty($consultaData['codigo_aduana'])) {
                    $xml .= '<codAduana>' . htmlspecialchars($consultaData['codigo_aduana']) . '</codAduana>';
                }
                $xml .= '</argConsultarPrecumplidoParam>';
            }
            
            $xml .= '</ConsultarPrecumplido>';
            $xml .= '</SOAP-ENV:Body>';
            $xml .= '</SOAP-ENV:Envelope>';

            return $xml;

        } catch (Exception $e) {
            \Log::info('Error en createConsultarPrecumplidoXml: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * AnularArriboZonaPrimaria - Anular arribo registrado en zona primaria
     * Genera XML según especificación AFIP para anular arribo
     * 
     * @param array $anulacionData Datos de anulación (nro_viaje o referencia_arribo)
     * @param string $transactionId ID único de transacción (opcional)
     * @return string|null XML completo o null si error
     */
    public function createAnularArriboZonaPrimariaXml(array $anulacionData, string $transactionId = ''): ?string
    {
        try {
            // Validar datos obligatorios
            if (empty($anulacionData['nro_viaje']) && empty($anulacionData['referencia_arribo'])) {
                throw new Exception('Número de viaje o referencia de arribo obligatorio');
            }

            // Obtener tokens WSAA
            $wsaa = $this->getWSAATokens();

            // Crear XMLWriter
            $w = new \XMLWriter();
            $w->openMemory();
            $w->startDocument('1.0', 'UTF-8');

            // Envelope SOAP
            $w->startElementNs('soap', 'Envelope', 'http://schemas.xmlsoap.org/soap/envelope/');
            $w->writeAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
            $w->writeAttribute('xmlns:xsd', 'http://www.w3.org/2001/XMLSchema');
            
            $w->startElementNs('soap', 'Body', 'http://schemas.xmlsoap.org/soap/envelope/');
                $w->startElement('AnularArriboZonaPrimaria');
                $w->writeAttribute('xmlns', self::AFIP_NAMESPACE);

                // Autenticación empresa (obligatorio)
                $w->startElement('argWSAutenticacionEmpresa');
                    $w->writeElement('Token', $wsaa['token']);
                    $w->writeElement('Sign', $wsaa['sign']);
                    $w->writeElement('CuitEmpresaConectada', (string)$this->company->tax_id);
                    $w->writeElement('TipoAgente', 'TRSP');
                    $w->writeElement('Rol', 'TRSP');
                $w->endElement();

                // Parámetros de anulación
                $w->startElement('argAnularArriboZonaPrimariaParam');
                
                // ID Transacción (opcional)
                if (!empty($transactionId)) {
                    $w->writeElement('idTransaccion', substr($transactionId, 0, 15));
                }
                
                // Número de viaje (parámetro principal)
                if (!empty($anulacionData['nro_viaje'])) {
                    $w->writeElement('nroViaje', htmlspecialchars($anulacionData['nro_viaje']));
                } elseif (!empty($anulacionData['referencia_arribo'])) {
                    $w->writeElement('referenciaArribo', htmlspecialchars($anulacionData['referencia_arribo']));
                }
                
                // Motivo de anulación (opcional)
                if (!empty($anulacionData['motivo'])) {
                    $w->writeElement('motivoAnulacion', htmlspecialchars(substr($anulacionData['motivo'], 0, 50)));
                }
                
                $w->endElement(); // argAnularArriboZonaPrimariaParam
                $w->endElement(); // AnularArriboZonaPrimaria
            $w->endElement(); // Body
            $w->endElement(); // Envelope

            $w->endDocument();
            return $w->outputMemory();

        } catch (Exception $e) {
            \Log::info('Error en createAnularArriboZonaPrimariaXml: ' . $e->getMessage());
            throw $e;
        }
    }


    /**
     * ============================================
     * MÉTODOS INFORMACIÓN ANTICIPADA ARGENTINA
     * ============================================
     */

   /**
     * MÉTODO PRINCIPAL: RegistrarViaje - Información Anticipada del viaje
     * 
     * Genera XML para registro de información anticipada marítima según especificación AFIP.
     * Incluye datos de cabecera del viaje, embarcación, capitán y contenedores vacíos/correo.
     * 
     * @param Voyage $voyage Viaje con relaciones cargadas
     * @param string $transactionId ID único de transacción (máx 15 chars)
     * @return string XML completo según especificación AFIP
     * @throws Exception Si faltan datos obligatorios o error en generación
     */
    public function createRegistrarViajeXml(Voyage $voyage, string $transactionId): string
    {
        try {
            // Validar datos obligatorios
            $this->validateVoyageData($voyage);

            // Obtener tokens WSAA
            $wsaa = $this->getWSAATokens('wgesinformacionanticipada');

            // Crear XMLWriter
            $w = new \XMLWriter();
            $w->openMemory();
            $w->startDocument('1.0', 'UTF-8');

            // SOAP Envelope
            $w->startElementNs('soap', 'Envelope', 'http://schemas.xmlsoap.org/soap/envelope/');
            $w->writeAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
            $w->writeAttribute('xmlns:xsd', 'http://www.w3.org/2001/XMLSchema');

            // SOAP Body
            $w->startElementNs('soap', 'Body', 'http://schemas.xmlsoap.org/soap/envelope/');
                $w->startElement('RegistrarViaje');
                $w->writeAttribute('xmlns', self::AFIP_ANTICIPADA_NAMESPACE);

                // Autenticación empresa (obligatorio)
                $w->startElement('argWSAutenticacionEmpresa');
                    $w->writeElement('Token', $wsaa['token']);
                    $w->writeElement('Sign', $wsaa['sign']);
                    $w->writeElement('CuitEmpresaConectada', (string)$this->company->tax_id);
                    $w->writeElement('TipoAgente', 'TRSP');
                    $w->writeElement('Rol', 'TRSP');
                $w->endElement();

                // Parámetros RegistrarViaje
                $w->startElement('argRegistrarViaje');
                    $w->writeElement('IdTransaccion', substr($transactionId, 0, 15));

                    // Información Anticipada Marítima (estructura principal)
                    $w->startElement('InformacionAnticipadaMaritimaDoc');
                        $this->addVoyageInformation($w, $voyage);
                        $this->addContainersInformation($w, $voyage);
                    $w->endElement(); // InformacionAnticipadaMaritimaDoc

                $w->endElement(); // argRegistrarViaje
                $w->endElement(); // RegistrarViaje
            $w->endElement(); // Body
            $w->endElement(); // Envelope

            $w->endDocument();
            return $w->outputMemory();

        } catch (Exception $e) {
            \Log::info('Error en createRegistrarViajeXml: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * RectificarViaje - Rectificación de viaje ATA MT
     * 
     * Genera XML para modificar un viaje previamente registrado.
     * Requiere el IdentificadorViaje obtenido del registro original.
     * 
     * @param Voyage $voyage Viaje con relaciones cargadas
     * @param array $rectificationData Datos de rectificación incluyendo original_external_reference
     * @param string $transactionId ID único de transacción
     * @return string XML completo según especificación AFIP
     * @throws Exception Si faltan datos obligatorios
     */
    public function createRectificarViajeXml(Voyage $voyage, array $rectificationData, string $transactionId): string
    {
        try {
            // Validar datos obligatorios
            $this->validateVoyageData($voyage);
            
            if (empty($rectificationData['original_external_reference'])) {
                throw new Exception('Se requiere original_external_reference para rectificación');
            }

            // Obtener tokens WSAA
            $wsaa = $this->getWSAATokens();

            // Crear XMLWriter
            $w = new \XMLWriter();
            $w->openMemory();
            $w->startDocument('1.0', 'UTF-8');

            // SOAP Envelope
            $w->startElementNs('soap', 'Envelope', 'http://schemas.xmlsoap.org/soap/envelope/');
            $w->writeAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
            $w->writeAttribute('xmlns:xsd', 'http://www.w3.org/2001/XMLSchema');

            // SOAP Body
            $w->startElementNs('soap', 'Body', 'http://schemas.xmlsoap.org/soap/envelope/');
                $w->startElement('RectificarViaje');
                $w->writeAttribute('xmlns', self::AFIP_ANTICIPADA_NAMESPACE);

                // Autenticación empresa (obligatorio)
                $w->startElement('argWSAutenticacionEmpresa');
                    $w->writeElement('Token', $wsaa['token']);
                    $w->writeElement('Sign', $wsaa['sign']);
                    $w->writeElement('CuitEmpresaConectada', (string)$this->company->tax_id);
                    $w->writeElement('TipoAgente', 'TRSP');
                    $w->writeElement('Rol', 'TRSP');
                $w->endElement();

                // Parámetros RectificarViaje
                $w->startElement('argRectificarViaje');
                    $w->writeElement('IdTransaccion', substr($transactionId, 0, 15));

                    // Información Anticipada Marítima (estructura principal)
                    $w->startElement('InformacionAnticipadaMaritimaDoc');
                        // Identificador del viaje original (obligatorio para rectificación)
                        $w->writeElement('IdentificadorViaje', $rectificationData['original_external_reference']);
                        
                        $this->addVoyageInformation($w, $voyage);
                        $this->addContainersInformation($w, $voyage);
                    $w->endElement(); // InformacionAnticipadaMaritimaDoc

                $w->endElement(); // argRectificarViaje
                $w->endElement(); // RectificarViaje
            $w->endElement(); // Body
            $w->endElement(); // Envelope

            $w->endDocument();
            return $w->outputMemory();

        } catch (Exception $e) {
            \Log::info('Error en createRectificarViajeXml: ' . $e->getMessage());
            throw $e;
        }
    }

     /**
     * RegistrarTitulosCbc - Registro de títulos ATA CBC
     * 
     * Genera XML para registro de títulos ATA CBC según especificación AFIP.
     * Busca automáticamente el IdentificadorViaje del último RegistrarViaje exitoso.
     * 
     * @param Voyage $voyage Viaje con relaciones cargadas
     * @param array $titulosData Datos específicos de títulos CBC (no usado por ahora)
     * @param string $transactionId ID único de transacción
     * @return string XML completo según especificación AFIP
     * @throws Exception Si faltan datos obligatorios
     */
    public function createRegistrarTitulosCbcXml(Voyage $voyage, array $titulosData, string $transactionId): string
    {
        try {
            // Validar datos obligatorios
            $this->validateVoyageData($voyage);

            // Buscar IdentificadorViaje del último RegistrarViaje exitoso
            $previousTransaction = $voyage->webserviceTransactions()
                ->where('webservice_type', 'anticipada')
                ->where('status', 'success')
                ->whereNotNull('external_reference')
                ->latest()
                ->first();

            if (!$previousTransaction) {
                throw new Exception('Debe registrar el viaje con RegistrarViaje antes de enviar títulos CBC');
            }

            $identificadorViaje = $previousTransaction->external_reference;

            // Obtener tokens WSAA
            $wsaa = $this->getWSAATokens('wgesinformacionanticipada');

            // Crear XMLWriter
            $w = new \XMLWriter();
            $w->openMemory();
            $w->startDocument('1.0', 'UTF-8');

            // SOAP Envelope
            $w->startElementNs('soapenv', 'Envelope', 'http://schemas.xmlsoap.org/soap/envelope/');
            $w->writeAttribute('xmlns:ar', self::AFIP_ANTICIPADA_NAMESPACE);

            // SOAP Body
            $w->startElementNs('soapenv', 'Body', null);
                $w->startElement('ar:RegistrarTitulosCbc');

                    // Autenticación empresa
                    $w->startElement('ar:argWSAutenticacionEmpresa');
                        $w->writeElement('ar:Token', $wsaa['token']);
                        $w->writeElement('ar:Sign', $wsaa['sign']);
                        $w->writeElement('ar:CuitEmpresaConectada', (string)$this->company->tax_id);
                        $w->writeElement('ar:TipoAgente', 'TRSP');
                        $w->writeElement('ar:Rol', 'TRSP');
                    $w->endElement();

                    // Parámetros RegistrarTitulosCBC
                    $w->startElement('ar:argRegistrarTitulosCBC');

                        $registrarTitulosTransactionId = trim($transactionId);

                        if ($registrarTitulosTransactionId === '') {
                            throw new Exception(
                                'RegistrarTitulosCbc: falta IdTransaccion.'
                            );
                        }

                        if (strlen($registrarTitulosTransactionId) > 20) {
                            throw new Exception(
                                "RegistrarTitulosCbc: IdTransaccion "
                                . "'{$registrarTitulosTransactionId}' supera "
                                . 'los 20 caracteres admitidos por AFIP.'
                            );
                        }

                        $w->writeElement(
                            'ar:IdTransaccion',
                            $registrarTitulosTransactionId
                        );
                        
                        // Información de Títulos
                        $w->startElement('ar:InformacionTitulosDoc');
                            $w->writeElement('ar:IdentificadorViaje', $identificadorViaje);
                            
                            // Obtener conocimientos (BillsOfLading) del viaje
                            $billsOfLading = collect();
                            foreach ($voyage->shipments as $shipment) {
                                $billsOfLading = $billsOfLading->merge($shipment->billsOfLading);
                            }

                            if ($billsOfLading->isEmpty()) {
                                throw new Exception('No hay conocimientos de embarque para registrar');
                            }

                            // Títulos (array de conocimientos)
                            $w->startElement('ar:Titulos');
                            
                            foreach ($billsOfLading as $bol) {
                                $w->startElement('ar:Titulo');
                                    
                                    // 1. FechaEmbarque (obligatorio)
                                    // Debe provenir del conocimiento.
                                    if (!$bol->loading_date) {
                                        throw new Exception(
                                            "BL {$bol->bill_number}: falta la fecha de embarque."
                                        );
                                    }

                                    $w->writeElement(
                                        'ar:FechaEmbarque',
                                        $bol->loading_date->format('Y-m-d\TH:i:s')
                                    );
                                    
                                    // 2. CodigoPuertoEmbarque (obligatorio)
                                    // AFIP espera código de puerto POR_PAIS, no código de aduana.
                                    $loadingPortCode = trim(
                                        (string) ($bol->loadingPort?->code ?? '')
                                    );

                                    if ($loadingPortCode === '') {
                                        throw new Exception(
                                            "BL {$bol->bill_number}: falta el puerto de embarque."
                                        );
                                    }

                                    if (strlen($loadingPortCode) > 5) {
                                        throw new Exception(
                                            "BL {$bol->bill_number}: el código de puerto de embarque "
                                            . "'{$loadingPortCode}' supera los 5 caracteres admitidos por AFIP."
                                        );
                                    }

                                    $w->writeElement(
                                        'ar:CodigoPuertoEmbarque',
                                        $loadingPortCode
                                    );

                                    /*
                                     * FechaCargaLugarOrigen / LugarOrigen /
                                     * CodigoPaisLugarOrigen.
                                     *
                                     * Los tres datos son condicionales:
                                     * - si existe FechaCargaLugarOrigen,
                                     *   deben existir también LugarOrigen y
                                     *   CodigoPaisLugarOrigen;
                                     * - si no existe la fecha, no deben
                                     *   transmitirse lugar ni país.
                                     */
                                    $originLoadingDate = $bol->origin_loading_date;

                                    $originLocation = trim(
                                        (string) ($bol->origin_location ?? '')
                                    );

                                    $originCountryCode = trim(
                                        (string) (
                                            $bol->origin_country_code ?? ''
                                        )
                                    );

                                    if ($originLoadingDate) {
                                        if ($originLocation === '') {
                                            throw new Exception(
                                                "BL {$bol->bill_number}: existe fecha "
                                                . 'de carga en lugar de origen pero '
                                                . 'falta el lugar de origen.'
                                            );
                                        }

                                        if ($originCountryCode === '') {
                                            throw new Exception(
                                                "BL {$bol->bill_number}: existe fecha "
                                                . 'de carga en lugar de origen pero '
                                                . 'falta el país del lugar de origen.'
                                            );
                                        }

                                        if (mb_strlen($originLocation) > 50) {
                                            throw new Exception(
                                                "BL {$bol->bill_number}: el lugar de "
                                                . 'origen supera los 50 caracteres '
                                                . 'admitidos por AFIP.'
                                            );
                                        }

                                        if (strlen($originCountryCode) > 3) {
                                            throw new Exception(
                                                "BL {$bol->bill_number}: el código de "
                                                . 'país del lugar de origen supera los '
                                                . '3 caracteres admitidos por AFIP.'
                                            );
                                        }

                                        $w->writeElement(
                                            'ar:FechaCargaLugarOrigen',
                                            $originLoadingDate
                                                ->format('Y-m-d\TH:i:s')
                                        );

                                        $w->writeElement(
                                            'ar:LugarOrigen',
                                            $originLocation
                                        );

                                        $w->writeElement(
                                            'ar:CodigoPaisLugarOrigen',
                                            $originCountryCode
                                        );
                                    } elseif (
                                        $originLocation !== ''
                                        || $originCountryCode !== ''
                                    ) {
                                        throw new Exception(
                                            "BL {$bol->bill_number}: tiene lugar o país "
                                            . 'de origen informado sin fecha de carga '
                                            . 'en lugar de origen.'
                                        );
                                    }
                                    
                                    // 3. NumeroConocimiento (obligatorio - máx 18 chars)
                                    // Debe transmitirse exactamente como está registrado.
                                    // No fabricar ni truncar identificadores documentales.
                                    $bolNumber = trim(
                                        (string) ($bol->bill_number ?? '')
                                    );

                                    if ($bolNumber === '') {
                                        throw new Exception(
                                            "BL ID {$bol->id}: falta el número de conocimiento."
                                        );
                                    }

                                    if (strlen($bolNumber) > 18) {
                                        throw new Exception(
                                            "BL {$bolNumber}: el número de conocimiento "
                                            . 'supera los 18 caracteres admitidos por AFIP.'
                                        );
                                    }

                                                                        $w->writeElement(
                                        'ar:NumeroConocimiento',
                                        $bolNumber
                                    );

                                    /*
                                     * CodigoPuertoTrasbordo
                                     * Optativo según RegistrarTitulosCbc.
                                     */
                                    if ($bol->transshipmentPort) {
                                        $transshipmentPortCode = trim(
                                            (string) $bol->transshipmentPort->code
                                        );

                                        if (strlen($transshipmentPortCode) > 5) {
                                            throw new Exception(
                                                "BL {$bol->bill_number}: el código de puerto "
                                                . 'de trasbordo supera los 5 caracteres '
                                                . 'admitidos por AFIP.'
                                            );
                                        }

                                        if ($transshipmentPortCode !== '') {
                                            $w->writeElement(
                                                'ar:CodigoPuertoTrasbordo',
                                                $transshipmentPortCode
                                            );
                                        }
                                    }

                                    /*
                                     * CodigoPuertoDescarga
                                     * Obligatorio. Fuente real: puerto de descarga
                                     * asociado al conocimiento.
                                     */
                                    $dischargePortCode = trim(
                                        (string) ($bol->dischargePort?->code ?? '')
                                    );

                                    if ($dischargePortCode === '') {
                                        throw new Exception(
                                            "BL {$bol->bill_number}: falta el puerto de descarga."
                                        );
                                    }

                                    if (strlen($dischargePortCode) > 5) {
                                        throw new Exception(
                                            "BL {$bol->bill_number}: el código de puerto "
                                            . 'de descarga supera los 5 caracteres '
                                            . 'admitidos por AFIP.'
                                        );
                                    }

                                    $w->writeElement(
                                        'ar:CodigoPuertoDescarga',
                                        $dischargePortCode
                                    );

                                    /*
                                     * FechaDescarga
                                     * Optativa. Sólo se informa cuando existe
                                     * realmente en el conocimiento.
                                     */
                                    if ($bol->discharge_date) {
                                        $w->writeElement(
                                            'ar:FechaDescarga',
                                            $bol->discharge_date
                                                ->format('Y-m-d\TH:i:s')
                                        );
                                    }

                                    /*
                                     * CodigoPaisDestino
                                     * Obligatorio.
                                     *
                                     * Se usa exclusivamente el campo AFIP
                                     * persistido en el conocimiento. No inferir
                                     * alpha2, país del puerto ni otro catálogo.
                                     */
                                    $destinationCountryCode = trim(
                                        (string) (
                                            $bol->destination_country_code ?? ''
                                        )
                                    );

                                    if ($destinationCountryCode === '') {
                                        throw new Exception(
                                            "BL {$bol->bill_number}: falta el código "
                                            . 'AFIP del país de destino.'
                                        );
                                    }

                                    if (strlen($destinationCountryCode) > 3) {
                                        throw new Exception(
                                            "BL {$bol->bill_number}: el código AFIP "
                                            . 'del país de destino supera los '
                                            . '3 caracteres admitidos.'
                                        );
                                    }

                                    $w->writeElement(
                                        'ar:CodigoPaisDestino',
                                        $destinationCountryCode
                                    );

                                    /*
                                     * MarcaBultos
                                     * Obligatorio a nivel Titulo.
                                     *
                                     * No utilizar S/M ni reconstruirlo desde
                                     * otras propiedades en este generador.
                                     */
                                    $cargoMarks = trim(
                                        (string) ($bol->cargo_marks ?? '')
                                    );

                                    if ($cargoMarks === '') {
                                        throw new Exception(
                                            "BL {$bol->bill_number}: falta la marca "
                                            . 'de los bultos requerida por AFIP.'
                                        );
                                    }

                                    if (mb_strlen($cargoMarks) > 80) {
                                        throw new Exception(
                                            "BL {$bol->bill_number}: la marca de los "
                                            . 'bultos supera los 80 caracteres '
                                            . 'admitidos por AFIP.'
                                        );
                                    }

                                    $w->writeElement(
                                        'ar:MarcaBultos',
                                        $cargoMarks
                                    );

                                    /*
                                     * IndicadorConsolidado
                                     * Obligatorio S/N.
                                     *
                                     * En la base is_consolidated es booleano.
                                     * Se convierte explícitamente:
                                     *   0 = N
                                     *   1 = S
                                     *
                                     * Se usa el valor crudo de la columna porque
                                     * el modelo tiene actualmente casts duplicados
                                     * para is_consolidated.
                                     */
                                    $isConsolidatedRaw = $bol->getRawOriginal(
                                        'is_consolidated'
                                    );

                                    if (
                                        !in_array(
                                            (string) $isConsolidatedRaw,
                                            ['0', '1'],
                                            true
                                        )
                                    ) {
                                        throw new Exception(
                                            "BL {$bol->bill_number}: el indicador "
                                            . 'de consolidado no tiene un valor válido.'
                                        );
                                    }

                                    $w->writeElement(
                                        'ar:IndicadorConsolidado',
                                        (string) $isConsolidatedRaw === '1'
                                            ? 'S'
                                            : 'N'
                                    );

                                    /*
                                     * IndicadorTransitoTrasbordo
                                     * Obligatorio S/N.
                                     *
                                     * Esta columna ya se persiste con la
                                     * codificación AFIP; no se infiere desde
                                     * puertos, textos ni presencia de transbordo.
                                     */
                                    $transitTransshipmentIndicator = strtoupper(
                                        trim(
                                            (string) (
                                                $bol->is_transit_transshipment
                                                ?? ''
                                            )
                                        )
                                    );

                                    if (
                                        !in_array(
                                            $transitTransshipmentIndicator,
                                            ['S', 'N'],
                                            true
                                        )
                                    ) {
                                        throw new Exception(
                                            "BL {$bol->bill_number}: el indicador "
                                            . 'de tránsito/transbordo debe ser S o N.'
                                        );
                                    }

                                    $w->writeElement(
                                        'ar:IndicadorTransitoTrasbordo',
                                        $transitTransshipmentIndicator
                                    );

                                    /*
                                     * Los siguientes datos pertenecen al Titulo
                                     * en el contrato AFIP, pero la app los
                                     * persiste actualmente en ShipmentItem.
                                     *
                                     * Por eso todas las líneas del BL deben
                                     * contener un único valor consistente.
                                     */
                                    $items = $bol->shipmentItems;

                                    if ($items->isEmpty()) {
                                        throw new Exception(
                                            "BL {$bol->bill_number}: no tiene líneas de mercadería "
                                            . 'para RegistrarTitulosCbc.'
                                        );
                                    }

                                    /*
                                     * Datos del destinatario de la mercadería.
                                     *
                                     * Son optativos. Si se informa tipo o
                                     * identificador, ambos deben estar presentes
                                     * y ser consistentes en todas las líneas.
                                     *
                                     * AFIP define el identificador como numérico
                                     * con largo 11. No se infiere ni se completa.
                                     */
                                    $recipientDocumentTypes = $items
                                        ->map(
                                            fn ($item) => trim(
                                                (string) (
                                                    $item->consignee_document_type
                                                    ?? ''
                                                )
                                            )
                                        );

                                    $recipientIdentifiers = $items
                                        ->map(
                                            fn ($item) => trim(
                                                (string) (
                                                    $item->consignee_tax_id
                                                    ?? ''
                                                )
                                            )
                                        );

                                    $recipientPassportCountries = $items
                                        ->map(
                                            fn ($item) => trim(
                                                (string) (
                                                    $item->consignee_passport_country_code
                                                    ?? ''
                                                )
                                            )
                                        );

                                    $hasRecipientData =
                                        $recipientDocumentTypes->contains(
                                            fn ($value) => $value !== ''
                                        )
                                        || $recipientIdentifiers->contains(
                                            fn ($value) => $value !== ''
                                        )
                                        || $recipientPassportCountries->contains(
                                            fn ($value) => $value !== ''
                                        );

                                    if ($hasRecipientData) {
                                        if (
                                            $recipientDocumentTypes->contains('')
                                            || $recipientIdentifiers->contains('')
                                        ) {
                                            throw new Exception(
                                                "BL {$bol->bill_number}: todas las líneas deben "
                                                . 'tener tipo e identificador del destinatario '
                                                . 'cuando se informa alguno de esos datos.'
                                            );
                                        }

                                        $uniqueRecipientDocumentTypes =
                                            $recipientDocumentTypes
                                                ->unique()
                                                ->values();

                                        if (
                                            $uniqueRecipientDocumentTypes->count()
                                            !== 1
                                        ) {
                                            throw new Exception(
                                                "BL {$bol->bill_number}: las líneas tienen "
                                                . 'tipos de documento del destinatario diferentes.'
                                            );
                                        }

                                        $recipientDocumentType =
                                            $uniqueRecipientDocumentTypes->first();

                                        if (
                                            mb_strlen($recipientDocumentType)
                                            > 4
                                        ) {
                                            throw new Exception(
                                                "BL {$bol->bill_number}: el tipo de documento "
                                                . 'del destinatario supera los 4 caracteres '
                                                . 'admitidos por AFIP.'
                                            );
                                        }

                                        $uniqueRecipientIdentifiers =
                                            $recipientIdentifiers
                                                ->unique()
                                                ->values();

                                        if (
                                            $uniqueRecipientIdentifiers->count()
                                            !== 1
                                        ) {
                                            throw new Exception(
                                                "BL {$bol->bill_number}: las líneas tienen "
                                                . 'identificadores del destinatario diferentes.'
                                            );
                                        }

                                        $recipientIdentifier =
                                            $uniqueRecipientIdentifiers->first();

                                        if (
                                            !ctype_digit($recipientIdentifier)
                                            || strlen($recipientIdentifier) > 11
                                        ) {
                                            throw new Exception(
                                                "BL {$bol->bill_number}: el identificador "
                                                . 'del destinatario debe ser numérico '
                                                . 'y no superar 11 dígitos.'
                                            );
                                        }

                                        $w->writeElement(
                                            'ar:TipoDocumentoDestinatarioMercaderia',
                                            $recipientDocumentType
                                        );

                                        $w->writeElement(
                                            'ar:IdentificadorDestinatarioMercaderia',
                                            $recipientIdentifier
                                        );

                                        if ($recipientDocumentType === 'PASS') {
                                            if (
                                                $recipientPassportCountries
                                                    ->contains('')
                                            ) {
                                                throw new Exception(
                                                    "BL {$bol->bill_number}: todas las líneas "
                                                    . 'con destinatario PASS deben informar '
                                                    . 'el país emisor del pasaporte.'
                                                );
                                            }

                                            $uniqueRecipientPassportCountries =
                                                $recipientPassportCountries
                                                    ->unique()
                                                    ->values();

                                            if (
                                                $uniqueRecipientPassportCountries
                                                    ->count()
                                                !== 1
                                            ) {
                                                throw new Exception(
                                                    "BL {$bol->bill_number}: las líneas tienen "
                                                    . 'países emisores de pasaporte diferentes.'
                                                );
                                            }

                                            $recipientPassportCountry =
                                                $uniqueRecipientPassportCountries
                                                    ->first();

                                            if (
                                                strlen(
                                                    $recipientPassportCountry
                                                ) !== 3
                                            ) {
                                                throw new Exception(
                                                    "BL {$bol->bill_number}: el país emisor "
                                                    . 'del pasaporte debe tener 3 caracteres.'
                                                );
                                            }

                                            $w->writeElement(
                                                'ar:CodigoPaisEmisionPasaporteDestinatario',
                                                $recipientPassportCountry
                                            );
                                        } elseif (
                                            $recipientPassportCountries->contains(
                                                fn ($value) => $value !== ''
                                            )
                                        ) {
                                            throw new Exception(
                                                "BL {$bol->bill_number}: existe país emisor "
                                                . 'de pasaporte informado para un destinatario '
                                                . 'cuyo tipo de documento no es PASS.'
                                            );
                                        }
                                    }

                                    // PosicionArancelaria - obligatoria.
                                    $tariffPositions = $items
                                        ->map(
                                            fn ($item) => trim(
                                                (string) ($item->tariff_position ?? '')
                                            )
                                        );

                                    if ($tariffPositions->contains('')) {
                                        throw new Exception(
                                            "BL {$bol->bill_number}: todas las líneas deben "
                                            . 'tener posición arancelaria para AFIP.'
                                        );
                                    }

                                    $uniqueTariffPositions = $tariffPositions
                                        ->unique()
                                        ->values();

                                    if ($uniqueTariffPositions->count() !== 1) {
                                        throw new Exception(
                                            "BL {$bol->bill_number}: las líneas tienen "
                                            . 'posiciones arancelarias diferentes y AFIP '
                                            . 'admite una sola a nivel del título.'
                                        );
                                    }

                                    $tariffPosition = $uniqueTariffPositions->first();

                                    if (
                                        strlen($tariffPosition) < 7
                                        || strlen($tariffPosition) > 15
                                    ) {
                                        throw new Exception(
                                            "BL {$bol->bill_number}: la posición arancelaria "
                                            . 'debe tener entre 7 y 15 caracteres.'
                                        );
                                    }

                                    $w->writeElement(
                                        'ar:PosicionArancelaria',
                                        $tariffPosition
                                    );

                                    /*
                                     * Indicadores regulatorios.
                                     * AFIP exige S/N y la app los persiste
                                     * expresamente con esa misma codificación.
                                     */
                                    $secureLogisticsValues = $items
                                        ->map(
                                            fn ($item) => strtoupper(
                                                trim(
                                                    (string) (
                                                        $item->is_secure_logistics_operator
                                                        ?? ''
                                                    )
                                                )
                                            )
                                        );

                                    if (
                                        $secureLogisticsValues->contains(
                                            fn ($value) => !in_array(
                                                $value,
                                                ['S', 'N'],
                                                true
                                            )
                                        )
                                    ) {
                                        throw new Exception(
                                            "BL {$bol->bill_number}: todas las líneas deben "
                                            . 'tener IndicadorOperadorLogisticoSeguro S/N.'
                                        );
                                    }

                                    $uniqueSecureLogistics = $secureLogisticsValues
                                        ->unique()
                                        ->values();

                                    if ($uniqueSecureLogistics->count() !== 1) {
                                        throw new Exception(
                                            "BL {$bol->bill_number}: las líneas tienen valores "
                                            . 'diferentes para IndicadorOperadorLogisticoSeguro.'
                                        );
                                    }

                                    $w->writeElement(
                                        'ar:IndicadorOperadorLogisticoSeguro',
                                        $uniqueSecureLogistics->first()
                                    );

                                    $monitoredTransitValues = $items
                                        ->map(
                                            fn ($item) => strtoupper(
                                                trim(
                                                    (string) (
                                                        $item->is_monitored_transit
                                                        ?? ''
                                                    )
                                                )
                                            )
                                        );

                                    if (
                                        $monitoredTransitValues->contains(
                                            fn ($value) => !in_array(
                                                $value,
                                                ['S', 'N'],
                                                true
                                            )
                                        )
                                    ) {
                                        throw new Exception(
                                            "BL {$bol->bill_number}: todas las líneas deben "
                                            . 'tener IndicadorTransitoMonitoreado S/N.'
                                        );
                                    }

                                    $uniqueMonitoredTransit = $monitoredTransitValues
                                        ->unique()
                                        ->values();

                                    if ($uniqueMonitoredTransit->count() !== 1) {
                                        throw new Exception(
                                            "BL {$bol->bill_number}: las líneas tienen valores "
                                            . 'diferentes para IndicadorTransitoMonitoreado.'
                                        );
                                    }

                                    $w->writeElement(
                                        'ar:IndicadorTransitoMonitoreado',
                                        $uniqueMonitoredTransit->first()
                                    );

                                    $renarValues = $items
                                        ->map(
                                            fn ($item) => strtoupper(
                                                trim(
                                                    (string) ($item->is_renar ?? '')
                                                )
                                            )
                                        );

                                    if (
                                        $renarValues->contains(
                                            fn ($value) => !in_array(
                                                $value,
                                                ['S', 'N'],
                                                true
                                            )
                                        )
                                    ) {
                                        throw new Exception(
                                            "BL {$bol->bill_number}: todas las líneas deben "
                                            . 'tener IndicadorRenar S/N.'
                                        );
                                    }

                                    $uniqueRenar = $renarValues
                                        ->unique()
                                        ->values();

                                    if ($uniqueRenar->count() !== 1) {
                                        throw new Exception(
                                            "BL {$bol->bill_number}: las líneas tienen valores "
                                            . 'diferentes para IndicadorRenar.'
                                        );
                                    }

                                    $w->writeElement(
                                        'ar:IndicadorRenar',
                                        $uniqueRenar->first()
                                    );

                                    /*
                                     * RazonSocialFowarderExterior
                                     * Obligatoria en el contrato AFIP.
                                     */
                                    $forwarderNames = $items
                                        ->map(
                                            fn ($item) => trim(
                                                (string) (
                                                    $item->foreign_forwarder_name
                                                    ?? ''
                                                )
                                            )
                                        );

                                    if ($forwarderNames->contains('')) {
                                        throw new Exception(
                                            "BL {$bol->bill_number}: todas las líneas deben "
                                            . 'tener razón social del forwarder exterior.'
                                        );
                                    }

                                    $uniqueForwarderNames = $forwarderNames
                                        ->unique()
                                        ->values();

                                    if ($uniqueForwarderNames->count() !== 1) {
                                        throw new Exception(
                                            "BL {$bol->bill_number}: las líneas tienen "
                                            . 'forwarders exteriores diferentes.'
                                        );
                                    }

                                    $forwarderName = $uniqueForwarderNames->first();

                                    if (mb_strlen($forwarderName) > 70) {
                                        throw new Exception(
                                            "BL {$bol->bill_number}: la razón social del "
                                            . 'forwarder exterior supera los 70 caracteres.'
                                        );
                                    }

                                    $w->writeElement(
                                        'ar:RazonSocialFowarderExterior',
                                        $forwarderName
                                    );

                                    /*
                                     * CUIT y país del forwarder son optativos,
                                     * pero si existen deben ser consistentes
                                     * entre las líneas del mismo título.
                                     */
                                    $forwarderTaxIds = $items
                                        ->map(
                                            fn ($item) => trim(
                                                (string) (
                                                    $item->foreign_forwarder_tax_id
                                                    ?? ''
                                                )
                                            )
                                        )
                                        ->filter()
                                        ->unique()
                                        ->values();

                                    if ($forwarderTaxIds->count() > 1) {
                                        throw new Exception(
                                            "BL {$bol->bill_number}: las líneas tienen "
                                            . 'identificadores fiscales de forwarder diferentes.'
                                        );
                                    }

                                    if ($forwarderTaxIds->count() === 1) {
                                        $forwarderTaxId = $forwarderTaxIds->first();

                                        if (strlen($forwarderTaxId) > 35) {
                                            throw new Exception(
                                                "BL {$bol->bill_number}: el identificador "
                                                . 'tributario del forwarder exterior supera '
                                                . 'los 35 caracteres admitidos por AFIP.'
                                            );
                                        }

                                        $w->writeElement(
                                            'ar:IndicadorTributarioForwarderExterior',
                                            $forwarderTaxId
                                        );
                                    }

                                    $forwarderCountries = $items
                                        ->map(
                                            fn ($item) => trim(
                                                (string) (
                                                    $item->foreign_forwarder_country
                                                    ?? ''
                                                )
                                            )
                                        )
                                        ->filter()
                                        ->unique()
                                        ->values();

                                    if ($forwarderCountries->count() > 1) {
                                        throw new Exception(
                                            "BL {$bol->bill_number}: las líneas tienen "
                                            . 'países de forwarder diferentes.'
                                        );
                                    }

                                    if ($forwarderCountries->count() === 1) {
                                        $forwarderCountry = $forwarderCountries->first();

                                        if (strlen($forwarderCountry) > 3) {
                                            throw new Exception(
                                                "BL {$bol->bill_number}: el código de país "
                                                . 'del forwarder supera los 3 caracteres.'
                                            );
                                        }

                                        $w->writeElement(
                                            'ar:CodigoPaisEmisorIdentificadorForwarderExterior',
                                            $forwarderCountry
                                        );
                                    }

                                    /*
                                     * Comentario del título: optativo.
                                     * Si distintas líneas contienen comentarios
                                     * distintos no se elige uno arbitrariamente.
                                     */
                                    $titleComments = $items
                                        ->map(
                                            fn ($item) => trim(
                                                (string) ($item->comments ?? '')
                                            )
                                        )
                                        ->filter()
                                        ->unique()
                                        ->values();

                                    if ($titleComments->count() > 1) {
                                        throw new Exception(
                                            "BL {$bol->bill_number}: las líneas tienen "
                                            . 'comentarios AFIP diferentes.'
                                        );
                                    }

                                    if ($titleComments->count() === 1) {
                                        $titleComment = $titleComments->first();

                                        if (mb_strlen($titleComment) > 60) {
                                            throw new Exception(
                                                "BL {$bol->bill_number}: el comentario AFIP "
                                                . 'supera los 60 caracteres.'
                                            );
                                        }

                                        $w->writeElement(
                                            'ar:Comentario',
                                            $titleComment
                                        );
                                    }

                                    /*
                                     * Códigos de descarga del título.
                                     * La fuente canónica está en BillOfLading.
                                     */
                                    $dischargeOperativeCode = trim(
                                        (string) (
                                            $bol->operational_discharge_code
                                            ?? ''
                                        )
                                    );

                                    if ($dischargeOperativeCode === '') {
                                        throw new Exception(
                                            "BL {$bol->bill_number}: falta el lugar operativo "
                                            . 'AFIP de descarga.'
                                        );
                                    }

                                    if (strlen($dischargeOperativeCode) > 5) {
                                        throw new Exception(
                                            "BL {$bol->bill_number}: el lugar operativo "
                                            . 'de descarga supera los 5 caracteres.'
                                        );
                                    }

                                    $dischargeCustomsCode = trim(
                                        (string) (
                                            $bol->discharge_customs_code
                                            ?? ''
                                        )
                                    );

                                    if ($dischargeCustomsCode === '') {
                                        throw new Exception(
                                            "BL {$bol->bill_number}: falta la aduana "
                                            . 'AFIP de descarga.'
                                        );
                                    }

                                    if (strlen($dischargeCustomsCode) > 3) {
                                        throw new Exception(
                                            "BL {$bol->bill_number}: el código de aduana "
                                            . 'de descarga supera los 3 caracteres.'
                                        );
                                    }

                                    $w->writeElement(
                                        'ar:CodigoLugarOperativoDescarga',
                                        $dischargeOperativeCode
                                    );

                                    $w->writeElement(
                                        'ar:CodigoAduanaDescarga',
                                        $dischargeCustomsCode
                                    );

                                    // 4. Mercaderías (obligatorio)
                                    $w->startElement('ar:Mercaderias');

                                    $lineNumbers = [];

                                    foreach ($items as $item) {
                                        /*
                                         * NumeroLinea
                                         * Obligatorio, numérico, máximo 3 dígitos
                                         * y no puede repetirse dentro del título.
                                         */
                                        if (
                                            $item->line_number === null
                                            || !is_numeric($item->line_number)
                                        ) {
                                            throw new Exception(
                                                "BL {$bol->bill_number}: existe una línea "
                                                . 'sin número de línea válido.'
                                            );
                                        }

                                        $lineNumber = (int) $item->line_number;

                                        if ($lineNumber < 1 || $lineNumber > 999) {
                                            throw new Exception(
                                                "BL {$bol->bill_number}: número de línea "
                                                . "{$lineNumber} fuera del rango admitido por AFIP."
                                            );
                                        }

                                        if (in_array($lineNumber, $lineNumbers, true)) {
                                            throw new Exception(
                                                "BL {$bol->bill_number}: el número de línea "
                                                . "{$lineNumber} está repetido."
                                            );
                                        }

                                        $lineNumbers[] = $lineNumber;

                                        /*
                                         * CodigoEmbalaje
                                         * ShipmentItem.packaging_code es el campo
                                         * específico preservado para webservices.
                                         */
                                        $packagingCode = trim(
                                            (string) ($item->packaging_code ?? '')
                                        );

                                        if ($packagingCode === '') {
                                            throw new Exception(
                                                "BL {$bol->bill_number}, línea {$lineNumber}: "
                                                . 'falta el código de embalaje.'
                                            );
                                        }

                                        if (strlen($packagingCode) > 2) {
                                            throw new Exception(
                                                "BL {$bol->bill_number}, línea {$lineNumber}: "
                                                . "el código de embalaje '{$packagingCode}' "
                                                . 'supera los 2 caracteres admitidos por AFIP.'
                                            );
                                        }

                                        /*
                                         * Si CodigoEmbalaje = 05, AFIP exige
                                         * CondicionContenedor.
                                         *
                                         * La fuente real es ShipmentItem.container_condition.
                                         * No inferirla desde Container ni asignar H/P.
                                         */
                                        $itemContainerCondition = trim(
                                            (string) ($item->container_condition ?? '')
                                        );

                                        if (
                                            $packagingCode === '05'
                                            && $itemContainerCondition === ''
                                        ) {
                                            throw new Exception(
                                                "BL {$bol->bill_number}, línea {$lineNumber}: "
                                                . 'el embalaje 05 requiere CondicionContenedor.'
                                            );
                                        }

                                        if (
                                            $itemContainerCondition !== ''
                                            && strlen($itemContainerCondition) !== 1
                                        ) {
                                            throw new Exception(
                                                "BL {$bol->bill_number}, línea {$lineNumber}: "
                                                . 'CondicionContenedor debe tener 1 carácter.'
                                            );
                                        }

                                        /*
                                         * CantidadManifestada
                                         */
                                        if (
                                            $item->package_quantity === null
                                            || !is_numeric($item->package_quantity)
                                        ) {
                                            throw new Exception(
                                                "BL {$bol->bill_number}, línea {$lineNumber}: "
                                                . 'falta la cantidad manifestada.'
                                            );
                                        }

                                        $packageQuantity = (int) $item->package_quantity;

                                        if (
                                            $packageQuantity < 0
                                            || $packageQuantity > 999999999
                                        ) {
                                            throw new Exception(
                                                "BL {$bol->bill_number}, línea {$lineNumber}: "
                                                . 'cantidad manifestada fuera del rango AFIP.'
                                            );
                                        }

                                        /*
                                         * PesoVolumenManifestado
                                         */
                                        if (
                                            $item->gross_weight_kg === null
                                            || !is_numeric($item->gross_weight_kg)
                                        ) {
                                            throw new Exception(
                                                "BL {$bol->bill_number}, línea {$lineNumber}: "
                                                . 'falta el peso/volumen manifestado.'
                                            );
                                        }

                                        $grossWeight = (float) $item->gross_weight_kg;

                                        if (
                                            $grossWeight < 0
                                            || $grossWeight > 99999999.999
                                        ) {
                                            throw new Exception(
                                                "BL {$bol->bill_number}, línea {$lineNumber}: "
                                                . 'peso/volumen manifestado fuera del rango AFIP.'
                                            );
                                        }

                                        /*
                                         * DescripcionMercaderia
                                         */
                                        $description = trim(
                                            (string) ($item->item_description ?? '')
                                        );

                                        if ($description === '') {
                                            throw new Exception(
                                                "BL {$bol->bill_number}, línea {$lineNumber}: "
                                                . 'falta la descripción de la mercadería.'
                                            );
                                        }

                                        if (mb_strlen($description) > 80) {
                                            throw new Exception(
                                                "BL {$bol->bill_number}, línea {$lineNumber}: "
                                                . 'la descripción de la mercadería supera '
                                                . 'los 80 caracteres admitidos por AFIP.'
                                            );
                                        }

                                        /*
                                         * NumeroBultos
                                         * Obligatorio según el manual.
                                         *
                                         * La fuente preservada desde CUSCAR PCI
                                         * es ShipmentItem.cargo_marks.
                                         * No enviar "S/M" ni otro texto inventado.
                                         */
                                        $packageMarks = trim(
                                            (string) ($item->cargo_marks ?? '')
                                        );

                                        if ($packageMarks === '') {
                                            throw new Exception(
                                                "BL {$bol->bill_number}, línea {$lineNumber}: "
                                                . 'falta el número/marca de los bultos.'
                                            );
                                        }

                                        if (mb_strlen($packageMarks) > 100) {
                                            throw new Exception(
                                                "BL {$bol->bill_number}, línea {$lineNumber}: "
                                                . 'el número/marca de los bultos supera '
                                                . 'los 100 caracteres admitidos por AFIP.'
                                            );
                                        }

                                        $w->startElement('ar:LineaMercaderia');

                                            $w->writeElement(
                                                'ar:NumeroLinea',
                                                (string) $lineNumber
                                            );

                                            $w->writeElement(
                                                'ar:CodigoEmbalaje',
                                                $packagingCode
                                            );

                                            if ($packagingCode === '05') {
                                                $w->writeElement(
                                                    'ar:CondicionContenedor',
                                                    $itemContainerCondition
                                                );
                                            }

                                            $w->writeElement(
                                                'ar:CantidadManifestada',
                                                (string) $packageQuantity
                                            );

                                            $w->writeElement(
                                                'ar:PesoVolumenManifestado',
                                                number_format(
                                                    $grossWeight,
                                                    3,
                                                    '.',
                                                    ''
                                                )
                                            );

                                            $w->writeElement(
                                                'ar:DescripcionMercaderia',
                                                $description
                                            );

                                            $w->writeElement(
                                                'ar:NumeroBultos',
                                                $packageMarks
                                            );

                                        $w->endElement(); // LineaMercaderia
                                    }

                                    $w->endElement(); // Mercaderias
                                    
                                    // 5. Contenedores
                                    $containers = collect();

                                    foreach ($items as $item) {
                                        $containers = $containers->merge(
                                            $item->containers
                                        );
                                    }

                                    $containers = $containers
                                        ->unique('container_number')
                                        ->values();
                                    
                                    if ($containers->isNotEmpty()) {
                                        $w->startElement('ar:Contenedores');
                                        
                                        foreach ($containers as $container) {
                                            $containerNumber = trim(
                                                (string) ($container->container_number ?? '')
                                            );

                                            if ($containerNumber === '') {
                                                throw new Exception(
                                                    "BL {$bol->bill_number}: existe un contenedor "
                                                    . 'sin identificador.'
                                                );
                                            }

                                            if (strlen($containerNumber) > 20) {
                                                throw new Exception(
                                                    "Contenedor {$containerNumber}: el identificador "
                                                    . 'supera los 20 caracteres admitidos por AFIP.'
                                                );
                                            }

                                            /*
                                             * Características del contenedor:
                                             * AFIP espera un código de 4 caracteres.
                                             * container_types.iso_size_type contiene el código
                                             * ISO tamaño/tipo real.
                                             */
                                            $containerType = trim(
                                                (string) (
                                                    $container->containerType?->iso_size_type
                                                    ?? ''
                                                )
                                            );

                                            if (strlen($containerType) !== 4) {
                                                throw new Exception(
                                                    "Contenedor {$containerNumber}: falta un código "
                                                    . 'ISO de tamaño/tipo de 4 caracteres válido.'
                                                );
                                            }

                                            /*
                                             * Para los contenedores asociados a títulos cargados
                                             * la condición AFIP está persistida en
                                             * container_condition: H / P.
                                             *
                                             * No confundir con containers.condition, que representa
                                             * el estado físico/operativo V/D/S/P/L/R.
                                             */
                                            $containerCondition = trim(
                                                (string) ($container->container_condition ?? '')
                                            );

                                            if (!in_array(
                                                $containerCondition,
                                                ['H', 'P'],
                                                true
                                            )) {
                                                throw new Exception(
                                                    "Contenedor {$containerNumber}: falta una condición "
                                                    . 'AFIP válida H/P.'
                                                );
                                            }

                                            $tare = $container->tare_weight_kg;

                                            if ($tare === null || (float) $tare <= 0) {
                                                throw new Exception(
                                                    "Contenedor {$containerNumber}: falta la tara real."
                                                );
                                            }

                                            if ((float) $tare != floor((float) $tare)) {
                                                throw new Exception(
                                                    "Contenedor {$containerNumber}: la tara tiene "
                                                    . 'decimales y AFIP exige Tara entera en Kg.'
                                                );
                                            }

                                            $grossWeight = $container->current_gross_weight_kg;

                                            if (
                                                $grossWeight === null
                                                || (float) $grossWeight <= 0
                                            ) {
                                                throw new Exception(
                                                    "Contenedor {$containerNumber}: falta el peso "
                                                    . 'bruto real requerido por AFIP.'
                                                );
                                            }

                                            if (
                                                (float) $grossWeight
                                                != floor((float) $grossWeight)
                                            ) {
                                                throw new Exception(
                                                    "Contenedor {$containerNumber}: el peso bruto "
                                                    . 'tiene decimales y no existe una regla de '
                                                    . 'redondeo definida para AFIP.'
                                                );
                                            }

                                            if ((float) $tare > (float) $grossWeight) {
                                                throw new Exception(
                                                    "Contenedor {$containerNumber}: la tara no puede "
                                                    . 'ser mayor al peso bruto.'
                                                );
                                            }

                                            /*
                                             * AFIP exige FechaVencimientoContenedor o ACEP.
                                             * La app no tiene actualmente un campo ACEP
                                             * identificado, por lo que no se fabrica ninguno.
                                             */
                                            if (empty($container->expiry_date)) {
                                                throw new Exception(
                                                    "Contenedor {$containerNumber}: falta la fecha "
                                                    . 'de vencimiento y no existe ACEP informado.'
                                                );
                                            }

                                            $dischargeCustomsCode = trim(
                                                (string) (
                                                    $bol->discharge_customs_code ?? ''
                                                )
                                            );

                                            if ($dischargeCustomsCode === '') {
                                                throw new Exception(
                                                    "BL {$bol->bill_number}: falta la aduana AFIP "
                                                    . 'de descarga.'
                                                );
                                            }

                                            $operationalDischargeCode = trim(
                                                (string) (
                                                    $bol->operational_discharge_code
                                                    ?? ''
                                                )
                                            );

                                            if ($operationalDischargeCode === '') {
                                                throw new Exception(
                                                    "BL {$bol->bill_number}: falta el lugar operativo "
                                                    . 'AFIP de descarga.'
                                                );
                                            }

                                            $w->startElement('ar:Contenedor');

                                                $w->writeElement(
                                                    'CaracteristicasContenedor',
                                                    $containerType
                                                );

                                                $w->writeElement(
                                                    'IdentificadorContenedor',
                                                    $containerNumber
                                                );

                                                $w->writeElement(
                                                    'CondicionContenedor',
                                                    $containerCondition
                                                );

                                                $w->writeElement(
                                                    'Tara',
                                                    (string) ((int) $tare)
                                                );

                                                $w->writeElement(
                                                    'PesoBruto',
                                                    (string) ((int) $grossWeight)
                                                );

                                                // Precinto de origen: sólo si está declarado.
                                                if (!empty($container->shipper_seal)) {
                                                    $w->writeElement(
                                                        'NumeroPrecintoOrigen',
                                                        substr(
                                                            (string) $container->shipper_seal,
                                                            0,
                                                            35
                                                        )
                                                    );
                                                }

                                                $w->writeElement(
                                                    'FechaVencimientoContenedor',
                                                    \Carbon\Carbon::parse(
                                                        $container->expiry_date
                                                    )->format('Y-m-d\TH:i:s')
                                                );

                                                // Datos operativos reales del conocimiento.
                                                if ($bol->loadingPort) {
                                                    $w->writeElement(
                                                        'CodigoPuertoEmbarque',
                                                        substr(
                                                            (string) $bol->loadingPort->code,
                                                            0,
                                                            5
                                                        )
                                                    );
                                                }

                                                if ($bol->loading_date) {
                                                    $w->writeElement(
                                                        'FechaEmbarque',
                                                        $bol->loading_date
                                                            ->format('Y-m-d\TH:i:s')
                                                    );
                                                }

                                                if ($bol->origin_loading_date) {
                                                    $w->writeElement(
                                                        'FechaCargaLugarOrigen',
                                                        $bol->origin_loading_date
                                                            ->format('Y-m-d\TH:i:s')
                                                    );
                                                }

                                                if (!empty($bol->origin_operative_code)) {
                                                    $w->writeElement(
                                                        'CodigoLugarOrigen',
                                                        substr(
                                                            (string) $bol->origin_operative_code,
                                                            0,
                                                            5
                                                        )
                                                    );
                                                }

                                                if ($bol->dischargePort) {
                                                    $w->writeElement(
                                                        'CodigoPuertoDescarga',
                                                        substr(
                                                            (string) $bol->dischargePort->code,
                                                            0,
                                                            5
                                                        )
                                                    );
                                                }

                                                if ($bol->discharge_date) {
                                                    $w->writeElement(
                                                        'FechaDescarga',
                                                        $bol->discharge_date
                                                            ->format('Y-m-d\TH:i:s')
                                                    );
                                                }

                                                $w->writeElement(
                                                    'CodigoAduana',
                                                    substr(
                                                        $dischargeCustomsCode,
                                                        0,
                                                        3
                                                    )
                                                );

                                                $w->writeElement(
                                                    'CodigoLugarOperativoDescarga',
                                                    substr(
                                                        $operationalDischargeCode,
                                                        0,
                                                        5
                                                    )
                                                );

                                            $w->endElement(); // Contenedor
                                        }
                                        
                                        $w->endElement(); // Contenedores
                                    }
                                    
                                $w->endElement(); // Titulo
                            }
                            
                            $w->endElement(); // Titulos
                        $w->endElement(); // InformacionTitulosDoc

                    $w->endElement(); // argRegistrarTitulosCBC
                $w->endElement(); // RegistrarTitulosCbc
            $w->endElement(); // Body
            $w->endElement(); // Envelope

            $w->endDocument();
            
            $xmlContent = $w->outputMemory();
            
            \Log::info('XML RegistrarTitulosCbc generado', [
                'identificador_viaje' => $identificadorViaje,
                'bills_count' => $billsOfLading->count(),
                'xml_size' => strlen($xmlContent)
            ]);
            
            return $xmlContent;

        } catch (Exception $e) {
            \Log::error('Error en createRegistrarTitulosCbcXml: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * ================================================================================
     * MÉTODO: CerrarViaje - Cierre de Información Anticipada
     * ================================================================================
     * 
     * Genera XML para cerrar viaje de Información Anticipada Argentina.
     * Envía títulos, líneas de mercadería y contenedores que NO descargan en puerto argentino.
     * 
     * SEGÚN MANUAL AFIP CSMIC202506133994.pdf - Sección I) CERRARVIAJE
     * 
     * @param Voyage $voyage
     * @param Company $company
     * @return string XML generado
     */
    public function generateCerrarViajeXml(Voyage $voyage, Company $company): string
    {
        // Generar IdTransaccion único
        $idTransaccion = 'CV' . date('YmdHis') . str_pad($voyage->id, 6, '0', STR_PAD_LEFT);
        
        // Verificar que el viaje tenga IdentificadorViaje de AFIP
        if (empty($voyage->argentina_voyage_id)) {
            throw new \Exception("El viaje debe tener argentina_voyage_id para cerrar. Primero ejecute RegistrarViaje.");
        }
        
        // Obtener Bills of Lading que NO descargan en Argentina
        $billsNoArgentina = $voyage->billsOfLading()
            ->with([
                'loadingPort.country',
                'dischargePort.country',
                'transshipmentPort.country',
                'shipmentItems.cargoType',
                'shipmentItems.packagingType',
                'shipmentItems.containers.containerType'
            ])
            ->whereHas('dischargePort.country', function($query) {
                $query->where('code', '!=', 'AR');
            })
            ->get();
        
        // Si no hay conocimientos que NO descargan en Argentina, no se puede cerrar
        if ($billsNoArgentina->isEmpty()) {
            throw new \Exception("No hay conocimientos que descarguen fuera de Argentina para cerrar el viaje.");
        }
        
        // Iniciar construcción del XML
        $xml = '<?xml version="1.0" encoding="utf-8"?>' . "\n";
        $xml .= '<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" ';
        $xml .= 'xmlns:ar="Ar.Gob.Afip.Dga.Org.wgesinformacionanticipada">' . "\n";
        $xml .= '  <soap:Body>' . "\n";
        $xml .= '    <ar:CerrarViaje>' . "\n";
        
        // argWSAutenticacionEmpresa (será llenado por el servicio con tokens WSAA)
        $xml .= '      <ar:argWSAutenticacionEmpresa>' . "\n";
        $xml .= '        <ar:Token>__TOKEN__</ar:Token>' . "\n";
        $xml .= '        <ar:Sign>__SIGN__</ar:Sign>' . "\n";
        $xml .= '        <ar:CuitEmpresaConectada>' . $this->cleanNumeric($company->tax_id) . '</ar:CuitEmpresaConectada>' . "\n";
        $xml .= '        <ar:TipoAgente>TRSP</ar:TipoAgente>' . "\n";
        $xml .= '        <ar:Rol>TRSP</ar:Rol>' . "\n";
        $xml .= '      </ar:argWSAutenticacionEmpresa>' . "\n";
        
        // argCerrarViaje
        $xml .= '      <ar:argCerrarViaje>' . "\n";
        $xml .= '        <ar:IdTransaccion>' . $this->cleanString($idTransaccion) . '</ar:IdTransaccion>' . "\n";
        
        // InformacionTitulosCierreDoc
        $xml .= '        <ar:InformacionTitulosCierreDoc>' . "\n";
        $xml .= '          <ar:IdentificadorViaje>' . $this->cleanString($voyage->argentina_voyage_id) . '</ar:IdentificadorViaje>' . "\n";
        
        // Titulos (conocimientos que NO descargan en Argentina)
        $xml .= '          <ar:Titulos>' . "\n";
        
        foreach ($billsNoArgentina as $bill) {
            $xml .= $this->generateTituloCierreXml($bill);
        }
        
        $xml .= '          </ar:Titulos>' . "\n";
        
        // ContenedoresVaciosCorreo (opcional - por ahora no lo implementamos)
        // Este campo es para contenedores vacíos que se envían por correo
        // Si en el futuro se necesita, se puede agregar aquí
        
        $xml .= '        </ar:InformacionTitulosCierreDoc>' . "\n";
        $xml .= '      </ar:argCerrarViaje>' . "\n";
        $xml .= '    </ar:CerrarViaje>' . "\n";
        $xml .= '  </soap:Body>' . "\n";
        $xml .= '</soap:Envelope>';
        
        return $xml;
    }

    /**
     * Genera XML para un TituloCierre individual
     * 
     * @param BillOfLading $bill
     * @return string
     */
    private function generateTituloCierreXml(BillOfLading $bill): string
    {
        $xml = '';
        $xml .= '            <ar:TituloCierre>' . "\n";
        
        // CAMPOS OBLIGATORIOS
        
        // FechaEmbarque (S)
        $xml .= '              <ar:FechaEmbarque>' . $this->formatDateTime($bill->loading_date) . '</ar:FechaEmbarque>' . "\n";
        
        // CodigoPuertoEmbarque (S)
        $codigoPuertoEmbarque = $bill->loadingPort ? $bill->loadingPort->code : '';
        $xml .= '              <ar:CodigoPuertoEmbarque>' . $this->cleanString($codigoPuertoEmbarque) . '</ar:CodigoPuertoEmbarque>' . "\n";
        
        // CAMPOS OPCIONALES (solo si tienen valor)
        
        // FechaCargaLugarOrigen (N)
        if (!empty($bill->origin_loading_date)) {
            $xml .= '              <ar:FechaCargaLugarOrigen>' . $this->formatDateTime($bill->origin_loading_date) . '</ar:FechaCargaLugarOrigen>' . "\n";
        }
        
        // LugarOrigen (N)
        if (!empty($bill->origin_location)) {
            $xml .= '              <ar:LugarOrigen>' . $this->cleanString($bill->origin_location, 50) . '</ar:LugarOrigen>' . "\n";
        }
        
        // CodigoPaisLugarOrigen (N)
        if (!empty($bill->origin_country_code)) {
            $xml .= '              <ar:CodigoPaisLugarOrigen>' . $this->cleanString($bill->origin_country_code, 3) . '</ar:CodigoPaisLugarOrigen>' . "\n";
        }
        
        // NumeroConocimiento (S)
        $xml .= '              <ar:NumeroConocimiento>' . $this->cleanString($bill->bill_number, 18) . '</ar:NumeroConocimiento>' . "\n";
        
        // CodigoPuertoTrasbordo (N)
        if ($bill->transshipmentPort) {
            $xml .= '              <ar:CodigoPuertoTrasbordo>' . $this->cleanString($bill->transshipmentPort->code, 5) . '</ar:CodigoPuertoTrasbordo>' . "\n";
        }
        
        // CodigoPuertoDescarga (S)
        $codigoPuertoDescarga = $bill->dischargePort ? $bill->dischargePort->code : '';
        $xml .= '              <ar:CodigoPuertoDescarga>' . $this->cleanString($codigoPuertoDescarga, 5) . '</ar:CodigoPuertoDescarga>' . "\n";
        
        // FechaDescarga (N)
        if (!empty($bill->discharge_date)) {
            $xml .= '              <ar:FechaDescarga>' . $this->formatDateTime($bill->discharge_date) . '</ar:FechaDescarga>' . "\n";
        }
        
        // CodigoPaisDestino (S)
        $codigoPaisDestino = $bill->destination_country_code ?: ($bill->dischargePort && $bill->dischargePort->country ? $bill->dischargePort->country->code : '');
        $xml .= '              <ar:CodigoPaisDestino>' . $this->cleanString($codigoPaisDestino, 3) . '</ar:CodigoPaisDestino>' . "\n";
        
        // MarcaBultos (S)
        $marcaBultos = !empty($bill->cargo_marks) ? $bill->cargo_marks : 'S/M';
        $xml .= '              <ar:MarcaBultos>' . $this->cleanString($marcaBultos, 80) . '</ar:MarcaBultos>' . "\n";
        
        // IndicadorConsolidado (S) - N o S
        $indicadorConsolidado = $bill->is_consolidated ? 'S' : 'N';
        $xml .= '              <ar:IndicadorConsolidado>' . $indicadorConsolidado . '</ar:IndicadorConsolidado>' . "\n";
        
        // IndicadorTransitoTrasbordo (S) - N o S
        $indicadorTransitoTrasbordo = $bill->is_transit_transshipment ? 'S' : 'N';
        $xml .= '              <ar:IndicadorTransitoTrasbordo>' . $indicadorTransitoTrasbordo . '</ar:IndicadorTransitoTrasbordo>' . "\n";
        
        // Los siguientes campos vienen del PRIMER ShipmentItem (representativo del BOL)
        $firstItem = $bill->shipmentItems->first();
        
        if ($firstItem) {
            // PosicionArancelaria (S)
            $posicionArancelaria = $firstItem->tariff_position ?: '0000.00.00.000P';
            $xml .= '              <ar:PosicionArancelaria>' . $this->cleanString($posicionArancelaria, 16) . '</ar:PosicionArancelaria>' . "\n";
            
            // IndicadorOperadorLogisticoSeguro (S) - N o S
            $indicadorOLS = ($firstItem->is_secure_logistics_operator === 'S') ? 'S' : 'N';
            $xml .= '              <ar:IndicadorOperadorLogisticoSeguro>' . $indicadorOLS . '</ar:IndicadorOperadorLogisticoSeguro>' . "\n";
            
            // IndicadorTransitoMonitoreado (S) - N o S
            $indicadorTM = ($firstItem->is_monitored_transit === 'S') ? 'S' : 'N';
            $xml .= '              <ar:IndicadorTransitoMonitoreado>' . $indicadorTM . '</ar:IndicadorTransitoMonitoreado>' . "\n";
            
            // IndicadorRenar (S) - N o S
            $indicadorRenar = ($firstItem->is_renar === 'S') ? 'S' : 'N';
            $xml .= '              <ar:IndicadorRenar>' . $indicadorRenar . '</ar:IndicadorRenar>' . "\n";
            
            // RazonSocialFowarderExterior (S)
            $forwarderName = !empty($firstItem->foreign_forwarder_name) ? $firstItem->foreign_forwarder_name : 'N/A';
            $xml .= '              <ar:RazonSocialFowarderExterior>' . $this->cleanString($forwarderName, 70) . '</ar:RazonSocialFowarderExterior>' . "\n";
            
            // IndicadorTributarioForwarderExterior (N)
            if (!empty($firstItem->foreign_forwarder_tax_id)) {
                $xml .= '              <ar:IndicadorTributarioForwarderExterior>' . $this->cleanString($firstItem->foreign_forwarder_tax_id, 35) . '</ar:IndicadorTributarioForwarderExterior>' . "\n";
            }
            
            // CodigoPaisEmisorIdentificadorForwarderExterior (N)
            if (!empty($firstItem->foreign_forwarder_country)) {
                $xml .= '              <ar:CodigoPaisEmisorIdentificadorForwarderExterior>' . $this->cleanString($firstItem->foreign_forwarder_country, 3) . '</ar:CodigoPaisEmisorIdentificadorForwarderExterior>' . "\n";
            }
            
            // Comentario (N)
            if (!empty($firstItem->comments)) {
                $xml .= '              <ar:Comentario>' . $this->cleanString($firstItem->comments, 60) . '</ar:Comentario>' . "\n";
            }
        }
        
        // Mercaderias (líneas de mercadería)
        $xml .= '              <ar:Mercaderias>' . "\n";
        
        foreach ($bill->shipmentItems as $item) {
            $xml .= $this->generateLineaMercaderiaCierreXml($item);
        }
        
        $xml .= '              </ar:Mercaderias>' . "\n";
        
        // Contenedores (obtenidos a través de shipmentItems)
        $containers = $bill->shipmentItems->flatMap(function($item) {
            return $item->containers ?? collect();
        });

        if ($containers->count() > 0) {
            $xml .= '              <ar:Contenedores>' . "\n";
            
            foreach ($containers as $container) {
                $xml .= $this->generateContenedorCierreXml($container, $bill);
            }
            
            $xml .= '              </ar:Contenedores>' . "\n";
        }
        
        $xml .= '            </ar:TituloCierre>' . "\n";
        
        return $xml;
    }

    /**
     * Genera XML para una LineaMercaderia de cierre
     * 
     * @param ShipmentItem $item
     * @return string
     */
    private function generateLineaMercaderiaCierreXml(ShipmentItem $item): string
    {
        $xml = '';
        $xml .= '                <ar:LineaMercaderia>' . "\n";
        
        // NumeroLinea (S)
        $xml .= '                  <ar:NumeroLinea>' . intval($item->line_number) . '</ar:NumeroLinea>' . "\n";
        
        // CodigoEmbalaje (S)
        $codigoEmbalaje = $item->packaging_code ?: ($item->packagingType ? $item->packagingType->code : '33');
        $xml .= '                  <ar:CodigoEmbalaje>' . $this->cleanString($codigoEmbalaje, 2) . '</ar:CodigoEmbalaje>' . "\n";
        
        // TipoEmbalaje (N) - Opcional
        if ($item->packagingType && !empty($item->packagingType->name)) {
            $tipoEmbalaje = substr($item->packagingType->name, 0, 1); // Primera letra
            $xml .= '                  <ar:TipoEmbalaje>' . $tipoEmbalaje . '</ar:TipoEmbalaje>' . "\n";
        }
        
        // CondicionContenedor (N) - Solo si aplica
        // Por ahora lo dejamos vacío, se puede agregar lógica específica si se necesita
        
        // CantidadManifestada (S)
        $xml .= '                  <ar:CantidadManifestada>' . intval($item->package_quantity) . '</ar:CantidadManifestada>' . "\n";
        
        // PesoVolumenManifestado (S)
        $pesoVolumen = number_format($item->gross_weight_kg, 2, '.', '');
        $xml .= '                  <ar:PesoVolumenManifestado>' . $pesoVolumen . '</ar:PesoVolumenManifestado>' . "\n";
        
        // DescripcionMercaderia (S)
        $descripcion = !empty($item->item_description) ? $item->item_description : 'Mercadería general';
        $xml .= '                  <ar:DescripcionMercaderia>' . $this->cleanString($descripcion, 80) . '</ar:DescripcionMercaderia>' . "\n";
        
        // NumeroBultos (S)
        $numeroBultos = !empty($item->cargo_marks) ? $item->cargo_marks : 'S/M';
        $xml .= '                  <ar:NumeroBultos>' . $this->cleanString($numeroBultos, 100) . '</ar:NumeroBultos>' . "\n";
        
        // TipoCarga (N) - Opcional, se puede mapear desde cargoType si existe
        if ($item->cargoType && !empty($item->cargoType->code)) {
            $xml .= '                  <ar:TipoCarga>' . $this->cleanString($item->cargoType->code, 3) . '</ar:TipoCarga>' . "\n";
        }
        
        // Comentario (N)
        if (!empty($item->comments)) {
            $xml .= '                  <ar:Comentario>' . $this->cleanString($item->comments, 60) . '</ar:Comentario>' . "\n";
        }
        
        $xml .= '                </ar:LineaMercaderia>' . "\n";
        
        return $xml;
    }

    /**
     * Genera XML para un ContenedorCierre
     * 
     * @param Container $container
     * @param BillOfLading $bill
     * @return string
     */
    private function generateContenedorCierreXml(Container $container, BillOfLading $bill): string
    {
        $xml = '';
        $xml .= '                <ar:ContenedorCierre>' . "\n";
        
        // CuitAtaOperadorContenedor (S)
        $cuitOperador = $bill->shipment && $bill->shipment->voyage && $bill->shipment->voyage->company 
            ? $this->cleanNumeric($bill->shipment->voyage->company->tax_id) 
            : '';
        $xml .= '                  <CuitAtaOperadorContenedor>' . $cuitOperador . '</CuitAtaOperadorContenedor>' . "\n";
        
        // CaracteristicasContenedor (S)
        $caracteristicas = $container->containerType ? $container->containerType->code : '22G1';
        $xml .= '                  <CaracteristicasContenedor>' . $this->cleanString($caracteristicas, 4) . '</CaracteristicasContenedor>' . "\n";
        
        // IdentificadorContenedor (S)
        $xml .= '                  <IdentificadorContenedor>' . $this->cleanString($container->container_number, 20) . '</IdentificadorContenedor>' . "\n";
        
        // CondicionContenedor (S)
        $condicion = !empty($container->container_condition) ? $container->container_condition : 'H';
        $xml .= '                  <CondicionContenedor>' . $condicion . '</CondicionContenedor>' . "\n";
        
        // Tara (S) - en KG, sin decimales
        $tara = intval($container->tare_weight ?: 0);
        $xml .= '                  <Tara>' . $tara . '</Tara>' . "\n";
        
        // PesoBruto (S) - en KG, sin decimales
        $pesoBruto = intval($container->gross_weight ?: 0);
        $xml .= '                  <PesoBruto>' . $pesoBruto . '</PesoBruto>' . "\n";
        
        // NumeroPrecintoOrigen (N)
        if (!empty($container->seals)) {
            $seals = is_array($container->seals) ? $container->seals : json_decode($container->seals, true);
            if (is_array($seals) && count($seals) > 0) {
                $primerPrecinto = is_array($seals[0]) ? ($seals[0]['number'] ?? '') : $seals[0];
                $xml .= '                  <NumeroPrecintoOrigen>' . $this->cleanString($primerPrecinto, 35) . '</NumeroPrecintoOrigen>' . "\n";
            }
        }
        
        // FechaVencimientoContenedor (N) - Solo año y mes
        // Por ahora no lo incluimos si no tenemos el dato
        
        // Acep (N) - Campo opcional
        
        // CodigoPuertoEmbarque (N)
        if ($bill->loadingPort) {
            $xml .= '                  <CodigoPuertoEmbarque>' . $this->cleanString($bill->loadingPort->code, 5) . '</CodigoPuertoEmbarque>' . "\n";
        }
        
        // FechaEmbarque (N)
        if (!empty($bill->loading_date)) {
            $xml .= '                  <FechaEmbarque>' . $this->formatDateTime($bill->loading_date) . '</FechaEmbarque>' . "\n";
        }
        
        // FechaCargaLugarOrigen (N)
        if (!empty($bill->origin_loading_date)) {
            $xml .= '                  <FechaCargaLugarOrigen>' . $this->formatDateTime($bill->origin_loading_date) . '</FechaCargaLugarOrigen>' . "\n";
        }
        
        // CodigoLugarOrigen (N)
        if (!empty($bill->origin_location)) {
            $xml .= '                  <CodigoLugarOrigen>' . $this->cleanString($bill->origin_location, 5) . '</CodigoLugarOrigen>' . "\n";
        }
        
        // CodigoPaisLugarOrigen (N)
        if (!empty($bill->origin_country_code)) {
            $xml .= '                  <CodigoPaisLugarOrigen>' . $this->cleanString($bill->origin_country_code, 3) . '</CodigoPaisLugarOrigen>' . "\n";
        }
        
        // CodigoPuertoDescarga (N)
        if ($bill->dischargePort) {
            $xml .= '                  <CodigoPuertoDescarga>' . $this->cleanString($bill->dischargePort->code, 5) . '</CodigoPuertoDescarga>' . "\n";
        }
        
        // FechaDescarga (N)
        if (!empty($bill->discharge_date)) {
            $xml .= '                  <FechaDescarga>' . $this->formatDateTime($bill->discharge_date) . '</FechaDescarga>' . "\n";
        }
        
        // Comentario (N)
        if (!empty($container->notes)) {
            $xml .= '                  <Comentario>' . $this->cleanString($container->notes, 60) . '</Comentario>' . "\n";
        }
        
        $xml .= '                </ar:ContenedorCierre>' . "\n";
        
        return $xml;
    }

    /**
     * Agregar información del viaje al XML
     */
    private function addVoyageInformation(\XMLWriter $w, Voyage $voyage): void
    {
        // 1. IdentificadorViajeAnterior (opcional)
        if ($voyage->parent_voyage_id) {
            $w->writeElement('IdentificadorViajeAnterior', (string)$voyage->parent_voyage_id);
        }

        // 2. IdentificadorMedioTransporte (obligatorio)
        $vesselNumber = $voyage->leadVessel?->registration_number ?? $voyage->leadVessel?->name ?? 'SIN_REGISTRO';
        $w->writeElement('IdentificadorMedioTransporte', substr($vesselNumber, 0, 20));

        // 3. CodigoPaisProcedencia (obligatorio)
        $originCountryCode = $this->getCountryCode($voyage->originPort?->country?->alpha2_code ?? 'AR');
        $w->writeElement('CodigoPaisProcedencia', $originCountryCode);

        // 4. CodigoPuertoOrigen (obligatorio)
        $originPortCode = $this->getPortCustomsCode($voyage->originPort?->code ?? 'ARBUE');
        $w->writeElement('CodigoPuertoOrigen', $originPortCode);

        // 5. CodigoPaisFinViaje (obligatorio)
        $destinationCountryCode = $this->getCountryCode($voyage->destinationPort?->country?->alpha2_code ?? 'PY');
        $w->writeElement('CodigoPaisFinViaje', $destinationCountryCode);

        // 6. CodigoPuertoDestino (obligatorio)
        $destinationPortCode = $this->getPortCustomsCode($voyage->destinationPort?->code ?? 'PYTVT');
        $w->writeElement('CodigoPuertoDestino', $destinationPortCode);

        /*
         * Fecha de arribo.
         *
         * Es obligatoria para Información Anticipada y AFIP la define
         * con formato AAAAMMDD.
         *
         * No se deriva de la salida ni de la fecha actual.
         */
        if (!$voyage->estimated_arrival_date) {
            throw new Exception(
                'El viaje no tiene fecha estimada de arribo. '
                . 'AFIP exige FechaArribo para RegistrarViaje.'
            );
        }

        $w->writeElement(
            'FechaArribo',
            $voyage->estimated_arrival_date->format('Ymd')
        );

        /*
         * Fecha de inicio/embarque: optativa.
         * Se informa únicamente cuando existe una fecha de salida real.
         */
        if ($voyage->departure_date) {
            $w->writeElement(
                'FechaEmbarque',
                $voyage->departure_date->format('Ymd')
            );
        }

        /*
         * FechaCargaLugarOrigen no se informa acá.
         *
         * Es un dato opcional condicionado a una carga efectuada en un
         * lugar distinto del lugar de embarque. La app no tiene en este
         * punto un dato real que justifique calcular "salida - 2 horas".
         */

        // 10. CodigoLugarOrigen (opcional)
        $w->writeElement('CodigoLugarOrigen', $voyage->originPort?->code ?? 'ARBUE');

        // 11. CodigoPaisLugarOrigen (opcional)
        $w->writeElement('CodigoPaisLugarOrigen', $originCountryCode);

        // 12. CodigoPuertoDescarga (opcional)
        $w->writeElement('CodigoPuertoDescarga', $destinationPortCode);

        // 13. FechaDescarga (opcional)
        if ($voyage->estimated_arrival_date) {
            $w->writeElement('FechaDescarga', $voyage->estimated_arrival_date->format('Y-m-d\TH:i:s'));
        }

        // 14. Comentario (opcional)
        if ($voyage->special_instructions) {
            $w->writeElement('Comentario', substr($voyage->special_instructions, 0, 100));
        }

        // 15. CodigoAduana (opcional)
        $w->writeElement('CodigoAduana', $destinationPortCode);

        // 16. CodigoLugarOperativoDescarga (opcional)
        $w->writeElement('CodigoLugarOperativoDescarga', $voyage->destinationPort?->code ?? 'PYTVT');
    }

    /**
     * Agregar información de contenedores vacíos y de correo
     */
    /**
     * CORREGIDO según especificación AFIP exacta
     */
    private function addContainersInformation(\XMLWriter $w, Voyage $voyage): void
    {
        /*
         * ContenedoresVaciosCorreo sólo informa contenedores cuya condición
         * declarada sea:
         *
         *   V = vacío
         *   C = correo
         *
         * No crear contenedores ficticios cuando el viaje no tenga ninguno.
         */
        $containers = collect();

        try {
            if ($voyage->shipments()->count() > 0) {
                foreach ($voyage->shipments as $shipment) {
                    if ($shipment->billsOfLading()->count() > 0) {
                        foreach ($shipment->billsOfLading as $bol) {
                            if ($bol->shipmentItems()->count() > 0) {
                                foreach ($bol->shipmentItems as $item) {
                                    if ($item->containers()->count() > 0) {
                                        $containers = $containers->merge($item->containers);
                                    }
                                }
                            }
                        }
                    }
                }
            }
        } catch (Exception $e) {
            \Log::info('Error obteniendo contenedores: ' . $e->getMessage());
        }

        $containers = $containers
            ->filter(
                fn ($container) => in_array(
                    $container->condition,
                    ['V', 'C'],
                    true
                )
            )
            ->unique('container_number')
            ->values();

        // El bloque es opcional. Si no existen vacíos/correo reales,
        // no se transmite ningún contenedor inventado.
        if ($containers->isEmpty()) {
            return;
        }

        $w->startElement('ContenedoresVaciosCorreo');

        // Procesar únicamente contenedores vacíos/correo reales.
            // Procesar contenedores reales si existen
            foreach ($containers as $container) {
                $containerNumber = trim((string) $container->container_number);

                if ($containerNumber === '') {
                    throw new Exception(
                        'Existe un contenedor vacío sin identificador. '
                        . 'AFIP exige IdentificadorContenedor.'
                    );
                }

                /*
                 * AFIP exige las características según la tabla ISO.
                 * container_types.iso_size_type es el código ISO de
                 * tamaño/tipo de 4 caracteres (ej. 22G1).
                 *
                 * No usar el code interno 20GP/40HC como reemplazo.
                 */
                $isoSizeType = trim(
                    (string) ($container->containerType?->iso_size_type ?? '')
                );

                if ($isoSizeType === '') {
                    throw new Exception(
                        "Contenedor {$containerNumber}: falta el código ISO "
                        . 'de tamaño/tipo requerido por AFIP.'
                    );
                }

                $tara = $container->tare_weight_kg;

                if ($tara === null || (float) $tara <= 0) {
                    throw new Exception(
                        "Contenedor {$containerNumber}: falta la tara real "
                        . 'requerida por AFIP.'
                    );
                }

                $pesoBruto = $container->current_gross_weight_kg;

                if ($pesoBruto === null || (float) $pesoBruto <= 0) {
                    throw new Exception(
                        "Contenedor {$containerNumber}: falta el peso bruto real "
                        . 'requerido por AFIP.'
                    );
                }

                /*
                 * AFIP exige FechaVencimientoContenedor o ACEP.
                 *
                 * La app no tiene actualmente un campo ACEP identificado.
                 * Por eso, si tampoco existe expiry_date, se detiene el envío
                 * en lugar de fabricar una fecha a partir del viaje.
                 */
                if (empty($container->expiry_date)) {
                    throw new Exception(
                        "Contenedor {$containerNumber}: falta la fecha de "
                        . 'vencimiento requerida por AFIP y no hay ACEP informado.'
                    );
                }

                /*
                 * Los datos de descarga pertenecen al conocimiento.
                 *
                 * Un contenedor puede estar relacionado con más de un item y,
                 * por lo tanto, eventualmente con más de un conocimiento.
                 * Sólo se puede transmitir si todos los conocimientos asociados
                 * coinciden en los datos aduaneros de descarga.
                 */
                $bills = $container->shipmentItems()
                    ->with('billOfLading.dischargePort')
                    ->get()
                    ->pluck('billOfLading')
                    ->filter()
                    ->unique('id')
                    ->values();

                if ($bills->isEmpty()) {
                    throw new Exception(
                        "Contenedor {$containerNumber}: no tiene un conocimiento "
                        . 'asociado para obtener los datos de descarga AFIP.'
                    );
                }

                $customsCodes = $bills
                    ->pluck('discharge_customs_code')
                    ->filter(fn ($value) => trim((string) $value) !== '')
                    ->map(fn ($value) => trim((string) $value))
                    ->unique()
                    ->values();

                if ($customsCodes->count() !== 1) {
                    throw new Exception(
                        "Contenedor {$containerNumber}: falta o es inconsistente "
                        . 'el código de aduana de descarga AFIP.'
                    );
                }

                $operativeCodes = $bills
                    ->pluck('operational_discharge_code')
                    ->filter(fn ($value) => trim((string) $value) !== '')
                    ->map(fn ($value) => trim((string) $value))
                    ->unique()
                    ->values();

                if ($operativeCodes->count() !== 1) {
                    throw new Exception(
                        "Contenedor {$containerNumber}: falta o es inconsistente "
                        . 'el código de lugar operativo de descarga AFIP.'
                    );
                }

                $dischargePortCodes = $bills
                    ->map(fn ($bill) => trim(
                        (string) ($bill->dischargePort?->code ?? '')
                    ))
                    ->filter()
                    ->unique()
                    ->values();

                if ($dischargePortCodes->count() !== 1) {
                    throw new Exception(
                        "Contenedor {$containerNumber}: falta o es inconsistente "
                        . 'el puerto de descarga.'
                    );
                }

                $dischargeDates = $bills
                    ->map(function ($bill) {
                        if (empty($bill->discharge_date)) {
                            return null;
                        }

                        return $bill->discharge_date->format('Ymd');
                    })
                    ->filter()
                    ->unique()
                    ->values();

                if ($dischargeDates->count() !== 1) {
                    throw new Exception(
                        "Contenedor {$containerNumber}: falta o es inconsistente "
                        . 'la fecha de descarga.'
                    );
                }

                $w->startElement('Contenedor');

                    // Campos obligatorios, en el orden de la especificación.
                    $w->writeElement(
                        'CaracteristicasContenedor',
                        substr($isoSizeType, 0, 4)
                    );

                    $w->writeElement(
                        'IdentificadorContenedor',
                        substr($containerNumber, 0, 20)
                    );

                    $w->writeElement(
                        'CondicionContenedor',
                        (string) $container->condition
                    );

                    $w->writeElement(
                        'Tara',
                        number_format((float) $tara, 2, '.', '')
                    );

                    $w->writeElement(
                        'PesoBruto',
                        number_format((float) $pesoBruto, 3, '.', '')
                    );

                    // El precinto de origen es optativo.
                    // Sólo SH/shipper_seal identifica inequívocamente al cargador.
                    if (!empty($container->shipper_seal)) {
                        $w->writeElement(
                            'NumeroPrecintoOrigen',
                            substr((string) $container->shipper_seal, 0, 35)
                        );
                    }

                    // AFIP requiere AAAAMM para este campo.
                    $w->writeElement(
                        'FechaVencimientoContenedor',
                        \Carbon\Carbon::parse($container->expiry_date)->format('Ym')
                    );

                    /*
                     * Para condición V, AFIP exige puerto y fecha de descarga.
                     * Aduana y lugar operativo son obligatorios en RegistrarViaje.
                     *
                     * Todos provienen de los conocimientos asociados; no hay
                     * códigos ni fechas por defecto.
                     */
                    $w->writeElement(
                        'CodigoPuertoDescarga',
                        substr($dischargePortCodes->first(), 0, 5)
                    );

                    $w->writeElement(
                        'FechaDescarga',
                        $dischargeDates->first()
                    );

                    $w->writeElement(
                        'CodigoAduana',
                        substr($customsCodes->first(), 0, 3)
                    );

                    $w->writeElement(
                        'CodigoLugarOperativoDescarga',
                        substr($operativeCodes->first(), 0, 5)
                    );

                $w->endElement(); // Contenedor
            }

        $w->endElement(); // ContenedoresVaciosCorreo
    }

    /**
     * Validar datos obligatorios del Viaje
     */
    private function validateVoyageData(Voyage $voyage): void
    {
        if (!$voyage->voyage_number) {
            throw new Exception('Viaje debe tener número de viaje definido');
        }

        if (!$voyage->lead_vessel_id || !$voyage->leadVessel) {
            throw new Exception('Viaje debe tener embarcación líder definida');
        }

        if (!$voyage->origin_port_id || !$voyage->originPort) {
            throw new Exception('Viaje debe tener puerto de origen definido');
        }

        if (!$voyage->destination_port_id || !$voyage->destinationPort) {
            throw new Exception('Viaje debe tener puerto de destino definido');
        }

        if (!$voyage->departure_date) {
            throw new Exception('Viaje debe tener fecha de salida definida');
        }
    }

   private function getCountryCode(string $alpha2Code): string
    {
        // Usar datos reales del modelo Country
        $country = \App\Models\Country::where('alpha2_code', strtoupper($alpha2Code))->first();
        
        if ($country) {
            // Si tiene customs_code específico, usarlo
            if ($country->customs_code) {
                return $country->customs_code;
            }
            
            // Si tiene numeric_code, usarlo
            if ($country->numeric_code) {
                return str_pad($country->numeric_code, 3, '0', STR_PAD_LEFT);
            }
        }
        
        // Fallbacks seguros basados en códigos ISO estándar
        return match(strtoupper($alpha2Code)) {
            'AR' => '032', // Argentina
            'PY' => '600', // Paraguay
            'BR' => '076', // Brasil
            'UY' => '858', // Uruguay
            default => '032' // Argentina por defecto
        };
    }

   /*  private function getPortCustomsCode(string $portCode): string
    {
        // Usar datos reales del modelo Port
        $port = \App\Models\Port::where('code', strtoupper($portCode))->first();
        
        if ($port && $port->afip_code) {
            return $port->afip_code;
        }
        
        // Fallbacks seguros para puertos conocidos de la hidrovía
        // CORREGIDO según información de Roberto Benbassat
        return match(strtoupper($portCode)) {
            'ARBUE' => '033', // Buenos Aires (CORREGIDO: era 001)
            'ARLPG' => '001', // La Plata (CORREGIDO: era 033)
            'ARPAR' => '041', // Paraná
            'ARSFE' => '062', // Santa Fe
            'ARROS' => '052', // Rosario
            'ARSLA' => '057', // San Lorenzo
            'PYASU' => '001', // Asunción (Paraguay)
            'PYTVT' => '001', // Villeta (Paraguay - misma aduana Asunción)
            'PYCON' => '002', // Concepción (Paraguay)
            'PYPIL' => '003', // Pilar (Paraguay)
            default => '033'  // Buenos Aires por defecto (CORREGIDO)
        };
    } */
    private function getPortCustomsCode(string $portCode): string
    {
        // PRIORIDAD: Mapeo hardcodeado para puertos conocidos de la hidrovía
        // Según confirmación de Roberto Benbassat y Luciano de AFIP
        $portCode = strtoupper($portCode);
        
        $hidrovia = match($portCode) {
            'ARBUE' => '033', // Buenos Aires → Aduana La Plata
            'ARLPG' => '033', // La Plata → Aduana La Plata
            'ARPAR' => '041', // Paraná
            'ARSFE' => '062', // Santa Fe
            'ARROS' => '052', // Rosario
            'ARSLA' => '057', // San Lorenzo
            'PYASU' => '001', // Asunción (Paraguay)
            'PYTVT' => '001', // Villeta (Paraguay)
            'PYCON' => '002', // Concepción (Paraguay)
            'PYPIL' => '003', // Pilar (Paraguay)
            default => null
        };
        
        if ($hidrovia !== null) {
            return $hidrovia;
        }
        
        // Fallback: buscar en BD para otros puertos
        $port = \App\Models\Port::where('code', $portCode)->first();
        
        if ($port && $port->afip_code) {
            return $port->afip_code;
        }
        
        // Default
        return '033';
    }

    /**
     * Genera el XML del método RegistrarDesconsolidado (AFIP)
     * usando BillOfLading madre/hijos del Voyage.
     */
    public function generateDeconsolidatedXml(Voyage $voyage): string
    {
        try {
            // Master BL del viaje
            $master = $voyage->billsOfLading()
                ->where('is_master_bill', true)
                ->first();

            if (!$master) {
                throw new \Exception('No se encontró Conocimiento Madre (is_master_bill = true).');
            }

            // House BLs del master
            $houses = $voyage->billsOfLading()
                ->where('is_house_bill', true)
                ->where('master_bill_number', $master->bill_number)
                ->get();

            if ($houses->isEmpty()) {
                throw new \Exception('No se encontraron Conocimientos Hijo asociados al master BL.');
            }

            // Crear raíz
            $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><Desconsolidado></Desconsolidado>');

            // Cabecera — tomar identificador de AFIP si existe, sino id local
            $cabecera = $xml->addChild('Cabecera');
            $cabecera->addChild('IdentificadorViaje', htmlspecialchars((string)($voyage->argentina_voyage_id ?? $voyage->id)));
            // Para AFIP: identificador del título madre; usamos bill_number del master
            $cabecera->addChild('IdentificadorTituloMadre', htmlspecialchars((string)$master->bill_number));
            // Fecha operación (hoy) y puerto (usamos destino del viaje si está)
            $cabecera->addChild('FechaOperacion', now()->format('Y-m-d'));

            // Puerto: preferimos destinationPort->code si está cargado; fallback a nombre
            $puerto = $voyage->destinationPort?->code
                ?? $voyage->destinationPort?->name
                ?? $master->dischargePort?->code
                ?? $master->dischargePort?->name
                ?? '';
            $cabecera->addChild('Puerto', htmlspecialchars((string)$puerto));

            // Titulos hijos
            $lista = $xml->addChild('TitulosHijos');

            foreach ($houses as $h) {
                $titulo = $lista->addChild('TituloHijo');

                // Identificador del hijo: usamos su bill_number
                $titulo->addChild('IdentificadorTituloHijo', htmlspecialchars((string)$h->bill_number));

                // BL (house BL si existe, sino el mismo bill_number)
                $bl = $h->house_bill_number ?: $h->bill_number;
                $titulo->addChild('BL', htmlspecialchars((string)$bl));

                // Pesos y bultos — campos reales de tu migración
                // gross_weight_kg (decimal), total_packages (int)
                $peso   = number_format((float)($h->gross_weight_kg ?? 0), 2, '.', '');
                $bultos = (int)($h->total_packages ?? 0);
                $titulo->addChild('PesoBruto', $peso);
                $titulo->addChild('CantidadBultos', $bultos);

                // Tipo de bulto — intentar desde relación de embalaje principal, si no, unidad
                $tipoBulto =
                    $h->primaryPackagingType?->code
                    ?? $h->primaryPackagingType?->name
                    ?? $h->measurement_unit
                    ?? '';
                $titulo->addChild('TipoBulto', htmlspecialchars((string)$tipoBulto));

                // Consignatario — desde relación consignee (Client->name)
                $consignatario = $h->consignee?->name ?? '';
                $titulo->addChild('Consignatario', htmlspecialchars((string)$consignatario));

                // País destino — intentar por dischargePort->country->code o finalDestinationPort
                $paisDestino =
                    $h->dischargePort?->country?->code
                    ?? $h->finalDestinationPort?->country?->code
                    ?? '';
                $titulo->addChild('PaisDestino', htmlspecialchars((string)$paisDestino));
            }

            // Formatear bonito
            $dom = new \DOMDocument('1.0', 'UTF-8');
            $dom->preserveWhiteSpace = false;
            $dom->formatOutput = true;
            $dom->loadXML($xml->asXML());

            return $dom->saveXML();

        } catch (\Exception $e) {
            Log::error('Error al generar XML de Desconsolidado: '.$e->getMessage());
            throw $e;
        }
    }

    // ========================================
    // HELPER METHODS FOR XML GENERATION
    // ========================================

    /**
     * Limpia y valida números (CUIT, etc)
     */
    private function cleanNumeric(?string $value): string
    {
        if (empty($value)) {
            return '';
        }
        
        // Remover todo excepto dígitos
        return preg_replace('/[^0-9]/', '', $value);
    }

    /**
     * Limpia strings para XML (remueve caracteres especiales)
     */
    private function cleanString(?string $value, ?int $maxLength = null): string
    {
        if (empty($value)) {
            return '';
        }
        
        // Remover caracteres especiales y trim
        $cleaned = trim($value);
        
        // Escapar para XML
        $cleaned = htmlspecialchars($cleaned, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        
        // Limitar longitud si se especifica
        if ($maxLength && strlen($cleaned) > $maxLength) {
            $cleaned = substr($cleaned, 0, $maxLength);
        }
        
        return $cleaned;
    }

    /**
     * Formatea fechas para AFIP (yyyy-mm-ddThh:mi:ss)
     */
    private function formatDateTime($date): string
    {
        if (empty($date)) {
            return now()->format('Y-m-d\TH:i:s');
        }
        
        if ($date instanceof \Carbon\Carbon) {
            return $date->format('Y-m-d\TH:i:s');
        }
        
        if (is_string($date)) {
            try {
                return \Carbon\Carbon::parse($date)->format('Y-m-d\TH:i:s');
            } catch (\Exception $e) {
                return now()->format('Y-m-d\TH:i:s');
            }
        }
        
        return now()->format('Y-m-d\TH:i:s');
    }


}