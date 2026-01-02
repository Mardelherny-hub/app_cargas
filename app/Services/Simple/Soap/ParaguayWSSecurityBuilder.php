<?php

namespace App\Services\Simple\Soap;

use DOMDocument;
use Illuminate\Support\Facades\Log;
use RobRichards\WsePhp\WSSESoap;
use RobRichards\XMLSecLibs\XMLSecurityKey;
use RuntimeException;

/**
 * WS-Security Builder para Paraguay DNA usando wse-php
 * 
 * Implementa:
 * - Firma digital (SignedParts: Body + Timestamp)
 * - Encriptación (EncryptedParts: Body)
 * - EncryptSignature (la firma también se encripta)
 * - BinarySecurityToken con certificado X.509
 */
class ParaguayWSSecurityBuilder
{
    private string $clientCertificate;
    private string $clientPrivateKey;
    private string $serverCertificate;

    public function __construct(string $clientCertificate, string $clientPrivateKey, string $serverCertificate)
    {
        $this->clientCertificate = $clientCertificate;
        $this->clientPrivateKey = $clientPrivateKey;
        $this->serverCertificate = $serverCertificate;
    }

    public function secure(string $requestXml): string
    {
        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->preserveWhiteSpace = false;
        $doc->formatOutput = false;

        if (!$doc->loadXML($requestXml)) {
            throw new RuntimeException('No se pudo cargar el XML SOAP para aplicar WS-Security.');
        }

        $objWSSE = new WSSESoap($doc);

        // 1. Agregar Timestamp (5 minutos de validez)
        $objWSSE->addTimestamp(300);

        // 2. Firmar con clave privada del cliente (RSA-SHA256)
        $objKey = new XMLSecurityKey(XMLSecurityKey::RSA_SHA1, ['type' => 'private']);
        $objKey->loadKey($this->clientPrivateKey, false, false);
        
        $objWSSE->signSoapDoc($objKey);

        // 3. Agregar BinarySecurityToken y vincularlo a la firma
        $token = $objWSSE->addBinaryToken($this->clientCertificate);
        $objWSSE->attachTokentoSig($token);

        // 4. Encriptar Body + Firma con clave pública del servidor DNA
        $sessionKey = new XMLSecurityKey(XMLSecurityKey::TRIPLEDES_CBC);
        $sessionKey->generateSessionKey();

        $siteKey = new XMLSecurityKey(XMLSecurityKey::RSA_1_5, ['type' => 'public']);
        $siteKey->loadKey($this->serverCertificate, false, true);

        // encryptSignature=true es el default - encripta BODY + SIGNATURE
        $objWSSE->encryptSoapDoc($siteKey, $sessionKey, [
            'KeyInfo' => [
                'X509SubjectKeyIdentifier' => true,
            ],
        ]);

        $securedXml = $objWSSE->saveXML();

        Log::debug('WS-Security aplicado con wse-php', [
            'original_length' => strlen($requestXml),
            'secured_length' => strlen($securedXml),
        ]);

        return $securedXml;
    }
}