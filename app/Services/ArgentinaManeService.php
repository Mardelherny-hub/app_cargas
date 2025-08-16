<?php

namespace App\Services\Webservice;

use App\Models\Company;
use App\Models\Voyage;
use App\Models\User;
use App\Models\WebserviceTransaction;
use App\Models\WebserviceResponse;
use App\Models\WebserviceLog;
use App\Services\FileGeneration\ManeFileGeneratorService;
use App\Services\Webservice\SoapClientService;
use App\Services\Webservice\CertificateManagerService;
use App\Services\Webservice\XmlSerializerService;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * MÓDULO 5: WEBSERVICES ADUANA - ArgentinaManeService
 *
 * Servicio integrador para MANE (Sistema Malvina) Argentina AFIP.
 * NOTA: Actualmente genera archivos para Malvina. Cuando el webservice esté disponible,
 * se adaptará para envío directo SOAP.
 * 
 * Integra:
 * - ManeFileGeneratorService: Para generar datos estructurados/archivos
 * - SoapClientService: Cliente SOAP base (preparado para futuro)
 * - CertificateManagerService: Gestión certificados .p12
 * - XmlSerializerService: Generación XML (preparado para futuro)
 * 
 * Funcionalidades actuales:
 * - Generación de archivos MANE para sistema Malvina
 * - Registro de transacciones como si fuera webservice
 * - Sistema completo de logs y auditoría
 * 
 * Funcionalidades futuras (cuando webservice esté disponible):
 * - Envío SOAP directo al webservice MANE
 * - Procesamiento de respuestas XML
 * - Reintentos automáticos
 */
class ArgentinaManeService
{
    private Company $company;
    private User $user;
    private array $config;
    
    // Servicios integrados
    private ManeFileGeneratorService $maneGenerator;
    private SoapClientService $soapClient;
    private CertificateManagerService $certificateManager;
    private XmlSerializerService $xmlSerializer;
    
    // Para tracking
    private ?int $currentTransactionId = null;

    /**
     * Constructor con inyección de dependencias
     */
    public function __construct(Company $company, User $user, array $config = [])
    {
        $this->company = $company;
        $this->user = $user;
        
        // Configuración por defecto
        $this->config = array_merge([
            'webservice_type' => 'mane',
            'environment' => config('app.env') === 'production' ? 'production' : 'testing',
            'timeout' => 60,
            'retry_attempts' => 3,
            'validate_before_send' => true,
            'log_level' => 'info',
            'generate_file' => true,  // Por ahora siempre genera archivo
            'use_webservice' => false, // Cuando esté disponible, cambiar a true
        ], $config);

        // Inicializar servicios
        $this->initializeServices();

        $this->logOperation('info', 'ArgentinaManeService inicializado', [
            'company_id' => $company->id,
            'company_name' => $company->legal_name,
            'id_maria' => $company->id_maria,
            'environment' => $this->config['environment'],
        ]);
    }

    /**
     * Inicializar servicios integrados
     */
    private function initializeServices(): void
    {
        // Generador de archivos MANE
        $this->maneGenerator = new ManeFileGeneratorService($this->company);
        
        // Cliente SOAP (preparado para cuando esté el webservice)
        $this->soapClient = new SoapClientService($this->company, $this->config);
        
        // Gestor de certificados
        $this->certificateManager = new CertificateManagerService($this->company);
        
        // Serializador XML (preparado para cuando esté el webservice)
        $this->xmlSerializer = new XmlSerializerService($this->company, $this->config);
    }

