<?php

namespace App\Services\Simple;

use App\Models\Voyage;
use App\Models\Company;
use App\Models\User;
use App\Services\Webservice\SoapClientService;
use App\Services\Simple\SimpleXmlGenerator;
use App\Services\Simple\BaseWebserviceService;
use App\Models\WebserviceResponse;
use App\Models\WebserviceLog;
use App\Models\WebserviceError;
use Exception;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

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
class ArgentinaAnticipatedService
{
    private Company $company;
    private User $user;
    private SoapClientService $soapClient;
    private array $config;
    private ?int $currentTransactionId = null;

    public function __construct(Company $company, User $user, array $config = [])
    {
        $this->company = $company;
        $this->user = $user;
        $this->soapClient = new SoapClientService($company);
        $this->config = array_merge([
            'webservice_type' => 'anticipada',
            'country' => 'AR',
            'environment' => 'testing',
            'soap_action_registrar_viaje' => 'Ar.Gob.Afip.Dga.Org.wgesinformacionanticipada/RegistrarViaje',
            'soap_action_rectificar_viaje' => 'Ar.Gob.Afip.Dga.Org.wgesinformacionanticipada/RectificarViaje',
            'soap_action_registrar_titulos_cbc' => 'Ar.Gob.Afip.Dga.Org.wgesinformacionanticipada/RegistrarTitulosCbc',
            'timeout_seconds' => 60,
            'max_retries' => 3,
            'require_certificate' => true,
        ], $config);
    }

    /**
     * Validación específica de datos
     */
    private function validateSpecificData(Voyage $voyage): array
    {
        $validation = ['errors' => [], 'warnings' => []];

        // Verificar embarcación líder
        if (!$voyage->lead_vessel_id) {
            $validation['errors'][] = 'Viaje debe tener embarcación líder definida';
        }

        // Verificar fechas
        if (!$voyage->departure_date) {
            $validation['errors'][] = 'Viaje debe tener fecha de salida definida';
        }

        // Verificar empresa
        if (!$voyage->company_id) {
            $validation['errors'][] = 'Viaje debe estar asociado a una empresa válida';
        }

        return $validation;
    }

    /**
     * Validar si el voyage puede ser procesado para Información Anticipada
     * 
     * @param Voyage $voyage
     * @return array ['can_process' => bool, 'errors' => [], 'warnings' => []]
     */
    public function canProcessVoyage(Voyage $voyage): array
    {
        $validation = [
            'can_process' => false,
            'errors' => [],
            'warnings' => [],
        ];

        // 1. Validar voyage básico
        if (!$voyage || !$voyage->id) {
            $validation['errors'][] = 'Viaje no válido o no encontrado';
            return $validation;
        }

        // 2. Validar datos obligatorios del viaje
        if (!$voyage->voyage_number) {
            $validation['errors'][] = 'Número de viaje requerido';
        }

        if (!$voyage->lead_vessel_id) {
            $validation['errors'][] = 'Embarcación líder requerida';
        }

        if (!$voyage->origin_port_id || !$voyage->destination_port_id) {
            $validation['errors'][] = 'Puertos de origen y destino requeridos';
        }

        if (!$voyage->departure_date) {
            $validation['errors'][] = 'Fecha de salida requerida';
        }

        // 3. Validar empresa
        if ($voyage->company_id !== $this->company->id) {
            $validation['errors'][] = 'Viaje no pertenece a la empresa';
        }

        // 4. Warnings (no bloquean envío)
        if (!$voyage->captain_id) {
            $validation['warnings'][] = 'Recomendado: Asignar capitán al viaje';
        }

        if (!$voyage->estimated_arrival_date) {
            $validation['warnings'][] = 'Recomendado: Fecha estimada de llegada';
        }

        // 5. Determinar si puede procesar
        $validation['can_process'] = empty($validation['errors']);

        return $validation;
    }

