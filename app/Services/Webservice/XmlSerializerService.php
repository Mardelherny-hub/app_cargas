<?php

namespace App\Services\Webservice;

use App\Models\Company;
use App\Models\Voyage;
use App\Models\Shipment;
use App\Models\Container;
use App\Models\Vessel;
use App\Models\Captain;
use App\Models\WebserviceLog;
use Exception;
use DOMDocument;
use DOMElement;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * MÓDULO 4: WEBSERVICES ADUANA - XmlSerializerService
 *
 * Servicio especializado para serialización de datos del sistema a XML
 * para webservices aduaneros Argentina y Paraguay.
 * 
 * Genera XML específico para:
 * - MIC/DTA Argentina (AFIP)
 * - Información Anticipada Argentina
 * - Manifiestos Paraguay (DNA)
 * 
 * Mapea datos reales del sistema:
 * - PARANA.csv: MAERSK LINE ARGENTINA S.A, PAR13001, V022NB
 * - Contenedores: 40HC, 20GP, múltiples tipos
 * - Rutas: ARBUE → PYTVT (Buenos Aires → Paraguay Terminal Villeta)
 * 
 * Integra con:
 * - CertificateManagerService para firma XML
 * - Modelos existentes: Company, Voyage, Shipment, Vessel, Container
 * - Sistema de logs del módulo webservices
 */
class XmlSerializerService
{
    private Company $company;
    private array $config;
    private ?DOMDocument $dom = null;

    /**
     * Configuración XML por defecto
     */
    private const DEFAULT_CONFIG = [
        'encoding' => 'UTF-8',
        'version' => '1.0',
        'format_output' => true,
        'preserve_white_space' => false,
        'validate_structure' => true,
        'include_soap_envelope' => true,
        'soap_version' => '1_2',
        'namespace_prefixes' => [
            'soap' => 'http://schemas.xmlsoap.org/soap/envelope/',
            'xsi' => 'http://www.w3.org/2001/XMLSchema-instance',
            'xsd' => 'http://www.w3.org/2001/XMLSchema',
        ],
    ];

    /**
     * Mapeo de códigos para Argentina AFIP
     */
    private const ARGENTINA_CODES = [
        'via_transporte' => 8, // Hidrovía según EDIFACT 8067
        'pais_argentina' => 'AR',
        'tipo_agente' => 'ATA', // Agente de Transporte Aduanero
        'rol_empresa' => 'TRANSPORTISTA',
        'tipo_embarcacion' => [
            'barge' => 'BAR', // Barcaza
            'tugboat' => 'EMP', // Empujador/Remolcador
            'self_propelled' => 'BUM', // Buque Motor
        ],
        'condicion_contenedor' => [
            'house' => 'H', // a casa - house
            'pier' => 'P', // a puerto - pier  
            'empty' => 'V', // vacío
            'mail' => 'C', // correo
        ],
        'indicador_lastre' => [
            true => 'S',
            false => 'N',
        ],
    ];

    public function __construct(Company $company, array $config = [])
    {
        $this->company = $company;
        $this->config = array_merge(self::DEFAULT_CONFIG, $config);
        
        $this->logOperation('info', 'XmlSerializerService inicializado', [
            'company_id' => $company->id,
            'company_name' => $company->legal_name,
            'config' => $config,
        ]);
    }

    /**
     * Crear XML para MIC/DTA Argentina
     */
    public function createMicDtaXml(Shipment $shipment, string $transactionId): ?string
{
    try {
        $this->logOperation('info', 'Iniciando creación XML MIC/DTA', [
            'shipment_id' => $shipment->id,
            'transaction_id' => $transactionId,
            'shipment_number' => $shipment->shipment_number,
        ], 'xml_generation'); // ✅ CATEGORY AGREGADA

        // Validar precondiciones
        $validation = $this->validateShipmentForMicDta($shipment);
        if (!$validation['is_valid']) {
            $this->logOperation('error', 'Shipment no válido para MIC/DTA', [
                'errors' => $validation['errors'],
            ], 'xml_validation'); // ✅ CATEGORY AGREGADA
            return null;
        }

        // Inicializar DOM
        $this->initializeDom();

        // Crear estructura SOAP
        $envelope = $this->createSoapEnvelope();
        $body = $this->createElement('soap:Body');
        $envelope->appendChild($body);

        // ✅ NAMESPACE CORREGIDO - URI ABSOLUTA VÁLIDA
        $registrarMicDta = $this->createElement('RegistrarMicDta');
        $registrarMicDta->setAttribute('xmlns', 'http://schemas.afip.gob.ar/wgesregsintia2/v1');
        $body->appendChild($registrarMicDta);

        // Agregar autenticación de empresa
        $autenticacion = $this->createAutenticacionEmpresa($registrarMicDta);
        
        // Agregar parámetros del MIC/DTA
        $parametros = $this->createMicDtaParam($registrarMicDta, $shipment, $transactionId);

        // Generar XML string
        $xmlString = $this->dom->saveXML();

        // ✅ VALIDAR XML ANTES DE RETORNAR
        $validation = $this->validateXmlStructure($xmlString);
        if (!$validation['is_valid']) {
            $this->logOperation('error', 'Error en validación XML', [
                'errors' => $validation['errors'],
            ], 'xml_validation'); // ✅ CATEGORY AGREGADA
            throw new Exception('XML generado no válido: ' . implode(', ', $validation['errors']));
        }
        
        $this->logOperation('info', 'XML MIC/DTA creado exitosamente', [
            'xml_length' => strlen($xmlString),
            'transaction_id' => $transactionId,
        ], 'xml_generation'); // ✅ CATEGORY AGREGADA

        return $xmlString;

    } catch (Exception $e) {
        $this->logOperation('error', 'Error creando XML MIC/DTA', [
            'error' => $e->getMessage(),
            'shipment_id' => $shipment->id ?? 'N/A',
            'transaction_id' => $transactionId,
        ], 'xml_error'); // ✅ CATEGORY AGREGADA
        return null;
    }
}

    /**
     * Validar shipment para MIC/DTA
     */
    private function validateShipmentForMicDta(Shipment $shipment): array
    {
        $validation = [
            'is_valid' => false,
            'errors' => [],
            'warnings' => [],
        ];

        // Validar que existe voyage relacionado
        if (!$shipment->voyage) {
            $validation['errors'][] = 'Shipment debe tener un viaje relacionado';
        }

        // Validar que existe vessel relacionado
        if (!$shipment->vessel) {
            $validation['errors'][] = 'Shipment debe tener una embarcación relacionada';
        }

        // Validar que existe company en el voyage
        if (!$shipment->voyage?->company) {
            $validation['errors'][] = 'Viaje debe tener una empresa relacionada';
        }

        // Validar que la empresa tiene configuración de webservices
        $company = $shipment->voyage?->company;
        if ($company && !$company->ws_active) {
            $validation['errors'][] = 'Empresa no tiene webservices activos';
        }

        // Validar que tiene captain si es requerido
        if (!$shipment->captain_id && $shipment->vessel?->vesselType?->requires_pilot) {
            $validation['warnings'][] = 'Embarcación requiere capitán/piloto';
        }

        // Validar que tiene contenedores si maneja contenedores
        if ($shipment->vessel?->vesselType?->handles_containers && $shipment->containers_loaded == 0) {
            $validation['warnings'][] = 'Embarcación configurada para contenedores pero no tiene carga';
        }

        $validation['is_valid'] = empty($validation['errors']);

        return $validation;
    }

    /**
     * Inicializar documento DOM
     */
    private function initializeDom(): void
    {
        $this->dom = new DOMDocument($this->config['version'], $this->config['encoding']);
        $this->dom->formatOutput = $this->config['format_output'];
        $this->dom->preserveWhiteSpace = $this->config['preserve_white_space'];
    }

    /**
     * Crear envelope SOAP
     */
    private function createSoapEnvelope(): DOMElement
    {
        $envelope = $this->createElement('soap:Envelope');
        
        // Agregar namespaces
        foreach ($this->config['namespace_prefixes'] as $prefix => $uri) {
            $envelope->setAttribute("xmlns:$prefix", $uri);
        }
        
        $this->dom->appendChild($envelope);
        return $envelope;
    }

    /**
     * Crear elemento con namespace awareness
     */
    private function createElement(string $name, string $value = null): DOMElement
    {
        if ($value !== null) {
            return $this->dom->createElement($name, htmlspecialchars($value, ENT_XML1, 'UTF-8'));
        }
        return $this->dom->createElement($name);
    }

