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
            'company_name' => $company->business_name,
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
            ]);

            // Validar precondiciones
            $validation = $this->validateShipmentForMicDta($shipment);
            if (!$validation['is_valid']) {
                $this->logOperation('error', 'Shipment no válido para MIC/DTA', [
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

            // Crear elemento RegistrarMicDta
            $registrarMicDta = $this->createElement('RegistrarMicDta');
            $registrarMicDta->setAttribute('xmlns', 'Ar.Gob.Afip.Dga.wgesregsintia2');
            $body->appendChild($registrarMicDta);

            // Agregar autenticación de empresa
            $autenticacion = $this->createAutenticacionEmpresa($registrarMicDta);
            
            // Agregar parámetros del MIC/DTA
            $parametros = $this->createMicDtaParam($registrarMicDta, $shipment, $transactionId);

            // Generar XML string
            $xmlString = $this->dom->saveXML();
            
            $this->logOperation('info', 'XML MIC/DTA creado exitosamente', [
                'xml_length' => strlen($xmlString),
                'transaction_id' => $transactionId,
            ]);

            return $xmlString;

        } catch (Exception $e) {
            $this->logOperation('error', 'Error creando XML MIC/DTA', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'shipment_id' => $shipment->id,
                'transaction_id' => $transactionId,
            ]);
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
        $nombre = $this->createElement('nombre', $company->business_name);
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
        $nombre = $this->createElement('nombre', $company->business_name);
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
            
            if (!$dom->loadXML($xml)) {
                $validation['errors'][] = 'XML malformado o inválido';
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

            // TODO: Validar contra schema XSD si se proporciona
            if ($schemaPath && file_exists($schemaPath)) {
                if (!$dom->schemaValidate($schemaPath)) {
                    $validation['errors'][] = 'XML no válido según schema XSD';
                }
            }

            $validation['is_valid'] = empty($validation['errors']);

            $this->logOperation('info', 'Validación XML completada', $validation);

            return $validation;

        } catch (Exception $e) {
            $validation['errors'][] = 'Error validando XML: ' . $e->getMessage();
            
            $this->logOperation('error', 'Error en validación XML', [
                'error' => $e->getMessage(),
            ]);

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
     * Logging centralizado para el servicio
     */
    private function logOperation(string $level, string $message, array $context = []): void
    {
        $logData = array_merge([
            'service' => 'XmlSerializerService',
            'company_id' => $this->company->id,
            'company_name' => $this->company->business_name,
            'timestamp' => now()->toISOString(),
        ], $context);

        // Log en archivo Laravel
        Log::{$level}($message, $logData);

        // Log en tabla webservice_logs
        try {
            WebserviceLog::create([
                'transaction_id' => null,
                'level' => $level,
                'message' => $message,
                'context' => $logData,
            ]);
        } catch (Exception $e) {
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
     * Obtener códigos específicos para Argentina
     */
    public static function getArgentinaCodes(): array
    {
        return self::ARGENTINA_CODES;
    }
}