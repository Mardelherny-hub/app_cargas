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
        'testing' => 'https://securetest.aduana.gov.py/wsdl/wsaaserver/Server',
        'production' => 'https://secure.aduana.gov.py/wsdl/wsaaserver/Server',
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
        $generationTime = date('c', $uniqueId - 60); // 1 minuto en el pasado
        $expirationTime = date('c', $uniqueId + 60); // 1 minuto en el futuro

        $tra = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<loginTicketRequest version="1.0">
    <header>
        <uniqueId>{$uniqueId}</uniqueId>
        <generationTime>{$generationTime}</generationTime>
        <expirationTime>{$expirationTime}</expirationTime>
    </header>
    <service>{$this->getServiceName()}</service>
</loginTicketRequest>
XML;

        Log::debug('WSAA Paraguay: TRA generado', [
            'uniqueId' => $uniqueId,
            'service' => self::SERVICE_NAME,
        ]);

        return $tra;
    }

    /**
     * Firmar TRA con certificado .pem usando OpenSSL
     * 
     * @param string $tra XML del TRA sin firmar
     * @return string TRA firmado en base64
     * @throws Exception
     */
    private function signTRA(string $tra): string
    {
        // 1. Obtener certificado Paraguay
        $certificate = $this->company->getCertificate('paraguay');
        
        if (!$certificate) {
            throw new Exception("Certificado Paraguay no encontrado para la empresa");
        }

        $certPath = storage_path('app/private/' . $certificate['path']);
        
        if (!file_exists($certPath)) {
            throw new Exception("Archivo de certificado no existe: {$certPath}");
        }

        // Verificar extensión del certificado (.pem o .p12)
        $extension = strtolower(pathinfo($certPath, PATHINFO_EXTENSION));

        if (!in_array($extension, ['pem', 'p12', 'pfx'])) {
            throw new Exception("El certificado de Paraguay debe ser formato .pem o .p12 (actual: .{$extension})");
        }   
        // 2. Leer certificado según formato
        if (in_array($extension, ['p12', 'pfx'])) {
            // Convertir .p12 a .pem temporal
            $certContent = $this->convertP12ToPem($certPath, $certificate['password'] ?? '');
        } else {
            // Leer .pem directamente
            $certContent = file_get_contents($certPath);
            
            if ($certContent === false) {
                throw new Exception("No se pudo leer el certificado");
            }

            // Validar que contiene BEGIN/END markers
            if (!str_contains($certContent, 'BEGIN CERTIFICATE') && !str_contains($certContent, 'BEGIN RSA PRIVATE KEY')) {
                throw new Exception("El archivo .pem no tiene el formato válido (falta BEGIN CERTIFICATE o BEGIN RSA PRIVATE KEY)");
            }
        }

        // 3. Crear archivos temporales
        $traFile = tempnam(sys_get_temp_dir(), 'tra_');
        $signedFile = tempnam(sys_get_temp_dir(), 'tra_signed_');
        $certFile = tempnam(sys_get_temp_dir(), 'cert_');

        try {
            // Escribir TRA a archivo temporal
            file_put_contents($traFile, $tra);
            file_put_contents($certFile, $certContent);

            // 4. Firmar con OpenSSL (comando exacto según manual DNA)
            $command = sprintf(
                'openssl smime -sign -in %s -signer %s -inkey %s -out %s -outform DER 2>&1',
                escapeshellarg($traFile),
                escapeshellarg($certFile),
                escapeshellarg($certFile),
                escapeshellarg($signedFile)
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

            // 5. Leer firma y codificar en base64
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
            @unlink($certFile);
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
            $response = $client->loginCms(['in0' => $signedTRA]);

            if (!isset($response->loginCmsReturn)) {
                throw new Exception("Respuesta WSAA inválida: no contiene loginCmsReturn");
            }

            // Parsear XML de respuesta
            $xml = simplexml_load_string($response->loginCmsReturn);

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