<?php

namespace App\Services\Simple;

use App\Models\Voyage;
use App\Services\Simple\BaseWebserviceService;
use Exception;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * SISTEMA MODULAR WEBSERVICES - ArgentinaAnticipatedService
 * 
 * Servicio para Información Anticipada Argentina AFIP
 * Extiende BaseWebserviceService para el webservice AFIP de Información Anticipada Marítima.
 * 
 * ESPECIFICACIONES AFIP:
 * - WSDL: https://wsaduhomoext.afip.gob.ar/DIAV2/wgesinformacionanticipada/wgesinformacionanticipada.asmx?wsdl
 * - Namespace: Ar.Gob.Afip.Dga.Org.wgesinformacionanticipada
 * - Métodos: RegistrarViaje, RectificarViaje, RegistrarTitulosCbc
 * 
 * FUNCIONALIDADES:
 * - Registro de viaje ATA MT (más simple que MIC/DTA)
 * - Rectificación de viaje
 * - Registro de títulos ATA CBC
 * - NO requiere TRACKs (diferencia con MIC/DTA)
 * - Datos de cabecera + embarcación + contenedores vacíos
 * 
 * REUTILIZA INFRAESTRUCTURA:
 * - BaseWebserviceService (validaciones, transacciones, logging)
 * - CertificateManagerService (certificados .p12 existentes)
 * - SimpleXmlGenerator (generación XML AFIP)
 * - Modelos existentes: Voyage, Shipment, Company
 */
class ArgentinaAnticipatedService extends BaseWebserviceService
{
    /**
     * Configuración específica para Información Anticipada Argentina
     */
    protected function getWebserviceConfig(): array
    {
        return [
            'webservice_type' => 'anticipada',
            'country' => 'AR',
            'environment' => 'testing',
            
            // URLs AFIP Información Anticipada
            'webservice_url_testing' => 'https://wsaduhomoext.afip.gob.ar/DIAV2/wgesinformacionanticipada/wgesinformacionanticipada.asmx',
            'webservice_url_production' => 'https://webservicesadu.afip.gob.ar/DIAV2/wgesinformacionanticipada/wgesinformacionanticipada.asmx',
            'wsdl_url_testing' => 'https://wsaduhomoext.afip.gob.ar/DIAV2/wgesinformacionanticipada/wgesinformacionanticipada.asmx?wsdl',
            'wsdl_url_production' => 'https://webservicesadu.afip.gob.ar/DIAV2/wgesinformacionanticipada/wgesinformacionanticipada.asmx?wsdl',
            
            // SOAP Actions Información Anticipada
            'soap_action_registrar_viaje' => 'Ar.Gob.Afip.Dga.Org.wgesinformacionanticipada/RegistrarViaje',
            'soap_action_rectificar_viaje' => 'Ar.Gob.Afip.Dga.Org.wgesinformacionanticipada/RectificarViaje',
            'soap_action_registrar_titulos_cbc' => 'Ar.Gob.Afip.Dga.Org.wgesinformacionanticipada/RegistrarTitulosCbc',
            
            // Configuración específica
            'timeout_seconds' => 60,
            'max_retries' => 3,
            'require_certificate' => true,
            'validate_xml_structure' => true,
            'requires_tracks' => false, // Diferencia clave con MIC/DTA
            'max_containers_per_voyage' => 100,
        ];
    }

    /**
     * Tipo de webservice específico
     */
    protected function getWebserviceType(): string
    {
        return 'anticipada';
    }

    /**
     * País del webservice
     */
    protected function getCountry(): string
    {
        return 'AR';
    }

    /**
     * URL del WSDL específico del webservice
     */
    protected function getWsdlUrl(): string
    {
        $environment = $this->config['environment'] ?? 'testing';
        $urls = [
            'testing' => 'https://wsaduhomoext.afip.gob.ar/DIAV2/wgesinformacionanticipada/wgesinformacionanticipada.asmx?wsdl',
            'production' => 'https://webservicesadu.afip.gob.ar/DIAV2/wgesinformacionanticipada/wgesinformacionanticipada.asmx?wsdl',
        ];
        return $urls[$environment] ?? $urls['testing'];
    }

