<?php

namespace App\Services\Webservice;

use App\Models\Company;
use App\Models\Voyage;
use App\Models\WebserviceTransaction;
use App\Models\WebserviceLog;
use App\Services\Webservice\ArgentinaMicDtaService;
use App\Services\Webservice\ParaguayCustomsService;
use App\Services\Webservice\SoapClientService;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * SERVICIO PARA TESTING DE ENVÍOS A ADUANAS
 * 
 * Facilita las pruebas de envío a aduanas Argentina y Paraguay
 * utilizando la infraestructura existente del sistema.
 * 
 * FUNCIONALIDADES:
 * - Validación previa de datos antes del envío
 * - Testing de conectividad con webservices
 * - Simulación de envíos en ambiente de testing
 * - Monitoreo de respuestas y errores
 * - Generación de reportes de testing
 * 
 * INTEGRACIÓN:
 * - Utiliza servicios existentes ArgentinaMicDtaService y ParaguayCustomsService
 * - Compatible con certificados .p12 del sistema
 * - Logging unificado con tablas webservice_*
 * - Respeta estructura de transacciones existente
 */
class TestingCustomsService
{
    private Company $company;
    private array $testResults = [];
    private array $config;

    /**
     * Configuración de testing
     */
    private const TESTING_CONFIG = [
        'timeout' => 30,
        'max_retries' => 2,
        'test_environments' => ['testing'],
        'validation_rules' => [
            'voyage_required_fields' => ['voyage_number', 'vessel_id', 'origin_port_id', 'destination_port_id'],
            'shipment_required_fields' => ['shipment_number', 'client_id', 'gross_weight'],
            'container_required_fields' => ['container_number', 'container_type']
        ]
    ];

    public function __construct(Company $company)
    {
        $this->company = $company;
        $this->config = self::TESTING_CONFIG;
        $this->testResults = [];
    }

