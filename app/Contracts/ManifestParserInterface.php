<?php

namespace App\Contracts;

use App\ValueObjects\ManifestParseResult;

/**
 * INTERFACE BASE PARA PARSERS DE MANIFIESTOS
 * 
 * Define el contrato estándar que deben cumplir todos los parsers
 * de manifiestos para garantizar consistencia y intercambiabilidad.
 * 
 * RESPONSABILIDADES:
 * - Detección automática de formato
 * - Parsing robusto de archivos
 * - Validación de datos
 * - Transformación a formato estándar
 * 
 * IMPLEMENTACIONES DISPONIBLES:
 * - KlineDataParser: Archivos .DAT de K-Line
 * - ParanaExcelParser: Excel MAERSK (.xlsx)
 * - GuaranCsvParser: CSV consolidado multi-línea
 * - LoginXmlParser: XML anidado completo
 * - TfpTextParser: Formato jerárquico con delimitadores
 * - CmspEdiParser: EDI CUSCAR estándar
 * - NavsurTextParser: Texto libre descriptivo
 */
interface ManifestParserInterface
{
    /**
     * Verificar si el parser puede procesar el archivo dado
     * 
     * Este método debe realizar verificaciones rápidas para determinar
     * si el parser es compatible con el archivo, incluyendo:
     * - Extensión de archivo
     * - Contenido/estructura inicial
     * - Patrones específicos del formato
     * 
     * @param string $filePath Ruta completa al archivo
     * @return bool True si puede parsear, false si no
     */
    public function canParse(string $filePath): bool;

    /**
     * Parsear el archivo y retornar resultado estructurado
     * 
     * Método principal que procesa completamente el archivo y
     * genera los objetos de modelo correspondientes (Voyage,
     * Shipment, BillOfLading, etc.)
     * 
     * @param string $filePath Ruta completa al archivo
     * @return ManifestParseResult Resultado del parsing con datos creados
     * @throws \Exception Si ocurre error crítico durante el parsing
     */
    public function parse(string $filePath): ManifestParseResult;

    /**
     * Validar datos parseados antes de procesamiento
     * 
     * Realizar validaciones de integridad y consistencia de los
     * datos extraídos del archivo antes de crear objetos de modelo.
     * 
     * @param array $data Datos parseados en formato raw
     * @return array Lista de errores encontrados (vacío si válido)
     */
    public function validate(array $data): array;

    /**
     * Transformar datos parseados a formato estándar del sistema
     * 
     * Convertir datos del formato específico del parser a la
     * estructura estándar esperada por el sistema.
     * 
     * @param array $data Datos parseados en formato original
     * @return array Datos transformados a formato estándar
     */
    public function transform(array $data): array;

    /**
     * Obtener información del formato soportado
     * 
     * Retorna metadatos sobre el tipo de archivo que maneja
     * este parser para propósitos informativos.
     * 
     * @return array Información del formato [name, description, extensions, etc.]
     */
    public function getFormatInfo(): array;

    /**
     * Obtener configuración por defecto del parser
     * 
     * Configuración específica que puede ser personalizada
     * para diferentes necesidades de parsing.
     * 
     * @return array Configuración por defecto
     */
    public function getDefaultConfig(): array;
}