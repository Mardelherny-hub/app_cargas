<?php

namespace App\Services\Simple;

use App\Models\Company;
use App\Models\WsaaToken;
use Exception;
use Illuminate\Support\Facades\Log;
use SoapClient;

/**
 * Servicio WSAA para DNA Paraguay
 * 
 * Gestiona la autenticación con el servicio WSAA de la Dirección Nacional de Aduanas
 * de Paraguay para obtener tokens dinámicos (ticket y firma) necesarios para invocar
 * los webservices GDSF.
 * 
 * FLUJO:
 * 1. Generar TRA (Ticket Request Authentication) XML
 * 2. Firmar TRA con certificado .pem de la empresa
 * 3. Enviar TRA firmado a WSAA Paraguay
 * 4. Recibir y cachear token + sign
 * 5. Reutilizar token/sign hasta expiración
 * 
 * Basado en el patrón exitoso de AfipWsaaService.php
 */
class ParaguayWsaaService
{
    /**
     * @var Company Empresa solicitante
     */
    private Company $company;

    /**
     * @var string Ambiente (testing/production)
     */
    private string $environment;

    /**
     * URLs del servicio WSAA según ambiente
     */
    private const WSAA_URLS = [
        'testing' => 'https://securetest.aduana.gov.py/wsaaserver/Server?wsdl',
        'production' => 'https://secure.aduana.gov.py/wsaaserver/Server?wsdl',
    ];

    private const WSAA_DESTINATION = [
        'testing' => 'C=py, O=dna, OU=sofia, CN=wsaatest',
        'production' => 'C=py, O=dna, OU=sofia, CN=wsaa',
    ];

    /**
     * Servicio TRA para DNA Paraguay (según correo DNA)
     */
    private const SERVICE_NAME = 'serviciotemaflu';

    /**
     * Constructor
     */
    public function __construct(Company $company, string $environment = 'testing')
    {
        $this->company = $company;
        $this->environment = $environment;
    }

