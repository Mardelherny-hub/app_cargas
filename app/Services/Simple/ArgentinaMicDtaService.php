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

    // ========================================================================
    // REGISTRAR CONVOY - PASO 3 AFIP (CRÍTICO PARA BARCAZAS)
    // ========================================================================

    /**
     * Registrar convoy (RegistrarConvoy) - PASO 3 AFIP
     * Agrupa múltiples MIC/DTA bajo un convoy único usando external_reference
     * 
     * @param array $shipmentIds Array de IDs de shipments con MIC/DTA registrados
     * @param string|null $convoyName Nombre opcional del convoy
     * @return array Resultado de la operación
     */
    public function registrarConvoy(array $shipmentIds, string $convoyName = null): array
    {
        $result = [
            'success' => false,
            'convoy_id' => null,
            'transaction_id' => null,
            'nro_viaje' => null,
            'shipments_included' => [],
            'errors' => [],
        ];

        DB::beginTransaction();

        try {
            $this->logOperation('info', 'Iniciando RegistrarConvoy - Paso 3 AFIP', [
                'shipment_ids' => $shipmentIds,
                'convoy_name' => $convoyName,
                'shipments_count' => count($shipmentIds),
            ]);

            // 1. ✅ VALIDAR: Obtener external_reference de MIC/DTA exitosos
            $validation = $this->validateShipmentsForConvoy($shipmentIds);
            if (!$validation['is_valid']) {
                $result['errors'] = $validation['errors'];
                return $result;
            }

            $shipments = $validation['shipments'];
            $convoyData = $validation['convoy_data']; // Contiene remolcador_micdta_id y barcazas_micdta_ids

            // 2. Generar nombre de convoy si no se proporciona
            $convoyId = $convoyName ?? $this->generateConvoyReference($shipments);

            // 3. Crear transacción para convoy
            $transaction = $this->createConvoyTransaction($shipments, $convoyId);
            $result['transaction_id'] = $transaction->id;
            $this->currentTransactionId = $transaction->id;

            // 4. ✅ GENERAR XML usando createRegistrarConvoyXml
            $xmlContent = $this->xmlSerializer->createRegistrarConvoyXml($convoyData, $transaction->transaction_id);
            if (!$xmlContent) {
                throw new Exception('Error generando XML RegistrarConvoy');
            }

            // 5. Enviar a AFIP
            $soapClient = $this->createSoapClient();
            $soapResponse = $this->sendConvoySoapRequest($transaction, $soapClient, $xmlContent);

            // 6. Procesar respuesta
            if ($soapResponse['success']) {
                $result = $this->processConvoyResponse($transaction, $soapResponse, $shipments, $convoyId);
                
                if ($result['success']) {
                    $result['convoy_id'] = $convoyId;
                    $result['shipments_included'] = collect($shipments)->pluck('id')->toArray();
                    
                    // ✅ Extraer nroViaje de la respuesta AFIP
                    $result['nro_viaje'] = $this->extractNroViajeFromResponse($soapResponse);
                }
            } else {
                $result['errors'] = $soapResponse['errors'] ?? ['Error en comunicación con AFIP'];
            }

            DB::commit();
            return $result;

        } catch (Exception $e) {
            DB::rollBack();
            
            $this->logOperation('error', 'Error en RegistrarConvoy', [
                'error' => $e->getMessage(),
                'shipment_ids' => $shipmentIds,
                'convoy_name' => $convoyName,
            ]);

            $result['errors'][] = $e->getMessage();
            return $result;
        }
    }

    /**
     * CORREGIDO: Validar shipments para formar convoy - usando external_reference
     * 
     * @param array $shipmentIds Array de IDs de shipments
     * @return array Resultado de validación con external_reference
     */
    private function validateShipmentsForConvoy(array $shipmentIds): array
    {
        $validation = [
            'is_valid' => false,
            'shipments' => [],
            'convoy_data' => [],
            'errors' => [],
        ];

        try {
            // 1. Obtener shipments válidos
            $shipments = \App\Models\Shipment::whereIn('id', $shipmentIds)
                ->where('active', true)
                ->get();

            if ($shipments->count() !== count($shipmentIds)) {
                $validation['errors'][] = 'Algunos shipments no existen o están inactivos';
                return $validation;
            }

            // 2. Verificar que todos pertenezcan a la misma empresa
            $companyIds = $shipments->pluck('voyage.company_id')->unique()->filter();
            if ($companyIds->count() > 1 || !$companyIds->contains($this->company->id)) {
                $validation['errors'][] = 'Todos los shipments deben pertenecer a la misma empresa';
                return $validation;
            }

            // 3. ✅ CRÍTICO: Obtener external_reference de MIC/DTA exitosos
            $micDtaReferences = [];
            $remolcadorReference = null;
            
            foreach ($shipments as $shipment) {
                // Buscar MIC/DTA exitoso para este shipment
                $micDtaTransaction = \App\Models\WebserviceTransaction::where('shipment_id', $shipment->id)
                    ->where('webservice_type', 'micdta')
                    ->where('status', 'sent')
                    ->whereNotNull('external_reference')
                    ->where('company_id', $this->company->id)
                    ->latest('completed_at')
                    ->first();

                if (!$micDtaTransaction) {
                    $validation['errors'][] = "Shipment {$shipment->shipment_number} no tiene MIC/DTA registrado exitosamente";
                    continue;
                }

                // ✅ Determinar remolcador vs barcaza según tipo de embarcación
                $voyage = $shipment->voyage()->with('leadVessel')->first();
                $vesselType = $voyage?->leadVessel?->vessel_type ?? 'unknown';
                
                if (in_array($vesselType, ['tugboat', 'remolcador', 'empujador'])) {
                    if ($remolcadorReference) {
                        $validation['errors'][] = 'Solo puede haber un remolcador por convoy';
                        continue;
                    }
                    $remolcadorReference = $micDtaTransaction->external_reference;
                } else {
                    // Es una barcaza
                    $micDtaReferences[] = $micDtaTransaction->external_reference;
                }
            }

            // 4. Validar estructura del convoy
            if (!$remolcadorReference) {
                $validation['errors'][] = 'Convoy requiere al menos un remolcador';
                return $validation;
            }

            if (empty($micDtaReferences)) {
                $validation['errors'][] = 'Convoy requiere al menos una barcaza';
                return $validation;
            }

            // 5. ✅ Preparar datos para XML RegistrarConvoy
            $validation['is_valid'] = true;
            $validation['shipments'] = $shipments;
            $validation['convoy_data'] = [
                'remolcador_micdta_id' => $remolcadorReference,
                'barcazas_micdta_ids' => $micDtaReferences,
            ];

            $this->logOperation('info', 'Validación convoy exitosa', [
                'remolcador_ref' => $remolcadorReference,
                'barcazas_refs' => $micDtaReferences,
                'shipments_count' => $shipments->count(),
            ]);

            return $validation;

        } catch (Exception $e) {
            $validation['errors'][] = 'Error validando shipments para convoy: ' . $e->getMessage();
            
            $this->logOperation('error', 'Error en validación convoy', [
                'error' => $e->getMessage(),
                'shipment_ids' => $shipmentIds,
            ]);
            
            return $validation;
        }
    }

    /**
     * Generar referencia única para convoy
     */
    private function generateConvoyReference($shipments): string
    {
        $year = now()->format('Y');
        $month = now()->format('m');
        $sequence = str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
        
        return "CONVOY_{$year}{$month}_{$sequence}_{$this->company->id}";
    }

    /**
     * Crear transacción para convoy
     */
    private function createConvoyTransaction($shipments, string $convoyId): \App\Models\WebserviceTransaction
    {
        $transactionId = 'CONVOY_' . time() . '_' . $this->company->id;

        return \App\Models\WebserviceTransaction::create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'voyage_id' => $shipments->first()->voyage_id ?? null,
            'transaction_id' => $transactionId,
            'webservice_type' => 'convoy',
            'country' => 'AR',
            'webservice_url' => $this->getWsdlUrl(),
            'soap_action' => 'Ar.Gob.Afip.Dga.wgesregsintia2/RegistrarConvoy',
            'status' => 'pending',
            'environment' => $this->config['environment'],
            'timeout_seconds' => 60,
            'max_retries' => 3,
            'retry_intervals' => json_encode([30, 120, 300]),
            'requires_certificate' => true,
            'additional_metadata' => [
                'method' => 'RegistrarConvoy',
                'step' => 3,
                'purpose' => 'Agrupar múltiples MIC/DTA en convoy',
                'convoy_id' => $convoyId,
                'shipments_count' => $shipments->count(),
                'shipment_ids' => $shipments->pluck('id')->toArray(),
            ],
        ]);
    }

    /**
     * Enviar SOAP Request para RegistrarConvoy
     */
    private function sendConvoySoapRequest(\App\Models\WebserviceTransaction $transaction, $soapClient, string $xmlContent): array
    {
        $result = [
            'success' => false,
            'response_data' => null,
            'errors' => [],
        ];

        try {
            $this->logOperation('info', 'Enviando SOAP RegistrarConvoy', [
                'transaction_id' => $transaction->id,
                'xml_size_kb' => round(strlen($xmlContent) / 1024, 2),
            ]);

            // Actualizar transacción
            $transaction->update([
                'status' => 'sending',
                'request_xml' => $xmlContent,
                'sent_at' => now(),
            ]);

            // Llamada SOAP directa
            $response = $soapClient->__doRequest(
                $xmlContent,
                $this->getWsdlUrl(),
                'Ar.Gob.Afip.Dga.wgesregsintia2/RegistrarConvoy',
                SOAP_1_1,
                false
            );

            if ($response) {
                $result['success'] = true;
                $result['response_data'] = $response;
                
                $this->logOperation('info', 'Respuesta SOAP RegistrarConvoy recibida', [
                    'transaction_id' => $transaction->id,
                    'response_length' => strlen($response),
                ]);

                // Actualizar transacción con respuesta
                $transaction->update([
                    'response_xml' => $response,
                    'response_at' => now(),
                    'status' => 'sent',
                ]);
            } else {
                $result['errors'][] = 'Respuesta SOAP vacía';
                
                $transaction->update([
                    'status' => 'error',
                    'error_message' => 'Respuesta SOAP vacía',
                    'completed_at' => now(),
                ]);
            }

            return $result;

        } catch (Exception $e) {
            $result['errors'][] = 'Error SOAP RegistrarConvoy: ' . $e->getMessage();
            
            $this->logOperation('error', 'Error en SOAP RegistrarConvoy', [
                'error' => $e->getMessage(),
                'transaction_id' => $transaction->id,
            ]);

            $transaction->update([
                'status' => 'error',
                'error_message' => $e->getMessage(),
                'completed_at' => now(),
            ]);
            
            return $result;
        }
    }

    /**
     * Procesar respuesta de convoy
     */
    private function processConvoyResponse(\App\Models\WebserviceTransaction $transaction, array $soapResponse, $shipments, string $convoyId): array
    {
        $result = [
            'success' => false,
            'convoy_reference' => null,
            'nro_viaje' => null,
        ];

        try {
            // Extraer nroViaje de la respuesta
            $nroViaje = $this->extractNroViajeFromResponse($soapResponse);
            
            // Actualizar transacción con éxito
            $transaction->update([
                'status' => 'success',
                'external_reference' => $nroViaje ?? $convoyId,
                'confirmation_number' => $nroViaje,
                'completed_at' => now(),
                'success_data' => [
                    'convoy_id' => $convoyId,
                    'nro_viaje' => $nroViaje,
                    'shipments_count' => $shipments->count(),
                ],
            ]);
            
            $result['success'] = true;
            $result['convoy_reference'] = $nroViaje ?? $convoyId;
            $result['nro_viaje'] = $nroViaje;
            
            $this->logOperation('info', 'Convoy registrado exitosamente', [
                'transaction_id' => $transaction->id,
                'convoy_id' => $convoyId,
                'nro_viaje' => $nroViaje,
                'shipments_count' => $shipments->count(),
            ]);
            
            return $result;
            
        } catch (Exception $e) {
            $this->logOperation('error', 'Error procesando respuesta RegistrarConvoy', [
                'error' => $e->getMessage(),
                'transaction_id' => $transaction->id,
            ]);
            
            $transaction->update([
                'status' => 'error',
                'error_message' => $e->getMessage(),
                'completed_at' => now(),
            ]);
            
            throw $e;
        }
    }

    /**
     * ✅ NUEVO: Extraer nroViaje de la respuesta AFIP
     */
    private function extractNroViajeFromResponse(array $soapResponse): ?string
    {
        try {
            // La respuesta AFIP RegistrarConvoy contiene <nroViaje>
            if (isset($soapResponse['response_data'])) {
                $responseData = $soapResponse['response_data'];
                
                // Si es string XML, parsear
                if (is_string($responseData)) {
                    if (preg_match('/<nroViaje>([^<]+)<\/nroViaje>/', $responseData, $matches)) {
                        return (string)$matches[1];
                    }
                }
                
                // Si es array/object
                if (isset($responseData->nroViaje)) {
                    return (string)$responseData->nroViaje;
                }
                
                if (is_array($responseData) && isset($responseData['nroViaje'])) {
                    return (string)$responseData['nroViaje'];
                }
            }

            return null;

        } catch (Exception $e) {
            $this->logOperation('error', 'Error extrayendo nroViaje', [
                'error' => $e->getMessage(),
                'response_structure' => json_encode($soapResponse, JSON_PARTIAL_OUTPUT_ON_ERROR),
            ]);
            
            return null;
        }
    }

    // ========================================================================
    // ASIGNAR ATA REMOLCADOR - MÉTODO COMPLEMENTARIO AFIP
    // ========================================================================

    /**
     * AsignarATARemol - Asignar CUIT del ATA Remolcador a MIC/DTA
     * 
     * @param string $micDtaId ID del MIC/DTA (external_reference)
     * @param string $cuitAtaRemolcador CUIT del ATA Remolcador (11 dígitos)
     * @return array Resultado de la operación
     */
    public function asignarATARemolcador(string $micDtaId, string $cuitAtaRemolcador): array
    {
        $result = [
            'success' => false,
            'transaction_id' => null,
            'micdta_id' => $micDtaId,
            'cuit_ata_remolcador' => $cuitAtaRemolcador,
            'errors' => [],
        ];

        DB::beginTransaction();

        try {
            $this->logOperation('info', 'Iniciando AsignarATARemol', [
                'micdta_id' => $micDtaId,
                'cuit_ata_remolcador' => $cuitAtaRemolcador,
            ]);

            // 1. Validar parámetros
            $validation = $this->validateAsignarATARemolParams($micDtaId, $cuitAtaRemolcador);
            if (!$validation['is_valid']) {
                $result['errors'] = $validation['errors'];
                return $result;
            }

            // 2. Verificar que el MIC/DTA existe y pertenece a la empresa
            $micDtaTransaction = $this->findMicDtaTransaction($micDtaId);
            if (!$micDtaTransaction) {
                $result['errors'][] = 'MIC/DTA no encontrado o no pertenece a esta empresa';
                return $result;
            }

            // 3. Crear transacción para AsignarATARemol
            $transaction = $this->createAsignarATARemolTransaction($micDtaTransaction, $cuitAtaRemolcador);
            $result['transaction_id'] = $transaction->id;
            $this->currentTransactionId = $transaction->id;

            // 4. Generar XML para AsignarATARemol
            $asignacionData = [
                'id_micdta' => $micDtaId,
                'cuit_ata_remolcador' => $cuitAtaRemolcador,
            ];

            $xmlContent = $this->xmlSerializer->createAsignarATARemolXml($asignacionData, $transaction->transaction_id);
            if (!$xmlContent) {
                throw new Exception('Error generando XML AsignarATARemol');
            }

            // 5. Enviar a AFIP
            $soapClient = $this->createSoapClient();
            $soapResponse = $this->sendAsignarATARemolSoapRequest($transaction, $soapClient, $xmlContent);

            // 6. Procesar respuesta
            if ($soapResponse['success']) {
                $result = $this->processAsignarATARemolResponse($transaction, $soapResponse, $micDtaId, $cuitAtaRemolcador);
            } else {
                $result['errors'] = $soapResponse['errors'] ?? ['Error en comunicación con AFIP'];
            }

            DB::commit();
            return $result;

        } catch (Exception $e) {
            DB::rollBack();
            
            $this->logOperation('error', 'Error en AsignarATARemol', [
                'error' => $e->getMessage(),
                'micdta_id' => $micDtaId,
                'cuit_ata_remolcador' => $cuitAtaRemolcador,
            ]);

            $result['errors'][] = $e->getMessage();
            return $result;
        }
    }

    /**
     * Validar parámetros para AsignarATARemol
     */
    private function validateAsignarATARemolParams(string $micDtaId, string $cuitAtaRemolcador): array
    {
        $validation = [
            'is_valid' => false,
            'errors' => [],
        ];

        // Validar MIC/DTA ID
        if (empty($micDtaId)) {
            $validation['errors'][] = 'ID MIC/DTA es obligatorio';
        } elseif (strlen($micDtaId) > 16) {
            $validation['errors'][] = 'ID MIC/DTA no puede exceder 16 caracteres';
        }

        // Validar CUIT
        $cuitLimpio = preg_replace('/[^0-9]/', '', $cuitAtaRemolcador);
        if (empty($cuitLimpio)) {
            $validation['errors'][] = 'CUIT ATA Remolcador es obligatorio';
        } elseif (strlen($cuitLimpio) !== 11) {
            $validation['errors'][] = 'CUIT ATA Remolcador debe tener exactamente 11 dígitos';
        } elseif (!$this->validarCuitChecksum($cuitLimpio)) {
            $validation['errors'][] = 'CUIT ATA Remolcador tiene formato inválido';
        }

        $validation['is_valid'] = empty($validation['errors']);

        if (!$validation['is_valid']) {
            $this->logOperation('warning', 'Validación AsignarATARemol falló', [
                'errors' => $validation['errors'],
                'micdta_id_length' => strlen($micDtaId),
                'cuit_length' => strlen($cuitLimpio),
            ]);
        }

        return $validation;
    }

    /**
     * Buscar transacción MIC/DTA existente
     */
    private function findMicDtaTransaction(string $micDtaId): ?\App\Models\WebserviceTransaction
    {
        return \App\Models\WebserviceTransaction::where('external_reference', $micDtaId)
            ->where('company_id', $this->company->id)
            ->where('webservice_type', 'micdta')
            ->where('status', 'success')
            ->latest('completed_at')
            ->first();
    }

    /**
     * Crear transacción para AsignarATARemol
     */
    private function createAsignarATARemolTransaction(\App\Models\WebserviceTransaction $micDtaTransaction, string $cuitAtaRemolcador): \App\Models\WebserviceTransaction
    {
        $transactionId = 'ATA_REMOL_' . time() . '_' . $this->company->id;

        return \App\Models\WebserviceTransaction::create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'voyage_id' => $micDtaTransaction->voyage_id,
            'shipment_id' => $micDtaTransaction->shipment_id,
            'transaction_id' => $transactionId,
            'webservice_type' => 'ata_remolcador',
            'country' => 'AR',
            'webservice_url' => $this->getWsdlUrl(),
            'soap_action' => 'Ar.Gob.Afip.Dga.wgesregsintia2/AsignarATARemol',
            'status' => 'pending',
            'environment' => $this->config['environment'],
            'timeout_seconds' => 60,
            'max_retries' => 3,
            'additional_metadata' => [
                'method' => 'AsignarATARemol',
                'original_micdta_transaction_id' => $micDtaTransaction->id,
                'original_micdta_reference' => $micDtaTransaction->external_reference,
                'cuit_ata_remolcador' => $cuitAtaRemolcador,
                'purpose' => 'Asignar CUIT ATA Remolcador a MIC/DTA',
            ],
        ]);
    }

    /**
     * Enviar SOAP Request para AsignarATARemol
     */
    private function sendAsignarATARemolSoapRequest(\App\Models\WebserviceTransaction $transaction, $soapClient, string $xmlContent): array
    {
        $result = [
            'success' => false,
            'response_data' => null,
            'errors' => [],
        ];

        try {
            $this->logOperation('info', 'Enviando SOAP AsignarATARemol', [
                'transaction_id' => $transaction->id,
                'xml_size_kb' => round(strlen($xmlContent) / 1024, 2),
            ]);

            // Actualizar transacción
            $transaction->update([
                'status' => 'sending',
                'request_xml' => $xmlContent,
                'sent_at' => now(),
            ]);

            // Llamada SOAP directa
            $response = $soapClient->__doRequest(
                $xmlContent,
                $this->getWsdlUrl(),
                'Ar.Gob.Afip.Dga.wgesregsintia2/AsignarATARemol',
                SOAP_1_1,
                false
            );

            if ($response) {
                $result['success'] = true;
                $result['response_data'] = $response;
                
                $this->logOperation('info', 'Respuesta SOAP AsignarATARemol recibida', [
                    'transaction_id' => $transaction->id,
                    'response_length' => strlen($response),
                ]);

                // Actualizar transacción con respuesta
                $transaction->update([
                    'response_xml' => $response,
                    'response_at' => now(),
                    'status' => 'sent',
                ]);
            } else {
                $result['errors'][] = 'Respuesta SOAP vacía';
                
                $transaction->update([
                    'status' => 'error',
                    'error_message' => 'Respuesta SOAP vacía',
                    'completed_at' => now(),
                ]);
            }

            return $result;

        } catch (Exception $e) {
            $result['errors'][] = 'Error SOAP AsignarATARemol: ' . $e->getMessage();
            
            $this->logOperation('error', 'Error en SOAP AsignarATARemol', [
                'error' => $e->getMessage(),
                'transaction_id' => $transaction->id,
            ]);

            $transaction->update([
                'status' => 'error',
                'error_message' => $e->getMessage(),
                'completed_at' => now(),
            ]);
            
            return $result;
        }
    }

    /**
     * Procesar respuesta de AsignarATARemol
     */
    private function processAsignarATARemolResponse(\App\Models\WebserviceTransaction $transaction, array $soapResponse, string $micDtaId, string $cuitAtaRemolcador): array
    {
        $result = [
            'success' => false,
            'micdta_id' => $micDtaId,
            'cuit_ata_remolcador' => $cuitAtaRemolcador,
        ];

        try {
            // Verificar si hay errores SOAP
            if (isset($soapResponse['response_data']) && is_string($soapResponse['response_data'])) {
                $responseXml = $soapResponse['response_data'];
                
                if (strpos($responseXml, 'soap:Fault') !== false) {
                    $errorMsg = $this->extractSoapFaultMessage($responseXml);
                    throw new Exception("Error AFIP: " . $errorMsg);
                }
            }

            // Actualizar transacción con éxito
            $transaction->update([
                'status' => 'success',
                'external_reference' => $micDtaId . '_ATA_' . $cuitAtaRemolcador,
                'confirmation_number' => $micDtaId,
                'completed_at' => now(),
                'success_data' => [
                    'micdta_id' => $micDtaId,
                    'cuit_ata_remolcador' => $cuitAtaRemolcador,
                    'assignment_completed' => true,
                ],
            ]);
            
            $result['success'] = true;
            
            $this->logOperation('info', 'ATA Remolcador asignado exitosamente', [
                'transaction_id' => $transaction->id,
                'micdta_id' => $micDtaId,
                'cuit_ata_remolcador' => $cuitAtaRemolcador,
            ]);
            
            return $result;
            
        } catch (Exception $e) {
            $this->logOperation('error', 'Error procesando respuesta AsignarATARemol', [
                'error' => $e->getMessage(),
                'transaction_id' => $transaction->id,
            ]);
            
            $transaction->update([
                'status' => 'error',
                'error_message' => $e->getMessage(),
                'completed_at' => now(),
            ]);
            
            throw $e;
        }
    }

    /**
     * Validar checksum de CUIT argentino
     */
    private function validarCuitChecksum(string $cuit): bool
    {
        if (strlen($cuit) !== 11 || !is_numeric($cuit)) {
            return false;
        }

        $multiplicadores = [5, 4, 3, 2, 7, 6, 5, 4, 3, 2];
        $suma = 0;

        for ($i = 0; $i < 10; $i++) {
            $suma += intval($cuit[$i]) * $multiplicadores[$i];
        }

        $resto = $suma % 11;
        $digitoVerificador = $resto < 2 ? $resto : 11 - $resto;

        return $digitoVerificador == intval($cuit[10]);
    }

    // ========================================================================
    // REGISTRAR SALIDA ZONA PRIMARIA - PASO 4 AFIP (FINAL)
    // ========================================================================

    /**
     * RegistrarSalidaZonaPrimaria - Registrar salida de zona primaria de convoy
     * 
     * @param string $nroViaje Número de viaje obtenido de RegistrarConvoy
     * @return array Resultado de la operación
     */
    public function registrarSalidaZonaPrimaria(string $nroViaje): array
    {
        $result = [
            'success' => false,
            'transaction_id' => null,
            'nro_viaje' => $nroViaje,
            'nro_salida' => null,
            'errors' => [],
        ];

        DB::beginTransaction();

        try {
            $this->logOperation('info', 'Iniciando RegistrarSalidaZonaPrimaria - Paso 4 AFIP (Final)', [
                'nro_viaje' => $nroViaje,
            ]);

            // 1. Validar número de viaje
            if (empty($nroViaje)) {
                $result['errors'][] = 'Número de viaje es obligatorio';
                return $result;
            }

            // 2. Verificar que el convoy existe y fue registrado exitosamente
            $convoyTransaction = $this->findConvoyTransaction($nroViaje);
            if (!$convoyTransaction) {
                $result['errors'][] = 'No se encontró convoy registrado con ese número de viaje';
                return $result;
            }

            // 3. Crear transacción para RegistrarSalidaZonaPrimaria
            $transaction = $this->createSalidaZonaPrimariaTransaction($convoyTransaction, $nroViaje);
            $result['transaction_id'] = $transaction->id;
            $this->currentTransactionId = $transaction->id;

            // 4. Generar XML para RegistrarSalidaZonaPrimaria
            $salidaData = [
                'nro_viaje' => $nroViaje,
            ];

            $xmlContent = $this->xmlSerializer->createRegistrarSalidaZonaPrimariaXml($salidaData, $transaction->transaction_id);
            if (!$xmlContent) {
                throw new Exception('Error generando XML RegistrarSalidaZonaPrimaria');
            }

            // 5. Enviar a AFIP
            $soapClient = $this->createSoapClient();
            $soapResponse = $this->sendSalidaZonaPrimariaSoapRequest($transaction, $soapClient, $xmlContent);

            // 6. Procesar respuesta
            if ($soapResponse['success']) {
                $result = $this->processSalidaZonaPrimariaResponse($transaction, $soapResponse, $nroViaje);
            } else {
                $result['errors'] = $soapResponse['errors'] ?? ['Error en comunicación con AFIP'];
            }

            DB::commit();
            return $result;

        } catch (Exception $e) {
            DB::rollBack();
            
            $this->logOperation('error', 'Error en RegistrarSalidaZonaPrimaria', [
                'error' => $e->getMessage(),
                'nro_viaje' => $nroViaje,
            ]);

            $result['errors'][] = $e->getMessage();
            return $result;
        }
    }

    /**
     * Buscar transacción de convoy por número de viaje
     */
    private function findConvoyTransaction(string $nroViaje): ?\App\Models\WebserviceTransaction
    {
        return \App\Models\WebserviceTransaction::where('confirmation_number', $nroViaje)
            ->orWhereJsonContains('success_data->nro_viaje', $nroViaje)
            ->where('company_id', $this->company->id)
            ->where('webservice_type', 'convoy')
            ->where('status', 'success')
            ->latest('completed_at')
            ->first();
    }

    /**
     * Crear transacción para RegistrarSalidaZonaPrimaria
     */
    private function createSalidaZonaPrimariaTransaction(\App\Models\WebserviceTransaction $convoyTransaction, string $nroViaje): \App\Models\WebserviceTransaction
    {
        $transactionId = 'SALIDA_' . time() . '_' . $this->company->id;

        return \App\Models\WebserviceTransaction::create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'voyage_id' => $convoyTransaction->voyage_id,
            'transaction_id' => $transactionId,
            'webservice_type' => 'salida_zona_primaria',
            'country' => 'AR',
            'webservice_url' => $this->getWsdlUrl(),
            'soap_action' => 'Ar.Gob.Afip.Dga.wgesregsintia2/RegistrarSalidaZonaPrimaria',
            'status' => 'pending',
            'environment' => $this->config['environment'],
            'timeout_seconds' => 60,
            'max_retries' => 3,
            'additional_metadata' => [
                'method' => 'RegistrarSalidaZonaPrimaria',
                'step' => 4,
                'purpose' => 'Registrar salida de zona primaria',
                'nro_viaje' => $nroViaje,
                'convoy_transaction_id' => $convoyTransaction->id,
                'convoy_reference' => $convoyTransaction->external_reference,
            ],
        ]);
    }

    /**
     * Enviar SOAP Request para RegistrarSalidaZonaPrimaria
     */
    private function sendSalidaZonaPrimariaSoapRequest(\App\Models\WebserviceTransaction $transaction, $soapClient, string $xmlContent): array
    {
        $result = [
            'success' => false,
            'response_data' => null,
            'errors' => [],
        ];

        try {
            $this->logOperation('info', 'Enviando SOAP RegistrarSalidaZonaPrimaria', [
                'transaction_id' => $transaction->id,
                'xml_size_kb' => round(strlen($xmlContent) / 1024, 2),
            ]);

            // Actualizar transacción
            $transaction->update([
                'status' => 'sending',
                'request_xml' => $xmlContent,
                'sent_at' => now(),
            ]);

            // Llamada SOAP directa
            $response = $soapClient->__doRequest(
                $xmlContent,
                $this->getWsdlUrl(),
                'Ar.Gob.Afip.Dga.wgesregsintia2/RegistrarSalidaZonaPrimaria',
                SOAP_1_1,
                false
            );

            if ($response) {
                $result['success'] = true;
                $result['response_data'] = $response;
                
                $this->logOperation('info', 'Respuesta SOAP RegistrarSalidaZonaPrimaria recibida', [
                    'transaction_id' => $transaction->id,
                    'response_length' => strlen($response),
                ]);

                // Actualizar transacción con respuesta
                $transaction->update([
                    'response_xml' => $response,
                    'response_at' => now(),
                    'status' => 'sent',
                ]);
            } else {
                $result['errors'][] = 'Respuesta SOAP vacía';
                
                $transaction->update([
                    'status' => 'error',
                    'error_message' => 'Respuesta SOAP vacía',
                    'completed_at' => now(),
                ]);
            }

            return $result;

        } catch (Exception $e) {
            $result['errors'][] = 'Error SOAP RegistrarSalidaZonaPrimaria: ' . $e->getMessage();
            
            $this->logOperation('error', 'Error en SOAP RegistrarSalidaZonaPrimaria', [
                'error' => $e->getMessage(),
                'transaction_id' => $transaction->id,
            ]);

            $transaction->update([
                'status' => 'error',
                'error_message' => $e->getMessage(),
                'completed_at' => now(),
            ]);
            
            return $result;
        }
    }

    /**
     * Procesar respuesta de RegistrarSalidaZonaPrimaria
     */
    private function processSalidaZonaPrimariaResponse(\App\Models\WebserviceTransaction $transaction, array $soapResponse, string $nroViaje): array
    {
        $result = [
            'success' => false,
            'nro_viaje' => $nroViaje,
            'nro_salida' => null,
        ];

        try {
            // Extraer número de salida de la respuesta
            $nroSalida = $this->extractNroSalidaFromResponse($soapResponse);
            
            // Actualizar transacción con éxito
            $transaction->update([
                'status' => 'success',
                'external_reference' => $nroSalida ?? $nroViaje . '_SALIDA',
                'confirmation_number' => $nroSalida,
                'completed_at' => now(),
                'success_data' => [
                    'nro_viaje' => $nroViaje,
                    'nro_salida' => $nroSalida,
                    'salida_registered' => true,
                    'final_step_completed' => true,
                ],
            ]);
            
            $result['success'] = true;
            $result['nro_salida'] = $nroSalida;
            
            $this->logOperation('info', 'Salida de zona primaria registrada exitosamente - PROCESO COMPLETO', [
                'transaction_id' => $transaction->id,
                'nro_viaje' => $nroViaje,
                'nro_salida' => $nroSalida,
                'final_step' => true,
            ]);
            
            return $result;
            
        } catch (Exception $e) {
            $this->logOperation('error', 'Error procesando respuesta RegistrarSalidaZonaPrimaria', [
                'error' => $e->getMessage(),
                'transaction_id' => $transaction->id,
            ]);
            
            $transaction->update([
                'status' => 'error',
                'error_message' => $e->getMessage(),
                'completed_at' => now(),
            ]);
            
            throw $e;
        }
    }

    /**
     * Extraer número de salida de la respuesta AFIP
     */
    private function extractNroSalidaFromResponse(array $soapResponse): ?string
    {
        try {
            // La respuesta AFIP RegistrarSalidaZonaPrimaria contiene el número de salida
            if (isset($soapResponse['response_data'])) {
                $responseData = $soapResponse['response_data'];
                
                // Si es string XML, parsear
                if (is_string($responseData)) {
                    // Patrones posibles para número de salida
                    $patterns = [
                        '/<nroSalida>([^<]+)<\/nroSalida>/',
                        '/<numeroSalida>([^<]+)<\/numeroSalida>/',
                        '/<resultado>([^<]+)<\/resultado>/',
                        '/<confirmacion>([^<]+)<\/confirmacion>/',
                    ];
                    
                    foreach ($patterns as $pattern) {
                        if (preg_match($pattern, $responseData, $matches)) {
                            return (string)$matches[1];
                        }
                    }
                }
                
                // Si es array/object
                if (isset($responseData->nroSalida)) {
                    return (string)$responseData->nroSalida;
                }
                
                if (is_array($responseData) && isset($responseData['nroSalida'])) {
                    return (string)$responseData['nroSalida'];
                }
            }

            return null;

        } catch (Exception $e) {
            $this->logOperation('error', 'Error extrayendo nroSalida', [
                'error' => $e->getMessage(),
                'response_structure' => json_encode($soapResponse, JSON_PARTIAL_OUTPUT_ON_ERROR),
            ]);
            
            return null;
        }
    }
}