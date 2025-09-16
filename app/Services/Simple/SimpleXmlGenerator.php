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
     * Genera TRACKs necesarios para MIC/DTA
     */
    public function createRegistrarTitEnviosXml(Shipment $shipment, string $transactionId): string
    {
        $wsdl     = 'https://wsaduhomoext.afip.gob.ar/DIAV2/wgesregsintia2/wgesregsintia2.asmx?wsdl';
        $endpoint = 'https://wsaduhomoext.afip.gob.ar/DIAV2/wgesregsintia2/wgesregsintia2.asmx';

        $options = [
            'trace'        => 1,          // para __getLastRequest/Response
            'exceptions'   => true,       // lanzar SoapFault
            'cache_wsdl'   => WSDL_CACHE_NONE,
            'soap_version' => SOAP_1_1,   // según binding del WSDL
        ];
        try {

            $client = new \SoapClient($wsdl, $options);
            $voyage = $shipment->voyage;
            $wsaa   = $this->getWSAATokens(); // ['token','sign','cuit']

            $nsSoap = 'http://schemas.xmlsoap.org/soap/envelope/';
            $nsXsi  = 'http://www.w3.org/2001/XMLSchema-instance';
            $nsXsd  = 'http://www.w3.org/2001/XMLSchema';
            $nsSvc  = 'Ar.Gob.Afip.Dga.wgesregsintia2';

            // Ajustá estos campos a tus models/datos reales
            $codAduOrigen = (string)($voyage->originPort?->customs_code ?? '');     // ej "019"
            $codPaisDest  = (string)($voyage->destinationPort?->country?->numeric_code ?? '600');
            $codAduDest   = (string)($voyage->destinationPort?->customs_code ?? ''); // ej "051"
            $idManif      = (string)($shipment->origin_manifest_id   ?? 'SIN_MANIFIESTO');
            $idDocTrp     = (string)($shipment->origin_transport_doc ?? 'SIN_DOC');

            // Contenedores (juntá todos)
            $containers = $shipment->shipmentItems()
                ->with(['containers.containerType', 'containers.seals'])
                ->get()
                ->flatMap(fn($it) => $it->containers);

            $w = new \XMLWriter();
            $w->openMemory();
            $w->startDocument('1.0','UTF-8');

            // Envelope
            $w->startElementNs('soap','Envelope',$nsSoap);
            $w->writeAttribute('xmlns:xsi', $nsXsi);
            $w->writeAttribute('xmlns:xsd', $nsXsd);

            /* // Header Sacamos el header por error 7007
            $w->startElementNs('soap','Header',$nsSoap);
                $w->startElement('Auth');              // sin prefijo
                $w->writeAttribute('xmlns',$nsSvc);  // namespace del servicio
                
                $w->writeElement('Cuit',  (string)$this->company->tax_id);
                $w->endElement(); // Auth
            $w->endElement(); // Header */

            // Body
            $w->startElementNs('soap','Body',$nsSoap);
                $w->startElement('RegistrarTitEnvios');
                $w->writeAttribute('xmlns', $nsSvc);

                // argWSAutenticacionEmpresa
                $w->startElement('argWSAutenticacionEmpresa');
                    $w->writeElement('Token', $wsaa['token']);
                    $w->writeElement('Sign',  $wsaa['sign']);
                    $w->writeElement('CuitEmpresaConectada', (string)$this->company->tax_id); // s:long
                    $w->writeElement('TipoAgente', 'ATA');
                    $w->writeElement('Rol', 'TRSP');
                $w->endElement();

                // argRegistrarTitEnviosParam
                $w->startElement('argRegistrarTitEnviosParam');
                    $w->writeElement('idTransaccion', (string)$transactionId);

                    // titulosTransEnvios
                    $w->startElement('titulosTransEnvios');
                    $w->startElement('TitTransEnvio');
                        $w->writeElement('codViaTrans','8'); // int
                        $w->writeElement('idTitTrans',(string)$shipment->shipment_number);

                        $w->writeElement('indFinCom','S');
                        $w->writeElement('indFraccTransp','N');
                        $w->writeElement('indConsol','N');

                        $w->writeElement('idManiCargaArrPaisPart', $idManif);
                        $w->writeElement('idDocTranspArrPaisPart', $idDocTrp);

                        if ($codAduOrigen !== '') {
                        $w->startElement('origen');
                            $w->writeElement('codAdu', $codAduOrigen);
                        $w->endElement();
                        }

                        if ($codPaisDest !== '' || $codAduDest !== '') {
                        $w->startElement('destino');
                            if ($codPaisDest !== '') $w->writeElement('codPais', $codPaisDest);
                            if ($codAduDest  !== '') $w->writeElement('codAdu',  $codAduDest);
                        $w->endElement();
                        }

                        // envios (obligatorio idEnvio)
                        $w->startElement('envios');
                        $w->startElement('Envio');
                            $w->writeElement('idEnvio','1'); // int requerido
                            $w->writeElement('idFiscalATAMIC', (string)$this->company->tax_id); // opcional
                        $w->endElement();
                        $w->endElement();

                    $w->endElement(); // TitTransEnvio
                    $w->endElement();   // titulosTransEnvios

                    // contenedores (solo si hay)
                    if ($containers->isNotEmpty()) {
                    $w->startElement('contenedores');
                        foreach ($containers as $c) {
                        $w->startElement('Contenedor');
                            $w->writeElement('id',        (string)$c->container_number);
                            $w->writeElement('codMedida', (string)($c->containerType?->argentina_ws_code ?? '42G1'));
                            $map = ['L'=>'P','V'=>'V','full'=>'P','empty'=>'V'];
                            $w->writeElement('condicion', (string)($map[$c->condition] ?? $c->condition));
                            if ($c->seals && $c->seals->isNotEmpty()) {
                            $w->startElement('precintos'); // ArrayOfString2
                                foreach ($c->seals as $s) {
                                $w->writeElement('precinto', (string)$s->seal_number);
                                }
                            $w->endElement();
                            }
                        $w->endElement();
                        }
                    $w->endElement();
                    }

                $w->endElement(); // argRegistrarTitEnviosParam
                $w->endElement();   // RegistrarTitEnvios
            $w->endElement();     // Body
            $w->endElement();       // Envelope

            $w->endDocument();
            return $w->outputMemory();

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
        // LIMPIAR CACHE ANTES DE GENERAR NUEVO TA
        $this->clearWSAACache();
        try {
            error_log("WSAA: Iniciando obtención TA para service=wgesregsintia2");
            
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
    
}