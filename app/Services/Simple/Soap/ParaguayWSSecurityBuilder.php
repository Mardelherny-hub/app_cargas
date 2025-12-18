<?php

namespace App\Services\Simple\Soap;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class ParaguayWSSecurityBuilder
{
    private const NS_WSSE = 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd';
    private const NS_WSU = 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd';
    private const NS_DS = 'http://www.w3.org/2000/09/xmldsig#';
    private const NS_XENC = 'http://www.w3.org/2001/04/xmlenc#';
    private const C14N_ALGORITHM = 'http://www.w3.org/2001/10/xml-exc-c14n#';
    private const SIGNATURE_ALGORITHM = 'http://www.w3.org/2001/04/xmldsig-more#rsa-sha256';
    private const DIGEST_ALGORITHM = 'http://www.w3.org/2001/04/xmlenc#sha256';
    private const X509_VALUE_TYPE = 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-x509-token-profile-1.0#X509v3';
    private const BASE64_ENCODING_TYPE = 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-soap-message-security-1.0#Base64Binary';

    private $clientPrivateKey;
    private $serverPublicKey;
    private string $clientCertificate;
    private string $serverCertificate;

    public function __construct(string $clientCertificate, string $clientPrivateKey, string $serverCertificate)
    {
        $privateKey = openssl_pkey_get_private($clientPrivateKey);
        if (! $privateKey) {
            throw new RuntimeException('Clave privada del certificado Paraguay inválida o faltante.');
        }

        $serverCertResource = openssl_x509_read($serverCertificate);
        if (! $serverCertResource) {
            throw new RuntimeException('Certificado público de la DNA Paraguay inválido.');
        }

        $publicKey = openssl_pkey_get_public($serverCertResource);
        if (! $publicKey) {
            throw new RuntimeException('No se pudo obtener la clave pública de la DNA Paraguay.');
        }

        $this->clientPrivateKey = $privateKey;
        $this->serverPublicKey = $publicKey;
        $this->clientCertificate = $clientCertificate;
        $this->serverCertificate = $serverCertificate;
    }

    public function secure(string $requestXml): string
    {
        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->preserveWhiteSpace = false;
        $doc->formatOutput = false;

        if (! $doc->loadXML($requestXml)) {
            throw new RuntimeException('No se pudo preparar el XML SOAP para firmar/encriptar.');
        }

        $envelope = $doc->documentElement;
        $soapNamespace = $envelope->namespaceURI;
        if (! $soapNamespace) {
            throw new RuntimeException('SOAP Envelope sin namespace, no se puede aplicar WS-Security.');
        }

        $this->ensureNamespace($envelope, 'wsse', self::NS_WSSE);
        $this->ensureNamespace($envelope, 'wsu', self::NS_WSU);
        $this->ensureNamespace($envelope, 'ds', self::NS_DS);
        $this->ensureNamespace($envelope, 'xenc', self::NS_XENC);

        $header = $this->getOrCreateChild($doc, $envelope, $soapNamespace, 'Header');
        $body = $this->getFirstChild($envelope, $soapNamespace, 'Body');

        $security = $this->createSecurityNode($doc, $header, $soapNamespace);
        $timestampId = $this->appendTimestamp($doc, $security);
        $tokenId = $this->appendBinarySecurityToken($doc, $security);
        $bodyId = $this->ensureNodeHasId($body, 'Body-');

        //$encryptionIds = $this->encryptBody($doc, $body, $security);
        // DNA Paraguay solo requiere firma, NO encriptación del body
        // $encryptionIds = $this->encryptBody($doc, $body, $security);
        $encryptionIds = []; // Sin encriptación
        $this->appendSignature($doc, $security, $bodyId, $timestampId, $tokenId);

        Log::debug('SOAP Paraguay firmado (sin encriptación)', [
            'timestamp_id' => $timestampId,
            'token_id' => $tokenId,
            'body_id' => $bodyId,
            'encrypted_data_id' => $encryptionIds['encrypted_data_id'] ?? null,
            'encrypted_key_id' => $encryptionIds['encrypted_key_id'] ?? null,
        ]);

        return $doc->saveXML();
    }

