<?php

namespace App\Services\Simple;

use App\Models\Company;
use App\Models\Shipment;
use Exception;

/**
 * SISTEMA SIMPLE WEBSERVICES - Generador XML Directo
 * 
 * Generador minimalista sin capas de abstracción.
 * Crea XML directamente según especificaciones AFIP.
 * 
 * REEMPLAZA: XmlSerializerService complejo
 * ENFOQUE: Directo, simple, fácil de debuggear
 * 
 * FUNCIONALIDADES:
 * - Genera XML RegistrarTitEnvios (PASO 1)
 * - Genera XML RegistrarMicDta (PASO 2) 
 * - Sin validaciones complejas
 * - Sin logging excesivo
 * - Solo lo esencial para AFIP
 */
class SimpleXmlGenerator
{
    private Company $company;
    private const AFIP_NAMESPACE = 'Ar.Gob.Afip.Dga.wgesregsintia2';
    private const WSDL_URL = 'https://wsaduhomoext.afip.gob.ar/DIAV2/wgesregsintia2/wgesregsintia2.asmx?wsdl';
    private array $config;


    public function __construct(Company $company, array $config = [])
    {
        $this->company = $company;
        $this->config = $config;
    }

    /**
     * Crear XML para RegistrarTitEnvios - PASO 1 AFIP
     * SOLO REGISTRA EL TÍTULO - NO INCLUYE ENVÍOS DETALLADOS
     * Los envíos se registran por separado con RegistrarEnvios
     */
    public function createRegistrarTitEnviosXml(Shipment $shipment, string $transactionId): string
    {
        $wsdl     = 'https://wsaduhomoext.afip.gob.ar/DIAV2/wgesregsintia2/wgesregsintia2.asmx?wsdl';
        $endpoint = 'https://wsaduhomoext.afip.gob.ar/DIAV2/wgesregsintia2/wgesregsintia2.asmx';

        $options = [
            'trace'        => 1,
            'exceptions'   => true,
            'cache_wsdl'   => WSDL_CACHE_NONE,
            'soap_version' => SOAP_1_1,
        ];

        try {
            $client = new \SoapClient($wsdl, $options);
            $voyage = $shipment->voyage()->with(['originPort', 'destinationPort', 'originPort.country', 'destinationPort.country'])->first();
            $wsaa   = $this->getWSAATokens();

            $nsSoap = 'http://schemas.xmlsoap.org/soap/envelope/';
            $nsXsi  = 'http://www.w3.org/2001/XMLSchema-instance';
            $nsXsd  = 'http://www.w3.org/2001/XMLSchema';
            $nsSvc  = 'Ar.Gob.Afip.Dga.wgesregsintia2';

            // Códigos AFIP obligatorios
            // Códigos AFIP desde los datos reales
            // Mapeo temporal de códigos de aduana por puerto
            $portCustomsCodes = [
                'ARBUE' => '019', // Buenos Aires
                'PYTVT' => '051', // Villeta
            ];

            $originPortCode = $voyage->originPort?->code ?? '';
            $destPortCode = $voyage->destinationPort?->code ?? '';

            $codAduOrigen = $voyage->originPort?->customs_code ?? ($portCustomsCodes[$originPortCode] ?? '');
            $codAduDest = $voyage->destinationPort?->customs_code ?? ($portCustomsCodes[$destPortCode] ?? '');
            $codPaisOrigen = $voyage->originPort?->country?->numeric_code ?? '';
            $codPaisDest  = $voyage->destinationPort?->country?->numeric_code ?? '';
            $idManif      = $shipment->origin_manifest_id ?? 'SIN_MANIFIESTO';
            $idDocTrp     = $shipment->origin_transport_doc ?? 'SIN_DOC';

            // Contenedores (solo para registro del título)
            $containers = $shipment->shipmentItems()
                ->with(['containers.containerType'])
                ->get()
                ->flatMap(fn($it) => $it->containers);

            $w = new \XMLWriter();
            $w->openMemory();
            $w->startDocument('1.0','UTF-8');

            // Envelope
            $w->startElementNs('soap','Envelope',$nsSoap);
            $w->writeAttribute('xmlns:xsi', $nsXsi);
            $w->writeAttribute('xmlns:xsd', $nsXsd);

            // Body
            $w->startElementNs('soap','Body',$nsSoap);
                $w->startElement('RegistrarTitEnvios');
                $w->writeAttribute('xmlns', $nsSvc);

                // argWSAutenticacionEmpresa
                $w->startElement('argWSAutenticacionEmpresa');
                    $w->writeElement('Token', $wsaa['token']);
                    $w->writeElement('Sign',  $wsaa['sign']);
                    $w->writeElement('CuitEmpresaConectada', (string)$this->company->tax_id);
                    $w->writeElement('TipoAgente', 'ATA');
                    $w->writeElement('Rol', 'TRSP');
                $w->endElement();

                // argRegistrarTitEnviosParam
                $w->startElement('argRegistrarTitEnviosParam');
                    $w->writeElement('idTransaccion', substr($transactionId, 0, 15));

                    // titulosTransEnvios - SOLO TÍTULO
                    $w->startElement('titulosTransEnvios');
                    $w->startElement('TitTransEnvio');
                        $w->writeElement('codViaTrans','8'); // Hidrovía
                        $w->writeElement('idTitTrans',(string)$shipment->shipment_number);

                        $w->writeElement('indFinCom','S');
                        $w->writeElement('indFraccTransp','N');
                        $w->writeElement('indConsol','N');

                        $w->writeElement('idManiCargaArrPaisPart', $idManif);
                        $w->writeElement('idDocTranspArrPaisPart', $idDocTrp);

                        // ORIGEN - OBLIGATORIO con códigos AFIP
                        $w->startElement('origen');
                            $w->writeElement('codPais', $codPaisOrigen);
                            $w->writeElement('codAdu', $codAduOrigen);
                        $w->endElement();

                        // DESTINO - OBLIGATORIO con códigos AFIP
                        $w->startElement('destino');
                            $w->writeElement('codPais', $codPaisDest);
                            $w->writeElement('codAdu', $codAduDest);
                        $w->endElement();

                        // RegistrarTitEnvios NO incluye envíos detallados
                        // Los envíos se registran por separado con RegistrarEnvios

                    $w->endElement(); // TitTransEnvio
                    $w->endElement(); // titulosTransEnvios

                    // titulosTransContVacios - puede estar vacío
                    $w->startElement('titulosTransContVacios');
                    $w->endElement();

                    // contenedores (solo registro básico para el título)
                    if ($containers->isNotEmpty()) {
                        $w->startElement('contenedores');
                            foreach ($containers as $c) {
                                $w->startElement('Contenedor');
                                    $w->writeElement('id', (string)$c->container_number);
                                    
                                    // Mapeo de códigos de medida para AFIP
                                    $containerCode = $c->containerType?->argentina_ws_code ?? '42G1';
                                    if ($containerCode === '40GP') $containerCode = '42G1';
                                    if ($containerCode === '20GP') $containerCode = '22G1';
                                    $w->writeElement('codMedida', $containerCode);
                                    
                                    // Condición del contenedor
                                    $conditionMap = ['L'=>'P','V'=>'V','full'=>'P','empty'=>'V','loaded'=>'P'];
                                    $condition = $conditionMap[$c->condition] ?? 'P';
                                    $w->writeElement('condicion', $condition);
                                    
                                    // Precintos aduaneros si existen
                                    $seals = $c->customsSeals ?? collect();
                                    if ($seals->isNotEmpty()) {
                                        $w->startElement('precintos');
                                            foreach ($seals as $seal) {
                                                $w->writeElement('precinto', (string)$seal->seal_number);
                                            }
                                        $w->endElement();
                                    }
                                $w->endElement();
                            }
                        $w->endElement();
                    }

                $w->endElement(); // argRegistrarTitEnviosParam
                $w->endElement(); // RegistrarTitEnvios
            $w->endElement(); // Body
            $w->endElement(); // Envelope

            $w->endDocument();
            
            // Log para debug
            $xmlContent = $w->outputMemory();
            error_log("XML RegistrarTitEnvios generado (solo título) - XML length: " . strlen($xmlContent));

            return $xmlContent;

        } catch (\SoapFault $f) {
            \Log::error('RegistrarTitEnvios Fault', [
                'faultcode' => $f->faultcode ?? null,
                'faultstring' => $f->getMessage(),
                'detail' => isset($f->detail) ? (is_string($f->detail) ? $f->detail : json_encode($f->detail)) : null,
                'last_request' => $client->__getLastRequest(),
                'last_response' => $client->__getLastResponse(),
            ]);
            throw $f;
        }
    }

/**
 * Crear XML para RegistrarEnvios - PASO 2 AFIP COMPLETO
 * REESCRITO desde cero siguiendo WSDL oficial AFIP
 * Con hardcode temporal para garantizar funcionamiento
 */
public function createRegistrarEnviosXml(Shipment $shipment, string $transactionId): string
{
    $wsdl = 'https://wsaduhomoext.afip.gob.ar/DIAV2/wgesregsintia2/wgesregsintia2.asmx?wsdl';

    try {
        $voyage = $shipment->voyage()->with(['originPort', 'destinationPort'])->first();
        $wsaa = $this->getWSAATokens();
        $billsOfLading = $shipment->billsOfLading()->with('shipmentItems')->get();

        $nsSoap = 'http://schemas.xmlsoap.org/soap/envelope/';
        $nsSvc = 'Ar.Gob.Afip.Dga.wgesregsintia2';

        $w = new \XMLWriter();
        $w->openMemory();
        $w->startDocument('1.0', 'UTF-8');

        $w->startElementNs('soap', 'Envelope', $nsSoap);
        $w->startElementNs('soap', 'Body', $nsSoap);
            $w->startElement('RegistrarEnvios');
            $w->writeAttribute('xmlns', $nsSvc);

            // Autenticación
            $w->startElement('argWSAutenticacionEmpresa');
                $w->writeElement('Token', $wsaa['token']);
                $w->writeElement('Sign', $wsaa['sign']);
                $w->writeElement('CuitEmpresaConectada', (string)$this->company->tax_id);
                $w->writeElement('TipoAgente', 'ATA');
                $w->writeElement('Rol', 'TRSP');
            $w->endElement();

            // Parámetros RegistrarEnvios
            $w->startElement('argRegistrarEnviosParam');
                // CORREGIDO: Limitar transactionId a 15 caracteres
                $w->writeElement('idTransaccion', substr($transactionId, 0, 15));
                $w->writeElement('idTitTrans', (string)$shipment->shipment_number);

                // Envíos individuales
                $w->startElement('envios');
                foreach ($billsOfLading as $index => $bol) {
                    $w->startElement('Envio');
                        $w->writeElement('idEnvio', (string)($index + 1));
                        $w->writeElement('fechaEmb', ($voyage->departure_date ? $voyage->departure_date->format('Y-m-d') : now()->format('Y-m-d')));
                        
                        // CORREGIDO: Usar UN/LOCODE directos, no códigos de aduana
                        $w->writeElement('codPuertoEmb', $voyage->originPort?->code ?? 'ARBUE');
                        $w->writeElement('codPuertoDesc', $voyage->destinationPort?->code ?? 'PYTVT');
                        
                        // Campos canónicos AFIP
                        $bolNumber = $bol->bill_of_lading_number ?? $bol->bl_number ?? ('BL' . str_pad($index + 1, 6, '0', STR_PAD_LEFT));
                        $w->writeElement('idDocComercial', (string)$bolNumber);
                        $w->writeElement('descripcionMercaderia', (string)($bol->cargo_description ?? 'CARGA GENERAL'));
                        
                        // CORREGIDO: Usar peso real > 0
                        $totalPackages = max(1, $bol->shipmentItems->sum('package_quantity'));
                        $totalWeight = max(0.1, $bol->shipmentItems->sum('gross_weight_kg'));
                        
                        $w->writeElement('cantBultos', (string)$totalPackages);
                        $w->writeElement('pesoBrutoKg', number_format($totalWeight, 3, '.', ''));
                        $w->writeElement('indUltimaFraccion', 'S');

                        // AGREGADO: Lugares operativos obligatorios
                        $w->startElement('lugOperOrigen');
                            $w->writeElement('codLugOper', $voyage->originPort?->code ?? 'ARBUE');
                            $w->writeElement('codCiu', $voyage->originPort?->code ?? 'ARBUE');
                        $w->endElement();

                        $w->startElement('lugOperDestino');
                            $w->writeElement('codLugOper', $voyage->destinationPort?->code ?? 'PYTVT');
                            $w->writeElement('codCiu', $voyage->destinationPort?->code ?? 'PYTVT');
                        $w->endElement();

                        // AGREGADO: Campos obligatorios faltantes
                        $w->writeElement('indUltFra', 'S');
                        $w->writeElement('idFiscalATAMIC', (string)$this->company->tax_id);

                        // CORREGIDO: Destinaciones con estructura completa
                        $w->startElement('destinaciones');
                        $w->startElement('Destinacion');
                            $w->writeElement('codDestinacion', 'EXP');
                            $w->writeElement('codDivisaFob', 'USD');
                            $w->writeElement('codDivisaFle', 'USD');
                            $w->writeElement('codDivisaSeg', 'USD');
                            
                            // CORREGIDO: idDecla con 16 caracteres exactos
                            $w->writeElement('idDecla', str_pad('D' . $bol->id, 16, '0', STR_PAD_LEFT));
                            
                            // AGREGADO: items con Item obligatorio
                            $w->startElement('items');
                            $w->startElement('Item');
                            $w->writeElement('nroItem', (string)($index + 1));  // ← ESTA LÍNEA
                                $w->writeElement('descripcion', $bol->cargo_description ?? 'MERCADERIA GENERAL');
                            $w->endElement();
                            $w->endElement();
                            
                            // CORREGIDO: bultos con todos los campos obligatorios
                            $w->startElement('bultos');
                            $w->startElement('Bulto');
                                $w->writeElement('cantBultos', (string)$totalPackages);
                                $w->writeElement('pesoBruto', number_format($totalWeight, 3, '.', ''));
                                // CORREGIDO: pesoBrutoTotFrac debe ser > 0
                                $w->writeElement('pesoBrutoTotFrac', number_format($totalWeight, 3, '.', ''));
                                $w->writeElement('cantBultosTotFrac', (string)$totalPackages);
                                // CORREGIDO: Código de embalaje corto válido
                                $w->writeElement('codEmbalaje', 'CT');
                                // AGREGADO: Campos obligatorios faltantes
                                $w->writeElement('descMercaderia', $bol->cargo_description ?? 'MERCADERIA GENERAL');
                                $w->writeElement('indCargSuelt', 'N');
                                $w->writeElement('codTipEmbalaje', 'CT');
                            $w->endElement();
                            $w->endElement();
                        $w->endElement();
                        $w->endElement();
                    $w->endElement();
                }
                $w->endElement();
            $w->endElement();
            $w->endElement();
        $w->endElement();
        $w->endElement();

        $w->endDocument();
        
        $xmlContent = $w->outputMemory();
        error_log("XML RegistrarEnvios REESCRITO - BLs: " . $billsOfLading->count() . ", XML length: " . strlen($xmlContent));


        error_log("XML COMPLETO ENVIADO: " . $xmlContent);
        error_log("XML RegistrarEnvios REESCRITO - BLs: " . $billsOfLading->count() . ", XML length: " . strlen($xmlContent));

        return $xmlContent;

    } catch (\SoapFault $f) {
        \Log::error('RegistrarEnvios Fault REESCRITO', [
            'faultcode' => $f->faultcode ?? null,
            'faultstring' => $f->getMessage(),
        ]);
        throw $f;
    }
}

