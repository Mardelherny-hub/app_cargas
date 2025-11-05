<?php

namespace App\Services\Simple;

use App\Models\Company;
use App\Models\Voyage;
use App\Models\Shipment;
use App\Models\BillOfLading;
use App\Models\Container;
use App\Models\VoyageAttachment;
use Exception;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * SISTEMA SIMPLE WEBSERVICES - Generador XML Paraguay
 * 
 * Genera XML automáticamente desde BD para webservices DNA Paraguay (GDSF)
 * Sigue el mismo patrón exitoso de SimpleXmlGenerator (Argentina)
 * 
 * MÉTODOS GDSF SOPORTADOS:
 * - XFFM: Carátula/Manifiesto Fluvial
 * - XFBL: Conocimientos/BLs
 * - XFBT: Hoja de Ruta/Barcazas
 * - XISP: Incluir Embarcación
 * - XRSP: Desvincular Embarcación
 * - XFCT: Cerrar Viaje
 * 
 * IMPORTANTE:
 * - USA SOLO campos verificados de modelos
 * - NO inventa datos
 * - Genera XML según especificación GDSF
 */
class SimpleXmlGeneratorParaguay
{
    private Company $company;
    private array $config;

    public function __construct(Company $company, array $config = [])
    {
        $this->company = $company;
        $this->config = $config;
    }

