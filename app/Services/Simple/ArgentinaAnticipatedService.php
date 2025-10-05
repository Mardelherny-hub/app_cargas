<?php

namespace App\Services\Simple;

use App\Models\Voyage;
use App\Models\Company;
use App\Models\User;
use App\Services\Webservice\SoapClientService;
use App\Services\Simple\SimpleXmlGenerator;
use App\Services\Simple\BaseWebserviceService;
use Exception;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * SISTEMA MODULAR WEBSERVICES - ArgentinaAnticipatedService
 * 
 * Servicio para Información Anticipada Argentina AFIP
 * Extiende BaseWebserviceService para el webservice AFIP de Información Anticipada Marítima.
 * 
 * ESPECIFICACIONES AFIP:
 * - WSDL: https://wsaduhomoext.afip.gob.ar/DIAV2/wgesinformacionanticipada/wgesinformacionanticipada.asmx?wsdl
 * - Namespace: Ar.Gob.Afip.Dga.Org.wgesinformacionanticipada
 * - Métodos: RegistrarViaje, RectificarViaje, RegistrarTitulosCbc
 * 
 * FUNCIONALIDADES:
 * - Registro de viaje ATA MT (más simple que MIC/DTA)
 * - Rectificación de viaje
 * - Registro de títulos ATA CBC
 * - NO requiere TRACKs (diferencia con MIC/DTA)
 * - Datos de cabecera + embarcación + contenedores vacíos
 * 
 * REUTILIZA INFRAESTRUCTURA:
 * - BaseWebserviceService (validaciones, transacciones, logging)
 * - CertificateManagerService (certificados .p12 existentes)
 * - SimpleXmlGenerator (generación XML AFIP)
 * - Modelos existentes: Voyage, Shipment, Company
 */
class ArgentinaAnticipatedService
{
    private Company $company;
    private User $user;
    private SoapClientService $soapClient;
    private array $config;
    private ?int $currentTransactionId = null;

    public function __construct(Company $company, User $user, array $config = [])
    {
        $this->company = $company;
        $this->user = $user;
        $this->soapClient = new SoapClientService($company);
        $this->config = array_merge([
            'webservice_type' => 'anticipada',
            'country' => 'AR',
            'environment' => 'testing',
            'soap_action_registrar_viaje' => 'Ar.Gob.Afip.Dga.Org.wgesinformacionanticipada/RegistrarViaje',
            'soap_action_rectificar_viaje' => 'Ar.Gob.Afip.Dga.Org.wgesinformacionanticipada/RectificarViaje',
            'soap_action_registrar_titulos_cbc' => 'Ar.Gob.Afip.Dga.Org.wgesinformacionanticipada/RegistrarTitulosCbc',
            'timeout_seconds' => 60,
            'max_retries' => 3,
            'require_certificate' => true,
        ], $config);
    }

    /**
     * Validación específica de datos
     */
    private function validateSpecificData(Voyage $voyage): array
    {
        $validation = ['errors' => [], 'warnings' => []];

        // Verificar embarcación líder
        if (!$voyage->lead_vessel_id) {
            $validation['errors'][] = 'Viaje debe tener embarcación líder definida';
        }

        // Verificar fechas
        if (!$voyage->departure_date) {
            $validation['errors'][] = 'Viaje debe tener fecha de salida definida';
        }

        // Verificar empresa
        if (!$voyage->company_id) {
            $validation['errors'][] = 'Viaje debe estar asociado a una empresa válida';
        }

        return $validation;
    }

