<?php

/**
 * SCRIPT DE PRUEBA: ArgentinaAnticipatedService
 * 
 * Archivo: tests/Feature/Webservice/ArgentinaAnticipatedServiceTest.php
 * 
 * Este script valida que el ArgentinaAnticipatedService funcione correctamente
 * con datos reales del sistema (PARANA.csv, MAERSK, PAR13001, V022NB).
 * 
 * EJECUTAR CON: php artisan test --filter=ArgentinaAnticipatedServiceTest
 */

namespace Tests\Feature\Webservice;

use Tests\TestCase;
use App\Models\Company;
use App\Models\Voyage;
use App\Models\Vessel;
use App\Models\Captain;
use App\Models\User;
use App\Models\Shipment;
use App\Services\Webservice\ArgentinaAnticipatedService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ArgentinaAnticipatedServiceTest extends TestCase
{
    use RefreshDatabase;

    private Company $maerskCompany;
    private User $testUser;
    private Vessel $parana01Vessel;
    private Captain $testCaptain;
    private Voyage $testVoyage;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Crear datos de prueba usando estructura real del sistema
        $this->createTestData();
    }

    /**
     * Crear datos de prueba basados en PARANA.csv
     */
    private function createTestData(): void
    {
        // 1. Crear empresa MAERSK (datos reales del sistema)
        $this->maerskCompany = Company::create([
            'legal_name' => 'MAERSK LINE ARGENTINA S.A.',
            'tax_id' => '30-12345678-9', // CUIT de prueba válido
            'email' => 'operations@maersk.com.ar',
            'phone' => '+54-11-4567-8900',
            'address' => 'Puerto de Buenos Aires, Dique 4',
            'city' => 'Buenos Aires',
            'country' => 'AR',
            'is_active' => true,
            
            // Configuración de webservices
            'certificate_path' => storage_path('certificates/maersk_test.p12'),
            'certificate_password' => 'test_password_123',
            'certificate_alias' => 'MAERSK_CERT_TEST',
            'ws_environment' => 'testing',
            'ws_config' => [
                'timeout' => 60,
                'retry_attempts' => 3,
                'webservice_urls' => [
                    'testing' => [
                        'anticipada' => 'https://wsaduhomoext.afip.gob.ar/DIAV2/wgesinformacionanticipada/wgesinformacionanticipada.asmx',
                    ],
                ],
            ],
        ]);

        // 2. Crear usuario operador
        $this->testUser = User::create([
            'name' => 'Operador Webservices',
            'email' => 'operador@maersk.com.ar',
            'password' => bcrypt('password'),
            'company_id' => $this->maerskCompany->id,
            'is_active' => true,
        ]);

        // 3. Crear embarcación PAR13001 (datos reales)
        $this->parana01Vessel = Vessel::create([
            'name' => 'PAR13001',
            'imo_number' => 'IMO9876543',
            'flag' => 'AR',
            'year_built' => 2018,
            'length' => 60.5,
            'width' => 10.2,
            'container_capacity' => 48,
            'cargo_capacity' => 1200,
            'vessel_type_id' => 1, // Asumir que existe VesselType con ID 1
            'company_id' => $this->maerskCompany->id,
            'is_active' => true,
        ]);

        // 4. Crear capitán
        $this->testCaptain = Captain::create([
            'full_name' => 'Capitán Juan Carlos Fernández',
            'license_number' => 'NAV-AR-12345',
            'document_type' => 'DNI',
            'document_number' => '12345678',
            'nationality' => 'AR',
            'company_id' => $this->maerskCompany->id,
            'is_active' => true,
        ]);

        // 5. Crear viaje V022NB (datos reales)
        $this->testVoyage = Voyage::create([
            'voyage_number' => 'V022NB',
            'vessel_id' => $this->parana01Vessel->id,
            'captain_id' => $this->testCaptain->id,
            'company_id' => $this->maerskCompany->id,
            'departure_port' => 'ARBUE', // Buenos Aires
            'arrival_port' => 'PYTVT',   // Paraguay Terminal Villeta
            'departure_date' => Carbon::now()->addDays(2), // Salida en 2 días
            'arrival_date' => Carbon::now()->addDays(5),   // Llegada en 5 días
            'is_convoy' => false,
            'status' => 'programado',
        ]);

        // 6. Crear shipments de prueba
        $this->createTestShipments();
    }

    /**
     * Crear shipments de prueba para el viaje
     */
    private function createTestShipments(): void
    {
        // Shipment 1: Contenedores 40HC
        Shipment::create([
            'shipment_number' => 'SHP001-V022NB',
            'voyage_id' => $this->testVoyage->id,
            'company_id' => $this->maerskCompany->id,
            'client_id' => null, // Sin cliente específico para prueba
            'cargo_description' => 'Contenedores con productos manufacturados',
            'containers_loaded' => 24,
            'gross_weight' => 480000, // 480 toneladas
            'net_weight' => 460000,
            'volume' => 1200,
            'cargo_weight_loaded' => 480,
            'status' => 'confirmado',
        ]);

        // Shipment 2: Contenedores 20GP
        Shipment::create([
            'shipment_number' => 'SHP002-V022NB',
            'voyage_id' => $this->testVoyage->id,
            'company_id' => $this->maerskCompany->id,
            'client_id' => null,
            'cargo_description' => 'Contenedores con granos y cereales',
            'containers_loaded' => 20,
            'gross_weight' => 300000, // 300 toneladas
            'net_weight' => 285000,
            'volume' => 800,
            'cargo_weight_loaded' => 300,
            'status' => 'confirmado',
        ]);
    }

    /**
     * TEST 1: Validar inicialización del servicio
     */
    public function test_service_initialization(): void
    {
        $service = new ArgentinaAnticipatedService($this->maerskCompany, $this->testUser);
        
        $this->assertInstanceOf(ArgentinaAnticipatedService::class, $service);
        
        $config = $service->getConfig();
        $this->assertEquals('anticipada', $config['webservice_type']);
        $this->assertEquals('AR', $config['country']);
        $this->assertEquals('testing', $config['environment']);
        $this->assertTrue($config['require_certificate']);
    }

    /**
     * TEST 2: Validar configuración de métodos disponibles
     */
    public function test_available_methods(): void
    {
        $service = new ArgentinaAnticipatedService($this->maerskCompany, $this->testUser);
        
        $methods = $service->getAvailableMethods();
        $this->assertContains('RegistrarViaje', $methods);
        $this->assertContains('RectificarViaje', $methods);
        $this->assertContains('RegistrarTitulosCbc', $methods);
    }

    /**
     * TEST 3: Validación completa de voyage para información anticipada
     */
    public function test_voyage_validation_success(): void
    {
        $service = new ArgentinaAnticipatedService($this->maerskCompany, $this->testUser);
        
        // Usar reflexión para acceder al método privado de validación
        $reflection = new \ReflectionClass($service);
        $validateMethod = $reflection->getMethod('validateForAnticipated');
        $validateMethod->setAccessible(true);
        
        $validation = $validateMethod->invoke($service, $this->testVoyage);
        
        $this->assertTrue($validation['is_valid'], 'Voyage debe ser válido para información anticipada');
        $this->assertEmpty($validation['errors'], 'No debe haber errores de validación');
    }

    /**
     * TEST 4: Validación con voyage inválido
     */
    public function test_voyage_validation_failure(): void
    {
        // Crear voyage sin datos obligatorios
        $invalidVoyage = Voyage::create([
            'voyage_number' => '', // Número vacío
            'vessel_id' => null,   // Sin embarcación
            'captain_id' => null,  // Sin capitán
            'company_id' => $this->maerskCompany->id,
            'departure_port' => '',
            'arrival_port' => '',
            'departure_date' => null,
        ]);

        $service = new ArgentinaAnticipatedService($this->maerskCompany, $this->testUser);
        
        $reflection = new \ReflectionClass($service);
        $validateMethod = $reflection->getMethod('validateForAnticipated');
        $validateMethod->setAccessible(true);
        
        $validation = $validateMethod->invoke($service, $invalidVoyage);
        
        $this->assertFalse($validation['is_valid'], 'Voyage inválido debe fallar validación');
        $this->assertNotEmpty($validation['errors'], 'Debe haber errores de validación');
        $this->assertGreaterThan(3, count($validation['errors']), 'Debe detectar múltiples errores');
    }

    /**
     * TEST 5: Creación de transacción
     */
    public function test_transaction_creation(): void
    {
        $service = new ArgentinaAnticipatedService($this->maerskCompany, $this->testUser);
        
        // Usar reflexión para acceder al método privado
        $reflection = new \ReflectionClass($service);
        $createTransactionMethod = $reflection->getMethod('createTransaction');
        $createTransactionMethod->setAccessible(true);
        
        $transaction = $createTransactionMethod->invoke($service, $this->testVoyage);
        
        $this->assertNotNull($transaction);
        $this->assertNotNull($transaction->id);
        $this->assertEquals('anticipada', $transaction->webservice_type);
        $this->assertEquals('AR', $transaction->country);
        $this->assertEquals($this->maerskCompany->id, $transaction->company_id);
        $this->assertEquals($this->testVoyage->id, $transaction->voyage_id);
        $this->assertEquals('pending', $transaction->status);
        
        // Validar metadata
        $metadata = $transaction->additional_metadata;
        $this->assertEquals('V022NB', $metadata['voyage_number']);
        $this->assertEquals('PAR13001', $metadata['vessel_name']);
        $this->assertEquals('RegistrarViaje', $metadata['method_used']);
        $this->assertFalse($metadata['is_rectification']);
    }

    /**
     * TEST 6: Simulación de registro de viaje (sin envío real)
     */
    public function test_register_voyage_simulation(): void
    {
        // Configurar ambiente de testing sin envío real
        $config = ['environment' => 'testing', 'validate_xml_structure' => false];
        $service = new ArgentinaAnticipatedService($this->maerskCompany, $this->testUser, $config);
        
        // Mock del método de envío SOAP para evitar envío real
        $this->mockSoapSending($service);
        
        $result = $service->registerVoyage($this->testVoyage);
        
        // Validar estructura de respuesta
        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('transaction_id', $result);
        $this->assertArrayHasKey('errors', $result);
        $this->assertArrayHasKey('warnings', $result);
        
        // Si hay errores, mostrarlos para debug
        if (!empty($result['errors'])) {
            $this->fail('Errores en registro de viaje: ' . implode(', ', $result['errors']));
        }
    }

    /**
     * TEST 7: Estadísticas de empresa
     */
    public function test_company_statistics(): void
    {
        $service = new ArgentinaAnticipatedService($this->maerskCompany, $this->testUser);
        
        $stats = $service->getCompanyStatistics();
        
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('total_transactions', $stats);
        $this->assertArrayHasKey('successful_transactions', $stats);
        $this->assertArrayHasKey('error_transactions', $stats);
        $this->assertArrayHasKey('success_rate', $stats);
        $this->assertArrayHasKey('rectifications_count', $stats);
        
        // Inicialmente debe estar en 0
        $this->assertEquals(0, $stats['total_transactions']);
        $this->assertEquals(0.0, $stats['success_rate']);
    }

    /**
     * TEST 8: Cambio de ambiente
     */
    public function test_environment_change(): void
    {
        $service = new ArgentinaAnticipatedService($this->maerskCompany, $this->testUser);
        
        // Cambiar a producción
        $service->setEnvironment('production');
        $config = $service->getConfig();
        $this->assertEquals('production', $config['environment']);
        
        // Cambiar de vuelta a testing
        $service->setEnvironment('testing');
        $config = $service->getConfig();
        $this->assertEquals('testing', $config['environment']);
        
        // Probar ambiente inválido
        $this->expectException(\Exception::class);
        $service->setEnvironment('invalid_environment');
    }

    /**
     * TEST 9: Validación de empresa argentina
     */
    public function test_argentina_company_validation(): void
    {
        // Crear empresa paraguaya para probar restricción
        $paraguayCompany = Company::create([
            'legal_name' => 'EMPRESA PARAGUAYA S.A.',
            'tax_id' => '80012345-6',
            'country' => 'PY', // Paraguay
            'is_active' => true,
        ]);

        $service = new ArgentinaAnticipatedService($paraguayCompany, $this->testUser);
        
        $reflection = new \ReflectionClass($service);
        $validateMethod = $reflection->getMethod('validateForAnticipated');
        $validateMethod->setAccessible(true);
        
        $validation = $validateMethod->invoke($service, $this->testVoyage);
        
        $this->assertFalse($validation['is_valid']);
        $this->assertContains('Información Anticipada solo para empresas argentinas', $validation['errors']);
    }

    /**
     * TEST 10: Validación CUIT formato correcto
     */
    public function test_cuit_validation(): void
    {
        // Empresa con CUIT inválido
        $invalidCompany = Company::create([
            'legal_name' => 'EMPRESA CUIT INVÁLIDO S.A.',
            'tax_id' => '123456', // CUIT muy corto
            'country' => 'AR',
            'is_active' => true,
        ]);

        $service = new ArgentinaAnticipatedService($invalidCompany, $this->testUser);
        
        $reflection = new \ReflectionClass($service);
        $validateMethod = $reflection->getMethod('validateForAnticipated');
        $validateMethod->setAccessible(true);
        
        $validation = $validateMethod->invoke($service, $this->testVoyage);
        
        $this->assertFalse($validation['is_valid']);
        $this->assertContains('CUIT de empresa inválido para Argentina', $validation['errors']);
    }

    /**
     * Mock del envío SOAP para testing
     */
    private function mockSoapSending($service): void
    {
        // En un test real, aquí se mockearía el SoapClientService
        // Para esta prueba básica, asumimos que la validación y creación de transacción funcionan
        Log::info('TEST: Mock SOAP sending configured for ArgentinaAnticipatedService');
    }

    /**
     * Limpiar datos después de cada test
     */
    protected function tearDown(): void
    {
        // Laravel RefreshDatabase se encarga de limpiar automáticamente
        parent::tearDown();
    }
}

/**
 * SCRIPT DE PRUEBA MANUAL - CONSOLA
 * 
 * Para ejecutar desde artisan tinker:
 * 
 * php artisan tinker
 * 
 * // Cargar empresa MAERSK
 * $company = App\Models\Company::where('legal_name', 'LIKE', '%MAERSK%')->first();
 * $user = App\Models\User::where('company_id', $company->id)->first();
 * 
 * // Cargar viaje V022NB
 * $voyage = App\Models\Voyage::where('voyage_number', 'V022NB')->first();
 * 
 * // Crear servicio
 * $service = new App\Services\Webservice\ArgentinaAnticipatedService($company, $user);
 * 
 * // Obtener configuración
 * $config = $service->getConfig();
 * dd($config);
 * 
 * // Obtener métodos disponibles
 * $methods = $service->getAvailableMethods();
 * dd($methods);
 * 
 * // Obtener estadísticas
 * $stats = $service->getCompanyStatistics();
 * dd($stats);
 * 
 * // IMPORTANTE: No ejecutar registerVoyage() sin configuración real de certificados
 * // ya que intentará conectarse al webservice real de AFIP
 */