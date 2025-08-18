<?php

namespace App\Services\Webservice;

use App\Models\Company;
use App\Models\Shipment;
use App\Models\Container;
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
 * MÓDULO 4: WEBSERVICES ADUANA - ArgentinaDeconsolidationService
 *
 * Servicio integrador completo para Desconsolidados Argentina AFIP.
 * Maneja títulos madre, contenedores y títulos hijos según DESA.
 * 
 * Integra:
 * - SoapClientService: Cliente SOAP con URLs reales Argentina
 * - CertificateManagerService: Gestión certificados .p12
 * - XmlSerializerService: Generación XML según especificación AFIP
 * 
 * Funcionalidades:
 * - Registro de títulos desconsolidador (RegistrarTitulosDesconsolidador)
 * - Rectificación de títulos registrados (RectificarTitulosDesconsolidador)
 * - Eliminación de títulos (EliminarTitulosDesconsolidador)
 * - Validación completa pre-envío usando datos reales del sistema
 * - Generación de XML con datos de PARANA (MAERSK, contenedores reales)
 * - Envío SOAP al webservice Argentina con autenticación
 * - Procesamiento de respuestas y actualización de estados
 * - Sistema completo de logs y auditoría de transacciones
 * - Manejo de errores y reintentos automáticos
 * 
 * Conceptos de Desconsolidación:
 * - **Título Madre**: Shipment original que se va a desconsolidar
 * - **Contenedores**: Contenedores asociados al título madre
 * - **Títulos Hijos**: Nuevos shipments resultantes de la desconsolidación
 * 
 * Datos reales soportados:
 * - Empresas: MAERSK LINE ARGENTINA S.A. (CUIT, certificados .p12)
 * - Contenedores: CONT001, CONT002 con tipos 20GP, 40HC reales
 * - Shipments: Como títulos madre con división en títulos hijos
 * - Validaciones: Pesos, volúmenes, coherencia de datos
 */
class ArgentinaDeconsolidationService
{
    private Company $company;
    private User $user;
    private SoapClientService $soapClient;
    private CertificateManagerService $certificateManager;
    private XmlSerializerService $xmlSerializer;
    private array $config;

    /**
     * Configuración específica para Desconsolidados Argentina
     */
    private const DECONSOLIDATION_CONFIG = [
        'webservice_type' => 'desconsolidado',
        'country' => 'AR',
        'soap_action' => 'Ar.Gob.Afip.Dga.wgesregsintia2/RegistrarTitulosDesconsolidador',
        'environment' => 'testing', // Por defecto testing, se puede cambiar
        'max_retries' => 3,
        'retry_intervals' => [30, 120, 300], // 30s, 2min, 5min
        'timeout_seconds' => 60,
        'require_certificate' => true,
        'validate_xml_structure' => true,
        'methods' => ['RegistrarTitulosDesconsolidador', 'RectificarTitulosDesconsolidador', 'EliminarTitulosDesconsolidador'],
        'require_company_role' => 'Desconsolidador',
    ];

    public function __construct(Company $company, User $user, array $config = [])
    {
        $this->company = $company;
        $this->user = $user;
        $this->config = array_merge(self::DECONSOLIDATION_CONFIG, $config);

        // Inicializar servicios integrados
        $this->soapClient = new SoapClientService($company);
        $this->certificateManager = new CertificateManagerService($company);
        $this->xmlSerializer = new XmlSerializerService($company);

        $this->logOperation('info', 'ArgentinaDeconsolidationService inicializado', [
            'company_id' => $company->id,
            'company_name' => $company->legal_name,
            'user_id' => $user->id,
            'environment' => $this->config['environment'],
        ]);
    }

