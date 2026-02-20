<?php

namespace App\Services\Simple;

use App\Models\Company;
use App\Models\User;
use App\Models\Voyage;
use App\Models\WebserviceTransaction;
use App\Services\Simple\Soap\ParaguaySecureSoapClient;
use App\Services\Simple\Soap\ParaguayWSSecurityBuilder;
use App\Services\Simple\ParaguayWsaaService;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use SoapClient;
use SoapFault;
use App\Models\VoyageAttachment;

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
            'testing' => 'https://securetest.aduana.gov.py/gdsf/serviciogdsf?wsdl',
            'production' => 'https://secure.aduana.gov.py/wsdl/gdsf/serviciogdsf',
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
            'dna_public_certificate' => config('services.paraguay.dna_public_certificate'),
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
        if (! $voyage->voyage_number) {
            $errors[] = 'Viaje sin número de viaje';
        }

        if (! $voyage->leadVessel) {
            $errors[] = 'Viaje sin embarcación principal asignada';
        }

        if (! $voyage->originPort || ! $voyage->destinationPort) {
            $errors[] = 'Viaje sin puertos de origen/destino';
        }

        // ========================================
        // VALIDACIÓN DE CERTIFICADO CON BYPASS
        // ========================================

        $shouldBypass = $this->company->shouldBypassTesting('paraguay');
        $hasCertificate = $this->company->hasCertificateForCountry('paraguay');

        if ($this->config['require_certificate']) {
            if (! $hasCertificate) {
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

                if ($certPath && ! \Illuminate\Support\Facades\Storage::exists($certPath)) {
                    if ($shouldBypass) {
                        $warnings[] = 'Archivo de certificado no encontrado (usando modo bypass)';
                    } else {
                        $errors[] = 'Archivo de certificado no encontrado';
                    }
                }
            }

            // Validar RUC
            if (! $this->company->tax_id) {
                $errors[] = 'Empresa sin RUC/Tax ID configurado';
            }
        }

        // ========================================
        // VALIDACIÓN DE CREDENCIALES DNA CON BYPASS
        // ========================================

       /*  $auth = $this->config['auth'];
        $hasCredentials = ! empty($auth['idUsuario']) && ! empty($auth['ticket']) && ! empty($auth['firma']);

        if (! $hasCredentials) {
            if ($shouldBypass) {
                // Con bypass, credenciales faltantes son solo advertencia
                $warnings[] = 'Credenciales DNA no configuradas (usando modo bypass)';
                $warnings[] = 'Configure las credenciales DNA en: Configuración → Webservices → Paraguay';
            } else {
                // Sin bypass, credenciales son obligatorias
                $errors[] = 'Credenciales DNA Paraguay incompletas';
                $warnings[] = 'Configure las credenciales DNA en: Configuración → Webservices → Paraguay';
            }
        } */

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
            $validation['errors'][] = 'Error interno en validación: '.$e->getMessage();
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
            if (! $validation['can_process']) {
                throw new Exception('Viaje no válido: '.implode(', ', $validation['errors']));
            }

            // 2. Verificar si ya fue enviado
            $existingXffm = $this->getExistingTransaction($voyage, 'XFFM');
            if ($existingXffm && ! ($options['force_resend'] ?? false)) {
                return [
                    'success' => false,
                    'error_message' => 'XFFM ya fue enviado. Use force_resend=true para reenviar.',
                    'existing_nroViaje' => $existingXffm->external_reference,
                ];
            }

            // 3. Generar XML automáticamente
            $transactionId = $this->generateTransactionId('XFFM');
            $xml = $this->paraguayXmlGenerator->createXffmXml($voyage, $transactionId);
            // Guardar XML original para DNA (sin firmar)
            file_put_contents(storage_path("logs/DNA_XFFM_ORIGINAL_{$voyage->id}_" . now()->format('YmdHis') . ".xml"), $xml);

            // 4. Crear transacción
            $transaction = $this->createTransaction($voyage, [
                'tipo_mensaje' => 'XFFM',
                'transaction_id' => $transactionId,
            ]);

            $transaction->request_xml = $xml;
            $transaction->save();

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

                // 🔍 LOG para verificar extracción
                $this->logOperation('info', 'Extrayendo nroViaje de respuesta XFFM', [
                    'nroViaje_extracted' => $nroViaje,
                    'response_data_type' => gettype($soapResult['response_data']),
                    'has_response_data' => isset($soapResult['response_data']),
                ]);

                // Si no se extrajo el nroViaje, intentar del raw_response XML
                if (! $nroViaje && isset($soapResult['raw_response'])) {
                    $nroViaje = $this->extractNroViajeFromRawXml($soapResult['raw_response']);
                    $this->logOperation('info', 'nroViaje extraído de raw_response', [
                        'nroViaje' => $nroViaje,
                    ]);
                }

                // Validar que tenemos nroViaje antes de actualizar
                if (! $nroViaje) {
                    $this->logOperation('warning', 'No se pudo extraer nroViaje de la respuesta XFFM', [
                        'transaction_id' => $transaction->id,
                        'raw_response_length' => strlen($soapResult['raw_response'] ?? ''),
                    ]);
                }

                // Detectar REJECTED antes de persistir
                $dnaStatus = $this->determineDnaStatus($soapResult);

                $transaction->update([
                    'status' => $dnaStatus['status'],
                    'external_reference' => $nroViaje,
                    'response_xml' => $soapResult['raw_response'] ?? null,
                ]);

                // Guardar nroViaje también en metadata como respaldo
                if ($nroViaje) {
                    $metadata = $transaction->additional_metadata ?? [];
                    $metadata['nro_viaje'] = $nroViaje;
                    $transaction->update(['additional_metadata' => $metadata]);
                }

                // Actualizar estado del voyage
                $this->updateWebserviceStatus($voyage, 'XFFM', [
                    'status' => $dnaStatus['status'],
                    'nro_viaje' => $nroViaje,
                ]);

                DB::commit();

                $this->logOperation($dnaStatus['was_rejected'] ? 'warning' : 'info', 
                    $dnaStatus['was_rejected'] ? 'XFFM RECHAZADO por DNA' : 'XFFM enviado exitosamente', [
                    'voyage_id' => $voyage->id,
                    'nroViaje' => $nroViaje,
                    'transaction_id' => $transactionId,
                    'dna_status' => $dnaStatus['details']['status_code'],
                    'dna_reason' => $dnaStatus['details']['reason'],
                ]);

                return [
                    'success' => !$dnaStatus['was_rejected'],
                    'accepted' => !$dnaStatus['was_rejected'],
                    'nroViaje' => $nroViaje,
                    'transaction_id' => $transactionId,
                    'message' => $dnaStatus['was_rejected'] 
                        ? 'RECHAZADO por DNA: ' . ($dnaStatus['details']['reason'] ?? 'Sin detalle')
                        : 'XFFM enviado exitosamente',
                    'dna_response' => $dnaStatus['details'],
                ];
            } else {
                throw new Exception($soapResult['error_message'] ?? 'Error SOAP desconocido');
            }

        } catch (Exception $e) {
            
            // Resetear transaction_id antes de loguear
            $this->currentTransactionId = null;

            $this->logOperation('error', 'Error enviando XFFM', [
                'voyage_id' => $voyage->id,
                'error' => $e->getMessage(),
            ]);
            
            DB::rollBack();
            
            return [
                'success' => false,
                'error_message' => $e->getMessage(),
            ];
        }
    }

    /**
     * 2. XFBL - Conocimientos/BLs
     * Requiere XFFM enviado previamente
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
            if (! $xffmTransaction || $xffmTransaction->status !== 'sent') {
                throw new Exception('Debe enviar XFFM primero');
            }

            // 2. Obtener nroViaje con fallback a response_xml
            $nroViaje = $xffmTransaction->external_reference;
            if (! $nroViaje && $xffmTransaction->response_xml) {
                $nroViaje = $this->extractNroViajeFromRawXml($xffmTransaction->response_xml);
                if ($nroViaje) {
                    $xffmTransaction->update(['external_reference' => $nroViaje]);
                }
            }

            $this->logOperation('info', '🔍 Verificando nroViaje para XFBL', [
                'external_reference' => $nroViaje,
                'has_response_xml' => ! empty($xffmTransaction->response_xml),
            ]);

            // Si external_reference está vacío, extraer del response_xml
            if (! $nroViaje && $xffmTransaction->response_xml) {
                $this->logOperation('info', '🔄 Extrayendo nroViaje de response_xml', [
                    'xffm_transaction_id' => $xffmTransaction->id,
                ]);

                $nroViaje = $this->extractNroViajeFromRawXml($xffmTransaction->response_xml);

                // Actualizar el external_reference para la próxima vez
                if ($nroViaje) {
                    $xffmTransaction->update(['external_reference' => $nroViaje]);
                    $this->logOperation('info', '✅ nroViaje recuperado y guardado', [
                        'nroViaje' => $nroViaje,
                    ]);
                }
            }

            if (! $nroViaje) {
                throw new Exception('No se encontró nroViaje de XFFM');
            }

            // 3. Validar que hay BLs
            $blCount = $voyage->shipments->flatMap->billsOfLading->count();
            if ($blCount === 0) {
                throw new Exception('No hay Bills of Lading para enviar');
            }

            // 4. Generar XML
            $transactionId = $this->generateTransactionId('XFBL');
            $xml = $this->paraguayXmlGenerator->createXfblXml($voyage, $transactionId, $nroViaje);

            // 5. Crear transacción
            $transaction = $this->createTransaction($voyage, [
                'tipo_mensaje' => 'XFBL',
                'transaction_id' => $transactionId,
            ]);

            $transaction->request_xml = $xml;
            $transaction->save();

            $this->currentTransactionId = $transaction->id;  // ← ESTA LÍNEA

            // 6. Enviar SOAP
            $soapResult = $this->sendSoapMessage([
                'codigo' => 'XFBL',
                'version' => '1.0',
                'viaje' => $nroViaje,
                'xml' => $xml,
            ]);

            // 7. Procesar respuesta
            if ($soapResult['success']) {
                $dnaStatus = $this->determineDnaStatus($soapResult);

                $transaction->update([
                    'status' => $dnaStatus['status'],
                    'response_xml' => $soapResult['raw_response'] ?? null,
                ]);

                $this->updateWebserviceStatus($voyage, 'XFBL', [
                    'status' => $dnaStatus['status'],
                    'nro_viaje' => $nroViaje,
                ]);

                DB::commit();

                return [
                    'success' => !$dnaStatus['was_rejected'],
                    'transaction_id' => $transactionId,
                    'nroViaje' => $nroViaje,
                    'message' => $dnaStatus['was_rejected']
                        ? 'RECHAZADO por DNA: ' . ($dnaStatus['details']['reason'] ?? 'Sin detalle')
                        : 'XFBL enviado exitosamente',
                    'bl_count' => $blCount,
                    'dna_response' => $dnaStatus['details'],
                ];
            } else {
                throw new Exception($soapResult['error_message'] ?? 'Error SOAP');
            }

        } catch (Exception $e) {
            
            // Resetear transaction_id antes de loguear
            $this->currentTransactionId = null;

            $this->logOperation('error', 'Error enviando XFBL', [
                'voyage_id' => $voyage->id,
                'error' => $e->getMessage(),
            ]);

            DB::rollBack();

            return [
                'success' => false,
                'error_message' => $e->getMessage(),
            ];
        }
    }

    /**
     * 3. XFBT - Hoja de Ruta (Contenedores)
     * Requiere XFFM enviado previamente
     */
    public function sendXfbt(Voyage $voyage, array $options = []): array
    {
        \Log::info('🔵 XFBT - Inicio', ['voyage_id' => $voyage->id]);

        $this->logOperation('info', 'Iniciando envío XFBT (Contenedores)', [
            'voyage_id' => $voyage->id,
        ]);

        DB::beginTransaction();

        try {
            // 1. Verificar XFFM
            $xffmTransaction = $this->getExistingTransaction($voyage, 'XFFM');
            if (! $xffmTransaction || $xffmTransaction->status !== 'sent') {
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
                $this->logOperation('warning', 'Viaje sin contenedores, saltando XFBT', [
                    'voyage_id' => $voyage->id,
                ]);

                $this->updateWebserviceStatus($voyage, 'XFBT', [
                    'status' => 'skipped',
                    'additional_data' => [
                        'skipped' => true,
                        'reason' => 'Sin contenedores',
                    ],
                ]);

                DB::commit();

                return [
                    'success' => true,
                    'skipped' => true,
                    'message' => 'XFBT omitido: viaje sin contenedores',
                    'container_count' => 0,
                ];
            }

            // 3. Generar XML
            $transactionId = $this->generateTransactionId('XFBT');
            $xml = $this->paraguayXmlGenerator->createXfbtXml($voyage, $transactionId, $nroViaje);

            // 4. Crear transacción
            $transaction = $this->createTransaction($voyage, [
                'tipo_mensaje' => 'XFBT',
                'transaction_id' => $transactionId,
            ]);

            $this->currentTransactionId = $transaction->id;

            // 5. Enviar SOAP
            $soapResult = $this->sendSoapMessage([
                'codigo' => 'XFBT',
                'version' => '1.0',
                'viaje' => $nroViaje,
                'xml' => $xml,
            ]);

            // 6. Procesar respuesta
            if ($soapResult['success']) {
                $dnaStatus = $this->determineDnaStatus($soapResult);

                $transaction->update([
                    'status' => $dnaStatus['status'],
                    'response_xml' => $soapResult['raw_response'] ?? null,
                    'request_xml' => $xml,
                ]);

                $this->updateWebserviceStatus($voyage, 'XFBT', [
                    'status' => $dnaStatus['status'],
                    'nro_viaje' => $nroViaje,
                ]);

                DB::commit();

                return [
                    'success' => !$dnaStatus['was_rejected'],
                    'transaction_id' => $transactionId,
                    'message' => $dnaStatus['was_rejected']
                        ? 'RECHAZADO por DNA: ' . ($dnaStatus['details']['reason'] ?? 'Sin detalle')
                        : 'XFBT enviado exitosamente',
                    'container_count' => $containers->count(),
                    'dna_response' => $dnaStatus['details'],
                ];
            } else {
                throw new Exception($soapResult['error_message'] ?? 'Error SOAP');
            }

        } catch (Exception $e) {
            
            // Resetear transaction_id antes de loguear
            $this->currentTransactionId = null;

            $this->logOperation('error', 'Error enviando XFBT', [
                'voyage_id' => $voyage->id,
                'error' => $e->getMessage(),
            ]);
            
            DB::rollBack();
            
            return [
                'success' => false,
                'error_message' => $e->getMessage(),
            ];
        }
    }

    /**
     * 4. XFCT - Cerrar Viaje
     * Último paso - Cierra el nroViaje cuando todo está completo
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
            if (! $xffmTransaction || $xffmTransaction->status !== 'sent') {
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

            $this->currentTransactionId = $transaction->id;  // ← ESTA LÍNEA

            // 4. Enviar SOAP
            $soapResult = $this->sendSoapMessage([
                'codigo' => 'XFCT',
                'version' => '1.0',
                'viaje' => $nroViaje,
                'xml' => $xml,
            ]);

            // 5. Procesar respuesta
            if ($soapResult['success']) {
                $dnaStatus = $this->determineDnaStatus($soapResult);

                $transaction->update([
                    'status' => $dnaStatus['status'],
                    'response_xml' => $soapResult['raw_response'] ?? null,
                ]);

                $this->updateWebserviceStatus($voyage, 'XFCT', [
                    'status' => $dnaStatus['status'],
                    'nro_viaje' => $nroViaje,
                ]);

                DB::commit();

                return [
                    'success' => !$dnaStatus['was_rejected'],
                    'transaction_id' => $transactionId,
                    'message' => $dnaStatus['was_rejected']
                        ? 'RECHAZADO por DNA: ' . ($dnaStatus['details']['reason'] ?? 'Sin detalle')
                        : 'Viaje cerrado exitosamente',
                    'nroViaje' => $nroViaje,
                    'dna_response' => $dnaStatus['details'],
                ];
            } else {
                throw new Exception($soapResult['error_message'] ?? 'Error SOAP');
            }

        } catch (Exception $e) {
            
            // Resetear transaction_id antes de loguear
            $this->currentTransactionId = null;

            $this->logOperation('error', 'Error enviando XFCT', [
                'voyage_id' => $voyage->id,
                'error' => $e->getMessage(),
            ]);

            DB::rollBack();

            return [
                'success' => false,
                'error_message' => $e->getMessage(),
            ];
        }
    }

    /**
     * 5. XISP - Incluir Embarcación en Viaje
     * Agrega una embarcación adicional al viaje (antes de generar manifiesto)
     * Requiere XFFM enviado previamente
     *
     * @param Voyage $voyage Viaje
     * @param \App\Models\Vessel $vessel Embarcación a incluir
     * @param array $options in_ballast (S/N), seals (array), force_resend (bool)
     * @return array Resultado
     */
    public function sendXisp(Voyage $voyage, $vessel, array $options = []): array
    {
        $this->logOperation('info', 'Iniciando envío XISP (Incluir Embarcación)', [
            'voyage_id' => $voyage->id,
            'vessel_id' => $vessel->id,
            'vessel_name' => $vessel->name,
        ]);

        DB::beginTransaction();

        try {
            // 1. Verificar XFFM
            $xffmTransaction = $this->getExistingTransaction($voyage, 'XFFM');
            if (!$xffmTransaction || $xffmTransaction->status !== 'sent') {
                throw new Exception('Debe enviar XFFM primero');
            }

            $nroViaje = $xffmTransaction->external_reference;
            if (!$nroViaje && $xffmTransaction->response_xml) {
                $nroViaje = $this->extractNroViajeFromRawXml($xffmTransaction->response_xml);
                if ($nroViaje) {
                    $xffmTransaction->update(['external_reference' => $nroViaje]);
                }
            }

            if (!$nroViaje) {
                throw new Exception('No se pudo obtener nroViaje del XFFM');
            }

            // 2. Generar XML
            $transactionId = $this->generateTransactionId('XISP');
            $xml = $this->paraguayXmlGenerator->createXispXml($voyage, $vessel, $transactionId, $nroViaje, $options);

            // Guardar XML para debug
            file_put_contents(storage_path("logs/DNA_XISP_{$voyage->id}_{$vessel->id}_" . now()->format('YmdHis') . ".xml"), $xml);

            // 3. Crear transacción
            $transaction = $this->createTransaction($voyage, [
                'tipo_mensaje' => 'XISP',
                'transaction_id' => $transactionId,
            ]);

            $transaction->request_xml = $xml;
            $transaction->save();

            $this->currentTransactionId = $transaction->id;

            // 4. Enviar SOAP
            $soapResult = $this->sendSoapMessage([
                'codigo' => 'XISP',
                'version' => '1.0',
                'viaje' => $nroViaje,
                'xml' => $xml,
            ]);

            // 5. Procesar respuesta
            if ($soapResult['success']) {
                $dnaStatus = $this->determineDnaStatus($soapResult);

                $transaction->update([
                    'status' => $dnaStatus['status'],
                    'response_xml' => $soapResult['raw_response'] ?? null,
                    'request_xml' => $xml,
                ]);

                $this->updateWebserviceStatus($voyage, 'XISP', [
                    'status' => $dnaStatus['status'],
                    'nro_viaje' => $nroViaje,
                    'vessel_id' => $vessel->id,
                    'vessel_name' => $vessel->name,
                ]);

                DB::commit();

                return [
                    'success' => !$dnaStatus['was_rejected'],
                    'transaction_id' => $transactionId,
                    'message' => $dnaStatus['was_rejected']
                        ? 'RECHAZADO por DNA: ' . ($dnaStatus['details']['reason'] ?? 'Sin detalle')
                        : 'Embarcación incluida exitosamente',
                    'vessel_name' => $vessel->name,
                    'dna_response' => $dnaStatus['details'],
                ];
            } else {
                throw new Exception($soapResult['error_message'] ?? 'Error SOAP');
            }

        } catch (Exception $e) {
            $this->currentTransactionId = null;

            $this->logOperation('error', 'Error enviando XISP', [
                'voyage_id' => $voyage->id,
                'vessel_id' => $vessel->id ?? null,
                'error' => $e->getMessage(),
            ]);

            DB::rollBack();

            return [
                'success' => false,
                'error_message' => $e->getMessage(),
            ];
        }
    }

    /**
     * 6. XRSP - Desvincular Embarcación de Viaje
     * Remueve una embarcación del viaje (antes de generar manifiesto)
     * Requiere XFFM enviado previamente
     *
     * @param Voyage $voyage Viaje
     * @param \App\Models\Vessel $vessel Embarcación a desvincular
     * @param array $options force_resend (bool)
     * @return array Resultado
     */
    public function sendXrsp(Voyage $voyage, $vessel, array $options = []): array
    {
        $this->logOperation('info', 'Iniciando envío XRSP (Desvincular Embarcación)', [
            'voyage_id' => $voyage->id,
            'vessel_id' => $vessel->id,
            'vessel_name' => $vessel->name,
        ]);

        DB::beginTransaction();

        try {
            // 1. Verificar XFFM
            $xffmTransaction = $this->getExistingTransaction($voyage, 'XFFM');
            if (!$xffmTransaction || $xffmTransaction->status !== 'sent') {
                throw new Exception('Debe enviar XFFM primero');
            }

            $nroViaje = $xffmTransaction->external_reference;
            if (!$nroViaje && $xffmTransaction->response_xml) {
                $nroViaje = $this->extractNroViajeFromRawXml($xffmTransaction->response_xml);
                if ($nroViaje) {
                    $xffmTransaction->update(['external_reference' => $nroViaje]);
                }
            }

            if (!$nroViaje) {
                throw new Exception('No se pudo obtener nroViaje del XFFM');
            }

            // 2. Generar XML
            $transactionId = $this->generateTransactionId('XRSP');
            $xml = $this->paraguayXmlGenerator->createXrspXml($vessel, $transactionId, $nroViaje);

            // Guardar XML para debug
            file_put_contents(storage_path("logs/DNA_XRSP_{$voyage->id}_{$vessel->id}_" . now()->format('YmdHis') . ".xml"), $xml);

            // 3. Crear transacción
            $transaction = $this->createTransaction($voyage, [
                'tipo_mensaje' => 'XRSP',
                'transaction_id' => $transactionId,
            ]);

            $transaction->request_xml = $xml;
            $transaction->save();

            $this->currentTransactionId = $transaction->id;

            // 4. Enviar SOAP
            $soapResult = $this->sendSoapMessage([
                'codigo' => 'XRSP',
                'version' => '1.0',
                'viaje' => $nroViaje,
                'xml' => $xml,
            ]);

            // 5. Procesar respuesta
            if ($soapResult['success']) {
                $dnaStatus = $this->determineDnaStatus($soapResult);

                $transaction->update([
                    'status' => $dnaStatus['status'],
                    'response_xml' => $soapResult['raw_response'] ?? null,
                    'request_xml' => $xml,
                ]);

                $this->updateWebserviceStatus($voyage, 'XRSP', [
                    'status' => $dnaStatus['status'],
                    'nro_viaje' => $nroViaje,
                    'vessel_id' => $vessel->id,
                    'vessel_name' => $vessel->name,
                ]);

                DB::commit();

                return [
                    'success' => !$dnaStatus['was_rejected'],
                    'transaction_id' => $transactionId,
                    'message' => $dnaStatus['was_rejected']
                        ? 'RECHAZADO por DNA: ' . ($dnaStatus['details']['reason'] ?? 'Sin detalle')
                        : 'Embarcación desvinculada exitosamente',
                    'vessel_name' => $vessel->name,
                    'dna_response' => $dnaStatus['details'],
                ];
            } else {
                throw new Exception($soapResult['error_message'] ?? 'Error SOAP');
            }

        } catch (Exception $e) {
            $this->currentTransactionId = null;

            $this->logOperation('error', 'Error enviando XRSP', [
                'voyage_id' => $voyage->id,
                'vessel_id' => $vessel->id ?? null,
                'error' => $e->getMessage(),
            ]);

            DB::rollBack();

            return [
                'success' => false,
                'error_message' => $e->getMessage(),
            ];
        }
    }

    // ====================================
    // DOCUMENTO IMG - enviarDocumento
    // ====================================

    /**
     * Enviar documento adjunto a DNA Paraguay via método SOAP enviarDocumento
     * 
     * DIFERENTE de EnviarMensajeFluvial - usa método SOAP propio
     * Manual GDSF: enviarDocumento(DocumentoIMG, Autenticacion)
     * 
     * DocumentoIMG: { nroViaje, idTitTrans, tipo, nroDocumento, archivo (base64 max 5MB) }
     * Para invalidar: enviar archivo = null
     *
     * @param Voyage $voyage Viaje (debe tener XFFM enviado con nroViaje)
     * @param \App\Models\VoyageAttachment $attachment Adjunto a enviar
     * @param array $options Opciones adicionales: invalidate (bool) para anular documento
     * @return array ['success' => bool, 'message' => string, ...]
     */
    public function sendDocumentoImg(Voyage $voyage, \App\Models\VoyageAttachment $attachment, array $options = []): array
    {
        $this->logOperation('info', 'Iniciando envío DocumentoIMG', [
            'voyage_id' => $voyage->id,
            'attachment_id' => $attachment->id,
            'document_number' => $attachment->document_number,
            'document_type' => $attachment->document_type,
            'invalidate' => $options['invalidate'] ?? false,
        ]);

        try {
            // 1. Verificar XFFM enviado (necesitamos nroViaje)
            $xffmTransaction = $this->getExistingTransaction($voyage, 'XFFM');
            if (!$xffmTransaction) {
                throw new Exception('Debe enviar XFFM primero para obtener nroViaje');
            }

            $nroViaje = $xffmTransaction->external_reference;
            if (empty($nroViaje)) {
                throw new Exception('No se encontró nroViaje en la transacción XFFM');
            }

            // 2. Verificar que el attachment tiene BL asociado
            if (!$attachment->bill_of_lading_id) {
                throw new Exception('El documento debe estar asociado a un Conocimiento (BL)');
            }

            $bl = $attachment->billOfLading;
            if (!$bl) {
                throw new Exception('Conocimiento de embarque no encontrado');
            }

            // idTitTrans = bill_number del BL (como se usa en XFBL)
            $idTitTrans = $bl->bill_number;
            if (empty($idTitTrans)) {
                throw new Exception('El BL no tiene número de conocimiento (bill_number)');
            }

            // 3. Validar documento
            $tipo = $attachment->document_type;
            $nroDocumento = $attachment->document_number;

            if (empty($tipo) || empty($nroDocumento)) {
                throw new Exception('El documento requiere tipo EDIFACT y número de documento');
            }

            // 4. Obtener archivo en base64 (o null si es invalidación)
            $isInvalidate = $options['invalidate'] ?? false;
            $archivoBase64 = null;

            if (!$isInvalidate) {
                // Verificar tamaño (max 5MB)
                if ($attachment->file_size > 5242880) {
                    throw new Exception('El archivo excede el máximo de 5MB permitido por DNA');
                }

                $archivoBase64 = $attachment->getBase64Content();
            }

            // 5. Enviar SOAP
            $soapResult = $this->sendDocumentoSoap($nroViaje, $idTitTrans, $tipo, $nroDocumento, $archivoBase64);

            // 6. Procesar resultado
            if ($soapResult['success']) {
                // Marcar attachment como enviado
                $attachment->update([
                    'sent_to_dna' => true,
                    'sent_to_dna_at' => now(),
                ]);

                $this->logOperation('info', 'DocumentoIMG enviado exitosamente', [
                    'voyage_id' => $voyage->id,
                    'attachment_id' => $attachment->id,
                    'nro_viaje' => $nroViaje,
                    'id_tit_trans' => $idTitTrans,
                    'invalidate' => $isInvalidate,
                ]);

                return [
                    'success' => true,
                    'message' => $isInvalidate
                        ? 'Documento invalidado exitosamente en DNA'
                        : 'Documento enviado exitosamente a DNA',
                    'attachment_id' => $attachment->id,
                    'nro_viaje' => $nroViaje,
                    'id_tit_trans' => $idTitTrans,
                ];
            } else {
                throw new Exception($soapResult['error_message'] ?? 'Error SOAP enviarDocumento');
            }

        } catch (Exception $e) {
            $this->logOperation('error', 'Error enviando DocumentoIMG', [
                'voyage_id' => $voyage->id,
                'attachment_id' => $attachment->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error_message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Envío masivo de documentos pendientes para un viaje
     * Envía todos los VoyageAttachment que tengan sent_to_dna = false
     *
     * @return array ['success' => bool, 'sent' => int, 'failed' => int, 'results' => array]
     */
    public function sendAllPendingDocuments(Voyage $voyage): array
    {
        $pendingAttachments = \App\Models\VoyageAttachment::where('voyage_id', $voyage->id)
            ->where('sent_to_dna', false)
            ->whereNotNull('bill_of_lading_id')
            ->whereNotNull('document_type')
            ->whereNotNull('document_number')
            ->get();

        if ($pendingAttachments->isEmpty()) {
            return [
                'success' => true,
                'message' => 'No hay documentos pendientes de envío',
                'sent' => 0,
                'failed' => 0,
                'results' => [],
            ];
        }

        $results = [];
        $sent = 0;
        $failed = 0;

        foreach ($pendingAttachments as $attachment) {
            $result = $this->sendDocumentoImg($voyage, $attachment);
            $results[] = [
                'attachment_id' => $attachment->id,
                'document_number' => $attachment->document_number,
                'success' => $result['success'],
                'message' => $result['message'] ?? $result['error_message'] ?? '',
            ];

            if ($result['success']) {
                $sent++;
            } else {
                $failed++;
            }
        }

        return [
            'success' => $failed === 0,
            'message' => "Enviados: {$sent}, Fallidos: {$failed}",
            'sent' => $sent,
            'failed' => $failed,
            'results' => $results,
        ];
    }

    /**
     * Llamada SOAP a enviarDocumento (método DIFERENTE de EnviarMensajeFluvial)
     * CON BYPASS INTELIGENTE
     */
    private function sendDocumentoSoap(
        string $nroViaje,
        string $idTitTrans,
        string $tipo,
        string $nroDocumento,
        ?string $archivoBase64
    ): array {
        // ========================================
        // BYPASS INTELIGENTE
        // ========================================
        $shouldBypass = $this->company->shouldBypassTesting('paraguay');
        $environment = $this->config['environment'] ?? 'testing';

        if ($shouldBypass) {
            $this->logOperation('info', '🔄 BYPASS DocumentoIMG - Simulando respuesta', [
                'nro_viaje' => $nroViaje,
                'id_tit_trans' => $idTitTrans,
                'tipo' => $tipo,
                'nro_documento' => $nroDocumento,
                'has_archivo' => !is_null($archivoBase64),
            ]);

            return [
                'success' => true,
                'raw_response' => '<enviarDocumentoResponse><confirmacion>Documento recibido correctamente (BYPASS)</confirmacion></enviarDocumentoResponse>',
            ];
        }

        // ========================================
        // CONEXIÓN REAL A DNA PARAGUAY
        // ========================================
        try {
            $this->logOperation('info', '🌐 Enviando DocumentoIMG a DNA', [
                'nro_viaje' => $nroViaje,
                'id_tit_trans' => $idTitTrans,
                'environment' => $environment,
            ]);

            // Crear cliente SOAP
            if (!$this->soapClient) {
                $this->soapClient = $this->createSoapClient();
            }
            $client = $this->soapClient;

            // Obtener tokens WSAA
            $wsaaService = new ParaguayWsaaService($this->company, $environment);
            $wsaaTokens = $wsaaService->getTokens();
            $ruc = $this->company->tax_id;

            // Llamada SOAP al método enviarDocumento
            $result = $client->enviarDocumento([
                'DocumentoIMG' => [
                    'nroViaje' => $nroViaje,
                    'idTitTrans' => $idTitTrans,
                    'tipo' => $tipo,
                    'nroDocumento' => $nroDocumento,
                    'archivo' => $archivoBase64,
                ],
                'autenticacion' => [
                    'firma' => $wsaaTokens['sign'],
                    'idUsuario' => $wsaaTokens['ruc'] ?? $ruc,
                    'ticket' => $wsaaTokens['token'],
                ],
            ]);

            $rawResponse = $client->__getLastResponse();

            $this->logOperation('info', 'enviarDocumento respuesta recibida', [
                'raw_length' => strlen($rawResponse ?? ''),
            ]);

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
        //$auth = $this->config['auth'];

        // Verificar si debe usar bypass
        $useBypass = false;

        // Razón 1: Bypass activado explícitamente en configuración
        if ($shouldBypass) {
            $useBypass = true;
            $bypassReason = 'Bypass activado en configuración de empresa';
        }

        // Razón 2: Ambiente testing sin credenciales DNA completas
        /* if ($environment === 'testing' && (empty($auth['idUsuario']) || empty($auth['ticket']) || empty($auth['firma']))) {
            $useBypass = true;
            $bypassReason = 'Ambiente testing sin credenciales DNA';
        } */

        // Razón 3: Certificado es de testing (verificar si existe y es fake)
        /* $certificate = $this->company->getCertificate('paraguay');
        if ($certificate && isset($certificate['alias']) && str_contains(strtoupper($certificate['alias']), 'TEST')) {
            $useBypass = true;
            $bypassReason = 'Certificado de testing detectado';
        } */

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
            $this->logOperation('info', '🌐 Conectando a DNA Paraguay', [
                'codigo' => $params['codigo'],
                'viaje' => $params['viaje'],
                'environment' => $environment,
            ]);

            // Credenciales DNA Paraguay (sin WSAA)
            $ruc = $this->company->tax_id; 

           /*  $client = new \SoapClient($this->getWsdlUrl(), [
                'trace' => 1,
                'exceptions' => true,
                'soap_version' => SOAP_1_1,
                'stream_context' => stream_context_create([
                    'ssl' => [
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                        'allow_self_signed' => true,
                    ]
                ])
            ]); */

            // Crear cliente SOAP con WS-Security si no existe
            if (!$this->soapClient) {
                $this->soapClient = $this->createSoapClient();
            }

            $client = $this->soapClient;

            // Obtener tokens WSAA dinámicamente
            $wsaaService = new ParaguayWsaaService($this->company, $environment);
            $wsaaTokens = $wsaaService->getTokens();

            $this->logOperation('info', 'Tokens WSAA obtenidos', [
                'has_token' => !empty($wsaaTokens['token']),
                'has_sign' => !empty($wsaaTokens['sign']),
                'ruc' => $wsaaTokens['ruc'] ?? $ruc,
            ]);

            // Parámetros GDSF con autenticación WSAA dinámica
            $soapParams = [
                'codigo' => $params['codigo'],
                'version' => $params['version'],
                'viaje' => $params['viaje'],
                'xml' => $params['xml'],
                'Autenticacion' => [
                    'idUsuario' => $wsaaTokens['ruc'] ?? $ruc,
                    'ticket' => $wsaaTokens['token'],
                    'firma' => $wsaaTokens['sign'],
                ],
            ];
            // Enviar
            // Llamada directa al método SOAP (más clara y correcta)
            $result = $client->enviarMensajeFluvial([
                'codigo' => $soapParams['codigo'],
                'version' => $soapParams['version'],
                'viaje' => $soapParams['viaje'],
                'xml' => $soapParams['xml'],
                'autenticacion' => [
                    'firma' => $wsaaTokens['sign'],
                    'idUsuario' => $wsaaTokens['ruc'] ?? $ruc,
                    'ticket' => $wsaaTokens['token'],
                ],
            ]);
            $rawResponse = $client->__getLastResponse();

            // Persistir WebserviceResponse con XML
            try {
                \App\Models\WebserviceResponse::create([
                    'transaction_id' => $this->currentTransactionId,
                    'response_type' => 'success',
                    'processing_status' => 'completed',
                    'requires_action' => false,
                    'customs_metadata' => [
                        'request_xml' => $params['xml'],
                        'raw_response' => $rawResponse,
                        'codigo' => $params['codigo'],
                    ],
                    'customs_status' => 'sent',
                    'processed_at' => now(),
                ]);
            } catch (\Exception $e) {
                \Log::warning('Error guardando XML real', ['error' => $e->getMessage()]);
            }

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
        try {
            $codigo = $params['codigo'];
            $viaje = $params['viaje'];

            \Log::info('🐛 Inicio generateBypassResponse');

            $nroViaje = $viaje ?? 'PY'.date('Y').str_pad(rand(1, 99999), 5, '0', STR_PAD_LEFT);

            \Log::info('🐛 Antes del switch');

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
                case 'XISP':
                    $responseXml = $this->generateXispBypassXml($viaje);
                    break;
                case 'XRSP':
                    $responseXml = $this->generateXrspBypassXml($viaje);
                    break;
                default:
                    $responseXml = '<response><status>OK</status></response>';
            }

            \Log::info('🐛 Después del switch, antes de guardar');

            $responseData = [
                'voyage_number' => $viaje,
                'customs_status' => 'sent',
                'customs_metadata' => [
                    'codigo' => $codigo,
                    'request_xml' => $params['xml'],
                    'raw_response' => $responseXml,
                ],
            ];

            $response = \App\Models\WebserviceResponse::create([
                'transaction_id' => $this->currentTransactionId,
                'response_type' => 'success',
                'processing_status' => 'completed',
                'requires_action' => false,
                'response_data' => json_encode($responseData),
                'customs_status' => 'sent',
                'bypass' => true,
                'processed_at' => now(),
            ]);

            \Log::info('✅ Response guardado: '.$response->id);

        } catch (\Throwable $e) {
            \Log::error('💥 ERROR FATAL en generateBypassResponse: '.$e->getMessage().' | Línea: '.$e->getLine());
        }

        return [
            'success' => true,
            'response_data' => (object) [
                'nroViaje' => $nroViaje ?? 'ERROR',
                'estado' => 'ACEPTADO',
            ],
            'raw_response' => $responseXml ?? '<error/>',
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
            // CASO 1: Response es un objeto con nroViaje directo (BYPASS)
            if (is_object($responseData) && isset($responseData->nroViaje)) {
                return (string) $responseData->nroViaje;
            }

            // CASO 2: Response tiene XML string (->return->xml o ->xml)
            $xmlString = null;
            if (is_object($responseData) && isset($responseData->return->xml)) {
                $xmlString = $responseData->return->xml;
            } elseif (is_object($responseData) && isset($responseData->xml)) {
                $xmlString = $responseData->xml;
            }
            if ($xmlString) {
                $xml = simplexml_load_string($xmlString);
                if ($xml && isset($xml->nroViaje)) {
                    return (string) $xml->nroViaje;
                }
                if ($xml && isset($xml->MessageHeaderDocument->ID)) {
                    $id = (string) $xml->MessageHeaderDocument->ID;
                    if (!empty($id)) {
                        return $id;
                    }
                }
            }

            // CASO 3: Response es array con nroViaje
            if (is_array($responseData) && isset($responseData['nroViaje'])) {
                return (string) $responseData['nroViaje'];
            }

            // CASO 4: raw_response tiene XML con nroViaje (array)
            if (is_array($responseData) && isset($responseData['raw_response'])) {
                $xml = simplexml_load_string($responseData['raw_response']);
                if ($xml) {
                    $namespaces = $xml->getNamespaces(true);
                    $xml->registerXPathNamespace('soap', 'http://schemas.xmlsoap.org/soap/envelope/');
                    $xml->registerXPathNamespace('gdsf', 'http://gdsf.aduana.gov.py/');

                    $nroViaje = $xml->xpath('//nroViaje');
                    if (! empty($nroViaje)) {
                        return (string) $nroViaje[0];
                    }
                }
            }

            \Log::warning('No se pudo extraer nroViaje', [
                'response_type' => gettype($responseData),
                'response_keys' => is_object($responseData) ? get_object_vars($responseData) : (is_array($responseData) ? array_keys($responseData) : 'not_object_or_array'),
            ]);

            return null;

        } catch (Exception $e) {
            \Log::error('Error extrayendo nroViaje', [
                'error' => $e->getMessage(),
                'response' => json_encode($responseData),
            ]);

            return null;
       }
    }

    /**
     * Extraer detalles completos de la respuesta DNA (StatusCode, ReasonCode, Reason)
     */
    protected function extractDnaResponseDetails($responseData): array
    {
        $details = [
            'status_code' => null,
            'reason_code' => null,
            'reason' => null,
            'raw_xml' => null,
        ];

        try {
            // Obtener el XML de la respuesta
            $xmlString = null;
            
            if (is_object($responseData) && isset($responseData->return->xml)) {
                $xmlString = $responseData->return->xml;
            } elseif (is_object($responseData) && isset($responseData->xml)) {
                $xmlString = $responseData->xml;
            }

            if (!$xmlString) {
                return $details;
            }

            $details['raw_xml'] = $xmlString;

            // Parsear el XML
            libxml_use_internal_errors(true);
            $xml = @simplexml_load_string($xmlString);
            
            if ($xml === false) {
                return $details;
            }

            // Extraer StatusCode
            $statusCode = $xml->xpath('//StatusCode');
            if (!empty($statusCode)) {
                $details['status_code'] = (string) $statusCode[0];
            }

            // Extraer ReasonCode
            $reasonCode = $xml->xpath('//ReasonCode');
            if (!empty($reasonCode)) {
                $details['reason_code'] = (string) $reasonCode[0];
            }

            // Extraer Reason
            $reason = $xml->xpath('//Reason');
            if (!empty($reason)) {
                $details['reason'] = (string) $reason[0];
            }

            // También intentar extraer nroViaje si existe
            $nroViaje = $xml->xpath('//nroViaje');
            if (!empty($nroViaje)) {
                $details['nro_viaje'] = (string) $nroViaje[0];
            }

        } catch (\Exception $e) {
            \Log::error('Error extrayendo detalles DNA', ['error' => $e->getMessage()]);
        }

        return $details;
    }

    /**
     * Determinar status DNA desde la respuesta SOAP
     * Busca StatusCode/ReasonCode en response_data y raw_response (con fallback regex)
     */
    private function determineDnaStatus(array $soapResult): array
    {
        // 1. Intentar desde response_data (objeto SOAP parseado)
        $details = $this->extractDnaResponseDetails($soapResult['response_data'] ?? null);

        // 2. Fallback: regex directo del raw_response
        if (!$details['status_code'] && !empty($soapResult['raw_response'])) {
            $raw = $soapResult['raw_response'];
            
            if (preg_match('/<StatusCode>([^<]+)<\/StatusCode>/i', $raw, $m)) {
                $details['status_code'] = trim($m[1]);
            }
            if (preg_match('/<ReasonCode>([^<]+)<\/ReasonCode>/i', $raw, $m)) {
                $details['reason_code'] = trim($m[1]);
            }
            if (preg_match('/<Reason>([^<]+)<\/Reason>/i', $raw, $m)) {
                $details['reason'] = trim($m[1]);
            }
        }

        $wasRejected = ($details['status_code'] === 'REJECTED');

        return [
            'status' => $wasRejected ? 'rejected' : 'sent',
            'was_rejected' => $wasRejected,
            'details' => $details,
        ];
    }

    /**
     * Paraguay valida fecha YYYYMMDD dentro del idTransaccion
     * Formato manual: "6 digitos + fecha(yyyymmdd)"
     * Ref: /MICDTA/IDTRANSACCION - Error 11 GDSF
     */
    protected function generateTransactionId(string $tipo = 'PY'): string
    {
        $seq = str_pad(mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
        $fecha = date('Ymd');
        
        return $seq . $fecha;
    }

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

    /**
     * Extraer nroViaje directamente del XML raw de respuesta
     */
    private function extractNroViajeFromRawXml(string $xmlString): ?string
    {
        try {
            // Limpiar el XML
            $xmlString = trim($xmlString);

            // Remover BOM si existe
            if (substr($xmlString, 0, 3) === "\xEF\xBB\xBF") {
                $xmlString = substr($xmlString, 3);
            }

            $this->logOperation('info', '🔍 Parseando XML', [
                'xml_length' => strlen($xmlString),
                'xml_preview' => substr($xmlString, 0, 200),
            ]);

            // Intentar parsear con SimpleXML
            libxml_use_internal_errors(true);
            libxml_clear_errors();

            $xml = @simplexml_load_string($xmlString, 'SimpleXMLElement', LIBXML_NOCDATA);

            if ($xml === false) {
                $errors = libxml_get_errors();
                $this->logOperation('warning', 'SimpleXML falló, intentando regex', [
                    'errors' => array_map(fn ($e) => $e->message, $errors),
                ]);
                libxml_clear_errors();

                // FALLBACK: Usar expresión regular
                if (preg_match('/<nroViaje>([^<]+)<\/nroViaje>/i', $xmlString, $matches)) {
                    $nroViaje = trim($matches[1]);
                    $this->logOperation('info', '✅ nroViaje extraído con regex', [
                        'nroViaje' => $nroViaje,
                    ]);

                    return $nroViaje;
                }

                // FALLBACK 2: Buscar en MessageHeaderDocument > ID (formato GDSF)
                $decoded = html_entity_decode($xmlString);
                if (preg_match('/<MessageHeaderDocument>.*?<ID>([^<]+)<\/ID>/s', $decoded, $matches)) {
                    $nroViaje = trim($matches[1]);
                    if (!empty($nroViaje)) {
                        $this->logOperation('info', '✅ nroViaje extraído de MessageHeaderDocument/ID', [
                            'nroViaje' => $nroViaje,
                        ]);
                        return $nroViaje;
                    }
                }

                return null;
            }

            // Si SimpleXML funciona, usar XPath
            $xml->registerXPathNamespace('soap', 'http://schemas.xmlsoap.org/soap/envelope/');
            $xml->registerXPathNamespace('gdsf', 'http://gdsf.aduana.gov.py/');

            // Intentar múltiples paths
            $paths = [
                '//nroViaje',
                '//gdsf:nroViaje',
                '//*[local-name()="nroViaje"]',
            ];

            foreach ($paths as $path) {
                $result = $xml->xpath($path);
                if (! empty($result) && ! empty((string) $result[0])) {
                    $nroViaje = trim((string) $result[0]);
                    $this->logOperation('info', '✅ nroViaje extraído con XPath', [
                        'nroViaje' => $nroViaje,
                        'xpath' => $path,
                    ]);

                    return $nroViaje;
                }
            }

            // FALLBACK: Buscar en MessageHeaderDocument > ID con XPath
            $idPaths = [
                '//MessageHeaderDocument/ID',
                '//*[local-name()="MessageHeaderDocument"]/*[local-name()="ID"]',
            ];
            foreach ($idPaths as $path) {
                $result = $xml->xpath($path);
                if (!empty($result) && !empty((string) $result[0])) {
                    $nroViaje = trim((string) $result[0]);
                    $this->logOperation('info', '✅ nroViaje extraído de MessageHeaderDocument/ID via XPath', [
                        'nroViaje' => $nroViaje,
                    ]);
                    return $nroViaje;
                }
            }

            $this->logOperation('warning', '❌ No se encontró nroViaje en el XML', [
                'paths_tried' => array_merge($paths, $idPaths),
            ]);

            return null;

        } catch (Exception $e) {
            $this->logOperation('error', 'Error crítico parseando XML', [
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
            ]);

            return null;
        } finally {
            libxml_use_internal_errors(false);
        }
    }


    protected function createSoapClient(): SoapClient
    {
        if ($this->soapClient) {
            return $this->soapClient;
        }

        $wsdlUrl = $this->getWsdlUrl();
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
            ],
        ]);

        // Leer certificados Paraguay (estructura de archivos separados)
        $certificate = $this->company->getCertificate('paraguay');

        if (!$certificate) {
            throw new Exception('Certificado Paraguay no configurado.');
        }

        // Determinar rutas según estructura
        if (isset($certificate['cert_path']) && isset($certificate['key_path'])) {
            // Nueva estructura: archivos separados
            $certPath = storage_path('app/private/' . $certificate['cert_path']);
            $keyPath = storage_path('app/private/' . $certificate['key_path']);
        } elseif (isset($certificate['path'])) {
            // Legacy: archivo combinado
            $certPath = storage_path('app/private/' . $certificate['path']);
            $keyPath = $certPath; // Mismo archivo contiene ambos
        } else {
            throw new Exception('Estructura de certificado Paraguay inválida.');
        }

        if (!file_exists($certPath)) {
            throw new Exception("Archivo de certificado no existe: {$certPath}");
        }
        if (!file_exists($keyPath)) {
            throw new Exception("Archivo de clave privada no existe: {$keyPath}");
        }

        $certContent = file_get_contents($certPath);
        $keyContent = file_get_contents($keyPath);

        $certData = [
            'cert' => $certContent,
            'pkey' => $keyContent,
        ];

        \Log::info('DEBUG certData Paraguay', [
            'certData' => 'LOADED',
            'has_cert' => !empty($certData['cert']),
            'has_pkey' => !empty($certData['pkey']),
            'cert_path' => $certPath,
            'key_path' => $keyPath,
        ]);

        if (empty($certData['cert']) || empty($certData['pkey'])) {
            throw new Exception('Certificado digital Paraguay inválido. Verifique los archivos .pem.');
        }

        $dnaCertificatePath = storage_path('app/private/DNA/gdsfws.pem');
        $dnaCertificatePath = $this->resolveCertificatePath($dnaCertificatePath);

        if (! $dnaCertificatePath || ! is_readable($dnaCertificatePath)) {
            throw new Exception('Certificado público de la DNA Paraguay no encontrado o sin permisos de lectura.');
        }

        $dnaCertificate = file_get_contents($dnaCertificatePath);
        if (! $dnaCertificate) {
            throw new Exception('No se pudo leer el certificado público de la DNA Paraguay.');
        }

        // Certificado del servidor DNA para encriptar
        $dnaCertPath = storage_path('app/private/DNA/gdsfws.pem');
        if (!file_exists($dnaCertPath)) {
            throw new \Exception("Certificado del servidor DNA no encontrado: {$dnaCertPath}");
        }
        $dnaCertificate = file_get_contents($dnaCertPath);

        $securityBuilder = new ParaguayWSSecurityBuilder(
            $certData['cert'],
            $certData['pkey'],
            $dnaCertificate
        );

        $securityBuilder = new ParaguayWSSecurityBuilder(
            $certData['cert'],
            $certData['pkey'],
            $dnaCertificate
        );

        $this->soapClient = new ParaguaySecureSoapClient($wsdlUrl, [
            'trace' => 1,
            'exceptions' => true,
            'soap_version' => SOAP_1_1,
            'stream_context' => stream_context_create([
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true,
                ]
            ])
        ], $securityBuilder, $certData['pkey']);

        return $this->soapClient;
    }

    private function resolveCertificatePath(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        if (is_readable($path)) {
            return $path;
        }

        $basePath = base_path($path);
        if (is_readable($basePath)) {
            return $basePath;
        }

        return null;
    }
    
    /**
     * Generar XML de respuesta XISP simulada
     */
    private function generateXispBypassXml(string $viaje): string
    {
        $timestamp = now()->format('Y-m-d\TH:i:s');

        return <<<XML
    <?xml version="1.0" encoding="UTF-8"?>
    <soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
        <soap:Body>
            <EnviarMensajeFluvialResponse xmlns="http://gdsf.aduana.gov.py/">
                <resultado>
                    <codigo>0</codigo>
                    <mensaje>Embarcación incluida correctamente (BYPASS)</mensaje>
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
     * Generar XML de respuesta XRSP simulada
     */
    private function generateXrspBypassXml(string $viaje): string
    {
        $timestamp = now()->format('Y-m-d\TH:i:s');

        return <<<XML
    <?xml version="1.0" encoding="UTF-8"?>
    <soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
        <soap:Body>
            <EnviarMensajeFluvialResponse xmlns="http://gdsf.aduana.gov.py/">
                <resultado>
                    <codigo>0</codigo>
                    <mensaje>Embarcación desvinculada correctamente (BYPASS)</mensaje>
                    <nroViaje>{$viaje}</nroViaje>
                    <estado>ACEPTADO</estado>
                    <fechaProceso>{$timestamp}</fechaProceso>
                </resultado>
            </EnviarMensajeFluvialResponse>
        </soap:Body>
    </soap:Envelope>
    XML;
    }
}