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
                    $emptyContainers = collect();

                    foreach ($billsOfLading as $bol) {
                        // Códigos AFIP desde el BL (prioridad) o fallback a voyage
                        //$bolCodAduOrigen = $bol->origin_customs_code ?: $codAduOrigen;
                        $bolCodAduOrigen = $codAduOrigen; // usar el mapeo del puerto (getPortCustomsCode) haste que se aclare el problema de los codigos
                        $bolCodLugOperOrigen = $bol->origin_operative_code ?: $codLugOperOrigen;
                        $bolCodAduDest = str_pad($bol->discharge_customs_code ?: $codAduDest, 3, '0', STR_PAD_LEFT);
                        $bolCodLugOperDest = str_pad($bol->operational_discharge_code ?: $codLugOperDest, 3, '0', STR_PAD_LEFT);
                        
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
                                                    if ($container->condition !== 'V') {
                                                        $allContainers->push($container);
                                                    } else {
                                                        $emptyContainers->push($container);
                                                    }
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
                    if ($emptyContainers->isNotEmpty()) {
                        $w->startElement('titulosTransContVacios');
                            $w->startElement('TitTransContVacio');
                                $w->writeElement('codViaTrans', '8');
                                $w->writeElement('idTitTrans', 'VACIOS-' . $transactionId);
                                
                                $w->startElement('idContenedores');
                                foreach ($emptyContainers as $ec) {
                                    $w->writeElement('idCont', $ec->container_number);
                                }
                                $w->endElement();
                                
                                // Remitente simplificado para vacíos
                                $firstBol = $billsOfLading->first();
                                $this->writeRemitente($w, $firstBol);
                                $this->writeConsignatario($w, $firstBol);
                                $this->writeDestinatario($w, $firstBol);

                                // Códigos AFIP desde el primer BL
                                $vaciosCodAduOrigen = $firstBol->origin_customs_code ?: $codAduOrigen;
                                $vaciosCodLugOperOrigen = $firstBol->origin_operative_code ?: $codLugOperOrigen;
                                $vaciosCodAduDest = str_pad($firstBol->discharge_customs_code ?: $codAduDest, 3, '0', STR_PAD_LEFT);
                                $vaciosCodLugOperDest = str_pad($firstBol->operational_discharge_code ?: $codLugOperDest, 3, '0', STR_PAD_LEFT);

                                $w->startElement('origen');
                                    $w->writeElement('codAdu', $vaciosCodAduOrigen);
                                    $w->writeElement('codLugOper', $vaciosCodLugOperOrigen);
                                    $w->writeElement('codCiu', $codCiuOrigen);
                                $w->endElement();

                                $w->startElement('destino');
                                    $w->writeElement('codPais', $codPaisDest);
                                    $w->writeElement('codAdu', $vaciosCodAduDest);
                                    $w->writeElement('codLugOper', $vaciosCodLugOperDest);
                                    $w->writeElement('codCiu', $codCiuDest);
                                $w->endElement();
                                
                                $w->writeElement('idFiscalATAMIC', preg_replace('/[^0-9]/', '', $this->company->tax_id));
                            $w->endElement(); // TitTransContVacio
                        $w->endElement(); // titulosTransContVacios
                    }

                    // === CONTENEDORES (todos, llenos y vacíos) ===
                    $allContainersUnique = $allContainers->merge($emptyContainers)->unique('container_number');
                    
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
            $billsOfLading = $shipment->billsOfLading()
                ->with(['shipmentItems.containers', 'shipmentItems.packagingType'])
                ->get();

            if ($billsOfLading->isEmpty()) {
                throw new Exception("Shipment {$shipment->id} no tiene Bills of Lading para generar envíos.");
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
                        
                        // Validar campo obligatorio id_decla
                        if (empty($bol->permiso_embarque)) {
                            throw new \Exception("BL {$bol->bill_number} no tiene Permiso de Embarque. Campo obligatorio para AFIP.");
                        }

                        $w->startElement('Envio');

                            // === DESTINACIONES ===
                            $w->startElement('destinaciones');
                                $w->startElement('Destinacion');
                                    
                                    // idDecla - Obligatorio C(16)
                                    $w->writeElement('idDecla', $bol->id_decla);
                                    
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
                // Para países extranjeros (no AR), usar 001 según tabla AFIP exterior
                if (strtoupper($codPais) !== 'AR') {
                    $w->writeElement('codLugOper', '001');
                } else {
                    // Buscar lugar operativo vinculado al puerto
                    $operativeLocation = \App\Models\AfipOperativeLocation::where('port_id', $port->id)
                        ->where('is_active', true)
                        ->first();
                    $codLugOper = $operativeLocation?->location_code ?? '001';
                    $w->writeElement('codLugOper', $codLugOper);
                }
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
        $environment = $this->config['environment'] ?? 'testing';
        $wsdlUrl = $environment === 'production'
            ? 'https://wsaa.afip.gov.ar/ws/services/LoginCms?wsdl'
            : 'https://wsaahomo.afip.gov.ar/ws/services/LoginCms?wsdl';
        
        $client = new \SoapClient($wsdlUrl, [
            'trace' => true,
            'exceptions' => true,
            'stream_context' => stream_context_create([
                'ssl' => [
                    'verify_peer' => true,
                    'verify_peer_name' => true,
                    'allow_self_signed' => false,
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
                    $w->writeElement('Token', $this->iaRequired($wsaa['token'] ?? null, 'Token WSAA'));
                    $w->writeElement('Sign', $this->iaRequired($wsaa['sign'] ?? null, 'Sign WSAA'));
                    $w->writeElement('CuitEmpresaConectada', $this->iaNumeric($this->company->tax_id, 'CuitEmpresaConectada', 11));
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
    public function createRegistrarViajeXml(Voyage $voyage, string $transactionId, array $voyageData = []): string
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
                    $w->writeElement('Token', $this->iaRequired($wsaa['token'] ?? null, 'Token WSAA'));
                    $w->writeElement('Sign', $this->iaRequired($wsaa['sign'] ?? null, 'Sign WSAA'));
                    $w->writeElement('CuitEmpresaConectada', $this->iaNumeric($this->company->tax_id, 'CuitEmpresaConectada', 11));
                    $w->writeElement('TipoAgente', 'TRSP');
                    $w->writeElement('Rol', 'TRSP');
                $w->endElement();

                // Parámetros RegistrarViaje
                $w->startElement('argRegistrarViaje');
                    $w->writeElement('IdTransaccion', $this->requireIaTransactionId($transactionId));

                    // Información Anticipada Marítima (estructura principal)
                    $w->startElement('InformacionAnticipadaMaritimaDoc');
                        $this->addVoyageInformation($w, $voyage, $voyageData);
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
                $w->startElement('RectificarViaje');
                $w->writeAttribute('xmlns', self::AFIP_ANTICIPADA_NAMESPACE);

                // Autenticación empresa (obligatorio)
                $w->startElement('argWSAutenticacionEmpresa');
                    $w->writeElement('Token', $this->iaRequired($wsaa['token'] ?? null, 'Token WSAA'));
                    $w->writeElement('Sign', $this->iaRequired($wsaa['sign'] ?? null, 'Sign WSAA'));
                    $w->writeElement('CuitEmpresaConectada', $this->iaNumeric($this->company->tax_id, 'CuitEmpresaConectada', 11));
                    $w->writeElement('TipoAgente', 'TRSP');
                    $w->writeElement('Rol', 'TRSP');
                $w->endElement();

                // Parámetros RectificarViaje
                $w->startElement('argRectificarViaje');
                    $w->writeElement('IdTransaccion', $this->requireIaTransactionId($transactionId));

                    // Información Anticipada Marítima (estructura principal)
                    $w->startElement('InformacionAnticipadaMaritimaDoc');
                        // Identificador del viaje original (obligatorio para rectificación)
                        $w->writeElement('IdentificadorViaje', $this->iaRequired($rectificationData['original_external_reference'], 'IdentificadorViaje', 16));
                        
                        $this->addVoyageInformation($w, $voyage, $rectificationData);
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
        $this->validateVoyageData($voyage);

        $voyage->loadMissing([
            'shipments.billsOfLading.consignee',
            'shipments.billsOfLading.notifyParty',
            'shipments.billsOfLading.loadingPort.country',
            'shipments.billsOfLading.dischargePort.country',
            'shipments.billsOfLading.transshipmentPort.country',
            'shipments.billsOfLading.shipmentItems.packagingType',
            'shipments.billsOfLading.shipmentItems.cargoType',
            'shipments.billsOfLading.shipmentItems.containers.containerType',
        ]);

        $identifier = $this->iaRequired(
            $voyage->argentina_voyage_id,
            'IdentificadorViaje',
            16
        );

        $bills = $voyage->shipments
            ->flatMap(fn ($shipment) => $shipment->billsOfLading)
            ->filter(fn ($bill) => strtoupper(trim((string) $bill->dischargePort?->country?->alpha2_code)) === 'AR')
            ->filter(fn ($bill) => $this->iaBillHasManifestedCargo($bill))
            ->values();

        if ($bills->isEmpty()) {
            throw new Exception('Información Anticipada: no hay conocimientos con descarga en Argentina para RegistrarTitulosCbc.');
        }

        $wsaa = $this->getWSAATokens('wgesinformacionanticipada');

        $w = new \XMLWriter();
        $w->openMemory();
        $w->startDocument('1.0', 'UTF-8');
        $w->startElementNs('soap', 'Envelope', 'http://schemas.xmlsoap.org/soap/envelope/');
        $w->writeAttribute('xmlns:ar', self::AFIP_ANTICIPADA_NAMESPACE);
        $w->startElementNs('soap', 'Body', null);
        $w->startElement('ar:RegistrarTitulosCbc');

        $w->startElement('ar:argWSAutenticacionEmpresa');
        $w->writeElement('ar:Token', $wsaa['token']);
        $w->writeElement('ar:Sign', $wsaa['sign']);
        $w->writeElement('ar:CuitEmpresaConectada', $this->iaNumeric($this->company->tax_id, 'CuitEmpresaConectada', 11));
        $w->writeElement('ar:TipoAgente', 'TRSP');
        $w->writeElement('ar:Rol', 'TRSP');
        $w->endElement();

        $w->startElement('ar:argRegistrarTitulosCBC');
        $w->writeElement('ar:IdTransaccion', $this->requireIaTransactionId($transactionId));
        $w->startElement('ar:InformacionTitulosDoc');
        $w->writeElement('ar:IdentificadorViaje', $identifier);
        $w->startElement('ar:Titulos');

        foreach ($bills as $bill) {
            $this->writeIaTitle($w, $bill, false);
        }

        $w->endElement(); // Titulos
        $this->addContainersInformation($w, $voyage, 'ar:');
        $w->endElement(); // InformacionTitulosDoc
        $w->endElement(); // argRegistrarTitulosCBC
        $w->endElement(); // RegistrarTitulosCbc
        $w->endElement(); // Body
        $w->endElement(); // Envelope
        $w->endDocument();

        return $w->outputMemory();
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
    public function generateCerrarViajeXml(
        Voyage $voyage,
        Company $company,
        ?string $transactionId = null
    ): string {
        $identifier = $this->iaRequired(
            $voyage->argentina_voyage_id,
            'IdentificadorViaje',
            16
        );

        $bills = $voyage->billsOfLading()
            ->with([
                'consignee',
                'notifyParty',
                'loadingPort.country',
                'dischargePort.country',
                'transshipmentPort.country',
                'shipmentItems.cargoType',
                'shipmentItems.packagingType',
                'shipmentItems.containers.containerType',
            ])
            ->whereHas('dischargePort.country', function ($query) {
                $query->where('alpha2_code', '!=', 'AR');
            })
            ->get();

        if ($bills->isEmpty()) {
            throw new Exception(
                'Información Anticipada: no hay conocimientos con descarga fuera de Argentina para CerrarViaje.'
            );
        }

        $transactionId ??= 'CV'
            . now()->format('ymdHis')
            . str_pad((string) ($voyage->id % 1000000), 6, '0', STR_PAD_LEFT);
        $transactionId = $this->requireIaTransactionId($transactionId);

        $wsaa = $this->getWSAATokens('wgesinformacionanticipada');

        $w = new \XMLWriter();
        $w->openMemory();
        $w->startDocument('1.0', 'UTF-8');
        $w->startElementNs('soap', 'Envelope', 'http://schemas.xmlsoap.org/soap/envelope/');
        $w->writeAttribute('xmlns:ar', self::AFIP_ANTICIPADA_NAMESPACE);
        $w->startElementNs('soap', 'Body', null);
        $w->startElement('ar:CerrarViaje');

        $w->startElement('ar:argWSAutenticacionEmpresa');
        $w->writeElement('ar:Token', $this->iaRequired($wsaa['token'] ?? null, 'Token WSAA'));
        $w->writeElement('ar:Sign', $this->iaRequired($wsaa['sign'] ?? null, 'Sign WSAA'));
        $w->writeElement(
            'ar:CuitEmpresaConectada',
            $this->iaNumeric($company->tax_id, 'CuitEmpresaConectada', 11)
        );
        $w->writeElement('ar:TipoAgente', 'TRSP');
        $w->writeElement('ar:Rol', 'TRSP');
        $w->endElement();

        $w->startElement('ar:argCerrarViaje');
        $w->writeElement('ar:IdTransaccion', $transactionId);
        $w->startElement('ar:InformacionTitulosCierreDoc');
        $w->writeElement('ar:IdentificadorViaje', $identifier);
        $w->startElement('ar:Titulos');

        foreach ($bills as $bill) {
            $this->writeIaTitle($w, $bill, true);
        }

        $w->endElement();
        $w->endElement();
        $w->endElement();
        $w->endElement();
        $w->endElement();
        $w->endElement();
        $w->endDocument();

        return $w->outputMemory();
    }

    /**
     * Agregar información del viaje al XML
     */
    private function addVoyageInformation(\XMLWriter $w, Voyage $voyage, array $voyageData = []): void
    {
        $voyage->loadMissing([
            'leadVessel.flagCountry',
            'leadVessel.owner.country',
            'captain.documentCountry',
            'originPort.country',
            'destinationPort.country',
            'originPort.primaryCustomsOffice',
            'destinationPort.primaryCustomsOffice',
            'originCustoms',
            'destinationCustoms',
        ]);

        $vessel = $voyage->leadVessel;
        $vesselId = $vessel?->name ?: $vessel?->registration_number;
        $vesselOwner = $vessel?->owner;

        if (!$vesselOwner) {
            throw new Exception('Información Anticipada: la embarcación no tiene propietario asociado.');
        }

        $w->writeElement('IdentificadorMedioTransporte', $this->iaRequired($vesselId, 'IdentificadorMedioTransporte', 40));
        $w->writeElement('CodigoPaisProcedencia', $this->iaCountry($voyage->originPort?->country, 'CodigoPaisProcedencia'));
        $w->writeElement('CodigoPuertoOrigen', $this->iaPort($voyage->originPort, 'CodigoPuertoOrigen'));

        if ($voyage->destinationPort?->country) {
            $w->writeElement('CodigoPaisFinViaje', $this->iaCountry($voyage->destinationPort->country, 'CodigoPaisFinViaje'));
        }

        $direction = $this->iaImportExportIndicator($voyage);
        if ($direction !== null) {
            $w->writeElement('IndicadorImportacionExportacion', $direction);
        }

        if ($voyage->departure_date) {
            $w->writeElement('FechaInicioViaje', $this->iaDate($voyage->departure_date));
        }
        if (!$voyage->estimated_arrival_date) {
            throw new Exception('Información Anticipada: FechaArribo es obligatoria.');
        }

        $w->writeElement('FechaArribo', $this->iaDate($voyage->estimated_arrival_date));
        $w->writeElement('IndicadorTransporteVacio', $this->iaYesNoRequired($voyage->is_empty_transport, 'IndicadorTransporteVacio'));
        $w->writeElement('IndicadorMercaderiaAbordo', $this->iaYesNoRequired($voyage->has_cargo_onboard, 'IndicadorMercaderiaAbordo'));
        $w->writeElement('DesignacionTransportista', $this->iaRequired($vesselOwner->commercial_name ?: $vesselOwner->legal_name, 'DesignacionTransportista', 35));
        $w->writeElement('CodigoPaisTransportista', $this->iaCountry($vesselOwner->country, 'CodigoPaisTransportista'));
        $w->writeElement('CodigoNacionalidadMediodeTransporte', $this->iaCountry($vessel?->flagCountry, 'CodigoNacionalidadMediodeTransporte'));

        $operative = $this->iaVoyageOperativeLocation($voyage);
        if ($operative) {
            $w->writeElement('CodigoLugarOperativo', $operative);
        }
        $w->writeElement('CodigoAduana', $this->iaVoyageCustomsCode($voyage));

        $captain = $voyage->captain;
        if ($captain) {
            $name = trim((string) ($captain->full_name ?: trim((string) $captain->first_name . ' ' . (string) $captain->last_name)));
            if ($name !== '') {
                $w->writeElement('NombreCapitanBuque', $this->iaRequired($name, 'NombreCapitanBuque', 70));
            }
            if ($captain->document_type) {
                $w->writeElement('TipoIdentificadorCapitan', $this->iaRequired(strtoupper(trim($captain->document_type)), 'TipoIdentificadorCapitan', 4));
            }
            if ($captain->document_number) {
                $w->writeElement('NumeroIdentificadorCapitan', $this->iaRequired(trim($captain->document_number), 'NumeroIdentificadorCapitan', 35));
            }
            if ($captain->documentCountry) {
                $w->writeElement('CodigoPaisEmisorIdentificadorCapitan', $this->iaCountry($captain->documentCountry, 'CodigoPaisEmisorIdentificadorCapitan'));
            }
        }

        if ($voyage->special_instructions) {
            $w->writeElement('Comentario', $this->iaRequired($voyage->special_instructions, 'Comentario', 60));
        }

        $isEmptyTransport = $this->iaYesNoRequired(
            $voyage->is_empty_transport,
            'IndicadorTransporteVacio'
        );
        $hasCargoOnboard = $this->iaYesNoRequired(
            $voyage->has_cargo_onboard,
            'IndicadorMercaderiaAbordo'
        );
        $ataCbcTaxIds = $this->iaAtaCbcTaxIds(
            $voyageData['ata_cbc_cuits'] ?? []
        );

        if ($isEmptyTransport === 'N' && $hasCargoOnboard === 'S' && empty($ataCbcTaxIds)) {
            throw new Exception(
                'Información Anticipada: el viaje con mercadería a bordo requiere informar al menos un CUIT de ATA CBC.'
            );
        }

        if ($isEmptyTransport === 'S' && !empty($ataCbcTaxIds)) {
            throw new Exception(
                'Información Anticipada: no corresponde informar ATA CBC para un transporte en lastre.'
            );
        }

        if (!empty($ataCbcTaxIds)) {
            $w->startElement('AtaCbcViaje');
            foreach ($ataCbcTaxIds as $taxId) {
                $w->startElement('AtaCbc');
                $w->writeElement('CuitAtaCbc', $taxId);
                $w->endElement();
            }
            $w->endElement();
        }
    }

    private function addContainersInformation(\XMLWriter $w, Voyage $voyage, string $prefix = ''): void
    {
        $voyage->loadMissing([
            'shipments.billsOfLading.loadingPort',
            'shipments.billsOfLading.dischargePort',
            'shipments.billsOfLading.shipmentItems.containers.containerType',
        ]);

        $entries = collect();

        foreach ($voyage->shipments as $shipment) {
            foreach ($shipment->billsOfLading as $bill) {
                foreach ($bill->shipmentItems as $item) {
                    foreach ($item->containers as $container) {
                        $condition = $this->iaContainerCondition($container, $item);
                        if (!in_array($condition, ['V', 'C'], true)) {
                            continue;
                        }
                        $entries->put($container->container_number, compact('container', 'bill', 'item'));
                    }
                }
            }
        }

        if ($entries->isEmpty()) {
            return;
        }

        $w->startElement($prefix . 'ContenedoresVaciosCorreo');
        foreach ($entries as $entry) {
            $this->writeIaContainer($w, $entry['container'], $entry['bill'], $entry['item'], $prefix);
        }
        $w->endElement();
    }

    private function writeIaContainer(\XMLWriter $w, $container, BillOfLading $bill, $item, string $prefix = ''): void
    {
        $number = $this->iaRequired($container->container_number, $prefix . 'IdentificadorContenedor', 20);
        $type = $container->containerType?->iso_code ?: $container->containerType?->code;
        $type = $this->iaRequired($type, "Contenedor {$number}: CaracteristicasContenedor", 4);
        if (mb_strlen($type) !== 4) {
            throw new Exception("Contenedor {$number}: CaracteristicasContenedor debe tener 4 caracteres.");
        }

        $condition = $this->iaContainerCondition($container, $item);
        if (!in_array($condition, ['V', 'C'], true)) {
            throw new Exception("Contenedor {$number}: RegistrarViaje sólo admite contenedores vacíos o de correo.");
        }

        $tare = $container->tare_weight_kg;
        $gross = $container->current_gross_weight_kg ?? $item?->pivot?->gross_weight_kg;
        if ($tare === null || !is_numeric($tare)) {
            throw new Exception("Contenedor {$number}: Tara es obligatoria.");
        }
        if ($gross === null || !is_numeric($gross)) {
            throw new Exception("Contenedor {$number}: PesoBruto es obligatorio.");
        }
        if ((float) $tare > (float) $gross) {
            throw new Exception("Contenedor {$number}: Tara no puede superar PesoBruto.");
        }

        $w->startElement($prefix . 'Contenedor');
        $w->writeElement($prefix . 'CaracteristicasContenedor', $type);
        $w->writeElement($prefix . 'IdentificadorContenedor', $number);
        $w->writeElement($prefix . 'CondicionContenedor', $condition);
        $w->writeElement($prefix . 'Tara', $this->iaIntegerWeight($tare, "Contenedor {$number}: Tara", 10));
        $w->writeElement($prefix . 'PesoBruto', $this->iaIntegerWeight($gross, "Contenedor {$number}: PesoBruto", 14));

        $seal = trim((string) ($container->customs_seal ?: $container->shipper_seal ?: $container->carrier_seal));
        if ($seal !== '') {
            $w->writeElement($prefix . 'NumeroPrecintoOrigen', $this->iaRequired($seal, $prefix . 'NumeroPrecintoOrigen', 35));
        }

        $expiry = $container->expiry_date ?: $container->csc_expiry_date;
        $acep = data_get($container->webservice_data, 'acep');
        if ($expiry && $acep) {
            throw new Exception(
                "Contenedor {$number}: no se pueden informar simultáneamente FechaVencimientoContenedor y ACEP."
            );
        }
        if ($expiry) {
            $w->writeElement($prefix . 'FechaVencimientoContenedor', $this->iaDate($expiry));
        }
        if ($acep) {
            $w->writeElement($prefix . 'Acep', $this->iaRequired($acep, 'ACEP', 20));
        }

        if ($condition === 'V') {
            $w->writeElement($prefix . 'CodigoPuertoEmbarque', $this->iaPort($bill->loadingPort, $prefix . 'CodigoPuertoEmbarque'));
            if ($bill->loading_date) {
                $w->writeElement($prefix . 'FechaEmbarque', $this->iaDate($bill->loading_date));
            }
            $w->writeElement($prefix . 'CodigoPuertoDescarga', $this->iaPort($bill->dischargePort, $prefix . 'CodigoPuertoDescarga'));
            if ($bill->discharge_date) {
                $w->writeElement($prefix . 'FechaDescarga', $this->iaDate($bill->discharge_date));
            }
        }

        $operativeCode = $bill->operational_discharge_code ?: $item?->operational_discharge_code;
        $operativeCode = $this->iaOperativeCode(
            $operativeCode,
            "Contenedor {$number}: CodigoLugarOperativoDescarga"
        );
        $w->writeElement(
            $prefix . 'CodigoAduana',
            $this->iaCustomsFromOperativeCode(
                $operativeCode,
                "Contenedor {$number}: CodigoAduana"
            )
        );
        $w->writeElement($prefix . 'CodigoLugarOperativoDescarga', $operativeCode);

        $w->endElement();
    }

    private function iaRequired($value, string $label, ?int $maxLength = null): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            throw new Exception("Información Anticipada: {$label} es obligatorio.");
        }
        if ($maxLength !== null && mb_strlen($value) > $maxLength) {
            throw new Exception("Información Anticipada: {$label} supera {$maxLength} caracteres.");
        }
        return $value;
    }

    private function iaYesNoRequired($value, string $label): string
    {
        $value = strtoupper(trim((string) $value));
        if (!in_array($value, ['S', 'N'], true)) {
            throw new Exception("Información Anticipada: {$label} debe ser S o N.");
        }
        return $value;
    }

    private function iaAtaCbcTaxIds($value): array
    {
        $values = is_array($value)
            ? $value
            : preg_split('/[,;\\n]+/', (string) $value);

        $taxIds = [];
        foreach ($values as $item) {
            $digits = preg_replace('/\\D+/', '', (string) $item);
            if ($digits === '') {
                continue;
            }
            if (strlen($digits) !== 11) {
                throw new Exception(
                    'Información Anticipada: cada CUIT de ATA CBC debe contener 11 dígitos.'
                );
            }
            $taxIds[$digits] = $digits;
        }

        return array_values($taxIds);
    }

    private function iaCountry($country, string $label): string
    {
        if (!$country) {
            throw new Exception("Información Anticipada: {$label} no está configurado.");
        }

        $afipCode = trim((string) $country->codigo_afip);
        if ($afipCode !== '' && strlen($afipCode) === 3 && ctype_digit($afipCode)) {
            return $afipCode;
        }

        throw new Exception(
            "Información Anticipada: {$label} no tiene configurado un código de país válido para ARCA."
        );
    }

    private function iaCountryValue($value, string $label): string
    {
        $value = strtoupper(trim((string) $value));
        if (strlen($value) === 2) {
            $country = \App\Models\Country::where('alpha2_code', $value)->first();
            return $this->iaCountry($country, $label);
        }

        if (!preg_match('/^\\d{3}$/', $value)) {
            throw new Exception("Información Anticipada: {$label} debe ser un código de país válido para ARCA.");
        }

        $country = \App\Models\Country::where('codigo_afip', $value)->first();
        return $this->iaCountry($country, $label);
    }

    private function iaPort($port, string $label): string
    {
        if (!$port) {
            throw new Exception("Información Anticipada: {$label} no está configurado.");
        }
        $code = strtoupper(trim((string) $port->code));
        if (strlen($code) !== 5) {
            throw new Exception("Información Anticipada: {$label} debe tener un código de puerto válido de 5 caracteres.");
        }
        return $code;
    }

    private function iaVoyageCustomsCode(Voyage $voyage): string
    {
        $operativeCodes = $this->iaVoyageOperativeCodes($voyage);
        if ($operativeCodes->isNotEmpty()) {
            $customsCodes = $operativeCodes
                ->map(fn ($code) => $this->iaCustomsFromOperativeCode(
                    $code,
                    'CodigoAduana'
                ))
                ->unique()
                ->values();

            if ($customsCodes->count() === 1) {
                return (string) $customsCodes->first();
            }

            throw new Exception(
                'Información Anticipada: los lugares operativos del viaje pertenecen a distintas Aduanas.'
            );
        }

        $operativeCode = $this->iaVoyageOperativeLocation($voyage);
        if ($operativeCode !== null) {
            return $this->iaCustomsFromOperativeCode(
                $operativeCode,
                'CodigoAduana'
            );
        }

        $candidates = [];
        if ($voyage->originPort?->country?->alpha2_code === 'AR') {
            $candidates = [
                $voyage->originCustoms?->webservice_code,
                $voyage->originCustoms?->code,
                $voyage->originPort?->primaryCustomsOffice?->webservice_code,
                $voyage->originPort?->primaryCustomsOffice?->code,
                $voyage->originPort?->afip_code,
            ];
        } elseif ($voyage->destinationPort?->country?->alpha2_code === 'AR') {
            $candidates = [
                $voyage->destinationCustoms?->webservice_code,
                $voyage->destinationCustoms?->code,
                $voyage->destinationPort?->primaryCustomsOffice?->webservice_code,
                $voyage->destinationPort?->primaryCustomsOffice?->code,
                $voyage->destinationPort?->afip_code,
            ];
        }

        foreach ($candidates as $candidate) {
            $digits = preg_replace('/\D+/', '', (string) $candidate);
            if (strlen($digits) === 3) {
                return $digits;
            }
        }

        throw new Exception(
            'Información Anticipada: no se pudo determinar el código de Aduana requerido.'
        );
    }

    private function iaVoyageOperativeCodes(Voyage $voyage)
    {
        $isArgentineOrigin = $voyage->originPort?->country?->alpha2_code === 'AR';
        $isArgentineDestination = $voyage->destinationPort?->country?->alpha2_code === 'AR';

        if (!$isArgentineOrigin && !$isArgentineDestination) {
            return collect();
        }

        $field = $isArgentineOrigin
            ? 'origin_operative_code'
            : 'operational_discharge_code';

        $bills = \App\Models\BillOfLading::whereHas(
            'shipment',
            fn ($q) => $q->where('voyage_id', $voyage->id)
        )->with('shipmentItems')->get();

        return $bills
            ->flatMap(function ($bill) use ($field) {
                return collect([$bill->{$field}])
                    ->merge($bill->shipmentItems->pluck($field));
            })
            ->filter(fn ($value) => trim((string) $value) !== '')
            ->map(fn ($value) => trim((string) $value))
            ->unique()
            ->values();
    }

    private function iaVoyageOperativeLocation(Voyage $voyage): ?string
    {
        $isArgentineOrigin = $voyage->originPort?->country?->alpha2_code === 'AR';
        $isArgentineDestination = $voyage->destinationPort?->country?->alpha2_code === 'AR';

        if (!$isArgentineOrigin && !$isArgentineDestination) {
            return null;
        }

        $codes = $this->iaVoyageOperativeCodes($voyage);

        foreach ($codes as $code) {
            $this->iaOperativeCode($code, 'CodigoLugarOperativo');
        }

        if ($codes->count() > 1) {
            // CodigoLugarOperativo es opcional en RegistrarViaje. Cuando la
            // carga descarga en más de una terminal de la misma Aduana no se
            // elige una arbitrariamente; CodigoAduana se resuelve por separado.
            return null;
        }

        if ($codes->isEmpty()) {
            $argentinePort = $isArgentineOrigin
                ? $voyage->originPort
                : $voyage->destinationPort;

            $portLocations = \App\Models\AfipOperativeLocation::where(
                'port_id',
                $argentinePort?->id
            )->where('is_active', true)->get();

            if ($portLocations->count() === 1) {
                return $this->iaOperativeCode(
                    $portLocations->first()->location_code,
                    'CodigoLugarOperativo'
                );
            }

            if ($portLocations->count() > 1) {
                throw new Exception(
                    "Información Anticipada: el puerto argentino {$argentinePort?->code} tiene más de un lugar operativo activo; debe informarse el lugar operativo del viaje."
                );
            }

            return null;
        }

        return $this->iaOperativeCode(
            $codes->first(),
            'CodigoLugarOperativo'
        );
    }

    private function iaOperativeCode($value, string $label): string
    {
        $code = $this->iaRequired($value, $label, 5);
        if (mb_strlen($code) !== 5) {
            throw new Exception(
                "Información Anticipada: {$label} debe tener un código de lugar operativo válido de 5 caracteres."
            );
        }

        $exists = \App\Models\AfipOperativeLocation::where(
            'location_code',
            $code
        )->where('is_active', true)->exists();

        if (!$exists) {
            throw new Exception(
                "Información Anticipada: {$label} {$code} no existe en el catálogo de lugares operativos de Aduana."
            );
        }

        return $code;
    }

    private function iaCustomsFromOperativeCode(
        string $operativeCode,
        string $label
    ): string {
        $location = \App\Models\AfipOperativeLocation::where(
            'location_code',
            $operativeCode
        )->where('is_active', true)->first();

        if (!$location) {
            throw new Exception(
                "Información Anticipada: no existe el lugar operativo {$operativeCode} en el catálogo de Aduana."
            );
        }

        return $this->iaNumeric(
            $location->customs_code,
            $label,
            3
        );
    }

    private function iaResolveCustomsForOperative(
        $candidate,
        string $operativeCode,
        string $label
    ): string {
        $expected = $this->iaCustomsFromOperativeCode(
            $operativeCode,
            $label
        );

        if ($candidate === null || trim((string) $candidate) === '') {
            return $expected;
        }

        $provided = $this->iaNumeric($candidate, $label, 3);
        if ($provided !== $expected) {
            throw new Exception(
                "Información Anticipada: {$label} {$provided} no corresponde "
                . "al lugar operativo {$operativeCode} (Aduana {$expected})."
            );
        }

        return $provided;
    }

    private function iaImportExportIndicator(Voyage $voyage): ?string
    {
        $origin = $voyage->originPort?->country?->alpha2_code;
        $destination = $voyage->destinationPort?->country?->alpha2_code;

        if ($origin === 'AR' && $destination !== 'AR') {
            return 'E';
        }

        if ($destination === 'AR' && $origin !== 'AR') {
            return 'I';
        }

        return null;
    }

    private function iaContainerCondition($container, $item = null): string
    {
        if (strtoupper(trim((string) $container->condition)) === 'V') {
            return 'V';
        }

        $value = strtoupper(trim((string) ($item?->pivot?->container_condition ?: $item?->container_condition ?: $container->container_condition)));
        if (in_array($value, ['H', 'P', 'V', 'C'], true)) {
            return $value;
        }

        return $value;
    }

    private function iaBillHasManifestedCargo(BillOfLading $bill): bool
    {
        foreach ($bill->shipmentItems as $item) {
            if ($item->containers->isEmpty()) {
                return true;
            }

            foreach ($item->containers as $container) {
                if (!in_array($this->iaContainerCondition($container, $item), ['V', 'C'], true)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function iaDate($date): string
    {
        return \Illuminate\Support\Carbon::parse($date)->format('Y-m-d\TH:i:s');
    }

    private function requireIaTransactionId(string $transactionId): string
    {
        return $this->iaRequired($transactionId, 'IdTransaccion', 20);
    }

    private function iaNumeric($value, string $label, int $length): string
    {
        $digits = preg_replace('/\D+/', '', (string) $value);
        if (strlen($digits) !== $length) {
            throw new Exception("Información Anticipada: {$label} debe tener exactamente {$length} dígitos.");
        }
        return $digits;
    }

    private function iaDecimalWeight(
        $value,
        string $label,
        int $maxIntegerDigits
    ): string {
        if (!is_numeric($value) || (float) $value < 0) {
            throw new Exception("Información Anticipada: {$label} debe ser un decimal no negativo.");
        }

        $text = trim((string) $value);
        [$integer] = explode('.', $text, 2);
        if (strlen(ltrim($integer, '-')) > $maxIntegerDigits) {
            throw new Exception("Información Anticipada: {$label} supera {$maxIntegerDigits} dígitos enteros.");
        }

        return $text;
    }

    private function iaIntegerWeight(
        $value,
        string $label,
        int $maxDigits
    ): string {
        if (!is_numeric($value) || (float) $value < 0) {
            throw new Exception("Información Anticipada: {$label} debe ser un entero no negativo.");
        }

        $numeric = (float) $value;
        $rounded = round($numeric);

        if (abs($numeric - $rounded) > 0.000001) {
            throw new Exception("Información Anticipada: {$label} debe expresarse en kilogramos enteros.");
        }

        $text = (string) (int) $rounded;
        if (strlen($text) > $maxDigits) {
            throw new Exception("Información Anticipada: {$label} supera {$maxDigits} dígitos.");
        }

        return $text;
    }

    private function iaYesNo($value): string
    {
        if ($value === true || $value === 1 || $value === '1' || strtoupper(trim((string) $value)) === 'S') {
            return 'S';
        }
        if ($value === false || $value === 0 || $value === '0' || strtoupper(trim((string) $value)) === 'N') {
            return 'N';
        }
        throw new Exception('Información Anticipada: indicador S/N sin valor válido.');
    }

    private function iaUniqueItemValue($items, string $field)
    {
        $values = $items->pluck($field)
            ->filter(fn ($value) => $value !== null && trim((string) $value) !== '')
            ->map(fn ($value) => trim((string) $value))
            ->unique()
            ->values();

        if ($values->count() > 1) {
            throw new Exception("Información Anticipada: los ítems tienen valores distintos para {$field}.");
        }

        return $values->first();
    }

    private function writeIaTitle(\XMLWriter $w, BillOfLading $bill, bool $closing = false): void
    {
        $bill->loadMissing([
            'consignee',
            'notifyParty',
            'loadingPort.country',
            'dischargePort.country',
            'transshipmentPort.country',
            'shipmentItems.packagingType',
            'shipmentItems.cargoType',
            'shipmentItems.containers.containerType',
        ]);

        $number = $this->iaRequired($bill->bill_number, 'NumeroConocimiento', 18);
        if (!$bill->loading_date) {
            throw new Exception("Conocimiento {$number}: FechaEmbarque es obligatoria.");
        }
        if (!$bill->loadingPort || !$bill->dischargePort) {
            throw new Exception("Conocimiento {$number}: puertos de embarque y descarga son obligatorios.");
        }
        if ($bill->shipmentItems->isEmpty()) {
            throw new Exception("Conocimiento {$number}: debe tener líneas de mercadería.");
        }

        $items = $bill->shipmentItems;
        $firstItem = $items->first();
        $consolidated = $this->iaYesNo($bill->is_consolidated);
        $transit = $this->iaYesNo($bill->is_transit_transshipment);

        $tariff = $bill->commodity_code
            ?: $this->iaUniqueItemValue($items, 'tariff_position')
            ?: $this->iaUniqueItemValue($items, 'commodity_code');
        $tariff = $this->iaRequired($tariff, "Conocimiento {$number}: PosicionArancelaria", 16);
        if ($consolidated === 'N' && (mb_strlen($tariff) < 7 || mb_strlen($tariff) > 15)) {
            throw new Exception("Conocimiento {$number}: PosicionArancelaria debe tener entre 7 y 15 caracteres cuando no es consolidado.");
        }

        $forwarder = $this->iaRequired(
            $this->iaUniqueItemValue($items, 'foreign_forwarder_name'),
            "Conocimiento {$number}: RazonSocialFowarderExterior",
            70
        );
        $customs = null;
        $operative = null;

        if (!$closing) {
            $operative = $bill->operational_discharge_code
                ?: $this->iaUniqueItemValue($items, 'operational_discharge_code');
            $operative = $this->iaOperativeCode(
                $operative,
                "Conocimiento {$number}: CodigoLugarOperativoDescarga"
            );

            $customs = $bill->discharge_customs_code
                ?: $this->iaUniqueItemValue($items, 'discharge_customs_code');
            $customs = $this->iaResolveCustomsForOperative(
                $customs,
                $operative,
                "Conocimiento {$number}: CodigoAduanaDescarga"
            );
        }

        $marks = $bill->cargo_marks
            ?: $this->iaUniqueItemValue($items, 'cargo_marks');
        if (trim((string) $marks) === '' && $items->every(fn ($item) => $item->containers->isNotEmpty())) {
            // Precedente operativo ya aceptado por ARCA en RegistrarTitulosCbc:
            // para títulos contenedorizados sin marca declarada se informa S/M.
            $marks = 'S/M';
        }
        $marks = $this->iaRequired($marks, "Conocimiento {$number}: MarcaBultos", 80);
        $countryDestination = $bill->destination_country_code
            ? $this->iaCountryValue($bill->destination_country_code, 'CodigoPaisDestino')
            : $this->iaCountry($bill->dischargePort?->country, 'CodigoPaisDestino');

        $w->startElement('ar:' . ($closing ? 'TituloCierre' : 'Titulo'));
        $w->writeElement('ar:FechaEmbarque', $this->iaDate($bill->loading_date));
        $w->writeElement('ar:CodigoPuertoEmbarque', $this->iaPort($bill->loadingPort, 'CodigoPuertoEmbarque'));

        if ($bill->origin_loading_date) {
            $w->writeElement('ar:FechaCargaLugarOrigen', $this->iaDate($bill->origin_loading_date));
        }

        if ($closing) {
            if ($bill->origin_location) {
                $w->writeElement('ar:LugarOrigen', $this->iaRequired($bill->origin_location, 'LugarOrigen', 50));
            }
            if ($bill->origin_country_code) {
                $w->writeElement('ar:CodigoPaisLugarOrigen', $this->iaCountryValue($bill->origin_country_code, 'CodigoPaisLugarOrigen'));
            }
        } else {
            $w->writeElement(
                'ar:LugarOrigen',
                $this->iaRequired($bill->origin_location, "Conocimiento {$number}: LugarOrigen", 50)
            );
            $w->writeElement(
                'ar:CodigoPaisLugarOrigen',
                $this->iaCountryValue($bill->origin_country_code, "Conocimiento {$number}: CodigoPaisLugarOrigen")
            );
        }

        $w->writeElement('ar:NumeroConocimiento', $number);

        if ($bill->transshipmentPort) {
            $w->writeElement('ar:CodigoPuertoTrasbordo', $this->iaPort($bill->transshipmentPort, 'CodigoPuertoTrasbordo'));
        }

        $w->writeElement('ar:CodigoPuertoDescarga', $this->iaPort($bill->dischargePort, 'CodigoPuertoDescarga'));
        if ($bill->discharge_date) {
            $w->writeElement('ar:FechaDescarga', $this->iaDate($bill->discharge_date));
        }
        $w->writeElement('ar:CodigoPaisDestino', $countryDestination);
        $w->writeElement('ar:MarcaBultos', $marks);

        if (!$closing) {
            $consignee = trim((string) ($bill->consignee?->legal_name ?? ''));
            if ($consignee !== '') {
                $w->writeElement('ar:Consignatario', $this->iaRequired($consignee, 'Consignatario', 80));
            }
            $notify = trim((string) ($bill->notify_party_text ?: $bill->notifyParty?->legal_name));
            if ($notify !== '') {
                $w->writeElement('ar:NotificarA', $this->iaRequired($notify, 'NotificarA', 35));
            }

            $docType = $this->iaUniqueItemValue($items, 'consignee_document_type');
            if ($docType) {
                $w->writeElement('ar:TipoDocumentoDestinatarioMercaderia', $this->iaRequired(strtoupper($docType), 'TipoDocumentoDestinatarioMercaderia', 4));
            }
            $destId = $this->iaUniqueItemValue($items, 'consignee_tax_id');
            if ($destId) {
                $w->writeElement('ar:IdentificadorDestinatarioMercaderia', $this->iaNumeric($destId, 'IdentificadorDestinatarioMercaderia', 11));
            }
        }

        $w->writeElement('ar:IndicadorConsolidado', $consolidated);
        $w->writeElement('ar:IndicadorTransitoTrasbordo', $transit);
        $w->writeElement('ar:PosicionArancelaria', $tariff);
        $w->writeElement('ar:IndicadorOperadorLogisticoSeguro', $this->iaYesNoRequired($this->iaUniqueItemValue($items, 'is_secure_logistics_operator'), 'IndicadorOperadorLogisticoSeguro'));
        $w->writeElement('ar:IndicadorTransitoMonitoreado', $this->iaYesNoRequired($this->iaUniqueItemValue($items, 'is_monitored_transit'), 'IndicadorTransitoMonitoreado'));
        $w->writeElement('ar:IndicadorRenar', $this->iaYesNoRequired($this->iaUniqueItemValue($items, 'is_renar'), 'IndicadorRenar'));
        $w->writeElement('ar:RazonSocialFowarderExterior', $forwarder);

        $forwarderTax = $this->iaUniqueItemValue($items, 'foreign_forwarder_tax_id');
        if ($forwarderTax) {
            $w->writeElement('ar:IndicadorTributarioForwarderExterior', $this->iaRequired($forwarderTax, 'IndicadorTributarioForwarderExterior', 35));
        }
        $forwarderCountry = $this->iaUniqueItemValue($items, 'foreign_forwarder_country');
        if ($forwarderCountry) {
            $w->writeElement('ar:CodigoPaisEmisorIdentificadorForwarderExterior', $this->iaCountryValue($forwarderCountry, 'CodigoPaisEmisorIdentificadorForwarderExterior'));
        }

        if ($firstItem?->comments) {
            $w->writeElement('ar:Comentario', $this->iaRequired($firstItem->comments, 'Comentario', 60));
        }

        if (!$closing) {
            $w->writeElement('ar:CodigoAduanaDescarga', $customs);
            $w->writeElement('ar:CodigoLugarOperativoDescarga', $operative);
        }

        $w->startElement('ar:Mercaderias');
        $usedLines = [];
        foreach ($items as $index => $item) {
            $line = (int) ($item->line_number ?: ($index + 1));
            if ($line < 1 || $line > 999 || isset($usedLines[$line])) {
                throw new Exception("Conocimiento {$number}: NumeroLinea inválido o repetido ({$line}).");
            }
            $usedLines[$line] = true;

            $packCode = trim((string) $item->packaging_code);
            if ($packCode === '' && $item->containers->isNotEmpty()) {
                // En el contrato ARCA, 05 representa mercadería contenedorizada.
                // Los envíos CBC exitosos existentes de la aplicación usan este
                // código y ARCA exige que CantidadManifestada coincida con la
                // cantidad de contenedores (error 11372).
                $packCode = '05';
            }
            $packCode = $this->iaRequired(
                $packCode,
                "Conocimiento {$number}, línea {$line}: código de embalaje",
                2
            );
            if (mb_strlen($packCode) !== 2) {
                throw new Exception("Conocimiento {$number}, línea {$line}: CodigoEmbalaje debe tener 2 caracteres.");
            }

            $quantity = $packCode === '05'
                ? $item->containers->count()
                : (int) $item->package_quantity;
            if ($quantity < 1 || $quantity > 999999999) {
                throw new Exception("Conocimiento {$number}, línea {$line}: CantidadManifestada fuera de rango.");
            }

            if ($item->gross_weight_kg === null || !is_numeric($item->gross_weight_kg)) {
                throw new Exception("Conocimiento {$number}, línea {$line}: PesoVolumenManifestado es obligatorio.");
            }
            $weight = (float) $item->gross_weight_kg;
            if ($weight < 0 || $weight > 99999999.999) {
                throw new Exception("Conocimiento {$number}, línea {$line}: PesoVolumenManifestado fuera de rango.");
            }

            $description = $this->iaRequired($item->item_description, "Conocimiento {$number}, línea {$line}: DescripcionMercaderia", 80);
            $packageMarks = trim((string) $item->cargo_marks);
            if ($packageMarks === '' && $packCode === '05') {
                // Precedente operativo ya aceptado por ARCA para líneas
                // contenedorizadas sin numeración de bultos declarada.
                $packageMarks = 'S/N';
            }
            $packageMarks = $this->iaRequired($packageMarks, "Conocimiento {$number}, línea {$line}: NumeroBultos", 100);

            $w->startElement('ar:LineaMercaderia');
            $w->writeElement('ar:NumeroLinea', (string) $line);
            $w->writeElement('ar:CodigoEmbalaje', $packCode);

            $packageTypeCode = trim((string) $item->package_type_description);
            if ($packCode !== '05' && mb_strlen($packageTypeCode) === 1) {
                $w->writeElement('ar:TipoEmbalaje', strtoupper($packageTypeCode));
            }

            if ($packCode === '05') {
                $conditions = $item->containers
                    ->map(fn ($container) => $this->iaContainerCondition($container, $item))
                    ->filter()
                    ->unique()
                    ->values();
                if ($conditions->count() !== 1 || !in_array($conditions->first(), ['H', 'P'], true)) {
                    throw new Exception("Conocimiento {$number}, línea {$line}: CondicionContenedor inválida.");
                }
                $w->writeElement('ar:CondicionContenedor', (string) $conditions->first());
            }

            $w->writeElement('ar:CantidadManifestada', (string) $quantity);
            $w->writeElement(
                'ar:PesoVolumenManifestado',
                $this->iaDecimalWeight(
                    $item->gross_weight_kg,
                    "Conocimiento {$number}, línea {$line}: PesoVolumenManifestado",
                    12
                )
            );
            $w->writeElement('ar:DescripcionMercaderia', $description);
            $w->writeElement('ar:NumeroBultos', $packageMarks);

            if ($item->cargoType?->code) {
                $w->writeElement('ar:TipoCarga', $this->iaRequired($item->cargoType->code, 'TipoCarga', 3));
            }
            if ($item->comments) {
                $w->writeElement('ar:Comentario', $this->iaRequired($item->comments, 'Comentario', 60));
            }
            $w->endElement();
        }
        $w->endElement();

        $containers = collect();
        foreach ($items as $item) {
            foreach ($item->containers as $container) {
                $containers->put($container->container_number, ['container' => $container, 'item' => $item]);
            }
        }

        if ($containers->isNotEmpty()) {
            $w->startElement('ar:Contenedores');
            foreach ($containers as $entry) {
                $this->writeIaTitleContainer(
                    $w,
                    $entry['container'],
                    $bill,
                    $entry['item'],
                    $closing
                );
            }
            $w->endElement();
        }

        $w->endElement();
    }

    private function writeIaTitleContainer(
        \XMLWriter $w,
        $container,
        BillOfLading $bill,
        $item,
        bool $closing
    ): void {
        $number = $this->iaRequired($container->container_number, 'IdentificadorContenedor', 20);
        $type = $container->containerType?->iso_code ?: $container->containerType?->code;
        $type = $this->iaRequired($type, "Contenedor {$number}: CaracteristicasContenedor", 4);
        if (mb_strlen($type) !== 4) {
            throw new Exception("Contenedor {$number}: CaracteristicasContenedor debe tener 4 caracteres.");
        }

        $condition = $this->iaContainerCondition($container, $item);
        if (!in_array($condition, ['H', 'P', 'V', 'C'], true)) {
            throw new Exception("Contenedor {$number}: CondicionContenedor inválida.");
        }

        $tare = $container->tare_weight_kg;
        $gross = $container->current_gross_weight_kg ?? $item?->pivot?->gross_weight_kg;
        if ($tare === null || !is_numeric($tare)) {
            throw new Exception("Contenedor {$number}: Tara es obligatoria.");
        }
        if ($gross === null || !is_numeric($gross)) {
            throw new Exception("Contenedor {$number}: PesoBruto es obligatorio.");
        }
        if ((float) $tare > (float) $gross) {
            throw new Exception("Contenedor {$number}: Tara no puede superar PesoBruto.");
        }

        $expiry = $container->expiry_date ?: $container->csc_expiry_date;
        $acep = data_get($container->webservice_data, 'acep');
        if ($expiry && $acep) {
            throw new Exception(
                "Contenedor {$number}: no se pueden informar simultáneamente FechaVencimientoContenedor y ACEP."
            );
        }

        $w->startElement('ar:' . ($closing ? 'ContenedorCierre' : 'Contenedor'));

        $operatorTaxId = $container->operatorClient?->tax_id
            ?: data_get($container->webservice_data, 'operator_tax_id');

        if ($closing) {
            $w->writeElement(
                'ar:CuitAtaOperadorContenedor',
                $this->iaNumeric(
                    $operatorTaxId,
                    "Contenedor {$number}: CuitAtaOperadorContenedor",
                    11
                )
            );
        } elseif ($operatorTaxId) {
            $w->writeElement(
                'ar:CuitAtaOperadorContenedor',
                $this->iaNumeric(
                    $operatorTaxId,
                    "Contenedor {$number}: CuitAtaOperadorContenedor",
                    11
                )
            );
        }

        $w->writeElement('ar:CaracteristicasContenedor', $type);
        $w->writeElement('ar:IdentificadorContenedor', $number);
        $w->writeElement('ar:CondicionContenedor', $condition);
        $w->writeElement('ar:Tara', $this->iaIntegerWeight($tare, "Contenedor {$number}: Tara", 10));
        $w->writeElement('ar:PesoBruto', $this->iaDecimalWeight($gross, "Contenedor {$number}: PesoBruto", 14));

        $seal = trim((string) ($container->customs_seal ?: $container->shipper_seal ?: $container->carrier_seal));
        if ($seal !== '') {
            $w->writeElement('ar:NumeroPrecintoOrigen', $this->iaRequired($seal, 'NumeroPrecintoOrigen', 35));
        }
        if ($expiry) {
            $w->writeElement('ar:FechaVencimientoContenedor', $this->iaDate($expiry));
        }
        if ($acep) {
            $w->writeElement('ar:Acep', $this->iaRequired($acep, 'ACEP', 20));
        }

        if (!$closing) {
            $operative = $bill->operational_discharge_code ?: $item?->operational_discharge_code;
            $operative = $this->iaOperativeCode(
                $operative,
                "Contenedor {$number}: CodigoLugarOperativoDescarga"
            );

            $customs = $bill->discharge_customs_code ?: $item?->discharge_customs_code;
            $customs = $this->iaResolveCustomsForOperative(
                $customs,
                $operative,
                "Contenedor {$number}: CodigoAduana"
            );

            $w->writeElement('ar:CodigoAduana', $customs);
            $w->writeElement('ar:CodigoLugarOperativoDescarga', $operative);
        }

        $w->endElement();
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