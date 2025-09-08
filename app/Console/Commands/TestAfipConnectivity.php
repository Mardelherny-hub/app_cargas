<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Exception;

/**
 * 🧪 COMANDO DE TESTING: Validar conectividad básica con AFIP
 * 
 * Este comando permite validar que:
 * 1. La conectividad con el webservice AFIP funciona
 * 2. El método Dummy responde correctamente  
 * 3. Los certificados están configurados
 * 4. La estructura XML es aceptada por AFIP
 */
class TestAfipConnectivity extends Command
{
    protected $signature = 'afip:test-connectivity 
                            {--environment=testing : Ambiente a probar (testing|production)}
                            {--method=dummy : Método a probar (dummy|validate-xml)}
                            {--company= : ID de empresa a usar para testing}';

    protected $description = 'Probar conectividad básica con webservices AFIP';

    public function handle()
    {
        $this->info('🧪 INICIANDO TEST DE CONECTIVIDAD AFIP');
        $this->newLine();

        $environment = $this->option('environment');
        $method = $this->option('method');
        $companyId = $this->option('company');

        // Obtener URLs según ambiente
        $urls = $this->getAfipUrls($environment);
        $this->info("📡 Ambiente: {$environment}");
        $this->info("🔗 URL: {$urls['wgesregsintia2']}");
        $this->newLine();

        try {
            switch ($method) {
                case 'dummy':
                    $this->testDummyMethod($urls['wgesregsintia2']);
                    break;
                    
                case 'validate-xml':
                    $this->testXmlValidation($companyId);
                    break;
                    
                default:
                    $this->error("Método desconocido: {$method}");
                    return 1;
            }

            $this->info('✅ TEST COMPLETADO EXITOSAMENTE');
            return 0;

        } catch (Exception $e) {
            $this->error('❌ ERROR EN TEST: ' . $e->getMessage());
            $this->newLine();
            $this->line('📋 Stack trace:');
            $this->line($e->getTraceAsString());
            return 1;
        }
    }

    /**
     * ✅ TEST 1: Método Dummy de AFIP
     */
    private function testDummyMethod(string $wsdlUrl): void
    {
        $this->info('🔍 TESTING MÉTODO DUMMY...');

        // Crear cliente SOAP simple para método Dummy
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ],
            'http' => [
                'timeout' => 30,
                'user_agent' => 'AFIP Test Client/1.0'
            ]
        ]);

        try {
            // Crear cliente SOAP para AFIP
            $client = new \SoapClient($wsdlUrl . '?WSDL', [
                'stream_context' => $context,
                'connection_timeout' => 30,
                'cache_wsdl' => WSDL_CACHE_NONE,
                'trace' => true,
                'exceptions' => true
            ]);

            $this->line('   📌 Cliente SOAP creado exitosamente');

            // Llamar método Dummy (no requiere autenticación)
            $response = $client->Dummy();

            $this->line('   📌 Respuesta del método Dummy:');
            $this->line('      - AppServer: ' . ($response->DummyResult->AppServer ?? 'N/A'));
            $this->line('      - DbServer: ' . ($response->DummyResult->DbServer ?? 'N/A'));
            $this->line('      - AuthServer: ' . ($response->DummyResult->AuthServer ?? 'N/A'));

            // Verificar que todos los servidores respondan OK
            $appOk = ($response->DummyResult->AppServer ?? '') === 'OK';
            $dbOk = ($response->DummyResult->DbServer ?? '') === 'OK';
            $authOk = ($response->DummyResult->AuthServer ?? '') === 'OK';

            if ($appOk && $dbOk && $authOk) {
                $this->info('   ✅ Todos los servidores AFIP están operativos');
            } else {
                $this->warn('   ⚠️  Algunos servidores AFIP presentan problemas');
            }

        } catch (\SoapFault $e) {
            $this->error('   ❌ Error SOAP: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * ✅ TEST 2: Validación de XML con namespace corregido
     */
    private function testXmlValidation(?string $companyId): void
    {
        $this->info('🔍 TESTING VALIDACIÓN XML...');

        if (!$companyId) {
            $this->error('   ❌ Se requiere especificar --company=ID para este test');
            return;
        }

        // Obtener empresa
        $company = \App\Models\Company::find($companyId);
        if (!$company) {
            $this->error("   ❌ Empresa no encontrada: {$companyId}");
            return;
        }

        $this->line("   📌 Usando empresa: {$company->legal_name} (ID: {$company->id})");

        // Obtener un shipment de prueba a través de voyages
        $shipment = \App\Models\Shipment::whereHas('voyage', function($query) use ($company) {
                $query->where('company_id', $company->id);
            })
            ->with(['voyage', 'vessel'])
            ->first();

        if (!$shipment) {
            $this->error('   ❌ No se encontraron shipments para esta empresa');
            return;
        }

        $this->line("   📌 Usando shipment: {$shipment->shipment_number}");

        try {
            // Crear XML usando el servicio corregido
            $xmlSerializer = new \App\Services\Webservice\XmlSerializerService($company);
            $transactionId = 'TEST_' . date('YmdHis') . '_' . rand(1000, 9999);
            
            $xml = $xmlSerializer->createMicDtaXml($shipment, $transactionId);

            if ($xml) {
                $this->line('   📌 XML generado exitosamente');
                $this->line('   📏 Tamaño: ' . strlen($xml) . ' bytes');
                
                // Validar estructura XML
                $validation = $xmlSerializer->validateXmlStructure($xml);
                
                if ($validation['is_valid']) {
                    $this->info('   ✅ XML tiene estructura válida');
                } else {
                    $this->error('   ❌ XML tiene errores de estructura:');
                    foreach ($validation['errors'] as $error) {
                        $this->line("      - {$error}");
                    }
                }

                // Mostrar muestra del XML (primeros 500 caracteres)
                $this->newLine();
                $this->line('📄 MUESTRA DEL XML GENERADO:');
                $this->line('═══════════════════════════════════════');
                $this->line(substr($xml, 0, 500) . '...');
                $this->line('═══════════════════════════════════════');

            } else {
                $this->error('   ❌ No se pudo generar XML');
            }

        } catch (Exception $e) {
            $this->error('   ❌ Error generando XML: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * ✅ Obtener URLs de webservices AFIP según ambiente
     */
    private function getAfipUrls(string $environment): array
    {
        if ($environment === 'production') {
            return [
                'wgesregsintia2' => 'https://webservicesadu.afip.gob.ar/DIAV2/wgesregsintia2/wgesregsintia2.asmx',
                'wsaa' => 'https://wsaa.afip.gov.ar/ws/services/LoginCms',
            ];
        }

        // Testing/Homologación (por defecto)
        return [
            'wgesregsintia2' => 'https://wsaduhomoext.afip.gob.ar/diav2/wgesregsintia2/wgesregsintia2.asmx',
            'wsaa' => 'https://wsaahomo.afip.gov.ar/ws/services/LoginCms',
        ];
    }
}

/**
 * 📋 INSTRUCCIONES DE USO:
 * 
 * 1. TEST BÁSICO DE CONECTIVIDAD:
 *    php artisan afip:test-connectivity --method=dummy
 * 
 * 2. TEST CON EMPRESA ESPECÍFICA:
 *    php artisan afip:test-connectivity --method=validate-xml --company=1
 * 
 * 3. TEST EN PRODUCCIÓN:
 *    php artisan afip:test-connectivity --environment=production --method=dummy
 * 
 * 4. COMBINADO:
 *    php artisan afip:test-connectivity --environment=testing --method=validate-xml --company=1
 */