    /**
     * Obtener tokens WSAA (token y sign)
     * Verifica cache primero, genera nuevo si es necesario
     * 
     * @return array ['token' => string, 'sign' => string, 'ruc' => string]
     * @throws Exception
     */
    public function getTokens(): array
    {
        try {
            // 1. Verificar cache primero
            $cachedToken = WsaaToken::getValidToken(
                $this->company->id,
                self::SERVICE_NAME,
                $this->environment
            );

            if ($cachedToken) {
                $cachedToken->markAsUsed();
                
                Log::info('WSAA Paraguay: Token en cache reutilizado', [
                    'company_id' => $this->company->id,
                    'expires_at' => $cachedToken->expires_at,
                ]);

                return [
                    'token' => $cachedToken->token,
                    'sign' => $cachedToken->sign,
                    'ruc' => $this->company->tax_id,
                ];
            }

            // 2. No hay token válido, generar nuevo
            Log::info('WSAA Paraguay: Generando nuevo token', [
                'company_id' => $this->company->id,
                'environment' => $this->environment,
            ]);

            $tokens = $this->generateNewTokens();

            // 3. Cachear en base de datos
            WsaaToken::createToken([
                'company_id' => $this->company->id,
                'service_name' => self::SERVICE_NAME,
                'environment' => $this->environment,
                'token' => $tokens['token'],
                'sign' => $tokens['sign'],
                'issued_at' => now(),
                'expires_at' => now()->addHours(12), // Paraguay: 12 horas como AFIP
                'generation_time' => date('c'),
                'unique_id' => uniqid('py_', true),
                'certificate_used' => $this->getCertificatePath(),
                'usage_count' => 0,
                'status' => 'active',
                'created_by_process' => 'ParaguayWsaaService',
                'creation_context' => [
                    'method' => 'getTokens',
                    'service' => self::SERVICE_NAME,
                    'environment' => $this->environment,
                ],
            ]);

            return [
                'token' => $tokens['token'],
                'sign' => $tokens['sign'],
                'ruc' => $this->company->tax_id,
            ];

        } catch (Exception $e) {
            Log::error('WSAA Paraguay ERROR', [
                'company_id' => $this->company->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new Exception("Error obteniendo tokens WSAA Paraguay: " . $e->getMessage());
        }
    }

    /**
     * Generar nuevos tokens llamando a WSAA
     * 
     * @return array ['token' => string, 'sign' => string]
     * @throws Exception
     */
    private function generateNewTokens(): array
    {
        // 1. Generar TRA (Ticket Request)
        $tra = $this->generateTRA();

        // 2. Firmar TRA con certificado
        $signedTRA = $this->signTRA($tra);

        // 3. Llamar a WSAA
        $response = $this->callWSAA($signedTRA);

        return $response;
    }

    /**
     * Generar TRA (Ticket Request Authentication) XML
     * Estructura según especificación WSAA Paraguay
     * 
     * @return string XML del TRA
     */
    private function generateTRA(): string
    {
        $uniqueId = time();
        $generationTime = date('c', $uniqueId - 60);
        $expirationTime = date('c', $uniqueId + 60);

        // Obtener DNs
        $source = $this->getCertificateDN();
        $destination = $this->getWsaaDestination();

        $tra = '<?xml version="1.0" encoding="UTF-8"?>' .
    '<loginTicketRequest version="1.0">' .
    '<header>' .
    '<source>' . $source . '</source>' .
    '<destination>' . $destination . '</destination>' .
    '<uniqueId>' . $uniqueId . '</uniqueId>' .
    '<generationTime>' . $generationTime . '</generationTime>' .
    '<expirationTime>' . $expirationTime . '</expirationTime>' .
    '</header>' .
    '<service>' . $this->getServiceName() . '</service>' .
    '</loginTicketRequest>';

        Log::debug('WSAA Paraguay: TRA generado', [
    'uniqueId' => $uniqueId,
    'source' => $source,
    'destination' => $destination,
    'service' => self::SERVICE_NAME,
    'tra_xml_completo' => $tra,
]);

return $tra;
    }

    /**
     * Firmar TRA con certificado .pem usando OpenSSL
     * SIMPLIFICADO: Usa archivos separados de Paraguay (cert_path + key_path)
     * 
     * @param string $tra XML del TRA sin firmar
     * @return string TRA firmado en base64
     * @throws Exception
     */
    private function signTRA(string $tra): string
    {
        // 1. Obtener datos del certificado Paraguay
        $certificate = $this->company->getCertificate('paraguay');
        
        if (!$certificate) {
            throw new Exception("Certificado Paraguay no encontrado para la empresa");
        }

        // 2. Determinar rutas según estructura (nueva o legacy)
        if (isset($certificate['cert_path']) && isset($certificate['key_path'])) {
            // NUEVA ESTRUCTURA: archivos separados
            $certPath = storage_path('app/private/' . $certificate['cert_path']);
            $keyPath = storage_path('app/private/' . $certificate['key_path']);
            
            Log::debug('WSAA Paraguay: Usando estructura nueva (archivos separados)', [
                'cert_path' => $certificate['cert_path'],
                'key_path' => $certificate['key_path'],
            ]);
        } elseif (isset($certificate['path'])) {
            // ESTRUCTURA LEGACY: archivo único combinado, necesita separar
            $pemPath = storage_path('app/private/' . $certificate['path']);
            
            if (!file_exists($pemPath)) {
                throw new Exception("Archivo de certificado no existe: {$pemPath}");
            }
            
            $pemContent = file_get_contents($pemPath);
            
            // Extraer certificado
            $certStart = strpos($pemContent, '-----BEGIN CERTIFICATE-----');
            $certEnd = strpos($pemContent, '-----END CERTIFICATE-----');
            if ($certStart === false || $certEnd === false) {
                throw new Exception("No se encontró el certificado en el archivo PEM");
            }
            $certOnly = substr($pemContent, $certStart, $certEnd - $certStart + strlen('-----END CERTIFICATE-----'));

            // Extraer clave privada
            $keyContent = '';
            if (strpos($pemContent, '-----BEGIN PRIVATE KEY-----') !== false) {
                $keyStart = strpos($pemContent, '-----BEGIN PRIVATE KEY-----');
                $keyEnd = strpos($pemContent, '-----END PRIVATE KEY-----');
                $keyContent = substr($pemContent, $keyStart, $keyEnd - $keyStart + strlen('-----END PRIVATE KEY-----'));
            } elseif (strpos($pemContent, '-----BEGIN RSA PRIVATE KEY-----') !== false) {
                $keyStart = strpos($pemContent, '-----BEGIN RSA PRIVATE KEY-----');
                $keyEnd = strpos($pemContent, '-----END RSA PRIVATE KEY-----');
                $keyContent = substr($pemContent, $keyStart, $keyEnd - $keyStart + strlen('-----END RSA PRIVATE KEY-----'));
            }
            
            if (empty($keyContent)) {
                throw new Exception("No se encontró la clave privada en el archivo PEM");
            }

            // Crear archivos temporales
            $certPath = tempnam(sys_get_temp_dir(), 'cert_');
            $keyPath = tempnam(sys_get_temp_dir(), 'key_');
            file_put_contents($certPath, $certOnly);
            file_put_contents($keyPath, $keyContent);
            
            $tempFiles = true;
            
            Log::debug('WSAA Paraguay: Usando estructura legacy (archivo combinado)', [
                'path' => $certificate['path'],
            ]);
        } else {
            throw new Exception("Estructura de certificado Paraguay inválida");
        }

        // 3. Verificar que los archivos existen
        if (!file_exists($certPath)) {
            throw new Exception("Archivo de certificado no existe: {$certPath}");
        }
        if (!file_exists($keyPath)) {
            throw new Exception("Archivo de clave privada no existe: {$keyPath}");
        }

        // 4. Crear archivos temporales para TRA y firma
        $traFile = tempnam(sys_get_temp_dir(), 'tra_');
        $signedFile = tempnam(sys_get_temp_dir(), 'signed_');

        try {
            // Escribir TRA a archivo temporal
            file_put_contents($traFile, $tra);

            // 5. Firmar con OpenSSL - archivos separados
            $command = sprintf(
                'openssl smime -sign -in %s -out %s -signer %s -inkey %s -outform DER -nodetach 2>&1',
                escapeshellarg($traFile),
                escapeshellarg($signedFile),
                escapeshellarg($certPath),
                escapeshellarg($keyPath)
            );

            exec($command, $output, $returnCode);

            if ($returnCode !== 0) {
                Log::error('WSAA Paraguay: Error en comando OpenSSL', [
                    'command' => $command,
                    'output' => $output,
                    'return_code' => $returnCode,
                ]);
                
                throw new Exception("Error firmando TRA con OpenSSL: " . implode(', ', $output));
            }

            // 6. Leer firma DER y codificar en base64
            $signedContent = file_get_contents($signedFile);
            
            if ($signedContent === false) {
                throw new Exception("No se pudo leer el TRA firmado");
            }

            $signedBase64 = base64_encode($signedContent);

            Log::debug('WSAA Paraguay: TRA firmado exitosamente', [
                'signed_length' => strlen($signedBase64),
            ]);

            return $signedBase64;

        } finally {
            // Limpiar archivos temporales
            @unlink($traFile);
            @unlink($signedFile);
            
            // Si usamos estructura legacy, limpiar archivos temporales de cert/key
            if (isset($tempFiles) && $tempFiles) {
                @unlink($certPath);
                @unlink($keyPath);
            }
        }
    }

    /**
     * Llamar al servicio WSAA Paraguay con TRA firmado
     * 
     * @param string $signedTRA TRA firmado en base64
     * @return array ['token' => string, 'sign' => string]
     * @throws Exception
     */
    private function callWSAA(string $signedTRA): array
    {
        $wsdlUrl = $this->getWsaaUrl();

        Log::info('WSAA Paraguay: Llamando a WSAA', [
            'url' => $wsdlUrl,
            'environment' => $this->environment,
        ]);

        try {
            // Crear cliente SOAP
            $client = new SoapClient($wsdlUrl, [
                'trace' => true,
                'exceptions' => true,
                'soap_version' => SOAP_1_1,
                'stream_context' => stream_context_create([
                    'ssl' => [
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                        'allow_self_signed' => true,
                    ]
                ])
            ]);

            // Llamar al método loginCms (mismo que AFIP)
            // Llamar al método loginCms (DNA Paraguay usa parámetro directo, no array)
           // Loguear CMS antes de enviar
Log::debug('WSAA Paraguay: CMS a enviar', [
    'cms_length' => strlen($signedTRA),
    'cms_preview' => substr($signedTRA, 0, 100) . '...',
]);

// Inspeccionar funciones disponibles en el WSDL
Log::debug('WSAA Paraguay: Funciones WSDL', [
    'functions' => $client->__getFunctions(),
    'types' => $client->__getTypes(),
]);

// Enviar CMS como string directo (el WSDL de DNA puede esperarlo así)
$response = $client->__soapCall('loginCms', [$signedTRA]);

// Loguear request/response SOAP completo para diagnóstico
Log::debug('WSAA Paraguay: SOAP Request enviado', [
    'request' => $client->__getLastRequest(),
]);
Log::debug('WSAA Paraguay: SOAP Response recibido', [
    'response' => $client->__getLastResponse(),
]);

            // DNA Paraguay devuelve "return", no "loginCmsReturn" como AFIP
            $loginTicketXml = null;
            
            if (isset($response->return)) {
                $loginTicketXml = $response->return;
            } elseif (isset($response->loginCmsReturn)) {
                $loginTicketXml = $response->loginCmsReturn;
            } elseif (is_string($response)) {
                $loginTicketXml = $response;
            }
            
            if (empty($loginTicketXml)) {
                Log::error('WSAA Paraguay: Respuesta sin datos', [
                    'response_type' => gettype($response),
                    'response_keys' => is_object($response) ? get_object_vars($response) : 'N/A',
                ]);
                throw new Exception("Respuesta WSAA inválida: no contiene return ni loginCmsReturn");
            }
            
            Log::debug('WSAA Paraguay: LoginTicket XML recibido', [
                'xml_length' => strlen($loginTicketXml),
                'xml_preview' => substr($loginTicketXml, 0, 200),
            ]);

            // Parsear XML de respuesta
            $xml = simplexml_load_string($loginTicketXml);

            if ($xml === false) {
                throw new Exception("Error parseando XML de respuesta WSAA");
            }

            // Extraer token y sign
            $token = (string)$xml->credentials->token;
            $sign = (string)$xml->credentials->sign;

            if (empty($token) || empty($sign)) {
                throw new Exception("Token o Sign vacíos en respuesta WSAA");
            }

            Log::info('WSAA Paraguay: Tokens obtenidos exitosamente', [
                'token_length' => strlen($token),
                'sign_length' => strlen($sign),
            ]);

            return [
                'token' => $token,
                'sign' => $sign,
            ];

        } catch (\SoapFault $e) {
            Log::error('WSAA Paraguay SOAP Fault', [
                'faultcode' => $e->faultcode,
                'faultstring' => $e->faultstring,
            ]);

            throw new Exception("SOAP Fault en WSAA: {$e->faultstring}");
        }
    }

    /**
     * Obtener URL del servicio WSAA según ambiente
     */
    private function getWsaaUrl(): string
    {
        return self::WSAA_URLS[$this->environment] ?? self::WSAA_URLS['testing'];
    }

    /**
     * Obtener nombre del servicio TRA
     */
    private function getServiceName(): string
    {
        return self::SERVICE_NAME;
    }

    /**
     * Obtener ruta del certificado
     */
    private function getCertificatePath(): ?string
    {
        $certificate = $this->company->getCertificate('paraguay');
        return $certificate['path'] ?? null;
    }

    /**
     * Extraer DN (Distinguished Name) del certificado
     * 
     * @return string DN del certificado
     * @throws Exception
     */
    /**
     * Extraer DN (Distinguished Name) del certificado
     * 
     * @return string DN del certificado
     * @throws Exception
     */
    private function getCertificateDN(): string
    {
        $certificate = $this->company->getCertificate('paraguay');
        
        if (!$certificate) {
            throw new Exception("Certificado Paraguay no encontrado");
        }

        // Soportar ambas estructuras
        if (isset($certificate['cert_path'])) {
            $certPath = storage_path('app/private/' . $certificate['cert_path']);
        } else {
            $certPath = storage_path('app/private/' . $certificate['path']);
        }
        
        if (!file_exists($certPath)) {
            throw new Exception("Archivo de certificado no existe: {$certPath}");
        }

        // Leer contenido del certificado
        $certContent = file_get_contents($certPath);
        
        // Extraer el DN usando OpenSSL
        $certData = openssl_x509_parse($certContent);
        
        if (!$certData || !isset($certData['subject'])) {
            throw new Exception("No se pudo parsear el certificado para extraer DN");
        }

        // Construir DN desde subject
        $subject = $certData['subject'];
        $dnParts = [];
        
        // Orden estándar: CN, OU, O, L, ST, C
        // Manejar campos que pueden ser arrays
        if (isset($subject['CN'])) {
            $dnParts[] = 'CN=' . (is_array($subject['CN']) ? implode('.', $subject['CN']) : $subject['CN']);
        }
        if (isset($subject['OU'])) {
            $dnParts[] = 'OU=' . (is_array($subject['OU']) ? implode('.', $subject['OU']) : $subject['OU']);
        }
        if (isset($subject['O'])) {
            $dnParts[] = 'O=' . (is_array($subject['O']) ? implode('.', $subject['O']) : $subject['O']);
        }
        if (isset($subject['L'])) {
            $dnParts[] = 'L=' . (is_array($subject['L']) ? implode('.', $subject['L']) : $subject['L']);
        }
        if (isset($subject['ST'])) {
            $dnParts[] = 'ST=' . (is_array($subject['ST']) ? implode('.', $subject['ST']) : $subject['ST']);
        }
        if (isset($subject['C'])) {
            $dnParts[] = 'C=' . (is_array($subject['C']) ? implode('.', $subject['C']) : $subject['C']);
        }

        $dn = implode(', ', $dnParts);
        
        Log::debug('WSAA Paraguay: DN extraído del certificado', ['dn' => $dn]);
        
        return $dn;
    }

    /**
     * Obtener DN del servidor WSAA (destination)
     */
    private function getWsaaDestination(): string
    {
        return self::WSAA_DESTINATION[$this->environment] ?? self::WSAA_DESTINATION['testing'];
    }

    /**
     * Convertir certificado .p12 a formato .pem
     * 
     * @param string $p12Path Ruta al archivo .p12
     * @param string $password Contraseña del certificado (puede estar encriptada)
     * @return string Contenido PEM (certificado + clave privada)
     * @throws Exception
     */
    private function convertP12ToPem(string $p12Path, string $password): string
    {
        try {
            // CRÍTICO: Desencriptar password si está encriptada
            $plainPassword = $password;
            
            if (!empty($password)) {
                try {
                    $plainPassword = decrypt($password);
                    Log::debug('WSAA Paraguay: Contraseña desencriptada exitosamente');
                } catch (\Exception $e) {
                    // Si falla decrypt, asumir que ya está en texto plano
                    Log::debug('WSAA Paraguay: Contraseña ya está en texto plano', [
                        'error' => $e->getMessage()
                    ]);
                }
            }

            Log::info('WSAA Paraguay: Intentando leer .p12', [
                'path' => $p12Path,
                'password_length' => strlen($plainPassword),
                'password_empty' => empty($plainPassword),
            ]);

            // Leer contenido del .p12
            $p12Content = file_get_contents($p12Path);
            
            if ($p12Content === false) {
                throw new Exception("No se pudo leer el archivo .p12");
            }

            // Parsear el archivo PKCS12
            $certs = [];
            $result = openssl_pkcs12_read($p12Content, $certs, $plainPassword);
            
            if (!$result) {
                // Obtener error detallado de OpenSSL
                $opensslError = '';
                while ($msg = openssl_error_string()) {
                    $opensslError .= $msg . '; ';
                }
                
                Log::error('WSAA Paraguay: Error OpenSSL al leer .p12', [
                    'openssl_error' => $opensslError,
                    'password_used_length' => strlen($plainPassword),
                ]);
                
                throw new Exception("Error al leer el certificado .p12. Verifique la contraseña. OpenSSL: {$opensslError}");
            }

            // Validar que tenemos certificado y clave privada
            if (!isset($certs['cert']) || !isset($certs['pkey'])) {
                Log::error('WSAA Paraguay: .p12 no contiene cert/pkey', [
                    'keys_found' => array_keys($certs),
                ]);
                throw new Exception("El archivo .p12 no contiene certificado o clave privada válidos");
            }

            // Combinar certificado y clave privada en formato PEM
            $pemContent = $certs['cert'] . "\n" . $certs['pkey'];

            Log::info('WSAA Paraguay: Certificado .p12 convertido a PEM exitosamente', [
                'cert_length' => strlen($certs['cert']),
                'pkey_length' => strlen($certs['pkey']),
            ]);

            return $pemContent;

        } catch (\Exception $e) {
            Log::error('WSAA Paraguay: Error convirtiendo .p12 a PEM', [
                'error' => $e->getMessage(),
                'p12_path' => $p12Path,
                'trace' => $e->getTraceAsString(),
            ]);
            
            throw new Exception("Error procesando certificado .p12: " . $e->getMessage());
        }
    }
}