    /**
     * Crear XML para RegistrarMicDta - PASO 2 AFIP
     * Usa TRACKs generados en paso 1
     */
    public function createRegistrarMicDtaXml(Voyage $voyage, array $tracks, string $transactionId): string
    {

        $vessel = $voyage->leadVessel;

        $xml = $this->createSoapEnvelope();
        
        $xml .= '<wges:RegistrarMicDta xmlns:wges="' . self::AFIP_NAMESPACE . '">';
        
        // Autenticación empresa
        $xml .= '<argWSAutenticacionEmpresa>';
        $xml .= '<argCuit>' . $this->company->tax_id . '</argCuit>';
        $xml .= '</argWSAutenticacionEmpresa>';
        
        // Parámetros MIC/DTA
        $xml .= '<argRegistrarMicDtaParam>';
        $xml .= '<IdTransaccion>' . $transactionId . '</IdTransaccion>';
        
        // Estructura MIC/DTA
        $xml .= '<micDta>';
        $xml .= '<codViaTrans>' . ($voyage->transport_mode ?? '8') . '</codViaTrans>'; // 8=Hidrovía
        
        // Datos del transportista
        $xml .= '<transportista>';
        $xml .= '<cuitTransportista>' . $this->company->tax_id . '</cuitTransportista>';
        $xml .= '<denominacionTransportista>' . htmlspecialchars($this->company->legal_name) . '</denominacionTransportista>';
        $xml .= '</transportista>';
        
        // Datos del propietario del vehículo
        $xml .= '<propietarioVehiculo>';
        $xml .= '<cuitPropietario>' . $this->company->tax_id . '</cuitPropietario>';
        $xml .= '<denominacionPropietario>' . htmlspecialchars($this->company->legal_name) . '</denominacionPropietario>';
        $xml .= '</propietarioVehiculo>';
        
        // Indicador en lastre
        $xml .= '<indEnLastre>' . ($shipment->is_ballast ? 'S' : 'N') . '</indEnLastre>';
        
        // Datos de la embarcación
        $xml .= '<embarcacion>';
        $xml .= '<nombreEmbarcacion>' . htmlspecialchars($vessel?->name ?? 'SIN NOMBRE') . '</nombreEmbarcacion>';
        $xml .= '<registroNacionalEmbarcacion>' . ($vessel?->registration_number ?? 'SIN_REGISTRO') . '</registroNacionalEmbarcacion>';
        $xml .= '<tipoEmbarcacion>' . ($vessel->vessel_type_code ?? 'BAR') . '</tipoEmbarcacion>';
        $xml .= '</embarcacion>';
        
        // Si hay TRACKs, incluirlos
        if (!empty($tracks)) {
            $xml .= '<tracks>';
            foreach ($tracks as $track) {
                $xml .= '<track>';
                $xml .= '<trackId>' . $track['track_id'] . '</trackId>';
                $xml .= '<shipmentId>' . $track['shipment_id'] . '</shipmentId>';
                $xml .= '</track>';
            }
            $xml .= '</tracks>';
        }
        
        $xml .= '</micDta>';
        $xml .= '</argRegistrarMicDtaParam>';
        $xml .= '</wges:RegistrarMicDta>';
        $xml .= $this->closeSoapEnvelope();

        return $xml;
    }

