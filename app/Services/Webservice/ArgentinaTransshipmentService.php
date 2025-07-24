<?php

namespace App\Services\Webservice;

use App\Models\Company;
use App\Models\Voyage;
use App\Models\Vessel;
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
 * MÓDULO 4: WEBSERVICES ADUANA - ArgentinaTransshipmentService
 *
 * Servicio integrador completo para Transbordos Argentina AFIP.
 * Maneja barcazas, división de cargas, tracking de posición y contenedores vacíos.
 * 
 * Integra:
 * - SoapClientService: Cliente SOAP con URLs reales Argentina
 * - CertificateManagerService: Gestión certificados .p12
 * - XmlSerializerService: Generación XML según especificación AFIP
 * 
 * Funcionalidades:
 * - Registro de transbordos con barcazas (RegistrarTransbordo)
 * - Títulos de transporte contenedores vacíos (TitTransContVacioReg)
 * - Actualización de posición de barcazas (ActualizarPosicion)
 * - Validación completa pre-envío usando datos reales del sistema
 * - Generación de XML con datos de PARANA (barcazas, contenedores reales)
 * - Envío SOAP al webservice Argentina con autenticación
 * - Procesamiento de respuestas y actualización de estados
 * - Sistema completo de logs y auditoría de transacciones
 * - Manejo de errores y reintentos automáticos
 * 
 * Conceptos de Transbordo:
 * - **Barcaza**: Embarcación para transporte fluvial de contenedores
 * - **División de Cargas**: Separación de contenedores en diferentes barcazas
 * - **Tracking**: Seguimiento de posición GPS de barcazas en ruta
 * - **Contenedores Vacíos**: Gestión específica de contenedores sin carga
 * 
 * Datos reales soportados:
 * - Empresas: MAERSK LINE ARGENTINA S.A. (CUIT, certificados .p12)
 * - Barcazas: BARCAZA-01, BARCAZA-02 con capacidades reales
 * - Rutas: Buenos Aires → Asunción, Rosario → Montevideo
 * - Contenedores: 20GP, 40HC con seguimiento individual
 * - Posiciones: Coordenadas GPS, puntos de control fluvial
 */
class ArgentinaTransshipmentService
{
    private Company $company;
    private User $user;
    private SoapClientService $soapClient;
    private CertificateManagerService $certificateManager;
    private XmlSerializerService $xmlSerializer;
    private array $config;

    /**
     * Configuración específica para Transbordos Argentina
     */
    private const TRANSSHIPMENT_CONFIG = [
        'webservice_type' => 'transbordo',
        'country' => 'AR',
        'soap_action' => 'Ar.Gob.Afip.Dga.wgesregsintia2/RegistrarTransbordo',
        'environment' => 'testing', // Por defecto testing, se puede cambiar
        'max_retries' => 3,
        'retry_intervals' => [30, 120, 300], // 30s, 2min, 5min
        'timeout_seconds' => 60,
        'require_certificate' => true,
        'validate_xml_structure' => true,
        'methods' => ['RegistrarTransbordo', 'ActualizarPosicion', 'TitTransContVacioReg'],
        'require_company_role' => 'Transbordos',
        'max_barges_per_transaction' => 10,
        'max_containers_per_barge' => 50,
    ];

    /**
     * Códigos específicos para transporte fluvial
     */
    private const RIVER_TRANSPORT_CODES = [
        'via_transporte' => 8, // Hidrovía según EDIFACT 8067
        'tipo_embarcacion' => [
            'barge' => 'BAR', // Barcaza
            'tugboat' => 'EMP', // Empujador/Remolcador
            'convoy' => 'CON', // Convoy de barcazas
        ],
        'estado_contenedor' => [
            'full' => 'LLENO',
            'empty' => 'VACIO',
            'damaged' => 'AVERIADO',
        ],
        'tipo_carga' => [
            'general' => 'GRAL',
            'granel' => 'GRAN',
            'liquida' => 'LIQ',
            'peligrosa' => 'PELIG',
        ],
        'puntos_control' => [
            'ARBUE' => 'Puerto Buenos Aires',
            'ARROS' => 'Puerto Rosario',
            'PYASU' => 'Puerto Asunción',
            'PYTVT' => 'Terminal Villeta',
            'UYMON' => 'Puerto Montevideo',
        ],
    ];

    public function __construct(Company $company, User $user, array $config = [])
    {
        $this->company = $company;
        $this->user = $user;
        $this->config = array_merge(self::TRANSSHIPMENT_CONFIG, $config);

        // Inicializar servicios integrados
        $this->soapClient = new SoapClientService($company);
        $this->certificateManager = new CertificateManagerService($company);
        $this->xmlSerializer = new XmlSerializerService($company);

        $this->logOperation('info', 'ArgentinaTransshipmentService inicializado', [
            'company_id' => $company->id,
            'company_name' => $company->legal_name,
            'user_id' => $user->id,
            'environment' => $this->config['environment'],
        ]);
    }

