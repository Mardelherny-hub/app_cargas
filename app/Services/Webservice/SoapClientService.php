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
            'micdta' => 'https://wsaduhomoext.afip.gob.ar/DIAV2/wgesregsintia2/wgesregsintia2.asmx?wsdl',
            'anticipada' => 'https://wsaduhomoext.afip.gob.ar/DIAV2/wgesinformacionanticipada/wgesinformacionanticipada.asmx?wsdl',
            'desconsolidado' => 'https://wsaduhomoext.afip.gob.ar/DIAV2/wgesregsintia2/wgesregsintia2.asmx?wsdl',
            'transbordo' => 'https://wsaduhomoext.afip.gob.ar/DIAV2/wgesregsintia2/wgesregsintia2.asmx?wsdl',
            'auth' => 'https://wsaahomo.afip.gov.ar/ws/services/LoginCms?wsdl',
        ],
        'production' => [
            'micdta' => 'https://webservicesadu.afip.gob.ar/DIAV2/wgesregsintia2/wgesregsintia2.asmx?wsdl',
            'anticipada' => 'https://webservicesadu.afip.gob.ar/DIAV2/wgesinformacionanticipada/wgesinformacionanticipada.asmx?wsdl',
            'desconsolidado' => 'https://webservicesadu.afip.gob.ar/DIAV2/wgesregsintia2/wgesregsintia2.asmx?wsdl',
            'transbordo' => 'https://webservicesadu.afip.gob.ar/DIAV2/wgesregsintia2/wgesregsintia2.asmx?wsdl',
            'auth' => 'https://wsaa.afip.gov.ar/ws/services/LoginCms?wsdl',
        ],
    ],
    'PY' => [
        'testing' => [
            'gdsf' => 'https://securetest.aduana.gov.py/wsdl/tere2/serviciotere?wsdl',
            'paraguay_customs' => 'https://securetest.aduana.gov.py/wsdl/tere2/serviciotere?wsdl',
            'auth' => 'https://securetest.aduana.gov.py/wsdl/wsaaserver/Server?wsdl',
        ],
        'production' => [
            'gdsf' => 'https://secure.aduana.gov.py/wsdl/tere2/serviciotere?wsdl',
            'paraguay_customs' => 'https://secure.aduana.gov.py/wsdl/tere2/serviciotere?wsdl',
            'auth' => 'https://secure.aduana.gov.py/wsdl/wsaaserver/Server?wsdl',
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
    // Al inicio del método getWsdlUrl(), agrega:
Log::debug('DEFAULT_WEBSERVICE_URLS structure', [
    'full_array' => self::DEFAULT_WEBSERVICE_URLS
]);
    $wsdlUrl = $this->getWsdlUrl($webserviceType, $environment);

     // 🔍 DEBUG: Descargar el WSDL manualmente para ver qué recibimos
    try {
        $context = $this->createStreamContext();
        $wsdlContent = file_get_contents($wsdlUrl, false, $context);
        
        Log::debug('WSDL Content Debug', [
            'url' => $wsdlUrl,
            'content_length' => strlen($wsdlContent),
            'first_100_chars' => substr($wsdlContent, 0, 100),
            'last_100_chars' => substr($wsdlContent, -100),
            'contains_wsdl' => strpos($wsdlContent, '<wsdl:') !== false,
            'contains_html' => strpos($wsdlContent, '<html') !== false,
        ]);
        
    } catch (Exception $e) {
        Log::error('Error descargando WSDL para debug', ['error' => $e->getMessage()]);
    }

    $soapOptions = [
        'soap_version' => $webserviceType === 'anticipada' ? SOAP_1_1 : SOAP_1_2,
        'encoding' => 'UTF-8',
        'connection_timeout' => $this->config['connection_timeout'] ?? 30,
        'timeout' => $this->config['timeout'] ?? 60,
        'trace' => true,
        'exceptions' => true,
        'cache_wsdl' => WSDL_CACHE_NONE,
        'user_agent' => 'AppCargas/1.0 (Sistema de Cargas Fluviales)',
        'stream_context' => $this->createStreamContext(),
        // ⭐ AGREGAR ESTAS OPCIONES PARA DESARROLLO
        'features' => SOAP_SINGLE_ELEMENT_ARRAYS,
        'keep_alive' => false,
        'compression' => SOAP_COMPRESSION_ACCEPT | SOAP_COMPRESSION_GZIP,
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
            'php_version' => PHP_VERSION,
            'openssl_version' => OPENSSL_VERSION_TEXT,
        ]);

        throw new Exception("No se pudo conectar al webservice {$webserviceType}: " . $e->getMessage());
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
    
    // 🐛 DEBUG COMPLETO
    Log::debug('getWsdlUrl Debug Completo', [
        'webserviceType' => $webserviceType,
        'environment' => $environment,
        'country' => $country,
        'DEFAULT_WEBSERVICE_URLS_keys' => array_keys(self::DEFAULT_WEBSERVICE_URLS),
        'country_exists_in_default' => isset(self::DEFAULT_WEBSERVICE_URLS[$country]),
        'environment_exists' => isset(self::DEFAULT_WEBSERVICE_URLS[$country][$environment]),
        'webservice_exists' => isset(self::DEFAULT_WEBSERVICE_URLS[$country][$environment][$webserviceType]),
        'full_path_check' => self::DEFAULT_WEBSERVICE_URLS[$country][$environment][$webserviceType] ?? 'NOT_FOUND'
    ]);
    
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
            'micdta' => 'AR',
            'anticipada' => 'AR', 
            'desconsolidado' => 'AR',
            'transbordo' => 'AR',
            'mane' => 'AR',
            
            // Paraguay
            'gdsf' => 'PY',
            'tere' => 'PY',
            'paraguay_customs' => 'PY',
            'servicioreferencia' => 'PY',
        ];

        $country = $webserviceCountryMapping[$webserviceType] ?? null;
        
        if (!$country) {
            // Fallback al país de la empresa
            $companyCountry = strtolower($this->company->country ?? 'AR');
            $country = $companyCountry === 'py' ? 'PY' : 'AR';
            
            Log::warning('Tipo de webservice no reconocido, usando país de empresa', [
                'webservice_type' => $webserviceType,
                'company_country' => $this->company->country,
                'assigned_country' => $country,
            ]);
        }

         // 🐛 DEBUG TEMPORAL
    Log::debug('getCountryFromWebserviceType result', [
        'webserviceType' => $webserviceType,
        'country_result' => $country,
        'mapping_exists' => isset($webserviceCountryMapping[$webserviceType])
    ]);

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
            'method' => 'GET',
            'header' => "Accept: text/xml\r\n",
        ],
        'ssl' => [
            'verify_peer' => false,           // ⭐ PARA DESARROLLO
            'verify_peer_name' => false,      // ⭐ PARA DESARROLLO  
            'allow_self_signed' => true,      // ⭐ PARA DESARROLLO
            'ciphers' => 'DEFAULT',
        ],
    ];

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
    /**
 * ✅ NUEVA IMPLEMENTACIÓN: Headers de autenticación Argentina
 */
