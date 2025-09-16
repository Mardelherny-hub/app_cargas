<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Company;
use App\Services\Webservice\CertificateManagerService;
use SoapClient;
use Exception;
use Illuminate\Support\Facades\Storage;

/**
 * COMANDO PARA DEBUGGEAR PROBLEMA WSAA - SimpleXmlGenerator
 * 
 * Diagnostica paso a paso el proceso de autenticación WSAA para encontrar
 * dónde exactamente falla el método getWSAATokens().
 * 
 * PROBLEMA IDENTIFICADO:
 * - Error AFIP 7008 "token invalido"
 * - getWSAATokens() falla silenciosamente y usa tokens mock
 * - CertificateManagerService funciona correctamente
 * - signLoginTicket() o callWSAA() fallan sin mostrar error real
 * 
 * USO:
 * php artisan wsaa:debug
 * php artisan wsaa:debug --company=1005
 * php artisan wsaa:debug --step=cert  (solo certificado)
 * php artisan wsaa:debug --step=ticket (solo loginTicket)
 * php artisan wsaa:debug --step=sign   (solo firma)
 * php artisan wsaa:debug --step=wsaa   (solo llamada WSAA)
 */
class DebugWSAACommand extends Command
{
    /**
     * Signature del comando
     */
    protected $signature = 'wsaa:debug 
                           {--company=1005 : ID de empresa para testing (MAERSK por defecto)}
                           {--step= : Ejecutar solo un paso específico (cert|ticket|sign|wsaa)}
                           {--save-files : Guardar archivos temporales para análisis}';

    /**
     * Descripción del comando
     */
    protected $description = 'Debuggear paso a paso el proceso de autenticación WSAA para encontrar el problema exacto';

    private Company $company;
    private CertificateManagerService $certificateManager;
    private array $debugResults = [];

    /**
     * Ejecutar el comando
     */
    public function handle(): int
    {
        $this->displayHeader();

        try {
            // 1. Obtener empresa de testing
            if (!$this->getCompany()) {
                return Command::FAILURE;
            }

            // 2. Inicializar servicios
            $this->initializeServices();

            // 3. Ejecutar debugging según paso solicitado
            $step = $this->option('step');
            
            if (!$step) {
                // Ejecutar todos los pasos
                $this->debugAllSteps();
            } else {
                // Ejecutar solo el paso solicitado
                $this->debugSpecificStep($step);
            }

            // 4. Mostrar resumen final
            $this->showFinalSummary();

            return Command::SUCCESS;

        } catch (Exception $e) {
            $this->error("❌ Error crítico durante debugging: " . $e->getMessage());
            $this->error("Archivo: " . $e->getFile());
            $this->error("Línea: " . $e->getLine());
            return Command::FAILURE;
        }
    }

    /**
     * Mostrar header del comando
     */
    private function displayHeader(): void
    {
        $this->info('🔧 DEBUG WSAA - Diagnóstico Completo');
        $this->info('==================================');
        $this->info('Empresa: MAERSK (ID: 1005, CUIT: 20170980418)');
        $this->info('Problema: Error AFIP 7008 "token invalido"');
        $this->info('Objetivo: Encontrar punto exacto de falla en getWSAATokens()');
        $this->newLine();
    }

    /**
     * Obtener empresa para testing
     */
    private function getCompany(): bool
    {
        $companyId = $this->option('company');
        $this->company = Company::find($companyId);

        if (!$this->company) {
            $this->error("❌ Empresa {$companyId} no encontrada");
            return false;
        }

        $this->info("✅ Empresa encontrada: {$this->company->legal_name}");
        $this->info("   CUIT: {$this->company->tax_id}");
        $this->newLine();

        return true;
    }

    /**
     * Inicializar servicios necesarios
     */
    private function initializeServices(): void
    {
        $this->certificateManager = new CertificateManagerService($this->company);
        $this->info("✅ CertificateManagerService inicializado");
    }

    /**
     * Debug de todos los pasos
     */
    private function debugAllSteps(): void
    {
        $this->info("🔍 EJECUTANDO DEBUGGING COMPLETO DE WSAA");
        $this->info("=======================================");
        $this->newLine();

        $this->debugStep1_Certificate();
        $this->debugStep2_LoginTicket();
        $this->debugStep3_SignTicket();
        $this->debugStep4_CallWSAA();
    }