    /**
     * Ejecutar prueba completa de envío a aduanas
     */
    public function runCompleteTest(Voyage $voyage, array $options = []): array
    {
        $testId = $this->generateTestId();
        
        $this->logTestOperation('info', 'Iniciando prueba completa de envío a aduanas', [
            'test_id' => $testId,
            'voyage_id' => $voyage->id,
            'voyage_number' => $voyage->voyage_number,
            'options' => $options
        ]);

        try {
            // 1. Validación previa de datos
            $this->testResults['data_validation'] = $this->validateVoyageData($voyage);
            
            // 2. Verificación de certificados
            $this->testResults['certificate_validation'] = $this->validateCertificate();
            
            // 3. Testing de conectividad
            $this->testResults['connectivity_test'] = $this->testConnectivity($options);
            
            // 4. Validación de XML generado
            $this->testResults['xml_validation'] = $this->validateGeneratedXml($voyage, $options);
            
            // 5. Simulación de envío (solo en testing)
            if (($options['environment'] ?? 'testing') === 'testing') {
                $this->testResults['simulation_test'] = $this->simulateCustomsSend($voyage, $options);
            }
            
            // 6. Generar resumen final
            $this->testResults['summary'] = $this->generateTestSummary();
            
            $this->logTestOperation('info', 'Prueba completa finalizada', [
                'test_id' => $testId,
                'status' => $this->testResults['summary']['status'],
                'total_checks' => $this->testResults['summary']['total_checks'],
                'passed_checks' => $this->testResults['summary']['passed_checks']
            ]);

            return $this->testResults;

        } catch (Exception $e) {
            $this->logTestOperation('error', 'Error en prueba completa', [
                'test_id' => $testId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $this->testResults['error'] = [
                'status' => 'error',
                'message' => $e->getMessage(),
                'occurred_at' => now()->toISOString()
            ];

            return $this->testResults;
        }
    }

    /**
     * Validar datos del viaje antes del envío
     */
    private function validateVoyageData(Voyage $voyage): array
    {
        $result = [
            'status' => 'success',
            'checks' => [],
            'errors' => [],
            'warnings' => []
        ];

        try {
            // Validar campos obligatorios del viaje
            $voyageFields = $this->config['validation_rules']['voyage_required_fields'];
            foreach ($voyageFields as $field) {
                if (empty($voyage->$field)) {
                    $result['errors'][] = "Campo obligatorio del viaje faltante: {$field}";
                }
            }

            // Validar que tenga shipments
            $shipmentsCount = $voyage->shipments()->count();
            if ($shipmentsCount === 0) {
                $result['errors'][] = "El viaje no tiene shipments asociados";
            } else {
                $result['checks'][] = "Viaje tiene {$shipmentsCount} shipments";
            }

            // Validar shipments
            foreach ($voyage->shipments as $shipment) {
                $shipmentErrors = $this->validateShipmentData($shipment);
                if (!empty($shipmentErrors)) {
                    $result['errors'] = array_merge($result['errors'], $shipmentErrors);
                }
            }

            // Validar vessel si existe
            if ($voyage->vessel) {
                $result['checks'][] = "Vessel: {$voyage->vessel->vessel_name} (Código: {$voyage->vessel->vessel_code})";
            } else {
                $result['warnings'][] = "No se encontró información del vessel";
            }

            // Determinar status final
            if (!empty($result['errors'])) {
                $result['status'] = 'error';
            } elseif (!empty($result['warnings'])) {
                $result['status'] = 'warning';
            }

        } catch (Exception $e) {
            $result['status'] = 'error';
            $result['errors'][] = "Error validando datos: " . $e->getMessage();
        }

        return $result;
    }

    /**
     * Validar datos de un shipment específico
     */
    private function validateShipmentData($shipment): array
    {
        $errors = [];
        $shipmentFields = $this->config['validation_rules']['shipment_required_fields'];

        foreach ($shipmentFields as $field) {
            if (empty($shipment->$field)) {
                $errors[] = "Shipment {$shipment->shipment_number}: Campo {$field} faltante";
            }
        }

        // Validar client si existe
        if (!$shipment->client) {
            $errors[] = "Shipment {$shipment->shipment_number}: No tiene cliente asociado";
        } elseif (empty($shipment->client->tax_id)) {
            $errors[] = "Shipment {$shipment->shipment_number}: Cliente sin CUIT/RUC";
        }

        // Validar contenedores si los tiene
        $containersCount = $shipment->containers()->count();
        if ($containersCount > 0) {
            foreach ($shipment->containers as $container) {
                $containerErrors = $this->validateContainerData($container, $shipment->shipment_number);
                $errors = array_merge($errors, $containerErrors);
            }
        }

        return $errors;
    }

    /**
     * Validar datos de un contenedor
     */
    private function validateContainerData($container, string $shipmentNumber): array
    {
        $errors = [];
        $containerFields = $this->config['validation_rules']['container_required_fields'];

        foreach ($containerFields as $field) {
            if (empty($container->$field)) {
                $errors[] = "Shipment {$shipmentNumber}, Container {$container->container_number}: Campo {$field} faltante";
            }
        }

        return $errors;
    }

    /**
     * Validar certificado de la empresa
     */
    private function validateCertificate(): array
    {
        $result = [
            'status' => 'success',
            'checks' => [],
            'errors' => [],
            'warnings' => []
        ];

        try {
            // Verificar que la empresa tenga certificado
            if (empty($this->company->certificate_path)) {
                $result['status'] = 'error';
                $result['errors'][] = "La empresa no tiene certificado configurado";
                return $result;
            }

            // Verificar que el archivo existe
            if (!file_exists(storage_path('app/' . $this->company->certificate_path))) {
                $result['status'] = 'error';
                $result['errors'][] = "Archivo de certificado no encontrado";
                return $result;
            }

            $result['checks'][] = "Certificado encontrado: " . basename($this->company->certificate_path);

            // Verificar fecha de expiración
            if ($this->company->certificate_expires_at) {
                $expiresAt = Carbon::parse($this->company->certificate_expires_at);
                $daysUntilExpiry = now()->diffInDays($expiresAt, false);

                if ($daysUntilExpiry < 0) {
                    $result['status'] = 'error';
                    $result['errors'][] = "Certificado vencido hace " . abs($daysUntilExpiry) . " días";
                } elseif ($daysUntilExpiry < 30) {
                    $result['warnings'][] = "Certificado vence en {$daysUntilExpiry} días";
                } else {
                    $result['checks'][] = "Certificado válido por {$daysUntilExpiry} días más";
                }
            }

            // Verificar contraseña del certificado si está disponible
            if ($this->company->certificate_password) {
                $result['checks'][] = "Contraseña de certificado configurada";
            } else {
                $result['warnings'][] = "Contraseña de certificado no configurada";
            }

        } catch (Exception $e) {
            $result['status'] = 'error';
            $result['errors'][] = "Error validando certificado: " . $e->getMessage();
        }

        return $result;
    }

    /**
     * Testing de conectividad con webservices
     */
    private function testConnectivity(array $options): array
    {
        $result = [
            'status' => 'success',
            'tests' => [],
            'errors' => []
        ];

        $environment = $options['environment'] ?? 'testing';
        $webserviceType = $options['webservice_type'] ?? 'micdta';

        try {
            // Test Argentina AFIP
            if (in_array($webserviceType, ['anticipada', 'micdta', 'transbordo'])) {
                $result['tests']['argentina'] = $this->testArgentinaConnectivity($environment);
            }

            // Test Paraguay DNA
            if ($webserviceType === 'paraguay_customs') {
                $result['tests']['paraguay'] = $this->testParaguayConnectivity($environment);
            }

            // Determinar status general
            foreach ($result['tests'] as $test) {
                if ($test['status'] === 'error') {
                    $result['status'] = 'error';
                    break;
                } elseif ($test['status'] === 'warning' && $result['status'] === 'success') {
                    $result['status'] = 'warning';
                }
            }

        } catch (Exception $e) {
            $result['status'] = 'error';
            $result['errors'][] = "Error en test de conectividad: " . $e->getMessage();
        }

        return $result;
    }

    /**
     * Testing de conectividad con Argentina AFIP
     */
    private function testArgentinaConnectivity(string $environment): array
    {
        $result = [
            'status' => 'success',
            'message' => 'Conectividad Argentina OK',
            'details' => []
        ];

        try {
            // URLs según ambiente
            $urls = [
                'testing' => 'https://wsaduhomoext.afip.gob.ar/DIAV2/wgesregsintia2/wgesregsintia2.asmx',
                'production' => 'https://wsadu.afip.gob.ar/DIAV2/wgesregsintia2/wgesregsintia2.asmx'
            ];

            $url = $urls[$environment] ?? $urls['testing'];
            
            // Test básico de conectividad usando el SoapClientService existente
            $soapClientService = new SoapClientService($this->company);
            
            // Intentar crear cliente para verificar conectividad
            $client = $soapClientService->createClient('micdta', $environment);
            
            if ($client) {
                $result['details'][] = "Conexión exitosa a {$url}";
                $result['details'][] = "Cliente SOAP creado correctamente";
            } else {
                $result['status'] = 'warning';
                $result['message'] = 'Advertencia en conectividad Argentina';
                $result['details'][] = "No se pudo crear el cliente SOAP";
            }

        } catch (Exception $e) {
            $result['status'] = 'error';
            $result['message'] = 'Error en test Argentina';
            $result['details'][] = "Error: " . $e->getMessage();
        }

        return $result;
    }

    /**
     * Testing de conectividad con Paraguay DNA
     */
    private function testParaguayConnectivity(string $environment): array
    {
        $result = [
            'status' => 'success',
            'message' => 'Conectividad Paraguay OK',
            'details' => []
        ];

        try {
            // URLs según ambiente
            $urls = [
                'testing' => 'https://securetest.aduana.gov.py/wsdl/gdsf/serviciogdsf',
                'production' => 'https://secure.aduana.gov.py/wsdl/gdsf/serviciogdsf'
            ];

            $url = $urls[$environment] ?? $urls['testing'];
            
            // Test básico de conectividad usando el SoapClientService existente
            $soapClientService = new SoapClientService($this->company);
            
            // Intentar crear cliente para verificar conectividad
            $client = $soapClientService->createClient('paraguay_customs', $environment);
            
            if ($client) {
                $result['details'][] = "Conexión exitosa a {$url}";
                $result['details'][] = "Cliente SOAP creado correctamente";
            } else {
                $result['status'] = 'warning';
                $result['message'] = 'Advertencia en conectividad Paraguay';
                $result['details'][] = "No se pudo crear el cliente SOAP";
            }

        } catch (Exception $e) {
            $result['status'] = 'error';
            $result['message'] = 'Error en test Paraguay';
            $result['details'][] = "Error: " . $e->getMessage();
        }

        return $result;
    }

    /**
     * Validar XML generado antes del envío
     */
    private function validateGeneratedXml(Voyage $voyage, array $options): array
    {
        $result = [
            'status' => 'success',
            'checks' => [],
            'errors' => [],
            'xml_sample' => null
        ];

        try {
            $webserviceType = $options['webservice_type'] ?? 'micdta';
            $transactionId = 'TEST_' . $this->generateTestId();

            // Usar XmlSerializerService que ya existe
            $xmlSerializer = new XmlSerializerService($this->company);

            // Generar XML según tipo de webservice usando métodos existentes
            switch ($webserviceType) {
                case 'micdta':
                    // Usar el primer shipment del voyage para generar XML MIC/DTA
                    $firstShipment = $voyage->shipments()->first();
                    if ($firstShipment) {
                        $xmlData = $xmlSerializer->createMicDtaXml($firstShipment, $transactionId);
                    } else {
                        throw new Exception("El viaje no tiene shipments para generar MIC/DTA");
                    }
                    break;

                case 'anticipada':
                    $xmlData = $xmlSerializer->createAnticipatedInfoXml($voyage, $transactionId);
                    break;

                case 'paraguay_customs':
                    $xmlData = $xmlSerializer->createParaguayManifestXml($voyage, $transactionId);
                    break;

                default:
                    throw new Exception("Tipo de webservice no soportado: {$webserviceType}");
            }

            if ($xmlData) {
                $result['checks'][] = "XML generado correctamente";
                $result['checks'][] = "Tamaño del XML: " . strlen($xmlData) . " bytes";
                
                // Guardar muestra del XML (primeros 500 caracteres)
                $result['xml_sample'] = substr($xmlData, 0, 500) . (strlen($xmlData) > 500 ? '...' : '');

                // Validación básica de XML usando método existente
                $validation = $xmlSerializer->validateXmlStructure($xmlData);
                if ($validation['is_valid']) {
                    $result['checks'][] = "XML válido sintácticamente";
                } else {
                    $result['status'] = 'error';
                    $result['errors'] = array_merge($result['errors'], $validation['errors']);
                }
            } else {
                $result['status'] = 'error';
                $result['errors'][] = "No se pudo generar el XML";
            }

        } catch (Exception $e) {
            $result['status'] = 'error';
            $result['errors'][] = "Error generando XML: " . $e->getMessage();
        }

        return $result;
    }

    /**
     * Simular envío a aduana (solo en testing)
     */
    private function simulateCustomsSend(Voyage $voyage, array $options): array
    {
        $result = [
            'status' => 'success',
            'simulation_id' => $this->generateSimulationId(),
            'checks' => [],
            'response' => null
        ];

        try {
            $webserviceType = $options['webservice_type'] ?? 'micdta';

            // Crear transacción de prueba
            $transaction = WebserviceTransaction::create([
                'company_id' => $this->company->id,
                'voyage_id' => $voyage->id,
                'transaction_id' => 'TEST_' . $result['simulation_id'],
                'webservice_type' => $webserviceType,
                'environment' => 'testing',
                'status' => 'simulated',
                'request_data' => [
                    'test_mode' => true,
                    'voyage_number' => $voyage->voyage_number,
                    'shipments_count' => $voyage->shipments()->count()
                ]
            ]);

            $result['checks'][] = "Transacción de prueba creada: {$transaction->transaction_id}";

            // Simular respuesta de la aduana
            $simulatedResponse = $this->generateSimulatedResponse($webserviceType);
            $result['response'] = $simulatedResponse;

            // Actualizar transacción con respuesta simulada
            $transaction->update([
                'status' => 'success',
                'response_data' => $simulatedResponse,
                'response_at' => now()
            ]);

            $result['checks'][] = "Respuesta simulada generada";
            $result['checks'][] = "Número de confirmación: {$simulatedResponse['confirmation_number']}";

        } catch (Exception $e) {
            $result['status'] = 'error';
            $result['errors'][] = "Error en simulación: " . $e->getMessage();
        }

        return $result;
    }

    /**
     * Generar respuesta simulada de la aduana
     */
    private function generateSimulatedResponse(string $webserviceType): array
    {
        $confirmationNumber = 'SIM_' . now()->format('Ymd') . '_' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);

        $baseResponse = [
            'status' => 'success',
            'confirmation_number' => $confirmationNumber,
            'timestamp' => now()->toISOString(),
            'message' => 'Envío simulado procesado correctamente'
        ];

        // Personalizar según tipo de webservice
        switch ($webserviceType) {
            case 'micdta':
                $baseResponse['voyage_track'] = 'ARG_' . $confirmationNumber;
                $baseResponse['aduana'] = 'Argentina AFIP';
                break;

            case 'paraguay_customs':
                $baseResponse['manifest_number'] = 'PY_' . $confirmationNumber;
                $baseResponse['aduana'] = 'Paraguay DNA';
                break;
        }

        return $baseResponse;
    }

    /**
     * Generar resumen final de todas las pruebas
     */
    private function generateTestSummary(): array
    {
        $totalChecks = 0;
        $passedChecks = 0;
        $errors = [];
        $warnings = [];

        // Contar checks y errores de todas las secciones
        foreach ($this->testResults as $section => $data) {
            if (is_array($data) && isset($data['checks'])) {
                $totalChecks += count($data['checks']);
                if ($data['status'] === 'success') {
                    $passedChecks += count($data['checks']);
                }
            }

            if (is_array($data) && isset($data['errors'])) {
                $errors = array_merge($errors, $data['errors']);
            }

            if (is_array($data) && isset($data['warnings'])) {
                $warnings = array_merge($warnings, $data['warnings']);
            }
        }

        $overallStatus = 'success';
        if (!empty($errors)) {
            $overallStatus = 'error';
        } elseif (!empty($warnings)) {
            $overallStatus = 'warning';
        }

        return [
            'status' => $overallStatus,
            'total_checks' => $totalChecks,
            'passed_checks' => $passedChecks,
            'total_errors' => count($errors),
            'total_warnings' => count($warnings),
            'success_rate' => $totalChecks > 0 ? round(($passedChecks / $totalChecks) * 100, 2) : 0,
            'test_completed_at' => now()->toISOString(),
            'ready_for_production' => $overallStatus === 'success'
        ];
    }

    /**
     * Validar si un string es XML válido
     */
    private function isValidXml(string $xml): bool
    {
        libxml_use_internal_errors(true);
        $doc = simplexml_load_string($xml);
        return $doc !== false;
    }

    /**
     * Generar ID único para el test
     */
    private function generateTestId(): string
    {
        return 'TEST_' . now()->format('YmdHis') . '_' . str_pad(mt_rand(1, 999), 3, '0', STR_PAD_LEFT);
    }

    /**
     * Generar ID único para simulación
     */
    private function generateSimulationId(): string
    {
        return now()->format('YmdHis') . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
    }

    /**
     * Logging específico para operaciones de testing
     */
    private function logTestOperation(string $level, string $message, array $context = []): void
    {
        $logData = array_merge([
            'service' => 'TestingCustomsService',
            'company_id' => $this->company->id,
            'company_name' => $this->company->legal_name,
            'timestamp' => now()->toISOString(),
            'test_session' => session()->getId()
        ], $context);

        Log::{$level}($message, $logData);

        // Log en tabla webservice_logs si hay transaction_id
        if (isset($context['transaction_id'])) {
            try {
                WebserviceLog::create([
                    'transaction_id' => $context['transaction_id'],
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
    }

    /**
     * Obtener configuración actual del servicio de testing
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * Obtener resultados del último test ejecutado
     */
    public function getLastTestResults(): array
    {
        return $this->testResults;
    }
}