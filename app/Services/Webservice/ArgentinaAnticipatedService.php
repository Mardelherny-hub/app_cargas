<?php

namespace App\Services\Webservice;

use App\Models\Company;
use App\Models\Voyage;
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
 * MÓDULO 4: WEBSERVICES ADUANA - ArgentinaAnticipatedService
 *
 * Servicio integrador completo para Información Anticipada Argentina AFIP.
 * Implementa el webservice wgesinformacionanticipada para registro de viajes.
 * 
 * Integra:
 * - SoapClientService: Cliente SOAP con URLs reales Argentina
 * - CertificateManagerService: Gestión certificados .p12
 * - XmlSerializerService: Generación XML según especificación AFIP
 * 
 * Funcionalidades:
 * - Registro de viajes con información anticipada (RegistrarViaje)
 * - Rectificación de viajes ya registrados (RectificarViaje)
 * - Validación completa pre-envío usando datos reales del sistema
 * - Generación de XML con datos de PARANA (MAERSK, PAR13001, V022NB)
 * - Envío SOAP al webservice Argentina con autenticación
 * - Procesamiento de respuestas y actualización de estados
 * - Sistema completo de logs y auditoría de transacciones
 * - Manejo de errores y reintentos automáticos
 * 
 * Datos reales soportados:
 * - Empresas: MAERSK LINE ARGENTINA S.A. (CUIT, certificados .p12)
 * - Embarcaciones: PAR13001, GUARAN F, REINA DEL PARANA
 * - Viajes: V022NB, V023NB con rutas ARBUE → PYTVT  
 * - Shipments: Múltiples envíos por viaje con contenedores 40HC, 20GP
 * - Capitanes: Asignados del sistema con licencias válidas
 */
class ArgentinaAnticipatedService
{
    private Company $company;
    private User $user;
    private SoapClientService $soapClient;
    private CertificateManagerService $certificateManager;
    private XmlSerializerService $xmlSerializer;
    private array $config;

    /**
     * Configuración específica para Información Anticipada Argentina
     */
    private const ANTICIPATED_CONFIG = [
        'webservice_type' => 'anticipada',
        'country' => 'AR',
        'soap_action' => 'Ar.Gob.Afip.Dga.Org.wgesinformacionanticipada/RegistrarViaje',
        'environment' => 'testing', // Por defecto testing, se puede cambiar
        'max_retries' => 3,
        'retry_intervals' => [30, 120, 300], // 30s, 2min, 5min
        'timeout_seconds' => 60,
        'require_certificate' => true,
        'validate_xml_structure' => true,
        'methods' => ['RegistrarViaje', 'RectificarViaje', 'RegistrarTitulosCbc'],
    ];

    public function __construct(Company $company, User $user, array $config = [])
{
    try {
        Log::info('CONSTRUCTOR ArgentinaAnticipatedService - INICIO', [
            'company_id' => $company->id,
            'user_id' => $user->id,
        ]);

        $this->company = $company;
        $this->user = $user;
        $this->config = array_merge(self::ANTICIPATED_CONFIG, $config);

        Log::info('CONSTRUCTOR - Creando SoapClientService');
        $this->soapClient = new SoapClientService($company);
        
        Log::info('CONSTRUCTOR - Creando CertificateManagerService');
        $this->certificateManager = new CertificateManagerService($company);
        
        Log::info('CONSTRUCTOR - Creando XmlSerializerService');
        $this->xmlSerializer = new XmlSerializerService($company);

        Log::info('CONSTRUCTOR - ÉXITO');
        
    } catch (Exception $e) {
        Log::error('ERROR EN CONSTRUCTOR ArgentinaAnticipatedService', [
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ]);
        throw $e;
    }
}

