<?php

namespace App\Services\Simple\Soap;

use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Desencripta respuestas WS-Security de DNA Paraguay
 * 
 * Algoritmos soportados (según especificación DNA):
 * - KeyWrap: RSA 1.5
 * - Cifrado: TripleDES-CBC
 */
class ParaguayWSSecurityDecryptor
{
    private const NS_SOAP = 'http://schemas.xmlsoap.org/soap/envelope/';
    private const NS_WSSE = 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd';
    private const NS_WSU = 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd';
    private const NS_XENC = 'http://www.w3.org/2001/04/xmlenc#';
    private const NS_DS = 'http://www.w3.org/2000/09/xmldsig#';

    private $clientPrivateKey;

    public function __construct(string $clientPrivateKey)
    {
        $privateKey = openssl_pkey_get_private($clientPrivateKey);
        if (!$privateKey) {
            throw new RuntimeException('Clave privada inválida para desencriptar respuesta Paraguay.');
        }
        $this->clientPrivateKey = $privateKey;
    }

    /**
     * Desencripta una respuesta SOAP con WS-Security
     */
    public function decrypt(string $encryptedXml): string
    {
        Log::debug('ParaguayWSSecurityDecryptor: Iniciando desencriptación', [
            'xml_length' => strlen($encryptedXml),
        ]);

        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->preserveWhiteSpace = true;
        
        if (!$doc->loadXML($encryptedXml)) {
            throw new RuntimeException('No se pudo parsear el XML de respuesta.');
        }

        $xpath = new DOMXPath($doc);
        $xpath->registerNamespace('soap', self::NS_SOAP);
        $xpath->registerNamespace('wsse', self::NS_WSSE);
        $xpath->registerNamespace('wsu', self::NS_WSU);
        $xpath->registerNamespace('xenc', self::NS_XENC);
        $xpath->registerNamespace('ds', self::NS_DS);

        // 1. Extraer la clave de sesión encriptada
        $sessionKey = $this->extractAndDecryptSessionKey($xpath);
        
        if (!$sessionKey) {
            Log::warning('ParaguayWSSecurityDecryptor: No se encontró EncryptedKey, retornando XML original');
            return $encryptedXml;
        }

        Log::debug('ParaguayWSSecurityDecryptor: Clave de sesión obtenida', [
            'key_length' => strlen($sessionKey),
        ]);

        // 2. Desencriptar el Body
        $this->decryptBody($doc, $xpath, $sessionKey);

        // 3. Desencriptar elementos en el Header (firma, si existe)
        $this->decryptHeaderElements($doc, $xpath, $sessionKey);

        $decryptedXml = $doc->saveXML();

        Log::info('ParaguayWSSecurityDecryptor: Desencriptación completada', [
            'original_length' => strlen($encryptedXml),
            'decrypted_length' => strlen($decryptedXml),
        ]);

        return $decryptedXml;
    }

    /**
     * Extrae y desencripta la clave de sesión del EncryptedKey
     */
    private function extractAndDecryptSessionKey(DOMXPath $xpath): ?string
    {
        // Buscar EncryptedKey en el Header
        $encryptedKeyNodes = $xpath->query('//xenc:EncryptedKey');
        
        if ($encryptedKeyNodes->length === 0) {
            return null;
        }

        $encryptedKey = $encryptedKeyNodes->item(0);
        
        // Verificar algoritmo
        $encMethodNodes = $xpath->query('.//xenc:EncryptionMethod/@Algorithm', $encryptedKey);
        if ($encMethodNodes->length > 0) {
            $algorithm = $encMethodNodes->item(0)->nodeValue;
            Log::debug('ParaguayWSSecurityDecryptor: Algoritmo KeyWrap', ['algorithm' => $algorithm]);
            
            if ($algorithm !== 'http://www.w3.org/2001/04/xmlenc#rsa-1_5') {
                Log::warning('ParaguayWSSecurityDecryptor: Algoritmo no esperado', ['algorithm' => $algorithm]);
            }
        }

        // Extraer CipherValue
        $cipherValueNodes = $xpath->query('.//xenc:CipherData/xenc:CipherValue', $encryptedKey);
        
        if ($cipherValueNodes->length === 0) {
            throw new RuntimeException('No se encontró CipherValue en EncryptedKey.');
        }

        $encryptedSessionKey = base64_decode($cipherValueNodes->item(0)->nodeValue);
        
        // Desencriptar con RSA PKCS1 v1.5
        $sessionKey = '';
        $result = openssl_private_decrypt(
            $encryptedSessionKey,
            $sessionKey,
            $this->clientPrivateKey,
            OPENSSL_PKCS1_PADDING  // RSA 1.5
        );

        if (!$result) {
            $error = openssl_error_string();
            throw new RuntimeException('Error desencriptando clave de sesión: ' . $error);
        }

        return $sessionKey;
    }

