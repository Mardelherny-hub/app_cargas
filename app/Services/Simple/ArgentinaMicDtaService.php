<?php

namespace App\Services\Simple;

use App\Models\Voyage;
use App\Models\WebserviceTrack;
use App\Services\Simple\BaseWebserviceService;
use Exception;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * SISTEMA MODULAR WEBSERVICES - ArgentinaMicDtaService
 * 
 * Servicio específico para MIC/DTA Argentina AFIP.
 * Hereda funcionalidad común de BaseWebserviceService.
 * 
 * FLUJO SECUENCIAL AFIP OBLIGATORIO:
 * 1. RegistrarTitEnvios (genera TRACKs por shipment)
 * 2. RegistrarMicDta (usa TRACKs del paso 1)
 * 
 * INTEGRACIÓN CON SISTEMA EXISTENTE:
 * - Usa servicios base: CertificateManager, SoapClient, XmlSerializer
 * - Compatible con datos reales: MAERSK, PAR13001, V022NB
 * - Gestión WebserviceTrack para TRACKs de AFIP
 * - Sistema de logging unificado
 * 
 * DATOS AFIP REQUERIDOS:
 * - CUIT empresa (company.tax_id)
 * - Identificador viaje único (voyage.voyage_number + fecha)
 * - Datos embarcación (vessel.name, vessel.registration)
 * - Puertos origen/destino con códigos AFIP
 * - Shipments con conocimientos de embarque
 * - Contenedores con tipos normalizados
 */
class ArgentinaMicDtaService extends BaseWebserviceService
{
    /**
     * Configuración específica MIC/DTA Argentina
     */
    protected function getWebserviceConfig(): array
    {
        return [
            'webservice_type' => 'micdta',
            'country' => 'AR',
            'environment' => 'testing', // testing | production
            'webservice_url' => 'https://fwshomo.afip.gov.ar/wgesregsintia2/RegSinTIA2Service.svc',
            'soap_action' => 'Ar.Gob.Afip.Dga.wgesregsintia2/RegistrarMicDta',
            'titenvios_soap_action' => 'Ar.Gob.Afip.Dga.wgesregsintia2/RegistrarTitEnvios',
            'requires_tracks' => true,
            'validate_tracks_before_micdta' => true,
            'max_containers_per_shipment' => 50,
            'timeout_seconds' => 90,
        ];
    }

    protected function getWebserviceType(): string
    {
        return 'micdta';
    }

    protected function getCountry(): string
    {
        return 'AR';
    }

    /**
     * URL del WSDL para MIC/DTA Argentina
     */
    protected function getWsdlUrl(): string
    {
        $environment = $this->config['environment'] ?? 'testing';
        
        $urls = [
            'testing' => 'https://wsaduhomoext.afip.gob.ar/DIAV2/wgesregsintia2/wgesregsintia2.asmx?wsdl',
            'production' => 'https://wsaduext.afip.gob.ar/DIAV2/wgesregsintia2/wgesregsintia2.asmx?wsdl',
        ];
        
        return $urls[$environment] ?? $urls['testing'];
    }

