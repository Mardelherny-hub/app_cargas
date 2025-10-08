<?php

namespace App\Services\Simple;

use App\Models\Voyage;
use Exception;
use SoapClient;
use SoapFault;

/**
 * Sistema Simple – Paraguay DNA (GDSF)
 *
 * EnviarMensajeFluvial(codigo, version, viaje, xml, Autenticacion)
 * y, opcionalmente, DocumentoIMG(...) si en $options['docimg'] se provee el adjunto PDF.
 *
 * NOTAS IMPORTANTES:
 * - No se inventan tags XML del mensaje GDSF: el XML debe venir en $options['xml'].
 * - Autenticación (idUsuario, ticket, firma) debe venir en $options['auth'].
 * - Para DocumentoIMG, el payload se arma de forma flexible; si necesitás un shape exacto,
 *   podés pasar $options['docimg']['soap_params'] y se usa tal cual.
 */
class ParaguayDnaService extends BaseWebserviceService
{
    /** Identificador del tipo de servicio (para auditoría/estados) */
    protected string $webserviceType = 'paraguay_gdsf';

    /** País */
    protected string $country = 'PY';

    /**
     * Config específica (se mezcla con BASE_CONFIG si la clase base la usa).
     *
     * Espera en config/services.php, p.ej.:
     * 'paraguay' => [
     *   'environment'         => 'testing', // 'production'
     *   'wsdl'                => 'https://securetest.aduana.gov.py/wsdl/gdsf/serviciogdsf',
     *   'soap_method'         => 'EnviarMensajeFluvial',
     *   'soap_docimg_method'  => 'DocumentoIMG',
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
            'soap_docimg_method'   => config('services.paraguay.soap_docimg_method', 'DocumentoIMG'),
            'require_certificate'  => config('services.paraguay.require_certificate', true),
            'auth'                 => config('services.paraguay.auth', []),
        ]);
    }

    /** URL/WSDL de GDSF */
    protected function getWsdlUrl(): string
    {
        $url = $this->config['webservice_url'] ?? '';
        if (!$url) {
            throw new Exception('Config faltante: services.paraguay.wsdl');
        }
        return $url;
    }