    /**
     * Validar si el voyage puede ser procesado para Información Anticipada
     * 
     * @param Voyage $voyage
     * @return array ['can_process' => bool, 'errors' => [], 'warnings' => []]
     */
    public function canProcessVoyage(Voyage $voyage): array
    {
        $validation = [
            'can_process' => false,
            'errors' => [],
            'warnings' => [],
        ];

        // 1. Validar voyage básico
        if (!$voyage || !$voyage->id) {
            $validation['errors'][] = 'Viaje no válido o no encontrado';
            return $validation;
        }

        // 2. Validar datos obligatorios del viaje
        if (!$voyage->voyage_number) {
            $validation['errors'][] = 'Número de viaje requerido';
        }

        if (!$voyage->lead_vessel_id) {
            $validation['errors'][] = 'Embarcación líder requerida';
        }

        if (!$voyage->origin_port_id || !$voyage->destination_port_id) {
            $validation['errors'][] = 'Puertos de origen y destino requeridos';
        }

        if (!$voyage->departure_date) {
            $validation['errors'][] = 'Fecha de salida requerida';
        }

        // 3. Validar empresa
        if ($voyage->company_id !== $this->company->id) {
            $validation['errors'][] = 'Viaje no pertenece a la empresa';
        }

        // 4. Warnings (no bloquean envío)
        if (!$voyage->captain_id) {
            $validation['warnings'][] = 'Recomendado: Asignar capitán al viaje';
        }

        if (!$voyage->estimated_arrival_date) {
            $validation['warnings'][] = 'Recomendado: Fecha estimada de llegada';
        }

        // 5. Determinar si puede procesar
        $validation['can_process'] = empty($validation['errors']);

        return $validation;
    }

    /**
     * Registrar operación en logs
     */
    private function logOperation(string $level, string $message, array $context = []): void
    {
        $context = array_merge([
            'service' => 'ArgentinaAnticipatedService',
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
        ], $context);

        match($level) {
            'debug' => \Log::debug($message, $context),
            'info' => \Log::info($message, $context),
            'warning' => \Log::warning($message, $context),
            'error' => \Log::error($message, $context),
            default => \Log::info($message, $context),
        };
    }

    /**
     * MÉTODO PRINCIPAL: RegistrarViaje - Registro de viaje ATA MT
     * 
     * Registra información anticipada del viaje completo con datos de cabecera,
     * embarcación, capitán y contenedores vacíos/correo.
     */
    public function registrarViaje(Voyage $voyage, array $options = []): array
    {
        try {
            DB::beginTransaction();

            // Crear transacción
            $transaction = $this->createWebserviceTransaction($voyage, array_merge($options, [
                'method' => 'RegistrarViaje',
                'soap_action' => $this->config['soap_action_registrar_viaje'],
            ]));
            
            $this->currentTransactionId = $transaction->id;

            // Validar datos específicos
            $validation = $this->validateSpecificData($voyage);
            if (!empty($validation['errors'])) {
                throw new Exception('Errores de validación: ' . implode(', ', $validation['errors']));
            }

            // Cargar relaciones necesarias
            $voyage->load([
                'company',
                'leadVessel',
                'captain',
                'originPort.country',
                'destinationPort.country',
                'shipments.vessel',
                'shipments.captain',
                'shipments.billsOfLading.shipmentItems.containers'
            ]);

            // Generar XML para RegistrarViaje
            $transactionId = 'ANTICIPADA_' . time() . '_' . $voyage->id;
            $xmlGenerator = new SimpleXmlGenerator($this->company);
            $xmlContent = $xmlGenerator->createRegistrarViajeXml($voyage, $transactionId);

            if (!$xmlContent) {
                throw new Exception('Error generando XML para RegistrarViaje');
            }

            // Crear cliente SOAP
            $soapClient = $this->soapClient->createClient($this->config['webservice_type'], $this->config['environment']);


            // Enviar request SOAP
            $soapResult = $this->sendSoapRequest($transaction, $soapClient, $xmlContent, 'RegistrarViaje');

            // Procesar respuesta
            // Extraer IdentificadorViaje de la respuesta XML
            // Extraer IdentificadorViaje de la respuesta (AFIP devuelve HTML)
            $voyageIdentifier = null;
            if (isset($soapResult['response_data'])) {
                $response = $soapResult['response_data'];
                
                // AFIP devuelve: <title></title>NUMERO_IDENTIFICADOR</head>
                if (preg_match('/<\/title>(\d+)<\/head>/', $response, $matches)) {
                    $voyageIdentifier = $matches[1];
                    \Log::info("IdentificadorViaje extraído de HTML", ['identificador' => $voyageIdentifier]);
                } else {
                    \Log::warning("No se encontró IdentificadorViaje en respuesta", ['response' => substr($response, 0, 200)]);
                }
            }

            if ($soapResult['success']) {
                $transaction->update([
                    'status' => 'success',
                    'external_reference' => $voyageIdentifier,
                    'response_data' => $soapResult['response_data'] ?? null,
                    'completed_at' => now(),
                ]);
                
                // ✅ PERSISTIR ESTADO DEL WEBSERVICE
                $this->updateWebserviceStatus($voyage, 'sent', [
                    'transaction_id' => $transaction->id,
                    'external_reference' => $voyageIdentifier,
                ]);

                Log::info('WebserviceSimple [anticipada]: RegistrarViaje enviado exitosamente', [
                    'voyage_id' => $voyage->id,
                    'transaction_id' => $transaction->id,
                    'external_reference' => $voyageIdentifier,
                ]);
                
                $soapResult['external_reference'] = $voyageIdentifier;
                DB::commit();
                $soapResult['transaction_id'] = $transaction->id;
                return $soapResult;

            } else {
                $transaction->update([
                    'status' => 'error',
                    'error_message' => $soapResult['error_message'] ?? 'Error desconocido',
                ]);

                $this->updateWebserviceStatus($voyage, 'error', [
                    'last_error_at' => now(),
                    'last_error_message' => $soapResult['error_message'],
                ]);

                DB::commit();
                $soapResult['transaction_id'] = $transaction->id;
                return $soapResult;
            }

        } catch (Exception $e) {
            DB::rollBack();
            
            $this->logOperation('error', 'Error en RegistrarViaje', [
                'error' => $e->getMessage(),
                'voyage_id' => $voyage->id,
            ]);

            return [
                'success' => false,
                'error_message' => $e->getMessage(),
                'transaction_id' => $this->currentTransactionId,
            ];
        }
    }

