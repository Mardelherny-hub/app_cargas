<?php

namespace App\Services\Webservice;

use App\Models\Company;
use App\Models\Shipment;
use App\Models\User;
use App\Models\WebserviceTransaction;
use App\Models\WebserviceResponse;
use App\Models\WebserviceLog;
use App\Services\Webservice\SoapClientService;
use App\Services\Webservice\CertificateManagerService;
use App\Services\Webservice\XmlSerializerService;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * MÓDULO 4: WEBSERVICES ADUANA - ArgentinaMicDtaService
 *
 * Servicio integrador completo para MIC/DTA Argentina AFIP.
 * Combina todos los servicios del módulo para el envío completo de MIC/DTA.
 * 
 * Integra:
 * - SoapClientService: Cliente SOAP base con URLs reales
 * - CertificateManagerService: Gestión certificados .p12
 * - XmlSerializerService: Generación XML según especificación AFIP
 * 
 * Funcionalidades:
 * - Validación completa pre-envío usando datos reales del sistema
 * - Generación de XML MIC/DTA con datos de PARANA (MAERSK, PAR13001, V022NB)
 * - Envío SOAP al webservice Argentina con autenticación
 * - Procesamiento de respuestas y actualización de estados
 * - Sistema completo de logs y auditoría de transacciones
 * - Manejo de errores y reintentos automáticos
 * 
 * Datos reales soportados:
 * - Empresas: MAERSK LINE ARGENTINA S.A. (CUIT, certificados .p12)
 * - Embarcaciones: PAR13001, GUARAN F, REINA DEL PARANA
 * - Viajes: V022NB, V023NB con rutas ARBUE → PYTVT  
 * - Contenedores: 40HC, 20GP, capacidades 38-48 unidades
 * - Capitanes: asignados del sistema con licencias válidas
 */
class ArgentinaMicDtaService
{
    private Company $company;
    private User $user;
    private ?int $currentTransactionId = null;
    private SoapClientService $soapClient;
    private CertificateManagerService $certificateManager;
    private XmlSerializerService $xmlSerializer;
    private array $config;

    /**
     * Configuración específica para MIC/DTA Argentina
     */
    private const MICDTA_CONFIG = [
        'webservice_type' => 'micdta',
        'country' => 'AR',
        'soap_action' => 'Ar.Gob.Afip.Dga.wgesregsintia2/RegistrarMicDta',
        'environment' => 'testing', // Por defecto testing, se puede cambiar
        'max_retries' => 3,
        'retry_intervals' => [30, 120, 300], // 30s, 2min, 5min
        'timeout_seconds' => 60,
        'require_certificate' => true,
        'validate_xml_structure' => true,
    ];

    public function __construct(Company $company, User $user, array $config = [])
    {
        $this->company = $company;
        $this->user = $user;
        $this->config = array_merge(self::MICDTA_CONFIG, $config);

        // Inicializar servicios integrados
        $this->soapClient = new SoapClientService($company);
        $this->certificateManager = new CertificateManagerService($company);
        $this->xmlSerializer = new XmlSerializerService($company);

        $this->logOperation('info', 'ArgentinaMicDtaService inicializado', [
            'company_id' => $company->id,
            'company_name' => $company->legal_name,
            'user_id' => $user->id,
            'environment' => $this->config['environment'],
        ]);
    }

