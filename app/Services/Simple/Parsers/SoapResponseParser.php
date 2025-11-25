<?php

namespace App\Services\Simple\Parsers;

use DOMDocument;
use DOMXPath;
use Exception;
use Illuminate\Support\Facades\Log;

/**
 * SOAP RESPONSE PARSER - Estandarización de Extracción de Datos
 * 
 * Clase centralizada para parsear respuestas SOAP/XML de AFIP y DNA.
 * Elimina la duplicación de código de extracción en múltiples servicios.
 * 
 * FUNCIONALIDADES:
 * ✅ Extracción de TRACKs con múltiples patrones
 * ✅ Extracción de IDs MIC/DTA
 * ✅ Detección de errores SOAP Fault
 * ✅ Extracción de metadatos (server, timestamp)
 * ✅ Validación de estructura XML
 * ✅ Logging detallado para debugging
 * 
 * USO:
 * $parser = new SoapResponseParser();
 * $tracks = $parser->extractTracks($soapResponse);
 * $micDtaId = $parser->extractMicDtaId($soapResponse);
 * $error = $parser->extractSoapFault($soapResponse);
 */
class SoapResponseParser
{
    /**
     * Patrones de búsqueda para TRACKs AFIP
     * Formato: YYYY-AR-########-#
     */
    private const TRACK_PATTERNS = [
        // Patrón XML tag
        '/<idTrack>([^<]+)<\/idTrack>/',
        '/<trackNumber>([^<]+)<\/trackNumber>/',
        '/<TrackNumber>([^<]+)<\/TrackNumber>/',
        
        // Patrón con namespace
        '/<ns\d*:idTrack>([^<]+)<\/ns\d*:idTrack>/',
        
        // Patrón regex para formato TRACK directo (AFIP: YYYY-AR-########-#)
        '/(\d{4}AR\d{8}\d)/',
        
        // Patrones adicionales para RegistrarTitEnvios
        '/<idTrack>\s*([^<]+)\s*<\/idTrack>/i',
    ];

    /**
     * Patrones para ID MIC/DTA
     */
    private const MICDTA_ID_PATTERNS = [
        '/<idMicDta>([^<]+)<\/idMicDta>/',
        '/<MicDtaId>([^<]+)<\/MicDtaId>/',
        '/<NumeroMicDta>([^<]+)<\/NumeroMicDta>/',
        '/<micDta>([^<]+)<\/micDta>/i',
    ];

    /**
     * Logging flag
     */
    private bool $enableLogging;

    /**
     * Contexto para logs
     */
    private array $logContext;

    public function __construct(bool $enableLogging = true, array $logContext = [])
    {
        $this->enableLogging = $enableLogging;
        $this->logContext = $logContext;
    }

    // ====================================
    // EXTRACCIÓN DE TRACKs
    // ====================================

    /**
     * Extraer TRACKs de respuesta SOAP/XML
     * 
     * @param string $response Respuesta SOAP completa
     * @param string $method Método AFIP que generó la respuesta (para contexto)
     * @return array Lista de TRACKs únicos encontrados
     */
    public function extractTracks(string $response, string $method = 'unknown'): array
    {
        if (empty($response)) {
            $this->log('warning', 'Respuesta SOAP vacía', ['method' => $method]);
            return [];
        }

        $tracks = [];

        // ESTRATEGIA 1: Extracción por patrones regex (rápido)
        $tracks = array_merge($tracks, $this->extractTracksByRegex($response));

        // ESTRATEGIA 2: Extracción por DOM/XPath (preciso)
        if (empty($tracks)) {
            $tracks = array_merge($tracks, $this->extractTracksByDom($response));
        }

        // ESTRATEGIA 3: Extracción por SimpleXML (fallback)
        if (empty($tracks)) {
            $tracks = array_merge($tracks, $this->extractTracksBySimpleXml($response));
        }

        // Limpiar y validar TRACKs
        $tracks = $this->cleanAndValidateTracks($tracks);

        $this->log('info', 'TRACKs extraídos', [
            'method' => $method,
            'tracks_count' => count($tracks),
            'tracks' => $tracks,
        ]);

        return $tracks;
    }