    /**
     * Validación específica de datos para Información Anticipada
     */
    protected function validateSpecificData(Voyage $voyage): array
    {
        $validation = ['errors' => [], 'warnings' => []];

        // Validaciones básicas heredadas
        $baseValidation = $this->validateBaseData($voyage);
        $validation['errors'] = array_merge($validation['errors'], $baseValidation['errors']);
        $validation['warnings'] = array_merge($validation['warnings'], $baseValidation['warnings']);

        // Validaciones específicas Información Anticipada
        
        // 1. Verificar embarcación líder
        if (!$voyage->lead_vessel_id) {
            $validation['errors'][] = 'Voyage debe tener embarcación líder definida';
        }

        // 2. Verificar capitán
        if (!$voyage->captain_id) {
            $validation['warnings'][] = 'Voyage sin capitán asignado - se usará capitán por defecto';
        }

        // 3. Verificar fechas obligatorias
        if (!$voyage->departure_date) {
            $validation['errors'][] = 'Voyage debe tener fecha de salida definida';
        }

        if (!$voyage->estimated_arrival_date) {
            $validation['warnings'][] = 'Voyage sin fecha estimada de llegada';
        }

        // 4. Verificar ruta completa
        if (!$voyage->origin_port_id || !$voyage->destination_port_id) {
            $validation['errors'][] = 'Voyage debe tener puertos de origen y destino definidos';
        }

        // 5. Verificar que tenga al menos un shipment
        $shipmentsCount = $voyage->shipments()->count();
        if ($shipmentsCount === 0) {
            $validation['errors'][] = 'Voyage debe tener al menos un shipment para información anticipada';
        }

        // 6. Validar estado del voyage
        if (!in_array($voyage->status, ['confirmed', 'in_progress', 'ready_to_depart'])) {
            $validation['warnings'][] = 'Estado del voyage no está confirmado para envío anticipado';
        }

        // 7. Verificar empresa
        if (!$voyage->company_id || !$voyage->company) {
            $validation['errors'][] = 'Voyage debe estar asociado a una empresa válida';
        }

        return $validation;
    }

    /**
     * Envío específico del webservice Información Anticipada
     */
    protected function sendSpecificWebservice(Voyage $voyage, array $options = []): array
    {
        try {
            $this->logOperation('info', 'Iniciando envío Información Anticipada Argentina', [
                'voyage_id' => $voyage->id,
                'voyage_number' => $voyage->voyage_number,
                'method' => $options['method'] ?? 'RegistrarViaje',
            ]);

            // Determinar método a ejecutar
            $method = $options['method'] ?? 'RegistrarViaje';
            
            switch ($method) {
                case 'RegistrarViaje':
                    return $this->registrarViaje($voyage, $options);
                    
                case 'RectificarViaje':
                    return $this->rectificarViaje($voyage, $options);
                    
                case 'RegistrarTitulosCbc':
                    return $this->registrarTitulosCbc($voyage, $options);
                    
                default:
                    throw new Exception("Método no válido para Información Anticipada: {$method}");
            }

        } catch (Exception $e) {
            $this->logOperation('error', 'Error en envío Información Anticipada', [
                'error' => $e->getMessage(),
                'voyage_id' => $voyage->id,
            ]);

            return [
                'success' => false,
                'error_message' => $e->getMessage(),
                'transaction_id' => $this->currentTransactionId,
            ];
        }
    }

    /**
     * MÉTODO PRINCIPAL: RegistrarViaje - Registro de viaje ATA MT
     * 
     * Registra información anticipada del viaje completo con datos de cabecera,
     * embarcación, capitán y contenedores vacíos/correo.
     */
    public function registrarViaje(Voyage $voyage, array $options = []): array
    {
        try {
            DB::beginTransaction();

            // Crear transacción
            $transaction = $this->createTransaction($voyage, array_merge($options, [
                'method' => 'RegistrarViaje',
                'soap_action' => $this->config['soap_action_registrar_viaje'],
            ]));
            
            $this->currentTransactionId = $transaction->id;

            // Validar datos específicos
            $validation = $this->validateSpecificData($voyage);
            if (!empty($validation['errors'])) {
                throw new Exception('Errores de validación: ' . implode(', ', $validation['errors']));
            }

            // Cargar relaciones necesarias
            $voyage->load([
                'company',
                'leadVessel',
                'captain',
                'originPort.country',
                'destinationPort.country',
                'shipments.vessel',
                'shipments.captain',
                'shipments.billsOfLading.shipmentItems.containers'
            ]);

            // Generar XML para RegistrarViaje
            $transactionId = $this->generateTransactionId();
            $xmlContent = $this->xmlSerializer->createRegistrarViajeXml($voyage, $transactionId);

            if (!$xmlContent) {
                throw new Exception('Error generando XML para RegistrarViaje');
            }

            // Crear cliente SOAP
            $soapClient = $this->createSoapClient();

            // Enviar request SOAP
            $soapResult = $this->sendSoapRequest($transaction, $soapClient, $xmlContent, 'RegistrarViaje');

            // Procesar respuesta
            if ($soapResult['success']) {
                $transaction->update([
                    'status' => 'success',
                    'external_reference' => $soapResult['external_reference'] ?? null,
                    'response_data' => $soapResult['response_data'] ?? null,
                    'completed_at' => now(),
                ]);

                $this->updateWebserviceStatus($voyage, 'sent', [
                    'last_sent_at' => now(),
                    'external_reference' => $soapResult['external_reference'],
                ]);

                $this->logOperation('info', 'RegistrarViaje enviado exitosamente', [
                    'voyage_id' => $voyage->id,
                    'transaction_id' => $transaction->id,
                    'external_reference' => $soapResult['external_reference'],
                ]);

                DB::commit();
                return $soapResult;

            } else {
                $transaction->update([
                    'status' => 'error',
                    'error_message' => $soapResult['error_message'] ?? 'Error desconocido',
                ]);

                $this->updateWebserviceStatus($voyage, 'error', [
                    'last_error_at' => now(),
                    'last_error_message' => $soapResult['error_message'],
                ]);

                DB::commit();
                return $soapResult;
            }

        } catch (Exception $e) {
            DB::rollBack();
            
            $this->logOperation('error', 'Error en RegistrarViaje', [
                'error' => $e->getMessage(),
                'voyage_id' => $voyage->id,
            ]);

            return [
                'success' => false,
                'error_message' => $e->getMessage(),
                'transaction_id' => $this->currentTransactionId,
            ];
        }
    }