   /**
 * Enviar MIC/DTA completo para un shipment
 */
public function sendMicDta(Shipment $shipment): array
{
    $result = [
        'success' => false,
        'transaction_id' => null,
        'response_data' => null,
        'errors' => [],
        'warnings' => [],
    ];

    DB::beginTransaction();

    try {
        // Log inicial SIN transaction_id
        $this->logInitialization('info', 'Iniciando envío MIC/DTA completo', [
            'shipment_id' => $shipment->id,
            'shipment_number' => $shipment->shipment_number,
            'voyage_number' => $shipment->voyage?->voyage_number,
            'vessel_name' => $shipment->vessel?->name,
        ]);

        // 1. Validaciones integrales pre-envío
        $validation = $this->validateForMicDta($shipment);
        if (!$validation['is_valid']) {
            $result['errors'] = $validation['errors'];
            return $result;
        }

        // 2. Crear transacción en base de datos ✅ ANTES de logs con transaction_id
        $transaction = $this->createTransaction($shipment);
        $result['transaction_id'] = $transaction->id;

        // ✅ AHORA SÍ GUARDAR EL TRANSACTION_ID PARA LOGS POSTERIORES
        $this->currentTransactionId = $transaction->id;

        // 3. Generar XML usando el XmlSerializerService
        $xmlContent = $this->generateXml($shipment, $transaction->transaction_id);
        if (!$xmlContent) {
            throw new Exception('Error generando XML MIC/DTA');
        }

        // ✅ BYPASS INTELIGENTE ARGENTINA - DESPUÉS DE XML, ANTES DE SOAP
        $argentinaData = $this->company->getArgentinaWebserviceData();
        $shouldBypass = $this->company->shouldBypassTesting('argentina');
        $isTestingConfig = $this->company->isTestingConfiguration('argentina', $argentinaData);

        $this->logOperation('info', 'Verificando bypass Argentina', [
            'transaction_id' => $transaction->id,
            'should_bypass' => $shouldBypass,
            'is_testing_config' => $isTestingConfig,
            'environment' => $this->config['environment'],
            'cuit' => $argentinaData['cuit'] ?? 'no-configurado',
        ]);

        if ($shouldBypass || $this->config['environment'] === 'testing') {
            if ($isTestingConfig || $shouldBypass) {
                
                $this->logOperation('info', 'BYPASS ACTIVADO: Simulando respuesta Argentina MIC/DTA', [
                    'transaction_id' => $transaction->id,
                    'reason' => $shouldBypass ? 'Bypass empresarial activado' : 'Configuración de testing detectada',
                    'cuit_used' => $argentinaData['cuit'] ?? 'no-configurado',
                ]);

                // Generar respuesta simulada
                $bypassResponse = $this->generateBypassResponse('RegistrarMicDta', $transaction->transaction_id, $argentinaData);
                
                // Actualizar transacción como exitosa con datos de bypass
                $transaction->update([
                    'status' => 'success',
                    'response_at' => now(),
                    'confirmation_number' => $bypassResponse['response_data']['micdta_id'],
                    'success_data' => $bypassResponse,
                    'request_xml' => $xmlContent, // Guardar XML generado
                ]);

                // Crear registro de respuesta estructurada
                WebserviceResponse::create([
                    'transaction_id' => $transaction->id,
                    'response_type' => 'success',
                    'voyage_number' => $bypassResponse['response_data']['voyage_number'],
                    'response_data' => $bypassResponse,
                    'processed_at' => now(),
                ]);

                // Preparar resultado final
                $result = [
                    'success' => true,
                    'transaction_id' => $transaction->id,
                    'response_data' => $bypassResponse['response_data'],
                    'bypass_mode' => true,
                    'errors' => [],
                    'warnings' => ['Respuesta simulada - Ambiente de testing o bypass activado'],
                ];

                DB::commit();
                return $result;
            }
        }

        // ✅ VALIDAR CONFIGURACIÓN ANTES DE CONEXIÓN REAL
        $configErrors = $this->company->validateWebserviceConfig('argentina');
        if (!empty($configErrors)) {
            $this->logOperation('error', 'Configuración Argentina incompleta', [
                'transaction_id' => $transaction->id,
                'errors' => $configErrors,
            ]);

            $transaction->update([
                'status' => 'error',
                'error_message' => 'Configuración incompleta: ' . implode(', ', $configErrors),
                'response_at' => now(),
            ]);

            $result['errors'] = $configErrors;
            DB::commit(); // Commit para guardar el estado de error
            return $result;
        }

        // ✅ CONEXIÓN REAL A ARGENTINA - Solo si bypass no activado y configuración OK
        $this->logOperation('info', 'Procediendo con conexión real a AFIP', [
            'transaction_id' => $transaction->id,
            'environment' => $this->config['environment'],
        ]);

        // 4. Preparar cliente SOAP
        $soapClient = $this->prepareSoapClient();

        // 5. Enviar request SOAP
        $soapResponse = $this->sendSoapRequest($transaction, $soapClient, $xmlContent);

        // 6. Procesar respuesta
        $result = $this->processResponse($transaction, $soapResponse);

        DB::commit();
        return $result;

    } catch (Exception $e) {
        DB::rollBack();

        // Log de error SIN transaction_id si no se creó aún
        $this->logOperation('error', 'Error en envío MIC/DTA', [
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'shipment_id' => $shipment->id,
            'transaction_id' => $this->currentTransactionId ?? 'not_created',
        ], 'error_handling');

        // Actualizar transacción si existe
        if (isset($transaction)) {
            $transaction->update([
                'status' => 'error',
                'error_message' => $e->getMessage(),
                'response_at' => now(),
            ]);
        }

        $result['errors'][] = $e->getMessage();
        return $result;
    }
}