    /**
     * Validaciones específicas para MIC/DTA Argentina
     */
    protected function validateSpecificData(Voyage $voyage): array
    {
        $validation = ['errors' => [], 'warnings' => []];

        try {
            // 1. Verificar CUIT empresa
            if (!$this->company->tax_id || strlen($this->company->tax_id) !== 11) {
                $validation['errors'][] = 'CUIT de empresa inválido para AFIP';
            }

            // 2. Verificar embarcación líder
            if ($voyage->leadVessel) {
                if (!$voyage->leadVessel->name) {
                    $validation['errors'][] = 'Embarcación sin nombre válido';
                }
                if (!$voyage->leadVessel->registration_number) {
                    $validation['warnings'][] = 'Embarcación sin número de registro';
                }
            }

            // 3. Verificar puertos con códigos Argentina
            if ($voyage->originPort && !$this->isValidArgentinaPort($voyage->originPort->code)) {
                $validation['warnings'][] = 'Puerto origen sin código Argentina válido';
            }
            
            if ($voyage->destinationPort && !$this->isValidArgentinaPort($voyage->destinationPort->code)) {
                $validation['warnings'][] = 'Puerto destino sin código Argentina válido';
            }

            // 4. Verificar shipments con datos mínimos
            foreach ($voyage->shipments as $shipment) {
                if (!$shipment->shipment_number) {
                    $validation['errors'][] = "Shipment {$shipment->id} sin número de embarque";
                }

                // Verificar bills of lading por shipment
                $bolCount = $shipment->billsOfLading()->count();
                if ($bolCount === 0) {
                    $validation['errors'][] = "Shipment {$shipment->shipment_number} sin conocimientos de embarque";
                }

                // Verificar peso total
                $totalWeight = $shipment->billsOfLading()
                    ->join('shipment_items', 'bills_of_lading.id', '=', 'shipment_items.bill_of_lading_id')
                    ->sum('shipment_items.gross_weight_kg');

                if ($totalWeight <= 0) {
                    $validation['warnings'][] = "Shipment {$shipment->shipment_number} sin peso de carga";
                }
            }

            // 5. Verificar contenedores
            $totalContainers = 0;
            foreach ($voyage->shipments as $shipment) {
                foreach ($shipment->billsOfLading as $bol) {
                    $containers = \DB::table('container_shipment_item')
                        ->join('shipment_items', 'container_shipment_item.shipment_item_id', '=', 'shipment_items.id')
                        ->where('shipment_items.bill_of_lading_id', $bol->id)
                        ->distinct('container_shipment_item.container_id')
                        ->count('container_shipment_item.container_id');
                    $totalContainers += $containers;
                }
            }

            if ($totalContainers === 0) {
                $validation['warnings'][] = 'Voyage sin contenedores identificados';
            }

            $this->logOperation('info', 'Validación específica MIC/DTA completada', [
                'voyage_id' => $voyage->id,
                'errors_count' => count($validation['errors']),
                'warnings_count' => count($validation['warnings']),
                'total_containers' => $totalContainers,
            ]);

        } catch (Exception $e) {
            $validation['errors'][] = 'Error en validación MIC/DTA: ' . $e->getMessage();
            $this->logOperation('error', 'Error validando datos MIC/DTA', [
                'error' => $e->getMessage(),
                'voyage_id' => $voyage->id,
            ]);
        }

        return $validation;
    }

