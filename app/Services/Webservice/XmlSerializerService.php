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
 * ✅ CORREGIDO: XmlSerializerService - Versión Final
 * 
 * Servicio especializado para serialización de datos del sistema a XML
 * para webservices aduaneros Argentina y Paraguay.
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
        // ✅ NUEVO: Namespace correcto para AFIP Argentina
        'afip_micdta_namespace' => 'http://schemas.afip.gob.ar/wgesregsintia2/v1',
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
    ];

    public function __construct(Company $company, array $config = [])
    {
        $this->company = $company;
        $this->config = array_merge(self::DEFAULT_CONFIG, $config);
        
        $this->logOperation('info', 'XmlSerializerService inicializado con namespace corregido', [
            'company_id' => $company->id,
            'company_name' => $company->legal_name,
            'afip_namespace' => $this->config['afip_micdta_namespace'],
        ]);
    }
    /**
     * ✅ CORRECCIÓN PRINCIPAL: Método createMicDtaXml con namespace correcto
     */
    public function createMicDtaXml(Shipment $shipment, string $transactionId): ?string
    {
        try {
            $this->logOperation('info', 'Iniciando creación XML MIC/DTA Argentina', [
                'shipment_id' => $shipment->id,
                'transaction_id' => $transactionId,
                'shipment_number' => $shipment->shipment_number,
            ], 'xml_generation');

            // Validar precondiciones
            $validation = $this->validateShipmentForMicDta($shipment);
            if (!$validation['is_valid']) {
                $this->logOperation('error', 'Shipment no válido para MIC/DTA', [
                    'errors' => $validation['errors'],
                ], 'xml_validation');
                return null;
            }

            // Inicializar DOM
            $this->initializeDom();

            // Crear estructura SOAP
            $envelope = $this->createSoapEnvelope();
            $body = $this->createElement('soap:Body');
            $envelope->appendChild($body);

            // ✅ CORRECCIÓN CRÍTICA: Namespace XML correcto
            $registrarMicDta = $this->createElement('RegistrarMicDta');
            
            // ❌ ANTES (INCORRECTO):
            // $registrarMicDta->setAttribute('xmlns', 'Ar.Gob.Afip.Dga.wgesregsintia2');
            
            // ✅ DESPUÉS (CORRECTO):
            $registrarMicDta->setAttribute('xmlns', $this->config['afip_micdta_namespace']);
            
            $body->appendChild($registrarMicDta);

            // ✅ AGREGAR AUTENTICACIÓN EMPRESA según especificación AFIP
            $autenticacion = $this->createAutenticacionEmpresaMicDta($registrarMicDta);
            
            // ✅ AGREGAR PARÁMETROS MIC/DTA según especificación AFIP
            $parametros = $this->createMicDtaParam($registrarMicDta, $shipment, $transactionId);

            // Generar XML string
            $xmlString = $this->dom->saveXML();

            // ✅ VALIDAR XML ANTES DE RETORNAR
            $validation = $this->validateXmlStructure($xmlString);
            if (!$validation['is_valid']) {
                $this->logOperation('error', 'Error en validación XML', [
                    'errors' => $validation['errors'],
                ], 'xml_validation');
                throw new Exception('XML generado no válido: ' . implode(', ', $validation['errors']));
            }

            $this->logOperation('info', 'XML MIC/DTA creado exitosamente', [
                'xml_length' => strlen($xmlString),
                'transaction_id' => $transactionId,
                'namespace_used' => $this->config['afip_micdta_namespace'],
            ], 'xml_generation');

            return $xmlString;

        } catch (Exception $e) {
            $this->logOperation('error', 'Error creando XML MIC/DTA', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'shipment_id' => $shipment->id ?? 'N/A',
                'transaction_id' => $transactionId,
            ], 'xml_error');
            return null;
        }
    }

    private function createMicDtaParam(DOMElement $parent, Shipment $shipment, string $transactionId): DOMElement
    {
        $parametros = $this->createElement('argRegistrarMicDtaParam');
        $parent->appendChild($parametros);

        // ✅ ID de transacción (máximo 15 caracteres según especificación)
        $idTransaccion = $this->createElement('idTransaccion', substr($transactionId, 0, 15));
        $parametros->appendChild($idTransaccion);

        // ✅ Estructura MIC/DTA principal
        $micDta = $this->createElement('micDta');
        $parametros->appendChild($micDta);

        // ✅ Código de vía de transporte (8 = Hidrovía según EDIFACT 8067)
        $codViaTrans = $this->createElement('codViaTrans', '8');
        $micDta->appendChild($codViaTrans);

        // ✅ Agregar datos del transportista (obligatorio)
        $this->createTransportistaMicDta($micDta, $shipment);

        // ✅ Agregar datos del propietario del vehículo (obligatorio)
        $this->createPropietarioVehiculoMicDta($micDta, $shipment);

        // ✅ Indicador en lastre (S/N)
        $indEnLastre = $this->createElement('indEnLastre', $shipment->is_ballast ? 'S' : 'N');
        $micDta->appendChild($indEnLastre);

        // ✅ Datos de la embarcación (obligatorio)
        $this->createEmbarcacionMicDta($micDta, $shipment);

        return $parametros;
    }

    /**
     * ✅ MÉTODOS HELPER CORREGIDOS Y UNIFICADOS
     */

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
     * Crear elemento con escape XML seguro
     */
    private function createElement(string $name, string $value = null): DOMElement
    {
        if ($value !== null) {
            return $this->dom->createElement($name, htmlspecialchars($value, ENT_XML1, 'UTF-8'));
        }
        return $this->dom->createElement($name);
    }

    /**
     * ✅ CORRECCIÓN: Crear autenticación de empresa según especificación AFIP
     * NOTA: Método renombrado para coincidir con la implementación actual
     */
    private function createAutenticacionEmpresaMicDta(DOMElement $parent): DOMElement
    {
        $autenticacion = $this->createElement('argWSAutenticacionEmpresa');
        $parent->appendChild($autenticacion);

        // ✅ CUIT de empresa conectada (sin guiones, según especificación)
        $cuit = $this->createElement('CuitEmpresaConectada', $this->cleanTaxId($this->company->tax_id));
        $autenticacion->appendChild($cuit);

        // ✅ Tipo de agente (según documentación AFIP: "TRSP")
        $tipoAgente = $this->createElement('TipoAgente', 'TRSP');
        $autenticacion->appendChild($tipoAgente);

        // ✅ Rol de la empresa (según documentación AFIP: "TRSP")
        $rol = $this->createElement('Rol', 'TRSP');
        $autenticacion->appendChild($rol);

        return $autenticacion;
    }

    /**
     * ✅ Crear elemento viaje para MIC/DTA
     */
    private function createViajeMicDta(\DOMElement $parent, Shipment $shipment, string $transactionId): \DOMElement
    {
        $viaje = $this->createElement('viaje');
        $parent->appendChild($viaje);

        $voyage = $shipment->voyage;
        $vessel = $shipment->vessel ?? $voyage->leadVessel;

        // Número de viaje (obligatorio)
        $numeroViajeElement = $this->createElement('numeroViaje', $voyage->voyage_number);
        $viaje->appendChild($numeroViajeElement);

        // Fecha de salida (obligatorio)
        $fechaSalida = $voyage->departure_date ? 
            $voyage->departure_date->format('Y-m-d\TH:i:s') : 
            now()->format('Y-m-d\TH:i:s');
        $fechaSalidaElement = $this->createElement('fechaSalida', $fechaSalida);
        $viaje->appendChild($fechaSalidaElement);

        // Puerto origen (obligatorio)
        $puertoOrigenElement = $this->createElement('puertoOrigen', $voyage->originPort->code ?? 'ARBUE');
        $viaje->appendChild($puertoOrigenElement);

        // Puerto destino (obligatorio)
        $puertoDestinoElement = $this->createElement('puertoDestino', $voyage->destinationPort->code ?? 'PYTVT');
        $viaje->appendChild($puertoDestinoElement);

        // Datos de embarcación (obligatorios)
        $nombreEmbarcacionElement = $this->createElement('nombreEmbarcacion', $vessel->name ?? 'Unknown Vessel');
        $viaje->appendChild($nombreEmbarcacionElement);

        $tipoEmbarcacionElement = $this->createElement('tipoEmbarcacion', $vessel->vesselType->name ?? 'Barge');
        $viaje->appendChild($tipoEmbarcacionElement);

        $matriculaElement = $this->createElement('matriculaEmbarcacion', $vessel->registration_number ?? $vessel->name);
        $viaje->appendChild($matriculaElement);

        return $viaje;
    }

    /**
     * ✅ Crear elemento capitán para MIC/DTA
     */
    private function createCapitanMicDta(\DOMElement $parent, Shipment $shipment): \DOMElement
    {
        $capitan = $this->createElement('capitan');
        $parent->appendChild($capitan);

        $captain = $shipment->captain ?? $shipment->voyage->captain;

        if ($captain) {
            // Nombre y apellido (obligatorios)
            $nombreElement = $this->createElement('nombre', $captain->first_name ?? 'Default');
            $capitan->appendChild($nombreElement);

            $apellidoElement = $this->createElement('apellido', $captain->last_name ?? 'Captain');
            $capitan->appendChild($apellidoElement);

            // Documento (obligatorio)
            $documentoElement = $this->createElement('documento', $captain->document_number ?? '12345678');
            $capitan->appendChild($documentoElement);

            // Licencia (obligatorio)
            $licenciaElement = $this->createElement('licencia', $captain->license_number ?? 'CAP-001');
            $capitan->appendChild($licenciaElement);
        } else {
            // Valores por defecto si no hay capitán asignado
            $nombreElement = $this->createElement('nombre', 'Default');
            $capitan->appendChild($nombreElement);

            $apellidoElement = $this->createElement('apellido', 'Captain');
            $capitan->appendChild($apellidoElement);

            $documentoElement = $this->createElement('documento', '12345678');
            $capitan->appendChild($documentoElement);

            $licenciaElement = $this->createElement('licencia', 'CAP-001');
            $capitan->appendChild($licenciaElement);
        }

        return $capitan;
    }

    /**
     * ✅ Crear elementos contenedores para MIC/DTA
     */
    private function createContenedoresMicDta(\DOMElement $parent, Shipment $shipment): void
    {
        // Obtener contenedores reales del shipment
        $containers = collect();
        
        // Buscar contenedores a través de shipment items
        if ($shipment->shipmentItems) {
            foreach ($shipment->shipmentItems as $item) {
                if ($item->containers) {
                    $containers = $containers->concat($item->containers);
                }
            }
        }

        // Si no hay contenedores físicos, crear basado en datos del shipment
        if ($containers->isEmpty() && $shipment->containers_loaded > 0) {
            for ($i = 1; $i <= $shipment->containers_loaded; $i++) {
                $contenedor = $this->createElement('contenedor');
                $parent->appendChild($contenedor);

                $numeroElement = $this->createElement('numero', 'CONT' . str_pad($i, 6, '0', STR_PAD_LEFT));
                $contenedor->appendChild($numeroElement);

                $tipoElement = $this->createElement('tipo', '40HC'); // Tipo por defecto
                $contenedor->appendChild($tipoElement);

                $pesoElement = $this->createElement('peso', '25000'); // Peso estimado en kg
                $contenedor->appendChild($pesoElement);

                $estadoElement = $this->createElement('estado', 'LLENO');
                $contenedor->appendChild($estadoElement);
            }
        } else {
            // Usar contenedores reales
            foreach ($containers as $container) {
                $contenedor = $this->createElement('contenedor');
                $parent->appendChild($contenedor);

                $numeroElement = $this->createElement('numero', $container->container_number ?? 'UNKNOWN');
                $contenedor->appendChild($numeroElement);

                $tipoElement = $this->createElement('tipo', $container->container_type ?? '40HC');
                $contenedor->appendChild($tipoElement);

                $pesoElement = $this->createElement('peso', (string)($container->gross_weight ?? 25000));
                $contenedor->appendChild($pesoElement);

                $estadoElement = $this->createElement('estado', $container->status ?? 'LLENO');
                $contenedor->appendChild($estadoElement);
            }
        }
    }

    /**
     * ✅ Crear elemento carga para MIC/DTA
     */
    private function createCargaMicDta(\DOMElement $parent, Shipment $shipment): \DOMElement
    {
        $carga = $this->createElement('carga');
        $parent->appendChild($carga);

        // Peso total (obligatorio)
        $pesoTotal = $shipment->cargo_weight_loaded * 1000; // Convertir a kg
        $pesoTotalElement = $this->createElement('pesoTotal', (string)$pesoTotal);
        $carga->appendChild($pesoTotalElement);

        // Cantidad de contenedores (obligatorio)
        $cantidadElement = $this->createElement('cantidadContenedores', (string)$shipment->containers_loaded);
        $carga->appendChild($cantidadElement);

        // Descripción de carga
        $descripcionElement = $this->createElement('descripcion', $shipment->cargo_description ?? 'Carga general');
        $carga->appendChild($descripcionElement);

        // Tipo de mercadería
        $tipoMercaderiaElement = $this->createElement('tipoMercaderia', 'GENERAL');
        $carga->appendChild($tipoMercaderiaElement);

        return $carga;
    }

    /**
     * ✅ Validar shipment para MIC/DTA
     */
    private function validateShipmentForMicDta(Shipment $shipment): array
    {
        $validation = [
            'is_valid' => false,
            'errors' => [],
            'warnings' => [],
        ];

        // Validar shipment básico
        if (!$shipment || !$shipment->id) {
            $validation['errors'][] = 'Shipment no válido o no encontrado';
            return $validation;
        }

        // Validar voyage asociado
        if (!$shipment->voyage) {
            $validation['errors'][] = 'Shipment debe tener un voyage asociado';
        }

        // Validar vessel
        if (!$shipment->vessel && !$shipment->voyage?->leadVessel) {
            $validation['errors'][] = 'Shipment debe tener una embarcación asociada';
        }

        // Validar que pertenece a la empresa
        if ($shipment->voyage && $shipment->voyage->company_id !== $this->company->id) {
            $validation['errors'][] = 'Shipment no pertenece a la empresa actual';
        }

        $validation['is_valid'] = empty($validation['errors']);

        return $validation;
    }

    /**
     * ✅ NUEVO: Crear datos del transportista según especificación AFIP
     */
    private function createTransportistaMicDta(DOMElement $parent, Shipment $shipment): DOMElement
    {
        $transportista = $this->createElement('transportista');
        $parent->appendChild($transportista);

        // Nombre/Razón Social
        $nombre = $this->createElement('nombre', $this->company->legal_name ?? $this->company->name);
        $transportista->appendChild($nombre);

        // Domicilio
        $domicilio = $this->createElement('domicilio');
        $transportista->appendChild($domicilio);
        
        $nombreCalle = $this->createElement('nombreCalle', $this->company->address ?? 'Dirección no especificada');
        $domicilio->appendChild($nombreCalle);

        $ciudad = $this->createElement('ciudad', $this->company->city ?? 'Buenos Aires');
        $domicilio->appendChild($ciudad);

        // País (AR para Argentina)
        $codPais = $this->createElement('codPais', 'AR');
        $transportista->appendChild($codPais);

        // Identificación fiscal
        $idFiscal = $this->createElement('idFiscal', $this->cleanTaxId($this->company->tax_id));
        $transportista->appendChild($idFiscal);

        // Tipo de transportista (1 = Transportista marítimo/fluvial)
        $tipTrans = $this->createElement('tipTrans', '1');
        $transportista->appendChild($tipTrans);

        return $transportista;
    }

    /**
     * ✅ NUEVO: Crear datos del propietario del vehículo
     */
    private function createPropietarioVehiculoMicDta(DOMElement $parent, Shipment $shipment): DOMElement
    {
        $propVehiculo = $this->createElement('propVehiculo');
        $parent->appendChild($propVehiculo);

        // Por defecto, el propietario es la misma empresa
        $nombre = $this->createElement('nombre', $this->company->legal_name ?? $this->company->name);
        $propVehiculo->appendChild($nombre);

        // Domicilio
        $domicilio = $this->createElement('domicilio');
        $propVehiculo->appendChild($domicilio);
        
        $nombreCalle = $this->createElement('nombreCalle', $this->company->address ?? 'Dirección no especificada');
        $domicilio->appendChild($nombreCalle);

        // País
        $codPais = $this->createElement('codPais', 'AR');
        $propVehiculo->appendChild($codPais);

        // Identificación fiscal
        $idFiscal = $this->createElement('idFiscal', $this->cleanTaxId($this->company->tax_id));
        $propVehiculo->appendChild($idFiscal);

        return $propVehiculo;
    }

    /**
     * ✅ NUEVO: Crear datos de la embarcación según especificación AFIP
     */
    private function createEmbarcacionMicDta(DOMElement $parent, Shipment $shipment): DOMElement
    {
        $embarcacion = $this->createElement('embarcacion');
        $parent->appendChild($embarcacion);

        $vessel = $shipment->vessel ?? $shipment->voyage->leadVessel;

        // País de la embarcación
        $codPais = $this->createElement('codPais', $vessel->flag_country ?? 'AR');
        $embarcacion->appendChild($codPais);

        // ID de la embarcación (matrícula)
        $id = $this->createElement('id', $vessel->registration_number ?? $vessel->name ?? 'UNKNOWN');
        $embarcacion->appendChild($id);

        // Nombre de la embarcación
        $nombre = $this->createElement('nombre', $vessel->name ?? 'Vessel Unknown');
        $embarcacion->appendChild($nombre);

        // Tipo de embarcación (BAR = Barcaza, EMP = Empujador, BUM = Buque Motor)
        $tipEmb = $this->createElement('tipEmb', $this->mapVesselTypeToAfip($vessel->vesselType->name ?? 'barge'));
        $embarcacion->appendChild($tipEmb);

        // Indicador si integra convoy (S/N)
        $indIntegraConvoy = $this->createElement('indIntegraConvoy', 'N'); // Por defecto no
        $embarcacion->appendChild($indIntegraConvoy);

        return $embarcacion;
    }

    /**
     * ✅ HELPER: Mapear tipos de embarcación del sistema a códigos AFIP
     */
    private function mapVesselTypeToAfip(string $vesselType): string
    {
        $mapping = [
            'barge' => 'BAR',
            'tugboat' => 'EMP', 
            'pusher' => 'EMP',
            'self_propelled' => 'BUM',
            'motor_vessel' => 'BUM',
        ];

        return $mapping[strtolower($vesselType)] ?? 'BAR'; // Default: Barcaza
    }

    /**
     * ✅ HELPER: Limpiar CUIT/CUIL (quitar guiones y espacios)
     */
    private function cleanTaxId(string $taxId): string
    {
        return preg_replace('/[^0-9]/', '', $taxId);
    }

    /**
     * ✅ CORRECCIÓN: Validar estructura XML con namespace correcto
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
            
            // Capturar errores de parsing XML
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

            // ✅ VALIDAR NAMESPACE CORRECTO AFIP
            $registrarMicDta = $dom->getElementsByTagName('RegistrarMicDta');
            if ($registrarMicDta->length > 0) {
                $xmlns = $registrarMicDta->item(0)->getAttribute('xmlns');
                $expectedNamespace = $this->config['afip_micdta_namespace'];
                
                if ($xmlns !== $expectedNamespace) {
                    $validation['errors'][] = "Namespace incorrecto. Esperado: {$expectedNamespace}, Encontrado: {$xmlns}";
                }
            } else {
                $validation['errors'][] = 'Elemento RegistrarMicDta no encontrado';
            }

            $validation['is_valid'] = empty($validation['errors']);

            return $validation;

        } catch (Exception $e) {
            $validation['errors'][] = 'Error validando XML: ' . $e->getMessage();
            return $validation;
        }
    }

    /**
     * ✅ Método de logging con category requerida
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

    /**
     * ✅ MÉTODOS STUB TEMPORALES para otros tipos de XML
     */

    /**
     * Crear XML para Información Anticipada Argentina (stub temporal)
     */
    public function createAnticipatedXml(Voyage $voyage, string $transactionId): ?string
    {
        $this->logOperation('info', 'createAnticipatedXml llamado (STUB TEMPORAL)', [
            'voyage_id' => $voyage->id,
            'transaction_id' => $transactionId,
        ]);
        
        // TODO: Implementar XML para Información Anticipada
        return null;
    }

    /**
     * Crear XML para Paraguay (stub temporal)
     */
    public function createParaguayManifestXml(Voyage $voyage, string $transactionId): ?string
    {
        $this->logOperation('info', 'createParaguayManifestXml llamado (STUB TEMPORAL)', [
            'voyage_id' => $voyage->id,
            'transaction_id' => $transactionId,
        ]);
        
        // TODO: Implementar XML para Paraguay
        return null;
    }

    /**
     * Crear XML para Transbordo (stub temporal)
     */
    public function createTransshipmentXml(array $transshipmentData, string $transactionId): ?string
    {
        $this->logOperation('info', 'createTransshipmentXml llamado (STUB TEMPORAL)', [
            'transaction_id' => $transactionId,
            'barges_count' => count($transshipmentData['barge_data'] ?? []),
        ]);
        
        // TODO: Implementar XML para Transbordos
        return null;
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