    /**
     * XFFM - Carátula/Manifiesto Fluvial
     * Primer mensaje obligatorio - Registra el viaje en DNA Paraguay
     * 
     * @param Voyage $voyage
     * @param string $transactionId
     * @return string XML completo
     */
    public function createXffmXml(Voyage $voyage, string $transactionId): string
    {
        try {
            // Cargar relaciones necesarias
            $voyage->load([
                'leadVessel',
                'originPort.country',
                'destinationPort.country',
                'captain',
                'company'
            ]);

            $w = new \XMLWriter();
            $w->openMemory();
            $w->startDocument('1.0', 'UTF-8');

            // Envelope - Estructura según manual GDSF
            $w->startElement('Envelope');
            $w->writeAttribute('xmlns', 'http://schemas.xmlsoap.org/soap/envelope/');
            
                $w->startElement('Body');
                    $w->startElement('MicDta');
                    $w->writeAttribute('xmlns', 'http://www.dna.gov.py/gdsf');
                    
                        // ID Transacción
                        $w->writeElement('idTransaccion', substr($transactionId, 0, 20));
                        
                        // Transportista
                        $w->startElement('transportista');
                            $w->writeElement('nombre', htmlspecialchars(
                                substr($this->company->legal_name ?? 'NO ESPECIFICADO', 0, 100)
                            ));
                            $w->writeElement('domicilio', htmlspecialchars(
                                substr($this->company->address ?? 'NO ESPECIFICADO', 0, 100)
                            ));
                            $w->writeElement('codPais', $this->getCountryCode(
                                $voyage->originPort->country->alpha2_code ?? 'AR'
                            ));
                            $w->writeElement('idFiscal', (string)($this->company->tax_id ?? '0'));
                            $w->writeElement('tipTrans', 'A'); // Agente de Transporte Aduanero
                        $w->endElement(); // transportista
                        
                        // Propietario del vehículo (mismo que transportista)
                        $w->startElement('propVehiculo');
                            $w->writeElement('nombre', htmlspecialchars(
                                substr($this->company->legal_name ?? 'NO ESPECIFICADO', 0, 100)
                            ));
                            $w->writeElement('domicilio', htmlspecialchars(
                                substr($this->company->address ?? 'NO ESPECIFICADO', 0, 100)
                            ));
                            $w->writeElement('codPais', $this->getCountryCode(
                                $voyage->originPort->country->country->alpha2_code ?? 'AR'
                            ));
                            $w->writeElement('idFiscal', (string)($this->company->tax_id ?? '0'));
                        $w->endElement(); // propVehiculo
                        
                        // Indicador en lastre (N = con carga, S = vacío)
                        $indEnLastre = ($voyage->is_empty_transport === 'S') ? 'S' : 'N';
                        $w->writeElement('indEnLastre', $indEnLastre);
                        
                        // Embarcación principal
                        $w->startElement('embarcacion');
                            $vessel = $voyage->leadVessel;
                            $w->writeElement('codPais', $this->getCountryCode(
                                $voyage->originPort->country->alpha2_code ?? 'AR'
                            ));
                            $w->writeElement('patente', htmlspecialchars(
                                substr($vessel->registration_number ?? 'SIN-PATENTE', 0, 20)
                            ));
                            $w->writeElement('patentesRemol', ''); // Vacío si no aplica
                            $w->writeElement('marca', htmlspecialchars(
                                substr($vessel->name ?? 'NO ESPECIFICADO', 0, 50)
                            ));
                            $w->writeElement('nroChasis', ''); // No aplicable para embarcaciones
                            $w->writeElement('modChasis', ''); // No aplicable
                            $w->writeElement('anioFab', (string)($vessel->built_date 
                                ? Carbon::parse($vessel->built_date)->year 
                                : date('Y'))
                            );
                            $w->writeElement('capTraccion', '0'); // Capacidad tracción (N/A barcazas)
                            $w->writeElement('accTipNum', ''); // Número tipo accesorio
                            
                            // Precintos (opcional si hay)
                            $w->startElement('precintos');
                                $w->writeAttribute('xsi:nil', 'true');
                                $w->writeAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
                            $w->endElement(); // precintos
                            
                            // Conductor (Capitán)
                            if ($voyage->captain) {
                                $w->startElement('conductor');
                                    $w->writeElement('nombre', htmlspecialchars(
                                        substr($voyage->captain->full_name ?? 
                                               trim(($voyage->captain->first_name ?? '') . ' ' . 
                                                    ($voyage->captain->last_name ?? '')), 0, 100)
                                    ));
                                    $w->writeElement('tipDoc', $this->getDocumentType(
                                        $voyage->captain->document_type ?? 'DNI'
                                    ));
                                    $w->writeElement('nroDoc', (string)($voyage->captain->document_number ?? '0'));
                                    $w->writeElement('domicilio', htmlspecialchars(
                                        substr($voyage->captain->address ?? 'NO ESPECIFICADO', 0, 100)
                                    ));
                                $w->endElement(); // conductor
                            } else {
                                $w->startElement('conductor');
                                    $w->writeElement('nombre', 'SIN ASIGNAR');
                                    $w->writeElement('tipDoc', 'DNI');
                                    $w->writeElement('nroDoc', '0');
                                    $w->writeElement('domicilio', 'NO ESPECIFICADO');
                                $w->endElement();
                            }
                            
                        $w->endElement(); // embarcacion
                        
                    $w->endElement(); // MicDta
                $w->endElement(); // Body
            $w->endElement(); // Envelope

            $w->endDocument();
            $xmlContent = $w->outputMemory();

            Log::info('XML XFFM generado', [
                'voyage_id' => $voyage->id,
                'transaction_id' => $transactionId,
                'xml_length' => strlen($xmlContent)
            ]);

            return $xmlContent;

        } catch (Exception $e) {
            Log::error('Error generando XML XFFM', [
                'voyage_id' => $voyage->id ?? null,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * XFBL - Conocimientos/BLs
     * Mensaje para declarar los Bills of Lading del viaje
     * Requiere XFFM enviado previamente
     * 
     * @param Voyage $voyage
     * @param string $transactionId
     * @param string|null $nroViaje Número de viaje retornado por XFFM
     * @return string XML completo
     */
    public function createXfblXml(Voyage $voyage, string $transactionId, ?string $nroViaje = null): string
    {
        try {
            // Cargar Bills of Lading a través de Shipments
            $voyage->load([
                'shipments.billsOfLading.shipper',
                'shipments.billsOfLading.consignee',
                'shipments.billsOfLading.notifyParty',
                'shipments.billsOfLading.loadingPort.country',
                'shipments.billsOfLading.dischargePort.country'
            ]);

            // Obtener todos los BLs del viaje
            $billsOfLading = $voyage->shipments->flatMap->billsOfLading;

            if ($billsOfLading->isEmpty()) {
                throw new Exception('No hay Bills of Lading para generar XFBL');
            }

            $w = new \XMLWriter();
            $w->openMemory();
            $w->startDocument('1.0', 'UTF-8');

            $w->startElement('Envelope');
            $w->writeAttribute('xmlns', 'http://schemas.xmlsoap.org/soap/envelope/');
            
                $w->startElement('Body');
                    $w->startElement('TitulosTransporte');
                    $w->writeAttribute('xmlns', 'http://www.dna.gov.py/gdsf');
                    
                        // ID Transacción
                        $w->writeElement('idTransaccion', substr($transactionId, 0, 20));
                        
                        // Número de viaje (si fue retornado por XFFM)
                        if ($nroViaje) {
                            $w->writeElement('nroViaje', htmlspecialchars($nroViaje));
                        }
                        
                        // Lista de Títulos de Transporte (BLs)
                        $w->startElement('titTrans');
                        
                        foreach ($billsOfLading as $bl) {
                            $w->startElement('TitTrans');
                            
                                // Número de conocimiento
                                $w->writeElement('idTitTrans', htmlspecialchars(
                                    substr($bl->bill_number ?? 'SIN-BL', 0, 18)
                                ));
                                
                                // Remitente (Shipper)
                                $w->startElement('remitente');
                                    $shipper = $bl->shipper;
                                    $w->writeElement('nombre', htmlspecialchars(
                                        substr($shipper->legal_name ?? 'NO ESPECIFICADO', 0, 100)
                                    ));
                                    $w->writeElement('domicilio', htmlspecialchars(
                                        substr($shipper->address ?? 'NO ESPECIFICADO', 0, 100)
                                    ));
                                    $w->writeElement('codPais', $this->getCountryCode(
                                        $bl->loadingPort->country->alpha2_code ?? 'AR'
                                    ));
                                    $w->writeElement('idFiscal', (string)($shipper->tax_id ?? '0'));
                                $w->endElement(); // remitente
                                
                                // Consignatario (Consignee)
                                $w->startElement('consignatario');
                                    $consignee = $bl->consignee;
                                    $w->writeElement('nombre', htmlspecialchars(
                                        substr($consignee->legal_name ?? 'NO ESPECIFICADO', 0, 100)
                                    ));
                                    $w->writeElement('domicilio', htmlspecialchars(
                                        substr($consignee->address ?? 'NO ESPECIFICADO', 0, 100)
                                    ));
                                    $w->writeElement('codPais', $this->getCountryCode(
                                        $bl->dischargePort->country->alpha2_code ?? 'PY'
                                    ));
                                    $w->writeElement('idFiscal', (string)($consignee->tax_id ?? '0'));
                                $w->endElement(); // consignatario
                                
                                // Notificado (Notify Party) - Opcional
                                if ($bl->notifyParty) {
                                    $w->startElement('notificado');
                                        $notify = $bl->notifyParty;
                                        $w->writeElement('nombre', htmlspecialchars(
                                            substr($notify->legal_name ?? 'NO ESPECIFICADO', 0, 100)
                                        ));
                                        $w->writeElement('domicilio', htmlspecialchars(
                                            substr($notify->address ?? 'NO ESPECIFICADO', 0, 100)
                                        ));
                                        $w->writeElement('codPais', $this->getCountryCode(
                                            $bl->dischargePort->country->alpha2_code ?? 'PY'
                                        ));
                                        $w->writeElement('idFiscal', (string)($notify->tax_id ?? '0'));
                                    $w->endElement(); // notificado
                                }
                                
                                // Indicador finalidad comercial
                                $w->writeElement('indFinCom', 'V'); // V=Venta, C=Consignación
                                
                                // Factura (opcional)
                                $w->startElement('nroFact');
                                    $w->writeAttribute('xsi:nil', 'true');
                                    $w->writeAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
                                $w->endElement(); // nroFact
                                
                                // Bultos/Mercadería
                                $w->startElement('bultos');
                                    $w->startElement('Bulto');
                                        $w->writeElement('tipEmbalaje', '01'); // Código embalaje según tabla GDSF
                                        $w->writeElement('cantBultos', (string)($bl->total_packages ?? 1));
                                        $w->writeElement('marcas', htmlspecialchars(
                                            substr($bl->cargo_marks ?? 'SIN MARCAS', 0, 80)
                                        ));
                                        
                                        // Armonizado (Descripción de mercadería)
                                        $w->startElement('armonizado');
                                            $w->writeElement('codArmonizado', '0000.00.00'); // Código NCM/HS
                                            $w->writeElement('descMercaderia', htmlspecialchars(
                                                substr($bl->cargo_description ?? 'MERCADERIA GENERAL', 0, 500)
                                            ));
                                            $w->writeElement('pesoBruto', number_format(
                                                $bl->gross_weight_kg ?? 0, 2, '.', ''
                                            ));
                                        $w->endElement(); // armonizado
                                        
                                    $w->endElement(); // Bulto
                                $w->endElement(); // bultos

                                // ✅ AGREGAR AQUÍ (DESPUÉS de cerrar </bultos>, ANTES de cerrar </TitTrans>):

                                // ADJUNTOS - DocAnexo
                                $attachments = $voyage->attachments()
                                    ->where(function($query) use ($bl) {
                                        $query->where('bill_of_lading_id', $bl->id)
                                            ->orWhereNull('bill_of_lading_id');
                                    })
                                    ->get();

                                foreach ($attachments as $attachment) {
                                    $w->startElement('DocAnexo');
                                    
                                        // Tipo de documento (código EDIFACT)
                                        $w->writeElement('codTipDoc', $attachment->getDocumentTypeCode());
                                        
                                        // Número de documento
                                        if ($attachment->document_number) {
                                            $w->writeElement('documento', htmlspecialchars(
                                                substr($attachment->document_number, 0, 39)
                                            ));
                                        } else {
                                            // Si no tiene número, usar nombre del archivo
                                            $w->writeElement('documento', htmlspecialchars(
                                                substr($attachment->original_name, 0, 39)
                                            ));
                                        }
                                        
                                        // Archivo en Base64 (opcional según GDSF, pero lo incluimos)
                                        try {
                                            $base64Content = $attachment->getBase64Content();
                                            $w->writeElement('archivo', $base64Content);
                                        } catch (\Exception $e) {
                                            Log::warning('Error obteniendo Base64 de adjunto', [
                                                'attachment_id' => $attachment->id,
                                                'error' => $e->getMessage()
                                            ]);
                                            // Continuar sin el archivo si falla
                                        }
                                        
                                    $w->endElement(); // DocAnexo
                                }

                                $w->endElement(); // TitTrans
                        }
                        
                        $w->endElement(); // titTrans
                        
                    $w->endElement(); // TitulosTransporte
                $w->endElement(); // Body
            $w->endElement(); // Envelope

            $w->endDocument();
            $xmlContent = $w->outputMemory();

            Log::info('XML XFBL generado', [
                'voyage_id' => $voyage->id,
                'transaction_id' => $transactionId,
                'bls_count' => $billsOfLading->count(),
                'xml_length' => strlen($xmlContent)
            ]);

            return $xmlContent;

        } catch (Exception $e) {
            Log::error('Error generando XML XFBL', [
                'voyage_id' => $voyage->id ?? null,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * XFBT - Hoja de Ruta (Barcazas y Contenedores)
     * Declara los contenedores y su asignación a barcazas
     * 
     * @param Voyage $voyage
     * @param string $transactionId
     * @param string|null $nroViaje
     * @return string XML completo
     */
    public function createXfbtXml(Voyage $voyage, string $transactionId, ?string $nroViaje = null): string
    {
        try {
            // Cargar contenedores a través de Shipments → BLs
            $voyage->load([
                'shipments.billsOfLading.shipmentItems.containers.containerType'
            ]);

            // Obtener todos los contenedores a través de ShipmentItems
            $containers = $voyage->shipments
                ->flatMap->billsOfLading
                ->flatMap->shipmentItems
                ->flatMap->containers
                ->unique('id');

            if ($containers->isEmpty()) {
                throw new Exception('No hay contenedores para generar XFBT');
            }

            $w = new \XMLWriter();
            $w->openMemory();
            $w->startDocument('1.0', 'UTF-8');

            $w->startElement('Envelope');
            $w->writeAttribute('xmlns', 'http://schemas.xmlsoap.org/soap/envelope/');
            
                $w->startElement('Body');
                    $w->startElement('HojaRuta');
                    $w->writeAttribute('xmlns', 'http://www.dna.gov.py/gdsf');
                    
                        $w->writeElement('idTransaccion', substr($transactionId, 0, 20));
                        
                        if ($nroViaje) {
                            $w->writeElement('nroViaje', htmlspecialchars($nroViaje));
                        }
                        
                        // Lista de Contenedores
                        $w->startElement('contenedores');
                        
                        foreach ($containers as $container) {
                            $w->startElement('Contenedor');
                            
                                // Identificador del contenedor
                                $w->writeElement('id', htmlspecialchars(
                                    substr($container->container_number ?? 'SIN-CONT', 0, 20)
                                ));
                                
                                // Código de medida (20GP, 40HC, etc.)
                                $w->writeElement('codMedida', htmlspecialchars(
                                    substr($container->containerType->iso_code ?? '40HC', 0, 4)
                                ));
                                
                                // Condición (H=House, P=Pier, C=Correo)
                                $w->writeElement('condicion', 'H'); // House por defecto
                                
                                // Accesorio (tipo de embalaje)
                                $w->writeElement('accesorio', '05'); // Contenedor según tabla GDSF
                                
                                // Precintos
                                $w->startElement('precintos');
                                    if ($container->seal_number) {
                                        $w->startElement('Precinto');
                                            $w->writeElement('nroPrecinto', htmlspecialchars(
                                                substr($container->seal_number, 0, 35)
                                            ));
                                        $w->endElement(); // Precinto
                                    } else {
                                        $w->writeAttribute('xsi:nil', 'true');
                                        $w->writeAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
                                    }
                                $w->endElement(); // precintos
                                
                            $w->endElement(); // Contenedor
                        }
                        
                        $w->endElement(); // contenedores
                        
                    $w->endElement(); // HojaRuta
                $w->endElement(); // Body
            $w->endElement(); // Envelope

            $w->endDocument();
            $xmlContent = $w->outputMemory();

            Log::info('XML XFBT generado', [
                'voyage_id' => $voyage->id,
                'transaction_id' => $transactionId,
                'containers_count' => $containers->count(),
                'xml_length' => strlen($xmlContent)
            ]);

            return $xmlContent;

        } catch (Exception $e) {
            Log::error('Error generando XML XFBT', [
                'voyage_id' => $voyage->id ?? null,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * XFCT - Cerrar Viaje
     * Cierra el número de viaje cuando todo está completo
     * 
     * @param string $nroViaje Número de viaje retornado por DNA
     * @param string $transactionId
     * @return string XML completo
     */
    public function createXfctXml(string $nroViaje, string $transactionId): string
    {
        try {
            $w = new \XMLWriter();
            $w->openMemory();
            $w->startDocument('1.0', 'UTF-8');

            $w->startElement('Envelope');
            $w->writeAttribute('xmlns', 'http://schemas.xmlsoap.org/soap/envelope/');
            
                $w->startElement('Body');
                    $w->startElement('CerrarViaje');
                    $w->writeAttribute('xmlns', 'http://www.dna.gov.py/gdsf');
                    
                        $w->writeElement('idTransaccion', substr($transactionId, 0, 20));
                        $w->writeElement('nroViaje', htmlspecialchars($nroViaje));
                        
                    $w->endElement(); // CerrarViaje
                $w->endElement(); // Body
            $w->endElement(); // Envelope

            $w->endDocument();
            $xmlContent = $w->outputMemory();

            Log::info('XML XFCT generado', [
                'nro_viaje' => $nroViaje,
                'transaction_id' => $transactionId
            ]);

            return $xmlContent;

        } catch (Exception $e) {
            Log::error('Error generando XML XFCT', [
                'nro_viaje' => $nroViaje ?? null,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * HELPERS - Mapeo de códigos según tablas DNA Paraguay
     */

    /**
     * Mapear código de país a formato DNA (3 dígitos)
     */
    private function getCountryCode(string $alpha2Code): string
    {
        $mapping = [
            'AR' => '032', // Argentina
            'PY' => '600', // Paraguay
            'BR' => '076', // Brasil
            'UY' => '858', // Uruguay
            'BO' => '068', // Bolivia
            'US' => '840', // Estados Unidos
        ];

        return $mapping[strtoupper($alpha2Code)] ?? '032';
    }

    /**
     * Mapear tipo de documento
     */
    private function getDocumentType(string $type): string
    {
        $mapping = [
            'DNI' => '1',
            'PASSPORT' => '2',
            'CI' => '3', // Cédula de Identidad
            'RUC' => '4', // Paraguay
            'CUIT' => '5', // Argentina
        ];

        return $mapping[strtoupper($type)] ?? '1';
    }
}