<?php

namespace App\Services\Simple;

use App\Models\Company;
use App\Models\User;
use App\Models\Voyage;
use App\Models\WebserviceTransaction;
use App\Models\WebserviceResponse;
use App\Models\WebserviceLog;
use App\Services\Simple\BaseWebserviceService;
use App\Services\Simple\SimpleXmlGenerator;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * CONSULTAS DE ESTADO MIC/DTA Argentina AFIP
 * 
 * Servicio especializado para consultar el estado de MIC/DTA ya enviados a AFIP.
 * Extiende el sistema existente sin modificar ArgentinaMicDtaService principal.
 * 
 * FUNCIONALIDADES:
 * - Consultar estado de MIC/DTA por external_reference
 * - Consultar estado por track_number específico
 * - Actualizar estado en WebserviceTransaction
 * - Logging completo de consultas
 * - Manejo de errores AFIP
 * 
 * REUTILIZA:
 * - SoapClientService (URLs y configuración AFIP existentes)
 * - BaseWebserviceService (logging y transacciones)
 * - Estructura de WebserviceTransaction/WebserviceTrack
 * 
 * FLUJO:
 * 1. Buscar transacciones MIC/DTA exitosas
 * 2. Usar external_reference para consultar AFIP
 * 3. Procesar respuesta y actualizar estado
 * 4. Registrar resultado en WebserviceResponse
 */
class ArgentinaMicDtaStatusService extends BaseWebserviceService
{

    /**
     * Configuración específica para consultas de estado
     */
    protected function getWebserviceConfig(): array
    {
        return [
            'webservice_type' => 'consulta',
            'country' => 'AR',
            'environment' => WebserviceEnvironment::resolve($this->company),
            'soap_action' => 'Ar.Gob.Afip.Dga.wgesregsintia2/ConsultarEstadoMicDta',
            'timeout_seconds' => 30,
            'require_certificate' => true,
        ];
    }

    protected function getWebserviceType(): string
    {
        return 'consulta';
    }

    protected function getCountry(): string
    {
        return 'AR';
    }

    protected function getWsdlUrl(): string
    {
        return WebserviceEnvironment::argentinaEndpoint($this->company, 'wgesregsintia2') . '?wsdl';
    }

    /**
     * Status consulta transacciones existentes, no registra envíos de un viaje.
     * El flujo público vigente es consultarEstadoTransacciones().
     */
    protected function sendSpecificWebservice(Voyage $voyage, array $options = []): array
    {
        return [
            'success' => false,
            'error_code' => 'STATUS_REQUIRES_TRANSACTION_QUERY',
            'error_message' => 'Utilice consultarEstadoTransacciones() para consultar estados MIC/DTA.',
        ];
    }