    /**
     * Extracción por regex (más rápido pero menos preciso)
     */
    private function extractTracksByRegex(string $response): array
    {
        $tracks = [];

        foreach (self::TRACK_PATTERNS as $pattern) {
            if (preg_match_all($pattern, $response, $matches)) {
                $tracks = array_merge($tracks, $matches[1]);
            }
        }

        return $tracks;
    }

    /**
     * Extracción por DOM/XPath (más preciso)
     */
    private function extractTracksByDom(string $response): array
    {
        $tracks = [];

        try {
            $dom = new DOMDocument();
            @$dom->loadXML($response);

            $xpath = new DOMXPath($dom);
            
            // Registrar namespaces comunes AFIP
            $xpath->registerNamespace('soap', 'http://schemas.xmlsoap.org/soap/envelope/');
            $xpath->registerNamespace('ns', 'Ar.Gob.Afip.Dga.wgesregsintia2');

            // Buscar nodos de TRACKs
            $trackNodes = $xpath->query('//idTrack | //trackNumber | //TrackNumber | //ns:idTrack');

            foreach ($trackNodes as $node) {
                $trackValue = trim($node->nodeValue);
                if (!empty($trackValue)) {
                    $tracks[] = $trackValue;
                }
            }

        } catch (Exception $e) {
            $this->log('warning', 'Error en extracción DOM', [
                'error' => $e->getMessage()
            ]);
        }

        return $tracks;
    }

    /**
     * Extracción por SimpleXML (fallback)
     */
    private function extractTracksBySimpleXml(string $response): array
    {
        $tracks = [];

        try {
            // Limpiar namespaces para SimpleXML
            $cleanXml = preg_replace('/xmlns[^=]*="[^"]*"/i', '', $response);
            $xml = @simplexml_load_string($cleanXml);

            if ($xml === false) {
                return $tracks;
            }

            // Buscar elementos comunes de TRACKs
            $trackElements = [
                'idTrack',
                'trackNumber',
                'TrackNumber',
            ];

            foreach ($trackElements as $element) {
                $nodes = $xml->xpath("//{$element}");
                foreach ($nodes as $node) {
                    $trackValue = trim((string)$node);
                    if (!empty($trackValue)) {
                        $tracks[] = $trackValue;
                    }
                }
            }

        } catch (Exception $e) {
            $this->log('warning', 'Error en extracción SimpleXML', [
                'error' => $e->getMessage()
            ]);
        }

        return $tracks;
    }

    /**
     * Limpiar y validar formato de TRACKs
     */
    private function cleanAndValidateTracks(array $tracks): array
    {
        $validated = [];

        foreach ($tracks as $track) {
            // Limpiar espacios
            $track = trim($track);
            
            // Saltar vacíos
            if (empty($track)) {
                continue;
            }

            // Validar formato AFIP: YYYY-AR-########-# (con guiones)
            if (preg_match('/^\d{4}-AR-\d{8}-\d$/', $track)) {
                $validated[] = $track;
                continue;
            }
            
            // Validar formato AFIP alternativo: YYYYAR#########  (sin guiones, 15 chars)
            if (preg_match('/^\d{4}AR\d{9}$/', $track)) {
                $validated[] = $track;
                continue;
            }
            
            // Validar formato AFIP alternativo: 16 caracteres numéricos con AR
            if (preg_match('/^\d{4}AR\d{8}\d$/', $track)) {
                $validated[] = $track;
                continue;
            }
            
            // Aceptar TRACKs de testing que empiecen con TEST_
            if (str_starts_with($track, 'TEST_TRACK_')) {
                $validated[] = $track;
                continue;
            }

            $this->log('warning', 'TRACK con formato no reconocido', [
                'track' => $track,
                'length' => strlen($track),
                'expected_formats' => ['YYYY-AR-########-#', 'YYYYAR#########', 'TEST_TRACK_*']
            ]);
        }

        // Eliminar duplicados
        return array_unique($validated);
    }

