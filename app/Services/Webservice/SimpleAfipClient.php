<?php

namespace App\Services\Webservice;

use SoapClient;
use Exception;
use App\Models\Shipment;

/**
 * Cliente AFIP Ultra-Simplificado
 * Sin abstracciones, sin complejidad, solo funcionalidad básica
 */
class SimpleAfipClient
{
    private string $wsdlUrl = 'https://wsaduhomoext.afip.gob.ar/DIAV2/wgesregsintia2/wgesregsintia2.asmx?wsdl';
    private string $cuit;
    
    public function __construct(string $cuit)
    {
        $this->cuit = preg_replace('/[^0-9]/', '', $cuit);
    }
    
    /**
     * Registrar títulos y envíos - DIRECTO
     */
    public function registrarTitEnvios(Shipment $shipment): array
    {
        try {
            // 1. Crear XML simple
            $xml = $this->createSimpleXML($shipment);
            
            // 2. Enviar directo a AFIP
            $response = $this->sendToAfip($xml, 'RegistrarTitEnvios');
            
            return [
                'success' => true,
                'xml_sent' => $xml,
                'response' => $response,
                'tracks' => $this->extractTracks($response)
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'xml_sent' => $xml ?? null
            ];
        }
    }
    
    /**
     * Crear XML mínimo válido para AFIP
     */
    private function createSimpleXML(Shipment $shipment): string
    {
        $transactionId = 'TEST_' . time();
        
        return '<?xml version="1.0" encoding="UTF-8"?>
<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
  <soap:Body>
    <RegistrarTitEnvios xmlns="http://schemas.afip.gob.ar/wgesregsintia2/v1">
      <argWSAutenticacionEmpresa>
        <CuitEmpresaConectada>' . $this->cuit . '</CuitEmpresaConectada>
        <TipoAgente>TRSP</TipoAgente>
        <Rol>TRSP</Rol>
      </argWSAutenticacionEmpresa>
      <argRegistrarTitEnviosParam>
        <IdTransaccion>' . $transactionId . '</IdTransaccion>
        <Titulo>
          <NumeroTitulo>TIT_' . $shipment->shipment_number . '</NumeroTitulo>
          <TipoTitulo>1</TipoTitulo>
          <IdentificadorViaje>' . str_pad($shipment->voyage->voyage_number, 16, '0', STR_PAD_LEFT) . '</IdentificadorViaje>
          <CuitAtaMt>' . $this->cuit . '</CuitAtaMt>
          <Transportista>
            <Nombre>MAERSK LINE ARGENTINA S.A.</Nombre>
            <Cuit>' . $this->cuit . '</Cuit>
            <Direccion>AV CORRIENTES 1234</Direccion>
          </Transportista>
          <Viaje>
            <NumeroViaje>' . $shipment->voyage->voyage_number . '</NumeroViaje>
            <PuertoOrigen>' . ($shipment->voyage->originPort->code ?? 'ARBUE') . '</PuertoOrigen>
            <PuertoDestino>' . ($shipment->voyage->destinationPort->code ?? 'PYTVT') . '</PuertoDestino>
            <FechaSalida>' . ($shipment->voyage->departure_date ? $shipment->voyage->departure_date->format('Y-m-d\TH:i:s') : now()->format('Y-m-d\TH:i:s')) . '</FechaSalida>
          </Viaje>
          <Conductor>
            <Nombre>Juan</Nombre>
            <Apellido>Perez</Apellido>
            <Licencia>CAP001</Licencia>
          </Conductor>
          <PorteadorTitulo>
            <Nombre>MAERSK LINE ARGENTINA S.A.</Nombre>
            <Cuit>' . $this->cuit . '</Cuit>
          </PorteadorTitulo>
          <ResumenMercaderias>
            <PesoTotal>32245</PesoTotal>
            <CantidadBultos>375</CantidadBultos>
          </ResumenMercaderias>
          <Embarcacion>
            <Nombre>' . ($shipment->vessel->name ?? 'Rio Parana I') . '</Nombre>
            <CodigoPais>AR</CodigoPais>
          </Embarcacion>
        </Titulo>
        <Envios>
          <NumeroConocimiento>' . str_pad($shipment->shipment_number, 18, '0', STR_PAD_LEFT) . '</NumeroConocimiento>
          <Aduana>621</Aduana>
          <CodigoLugarOperativoDescarga>PYTVT</CodigoLugarOperativoDescarga>
          <MarcaBultos>MARCA GENERAL</MarcaBultos>
          <IndicadorConsolidado>N</IndicadorConsolidado>
          <IndicadorTransitoTransbordo>N</IndicadorTransitoTransbordo>
        </Envios>
      </argRegistrarTitEnviosParam>
    </RegistrarTitEnvios>
  </soap:Body>
</soap:Envelope>';
    }
    
    /**
     * Envío directo a AFIP sin abstracciones
     */
    private function sendToAfip(string $xml, string $method): string
    {
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ]);
        
        $client = new SoapClient($this->wsdlUrl, [
            'trace' => true,
            'exceptions' => true,
            'soap_version' => SOAP_1_2,
            'stream_context' => $context
        ]);
        
        // Envío directo con XML como string
        $response = $client->__doRequest(
            $xml,
            $this->wsdlUrl,
            'Ar.Gob.Afip.Dga.wgesregsintia2/' . $method,
            SOAP_1_2
        );
        
        return $response;
    }
    
    /**
     * Extraer tracks de respuesta AFIP
     */
    private function extractTracks(string $response): array
    {
        $tracks = [];
        
        if (preg_match_all('/<TracksEnv>([^<]+)<\/TracksEnv>/', $response, $matches)) {
            $tracks = $matches[1];
        }
        
        return $tracks;
    }

    public function getLastSoapResponse($xml, $method) {
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ]);
        
        $client = new SoapClient($this->wsdlUrl, [
            'trace' => true,
            'exceptions' => false, // No lanzar excepciones
            'soap_version' => SOAP_1_2,
            'stream_context' => $context
        ]);
        
        try {
            $response = $client->__doRequest($xml, $this->wsdlUrl, 'Ar.Gob.Afip.Dga.wgesregsintia2/' . $method, SOAP_1_2);
            
            return [
                'request' => $client->__getLastRequest(),
                'response' => $response,
                'headers' => $client->__getLastResponseHeaders()
            ];
        } catch (Exception $e) {
            return [
                'error' => $e->getMessage(),
                'request' => $client->__getLastRequest(),
                'response' => $client->__getLastResponse()
            ];
        }
    }
}