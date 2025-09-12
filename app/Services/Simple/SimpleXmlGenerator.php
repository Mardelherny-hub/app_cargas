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
    private const WSAA_NAMESPACE = 'http://ar.gob.afip.dif.wgesregsintia2/';

    public function __construct(Company $company)
    {
        $this->company = $company;
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

        // Crear XML usando string directo (más simple que DOM)
        $xml = $this->createSoapEnvelope();
        
        $xml .= '<ns1:RegistrarTitEnvios xmlns:ns1="' . self::AFIP_NAMESPACE . '">';
        
        // Autenticación empresa
        $xml .= '<argWSAutenticacionEmpresa>';
        $xml .= '<argCuit>' . $this->company->tax_id . '</argCuit>';
        $xml .= '</argWSAutenticacionEmpresa>';
        
        // Parámetros TitEnvios
        $xml .= '<argRegistrarTitEnviosParam>';
        $xml .= '<IdTransaccion>' . $transactionId . '</IdTransaccion>';
        
        // Información del Título
        $xml .= '<Titulo>';
        $xml .= '<NroTitulo>' . $shipment->shipment_number . '</NroTitulo>';
        $xml .= '<TipoTitulo>3</TipoTitulo>'; // MIC/DTA
        $xml .= '<FechaEmision>' . now()->format('Y-m-d') . '</FechaEmision>';
        $xml .= '<LugarEmision>' . ($voyage->originPort?->code ?? 'ARBUE') . '</LugarEmision>';
        $xml .= '<CUIT>' . $this->company->tax_id . '</CUIT>';
        $xml .= '</Titulo>';
        
        // Información de Envíos
        $xml .= '<Envios>';
        $xml .= '<Envio>';
        $xml .= '<NroEnvio>1</NroEnvio>';
        $xml .= '<FechaEnvio>' . ($voyage->departure_date?->format('Y-m-d') ?? now()->format('Y-m-d')) . '</FechaEnvio>';
        $xml .= '<LugarOrigen>' . ($voyage->originPort?->code ?? 'ARBUE') . '</LugarOrigen>';
        $xml .= '<LugarDestino>' . ($voyage->destinationPort?->code ?? 'PYTVT') . '</LugarDestino>';
        
        // Datos de la embarcación
        $xml .= '<Embarcacion>';
        $xml .= '<Nombre>' . htmlspecialchars($vessel?->name ?? 'SIN NOMBRE') . '</Nombre>';
        $xml .= '<RegistroNacional>' . ($vessel?->registration_number ?? 'SIN_REGISTRO') . '</RegistroNacional>';
        $xml .= '<TipoEmbarcacion>BAR</TipoEmbarcacion>'; // Barcaza
        $xml .= '</Embarcacion>';
        
        // Datos del conductor (capitán)
        $xml .= '<Conductor>';
        $xml .= '<Nombre>' . htmlspecialchars($captain?->first_name ?? 'SIN NOMBRE') . '</Nombre>';
        $xml .= '<Apellido>' . htmlspecialchars($captain?->last_name ?? 'SIN APELLIDO') . '</Apellido>';
        $xml .= '<TipoDocumento>96</TipoDocumento>'; // CUIT
        $xml .= '<NroDocumento>' . ($captain?->tax_id ?? $this->company->tax_id) . '</NroDocumento>';
        $xml .= '</Conductor>';
        
        // Carga básica
        $xml .= '<Carga>';
        $xml .= '<TipoCarga>GRAL</TipoCarga>';
        $xml .= '<PesoBruto>' . ($shipment->cargo_weight_loaded ?? 1000) . '</PesoBruto>';
        $xml .= '<CantidadBultos>' . ($shipment->containers_loaded ?? 1) . '</CantidadBultos>';
        $xml .= '<Descripcion>Carga general contenedorizada</Descripcion>';
        $xml .= '</Carga>';
        
        $xml .= '</Envio>';
        $xml .= '</Envios>';
        $xml .= '</argRegistrarTitEnviosParam>';
        $xml .= '</ns1:RegistrarTitEnvios>';
        $xml .= $this->closeSoapEnvelope();

        return $xml;
    }

    /**
     * Crear XML para RegistrarMicDta - PASO 2 AFIP
     * Usa TRACKs generados en paso 1
     */
    public function createRegistrarMicDtaXml(Shipment $shipment, string $transactionId, array $tracks = []): string
    {
        $voyage = $shipment->voyage;
        $vessel = $shipment->vessel ?? $voyage->leadVessel;

        $xml = $this->createSoapEnvelope();
        
        $xml .= '<ns1:RegistrarMicDta xmlns:ns1="' . self::AFIP_NAMESPACE . '">';
        
        // Autenticación empresa
        $xml .= '<argWSAutenticacionEmpresa>';
        $xml .= '<argCuit>' . $this->company->tax_id . '</argCuit>';
        $xml .= '</argWSAutenticacionEmpresa>';
        
        // Parámetros MIC/DTA
        $xml .= '<argRegistrarMicDtaParam>';
        $xml .= '<idTransaccion>' . $transactionId . '</idTransaccion>';
        
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
        $xml .= '</ns1:RegistrarMicDta>';
        $xml .= $this->closeSoapEnvelope();

        return $xml;
    }

    /**
     * Crear envelope SOAP estándar
     */
    private function createSoapEnvelope(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>' .
               '<env:Envelope xmlns:env="http://www.w3.org/2003/05/soap-envelope" ' .
               'xmlns:ns1="' . self::AFIP_NAMESPACE . '" ' .
               'xmlns:ns2="' . self::WSAA_NAMESPACE . '">' .
               '<env:Header>' .
               '<ns2:Auth>' .
               '<item><key>Token</key><value>VEVTVElOR19UT0tFTl8xMDA1XzE3NTc2NDU1NTM=</value></item>' .
               '<item><key>Sign</key><value>VEVTVElOR19TSUdOXzMwNjg4NDE1NTMxXzE3NTc2NDU1NTM=</value></item>' .
               '<item><key>Cuit</key><value>' . $this->company->tax_id . '</value></item>' .
               '</ns2:Auth>' .
               '</env:Header>' .
               '<env:Body>';
    }

    /**
     * Cerrar envelope SOAP
     */
    private function closeSoapEnvelope(): string
    {
        return '</env:Body></env:Envelope>';
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
}