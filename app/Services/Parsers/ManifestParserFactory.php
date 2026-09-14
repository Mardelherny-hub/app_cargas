<?php

namespace App\Services\Parsers;

use App\Contracts\ManifestParserInterface;
use App\Services\Parsers\KlineDataParser;
use App\Services\Parsers\ParanaExcelParser;
use App\Services\Parsers\GuaranExcelParserCompat;
use App\Services\Parsers\LoginXmlParser;
use App\Services\Parsers\TfpTextParser;
use App\Services\Parsers\NavsurTextParser;
use App\Services\Parsers\CmspEdiParserCompat;
use App\Services\Parsers\G2OceanXmlParser;
use Exception;
use Illuminate\Support\Facades\Log;

class ManifestParserFactory
{
    protected array $parsers = [
        KlineDataParser::class,
        ParanaExcelParser::class,
        GuaranExcelParserCompat::class,
        LoginXmlParser::class,
        TfpTextParser::class,
        CmspEdiParserCompat::class,
        NavsurTextParser::class,
        G2OceanXmlParser::class,
    ];

    protected array $extensionMappings = [
        'dat' => [KlineDataParser::class],
        'txt' => [KlineDataParser::class, NavsurTextParser::class, TfpTextParser::class],
        'xlsx' => [ParanaExcelParser::class, GuaranExcelParserCompat::class],
        'xls' => [ParanaExcelParser::class, GuaranExcelParserCompat::class],
        'xml' => [LoginXmlParser::class, G2OceanXmlParser::class],
        'edi' => [CmspEdiParserCompat::class],
    ];

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

        $detectedParser = $this->detectParserByContent($filePath);

        if ($detectedParser) {
            Log::info('Parser detected successfully', [
                'parser_class' => get_class($detectedParser),
                'file_path' => $filePath
            ]);
            return $detectedParser;
        }

        $detectedParser = $this->detectParserByExtension($filePath);

        if ($detectedParser) {
            Log::info('Parser detected by extension', [
                'parser_class' => get_class($detectedParser),
                'file_path' => $filePath
            ]);
            return $detectedParser;
        }

        throw new Exception(
            "No se pudo encontrar un parser compatible para el archivo: "
            . basename($filePath)
            . " (extensión: " . pathinfo($filePath, PATHINFO_EXTENSION) . ")"
        );
    }

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

                if ($parser instanceof ManifestParserInterface && $parser->canParse($filePath)) {
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

    public function getAvailableParsers(): array
    {
        return $this->parsers;
    }

    public function getSupportedFormats(): array
    {
        $formats = [];

        foreach ($this->parsers as $parserClass) {
            try {
                $parser = new $parserClass();

                if ($parser instanceof ManifestParserInterface) {
                    $formats[] = $parser->getFormatInfo();
                } else {
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

    protected function extractDescriptionFromDocComment(?string $docComment): string
    {
        if (!$docComment) {
            return 'Sin descripción disponible';
        }

        if (preg_match('/\/\*\*\s*\n\s*\*\s*(.+?)\s*\n/', $docComment, $matches)) {
            return trim($matches[1]);
        }

        return 'Sin descripción disponible';
    }

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

    public function canProcessFile(string $filePath): bool
    {
        try {
            $this->getParser($filePath);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    public function getFormatStatistics(): array
    {
        return [
            'total_parsers' => count($this->parsers),
            'total_extensions' => count($this->extensionMappings),
            'extensions_supported' => array_keys($this->extensionMappings),
            'parsers_available' => array_map(function ($parser) {
                return class_basename($parser);
            }, $this->parsers)
        ];
    }

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