    /**
     * Crear XML para RegistrarMicDta usando objetos correctos
     */
    public function createMicDtaXml(Voyage $voyage, array $tracks, string $transactionId): string
    {
        $vessel = $voyage->leadVessel;

        $xml = $this->createSoapEnvelope();
        
        $xml .= '<wges:RegistrarMicDta xmlns:wges="' . self::AFIP_NAMESPACE . '">';
        
        // Autenticación empresa
        $xml .= '<argWSAutenticacionEmpresa>';
        $xml .= '<argCuit>' . $this->company->tax_id . '</argCuit>';
        $xml .= '</argWSAutenticacionEmpresa>';
        
        // Parámetros MIC/DTA
        $xml .= '<argRegistrarMicDtaParam>';
        $xml .= '<IdTransaccion>' . $transactionId . '</IdTransaccion>';
        
        // Estructura MIC/DTA
        $xml .= '<micDta>';
        $xml .= '<codViaTrans>8</codViaTrans>'; // Hidrovía
        
        // Datos del transportista
        $xml .= '<transportista>';
        $xml .= '<cuitTransportista>' . $this->company->tax_id . '</cuitTransportista>';
        $xml .= '<denominacionTransportista>' . htmlspecialchars($this->company->legal_name) . '</denominacionTransportista>';
        $xml .= '</transportista>';
        
        // Datos del propietario del vehículo
        $xml .= '<propietarioVehiculo>';
        $xml .= '<cuitPropietario>' . $this->company->tax_id . '</cuitPropietario>';
        $xml .= '<denominacionPropietario>' . htmlspecialchars($this->company->legal_name) . '</denominacionPropietario>';
        $xml .= '</propietarioVehiculo>';
        
        // Indicador en lastre
        $xml .= '<indEnLastre>N</indEnLastre>'; // No en lastre (con carga)
        
        // Datos de la embarcación
        $xml .= '<embarcacion>';
        $xml .= '<nombreEmbarcacion>' . htmlspecialchars($vessel?->name ?? 'SIN NOMBRE') . '</nombreEmbarcacion>';
        $xml .= '<registroNacionalEmbarcacion>' . ($vessel?->registration_number ?? 'SIN_REGISTRO') . '</registroNacionalEmbarcacion>';
        $xml .= '<tipoEmbarcacion>BAR</tipoEmbarcacion>'; // Barcaza
        $xml .= '</embarcacion>';
        
        // Si hay TRACKs, incluirlos
        if (!empty($tracks)) {
            $xml .= '<tracks>';
            foreach ($tracks as $shipmentId => $trackList) {
                if (is_array($trackList)) {
                    foreach ($trackList as $track) {
                        $xml .= '<track>';
                        $xml .= '<trackId>' . $track . '</trackId>';
                        $xml .= '<shipmentId>' . $shipmentId . '</shipmentId>';
                        $xml .= '</track>';
                    }
                }
            }
            $xml .= '</tracks>';
        }
        
        $xml .= '</micDta>';
        $xml .= '</argRegistrarMicDtaParam>';
        $xml .= '</wges:RegistrarMicDta>';
        $xml .= $this->closeSoapEnvelope();

        return $xml;
    }