    /**
     * Debug de paso específico
     */
    private function debugSpecificStep(string $step): void
    {
        $this->info("🔍 EJECUTANDO DEBUGGING ESPECÍFICO: " . strtoupper($step));
        $this->info("=====================================");
        $this->newLine();

        switch ($step) {
            case 'cert':
                $this->debugStep1_Certificate();
                break;
            case 'ticket':
                $this->debugStep2_LoginTicket();
                break;
            case 'sign':
                $this->debugStep3_SignTicket();
                break;
            case 'wsaa':
                $this->debugStep4_CallWSAA();
                break;
            default:
                $this->error("❌ Paso inválido: {$step}");
                $this->info("Pasos válidos: cert, ticket, sign, wsaa");
        }
    }

    /**
     * PASO 1: Debug del certificado
     */
    private function debugStep1_Certificate(): void
    {
        $this->info("1️⃣ DEBUGGING CERTIFICADO");
        $this->info("========================");

        try {
            // Verificar configuración del certificado
            $this->info("📋 Configuración del certificado:");
            $this->info("   - Ruta: " . ($this->company->certificate_path ?? 'NO CONFIGURADO'));
            $this->info("   - Tiene contraseña: " . ($this->company->certificate_password ? 'SI' : 'NO'));
            $this->info("   - Alias: " . ($this->company->certificate_alias ?? 'NO CONFIGURADO'));
            
            if ($this->company->certificate_expires_at) {
                $this->info("   - Expira: " . $this->company->certificate_expires_at->format('d/m/Y H:i:s'));
            }

            // Verificar que el archivo existe
            if ($this->company->certificate_path) {
                $exists = Storage::exists($this->company->certificate_path);
                $this->info("   - Archivo existe: " . ($exists ? 'SI' : 'NO'));
                
                if ($exists) {
                    $size = Storage::size($this->company->certificate_path);
                    $this->info("   - Tamaño: " . number_format($size) . " bytes");
                }
            }

            // Intentar leer certificado usando CertificateManagerService
            $this->info("");
            $this->info("🔑 Intentando leer certificado...");
            $certData = $this->certificateManager->readCertificate();

            if ($certData) {
                $this->info("✅ Certificado leído exitosamente");
                $this->info("   - Tiene cert: " . (isset($certData['cert']) ? 'SI' : 'NO'));
                $this->info("   - Tiene pkey: " . (isset($certData['pkey']) ? 'SI' : 'NO'));
                $this->info("   - Tiene extracerts: " . (isset($certData['extracerts']) ? 'SI (' . count($certData['extracerts']) . ')' : 'NO'));

                // Validar certificado
                $validation = $this->certificateManager->validateCompanyCertificate();
                $this->info("   - Validación: " . ($validation['is_valid'] ? 'VÁLIDO' : 'INVÁLIDO'));
                
                if (!$validation['is_valid']) {
                    foreach ($validation['errors'] as $error) {
                        $this->warn("     * Error: " . $error);
                    }
                }

                $this->debugResults['certificate'] = [
                    'status' => 'success',
                    'data' => $certData,
                    'validation' => $validation
                ];

            } else {
                $this->error("❌ NO se pudo leer el certificado");
                $this->debugResults['certificate'] = [
                    'status' => 'error',
                    'message' => 'No se pudo leer el certificado'
                ];
            }

        } catch (Exception $e) {
            $this->error("❌ Error en debugging del certificado:");
            $this->error("   Mensaje: " . $e->getMessage());
            $this->error("   Archivo: " . $e->getFile());
            $this->error("   Línea: " . $e->getLine());
            
            $this->debugResults['certificate'] = [
                'status' => 'error',
                'exception' => $e->getMessage()
            ];
        }

        $this->newLine();
    }