    // ====================================
    // EXTRACCIÓN DE IDs MIC/DTA
    // ====================================

    /**
     * Extraer ID MIC/DTA de respuesta
     * 
     * @param string $response Respuesta SOAP
     * @return string|null ID MIC/DTA o null si no se encuentra
     */
    public function extractMicDtaId(string $response): ?string
    {
        if (empty($response)) {
            return null;
        }

        // Intentar con cada patrón
        foreach (self::MICDTA_ID_PATTERNS as $pattern) {
            if (preg_match($pattern, $response, $matches)) {
                $micDtaId = trim($matches[1]);
                
                $this->log('info', 'ID MIC/DTA extraído', [
                    'micdta_id' => $micDtaId,
                    'pattern_used' => $pattern,
                ]);

                return $micDtaId;
            }
        }

        $this->log('warning', 'No se pudo extraer ID MIC/DTA', [
            'response_preview' => substr($response, 0, 500),
        ]);

        return null;
    }

    // ====================================
    // DETECCIÓN DE ERRORES SOAP
    // ====================================

    /**
     * Verificar si la respuesta contiene un SOAP Fault
     * 
     * @param string $response Respuesta SOAP
     * @return bool True si hay error SOAP
     */
    public function hasSoapFault(string $response): bool
    {
        return stripos($response, 'soap:Fault') !== false ||
               stripos($response, 'soapenv:Fault') !== false;
    }

    /**
     * Extraer mensaje de error SOAP Fault
     * 
     * @param string $response Respuesta SOAP
     * @return string Mensaje de error o descripción genérica
     */
    public function extractSoapFault(string $response): string
    {
        if (!$this->hasSoapFault($response)) {
            return '';
        }

        // Patrones para extraer mensajes de error
        $errorPatterns = [
            '/<faultstring>([^<]+)<\/faultstring>/',
            '/<faultcode>([^<]+)<\/faultcode>/',
            '/<message>([^<]+)<\/message>/i',
            '/<descripcion>([^<]+)<\/descripcion>/i',
        ];

        $errorParts = [];

        foreach ($errorPatterns as $pattern) {
            if (preg_match($pattern, $response, $matches)) {
                $errorParts[] = trim($matches[1]);
            }
        }

        $errorMessage = implode(' - ', array_unique($errorParts));

        if (empty($errorMessage)) {
            $errorMessage = 'Error SOAP no especificado';
        }

        $this->log('error', 'SOAP Fault detectado', [
            'error_message' => $errorMessage,
        ]);

        return $errorMessage;
    }

    /**
     * Extraer código de error AFIP
     * 
     * @param string $response Respuesta SOAP
     * @return string|null Código de error o null
     */
    public function extractAfipErrorCode(string $response): ?string
    {
        $patterns = [
            '/<codigoError>([^<]+)<\/codigoError>/',
            '/<errorCode>([^<]+)<\/errorCode>/',
            '/<codigo>([^<]+)<\/codigo>/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $response, $matches)) {
                return trim($matches[1]);
            }
        }

