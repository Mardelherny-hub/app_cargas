<?php

namespace App\Services\Webservice;

use App\Models\Company;
use App\Models\WebserviceLog;
use Exception;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * MÓDULO 4: WEBSERVICES ADUANA - CertificateManagerService
 *
 * Servicio especializado para gestión de certificados digitales .p12
 * Maneja validación, carga, extracción de información y firma XML
 * 
 * Integra con:
 * - Company model (certificate_path, certificate_password, etc.)
 * - SoapClientService para firma de requests XML
 * - Sistema de logs del módulo webservices
 * 
 * Funcionalidades:
 * - Validación de certificados .p12 y passwords
 * - Extracción de metadatos (CN, fecha vencimiento, alias)
 * - Firma XML para webservices SOAP Argentina/Paraguay
 * - Validación de estados y vencimientos
 * - Logging detallado de operaciones
 * 
 * Basado en:
 * - Webservice Argentina AFIP MIC/DTA
 * - Certificados empresariales del sistema Company
 * - Estándares PKCS#12 y firma XML
 */
class CertificateManagerService
{
    private Company $company;
    private array $config;
    private ?array $certificateInfo = null;

    /**
     * Configuración por defecto para certificados
     */
    private const DEFAULT_CONFIG = [
        'supported_formats' => ['p12', 'pfx'],
        'max_file_size' => 2097152, // 2MB
        'signature_algorithm' => 'RSA_SHA256',
        'digest_algorithm' => 'SHA256',
        'canonicalization' => 'http://www.w3.org/2001/10/xml-exc-c14n#',
        'key_store_type' => 'PKCS12',
        'certificate_encoding' => 'base64',
        'validation_strict' => true,
    ];

    /**
     * Errores comunes de certificados
     */
    private const CERTIFICATE_ERRORS = [
        'INVALID_PASSWORD' => 'Contraseña del certificado incorrecta',
        'CERTIFICATE_EXPIRED' => 'Certificado vencido',
        'CERTIFICATE_NOT_YET_VALID' => 'Certificado aún no válido',
        'INVALID_FORMAT' => 'Formato de certificado no válido',
        'FILE_NOT_FOUND' => 'Archivo de certificado no encontrado',
        'CORRUPT_CERTIFICATE' => 'Certificado corrupto o dañado',
        'MISSING_PRIVATE_KEY' => 'Clave privada no encontrada en certificado',
        'WEAK_KEY_SIZE' => 'Tamaño de clave insuficiente (mínimo 2048 bits)',
    ];

    public function __construct(Company $company, array $config = [])
    {
        $this->company = $company;
        $this->config = array_merge(self::DEFAULT_CONFIG, $config);
        
        $this->logOperation('info', 'CertificateManagerService inicializado', [
            'company_id' => $company->id,
            'company_name' => $company->business_name,
            'has_certificate' => $company->has_certificate,
        ]);
    }