    /**
     * Enviar MANE para un viaje
     * Por ahora genera archivo, en el futuro enviará por webservice
     */
    public function sendMane(Voyage $voyage): array
    {
        DB::beginTransaction();
        
        try {
            // 1. Validaciones
            $validation = $this->validateVoyageForMane($voyage);
            if (!$validation['is_valid']) {
                return $this->buildErrorResponse('VALIDATION_ERROR', 
                    'Viaje no válido para MANE', $validation['errors']);
            }

            // 2. Crear transacción
            $transactionId = $this->generateTransactionId('MANE');
            $transaction = $this->createTransaction([
                'voyage_id' => $voyage->id,
                'webservice_type' => 'mane',
                'country' => 'AR',
                'transaction_id' => $transactionId,
                'user_id' => $this->user->id,
                'status' => 'pending',
            ]);

            // 3. Decidir si usar webservice o generar archivo
            if ($this->config['use_webservice'] && $this->isWebserviceAvailable()) {
                // FUTURO: Enviar por webservice cuando esté disponible
                $response = $this->sendViaWebservice($voyage, $transaction, $transactionId);
            } else {
                // ACTUAL: Generar archivo para Malvina
                $response = $this->generateManeFile($voyage, $transaction, $transactionId);
            }

            // 4. Actualizar transacción con respuesta
            $this->updateTransactionWithResponse($transaction, $response);

            DB::commit();

            return $response;

        } catch (Exception $e) {
            DB::rollBack();
            
            $this->logOperation('error', 'Error enviando MANE', [
                'error' => $e->getMessage(),
                'voyage_id' => $voyage->id,
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->buildErrorResponse('SYSTEM_ERROR', 
                'Error del sistema: ' . $e->getMessage());
        }
    }

    /**
     * Generar archivo MANE (método actual mientras no hay webservice)
     */
    private function generateManeFile(Voyage $voyage, WebserviceTransaction $transaction, string $transactionId): array
    {
        try {
            $this->logOperation('info', 'Generando archivo MANE', [
                'voyage_id' => $voyage->id,
                'transaction_id' => $transactionId,
            ]);

            // Generar archivo usando ManeFileGeneratorService
            $filepath = $this->maneGenerator->generateForVoyage($voyage);
            
            // Leer contenido del archivo para guardarlo en la transacción
            $fileContent = \Storage::get($filepath);
            
            // Actualizar transacción con el "request" (archivo generado)
            $transaction->update([
                'request_xml' => $fileContent,  // Guardamos el contenido del archivo
                'additional_metadata' => [
                    'file_path' => $filepath,
                    'file_size' => strlen($fileContent),
                    'file_type' => 'text/plain',
                    'id_maria' => $this->company->id_maria,
                ],
            ]);

            $this->logOperation('info', 'Archivo MANE generado exitosamente', [
                'filepath' => $filepath,
                'size' => strlen($fileContent),
                'transaction_id' => $transactionId,
            ]);

            return [
                'success' => true,
                'transaction_id' => $transactionId,
                'file_path' => $filepath,
                'file_size' => strlen($fileContent),
                'message' => 'Archivo MANE generado exitosamente para sistema Malvina',
                'download_url' => route('company.mane.download', ['filename' => basename($filepath)]),
            ];

        } catch (Exception $e) {
            $this->logOperation('error', 'Error generando archivo MANE', [
                'error' => $e->getMessage(),
                'voyage_id' => $voyage->id,
            ]);

            return [
                'success' => false,
                'error_code' => 'FILE_GENERATION_ERROR',
                'error_message' => 'Error generando archivo: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * FUTURO: Enviar por webservice cuando esté disponible
     */
    private function sendViaWebservice(Voyage $voyage, WebserviceTransaction $transaction, string $transactionId): array
    {
        try {
            $this->logOperation('info', 'Preparando envío MANE por webservice', [
                'voyage_id' => $voyage->id,
                'transaction_id' => $transactionId,
            ]);

            // 1. Preparar datos estructurados
            $xmlData = $this->maneGenerator->prepareXmlData($voyage, $transactionId);
            
            // 2. Generar XML usando XmlSerializerService
            // NOTA: Cuando tengamos la especificación real, crear el método createManeXml()
            $xmlContent = $this->xmlSerializer->createManeXml($xmlData);
            
            if (!$xmlContent) {
                throw new Exception('Error generando XML MANE');
            }

            // 3. Actualizar transacción con el XML request
            $transaction->update(['request_xml' => $xmlContent]);

            // 4. Preparar cliente SOAP
            $soapClient = $this->soapClient->createClient(
                'mane',  // Tipo de webservice
                $this->config['environment']
            );

            // 5. Enviar por SOAP
            $soapResult = $this->soapClient->sendRequest(
                $transaction,
                'RegistrarMane',  // Método SOAP (ajustar cuando tengamos la especificación)
                ['xmlData' => $xmlContent]
            );

            // 6. Procesar respuesta
            if ($soapResult['success']) {
                return [
                    'success' => true,
                    'transaction_id' => $transactionId,
                    'external_reference' => $soapResult['response']->referencia ?? null,
                    'message' => 'MANE enviado exitosamente al webservice',
                ];
            } else {
                return [
                    'success' => false,
                    'error_code' => $soapResult['error_code'],
                    'error_message' => $soapResult['error_message'],
                ];
            }

        } catch (Exception $e) {
            $this->logOperation('error', 'Error enviando MANE por webservice', [
                'error' => $e->getMessage(),
                'voyage_id' => $voyage->id,
            ]);

            return [
                'success' => false,
                'error_code' => 'WEBSERVICE_ERROR',
                'error_message' => 'Error en webservice: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Validar viaje para MANE
     */
    private function validateVoyageForMane(Voyage $voyage): array
    {
        $validation = [
            'is_valid' => false,
            'errors' => [],
            'warnings' => [],
        ];

        // Validar que la empresa tiene ID María
        if (empty($this->company->id_maria)) {
            $validation['errors'][] = 'La empresa debe tener ID María configurado para MANE';
        }

        // Validar que la empresa tiene rol Cargas
        if (!$this->company->hasRole('Cargas')) {
            $validation['errors'][] = 'La empresa debe tener el rol "Cargas" para usar MANE';
        }

        // Validar que el viaje tiene shipments
        if ($voyage->shipments->isEmpty()) {
            $validation['errors'][] = 'El viaje debe tener al menos un shipment';
        }

        // Validar que el viaje pertenece a la empresa
        if ($voyage->company_id !== $this->company->id) {
            $validation['errors'][] = 'El viaje no pertenece a la empresa actual';
        }

        // Validar estado del viaje
        if (!in_array($voyage->status, ['completed', 'in_progress', 'approved'])) {
            $validation['warnings'][] = 'El viaje no está en estado óptimo para envío';
        }

        $validation['is_valid'] = empty($validation['errors']);

        return $validation;
    }

    /**
     * Verificar si el webservice MANE está disponible
     */
    private function isWebserviceAvailable(): bool
    {
        // Por ahora retorna false porque el webservice no está disponible
        // Cuando esté disponible, hacer un health check real
        return false;
    }

    /**
     * Generar ID de transacción único
     */
    private function generateTransactionId(string $prefix): string
    {
        return sprintf(
            '%s-%s-%s-%s',
            $prefix,
            $this->company->id,
            date('YmdHis'),
            uniqid()
        );
    }

    /**
     * Crear transacción en base de datos
     */
    private function createTransaction(array $data): WebserviceTransaction
    {
        $webserviceUrl = $this->config['use_webservice'] 
            ? $this->getWebserviceUrl() 
            : 'file://local/mane_export';

        $transaction = WebserviceTransaction::create([
            'company_id' => $this->company->id,
            'voyage_id' => $data['voyage_id'],
            'shipment_id' => null,
            'webservice_type' => $data['webservice_type'],
            'webservice_url' => $webserviceUrl,
            'transaction_id' => $data['transaction_id'],
            'environment' => $this->config['environment'],
            'country' => $data['country'],
            'status' => $data['status'],
            'user_id' => $data['user_id'],
            'sent_at' => now(),
            'request_xml' => null,
            'response_xml' => null,
            'external_reference' => null,
            'error_message' => null,
            'error_code' => null,
            'response_time_ms' => null,
            'retry_count' => 0,
            'max_retries' => $this->config['retry_attempts'],
            'is_test_mode' => $this->config['environment'] === 'testing',
        ]);

        $this->currentTransactionId = $transaction->id;
        
        return $transaction;
    }

    /**
     * Obtener URL del webservice MANE
     */
    private function getWebserviceUrl(): string
    {
        // URLs hipotéticas para cuando esté disponible el webservice
        $urls = [
            'testing' => 'https://wsaduhomoext.afip.gob.ar/MANE/wsmane/wsmane.asmx',
            'production' => 'https://wsadu.afip.gob.ar/MANE/wsmane/wsmane.asmx',
        ];

        return $urls[$this->config['environment']] ?? $urls['testing'];
    }

    /**
     * Actualizar transacción con respuesta
     */
    private function updateTransactionWithResponse(WebserviceTransaction $transaction, array $response): void
    {
        $updateData = [
            'response_time_ms' => round((microtime(true) - $transaction->sent_at->timestamp) * 1000),
        ];

        if ($response['success']) {
            $updateData['status'] = 'success';
            $updateData['external_reference'] = $response['external_reference'] ?? null;
            
            // Si es archivo, guardar metadata
            if (isset($response['file_path'])) {
                $updateData['additional_metadata'] = array_merge(
                    $transaction->additional_metadata ?? [],
                    [
                        'file_path' => $response['file_path'],
                        'file_size' => $response['file_size'],
                        'download_url' => $response['download_url'] ?? null,
                    ]
                );
            }
        } else {
            $updateData['status'] = 'error';
            $updateData['error_message'] = $response['error_message'] ?? 'Error desconocido';
            $updateData['error_code'] = $response['error_code'] ?? 'UNKNOWN_ERROR';
        }

        $transaction->update($updateData);
    }

    /**
     * Construir respuesta de error
     */
    private function buildErrorResponse(string $code, string $message, array $errors = []): array
    {
        return [
            'success' => false,
            'error_code' => $code,
            'error_message' => $message,
            'errors' => $errors,
            'can_retry' => in_array($code, ['TIMEOUT', 'CONNECTION_ERROR']),
        ];
    }

    /**
     * Log de operaciones
     */
    private function logOperation(string $level, string $message, array $context = [], string $category = 'general'): void
    {
        $logData = array_merge([
            'service' => 'ArgentinaManeService',
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'environment' => $this->config['environment'],
            'category' => $category,
            'transaction_id' => $this->currentTransactionId,
        ], $context);

        // Log en archivo
        Log::$level("[MANE] {$message}", $logData);

        // Log en base de datos si hay transacción activa
        if ($this->currentTransactionId) {
            WebserviceLog::create([
                'webservice_transaction_id' => $this->currentTransactionId,
                'level' => $level,
                'category' => $category,
                'message' => $message,
                'context' => $context,
                'logged_at' => now(),
            ]);
        }
    }

    /**
     * Obtener información del servicio
     */
    public function getServiceInfo(): array
    {
        return [
            'service_name' => 'Argentina MANE Service',
            'company' => $this->company->legal_name,
            'id_maria' => $this->company->id_maria,
            'environment' => $this->config['environment'],
            'mode' => $this->config['use_webservice'] ? 'webservice' : 'file_generation',
            'webservice_available' => $this->isWebserviceAvailable(),
            'supported_operations' => [
                'send_mane',
                'generate_file',
                'validate_voyage',
            ],
        ];
    }
}