    /**
     * RectificarViaje - Rectificación de viaje ATA MT
     */
    public function rectificarViaje(Voyage $voyage, array $options = []): array
    {
        try {
            DB::beginTransaction();

            // Crear transacción
            $transaction = $this->createWebserviceTransaction($voyage, array_merge($options, [
                'method' => 'RectificarViaje',
                'soap_action' => $this->config['soap_action_rectificar_viaje'],
            ]));
            
            $this->currentTransactionId = $transaction->id;

            // Verificar que existe un viaje previo enviado
            $previousTransaction = $voyage->webserviceTransactions()
                ->where('webservice_type', 'anticipada')
                ->where('status', 'success')
                ->whereNotNull('external_reference')
                ->latest()
                ->first();

            if (!$previousTransaction) {
                throw new Exception('No se encontró un viaje previo enviado para rectificar');
            }

            // Cargar relaciones necesarias
            $voyage->load([
                'company',
                'leadVessel', 
                'captain',
                'originPort.country',
                'destinationPort.country',
                'shipments.vessel',
                'shipments.captain',
                'shipments.billsOfLading.shipmentItems.containers'
            ]);

            // Generar XML para RectificarViaje
            $transactionId = 'ANTICIPADA_' . time() . '_' . $voyage->id;
            $rectificationData = array_merge($options, [
                'original_external_reference' => $previousTransaction->external_reference,
            ]);
            
            $xmlGenerator = new SimpleXmlGenerator($this->company);
            $xmlContent = $xmlGenerator->createRectificarViajeXml($voyage, $rectificationData, $transactionId);

            if (!$xmlContent) {
                throw new Exception('Error generando XML para RectificarViaje');
            }

            // Crear cliente SOAP y enviar
            $soapClient = $this->soapClient->createClient($this->config['webservice_type'], $this->config['environment']);
            $soapResult = $this->sendSoapRequest($transaction, $soapClient, $xmlContent, 'RectificarViaje');

            // Procesar respuesta
            if ($soapResult['success']) {
                $transaction->update([
                    'status' => 'success',
                    'external_reference' => $soapResult['external_reference'] ?? null,
                    'response_data' => $soapResult['response_data'] ?? null,
                    'completed_at' => now(),
                ]);

                //$this->updateWebserviceStatus($voyage, 'sent', [
                //    'last_sent_at' => now(),
                //    'external_reference' => $soapResult['external_reference'],
                //]);

                DB::commit();
                return $soapResult;
            } else {
                $transaction->update([
                    'status' => 'error',
                    'error_message' => $soapResult['error_message'] ?? 'Error desconocido',
                ]);

                DB::commit();
                return $soapResult;
            }

        } catch (Exception $e) {
            DB::rollBack();
            
            $this->logOperation('error', 'Error en RectificarViaje', [
                'error' => $e->getMessage(),
                'voyage_id' => $voyage->id,
            ]);

            return [
                'success' => false,
                'error_message' => $e->getMessage(),
                'transaction_id' => $this->currentTransactionId,
            ];
        }
    }

