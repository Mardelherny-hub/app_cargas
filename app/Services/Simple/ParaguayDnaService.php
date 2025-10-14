<?php

namespace App\Services\Simple;

use App\Models\Company;
use App\Models\User;
use App\Models\Voyage;
use App\Models\WebserviceTransaction;
use App\Services\Simple\BaseWebserviceService;
use App\Services\Simple\SimpleXmlGeneratorParaguay;
use Exception;
use SoapClient;
use SoapFault;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

/**
 * SISTEMA MODULAR WEBSERVICES - ParaguayDnaService
 * 
 * Servicio para Manifiestos Fluviales DNA Paraguay (GDSF)
 * Extiende BaseWebserviceService siguiendo el patrón exitoso de Argentina
 * 
 * MÉTODOS GDSF SOPORTADOS:
 * 1. XFFM - Carátula/Manifiesto (OBLIGATORIO PRIMERO)
 * 2. XFBL - Conocimientos/BLs (requiere XFFM)
 * 3. XFBT - Hoja de Ruta/Contenedores (requiere XFFM)
 * 4. XISP - Incluir Embarcación (opcional)
 * 5. XRSP - Desvincular Embarcación (opcional)
 * 6. XFCT - Cerrar Viaje (último paso)
 * 
 * FLUJO OBLIGATORIO:
 * XFFM → retorna nroViaje → XFBL/XFBT (usan nroViaje) → XFCT
 * 
 * INTEGRACIÓN:
 * - Genera XML automático desde BD vía SimpleXmlGeneratorParaguay
 * - Valida dependencias (no permite XFBL sin XFFM)
 * - Persiste en WebserviceTransaction/Response/Log automáticamente
 * - Retorna estados estructurados para UI
 */
class ParaguayDnaService extends BaseWebserviceService
{
    private SimpleXmlGeneratorParaguay $paraguayXmlGenerator;

    public function __construct(Company $company, User $user, array $config = [])
    {
        parent::__construct($company, $user, $config);
        
        // Inicializar generador XML específico de Paraguay
        $this->paraguayXmlGenerator = new SimpleXmlGeneratorParaguay($company, $this->config);
    }

    // ====================================
    // MÉTODOS ABSTRACTOS OBLIGATORIOS
    // ====================================

    /**
     * Tipo de webservice
     */
    protected function getWebserviceType(): string
    {
        return 'manifiesto';
    }

    /**
     * País del webservice
     */
    protected function getCountry(): string
    {
        return 'PY';
    }

    /**
     * URL del WSDL
     */
    protected function getWsdlUrl(): string
    {
        $environment = $this->company->ws_environment ?? 'testing';
        
        $urls = [
            'testing' => 'https://securetest.aduana.gov.py/wsdl/gdsf/serviciogdsf?wsdl',
            'production' => 'https://secure.aduana.gov.py/wsdl/gdsf/serviciogdsf?wsdl',
        ];
        
        return $urls[$environment] ?? $urls['testing'];
    }

    /**
     * Configuración específica de Paraguay
     */
    protected function getWebserviceConfig(): array
    {
        return array_merge(parent::BASE_CONFIG, [
            'environment' => config('services.paraguay.environment', 'testing'),
            'webservice_url' => config('services.paraguay.wsdl'),
            'soap_method' => 'EnviarMensajeFluvial',
            'require_certificate' => config('services.paraguay.require_certificate', true),
            'auth' => [
                'idUsuario' => config('services.paraguay.auth.idUsuario'),
                'ticket' => config('services.paraguay.auth.ticket'),
                'firma' => config('services.paraguay.auth.firma'),
            ],
        ]);
    }

