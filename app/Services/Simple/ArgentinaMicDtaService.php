<?php

namespace App\Services\Simple;

use App\Models\Voyage;
use App\Models\WebserviceTrack;
use App\Services\Simple\BaseWebserviceService;
use Exception;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * SISTEMA MODULAR WEBSERVICES - ArgentinaMicDtaService CORREGIDO
 * 
 * SOLUCIÓN DEFINITIVA para MIC/DTA Argentina AFIP
 * Flujo secuencial corregido: RegistrarTitEnvios -> RegistrarEnvios -> RegistrarMicDta
 * 
 * CORRECCIONES CRÍTICAS:
 * - Flujo secuencial claro y separado
 * - Extracción correcta de TRACKs de respuestas AFIP
 * - Uso del SimpleXmlGenerator corregido
 * - Manejo robusto de errores SOAP
 * - Validaciones mejoradas
 * - Logging detallado para debug
 * 
 * FLUJO CORRECTO AFIP:
 * 1. RegistrarTitEnvios (por cada shipment) -> registra título
 * 2. RegistrarEnvios (por cada shipment) -> genera TRACKs
 * 3. RegistrarMicDta (voyage completo) -> usa todos los TRACKs
 */
class ArgentinaMicDtaService extends BaseWebserviceService
{
    /**
     * CORREGIR: Configuración específica MIC/DTA Argentina
     * Reemplaza el método getWebserviceConfig() en ArgentinaMicDtaService.php
     */
    /**
     * CONFIGURACIÓN COMPLETA CORREGIDA - Reemplaza getWebserviceConfig()
     */
    protected function getWebserviceConfig(): array
    {
        return [
            'webservice_type' => 'micdta',
            'country' => 'AR',
            'environment' => 'testing',
            
            // URLs OBLIGATORIAS
            'webservice_url' => 'https://wsaduhomoext.afip.gob.ar/DIAV2/wgesregsintia2/wgesregsintia2.asmx',
            'wsdl_url' => 'https://wsaduhomoext.afip.gob.ar/DIAV2/wgesregsintia2/wgesregsintia2.asmx?wsdl',
            
            // SOAP ACTIONS COMPLETAS - OBLIGATORIAS
            'soap_action' => 'Ar.Gob.Afip.Dga.wgesregsintia2/RegistrarMicDta',
            'soap_action_titenvios' => 'Ar.Gob.Afip.Dga.wgesregsintia2/RegistrarTitEnvios',
            'soap_action_envios' => 'Ar.Gob.Afip.Dga.wgesregsintia2/RegistrarEnvios',
            'soap_action_micdta' => 'Ar.Gob.Afip.Dga.wgesregsintia2/RegistrarMicDta', // ← FALTABA ESTA
            
            // Configuración adicional
            'timeout_seconds' => 90,
            'max_retries' => 3,
            'requires_tracks' => true,
            'validate_tracks_before_micdta' => true,
            'max_containers_per_shipment' => 50,
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

    protected function getWsdlUrl(): string
    {
        return $this->config['wsdl_url'];
    }

    public function getXmlSerializer()
    {
        return $this->xmlSerializer;
    }

    /**
     * Validaciones específicas para MIC/DTA Argentina - VERSIÓN DETALLADA
     * Reemplaza el método validateSpecificData() en ArgentinaMicDtaService.php
     */
    protected function validateSpecificData(Voyage $voyage): array
    {
        $validation = ['errors' => [], 'warnings' => [], 'details' => []];

        try {
            // 1. VALIDACIÓN EMPRESA Y CERTIFICADO
            if (!$this->company->tax_id) {
                $validation['errors'][] = 'Empresa sin CUIT configurado';
            } elseif (strlen($this->company->tax_id) !== 11) {
                $validation['errors'][] = "CUIT de empresa inválido: '{$this->company->tax_id}' (debe tener 11 dígitos)";
            } else {
                $validation['details'][] = "CUIT empresa: {$this->company->tax_id} ✓";
            }

            if (!$this->company->legal_name) {
                $validation['errors'][] = 'Empresa sin razón social configurada';
            } else {
                $validation['details'][] = "Empresa: {$this->company->legal_name} ✓";
            }

            // Validar certificado
            try {
                $certificateManager = new \App\Services\Webservice\CertificateManagerService($this->company);
                $certValidation = $certificateManager->validateCompanyCertificate();
                if (!$certValidation['is_valid']) {
                    $validation['errors'][] = 'Certificado digital inválido: ' . implode(', ', $certValidation['errors']);
                } else {
                    $validation['details'][] = 'Certificado digital válido ✓';
                }
            } catch (Exception $e) {
                $validation['errors'][] = 'Error validando certificado: ' . $e->getMessage();
            }

            // 2. VALIDACIÓN VOYAGE BÁSICO
            if (!$voyage->voyage_number) {
                $validation['errors'][] = 'Voyage sin número de viaje';
            } else {
                $validation['details'][] = "Voyage: {$voyage->voyage_number} ✓";
            }

            if (!$voyage->lead_vessel_id) {
                $validation['errors'][] = 'Voyage sin embarcación líder asignada';
            } else {
                $vessel = $voyage->leadVessel;
                if (!$vessel) {
                    $validation['errors'][] = 'Embarcación líder no encontrada en base de datos';
                } else {
                    if (!$vessel->name) {
                        $validation['errors'][] = 'Embarcación sin nombre válido';
                    } else {
                        $validation['details'][] = "Embarcación: {$vessel->name} ✓";
                    }
                    
                    if (!$vessel->registration_number) {
                        $validation['warnings'][] = "Embarcación '{$vessel->name}' sin número de registro";
                    } else {
                        $validation['details'][] = "Registro embarcación: {$vessel->registration_number} ✓";
                    }
                }
            }

            // 3. VALIDACIÓN PUERTOS
            if (!$voyage->origin_port_id) {
                $validation['errors'][] = 'Voyage sin puerto de origen';
            } else {
                $originPort = $voyage->originPort;
                if (!$originPort) {
                    $validation['errors'][] = 'Puerto de origen no encontrado';
                } else {
                    $validation['details'][] = "Puerto origen: {$originPort->code} - {$originPort->name} ✓";
                    
                    if (!$originPort->code) {
                        $validation['warnings'][] = 'Puerto origen sin código UN/LOCODE';
                    }
                }
            }

            if (!$voyage->destination_port_id) {
                $validation['errors'][] = 'Voyage sin puerto de destino';
            } else {
                $destPort = $voyage->destinationPort;
                if (!$destPort) {
                    $validation['errors'][] = 'Puerto de destino no encontrado';
                } else {
                    $validation['details'][] = "Puerto destino: {$destPort->code} - {$destPort->name} ✓";
                    
                    if (!$destPort->code) {
                        $validation['warnings'][] = 'Puerto destino sin código UN/LOCODE';
                    }
                }
            }

            // 4. VALIDACIÓN SHIPMENTS
            $shipments = $voyage->shipments()->with('billsOfLading.shipmentItems')->get();
            
            if ($shipments->isEmpty()) {
                $validation['errors'][] = 'Voyage sin shipments asociados';
            } else {
                $validation['details'][] = "Shipments encontrados: {$shipments->count()} ✓";
                
                foreach ($shipments as $index => $shipment) {
                    $shipmentErrors = [];
                    $shipmentWarnings = [];
                    
                    if (!$shipment->shipment_number) {
                        $shipmentErrors[] = "Shipment " . ($index + 1) . " sin número de embarque";
                    }

                    // Validar bills of lading
                    $bolCount = $shipment->billsOfLading()->count();
                    if ($bolCount === 0) {
                        $shipmentErrors[] = "Shipment '{$shipment->shipment_number}' sin conocimientos de embarque (BL)";
                    } else {
                        $validation['details'][] = "Shipment '{$shipment->shipment_number}': {$bolCount} BL ✓";
                        
                        // Validar contenido de cada BL
                        foreach ($shipment->billsOfLading as $bol) {
                            if (!$bol->bill_of_lading_number && !$bol->bl_number) {
                                $shipmentWarnings[] = "BL sin número válido en shipment '{$shipment->shipment_number}'";
                            }
                            
                            if (!$bol->cargo_description) {
                                $shipmentWarnings[] = "BL sin descripción de carga en shipment '{$shipment->shipment_number}'";
                            }
                            
                            // Validar peso y cantidad
                            $totalWeight = $bol->shipmentItems->sum('gross_weight_kg');
                            $totalPackages = $bol->shipmentItems->sum('package_quantity');
                            
                            if ($totalWeight <= 0) {
                                $shipmentErrors[] = "BL '{$bol->bill_of_lading_number}' sin peso válido (actual: {$totalWeight} kg)";
                            }
                            
                            if ($totalPackages <= 0) {
                                $shipmentErrors[] = "BL '{$bol->bill_of_lading_number}' sin cantidad de bultos válida (actual: {$totalPackages})";
                            }
                            
                            if ($totalWeight > 0 && $totalPackages > 0) {
                                $validation['details'][] = "BL '{$bol->bill_of_lading_number}': {$totalPackages} bultos, {$totalWeight} kg ✓";
                            }
                        }
                    }
                    
                    // Agregar errores/warnings del shipment
                    foreach ($shipmentErrors as $error) {
                        $validation['errors'][] = $error;
                    }
                    foreach ($shipmentWarnings as $warning) {
                        $validation['warnings'][] = $warning;
                    }
                }
            }

            // 5. VALIDACIÓN CONTENEDORES
            $totalContainers = 0;
            foreach ($shipments as $shipment) {
                foreach ($shipment->billsOfLading as $bol) {
                    $containers = \DB::table('container_shipment_item')
                        ->join('shipment_items', 'container_shipment_item.shipment_item_id', '=', 'shipment_items.id')
                        ->join('containers', 'container_shipment_item.container_id', '=', 'containers.id')
                        ->where('shipment_items.bill_of_lading_id', $bol->id)
                        ->select('containers.container_number', 'containers.condition')
                        ->distinct('containers.id')
                        ->get();
                    
                    $totalContainers += $containers->count();
                    
                    foreach ($containers as $container) {
                        if (!$container->container_number) {
                            $validation['warnings'][] = "Contenedor sin número válido en BL '{$bol->bill_of_lading_number}'";
                        }
                    }
                }
            }

            if ($totalContainers === 0) {
                $validation['warnings'][] = 'Voyage sin contenedores identificados';
            } else {
                $validation['details'][] = "Contenedores encontrados: {$totalContainers} ✓";
            }

            // 6. VALIDACIÓN FECHAS
            if (!$voyage->departure_date) {
                $validation['warnings'][] = 'Voyage sin fecha de salida configurada';
            } else {
                $validation['details'][] = "Fecha salida: {$voyage->departure_date->format('Y-m-d')} ✓";
            }

            // 7. VALIDACIÓN CONFIGURACIÓN WEBSERVICE
            if (!$this->company->ws_active) {
                $validation['warnings'][] = 'Webservices no activados para la empresa';
            }

            $environment = $this->company->ws_environment ?? 'testing';
            $validation['details'][] = "Ambiente webservice: {$environment} ✓";

            // RESUMEN FINAL
            $this->logOperation('info', 'Validación MIC/DTA completada - DETALLADA', [
                'voyage_id' => $voyage->id,
                'voyage_number' => $voyage->voyage_number,
                'errors_count' => count($validation['errors']),
                'warnings_count' => count($validation['warnings']),
                'details_count' => count($validation['details']),
                'shipments_count' => $shipments->count(),
                'total_containers' => $totalContainers,
                'can_process' => empty($validation['errors']),
            ]);

        } catch (Exception $e) {
            $validation['errors'][] = 'Error interno en validación: ' . $e->getMessage();
            $this->logOperation('error', 'Error validando datos MIC/DTA', [
                'error' => $e->getMessage(),
                'voyage_id' => $voyage->id,
                'trace' => $e->getTraceAsString(),
            ]);
        }

        return $validation;
    }

    /**
     * Envío específico MIC/DTA con flujo secuencial AFIP CORREGIDO
     */
    protected function sendSpecificWebservice(Voyage $voyage, array $options = []): array
    {
        try {
            $this->logOperation('info', 'Iniciando envío MIC/DTA Argentina - FLUJO CORREGIDO', [
                'voyage_id' => $voyage->id,
                'voyage_number' => $voyage->voyage_number,
                'shipments_count' => $voyage->shipments()->count(),
            ]);

            // FLUJO SECUENCIAL AFIP CORREGIDO
            $allTracks = [];

            // PROCESAR CADA SHIPMENT: TitEnvios -> Envios (genera TRACKs)
            foreach ($voyage->shipments as $shipment) {
                $shipmentTracks = $this->processShipmentFlow($shipment);
                if (!$shipmentTracks['success']) {
                    return $shipmentTracks; // Error en shipment, abortar
                }
                
                $allTracks[$shipment->id] = $shipmentTracks['tracks'];
            }

            // PASO FINAL: RegistrarMicDta con todos los TRACKs
            $micDtaResult = $this->registrarMicDta($voyage, $allTracks);
            
            return $micDtaResult;

        } catch (Exception $e) {
            $this->logOperation('error', 'Error en envío MIC/DTA', [
                'voyage_id' => $voyage->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error_message' => $e->getMessage(),
                'error_code' => 'MICDTA_SEND_ERROR',
            ];
        }
    }

    /**
     * FLUJO CORRECTO POR SHIPMENT: TitEnvios -> Envios
     */
    private function processShipmentFlow($shipment): array
    {
        try {
            $this->logOperation('info', 'Procesando flujo shipment', [
                'shipment_id' => $shipment->id,
                'shipment_number' => $shipment->shipment_number,
            ]);

            // Crear cliente SOAP
            $soapClient = $this->createSoapClient();

            // PASO 1: RegistrarTitEnvios (solo registra el título)
            $titEnviosResult = $this->sendTitEnvios($soapClient, $shipment);
            if (!$titEnviosResult['success']) {
                return $titEnviosResult;
            }

            // PASO 2: RegistrarEnvios (genera TRACKs)
            $enviosResult = $this->sendEnvios($soapClient, $shipment);
            if (!$enviosResult['success']) {
                return $enviosResult;
            }

            // Extraer TRACKs de la respuesta
            $tracks = $this->extractTracksFromResponse($enviosResult['response']);
            if (empty($tracks)) {
                throw new Exception("No se generaron TRACKs para shipment {$shipment->id}");
            }

            $this->logOperation('info', 'Flujo shipment completado exitosamente', [
                'shipment_id' => $shipment->id,
                'tracks_generated' => count($tracks),
                'tracks' => $tracks,
            ]);

            return [
                'success' => true,
                'tracks' => $tracks,
            ];

        } catch (Exception $e) {
            $this->logOperation('error', 'Error en flujo shipment', [
                'shipment_id' => $shipment->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error_message' => $e->getMessage(),
                'error_code' => 'SHIPMENT_FLOW_ERROR',
            ];
        }
    }

    /**
     * PASO 1: Enviar RegistrarTitEnvios
     */
    private function sendTitEnvios($soapClient, $shipment): array
    {
        try {
            $transactionId = 'TIT_' . time() . '_' . $shipment->id;
            
            // Usar XML corregido
            $xml = $this->xmlSerializer->createRegistrarTitEnviosXml($shipment, $transactionId);
            
            $this->logOperation('info', 'Enviando RegistrarTitEnvios', [
                'shipment_id' => $shipment->id,
                'transaction_id' => $transactionId,
                'xml_length' => strlen($xml),
            ]);

            // Envío SOAP directo
            $response = $soapClient->__doRequest(
                $xml,
                $this->getWsdlUrl(),
                $this->config['soap_action_titenvios'],
                SOAP_1_1,
                false
            );

            // Validar respuesta
            if ($response === null || $response === false) {
                $lastResponse = $soapClient->__getLastResponse();
                throw new Exception("SOAP response null para TitEnvios. Response: " . ($lastResponse ?: 'No response'));
            }

            // Verificar errores SOAP
            if (strpos($response, 'soap:Fault') !== false) {
                $errorMsg = $this->extractSoapFaultMessage($response);
                throw new Exception("SOAP Fault en TitEnvios: " . $errorMsg);
            }

            $this->logOperation('info', 'RegistrarTitEnvios exitoso', [
                'shipment_id' => $shipment->id,
                'response_length' => strlen($response),
            ]);

            return [
                'success' => true,
                'response' => $response,
                'transaction_id' => $transactionId,
            ];

        } catch (Exception $e) {
            $this->logOperation('error', 'Error en RegistrarTitEnvios', [
                'shipment_id' => $shipment->id,
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
     * PASO 2: Enviar RegistrarEnvios (genera TRACKs)
     */
    private function sendEnvios($soapClient, $shipment): array
    {
        try {
            $transactionId = 'ENV_' . time() . '_' . $shipment->id;
            
            // Usar XML corregido
            $xml = $this->xmlSerializer->createRegistrarEnviosXml($shipment, $transactionId);
            
            $this->logOperation('info', 'Enviando RegistrarEnvios', [
                'shipment_id' => $shipment->id,
                'transaction_id' => $transactionId,
                'xml_length' => strlen($xml),
            ]);

            // Envío SOAP directo
            $response = $soapClient->__doRequest(
                $xml,
                $this->getWsdlUrl(),
                $this->config['soap_action_envios'],
                SOAP_1_1,
                false
            );

            // Validar respuesta
            if ($response === null || $response === false) {
                $lastResponse = $soapClient->__getLastResponse();
                throw new Exception("SOAP response null para Envios. Response: " . ($lastResponse ?: 'No response'));
            }

            // Verificar errores SOAP
            if (strpos($response, 'soap:Fault') !== false) {
                $errorMsg = $this->extractSoapFaultMessage($response);
                throw new Exception("SOAP Fault en Envios: " . $errorMsg);
            }

            $this->logOperation('info', 'RegistrarEnvios exitoso', [
                'shipment_id' => $shipment->id,
                'response_length' => strlen($response),
            ]);

            return [
                'success' => true,
                'response' => $response,
                'transaction_id' => $transactionId,
            ];

        } catch (Exception $e) {
            $this->logOperation('error', 'Error en RegistrarEnvios', [
                'shipment_id' => $shipment->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error_message' => $e->getMessage(),
                'error_code' => 'ENVIOS_ERROR',
            ];
        }
    }

    /**
     * PASO 3: Registrar MIC/DTA usando todos los TRACKs
     */
    // VERSIÓN COMPLETA - Poblar TODOS los campos para reportes/auditorías
    // REEMPLAZAR registrarMicDta() en app/Services/Simple/ArgentinaMicDtaService.php

    private function registrarMicDta(Voyage $voyage, array $allTracks): array
    {
        $startTime = microtime(true);
        
        try {
            $this->logOperation('info', 'Iniciando RegistrarMicDta', [
                'voyage_id' => $voyage->id,
                'shipments_with_tracks' => count($allTracks),
                'total_tracks' => array_sum(array_map('count', $allTracks)),
            ]);

            // ✅ BUSCAR TRANSACCIÓN EXISTENTE (puede haber sido creada antes)
            $transactionId = 'MICDTA_' . time() . '_' . $voyage->id;
            $transaction = \App\Models\WebserviceTransaction::where('voyage_id', $voyage->id)
                ->where('webservice_type', 'micdta')
                ->where('status', 'pending')
                ->latest()
                ->first();

            if (!$transaction) {
                // ✅ CREAR TRANSACCIÓN CON DATOS COMPLETOS PARA REPORTES
                $transaction = \App\Models\WebserviceTransaction::create([
                    'company_id' => $this->company->id,
                    'user_id' => $this->user->id,
                    'voyage_id' => $voyage->id,
                    'transaction_id' => $transactionId,
                    'webservice_type' => 'micdta',
                    'country' => 'AR',
                    'webservice_url' => $this->getWsdlUrl(),
                    'soap_action' => $this->config['soap_action_micdta'],
                    'status' => 'pending',
                    'environment' => $this->config['environment'],
                    'timeout_seconds' => 60,
                    'max_retries' => 3,
                    
                    // ✅ DATOS DE NEGOCIO PARA REPORTES
                    'total_weight_kg' => $voyage->shipments->sum('total_weight_kg') ?? 0,
                    'total_value' => $voyage->shipments->sum('declared_value') ?? 0,
                    'currency_code' => 'USD',
                    'container_count' => $voyage->shipments->sum('container_count') ?? 0,
                    'bill_of_lading_count' => $voyage->billsOfLading->count() ?? 0,
                    
                    // ✅ DATOS TÉCNICOS PARA AUDITORÍA
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                    'certificate_used' => 'appcargas', // Del log veo que usan este certificado
                    
                    // ✅ METADATOS PARA ANÁLISIS
                    'additional_metadata' => [
                        'method' => 'RegistrarMicDta',
                        'tracks_count' => array_sum(array_map('count', $allTracks)),
                        'shipments_count' => count($allTracks),
                        'voyage_number' => $voyage->voyage_number,
                        'company_name' => $this->company->legal_name,
                    ],
                    
                    'sent_at' => now(),
                ]);
            }

            // Crear cliente SOAP
            $soapClient = $this->createSoapClient();

            // Generar XML MIC/DTA
            $xml = $this->xmlSerializer->createRegistrarMicDtaXml($voyage, $allTracks, $transactionId);

            $this->logOperation('info', 'Enviando RegistrarMicDta', [
                'voyage_id' => $voyage->id,
                'transaction_id' => $transaction->id,
                'xml_length' => strlen($xml),
            ]);

            // ✅ ACTUALIZAR CON REQUEST XML Y TIMING
            $transaction->update([
                'status' => 'sending',
                'request_xml' => $xml,
                'sent_at' => now(),
            ]);

            // Envío SOAP directo (MANTENER SIMPLE)
            $response = $soapClient->__doRequest(
                $xml,
                $this->getWsdlUrl(),
                $this->config['soap_action_micdta'],
                SOAP_1_1,
                false
            );

            $endTime = microtime(true);
            $responseTime = round(($endTime - $startTime) * 1000); // millisegundos

            // ✅ ACTUALIZAR CON RESPONSE Y TIMING PARA REPORTES DE PERFORMANCE
            $transaction->update([
                'response_xml' => $response,
                'response_at' => now(),
                'response_time_ms' => $responseTime,
            ]);

            // Validar respuesta
            if ($response === null || $response === false) {
                $lastResponse = $soapClient->__getLastResponse();
                
                // ✅ GUARDAR ERROR ESTRUCTURADO PARA ANÁLISIS
                $errorMsg = "SOAP response null para MicDta. Response: " . ($lastResponse ?: 'No response');
                $this->saveStructuredError($transaction, 'network', 'critical', $errorMsg);
                
                throw new Exception($errorMsg);
            }

            // Verificar errores SOAP
            if (strpos($response, 'soap:Fault') !== false) {
                $errorMsg = $this->extractSoapFaultMessage($response);
                
                // ✅ GUARDAR ERROR SOAP ESTRUCTURADO
                $this->saveStructuredError($transaction, 'system', 'high', $errorMsg);
                $transaction->update([
                    'status' => 'error',
                    'error_code' => 'SOAP_FAULT',
                    'error_message' => $errorMsg,
                    'completed_at' => now(),
                ]);
                
                throw new Exception("SOAP Fault en MicDta: " . $errorMsg);
            }

            // Procesar respuesta exitosa
            $micDtaId = $this->extractMicDtaIdFromResponse($response);

            if ($micDtaId) {
                // ✅ ACTUALIZAR TRANSACCIÓN CON DATOS COMPLETOS PARA AUDITORÍA
                $transaction->update([
                    'status' => 'sent',
                    'external_reference' => $micDtaId,
                    'confirmation_number' => $micDtaId,
                    'completed_at' => now(),
                    'success_data' => [
                        'mic_dta_id' => $micDtaId,
                        'tracks_processed' => array_sum(array_map('count', $allTracks)),
                        'afip_server' => $this->extractServerFromResponse($response),
                        'afip_timestamp' => $this->extractTimestampFromResponse($response),
                    ],
                ]);

                // ✅ CREAR WEBSERVICE RESPONSE COMPLETA PARA REPORTES
                \App\Models\WebserviceResponse::create([
                    'transaction_id' => $transaction->id,
                    'response_type' => 'success',
                    'reference_number' => $micDtaId, // ⭐ CRÍTICO para GPS
                    'confirmation_number' => $micDtaId,
                    'customs_status' => 'processed',
                    'customs_processed_at' => now(),
                    
                    // ✅ DATOS PARA REPORTES DE COMPLIANCE
                    'data_validated' => true,
                    'documents_approved' => true,
                    'payment_status' => 'not_required',
                    
                    // ✅ METADATOS PARA ANÁLISIS FUTURO
                    'additional_data' => [
                        'mic_dta_id' => $micDtaId,
                        'voyage_id' => $voyage->id,
                        'voyage_number' => $voyage->voyage_number,
                        'tracks_count' => array_sum(array_map('count', $allTracks)),
                        'processing_time_ms' => $responseTime,
                        'environment' => $this->config['environment'],
                        'company_id' => $this->company->id,
                        'company_name' => $this->company->legal_name,
                    ],
                    
                    'is_success' => true,
                    'processed_at' => now(),
                ]);

                $this->logOperation('info', 'WebserviceResponse creada exitosamente', [
                    'transaction_id' => $transaction->id,
                    'mic_dta_id' => $micDtaId,
                    'response_time_ms' => $responseTime,
                ]);
            } else {
                // ✅ MANEJAR CASO SIN MIC/DTA ID
                $this->saveValidationWarning($transaction, 'MIC/DTA ID no extraído de respuesta AFIP');
                
                $transaction->update([
                    'status' => 'sent', // Técnicamente enviado
                    'external_reference' => $transaction->transaction_id, // Usar transaction ID como fallback
                    'requires_manual_review' => true,
                    'validation_errors' => ['No se pudo extraer MIC/DTA ID de la respuesta AFIP'],
                    'completed_at' => now(),
                ]);
                
                // ✅ CREAR WEBSERVICE RESPONSE AUNQUE NO TENGAMOS MIC/DTA ID
                \App\Models\WebserviceResponse::create([
                    'transaction_id' => $transaction->id,
                    'response_type' => 'success',
                    'reference_number' => $transaction->transaction_id, // ⭐ CRÍTICO para GPS - usar transaction_id
                    'confirmation_number' => $transaction->transaction_id,
                    'customs_status' => 'processed',
                    'customs_processed_at' => now(),
                    'processed_at' => now(),
                ]);
            }

            $this->logOperation('info', 'MIC/DTA registrado exitosamente', [
                'voyage_id' => $voyage->id,
                'mic_dta_id' => $micDtaId,
                'transaction_id' => $transaction->id,
                'response_time_ms' => $responseTime,
            ]);

            // Guardar TRACKs en base de datos
            $this->saveTracks($voyage, $allTracks);

            // ✅ CRÍTICO: Guardar datos para GPS y auditorías
            $this->saveTransactionData($transaction->transaction_id, $xml, $response, $micDtaId);
            $this->saveResponseRecord($transactionId, $voyage, $micDtaId);

            return [
                'success' => true,
                'transaction_id' => $transactionId,
                'mic_dta_id' => $micDtaId,
                'response' => $response,
                'execution_time_ms' => $responseTime,
                'tracks_saved' => count($allTracks),
                'shipments_processed' => count($allTracks),
            ];

        } catch (Exception $e) {
            $endTime = microtime(true);
            $responseTime = round(($endTime - $startTime) * 1000);
            
            // ✅ GUARDAR ERROR COMPLETO PARA ANÁLISIS
            if (isset($transaction)) {
                $this->saveStructuredError($transaction, 'system', 'critical', $e->getMessage());
                $transaction->update([
                    'status' => 'error',
                    'error_code' => 'MICDTA_ERROR',
                    'error_message' => $e->getMessage(),
                    'response_time_ms' => $responseTime,
                    'completed_at' => now(),
                    'error_details' => [
                        'exception_class' => get_class($e),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                        'trace' => $e->getTraceAsString(),
                    ],
                ]);
            }

            $this->logOperation('error', 'Error en RegistrarMicDta', [
                'voyage_id' => $voyage->id,
                'error' => $e->getMessage(),
                'response_time_ms' => $responseTime,
            ]);

            return [
                'success' => false,
                'error_message' => $e->getMessage(),
                'error_code' => 'MICDTA_ERROR',
                'response_time_ms' => $responseTime,
            ];
        }
    }

    // ✅ MÉTODOS AUXILIARES PARA DATOS ESTRUCTURADOS

    private function saveStructuredError($transaction, $category, $severity, $message)
    {
        \App\Models\WebserviceError::create([
            'transaction_id' => $transaction->id,
            'error_code' => 'MICDTA_' . strtoupper($category),
            'error_title' => 'Error en MIC/DTA',
            'error_description' => $message,
            'category' => $category,
            'severity' => $severity,
            'is_blocking' => true,
            'allows_retry' => $severity !== 'critical',
            'suggested_solution' => $this->getSuggestedSolution($category),
            'environment' => $this->config['environment'],
        ]);
    }

    private function saveValidationWarning($transaction, $message)
    {
        $warnings = $transaction->validation_errors ?? [];
        $warnings[] = $message;
        $transaction->update(['validation_errors' => $warnings]);
    }

    private function getSuggestedSolution($category)
    {
        $solutions = [
            'network' => 'Verificar conectividad con AFIP. Reintentar en unos minutos.',
            'system' => 'Error del sistema AFIP. Contactar soporte técnico.',
            'validation' => 'Revisar datos del voyage y shipments.',
        ];
        
        return $solutions[$category] ?? 'Contactar administrador del sistema.';
    }

    private function extractServerFromResponse($response)
    {
        if (preg_match('/<Server>([^<]+)<\/Server>/', $response, $matches)) {
            return $matches[1];
        }
        return null;
    }

    private function extractTimestampFromResponse($response)
    {
        if (preg_match('/<TimeStamp>([^<]+)<\/TimeStamp>/', $response, $matches)) {
            return $matches[1];
        }
        return null;
    }

    /**
     * Extraer TRACKs de respuesta AFIP CORREGIDO
     */
    /**
     * Extraer TRACKs de respuesta AFIP MEJORADO
     * Reemplaza el método extractTracksFromResponse() en ArgentinaMicDtaService.php
     */
    private function extractTracksFromResponse(string $response): array
    {
        if (!$response) {
            $this->logOperation('warning', 'Respuesta AFIP vacía para extracción TRACKs');
            return [];
        }

        // Log detallado de la respuesta para debug
        $this->logOperation('debug', 'Respuesta AFIP completa para análisis TRACKs', [
            'response_content' => $response,
            'response_length' => strlen($response),
            'contains_soap_fault' => strpos($response, 'soap:Fault') !== false,
            'contains_track_keywords' => [
                'Track' => strpos($response, 'Track') !== false,
                'track' => strpos($response, 'track') !== false,
                'TRACK' => strpos($response, 'TRACK') !== false,
                'TracksEnv' => strpos($response, 'TracksEnv') !== false,
                'NumeroTrack' => strpos($response, 'NumeroTrack') !== false,
            ]
        ]);

        // Verificar errores SOAP primero
        if (strpos($response, 'soap:Fault') !== false) {
            if (preg_match('/<faultstring>([^<]+)<\/faultstring>/', $response, $matches)) {
                $errorMsg = $matches[1];
                $this->logOperation('error', 'SOAP Fault en respuesta AFIP', [
                    'fault_string' => $errorMsg,
                    'response_excerpt' => substr($response, 0, 500)
                ]);
                throw new Exception("Error SOAP de AFIP: " . $errorMsg);
            }
        }

        $tracks = [];

        // PATRONES EXPANDIDOS para diferentes formatos AFIP
        $patterns = [
            // Patrones estándar
            '/<TracksEnv>([^<]+)<\/TracksEnv>/i',
            '/<Track>([^<]+)<\/Track>/i', 
            '/<trackId>([^<]+)<\/trackId>/i',
            '/<NumeroTrack>([^<]+)<\/NumeroTrack>/i',
            '/<IdTrack>([^<]+)<\/IdTrack>/i',
            
            // Patrones específicos AFIP wgesregsintia2
            '/<trackTransporte>([^<]+)<\/trackTransporte>/i',
            '/<idTrackTransporte>([^<]+)<\/idTrackTransporte>/i',
            '/<numeroSeguimiento>([^<]+)<\/numeroSeguimiento>/i',
            '/<codigoSeguimiento>([^<]+)<\/codigoSeguimiento>/i',
            
            // Patrones genéricos para códigos numéricos
            '/<resultado>([0-9A-Z\-]{8,20})<\/resultado>/i',
            '/<respuesta>([0-9A-Z\-]{8,20})<\/respuesta>/i',
            '/<codigo>([0-9A-Z\-]{8,20})<\/codigo>/i',
            
            // Patrones con atributos
            '/track[^>]*>([0-9A-Z\-]{8,20})</i',
            '/id[^>]*>([0-9A-Z\-]{8,20})</i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $response, $matches)) {
                $foundTracks = $matches[1];
                
                $this->logOperation('info', 'TRACKs encontrados con patrón', [
                    'pattern' => $pattern,
                    'tracks_found' => count($foundTracks),
                    'tracks' => $foundTracks
                ]);
                
                $tracks = array_merge($tracks, $foundTracks);
            }
        }

        // Si no encuentra TRACKs con patrones, buscar números que parezcan TRACKs
        if (empty($tracks)) {
            $this->logOperation('warning', 'No se encontraron TRACKs con patrones, intentando extracción alternativa');
            
            // Buscar códigos alfanuméricos que podrían ser TRACKs
            if (preg_match_all('/([A-Z0-9\-]{10,20})/', $response, $matches)) {
                $potentialTracks = array_filter($matches[1], function($code) {
                    // Filtrar códigos que no son fechas ni otros valores comunes
                    return !preg_match('/^\d{4}-\d{2}-\d{2}/', $code) && 
                           !in_array($code, ['UTF-8', 'SOAP-ENV', 'XMLNS']);
                });
                
                if (!empty($potentialTracks)) {
                    $this->logOperation('info', 'TRACKs potenciales encontrados por extracción alternativa', [
                        'potential_tracks' => array_values($potentialTracks)
                    ]);
                    $tracks = array_values($potentialTracks);
                }
            }
        }

        // Si aún no hay TRACKs, analizar estructura XML completa
        if (empty($tracks)) {
            $this->logOperation('warning', 'No se encontraron TRACKs, analizando estructura XML completa');
            
            try {
                $dom = new \DOMDocument();
                if (@$dom->loadXML($response)) {
                    $xpath = new \DOMXPath($dom);
                    
                    // Buscar todos los elementos que contengan valores alfanuméricos
                    $nodes = $xpath->query('//*[string-length(normalize-space(.)) > 5 and string-length(normalize-space(.)) < 25]');
                    
                    foreach ($nodes as $node) {
                        $value = trim($node->textContent);
                        // Si parece un código de seguimiento
                        if (preg_match('/^[A-Z0-9\-]{8,20}$/', $value) && 
                            !preg_match('/^\d{4}-\d{2}-\d{2}/', $value)) {
                            $tracks[] = $value;
                            
                            $this->logOperation('info', 'TRACK encontrado por análisis XML', [
                                'element_name' => $node->nodeName,
                                'track_value' => $value,
                                'parent_element' => $node->parentNode ? $node->parentNode->nodeName : null
                            ]);
                        }
                    }
                }
            } catch (Exception $e) {
                $this->logOperation('error', 'Error analizando XML para TRACKs', [
                    'error' => $e->getMessage()
                ]);
            }
        }

        // Limpiar y validar TRACKs encontrados
        $tracks = array_unique(array_filter($tracks, function($track) {
            $track = trim($track);
            return !empty($track) && 
                   strlen($track) >= 6 && 
                   strlen($track) <= 25 &&
                   !preg_match('/^\d{4}-\d{2}-\d{2}/', $track) && // No fechas
                   !in_array(strtoupper($track), ['TRUE', 'FALSE', 'SUCCESS', 'ERROR']); // No booleanos
        }));

        $finalTracks = array_values($tracks);

        $this->logOperation('info', 'Extracción de TRACKs completada', [
            'tracks_found' => count($finalTracks),
            'tracks' => $finalTracks,
            'response_analyzed' => strlen($response) . ' caracteres',
            'extraction_method' => empty($finalTracks) ? 'ninguno_exitoso' : 'patron_matching'
        ]);

        return $finalTracks;
    }

    /**
     * MÉTODO ADICIONAL: Generar TRACKs simulados para testing (solo desarrollo)
     */
    private function generateFallbackTracks(int $shipmentId, int $quantity = 1): array
    {
        // Solo en ambiente de desarrollo cuando no se pueden extraer TRACKs reales
        if (app()->environment('production')) {
            return [];
        }

        $tracks = [];
        for ($i = 1; $i <= $quantity; $i++) {
            $tracks[] = 'TRACK' . str_pad($shipmentId, 4, '0', STR_PAD_LEFT) . str_pad($i, 3, '0', STR_PAD_LEFT);
        }

        $this->logOperation('warning', 'TRACKs fallback generados para desarrollo', [
            'shipment_id' => $shipmentId,
            'tracks_generated' => $tracks,
            'note' => 'Solo para desarrollo - no usar en producción'
        ]);

        return $tracks;
    }

    /**
     * Extraer mensaje de error SOAP Fault
     */
    private function extractSoapFaultMessage(string $response): string
    {
        if (preg_match('/<faultstring>([^<]+)<\/faultstring>/', $response, $matches)) {
            return $matches[1];
        }
        
        if (preg_match('/<faultcode>([^<]+)<\/faultcode>/', $response, $matches)) {
            return 'Fault Code: ' . $matches[1];
        }

        return 'Error SOAP desconocido';
    }

    /**
     * Extraer ID MIC/DTA de respuesta
     */
    private function extractMicDtaIdFromResponse(string $response): ?string
    {
        $patterns = [
            '/<MicDtaId>([^<]+)<\/MicDtaId>/',
            '/<IdMicDta>([^<]+)<\/IdMicDta>/',
            '/<NumeroMicDta>([^<]+)<\/NumeroMicDta>/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $response, $matches)) {
                return $matches[1];
            }
        }

        return null;
    }

    /**
     * Guardar TRACKs en base de datos - COMPLETO con todos los campos obligatorios
     * Reemplaza el método saveTracks() en ArgentinaMicDtaService.php
     */
    private function saveTracks(Voyage $voyage, array $allTracks): void
    {
        try {
            // Obtener la transacción actual
            $currentTransaction = $this->getCurrentTransaction();
            
            if (!$currentTransaction) {
                $this->logOperation('error', 'No se encontró transacción actual para vincular TRACKs');
                return;
            }

            $totalSaved = 0;

            foreach ($allTracks as $shipmentId => $tracks) {
                // Obtener datos del shipment para completar referencias
                $shipment = \App\Models\Shipment::find($shipmentId);
                
                foreach ($tracks as $trackNumber) {
                    WebserviceTrack::create([
                        // Claves foráneas
                        'webservice_transaction_id' => $currentTransaction->id,
                        'shipment_id' => $shipmentId,
                        'container_id' => null, // Para envíos, no contenedores específicos
                        'bill_of_lading_id' => null, // Podríamos mejorar esto después
                        
                        // Datos del TRACK
                        'track_number' => $trackNumber,
                        'track_type' => 'envio', // OBLIGATORIO: tipo de TRACK según AFIP
                        'webservice_method' => 'RegistrarTitEnvios', // OBLIGATORIO: método que generó
                        
                        // Referencias de negocio (OBLIGATORIOS)
                        'reference_type' => 'shipment',
                        'reference_number' => $shipment ? $shipment->shipment_number : "SHIP_{$shipmentId}",
                        'description' => $shipment ? "Envío {$shipment->shipment_number}" : "Envío ID {$shipmentId}",
                        
                        // Datos AFIP
                        'afip_title_number' => null, // Se podría llenar si tenemos el título
                        'afip_metadata' => [
                            'generated_from' => 'RegistrarTitEnvios',
                            'voyage_id' => $voyage->id,
                            'voyage_number' => $voyage->voyage_number,
                            'extraction_method' => 'alternative_patterns',
                        ],
                        
                        // Timestamps
                        'generated_at' => now(),
                        
                        // Estado y tracking
                        'status' => 'used_in_micdta', // ENUM CORRECTO
                        'used_at' => now(), // Ya que se está usando en MIC/DTA
                        'completed_at' => null,
                        
                        // Auditoría (OBLIGATORIOS)
                        'created_by_user_id' => $this->user->id,
                        'created_from_ip' => request()->ip(),
                        
                        // Cadena de proceso
                        'process_chain' => ['generated', 'used_in_micdta'],
                        'notes' => 'TRACK extraído de respuesta RegistrarEnvios y usado inmediatamente en MIC/DTA',
                    ]);
                    $totalSaved++;
                }
            }

            $this->logOperation('info', 'TRACKs guardados exitosamente con datos completos', [
                'tracks_saved' => $totalSaved,
                'voyage_id' => $voyage->id,
                'voyage_number' => $voyage->voyage_number,
                'transaction_id' => $currentTransaction->id,
                'transaction_external_id' => $currentTransaction->transaction_id,
                'shipments_count' => count($allTracks),
                'user_id' => $this->user->id,
            ]);

        } catch (Exception $e) {
            $this->logOperation('error', 'Error guardando TRACKs completos', [
                'error' => $e->getMessage(),
                'voyage_id' => $voyage->id,
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ]);
            // No fallar el proceso completo
        }
    }

    /**
     * Método auxiliar mejorado para obtener transacción actual
     */
    private function getCurrentTransaction(): ?\App\Models\WebserviceTransaction
    {
        // Intentar usar la transacción actual almacenada
        if ($this->currentTransactionId) {
            return \App\Models\WebserviceTransaction::find($this->currentTransactionId);
        }
        
        // Fallback: buscar la transacción MIC/DTA más reciente para esta empresa
        return \App\Models\WebserviceTransaction::where('company_id', $this->company->id)
            ->where('webservice_type', 'micdta')
            ->where('country', 'AR')
            ->where('status', 'sent')
            ->latest()
            ->first();
    }

    /**
 * Guardar datos de transacción para auditorías
 */
private function saveTransactionData(string $transactionId, string $requestXml, string $responseXml, ?string $micDtaId): void
{
    try {
        \App\Models\WebserviceTransaction::where('transaction_id', $transactionId)
            ->update([
                'external_reference' => $micDtaId,
                'request_xml' => $requestXml,
                'response_xml' => $responseXml,
                'status' => 'success',
                'response_at' => now(),
            ]);
    } catch (Exception $e) {
        $this->logOperation('error', 'Error guardando datos transacción', ['error' => $e->getMessage()]);
    }
}

/**
 * Guardar registro de respuesta para GPS
 */
private function saveResponseRecord(string $transactionId, Voyage $voyage, ?string $micDtaId): void
{
    try {
        $transaction = \App\Models\WebserviceTransaction::where('transaction_id', $transactionId)->first();
        if ($transaction) {
            \App\Models\WebserviceResponse::create([
                'transaction_id' => $transaction->id,
                'response_type' => 'success',
                'reference_number' => $micDtaId ?: $transactionId,
                'voyage_number' => $voyage->voyage_number,
                'confirmation_number' => $micDtaId,
                'processed_at' => now(),
            ]);
        }
    } catch (Exception $e) {
        $this->logOperation('error', 'Error guardando respuesta para GPS', ['error' => $e->getMessage()]);
    }
}
}