    /**
     * Desencripta el contenido del Body
     */
    private function decryptBody(DOMDocument $doc, DOMXPath $xpath, string $sessionKey): void
    {
        // Buscar EncryptedData en el Body
        $bodyEncryptedData = $xpath->query('//soap:Body/xenc:EncryptedData');
        
        if ($bodyEncryptedData->length === 0) {
            Log::debug('ParaguayWSSecurityDecryptor: No hay EncryptedData en Body');
            return;
        }

        foreach ($bodyEncryptedData as $encryptedData) {
            $this->decryptElement($doc, $xpath, $encryptedData, $sessionKey);
        }
    }

    /**
     * Desencripta elementos en el Header (como la firma encriptada)
     */
    private function decryptHeaderElements(DOMDocument $doc, DOMXPath $xpath, string $sessionKey): void
    {
        // Buscar EncryptedData en el Security header
        $headerEncryptedData = $xpath->query('//wsse:Security/xenc:EncryptedData');
        
        foreach ($headerEncryptedData as $encryptedData) {
            $this->decryptElement($doc, $xpath, $encryptedData, $sessionKey);
        }
    }

    /**
     * Desencripta un elemento EncryptedData individual
     */
    private function decryptElement(DOMDocument $doc, DOMXPath $xpath, \DOMElement $encryptedData, string $sessionKey): void
    {
        // Verificar algoritmo de encriptación
        $encMethodNodes = $xpath->query('.//xenc:EncryptionMethod/@Algorithm', $encryptedData);
        $algorithm = $encMethodNodes->length > 0 ? $encMethodNodes->item(0)->nodeValue : '';
        
        Log::debug('ParaguayWSSecurityDecryptor: Desencriptando elemento', [
            'algorithm' => $algorithm,
            'id' => $encryptedData->getAttribute('Id'),
        ]);

        // Extraer CipherValue
        $cipherValueNodes = $xpath->query('.//xenc:CipherData/xenc:CipherValue', $encryptedData);
        
        if ($cipherValueNodes->length === 0) {
            Log::warning('ParaguayWSSecurityDecryptor: No se encontró CipherValue en EncryptedData');
            return;
        }

        $cipherText = base64_decode($cipherValueNodes->item(0)->nodeValue);
        
        // Desencriptar según algoritmo
        $decryptedContent = $this->decryptData($cipherText, $sessionKey, $algorithm);
        
        if ($decryptedContent === false) {
            Log::error('ParaguayWSSecurityDecryptor: Error desencriptando contenido');
            return;
        }

        Log::debug('ParaguayWSSecurityDecryptor: Contenido desencriptado', [
            'length' => strlen($decryptedContent),
            'preview' => substr($decryptedContent, 0, 200),
        ]);

        // Reemplazar EncryptedData con el contenido desencriptado
        $this->replaceEncryptedData($doc, $encryptedData, $decryptedContent);
    }