    /** Validaciones mínimas (no inventamos negocio) */
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
     * Envío específico:
     * 1) EnviarMensajeFluvial (obligatorio).
     * 2) Si llega $options['docimg'], enviar DocumentoIMG (adjunto PDF) luego del punto 1.
     *
     * $options requeridos:
     *  - 'codigo'  => string (XFFM/XFBL/XFBT/XISP/XRSP/XFCT)
     *  - 'version' => string (ej: '1.0' / el que corresponda)
     *  - 'xml'     => string (XML ya construido)
     *  - 'viaje'   => string|null
     *  - 'auth'    => ['idUsuario','ticket','firma']
     *
     * $options opcionales:
     *  - 'docimg'  => [
     *        'enabled'      => bool,
     *        'nroDocumento' => string,                // requerido si enabled=true
     *        'file'         => [
     *             'filename'   => string,             // ej. adjunto.pdf
     *             'mimetype'   => 'application/pdf',
     *             'size'       => int,
     *             'base64'     => string,             // contenido en base64
     *        ],
     *        // Si se necesita control total del payload SOAP, se puede pasar:
     *        // 'soap_params' => array                 // se usará tal cual
     *    ]
     */
    protected function sendSpecificWebservice(Voyage $voyage, array $options = []): array
    {
        $codigo  = $options['codigo']  ?? null;
        $version = $options['version'] ?? null;
        $xml     = $options['xml']     ?? null;
        $viaje   = $options['viaje']   ?? null;

        // auth
        $auth = $options['auth'] ?? $this->config['auth'] ?? [];
        $idUsuario = $auth['idUsuario'] ?? null;
        $ticket    = $auth['ticket']    ?? null;
        $firma     = $auth['firma']     ?? null;

        // chequear requeridos
        foreach (['codigo','version','xml'] as $k) {
            if (empty($$k)) {
                throw new Exception("Falta parámetro requerido: {$k}");
            }
        }
        foreach (['idUsuario','ticket','firma'] as $k) {
            if (empty($$k)) {
                throw new Exception("Falta credencial DNA: {$k}");
            }
        }

        // 1) Cliente SOAP
        $client = $this->createSoapClient($this->getWsdlUrl(), ['trace' => 1]);

        // 2) EnviarMensajeFluvial
        $soapMethod = $this->config['soap_method'] ?? 'EnviarMensajeFluvial';
        $payload = [[
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

        try {
            $resultMain = $client->__soapCall($soapMethod, $payload);
        } catch (SoapFault $sf) {
            // falló el envío principal → devolvemos error
            return [
                'success'       => false,
                'error_message' => $sf->faultstring ?? $sf->getMessage(),
                'error_code'    => $sf->faultcode ?? 'SOAP_FAULT',
                'raw_response'  => method_exists($client, '__getLastResponse') ? $client->__getLastResponse() : null,
            ];
        } catch (Exception $e) {
            return [
                'success'       => false,
                'error_message' => $e->getMessage(),
                'error_code'    => 'GENERAL_ERROR',
                'raw_response'  => method_exists($client, '__getLastResponse') ? $client->__getLastResponse() : null,
            ];
        }

        $rawResponseMain = method_exists($client, '__getLastResponse') ? $client->__getLastResponse() : null;
        $parsedMain = ['xml' => null];
        // si la respuesta trae un 'xml' en el objeto, lo exponemos (defensivo)
        if (is_object($resultMain)) {
            $container = property_exists($resultMain, 'EnviarMensajeFluvialResult') ? $resultMain->EnviarMensajeFluvialResult : $resultMain;
            if (is_object($container) && property_exists($container, 'xml')) {
                $parsedMain['xml'] = $container->xml;
            }
        }

        // 3) Si corresponde, mandar DocumentoIMG
        $docimgResult = null;
        $docimgSent   = false;

        if (!empty($options['docimg']) && !empty($options['docimg']['enabled'])) {
            $docimg = $options['docimg'];
            $docMethod = $this->config['soap_docimg_method'] ?? 'DocumentoIMG';

            try {
                $docParams = $this->buildDocumentoImgParams($docimg, $idUsuario, $ticket, $firma);

                // Si se pasó un shape "soap_params" explícito, se usa tal cual.
                $paramsToSend = [$docParams];

                $resultDoc = $client->__soapCall($docMethod, $paramsToSend);

                $rawResponseDoc = method_exists($client, '__getLastResponse') ? $client->__getLastResponse() : null;

                $docimgResult = [
                    'success'      => true,
                    'raw_response' => $rawResponseDoc,
                ];
                $docimgSent = true;

            } catch (SoapFault $sf) {
                $docimgResult = [
                    'success'       => false,
                    'error_message' => $sf->faultstring ?? $sf->getMessage(),
                    'error_code'    => $sf->faultcode ?? 'SOAP_FAULT',
                    'raw_response'  => method_exists($client, '__getLastResponse') ? $client->__getLastResponse() : null,
                ];
            } catch (Exception $e) {
                $docimgResult = [
                    'success'       => false,
                    'error_message' => $e->getMessage(),
                    'error_code'    => 'GENERAL_ERROR',
                    'raw_response'  => method_exists($client, '__getLastResponse') ? $client->__getLastResponse() : null,
                ];
            }
        }

        // 4) Resultado consolidado
        return [
            'success' => true,
            'response_data' => [
                'main' => [
                    'raw_response' => $rawResponseMain,
                    'parsed'       => $parsedMain,
                ],
                'docimg' => $docimgResult,
            ],
            'docimg_sent' => $docimgSent,
        ];
    }

    /**
     * Construye el payload para DocumentoIMG de forma flexible.
     * - Si viene $docimg['soap_params'], se devuelve exactamente eso.
     * - Si no, se arma un payload común con base64 + metadatos.
     *
     * @throws Exception si faltan campos esenciales.
     */
    protected function buildDocumentoImgParams(array $docimg, string $idUsuario, string $ticket, string $firma): array
    {
        // Si te pasan el shape exacto, respetalo sin tocarlo
        if (!empty($docimg['soap_params']) && is_array($docimg['soap_params'])) {
            return $docimg['soap_params'];
        }

        // Campos mínimos que sí controlamos
        $nroDocumento = $docimg['nroDocumento'] ?? null;
        $file         = $docimg['file'] ?? null;

        if (!$nroDocumento) {
            throw new Exception('DocumentoIMG: falta nroDocumento.');
        }
        if (!$file || empty($file['base64'])) {
            throw new Exception('DocumentoIMG: falta contenido base64 del archivo.');
        }

        // Metadata básica (defensiva)
        $filename = $file['filename'] ?? 'adjunto.pdf';
        $mimetype = $file['mimetype'] ?? 'application/pdf';

        // Payload genérico: muchos servicios aceptan el binario en base64 con claves documentales.
        // Si el WSDL requiere un shape distinto, podés pasar 'soap_params' desde el controlador.
        return [
            'nroDocumento'  => $nroDocumento,
            // variantes usuales — si el WSDL no acepta, usar 'soap_params' desde el caller
            'archivo'       => $file['base64'],     // contenido base64
            'nombreArchivo' => $filename,
            'tipo'          => $mimetype,
            'Autenticacion' => [
                'idUsuario' => (string)$idUsuario,
                'ticket'    => (string)$ticket,
                'firma'     => (string)$firma,
            ],
        ];
    }
}