    /**
     * Envío específico MIC/DTA con flujo secuencial AFIP
     */
    protected function sendSpecificWebservice(Voyage $voyage, array $options = []): array
    {
        try {
            $this->logOperation('info', 'Iniciando envío MIC/DTA Argentina', [
                'voyage_id' => $voyage->id,
                'voyage_number' => $voyage->voyage_number,
            ]);

            // PASO 1: RegistrarTitEnvios (generar TRACKs)
            $titEnviosResult = $this->registrarTitEnvios($voyage);
            if (!$titEnviosResult['success']) {
                return $titEnviosResult;
            }

            // PASO 2: RegistrarMicDta (usar TRACKs generados)
            $micDtaResult = $this->registrarMicDta($voyage, $titEnviosResult['tracks']);
            
            return $micDtaResult;

        } catch (Exception $e) {
            $this->logOperation('error', 'Error en envío MIC/DTA', [
                'voyage_id' => $voyage->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error_message' => $e->getMessage(),
                'error_code' => 'MICDTA_SEND_ERROR',
            ];
        }
    }

    /**
     * PASO 1: Registrar Títulos y Envíos para generar TRACKs
     */
    private function registrarTitEnvios(Voyage $voyage): array
    {
        try {
            $this->logOperation('info', 'Iniciando RegistrarTitEnvios', [
                'voyage_id' => $voyage->id,
            ]);

            // Crear cliente SOAP
            $soapClient = $this->createSoapClient();

            $this->logOperation('debug', 'SoapClient creado', [
                'wsdl_url' => $this->getWsdlUrl(),
                'client_created' => $soapClient ? 'yes' : 'no',
            ]);

            try {
                $functions = $soapClient->__getFunctions();
                $this->logOperation('debug', 'WSDL cargado correctamente', [
                    'functions_count' => count($functions),
                ]);
            } catch (Exception $e) {
                $this->logOperation('error', 'Error cargando WSDL', [
                    'error' => $e->getMessage(),
                ]);
            }

            // Procesar cada shipment del voyage
            $allTracks = [];
            
            foreach ($voyage->shipments as $shipment) {
                // Generar XML usando SimpleXmlGenerator
                $transactionId = 12345678901;
                $transactionId = substr($transactionId, 0, 20);
                $xml = $this->xmlSerializer->createRegistrarTitEnviosXml($shipment, $transactionId);

                $this->logOperation('info', 'XML enviado a AFIP - RegistrarTitEnvios', [
                    'xml_content' => $xml,
                    'xml_length' => strlen($xml),
                    'transaction_id' => $transactionId,
                    'shipment_id' => $shipment->id
                ]);

                // Debug: Mostrar XML que enviamos a AFIP
                $this->logOperation('info', 'XML enviado a AFIP - RegistrarTitEnvios', [
                    'xml_content' => $xml,
                    'xml_length' => strlen($xml),
                    'transaction_id' => $transactionId,
                    'voyage_id' => $voyage->id
                ]);
                // Envío directo a AFIP
                $response = $soapClient->__doRequest(
                    $xml,
                    $this->getWsdlUrl(),
                    'Ar.Gob.Afip.Dga.wgesregsintia2/RegistrarTitEnvios',
                    SOAP_1_1,
                    false
                );
                // VALIDAR respuesta
                if ($response === null || $response === false) {
                    $lastError = $soapClient->__getLastResponse();
                    throw new Exception("SOAP response null. Last response: " . ($lastError ?: 'No response'));
                }

                // Procesar respuesta y extraer TRACKs
                $tracks = $this->extractTracksFromResponse($response);
                if (empty($tracks)) {
                    throw new Exception("No se generaron TRACKs para shipment {$shipment->id}");
                }

                $allTracks[$shipment->id] = $tracks;

                $this->logOperation('info', 'TRACKs generados exitosamente', [
                    'shipment_id' => $shipment->id,
                    'tracks_count' => count($tracks),
                ]);
            }

            return [
                'success' => true,
                'tracks' => $allTracks,
            ];

        } catch (Exception $e) {
            $this->logOperation('error', 'Error en RegistrarTitEnvios', [
                'voyage_id' => $voyage->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error_message' => $e->getMessage(),
                'error_code' => 'TITENVIOS_ERROR',
            ];
        }
    }

    /**
     * PASO 2: Registrar MIC/DTA usando TRACKs generados
     */
    private function registrarMicDta(Voyage $voyage, array $tracks): array
    {
        try {
            $this->logOperation('info', 'Iniciando RegistrarMicDta', [
                'voyage_id' => $voyage->id,
                'tracks_count' => count($tracks),
            ]);

            // Crear cliente SOAP
            $soapClient = $this->createSoapClient();

            // Generar XML MIC/DTA con TRACKs
            $transactionId = 'MICDTA_' . time() . '_' . $voyage->id;
            $xml = $this->xmlSerializer->createRegistrarMicDtaXml($voyage, $tracks, $transactionId);

            // DEBUG: Verificar qué método se ejecutó realmente
            $this->logOperation('debug', 'Verificando XML generado', [
                'method_called' => 'createRegistrarMicDtaXml',
                'xml_contains_micdta' => strpos($xml, 'RegistrarMicDta') !== false,
                'xml_contains_titenvios' => strpos($xml, 'RegistrarTitEnvios') !== false,
                'xml_first_100' => substr($xml, 0, 100)
            ]);

            // Envío directo a AFIP
            $response = $soapClient->__doRequest(
                $xml,
                $this->getWsdlUrl(),
                'Ar.Gob.Afip.Dga.wgesregsintia2/RegistrarMicDta',
                SOAP_1_1,
                false
            );

            // Procesar respuesta
            $result = $this->processMicDtaResponse($response);

            $this->logOperation('info', 'MIC/DTA procesado exitosamente', [
                'voyage_id' => $voyage->id,
                'mic_dta_id' => $result['mic_dta_id'] ?? null,
            ]);

            return [
                'success' => true,
                'mic_dta_id' => $result['mic_dta_id'] ?? null,
                'response_data' => $result,
            ];

        } catch (Exception $e) {
            $this->logOperation('error', 'Error en RegistrarMicDta', [
                'voyage_id' => $voyage->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error_message' => $e->getMessage(),
                'error_code' => 'MICDTA_ERROR',
            ];
        }
    }
    

    // ====================================
    // MÉTODOS AUXILIARES
    // ====================================

    /**
     * Generar XML para RegistrarTitEnvios
     */
    private function generateTitEnviosXml(Voyage $voyage): array
    {
        $results = [];
        try {
            foreach ($voyage->shipments as $shipment) {
                $xml = $this->xmlSerializer->createRegistrarTitEnviosXml($shipment, $this->generateTransactionId());
                if ($xml) {
                    $results[] = [
                        'shipment_id' => $shipment->id,
                        'xml' => $xml
                    ];
                }
            }
            return $results;
        } catch (Exception $e) {
            $this->logOperation('error', 'Error generando XML TitEnvios', [
                'error' => $e->getMessage(),
                'voyage_id' => $voyage->id,
            ]);
            return [];
        }
    }

    /**
     * Generar XML para RegistrarMicDta
     */
    private function generateMicDtaXml(Voyage $voyage, array $tracks): ?string
    {
        try {
            return $this->xmlSerializer->createMicDtaXml($voyage, $tracks, $this->generateTransactionId());
        } catch (Exception $e) {
            $this->logOperation('error', 'Error generando XML MicDta', [
                'error' => $e->getMessage(),
                'voyage_id' => $voyage->id,
            ]);
            return null;
        }
    }

    
    /**
     * Guardar TRACKs en base de datos
     */
    private function saveTracks(Voyage $voyage, array $tracks): void
    {
        try {
            $shipments = $voyage->shipments()->get();
            
            foreach ($tracks as $index => $trackData) {
                $shipment = $shipments[$index] ?? null;
                
                if ($shipment) {
                    WebserviceTrack::create([
                        'company_id' => $this->company->id,
                        'voyage_id' => $voyage->id,
                        'shipment_id' => $shipment->id,
                        'track_number' => $trackData['track_number'],
                        'webservice_type' => $this->getWebserviceType(),
                        'country' => $this->getCountry(),
                        'status' => 'active',
                        'generated_at' => $trackData['generated_at'],
                    ]);
                }
            }

            $this->logOperation('info', 'TRACKs guardados en base de datos', [
                'tracks_saved' => count($tracks),
                'voyage_id' => $voyage->id,
            ]);

        } catch (Exception $e) {
            $this->logOperation('error', 'Error guardando TRACKs', [
                'error' => $e->getMessage(),
                'voyage_id' => $voyage->id,
            ]);
            throw $e;
        }
    }

    /**
     * Extraer número de confirmación de respuesta MicDta
     */
    private function extractConfirmationFromResponse(string $responseXml): ?string
    {
        try {
            $dom = new \DOMDocument();
            $dom->loadXML($responseXml);
            
            // Buscar número de confirmación en respuesta AFIP
            $confirmationElements = $dom->getElementsByTagName('NumeroConfirmacion');
            if ($confirmationElements->length > 0) {
                return $confirmationElements->item(0)->textContent;
            }

            return null;

        } catch (Exception $e) {
            $this->logOperation('error', 'Error extrayendo confirmación', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Verificar si puerto tiene código Argentina válido
     */
    private function isValidArgentinaPort(string $portCode): bool
    {
        // Para MIC/DTA Argentina, aceptar puertos argentinos + paraguayos con afip_code
        $argentinaCodes = ['ARBUE', 'ARRSA', 'ARSFE', 'ARPAR', 'ARCAM'];
        
        if (in_array($portCode, $argentinaCodes)) {
            return true;
        }
        
        // Verificar puertos paraguayos con afip_code configurado
        $port = \App\Models\Port::where('code', $portCode)->first();
        return $port && isset($port->webservice_config['afip_code']);
    }

    /**
     * Obtener transacción actual
     */
    private function getCurrentTransaction(): ?\App\Models\WebserviceTransaction
    {
        if ($this->currentTransactionId) {
            return \App\Models\WebserviceTransaction::find($this->currentTransactionId);
        }
        return null;
    }

    /**
     * Extraer TRACKs de la respuesta AFIP
     */
    private function extractTracksFromResponse(string $response): array
    {
        if (strpos($response, 'soap:Fault') !== false) {
            // Extraer mensaje de error
            if (preg_match('/<faultstring>([^<]+)<\/faultstring>/', $response, $matches)) {
                $errorMsg = $matches[1];
                $this->logOperation('error', 'SOAP Fault recibido de AFIP', [
                    'fault_string' => $errorMsg,
                    'response_full' => $response
                ]);
                throw new Exception("Error SOAP de AFIP: " . $errorMsg);
            }
        }
        if (!$response) {
            return [];
        }
        // LOG para debug - ver qué devuelve AFIP
        $this->logOperation('debug', 'Respuesta AFIP completa para análisis', [
            'response_content' => $response,
            'response_length' => strlen($response),
        ]);
        
        $tracks = [];
        
        // Múltiples formatos posibles de AFIP
        if (preg_match_all('/<TracksEnv>([^<]+)<\/TracksEnv>/', $response, $matches)) {
            $tracks = $matches[1];
        } elseif (preg_match_all('/<Track>([^<]+)<\/Track>/', $response, $matches)) {
            $tracks = $matches[1];
        } elseif (preg_match_all('/<trackId>([^<]+)<\/trackId>/', $response, $matches)) {
            $tracks = $matches[1];
        }
        
        $this->logOperation('info', 'TRACKs extraídos de respuesta', [
            'tracks_found' => count($tracks),
            'tracks' => $tracks,
        ]);
        
        return $tracks;
    }

    /**
     * Procesar respuesta MIC/DTA
     */
    private function processMicDtaResponse(string $response): array
    {
        $result = ['mic_dta_id' => null];
        
        // Buscar ID de MIC/DTA en la respuesta
        if (preg_match('/<MicDtaId>([^<]+)<\/MicDtaId>/', $response, $matches)) {
            $result['mic_dta_id'] = $matches[1];
        }
        
        return $result;
    }
}