    /**
     * RectificarViaje - Rectificación de viaje ATA MT
     */
    public function rectificarViaje(Voyage $voyage, array $options = []): array
    {
        try {
            DB::beginTransaction();

            // Crear transacción
            $transaction = $this->createTransaction($voyage, array_merge($options, [
                'method' => 'RectificarViaje',
                'soap_action' => $this->config['soap_action_rectificar_viaje'],
            ]));
            
            $this->currentTransactionId = $transaction->id;

            // Verificar que existe un viaje previo enviado
            $previousTransaction = $voyage->webserviceTransactions()
                ->where('webservice_type', 'anticipada')
                ->where('status', 'success')
                ->whereNotNull('external_reference')
                ->latest()
                ->first();

            if (!$previousTransaction) {
                throw new Exception('No se encontró un viaje previo enviado para rectificar');
            }

            // Cargar relaciones necesarias
            $voyage->load([
                'company',
                'leadVessel', 
                'captain',
                'originPort.country',
                'destinationPort.country',
                'shipments.vessel',
                'shipments.captain',
                'shipments.billsOfLading.shipmentItems.containers'
            ]);

            // Generar XML para RectificarViaje
            $transactionId = $this->generateTransactionId();
            $rectificationData = array_merge($options, [
                'original_external_reference' => $previousTransaction->external_reference,
            ]);
            
            $xmlContent = $this->xmlSerializer->createRectificarViajeXml($voyage, $rectificationData, $transactionId);

            if (!$xmlContent) {
                throw new Exception('Error generando XML para RectificarViaje');
            }

            // Crear cliente SOAP y enviar
            $soapClient = $this->createSoapClient();
            $soapResult = $this->sendSoapRequest($transaction, $soapClient, $xmlContent, 'RectificarViaje');

            // Procesar respuesta
            if ($soapResult['success']) {
                $transaction->update([
                    'status' => 'success',
                    'external_reference' => $soapResult['external_reference'] ?? null,
                    'response_data' => $soapResult['response_data'] ?? null,
                    'completed_at' => now(),
                ]);

                $this->updateWebserviceStatus($voyage, 'sent', [
                    'last_sent_at' => now(),
                    'external_reference' => $soapResult['external_reference'],
                ]);

                DB::commit();
                return $soapResult;
            } else {
                $transaction->update([
                    'status' => 'error',
                    'error_message' => $soapResult['error_message'] ?? 'Error desconocido',
                ]);

                DB::commit();
                return $soapResult;
            }

        } catch (Exception $e) {
            DB::rollBack();
            
            $this->logOperation('error', 'Error en RectificarViaje', [
                'error' => $e->getMessage(),
                'voyage_id' => $voyage->id,
            ]);

            return [
                'success' => false,
                'error_message' => $e->getMessage(),
                'transaction_id' => $this->currentTransactionId,
            ];
        }
    }

