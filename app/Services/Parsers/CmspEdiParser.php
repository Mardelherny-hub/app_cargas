<?php

namespace App\Services\Parsers;

use App\Contracts\ManifestParserInterface;
use App\ValueObjects\ManifestParseResult;
use App\Models\Voyage;
use App\Services\Parsers\Concerns\EnsuresUniqueVoyageNumber;
use App\Services\Parsers\Concerns\ExtractsEmbeddedTaxId;
use App\Services\Parsers\Concerns\ResolvesClientAddresses;
use App\Models\Shipment;
use App\Models\BillOfLading;
use App\Models\Container;
use App\Models\ShipmentItem;
use App\Models\Client;
use App\Models\Port;
use App\Models\Country;
use App\Models\DocumentType;
use App\Models\Vessel;
use App\Models\ContainerType;
use App\Models\CargoType;
use App\Models\PackagingType;
use App\Models\ManifestImport;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Exception;

/**
 * PARSER PARA CMSP.EDI - FORMATO EDI CUSCAR UN/EDIFACT
 * 
 * Procesa archivos EDI CUSCAR con estructura:
 * - UNB: Interchange Header
 * - UNH: Message Header (CUSCAR)
 * - BGM: Message Type
 * - TDT: Transport Details (vessel info)
 * - LOC: Location (puertos)
 * - CNI: Container Info
 * - GID: Goods (mercadería)
 * - SGP: Equipment Details (contenedores)
 * - MEA: Measurements (pesos/volúmenes)
 * - NAD: Name and Address (partes)
 * 
 * AGENTE: CMSP (Container Management System Paraguay)
 * FORMATO: UN/EDIFACT CUSCAR D.96B
 */
class CmspEdiParser implements ManifestParserInterface
{
    use EnsuresUniqueVoyageNumber;
    use ExtractsEmbeddedTaxId;
    use ResolvesClientAddresses;

    /**
     * Rol de la ultima parte leida, para asociarle el RFF+ADZ que viene
     * inmediatamente despues (ver parseReference).
     */
    protected ?string $lastPartyRole = null;

    /**
     * True cuando el conocimiento en curso repite el mismo peso en todos sus
     * items (es el total del conocimiento, no el peso de cada contenedor).
     */
    protected bool $pesoItemEsTotalRepetido = false;

    protected array $stats = [
        'processed_containers' => 0,
        'processed_items' => 0,
        'processed_bls' => 0,
        'errors' => 0,
        'warnings' => []
    ];

    protected array $ediSegments = [];
    protected array $parsedData = [];