    /**
 * Validaciones específicas de Paraguay - VERSIÓN CON BYPASS
 */
    protected function validateSpecificData(Voyage $voyage): array
{
    $errors = [];
    $warnings = [];

    // Validar datos básicos del viaje
    if (!$voyage->voyage_number) {
        $errors[] = 'Viaje sin número de viaje';
    }

    if (!$voyage->leadVessel) {
        $errors[] = 'Viaje sin embarcación principal asignada';
    }

    if (!$voyage->originPort || !$voyage->destinationPort) {
        $errors[] = 'Viaje sin puertos de origen/destino';
    }

    // ========================================
    // VALIDACIÓN DE CERTIFICADO CON BYPASS
    // ========================================
    
    $shouldBypass = $this->company->shouldBypassTesting('paraguay');
    $hasCertificate = $this->company->hasCertificateForCountry('paraguay');
    
    if ($this->config['require_certificate']) {
        if (!$hasCertificate) {
            if ($shouldBypass) {
                // Con bypass, certificado faltante es solo advertencia
                $warnings[] = 'Certificado Paraguay no configurado (usando modo bypass)';
            } else {
                // Sin bypass, certificado es obligatorio
                $errors[] = 'Certificado digital Paraguay requerido';
            }
        } else {
            // Verificar que el certificado sea válido (existe el archivo)
            $certificate = $this->company->getCertificate('paraguay');
            $certPath = $certificate['path'] ?? null;
            
            if ($certPath && !\Illuminate\Support\Facades\Storage::exists($certPath)) {
                if ($shouldBypass) {
                    $warnings[] = 'Archivo de certificado no encontrado (usando modo bypass)';
                } else {
                    $errors[] = 'Archivo de certificado no encontrado';
                }
            }
        }
        
        // Validar RUC
        if (!$this->company->tax_id) {
            $errors[] = 'Empresa sin RUC/Tax ID configurado';
        }
    }

    // ========================================
    // VALIDACIÓN DE CREDENCIALES DNA CON BYPASS
    // ========================================
    
    $auth = $this->config['auth'];
    $hasCredentials = !empty($auth['idUsuario']) && !empty($auth['ticket']) && !empty($auth['firma']);
    
    if (!$hasCredentials) {
        if ($shouldBypass) {
            // Con bypass, credenciales faltantes son solo advertencia
            $warnings[] = 'Credenciales DNA no configuradas (usando modo bypass)';
            $warnings[] = 'Configure las credenciales DNA en: Configuración → Webservices → Paraguay';
        } else {
            // Sin bypass, credenciales son obligatorias
            $errors[] = 'Credenciales DNA Paraguay incompletas';
            $warnings[] = 'Configure las credenciales DNA en: Configuración → Webservices → Paraguay';
        }
    }

    return [
        'errors' => $errors,
        'warnings' => $warnings,
    ];
}

/**
 * Override del método canProcessVoyage para manejar bypass
 * Sobreescribe la validación de certificado de BaseWebserviceService
 */
public function canProcessVoyage(Voyage $voyage): array
{
    $validation = [
        'can_process' => false,
        'errors' => [],
        'warnings' => [],
    ];

    try {
        // 1. Validaciones básicas comunes (del padre)
        $baseValidation = $this->validateBaseData($voyage);
        $validation['errors'] = array_merge($validation['errors'], $baseValidation['errors']);
        $validation['warnings'] = array_merge($validation['warnings'], $baseValidation['warnings']);

        // 2. Validaciones específicas de Paraguay (con bypass integrado)
        $specificValidation = $this->validateSpecificData($voyage);
        $validation['errors'] = array_merge($validation['errors'], $specificValidation['errors']);
        $validation['warnings'] = array_merge($validation['warnings'], $specificValidation['warnings']);

        // 3. NO validar certificado con CertificateManagerService
        //    Ya lo manejamos en validateSpecificData() con soporte de bypass

        // 4. Determinar si puede procesar
        $validation['can_process'] = empty($validation['errors']);

        $this->logOperation(
            $validation['can_process'] ? 'info' : 'warning',
            'Validación de Viaje Paraguay completada',
            [
                'can_process' => $validation['can_process'],
                'errors_count' => count($validation['errors']),
                'warnings_count' => count($validation['warnings']),
                'bypass_enabled' => $this->company->shouldBypassTesting('paraguay'),
            ]
        );

        return $validation;

    } catch (Exception $e) {
        $validation['errors'][] = 'Error interno en validación: ' . $e->getMessage();
        $validation['can_process'] = false;
        
        $this->logOperation('error', 'Error en canProcessVoyage', [
            'error' => $e->getMessage(),
        ]);
        
        return $validation;
    }
}