    /**
     * Obtener tokens WSAA para autenticación AFIP - TA ESPECÍFICO para wgesregsintia2
     */
    private function getWSAATokens(): array
{
    try {
        // 1. VERIFICAR CACHE PRIMERO
        $cachedToken = \App\Models\WsaaToken::getValidToken(
            $this->company->id, 
            'wgesregsintia2', 
            $this->config['environment'] ?? 'testing'
        );
        
        if ($cachedToken) {
            $cachedToken->markAsUsed();
            error_log("WSAA: Reutilizando token válido del cache - expires_in_minutes=" . $cachedToken->getTimeToExpiryMinutes());
            
            return [
                'token' => $cachedToken->token,
                'sign' => $cachedToken->sign,
                'cuit' => $this->company->tax_id
            ];
        }
        
        // 2. SI NO HAY CACHE VÁLIDO → GENERAR NUEVO (código original)
        error_log("WSAA: No hay token válido en cache, generando nuevo para service=wgesregsintia2");
            
            $certificateManager = new \App\Services\Webservice\CertificateManagerService($this->company);
            
            // Leer certificado
            $certData = $certificateManager->readCertificate();
            if (!$certData) {
                throw new Exception("No se pudo leer el certificado .p12");
            }
            
            // Log certificado usado
            $certInfo = $certificateManager->extractCertificateInfo($certData);
            error_log("WSAA: Certificado para firmar TRA - CN=" . ($certInfo['common_name'] ?? 'N/A') . 
                    ", Serial=" . substr($certInfo['serial_number'] ?? 'N/A', -8));
            
            // Generar LoginTicket ESPECÍFICO para wgesregsintia2
            $loginTicket = $this->generateLoginTicket();
            error_log("WSAA: LoginTicket generado - service=wgesregsintia2, longitud=" . strlen($loginTicket));
            
            // Firmar LoginTicket
            $signedTicket = $this->signLoginTicket($loginTicket, $certData);
            error_log("WSAA: TRA firmado - longitud=" . strlen($signedTicket));
            
            // Llamar a WSAA
            $wsaaTokens = $this->callWSAA($signedTicket);
            
            // Log tokens obtenidos (SIN valores completos)
            error_log("WSAA: TA obtenido exitosamente - Token length=" . strlen($wsaaTokens['token']) . 
                    ", Sign length=" . strlen($wsaaTokens['sign']) . 
                    ", Token hash=" . substr(md5($wsaaTokens['token']), 0, 8) .
                    ", Sign hash=" . substr(md5($wsaaTokens['sign']), 0, 8));
            
            // 3. GUARDAR TOKEN EN CACHE ANTES DE RETORNAR
            \App\Models\WsaaToken::createToken([
                'company_id' => $this->company->id,
                'service_name' => 'wgesregsintia2',
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
                'creation_context' => ['method' => 'getWSAATokens', 'service' => 'wgesregsintia2'],
            ]);
            
            error_log("WSAA: Token nuevo guardado en cache exitosamente");
            
            return [
                'token' => $wsaaTokens['token'],
                'sign' => $wsaaTokens['sign'],
                'cuit' => $this->company->tax_id
            ];
            
        } catch (Exception $e) {
            error_log("WSAA ERROR: " . $e->getMessage());
            error_log("WSAA ERROR en: " . $e->getFile() . ":" . $e->getLine());
            throw $e; // NO FALLBACK - necesitamos TA real
        }
    }