    /**
     * RegistrarTitulosCbc - Registro de títulos ATA CBC
     */
    public function registrarTitulosCbc(Voyage $voyage, array $options = []): array
    {
        try {
            DB::beginTransaction();

            // Crear transacción
            $transaction = $this->createWebserviceTransaction($voyage, array_merge($options, [
                'method' => 'RegistrarTitulosCbc',
                'soap_action' => $this->config['soap_action_registrar_titulos_cbc'],
            ]));
            
            $this->currentTransactionId = $transaction->id;

            // Cargar relaciones necesarias
            $voyage->load([
                'company',
                'leadVessel',
                'captain', 
                'originPort.country',
                'destinationPort.country',
                'shipments.vessel',
                'shipments.captain',
                'shipments.billsOfLading.shipmentItems.containers'
            ]);

            // Generar XML para RegistrarTitulosCbc
            $transactionId = 'ANTICIPADA_' . time() . '_' . $voyage->id;
            $xmlGenerator = new SimpleXmlGenerator($this->company);
            $xmlContent = $xmlGenerator->createRegistrarTitulosCbcXml($voyage, $options, $transactionId);

            if (!$xmlContent) {
                throw new Exception('Error generando XML para RegistrarTitulosCbc');
            }

            // Crear cliente SOAP y enviar
            $soapClient = $this->soapClient->createClient($this->config['webservice_type'], $this->config['environment']);
            $soapResult = $this->sendSoapRequest($transaction, $soapClient, $xmlContent, 'RegistrarTitulosCbc');

            // Procesar respuesta
            if ($soapResult['success']) {
                $transaction->update([
                    'status' => 'success',
                    'external_reference' => $soapResult['external_reference'] ?? null,
                    'response_data' => $soapResult['response_data'] ?? null,
                    'completed_at' => now(),
                ]);

                //$this->updateWebserviceStatus($voyage, 'sent', [
                //    'last_sent_at' => now(),
                //    'external_reference' => $soapResult['external_reference'],
                //]);

                DB::commit();
                return $soapResult;
            } else {
                $transaction->update([
                    'status' => 'error',
                    'error_message' => $soapResult['error_message'] ?? 'Error desconocido',
                ]);

                DB::commit();
                return $soapResult;
            }

        } catch (Exception $e) {
            DB::rollBack();
            
            $this->logOperation('error', 'Error en RegistrarTitulosCbc', [
                'error' => $e->getMessage(),
                'voyage_id' => $voyage->id,
            ]);

            return [
                'success' => false,
                'error_message' => $e->getMessage(),
                'transaction_id' => $this->currentTransactionId,
            ];
        }
    }

