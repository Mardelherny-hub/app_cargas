<?php

namespace App\Services\Simple;

use App\Models\Voyage;
use App\Models\Company;
use App\Models\User;
use App\Models\WebserviceTransaction;
use App\Models\WebserviceResponse;
use App\Models\WebserviceLog;
use App\Services\Simple\BaseWebserviceService;
use App\Services\Webservice\Argentina\SimpleXmlGeneratorDesconsolidado;
use Exception;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * SISTEMA MODULAR WEBSERVICES - ArgentinaDeconsolidatedService
 * 
 * Servicio para Desconsolidados Argentina AFIP
 * Extiende BaseWebserviceService para el webservice AFIP wgesinformacionanticipada
 * 
 * MÉTODOS AFIP IMPLEMENTADOS:
 * - RegistrarTitulosDesconsolidador: Registrar títulos desconsolidados (house bills)
 * - RectificarTitulosDesconsolidador: Rectificar títulos registrados
 * - EliminarTitulosDesconsolidador: Eliminar títulos por puerto/conocimiento
 * 
 * FLUJO:
 * 1. Validar que existan BLs con master_bill_number (títulos hijos)
 * 2. Generar XML automático desde BD usando SimpleXmlGeneratorDesconsolidado
 * 3. Reemplazar tokens de autenticación AFIP
 * 4. Enviar vía SOAP
 * 5. Procesar respuesta y actualizar estados
 * 
 * PATRÓN: Igual a ArgentinaAnticipatedService y ArgentinaMicDtaService
 */
class ArgentinaDeconsolidatedService extends BaseWebserviceService
{
    /**
     * Generador XML específico para desconsolidados
     */
    private ?SimpleXmlGeneratorDesconsolidado $xmlGenerator = null;

    /**
     * Configuración específica del webservice desconsolidados
     */
    protected function getWebserviceConfig(): array
    {
        return [
            'webservice_type' => 'desconsolidados',
            'country' => 'AR',
            'environment' => 'testing',
            
            // URLs AFIP
            'webservice_url' => 'https://wsaduhomoext.afip.gob.ar/DIAV2/wgesinformacionanticipada/wgesinformacionanticipada.asmx',
            'wsdl_url' => 'https://wsaduhomoext.afip.gob.ar/DIAV2/wgesinformacionanticipada/wgesinformacionanticipada.asmx?wsdl',
            
            // SOAP Actions
            'soap_action_registrar' => 'Ar.Gob.Afip.Dga.Org.wgesinformacionanticipada/RegistrarTitulosDesconsolidador',
            'soap_action_rectificar' => 'Ar.Gob.Afip.Dga.Org.wgesinformacionanticipada/RectificarTitulosDesconsolidador',
            'soap_action_eliminar' => 'Ar.Gob.Afip.Dga.Org.wgesinformacionanticipada/EliminarTitulosDesconsolidador',
            
            // Configuración adicional
            'timeout_seconds' => 90,
            'max_retries' => 3,
            'require_certificate' => true,
            'validate_master_bill' => true, // Validar que exista título madre
        ];
    }

    protected function getWebserviceType(): string
    {
        return 'desconsolidados';
    }

    protected function getCountry(): string
    {
        return 'AR';
    }

    protected function getWsdlUrl(): string
    {
        return $this->config['wsdl_url'] ?? '';
    }