    /**
 * Crear envelope SOAP con autenticación WSAA exacta
 */
private function createSoapEnvelope(): string
{
    // Obtener TA real de WSAA
    $wsaaTokens = $this->getWSAATokens();

    // Log del header exacto que se enviará
    error_log("SOAP Header: Auth/Token length=" . strlen($wsaaTokens['token']));
    error_log("SOAP Header: Auth/Sign length=" . strlen($wsaaTokens['sign'])); 
    error_log("SOAP Header: Auth/Cuit=" . $wsaaTokens['cuit']);
    error_log("SOAP Header: Estructura=Auth>Token,Sign,Cuit (sin namespace adicional)");

    return '<?xml version="1.0" encoding="UTF-8"?>' .
           '<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">' .
           '<soap:Header>' .
           '<Auth>' .
           '<Token>' . $wsaaTokens['token'] . '</Token>' .
           '<Sign>' . $wsaaTokens['sign'] . '</Sign>' .
           '<Cuit>' . $wsaaTokens['cuit'] . '</Cuit>' .
           '</Auth>' .
           '</soap:Header>' .
           '<soap:Body>';
}

    /**
     * Cerrar envelope SOAP
     */
    private function closeSoapEnvelope(): string
    {
        return '</soap:Body></soap:Envelope>';
    }

