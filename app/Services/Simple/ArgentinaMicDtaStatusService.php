<?php

namespace App\Services\Simple;

use App\Models\Company;
use App\Models\User;
use App\Models\WebserviceTransaction;
use App\Models\WebserviceResponse;
use App\Models\WebserviceLog;
use App\Services\Simple\BaseWebserviceService;
use App\Services\Webservice\SoapClientService;
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
    private SoapClientService $soapClient;
    private SimpleXmlGenerator $xmlGenerator;

    /**
     * Configuración específica para consultas de estado
     */
    protected function getWebserviceConfig(): array
    {
        return [
            'webservice_type' => 'micdta_status',
            'country' => 'AR',
            'environment' => 'testing',
            'soap_action' => 'Ar.Gob.Afip.Dga.wgesregsintia2/ConsultarEstadoMicDta',
            'timeout_seconds' => 30,
            'require_certificate' => true,
        ];
    }

    protected function getWebserviceType(): string
    {
        return 'micdta_status';
    }

    protected function getCountry(): string
    {
        return 'AR';
    }

    protected function getWsdlUrl(): string
    {
        // Usar la misma URL que MIC/DTA principal
        $environment = $this->config['environment'] ?? 'testing';
        $urls = [
            'testing' => 'https://wsaduhomoext.afip.gob.ar/DIAV2/wgesregsintia2/wgesregsintia2.asmx?wsdl',
            'production' => 'https://wsaduext.afip.gob.ar/DIAV2/wgesregsintia2/wgesregsintia2.asmx?wsdl',
        ];
        return $urls[$environment] ?? $urls['testing'];
    }

    public function __construct(Company $company, User $user, array $config = [])
    {
        parent::__construct($company, $user, $config);
        
        // Inicializar servicios reutilizando infraestructura existente
        $this->soapClient = new SoapClientService($company);
        $this->xmlGenerator = new SimpleXmlGenerator($company, $this->config);
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
                    'success' => true,
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
                'success' => true,
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

        try {
            // Crear transacción de consulta
            $consultaTransaction = $this->crearTransaccionConsulta($transaccion);
            
            // Generar XML de consulta
            $xmlConsulta = $this->generarXmlConsulta($transaccion->external_reference);
            
            // Enviar consulta a AFIP
            $soapClient = $this->soapClient->createClient('micdta', $this->config['environment']);
            $respuestaAfip = $this->enviarConsultaSoap($consultaTransaction, $soapClient, $xmlConsulta);
            
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
                    'consulta_transaction_id' => $consultaTransaction->id,
                ];
            }

        } catch (Exception $e) {
            $this->logOperation('error', 'Error en consulta individual', [
                'transaction_id' => $transaccion->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'transaction_id' => $transaccion->id,
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
            'transaction_id' => 'CONSULTA_' . $transaccionOriginal->transaction_id . '_' . time(),
            'external_reference' => $transaccionOriginal->external_reference,
            'webservice_type' => 'micdta_status',
            'country' => 'AR',
            'status' => 'pending',
            'method_name' => 'ConsultarEstadoMicDta',
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
        // XML básico para consulta de estado AFIP
        // Basado en el patrón del sistema existente
        return '<?xml version="1.0" encoding="utf-8"?>
<soap:Envelope xmlns:soap="http://www.w3.org/2003/05/soap-envelope" xmlns:wges="Ar.Gob.Afip.Dga.wgesregsintia2">
    <soap:Header>
        <wges:AuthSoapHd>
            <wges:ticket>TESTING_TOKEN_' . $this->company->id . '_' . time() . '</wges:ticket>
            <wges:sign>TESTING_SIGN_' . $this->company->tax_id . '_' . time() . '</wges:sign>
            <wges:cuitRepresentado>' . $this->company->tax_id . '</wges:cuitRepresentado>
        </wges:AuthSoapHd>
    </soap:Header>
    <soap:Body>
        <wges:ConsultarEstadoMicDta>
            <wges:consultaEstadoParam>
                <wges:MicDtaId>' . $externalReference . '</wges:MicDtaId>
                <wges:TipoConsulta>ESTADO</wges:TipoConsulta>
            </wges:consultaEstadoParam>
        </wges:ConsultarEstadoMicDta>
    </soap:Body>
</soap:Envelope>';
    }

    /**
     * Enviar consulta SOAP a AFIP
     */
    private function enviarConsultaSoap(WebserviceTransaction $transaction, $soapClient, string $xmlContent): array
    {
        $startTime = microtime(true);
        
        try {
            $transaction->update(['status' => 'sending', 'sent_at' => now()]);

            // Enviar usando SoapClientService existente
            $result = $this->soapClient->sendRequest($transaction, 'ConsultarEstadoMicDta', ['xmlParam' => $xmlContent]);
            
            $endTime = microtime(true);
            $responseTime = round(($endTime - $startTime) * 1000);

            $transaction->update([
                'status' => 'sent',
                'response_at' => now(),
                'response_time_ms' => $responseTime,
                'request_xml' => $xmlContent,
                'response_xml' => $result['response_xml'] ?? null,
            ]);

            return [
                'success' => true,
                'response_data' => $result['response_xml'] ?? '',
                'response_time_ms' => $responseTime,
            ];

        } catch (Exception $e) {
            $transaction->update([
                'status' => 'error',
                'error_count' => ($transaction->error_count ?? 0) + 1,
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

        try {
            if (empty($responseXml)) {
                return $estado;
            }

            // Parsear XML de respuesta AFIP
            if (preg_match('/<EstadoMicDta>([^<]+)<\/EstadoMicDta>/', $responseXml, $matches)) {
                $estado['codigo_estado'] = $matches[1];
                $estado['estado_normalizado'] = $this->normalizarEstadoAfip($matches[1]);
            }

            if (preg_match('/<DescripcionEstado>([^<]+)<\/DescripcionEstado>/', $responseXml, $matches)) {
                $estado['descripcion_estado'] = $matches[1];
            }

            if (preg_match('/<FechaProcesamiento>([^<]+)<\/FechaProcesamiento>/', $responseXml, $matches)) {
                $estado['fecha_procesamiento'] = $matches[1];
            }

            if (preg_match('/<Observaciones>([^<]+)<\/Observaciones>/', $responseXml, $matches)) {
                $estado['observaciones'] = $matches[1];
            }

        } catch (Exception $e) {
            $this->logOperation('warning', 'Error procesando respuesta de estado', [
                'error' => $e->getMessage(),
                'response_length' => strlen($responseXml),
            ]);
        }

        return $estado;
    }

    /**
     * Normalizar estado AFIP a estados del sistema
     */
    private function normalizarEstadoAfip(string $codigoAfip): string
    {
        $mapeoEstados = [
            'ACEPTADO' => 'approved',
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
            'webservice_transaction_id' => $consultaTransaction->id,
            'response_code' => $success ? '200' : '500',
            'response_message' => $success ? 'Consulta exitosa' : 'Error en consulta',
            'response_data' => $data,
            'is_success' => $success,
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