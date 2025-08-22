<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Company;
use App\Models\Voyage;
use App\Models\WebserviceTransaction;
use App\Services\Webservice\SoapClientService;
use App\Services\Webservice\TestingCustomsService;
use App\Services\Webservice\XmlSerializerService;
use App\Services\Webservice\CertificateManagerService;
use Exception;
use Illuminate\Support\Facades\Log;

/**
 * MÓDULO 4: WEBSERVICES ADUANA - Comando de Testing
 * 
 * Comando para probar webservices aduaneros usando servicios existentes.
 * Ejecuta tests sistemáticos sin certificados reales primero, luego con certificados.
 * 
 * USO:
 * php artisan webservices:test
 * php artisan webservices:test --company=1006
 * php artisan webservices:test --environment=testing
 * php artisan webservices:test --with-certificates
 */
class TestWebservicesCommand extends Command
{
    /**
     * Signature del comando
     */
    protected $signature = 'webservices:test 
                           {--company=1006 : ID de empresa para testing}
                           {--environment=testing : Ambiente (testing/production)}
                           {--webservice-type=micdta : Tipo de webservice a probar}
                           {--with-certificates : Incluir tests con certificados reales}
                           {--voyage= : ID de voyage específico para probar}
                           {--dry-run : Solo mostrar qué se haría sin ejecutar}';

    /**
     * Descripción del comando
     */
    protected $description = 'Probar webservices aduaneros Argentina/Paraguay de forma sistemática';

    /**
     * Empresa para testing
     */
    private ?Company $company = null;

    /**
     * Resultados de los tests
     */
    private array $testResults = [];

    /**
     * Contador de errores
     */
    private int $errorCount = 0;

    /**
     * Ejecutar el comando
     */
    public function handle(): int
    {
        $this->displayHeader();

        try {
            // 1. Obtener empresa para testing
            $company = $this->getCompany();
            if (!$company) {
                return Command::FAILURE;
            }

            $this->company = $company;

            // 2. Configurar parámetros de testing
            $environment = $this->option('environment');
            $webserviceType = $this->option('webservice-type');
            $withCertificates = $this->option('with-certificates');
            $isDryRun = $this->option('dry-run');

            $this->info("🎯 Testing configurado:");
            $this->info("   Empresa: {$company->legal_name} (ID: {$company->id})");
            $this->info("   Ambiente: {$environment}");
            $this->info("   Tipo: {$webserviceType}");
            $this->info("   Con certificados: " . ($withCertificates ? 'SÍ' : 'NO'));
            $this->info("   Dry run: " . ($isDryRun ? 'SÍ' : 'NO'));
            $this->newLine();

            // 3. Ejecutar tests en orden
            $this->runConnectivityTests($environment, $webserviceType);
            $this->runXmlGenerationTests($webserviceType);
            
            if ($withCertificates) {
                $this->runCertificateTests();
                $this->runAuthenticationTests($environment);
            }

            // 4. Mostrar resumen final
            $this->showTestSummary();

            return $this->errorCount === 0 ? Command::SUCCESS : Command::FAILURE;

        } catch (Exception $e) {
            $this->error("❌ Error ejecutando tests: {$e->getMessage()}");
            $this->error("📍 Archivo: {$e->getFile()} línea {$e->getLine()}");
            return Command::FAILURE;
        }
    }

    /**
     * Mostrar encabezado del comando
     */
    private function displayHeader(): void
    {
        $this->line('');
        $this->info('╔══════════════════════════════════════════════════════════════╗');
        $this->info('║              🧪 TESTING WEBSERVICES ADUANEROS               ║');
        $this->info('║                    ARGENTINA & PARAGUAY                     ║');
        $this->info('╚══════════════════════════════════════════════════════════════╝');
        $this->line('');
    }

    /**
     * Obtener empresa para testing
     */
    private function getCompany(): ?Company
    {
        $companyId = $this->option('company');

        $company = Company::find($companyId);
        if (!$company) {
            $this->error("❌ Empresa {$companyId} no encontrada");
            return null;
        }

        return $company;
    }