    /**
     * Verificar si puede parsear el archivo
     */
    public function canParse(string $filePath): bool
    {
        // La extension no decide: se descartan solo los formatos que sabemos que
        // no son EDIFACT, y el resto se resuelve mirando el contenido.
        //
        // Verificado 07/08/2026: el archivo de Hapag-Lloyd
        // "2047270O_CUSCAR.593698204.7091255943.1" termina en ".1" y es un CUSCAR
        // valido (UNA:+.? / UNB+UNOA / UNH+1+CUSCAR:D:96B:UN). Exigir extension
        // "edi" lo rechazaba sin abrirlo. Los marcadores de abajo son especificos
        // de EDIFACT, asi que la deteccion por contenido alcanza.
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        if (in_array($extension, ['xlsx', 'xls', 'xml', 'csv', 'pdf', 'zip'], true)) {
            return false;
        }

        try {
            $handle = fopen($filePath, 'r');
            if (!$handle) {
                return false;
            }

            // Buscar patrones característicos EDI CUSCAR
            $foundEdiPattern = false;
            $lineCount = 0;
            
            while (!feof($handle) && $lineCount < 10) {
                $line = fgets($handle);
                if ($line === false) break;
                
                $line = trim($line);
                
                // Buscar marcadores EDI CUSCAR específicos
                if (strpos($line, 'UNH+') !== false && strpos($line, '+CUSCAR:D:96B') !== false ||
                    strpos($line, 'UNB+UNOA') !== false ||
                    strpos($line, 'BGM+85+') !== false) {
                    $foundEdiPattern = true;
                    break;
                }
                
                $lineCount++;
            }
            
            fclose($handle);
            return $foundEdiPattern;
            
        } catch (Exception $e) {
            Log::warning('Error checking CMSP EDI file', [
                'file_path' => $filePath,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Parsear el archivo EDI y retornar resultado
     */
    public function parse(string $filePath, array $options = []): ManifestParseResult
    {
        $startTime = microtime(true);

        Log::info('Starting CMSP EDI parsing', [
            'file_path' => $filePath,
            'file_size' => filesize($filePath)
        ]);

        try {
            // 0. Registrar la importación (con dup-check por hash)
            $importRecord = $this->createImportRecord($filePath);

            // 1. Leer y parsear segmentos EDI
            $this->parseEdiFile($filePath);

            // 2. Extraer datos estructurados
            $this->extractStructuredData();

            // 3. Validar datos extraídos
            $errors = $this->validate($this->parsedData);
            if (!empty($errors)) {
                throw new Exception('Errores de validación: ' . implode(', ', $errors));
            }

            // 4. Transformar a formato estándar
            $standardData = $this->transform($this->parsedData);

            // 5. Crear objetos de modelo
            return $this->createModelObjects($standardData, $importRecord, $startTime, $options);

        } catch (Exception $e) {
            $this->stats['errors']++;

            // Archivo ya importado: el viaje y su envío ya existen (choca el índice
            // único voyage_id + vessel_id). Mensaje amable en lugar del error SQL.
            if (strpos($e->getMessage(), 'uk_shipments_voyage_vessel') !== false
                || strpos($e->getMessage(), 'voyages_voyage_number_unique') !== false
                || strpos($e->getMessage(), 'ya fue importado anteriormente') !== false) {
                if (isset($importRecord)) {
                    $importRecord->markAsFailed([
                        'Este archivo ya fue importado anteriormente. El viaje ya existe en el sistema y no se duplicó ningún dato.'
                    ], [
                        'import_statistics' => $this->stats,
                    ]);
                }
                return ManifestParseResult::failure([
                    'Este archivo ya fue importado anteriormente. El viaje ya existe en el sistema y no se duplicó ningún dato. Si necesita importarlo de nuevo, primero revierta la importación desde el Historial de Importaciones.'
                ]);
            }

            if (isset($importRecord)) {
                $importRecord->markAsFailed([$e->getMessage()], [
                    'import_statistics' => $this->stats,
                ]);
            }
            Log::error('CMSP EDI parsing failed', [
                'file_path' => $filePath,
                'error' => $e->getMessage(),
                'stats' => $this->stats
            ]);
            throw $e;
        }
    }

    /**
     * Parsear archivo EDI en segmentos
     */
    protected function parseEdiFile(string $filePath): void
    {
        $content = file_get_contents($filePath);
        if ($content === false) {
            throw new Exception('No se pudo leer el archivo EDI');
        }

        // Los EDIFACT de esta naviera vienen en Windows-1252 (verificado 03/08/2026).
        // Sin normalizar, los acentos son bytes invalidos en UTF-8 y rompen
        // cualquier guardado JSON aguas abajo.
        //
        // Windows-1252 y no ISO-8859-1: las vocales acentuadas coinciden, pero el
        // rango 0x80-0x9F difiere. El byte 0x96 del nombre "MAERSK AS ? ATA:" es
        // guion medio en Windows-1252 y caracter de control invisible en
        // ISO-8859-1, que es el cuadradito que se veia en pantalla. Mismo caso
        // para comillas y apostrofes tipograficos.
        if (!mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'Windows-1252');
        }

        /*
         * Separar segmentos respetando el carácter de escape EDIFACT.
         *
         * En UN/EDIFACT '?' libera al carácter siguiente. Por lo tanto,
         * un apóstrofe precedido por '?' pertenece al contenido y NO
         * termina el segmento.
         *
         * Ejemplos reales Hapag-Lloyd:
         *   PCI++BATCH NO?'S?: ...
         *   FTX+AAA+++1X40?' HC CONTAINER:...
         */
        $rawSegments = [];
        $buffer = '';
        $released = false;
        $length = strlen($content);

        for ($i = 0; $i < $length; $i++) {
            $char = $content[$i];

            if ($released) {
                $buffer .= $char;
                $released = false;
                continue;
            }

            if ($char === '?') {
                $buffer .= $char;
                $released = true;
                continue;
            }

            if ($char === "'") {
                if (trim($buffer) !== '') {
                    $rawSegments[] = $buffer;
                }

                $buffer = '';
                continue;
            }

            $buffer .= $char;
        }

        if (trim($buffer) !== '') {
            $rawSegments[] = $buffer;
        }

        $this->ediSegments = [];

        foreach ($rawSegments as $line) {
            $line = trim($line);
            if (empty($line)) continue;

            // Parsear segmento EDI
            if (preg_match('/^([A-Z]{3})\+(.+)$/', $line, $matches)) {
                $segmentTag = $matches[1];
                $segmentData = $matches[2];

                /*
                 * Separar elementos respetando el release character EDIFACT.
                 *
                 * '?+' representa un signo + literal dentro del dato y no un
                 * separador de elemento.
                 *
                 * En los archivos reales auditados no aparece la secuencia '??',
                 * por lo que esta separación cubre los casos efectivamente
                 * utilizados por ambos emisores CUSCAR recibidos.
                 */
                $elements = preg_split('/(?<!\?)\+/', $segmentData);

                $this->ediSegments[] = [
                    'tag' => $segmentTag,
                    'data' => $segmentData,
                    'elements' => $elements
                ];
            }
        }

        Log::info('EDI segments parsed', [
            'total_segments' => count($this->ediSegments)
        ]);
    }

    /**
     * Extraer datos estructurados de segmentos EDI
     */
    protected function extractStructuredData(): void
    {
        $this->parsedData = [
            'interchange' => [],
            'message' => [],
            'vessel' => [],
            'ports' => [],
            'dates' => [],
            'containers' => [],
            'items' => [],
            'parties' => [],
            // Indice de contenedores declarados en bloques EQD, por numero.
            // No siempre existe: 250-22_316S-CUSCAR.EDI trae 178, CMSP.EDI ninguno.
            'equipment' => []
        ];

        $currentContainer = null;
        $currentItem = null;
        $currentEquipment = null;
        $allItems = []; // Coleccionar todos los items

        foreach ($this->ediSegments as $segment) {
            switch ($segment['tag']) {
                case 'UNB':
                    $this->parseInterchangeHeader($segment);
                    break;

                case 'UNH':
                    $this->parseMessageHeader($segment);
                    break;

                case 'BGM':
                    $this->parseMessageType($segment);
                    break;

                case 'TDT':
                    $this->parseTransportDetails($segment);
                    break;

                case 'DTM':
                    $this->parseCuscarDateTime($segment);
                    break;

                case 'LOC':
                    $this->parseLocation($segment);
                    break;

                case 'CNI':
                    // Guardar contenedor anterior si existe y tiene items
                    if ($currentContainer !== null && !empty($currentContainer['items'])) {
                        $this->parsedData['containers'][] = $currentContainer;
                    }

                    // Los bloques EQD terminaron: sin esto $currentEquipment queda
                    // apuntando al ultimo contenedor durante todo el conocimiento.
                    $currentEquipment = null;

                    // Crear nuevo contenedor
                    $currentContainer = [
                        'sequence' => $segment['elements'][0] ?? '',
                        'containers' => [],
                        'items' => [],
                        'references' => [],
                        // Peso bruto del conocimiento (MEA+AAX). Unico peso real
                        // del archivo: ver comentario en parseMeasurements().
                        'gross_weight_kg' => 0,
                    ];
                    break;

                case 'RFF':
                    $this->parseReference($segment, $currentContainer);
                    break;

                case 'MEA':
                    // MEA+AAE+T+KGM:2080 dentro de un bloque EQD es la tara real
                    // del contenedor. Hasta ahora se descartaba porque no habia
                    // item activo. Las taras del archivo van de 2080 a 3940 kg,
                    // contra el 2200 fijo que usaba createContainer().
                    if ($currentEquipment !== null
                        && ($segment['elements'][0] ?? '') === 'AAE'
                        && ($segment['elements'][1] ?? '') === 'T') {
                        $mea = explode(':', $segment['elements'][2] ?? '');
                        if (($mea[0] ?? '') === 'KGM' && isset($mea[1])) {
                            $this->parsedData['equipment'][$currentEquipment]['tare_weight_kg'] = (float) $mea[1];
                        }
                        break;
                    }

                    // MEA+WT+T+KGM:3700 -> tara cuando el emisor no usa AAE+T
                    // (Roberto 11/08/2026, archivo con EQD+CN+FFAU2866044). Solo
                    // como respaldo: si AAE+T ya la cargo, no se pisa.
                    if ($currentEquipment !== null
                        && ($segment['elements'][0] ?? '') === 'WT'
                        && ($segment['elements'][1] ?? '') === 'T'
                        && !isset($this->parsedData['equipment'][$currentEquipment]['tare_weight_kg'])) {
                        $mea = explode(':', $segment['elements'][2] ?? '');
                        if (($mea[0] ?? '') === 'KGM' && isset($mea[1])) {
                            $this->parsedData['equipment'][$currentEquipment]['tare_weight_kg'] = (float) $mea[1];
                        }
                        break;
                    }

                    // MEA+AAE+VGM+KGM:22350 es el peso verificado del contenedor
                    // (SOLAS). Es el unico peso real por contenedor del archivo:
                    // el MEA+AAE+G repite el total del conocimiento en cada item.
                    // Roberto lo senalo el 07/08/2026. A veces viene en 0, y en
                    // ese caso no se pisa nada.
                    if ($currentEquipment !== null
                        && ($segment['elements'][0] ?? '') === 'AAE'
                        && ($segment['elements'][1] ?? '') === 'VGM') {
                        $mea = explode(':', $segment['elements'][2] ?? '');
                        if (($mea[0] ?? '') === 'KGM' && isset($mea[1]) && (float) $mea[1] > 0) {
                            $this->parsedData['equipment'][$currentEquipment]['vgm_weight_kg'] = (float) $mea[1];
                        }
                        break;
                    }

                    $this->parseMeasurements($segment, $currentContainer, $currentItem);
                    break;

                case 'NAD':
                    $this->parseParty($segment, $currentContainer);
                    break;

                case 'GID':
                    // Romper el alias antes de reasignar. Las dos lineas de abajo
                    // guardan &$currentItem, o sea referencias a la variable y no
                    // copias del array. Sin este unset, la reasignacion de la vuelta
                    // siguiente escribe A TRAVES de esos alias y pisa el item anterior:
                    // los 178 GID del archivo de Roberto terminaban siendo todos el
                    // ultimo, con un solo contenedor entre los 13 conocimientos.
                    // Verificado en PHP 8.3: sin unset da 3,3,3; con unset da 1,2,3.
                    unset($currentItem);

                    $currentItem = [
                        'sequence' => $segment['elements'][0] ?? '',
                        'package_info' => $segment['elements'][1] ?? '',
                        'description' => '',
                        'gross_weight_kg' => 0,
                        'tare_weight_kg' => 0,
                        'volume_m3' => 0,
                        'containers' => [],
                        // Campos DGS (mercadería peligrosa)
                        'is_dangerous_goods' => false,
                        'imdg_class' => null,
                        'un_number' => null,

                        // PCI/C210: marcas declaradas para los bultos.
                        'cargo_marks' => null,

                        // Campo CST (código arancelario)
                        'commodity_code' => null,
                    ];
                    
                    // Agregar item al contenedor actual
                    if ($currentContainer !== null) {
                        $currentContainer['items'][] = &$currentItem;
                    }
                    
                    // También guardarlo en lista general
                    $allItems[] = &$currentItem;
                    break;

                case 'SGP':
                    if ($currentItem !== null) {
                        $containerNumber = $segment['elements'][0] ?? '';
                        if (!empty($containerNumber)) {
                            $currentItem['containers'][] = $containerNumber;
                        }
                    }
                    break;

                // EQD+CN+SEGU3691219+22G1::5+2+3+4
                // Los bloques EQD vienen ANTES del primer CNI y declaran cada
                // contenedor con su codigo ISO y su tara real. Se indexan por
                // numero y createContainer() los cruza con los SGP de cada
                // conocimiento. Si el archivo no trae EQD (caso CMSP.EDI), el
                // indice queda vacio y todo se comporta como antes.
                case 'EQD':
                    $equipmentNumber = $segment['elements'][1] ?? '';
                    if (!empty($equipmentNumber)) {
                        $this->parsedData['equipment'][$equipmentNumber] = [
                            'iso_code'                => explode(':', $segment['elements'][2] ?? '')[0] ?? '',
                            'tare_weight_kg'          => null,
                            'shipper_seal'            => null,
                            'customs_seal'            => null,
                            'carrier_seal'            => null,
                            'additional_seals'        => [],
                            'transport_temperature_c' => null,
                        ];
                        $currentEquipment = $equipmentNumber;
                    } else {
                        $currentEquipment = null;
                    }
                    break;

                // SEL:
                //   SH = cargador
                //   CU = Aduana
                //   CA = transportista
                //   AB = emisor desconocido
                case 'SEL':
                    if ($currentEquipment !== null) {
                        $seal = trim($segment['elements'][0] ?? '');

                        $issuerParts = explode(':', $segment['elements'][1] ?? '');
                        $issuerCode = strtoupper(trim($issuerParts[0] ?? ''));

                        if ($seal !== '') {
                            $sealField = match ($issuerCode) {
                                'SH' => 'shipper_seal',
                                'CU' => 'customs_seal',
                                'CA' => 'carrier_seal',
                                default => null,
                            };

                            // Los campos específicos admiten un precinto.
                            // Si ya existe otro del mismo tipo, conservar el
                            // siguiente en additional_seals en vez de pisarlo.
                            if ($sealField !== null
                                && empty($this->parsedData['equipment'][$currentEquipment][$sealField])) {
                                $this->parsedData['equipment'][$currentEquipment][$sealField] = $seal;
                            } else {
                                $this->parsedData['equipment'][$currentEquipment]['additional_seals'][] = [
                                    'seal_number' => $seal,
                                    'issuer_code' => $issuerCode !== '' ? $issuerCode : null,
                                ];
                            }
                        }
                    }
                    break;

                case 'TMP':
                    /*
                     * TMP+2 = temperatura de transporte.
                     *
                     * Ejemplos reales Hapag-Lloyd:
                     *   TMP+2+-003:CEL
                     *   TMP+2+018:CEL
                     *   TMP+2+020:CEL
                     *
                     * El TMP pertenece al EQD actualmente activo.
                     * Sólo se guarda cuando el emisor declara explícitamente
                     * temperatura de transporte en grados Celsius.
                     */
                    if ($currentEquipment !== null
                        && ($segment['elements'][0] ?? '') === '2') {
                        $temperature = explode(
                            ':',
                            $segment['elements'][1] ?? ''
                        );

                        if (($temperature[1] ?? '') === 'CEL'
                            && isset($temperature[0])
                            && is_numeric($temperature[0])) {
                            $this->parsedData['equipment'][$currentEquipment]['transport_temperature_c']
                                = (float) $temperature[0];
                        }
                    }
                    break;

                case 'FTX':
                    $this->parseFreeText($segment, $currentItem);
                    break;

                case 'PCI':
                    /*
                     * PCI/C210 contiene las marcas de los bultos.
                     *
                     * Ejemplos reales Hapag-Lloyd:
                     *   PCI++NM
                     *   PCI++NO MARKS
                     *   PCI++BATCH NO?'S?: 3010851...
                     *
                     * No se interpreta el contenido: se conserva como lo
                     * declaró el emisor y sólo se eliminan los caracteres
                     * técnicos de escape EDIFACT.
                     */
                    if ($currentItem !== null) {
                        $cargoMarks = $this->cleanEdifactText(
                            $segment['elements'][1] ?? ''
                        );

                        if ($cargoMarks !== '') {
                            $currentItem['cargo_marks'] = mb_substr(
                                $cargoMarks,
                                0,
                                500
                            );
                        }
                    }
                    break;

                case 'DGS':
                    // DGS+IMD+9+3077 → clase IMO 9, UN 3077
                    if ($currentItem !== null) {
                        $currentItem['is_dangerous_goods'] = true;
                        $currentItem['imdg_class'] = $segment['elements'][1] ?? null;
                        $currentItem['un_number'] = $segment['elements'][2] ?? null;
                    }
                    break;

                case 'CST':
                    // CST+1+9999.00+:169 → código arancelario
                    if ($currentItem !== null && !empty($segment['elements'][1])) {
                        $commodity = explode(':', $segment['elements'][1])[0] ?? '';
                        // Mismo formato que la NCM extraida de la descripcion,
                        // para que el campo no quede con dos formas distintas.
                        $commodity = $this->normalizeNcm($commodity);
                        if ($commodity !== null) {
                            $currentItem['commodity_code'] = $commodity;
                        }
                    }
                    break;
            }
        }


        // Agregar el último contenedor si tiene items
        if ($currentContainer !== null && !empty($currentContainer['items'])) {
            $this->parsedData['containers'][] = $currentContainer;
        }

        // Si no hay contenedores con items, crear uno por defecto con todos los items
        if (empty($this->parsedData['containers']) && !empty($allItems)) {
            $this->parsedData['containers'][] = [
                'sequence' => '1',
                'containers' => [],
                'items' => $allItems,
                'references' => []
            ];
        }
    }

    /**
     * Parsear header de intercambio
     */
    protected function parseInterchangeHeader(array $segment): void
    {
        if (count($segment['elements']) >= 4) {
            $this->parsedData['interchange'] = [
                'syntax_id' => $segment['elements'][0] ?? '',
                'sender' => $segment['elements'][1] ?? '',
                'receiver' => $segment['elements'][2] ?? '',
                'date_time' => $segment['elements'][3] ?? ''
            ];
        }
    }

    /**
     * Parsear header de mensaje
     */
    protected function parseMessageHeader(array $segment): void
    {
        if (count($segment['elements']) >= 2) {
            $messageType = explode(':', $segment['elements'][1] ?? '');
            $this->parsedData['message'] = [
                'reference' => $segment['elements'][0] ?? '',
                'type' => $messageType[0] ?? '',
                'version' => $messageType[1] ?? '',
                'release' => $messageType[2] ?? ''
            ];
        }
    }

    /**
     * Parsear tipo de mensaje
     */
    protected function parseMessageType(array $segment): void
    {
        $this->parsedData['message']['document_type'] = $segment['elements'][0] ?? '';
        $this->parsedData['message']['document_number'] = $segment['elements'][1] ?? '';
    }

    /**
     * Parsear fechas operativas del CUSCAR.
     *
     * Sólo se toman los calificadores que tienen destino inequívoco
     * en Voyage:
     *
     *   132 = llegada estimada
     *   136 = salida
     *
     * DTM+137 es fecha del documento/mensaje y NO se usa como salida.
     *
     * Los archivos reales recibidos no siempre respetan el tercer
     * componente de formato:
     *
     *   CMSP:
     *   DTM+132:202603020000:102
     *
     *   Hapag-Lloyd:
     *   DTM+132:20251202:102
     *
     * Por eso se interpreta el valor efectivo según su longitud,
     * sin alterar ni completar datos inexistentes.
     */
    protected function parseCuscarDateTime(array $segment): void
    {
        $composite = explode(':', $segment['elements'][0] ?? '');

        $qualifier = trim($composite[0] ?? '');
        $rawValue = trim($composite[1] ?? '');

        if (!in_array($qualifier, ['132', '136'], true)) {
            return;
        }

        $normalized = $this->normalizeCuscarDateTime($rawValue);

        if ($normalized === null) {
            throw new Exception(
                "Fecha inválida en DTM+{$qualifier}: '{$rawValue}'."
            );
        }

        if ($qualifier === '132') {
            $this->parsedData['dates']['estimated_arrival'] = $normalized;
        } elseif ($qualifier === '136') {
            $this->parsedData['dates']['departure'] = $normalized;
        }
    }

    /**
     * Normalizar las formas observadas realmente en los CUSCAR recibidos:
     *
     *   CCYYMMDD       -> fecha
     *   YYMMDDHHMM     -> fecha/hora
     *   CCYYMMDDHHMM   -> fecha/hora
     *
     * Cuando el archivo sólo informa fecha, se conserva esa fecha.
     * La columna Voyage es DATETIME y la base almacenará la hora como
     * 00:00:00 porque el emisor no informó una hora.
     */
    protected function normalizeCuscarDateTime(string $raw): ?string
    {
        $digits = preg_replace('/\D/', '', $raw);

        if ($digits === null) {
            return null;
        }

        $year = null;
        $month = null;
        $day = null;
        $hour = 0;
        $minute = 0;

        if (strlen($digits) === 8) {
            // CCYYMMDD
            $year = (int) substr($digits, 0, 4);
            $month = (int) substr($digits, 4, 2);
            $day = (int) substr($digits, 6, 2);
        } elseif (strlen($digits) === 10) {
            // YYMMDDHHMM
            $shortYear = (int) substr($digits, 0, 2);
            $year = $shortYear >= 70
                ? 1900 + $shortYear
                : 2000 + $shortYear;

            $month = (int) substr($digits, 2, 2);
            $day = (int) substr($digits, 4, 2);
            $hour = (int) substr($digits, 6, 2);
            $minute = (int) substr($digits, 8, 2);
        } elseif (strlen($digits) === 12) {
            // CCYYMMDDHHMM
            $year = (int) substr($digits, 0, 4);
            $month = (int) substr($digits, 4, 2);
            $day = (int) substr($digits, 6, 2);
            $hour = (int) substr($digits, 8, 2);
            $minute = (int) substr($digits, 10, 2);
        } else {
            return null;
        }

        if (!checkdate($month, $day, $year)
            || $hour < 0
            || $hour > 23
            || $minute < 0
            || $minute > 59) {
            return null;
        }

        return sprintf(
            '%04d-%02d-%02d %02d:%02d:00',
            $year,
            $month,
            $day,
            $hour,
            $minute
        );
    }

    /**
     * Parsear detalles de transporte
     */
    protected function parseTransportDetails(array $segment): void
    {
        if (count($segment['elements']) >= 2) {
            // TDT D.96B:
            // 0 = 8051 etapa de transporte
            // 1 = 8028 referencia del viaje
            // 4 = C040 transportista
            // 7 = C222 identificación del medio de transporte
            //
            // Ejemplos reales:
            // CMSP:
            // TDT+20+07LSRPFSR+1++CMSP:172+++:103::250-22/300-1:PY
            //
            // Hapag-Lloyd:
            // TDT+20+2511S+1++HLCU::5:HAPAG-LLOYD+++ZPQP1:172::CONAY I:PY

            $transportIdentification = explode(':', $segment['elements'][7] ?? '');

            // C222/8212 = nombre o identificación textual del medio.
            // Si 8212 no viene informado, usar C222/8213 como respaldo.
            $vesselName = trim($transportIdentification[3] ?? '');

            if ($vesselName === '') {
                $vesselName = trim($transportIdentification[0] ?? '');
            }

            $this->parsedData['vessel'] = [
                'transport_stage' => $segment['elements'][0] ?? '',
                'vessel_name' => $vesselName,
                'voyage_number' => $segment['elements'][1] ?? '',
                'carrier' => explode(':', $segment['elements'][4] ?? '')[0] ?? ''
            ];
        }
    }

    /**
     * Parsear ubicaciones/puertos
     */
    protected function parseLocation(array $segment): void
    {
        if (count($segment['elements']) >= 2) {
            $locationType = $segment['elements'][0] ?? '';
            $portCode = explode(':', $segment['elements'][1] ?? '')[0] ?? '';

            if ($locationType === '9') {
                $this->parsedData['ports']['loading'] = $portCode;
            } elseif ($locationType === '60' || $locationType === '11') {
                $this->parsedData['ports']['discharge'] = $portCode;
            }
        }
    }

    /**
     * Parsear información de contenedor
     */
    protected function parseContainerInfo(array $segment): ?array
    {
        if (count($segment['elements']) >= 1) {
            $containerInfo = [
                'sequence' => $segment['elements'][0] ?? '',
                'containers' => [],
                'items' => [],
                'references' => []
            ];
            
            $this->parsedData['containers'][] = $containerInfo;
            return $containerInfo;
        }
        
        return null;
    }

    /**
     * Parsear referencias
     */
    protected function parseReference(array $segment, ?array &$currentContainer): void
    {
        if (count($segment['elements']) >= 1) {
            $refType = explode(':', $segment['elements'][0] ?? '')[0] ?? '';
            $refValue = explode(':', $segment['elements'][0] ?? '')[1] ?? '';

            // ADZ = identificador fiscal de la parte anterior, en su campo propio.
            // Verificado 06/08/2026 sobre 250-22_316S-CUSCAR: aparece en los 13 de
            // 13 NAD+CN del archivo. Preferirlo al texto del nombre evita depender
            // de como cada emisor lo escriba ("... S.A. CUIT:? 30-69318494-7 ...").
            // Los roles CZ y CX no lo traen: para esos sigue el texto libre.
            if ($refType === 'ADZ' && $this->lastPartyRole !== null) {
                if ($currentContainer !== null
                    && isset($currentContainer['parties'][$this->lastPartyRole])) {
                    $currentContainer['parties'][$this->lastPartyRole]['tax_id'] = $refValue;
                } elseif (isset($this->parsedData['parties'][$this->lastPartyRole])) {
                    $this->parsedData['parties'][$this->lastPartyRole]['tax_id'] = $refValue;
                }
                $this->lastPartyRole = null;
            }

            if ($currentContainer) {
                if ($refType === 'BM') {
                    // BM = Bill of Lading number (número de conocimiento)
                    $currentContainer['references']['bill_number'] = $refValue;
                } elseif ($refType === 'PLZ') {
                    // Se conserva tal como viene, sin asignarle semántica aduanera.
                    $currentContainer['references']['permit'] = $refValue;
                } elseif ($refType === 'EP') {
                    // UN/EDIFACT D.96B: EP = Export permit number.
                    $currentContainer['references']['export_permit'] = $refValue;
                }
            }
        }
    }

    /**
     * Parsear medidas
     */
    protected function parseMeasurements(array $segment, ?array &$currentContainer, ?array &$currentItem): void
    {
        if (count($segment['elements']) >= 3) {
            $measureType = $segment['elements'][0] ?? '';
            $weightData = explode(':', $segment['elements'][2] ?? '');
            $unit = $weightData[0] ?? '';
            $value = $weightData[1] ?? '';

            // MEA+AAX+G+KGM a nivel CNI: peso bruto del conocimiento. Aparece
            // entre el GIS y el primer GID, o sea sin item activo, y hasta ahora
            // se descartaba entero.
            //
            // Los AAE y AAY por item NO se mapean: verificado sobre los dos
            // archivos de muestra (15 conocimientos, emisores y anios distintos),
            // el emisor escribe el total del conocimiento en cada item. En
            // SEGBUE26P102907 los 93 items dicen 340230, igual que el AAX.
            // Tomarlos como peso por item multiplicaria el conocimiento por 93.
            $attribute = $segment['elements'][1] ?? '';

            // MEA+AAX+G+KGM a nivel CNI: peso bruto declarado del conocimiento.
            // Aparece entre el GIS y el primer GID, o sea sin item activo. Es un
            // dato propio del conocimiento, independiente de lo que traigan los
            // items, y por eso se conserva aunque los items tambien tengan peso.
            if (!$currentItem && $currentContainer !== null
                && $measureType === 'AAX' && $unit === 'KGM') {
                $currentContainer['gross_weight_kg'] = $this->normalizeDecimal($value);
                return;
            }

            if ($currentItem) {
                // Los pesos del item se cargan TAL CUAL vienen en el archivo
                // (criterio de Roberto 22/07): algunos emisores los mandan bien y
                // otros repiten el total del conocimiento. Normalizarlos aca
                // obligaria a carga manual incluso cuando el dato viene correcto.
                // El operador corrige los que vengan mal.
                if ($measureType === 'AAE' && $attribute === 'G' && $unit === 'KGM') {
                    $currentItem['gross_weight_kg'] = $this->normalizeDecimal($value);
                } elseif ($measureType === 'AAY' && $attribute === 'G' && $unit === 'KGM') {
                    // AAY+G es peso bruto VERIFICADO, respaldo cuando no vino
                    // AAE+G. Los items se inicializan con gross_weight_kg = 0,
                    // por lo que isset() no sirve para detectar que AAE no cargó
                    // ningún peso. AAE+G mantiene prioridad cuando dejó un valor
                    // positivo.
                    if ((float) ($currentItem['gross_weight_kg'] ?? 0) <= 0) {
                        $currentItem['gross_weight_kg'] = $this->normalizeDecimal($value);
                    }
                } elseif ($measureType === 'AAE' && $unit === 'MTQ') {
                    // Este emisor copia el peso en el campo de volumen: manda
                    // MEA+AAE+G+KGM:222750 y MEA+AAE+AAW+MTQ:222750, el mismoFV
                    // numero (verificado 06/08/2026 sobre 250-22_316S-CUSCAR:
                    // ocurre en los 93/93, 34/34 y 139/139 grupos del archivo).
                    // 222.750 m3 para 820 bolsas es fisicamente imposible.
                    //
                    // Cuando el volumen coincide exacto con el peso bruto del
                    // mismo item se descarta: queda 0 = "no declarado", que es
                    // la verdad. No se recorta al maximo de la columna porque
                    // eso seria fabricar un dato.
                    $volumen = $this->normalizeDecimal($value);
                    $bruto   = $currentItem['gross_weight_kg'] ?? null;

                    $currentItem['volume_m3'] = ($bruto !== null && abs($volumen - $bruto) < 0.001)
                        ? 0
                        : $volumen;
                }
            }
        }
    }

    /**
     * Parsear partes (shipper, consignee, etc.)
     */
    /**
     * Las partes pertenecen al conocimiento (CNI), no al archivo. Cada CNI trae
     * su NAD+CN / NAD+CZ / NAD+CX y hasta 06/08/2026 todos se guardaban en un
     * unico casillero global: el ultimo pisaba a los anteriores y los 51
     * conocimientos del archivo terminaban con las mismas partes.
     *
     * Si no hay CNI abierto (NAD de cabecera) se conservan en parsedData como
     * respaldo para los conocimientos que no declaren las suyas.
     */
    protected function parseParty(array $segment, ?array &$currentContainer = null): void
    {
        if (count($segment['elements']) >= 3) {
            $partyType = $segment['elements'][0] ?? '';

            // El nombre se parte cuando el dato contiene '+' sin escapar: en el
            // archivo de MSC los telefonos vienen como "PHONE; +54 115300 7300",
            // y el explode('+') de la linea 201 corta ahi. Por eso el cliente 733
            // quedo guardado como "...C1098AAQ - PHONE; ". Se reconstruye uniendo
            // todos los elementos desde el tercero en adelante.
            $partyRaw = implode('+', array_slice($segment['elements'], 2));

            // ':' es separador de componentes EDIFACT: el primero es la razon
            // social y el resto el domicilio. Se parte ANTES de cleanEdifactText
            // porque esa funcion quita el '?' de escape, y ahi un ':' literal
            // quedaria igual que un separador y cortaria donde no corresponde.
            $partyParts = preg_split('/(?<!\?):/', $partyRaw);

            // El identificador fiscal se busca sobre el texto COMPLETO, antes de
            // partir. El archivo escribe "... S.A. CUIT:? 30-69318494-7 ...": el
            // ':' separador cae entre la etiqueta y el numero, con lo cual al
            // partir la etiqueta queda en el nombre y los digitos en el domicilio,
            // y el extractor (que necesita las dos cosas juntas) no encuentra
            // nada. Regresion introducida el 05/08/2026 al agregar el corte;
            // reportada por Roberto el 06/08 como clientes duplicados sin CUIT.
            $cleanPartyRaw = $this->cleanEdifactText($partyRaw);

            $partyTaxId = $this->extractEmbeddedTaxId(
                $cleanPartyRaw
            );

            // El nombre se sanea después y puede perder la etiqueta CUIT/RUC/etc.
            // Conservar ahora el tipo sólo cuando el emisor lo declara expresamente.
            $partyTaxType = $this->extractExplicitTaxTypeFromText(
                $cleanPartyRaw,
                $partyTaxId
            );

            $partyName = $this->cleanEdifactText($partyParts[0] ?? '');

            // Marca de "a la atencion de" pegada al final del nombre
            // ("MAERSK AS - ATA: WEBER..."). Solo al final y como palabra
            // completa, para no tocar razones sociales tipo "ATACAMA S.A.".
            //
            // La clase incluye guion medio (U+2013) y largo (U+2014) ademas del
            // ASCII: el archivo trae 0x96, que en Windows-1252 es guion medio.
            // Sin eso quedaba "MAERSK AS -" con el guion colgando.
            // Se recorta en bucle porque pueden venir encadenadas: en
            // "AGENCIA MARITIMA INTERNACIONAL SA CUIT 30-58534342-7 DIRECCION"
            // primero cae DIRECCION y recien ahi queda el CUIT al final.
            // El numero solo se borra si lo precede una etiqueta, para no tocar
            // razones sociales tipo "NAVIERA DEL SUR 2000 S.A.".
            $marcas    = '(?:ATTN|ATTE|ATT|ATN|ATA|ATENCION|C\/O|CARE\s+OF)';
            $etiquetas = '(?:RUT\s*\/\s*VAT|RUC\s*\/\s*TAX\s?ID|TAX\s?ID|TAXID|R\.U\.C\.|RUC|CUIT(?:\s*NBR)?|CNPJ|NIT|DIRECCION|DIRECCI\x{00D3}N|PAIS|PA\x{00CD}S)';
            $sep       = '[\s\-\x{2013}\x{2014},;]';

            for ($i = 0; $i < 5; $i++) {
                $previo = $partyName;

                $partyName = preg_replace('/[\s\-\x{2013}\x{2014},;:.]*\b' . $marcas . '\b[\s\-\x{2013}\x{2014},;:.]*$/iu', '', $partyName);
                $partyName = preg_replace('/' . $sep . '*\b' . $etiquetas . '\b[\s:.\-]*[0-9][0-9.\-\/]*$/iu', '', $partyName);
                $partyName = preg_replace('/' . $sep . '*\b' . $etiquetas . '\b[\s\-\x{2013}\x{2014},;:]*$/iu', '', $partyName);
                $partyName = preg_replace('/' . $sep . '+$/u', '', $partyName);

                if ($partyName === $previo) {
                    break;
                }
            }

            $partyAddress = count($partyParts) > 1
                ? $this->cleanEdifactText(implode(' ', array_slice($partyParts, 1)))
                : null;

            // Roles EDIFACT (verificado contra CMSP.EDI y 250-22_316S-CUSCAR.EDI,
            // y confirmado por Roberto 20/07):
            //   CN = consignatario  |  CZ = cargador  |  CX = notificatario
            // Antes estaban rotados (CN→shipper, CX→consignee, CZ→notify), por eso
            // el cargador mostraba lo mismo que el consignatario: en los dos archivos
            // CN y CX son la misma empresa y CZ es la distinta.
            // N1 lo usa Hapag-Lloyd para el notificatario en lugar de CX
            // (verificado 06/08/2026 sobre 2047270O_CUSCAR: 51 NAD+N1).
            $rol = match ($partyType) {
                'CN' => 'consignee',
                'CZ' => 'shipper',
                'CX', 'N1' => 'notify',
                default => null,
            };

            if ($rol !== null) {
                $parte = [
                    'name' => $partyName,
                    'address' => $partyAddress,
                    'type' => $rol,
                    // Del texto completo. El RFF+ADZ pisa tax_id después si viene.
                    // tax_type sólo existe cuando CUIT/RUC/CNPJ/NIT fue explícito.
                    'tax_id' => $partyTaxId,
                    'tax_type' => $partyTaxType,
                ];

                if ($currentContainer !== null) {
                    $currentContainer['parties'][$rol] = $parte;
                } else {
                    $this->parsedData['parties'][$rol] = $parte;
                }
            }

            $this->lastPartyRole = match ($partyType) {
                'CN' => 'consignee',
                'CZ' => 'shipper',
                'CX' => 'notify',
                default => null,
            };
        }
    }

    /**
     * Limpiar texto EDIFACT: aplica el caracter de escape y normaliza espacios.
     *
     * En UN/EDIFACT '?' libera al caracter siguiente. El emisor lo usa dentro del
     * nombre ("CUIT:? 30-69318494-7"), y eso rompia la extraccion del identificador
     * fiscal: el patron de ExtractsEmbeddedTaxId acepta un solo separador despues
     * del prefijo, y ahi habia dos (':' y '?'). Por eso el cliente 733 quedo con
     * tax_id null teniendo el CUIT escrito en el propio nombre.
     */
    protected function cleanEdifactText(?string $text): string
    {
        if ($text === null) {
            return '';
        }

        $clean = preg_replace('/\?(.)/', '$1', $text);

        // Espacio duro (U+00A0). trim() y \s no lo consideran espacio blanco,
        // por eso quedaba colgado al final de "MAERSK LINE ARGENTINA SA".
        $clean = str_replace("\xC2\xA0", ' ', $clean);

        return trim(preg_replace('/\s+/', ' ', $clean));
    }

    /**
     * Parsear ítem de mercadería
     */
    protected function parseGoodsItem(array $segment, ?array &$currentContainer): ?array
    {
        if (count($segment['elements']) >= 2) {
            $item = [
                'sequence' => $segment['elements'][0] ?? '',
                'package_info' => $segment['elements'][1] ?? '',
                'description' => '',
                'gross_weight_kg' => 0,
                'tare_weight_kg' => 0,
                'volume_m3' => 0,
                'containers' => []
            ];

            if ($currentContainer) {
                $currentContainer['items'][] = &$item;
            } else {
                $this->parsedData['items'][] = $item;
            }

            return $item;
        }

        return null;
    }

    /**
     * Parsear colocación de contenedor
     */
    protected function parseContainerPlacement(array $segment, ?array &$currentContainer, ?array &$currentItem): void
    {
        if (count($segment['elements']) >= 1) {
            $containerNumber = $segment['elements'][0] ?? '';

            if ($currentItem && !empty($containerNumber)) {
                $currentItem['containers'][] = $containerNumber;
            }

            if ($currentContainer && !empty($containerNumber)) {
                $currentContainer['containers'][] = $containerNumber;
            }
        }
    }

    /**
     * Parsear texto libre (descripción)
     */
    protected function parseFreeText(array $segment, ?array &$currentItem): void
    {
        if (count($segment['elements']) >= 4 && $currentItem) {
            /*
             * FTX puede contener caracteres reservados escapados con '?'.
             *
             * Ejemplos reales Hapag-Lloyd:
             *   NCM NO.?: 2918.99.99
             *   TEMPERATURE ?+20C
             *   1X40?' HC CONTAINER
             *
             * parseEdiFile ya conserva esos caracteres dentro del mismo
             * elemento; acá se elimina únicamente el release character.
             */
            $description = $this->cleanEdifactText(
                $segment['elements'][3] ?? ''
            );

            if ($description !== '') {
                $currentItem['description'] = $description;
            }
        }
    }

    /**
     * Registrar la importación en ManifestImport (con dup-check por hash).
     */
    protected function createImportRecord(string $filePath): ManifestImport
    {
        $user = auth()->user();
        if (!$user) {
            throw new Exception('Usuario no autenticado para crear registro de importación');
        }

        $fileName = basename($filePath);
        $fileSize = file_exists($filePath) ? filesize($filePath) : null;
        $fileHash = file_exists($filePath) ? ManifestImport::generateFileHash($filePath) : null;

        $companyId = null;
        if ($user->company_id) {
            $companyId = $user->company_id;
        } elseif ($user->userable_type === 'App\Models\Company' && $user->userable_id) {
            $companyId = (int) $user->userable_id;
        }

        if ($fileHash && $companyId) {
            $existing = ManifestImport::isFileAlreadyImported($fileHash, $companyId);
            if ($existing) {
                throw new Exception("Este archivo ya fue importado anteriormente (ID: {$existing->id})");
            }
        }

        return ManifestImport::createForImport([
            'company_id'      => $companyId,
            'user_id'         => $user->id,
            'file_name'       => $fileName,
            'file_format'     => 'cmsp',
            'file_size_bytes' => $fileSize,
            'file_hash'       => $fileHash,
            'parser_config'   => [
                'parser_class' => self::class,
            ],
        ]);
    }

    /**
     * Crear objetos de modelo
     */
    protected function createModelObjects(
        array $data,
        ?ManifestImport $importRecord = null,
        ?float $startTime = null,
        array $options = []
    ): ManifestParseResult
    {
        return DB::transaction(function () use ($data, $importRecord, $startTime, $options) {
            $voyage = $this->createVoyage($data, $options);
            $shipment = $this->createShipment($voyage, $data);
            
            $billsOfLading = [];
            
            // Iterar sobre cada CNI (cada uno es un BL separado)
            foreach ($data['containers'] as $containerGroup) {
                // Obtener bill_number del RFF+BM, o usar fallback
                $billNumber = $containerGroup['references']['bill_number'] 
                    ?? $data['message']['document_number'] . '-' . ($containerGroup['sequence'] ?? uniqid());
                
                // Crear BL para este grupo
                $billOfLading = $this->createBillOfLadingForGroup($shipment, $data, $billNumber, $containerGroup);
                $billsOfLading[] = $billOfLading;
                
                // Crear items y contenedores solo para este BL
                $this->createContainersAndItemsForGroup($billOfLading, $containerGroup);
                
                $this->stats['processed_bls']++;
            }

            Log::info('CMSP EDI parsing completed successfully', [
                'voyage_id' => $voyage->id,
                'shipment_id' => $shipment->id,
                'bills_created' => count($billsOfLading),
                'stats' => $this->stats
            ]);

            // Registrar objetos creados y completar el registro de importación.
            // El revert reconstruye items/containers (incluido el pivote) desde el voyage_id.
            if ($importRecord) {
                $billIds = array_map(fn($bl) => $bl->id, $billsOfLading);

                $itemIds = [];
                foreach ($billsOfLading as $bl) {
                    $itemIds = array_merge($itemIds, $bl->shipmentItems()->pluck('id')->all());
                }

                // No se trackean container_ids a propósito: getCreatedContainers busca por
                // número de forma global y podría incluir containers de otros viajes; el
                // revert los reconstruye de forma segura por el pivote.
                $importRecord->recordCreatedObjects([
                    'voyage'   => [$voyage->id],
                    'shipment' => [$shipment->id],
                    'bill'     => $billIds,
                    'item'     => $itemIds,
                ]);

                $completionData = [
                    'voyage_id' => $voyage->id,
                    'created_bills' => count($billIds),
                    'created_items' => count($itemIds),
                    'created_containers' => $this->stats['processed_containers'] ?? 0,
                    'processing_time_seconds' => $startTime
                        ? round(microtime(true) - $startTime, 2)
                        : null,
                    'import_statistics' => $this->stats,
                    'warnings' => $this->stats['warnings'],
                    'warnings_count' => count($this->stats['warnings']),
                    'notes' => 'Importación CMSP EDI completada',
                ];

                if (!empty($this->stats['warnings'])) {
                    $importRecord->markAsCompletedWithWarnings(
                        $completionData
                    );
                } else {
                    $importRecord->markAsCompleted(
                        $completionData
                    );
                }
            }

            return ManifestParseResult::success(
                voyage: $voyage,
                shipments: [$shipment],
                containers: $this->getCreatedContainers($data),
                billsOfLading: $billsOfLading,
                warnings: $this->stats['warnings'],
                statistics: [
                    'processed_containers' => $this->stats['processed_containers'],
                    'processed_items' => $this->stats['processed_items'],
                    'processed_bls' => $this->stats['processed_bls'],
                    'errors' => $this->stats['errors'],
                    'warnings' => $this->stats['warnings']
                ]
            );
        });
    }

    /**
     * Crear voyage
     */
    protected function createVoyage(array $data, array $options = []): Voyage
    {
        // OBTENER COMPANY_ID CORRECTAMENTE
        $user = auth()->user();
        $companyId = null;

        if ($user->company_id) {
            $companyId = $user->company_id;
        } elseif ($user->userable_type === 'App\Models\Company' && $user->userable_id) {
            $companyId = (int) $user->userable_id;
        } else {
            throw new Exception("Usuario no tiene empresa asignada. User ID: {$user->id}");
        }

         Log::info('createVoyage Debug CMSP', [
            'user_id' => $user?->id,
            'user_email' => $user?->email,
            'user_company_id' => $user?->company_id,
            'user_userable_type' => $user?->userable_type,
            'user_userable_id' => $user?->userable_id,
            'computed_company_id' => $companyId
        ]);

        $originPort = $this->findOrCreatePort($data['ports']['loading'] ?? 'ARBUE');
        $destPort = $this->findOrCreatePort($data['ports']['discharge'] ?? 'PYASU');

        /*
         * La embarcación seleccionada por el operador es un respaldo.
         *
         * Si el CUSCAR identifica una embarcación, esa información prevalece:
         * - si ya existe en la empresa, se utiliza;
         * - si no existe, se incorpora con únicamente los datos conocidos.
         *
         * Si el archivo no identifica ninguna embarcación, se utiliza la
         * seleccionada por el operador en el formulario.
         */
        $fileVesselName = trim(
            (string) ($data['vessel']['vessel_name'] ?? '')
        );

        if ($fileVesselName !== '') {
            $vessel = $this->findOrCreateVessel(
                $fileVesselName,
                $companyId
            );
        } else {
            $vesselId = $options['vessel_id'] ?? null;

            if (!$vesselId) {
                throw new Exception(
                    'El archivo CUSCAR no informa una embarcación y no se seleccionó una embarcación de respaldo.'
                );
            }

            $vessel = Vessel::where('id', $vesselId)
                ->where('company_id', $companyId)
                ->first();

            if (!$vessel) {
                throw new Exception(
                    "Embarcación con ID {$vesselId} no encontrada para la empresa."
                );
            }
        }

        $voyageNumber = ($data['vessel']['voyage_number'] ?? '') . '-' .
                       ($data['message']['document_number'] ?? uniqid());

        // DETERMINAR cargo_type basado en puertos reales del EDI
        $loadingPort = $data['ports']['loading'] ?? 'ARBUE';
        $dischargePort = $data['ports']['discharge'] ?? 'PYASU';
        
        $cargoType = $this->determineCargTypeFromPorts($loadingPort, $dischargePort);

        // El voyage_number es único global. Si ya existe (en cualquier empresa),
        // se bloquea la importación con un error claro en lugar de reusar el viaje.
        $this->guardVoyageNumberIsFree($voyageNumber);

        /*
         * Fecha de salida:
         * sólo se conserva DTM+136 cuando el archivo la informa.
         * Si el CUSCAR no trae DTM+136, queda NULL.
         *
         * DTM+137 es fecha del documento y nunca reemplaza la salida.
         */
        $departureDate = $data['dates']['departure'] ?? null;

        /*
         * La llegada estimada se toma exclusivamente de DTM+132.
         * estimated_arrival_date continúa siendo obligatoria en Voyage,
         * por lo que ante su ausencia se detiene la importación.
         */
        $estimatedArrivalDate = $data['dates']['estimated_arrival'] ?? null;

        if ($estimatedArrivalDate === null) {
            throw new Exception(
                'El archivo CUSCAR no informa la fecha estimada de llegada DTM+132.'
            );
        }

        /*
         * Sólo puede validarse cronología cuando el archivo informó salida.
         * Si no existe DTM+136, departure_date permanece NULL.
         */
        if ($departureDate !== null) {
            $departureTimestamp = strtotime(
                (string) $departureDate
            );

            $estimatedArrivalTimestamp = strtotime(
                (string) $estimatedArrivalDate
            );

            if (
                $departureTimestamp === false
                || $estimatedArrivalTimestamp === false
            ) {
                throw new Exception(
                    'No se pudo validar la fecha de salida o la fecha estimada de llegada del viaje.'
                );
            }

            $departureDay = date(
                'Y-m-d',
                $departureTimestamp
            );

            $estimatedArrivalDay = date(
                'Y-m-d',
                $estimatedArrivalTimestamp
            );

            if ($departureDay > $estimatedArrivalDay) {
                throw new Exception(
                    'La fecha de salida no puede ser posterior a la fecha estimada de llegada '
                    . 'informada por el archivo CUSCAR.'
                );
            }
        }

        return Voyage::create([
            'company_id' => $companyId,
            'voyage_number' => $voyageNumber,
            'lead_vessel_id' => $vessel->id,
            'origin_port_id' => $originPort->id,
            'destination_port_id' => $destPort->id,
            'origin_country_id' => $originPort->country_id,         // ← AGREGAR
            'destination_country_id' => $destPort->country_id,      // ← AGREGAR
            'departure_date' => $departureDate,
            'estimated_arrival_date' => $estimatedArrivalDate,
            'status' => 'planning',
            'cargo_type' => $cargoType,
            'created_by_user_id' => auth()->id(),
            'manifest_format' => 'CMSP_EDI_CUSCAR',
            'import_source' => 'cmsp_edi_parser'
        ]);
    }

    /**
     * Determinar cargo_type basado en puertos origen/destino del EDI
     */
    protected function determineCargTypeFromPorts(string $loadingPort, string $dischargePort): string
    {
        // Mapeo de puertos a países
        $argentinePorts = ['ARBUE'];
        $paraguayanPorts = ['PYASU'];
        
        $isFromArgentina = in_array($loadingPort, $argentinePorts);
        $isToParaguay = in_array($dischargePort, $paraguayanPorts);
        $isFromParaguay = in_array($loadingPort, $paraguayanPorts);
        $isToArgentina = in_array($dischargePort, $argentinePorts);
        
        // ARBUE → PYASU = Import (para Paraguay)
        if ($isFromArgentina && $isToParaguay) {
            return 'import';
        }
        
        // PYASU → ARBUE = Export (desde Paraguay)
        if ($isFromParaguay && $isToArgentina) {
            return 'export';
        }
        
        // Default: transit (tránsito)
        return 'transit';
    }

    /**
     * Buscar o crear vessel basado en nombre del EDI
     */
    protected function findOrCreateVessel(
        string $vesselName,
        int $companyId
    ): Vessel {
        $vesselName = trim($vesselName);

        $vessel = Vessel::where('company_id', $companyId)
            ->where('name', $vesselName)
            ->first();

        if ($vessel) {
            return $vessel;
        }

        /*
         * El archivo acredita la existencia y el nombre de la embarcación,
         * pero no necesariamente informa sus datos registrales o técnicos.
         *
         * Se incorpora el registro sin fabricar información.
         * Los datos desconocidos quedan NULL y la ficha queda sin verificar
         * hasta que el operador complete la información real.
         */
        $vessel = new Vessel();

        $vessel->name = $vesselName;
        $vessel->company_id = $companyId;

        $vessel->registration_number = null;
        $vessel->vessel_type_id = null;
        $vessel->flag_country_id = null;

        $vessel->length_meters = null;
        $vessel->beam_meters = null;
        $vessel->draft_meters = null;

        $vessel->cargo_capacity_tons = null;
        $vessel->max_cargo_capacity = null;

        /*
         * Estas columnas poseen defaults históricos en la tabla.
         * Se escribe NULL explícitamente para no atribuir características
         * que el archivo CUSCAR nunca informó.
         */
        $vessel->engine_hours = null;
        $vessel->ownership_type = null;
        $vessel->available_for_charter = null;
        $vessel->current_crew_size = null;
        $vessel->crew_quarters_available = null;
        $vessel->passenger_capacity = null;
        $vessel->maintenance_interval_days = null;

        $vessel->has_cranes = null;
        $vessel->has_conveyor_system = null;
        $vessel->has_refrigeration = null;
        $vessel->has_gps = null;
        $vessel->has_radar = null;
        $vessel->has_ais = null;
        $vessel->green_technology = null;

        /*
         * Estos sí son estados internos conocidos por la aplicación:
         * el registro puede utilizarse, pero sus datos aún no están verificados.
         */
        $vessel->operational_status = 'active';
        $vessel->active = true;
        $vessel->verified = false;
        $vessel->created_by_user_id = auth()->id();

        $vessel->save();

        $warning = sprintf(
            'La embarcación "%s" fue informada por el archivo CUSCAR y no estaba registrada. '
            . 'Se incorporó a la empresa y fue asignada al viaje. '
            . 'Su ficha tiene datos registrales o técnicos pendientes de completar.',
            $vesselName
        );

        $this->stats['warnings'][] = $warning;

        Log::warning(
            'Embarcación incorporada desde CUSCAR con datos incompletos',
            [
                'vessel_id' => $vessel->id,
                'vessel_name' => $vesselName,
                'company_id' => $companyId,
            ]
        );

        return $vessel;
    }

    /**
     * Crear shipment
     */
    protected function createShipment(Voyage $voyage, array $data): Shipment
    {
        return Shipment::create([
            'voyage_id' => $voyage->id,
            'vessel_id' => $voyage->lead_vessel_id,
            'shipment_number' => 'CMSP-' . ($data['message']['document_number'] ?? uniqid()),
            'sequence_in_voyage' => $this->getNextSequenceInVoyage($voyage->id),
            'vessel_role' => 'single',
            'cargo_capacity_tons' => $voyage->leadVessel?->cargo_capacity_tons,
            'status' => 'planning',
            'active' => true,
            'created_by_user_id' => auth()->id()
        ]);
    }

    /**
     * Obtener siguiente número de secuencia para el viaje
     */
    protected function getNextSequenceInVoyage(int $voyageId): int
    {
        $maxSequence = Shipment::where('voyage_id', $voyageId)
            ->max('sequence_in_voyage') ?? 0;
        
        return $maxSequence + 1;
    }

    /**
     * Crear bill of lading y sus ítems (si corresponde)
     */
    protected function createBillOfLading(Shipment $shipment, array $data): BillOfLading
    {
        $shipper   = $this->findOrCreateClient($data['parties']['shipper']   ?? null);
        $consignee = $this->findOrCreateClient($data['parties']['consignee'] ?? null);

        $billNumber = $data['message']['document_number'] ?? ('CMSP-' . uniqid());

        // Fechas base
        $billDate = $this->extractBillDate($data) ?? now()->toDateString();
        $loadingDate = $this->extractLoadingDate($data)
            ?? optional($shipment->voyage)->departure_date
            ?? $billDate;

        // (opcional) Si tu schema lo requiere:
        $dischargeDate = $this->extractDischargeDate($data)
            ?? optional($shipment->voyage)->arrival_date
            ?? $loadingDate;

        // Crear BL
        $bl = BillOfLading::create([
            'shipment_id'               => $shipment->id,
            'bill_number'               => (string) $billNumber,   // evita perder ceros
            'shipper_id'                => $shipper?->id,
            'consignee_id'              => $consignee?->id,
            'loading_port_id'           => $shipment->voyage->origin_port_id,
            'discharge_port_id'         => $shipment->voyage->destination_port_id,
            'bill_type'                 => 'house',
            'origin_country_id'         => $shipment->voyage->origin_country_id,
            'destination_country_id'    => $shipment->voyage->destination_country_id,
            'loading_customs_id'        => null,
            'discharge_customs_id'      => null,
            'primary_cargo_type_id'     => $this->getDefaultCargoTypeId(),
            'primary_packaging_type_id' => $this->getDefaultPackagingTypeId(),
            'freight_terms'             => 'prepaid',
            'is_consolidated'           => false,
            'documentation_complete'    => false,
            'customs_cleared'           => false,
            'cargo_description'         => 'Según detalle',
            'total_packages'            => 0,
            'gross_weight_kg'           => 0,
            'net_weight_kg'             => 0,
            'volume_m3'                 => 0,
            'status'                    => 'draft',
            'issue_date'                => now()->toDateString(),
            'bill_date'                 => $billDate,
            'loading_date'              => $loadingDate,
            // 'discharge_date'         => $dischargeDate, // descomentar si es NOT NULL
            'created_by_user_id'        => auth()->id(),
        ]);

        
        return $bl;
    }

    /**
     * Crear BL para un grupo específico de CNI
     */
    protected function createBillOfLadingForGroup(Shipment $shipment, array $data, string $billNumber, array $containerGroup = []): BillOfLading
    {
        // Partes propias del conocimiento. Las de $data son de cabecera y solo
        // sirven de respaldo para los CNI que no declaren las suyas: usarlas
        // siempre hacia que los 51 conocimientos del archivo compartieran las
        // mismas partes (reportado por Roberto 06/08/2026).
        $partesDelGrupo = $containerGroup['parties'] ?? [];

        $shipperData = $partesDelGrupo['shipper']
            ?? $data['parties']['shipper']
            ?? null;

        $consigneeData = $partesDelGrupo['consignee']
            ?? $data['parties']['consignee']
            ?? null;

        $notifyData = $partesDelGrupo['notify']
            ?? $data['parties']['notify']
            ?? null;

        $shipper = $this->findOrCreateClient($shipperData);
        $consignee = $this->findOrCreateClient($consigneeData);

        /*
         * Hapag-Lloyd usa literalmente "SAME AS CONSIGNEE" cuando el
         * notificatario es el mismo consignatario. No es una razón social
         * y por lo tanto no debe crear/reutilizar un Client.
         *
         * Se conserva como texto del conocimiento, igual que ya hace el
         * importador G2Ocean para este mismo caso.
         */
        $notifyParty = null;
        $notifyText = null;

        $notifyName = strtoupper(trim(
            (string) ($notifyData['name'] ?? '')
        ));

        if ($notifyName === 'SAME AS CONSIGNEE') {
            $notifyText = 'SAME AS CONSIGNEE';
        } elseif ($notifyName !== '') {
            $notifyParty = $this->findOrCreateClient($notifyData);
        }

        // Si alguna linea del grupo trae contenedores, el conocimiento es
        // contenedorizado. Mismo criterio que en createShipmentItem().
        $tieneContenedores = false;
        foreach ($containerGroup['items'] ?? [] as $itemDelGrupo) {
            if (!empty($itemDelGrupo['containers'])) {
                $tieneContenedores = true;
                break;
            }
        }

        $billDate = $this->extractBillDate($data) ?? now()->toDateString();
        $loadingDate = $this->extractLoadingDate($data)
            ?? optional($shipment->voyage)->departure_date
            ?? $billDate;

        /*
         * Descripción real del conocimiento.
         *
         * En algunos CUSCAR el mismo GID se repite una vez por cada SGP,
         * por lo que la misma descripción puede aparecer muchas veces.
         * Se conservan únicamente las descripciones distintas informadas
         * realmente por el archivo.
         */
        $cargoDescriptions = [];

        foreach ($containerGroup['items'] ?? [] as $itemDelGrupo) {
            $description = trim((string) ($itemDelGrupo['description'] ?? ''));

            if ($description === '') {
                continue;
            }

            // Mismo saneamiento utilizado al crear ShipmentItem.
            $description = mb_convert_encoding($description, 'UTF-8', 'UTF-8');
            $description = preg_replace('/[^\x20-\x7E\xC0-\xFF]/', '', $description);
            $description = mb_substr($description, 0, 5000);

            if ($description !== '') {
                $cargoDescriptions[] = $description;
            }
        }

        $cargoDescriptions = array_values(array_unique($cargoDescriptions));

        $cargoDescription = !empty($cargoDescriptions)
            ? implode(' | ', $cargoDescriptions)
            : 'Según detalle';

        $bill = BillOfLading::create([
            'shipment_id'               => $shipment->id,
            'bill_number'               => (string) $billNumber,
            'shipper_id'                => $shipper?->id,
            'consignee_id'              => $consignee?->id,
            // El notificatario nunca se persistía: se parseaba y se descartaba.
            'notify_party_id'           => $notifyParty?->id,
            'notify_party_text'         => $notifyText,
            'loading_port_id'           => $shipment->voyage->origin_port_id,
            'discharge_port_id'         => $shipment->voyage->destination_port_id,
            'bill_type'                 => 'house',
            'origin_country_id'         => $shipment->voyage->origin_country_id,
            'destination_country_id'    => $shipment->voyage->destination_country_id,
            'loading_customs_id'        => null,
            'discharge_customs_id'      => null,
            'primary_cargo_type_id'     => $tieneContenedores
                ? $this->getContainerCargoTypeId()
                : $this->getDefaultCargoTypeId(),
            'primary_packaging_type_id' => $tieneContenedores
                ? $this->getContainerPackagingTypeId()
                : $this->getDefaultPackagingTypeId(),
            'freight_terms'             => 'prepaid',
            'is_consolidated'           => false,
            'documentation_complete'    => false,
            'customs_cleared'           => false,

            // RFF+EP del CUSCAR: número de permiso de exportación.
            'permiso_embarque'          => $containerGroup['references']['export_permit'] ?? null,

            // Descripción real informada en los GID/FTX de este CNI.
            'cargo_description'         => $cargoDescription,
            'total_packages'            => 0,
            // Peso bruto del conocimiento tomado del MEA+AAX del CNI.
            'gross_weight_kg'           => $containerGroup['gross_weight_kg'] ?? 0,
            'net_weight_kg'             => 0,
            'volume_m3'                 => 0,
            'status'                    => 'draft',
            'issue_date'                => now()->toDateString(),
            'bill_date'                 => $billDate,
            'loading_date'              => $loadingDate,
            'created_by_user_id'        => auth()->id(),
        ]);

        /*
         * Dirección por conocimiento:
         *
         * - si coincide con la ficha maestra, no se duplica;
         * - si difiere, se conserva en BillOfLadingContact;
         * - nunca se pisa el domicilio maestro del cliente.
         */
        if ($specific = $this->resolveSpecificAddress(
            $shipper,
            $shipperData['address'] ?? null,
            'shipper'
        )) {
            $bill->specificContacts()->create($specific);
        }

        if ($specific = $this->resolveSpecificAddress(
            $consignee,
            $consigneeData['address'] ?? null,
            'consignee'
        )) {
            $bill->specificContacts()->create($specific);
        }

        if ($notifyParty && ($specific = $this->resolveSpecificAddress(
            $notifyParty,
            $notifyData['address'] ?? null,
            'notify_party'
        ))) {
            $bill->specificContacts()->create($specific);
        }

        return $bill;
    }

    /**
     * Crear contenedores e items para un grupo específico de CNI
     */
    protected function createContainersAndItemsForGroup(BillOfLading $billOfLading, array $containerGroup): void
    {
        /*
         * En CUSCAR un mismo GID puede repetirse una vez por cada SGP/contenedor.
         *
         * Ejemplo real CMSP:
         *   GID+1+820:BG:::BAG
         *   FTX+AAA+++AZUCAR
         *   MEA...222750
         *   SGP+CONTENEDOR1
         *
         *   GID+1+820:BG:::BAG
         *   FTX+AAA+++AZUCAR
         *   MEA...222750
         *   SGP+CONTENEDOR2
         *
         * No son dos mercaderías: es la misma línea distribuida físicamente
         * entre distintos contenedores.
         *
         * Se consolidan únicamente items cuyos datos de mercadería son
         * exactamente iguales. Los contenedores se excluyen de la firma porque
         * son justamente el dato que cambia entre las repeticiones.
         */
        $itemsConsolidados = [];

        foreach ($containerGroup['items'] ?? [] as $item) {
            $firmaData = [
                'sequence' => $item['sequence'] ?? '',
                'package_info' => $item['package_info'] ?? '',
                'description' => $item['description'] ?? '',
                'cargo_marks' => $item['cargo_marks'] ?? null,
                'gross_weight_kg' => $item['gross_weight_kg'] ?? 0,
                'tare_weight_kg' => $item['tare_weight_kg'] ?? 0,
                'volume_m3' => $item['volume_m3'] ?? 0,
                'is_dangerous_goods' => $item['is_dangerous_goods'] ?? false,
                'imdg_class' => $item['imdg_class'] ?? null,
                'un_number' => $item['un_number'] ?? null,
                'commodity_code' => $item['commodity_code'] ?? null,
            ];

            $firma = sha1(json_encode($firmaData, JSON_UNESCAPED_UNICODE));

            if (!isset($itemsConsolidados[$firma])) {
                $item['_physical_occurrences'] = 1;
                $item['containers'] = array_values(array_unique($item['containers'] ?? []));
                $itemsConsolidados[$firma] = $item;
                continue;
            }

            $itemsConsolidados[$firma]['_physical_occurrences']++;

            $itemsConsolidados[$firma]['containers'] = array_values(array_unique(array_merge(
                $itemsConsolidados[$firma]['containers'] ?? [],
                $item['containers'] ?? []
            )));
        }

        foreach ($itemsConsolidados as $item) {
            $shipmentItem = $this->createShipmentItem($billOfLading, $item);

            foreach ($item['containers'] as $containerNumber) {
                $this->createContainer($containerNumber, $item, $shipmentItem);
            }

            $this->stats['processed_items']++;
        }
    }

    /**
     * Peso del contenedor.
     *
     * Si el GID apareció una sola vez, se conserva el peso declarado en ese item.
     *
     * Si el mismo GID apareció físicamente varias veces, una por cada SGP,
     * el peso del item representa la mercadería lógica completa y para cada
     * contenedor se usa su VGM cuando el archivo lo proporciona.
     */
    protected function pesoContenedor(string $containerNumber, array $itemData): float
    {
        if (stripos($itemData['description'] ?? '', 'VACIO') !== false) {
            return 0;
        }

        $delItem = (float) ($itemData['gross_weight_kg'] ?? 0);
        $repeticiones = (int) ($itemData['_physical_occurrences'] ?? 1);

        if ($repeticiones <= 1) {
            return $delItem;
        }

        $vgm = (float) ($this->parsedData['equipment'][$containerNumber]['vgm_weight_kg'] ?? 0);

        return $vgm > 0 ? $vgm : $delItem;
    }

    protected function getDefaultCargoTypeId(): int
    {
        return CargoType::where('active', true)->where('is_common', true)->first()?->id ?? 1;
    }

    protected function getDefaultPackagingTypeId(): int  
    {
        return PackagingType::where('active', true)->where('is_common', true)->first()?->id ?? 1;
    }

    /**
     * Tipos para carga contenedorizada. Verificados en produccion 31/07/2026:
     * CargoType CON001 = CONTENEDORES (id 9), PackagingType T = CONTENEDOR (id 4).
     * Mismo criterio que LoginXmlParser. Se busca por code y no por id porque el
     * code es estable entre entornos. Si no existe, cae al default de siempre.
     */
    protected function getContainerCargoTypeId(): int
    {
        return CargoType::where('code', 'CON001')->where('active', true)->value('id')
            ?? $this->getDefaultCargoTypeId();
    }

    protected function getContainerPackagingTypeId(): int
    {
        return PackagingType::where('code', 'T')->where('active', true)->value('id')
            ?? $this->getDefaultPackagingTypeId();
    }

    protected function extractBillDate(array $data): ?string
    {
        // Ejemplo: $data['message']['prepared_at'] podría venir del EDI (UNB/DTM)
        $raw = $data['message']['prepared_at'] ?? $data['message']['date_time'] ?? null;
        if (!$raw) return null;

        // Casos típicos: “240929:33:00” (YYMMDD:hh:mm) o “2024-09-29 13:33:00”
        // Tomamos solo la fecha.
        // 1) YYMMDD...
        if (preg_match('/^(\d{2})(\d{2})(\d{2})/', $raw, $m)) {
            $year = (int)$m[1];
            $year += $year >= 70 ? 1900 : 2000; // pivot simple
            $month = $m[2];
            $day = $m[3];
            return sprintf('%04d-%02d-%02d', $year, $month, $day);
        }

        // 2) ISO-like
        $ts = strtotime($raw);
        return $ts ? date('Y-m-d', $ts) : null;
    }


    /**
     * Crear contenedores y items
     */
    protected function createContainersAndItems(BillOfLading $billOfLading, array $data): void
    {
        foreach ($data['containers'] as $containerGroup) {
            foreach ($containerGroup['items'] as $item) {
                $shipmentItem = $this->createShipmentItem($billOfLading, $item);

                foreach ($item['containers'] as $containerNumber) {
                    $this->createContainer($containerNumber, $item, $shipmentItem);
                }

                $this->stats['processed_items']++;
            }
        }
    }

    /**
     * Recupera el código NCM/HS escrito dentro del texto libre para los
     * emisores que no mandan el segmento CST.
     *
     * Formas verificadas en archivos reales:
     *   NCM: 8433.90
     *   NCM. NO.: 2918.99.99
     *   NCM CODE: 1207.99.90
     *   HS CODE: 69139099
     *   HS CODE 3902.10
     *   HS-CODE: 390120
     *   HS-CODE : 87 08 29
     *
     * Se conserva el mismo normalizador utilizado para CST/NCM.
     * No se completan dígitos inexistentes ni se fabrica una posición.
     */
    protected function extractNcmFromText(?string $text): ?string
    {
        if (empty($text)) {
            return null;
        }

        if (!preg_match(
            '/(?:NCM|HS[\s-]*CODE)[^0-9]{0,15}([0-9][0-9.\s]{3,20})/i',
            $text,
            $m
        )) {
            return null;
        }

        return $this->normalizeNcm($m[1]);
    }

    /**
     * Formato de NCM definido por Roberto (03/08/2026): 4 digitos, punto y 2
     * decimales. Si vienen mas digitos se descartan los sobrantes; si vienen
     * 4 o 5 quedan los primeros 4 sin decimales; con menos de 4 se descarta.
     *
     * Verificado contra las 7 formas del archivo real (voyage 95):
     *   1006.30 -> 1006.30    1006.30.00 -> 1006.30    12079990 -> 1207.99
     *   1207.99.90 -> 1207.99  170199 -> 1701.99   1701141020 -> 1701.14
     */
    protected function normalizeNcm(?string $raw): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $raw);

        if (strlen($digits) >= 6) {
            return substr($digits, 0, 4) . '.' . substr($digits, 4, 2);
        }

        if (strlen($digits) >= 4) {
            return substr($digits, 0, 4);
        }

        return null;
    }

    /**
     * Crear shipment item
     */
    protected function createShipmentItem(BillOfLading $billOfLading, array $itemData): ShipmentItem
{
    // Obtener siguiente número de línea
    $lineNumber = ShipmentItem::where('bill_of_lading_id', $billOfLading->id)
                              ->max('line_number') ?? 0;
    $lineNumber++;

    // SANITIZAR descripción para evitar errores de codificación
    $description = $itemData['description'] ?: 'Mercadería según manifiesto EDI';
    $cleanDescription = mb_convert_encoding($description, 'UTF-8', 'UTF-8');
    $cleanDescription = preg_replace('/[^\x20-\x7E\xC0-\xFF]/', '', $cleanDescription); // Remover caracteres problemáticos
    // 5000: el maximo real medido en archivos TFP es 2075 (ASUNCION B, 07/08/2026),
    // con parrafos legales completos que pueden crecer segun el emisor.
    $cleanDescription = mb_substr($cleanDescription, 0, 5000); // Limitar longitud

    // Un contenedor vacio no lleva mercaderia: bultos, peso y volumen en cero
    // aunque el archivo los declare (Roberto 07/08/2026). Mismo criterio que
    // createContainer para la condicion V.
    $esVacio = stripos($itemData['description'] ?? '', 'VACIO') !== false;

    // La NCM viene en el segmento CST cuando el emisor lo manda. Si no, esta
    // escrita dentro del texto libre. Se busca sobre $description (el texto
    // completo) y no sobre $cleanDescription, que ya viene truncado a 5000.
    $commodityCode = $itemData['commodity_code'] ?? null;
    if (empty($commodityCode)) {
        $commodityCode = $this->extractNcmFromText($description);
    }

    /*
     * GID/C213:
     *   0 = cantidad de bultos
     *   1 = código del tipo de embalaje
     *   2 = identificación de lista de códigos
     *   3 = agencia responsable de la lista
     *   4 = descripción textual del embalaje
     *
     * Ejemplos reales:
     *   CMSP:  820:BG:::BAG
     *   Hapag: 420:CT:ZZZ:5
     */
    $packageInfo = (string) ($itemData['package_info'] ?? '');
    $packageParts = explode(':', $packageInfo);

    $packagingCode = trim($packageParts[1] ?? '');
    $packagingCode = $packagingCode !== '' ? $packagingCode : null;

    $packageTypeDescription = trim($packageParts[4] ?? '');
    $packageTypeDescription = $packageTypeDescription !== ''
        ? $packageTypeDescription
        : null;

    return ShipmentItem::create([
        'bill_of_lading_id' => $billOfLading->id,
        'line_number' => $lineNumber,
        // Si el item trae contenedores del archivo es carga contenedorizada.
        // La lista ya viene armada cuando se llama aca (ver lineas 1106 y 1156).
        'cargo_type_id' => !empty($itemData['containers'])
            ? $this->getContainerCargoTypeId()
            : $this->getDefaultCargoTypeId(),
        'packaging_type_id' => !empty($itemData['containers'])
            ? $this->getContainerPackagingTypeId()
            : $this->getDefaultPackagingTypeId(),
        // Un contenedor vacio no lleva mercaderia: bultos y peso en cero aunque
        // el archivo los declare (Roberto 07/08/2026). La descripcion se conserva
        // para que se vea que es un reposicionamiento de vacios.
        'package_quantity' => $esVacio
            ? 0
            : $this->extractPackageCount($itemData['package_info'] ?? ''),

        // Conservar el embalaje declarado en GID/C213.
        'packaging_code' => $packagingCode,
        'package_type_description' => $packageTypeDescription,

        'item_description' => $cleanDescription,
        'cargo_marks' => $itemData['cargo_marks'] ?? null,
        // Peso tal cual viene en el archivo (MEA+AAE+G+KGM del GID).
        'gross_weight_kg' => $esVacio ? 0 : ($itemData['gross_weight_kg'] ?? 0),
        // net_weight_kg no viene en el archivo y no se transmite a ningun
        // webservice activo. Se deja en NULL ("no informado") en lugar de
        // calcular bruto - tara, que es lo que daba los negativos.
        'net_weight_kg' => null,
        'volume_m3' => $esVacio ? 0 : ($itemData['volume_m3'] ?? 0),
        // Campos DGS (mercadería peligrosa)
        'is_dangerous_goods' => $itemData['is_dangerous_goods'] ?? false,
        'imdg_class' => $itemData['imdg_class'] ?? null,
        'un_number' => $itemData['un_number'] ?? null,
        // Codigo arancelario: segmento CST, o extraido de la descripcion.
        'commodity_code' => $commodityCode,
        'created_by_user_id' => auth()->id()
    ]);
}

    /**
     * Crear contenedor
     */
    protected function createContainer(string $containerNumber, array $itemData, ShipmentItem $shipmentItem): void
    {
        if (empty($containerNumber)) {
            return;
        }

        // BUSCAR CONTENEDOR EXISTENTE PRIMERO
        $container = Container::where('container_number', $containerNumber)->first();

        if ($container) {
            // Contenedor existente: no pisar sus datos físicos globales.
            // Solo asociarlo al item de este conocimiento.
            if (!$shipmentItem->containers->contains($container->id)) {
                $shipmentItem->containers()->attach($container->id, [
                    'package_quantity' => stripos($itemData['description'] ?? '', 'VACIO') !== false
                        ? 0
                        : $this->extractPackageCount($itemData['package_info'] ?? ''),
                    'gross_weight_kg' => $this->pesoContenedor($containerNumber, $itemData),
                    'net_weight_kg' => null,
                    'volume_m3' => $itemData['volume_m3'] ?? 0,
                ]);
            }

            $this->stats['processed_containers']++;
            return;
        }

        // Datos físicos declarados para este contenedor en su bloque EQD.
        $equipment = $this->parsedData['equipment'][$containerNumber] ?? null;

        /*
         * Si el contenedor no existe todavía, necesitamos los datos físicos
         * declarados por el CUSCAR para poder crearlo.
         *
         * Hay emisores reales que informan SGP pero no envían ningún EQD
         * (CMSP.EDI histórico: 72 SGP y 0 EQD). En ese caso no corresponde
         * completar tipo, tara ni peso máximo usando valores de catálogo:
         * serían datos no declarados por el archivo.
         *
         * Un contenedor ya existente sí puede asociarse, porque sus datos
         * maestros fueron resueltos previamente y no se pisan.
         */
        if (!$equipment) {
            throw new Exception(
                "El contenedor {$containerNumber} no existe y el archivo CUSCAR "
                . "no informa datos EQD suficientes para crearlo. "
                . "Debe registrar previamente el contenedor o utilizar un archivo "
                . "que informe sus datos físicos."
            );
        }

        $containerType = null;

        if ($equipment && !empty($equipment['iso_code'])) {
            $typeCode = $this->mapIsoContainerType($equipment['iso_code']);

            if ($typeCode) {
                $containerType = ContainerType::where('code', $typeCode)
                    ->where('active', true)
                    ->first();
            }
        }

        if (!$containerType) {
            $containerType = ContainerType::where('active', true)
                ->where('is_standard', true)
                ->first();
        }

        if (!$containerType) {
            $this->stats['warnings'][] = "Tipo de contenedor no encontrado: {$containerNumber}";
            return;
        }

        $esVacio = stripos($itemData['description'] ?? '', 'VACIO') !== false;
        $condition = $esVacio ? 'V' : 'L';

        /*
         * Tara:
         * 1. valor real declarado en EQD/MEA;
         * 2. si no vino, especificación del tipo de contenedor catalogado.
         *
         * La columna containers.tare_weight_kg es obligatoria, por eso no puede
         * quedar NULL.
         */
        $tareWeight = isset($equipment['tare_weight_kg'])
            && $equipment['tare_weight_kg'] !== null
                ? (float) $equipment['tare_weight_kg']
                : (float) $containerType->tare_weight_kg;

        /*
         * VGM es el peso bruto verificado del contenedor.
         * Si no viene declarado no se reemplaza con el peso del GID, porque ese
         * peso corresponde a la mercadería y puede ser el total del conocimiento.
         */
        $vgmWeight = (float) ($equipment['vgm_weight_kg'] ?? 0);

        $currentGrossWeight = $vgmWeight > 0
            ? $vgmWeight
            : null;

        /*
         * Peso de carga del contenedor:
         *
         * - vacío => 0;
         * - con VGM => VGM - tara;
         * - un único contenedor sin VGM => puede usarse el peso de su item;
         * - varios contenedores sin VGM => no distribuir ni repetir el total:
         *   queda NULL.
         */
        $physicalOccurrences = (int) ($itemData['_physical_occurrences'] ?? 1);

        if ($esVacio) {
            $cargoWeight = 0;
        } elseif ($vgmWeight > 0) {
            $cargoWeight = max(0.0, $vgmWeight - $tareWeight);
        } elseif ($physicalOccurrences <= 1) {
            $cargoWeight = isset($itemData['gross_weight_kg'])
                ? (float) $itemData['gross_weight_kg']
                : null;
        } else {
            $cargoWeight = null;
        }

        $container = Container::create([
            'container_number' => $containerNumber,
            'container_type_id' => $containerType->id,
            'condition' => $condition,

            // Precintos clasificados según el emisor declarado en SEL.
            'shipper_seal' => $equipment['shipper_seal'] ?? null,
            'customs_seal' => $equipment['customs_seal'] ?? null,
            'carrier_seal' => $equipment['carrier_seal'] ?? null,
            'additional_seals' => !empty($equipment['additional_seals'])
                ? json_encode($equipment['additional_seals'], JSON_UNESCAPED_UNICODE)
                : null,

            // Temperatura únicamente cuando TMP+2 la declara expresamente.
            'temperature_controlled' => $equipment['transport_temperature_c'] !== null,
            'set_temperature' => $equipment['transport_temperature_c'],

            'tare_weight_kg' => $tareWeight,
            'max_gross_weight_kg' => $containerType->max_gross_weight_kg,
            'current_gross_weight_kg' => $currentGrossWeight,
            'cargo_weight_kg' => $cargoWeight,
            'operational_status' => $esVacio ? 'empty' : 'loaded',
            'active' => true,
            'created_by_user_id' => auth()->id(),
        ]);

        // Datos de la relación entre mercadería y contenedor.
        $pesoContenedor = $this->pesoContenedor($containerNumber, $itemData);

        $shipmentItem->containers()->attach($container->id, [
            'package_quantity' => $esVacio
                ? 0
                : $this->extractPackageCount($itemData['package_info'] ?? ''),
            'gross_weight_kg' => $pesoContenedor,
            'net_weight_kg' => $esVacio
                ? 0
                : max(0.0, $pesoContenedor - $tareWeight),
            'volume_m3' => $itemData['volume_m3'] ?? 0,
        ]);

        $this->stats['processed_containers']++;
    }

    /**
     * Mapear codigo ISO 6346 del EQD al code de container_types.
     *
     * En ISO 6346 el primer caracter es el largo (2 = 20 pies, 4 = 40 pies) y el
     * segundo la altura (2 = estandar, 5 = high cube). El archivo de Roberto trae
     * solo 45G1 (138 veces) y 22G1 (40); el resto se incluye por equivalencia con
     * los tipos ya cargados en container_types. Codigo desconocido -> null, y el
     * llamador cae al tipo estandar por defecto.
     */
    protected function mapIsoContainerType(string $isoCode): ?string
    {
        $map = [
            '22G1' => '20GP',
            '42G1' => '40GP',
            '45G1' => '40HC',
            '22R1' => '20RF',
            '45R1' => '40RH',
            '22T1' => '20TN',
            '22U1' => '20OT',
        ];

        return $map[strtoupper(trim($isoCode))] ?? null;
    }

    /**
     * Resolver puerto por código. NUNCA auto-crea puertos (política del proyecto:
     * el catálogo tiene ~17.500; un código desconocido debe dar error claro).
     * Los generadores CMSP/EDI usan códigos propios que no son UN/LOCODE; se mapean
     * con aliases verificados contra el catálogo real:
     *   ARBAI ("BUENOS AIRES") -> ARBUE | PYTV ("TERPORT-VILLETA") -> PYTVT
     *   PYSEF ("PUERTO SEGURO FLUVIAL") -> PYPSE (id 17599, Villeta, country_id 174)
     */
    protected function findOrCreatePort(string $portCode): Port
    {
        $code = strtoupper(trim($portCode));

        if ($code === '') {
            throw new Exception('Código de puerto vacío');
        }

        // Códigos propios de los generadores (no son UN/LOCODE). Mapa compartido con
        // TfpTextParser: ambos formatos usan la misma codificación de la casa.
        // Verificados contra archivos reales (TFP 13/07/2026, CMSP 30/07/2026).
        $aliases = [
            'ARBAI' => 'ARBUE',   // "BUENOS AIRES"
            'PYTV'  => 'PYTVT',   // "TERPORT-VILLETA"
            'PYSEF' => 'PYPSE',   // "PUERTO SEGURO FLUVIAL"
        'PYNNV' => 'PYVLL',   // "ANNP VILLETA" = puerto publico de Villeta (verificado 11/08/2026 contra BM ROSA V.468)
        ];
        $resolved = $aliases[$code] ?? $code;

        $port = Port::where('code', $resolved)->first();

        if ($port) {
            if ($resolved !== $code) {
                Log::info('CMSP: puerto mapeado por alias', [
                    'codigo_archivo'  => $code,
                    'codigo_resuelto' => $resolved,
                    'port_id'         => $port->id,
                ]);
            }
            return $port;
        }

        throw new Exception(
            "Puerto desconocido en el archivo EDI: '{$code}'. " .
            "No existe en el catálogo de puertos y no se crean puertos automáticamente. " .
            "Verifique el código o solicite el alta del puerto al administrador."
        );
    }

    /**
     * Buscar o crear cliente
     */
    protected function extractExplicitTaxTypeFromText(
        ?string $text,
        ?string $expectedTaxId = null
    ): ?string {
        $text = trim((string) $text);

        if ($text === '') {
            return null;
        }

        $patterns = [
            'CUIT' => '/\bCUIT\b\s*(?:(?:NBR|NRO|Nº|N°)\.?\s*)?[:#?.-]*\s*([0-9][0-9.\/-]{5,20})/iu',
            'CNPJ' => '/\bCNPJ\b\s*[:#?.-]*\s*([0-9][0-9.\/-]{5,20})/iu',
            'RUC' => '/\bR\.?\s*U\.?\s*C\.?\b\s*[:#?.-]*\s*([0-9][0-9.\/-]{5,20})/iu',
            'NIT' => '/\bNIT\b\s*[:#?.-]*\s*([0-9][0-9.\/-]{5,20})/iu',
        ];

        foreach ($patterns as $taxType => $pattern) {
            if (!preg_match($pattern, $text, $matches)) {
                continue;
            }

            $taxId = preg_replace('/\D/', '', $matches[1]);

            if (
                $taxId === ''
                || preg_match('/^0+$/', $taxId)
                || !$this->isTaxIdCompatibleWithType($taxId, $taxType)
            ) {
                throw new \DomainException(
                    "CMSP: identificador {$taxType} con formato incompatible."
                );
            }

            if ($expectedTaxId !== null) {
                $expected = preg_replace('/\D/', '', $expectedTaxId);

                if ($expected !== '' && $expected !== $taxId) {
                    throw new \DomainException(
                        "CMSP: el identificador {$taxType} escrito en NAD "
                        . 'no coincide con el identificador fiscal resuelto.'
                    );
                }
            }

            return $taxType;
        }

        return null;
    }

    protected function isTaxIdCompatibleWithType(
        string $taxId,
        string $taxType
    ): bool {
        $length = strlen($taxId);

        return match ($taxType) {
            'CUIT' => $length === 11,
            'CNPJ' => $length === 14,
            'RUC' => $length >= 7 && $length <= 9,
            'NIT' => $length >= 9 && $length <= 10,
            default => false,
        };
    }

    /**
     * @return array{tax_id:?string,tax_type:?string}
     */
    protected function resolvePartyTaxIdentity(array $partyData): array
    {
        $taxId = $this->resolveTaxId(
            $partyData['tax_id'] ?? null,
            $partyData['name'] ?? null,
            $partyData['address'] ?? null
        );

        $taxType = trim(
            strtoupper((string) ($partyData['tax_type'] ?? ''))
        );

        $taxType = $taxType !== ''
            ? $taxType
            : null;

        if ($taxType === null && $taxId !== null) {
            $taxType = $this->extractExplicitTaxTypeFromText(
                ($partyData['name'] ?? '')
                . ' '
                . ($partyData['address'] ?? ''),
                $taxId
            );
        }

        if (
            $taxId !== null
            && $taxType !== null
            && !$this->isTaxIdCompatibleWithType($taxId, $taxType)
        ) {
            throw new \DomainException(
                "CMSP: identificador {$taxType} con formato incompatible."
            );
        }

        return [
            'tax_id' => $taxId,
            'tax_type' => $taxType,
        ];
    }

    protected function countryAlpha2ForTaxType(?string $taxType): ?string
    {
        return match ($taxType) {
            'CUIT' => 'AR',
            'RUC' => 'PY',
            'CNPJ' => 'BR',
            'NIT' => 'CO',
            default => null,
        };
    }

    protected function countryAlpha2FromPartyText(?string $text): ?string
    {
        $text = mb_strtoupper(trim((string) $text));

        if ($text === '') {
            return null;
        }

        $patterns = [
            'AR' => '/\bARGENTINA\b/u',
            'PY' => '/\bPARAGUAY\b/u',
            'UY' => '/\bURUGUAY\b/u',
            'BR' => '/\b(?:BRASIL|BRAZIL)\b/u',
            'CO' => '/\bCOLOMBIA\b/u',
        ];

        foreach ($patterns as $alpha2 => $pattern) {
            if (preg_match($pattern, $text)) {
                return $alpha2;
            }
        }

        return null;
    }

    protected function countryIdForAlpha2(string $alpha2): int
    {
        $countryId = Country::query()
            ->where('alpha2_code', strtoupper($alpha2))
            ->value('id');

        if (!$countryId) {
            throw new \DomainException(
                "CMSP: no existe el país {$alpha2} en el catálogo."
            );
        }

        return (int) $countryId;
    }

    protected function contextualCountryIdForParty(array $partyData): int
    {
        $role = $partyData['type'] ?? null;

        $portCode = match ($role) {
            'shipper' => $this->parsedData['ports']['loading'] ?? null,
            'consignee', 'notify' => $this->parsedData['ports']['discharge'] ?? null,
            default => null,
        };

        if (!$portCode) {
            throw new \DomainException(
                'CMSP: no existe contexto suficiente para resolver el país de la parte.'
            );
        }

        return (int) $this->findOrCreatePort(
            $portCode
        )->country_id;
    }

    protected function resolveClientCountryId(
        array $partyData,
        ?string $taxType
    ): int {
        $textAlpha2 = $this->countryAlpha2FromPartyText(
            ($partyData['name'] ?? '')
            . ' '
            . ($partyData['address'] ?? '')
        );

        $taxAlpha2 = $this->countryAlpha2ForTaxType($taxType);

        if (
            $textAlpha2 !== null
            && $taxAlpha2 !== null
            && $textAlpha2 !== $taxAlpha2
        ) {
            throw new \DomainException(
                "CMSP: el país declarado {$textAlpha2} "
                . "es incompatible con el tipo fiscal {$taxType}."
            );
        }

        if ($textAlpha2 !== null) {
            return $this->countryIdForAlpha2($textAlpha2);
        }

        if ($taxAlpha2 !== null) {
            return $this->countryIdForAlpha2($taxAlpha2);
        }

        return $this->contextualCountryIdForParty($partyData);
    }

    /**
     * Buscar o crear cliente sin fabricar jurisdicción ni tipo documental.
     */
    protected function findOrCreateClient(?array $partyData): ?Client
    {
        if (!$partyData || empty($partyData['name'])) {
            return null;
        }

        $user = auth()->user();

        $companyId = $user->company_id
            ?? (
                $user->userable_type === 'App\Models\Company'
                    ? $user->userable_id
                    : null
            );

        if (!$companyId) {
            throw new Exception(
                "Usuario no tiene empresa asignada. User ID: {$user->id}"
            );
        }

        $identity = $this->resolvePartyTaxIdentity(
            $partyData
        );

        $taxId = $identity['tax_id'];
        $taxType = $identity['tax_type'];

        $countryId = $this->resolveClientCountryId(
            $partyData,
            $taxType
        );

        // Con identificador fiscal la identidad es tax_id + country_id.
        // Nunca degradar luego a una coincidencia sólo por nombre.
        if ($taxId !== null) {
            $client = Client::query()
                ->where('tax_id', $taxId)
                ->where('country_id', $countryId)
                ->first();

            if ($client) {
                $this->persistClientAddress(
                    $client,
                    $partyData['address'] ?? null
                );

                return $client;
            }
        } else {
            // Sin identificador fiscal sólo reutilizar otra ficha también
            // sin tax_id, mismo país y mismo nombre.
            $name = trim((string) $partyData['name']);

            $client = Client::query()
                ->whereNull('tax_id')
                ->where('country_id', $countryId)
                ->where(function ($query) use ($name) {
                    $normalizedName = mb_strtoupper($name);

                    $query
                        ->whereRaw(
                            'UPPER(TRIM(legal_name)) = ?',
                            [$normalizedName]
                        )
                        ->orWhereRaw(
                            'UPPER(TRIM(commercial_name)) = ?',
                            [$normalizedName]
                        );
                })
                ->first();

            if ($client) {
                $this->persistClientAddress(
                    $client,
                    $partyData['address'] ?? null
                );

                return $client;
            }
        }

        $documentTypeId = null;

        if ($taxId !== null && $taxType !== null) {
            $documentTypeId = DocumentType::query()
                ->where('code', $taxType)
                ->where('country_id', $countryId)
                ->where('active', true)
                ->value('id');

            if (!$documentTypeId) {
                throw new \DomainException(
                    "CMSP: no existe un tipo documental {$taxType} "
                    . 'activo y compatible con el país resuelto.'
                );
            }
        }

        $client = Client::create([
            'created_by_company_id' => $companyId,
            'legal_name' => $partyData['name'],
            'commercial_name' => $partyData['name'],
            'tax_id' => $taxId,
            'country_id' => $countryId,
            'document_type_id' => $documentTypeId,
        ]);

        Log::info('CMSP: cliente creado automáticamente', [
            'name' => $partyData['name'],
            'client_id' => $client->id,
            'tax_id' => $taxId,
            'country_id' => $countryId,
            'document_type_id' => $documentTypeId,
        ]);

        $this->persistClientAddress(
            $client,
            $partyData['address'] ?? null
        );

        return $client;
    }

    /**
     * Extraer cantidad de paquetes
     */
    protected function extractPackageCount(string $packageInfo): int
    {
        if (preg_match('/^(\d+):/', $packageInfo, $matches)) {
            return (int) $matches[1];
        }
        return 1;
    }

    /**
     * Obtener contenedores creados
     */
    protected function getCreatedContainers(array $data): array
    {
        $containerNumbers = [];

        foreach ($data['containers'] as $containerGroup) {
            foreach ($containerGroup['items'] as $item) {
                $containerNumbers = array_merge($containerNumbers, $item['containers']);
            }
        }

        return Container::whereIn('container_number', $containerNumbers)->get()->toArray();
    }

    /**
     * Validar datos parseados
     */
    public function validate(array $data): array
    {
        $errors = [];
if (empty($data['ports']['loading']) || empty($data['ports']['discharge'])) {
            $errors[] = 'Información de puertos incompleta';
        }

        if (empty($data['containers'])) {
            $errors[] = 'No se encontraron contenedores en el archivo';
        }

        foreach ($data['containers'] as $containerGroup) {
            if (empty($containerGroup['items'])) {
                $errors[] = 'Grupo de contenedores sin items';
                continue;
            }

            foreach ($containerGroup['items'] as $item) {
                if (empty($item['containers'])) {
                    $errors[] = 'Item sin contenedores asociados';
                }
            }
        }

        return $errors;
    }

    /**
     * Transformar datos a formato estándar
     */
    public function transform(array $data): array
    {
        return $data;
    }

    /**
     * Obtener información del formato soportado
     */
    public function getFormatInfo(): array
    {
        return [
            'name' => 'CMSP EDI CUSCAR',
            'description' => 'Archivo EDI CUSCAR UN/EDIFACT D.96B de CMSP Paraguay',
            'extensions' => ['edi'],
            'version' => 'D.96B',
            'parser_class' => self::class,
            'capabilities' => [
                'multiple_containers' => true,
                'weight_measurements' => true,
                'volume_measurements' => true,
                'party_information' => true,
                'transport_details' => true,
                'location_codes' => true,
                'booking_references' => true,
                'empty_container_handling' => true,
                'dangerous_goods' => true
            ]
        ];
    }

    /**
     * Obtener configuración por defecto del parser
     */
    public function getDefaultConfig(): array
    {
        return [
            'encoding' => 'UTF-8',
            'segment_separator' => "'",
            'element_separator' => '+',
            'component_separator' => ':',
            'escape_character' => '?',
            'validate_containers' => true,
            'validate_weights' => true,
            'create_missing_ports' => true,
            'create_missing_clients' => true,
            'default_tare_weight' => 2200,
            'default_max_gross_weight' => 30000
        ];
    }

    protected function extractLoadingDate(array $data): ?string
    {
        // DTM de carga, fecha de “on-board”, o fecha de retiro en origen si existiera
        $raw = $data['bl']['loading_date']
            ?? $data['voyage']['etd']           // Estimated Time of Departure
            ?? $data['shipment']['loading_date']
            ?? null;

        return $this->normalizeAnyDate($raw);
    }

    protected function extractDischargeDate(array $data): ?string
    {
        $raw = $data['bl']['discharge_date']
            ?? $data['voyage']['eta']           // Estimated Time of Arrival
            ?? $data['shipment']['discharge_date']
            ?? null;

        return $this->normalizeAnyDate($raw);
    }

    protected function normalizeAnyDate(?string $raw): ?string
    {
        if (!$raw) return null;

        // Formato YYMMDD (p.ej. 250826) o YYMMDD:hh:mm (p.ej. 250826:13:33)
        if (preg_match('/^(\d{2})(\d{2})(\d{2})/', $raw, $m)) {
            $y = (int)$m[1]; $y += $y >= 70 ? 1900 : 2000;
            return sprintf('%04d-%02d-%02d', $y, $m[2], $m[3]);
        }

        // ISO-like o cualquier cosa que strtotime entienda
        $ts = strtotime($raw);
        return $ts ? date('Y-m-d', $ts) : null;
    }

    /**
     * Convertir un valor numerico EDI a float tolerando ambos separadores.
     *
     * El archivo mezcla los dos formatos dentro del mismo conocimiento: en
     * TER2BUE26P102900 el AAX viene "92408.75" y los items "92408,75". Ni el cast
     * directo ni toFloat() sirven: (float)"92408,75" da 92408 y pierde decimales,
     * y toFloat() trata el punto como separador de miles y convertiria
     * "222750.00" en 22275000.
     */
    protected function normalizeDecimal(?string $value): float
    {
        if ($value === null || trim($value) === '') {
            return 0.0;
        }

        $v = trim($value);
        $hasComma = strpos($v, ',') !== false;
        $hasDot   = strpos($v, '.') !== false;

        if ($hasComma && $hasDot) {
            // Formato europeo: punto miles, coma decimal.
            $v = str_replace(['.', ','], ['', '.'], $v);
        } elseif ($hasComma) {
            $v = str_replace(',', '.', $v);
        }

        return (float) $v;
    }

    protected function toFloat($v): float
    {
        if ($v === null || $v === '') return 0.0;
        // quita espacios y miles
        $s = trim((string)$v);
        $s = str_replace(['.', ' '], ['', ''], $s); // 25.370,05 -> 25370,05
        $s = str_replace([','], ['.'], $s);         // 25370,05 -> 25370.05
        return (float)$s;
    }

    

    protected function createSingleItemFromHeader(BillOfLading $bl, array $header): ShipmentItem
{
    $lineNumber = $this->nextLineNumber($bl);  // Agregar esta línea

    $desc   = $header['cargo_description'] ?? 'Según detalle';
    $gross  = $this->toFloat($header['gross_weight'] ?? 0);
    $net    = $this->toFloat($header['net_weight']   ?? 0);
    $volume = $this->toFloat($header['measurement']  ?? 0);

    // toma cantidad desde el header si existe; si no, 0
    $qty = (int)($header['packages'] ?? $header['package_quantity'] ?? 0);

    return ShipmentItem::create([
        'bill_of_lading_id'   => $bl->id,
        'sequence_number'         => $lineNumber,
        'description'    => mb_substr($desc, 0, 5000),
        'cargo_type_id'       => $bl->primary_cargo_type_id ?? 1,
        'packaging_type_id'   => $bl->primary_packaging_type_id ?? 1,
        'package_quantity'    => max(0, $qty),       // ← CLAVE PARA TU ERROR
        'gross_weight_kg'     => max(0.0, $gross),
        'net_weight_kg'       => max(0.0, $net),
        'volume_m3'           => max(0.0, $volume),
        'created_by_user_id'  => auth()->id(),
    ]);
}


    protected function createItems(BillOfLading $bl, array $lines, array $header): void
{
    if (empty($lines)) {
        $this->createSingleItemFromHeader($bl, $header);
        return;
    }

    foreach ($lines as $i => $row) {
        $lineNumber = $this->nextLineNumber($bl);  // Agregar esta línea

        $desc   = $row['description'] ?? ($header['cargo_description'] ?? 'Según detalle');
        $gross  = $this->toFloat($row['gross_weight'] ?? 0);
        $net    = $this->toFloat($row['net_weight']   ?? 0);
        $volume = $this->toFloat($row['measurement']  ?? $row['cbm'] ?? 0);

        // soporta ambos nombres; usa el que exista
        $qty = (int)($row['packages'] ?? $row['package_quantity'] ?? 0);

        ShipmentItem::create([
            'bill_of_lading_id' => $bl->id,
            'sequence_number'   => $lineNumber,
            'description'       => mb_substr($desc, 0, 5000),
            'cargo_type_id'     => $bl->primary_cargo_type_id ?? 1,
            'packaging_type_id' => $bl->primary_packaging_type_id ?? 1,
            'package_count'     => max(0, $qty),     // ← CLAVE
            'gross_weight_kg'   => max(0.0, $gross),
            'net_weight_kg'     => max(0.0, $net),
            'volume_m3'         => max(0.0, $volume),
            'created_by_user_id' => auth()->id(),
        ]);
    }

    
}

protected function nextLineNumber(BillOfLading $bl): int
{
    return (int) ($bl->shipmentItems()->max('line_number') ?? 0) + 1;}

}