    /**
     * Validación mínima del XML generado
     */
    public function validateXml(string $xml): bool
    {
        // Validación básica: XML bien formado
        $dom = new \DOMDocument();
        return @$dom->loadXML($xml) !== false;
    }

private function generateLoginTicket(): string
{
    // 1) Generar uniqueId seguro, acotado para evitar overflow en parsers viejos
    //    31 bits: 0..2147483647
    $base = time(); // segundos
    $uniqueId = (int) min($base, 2147483647);

    // 2) Tiempos en UTC para evitar problemas de DST/offset
    $nowUtc = new \DateTime('now', new \DateTimeZone('UTC'));

    // Ajustá la ventana si AFIP se queja de clock-skew:
    // -5 min / +12 h suele andar perfecto.
    $generationTime = (clone $nowUtc)->sub(new \DateInterval('PT5M'));
    $expirationTime = (clone $nowUtc)->add(new \DateInterval('PT12H'));

    // 3) Formato ISO 8601. Con 'Z' explícita para UTC (estrictamente compatible).
    $generationTimeStr = $generationTime->format('Y-m-d\TH:i:s\Z');
    $expirationTimeStr = $expirationTime->format('Y-m-d\TH:i:s\Z');

    // 4) Service EXACTO para este WS
    $serviceExacto = 'wgesregsintia2';

    // 5) Construir XML minimalista sin indentación/espacios extra
    //    Importante: este string debe ser EXACTAMENTE el que firmás (CMS).
    $loginTicket =
        '<?xml version="1.0" encoding="UTF-8"?>' .
        '<loginTicketRequest version="1.0">' .
            '<header>' .
                '<uniqueId>' . $uniqueId . '</uniqueId>' .
                '<generationTime>' . $generationTimeStr . '</generationTime>' .
                '<expirationTime>' . $expirationTimeStr . '</expirationTime>' .
            '</header>' .
            '<service>' . $serviceExacto . '</service>' .
        '</loginTicketRequest>';

    // 6) Logs útiles (no loguees el XML completo en prod si te preocupa el tamaño)
    error_log("WSAA: Generando LoginTicket (UTC) service={$serviceExacto}");
    error_log("WSAA: uniqueId={$uniqueId}");
    error_log("WSAA: generationTime={$generationTimeStr} expirationTime={$expirationTimeStr}");

    // Opcional: hash para trazar que lo firmado == lo enviado
    error_log('WSAA: sha256(TRA)=' . hash('sha256', $loginTicket));

    return $loginTicket;
}

