<?php

namespace App\Services\Webservice;

use App\Models\Company;
use App\Models\WebserviceTransaction;
use SoapClient;
use SoapFault;
use Exception;
use Illuminate\Support\Facades\Log;

/**
 * MÓDULO 4: WEBSERVICES ADUANA - SoapClientService
 *
 * Servicio centralizado para manejo de comunicación SOAP con webservices aduaneros.
 * Maneja certificados digitales, autenticación, envío de requests y parseo de responses.
 *
 * Soporta:
 * - Argentina AFIP: MIC/DTA, Información Anticipada
 * - Paraguay DNA: GDSF, Manifiestos
 * - Autenticación con certificados .p12
 * - Logging detallado y manejo de errores
 * - Reintentos automáticos con backoff
 */
class SoapClientService
{
    private Company $company;
    private array $config;
    private ?SoapClient $soapClient = null;

    /**
     * URLs por defecto de webservices (basadas en investigación)
     */
    private const DEFAULT_WEBSERVICE_URLS = [
        'AR' => [
            'testing' => [
                'micdta' => 'https://wsaduhomoext.afip.gob.ar/DIAV2/wgesregsintia2/wgesregsintia2.asmx',
                'anticipada' => 'https://wsaduhomoext.afip.gob.ar/DIAV2/wgesinformacionanticipada/wgesinformacionanticipada.asmx',
                'desconsolidado' => 'https://wsaduhomoext.afip.gob.ar/DIAV2/wgesregsintia2/wgesregsintia2.asmx',
                'transbordo' => 'https://wsaduhomoext.afip.gob.ar/DIAV2/wgesregsintia2/wgesregsintia2.asmx',
                'auth' => 'https://wsaahomo.afip.gov.ar/ws/services/LoginCms',
            ],
            'production' => [
                'micdta' => 'https://webservicesadu.afip.gob.ar/DIAV2/wgesregsintia2/wgesregsintia2.asmx',
                'anticipada' => 'https://webservicesadu.afip.gob.ar/DIAV2/wgesinformacionanticipada/wgesinformacionanticipada.asmx',
                'desconsolidado' => 'https://webservicesadu.afip.gob.ar/DIAV2/wgesregsintia2/wgesregsintia2.asmx',
                'transbordo' => 'https://webservicesadu.afip.gob.ar/DIAV2/wgesregsintia2/wgesregsintia2.asmx',
                'auth' => 'https://wsaa.afip.gov.ar/ws/services/LoginCms',
            ],
        ],
        'PY' => [
            'testing' => [
                'gdsf' => 'https://securetest.aduana.gov.py/wsdl/tere2/serviciotere', // ✅ CORRECTO
                'paraguay_customs' => 'https://securetest.aduana.gov.py/wsdl/tere2/serviciotere',
                'auth' => 'https://securetest.aduana.gov.py/wsdl/wsaaserver/Server',
            ],
            'production' => [
                'gdsf' => 'https://secure.aduana.gov.py/wsdl/tere2/serviciotere', // ✅ CORRECTO
                'paraguay_customs' => 'https://secure.aduana.gov.py/wsdl/tere2/serviciotere',
                'auth' => 'https://secure.aduana.gov.py/wsdl/wsaaserver/Server',
            ],
        ],
    ];

    /**
     * SOAPActions conocidas por webservice
     */
    private const SOAP_ACTIONS = [
        'AR' => [
            'micdta' => 'Ar.Gob.Afip.Dga.wgesregsintia2/RegistrarMicDta',
            'anticipada' => 'Ar.Gob.Afip.Dga.Org.wgesinformacionanticipada/RegistrarViaje',
        ],
        'PY' => [
            // Paraguay usa diferentes patrones de SOAPAction
        ],
    ];

    public function __construct(Company $company)
    {
        $this->company = $company;
        $this->config = $this->buildConfiguration();
    }

    /**
     * Crear cliente SOAP para un webservice específico
     */
    public function createClient(string $webserviceType, string $environment = 'testing'): SoapClient
    {
        $wsdlUrl = $this->getWsdlUrl($webserviceType, $environment);

        $soapOptions = [
            'soap_version' => SOAP_1_2,
            'encoding' => 'UTF-8',
            'connection_timeout' => $this->config['connection_timeout'] ?? 30,
            'timeout' => $this->config['timeout'] ?? 60,
            'trace' => true,
            'exceptions' => true,
            'cache_wsdl' => WSDL_CACHE_NONE, // Deshabilitado para desarrollo
            'user_agent' => 'AppCargas/1.0 (Sistema de Cargas Fluviales)',
            'stream_context' => $this->createStreamContext(),
        ];

        try {
            $this->soapClient = new SoapClient($wsdlUrl, $soapOptions);

            // Configurar headers de autenticación si es necesario
            $this->addAuthenticationHeaders($webserviceType);

            return $this->soapClient;

        } catch (SoapFault $e) {
            Log::error('Error creando cliente SOAP', [
                'company_id' => $this->company->id,
                'webservice_type' => $webserviceType,
                'wsdl_url' => $wsdlUrl,
                'error' => $e->getMessage(),
            ]);

            throw new Exception("No se pudo conectar al webservice {$webserviceType}: " . $e->getMessage());
        }
    }