    private function createSecurityNode(DOMDocument $doc, DOMElement $header, string $soapNamespace): DOMElement
    {
        foreach ($header->childNodes as $child) {
            if ($child instanceof DOMElement && $child->namespaceURI === self::NS_WSSE && $child->localName === 'Security') {
                return $child;
            }
        }

        $security = $doc->createElementNS(self::NS_WSSE, 'wsse:Security');
        $security->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:wsse', self::NS_WSSE);
        $security->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:wsu', self::NS_WSU);
        $security->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:ds', self::NS_DS);
        $security->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xenc', self::NS_XENC);
        $security->setAttributeNS($soapNamespace, $header->prefix ? $header->prefix . ':mustUnderstand' : 'mustUnderstand', '1');

        $header->appendChild($security);

        return $security;
    }

    private function appendTimestamp(DOMDocument $doc, DOMElement $security): string
    {
        $timestampId = 'TS-' . $this->generateId();
        $timestamp = $doc->createElementNS(self::NS_WSU, 'wsu:Timestamp');
        $timestamp->setAttributeNS(self::NS_WSU, 'wsu:Id', $timestampId);

        $paraguayTz = new \DateTimeZone('America/Asuncion');
        $now = new \DateTime('now', $paraguayTz);
        $created = $doc->createElementNS(self::NS_WSU, 'wsu:Created', gmdate('Y-m-d\TH:i:s\Z'));
        $nowExpires = new \DateTime('now', $paraguayTz);
        $nowExpires->modify('+5 minutes');
        $expires = $doc->createElementNS(self::NS_WSU, 'wsu:Expires', gmdate('Y-m-d\TH:i:s\Z', time() + 300));
        $timestamp->appendChild($created);
        $timestamp->appendChild($expires);

        $security->appendChild($timestamp);

        return $timestampId;
    }

    private function appendBinarySecurityToken(DOMDocument $doc, DOMElement $security): string
    {
        $tokenId = 'X509-' . $this->generateId();
        $certificateBody = $this->normalizeCertificate($this->clientCertificate);

        $token = $doc->createElementNS(self::NS_WSSE, 'wsse:BinarySecurityToken', $certificateBody);
        $token->setAttribute('EncodingType', self::BASE64_ENCODING_TYPE);
        $token->setAttribute('ValueType', self::X509_VALUE_TYPE);
        $token->setAttributeNS(self::NS_WSU, 'wsu:Id', $tokenId);

        $security->appendChild($token);

        return $tokenId;
    }