    /**
     * Inicializar documento DOM - ALIAS PARA CONSISTENCIA
     */
    private function initializeDomDocument(): void
    {
        $this->initializeDom();
    }

    /**
     * Crear autenticación de empresa
     */
    private function createAutenticacionEmpresa(DOMElement $parent): DOMElement
    {
        $autenticacion = $this->createElement('argWSAutenticacionEmpresa');
        $parent->appendChild($autenticacion);

        // CUIT de empresa conectada (sin guiones)
        $cuit = $this->createElement('CuitEmpresaConectada', $this->cleanTaxId($this->company->tax_id));
        $autenticacion->appendChild($cuit);

        // Tipo de agente
        $tipoAgente = $this->createElement('TipoAgente', self::ARGENTINA_CODES['tipo_agente']);
        $autenticacion->appendChild($tipoAgente);

        // Rol de la empresa
        $rol = $this->createElement('Rol', self::ARGENTINA_CODES['rol_empresa']);
        $autenticacion->appendChild($rol);

        return $autenticacion;
    }

    /**
     * Crear parámetros del MIC/DTA
     */
    private function createMicDtaParam(DOMElement $parent, Shipment $shipment, string $transactionId): DOMElement
    {
        $parametros = $this->createElement('argRegistrarMicDtaParam');
        $parent->appendChild($parametros);

        // ID de transacción
        $idTransaccion = $this->createElement('idTransaccion', $transactionId);
        $parametros->appendChild($idTransaccion);

        // Crear elemento micDta principal
        $micDta = $this->createElement('micDta');
        $parametros->appendChild($micDta);

        // Código vía de transporte (8 = Hidrovía)
        $codViaTrans = $this->createElement('codViaTrans', (string)self::ARGENTINA_CODES['via_transporte']);
        $micDta->appendChild($codViaTrans);

        // Datos del transportista
        $this->addTransportistaData($micDta, $shipment);

        // Datos del propietario del vehículo
        $this->addPropietarioVehiculoData($micDta, $shipment);

        // Indicador en lastre
        $this->addIndicadorLastre($micDta, $shipment);

        // Datos del vehículo/embarcación
        $this->addVehiculoData($micDta, $shipment);

        // Conductores (capitanes)
        $this->addConductoresData($micDta, $shipment);

        // Contenedores con carga
        $this->addContenedoresConCarga($micDta, $shipment);

        // Datos de embarcación
        $this->addEmbarcacionData($micDta, $shipment);

        return $parametros;
    }

    /**
     * Agregar datos del transportista
     */
    private function addTransportistaData(DOMElement $parent, Shipment $shipment): void
    {
        $company = $shipment->voyage->company;
        
        $transportista = $this->createElement('transportista');
        $parent->appendChild($transportista);

        // Nombre de la empresa transportista
        $nombre = $this->createElement('nombre', $company->legal_name);
        $transportista->appendChild($nombre);

        // Domicilio (nullable según especificación)
        $domicilio = $this->createElement('domicilio');
        $domicilio->setAttribute('xsi:nil', 'true');
        $transportista->appendChild($domicilio);

        // Código de país
        $codPais = $this->createElement('codPais', $company->country);
        $transportista->appendChild($codPais);

        // ID Fiscal (CUIT limpio)
        $idFiscal = $this->createElement('idFiscal', $this->cleanTaxId($company->tax_id));
        $transportista->appendChild($idFiscal);

        // Tipo de transportista
        $tipTrans = $this->createElement('tipTrans', 'EMPRESA');
        $transportista->appendChild($tipTrans);
    }

    /**
     * Agregar datos del propietario del vehículo
     */
    private function addPropietarioVehiculoData(DOMElement $parent, Shipment $shipment): void
    {
        // Por defecto, el propietario es la misma empresa transportista
        $company = $shipment->voyage->company;
        
        $propVehiculo = $this->createElement('propVehiculo');
        $parent->appendChild($propVehiculo);

        // Nombre del propietario
        $nombre = $this->createElement('nombre', $company->legal_name);
        $propVehiculo->appendChild($nombre);

        // Domicilio
        $domicilio = $this->createElement('domicilio');
        $domicilio->setAttribute('xsi:nil', 'true');
        $propVehiculo->appendChild($domicilio);

        // Código de país
        $codPais = $this->createElement('codPais', $company->country);
        $propVehiculo->appendChild($codPais);

        // ID Fiscal
        $idFiscal = $this->createElement('idFiscal', $this->cleanTaxId($company->tax_id));
        $propVehiculo->appendChild($idFiscal);
    }

    /**
     * Agregar indicador en lastre
     */
    private function addIndicadorLastre(DOMElement $parent, Shipment $shipment): void
    {
        // Determinar si va en lastre basado en la carga
        $enLastre = $shipment->cargo_weight_loaded == 0 || $shipment->containers_loaded == 0;
        
        $indicador = self::ARGENTINA_CODES['indicador_lastre'][$enLastre];
        $indEnLastre = $this->createElement('indEnLastre', $indicador);
        $parent->appendChild($indEnLastre);
    }

    /**
     * Agregar datos del vehículo
     */
    private function addVehiculoData(DOMElement $parent, Shipment $shipment): void
    {
        $vessel = $shipment->vessel;
        $company = $shipment->voyage->company;
        
        $vehiculo = $this->createElement('vehiculo');
        $parent->appendChild($vehiculo);

        // Código de país del vehículo
        $codPais = $this->createElement('codPais', $company->country);
        $vehiculo->appendChild($codPais);

        // Patente/matrícula de la embarcación
        $patente = $this->createElement('patente', $vessel->registry_number ?? $vessel->name);
        $vehiculo->appendChild($patente);

        // Patentes de remolque (null para embarcaciones fluviales)
        $patentesRemol = $this->createElement('patentesRemol');
        $patentesRemol->setAttribute('xsi:nil', 'true');
        $vehiculo->appendChild($patentesRemol);

        // Marca (usar owner como marca)
        $marca = $this->createElement('marca', $vessel->owner ?? 'PROPIETARIO');
        $vehiculo->appendChild($marca);

        // Número de chasis (usar IMO o nombre como identificador)
        $nroChasis = $this->createElement('nroChasis', $vessel->imo_number ?? $vessel->name);
        $vehiculo->appendChild($nroChasis);

        // Modelo de chasis (usar tipo de embarcación)
        $modChasis = $this->createElement('modChasis', $vessel->vesselType?->name ?? 'EMBARCACION');
        $vehiculo->appendChild($modChasis);

        // Año de fabricación
        $anioFab = $this->createElement('anioFab', (string)($vessel->year_built ?? date('Y')));
        $vehiculo->appendChild($anioFab);

        // Capacidad de tracción (usar capacidad de carga en toneladas)
        $capTraccion = $this->createElement('capTraccion', (string)intval($vessel->cargo_capacity ?? 1000));
        $vehiculo->appendChild($capTraccion);

        // Accesorio tipo y número
        $acceTipNum = $this->createElement('acceTipNum', 'CONTENEDOR');
        $vehiculo->appendChild($acceTipNum);

        // Precintos del vehículo
        $precintos = $this->createElement('precintos');
        $precintos->setAttribute('xsi:nil', 'true');
        $vehiculo->appendChild($precintos);
    }

    /**
     * Agregar datos de conductores
     */
    private function addConductoresData(DOMElement $parent, Shipment $shipment): void
    {
        $conductores = $this->createElement('conductores');
        $parent->appendChild($conductores);

        // Si hay capitán asignado, agregarlo
        if ($shipment->captain) {
            $this->addConductorData($conductores, $shipment->captain);
        } else {
            // Agregar conductor nulo según especificación
            $conductor = $this->createElement('Conductor');
            $conductor->setAttribute('xsi:nil', 'true');
            $conductores->appendChild($conductor);
        }

        // Segundo conductor (siempre nulo para este caso)
        $conductor2 = $this->createElement('Conductor');
        $conductor2->setAttribute('xsi:nil', 'true');
        $conductores->appendChild($conductor2);
    }