    /**
     * Validaciones integrales para MIC/DTA usando datos reales
     */
    private function validateForMicDta(Shipment $shipment): array
    {
        $validation = [
            'is_valid' => false,
            'errors' => [],
            'warnings' => [],
        ];

        // 1. Validar empresa activa con webservices
        if (!$this->company->active) {
            $validation['errors'][] = 'Empresa inactiva';
        }

        if (!$this->company->ws_active) {
            $validation['errors'][] = 'Webservices deshabilitados para la empresa';
        }

        // 2. Validar certificado de la empresa
        $certValidation = $this->certificateManager->validateCompanyCertificate();
        if (!$certValidation['is_valid']) {
            $validation['errors'] = array_merge($validation['errors'], $certValidation['errors']);
        }

        // 3. Validar shipment y relaciones obligatorias
        if (!$shipment->voyage) {
            $validation['errors'][] = 'Shipment debe tener un viaje asociado';
        }

        if (!$shipment->vessel) {
            $validation['errors'][] = 'Shipment debe tener una embarcación asociada';
        }

        if (!$shipment->voyage?->company_id || $shipment->voyage->company_id !== $this->company->id) {
            $validation['errors'][] = 'Viaje no pertenece a la empresa actual';
        }

        // 4. Validar datos específicos del viaje
        $voyage = $shipment->voyage;
        if ($voyage) {
            if (!$voyage->voyage_number) {
                $validation['errors'][] = 'Viaje debe tener número de viaje';
            }

            if (!$voyage->departure_date) {
                $validation['errors'][] = 'Viaje debe tener fecha de salida';
            }

            if (!$voyage->origin_port_id || !$voyage->destination_port_id) {
                $validation['errors'][] = 'Viaje debe tener puertos de origen y destino';
            }
        }

        // 5. Validar datos de la embarcación
        $vessel = $shipment->vessel;
        if ($vessel) {
            if (!$vessel->name) {
                $validation['errors'][] = 'Embarcación debe tener nombre';
            }

            if (!$vessel->vesselType) {
                $validation['warnings'][] = 'Embarcación sin tipo definido';
            }
        }

        // 6. Validar datos de carga (usando datos reales del shipment)
        if ($shipment->containers_loaded === 0 && $shipment->cargo_weight_loaded == 0) {
            $validation['warnings'][] = 'Shipment sin carga (en lastre)';
        }

        // 7. Validar CUIT de la empresa
        if (!$this->company->tax_id || strlen(preg_replace('/[^0-9]/', '', $this->company->tax_id)) !== 11) {
            $validation['errors'][] = 'CUIT de empresa inválido para Argentina';
        }

        // 8. Validar país de operación
        if ($this->company->country !== 'AR') {
            $validation['errors'][] = 'MIC/DTA Argentina solo para empresas argentinas';
        }

        $validation['is_valid'] = empty($validation['errors']);

        $this->logOperation($validation['is_valid'] ? 'info' : 'warning', 'Validación MIC/DTA completada', $validation);

        return $validation;
    }