    private function encryptBody(DOMDocument $doc, DOMElement $body, DOMElement $security): array
    {
        $bodyContents = '';
        for ($child = $body->firstChild; $child; $child = $child->nextSibling) {
            if ($child instanceof DOMNode) {
                $bodyContents .= $doc->saveXML($child);
            }
        }

        $sessionKey = random_bytes(32);
        $initializationVector = random_bytes(16);
        $cipherText = openssl_encrypt($bodyContents, 'aes-256-cbc', $sessionKey, OPENSSL_RAW_DATA, $initializationVector);
        if ($cipherText === false) {
            throw new RuntimeException('No se pudo encriptar el cuerpo SOAP para Paraguay.');
        }

        while ($body->firstChild) {
            $body->removeChild($body->firstChild);
        }

        $encryptedDataId = 'ED-' . $this->generateId();
        $encryptedKeyId = 'EK-' . $this->generateId();

        $encryptedData = $doc->createElementNS(self::NS_XENC, 'xenc:EncryptedData');
        $encryptedData->setAttribute('Type', 'http://www.w3.org/2001/04/xmlenc#Content');
        $encryptedData->setAttribute('Id', $encryptedDataId);

        $dataEncryptionMethod = $doc->createElementNS(self::NS_XENC, 'xenc:EncryptionMethod');
        $dataEncryptionMethod->setAttribute('Algorithm', 'http://www.w3.org/2001/04/xmlenc#aes256-cbc');
        $encryptedData->appendChild($dataEncryptionMethod);

        $cipherData = $doc->createElementNS(self::NS_XENC, 'xenc:CipherData');
        $cipherValue = $doc->createElementNS(self::NS_XENC, 'xenc:CipherValue', base64_encode($initializationVector . $cipherText));
        $cipherData->appendChild($cipherValue);
        $encryptedData->appendChild($cipherData);

        $keyInfo = $doc->createElementNS(self::NS_DS, 'ds:KeyInfo');
        $keyTokenRef = $doc->createElementNS(self::NS_WSSE, 'wsse:SecurityTokenReference');
        $keyReference = $doc->createElementNS(self::NS_WSSE, 'wsse:Reference');
        $keyReference->setAttribute('URI', '#' . $encryptedKeyId);
        $keyReference->setAttribute('ValueType', 'http://www.w3.org/2001/04/xmlenc#EncryptedKey');
        $keyTokenRef->appendChild($keyReference);
        $keyInfo->appendChild($keyTokenRef);
        $encryptedData->appendChild($keyInfo);

        $body->appendChild($encryptedData);

        if (! openssl_public_encrypt($sessionKey, $encryptedSessionKey, $this->serverPublicKey, OPENSSL_PKCS1_OAEP_PADDING)) {
            throw new RuntimeException('No se pudo encriptar la clave de sesión para Paraguay.');
        }

        $encryptedKey = $doc->createElementNS(self::NS_XENC, 'xenc:EncryptedKey');
        $encryptedKey->setAttribute('Id', $encryptedKeyId);

        $keyEncryptionMethod = $doc->createElementNS(self::NS_XENC, 'xenc:EncryptionMethod');
        $keyEncryptionMethod->setAttribute('Algorithm', 'http://www.w3.org/2001/04/xmlenc#rsa-oaep-mgf1p');
        $encryptedKey->appendChild($keyEncryptionMethod);

        $keyInfoNode = $doc->createElementNS(self::NS_DS, 'ds:KeyInfo');
        $x509Data = $doc->createElementNS(self::NS_DS, 'ds:X509Data');
        $x509Cert = $doc->createElementNS(self::NS_DS, 'ds:X509Certificate', $this->normalizeCertificate($this->serverCertificate));
        $x509Data->appendChild($x509Cert);
        $keyInfoNode->appendChild($x509Data);
        $encryptedKey->appendChild($keyInfoNode);

        $keyCipherData = $doc->createElementNS(self::NS_XENC, 'xenc:CipherData');
        $keyCipherValue = $doc->createElementNS(self::NS_XENC, 'xenc:CipherValue', base64_encode($encryptedSessionKey));
        $keyCipherData->appendChild($keyCipherValue);
        $encryptedKey->appendChild($keyCipherData);

        $referenceList = $doc->createElementNS(self::NS_XENC, 'xenc:ReferenceList');
        $dataReference = $doc->createElementNS(self::NS_XENC, 'xenc:DataReference');
        $dataReference->setAttribute('URI', '#' . $encryptedDataId);
        $referenceList->appendChild($dataReference);
        $encryptedKey->appendChild($referenceList);

        $security->appendChild($encryptedKey);

        return [
            'encrypted_data_id' => $encryptedDataId,
            'encrypted_key_id' => $encryptedKeyId,
        ];
    }