    /**
     * Enviar request SOAP específico para Información Anticipada
     */
    private function sendSoapRequest($transaction, $soapClient, string $xmlContent, string $method): array
{
    try {
        Log::info("WebserviceSimple [anticipada]: Iniciando request SOAP {$method}", [
            'transaction_id' => $transaction->id,
            'xml_size_kb' => round(strlen($xmlContent) / 1024, 2),
        ]);

        // Actualizar estado a 'sending'
        $transaction->update(['status' => 'sending', 'sent_at' => now()]);

        // Extraer parámetros estructurados del XML
        $parameters = $this->extractSoapParameters($xmlContent, $method);

        // Enviar usando SoapClientService - CAMBIAR SOLO EL MÉTODO
        $startTime = microtime(true);
        $response = $soapClient->__doRequest(
            $xmlContent,
            'https://wsaduhomoext.afip.gob.ar/DIAV2/wgesinformacionanticipada/wgesinformacionanticipada.asmx',
            "Ar.Gob.Afip.Dga.Org.wgesinformacionanticipada/{$method}",
            SOAP_1_2
        );
        $responseTime = round((microtime(true) - $startTime) * 1000);

        $soapResult = [
            'success' => strpos($response, 'soap:Fault') === false,
            'response_data' => $response,
            'response_time_ms' => $responseTime,
            'request_xml' => $xmlContent,
            'response_xml' => $response,
        ];

        \Log::info("SOAP Response RAW", [
            'response_length' => strlen($response),
            'first_500_chars' => substr($response, 0, 500),
            'contains_soap' => strpos($response, 'soap:') !== false,
            'contains_html' => strpos($response, '<html') !== false,
        ]);

        // Actualizar transacción con XMLs
        $transaction->update([
            'request_xml' => $soapResult['request_xml'] ?? $xmlContent,
            'response_xml' => $soapResult['response_xml'] ?? null,
            'response_time_ms' => $soapResult['response_time_ms'] ?? null,
        ]);

        return $soapResult;

    } catch (Exception $e) {
        // Capturar respuesta SOAP completa para análisis
        $lastResponse = $soapClient->__getLastResponse() ?? '';
        $lastRequest = $soapClient->__getLastRequest() ?? '';
        
        // Extraer errores AFIP específicos
        $afipErrors = $this->extractAfipErrorDetails($lastResponse);
        
        // Logging detallado del error
        Log::error("WebserviceSimple [anticipada]: Error en request SOAP {$method}", [
            'error' => $e->getMessage(),
            'transaction_id' => $transaction->id,
            'has_afip_errors' => $afipErrors['has_afip_errors'],
            'afip_error_codes' => $afipErrors['has_afip_errors'] ? array_column($afipErrors['afip_errors'], 'codigo') : null,
            'afip_error_details' => $afipErrors['afip_errors'],
            'afip_error_summary' => $afipErrors['error_summary'],
            'soap_request_size' => strlen($lastRequest),
            'soap_response_size' => strlen($lastResponse),
            'soap_response_excerpt' => substr($lastResponse, 0, 1000),
        ]);

        return [
            'success' => false,
            'error_message' => $e->getMessage(),
            'response_time_ms' => isset($responseTime) ? $responseTime : null,
            'afip_error_details' => $afipErrors['afip_errors'],
            'afip_error_summary' => $afipErrors['error_summary'],
        ];
    }
}

    /**
     * Extraer parámetros SOAP del XML generado
     */
    private function extractSoapParameters(string $xmlContent, string $method): array
    {
        // Por simplicidad, retornar XML completo como parámetro
        return ['xmlParam' => $xmlContent];
    }

    /**
     * Procesar respuesta SOAP de AFIP
     */
    private function processSoapResponse($soapResponse, string $method): array
    {
        // TODO: Implementar procesamiento específico de respuestas AFIP
        // Por ahora retorna estructura básica
        return [
            'success' => true,
            'external_reference' => 'TEMP_REF_' . time(),
            'response_data' => $soapResponse,
        ];
    }

    /**
     * Convertir respuesta SOAP a XML para almacenamiento
     */
    private function soapResponseToXml($soapResponse): string
    {
        // TODO: Implementar conversión específica
        return is_object($soapResponse) || is_array($soapResponse) 
            ? json_encode($soapResponse) 
            : (string)$soapResponse;
    }