    /**
     * Enviar request SOAP
     */
    public function sendRequest(
        WebserviceTransaction $transaction,
        string $method,
        array $parameters
    ): array {
        if (!$this->soapClient) {
            throw new Exception('Cliente SOAP no inicializado');
        }

        $startTime = microtime(true);

        try {
            // Log del inicio de la transacción
            $this->logTransaction($transaction, 'info', 'Iniciando envío SOAP', [
                'method' => $method,
                'webservice_url' => $transaction->webservice_url,
                'soap_action' => $transaction->soap_action,
            ]);

            // Ejecutar llamada SOAP
            $response = $this->soapClient->__soapCall($method, $parameters);

            $endTime = microtime(true);
            $responseTime = round(($endTime - $startTime) * 1000);

            // Obtener XMLs de request y response
            $requestXml = $this->soapClient->__getLastRequest();
            $responseXml = $this->soapClient->__getLastResponse();

            // Log exitoso
            $this->logTransaction($transaction, 'info', 'Respuesta SOAP recibida exitosamente', [
                'response_time_ms' => $responseTime,
                'response_size' => strlen($responseXml),
            ]);

            return [
                'success' => true,
                'response' => $response,
                'request_xml' => $requestXml,
                'response_xml' => $responseXml,
                'response_time_ms' => $responseTime,
            ];

        } catch (SoapFault $soapFault) {
            $endTime = microtime(true);
            $responseTime = round(($endTime - $startTime) * 1000);

            $this->logTransaction($transaction, 'error', 'Error SOAP Fault', [
                'fault_code' => $soapFault->faultcode,
                'fault_string' => $soapFault->faultstring,
                'response_time_ms' => $responseTime,
            ]);

            return [
                'success' => false,
                'error_type' => 'soap_fault',
                'error_code' => $soapFault->faultcode,
                'error_message' => $soapFault->faultstring,
                'request_xml' => $this->soapClient->__getLastRequest(),
                'response_xml' => $this->soapClient->__getLastResponse(),
                'response_time_ms' => $responseTime,
            ];

        } catch (Exception $e) {
            $endTime = microtime(true);
            $responseTime = round(($endTime - $startTime) * 1000);

            $this->logTransaction($transaction, 'error', 'Error general en comunicación SOAP', [
                'error' => $e->getMessage(),
                'response_time_ms' => $responseTime,
            ]);

            return [
                'success' => false,
                'error_type' => 'general_error',
                'error_code' => 'SOAP_ERROR',
                'error_message' => $e->getMessage(),
                'response_time_ms' => $responseTime,
            ];
        }
    }

    /**
     * Obtener URL del WSDL según webservice y ambiente
     * ACTUALIZADO: Usa configuración de empresa con fallback a URLs por defecto
     */
    private function getWsdlUrl(string $webserviceType, string $environment): string
    {
        // ✅ DETERMINAR PAÍS DESDE WEBSERVICE TYPE
        $country = $this->getCountryFromWebserviceType($webserviceType);
        
        // ✅ INTENTAR OBTENER URL DESDE CONFIGURACIÓN DE EMPRESA
        $configuredUrl = $this->company->getWebserviceUrl($country, $webserviceType, $environment);
        
        if ($configuredUrl) {
            Log::info('Usando URL configurada desde empresa', [
                'company_id' => $this->company->id,
                'webservice_type' => $webserviceType,
                'environment' => $environment,
                'country' => $country,
                'url' => $configuredUrl,
            ]);
            
            return $configuredUrl;
        }

        // ✅ FALLBACK: Verificar si la empresa tiene URLs personalizadas en formato anterior
        $legacyCustomUrls = $this->company->ws_config['webservice_urls'][$environment] ?? null;
        if ($legacyCustomUrls && isset($legacyCustomUrls[$webserviceType])) {
            Log::info('Usando URL legacy desde ws_config', [
                'company_id' => $this->company->id,
                'webservice_type' => $webserviceType,
                'environment' => $environment,
                'url' => $legacyCustomUrls[$webserviceType],
            ]);
            
            return $legacyCustomUrls[$webserviceType];
        }

        // ✅ USAR URLs POR DEFECTO COMO ÚLTIMO RECURSO
        $defaultUrls = self::DEFAULT_WEBSERVICE_URLS[$country][$environment] ?? null;
        if (!$defaultUrls || !isset($defaultUrls[$webserviceType])) {
            throw new Exception("URL de webservice no configurada para {$webserviceType} en {$environment}");
        }

        Log::info('Usando URL por defecto', [
            'company_id' => $this->company->id,
            'webservice_type' => $webserviceType,
            'environment' => $environment,
            'country' => $country,
            'url' => $defaultUrls[$webserviceType],
        ]);

        return $defaultUrls[$webserviceType];
    }