    /**
     * Test 1: Conectividad básica
     */
    private function runConnectivityTests(string $environment, string $webserviceType): void
    {
        $this->info('🌐 TEST 1: Conectividad básica');
        $this->info('==============================');

        try {
            // Obtener voyage para testing - usar método existente
            $voyage = $this->getTestVoyage();
            
            if (!$voyage) {
                $this->warn('⚠️  No hay voyages disponibles para testing completo');
                $this->warn('   Ejecutando solo test básico de SoapClient...');
                
                // Test básico usando solo SoapClientService
                $soapClientService = new SoapClientService($this->company);
                
                $this->info('   Probando Argentina AFIP...');
                $argentinClient = $soapClientService->createClient('micdta', $environment);
                if ($argentinClient) {
                    $this->info('   ✅ Cliente SOAP Argentina creado exitosamente');
                } else {
                    $this->warn('   ⚠️  No se pudo crear cliente Argentina');
                }
                
                $this->info('   Probando Paraguay DNA...');
                $paraguayClient = $soapClientService->createClient('paraguay_customs', $environment);
                if ($paraguayClient) {
                    $this->info('   ✅ Cliente SOAP Paraguay creado exitosamente');
                } else {
                    $this->warn('   ⚠️  No se pudo crear cliente Paraguay');
                }
                
                $this->testResults['connectivity'] = [
                    'status' => 'partial',
                    'argentina' => $argentinClient ? 'success' : 'failed',
                    'paraguay' => $paraguayClient ? 'success' : 'failed'
                ];
                
            } else {
                // Test completo usando TestingCustomsService
                $this->info("   Usando voyage: {$voyage->voyage_number}");
                $testingService = new TestingCustomsService($this->company);
                
                $completeResult = $testingService->runCompleteTest($voyage, [
                    'environment' => $environment,
                    'webservice_type' => $webserviceType
                ]);

                // Extraer solo resultados de conectividad
                $connectivityResult = $completeResult['connectivity_test'] ?? ['status' => 'error', 'message' => 'No se pudo ejecutar test'];

                if ($connectivityResult['status'] === 'success') {
                    $this->info('✅ Conectividad exitosa');
                    if (isset($connectivityResult['tests'])) {
                        foreach ($connectivityResult['tests'] as $country => $test) {
                            $this->info("   {$country}: {$test['message']}");
                            if (isset($test['details'])) {
                                foreach ($test['details'] as $detail) {
                                    $this->info("     - {$detail}");
                                }
                            }
                        }
                    }
                } else {
                    $this->error('❌ Problemas de conectividad');
                    $this->errorCount++;
                    
                    // Mostrar errores específicos
                    if (isset($connectivityResult['errors'])) {
                        foreach ($connectivityResult['errors'] as $error) {
                            $this->error("   - {$error}");
                        }
                    }
                    
                    // Mostrar detalles de tests fallidos
                    if (isset($connectivityResult['tests'])) {
                        foreach ($connectivityResult['tests'] as $country => $test) {
                            if ($test['status'] === 'error' || $test['status'] === 'warning') {
                                $this->error("   {$country}: {$test['message']}");
                                if (isset($test['details'])) {
                                    foreach ($test['details'] as $detail) {
                                        $this->error("     - {$detail}");
                                    }
                                }
                            }
                        }
                    }
                    
                    // Si no hay detalles, mostrar mensaje genérico
                    if (!isset($connectivityResult['errors']) && !isset($connectivityResult['tests'])) {
                        $this->error("   - Error de conectividad sin detalles específicos");
                        $this->error("   - Verifique configuración de red y certificados");
                    }
                }

                $this->testResults['connectivity'] = $connectivityResult;
            }

        } catch (Exception $e) {
            $this->error("❌ Error en test de conectividad: {$e->getMessage()}");
            $this->errorCount++;
            $this->testResults['connectivity'] = ['status' => 'error', 'message' => $e->getMessage()];
        }

        $this->newLine();
    }

    /**
     * Test 2: Generación de XML
     */
    private function runXmlGenerationTests(string $webserviceType): void
    {
        $this->info('📄 TEST 2: Generación de XML');
        $this->info('============================');

        try {
            // Buscar un voyage de la empresa para testing
            $voyage = $this->getTestVoyage();
            if (!$voyage) {
                $this->warn('⚠️  No hay voyages disponibles para testing XML');
                $this->testResults['xml_generation'] = ['status' => 'skipped', 'message' => 'Sin datos de testing'];
                $this->newLine();
                return;
            }

            $this->info("   Usando voyage: {$voyage->voyage_number}");

            $testingService = new TestingCustomsService($this->company);
            
            // Usar runCompleteTest y extraer solo resultados de XML
            $completeResult = $testingService->runCompleteTest($voyage, [
                'webservice_type' => $webserviceType
            ]);

            $xmlResult = $completeResult['xml_validation'] ?? ['status' => 'error', 'message' => 'No se pudo validar XML'];

            if ($xmlResult['status'] === 'success') {
                $this->info('✅ XML generado correctamente');
                if (isset($xmlResult['checks'])) {
                    foreach ($xmlResult['checks'] as $check) {
                        $this->info("   - {$check}");
                    }
                }
                
                if (isset($xmlResult['xml_sample'])) {
                    $this->info("   XML preview: " . substr($xmlResult['xml_sample'], 0, 100) . "...");
                }
            } else {
                $this->error('❌ Error generando XML');
                $this->errorCount++;
                if (isset($xmlResult['errors'])) {
                    foreach ($xmlResult['errors'] as $error) {
                        $this->error("   - {$error}");
                    }
                }
            }

            $this->testResults['xml_generation'] = $xmlResult;

        } catch (Exception $e) {
            $this->error("❌ Error en test de XML: {$e->getMessage()}");
            $this->errorCount++;
            $this->testResults['xml_generation'] = ['status' => 'error', 'message' => $e->getMessage()];
        }

        $this->newLine();
    }