    /**
     * Agregar datos de un conductor específico
     */
    private function addConductorData(DOMElement $parent, Captain $captain): void
    {
        $conductor = $this->createElement('Conductor');
        $parent->appendChild($conductor);

        // Nombre completo
        $nombre = $this->createElement('nombre', $captain->full_name);
        $conductor->appendChild($nombre);

        // Domicilio
        $domicilio = $this->createElement('domicilio');
        if ($captain->address) {
            $domicilio->textContent = $captain->address;
        } else {
            $domicilio->setAttribute('xsi:nil', 'true');
        }
        $conductor->appendChild($domicilio);

        // País (usar país de licencia o AR por defecto)
        $pais = $captain->licenseCountry?->iso_code ?? 'AR';
        $codPais = $this->createElement('codPais', $pais);
        $conductor->appendChild($codPais);

        // Documento del conductor
        $documento = $this->createElement('documento', $captain->document_number ?? '00000000');
        $conductor->appendChild($documento);

        // Número de licencia
        $licencia = $this->createElement('licencia', $captain->license_number ?? 'NO_APLICA');
        $conductor->appendChild($licencia);
    }

    /**
     * Agregar contenedores con carga
     */
    private function addContenedoresConCarga(DOMElement $parent, Shipment $shipment): void
    {
        $contenedoresConCarga = $this->createElement('contenedoresConCarga');
        $parent->appendChild($contenedoresConCarga);

        // Obtener contenedores del shipment
        $containers = $shipment->containers ?? collect();
        
        if ($containers->isEmpty()) {
            // Si no hay contenedores específicos, crear basado en datos del shipment
            $this->addGenericContainerIds($contenedoresConCarga, $shipment);
        } else {
            // Agregar IDs de contenedores reales
            foreach ($containers as $container) {
                $this->addContainerIdElement($contenedoresConCarga, $container->number);
            }
        }
    }

    /**
     * Agregar IDs de contenedores genéricos
     */
    private function addGenericContainerIds(DOMElement $parent, Shipment $shipment): void
    {
        $containerCount = max(1, $shipment->containers_loaded);
        
        for ($i = 1; $i <= $containerCount; $i++) {
            $containerId = sprintf('%s-%03d', $shipment->shipment_number, $i);
            $this->addContainerIdElement($parent, $containerId);
        }
    }

    /**
     * Agregar elemento ID de contenedor
     */
    private function addContainerIdElement(DOMElement $parent, string $containerId): void
    {
        $idCont = $this->createElement('idCont', $containerId);
        $parent->appendChild($idCont);
    }

    /**
     * Agregar datos de embarcación
     */
    private function addEmbarcacionData(DOMElement $parent, Shipment $shipment): void
    {
        $vessel = $shipment->vessel;
        $company = $shipment->voyage->company;
        
        $embarcacion = $this->createElement('embarcacion');
        $parent->appendChild($embarcacion);

        // Código de país
        $codPais = $this->createElement('codPais', $company->country);
        $embarcacion->appendChild($codPais);

        // ID de la embarcación
        $id = $this->createElement('id', $vessel->registry_number ?? $vessel->name);
        $embarcacion->appendChild($id);

        // Nombre de la embarcación
        $nombre = $this->createElement('nombre', $vessel->name);
        $embarcacion->appendChild($nombre);

        // Tipo de embarcación
        $vesselCategory = $vessel->vesselType?->category ?? 'barge';
        $tipEmb = self::ARGENTINA_CODES['tipo_embarcacion'][$vesselCategory] ?? 'BAR';
        $tipEmbElement = $this->createElement('tipEmb', $tipEmb);
        $embarcacion->appendChild($tipEmbElement);

        // Indicador integra convoy
        $integraConvoy = $shipment->voyage->is_convoy ? 'S' : 'N';
        $indIntegraConvoy = $this->createElement('indIntegraConvoy', $integraConvoy);
        $embarcacion->appendChild($indIntegraConvoy);

        // ID Fiscal ATA Remolcador (CUIT de la empresa)
        $idFiscalATARemol = $this->createElement('idFiscalATARemol', $this->cleanTaxId($company->tax_id));
        $embarcacion->appendChild($idFiscalATARemol);
    }

    /**
     * Limpiar tax_id removiendo guiones y espacios
     */
    private function cleanTaxId(string $taxId): string
    {
        return preg_replace('/[^0-9]/', '', $taxId);
    }

    /**
     * Crear XML para Información Anticipada Argentina
     */
    public function createInformacionAnticipadaXml(Voyage $voyage, string $transactionId): ?string
    {
        try {
            $this->logOperation('info', 'Iniciando creación XML Información Anticipada', [
                'voyage_id' => $voyage->id,
                'transaction_id' => $transactionId,
                'voyage_number' => $voyage->voyage_number,
            ]);

            // TODO: Implementar estructura XML para Información Anticipada
            // Estructura similar al MIC/DTA pero con datos del viaje completo
            
            $this->logOperation('info', 'XML Información Anticipada - Pendiente implementación');
            return null;

        } catch (Exception $e) {
            $this->logOperation('error', 'Error creando XML Información Anticipada', [
                'error' => $e->getMessage(),
                'voyage_id' => $voyage->id,
                'transaction_id' => $transactionId,
            ]);
            return null;
        }
    }

    /**
     * Crear XML para webservices Paraguay
     */
    public function createParaguayMicDtaXml(Shipment $shipment, string $transactionId): ?string
    {
        try {
            $this->logOperation('info', 'Iniciando creación XML Paraguay MIC/DTA', [
                'shipment_id' => $shipment->id,
                'transaction_id' => $transactionId,
            ]);

            // TODO: Implementar estructura XML específica para Paraguay
            // Basada en documentación GDSF Paraguay
            
            $this->logOperation('info', 'XML Paraguay MIC/DTA - Pendiente implementación');
            return null;

        } catch (Exception $e) {
            $this->logOperation('error', 'Error creando XML Paraguay MIC/DTA', [
                'error' => $e->getMessage(),
                'shipment_id' => $shipment->id,
                'transaction_id' => $transactionId,
            ]);
            return null;
        }
    }

    /**
     * Validar estructura XML contra schema
     */
    public function validateXmlStructure(string $xml, string $schemaPath = null): array
{
    $validation = [
        'is_valid' => false,
        'errors' => [],
        'warnings' => [],
    ];

    try {
        $dom = new DOMDocument();
        $dom->preserveWhiteSpace = false;
        
        // ✅ CAPTURAR ERRORES DE PARSING XML
        libxml_use_internal_errors(true);
        
        if (!$dom->loadXML($xml)) {
            $xmlErrors = libxml_get_errors();
            foreach ($xmlErrors as $error) {
                $validation['errors'][] = trim($error->message);
            }
            libxml_clear_errors();
            return $validation;
        }

        // Validar estructura básica SOAP
        $envelope = $dom->getElementsByTagName('Envelope');
        if ($envelope->length === 0) {
            $validation['errors'][] = 'Estructura SOAP inválida: falta Envelope';
        }

        $body = $dom->getElementsByTagName('Body');
        if ($body->length === 0) {
            $validation['errors'][] = 'Estructura SOAP inválida: falta Body';
        }

        // Validar namespace específico Argentina
        $registrarMicDta = $dom->getElementsByTagName('RegistrarMicDta');
        if ($registrarMicDta->length > 0) {
            $xmlns = $registrarMicDta->item(0)->getAttribute('xmlns');
            if (!filter_var($xmlns, FILTER_VALIDATE_URL)) {
                $validation['errors'][] = "Namespace no es URI absoluta válida: {$xmlns}";
            }
        }

        $validation['is_valid'] = empty($validation['errors']);

        return $validation;

    } catch (Exception $e) {
        $validation['errors'][] = 'Error validando XML: ' . $e->getMessage();
        return $validation;
    }
}