    /**
     * Determinar país desde tipo de webservice
     */
    private function getCountryFromWebserviceType(string $webserviceType): string
    {
        $webserviceCountryMapping = [
            // Argentina
            'micdta' => 'argentina',
            'anticipada' => 'argentina', 
            'desconsolidado' => 'argentina',
            'transbordo' => 'argentina',
            'mane' => 'argentina',
            
            // Paraguay
            'gdsf' => 'paraguay',
            'tere' => 'paraguay',
            'paraguay_customs' => 'paraguay',
            'servicioreferencia' => 'paraguay',
        ];

        $country = $webserviceCountryMapping[$webserviceType] ?? null;
        
        if (!$country) {
            // Fallback al país de la empresa
            $companyCountry = strtolower($this->company->country ?? 'AR');
            $country = $companyCountry === 'py' ? 'paraguay' : 'argentina';
            
            Log::warning('Tipo de webservice no reconocido, usando país de empresa', [
                'webservice_type' => $webserviceType,
                'company_country' => $this->company->country,
                'assigned_country' => $country,
            ]);
        }

        return $country;
    }

    /**
     * Crear contexto de stream para HTTPS con certificados
     */
    private function createStreamContext()
    {
        $contextOptions = [
            'http' => [
                'timeout' => $this->config['timeout'] ?? 60,
                'user_agent' => 'AppCargas/1.0',
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
                'allow_self_signed' => false,
            ],
        ];

        // Agregar certificado si existe
        if ($this->company->has_certificate && $this->company->certificate_path) {
            $certPath = $this->company->getCertificatePath();
            $certPassword = $this->company->getCertificatePassword();

            $contextOptions['ssl']['local_cert'] = $certPath;
            $contextOptions['ssl']['passphrase'] = $certPassword;
        }

        return stream_context_create($contextOptions);
    }

    /**
     * Agregar headers de autenticación específicos
     */
    private function addAuthenticationHeaders(string $webserviceType): void
    {
        $country = $this->company->country ?? 'AR';

        if ($country === 'AR') {
            // Para Argentina, se necesita token y sign de WSAA
            // TODO: Implementar autenticación WSAA completa
            $this->addArgentinaAuthHeaders();
        } elseif ($country === 'PY') {
            // Para Paraguay, autenticación específica
            // TODO: Implementar autenticación Paraguay
            $this->addParaguayAuthHeaders();
        }
    }

    /**
     * Headers de autenticación Argentina (placeholder)
     */
    private function addArgentinaAuthHeaders(): void
    {
        // TODO: Implementar autenticación WSAA
        // $token = $this->getWSAAToken();
        // $sign = $this->getWSAASign();

        // $authHeader = new SoapHeader(
        //     'http://ar.gob.afip.dif.wgesregsintia2/',
        //     'Auth',
        //     ['Token' => $token, 'Sign' => $sign]
        // );

        // $this->soapClient->__setSoapHeaders($authHeader);
    }

    /**
     * Headers de autenticación Paraguay (placeholder)
     */
    private function addParaguayAuthHeaders(): void
    {
        // TODO: Implementar autenticación Paraguay
    }

    /**
     * Construir configuración del servicio
     */
    private function buildConfiguration(): array
    {
        return [
            'timeout' => $this->company->ws_config['timeout'] ?? 60,
            'connection_timeout' => $this->company->ws_config['connection_timeout'] ?? 30,
            'max_retries' => $this->company->ws_config['max_retries'] ?? 3,
            'environment' => $this->company->ws_environment ?? 'testing',
        ];
    }

    /**
     * Log de transacciones
     */
    private function logTransaction(
        WebserviceTransaction $transaction,
        string $level,
        string $message,
        array $context = []
    ): void {
        // TODO: Crear registro en webservice_logs cuando esté implementado
        Log::channel('webservices')->{$level}($message, array_merge([
            'transaction_id' => $transaction->id,
            'company_id' => $this->company->id,
            'webservice_type' => $transaction->webservice_type,
        ], $context));
    }

    /**
     * Obtener información del último request/response
     */
    public function getLastRequestInfo(): array
    {
        if (!$this->soapClient) {
            return [];
        }

        return [
            'request_xml' => $this->soapClient->__getLastRequest(),
            'response_xml' => $this->soapClient->__getLastResponse(),
            'request_headers' => $this->soapClient->__getLastRequestHeaders(),
            'response_headers' => $this->soapClient->__getLastResponseHeaders(),
        ];
    }
}
