<?php

namespace App\Services\Simple;

use App\Models\Voyage;
use App\Models\WebserviceResponse;
use Exception;
use SoapClient;
use SoapFault;
use Illuminate\Support\Facades\Log;

/**
 * Sistema Simple – Paraguay DNA (GDSF)
 *
 * Método de referencia (Manual GDSF): EnviarMensajeFluvial(codigo, version, viaje, xml, Autenticacion)
 * - codigo: 'XFFM' | 'XFBL' | 'XFBT' | 'XISP' | 'XRSP' | 'XFCT'
 * - version: p.ej. '1.0'
 * - viaje: identificador de viaje (puede ser null en el primer envío de XFFM)
 * - xml: contenido XML del mensaje (NO inventamos etiquetas aquí)
 * - Autenticacion: { idUsuario, ticket, firma }
 *
 * Esta clase se integra al flujo del "sistema simple" heredando BaseWebserviceService:
 * - validateInput → validateSpecificData → sendSpecificWebservice → persistencia de respuesta y logs
 */
class ParaguayDnaService extends BaseWebserviceService
{
    /** Identificador del tipo de servicio (para auditoría, estados, etc.) */
    protected string $webserviceType = 'paraguay_gdsf';

    /** País */
    protected string $country = 'PY';

    /**
     * Configuración específica para Paraguay (mezclada con BASE_CONFIG de la clase base).
     * Espera en config/services.php:
     *
     * 'paraguay' => [
     *   'environment'         => 'testing', // o 'production'
     *   'wsdl'                => 'https://.../serviciogdsf?wsdl',
     *   'soap_method'         => 'EnviarMensajeFluvial',
     *   'require_certificate' => true,
     *   'auth' => [
     *       'idUsuario' => env('DNA_ID_USUARIO'),
     *       'ticket'    => env('DNA_TICKET'),
     *       'firma'     => env('DNA_FIRMA'),
     *   ],
     * ]
     */
    protected function getWebserviceConfig(): array
    {
        return array_merge(self::BASE_CONFIG, [
            'webservice_type'      => $this->webserviceType,
            'country'              => $this->country,
            'environment'          => config('services.paraguay.environment', 'testing'),
            'webservice_url'       => config('services.paraguay.wsdl'),
            'soap_method'          => config('services.paraguay.soap_method', 'EnviarMensajeFluvial'),
            'require_certificate'  => config('services.paraguay.require_certificate', true),
            'auth'                 => config('services.paraguay.auth', []),
        ]);
    }

    /** URL/WSDL del servicio GDSF */
    protected function getWsdlUrl(): string
    {
        $url = $this->config['webservice_url'] ?? '';
        if (!$url) {
            throw new Exception('Config faltante: services.paraguay.wsdl');
        }
        return $url;
    }

    /** Validaciones mínimas y generales (sin inventar negocio) */
    protected function validateSpecificData(Voyage $voyage): array
    {
        $errors = [];
        $warnings = [];

        if (!$voyage->voyage_number) {
            $errors[] = 'Voyage sin número (voyage_number).';
        }

        if (($this->config['require_certificate'] ?? false) && empty($this->company->certificate_path)) {
            $errors[] = 'Falta certificado digital (.p12) de la empresa para Paraguay.';
        }

        return compact('errors', 'warnings');
    }

