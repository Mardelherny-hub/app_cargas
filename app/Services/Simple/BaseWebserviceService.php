<?php

namespace App\Services\Simple;

use App\Models\Company;
use App\Models\Voyage;
use App\Models\User;
use App\Models\VoyageWebserviceStatus;
use App\Models\WebserviceTransaction;
use App\Models\WebserviceLog;
use App\Models\WebserviceResponse;
use SoapClient;
use SoapFault;
use App\Services\Webservice\CertificateManagerService;
use App\Services\Simple\SimpleXmlGenerator;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Illuminate\Support\Str;

/**
 * SISTEMA MODULAR WEBSERVICES - BaseWebserviceService
 * 
 * Clase base que contiene funcionalidad común para todos los webservices aduaneros.
 * Proporciona una base sólida y reutilizable para Argentina y Paraguay.
 * 
 * FUNCIONALIDADES COMUNES:
 * - Validación de datos voyage/shipments
 * - Gestión de VoyageWebserviceStatus modular
 * - Creación y manejo de WebserviceTransaction
 * - Sistema de logging unificado (WebserviceLog)
 * - Integración con servicios existentes (Certificate, Soap, Xml)
 * - Manejo de errores y reintentos
 * - Auditoría completa de operaciones
 * 
 * DISEÑO MODULAR:
 * - Métodos abstractos para implementación específica
 * - Hooks para extensibilidad
 * - Configuración flexible por webservice
 * - Compatible con datos existentes del sistema
 */
abstract class BaseWebserviceService
{
    protected Company $company;
    protected User $user;
    protected array $config;
    protected ?int $currentTransactionId = null;
    
    // Servicios integrados directos
    protected CertificateManagerService $certificateManager;
    protected ?SoapClient $soapClient = null;
    protected SimpleXmlGenerator $xmlSerializer;

    /**
     * Configuración base común a todos los webservices
     */
    protected const BASE_CONFIG = [
        'timeout_seconds' => 60,
        'max_retries' => 3,
        'retry_intervals' => [30, 120, 300], // segundos
        'require_certificate' => true,
        'validate_xml_structure' => true,
        'log_requests' => true,
        'log_responses' => true,
        'currency_code' => 'USD',
    ];

    /**
     * Estados válidos para webservices
     */
    protected const VALID_STATUSES = [
        'not_required', 'pending', 'validating', 'sending', 
        'sent', 'approved', 'rejected', 'error', 'retry', 
        'cancelled', 'expired'
    ];

    /**
     * Países soportados
     */
    protected const SUPPORTED_COUNTRIES = ['AR', 'PY'];

    public function __construct(Company $company, User $user, array $config = [])
    {
        $this->company = $company;
        $this->user = $user;
        $this->config = array_merge(self::BASE_CONFIG, $this->getWebserviceConfig(), $config);

        // Inicializar servicios directos
            $this->certificateManager = new CertificateManagerService($company);  
        $this->xmlSerializer = new SimpleXmlGenerator($company, $this->config);
        Log::info('Merged config in BaseWebserviceService: ', $this->config);

        $this->logOperation('info', 'BaseWebserviceService inicializado', [
            'webservice_type' => $this->getWebserviceType(),
            'country' => $this->getCountry(),
            'company_id' => $company->id,
            'user_id' => $user->id,
        ]);
    }