    /**
     * PASO 2: Debug del LoginTicket
     */
    private function debugStep2_LoginTicket(): void
    {
        $this->info("2️⃣ DEBUGGING LOGIN TICKET");
        $this->info("=========================");

        try {
            $uniqueId = uniqid();
            $generationTime = date('c');
            $expirationTime = date('c', strtotime('+2 hours'));
            
            $this->info("📋 Parámetros del LoginTicket:");
            $this->info("   - UniqueId: " . $uniqueId);
            $this->info("   - GenerationTime: " . $generationTime);
            $this->info("   - ExpirationTime: " . $expirationTime);
            $this->info("   - Service: wgesregsintia2");

            $loginTicket = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
<loginTicketRequest version=\"1.0\">
    <header>
        <uniqueId>{$uniqueId}</uniqueId>
        <generationTime>{$generationTime}</generationTime>
        <expirationTime>{$expirationTime}</expirationTime>
    </header>
    <service>wgesregsintia2</service>
</loginTicketRequest>";

            $this->info("");
            $this->info("✅ LoginTicket generado exitosamente");
            $this->info("   - Longitud: " . strlen($loginTicket) . " caracteres");
            
            if ($this->option('verbose')) {
                $this->info("   - Contenido:");
                $this->line($loginTicket);
            }

            // Validar XML
            $dom = new \DOMDocument();
            $xmlValid = @$dom->loadXML($loginTicket);
            $this->info("   - XML válido: " . ($xmlValid ? 'SI' : 'NO'));

            // Guardar archivo si se solicita
            if ($this->option('save-files')) {
                $tempPath = 'temp/debug_loginticket_' . time() . '.xml';
                Storage::put($tempPath, $loginTicket);
                $this->info("   - Guardado en: " . $tempPath);
            }

            $this->debugResults['loginticket'] = [
                'status' => 'success',
                'content' => $loginTicket,
                'length' => strlen($loginTicket),
                'xml_valid' => $xmlValid
            ];

        } catch (Exception $e) {
            $this->error("❌ Error generando LoginTicket:");
            $this->error("   Mensaje: " . $e->getMessage());
            
            $this->debugResults['loginticket'] = [
                'status' => 'error',
                'exception' => $e->getMessage()
            ];
        }

        $this->newLine();
    }