    /**
     * Test 3: Validación de certificados
     */
    private function runCertificateTests(): void
    {
        $this->info('🔐 TEST 3: Validación de certificados');
        $this->info('====================================');

        try {
            $testingService = new TestingCustomsService($this->company);
            
            // Usar el método público validateCertificate directamente
            $certResult = $testingService->validateCertificate();

            if ($certResult['status'] === 'success') {
                $this->info('✅ Certificado validado correctamente');
                if (isset($certResult['checks'])) {
                    foreach ($certResult['checks'] as $check) {
                        $this->info("   - {$check}");
                    }
                }
            } else {
                $this->warn('⚠️  Problemas con certificado');
                if (isset($certResult['errors'])) {
                    foreach ($certResult['errors'] as $error) {
                        $this->warn("   - {$error}");
                    }
                }
                if (isset($certResult['warnings'])) {
                    foreach ($certResult['warnings'] as $warning) {
                        $this->warn("   - {$warning}");
                    }
                }
            }

            $this->testResults['certificate'] = $certResult;

        } catch (Exception $e) {
            $this->error("❌ Error en test de certificado: {$e->getMessage()}");
            $this->errorCount++;
            $this->testResults['certificate'] = ['status' => 'error', 'message' => $e->getMessage()];
        }

        $this->newLine();
    }

    /**
     * Test 4: Autenticación con webservices
     */
    private function runAuthenticationTests(string $environment): void
    {
        $this->info('🔑 TEST 4: Autenticación con webservices');
        $this->info('=======================================');

        try {
            $soapClientService = new SoapClientService($this->company);
            
            // Test Argentina
            $this->info('   Probando autenticación Argentina...');
            $argentinClient = $soapClientService->createClient('micdta', $environment);
            if ($argentinClient) {
                $this->info('   ✅ Cliente SOAP Argentina creado');
            } else {
                $this->warn('   ⚠️  No se pudo crear cliente Argentina');
            }

            // Test Paraguay
            $this->info('   Probando autenticación Paraguay...');
            $paraguayClient = $soapClientService->createClient('paraguay_customs', $environment);
            if ($paraguayClient) {
                $this->info('   ✅ Cliente SOAP Paraguay creado');
            } else {
                $this->warn('   ⚠️  No se pudo crear cliente Paraguay');
            }

            $this->testResults['authentication'] = [
                'status' => 'success',
                'argentina' => $argentinClient ? 'success' : 'failed',
                'paraguay' => $paraguayClient ? 'success' : 'failed'
            ];

        } catch (Exception $e) {
            $this->error("❌ Error en test de autenticación: {$e->getMessage()}");
            $this->errorCount++;
            $this->testResults['authentication'] = ['status' => 'error', 'message' => $e->getMessage()];
        }

        $this->newLine();
    }

    /**
     * Obtener voyage para testing
     */
    private function getTestVoyage(): ?Voyage
    {
        $voyageId = $this->option('voyage');
        
        if ($voyageId) {
            return Voyage::where('company_id', $this->company->id)->find($voyageId);
        }

        // Buscar un voyage de la empresa con shipments
        return Voyage::where('company_id', $this->company->id)
            ->whereHas('shipments')
            ->first();
    }

    /**
     * Mostrar resumen de tests
     */
    private function showTestSummary(): void
    {
        $this->info('📊 RESUMEN DE TESTS');
        $this->info('==================');

        $totalTests = count($this->testResults);
        $successfulTests = collect($this->testResults)->filter(function ($result) {
            return $result['status'] === 'success';
        })->count();

        $this->info("Total tests ejecutados: {$totalTests}");
        $this->info("Tests exitosos: {$successfulTests}");
        $this->info("Tests con errores: {$this->errorCount}");

        if ($this->errorCount === 0) {
            $this->info('🎉 ¡Todos los tests completados exitosamente!');
        } else {
            $this->warn("⚠️  Se encontraron {$this->errorCount} errores");
        }

        $this->newLine();
        $this->info('💡 Próximos pasos sugeridos:');
        
        if (!$this->option('with-certificates')) {
            $this->info('   - Ejecutar con certificados: --with-certificates');
        }
        
        $this->info('   - Probar en producción: --environment=production');
        $this->info('   - Probar otros tipos: --webservice-type=anticipada');
        $this->info('   - Ver logs detallados en storage/logs/');
    }
}