    /**
     * Envío específico del webservice (no implementado aquí directamente)
     * Cada método GDSF tiene su propia implementación
     */
    protected function sendSpecificWebservice(Voyage $voyage, array $options = []): array
    {
        throw new Exception('Use métodos específicos: sendXffm(), sendXfbl(), sendXfbt(), etc.');
    }

    // ====================================
    // MÉTODOS PÚBLICOS ESPECÍFICOS GDSF
    // ====================================

    /**
     * 1. XFFM - Carátula/Manifiesto Fluvial
     * PRIMER envío obligatorio - Registra el viaje en DNA Paraguay
     * Retorna nroViaje necesario para envíos posteriores
     * 
     * @param Voyage $voyage
     * @param array $options
     * @return array ['success' => bool, 'nroViaje' => string|null, ...]
     */
    public function sendXffm(Voyage $voyage, array $options = []): array
    {
        $this->logOperation('info', 'Iniciando envío XFFM (Carátula)', [
            'voyage_id' => $voyage->id,
            'voyage_number' => $voyage->voyage_number,
        ]);

        DB::beginTransaction();

        try {
            // 1. Validar viaje
            $validation = $this->canProcessVoyage($voyage);
            if (!$validation['can_process']) {
                throw new Exception('Viaje no válido: ' . implode(', ', $validation['errors']));
            }

            // 2. Verificar si ya fue enviado
            $existingXffm = $this->getExistingTransaction($voyage, 'XFFM');
            if ($existingXffm && !($options['force_resend'] ?? false)) {
                return [
                    'success' => false,
                    'error_message' => 'XFFM ya fue enviado. Use force_resend=true para reenviar.',
                    'existing_nroViaje' => $existingXffm->external_reference,
                ];
            }

            // 3. Generar XML automáticamente
            $transactionId = $this->generateTransactionId('XFFM');
            $xml = $this->paraguayXmlGenerator->createXffmXml($voyage, $transactionId);

            // 4. Crear transacción
            $transaction = $this->createTransaction($voyage, [
                'tipo_mensaje' => 'XFFM',
                'transaction_id' => $transactionId,
            ]);
            $this->currentTransactionId = $transaction->id;

            // 5. Enviar SOAP
            $soapResult = $this->sendSoapMessage([
                'codigo' => 'XFFM',
                'version' => '1.0',
                'viaje' => null, // NULL en primer envío XFFM
                'xml' => $xml,
            ]);

            // 6. Procesar respuesta
            if ($soapResult['success']) {
                // Extraer nroViaje de la respuesta
                $nroViaje = $this->extractNroViajeFromResponse($soapResult['response_data']);

                $transaction->update([
                    'status' => 'sent',
                    'external_reference' => $nroViaje,
                    'response_xml' => $soapResult['raw_response'] ?? null,
                ]);

                // Actualizar estado del voyage
                $this->updateWebserviceStatus($voyage, 'XFFM', [
                    'status' => 'sent',
                    'nro_viaje' => $nroViaje,
                ]);

                DB::commit();

                $this->logOperation('info', 'XFFM enviado exitosamente', [
                    'voyage_id' => $voyage->id,
                    'nroViaje' => $nroViaje,
                    'transaction_id' => $transactionId,
                ]);

                return [
                    'success' => true,
                    'nroViaje' => $nroViaje,
                    'transaction_id' => $transactionId,
                    'message' => 'XFFM enviado exitosamente',
                ];
            } else {
                throw new Exception($soapResult['error_message'] ?? 'Error SOAP desconocido');
            }

        } catch (Exception $e) {
            DB::rollBack();

            $this->logOperation('error', 'Error enviando XFFM', [
                'voyage_id' => $voyage->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error_message' => $e->getMessage(),
            ];
        }
    }

    /**
     * 2. XFBL - Conocimientos/BLs
     * Requiere XFFM enviado previamente
     * 
     * @param Voyage $voyage
     * @param array $options
     * @return array
     */
    public function sendXfbl(Voyage $voyage, array $options = []): array
    {
        $this->logOperation('info', 'Iniciando envío XFBL (Conocimientos)', [
            'voyage_id' => $voyage->id,
        ]);

        DB::beginTransaction();

        try {
            // 1. Verificar que XFFM fue enviado
            $xffmTransaction = $this->getExistingTransaction($voyage, 'XFFM');
            if (!$xffmTransaction || $xffmTransaction->status !== 'sent') {
                throw new Exception('Debe enviar XFFM primero');
            }

            $nroViaje = $xffmTransaction->external_reference;
            if (!$nroViaje) {
                throw new Exception('No se encontró nroViaje de XFFM');
            }

            // 2. Validar que hay BLs
            $blCount = $voyage->shipments->flatMap->billsOfLading->count();
            if ($blCount === 0) {
                throw new Exception('No hay Bills of Lading para enviar');
            }

            // 3. Generar XML
            $transactionId = $this->generateTransactionId('XFBL');
            $xml = $this->paraguayXmlGenerator->createXfblXml($voyage, $transactionId, $nroViaje);

            // 4. Crear transacción
            $transaction = $this->createTransaction($voyage, [
                'tipo_mensaje' => 'XFBL',
                'transaction_id' => $transactionId,
            ]);

            // 5. Enviar SOAP
            $soapResult = $this->sendSoapMessage([
                'codigo' => 'XFBL',
                'version' => '1.0',
                'viaje' => $nroViaje,
                'xml' => $xml,
            ]);

            // 6. Procesar respuesta
            if ($soapResult['success']) {
                $transaction->update([
                    'status' => 'sent',
                    'response_xml' => $soapResult['raw_response'] ?? null,
                ]);

                $this->updateWebserviceStatus($voyage, 'XFBL', [
                    'status' => 'sent',
                    'nro_viaje' => $nroViaje,
                ]);

                DB::commit();

                return [
                    'success' => true,
                    'transaction_id' => $transactionId,
                    'message' => 'XFBL enviado exitosamente',
                    'bl_count' => $blCount,
                ];
            } else {
                throw new Exception($soapResult['error_message'] ?? 'Error SOAP');
            }

        } catch (Exception $e) {
            DB::rollBack();

            $this->logOperation('error', 'Error enviando XFBL', [
                'voyage_id' => $voyage->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error_message' => $e->getMessage(),
            ];
        }
    }

    /**
     * 3. XFBT - Hoja de Ruta (Contenedores)
     * Requiere XFFM enviado previamente
     * 
     * @param Voyage $voyage
     * @param array $options
     * @return array
     */
    public function sendXfbt(Voyage $voyage, array $options = []): array
    {
        $this->logOperation('info', 'Iniciando envío XFBT (Contenedores)', [
            'voyage_id' => $voyage->id,
        ]);

        DB::beginTransaction();

        try {
            // 1. Verificar XFFM
            $xffmTransaction = $this->getExistingTransaction($voyage, 'XFFM');
            if (!$xffmTransaction || $xffmTransaction->status !== 'sent') {
                throw new Exception('Debe enviar XFFM primero');
            }

            $nroViaje = $xffmTransaction->external_reference;

            // 2. Validar contenedores (a través de shipmentItems)
            $containers = $voyage->shipments
                ->flatMap->billsOfLading
                ->flatMap->shipmentItems
                ->flatMap->containers
                ->unique('id');

            if ($containers->isEmpty()) {
                throw new Exception('No hay contenedores para enviar');
            }

            // 3. Generar XML
            $transactionId = $this->generateTransactionId('XFBT');
            $xml = $this->paraguayXmlGenerator->createXfbtXml($voyage, $transactionId, $nroViaje);

            // 4. Crear transacción
            $transaction = $this->createTransaction($voyage, [
                'tipo_mensaje' => 'XFBT',
                'transaction_id' => $transactionId,
            ]);

            // 5. Enviar SOAP
            $soapResult = $this->sendSoapMessage([
                'codigo' => 'XFBT',
                'version' => '1.0',
                'viaje' => $nroViaje,
                'xml' => $xml,
            ]);

            // 6. Procesar respuesta
            if ($soapResult['success']) {
                $transaction->update([
                    'status' => 'sent',
                    'response_xml' => $soapResult['raw_response'] ?? null,
                ]);

                $this->updateWebserviceStatus($voyage, 'XFBT', [
                    'status' => 'sent',
                    'nro_viaje' => $nroViaje,
                ]);

                DB::commit();

                return [
                    'success' => true,
                    'transaction_id' => $transactionId,
                    'message' => 'XFBT enviado exitosamente',
                    'container_count' => $containers->count(),
                ];
            } else {
                throw new Exception($soapResult['error_message'] ?? 'Error SOAP');
            }

        } catch (Exception $e) {
            DB::rollBack();

            $this->logOperation('error', 'Error enviando XFBT', [
                'voyage_id' => $voyage->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error_message' => $e->getMessage(),
            ];
        }
    }

    /**
     * 4. XFCT - Cerrar Viaje
     * Último paso - Cierra el nroViaje cuando todo está completo
     * 
     * @param Voyage $voyage
     * @param array $options
     * @return array
     */
    public function sendXfct(Voyage $voyage, array $options = []): array
    {
        $this->logOperation('info', 'Iniciando envío XFCT (Cerrar Viaje)', [
            'voyage_id' => $voyage->id,
        ]);

        DB::beginTransaction();

        try {
            // 1. Verificar XFFM
            $xffmTransaction = $this->getExistingTransaction($voyage, 'XFFM');
            if (!$xffmTransaction || $xffmTransaction->status !== 'sent') {
                throw new Exception('Debe enviar XFFM primero');
            }

            $nroViaje = $xffmTransaction->external_reference;

            // 2. Generar XML
            $transactionId = $this->generateTransactionId('XFCT');
            $xml = $this->paraguayXmlGenerator->createXfctXml($nroViaje, $transactionId);

            // 3. Crear transacción
            $transaction = $this->createTransaction($voyage, [
                'tipo_mensaje' => 'XFCT',
                'transaction_id' => $transactionId,
            ]);

            // 4. Enviar SOAP
            $soapResult = $this->sendSoapMessage([
                'codigo' => 'XFCT',
                'version' => '1.0',
                'viaje' => $nroViaje,
                'xml' => $xml,
            ]);

            // 5. Procesar respuesta
            if ($soapResult['success']) {
                $transaction->update([
                    'status' => 'sent',
                    'response_xml' => $soapResult['raw_response'] ?? null,
                ]);

                $this->updateWebserviceStatus($voyage, 'XFCT', [
                    'status' => 'sent',
                    'nro_viaje' => $nroViaje,
                ]);

                DB::commit();

                return [
                    'success' => true,
                    'transaction_id' => $transactionId,
                    'message' => 'Viaje cerrado exitosamente',
                    'nroViaje' => $nroViaje,
                ];
            } else {
                throw new Exception($soapResult['error_message'] ?? 'Error SOAP');
            }

        } catch (Exception $e) {
            DB::rollBack();

            $this->logOperation('error', 'Error enviando XFCT', [
                'voyage_id' => $voyage->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error_message' => $e->getMessage(),
            ];
        }
    }

    // ====================================
    // MÉTODOS HELPERS PRIVADOS
    // ====================================

    /**
 * Enviar mensaje SOAP a DNA Paraguay CON BYPASS INTELIGENTE
 */
protected function sendSoapMessage(array $params): array
{
    // ========================================
    // BYPASS INTELIGENTE PARAGUAY
    // ========================================
    
    $shouldBypass = $this->company->shouldBypassTesting('paraguay');
    $environment = $this->config['environment'] ?? 'testing';
    $auth = $this->config['auth'];
    
    // Verificar si debe usar bypass
    $useBypass = false;
    
    // Razón 1: Bypass activado explícitamente en configuración
    if ($shouldBypass) {
        $useBypass = true;
        $bypassReason = 'Bypass activado en configuración de empresa';
    }
    
    // Razón 2: Ambiente testing sin credenciales DNA completas
    if ($environment === 'testing' && (empty($auth['idUsuario']) || empty($auth['ticket']) || empty($auth['firma']))) {
        $useBypass = true;
        $bypassReason = 'Ambiente testing sin credenciales DNA';
    }
    
    // Razón 3: Certificado es de testing (verificar si existe y es fake)
    $certificate = $this->company->getCertificate('paraguay');
    if ($certificate && isset($certificate['alias']) && str_contains(strtoupper($certificate['alias']), 'TEST')) {
        $useBypass = true;
        $bypassReason = 'Certificado de testing detectado';
    }
    
    // ========================================
    // SI DEBE USAR BYPASS: GENERAR RESPUESTA SIMULADA
    // ========================================
    
    if ($useBypass) {
        $this->logOperation('info', '🔄 BYPASS ACTIVADO - Simulando respuesta Paraguay', [
            'codigo' => $params['codigo'],
            'viaje' => $params['viaje'],
            'reason' => $bypassReason,
            'environment' => $environment,
        ]);
        
        return $this->generateBypassResponse($params);
    }
    
    // ========================================
    // CONEXIÓN REAL A DNA PARAGUAY
    // ========================================
    
    try {
        $this->logOperation('info', '🌐 Conectando a DNA Paraguay REAL', [
            'codigo' => $params['codigo'],
            'viaje' => $params['viaje'],
            'environment' => $environment,
        ]);
        
        $client = $this->createSoapClient();
        
        // Parámetros GDSF
        $soapParams = [
            'codigo' => $params['codigo'],
            'version' => $params['version'],
            'viaje' => $params['viaje'],
            'xml' => $params['xml'],
            'Autenticacion' => [
                'idUsuario' => $auth['idUsuario'],
                'ticket' => $auth['ticket'],
                'firma' => $auth['firma'],
            ],
        ];

        // Enviar
        $result = $client->__soapCall('EnviarMensajeFluvial', [$soapParams]);
        $rawResponse = $client->__getLastResponse();

        return [
            'success' => true,
            'response_data' => $result,
            'raw_response' => $rawResponse,
        ];

    } catch (SoapFault $e) {
        return [
            'success' => false,
            'error_message' => $e->faultstring ?? $e->getMessage(),
            'error_code' => $e->faultcode ?? 'SOAP_ERROR',
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'error_message' => $e->getMessage(),
            'error_code' => 'UNKNOWN_ERROR',
        ];
    }
}

/**
 * Generar respuesta simulada (BYPASS)
 */
private function generateBypassResponse(array $params): array
{
    $codigo = $params['codigo'];
    $viaje = $params['viaje'];
    
    // Generar nroViaje simulado si es XFFM
    $nroViaje = $viaje ?? 'PY' . date('Y') . str_pad(rand(1, 99999), 5, '0', STR_PAD_LEFT);
    
    $this->logOperation('info', '✅ BYPASS: Respuesta simulada generada', [
        'codigo' => $codigo,
        'nroViaje' => $nroViaje,
        'timestamp' => now()->toISOString(),
    ]);
    
    // Respuesta simulada según el tipo de mensaje
    switch ($codigo) {
        case 'XFFM':
            $responseXml = $this->generateXffmBypassXml($nroViaje);
            break;
            
        case 'XFBL':
            $responseXml = $this->generateXfblBypassXml($viaje);
            break;
            
        case 'XFBT':
            $responseXml = $this->generateXfbtBypassXml($viaje);
            break;
            
        case 'XFCT':
            $responseXml = $this->generateXfctBypassXml($viaje);
            break;
            
        default:
            $responseXml = '<response><status>OK</status><message>BYPASS SUCCESS</message></response>';
    }
    
    return [
        'success' => true,
        'response_data' => (object)[
            'nroViaje' => $nroViaje,
            'estado' => 'ACEPTADO',
            'mensaje' => 'Mensaje recibido correctamente (SIMULADO)',
            'bypass_mode' => true,
        ],
        'raw_response' => $responseXml,
    ];
}

/**
 * Generar XML de respuesta XFFM simulada
 */
private function generateXffmBypassXml(string $nroViaje): string
{
    $timestamp = now()->format('Y-m-d\TH:i:s');
    
    return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
    <soap:Body>
        <EnviarMensajeFluvialResponse xmlns="http://gdsf.aduana.gov.py/">
            <resultado>
                <codigo>0</codigo>
                <mensaje>Mensaje XFFM recibido correctamente (BYPASS)</mensaje>
                <nroViaje>{$nroViaje}</nroViaje>
                <estado>ACEPTADO</estado>
                <fechaProceso>{$timestamp}</fechaProceso>
            </resultado>
        </EnviarMensajeFluvialResponse>
    </soap:Body>
</soap:Envelope>
XML;
}

/**
 * Generar XML de respuesta XFBL simulada
 */
private function generateXfblBypassXml(string $viaje): string
{
    $timestamp = now()->format('Y-m-d\TH:i:s');
    
    return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
    <soap:Body>
        <EnviarMensajeFluvialResponse xmlns="http://gdsf.aduana.gov.py/">
            <resultado>
                <codigo>0</codigo>
                <mensaje>Conocimientos recibidos correctamente (BYPASS)</mensaje>
                <nroViaje>{$viaje}</nroViaje>
                <estado>ACEPTADO</estado>
                <fechaProceso>{$timestamp}</fechaProceso>
            </resultado>
        </EnviarMensajeFluvialResponse>
    </soap:Body>
</soap:Envelope>
XML;
}

/**
 * Generar XML de respuesta XFBT simulada
 */
private function generateXfbtBypassXml(string $viaje): string
{
    $timestamp = now()->format('Y-m-d\TH:i:s');
    
    return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
    <soap:Body>
        <EnviarMensajeFluvialResponse xmlns="http://gdsf.aduana.gov.py/">
            <resultado>
                <codigo>0</codigo>
                <mensaje>Contenedores recibidos correctamente (BYPASS)</mensaje>
                <nroViaje>{$viaje}</nroViaje>
                <estado>ACEPTADO</estado>
                <fechaProceso>{$timestamp}</fechaProceso>
            </resultado>
        </EnviarMensajeFluvialResponse>
    </soap:Body>
</soap:Envelope>
XML;
}

/**
 * Generar XML de respuesta XFCT simulada
 */
private function generateXfctBypassXml(string $viaje): string
{
    $timestamp = now()->format('Y-m-d\TH:i:s');
    
    return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
    <soap:Body>
        <EnviarMensajeFluvialResponse xmlns="http://gdsf.aduana.gov.py/">
            <resultado>
                <codigo>0</codigo>
                <mensaje>Viaje cerrado exitosamente (BYPASS)</mensaje>
                <nroViaje>{$viaje}</nroViaje>
                <estado>CERRADO</estado>
                <fechaProceso>{$timestamp}</fechaProceso>
            </resultado>
        </EnviarMensajeFluvialResponse>
    </soap:Body>
</soap:Envelope>
XML;
}

    /**
     * Extraer nroViaje de la respuesta GDSF
     */
    protected function extractNroViajeFromResponse($responseData): ?string
    {
        try {
            // DNA Paraguay retorna XML con nroViaje
            if (is_object($responseData) && isset($responseData->xml)) {
                $xml = simplexml_load_string($responseData->xml);
                if ($xml && isset($xml->nroViaje)) {
                    return (string)$xml->nroViaje;
                }
            }
            return null;
        } catch (Exception $e) {
            Log::warning('Error extrayendo nroViaje', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Obtener transacción existente por tipo de mensaje
     */
    /**
 * Obtener transacción existente por tipo de mensaje
 */
protected function getExistingTransaction(Voyage $voyage, string $tipoMensaje): ?WebserviceTransaction
{
    return WebserviceTransaction::where('voyage_id', $voyage->id)
        ->where('webservice_type', 'manifiesto')
        ->where('country', 'PY')
        ->whereJsonContains('additional_metadata->tipo_mensaje', $tipoMensaje)
        ->orderBy('created_at', 'desc')
        ->first();
}

/**
 * Crear transacción con datos específicos de Paraguay
 */
protected function createTransaction(Voyage $voyage, array $additionalData = []): WebserviceTransaction
{
    return WebserviceTransaction::create([
        'company_id' => $this->company->id,
        'user_id' => $this->user->id,
        'voyage_id' => $voyage->id,
        'transaction_id' => $additionalData['transaction_id'] ?? $this->generateTransactionId('PY'),
        'webservice_type' => 'manifiesto',
        'country' => 'PY',
        'webservice_url' => $this->getWsdlUrl(), // ← FIX
        'soap_action' => 'EnviarMensajeFluvial',
        'environment' => $this->config['environment'] ?? 'testing',
        'status' => 'pending',
        'retry_count' => 0,
        'max_retries' => 3,
        'currency_code' => 'USD',
        'container_count' => 0,
        'bill_of_lading_count' => 0,
        'certificate_used' => $this->company->getCertificatePathForCountry('paraguay'),
        'additional_metadata' => $additionalData,
    ]);
}

    
}