    /**
     * Desencripta datos con el algoritmo especificado
     */
    private function decryptData(string $cipherText, string $sessionKey, string $algorithm): string|false
    {
        // TripleDES-CBC (el que usa DNA Paraguay)
        if ($algorithm === 'http://www.w3.org/2001/04/xmlenc#tripledes-cbc') {
            // IV son los primeros 8 bytes
            $ivLength = 8;
            $iv = substr($cipherText, 0, $ivLength);
            $encrypted = substr($cipherText, $ivLength);
            
            // La clave de TripleDES debe ser de 24 bytes
            // Si la clave es más corta, hay que ajustarla
            $keyLength = 24;
            if (strlen($sessionKey) < $keyLength) {
                // Extender la clave si es necesario (K1K2K1 pattern)
                $sessionKey = str_pad($sessionKey, $keyLength, substr($sessionKey, 0, 8));
            } elseif (strlen($sessionKey) > $keyLength) {
                $sessionKey = substr($sessionKey, 0, $keyLength);
            }
            
            return openssl_decrypt(
                $encrypted,
                'des-ede3-cbc',
                $sessionKey,
                OPENSSL_RAW_DATA,
                $iv
            );
        }
        
        // AES-256-CBC (respaldo)
        if ($algorithm === 'http://www.w3.org/2001/04/xmlenc#aes256-cbc') {
            $ivLength = 16;
            $iv = substr($cipherText, 0, $ivLength);
            $encrypted = substr($cipherText, $ivLength);
            
            return openssl_decrypt(
                $encrypted,
                'aes-256-cbc',
                $sessionKey,
                OPENSSL_RAW_DATA,
                $iv
            );
        }

        // AES-128-CBC (respaldo)
        if ($algorithm === 'http://www.w3.org/2001/04/xmlenc#aes128-cbc') {
            $ivLength = 16;
            $iv = substr($cipherText, 0, $ivLength);
            $encrypted = substr($cipherText, $ivLength);
            
            return openssl_decrypt(
                $encrypted,
                'aes-128-cbc',
                $sessionKey,
                OPENSSL_RAW_DATA,
                $iv
            );
        }

        Log::error('ParaguayWSSecurityDecryptor: Algoritmo no soportado', ['algorithm' => $algorithm]);
        return false;
    }

    /**
     * Reemplaza el nodo EncryptedData con el contenido desencriptado
     */
    private function replaceEncryptedData(DOMDocument $doc, \DOMElement $encryptedData, string $decryptedContent): void
    {
        $parent = $encryptedData->parentNode;
        
        if (!$parent) {
            return;
        }

        // Determinar el tipo de contenido
        $type = $encryptedData->getAttribute('Type');
        
        // Si es contenido (no elemento completo), crear fragmento
        if ($type === 'http://www.w3.org/2001/04/xmlenc#Content') {
            // El contenido desencriptado es XML que va dentro del padre
            $fragment = $doc->createDocumentFragment();
            
            // Intentar parsear como XML
            $tempDoc = new DOMDocument('1.0', 'UTF-8');
            $tempDoc->preserveWhiteSpace = true;
            
            // Envolver en un elemento raíz temporal para parsear
            $wrapped = '<temp>' . $decryptedContent . '</temp>';
            
            if (@$tempDoc->loadXML($wrapped)) {
                $tempRoot = $tempDoc->documentElement;
                foreach ($tempRoot->childNodes as $child) {
                    $imported = $doc->importNode($child, true);
                    $fragment->appendChild($imported);
                }
                $parent->replaceChild($fragment, $encryptedData);
            } else {
                // Si no es XML válido, insertar como texto
                $textNode = $doc->createTextNode($decryptedContent);
                $parent->replaceChild($textNode, $encryptedData);
            }
        } else {
            // Es un elemento completo
            $tempDoc = new DOMDocument('1.0', 'UTF-8');
            if (@$tempDoc->loadXML($decryptedContent)) {
                $imported = $doc->importNode($tempDoc->documentElement, true);
                $parent->replaceChild($imported, $encryptedData);
            }
        }
    }
}