    /**
     * PASO 3: Debug de la firma del ticket
     */
   private function debugStep3_SignTicket(): void
{
    $this->info("3️⃣ DEBUGGING FIRMA DEL TICKET - MÉTODO AFIP ESTÁNDAR");
    $this->info("==================================================");

    try {
        // Verificar que tengamos certificado y loginticket
        if (!isset($this->debugResults['certificate']) || $this->debugResults['certificate']['status'] !== 'success') {
            $this->debugStep1_Certificate();
        }
        if (!isset($this->debugResults['loginticket']) || $this->debugResults['loginticket']['status'] !== 'success') {
            $this->debugStep2_LoginTicket();
        }

        $certData = $this->debugResults['certificate']['data'];
        $loginTicket = $this->debugResults['loginticket']['content'];

        $this->info("🔐 Usando método AFIP estándar para CMS...");

        // 1. Crear archivo temporal con LoginTicket
        $loginTicketFile = tempnam(sys_get_temp_dir(), 'loginticket_') . '.xml';
        file_put_contents($loginTicketFile, $loginTicket);
        $this->info("   - LoginTicket guardado: " . basename($loginTicketFile));

        // 2. Crear archivo de certificado temporal
        $certFile = tempnam(sys_get_temp_dir(), 'cert_') . '.pem';
        $certContent = $certData['cert'];

        // Incluir certificados intermedios si existen
        if (isset($certData['extracerts']) && is_array($certData['extracerts'])) {
            $this->info("   - Agregando " . count($certData['extracerts']) . " certificados intermedios");
            foreach ($certData['extracerts'] as $extraCert) {
                $certContent .= "\n" . $extraCert;
            }
        } else {
            $this->info("   - Sin certificados intermedios encontrados");
        }

        file_put_contents($certFile, $certContent);
        $this->info("   - Certificado PEM guardado: " . basename($certFile));

        // Analizar información del certificado
        $this->info("   - Analizando cadena de certificados...");
        $certInfo = openssl_x509_parse($certData['cert']);
        if ($certInfo) {
            $this->info("     * Subject: " . ($certInfo['subject']['CN'] ?? 'N/A'));
            $this->info("     * Issuer: " . ($certInfo['issuer']['CN'] ?? 'N/A'));
            $this->info("     * Valid from: " . date('Y-m-d', $certInfo['validFrom_time_t']));
            $this->info("     * Valid to: " . date('Y-m-d', $certInfo['validTo_time_t']));
        }

        // 3. Crear archivo de clave privada temporal
        $keyFile = tempnam(sys_get_temp_dir(), 'key_') . '.pem';
        file_put_contents($keyFile, $certData['pkey']);
        $this->info("   - Clave privada guardada: " . basename($keyFile));

        // 4. Usar OpenSSL externo (método más confiable para AFIP)
        $outputFile = tempnam(sys_get_temp_dir(), 'signed_') . '.p7s';
        
        $command = sprintf(
            'openssl smime -sign -in %s -out %s -signer %s -inkey %s -outform DER -nodetach 2>&1',
            escapeshellarg($loginTicketFile),
            escapeshellarg($outputFile),
            escapeshellarg($certFile),
            escapeshellarg($keyFile)
        );

        $this->info("   - Ejecutando comando OpenSSL...");
        if ($this->option('verbose')) {
            $this->info("   - Comando: " . $command);
        }

        exec($command, $output, $returnCode);

        if ($returnCode === 0 && file_exists($outputFile)) {
            $signature = file_get_contents($outputFile);
            $signatureBase64 = base64_encode($signature);
            
            $this->info("✅ Firma CMS generada exitosamente");
            $this->info("   - Archivo firmado: " . filesize($outputFile) . " bytes");
            $this->info("   - Base64 longitud: " . strlen($signatureBase64) . " caracteres");

            $this->debugResults['signature'] = [
                'status' => 'success',
                'content' => $signature,
                'content_base64' => $signatureBase64,
                'length' => strlen($signature),
                'length_base64' => strlen($signatureBase64)
            ];

        } else {
            $this->error("❌ Error con comando OpenSSL:");
            foreach ($output as $line) {
                $this->error("   " . $line);
            }
            
            // Fallback a método PHP con diferentes flags
            $this->info("   - Intentando fallback con openssl_pkcs7_sign...");
            $this->attemptPhpSigning($loginTicketFile, $certData);
        }

        // Limpiar archivos temporales
        @unlink($loginTicketFile);
        @unlink($certFile);
        @unlink($keyFile);
        @unlink($outputFile);

    } catch (Exception $e) {
        $this->error("❌ Error en debugging de firma:");
        $this->error("   Mensaje: " . $e->getMessage());
        $this->debugResults['signature'] = [
            'status' => 'error',
            'exception' => $e->getMessage()
        ];
    }

    $this->newLine();
}

private function attemptPhpSigning($loginTicketFile, $certData): void
{
    $flags = [
        PKCS7_BINARY | PKCS7_NOATTR,
        PKCS7_NOATTR,
        PKCS7_BINARY,
        0
    ];

    foreach ($flags as $index => $flag) {
        $this->info("   - Probando flags: " . $flag);
        
        $outputFile = $loginTicketFile . "_signed_$index.p7s";
        $result = openssl_pkcs7_sign(
            $loginTicketFile,
            $outputFile,
            $certData['cert'],
            $certData['pkey'],
            [],
            $flag
        );

        if ($result && file_exists($outputFile)) {
            $signature = file_get_contents($outputFile);
            $signatureBase64 = base64_encode($signature);
            
            $this->info("✅ Firma exitosa con flags: " . $flag);
            $this->debugResults['signature'] = [
                'status' => 'success',
                'content' => $signature,
                'content_base64' => $signatureBase64,
                'length' => strlen($signature),
                'length_base64' => strlen($signatureBase64),
                'flags_used' => $flag
            ];
            
            @unlink($outputFile);
            return;
        }
    }

    $this->error("❌ Todos los métodos de firma fallaron");
    $this->debugResults['signature'] = [
        'status' => 'error',
        'message' => 'Todos los métodos de firma fallaron'
    ];
}