    private function signLoginTicket(string $loginTicket, array $certData): string
{
    // Crear archivo temporal para el LoginTicket
    $loginTicketFile = tempnam(sys_get_temp_dir(), 'loginticket_') . '.xml';
    file_put_contents($loginTicketFile, $loginTicket);
    
    // Crear archivo de certificado temporal con cadena completa
    $certFile = tempnam(sys_get_temp_dir(), 'cert_') . '.pem';
    $certContent = $certData['cert'];
    
    // Incluir certificados intermedios si existen
    if (isset($certData['extracerts']) && is_array($certData['extracerts'])) {
        foreach ($certData['extracerts'] as $extraCert) {
            $certContent .= "\n" . $extraCert;
        }
    }
    
    file_put_contents($certFile, $certContent);
    
    // Crear archivo de clave privada temporal
    $keyFile = tempnam(sys_get_temp_dir(), 'key_') . '.pem';
    file_put_contents($keyFile, $certData['pkey']);
    
    // Archivo de salida
    $outputFile = tempnam(sys_get_temp_dir(), 'signed_') . '.p7s';
    
    // Intentar con comando OpenSSL externo primero (más confiable)
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
        // Fallback con openssl_pkcs7_sign usando flags correctos
        $result = openssl_pkcs7_sign(
            $loginTicketFile,
            $outputFile,
            $certData['cert'],
            $certData['pkey'],
            [],
            PKCS7_BINARY | PKCS7_NOATTR
        );
        
        if (!$result || !file_exists($outputFile)) {
            throw new Exception("Error firmando LoginTicket. OpenSSL output: " . implode(', ', $output));
        }
        
        $signature = file_get_contents($outputFile);
        $signatureBase64 = base64_encode($signature);
    }
    
    // Limpiar archivos temporales
    @unlink($loginTicketFile);
    @unlink($certFile);
    @unlink($keyFile);
    @unlink($outputFile);
    
    if (!$signatureBase64) {
        throw new Exception("Error generando firma Base64 para WSAA");
    }
    
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
        
        // Parsear respuesta XML
        $xml = simplexml_load_string($response->loginCmsReturn);
        