    /**
     * Obtener estadísticas del XML generado
     */
    public function getXmlStatistics(string $xml): array
    {
        try {
            $dom = new DOMDocument();
            $dom->loadXML($xml);

            $stats = [
                'size_bytes' => strlen($xml),
                'size_kb' => round(strlen($xml) / 1024, 2),
                'element_count' => $dom->getElementsByTagName('*')->length,
                'encoding' => $dom->encoding,
                'version' => $dom->version,
                'has_soap_envelope' => $dom->getElementsByTagName('Envelope')->length > 0,
                'namespaces' => [],
            ];

            // Extraer namespaces utilizados
            $xpath = new \DOMXPath($dom);
            foreach ($xpath->query('//namespace::*') as $namespace) {
                if ($namespace->localName !== 'xml') {
                    $stats['namespaces'][$namespace->localName] = $namespace->nodeValue;
                }
            }

            return $stats;

        } catch (Exception $e) {
            $this->logOperation('error', 'Error obteniendo estadísticas XML', [
                'error' => $e->getMessage(),
            ]);
            return [];
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
     * Obtener códigos específicos para Argentina
     */
    public static function getArgentinaCodes(): array
    {
        return self::ARGENTINA_CODES;
    }

   /**
 * EXTENSIÓN XmlSerializerService - Métodos para Información Anticipada Argentina
 */

    /**
     * Crear XML para Información Anticipada Argentina (RegistrarViaje)
     */
    public function createAnticipatedXml(Voyage $voyage, string $transactionId): ?string
    {
        try {
            $this->logOperation('info', 'Iniciando creación XML Información Anticipada', [
                'voyage_id' => $voyage->id,
                'transaction_id' => $transactionId,
                'voyage_number' => $voyage->voyage_number,
            ]);

            // Validar precondiciones
            $validation = $this->validateVoyageForAnticipated($voyage);
            if (!$validation['is_valid']) {
                $this->logOperation('error', 'Voyage no válido para Información Anticipada', [
                    'errors' => $validation['errors'],
                ]);
                return null;
            }

            // Inicializar DOM
            $this->initializeDom();

            // Crear estructura SOAP
            $envelope = $this->createSoapEnvelope();
            $body = $this->createElement('soap:Body');
            $envelope->appendChild($body);

            // Crear elemento RegistrarViaje
            $registrarViaje = $this->createElement('RegistrarViaje');
            $registrarViaje->setAttribute('xmlns', 'Ar.Gob.Afip.Dga.Org.wgesinformacionanticipada');
            $body->appendChild($registrarViaje);

            // Agregar autenticación de empresa
            $autenticacion = $this->createAutenticacionEmpresa($registrarViaje);
            
            // Agregar parámetros del viaje
            $parametros = $this->createAnticipatedParam($registrarViaje, $voyage, $transactionId);

            // Generar XML string
            $xmlString = $this->dom->saveXML();
            
            $this->logOperation('info', 'XML Información Anticipada creado exitosamente', [
                'xml_length' => strlen($xmlString),
                'transaction_id' => $transactionId,
                'voyage_id' => $voyage->id,
            ]);

            return $xmlString;

        } catch (Exception $e) {
            $this->logOperation('error', 'Error creando XML Información Anticipada', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'voyage_id' => $voyage->id,
                'transaction_id' => $transactionId,
            ]);
            return null;
        }
    }

    /**
     * Crear XML para rectificación de Información Anticipada (RectificarViaje)
     */
    public function createRectificationXml(Voyage $voyage, string $transactionId, string $originalReference, string $reason): ?string
    {
        try {
            $this->logOperation('info', 'Iniciando creación XML Rectificación', [
                'voyage_id' => $voyage->id,
                'transaction_id' => $transactionId,
                'original_reference' => $originalReference,
                'reason' => $reason,
            ]);

            // Validar precondiciones
            $validation = $this->validateVoyageForAnticipated($voyage);
            if (!$validation['is_valid']) {
                $this->logOperation('error', 'Voyage no válido para rectificación', [
                    'errors' => $validation['errors'],
                ]);
                return null;
            }

            // Inicializar DOM
            $this->initializeDom();

            // Crear estructura SOAP
            $envelope = $this->createSoapEnvelope();
            $body = $this->createElement('soap:Body');
            $envelope->appendChild($body);

            // Crear elemento RectificarViaje
            $rectificarViaje = $this->createElement('RectificarViaje');
            $rectificarViaje->setAttribute('xmlns', 'Ar.Gob.Afip.Dga.Org.wgesinformacionanticipada');
            $body->appendChild($rectificarViaje);

            // Agregar autenticación de empresa
            $autenticacion = $this->createAutenticacionEmpresa($rectificarViaje);
            
            // Agregar parámetros de rectificación
            $parametros = $this->createRectificationParam($rectificarViaje, $voyage, $transactionId, $originalReference, $reason);

            // Generar XML string
            $xmlString = $this->dom->saveXML();
            
            $this->logOperation('info', 'XML Rectificación creado exitosamente', [
                'xml_length' => strlen($xmlString),
                'transaction_id' => $transactionId,
                'original_reference' => $originalReference,
            ]);

            return $xmlString;

        } catch (Exception $e) {
            $this->logOperation('error', 'Error creando XML Rectificación', [
                'error' => $e->getMessage(),
                'original_reference' => $originalReference,
                'reason' => $reason,
            ]);
            return null;
        }
    }

    /**
     * Validar voyage para información anticipada
     */
    private function validateVoyageForAnticipated(Voyage $voyage): array
    {
        $validation = [
            'is_valid' => false,
            'errors' => [],
            'warnings' => [],
        ];

        // 1. Validar voyage básico
        if (!$voyage || !$voyage->id) {
            $validation['errors'][] = 'Voyage no válido o no encontrado';
        }

        // 2. Validar datos obligatorios
        if (!$voyage->voyage_number) {
            $validation['errors'][] = 'Número de viaje requerido';
        }

        if (!$voyage->vessel_id || !$voyage->vessel) {
            $validation['errors'][] = 'Embarcación requerida';
        }

        if (!$voyage->captain_id || !$voyage->captain) {
            $validation['errors'][] = 'Capitán requerido';
        }

        if (!$voyage->departure_port || !$voyage->arrival_port) {
            $validation['errors'][] = 'Puertos de origen y destino requeridos';
        }

        if (!$voyage->departure_date) {
            $validation['errors'][] = 'Fecha de salida requerida';
        }

        // 3. Validar que tenga shipments
        if ($voyage->shipments()->count() === 0) {
            $validation['errors'][] = 'El viaje debe tener al menos un shipment';
        }

        $validation['is_valid'] = empty($validation['errors']);

        return $validation;
    }

    /**
     * Crear parámetros para información anticipada
     */
    private function createAnticipatedParam(DOMElement $parent, Voyage $voyage, string $transactionId): DOMElement
    {
        $parametros = $this->createElement('argRegistrarViaje');
        $parent->appendChild($parametros);

        // ID de transacción
        $idTransaccion = $this->createElement('IdTransaccion', $transactionId);
        $parametros->appendChild($idTransaccion);

        // Datos del viaje
        $this->addVoyageData($parametros, $voyage);

        // Datos de la embarcación
        $this->addVesselData($parametros, $voyage->vessel);

        // Datos del capitán
        $this->addCaptainData($parametros, $voyage->captain);

        // Datos de los shipments
        $this->addShipmentsData($parametros, $voyage);

        return $parametros;
    }

    /**
     * Crear parámetros para rectificación
     */
    private function createRectificationParam(DOMElement $parent, Voyage $voyage, string $transactionId, string $originalReference, string $reason): DOMElement
    {
        $parametros = $this->createElement('argRectificarViaje');
        $parent->appendChild($parametros);

        // ID de transacción
        $idTransaccion = $this->createElement('IdTransaccion', $transactionId);
        $parametros->appendChild($idTransaccion);

        // Referencia original
        $referenciaOriginal = $this->createElement('ReferenciaOriginal', $originalReference);
        $parametros->appendChild($referenciaOriginal);

        // Motivo de rectificación
        $motivoRectif = $this->createElement('MotivoRectificacion', $reason);
        $parametros->appendChild($motivoRectif);

        // Datos del viaje (actualizados)
        $this->addVoyageData($parametros, $voyage);

        // Datos de la embarcación
        $this->addVesselData($parametros, $voyage->vessel);

        // Datos del capitán
        $this->addCaptainData($parametros, $voyage->captain);

        // Datos de los shipments
        $this->addShipmentsData($parametros, $voyage);

        return $parametros;
    }

    /**
     * Agregar datos del viaje al XML
     */
    private function addVoyageData(DOMElement $parent, Voyage $voyage): void
    {
        // Datos del viaje
        $datosViaje = $this->createElement('DatosViaje');
        $parent->appendChild($datosViaje);

        // Número de viaje
        $nroViaje = $this->createElement('NroViaje', $voyage->voyage_number);
        $datosViaje->appendChild($nroViaje);

        // Puerto de origen
        $puertoOrigen = $this->createElement('PuertoOrigen', $voyage->departure_port);
        $datosViaje->appendChild($puertoOrigen);

        // Puerto de destino
        $puertoDestino = $this->createElement('PuertoDestino', $voyage->arrival_port);
        $datosViaje->appendChild($puertoDestino);

        // Fecha de salida
        $fechaSalida = $this->createElement('FechaSalida', $voyage->departure_date->format('Y-m-d\TH:i:s'));
        $datosViaje->appendChild($fechaSalida);

        // Fecha de llegada (si está disponible)
        if ($voyage->arrival_date) {
            $fechaLlegada = $this->createElement('FechaLlegada', $voyage->arrival_date->format('Y-m-d\TH:i:s'));
            $datosViaje->appendChild($fechaLlegada);
        }

        // Indicador de convoy
        $esConvoy = $this->createElement('EsConvoy', $voyage->is_convoy ? 'S' : 'N');
        $datosViaje->appendChild($esConvoy);

        // Vía de transporte (8 para hidrovía)
        $viaTransporte = $this->createElement('ViaTransporte', self::ARGENTINA_CODES['via_transporte']);
        $datosViaje->appendChild($viaTransporte);
    }

    /**
     * Agregar datos de la embarcación al XML
     */
    private function addVesselData(DOMElement $parent, Vessel $vessel): void
    {
        $datosEmbarcacion = $this->createElement('DatosEmbarcacion');
        $parent->appendChild($datosEmbarcacion);

        // Nombre de la embarcación
        $nombre = $this->createElement('Nombre', $vessel->name);
        $datosEmbarcacion->appendChild($nombre);

        // IMO (si está disponible)
        if ($vessel->imo_number) {
            $imo = $this->createElement('IMO', $vessel->imo_number);
            $datosEmbarcacion->appendChild($imo);
        }

        // Tipo de embarcación
        $vesselCategory = $vessel->vesselType?->category ?? 'barge';
        $tipo = self::ARGENTINA_CODES['tipo_embarcacion'][$vesselCategory] ?? 'BAR';
        $tipoEmbarcacion = $this->createElement('TipoEmbarcacion', $tipo);
        $datosEmbarcacion->appendChild($tipoEmbarcacion);

        // Capacidad de contenedores
        if ($vessel->container_capacity) {
            $capacidad = $this->createElement('CapacidadContenedores', $vessel->container_capacity);
            $datosEmbarcacion->appendChild($capacidad);
        }

        // Bandera
        if ($vessel->flag) {
            $bandera = $this->createElement('Bandera', $vessel->flag);
            $datosEmbarcacion->appendChild($bandera);
        }
    }

    /**
     * Agregar datos del capitán al XML
     */
    private function addCaptainData(DOMElement $parent, Captain $captain): void
    {
        $datosCapitan = $this->createElement('DatosCapitan');
        $parent->appendChild($datosCapitan);

        // Nombre completo
        $nombre = $this->createElement('NombreCompleto', $captain->full_name);
        $datosCapitan->appendChild($nombre);

        // Número de licencia
        if ($captain->license_number) {
            $licencia = $this->createElement('NumeroLicencia', $captain->license_number);
            $datosCapitan->appendChild($licencia);
        }

        // Tipo de documento
        if ($captain->document_type) {
            $tipoDoc = $this->createElement('TipoDocumento', $captain->document_type);
            $datosCapitan->appendChild($tipoDoc);
        }

        // Número de documento
        if ($captain->document_number) {
            $nroDoc = $this->createElement('NumeroDocumento', $captain->document_number);
            $datosCapitan->appendChild($nroDoc);
        }

        // Nacionalidad
        if ($captain->nationality) {
            $nacionalidad = $this->createElement('Nacionalidad', $captain->nationality);
            $datosCapitan->appendChild($nacionalidad);
        }
    }

    /**
     * Agregar datos de los shipments al XML
     */
    private function addShipmentsData(DOMElement $parent, Voyage $voyage): void
    {
        $shipments = $voyage->shipments;
        
        if ($shipments->count() === 0) {
            return;
        }

        $listaEnvios = $this->createElement('ListaEnvios');
        $parent->appendChild($listaEnvios);

        foreach ($shipments as $shipment) {
            $envio = $this->createElement('Envio');
            $listaEnvios->appendChild($envio);

            // Número de shipment
            $nroEnvio = $this->createElement('NumeroEnvio', $shipment->shipment_number);
            $envio->appendChild($nroEnvio);

            // Datos del cliente (si existe)
            if ($shipment->client) {
                $this->addClientData($envio, $shipment->client);
            }

            // Datos de carga
            $this->addCargoData($envio, $shipment);

            // Contenedores (si los tiene)
            $containers = $shipment->containers;
            if ($containers->count() > 0) {
                $this->addContainersData($envio, $containers);
            }
        }
    }

    /**
     * Agregar datos del cliente al XML
     */
    private function addClientData(DOMElement $parent, $client): void
    {
        $datosCliente = $this->createElement('DatosCliente');
        $parent->appendChild($datosCliente);

        // Razón social
        $razonSocial = $this->createElement('RazonSocial', $client->legal_name ?? $client->name);
        $datosCliente->appendChild($razonSocial);

        // CUIT/RUC
        if ($client->tax_id) {
            $taxId = $this->createElement('CUIT', $this->cleanTaxId($client->tax_id));
            $datosCliente->appendChild($taxId);
        }

        // Dirección
        if ($client->address) {
            $direccion = $this->createElement('Direccion', $client->address);
            $datosCliente->appendChild($direccion);
        }

        // Ciudad
        if ($client->city) {
            $ciudad = $this->createElement('Ciudad', $client->city);
            $datosCliente->appendChild($ciudad);
        }

        // País
        if ($client->country) {
            $pais = $this->createElement('Pais', $client->country);
            $datosCliente->appendChild($pais);
        }
    }

    /**
     * Agregar datos de carga al XML
     */
    private function addCargoData(DOMElement $parent, Shipment $shipment): void
    {
        $datosCarga = $this->createElement('DatosCarga');
        $parent->appendChild($datosCarga);

        // Peso bruto
        if ($shipment->gross_weight) {
            $pesoBruto = $this->createElement('PesoBruto', $shipment->gross_weight);
            $datosCarga->appendChild($pesoBruto);
        }

        // Peso neto
        if ($shipment->net_weight) {
            $pesoNeto = $this->createElement('PesoNeto', $shipment->net_weight);
            $datosCarga->appendChild($pesoNeto);
        }

        // Volumen
        if ($shipment->volume) {
            $volumen = $this->createElement('Volumen', $shipment->volume);
            $datosCarga->appendChild($volumen);
        }

        // Cantidad de contenedores
        if ($shipment->containers_loaded) {
            $cantContenedores = $this->createElement('CantidadContenedores', $shipment->containers_loaded);
            $datosCarga->appendChild($cantContenedores);
        }

        // Descripción de mercadería
        if ($shipment->cargo_description) {
            $descripcion = $this->createElement('DescripcionMercaderia', $shipment->cargo_description);
            $datosCarga->appendChild($descripcion);
        }

        // Valor FOB (si está disponible)
        if ($shipment->fob_value) {
            $valorFOB = $this->createElement('ValorFOB', $shipment->fob_value);
            $datosCarga->appendChild($valorFOB);
        }
    }

    /**
     * Agregar datos de contenedores al XML
     */
    private function addContainersData(DOMElement $parent, $containers): void
    {
        $listaContenedores = $this->createElement('ListaContenedores');
        $parent->appendChild($listaContenedores);

        foreach ($containers as $container) {
            $contenedor = $this->createElement('Contenedor');
            $listaContenedores->appendChild($contenedor);

            // Número de contenedor
            $numero = $this->createElement('Numero', $container->container_number);
            $contenedor->appendChild($numero);

            // Tipo de contenedor
            $tipo = $this->createElement('Tipo', $container->container_type);
            $contenedor->appendChild($tipo);

            // Estado del contenedor
            if ($container->status) {
                $estado = $this->createElement('Estado', $container->status);
                $contenedor->appendChild($estado);
            }

            // Sello
            if ($container->seal_number) {
                $sello = $this->createElement('Sello', $container->seal_number);
                $contenedor->appendChild($sello);
            }

            // Peso bruto del contenedor
            if ($container->gross_weight) {
                $peso = $this->createElement('PesoBruto', $container->gross_weight);
                $contenedor->appendChild($peso);
            }

            // Condición del contenedor
            $condicion = self::ARGENTINA_CODES['condicion_contenedor']['house'] ?? 'H';
            $condicionElement = $this->createElement('Condicion', $condicion);
            $contenedor->appendChild($condicionElement);
        }
    }

    /**
     * Mapeo adicional de datos reales del sistema para información anticipada
     */
    private const ANTICIPATED_CODES = [
        'tipo_viaje' => [
            'regular' => 'REG',
            'convoy' => 'CON',
            'especial' => 'ESP',
        ],
        'estado_viaje' => [
            'programado' => 'PROG',
            'en_curso' => 'CURSO',
            'completado' => 'COMP',
            'cancelado' => 'CANC',
        ],
        'tipo_carga' => [
            'contenedores' => 'CONT',
            'granel' => 'GRAN',
            'general' => 'GRAL',
            'liquida' => 'LIQ',
        ],
    ];

    /**
     * Obtener códigos específicos para información anticipada
     */
    public static function getAnticipatedCodes(): array
    {
        return self::ANTICIPATED_CODES;
    }

    /**
     * MÉTODOS ESPECÍFICOS PARAGUAY GDSF
     * Extensión del XmlSerializerService para webservices Paraguay
     */

    /**
     * Mapeo de códigos para Paraguay GDSF
     */
    private const PARAGUAY_CODES = [
        'via_transporte' => 'FLUVIAL',
        'pais_paraguay' => 'PY',
        'pais_argentina' => 'AR',
        'tipo_documento_ruc' => 'RUC',
        'tipo_transporte' => 'HIDROVIA',
        'puerto_villeta' => 'PYTVT',
        'puerto_buenos_aires' => 'ARBUE',
        'moneda_default' => 'USD',
        'unidad_medida_kg' => 'KG',
        'unidad_medida_m3' => 'M3',
        'regimen_aduanero' => '10', // Importación para consumo
        'tipo_operacion' => 'IMPO', // Importación
        'tipo_contenedor' => [
            '40HC' => '42G1', // 40' High Cube General
            '20GP' => '22G1', // 20' General Purpose
            '40GP' => '42G1', // 40' General Purpose
            '20OT' => '22U1', // 20' Open Top
            '40OT' => '42U1', // 40' Open Top
        ],
        'estado_contenedor' => [
            'full' => 'CARGADO',
            'empty' => 'VACIO',
        ],
    ];

    /**
     * Crear XML para Manifiesto Paraguay GDSF
     * Basado en documentación oficial Paraguay DNA
     */
    public function createParaguayManifestXml(Voyage $voyage, string $transactionId): ?string
    {
        try {
            $this->logOperation('info', 'Iniciando creación XML Manifiesto Paraguay', [
                'voyage_id' => $voyage->id,
                'transaction_id' => $transactionId,
                'vessel' => $voyage->vessel->name ?? 'N/A',
                'voyage_number' => $voyage->voyage_number,
            ]);

            $this->initializeDomDocument();
            
            // Crear envelope SOAP específico Paraguay
            $envelope = $this->createParaguaySoapEnvelope();
            $body = $this->createElement('soap:Body');
            $envelope->appendChild($body);

            // Método específico GDSF
            $manifestMethod = $this->createElement('gdsf:enviarManifiesto');
            $manifestMethod->setAttribute('xmlns:gdsf', 'https://secure.aduana.gov.py/gdsf/schema');
            $body->appendChild($manifestMethod);

            // Crear estructura del manifiesto GDSF
            $this->createParaguayManifestStructure($manifestMethod, $voyage, $transactionId);

            $xml = $this->dom->saveXML();
            
            $this->logOperation('info', 'XML Manifiesto Paraguay generado exitosamente', [
                'transaction_id' => $transactionId,
                'xml_length' => strlen($xml),
                'voyage_id' => $voyage->id,
            ]);

            return $xml;

        } catch (Exception $e) {
            $this->logOperation('error', 'Error creando XML Manifiesto Paraguay', [
                'error' => $e->getMessage(),
                'voyage_id' => $voyage->id,
                'transaction_id' => $transactionId,
                'trace' => $e->getTraceAsString(),
            ]);
            return null;
        }
    }

    /**
     * Crear XML para Consulta Paraguay GDSF
     */
    public function createParaguayQueryXml(string $paraguayReference, string $transactionId): ?string
    {
        try {
            $this->logOperation('info', 'Iniciando creación XML Consulta Paraguay', [
                'paraguay_reference' => $paraguayReference,
                'transaction_id' => $transactionId,
            ]);

            $this->initializeDomDocument();
            
            // Crear envelope SOAP específico Paraguay
            $envelope = $this->createParaguaySoapEnvelope();
            $body = $this->createElement('soap:Body');
            $envelope->appendChild($body);

            // Método específico GDSF para consultas
            $queryMethod = $this->createElement('gdsf:consultarEstado');
            $queryMethod->setAttribute('xmlns:gdsf', 'https://secure.aduana.gov.py/gdsf/schema');
            $body->appendChild($queryMethod);

            // Parámetros de consulta
            $queryParams = $this->createElement('gdsf:parametrosConsulta');
            $queryMethod->appendChild($queryParams);

            // RUC de la empresa consultante
            $rucEmpresa = $this->createElement('gdsf:rucEmpresa', $this->cleanTaxId($this->company->tax_id));
            $queryParams->appendChild($rucEmpresa);

            // Referencia Paraguay a consultar
            $referencia = $this->createElement('gdsf:referenciaManifiesto', $paraguayReference);
            $queryParams->appendChild($referencia);

            // ID de transacción
            $idTransaccion = $this->createElement('gdsf:idTransaccion', $transactionId);
            $queryParams->appendChild($idTransaccion);

            // Tipo de consulta
            $tipoConsulta = $this->createElement('gdsf:tipoConsulta', 'ESTADO_MANIFIESTO');
            $queryParams->appendChild($tipoConsulta);

            $xml = $this->dom->saveXML();
            
            $this->logOperation('info', 'XML Consulta Paraguay generado exitosamente', [
                'transaction_id' => $transactionId,
                'xml_length' => strlen($xml),
                'paraguay_reference' => $paraguayReference,
            ]);

            return $xml;

        } catch (Exception $e) {
            $this->logOperation('error', 'Error creando XML Consulta Paraguay', [
                'error' => $e->getMessage(),
                'paraguay_reference' => $paraguayReference,
                'transaction_id' => $transactionId,
            ]);
            return null;
        }
    }

    /**
     * Crear XML para Rectificación Paraguay GDSF
     */
    public function createParaguayRectificationXml(string $paraguayReference, array $corrections, string $transactionId): ?string
    {
        try {
            $this->logOperation('info', 'Iniciando creación XML Rectificación Paraguay', [
                'paraguay_reference' => $paraguayReference,
                'corrections_count' => count($corrections),
                'transaction_id' => $transactionId,
            ]);

            $this->initializeDomDocument();
            
            // Crear envelope SOAP específico Paraguay
            $envelope = $this->createParaguaySoapEnvelope();
            $body = $this->createElement('soap:Body');
            $envelope->appendChild($body);

            // Método específico GDSF para rectificaciones
            $rectifyMethod = $this->createElement('gdsf:rectificarManifiesto');
            $rectifyMethod->setAttribute('xmlns:gdsf', 'https://secure.aduana.gov.py/gdsf/schema');
            $body->appendChild($rectifyMethod);

            // Parámetros de rectificación
            $rectifyParams = $this->createElement('gdsf:parametrosRectificacion');
            $rectifyMethod->appendChild($rectifyParams);

            // RUC de la empresa
            $rucEmpresa = $this->createElement('gdsf:rucEmpresa', $this->cleanTaxId($this->company->tax_id));
            $rectifyParams->appendChild($rucEmpresa);

            // Referencia del manifiesto a rectificar
            $referencia = $this->createElement('gdsf:referenciaOriginal', $paraguayReference);
            $rectifyParams->appendChild($referencia);

            // ID de transacción de rectificación
            $idTransaccion = $this->createElement('gdsf:idTransaccionRectificacion', $transactionId);
            $rectifyParams->appendChild($idTransaccion);

            // Motivo de rectificación
            $motivo = $this->createElement('gdsf:motivoRectificacion', 'CORRECCION_DATOS');
            $rectifyParams->appendChild($motivo);

            // Correcciones específicas
            $correcciones = $this->createElement('gdsf:correcciones');
            $rectifyParams->appendChild($correcciones);

            foreach ($corrections as $field => $newValue) {
                $correccion = $this->createElement('gdsf:correccion');
                $correcciones->appendChild($correccion);

                $campo = $this->createElement('gdsf:campo', $field);
                $correccion->appendChild($campo);

                $valorNuevo = $this->createElement('gdsf:valorNuevo', $newValue);
                $correccion->appendChild($valorNuevo);
            }

            $xml = $this->dom->saveXML();
            
            $this->logOperation('info', 'XML Rectificación Paraguay generado exitosamente', [
                'transaction_id' => $transactionId,
                'xml_length' => strlen($xml),
                'corrections_applied' => count($corrections),
            ]);

            return $xml;

        } catch (Exception $e) {
            $this->logOperation('error', 'Error creando XML Rectificación Paraguay', [
                'error' => $e->getMessage(),
                'paraguay_reference' => $paraguayReference,
                'transaction_id' => $transactionId,
            ]);
            return null;
        }
    }

    /**
     * Crear envelope SOAP específico para Paraguay
     */
    private function createParaguaySoapEnvelope(): DOMElement
    {
        $envelope = $this->createElement('soap:Envelope');
        
        // Namespaces específicos Paraguay GDSF
        $envelope->setAttribute('xmlns:soap', 'http://schemas.xmlsoap.org/soap/envelope/');
        $envelope->setAttribute('xmlns:gdsf', 'https://secure.aduana.gov.py/gdsf/schema');
        $envelope->setAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
        $envelope->setAttribute('xmlns:xsd', 'http://www.w3.org/2001/XMLSchema');
        
        $this->dom->appendChild($envelope);
        return $envelope;
    }

    /**
     * Crear estructura del manifiesto Paraguay GDSF
     * Basado en documentación oficial DNA Paraguay
     */
    private function createParaguayManifestStructure(DOMElement $parent, Voyage $voyage, string $transactionId): void
    {
        // Parámetros del manifiesto
        $manifestParams = $this->createElement('gdsf:parametrosManifiesto');
        $parent->appendChild($manifestParams);

        // Datos de la empresa transportista
        $this->createParaguayCompanyData($manifestParams);

        // Datos del viaje/transporte
        $this->createParaguayVoyageData($manifestParams, $voyage, $transactionId);

        // Datos de la embarcación
        $this->createParaguayVesselData($manifestParams, $voyage->vessel, $voyage);

        // Datos de los conocimientos (shipments)
        $this->createParaguayShipmentsData($manifestParams, $voyage->shipments);

        // Datos de contenedores
        $this->createParaguayContainersData($manifestParams, $voyage);
    }

    /**
     * Crear datos de empresa para Paraguay
     */
    private function createParaguayCompanyData(DOMElement $parent): void
    {
        $empresaTransportista = $this->createElement('gdsf:empresaTransportista');
        $parent->appendChild($empresaTransportista);

        // RUC de la empresa (sin guiones)
        $ruc = $this->createElement('gdsf:ruc', $this->cleanTaxId($this->company->tax_id));
        $empresaTransportista->appendChild($ruc);

        // Razón social
        $razonSocial = $this->createElement('gdsf:razonSocial', $this->company->name);
        $empresaTransportista->appendChild($razonSocial);

        // Tipo de documento
        $tipoDocumento = $this->createElement('gdsf:tipoDocumento', self::PARAGUAY_CODES['tipo_documento_ruc']);
        $empresaTransportista->appendChild($tipoDocumento);

        // Domicilio (si está disponible)
        if ($this->company->address) {
            $domicilio = $this->createElement('gdsf:domicilio', $this->company->address);
            $empresaTransportista->appendChild($domicilio);
        }
    }

    /**
     * Crear datos del viaje para Paraguay
     */
    private function createParaguayVoyageData(DOMElement $parent, Voyage $voyage, string $transactionId): void
    {
        $datosViaje = $this->createElement('gdsf:datosViaje');
        $parent->appendChild($datosViaje);

        // ID de transacción único
        $idTransaccion = $this->createElement('gdsf:idTransaccion', $transactionId);
        $datosViaje->appendChild($idTransaccion);

        // Número de viaje
        $numeroViaje = $this->createElement('gdsf:numeroViaje', $voyage->voyage_number);
        $datosViaje->appendChild($numeroViaje);

        // Vía de transporte
        $viaTransporte = $this->createElement('gdsf:viaTransporte', self::PARAGUAY_CODES['via_transporte']);
        $datosViaje->appendChild($viaTransporte);

        // Tipo de operación
        $tipoOperacion = $this->createElement('gdsf:tipoOperacion', self::PARAGUAY_CODES['tipo_operacion']);
        $datosViaje->appendChild($tipoOperacion);

        // Puerto de origen
        $puertoOrigen = $this->createElement('gdsf:puertoOrigen', $voyage->departure_port ?? self::PARAGUAY_CODES['puerto_buenos_aires']);
        $datosViaje->appendChild($puertoOrigen);

        // Puerto de destino
        $puertoDestino = $this->createElement('gdsf:puertoDestino', $voyage->arrival_port ?? self::PARAGUAY_CODES['puerto_villeta']);
        $datosViaje->appendChild($puertoDestino);

        // Fecha de salida
        if ($voyage->departure_date) {
            $fechaSalida = $this->createElement('gdsf:fechaSalida', $voyage->departure_date->format('Y-m-d'));
            $datosViaje->appendChild($fechaSalida);
        }

        // Fecha estimada de llegada
        if ($voyage->estimated_arrival) {
            $fechaLlegadaEstimada = $this->createElement('gdsf:fechaLlegadaEstimada', $voyage->estimated_arrival->format('Y-m-d'));
            $datosViaje->appendChild($fechaLlegadaEstimada);
        }
    }

    /**
     * Crear datos de embarcación para Paraguay
     */
    private function createParaguayVesselData(DOMElement $parent, ?Vessel $vessel, Voyage $voyage): void
    {
        $datosEmbarcacion = $this->createElement('gdsf:datosEmbarcacion');
        $parent->appendChild($datosEmbarcacion);

        if ($vessel) {
            // Nombre de la embarcación
            $nombreEmbarcacion = $this->createElement('gdsf:nombre', $vessel->name);
            $datosEmbarcacion->appendChild($nombreEmbarcacion);

            // Código/matrícula
            $matricula = $this->createElement('gdsf:matricula', $vessel->imo_number ?? $vessel->name);
            $datosEmbarcacion->appendChild($matricula);

            // Bandera
            $bandera = $this->createElement('gdsf:bandera', $vessel->flag ?? self::PARAGUAY_CODES['pais_argentina']);
            $datosEmbarcacion->appendChild($bandera);

            // Tipo de embarcación (basado en is_barge)
            $tipoEmbarcacion = $vessel->is_barge ? 'BARCAZA' : 'BUQUE_MOTOR';
            $tipo = $this->createElement('gdsf:tipoEmbarcacion', $tipoEmbarcacion);
            $datosEmbarcacion->appendChild($tipo);

            // Capacidad en TEU (si está disponible)
            if ($vessel->capacity_teu) {
                $capacidad = $this->createElement('gdsf:capacidadTeu', $vessel->capacity_teu);
                $datosEmbarcacion->appendChild($capacidad);
            }
        } else {
            // Datos mínimos si no hay vessel
            $nombreEmbarcacion = $this->createElement('gdsf:nombre', 'N/A');
            $datosEmbarcacion->appendChild($nombreEmbarcacion);

            $matricula = $this->createElement('gdsf:matricula', $voyage->voyage_number ?? 'N/A');
            $datosEmbarcacion->appendChild($matricula);
        }
    }

    /**
     * Crear datos de conocimientos (shipments) para Paraguay
     */
    private function createParaguayShipmentsData(DOMElement $parent, $shipments): void
    {
        $conocimientos = $this->createElement('gdsf:conocimientos');
        $parent->appendChild($conocimientos);

        foreach ($shipments as $shipment) {
            $conocimiento = $this->createElement('gdsf:conocimiento');
            $conocimientos->appendChild($conocimiento);

            // Número de conocimiento
            $numeroConocimiento = $this->createElement('gdsf:numero', $shipment->bl_number ?? 'N/A');
            $conocimiento->appendChild($numeroConocimiento);

            // Shipper (cargador)
            if ($shipment->shipper_name) {
                $cargador = $this->createElement('gdsf:cargador', $shipment->shipper_name);
                $conocimiento->appendChild($cargador);
            }

            // Consignee (consignatario)
            if ($shipment->consignee_name) {
                $consignatario = $this->createElement('gdsf:consignatario', $shipment->consignee_name);
                $conocimiento->appendChild($consignatario);
            }

            // Descripción de mercadería
            if ($shipment->description) {
                $descripcion = $this->createElement('gdsf:descripcionMercaderia', $shipment->description);
                $conocimiento->appendChild($descripcion);
            }

            // Peso bruto
            if ($shipment->gross_weight) {
                $peso = $this->createElement('gdsf:pesoBruto', $shipment->gross_weight);
                $peso->setAttribute('unidad', self::PARAGUAY_CODES['unidad_medida_kg']);
                $conocimiento->appendChild($peso);
            }

            // Volumen
            if ($shipment->volume) {
                $volumen = $this->createElement('gdsf:volumen', $shipment->volume);
                $volumen->setAttribute('unidad', self::PARAGUAY_CODES['unidad_medida_m3']);
                $conocimiento->appendChild($volumen);
            }

            // Número de bultos
            if ($shipment->number_of_packages) {
                $bultos = $this->createElement('gdsf:numeroBultos', $shipment->number_of_packages);
                $conocimiento->appendChild($bultos);
            }
        }
    }

    /**
     * Crear datos de contenedores para Paraguay
     */
    private function createParaguayContainersData(DOMElement $parent, Voyage $voyage): void
    {
        $contenedores = $this->createElement('gdsf:contenedores');
        $parent->appendChild($contenedores);

        // Recopilar todos los contenedores del viaje
        $allContainers = collect();
        foreach ($voyage->shipments as $shipment) {
            $allContainers = $allContainers->merge($shipment->containers);
        }

        foreach ($allContainers as $container) {
            $contenedor = $this->createElement('gdsf:contenedor');
            $contenedores->appendChild($contenedor);

            // Número de contenedor
            $numero = $this->createElement('gdsf:numero', $container->container_number);
            $contenedor->appendChild($numero);

            // Tipo de contenedor (mapear a códigos Paraguay)
            $tipoContenedor = self::PARAGUAY_CODES['tipo_contenedor'][$container->container_type] ?? $container->container_type;
            $tipo = $this->createElement('gdsf:tipo', $tipoContenedor);
            $contenedor->appendChild($tipo);

            // Estado del contenedor
            $estado = $container->is_empty ? 
                self::PARAGUAY_CODES['estado_contenedor']['empty'] : 
                self::PARAGUAY_CODES['estado_contenedor']['full'];
            $estadoElement = $this->createElement('gdsf:estado', $estado);
            $contenedor->appendChild($estadoElement);

            // Sellos
            if ($container->seal_number) {
                $sellos = $this->createElement('gdsf:sellos');
                $contenedor->appendChild($sellos);

                $sello = $this->createElement('gdsf:sello', $container->seal_number);
                $sellos->appendChild($sello);
            }

            // Peso tara
            if ($container->tare_weight) {
                $pesoTara = $this->createElement('gdsf:pesoTara', $container->tare_weight);
                $pesoTara->setAttribute('unidad', self::PARAGUAY_CODES['unidad_medida_kg']);
                $contenedor->appendChild($pesoTara);
            }

            // Peso bruto
            if ($container->gross_weight) {
                $pesoBruto = $this->createElement('gdsf:pesoBruto', $container->gross_weight);
                $pesoBruto->setAttribute('unidad', self::PARAGUAY_CODES['unidad_medida_kg']);
                $contenedor->appendChild($pesoBruto);
            }
        }
    }

    /**
 * MÉTODO STUB TEMPORAL - createTransshipmentXml
 * 
 * AGREGAR al final de: app/Services/Webservice/XmlSerializerService.php
 * (antes del último "}")
 */

/**
 * Crear XML para Transbordos Argentina (RegistrarEnvios) - MÉTODO STUB TEMPORAL
 * 
 * @param array $transshipmentData Datos del transbordo ['barge_data' => [...], 'voyage' => Voyage]
 * @param string $transactionId ID de transacción
 * @return string|null XML generado o null si falla
 */
public function createTransshipmentXml(array $transshipmentData, string $transactionId): ?string
{
    try {
        $this->logOperation('info', 'Creando XML transbordo (STUB TEMPORAL)', [
            'transaction_id' => $transactionId,
            'barges_count' => count($transshipmentData['barge_data'] ?? []),
            'voyage_id' => $transshipmentData['voyage']->id ?? 'N/A'
        ]);

        // Inicializar DOM
        $this->initializeDom();

        // Crear estructura SOAP básica
        $envelope = $this->createSoapEnvelope();
        $body = $this->createElement('soap:Body');
        $envelope->appendChild($body);

        // Crear elemento RegistrarEnvios (método para transbordos según documentación AFIP)
        $registrarEnvios = $this->createElement('RegistrarEnvios');
        $registrarEnvios->setAttribute('xmlns', 'Ar.Gob.Afip.Dga.wgesregsintia2');
        $body->appendChild($registrarEnvios);

        // Autenticación de empresa
        $this->createAutenticacionEmpresa($registrarEnvios);

        // Parámetros del transbordo
        $parametros = $this->createElement('argRegistrarEnviosParam');
        $registrarEnvios->appendChild($parametros);

        // ID de transacción
        $idTransaccion = $this->createElement('idTransaccion', $transactionId);
        $parametros->appendChild($idTransaccion);

        // ID del título de transporte (usando voyage_number o default)
        $voyage = $transshipmentData['voyage'] ?? null;
        $titTrans = $voyage ? $voyage->voyage_number : 'TIT-' . substr($transactionId, -8);
        $idTitTrans = $this->createElement('idTitTrans', $titTrans);
        $parametros->appendChild($idTitTrans);

        // Envíos (barcazas con contenedores)
        $envios = $this->createElement('envios');
        $parametros->appendChild($envios);

        // Procesar cada barcaza como un envío
        $bargeData = $transshipmentData['barge_data'] ?? [];
        foreach ($bargeData as $index => $barge) {
            $envio = $this->createElement('Envio');
            $envios->appendChild($envio);

            // Destinaciones (ruta de la barcaza)
            $destinaciones = $this->createElement('destinaciones');
            $destinaciones->setAttribute('xsi:nil', 'true');
            $envio->appendChild($destinaciones);

            // Indicador de última fracción (siempre 'S' para simplicidad)
            $indUltFra = $this->createElement('indUltFra', 'S');
            $envio->appendChild($indUltFra);

            // Contenedores de esta barcaza
            $contenedores = $this->createElement('contenedores');
            $envio->appendChild($contenedores);

            $containers = $barge['containers'] ?? [];
            foreach ($containers as $containerIndex => $container) {
                $contenedor = $this->createElement('Contenedor');
                $contenedores->appendChild($contenedor);

                // ID del contenedor
                $containerId = $container['container_number'] ?? "CONT{$index}{$containerIndex}";
                $id = $this->createElement('id', $containerId);
                $contenedor->appendChild($id);

                // Código de medida (tipo de contenedor simplificado)
                $containerType = $container['container_type'] ?? '20ST';
                $codMedida = $this->createElement('codMedida', $containerType);
                $contenedor->appendChild($codMedida);

                // Condición del contenedor (siempre 'LLENO' para simplicidad)
                $condicion = $this->createElement('condicion', 'LLENO');
                $contenedor->appendChild($condicion);

                // Accesorio (no aplica para contenedores básicos)
                $accesorio = $this->createElement('accesorio', '');
                $contenedor->appendChild($accesorio);

                // Precintos (sello del contenedor)
                $precintos = $this->createElement('precintos');
                $precintos->setAttribute('xsi:nil', 'true');
                $contenedor->appendChild($precintos);
            }
        }

        // Generar XML final
        $xmlContent = $this->dom->saveXML();
        
        $this->logOperation('info', 'XML transbordo generado exitosamente (STUB)', [
            'transaction_id' => $transactionId,
            'xml_size_bytes' => strlen($xmlContent),
            'barges_processed' => count($bargeData)
        ]);

        return $xmlContent;

    } catch (Exception $e) {
        $this->logOperation('error', 'Error generando XML transbordo (STUB)', [
            'transaction_id' => $transactionId,
            'error' => $e->getMessage(),
            'line' => $e->getLine()
        ]);

        return null;
    }
}

/**
 * Método de logging con category requerida - AGREGADO
 */
protected function logOperation(string $level, string $message, array $context = [], string $category = 'xml_operation'): void
{
    try {
        // Agregar información del servicio al contexto
        $context['service'] = 'XmlSerializerService';
        $context['company_id'] = $this->company->id;
        $context['company_name'] = $this->company->legal_name ?? $this->company->name;
        $context['timestamp'] = now()->toISOString();

        // Log a Laravel por defecto
        Log::$level($message, $context);

        // Si hay transaction_id en contexto, intentar log a webservice_logs
        $transactionId = $context['transaction_id'] ?? null;
        if ($transactionId && is_numeric($transactionId)) {
            \App\Models\WebserviceLog::create([
                'transaction_id' => (int) $transactionId,
                'level' => $level,
                'category' => $category, // ✅ CAMPO REQUERIDO
                'message' => $message,
                'context' => $context,
                'created_at' => now(),
            ]);
        }

    } catch (\Exception $e) {
        // Fallback a log normal de Laravel si falla webservice_logs
        Log::error('Error logging to webservice_logs table', [
            'original_message' => $message,
            'error' => $e->getMessage()
        ]);
    }
}

}