    /**
     * Registrar viaje con información anticipada completa
     */
    public function registerVoyage(Voyage $voyage): array
    {
        $result = [
            'success' => false,
            'transaction_id' => null,
            'voyage_reference' => null,
            'response_data' => null,
            'errors' => [],
            'warnings' => [],
        ];

        DB::beginTransaction();

        try {
            $this->logOperation('info', 'Iniciando registro de información anticipada', [
                'voyage_id' => $voyage->id,
                'voyage_number' => $voyage->voyage_number,
                'vessel_name' => $voyage->vessel?->name,
                'route' => $voyage->departure_port . ' → ' . $voyage->arrival_port,
                'shipments_count' => $voyage->shipments()->count(),
            ]);

            // 1. Validaciones integrales pre-envío
            $validation = $this->validateForAnticipated($voyage);
            if (!$validation['is_valid']) {
                $result['errors'] = $validation['errors'];
                $result['warnings'] = $validation['warnings'];
                return $result;
            }

            // 2. Crear transacción en base de datos
            $transaction = $this->createTransaction($voyage);
            $result['transaction_id'] = $transaction->id;

            // 3. Generar XML usando datos reales del sistema
            $xmlContent = $this->generateXml($voyage, $transaction->transaction_id);
            if (!$xmlContent) {
                throw new Exception('No se pudo generar XML para información anticipada');
            }

             // ✅ BYPASS INTELIGENTE ARGENTINA - DESPUÉS DE XML, ANTES DE SOAP
            $argentinaData = $this->company->getArgentinaWebserviceData();
            $shouldBypass = $this->company->shouldBypassTesting('argentina');
            $isTestingConfig = $this->company->isTestingConfiguration('argentina', $argentinaData);

            $this->logOperation('info', 'Verificando bypass Argentina Anticipada', [
                'transaction_id' => $transaction->id ?? 'pending',
                'should_bypass' => $shouldBypass,
                'is_testing_config' => $isTestingConfig,
                'environment' => $this->config['environment'],
                'cuit' => $argentinaData['cuit'] ?? 'no-configurado',
            ]);

            if ($shouldBypass || $this->config['environment'] === 'testing') {
                if ($isTestingConfig || $shouldBypass) {
                    
                    $this->logOperation('info', 'BYPASS ACTIVADO: Simulando respuesta Argentina Anticipada', [
                        'transaction_id' => $transaction->id ?? 'pending',
                        'reason' => $shouldBypass ? 'Bypass empresarial activado' : 'Configuración de testing detectada',
                        'cuit_used' => $argentinaData['cuit'] ?? 'no-configurado',
                    ]);

                    // Generar respuesta simulada
                    $bypassResponse = $this->generateBypassResponse('RegistrarViaje', $transaction->transaction_id ?? uniqid(), $argentinaData);
                    
                    // Actualizar transacción como exitosa con datos de bypass
                    $transaction->update([
                        'status' => 'success',
                        'response_at' => now(),
                        'confirmation_number' => $bypassResponse['response_data']['voyage_reference'],
                        'success_data' => $bypassResponse,
                        'request_xml' => $xmlContent ?? null, // Guardar XML generado si existe
                    ]);

                    // Crear registro de respuesta estructurada
                    WebserviceResponse::create([
                        'transaction_id' => $transaction->id,
                        'response_type' => 'success',
                        'voyage_number' => $bypassResponse['response_data']['voyage_reference'],
                        'response_data' => $bypassResponse,
                        'processed_at' => now(),
                    ]);

                    // Preparar resultado final
                    $result = [
                        'success' => true,
                        'transaction_id' => $transaction->id,
                        'voyage_reference' => $bypassResponse['response_data']['voyage_reference'],
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
                    'transaction_id' => $transaction->id ?? 'pending',
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
            $this->logOperation('info', 'Procediendo con conexión real a AFIP Anticipada', [
                'transaction_id' => $transaction->id ?? 'pending',
                'environment' => $this->config['environment'],
            ]);

            // 4. Validar estructura XML generada
            if ($this->config['validate_xml_structure']) {
                $xmlValidation = $this->xmlSerializer->validateXmlStructure($xmlContent);
                if (!$xmlValidation['is_valid']) {
                    throw new Exception('XML generado no válido: ' . implode(', ', $xmlValidation['errors']));
                }
            }

            // 5. Preparar cliente SOAP
            $soapClient = $this->prepareSoapClient();

            // 6. Enviar al webservice Argentina
            $soapResult = $this->sendSoapRequest($transaction, $soapClient, $xmlContent);

            // 7. Procesar respuesta
            if ($soapResult['success']) {
                $this->processSuccessResponse($transaction, $soapResult);
                $result['success'] = true;
                $result['voyage_reference'] = $soapResult['voyage_reference'] ?? null;
                $result['response_data'] = $soapResult['response_data'];
            } else {
                $this->processErrorResponse($transaction, $soapResult);
                $result['errors'][] = $soapResult['error_message'];
            }

            DB::commit();

            $this->logOperation('info', 'Registro de información anticipada completado', [
                'transaction_id' => $transaction->id,
                'success' => $result['success'],
                'voyage_reference' => $result['voyage_reference'],
                'response_time_ms' => $soapResult['response_time_ms'] ?? null,
            ]);

            return $result;

        } catch (Exception $e) {
            DB::rollback();

            $this->logOperation('error', 'Error en registro de información anticipada', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'voyage_id' => $voyage->id,
                'transaction_id' => $result['transaction_id'],
            ]);

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
     * Rectificar viaje ya registrado
     */
    public function rectifyVoyage(Voyage $voyage, string $originalReference, string $rectificationReason): array
    {
        $result = [
            'success' => false,
            'transaction_id' => null,
            'voyage_reference' => null,
            'response_data' => null,
            'errors' => [],
            'warnings' => [],
        ];

        DB::beginTransaction();

        try {
            $this->logOperation('info', 'Iniciando rectificación de información anticipada', [
                'voyage_id' => $voyage->id,
                'original_reference' => $originalReference,
                'rectification_reason' => $rectificationReason,
            ]);

            // 1. Validar que el viaje original existe
            $originalTransaction = WebserviceTransaction::where('external_reference', $originalReference)
                ->where('company_id', $this->company->id)
                ->where('webservice_type', 'anticipada')
                ->where('status', 'success')
                ->first();

            if (!$originalTransaction) {
                $result['errors'][] = 'No se encontró el viaje original para rectificar';
                return $result;
            }

            // 2. Validaciones para rectificación
            $validation = $this->validateForRectification($voyage, $originalTransaction);
            if (!$validation['is_valid']) {
                $result['errors'] = $validation['errors'];
                return $result;
            }

            // 3. Crear nueva transacción de rectificación
            $transaction = $this->createRectificationTransaction($voyage, $originalTransaction, $rectificationReason);
            $result['transaction_id'] = $transaction->id;

            // 4. Generar XML de rectificación
            $xmlContent = $this->generateRectificationXml($voyage, $transaction->transaction_id, $originalReference, $rectificationReason);
            if (!$xmlContent) {
                throw new Exception('No se pudo generar XML de rectificación');
            }

            // 5. Cambiar SOAPAction para rectificación
            $originalSoapAction = $this->config['soap_action'];
            $this->config['soap_action'] = 'Ar.Gob.Afip.Dga.Org.wgesinformacionanticipada/RectificarViaje';

            // 6. Preparar cliente SOAP y enviar
            $soapClient = $this->prepareSoapClient();
            $soapResult = $this->sendSoapRequest($transaction, $soapClient, $xmlContent);

            // 7. Restaurar SOAPAction original
            $this->config['soap_action'] = $originalSoapAction;

            // 8. Procesar respuesta
            if ($soapResult['success']) {
                $this->processSuccessResponse($transaction, $soapResult);
                $result['success'] = true;
                $result['voyage_reference'] = $soapResult['voyage_reference'] ?? null;
                $result['response_data'] = $soapResult['response_data'];
            } else {
                $this->processErrorResponse($transaction, $soapResult);
                $result['errors'][] = $soapResult['error_message'];
            }

            DB::commit();

            $this->logOperation('info', 'Rectificación completada', [
                'transaction_id' => $transaction->id,
                'success' => $result['success'],
                'new_voyage_reference' => $result['voyage_reference'],
            ]);

            return $result;

        } catch (Exception $e) {
            DB::rollback();

            $this->logOperation('error', 'Error en rectificación', [
                'error' => $e->getMessage(),
                'voyage_id' => $voyage->id,
                'original_reference' => $originalReference,
            ]);

            $result['errors'][] = $e->getMessage();
            return $result;
        }
    }

    /**
     * Validaciones integrales para información anticipada
     */
    private function validateForAnticipated(Voyage $voyage): array
    {
        $validation = [
            'is_valid' => false,
            'errors' => [],
            'warnings' => [],
        ];

        // 1. Validar voyage básico
        if (!$voyage || !$voyage->id) {
            $validation['errors'][] = 'Viaje no válido o no encontrado';
        }

        // 2. Validar datos obligatorios del viaje
        if (!$voyage->voyage_number) {
            $validation['errors'][] = 'Número de viaje requerido';
        }

        if (!$voyage->departure_port || !$voyage->arrival_port) {
            $validation['errors'][] = 'Puertos de origen y destino requeridos';
        }

        if (!$voyage->departure_date) {
            $validation['errors'][] = 'Fecha de salida requerida';
        }

        // 3. Validar embarcación
        if (!$voyage->vessel_id || !$voyage->vessel) {
            $validation['errors'][] = 'Embarcación requerida para información anticipada';
        } else {
            if (!$voyage->vessel->name || !$voyage->vessel->imo_number) {
                $validation['errors'][] = 'Embarcación debe tener nombre e IMO válidos';
            }
        }

        // 4. Validar capitán
        if (!$voyage->captain_id || !$voyage->captain) {
            $validation['errors'][] = 'Capitán requerido para información anticipada';
        } else {
            if (!$voyage->captain->full_name || !$voyage->captain->license_number) {
                $validation['errors'][] = 'Capitán debe tener nombre completo y licencia válida';
            }
        }

        // 5. Validar que tenga shipments
        $shipmentsCount = $voyage->shipments()->count();
        if ($shipmentsCount === 0) {
            $validation['errors'][] = 'El viaje debe tener al menos un envío para información anticipada';
        } else if ($shipmentsCount > 50) {
            $validation['warnings'][] = "Viaje con {$shipmentsCount} envíos, verificar límites del webservice";
        }

        // 6. Validar empresa
        if (!$this->company->tax_id || strlen(preg_replace('/[^0-9]/', '', $this->company->tax_id)) !== 11) {
            $validation['errors'][] = 'CUIT de empresa inválido para Argentina';
        }

        // 7. Validar certificado de empresa
        if (!$this->company->certificate_path || !$this->company->certificate_password) {
            $validation['errors'][] = 'Empresa debe tener certificado .p12 configurado';
        }

        // 8. Validar país de operación
        if ($this->company->country !== 'AR') {
            $validation['errors'][] = 'Información Anticipada solo para empresas argentinas';
        }

        // 9. Validar fechas
        if ($voyage->departure_date && $voyage->departure_date->isPast()) {
            $validation['warnings'][] = 'Fecha de salida en el pasado, verificar si es correcto';
        }

        // 10. Validar consistencia de datos
        if ($voyage->vessel && $voyage->shipments()->count() > 0) {
            $totalContainers = $voyage->shipments()->sum('containers_loaded');
            $vesselCapacity = $voyage->vessel->container_capacity ?? 50;
            
            if ($totalContainers > $vesselCapacity) {
                $validation['warnings'][] = "Total de contenedores ({$totalContainers}) excede capacidad de embarcación ({$vesselCapacity})";
            }
        }

        $validation['is_valid'] = empty($validation['errors']);

        $this->logOperation($validation['is_valid'] ? 'info' : 'warning', 'Validación información anticipada completada', $validation);

        return $validation;
    }

    /**
     * Validaciones específicas para rectificación
     */
    private function validateForRectification(Voyage $voyage, WebserviceTransaction $originalTransaction): array
    {
        $validation = [
            'is_valid' => false,
            'errors' => [],
            'warnings' => [],
        ];

        // 1. Validar que la transacción original es del mismo viaje
        if ($originalTransaction->voyage_id !== $voyage->id) {
            $validation['errors'][] = 'La transacción original no corresponde al viaje actual';
        }

        // 2. Validar que no han pasado más de 24 horas desde el envío original (ejemplo de regla de negocio)
        if ($originalTransaction->created_at->diffInHours(now()) > 24) {
            $validation['warnings'][] = 'Han pasado más de 24 horas desde el envío original, verificar si es válida la rectificación';
        }

        // 3. Validar que no hay otra rectificación pendiente
        $pendingRectification = WebserviceTransaction::where('voyage_id', $voyage->id)
            ->where('webservice_type', 'anticipada')
            ->where('additional_metadata->is_rectification', true)
            ->whereIn('status', ['pending', 'sending', 'retry'])
            ->exists();

        if ($pendingRectification) {
            $validation['errors'][] = 'Ya hay una rectificación pendiente para este viaje';
        }

        // 4. Validar voyage actual (usando validación estándar)
        $standardValidation = $this->validateForAnticipated($voyage);
        if (!$standardValidation['is_valid']) {
            $validation['errors'] = array_merge($validation['errors'], $standardValidation['errors']);
            $validation['warnings'] = array_merge($validation['warnings'], $standardValidation['warnings']);
        }

        $validation['is_valid'] = empty($validation['errors']);

        return $validation;
    }

    /**
     * Crear transacción en base de datos
     */
    private function createTransaction(Voyage $voyage): WebserviceTransaction
    {
        // Generar ID único de transacción (formato: EMPRESA-FECHA-SECUENCIA)
        $companyCode = substr(preg_replace('/[^0-9]/', '', $this->company->tax_id), -4);
        $dateCode = now()->format('Ymd');
        $sequence = WebserviceTransaction::where('company_id', $this->company->id)
            ->whereDate('created_at', today())
            ->count() + 1;
        
        $transactionId = sprintf('ANT-%s-%s-%03d', $companyCode, $dateCode, $sequence);

        // Obtener URL del webservice
        $webserviceUrl = $this->getWebserviceUrl();

        // Calcular totales del viaje
        $shipments = $voyage->shipments;
        $totalWeight = $shipments->sum('cargo_weight_loaded') * 1000; // convertir a kg
        $totalContainers = $shipments->sum('containers_loaded');

        $transaction = WebserviceTransaction::create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'shipment_id' => null, // Para información anticipada es a nivel voyage
            'voyage_id' => $voyage->id,
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
            
            // Datos específicos del voyage para referencia
            'total_weight_kg' => $totalWeight,
            'container_count' => $totalContainers,
            'currency_code' => 'USD', // Por defecto
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
            
            'additional_metadata' => [
                'voyage_number' => $voyage->voyage_number,
                'vessel_name' => $voyage->vessel?->name,
                'vessel_imo' => $voyage->vessel?->imo_number,
                'captain_name' => $voyage->captain?->full_name,
                'departure_port' => $voyage->departure_port,
                'arrival_port' => $voyage->arrival_port,
                'departure_date' => $voyage->departure_date?->toISOString(),
                'arrival_date' => $voyage->arrival_date?->toISOString(),
                'is_convoy' => $voyage->is_convoy ? true : false,
                'shipments_count' => $shipments->count(),
                'is_rectification' => false,
                'method_used' => 'RegistrarViaje',
            ],
        ]);

        $this->logOperation('info', 'Transacción información anticipada creada', [
            'transaction_id' => $transaction->id,
            'internal_transaction_id' => $transactionId,
            'webservice_url' => $webserviceUrl,
            'voyage_data' => $transaction->additional_metadata,
        ]);

        return $transaction;
    }

    /**
     * Crear transacción de rectificación
     */
    private function createRectificationTransaction(Voyage $voyage, WebserviceTransaction $originalTransaction, string $reason): WebserviceTransaction
    {
        // Generar ID único para rectificación
        $companyCode = substr(preg_replace('/[^0-9]/', '', $this->company->tax_id), -4);
        $dateCode = now()->format('Ymd');
        $sequence = WebserviceTransaction::where('company_id', $this->company->id)
            ->whereDate('created_at', today())
            ->count() + 1;
        
        $transactionId = sprintf('RECT-%s-%s-%03d', $companyCode, $dateCode, $sequence);

        $webserviceUrl = $this->getWebserviceUrl();
        $shipments = $voyage->shipments;
        $totalWeight = $shipments->sum('cargo_weight_loaded') * 1000;
        $totalContainers = $shipments->sum('containers_loaded');

        $transaction = WebserviceTransaction::create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'shipment_id' => null,
            'voyage_id' => $voyage->id,
            'transaction_id' => $transactionId,
            'webservice_type' => $this->config['webservice_type'],
            'country' => $this->config['country'],
            'webservice_url' => $webserviceUrl,
            'soap_action' => 'Ar.Gob.Afip.Dga.Org.wgesinformacionanticipada/RectificarViaje',
            'status' => 'pending',
            'max_retries' => $this->config['max_retries'],
            'retry_intervals' => $this->config['retry_intervals'],
            'environment' => $this->config['environment'],
            'certificate_used' => $this->company->certificate_alias,
            
            'total_weight_kg' => $totalWeight,
            'container_count' => $totalContainers,
            'currency_code' => 'USD',
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
            
            'additional_metadata' => [
                'voyage_number' => $voyage->voyage_number,
                'vessel_name' => $voyage->vessel?->name,
                'vessel_imo' => $voyage->vessel?->imo_number,
                'captain_name' => $voyage->captain?->full_name,
                'departure_port' => $voyage->departure_port,
                'arrival_port' => $voyage->arrival_port,
                'departure_date' => $voyage->departure_date?->toISOString(),
                'arrival_date' => $voyage->arrival_date?->toISOString(),
                'is_convoy' => $voyage->is_convoy ? true : false,
                'shipments_count' => $shipments->count(),
                'is_rectification' => true,
                'method_used' => 'RectificarViaje',
                'original_transaction_id' => $originalTransaction->id,
                'original_external_reference' => $originalTransaction->external_reference,
                'rectification_reason' => $reason,
            ],
        ]);

        return $transaction;
    }

