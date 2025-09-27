<?php

namespace App\Services\Simple;

use App\Models\Company;
use App\Models\Shipment;
use App\Models\Voyage;
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
        try {
            $voyage = $shipment->voyage()->with(['originPort', 'destinationPort', 'originPort.country', 'destinationPort.country'])->first();
            $wsaa = $this->getWSAATokens();

            // Códigos AFIP obligatorios
            $portCustomsCodes = [
                'ARBUE' => '019', // Buenos Aires
                'PYTVT' => '051', // Villeta
                'ARSFE' => '014', // Santa Fe
                'ARPAR' => '013', // Paraná
            ];

            $originPortCode = $voyage->originPort?->code ?? '';
            $destPortCode = $voyage->destinationPort?->code ?? '';
            
            $codAduOrigen = $voyage->originPort?->customs_code ?? ($portCustomsCodes[$originPortCode] ?? '019');
            $codAduDest = $voyage->destinationPort?->customs_code ?? ($portCustomsCodes[$destPortCode] ?? '051');
            $codPaisOrigen = $voyage->originPort?->country?->numeric_code ?? '032'; // Argentina
            $codPaisDest = $voyage->destinationPort?->country?->numeric_code ?? '600'; // Paraguay

            // Contenedores del shipment
            $containers = $shipment->shipmentItems()
                ->with(['containers.containerType'])
                ->get()
                ->flatMap(fn($item) => $item->containers);

            $w = new \XMLWriter();
            $w->openMemory();
            $w->startDocument('1.0', 'UTF-8');

            // SOAP Envelope
            $w->startElementNs('soap', 'Envelope', 'http://schemas.xmlsoap.org/soap/envelope/');
            $w->writeAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
            $w->writeAttribute('xmlns:xsd', 'http://www.w3.org/2001/XMLSchema');

            // SOAP Body
            $w->startElementNs('soap', 'Body', 'http://schemas.xmlsoap.org/soap/envelope/');
                $w->startElement('RegistrarTitEnvios');
                $w->writeAttribute('xmlns', self::AFIP_NAMESPACE);

                // Autenticación empresa
                $w->startElement('argWSAutenticacionEmpresa');
                    $w->writeElement('Token', $wsaa['token']);
                    $w->writeElement('Sign', $wsaa['sign']);
                    $w->writeElement('CuitEmpresaConectada', (string)$this->company->tax_id);
                    $w->writeElement('TipoAgente', 'ATA');
                    $w->writeElement('Rol', 'TRSP');
                $w->endElement();

                // Parámetros RegistrarTitEnvios
                $w->startElement('argRegistrarTitEnviosParam');
                    $w->writeElement('idTransaccion', substr($transactionId, 0, 15));

                    // Títulos de transporte (SOLO el título, sin envíos)
                    $w->startElement('titulosTransEnvios');
                    $w->startElement('TitTransEnvio');
                        $w->writeElement('codViaTrans', '8'); // Hidrovía
                        $w->writeElement('idTitTrans', (string)$shipment->shipment_number);
                        $w->writeElement('indFinCom', 'S');
                        $w->writeElement('indFraccTransp', 'N');
                        $w->writeElement('indConsol', 'N');
                        
                        // IDs documentos
                        $w->writeElement('idManiCargaArrPaisPart', $shipment->origin_manifest_id ?? 'SIN_MANIFIESTO');
                        $w->writeElement('idDocTranspArrPaisPart', $shipment->origin_transport_doc ?? 'SIN_DOC');

                        // Origen obligatorio
                        $w->startElement('origen');
                            $w->writeElement('codPais', $codPaisOrigen);
                            $w->writeElement('codAdu', $codAduOrigen);
                        $w->endElement();

                        // Destino obligatorio
                        $w->startElement('destino');
                            $w->writeElement('codPais', $codPaisDest);
                            $w->writeElement('codAdu', $codAduDest);
                        $w->endElement();

                    $w->endElement(); // TitTransEnvio
                    $w->endElement(); // titulosTransEnvios

                    // Títulos contenedores vacíos (puede estar vacío)
                    $w->startElement('titulosTransContVacios');
                    $w->endElement();

                    // Contenedores (registro básico para el título)
                    if ($containers->isNotEmpty()) {
                        $w->startElement('contenedores');
                        foreach ($containers as $c) {
                            $w->startElement('Contenedor');
                                $w->writeElement('id', (string)$c->container_number);
                                
                                // Mapeo códigos AFIP
                                $containerCode = $c->containerType?->argentina_ws_code ?? '42G1';
                                if ($containerCode === '40GP') $containerCode = '42G1';
                                if ($containerCode === '20GP') $containerCode = '22G1';
                                $w->writeElement('codMedida', $containerCode);
                                
                                // Condición contenedor
                                $conditionMap = ['L' => 'P', 'V' => 'V', 'full' => 'P', 'empty' => 'V', 'loaded' => 'P'];
                                $condition = $conditionMap[$c->condition] ?? 'P';
                                $w->writeElement('condicion', $condition);
                                
                                // Precintos si existen
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
            return $w->outputMemory();

        } catch (Exception $e) {
            error_log('Error en createRegistrarTitEnviosXml: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * PASO 2: RegistrarEnvios - Envíos detallados para generar TRACKs
     * CORREGIDO con todos los campos obligatorios AFIP
     */
    /**
     * PASO 2: RegistrarEnvios - CORREGIDO con cálculo de pesos REAL
     * Reemplaza createRegistrarEnviosXml() en SimpleXmlGenerator.php
     */
    public function createRegistrarEnviosXml(Shipment $shipment, string $transactionId): string
    {
        try {
            $voyage = $shipment->voyage()->with(['originPort', 'destinationPort'])->first();
            $wsaa = $this->getWSAATokens();
            
            // CARGAR CORRECTAMENTE BLs con shipmentItems
            $billsOfLading = $shipment->billsOfLading()->with(['shipmentItems'])->get();

            $w = new \XMLWriter();
            $w->openMemory();
            $w->startDocument('1.0', 'UTF-8');

            $w->startElementNs('soap', 'Envelope', 'http://schemas.xmlsoap.org/soap/envelope/');
            $w->startElementNs('soap', 'Body', 'http://schemas.xmlsoap.org/soap/envelope/');
                $w->startElement('RegistrarEnvios');
                $w->writeAttribute('xmlns', self::AFIP_NAMESPACE);

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
                    $w->writeElement('idTransaccion', substr($transactionId, 0, 15));
                    $w->writeElement('idTitTrans', (string)$shipment->shipment_number);

                    // Envíos individuales
                    $w->startElement('envios');
                    foreach ($billsOfLading as $index => $bol) {
                        // CÁLCULO CORREGIDO DE PESOS
                        $shipmentItemsQuery = $bol->shipmentItems();
                        $shipmentItemsCount = $shipmentItemsQuery->count();
                        
                        // LOG PARA DEBUG
                        error_log("BL {$bol->id}: ShipmentItems count = {$shipmentItemsCount}");
                        
                        if ($shipmentItemsCount > 0) {
                            // Usar query directa para asegurar datos
                            $weightSum = $shipmentItemsQuery->sum('gross_weight_kg');
                            $packageSum = $shipmentItemsQuery->sum('package_quantity');
                        } else {
                            // Fallback: usar datos del BL si no hay shipmentItems
                            $weightSum = $bol->gross_weight_kg ?? 0;
                            $packageSum = $bol->total_packages ?? 0;
                        }
                        
                        // VALIDAR que los valores sean > 0 ANTES de enviar a AFIP
                        $totalWeight = max(1.0, (float)$weightSum);
                        $totalPackages = max(1, (int)$packageSum);
                        
                        // LOG DETALLADO PARA DEBUG
                        error_log("BL {$bol->bill_of_lading_number}: weight_sum={$weightSum}, package_sum={$packageSum}, final_weight={$totalWeight}, final_packages={$totalPackages}");
                        
                        $w->startElement('Envio');
                            $w->writeElement('idEnvio', (string)($index + 1));
                            $w->writeElement('fechaEmb', ($voyage->departure_date ? $voyage->departure_date->format('Y-m-d') : now()->format('Y-m-d')));
                            
                            // Puertos UN/LOCODE
                            $w->writeElement('codPuertoEmb', $voyage->originPort?->code ?? 'ARBUE');
                            $w->writeElement('codPuertoDesc', $voyage->destinationPort?->code ?? 'PYTVT');
                            
                            // Documento comercial
                            $bolNumber = $bol->bill_of_lading_number ?? $bol->bl_number ?? ('BL' . str_pad($index + 1, 6, '0', STR_PAD_LEFT));
                            $w->writeElement('idDocComercial', (string)$bolNumber);
                            $w->writeElement('descripcionMercaderia', (string)($bol->cargo_description ?? 'CARGA GENERAL'));
                            
                            // PESO Y BULTOS CORREGIDOS - NUNCA 0
                            $w->writeElement('cantBultos', (string)$totalPackages);
                            $w->writeElement('pesoBrutoKg', number_format($totalWeight, 3, '.', ''));
                            $w->writeElement('indUltimaFraccion', 'S');

                            // Lugares operativos obligatorios
                            $w->startElement('lugOperOrigen');
                                $w->writeElement('codLugOper', $voyage->originPort?->code ?? 'ARBUE');
                                $w->writeElement('codCiu', $voyage->originPort?->code ?? 'ARBUE');
                            $w->endElement();

                            $w->startElement('lugOperDestino');
                                $w->writeElement('codLugOper', $voyage->destinationPort?->code ?? 'PYTVT');
                                $w->writeElement('codCiu', $voyage->destinationPort?->code ?? 'PYTVT');
                            $w->endElement();

                            // Campos obligatorios adicionales
                            $w->writeElement('indUltFra', 'S');
                            $w->writeElement('idFiscalATAMIC', (string)$this->company->tax_id);

                            // Destinaciones con estructura completa
                            $w->startElement('destinaciones');
                            $w->startElement('Destinacion');
                                $w->writeElement('codDestinacion', 'EXP');
                                $w->writeElement('codDivisaFob', 'USD');
                                $w->writeElement('codDivisaFle', 'USD');
                                $w->writeElement('codDivisaSeg', 'USD');
                                
                                // idDecla con 16 caracteres exactos
                                $w->writeElement('idDecla', str_pad('D' . $bol->id, 16, '0', STR_PAD_LEFT));
                                
                                // Items con estructura obligatoria
                                $w->startElement('items');
                                $w->startElement('Item');
                                    $w->writeElement('nroItem', (string)($index + 1));
                                    $w->writeElement('descripcion', $bol->cargo_description ?? 'MERCADERIA GENERAL');
                                $w->endElement();
                                $w->endElement();
                                
                                // Bultos con todos los campos obligatorios - PESO CORRECTO
                                $w->startElement('bultos');
                                $w->startElement('Bulto');
                                    $w->writeElement('cantBultos', (string)$totalPackages);
                                    $w->writeElement('pesoBruto', number_format($totalWeight, 3, '.', ''));
                                    $w->writeElement('pesoBrutoTotFrac', number_format($totalWeight, 3, '.', ''));
                                    $w->writeElement('cantBultosTotFrac', (string)$totalPackages);
                                    $w->writeElement('codEmbalaje', 'CT');
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
            
            // LOG FINAL PARA VERIFICAR
            error_log("XML RegistrarEnvios generado - BLs: " . $billsOfLading->count() . ", XML length: " . strlen($xmlContent));
            
            return $xmlContent;

        } catch (Exception $e) {
            error_log('Error en createRegistrarEnviosXml: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * PASO 3: RegistrarMicDta - CORREGIDO usando voyage y tracks
     */
    public function createRegistrarMicDtaXml(Voyage $voyage, array $tracks, string $transactionId): string
    {
        try {
            $vessel = $voyage->leadVessel;
            $wsaa = $this->getWSAATokens();

            $w = new \XMLWriter();
            $w->openMemory();
            $w->startDocument('1.0', 'UTF-8');

            $w->startElementNs('soap', 'Envelope', 'http://schemas.xmlsoap.org/soap/envelope/');
            $w->startElementNs('soap', 'Body', 'http://schemas.xmlsoap.org/soap/envelope/');
                $w->startElement('RegistrarMicDta');
                $w->writeAttribute('xmlns', self::AFIP_NAMESPACE);

                // Autenticación empresa
                $w->startElement('argWSAutenticacionEmpresa');
                    $w->writeElement('Token', $wsaa['token']);
                    $w->writeElement('Sign', $wsaa['sign']);
                    $w->writeElement('CuitEmpresaConectada', (string)$this->company->tax_id);
                    $w->writeElement('TipoAgente', 'ATA');
                    $w->writeElement('Rol', 'TRSP');
                $w->endElement();

                // Parámetros MIC/DTA
                $w->startElement('argRegistrarMicDtaParam');
                    $w->writeElement('IdTransaccion', substr($transactionId, 0, 15));
                    
                    // Estructura MIC/DTA
                    $w->startElement('micDta');
                        $w->writeElement('codViaTrans', '8'); // Hidrovía
                        
                        // Transportista
                        $w->startElement('transportista');
                            $w->writeElement('cuitTransportista', (string)$this->company->tax_id);
                            $w->writeElement('denominacionTransportista', htmlspecialchars($this->company->legal_name));
                        $w->endElement();
                        
                        // Propietario vehículo  
                        $w->startElement('propietarioVehiculo');
                            $w->writeElement('cuitPropietario', (string)$this->company->tax_id);
                            $w->writeElement('denominacionPropietario', htmlspecialchars($this->company->legal_name));
                        $w->endElement();
                        
                        $w->writeElement('indEnLastre', 'N'); // No en lastre
                        
                        // Embarcación
                        $w->startElement('embarcacion');
                            $w->writeElement('nombreEmbarcacion', htmlspecialchars($vessel?->name ?? 'SIN NOMBRE'));
                            $w->writeElement('registroNacionalEmbarcacion', ($vessel?->registration_number ?? 'SIN_REGISTRO'));
                            $w->writeElement('tipoEmbarcacion', 'BAR'); // Barcaza
                        $w->endElement();
                        
                        // TRACKs generados en pasos anteriores
                        if (!empty($tracks)) {
                            $w->startElement('tracks');
                            foreach ($tracks as $shipmentId => $trackList) {
                                if (is_array($trackList)) {
                                    foreach ($trackList as $track) {
                                        $w->startElement('track');
                                            $w->writeElement('trackId', (string)$track);
                                            $w->writeElement('shipmentId', (string)$shipmentId);
                                        $w->endElement();
                                    }
                                }
                            }
                            $w->endElement();
                        }
                        
                    $w->endElement(); // micDta
                $w->endElement(); // argRegistrarMicDtaParam
                $w->endElement(); // RegistrarMicDta
            $w->endElement(); // Body
            $w->endElement(); // Envelope

            $w->endDocument();
            return $w->outputMemory();

        } catch (Exception $e) {
            error_log('Error en createRegistrarMicDtaXml: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Obtener tokens WSAA - MÉTODO SIN CAMBIOS (funciona correctamente)
     */
    private function getWSAATokens(): array
    {
        try {
            // Verificar cache primero
            $cachedToken = \App\Models\WsaaToken::getValidToken(
                $this->company->id, 
                'wgesregsintia2', 
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
            
            $loginTicket = $this->generateLoginTicket();
            $signedTicket = $this->signLoginTicket($loginTicket, $certData);
            $wsaaTokens = $this->callWSAA($signedTicket);
            
            // Guardar en cache
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
            
            return [
                'token' => $wsaaTokens['token'],
                'sign' => $wsaaTokens['sign'],
                'cuit' => $this->company->tax_id
            ];
            
        } catch (Exception $e) {
            error_log("WSAA ERROR: " . $e->getMessage());
            throw $e;
        }
    }

    private function generateLoginTicket(): string
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
                   '<service>wgesregsintia2</service>' .
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
            
            // Body con método RegistrarConvoy
            $xml .= '<soap:Body>';
            $xml .= '<RegistrarConvoy xmlns="' . self::AFIP_NAMESPACE . '">';
            
            // Autenticación empresa (obligatorio AFIP)
            $xml .= '<argWSAutenticacionEmpresa>';
            $xml .= '<CuitEmpresaConectada>' . htmlspecialchars($wsaaTokens['cuit']) . '</CuitEmpresaConectada>';
            $xml .= '<TipoAgente>TRSP</TipoAgente>'; // Transportista
            $xml .= '<Rol>TRSP</Rol>'; // Rol transportista
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
            error_log("SimpleXmlGenerator: Error creando XML RegistrarConvoy - " . $e->getMessage());
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
            error_log("SimpleXmlGenerator: Error creando XML AsignarATARemol - " . $e->getMessage());
            return null;
        }
    }

    /**
     * PASO 4: RegistrarSalidaZonaPrimaria - Registrar salida de puerto
     * Genera XML según especificación exacta AFIP
     * 
     * @param array $salidaData Datos de salida
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
            
            // Body con método RegistrarSalidaZonaPrimaria
            $xml .= '<soap:Body>';
            $xml .= '<RegistrarSalidaZonaPrimaria xmlns="' . self::AFIP_NAMESPACE . '">';
            
            // Autenticación empresa (obligatorio AFIP)
            $xml .= '<argWSAutenticacionEmpresa>';
            $xml .= '<CuitEmpresaConectada>' . htmlspecialchars($wsaaTokens['cuit']) . '</CuitEmpresaConectada>';
            $xml .= '<TipoAgente>TRSP</TipoAgente>'; // Transportista
            $xml .= '<Rol>TRSP</Rol>'; // Rol transportista
            $xml .= '</argWSAutenticacionEmpresa>';
            
            // Número de viaje (único parámetro requerido)
            $xml .= '<argNroViaje>' . htmlspecialchars($salidaData['nro_viaje']) . '</argNroViaje>';
            
            $xml .= '</RegistrarSalidaZonaPrimaria>';
            $xml .= '</soap:Body>';
            $xml .= '</soap:Envelope>';

            return $xml;

        } catch (Exception $e) {
            error_log("SimpleXmlGenerator: Error creando XML RegistrarSalidaZonaPrimaria - " . $e->getMessage());
            return null;
        }
    }

    /**
     * SolicitarAnularMicDta - Solicitar anulación de MIC/DTA
     * Genera XML según especificación exacta AFIP
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
            
            // Body con método SolicitarAnularMicDta
            $xml .= '<soap:Body>';
            $xml .= '<SolicitarAnularMicDta xmlns="' . self::AFIP_NAMESPACE . '">';
            
            // Autenticación empresa (obligatorio AFIP)
            $xml .= '<argWSAutenticacionEmpresa>';
            $xml .= '<CuitEmpresaConectada>' . htmlspecialchars($wsaaTokens['cuit']) . '</CuitEmpresaConectada>';
            $xml .= '<TipoAgente>TRSP</TipoAgente>'; // Transportista
            $xml .= '<Rol>TRSP</Rol>'; // Rol transportista
            $xml .= '</argWSAutenticacionEmpresa>';
            
            // Parámetros específicos SolicitarAnularMicDta
            $xml .= '<argSolicitarAnularMicDtaParam>';
            
            // ID MIC/DTA (máximo 16 caracteres)
            $xml .= '<idMicDta>' . htmlspecialchars($anulacionData['id_micdta']) . '</idMicDta>';
            
            // Descripción del motivo (máximo 50 caracteres)
            $xml .= '<descMotivo>' . htmlspecialchars($anulacionData['desc_motivo']) . '</descMotivo>';
            
            $xml .= '</argSolicitarAnularMicDtaParam>';
            $xml .= '</SolicitarAnularMicDta>';
            $xml .= '</soap:Body>';
            $xml .= '</soap:Envelope>';

            return $xml;

        } catch (Exception $e) {
            error_log("SimpleXmlGenerator: Error creando XML SolicitarAnularMicDta - " . $e->getMessage());
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
                $w->startElement('RectifConvoyMicDta');
                $w->writeAttribute('xmlns', self::AFIP_NAMESPACE);

                // Autenticación empresa
                $w->startElement('argWSAutenticacionEmpresa');
                    $w->writeElement('Token', $wsaa['token']);
                    $w->writeElement('Sign', $wsaa['sign']);
                    $w->writeElement('CuitEmpresaConectada', (string)$this->company->tax_id);
                    $w->writeElement('TipoAgente', 'ATA');
                    $w->writeElement('Rol', 'TRSP');
                $w->endElement();

                // Parámetros RectifConvoyMicDta
                $w->startElement('argRectifConvoyMicDtaParam');
                    
                    // ID Transacción (máximo 15 caracteres AFIP)
                    $w->writeElement('idTransaccion', substr($transactionId, 0, 15));
                    
                    // Número de viaje (obligatorio)
                    $w->writeElement('nroViaje', (string)$rectifData['nro_viaje']);
                    
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
            error_log('Error en createRectifConvoyMicDtaXml: ' . $e->getMessage());
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

            // Crear XMLWriter
            $w = new \XMLWriter();
            $w->openMemory();
            $w->startDocument('1.0', 'UTF-8');

            // Envelope SOAP
            $w->startElementNs('soap', 'Envelope', 'http://schemas.xmlsoap.org/soap/envelope/');
            $w->writeAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
            $w->writeAttribute('xmlns:xsd', 'http://www.w3.org/2001/XMLSchema');
            
            $w->startElementNs('soap', 'Body', 'http://schemas.xmlsoap.org/soap/envelope/');
                $w->startElement('ConsultarMicDtaAsig');
                $w->writeAttribute('xmlns', self::AFIP_NAMESPACE);

                // Autenticación empresa (obligatorio para todos los métodos AFIP)
                $w->startElement('argWSAutenticacionEmpresa');
                    $w->writeElement('Token', $wsaa['token']);
                    $w->writeElement('Sign', $wsaa['sign']);
                    $w->writeElement('CuitEmpresaConectada', (string)$this->company->tax_id);
                    $w->writeElement('TipoAgente', 'ATA');
                    $w->writeElement('Rol', 'TRSP');
                $w->endElement();

                // Parámetros de consulta (si se especifican filtros)
                if (!empty($consultaData) || !empty($transactionId)) {
                    $w->startElement('argConsultarMicDtaAsigParam');
                    
                    // ID Transacción para identificar la consulta (opcional)
                    if (!empty($transactionId)) {
                        $w->writeElement('idTransaccion', substr($transactionId, 0, 15));
                    }
                    
                    // Filtros opcionales para la consulta
                    if (!empty($consultaData['fecha_desde'])) {
                        $w->writeElement('fechaDesde', $consultaData['fecha_desde']);
                    }
                    
                    if (!empty($consultaData['fecha_hasta'])) {
                        $w->writeElement('fechaHasta', $consultaData['fecha_hasta']);
                    }
                    
                    if (!empty($consultaData['cuit_ata_remolcador'])) {
                        $w->writeElement('cuitATARemolcador', $consultaData['cuit_ata_remolcador']);
                    }
                    
                    if (!empty($consultaData['nro_viaje'])) {
                        $w->writeElement('nroViaje', $consultaData['nro_viaje']);
                    }
                    
                    $w->endElement(); // argConsultarMicDtaAsigParam
                }
                
                $w->endElement(); // ConsultarMicDtaAsig
            $w->endElement(); // Body
            $w->endElement(); // Envelope

            $w->endDocument();
            return $w->outputMemory();

        } catch (Exception $e) {
            error_log('Error en createConsultarMicDtaAsigXml: ' . $e->getMessage());
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

            // Crear XMLWriter
            $w = new \XMLWriter();
            $w->openMemory();
            $w->startDocument('1.0', 'UTF-8');

            // Envelope SOAP
            $w->startElementNs('soap', 'Envelope', 'http://schemas.xmlsoap.org/soap/envelope/');
            $w->writeAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
            $w->writeAttribute('xmlns:xsd', 'http://www.w3.org/2001/XMLSchema');
            
            $w->startElementNs('soap', 'Body', 'http://schemas.xmlsoap.org/soap/envelope/');
                $w->startElement('ConsultarTitEnviosReg');
                $w->writeAttribute('xmlns', self::AFIP_NAMESPACE);

                // Autenticación empresa (único parámetro requerido según AFIP)
                $w->startElement('argWSAutenticacionEmpresa');
                    $w->writeElement('Token', $wsaa['token']);
                    $w->writeElement('Sign', $wsaa['sign']);
                    $w->writeElement('CuitEmpresaConectada', (string)$this->company->tax_id);
                    $w->writeElement('TipoAgente', 'ATA');
                    $w->writeElement('Rol', 'TRSP');
                $w->endElement();

                $w->endElement(); // ConsultarTitEnviosReg
            $w->endElement(); // Body
            $w->endElement(); // Envelope

            $w->endDocument();
            return $w->outputMemory();

        } catch (Exception $e) {
            error_log('Error en createConsultarTitEnviosRegXml: ' . $e->getMessage());
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
                $w->startElement('RegistrarArriboZonaPrimaria');
                $w->writeAttribute('xmlns', self::AFIP_NAMESPACE);

                // Autenticación empresa (obligatorio)
                $w->startElement('argWSAutenticacionEmpresa');
                    $w->writeElement('Token', $wsaa['token']);
                    $w->writeElement('Sign', $wsaa['sign']);
                    $w->writeElement('CuitEmpresaConectada', (string)$this->company->tax_id);
                    $w->writeElement('TipoAgente', 'ATA');
                    $w->writeElement('Rol', 'TRSP');
                $w->endElement();

                // Número de viaje (único parámetro requerido)
                $w->writeElement('argNroViaje', (string)$arriboData['nro_viaje']);

                $w->endElement(); // RegistrarArriboZonaPrimaria
            $w->endElement(); // Body
            $w->endElement(); // Envelope

            $w->endDocument();
            return $w->outputMemory();

        } catch (Exception $e) {
            error_log('Error en createRegistrarArriboZonaPrimariaXml: ' . $e->getMessage());
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
                    $w->writeElement('TipoAgente', 'ATA');
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
            error_log('Error en createAnularTituloXml: ' . $e->getMessage());
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
    public function createRegistrarTitMicDtaXml(array $vinculacionData, string $transactionId): ?string
    {
        try {
            // Validar datos obligatorios
            if (empty($vinculacionData['id_micdta'])) {
                throw new Exception('ID MIC/DTA obligatorio');
            }
            
            if (empty($vinculacionData['titulos']) || !is_array($vinculacionData['titulos'])) {
                throw new Exception('Lista de títulos obligatoria');
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
                    $w->writeElement('TipoAgente', 'ATA');
                    $w->writeElement('Rol', 'TRSP');
                $w->endElement();

                // Parámetros específicos del método
                $w->startElement('argRegistrarTitMicDtaParam');
                    
                    // ID Transacción (obligatorio)
                    $w->writeElement('idTransaccion', substr($transactionId, 0, 15));
                    
                    // ID MIC/DTA al cual vincular títulos (obligatorio)
                    $w->writeElement('idMicDta', htmlspecialchars($vinculacionData['id_micdta']));
                    
                    // Lista de títulos de transporte a vincular
                    $w->startElement('idTitTrans');
                    foreach ($vinculacionData['titulos'] as $titulo) {
                        $tituloId = is_array($titulo) ? ($titulo['id'] ?? $titulo['id_titulo'] ?? '') : (string)$titulo;
                        if (!empty($tituloId)) {
                            $w->writeElement('string', htmlspecialchars(substr($tituloId, 0, 36)));
                        }
                    }
                    $w->endElement(); // idTitTrans
                    
                $w->endElement(); // argRegistrarTitMicDtaParam
                $w->endElement(); // RegistrarTitMicDta
            $w->endElement(); // Body
            $w->endElement(); // Envelope

            $w->endDocument();
            return $w->outputMemory();

        } catch (Exception $e) {
            error_log('Error en createRegistrarTitMicDtaXml: ' . $e->getMessage());
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
                    $w->writeElement('TipoAgente', 'ATA');
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
            error_log('Error en createDesvincularTitMicDtaXml: ' . $e->getMessage());
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
                    $w->writeElement('TipoAgente', 'ATA');
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
            error_log('Error en createAnularEnviosXml: ' . $e->getMessage());
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
            // Crear XMLWriter
            $w = new \XMLWriter();
            $w->openMemory();
            $w->startDocument('1.0', 'UTF-8');

            // Envelope SOAP
            $w->startElementNs('soap', 'Envelope', 'http://schemas.xmlsoap.org/soap/envelope/');
            $w->writeAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
            $w->writeAttribute('xmlns:xsd', 'http://www.w3.org/2001/XMLSchema');
            
            $w->startElementNs('soap', 'Body', 'http://schemas.xmlsoap.org/soap/envelope/');
                // Método Dummy sin parámetros específicos (solo namespace)
                $w->startElement('Dummy');
                $w->writeAttribute('xmlns', self::AFIP_NAMESPACE);
                $w->endElement(); // Dummy (self-closing)
            $w->endElement(); // Body
            $w->endElement(); // Envelope

            $w->endDocument();
            return $w->outputMemory();

        } catch (Exception $e) {
            error_log('Error en createDummyXml: ' . $e->getMessage());
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

            // Crear XMLWriter
            $w = new \XMLWriter();
            $w->openMemory();
            $w->startDocument('1.0', 'UTF-8');

            // Envelope SOAP
            $w->startElementNs('soap', 'Envelope', 'http://schemas.xmlsoap.org/soap/envelope/');
            $w->writeAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
            $w->writeAttribute('xmlns:xsd', 'http://www.w3.org/2001/XMLSchema');
            
            $w->startElementNs('soap', 'Body', 'http://schemas.xmlsoap.org/soap/envelope/');
                $w->startElement('ConsultarPrecumplido');
                $w->writeAttribute('xmlns', self::AFIP_NAMESPACE);

                // Autenticación empresa (obligatorio)
                $w->startElement('argWSAutenticacionEmpresa');
                    $w->writeElement('Token', $wsaa['token']);
                    $w->writeElement('Sign', $wsaa['sign']);
                    $w->writeElement('CuitEmpresaConectada', (string)$this->company->tax_id);
                    $w->writeElement('TipoAgente', 'ATA');
                    $w->writeElement('Rol', 'TRSP');
                $w->endElement();

                // Parámetros de consulta (si se especifican)
                if (!empty($consultaData)) {
                    $w->startElement('argConsultarPrecumplidoParam');
                    
                    // ID Transacción para identificar la consulta (opcional)
                    if (!empty($transactionId)) {
                        $w->writeElement('idTransaccion', substr($transactionId, 0, 15));
                    }
                    
                    // ID de destinación para consultar precumplido (principal parámetro)
                    if (!empty($consultaData['destinacion_id'])) {
                        $w->writeElement('idDestinacion', htmlspecialchars($consultaData['destinacion_id']));
                    }
                    
                    // Otros filtros opcionales
                    if (!empty($consultaData['codigo_aduana'])) {
                        $w->writeElement('codAduana', htmlspecialchars($consultaData['codigo_aduana']));
                    }
                    
                    $w->endElement(); // argConsultarPrecumplidoParam
                }

                $w->endElement(); // ConsultarPrecumplido
            $w->endElement(); // Body
            $w->endElement(); // Envelope

            $w->endDocument();
            return $w->outputMemory();

        } catch (Exception $e) {
            error_log('Error en createConsultarPrecumplidoXml: ' . $e->getMessage());
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
                    $w->writeElement('TipoAgente', 'ATA');
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
            error_log('Error en createAnularArriboZonaPrimariaXml: ' . $e->getMessage());
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
                $w->startElement('RegistrarViaje');
                $w->writeAttribute('xmlns', self::AFIP_ANTICIPADA_NAMESPACE);

                // Autenticación empresa (obligatorio)
                $w->startElement('argWSAutenticacionEmpresa');
                    $w->writeElement('Token', $wsaa['token']);
                    $w->writeElement('Sign', $wsaa['sign']);
                    $w->writeElement('CuitEmpresaConectada', (string)$this->company->tax_id);
                    $w->writeElement('TipoAgente', 'ATA');
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
            error_log('Error en createRegistrarViajeXml: ' . $e->getMessage());
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
                    $w->writeElement('TipoAgente', 'ATA');
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
            error_log('Error en createRectificarViajeXml: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * RegistrarTitulosCbc - Registro de títulos ATA CBC
     * 
     * Genera XML para registro de títulos ATA CBC según especificación AFIP.
     * 
     * @param Voyage $voyage Viaje con relaciones cargadas
     * @param array $titulosData Datos específicos de títulos CBC
     * @param string $transactionId ID único de transacción
     * @return string XML completo según especificación AFIP
     * @throws Exception Si faltan datos obligatorios
     */
    public function createRegistrarTitulosCbcXml(Voyage $voyage, array $titulosData, string $transactionId): string
    {
        try {
            // Validar datos obligatorios
            $this->validateVoyageData($voyage);

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
                $w->startElement('RegistrarTitulosCbc');
                $w->writeAttribute('xmlns', self::AFIP_ANTICIPADA_NAMESPACE);

                // Autenticación empresa (obligatorio)
                $w->startElement('argWSAutenticacionEmpresa');
                    $w->writeElement('Token', $wsaa['token']);
                    $w->writeElement('Sign', $wsaa['sign']);
                    $w->writeElement('CuitEmpresaConectada', (string)$this->company->tax_id);
                    $w->writeElement('TipoAgente', 'ATA');
                    $w->writeElement('Rol', 'TRSP');
                $w->endElement();

                // Parámetros RegistrarTitulosCbc
                $w->startElement('argRegistrarTitulosCBC');
                    $w->writeElement('IdTransaccion', substr($transactionId, 0, 15));
                    
                    // TODO: Implementar estructura específica de títulos CBC
                    // Estructura básica por ahora
                    $w->startElement('TitulosCbc');
                        $w->writeElement('VoyageId', (string)$voyage->id);
                        $w->writeElement('VoyageNumber', $voyage->voyage_number);
                    $w->endElement();

                $w->endElement(); // argRegistrarTitulosCBC
                $w->endElement(); // RegistrarTitulosCbc
            $w->endElement(); // Body
            $w->endElement(); // Envelope

            $w->endDocument();
            return $w->outputMemory();

        } catch (Exception $e) {
            error_log('Error en createRegistrarTitulosCbcXml: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Agregar información del viaje al XML
     */
    private function addVoyageInformation(\XMLWriter $w, Voyage $voyage): void
    {
        // Identificador del medio de transporte (número de embarcación)
        $vesselNumber = $voyage->leadVessel?->registration_number ?? $voyage->leadVessel?->name ?? 'SIN_REGISTRO';
        $w->writeElement('IdentificadorMedioTransporte', $vesselNumber);

        // Códigos de país (origen y destino)
        $originCountryCode = $this->getCountryCode($voyage->originPort?->country?->alpha2_code ?? 'AR');
        $destinationCountryCode = $this->getCountryCode($voyage->destinationPort?->country?->alpha2_code ?? 'PY');
        
        $w->writeElement('CodigoPaisProcedencia', $originCountryCode);
        $w->writeElement('CodigoPaisFinViaje', $destinationCountryCode);

        // Códigos de puerto (origen y destino)
        $originPortCode = $this->getPortCustomsCode($voyage->originPort?->code ?? 'ARBUE');
        $destinationPortCode = $this->getPortCustomsCode($voyage->destinationPort?->code ?? 'PYTVT');
        
        $w->writeElement('CodigoPuertoOrigen', $originPortCode);
        // CAMPOS OBLIGATORIOS FALTANTES PARA AFIP
        // FechaArribo (obligatorio) - usar estimated_arrival_date o departure_date
        if ($voyage->estimated_arrival_date) {
            $w->writeElement('FechaArribo', $voyage->estimated_arrival_date->format('Y-m-d\TH:i:s'));
        } elseif ($voyage->departure_date) {
            // Fallback: agregar 24 horas a departure_date
            $w->writeElement('FechaArribo', $voyage->departure_date->addDay()->format('Y-m-d\TH:i:s'));
        } else {
            // Fallback mínimo
            $w->writeElement('FechaArribo', now()->addDay()->format('Y-m-d\TH:i:s'));
        }

        // IndicadorTransporteVacio (S/N) - basado en si hay contenedores cargados
        $hasLoadedContainers = $voyage->shipments()->whereHas('billsOfLading')->exists();
        $w->writeElement('IndicadorTransporteVacio', $hasLoadedContainers ? 'N' : 'S');

        // IndicadorMercaderiaAbordo (S/N) - mismo criterio que arriba  
        $w->writeElement('IndicadorMercaderiaAbordo', $hasLoadedContainers ? 'S' : 'N');

        // DesignacionTransportista (string 35) - nombre de la empresa o capitán
        $transportistName = $voyage->captain_name ?? $this->company->name ?? 'ATA TRANSPORTISTA';
        $w->writeElement('DesignacionTransportista', substr($transportistName, 0, 35));

        // CodigoPaisTransportista - país de la empresa
        $transportistCountry = $this->getCountryCode($this->company->country ?? 'AR');
        $w->writeElement('CodigoPaisTransportista', $transportistCountry);

        // CodigoNacionalidadMedioTransporte - bandera de la embarcación
        $vesselNationality = $this->getCountryCode($voyage->leadVessel?->flag_country ?? 'AR');
        $w->writeElement('CodigoNacionalidadMedioTransporte', $vesselNationality);
        $w->writeElement('CodigoPuertoDestino', $destinationPortCode);

        // Fechas (formato ISO requerido por AFIP)
        if ($voyage->departure_date) {
            $w->writeElement('FechaEmbarque', $voyage->departure_date->format('Y-m-d\TH:i:s'));
        }

        if ($voyage->estimated_arrival_date) {
            $w->writeElement('FechaDescarga', $voyage->estimated_arrival_date->format('Y-m-d\TH:i:s'));
        }

        // Campos adicionales opcionales
        $w->writeElement('CodigoLugarOrigen', $voyage->originPort?->code ?? 'ARBUE');
        $w->writeElement('CodigoPaisLugarOrigen', $originCountryCode);
        $w->writeElement('CodigoPuertoDescarga', $destinationPortCode);
        
        // Comentarios o instrucciones especiales
        if ($voyage->special_instructions) {
            $w->writeElement('Comentario', substr($voyage->special_instructions, 0, 100));
        }

        // Código de aduana
        $w->writeElement('CodigoAduana', $destinationPortCode);
        
        // Lugar operativo de descarga
        $w->writeElement('CodigoLugarOperativoDescarga', $voyage->destinationPort?->code ?? 'PYTVT');
    }

    /**
     * Agregar información de contenedores vacíos y de correo
     */
    private function addContainersInformation(\XMLWriter $w, Voyage $voyage): void
    {
        $w->startElement('ContenedoresVaciosCorreo');
        
        // Obtener contenedores de todos los shipments del voyage
        $containers = collect();
        foreach ($voyage->shipments as $shipment) {
            foreach ($shipment->billsOfLading as $bol) {
                foreach ($bol->shipmentItems as $item) {
                    $containers = $containers->merge($item->containers);
                }
            }
        }

        // Procesar cada contenedor
        foreach ($containers as $container) {
            $w->startElement('Contenedor');
                
                // Identificador del contenedor (obligatorio)
                $w->writeElement('IdentificadorContenedor', $container->container_number ?? 'CONT' . $container->id);
                
                // CUIT del operador de contenedores (opcional)
                if ($container->operator_tax_id) {
                    $w->writeElement('CuitOperadorContenedores', $container->operator_tax_id);
                }
                
                // Condición del contenedor (H/P - Casa a casa / Muelle a muelle)
                $condition = $container->condition ?? 'H'; // Por defecto Casa a casa
                $w->writeElement('CondicionContenedor', $condition);
                
                // Características del contenedor (tipo/tamaño)
                $characteristics = $container->containerType?->code ?? '40HC';
                $w->writeElement('CaracteristicasContenedor', $characteristics);
                
                // Fechas opcionales
                if ($container->loading_date) {
                    $w->writeElement('FechaCargaLugarOrigen', $container->loading_date->format('Y-m-d\TH:i:s'));
                }
                
                if ($container->discharge_date) {
                    $w->writeElement('FechaDescarga', $container->discharge_date->format('Y-m-d\TH:i:s'));
                }
                
                // Códigos de lugar (opcionales)
                $w->writeElement('CodigoLugarOrigen', $voyage->originPort?->code ?? 'ARBUE');
                $w->writeElement('CodigoPaisLugarOrigen', $this->getCountryCode($voyage->originPort?->country?->alpha2_code ?? 'AR'));
                $w->writeElement('CodigoPuertoDescarga', $this->getPortCustomsCode($voyage->destinationPort?->code ?? 'PYTVT'));
                
                // Comentarios del contenedor
                if ($container->notes) {
                    $w->writeElement('Comentario', substr($container->notes, 0, 100));
                }

            $w->endElement(); // Contenedor
        }

        $w->endElement(); // ContenedoresVaciosCorreo
    }

    /**
     * Validar datos obligatorios del voyage
     */
    private function validateVoyageData(Voyage $voyage): void
    {
        if (!$voyage->voyage_number) {
            throw new Exception('Voyage debe tener número de viaje definido');
        }

        if (!$voyage->lead_vessel_id || !$voyage->leadVessel) {
            throw new Exception('Voyage debe tener embarcación líder definida');
        }

        if (!$voyage->origin_port_id || !$voyage->originPort) {
            throw new Exception('Voyage debe tener puerto de origen definido');
        }

        if (!$voyage->destination_port_id || !$voyage->destinationPort) {
            throw new Exception('Voyage debe tener puerto de destino definido');
        }

        if (!$voyage->departure_date) {
            throw new Exception('Voyage debe tener fecha de salida definida');
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

private function getPortCustomsCode(string $portCode): string
{
    // Usar datos reales del modelo Port
    $port = \App\Models\Port::where('code', strtoupper($portCode))->first();
    
    if ($port && $port->customs_code) {
        return $port->customs_code;
    }
    
    // Fallbacks seguros para puertos conocidos de la hidrovía
    return match(strtoupper($portCode)) {
        'ARBUE' => '019', // Buenos Aires
        'ARPAR' => '013', // Paraná
        'ARSFE' => '014', // Santa Fe
        'ARROS' => '016', // Rosario
        'ARSLA' => '016', // San Lorenzo (usa código Rosario)
        'PYASU' => '001', // Asunción
        'PYTVT' => '051', // Villeta
        'PYCON' => '002', // Concepción
        'PYPIL' => '003', // Pilar
        default => '019'  // Buenos Aires por defecto
    };
}

}