    private function appendSignature(DOMDocument $doc, DOMElement $security, string $bodyId, string $timestampId, string $tokenId): void
    {
        $signature = $doc->createElementNS(self::NS_DS, 'ds:Signature');
        $signature->setAttribute('Id', 'SIG-' . $this->generateId());

        $signedInfo = $doc->createElementNS(self::NS_DS, 'ds:SignedInfo');

        $canonicalizationMethod = $doc->createElementNS(self::NS_DS, 'ds:CanonicalizationMethod');
        $canonicalizationMethod->setAttribute('Algorithm', self::C14N_ALGORITHM);
        $signedInfo->appendChild($canonicalizationMethod);

        $signatureMethod = $doc->createElementNS(self::NS_DS, 'ds:SignatureMethod');
        $signatureMethod->setAttribute('Algorithm', self::SIGNATURE_ALGORITHM);
        $signedInfo->appendChild($signatureMethod);

        foreach ([$bodyId, $timestampId] as $referenceId) {
            $reference = $doc->createElementNS(self::NS_DS, 'ds:Reference');
            $reference->setAttribute('URI', '#' . $referenceId);

            $transforms = $doc->createElementNS(self::NS_DS, 'ds:Transforms');
            $transform = $doc->createElementNS(self::NS_DS, 'ds:Transform');
            $transform->setAttribute('Algorithm', self::C14N_ALGORITHM);
            $transforms->appendChild($transform);
            $reference->appendChild($transforms);

            $digestMethod = $doc->createElementNS(self::NS_DS, 'ds:DigestMethod');
            $digestMethod->setAttribute('Algorithm', self::DIGEST_ALGORITHM);
            $reference->appendChild($digestMethod);

            $digestValue = $doc->createElementNS(self::NS_DS, 'ds:DigestValue', $this->calculateDigest($doc, $referenceId));
            $reference->appendChild($digestValue);

            $signedInfo->appendChild($reference);
        }

        $signature->appendChild($signedInfo);

        $signedInfoCanonical = $signedInfo->C14N(true, false);
        if (! openssl_sign($signedInfoCanonical, $signatureBinary, $this->clientPrivateKey, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('No se pudo firmar la petición SOAP para Paraguay.');
        }

        $signatureValue = $doc->createElementNS(self::NS_DS, 'ds:SignatureValue', base64_encode($signatureBinary));
        $signature->appendChild($signatureValue);

        $keyInfo = $doc->createElementNS(self::NS_DS, 'ds:KeyInfo');
        $securityTokenReference = $doc->createElementNS(self::NS_WSSE, 'wsse:SecurityTokenReference');
        $reference = $doc->createElementNS(self::NS_WSSE, 'wsse:Reference');
        $reference->setAttribute('URI', '#' . $tokenId);
        $reference->setAttribute('ValueType', self::X509_VALUE_TYPE);
        $securityTokenReference->appendChild($reference);
        $keyInfo->appendChild($securityTokenReference);

        $signature->appendChild($keyInfo);

        $security->appendChild($signature);
    }

    private function ensureNodeHasId(DOMElement $node, string $prefix): string
    {
        $currentId = $node->getAttributeNS(self::NS_WSU, 'Id');
        if ($currentId) {
            return $currentId;
        }

        $newId = $prefix . $this->generateId();
        $node->setAttributeNS(self::NS_WSU, 'wsu:Id', $newId);

        return $newId;
    }

    private function calculateDigest(DOMDocument $doc, string $referenceId): string
    {
        $xpath = new DOMXPath($doc);
        $xpath->registerNamespace('wsu', self::NS_WSU);
        $query = "//*[@wsu:Id='" . $referenceId . "' or @Id='" . $referenceId . "']";
        $node = $xpath->query($query)->item(0);

        if (! $node instanceof DOMNode) {
            throw new RuntimeException("No se encontró el nodo con referencia {$referenceId} para firmar.");
        }

        $canonical = $node->C14N(true, false);
        $digest = hash('sha256', $canonical, true);

        return base64_encode($digest);
    }

    private function getOrCreateChild(DOMDocument $doc, DOMElement $parent, string $namespace, string $localName): DOMElement
    {
        foreach ($parent->childNodes as $child) {
            if ($child instanceof DOMElement && $child->namespaceURI === $namespace && $child->localName === $localName) {
                return $child;
            }
        }

        $prefix = $parent->prefix ?: 'soap';
        $child = $doc->createElementNS($namespace, $prefix . ':' . $localName);
        $parent->insertBefore($child, $parent->firstChild);

        return $child;
    }

    private function getFirstChild(DOMElement $parent, string $namespace, string $localName): DOMElement
    {
        foreach ($parent->childNodes as $child) {
            if ($child instanceof DOMElement && $child->namespaceURI === $namespace && $child->localName === $localName) {
                return $child;
            }
        }

        throw new RuntimeException("SOAP Envelope sin elemento {$localName} requerido.");
    }

    private function ensureNamespace(DOMElement $element, string $prefix, string $uri): void
    {
        if ($element->lookupNamespaceURI($prefix) === $uri) {
            return;
        }

        $element->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:' . $prefix, $uri);
    }

    private function normalizeCertificate(string $certificate): string
    {
        // Extraer solo el certificado X.509 (sin clave privada ni otros elementos)
        if (preg_match('/-----BEGIN CERTIFICATE-----(.+?)-----END CERTIFICATE-----/s', $certificate, $matches)) {
            // Limpiar espacios y saltos de línea
            $clean = preg_replace('/\s+/', '', $matches[1]);
            return $clean;
        }
        
        throw new \RuntimeException('No se pudo extraer el certificado X.509 del archivo');
    }

    private function generateId(): string
    {
        return bin2hex(random_bytes(8));
    }
}