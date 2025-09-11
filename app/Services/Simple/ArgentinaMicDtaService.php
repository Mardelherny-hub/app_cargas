<?php

namespace App\Services\Simple;

use App\Models\Voyage;
use App\Models\WebserviceTrack;
use App\Services\Simple\BaseWebserviceService;
use Exception;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * SISTEMA MODULAR WEBSERVICES - ArgentinaMicDtaService
 * 
 * Servicio específico para MIC/DTA Argentina AFIP.
 * Hereda funcionalidad común de BaseWebserviceService.
 * 
 * FLUJO SECUENCIAL AFIP OBLIGATORIO:
 * 1. RegistrarTitEnvios (genera TRACKs por shipment)
 * 2. RegistrarMicDta (usa TRACKs del paso 1)
 * 
 * INTEGRACIÓN CON SISTEMA EXISTENTE:
 * - Usa servicios base: CertificateManager, SoapClient, XmlSerializer
 * - Compatible con datos reales: MAERSK, PAR13001, V022NB
 * - Gestión WebserviceTrack para TRACKs de AFIP
 * - Sistema de logging unificado
 * 
 * DATOS AFIP REQUERIDOS:
 * - CUIT empresa (company.tax_id)
 * - Identificador viaje único (voyage.voyage_number + fecha)
 * - Datos embarcación (vessel.name, vessel.registration)
 * - Puertos origen/destino con códigos AFIP
 * - Shipments con conocimientos de embarque
 * - Contenedores con tipos normalizados
 */
class ArgentinaMicDtaService extends BaseWebserviceService
{
    /**
     * Configuración específica MIC/DTA Argentina
     */
    protected function getWebserviceConfig(): array
    {
        return [
            'webservice_type' => 'micdta',
            'country' => 'AR',
            'environment' => 'testing', // testing | production
            'webservice_url' => 'https://fwshomo.afip.gov.ar/wgesregsintia2/RegSinTIA2Service.svc',
            'soap_action' => 'Ar.Gob.Afip.Dga.wgesregsintia2/RegistrarMicDta',
            'titenvios_soap_action' => 'Ar.Gob.Afip.Dga.wgesregsintia2/RegistrarTitEnvios',
            'requires_tracks' => true,
            'validate_tracks_before_micdta' => true,
            'max_containers_per_shipment' => 50,
            'timeout_seconds' => 90,
        ];
    }

    protected function getWebserviceType(): string
    {
        return 'micdta';
    }

    protected function getCountry(): string
    {
        return 'AR';
    }

    /**
     * Validaciones específicas para MIC/DTA Argentina
     */
    protected function validateSpecificData(Voyage $voyage): array
    {
        $validation = ['errors' => [], 'warnings' => []];

        try {
            // 1. Verificar CUIT empresa
            if (!$this->company->tax_id || strlen($this->company->tax_id) !== 11) {
                $validation['errors'][] = 'CUIT de empresa inválido para AFIP';
            }

            // 2. Verificar embarcación líder
            if ($voyage->leadVessel) {
                if (!$voyage->leadVessel->name) {
                    $validation['errors'][] = 'Embarcación sin nombre válido';
                }
                if (!$voyage->leadVessel->registration_number) {
                    $validation['warnings'][] = 'Embarcación sin número de registro';
                }
            }

            // 3. Verificar puertos con códigos Argentina
            if ($voyage->originPort && !$this->isValidArgentinaPort($voyage->originPort->code)) {
                $validation['warnings'][] = 'Puerto origen sin código Argentina válido';
            }
            
            if ($voyage->destinationPort && !$this->isValidArgentinaPort($voyage->destinationPort->code)) {
                $validation['warnings'][] = 'Puerto destino sin código Argentina válido';
            }

            // 4. Verificar shipments con datos mínimos
            foreach ($voyage->shipments as $shipment) {
                if (!$shipment->shipment_number) {
                    $validation['errors'][] = "Shipment {$shipment->id} sin número de embarque";
                }

                // Verificar bills of lading por shipment
                $bolCount = $shipment->billsOfLading()->count();
                if ($bolCount === 0) {
                    $validation['errors'][] = "Shipment {$shipment->shipment_number} sin conocimientos de embarque";
                }

                // Verificar peso total
                $totalWeight = $shipment->billsOfLading()
                    ->join('shipment_items', 'bills_of_lading.id', '=', 'shipment_items.bill_of_lading_id')
                    ->sum('shipment_items.gross_weight_kg');

                if ($totalWeight <= 0) {
                    $validation['warnings'][] = "Shipment {$shipment->shipment_number} sin peso de carga";
                }
            }

            // 5. Verificar contenedores
            $totalContainers = 0;
            foreach ($voyage->shipments as $shipment) {
                foreach ($shipment->billsOfLading as $bol) {
                    $containers = $bol->shipmentItems()
                        ->whereNotNull('container_number')
                        ->distinct('container_number')
                        ->count();
                    $totalContainers += $containers;
                }
            }

            if ($totalContainers === 0) {
                $validation['warnings'][] = 'Voyage sin contenedores identificados';
            }

            $this->logOperation('info', 'Validación específica MIC/DTA completada', [
                'voyage_id' => $voyage->id,
                'errors_count' => count($validation['errors']),
                'warnings_count' => count($validation['warnings']),
                'total_containers' => $totalContainers,
            ]);

        } catch (Exception $e) {
            $validation['errors'][] = 'Error en validación MIC/DTA: ' . $e->getMessage();
            $this->logOperation('error', 'Error validando datos MIC/DTA', [
                'error' => $e->getMessage(),
                'voyage_id' => $voyage->id,
            ]);
        }

        return $validation;
    }

