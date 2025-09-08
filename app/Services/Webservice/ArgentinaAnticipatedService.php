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
    private ?int $currentTransactionId = null;

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
        $this->company = $company;
        $this->user = $user;
        $this->config = array_merge(self::ANTICIPATED_CONFIG, $config);

        // Inicializar servicios integrados
        $this->soapClient = new SoapClientService($company);
        $this->certificateManager = new CertificateManagerService($company);
        $this->xmlSerializer = new XmlSerializerService($company);

        $this->logInitialization('info', 'ArgentinaAnticipatedService inicializado', [
            'company_id' => $company->id,
            'company_name' => $company->legal_name,
            'user_id' => $user->id,
            'environment' => $this->config['environment'],
        ]);
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
            // Cargar relaciones necesarias
            $voyage->load(['originPort', 'destinationPort', 'leadVessel', 'captain', 'shipments']);

            $this->logOperation('info', 'Iniciando registro de información anticipada', [
                'voyage_id' => $voyage->id,
                'voyage_number' => $voyage->voyage_number,
                'vessel_name' => $voyage->leadVessel?->name,
                'route' => ($voyage->originPort?->code ?? 'ORIGEN') . ' → ' . ($voyage->destinationPort?->code ?? 'DESTINO'),
                'shipments_count' => $voyage->shipments()->count(),
            ]);

            // 1. Validaciones integrales pre-envío
            $validation = $this->validateForAnticipated($voyage);
            if (!$validation['is_valid']) {
                $result['errors'] = $validation['errors'];
                $result['warnings'] = $validation['warnings'];
                DB::rollback();
                return $result;
            }

            // 2. Crear transacción en base de datos
            $transaction = $this->createTransaction($voyage);
            $result['transaction_id'] = $transaction->id;

            $this->currentTransactionId = $transaction->id;

            // 3. Generar XML usando datos reales del sistema
            $xmlContent = $this->generateXml($voyage, $transaction->transaction_id);
            if (!$xmlContent) {
                throw new Exception('No se pudo generar XML para información anticipada');
            }

            // 4. Validar estructura XML generada
            if ($this->config['validate_xml_structure']) {
                $xmlValidation = $this->xmlSerializer->validateXmlStructure($xmlContent);
                if (!$xmlValidation['is_valid']) {
                    throw new Exception('XML generado no válido: ' . implode(', ', $xmlValidation['errors']));
                }
            }

            // ✅ BYPASS INTELIGENTE ARGENTINA - DESPUÉS DE XML, ANTES DE SOAP
            $argentinaData = $this->company->getArgentinaWebserviceData();
            $shouldBypass = $this->company->shouldBypassTesting('argentina');
            $isTestingConfig = $this->company->isTestingConfiguration('argentina', $argentinaData);

            $this->logOperation('info', 'Verificando bypass Argentina Anticipada', [
                'transaction_id' => $transaction->id,
                'should_bypass' => $shouldBypass,
                'is_testing_config' => $isTestingConfig,
                'environment' => $this->config['environment'],
                'cuit' => $argentinaData['cuit'] ?? 'no-configurado',
            ]);

            //if ($shouldBypass || $this->config['environment'] === 'testing') {
            //    if ($isTestingConfig || $shouldBypass) {
                    
            //        $this->logOperation('info', 'BYPASS ACTIVADO: Simulando respuesta Argentina Anticipada', [
            //            'transaction_id' => $transaction->id,
            //            'reason' => $shouldBypass ? 'Bypass empresarial activado' : 'Configuración de testing detectada',
            //            'cuit_used' => $argentinaData['cuit'] ?? 'testing-mode',
            //        ]);

                    // Generar respuesta simulada exitosa
            //        $bypassResponse = $this->generateBypassResponse($voyage, $transaction);

                    // Actualizar transacción como exitosa
            //        $transaction->update([
            //            'status' => 'success',
            //            'voyage_reference' => $bypassResponse['voyage_reference'],
            //            'response_data' => $bypassResponse['response_data'],
            //            'completed_at' => now(),
            //        ]);

            //        $result['success'] = true;
            //        $result['voyage_reference'] = $bypassResponse['voyage_reference'];
            //        $result['response_data'] = $bypassResponse['response_data'];
            //        $result['warnings'][] = 'Respuesta simulada - Bypass activado para testing';

            //        DB::commit();

            //        $this->logOperation('info', 'Bypass completado exitosamente', [
            //            'transaction_id' => $transaction->id,
             //           'voyage_reference' => $bypassResponse['voyage_reference'],
            //            'bypass_reason' => $shouldBypass ? 'Empresarial' : 'Testing config',
            //        ]);

            //        return $result;
            //    }
            //}

            // 5. Preparar cliente SOAP y enviar
            $soapClient = $this->prepareSoapClient();
            $soapResult = $this->sendSoapRequest($transaction, $soapClient, $xmlContent);

            // 6. Procesar respuesta
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
            ]);

            return $result;

        } catch (Exception $e) {
            DB::rollback();

            $this->logOperation('error', 'Error en registro de información anticipada', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'voyage_id' => $voyage->id,
                'transaction_id' => $this->currentTransactionId,
            ]);

            $result['errors'][] = $e->getMessage();
            return $result;
        }
    }

    /**
     * Generar respuesta de bypass realista para Argentina Anticipada
     */
    private function generateBypassResponse(Voyage $voyage, WebserviceTransaction $transaction): array
    {
        $argentinaReference = $this->generateRealisticArgentinaReference();
        
        return [
            'voyage_reference' => $argentinaReference,
            'response_data' => [
                'codigo_respuesta' => '00',
                'descripcion_respuesta' => 'Operación exitosa',
                'numero_referencia' => $argentinaReference,
                'fecha_procesamiento' => now()->format('Y-m-d H:i:s'),
                'numero_expediente' => 'EXP-' . $argentinaReference,
                'estado_tramite' => 'REGISTRADO',
                'observaciones' => 'Información anticipada registrada correctamente',
                'bypass_info' => [
                    'simulated' => true,
                    'mode' => 'bypass_argentina_anticipated',
                    'transaction_id' => $transaction->transaction_id,
                    'generated_at' => now()->toISOString(),
                ],
                'vessel_data' => [
                    'vessel_name' => $voyage->leadVessel?->name,
                    'imo_number' => $voyage->leadVessel?->imo_number,
                    'voyage_number' => $voyage->voyage_number,
                ],
                'route_data' => [
                    'origin_port' => $voyage->originPort?->code,
                    'destination_port' => $voyage->destinationPort?->code,
                    'departure_date' => $voyage->departure_date?->format('Y-m-d'),
                ],
            ],
        ];
    }

    /**
     * Generar referencia realista Argentina
     */
    private function generateRealisticArgentinaReference(): string
    {
        $year = now()->format('Y');
        $sequence = rand(100000, 999999);
        return "ARG{$year}{$sequence}";
    }

    /**
     * Método para logs sin transaction_id (durante inicialización)
     */
    private function logInitialization(string $level, string $message, array $context = []): void
    {
        $logData = array_merge([
            'service' => 'ArgentinaAnticipatedService',
            'company_id' => $this->company->id,
            'company_name' => $this->company->legal_name,
            'user_id' => $this->user->id,
            'timestamp' => now()->toISOString(),
        ], $context);

        Log::log($level, $message, $logData);
    }

    /**
     * Crear transacción de base de datos
     */
    private function createTransaction(Voyage $voyage): WebserviceTransaction
    {
        // Calcular datos sumarios
        $shipments = $voyage->shipments;
        $totalWeight = $shipments->sum('total_weight_kg') ?? 0;
        $totalContainers = $shipments->sum('containers_loaded') ?? 0;

        // Generar ID único de transacción
        $companyCode = substr(preg_replace('/[^0-9]/', '', $this->company->tax_id), -4);
        $dateCode = now()->format('Ymd-Hi');
        $randomSuffix = rand(10, 99);
        $transactionId = "ANT-{$companyCode}-{$dateCode}-{$randomSuffix}";

        // URL del webservice según entorno
        $webserviceUrl = $this->config['environment'] === 'production' 
            ? 'https://wsaduanaprod.afip.gob.ar/DIAV2/wgesinformacionanticipada/wgesinformacionanticipada.asmx'
            : 'https://wsaduhomoext.afip.gob.ar/DIAV2/wgesinformacionanticipada/wgesinformacionanticipada.asmx';

        $transaction = WebserviceTransaction::create([
            'company_id' => $this->company->id,
            'voyage_id' => $voyage->id,
            'user_id' => $this->user->id,
            'webservice_type' => 'anticipada',
            'country' => 'AR',
            'transaction_id' => $transactionId,
            'status' => 'pending',
            'webservice_url' => $webserviceUrl,
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
                'vessel_name' => $voyage->leadVessel?->name,
                'vessel_imo' => $voyage->leadVessel?->imo_number,
                'captain_name' => $voyage->captain?->full_name,
                'originPort' => $voyage->originPort?->code,
                'destinationPort' => $voyage->destinationPort?->code,
                'departure_date' => $voyage->departure_date?->toISOString(),
                'arrival_date' => $voyage->estimated_arrival_date?->toISOString(),
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

        if (!$voyage->originPort || !$voyage->destinationPort) {
            $validation['errors'][] = 'Puertos de origen y destino requeridos';
        }

        if (!$voyage->departure_date) {
            $validation['errors'][] = 'Fecha de salida requerida';
        }

        // 3. Validar embarcación
        if (!$voyage->lead_vessel_id || !$voyage->leadVessel) {
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
        if ($voyage->leadVessel && $voyage->shipments()->count() > 0) {
            $totalContainers = $voyage->shipments()->sum('containers_loaded');
            $vesselCapacity = $voyage->leadVessel->container_capacity ?? 50;
            
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
                'originPort' => $voyage->originPort,
                'destinationPort' => $voyage->destinationPort,
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
    private function logOperation(string $level, string $message, array $context = [], string $category = 'general'): void
    {
        try {
            $context['service'] = 'ArgentinaAnticipatedService';
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
                    'category' => $category, // ✅ CAMPO AGREGADO
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
     * Obtener métodos disponibles del webservice
     */
    public function getAvailableMethods(): array
    {
        return $this->config['methods'];
    }

}