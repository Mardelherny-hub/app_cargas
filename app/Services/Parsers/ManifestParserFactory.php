<?php

namespace App\Services\Parsers;

use App\Contracts\ManifestParserInterface;
use App\Services\Parsers\KlineDataParser;
use App\Services\Parsers\ParanaExcelParser;
// TODO: Agregar otros parsers cuando estén implementados
use App\Services\Parsers\GuaranCsvParser;
use App\Services\Parsers\LoginXmlParser;
// use App\Services\Parsers\TfpTextParser;
// use App\Services\Parsers\CmspEdiParser;
use App\Services\Parsers\NavsurTextParser;
use Exception;
use Illuminate\Support\Facades\Log;

/**
 * FACTORY PARA AUTO-DETECCIÓN Y CREACIÓN DE PARSERS
 * 
 * Responsable de detectar automáticamente el tipo de archivo de manifiesto
 * y retornar el parser apropiado para procesarlo.
 * 
 * CARACTERÍSTICAS:
 * - Auto-detección basada en contenido y extensión
 * - Registro de parsers disponibles
 * - Fallback a parser genérico si es necesario
 * - Logging detallado para debugging
 */
class ManifestParserFactory
{
    /**
     * Lista de parsers disponibles en orden de prioridad
     */
    protected array $parsers = [
        KlineDataParser::class,
        ParanaExcelParser::class,
        GuaranCsvParser::class, 
        LoginXmlParser::class,
        // TfpTextParser::class,
        // CmspEdiParser::class,
        NavsurTextParser::class,
    ];

    /**
     * Mapeo de extensiones a parsers esperados
     */
    protected array $extensionMappings = [
        'dat' => [KlineDataParser::class],
        'txt' => [KlineDataParser::class, NavsurTextParser::class],
        //'txt' => [KlineDataParser::class, TfpTextParser::class, NavsurTextParser::class],
        'xlsx' => [ParanaExcelParser::class],
        'xls' => [ParanaExcelParser::class],
        'csv' => [], // GuaranCsvParser::class
        'xml' => [LoginXmlParser::class], // LoginXmlParser::class
        'edi' => [], // CmspEdiParser::class
    ];

    /**
     * Obtener parser apropiado para un archivo
     * 
     * @param string $filePath Ruta completa al archivo
     * @return ManifestParserInterface Parser que puede procesar el archivo
     * @throws Exception Si no se encuentra parser compatible
     */
    public function getParser(string $filePath): ManifestParserInterface
    {
        if (!file_exists($filePath)) {
            throw new Exception("Archivo no encontrado: {$filePath}");
        }

        Log::info('Detecting parser for file', [
            'file_path' => $filePath,
            'file_size' => filesize($filePath),
            'extension' => pathinfo($filePath, PATHINFO_EXTENSION)
        ]);

        // Intentar detección por contenido y extensión
        $detectedParser = $this->detectParserByContent($filePath);
        
        if ($detectedParser) {
            Log::info('Parser detected successfully', [
                'parser_class' => get_class($detectedParser),
                'file_path' => $filePath
            ]);
            return $detectedParser;
        }

        // Si no se detecta, intentar por extensión
        $detectedParser = $this->detectParserByExtension($filePath);
        
        if ($detectedParser) {
            Log::info('Parser detected by extension', [
                'parser_class' => get_class($detectedParser),
                'file_path' => $filePath
            ]);
            return $detectedParser;
        }

        throw new Exception(
            "No se pudo encontrar un parser compatible para el archivo: " . 
            basename($filePath) . 
            " (extensión: " . pathinfo($filePath, PATHINFO_EXTENSION) . ")"
        );
    }

    /**
     * Detectar parser basado en contenido del archivo
     */
    protected function detectParserByContent(string $filePath): ?ManifestParserInterface
    {
        foreach ($this->parsers as $parserClass) {
            try {
                $parser = new $parserClass();
                
                if ($parser instanceof ManifestParserInterface && $parser->canParse($filePath)) {
                    Log::debug('Parser can handle file', [
                        'parser_class' => $parserClass,
                        'file_path' => $filePath
                    ]);
                    return $parser;
                }
            } catch (Exception $e) {
                Log::warning('Parser failed canParse check', [
                    'parser_class' => $parserClass,
                    'file_path' => $filePath,
                    'error' => $e->getMessage()
                ]);
                continue;
            }
        }

        return null;
    }

    /**
     * Detectar parser basado en extensión del archivo
     */
    protected function detectParserByExtension(string $filePath): ?ManifestParserInterface
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        
        if (!isset($this->extensionMappings[$extension])) {
            Log::warning('Unknown file extension', [
                'extension' => $extension,
                'file_path' => $filePath
            ]);
            return null;
        }

        $candidateParsers = $this->extensionMappings[$extension];
        
        foreach ($candidateParsers as $parserClass) {
            try {
                $parser = new $parserClass();
                
                if ($parser instanceof ManifestParserInterface) {
                    // Para parsers por extensión, asumir que puede procesar
                    Log::debug('Using parser based on extension', [
                        'parser_class' => $parserClass,
                        'extension' => $extension,
                        'file_path' => $filePath
                    ]);
                    return $parser;
                }
            } catch (Exception $e) {
                Log::warning('Failed to instantiate parser', [
                    'parser_class' => $parserClass,
                    'error' => $e->getMessage()
                ]);
                continue;
            }
        }