    /**
     * Crear transacción webservice
     */
    /**
     * Crear transacción webservice
     */
    private function createWebserviceTransaction(Voyage $voyage, array $options = []): object
    {
        return new class {
            public $id;
            
            public function __construct() {
                $this->id = time();
            }
            
            public function update($data) {
                // Mock update - no hace nada por ahora
                return true;
            }
        };
    }

    /**
     * Método público principal para envío
     */
    public function sendWebservice(Voyage $voyage, array $options = []): array
    {
        $method = $options['method'] ?? 'RegistrarViaje';
        
        switch ($method) {
            case 'RegistrarViaje':
                return $this->registrarViaje($voyage, $options);
                
            case 'RectificarViaje':
                return $this->rectificarViaje($voyage, $options);
                
            case 'RegistrarTitulosCbc':
                return $this->registrarTitulosCbc($voyage, $options);
                
            default:
                return [
                    'success' => false,
                    'error_message' => "Método no válido: {$method}",
                ];
        }
    }

    /**
 * Método temporal para debugging - obtener respuesta SOAP completa
 */
public function debugSoapResponse(Voyage $voyage): array
{
    try {
        // Generar XML como lo hace el método normal
        $xmlGenerator = new \App\Services\Simple\SimpleXmlGenerator($voyage->company);
        $transactionId = 'DEBUG_' . time();
        $xmlContent = $xmlGenerator->createRegistrarViajeXml($voyage, $transactionId);
        
        // Enviar y capturar respuesta completa
        $soapClient = $this->createSoapClient();
        $response = $this->sendSoapRequest($soapClient, $xmlContent, 'RegistrarViaje');
        
        // Obtener respuesta completa del cliente SOAP
        $lastResponse = $soapClient->__getLastResponse();
        $lastRequest = $soapClient->__getLastRequest();
        
        // Extraer errores AFIP
        $afipErrors = ['has_afip_errors' => false, 'afip_errors' => [], 'error_summary' => ''];
        
        return [
            'xml_sent' => $xmlContent,
            'xml_sent_size' => strlen($xmlContent),
            'soap_response_raw' => $lastResponse,
            'soap_response_size' => strlen($lastResponse),
            'afip_errors' => $afipErrors,
            'parsed_error' => $response,
        ];
        
    } catch (Exception $e) {
        return [
            'error' => $e->getMessage(),
            'soap_response_raw' => $soapClient->__getLastResponse() ?? '',
            'soap_request_raw' => $soapClient->__getLastRequest() ?? '',
        ];
    }
}

/**
     * Actualizar estado del webservice en VoyageWebserviceStatus
     */
    private function updateWebserviceStatus(Voyage $voyage, string $status, array $data = []): void
    {
        // Obtener o crear el estado del webservice
        $webserviceStatus = \App\Models\VoyageWebserviceStatus::firstOrCreate(
            [
                'voyage_id' => $voyage->id,
                'country' => 'AR',
                'webservice_type' => 'anticipada',
            ],
            [
                'company_id' => $this->company->id,
                'user_id' => $this->user->id,
                'status' => 'pending',
                'can_send' => true,
                'is_required' => true,
                'retry_count' => 0,
                'max_retries' => 3,
            ]
        );

        // Actualizar según el estado
        switch ($status) {
            case 'sent':
                $webserviceStatus->markAsSent(
                    $data['transaction_id'] ?? null,
                    $this->user->id
                );
                
                // Si tenemos external_reference, guardarlo también
                if (isset($data['external_reference'])) {
                    $webserviceStatus->update([
                        'external_voyage_number' => $data['external_reference'],
                    ]);
                }
                break;

            case 'approved':
                $webserviceStatus->markAsApproved(
                    $data['confirmation_number'] ?? null,
                    $data['external_reference'] ?? null,
                    $this->user->id
                );
                break;

            case 'error':
                $webserviceStatus->markAsError(
                    $data['error_code'] ?? null,
                    $data['error_message'] ?? null,
                    $this->user->id
                );
                break;
        }

        $this->logOperation('info', 'Estado de webservice actualizado', [
            'voyage_id' => $voyage->id,
            'status' => $status,
            'webservice_status_id' => $webserviceStatus->id,
        ]);
    }

    
}