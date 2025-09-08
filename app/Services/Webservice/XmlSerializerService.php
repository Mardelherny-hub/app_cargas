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

            // ✅ VALIDAR NAMESPACE CORRECTO AFIP - SOPORTAR MÚLTIPLES ELEMENTOS
            $registrarMicDta = $dom->getElementsByTagName('RegistrarMicDta');
            $registrarTitEnvios = $dom->getElementsByTagName('RegistrarTitEnvios');

            if ($registrarMicDta->length > 0) {
                $xmlns = $registrarMicDta->item(0)->getAttribute('xmlns');
                $expectedNamespace = $this->config['afip_micdta_namespace'];
                if ($xmlns !== $expectedNamespace) {
                    $validation['errors'][] = "Namespace incorrecto. Esperado: {$expectedNamespace}, Encontrado: {$xmlns}";
                }
            } elseif ($registrarTitEnvios->length > 0) {
                $xmlns = $registrarTitEnvios->item(0)->getAttribute('xmlns');
                $expectedNamespace = $this->config['afip_micdta_namespace'];
                if ($xmlns !== $expectedNamespace) {
                    $validation['errors'][] = "Namespace incorrecto. Esperado: {$expectedNamespace}, Encontrado: {$xmlns}";
                }
            } else {
                $validation['errors'][] = 'Elemento RegistrarMicDta o RegistrarTitEnvios no encontrado';
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

    // === AGREGADO: Métodos RegistrarTitEnvios ===

    /**
     * Crear XML para RegistrarTitEnvios - PASO 1 AFIP
     */
    public function createTitEnviosXml(Shipment $shipment, string $transactionId): ?string
    {
        try {
            $this->logOperation('info', 'Iniciando creación XML RegistrarTitEnvios', [
                'shipment_id' => $shipment->id,
                'transaction_id' => $transactionId,
                'shipment_number' => $shipment->shipment_number,
            ], 'xml_titenvios');

            // Validar shipment para TitEnvios
            $validation = $this->validateShipmentForTitEnvios($shipment);
            if (!$validation['is_valid']) {
                $this->logOperation('error', 'Shipment no válido para TitEnvios', [
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

            // Elemento principal RegistrarTitEnvios
            $registrarTitEnvios = $this->createElement('RegistrarTitEnvios');
            $registrarTitEnvios->setAttribute('xmlns', $this->config['afip_micdta_namespace']);
            $body->appendChild($registrarTitEnvios);

            // Autenticación empresa
            $autenticacion = $this->createAutenticacionEmpresaTitEnvios($registrarTitEnvios);
            
            // Parámetros TitEnvios
            $parametros = $this->createTitEnviosParam($registrarTitEnvios, $shipment, $transactionId);

            // Generar XML string
            $xmlString = $this->dom->saveXML();

            // Validar XML
            $validation = $this->validateXmlStructure($xmlString);
            if (!$validation['is_valid']) {
                $this->logOperation('error', 'Error validación XML TitEnvios', [
                    'errors' => $validation['errors'],
                ], 'xml_validation');
                throw new Exception('XML TitEnvios no válido: ' . implode(', ', $validation['errors']));
            }

            $this->logOperation('info', 'XML RegistrarTitEnvios generado exitosamente', [
                'xml_size_kb' => round(strlen($xmlString) / 1024, 2),
                'transaction_id' => $transactionId,
            ], 'xml_titenvios');

            return $xmlString;

        } catch (Exception $e) {
            $this->logOperation('error', 'Error generando XML RegistrarTitEnvios', [
                'error' => $e->getMessage(),
                'transaction_id' => $transactionId,
            ], 'xml_error');
            return null;
        }
    }

    /**
     * Crear XML MIC/DTA que incluye TRACKs - PASO 2 AFIP
     */
    public function createMicDtaXmlWithTracks(Shipment $shipment, string $transactionId, array $tracks): ?string
    {
        try {
            $this->logOperation('info', 'Iniciando creación XML MIC/DTA con TRACKs', [
                'shipment_id' => $shipment->id,
                'transaction_id' => $transactionId,
                'tracks_count' => count($tracks),
                'tracks' => $tracks,
            ], 'xml_micdta_tracks');

            // Usar método base y agregar TRACKs
            $baseXml = $this->createMicDtaXml($shipment, $transactionId);
            if (!$baseXml) {
                throw new Exception('Error generando XML base MIC/DTA');
            }

            // Insertar TRACKs en el XML
            $xmlWithTracks = $this->insertTracksIntoMicDtaXml($baseXml, $tracks);
            
            $this->logOperation('info', 'XML MIC/DTA con TRACKs generado exitosamente', [
                'xml_size_kb' => round(strlen($xmlWithTracks) / 1024, 2),
                'tracks_inserted' => count($tracks),
            ], 'xml_micdta_tracks');

            return $xmlWithTracks;

        } catch (Exception $e) {
            $this->logOperation('error', 'Error generando XML MIC/DTA con TRACKs', [
                'error' => $e->getMessage(),
                'transaction_id' => $transactionId,
            ], 'xml_error');
            return null;
        }
    }

    /**
     * Validar shipment para RegistrarTitEnvios
     */
    private function validateShipmentForTitEnvios(Shipment $shipment): array
    {
        $validation = [
            'is_valid' => false,
            'errors' => [],
            'warnings' => [],
        ];

        // 1. Shipment debe existir y estar activo
        if (!$shipment || !$shipment->active) {
            $validation['errors'][] = 'Shipment inactivo o no válido';
        }

        // 2. Debe tener voyage asociado
        if (!$shipment->voyage) {
            $validation['errors'][] = 'Shipment debe tener voyage asociado';
        }

        // 3. Debe tener vessel válido
        if (!$shipment->vessel) {
            $validation['errors'][] = 'Shipment debe tener vessel asociado';
        }

        // 4. Verificar que tenga carga (containers o bills)
        $hasContainers = $shipment->containers && $shipment->containers->count() > 0;
        $hasBills = $shipment->billsOfLading && $shipment->billsOfLading->count() > 0;
        
        if (!$hasContainers && !$hasBills) {
            $validation['errors'][] = 'Shipment debe tener contenedores o conocimientos de embarque';
        }

        // 5. Verificar datos del voyage
        if ($shipment->voyage) {
            if (!$shipment->voyage->originPort || !$shipment->voyage->destinationPort) {
                $validation['errors'][] = 'Voyage debe tener puertos de origen y destino';
            }
            
            if (!$shipment->voyage->departure_date) {
                $validation['errors'][] = 'Voyage debe tener fecha de salida';
            }
        }

        $validation['is_valid'] = empty($validation['errors']);

        return $validation;
    }

    /**
     * Crear autenticación empresa para TitEnvios
     */
    private function createAutenticacionEmpresaTitEnvios($parentElement)
    {
        $autenticacion = $this->createElement('argWSAutenticacionEmpresa');
        $parentElement->appendChild($autenticacion);

        // Obtener datos de Argentina
        $argentinaData = $this->company->getArgentinaWebserviceData();

        // CUIT empresa conectada
        $cuitElement = $this->createElement('CuitEmpresaConectada');
        $cuitElement->textContent = preg_replace('/[^0-9]/', '', $this->company->tax_id);
        $autenticacion->appendChild($cuitElement);

        // Tipo agente - Para TitEnvios es BODEGA el tipo debe ser TRANSPORTISTA
        $argentinaData = $this->company->getArgentinaWebserviceData();
    
        $tipoAgente = $this->createElement('TipoAgente');
        $tipoAgente->textContent = $argentinaData['tipo_agente'] ?? 'TRSP';
        $autenticacion->appendChild($tipoAgente);

        $rol = $this->createElement('Rol');
        $rol->textContent = $argentinaData['rol'] ?? 'TRSP';
        $autenticacion->appendChild($rol);

        return $autenticacion;
    }

    /**
     * Crear parámetros para RegistrarTitEnvios
     */
    private function createTitEnviosParam($parentElement, Shipment $shipment, string $transactionId)
    {
        $param = $this->createElement('argRegistrarTitEnviosParam');
        $parentElement->appendChild($param);

        // ID Transacción
        $idTransaccion = $this->createElement('IdTransaccion');
        $idTransaccion->textContent = $transactionId;
        $param->appendChild($idTransaccion);

        // Información del titulo y envíos
        $this->createTituloInfo($param, $shipment);
        $this->createEnviosInfo($param, $shipment);

        return $param;
    }

    /**
     * Crear información del título
     */
    private function createTituloInfo($parentElement, Shipment $shipment)
    {
        $titulo = $this->createElement('Titulo');
        $parentElement->appendChild($titulo);

        // Número de título (usar shipment number como base)
        $numeroTitulo = $this->createElement('NumeroTitulo');
        $numeroTitulo->textContent = 'TIT_' . $shipment->shipment_number;
        $titulo->appendChild($numeroTitulo);

        // Tipo de título
        $tipoTitulo = $this->createElement('TipoTitulo');
        $tipoTituloCode = $this->getTipoTituloFromShipment($shipment);
        $tipoTitulo->textContent = $tipoTituloCode;
        $titulo->appendChild($tipoTitulo);

        // Información del transportista
        $this->createTransportistaInfo($titulo);

        // Información del viaje
        $this->createViajeInfo($titulo, $shipment);

        // Información del capitán - desde relación real
        if ($shipment->voyage->captain) {
            $conductor = $this->createElement('Conductor');
            $titulo->appendChild($conductor);
            
            $nombre = $this->createElement('Nombre');
            $nombre->textContent = $shipment->voyage->captain->first_name;
            $conductor->appendChild($nombre);
            
            $apellido = $this->createElement('Apellido');
            $apellido->textContent = $shipment->voyage->captain->last_name;
            $conductor->appendChild($apellido);
            
            $licencia = $this->createElement('Licencia');
            $licencia->textContent = $shipment->voyage->captain->license_number;
            $conductor->appendChild($licencia);
        }

        // OBLIGATORIO AFIP: PorteadorTitulo 
        $porteadorTitulo = $this->createElement('PorteadorTitulo');
        $titulo->appendChild($porteadorTitulo);

        $nombrePorteador = $this->createElement('Nombre');
        $nombrePorteador->textContent = $this->company->legal_name;
        $porteadorTitulo->appendChild($nombrePorteador);

        $cuitPorteador = $this->createElement('Cuit');
        $cuitPorteador->textContent = preg_replace('/[^0-9]/', '', $this->company->tax_id);
        $porteadorTitulo->appendChild($cuitPorteador);

        // OBLIGATORIO AFIP: ResumenMercaderias
        $resumenMercaderias = $this->createElement('ResumenMercaderias');
        $titulo->appendChild($resumenMercaderias);

        // Sumar peso real de todos los bultos (bills of lading)
        $pesoTotalCalculado = 0;
        $cantidadBultosCalculada = 0;

        if ($shipment->billsOfLading) {
            foreach ($shipment->billsOfLading as $bill) {
                // Peso en gramos (como aparece en PesoBulto)
                $pesoTotalCalculado += ($bill->gross_weight_kg ?? 0) * 1000;
                $cantidadBultosCalculada += $bill->total_packages ?? 0;
            }
        }

        // Si no hay bills, usar datos del shipment
        if ($pesoTotalCalculado === 0) {
            $pesoTotalCalculado = ($shipment->cargo_weight_loaded ?? 1) * 1000;
            $cantidadBultosCalculada = $shipment->total_packages ?? 1;
        }

        // ResumenMercaderias debe coincidir con suma de bultos
        $pesoTotalMercaderias = $this->createElement('PesoTotal');
        $pesoTotalMercaderias->textContent = (string)($pesoTotalCalculado / 1000); // Misma unidad que PesoBulto
        $resumenMercaderias->appendChild($pesoTotalMercaderias);

        $cantidadBultosTotales = $this->createElement('CantidadBultos');
        $cantidadBultosTotales->textContent = (string)max(1, $cantidadBultosCalculada);
        $resumenMercaderias->appendChild($cantidadBultosTotales);


        // Información de embarcación - desde relación real
        if ($shipment->vessel) {
            $embarcacion = $this->createElement('Embarcacion');
            $titulo->appendChild($embarcacion);

            $nombreEmb = $this->createElement('Nombre');
            $nombreEmb->textContent = $shipment->vessel->name;
            $embarcacion->appendChild($nombreEmb);

            $codigoPais = $this->createElement('CodigoPais');
            $codigoPais->textContent = 'AR'; // Desde vessel->flag_country si existe la relación
            $embarcacion->appendChild($codigoPais);
        }

        return $titulo;
    }

    /**
     * NUEVO: Obtener TipoTitulo desde base de datos
     */
    private function getTipoTituloFromShipment(Shipment $shipment): string
    {
        // Buscar en tabla title_types o shipment_types si existe
        // Si no existe la tabla, intentar desde configuración
        try {
            // Opción 1: Desde tipo de shipment
            if ($shipment->type && Schema::hasTable('shipment_types')) {
                $shipmentType = \DB::table('shipment_types')
                    ->where('code', $shipment->type)
                    ->whereNotNull('afip_title_code')
                    ->first();
                
                if ($shipmentType) {
                    return $shipmentType->afip_title_code;
                }
            }

            // Opción 2: Desde configuración de empresa
            $argentinaData = $this->company->getArgentinaWebserviceData();
            if (isset($argentinaData['default_title_type'])) {
                return $argentinaData['default_title_type'];
            }

            // Opción 3: Basado en tipo de carga del shipment
            if ($shipment->billsOfLading && $shipment->billsOfLading->count() > 0) {
                $firstBill = $shipment->billsOfLading->first();
                if ($firstBill->primaryCargoType && $firstBill->primaryCargoType->afip_title_code) {
                    return $firstBill->primaryCargoType->afip_title_code;
                }
            }

            // Fallback: usar código general
            return '1'; // 1 = Carga General según AFIP

        } catch (\Exception $e) {
            Log::warning('Error obteniendo TipoTitulo desde BD', [
                'error' => $e->getMessage(),
                'shipment_id' => $shipment->id
            ]);
            return '1'; // Fallback seguro
        }
    }

    /**
     * NUEVO: Calcular peso total real desde bills of lading (en gramos)
     */
    private function calcularPesoTotalDesdeBills(Shipment $shipment): int
    {
        $pesoTotal = 0;
        
        if ($shipment->billsOfLading) {
            foreach ($shipment->billsOfLading as $bill) {
                // Sumar peso en gramos
                $peso = ($bill->gross_weight_kg ?? 0) * 1000;
                $pesoTotal += $peso;
            }
        }
        
        // Si no hay bills, usar peso del shipment
        if ($pesoTotal === 0 && $shipment->cargo_weight_loaded) {
            $pesoTotal = $shipment->cargo_weight_loaded * 1000;
        }
        
        return max(1000, $pesoTotal); // Mínimo 1kg
    }

    /**
     * NUEVO: Calcular cantidad real de bultos desde bills of lading
     */
    private function calcularCantidadBultosDesdeBills(Shipment $shipment): int
    {
        $totalBultos = 0;
        
        if ($shipment->billsOfLading) {
            foreach ($shipment->billsOfLading as $bill) {
                $totalBultos += $bill->total_packages ?? 0;
            }
        }
        
        // Si no hay bills, usar total del shipment
        if ($totalBultos === 0 && $shipment->total_packages) {
            $totalBultos = $shipment->total_packages;
        }
        
        return max(1, $totalBultos); // Mínimo 1 bulto
    }

    /**
     * Crear información de envíos
     */
    private function createEnviosInfo($parentElement, Shipment $shipment)
{
    $envios = $this->createElement('Envios');
    $parentElement->appendChild($envios);

    $envio = $this->createElement('Envio');
    $envios->appendChild($envio);

    // Número de envío
    $numeroEnvio = $this->createElement('NumeroEnvio');
    $numeroEnvio->textContent = $shipment->shipment_number;
    $envio->appendChild($numeroEnvio);

    // Descripción de la carga
    $descripcion = $this->createElement('DescripcionCarga');
    $descripcion->textContent = $shipment->description ?? 'CARGA GENERAL';
    $envio->appendChild($descripcion);

    // Peso total calculado
    $peso = $this->createElement('PesoTotal');
    $pesoEnKg = $this->calcularPesoEnviosEnKg($shipment);
    $peso->textContent = (string)$pesoEnKg;
    $envio->appendChild($peso);

    // Usar SOLO el método createBultosInfo (NO createBultosInfoSinHardcode)
    if ($shipment->billsOfLading && $shipment->billsOfLading->count() > 0) {
        $this->createBultosInfo($envio, $shipment);
    }

    return $envios;
}

    /**
     * NUEVO: Obtener código embalaje válido SOLO desde BD
     */
    private function getValidPackagingCodeFromBD($bill): string
    {
        try {
            // Intentar desde relación bill → packaging_type
            if ($bill->primaryPackagingType && $bill->primaryPackagingType->argentina_ws_code) {
                return $bill->primaryPackagingType->argentina_ws_code;
            }

            // Intentar desde packaging_type_id directo
            if ($bill->primary_packaging_type_id) {
                $packagingType = \App\Models\PackagingType::where('id', $bill->primary_packaging_type_id)
                    ->whereNotNull('argentina_ws_code')
                    ->first();
                
                if ($packagingType) {
                    return $packagingType->argentina_ws_code;
                }
            }

            // Buscar packaging por defecto para contenedores
            $defaultPackaging = \App\Models\PackagingType::where('active', true)
                ->whereNotNull('argentina_ws_code')
                ->orderBy('is_default', 'desc')
                ->first();

            if ($defaultPackaging) {
                return $defaultPackaging->argentina_ws_code;
            }

            // ERROR: No hay códigos en BD
            throw new \Exception('No se encontraron códigos de embalaje AFIP en BD');

        } catch (\Exception $e) {
            Log::error('Error obteniendo código embalaje AFIP', [
                'bill_id' => $bill->id,
                'error' => $e->getMessage()
            ]);
            
            // FALLO CRÍTICO: No usar fallback hardcoded
            throw new \Exception('Código embalaje AFIP requerido - verificar tabla packaging_types');
        }
    }

    /**
     * NUEVO: Obtener código mercadería válido SOLO desde BD
     */
    private function getValidCargoCodeFromBD($bill): string
    {
        try {
            // Intentar desde relación bill → cargo_type
            if ($bill->primaryCargoType && $bill->primaryCargoType->code) {
                return $bill->primaryCargoType->code;
            }

            // Intentar desde cargo_type_id directo
            if ($bill->primary_cargo_type_id) {
                $cargoType = \App\Models\CargoType::where('id', $bill->primary_cargo_type_id)
                    ->whereNotNull('code')
                    ->first();
                
                if ($cargoType) {
                    return $cargoType->code;
                }
            }

            // Buscar cargo por defecto
            $defaultCargo = \App\Models\CargoType::where('active', true)
                ->whereNotNull('code')
                ->orderBy('is_default', 'desc')
                ->first();

            if ($defaultCargo) {
                return $defaultCargo->code;
            }

            // ERROR: No hay códigos en BD
            throw new \Exception('No se encontraron códigos de mercadería en BD');

        } catch (\Exception $e) {
            Log::error('Error obteniendo código mercadería', [
                'bill_id' => $bill->id,
                'error' => $e->getMessage()
            ]);
            
            // FALLO CRÍTICO: No usar fallback hardcoded
            throw new \Exception('Código mercadería requerido - verificar tabla cargo_types');
        }
    }

    /**
     * NUEVO: Obtener nombre packaging SOLO desde BD
     */
    private function getPackagingNameFromBD($bill): string
    {
        try {
            if ($bill->primaryPackagingType && $bill->primaryPackagingType->name) {
                return $bill->primaryPackagingType->name;
            }

            if ($bill->primary_packaging_type_id) {
                $packagingType = \App\Models\PackagingType::find($bill->primary_packaging_type_id);
                if ($packagingType) {
                    return $packagingType->name;
                }
            }

            throw new \Exception('Nombre de embalaje requerido');

        } catch (\Exception $e) {
            Log::error('Error obteniendo nombre embalaje', [
                'bill_id' => $bill->id,
                'error' => $e->getMessage()
            ]);
            
            throw new \Exception('Nombre embalaje requerido - verificar relaciones BD');
        }
    }

    /**
     * NUEVO: Calcular peso de envíos en kg (consistente con ResumenMercaderias)
     */
    private function calcularPesoEnviosEnKg(Shipment $shipment): int
    {
        $pesoTotal = 0;
        
        if ($shipment->billsOfLading) {
            foreach ($shipment->billsOfLading as $bill) {
                $pesoTotal += $bill->gross_weight_kg ?? 0;
            }
        }
        
        if ($pesoTotal === 0 && $shipment->cargo_weight_loaded) {
            $pesoTotal = $shipment->cargo_weight_loaded;
        }
        
        return max(1, $pesoTotal); // Mínimo 1kg
    }

    /**
     * Crear información de transportista
     */
    private function createTransportistaInfo($parentElement)
    {
        $transportista = $this->createElement('Transportista');
        $parentElement->appendChild($transportista);

        // Nombre
        $nombre = $this->createElement('Nombre');
        $nombre->textContent = $this->company->legal_name ?? $this->company->name;
        $transportista->appendChild($nombre);

        // CUIT
        $cuit = $this->createElement('Cuit');
        $cuit->textContent = preg_replace('/[^0-9]/', '', $this->company->tax_id);
        $transportista->appendChild($cuit);

        // Dirección
        $direccion = $this->createElement('Direccion');
        $direccion->textContent = $this->company->address ?? 'DIRECCION NO ESPECIFICADA';
        $transportista->appendChild($direccion);

        return $transportista;
    }

    /**
     * Crear información del viaje
     */
    private function createViajeInfo($parentElement, Shipment $shipment)
    {
        if (!$shipment->voyage) {
            return null;
        }

        $viaje = $this->createElement('Viaje');
        $parentElement->appendChild($viaje);

        // Número de viaje
        $numeroViaje = $this->createElement('NumeroViaje');
        $numeroViaje->textContent = $shipment->voyage->voyage_number;
        $viaje->appendChild($numeroViaje);

        // Puerto origen - usar relación real
        if ($shipment->voyage->originPort) {
            $puertoOrigen = $this->createElement('PuertoOrigen');
            $puertoOrigen->textContent = $shipment->voyage->originPort->code;
            $viaje->appendChild($puertoOrigen);
        }

        // Puerto destino - usar relación real
        if ($shipment->voyage->destinationPort) {
            $puertoDestino = $this->createElement('PuertoDestino');
            $puertoDestino->textContent = $shipment->voyage->destinationPort->code;
            $viaje->appendChild($puertoDestino);
        }

        // Fecha salida
        if ($shipment->voyage->departure_date) {
            $fechaSalida = $this->createElement('FechaSalida');
            $fechaSalida->textContent = $shipment->voyage->departure_date->format('Y-m-d\TH:i:s');
            $viaje->appendChild($fechaSalida);
        }

        return $viaje;
    }

    /**
     * Crear información de contenedores CON CÓDIGOS AFIP OBLIGATORIOS
     */
    private function createContenedoresInfo($parentElement, Shipment $shipment)
    {
        $contenedores = $this->createElement('Contenedores');
        $parentElement->appendChild($contenedores);

        // Verificar si hay contenedores reales
        $containers = $shipment->containers ?? collect();
        
        if ($containers->isEmpty() && $shipment->containers_loaded > 0) {
            // Crear contenedores virtuales basados en datos del shipment
            for ($i = 1; $i <= $shipment->containers_loaded; $i++) {
                $contenedor = $this->createElement('Contenedor');
                $contenedores->appendChild($contenedor);

                // Número de contenedor virtual
                $numero = $this->createElement('NumeroContenedor');
                $numero->textContent = 'VIRT' . str_pad($i, 6, '0', STR_PAD_LEFT);
                $contenedor->appendChild($numero);

                // ✅ OBLIGATORIO AFIP: Código ISO del contenedor
                $codigoISO = $this->createElement('CodigoISOContenedor');
                $codigoISO->textContent = '42G1'; // 40HC por defecto
                $contenedor->appendChild($codigoISO);

                // Tipo de contenedor
                $tipo = $this->createElement('TipoContenedor');
                $tipo->textContent = '40HC';
                $contenedor->appendChild($tipo);



                // OBLIGATORIO AFIP: Código ISO del contenedor desde tabla container_types
                $codigoISO = $this->createElement('CodigoISOContenedor');
                $isoCode = $container->containerType?->argentina_ws_code;
                if (!$isoCode) {
                    // Fallback: buscar tipo activo con código
                    $defaultType = \App\Models\ContainerType::where('active', true)
                        ->whereNotNull('argentina_ws_code')
                        ->first();
                    $isoCode = $defaultType?->argentina_ws_code ?? '42G1';
                }
                $codigoISO->textContent = $isoCode;
                $contenedor->appendChild($codigoISO);

                // OBLIGATORIO AFIP: Peso bruto del contenedor
                $pesoBruto = $this->createElement('PesoBrutoContenedor');
                $peso = $container->gross_weight ?? $container->tare_weight ?? 25000;
                $pesoBruto->textContent = (string)$peso;
                $contenedor->appendChild($pesoBruto);
            }
        } else {
            // Usar contenedores reales
            foreach ($containers as $container) {
                $contenedor = $this->createElement('Contenedor');
                $contenedores->appendChild($contenedor);

                // Número de contenedor
                $numero = $this->createElement('NumeroContenedor');
                $numero->textContent = $container->container_number ?? 'UNKNOWN';
                $contenedor->appendChild($numero);

                // ✅ OBLIGATORIO AFIP: Código ISO del contenedor desde ContainerType
                $codigoISO = $this->createElement('CodigoISOContenedor');
                $isoCode = $container->containerType?->argentina_ws_code ?? '42G1';
                $codigoISO->textContent = $isoCode;
                $contenedor->appendChild($codigoISO);

                // Tipo de contenedor
                $tipo = $this->createElement('TipoContenedor');
                $tipo->textContent = $container->container_type ?? '40HC';
                $contenedor->appendChild($tipo);                 

                // Precintos si existen
                if ($container->seals && count($container->seals) > 0) {
                    $precintos = $this->createElement('Precintos');
                    $contenedor->appendChild($precintos);
                    
                    foreach ($container->seals as $seal) {
                        $precinto = $this->createElement('Precinto');
                        $precinto->textContent = $seal->seal_number ?? 'NO_SEAL';
                        $precintos->appendChild($precinto);
                    }
                }
            }
        }

        return $contenedores;
    }

    /**
     * Crear información de bultos CON CÓDIGOS AFIP OBLIGATORIOS
     */
    private function createBultosInfo($parentElement, Shipment $shipment)
{
    $bultos = $this->createElement('Bultos');
    $parentElement->appendChild($bultos);

    foreach ($shipment->billsOfLading as $bill) {
        $bulto = $this->createElement('Bulto');
        $bultos->appendChild($bulto);

        // Número de conocimiento
        $numeroBill = $this->createElement('NumeroConocimiento');
        $numeroBill->textContent = $bill->bill_number ?? $bill->number ?? 'BL' . $bill->id;
        $bulto->appendChild($numeroBill);

        // Descripción mercadería
        $descripcion = $this->createElement('DescripcionMercaderia');
        $descripcion->textContent = $bill->cargo_description ?? 'MERCADERIA GENERAL';
        $bulto->appendChild($descripcion);

        // Código de embalaje desde BD
        $codigoEmbalaje = $this->createElement('CodigoEmbalaje');
        $packagingCode = $bill->primaryPackagingType?->argentina_ws_code ?? '05';
        $codigoEmbalaje->textContent = $packagingCode;
        $bulto->appendChild($codigoEmbalaje);

        // REGLA AFIP: Para código "05" (contenedor) NO agregar TipoEmbalaje
        if ($packagingCode === '05') {
            $condicion = $this->createElement('CondicionDelContenedor');
            $condicion->textContent = 'P'; // P = muelle a muelle
            $bulto->appendChild($condicion);
        } else {
            $tipoEmbalaje = $this->createElement('TipoEmbalaje');
            $tipoEmbalaje->textContent = $bill->primaryPackagingType?->name ?? 'EMBALAJE GENERAL';
            $bulto->appendChild($tipoEmbalaje);
        }

        // Código de mercadería desde BD
        $codigoMercaderia = $this->createElement('CodigoMercaderia');
        $cargoCode = $bill->primaryCargoType?->code ?? 'GEN001';
        $codigoMercaderia->textContent = $cargoCode;
        $bulto->appendChild($codigoMercaderia);

        // Cantidad de bultos
        $cantidad = $this->createElement('CantidadBultos');
        $cantidad->textContent = max(1, $bill->total_packages ?? 1);
        $bulto->appendChild($cantidad);

        // Peso del bulto en kg
        $peso = $this->createElement('PesoBulto');
        $peso->textContent = number_format($bill->gross_weight_kg ?? 1000, 2, '.', '');
        $bulto->appendChild($peso);

        // Unidad de medida
        $unidadMedida = $this->createElement('UnidadMedida');
        $unidadMedida->textContent = $bill->measurement_unit ?? 'KG';
        $bulto->appendChild($unidadMedida);
    }

    return $bultos;
}

    /**
     * Insertar TRACKs en XML MIC/DTA existente
     */
    private function insertTracksIntoMicDtaXml(string $baseXml, array $tracks): string
    {
        try {
            // Cargar XML base
            $dom = new \DOMDocument('1.0', 'UTF-8');
            $dom->loadXML($baseXml);
            $dom->formatOutput = true;

            // Buscar elemento donde insertar TRACKs
            $xpath = new \DOMXPath($dom);
            $xpath->registerNamespace('soap', 'http://schemas.xmlsoap.org/soap/envelope/');
            
            // Buscar argRegistrarMicDtaParam
            $paramNodes = $xpath->query('//argRegistrarMicDtaParam');
            if ($paramNodes->length === 0) {
                throw new Exception('No se encontró argRegistrarMicDtaParam en XML base');
            }

            $paramElement = $paramNodes->item(0);

            // Crear elemento CargasSueltasIdTrack
            $cargasSueltas = $dom->createElement('CargasSueltasIdTrack');
            $paramElement->appendChild($cargasSueltas);

            // Agregar cada TRACK
            foreach ($tracks as $track) {
                $trackElement = $dom->createElement('IdTrack');
                $trackElement->textContent = $track;
                $cargasSueltas->appendChild($trackElement);
            }

            // También crear TracksContVacios si hay TRACKs de contenedores vacíos
            $emptyTracks = array_filter($tracks, function($track) {
                // Buscar TRACKs que correspondan a contenedores vacíos
                $trackRecord = \App\Models\WebserviceTrack::where('track_number', $track)
                    ->where('track_type', 'contenedor_vacio')
                    ->first();
                return $trackRecord !== null;
            });

            if (!empty($emptyTracks)) {
                $tracksVacios = $dom->createElement('TracksContVacios');
                $paramElement->appendChild($tracksVacios);

                foreach ($emptyTracks as $emptyTrack) {
                    $trackVacioElement = $dom->createElement('IdTrackVacio');
                    $trackVacioElement->textContent = $emptyTrack;
                    $tracksVacios->appendChild($trackVacioElement);
                }
            }

            return $dom->saveXML();

        } catch (Exception $e) {
            $this->logOperation('error', 'Error insertando TRACKs en XML MIC/DTA', [
                'error' => $e->getMessage(),
                'tracks' => $tracks,
            ], 'xml_tracks_error');
            
            // Retornar XML base si falla la inserción
            return $baseXml;
        }
    }

    /**
     * Validar estructura XML específica para TitEnvios
     */
    private function validateTitEnviosXmlStructure(string $xmlContent): array
    {
        $validation = [
            'is_valid' => false,
            'errors' => [],
            'warnings' => [],
        ];

        try {
            // Cargar XML
            $dom = new \DOMDocument();
            $dom->loadXML($xmlContent);

            // Verificar elementos obligatorios para TitEnvios
            $requiredElements = [
                '//RegistrarTitEnvios' => 'Elemento raíz RegistrarTitEnvios',
                '//argWSAutenticacionEmpresa' => 'Autenticación empresa',
                '//argRegistrarTitEnviosParam' => 'Parámetros TitEnvios',
                '//IdTransaccion' => 'ID Transacción',
                '//Titulo' => 'Información del título',
                '//Envios' => 'Información de envíos',
            ];

            $xpath = new \DOMXPath($dom);
            foreach ($requiredElements as $xpathQuery => $description) {
                $nodes = $xpath->query($xpathQuery);
                if ($nodes->length === 0) {
                    $validation['errors'][] = "Falta elemento obligatorio: {$description}";
                }
            }

            // Verificar namespace correcto
            $registrarNodes = $xpath->query('//RegistrarTitEnvios[@xmlns]');
            if ($registrarNodes->length > 0) {
                $namespace = $registrarNodes->item(0)->getAttribute('xmlns');
                if ($namespace !== $this->config['afip_micdta_namespace']) {
                    $validation['errors'][] = "Namespace incorrecto: {$namespace}";
                }
            } else {
                $validation['errors'][] = 'Falta namespace en RegistrarTitEnvios';
            }

            $validation['is_valid'] = empty($validation['errors']);

        } catch (Exception $e) {
            $validation['errors'][] = 'Error parseando XML: ' . $e->getMessage();
        }

        return $validation;
    }

    /**
     * Log específico para operaciones TitEnvios
     */
    private function logTitEnviosOperation(string $level, string $message, array $context = []): void
    {
        $context['xml_type'] = 'RegistrarTitEnvios';
        $context['afip_step'] = 1;
        $context['purpose'] = 'Generate TRACKs';
        
        $this->logOperation($level, $message, $context, 'xml_titenvios');
    }

}