        return null;
    }

    /**
     * Registrar nuevo parser en el factory
     */
    public function registerParser(string $parserClass, array $extensions = []): void
    {
        if (!in_array($parserClass, $this->parsers)) {
            $this->parsers[] = $parserClass;
        }

        foreach ($extensions as $extension) {
            if (!isset($this->extensionMappings[$extension])) {
                $this->extensionMappings[$extension] = [];
            }
            
            if (!in_array($parserClass, $this->extensionMappings[$extension])) {
                $this->extensionMappings[$extension][] = $parserClass;
            }
        }

        Log::info('Parser registered', [
            'parser_class' => $parserClass,
            'extensions' => $extensions
        ]);
    }

    /**
     * Obtener lista de parsers disponibles
     */
    public function getAvailableParsers(): array
    {
        return $this->parsers;
    }

    /**
     * Obtener información de parsers soportados
     */
    public function getSupportedFormats(): array
    {
        $formats = [];
        
        foreach ($this->parsers as $parserClass) {
            try {
                $parser = new $parserClass();
                
                if ($parser instanceof ManifestParserInterface) {
                    // Usar el método getFormatInfo() del parser
                    $formatInfo = $parser->getFormatInfo();
                    $formats[] = $formatInfo;
                } else {
                    // Fallback para parsers que no implementen la interface completa
                    $reflection = new \ReflectionClass($parserClass);
                    $docComment = $reflection->getDocComment();
                    
                    $formats[] = [
                        'name' => $reflection->getShortName(),
                        'description' => $this->extractDescriptionFromDocComment($docComment),
                        'extensions' => $this->getExtensionsForParser($parserClass),
                        'parser_class' => $parserClass
                    ];
                }
            } catch (Exception $e) {
                Log::warning('Failed to get parser info', [
                    'parser_class' => $parserClass,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $formats;
    }

    /**
     * Extraer descripción del comentario de documentación
     */
    protected function extractDescriptionFromDocComment(?string $docComment): string
    {
        if (!$docComment) {
            return 'Sin descripción disponible';
        }

        // Buscar primera línea de descripción después de /**
        if (preg_match('/\/\*\*\s*\n\s*\*\s*(.+?)\s*\n/', $docComment, $matches)) {
            return trim($matches[1]);
        }

        return 'Sin descripción disponible';
    }

    /**
     * Obtener extensiones soportadas por un parser
     */
    protected function getExtensionsForParser(string $parserClass): array
    {
        $extensions = [];
        
        foreach ($this->extensionMappings as $extension => $parsers) {
            if (in_array($parserClass, $parsers)) {
                $extensions[] = $extension;
            }
        }

        return $extensions;
    }

    /**
     * Validar que un archivo es procesable por algún parser
     */
    public function canProcessFile(string $filePath): bool
    {
        try {
            $this->getParser($filePath);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Obtener estadísticas de formatos soportados
     */
    public function getFormatStatistics(): array
    {
        return [
            'total_parsers' => count($this->parsers),
            'total_extensions' => count($this->extensionMappings),
            'extensions_supported' => array_keys($this->extensionMappings),
            'parsers_available' => array_map(function($parser) {
                return class_basename($parser);
            }, $this->parsers)
        ];
    }

    /**
     * Obtener configuración detallada de un parser específico
     */
    public function getParserConfig(string $parserClass): array
    {
        try {
            $parser = new $parserClass();
            
            if ($parser instanceof ManifestParserInterface) {
                return [
                    'format_info' => $parser->getFormatInfo(),
                    'default_config' => $parser->getDefaultConfig(),
                    'class' => $parserClass
                ];
            }
            
            return ['error' => 'Parser does not implement ManifestParserInterface'];
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Verificar si un parser específico puede procesar un archivo
     */
    public function canParserProcessFile(string $parserClass, string $filePath): bool
    {
        try {
            $parser = new $parserClass();
            
            if ($parser instanceof ManifestParserInterface) {
                return $parser->canParse($filePath);
            }
            
            return false;
        } catch (Exception $e) {
            Log::warning('Error checking parser capability', [
                'parser_class' => $parserClass,
                'file_path' => $filePath,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Obtener lista de parsers que pueden procesar un archivo
     */
    public function getCompatibleParsers(string $filePath): array
    {
        $compatibleParsers = [];
        
        foreach ($this->parsers as $parserClass) {
            if ($this->canParserProcessFile($parserClass, $filePath)) {
                try {
                    $parser = new $parserClass();
                    $compatibleParsers[] = [
                        'class' => $parserClass,
                        'name' => class_basename($parserClass),
                        'info' => $parser instanceof ManifestParserInterface 
                            ? $parser->getFormatInfo() 
                            : ['name' => class_basename($parserClass)]
                    ];
                } catch (Exception $e) {
                    Log::warning('Error getting compatible parser info', [
                        'parser_class' => $parserClass,
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }
        
        return $compatibleParsers;
    }
}