        return null;
    }

    // ====================================
    // EXTRACCIÓN DE METADATOS
    // ====================================

    /**
     * Extraer servidor AFIP de la respuesta
     * 
     * @param string $response Respuesta SOAP
     * @return string|null Nombre del servidor
     */
    public function extractServer(string $response): ?string
    {
        $patterns = [
            '/<Server>([^<]+)<\/Server>/',
            '/<servidor>([^<]+)<\/servidor>/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $response, $matches)) {
                return trim($matches[1]);
            }
        }

        return null;
    }

    /**
     * Extraer timestamp de la respuesta
     * 
     * @param string $response Respuesta SOAP
     * @return string|null Timestamp
     */
    public function extractTimestamp(string $response): ?string
    {
        $patterns = [
            '/<TimeStamp>([^<]+)<\/TimeStamp>/',
            '/<timestamp>([^<]+)<\/timestamp>/i',
            '/<fechaHora>([^<]+)<\/fechaHora>/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $response, $matches)) {
                return trim($matches[1]);
            }
        }

        return null;
    }

    /**
     * Extraer número de viaje (para Paraguay DNS)
     * 
     * @param string $response Respuesta SOAP
     * @return string|null Número de viaje
     */
    public function extractVoyageNumber(string $response): ?string
    {
        $patterns = [
            '/<nroViaje>([^<]+)<\/nroViaje>/',
            '/<numeroViaje>([^<]+)<\/numeroViaje>/i',
            '/<voyageNumber>([^<]+)<\/voyageNumber>/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $response, $matches)) {
                return trim($matches[1]);
            }
        }

        return null;
    }

    // ====================================
    // VALIDACIÓN DE ESTRUCTURA
    // ====================================

    /**
     * Validar que el XML es bien formado
     * 
     * @param string $xml XML string
     * @return bool True si es válido
     */
    public function isValidXml(string $xml): bool
    {
        if (empty($xml)) {
            return false;
        }

        libxml_use_internal_errors(true);
        $doc = simplexml_load_string($xml);
        $errors = libxml_get_errors();
        libxml_clear_errors();

        if ($doc === false || !empty($errors)) {
            $this->log('warning', 'XML inválido', [
                'errors' => array_map(function($error) {
                    return $error->message;
                }, $errors),
            ]);
            return false;
        }

        return true;
    }

    /**
     * Obtener estructura del XML para debugging
     * 
     * @param string $xml XML string
     * @return array Estructura simplificada
     */
    public function getXmlStructure(string $xml): array
    {
        try {
            $dom = new DOMDocument();
            @$dom->loadXML($xml);

            return $this->domNodeToArray($dom->documentElement);

        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Convertir nodo DOM a array (recursivo)
     */
    private function domNodeToArray($node): array
    {
        $result = [];

        if ($node->hasChildNodes()) {
            foreach ($node->childNodes as $child) {
                if ($child->nodeType === XML_ELEMENT_NODE) {
                    $result[$child->nodeName] = $this->domNodeToArray($child);
                }
            }
        } else {
            $result = $node->nodeValue;
        }

        return $result;
    }

    // ====================================
    // UTILIDADES
    // ====================================

    /**
     * Logging interno
     */
    private function log(string $level, string $message, array $context = []): void
    {
        if (!$this->enableLogging) {
            return;
        }

        $fullContext = array_merge($this->logContext, $context, [
            'parser' => 'SoapResponseParser',
        ]);

        Log::$level($message, $fullContext);
    }

    /**
     * Obtener vista previa del XML para logs (truncado)
     * 
     * @param string $xml XML completo
     * @param int $length Longitud máxima
     * @return string XML truncado
     */
    public function getXmlPreview(string $xml, int $length = 500): string
    {
        if (strlen($xml) <= $length) {
            return $xml;
        }

        return substr($xml, 0, $length) . '... [truncado]';
    }

    /**
     * Extraer todos los datos relevantes de una respuesta
     * (útil para debugging)
     * 
     * @param string $response Respuesta SOAP
     * @return array Todos los datos extraídos
     */
    public function extractAll(string $response): array
    {
        return [
            'tracks' => $this->extractTracks($response),
            'micdta_id' => $this->extractMicDtaId($response),
            'has_soap_fault' => $this->hasSoapFault($response),
            'soap_fault_message' => $this->hasSoapFault($response) ? $this->extractSoapFault($response) : null,
            'afip_error_code' => $this->extractAfipErrorCode($response),
            'server' => $this->extractServer($response),
            'timestamp' => $this->extractTimestamp($response),
            'voyage_number' => $this->extractVoyageNumber($response),
            'is_valid_xml' => $this->isValidXml($response),
        ];
    }
}