    /**
     * Envío específico MIC/DTA con flujo secuencial AFIP
     */
    protected function sendSpecificWebservice(Voyage $voyage, array $options = []): array
    {
        $this->logOperation('info', 'Iniciando flujo secuencial MIC/DTA AFIP', [
            'voyage_id' => $voyage->id,
            'voyage_number' => $voyage->voyage_number,
        ]);

        try {
            // PASO 1: RegistrarTitEnvios (genera TRACKs)
            $titEnviosResult = $this->sendRegistrarTitEnvios($voyage);
            if (!$titEnviosResult['success']) {
                return $titEnviosResult;
            }

            // PASO 2: RegistrarMicDta (usa TRACKs generados)
            $micDtaResult = $this->sendRegistrarMicDta($voyage, $titEnviosResult['tracks']);
            
            return $micDtaResult;

        } catch (Exception $e) {
            $this->logOperation('error', 'Error en flujo MIC/DTA', [
                'error' => $e->getMessage(),
                'voyage_id' => $voyage->id,
            ]);

            return [
                'success' => false,
                'error_message' => 'Error en flujo MIC/DTA: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * PASO 1: RegistrarTitEnvios - Generar TRACKs
     */
    private function sendRegistrarTitEnvios(Voyage $voyage): array
    {
        $this->logOperation('info', 'PASO 1: Iniciando RegistrarTitEnvios', [
            'voyage_id' => $voyage->id,
        ]);

        try {
            // 1. Generar XML para TitEnvios
            $xmlContent = $this->generateTitEnviosXml($voyage);
            if (!$xmlContent) {
                throw new Exception('Error generando XML TitEnvios');
            }

            // 2. Preparar cliente SOAP
            $soapClient = $this->soapClient->createClient(
                $this->getWebserviceType(),
                $this->config['environment']
            );

            // 3. Enviar SOAP request
            $soapResult = $this->soapClient->sendRequest(
                $this->getCurrentTransaction(),
                'RegistrarTitEnvios',
                ['xmlContent' => $xmlContent]
            );

            if (!$soapResult['success']) {
                throw new Exception('Error en SOAP RegistrarTitEnvios: ' . $soapResult['error_message']);
            }

            // 4. Procesar respuesta y extraer TRACKs
            $tracks = $this->extractTracksFromResponse($soapResult['response_xml'], $voyage);
            
            // 5. Guardar TRACKs en base de datos
            $this->saveTracks($voyage, $tracks);

            $this->logOperation('info', 'RegistrarTitEnvios exitoso', [
                'voyage_id' => $voyage->id,
                'tracks_generated' => count($tracks),
            ]);

            return [
                'success' => true,
                'tracks' => $tracks,
                'response_xml' => $soapResult['response_xml'],
            ];

        } catch (Exception $e) {
            $this->logOperation('error', 'Error en RegistrarTitEnvios', [
                'error' => $e->getMessage(),
                'voyage_id' => $voyage->id,
            ]);

            return [
                'success' => false,
                'error_message' => 'RegistrarTitEnvios falló: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * PASO 2: RegistrarMicDta - Usar TRACKs generados
     */
    private function sendRegistrarMicDta(Voyage $voyage, array $tracks): array
    {
        $this->logOperation('info', 'PASO 2: Iniciando RegistrarMicDta', [
            'voyage_id' => $voyage->id,
            'tracks_count' => count($tracks),
        ]);

        try {
            // 1. Validar que tenemos TRACKs
            if (empty($tracks)) {
                throw new Exception('No hay TRACKs disponibles para MIC/DTA');
            }

            // 2. Generar XML MicDta con TRACKs
            $xmlContent = $this->generateMicDtaXml($voyage, $tracks);
            if (!$xmlContent) {
                throw new Exception('Error generando XML MicDta');
            }

            // 3. Preparar cliente SOAP
            $soapClient = $this->soapClient->createClient(
                $this->getWebserviceType(),
                $this->config['environment']
            );

            // 4. Enviar SOAP request
            $soapResult = $this->soapClient->sendRequest(
                $this->getCurrentTransaction(),
                'RegistrarMicDta',
                ['xmlContent' => $xmlContent]
            );

            if (!$soapResult['success']) {
                throw new Exception('Error en SOAP RegistrarMicDta: ' . $soapResult['error_message']);
            }

            // 5. Procesar respuesta final
            $confirmationNumber = $this->extractConfirmationFromResponse($soapResult['response_xml']);

            $this->logOperation('info', 'RegistrarMicDta exitoso', [
                'voyage_id' => $voyage->id,
                'confirmation_number' => $confirmationNumber,
            ]);

            return [
                'success' => true,
                'confirmation_number' => $confirmationNumber,
                'tracks_used' => $tracks,
                'response_xml' => $soapResult['response_xml'],
            ];

        } catch (Exception $e) {
            $this->logOperation('error', 'Error en RegistrarMicDta', [
                'error' => $e->getMessage(),
                'voyage_id' => $voyage->id,
            ]);

            return [
                'success' => false,
                'error_message' => 'RegistrarMicDta falló: ' . $e->getMessage(),
            ];
        }
    }

    // ====================================
    // MÉTODOS AUXILIARES
    // ====================================

    /**
     * Generar XML para RegistrarTitEnvios
     */
    private function generateTitEnviosXml(Voyage $voyage): ?string
    {
        try {
            return $this->xmlSerializer->createTitEnviosXml($voyage, $this->generateTransactionId());
        } catch (Exception $e) {
            $this->logOperation('error', 'Error generando XML TitEnvios', [
                'error' => $e->getMessage(),
                'voyage_id' => $voyage->id,
            ]);
            return null;
        }
    }

    /**
     * Generar XML para RegistrarMicDta
     */
    private function generateMicDtaXml(Voyage $voyage, array $tracks): ?string
    {
        try {
            return $this->xmlSerializer->createMicDtaXml($voyage, $tracks, $this->generateTransactionId());
        } catch (Exception $e) {
            $this->logOperation('error', 'Error generando XML MicDta', [
                'error' => $e->getMessage(),
                'voyage_id' => $voyage->id,
            ]);
            return null;
        }
    }

    /**
     * Extraer TRACKs de respuesta AFIP
     */
    private function extractTracksFromResponse(string $responseXml, Voyage $voyage): array
    {
        $tracks = [];
        
        try {
            $dom = new \DOMDocument();
            $dom->loadXML($responseXml);
            
            // Buscar elementos de TRACK en la respuesta
            $trackElements = $dom->getElementsByTagName('Track');
            
            foreach ($trackElements as $trackElement) {
                $trackNumber = $trackElement->textContent;
                if ($trackNumber) {
                    $tracks[] = [
                        'track_number' => $trackNumber,
                        'shipment_id' => null, // Se asignará al guardar
                        'generated_at' => now(),
                    ];
                }
            }

            $this->logOperation('info', 'TRACKs extraídos de respuesta', [
                'tracks_count' => count($tracks),
                'voyage_id' => $voyage->id,
            ]);

        } catch (Exception $e) {
            $this->logOperation('error', 'Error extrayendo TRACKs', [
                'error' => $e->getMessage(),
                'voyage_id' => $voyage->id,
            ]);
        }

        return $tracks;
    }

    /**
     * Guardar TRACKs en base de datos
     */
    private function saveTracks(Voyage $voyage, array $tracks): void
    {
        try {
            $shipments = $voyage->shipments()->get();
            
            foreach ($tracks as $index => $trackData) {
                $shipment = $shipments[$index] ?? null;
                
                if ($shipment) {
                    WebserviceTrack::create([
                        'company_id' => $this->company->id,
                        'voyage_id' => $voyage->id,
                        'shipment_id' => $shipment->id,
                        'track_number' => $trackData['track_number'],
                        'webservice_type' => $this->getWebserviceType(),
                        'country' => $this->getCountry(),
                        'status' => 'active',
                        'generated_at' => $trackData['generated_at'],
                    ]);
                }
            }

            $this->logOperation('info', 'TRACKs guardados en base de datos', [
                'tracks_saved' => count($tracks),
                'voyage_id' => $voyage->id,
            ]);

        } catch (Exception $e) {
            $this->logOperation('error', 'Error guardando TRACKs', [
                'error' => $e->getMessage(),
                'voyage_id' => $voyage->id,
            ]);
            throw $e;
        }
    }

    /**
     * Extraer número de confirmación de respuesta MicDta
     */
    private function extractConfirmationFromResponse(string $responseXml): ?string
    {
        try {
            $dom = new \DOMDocument();
            $dom->loadXML($responseXml);
            
            // Buscar número de confirmación en respuesta AFIP
            $confirmationElements = $dom->getElementsByTagName('NumeroConfirmacion');
            if ($confirmationElements->length > 0) {
                return $confirmationElements->item(0)->textContent;
            }

            return null;

        } catch (Exception $e) {
            $this->logOperation('error', 'Error extrayendo confirmación', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Verificar si puerto tiene código Argentina válido
     */
    private function isValidArgentinaPort(string $portCode): bool
    {
        // Códigos típicos de puertos Argentina para hidrovía
        $validCodes = ['ARBUE', 'ARRSA', 'ARSFE', 'ARPAR', 'ARCAM'];
        return in_array($portCode, $validCodes);
    }

    /**
     * Obtener transacción actual
     */
    private function getCurrentTransaction(): ?\App\Models\WebserviceTransaction
    {
        if ($this->currentTransactionId) {
            return \App\Models\WebserviceTransaction::find($this->currentTransactionId);
        }
        return null;
    }
}