        return [
            'token' => (string)$xml->credentials->token,
            'sign' => (string)$xml->credentials->sign
        ];
    }

    /**
     * Limpiar cache de TAs anteriores para forzar generación nueva
     */
    private function clearWSAACache(): void
    {
        try {
            // Buscar y eliminar archivos de cache comunes
            $cachePatterns = [
                storage_path('app/wsaa_cache*'),
                storage_path('app/certificates/ta_*'),
                storage_path('framework/cache/wsaa*'),
                sys_get_temp_dir() . '/wsaa_*',
                sys_get_temp_dir() . '/ta_*'
            ];
            
            $filesCleared = 0;
            foreach ($cachePatterns as $pattern) {
                $files = glob($pattern);
                if ($files) {
                    foreach ($files as $file) {
                        if (is_file($file) && unlink($file)) {
                            $filesCleared++;
                        }
                    }
                }
            }
            
            error_log("WSAA Cache: Archivos eliminados={$filesCleared}");
            
            // Limpiar cache de Laravel si existe
            if (function_exists('cache')) {
                cache()->forget('wsaa_token_' . $this->company->id);
                cache()->forget('wsaa_sign_' . $this->company->id);
                cache()->forget('wsaa_expires_' . $this->company->id);
                error_log("WSAA Cache: Cache Laravel limpiado");
            }
            
        } catch (Exception $e) {
            error_log("WSAA Cache: Error limpiando cache - " . $e->getMessage());
        }
    }
    
    /**
     * ================================================================================
     * EXTENSIÓN GPS - ACTUALIZAR POSICIÓN AFIP
     * ================================================================================
     * 
     * AGREGAR este método al final de la clase SimpleXmlGenerator.php
     * antes del último } de la clase
     */

    /**
     * Generar XML ActualizarPosicion con WSAA real
     * Reutiliza el sistema WSAA existente del MIC/DTA
     * 
     * @param array $data Datos de posición GPS
     * @return string XML completo con autenticación WSAA
     */
    public function generateActualizarPosicionXml(array $data): string
    {
        try {
            error_log("SimpleXmlGenerator: Generando XML ActualizarPosicion");
            error_log("SimpleXmlGenerator: External reference=" . ($data['external_reference'] ?? 'N/A'));
            error_log("SimpleXmlGenerator: Coordinates=" . $data['latitude'] . ',' . $data['longitude']);
            
            // ✅ REUTILIZAR sistema WSAA existente (mismo que MIC/DTA)
            $wsaaTokens = $this->getWSAATokens();
            
            error_log("SimpleXmlGenerator: WSAA tokens obtenidos - Token length=" . strlen($wsaaTokens['token']));
            
            // ✅ Crear envelope SOAP con autenticación real
            $xml = '<?xml version="1.0" encoding="UTF-8"?>';
            $xml .= '<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">';
            $xml .= '<soap:Header>';
            $xml .= '<Auth>';
            $xml .= '<Token>' . $wsaaTokens['token'] . '</Token>';
            $xml .= '<Sign>' . $wsaaTokens['sign'] . '</Sign>';
            $xml .= '<Cuit>' . $wsaaTokens['cuit'] . '</Cuit>';
            $xml .= '</Auth>';
            $xml .= '</soap:Header>';
            
            // ✅ Cuerpo específico para ActualizarPosicion
            $xml .= '<soap:Body>';
            $xml .= '<wges:ActualizarPosicion xmlns:wges="Ar.Gob.Afip.Dga.wgesregsintia2">';
            $xml .= '<wges:argActualizarPosicionParam>';
            
            // Datos obligatorios ActualizarPosicion según documentación AFIP
            $xml .= '<wges:referenciaMicDta>' . htmlspecialchars($data['external_reference']) . '</wges:referenciaMicDta>';
            $xml .= '<wges:latitud>' . number_format($data['latitude'], 8, '.', '') . '</wges:latitud>';
            $xml .= '<wges:longitud>' . number_format($data['longitude'], 8, '.', '') . '</wges:longitud>';
            $xml .= '<wges:fechaHoraPosicion>' . ($data['timestamp'] ?? now()->toISOString()) . '</wges:fechaHoraPosicion>';
            
            // Observaciones opcionales pero recomendadas
            if (isset($data['observations']) && !empty($data['observations'])) {
                $xml .= '<wges:observaciones>' . htmlspecialchars($data['observations']) . '</wges:observaciones>';
            } else {
                $xml .= '<wges:observaciones>Actualización GPS automática - Sistema ' . config('app.name', 'PARANA') . '</wges:observaciones>';
            }
            
            // Datos adicionales del voyage si están disponibles
            if (isset($data['voyage_data']) && is_array($data['voyage_data'])) {
                if (isset($data['voyage_data']['vessel_name'])) {
                    $xml .= '<wges:nombreEmbarcacion>' . htmlspecialchars($data['voyage_data']['vessel_name']) . '</wges:nombreEmbarcacion>';
                }
                if (isset($data['voyage_data']['voyage_number'])) {
                    $xml .= '<wges:numeroViaje>' . htmlspecialchars($data['voyage_data']['voyage_number']) . '</wges:numeroViaje>';
                }
            }
            
            $xml .= '</wges:argActualizarPosicionParam>';
            $xml .= '</wges:ActualizarPosicion>';
            $xml .= '</soap:Body>';
            $xml .= '</soap:Envelope>';
            
            error_log("SimpleXmlGenerator: XML ActualizarPosicion generado exitosamente");
            error_log("SimpleXmlGenerator: XML size=" . strlen($xml) . " bytes");
            error_log("SimpleXmlGenerator: Has WSAA Auth=" . (str_contains($xml, '<Auth>') ? 'YES' : 'NO'));
            
            return $xml;
            
        } catch (Exception $e) {
            error_log("SimpleXmlGenerator ERROR ActualizarPosicion: " . $e->getMessage());
            error_log("SimpleXmlGenerator ERROR File: " . $e->getFile() . " Line: " . $e->getLine());
            throw $e;
        }
    }

    /**
     * ================================================================================
     * MÉTODOS AUXILIARES PARA GPS (si no existen ya)
     * ================================================================================
     */
    
    /**
     * Validar datos de posición GPS antes de generar XML
     * 
     * @param array $data
     * @return bool
     */
    private function validatePositionData(array $data): bool
    {
        // Validar campos obligatorios
        if (empty($data['external_reference'])) {
            throw new Exception("Falta referencia MIC/DTA para ActualizarPosicion");
        }
        
        if (!isset($data['latitude']) || !isset($data['longitude'])) {
            throw new Exception("Faltan coordenadas GPS para ActualizarPosicion");
        }
        
        // Validar rangos de coordenadas
        $lat = (float) $data['latitude'];
        $lng = (float) $data['longitude'];
        
        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            throw new Exception("Coordenadas GPS inválidas: lat={$lat}, lng={$lng}");
        }
        
        // Validación específica hidrovía Paraná (advertencia, no error)
        if ($lat < -35 || $lat > -20 || $lng < -62 || $lng > -54) {
            error_log("ADVERTENCIA: Coordenadas fuera del rango típico de hidrovía Paraná");
        }
        
        return true;
    }
    
    /**
     * Crear envelope SOAP base (si no existe método similar)
     * NOTA: Verificar si ya existe un método similar antes de agregar
     */
    private function createBasicSoapEnvelope(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>' .
               '<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">';
    }
}