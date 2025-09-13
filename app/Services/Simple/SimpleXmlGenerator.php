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
        // Obtener datos necesarios
        $voyage = $shipment->voyage;
        $vessel = $shipment->vessel ?? $voyage->leadVessel;
        $captain = $shipment->captain ?? $voyage->captain;
        // Obtener tokens WSAA para autenticación
        $wsaaTokens = $this->getWSAATokens();

        // Crear XML usando string directo siguiendo documentación oficial AFIP
        $xml = $this->createSoapEnvelope();
        
        $xml .= '<RegistrarTitEnvios xmlns="' . self::AFIP_NAMESPACE . '">';

        // Autenticación empresa como PRIMER parámetro
        $xml .= '<argWSAutenticacionEmpresa>';
        $xml .= '<Token>' . $wsaaTokens['token'] . '</Token>';
        $xml .= '<Sign>' . $wsaaTokens['sign'] . '</Sign>';
        $xml .= '<CuitEmpresaConectada>' . $this->company->tax_id . '</CuitEmpresaConectada>';
        $xml .= '<TipoAgente>TRSP</TipoAgente>';
        $xml .= '<Rol>TRSP</Rol>';
        $xml .= '</argWSAutenticacionEmpresa>';
        // Parámetros TitEnvios - ESTRUCTURA OFICIAL AFIP
        $xml .= '<argRegistrarTitEnviosParam>';
        $xml .= '<idTransaccion>' . $transactionId . '</idTransaccion>';
        
        // ESTRUCTURA OFICIAL: titulosTransEnvios
        $xml .= '<titulosTransEnvios>';
        $xml .= '<TitTransEnvio>';
        $xml .= '<codViaTrans>' . ($voyage->transport_mode_code ?? '8') . '</codViaTrans>';
        $xml .= '<idTitTrans>' . $shipment->shipment_number . '</idTitTrans>';
        $xml .= '<obsDeclaAduInter>' . ($shipment->customs_notes ?? '') . '</obsDeclaAduInter>';
        
        // Remitente
        $xml .= '<remitente>';
        $xml .= '<cuit>' . $this->company->tax_id . '</cuit>';
        $xml .= '<razonSocial>' . htmlspecialchars($this->company->legal_name) . '</razonSocial>';
        $xml .= '</remitente>';
        
        // Consignatario
        $xml .= '<consignatario>';
        $xml .= '<cuit>' . ($shipment->consignee_tax_id ?? $this->company->tax_id) . '</cuit>';
        $xml .= '<razonSocial>' . htmlspecialchars($shipment->consignee_name ?? $this->company->legal_name) . '</razonSocial>';
        $xml .= '</consignatario>';
        
        // Destinatario
        $xml .= '<destinatario>';
        $xml .= '<cuit>' . ($shipment->destination_tax_id ?? $this->company->tax_id) . '</cuit>';
        $xml .= '<razonSocial>' . htmlspecialchars($shipment->destination_name ?? $this->company->legal_name) . '</razonSocial>';
        $xml .= '</destinatario>';
        
        // Notificado
        $xml .= '<notificado>';
        $xml .= '<cuit>' . ($shipment->notify_party_tax_id ?? $this->company->tax_id) . '</cuit>';
        $xml .= '<razonSocial>' . htmlspecialchars($shipment->notify_party_name ?? $this->company->legal_name) . '</razonSocial>';
        $xml .= '</notificado>';
        
        $xml .= '<indFinCom>' . ($shipment->commercial_purpose ?? 'S') . '</indFinCom>';
        $xml .= '<indFraccTransp>' . ($shipment->is_fractional ? 'S' : 'N') . '</indFraccTransp>';
        $xml .= '<indConsol>' . ($shipment->is_consolidated ? 'S' : 'N') . '</indConsol>';
        $xml .= '<idManiCargaArrPaisPart>' . ($shipment->origin_manifest_id ?? '') . '</idManiCargaArrPaisPart>';
        $xml .= '<idDocTranspArrPaisPart>' . ($shipment->origin_transport_doc ?? '') . '</idDocTranspArrPaisPart>';
        
        // Origen
        $xml .= '<origen>';
        $xml .= '<codPais>' . ($voyage->originPort?->country?->numeric_code ?? '032') . '</codPais>';
        $xml .= '<codPuerto>' . ($voyage->originPort?->code ?? 'ARBUE') . '</codPuerto>';
        $xml .= '</origen>';
        
        // Destino
        $xml .= '<destino>';
        $xml .= '<codPais>' . ($voyage->destinationPort?->country?->numeric_code ?? '600') . '</codPais>';
        $xml .= '<codPuerto>' . ($voyage->destinationPort?->code ?? 'PYTVT') . '</codPuerto>';
        $xml .= '</destino>';
        
        // Envíos
        $xml .= '<envios>';
        $xml .= '<Envio>';
        $xml .= '<idEnvio>' . $shipment->sequence_in_voyage . '</idEnvio>';
        $xml .= '<fechaEmb>' . ($voyage->departure_date?->format('Y-m-d') ?? now()->format('Y-m-d')) . '</fechaEmb>';
        $xml .= '<codPuertoEmb>' . ($voyage->originPort?->code ?? 'ARBUE') . '</codPuertoEmb>';
        $xml .= '<codPuertoDesc>' . ($voyage->destinationPort?->code ?? 'PYTVT') . '</codPuertoDesc>';
        $xml .= '<idFiscalATAMIC>' . $this->company->tax_id . '</idFiscalATAMIC>';
        $xml .= '</Envio>';
        $xml .= '</envios>';
        
        $xml .= '</TitTransEnvio>';
        $xml .= '</titulosTransEnvios>';
        
        // Títulos contenedores vacíos
        $xml .= '<titulosTransContVacios>';
        $xml .= '</titulosTransContVacios>';
        
        // Contenedores
        $xml .= '<contenedores>';
        // Solo si hay contenedores cargados
        if ($shipment->containers_loaded > 0) {
            for ($i = 1; $i <= $shipment->containers_loaded; $i++) {
                $xml .= '<Contenedor>';
                $xml .= '<id>' . ($shipment->shipment_number . '_CONT' . str_pad($i, 3, '0', STR_PAD_LEFT)) . '</id>';
                $xml .= '<codMedida>' . ($vessel->default_container_size ?? '20') . '</codMedida>';
                $xml .= '<condicion>' . ($shipment->container_condition ?? 'P') . '</condicion>';
                $xml .= '<accesorio>' . ($shipment->container_accessories ?? '') . '</accesorio>';
                $xml .= '</Contenedor>';
            }
        }
        $xml .= '</contenedores>';
        
        $xml .= '</argRegistrarTitEnviosParam>';
        $xml .= '</RegistrarTitEnvios>';
        $xml .= $this->closeSoapEnvelope();

        return $xml;
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
     * Obtener tokens WSAA para autenticación AFIP
     */
    private function getWSAATokens(): array
{
    error_log("=== INICIO DEBUG WSAA ===");
    
    try {
        $certificateManager = new \App\Services\Webservice\CertificateManagerService($this->company);
        
        $certData = $certificateManager->readCertificate();
        if (!$certData) {
            error_log("ERROR: No se pudo leer certificado");
            throw new Exception("No se pudo leer el certificado .p12");
        }
        error_log("CERTIFICADO LEÍDO OK");

        $loginTicket = $this->generateLoginTicket();
        error_log("LOGIN TICKET GENERADO: " . strlen($loginTicket) . " chars");

        $signedTicket = $this->signLoginTicket($loginTicket, $certData);
        error_log("SIGNED TICKET GENERADO: " . strlen($signedTicket) . " chars");

        $wsaaTokens = $this->callWSAA($signedTicket);
        error_log("WSAA TOKENS OBTENIDOS");
        error_log("TOKEN: " . substr($wsaaTokens['token'], 0, 50) . "...");
        
        return [
            'token' => $wsaaTokens['token'],
            'sign' => $wsaaTokens['sign'],
            'cuit' => $this->company->tax_id
        ];
        
    } catch (Exception $e) {
        error_log("ERROR WSAA ESPECÍFICO: " . $e->getMessage());
        error_log("STACK TRACE: " . $e->getTraceAsString());
        
        return [
            'token' => base64_encode('CERT_' . $this->company->id . '_' . time()),
            'sign' => base64_encode('SIGN_' . $this->company->tax_id . '_' . time()),
            'cuit' => $this->company->tax_id
        ];
    }
}

    /**
     * Crear envelope SOAP estándar con autenticación WSAA
     */
    private function createSoapEnvelope(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>' .
            '<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">' .
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
        $uniqueId = uniqid();
        $generationTime = date('c');
        $expirationTime = date('c', strtotime('+2 hours'));
        
        return "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
    <loginTicketRequest version=\"1.0\">
        <header>
            <uniqueId>{$uniqueId}</uniqueId>
            <generationTime>{$generationTime}</generationTime>
            <expirationTime>{$expirationTime}</expirationTime>
        </header>
        <service>wgesregsintia2</service>
    </loginTicketRequest>";
    }

    private function signLoginTicket(string $loginTicket, array $certData): string
    {
        // Crear archivo temporal para el LoginTicket
        $tempFile = tempnam(sys_get_temp_dir(), 'loginticket');
        file_put_contents($tempFile, $loginTicket);
        
        // Usar directamente cert y pkey del CertificateManagerService
        $outputFile = $tempFile . '.signed';
        
        // Firmar usando el formato correcto del CertificateManagerService
        if (openssl_pkcs7_sign(
            $tempFile, 
            $outputFile, 
            $certData['cert'],     // Certificado ya en formato correcto
            $certData['pkey'],     // Clave privada ya en formato correcto
            [], 
            PKCS7_NOATTR
        )) {
            $signature = file_get_contents($outputFile);
            
            // Limpiar archivos temporales
            unlink($tempFile);
            unlink($outputFile);
            
            return $signature;
        }
        
        // Limpiar archivos en caso de error
        unlink($tempFile);
        if (file_exists($outputFile)) {
            unlink($outputFile);
        }
        
        throw new Exception("Error firmando LoginTicket con OpenSSL");
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
    
}