    /**
     * Generar XML usando el XmlSerializerService
     */
    private function generateXml(Voyage $voyage, string $transactionId): ?string
    {
        try {
            // Para información anticipada, usamos un método específico del XmlSerializerService
            $xmlContent = $this->xmlSerializer->createAnticipatedXml($voyage, $transactionId);
            
            if ($xmlContent) {
                $this->logOperation('info', 'XML información anticipada generado exitosamente', [
                    'xml_size_kb' => round(strlen($xmlContent) / 1024, 2),
                    'transaction_id' => $transactionId,
                    'voyage_id' => $voyage->id,
                ]);
            }

            return $xmlContent;

        } catch (Exception $e) {
            $this->logOperation('error', 'Error generando XML información anticipada', [
                'error' => $e->getMessage(),
                'transaction_id' => $transactionId,
                'voyage_id' => $voyage->id,
            ]);
            return null;
        }
    }

    /**
     * Generar XML de rectificación
     */
    private function generateRectificationXml(Voyage $voyage, string $transactionId, string $originalReference, string $reason): ?string
    {
        try {
            $xmlContent = $this->xmlSerializer->createRectificationXml($voyage, $transactionId, $originalReference, $reason);
            
            if ($xmlContent) {
                $this->logOperation('info', 'XML rectificación generado exitosamente', [
                    'xml_size_kb' => round(strlen($xmlContent) / 1024, 2),
                    'transaction_id' => $transactionId,
                    'original_reference' => $originalReference,
                ]);
            }

            return $xmlContent;

        } catch (Exception $e) {
            $this->logOperation('error', 'Error generando XML rectificación', [
                'error' => $e->getMessage(),
                'transaction_id' => $transactionId,
                'original_reference' => $originalReference,
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
     * Enviar request SOAP usando SoapClientService
     */
    private function sendSoapRequest(WebserviceTransaction $transaction, $soapClient, string $xmlContent): array
    {
        try {
            // Actualizar estado a 'sending'
            $transaction->update(['status' => 'sending', 'sent_at' => now()]);

            // Extraer parámetros del XML para el método SOAP
            $parameters = $this->extractSoapParameters($xmlContent);

            // Determinar método SOAP según si es rectificación o no
            $soapMethod = $transaction->additional_metadata['is_rectification'] ?? false 
                ? 'RectificarViaje' 
                : 'RegistrarViaje';

            // Enviar usando SoapClientService
            $soapResult = $this->soapClient->sendRequest($transaction, $soapMethod, $parameters);

            // Actualizar transacción con XMLs
            $transaction->update([
                'request_xml' => $soapResult['request_xml'] ?? $xmlContent,
                'response_xml' => $soapResult['response_xml'] ?? null,
                'response_time_ms' => $soapResult['response_time_ms'] ?? null,
            ]);

            return $soapResult;

        } catch (Exception $e) {
            $this->logOperation('error', 'Error enviando request SOAP', [
                'error' => $e->getMessage(),
                'transaction_id' => $transaction->id,
            ]);

            return [
                'success' => false,
                'error_message' => $e->getMessage(),
                'response_time_ms' => null,
            ];
        }
    }

    /**
     * Extraer parámetros SOAP del XML generado
     */
    private function extractSoapParameters(string $xmlContent): array
    {
        // Implementación básica - en producción se debe parsear el XML correctamente
        return [
            'argWSAutenticacionEmpresa' => [
                'CuitEmpresaConectada' => preg_replace('/[^0-9]/', '', $this->company->tax_id),
                'TipoAgente' => 'ATA',
                'Rol' => 'BUQUE',
            ],
            'xmlData' => $xmlContent,
        ];
    }

    /**
     * Procesar respuesta exitosa
     */
    private function processSuccessResponse(WebserviceTransaction $transaction, array $soapResult): void
    {
        // Extraer referencia del viaje de la respuesta (si está disponible)
        $voyageReference = $this->extractVoyageReference($soapResult['response_data'] ?? []);

        $transaction->update([
            'status' => 'success',
            'completed_at' => now(),
            'external_reference' => $voyageReference,
        ]);

        // Crear registro de respuesta exitosa
        WebserviceResponse::create([
            'webservice_transaction_id' => $transaction->id,
            'response_code' => '200',
            'response_message' => 'Información anticipada registrada exitosamente',
            'response_data' => $soapResult['response_data'] ?? [],
            'is_success' => true,
        ]);

        $this->logOperation('info', 'Respuesta exitosa procesada', [
            'transaction_id' => $transaction->id,
            'voyage_reference' => $voyageReference,
        ]);
    }

    /**
     * Procesar respuesta de error
     */
    private function processErrorResponse(WebserviceTransaction $transaction, array $soapResult): void
    {
        $transaction->update([
            'status' => 'error',
            'completed_at' => now(),
            'error_count' => ($transaction->error_count ?? 0) + 1,
        ]);

        // Crear registro de error
        WebserviceResponse::create([
            'webservice_transaction_id' => $transaction->id,
            'response_code' => $soapResult['error_code'] ?? '500',
            'response_message' => $soapResult['error_message'] ?? 'Error desconocido',
            'response_data' => $soapResult,
            'is_success' => false,
        ]);

        $this->logOperation('error', 'Error procesado', [
            'transaction_id' => $transaction->id,
            'error_message' => $soapResult['error_message'] ?? 'Error desconocido',
        ]);
    }

    /**
     * Extraer referencia del viaje de la respuesta AFIP
     */
    private function extractVoyageReference($responseData): ?string
    {
        // Implementación básica - en producción se debe parsear la respuesta correctamente
        if (is_array($responseData) && isset($responseData['VoyageReference'])) {
            return $responseData['VoyageReference'];
        }
        
        // Si la respuesta es XML, se puede parsear aquí
        return null;
    }

    /**
     * Obtener URL del webservice según configuración
     */
    private function getWebserviceUrl(): string
    {
        // Verificar si la empresa tiene URLs personalizadas
        $customUrls = $this->company->ws_config['webservice_urls'][$this->config['environment']] ?? null;
        
        if ($customUrls && isset($customUrls[$this->config['webservice_type']])) {
            return $customUrls[$this->config['webservice_type']];
        }

        // URLs por defecto del SoapClientService
        $defaultUrls = [
            'testing' => 'https://wsaduhomoext.afip.gob.ar/DIAV2/wgesinformacionanticipada/wgesinformacionanticipada.asmx',
            'production' => 'https://webservicesadu.afip.gob.ar/DIAV2/wgesinformacionanticipada/wgesinformacionanticipada.asmx',
        ];

        return $defaultUrls[$this->config['environment']] ?? $defaultUrls['testing'];
    }

    /**
     * Obtener estadísticas de transacciones de información anticipada de la empresa
     */
    public function getCompanyStatistics(): array
    {
        $stats = [
            'total_transactions' => 0,
            'successful_transactions' => 0,
            'error_transactions' => 0,
            'pending_transactions' => 0,
            'rectifications_count' => 0,
            'success_rate' => 0.0,
            'average_response_time_ms' => 0,
            'last_successful_transaction' => null,
            'most_common_errors' => [],
        ];

        try {
            $transactions = WebserviceTransaction::forCompany($this->company->id)
                ->ofType('anticipada')
                ->forCountry('AR');

            $stats['total_transactions'] = $transactions->count();
            $stats['successful_transactions'] = $transactions->where('status', 'success')->count();
            $stats['error_transactions'] = $transactions->whereIn('status', ['error', 'expired'])->count();
            $stats['pending_transactions'] = $transactions->whereIn('status', ['pending', 'sending', 'retry'])->count();
            
            // Contar rectificaciones
            $stats['rectifications_count'] = $transactions->where('additional_metadata->is_rectification', true)->count();

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
     * Logging centralizado para el servicio
     */
    private function logOperation(string $level, string $message, array $context = []): void
    {
        $logData = array_merge([
            'service' => 'ArgentinaAnticipatedService',
            'company_id' => $this->company->id,
            'company_name' => $this->company->legal_name,
            'user_id' => $this->user->id,
            'timestamp' => now()->toISOString(),
        ], $context);

        // Log en archivo Laravel
        Log::{$level}($message, $logData);

        // Log en tabla webservice_logs
        try {
            WebserviceLog::create([
                'transaction_id' => $context['transaction_id'] ?? null,
                'level' => $level,
                'message' => $message,
                'context' => $logData,
            ]);
        } catch (Exception $e) {
            Log::error('Error logging to webservice_logs table', [
                'original_message' => $message,
                'error' => $e->getMessage(),
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
     * Obtener métodos disponibles del webservice
     */
    public function getAvailableMethods(): array
    {
        return $this->config['methods'];
    }

    /**
 * Generar respuesta simulada (bypass) para Argentina Información Anticipada
 */
private function generateBypassResponse(string $operation, string $transactionId, array $argentinaData): array
{
    $this->logOperation('info', 'BYPASS: Simulando respuesta Argentina Información Anticipada', [
        'operation' => $operation,
        'transaction_id' => $transactionId,
        'reason' => 'Configuración de testing o bypass activado',
        'cuit_used' => $argentinaData['cuit'] ?? 'no-configurado',
    ]);

    // Generar referencias realistas Argentina
    $argentinaReference = $this->generateRealisticArgentinaReference();
    $voyageReference = 'ANT' . date('Y') . str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);

    switch ($operation) {
        case 'RegistrarViaje':
        case 'enviarAnticipada':
            return [
                'success' => true,
                'response_data' => [
                    'voyage_reference' => $voyageReference,
                    'anticipada_id' => $argentinaReference,
                    'success' => true,
                ],
                'status' => 'BYPASS_SUCCESS',
                'bypass_mode' => true,
                'processed_at' => now(),
                'status_message' => 'Información Anticipada registrada exitosamente en AFIP (SIMULADO)',
            ];

        case 'RectificarViaje':
        case 'rectificarAnticipada':
            return [
                'success' => true,
                'response_data' => [
                    'voyage_reference' => $voyageReference,
                    'rectification_id' => $argentinaReference,
                    'success' => true,
                ],
                'status' => 'RECTIFICADO',
                'status_description' => 'Información Anticipada rectificada exitosamente en AFIP (SIMULADO)',
                'bypass_mode' => true,
            ];

        case 'consultarEstado':
            return [
                'success' => true,
                'status' => 'PROCESADO',
                'status_description' => 'Información Anticipada procesada por AFIP (SIMULADO)',
                'voyage_reference' => $voyageReference,
                'last_update' => now(),
                'bypass_mode' => true,
            ];

        default:
            return [
                'success' => true,
                'status' => 'BYPASS_SUCCESS',
                'message' => "Operación Argentina Anticipada {$operation} simulada exitosamente",
                'bypass_mode' => true,
            ];
    }
}

/**
 * Generar referencia Argentina AFIP realista para Información Anticipada
 */
private function generateRealisticArgentinaReference(): string
{
    $year = date('Y');
    $office = '001'; // Código típico aduana Buenos Aires para Anticipada
    $sequence = str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
    $checkDigit = substr(md5($year . $office . $sequence), -1);
    
    return 'ANT' . $year . $office . 'INF' . $sequence . strtoupper($checkDigit);
}
}