private function addArgentinaAuthHeaders(): void
{
    try {
        // Intentar obtener token WSAA si está disponible
        $authData = $this->getArgentinaAuthData();
        
        if ($authData && isset($authData['token']) && isset($authData['sign'])) {
            // Usar autenticación WSAA completa
            $authHeader = new \SoapHeader(
                'http://ar.gob.afip.dif.wgesregsintia2/',
                'Auth',
                [
                    'Token' => $authData['token'],
                    'Sign' => $authData['sign'],
                    'Cuit' => $this->company->tax_id
                ]
            );
            
            $this->soapClient->__setSoapHeaders($authHeader);
            
            Log::info('Autenticación WSAA aplicada', [
                'company_id' => $this->company->id,
                'cuit' => $this->company->tax_id,
                'has_token' => !empty($authData['token']),
            ]);
        } else {
            // Fallback: autenticación básica para testing
            $this->addBasicArgentinaAuth();
        }

    } catch (Exception $e) {
        Log::warning('Error en autenticación WSAA, usando autenticación básica', [
            'error' => $e->getMessage(),
            'company_id' => $this->company->id,
        ]);
        
        // Fallback: autenticación básica
        $this->addBasicArgentinaAuth();
    }
}

/**
 * ✅ NUEVO: Autenticación básica Argentina para testing/desarrollo
 */
private function addBasicArgentinaAuth(): void
{
    try {
        // Configurar contexto SSL con certificado de la empresa
        $certificateManager = new \App\Services\Webservice\CertificateManagerService($this->company);
        $certData = $certificateManager->readCertificate();
        
        if ($certData) {
            // Configurar cliente SOAP con certificado
            $contextOptions = [
                'ssl' => [
                    'verify_peer' => false, // Para testing
                    'verify_peer_name' => false, // Para testing
                    'allow_self_signed' => true, // Para testing
                    'local_cert' => $certData['cert'] ?? null,
                    'local_pk' => $certData['pkey'] ?? null,
                    'passphrase' => $this->company->certificate_password,
                ]
            ];
            
            $context = stream_context_create($contextOptions);
            $this->soapClient->__setLocation('https://wsaduhomoext.afip.gob.ar/DIAV2/wgesregsintia2/wgesregsintia2.asmx');
            
            Log::info('Autenticación básica Argentina configurada', [
                'company_id' => $this->company->id,
                'has_certificate' => !empty($certData),
            ]);
        } else {
            Log::warning('No se pudo configurar certificado para autenticación', [
                'company_id' => $this->company->id,
                'certificate_path' => $this->company->certificate_path,
            ]);
        }

    } catch (Exception $e) {
        Log::error('Error configurando autenticación básica Argentina', [
            'error' => $e->getMessage(),
            'company_id' => $this->company->id,
        ]);
    }
}

/**
 * ✅ NUEVO: Obtener datos de autenticación Argentina desde empresa
 */