    /**
     * PASO 4: Debug de la llamada WSAA
     */
    private function debugStep4_CallWSAA(): void
    {
        $this->info("4️⃣ DEBUGGING LLAMADA WSAA");
        $this->info("=========================");

        try {
            // Verificar que tengamos firma
            if (!isset($this->debugResults['signature']) || $this->debugResults['signature']['status'] !== 'success') {
                $this->warn("⚠️ Ejecutando firma de ticket primero...");
                $this->debugStep3_SignTicket();
                
                if (!isset($this->debugResults['signature']) || $this->debugResults['signature']['status'] !== 'success') {
                    $this->error("❌ No se puede continuar sin firma válida");
                    return;
                }
            }

            $signedTicket = isset($this->debugResults['signature']['content_base64']) 
                ? $this->debugResults['signature']['content_base64'] 
                : base64_encode($this->debugResults['signature']['content']);
            $wsdlUrl = 'https://wsaahomo.afip.gov.ar/ws/services/LoginCms?wsdl';

            $this->info("🌐 Configurando cliente SOAP para WSAA...");
            $this->info("   - WSDL URL: " . $wsdlUrl);
            $this->info("   - Usando firma Base64 (" . strlen($signedTicket) . " caracteres)");

            // Crear contexto SSL
            $context = stream_context_create([
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                ]
            ]);

            // Crear cliente SOAP
            $this->info("   - Creando SoapClient...");
            $client = new SoapClient($wsdlUrl, [
                'trace' => true,
                'exceptions' => true,
                'stream_context' => $context,
                'connection_timeout' => 30,
                'cache_wsdl' => WSDL_CACHE_NONE // Forzar carga fresca del WSDL
            ]);

            $this->info("✅ SoapClient creado exitosamente");

            // Obtener funciones disponibles
            try {
                $functions = $client->__getFunctions();
                $this->info("   - Funciones WSDL disponibles: " . count($functions));
                
                if ($this->option('verbose')) {
                    foreach ($functions as $function) {
                        $this->info("     * " . $function);
                    }
                }
            } catch (Exception $e) {
                $this->warn("   - No se pudieron obtener funciones WSDL: " . $e->getMessage());
            }

            // Intentar llamada a loginCms
            $this->info("");
            $this->info("🚀 Ejecutando loginCms...");
            $this->info("   - Parámetro: signedTicket (" . strlen($signedTicket) . " caracteres)");

            $response = $client->loginCms(['in0' => $signedTicket]);

            $this->info("✅ Respuesta recibida de WSAA");
            
            // Analizar respuesta
            if (isset($response->loginCmsReturn)) {
                $responseXml = $response->loginCmsReturn;
                $this->info("   - Response XML longitud: " . strlen($responseXml) . " caracteres");

                // Intentar parsear XML
                $xml = simplexml_load_string($responseXml);
                if ($xml) {
                    $this->info("✅ Response XML parseado exitosamente");
                    
                    // Buscar token y sign
                    $token = (string)($xml->credentials->token ?? '');
                    $sign = (string)($xml->credentials->sign ?? '');
                    
                    if ($token && $sign) {
                        $this->info("🎯 TOKENS WSAA OBTENIDOS EXITOSAMENTE:");
                        $this->info("   - Token longitud: " . strlen($token) . " caracteres");
                        $this->info("   - Sign longitud: " . strlen($sign) . " caracteres");
                        
                        if ($this->option('verbose')) {
                            $this->info("   - Token: " . substr($token, 0, 50) . "...");
                            $this->info("   - Sign: " . substr($sign, 0, 50) . "...");
                        }

                        $this->debugResults['wsaa'] = [
                            'status' => 'success',
                            'token' => $token,
                            'sign' => $sign,
                            'full_response' => $responseXml
                        ];

                    } else {
                        $this->error("❌ No se encontraron token/sign en la respuesta");
                        
                        if ($this->option('verbose')) {
                            $this->info("Response XML completo:");
                            $this->line($responseXml);
                        }

                        $this->debugResults['wsaa'] = [
                            'status' => 'error',
                            'message' => 'Token/sign no encontrados en respuesta',
                            'response' => $responseXml
                        ];
                    }
                } else {
                    $this->error("❌ No se pudo parsear XML de respuesta");
                    $this->debugResults['wsaa'] = [
                        'status' => 'error',
                        'message' => 'XML de respuesta inválido',
                        'response' => $responseXml
                    ];
                }
            } else {
                $this->error("❌ No se recibió loginCmsReturn en la respuesta");
                
                if ($this->option('verbose')) {
                    $this->info("Respuesta completa:");
                    var_dump($response);
                }

                $this->debugResults['wsaa'] = [
                    'status' => 'error',
                    'message' => 'loginCmsReturn no encontrado',
                    'response' => $response
                ];
            }

            // Obtener request/response del SOAP
            if ($this->option('verbose')) {
                $this->info("");
                $this->info("📨 Detalles del intercambio SOAP:");
                $this->info("Request Headers:");
                $this->line($client->__getLastRequestHeaders());
                $this->info("Request Body:");
                $this->line($client->__getLastRequest());
                $this->info("Response Headers:");
                $this->line($client->__getLastResponseHeaders());
                $this->info("Response Body:");
                $this->line($client->__getLastResponse());
            }

        } catch (SoapFault $e) {
            $this->error("❌ SOAP Fault en llamada WSAA:");
            $this->error("   Código: " . $e->faultcode);
            $this->error("   Mensaje: " . $e->faultstring);
            
            if ($this->option('verbose') && isset($client)) {
                $this->info("Last Request:");
                $this->line($client->__getLastRequest());
                $this->info("Last Response:");
                $this->line($client->__getLastResponse());
            }

            $this->debugResults['wsaa'] = [
                'status' => 'soap_fault',
                'fault_code' => $e->faultcode,
                'fault_string' => $e->faultstring
            ];

        } catch (Exception $e) {
            $this->error("❌ Error en llamada WSAA:");
            $this->error("   Mensaje: " . $e->getMessage());
            $this->error("   Archivo: " . $e->getFile());
            $this->error("   Línea: " . $e->getLine());
            
            $this->debugResults['wsaa'] = [
                'status' => 'error',
                'exception' => $e->getMessage()
            ];
        }