    /**
     * Validar certificado completo de la empresa
     */
    public function validateCompanyCertificate(): array
    {
        $this->logOperation('info', 'Iniciando validación completa de certificado');

        $validation = [
            'is_valid' => false,
            'errors' => [],
            'warnings' => [],
            'certificate_info' => null,
            'expires_in_days' => null,
            'can_sign_xml' => false,
        ];

        try {
            // 1. Verificar que existe configuración de certificado
            if (!$this->company->certificate_path) {
                $validation['errors'][] = 'No hay certificado configurado';
                return $validation;
            }

            // 2. Verificar que existe el archivo físico
            if (!Storage::exists($this->company->certificate_path)) {
                $validation['errors'][] = self::CERTIFICATE_ERRORS['FILE_NOT_FOUND'];
                return $validation;
            }

            // 3. Validar password
            if (!$this->company->certificate_password) {
                $validation['errors'][] = 'No hay contraseña configurada para el certificado';
                return $validation;
            }

            // 4. Leer y validar certificado
            $certData = $this->readCertificate();
            if (!$certData) {
                $validation['errors'][] = self::CERTIFICATE_ERRORS['CORRUPT_CERTIFICATE'];
                return $validation;
            }

            // 5. Extraer información del certificado
            $certInfo = $this->extractCertificateInfo($certData);
            if (!$certInfo) {
                $validation['errors'][] = 'No se pudo extraer información del certificado';
                return $validation;
            }

            $validation['certificate_info'] = $certInfo;

            // 6. Validar fechas
            $dateValidation = $this->validateCertificateDates($certInfo);
            if (!$dateValidation['is_valid']) {
                $validation['errors'] = array_merge($validation['errors'], $dateValidation['errors']);
            } else {
                $validation['expires_in_days'] = $dateValidation['expires_in_days'];
                
                // Advertencia si vence pronto
                if ($dateValidation['expires_in_days'] <= 30) {
                    $validation['warnings'][] = "Certificado vence en {$dateValidation['expires_in_days']} días";
                }
            }

            // 7. Validar clave privada
            if (!$this->hasPrivateKey($certData)) {
                $validation['errors'][] = self::CERTIFICATE_ERRORS['MISSING_PRIVATE_KEY'];
            }

            // 8. Validar capacidad de firma
            $validation['can_sign_xml'] = $this->canSignXml($certData);
            if (!$validation['can_sign_xml']) {
                $validation['errors'][] = 'Certificado no puede firmar XML (clave privada o algoritmo incompatible)';
            }

            // 9. Determinar validez general
            $validation['is_valid'] = empty($validation['errors']);

            $this->logOperation(
                $validation['is_valid'] ? 'info' : 'warning',
                'Validación de certificado completada',
                $validation
            );

            return $validation;

        } catch (Exception $e) {
            $validation['errors'][] = 'Error interno: ' . $e->getMessage();
            
            $this->logOperation('error', 'Error validando certificado', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return $validation;
        }
    }

    /**
     * Leer certificado desde storage
     */
    public function readCertificate(): ?array
    {
        try {
            if (!$this->company->certificate_path || !Storage::exists($this->company->certificate_path)) {
                return null;
            }

            $certificateContent = Storage::get($this->company->certificate_path);
            $password = $this->company->certificate_password;

            // Leer certificado PKCS#12
            $certificates = [];
            if (!openssl_pkcs12_read($certificateContent, $certificates, $password)) {
                $this->logOperation('error', 'Error leyendo certificado PKCS#12', [
                    'openssl_error' => openssl_error_string(),
                    'certificate_path' => $this->company->certificate_path,
                ]);
                return null;
            }

            $this->logOperation('info', 'Certificado leído exitosamente', [
                'has_cert' => isset($certificates['cert']),
                'has_pkey' => isset($certificates['pkey']),
                'has_extracerts' => isset($certificates['extracerts']),
            ]);

            return $certificates;

        } catch (Exception $e) {
            $this->logOperation('error', 'Excepción leyendo certificado', [
                'error' => $e->getMessage(),
                'certificate_path' => $this->company->certificate_path ?? 'null',
            ]);
            return null;
        }
    }

    /**
     * Extraer información detallada del certificado
     */
    public function extractCertificateInfo(array $certData): ?array
    {
        try {
            if (!isset($certData['cert'])) {
                return null;
            }

            $certResource = openssl_x509_read($certData['cert']);
            if (!$certResource) {
                return null;
            }

            $certInfo = openssl_x509_parse($certResource);
            if (!$certInfo) {
                return null;
            }

            $info = [
                'subject' => $certInfo['subject'] ?? [],
                'issuer' => $certInfo['issuer'] ?? [],
                'valid_from' => isset($certInfo['validFrom_time_t']) ? 
                    Carbon::createFromTimestamp($certInfo['validFrom_time_t']) : null,
                'valid_to' => isset($certInfo['validTo_time_t']) ? 
                    Carbon::createFromTimestamp($certInfo['validTo_time_t']) : null,
                'serial_number' => $certInfo['serialNumber'] ?? null,
                'signature_algorithm' => $certInfo['signatureTypeSN'] ?? null,
                'version' => $certInfo['version'] ?? null,
                'extensions' => $certInfo['extensions'] ?? [],
            ];

            // Extraer información específica del subject
            $subject = $certInfo['subject'] ?? [];
            $info['common_name'] = $subject['CN'] ?? null;
            $info['organization'] = $subject['O'] ?? null;
            $info['organizational_unit'] = $subject['OU'] ?? null;
            $info['country'] = $subject['C'] ?? null;
            $info['state'] = $subject['ST'] ?? null;
            $info['locality'] = $subject['L'] ?? null;
            $info['email'] = $subject['emailAddress'] ?? null;

            // Información del emisor
            $issuer = $certInfo['issuer'] ?? [];
            $info['issuer_name'] = $issuer['CN'] ?? null;
            $info['issuer_organization'] = $issuer['O'] ?? null;

            // Detalles de la clave
            $publicKey = openssl_pkey_get_public($certResource);
            if ($publicKey) {
                $keyDetails = openssl_pkey_get_details($publicKey);
                $info['key_size'] = $keyDetails['bits'] ?? null;
                $info['key_type'] = $keyDetails['type'] ?? null;
            }

            $this->certificateInfo = $info;

            $this->logOperation('info', 'Información de certificado extraída', [
                'common_name' => $info['common_name'],
                'organization' => $info['organization'],
                'valid_from' => $info['valid_from']?->format('Y-m-d H:i:s'),
                'valid_to' => $info['valid_to']?->format('Y-m-d H:i:s'),
                'key_size' => $info['key_size'],
            ]);

            return $info;

        } catch (Exception $e) {
            $this->logOperation('error', 'Error extrayendo información de certificado', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Validar fechas de vigencia del certificado
     */
    public function validateCertificateDates(array $certInfo): array
    {
        $validation = [
            'is_valid' => false,
            'errors' => [],
            'expires_in_days' => null,
        ];

        try {
            $now = Carbon::now();
            $validFrom = $certInfo['valid_from'];
            $validTo = $certInfo['valid_to'];

            if (!$validFrom || !$validTo) {
                $validation['errors'][] = 'Fechas de validez no disponibles';
                return $validation;
            }

            // Verificar que ya es válido
            if ($now->lt($validFrom)) {
                $validation['errors'][] = self::CERTIFICATE_ERRORS['CERTIFICATE_NOT_YET_VALID'] . 
                    ' (válido desde ' . $validFrom->format('d/m/Y H:i') . ')';
                return $validation;
            }

            // Verificar que no haya vencido
            if ($now->gt($validTo)) {
                $validation['errors'][] = self::CERTIFICATE_ERRORS['CERTIFICATE_EXPIRED'] . 
                    ' (venció el ' . $validTo->format('d/m/Y H:i') . ')';
                return $validation;
            }

            // Calcular días hasta vencimiento
            $validation['expires_in_days'] = $now->diffInDays($validTo, false);
            $validation['is_valid'] = true;

            return $validation;

        } catch (Exception $e) {
            $validation['errors'][] = 'Error validando fechas: ' . $e->getMessage();
            return $validation;
        }
    }

    /**
     * Verificar si el certificado tiene clave privada
     */
    public function hasPrivateKey(array $certData): bool
    {
        return isset($certData['pkey']) && !empty($certData['pkey']);
    }

    /**
     * Verificar si puede firmar XML
     */
    public function canSignXml(array $certData): bool
    {
        try {
            if (!$this->hasPrivateKey($certData)) {
                return false;
            }

            // Verificar que la clave privada sea válida
            $privateKey = openssl_pkey_get_private($certData['pkey']);
            if (!$privateKey) {
                return false;
            }

            // Verificar detalles de la clave
            $keyDetails = openssl_pkey_get_details($privateKey);
            if (!$keyDetails) {
                return false;
            }

            // Verificar tamaño mínimo de clave (2048 bits para RSA)
            $keySize = $keyDetails['bits'] ?? 0;
            if ($keySize < 2048) {
                $this->logOperation('warning', 'Tamaño de clave insuficiente', [
                    'key_size' => $keySize,
                    'minimum_required' => 2048,
                ]);
                return false;
            }

            return true;

        } catch (Exception $e) {
            $this->logOperation('error', 'Error verificando capacidad de firma XML', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Firmar XML con el certificado de la empresa
     */
    public function signXml(string $xmlContent, array $options = []): ?string
    {
        try {
            $this->logOperation('info', 'Iniciando firma XML', [
                'xml_length' => strlen($xmlContent),
                'options' => $options,
            ]);

            // Validar certificado antes de firmar
            $validation = $this->validateCompanyCertificate();
            if (!$validation['is_valid']) {
                $this->logOperation('error', 'Certificado no válido para firma', [
                    'errors' => $validation['errors'],
                ]);
                return null;
            }

            // Leer certificado
            $certData = $this->readCertificate();
            if (!$certData) {
                return null;
            }

            // Preparar opciones de firma
            $signOptions = array_merge([
                'signature_algorithm' => $this->config['signature_algorithm'],
                'digest_algorithm' => $this->config['digest_algorithm'],
                'canonicalization' => $this->config['canonicalization'],
            ], $options);

            // Crear documento XML
            $doc = new \DOMDocument('1.0', 'UTF-8');
            $doc->loadXML($xmlContent);

            // Aplicar firma XML (implementación básica)
            // Nota: Para producción se recomienda usar una librería especializada como xmlseclibs
            $signedXml = $this->applyXmlSignature($doc, $certData, $signOptions);

            if ($signedXml) {
                $this->logOperation('info', 'XML firmado exitosamente', [
                    'signed_xml_length' => strlen($signedXml),
                ]);
            }

            return $signedXml;

        } catch (Exception $e) {
            $this->logOperation('error', 'Error firmando XML', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return null;
        }
    }

    /**
     * Aplicar firma XML (implementación básica)
     * NOTA: Para producción usar librería especializada como xmlseclibs
     */
    private function applyXmlSignature(\DOMDocument $doc, array $certData, array $options): ?string
    {
        try {
            // Esta es una implementación básica para demostrar el flujo
            // En producción se debe usar xmlseclibs u otra librería especializada
            
            $xmlContent = $doc->saveXML();
            
            // Crear hash del contenido XML
            $hash = hash('sha256', $xmlContent);
            
            // Firmar el hash con la clave privada
            $privateKey = openssl_pkey_get_private($certData['pkey']);
            $signature = '';
            
            if (openssl_sign($hash, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
                $signatureBase64 = base64_encode($signature);
                
                // Obtener certificado en base64
                $certificate = $certData['cert'];
                $certLines = explode("\n", $certificate);
                $certBase64 = '';
                $capture = false;
                
                foreach ($certLines as $line) {
                    if (strpos($line, '-----BEGIN CERTIFICATE-----') !== false) {
                        $capture = true;
                        continue;
                    }
                    if (strpos($line, '-----END CERTIFICATE-----') !== false) {
                        break;
                    }
                    if ($capture) {
                        $certBase64 .= trim($line);
                    }
                }

                // Agregar signature al XML (implementación básica)
                $root = $doc->documentElement;
                $signatureElement = $doc->createElement('Signature');
                $signatureElement->setAttribute('xmlns', 'http://www.w3.org/2000/09/xmldsig#');
                
                // SignedInfo
                $signedInfo = $doc->createElement('SignedInfo');
                $canonicalizationMethod = $doc->createElement('CanonicalizationMethod');
                $canonicalizationMethod->setAttribute('Algorithm', $options['canonicalization']);
                $signedInfo->appendChild($canonicalizationMethod);
                
                $signatureMethod = $doc->createElement('SignatureMethod');
                $signatureMethod->setAttribute('Algorithm', 'http://www.w3.org/2001/04/xmldsig-more#rsa-sha256');
                $signedInfo->appendChild($signatureMethod);
                
                $reference = $doc->createElement('Reference');
                $reference->setAttribute('URI', '');
                $transforms = $doc->createElement('Transforms');
                $transform = $doc->createElement('Transform');
                $transform->setAttribute('Algorithm', 'http://www.w3.org/2000/09/xmldsig#enveloped-signature');
                $transforms->appendChild($transform);
                $reference->appendChild($transforms);
                
                $digestMethod = $doc->createElement('DigestMethod');
                $digestMethod->setAttribute('Algorithm', 'http://www.w3.org/2001/04/xmlenc#sha256');
                $reference->appendChild($digestMethod);
                
                $digestValue = $doc->createElement('DigestValue', base64_encode(hash('sha256', $xmlContent, true)));
                $reference->appendChild($digestValue);
                
                $signedInfo->appendChild($reference);
                $signatureElement->appendChild($signedInfo);
                
                // SignatureValue
                $signatureValue = $doc->createElement('SignatureValue', $signatureBase64);
                $signatureElement->appendChild($signatureValue);
                
                // KeyInfo
                $keyInfo = $doc->createElement('KeyInfo');
                $x509Data = $doc->createElement('X509Data');
                $x509Certificate = $doc->createElement('X509Certificate', $certBase64);
                $x509Data->appendChild($x509Certificate);
                $keyInfo->appendChild($x509Data);
                $signatureElement->appendChild($keyInfo);
                
                $root->appendChild($signatureElement);
                
                return $doc->saveXML();
            }
            
            return null;

        } catch (Exception $e) {
            $this->logOperation('error', 'Error aplicando firma XML', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Obtener información actual del certificado de la empresa
     */
    public function getCertificateInfo(): ?array
    {
        if ($this->certificateInfo) {
            return $this->certificateInfo;
        }

        $certData = $this->readCertificate();
        if (!$certData) {
            return null;
        }

        return $this->extractCertificateInfo($certData);
    }

    /**
     * Verificar si la empresa está lista para usar webservices
     */
    public function isReadyForWebservices(): array
    {
        $readiness = [
            'is_ready' => false,
            'checks' => [],
            'errors' => [],
            'warnings' => [],
        ];

        // 1. Verificar que empresa está activa
        $readiness['checks']['company_active'] = $this->company->active;
        if (!$this->company->active) {
            $readiness['errors'][] = 'Empresa inactiva';
        }

        // 2. Verificar webservices habilitados
        $readiness['checks']['webservices_enabled'] = $this->company->ws_active;
        if (!$this->company->ws_active) {
            $readiness['errors'][] = 'Webservices deshabilitados';
        }

        // 3. Validar certificado completo
        $certValidation = $this->validateCompanyCertificate();
        $readiness['checks']['certificate_valid'] = $certValidation['is_valid'];
        $readiness['errors'] = array_merge($readiness['errors'], $certValidation['errors']);
        $readiness['warnings'] = array_merge($readiness['warnings'], $certValidation['warnings']);

        // 4. Verificar URLs de webservices configuradas
        $wsUrls = $this->company->webservice_urls ?? [];
        $readiness['checks']['urls_configured'] = !empty($wsUrls);
        if (empty($wsUrls)) {
            $readiness['errors'][] = 'URLs de webservices no configuradas';
        }

        // 5. Verificar rol de empresa apropiado
        $roles = $this->company->getRoles();
        $webserviceRoles = ['Cargas', 'Desconsolidador', 'Transbordos'];
        $hasWebserviceRole = !empty(array_intersect($roles, $webserviceRoles));
        $readiness['checks']['appropriate_role'] = $hasWebserviceRole;
        if (!$hasWebserviceRole) {
            $readiness['errors'][] = 'Empresa sin rol apropiado para webservices';
        }

        $readiness['is_ready'] = empty($readiness['errors']);

        $this->logOperation('info', 'Verificación de readiness para webservices', $readiness);

        return $readiness;
    }

    /**
     * Actualizar información de certificado en base de datos
     */
    public function updateCertificateInfo(): bool
    {
        try {
            $certInfo = $this->getCertificateInfo();
            if (!$certInfo) {
                return false;
            }

            $updateData = [];

            // Actualizar alias si se puede extraer del CN
            if ($certInfo['common_name'] && !$this->company->certificate_alias) {
                $updateData['certificate_alias'] = $certInfo['common_name'];
            }

            // Actualizar fecha de vencimiento
            if ($certInfo['valid_to']) {
                $updateData['certificate_expires_at'] = $certInfo['valid_to'];
            }

            if (!empty($updateData)) {
                $this->company->update($updateData);
                
                $this->logOperation('info', 'Información de certificado actualizada en BD', $updateData);
                return true;
            }

            return true;

        } catch (Exception $e) {
            $this->logOperation('error', 'Error actualizando información de certificado', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Logging centralizado para el servicio
     */
    private function logOperation(string $level, string $message, array $context = []): void
    {
        $logData = array_merge([
            'service' => 'CertificateManagerService',
            'company_id' => $this->company->id,
            'company_name' => $this->company->business_name,
            'timestamp' => now()->toISOString(),
        ], $context);

        // Log en archivo Laravel
        Log::{$level}($message, $logData);

        // Log en tabla webservice_logs si es necesario
        try {
            WebserviceLog::create([
                'transaction_id' => null, // No hay transacción específica
                'level' => $level,
                'message' => $message,
                'context' => $logData,
            ]);
        } catch (Exception $e) {
            // Evitar loops infinitos si hay problemas con la BD
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
     * Obtener lista de errores conocidos
     */
    public static function getCertificateErrors(): array
    {
        return self::CERTIFICATE_ERRORS;
    }
}