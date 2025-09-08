<?php

namespace App\Services\Webservice;

use App\Models\Company;
use App\Models\Shipment;
use App\Models\User;
use App\Models\WebserviceTransaction;
use App\Models\WebserviceResponse;
use App\Models\WebserviceLog;
use App\Models\WebserviceTrack;
use App\Services\Webservice\SoapClientService;
use App\Services\Webservice\CertificateManagerService;
use App\Services\Webservice\XmlSerializerService;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * SERVICIO COMPLETO MIC/DTA ARGENTINA AFIP - SISTEMA TRACKs
 *
 * Servicio integrador completo para MIC/DTA Argentina AFIP con sistema TRACKs.
 * Flujo secuencial: RegistrarTitEnvios → RegistrarMicDta
 * 
 * FUNCIONALIDADES COMPLETAS:
 * - Paso 1: RegistrarTitEnvios (genera TRACKs)
 * - Paso 2: RegistrarMicDta (usa TRACKs del paso 1)
 * - Gestión completa de WebserviceTrack
 * - Validaciones pre-envío
 * - Manejo robusto de errores
 * - Sistema de logging completo
 * - Transacciones con rollback
 * 
 * INTEGRA:
 * - SoapClientService: Cliente SOAP con URLs reales
 * - CertificateManagerService: Gestión certificados .p12
 * - XmlSerializerService: Generación XML según especificación AFIP
 * 
 * DATOS SOPORTADOS:
 * - Empresas: MAERSK LINE ARGENTINA S.A. (CUIT, certificados .p12)
 * - Embarcaciones: PAR13001, GUARAN F, REINA DEL PARANA
 * - Viajes: V022NB, V023NB con rutas ARBUE → PYTVT  
 * - Contenedores: 40HC, 20GP, capacidades 38-48 unidades
 * - Capitanes: asignados del sistema con licencias válidas
 */
class ArgentinaMicDtaService
{
    private Company $company;
    private User $user;
    private ?int $currentTransactionId = null;
    private SoapClientService $soapClient;
    private CertificateManagerService $certificateManager;
    private XmlSerializerService $xmlSerializer;
    private array $config;

    /**
     * Configuración específica para MIC/DTA Argentina
     */
    private const MICDTA_CONFIG = [
        'webservice_type' => 'micdta',
        'country' => 'AR',
        'soap_action' => 'Ar.Gob.Afip.Dga.wgesregsintia2/RegistrarMicDta',
        'titenvios_soap_action' => 'Ar.Gob.Afip.Dga.wgesregsintia2/RegistrarTitEnvios',
        'environment' => 'testing',
        'max_retries' => 3,
        'retry_intervals' => [30, 120, 300],
        'timeout_seconds' => 60,
        'require_certificate' => true,
        'validate_xml_structure' => true,
    ];

    public function __construct(Company $company, User $user, array $config = [])
    {
        $this->company = $company;
        $this->user = $user;
        $this->config = array_merge(self::MICDTA_CONFIG, $config);

        // Inicializar servicios integrados
        $this->soapClient = new SoapClientService($company);
        $this->certificateManager = new CertificateManagerService($company);
        $this->xmlSerializer = new XmlSerializerService($company, $this->config);
    }

    // ========================================================================
    // MÉTODOS PRINCIPALES DEL FLUJO TRACKs AFIP
    // ========================================================================

    /**
     * Registrar títulos y envíos (RegistrarTitEnvios) - PASO 1 AFIP
     */
    public function registrarTitEnvios(Shipment $shipment): array
    {
        $result = [
            'success' => false,
            'tracks' => [],
            'transaction_id' => null,
            'errors' => [],
        ];

        try {
            $this->logOperation('info', 'Iniciando RegistrarTitEnvios - Paso 1 AFIP', [
                'shipment_id' => $shipment->id,
                'shipment_number' => $shipment->shipment_number,
            ]);

            // 1. Crear transacción específica para TitEnvios
            $transaction = $this->createTitEnviosTransaction($shipment);
            $result['transaction_id'] = $transaction->id;
            $this->currentTransactionId = $transaction->id;

            // 2. Generar XML para RegistrarTitEnvios
            $xmlContent = $this->xmlSerializer->createTitEnviosXml($shipment, $transaction->transaction_id);
            if (!$xmlContent) {
                throw new Exception('Error generando XML RegistrarTitEnvios');
            }

            // 3. Envío real a AFIP
            $soapClient = $this->prepareSoapClient();
            $soapResponse = $this->sendTitEnviosSoapRequest($transaction, $soapClient, $xmlContent);

            // 4. Procesar respuesta y crear TRACKs
            if ($soapResponse['success']) {
                $result = $this->processTitEnviosResponse($transaction, $soapResponse, $shipment);
            } else {
                $result['errors'] = $soapResponse['errors'] ?? ['Error en RegistrarTitEnvios'];
            }

            return $result;

        } catch (Exception $e) {
            $this->logOperation('error', 'Error en RegistrarTitEnvios', [
                'error' => $e->getMessage(),
                'shipment_id' => $shipment->id,
            ]);

            $result['errors'][] = $e->getMessage();
            return $result;
        }
    }