        $this->newLine();
    }

    /**
     * Mostrar resumen final del debugging
     */
    private function showFinalSummary(): void
    {
        $this->info("📊 RESUMEN FINAL DEL DEBUGGING");
        $this->info("==============================");
        $this->newLine();

        $totalSteps = count($this->debugResults);
        $successfulSteps = 0;

        foreach ($this->debugResults as $step => $result) {
            $status = $result['status'];
            $emoji = $status === 'success' ? '✅' : '❌';
            
            if ($status === 'success') {
                $successfulSteps++;
            }

            $this->info("{$emoji} " . strtoupper($step) . ": " . strtoupper($status));
            
            if ($status !== 'success' && isset($result['message'])) {
                $this->warn("   └─ " . $result['message']);
            }
            
            if ($status !== 'success' && isset($result['exception'])) {
                $this->warn("   └─ " . $result['exception']);
            }

            if ($status === 'soap_fault') {
                $this->warn("   └─ SOAP Fault: " . $result['fault_string']);
            }
        }

        $this->newLine();
        
        if ($successfulSteps === $totalSteps) {
            $this->info("🎉 TODOS LOS PASOS EXITOSOS - WSAA FUNCIONANDO CORRECTAMENTE");
            $this->info("El problema no está en la autenticación WSAA, revisar:");
            $this->info("- Formato del XML enviado a AFIP");
            $this->info("- Uso correcto de los tokens en las requests");
            $this->info("- Headers SOAP correctos");
        } else {
            $failures = $totalSteps - $successfulSteps;
            $this->error("⚠️ SE ENCONTRARON PROBLEMAS EN {$failures} DE {$totalSteps} PASOS");
            $this->info("");
            $this->info("🔧 PRÓXIMOS PASOS PARA SOLUCIONAR:");
            
            if (isset($this->debugResults['certificate']) && $this->debugResults['certificate']['status'] !== 'success') {
                $this->info("1. Verificar/corregir configuración del certificado");
            }
            
            if (isset($this->debugResults['signature']) && $this->debugResults['signature']['status'] !== 'success') {
                $this->info("2. Verificar proceso de firma con OpenSSL");
            }
            
            if (isset($this->debugResults['wsaa']) && $this->debugResults['wsaa']['status'] !== 'success') {
                $this->info("3. Revisar conectividad y configuración WSAA");
            }
        }

        $this->newLine();
        $this->info("💾 Para guardar archivos temporales usar: --save-files");
        $this->info("🔍 Para ver output detallado usar: --verbose");
    }
}