    /**
     * Consultar estado de transacciones MIC/DTA pendientes
     * 
     * @param array $transactionIds IDs específicos a consultar (opcional)
     * @return array Resultados de consultas
     */
    public function consultarEstadoTransacciones(array $transactionIds = []): array
    {
        $this->logOperation('info', 'Iniciando consulta de estados MIC/DTA', [
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'specific_transactions' => count($transactionIds),
        ]);

        try {
            // Buscar transacciones MIC/DTA exitosas que requieren seguimiento
            $transacciones = $this->obtenerTransaccionesPendientes($transactionIds);
            
            if ($transacciones->isEmpty()) {
                return [
                    'success' => false,
                    'consultas_exitosas' => 0,
                    'consultas_error' => 0,
                    'error' => 'No hay transacciones MIC/DTA pendientes de consulta',
                    'message' => 'No hay transacciones MIC/DTA pendientes de consulta',
                    'consultas_realizadas' => 0,
                ];
            }

            $resultados = [];
            $consultasExitosas = 0;
            $consultasError = 0;

            foreach ($transacciones as $transaccion) {
                try {
                    $resultado = $this->consultarEstadoIndividual($transaccion);
                    $resultados[] = $resultado;
                    
                    if ($resultado['success']) {
                        $consultasExitosas++;
                    } else {
                        $consultasError++;
                    }

                } catch (Exception $e) {
                    $consultasError++;
                    $resultados[] = [
                        'success' => false,
                        'transaction_id' => $transaccion->id,
                        'error' => $e->getMessage(),
                    ];
                    
                    $this->logOperation('error', 'Error consultando transacción individual', [
                        'transaction_id' => $transaccion->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $this->logOperation('info', 'Consulta de estados completada', [
                'transacciones_procesadas' => count($transacciones),
                'consultas_exitosas' => $consultasExitosas,
                'consultas_error' => $consultasError,
            ]);

            return [
                'success' => $consultasExitosas > 0 && $consultasError === 0,
                'error' => collect($resultados)->firstWhere('success', false)['error'] ?? null,
                'error_code' => collect($resultados)->firstWhere('success', false)['error_code'] ?? null,
                'consulta_transaction_id' => collect($resultados)->firstWhere('success', false)['consulta_transaction_id'] ?? null,
                'transacciones_procesadas' => count($transacciones),
                'consultas_exitosas' => $consultasExitosas,
                'consultas_error' => $consultasError,
                'resultados' => $resultados,
            ];

        } catch (Exception $e) {
            $this->logOperation('error', 'Error general en consulta de estados', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'consultas_realizadas' => 0,
            ];
        }
    }

    /**
     * Consultar estado de una transacción específica
     */
    private function consultarEstadoIndividual(WebserviceTransaction $transaccion): array
    {
        $this->logOperation('info', 'Consultando estado individual', [
            'transaction_id' => $transaccion->id,
            'external_reference' => $transaccion->external_reference,
            'transaction_date' => $transaccion->sent_at,
        ]);

        $consultaTransaction = null;
        try {
            // Crear transacción de consulta
            $consultaTransaction = $this->crearTransaccionConsulta($transaccion);
            
            // Generar XML de consulta
            $xmlConsulta = $this->generarXmlConsulta($transaccion->external_reference);
            
            // Enviar consulta a AFIP
            $this->soapClient = $this->createSoapClient();
            $respuestaAfip = $this->enviarConsultaSoap($consultaTransaction, $this->soapClient, $xmlConsulta);
            
            if ($respuestaAfip['success']) {
                // Procesar respuesta exitosa
                $estadoAfip = $this->procesarRespuestaEstado($respuestaAfip['response_data']);
                $this->actualizarEstadoTransaccion($transaccion, $estadoAfip);
                $this->registrarRespuestaConsulta($consultaTransaction, $estadoAfip, true);

                return [
                    'success' => true,
                    'transaction_id' => $transaccion->id,
                    'external_reference' => $transaccion->external_reference,
                    'estado_afip' => $estadoAfip,
                    'consulta_transaction_id' => $consultaTransaction->id,
                ];
            } else {
                // Procesar error
                $this->registrarRespuestaConsulta($consultaTransaction, $respuestaAfip, false);
                
                return [
                    'success' => false,
                    'transaction_id' => $transaccion->id,
                    'external_reference' => $transaccion->external_reference,
                    'error' => $respuestaAfip['error'] ?? 'Error desconocido en consulta AFIP',
                    'error_code' => $consultaTransaction->error_code,
                    'consulta_transaction_id' => $consultaTransaction->id,
                ];
            }

        } catch (Exception $e) {
            if ($consultaTransaction) {
                $consultaTransaction->update(['status' => 'error', 'error_message' => $e->getMessage(),
                    'error_code' => $consultaTransaction->error_code ?: 'STATUS_QUERY_ERROR']);
            }
            $this->logOperation('error', 'Error en consulta individual', [
                'transaction_id' => $transaccion->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'transaction_id' => $transaccion->id,
                'consulta_transaction_id' => $consultaTransaction?->id,
                'error_code' => $consultaTransaction?->error_code ?? 'STATUS_QUERY_ERROR',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Obtener transacciones MIC/DTA que requieren consulta de estado
     */
    private function obtenerTransaccionesPendientes(array $transactionIds = [])
    {
        $query = WebserviceTransaction::where('company_id', $this->company->id)
            ->where('webservice_type', 'micdta')
            ->where('status', 'sent')
            ->whereNotNull('external_reference')
            ->whereNotNull('sent_at')
            ->where('sent_at', '>=', now()->subDays(30)); // Últimos 30 días

        if (!empty($transactionIds)) {
            $query->whereIn('id', $transactionIds);
        }

        return $query->orderBy('sent_at', 'desc')->limit(50)->get();
    }

    /**
     * Crear transacción para registrar la consulta
     */
    private function crearTransaccionConsulta(WebserviceTransaction $transaccionOriginal): WebserviceTransaction
    {
        return WebserviceTransaction::create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'voyage_id' => $transaccionOriginal->voyage_id,
            'shipment_id' => $transaccionOriginal->shipment_id,
            'transaction_id' => 'CONSULTA_' . (string) \Illuminate\Support\Str::uuid(),
            'external_reference' => $transaccionOriginal->external_reference,
            'webservice_type' => 'consulta',
            'country' => 'AR',
            'status' => 'pending',
            'soap_action' => $this->config['soap_action'],
            'webservice_url' => $this->getWsdlUrl(),
            'environment' => $this->config['environment'],
            'additional_metadata' => [
                'original_transaction_id' => $transaccionOriginal->id,
                'original_external_reference' => $transaccionOriginal->external_reference,
                'consultation_type' => 'status_check',
            ],
        ]);
    }

    /**
     * Generar XML para consulta de estado
     */
    private function generarXmlConsulta(string $externalReference): string
    {
        return $this->xmlSerializer->createConsultarEstadoMicDtaXml($externalReference);
    }

    /**
     * Enviar consulta SOAP a AFIP
     */
    private function enviarConsultaSoap(WebserviceTransaction $transaction, $soapClient, string $xmlContent): array
    {
        $startTime = microtime(true);
        
        try {
            $transaction->update(['status' => 'sending', 'sent_at' => now(), 'request_xml' => $xmlContent]);

            $response = $soapClient->__doRequest($xmlContent,
                WebserviceEnvironment::argentinaEndpoint($this->company, 'wgesregsintia2'),
                $this->config['soap_action'], SOAP_1_2, false);
            $transaction->update(['response_xml' => $response ?: null]);
            if (!$response) {
                throw new Exception('La consulta MIC/DTA no recibió respuesta SOAP.');
            }
            $document = new \DOMDocument();
            if (!@$document->loadXML($response, LIBXML_NONET)) {
                throw new Exception('La consulta MIC/DTA recibió una respuesta XML inválida.');
            }
            $xpath = new \DOMXPath($document);
            if ($xpath->query('//*[local-name()="Fault"]')->length) {
                $detail = $xpath->evaluate('string(//*[local-name()="faultstring"] | //*[local-name()="Reason"]/*[local-name()="Text"])');
                $code = $xpath->evaluate('string(//*[local-name()="faultcode"] | //*[local-name()="Code"]/*[local-name()="Value"])');
                $transaction->update(['error_code' => mb_substr($code ?: 'SOAP_FAULT', 0, 50)]);
                throw new Exception($detail ?: 'SOAP Fault en consulta MIC/DTA');
            }
            
            $endTime = microtime(true);
            $responseTime = round(($endTime - $startTime) * 1000);

            $transaction->update([
                'status' => 'sent',
                'response_at' => now(),
                'response_time_ms' => $responseTime,
                'request_xml' => $xmlContent,
                'response_xml' => $response,
            ]);

            return [
                'success' => true,
                'response_data' => $response,
                'response_time_ms' => $responseTime,
            ];

        } catch (Exception $e) {
            $transaction->update([
                'status' => 'error',
                'error_code' => $transaction->error_code ?: 'STATUS_QUERY_ERROR',
                'error_message' => $e->getMessage(),
            ]);

            $this->logOperation('error', 'Error en envío SOAP consulta', [
                'transaction_id' => $transaction->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Procesar respuesta de estado de AFIP
     */
    private function procesarRespuestaEstado(string $responseXml): array
    {
        $estado = [
            'codigo_estado' => null,
            'descripcion_estado' => null,
            'fecha_procesamiento' => null,
            'observaciones' => null,
            'estado_normalizado' => 'unknown',
        ];

        $document = new \DOMDocument();
        if (!@$document->loadXML($responseXml, LIBXML_NONET)) {
            throw new Exception('Respuesta de estado MIC/DTA inválida.');
        }
        $xpath = new \DOMXPath($document);
        foreach (['EstadoMicDta' => 'codigo_estado', 'DescripcionEstado' => 'descripcion_estado',
            'FechaProcesamiento' => 'fecha_procesamiento', 'Observaciones' => 'observaciones'] as $tag => $field) {
            $estado[$field] = trim($xpath->evaluate('string(//*[local-name()="'.$tag.'"])')) ?: null;
        }
        if ($estado['codigo_estado'] === null) {
            throw new Exception('La respuesta de consulta MIC/DTA no contiene EstadoMicDta.');
        }
        $estado['estado_normalizado'] = $this->normalizarEstadoAfip($estado['codigo_estado']);

        return $estado;
    }

    /**
     * Normalizar estado AFIP a estados del sistema
     */
    private function normalizarEstadoAfip(string $codigoAfip): string
    {
        $mapeoEstados = [
            'ACEPTADO' => 'success',
            'RECHAZADO' => 'rejected',
            'PROCESANDO' => 'processing',
            'PENDIENTE' => 'pending',
            'ERROR' => 'error',
        ];

        return $mapeoEstados[strtoupper($codigoAfip)] ?? 'unknown';
    }

    /**
     * Actualizar estado de la transacción original
     */
    private function actualizarEstadoTransaccion(WebserviceTransaction $transaccion, array $estadoAfip): void
    {
        $nuevoEstado = $estadoAfip['estado_normalizado'];
        
        if ($nuevoEstado !== 'unknown' && $transaccion->status !== $nuevoEstado) {
            $transaccion->update([
                'status' => $nuevoEstado,
                'additional_metadata' => array_merge(
                    $transaccion->additional_metadata ?? [],
                    [
                        'last_status_check' => now()->toISOString(),
                        'afip_status' => $estadoAfip,
                    ]
                ),
            ]);

            $this->logOperation('info', 'Estado de transacción actualizado', [
                'transaction_id' => $transaccion->id,
                'old_status' => $transaccion->status,
                'new_status' => $nuevoEstado,
                'afip_code' => $estadoAfip['codigo_estado'],
            ]);
        }
    }

    /**
     * Registrar respuesta de consulta
     */
    private function registrarRespuestaConsulta(WebserviceTransaction $consultaTransaction, array $data, bool $success): void
    {
        WebserviceResponse::create([
            'transaction_id' => $consultaTransaction->id,
            'response_type' => $success ? 'success' : 'system_error',
            'processing_status' => 'completed',
            'customs_status' => $data['codigo_estado'] ?? null,
            'customs_metadata' => $data,
            'processed_at' => now(),
        ]);
    }

    /**
     * Validaciones específicas (requerido por BaseWebserviceService)
     */
    protected function validateSpecificData($data): array
    {
        return ['errors' => [], 'warnings' => []];
    }
}