    /**
     * RegistrarTitulosCbc - Registro de títulos ATA CBC
     */
    public function registrarTitulosCbc(Voyage $voyage, array $options = []): array
    {
        try {
            DB::beginTransaction();

            // Crear transacción
            $transaction = $this->createTransaction($voyage, array_merge($options, [
                'method' => 'RegistrarTitulosCbc',
                'soap_action' => $this->config['soap_action_registrar_titulos_cbc'],
            ]));
            
            $this->currentTransactionId = $transaction->id;

            // Cargar relaciones necesarias
            $voyage->load([
                'company',
                'leadVessel',
                'captain', 
                'originPort.country',
                'destinationPort.country',
                'shipments.vessel',
                'shipments.captain',
                'shipments.billsOfLading.shipmentItems.containers'
            ]);

            // Generar XML para RegistrarTitulosCbc
            $transactionId = $this->generateTransactionId();
            $xmlContent = $this->xmlSerializer->createRegistrarTitulosCbcXml($voyage, $options, $transactionId);

            if (!$xmlContent) {
                throw new Exception('Error generando XML para RegistrarTitulosCbc');
            }

            // Crear cliente SOAP y enviar
            $soapClient = $this->createSoapClient();
            $soapResult = $this->sendSoapRequest($transaction, $soapClient, $xmlContent, 'RegistrarTitulosCbc');

            // Procesar respuesta
            if ($soapResult['success']) {
                $transaction->update([
                    'status' => 'success',
                    'external_reference' => $soapResult['external_reference'] ?? null,
                    'response_data' => $soapResult['response_data'] ?? null,
                    'completed_at' => now(),
                ]);

                $this->updateWebserviceStatus($voyage, 'sent', [
                    'last_sent_at' => now(),
                    'external_reference' => $soapResult['external_reference'],
                ]);

                DB::commit();
                return $soapResult;
            } else {
                $transaction->update([
                    'status' => 'error',
                    'error_message' => $soapResult['error_message'] ?? 'Error desconocido',
                ]);

                DB::commit();
                return $soapResult;
            }

        } catch (Exception $e) {
            DB::rollBack();
            
            $this->logOperation('error', 'Error en RegistrarTitulosCbc', [
                'error' => $e->getMessage(),
                'voyage_id' => $voyage->id,
            ]);

            return [
                'success' => false,
                'error_message' => $e->getMessage(),
                'transaction_id' => $this->currentTransactionId,
            ];
        }
    }

    /**
     * Enviar request SOAP específico para Información Anticipada
     */
    private function sendSoapRequest($transaction, $soapClient, string $xmlContent, string $method): array
    {
        try {
            $this->logOperation('info', "Enviando request SOAP {$method}", [
                'transaction_id' => $transaction->id,
                'xml_size_kb' => round(strlen($xmlContent) / 1024, 2),
            ]);

            // Actualizar estado a 'sending'
            $transaction->update(['status' => 'sending', 'sent_at' => now()]);

            // Extraer parámetros del XML para SOAP
            $soapParams = $this->extractSoapParameters($xmlContent, $method);

            // Llamar método SOAP específico
            $startTime = microtime(true);
            $soapResponse = $soapClient->{$method}($soapParams);
            $responseTime = round((microtime(true) - $startTime) * 1000);

            // Procesar respuesta SOAP
            $result = $this->processSoapResponse($soapResponse, $method);
            $result['response_time_ms'] = $responseTime;

            // Actualizar transacción con XMLs
            $transaction->update([
                'request_xml' => $xmlContent,
                'response_xml' => $this->soapResponseToXml($soapResponse),
                'response_time_ms' => $responseTime,
            ]);

            return $result;

        } catch (Exception $e) {
            $this->logOperation('error', "Error en request SOAP {$method}", [
                'error' => $e->getMessage(),
                'transaction_id' => $transaction->id,
            ]);

            return [
                'success' => false,
                'error_message' => $e->getMessage(),
                'response_time_ms' => isset($responseTime) ? $responseTime : null,
            ];
        }
    }

    /**
     * Extraer parámetros SOAP del XML generado
     */
    private function extractSoapParameters(string $xmlContent, string $method): array
    {
        // TODO: Implementar extracción de parámetros específicos del XML
        // Por ahora retorna estructura básica
        return [
            'xmlContent' => $xmlContent,
            'method' => $method,
        ];
    }

    /**
     * Procesar respuesta SOAP de AFIP
     */
    private function processSoapResponse($soapResponse, string $method): array
    {
        // TODO: Implementar procesamiento específico de respuestas AFIP
        // Por ahora retorna estructura básica
        return [
            'success' => true,
            'external_reference' => 'TEMP_REF_' . time(),
            'response_data' => $soapResponse,
        ];
    }

    /**
     * Convertir respuesta SOAP a XML para almacenamiento
     */
    private function soapResponseToXml($soapResponse): string
    {
        // TODO: Implementar conversión específica
        return is_object($soapResponse) || is_array($soapResponse) 
            ? json_encode($soapResponse) 
            : (string)$soapResponse;
    }
}