    /**
     * Registrar títulos desconsolidador
     * 
     * @param Shipment $tituloMadre Shipment original a desconsolidar
     * @param array $contenedores IDs de contenedores asociados
     * @param array $titulosHijos Datos de los nuevos shipments hijos
     */
    public function registerDeconsolidation(Shipment $tituloMadre, array $contenedores, array $titulosHijos): array
    {
        $result = [
            'success' => false,
            'transaction_id' => null,
            'deconsolidation_reference' => null,
            'response_data' => null,
            'errors' => [],
            'warnings' => [],
        ];

        DB::beginTransaction();

        try {
            $this->logOperation('info', 'Iniciando registro de desconsolidación', [
                'titulo_madre_id' => $tituloMadre->id,
                'titulo_madre_number' => $tituloMadre->shipment_number,
                'contenedores_count' => count($contenedores),
                'titulos_hijos_count' => count($titulosHijos),
            ]);

            // ✅ BYPASS INTELIGENTE ARGENTINA - ANTES DE VALIDACIONES
            $argentinaData = $this->company->getArgentinaWebserviceData();
            $shouldBypass = $this->company->shouldBypassTesting('argentina');
            $isTestingConfig = $this->company->isTestingConfiguration('argentina', $argentinaData);

            $this->logOperation('info', 'Verificando bypass Argentina Desconsolidación', [
                'should_bypass' => $shouldBypass,
                'is_testing_config' => $isTestingConfig,
                'environment' => $this->config['environment'],
                'cuit' => $argentinaData['cuit'] ?? 'no-configurado',
            ]);

            if ($shouldBypass || $this->config['environment'] === 'testing') {
                if ($isTestingConfig || $shouldBypass) {
                    
                    $this->logOperation('info', 'BYPASS ACTIVADO: Simulando respuesta Argentina Desconsolidación', [
                        'reason' => $shouldBypass ? 'Bypass empresarial activado' : 'Configuración de testing detectada',
                        'cuit_used' => $argentinaData['cuit'] ?? 'testing-mode',
                    ]);

                    // Crear transacción mínima para el bypass
                    $transaction = $this->createBypassTransaction($tituloMadre, $contenedores, $titulosHijos);
                    $result['transaction_id'] = $transaction->id;

                    // Generar respuesta simulada exitosa
                    $bypassResponse = $this->generateBypassResponse($tituloMadre, $transaction);

                    // Actualizar transacción como exitosa
                    $transaction->update([
                        'status' => 'success',
                        'voyage_reference' => $bypassResponse['deconsolidation_reference'],
                        'response_data' => $bypassResponse['response_data'],
                        'completed_at' => now(),
                    ]);

                    $result['success'] = true;
                    $result['deconsolidation_reference'] = $bypassResponse['deconsolidation_reference'];
                    $result['response_data'] = $bypassResponse['response_data'];
                    $result['warnings'][] = 'Respuesta simulada - Bypass activado para testing';

                    DB::commit();

                    $this->logOperation('info', 'Bypass completado exitosamente', [
                        'transaction_id' => $transaction->id,
                        'deconsolidation_reference' => $bypassResponse['deconsolidation_reference'],
                        'bypass_reason' => $shouldBypass ? 'Empresarial' : 'Testing config',
                    ]);

                    return $result;
                }
            }

            // 1. Validaciones integrales pre-envío (solo si no hay bypass)
            $validation = $this->validateForDeconsolidation($tituloMadre, $contenedores, $titulosHijos);
            if (!$validation['is_valid']) {
                $result['errors'] = $validation['errors'];
                $result['warnings'] = $validation['warnings'];
                return $result;
            }

            // 2. Crear transacción en base de datos
            $transaction = $this->createDeconsolidationTransaction($tituloMadre, $contenedores, $titulosHijos);
            $result['transaction_id'] = $transaction->id;

            // 3. Generar XML usando datos reales del sistema
            $xmlContent = $this->generateDeconsolidationXml($tituloMadre, $contenedores, $titulosHijos, $transaction->transaction_id);
            if (!$xmlContent) {
                throw new Exception('No se pudo generar XML para desconsolidación');
            }

            // 4. Validar estructura XML generada
            if ($this->config['validate_xml_structure']) {
                $xmlValidation = $this->xmlSerializer->validateXmlStructure($xmlContent);
                if (!$xmlValidation['is_valid']) {
                    throw new Exception('XML generado no válido: ' . implode(', ', $xmlValidation['errors']));
                }
            }

            // 5. Preparar cliente SOAP y enviar
            $soapClient = $this->prepareSoapClient();
            $soapResult = $this->sendSoapRequest($transaction, $soapClient, $xmlContent);

            // 6. Procesar respuesta
            if ($soapResult['success']) {
                $this->processSuccessResponse($transaction, $soapResult);
                $result['success'] = true;
                $result['deconsolidation_reference'] = $soapResult['deconsolidation_reference'] ?? null;
                $result['response_data'] = $soapResult['response_data'];
            } else {
                $this->processErrorResponse($transaction, $soapResult);
                $result['errors'][] = $soapResult['error_message'];
            }

            DB::commit();

            $this->logOperation('info', 'Registro de desconsolidación completado', [
                'transaction_id' => $transaction->id,
                'success' => $result['success'],
                'deconsolidation_reference' => $result['deconsolidation_reference'] ?? null,
            ]);

            return $result;

        } catch (Exception $e) {
            DB::rollback();

            $this->logOperation('error', 'Error en registro de desconsolidación', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'titulo_madre_id' => $tituloMadre->id,
                'transaction_id' => $result['transaction_id'],
            ]);

            $result['errors'][] = $e->getMessage();
            return $result;
        }
    }

    /**
     * Crear transacción para bypass (más simple)
     */
    private function createBypassTransaction(Shipment $tituloMadre, array $contenedores, array $titulosHijos): WebserviceTransaction
    {
        // Generar ID único de transacción simplificado
        $companyCode = substr(preg_replace('/[^0-9]/', '', $this->company->tax_id), -4);
        $dateCode = now()->format('Ymd-Hi');
        $randomSuffix = rand(10, 99);
        $transactionId = "DESCON-{$companyCode}-{$dateCode}-{$randomSuffix}";

        return WebserviceTransaction::create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'shipment_id' => $tituloMadre->id,
            'voyage_id' => $tituloMadre->voyage_id,
            'transaction_id' => $transactionId,
            'webservice_type' => 'desconsolidado',
            'country' => 'AR',
            'status' => 'pending',
            'webservice_url' => 'https://wsaduhomoext.afip.gob.ar/DIAV2/wgesregsintia2/wgesregsintia2.asmx',
            'environment' => $this->config['environment'],
            'certificate_used' => $this->company->certificate_alias,
            
            'total_weight_kg' => array_sum(array_column($titulosHijos, 'peso')),
            'container_count' => count($contenedores),
            'currency_code' => 'USD',
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
            
            'additional_metadata' => [
                'titulo_madre_number' => $tituloMadre->shipment_number,
                'contenedores_count' => count($contenedores),
                'titulos_hijos_count' => count($titulosHijos),
                'is_rectification' => false,
                'method_used' => 'RegistrarTitulosDesconsolidador',
                'bypass_mode' => true,
            ],
        ]);
    }

    /**
     * Generar respuesta de bypass realista para Argentina Desconsolidación
     */
    private function generateBypassResponse(Shipment $tituloMadre, WebserviceTransaction $transaction): array
    {
        $argentinaReference = $this->generateRealisticArgentinaReference();
        
        return [
            'deconsolidation_reference' => $argentinaReference,
            'response_data' => [
                'codigo_respuesta' => '00',
                'descripcion_respuesta' => 'Operación exitosa',
                'numero_referencia' => $argentinaReference,
                'fecha_procesamiento' => now()->format('Y-m-d H:i:s'),
                'numero_expediente' => 'EXP-' . $argentinaReference,
                'estado_tramite' => 'REGISTRADO',
                'observaciones' => 'Títulos desconsolidador registrados correctamente',
                'bypass_info' => [
                    'simulated' => true,
                    'mode' => 'bypass_argentina_deconsolidation',
                    'transaction_id' => $transaction->transaction_id,
                    'generated_at' => now()->toISOString(),
                ],
                'shipment_data' => [
                    'titulo_madre_number' => $tituloMadre->shipment_number,
                    'titulo_madre_id' => $tituloMadre->id,
                    'voyage_number' => $tituloMadre->voyage?->voyage_number,
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
        return "DESCON{$year}{$sequence}";
    }

    /**
     * Rectificar títulos desconsolidador ya registrados
     */
    public function rectifyDeconsolidation(Shipment $tituloMadre, array $contenedores, array $titulosHijos, string $originalReference, string $reason): array
    {
        $result = [
            'success' => false,
            'transaction_id' => null,
            'deconsolidation_reference' => null,
            'response_data' => null,
            'errors' => [],
            'warnings' => [],
        ];

        DB::beginTransaction();

        try {
            $this->logOperation('info', 'Iniciando rectificación de desconsolidación', [
                'titulo_madre_id' => $tituloMadre->id,
                'original_reference' => $originalReference,
                'rectification_reason' => $reason,
            ]);

            // 1. Validar que el registro original existe
            $originalTransaction = WebserviceTransaction::where('external_reference', $originalReference)
                ->where('company_id', $this->company->id)
                ->where('webservice_type', 'desconsolidado')
                ->where('status', 'success')
                ->first();

            if (!$originalTransaction) {
                $result['errors'][] = 'No se encontró la desconsolidación original para rectificar';
                return $result;
            }

            // 2. Validaciones para rectificación
            $validation = $this->validateForRectification($tituloMadre, $contenedores, $titulosHijos, $originalTransaction);
            if (!$validation['is_valid']) {
                $result['errors'] = $validation['errors'];
                return $result;
            }

            // 3. Crear nueva transacción de rectificación
            $transaction = $this->createRectificationTransaction($tituloMadre, $contenedores, $titulosHijos, $originalTransaction, $reason);
            $result['transaction_id'] = $transaction->id;

            // 4. Generar XML de rectificación
            $xmlContent = $this->generateRectificationXml($tituloMadre, $contenedores, $titulosHijos, $transaction->transaction_id, $originalReference, $reason);
            if (!$xmlContent) {
                throw new Exception('No se pudo generar XML de rectificación');
            }

            // 5. Cambiar SOAPAction para rectificación
            $originalSoapAction = $this->config['soap_action'];
            $this->config['soap_action'] = 'Ar.Gob.Afip.Dga.wgesregsintia2/RectificarTitulosDesconsolidador';

            // 6. Preparar cliente SOAP y enviar
            $soapClient = $this->prepareSoapClient();
            $soapResult = $this->sendSoapRequest($transaction, $soapClient, $xmlContent);

            // 7. Restaurar SOAPAction original
            $this->config['soap_action'] = $originalSoapAction;

            // 8. Procesar respuesta
            if ($soapResult['success']) {
                $this->processSuccessResponse($transaction, $soapResult);
                $result['success'] = true;
                $result['deconsolidation_reference'] = $soapResult['deconsolidation_reference'] ?? null;
                $result['response_data'] = $soapResult['response_data'];
            } else {
                $this->processErrorResponse($transaction, $soapResult);
                $result['errors'][] = $soapResult['error_message'];
            }

            DB::commit();

            $this->logOperation('info', 'Rectificación completada', [
                'transaction_id' => $transaction->id,
                'success' => $result['success'],
                'new_reference' => $result['deconsolidation_reference'],
            ]);

            return $result;

        } catch (Exception $e) {
            DB::rollback();

            $this->logOperation('error', 'Error en rectificación de desconsolidación', [
                'error' => $e->getMessage(),
                'titulo_madre_id' => $tituloMadre->id,
                'original_reference' => $originalReference,
            ]);

            $result['errors'][] = $e->getMessage();
            return $result;
        }
    }

    /**
     * Eliminar títulos desconsolidador
     */
    public function deleteDeconsolidation(string $originalReference, string $reason): array
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
            $this->logOperation('info', 'Iniciando eliminación de desconsolidación', [
                'original_reference' => $originalReference,
                'deletion_reason' => $reason,
            ]);

            // 1. Validar que el registro original existe
            $originalTransaction = WebserviceTransaction::where('external_reference', $originalReference)
                ->where('company_id', $this->company->id)
                ->where('webservice_type', 'desconsolidado')
                ->where('status', 'success')
                ->first();

            if (!$originalTransaction) {
                $result['errors'][] = 'No se encontró la desconsolidación para eliminar';
                return $result;
            }

            // 2. Validar que se puede eliminar
            if ($originalTransaction->created_at->diffInHours(now()) > 48) {
                $result['warnings'][] = 'Han pasado más de 48 horas desde el registro, verificar si es válida la eliminación';
            }

            // 3. Crear transacción de eliminación
            $transaction = $this->createDeletionTransaction($originalTransaction, $reason);
            $result['transaction_id'] = $transaction->id;

            // 4. Generar XML de eliminación
            $xmlContent = $this->generateDeletionXml($transaction->transaction_id, $originalReference, $reason);
            if (!$xmlContent) {
                throw new Exception('No se pudo generar XML de eliminación');
            }

            // 5. Cambiar SOAPAction para eliminación
            $originalSoapAction = $this->config['soap_action'];
            $this->config['soap_action'] = 'Ar.Gob.Afip.Dga.wgesregsintia2/EliminarTitulosDesconsolidador';

            // 6. Preparar cliente SOAP y enviar
            $soapClient = $this->prepareSoapClient();
            $soapResult = $this->sendSoapRequest($transaction, $soapClient, $xmlContent);

            // 7. Restaurar SOAPAction original
            $this->config['soap_action'] = $originalSoapAction;

            // 8. Procesar respuesta
            if ($soapResult['success']) {
                $this->processSuccessResponse($transaction, $soapResult);
                $result['success'] = true;
                $result['response_data'] = $soapResult['response_data'];
                
                // Marcar transacción original como eliminada
                $originalTransaction->update(['status' => 'deleted']);
            } else {
                $this->processErrorResponse($transaction, $soapResult);
                $result['errors'][] = $soapResult['error_message'];
            }

            DB::commit();

            $this->logOperation('info', 'Eliminación completada', [
                'transaction_id' => $transaction->id,
                'success' => $result['success'],
            ]);

            return $result;

        } catch (Exception $e) {
            DB::rollback();

            $this->logOperation('error', 'Error en eliminación de desconsolidación', [
                'error' => $e->getMessage(),
                'original_reference' => $originalReference,
            ]);

            $result['errors'][] = $e->getMessage();
            return $result;
        }
    }

    /**
     * Validaciones integrales para desconsolidación
     */
    private function validateForDeconsolidation(Shipment $tituloMadre, array $contenedores, array $titulosHijos): array
    {
        $validation = [
            'is_valid' => false,
            'errors' => [],
            'warnings' => [],
        ];

        // 1. Validar título madre (shipment)
        if (!$tituloMadre || !$tituloMadre->id) {
            $validation['errors'][] = 'Título madre no válido o no encontrado';
        }

        if (!$tituloMadre->shipment_number) {
            $validation['errors'][] = 'Título madre debe tener número de shipment';
        }

        // 2. Validar que el título madre tiene contenedores cargados
        if (!$tituloMadre->containers_loaded || $tituloMadre->containers_loaded <= 0) {
            $validation['errors'][] = 'Título madre debe tener contenedores cargados';
        }

        // 3. Validar contenedores
        if (empty($contenedores)) {
            $validation['errors'][] = 'Debe especificar al menos un contenedor para desconsolidar';
        }

        // Verificar que los contenedores existen (sin validar shipment_id por ahora)
        foreach ($contenedores as $containerId) {
            $container = Container::where('id', $containerId)->first();
            
            if (!$container) {
                $validation['errors'][] = "Contenedor {$containerId} no encontrado";
            }
        }

        // 4. Validar títulos hijos
        if (empty($titulosHijos)) {
            $validation['errors'][] = 'Debe especificar al menos un título hijo para la desconsolidación';
        }

        if (count($titulosHijos) > 20) {
            $validation['warnings'][] = 'Desconsolidación con más de 20 títulos hijos, verificar límites del webservice';
        }

        foreach ($titulosHijos as $index => $tituloHijo) {
            if (empty($tituloHijo['numero'])) {
                $validation['errors'][] = "Título hijo #{$index} debe tener número";
            }
            
            if (empty($tituloHijo['descripcion'])) {
                $validation['errors'][] = "Título hijo #{$index} debe tener descripción";
            }

            if (isset($tituloHijo['peso']) && $tituloHijo['peso'] <= 0) {
                $validation['errors'][] = "Título hijo #{$index} debe tener peso válido";
            }
        }

        // 5. Validar empresa
        if (!$this->company->tax_id || strlen(preg_replace('/[^0-9]/', '', $this->company->tax_id)) !== 11) {
            $validation['errors'][] = 'CUIT de empresa inválido para Argentina';
        }

        // 6. Validar rol de empresa
        if (!$this->company->hasRole('Desconsolidador')) {
            $validation['errors'][] = 'Empresa debe tener rol "Desconsolidador" para realizar desconsolidaciones';
        }

        // 7. Validar certificado de empresa
        if (!$this->company->certificate_path || !$this->company->certificate_password) {
            $validation['errors'][] = 'Empresa debe tener certificado .p12 configurado';
        }

        // 8. Validar país de operación
        if ($this->company->country !== 'AR') {
            $validation['errors'][] = 'Desconsolidados solo para empresas argentinas';
        }

        // 9. Validar coherencia de pesos
        $pesoTotalHijos = array_sum(array_column($titulosHijos, 'peso'));
        $pesoMadre = $tituloMadre->gross_weight ?? 0;
        
        if ($pesoTotalHijos > $pesoMadre * 1.1) { // Tolerancia del 10%
            $validation['warnings'][] = "Peso total títulos hijos ({$pesoTotalHijos} kg) excede peso madre ({$pesoMadre} kg)";
        }

        // 10. Validar que el título madre no esté ya desconsolidado
        $existingDeconsolidation = WebserviceTransaction::where('shipment_id', $tituloMadre->id)
            ->where('webservice_type', 'desconsolidado')
            ->where('status', 'success')
            ->exists();

        if ($existingDeconsolidation) {
            $validation['warnings'][] = 'El título madre ya tiene una desconsolidación registrada, esto será una rectificación';
        }

        $validation['is_valid'] = empty($validation['errors']);

        $this->logOperation($validation['is_valid'] ? 'info' : 'warning', 'Validación desconsolidación completada', $validation);

        return $validation;
    }

    /**
     * Validaciones específicas para rectificación
     */
    private function validateForRectification(Shipment $tituloMadre, array $contenedores, array $titulosHijos, WebserviceTransaction $originalTransaction): array
    {
        $validation = [
            'is_valid' => false,
            'errors' => [],
            'warnings' => [],
        ];

        // 1. Validar que la transacción original es del mismo shipment
        if ($originalTransaction->shipment_id !== $tituloMadre->id) {
            $validation['errors'][] = 'La transacción original no corresponde al título madre actual';
        }

        // 2. Validar que no han pasado más de 72 horas desde el envío original
        if ($originalTransaction->created_at->diffInHours(now()) > 72) {
            $validation['warnings'][] = 'Han pasado más de 72 horas desde el envío original, verificar si es válida la rectificación';
        }

        // 3. Validar que no hay otra rectificación pendiente
        $pendingRectification = WebserviceTransaction::where('shipment_id', $tituloMadre->id)
            ->where('webservice_type', 'desconsolidado')
            ->where('additional_metadata->is_rectification', true)
            ->whereIn('status', ['pending', 'sending', 'retry'])
            ->exists();

        if ($pendingRectification) {
            $validation['errors'][] = 'Ya hay una rectificación pendiente para este título madre';
        }

        // 4. Validar datos actuales (usando validación estándar)
        $standardValidation = $this->validateForDeconsolidation($tituloMadre, $contenedores, $titulosHijos);
        if (!$standardValidation['is_valid']) {
            $validation['errors'] = array_merge($validation['errors'], $standardValidation['errors']);
            $validation['warnings'] = array_merge($validation['warnings'], $standardValidation['warnings']);
        }

        $validation['is_valid'] = empty($validation['errors']);

        return $validation;
    }

    /**
     * Crear transacción en base de datos para desconsolidación
     */
    private function createDeconsolidationTransaction(Shipment $tituloMadre, array $contenedores, array $titulosHijos): WebserviceTransaction
    {
        // Generar ID único de transacción (formato: DESCON-EMPRESA-FECHA-SECUENCIA)
        $companyCode = substr(preg_replace('/[^0-9]/', '', $this->company->tax_id), -4);
        $dateCode = now()->format('Ymd');
        $sequence = WebserviceTransaction::where('company_id', $this->company->id)
            ->whereDate('created_at', today())
            ->count() + 1;
        
        $transactionId = sprintf('DESCON-%s-%s-%03d', $companyCode, $dateCode, $sequence);

        // Obtener URL del webservice
        $webserviceUrl = $this->getWebserviceUrl();

        // Calcular totales
        $totalWeight = array_sum(array_column($titulosHijos, 'peso'));
        $totalContainers = count($contenedores);

        $transaction = WebserviceTransaction::create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'shipment_id' => $tituloMadre->id,
            'voyage_id' => $tituloMadre->voyage_id,
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
            
            // Datos específicos de la desconsolidación
            'total_weight_kg' => $totalWeight,
            'container_count' => $totalContainers,
            'currency_code' => 'USD',
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
            
            'additional_metadata' => [
                'titulo_madre_number' => $tituloMadre->shipment_number,
                'titulo_madre_weight' => $tituloMadre->gross_weight,
                'contenedores_ids' => $contenedores,
                'titulos_hijos_count' => count($titulosHijos),
                'titulos_hijos' => $titulosHijos,
                'is_rectification' => false,
                'method_used' => 'RegistrarTitulosDesconsolidador',
                'company_role' => 'Desconsolidador',
            ],
        ]);

        $this->logOperation('info', 'Transacción desconsolidación creada', [
            'transaction_id' => $transaction->id,
            'internal_transaction_id' => $transactionId,
            'webservice_url' => $webserviceUrl,
            'titulo_madre' => $tituloMadre->shipment_number,
            'contenedores_count' => $totalContainers,
            'titulos_hijos_count' => count($titulosHijos),
        ]);

        return $transaction;
    }

    /**
     * Crear transacción de rectificación
     */
    private function createRectificationTransaction(Shipment $tituloMadre, array $contenedores, array $titulosHijos, WebserviceTransaction $originalTransaction, string $reason): WebserviceTransaction
    {
        // Generar ID único para rectificación
        $companyCode = substr(preg_replace('/[^0-9]/', '', $this->company->tax_id), -4);
        $dateCode = now()->format('Ymd');
        $sequence = WebserviceTransaction::where('company_id', $this->company->id)
            ->whereDate('created_at', today())
            ->count() + 1;
        
        $transactionId = sprintf('DESCON-RECT-%s-%s-%03d', $companyCode, $dateCode, $sequence);

        $webserviceUrl = $this->getWebserviceUrl();
        $totalWeight = array_sum(array_column($titulosHijos, 'peso'));
        $totalContainers = count($contenedores);

        $transaction = WebserviceTransaction::create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'shipment_id' => $tituloMadre->id,
            'voyage_id' => $tituloMadre->voyage_id,
            'transaction_id' => $transactionId,
            'webservice_type' => $this->config['webservice_type'],
            'country' => $this->config['country'],
            'webservice_url' => $webserviceUrl,
            'soap_action' => 'Ar.Gob.Afip.Dga.wgesregsintia2/RectificarTitulosDesconsolidador',
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
                'titulo_madre_number' => $tituloMadre->shipment_number,
                'titulo_madre_weight' => $tituloMadre->gross_weight,
                'contenedores_ids' => $contenedores,
                'titulos_hijos_count' => count($titulosHijos),
                'titulos_hijos' => $titulosHijos,
                'is_rectification' => true,
                'method_used' => 'RectificarTitulosDesconsolidador',
                'original_transaction_id' => $originalTransaction->id,
                'original_external_reference' => $originalTransaction->external_reference,
                'rectification_reason' => $reason,
            ],
        ]);

        return $transaction;
    }

    /**
     * Crear transacción de eliminación
     */
    private function createDeletionTransaction(WebserviceTransaction $originalTransaction, string $reason): WebserviceTransaction
    {
        $companyCode = substr(preg_replace('/[^0-9]/', '', $this->company->tax_id), -4);
        $dateCode = now()->format('Ymd');
        $sequence = WebserviceTransaction::where('company_id', $this->company->id)
            ->whereDate('created_at', today())
            ->count() + 1;
        
        $transactionId = sprintf('DESCON-DEL-%s-%s-%03d', $companyCode, $dateCode, $sequence);

        $transaction = WebserviceTransaction::create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'shipment_id' => $originalTransaction->shipment_id,
            'voyage_id' => $originalTransaction->voyage_id,
            'transaction_id' => $transactionId,
            'webservice_type' => $this->config['webservice_type'],
            'country' => $this->config['country'],
            'webservice_url' => $this->getWebserviceUrl(),
            'soap_action' => 'Ar.Gob.Afip.Dga.wgesregsintia2/EliminarTitulosDesconsolidador',
            'status' => 'pending',
            'max_retries' => $this->config['max_retries'],
            'retry_intervals' => $this->config['retry_intervals'],
            'environment' => $this->config['environment'],
            'certificate_used' => $this->company->certificate_alias,
            
            'total_weight_kg' => 0,
            'container_count' => 0,
            'currency_code' => 'USD',
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
            
            'additional_metadata' => [
                'is_deletion' => true,
                'method_used' => 'EliminarTitulosDesconsolidador',
                'original_transaction_id' => $originalTransaction->id,
                'original_external_reference' => $originalTransaction->external_reference,
                'deletion_reason' => $reason,
            ],
        ]);

        return $transaction;
    }

    /**
     * Generar XML usando el XmlSerializerService
     */
    private function generateDeconsolidationXml(Shipment $tituloMadre, array $contenedores, array $titulosHijos, string $transactionId): ?string
    {
        try {
            // Preparar datos estructurados para el XML
            $deconsolidationData = [
                'titulo_madre' => $tituloMadre,
                'contenedores' => $contenedores,
                'titulos_hijos' => $titulosHijos,
            ];

            $xmlContent = $this->xmlSerializer->createDeconsolidationXml($deconsolidationData, $transactionId);
            
            if ($xmlContent) {
                $this->logOperation('info', 'XML desconsolidación generado exitosamente', [
                    'xml_size_kb' => round(strlen($xmlContent) / 1024, 2),
                    'transaction_id' => $transactionId,
                    'titulo_madre_id' => $tituloMadre->id,
                ]);
            }

            return $xmlContent;

        } catch (Exception $e) {
            $this->logOperation('error', 'Error generando XML desconsolidación', [
                'error' => $e->getMessage(),
                'transaction_id' => $transactionId,
                'titulo_madre_id' => $tituloMadre->id,
            ]);
            return null;
        }
    }

    /**
     * Generar XML de rectificación
     */
    private function generateRectificationXml(Shipment $tituloMadre, array $contenedores, array $titulosHijos, string $transactionId, string $originalReference, string $reason): ?string
    {
        try {
            $deconsolidationData = [
                'titulo_madre' => $tituloMadre,
                'contenedores' => $contenedores,
                'titulos_hijos' => $titulosHijos,
                'original_reference' => $originalReference,
                'rectification_reason' => $reason,
            ];

            $xmlContent = $this->xmlSerializer->createDeconsolidationRectificationXml($deconsolidationData, $transactionId);
            
            if ($xmlContent) {
                $this->logOperation('info', 'XML rectificación desconsolidación generado exitosamente', [
                    'xml_size_kb' => round(strlen($xmlContent) / 1024, 2),
                    'transaction_id' => $transactionId,
                    'original_reference' => $originalReference,
                ]);
            }

            return $xmlContent;

        } catch (Exception $e) {
            $this->logOperation('error', 'Error generando XML rectificación desconsolidación', [
                'error' => $e->getMessage(),
                'transaction_id' => $transactionId,
                'original_reference' => $originalReference,
            ]);
            return null;
        }
    }

    /**
     * Generar XML de eliminación
     */
    private function generateDeletionXml(string $transactionId, string $originalReference, string $reason): ?string
    {
        try {
            $deletionData = [
                'original_reference' => $originalReference,
                'deletion_reason' => $reason,
            ];

            $xmlContent = $this->xmlSerializer->createDeconsolidationDeletionXml($deletionData, $transactionId);
            
            if ($xmlContent) {
                $this->logOperation('info', 'XML eliminación desconsolidación generado exitosamente', [
                    'xml_size_kb' => round(strlen($xmlContent) / 1024, 2),
                    'transaction_id' => $transactionId,
                    'original_reference' => $originalReference,
                ]);
            }

            return $xmlContent;

        } catch (Exception $e) {
            $this->logOperation('error', 'Error generando XML eliminación desconsolidación', [
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

            // Determinar método SOAP según el tipo de operación
            $soapMethod = $this->getSoapMethodFromTransaction($transaction);

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
     * Obtener método SOAP según la transacción
     */
    private function getSoapMethodFromTransaction(WebserviceTransaction $transaction): string
    {
        $metadata = $transaction->additional_metadata;
        
        if ($metadata['is_deletion'] ?? false) {
            return 'EliminarTitulosDesconsolidador';
        }
        
        if ($metadata['is_rectification'] ?? false) {
            return 'RectificarTitulosDesconsolidador';
        }
        
        return 'RegistrarTitulosDesconsolidador';
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
                'Rol' => 'DESCONSOLIDADOR',
            ],
            'xmlData' => $xmlContent,
        ];
    }

    /**
     * Procesar respuesta exitosa
     */
    private function processSuccessResponse(WebserviceTransaction $transaction, array $soapResult): void
    {
        // Extraer referencia de desconsolidación de la respuesta (si está disponible)
        $deconsolidationReference = $this->extractDeconsolidationReference($soapResult['response_data'] ?? []);

        $transaction->update([
            'status' => 'success',
            'completed_at' => now(),
            'external_reference' => $deconsolidationReference,
        ]);

        // Crear registro de respuesta exitosa
        WebserviceResponse::create([
            'webservice_transaction_id' => $transaction->id,
            'response_code' => '200',
            'response_message' => 'Desconsolidación procesada exitosamente',
            'response_data' => $soapResult['response_data'] ?? [],
            'is_success' => true,
        ]);

        $this->logOperation('info', 'Respuesta exitosa procesada', [
            'transaction_id' => $transaction->id,
            'deconsolidation_reference' => $deconsolidationReference,
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
     * Extraer referencia de desconsolidación de la respuesta AFIP
     */
    private function extractDeconsolidationReference($responseData): ?string
    {
        // Implementación básica - en producción se debe parsear la respuesta correctamente
        if (is_array($responseData) && isset($responseData['DeconsolidationReference'])) {
            return $responseData['DeconsolidationReference'];
        }
        
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

        // URLs por defecto - Desconsolidados usa el mismo webservice que MIC/DTA
        $defaultUrls = [
            'testing' => 'https://wsaduhomoext.afip.gob.ar/DIAV2/wgesregsintia2/wgesregsintia2.asmx',
            'production' => 'https://webservicesadu.afip.gob.ar/DIAV2/wgesregsintia2/wgesregsintia2.asmx',
        ];

        return $defaultUrls[$this->config['environment']] ?? $defaultUrls['testing'];
    }

    /**
     * Obtener estadísticas de transacciones de desconsolidación de la empresa
     */
    public function getCompanyStatistics(): array
    {
        $stats = [
            'total_transactions' => 0,
            'successful_transactions' => 0,
            'error_transactions' => 0,
            'pending_transactions' => 0,
            'rectifications_count' => 0,
            'deletions_count' => 0,
            'success_rate' => 0.0,
            'average_response_time_ms' => 0,
            'last_successful_transaction' => null,
            'most_common_errors' => [],
        ];

        try {
            $transactions = WebserviceTransaction::forCompany($this->company->id)
                ->ofType('desconsolidado')
                ->forCountry('AR');

            $stats['total_transactions'] = $transactions->count();
            $stats['successful_transactions'] = $transactions->where('status', 'success')->count();
            $stats['error_transactions'] = $transactions->whereIn('status', ['error', 'expired'])->count();
            $stats['pending_transactions'] = $transactions->whereIn('status', ['pending', 'sending', 'retry'])->count();
            
            // Contar rectificaciones y eliminaciones
            $stats['rectifications_count'] = $transactions->where('additional_metadata->is_rectification', true)->count();
            $stats['deletions_count'] = $transactions->where('additional_metadata->is_deletion', true)->count();

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
     * Logging centralizado para el servicio
     */
    private function logOperation(string $level, string $message, array $context = [], string $category = 'general'): void
    {
        try {
            $context['service'] = 'ArgentinaDeconsolidationService';
            $context['company_id'] = $this->company->id;
            $context['company_name'] = $this->company->legal_name ?? $this->company->name;
            $context['user_id'] = $this->user->id;
            $context['timestamp'] = now()->toISOString();

            Log::$level($message, $context);

            // Log a webservice_logs si hay transaction_id
            $transactionId = $context['transaction_id'] ?? null;
            if ($transactionId && is_numeric($transactionId)) {
                \App\Models\WebserviceLog::create([
                    'transaction_id' => (int) $transactionId,
                    'level' => $level,
                    'category' => $category,
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
}