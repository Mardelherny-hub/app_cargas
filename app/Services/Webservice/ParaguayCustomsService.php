<?php

namespace App\Services\Webservice;

use App\Models\Company;
use App\Models\Voyage;
use App\Models\Shipment;
use App\Models\Container;
use App\Models\Vessel;
use App\Models\WebserviceTransaction;
use App\Models\WebserviceLog;
use App\Models\WebserviceResponse;
use App\Services\Webservice\SoapClientService;
use App\Services\Webservice\XmlSerializerService;
use App\Services\Webservice\CertificateManagerService;
use Exception;
use SoapClient;
use SoapFault;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * MÓDULO 4: WEBSERVICES ADUANA - ParaguayCustomsService
 *
 * Servicio especializado para webservices de Aduanas Paraguay (DNA).
 * Maneja comunicación SOAP con sistema GDSF Paraguay.
 *
 * WEBSERVICES PARAGUAY SOPORTADOS:
 * - Manifiestos Fluviales (equivalente MIC/DTA Argentina)
 * - Consultas de Estado
 * - Rectificaciones de Manifiestos
 * - Anulaciones (si aplica)
 *
 * ENDPOINTS CONFIRMADOS:
 * - Testing: https://securetest.aduana.gov.py/wsdl/gdsf/serviciogdsf
 * - Auth: https://securetest.aduana.gov.py/wsdl/wsaaserver/Server
 * - Production: https://secure.aduana.gov.py/wsdl/gdsf/serviciogdsf
 *
 * DATOS REALES DEL SISTEMA:
 * - PARANA.csv: MAERSK LINE ARGENTINA S.A.
 * - Vessel: PAR13001, Voyage: V022NB
 * - Ruta: ARBUE → PYTVT (Buenos Aires → Terminal Villeta Paraguay)
 * - Containers: 40HC, 20GP con datos reales
 *
 * INTEGRACIÓN:
 * - Usa SoapClientService para comunicación
 * - XmlSerializerService para XML específico Paraguay
 * - CertificateManagerService para certificados .p12
 * - Sistema de logging y transacciones unificado
 * - Compatible con datos existentes del sistema
 */
class ParaguayCustomsService
{
    private Company $company;
    private SoapClientService $soapClient;
    private XmlSerializerService $xmlSerializer;
    private CertificateManagerService $certificateManager;
    private array $config;

    /**
     * Configuración específica Paraguay
     */
    private const PARAGUAY_CONFIG = [
        'country_code' => 'PY',
        'timeout' => 60,
        'max_retries' => 3,
        'retry_delay' => 5, // segundos
        'environment' => 'testing', // testing | production
    ];

    /**
     * Códigos específicos Paraguay GDSF
     */
    private const PARAGUAY_CODES = [
        'via_transporte' => 'FLUVIAL',
        'pais_paraguay' => 'PY',
        'pais_argentina' => 'AR',
        'tipo_documento_ruc' => 'RUC',
        'tipo_transporte' => 'HIDROVIA',
        'puerto_villeta' => 'PYTVT',
        'puerto_buenos_aires' => 'ARBUE',
        'moneda_default' => 'USD',
        'unidad_medida_kg' => 'KG',
        'unidad_medida_m3' => 'M3',
        'tipo_contenedor' => [
            '40HC' => '42G1', // 40' High Cube General
            '20GP' => '22G1', // 20' General Purpose
            '40GP' => '42G1', // 40' General Purpose
            '20OT' => '22U1', // 20' Open Top
            '40OT' => '42U1', // 40' Open Top
        ],
        'estado_contenedor' => [
            'full' => 'CARGADO',
            'empty' => 'VACIO',
        ],
        'tipo_manifiesto' => 'IMPO', // Importación
        'regimen_aduanero' => '10', // Importación para consumo
    ];

    public function __construct(Company $company)
    {
        $this->company = $company;
        $this->soapClient = new SoapClientService($company);
        $this->xmlSerializer = new XmlSerializerService($company);
        $this->certificateManager = new CertificateManagerService($company);
        $this->config = array_merge(self::PARAGUAY_CONFIG, [
            'company_ruc' => $this->cleanRuc($company->tax_id),
            'company_name' => $company->name,
        ]);

        $this->logOperation('info', 'ParaguayCustomsService inicializado', [
            'company' => $company->name,
            'ruc' => $this->config['company_ruc'],
        ]);
    }