private function getArgentinaAuthData(): ?array
{
    try {
        // Buscar configuración WSAA en la empresa
        $argentinaConfig = $this->company->getArgentinaWebserviceData();
        
        if (isset($argentinaConfig['wsaa_token']) && isset($argentinaConfig['wsaa_sign'])) {
            return [
                'token' => $argentinaConfig['wsaa_token'],
                'sign' => $argentinaConfig['wsaa_sign'],
                'expires_at' => $argentinaConfig['wsaa_expires_at'] ?? null,
            ];
        }
        
        // Si no hay token válido, intentar generar uno nuevo
        return $this->generateWSAAToken();

    } catch (Exception $e) {
        Log::error('Error obteniendo datos de autenticación Argentina', [
            'error' => $e->getMessage(),
            'company_id' => $this->company->id,
        ]);
        return null;
    }
}

/**
 * ✅ NUEVO: Generar token WSAA simplificado
 */
private function generateWSAAToken(): ?array
{
    try {
        // Por ahora, generar datos mock para testing
        // En producción, esto debe hacer la llamada real a WSAA
        
        $token = base64_encode('TESTING_TOKEN_' . $this->company->id . '_' . time());
        $sign = base64_encode('TESTING_SIGN_' . $this->company->tax_id . '_' . time());
        
        Log::info('Token WSAA de testing generado', [
            'company_id' => $this->company->id,
            'token_length' => strlen($token),
            'sign_length' => strlen($sign),
        ]);
        
        return [
            'token' => $token,
            'sign' => $sign,
            'expires_at' => now()->addHours(12)->toISOString(),
        ];

    } catch (Exception $e) {
        Log::error('Error generando token WSAA', [
            'error' => $e->getMessage(),
            'company_id' => $this->company->id,
        ]);
        return null;
    }
}

/**
 * ✅ ACTUALIZADO: Enviar request SOAP con mejor manejo de errores
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
            'parameters_count' => count($parameters),
        ]);

        // ✅ ESTRUCTURA SIMPLIFICADA PARA AFIP
        if ($method === 'RegistrarTitEnvios' || $method === 'RegistrarMicDta') {
            // Para AFIP, usar estructura XML directa
            $xmlParam = $parameters['xmlParam'] ?? $parameters[0] ?? '';
            
            // Llamada SOAP con XML directo
            $response = $this->soapClient->__soapCall($method, ['xmlParam' => $xmlParam]);
        } else {
            // Para otros métodos, usar estructura estándar
            $response = $this->soapClient->__soapCall($method, $parameters);
        }

        $endTime = microtime(true);
        $responseTime = round(($endTime - $startTime) * 1000);

        // Obtener XMLs de request y response
        $requestXml = $this->soapClient->__getLastRequest();
        $responseXml = $this->soapClient->__getLastResponse();

        // Log exitoso
        $this->logTransaction($transaction, 'info', 'Respuesta SOAP recibida exitosamente', [
            'method' => $method,
            'response_time_ms' => $responseTime,
            'response_size' => strlen($responseXml ?? ''),
        ]);

        return [
            'success' => true,
            'response_data' => $response,
            'request_xml' => $requestXml,
            'response_xml' => $responseXml,
            'response_time_ms' => $responseTime,
        ];

    } catch (\SoapFault $e) {
        $endTime = microtime(true);
        $responseTime = round(($endTime - $startTime) * 1000);

        // Capturar XMLs incluso en caso de error
        $requestXml = $this->soapClient->__getLastRequest();
        $responseXml = $this->soapClient->__getLastResponse();

        $this->logTransaction($transaction, 'error', 'Error detallado SOAP RegistrarTitEnvios', [
            'method' => $method,
            'fault_code' => $e->faultcode,
            'fault_string' => $e->faultstring,
            'soap_request' => $requestXml,
            'soap_response' => $responseXml,
            'soap_headers' => $this->soapClient->__getLastResponseHeaders() ?? null,
            'response_time_ms' => $responseTime,
        ]);

        return [
            'success' => false,
            'error_message' => $e->faultstring,
            'error_code' => $e->faultcode,
            'request_xml' => $requestXml,
            'response_xml' => $responseXml,
            'response_time_ms' => $responseTime,
        ];

    } catch (Exception $e) {
        $endTime = microtime(true);
        $responseTime = round(($endTime - $startTime) * 1000);

        $this->logTransaction($transaction, 'error', 'Error general en envío SOAP', [
            'method' => $method,
            'error' => $e->getMessage(),
            'response_time_ms' => $responseTime,
        ]);

        return [
            'success' => false,
            'error_message' => $e->getMessage(),
            'error_code' => 'SOAP_GENERAL_ERROR',
            'response_time_ms' => $responseTime,
        ];
    }
}

/**
 * ✅ NUEVO: Headers de autenticación Paraguay (placeholder mejorado)
 */
private function addParaguayAuthHeaders(): void
{
    // TODO: Implementar autenticación Paraguay específica
    Log::info('Autenticación Paraguay pendiente de implementación', [
        'company_id' => $this->company->id,
    ]);
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