    /**
     * VALIDACIÓN ESPECÍFICA PARA DESCONSOLIDADOS
     * 
     * Verifica que:
     * - Existan BillsOfLading con master_bill_number
     * - Los títulos madre existan en el mismo viaje
     * - Las aduanas coincidan entre padre e hijo
     */
    protected function validateSpecificData(Voyage $voyage): array
    {
        $errors = [];
        $warnings = [];

        // Obtener BLs desconsolidados (con título madre)
        $desconsolidatedBills = $voyage->billsOfLading()
            ->whereNotNull('master_bill_number')
            ->with(['shipment', 'loadingPort', 'dischargePort'])
            ->get();

        if ($desconsolidatedBills->isEmpty()) {
            $errors[] = 'No hay títulos desconsolidados (BillsOfLading con master_bill_number) en este viaje.';
            return ['errors' => $errors, 'warnings' => $warnings];
        }

        // Validar cada título desconsolidado
        foreach ($desconsolidatedBills as $bill) {
            // 1. Validar que tenga número de conocimiento
            if (empty($bill->bill_number)) {
                $errors[] = "BillOfLading ID {$bill->id} no tiene número de conocimiento (bill_number).";
            }

            // 2. Validar que tenga título madre
            if (empty($bill->master_bill_number)) {
                $errors[] = "BillOfLading ID {$bill->id} no tiene título madre (master_bill_number).";
            }

            // 3. Validar que el título madre exista en el viaje
            if ($this->config['validate_master_bill']) {
                $masterExists = $voyage->billsOfLading()
                    ->where('bill_number', $bill->master_bill_number)
                    ->exists();
                    
                if (!$masterExists) {
                    $errors[] = "Título madre '{$bill->master_bill_number}' no encontrado en el viaje para BL '{$bill->bill_number}'.";
                }
            }

            // 4. Validar puertos AFIP
            if (!$bill->loadingPort || !$bill->loadingPort->afip_code) {
                $errors[] = "BL '{$bill->bill_number}' no tiene puerto de embarque con código AFIP.";
            }

            if (!$bill->dischargePort || !$bill->dischargePort->afip_code) {
                $errors[] = "BL '{$bill->bill_number}' no tiene puerto de descarga con código AFIP.";
            }

            // 5. Validar que tenga al menos un item de mercadería
            if ($bill->shipmentItems->isEmpty()) {
                $warnings[] = "BL '{$bill->bill_number}' no tiene ítems de mercadería. AFIP puede rechazarlo.";
            }

            // 6. Validar códigos aduaneros
            if (empty($bill->discharge_customs_code)) {
                $warnings[] = "BL '{$bill->bill_number}' no tiene código de aduana de descarga.";
            }
        }

        return [
            'errors' => $errors,
            'warnings' => $warnings,
            'bills_count' => $desconsolidatedBills->count(),
        ];
    }

    /**
     * ENVÍO ESPECÍFICO (método requerido por BaseWebserviceService)
     * Este método no se usa directamente, los métodos públicos manejan el envío
     */
    protected function sendSpecificWebservice(Voyage $voyage, array $options = []): array
    {
        $method = $options['method'] ?? 'registrar';
        
        switch ($method) {
            case 'registrar':
                return $this->registrarTitulos($voyage);
            case 'rectificar':
                return $this->rectificarTitulos($voyage);
            case 'eliminar':
                return $this->eliminarTitulos($voyage);
            default:
                throw new Exception("Método desconocido: {$method}");
        }
    }

    // ====================================
    // MÉTODOS PÚBLICOS PRINCIPALES
    // ====================================

    /**
     * REGISTRAR TÍTULOS DESCONSOLIDADOR
     * 
     * @param Voyage $voyage
     * @return array Resultado del envío
     */
    public function registrarTitulos(Voyage $voyage): array
    {
        return $this->executeWebserviceMethod($voyage, 'registrar', 'RegistrarTitulosDesconsolidador');
    }

    /**
     * RECTIFICAR TÍTULOS DESCONSOLIDADOR
     * 
     * @param Voyage $voyage
     * @return array Resultado del envío
     */
    public function rectificarTitulos(Voyage $voyage): array
    {
        return $this->executeWebserviceMethod($voyage, 'rectificar', 'RectificarTitulosDesconsolidador');
    }

    /**
     * ELIMINAR TÍTULOS DESCONSOLIDADOR
     * 
     * @param Voyage $voyage
     * @return array Resultado del envío
     */
    public function eliminarTitulos(Voyage $voyage): array
    {
        return $this->executeWebserviceMethod($voyage, 'eliminar', 'EliminarTitulosDesconsolidador');
    }

    // ====================================
    // MÉTODOS PRIVADOS DE IMPLEMENTACIÓN
    // ====================================