    /**
     * Envío MIC/DTA completo usando TRACKs - PASO 2 AFIP
     */
    public function sendMicDtaWithTracks(Shipment $shipment, array $tracks = null): array
    {
        $result = [
            'success' => false,
            'transaction_id' => null,
            'tracks_used' => [],
            'errors' => [],
        ];

        DB::beginTransaction();

        try {
            $this->logOperation('info', 'Iniciando MIC/DTA con TRACKs - Paso 2 AFIP', [
                'shipment_id' => $shipment->id,
                'tracks_provided' => count($tracks ?? []),
            ]);

            // 1. Obtener o generar TRACKs
            if (empty($tracks)) {
                $this->logOperation('info', 'TRACKs no proporcionados, ejecutando RegistrarTitEnvios primero');
                
                $titEnviosResult = $this->registrarTitEnvios($shipment);
                if (!$titEnviosResult['success']) {
                    $result['errors'] = array_merge(['Error en RegistrarTitEnvios:'], $titEnviosResult['errors']);
                    return $result;
                }
                
                $tracks = $titEnviosResult['tracks'];
            }

            // 2. Validar TRACKs disponibles
            $availableTracks = $this->validateTracksForMicDta($tracks);
            if (empty($availableTracks)) {
                $result['errors'][] = 'No hay TRACKs válidos disponibles para MIC/DTA';
                return $result;
            }

            // 3. Crear transacción MIC/DTA
            $transaction = $this->createTransaction($shipment);
            $result['transaction_id'] = $transaction->id;
            $this->currentTransactionId = $transaction->id;

            // 4. Generar XML MIC/DTA con TRACKs
            $xmlContent = $this->xmlSerializer->createMicDtaXmlWithTracks($shipment, $transaction->transaction_id, $availableTracks);
            if (!$xmlContent) {
                throw new Exception('Error generando XML MIC/DTA con TRACKs');
            }

            // 5. Enviar a AFIP
            $soapClient = $this->prepareSoapClient();
            $soapResponse = $this->sendSoapRequest($transaction, $soapClient, $xmlContent);
            $result = $this->processResponse($transaction, $soapResponse);

            // 6. Marcar TRACKs como usados si fue exitoso
            if ($result['success']) {
                $this->markTracksAsUsed($availableTracks, 'used_in_micdta');
                $result['tracks_used'] = $availableTracks;
            }

            DB::commit();
            return $result;

        } catch (Exception $e) {
            DB::rollBack();
            
            $this->logOperation('error', 'Error en MIC/DTA con TRACKs', [
                'error' => $e->getMessage(),
                'shipment_id' => $shipment->id,
            ]);

            $result['errors'][] = $e->getMessage();
            return $result;
        }
    }

    /**
     * Método principal heredado para compatibilidad
     */
    public function sendMicDta(Shipment $shipment): array
    {
        return $this->sendMicDtaWithTracks($shipment);
    }

    // ========================================================================
    // MÉTODOS DE SOPORTE TRACKs
    // ========================================================================