    /**
     * Registrar operación en logs
     */
    private function logOperation(string $level, string $message, array $context = []): void
    {
        $context = array_merge([
            'service' => 'ArgentinaAnticipatedService',
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
        ], $context);

        match($level) {
            'debug' => \Log::debug($message, $context),
            'info' => \Log::info($message, $context),
            'warning' => \Log::warning($message, $context),
            'error' => \Log::error($message, $context),
            default => \Log::info($message, $context),
        };
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
            $transaction = $this->createWebserviceTransaction($voyage, array_merge($options, [
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
            $transactionId = 'ANTICIPADA_' . time() . '_' . $voyage->id;
            $xmlGenerator = new SimpleXmlGenerator($this->company, $this->config);
            $xmlContent = $xmlGenerator->createRegistrarViajeXml($voyage, $transactionId);

            if (!$xmlContent) {
                throw new Exception('Error generando XML para RegistrarViaje');
            }

            // Crear cliente SOAP
            $soapClient = $this->soapClient->createClient($this->config['webservice_type'], $this->config['environment']);


            // Enviar request SOAP
            $soapResult = $this->sendSoapRequest($transaction, $soapClient, $xmlContent, 'RegistrarViaje');

            // Procesar respuesta
            // Extraer IdentificadorViaje de la respuesta XML
            // Extraer IdentificadorViaje de la respuesta (AFIP devuelve HTML)
            $voyageIdentifier = null;
            if (isset($soapResult['response_data'])) {
                $response = $soapResult['response_data'];
                
                // AFIP devuelve: <title></title>NUMERO_IDENTIFICADOR</head>
                if (preg_match('/<\/title>(\d+)<\/head>/', $response, $matches)) {
                    $voyageIdentifier = $matches[1];
                    \Log::info("IdentificadorViaje extraído de HTML", ['identificador' => $voyageIdentifier]);
                } else {
                    \Log::warning("No se encontró IdentificadorViaje en respuesta", ['response' => substr($response, 0, 200)]);
                }
            }

            if ($soapResult['success']) {
                $transaction->update([
                    'status' => 'success',
                    'external_reference' => $voyageIdentifier,
                    'completed_at' => now(),
                ]);
                
                // ✅ PERSISTIR ESTADO DEL WEBSERVICE
                $this->updateWebserviceStatus($voyage, 'sent', [
                    'transaction_id' => $transaction->id,
                    'external_reference' => $voyageIdentifier,
                ]);

                // ✅ ACTUALIZAR VOYAGE CON IdentificadorViaje DE AFIP
                $voyage->update([
                    'argentina_voyage_id' => $voyageIdentifier,
                ]);

                Log::info('WebserviceSimple [anticipada]: RegistrarViaje enviado exitosamente', [
                    'voyage_id' => $voyage->id,
                    'transaction_id' => $transaction->id,
                    'external_reference' => $voyageIdentifier,
                ]);
                
                DB::commit();
                
                return [
                    'success' => true,
                    'transaction_id' => $transaction->id,
                    'external_reference' => $voyageIdentifier,
                    'error_message' => null,
                ];
            } else {
                $errorMessage = $soapResult['error_message'] ?? 'Error desconocido en envío SOAP';
                
                $transaction->update([
                    'status' => 'error',
                    'error_message' => $errorMessage,
                ]);

                $this->updateWebserviceStatus($voyage, 'error', [
                    'last_error_at' => now(),
                    'last_error_message' => $errorMessage,
                ]);

                Log::info('🔍 DEBUG: Antes de commit', [
                    'transaction_id' => $transaction->id,
                    'has_request_xml' => isset($soapResult['request_xml']),
                    'has_response_xml' => isset($soapResult['response_xml']),
                    'request_xml_length' => isset($soapResult['request_xml']) ? strlen($soapResult['request_xml']) : 0,
                    'response_xml_length' => isset($soapResult['response_xml']) ? strlen($soapResult['response_xml']) : 0,
                ]);

                DB::commit();

                Log::info('🔍 DEBUG: Después de commit exitoso');
                
                return [
                    'success' => false,
                    'transaction_id' => $transaction->id,
                    'external_reference' => null,
                    'error_message' => $errorMessage,
                ];
            }

        } catch (Exception $e) {
            DB::rollBack();
            
            $this->logOperation('error', 'Error en RegistrarViaje', [
                'error' => $e->getMessage(),
                'voyage_id' => $voyage->id,
            ]);

            return [
                'success' => false,
                'transaction_id' => $this->currentTransactionId,
                'external_reference' => null,
                'error_message' => $e->getMessage(),
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
            $transaction = $this->createWebserviceTransaction($voyage, array_merge($options, [
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
            $transactionId = 'ANTICIPADA_' . time() . '_' . $voyage->id;
            $rectificationData = array_merge($options, [
                'original_external_reference' => $previousTransaction->external_reference,
            ]);
            
            $xmlGenerator = new SimpleXmlGenerator($this->company, $this->config);
            $xmlContent = $xmlGenerator->createRectificarViajeXml($voyage, $rectificationData, $transactionId);

            if (!$xmlContent) {
                throw new Exception('Error generando XML para RectificarViaje');
            }

            // Crear cliente SOAP y enviar
            $soapClient = $this->soapClient->createClient($this->config['webservice_type'], $this->config['environment']);
            $soapResult = $this->sendSoapRequest($transaction, $soapClient, $xmlContent, 'RectificarViaje');

            // Procesar respuesta
            // Procesar respuesta
            $voyageIdentifier = $soapResult['external_reference'] ?? null;

            if ($soapResult['success']) {
                $transaction->update([
                    'status' => 'success',
                    'external_reference' => $voyageIdentifier,
                    'completed_at' => now(),
                ]);
                
                // Actualizar estado del webservice
                $this->updateWebserviceStatus($voyage, 'sent', [
                    'transaction_id' => $transaction->id,
                    'external_reference' => $voyageIdentifier,
                ]);

                Log::info('RectificarViaje enviado exitosamente', [
                    'voyage_id' => $voyage->id,
                    'transaction_id' => $transaction->id,
                    'external_reference' => $voyageIdentifier,
                ]);
                
                DB::commit();
                $soapResult['transaction_id'] = $transaction->id;
                return $soapResult;
            } else {
                $transaction->update([
                    'status' => 'error',
                    'error_message' => $soapResult['error_message'] ?? 'Error desconocido',
                ]);

                $this->updateWebserviceStatus($voyage, 'error', [
                    'last_error_at' => now(),
                    'last_error_message' => $soapResult['error_message'] ?? 'Error desconocido',
                ]);

                DB::commit();
                $soapResult['transaction_id'] = $transaction->id;
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
            $transaction = $this->createWebserviceTransaction($voyage, array_merge($options, [
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
            $transactionId = 'ANTICIPADA_' . time() . '_' . $voyage->id;
            $xmlGenerator = new SimpleXmlGenerator($this->company, $this->config);
            $xmlContent = $xmlGenerator->createRegistrarTitulosCbcXml($voyage, $options, $transactionId);

            if (!$xmlContent) {
                throw new Exception('Error generando XML para RegistrarTitulosCbc');
            }

            // Crear cliente SOAP y enviar
            $soapClient = $this->soapClient->createClient($this->config['webservice_type'], $this->config['environment']);
            $soapResult = $this->sendSoapRequest($transaction, $soapClient, $xmlContent, 'RegistrarTitulosCbc');

            // Procesar respuesta
            $voyageIdentifier = $soapResult['external_reference'] ?? null;

            if ($soapResult['success']) {
                $transaction->update([
                    'status' => 'success',
                    'external_reference' => $voyageIdentifier,
                    'completed_at' => now(),
                ]);

                // Parsear y guardar TRACKs
                $tracks = $this->parseAndSaveTracks(
                    $soapResult['response_xml'] ?? $soapResult['response_data'] ?? '',
                    $transaction->id,
                    $voyage
                );
                
                if (!empty($tracks)) {
                    \Log::info('TRACKs guardados para RegistrarTitulosCbc', [
                        'voyage_id' => $voyage->id,
                        'tracks_count' => count($tracks),
                        'tracks' => $tracks,
                    ]);
                }
                
                // Actualizar estado del webservice
                $this->updateWebserviceStatus($voyage, 'sent', [
                    'transaction_id' => $transaction->id,
                    'external_reference' => $voyageIdentifier,
                ]);

                Log::info('RectificarViaje enviado exitosamente', [
                    'voyage_id' => $voyage->id,
                    'transaction_id' => $transaction->id,
                    'external_reference' => $voyageIdentifier,
                ]);
                
                DB::commit();
                $soapResult['transaction_id'] = $transaction->id;
                return $soapResult;
            } else {
                $transaction->update([
                    'status' => 'error',
                    'error_message' => $soapResult['error_message'] ?? 'Error desconocido',
                ]);

                $this->updateWebserviceStatus($voyage, 'error', [
                    'last_error_at' => now(),
                    'last_error_message' => $soapResult['error_message'] ?? 'Error desconocido',
                ]);

                DB::commit();
                $soapResult['transaction_id'] = $transaction->id;
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
     * CerrarViaje - Cierre de Información Anticipada
     * 
     * Cierra el viaje enviando conocimientos que NO descargan en Argentina.
     * Requisito: El viaje debe tener argentina_voyage_id (RegistrarViaje previo).
     * 
     * @param Voyage $voyage
     * @param array $options
     * @return array
     */
    public function cerrarViaje(Voyage $voyage, array $options = []): array
    {
        DB::beginTransaction();
        
        try {
            // 1. VALIDAR PREREQUISITOS
            if (empty($voyage->argentina_voyage_id)) {
                return [
                    'success' => false,
                    'transaction_id' => null,
                    'external_reference' => null,
                    'error_message' => 'El viaje debe tener IdentificadorViaje de AFIP. Primero ejecute RegistrarViaje.',
                ];
            }
            
            // 2. VERIFICAR QUE HAYA CONOCIMIENTOS FUERA DE ARGENTINA
            $billsNoArgentina = $voyage->billsOfLading()
                ->whereHas('dischargePort.country', function($query) {
                    $query->where('code', '!=', 'AR');
                })
                ->count();
            
            if ($billsNoArgentina === 0) {
                return [
                    'success' => false,
                    'transaction_id' => null,
                    'external_reference' => null,
                    'error_message' => 'No hay conocimientos que descarguen fuera de Argentina para cerrar el viaje.',
                ];
            }
            
            Log::info("CerrarViaje: {$billsNoArgentina} conocimientos fuera de Argentina", [
                'voyage_id' => $voyage->id,
                'argentina_voyage_id' => $voyage->argentina_voyage_id,
            ]);
            
            // 3. CREAR TRANSACCIÓN
            $transaction = $this->createWebserviceTransaction($voyage, [
                'method' => 'CerrarViaje',
                'soap_action' => 'Ar.Gob.Afip.Dga.Org.wgesinformacionanticipada/CerrarViaje',
            ]);
            
            // 4. GENERAR XML (con placeholders __TOKEN__ y __SIGN__)
            $xmlGenerator = new SimpleXmlGenerator($this->company);
            $requestXml = $xmlGenerator->generateCerrarViajeXml($voyage, $this->company);
            
            Log::info("XML CerrarViaje generado", [
                'voyage_id' => $voyage->id,
                'xml_size' => strlen($requestXml),
            ]);
            
            // 5. CREAR CLIENTE SOAP
            $soapClient = $this->soapClient->createClient($this->config['webservice_type'], $this->config['environment']);

            // 5.1 ENVIAR REQUEST SOAP
            $soapResult = $this->sendSoapRequest($transaction, $soapClient, $requestXml, 'CerrarViaje');
            
            // 6. PROCESAR RESPUESTA
            if ($soapResult['success']) {
                // Actualizar transacción como exitosa
                $transaction->update([
                    'status' => 'success',
                    'completed_at' => now(),
                ]);
                
                // Actualizar estado del viaje
                $voyage->update([
                    'argentina_status' => 'approved',
                ]);
                
                // ✅ PERSISTIR ESTADO DEL WEBSERVICE
                $this->updateWebserviceStatus($voyage, 'approved', [
                    'transaction_id' => $transaction->id,
                ]);
                
                Log::info('CerrarViaje enviado exitosamente', [
                    'voyage_id' => $voyage->id,
                    'transaction_id' => $transaction->id,
                    'argentina_voyage_id' => $voyage->argentina_voyage_id,
                ]);
                
                DB::commit();
                
                return [
                    'success' => true,
                    'transaction_id' => $transaction->id,
                    'external_reference' => $voyage->argentina_voyage_id,
                    'error_message' => null,
                ];
                
            } else {
                // Error en envío
                $errorMessage = $soapResult['error_message'] ?? 'Error desconocido en envío SOAP';
                
                $transaction->update([
                    'status' => 'error',
                    'error_message' => $errorMessage,
                ]);
                
                $this->updateWebserviceStatus($voyage, 'error', [
                    'last_error_at' => now(),
                    'last_error_message' => $errorMessage,
                ]);
                
                DB::commit();
                
                return [
                    'success' => false,
                    'transaction_id' => $transaction->id,
                    'external_reference' => null,
                    'error_message' => $errorMessage,
                ];
            }
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            $this->logOperation('error', 'Error en CerrarViaje', [
                'error' => $e->getMessage(),
                'voyage_id' => $voyage->id,
            ]);
            
            return [
                'success' => false,
                'transaction_id' => $this->currentTransactionId ?? null,
                'external_reference' => null,
                'error_message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Parsear respuesta XML de CerrarViaje
     * 
     * @param string $responseXml
     * @return array
     */
    private function parseCerrarViajeResponse(string $responseXml): array
    {
        try {
            $xml = simplexml_load_string($responseXml);
            
            if ($xml === false) {
                return [
                    'success' => false,
                    'error_message' => 'Error parseando XML de respuesta',
                ];
            }
            
            // Registrar namespaces
            $xml->registerXPathNamespace('soap', 'http://schemas.xmlsoap.org/soap/envelope/');
            $xml->registerXPathNamespace('ar', 'Ar.Gob.Afip.Dga.Org.wgesinformacionanticipada');
            
            // Buscar el resultado
            $result = $xml->xpath('//ar:CerrarViajeResult');
            
            if (empty($result)) {
                return [
                    'success' => false,
                    'error_message' => 'No se encontró CerrarViajeResult en la respuesta',
                ];
            }
            
            $result = $result[0];
            
            // Extraer errores
            $errors = [];
            if (isset($result->ListaErrores->DetalleError)) {
                foreach ($result->ListaErrores->DetalleError as $error) {
                    $codigo = (string)$error->Codigo;
                    $descripcion = (string)$error->Descripcion;
                    
                    $errors[] = [
                        'codigo' => $codigo,
                        'descripcion' => $descripcion,
                        'adicional' => (string)$error->DescripcionAdicional ?? null,
                    ];
                    
                    // Código 0 = éxito
                    if ($codigo !== '0') {
                        return [
                            'success' => false,
                            'error_code' => $codigo,
                            'error_message' => $descripcion,
                            'errors' => $errors,
                        ];
                    }
                }
            }
            
            // Éxito
            return [
                'success' => true,
                'identificador_viaje' => (string)$result->IdentificadorViaje ?? null,
                'server' => (string)$result->Server ?? null,
                'timestamp' => (string)$result->TimeStamp ?? null,
                'errors' => $errors,
            ];
            
        } catch (\Exception $e) {
            Log::error("Error parseando respuesta CerrarViaje: " . $e->getMessage());
            
            return [
                'success' => false,
                'error_message' => 'Error parseando respuesta: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Enviar request SOAP específico para Información Anticipada
     */
    private function sendSoapRequest($transaction, $soapClient, string $xmlContent, string $method): array
    {
        try {
            // 📝 LOG: Inicio de envío
            $this->createWebserviceLog(
                $transaction->id,
                'info',
                'soap_request',
                "Iniciando envío SOAP {$method}",
                [
                    'xml_size_kb' => round(strlen($xmlContent) / 1024, 2),
                    'method' => $method,
                ]
            );

            // Actualizar estado a 'sending'
            $transaction->update(['status' => 'sending', 'sent_at' => now()]);

            // Enviar usando __doRequest directo (ORIGINAL)
            $startTime = microtime(true);
            $response = $soapClient->__doRequest(
                $xmlContent,
                'https://wsaduhomoext.afip.gob.ar/DIAV2/wgesinformacionanticipada/wgesinformacionanticipada.asmx',
                "Ar.Gob.Afip.Dga.Org.wgesinformacionanticipada/{$method}",
                SOAP_1_2
            );
            $responseTime = round((microtime(true) - $startTime) * 1000);

            // 📝 LOG: Respuesta recibida
            $this->createWebserviceLog(
                $transaction->id,
                'info',
                'soap_response',
                "Respuesta SOAP recibida en {$responseTime}ms",
                [
                    'response_time_ms' => $responseTime,
                    'response_size_kb' => round(strlen($response) / 1024, 2),
                ]
            );

            // Verificar si hay error SOAP
            $hasError = strpos($response, 'soap:Fault') !== false;
            
            if ($hasError) {
                // 📝 LOG: Error SOAP detectado
                $this->createWebserviceLog(
                    $transaction->id,
                    'error',
                    'soap_fault',
                    'Error SOAP detectado en respuesta',
                    ['response_snippet' => substr($response, 0, 500)]
                );
                
                // 📊 ERROR: Registrar en catálogo
                $this->registerWebserviceError('SOAP_FAULT', 'Error en respuesta SOAP');
            }

            // Parsear respuesta HTML de AFIP para extraer IdentificadorViaje
            $externalReference = $this->parseAfipResponse($response);
            
            if ($externalReference) {
                // 📝 LOG: IdentificadorViaje extraído
                $this->createWebserviceLog(
                    $transaction->id,
                    'info',
                    'data_extraction',
                    "IdentificadorViaje extraído: {$externalReference}"
                );
            }

            $soapResult = [
                'success' => !$hasError && $externalReference !== null,
                'response_data' => $response,
                'response_time_ms' => $responseTime,
                'request_xml' => $xmlContent,
                'response_xml' => $response,
                'external_reference' => $externalReference,
            ];

            // Actualizar transacción con XMLs
            $transaction->update([
                'request_xml' => $xmlContent,
                'response_xml' => $response,
                'response_time_ms' => $responseTime,
                'response_at' => now(),
                'external_reference' => $externalReference,
            ]);

            // 📄 RESPONSE: Crear respuesta estructurada
            $this->createWebserviceResponse(
                $transaction->id,
                $soapResult['success'],
                [
                    'external_reference' => $externalReference,
                    'response_time_ms' => $responseTime,
                    'method' => $method,
                ]
            );

            // 📝 LOG: Finalización
            $this->createWebserviceLog(
                $transaction->id,
                $soapResult['success'] ? 'info' : 'error',
                'completion',
                $soapResult['success'] 
                    ? "Proceso completado exitosamente" 
                    : "Proceso completado con errores",
                ['final_status' => $soapResult['success'] ? 'success' : 'error']
            );

            return $soapResult;

        } catch (Exception $e) {
            // 📝 LOG: Excepción capturada
            $this->createWebserviceLog(
                $transaction->id,
                'critical',
                'exception',
                "Excepción durante envío SOAP: {$e->getMessage()}",
                [
                    'exception_class' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]
            );
            
            // 📊 ERROR: Registrar excepción
            $this->registerWebserviceError('EXCEPTION', $e->getMessage());

            return [
                'success' => false,
                'error_message' => $e->getMessage(),
                'response_time_ms' => null,
            ];
        }
    }

    /**
     * Extraer parámetros SOAP del XML generado
     */
    private function extractSoapParameters(string $xmlContent, string $method): array
    {
        // Por simplicidad, retornar XML completo como parámetro
        return ['xmlParam' => $xmlContent];
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

    /**
     * Crear transacción webservice
     */
    /**
     * Crear transacción webservice
     */
    private function createWebserviceTransaction(Voyage $voyage, array $options = []): \App\Models\WebserviceTransaction
    {
        $transactionId = 'ANT-' . $this->company->id . '-' . now()->format('YmdHis') . '-' . rand(10, 99);
        $method = $options['method'] ?? 'RegistrarViaje';
        
        return \App\Models\WebserviceTransaction::create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'voyage_id' => $voyage->id,
            'transaction_id' => $transactionId,
            'webservice_type' => 'anticipada',
            'country' => 'AR',
            'webservice_url' => 'https://wsaduhomoext.afip.gob.ar/DIAV2/wgesinformacionanticipada/wgesinformacionanticipada.asmx',
            'soap_action' => $options['soap_action'] ?? 'Ar.Gob.Afip.Dga.Org.wgesinformacionanticipada/RegistrarViaje',
            'additional_metadata' => ['method' => $method],
            'status' => 'pending',
            'retry_count' => 0,
            'max_retries' => 3,
            'environment' => $this->config['environment'],
            'currency_code' => 'USD',
            'container_count' => 0,
            'bill_of_lading_count' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Método público principal para envío
     */
    public function sendWebservice(Voyage $voyage, array $options = []): array
    {
        $method = $options['method'] ?? 'RegistrarViaje';
        
        switch ($method) {
            case 'RegistrarViaje':
                return $this->registrarViaje($voyage, $options);
                
            case 'RectificarViaje':
                return $this->rectificarViaje($voyage, $options);
                
            case 'RegistrarTitulosCbc':
                return $this->registrarTitulosCbc($voyage, $options);

            case 'CerrarViaje':
                return $this->cerrarViaje($voyage, $options);
                
            default:
                return [
                    'success' => false,
                    'error_message' => "Método no válido: {$method}",
                ];
        }
    }

    /**
     * Método temporal para debugging - obtener respuesta SOAP completa
     */
    public function debugSoapResponse(Voyage $voyage): array
    {
        try {
            // Generar XML como lo hace el método normal
            $xmlGenerator = new \App\Services\Simple\SimpleXmlGenerator($voyage->company);
            $transactionId = 'DEBUG_' . time();
            $xmlContent = $xmlGenerator->createRegistrarViajeXml($voyage, $transactionId);
            
            // Enviar y capturar respuesta completa
            $soapClient = $this->createSoapClient();
            $response = $this->sendSoapRequest($soapClient, $xmlContent, 'RegistrarViaje');
            
            // Obtener respuesta completa del cliente SOAP
            $lastResponse = $soapClient->__getLastResponse();
            $lastRequest = $soapClient->__getLastRequest();
            
            // Extraer errores AFIP
            $afipErrors = ['has_afip_errors' => false, 'afip_errors' => [], 'error_summary' => ''];
            
            return [
                'xml_sent' => $xmlContent,
                'xml_sent_size' => strlen($xmlContent),
                'soap_response_raw' => $lastResponse,
                'soap_response_size' => strlen($lastResponse),
                'afip_errors' => $afipErrors,
                'parsed_error' => $response,
            ];
            
        } catch (Exception $e) {
            return [
                'error' => $e->getMessage(),
                'soap_response_raw' => $soapClient->__getLastResponse() ?? '',
                'soap_request_raw' => $soapClient->__getLastRequest() ?? '',
            ];
        }
    }

    /**
     * Actualizar estado del webservice en VoyageWebserviceStatus
     */
    private function updateWebserviceStatus(Voyage $voyage, string $status, array $data = []): void
    {
        // Obtener o crear el estado del webservice
        $webserviceStatus = \App\Models\VoyageWebserviceStatus::firstOrCreate(
            [
                'voyage_id' => $voyage->id,
                'country' => 'AR',
                'webservice_type' => 'anticipada',
            ],
            [
                'company_id' => $this->company->id,
                'user_id' => $this->user->id,
                'status' => 'pending',
                'can_send' => true,
                'is_required' => true,
                'retry_count' => 0,
                'max_retries' => 3,
            ]
        );

        // Actualizar según el estado
        switch ($status) {
            case 'sent':
                $webserviceStatus->markAsSent(
                    $data['transaction_id'] ?? null,
                    $this->user->id
                );
                
                // Si tenemos external_reference, guardarlo también
                if (isset($data['external_reference'])) {
                    $webserviceStatus->update([
                        'external_voyage_number' => $data['external_reference'],
                    ]);
                }
                break;

            case 'approved':
                $webserviceStatus->markAsApproved(
                    $data['confirmation_number'] ?? null,
                    $data['external_reference'] ?? null,
                    $this->user->id
                );
                break;

            case 'error':
                $webserviceStatus->markAsError(
                    $data['error_code'] ?? null,
                    $data['error_message'] ?? null,
                    $this->user->id
                );
                break;
        }

        $this->logOperation('info', 'Estado de webservice actualizado', [
            'voyage_id' => $voyage->id,
            'status' => $status,
            'webservice_status_id' => $webserviceStatus->id,
        ]);
    }

    /**
     * Crear log de webservice
     */
    private function createWebserviceLog(
        int $transactionId, 
        string $level, 
        string $category,
        string $message, 
        array $context = []
    ): void
    {
        try {
            WebserviceLog::create([
                'transaction_id' => $transactionId,
                'user_id' => $this->user->id,
                'level' => $level,
                'category' => $category,
                'message' => $message,
                'context' => !empty($context) ? $context : null,
                'environment' => $this->config['environment'],
                'webservice_operation' => 'RegistrarViaje',
                'created_at' => now(),
            ]);
        } catch (Exception $e) {
            Log::error('Error creando WebserviceLog: ' . $e->getMessage());
        }
    }

    /**
     * Crear respuesta estructurada
     */
    private function createWebserviceResponse(
        int $transactionId,
        bool $isSuccess,
        array $responseData
    ): void
    {
        try {
            $responseType = $isSuccess ? 'success' : 'business_error';
            
            // Extraer IdentificadorViaje si existe
            $voyageNumber = null;
            if (isset($responseData['external_reference'])) {
                $voyageNumber = $responseData['external_reference'];
            }
            
            WebserviceResponse::create([
                'transaction_id' => $transactionId,
                'response_type' => $responseType,
                'processing_status' => $isSuccess ? 'completed' : 'requires_manual',
                'requires_action' => !$isSuccess,
                'voyage_number' => $voyageNumber,
                'customs_metadata' => $responseData,
                'customs_status' => $isSuccess ? 'approved' : 'rejected',
                'customs_processed_at' => now(),
                'processed_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (Exception $e) {
            Log::error('Error creando WebserviceResponse: ' . $e->getMessage());
        }
    }

    /**
     * Registrar error en catálogo (si no existe)
     */
    private function registerWebserviceError(string $errorCode, string $errorMessage): void
    {
        try {
            // Buscar si el error ya existe
            $existingError = WebserviceError::where('country', 'AR')
                ->where('webservice_type', 'anticipada')
                ->where('error_code', $errorCode)
                ->first();
                
            if (!$existingError) {
                // Crear nuevo error en catálogo
                WebserviceError::create([
                    'country' => 'AR',
                    'webservice_type' => 'anticipada',
                    'error_code' => $errorCode,
                    'error_title' => 'Error AFIP ' . $errorCode,
                    'error_description' => $errorMessage,
                    'category' => 'business_logic',
                    'severity' => 'high',
                    'is_blocking' => true,
                    'allows_retry' => false,
                    'suggested_solution' => 'Verificar datos según documentación AFIP',
                    'frequency_count' => 1,
                    'first_occurrence' => now(),
                    'last_occurrence' => now(),
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                // Actualizar contador de frecuencia
                $existingError->increment('frequency_count');
                $existingError->update(['last_occurrence' => now()]);
            }
        } catch (Exception $e) {
            Log::error('Error registrando WebserviceError: ' . $e->getMessage());
        }
    }

    

    /**
     * Parsear respuesta SOAP AFIP para extraer IdentificadorViaje
     * 
     * @param string $response XML SOAP completo de AFIP
     * @return string|null IdentificadorViaje (16 chars) o null
     */
    private function parseAfipResponse(string $response): ?string
    {
        try {
            // Patrón HTML (AFIP testing devuelve HTML: <title></title>NUMERO</head>)
            if (preg_match('/<\/title>(.+?)<\/head>/s', $response, $matches)) {
                $identifier = trim($matches[1]);
                if (!empty($identifier)) {
                    Log::info("✅ IdentificadorViaje extraído de HTML", ['id' => $identifier]);
                    return $identifier;
                }
            }
            
            // Patrones XML SOAP (sin validación de longitud - aceptamos lo que AFIP envíe)
            $patterns = [
                '/<IdentificadorViaje>(.+?)<\/IdentificadorViaje>/is',
                '/<identificadorViaje>(.+?)<\/identificadorViaje>/is',
            ];

            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $response, $matches)) {
                    $identifier = trim($matches[1]);
                    if (!empty($identifier)) {
                        Log::info("✅ IdentificadorViaje extraído de XML", ['id' => $identifier]);
                        return $identifier;
                    }
                }
            }

            Log::warning("❌ No se encontró IdentificadorViaje en respuesta", [
                'response_snippet' => substr($response, 0, 300),
            ]);

            return null;

        } catch (\Exception $e) {
            Log::error("Error parseando respuesta AFIP", ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Validar formato AFIP: AAAAVVNNNNNNNNND (16 chars)
     * 
     * @param string $identifier
     * @return bool
     */
    private function validateAfipVoyageIdentifier(string $identifier): bool
    {
        // Longitud: 16 caracteres exactos
        if (strlen($identifier) !== 16) {
            return false;
        }

        // Formato: 15 dígitos + 1 alfanumérico
        if (!preg_match('/^\d{15}[A-Z0-9]$/i', $identifier)) {
            return false;
        }

        // Año razonable (2010-2099)
        $year = (int)substr($identifier, 0, 4);
        if ($year < 2010 || $year > 2099) {
            return false;
        }

        return true;
    }

    /**
     * Parsear y guardar TRACKs de la respuesta RegistrarTitulosCbc
     */
    private function parseAndSaveTracks(string $response, int $transactionId, Voyage $voyage): array
    {
        $tracks = [];
        
        try {
            // AFIP devuelve HTML, no XML - buscar TRACKs con regex
            // Patrón ejemplo: TRACK001, TRACK002, etc.
            if (preg_match_all('/TRACK\d+/i', $response, $matches)) {
                
                $billsOfLading = collect();
                foreach ($voyage->shipments as $shipment) {
                    $billsOfLading = $billsOfLading->merge($shipment->billsOfLading);
                }
                
                foreach ($matches[0] as $index => $trackNumber) {
                    $bol = $billsOfLading->get($index);
                    
                    if ($trackNumber && $bol) {
                        // Guardar en webservice_tracks
                        \App\Models\WebserviceTrack::create([
                            'webservice_transaction_id' => $transactionId,
                            'bill_of_lading_id' => $bol->id,
                            'track_number' => $trackNumber,
                            'track_type' => 'envio',
                            'webservice_method' => 'RegistrarTitulosCbc',
                            'status' => 'active',
                            'afip_response_data' => ['track' => $trackNumber],
                        ]);
                        
                        $tracks[] = $trackNumber;
                        
                        \Log::info("TRACK guardado", [
                            'track_number' => $trackNumber,
                            'bill_id' => $bol->id,
                        ]);
                    }
                }
            } else {
                \Log::warning('No se encontraron TRACKs en respuesta HTML', [
                    'response_snippet' => substr($response, 0, 200)
                ]);
            }
            
        } catch (Exception $e) {
            \Log::error('Error parseando TRACKs: ' . $e->getMessage());
        }
        
        return $tracks;
    }
    
}