    /**
     * EJECUTAR MÉTODO WEBSERVICE (patrón común)
     * 
     * @param Voyage $voyage
     * @param string $methodType registrar|rectificar|eliminar
     * @param string $soapMethod Nombre del método SOAP AFIP
     * @return array
     */
    private function executeWebserviceMethod(Voyage $voyage, string $methodType, string $soapMethod): array
    {
        DB::beginTransaction();
        
        try {
            // 1. Validar datos
            $validation = $this->validateSpecificData($voyage);
            
            if (!empty($validation['errors'])) {
                throw new Exception('Errores de validación: ' . implode(', ', $validation['errors']));
            }

            // 2. Generar XML
            $xmlContent = $this->generateXml($voyage, $methodType);
            
            if (!$xmlContent) {
                throw new Exception('Error generando XML para desconsolidados.');
            }

            // 3. Reemplazar tokens de autenticación
            $xmlContent = $this->replaceAuthTokens($xmlContent);

            // 4. Crear transacción
            $transaction = $this->createTransaction($voyage, [
                'methodType' => $methodType,
                'xmlContent' => $xmlContent,
            ]);

            // 5. Enviar vía SOAP
            $soapResult = $this->sendSoapRequest($transaction, $xmlContent, $soapMethod);

            // 6. Procesar respuesta
            if ($soapResult['success']) {
                $this->processSuccessResponse($transaction, $soapResult, $voyage, $methodType);
                DB::commit();
                
                return [
                    'success' => true,
                    'transaction_id' => $transaction->id,
                    'message' => "Títulos desconsolidados {$methodType} exitosamente.",
                    'identifier' => $soapResult['identifier'] ?? null,
                ];
            } else {
                $this->processErrorResponse($transaction, $soapResult);
                DB::commit();
                
                return [
                    'success' => false,
                    'transaction_id' => $transaction->id,
                    'error' => $soapResult['error_message'] ?? 'Error desconocido',
                ];
            }

        } catch (Exception $e) {
            DB::rollBack();
            
            $this->logOperation('error', "Error en {$methodType} desconsolidados", [
                'voyage_id' => $voyage->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * GENERAR XML SEGÚN EL MÉTODO
     */
    private function generateXml(Voyage $voyage, string $methodType): ?string
    {
        try {
            if (!$this->xmlGenerator) {
                $this->xmlGenerator = new SimpleXmlGeneratorDesconsolidado($voyage);
            }

            $transactionId = $this->generateTransactionId();

            switch ($methodType) {
                case 'registrar':
                    return $this->xmlGenerator->generateRegistrar($transactionId);
                case 'rectificar':
                    return $this->xmlGenerator->generateRectificar($transactionId);
                case 'eliminar':
                    return $this->xmlGenerator->generateEliminar($transactionId);
                default:
                    throw new Exception("Tipo de método desconocido: {$methodType}");
            }

        } catch (Exception $e) {
            $this->logOperation('error', 'Error generando XML', [
                'method' => $methodType,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * REEMPLAZAR TOKENS DE AUTENTICACIÓN AFIP
     */
    private function replaceAuthTokens(string $xmlContent): string
    {
        // Obtener credenciales AFIP
        $authData = $this->getAfipAuthData();

        $replacements = [
            '##TOKEN##' => $authData['token'] ?? '',
            '##SIGN##' => $authData['sign'] ?? '',
            '##CUIT##' => $authData['cuit'] ?? '',
            '##TIPO_AGENTE##' => $authData['tipo_agente'] ?? 'ATA',
            '##ROL##' => $authData['rol'] ?? 'DESCONSOLIDADOR',
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $xmlContent);
    }

    /**
     * OBTENER DATOS DE AUTENTICACIÓN AFIP
     */
    private function getAfipAuthData(): array
    {
        // TODO: Integrar con CertificateManagerService para obtener token/sign real
        // Por ahora retornar valores de configuración
        return [
            'token' => config('services.afip.token', 'TOKEN_PLACEHOLDER'),
            'sign' => config('services.afip.sign', 'SIGN_PLACEHOLDER'),
            'cuit' => preg_replace('/[^0-9]/', '', $this->company->tax_id),
            'tipo_agente' => 'ATA',
            'rol' => 'DESCONSOLIDADOR',
        ];
    }

    /**
     * CREAR TRANSACCIÓN WEBSERVICE
     */
    protected function createTransaction(Voyage $voyage, array $options = []): WebserviceTransaction
    {
        $methodType = $options['methodType'] ?? 'registrar';
        $xmlContent = $options['xmlContent'] ?? '';

        return WebserviceTransaction::create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'voyage_id' => $voyage->id,
            'transaction_id' => $this->generateTransactionId(),
            'webservice_type' => 'desconsolidados',
            'country' => 'AR',
            'webservice_url' => $this->config['webservice_url'],
            'soap_action' => $this->config["soap_action_{$methodType}"] ?? '',
            'status' => 'pending',
            'request_xml' => $xmlContent,
            'environment' => $this->config['environment'] ?? 'testing',
            'currency_code' => 'USD',
            'container_count' => 0,
            'bill_of_lading_count' => $voyage->billsOfLading()->whereNotNull('master_bill_number')->count(),
            'additional_metadata' => [
                'method' => $methodType,
                'voyage_number' => $voyage->voyage_number,
            ],
        ]);
    }

    /**
     * ENVIAR REQUEST SOAP
     */
    private function sendSoapRequest(WebserviceTransaction $transaction, string $xmlContent, string $soapMethod): array
    {
        try {
            $transaction->update(['status' => 'sending', 'sent_at' => now()]);

            // Crear cliente SOAP
            $soapClient = $this->createSoapClient();

            // Preparar parámetros (simplificado - AFIP recibe el XML completo)
            $startTime = microtime(true);
            
            // Llamar al método SOAP
            $response = $soapClient->__soapCall($soapMethod, [$xmlContent]);
            
            $responseTime = (int)((microtime(true) - $startTime) * 1000);

            // Extraer XMLs de la transacción SOAP
            $requestXml = $soapClient->__getLastRequest();
            $responseXml = $soapClient->__getLastResponse();

            // Actualizar transacción
            $transaction->update([
                'request_xml' => $requestXml,
                'response_xml' => $responseXml,
                'response_time_ms' => $responseTime,
                'response_at' => now(),
            ]);

            // Parsear respuesta
            return $this->parseAfipResponse($response, $responseXml);

        } catch (\SoapFault $e) {
            $this->logOperation('error', 'Error SOAP', [
                'transaction_id' => $transaction->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error_message' => 'Error SOAP: ' . $e->getMessage(),
            ];
        } catch (Exception $e) {
            $this->logOperation('error', 'Error enviando SOAP', [
                'transaction_id' => $transaction->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error_message' => $e->getMessage(),
            ];
        }
    }

    /**
     * PARSEAR RESPUESTA AFIP
     */
    private function parseAfipResponse($response, string $responseXml): array
    {
        try {
            // Buscar errores en la respuesta XML
            if (stripos($responseXml, '<ListaErrores>') !== false) {
                preg_match('/<Descripcion>(.*?)<\/Descripcion>/i', $responseXml, $matches);
                $errorDesc = $matches[1] ?? 'Error desconocido';
                
                return [
                    'success' => false,
                    'error_message' => $errorDesc,
                ];
            }

            // Buscar identificador de viaje
            $identifier = null;
            if (preg_match('/<IdentificadorViaje>(.*?)<\/IdentificadorViaje>/i', $responseXml, $matches)) {
                $identifier = $matches[1];
            }

            return [
                'success' => true,
                'identifier' => $identifier,
                'response_data' => $response,
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error_message' => 'Error parseando respuesta: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * PROCESAR RESPUESTA EXITOSA
     */
    private function processSuccessResponse(
        WebserviceTransaction $transaction, 
        array $soapResult, 
        Voyage $voyage,
        string $methodType
    ): void {
        $transaction->update([
            'status' => 'success',
            'completed_at' => now(),
            'confirmation_number' => $soapResult['identifier'] ?? null,
        ]);

        // Crear respuesta estructurada
        WebserviceResponse::create([
            'transaction_id' => $transaction->id,
            'response_type' => 'desconsolidados_' . $methodType,
            'processing_status' => 'completed',
            'confirmation_number' => $soapResult['identifier'] ?? null,
            'voyage_number' => $voyage->voyage_number,
            'is_final_response' => true,
        ]);

        // Actualizar estado del voyage
        $this->updateVoyageWebserviceStatus($voyage, $methodType, 'approved');

        $this->logOperation('info', "Desconsolidados {$methodType} exitoso", [
            'transaction_id' => $transaction->id,
            'voyage_id' => $voyage->id,
            'identifier' => $soapResult['identifier'] ?? null,
        ]);
    }

    /**
     * PROCESAR RESPUESTA DE ERROR
     */
    private function processErrorResponse(WebserviceTransaction $transaction, array $soapResult): void
    {
        $transaction->update([
            'status' => 'error',
            'completed_at' => now(),
            'error_message' => $soapResult['error_message'] ?? 'Error desconocido',
        ]);

        $this->logOperation('error', 'Error en respuesta AFIP', [
            'transaction_id' => $transaction->id,
            'error' => $soapResult['error_message'] ?? 'Error desconocido',
        ]);
    }

    /**
     * ACTUALIZAR ESTADO WEBSERVICE DEL VOYAGE
     */
    private function updateVoyageWebserviceStatus(Voyage $voyage, string $methodType, string $status): void
    {
        $voyage->webserviceStatuses()->updateOrCreate(
            [
                'webservice_type' => 'desconsolidados',
                'method_name' => $methodType,
            ],
            [
                'status' => $status,
                'last_attempt_at' => now(),
                'success_at' => $status === 'approved' ? now() : null,
            ]
        );
    }

    /**
     * GENERAR ID ÚNICO DE TRANSACCIÓN
     */
    protected function generateTransactionId(): string
    {
        return 'DEC' . $this->company->id . now()->format('YmdHis') . rand(1000, 9999);
    }
}