    /**
     * Enviar Manifiesto de Importación Paraguay
     * Equivalente al MIC/DTA Argentina
     */
    public function sendImportManifest(Voyage $voyage, int $userId): array
    {
        try {
            $this->logOperation('info', 'Iniciando envío Manifiesto Paraguay', [
                'voyage_id' => $voyage->id,
                'voyage_number' => $voyage->voyage_number,
                'vessel' => $voyage->vessel->name ?? 'N/A',
                'user_id' => $userId,
            ]);

            // Validaciones previas
            $validation = $this->validateVoyageForManifest($voyage);
            if (!$validation['is_valid']) {
                return $this->buildErrorResponse('VALIDATION_ERROR', 
                    'Errores de validación', $validation['errors']);
            }

            // Generar transaction ID único
            $transactionId = $this->generateTransactionId('MANIFEST');

            // Crear transacción en base de datos
            $transaction = $this->createTransaction([
                'voyage_id' => $voyage->id,
                'webservice_type' => 'manifiesto',
                'country' => 'PY',
                'transaction_id' => $transactionId,
                'user_id' => $userId,
                'status' => 'pending',
            ]);

            // Generar XML del manifiesto
            $manifestXml = $this->xmlSerializer->createParaguayManifestXml($voyage, $transactionId);
            if (!$manifestXml) {
                $this->updateTransactionStatus($transaction, 'error', 
                    'XML_GENERATION_ERROR', 'Error generando XML del manifiesto');
                return $this->buildErrorResponse('XML_GENERATION_ERROR', 
                    'Error generando XML del manifiesto');
            }

            $transaction->update(['request_xml' => $manifestXml]);

            // Enviar al webservice Paraguay
            $response = $this->sendSoapRequest('gdsf', 'enviarManifiesto', $manifestXml, $transactionId);

            if ($response['success']) {
                // Procesar respuesta exitosa
                $this->processSuccessfulResponse($transaction, $response['data']);
                
                return [
                    'success' => true,
                    'transaction_id' => $transactionId,
                    'manifest_reference' => $response['data']['manifest_reference'] ?? null,
                    'paraguay_reference' => $response['data']['paraguay_reference'] ?? null,
                    'message' => 'Manifiesto enviado exitosamente a Paraguay',
                ];
            } else {
                // Procesar error del webservice
                $this->processErrorResponse($transaction, $response);
                
                return [
                    'success' => false,
                    'transaction_id' => $transactionId,
                    'error_code' => $response['error_code'],
                    'error_message' => $response['error_message'],
                    'can_retry' => $response['can_retry'] ?? false,
                ];
            }

        } catch (Exception $e) {
            $this->logOperation('error', 'Error crítico enviando manifiesto Paraguay', [
                'error' => $e->getMessage(),
                'voyage_id' => $voyage->id ?? null,
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->buildErrorResponse('CRITICAL_ERROR', 
                'Error crítico del sistema: ' . $e->getMessage());
        }
    }

    /**
     * Consultar estado de manifiesto Paraguay
     */
    public function queryManifestStatus(string $paraguayReference): array
    {
        try {
            $this->logOperation('info', 'Consultando estado manifiesto Paraguay', [
                'paraguay_reference' => $paraguayReference,
            ]);

            $transactionId = $this->generateTransactionId('QUERY');

            // Crear XML de consulta
            $queryXml = $this->xmlSerializer->createParaguayQueryXml($paraguayReference, $transactionId);
            if (!$queryXml) {
                return $this->buildErrorResponse('XML_GENERATION_ERROR', 
                    'Error generando XML de consulta');
            }

            // Enviar consulta
            $response = $this->sendSoapRequest('gdsf', 'consultarEstado', $queryXml, $transactionId);

            if ($response['success']) {
                return [
                    'success' => true,
                    'status' => $response['data']['status'] ?? 'UNKNOWN',
                    'status_description' => $response['data']['status_description'] ?? '',
                    'last_update' => $response['data']['last_update'] ?? null,
                    'observations' => $response['data']['observations'] ?? null,
                ];
            } else {
                return [
                    'success' => false,
                    'error_code' => $response['error_code'],
                    'error_message' => $response['error_message'],
                ];
            }

        } catch (Exception $e) {
            $this->logOperation('error', 'Error consultando estado Paraguay', [
                'error' => $e->getMessage(),
                'paraguay_reference' => $paraguayReference,
            ]);

            return $this->buildErrorResponse('QUERY_ERROR', 
                'Error consultando estado: ' . $e->getMessage());
        }
    }

    /**
     * Rectificar manifiesto Paraguay
     */
    public function rectifyManifest(string $paraguayReference, array $corrections): array
    {
        try {
            $this->logOperation('info', 'Iniciando rectificación Paraguay', [
                'paraguay_reference' => $paraguayReference,
                'corrections_count' => count($corrections),
            ]);

            $transactionId = $this->generateTransactionId('RECTIFY');

            // Crear XML de rectificación
            $rectifyXml = $this->xmlSerializer->createParaguayRectificationXml(
                $paraguayReference, $corrections, $transactionId
            );

            if (!$rectifyXml) {
                return $this->buildErrorResponse('XML_GENERATION_ERROR', 
                    'Error generando XML de rectificación');
            }

            // Enviar rectificación
            $response = $this->sendSoapRequest('gdsf', 'rectificarManifiesto', $rectifyXml, $transactionId);

            if ($response['success']) {
                return [
                    'success' => true,
                    'transaction_id' => $transactionId,
                    'rectification_reference' => $response['data']['rectification_reference'] ?? null,
                    'message' => 'Rectificación procesada exitosamente',
                ];
            } else {
                return [
                    'success' => false,
                    'error_code' => $response['error_code'],
                    'error_message' => $response['error_message'],
                ];
            }

        } catch (Exception $e) {
            $this->logOperation('error', 'Error en rectificación Paraguay', [
                'error' => $e->getMessage(),
                'paraguay_reference' => $paraguayReference,
            ]);

            return $this->buildErrorResponse('RECTIFICATION_ERROR', 
                'Error en rectificación: ' . $e->getMessage());
        }
    }

   /**
     * Enviar request SOAP a Paraguay con bypass inteligente
     */
    private function sendSoapRequest(string $webserviceType, string $operation, string $xml, string $transactionId): array
    {
        // ✅ OBTENER CONFIGURACIÓN DE PARAGUAY DESDE COMPANY
        $paraguayData = $this->company->getParaguayWebserviceData();
        $shouldBypass = $this->company->shouldBypassTesting('paraguay');
        
        $this->logOperation('info', 'Iniciando request SOAP Paraguay', [
            'webservice_type' => $webserviceType,
            'operation' => $operation,
            'transaction_id' => $transactionId,
            'xml_length' => strlen($xml),
            'ruc_configured' => !empty($paraguayData['ruc']),
            'should_bypass' => $shouldBypass,
            'environment' => $this->config['environment'],
        ]);

        // ✅ BYPASS INTELIGENTE: Verificar si debe simular respuesta
        //if ($shouldBypass || $this->config['environment'] === 'testing') {
        //    
        //    // Verificar si la configuración es de testing/desarrollo
        //    $isTestingConfig = $this->isTestingConfiguration($paraguayData);
        //    
        //    if ($isTestingConfig || $shouldBypass) {
        //        return $this->generateBypassResponse($operation, $transactionId, $paraguayData);
        //    }
        //}

        // ✅ VALIDAR CONFIGURACIÓN ANTES DE CONEXIÓN REAL
        $configErrors = $this->company->validateWebserviceConfig('paraguay');
        if (!empty($configErrors)) {
            $this->logOperation('error', 'Configuración Paraguay incompleta', [
                'operation' => $operation,
                'transaction_id' => $transactionId,
                'errors' => $configErrors,
            ]);

            return [
                'success' => false,
                'error_code' => 'CONFIG_INCOMPLETE',
                'error_message' => 'Configuración de Paraguay incompleta: ' . implode(', ', $configErrors),
                'can_retry' => false,
            ];
        }

        // ✅ CONEXIÓN REAL A PARAGUAY
        return $this->sendRealSoapRequest($webserviceType, $operation, $xml, $transactionId, $paraguayData);
    }

    /**
     * Verificar si la configuración es de testing/desarrollo
     */
    private function isTestingConfiguration(array $paraguayData): bool
    {
        // Verificar patrones típicos de datos de testing
        $testingPatterns = [
            'ruc' => ['80123456-7', '12345678-9', '00000000-0'],
            'company_name' => ['TEST', 'TESTING', 'DESARROLLO', 'DEMO'],
        ];

        // Verificar RUC de testing
        if (in_array($paraguayData['ruc'], $testingPatterns['ruc'])) {
            return true;
        }

        // Verificar nombre de empresa de testing
        $companyName = strtoupper($paraguayData['company_name'] ?? '');
        foreach ($testingPatterns['company_name'] as $pattern) {
            if (strpos($companyName, $pattern) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Generar respuesta simulada (bypass)
     */
    private function generateBypassResponse(string $operation, string $transactionId, array $paraguayData): array
    {
        $this->logOperation('info', 'BYPASS: Simulando respuesta Paraguay', [
            'operation' => $operation,
            'transaction_id' => $transactionId,
            'reason' => 'Configuración de testing o bypass activado',
            'ruc_used' => $paraguayData['ruc'] ?? 'no-configurado',
        ]);

        // Generar referencias realistas
        $paraguayReference = $this->generateRealisticParaguayReference();
        $manifestReference = 'SIM_' . substr($transactionId, -8);

        switch ($operation) {
            case 'enviarManifiesto':
                return [
                    'success' => true,
                    'status' => 'BYPASS_SUCCESS',
                    'data' => [
                        'manifest_reference' => $manifestReference,
                        'paraguay_reference' => $paraguayReference,
                        'processed_at' => now(),
                        'status_message' => 'Manifiesto procesado exitosamente (SIMULADO)',
                        'bypass_mode' => true,
                    ],
                ];

            case 'consultarEstado':
                return [
                    'success' => true,
                    'status' => 'PRESENTADO',
                    'status_description' => 'Manifiesto presentado y en proceso (SIMULADO)',
                    'last_update' => now(),
                    'bypass_mode' => true,
                ];

            default:
                return [
                    'success' => true,
                    'status' => 'BYPASS_SUCCESS',
                    'message' => "Operación {$operation} simulada exitosamente",
                    'bypass_mode' => true,
                ];
        }
    }

    /**
     * Enviar request SOAP real a Paraguay
     */
    private function sendRealSoapRequest(string $webserviceType, string $operation, string $xml, string $transactionId, array $paraguayData): array
    {
        try {
            $this->logOperation('info', 'Enviando request SOAP REAL Paraguay', [
                'webservice_type' => $webserviceType,
                'operation' => $operation,
                'transaction_id' => $transactionId,
                'ruc' => $paraguayData['ruc'],
                'company_name' => $paraguayData['company_name'],
            ]);

            // Obtener URL específica desde configuración
            $webserviceUrl = $this->company->getWebserviceUrl('paraguay', 'tere', $this->config['environment']);
            
            if (!$webserviceUrl) {
                throw new Exception("URL del webservice Paraguay no configurada para ambiente {$this->config['environment']}");
            }

            // Crear cliente SOAP con URL de configuración
            $client = $this->soapClient->createClient($webserviceType, $this->config['environment']);
            
            // Configurar headers específicos Paraguay
            $headers = $this->buildParaguayHeaders($operation);
            
            // Realizar llamada SOAP
            $startTime = microtime(true);
            $soapResponse = $client->__soapCall($operation, [$xml], null, $headers);
            $responseTime = microtime(true) - $startTime;

            $this->logOperation('info', 'Respuesta SOAP Paraguay recibida', [
                'operation' => $operation,
                'transaction_id' => $transactionId,
                'response_time' => round($responseTime, 3),
                'response_size' => strlen(serialize($soapResponse)),
                'webservice_url' => $webserviceUrl,
            ]);

            // Parsear respuesta Paraguay
            return $this->parseParaguayResponse($soapResponse, $operation);

        } catch (SoapFault $e) {
            $this->logOperation('error', 'SOAP Fault Paraguay', [
                'operation' => $operation,
                'transaction_id' => $transactionId,
                'fault_code' => $e->faultcode,
                'fault_string' => $e->faultstring,
                'detail' => $e->detail ?? null,
            ]);

            return [
                'success' => false,
                'error_type' => 'soap_fault',
                'error_code' => $e->faultcode,
                'error_message' => $e->faultstring,
                'can_retry' => $this->isSoapFaultRetryable($e->faultcode),
            ];

        } catch (Exception $e) {
            $this->logOperation('error', 'Error comunicación Paraguay', [
                'operation' => $operation,
                'transaction_id' => $transactionId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error_code' => 'COMMUNICATION_ERROR',
                'error_message' => $e->getMessage(),
                'can_retry' => true,
            ];
        }
    }

    /**
     * Generar referencia Paraguay realista para testing
     */
    private function generateRealisticParaguayReference(): string
    {
        $year = date('Y');
        $office = '704'; // Código típico aduana Paraguay
        $sequence = str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
        $checkDigit = substr(md5($year . $office . $sequence), -1);
        
        return $year . $office . 'TERE' . $sequence . strtoupper($checkDigit);
    }

    /**
     * Parsear respuesta de Paraguay GDSF
     */
    private function parseParaguayResponse($soapResponse, string $operation): array
    {
        try {
            // Paraguay devuelve XML en formato específico según documentación GDSF
            if (is_object($soapResponse) && isset($soapResponse->xml)) {
                $responseXml = $soapResponse->xml;
                
                // Parsear XML de respuesta Paraguay
                $dom = new \DOMDocument();
                if (!$dom->loadXML($responseXml)) {
                    throw new Exception('XML de respuesta inválido');
                }

                // Extraer datos según operación
                switch ($operation) {
                    case 'enviarManifiesto':
                        return $this->parseManifestResponse($dom);
                    case 'consultarEstado':
                        return $this->parseStatusResponse($dom);
                    case 'rectificarManifiesto':
                        return $this->parseRectificationResponse($dom);
                    default:
                        return $this->parseGenericResponse($dom);
                }
            }

            throw new Exception('Formato de respuesta Paraguay no reconocido');

        } catch (Exception $e) {
            $this->logOperation('error', 'Error parseando respuesta Paraguay', [
                'operation' => $operation,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error_code' => 'RESPONSE_PARSING_ERROR',
                'error_message' => 'Error procesando respuesta: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Parsear respuesta de envío de manifiesto
     */
    private function parseManifestResponse(\DOMDocument $dom): array
    {
        // Buscar elementos específicos de respuesta Paraguay
        $statusElements = $dom->getElementsByTagName('statusCode');
        $idElements = $dom->getElementsByTagName('id');
        $messageElements = $dom->getElementsByTagName('MessageHeaderDocument');

        if ($statusElements->length > 0) {
            $status = $statusElements->item(0)->textContent;
            
            if ($status === 'OK' || $status === 'SUCCESS') {
                // Respuesta exitosa
                $manifestReference = null;
                $paraguayReference = null;

                // Extraer referencias según documentación GDSF
                if ($idElements->length > 0) {
                    $paraguayReference = $idElements->item(0)->textContent;
                }

                if ($messageElements->length > 0) {
                    $headerElement = $messageElements->item(0);
                    $ramIdElements = $headerElement->getElementsByTagName('ID');
                    if ($ramIdElements->length > 0) {
                        $manifestReference = $ramIdElements->item(0)->textContent;
                    }
                }

                return [
                    'success' => true,
                    'data' => [
                        'status' => $status,
                        'manifest_reference' => $manifestReference,
                        'paraguay_reference' => $paraguayReference,
                        'processed_at' => now(),
                    ],
                ];
            } else {
                // Respuesta con error
                $errorMessage = 'Error procesando manifiesto';
                $errorDetails = $dom->getElementsByTagName('ResponseStatus');
                if ($errorDetails->length > 0) {
                    $errorMessage = $errorDetails->item(0)->textContent;
                }

                return [
                    'success' => false,
                    'error_code' => $status,
                    'error_message' => $errorMessage,
                ];
            }
        }

        return [
            'success' => false,
            'error_code' => 'INVALID_RESPONSE',
            'error_message' => 'Respuesta de Paraguay sin estado válido',
        ];
    }

    /**
     * Parsear respuesta de consulta de estado
     */
    private function parseStatusResponse(\DOMDocument $dom): array
    {
        $statusElements = $dom->getElementsByTagName('statusCode');
        $detailElements = $dom->getElementsByTagName('detalles');

        if ($statusElements->length > 0) {
            $status = $statusElements->item(0)->textContent;
            $statusDescription = '';
            $observations = null;

            if ($detailElements->length > 0) {
                $statusDescription = $detailElements->item(0)->textContent;
            }

            return [
                'success' => true,
                'data' => [
                    'status' => $status,
                    'status_description' => $statusDescription,
                    'last_update' => now(),
                    'observations' => $observations,
                ],
            ];
        }

        return [
            'success' => false,
            'error_code' => 'QUERY_FAILED',
            'error_message' => 'No se pudo obtener el estado del manifiesto',
        ];
    }

    /**
     * Parsear respuesta de rectificación
     */
    private function parseRectificationResponse(\DOMDocument $dom): array
    {
        $statusElements = $dom->getElementsByTagName('statusCode');
        $idElements = $dom->getElementsByTagName('id');

        if ($statusElements->length > 0) {
            $status = $statusElements->item(0)->textContent;
            
            if ($status === 'OK' || $status === 'SUCCESS') {
                $rectificationReference = null;
                if ($idElements->length > 0) {
                    $rectificationReference = $idElements->item(0)->textContent;
                }

                return [
                    'success' => true,
                    'data' => [
                        'status' => $status,
                        'rectification_reference' => $rectificationReference,
                        'processed_at' => now(),
                    ],
                ];
            }
        }

        return [
            'success' => false,
            'error_code' => 'RECTIFICATION_FAILED',
            'error_message' => 'Error procesando rectificación',
        ];
    }

    /**
     * Parsear respuesta genérica
     */
    private function parseGenericResponse(\DOMDocument $dom): array
    {
        $statusElements = $dom->getElementsByTagName('statusCode');
        
        if ($statusElements->length > 0) {
            $status = $statusElements->item(0)->textContent;
            return [
                'success' => ($status === 'OK' || $status === 'SUCCESS'),
                'data' => ['status' => $status],
            ];
        }

        return [
            'success' => false,
            'error_code' => 'UNKNOWN_RESPONSE',
            'error_message' => 'Respuesta de Paraguay no reconocida',
        ];
    }

    /**
     * Validar viaje para manifiesto Paraguay
     */
   /**
     * Validar viaje para manifiesto Paraguay - VERSIÓN FINAL CORREGIDA
     * 
     * BASADO EN ESTRUCTURA REAL DEL SISTEMA:
     * - Voyage -> Shipments -> BillsOfLading -> ShipmentItems
     * - ShipmentItems -> Containers (tabla pivote container_shipment_item)
     * - Paraguay acepta tanto carga contenedorizada como a granel
     */
    private function validateVoyageForManifest(Voyage $voyage): array
    {
        $errors = [];
        $warnings = [];

        $this->logOperation('debug', 'INICIANDO validateVoyageForManifest', [
            'voyage_id' => $voyage->id,
            'voyage_number' => $voyage->voyage_number
        ]);

        // 1. Validar datos básicos del viaje
        if (!$voyage->vessel) {
            $errors[] = 'El viaje debe tener una embarcación asignada';
        }

        if (!$voyage->voyage_number) {
            $errors[] = 'El viaje debe tener un número de viaje';
        }

        $this->logOperation('debug', 'Validaciones básicas completadas', [
            'errors_count' => count($errors)
        ]);

        // 2. Validar que llegue a Paraguay (opcional - warning solamente)
        // ✅ REEMPLAZAR CON ESTO:
if (!$voyage->origin_port_id || !$voyage->destination_port_id) {
    $errors[] = 'El viaje debe tener puertos de salida y llegada definidos';
}

// Agregar log para debug de datos reales
$this->logOperation('debug', 'Datos reales del viaje para validación', [
    'voyage_id' => $voyage->id,
    'voyage_number' => $voyage->voyage_number,
    'origin_port_id' => $voyage->origin_port_id,
    'destination_port_id' => $voyage->destination_port_id,
    'has_origin' => !empty($voyage->origin_port_id),
    'has_destination' => !empty($voyage->destination_port_id)
]);

        // 3. Validar shipments
        $shipments = $voyage->shipments;
        if ($shipments->isEmpty()) {
            $errors[] = 'El viaje debe tener al menos un envío/carga';
        }

        $this->logOperation('debug', 'Validación shipments', [
            'shipments_count' => $shipments->count(),
            'shipments_ids' => $shipments->pluck('id')->toArray()
        ]);

        // 4. Validar carga por shipment (VERSIÓN CORREGIDA)
        foreach ($shipments as $shipment) {
            $this->logOperation('debug', 'Validando shipment', [
                'shipment_id' => $shipment->id,
                'shipment_number' => $shipment->shipment_number ?? 'N/A'
            ]);

            // 4.1 Verificar que tenga conocimientos de embarque (Bills of Lading)
            $billsCount = $shipment->billsOfLading()->count();
            if ($billsCount === 0) {
                $warnings[] = "El shipment {$shipment->shipment_number} no tiene conocimientos de embarque";
            }

            // 4.2 Verificar carga a través de shipmentItems (estructura correcta)
            try {
                $shipmentItemsCount = $shipment->shipmentItems()->count();
                
                if ($shipmentItemsCount === 0) {
                    $warnings[] = "El shipment {$shipment->shipment_number} no tiene ítems de carga definidos";
                } else {
                    $this->logOperation('debug', 'Shipment tiene carga', [
                        'shipment_id' => $shipment->id,
                        'shipment_items_count' => $shipmentItemsCount
                    ]);

                    // 4.3 Verificar contenedores (si los hay) - ESTRUCTURA CORRECTA
                    $containersCount = $shipment->shipmentItems()
                        ->whereHas('containers')
                        ->count();

                    if ($containersCount > 0) {
                        $this->logOperation('debug', 'Shipment tiene contenedores', [
                            'shipment_id' => $shipment->id,
                            'items_with_containers' => $containersCount
                        ]);

                        // Validar contenedores específicos
                        $shipmentItems = $shipment->shipmentItems()->with('containers')->get();
                        foreach ($shipmentItems as $item) {
                            foreach ($item->containers as $container) {
                                if (!$container->container_number) {
                                    $errors[] = "Contenedor sin número en shipment {$shipment->shipment_number}";
                                }
                            }
                        }
                    } else {
                        $this->logOperation('debug', 'Shipment sin contenedores (posible carga a granel)', [
                            'shipment_id' => $shipment->id,
                            'note' => 'Paraguay acepta carga a granel'
                        ]);
                    }
                }

            } catch (\Exception $e) {
                $this->logOperation('warning', 'Error validando carga del shipment', [
                    'shipment_id' => $shipment->id,
                    'error' => $e->getMessage()
                ]);
                $warnings[] = "No se pudo validar completamente la carga del shipment {$shipment->shipment_number}";
            }
        }

        $this->logOperation('debug', 'Validación de carga completada');

        // 5. Validar datos de la empresa
        if (!$this->company->tax_id) {
            $errors[] = 'La empresa debe tener RUC/CUIT configurado';
        }

        // 6. Validar certificados para Paraguay
        $certValidation = $this->certificateManager->validateCompanyCertificate();
        if (!$certValidation['is_valid']) {
            $errors[] = 'La empresa debe tener certificado digital válido para Paraguay';
            // Agregar errores específicos del certificado
            $errors = array_merge($errors, $certValidation['errors']);
        }

        $this->logOperation('debug', 'FINALIZANDO validateVoyageForManifest', [
            'is_valid' => empty($errors),
            'errors_count' => count($errors),
            'warnings_count' => count($warnings),
            'errors' => $errors,        
            'warnings' => $warnings
        ]);

        return [
            'is_valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    /**
     * Construir headers SOAP para Paraguay
     */
    private function buildParaguayHeaders(string $operation): array
    {
        return [
            'SOAPAction' => "\"urn:gdsf:$operation\"",
            'Content-Type' => 'text/xml; charset=utf-8',
            'User-Agent' => 'ParaguayCustomsService/1.0',
        ];
    }

    /**
     * Determinar si un SOAP Fault es reintentar
     */
    private function isSoapFaultRetryable(string $faultCode): bool
    {
        $retryableFaults = [
            'Server.Timeout',
            'Server.Busy',
            'Client.ConnectionTimeout',
            'Server.TemporaryUnavailable',
        ];

        return in_array($faultCode, $retryableFaults);
    }

    /**
     * Generar ID de transacción único
     */
    private function generateTransactionId(string $prefix): string
    {
        return sprintf(
            '%s_%s_%s_%s',
            $prefix,
            $this->config['company_ruc'],
            date('YmdHis'),
            substr(uniqid(), -6)
        );
    }

    /**
     * Limpiar RUC paraguayo (sin guiones ni puntos)
     */
    private function cleanRuc(string $ruc): string
    {
        return preg_replace('/[^0-9]/', '', $ruc);
    }

    /**
     * Helper methods (reusables del patrón Argentina)
     */
   private function createTransaction(array $data): WebserviceTransaction
{
    // Valores por defecto para Paraguay
    $defaults = [
        'company_id' => $this->company->id,
        'webservice_url' => 'https://securetest.aduana.gov.py/wsdl/gdsf/serviciogdsf',
        'soap_action' => 'urn:gdsf:enviarManifiesto',
        'environment' => 'testing',
        'retry_count' => 0,
        'max_retries' => 3,
        'currency_code' => 'USD',
        'container_count' => 0,
        'total_weight_kg' => 0,
        'ip_address' => request()?->ip() ?? '127.0.0.1',
        'user_agent' => request()?->userAgent() ?? 'ParaguayCustomsService',
        'created_at' => now(),
    ];

    return WebserviceTransaction::create(array_merge($defaults, $data));
}

    private function updateTransactionStatus(WebserviceTransaction $transaction, string $status, string $errorCode = null, string $errorMessage = null): void
    {
        $transaction->update([
            'status' => $status,
            'error_code' => $errorCode,
            'error_message' => $errorMessage,
            'response_at' => now(),
        ]);
    }

    private function processSuccessfulResponse(WebserviceTransaction $transaction, array $responseData): void
    {
        $transaction->update([
            'status' => 'success',
            'response_at' => now(),
        ]);

        // Crear registro de respuesta
        WebserviceResponse::create([
            'transaction_id' => $transaction->id,
            'confirmation_number' => $responseData['paraguay_reference'] ?? null,
            'reference_number' => $responseData['manifest_reference'] ?? null,
            'paraguay_gdsf_reference' => $responseData['paraguay_reference'] ?? null,
            'customs_status' => 'RECEIVED',
            'customs_processed_at' => $responseData['processed_at'] ?? now(),
        ]);
    }

    private function processErrorResponse(WebserviceTransaction $transaction, array $errorResponse): void
    {
        $transaction->update([
            'status' => 'error',
            'error_code' => $errorResponse['error_code'],
            'error_message' => $errorResponse['error_message'],
            'response_at' => now(),
        ]);
    }

    private function buildErrorResponse(string $errorCode, string $message, array $details = []): array
    {
        return [
            'success' => false,
            'error_code' => $errorCode,
            'error_message' => $message,
            'details' => $details,
            'timestamp' => now()->toISOString(),
        ];
    }

    private function logOperation(string $level, string $message, array $context = [], string $category = 'general'): void
    {
        $logData = array_merge([
            'service' => 'ParaguayCustomsService',
            'company_id' => $this->company->id,
            'company_name' => $this->company->legal_name,
            'timestamp' => now()->toISOString(),
        ], $context);

        // Log en archivo Laravel
        Log::{$level}($message, $logData);

        // Log en tabla webservice_logs solo si hay transaction_id válido
        try {
            if (isset($context['transaction_id']) && $context['transaction_id'] !== null) {
                WebserviceLog::create([
                    'transaction_id' => $this->getCurrentTransactionId($context['transaction_id']),
                    'level' => $level,
                    'category' => $category,  // ✅ AGREGADO campo obligatorio
                    'message' => $message,
                    'context' => $logData,
                    'created_at' => now(),
                ]);
            }
        } catch (Exception $e) {
            Log::error('Error logging to webservice_logs table', [
                'original_message' => $message,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Obtener ID numérico de transacción desde string
     */
    private function getCurrentTransactionId(string $stringTransactionId): ?int
    {
        $transaction = WebserviceTransaction::where('transaction_id', $stringTransactionId)->first();
        return $transaction?->id;
    }

    /**
     * Obtener URL del webservice Paraguay
     */
    private function getWebserviceUrl(): string
    {
        // URLs configuradas para Paraguay
        $urls = [
            'testing' => 'https://securetest.aduana.gov.py/wsdl/gdsf/serviciogdsf',
            'production' => 'https://secure.aduana.gov.py/wsdl/gdsf/serviciogdsf'
        ];

        return $urls[$this->config['environment']] ?? $urls['testing'];
    }

    /**
     * Método wrapper para el controller
     */
    public function sendManifest(Voyage $voyage): array
    {
        return $this->sendImportManifest($voyage, auth()->id());
    }
}