    /**
     * Crear cliente SOAP nativo
     */
    protected function createSoapClient(): SoapClient
    {
        if ($this->soapClient) {
            return $this->soapClient;
        }

        $wsdlUrl = $this->getWsdlUrl();
        
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ]);

        $this->soapClient = new SoapClient($wsdlUrl, [
            'trace' => true,
            'exceptions' => true,
            'soap_version' => SOAP_1_2,
            'stream_context' => $context,
            'connection_timeout' => $this->config['timeout_seconds'] ?? 60,
        ]);

        return $this->soapClient;
    }

    // ====================================
    // MÉTODOS ABSTRACTOS - IMPLEMENTAR EN CLASES HIJAS
    // ====================================

    
    /**
     * Tipo de webservice específico (ej: 'micdta', 'anticipada', 'manifiesto')
     */
    abstract protected function getWebserviceType(): string;

    /**
     * País del webservice ('AR' o 'PY')
     */
    abstract protected function getCountry(): string;

    /**
     * Configuración específica del webservice
     */
    abstract protected function getWebserviceConfig(): array;

    /**
     * Validación específica de datos para el webservice
     */
    abstract protected function validateSpecificData(Voyage $voyage): array;

    /**
     * Envío específico del webservice
     */
    abstract protected function sendSpecificWebservice(Voyage $voyage, array $options = []): array;

    /**
     * URL del WSDL específico del webservice
     */
    abstract protected function getWsdlUrl(): string;


    // ====================================
    // MÉTODOS PÚBLICOS PRINCIPALES
    // ====================================

    /**
     * Validar si un voyage puede usar este webservice
     */
    public function canProcessVoyage(Voyage $voyage): array
    {
        $this->logOperation('info', 'Iniciando validación de voyage', [
            'voyage_id' => $voyage->id,
            'voyage_number' => $voyage->voyage_number,
        ]);

        $validation = [
            'can_process' => false,
            'errors' => [],
            'warnings' => [],
            'requirements_met' => [],
        ];

        try {
            // 1. Validaciones básicas comunes
            $baseValidation = $this->validateBaseData($voyage);
            $validation['errors'] = array_merge($validation['errors'], $baseValidation['errors']);
            $validation['warnings'] = array_merge($validation['warnings'], $baseValidation['warnings']);

            // 2. Validaciones específicas del webservice
            $specificValidation = $this->validateSpecificData($voyage);
            $validation['errors'] = array_merge($validation['errors'], $specificValidation['errors']);
            $validation['warnings'] = array_merge($validation['warnings'], $specificValidation['warnings']);

            // 3. Validar certificado de la empresa
            $certificateValidation = $this->certificateManager->validateCompanyCertificate();
            if (!$certificateValidation['is_valid']) {
                $validation['errors'][] = 'Certificado digital no válido: ' . implode(', ', $certificateValidation['errors']);
            }

            // 4. Determinar si puede procesar
            $validation['can_process'] = empty($validation['errors']);

            $this->logOperation(
                $validation['can_process'] ? 'info' : 'warning',
                'Validación de voyage completada',
                [
                    'can_process' => $validation['can_process'],
                    'errors_count' => count($validation['errors']),
                    'warnings_count' => count($validation['warnings']),
                ]
            );

            return $validation;

        } catch (Exception $e) {
            $validation['errors'][] = 'Error interno en validación: ' . $e->getMessage();
            
            $this->logOperation('error', 'Error en validación de voyage', [
                'error' => $e->getMessage(),
                'voyage_id' => $voyage->id,
            ]);

            return $validation;
        }
    }

    /**
     * Obtener o crear estado de webservice para el voyage
     */
    public function getWebserviceStatus(Voyage $voyage): VoyageWebserviceStatus
    {
        return VoyageWebserviceStatus::firstOrCreate(
            [
                'company_id' => $this->company->id,
                'voyage_id' => $voyage->id,
                'country' => $this->getCountry(),
                'webservice_type' => $this->getWebserviceType(),
            ],
            [
                'user_id' => $this->user->id,
                'status' => 'pending',
                'can_send' => false,
                'is_required' => true,
                'retry_count' => 0,
                'max_retries' => $this->config['max_retries'],
            ]
        );
    }

    /**
     * Enviar webservice (método principal público)
     */
    public function sendWebservice(Voyage $voyage, array $options = []): array
    {
        $this->logOperation('info', 'Iniciando envío de webservice', [
            'voyage_id' => $voyage->id,
            'webservice_type' => $this->getWebserviceType(),
            'options' => $options,
        ]);

        DB::beginTransaction();
        
        try {
            // 1. Validar que se puede procesar
            $validation = $this->canProcessVoyage($voyage);
            if (!$validation['can_process']) {
                throw new Exception('Voyage no válido para webservice: ' . implode(', ', $validation['errors']));
            }

            // 2. Obtener/crear estado de webservice
            $status = $this->getWebserviceStatus($voyage);
            $status->update(['status' => 'validating']);

            // 3. Crear transacción
            $transaction = $this->createTransaction($voyage, $options);
            $this->currentTransactionId = $transaction->id;

            // 4. Enviar webservice específico
            $result = $this->sendSpecificWebservice($voyage, $options);

            // 5. Procesar resultado
            if ($result['success']) {
                $status->update([
                    'status' => 'sent',
                    'last_transaction_id' => $transaction->transaction_id,
                    'last_sent_at' => now(),
                    'retry_count' => 0,
                ]);

                $transaction->update(['status' => 'sent']);
                
                $this->logOperation('info', 'Webservice enviado exitosamente', [
                    'transaction_id' => $transaction->transaction_id,
                    'voyage_id' => $voyage->id,
                ]);
            } else {
                $status->update([
                    'status' => 'error',
                    'last_error_message' => $result['error_message'] ?? 'Error desconocido',
                ]);

                $transaction->update([
                    'status' => 'error',
                    'error_message' => $result['error_message'] ?? 'Error desconocido',
                ]);
            }

            DB::commit();
            return $result;

        } catch (Exception $e) {
            DB::rollBack();
            
            $this->logOperation('error', 'Error enviando webservice', [
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

    // ====================================
    // MÉTODOS PROTEGIDOS PARA CLASES HIJAS
    // ====================================

    /**
     * Validaciones básicas comunes a todos los webservices
     */
    protected function validateBaseData(Voyage $voyage): array
    {
        $validation = ['errors' => [], 'warnings' => []];

        // Verificar voyage básico
        if (!$voyage->voyage_number) {
            $validation['errors'][] = 'Voyage sin número de viaje';
        }

        if (!$voyage->lead_vessel_id) {
            $validation['errors'][] = 'Voyage sin embarcación líder asignada';
        }

        if (!$voyage->origin_port_id || !$voyage->destination_port_id) {
            $validation['errors'][] = 'Voyage sin puertos de origen/destino';
        }

        // Verificar shipments
        $shipmentsCount = $voyage->shipments()->count();
        if ($shipmentsCount === 0) {
            $validation['errors'][] = 'Voyage sin shipments asociados';
        }

        // Verificar bills of lading
        $bolCount = $voyage->billsOfLading()->count();
        if ($bolCount === 0) {
            $validation['errors'][] = 'Voyage sin conocimientos de embarque';
        }

        return $validation;
    }

    /**
     * Crear transacción de webservice
     */
    protected function createTransaction(Voyage $voyage, array $options = []): WebserviceTransaction
    {
        $transactionId = $this->generateTransactionId();

        return WebserviceTransaction::create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'voyage_id' => $voyage->id,
            'transaction_id' => $transactionId,
            'webservice_type' => $this->getWebserviceType(),
            'country' => $this->getCountry(),
            'webservice_url' => $this->config['webservice_url'] ?? null,
            'soap_action' => $this->config['soap_action'] ?? null,
            'status' => 'pending',
            'retry_count' => 0,
            'max_retries' => $this->config['max_retries'],
            'currency_code' => $this->config['currency_code'],
            'environment' => $this->config['environment'] ?? 'testing',
            'certificate_used' => $this->company->certificate_path,
            'additional_metadata' => $options,
        ]);
    }

    /**
     * Logging unificado para webservices
     */
    protected function logOperation(string $level, string $message, array $context = []): void
    {
        // Log en sistema Laravel
        Log::info("WebserviceSimple [{$this->getWebserviceType()}]: {$message}", $context);

        // Log en base de datos si hay transacción activa
        if ($this->currentTransactionId) {
            WebserviceLog::create([
                'transaction_id' => $this->currentTransactionId,
                'user_id' => $this->user->id,
                'level' => $level,
                'message' => $message,
                'category' => 'webservice_operation',
                'context' => $context,
                'environment' => $this->config['environment'] ?? 'testing',
            ]);
        }
    }

    /**
     * Generar ID único para transacción
     */
    protected function generateTransactionId(): string
    {
        return strtoupper(
            $this->getCountry() . '_' . 
            $this->getWebserviceType() . '_' . 
            date('YmdHis') . '_' . 
            Str::random(6)
        );
    }

    /**
     * Actualizar estado de webservice
     */
    protected function updateWebserviceStatus(Voyage $voyage, string $status, array $data = []): void
    {
        $webserviceStatus = $this->getWebserviceStatus($voyage);
        
        $updateData = array_merge(['status' => $status], $data);
        
        if (in_array($status, self::VALID_STATUSES)) {
            $webserviceStatus->update($updateData);
            
            $this->logOperation('info', 'Estado de webservice actualizado', [
                'voyage_id' => $voyage->id,
                'new_status' => $status,
                'update_data' => $data,
            ]);
        }
    }
}