    /**
     * Crear transacción específica para RegistrarTitEnvios
     */
    private function createTitEnviosTransaction(Shipment $shipment): WebserviceTransaction
    {
        $transactionId = 'TITENV_' . time() . '_' . rand(1000, 9999);
        
        return WebserviceTransaction::create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'shipment_id' => $shipment->id,
            'voyage_id' => $shipment->voyage_id,
            'transaction_id' => $transactionId,
            'webservice_type' => 'micdta',
            'country' => 'AR',
            'soap_action' => $this->config['titenvios_soap_action'],
            'status' => 'pending',
            'environment' => $this->config['environment'],
            'webservice_url' => $this->getWebserviceUrl(),
            'timeout_seconds' => $this->config['timeout_seconds'] ?? 60,
            'max_retries' => $this->config['max_retries'],
            'retry_intervals' => json_encode($this->config['retry_intervals']),
            'requires_certificate' => $this->config['require_certificate'],
            'additional_metadata' => [
                'method' => 'RegistrarTitEnvios',
                'step' => 1,
                'purpose' => 'Generar TRACKs para MIC/DTA',
            ],
        ]);
    }

    /**
     * Crear registros WebserviceTrack en base de datos
     */
    private function createWebserviceTracks(int $transactionId, array $tracksData): array
    {
        $tracks = [];
        
        foreach ($tracksData as $trackData) {
            $track = WebserviceTrack::create([
                'webservice_transaction_id' => $transactionId,
                'shipment_id' => $trackData['shipment_id'] ?? null,
                'container_id' => $trackData['container_id'] ?? null,
                'bill_of_lading_id' => $trackData['bill_of_lading_id'] ?? null,
                'track_number' => $trackData['track_number'],
                'track_type' => $trackData['track_type'] ?? 'envio',
                'webservice_method' => 'RegistrarTitEnvios',
                'reference_type' => $trackData['reference_type'] ?? 'shipment',
                'reference_number' => $trackData['reference_number'],
                'description' => $trackData['description'] ?? null,
                'afip_title_number' => $trackData['afip_title_number'] ?? null,
                'afip_metadata' => $trackData['afip_metadata'] ?? null,
                'generated_at' => now(),
                'status' => 'generated',
                'created_by_user_id' => $this->user->id,
                'created_from_ip' => request()->ip(),
                'process_chain' => ['generated'],
            ]);
            
            $tracks[] = $track;
        }
        
        $this->logOperation('info', 'TRACKs creados en base de datos', [
            'transaction_id' => $transactionId,
            'tracks_count' => count($tracks),
            'track_numbers' => collect($tracks)->pluck('track_number')->toArray(),
        ]);
        
        return $tracks;
    }

    /**
     * Validar TRACKs disponibles para usar en MIC/DTA
     */
    private function validateTracksForMicDta(array $trackNumbers): array
    {
        if (empty($trackNumbers)) {
            return [];
        }
        
        // Obtener TRACKs desde base de datos
        $availableTracks = WebserviceTrack::whereIn('track_number', $trackNumbers)
            ->where('status', 'generated')
            ->where('webservice_method', 'RegistrarTitEnvios')
            ->get();
        
        $validTracks = [];
        foreach ($availableTracks as $track) {
            // Verificar que no haya expirado (asumiendo 24h de validez)
            if ($track->generated_at->diffInHours(now()) < 24) {
                $validTracks[] = $track->track_number;
            } else {
                $this->logOperation('warning', 'TRACK expirado encontrado', [
                    'track_number' => $track->track_number,
                    'generated_at' => $track->generated_at,
                    'hours_since_generation' => $track->generated_at->diffInHours(now()),
                ]);
            }
        }
        
        $this->logOperation('info', 'Validación de TRACKs completada', [
            'tracks_requested' => count($trackNumbers),
            'tracks_available' => count($validTracks),
            'tracks_expired' => count($trackNumbers) - count($validTracks),
        ]);
        
        return $validTracks;
    }

    /**
     * Marcar TRACKs como usados en el proceso
     */
    private function markTracksAsUsed(array $trackNumbers, string $status): void
    {
        if (empty($trackNumbers)) {
            return;
        }
        
        $updatedCount = WebserviceTrack::whereIn('track_number', $trackNumbers)
            ->where('status', 'generated')
            ->update([
                'status' => $status,
                'used_at' => now(),
                'process_chain' => DB::raw("JSON_ARRAY_APPEND(COALESCE(process_chain, JSON_ARRAY()), '$', '$status')"),
            ]);
        
        $this->logOperation('info', 'TRACKs marcados como usados', [
            'status' => $status,
            'tracks_updated' => $updatedCount,
            'track_numbers' => $trackNumbers,
        ]);
    }

    /**
     * Enviar SOAP Request para RegistrarTitEnvios (paso 1)
     */
    private function sendTitEnviosSoapRequest(WebserviceTransaction $transaction, $soapClient, string $xmlContent): array
    {
        $result = [
            'success' => false,
            'response_data' => null,
            'errors' => [],
        ];

        try {
            $this->logOperation('info', 'Enviando SOAP RegistrarTitEnvios', [
                'transaction_id' => $transaction->id,
                'xml_size_kb' => round(strlen($xmlContent) / 1024, 2),
            ]);

            // Preparar parámetros SOAP
            $soapParams = [
                'xmlParam' => $xmlContent,
            ];

            // Llamada SOAP real
            $soapResponse = $soapClient->__soapCall('RegistrarTitEnvios', $soapParams);

            if ($soapResponse) {
                $result['success'] = true;
                $result['response_data'] = $soapResponse;
                
                $this->logOperation('info', 'Respuesta SOAP RegistrarTitEnvios recibida', [
                    'transaction_id' => $transaction->id,
                    'has_response' => !empty($soapResponse),
                ]);
            } else {
                $result['errors'][] = 'Respuesta SOAP vacía';
            }

            return $result;

        } catch (Exception $e) {
            $result['errors'][] = 'Error SOAP RegistrarTitEnvios: ' . $e->getMessage();
            
            $this->logOperation('error', 'Error en SOAP RegistrarTitEnvios', [
                'transaction_id' => $transaction->id,
                'error' => $e->getMessage(),
            ]);

            return $result;
        }
    }

    /**
     * Procesar respuesta real de RegistrarTitEnvios desde AFIP
     */
    private function processTitEnviosResponse(WebserviceTransaction $transaction, array $soapResponse, Shipment $shipment): array
    {
        $result = [
            'success' => false,
            'tracks' => [],
            'transaction_id' => $transaction->id,
        ];
        
        try {
            // Extraer TRACKs de la respuesta AFIP
            $afipTracks = $this->extractTracksFromResponse($soapResponse['response_data'] ?? []);
            
            if (empty($afipTracks)) {
                throw new Exception('No se recibieron TRACKs válidos de AFIP');
            }
            
            // Crear registros de TRACKs en base de datos
            $tracks = $this->createWebserviceTracksFromResponse($transaction->id, $afipTracks, $shipment);
            
            // Actualizar transacción como exitosa
            $transaction->update([
                'status' => 'success',
                'completed_at' => now(),
                'external_reference' => $soapResponse['confirmation_number'] ?? null,
            ]);
            
            // Crear registro de respuesta
            WebserviceResponse::create([
                'transaction_id' => $transaction->id,
                'response_type' => 'success',
                'confirmation_number' => $soapResponse['confirmation_number'] ?? null,
                'argentina_tracks_env' => collect($tracks)->pluck('track_number')->toArray(),
                'customs_status' => 'processed',
                'customs_processed_at' => now(),
            ]);
            
            $result['success'] = true;
            $result['tracks'] = collect($tracks)->pluck('track_number')->toArray();
            
            return $result;
            
        } catch (Exception $e) {
            $this->logOperation('error', 'Error procesando respuesta RegistrarTitEnvios', [
                'error' => $e->getMessage(),
                'transaction_id' => $transaction->id,
            ]);
            
            $transaction->update([
                'status' => 'error',
                'error_message' => $e->getMessage(),
                'completed_at' => now(),
            ]);
            
            throw $e;
        }
    }

    /**
     * Extraer TRACKs de respuesta AFIP
     */
    private function extractTracksFromResponse($responseData): array
    {
        $tracks = [];
        
        // Según documentación AFIP, la respuesta contiene TracksEnv
        if (isset($responseData['TracksEnv']) && is_array($responseData['TracksEnv'])) {
            foreach ($responseData['TracksEnv'] as $index => $trackNumber) {
                $tracks[] = [
                    'track_number' => $trackNumber,
                    'track_type' => 'envio',
                    'reference_type' => 'shipment',
                    'reference_number' => 'ENV_' . ($index + 1),
                    'afip_metadata' => [
                        'source' => 'RegistrarTitEnvios',
                        'response_index' => $index,
                    ],
                ];
            }
        }
        
        // TracksContVacios para contenedores vacíos
        if (isset($responseData['TracksContVacios']) && is_array($responseData['TracksContVacios'])) {
            foreach ($responseData['TracksContVacios'] as $index => $trackNumber) {
                $tracks[] = [
                    'track_number' => $trackNumber,
                    'track_type' => 'contenedor_vacio',
                    'reference_type' => 'container',
                    'reference_number' => 'CONT_VACIO_' . ($index + 1),
                    'afip_metadata' => [
                        'source' => 'RegistrarTitEnvios',
                        'response_index' => $index,
                    ],
                ];
            }
        }
        
        // Si no encuentra estructura específica, buscar cualquier campo con "track"
        if (empty($tracks)) {
            $this->logOperation('warning', 'No se encontraron TRACKs en estructura estándar, buscando alternativos', [
                'response_keys' => array_keys($responseData),
            ]);
            
            foreach ($responseData as $key => $value) {
                if (stripos($key, 'track') !== false && !empty($value)) {
                    if (is_array($value)) {
                        foreach ($value as $trackNumber) {
                            $tracks[] = [
                                'track_number' => $trackNumber,
                                'track_type' => 'envio',
                                'reference_type' => 'shipment',
                                'reference_number' => 'GENERIC',
                                'afip_metadata' => [
                                    'source' => 'RegistrarTitEnvios',
                                    'field' => $key,
                                ],
                            ];
                        }
                    } elseif (is_string($value)) {
                        $tracks[] = [
                            'track_number' => $value,
                            'track_type' => 'envio',
                            'reference_type' => 'shipment',
                            'reference_number' => 'GENERIC',
                            'afip_metadata' => [
                                'source' => 'RegistrarTitEnvios',
                                'field' => $key,
                            ],
                        ];
                    }
                }
            }
        }
        
        return $tracks;
    }

    /**
     * Crear registros WebserviceTrack desde respuesta AFIP
     */
    private function createWebserviceTracksFromResponse(int $transactionId, array $tracks, Shipment $shipment): array
    {
        $createdTracks = [];

        try {
            foreach ($tracks as $trackData) {
                $trackRecord = [
                    'webservice_transaction_id' => $transactionId,
                    'shipment_id' => $shipment->id,
                    'container_id' => null,
                    'bill_of_lading_id' => null,
                    'track_number' => $trackData['track_number'],
                    'track_type' => $trackData['track_type'] ?? 'envio',
                    'webservice_method' => 'RegistrarTitEnvios',
                    'reference_type' => $trackData['reference_type'] ?? 'shipment',
                    'reference_number' => $trackData['reference_number'] ?? $shipment->shipment_number,
                    'description' => "TRACK generado para shipment {$shipment->shipment_number}",
                    'afip_title_number' => null,
                    'afip_metadata' => $trackData['afip_metadata'] ?? [],
                    'generated_at' => now(),
                    'status' => 'generated',
                    'used_at' => null,
                    'completed_at' => null,
                    'created_by_user_id' => $this->user->id,
                    'created_from_ip' => request()->ip(),
                    'process_chain' => ['RegistrarTitEnvios'],
                    'notes' => 'TRACK generado automáticamente por flujo TRACKs AFIP',
                ];

                $track = WebserviceTrack::create($trackRecord);
                $createdTracks[] = $track->toArray();
            }

            return $createdTracks;

        } catch (Exception $e) {
            $this->logOperation('error', 'Error creando registros WebserviceTrack', [
                'error' => $e->getMessage(),
                'transaction_id' => $transactionId,
                'tracks' => $tracks,
            ]);
            return [];
        }
    }

    // ========================================================================
    // MÉTODOS HEREDADOS Y COMPATIBILIDAD
    // ========================================================================

    /**
     * Crear transacción para MIC/DTA normal
     */
    public function createTransaction(Shipment $shipment): WebserviceTransaction
    {
        $micDtaReference = $this->generateMicDtaReference($shipment);
        
        return WebserviceTransaction::create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'shipment_id' => $shipment->id,
            'voyage_id' => $shipment->voyage_id,
            'transaction_id' => $micDtaReference,
            'webservice_type' => $this->config['webservice_type'],
            'country' => $this->config['country'],
            'soap_action' => $this->config['soap_action'],
            'status' => 'pending',
            'environment' => $this->config['environment'],
            'webservice_url' => $this->getWebserviceUrl(),
            'timeout_seconds' => $this->config['timeout_seconds'],
            'max_retries' => $this->config['max_retries'],
            'retry_intervals' => json_encode($this->config['retry_intervals']),
            'requires_certificate' => $this->config['require_certificate'],
            'additional_metadata' => [
                'method' => 'RegistrarMicDta',
                'step' => 2,
                'purpose' => 'Envío MIC/DTA con TRACKs',
            ],
        ]);
    }

    /**
     * Preparar cliente SOAP
     */
    private function prepareSoapClient()
    {
        $webserviceUrl = $this->getWebserviceUrl();
        
        // Usar SoapClientService con parámetros correctos
        return $this->soapClient->createClient($this->config['webservice_type'], $this->config['environment']);
    }

    /**
     * Obtener URL del webservice según ambiente
     */
    private function getWebserviceUrl(): string
    {
        $environment = $this->config['environment'];
        
        $urls = [
            'testing' => 'https://wsaduhomoext.afip.gob.ar/wgesregsintia2/WsAduanaRegistrarSintiaV2.asmx?WSDL',
            'production' => 'https://wsaduext.afip.gob.ar/wgesregsintia2/WsAduanaRegistrarSintiaV2.asmx?WSDL',
        ];
        
        return $urls[$environment] ?? $urls['testing'];
    }

    /**
     * Enviar request SOAP usando SoapClientService
     */
    private function sendSoapRequest(WebserviceTransaction $transaction, $soapClient, string $xmlContent): array
    {
        try {
            $this->logOperation('info', 'Iniciando request SOAP Argentina', [
                'transaction_id' => $transaction->id,
                'xml_size_kb' => round(strlen($xmlContent) / 1024, 2),
            ]);

            // Actualizar estado a 'sending'
            $transaction->update(['status' => 'sending', 'sent_at' => now()]);

            // Extraer parámetros estructurados del XML
            $parameters = $this->extractSoapParameters($xmlContent);

            // Enviar usando SoapClientService
            $soapResult = $this->soapClient->sendRequest($transaction, 'RegistrarMicDta', [$parameters]);

            // Actualizar transacción con XMLs
            $transaction->update([
                'request_xml' => $soapResult['request_xml'] ?? $xmlContent,
                'response_xml' => $soapResult['response_xml'] ?? null,
                'response_time_ms' => $soapResult['response_time_ms'] ?? null,
            ]);

            return $soapResult;

        } catch (Exception $e) {
            $this->logOperation('error', 'Error en request SOAP', [
                'transaction_id' => $transaction->id,
                'error' => $e->getMessage(),
            ]);

            $transaction->update([
                'status' => 'error',
                'error_message' => $e->getMessage(),
                'completed_at' => now(),
            ]);

            return [
                'success' => false,
                'error_message' => $e->getMessage(),
                'error_code' => 'SOAP_REQUEST_ERROR',
            ];
        }
    }

    /**
     * Extraer parámetros SOAP del XML
     */
    private function extractSoapParameters(string $xmlContent): array
    {
        // Por simplicidad, retornar XML completo como parámetro
        return ['xmlParam' => $xmlContent];
    }

    /**
     * Procesar respuesta del webservice SOAP
     */
    private function processResponse(WebserviceTransaction $transaction, array $soapResponse): array
    {
        $result = [
            'success' => false,
            'mic_dta_reference' => null,
            'response_data' => null,
            'errors' => [],
            'warnings' => [],
        ];

        try {
            if ($soapResponse['success']) {
                // Procesar respuesta exitosa usando método existente
                $this->processSuccessResponse($transaction, $soapResponse);
                
                $result['success'] = true;
                $result['mic_dta_reference'] = $soapResponse['response_data']['micdta_id'] ?? null;
                $result['response_data'] = $soapResponse['response_data'] ?? [];
                
                $this->logOperation('info', 'Respuesta MIC/DTA procesada exitosamente', [
                    'transaction_id' => $transaction->id,
                    'mic_dta_reference' => $result['mic_dta_reference'],
                ]);
                
            } else {
                // Procesar respuesta de error usando método existente
                $this->processErrorResponse($transaction, $soapResponse);
                
                $result['errors'][] = $soapResponse['error_message'] ?? 'Error desconocido en MIC/DTA';
                
                $this->logOperation('error', 'Error en respuesta MIC/DTA', [
                    'transaction_id' => $transaction->id,
                    'error_message' => $soapResponse['error_message'] ?? 'Error desconocido',
                    'error_code' => $soapResponse['error_code'] ?? 'UNKNOWN',
                ]);
            }

            return $result;

        } catch (Exception $e) {
            $this->logOperation('error', 'Error procesando respuesta MIC/DTA', [
                'transaction_id' => $transaction->id,
                'error' => $e->getMessage(),
            ]);

            $result['errors'][] = 'Error procesando respuesta: ' . $e->getMessage();
            return $result;
        }
    }

    /**
     * Procesar respuesta exitosa
     */
    private function processSuccessResponse(WebserviceTransaction $transaction, array $soapResponse): void
    {
        try {
            // Actualizar transacción como exitosa
            $transaction->update([
                'status' => 'success',
                'completed_at' => now(),
                'external_reference' => $soapResponse['response_data']['confirmation_number'] ?? null,
            ]);

            // Crear registro de respuesta exitosa
            WebserviceResponse::create([
                'transaction_id' => $transaction->id,
                'response_type' => 'success',
                'confirmation_number' => $soapResponse['response_data']['confirmation_number'] ?? null,
                'customs_status' => 'processed',
                'customs_processed_at' => now(),
                'additional_data' => $soapResponse['response_data'] ?? [],
            ]);

            $this->logOperation('info', 'Respuesta exitosa procesada', [
                'transaction_id' => $transaction->id,
                'confirmation_number' => $soapResponse['response_data']['confirmation_number'] ?? null,
            ]);

        } catch (Exception $e) {
            $this->logOperation('error', 'Error procesando respuesta exitosa', [
                'transaction_id' => $transaction->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Procesar respuesta de error
     */
    private function processErrorResponse(WebserviceTransaction $transaction, array $soapResponse): void
    {
        try {
            // Actualizar transacción con error
            $transaction->update([
                'status' => 'error',
                'error_message' => $soapResponse['error_message'] ?? 'Error desconocido',
                'completed_at' => now(),
            ]);

            // Crear registro de respuesta de error
            WebserviceResponse::create([
                'transaction_id' => $transaction->id,
                'response_type' => 'error',
                'error_code' => $soapResponse['error_code'] ?? 'UNKNOWN',
                'error_message' => $soapResponse['error_message'] ?? 'Error desconocido',
                'customs_status' => 'error',
                'additional_data' => $soapResponse,
            ]);

            $this->logOperation('error', 'Respuesta de error procesada', [
                'transaction_id' => $transaction->id,
                'error_code' => $soapResponse['error_code'] ?? 'UNKNOWN',
                'error_message' => $soapResponse['error_message'] ?? 'Error desconocido',
            ]);

        } catch (Exception $e) {
            $this->logOperation('error', 'Error procesando respuesta de error', [
                'transaction_id' => $transaction->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Generar referencia MIC/DTA única
     */
    private function generateMicDtaReference(Shipment $shipment): string
    {
        $year = now()->format('Y');
        $office = '001'; // Código oficina por defecto
        $sequence = str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        
        // Calcular dígito verificador simple
        $checkDigit = substr(md5($year . $office . $sequence), -1);
        
        return 'ARG' . $year . $office . 'MIC' . $sequence . strtoupper($checkDigit);
    }

    // ========================================================================
    // MÉTODOS DE BYPASS Y TESTING (COMPATIBILIDAD)
    // ========================================================================

    /**
     * Determinar si debe usar bypass
     */
    private function shouldUseBypass(): bool
    {
        // Verificar bypass explícito de la empresa
        if ($this->company->shouldBypassTesting('argentina')) {
            return true;
        }

        // Verificar si es configuración de testing
        $argentinaData = $this->company->getArgentinaWebserviceData();
        return $this->company->isTestingConfiguration('argentina', $argentinaData);
    }

    /**
     * Método para logging específico de MIC/DTA
     */
    private function logMicDtaOperation(string $level, string $message, array $context = []): void
    {
        $context['operation'] = 'MIC_DTA_Argentina';
        $context['webservice_type'] = 'micdta';
        $context['country'] = 'AR';
        
        $this->logOperation($level, $message, $context, 'micdta_operation');
    }

    /**
     * Método de logging principal con category requerida
     */
    protected function logOperation(string $level, string $message, array $context = [], string $category = 'micdta_operation'): void
    {
        try {
            // Agregar información del servicio al contexto
            $context['service'] = 'ArgentinaMicDtaService';
            $context['company_id'] = $this->company->id;
            $context['company_name'] = $this->company->legal_name ?? $this->company->name;
            $context['user_id'] = $this->user->id;
            $context['timestamp'] = now()->toISOString();
            $context['category'] = $category;

            // Log a Laravel por defecto
            Log::$level($message, $context);

            // Si hay transaction_id en contexto, intentar log a webservice_logs
            $transactionId = $context['transaction_id'] ?? $this->currentTransactionId;
            
            if ($transactionId) {
                try {
                    WebserviceLog::create([
                        'transaction_id' => $transactionId,
                        'company_id' => $this->company->id,
                        'user_id' => $this->user->id,
                        'level' => $level,
                        'message' => $message,
                        'context' => $context,
                        'category' => $category,
                        'webservice_type' => $this->config['webservice_type'],
                        'country' => $this->config['country'],
                    ]);
                } catch (Exception $e) {
                    // Si falla el log a BD, solo logear a Laravel
                    Log::warning('Failed to log to webservice_logs table', [
                        'error' => $e->getMessage(),
                        'original_message' => $message,
                    ]);
                }
            }

        } catch (Exception $e) {
            // Fallback: solo log básico a Laravel
            Log::error('Failed to log operation', [
                'error' => $e->getMessage(),
                'original_message' => $message,
            ]);
        }
    }

    // ========================================================================
    // MÉTODOS AUXILIARES Y CONFIGURACIÓN
    // ========================================================================

    /**
     * Obtener configuración actual del servicio
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * Actualizar configuración del servicio
     */
    public function setConfig(array $config): void
    {
        $this->config = array_merge($this->config, $config);
    }

    /**
     * Obtener estadísticas del servicio
     */
    public function getServiceStatistics(): array
    {
        return [
            'company_id' => $this->company->id,
            'company_name' => $this->company->legal_name ?? $this->company->name,
            'user_id' => $this->user->id,
            'config' => $this->config,
            'webservice_url' => $this->getWebserviceUrl(),
            'tracks_available' => WebserviceTrack::where('status', 'generated')
                ->whereHas('webserviceTransaction', function($q) {
                    $q->where('company_id', $this->company->id);
                })->count(),
            'tracks_used' => WebserviceTrack::where('status', 'used_in_micdta')
                ->whereHas('webserviceTransaction', function($q) {
                    $q->where('company_id', $this->company->id);
                })->count(),
            'recent_transactions' => WebserviceTransaction::where('company_id', $this->company->id)
                ->where('webservice_type', 'micdta')
                ->where('created_at', '>=', now()->subDays(7))
                ->count(),
        ];
    }

    /**
     * Validar configuración del servicio
     */
    public function validateConfiguration(): array
    {
        $validation = [
            'is_valid' => true,
            'errors' => [],
            'warnings' => [],
        ];

        // Validar empresa
        if (!$this->company->active) {
            $validation['errors'][] = 'Empresa inactiva';
        }

        if (!$this->company->ws_active) {
            $validation['errors'][] = 'Webservices deshabilitados para la empresa';
        }

        // Validar CUIT
        $cuit = preg_replace('/[^0-9]/', '', $this->company->tax_id ?? '');
        if (strlen($cuit) !== 11) {
            $validation['errors'][] = 'CUIT inválido (debe tener 11 dígitos)';
        }

        // Validar certificados si son requeridos
        if ($this->config['require_certificate']) {
            try {
                $certificate = $this->certificateManager->getCertificate();
                if (!$certificate) {
                    $validation['errors'][] = 'Certificado requerido pero no configurado';
                }
            } catch (Exception $e) {
                $validation['errors'][] = 'Error validando certificado: ' . $e->getMessage();
            }
        }

        // Validar configuración Argentina
        try {
            $argentinaData = $this->company->getArgentinaWebserviceData();
            if (empty($argentinaData['cuit'])) {
                $validation['warnings'][] = 'CUIT Argentina no configurado específicamente';
            }
        } catch (Exception $e) {
            $validation['warnings'][] = 'Error obteniendo configuración Argentina: ' . $e->getMessage();
        }

        $validation['is_valid'] = empty($validation['errors']);

        return $validation;
    }

    /**
     * Limpiar TRACKs expirados
     */
    public function cleanupExpiredTracks(): array
    {
        $result = [
            'tracks_cleaned' => 0,
            'tracks_found' => 0,
        ];

        try {
            // Buscar TRACKs expirados (más de 24 horas sin usar)
            $expiredTracks = WebserviceTrack::where('status', 'generated')
                ->where('generated_at', '<', now()->subHours(24))
                ->whereHas('webserviceTransaction', function($q) {
                    $q->where('company_id', $this->company->id);
                })
                ->get();

            $result['tracks_found'] = $expiredTracks->count();

            if ($expiredTracks->count() > 0) {
                // Marcar como expirados
                WebserviceTrack::whereIn('id', $expiredTracks->pluck('id'))
                    ->update([
                        'status' => 'expired',
                        'used_at' => now(),
                        'notes' => 'TRACK expirado automáticamente por cleanup',
                    ]);

                $result['tracks_cleaned'] = $expiredTracks->count();

                $this->logOperation('info', 'TRACKs expirados limpiados', [
                    'tracks_cleaned' => $result['tracks_cleaned'],
                ]);
            }

        } catch (Exception $e) {
            $this->logOperation('error', 'Error limpiando TRACKs expirados', [
                'error' => $e->getMessage(),
            ]);
        }

        return $result;
    }
}