    /**
     * Registrar transbordo con división de cargas en barcazas
     * 
     * @param array $bargeData Datos de las barcazas: [['barge_id' => 'BARCAZA-01', 'containers' => [...], 'route' => [...]]]
     * @param Voyage $voyage Viaje asociado al transbordo
     */
    public function registerTransshipment(array $bargeData, Voyage $voyage): array
    {
        $result = [
            'success' => false,
            'transaction_id' => null,
            'transshipment_reference' => null,
            'barge_references' => [],
            'response_data' => null,
            'errors' => [],
            'warnings' => [],
        ];

        DB::beginTransaction();

        try {
            $this->logOperation('info', 'Iniciando registro de transbordo', [
                'voyage_id' => $voyage->id,
                'voyage_number' => $voyage->voyage_number,
                'barges_count' => count($bargeData),
                'total_containers' => array_sum(array_column($bargeData, 'containers_count')),
            ]);

            // 1. Validaciones integrales pre-envío
            $validation = $this->validateForTransshipment($bargeData, $voyage);
            if (!$validation['is_valid']) {
                $result['errors'] = $validation['errors'];
                $result['warnings'] = $validation['warnings'];
                return $result;
            }

            // 2. Crear transacción en base de datos
            $transaction = $this->createTransshipmentTransaction($bargeData, $voyage);
            $result['transaction_id'] = $transaction->id;

            // 3. Generar XML usando datos reales del sistema
            $xmlContent = $this->generateTransshipmentXml($bargeData, $voyage, $transaction->transaction_id);
            if (!$xmlContent) {
                throw new Exception('No se pudo generar XML para transbordo');
            }

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
                $result['transshipment_reference'] = $soapResult['transshipment_reference'] ?? null;
                $result['barge_references'] = $soapResult['barge_references'] ?? [];
                $result['response_data'] = $soapResult['response_data'];
            } else {
                $this->processErrorResponse($transaction, $soapResult);
                $result['errors'][] = $soapResult['error_message'];
            }

            DB::commit();

            $this->logOperation('info', 'Registro de transbordo completado', [
                'transaction_id' => $transaction->id,
                'success' => $result['success'],
                'transshipment_reference' => $result['transshipment_reference'],
                'response_time_ms' => $soapResult['response_time_ms'] ?? null,
            ]);

            return $result;

        } catch (Exception $e) {
            DB::rollback();

            $this->logOperation('error', 'Error en registro de transbordo', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'voyage_id' => $voyage->id,
                'transaction_id' => $result['transaction_id'],
            ]);