    /**
     * Crear transacción en base de datos usando estructura real
     */
    private function createTransaction(Shipment $shipment): WebserviceTransaction
    {
        // Generar ID único de transacción (formato: EMPRESA-FECHA-SECUENCIA)
        $companyCode = substr(preg_replace('/[^0-9]/', '', $this->company->tax_id), -4);
        $dateCode = now()->format('Ymd');
        $sequence = WebserviceTransaction::where('company_id', $this->company->id)
            ->whereDate('created_at', today())
            ->count() + 1;
        
        $transactionId = sprintf('%s-%s-%03d', $companyCode, $dateCode, $sequence);

        // Obtener URL del webservice según configuración
        $webserviceUrl = $this->getWebserviceUrl();

        $transaction = WebserviceTransaction::create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'shipment_id' => $shipment->id,
            'voyage_id' => $shipment->voyage_id,
            'transaction_id' => $transactionId,
            'webservice_type' => $this->config['webservice_type'],
            'country' => $this->config['country'],
            'webservice_url' => $webserviceUrl,
            'soap_action' => $this->config['soap_action'],
            'status' => 'pending',
            'max_retries' => $this->config['max_retries'],
            'retry_intervals' => $this->config['retry_intervals'],
            'environment' => $this->config['environment'],
            'certificate_used' => $this->company->certificate_alias,
            
            // Datos específicos del shipment para referencia
            'total_weight_kg' => $shipment->cargo_weight_loaded * 1000, // convertir a kg
            'container_count' => $shipment->containers_loaded,
            'currency_code' => 'USD', // Por defecto
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
            
            'additional_metadata' => [
                'shipment_number' => $shipment->shipment_number,
                'voyage_number' => $shipment->voyage?->voyage_number,
                'vessel_name' => $shipment->vessel?->name,
                'vessel_type' => $shipment->vessel?->vesselType?->name,
                'captain_name' => $shipment->captain?->full_name,
                'is_convoy' => $shipment->voyage?->is_convoy ?? false,
            ],
        ]);

        // ✅ GUARDAR TRANSACTION ID PARA LOGS
        $this->currentTransactionId = $transaction->id;
        
        $this->logOperation('info', 'Transacción MIC/DTA creada', [
            'transaction_id' => $transaction->id,
            'internal_transaction_id' => $transactionId,
            'webservice_url' => $webserviceUrl,
        ], 'transaction_management'); // ✅ CATEGORY AGREGADA

        return $transaction;
    }

    /**
     * Generar XML usando el XmlSerializerService
     */
    private function generateXml(Shipment $shipment, string $transactionId): ?string
    {
        try {
            $xmlContent = $this->xmlSerializer->createMicDtaXml($shipment, $transactionId);
            
            if ($xmlContent) {
                $this->logOperation('info', 'XML MIC/DTA generado exitosamente', [
                    'xml_size_kb' => round(strlen($xmlContent) / 1024, 2),
                    'transaction_id' => $transactionId,
                ]);
            }

            return $xmlContent;

        } catch (Exception $e) {
            $this->logOperation('error', 'Error generando XML MIC/DTA', [
                'error' => $e->getMessage(),
                'transaction_id' => $transactionId,
            ]);
            return null;
        }
    }

    /**
     * Preparar cliente SOAP usando SoapClientService
     */
    private function prepareSoapClient()
    {
        try {
            return $this->soapClient->createClient(
                $this->config['webservice_type'],
                $this->config['environment']
            );

        } catch (Exception $e) {
            $this->logOperation('error', 'Error preparando cliente SOAP', [
                'error' => $e->getMessage(),
                'webservice_type' => $this->config['webservice_type'],
                'environment' => $this->config['environment'],
            ]);
            throw $e;
        }
    }

    /**
     * Enviar request SOAP usando SoapClientService con bypass inteligente
     */
    private function sendSoapRequest(WebserviceTransaction $transaction, $soapClient, string $xmlContent): array
    {
        try {
            // ✅ OBTENER CONFIGURACIÓN DE ARGENTINA DESDE COMPANY
            $argentinaData = $this->company->getArgentinaWebserviceData();
            $shouldBypass = $this->company->shouldBypassTesting('argentina');

            $this->logOperation('info', 'Iniciando request SOAP Argentina', [
                'transaction_id' => $transaction->id,
                'cuit_configured' => !empty($argentinaData['cuit']),
                'should_bypass' => $shouldBypass,
                'environment' => $this->config['environment'],
            ]);

            // ✅ BYPASS INTELIGENTE: Verificar si debe simular respuesta
            if ($shouldBypass || $this->config['environment'] === 'testing') {
                
                // Verificar si la configuración es de testing/desarrollo
                $isTestingConfig = $this->company->isTestingConfiguration('argentina', $argentinaData);
                
                if ($isTestingConfig || $shouldBypass) {
                    // Actualizar transacción como bypass
                    $transaction->update(['status' => 'bypass', 'sent_at' => now()]);
                    
                    // Generar respuesta simulada
                    return $this->generateBypassResponse('RegistrarMicDta', $transaction->internal_transaction_id, $argentinaData);
                }
            }

            // ✅ VALIDAR CONFIGURACIÓN ANTES DE CONEXIÓN REAL
            $configErrors = $this->company->validateWebserviceConfig('argentina');
            if (!empty($configErrors)) {
                $this->logOperation('error', 'Configuración Argentina incompleta', [
                    'transaction_id' => $transaction->id,
                    'errors' => $configErrors,
                ]);

                $transaction->update(['status' => 'error']);
                
                return [
                    'success' => false,
                    'error_code' => 'CONFIG_INCOMPLETE',
                    'error_message' => 'Configuración de Argentina incompleta: ' . implode(', ', $configErrors),
                    'can_retry' => false,
                ];
            }

            // ✅ CONEXIÓN REAL A ARGENTINA (solo si bypass no activado y configuración OK)
            
            // Actualizar estado a 'sending'
            $transaction->update(['status' => 'sending', 'sent_at' => now()]);

            // Extraer parámetros del XML para el método SOAP
            $parameters = $this->extractSoapParameters($xmlContent);

            // Enviar usando SoapClientService
            $soapResult = $this->soapClient->sendRequest($transaction, 'RegistrarMicDta', $parameters);

            // Actualizar transacción con XMLs
            $transaction->update([
                'request_xml' => $soapResult['request_xml'] ?? $xmlContent,
                'response_xml' => $soapResult['response_xml'] ?? null,
                'response_time_ms' => $soapResult['response_time_ms'] ?? null,
            ]);

            if ($soapResult['success']) {
                // Parsear respuesta exitosa
                $responseData = $this->parseSuccessResponse($soapResult['response']);
                
                return [
                    'success' => true,
                    'response_data' => $responseData,
                    'response_time_ms' => $soapResult['response_time_ms'],
                ];
            } else {
                return [
                    'success' => false,
                    'error_type' => $soapResult['error_type'],
                    'error_code' => $soapResult['error_code'],
                    'error_message' => $soapResult['error_message'],
                    'response_time_ms' => $soapResult['response_time_ms'],
                ];
            }

        } catch (Exception $e) {
            $this->logOperation('error', 'Error en envío SOAP', [
                'error' => $e->getMessage(),
                'transaction_id' => $transaction->id,
            ]);

            return [
                'success' => false,
                'error_type' => 'general_error',
                'error_code' => 'SOAP_SEND_ERROR',
                'error_message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Extraer parámetros SOAP del XML generado
     */
    private function extractSoapParameters(string $xmlContent): array
    {
        // Para la implementación básica, convertir XML a array de parámetros
        // En una implementación más robusta, se podría usar DOMDocument para parsear
        try {
            $dom = new \DOMDocument();
            $dom->loadXML($xmlContent);

            // Extraer elementos principales
            $registrarMicDta = $dom->getElementsByTagName('RegistrarMicDta')->item(0);
            
            if (!$registrarMicDta) {
                throw new Exception('Estructura XML MIC/DTA inválida');
            }

            // Para SoapClient PHP, necesitamos pasar los parámetros como array
            // El XML se incluirá en el request_xml del transaction
            return [
                'xmlContent' => $xmlContent
            ];

        } catch (Exception $e) {
            $this->logOperation('error', 'Error extrayendo parámetros SOAP', [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Parsear respuesta exitosa del webservice
     */
    private function parseSuccessResponse($soapResponse): array
    {
        try {
            // Estructura esperada según manual AFIP:
            // <RegistrarMicDtaResponse>
            //   <RegistrarMicDtaResult>
            //     <idMicDta>string</idMicDta>
            //     <nroViaje>string</nroViaje>
            //   </RegistrarMicDtaResult>
            // </RegistrarMicDtaResponse>

            $responseData = [
                'micdta_id' => null,
                'voyage_number' => null,
                'success' => false,
            ];

            if (isset($soapResponse->RegistrarMicDtaResult)) {
                $result = $soapResponse->RegistrarMicDtaResult;
                
                $responseData['micdta_id'] = (string)($result->idMicDta ?? '');
                $responseData['voyage_number'] = (string)($result->nroViaje ?? '');
                $responseData['success'] = !empty($responseData['micdta_id']);
            }

            $this->logOperation('info', 'Respuesta MIC/DTA parseada', $responseData);

            return $responseData;

        } catch (Exception $e) {
            $this->logOperation('error', 'Error parseando respuesta', [
                'error' => $e->getMessage(),
            ]);
            
            return [
                'micdta_id' => null,
                'voyage_number' => null,
                'success' => false,
                'parse_error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Procesar respuesta exitosa
     */
    private function processSuccessResponse(WebserviceTransaction $transaction, array $soapResult): void
    {
        $responseData = $soapResult['response_data'];

        // Actualizar transacción
        $transaction->update([
            'status' => 'success',
            'response_at' => now(),
            'confirmation_number' => $responseData['micdta_id'],
            'success_data' => $responseData,
        ]);

        // Crear registro de respuesta estructurada
        WebserviceResponse::create([
            'transaction_id' => $transaction->id,
            'response_type' => 'success',
            'voyage_number' => $responseData['voyage_number'],
            'customs_data' => $responseData,
            'processed_at' => now(),
        ]);

        $this->logOperation('info', 'Respuesta exitosa procesada', [
            'transaction_id' => $transaction->id,
            'micdta_id' => $responseData['micdta_id'],
            'voyage_number' => $responseData['voyage_number'],
        ]);
    }

    /**
     * Procesar respuesta con error
     */
    private function processErrorResponse(WebserviceTransaction $transaction, array $soapResult): void
    {
        $transaction->update([
            'status' => 'error',
            'response_at' => now(),
            'error_code' => $soapResult['error_code'],
            'error_message' => $soapResult['error_message'],
            'error_details' => [
                'error_type' => $soapResult['error_type'],
                'response_time_ms' => $soapResult['response_time_ms'],
            ],
        ]);

        $this->logOperation('error', 'Error en respuesta MIC/DTA', [
            'transaction_id' => $transaction->id,
            'error_code' => $soapResult['error_code'],
            'error_message' => $soapResult['error_message'],
            'error_type' => $soapResult['error_type'],
        ]);
    }

    /**
     * Obtener URL del webservice según configuración
     */
    private function getWebserviceUrl(): string
    {
        // Usar configuración del company si existe, sino usar defaults del SoapClientService
        $customUrls = $this->company->ws_config['webservice_urls'][$this->config['environment']] ?? null;
        
        if ($customUrls && isset($customUrls[$this->config['webservice_type']])) {
            return $customUrls[$this->config['webservice_type']];
        }

        // URLs por defecto del SoapClientService
        $defaultUrls = [
            'testing' => 'https://wsaduhomoext.afip.gob.ar/DIAV2/wgesregsintia2/wgesregsintia2.asmx',
            'production' => 'https://webservicesadu.afip.gob.ar/DIAV2/wgesregsintia2/wgesregsintia2.asmx',
        ];

        return $defaultUrls[$this->config['environment']] ?? $defaultUrls['testing'];
    }

    /**
     * Obtener estadísticas de transacciones MIC/DTA de la empresa
     */
    public function getCompanyStatistics(): array
    {
        $stats = [
            'total_transactions' => 0,
            'successful_transactions' => 0,
            'error_transactions' => 0,
            'pending_transactions' => 0,
            'success_rate' => 0.0,
            'average_response_time_ms' => 0,
            'last_successful_transaction' => null,
            'most_common_errors' => [],
        ];

        try {
            $transactions = WebserviceTransaction::forCompany($this->company->id)
                ->ofType('micdta')
                ->forCountry('AR');

            $stats['total_transactions'] = $transactions->count();
            $stats['successful_transactions'] = $transactions->where('status', 'success')->count();
            $stats['error_transactions'] = $transactions->whereIn('status', ['error', 'expired'])->count();
            $stats['pending_transactions'] = $transactions->whereIn('status', ['pending', 'sending', 'retry'])->count();

            if ($stats['total_transactions'] > 0) {
                $stats['success_rate'] = round(($stats['successful_transactions'] / $stats['total_transactions']) * 100, 2);
            }

            // Tiempo promedio de respuesta
            $avgTime = $transactions->whereNotNull('response_time_ms')->avg('response_time_ms');
            $stats['average_response_time_ms'] = $avgTime ? round($avgTime) : 0;

            // Última transacción exitosa
            $lastSuccess = $transactions->where('status', 'success')->latest()->first();
            $stats['last_successful_transaction'] = $lastSuccess?->created_at;

            return $stats;

        } catch (Exception $e) {
            $this->logOperation('error', 'Error obteniendo estadísticas', [
                'error' => $e->getMessage(),
            ]);
            return $stats;
        }
    }

   /**
     * Método de logging con category - AGREGADO
     */
    protected function logOperation(string $level, string $message, array $context = [], string $category = 'micdta_operation'): void
    {
        try {
            $context['service'] = 'ArgentinaMicDtaService';
            $context['company_id'] = $this->company->id;
            $context['company_name'] = $this->company->legal_name ?? $this->company->name;
            $context['user_id'] = $this->user->id;
            $context['timestamp'] = now()->toISOString();

            Log::$level($message, $context);

            // Log a webservice_logs si hay transaction_id
            if ($this->currentTransactionId) {
                \App\Models\WebserviceLog::create([
                    'transaction_id' => $this->currentTransactionId,
                    'level' => $level,
                    'category' => $category, // ✅ CAMPO REQUERIDO
                    'message' => $message,
                    'context' => $context,
                    'created_at' => now(),
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Error logging to webservice_logs table', [
                'original_message' => $message,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Obtener configuración actual del servicio
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * Cambiar ambiente (testing/production)
     */
    public function setEnvironment(string $environment): void
    {
        if (!in_array($environment, ['testing', 'production'])) {
            throw new Exception("Ambiente inválido: {$environment}");
        }

        $this->config['environment'] = $environment;
        
        $this->logOperation('info', 'Ambiente cambiado', [
            'new_environment' => $environment,
        ]);
    }

    /**
 * Log para inicialización (sin transaction_id) - MÉTODO NUEVO
 */
protected function logInitialization(string $level, string $message, array $context = []): void
{
    $context['service'] = 'ArgentinaMicDtaService';
    $context['company_id'] = $this->company->id;
    $context['company_name'] = $this->company->legal_name ?? $this->company->name;
    $context['user_id'] = $this->user->id;
    $context['timestamp'] = now()->toISOString();
    
    // Solo log a Laravel hasta que se cree la transacción
    Log::$level($message, $context);
}

/**
 * Generar respuesta simulada (bypass) para Argentina MIC/DTA
 */
private function generateBypassResponse(string $operation, string $transactionId, array $argentinaData): array
{
    $this->logOperation('info', 'BYPASS: Simulando respuesta Argentina MIC/DTA', [
        'operation' => $operation,
        'transaction_id' => $transactionId,
        'reason' => 'Configuración de testing o bypass activado',
        'cuit_used' => $argentinaData['cuit'] ?? 'no-configurado',
    ]);

    // Generar referencias realistas Argentina
    $argentinaReference = $this->generateRealisticArgentinaReference();
    $micNumber = 'MIC' . date('Y') . str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
    $voyageNumber = 'VYG' . date('y') . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);

    switch ($operation) {
        case 'RegistrarMicDta':
        case 'enviarMicDta':
            return [
                'success' => true,
                'response_data' => [
                    'micdta_id' => $argentinaReference,
                    'voyage_number' => $voyageNumber,
                    'success' => true,
                ],
                'status' => 'BYPASS_SUCCESS',
                'bypass_mode' => true,
                'processed_at' => now(),
                'status_message' => 'MIC/DTA registrado exitosamente en AFIP (SIMULADO)',
            ];

        case 'consultarEstado':
        case 'ConsultarEstadoMicDta':
            return [
                'success' => true,
                'status' => 'PRESENTADO',
                'status_description' => 'MIC/DTA presentado y en proceso por AFIP (SIMULADO)',
                'micdta_id' => $argentinaReference,
                'last_update' => now(),
                'bypass_mode' => true,
            ];

        case 'anularMicDta':
        case 'AnularMicDta':
            return [
                'success' => true,
                'status' => 'ANULADO',
                'status_description' => 'MIC/DTA anulado exitosamente en AFIP (SIMULADO)',
                'micdta_id' => $argentinaReference,
                'cancelled_at' => now(),
                'bypass_mode' => true,
            ];

        default:
            return [
                'success' => true,
                'status' => 'BYPASS_SUCCESS',
                'message' => "Operación Argentina {$operation} simulada exitosamente",
                'bypass_mode' => true,
            ];
    }
}

/**
 * Generar referencia Argentina AFIP realista para testing
 */
private function generateRealisticArgentinaReference(): string
{
    $year = date('Y');
    $office = '010'; // Código típico aduana Buenos Aires
    $sequence = str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
    $checkDigit = substr(md5($year . $office . $sequence), -1);
    
    return 'ARG' . $year . $office . 'MIC' . $sequence . strtoupper($checkDigit);
}

}