    /**
     * Envío específico GDSF: se debe proveer XML y parámetros del método.
     *
     * $options obligatorios:
     *  - 'codigo'  => string (XFFM/XFBL/...)
     *  - 'version' => string (ej: '1.0')
     *  - 'xml'     => string (XML completo del mensaje)
     *  - 'viaje'   => string|null
     *
     * Autenticación:
     *  - Por config('services.paraguay.auth') o por $options['auth'] = ['idUsuario','ticket','firma']
     */
    protected function sendSpecificWebservice(Voyage $voyage, array $options = []): array
    {
        $codigo  = $options['codigo']  ?? null;
        $version = $options['version'] ?? null;
        $xml     = $options['xml']     ?? null;
        $viaje   = $options['viaje']   ?? null;

        if (!$codigo || !$version || !$xml) {
            return [
                'success' => false,
                'error_message' => 'Parámetros incompletos: se requieren codigo, version y xml.',
                'error_code' => 'MISSING_PARAMS',
            ];
        }

        // Autenticación: prioriza $options['auth'], luego config
        $auth = $options['auth'] ?? $this->config['auth'] ?? [];
        $idUsuario = $auth['idUsuario'] ?? null;
        $ticket    = $auth['ticket']    ?? null;
        $firma     = $auth['firma']     ?? null;

        if (!$idUsuario || !$ticket || !$firma) {
            return [
                'success' => false,
                'error_message' => 'Faltan credenciales DNA (idUsuario, ticket, firma).',
                'error_code' => 'MISSING_AUTH',
            ];
        }

        try {
            // Cliente SOAP (usa createSoapClient de la base para contexto SSL/certificado)
            $client = $this->createSoapClient($this->getWsdlUrl(), [
                'trace' => 1, // útil para depurar
            ]);

            $soapMethod = $this->config['soap_method'] ?? 'EnviarMensajeFluvial';

            // Parámetros EXACTOS del método (no inventamos nombres)
            $params = [[
                'codigo'        => $codigo,
                'version'       => $version,
                'viaje'         => $viaje,
                'xml'           => $xml,
                'Autenticacion' => [
                    'idUsuario' => (string)$idUsuario,
                    'ticket'    => (string)$ticket,
                    'firma'     => (string)$firma,
                ],
            ]];

            // Invocar
            $result = $client->__soapCall($soapMethod, $params);

            // Respuesta cruda
            $rawResponse = $client->__getLastResponse() ?: (string)json_encode($result);

            // Intento mínimo de extraer el campo 'xml' (si lo retorna así el servicio)
            $parsed = ['xml' => null];
            if (is_object($result) && property_exists($result, 'xml')) {
                $parsed['xml'] = $result->xml;
            }

            // Persistir respuesta (no interrumpir si falla)
            try {
                WebserviceResponse::create([
                    'transaction_id'       => $this->currentTransactionId,
                    'response_type'        => 'success',
                    'processing_status'    => 'completed',
                    'requires_action'      => false,
                    'voyage_number'        => $voyage->voyage_number,
                    'customs_metadata'     => [
                        'request'      => [
                            'codigo'  => $codigo,
                            'version' => $version,
                            'viaje'   => $viaje,
                        ],
                        'request_xml'   => $xml,
                        'raw_response'  => $rawResponse,
                        'parsed'        => $parsed,
                        'soap_method'   => $soapMethod,
                    ],
                    'customs_status'       => 'sent',
                    'customs_processed_at' => now(),
                    'processed_at'         => now(),
                    'created_at'           => now(),
                    'updated_at'           => now(),
                ]);
            } catch (Exception $e) {
                Log::warning('ParaguayDnaService: error al persistir WebserviceResponse', [
                    'transaction_id' => $this->currentTransactionId,
                    'error'          => $e->getMessage(),
                ]);
            }

            $this->logOperation('info', 'GDSF EnviarMensajeFluvial OK', [
                'transaction_id' => $this->currentTransactionId,
                'voyage_id'      => $voyage->id,
                'codigo'         => $codigo,
                'version'        => $version,
                'viaje'          => $viaje,
            ]);

            return [
                'success'       => true,
                'response_data' => [
                    'raw_response' => $rawResponse,
                    'parsed'       => $parsed,
                ],
            ];

        } catch (SoapFault $sf) {
            $this->logOperation('error', 'GDSF SOAP Fault', [
                'transaction_id' => $this->currentTransactionId,
                'faultcode'      => $sf->faultcode ?? null,
                'faultstring'    => $sf->faultstring ?? $sf->getMessage(),
            ]);

            return [
                'success'       => false,
                'error_message' => $sf->faultstring ?? $sf->getMessage(),
                'error_code'    => $sf->faultcode ?? 'SOAP_FAULT',
            ];
        } catch (Exception $e) {
            $this->logOperation('error', 'GDSF Error general', [
                'transaction_id' => $this->currentTransactionId,
                'error'          => $e->getMessage(),
            ]);

            return [
                'success'       => false,
                'error_message' => $e->getMessage(),
                'error_code'    => 'GENERAL_ERROR',
            ];
        }
    }

    /** Helper: envío de carátula XFFM. $viaje null para primer envío */
    public function sendXffm(Voyage $voyage, string $xml, ?string $viaje = null, string $version = '1.0', array $auth = []): array
    {
        return $this->send($voyage, [
            'codigo'  => 'XFFM',
            'version' => $version,
            'xml'     => $xml,
            'viaje'   => $viaje,
            'auth'    => $auth,
        ]);
    }

    /** Helper genérico para cualquier código GDSF soportado (XML provisto) */
    public function sendGeneric(Voyage $voyage, string $codigo, string $xml, ?string $viaje = null, string $version = '1.0', array $auth = []): array
    {
        return $this->send($voyage, [
            'codigo'  => $codigo,
            'version' => $version,
            'xml'     => $xml,
            'viaje'   => $viaje,
            'auth'    => $auth,
        ]);
    }
}