            $result['errors'][] = $e->getMessage();
            return $result;
        }
    }

    /**
     * Registrar títulos de transporte para contenedores vacíos
     * 
     * @param array $emptyContainers Lista de contenedores vacíos: [['container_id' => 'CONT001', 'barge_id' => 'BARCAZA-01']]
     * @param string $route Ruta del transporte (ej: 'ARBUE-PYASU')
     */
    public function registerEmptyContainers(array $emptyContainers, string $route): array
    {
        $result = [
            'success' => false,
            'transaction_id' => null,
            'transport_title_reference' => null,
            'response_data' => null,
            'errors' => [],
            'warnings' => [],
        ];

        DB::beginTransaction();

        try {
            $this->logOperation('info', 'Iniciando registro de contenedores vacíos', [
                'containers_count' => count($emptyContainers),
                'route' => $route,
            ]);

            // 1. Validaciones específicas para contenedores vacíos
            $validation = $this->validateForEmptyContainers($emptyContainers, $route);
            if (!$validation['is_valid']) {
                $result['errors'] = $validation['errors'];
                return $result;
            }

            // 2. Crear transacción en base de datos
            $transaction = $this->createEmptyContainersTransaction($emptyContainers, $route);
            $result['transaction_id'] = $transaction->id;

            // 3. Generar XML específico para contenedores vacíos
            $xmlContent = $this->generateEmptyContainersXml($emptyContainers, $route, $transaction->transaction_id);
            if (!$xmlContent) {
                throw new Exception('No se pudo generar XML para contenedores vacíos');
            }

            // 4. Cambiar SOAPAction para contenedores vacíos
            $originalSoapAction = $this->config['soap_action'];
            $this->config['soap_action'] = 'Ar.Gob.Afip.Dga.wgesregsintia2/TitTransContVacioReg';

            // 5. Preparar cliente SOAP y enviar
            $soapClient = $this->prepareSoapClient();
            $soapResult = $this->sendSoapRequest($transaction, $soapClient, $xmlContent);

            // 6. Restaurar SOAPAction original
            $this->config['soap_action'] = $originalSoapAction;

            // 7. Procesar respuesta
            if ($soapResult['success']) {
                $this->processSuccessResponse($transaction, $soapResult);
                $result['success'] = true;
                $result['transport_title_reference'] = $soapResult['transport_title_reference'] ?? null;
                $result['response_data'] = $soapResult['response_data'];
            } else {
                $this->processErrorResponse($transaction, $soapResult);
                $result['errors'][] = $soapResult['error_message'];
            }

            DB::commit();

            $this->logOperation('info', 'Registro de contenedores vacíos completado', [
                'transaction_id' => $transaction->id,
                'success' => $result['success'],
                'transport_title_reference' => $result['transport_title_reference'],
            ]);

            return $result;

        } catch (Exception $e) {
            DB::rollback();

            $this->logOperation('error', 'Error en registro de contenedores vacíos', [
                'error' => $e->getMessage(),
                'containers_count' => count($emptyContainers),
                'route' => $route,
            ]);

            $result['errors'][] = $e->getMessage();
            return $result;
        }
    }

    /**
     * Actualizar posición de barcazas en tránsito
     * 
     * @param array $positionUpdates Actualizaciones de posición: [['barge_id' => 'BARCAZA-01', 'lat' => -34.123, 'lng' => -58.456, 'timestamp' => '...']]
     */
    public function updateBargePositions(array $positionUpdates): array
    {
        $result = [
            'success' => false,
            'transaction_id' => null,
            'position_references' => [],
            'response_data' => null,
            'errors' => [],
            'warnings' => [],
        ];

        DB::beginTransaction();

        try {
            $this->logOperation('info', 'Iniciando actualización de posiciones', [
                'barges_count' => count($positionUpdates),
                'positions' => $positionUpdates,
            ]);

            // 1. Validaciones para actualización de posición
            $validation = $this->validateForPositionUpdate($positionUpdates);
            if (!$validation['is_valid']) {
                $result['errors'] = $validation['errors'];
                return $result;
            }

            // 2. Crear transacción en base de datos
            $transaction = $this->createPositionUpdateTransaction($positionUpdates);
            $result['transaction_id'] = $transaction->id;

            // 3. Generar XML para actualización de posición
            $xmlContent = $this->generatePositionUpdateXml($positionUpdates, $transaction->transaction_id);
            if (!$xmlContent) {
                throw new Exception('No se pudo generar XML para actualización de posición');
            }

            // 4. Cambiar SOAPAction para actualización de posición
            $originalSoapAction = $this->config['soap_action'];
            $this->config['soap_action'] = 'Ar.Gob.Afip.Dga.wgesregsintia2/ActualizarPosicion';

            // 5. Preparar cliente SOAP y enviar
            $soapClient = $this->prepareSoapClient();
            $soapResult = $this->sendSoapRequest($transaction, $soapClient, $xmlContent);

            // 6. Restaurar SOAPAction original
            $this->config['soap_action'] = $originalSoapAction;

            // 7. Procesar respuesta
            if ($soapResult['success']) {
                $this->processSuccessResponse($transaction, $soapResult);
                $result['success'] = true;
                $result['position_references'] = $soapResult['position_references'] ?? [];
                $result['response_data'] = $soapResult['response_data'];
            } else {
                $this->processErrorResponse($transaction, $soapResult);
                $result['errors'][] = $soapResult['error_message'];
            }

            DB::commit();

            $this->logOperation('info', 'Actualización de posiciones completada', [
                'transaction_id' => $transaction->id,
                'success' => $result['success'],
                'updated_barges' => count($positionUpdates),
            ]);

            return $result;

        } catch (Exception $e) {
            DB::rollback();

            $this->logOperation('error', 'Error en actualización de posiciones', [
                'error' => $e->getMessage(),
                'barges_count' => count($positionUpdates),
            ]);

            $result['errors'][] = $e->getMessage();
            return $result;
        }
    }

    /**
     * Validaciones integrales para transbordo
     */
    private function validateForTransshipment(array $bargeData, Voyage $voyage): array
    {
        $validation = [
            'is_valid' => false,
            'errors' => [],
            'warnings' => [],
        ];

        // 1. Validar voyage
        if (!$voyage || !$voyage->id) {
            $validation['errors'][] = 'Viaje no válido o no encontrado';
        }

        if (!$voyage->voyage_number) {
            $validation['errors'][] = 'Viaje debe tener número válido';
        }

        // 2. Validar datos de barcazas
        if (empty($bargeData)) {
            $validation['errors'][] = 'Debe especificar al menos una barcaza para el transbordo';
        }

        if (count($bargeData) > $this->config['max_barges_per_transaction']) {
            $validation['errors'][] = "Máximo {$this->config['max_barges_per_transaction']} barcazas por transacción";
        }

        foreach ($bargeData as $index => $barge) {
            if (empty($barge['barge_id'])) {
                $validation['errors'][] = "Barcaza #{$index} debe tener ID válido";
            }

            if (empty($barge['containers']) || !is_array($barge['containers'])) {
                $validation['errors'][] = "Barcaza {$barge['barge_id']} debe tener lista de contenedores";
            } else {
                if (count($barge['containers']) > $this->config['max_containers_per_barge']) {
                    $validation['warnings'][] = "Barcaza {$barge['barge_id']} tiene más de {$this->config['max_containers_per_barge']} contenedores";
                }
            }

            if (empty($barge['route'])) {
                $validation['errors'][] = "Barcaza {$barge['barge_id']} debe tener ruta definida";
            }

            // Validar formato de ruta
            if (isset($barge['route']) && !$this->isValidRoute($barge['route'])) {
                $validation['errors'][] = "Ruta '{$barge['route']}' no es válida para barcaza {$barge['barge_id']}";
            }
        }

        // 3. Validar empresa
        if (!$this->company->tax_id || strlen(preg_replace('/[^0-9]/', '', $this->company->tax_id)) !== 11) {
            $validation['errors'][] = 'CUIT de empresa inválido para Argentina';
        }

        // 4. Validar rol de empresa
        if (!$this->company->hasRole('Transbordos')) {
            $validation['errors'][] = 'Empresa debe tener rol "Transbordos" para realizar transbordos';
        }

        // 5. Validar certificado de empresa
        if (!$this->company->certificate_path || !$this->company->certificate_password) {
            $validation['errors'][] = 'Empresa debe tener certificado .p12 configurado';
        }

        // 6. Validar país de operación
        if ($this->company->country !== 'AR') {
            $validation['errors'][] = 'Transbordos solo para empresas argentinas';
        }

        // 7. Validar capacidades
        $totalContainers = array_sum(array_map(function($barge) {
            return count($barge['containers'] ?? []);
        }, $bargeData));

        if ($voyage->shipments()->sum('containers_loaded') < $totalContainers) {
            $validation['warnings'][] = "Total contenedores en barcazas ({$totalContainers}) excede contenedores cargados en viaje";
        }

        $validation['is_valid'] = empty($validation['errors']);

        $this->logOperation($validation['is_valid'] ? 'info' : 'warning', 'Validación transbordo completada', $validation);

        return $validation;
    }

    /**
     * Validaciones para contenedores vacíos
     */
    private function validateForEmptyContainers(array $emptyContainers, string $route): array
    {
        $validation = [
            'is_valid' => false,
            'errors' => [],
            'warnings' => [],
        ];

        // 1. Validar contenedores
        if (empty($emptyContainers)) {
            $validation['errors'][] = 'Debe especificar al menos un contenedor vacío';
        }

        foreach ($emptyContainers as $index => $container) {
            if (empty($container['container_id'])) {
                $validation['errors'][] = "Contenedor #{$index} debe tener ID válido";
            }

            if (empty($container['barge_id'])) {
                $validation['errors'][] = "Contenedor {$container['container_id']} debe especificar barcaza";
            }
        }

        // 2. Validar ruta
        if (!$this->isValidRoute($route)) {
            $validation['errors'][] = "Ruta '{$route}' no es válida";
        }

        // 3. Validar empresa (mismas validaciones que transbordo)
        if (!$this->company->hasRole('Transbordos')) {
            $validation['errors'][] = 'Empresa debe tener rol "Transbordos"';
        }

        if ($this->company->country !== 'AR') {
            $validation['errors'][] = 'Contenedores vacíos solo para empresas argentinas';
        }

        $validation['is_valid'] = empty($validation['errors']);

        return $validation;
    }

    /**
     * Validaciones para actualización de posición
     */
    private function validateForPositionUpdate(array $positionUpdates): array
    {
        $validation = [
            'is_valid' => false,
            'errors' => [],
            'warnings' => [],
        ];

        if (empty($positionUpdates)) {
            $validation['errors'][] = 'Debe especificar al menos una actualización de posición';
        }

        foreach ($positionUpdates as $index => $update) {
            if (empty($update['barge_id'])) {
                $validation['errors'][] = "Actualización #{$index} debe especificar barcaza";
            }

            if (!isset($update['lat']) || !is_numeric($update['lat']) || $update['lat'] < -90 || $update['lat'] > 90) {
                $validation['errors'][] = "Latitud inválida para barcaza {$update['barge_id']}";
            }

            if (!isset($update['lng']) || !is_numeric($update['lng']) || $update['lng'] < -180 || $update['lng'] > 180) {
                $validation['errors'][] = "Longitud inválida para barcaza {$update['barge_id']}";
            }

            if (empty($update['timestamp'])) {
                $validation['errors'][] = "Timestamp requerido para barcaza {$update['barge_id']}";
            }
        }

        $validation['is_valid'] = empty($validation['errors']);

        return $validation;
    }

    /**
     * Validar formato de ruta
     */
    private function isValidRoute(string $route): bool
    {
        // Formato esperado: ORIGEN-DESTINO (ej: ARBUE-PYASU)
        $pattern = '/^[A-Z]{5}-[A-Z]{5}$/';
        
        if (!preg_match($pattern, $route)) {
            return false;
        }

        $parts = explode('-', $route);
        $validPorts = array_keys(self::RIVER_TRANSPORT_CODES['puntos_control']);
        
        return in_array($parts[0], $validPorts) && in_array($parts[1], $validPorts);
    }

    /**
     * Crear transacción para transbordo
     */
    private function createTransshipmentTransaction(array $bargeData, Voyage $voyage): WebserviceTransaction
    {
        $companyCode = substr(preg_replace('/[^0-9]/', '', $this->company->tax_id), -4);
        $dateCode = now()->format('Ymd');
        $sequence = WebserviceTransaction::where('company_id', $this->company->id)
            ->whereDate('created_at', today())
            ->count() + 1;
        
        $transactionId = sprintf('TRANSB-%s-%s-%03d', $companyCode, $dateCode, $sequence);

        $webserviceUrl = $this->getWebserviceUrl();
        $totalContainers = array_sum(array_map(function($barge) {
            return count($barge['containers'] ?? []);
        }, $bargeData));

        $transaction = WebserviceTransaction::create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'shipment_id' => null, // Transbordos son a nivel voyage
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
            
            'total_weight_kg' => 0, // Se calculará con los contenedores
            'container_count' => $totalContainers,
            'currency_code' => 'USD',
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
            
            'additional_metadata' => [
                'voyage_number' => $voyage->voyage_number,
                'barges_count' => count($bargeData),
                'barge_data' => $bargeData,
                'method_used' => 'RegistrarTransbordo',
                'company_role' => 'Transbordos',
                'transport_type' => 'river_transport',
            ],
        ]);

        $this->logOperation('info', 'Transacción transbordo creada', [
            'transaction_id' => $transaction->id,
            'internal_transaction_id' => $transactionId,
            'barges_count' => count($bargeData),
            'total_containers' => $totalContainers,
        ]);

        return $transaction;
    }

    /**
     * Crear transacción para contenedores vacíos
     */
    private function createEmptyContainersTransaction(array $emptyContainers, string $route): WebserviceTransaction
    {
        $companyCode = substr(preg_replace('/[^0-9]/', '', $this->company->tax_id), -4);
        $dateCode = now()->format('Ymd');
        $sequence = WebserviceTransaction::where('company_id', $this->company->id)
            ->whereDate('created_at', today())
            ->count() + 1;
        
        $transactionId = sprintf('EMPTY-%s-%s-%03d', $companyCode, $dateCode, $sequence);

        $transaction = WebserviceTransaction::create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'shipment_id' => null,
            'voyage_id' => null,
            'transaction_id' => $transactionId,
            'webservice_type' => $this->config['webservice_type'],
            'country' => $this->config['country'],
            'webservice_url' => $this->getWebserviceUrl(),
            'soap_action' => 'Ar.Gob.Afip.Dga.wgesregsintia2/TitTransContVacioReg',
            'status' => 'pending',
            'max_retries' => $this->config['max_retries'],
            'retry_intervals' => $this->config['retry_intervals'],
            'environment' => $this->config['environment'],
            'certificate_used' => $this->company->certificate_alias,
            
            'total_weight_kg' => 0, // Contenedores vacíos
            'container_count' => count($emptyContainers),
            'currency_code' => 'USD',
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
            
            'additional_metadata' => [
                'empty_containers' => $emptyContainers,
                'route' => $route,
                'method_used' => 'TitTransContVacioReg',
                'container_type' => 'empty',
            ],
        ]);

        return $transaction;
    }

    /**
     * Crear transacción para actualización de posición
     */
    private function createPositionUpdateTransaction(array $positionUpdates): WebserviceTransaction
    {
        $companyCode = substr(preg_replace('/[^0-9]/', '', $this->company->tax_id), -4);
        $dateCode = now()->format('Ymd');
        $sequence = WebserviceTransaction::where('company_id', $this->company->id)
            ->whereDate('created_at', today())
            ->count() + 1;
        
        $transactionId = sprintf('POS-%s-%s-%03d', $companyCode, $dateCode, $sequence);

        $transaction = WebserviceTransaction::create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'shipment_id' => null,
            'voyage_id' => null,
            'transaction_id' => $transactionId,
            'webservice_type' => $this->config['webservice_type'],
            'country' => $this->config['country'],
            'webservice_url' => $this->getWebserviceUrl(),
            'soap_action' => 'Ar.Gob.Afip.Dga.wgesregsintia2/ActualizarPosicion',
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
                'position_updates' => $positionUpdates,
                'barges_count' => count($positionUpdates),
                'method_used' => 'ActualizarPosicion',
            ],
        ]);

        return $transaction;
    }

    /**
     * Generar XML usando el XmlSerializerService
     */
    private function generateTransshipmentXml(array $bargeData, Voyage $voyage, string $transactionId): ?string
    {
        try {
            $transshipmentData = [
                'barge_data' => $bargeData,
                'voyage' => $voyage,
            ];

            $xmlContent = $this->xmlSerializer->createTransshipmentXml($transshipmentData, $transactionId);
            
            if ($xmlContent) {
                $this->logOperation('info', 'XML transbordo generado exitosamente', [
                    'xml_size_kb' => round(strlen($xmlContent) / 1024, 2),
                    'transaction_id' => $transactionId,
                    'barges_count' => count($bargeData),
                ]);
            }

            return $xmlContent;

        } catch (Exception $e) {
            $this->logOperation('error', 'Error generando XML transbordo', [
                'error' => $e->getMessage(),
                'transaction_id' => $transactionId,
                'voyage_id' => $voyage->id,
            ]);
            return null;
        }
    }

    /**
     * Generar XML para contenedores vacíos
     */
    private function generateEmptyContainersXml(array $emptyContainers, string $route, string $transactionId): ?string
    {
        try {
            $emptyContainersData = [
                'empty_containers' => $emptyContainers,
                'route' => $route,
            ];

            $xmlContent = $this->xmlSerializer->createEmptyContainersXml($emptyContainersData, $transactionId);
            
            if ($xmlContent) {
                $this->logOperation('info', 'XML contenedores vacíos generado exitosamente', [
                    'xml_size_kb' => round(strlen($xmlContent) / 1024, 2),
                    'transaction_id' => $transactionId,
                    'containers_count' => count($emptyContainers),
                ]);
            }

            return $xmlContent;

        } catch (Exception $e) {
            $this->logOperation('error', 'Error generando XML contenedores vacíos', [
                'error' => $e->getMessage(),
                'transaction_id' => $transactionId,
                'route' => $route,
            ]);
            return null;
        }
    }

    /**
     * Generar XML para actualización de posición
     */
    private function generatePositionUpdateXml(array $positionUpdates, string $transactionId): ?string
    {
        try {
            $xmlContent = $this->xmlSerializer->createPositionUpdateXml($positionUpdates, $transactionId);
            
            if ($xmlContent) {
                $this->logOperation('info', 'XML actualización posición generado exitosamente', [
                    'xml_size_kb' => round(strlen($xmlContent) / 1024, 2),
                    'transaction_id' => $transactionId,
                    'barges_count' => count($positionUpdates),
                ]);
            }

            return $xmlContent;

        } catch (Exception $e) {
            $this->logOperation('error', 'Error generando XML actualización posición', [
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
        return $transaction->additional_metadata['method_used'] ?? 'RegistrarTransbordo';
    }

    /**
     * Extraer parámetros SOAP del XML generado
     */
    private function extractSoapParameters(string $xmlContent): array
    {
        return [
            'argWSAutenticacionEmpresa' => [
                'CuitEmpresaConectada' => preg_replace('/[^0-9]/', '', $this->company->tax_id),
                'TipoAgente' => 'ATA',
                'Rol' => 'TRANSBORDO',
            ],
            'xmlData' => $xmlContent,
        ];
    }

    /**
     * Procesar respuesta exitosa
     */
    private function processSuccessResponse(WebserviceTransaction $transaction, array $soapResult): void
    {
        // Extraer referencias de la respuesta
        $references = $this->extractTransshipmentReferences($soapResult['response_data'] ?? []);

        $transaction->update([
            'status' => 'success',
            'completed_at' => now(),
            'external_reference' => $references['main_reference'] ?? null,
        ]);

        // Crear registro de respuesta exitosa
        WebserviceResponse::create([
            'webservice_transaction_id' => $transaction->id,
            'response_code' => '200',
            'response_message' => 'Transbordo procesado exitosamente',
            'response_data' => $soapResult['response_data'] ?? [],
            'is_success' => true,
        ]);

        $this->logOperation('info', 'Respuesta exitosa procesada', [
            'transaction_id' => $transaction->id,
            'references' => $references,
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
     * Extraer referencias de transbordo de la respuesta AFIP
     */
    private function extractTransshipmentReferences($responseData): array
    {
        $references = [
            'main_reference' => null,
            'barge_references' => [],
            'position_references' => [],
        ];

        if (is_array($responseData)) {
            $references['main_reference'] = $responseData['TransshipmentReference'] ?? null;
            $references['barge_references'] = $responseData['BargeReferences'] ?? [];
            $references['position_references'] = $responseData['PositionReferences'] ?? [];
        }
        
        return $references;
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

        // URLs por defecto - Transbordos usa el mismo webservice que MIC/DTA
        $defaultUrls = [
            'testing' => 'https://wsaduhomoext.afip.gob.ar/DIAV2/wgesregsintia2/wgesregsintia2.asmx',
            'production' => 'https://webservicesadu.afip.gob.ar/DIAV2/wgesregsintia2/wgesregsintia2.asmx',
        ];

        return $defaultUrls[$this->config['environment']] ?? $defaultUrls['testing'];
    }

    /**
     * Obtener estadísticas de transacciones de transbordo de la empresa
     */
    public function getCompanyStatistics(): array
    {
        $stats = [
            'total_transactions' => 0,
            'successful_transactions' => 0,
            'error_transactions' => 0,
            'pending_transactions' => 0,
            'empty_containers_transactions' => 0,
            'position_updates_count' => 0,
            'total_barges_handled' => 0,
            'success_rate' => 0.0,
            'average_response_time_ms' => 0,
            'last_successful_transaction' => null,
            'active_routes' => [],
        ];

        try {
            $transactions = WebserviceTransaction::forCompany($this->company->id)
                ->ofType('transbordo')
                ->forCountry('AR');

            $stats['total_transactions'] = $transactions->count();
            $stats['successful_transactions'] = $transactions->where('status', 'success')->count();
            $stats['error_transactions'] = $transactions->whereIn('status', ['error', 'expired'])->count();
            $stats['pending_transactions'] = $transactions->whereIn('status', ['pending', 'sending', 'retry'])->count();
            
            // Contar transacciones específicas
            $stats['empty_containers_transactions'] = $transactions->where('additional_metadata->method_used', 'TitTransContVacioReg')->count();
            $stats['position_updates_count'] = $transactions->where('additional_metadata->method_used', 'ActualizarPosicion')->count();

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
            'service' => 'ArgentinaTransshipmentService',
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
     * Obtener códigos de transporte fluvial
     */
    public static function getRiverTransportCodes(): array
    {
        return self::RIVER_TRANSPORT_CODES;
    }

    /**
     * Obtener puntos de control disponibles
     */
    public static function getControlPoints(): array
    {
        return self::RIVER_TRANSPORT_CODES['puntos_control'];
    }

    /**
     * Validar coordenadas GPS
     */
    public function validateGpsCoordinates(float $lat, float $lng): bool
    {
        return $lat >= -90 && $lat <= 90 && $lng >= -180 && $lng <= 180;
    }

    /**
     * Calcular distancia entre dos puntos GPS (fórmula haversine)
     */
    public function calculateDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371; // Radio de la Tierra en km

        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLng / 2) * sin($dLng / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    /**
     * Obtener historial de posiciones de una barcaza
     */
    public function getBargePositionHistory(string $bargeId, int $days = 7): array
    {
        try {
            // Buscar transacciones de actualización de posición para la barcaza
            $transactions = WebserviceTransaction::forCompany($this->company->id)
                ->ofType('transbordo')
                ->where('additional_metadata->method_used', 'ActualizarPosicion')
                ->where('status', 'success')
                ->where('created_at', '>=', now()->subDays($days))
                ->orderBy('created_at', 'desc')
                ->get();

            $positions = [];

            foreach ($transactions as $transaction) {
                $positionUpdates = $transaction->additional_metadata['position_updates'] ?? [];
                
                foreach ($positionUpdates as $update) {
                    if ($update['barge_id'] === $bargeId) {
                        $positions[] = [
                            'timestamp' => $update['timestamp'],
                            'lat' => $update['lat'],
                            'lng' => $update['lng'],
                            'transaction_id' => $transaction->id,
                            'created_at' => $transaction->created_at,
                        ];
                    }
                }
            }

            return $positions;

        } catch (Exception $e) {
            $this->logOperation('error', 'Error obteniendo historial de posiciones', [
                'error' => $e->getMessage(),
                'barge_id' => $bargeId,
                'days' => $days,
            ]);
            return [];
        }
    }

    /**
     * Obtener barcazas activas de la empresa
     */
    public function getActiveBarges(): array
    {
        try {
            // Obtener todas las transacciones de transbordo exitosas de los últimos 30 días
            $transactions = WebserviceTransaction::forCompany($this->company->id)
                ->ofType('transbordo')
                ->where('status', 'success')
                ->where('created_at', '>=', now()->subDays(30))
                ->get();

            $barges = [];

            foreach ($transactions as $transaction) {
                $metadata = $transaction->additional_metadata;
                
                // Si es un registro de transbordo
                if (isset($metadata['barge_data'])) {
                    foreach ($metadata['barge_data'] as $barge) {
                        $bargeId = $barge['barge_id'];
                        
                        if (!isset($barges[$bargeId])) {
                            $barges[$bargeId] = [
                                'barge_id' => $bargeId,
                                'last_activity' => $transaction->created_at,
                                'total_containers' => 0,
                                'routes' => [],
                                'transactions_count' => 0,
                            ];
                        }

                        $barges[$bargeId]['total_containers'] += count($barge['containers'] ?? []);
                        $barges[$bargeId]['routes'][] = $barge['route'] ?? 'N/A';
                        $barges[$bargeId]['transactions_count']++;
                        
                        // Actualizar última actividad si es más reciente
                        if ($transaction->created_at > $barges[$bargeId]['last_activity']) {
                            $barges[$bargeId]['last_activity'] = $transaction->created_at;
                        }
                    }
                }
            }

            // Limpiar rutas duplicadas
            foreach ($barges as &$barge) {
                $barge['routes'] = array_unique($barge['routes']);
            }

            return array_values($barges);

        } catch (Exception $e) {
            $this->logOperation('error', 'Error obteniendo barcazas activas', [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Obtener resumen de rutas utilizadas
     */
    public function getRouteSummary(): array
    {
        try {
            $transactions = WebserviceTransaction::forCompany($this->company->id)
                ->ofType('transbordo')
                ->where('status', 'success')
                ->where('created_at', '>=', now()->subDays(90)) // Últimos 3 meses
                ->get();

            $routes = [];

            foreach ($transactions as $transaction) {
                $metadata = $transaction->additional_metadata;
                
                // Procesar rutas de transbordo
                if (isset($metadata['barge_data'])) {
                    foreach ($metadata['barge_data'] as $barge) {
                        $route = $barge['route'] ?? null;
                        if ($route) {
                            if (!isset($routes[$route])) {
                                $routes[$route] = [
                                    'route' => $route,
                                    'origin' => $this->getPortName(explode('-', $route)[0] ?? ''),
                                    'destination' => $this->getPortName(explode('-', $route)[1] ?? ''),
                                    'usage_count' => 0,
                                    'containers_total' => 0,
                                    'barges_used' => [],
                                    'last_used' => null,
                                ];
                            }

                            $routes[$route]['usage_count']++;
                            $routes[$route]['containers_total'] += count($barge['containers'] ?? []);
                            $routes[$route]['barges_used'][] = $barge['barge_id'];
                            
                            if (!$routes[$route]['last_used'] || $transaction->created_at > $routes[$route]['last_used']) {
                                $routes[$route]['last_used'] = $transaction->created_at;
                            }
                        }
                    }
                }

                // Procesar rutas de contenedores vacíos
                if (isset($metadata['route'])) {
                    $route = $metadata['route'];
                    if (!isset($routes[$route])) {
                        $routes[$route] = [
                            'route' => $route,
                            'origin' => $this->getPortName(explode('-', $route)[0] ?? ''),
                            'destination' => $this->getPortName(explode('-', $route)[1] ?? ''),
                            'usage_count' => 0,
                            'containers_total' => 0,
                            'barges_used' => [],
                            'last_used' => null,
                        ];
                    }

                    $routes[$route]['usage_count']++;
                    $routes[$route]['containers_total'] += count($metadata['empty_containers'] ?? []);
                    
                    if (!$routes[$route]['last_used'] || $transaction->created_at > $routes[$route]['last_used']) {
                        $routes[$route]['last_used'] = $transaction->created_at;
                    }
                }
            }

            // Limpiar barcazas duplicadas y ordenar por uso
            foreach ($routes as &$route) {
                $route['barges_used'] = array_unique($route['barges_used']);
                $route['barges_count'] = count($route['barges_used']);
            }

            // Ordenar por frecuencia de uso
            uasort($routes, function($a, $b) {
                return $b['usage_count'] - $a['usage_count'];
            });

            return array_values($routes);

        } catch (Exception $e) {
            $this->logOperation('error', 'Error obteniendo resumen de rutas', [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Obtener nombre descriptivo de puerto por código
     */
    private function getPortName(string $portCode): string
    {
        return self::RIVER_TRANSPORT_CODES['puntos_control'][$portCode] ?? $portCode;
    }

    /**
     * Generar reporte de actividad de transbordos
     */
    public function generateActivityReport(int $days = 30): array
    {
        try {
            $startDate = now()->subDays($days);
            
            $transactions = WebserviceTransaction::forCompany($this->company->id)
                ->ofType('transbordo')
                ->where('created_at', '>=', $startDate)
                ->get();

            $report = [
                'period' => [
                    'start_date' => $startDate->toDateString(),
                    'end_date' => now()->toDateString(),
                    'days' => $days,
                ],
                'summary' => [
                    'total_transactions' => $transactions->count(),
                    'successful_transactions' => $transactions->where('status', 'success')->count(),
                    'failed_transactions' => $transactions->whereIn('status', ['error', 'expired'])->count(),
                    'pending_transactions' => $transactions->whereIn('status', ['pending', 'sending', 'retry'])->count(),
                ],
                'by_method' => [
                    'transshipments' => $transactions->where('additional_metadata->method_used', 'RegistrarTransbordo')->count(),
                    'empty_containers' => $transactions->where('additional_metadata->method_used', 'TitTransContVacioReg')->count(),
                    'position_updates' => $transactions->where('additional_metadata->method_used', 'ActualizarPosicion')->count(),
                ],
                'containers' => [
                    'total_handled' => 0,
                    'empty_containers' => 0,
                    'loaded_containers' => 0,
                ],
                'barges' => [
                    'unique_barges_used' => 0,
                    'most_active_barge' => null,
                ],
                'routes' => [
                    'unique_routes_used' => 0,
                    'most_used_route' => null,
                ],
                'performance' => [
                    'average_response_time_ms' => 0,
                    'success_rate' => 0.0,
                ],
            ];

            // Calcular métricas detalladas
            $allBarges = [];
            $allRoutes = [];
            $totalContainers = 0;
            $emptyContainers = 0;

            foreach ($transactions as $transaction) {
                $metadata = $transaction->additional_metadata;
                
                // Procesar transbordo
                if (isset($metadata['barge_data'])) {
                    foreach ($metadata['barge_data'] as $barge) {
                        $allBarges[] = $barge['barge_id'];
                        $allRoutes[] = $barge['route'] ?? 'N/A';
                        $totalContainers += count($barge['containers'] ?? []);
                    }
                }

                // Procesar contenedores vacíos
                if (isset($metadata['empty_containers'])) {
                    $emptyContainers += count($metadata['empty_containers']);
                    $totalContainers += count($metadata['empty_containers']);
                    if (isset($metadata['route'])) {
                        $allRoutes[] = $metadata['route'];
                    }
                }
            }

            $report['containers']['total_handled'] = $totalContainers;
            $report['containers']['empty_containers'] = $emptyContainers;
            $report['containers']['loaded_containers'] = $totalContainers - $emptyContainers;

            // Barcazas únicas
            $uniqueBarges = array_count_values($allBarges);
            $report['barges']['unique_barges_used'] = count($uniqueBarges);
            if (!empty($uniqueBarges)) {
                $mostActiveBarge = array_keys($uniqueBarges, max($uniqueBarges))[0];
                $report['barges']['most_active_barge'] = [
                    'barge_id' => $mostActiveBarge,
                    'usage_count' => $uniqueBarges[$mostActiveBarge],
                ];
            }

            // Rutas únicas
            $uniqueRoutes = array_count_values(array_filter($allRoutes, function($route) {
                return $route !== 'N/A';
            }));
            $report['routes']['unique_routes_used'] = count($uniqueRoutes);
            if (!empty($uniqueRoutes)) {
                $mostUsedRoute = array_keys($uniqueRoutes, max($uniqueRoutes))[0];
                $report['routes']['most_used_route'] = [
                    'route' => $mostUsedRoute,
                    'usage_count' => $uniqueRoutes[$mostUsedRoute],
                    'origin' => $this->getPortName(explode('-', $mostUsedRoute)[0] ?? ''),
                    'destination' => $this->getPortName(explode('-', $mostUsedRoute)[1] ?? ''),
                ];
            }

            // Performance
            $avgTime = $transactions->whereNotNull('response_time_ms')->avg('response_time_ms');
            $report['performance']['average_response_time_ms'] = $avgTime ? round($avgTime) : 0;
            
            if ($report['summary']['total_transactions'] > 0) {
                $report['performance']['success_rate'] = round(
                    ($report['summary']['successful_transactions'] / $report['summary']['total_transactions']) * 100, 
                    2
                );
            }

            $this->logOperation('info', 'Reporte de actividad generado', [
                'days' => $days,
                'total_transactions' => $report['summary']['total_transactions'],
                'success_rate' => $report['performance']['success_rate'],
            ]);

            return $report;

        } catch (Exception $e) {
            $this->logOperation('error', 'Error generando reporte de actividad', [
                'error' => $e->getMessage(),
                'days' => $days,
            ]);
            return [];
        }
    }

    /**
     * Obtener transacciones pendientes de reintento
     */
    public function getPendingRetries(): array
    {
        try {
            $pendingTransactions = WebserviceTransaction::forCompany($this->company->id)
                ->ofType('transbordo')
                ->whereIn('status', ['pending', 'retry', 'error'])
                ->where('error_count', '<', DB::raw('max_retries'))
                ->orderBy('created_at', 'desc')
                ->get();

            $retries = [];

            foreach ($pendingTransactions as $transaction) {
                $nextRetryTime = null;
                
                if ($transaction->status === 'error' && $transaction->error_count < $transaction->max_retries) {
                    $intervals = $transaction->retry_intervals ?? [30, 120, 300];
                    $intervalIndex = min($transaction->error_count, count($intervals) - 1);
                    $nextRetryTime = $transaction->updated_at->addSeconds($intervals[$intervalIndex]);
                }

                $retries[] = [
                    'transaction_id' => $transaction->id,
                    'internal_transaction_id' => $transaction->transaction_id,
                    'method_used' => $transaction->additional_metadata['method_used'] ?? 'RegistrarTransbordo',
                    'status' => $transaction->status,
                    'error_count' => $transaction->error_count ?? 0,
                    'max_retries' => $transaction->max_retries,
                    'created_at' => $transaction->created_at,
                    'last_attempt' => $transaction->updated_at,
                    'next_retry_time' => $nextRetryTime,
                    'can_retry_now' => $nextRetryTime ? now()->gte($nextRetryTime) : true,
                ];
            }

            return $retries;

        } catch (Exception $e) {
            $this->logOperation('error', 'Error obteniendo reintentos pendientes', [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Reintentar transacción específica
     */
    public function retryTransaction(int $transactionId): array
    {
        $result = [
            'success' => false,
            'message' => '',
            'new_transaction_id' => null,
        ];

        try {
            $transaction = WebserviceTransaction::forCompany($this->company->id)
                ->ofType('transbordo')
                ->where('id', $transactionId)
                ->whereIn('status', ['pending', 'retry', 'error'])
                ->first();

            if (!$transaction) {
                $result['message'] = 'Transacción no encontrada o no se puede reintentar';
                return $result;
            }

            if ($transaction->error_count >= $transaction->max_retries) {
                $result['message'] = 'Transacción ha excedido el máximo de reintentos';
                return $result;
            }

            // Actualizar contador de errores y estado
            $transaction->update([
                'status' => 'retry',
                'error_count' => ($transaction->error_count ?? 0) + 1,
            ]);

            // Crear cliente SOAP y reenviar
            $soapClient = $this->prepareSoapClient();
            $xmlContent = $transaction->request_xml;
            
            if (!$xmlContent) {
                $result['message'] = 'No se encontró XML original para reenviar';
                return $result;
            }

            $soapResult = $this->sendSoapRequest($transaction, $soapClient, $xmlContent);

            if ($soapResult['success']) {
                $this->processSuccessResponse($transaction, $soapResult);
                $result['success'] = true;
                $result['message'] = 'Transacción reenviada exitosamente';
            } else {
                $this->processErrorResponse($transaction, $soapResult);
                $result['message'] = 'Error en reenvío: ' . $soapResult['error_message'];
            }

            $result['new_transaction_id'] = $transaction->id;

            $this->logOperation('info', 'Reintento de transacción procesado', [
                'transaction_id' => $transaction->id,
                'success' => $result['success'],
                'error_count' => $transaction->error_count,
            ]);

            return $result;

        } catch (Exception $e) {
            $this->logOperation('error', 'Error en reintento de transacción', [
                'error' => $e->getMessage(),
                'transaction_id' => $transactionId,
            ]);

            $result['message'] = 'Error interno: ' . $e->getMessage();
            return $result;
        }
    }
}